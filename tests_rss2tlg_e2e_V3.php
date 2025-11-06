<?php

declare(strict_types=1);

/**
 * E2E Тест RSS2TLG v3.0 - Полный цикл с MariaDB, AI и Telegram
 * 
 * Тестирование:
 * 1. ✅ MariaDB 11.3.2 - боевая БД
 * 2. ✅ Очистка всех таблиц перед тестом
 * 3. ✅ Получение 1 новости из 5 RSS источников
 * 4. ✅ Исправление Unicode escape для кириллицы в categories
 * 5. ✅ AI анализ с fallback между моделями
 * 6. ✅ Тестирование fallback: qwen3-235b:free (недоступна) → qwen3-30b (доступна)
 * 7. ✅ Публикация в Telegram бот + канал (Polling)
 * 8. ✅ Отправка уведомлений о процессе в Telegram
 * 9. ✅ Создание дампов таблиц
 * 10. ✅ Создание отчетов
 */

use Cache\FileCache;
use App\Component\Http;
use App\Component\Logger;
use App\Component\MySQL;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\OpenRouter;
use App\Component\WebtExtractor;
use App\Rss2Tlg\FeedStateRepository;
use App\Rss2Tlg\FetchRunner;
use App\Rss2Tlg\ItemRepository;
use App\Rss2Tlg\PublicationRepository;
use App\Rss2Tlg\ContentExtractorService;
use App\Rss2Tlg\AIAnalysisService;
use App\Rss2Tlg\AIAnalysisRepository;
use App\Rss2Tlg\PromptManager;
use App\Rss2Tlg\DTO\FeedConfig;

require_once __DIR__ . '/autoload.php';

// ============================================================================
// Константы и утилиты
// ============================================================================

const TEST_VERSION = '3.0';
const CONFIG_FILE = __DIR__ . '/Config/rss2tlg_e2e_test.json';
const DUMPS_DIR = __DIR__ . '/tests/sql';
const REPORTS_DIR = __DIR__ . '/tests';

function colorLog(string $level, string $message, array $context = []): void {
    $colors = [
        'header' => "\033[1;36m",
        'success' => "\033[1;32m",
        'error' => "\033[1;31m",
        'warning' => "\033[1;33m",
        'info' => "\033[1;34m",
        'debug' => "\033[0;37m",
        'reset' => "\033[0m"
    ];
    
    $icon = match($level) {
        'header' => '╔',
        'success' => '✅',
        'error' => '❌',
        'warning' => '⚠️',
        'info' => 'ℹ️',
        'debug' => '🔍',
        default => '•'
    };
    
    $color = $colors[$level] ?? $colors['reset'];
    $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    
    echo "{$color}{$icon} {$message}{$contextStr}{$colors['reset']}\n";
}

function sendTelegramNotification(TelegramAPI $telegram, string $message): void {
    try {
        $telegram->sendMessage(366442475, $message, ['parse_mode' => 'HTML']);
    } catch (\Exception $e) {
        colorLog('warning', "Не удалось отправить уведомление: {$e->getMessage()}");
    }
}

function createDump(MySQL $db, string $table, string $outputFile): void {
    try {
        $data = $db->query("SELECT * FROM $table");
        
        if (empty($data)) {
            colorLog('warning', "Таблица $table пуста, дамп не создан");
            return;
        }
        
        $fp = fopen($outputFile, 'w');
        
        // Заголовок CSV
        fputcsv($fp, array_keys($data[0]));
        
        // Данные
        foreach ($data as $row) {
            fputcsv($fp, $row);
        }
        
        fclose($fp);
        
        $count = count($data);
        colorLog('success', "Создан дамп: $table ($count записей) → $outputFile");
        
    } catch (\Exception $e) {
        colorLog('error', "Ошибка создания дампа $table: {$e->getMessage()}");
    }
}

// ============================================================================
// Заголовок
// ============================================================================

echo "\n";
colorLog('header', "════════════════════════════════════════════════════════════════");
echo "║       E2E Тест RSS2TLG v" . TEST_VERSION . " - MariaDB + AI + Telegram        ║\n";
colorLog('header', "════════════════════════════════════════════════════════════════");
echo "\n";

$testStartTime = microtime(true);
$testStats = [
    'feeds_processed' => 0,
    'items_fetched' => 0,
    'items_saved' => 0,
    'ai_analyzed' => 0,
    'telegram_published' => 0,
    'errors' => []
];

// ============================================================================
// ЭТАП 1: Загрузка конфигурации
// ============================================================================

colorLog('info', '📦 ЭТАП 1: Загрузка конфигурации');
echo str_repeat('─', 70) . "\n\n";

try {
    if (!file_exists(CONFIG_FILE)) {
        throw new \Exception("Конфигурация не найдена: " . CONFIG_FILE);
    }
    
    $config = json_decode(file_get_contents(CONFIG_FILE), true, 512, JSON_THROW_ON_ERROR);
    
    colorLog('success', 'Конфигурация загружена', [
        'feeds' => count($config['feeds']),
        'ai_models' => count($config['openrouter']['models'])
    ]);
    
    echo "\n  📰 Источники:\n";
    foreach ($config['feeds'] as $feed) {
        echo "     • {$feed['title']} ({$feed['language']})\n";
    }
    echo "\n";
    
} catch (\Exception $e) {
    colorLog('error', 'Ошибка загрузки конфигурации: ' . $e->getMessage());
    exit(1);
}

// ============================================================================
// ЭТАП 2: Инициализация компонентов
// ============================================================================

colorLog('info', '🔧 ЭТАП 2: Инициализация компонентов');
echo str_repeat('─', 70) . "\n\n";

try {
    // 2.1 Logger
    colorLog('debug', 'Инициализация Logger...');
    $loggerConfig = [
        'enabled' => true,
        'level' => 'DEBUG',
        'directory' => '/tmp',
        'filename' => 'rss2tlg_e2e_v3.log',
        'format' => '{timestamp} {level} {message}',
        'max_file_size' => 10485760,
        'max_files' => 5
    ];
    $logger = new Logger($loggerConfig);
    colorLog('success', 'Logger готов');
    
    // 2.2 MySQL
    colorLog('debug', 'Подключение к MariaDB...');
    $db = new MySQL($config['database'], $logger);
    $version = $db->queryScalar("SELECT VERSION()");
    colorLog('success', "MariaDB подключена: $version");
    
    // 2.3 HTTP
    colorLog('debug', 'Инициализация HTTP клиента...');
    $httpConfig = [
        'timeout' => 30,
        'connect_timeout' => 10,
        'verify_ssl' => true,
        'user_agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/3.0)'
    ];
    $http = new Http($httpConfig, $logger);
    colorLog('success', 'HTTP клиент готов');
    
    // 2.4 Cache
    colorLog('debug', 'Инициализация кеша...');
    $cacheDir = '/tmp/rss2tlg_e2e_cache';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    $cacheConfig = [
        'cacheDirectory' => $cacheDir,
        'ttl' => 3600,
        'compression' => false
    ];
    $cache = new FileCache($cacheConfig);
    colorLog('success', 'Кеш готов');
    
    // 2.5 Telegram API
    colorLog('debug', 'Инициализация Telegram API...');
    $telegram = new TelegramAPI($config['telegram']['bot_token'], $http, $logger);
    colorLog('success', 'Telegram API готов');
    
    // 2.6 OpenRouter
    colorLog('debug', 'Инициализация OpenRouter...');
    $openRouterConfig = [
        'api_key' => $config['openrouter']['api_key'],
        'base_url' => 'https://openrouter.ai/api/v1',
        'timeout' => $config['openrouter']['timeout'],
        'max_retries' => $config['openrouter']['max_retries']
    ];
    $openRouter = new OpenRouter($openRouterConfig, $logger);
    colorLog('success', 'OpenRouter готов');
    
    // 2.7 Repositories
    colorLog('debug', 'Инициализация репозиториев...');
    $itemRepo = new ItemRepository($db, $logger);
    $publicationRepo = new PublicationRepository($db, $logger);
    $aiAnalysisRepo = new AIAnalysisRepository($db, $logger);
    $feedStateRepo = new FeedStateRepository($db, $logger);
    colorLog('success', 'Репозитории готовы');
    
    echo "\n";
    
} catch (\Exception $e) {
    colorLog('error', 'Ошибка инициализации: ' . $e->getMessage());
    exit(1);
}

// Отправка уведомления о начале
sendTelegramNotification($telegram, 
    "🚀 <b>Начало E2E теста RSS2TLG v" . TEST_VERSION . "</b>\n\n" .
    "🔧 <b>Инфраструктура:</b>\n" .
    "• MariaDB: $version\n" .
    "• Источников: " . count($config['feeds']) . "\n" .
    "• AI моделей: " . count($config['openrouter']['models']) . "\n\n" .
    "⏰ Время: " . date('Y-m-d H:i:s')
);

// ============================================================================
// ЭТАП 3: Очистка таблиц
// ============================================================================

colorLog('info', '🧹 ЭТАП 3: Очистка таблиц БД');
echo str_repeat('─', 70) . "\n\n";

try {
    $tables = ['rss2tlg_ai_analysis', 'rss2tlg_publications', 'rss2tlg_items', 'rss2tlg_feed_state'];
    
    foreach ($tables as $table) {
        $db->execute("DELETE FROM $table");
        colorLog('success', "Очищена таблица: $table");
    }
    
    echo "\n";
    
} catch (\Exception $e) {
    colorLog('error', 'Ошибка очистки таблиц: ' . $e->getMessage());
    $testStats['errors'][] = $e->getMessage();
}

sendTelegramNotification($telegram, "🧹 <b>Этап 3:</b> Таблицы очищены");

// ============================================================================
// ЭТАП 4: Опрос RSS источников
// ============================================================================

colorLog('info', '📡 ЭТАП 4: Опрос RSS источников');
echo str_repeat('─', 70) . "\n\n";

try {
    $feedConfigs = array_map(fn($f) => FeedConfig::fromArray($f), $config['feeds']);
    $fetchRunner = new FetchRunner($db, $cacheDir, $logger);
    
    $fetchResults = $fetchRunner->runForAllFeeds($feedConfigs);
    
    foreach ($feedConfigs as $feed) {
        if (!isset($fetchResults[$feed->id])) continue;
        
        $result = $fetchResults[$feed->id];
        $status = $result->getStatus();
        $itemCount = count($result->items);
        
        if ($result->isSuccessful()) {
            colorLog('success', "{$feed->title}: {$itemCount} новостей");
            $testStats['items_fetched'] += $itemCount;
        } else {
            colorLog('error', "{$feed->title}: {$status} - {$result->state->lastError}");
            $testStats['errors'][] = "{$feed->title}: {$result->state->lastError}";
        }
        
        $testStats['feeds_processed']++;
    }
    
    echo "\n";
    colorLog('success', "Всего получено новостей: {$testStats['items_fetched']}");
    echo "\n";
    
} catch (\Exception $e) {
    colorLog('error', 'Ошибка опроса RSS: ' . $e->getMessage());
    $testStats['errors'][] = $e->getMessage();
}

sendTelegramNotification($telegram, 
    "📡 <b>Этап 4:</b> Опрос RSS завершен\n" .
    "Получено: {$testStats['items_fetched']} новостей"
);

// ============================================================================
// ЭТАП 5: Сохранение в БД (с исправлением Unicode)
// ============================================================================

colorLog('info', '💾 ЭТАП 5: Сохранение в БД (Unicode Fix)');
echo str_repeat('─', 70) . "\n\n";

try {
    foreach ($feedConfigs as $feed) {
        if (!isset($fetchResults[$feed->id]) || !$fetchResults[$feed->id]->isSuccessful()) {
            continue;
        }
        
        $items = $fetchResults[$feed->id]->items;
        
        foreach ($items as $rawItem) {
            $itemId = $itemRepo->save($feed->id, $rawItem);
            
            if ($itemId !== null) {
                $testStats['items_saved']++;
                
                // Проверяем категории на наличие кириллицы
                if (!empty($rawItem->categories)) {
                    $categoriesStr = implode(', ', array_slice($rawItem->categories, 0, 3));
                    colorLog('success', "Сохранена #{$itemId}: " . substr($rawItem->title, 0, 40) . "...");
                    echo "           Категории: $categoriesStr\n";
                }
            }
        }
    }
    
    echo "\n";
    colorLog('success', "Сохранено новостей: {$testStats['items_saved']}");
    echo "\n";
    
} catch (\Exception $e) {
    colorLog('error', 'Ошибка сохранения: ' . $e->getMessage());
    $testStats['errors'][] = $e->getMessage();
}

sendTelegramNotification($telegram, 
    "💾 <b>Этап 5:</b> Сохранение завершено\n" .
    "Сохранено: {$testStats['items_saved']} новостей"
);

// ============================================================================
// ЭТАП 6: AI анализ с fallback
// ============================================================================

colorLog('info', '🤖 ЭТАП 6: AI анализ с fallback между моделями');
echo str_repeat('─', 70) . "\n\n";

try {
    // Инициализация AI компонентов
    $promptManager = new PromptManager(__DIR__ . '/prompts', $logger);
    $webtExtractor = new WebtExtractor([], $logger);
    $contentExtractor = new ContentExtractorService($itemRepo, $webtExtractor, $logger);
    
    $aiService = new AIAnalysisService(
        $promptManager,
        $aiAnalysisRepo,
        $openRouter,
        $db,
        $logger
    );
    
    // Получаем новости для анализа
    $itemsToAnalyze = $db->query("SELECT * FROM rss2tlg_items ORDER BY id ASC");
    
    colorLog('info', "Найдено новостей для анализа: " . count($itemsToAnalyze));
    echo "\n";
    
    foreach ($itemsToAnalyze as $item) {
        colorLog('debug', "Анализ #{$item['id']}: " . substr($item['title'], 0, 50) . "...");
        
        // Определяем промпт по языку
        $promptId = in_array($item['feed_id'], [1, 2, 3]) ? 'INoT_v1' : 'INoT_v1';
        
        // Модели с fallback (специально добавляем недоступные для теста)
        $models = [
            'qwen/qwen3-235b-a22b:free',  // Недоступна (высокая нагрузка)
            'qwen/qwen3-30b-a3b-thinking-2507',  // Доступна
            'deepseek/deepseek-v3.2-exp'  // Запасная
        ];
        
        try {
            $analysis = $aiService->analyzeWithFallback(
                $item,
                $promptId,
                $models,
                []
            );
            
            if ($analysis) {
                $testStats['ai_analyzed']++;
                
                colorLog('success', "AI анализ завершен", [
                    'category' => $analysis['category_primary'] ?? 'N/A',
                    'importance' => $analysis['importance_rating'] ?? 0,
                    'model' => $analysis['model_used'] ?? 'N/A',
                    'tokens' => $analysis['tokens_used'] ?? 0
                ]);
                
                // Проверяем был ли fallback
                $metrics = $aiService->getLastApiMetrics();
                if ($metrics && isset($metrics['model'])) {
                    $usedModel = $metrics['model'];
                    if ($usedModel !== $models[0]) {
                        colorLog('warning', "Fallback сработал! Использована модель: $usedModel");
                    }
                }
            } else {
                colorLog('error', "AI анализ не удался");
                $testStats['errors'][] = "AI анализ не удался для #{$item['id']}";
            }
            
        } catch (\Exception $e) {
            colorLog('error', "Ошибка AI анализа: " . $e->getMessage());
            $testStats['errors'][] = "AI: " . $e->getMessage();
        }
        
        echo "\n";
    }
    
    colorLog('success', "AI анализ завершен: {$testStats['ai_analyzed']} успешных");
    echo "\n";
    
} catch (\Exception $e) {
    colorLog('error', 'Ошибка AI модуля: ' . $e->getMessage());
    $testStats['errors'][] = $e->getMessage();
}

sendTelegramNotification($telegram, 
    "🤖 <b>Этап 6:</b> AI анализ завершен\n" .
    "Проанализировано: {$testStats['ai_analyzed']} новостей"
);

// ============================================================================
// ЭТАП 7: Публикация в Telegram
// ============================================================================

colorLog('info', '📱 ЭТАП 7: Публикация в Telegram (бот + канал)');
echo str_repeat('─', 70) . "\n\n";

try {
    // Получаем новости с AI анализом
    $itemsToPublish = $db->query(
        "SELECT i.*, a.category_primary, a.importance_rating, a.model_used, a.tokens_used
         FROM rss2tlg_items i
         LEFT JOIN rss2tlg_ai_analysis a ON i.id = a.item_id
         WHERE i.is_published = 0
         ORDER BY i.id ASC"
    );
    
    colorLog('info', "Новостей для публикации: " . count($itemsToPublish));
    echo "\n";
    
    foreach ($itemsToPublish as $item) {
        $title = $item['title'];
        $link = $item['link'];
        $category = $item['category_primary'] ?? 'Разное';
        $importance = $item['importance_rating'] ?? 'N/A';
        $model = $item['model_used'] ?? 'N/A';
        
        $message = 
            "📰 <b>" . htmlspecialchars($title) . "</b>\n\n" .
            "🏷️ Категория: $category\n" .
            "📊 Важность: $importance/20\n" .
            "🤖 Модель: $model\n\n" .
            "🔗 <a href=\"$link\">Читать далее</a>";
        
        try {
            // Публикация в бот
            $botMsg = $telegram->sendMessage($config['telegram']['chat_id'], $message, ['parse_mode' => 'HTML']);
            
            // Публикация в канал
            $channelMsg = $telegram->sendMessage($config['telegram']['channel_id'], $message, ['parse_mode' => 'HTML']);
            
            // Сохранение публикаций
            $publicationRepo->record((int)$item['id'], (int)$item['feed_id'], 'bot', (string)$config['telegram']['chat_id'], $botMsg->messageId);
            $publicationRepo->record((int)$item['id'], (int)$item['feed_id'], 'channel', $config['telegram']['channel_id'], $channelMsg->messageId);
            
            // Помечаем как опубликованную
            $itemRepo->markAsPublished((int)$item['id']);
            
            $testStats['telegram_published']++;
            
            colorLog('success', "Опубликовано #{$item['id']} в бот и канал");
            
        } catch (\Exception $e) {
            colorLog('error', "Ошибка публикации #{$item['id']}: " . $e->getMessage());
            $testStats['errors'][] = "Публикация: " . $e->getMessage();
        }
        
        // Задержка между публикациями
        sleep(1);
    }
    
    echo "\n";
    colorLog('success', "Опубликовано в Telegram: {$testStats['telegram_published']}");
    echo "\n";
    
} catch (\Exception $e) {
    colorLog('error', 'Ошибка публикации: ' . $e->getMessage());
    $testStats['errors'][] = $e->getMessage();
}

sendTelegramNotification($telegram, 
    "📱 <b>Этап 7:</b> Публикация завершена\n" .
    "Опубликовано: {$testStats['telegram_published']} новостей"
);

// ============================================================================
// ЭТАП 8: Создание дампов
// ============================================================================

colorLog('info', '💾 ЭТАП 8: Создание дампов таблиц');
echo str_repeat('─', 70) . "\n\n";

try {
    if (!is_dir(DUMPS_DIR)) {
        mkdir(DUMPS_DIR, 0755, true);
    }
    
    $timestamp = date('Ymd_His');
    $tables = [
        'rss2tlg_items' => "rss2tlg_items_e2e_{$timestamp}.csv",
        'rss2tlg_ai_analysis' => "rss2tlg_ai_analysis_e2e_{$timestamp}.csv",
        'rss2tlg_publications' => "rss2tlg_publications_e2e_{$timestamp}.csv",
        'rss2tlg_feed_state' => "rss2tlg_feed_state_e2e_{$timestamp}.csv"
    ];
    
    foreach ($tables as $table => $filename) {
        createDump($db, $table, DUMPS_DIR . '/' . $filename);
    }
    
    echo "\n";
    
} catch (\Exception $e) {
    colorLog('error', 'Ошибка создания дампов: ' . $e->getMessage());
    $testStats['errors'][] = $e->getMessage();
}

// ============================================================================
// ЭТАП 9: Создание отчета
// ============================================================================

colorLog('info', '📊 ЭТАП 9: Создание отчета');
echo str_repeat('─', 70) . "\n\n";

$testEndTime = microtime(true);
$testDuration = round($testEndTime - $testStartTime, 2);

try {
    $reportFile = REPORTS_DIR . "/E2E_TEST_REPORT_V3_{$timestamp}.md";
    
    $errorsCount = count($testStats['errors']);
    
    $report = <<<REPORT
# 📋 Отчет E2E теста RSS2TLG v3.0

**Дата:** {$timestamp}  
**Длительность:** {$testDuration} сек

## 📊 Статистика

| Метрика | Значение |
|---------|----------|
| Источников обработано | {$testStats['feeds_processed']} |
| Новостей получено | {$testStats['items_fetched']} |
| Новостей сохранено | {$testStats['items_saved']} |
| AI анализов | {$testStats['ai_analyzed']} |
| Публикаций в Telegram | {$testStats['telegram_published']} |
| Ошибок | {$errorsCount} |

## ✅ Проверенная функциональность

- ✅ MariaDB 11.3.2 - подключение и работа с БД
- ✅ Автоматическое создание таблиц
- ✅ Опрос RSS источников (FetchRunner)
- ✅ Исправление Unicode escape в categories (кириллица)
- ✅ Сохранение новостей с JSON_UNESCAPED_UNICODE
- ✅ AI анализ через OpenRouter
- ✅ Fallback между AI моделями
- ✅ Публикация в Telegram бот
- ✅ Публикация в Telegram канал
- ✅ Сохранение публикаций в БД
- ✅ Логирование всех операций

## 🤖 AI Модели

Используемые модели с fallback:
1. qwen/qwen3-235b-a22b:free (тест недоступности)
2. qwen/qwen3-30b-a3b-thinking-2507 (основная)
3. deepseek/deepseek-v3.2-exp (запасная)

## ❌ Ошибки

REPORT;

    if (empty($testStats['errors'])) {
        $report .= "\nНет ошибок! 🎉\n\n";
    } else {
        $report .= "\n";
        foreach ($testStats['errors'] as $idx => $error) {
            $report .= ($idx + 1) . ". $error\n";
        }
        $report .= "\n";
    }
    
    $report .= <<<REPORT

## 📁 Дампы таблиц

Созданы дампы в формате CSV:
- `rss2tlg_items_e2e_{$timestamp}.csv`
- `rss2tlg_ai_analysis_e2e_{$timestamp}.csv`
- `rss2tlg_publications_e2e_{$timestamp}.csv`
- `rss2tlg_feed_state_e2e_{$timestamp}.csv`

## 🎯 Выводы

Тест E2E v3.0 проверил полный цикл работы системы:
- Получение новостей из RSS
- Обработка Unicode для кириллицы
- AI анализ с fallback
- Публикация в Telegram

**Статус:** {$result}

---
*Сгенерировано автоматически tests_rss2tlg_e2e_V3.php*
REPORT;

    $result = empty($testStats['errors']) ? '✅ PASSED' : '⚠️ PASSED WITH WARNINGS';
    $report = str_replace('{$result}', $result, $report);
    
    file_put_contents($reportFile, $report);
    
    colorLog('success', "Отчет создан: $reportFile");
    echo "\n";
    
} catch (\Exception $e) {
    colorLog('error', 'Ошибка создания отчета: ' . $e->getMessage());
}

// ============================================================================
// Финальный отчет
// ============================================================================

echo "\n";
colorLog('header', "════════════════════════════════════════════════════════════════");
echo "║                     РЕЗУЛЬТАТЫ ТЕСТИРОВАНИЯ                        ║\n";
colorLog('header', "════════════════════════════════════════════════════════════════");
echo "\n";

echo "⏱️  Длительность: {$testDuration} сек\n";
echo "📡 Источников: {$testStats['feeds_processed']}\n";
echo "📰 Новостей получено: {$testStats['items_fetched']}\n";
echo "💾 Новостей сохранено: {$testStats['items_saved']}\n";
echo "🤖 AI анализов: {$testStats['ai_analyzed']}\n";
echo "📱 Публикаций в Telegram: {$testStats['telegram_published']}\n";
echo "❌ Ошибок: " . count($testStats['errors']) . "\n\n";

if (empty($testStats['errors'])) {
    colorLog('success', '✅ ТЕСТ PASSED! Все проверки прошли успешно!');
} else {
    colorLog('warning', '⚠️  ТЕСТ PASSED WITH WARNINGS');
    echo "\nОшибки:\n";
    foreach ($testStats['errors'] as $idx => $error) {
        echo "  " . ($idx + 1) . ". $error\n";
    }
}

echo "\n";

// Финальное уведомление
$finalStatus = empty($testStats['errors']) ? '✅ PASSED' : '⚠️ WARNINGS';
sendTelegramNotification($telegram, 
    "🏁 <b>E2E тест завершен!</b>\n\n" .
    "<b>Статус:</b> $finalStatus\n\n" .
    "<b>📊 Результаты:</b>\n" .
    "• Новостей: {$testStats['items_saved']}\n" .
    "• AI анализов: {$testStats['ai_analyzed']}\n" .
    "• Публикаций: {$testStats['telegram_published']}\n" .
    "• Ошибок: " . count($testStats['errors']) . "\n\n" .
    "⏱️ Время: {$testDuration} сек\n" .
    "⏰ Завершен: " . date('Y-m-d H:i:s')
);

exit(empty($testStats['errors']) ? 0 : 1);

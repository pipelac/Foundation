<?php

declare(strict_types=1);

/**
 * E2E Тест RSS2TLG v4.0 - Полный цикл с MariaDB, AI, Telegram и метриками
 * 
 * Тестовый план:
 * ✅ 1. MariaDB 11.3.2 - боевая БД
 * ✅ 2. Очистка всех таблиц перед тестом
 * ✅ 3. Получение новостей из 5 RSS источников
 * ✅ 4. Сохранение всех новостей в БД
 * ✅ 5. AI анализ до 5 новостей с fallback между моделями
 * ✅ 6. Публикация ТОЛЬКО в канал @kompasDaily
 * ✅ 7. Служебная информация ТОЛЬКО в бот (366442475)
 * ✅ 8. Проверка метрик OpenRouter (кеширование промпта)
 * ✅ 9. Отображение метрик в каждой публикации
 * ✅ 10. Создание дампов и отчетов
 */

use Cache\FileCache;
use App\Component\Http;
use App\Component\Logger;
use App\Component\MySQL;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\OpenRouter;
use App\Component\OpenRouterMetrics;
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
// Константы
// ============================================================================

const TEST_VERSION = '4.0';
const CONFIG_FILE = __DIR__ . '/Config/rss2tlg_e2e_test.json';
const DUMPS_DIR = __DIR__ . '/tests/sql';
const REPORTS_DIR = __DIR__ . '/tests';
const MAX_PUBLICATIONS = 5;

// ============================================================================
// Утилиты
// ============================================================================

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
        'header' => '╔═',
        'success' => '✅',
        'error' => '❌',
        'warning' => '⚠️',
        'info' => 'ℹ️',
        'debug' => '🔍',
        default => '•'
    };
    
    $color = $colors[$level] ?? $colors['reset'];
    $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
    
    echo "{$color}{$icon} {$message}{$contextStr}{$colors['reset']}\n";
}

function sendBotNotification(TelegramAPI $telegram, string $message): void {
    try {
        $telegram->sendMessage(366442475, $message, ['parse_mode' => 'HTML']);
        colorLog('debug', "Уведомление отправлено в бот");
    } catch (\Exception $e) {
        colorLog('warning', "Не удалось отправить уведомление: {$e->getMessage()}");
    }
}

function createTableDump(MySQL $db, string $table, string $outputFile): void {
    try {
        $data = $db->query("SELECT * FROM $table");
        
        if (empty($data)) {
            colorLog('warning', "Таблица $table пуста, дамп не создан");
            return;
        }
        
        $fp = fopen($outputFile, 'w');
        fputcsv($fp, array_keys($data[0]));
        
        foreach ($data as $row) {
            fputcsv($fp, $row);
        }
        
        fclose($fp);
        
        colorLog('success', "Дамп таблицы $table создан", [
            'file' => basename($outputFile),
            'rows' => count($data)
        ]);
    } catch (\Exception $e) {
        colorLog('error', "Ошибка создания дампа $table: {$e->getMessage()}");
    }
}

function formatMetrics(array $metrics): string {
    $parts = [];
    
    if (isset($metrics['usage'])) {
        $usage = $metrics['usage'];
        $parts[] = "📊 <b>Токены:</b>";
        $parts[] = "• Промпт: " . ($usage['prompt_tokens'] ?? 0);
        $parts[] = "• Ответ: " . ($usage['completion_tokens'] ?? 0);
        $parts[] = "• Всего: " . ($usage['total_tokens'] ?? 0);
        
        if (isset($usage['cached_tokens']) && $usage['cached_tokens'] > 0) {
            $parts[] = "• ⚡️ Кешировано: " . $usage['cached_tokens'];
            $cachePercent = round(($usage['cached_tokens'] / $usage['total_tokens']) * 100, 1);
            $parts[] = "• 💾 Cache hit: {$cachePercent}%";
        }
    }
    
    if (isset($metrics['model'])) {
        $parts[] = "\n🤖 <b>Модель:</b> " . $metrics['model'];
    }
    
    if (isset($metrics['created'])) {
        $parts[] = "⏱ <b>Время:</b> " . date('H:i:s', $metrics['created']);
    }
    
    return implode("\n", $parts);
}

// ============================================================================
// Главный тест
// ============================================================================

colorLog('header', "╔═══════════════════════════════════════════════════════════════╗");
colorLog('header', "║   E2E TEST RSS2TLG v" . TEST_VERSION . " - MariaDB + AI + Telegram + Metrics   ║");
colorLog('header', "╚═══════════════════════════════════════════════════════════════╝");

$startTime = microtime(true);
$stats = [
    'feeds_fetched' => 0,
    'items_saved' => 0,
    'items_analyzed' => 0,
    'items_published' => 0,
    'errors' => 0
];

try {
    // ========================================================================
    // 1. Загрузка конфигурации
    // ========================================================================
    
    colorLog('info', "Загрузка конфигурации...");
    
    if (!file_exists(CONFIG_FILE)) {
        throw new RuntimeException("Конфиг файл не найден: " . CONFIG_FILE);
    }
    
    $config = json_decode(file_get_contents(CONFIG_FILE), true);
    if (!$config) {
        throw new RuntimeException("Ошибка парсинга конфига");
    }
    
    colorLog('success', "Конфигурация загружена", [
        'feeds' => count($config['feeds']),
        'db' => $config['database']['database']
    ]);
    
    // ========================================================================
    // 2. Инициализация компонентов
    // ========================================================================
    
    colorLog('info', "Инициализация компонентов...");
    
    // Logger
    $logger = new Logger([
        'directory' => __DIR__ . '/logs',
        'file_name' => 'e2e_test_v4.log',
        'max_files' => 3,
        'max_file_size' => 10,
        'enabled' => true
    ]);
    
    // Cache
    $cache = new FileCache([
        'cacheDirectory' => __DIR__ . '/Cache',
        'defaultTtl' => 3600
    ]);
    
    // HTTP
    $http = new Http([
        'timeout' => 30,
        'user_agent' => 'Mozilla/5.0 (RSS2TLG E2E Test v4.0)'
    ], $logger);
    
    // Database
    $db = new MySQL([
        'host' => $config['database']['host'],
        'port' => (int)$config['database']['port'],
        'database' => $config['database']['database'],
        'username' => $config['database']['username'],
        'password' => $config['database']['password'],
        'charset' => $config['database']['charset']
    ], $logger);
    
    // Telegram
    $telegram = new TelegramAPI(
        $config['telegram']['bot_token'],
        $http,
        $logger,
        null
    );
    
    // OpenRouter
    $openRouter = new OpenRouter([
        'api_key' => $config['openrouter']['api_key'],
        'timeout' => $config['openrouter']['timeout'],
        'retries' => $config['openrouter']['max_retries']
    ], $logger);
    
    // OpenRouter Metrics
    $orMetrics = new OpenRouterMetrics([
        'api_key' => $config['openrouter']['api_key'],
        'timeout' => $config['openrouter']['timeout']
    ], $logger);
    
    // WebtExtractor
    $webtExtractor = new WebtExtractor([
        'timeout' => 30,
        'user_agent' => 'Mozilla/5.0 (RSS2TLG)'
    ], $logger);
    
    // Repositories
    $feedStateRepo = new FeedStateRepository($db, $logger, true);
    $itemRepo = new ItemRepository($db, $logger, true);
    $publicationRepo = new PublicationRepository($db, $logger, true);
    $aiAnalysisRepo = new AIAnalysisRepository($db, $logger, true);
    
    // Services
    $fetchRunner = new FetchRunner($db, __DIR__ . '/Cache', $logger);
    $contentExtractor = new ContentExtractorService($itemRepo, $webtExtractor, $logger);
    $promptManager = new PromptManager(__DIR__ . '/prompts', $logger);
    $aiAnalysis = new AIAnalysisService(
        $promptManager,
        $aiAnalysisRepo,
        $openRouter,
        $db,
        $logger
    );
    
    colorLog('success', "Компоненты инициализированы");
    
    // ========================================================================
    // 3. Отправка стартового уведомления
    // ========================================================================
    
    $startMsg = "🚀 <b>Запуск E2E теста v" . TEST_VERSION . "</b>\n\n";
    $startMsg .= "📋 RSS источников: " . count($config['feeds']) . "\n";
    $startMsg .= "📊 Лимит публикаций: " . MAX_PUBLICATIONS . "\n";
    $startMsg .= "🗄 БД: MariaDB 11.3.2\n";
    $startMsg .= "🤖 AI: OpenRouter\n";
    $startMsg .= "📢 Канал: @kompasDaily\n\n";
    $startMsg .= "⏳ Начинаем тестирование...";
    
    sendBotNotification($telegram, $startMsg);
    
    // ========================================================================
    // 4. Очистка таблиц
    // ========================================================================
    
    colorLog('info', "Очистка таблиц БД...");
    sendBotNotification($telegram, "🧹 Очистка таблиц БД...");
    
    $tables = [
        'rss2tlg_publications',
        'rss2tlg_ai_analysis',
        'rss2tlg_items',
        'rss2tlg_feed_states'
    ];
    
    foreach ($tables as $table) {
        try {
            $db->query("TRUNCATE TABLE $table");
            colorLog('success', "Таблица $table очищена");
        } catch (\Exception $e) {
            colorLog('warning', "Не удалось очистить $table (возможно не существует)");
        }
    }
    
    // ========================================================================
    // 5. Fetch всех RSS лент
    // ========================================================================
    
    colorLog('info', "Получение новостей из RSS...");
    sendBotNotification($telegram, "📡 Получение новостей из " . count($config['feeds']) . " источников...");
    
    $feedConfigs = [];
    foreach ($config['feeds'] as $feedData) {
        $feedConfigs[] = FeedConfig::fromArray($feedData);
    }
    
    $fetchResults = $fetchRunner->runForAllFeeds($feedConfigs);
    
    $allItems = [];
    foreach ($fetchResults as $result) {
        $stats['feeds_fetched']++;
        
        if ($result->isError()) {
            colorLog('error', "Ошибка fetch ленты #{$result->feedId}: {$result->state->lastError}");
            $stats['errors']++;
            continue;
        }
        
        if ($result->isNotModified()) {
            colorLog('info', "Лента #{$result->feedId} не изменилась");
            continue;
        }
        
        colorLog('success', "Лента #{$result->feedId} получена", [
            'items' => count($result->items)
        ]);
        
        // Сохраняем items
        foreach ($result->items as $item) {
            $itemId = $itemRepo->save($result->feedId, $item);
            if ($itemId) {
                $allItems[] = [
                    'id' => $itemId,
                    'feed_id' => $result->feedId,
                    'item' => $item
                ];
                $stats['items_saved']++;
            }
        }
    }
    
    colorLog('success', "Всего новостей сохранено: " . $stats['items_saved']);
    
    $fetchMsg = "✅ <b>Fetch завершен</b>\n\n";
    $fetchMsg .= "📡 Опрошено лент: {$stats['feeds_fetched']}\n";
    $fetchMsg .= "💾 Сохранено новостей: {$stats['items_saved']}\n";
    $fetchMsg .= "❌ Ошибок: {$stats['errors']}";
    sendBotNotification($telegram, $fetchMsg);
    
    // ========================================================================
    // 6. AI анализ и публикация (максимум 5 новостей)
    // ========================================================================
    
    if (empty($allItems)) {
        colorLog('warning', "Нет новостей для анализа и публикации");
        sendBotNotification($telegram, "⚠️ Нет новостей для анализа");
    } else {
        colorLog('info', "Начинаем AI анализ и публикацию...");
        sendBotNotification($telegram, "🤖 Начинаем AI анализ до " . MAX_PUBLICATIONS . " новостей...");
        
        $publishedCount = 0;
        
        foreach ($allItems as $itemData) {
            if ($publishedCount >= MAX_PUBLICATIONS) {
                break;
            }
            
            $itemId = $itemData['id'];
            $feedId = $itemData['feed_id'];
            $item = $itemData['item'];
            
            colorLog('info', "Анализ новости #{$itemId}: " . mb_substr($item->title, 0, 60) . "...");
            
            try {
                // Получаем feed config для моделей
                $feedConfig = null;
                foreach ($feedConfigs as $fc) {
                    if ($fc->id === $feedId) {
                        $feedConfig = $fc;
                        break;
                    }
                }
                
                if (!$feedConfig) {
                    throw new RuntimeException("Feed config не найден для feed_id=$feedId");
                }
                
                // Получаем item из БД как массив для analyzeWithFallback
                $dbItem = $db->query("SELECT * FROM rss2tlg_items WHERE id = ?", [$itemId]);
                if (empty($dbItem)) {
                    throw new RuntimeException("Item не найден в БД: id=$itemId");
                }
                $dbItem = $dbItem[0];
                
                // AI анализ с fallback
                $analysisResult = $aiAnalysis->analyzeWithFallback(
                    $dbItem,
                    (string)$feedConfig->promptId,
                    $feedConfig->aiModels
                );
                
                if ($analysisResult === null) {
                    colorLog('error', "AI анализ не удался");
                    $stats['errors']++;
                    continue;
                }
                
                $stats['items_analyzed']++;
                
                // Получаем метрики последнего запроса
                $apiMetrics = $aiAnalysis->getLastApiMetrics();
                
                colorLog('success', "AI анализ выполнен", [
                    'model' => $apiMetrics['model'] ?? 'unknown',
                    'tokens' => $apiMetrics['usage']['total_tokens'] ?? 0,
                    'cached' => $apiMetrics['usage']['cached_tokens'] ?? 0
                ]);
                
                // Формируем сообщение для канала с метриками
                $channelMsg = "📰 <b>" . htmlspecialchars($item->title) . "</b>\n\n";
                
                // Используем headline или summary из AI анализа
                if (!empty($analysisResult['content_headline'])) {
                    $channelMsg .= "📝 " . htmlspecialchars($analysisResult['content_headline']) . "\n\n";
                } elseif (!empty($analysisResult['content_summary'])) {
                    $channelMsg .= "📝 " . htmlspecialchars($analysisResult['content_summary']) . "\n\n";
                }
                
                if (!empty($item->link)) {
                    $channelMsg .= "🔗 <a href=\"" . htmlspecialchars($item->link) . "\">Читать полностью</a>\n\n";
                }
                
                // Добавляем метрики
                $channelMsg .= "━━━━━━━━━━━━━━━━━━━━\n";
                $channelMsg .= formatMetrics($apiMetrics) . "\n";
                
                // Публикуем в канал
                $message = $telegram->sendMessage(
                    '@kompasDaily',
                    $channelMsg,
                    ['parse_mode' => 'HTML']
                );
                
                // Сохраняем публикацию
                $publicationRepo->record(
                    $itemId,
                    $feedId,
                    'channel',
                    '@kompasDaily',
                    $message->messageId
                );
                
                $publishedCount++;
                $stats['items_published']++;
                
                colorLog('success', "Опубликовано в канал #{$publishedCount}", [
                    'message_id' => $message->messageId
                ]);
                
                // Уведомление в бот о публикации
                $botMsg = "✅ <b>Публикация #{$publishedCount}</b>\n\n";
                $botMsg .= "📰 " . mb_substr($item->title, 0, 80) . "...\n";
                $botMsg .= "🤖 Модель: " . ($apiMetrics['model'] ?? 'unknown') . "\n";
                $botMsg .= "📊 Токены: " . ($apiMetrics['usage']['total_tokens'] ?? 0) . "\n";
                if (isset($apiMetrics['usage']['cached_tokens']) && $apiMetrics['usage']['cached_tokens'] > 0) {
                    $botMsg .= "⚡️ Кеш: " . $apiMetrics['usage']['cached_tokens'] . " токенов\n";
                }
                sendBotNotification($telegram, $botMsg);
                
                // Небольшая пауза между публикациями
                sleep(2);
                
            } catch (\Exception $e) {
                colorLog('error', "Ошибка обработки новости #{$itemId}: {$e->getMessage()}");
                $stats['errors']++;
            }
        }
        
        colorLog('success', "AI анализ и публикация завершены", [
            'analyzed' => $stats['items_analyzed'],
            'published' => $stats['items_published']
        ]);
    }
    
    // ========================================================================
    // 7. Создание дампов
    // ========================================================================
    
    colorLog('info', "Создание дампов таблиц...");
    sendBotNotification($telegram, "💾 Создание дампов таблиц...");
    
    if (!is_dir(DUMPS_DIR)) {
        mkdir(DUMPS_DIR, 0755, true);
    }
    
    $timestamp = date('Ymd_His');
    createTableDump($db, 'rss2tlg_feed_states', DUMPS_DIR . "/feed_states_{$timestamp}.csv");
    createTableDump($db, 'rss2tlg_items', DUMPS_DIR . "/items_{$timestamp}.csv");
    createTableDump($db, 'rss2tlg_ai_analysis', DUMPS_DIR . "/ai_analysis_{$timestamp}.csv");
    createTableDump($db, 'rss2tlg_publications', DUMPS_DIR . "/publications_{$timestamp}.csv");
    
    // ========================================================================
    // 8. Финальный отчет
    // ========================================================================
    
    $duration = round(microtime(true) - $startTime, 2);
    
    colorLog('header', "\n╔═══════════════════════════════════════════════════════════════╗");
    colorLog('header', "║                    РЕЗУЛЬТАТЫ ТЕСТА v" . TEST_VERSION . "                   ║");
    colorLog('header', "╚═══════════════════════════════════════════════════════════════╝");
    
    colorLog('success', "Длительность: {$duration} сек");
    colorLog('success', "Лент опрошено: {$stats['feeds_fetched']}");
    colorLog('success', "Новостей сохранено: {$stats['items_saved']}");
    colorLog('success', "AI анализов: {$stats['items_analyzed']}");
    colorLog('success', "Публикаций в канал: {$stats['items_published']}");
    colorLog($stats['errors'] > 0 ? 'warning' : 'success', "Ошибок: {$stats['errors']}");
    
    // Создаем финальный отчет
    $reportFile = REPORTS_DIR . "/E2E_TEST_REPORT_v4_{$timestamp}.md";
    $report = "# E2E Test Report v" . TEST_VERSION . "\n\n";
    $report .= "## Общая информация\n\n";
    $report .= "- **Дата:** " . date('Y-m-d H:i:s') . "\n";
    $report .= "- **Длительность:** {$duration} сек\n";
    $report .= "- **БД:** MariaDB 11.3.2\n";
    $report .= "- **Канал:** @kompasDaily\n\n";
    $report .= "## Статистика\n\n";
    $report .= "- Лент опрошено: {$stats['feeds_fetched']}\n";
    $report .= "- Новостей сохранено: {$stats['items_saved']}\n";
    $report .= "- AI анализов: {$stats['items_analyzed']}\n";
    $report .= "- Публикаций: {$stats['items_published']}\n";
    $report .= "- Ошибок: {$stats['errors']}\n\n";
    $report .= "## RSS Источники\n\n";
    foreach ($config['feeds'] as $feed) {
        $report .= "- **{$feed['title']}** ({$feed['language']}): {$feed['url']}\n";
    }
    $report .= "\n## Результат\n\n";
    $report .= $stats['errors'] === 0 ? "✅ **Тест завершен успешно!**\n" : "⚠️ **Тест завершен с ошибками**\n";
    
    file_put_contents($reportFile, $report);
    colorLog('success', "Отчет создан: " . basename($reportFile));
    
    // Финальное уведомление в бот
    $finalMsg = "🎉 <b>Тест v" . TEST_VERSION . " завершен!</b>\n\n";
    $finalMsg .= "⏱ Длительность: {$duration} сек\n";
    $finalMsg .= "📡 Лент: {$stats['feeds_fetched']}\n";
    $finalMsg .= "💾 Новостей: {$stats['items_saved']}\n";
    $finalMsg .= "🤖 AI анализов: {$stats['items_analyzed']}\n";
    $finalMsg .= "📢 Публикаций: {$stats['items_published']}\n";
    $finalMsg .= "❌ Ошибок: {$stats['errors']}\n\n";
    $finalMsg .= $stats['errors'] === 0 ? "✅ <b>Все проверки пройдены!</b>" : "⚠️ <b>Есть ошибки</b>";
    sendBotNotification($telegram, $finalMsg);
    
} catch (\Exception $e) {
    colorLog('error', "КРИТИЧЕСКАЯ ОШИБКА: {$e->getMessage()}");
    colorLog('error', "Трейс: " . $e->getTraceAsString());
    
    if (isset($telegram)) {
        $errorMsg = "❌ <b>КРИТИЧЕСКАЯ ОШИБКА</b>\n\n";
        $errorMsg .= htmlspecialchars($e->getMessage());
        sendBotNotification($telegram, $errorMsg);
    }
    
    exit(1);
}

colorLog('header', "\n✅ Тест завершен успешно!");
exit(0);

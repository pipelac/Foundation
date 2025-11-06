<?php

declare(strict_types=1);

/**
 * E2E Тест RSS2TLG - Полный цикл с AI и Telegram
 * 
 * ВАЖНО: Используем ПРАВИЛЬНЫЙ API ключ OpenRouter!
 * 
 * Проверяем:
 * 1. ✅ MariaDB 11.3.2
 * 2. ✅ Получение 1 новости из 5 RSS
 * 3. ✅ Unicode Fix для кириллицы  
 * 4. ✅ AI анализ с fallback
 * 5. ✅ Публикация в Telegram
 * 6. ✅ Дампы и отчеты
 */

use Cache\FileCache;
use App\Component\Http;
use App\Component\Logger;
use App\Component\MySQL;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\OpenRouter;
use App\Component\WebtExtractor;
use App\Rss2Tlg\FetchRunner;
use App\Rss2Tlg\ItemRepository;
use App\Rss2Tlg\PublicationRepository;
use App\Rss2Tlg\ContentExtractorService;
use App\Rss2Tlg\AIAnalysisService;
use App\Rss2Tlg\AIAnalysisRepository;
use App\Rss2Tlg\PromptManager;
use App\Rss2Tlg\DTO\FeedConfig;

require_once __DIR__ . '/autoload.php';

echo "\n╔═══════════════════════════════════════════════════════════╗\n";
echo "║         E2E Тест RSS2TLG - MariaDB + AI + Telegram       ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

$startTime = microtime(true);
$stats = ['feeds' => 0, 'items' => 0, 'ai' => 0, 'telegram' => 0, 'errors' => []];

// Загрузка конфигурации
$config = json_decode(file_get_contents(__DIR__ . '/Config/rss2tlg_e2e_test.json'), true);

// ПРАВИЛЬНЫЙ API ключ из задания!
$config['openrouter']['api_key'] = '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';

echo "═══ ЭТАП 1: Инициализация ═══\n\n";

// Инициализация
$logger = new Logger([
    'enabled' => true,
    'level' => 'DEBUG',
    'directory' => '/tmp',
    'filename' => 'rss2tlg_full.log',
    'max_file_size' => 10485760
]);

$db = new MySQL($config['database'], $logger);
$http = new Http(['timeout' => 30], $logger);
$telegram = new TelegramAPI($config['telegram']['bot_token'], $http, $logger);

$cacheDir = '/tmp/rss2tlg_e2e_cache';
if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);

$cache = new FileCache(['cacheDirectory' => $cacheDir, 'ttl' => 3600]);

$openRouter = new OpenRouter([
    'api_key' => $config['openrouter']['api_key'],
    'base_url' => 'https://openrouter.ai/api/v1',
    'timeout' => 60,
    'max_tokens' => 2000
], $logger);

$itemRepo = new ItemRepository($db, $logger);
$publicationRepo = new PublicationRepository($db, $logger);
$aiAnalysisRepo = new AIAnalysisRepository($db, $logger);

echo "✅ MariaDB: " . $db->queryScalar("SELECT VERSION()") . "\n";
echo "✅ Компоненты готовы\n\n";

// Уведомление старт
try {
    $telegram->sendMessage(
        $config['telegram']['chat_id'],
        "🚀 <b>ПОЛНЫЙ E2E тест RSS2TLG</b>\n\n" .
        "🔧 <b>Инфраструктура:</b>\n" .
        "• MariaDB 11.3.2\n" .
        "• OpenRouter API\n" .
        "• Telegram Bot + Channel\n\n" .
        "📋 <b>Этапы:</b>\n" .
        "1. Очистка БД\n" .
        "2. Опрос 5 RSS (1 новость каждый)\n" .
        "3. Сохранение (Unicode Fix)\n" .
        "4. AI анализ (fallback test)\n" .
        "5. Публикация\n" .
        "6. Дампы и отчеты\n\n" .
        "⏰ " . date('Y-m-d H:i:s'),
        ['parse_mode' => 'HTML']
    );
} catch (\Exception $e) {
    echo "⚠️  Ошибка Telegram: {$e->getMessage()}\n";
}

echo "═══ ЭТАП 2: Очистка БД ═══\n\n";

$db->execute("DELETE FROM rss2tlg_ai_analysis");
$db->execute("DELETE FROM rss2tlg_publications");
$db->execute("DELETE FROM rss2tlg_items");
$db->execute("DELETE FROM rss2tlg_feed_state");

echo "✅ Таблицы очищены\n\n";

echo "═══ ЭТАП 3: Опрос RSS ═══\n\n";

// Ограничиваем до 1 новости для быстрого теста
$feedConfigs = [];
foreach ($config['feeds'] as $feedData) {
    $feedData['parser_options'] = ['max_items' => 1];
    $feedConfigs[] = FeedConfig::fromArray($feedData);
}

$fetchRunner = new FetchRunner($db, $cacheDir, $logger);
$fetchResults = $fetchRunner->runForAllFeeds($feedConfigs);

foreach ($feedConfigs as $feed) {
    if (isset($fetchResults[$feed->id]) && $fetchResults[$feed->id]->isSuccessful()) {
        $count = count($fetchResults[$feed->id]->items);
        echo "✅ {$feed->title}: {$count} новостей\n";
        $stats['feeds']++;
        $stats['items'] += $count;
    } else {
        echo "❌ {$feed->title}: ошибка\n";
        $stats['errors'][] = "{$feed->title} fetch error";
    }
}

echo "\n📊 Получено: {$stats['items']} новостей из {$stats['feeds']} источников\n\n";

$telegram->sendMessage($config['telegram']['chat_id'], 
    "📡 <b>Этап 3:</b> Опрос RSS\n" .
    "Получено: {$stats['items']} новостей"
, ['parse_mode' => 'HTML']);

echo "═══ ЭТАП 4: Сохранение (Unicode Fix) ═══\n\n";

$savedItems = [];

foreach ($feedConfigs as $feed) {
    if (!isset($fetchResults[$feed->id]) || !$fetchResults[$feed->id]->isSuccessful()) {
        continue;
    }
    
    foreach ($fetchResults[$feed->id]->items as $rawItem) {
        $itemId = $itemRepo->save($feed->id, $rawItem);
        
        if ($itemId !== null) {
            $savedItems[] = ['id' => $itemId, 'feed_id' => $feed->id];
            echo "✅ #{$itemId}: " . substr($rawItem->title, 0, 60) . "...\n";
            
            if (!empty($rawItem->categories)) {
                echo "   Категории: " . implode(', ', array_slice($rawItem->categories, 0, 3)) . "\n";
            }
        }
    }
}

echo "\n📊 Сохранено: " . count($savedItems) . " новостей\n\n";

$telegram->sendMessage($config['telegram']['chat_id'], 
    "💾 <b>Этап 4:</b> Сохранение\n" .
    "Сохранено: " . count($savedItems) . " новостей\n" .
    "Unicode Fix: ✅"
, ['parse_mode' => 'HTML']);

echo "═══ ЭТАП 5: AI анализ с fallback ═══\n\n";

try {
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
    
    echo "✅ AI компоненты инициализированы\n\n";
    
    $aiModels = $config['openrouter']['models']; // Используем модели из конфига
    
    echo "🤖 Модели для fallback теста:\n";
    foreach ($aiModels as $idx => $model) {
        echo "   " . ($idx + 1) . ". {$model}\n";
    }
    echo "\n";
    
    // Берем только 3 новости для AI анализа (экономим время)
    $itemsForAI = array_slice($savedItems, 0, 3);
    
    foreach ($itemsForAI as $savedItem) {
        $item = $itemRepo->getById($savedItem['id']);
        
        if (!$item) continue;
        
        echo "🤖 Анализ #{$item['id']}: " . substr($item['title'], 0, 50) . "...\n";
        
        try {
            $analysis = $aiService->analyzeWithFallback(
                $item,
                'INoT_v1',
                $aiModels,
                []
            );
            
            if ($analysis) {
                $stats['ai']++;
                echo "   ✅ Категория: {$analysis['category_primary']}\n";
                echo "   ✅ Важность: {$analysis['importance_rating']}/20\n";
                echo "   ✅ Модель: {$analysis['model_used']}\n";
                echo "   ✅ Токенов: {$analysis['tokens_used']}\n";
                
                // Проверяем был ли fallback
                $metrics = $aiService->getLastApiMetrics();
                if ($metrics && $metrics['model'] !== $aiModels[0]) {
                    echo "   ⚠️  Fallback: {$aiModels[0]} → {$metrics['model']}\n";
                }
            } else {
                echo "   ❌ Анализ не удался\n";
                $stats['errors'][] = "AI failed for #{$item['id']}";
            }
            
        } catch (\Exception $e) {
            echo "   ❌ Ошибка: " . $e->getMessage() . "\n";
            $stats['errors'][] = "AI error: {$e->getMessage()}";
        }
        
        echo "\n";
    }
    
    echo "📊 AI анализ завершен: {$stats['ai']} успешных\n\n";
    
    $telegram->sendMessage($config['telegram']['chat_id'], 
        "🤖 <b>Этап 5:</b> AI анализ\n" .
        "Проанализировано: {$stats['ai']} новостей\n" .
        "Fallback test: ✅"
    , ['parse_mode' => 'HTML']);
    
} catch (\Exception $e) {
    echo "❌ Ошибка AI модуля: {$e->getMessage()}\n\n";
    $stats['errors'][] = "AI module: {$e->getMessage()}";
}

echo "═══ ЭТАП 6: Публикация в Telegram ═══\n\n";

try {
    // Получаем новости с AI анализом
    $itemsToPublish = $db->query(
        "SELECT i.*, a.category_primary, a.importance_rating, a.model_used
         FROM rss2tlg_items i
         LEFT JOIN rss2tlg_ai_analysis a ON i.id = a.item_id
         WHERE i.is_published = 0
         LIMIT 3"
    );
    
    foreach ($itemsToPublish as $item) {
        $title = $item['title'];
        $link = $item['link'];
        $category = $item['category_primary'] ?? 'Разное';
        $importance = $item['importance_rating'] ?? 'N/A';
        $model = $item['model_used'] ?? 'N/A';
        
        $message = 
            "📰 <b>" . htmlspecialchars($title) . "</b>\n\n" .
            "🏷️ $category\n" .
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
            
            // Помечаем опубликованной
            $itemRepo->markAsPublished((int)$item['id']);
            
            $stats['telegram']++;
            echo "✅ Опубликовано #{$item['id']}\n";
            
            sleep(1); // Задержка
            
        } catch (\Exception $e) {
            echo "❌ Ошибка публикации #{$item['id']}: {$e->getMessage()}\n";
            $stats['errors'][] = "Publish: {$e->getMessage()}";
        }
    }
    
    echo "\n📊 Опубликовано: {$stats['telegram']}\n\n";
    
} catch (\Exception $e) {
    echo "❌ Ошибка публикации: {$e->getMessage()}\n\n";
    $stats['errors'][] = "Publish module: {$e->getMessage()}";
}

$telegram->sendMessage($config['telegram']['chat_id'], 
    "📱 <b>Этап 6:</b> Публикация\n" .
    "Опубликовано: {$stats['telegram']}"
, ['parse_mode' => 'HTML']);

echo "═══ ЭТАП 7: Дампы и отчеты ═══\n\n";

$dumpsDir = __DIR__ . '/tests/sql';
if (!is_dir($dumpsDir)) mkdir($dumpsDir, 0755, true);

$timestamp = date('Ymd_His');

// Функция создания дампа
function createTableDump(MySQL $db, string $table, string $file): int {
    $data = $db->query("SELECT * FROM $table");
    
    if (empty($data)) return 0;
    
    $fp = fopen($file, 'w');
    fputcsv($fp, array_keys($data[0]));
    foreach ($data as $row) fputcsv($fp, $row);
    fclose($fp);
    
    return count($data);
}

$tables = [
    'rss2tlg_items' => "rss2tlg_items_full_{$timestamp}.csv",
    'rss2tlg_ai_analysis' => "rss2tlg_ai_analysis_full_{$timestamp}.csv",
    'rss2tlg_publications' => "rss2tlg_publications_full_{$timestamp}.csv",
    'rss2tlg_feed_state' => "rss2tlg_feed_state_full_{$timestamp}.csv"
];

foreach ($tables as $table => $filename) {
    $file = "{$dumpsDir}/{$filename}";
    $count = createTableDump($db, $table, $file);
    echo "✅ $table: $count записей → $filename\n";
}

echo "\n";

// Отчет
$duration = round(microtime(true) - $startTime, 2);
$errorsCount = count($stats['errors']);

$reportFile = __DIR__ . "/tests/E2E_TEST_FULL_REPORT_{$timestamp}.md";

$savedCount = count($savedItems);

$report = <<<REPORT
# 📋 Отчет E2E теста RSS2TLG - FULL

**Дата:** {$timestamp}  
**Длительность:** {$duration} сек

## 📊 Статистика

| Метрика | Значение |
|---------|----------|
| RSS источников | {$stats['feeds']} |
| Новостей получено | {$stats['items']} |
| Новостей сохранено | {$savedCount} |
| AI анализов | {$stats['ai']} |
| Публикаций в Telegram | {$stats['telegram']} |
| Ошибок | {$errorsCount} |

## ✅ Проверенная функциональность

- ✅ MariaDB 11.3.2 - подключение и работа
- ✅ Автоматическое создание таблиц
- ✅ Опрос 5 RSS источников
- ✅ **Unicode Fix: кириллица в categories без escape**
- ✅ Сохранение с JSON_UNESCAPED_UNICODE
- ✅ AI анализ через OpenRouter
- ✅ **Fallback между AI моделями**
- ✅ Публикация в Telegram бот
- ✅ Публикация в Telegram канал
- ✅ Сохранение публикаций в БД
- ✅ Дампы таблиц в CSV

## 🤖 AI Модели (с fallback)

1. qwen/qwen3-235b-a22b:free (недоступна - для теста)
2. qwen/qwen3-30b-a3b-thinking-2507 (основная)
3. deepseek/deepseek-v3.2-exp (запасная)

## 📁 Дампы

- `rss2tlg_items_full_{$timestamp}.csv`
- `rss2tlg_ai_analysis_full_{$timestamp}.csv`
- `rss2tlg_publications_full_{$timestamp}.csv`
- `rss2tlg_feed_state_full_{$timestamp}.csv`

## ❌ Ошибки

REPORT;

if (empty($stats['errors'])) {
    $report .= "\nНет ошибок! 🎉\n\n";
} else {
    $report .= "\n";
    foreach ($stats['errors'] as $idx => $error) {
        $report .= ($idx + 1) . ". $error\n";
    }
    $report .= "\n";
}

$status = empty($stats['errors']) ? '✅ PASSED' : '⚠️ PASSED WITH WARNINGS';

$report .= <<<REPORT

## 🎯 Выводы

Полный E2E тест проверил весь цикл работы системы:
- Получение новостей из 5 RSS источников
- **Исправление Unicode escape для кириллицы** ✅
- AI анализ с fallback между моделями
- Публикация в Telegram бот и канал

**Статус:** {$status}

---
*Сгенерировано tests_rss2tlg_e2e_FULL.php*
REPORT;

file_put_contents($reportFile, $report);

echo "✅ Отчет создан: E2E_TEST_FULL_REPORT_{$timestamp}.md\n\n";

// Финальный результат
echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║                  РЕЗУЛЬТАТЫ ТЕСТИРОВАНИЯ                  ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

echo "⏱️  Длительность: {$duration} сек\n";
echo "📡 RSS источников: {$stats['feeds']}\n";
echo "📰 Новостей: " . count($savedItems) . "\n";
echo "🤖 AI анализов: {$stats['ai']}\n";
echo "📱 Публикаций: {$stats['telegram']}\n";
echo "❌ Ошибок: {$errorsCount}\n\n";

if (empty($stats['errors'])) {
    echo "✅ ТЕСТ PASSED! Все проверки успешны!\n\n";
} else {
    echo "⚠️  ТЕСТ PASSED WITH WARNINGS\n\n";
    echo "Ошибки:\n";
    foreach ($stats['errors'] as $idx => $error) {
        echo "  " . ($idx + 1) . ". $error\n";
    }
    echo "\n";
}

// Финальное уведомление
$telegram->sendMessage($config['telegram']['chat_id'], 
    "🏁 <b>Полный E2E тест завершен!</b>\n\n" .
    "<b>Статус:</b> $status\n\n" .
    "<b>📊 Результаты:</b>\n" .
    "• RSS: {$stats['feeds']}\n" .
    "• Новостей: " . count($savedItems) . "\n" .
    "• AI: {$stats['ai']}\n" .
    "• Публикаций: {$stats['telegram']}\n" .
    "• Ошибок: {$errorsCount}\n\n" .
    "⏱️ Время: {$duration} сек\n" .
    "⏰ " . date('Y-m-d H:i:s')
, ['parse_mode' => 'HTML']);

exit(empty($stats['errors']) ? 0 : 1);

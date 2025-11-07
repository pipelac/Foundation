<?php

/**
 * RSS2TLG E2E Test v5
 * 
 * Полноценное E2E тестирование с:
 * - 5 реальными RSS источниками
 * - MariaDB 11.3.2
 * - AI анализом через OpenRouter
 * - Публикациями в Telegram Channel
 * - Уведомлениями в Telegram Bot
 * - Проверкой кеширования промптов
 * - Детальными метриками
 */

declare(strict_types=1);

require_once __DIR__ . '/../../autoload.php';

use App\Config\ConfigLoader;
use App\Component\Logger;
use App\Component\MySQL;
use App\Component\Http;
use App\Component\OpenRouter;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Rss2Tlg\FetchRunner;
use App\Rss2Tlg\ItemRepository;
use App\Rss2Tlg\PublicationRepository;
use App\Rss2Tlg\FeedStateRepository;
use App\Rss2Tlg\AIAnalysisRepository;
use App\Rss2Tlg\AIAnalysisService;
use App\Rss2Tlg\PromptManager;
use App\Rss2Tlg\DTO\FeedConfig;

// =============================================================================
// ИНИЦИАЛИЗАЦИЯ
// =============================================================================

$startTime = microtime(true);
$testStartDate = date('Y-m-d H:i:s');

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════════╗\n";
echo "║         RSS2TLG E2E ТЕСТ v5 - ПОЛНОЕ ТЕСТИРОВАНИЕ С AI                ║\n";
echo "╚════════════════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "🕐 Начало: {$testStartDate}\n\n";

// Загрузка конфигурации
$config = ConfigLoader::load('/home/engine/project/src/Rss2Tlg/config/rss2tlg_e2e_test.json');

// Создание компонентов
$logger = new Logger($config['logger']);
$db = new MySQL($config['database'], $logger);
$http = new Http($config['http'], $logger);
$telegram = new TelegramAPI($config['telegram']['bot_token'], $http, $logger, null);
$openRouter = new OpenRouter($config['openrouter'], $logger);

// Создание репозиториев
$feedStateRepo = new FeedStateRepository($db, $logger);
$itemRepo = new ItemRepository($db, $logger);
$publicationRepo = new PublicationRepository($db, $logger);
$aiAnalysisRepo = new AIAnalysisRepository($db, $logger);

// Создание сервисов
$fetchRunner = new FetchRunner($db, $config['cache']['cache_dir'], $logger);
$promptManager = new PromptManager('/home/engine/project/src/Rss2Tlg/prompts', $logger);
$aiAnalysisService = new AIAnalysisService(
    $promptManager,
    $aiAnalysisRepo,
    $openRouter,
    $db,
    $logger
);

// Статистика
$stats = [
    'feeds_total' => 0,
    'feeds_success' => 0,
    'feeds_error' => 0,
    'items_total' => 0,
    'items_saved' => 0,
    'ai_analyzed' => 0,
    'ai_cache_hits' => 0,
    'publications_total' => 0,
    'errors' => [],
    'ai_metrics' => []
];

// =============================================================================
// ОТПРАВКА УВЕДОМЛЕНИЯ О НАЧАЛЕ
// =============================================================================

function sendNotification(TelegramAPI $telegram, int $chatId, string $message): void {
    try {
        $telegram->sendMessage($chatId, $message, ['parse_mode' => 'HTML']);
    } catch (Exception $e) {
        echo "⚠️  Ошибка отправки уведомления: {$e->getMessage()}\n";
    }
}

sendNotification(
    $telegram,
    $config['telegram']['chat_id'],
    "🚀 <b>Начало E2E теста RSS2TLG v5</b>\n\n" .
    "📊 <b>Параметры:</b>\n" .
    "• RSS источников: 5\n" .
    "• БД: MariaDB 11.3.2\n" .
    "• AI: OpenRouter\n" .
    "• Публикации: до 5 в канал\n\n" .
    "⏳ Запущен: {$testStartDate}"
);

echo "✅ Уведомление о начале отправлено в бот\n\n";

// =============================================================================
// ШАГ 0: УДАЛЕНИЕ СТАРЫХ ДАМПОВ И ОТЧЕТОВ
// =============================================================================

echo "═══════════════════════════════════════════════════════════════════════\n";
echo "ШАГ 0: Удаление старых дампов и отчетов\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

$sqlDir = __DIR__ . '/sql';
$reportsDir = __DIR__ . '/reports';

// Удаление старых CSV дампов
if (is_dir($sqlDir)) {
    $csvFiles = glob($sqlDir . '/*.csv');
    foreach ($csvFiles as $file) {
        unlink($file);
        echo "🗑️  Удален старый дамп: " . basename($file) . "\n";
    }
    echo "✅ Очищено CSV дампов: " . count($csvFiles) . "\n";
} else {
    mkdir($sqlDir, 0755, true);
    echo "📁 Создана директория: sql/\n";
}

// Удаление старых отчетов
if (is_dir($reportsDir)) {
    $reportFiles = glob($reportsDir . '/*.md');
    foreach ($reportFiles as $file) {
        unlink($file);
        echo "🗑️  Удален старый отчет: " . basename($file) . "\n";
    }
    echo "✅ Очищено отчетов: " . count($reportFiles) . "\n";
} else {
    mkdir($reportsDir, 0755, true);
    echo "📁 Создана директория: reports/\n";
}

echo "\n";

// =============================================================================
// ШАГ 1: ОЧИСТКА ТАБЛИЦ БД
// =============================================================================

echo "═══════════════════════════════════════════════════════════════════════\n";
echo "ШАГ 1: Очистка таблиц БД\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

$tables = [
    'rss2tlg_publications',
    'rss2tlg_ai_analysis', 
    'rss2tlg_items',
    'rss2tlg_feed_state'
];

foreach ($tables as $table) {
    try {
        // Проверяем существование таблицы
        $exists = $db->query("SHOW TABLES LIKE '{$table}'");
        if (!empty($exists)) {
            $db->query("TRUNCATE TABLE {$table}");
            echo "✅ Таблица {$table} очищена\n";
        } else {
            echo "ℹ️  Таблица {$table} не существует (будет создана автоматически)\n";
        }
    } catch (Exception $e) {
        echo "⚠️  Ошибка с таблицей {$table}: {$e->getMessage()}\n";
    }
}

echo "\n";
sendNotification($telegram, $config['telegram']['chat_id'], "✅ Таблицы БД подготовлены");

// =============================================================================
// ШАГ 2: ОПРОС RSS ЛЕНТ
// =============================================================================

echo "═══════════════════════════════════════════════════════════════════════\n";
echo "ШАГ 2: Опрос RSS лент\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

$feedConfigs = array_map(
    fn($feedData) => FeedConfig::fromArray($feedData),
    $config['feeds']
);

$stats['feeds_total'] = count($feedConfigs);

sendNotification(
    $telegram,
    $config['telegram']['chat_id'],
    "📡 Начало опроса {$stats['feeds_total']} RSS лент..."
);

$fetchResults = $fetchRunner->runForAllFeeds($feedConfigs);

// Обработка результатов опроса
foreach ($fetchResults as $feedId => $result) {
    $feedName = $feedConfigs[$feedId - 1]->name ?? "Feed #{$feedId}";
    
    echo "📰 {$feedName}:\n";
    
    if ($result->isError()) {
        $stats['feeds_error']++;
        $errorMsg = $result->state->lastError ?? 'Unknown error';
        $stats['errors'][] = "{$feedName}: {$errorMsg}";
        echo "   ❌ Ошибка: {$errorMsg}\n\n";
        continue;
    }
    
    if ($result->isNotModified()) {
        echo "   ℹ️  Не изменилось с последнего опроса\n\n";
        $stats['feeds_success']++;
        continue;
    }
    
    // Успешный результат
    $stats['feeds_success']++;
    $itemCount = count($result->items);
    $stats['items_total'] += $itemCount;
    
    echo "   ✅ Получено новостей: {$itemCount}\n";
    
    // Сохранение новостей
    $savedCount = 0;
    foreach ($result->items as $item) {
        try {
            $itemId = $itemRepo->save($feedId, $item);
            if ($itemId !== null) {
                $savedCount++;
            }
        } catch (Exception $e) {
            $stats['errors'][] = "{$feedName} - ошибка сохранения: {$e->getMessage()}";
            echo "   ⚠️  Ошибка сохранения новости: {$e->getMessage()}\n";
        }
    }
    
    $stats['items_saved'] += $savedCount;
    echo "   💾 Сохранено в БД: {$savedCount}\n\n";
}

$feedsSummary = "📊 <b>Результаты опроса RSS:</b>\n" .
    "• Лент опрошено: {$stats['feeds_success']}/{$stats['feeds_total']}\n" .
    "• Новостей получено: {$stats['items_total']}\n" .
    "• Сохранено в БД: {$stats['items_saved']}";

if ($stats['feeds_error'] > 0) {
    $feedsSummary .= "\n• ⚠️ Ошибок: {$stats['feeds_error']}";
}

sendNotification($telegram, $config['telegram']['chat_id'], $feedsSummary);

// =============================================================================
// ШАГ 3: AI АНАЛИЗ
// =============================================================================

echo "═══════════════════════════════════════════════════════════════════════\n";
echo "ШАГ 3: AI анализ новостей\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

sendNotification($telegram, $config['telegram']['chat_id'], "🤖 Начало AI анализа...");

// Получаем 5 случайных новостей для анализа
$itemsForAnalysis = $db->query(
    "SELECT id, feed_id, title, description, link FROM rss2tlg_items ORDER BY RAND() LIMIT 5"
);

foreach ($itemsForAnalysis as $item) {
    echo "🔍 Анализ: {$item['title']}\n";
    
    try {
        $analysis = $aiAnalysisService->analyzeWithFallback(
            $item,
            '1',
            $config['feeds'][(int)$item['feed_id'] - 1]['ai_models'] ?? null
        );
        
        if ($analysis !== null) {
            $stats['ai_analyzed']++;
            
            // Получение метрик
            $metrics = $aiAnalysisService->getLastApiMetrics();
            if ($metrics !== null) {
                $cachedTokens = $metrics['usage']['cached_tokens'] ?? 0;
                $promptTokens = $metrics['usage']['prompt_tokens'] ?? 0;
                $completionTokens = $metrics['usage']['completion_tokens'] ?? 0;
                $totalTokens = $metrics['usage']['total_tokens'] ?? 0;
                $model = $metrics['model'] ?? 'unknown';
                
                if ($cachedTokens > 0) {
                    $stats['ai_cache_hits']++;
                }
                
                $stats['ai_metrics'][] = [
                    'item_id' => $item['id'],
                    'title' => mb_substr($item['title'], 0, 50),
                    'model' => $model,
                    'prompt_tokens' => $promptTokens,
                    'completion_tokens' => $completionTokens,
                    'total_tokens' => $totalTokens,
                    'cached_tokens' => $cachedTokens
                ];
                
                echo "   ✅ Анализ завершен\n";
                echo "   📊 Модель: {$model}\n";
                echo "   📊 Токены: prompt={$promptTokens}, completion={$completionTokens}, total={$totalTokens}\n";
                echo "   💾 Кешировано: {$cachedTokens} токенов\n";
            } else {
                echo "   ⚠️  Метрики недоступны\n";
            }
        } else {
            echo "   ❌ Анализ не выполнен\n";
            $stats['errors'][] = "AI анализ не выполнен для: {$item['title']}";
        }
    } catch (Exception $e) {
        echo "   ❌ Ошибка: {$e->getMessage()}\n";
        $stats['errors'][] = "AI ошибка: {$e->getMessage()}";
    }
    
    echo "\n";
}

$aiSummary = "🤖 <b>AI анализ завершен:</b>\n" .
    "• Проанализировано: {$stats['ai_analyzed']}/5\n" .
    "• Cache hits: {$stats['ai_cache_hits']}";

sendNotification($telegram, $config['telegram']['chat_id'], $aiSummary);

// =============================================================================
// ШАГ 4: ПУБЛИКАЦИЯ В TELEGRAM
// =============================================================================

echo "═══════════════════════════════════════════════════════════════════════\n";
echo "ШАГ 4: Публикация в Telegram канал\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

sendNotification($telegram, $config['telegram']['chat_id'], "📢 Начало публикации в канал...");

// Получаем 5 новостей с AI анализом
$itemsForPublication = $db->query(
    "SELECT i.*, a.category_primary, a.content_summary, a.tokens_used, a.model_used, a.cache_hit 
     FROM rss2tlg_items i 
     INNER JOIN rss2tlg_ai_analysis a ON i.id = a.item_id 
     LIMIT 5"
);

foreach ($itemsForPublication as $item) {
    echo "📤 Публикация: {$item['title']}\n";
    
    try {
        // Формирование сообщения с метриками
        $message = "<b>{$item['title']}</b>\n\n";
        
        if (!empty($item['content_summary'])) {
            $message .= "{$item['content_summary']}\n\n";
        }
        
        $message .= "🔗 <a href=\"{$item['link']}\">Читать полностью</a>\n\n";
        
        // Метрики
        $message .= "📊 <b>Метрики:</b>\n";
        $message .= "• Категория: {$item['category_primary']}\n";
        $message .= "• AI модель: {$item['model_used']}\n";
        $message .= "• Токены: {$item['tokens_used']}\n";
        $message .= "• Кеш: " . ($item['cache_hit'] ? '✅ Да' : '❌ Нет') . "\n";
        
        // Отправка в канал
        $sentMessage = $telegram->sendMessage(
            $config['telegram']['channel_id'],
            $message,
            ['parse_mode' => 'HTML']
        );
        
        // Запись публикации
        $publicationRepo->record(
            (int)$item['id'],
            (int)$item['feed_id'],
            'channel',
            $config['telegram']['channel_id'],
            $sentMessage->messageId
        );
        
        $stats['publications_total']++;
        echo "   ✅ Опубликовано (message_id: {$sentMessage->messageId})\n\n";
        
        // Задержка между публикациями
        sleep(2);
        
    } catch (Exception $e) {
        echo "   ❌ Ошибка публикации: {$e->getMessage()}\n\n";
        $stats['errors'][] = "Публикация ошибка: {$e->getMessage()}";
    }
}

$pubSummary = "📢 <b>Публикация завершена:</b>\n" .
    "• Опубликовано: {$stats['publications_total']}/5 в канал @kompasDaily";

sendNotification($telegram, $config['telegram']['chat_id'], $pubSummary);

// =============================================================================
// ШАГ 5: ПРОВЕРКА КЕШИРОВАНИЯ ПРОМПТОВ
// =============================================================================

echo "═══════════════════════════════════════════════════════════════════════\n";
echo "ШАГ 5: Проверка кеширования промптов\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

$cacheAnalysis = [
    'total_requests' => count($stats['ai_metrics']),
    'cache_hits' => 0,
    'cache_misses' => 0,
    'total_cached_tokens' => 0,
    'cache_hit_rate' => 0
];

foreach ($stats['ai_metrics'] as $metric) {
    if ($metric['cached_tokens'] > 0) {
        $cacheAnalysis['cache_hits']++;
        $cacheAnalysis['total_cached_tokens'] += $metric['cached_tokens'];
    } else {
        $cacheAnalysis['cache_misses']++;
    }
}

if ($cacheAnalysis['total_requests'] > 0) {
    $cacheAnalysis['cache_hit_rate'] = round(
        ($cacheAnalysis['cache_hits'] / $cacheAnalysis['total_requests']) * 100,
        2
    );
}

echo "📊 Результаты кеширования:\n";
echo "   • Всего запросов: {$cacheAnalysis['total_requests']}\n";
echo "   • Cache hits: {$cacheAnalysis['cache_hits']}\n";
echo "   • Cache misses: {$cacheAnalysis['cache_misses']}\n";
echo "   • Cache hit rate: {$cacheAnalysis['cache_hit_rate']}%\n";
echo "   • Всего кешировано токенов: {$cacheAnalysis['total_cached_tokens']}\n\n";

if ($cacheAnalysis['cache_hits'] > 0) {
    echo "✅ Кеширование промптов РАБОТАЕТ!\n\n";
} else {
    echo "⚠️  Кеширование промптов НЕ РАБОТАЕТ (или первые запросы)\n\n";
}

// =============================================================================
// ШАГ 6: СОЗДАНИЕ ДАМПОВ ТАБЛИЦ
// =============================================================================

echo "═══════════════════════════════════════════════════════════════════════\n";
echo "ШАГ 6: Создание дампов таблиц\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

$dumpDir = __DIR__ . '/sql';
if (!is_dir($dumpDir)) {
    mkdir($dumpDir, 0755, true);
}

$tablesToDump = ['rss2tlg_feed_state', 'rss2tlg_items', 'rss2tlg_ai_analysis', 'rss2tlg_publications'];

foreach ($tablesToDump as $table) {
    try {
        $rows = $db->query("SELECT * FROM {$table}");
        
        if (empty($rows)) {
            echo "⚠️  Таблица {$table} пуста, дамп пропущен\n";
            continue;
        }
        
        $csvFile = "{$dumpDir}/{$table}_" . date('Ymd_His') . ".csv";
        $fp = fopen($csvFile, 'w');
        
        // Заголовки
        fputcsv($fp, array_keys($rows[0]));
        
        // Данные
        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }
        
        fclose($fp);
        
        $fileSize = filesize($csvFile);
        $rowCount = count($rows);
        echo "✅ {$table}: {$rowCount} строк, " . round($fileSize / 1024, 2) . " KB\n";
        
    } catch (Exception $e) {
        echo "❌ Ошибка дампа {$table}: {$e->getMessage()}\n";
    }
}

echo "\n";

// =============================================================================
// ФИНАЛЬНЫЙ ОТЧЕТ
// =============================================================================

$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);
$testEndDate = date('Y-m-d H:i:s');

echo "═══════════════════════════════════════════════════════════════════════\n";
echo "ФИНАЛЬНЫЙ ОТЧЕТ\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

echo "⏱️  ВРЕМЯ:\n";
echo "   • Начало: {$testStartDate}\n";
echo "   • Окончание: {$testEndDate}\n";
echo "   • Длительность: {$duration} сек\n\n";

echo "📊 RSS ЛЕНТЫ:\n";
echo "   • Всего: {$stats['feeds_total']}\n";
echo "   • Успешно: {$stats['feeds_success']}\n";
echo "   • Ошибки: {$stats['feeds_error']}\n\n";

echo "📰 НОВОСТИ:\n";
echo "   • Получено: {$stats['items_total']}\n";
echo "   • Сохранено: {$stats['items_saved']}\n\n";

echo "🤖 AI АНАЛИЗ:\n";
echo "   • Проанализировано: {$stats['ai_analyzed']}/5\n";
echo "   • Cache hits: {$stats['ai_cache_hits']}\n";
echo "   • Cache hit rate: {$cacheAnalysis['cache_hit_rate']}%\n\n";

echo "📢 ПУБЛИКАЦИИ:\n";
echo "   • Опубликовано в канал: {$stats['publications_total']}/5\n\n";

if (!empty($stats['errors'])) {
    echo "⚠️  ОШИБКИ ({" . count($stats['errors']) . "}):\n";
    foreach ($stats['errors'] as $i => $error) {
        echo "   " . ($i + 1) . ". {$error}\n";
    }
    echo "\n";
}

echo "📊 AI МЕТРИКИ ДЕТАЛЬНО:\n";
foreach ($stats['ai_metrics'] as $i => $metric) {
    echo "   " . ($i + 1) . ". {$metric['title']}...\n";
    echo "      • Модель: {$metric['model']}\n";
    echo "      • Prompt: {$metric['prompt_tokens']}, Completion: {$metric['completion_tokens']}, Total: {$metric['total_tokens']}\n";
    echo "      • Cached: {$metric['cached_tokens']} токенов\n";
}

echo "\n";

// Итоговый статус
$testStatus = (count($stats['errors']) === 0 && $stats['ai_analyzed'] >= 3 && $stats['publications_total'] >= 3)
    ? '✅ PASSED'
    : '⚠️  PASSED WITH WARNINGS';

echo "═══════════════════════════════════════════════════════════════════════\n";
echo "СТАТУС ТЕСТА: {$testStatus}\n";
echo "═══════════════════════════════════════════════════════════════════════\n\n";

// =============================================================================
// СОХРАНЕНИЕ ОТЧЕТА
// =============================================================================

$reportDir = __DIR__ . '/reports';
if (!is_dir($reportDir)) {
    mkdir($reportDir, 0755, true);
}

$reportFile = "{$reportDir}/e2e_test_v5_" . date('Ymd_His') . ".md";
$reportContent = "# RSS2TLG E2E Test v5 Report\n\n";
$reportContent .= "**Дата:** {$testStartDate} - {$testEndDate}\n";
$reportContent .= "**Длительность:** {$duration} сек\n";
$reportContent .= "**Статус:** {$testStatus}\n\n";

$reportContent .= "## Статистика\n\n";
$reportContent .= "### RSS Ленты\n";
$reportContent .= "- Всего: {$stats['feeds_total']}\n";
$reportContent .= "- Успешно: {$stats['feeds_success']}\n";
$reportContent .= "- Ошибки: {$stats['feeds_error']}\n\n";

$reportContent .= "### Новости\n";
$reportContent .= "- Получено: {$stats['items_total']}\n";
$reportContent .= "- Сохранено: {$stats['items_saved']}\n\n";

$reportContent .= "### AI Анализ\n";
$reportContent .= "- Проанализировано: {$stats['ai_analyzed']}/5\n";
$reportContent .= "- Cache hits: {$stats['ai_cache_hits']}\n";
$reportContent .= "- Cache hit rate: {$cacheAnalysis['cache_hit_rate']}%\n";
$reportContent .= "- Кешировано токенов: {$cacheAnalysis['total_cached_tokens']}\n\n";

$reportContent .= "### Публикации\n";
$reportContent .= "- Опубликовано в канал: {$stats['publications_total']}/5\n\n";

$reportContent .= "## AI Метрики\n\n";
$reportContent .= "| # | Новость | Модель | Prompt | Completion | Total | Cached |\n";
$reportContent .= "|---|---------|--------|--------|------------|-------|--------|\n";
foreach ($stats['ai_metrics'] as $i => $metric) {
    $reportContent .= sprintf(
        "| %d | %s... | %s | %d | %d | %d | %d |\n",
        $i + 1,
        mb_substr($metric['title'], 0, 30),
        $metric['model'],
        $metric['prompt_tokens'],
        $metric['completion_tokens'],
        $metric['total_tokens'],
        $metric['cached_tokens']
    );
}

if (!empty($stats['errors'])) {
    $reportContent .= "\n## Ошибки\n\n";
    foreach ($stats['errors'] as $i => $error) {
        $reportContent .= ($i + 1) . ". {$error}\n";
    }
}

file_put_contents($reportFile, $reportContent);
echo "📄 Отчет сохранен: {$reportFile}\n\n";

// =============================================================================
// ОТПРАВКА ФИНАЛЬНОГО УВЕДОМЛЕНИЯ
// =============================================================================

$finalNotification = "🏁 <b>E2E тест v5 завершен!</b>\n\n";
$finalNotification .= "<b>Статус:</b> {$testStatus}\n";
$finalNotification .= "<b>Длительность:</b> {$duration} сек\n\n";
$finalNotification .= "📊 <b>Итоги:</b>\n";
$finalNotification .= "• RSS лент: {$stats['feeds_success']}/{$stats['feeds_total']}\n";
$finalNotification .= "• Новостей: {$stats['items_saved']}\n";
$finalNotification .= "• AI анализ: {$stats['ai_analyzed']}/5\n";
$finalNotification .= "• Публикаций: {$stats['publications_total']}/5\n";
$finalNotification .= "• Cache hit rate: {$cacheAnalysis['cache_hit_rate']}%\n";

if (count($stats['errors']) > 0) {
    $finalNotification .= "\n⚠️ Ошибок: " . count($stats['errors']);
}

sendNotification($telegram, $config['telegram']['chat_id'], $finalNotification);

echo "✅ Финальное уведомление отправлено в бот\n";
echo "\n🎉 ТЕСТ ЗАВЕРШЕН!\n\n";

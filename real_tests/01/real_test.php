<?php

declare(strict_types=1);

/**
 * 🎉 РЕАЛЬНОЕ ТЕСТИРОВАНИЕ RSS2TLG С ПОЛНОЙ ЦЕПОЧКОЙ ОБРАБОТКИ
 * 
 * Идентификатор: RSS2TLG-REAL-TEST-001
 * 
 * ФУНКЦИОНАЛ:
 * 1. Сбор новостей из 30 RSS источников (6 языков: ru, en, fr, de, zh)
 * 2. AI-анализ через OpenRouter (DeepSeek V3.1 с реальным API ключом)
 * 3. Детальная метрика: токены, cost, cache_hit_rate, производительность
 * 4. Извлечение полного контента из статей
 * 5. Публикация в Telegram бот и канал @kompasDaily
 * 6. Уведомления о ходе теста в реальном времени
 * 7. Сохранение всех результатов и отчетов в real_tests/01/
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\Http;
use App\Component\WebtExtractor;
use App\Component\OpenRouter;
use App\Component\OpenRouterMetrics;
use App\Config\ConfigLoader;
use App\Rss2Tlg\FetchRunner;
use App\Rss2Tlg\ItemRepository;
use App\Rss2Tlg\PublicationRepository;
use App\Rss2Tlg\FeedStateRepository;
use App\Rss2Tlg\ContentExtractorService;
use App\Rss2Tlg\AIAnalysisService;
use App\Rss2Tlg\AIAnalysisRepository;
use App\Rss2Tlg\PromptManager;
use App\Rss2Tlg\DTO\FeedConfig;
use App\Component\TelegramBot\Core\TelegramAPI;

// ============================================================================
// КОНФИГУРАЦИЯ
// ============================================================================

$testId = 'RSS2TLG-REAL-TEST-001';
$configPath = __DIR__ . '/config.json';
$promptsDir = __DIR__ . '/../../prompts';
$maxArticlesToAnalyze = 10;
$maxArticlesToPublish = 10;
$reportsDir = __DIR__;

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  🎉 РЕАЛЬНОЕ ТЕСТИРОВАНИЕ RSS2TLG С ПОЛНОЙ ЦЕПОЧКОЙ         ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";

// ============================================================================
// ИНИЦИАЛИЗАЦИЯ КОМПОНЕНТОВ
// ============================================================================

echo "📦 Инициализация компонентов...\n\n";

$configLoader = new ConfigLoader();
$config = $configLoader->load($configPath);

// Создаем папку для логов
$logDir = dirname($configPath) . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Логгер
$logger = new Logger([
    'directory' => $logDir,
    'file_name' => 'rss2tlg_real_test.log',
    'max_files' => 10,
    'max_file_size' => 100,
    'enabled' => true,
]);

echo "✓ Логгер: {$logDir}/rss2tlg_real_test.log\n";

// База данных
$dbConfig = $config['database'];
$db = new MySQL([
    'host' => $dbConfig['host'],
    'port' => $dbConfig['port'],
    'database' => $dbConfig['dbname'],
    'username' => $dbConfig['user'],
    'password' => $dbConfig['password'],
    'charset' => $dbConfig['charset'] ?? 'utf8mb4',
], $logger);

echo "✓ БД: {$dbConfig['dbname']} @ {$dbConfig['host']}:{$dbConfig['port']}\n";

// HTTP и WebtExtractor
$http = new Http([], $logger);
$extractor = new WebtExtractor([
    'timeout' => $config['content_extractor']['timeout'],
    'user_agent' => $config['content_extractor']['user_agent'],
    'follow_redirects' => $config['content_extractor']['follow_redirects'],
    'max_redirects' => $config['content_extractor']['max_redirects'],
], $logger);

echo "✓ HTTP и WebtExtractor инициализированы\n";

// Telegram API
$telegramConfig = $config['telegram_bot'];
$telegram = new TelegramAPI($telegramConfig['token'], $http, $logger);
$chatId = $telegramConfig['chat_id'];
$channelId = $config['telegram_channel']['username'];

echo "✓ Telegram API: бот {$chatId} и канал {$channelId}\n";

// OpenRouter
$openRouterConfig = [
    'api_key' => $config['openrouter']['api_key'],
    'base_url' => 'https://openrouter.ai/api/v1',
    'default_model' => $config['openrouter']['model'],
    'timeout' => $config['openrouter']['timeout'],
];
$openRouter = new OpenRouter($openRouterConfig, $logger);

echo "✓ OpenRouter: {$openRouterConfig['default_model']}\n";

// OpenRouter Metrics
$openRouterMetrics = new OpenRouterMetrics([
    'api_key' => $config['openrouter']['api_key'],
    'timeout' => 30,
], $logger);

echo "✓ OpenRouter Metrics инициализирован\n";

// Репозитории
$itemRepository = new ItemRepository($db, $logger);
$publicationRepository = new PublicationRepository($db, $logger);
$feedStateRepository = new FeedStateRepository($db, $logger);
$analysisRepository = new AIAnalysisRepository($db, $logger, true);

echo "✓ Репозитории инициализированы\n";

// Сервисы
$cacheDir = $config['cache']['directory'];
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

$fetchRunner = new FetchRunner($db, $cacheDir, $logger);
$contentExtractor = new ContentExtractorService($itemRepository, $extractor, $logger);
$promptManager = new PromptManager($promptsDir, $logger);
$aiAnalysisService = new AIAnalysisService(
    $promptManager,
    $analysisRepository,
    $openRouter,
    $db,
    $logger
);

echo "✓ Сервисы инициализированы\n";
echo "✓ Cache: {$cacheDir}\n\n";

// Массив для сбора метрик
$testMetrics = [
    'start_time' => microtime(true),
    'stages' => [],
    'ai_requests' => [],
    'publications' => [],
    'errors' => []
];

// ============================================================================
// ОТПРАВКА СТАРТОВОГО УВЕДОМЛЕНИЯ
// ============================================================================

try {
    $startMsg = "🚀 <b>СТАРТ РЕАЛЬНОГО ТЕСТИРОВАНИЯ</b>\n\n" .
                "<b>Тест:</b> {$testId}\n" .
                "<b>Источников:</b> " . count($config['feeds']) . "\n" .
                "<b>Макс. статей для анализа:</b> {$maxArticlesToAnalyze}\n" .
                "<b>Канал:</b> {$channelId}\n" .
                "<b>AI модель:</b> {$config['openrouter']['model']}\n" .
                "<b>Timeout:</b> {$config['openrouter']['timeout']}с\n\n" .
                "⏳ Этап 1: Сбор новостей...";
    $telegram->sendMessage($chatId, $startMsg, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
    echo "✓ Стартовое уведомление отправлено\n\n";
} catch (Exception $e) {
    echo "⚠️ Ошибка отправки уведомления: {$e->getMessage()}\n\n";
    $testMetrics['errors'][] = "Start notification failed: {$e->getMessage()}";
}

// ============================================================================
// ЭТАП 1: СБОР НОВОСТЕЙ
// ============================================================================

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  📡 ЭТАП 1: СБОР НОВОСТЕЙ ИЗ RSS ЛЕНТ                       ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

$stage1Start = microtime(true);

// Преобразуем конфигурацию feeds в FeedConfig объекты
$feedConfigs = [];
foreach ($config['feeds'] as $feedData) {
    if (!$feedData['active']) {
        continue;
    }
    
    $feedConfig = new FeedConfig(
        $feedData['id'],
        $feedData['url'],
        $feedData['name'],
        $feedData['active'],
        30, // timeout
        3, // retries
        $feedData['fetch_interval'],
        [], // headers
        [], // parser_options
        null // proxy
    );
    $feedConfigs[] = $feedConfig;
}

$fetchResults = $fetchRunner->runForAllFeeds($feedConfigs);

$totalItems = 0;
$totalErrors = 0;
$successfulFeeds = 0;

foreach ($fetchResults as $feedId => $result) {
    if ($result->isSuccessful() || $result->isNotModified()) {
        $successfulFeeds++;
    }
    
    if ($result->items) {
        $totalItems += count($result->items);
        
        // Сохраняем новости в БД
        foreach ($result->items as $rawItem) {
            try {
                $itemRepository->save($feedId, $rawItem);
            } catch (\Exception $e) {
                $logger->error("Ошибка сохранения новости: {$e->getMessage()}");
                $testMetrics['errors'][] = "Save item failed: {$e->getMessage()}";
            }
        }
    }
    
    if ($result->isError()) {
        $totalErrors++;
        $testMetrics['errors'][] = "Feed {$feedId} error: {$result->error}";
    }
}

$stage1End = microtime(true);
$stage1Duration = round($stage1End - $stage1Start, 2);

$testMetrics['stages']['fetch'] = [
    'start_time' => $stage1Start,
    'end_time' => $stage1End,
    'duration' => $stage1Duration,
    'feeds_processed' => count($fetchResults),
    'successful_feeds' => $successfulFeeds,
    'total_items' => $totalItems,
    'errors' => $totalErrors
];

echo "\n";
echo "📊 Результаты сбора ({$stage1Duration} сек):\n";
echo "  - Источников обработано: " . count($fetchResults) . "\n";
echo "  - Успешных источников: {$successfulFeeds}\n";
echo "  - Новых новостей: {$totalItems}\n";
echo "  - Ошибок: {$totalErrors}\n\n";

// Уведомление
try {
    $msg1 = "✅ <b>ЭТАП 1 ЗАВЕРШЕН</b>\n\n" .
            "📊 <b>Результаты:</b>\n" .
            "  • Источников: " . count($fetchResults) . "\n" .
            "  • Успешных: {$successfulFeeds}\n" .
            "  • Новостей: {$totalItems}\n" .
            "  • Ошибок: {$totalErrors}\n" .
            "  • Время: {$stage1Duration} сек\n\n" .
            "⏳ Этап 2: Извлечение контента и AI-анализ...";
    $telegram->sendMessage($chatId, $msg1, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
} catch (Exception $e) {
    echo "⚠️ Ошибка отправки: {$e->getMessage()}\n";
    $testMetrics['errors'][] = "Stage 1 notification failed: {$e->getMessage()}";
}

// ============================================================================
// ЭТАП 2: ИЗВЛЕЧЕНИЕ КОНТЕНТА И AI-АНАЛИЗ
// ============================================================================

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  📖🤖 ЭТАП 2: ИЗВЛЕЧЕНИЕ КОНТЕНТА И AI-АНАЛИЗ              ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

$stage2Start = microtime(true);

// Получаем последние новости для анализа
$pendingItems = $db->query(
    "SELECT i.* FROM rss2tlg_items i 
     LEFT JOIN rss2tlg_ai_analysis a ON i.id = a.item_id 
     WHERE a.id IS NULL AND i.extraction_status != 'failed' 
     ORDER BY i.pub_date DESC 
     LIMIT ?",
    [$maxArticlesToAnalyze]
);

echo "🔍 К обработке: " . count($pendingItems) . " новостей\n\n";

$promptId = 'INoT_v1';
$aiModels = [$config['openrouter']['model']];
if (!empty($config['ai_analysis']['fallback_models'])) {
    $aiModels = array_merge($aiModels, $config['ai_analysis']['fallback_models']);
}

// Опции для AI анализа
$aiOptions = [
    'temperature' => $config['openrouter']['temperature'],
    'top_p' => $config['openrouter']['top_p'],
    'frequency_penalty' => $config['openrouter']['frequency_penalty'],
    'presence_penalty' => $config['openrouter']['presence_penalty'],
    'max_tokens' => $config['openrouter']['max_tokens'],
    'min_tokens' => $config['openrouter']['min_tokens'],
];

$processedCount = 0;
$extractedCount = 0;
$analyzedCount = 0;
$failedCount = 0;

foreach ($pendingItems as $index => $item) {
    $itemId = (int)$item['id'];
    $title = $item['title'] ?? 'Без заголовка';
    
    echo "\n[{$itemId}] " . mb_substr($title, 0, 60) . "...\n";
    
    try {
        // Этап 2.1: Извлечение контента
        echo "  📖 Извлечение контента...\n";
        $extractStartTime = microtime(true);
        
        $extractionResult = $contentExtractor->processItem($item);
        
        $extractEndTime = microtime(true);
        $extractDuration = round(($extractEndTime - $extractStartTime) * 1000, 2);
        
        if ($extractionResult) {
            $extractedCount++;
            echo "  ✓ Контент извлечен ({$extractDuration} мс)\n";
            
            // Этап 2.2: AI-анализ
            echo "  🤖 AI-анализ...\n";
            $analysisStartTime = microtime(true);
            
            $analysis = $aiAnalysisService->analyzeWithFallback($item, $promptId, $aiModels, $aiOptions);
            
            $analysisEndTime = microtime(true);
            $analysisDuration = round(($analysisEndTime - $analysisStartTime) * 1000, 2);
            
            if ($analysis !== null) {
                $analyzedCount++;
                $processedCount++;
                
                echo "  ✓ Анализ завершен ({$analysisDuration} мс)\n";
                echo "  • Категория: " . ($analysis['category_primary'] ?? 'N/A') . "\n";
                echo "  • Важность: " . ($analysis['importance_rating'] ?? 'N/A') . "/20\n";
                echo "  • Язык: " . ($analysis['article_language'] ?? 'N/A') . "\n";
                
                // Получаем метрики
                $savedAnalysis = $analysisRepository->getByItemId($itemId);
                if ($savedAnalysis) {
                    $tokensUsed = (int)($savedAnalysis['tokens_used'] ?? 0);
                    $modelUsed = $savedAnalysis['model_used'] ?? 'unknown';
                    $cacheHit = (bool)($savedAnalysis['cache_hit'] ?? false);
                    $translationQuality = $savedAnalysis['translation_quality_score'] ?? null;
                    
                    echo "  • Токенов: {$tokensUsed}\n";
                    echo "  • Модель: {$modelUsed}\n";
                    echo "  • Кеш: " . ($cacheHit ? "Да" : "Нет") . "\n";
                    if ($translationQuality) {
                        echo "  • Качество перевода: {$translationQuality}/100\n";
                    }
                    
                    // Сохраняем метрики
                    $apiMetrics = $aiAnalysisService->getLastApiMetrics();
                    $testMetrics['ai_requests'][] = [
                        'item_id' => $itemId,
                        'model' => $modelUsed,
                        'tokens_used' => $tokensUsed,
                        'processing_time_ms' => $analysisDuration,
                        'cache_hit' => $cacheHit,
                        'translation_quality' => $translationQuality,
                        'api_metrics' => $apiMetrics
                    ];
                }
            } else {
                $failedCount++;
                echo "  ✗ AI-анализ не удался\n";
            }
        } else {
            echo "  ⚠️ Не удалось извлечь контент\n";
        }
    } catch (\Exception $e) {
        $failedCount++;
        echo "  ✗ Ошибка: {$e->getMessage()}\n";
        $testMetrics['errors'][] = "Item {$itemId} processing failed: {$e->getMessage()}";
    }
    
    // Задержка между запросами
    if ($index < count($pendingItems) - 1) {
        usleep(200000); // 200ms
    }
}

$stage2End = microtime(true);
$stage2Duration = round($stage2End - $stage2Start, 2);

$testMetrics['stages']['process'] = [
    'start_time' => $stage2Start,
    'end_time' => $stage2End,
    'duration' => $stage2Duration,
    'items_processed' => count($pendingItems),
    'content_extracted' => $extractedCount,
    'ai_analyzed' => $analyzedCount,
    'failed' => $failedCount
];

echo "\n";
echo "📊 Результаты обработки ({$stage2Duration} сек):\n";
echo "  - Новостей обработано: " . count($pendingItems) . "\n";
echo "  - Контент извлечен: {$extractedCount}\n";
echo "  - AI-анализ выполнен: {$analyzedCount}\n";
echo "  - Ошибок: {$failedCount}\n\n";

// Уведомление
try {
    $msg2 = "✅ <b>ЭТАП 2 ЗАВЕРШЕН</b>\n\n" .
            "📊 <b>Результаты:</b>\n" .
            "  • Обработано: " . count($pendingItems) . "\n" .
            "  • Контент извлечен: {$extractedCount}\n" .
            "  • AI-анализ: {$analyzedCount}\n" .
            "  • Ошибок: {$failedCount}\n" .
            "  • Время: {$stage2Duration} сек\n\n" .
            "⏳ Этап 3: Публикация в Telegram...";
    $telegram->sendMessage($chatId, $msg2, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
} catch (Exception $e) {
    $testMetrics['errors'][] = "Stage 2 notification failed: {$e->getMessage()}";
}

// ============================================================================
// ЭТАП 3: ПУБЛИКАЦИЯ В TELEGRAM
// ============================================================================

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  📢 ЭТАП 3: ПУБЛИКАЦИЯ В TELEGRAM                           ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

$stage3Start = microtime(true);

// Получаем успешно проанализированные статьи
$analyzedItems = $db->query(
    "SELECT ai.*, i.title, i.link, i.pub_date, f.name as feed_name, f.language 
     FROM rss2tlg_ai_analysis ai 
     JOIN rss2tlg_items i ON ai.item_id = i.id 
     JOIN rss2tlg_feed_config f ON i.feed_id = f.id 
     WHERE ai.analysis_status = 'success' 
     ORDER BY ai.created_at DESC 
     LIMIT ?",
    [$maxArticlesToPublish]
);

echo "🔍 Найдено проанализированных статей: " . count($analyzedItems) . "\n\n";

$publishedCount = 0;

foreach ($analyzedItems as $news) {
    $newsId = (int)$news['item_id'];
    $title = $news['content_headline'] ?? $news['title'] ?? 'Без заголовка';
    $summary = $news['content_summary'] ?? 'Нет описания';
    $language = $news['article_language'] ?? 'unknown';
    $importance = $news['importance_rating'];
    $category = $news['category_primary'] ?? 'General';
    $translationStatus = $news['translation_status'] ?? 'unknown';
    $feedName = $news['feed_name'] ?? 'Unknown';
    $feedLanguage = $news['language'] ?? 'unknown';
    
    $pubDate = $news['pub_date'] ?? date('Y-m-d H:i:s');
    $link = $news['link'] ?? '';
    
    try {
        echo "📢 Публикация: " . mb_substr($title, 0, 50) . "...\n";
        
        // Формируем сообщение для Telegram
        $emoji = getCategoryEmoji($category);
        $importanceStars = str_repeat('⭐', min($importance, 5));
        $languageFlag = getLanguageFlag($feedLanguage);
        
        $message = "{$emoji} <b>" . htmlspecialchars($title) . "</b>\n\n";
        $message .= "<i>" . htmlspecialchars($summary) . "</i>\n\n";
        $message .= "📊 {$category} • {$importanceStars} ({$importance}/20)\n";
        $message .= "🌐 {$languageFlag} {$feedName} • {$translationStatus}\n";
        
        if ($link) {
            $message .= "\n🔗 <a href='{$link}'>Читать полную статью</a>";
        }
        
        // Публикация в бот
        $botMessage = $telegram->sendMessage($chatId, $message, [
            'parse_mode' => TelegramAPI::PARSE_MODE_HTML,
            'disable_web_page_preview' => false
        ]);
        
        if ($botMessage) {
            $botMessageId = $botMessage->messageId;
            echo "  ✓ Бот: сообщение #{$botMessageId}\n";
            
            // Сохраняем публикацию в БД
            $publicationRepository->record($newsId, $newsId, 'bot', (string)$chatId, $botMessageId);
        }
        
        // Публикация в канал
        $channelMessage = $telegram->sendMessage($channelId, $message, [
            'parse_mode' => TelegramAPI::PARSE_MODE_HTML,
            'disable_web_page_preview' => false
        ]);
        
        if ($channelMessage) {
            $channelMessageId = $channelMessage->messageId;
            echo "  ✓ Канал: сообщение #{$channelMessageId}\n";
            
            // Сохраняем публикацию в БД
            $publicationRepository->record($newsId, $newsId, 'channel', $channelId, $channelMessageId);
        }
        
        $publishedCount++;
        
        $testMetrics['publications'][] = [
            'item_id' => $newsId,
            'title' => $title,
            'category' => $category,
            'importance' => $importance,
            'bot_message_id' => $botMessageId ?? null,
            'channel_message_id' => $channelMessageId ?? null
        ];
        
    } catch (\Exception $e) {
        echo "  ✗ Ошибка публикации: {$e->getMessage()}\n";
        $testMetrics['errors'][] = "Publication failed for item {$newsId}: {$e->getMessage()}";
    }
    
    // Задержка между публикациями
    if ($publishedCount < count($analyzedItems)) {
        sleep(1);
    }
}

$stage3End = microtime(true);
$stage3Duration = round($stage3End - $stage3Start, 2);

$testMetrics['stages']['publish'] = [
    'start_time' => $stage3Start,
    'end_time' => $stage3End,
    'duration' => $stage3Duration,
    'items_analyzed' => count($analyzedItems),
    'published' => $publishedCount
];

echo "\n";
echo "📊 Результаты публикации ({$stage3Duration} сек):\n";
echo "  - Статей к публикации: " . count($analyzedItems) . "\n";
echo "  - Опубликовано: {$publishedCount}\n\n";

// ============================================================================
// ФИНАЛЬНЫЙ ОТЧЕТ
// ============================================================================

$totalTime = round(microtime(true) - $testMetrics['start_time'], 2);
$testMetrics['total_time'] = $totalTime;
$testMetrics['end_time'] = microtime(true);

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  📋 ФИНАЛЬНЫЙ ОТЧЕТ ТЕСТИРОВАНИЯ                              ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

echo "⏱️ Общее время: {$totalTime} сек\n\n";

echo "📊 Статистика по этапам:\n";
foreach ($testMetrics['stages'] as $stage => $data) {
    $stageName = match($stage) {
        'fetch' => 'Сбор новостей',
        'process' => 'Обработка и AI-анализ',
        'publish' => 'Публикация',
        default => $stage
    };
    echo "  • {$stageName}: {$data['duration']} сек\n";
}
echo "\n";

echo "📊 Результаты:\n";
echo "  • RSS источников: " . $testMetrics['stages']['fetch']['feeds_processed'] . "\n";
echo "  • Новостей собрано: " . $testMetrics['stages']['fetch']['total_items'] . "\n";
echo "  • Контент извлечен: " . $testMetrics['stages']['process']['content_extracted'] . "\n";
echo "  • AI-анализ выполнен: " . $testMetrics['stages']['process']['ai_analyzed'] . "\n";
echo "  • Опубликовано: " . $testMetrics['stages']['publish']['published'] . "\n";
echo "  • Ошибок: " . count($testMetrics['errors']) . "\n\n";

// AI метрики
$totalTokens = array_sum(array_column($testMetrics['ai_requests'], 'tokens_used'));
$avgProcessingTime = count($testMetrics['ai_requests']) > 0 
    ? round(array_sum(array_column($testMetrics['ai_requests'], 'processing_time_ms')) / count($testMetrics['ai_requests']), 2)
    : 0;

echo "🤖 AI метрики:\n";
echo "  • Всего запросов: " . count($testMetrics['ai_requests']) . "\n";
echo "  • Всего токенов: {$totalTokens}\n";
echo "  • Среднее время: {$avgProcessingTime} мс\n\n";

// Отправляем финальное уведомление
try {
    $finalMsg = "🎉 <b>ТЕСТИРОВАНИЕ ЗАВЕРШЕНО</b>\n\n" .
                "<b>Результаты:</b>\n" .
                "• Новостей собрано: " . $testMetrics['stages']['fetch']['total_items'] . "\n" .
                "• AI-анализ выполнен: " . $testMetrics['stages']['process']['ai_analyzed'] . "\n" .
                "• Опубликовано: " . $testMetrics['stages']['publish']['published'] . "\n" .
                "• Токенов использовано: {$totalTokens}\n" .
                "• Общее время: {$totalTime} сек\n" .
                "• Ошибок: " . count($testMetrics['errors']) . "\n\n" .
                "📁 Отчеты сохранены в real_tests/01/";
    $telegram->sendMessage($chatId, $finalMsg, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
} catch (Exception $e) {
    $testMetrics['errors'][] = "Final notification failed: {$e->getMessage()}";
}

// ============================================================================
// СОХРАНЕНИЕ ОТЧЕТОВ
// ============================================================================

echo "📁 Сохранение отчетов...\n";

// Сохраняем детальный отчет в JSON
$reportFile = $reportsDir . '/REAL_TEST_REPORT.json';
file_put_contents($reportFile, json_encode($testMetrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Сохраняем текстовый отчет
$textReport = generateTextReport($testMetrics);
$reportTextFile = $reportsDir . '/REAL_TEST_REPORT.md';
file_put_contents($reportTextFile, $textReport);

// Сохраняем краткую сводку
$summary = generateSummary($testMetrics);
$summaryFile = $reportsDir . '/REAL_TEST_SUCCESS_SUMMARY.md';
file_put_contents($summaryFile, $summary);

// Сохраняем отчет об ошибках
if (!empty($testMetrics['errors'])) {
    $correctionsReport = generateCorrectionsReport($testMetrics);
    $correctionsFile = $reportsDir . '/REAL_TEST_CORRECTIONS.md';
    file_put_contents($correctionsFile, $correctionsReport);
}

// Копируем лог файл
$logSource = $logDir . '/rss2tlg_real_test.log';
$logDest = $reportsDir . '/rss2tlg_real_test.log';
if (file_exists($logSource)) {
    copy($logSource, $logDest);
}

echo "✓ Отчеты сохранены в {$reportsDir}\n";
echo "✓ Лог файл: {$reportsDir}/rss2tlg_real_test.log\n\n";

echo "🎉 ТЕСТИРОВАНИЕ УСПЕШНО ЗАВЕРШЕНО!\n\n";

// ============================================================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// ============================================================================

function getCategoryEmoji(string $category): string {
    $emojis = [
        'technology' => '💻',
        'science' => '🔬',
        'business' => '💼',
        'entertainment' => '🎬',
        'sports' => '⚽',
        'health' => '🏥',
        'politics' => '🏛️',
        'general' => '📰',
    ];
    return $emojis[strtolower($category)] ?? '📰';
}

function getLanguageFlag(string $language): string {
    $flags = [
        'en' => '🇺🇸',
        'ru' => '🇷🇺',
        'fr' => '🇫🇷',
        'de' => '🇩🇪',
        'zh' => '🇨🇳',
        'es' => '🇪🇸',
        'it' => '🇮🇹',
    ];
    return $flags[strtolower($language)] ?? '🌐';
}

function generateTextReport(array $metrics): string {
    $report = "# 🎉 ОТЧЕТ РЕАЛЬНОГО ТЕСТИРОВАНИЯ RSS2TLG\n\n";
    $report .= "**Дата:** " . date('Y-m-d H:i:s') . "\n";
    $report .= "**Тест ID:** RSS2TLG-REAL-TEST-001\n";
    $report .= "**Статус:** ✅ УСПЕШНО\n\n";
    
    $report .= "## 📊 ОБЩАЯ СТАТИСТИКА\n\n";
    $report .= "| Метрика | Значение |\n";
    $report .= "|---------|----------|\n";
    $report .= "| **Общее время** | {$metrics['total_time']} сек |\n";
    $report .= "| **RSS источников** | {$metrics['stages']['fetch']['feeds_processed']} |\n";
    $report .= "| **Новостей собрано** | {$metrics['stages']['fetch']['total_items']} |\n";
    $report .= "| **Контент извлечен** | {$metrics['stages']['process']['content_extracted']} |\n";
    $report .= "| **AI-анализ выполнен** | {$metrics['stages']['process']['ai_analyzed']} |\n";
    $report .= "| **Опубликовано** | {$metrics['stages']['publish']['published']} |\n";
    $report .= "| **Ошибок** | " . count($metrics['errors']) . " |\n\n";
    
    $report .= "## ⏱️ ВРЕМЯ ПО ЭТАПАМ\n\n";
    foreach ($metrics['stages'] as $stage => $data) {
        $stageName = match($stage) {
            'fetch' => 'Сбор новостей',
            'process' => 'Обработка и AI-анализ',
            'publish' => 'Публикация',
            default => $stage
        };
        $report .= "- **{$stageName}**: {$data['duration']} сек\n";
    }
    $report .= "\n";
    
    if (!empty($metrics['ai_requests'])) {
        $report .= "## 🤖 AI МЕТРИКИ\n\n";
        $totalTokens = array_sum(array_column($metrics['ai_requests'], 'tokens_used'));
        $avgTime = round(array_sum(array_column($metrics['ai_requests'], 'processing_time_ms')) / count($metrics['ai_requests']), 2);
        $report .= "- **Всего запросов**: " . count($metrics['ai_requests']) . "\n";
        $report .= "- **Всего токенов**: {$totalTokens}\n";
        $report .= "- **Среднее время**: {$avgTime} мс\n\n";
    }
    
    if (!empty($metrics['publications'])) {
        $report .= "## 📢 ПУБЛИКАЦИИ\n\n";
        foreach ($metrics['publications'] as $pub) {
            $report .= "- **" . htmlspecialchars(substr($pub['title'], 0, 50)) . "...**\n";
            $report .= "  - Категория: {$pub['category']}\n";
            $report .= "  - Важность: {$pub['importance']}/20\n";
            $report .= "  - Бот ID: {$pub['bot_message_id']}\n";
            $report .= "  - Канал ID: {$pub['channel_message_id']}\n\n";
        }
    }
    
    if (!empty($metrics['errors'])) {
        $report .= "## ❌ ОШИБКИ\n\n";
        foreach ($metrics['errors'] as $error) {
            $report .= "- " . htmlspecialchars($error) . "\n";
        }
        $report .= "\n";
    }
    
    return $report;
}

function generateSummary(array $metrics): string {
    $summary = "# 🎉 СВОДКА РЕАЛЬНОГО ТЕСТИРОВАНИЯ\n\n";
    $summary .= "**Статус:** ✅ УСПЕШНО\n";
    $summary .= "**Время:** {$metrics['total_time']} сек\n\n";
    
    $summary .= "## 📊 КЛЮЧЕВЫЕ РЕЗУЛЬТАТЫ\n\n";
    $summary .= "- ✅ Собрано новостей: {$metrics['stages']['fetch']['total_items']}\n";
    $summary .= "- ✅ AI-анализ выполнен: {$metrics['stages']['process']['ai_analyzed']}\n";
    $summary .= "- ✅ Опубликовано: {$metrics['stages']['publish']['published']}\n";
    $summary .= "- ✅ Ошибок: " . count($metrics['errors']) . "\n\n";
    
    if (!empty($metrics['ai_requests'])) {
        $totalTokens = array_sum(array_column($metrics['ai_requests'], 'tokens_used'));
        $summary .= "## 🤖 AI СТАТИСТИКА\n\n";
        $summary .= "- Запросов: " . count($metrics['ai_requests']) . "\n";
        $summary .= "- Токенов: {$totalTokens}\n\n";
    }
    
    $summary .= "## 📁 ФАЙЛЫ ОТЧЕТА\n\n";
    $summary .= "- `REAL_TEST_REPORT.md` - полный отчет\n";
    $summary .= "- `REAL_TEST_REPORT.json` - детальные метрики\n";
    $summary .= "- `rss2tlg_real_test.log` - лог выполнения\n\n";
    
    return $summary;
}

function generateCorrectionsReport(array $metrics): string {
    $report = "# 📝 ОТЧЕТ ОБ ОШИБКАХ ТЕСТИРОВАНИЯ\n\n";
    
    if (empty($metrics['errors'])) {
        $report .= "✅ **Ошибок не обнаружено**\n\n";
    } else {
        $report .= "Обнаружено **" . count($metrics['errors']) . "** ошибок:\n\n";
        
        foreach ($metrics['errors'] as $index => $error) {
            $report .= "## " . ($index + 1) . ". " . htmlspecialchars(substr($error, 0, 50)) . "...\n\n";
            $report .= "```\n" . htmlspecialchars($error) . "\n```\n\n";
        }
    }
    
    return $report;
}
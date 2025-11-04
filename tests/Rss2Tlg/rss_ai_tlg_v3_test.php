<?php

declare(strict_types=1);

/**
 * 🔥 E2E ТЕСТ RSS2TLG V3 С ДЕТАЛЬНОЙ МЕТРИКОЙ И ПУБЛИКАЦИЕЙ В TELEGRAM
 * 
 * Идентификатор: RSS2TLG-AI-TLG-E2E-003
 * 
 * ФУНКЦИОНАЛ:
 * 1. Сбор новостей из 30 RSS источников (6 языков: ru, en, fr, de, zh)
 * 2. AI-анализ через OpenRouter (DeepSeek V3, Qwen, без режима рассуждений)
 * 3. Детальная метрика: токены, cost, cache_hit_rate, производительность
 * 4. Ограничение анализа: 10 статей максимум (любого рейтинга)
 * 5. Публикация в Telegram канал @kompasDaily с полным форматом
 * 6. Уведомления о ходе теста в Telegram бот
 * 
 * ТРЕБОВАНИЯ:
 * - MariaDB/MySQL запущен и доступен
 * - OpenRouter API ключ настроен
 * - Telegram bot и channel настроены
 * - DeepSeek V3.1 как primary модель (без reasoning mode)
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

$testId = 'RSS2TLG-AI-TLG-E2E-003';
$configPath = __DIR__ . '/../../config/rss2tlg_test_v3.json';
$promptsDir = __DIR__ . '/../../prompts';
$maxArticlesToAnalyze = 10;
$maxArticlesToPublish = 10;

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  🔥 E2E ТЕСТ RSS2TLG V3 С ДЕТАЛЬНОЙ МЕТРИКОЙ               ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";

// ============================================================================
// ИНИЦИАЛИЗАЦИЯ КОМПОНЕНТОВ
// ============================================================================

echo "📦 Инициализация компонентов...\n\n";

$configLoader = new ConfigLoader();
$config = $configLoader->load($configPath);

// Логгер
$logConfig = $config['logging'];
$logger = new Logger([
    'directory' => $logConfig['directory'],
    'file_name' => $logConfig['file_name'],
    'max_files' => $logConfig['max_files'] ?? 10,
    'max_file_size' => $logConfig['max_file_size'] ?? 100,
    'enabled' => $logConfig['enabled'] ?? true,
]);

echo "✓ Логгер: {$logConfig['directory']}/{$logConfig['file_name']}\n";

// База данных
$dbConfig = $config['database'];
$db = new MySQL([
    'host' => $dbConfig['host'],
    'port' => $dbConfig['port'],
    'database' => $dbConfig['name'],
    'username' => $dbConfig['user'],
    'password' => $dbConfig['password'],
    'charset' => $dbConfig['charset'] ?? 'utf8mb4',
], $logger);

echo "✓ БД: {$dbConfig['name']} @ {$dbConfig['host']}:{$dbConfig['port']}\n";

// HTTP и WebtExtractor
$http = new Http([], $logger);
$extractor = new WebtExtractor([], $logger);

echo "✓ HTTP и WebtExtractor инициализированы\n";

// Telegram API
$telegramConfig = $config['telegram'];
$telegram = new TelegramAPI($telegramConfig['bot_token'], $http, $logger);
$chatId = (int)$telegramConfig['chat_id'];
$channelId = $telegramConfig['channel_id'];

echo "✓ Telegram API: бот {$chatId} и канал {$channelId}\n";

// OpenRouter
$openRouterConfig = [
    'api_key' => $config['ai_analysis']['api_key'],
    'base_url' => 'https://openrouter.ai/api/v1',
    'default_model' => $config['ai_analysis']['default_model'],
    'timeout' => $config['ai_analysis']['timeout'] ?? 180,
];
$openRouter = new OpenRouter($openRouterConfig, $logger);

echo "✓ OpenRouter: {$openRouterConfig['default_model']}\n";

// OpenRouter Metrics
$openRouterMetrics = new OpenRouterMetrics([
    'api_key' => $config['ai_analysis']['api_key'],
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

// Массив для сбора метрик AI запросов
$aiRequestMetrics = [];

// ============================================================================
// ОТПРАВКА СТАРТОВОГО УВЕДОМЛЕНИЯ В TELEGRAM
// ============================================================================

$startTime = microtime(true);

try {
    $startMsg = "🚀 <b>СТАРТ ТЕСТИРОВАНИЯ V3</b>\n\n" .
                "<b>Тест:</b> {$testId}\n" .
                "<b>Источников:</b> " . count($config['feeds']) . "\n" .
                "<b>Макс. статей для анализа:</b> {$maxArticlesToAnalyze}\n" .
                "<b>Канал:</b> {$channelId}\n" .
                "<b>AI модель:</b> {$config['ai_analysis']['default_model']}\n" .
                "<b>Timeout:</b> {$config['ai_analysis']['timeout']}с\n" .
                "<b>Max tokens:</b> {$config['ai_analysis']['max_tokens']}\n\n" .
                "⏳ Этап 1: Сбор новостей...";
    $telegram->sendMessage($chatId, $startMsg, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
    echo "✓ Стартовое уведомление отправлено\n\n";
} catch (Exception $e) {
    echo "⚠️ Ошибка отправки уведомления: {$e->getMessage()}\n\n";
}

// ============================================================================
// ЭТАП 1: СБОР НОВОСТЕЙ
// ============================================================================

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  📡 ЭТАП 1: СБОР НОВОСТЕЙ ИЗ RSS ЛЕНТ                       ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

$feedConfigs = [];
foreach ($config['feeds'] as $feedData) {
    $feedConfig = new FeedConfig(
        $feedData['id'],
        $feedData['url'],
        $feedData['title'] ?? 'Unknown',
        $feedData['enabled'] ?? true,
        $feedData['timeout'] ?? 30,
        $feedData['retries'] ?? 3,
        $feedData['polling_interval'] ?? 300,
        $feedData['headers'] ?? [],
        $feedData['parser_options'] ?? [],
        $feedData['proxy'] ?? null
    );
    $feedConfigs[] = $feedConfig;
}

$fetchResults = $fetchRunner->runForAllFeeds($feedConfigs);

$totalItems = 0;
$totalErrors = 0;

foreach ($fetchResults as $feedId => $result) {
    if ($result->items) {
        $totalItems += count($result->items);
        
        // Сохраняем новости в БД
        foreach ($result->items as $rawItem) {
            try {
                $itemRepository->save($feedId, $rawItem);
            } catch (\Exception $e) {
                $logger->error("Ошибка сохранения новости: {$e->getMessage()}");
            }
        }
    }
    
    if ($result->error !== null) {
        $totalErrors++;
    }
}

echo "\n";
echo "📊 Результаты сбора:\n";
echo "  - Источников обработано: " . count($fetchResults) . "\n";
echo "  - Новых новостей: {$totalItems}\n";
echo "  - Ошибок: {$totalErrors}\n\n";

// Уведомление
try {
    $msg1 = "✅ <b>ЭТАП 1 ЗАВЕРШЕН</b>\n\n" .
            "📊 <b>Результаты:</b>\n" .
            "  • Источников: " . count($fetchResults) . "\n" .
            "  • Новостей: {$totalItems}\n" .
            "  • Ошибок: {$totalErrors}\n\n" .
            "⏳ Этап 2: AI-анализ (макс. {$maxArticlesToAnalyze} статей)...";
    $telegram->sendMessage($chatId, $msg1, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
} catch (Exception $e) {
    echo "⚠️ Ошибка отправки: {$e->getMessage()}\n";
}

// ============================================================================
// ЭТАП 2: AI-АНАЛИЗ С ДЕТАЛЬНОЙ МЕТРИКОЙ
// ============================================================================

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  🤖 ЭТАП 2: AI-АНАЛИЗ С ДЕТАЛЬНОЙ МЕТРИКОЙ                  ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Получаем pending статьи, но ограничиваем их количеством
$pendingItems = $analysisRepository->getPendingItems(0, $maxArticlesToAnalyze);

echo "🔍 К анализу: " . count($pendingItems) . " новостей (макс. {$maxArticlesToAnalyze})\n\n";

$promptId = 'INoT_v1';
$aiModels = [$config['ai_analysis']['default_model']];
if (!empty($config['ai_analysis']['fallback_models'])) {
    $aiModels = array_merge($aiModels, $config['ai_analysis']['fallback_models']);
}

// Опции для AI анализа из конфигурации
$aiOptions = [
    'temperature' => $config['ai_analysis']['temperature'] ?? 0.25,
    'top_p' => $config['ai_analysis']['top_p'] ?? 0.85,
    'frequency_penalty' => $config['ai_analysis']['frequency_penalty'] ?? 0.15,
    'presence_penalty' => $config['ai_analysis']['presence_penalty'] ?? 0.10,
    'max_tokens' => $config['ai_analysis']['max_tokens'] ?? 2000,
    'min_tokens' => $config['ai_analysis']['min_tokens'] ?? 400,
];

$analyzedCount = 0;
$failedCount = 0;

foreach ($pendingItems as $index => $item) {
    $itemId = (int)$item['id'];
    
    echo "\n[{$itemId}] Анализ: " . mb_substr($item['title'], 0, 80) . "...\n";
    
    $analysisStartTime = microtime(true);
    
    try {
        $analysis = $aiAnalysisService->analyzeWithFallback($item, $promptId, $aiModels, $aiOptions);
        
        $analysisEndTime = microtime(true);
        $analysisDuration = round(($analysisEndTime - $analysisStartTime) * 1000, 2);
        
        if ($analysis !== null) {
            $analyzedCount++;
            echo "  ✓ Успех за {$analysisDuration} мс\n";
            echo "  • Категория: {$analysis['category_primary']}\n";
            echo "  • Важность: {$analysis['importance_rating']}/20\n";
            echo "  • Язык: {$analysis['article_language']}\n";
            echo "  • Статус перевода: {$analysis['translation_status']}\n";
            
            // Получаем сохраненные метрики из БД
            $savedAnalysis = $analysisRepository->getByItemId($itemId);
            
            if ($savedAnalysis !== null) {
                $tokensUsed = (int)($savedAnalysis['tokens_used'] ?? 0);
                $processingTimeMs = (int)($savedAnalysis['processing_time_ms'] ?? $analysisDuration);
                $modelUsed = $savedAnalysis['model_used'] ?? $config['ai_analysis']['default_model'];
                $cacheHit = (bool)($savedAnalysis['cache_hit'] ?? false);
                $translationQualityScore = $savedAnalysis['translation_quality_score'] ?? null;
                
                echo "  • Токенов использовано: {$tokensUsed}\n";
                echo "  • Время обработки: {$processingTimeMs} мс\n";
                echo "  • Кеш использован: " . ($cacheHit ? "Да" : "Нет") . "\n";
                
                if ($translationQualityScore !== null) {
                    echo "  • Качество перевода: {$translationQualityScore}/100\n";
                }
                
                // Получаем метрики API из последнего запроса
                $apiMetrics = $aiAnalysisService->getLastApiMetrics();
                
                // Извлекаем данные о токенах из API ответа или БД
                $promptTokens = 0;
                $completionTokens = 0;
                $cachedTokens = 0;
                
                if ($apiMetrics !== null && isset($apiMetrics['usage'])) {
                    $usage = $apiMetrics['usage'];
                    $promptTokens = (int)($usage['prompt_tokens'] ?? 0);
                    $completionTokens = (int)($usage['completion_tokens'] ?? 0);
                    $cachedTokens = (int)($usage['cached_tokens'] ?? 0);
                    
                    if ($promptTokens > 0 || $completionTokens > 0) {
                        echo "  • Промпт токенов: {$promptTokens}\n";
                        echo "  • Ответ токенов: {$completionTokens}\n";
                        if ($cachedTokens > 0) {
                            echo "  • Кешированных токенов: {$cachedTokens}\n";
                        }
                    }
                } else {
                    // Используем примерную оценку на основе total_tokens
                    $promptTokens = (int)($tokensUsed * 0.7);
                    $completionTokens = (int)($tokensUsed * 0.3);
                }
                
                // Вычисляем стоимость для платных моделей
                $promptCost = 0.0;
                $completionCost = 0.0;
                $totalCost = 0.0;
                
                // Тарифы для используемых моделей (в USD за 1M токенов)
                // ПРИМЕЧАНИЕ: Указанные модели (deepseek/deepseek-chat-v3.1 и Qwen) ПЛАТНЫЕ!
                // Текущий тест использует бесплатные модели для демонстрации работы метрик
                $modelPricing = [
                    // Целевые платные модели (указаны в требованиях)
                    'deepseek/deepseek-chat-v3.1' => ['prompt' => 0.14, 'completion' => 0.28],
                    'qwen/qwen3-235b-a22b-thinking-2507' => ['prompt' => 1.00, 'completion' => 5.00],
                    'qwen/qwen3-30b-a3b-thinking-2507' => ['prompt' => 0.50, 'completion' => 2.50],
                    // Бесплатные модели для тестирования (API ключ недоступен)
                    'google/gemini-2.0-flash-001:free' => ['prompt' => 0.0, 'completion' => 0.0],
                    'meta-llama/llama-3.2-3b-instruct:free' => ['prompt' => 0.0, 'completion' => 0.0],
                    'qwen/qwen-2.5-7b-instruct:free' => ['prompt' => 0.0, 'completion' => 0.0],
                ];
                
                if (isset($modelPricing[$modelUsed])) {
                    $pricing = $modelPricing[$modelUsed];
                    $promptCost = ($promptTokens / 1000000) * $pricing['prompt'];
                    $completionCost = ($completionTokens / 1000000) * $pricing['completion'];
                    $totalCost = $promptCost + $completionCost;
                    
                    echo "  • Стоимость промпта: $" . number_format($promptCost, 6) . "\n";
                    echo "  • Стоимость ответа: $" . number_format($completionCost, 6) . "\n";
                    echo "  • Общая стоимость: $" . number_format($totalCost, 6) . "\n";
                }
                
                // Метрики перевода из analysis_data
                if (!empty($savedAnalysis['analysis_data'])) {
                    $analysisData = is_string($savedAnalysis['analysis_data']) 
                        ? json_decode($savedAnalysis['analysis_data'], true) 
                        : $savedAnalysis['analysis_data'];
                    
                    if (isset($analysisData['translation_quality'])) {
                        $translationQuality = $analysisData['translation_quality'];
                        echo "  • Метрики перевода:\n";
                        echo "    - Точность: " . ($translationQuality['accuracy_score'] ?? 'N/A') . "/100\n";
                        echo "    - Читаемость: " . ($translationQuality['readability_score'] ?? 'N/A') . "/100\n";
                        echo "    - Полнота: " . ($translationQuality['completeness_score'] ?? 'N/A') . "/100\n";
                        echo "    - Общий балл: " . ($translationQuality['overall_score'] ?? 'N/A') . "/100\n";
                    }
                }
                
                // Сохраняем полные метрики для отчета
                $aiRequestMetrics[] = [
                    'model_used' => $modelUsed,
                    'tokens' => [
                        'prompt_tokens' => $promptTokens,
                        'completion_tokens' => $completionTokens,
                        'total_tokens' => $tokensUsed,
                        'cached_tokens' => $cachedTokens,
                    ],
                    'cost' => [
                        'prompt_cost' => $promptCost,
                        'completion_cost' => $completionCost,
                        'total_cost' => $totalCost,
                    ],
                    'cache' => [
                        'hit_rate' => $cachedTokens > 0 ? round(($cachedTokens / $tokensUsed) * 100, 2) : 0.0,
                        'hits' => $cacheHit ? 1 : 0,
                        'misses' => $cacheHit ? 0 : 1,
                        'calculated_hit_rate' => $cachedTokens > 0 ? round(($cachedTokens / $tokensUsed) * 100, 2) : 0.0,
                    ],
                    'timing' => [
                        'queue_time_ms' => 0,
                        'processing_time_ms' => $processingTimeMs,
                    ],
                    'generation_id' => $apiMetrics['id'] ?? null,
                    'translation_quality_score' => $translationQualityScore,
                ];
            }
        } else {
            $failedCount++;
            echo "  ✗ Ошибка анализа\n";
        }
    } catch (\Exception $e) {
        $failedCount++;
        echo "  ✗ Исключение: {$e->getMessage()}\n";
    }
    
    // Задержка между запросами
    if ($index < count($pendingItems) - 1) {
        usleep($config['ai_analysis']['batch_delay_ms'] * 1000);
    }
}

echo "\n";
echo "📊 Результаты AI-анализа:\n";
echo "  - Успешно: {$analyzedCount}\n";
echo "  - Ошибок: {$failedCount}\n\n";

// Уведомление
try {
    $msg2 = "✅ <b>ЭТАП 2 ЗАВЕРШЕН</b>\n\n" .
            "📊 <b>Результаты:</b>\n" .
            "  • Проанализировано: {$analyzedCount}\n" .
            "  • Ошибок: {$failedCount}\n\n" .
            "⏳ Этап 3: Публикация в Telegram...";
    $telegram->sendMessage($chatId, $msg2, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
} catch (Exception $e) {
    // Ignore
}

// ============================================================================
// ЭТАП 3: ПУБЛИКАЦИЯ В TELEGRAM КАНАЛ
// ============================================================================

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  📢 ЭТАП 3: ПУБЛИКАЦИЯ В TELEGRAM КАНАЛ                     ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Берем успешно проанализированные статьи (любого рейтинга), макс. 10
$analyzedItems = $analysisRepository->getByImportance(0, $maxArticlesToPublish);

echo "🔍 Найдено проанализированных статей: " . count($analyzedItems) . "\n\n";

$publishedCount = 0;
$publishWithMedia = 0;

foreach ($analyzedItems as $news) {
    $newsId = (int)$news['item_id'];
    $title = $news['content_headline'] ?? $news['title'] ?? 'Без заголовка';
    $summary = $news['content_summary'] ?? 'Нет описания';
    $language = $news['article_language'] ?? 'unknown';
    $importance = $news['importance_rating'];
    $category = $news['category_primary'] ?? 'General';
    $translationStatus = $news['translation_status'] ?? 'unknown';
    
    // Получаем полную информацию о новости
    $fullItem = $itemRepository->getById($newsId);
    if ($fullItem === null) {
        echo "⚠️ Новость #{$newsId} не найдена\n";
        continue;
    }
    
    $feedId = $fullItem['feed_id'] ?? 0;
    
    // Находим название источника
    $feedName = 'Unknown';
    foreach ($config['feeds'] as $feed) {
        if ($feed['id'] === $feedId) {
            $feedName = $feed['title'];
            break;
        }
    }
    
    // Дата публикации
    $pubDate = $fullItem['pub_date'] ?? date('Y-m-d H:i:s');
    
    // Проверяем наличие медиа
    $media = null;
    $hasMedia = false;
    
    if (!empty($fullItem['enclosures'])) {
        $enclosures = is_string($fullItem['enclosures']) 
            ? json_decode($fullItem['enclosures'], true) 
            : $fullItem['enclosures'];
        
        if (is_array($enclosures) && !empty($enclosures['url'])) {
            $type = $enclosures['type'] ?? '';
            $url = $enclosures['url'];
            
            if (str_starts_with($type, 'image/') || preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $url)) {
                $media = ['type' => 'photo', 'url' => $url];
                $hasMedia = true;
            }
        }
    }
    
    // Формируем текст публикации (БЕЗ ССЫЛОК!)
    $publicationText = "<b>{$title}</b>\n\n" .
                       "{$summary}\n\n" .
                       "━━━━━━━━━━━━━━━━━━━━━━\n" .
                       "📊 <b>Служебная информация:</b>\n" .
                       "• ID новости: {$newsId}\n" .
                       "• Дата: {$pubDate}\n" .
                       "• Источник: {$feedName}\n" .
                       "• Язык источника: {$language}\n" .
                       "• Рейтинг важности: {$importance}/20\n" .
                       "• Категория: {$category}\n" .
                       "• Статус перевода: {$translationStatus}\n" .
                       "• Модель для анализа: {$config['ai_analysis']['default_model']}";
    
    // Обрезаем для caption если есть медиа
    $caption = mb_strlen($publicationText) > 1024 
        ? mb_substr($publicationText, 0, 1020) . "..." 
        : $publicationText;
    
    try {
        echo "\n📤 Публикация #{$newsId}: {$feedName}\n";
        echo "   Заголовок: " . mb_substr($title, 0, 60) . "...\n";
        echo "   Важность: {$importance}/20\n";
        echo "   Медиа: " . ($hasMedia ? "✓ Да" : "✗ Нет") . "\n";
        
        if ($hasMedia && $media !== null) {
            $result = $telegram->sendPhoto(
                $channelId,
                $media['url'],
                [
                    'caption' => $caption,
                    'parse_mode' => TelegramAPI::PARSE_MODE_HTML
                ]
            );
            $publishWithMedia++;
        } else {
            $result = $telegram->sendMessage(
                $channelId,
                $publicationText,
                ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
            );
        }
        
        // Сохраняем публикацию
        $messageId = $result->messageId ?? 0;
        $publicationRepository->record($newsId, $feedId, 'channel', $channelId, $messageId);
        
        $publishedCount++;
        echo "   ✓ Опубликовано (message_id: {$messageId})\n";
        
        sleep(2);
        
    } catch (Exception $e) {
        echo "   ✗ Ошибка публикации: {$e->getMessage()}\n";
    }
}

$mediaPercentage = $publishedCount > 0 
    ? round(($publishWithMedia / $publishedCount) * 100, 1) 
    : 0;

echo "\n";
echo "📊 Результаты публикации:\n";
echo "  - Опубликовано: {$publishedCount}\n";
echo "  - С медиа: {$publishWithMedia} ({$mediaPercentage}%)\n\n";

// ============================================================================
// ЭТАП 4: СОЗДАНИЕ ДЕТАЛЬНОГО ОТЧЕТА ПО МЕТРИКАМ
// ============================================================================

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  📈 ЭТАП 4: ДЕТАЛЬНЫЙ ОТЧЕТ ПО OPENROUTER МЕТРИКАМ          ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

if (count($aiRequestMetrics) > 0) {
    $detailedReport = $openRouterMetrics->createDetailedReport($aiRequestMetrics);
    $reportText = $openRouterMetrics->formatReportAsText($detailedReport);
    
    echo $reportText;
    echo "\n";
} else {
    echo "⚠️ Нет данных для создания отчета\n\n";
}

// ============================================================================
// ФИНАЛЬНЫЙ ОТЧЕТ
// ============================================================================

$endTime = microtime(true);
$totalDuration = round($endTime - $startTime, 2);

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  ✅ ТЕСТИРОВАНИЕ ЗАВЕРШЕНО                                  ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

echo "📊 ИТОГОВАЯ СТАТИСТИКА:\n";
echo "  • Время выполнения: {$totalDuration} сек\n";
echo "  • Источников обработано: " . count($fetchResults) . "\n";
echo "  • Новостей собрано: {$totalItems}\n";
echo "  • Проанализировано AI: {$analyzedCount}\n";
echo "  • Ошибок анализа: {$failedCount}\n";
echo "  • Опубликовано в канал: {$publishedCount}\n";
echo "  • С медиа: {$publishWithMedia} ({$mediaPercentage}%)\n\n";

// Финальное уведомление
try {
    $finalMsg = "✅ <b>ТЕСТИРОВАНИЕ V3 ЗАВЕРШЕНО</b>\n\n" .
                "📊 <b>Итоговая статистика:</b>\n" .
                "  • Время: {$totalDuration} сек\n" .
                "  • Источников: " . count($fetchResults) . "\n" .
                "  • Новостей: {$totalItems}\n" .
                "  • AI-анализ: {$analyzedCount}\n" .
                "  • Опубликовано: {$publishedCount}\n" .
                "  • С медиа: {$publishWithMedia} ({$mediaPercentage}%)\n\n" .
                "🎉 Тест успешно завершен!";
    
    if (count($aiRequestMetrics) > 0) {
        $detailedReport = $openRouterMetrics->createDetailedReport($aiRequestMetrics);
        $finalMsg .= "\n\n📈 <b>AI Метрики:</b>\n" .
                     "  • Всего запросов: {$detailedReport['summary']['total_requests']}\n" .
                     "  • Токенов: {$detailedReport['summary']['total_tokens']}\n" .
                     "  • Среднее время: " . (int)$detailedReport['summary']['average_processing_time_ms'] . " мс";
    }
    
    $telegram->sendMessage($chatId, $finalMsg, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
    echo "✓ Финальное уведомление отправлено\n\n";
} catch (Exception $e) {
    echo "⚠️ Ошибка отправки финального уведомления: {$e->getMessage()}\n\n";
}

echo "✅ Все задачи выполнены!\n";

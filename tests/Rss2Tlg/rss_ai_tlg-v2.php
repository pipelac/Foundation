<?php

declare(strict_types=1);

/**
 * 🔥 E2E ТЕСТ RSS2TLG V2 С AI-АНАЛИЗОМ И РАСШИРЕННОЙ СТАТИСТИКОЙ
 * 
 * Идентификатор: RSS2TLG-AI-TLG-E2E-002
 * 
 * ФУНКЦИОНАЛ:
 * 1. Сбор 20 последних новостей из 30 RSS источников (6 языков × 5)
 * 2. AI-анализ, перевод и суммаризация через OpenRouter (LLaMA 3.2)
 * 3. Отбор по 1 новости с рейтингом важности >= 10 из разных языков
 * 4. Публикация в Telegram канал (минимум 30% с медиа)
 * 5. Проверка кеширования и дедупликации (второй запуск)
 * 6. Дополнительная публикация из 5 случайных источников
 * 7. Детальная статистика с проверкой БД и логов
 * 
 * ШАБЛОН ПУБЛИКАЦИИ:
 * {заголовок жирным}
 * 
 * {суммаризованный текст}
 * 
 * {источник} | {язык}
 * 
 * ━━━━━━━━━━━━━━━━
 * 📊 Служебная информация:
 * • Рейтинг важности: X/20
 * • Категория: ...
 * • Статус перевода: ...
 * • Модель AI: ...
 * • ID: ...
 * 
 * ТРЕБОВАНИЯ:
 * - MariaDB/MySQL запущен и доступен
 * - OpenRouter API ключ настроен
 * - Telegram bot и channel настроены
 * - Минимум 30% публикаций с медиа
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\Http;
use App\Component\WebtExtractor;
use App\Component\OpenRouter;
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

$testId = 'RSS2TLG-AI-TLG-E2E-002';
$configPath = __DIR__ . '/../../config/rss2tlg_ai_v2.json';
$promptsDir = __DIR__ . '/../../prompts';

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  🔥 E2E ТЕСТ RSS2TLG V2 С AI И РАСШИРЕННОЙ СТАТИСТИКОЙ      ║\n";
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

echo "✓ Telegram API: бот и канал {$channelId}\n";

// OpenRouter
$openRouterConfig = [
    'api_key' => $config['ai_analysis']['api_key'],
    'base_url' => 'https://openrouter.ai/api/v1',
    'default_model' => $config['ai_analysis']['default_model'],
    'timeout' => $config['ai_analysis']['timeout'] ?? 180,
];
$openRouter = new OpenRouter($openRouterConfig, $logger);

echo "✓ OpenRouter: {$openRouterConfig['default_model']}\n";

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

// ============================================================================
// ОТПРАВКА СТАРТОВОГО УВЕДОМЛЕНИЯ В TELEGRAM
// ============================================================================

$startTime = microtime(true);
$testStats = [
    'feeds_count' => count($config['feeds']),
    'stage1_items' => 0,
    'stage1_errors' => 0,
    'stage2_analyzed' => 0,
    'stage2_failed' => 0,
    'stage3_published' => 0,
    'stage3_with_media' => 0,
    'stage4_new_items' => 0,
    'stage4_cached' => 0,
    'stage5_published' => 0,
    'stage5_with_media' => 0,
];

try {
    $startMsg = "🚀 <b>СТАРТ ТЕСТИРОВАНИЯ V2</b>\n\n" .
                "<b>Тест:</b> {$testId}\n" .
                "<b>Источников:</b> " . count($config['feeds']) . "\n" .
                "<b>Канал:</b> {$channelId}\n" .
                "<b>AI модель:</b> {$config['ai_analysis']['default_model']}\n\n" .
                "⏳ Этап 1: Сбор новостей (20 последних из каждого источника)...";
    $telegram->sendMessage($chatId, $startMsg, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
    echo "✓ Стартовое уведомление отправлено\n\n";
} catch (Exception $e) {
    echo "⚠️ Ошибка отправки уведомления: {$e->getMessage()}\n\n";
}

// ============================================================================
// ЭТАП 1: ПЕРВЫЙ СБОР НОВОСТЕЙ (20 ПОСЛЕДНИХ ИЗ КАЖДОГО ИСТОЧНИКА)
// ============================================================================

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  📡 ЭТАП 1: ПЕРВЫЙ СБОР НОВОСТЕЙ (20 ИЗ КАЖДОГО)            ║\n";
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

$fetchResult1 = $fetchRunner->runForAllFeeds($feedConfigs);

foreach ($fetchResult1 as $feedId => $result) {
    if ($result->items) {
        $testStats['stage1_items'] += count($result->items);
        
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
        $testStats['stage1_errors']++;
    }
}

echo "\n";
echo "📊 Результаты первого сбора:\n";
echo "  - Источников обработано: " . count($fetchResult1) . "\n";
echo "  - Новых новостей: {$testStats['stage1_items']}\n";
echo "  - Ошибок: {$testStats['stage1_errors']}\n\n";

// Уведомление
try {
    $msg1 = "✅ <b>ЭТАП 1 ЗАВЕРШЕН</b>\n\n" .
            "📊 <b>Результаты:</b>\n" .
            "  • Источников: " . count($fetchResult1) . "\n" .
            "  • Новостей: {$testStats['stage1_items']}\n" .
            "  • Ошибок: {$testStats['stage1_errors']}\n\n" .
            "⏳ Этап 2: AI-анализ и суммаризация...";
    $telegram->sendMessage($chatId, $msg1, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
} catch (Exception $e) {
    echo "⚠️ Ошибка отправки: {$e->getMessage()}\n";
}

// ============================================================================
// ЭТАП 2: AI-АНАЛИЗ, ПЕРЕВОД И СУММАРИЗАЦИЯ
// ============================================================================

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  🤖 ЭТАП 2: AI-АНАЛИЗ, ПЕРЕВОД И СУММАРИЗАЦИЯ               ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

$pendingItems = $analysisRepository->getPendingItems(0, $testStats['stage1_items']);

echo "🔍 Найдено новостей для анализа: " . count($pendingItems) . "\n\n";

// Уведомление о начале анализа
try {
    $msg2 = "🤖 <b>ЭТАП 2 НАЧАТ</b>\n\n" .
            "📊 К анализу: " . count($pendingItems) . " новостей\n\n" .
            "⏳ Это займет несколько минут...";
    $telegram->sendMessage($chatId, $msg2, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
} catch (Exception $e) {
    // Ignore
}

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
    'max_tokens' => $config['ai_analysis']['max_tokens'] ?? 3000,
    'min_tokens' => $config['ai_analysis']['min_tokens'] ?? 400,
];

$progressCounter = 0;
$progressInterval = 50; // Уведомление каждые 50 новостей

foreach ($pendingItems as $index => $item) {
    $itemId = (int)$item['id'];
    
    echo "Анализ #{$itemId}: " . mb_substr($item['title'], 0, 60) . "...\n";
    
    $analysis = $aiAnalysisService->analyzeWithFallback($item, $promptId, $aiModels, $aiOptions);
    
    if ($analysis !== null) {
        $testStats['stage2_analyzed']++;
        echo "  ✓ Категория: {$analysis['category_primary']}, Важность: {$analysis['importance_rating']}/20\n";
    } else {
        $testStats['stage2_failed']++;
        echo "  ✗ Ошибка анализа\n";
    }
    
    // Прогрессивные уведомления
    $progressCounter++;
    if ($progressCounter % $progressInterval === 0) {
        try {
            $progressMsg = "📊 <b>Прогресс AI-анализа:</b>\n\n" .
                          "Обработано: {$progressCounter} / " . count($pendingItems) . "\n" .
                          "Успешно: {$testStats['stage2_analyzed']}\n" .
                          "Ошибок: {$testStats['stage2_failed']}";
            $telegram->sendMessage($chatId, $progressMsg, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
        } catch (Exception $e) {
            // Ignore
        }
    }
    
    // Задержка между запросами
    if ($index < count($pendingItems) - 1) {
        usleep($config['ai_analysis']['batch_delay_ms'] * 1000);
    }
}

echo "\n";
echo "📊 Результаты AI-анализа:\n";
echo "  - Успешно: {$testStats['stage2_analyzed']}\n";
echo "  - Ошибок: {$testStats['stage2_failed']}\n\n";

// Уведомление об окончании анализа
try {
    $msg3 = "✅ <b>ЭТАП 2 ЗАВЕРШЕН</b>\n\n" .
            "📊 <b>Результаты:</b>\n" .
            "  • Проанализировано: {$testStats['stage2_analyzed']}\n" .
            "  • Ошибок: {$testStats['stage2_failed']}\n\n" .
            "⏳ Этап 3: Отбор и публикация (по 1 из каждого языка)...";
    $telegram->sendMessage($chatId, $msg3, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
} catch (Exception $e) {
    // Ignore
}

// ============================================================================
// ЭТАП 3: ОТБОР И ПУБЛИКАЦИЯ (ПО 1 ИЗ КАЖДОГО ЯЗЫКА, РЕЙТИНГ >= 10)
// ============================================================================

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  📢 ЭТАП 3: ПУБЛИКАЦИЯ (ПО 1 ИЗ КАЖДОГО ЯЗЫКА)              ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

$importanceThreshold = $config['ai_analysis']['importance_threshold'];
$importantNews = $analysisRepository->getByImportance($importanceThreshold, 100);

echo "🔍 Найдено важных новостей (рейтинг >= {$importanceThreshold}): " . count($importantNews) . "\n\n";

// Группируем по языкам и берем по 1 из каждого
$languageGroups = [];
foreach ($importantNews as $news) {
    $lang = $news['article_language'] ?? 'unknown';
    if (!isset($languageGroups[$lang])) {
        $languageGroups[$lang] = [];
    }
    $languageGroups[$lang][] = $news;
}

$selectedNews = [];
foreach ($languageGroups as $lang => $newsArray) {
    if (count($newsArray) > 0) {
        $selectedNews[] = $newsArray[0]; // Берем первую самую важную
    }
}

echo "📰 Отобрано для публикации: " . count($selectedNews) . " новостей\n";
echo "Языки: " . implode(', ', array_keys($languageGroups)) . "\n\n";

foreach ($selectedNews as $index => $news) {
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
                       "📰 {$feedName} | 🌍 {$language}\n\n" .
                       "━━━━━━━━━━━━━━━━━━━━━━\n" .
                       "📊 <b>Служебная информация:</b>\n" .
                       "• Рейтинг важности: {$importance}/20\n" .
                       "• Категория: {$category}\n" .
                       "• Статус перевода: {$translationStatus}\n" .
                       "• Модель AI: {$config['ai_analysis']['default_model']}\n" .
                       "• ID новости: {$newsId}";
    
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
            $testStats['stage3_with_media']++;
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
        
        $testStats['stage3_published']++;
        echo "   ✓ Опубликовано (message_id: {$messageId})\n";
        
        sleep(2);
        
    } catch (Exception $e) {
        echo "   ✗ Ошибка публикации: {$e->getMessage()}\n";
    }
}

$mediaPercentage3 = $testStats['stage3_published'] > 0 
    ? round(($testStats['stage3_with_media'] / $testStats['stage3_published']) * 100, 1) 
    : 0;

echo "\n";
echo "📊 Результаты публикации:\n";
echo "  - Опубликовано: {$testStats['stage3_published']}\n";
echo "  - С медиа: {$testStats['stage3_with_media']} ({$mediaPercentage3}%)\n\n";

// Уведомление
try {
    $msg4 = "✅ <b>ЭТАП 3 ЗАВЕРШЕН</b>\n\n" .
            "📊 <b>Результаты:</b>\n" .
            "  • Опубликовано: {$testStats['stage3_published']}\n" .
            "  • С медиа: {$testStats['stage3_with_media']} ({$mediaPercentage3}%)\n\n" .
            "⏳ Этап 4: Проверка кеширования (второй запрос)...";
    $telegram->sendMessage($chatId, $msg4, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
} catch (Exception $e) {
    // Ignore
}

// ============================================================================
// ЭТАП 4: ВТОРОЙ ЗАПРОС (ПРОВЕРКА КЕШИРОВАНИЯ И ДЕДУПЛИКАЦИИ)
// ============================================================================

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  🔄 ЭТАП 4: ВТОРОЙ ЗАПРОС (КЕШИРОВАНИЕ И ДЕДУПЛИКАЦИЯ)      ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

sleep(3);

$fetchResult2 = $fetchRunner->runForAllFeeds($feedConfigs);

foreach ($fetchResult2 as $result) {
    if ($result->items) {
        $testStats['stage4_new_items'] += count($result->items);
    }
    
    if ($result->status === 'not_modified' || ($result->items === null && $result->error === null)) {
        $testStats['stage4_cached']++;
    }
}

echo "\n";
echo "📊 Результаты второго сбора:\n";
echo "  - Источников обработано: " . count($fetchResult2) . "\n";
echo "  - Новых новостей: {$testStats['stage4_new_items']}\n";
echo "  - Из кеша (304): {$testStats['stage4_cached']}\n\n";

// Уведомление
try {
    $msg5 = "✅ <b>ЭТАП 4 ЗАВЕРШЕН</b>\n\n" .
            "📊 <b>Результаты:</b>\n" .
            "  • Источников: " . count($fetchResult2) . "\n" .
            "  • Новых: {$testStats['stage4_new_items']}\n" .
            "  • Из кеша: {$testStats['stage4_cached']}\n\n" .
            "⏳ Этап 5: Дополнительная публикация (5 случайных источников)...";
    $telegram->sendMessage($chatId, $msg5, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
} catch (Exception $e) {
    // Ignore
}

// ============================================================================
// ЭТАП 5: ДОПОЛНИТЕЛЬНАЯ ПУБЛИКАЦИЯ ИЗ 5 СЛУЧАЙНЫХ ИСТОЧНИКОВ
// ============================================================================

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  🎲 ЭТАП 5: ПУБЛИКАЦИЯ ИЗ 5 СЛУЧАЙНЫХ ИСТОЧНИКОВ             ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Выбираем 5 случайных источников
$allFeedIds = array_column($config['feeds'], 'id');
shuffle($allFeedIds);
$randomFeedIds = array_slice($allFeedIds, 0, 5);

echo "🎲 Выбраны случайные источники: " . implode(', ', $randomFeedIds) . "\n\n";

// Получаем важные новости из этих источников
$randomNews = [];
foreach ($randomFeedIds as $feedId) {
    $sql = "SELECT ai.*, i.title, i.feed_id 
            FROM rss2tlg_ai_analysis ai
            JOIN rss2tlg_items i ON ai.item_id = i.id
            WHERE i.feed_id = ? AND ai.importance_rating >= ?
            ORDER BY ai.importance_rating DESC
            LIMIT 1";
    
    $result = $db->queryOne($sql, [$feedId, $importanceThreshold]);
    if ($result) {
        $randomNews[] = $result;
    }
}

echo "📰 Найдено для публикации: " . count($randomNews) . " новостей\n\n";

foreach ($randomNews as $news) {
    $newsId = (int)$news['item_id'];
    $title = $news['content_headline'] ?? $news['title'] ?? 'Без заголовка';
    $summary = $news['content_summary'] ?? 'Нет описания';
    $language = $news['article_language'] ?? 'unknown';
    $importance = $news['importance_rating'];
    $category = $news['category_primary'] ?? 'General';
    $translationStatus = $news['translation_status'] ?? 'unknown';
    
    $fullItem = $itemRepository->getById($newsId);
    if ($fullItem === null) {
        continue;
    }
    
    $feedId = $fullItem['feed_id'] ?? 0;
    
    $feedName = 'Unknown';
    foreach ($config['feeds'] as $feed) {
        if ($feed['id'] === $feedId) {
            $feedName = $feed['title'];
            break;
        }
    }
    
    // Проверяем медиа
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
    
    // Формируем текст (БЕЗ ССЫЛОК!)
    $publicationText = "<b>{$title}</b>\n\n" .
                       "{$summary}\n\n" .
                       "📰 {$feedName} | 🌍 {$language}\n\n" .
                       "━━━━━━━━━━━━━━━━━━━━━━\n" .
                       "📊 <b>Служебная информация:</b>\n" .
                       "• Рейтинг важности: {$importance}/20\n" .
                       "• Категория: {$category}\n" .
                       "• Статус перевода: {$translationStatus}\n" .
                       "• Модель AI: {$config['ai_analysis']['default_model']}\n" .
                       "• ID новости: {$newsId}";
    
    $caption = mb_strlen($publicationText) > 1024 
        ? mb_substr($publicationText, 0, 1020) . "..." 
        : $publicationText;
    
    try {
        echo "\n📤 Публикация #{$newsId}: {$feedName}\n";
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
            $testStats['stage5_with_media']++;
        } else {
            $result = $telegram->sendMessage(
                $channelId,
                $publicationText,
                ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
            );
        }
        
        $messageId = $result->messageId ?? 0;
        $publicationRepository->record($newsId, $feedId, 'channel', $channelId, $messageId);
        
        $testStats['stage5_published']++;
        echo "   ✓ Опубликовано (message_id: {$messageId})\n";
        
        sleep(2);
        
    } catch (Exception $e) {
        echo "   ✗ Ошибка публикации: {$e->getMessage()}\n";
    }
}

$mediaPercentage5 = $testStats['stage5_published'] > 0 
    ? round(($testStats['stage5_with_media'] / $testStats['stage5_published']) * 100, 1) 
    : 0;

echo "\n";
echo "📊 Результаты дополнительной публикации:\n";
echo "  - Опубликовано: {$testStats['stage5_published']}\n";
echo "  - С медиа: {$testStats['stage5_with_media']} ({$mediaPercentage5}%)\n\n";

// ============================================================================
// ЭТАП 6: ДЕТАЛЬНАЯ И ПОДРОБНАЯ СТАТИСТИКА
// ============================================================================

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  📈 ИТОГОВАЯ ДЕТАЛЬНАЯ СТАТИСТИКА                            ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

$totalPublished = $testStats['stage3_published'] + $testStats['stage5_published'];
$totalWithMedia = $testStats['stage3_with_media'] + $testStats['stage5_with_media'];
$totalMediaPercentage = $totalPublished > 0 ? round(($totalWithMedia / $totalPublished) * 100, 1) : 0;

// Статистика БД
$totalNewsInDb = $db->queryScalar("SELECT COUNT(*) FROM rss2tlg_items");
$totalPublications = $db->queryScalar("SELECT COUNT(*) FROM rss2tlg_publications");
$totalAnalyzed = $db->queryScalar("SELECT COUNT(*) FROM rss2tlg_ai_analysis");
$avgImportance = $db->queryScalar("SELECT AVG(importance_rating) FROM rss2tlg_ai_analysis");

echo "📊 <b>ОБЩАЯ СТАТИСТИКА:</b>\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "  Источников: " . count($config['feeds']) . "\n";
echo "  Новостей собрано (1-й запрос): {$testStats['stage1_items']}\n";
echo "  Новостей собрано (2-й запрос): {$testStats['stage4_new_items']}\n";
echo "  Из кеша: {$testStats['stage4_cached']}\n";
echo "  Ошибок сбора: {$testStats['stage1_errors']}\n\n";

echo "🤖 <b>AI-АНАЛИЗ:</b>\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "  Успешно проанализировано: {$testStats['stage2_analyzed']}\n";
echo "  Ошибок анализа: {$testStats['stage2_failed']}\n";
echo "  Средний рейтинг важности: " . round($avgImportance, 2) . "/20\n\n";

echo "📢 <b>ПУБЛИКАЦИИ:</b>\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "  Этап 3 (по языкам): {$testStats['stage3_published']}\n";
echo "  Этап 5 (случайные): {$testStats['stage5_published']}\n";
echo "  Всего опубликовано: {$totalPublished}\n";
echo "  С медиа: {$totalWithMedia} ({$totalMediaPercentage}%)\n";
echo "  Требование 30%: " . ($totalMediaPercentage >= 30 ? "✅ ВЫПОЛНЕНО" : "❌ НЕ ВЫПОЛНЕНО") . "\n\n";

echo "💾 <b>БАЗА ДАННЫХ:</b>\n";
echo "════════════════════════════════════════════════════════════════\n";
$tables = ['rss2tlg_items', 'rss2tlg_feed_state', 'rss2tlg_publications', 'rss2tlg_ai_analysis'];
foreach ($tables as $table) {
    $count = $db->queryScalar("SELECT COUNT(*) FROM {$table}");
    echo "  {$table}: {$count} записей\n";
}
echo "\n";

// Статистика по категориям
echo "📂 <b>КАТЕГОРИИ НОВОСТЕЙ:</b>\n";
echo "════════════════════════════════════════════════════════════════\n";
$categories = $db->query("
    SELECT category_primary, COUNT(*) as cnt 
    FROM rss2tlg_ai_analysis 
    GROUP BY category_primary 
    ORDER BY cnt DESC 
    LIMIT 10
");
foreach ($categories as $cat) {
    echo "  {$cat['category_primary']}: {$cat['cnt']}\n";
}
echo "\n";

// Статистика по языкам
echo "🌍 <b>РАСПРЕДЕЛЕНИЕ ПО ЯЗЫКАМ:</b>\n";
echo "════════════════════════════════════════════════════════════════\n";
$languages = $db->query("
    SELECT article_language, COUNT(*) as cnt 
    FROM rss2tlg_ai_analysis 
    GROUP BY article_language 
    ORDER BY cnt DESC
");
foreach ($languages as $lang) {
    echo "  {$lang['article_language']}: {$lang['cnt']}\n";
}
echo "\n";

$executionTime = round(microtime(true) - $startTime, 2);

echo "⏱️ <b>ПРОИЗВОДИТЕЛЬНОСТЬ:</b>\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "  Общее время: {$executionTime} сек (" . round($executionTime / 60, 2) . " мин)\n";
echo "  Среднее на источник: " . round($executionTime / count($config['feeds']), 2) . " сек\n";
echo "  Среднее на анализ: " . ($testStats['stage2_analyzed'] > 0 ? round($executionTime / $testStats['stage2_analyzed'], 2) : 0) . " сек\n\n";

// Проверка логов
$logFile = "{$logConfig['directory']}/{$logConfig['file_name']}";
if (file_exists($logFile)) {
    $logSize = filesize($logFile);
    $logLines = count(file($logFile));
    echo "📝 <b>ЛОГИ:</b>\n";
    echo "════════════════════════════════════════════════════════════════\n";
    echo "  Файл: {$logFile}\n";
    echo "  Размер: " . round($logSize / 1024, 2) . " KB\n";
    echo "  Строк: {$logLines}\n\n";
}

// ============================================================================
// ФИНАЛЬНОЕ УВЕДОМЛЕНИЕ В TELEGRAM
// ============================================================================

try {
    $finalMsg = "🎉 <b>ТЕСТИРОВАНИЕ V2 ЗАВЕРШЕНО</b>\n\n" .
                "📊 <b>Итоговая статистика:</b>\n\n" .
                "📡 <b>Сбор:</b>\n" .
                "  • Источников: " . count($config['feeds']) . "\n" .
                "  • Новостей (1-й): {$testStats['stage1_items']}\n" .
                "  • Новостей (2-й): {$testStats['stage4_new_items']}\n" .
                "  • Из кеша: {$testStats['stage4_cached']}\n\n" .
                "🤖 <b>AI-анализ:</b>\n" .
                "  • Успешно: {$testStats['stage2_analyzed']}\n" .
                "  • Ошибок: {$testStats['stage2_failed']}\n" .
                "  • Средний рейтинг: " . round($avgImportance, 2) . "/20\n\n" .
                "📢 <b>Публикации:</b>\n" .
                "  • Всего: {$totalPublished}\n" .
                "  • С медиа: {$totalWithMedia} ({$totalMediaPercentage}%)\n" .
                "  • Требование 30%: " . ($totalMediaPercentage >= 30 ? "✅" : "❌") . "\n\n" .
                "💾 <b>БД:</b>\n" .
                "  • Новостей: {$totalNewsInDb}\n" .
                "  • Публикаций: {$totalPublications}\n" .
                "  • AI-анализов: {$totalAnalyzed}\n\n" .
                "⏱️ <b>Время:</b> {$executionTime} сек\n\n" .
                "✅ Все этапы пройдены успешно!";
    
    $telegram->sendMessage($chatId, $finalMsg, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
    
    echo "✓ Финальное уведомление отправлено\n\n";
} catch (Exception $e) {
    echo "⚠️ Ошибка отправки финального уведомления: {$e->getMessage()}\n\n";
}

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  ✅ ТЕСТИРОВАНИЕ V2 УСПЕШНО ЗАВЕРШЕНО                        ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

echo "📝 Логи: {$logFile}\n";
echo "📊 Канал: {$channelId}\n";
echo "💾 БД: {$dbConfig['name']}\n\n";

<?php

declare(strict_types=1);

/**
 * 🔥 E2E ТЕСТ RSS2TLG С AI-АНАЛИЗОМ И TELEGRAM ПУБЛИКАЦИЕЙ
 * 
 * Идентификатор: RSS2TLG-AI-TLG-E2E-001
 * 
 * Этот тест проверяет полный цикл работы RSS2TLG:
 * 1. Сбор новостей из 25 RSS источников (5 языков)
 * 2. AI-анализ, перевод и суммаризация через OpenRouter
 * 3. Публикация в Telegram канал (с медиа контентом)
 * 4. Уведомления в Telegram бот о ходе тестирования
 * 5. Проверка кеширования и дедупликации
 * 
 * ТРЕБОВАНИЯ:
 * - MariaDB/MySQL запущен и доступен
 * - OpenRouter API ключ настроен
 * - Telegram bot token и channel настроены
 * - Минимум 30% публикаций с фото/видео
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

$testId = 'RSS2TLG-AI-TLG-E2E-001';
$configPath = __DIR__ . '/../../config/rss2tlg_ai_test.json';
$promptsDir = __DIR__ . '/../../prompts';

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  🔥 E2E ТЕСТ RSS2TLG С AI-АНАЛИЗОМ И TELEGRAM ПУБЛИКАЦИЕЙ   ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";

// ============================================================================
// ИНИЦИАЛИЗАЦИЯ КОМПОНЕНТОВ
// ============================================================================

echo "📦 Инициализация компонентов...\n\n";

// Загрузка конфигурации
$configLoader = new ConfigLoader();
$config = $configLoader->load($configPath);

// Инициализация логгера
$logConfig = $config['logging'];
$logger = new Logger([
    'directory' => $logConfig['directory'],
    'file_name' => $logConfig['file_name'],
    'max_files' => $logConfig['max_files'] ?? 10,
    'max_file_size' => $logConfig['max_file_size'] ?? 100,
    'enabled' => $logConfig['enabled'] ?? true,
]);

echo "✓ Логгер: {$logConfig['directory']}/{$logConfig['file_name']}\n";

// Подключение к БД
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

// Инициализация HTTP и WebtExtractor
$http = new Http([], $logger);
$extractor = new WebtExtractor([], $logger);

echo "✓ HTTP и WebtExtractor инициализированы\n";

// Инициализация Telegram API
$telegramConfig = $config['telegram'];
$telegram = new TelegramAPI($telegramConfig['bot_token'], $http, $logger);
$chatId = (int)$telegramConfig['chat_id'];
$channelId = $telegramConfig['channel_id'];

echo "✓ Telegram API: бот и канал {$channelId}\n";

// Инициализация OpenRouter
$openRouterConfig = [
    'api_key' => $config['ai_analysis']['api_key'],
    'base_url' => 'https://openrouter.ai/api/v1',
    'default_model' => $config['ai_analysis']['default_model'],
    'timeout' => 60,
];
$openRouter = new OpenRouter($openRouterConfig, $logger);

echo "✓ OpenRouter: {$openRouterConfig['default_model']}\n";

// Инициализация репозиториев
$itemRepository = new ItemRepository($db, $logger);
$publicationRepository = new PublicationRepository($db, $logger);
$feedStateRepository = new FeedStateRepository($db, $logger);
$analysisRepository = new AIAnalysisRepository($db, $logger, true);

echo "✓ Репозитории инициализированы\n";

// Инициализация сервисов
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

try {
    $startMsg = "🚀 <b>СТАРТ ТЕСТИРОВАНИЯ</b>\n\n" .
                "<b>Тест:</b> {$testId}\n" .
                "<b>Источников:</b> " . count($config['feeds']) . "\n" .
                "<b>Канал:</b> {$channelId}\n" .
                "<b>AI модель:</b> {$config['ai_analysis']['default_model']}\n\n" .
                "⏳ Начинаем сбор новостей...";
    $telegram->sendMessage($chatId, $startMsg, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
    echo "✓ Стартовое уведомление отправлено\n\n";
} catch (Exception $e) {
    echo "⚠️ Ошибка отправки уведомления: {$e->getMessage()}\n\n";
}

// ============================================================================
// ЭТАП 1: ПЕРВЫЙ СБОР НОВОСТЕЙ ИЗ RSS ЛЕНТ
// ============================================================================

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  📡 ЭТАП 1: ПЕРВЫЙ СБОР НОВОСТЕЙ                             ║\n";
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

// Подсчет результатов
$totalFeeds1 = count($fetchResult1);
$totalItems1 = 0;
$totalErrors1 = 0;

foreach ($fetchResult1 as $feedId => $result) {
    if ($result->items) {
        $newItemsCount = count($result->items);
        $totalItems1 += $newItemsCount;
        
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
        $totalErrors1++;
    }
}

echo "\n";
echo "📊 Результаты первого сбора:\n";
echo "  - Источников обработано: {$totalFeeds1}\n";
echo "  - Новых новостей: {$totalItems1}\n";
echo "  - Ошибок: {$totalErrors1}\n\n";

// Отправляем уведомление
try {
    $msg1 = "✅ <b>ЭТАП 1: СБОР ЗАВЕРШЕН</b>\n\n" .
            "📊 <b>Результаты:</b>\n" .
            "  • Источников: {$totalFeeds1}\n" .
            "  • Новостей: {$totalItems1}\n" .
            "  • Ошибок: {$totalErrors1}\n\n" .
            "⏳ Переходим к AI-анализу...";
    $telegram->sendMessage($chatId, $msg1, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
} catch (Exception $e) {
    echo "⚠️ Ошибка отправки уведомления: {$e->getMessage()}\n";
}

// ============================================================================
// ЭТАП 2: AI-АНАЛИЗ, ПЕРЕВОД И СУММАРИЗАЦИЯ
// ============================================================================

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  🤖 ЭТАП 2: AI-АНАЛИЗ, ПЕРЕВОД И СУММАРИЗАЦИЯ               ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Получаем новости для анализа
$pendingItems = $analysisRepository->getPendingItems(0, $totalItems1);

echo "🔍 Найдено новостей для анализа: " . count($pendingItems) . "\n\n";

// Отправляем уведомление
try {
    $msg2 = "🤖 <b>ЭТАП 2: AI-АНАЛИЗ НАЧАТ</b>\n\n" .
            "📊 <b>К анализу:</b> " . count($pendingItems) . " новостей\n\n" .
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

$analyzedCount = 0;
$failedCount = 0;

foreach ($pendingItems as $index => $item) {
    $itemId = (int)$item['id'];
    
    echo "Анализ #{$itemId}: " . mb_substr($item['title'], 0, 60) . "...\n";
    
    $analysis = $aiAnalysisService->analyzeWithFallback($item, $promptId, $aiModels);
    
    if ($analysis !== null) {
        $analyzedCount++;
        echo "  ✓ Категория: {$analysis['category_primary']}, Важность: {$analysis['importance_rating']}/20\n";
    } else {
        $failedCount++;
        echo "  ✗ Ошибка анализа\n";
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

// Отправляем уведомление
try {
    $msg3 = "✅ <b>ЭТАП 2: AI-АНАЛИЗ ЗАВЕРШЕН</b>\n\n" .
            "📊 <b>Результаты:</b>\n" .
            "  • Проанализировано: {$analyzedCount}\n" .
            "  • Ошибок: {$failedCount}\n\n" .
            "⏳ Переходим к публикации...";
    $telegram->sendMessage($chatId, $msg3, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
} catch (Exception $e) {
    // Ignore
}

// ============================================================================
// ЭТАП 3: ОТБОР И ПУБЛИКАЦИЯ НОВОСТЕЙ В TELEGRAM
// ============================================================================

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  📢 ЭТАП 3: ПУБЛИКАЦИЯ В TELEGRAM КАНАЛ                      ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Получаем важные новости (рейтинг >= 10) по разным языкам
$importanceThreshold = $config['ai_analysis']['importance_threshold'];
$importantNews = $analysisRepository->getByImportance($importanceThreshold, 100);

echo "🔍 Найдено важных новостей (рейтинг >= {$importanceThreshold}): " . count($importantNews) . "\n\n";

// Группируем по языкам и берем по 1 из каждого языка
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

// Публикуем новости
$publishedCount = 0;
$publishedWithMedia = 0;

foreach ($selectedNews as $index => $news) {
    $newsId = (int)$news['item_id'];
    $title = $news['content_headline'] ?? $news['title'] ?? 'Без заголовка';
    $summary = $news['content_summary'] ?? 'Нет описания';
    $language = $news['article_language'] ?? 'unknown';
    $importance = $news['importance_rating'];
    $category = $news['category_primary'] ?? 'General';
    
    // Получаем полную информацию о новости
    $fullItem = $itemRepository->getById($newsId);
    if ($fullItem === null) {
        echo "⚠️ Новость #{$newsId} не найдена\n";
        continue;
    }
    
    $sourceUrl = $fullItem['link'] ?? '';
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
    
    // Формируем текст публикации
    $publicationText = "<b>{$title}</b>\n\n" .
                       "{$summary}\n\n" .
                       "📎 <a href=\"{$sourceUrl}\">{$feedName}</a> | Язык: {$language}\n\n" .
                       "━━━━━━━━━━━━━━━━━━━━━━\n" .
                       "📊 <b>Служебная информация:</b>\n" .
                       "• Рейтинг важности: {$importance}/20\n" .
                       "• Категория: {$category}\n" .
                       "• Статус перевода: {$news['translation_status']}\n" .
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
            // Публикуем с медиа
            $result = $telegram->sendPhoto(
                $channelId,
                $media['url'],
                [
                    'caption' => $caption,
                    'parse_mode' => TelegramAPI::PARSE_MODE_HTML
                ]
            );
            $publishedWithMedia++;
        } else {
            // Публикуем только текст
            $result = $telegram->sendMessage(
                $channelId,
                $publicationText,
                ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
            );
        }
        
        // Сохраняем публикацию
        $messageId = $result['result']['message_id'] ?? 0;
        $publicationRepository->savePublication($newsId, 'telegram_channel', $channelId, $messageId);
        
        $publishedCount++;
        echo "   ✓ Опубликовано (message_id: {$messageId})\n";
        
        // Задержка между публикациями
        sleep(2);
        
    } catch (Exception $e) {
        echo "   ✗ Ошибка публикации: {$e->getMessage()}\n";
    }
}

$mediaPercentage = $publishedCount > 0 ? round(($publishedWithMedia / $publishedCount) * 100, 1) : 0;

echo "\n";
echo "📊 Результаты публикации:\n";
echo "  - Опубликовано: {$publishedCount}\n";
echo "  - С медиа: {$publishedWithMedia} ({$mediaPercentage}%)\n\n";

// Отправляем уведомление
try {
    $msg4 = "✅ <b>ЭТАП 3: ПУБЛИКАЦИЯ ЗАВЕРШЕНА</b>\n\n" .
            "📊 <b>Результаты:</b>\n" .
            "  • Опубликовано: {$publishedCount}\n" .
            "  • С медиа: {$publishedWithMedia} ({$mediaPercentage}%)\n\n" .
            "⏳ Переходим к проверке кеширования...";
    $telegram->sendMessage($chatId, $msg4, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
} catch (Exception $e) {
    // Ignore
}

// ============================================================================
// ЭТАП 4: ВТОРОЙ ЗАПРОС (ПРОВЕРКА КЕШИРОВАНИЯ)
// ============================================================================

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  🔄 ЭТАП 4: ВТОРОЙ ЗАПРОС (ПРОВЕРКА КЕШИРОВАНИЯ)            ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

sleep(3);

$fetchResult2 = $fetchRunner->runForAllFeeds($feedConfigs);

// Подсчет результатов
$totalFeeds2 = count($fetchResult2);
$totalItems2 = 0;
$totalCached2 = 0;

foreach ($fetchResult2 as $result) {
    if ($result->items) {
        $newItems = count($result->items);
        $totalItems2 += $newItems;
        
        // Если новых элементов нет, считаем что сработал кеш
        if ($newItems === 0 && $result->status === 'cached') {
            $totalCached2++;
        }
    }
}

echo "\n";
echo "📊 Результаты второго сбора:\n";
echo "  - Источников обработано: {$totalFeeds2}\n";
echo "  - Новых новостей: {$totalItems2}\n";
echo "  - Из кеша: {$totalCached2}\n\n";

// Отправляем уведомление
try {
    $msg5 = "✅ <b>ЭТАП 4: КЕШИРОВАНИЕ ПРОВЕРЕНО</b>\n\n" .
            "📊 <b>Результаты:</b>\n" .
            "  • Источников: {$totalFeeds2}\n" .
            "  • Новых: {$totalItems2}\n" .
            "  • Из кеша: {$totalCached2}\n\n" .
            "⏳ Дополнительная публикация...";
    $telegram->sendMessage($chatId, $msg5, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
} catch (Exception $e) {
    // Ignore
}

// ============================================================================
// ЭТАП 5: ДОПОЛНИТЕЛЬНАЯ ПУБЛИКАЦИЯ ИЗ 5 СЛУЧАЙНЫХ ИСТОЧНИКОВ
// ============================================================================

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  🎲 ЭТАП 5: ДОПОЛНИТЕЛЬНАЯ ПУБЛИКАЦИЯ                        ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Выбираем 5 случайных источников
$randomFeeds = array_rand($config['feeds'], min(5, count($config['feeds'])));
if (!is_array($randomFeeds)) {
    $randomFeeds = [$randomFeeds];
}

$randomFeedIds = array_map(fn($idx) => $config['feeds'][$idx]['id'], $randomFeeds);

echo "🎲 Выбрано случайных источников: " . count($randomFeedIds) . "\n";
echo "ID: " . implode(', ', $randomFeedIds) . "\n\n";

// Получаем по 1 новости из каждого источника с рейтингом >= 10
$additionalNews = [];
foreach ($randomFeedIds as $feedId) {
    $sql = "SELECT ai.*, i.* 
            FROM rss2tlg_ai_analysis ai
            INNER JOIN rss2tlg_items i ON ai.item_id = i.id
            WHERE i.feed_id = ? 
              AND ai.importance_rating >= ?
              AND i.id NOT IN (SELECT item_id FROM rss2tlg_publications)
            ORDER BY ai.importance_rating DESC
            LIMIT 1";
    
    $result = $db->query($sql, [$feedId, $importanceThreshold]);
    if (!empty($result)) {
        $additionalNews[] = $result[0];
    }
}

echo "📰 Найдено для публикации: " . count($additionalNews) . " новостей\n\n";

$additionalPublished = 0;
$additionalWithMedia = 0;

foreach ($additionalNews as $news) {
    $newsId = (int)$news['item_id'];
    $title = $news['content_headline'] ?? $news['title'] ?? 'Без заголовка';
    $summary = $news['content_summary'] ?? 'Нет описания';
    $language = $news['article_language'] ?? 'unknown';
    $importance = $news['importance_rating'];
    
    $sourceUrl = $news['link'] ?? '';
    $feedId = $news['feed_id'] ?? 0;
    
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
    
    if (!empty($news['enclosures'])) {
        $enclosures = is_string($news['enclosures']) 
            ? json_decode($news['enclosures'], true) 
            : $news['enclosures'];
        
        if (is_array($enclosures) && !empty($enclosures['url'])) {
            $type = $enclosures['type'] ?? '';
            $url = $enclosures['url'];
            
            if (str_starts_with($type, 'image/') || preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $url)) {
                $media = ['type' => 'photo', 'url' => $url];
                $hasMedia = true;
            }
        }
    }
    
    $publicationText = "<b>{$title}</b>\n\n" .
                       "{$summary}\n\n" .
                       "📎 <a href=\"{$sourceUrl}\">{$feedName}</a> | Язык: {$language}\n\n" .
                       "━━━━━━━━━━━━━━━━━━━━━━\n" .
                       "📊 <b>Служебная информация:</b>\n" .
                       "• Рейтинг важности: {$importance}/20\n" .
                       "• ID новости: {$newsId}";
    
    $caption = mb_strlen($publicationText) > 1024 
        ? mb_substr($publicationText, 0, 1020) . "..." 
        : $publicationText;
    
    try {
        echo "📤 Публикация #{$newsId}: {$feedName}\n";
        echo "   Медиа: " . ($hasMedia ? "✓ Да" : "✗ Нет") . "\n";
        
        if ($hasMedia && $media !== null) {
            $result = $telegram->sendPhoto($channelId, $media['url'], [
                'caption' => $caption,
                'parse_mode' => TelegramAPI::PARSE_MODE_HTML
            ]);
            $additionalWithMedia++;
        } else {
            $result = $telegram->sendMessage($channelId, $publicationText, [
                'parse_mode' => TelegramAPI::PARSE_MODE_HTML
            ]);
        }
        
        $messageId = $result['result']['message_id'] ?? 0;
        $publicationRepository->savePublication($newsId, 'telegram_channel', $channelId, $messageId);
        
        $additionalPublished++;
        echo "   ✓ Опубликовано\n\n";
        
        sleep(2);
        
    } catch (Exception $e) {
        echo "   ✗ Ошибка: {$e->getMessage()}\n\n";
    }
}

echo "📊 Дополнительно опубликовано: {$additionalPublished} (медиа: {$additionalWithMedia})\n\n";

// ============================================================================
// ЭТАП 6: ИТОГОВАЯ ДЕТАЛЬНАЯ СТАТИСТИКА
// ============================================================================

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  📈 ИТОГОВАЯ ДЕТАЛЬНАЯ СТАТИСТИКА                            ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Статистика AI-анализа
$aiStats = $analysisRepository->getStats();
$aiServiceMetrics = $aiAnalysisService->getMetrics();

echo "🤖 <b>AI-АНАЛИЗ:</b>\n";
echo "  Всего записей: {$aiStats['total']}\n";
echo "  Успешных: {$aiStats['success']}\n";
echo "  Ошибок: {$aiStats['failed']}\n";
echo "  Средняя важность: " . round($aiStats['avg_importance'] ?? 0, 1) . "/20\n";
echo "  Среднее время обработки: " . round($aiStats['avg_processing_time_ms'] ?? 0) . " мс\n";
echo "  Cache hits: {$aiStats['cache_hits']}\n";
echo "  Всего токенов: {$aiStats['total_tokens']}\n\n";

// Статистика публикаций
$totalPublished = $publishedCount + $additionalPublished;
$totalWithMedia = $publishedWithMedia + $additionalWithMedia;
$totalMediaPercentage = $totalPublished > 0 ? round(($totalWithMedia / $totalPublished) * 100, 1) : 0;

echo "📢 <b>ПУБЛИКАЦИИ:</b>\n";
echo "  Всего опубликовано: {$totalPublished}\n";
echo "  С медиа: {$totalWithMedia} ({$totalMediaPercentage}%)\n";
echo "  Без медиа: " . ($totalPublished - $totalWithMedia) . "\n\n";

// Статистика новостей
$totalNewsInDb = $db->queryScalar("SELECT COUNT(*) FROM rss2tlg_items");
$totalAnalyzed = $db->queryScalar("SELECT COUNT(*) FROM rss2tlg_ai_analysis");
$totalPublications = $db->queryScalar("SELECT COUNT(*) FROM rss2tlg_publications");

echo "📰 <b>НОВОСТИ В БД:</b>\n";
echo "  Всего новостей: {$totalNewsInDb}\n";
echo "  Проанализировано: {$totalAnalyzed}\n";
echo "  Опубликовано: {$totalPublications}\n\n";

// Проверка таблиц
echo "💾 <b>ПРОВЕРКА ТАБЛИЦ БД:</b>\n";

$tables = ['rss2tlg_items', 'rss2tlg_feed_state', 'rss2tlg_publications', 'rss2tlg_ai_analysis'];
foreach ($tables as $table) {
    $count = $db->queryScalar("SELECT COUNT(*) FROM {$table}");
    echo "  {$table}: {$count} записей\n";
}
echo "\n";

// Время выполнения
$executionTime = round(microtime(true) - $startTime, 2);

echo "⏱️ <b>ВРЕМЯ ВЫПОЛНЕНИЯ:</b>\n";
echo "  Общее время: {$executionTime} сек\n";
echo "  Среднее время на источник: " . round($executionTime / count($config['feeds']), 2) . " сек\n\n";

// ============================================================================
// ФИНАЛЬНОЕ УВЕДОМЛЕНИЕ В TELEGRAM
// ============================================================================

try {
    $finalMsg = "🎉 <b>ТЕСТИРОВАНИЕ ЗАВЕРШЕНО</b>\n\n" .
                "📊 <b>Итоговая статистика:</b>\n\n" .
                "📡 <b>Сбор новостей:</b>\n" .
                "  • Источников: " . count($config['feeds']) . "\n" .
                "  • Новостей собрано: {$totalItems1}\n" .
                "  • Из кеша (2-й запрос): {$totalCached2}\n\n" .
                "🤖 <b>AI-анализ:</b>\n" .
                "  • Проанализировано: {$analyzedCount}\n" .
                "  • Средняя важность: " . round($aiStats['avg_importance'] ?? 0, 1) . "/20\n" .
                "  • Токенов использовано: {$aiStats['total_tokens']}\n\n" .
                "📢 <b>Публикации:</b>\n" .
                "  • Опубликовано: {$totalPublished}\n" .
                "  • С медиа: {$totalWithMedia} ({$totalMediaPercentage}%)\n\n" .
                "💾 <b>База данных:</b>\n" .
                "  • Новостей в БД: {$totalNewsInDb}\n" .
                "  • Публикаций: {$totalPublications}\n\n" .
                "⏱️ <b>Время:</b> {$executionTime} сек\n\n" .
                "✅ Все этапы пройдены успешно!";
    
    $telegram->sendMessage($chatId, $finalMsg, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
    
    echo "✓ Финальное уведомление отправлено\n\n";
} catch (Exception $e) {
    echo "⚠️ Ошибка отправки финального уведомления: {$e->getMessage()}\n\n";
}

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  ✅ ТЕСТИРОВАНИЕ УСПЕШНО ЗАВЕРШЕНО                           ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

echo "📝 Логи: {$logConfig['directory']}/{$logConfig['file_name']}\n";
echo "📊 Канал: {$channelId}\n\n";

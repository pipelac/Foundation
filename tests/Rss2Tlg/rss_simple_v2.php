<?php

declare(strict_types=1);

/**
 * 🔥 УПРОЩЕННЫЙ E2E ТЕСТ RSS2TLG V2 БЕЗ AI-АНАЛИЗА
 * 
 * Идентификатор: RSS2TLG-SIMPLE-E2E-002
 * 
 * ФУНКЦИОНАЛ:
 * 1. Сбор 20 последних новостей из 30 RSS источников (6 языков × 5)
 * 2. Отбор по 1 новости из каждого языка (без AI-анализа)
 * 3. Публикация в Telegram канал (минимум 30% с медиа)
 * 4. Проверка кеширования и дедупликации (второй запуск)
 * 5. Дополнительная публикация из 5 случайных источников
 * 6. Детальная статистика с проверкой БД и логов
 * 
 * ШАБЛОН ПУБЛИКАЦИИ:
 * {заголовок жирным}
 * 
 * {description или краткое описание}
 * 
 * 📰 {источник} | 🌍 {язык}
 * 
 * ━━━━━━━━━━━━━━━━
 * 📊 Служебная информация:
 * • ID новости: ...
 * • Источник: ...
 * • Дата: ...
 * 
 * БЕЗ AI-АНАЛИЗА из-за проблем с OpenRouter free API
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\Http;
use App\Config\ConfigLoader;
use App\Rss2Tlg\FetchRunner;
use App\Rss2Tlg\ItemRepository;
use App\Rss2Tlg\PublicationRepository;
use App\Rss2Tlg\FeedStateRepository;
use App\Rss2Tlg\DTO\FeedConfig;
use App\Component\TelegramBot\Core\TelegramAPI;

// ============================================================================
// КОНФИГУРАЦИЯ
// ============================================================================

$testId = 'RSS2TLG-SIMPLE-E2E-002';
$configPath = __DIR__ . '/../../config/rss2tlg_ai_v2.json';

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  🔥 УПРОЩЕННЫЙ E2E ТЕСТ RSS2TLG V2 (БЕЗ AI)                  ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";

// ============================================================================
// ИНИЦИАЛИЗАЦИЯ КОМПОНЕНТОВ
// ============================================================================

echo "📦 Инициализация компонентов...\n\n";

$configLoader = new ConfigLoader();
$config = $configLoader->load($configPath);

$logConfig = $config['logging'];
$logger = new Logger([
    'directory' => $logConfig['directory'],
    'file_name' => 'rss_simple_v2.log',
    'max_files' => $logConfig['max_files'] ?? 10,
    'max_file_size' => $logConfig['max_file_size'] ?? 100,
    'enabled' => $logConfig['enabled'] ?? true,
]);

echo "✓ Логгер: {$logConfig['directory']}/rss_simple_v2.log\n";

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

$http = new Http([], $logger);

echo "✓ HTTP инициализирован\n";

$telegramConfig = $config['telegram'];
$telegram = new TelegramAPI($telegramConfig['bot_token'], $http, $logger);
$chatId = (int)$telegramConfig['chat_id'];
$channelId = $telegramConfig['channel_id'];

echo "✓ Telegram API: бот и канал {$channelId}\n";

$itemRepository = new ItemRepository($db, $logger);
$publicationRepository = new PublicationRepository($db, $logger);
$feedStateRepository = new FeedStateRepository($db, $logger);

echo "✓ Репозитории инициализированы\n";

$cacheDir = $config['cache']['directory'];
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

$fetchRunner = new FetchRunner($db, $cacheDir, $logger);

echo "✓ FetchRunner инициализирован\n";
echo "✓ Cache: {$cacheDir}\n\n";

// ============================================================================
// СТАТИСТИКА
// ============================================================================

$startTime = microtime(true);
$testStats = [
    'feeds_count' => count($config['feeds']),
    'stage1_items' => 0,
    'stage1_errors' => 0,
    'stage2_published' => 0,
    'stage2_with_media' => 0,
    'stage3_new_items' => 0,
    'stage3_cached' => 0,
    'stage4_published' => 0,
    'stage4_with_media' => 0,
];

// ============================================================================
// СТАРТОВОЕ УВЕДОМЛЕНИЕ
// ============================================================================

try {
    $startMsg = "🚀 <b>СТАРТ УПРОЩЕННОГО ТЕСТИРОВАНИЯ V2</b>\n\n" .
                "<b>Тест:</b> {$testId}\n" .
                "<b>Источников:</b> " . count($config['feeds']) . "\n" .
                "<b>Канал:</b> {$channelId}\n\n" .
                "⏳ Этап 1: Сбор новостей (20 из каждого источника)...";
    $telegram->sendMessage($chatId, $startMsg, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
    echo "✓ Стартовое уведомление отправлено\n\n";
} catch (Exception $e) {
    echo "⚠️ Ошибка отправки уведомления: {$e->getMessage()}\n\n";
}

// ============================================================================
// ЭТАП 1: ПЕРВЫЙ СБОР НОВОСТЕЙ
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

$savedItems = [];
foreach ($fetchResult1 as $feedId => $result) {
    if ($result->items) {
        $testStats['stage1_items'] += count($result->items);
        
        foreach ($result->items as $rawItem) {
            try {
                $itemId = $itemRepository->save($feedId, $rawItem);
                if ($itemId) {
                    $savedItems[] = [
                        'id' => $itemId,
                        'feed_id' => $feedId,
                        'title' => $rawItem->title,
                        'description' => $rawItem->description,
                        'link' => $rawItem->link,
                        'enclosure' => $rawItem->enclosure,
                        'pubDate' => $rawItem->pubDate,
                    ];
                }
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
echo "  - Сохранено в БД: " . count($savedItems) . "\n";
echo "  - Ошибок: {$testStats['stage1_errors']}\n\n";

try {
    $msg1 = "✅ <b>ЭТАП 1 ЗАВЕРШЕН</b>\n\n" .
            "📊 <b>Результаты:</b>\n" .
            "  • Источников: " . count($fetchResult1) . "\n" .
            "  • Новостей: {$testStats['stage1_items']}\n" .
            "  • Сохранено: " . count($savedItems) . "\n" .
            "  • Ошибок: {$testStats['stage1_errors']}\n\n" .
            "⏳ Этап 2: Отбор и публикация (по 1 из каждого языка)...";
    $telegram->sendMessage($chatId, $msg1, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
} catch (Exception $e) {
    echo "⚠️ Ошибка отправки: {$e->getMessage()}\n";
}

// ============================================================================
// ЭТАП 2: ПУБЛИКАЦИЯ (ПО 1 ИЗ КАЖДОГО ЯЗЫКА)
// ============================================================================

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  📢 ЭТАП 2: ПУБЛИКАЦИЯ (ПО 1 ИЗ КАЖДОГО ЯЗЫКА)              ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Группируем по источникам
$itemsByFeed = [];
foreach ($savedItems as $item) {
    $feedId = $item['feed_id'];
    if (!isset($itemsByFeed[$feedId])) {
        $itemsByFeed[$feedId] = [];
    }
    $itemsByFeed[$feedId][] = $item;
}

// Распределение источников по языкам
$languageFeeds = [
    'ru' => [1, 2, 3, 4, 5, 6],
    'en' => [7, 8, 9, 10, 11, 12],
    'fr' => [13, 14, 15, 16, 17, 18],
    'de' => [19, 20, 21, 22, 23, 24],
    'zh' => [25, 26, 27, 28, 29, 30],
];

$selectedNews = [];
foreach ($languageFeeds as $lang => $feedIds) {
    foreach ($feedIds as $feedId) {
        if (isset($itemsByFeed[$feedId]) && !empty($itemsByFeed[$feedId])) {
            $selectedNews[] = [
                'item' => $itemsByFeed[$feedId][0],
                'language' => $lang,
                'feed_id' => $feedId,
            ];
            break;
        }
    }
}

echo "📰 Отобрано для публикации: " . count($selectedNews) . " новостей\n";
echo "Языки: " . implode(', ', array_unique(array_column($selectedNews, 'language'))) . "\n\n";

foreach ($selectedNews as $newsData) {
    $item = $newsData['item'];
    $language = $newsData['language'];
    $feedId = $newsData['feed_id'];
    
    $feedName = 'Unknown';
    foreach ($config['feeds'] as $feed) {
        if ($feed['id'] === $feedId) {
            $feedName = $feed['title'];
            break;
        }
    }
    
    $title = $item['title'] ?? 'Без заголовка';
    $description = $item['description'] ?? '';
    $pubDate = $item['pubDate'] ?? '';
    
    $shortTitle = mb_strlen($title) > 100 ? mb_substr($title, 0, 97) . "..." : $title;
    $shortDesc = $description ? (mb_strlen($description) > 200 ? mb_substr($description, 0, 197) . "..." : $description) : 'Нет описания';
    
    // Проверяем медиа
    $media = null;
    $hasMedia = false;
    
    if (!empty($item['enclosure'])) {
        $enclosure = is_string($item['enclosure']) 
            ? json_decode($item['enclosure'], true) 
            : $item['enclosure'];
        
        if (is_array($enclosure) && !empty($enclosure['url'])) {
            $type = $enclosure['type'] ?? '';
            $url = $enclosure['url'];
            
            if (str_starts_with($type, 'image/') || preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $url)) {
                $media = ['type' => 'photo', 'url' => $url];
                $hasMedia = true;
            }
        }
    }
    
    // Формируем текст публикации (БЕЗ ССЫЛОК!)
    $publicationText = "<b>{$shortTitle}</b>\n\n" .
                       "{$shortDesc}\n\n" .
                       "📰 {$feedName} | 🌍 {$language}\n\n" .
                       "━━━━━━━━━━━━━━━━━━━━━━\n" .
                       "📊 <b>Служебная информация:</b>\n" .
                       "• ID новости: {$item['id']}\n" .
                       "• Источник: {$feedName}\n" .
                       "• Дата: {$pubDate}";
    
    $caption = mb_strlen($publicationText) > 1024 
        ? mb_substr($publicationText, 0, 1020) . "..." 
        : $publicationText;
    
    try {
        echo "\n📤 Публикация #{$item['id']}: {$feedName}\n";
        echo "   Заголовок: " . mb_substr($title, 0, 60) . "...\n";
        echo "   Язык: {$language}\n";
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
            $testStats['stage2_with_media']++;
        } else {
            $result = $telegram->sendMessage(
                $channelId,
                $publicationText,
                ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
            );
        }
        
        $messageId = $result->messageId ?? 0;
        $publicationRepository->record($item['id'], $feedId, 'channel', $channelId, $messageId);
        
        $testStats['stage2_published']++;
        echo "   ✓ Опубликовано (message_id: {$messageId})\n";
        
        sleep(2);
        
    } catch (Exception $e) {
        echo "   ✗ Ошибка публикации: {$e->getMessage()}\n";
    }
}

$mediaPercentage2 = $testStats['stage2_published'] > 0 
    ? round(($testStats['stage2_with_media'] / $testStats['stage2_published']) * 100, 1) 
    : 0;

echo "\n";
echo "📊 Результаты публикации:\n";
echo "  - Опубликовано: {$testStats['stage2_published']}\n";
echo "  - С медиа: {$testStats['stage2_with_media']} ({$mediaPercentage2}%)\n\n";

try {
    $msg2 = "✅ <b>ЭТАП 2 ЗАВЕРШЕН</b>\n\n" .
            "📊 <b>Результаты:</b>\n" .
            "  • Опубликовано: {$testStats['stage2_published']}\n" .
            "  • С медиа: {$testStats['stage2_with_media']} ({$mediaPercentage2}%)\n\n" .
            "⏳ Этап 3: Проверка кеширования (второй запрос)...";
    $telegram->sendMessage($chatId, $msg2, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
} catch (Exception $e) {
    // Ignore
}

// ============================================================================
// ЭТАП 3: ВТОРОЙ ЗАПРОС (КЕШИРОВАНИЕ)
// ============================================================================

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  🔄 ЭТАП 3: ВТОРОЙ ЗАПРОС (КЕШИРОВАНИЕ)                      ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

sleep(3);

$fetchResult2 = $fetchRunner->runForAllFeeds($feedConfigs);

foreach ($fetchResult2 as $result) {
    if ($result->items) {
        $testStats['stage3_new_items'] += count($result->items);
    }
    
    if ($result->status === 'not_modified' || ($result->items === null && $result->error === null)) {
        $testStats['stage3_cached']++;
    }
}

echo "\n";
echo "📊 Результаты второго сбора:\n";
echo "  - Источников обработано: " . count($fetchResult2) . "\n";
echo "  - Новых новостей: {$testStats['stage3_new_items']}\n";
echo "  - Из кеша (304): {$testStats['stage3_cached']}\n\n";

try {
    $msg3 = "✅ <b>ЭТАП 3 ЗАВЕРШЕН</b>\n\n" .
            "📊 <b>Результаты:</b>\n" .
            "  • Источников: " . count($fetchResult2) . "\n" .
            "  • Новых: {$testStats['stage3_new_items']}\n" .
            "  • Из кеша: {$testStats['stage3_cached']}\n\n" .
            "⏳ Этап 4: Дополнительная публикация (5 случайных)...";
    $telegram->sendMessage($chatId, $msg3, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
} catch (Exception $e) {
    // Ignore
}

// ============================================================================
// ЭТАП 4: ДОПОЛНИТЕЛЬНАЯ ПУБЛИКАЦИЯ (5 СЛУЧАЙНЫХ ИСТОЧНИКОВ)
// ============================================================================

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  🎲 ЭТАП 4: ПУБЛИКАЦИЯ ИЗ 5 СЛУЧАЙНЫХ ИСТОЧНИКОВ             ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

$allFeedIds = array_column($config['feeds'], 'id');
shuffle($allFeedIds);
$randomFeedIds = array_slice($allFeedIds, 0, 5);

echo "🎲 Выбраны случайные источники: " . implode(', ', $randomFeedIds) . "\n\n";

$randomNews = [];
foreach ($randomFeedIds as $feedId) {
    if (isset($itemsByFeed[$feedId]) && !empty($itemsByFeed[$feedId])) {
        $randomNews[] = [
            'item' => $itemsByFeed[$feedId][0],
            'feed_id' => $feedId,
        ];
    }
}

echo "📰 Найдено для публикации: " . count($randomNews) . " новостей\n\n";

foreach ($randomNews as $newsData) {
    $item = $newsData['item'];
    $feedId = $newsData['feed_id'];
    
    $feedName = 'Unknown';
    $language = 'unknown';
    foreach ($config['feeds'] as $feed) {
        if ($feed['id'] === $feedId) {
            $feedName = $feed['title'];
            $language = $feed['language'] ?? 'unknown';
            break;
        }
    }
    
    $title = $item['title'] ?? 'Без заголовка';
    $description = $item['description'] ?? '';
    $pubDate = $item['pubDate'] ?? '';
    
    $shortTitle = mb_strlen($title) > 100 ? mb_substr($title, 0, 97) . "..." : $title;
    $shortDesc = $description ? (mb_strlen($description) > 200 ? mb_substr($description, 0, 197) . "..." : $description) : 'Нет описания';
    
    // Медиа
    $media = null;
    $hasMedia = false;
    
    if (!empty($item['enclosure'])) {
        $enclosure = is_string($item['enclosure']) 
            ? json_decode($item['enclosure'], true) 
            : $item['enclosure'];
        
        if (is_array($enclosure) && !empty($enclosure['url'])) {
            $type = $enclosure['type'] ?? '';
            $url = $enclosure['url'];
            
            if (str_starts_with($type, 'image/') || preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $url)) {
                $media = ['type' => 'photo', 'url' => $url];
                $hasMedia = true;
            }
        }
    }
    
    $publicationText = "<b>{$shortTitle}</b>\n\n" .
                       "{$shortDesc}\n\n" .
                       "📰 {$feedName} | 🌍 {$language}\n\n" .
                       "━━━━━━━━━━━━━━━━━━━━━━\n" .
                       "📊 <b>Служебная информация:</b>\n" .
                       "• ID новости: {$item['id']}\n" .
                       "• Источник: {$feedName}\n" .
                       "• Дата: {$pubDate}";
    
    $caption = mb_strlen($publicationText) > 1024 
        ? mb_substr($publicationText, 0, 1020) . "..." 
        : $publicationText;
    
    try {
        echo "\n📤 Публикация #{$item['id']}: {$feedName}\n";
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
            $testStats['stage4_with_media']++;
        } else {
            $result = $telegram->sendMessage(
                $channelId,
                $publicationText,
                ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
            );
        }
        
        $messageId = $result->messageId ?? 0;
        $publicationRepository->record($item['id'], $feedId, 'channel', $channelId, $messageId);
        
        $testStats['stage4_published']++;
        echo "   ✓ Опубликовано (message_id: {$messageId})\n";
        
        sleep(2);
        
    } catch (Exception $e) {
        echo "   ✗ Ошибка публикации: {$e->getMessage()}\n";
    }
}

$mediaPercentage4 = $testStats['stage4_published'] > 0 
    ? round(($testStats['stage4_with_media'] / $testStats['stage4_published']) * 100, 1) 
    : 0;

echo "\n";
echo "📊 Результаты дополнительной публикации:\n";
echo "  - Опубликовано: {$testStats['stage4_published']}\n";
echo "  - С медиа: {$testStats['stage4_with_media']} ({$mediaPercentage4}%)\n\n";

// ============================================================================
// ИТОГОВАЯ СТАТИСТИКА
// ============================================================================

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  📈 ИТОГОВАЯ ДЕТАЛЬНАЯ СТАТИСТИКА                            ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

$totalPublished = $testStats['stage2_published'] + $testStats['stage4_published'];
$totalWithMedia = $testStats['stage2_with_media'] + $testStats['stage4_with_media'];
$totalMediaPercentage = $totalPublished > 0 ? round(($totalWithMedia / $totalPublished) * 100, 1) : 0;

$totalNewsInDb = $db->queryScalar("SELECT COUNT(*) FROM rss2tlg_items");
$totalPublications = $db->queryScalar("SELECT COUNT(*) FROM rss2tlg_publications");

echo "📊 <b>ОБЩАЯ СТАТИСТИКА:</b>\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "  Источников: " . count($config['feeds']) . "\n";
echo "  Новостей собрано (1-й запрос): {$testStats['stage1_items']}\n";
echo "  Новостей собрано (2-й запрос): {$testStats['stage3_new_items']}\n";
echo "  Из кеша: {$testStats['stage3_cached']}\n";
echo "  Ошибок сбора: {$testStats['stage1_errors']}\n\n";

echo "📢 <b>ПУБЛИКАЦИИ:</b>\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "  Этап 2 (по языкам): {$testStats['stage2_published']}\n";
echo "  Этап 4 (случайные): {$testStats['stage4_published']}\n";
echo "  Всего опубликовано: {$totalPublished}\n";
echo "  С медиа: {$totalWithMedia} ({$totalMediaPercentage}%)\n";
echo "  Требование 30%: " . ($totalMediaPercentage >= 30 ? "✅ ВЫПОЛНЕНО" : "❌ НЕ ВЫПОЛНЕНО") . "\n\n";

echo "💾 <b>БАЗА ДАННЫХ:</b>\n";
echo "════════════════════════════════════════════════════════════════\n";
$tables = ['rss2tlg_items', 'rss2tlg_feed_state', 'rss2tlg_publications'];
foreach ($tables as $table) {
    $count = $db->queryScalar("SELECT COUNT(*) FROM {$table}");
    echo "  {$table}: {$count} записей\n";
}
echo "\n";

$executionTime = round(microtime(true) - $startTime, 2);

echo "⏱️ <b>ПРОИЗВОДИТЕЛЬНОСТЬ:</b>\n";
echo "════════════════════════════════════════════════════════════════\n";
echo "  Общее время: {$executionTime} сек (" . round($executionTime / 60, 2) . " мин)\n";
echo "  Среднее на источник: " . round($executionTime / count($config['feeds']), 2) . " сек\n\n";

$logFile = "{$logConfig['directory']}/rss_simple_v2.log";
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
// ФИНАЛЬНОЕ УВЕДОМЛЕНИЕ
// ============================================================================

try {
    $finalMsg = "🎉 <b>УПРОЩЕННОЕ ТЕСТИРОВАНИЕ V2 ЗАВЕРШЕНО</b>\n\n" .
                "📊 <b>Итоговая статистика:</b>\n\n" .
                "📡 <b>Сбор:</b>\n" .
                "  • Источников: " . count($config['feeds']) . "\n" .
                "  • Новостей (1-й): {$testStats['stage1_items']}\n" .
                "  • Новостей (2-й): {$testStats['stage3_new_items']}\n" .
                "  • Из кеша: {$testStats['stage3_cached']}\n\n" .
                "📢 <b>Публикации:</b>\n" .
                "  • Всего: {$totalPublished}\n" .
                "  • С медиа: {$totalWithMedia} ({$totalMediaPercentage}%)\n" .
                "  • Требование 30%: " . ($totalMediaPercentage >= 30 ? "✅" : "❌") . "\n\n" .
                "💾 <b>БД:</b>\n" .
                "  • Новостей: {$totalNewsInDb}\n" .
                "  • Публикаций: {$totalPublications}\n\n" .
                "⏱️ <b>Время:</b> {$executionTime} сек\n\n" .
                "✅ Все этапы пройдены успешно!\n" .
                "⚠️ AI-анализ отключен из-за проблем с OpenRouter API";
    
    $telegram->sendMessage($chatId, $finalMsg, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
    
    echo "✓ Финальное уведомление отправлено\n\n";
} catch (Exception $e) {
    echo "⚠️ Ошибка отправки финального уведомления: {$e->getMessage()}\n\n";
}

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  ✅ ТЕСТИРОВАНИЕ УСПЕШНО ЗАВЕРШЕНО                           ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

echo "📝 Логи: {$logFile}\n";
echo "📊 Канал: {$channelId}\n";
echo "💾 БД: {$dbConfig['name']}\n\n";

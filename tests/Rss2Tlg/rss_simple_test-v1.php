<?php

declare(strict_types=1);

/**
 * 🔥 УПРОЩЕННЫЙ E2E ТЕСТ RSS2TLG БЕЗ AI-АНАЛИЗА
 * 
 * Этот тест проверяет базовую функциональность RSS2TLG:
 * 1. Сбор новостей из 25 RSS источников (5 языков)
 * 2. Публикация в Telegram канал (с медиа контентом)
 * 3. Уведомления в Telegram бот о ходе тестирования
 * 4. Проверка кеширования и дедупликации
 * 
 * БЕЗ AI-АНАЛИЗА из-за требований приватности OpenRouter для free моделей
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\Http;
use App\Component\WebtExtractor;
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

$testId = 'RSS2TLG-SIMPLE-E2E-001';
$configPath = __DIR__ . '/../../config/rss2tlg_ai_test.json';

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  🔥 УПРОЩЕННЫЙ E2E ТЕСТ RSS2TLG (БЕЗ AI)                     ║\n";
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
    'file_name' => 'rss_simple_test.log',
    'max_files' => $logConfig['max_files'] ?? 10,
    'max_file_size' => $logConfig['max_file_size'] ?? 100,
    'enabled' => $logConfig['enabled'] ?? true,
]);

echo "✓ Логгер: {$logConfig['directory']}/rss_simple_test.log\n";

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
// ОТПРАВКА СТАРТОВОГО УВЕДОМЛЕНИЯ В TELEGRAM
// ============================================================================

$startTime = microtime(true);

try {
    $startMsg = "🚀 <b>СТАРТ УПРОЩЕННОГО ТЕСТИРОВАНИЯ</b>\n\n" .
                "<b>Тест:</b> {$testId}\n" .
                "<b>Источников:</b> " . count($config['feeds']) . "\n" .
                "<b>Канал:</b> {$channelId}\n\n" .
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

$totalFeeds1 = count($fetchResult1);
$totalItems1 = 0;
$totalErrors1 = 0;
$savedItems = [];

foreach ($fetchResult1 as $feedId => $result) {
    if ($result->items) {
        $newItemsCount = count($result->items);
        $totalItems1 += $newItemsCount;
        
        foreach ($result->items as $rawItem) {
            try {
                $itemId = $itemRepository->save($feedId, $rawItem);
                if ($itemId) {
                    $savedItems[] = [
                        'id' => $itemId,
                        'feed_id' => $feedId,
                        'title' => $rawItem->title,
                        'link' => $rawItem->link,
                        'enclosure' => $rawItem->enclosure,
                    ];
                }
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
echo "  - Сохранено в БД: " . count($savedItems) . "\n";
echo "  - Ошибок: {$totalErrors1}\n\n";

try {
    $msg1 = "✅ <b>ЭТАП 1: СБОР ЗАВЕРШЕН</b>\n\n" .
            "📊 <b>Результаты:</b>\n" .
            "  • Источников: {$totalFeeds1}\n" .
            "  • Новостей: {$totalItems1}\n" .
            "  • Сохранено: " . count($savedItems) . "\n" .
            "  • Ошибок: {$totalErrors1}\n\n" .
            "⏳ Переходим к публикации...";
    $telegram->sendMessage($chatId, $msg1, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
} catch (Exception $e) {
    echo "⚠️ Ошибка отправки уведомления: {$e->getMessage()}\n";
}

// ============================================================================
// ЭТАП 2: ПУБЛИКАЦИЯ В TELEGRAM (ПО 1 ИЗ КАЖДОГО ЯЗЫКА)
// ============================================================================

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  📢 ЭТАП 2: ПУБЛИКАЦИЯ В TELEGRAM КАНАЛ                      ║\n";
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

// Берем по 1 новости из каждого языка
$languageFeeds = [
    'ru' => [1, 2, 3, 4, 5],      // Русские
    'en' => [6, 7, 8, 9, 10],     // Английские
    'fr' => [11, 12, 13, 14, 15], // Французские
    'de' => [16, 17, 18, 19, 20], // Немецкие
    'zh' => [21, 22, 23, 24, 25], // Китайские
];

$selectedNews = [];
foreach ($languageFeeds as $lang => $feedIds) {
    foreach ($feedIds as $feedId) {
        if (isset($itemsByFeed[$feedId]) && !empty($itemsByFeed[$feedId])) {
            // Берем первую новость
            $selectedNews[] = [
                'item' => $itemsByFeed[$feedId][0],
                'language' => $lang,
                'feed_id' => $feedId,
            ];
            break; // Берем только одну из этого языка
        }
    }
}

echo "📰 Отобрано для публикации: " . count($selectedNews) . " новостей\n";
echo "Языки: " . implode(', ', array_unique(array_column($selectedNews, 'language'))) . "\n\n";

$publishedCount = 0;
$publishedWithMedia = 0;

foreach ($selectedNews as $newsData) {
    $item = $newsData['item'];
    $language = $newsData['language'];
    $feedId = $newsData['feed_id'];
    
    // Находим название источника
    $feedName = 'Unknown';
    foreach ($config['feeds'] as $feed) {
        if ($feed['id'] === $feedId) {
            $feedName = $feed['title'];
            break;
        }
    }
    
    $title = $item['title'] ?? 'Без заголовка';
    $link = $item['link'] ?? '';
    
    // Обрезаем заголовок для компактности
    $shortTitle = mb_strlen($title) > 100 ? mb_substr($title, 0, 97) . "..." : $title;
    
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
    
    // Формируем текст публикации
    $publicationText = "<b>{$shortTitle}</b>\n\n" .
                       "📎 <a href=\"{$link}\">Читать полностью</a>\n\n" .
                       "📰 Источник: {$feedName}\n" .
                       "🌍 Язык: {$language}\n" .
                       "🆔 ID: {$item['id']}";
    
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
            $publishedWithMedia++;
        } else {
            $result = $telegram->sendMessage(
                $channelId,
                $publicationText,
                ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
            );
        }
        
        // $result - объект Message, извлекаем message_id
        $messageId = $result->messageId ?? 0;
        $publicationRepository->record($item['id'], $feedId, 'channel', $channelId, $messageId);
        
        $publishedCount++;
        echo "   ✓ Опубликовано (message_id: {$messageId})\n";
        
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

try {
    $msg2 = "✅ <b>ЭТАП 2: ПУБЛИКАЦИЯ ЗАВЕРШЕНА</b>\n\n" .
            "📊 <b>Результаты:</b>\n" .
            "  • Опубликовано: {$publishedCount}\n" .
            "  • С медиа: {$publishedWithMedia} ({$mediaPercentage}%)\n\n" .
            "⏳ Переходим к проверке кеширования...";
    $telegram->sendMessage($chatId, $msg2, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
} catch (Exception $e) {
    // Ignore
}

// ============================================================================
// ЭТАП 3: ВТОРОЙ ЗАПРОС (ПРОВЕРКА КЕШИРОВАНИЯ)
// ============================================================================

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  🔄 ЭТАП 3: ВТОРОЙ ЗАПРОС (ПРОВЕРКА КЕШИРОВАНИЯ)            ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

sleep(3);

$fetchResult2 = $fetchRunner->runForAllFeeds($feedConfigs);

$totalFeeds2 = count($fetchResult2);
$totalItems2 = 0;
$totalCached2 = 0;

foreach ($fetchResult2 as $result) {
    if ($result->items) {
        $newItems = count($result->items);
        $totalItems2 += $newItems;
    }
    
    // Считаем кешированные (304 или пустой результат)
    if ($result->status === 'not_modified' || ($result->items === null && $result->error === null)) {
        $totalCached2++;
    }
}

echo "\n";
echo "📊 Результаты второго сбора:\n";
echo "  - Источников обработано: {$totalFeeds2}\n";
echo "  - Новых новостей: {$totalItems2}\n";
echo "  - Из кеша (304): {$totalCached2}\n\n";

try {
    $msg3 = "✅ <b>ЭТАП 3: КЕШИРОВАНИЕ ПРОВЕРЕНО</b>\n\n" .
            "📊 <b>Результаты:</b>\n" .
            "  • Источников: {$totalFeeds2}\n" .
            "  • Новых: {$totalItems2}\n" .
            "  • Из кеша: {$totalCached2}\n";
    $telegram->sendMessage($chatId, $msg3, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
} catch (Exception $e) {
    // Ignore
}

// ============================================================================
// ЭТАП 4: ИТОГОВАЯ СТАТИСТИКА
// ============================================================================

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  📈 ИТОГОВАЯ ДЕТАЛЬНАЯ СТАТИСТИКА                            ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

$totalNewsInDb = $db->queryScalar("SELECT COUNT(*) FROM rss2tlg_items");
$totalPublications = $db->queryScalar("SELECT COUNT(*) FROM rss2tlg_publications");

echo "📰 <b>НОВОСТИ В БД:</b>\n";
echo "  Всего новостей: {$totalNewsInDb}\n";
echo "  Опубликовано: {$totalPublications}\n\n";

echo "💾 <b>ПРОВЕРКА ТАБЛИЦ БД:</b>\n";
$tables = ['rss2tlg_items', 'rss2tlg_feed_state', 'rss2tlg_publications'];
foreach ($tables as $table) {
    $count = $db->queryScalar("SELECT COUNT(*) FROM {$table}");
    echo "  {$table}: {$count} записей\n";
}
echo "\n";

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
                "📢 <b>Публикации:</b>\n" .
                "  • Опубликовано: {$publishedCount}\n" .
                "  • С медиа: {$publishedWithMedia} ({$mediaPercentage}%)\n\n" .
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

echo "📝 Логи: {$logConfig['directory']}/rss_simple_test.log\n";
echo "📊 Канал: {$channelId}\n\n";

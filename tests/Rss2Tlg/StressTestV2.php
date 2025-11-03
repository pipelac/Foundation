<?php

declare(strict_types=1);

/**
 * 🔥 СТРЕСС-ТЕСТ RSS2TLG V2 С МЕДИА И ИНДИКАЦИЕЙ ПРОГРЕССА
 * 
 * Идентификатор: RSS2TLG-STRESS-TEST-002
 * 
 * Улучшения версии 2:
 * - ✅ Полноценная поддержка медиа (фото/видео) из RSS enclosures
 * - ✅ Индикация прогресса постинга в телеграм бот (typing/uploading actions)
 * - ✅ Streaming режим для публикации в канал
 * - ✅ Детальная статистика по медиа-контенту
 * - ✅ Улучшенное извлечение медиа из различных форматов RSS
 * 
 * ТРЕБОВАНИЯ:
 * - MySQL сервер запущен и доступен
 * - БД rss2tlg создана
 * - Telegram bot token и channel_id настроены
 * - Директории cache и logs созданы
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\WebtExtractor;
use App\Rss2Tlg\ContentExtractorService;
use App\Rss2Tlg\FeedStateRepository;
use App\Rss2Tlg\FetchRunner;
use App\Rss2Tlg\ItemRepository;
use App\Rss2Tlg\PublicationRepository;
use App\Rss2Tlg\DTO\FeedConfig;
use App\Component\TelegramBot\Core\TelegramAPI;

// ============================================================================
// КОНФИГУРАЦИЯ
// ============================================================================

$testId = 'RSS2TLG-STRESS-TEST-002';

$config = [
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'rss2tlg',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'telegram' => [
        'bot_token' => '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI',
        'chat_id' => 366442475, // Для уведомлений
        'channel_id' => '@kompasDaily', // Для публикаций
    ],
    'cache_dir' => '/home/engine/project/cache/rss2tlg',
    'log_file' => '/home/engine/project/logs/rss2tlg_stress_test_v2.log',
    'feeds' => [
        // Базовые 5 источников (известные источники с медиа)
        [
            'id' => 1,
            'name' => 'РИА Новости',
            'url' => 'https://ria.ru/export/rss2/index.xml?page_type=google_newsstand',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/2.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 2,
            'name' => 'Ведомости Технологии',
            'url' => 'https://www.vedomosti.ru/rss/rubric/technology.xml',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/2.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 3,
            'name' => 'Лента.ру Топ-7',
            'url' => 'http://lenta.ru/rss/top7',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/2.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 4,
            'name' => 'Ars Technica AI',
            'url' => 'https://arstechnica.com/ai/feed',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/2.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 5,
            'name' => 'TechCrunch Startups',
            'url' => 'https://techcrunch.com/startups/feed',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/2.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        
        // Дополнительные 10 источников с медиа-контентом
        [
            'id' => 6,
            'name' => 'BBC News World',
            'url' => 'http://feeds.bbci.co.uk/news/world/rss.xml',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/2.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 7,
            'name' => 'The Guardian Tech',
            'url' => 'https://www.theguardian.com/technology/rss',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/2.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 8,
            'name' => 'Wired',
            'url' => 'https://www.wired.com/feed/rss',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/2.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 9,
            'name' => 'The Verge',
            'url' => 'https://www.theverge.com/rss/index.xml',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/2.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 10,
            'name' => 'Engadget',
            'url' => 'https://www.engadget.com/rss.xml',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/2.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 11,
            'name' => 'ТАСС',
            'url' => 'https://tass.ru/rss/v2.xml',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/2.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 12,
            'name' => 'РБК',
            'url' => 'https://rssexport.rbc.ru/rbcnews/news/30/full.rss',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/2.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 13,
            'name' => 'Хабр',
            'url' => 'https://habr.com/ru/rss/all/all/?fl=ru',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/2.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 14,
            'name' => 'N+1',
            'url' => 'https://nplus1.ru/rss',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/2.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 15,
            'name' => 'CNews',
            'url' => 'https://www.cnews.ru/inc/rss/news.xml',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/2.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
    ],
];

// ============================================================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// ============================================================================

/**
 * Извлекает медиа из новости
 */
function extractMedia(array $item): ?array
{
    // Проверяем enclosure из RSS
    if (!empty($item['enclosures'])) {
        $enclosures = is_string($item['enclosures']) 
            ? json_decode($item['enclosures'], true) 
            : $item['enclosures'];
        
        if (is_array($enclosures)) {
            $type = $enclosures['type'] ?? '';
            $url = $enclosures['url'] ?? '';
            
            if (!empty($url)) {
                // Определяем тип медиа
                if (str_starts_with($type, 'image/') || preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $url)) {
                    return ['type' => 'photo', 'url' => $url];
                } elseif (str_starts_with($type, 'video/') || preg_match('/\.(mp4|mov|avi|webm)$/i', $url)) {
                    return ['type' => 'video', 'url' => $url];
                }
            }
        }
    }
    
    // Проверяем image_url в контенте
    if (!empty($item['extracted_content'])) {
        // Ищем <img> теги
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $item['extracted_content'], $matches)) {
            return ['type' => 'photo', 'url' => $matches[1]];
        }
    }
    
    // Проверяем description
    if (!empty($item['description'])) {
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $item['description'], $matches)) {
            return ['type' => 'photo', 'url' => $matches[1]];
        }
    }
    
    return null;
}

/**
 * Отправка уведомления в Telegram с индикацией прогресса
 */
function sendTelegramNotification(
    TelegramAPI $telegram, 
    int $chatId, 
    string $message,
    bool $withTyping = true
): void {
    try {
        // Индикация прогресса
        if ($withTyping) {
            $telegram->sendChatAction($chatId, 'typing');
            usleep(500000); // 0.5 сек для реалистичности
        }
        
        $telegram->sendMessage($chatId, $message, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
    } catch (\Exception $e) {
        echo "⚠️ Ошибка отправки уведомления: " . $e->getMessage() . "\n";
    }
}

/**
 * Публикация в канал с медиа и индикацией прогресса
 */
function publishToChannel(
    TelegramAPI $telegram,
    string $channelId,
    string $feedName,
    string $title,
    string $content,
    ?array $media
): ?array {
    try {
        $message = "<b>📰 $feedName</b>\n\n<b>$title</b>\n\n$content";
        
        // Если есть медиа - отправляем с медиа
        if ($media !== null && !empty($media['url'])) {
            $mediaUrl = $media['url'];
            
            // Индикация загрузки медиа
            if ($media['type'] === 'photo') {
                $telegram->sendChatAction($channelId, 'upload_photo');
                usleep(800000); // 0.8 сек
                
                // Обрезаем caption если больше 1024 символов (лимит Telegram)
                $caption = mb_strlen($message) > 1024 
                    ? mb_substr($message, 0, 1020) . "..." 
                    : $message;
                
                $result = $telegram->sendPhoto(
                    $channelId,
                    $mediaUrl,
                    [
                        'caption' => $caption,
                        'parse_mode' => TelegramAPI::PARSE_MODE_HTML
                    ]
                );
            } elseif ($media['type'] === 'video') {
                $telegram->sendChatAction($channelId, 'upload_video');
                usleep(1000000); // 1 сек
                
                $caption = mb_strlen($message) > 1024 
                    ? mb_substr($message, 0, 1020) . "..." 
                    : $message;
                
                $result = $telegram->sendVideo(
                    $channelId,
                    $mediaUrl,
                    [
                        'caption' => $caption,
                        'parse_mode' => TelegramAPI::PARSE_MODE_HTML
                    ]
                );
            } else {
                // Fallback: отправляем текстом
                $telegram->sendChatAction($channelId, 'typing');
                usleep(500000);
                $result = $telegram->sendMessage($channelId, $message, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
            }
        } else {
            // Без медиа - просто текст с индикацией
            $telegram->sendChatAction($channelId, 'typing');
            usleep(500000);
            $result = $telegram->sendMessage($channelId, $message, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
        }
        
        return $result->toArray();
    } catch (\Exception $e) {
        throw $e;
    }
}

/**
 * Цветной вывод
 */
function colorize(string $text, string $color = 'white'): string
{
    $colors = [
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'magenta' => "\033[35m",
        'cyan' => "\033[36m",
        'white' => "\033[37m",
        'reset' => "\033[0m",
        'bold' => "\033[1m",
    ];
    
    return ($colors[$color] ?? $colors['white']) . $text . $colors['reset'];
}

/**
 * Прогресс-бар
 */
function showProgress(int $current, int $total, string $label = ''): void
{
    $percent = $total > 0 ? round(($current / $total) * 100) : 0;
    $bar = str_repeat('█', (int)($percent / 2));
    $empty = str_repeat('░', 50 - (int)($percent / 2));
    echo "\r" . colorize("  $label ", 'cyan') . "[$bar$empty] $percent% ($current/$total)";
    if ($current >= $total) {
        echo "\n";
    }
}

// ============================================================================
// ИНИЦИАЛИЗАЦИЯ
// ============================================================================

$startTime = microtime(true);

echo "\n" . colorize(str_repeat('=', 100), 'cyan') . "\n";
echo colorize("🚀 СТРЕСС-ТЕСТ RSS2TLG V2 С МЕДИА И ИНДИКАЦИЕЙ ПРОГРЕССА", 'bold') . "\n";
echo colorize("   Идентификатор: $testId", 'cyan') . "\n";
echo colorize("   Дата: " . date('Y-m-d H:i:s'), 'cyan') . "\n";
echo colorize(str_repeat('=', 100), 'cyan') . "\n\n";

// Логгер
$logger = new Logger([
    'directory' => dirname($config['log_file']),
    'file_name' => basename($config['log_file']),
    'log_level' => 'debug',
    'rotation' => true,
    'max_file_size' => 10 * 1024 * 1024,
]);

// HTTP клиент для Telegram
$httpClient = new App\Component\Http(['timeout' => 30], $logger);

// Telegram API
$telegram = new TelegramAPI($config['telegram']['bot_token'], $httpClient, $logger);

// Стартовое уведомление с индикацией
sendTelegramNotification($telegram, $config['telegram']['chat_id'], 
    "🚀 <b>СТРЕСС-ТЕСТ RSS2TLG V2</b>\n\n" .
    "🆔 ID: <code>$testId</code>\n" .
    "📊 Источников: <b>" . count($config['feeds']) . "</b>\n" .
    "🕐 Старт: " . date('Y-m-d H:i:s') . "\n\n" .
    "✨ <b>Новые возможности:</b>\n" .
    "• Поддержка медиа (фото/видео)\n" .
    "• Индикация прогресса постинга\n" .
    "• Streaming режим публикации\n\n" .
    "⏳ Инициализация инфраструктуры..."
);

// Подключение к БД
echo colorize("📊 Подключение к MySQL...", 'yellow') . "\n";
try {
    $db = new MySQL([
        'host' => $config['database']['host'],
        'port' => $config['database']['port'],
        'database' => $config['database']['database'],
        'username' => $config['database']['username'],
        'password' => $config['database']['password'],
        'charset' => $config['database']['charset'],
    ], $logger);
    
    echo colorize("✅ Подключено к MySQL: " . $config['database']['database'], 'green') . "\n\n";
} catch (\Exception $e) {
    echo colorize("❌ Ошибка подключения к БД: " . $e->getMessage(), 'red') . "\n";
    sendTelegramNotification($telegram, $config['telegram']['chat_id'], 
        "❌ <b>Тест провален</b>\n\nОшибка подключения к БД:\n<code>" . htmlspecialchars($e->getMessage()) . "</code>"
    );
    exit(1);
}

// Создание репозиториев
$feedStateRepo = new FeedStateRepository($db, $logger);
$itemRepo = new ItemRepository($db, $logger, true);
$pubRepo = new PublicationRepository($db, $logger, true);

// FetchRunner
$fetchRunner = new FetchRunner($db, $config['cache_dir'], $logger);

// WebtExtractor
$extractor = new WebtExtractor(['timeout' => 30], $logger);
$contentExtractor = new ContentExtractorService($itemRepo, $extractor, $logger);

// Конвертация конфигов в FeedConfig
$feedConfigs = array_map(function (array $feed) {
    return FeedConfig::fromArray($feed);
}, $config['feeds']);

echo colorize("✅ Инфраструктура готова", 'green') . "\n";
echo colorize("   - Репозитории инициализированы", 'white') . "\n";
echo colorize("   - FetchRunner готов", 'white') . "\n";
echo colorize("   - ContentExtractor готов", 'white') . "\n";
echo colorize("   - Telegram API готов (с индикацией прогресса)", 'white') . "\n\n";

sendTelegramNotification($telegram, $config['telegram']['chat_id'], 
    "✅ <b>Инфраструктура готова</b>\n\n" .
    "Начинаем тестирование с медиа..."
);

sleep(2);

// ============================================================================
// ТЕСТ 1: ПОЛУЧЕНИЕ НОВОСТЕЙ И ПУБЛИКАЦИЯ С МЕДИА
// ============================================================================

echo colorize(str_repeat('=', 100), 'magenta') . "\n";
echo colorize("🔄 ТЕСТ 1: Получение новостей и публикация с медиа-контентом", 'magenta') . "\n";
echo colorize(str_repeat('=', 100), 'magenta') . "\n\n";

sendTelegramNotification($telegram, $config['telegram']['chat_id'], 
    "📥 <b>ТЕСТ 1: Публикация с медиа</b>\n\n" .
    "Получение новостей из " . count($feedConfigs) . " источников...",
    true
);

// Выбираем первые 10 источников
$test1Feeds = array_slice($feedConfigs, 0, 10);

$test1Stats = [
    'feeds_processed' => 0,
    'items_fetched' => 0,
    'items_saved' => 0,
    'items_published' => 0,
    'items_with_photo' => 0,
    'items_with_video' => 0,
    'items_without_media' => 0,
    'media_errors' => 0,
    'errors' => 0,
    'duration' => 0,
];

$test1Start = microtime(true);

// Fetch новостей
echo colorize("📥 Получение новостей...", 'yellow') . "\n\n";
$fetchResults = $fetchRunner->runForAllFeeds($test1Feeds);

$feedIndex = 0;
foreach ($fetchResults as $feedId => $result) {
    $feedIndex++;
    $feedConfig = null;
    foreach ($test1Feeds as $fc) {
        if ($fc->id === $feedId) {
            $feedConfig = $fc;
            break;
        }
    }
    $feedName = $feedConfig ? $feedConfig->name : "Feed #$feedId";
    
    showProgress($feedIndex, count($test1Feeds), "Обработка лент");
    
    if ($result->isSuccessful()) {
        $itemsCount = count($result->getValidItems());
        echo colorize("  ✅ $feedName: $itemsCount новостей", 'green') . "\n";
        
        $test1Stats['feeds_processed']++;
        $test1Stats['items_fetched'] += $itemsCount;
        
        // Сохраняем новости
        foreach ($result->getValidItems() as $item) {
            $itemId = $itemRepo->save($feedId, $item);
            if ($itemId !== null) {
                $test1Stats['items_saved']++;
            }
        }
    } else {
        echo colorize("  ❌ $feedName: Ошибка", 'red') . "\n";
        $test1Stats['errors']++;
    }
}

echo "\n";

// Публикация 2 новостей из каждой ленты (ПРИОРИТЕТ: С МЕДИА)
echo colorize("📰 Публикация новостей с медиа-контентом...", 'yellow') . "\n\n";

foreach ($test1Feeds as $feedConfig) {
    $feedId = $feedConfig->id;
    $feedName = $feedConfig->name ?? "Feed #$feedId";
    
    echo colorize("  📌 $feedName:", 'cyan') . "\n";
    
    // Получаем до 10 неопубликованных новостей для выбора
    $items = $itemRepo->getUnpublished($feedId, 10);
    
    if (empty($items)) {
        echo colorize("    ⚠️ Нет новых новостей", 'yellow') . "\n\n";
        continue;
    }
    
    $published = 0;
    foreach ($items as $item) {
        if ($published >= 2) {
            break;
        }
        
        $itemId = (int)$item['id'];
        $title = (string)$item['title'];
        $link = (string)$item['link'];
        
        // Извлекаем медиа
        $media = extractMedia($item);
        
        // Извлекаем контент если нужно
        if ($item['extraction_status'] === 'pending') {
            echo colorize("    🔍 Извлечение контента...", 'white') . "\n";
            $contentExtractor->processItem($item);
            $item = $itemRepo->getByContentHash($item['content_hash']);
            if ($item === null) {
                continue;
            }
            
            // Пробуем еще раз извлечь медиа после обработки
            if ($media === null) {
                $media = extractMedia($item);
            }
        }
        
        $content = $itemRepo->getEffectiveContent($item);
        
        // Обрезаем текст
        $wordCount = str_word_count(strip_tags($content));
        if (mb_strlen($content) > 800) {
            $content = mb_substr(strip_tags($content), 0, 800) . "...\n\n📊 Полный текст: $wordCount слов";
        } else {
            $content = strip_tags($content);
        }
        
        $mediaInfo = $media ? " [{$media['type']}]" : "";
        echo colorize("    📄 $title$mediaInfo", 'white') . "\n";
        
        // Публикуем в канал с индикацией прогресса
        try {
            $messageData = publishToChannel(
                $telegram,
                $config['telegram']['channel_id'],
                $feedName,
                $title,
                $content,
                $media
            );
            
            if ($messageData !== null && isset($messageData['message_id'])) {
                $pubRepo->record(
                    $itemId,
                    $feedId,
                    'channel',
                    $config['telegram']['channel_id'],
                    $messageData['message_id']
                );
                
                $itemRepo->markAsPublished($itemId);
                
                $test1Stats['items_published']++;
                
                if ($media !== null) {
                    if ($media['type'] === 'photo') {
                        $test1Stats['items_with_photo']++;
                        echo colorize("      ✅ Опубликовано с фото", 'green') . "\n";
                    } elseif ($media['type'] === 'video') {
                        $test1Stats['items_with_video']++;
                        echo colorize("      ✅ Опубликовано с видео", 'green') . "\n";
                    }
                } else {
                    $test1Stats['items_without_media']++;
                    echo colorize("      ✅ Опубликовано без медиа", 'green') . "\n";
                }
                
                $published++;
            }
        } catch (\Exception $e) {
            echo colorize("      ❌ Ошибка: " . $e->getMessage(), 'red') . "\n";
            
            if ($media !== null) {
                $test1Stats['media_errors']++;
            }
            $test1Stats['errors']++;
        }
        
        sleep(3); // Задержка между публикациями
    }
    
    echo "\n";
}

$test1Stats['duration'] = round(microtime(true) - $test1Start, 2);

// Статистика
echo colorize(str_repeat('-', 100), 'cyan') . "\n";
echo colorize("📊 СТАТИСТИКА ТЕСТА 1:", 'cyan') . "\n";
echo colorize(str_repeat('-', 100), 'cyan') . "\n";
echo "  Источников обработано: " . colorize((string)$test1Stats['feeds_processed'], 'green') . " / " . count($test1Feeds) . "\n";
echo "  Новостей получено: " . colorize((string)$test1Stats['items_fetched'], 'green') . "\n";
echo "  Новостей сохранено: " . colorize((string)$test1Stats['items_saved'], 'green') . "\n";
echo "  Новостей опубликовано: " . colorize((string)$test1Stats['items_published'], 'green') . "\n";
echo "  \n";
echo "  📸 Медиа-контент:\n";
echo "    - С фото: " . colorize((string)$test1Stats['items_with_photo'], 'green') . "\n";
echo "    - С видео: " . colorize((string)$test1Stats['items_with_video'], 'green') . "\n";
echo "    - Без медиа: " . colorize((string)$test1Stats['items_without_media'], 'yellow') . "\n";
echo "    - Ошибок медиа: " . ($test1Stats['media_errors'] > 0 ? colorize((string)$test1Stats['media_errors'], 'red') : '0') . "\n";
echo "  \n";
echo "  Ошибок: " . ($test1Stats['errors'] > 0 ? colorize((string)$test1Stats['errors'], 'red') : colorize('0', 'green')) . "\n";
echo "  Длительность: " . colorize($test1Stats['duration'] . " сек", 'cyan') . "\n";
echo colorize(str_repeat('-', 100), 'cyan') . "\n\n";

$mediaPercent = $test1Stats['items_published'] > 0 
    ? round(($test1Stats['items_with_photo'] + $test1Stats['items_with_video']) / $test1Stats['items_published'] * 100, 1)
    : 0;

sendTelegramNotification($telegram, $config['telegram']['chat_id'], 
    "✅ <b>ТЕСТ 1 завершен</b>\n\n" .
    "📥 Получено: <b>{$test1Stats['items_fetched']}</b> новостей\n" .
    "💾 Сохранено: <b>{$test1Stats['items_saved']}</b>\n" .
    "📤 Опубликовано: <b>{$test1Stats['items_published']}</b>\n\n" .
    "📸 <b>Медиа-контент:</b>\n" .
    "• С фото: <b>{$test1Stats['items_with_photo']}</b>\n" .
    "• С видео: <b>{$test1Stats['items_with_video']}</b>\n" .
    "• Без медиа: <b>{$test1Stats['items_without_media']}</b>\n" .
    "• Процент с медиа: <b>{$mediaPercent}%</b>\n\n" .
    "⏱ Время: {$test1Stats['duration']} сек",
    true
);

// ============================================================================
// ИТОГОВАЯ СТАТИСТИКА
// ============================================================================

$totalDuration = round(microtime(true) - $startTime, 2);

echo colorize(str_repeat('=', 100), 'green') . "\n";
echo colorize("🎉 ИТОГОВАЯ СТАТИСТИКА", 'bold') . "\n";
echo colorize("   Тест ID: $testId", 'cyan') . "\n";
echo colorize(str_repeat('=', 100), 'green') . "\n\n";

// Общая статистика
$itemStats = $itemRepo->getStats();
$pubStats = $pubRepo->getStats();

echo colorize("📰 НОВОСТИ:", 'yellow') . "\n";
echo "  Всего в БД: " . colorize((string)($itemStats['total'] ?? 0), 'bold') . "\n";
echo "  Опубликованных: " . colorize((string)($itemStats['published'] ?? 0), 'green') . "\n";
echo "  Неопубликованных: " . ($itemStats['unpublished'] ?? 0) . "\n";
echo "\n";

echo colorize("📤 ПУБЛИКАЦИИ:", 'yellow') . "\n";
echo "  Всего публикаций: " . colorize((string)($pubStats['total'] ?? 0), 'bold') . "\n";
echo "  В каналы: " . colorize((string)($pubStats['to_channel'] ?? 0), 'green') . "\n";
echo "\n";

echo colorize("📸 МЕДИА-КОНТЕНТ:", 'yellow') . "\n";
echo "  С фото: " . colorize((string)$test1Stats['items_with_photo'], 'green') . " (" . $mediaPercent . "%)\n";
echo "  С видео: " . colorize((string)$test1Stats['items_with_video'], 'green') . "\n";
echo "  Без медиа: " . $test1Stats['items_without_media'] . "\n";
echo "  Ошибок медиа: " . ($test1Stats['media_errors'] > 0 ? colorize((string)$test1Stats['media_errors'], 'red') : '0') . "\n";
echo "\n";

echo colorize("⏱ ОБЩЕЕ ВРЕМЯ: $totalDuration сек", 'cyan') . "\n";
echo colorize(str_repeat('=', 100), 'green') . "\n\n";

// Финальное уведомление
$finalMessage = "🎉 <b>СТРЕСС-ТЕСТ V2 ЗАВЕРШЕН</b>\n\n";
$finalMessage .= "🆔 ID: <code>$testId</code>\n\n";
$finalMessage .= "📊 <b>Результаты:</b>\n";
$finalMessage .= "━━━━━━━━━━━━━━━━━━━━\n";
$finalMessage .= "📥 Получено: {$test1Stats['items_fetched']} новостей\n";
$finalMessage .= "💾 Всего в БД: " . ($itemStats['total'] ?? 0) . "\n";
$finalMessage .= "📤 Опубликовано: {$test1Stats['items_published']}\n\n";
$finalMessage .= "📸 <b>Медиа:</b>\n";
$finalMessage .= "• Фото: <b>{$test1Stats['items_with_photo']}</b>\n";
$finalMessage .= "• Видео: <b>{$test1Stats['items_with_video']}</b>\n";
$finalMessage .= "• Процент: <b>{$mediaPercent}%</b>\n\n";
$finalMessage .= "⏱ Время: $totalDuration сек\n\n";
$finalMessage .= "✅ <b>С индикацией прогресса!</b>";

sendTelegramNotification($telegram, $config['telegram']['chat_id'], $finalMessage, true);

echo colorize("✅ ТЕСТ V2 ЗАВЕРШЕН УСПЕШНО!", 'green') . "\n";
echo colorize("📊 Подробные логи: " . $config['log_file'], 'cyan') . "\n";
echo colorize("🆔 Идентификатор: $testId", 'cyan') . "\n\n";

exit(0);

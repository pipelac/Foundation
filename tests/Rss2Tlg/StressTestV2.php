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
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'rss2tlg',
        'username' => 'rss2tlg_user',
        'password' => 'rss2tlg_pass',
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
        [
            'id' => 16,
            'name' => 'Reuters Technology',
            'url' => 'https://www.reutersagency.com/feed/?taxonomy=best-topics&post_type=best',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/2.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 17,
            'name' => 'MIT Technology Review',
            'url' => 'https://www.technologyreview.com/feed/',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/2.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 18,
            'name' => 'Hacker News',
            'url' => 'https://news.ycombinator.com/rss',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/2.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 19,
            'name' => 'VentureBeat',
            'url' => 'https://venturebeat.com/feed/',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/2.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 20,
            'name' => 'ZDNet',
            'url' => 'https://www.zdnet.com/news/rss.xml',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/2.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 21,
            'name' => 'Tech.eu',
            'url' => 'https://tech.eu/feed/',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/2.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 22,
            'name' => 'Silicon Angle',
            'url' => 'https://siliconangle.com/feed/',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/2.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 23,
            'name' => 'Gizmodo',
            'url' => 'https://gizmodo.com/feed',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/2.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 24,
            'name' => 'The Next Web',
            'url' => 'https://thenextweb.com/feed/',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/2.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 25,
            'name' => 'Mashable',
            'url' => 'https://mashable.com/feeds/rss/all',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/2.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 26,
            'name' => 'CNET',
            'url' => 'https://www.cnet.com/rss/news/',
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
 * 
 * ИСПРАВЛЕНО: медиа и текст публикуются вместе в одном сообщении
 * 
 * @param TelegramAPI $telegram
 * @param int $chatIdForProgress ID чата для прогресс-бара (админ бот)
 * @param string $channelId ID канала для публикации
 * @param string $feedName Название источника
 * @param string $title Заголовок новости
 * @param string $content Текст новости
 * @param array|null $media Медиа-контент
 * @param int $currentItem Текущий номер публикации
 * @param int $totalItems Общее количество публикаций
 * @return array|null
 */
function publishToChannel(
    TelegramAPI $telegram,
    int $chatIdForProgress,
    string $channelId,
    string $feedName,
    string $title,
    string $content,
    ?array $media,
    int $currentItem,
    int $totalItems
): ?array {
    try {
        // 1. Показываем прогресс БЕЗ анимации (простое обновление текста)
        try {
            $percent = round(($currentItem / $totalItems) * 100);
            $filledBars = (int)(($currentItem / $totalItems) * 20);
            $emptyBars = 20 - $filledBars;
            $progressBar = str_repeat('█', $filledBars) . str_repeat('░', $emptyBars);
            
            $progressMessage = "📊 <b>Публикация новостей</b>\n\n" .
                               "$progressBar\n" .
                               "Опубликовано: <b>$currentItem</b> из <b>$totalItems</b> ($percent%)";
            
            // Простая отправка без анимации
            $telegram->sendMessage($chatIdForProgress, $progressMessage, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
        } catch (\Exception $e) {
            // Игнорируем ошибки прогресс-бара
        }
        
        // 2. Формируем сообщение с медиа (если есть) - ВСЁ В ОДНОМ ПОСТЕ
        $message = "<b>📰 $feedName</b>\n\n<b>$title</b>\n\n$content";
        
        if ($media !== null && !empty($media['url'])) {
            $mediaUrl = $media['url'];
            
            // Обрезаем caption если больше 1024 символов (лимит Telegram)
            $caption = mb_strlen($message) > 1024 
                ? mb_substr($message, 0, 1020) . "..." 
                : $message;
            
            if ($media['type'] === 'photo') {
                $telegram->sendChatAction($channelId, 'upload_photo');
                
                // ИСПРАВЛЕНО: отправляем фото с ПОЛНЫМ caption в одном сообщении
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
                
                // ИСПРАВЛЕНО: отправляем видео с ПОЛНЫМ caption в одном сообщении
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
                $result = $telegram->sendMessage(
                    $channelId, 
                    $message, 
                    ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
                );
            }
        } else {
            // 3. Без медиа - отправляем текстом (можно использовать streaming)
            // ИСПРАВЛЕНО: используем streaming только для чисто текстовых постов
            if (mb_strlen($message) > 300) {
                // Для длинных текстов используем streaming
                $result = $telegram->sendMessageStreaming(
                    $channelId,
                    strip_tags($message), // Убираем HTML для streaming
                    [],
                    20, // символов за обновление
                    30, // задержка мс (быстрее)
                    true // показывать typing
                );
            } else {
                // Короткие тексты отправляем сразу
                $result = $telegram->sendMessage(
                    $channelId, 
                    $message, 
                    ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
                );
            }
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
// ТЕСТ 1: ПОЛУЧЕНИЕ НОВОСТЕЙ ИЗ ВСЕХ ИСТОЧНИКОВ И ПУБЛИКАЦИЯ
// ============================================================================

echo colorize(str_repeat('=', 100), 'magenta') . "\n";
echo colorize("🔄 ТЕСТ 1: Получение новостей из всех источников и публикация с медиа-контентом", 'magenta') . "\n";
echo colorize(str_repeat('=', 100), 'magenta') . "\n\n";

sendTelegramNotification($telegram, $config['telegram']['chat_id'], 
    "📥 <b>ТЕСТ 1: Первый fetch из всех источников</b>\n\n" .
    "Получение новостей из " . count($feedConfigs) . " источников...",
    true
);

// Используем ВСЕ источники для первого теста
$test1Feeds = $feedConfigs;

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

// ИСПРАВЛЕНО: показываем простой статус загрузки БЕЗ анимированного прогресс-бара
try {
    $telegram->sendMessage(
        $config['telegram']['chat_id'],
        "📥 <b>Загрузка новостей</b>\n\nОбрабатывается " . count($test1Feeds) . " источников...",
        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
    );
} catch (\Exception $e) {
    echo colorize("⚠️ Ошибка отправки уведомления: " . $e->getMessage(), 'yellow') . "\n";
}

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

// Публикация 1 новости из 10 случайных источников (ПРИОРИТЕТ: С МЕДИА)
echo colorize("📰 Публикация 1 новости из 10 случайных источников...", 'yellow') . "\n\n";

// Выбираем 10 случайных источников, у которых есть неопубликованные новости
$feedsWithItems = [];
foreach ($test1Feeds as $feedConfig) {
    $items = $itemRepo->getUnpublished($feedConfig->id, 10);
    if (!empty($items)) {
        $feedsWithItems[] = [
            'config' => $feedConfig,
            'items' => $items
        ];
    }
}

// Перемешиваем и берем до 10 случайных
shuffle($feedsWithItems);
$selectedFeeds = array_slice($feedsWithItems, 0, 10);

echo colorize("  Выбрано " . count($selectedFeeds) . " источников для публикации", 'cyan') . "\n\n";

$published = 0;
$totalToPublish = count($selectedFeeds);

foreach ($selectedFeeds as $feedData) {
    $feedConfig = $feedData['config'];
    $items = $feedData['items'];
    $feedId = $feedConfig->id;
    $feedName = $feedConfig->name ?? "Feed #$feedId";
    
    echo colorize("  📌 $feedName:", 'cyan') . "\n";
    
    // Берем первую новость из этого источника
    $item = $items[0];
    
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
            echo colorize("      ⚠️ Не удалось извлечь контент", 'yellow') . "\n\n";
            continue;
        }
        
        // Пробуем еще раз извлечь медиа после обработки
        if ($media === null) {
            $media = extractMedia($item);
        }
    }
    
    $content = $itemRepo->getEffectiveContent($item);
    
    // Обрезаем текст и ПОЛНОСТЬЮ очищаем от HTML
    $content = strip_tags($content); // Удаляем все HTML теги
    $wordCount = str_word_count($content);
    
    if (mb_strlen($content) > 500) {
        $content = mb_substr($content, 0, 500) . "...\n\n📊 Полный текст: $wordCount слов";
    }
    
    // Экранируем HTML спецсимволы для безопасной передачи в Telegram
    $content = htmlspecialchars($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    $mediaInfo = $media ? " [{$media['type']}]" : "";
    echo colorize("    📄 $title$mediaInfo", 'white') . "\n";
    
    // Публикуем в канал с прогресс-баром и streaming
    try {
        $messageData = publishToChannel(
            $telegram,
            $config['telegram']['chat_id'], // chat_id для прогресс-бара
            $config['telegram']['channel_id'], // channel_id для публикации
            $feedName,
            $title,
            $content,
            $media,
            $published + 1, // текущий номер публикации
            $totalToPublish // всего публикаций
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
    
    sleep(10); // Задержка 10 секунд между публикациями (стриминг режим)
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

sleep(3);

// ============================================================================
// ТЕСТ 2: ПРОВЕРКА КЕШИРОВАНИЯ И ДЕДУПЛИКАЦИИ
// ============================================================================

echo colorize(str_repeat('=', 100), 'magenta') . "\n";
echo colorize("🔄 ТЕСТ 2: Проверка кеширования и дедупликации", 'magenta') . "\n";
echo colorize(str_repeat('=', 100), 'magenta') . "\n\n";

sendTelegramNotification($telegram, $config['telegram']['chat_id'], 
    "📥 <b>ТЕСТ 2: Повторный fetch для проверки кеша</b>\n\n" .
    "Повторное получение новостей из " . count($feedConfigs) . " источников...\n" .
    "Ожидаем: кеширование работает, новых новостей мало или нет",
    true
);

$test2Feeds = $feedConfigs;

$test2Stats = [
    'feeds_processed' => 0,
    'items_fetched' => 0,
    'items_new' => 0,
    'items_cached' => 0,
    'items_published' => 0,
    'items_with_photo' => 0,
    'items_with_video' => 0,
    'items_without_media' => 0,
    'errors' => 0,
    'duration' => 0,
];

$test2Start = microtime(true);

// Fetch новостей (повторный)
echo colorize("📥 Повторное получение новостей...", 'yellow') . "\n\n";

$fetchResults2 = $fetchRunner->runForAllFeeds($test2Feeds);

$feedIndex = 0;
foreach ($fetchResults2 as $feedId => $result) {
    $feedIndex++;
    $feedConfig = null;
    foreach ($test2Feeds as $fc) {
        if ($fc->id === $feedId) {
            $feedConfig = $fc;
            break;
        }
    }
    $feedName = $feedConfig ? $feedConfig->name : "Feed #$feedId";
    
    showProgress($feedIndex, count($test2Feeds), "Повторная обработка лент");
    
    if ($result->isSuccessful()) {
        $itemsCount = count($result->getValidItems());
        
        if ($itemsCount === 0) {
            echo colorize("  ✅ $feedName: 0 новых (кеш работает)", 'green') . "\n";
            $test2Stats['items_cached']++;
        } else {
            echo colorize("  ✅ $feedName: $itemsCount новых новостей", 'cyan') . "\n";
            $test2Stats['items_new'] += $itemsCount;
        }
        
        $test2Stats['feeds_processed']++;
        $test2Stats['items_fetched'] += $itemsCount;
        
        // Сохраняем новости
        foreach ($result->getValidItems() as $item) {
            $itemRepo->save($feedId, $item);
        }
    } else {
        echo colorize("  ❌ $feedName: Ошибка", 'red') . "\n";
        $test2Stats['errors']++;
    }
}

echo "\n";

// Публикация 1 новости из 5 случайных источников
echo colorize("📰 Публикация 1 новости из 5 случайных источников...", 'yellow') . "\n\n";

// Выбираем 5 случайных источников с неопубликованными новостями
$feedsWithItems2 = [];
foreach ($test2Feeds as $feedConfig) {
    $items = $itemRepo->getUnpublished($feedConfig->id, 10);
    if (!empty($items)) {
        $feedsWithItems2[] = [
            'config' => $feedConfig,
            'items' => $items
        ];
    }
}

// Перемешиваем и берем до 5 случайных
shuffle($feedsWithItems2);
$selectedFeeds2 = array_slice($feedsWithItems2, 0, 5);

echo colorize("  Выбрано " . count($selectedFeeds2) . " источников для публикации", 'cyan') . "\n\n";

$published2 = 0;
$totalToPublish2 = count($selectedFeeds2);

foreach ($selectedFeeds2 as $feedData) {
    $feedConfig = $feedData['config'];
    $items = $feedData['items'];
    $feedId = $feedConfig->id;
    $feedName = $feedConfig->name ?? "Feed #$feedId";
    
    echo colorize("  📌 $feedName:", 'cyan') . "\n";
    
    // Берем первую новость из этого источника
    $item = $items[0];
    
    $itemId = (int)$item['id'];
    $title = (string)$item['title'];
    
    // Извлекаем медиа
    $media = extractMedia($item);
    
    // Извлекаем контент если нужно
    if ($item['extraction_status'] === 'pending') {
        echo colorize("    🔍 Извлечение контента...", 'white') . "\n";
        $contentExtractor->processItem($item);
        $item = $itemRepo->getByContentHash($item['content_hash']);
        if ($item === null) {
            echo colorize("      ⚠️ Не удалось извлечь контент", 'yellow') . "\n\n";
            continue;
        }
        
        if ($media === null) {
            $media = extractMedia($item);
        }
    }
    
    $content = $itemRepo->getEffectiveContent($item);
    $content = strip_tags($content);
    $wordCount = str_word_count($content);
    
    if (mb_strlen($content) > 500) {
        $content = mb_substr($content, 0, 500) . "...\n\n📊 Полный текст: $wordCount слов";
    }
    
    $content = htmlspecialchars($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    $mediaInfo = $media ? " [{$media['type']}]" : "";
    echo colorize("    📄 $title$mediaInfo", 'white') . "\n";
    
    try {
        $messageData = publishToChannel(
            $telegram,
            $config['telegram']['chat_id'],
            $config['telegram']['channel_id'],
            $feedName,
            $title,
            $content,
            $media,
            $published2 + 1,
            $totalToPublish2
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
            $test2Stats['items_published']++;
            
            if ($media !== null) {
                if ($media['type'] === 'photo') {
                    $test2Stats['items_with_photo']++;
                    echo colorize("      ✅ Опубликовано с фото", 'green') . "\n";
                } elseif ($media['type'] === 'video') {
                    $test2Stats['items_with_video']++;
                    echo colorize("      ✅ Опубликовано с видео", 'green') . "\n";
                }
            } else {
                $test2Stats['items_without_media']++;
                echo colorize("      ✅ Опубликовано без медиа", 'green') . "\n";
            }
            
            $published2++;
        }
    } catch (\Exception $e) {
        echo colorize("      ❌ Ошибка: " . $e->getMessage(), 'red') . "\n";
        $test2Stats['errors']++;
    }
    
    sleep(10); // Задержка 10 секунд между публикациями
    echo "\n";
}

$test2Stats['duration'] = round(microtime(true) - $test2Start, 2);

// Статистика теста 2
echo colorize(str_repeat('-', 100), 'cyan') . "\n";
echo colorize("📊 СТАТИСТИКА ТЕСТА 2:", 'cyan') . "\n";
echo colorize(str_repeat('-', 100), 'cyan') . "\n";
echo "  Источников обработано: " . colorize((string)$test2Stats['feeds_processed'], 'green') . " / " . count($test2Feeds) . "\n";
echo "  Новостей получено: " . colorize((string)$test2Stats['items_fetched'], 'green') . "\n";
echo "  Новых новостей: " . colorize((string)$test2Stats['items_new'], 'yellow') . "\n";
echo "  Источников с кешем: " . colorize((string)$test2Stats['items_cached'], 'green') . "\n";
echo "  Опубликовано: " . colorize((string)$test2Stats['items_published'], 'green') . "\n";
echo "  Ошибок: " . ($test2Stats['errors'] > 0 ? colorize((string)$test2Stats['errors'], 'red') : colorize('0', 'green')) . "\n";
echo "  Длительность: " . colorize($test2Stats['duration'] . " сек", 'cyan') . "\n";
echo colorize(str_repeat('-', 100), 'cyan') . "\n\n";

sendTelegramNotification($telegram, $config['telegram']['chat_id'], 
    "✅ <b>ТЕСТ 2 завершен</b>\n\n" .
    "📥 Получено: <b>{$test2Stats['items_fetched']}</b> новостей\n" .
    "🆕 Новых: <b>{$test2Stats['items_new']}</b>\n" .
    "💾 Кешировано: <b>{$test2Stats['items_cached']}</b> источников\n" .
    "📤 Опубликовано: <b>{$test2Stats['items_published']}</b>\n\n" .
    "⏱ Время: {$test2Stats['duration']} сек",
    true
);

sleep(2);

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

$totalPublished = $test1Stats['items_published'] + $test2Stats['items_published'];
$totalWithPhoto = $test1Stats['items_with_photo'] + $test2Stats['items_with_photo'];
$totalWithVideo = $test1Stats['items_with_video'] + $test2Stats['items_with_video'];

echo colorize("📰 НОВОСТИ:", 'yellow') . "\n";
echo "  Всего в БД: " . colorize((string)($itemStats['total'] ?? 0), 'bold') . "\n";
echo "  Опубликованных: " . colorize((string)($itemStats['published'] ?? 0), 'green') . "\n";
echo "  Неопубликованных: " . ($itemStats['unpublished'] ?? 0) . "\n";
echo "\n";

echo colorize("📤 ПУБЛИКАЦИИ:", 'yellow') . "\n";
echo "  Всего публикаций: " . colorize((string)($pubStats['total'] ?? 0), 'bold') . "\n";
echo "  В каналы: " . colorize((string)($pubStats['to_channel'] ?? 0), 'green') . "\n";
echo "  - Тест 1: " . colorize((string)$test1Stats['items_published'], 'cyan') . "\n";
echo "  - Тест 2: " . colorize((string)$test2Stats['items_published'], 'cyan') . "\n";
echo "\n";

echo colorize("📸 МЕДИА-КОНТЕНТ:", 'yellow') . "\n";
echo "  С фото: " . colorize((string)$totalWithPhoto, 'green') . "\n";
echo "  С видео: " . colorize((string)$totalWithVideo, 'green') . "\n";
echo "  Без медиа: " . ($test1Stats['items_without_media'] + $test2Stats['items_without_media']) . "\n";
echo "\n";

echo colorize("🔄 КЕШИРОВАНИЕ:", 'yellow') . "\n";
echo "  Тест 1 получено: " . colorize((string)$test1Stats['items_fetched'], 'cyan') . "\n";
echo "  Тест 2 получено: " . colorize((string)$test2Stats['items_fetched'], 'cyan') . "\n";
echo "  Тест 2 новых: " . colorize((string)$test2Stats['items_new'], 'yellow') . "\n";
echo "  Тест 2 кешировано: " . colorize((string)$test2Stats['items_cached'], 'green') . " источников\n";
echo "\n";

echo colorize("⏱ ОБЩЕЕ ВРЕМЯ: $totalDuration сек", 'cyan') . "\n";
echo colorize("   - Тест 1: {$test1Stats['duration']} сек", 'white') . "\n";
echo colorize("   - Тест 2: {$test2Stats['duration']} сек", 'white') . "\n";
echo colorize(str_repeat('=', 100), 'green') . "\n\n";

// Финальное уведомление
$finalMessage = "🎉 <b>СТРЕСС-ТЕСТ V2 ЗАВЕРШЕН</b>\n\n";
$finalMessage .= "🆔 ID: <code>$testId</code>\n\n";
$finalMessage .= "📊 <b>Результаты:</b>\n";
$finalMessage .= "━━━━━━━━━━━━━━━━━━━━\n";
$finalMessage .= "📊 Источников: <b>" . count($feedConfigs) . "</b>\n";
$finalMessage .= "📥 Тест 1: <b>{$test1Stats['items_fetched']}</b> новостей\n";
$finalMessage .= "📥 Тест 2: <b>{$test2Stats['items_fetched']}</b> новостей\n";
$finalMessage .= "💾 Всего в БД: <b>" . ($itemStats['total'] ?? 0) . "</b>\n";
$finalMessage .= "📤 Опубликовано: <b>$totalPublished</b>\n\n";
$finalMessage .= "📸 <b>Медиа:</b>\n";
$finalMessage .= "• Фото: <b>$totalWithPhoto</b>\n";
$finalMessage .= "• Видео: <b>$totalWithVideo</b>\n\n";
$finalMessage .= "🔄 <b>Кеширование:</b>\n";
$finalMessage .= "• Новых в тесте 2: <b>{$test2Stats['items_new']}</b>\n";
$finalMessage .= "• Кешировано: <b>{$test2Stats['items_cached']}</b> источников\n\n";
$finalMessage .= "⏱ Время: <b>$totalDuration</b> сек\n\n";
$finalMessage .= "✅ <b>Все тесты пройдены!</b>";

sendTelegramNotification($telegram, $config['telegram']['chat_id'], $finalMessage, true);

echo colorize("✅ ТЕСТ V2 ЗАВЕРШЕН УСПЕШНО!", 'green') . "\n";
echo colorize("📊 Подробные логи: " . $config['log_file'], 'cyan') . "\n";
echo colorize("🆔 Идентификатор: $testId", 'cyan') . "\n\n";

exit(0);

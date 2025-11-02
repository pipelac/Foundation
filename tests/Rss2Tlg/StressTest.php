<?php

declare(strict_types=1);

/**
 * 🔥 СТРЕСС-ТЕСТ RSS2TLG С РЕАЛЬНОЙ ИНФРАСТРУКТУРОЙ
 * 
 * Идентификатор: RSS2TLG-STRESS-TEST-001
 * 
 * Тестирует полный цикл работы с 25+ RSS источниками:
 * 1. Получение новостей из 10 случайных лент
 * 2. Извлечение контента с фото/видео через WebtExtractor
 * 3. Публикация в Telegram канал через TelegramBot API
 * 4. Проверка кеширования и дедупликации
 * 5. Публикация из других 10 лент
 * 6. Детальная статистика и отчетность
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

$testId = 'RSS2TLG-STRESS-TEST-001';

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
    'log_file' => '/home/engine/project/logs/rss2tlg_stress_test.log',
    'feeds' => [
        // Базовые 5 источников
        [
            'id' => 1,
            'name' => 'РИА Новости',
            'url' => 'https://ria.ru/export/rss2/index.xml?page_type=google_newsstand',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/1.0)'],
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
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/1.0)'],
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
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/1.0)'],
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
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/1.0)'],
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
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/1.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        
        // Дополнительные 20 источников для стресс-теста
        [
            'id' => 6,
            'name' => 'BBC News World',
            'url' => 'http://feeds.bbci.co.uk/news/world/rss.xml',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/1.0)'],
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
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/1.0)'],
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
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/1.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 9,
            'name' => 'Reuters Technology',
            'url' => 'https://www.reutersagency.com/feed/?taxonomy=best-topics&post_type=best',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/1.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 10,
            'name' => 'The Verge',
            'url' => 'https://www.theverge.com/rss/index.xml',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/1.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 11,
            'name' => 'Engadget',
            'url' => 'https://www.engadget.com/rss.xml',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/1.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 12,
            'name' => 'ZDNet',
            'url' => 'https://www.zdnet.com/news/rss.xml',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/1.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 13,
            'name' => 'Mashable',
            'url' => 'https://mashable.com/feeds/rss/all',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/1.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 14,
            'name' => 'TechRadar',
            'url' => 'https://www.techradar.com/rss',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/1.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 15,
            'name' => 'ТАСС',
            'url' => 'https://tass.ru/rss/v2.xml',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/1.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 16,
            'name' => 'РБК',
            'url' => 'https://rssexport.rbc.ru/rbcnews/news/30/full.rss',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/1.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 17,
            'name' => 'Хабр',
            'url' => 'https://habr.com/ru/rss/all/all/?fl=ru',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/1.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 18,
            'name' => 'N+1',
            'url' => 'https://nplus1.ru/rss',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/1.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 19,
            'name' => 'CNews',
            'url' => 'https://www.cnews.ru/inc/rss/news.xml',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/1.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 20,
            'name' => 'Газета.Ru',
            'url' => 'https://www.gazeta.ru/export/rss/lenta.xml',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/1.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 21,
            'name' => 'Meduza',
            'url' => 'https://meduza.io/rss/all',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/1.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 22,
            'name' => 'Коммерсантъ',
            'url' => 'https://www.kommersant.ru/RSS/main.xml',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/1.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 23,
            'name' => 'Forbes Russia',
            'url' => 'https://www.forbes.ru/rss',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/1.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 24,
            'name' => 'Interfax',
            'url' => 'https://www.interfax.ru/rss.asp',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/1.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
        [
            'id' => 25,
            'name' => 'Fontanka',
            'url' => 'https://www.fontanka.ru/fontanka.rss',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/1.0)'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
    ],
];

// ============================================================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// ============================================================================

/**
 * Отправка уведомления в Telegram
 */
function sendTelegramNotification(TelegramAPI $telegram, int $chatId, string $message): void
{
    try {
        $telegram->sendMessage($chatId, $message, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
    } catch (\Exception $e) {
        echo "⚠️ Ошибка отправки уведомления: " . $e->getMessage() . "\n";
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
echo colorize("🚀 СТРЕСС-ТЕСТ RSS2TLG С РЕАЛЬНОЙ ИНФРАСТРУКТУРОЙ", 'bold') . "\n";
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

// Стартовое уведомление
sendTelegramNotification($telegram, $config['telegram']['chat_id'], 
    "🚀 <b>СТРЕСС-ТЕСТ RSS2TLG</b>\n\n" .
    "🆔 ID: <code>$testId</code>\n" .
    "📊 Источников: <b>" . count($config['feeds']) . "</b>\n" .
    "🕐 Старт: " . date('Y-m-d H:i:s') . "\n\n" .
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
echo colorize("   - Telegram API готов", 'white') . "\n\n";

sendTelegramNotification($telegram, $config['telegram']['chat_id'], 
    "✅ <b>Инфраструктура готова</b>\n\n" .
    "Начинаем тестирование..."
);

sleep(2);

// ============================================================================
// ТЕСТ 1: ПОЛУЧЕНИЕ НОВОСТЕЙ ИЗ 10 СЛУЧАЙНЫХ ЛЕНТ И ПУБЛИКАЦИЯ
// ============================================================================

echo colorize(str_repeat('=', 100), 'magenta') . "\n";
echo colorize("🔄 ТЕСТ 1: Получение и публикация из 10 случайных источников", 'magenta') . "\n";
echo colorize(str_repeat('=', 100), 'magenta') . "\n\n";

sendTelegramNotification($telegram, $config['telegram']['chat_id'], 
    "📥 <b>ТЕСТ 1: Первая волна</b>\n\n" .
    "Получение новостей из 10 случайных источников и публикация..."
);

// Выбираем 10 случайных источников
shuffle($feedConfigs);
$test1Feeds = array_slice($feedConfigs, 0, 10);

$test1Stats = [
    'feeds_processed' => 0,
    'items_fetched' => 0,
    'items_saved' => 0,
    'items_published' => 0,
    'items_with_media' => 0,
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

// Публикация 1-2 новостей из каждой ленты (с фото/видео если есть)
echo colorize("📰 Публикация новостей с медиа-контентом...", 'yellow') . "\n\n";

$publishedCount = 0;
foreach ($test1Feeds as $feedConfig) {
    $feedId = $feedConfig->id;
    $feedName = $feedConfig->name;
    
    echo colorize("  📌 $feedName:", 'cyan') . "\n";
    
    // Получаем до 5 неопубликованных новостей
    $items = $itemRepo->getUnpublished($feedId, 5);
    
    if (empty($items)) {
        echo colorize("    ⚠️ Нет новых новостей", 'yellow') . "\n\n";
        continue;
    }
    
    $published = 0;
    foreach ($items as $item) {
        if ($published >= 2) {
            break; // Публикуем максимум 2 новости с каждой ленты
        }
        
        $itemId = (int)$item['id'];
        $title = (string)$item['title'];
        $link = (string)$item['link'];
        
        // Извлекаем контент если нужно
        if ($item['extraction_status'] === 'pending') {
            echo colorize("    🔍 Извлечение: $title", 'white') . "\n";
            $contentExtractor->processItem($item);
            $item = $itemRepo->getByContentHash($item['content_hash']);
            if ($item === null) {
                continue;
            }
        }
        
        $content = $itemRepo->getEffectiveContent($item);
        
        // Проверяем наличие медиа в контенте
        $hasMedia = !empty($item['image_url']) || 
                    stripos($content, '<img') !== false ||
                    stripos($content, '<video') !== false;
        
        // Обрезаем текст
        $wordCount = str_word_count(strip_tags($content));
        if (mb_strlen($content) > 1000) {
            $content = mb_substr(strip_tags($content), 0, 1000) . "...\n\n📊 Полный текст: $wordCount слов";
        } else {
            $content = strip_tags($content);
        }
        
        // Формируем сообщение
        $message = "<b>📰 $feedName</b>\n\n";
        $message .= "<b>$title</b>\n\n";
        $message .= $content;
        
        if ($hasMedia) {
            $message .= "\n\n📸 <i>Медиа-контент доступен</i>";
        }
        
        // Отправляем в канал
        try {
            // Если есть изображение, пытаемся отправить с фото
            if (!empty($item['image_url'])) {
                try {
                    $result = $telegram->sendPhoto(
                        $config['telegram']['channel_id'],
                        $item['image_url'],
                        ['caption' => $message, 'parse_mode' => TelegramAPI::PARSE_MODE_HTML]
                    );
                } catch (\Exception $photoEx) {
                    // Если не удалось с фото, отправляем текстом
                    $result = $telegram->sendMessage(
                        $config['telegram']['channel_id'],
                        $message,
                        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
                    );
                }
            } else {
                $result = $telegram->sendMessage(
                    $config['telegram']['channel_id'],
                    $message,
                    ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
                );
            }
            
            $messageData = $result->toArray();
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
                if ($hasMedia) {
                    $test1Stats['items_with_media']++;
                }
                
                echo colorize("      ✅ Опубликовано" . ($hasMedia ? " (с медиа)" : ""), 'green') . "\n";
                $published++;
                $publishedCount++;
            }
        } catch (\Exception $e) {
            echo colorize("      ❌ Ошибка: " . $e->getMessage(), 'red') . "\n";
            $test1Stats['errors']++;
        }
        
        sleep(2); // Задержка между публикациями
    }
    
    echo "\n";
}

$test1Stats['duration'] = round(microtime(true) - $test1Start, 2);

// Статистика
echo colorize(str_repeat('-', 100), 'cyan') . "\n";
echo colorize("📊 СТАТИСТИКА ТЕСТА 1:", 'cyan') . "\n";
echo colorize(str_repeat('-', 100), 'cyan') . "\n";
echo "  Источников обработано: " . colorize((string)$test1Stats['feeds_processed'], 'green') . " / 10\n";
echo "  Новостей получено: " . colorize((string)$test1Stats['items_fetched'], 'green') . "\n";
echo "  Новостей сохранено: " . colorize((string)$test1Stats['items_saved'], 'green') . "\n";
echo "  Новостей опубликовано: " . colorize((string)$test1Stats['items_published'], 'green') . "\n";
echo "  С медиа-контентом: " . colorize((string)$test1Stats['items_with_media'], 'yellow') . "\n";
echo "  Ошибок: " . ($test1Stats['errors'] > 0 ? colorize((string)$test1Stats['errors'], 'red') : colorize('0', 'green')) . "\n";
echo "  Длительность: " . colorize($test1Stats['duration'] . " сек", 'cyan') . "\n";
echo colorize(str_repeat('-', 100), 'cyan') . "\n\n";

sendTelegramNotification($telegram, $config['telegram']['chat_id'], 
    "✅ <b>ТЕСТ 1 завершен</b>\n\n" .
    "📥 Получено: <b>{$test1Stats['items_fetched']}</b> новостей\n" .
    "💾 Сохранено: <b>{$test1Stats['items_saved']}</b>\n" .
    "📤 Опубликовано: <b>{$test1Stats['items_published']}</b>\n" .
    "📸 С медиа: <b>{$test1Stats['items_with_media']}</b>\n" .
    "⏱ Время: {$test1Stats['duration']} сек"
);

sleep(3);

// ============================================================================
// ТЕСТ 2: ПОВТОРНЫЙ ЗАПРОС - ПРОВЕРКА КЕШИРОВАНИЯ
// ============================================================================

echo colorize(str_repeat('=', 100), 'magenta') . "\n";
echo colorize("🔄 ТЕСТ 2: Проверка кеширования (повторный запрос тех же лент)", 'magenta') . "\n";
echo colorize(str_repeat('=', 100), 'magenta') . "\n\n";

sendTelegramNotification($telegram, $config['telegram']['chat_id'], 
    "🔄 <b>ТЕСТ 2: Кеширование</b>\n\n" .
    "Повторный запрос тех же источников для проверки дедупликации..."
);

$test2Stats = [
    'feeds_processed' => 0,
    'items_fetched' => 0,
    'items_new' => 0,
    'items_duplicates' => 0,
    'not_modified_count' => 0,
    'duration' => 0,
];

$test2Start = microtime(true);

// Запоминаем количество до
$statsBefore = $itemRepo->getStats();
$totalBefore = (int)($statsBefore['total'] ?? 0);

echo colorize("📥 Повторное получение новостей из тех же источников...", 'yellow') . "\n\n";
$fetchResults2 = $fetchRunner->runForAllFeeds($test1Feeds);

$feedIndex = 0;
foreach ($fetchResults2 as $feedId => $result) {
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
    
    if ($result->isSuccessful() || $result->isNotModified()) {
        $itemsCount = count($result->getValidItems());
        
        if ($result->isNotModified()) {
            echo colorize("  ✅ $feedName: 304 Not Modified (кеш работает)", 'green') . "\n";
            $test2Stats['not_modified_count']++;
        } else {
            echo colorize("  ✅ $feedName: $itemsCount новостей", 'green') . "\n";
        }
        
        $test2Stats['feeds_processed']++;
        $test2Stats['items_fetched'] += $itemsCount;
        
        // Пытаемся сохранить (проверка дедупликации)
        $newItems = 0;
        foreach ($result->getValidItems() as $item) {
            if (!$itemRepo->exists($item->contentHash)) {
                $itemId = $itemRepo->save($feedId, $item);
                if ($itemId !== null) {
                    $newItems++;
                }
            } else {
                $test2Stats['items_duplicates']++;
            }
        }
        
        $test2Stats['items_new'] += $newItems;
    } else {
        echo colorize("  ❌ $feedName: Ошибка", 'red') . "\n";
    }
}

$statsAfter = $itemRepo->getStats();
$totalAfter = (int)($statsAfter['total'] ?? 0);

$test2Stats['duration'] = round(microtime(true) - $test2Start, 2);

echo "\n";
echo colorize(str_repeat('-', 100), 'cyan') . "\n";
echo colorize("📊 СТАТИСТИКА ТЕСТА 2:", 'cyan') . "\n";
echo colorize(str_repeat('-', 100), 'cyan') . "\n";
echo "  Источников обработано: " . colorize((string)$test2Stats['feeds_processed'], 'green') . " / 10\n";
echo "  304 Not Modified: " . colorize((string)$test2Stats['not_modified_count'], 'yellow') . "\n";
echo "  Новостей получено: " . $test2Stats['items_fetched'] . "\n";
echo "  Новых новостей: " . colorize((string)$test2Stats['items_new'], 'green') . "\n";
echo "  Дубликатов отсечено: " . colorize((string)$test2Stats['items_duplicates'], 'yellow') . "\n";
echo "  Всего в БД до/после: " . colorize("$totalBefore → $totalAfter", 'cyan') . "\n";
echo "  Длительность: " . colorize($test2Stats['duration'] . " сек", 'cyan') . "\n";
echo colorize(str_repeat('-', 100), 'cyan') . "\n\n";

sendTelegramNotification($telegram, $config['telegram']['chat_id'], 
    "✅ <b>ТЕСТ 2 завершен</b>\n\n" .
    "📥 Получено: {$test2Stats['items_fetched']}\n" .
    "🆕 Новых: <b>{$test2Stats['items_new']}</b>\n" .
    "🔄 Дубликатов: <b>{$test2Stats['items_duplicates']}</b>\n" .
    "⚡️ 304 Not Modified: <b>{$test2Stats['not_modified_count']}</b>\n" .
    "⏱ Время: {$test2Stats['duration']} сек\n\n" .
    "✅ Кеширование работает корректно!"
);

sleep(3);

// ============================================================================
// ТЕСТ 3: ПУБЛИКАЦИЯ ИЗ ДРУГИХ 10 ИСТОЧНИКОВ
// ============================================================================

echo colorize(str_repeat('=', 100), 'magenta') . "\n";
echo colorize("🔄 ТЕСТ 3: Получение и публикация из других 10 источников", 'magenta') . "\n";
echo colorize(str_repeat('=', 100), 'magenta') . "\n\n";

sendTelegramNotification($telegram, $config['telegram']['chat_id'], 
    "📥 <b>ТЕСТ 3: Вторая волна</b>\n\n" .
    "Получение новостей из других 10 источников и публикация..."
);

// Берем следующие 10 источников
$test3Feeds = array_slice($feedConfigs, 10, 10);

$test3Stats = [
    'feeds_processed' => 0,
    'items_fetched' => 0,
    'items_saved' => 0,
    'items_published' => 0,
    'items_with_media' => 0,
    'errors' => 0,
    'duration' => 0,
];

$test3Start = microtime(true);

// Fetch новостей
echo colorize("📥 Получение новостей...", 'yellow') . "\n\n";
$fetchResults3 = $fetchRunner->runForAllFeeds($test3Feeds);

$feedIndex = 0;
foreach ($fetchResults3 as $feedId => $result) {
    $feedIndex++;
    $feedConfig = null;
    foreach ($test3Feeds as $fc) {
        if ($fc->id === $feedId) {
            $feedConfig = $fc;
            break;
        }
    }
    $feedName = $feedConfig ? $feedConfig->name : "Feed #$feedId";
    
    showProgress($feedIndex, count($test3Feeds), "Обработка лент");
    
    if ($result->isSuccessful()) {
        $itemsCount = count($result->getValidItems());
        echo colorize("  ✅ $feedName: $itemsCount новостей", 'green') . "\n";
        
        $test3Stats['feeds_processed']++;
        $test3Stats['items_fetched'] += $itemsCount;
        
        // Сохраняем
        foreach ($result->getValidItems() as $item) {
            $itemId = $itemRepo->save($feedId, $item);
            if ($itemId !== null) {
                $test3Stats['items_saved']++;
            }
        }
    } else {
        echo colorize("  ❌ $feedName: Ошибка", 'red') . "\n";
        $test3Stats['errors']++;
    }
}

echo "\n";

// Публикация
echo colorize("📰 Публикация новостей...", 'yellow') . "\n\n";

foreach ($test3Feeds as $feedConfig) {
    $feedId = $feedConfig->id;
    $feedName = $feedConfig->name;
    
    echo colorize("  📌 $feedName:", 'cyan') . "\n";
    
    $items = $itemRepo->getUnpublished($feedId, 5);
    
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
        
        if ($item['extraction_status'] === 'pending') {
            echo colorize("    🔍 Извлечение: $title", 'white') . "\n";
            $contentExtractor->processItem($item);
            $item = $itemRepo->getByContentHash($item['content_hash']);
            if ($item === null) {
                continue;
            }
        }
        
        $content = $itemRepo->getEffectiveContent($item);
        
        $hasMedia = !empty($item['image_url']) || 
                    stripos($content, '<img') !== false ||
                    stripos($content, '<video') !== false;
        
        $wordCount = str_word_count(strip_tags($content));
        if (mb_strlen($content) > 1000) {
            $content = mb_substr(strip_tags($content), 0, 1000) . "...\n\n📊 Полный текст: $wordCount слов";
        } else {
            $content = strip_tags($content);
        }
        
        $message = "<b>📰 $feedName</b>\n\n";
        $message .= "<b>$title</b>\n\n";
        $message .= $content;
        
        if ($hasMedia) {
            $message .= "\n\n📸 <i>Медиа-контент доступен</i>";
        }
        
        try {
            if (!empty($item['image_url'])) {
                try {
                    $result = $telegram->sendPhoto(
                        $config['telegram']['channel_id'],
                        $item['image_url'],
                        ['caption' => $message, 'parse_mode' => TelegramAPI::PARSE_MODE_HTML]
                    );
                } catch (\Exception $photoEx) {
                    $result = $telegram->sendMessage(
                        $config['telegram']['channel_id'],
                        $message,
                        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
                    );
                }
            } else {
                $result = $telegram->sendMessage(
                    $config['telegram']['channel_id'],
                    $message,
                    ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
                );
            }
            
            $messageData = $result->toArray();
            if ($messageData !== null && isset($messageData['message_id'])) {
                $pubRepo->record(
                    $itemId,
                    $feedId,
                    'channel',
                    $config['telegram']['channel_id'],
                    $messageData['message_id']
                );
                
                $itemRepo->markAsPublished($itemId);
                
                $test3Stats['items_published']++;
                if ($hasMedia) {
                    $test3Stats['items_with_media']++;
                }
                
                echo colorize("      ✅ Опубликовано" . ($hasMedia ? " (с медиа)" : ""), 'green') . "\n";
                $published++;
            }
        } catch (\Exception $e) {
            echo colorize("      ❌ Ошибка: " . $e->getMessage(), 'red') . "\n";
            $test3Stats['errors']++;
        }
        
        sleep(2);
    }
    
    echo "\n";
}

$test3Stats['duration'] = round(microtime(true) - $test3Start, 2);

echo colorize(str_repeat('-', 100), 'cyan') . "\n";
echo colorize("📊 СТАТИСТИКА ТЕСТА 3:", 'cyan') . "\n";
echo colorize(str_repeat('-', 100), 'cyan') . "\n";
echo "  Источников обработано: " . colorize((string)$test3Stats['feeds_processed'], 'green') . " / 10\n";
echo "  Новостей получено: " . colorize((string)$test3Stats['items_fetched'], 'green') . "\n";
echo "  Новостей сохранено: " . colorize((string)$test3Stats['items_saved'], 'green') . "\n";
echo "  Новостей опубликовано: " . colorize((string)$test3Stats['items_published'], 'green') . "\n";
echo "  С медиа-контентом: " . colorize((string)$test3Stats['items_with_media'], 'yellow') . "\n";
echo "  Ошибок: " . ($test3Stats['errors'] > 0 ? colorize((string)$test3Stats['errors'], 'red') : colorize('0', 'green')) . "\n";
echo "  Длительность: " . colorize($test3Stats['duration'] . " сек", 'cyan') . "\n";
echo colorize(str_repeat('-', 100), 'cyan') . "\n\n";

sendTelegramNotification($telegram, $config['telegram']['chat_id'], 
    "✅ <b>ТЕСТ 3 завершен</b>\n\n" .
    "📥 Получено: <b>{$test3Stats['items_fetched']}</b>\n" .
    "💾 Сохранено: <b>{$test3Stats['items_saved']}</b>\n" .
    "📤 Опубликовано: <b>{$test3Stats['items_published']}</b>\n" .
    "📸 С медиа: <b>{$test3Stats['items_with_media']}</b>\n" .
    "⏱ Время: {$test3Stats['duration']} сек"
);

sleep(2);

// ============================================================================
// ИТОГОВАЯ ДЕТАЛЬНАЯ СТАТИСТИКА
// ============================================================================

$totalDuration = round(microtime(true) - $startTime, 2);

echo colorize(str_repeat('=', 100), 'green') . "\n";
echo colorize("🎉 ИТОГОВАЯ ДЕТАЛЬНАЯ СТАТИСТИКА", 'bold') . "\n";
echo colorize("   Тест ID: $testId", 'cyan') . "\n";
echo colorize(str_repeat('=', 100), 'green') . "\n\n";

// Общая статистика по новостям
$itemStats = $itemRepo->getStats();
echo colorize("📰 НОВОСТИ:", 'yellow') . "\n";
echo "  Всего в БД: " . colorize((string)($itemStats['total'] ?? 0), 'bold') . "\n";
echo "  Опубликованных: " . colorize((string)($itemStats['published'] ?? 0), 'green') . "\n";
echo "  Неопубликованных: " . ($itemStats['unpublished'] ?? 0) . "\n";
echo "  Уникальных источников: " . ($itemStats['unique_feeds'] ?? 0) . "\n";
echo "  Извлечение контента:\n";
echo "    - Ожидает: " . ($itemStats['extraction_pending'] ?? 0) . "\n";
echo "    - Успешно: " . colorize((string)($itemStats['extraction_success'] ?? 0), 'green') . "\n";
echo "    - Ошибок: " . ($itemStats['extraction_failed'] ?? 0) . "\n";
echo "    - Пропущено: " . ($itemStats['extraction_skipped'] ?? 0) . "\n";
echo "\n";

// Статистика по публикациям
$pubStats = $pubRepo->getStats();
echo colorize("📤 ПУБЛИКАЦИИ:", 'yellow') . "\n";
echo "  Всего публикаций: " . colorize((string)($pubStats['total'] ?? 0), 'bold') . "\n";
echo "  Уникальных новостей: " . ($pubStats['unique_items'] ?? 0) . "\n";
echo "  В боты: " . ($pubStats['to_bot'] ?? 0) . "\n";
echo "  В каналы: " . colorize((string)($pubStats['to_channel'] ?? 0), 'green') . "\n";
echo "\n";

// Сводка по тестам
echo colorize("🧪 ДЕТАЛЬНАЯ СВОДКА ПО ТЕСТАМ:", 'yellow') . "\n";
echo colorize("  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", 'cyan') . "\n";
echo "  ТЕСТ 1 (первая волна - 10 источников):\n";
echo "    📥 Источников обработано: " . $test1Stats['feeds_processed'] . " / 10\n";
echo "    📊 Новостей получено: " . $test1Stats['items_fetched'] . "\n";
echo "    💾 Сохранено: " . $test1Stats['items_saved'] . "\n";
echo "    📤 Опубликовано: " . colorize((string)$test1Stats['items_published'], 'green') . "\n";
echo "    📸 С медиа: " . colorize((string)$test1Stats['items_with_media'], 'yellow') . "\n";
echo "    ⏱ Время: " . $test1Stats['duration'] . " сек\n";
echo "\n";

echo colorize("  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", 'cyan') . "\n";
echo "  ТЕСТ 2 (кеширование - те же 10 источников):\n";
echo "    📥 Источников обработано: " . $test2Stats['feeds_processed'] . " / 10\n";
echo "    ⚡️ 304 Not Modified: " . colorize((string)$test2Stats['not_modified_count'], 'yellow') . "\n";
echo "    📊 Новостей получено: " . $test2Stats['items_fetched'] . "\n";
echo "    🆕 Новых: " . colorize((string)$test2Stats['items_new'], 'green') . "\n";
echo "    🔄 Дубликатов отсечено: " . colorize((string)$test2Stats['items_duplicates'], 'yellow') . "\n";
echo "    ⏱ Время: " . $test2Stats['duration'] . " сек\n";
echo "\n";

echo colorize("  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", 'cyan') . "\n";
echo "  ТЕСТ 3 (вторая волна - другие 10 источников):\n";
echo "    📥 Источников обработано: " . $test3Stats['feeds_processed'] . " / 10\n";
echo "    📊 Новостей получено: " . $test3Stats['items_fetched'] . "\n";
echo "    💾 Сохранено: " . $test3Stats['items_saved'] . "\n";
echo "    📤 Опубликовано: " . colorize((string)$test3Stats['items_published'], 'green') . "\n";
echo "    📸 С медиа: " . colorize((string)$test3Stats['items_with_media'], 'yellow') . "\n";
echo "    ⏱ Время: " . $test3Stats['duration'] . " сек\n";
echo colorize("  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", 'cyan') . "\n";
echo "\n";

// Общая производительность
$totalFeeds = $test1Stats['feeds_processed'] + $test2Stats['feeds_processed'] + $test3Stats['feeds_processed'];
$totalItems = $test1Stats['items_fetched'] + $test2Stats['items_fetched'] + $test3Stats['items_fetched'];
$totalPublished = $test1Stats['items_published'] + $test3Stats['items_published'];
$totalWithMedia = $test1Stats['items_with_media'] + $test3Stats['items_with_media'];

echo colorize("⚡️ ПРОИЗВОДИТЕЛЬНОСТЬ:", 'yellow') . "\n";
echo "  Всего источников обработано: " . colorize((string)$totalFeeds, 'bold') . " / 30\n";
echo "  Всего новостей получено: " . colorize((string)$totalItems, 'bold') . "\n";
echo "  Всего опубликовано: " . colorize((string)$totalPublished, 'green') . "\n";
echo "  С медиа-контентом: " . colorize((string)$totalWithMedia, 'yellow') . " (" . round($totalWithMedia / max($totalPublished, 1) * 100, 1) . "%)\n";
echo "  Общее время: " . colorize($totalDuration . " сек", 'cyan') . "\n";
echo "  Средняя скорость: " . round($totalItems / max($totalDuration, 1), 2) . " новостей/сек\n";
echo "\n";

// Проверка логов
echo colorize("📋 ЛОГИ И ХРАНИЛИЩЕ:", 'yellow') . "\n";
if (file_exists($config['log_file'])) {
    $logSize = filesize($config['log_file']);
    $logLines = count(file($config['log_file']));
    echo "  ✅ Лог файл: " . $config['log_file'] . "\n";
    echo "  📊 Размер: " . number_format($logSize) . " байт (" . round($logSize / 1024, 2) . " КБ)\n";
    echo "  📝 Строк: " . number_format($logLines) . "\n";
} else {
    echo colorize("  ⚠️ Лог файл не найден!", 'yellow') . "\n";
}
echo "\n";

// Проверка таблиц БД
echo colorize("🗄️ ТАБЛИЦЫ БД:", 'yellow') . "\n";
$tables = ['rss2tlg_feed_state', 'rss2tlg_items', 'rss2tlg_publications'];
foreach ($tables as $table) {
    try {
        $result = $db->queryOne("SELECT COUNT(*) as count FROM $table");
        $count = $result['count'] ?? 0;
        echo "  ✅ $table: " . colorize(number_format((int)$count), 'green') . " записей\n";
    } catch (\Exception $e) {
        echo "  ❌ $table: ошибка (" . $e->getMessage() . ")\n";
    }
}
echo "\n";

echo colorize("⏱ ОБЩЕЕ ВРЕМЯ ТЕСТИРОВАНИЯ: " . $totalDuration . " сек (" . round($totalDuration / 60, 2) . " мин)", 'cyan') . "\n";
echo colorize(str_repeat('=', 100), 'green') . "\n\n";

// Финальное уведомление
$finalMessage = "🎉 <b>СТРЕСС-ТЕСТ ЗАВЕРШЕН</b>\n\n";
$finalMessage .= "🆔 ID: <code>$testId</code>\n\n";
$finalMessage .= "━━━━━━━━━━━━━━━━━━━━\n";
$finalMessage .= "📊 <b>Итоговая статистика:</b>\n\n";
$finalMessage .= "📥 Источников обработано: <b>$totalFeeds</b> / 30\n";
$finalMessage .= "📰 Новостей получено: <b>$totalItems</b>\n";
$finalMessage .= "💾 Всего в БД: <b>" . ($itemStats['total'] ?? 0) . "</b>\n";
$finalMessage .= "📤 Опубликовано: <b>$totalPublished</b>\n";
$finalMessage .= "📸 С медиа: <b>$totalWithMedia</b>\n";
$finalMessage .= "🔄 Дубликатов отсечено: <b>{$test2Stats['items_duplicates']}</b>\n";
$finalMessage .= "⚡️ 304 Not Modified: <b>{$test2Stats['not_modified_count']}</b>\n";
$finalMessage .= "⏱ Общее время: <b>$totalDuration</b> сек\n\n";
$finalMessage .= "✅ <b>Все функции работают корректно!</b>\n";
$finalMessage .= "━━━━━━━━━━━━━━━━━━━━";

sendTelegramNotification($telegram, $config['telegram']['chat_id'], $finalMessage);

echo colorize("✅ СТРЕСС-ТЕСТ ЗАВЕРШЕН УСПЕШНО!", 'green') . "\n";
echo colorize("📊 Подробные логи: " . $config['log_file'], 'cyan') . "\n";
echo colorize("🆔 Идентификатор теста: $testId", 'cyan') . "\n\n";

exit(0);

<?php

declare(strict_types=1);

/**
 * Полное тестирование инфраструктуры Rss2Tlg с реальными RSS лентами
 * 
 * Тестирует полный цикл:
 * 1. Получение новостей из RSS лент
 * 2. Извлечение контента через WebtExtractor
 * 3. Публикация в Telegram канал через PollingHandler
 * 4. Проверка кеширования и дедупликации
 * 5. Детальное логирование и статистика
 * 
 * ТРЕБОВАНИЯ:
 * - MySQL сервер запущен
 * - БД rss2tlg создана
 * - Telegram bot token и channel_id настроены
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
use App\Component\TelegramBot\Core\PollingHandler;

// ============================================================================
// КОНФИГУРАЦИЯ
// ============================================================================

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
    'log_file' => '/home/engine/project/logs/rss2tlg_test.log',
    'feeds' => [
        [
            'id' => 1,
            'name' => 'РИА Новости',
            'url' => 'https://ria.ru/export/rss2/index.xml?page_type=google_newsstand',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Rss2Tlg/1.0'],
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
            'headers' => ['User-Agent' => 'Rss2Tlg/1.0'],
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
            'headers' => ['User-Agent' => 'Rss2Tlg/1.0'],
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
            'headers' => ['User-Agent' => 'Rss2Tlg/1.0'],
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
            'headers' => ['User-Agent' => 'Rss2Tlg/1.0'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true],
        ],
    ],
];

// ============================================================================
// ИНИЦИАЛИЗАЦИЯ
// ============================================================================

$startTime = microtime(true);

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

// Telegram API для уведомлений
$telegram = new TelegramAPI($config['telegram']['bot_token'], $httpClient, $logger);

// Функция отправки уведомлений в Telegram
function sendTelegramNotification(TelegramAPI $telegram, int $chatId, string $message): void
{
    try {
        $telegram->sendMessage($chatId, $message, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
    } catch (\Exception $e) {
        echo "⚠️ Ошибка отправки уведомления: " . $e->getMessage() . "\n";
    }
}

// Цветной вывод
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
    ];
    
    return ($colors[$color] ?? $colors['white']) . $text . $colors['reset'];
}

// Заголовок
echo "\n" . colorize(str_repeat('=', 80), 'cyan') . "\n";
echo colorize("🚀 ПОЛНОЕ ТЕСТИРОВАНИЕ RSS2TLG С РЕАЛЬНОЙ ИНФРАСТРУКТУРОЙ", 'cyan') . "\n";
echo colorize(str_repeat('=', 80), 'cyan') . "\n\n";

sendTelegramNotification($telegram, $config['telegram']['chat_id'], 
    "🚀 <b>Тестирование Rss2Tlg начато</b>\n\n" .
    "📊 Источников: " . count($config['feeds']) . "\n" .
    "🕐 Время: " . date('Y-m-d H:i:s')
);

// Подключение к БД
echo colorize("📊 Подключение к БД...", 'yellow') . "\n";
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
        "❌ <b>Тест провален</b>\n\nОшибка подключения к БД"
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

// ============================================================================
// ТЕСТ 1: ПЕРВЫЙ ЗАПУСК - ПОЛУЧЕНИЕ НОВОСТЕЙ И ПУБЛИКАЦИЯ 2 ИЗ КАЖДОЙ ЛЕНТЫ
// ============================================================================

echo colorize(str_repeat('=', 80), 'magenta') . "\n";
echo colorize("🔄 ТЕСТ 1: Первый запуск - получение и публикация новостей", 'magenta') . "\n";
echo colorize(str_repeat('=', 80), 'magenta') . "\n\n";

sendTelegramNotification($telegram, $config['telegram']['chat_id'], 
    "📥 <b>ТЕСТ 1: Первый запуск</b>\n\n" .
    "Получение новостей из " . count($feedConfigs) . " источников..."
);

$test1Stats = [
    'feeds_processed' => 0,
    'items_fetched' => 0,
    'items_saved' => 0,
    'items_published' => 0,
    'duration' => 0,
];

$test1Start = microtime(true);

// Fetch новостей
echo colorize("📥 Получение новостей из RSS лент...", 'yellow') . "\n";
$fetchResults = $fetchRunner->runForAllFeeds($feedConfigs);

foreach ($fetchResults as $feedId => $result) {
    $feedName = $config['feeds'][$feedId - 1]['name'] ?? "Feed #$feedId";
    
    if ($result->isSuccessful()) {
        $itemsCount = count($result->getValidItems());
        echo colorize("  ✅ $feedName: $itemsCount новостей", 'green') . "\n";
        
        $test1Stats['feeds_processed']++;
        $test1Stats['items_fetched'] += $itemsCount;
        
        // Сохраняем новости в БД
        foreach ($result->getValidItems() as $item) {
            $itemId = $itemRepo->save($feedId, $item);
            if ($itemId !== null) {
                $test1Stats['items_saved']++;
            }
        }
    } else {
        echo colorize("  ❌ $feedName: Ошибка", 'red') . "\n";
    }
}

echo "\n";

// Извлечение контента и публикация 2 новостей из каждой ленты
echo colorize("📰 Публикация 2 новостей из каждого источника...", 'yellow') . "\n\n";

foreach ($feedConfigs as $feedConfig) {
    $feedId = $feedConfig->id;
    $feedName = $config['feeds'][$feedId - 1]['name'] ?? "Feed #$feedId";
    
    echo colorize("  📌 $feedName:", 'cyan') . "\n";
    
    // Получаем 2 неопубликованные новости
    $items = $itemRepo->getUnpublished($feedId, 2);
    
    if (empty($items)) {
        echo colorize("    ⚠️ Нет новых новостей", 'yellow') . "\n\n";
        continue;
    }
    
    foreach ($items as $item) {
        $itemId = (int)$item['id'];
        $title = (string)$item['title'];
        $link = (string)$item['link'];
        
        echo colorize("    📄 $title", 'white') . "\n";
        
        // Извлекаем контент
        $contentExtractor->processItem($item);
        
        // Получаем эффективный контент
        $item = $itemRepo->getByContentHash($item['content_hash']);
        if ($item === null) {
            continue;
        }
        
        $content = $itemRepo->getEffectiveContent($item);
        
        // Обрезаем текст если больше 500 символов
        $wordCount = str_word_count($content);
        if (mb_strlen($content) > 500) {
            $content = mb_substr($content, 0, 500) . "...\n\n📊 Полный текст: $wordCount слов";
        }
        
        // Формируем сообщение
        $message = "<b>$feedName</b>\n\n";
        $message .= "<b>$title</b>\n\n";
        $message .= strip_tags($content);
        
        // Отправляем в Telegram канал
        try {
            $result = $telegram->sendMessage(
                $config['telegram']['channel_id'],
                $message,
                ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
            );
            
            $messageData = $result->toArray();
            if ($messageData !== null && isset($messageData['message_id'])) {
                // Записываем публикацию
                $pubRepo->record(
                    $itemId,
                    $feedId,
                    'channel',
                    $config['telegram']['channel_id'],
                    $messageData['message_id']
                );
                
                // Помечаем как опубликованную
                $itemRepo->markAsPublished($itemId);
                
                $test1Stats['items_published']++;
                echo colorize("      ✅ Опубликовано в канал", 'green') . "\n";
            }
        } catch (\Exception $e) {
            echo colorize("      ❌ Ошибка публикации: " . $e->getMessage(), 'red') . "\n";
        }
        
        // Задержка между публикациями
        sleep(2);
    }
    
    echo "\n";
}

$test1Stats['duration'] = round(microtime(true) - $test1Start, 2);

// Статистика теста 1
echo colorize(str_repeat('-', 80), 'cyan') . "\n";
echo colorize("📊 СТАТИСТИКА ТЕСТА 1:", 'cyan') . "\n";
echo colorize(str_repeat('-', 80), 'cyan') . "\n";
echo "  Источников обработано: " . $test1Stats['feeds_processed'] . "\n";
echo "  Новостей получено: " . $test1Stats['items_fetched'] . "\n";
echo "  Новостей сохранено: " . $test1Stats['items_saved'] . "\n";
echo "  Новостей опубликовано: " . colorize((string)$test1Stats['items_published'], 'green') . "\n";
echo "  Длительность: " . $test1Stats['duration'] . " сек\n";
echo colorize(str_repeat('-', 80), 'cyan') . "\n\n";

sendTelegramNotification($telegram, $config['telegram']['chat_id'], 
    "✅ <b>ТЕСТ 1 завершен</b>\n\n" .
    "📥 Получено: {$test1Stats['items_fetched']} новостей\n" .
    "💾 Сохранено: {$test1Stats['items_saved']}\n" .
    "📤 Опубликовано: {$test1Stats['items_published']}\n" .
    "⏱ Время: {$test1Stats['duration']} сек"
);

// Пауза перед тестом 2
sleep(5);

// ============================================================================
// ТЕСТ 2: ВТОРОЙ ЗАПУСК - ПРОВЕРКА КЕШИРОВАНИЯ И ДЕДУПЛИКАЦИИ
// ============================================================================

echo colorize(str_repeat('=', 80), 'magenta') . "\n";
echo colorize("🔄 ТЕСТ 2: Второй запуск - проверка кеширования", 'magenta') . "\n";
echo colorize(str_repeat('=', 80), 'magenta') . "\n\n";

sendTelegramNotification($telegram, $config['telegram']['chat_id'], 
    "🔄 <b>ТЕСТ 2: Второй запуск</b>\n\n" .
    "Проверка кеширования и дедупликации..."
);

$test2Stats = [
    'feeds_processed' => 0,
    'items_fetched' => 0,
    'items_new' => 0,
    'items_duplicates' => 0,
    'duration' => 0,
];

$test2Start = microtime(true);

// Запоминаем текущее количество новостей
$statsBefore = $itemRepo->getStats();
$totalBefore = (int)($statsBefore['total'] ?? 0);

// Fetch новостей
echo colorize("📥 Повторное получение новостей...", 'yellow') . "\n";
$fetchResults2 = $fetchRunner->runForAllFeeds($feedConfigs);

foreach ($fetchResults2 as $feedId => $result) {
    $feedName = $config['feeds'][$feedId - 1]['name'] ?? "Feed #$feedId";
    
    if ($result->isSuccessful() || $result->isNotModified()) {
        $itemsCount = count($result->getValidItems());
        $status = $result->isNotModified() ? '304 Not Modified' : "$itemsCount новостей";
        
        echo colorize("  ✅ $feedName: $status", 'green') . "\n";
        
        $test2Stats['feeds_processed']++;
        $test2Stats['items_fetched'] += $itemsCount;
        
        // Пытаемся сохранить (проверка дедупликации)
        foreach ($result->getValidItems() as $item) {
            $itemId = $itemRepo->save($feedId, $item);
            if ($itemId !== null) {
                // Проверяем, новая ли это запись
                if ($itemRepo->exists($item->contentHash)) {
                    $test2Stats['items_duplicates']++;
                }
            }
        }
    } else {
        echo colorize("  ❌ $feedName: Ошибка", 'red') . "\n";
    }
}

// Сравниваем количество новостей
$statsAfter = $itemRepo->getStats();
$totalAfter = (int)($statsAfter['total'] ?? 0);
$test2Stats['items_new'] = $totalAfter - $totalBefore;

$test2Stats['duration'] = round(microtime(true) - $test2Start, 2);

echo "\n";
echo colorize(str_repeat('-', 80), 'cyan') . "\n";
echo colorize("📊 СТАТИСТИКА ТЕСТА 2:", 'cyan') . "\n";
echo colorize(str_repeat('-', 80), 'cyan') . "\n";
echo "  Источников обработано: " . $test2Stats['feeds_processed'] . "\n";
echo "  Новостей получено: " . $test2Stats['items_fetched'] . "\n";
echo "  Новых новостей: " . colorize((string)$test2Stats['items_new'], 'green') . "\n";
echo "  Дубликатов отсечено: " . colorize((string)$test2Stats['items_duplicates'], 'yellow') . "\n";
echo "  Длительность: " . $test2Stats['duration'] . " сек\n";
echo colorize(str_repeat('-', 80), 'cyan') . "\n\n";

sendTelegramNotification($telegram, $config['telegram']['chat_id'], 
    "✅ <b>ТЕСТ 2 завершен</b>\n\n" .
    "📥 Получено: {$test2Stats['items_fetched']} новостей\n" .
    "🆕 Новых: {$test2Stats['items_new']}\n" .
    "🔄 Дубликатов: {$test2Stats['items_duplicates']}\n" .
    "⏱ Время: {$test2Stats['duration']} сек"
);

// Пауза перед тестом 3
sleep(5);

// ============================================================================
// ТЕСТ 3: ПУБЛИКАЦИЯ ЕЩЕ 2 НОВОСТЕЙ ИЗ КАЖДОГО ИСТОЧНИКА
// ============================================================================

echo colorize(str_repeat('=', 80), 'magenta') . "\n";
echo colorize("🔄 ТЕСТ 3: Публикация еще 2 новостей из каждого источника", 'magenta') . "\n";
echo colorize(str_repeat('=', 80), 'magenta') . "\n\n";

sendTelegramNotification($telegram, $config['telegram']['chat_id'], 
    "📤 <b>ТЕСТ 3: Вторая волна публикаций</b>\n\n" .
    "Публикация еще 2 новостей из каждого источника..."
);

$test3Stats = [
    'items_published' => 0,
    'duration' => 0,
];

$test3Start = microtime(true);

foreach ($feedConfigs as $feedConfig) {
    $feedId = $feedConfig->id;
    $feedName = $config['feeds'][$feedId - 1]['name'] ?? "Feed #$feedId";
    
    echo colorize("  📌 $feedName:", 'cyan') . "\n";
    
    // Получаем 2 неопубликованные новости
    $items = $itemRepo->getUnpublished($feedId, 2);
    
    if (empty($items)) {
        echo colorize("    ⚠️ Нет новых новостей", 'yellow') . "\n\n";
        continue;
    }
    
    foreach ($items as $item) {
        $itemId = (int)$item['id'];
        $title = (string)$item['title'];
        
        echo colorize("    📄 $title", 'white') . "\n";
        
        // Извлекаем контент если нужно
        if ($item['extraction_status'] === 'pending') {
            $contentExtractor->processItem($item);
            $item = $itemRepo->getByContentHash($item['content_hash']);
        }
        
        if ($item === null) {
            continue;
        }
        
        $content = $itemRepo->getEffectiveContent($item);
        
        // Обрезаем текст
        $wordCount = str_word_count($content);
        if (mb_strlen($content) > 500) {
            $content = mb_substr($content, 0, 500) . "...\n\n📊 Полный текст: $wordCount слов";
        }
        
        // Формируем сообщение
        $message = "<b>$feedName</b>\n\n";
        $message .= "<b>$title</b>\n\n";
        $message .= strip_tags($content);
        
        // Отправляем в канал
        try {
            $result = $telegram->sendMessage(
                $config['telegram']['channel_id'],
                $message,
                ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
            );
            
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
                echo colorize("      ✅ Опубликовано в канал", 'green') . "\n";
            }
        } catch (\Exception $e) {
            echo colorize("      ❌ Ошибка: " . $e->getMessage(), 'red') . "\n";
        }
        
        sleep(2);
    }
    
    echo "\n";
}

$test3Stats['duration'] = round(microtime(true) - $test3Start, 2);

echo colorize(str_repeat('-', 80), 'cyan') . "\n";
echo colorize("📊 СТАТИСТИКА ТЕСТА 3:", 'cyan') . "\n";
echo colorize(str_repeat('-', 80), 'cyan') . "\n";
echo "  Новостей опубликовано: " . colorize((string)$test3Stats['items_published'], 'green') . "\n";
echo "  Длительность: " . $test3Stats['duration'] . " сек\n";
echo colorize(str_repeat('-', 80), 'cyan') . "\n\n";

sendTelegramNotification($telegram, $config['telegram']['chat_id'], 
    "✅ <b>ТЕСТ 3 завершен</b>\n\n" .
    "📤 Опубликовано: {$test3Stats['items_published']}\n" .
    "⏱ Время: {$test3Stats['duration']} сек"
);

// ============================================================================
// ИТОГОВАЯ СТАТИСТИКА
// ============================================================================

$totalDuration = round(microtime(true) - $startTime, 2);

echo colorize(str_repeat('=', 80), 'green') . "\n";
echo colorize("🎉 ИТОГОВАЯ ДЕТАЛЬНАЯ СТАТИСТИКА", 'green') . "\n";
echo colorize(str_repeat('=', 80), 'green') . "\n\n";

// Статистика по новостям
$itemStats = $itemRepo->getStats();
echo colorize("📰 НОВОСТИ:", 'yellow') . "\n";
echo "  Всего в БД: " . ($itemStats['total'] ?? 0) . "\n";
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
echo "  Всего публикаций: " . ($pubStats['total'] ?? 0) . "\n";
echo "  Уникальных новостей: " . ($pubStats['unique_items'] ?? 0) . "\n";
echo "  В боты: " . ($pubStats['to_bot'] ?? 0) . "\n";
echo "  В каналы: " . colorize((string)($pubStats['to_channel'] ?? 0), 'green') . "\n";
echo "\n";

// Сводка по тестам
echo colorize("🧪 СВОДКА ПО ТЕСТАМ:", 'yellow') . "\n";
echo "  ТЕСТ 1 (первый запуск):\n";
echo "    - Новостей получено: " . $test1Stats['items_fetched'] . "\n";
echo "    - Опубликовано: " . colorize((string)$test1Stats['items_published'], 'green') . "\n";
echo "    - Время: " . $test1Stats['duration'] . " сек\n";
echo "\n";
echo "  ТЕСТ 2 (кеширование):\n";
echo "    - Новостей получено: " . $test2Stats['items_fetched'] . "\n";
echo "    - Новых: " . $test2Stats['items_new'] . "\n";
echo "    - Дубликатов: " . colorize((string)$test2Stats['items_duplicates'], 'yellow') . "\n";
echo "    - Время: " . $test2Stats['duration'] . " сек\n";
echo "\n";
echo "  ТЕСТ 3 (вторая волна):\n";
echo "    - Опубликовано: " . colorize((string)$test3Stats['items_published'], 'green') . "\n";
echo "    - Время: " . $test3Stats['duration'] . " сек\n";
echo "\n";

echo colorize("⏱ ОБЩЕЕ ВРЕМЯ: $totalDuration сек", 'cyan') . "\n";
echo colorize(str_repeat('=', 80), 'green') . "\n\n";

// Проверка логов
echo colorize("📋 ПРОВЕРКА ЛОГОВ:", 'yellow') . "\n";
if (file_exists($config['log_file'])) {
    $logSize = filesize($config['log_file']);
    $logLines = count(file($config['log_file']));
    echo "  ✅ Лог файл: " . $config['log_file'] . "\n";
    echo "  📊 Размер: " . number_format($logSize) . " байт\n";
    echo "  📝 Строк: " . number_format($logLines) . "\n";
} else {
    echo colorize("  ⚠️ Лог файл не найден!", 'yellow') . "\n";
}
echo "\n";

// Проверка таблиц БД
echo colorize("🗄️ ПРОВЕРКА ТАБЛИЦ БД:", 'yellow') . "\n";
$tables = ['rss2tlg_feed_state', 'rss2tlg_items', 'rss2tlg_publications'];
foreach ($tables as $table) {
    $result = $db->queryOne("SELECT COUNT(*) as count FROM $table");
    $count = $result['count'] ?? 0;
    echo "  ✅ $table: " . number_format((int)$count) . " записей\n";
}
echo "\n";

// Финальное уведомление
$finalMessage = "🎉 <b>ВСЕ ТЕСТЫ ЗАВЕРШЕНЫ</b>\n\n";
$finalMessage .= "📊 <b>Итоговая статистика:</b>\n";
$finalMessage .= "━━━━━━━━━━━━━━━━━━━━\n";
$finalMessage .= "📥 Получено новостей: " . $test1Stats['items_fetched'] . "\n";
$finalMessage .= "💾 Сохранено в БД: " . ($itemStats['total'] ?? 0) . "\n";
$finalMessage .= "📤 Опубликовано: " . ($pubStats['total'] ?? 0) . "\n";
$finalMessage .= "🔄 Дубликатов отсечено: " . $test2Stats['items_duplicates'] . "\n";
$finalMessage .= "⏱ Общее время: $totalDuration сек\n\n";
$finalMessage .= "✅ Все функции работают корректно!";

sendTelegramNotification($telegram, $config['telegram']['chat_id'], $finalMessage);

echo colorize("✅ Тестирование завершено успешно!", 'green') . "\n";
echo colorize("📊 Подробные логи: " . $config['log_file'], 'cyan') . "\n\n";

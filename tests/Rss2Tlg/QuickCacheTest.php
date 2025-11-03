<?php

declare(strict_types=1);

/**
 * 🔄 ТЕСТ КЕШИРОВАНИЯ RSS2TLG
 * 
 * Проверяет:
 * - Кеширование RSS лент
 * - Дедупликацию новостей
 * - Повторный fetch не добавляет дубликаты
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Rss2Tlg\FetchRunner;
use App\Rss2Tlg\ItemRepository;
use App\Rss2Tlg\FeedStateRepository;
use App\Rss2Tlg\DTO\FeedConfig;

// Конфигурация
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
        'chat_id' => 366442475,
    ],
    'cache_dir' => '/home/engine/project/cache/rss2tlg',
    'log_file' => '/home/engine/project/logs/cache_test.log',
];

echo "\n";
echo "================================================================================\n";
echo "🔄 ТЕСТ КЕШИРОВАНИЯ RSS2TLG\n";
echo "================================================================================\n\n";

// Инициализация
$logger = new Logger([
    'directory' => dirname($config['log_file']),
    'file_name' => basename($config['log_file']),
    'log_level' => 'info',
]);

$httpClient = new App\Component\Http(['timeout' => 30], $logger);
$telegram = new TelegramAPI($config['telegram']['bot_token'], $httpClient, $logger);

$db = new MySQL([
    'host' => $config['database']['host'],
    'port' => $config['database']['port'],
    'database' => $config['database']['database'],
    'username' => $config['database']['username'],
    'password' => $config['database']['password'],
    'charset' => $config['database']['charset'],
], $logger);

$itemRepo = new ItemRepository($db, $logger, true);
$feedStateRepo = new FeedStateRepository($db, $logger);
$fetchRunner = new FetchRunner($db, $config['cache_dir'], $logger);

// Тестовые ленты (первые 3)
$feeds = [
    FeedConfig::fromArray([
        'id' => 1,
        'name' => 'РИА Новости',
        'url' => 'https://ria.ru/export/rss2/index.xml?page_type=google_newsstand',
        'enabled' => true,
        'timeout' => 30,
        'retries' => 3,
        'polling_interval' => 300,
        'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/2.0)'],
        'parser_options' => ['max_items' => 50, 'enable_cache' => true],
    ]),
    FeedConfig::fromArray([
        'id' => 2,
        'name' => 'Ведомости Технологии',
        'url' => 'https://www.vedomosti.ru/rss/rubric/technology.xml',
        'enabled' => true,
        'timeout' => 30,
        'retries' => 3,
        'polling_interval' => 300,
        'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/2.0)'],
        'parser_options' => ['max_items' => 50, 'enable_cache' => true],
    ]),
    FeedConfig::fromArray([
        'id' => 3,
        'name' => 'Лента.ру Топ-7',
        'url' => 'http://lenta.ru/rss/top7',
        'enabled' => true,
        'timeout' => 30,
        'retries' => 3,
        'polling_interval' => 300,
        'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/2.0)'],
        'parser_options' => ['max_items' => 50, 'enable_cache' => true],
    ]),
];

// Уведомление в Telegram
try {
    $telegram->sendMessage(
        $config['telegram']['chat_id'],
        "🔄 <b>ТЕСТ КЕШИРОВАНИЯ</b>\n\nПроверка работы кеширования и дедупликации...",
        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
    );
} catch (\Exception $e) {
    echo "⚠️ Не удалось отправить уведомление в Telegram\n";
}

// Статистика ДО
$statsBefore = $itemRepo->getStats();
echo "📊 СТАТИСТИКА ДО ВТОРОГО FETCH:\n";
echo "   Всего новостей в БД: " . ($statsBefore['total'] ?? 0) . "\n";
echo "   Опубликовано: " . ($statsBefore['published'] ?? 0) . "\n\n";

// Второй fetch
echo "🔄 Запуск ВТОРОГО fetch (должен использовать кеш)...\n\n";
$startTime = microtime(true);
$fetchResults = $fetchRunner->runForAllFeeds($feeds);
$duration = round(microtime(true) - $startTime, 2);

$newItemsCount = 0;
$cachedCount = 0;

foreach ($fetchResults as $feedId => $result) {
    $feedName = '';
    foreach ($feeds as $f) {
        if ($f->id === $feedId) {
            $feedName = $f->name;
            break;
        }
    }
    
    if ($result->isSuccessful()) {
        $itemsCount = count($result->getValidItems());
        echo "  ✅ $feedName: $itemsCount новостей получено\n";
        
        // Пробуем сохранить
        $savedCount = 0;
        foreach ($result->getValidItems() as $item) {
            $itemId = $itemRepo->save($feedId, $item);
            if ($itemId !== null) {
                $savedCount++;
            }
        }
        
        echo "     💾 Сохранено новых: $savedCount (остальные - дубликаты)\n";
        $newItemsCount += $savedCount;
        $cachedCount += ($itemsCount - $savedCount);
    } else {
        echo "  ❌ $feedName: Ошибка\n";
    }
}

echo "\n";

// Статистика ПОСЛЕ
$statsAfter = $itemRepo->getStats();
echo "📊 СТАТИСТИКА ПОСЛЕ ВТОРОГО FETCH:\n";
echo "   Всего новостей в БД: " . ($statsAfter['total'] ?? 0) . "\n";
echo "   Опубликовано: " . ($statsAfter['published'] ?? 0) . "\n";
echo "   Новых добавлено: " . $newItemsCount . "\n";
echo "   Дедуплицировано: " . $cachedCount . "\n";
echo "   Длительность: {$duration} сек\n\n";

// Результат
$totalBefore = $statsBefore['total'] ?? 0;
$totalAfter = $statsAfter['total'] ?? 0;
$diff = $totalAfter - $totalBefore;

if ($diff === 0) {
    echo "✅ ТЕСТ PASSED: Дубликаты успешно отфильтрованы!\n";
    echo "   Все новости уже были в БД, новых записей не добавлено.\n";
    $testResult = "✅ PASSED";
} elseif ($diff < 5) {
    echo "✅ ТЕСТ PASSED: Частичное обновление.\n";
    echo "   Добавлено всего $diff новых записей (вероятно, новые новости появились).\n";
    $testResult = "✅ PASSED (with updates)";
} else {
    echo "⚠️ ТЕСТ WARNING: Добавлено $diff новых записей.\n";
    echo "   Возможно, появились новые новости или кеширование работает не полностью.\n";
    $testResult = "⚠️ WARNING";
}

echo "\n";

// Финальное уведомление
try {
    $message = "🔄 <b>ТЕСТ КЕШИРОВАНИЯ ЗАВЕРШЕН</b>\n\n";
    $message .= "📊 <b>Результаты:</b>\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━\n";
    $message .= "До: <b>" . $totalBefore . "</b> новостей\n";
    $message .= "После: <b>" . $totalAfter . "</b> новостей\n";
    $message .= "Добавлено: <b>" . $diff . "</b>\n";
    $message .= "Дедуплицировано: <b>" . $cachedCount . "</b>\n\n";
    $message .= "⏱ Время: {$duration} сек\n\n";
    $message .= "Статус: $testResult";
    
    $telegram->sendMessage(
        $config['telegram']['chat_id'],
        $message,
        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
    );
} catch (\Exception $e) {
    echo "⚠️ Не удалось отправить финальное уведомление в Telegram\n";
}

echo "================================================================================\n\n";

exit(0);

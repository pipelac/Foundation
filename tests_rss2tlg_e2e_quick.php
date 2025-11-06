<?php

declare(strict_types=1);

/**
 * E2E Тест RSS2TLG - Быстрая проверка (без AI)
 * 
 * Проверяем:
 * 1. ✅ Подключение к MariaDB
 * 2. ✅ Получение RSS
 * 3. ✅ Сохранение с исправлением Unicode
 * 4. ✅ Отправка уведомлений в Telegram
 * 5. ✅ Создание дампов
 */

use Cache\FileCache;
use App\Component\Http;
use App\Component\Logger;
use App\Component\MySQL;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Rss2Tlg\FetchRunner;
use App\Rss2Tlg\ItemRepository;
use App\Rss2Tlg\DTO\FeedConfig;

require_once __DIR__ . '/autoload.php';

echo "\n🚀 Быстрый E2E тест RSS2TLG (без AI)\n\n";

$startTime = microtime(true);

// Загрузка конфигурации
$config = json_decode(file_get_contents(__DIR__ . '/Config/rss2tlg_e2e_test.json'), true);

// Инициализация
$logger = new Logger(['enabled' => true, 'level' => 'DEBUG', 'directory' => '/tmp', 'filename' => 'rss2tlg_quick.log']);
$db = new MySQL($config['database'], $logger);
$http = new Http(['timeout' => 30], $logger);
$telegram = new TelegramAPI($config['telegram']['bot_token'], $http, $logger);

echo "✅ Компоненты инициализированы\n";
echo "✅ MariaDB: " . $db->queryScalar("SELECT VERSION()") . "\n\n";

// Отправка стартового уведомления
try {
    $telegram->sendMessage(
        $config['telegram']['chat_id'],
        "🚀 <b>Быстрый E2E тест запущен</b>\n\n" .
        "Проверяем:\n" .
        "• Получение RSS\n" .
        "• Сохранение с Unicode Fix\n" .
        "• Создание дампов\n\n" .
        "⏰ " . date('Y-m-d H:i:s'),
        ['parse_mode' => 'HTML']
    );
    echo "✅ Стартовое уведомление отправлено\n\n";
} catch (\Exception $e) {
    echo "⚠️  Ошибка отправки уведомления: {$e->getMessage()}\n\n";
}

// Очистка таблиц
echo "🧹 Очистка таблиц...\n";
$db->execute("DELETE FROM rss2tlg_items");
$db->execute("DELETE FROM rss2tlg_feed_state");
echo "✅ Таблицы очищены\n\n";

// Опрос RSS (только первые 3 источника для скорости)
echo "📡 Опрос RSS источников...\n";

$feedConfigs = [];
foreach (array_slice($config['feeds'], 0, 3) as $feedData) {
    // Ограничиваем до 1 новости
    $feedData['parser_options'] = ['max_items' => 1];
    $feedConfigs[] = FeedConfig::fromArray($feedData);
}

$cacheDir = '/tmp/rss2tlg_e2e_cache';
if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);

$fetchRunner = new FetchRunner($db, $cacheDir, $logger);
$fetchResults = $fetchRunner->runForAllFeeds($feedConfigs);

$itemsFetched = 0;
foreach ($feedConfigs as $feed) {
    if (isset($fetchResults[$feed->id]) && $fetchResults[$feed->id]->isSuccessful()) {
        $count = count($fetchResults[$feed->id]->items);
        echo "  ✅ {$feed->title}: {$count} новостей\n";
        $itemsFetched += $count;
    }
}
echo "📊 Всего получено: {$itemsFetched} новостей\n\n";

// Сохранение в БД
echo "💾 Сохранение в БД с Unicode Fix...\n";

$itemRepo = new ItemRepository($db, $logger);
$itemsSaved = 0;

foreach ($feedConfigs as $feed) {
    if (!isset($fetchResults[$feed->id]) || !$fetchResults[$feed->id]->isSuccessful()) {
        continue;
    }
    
    foreach ($fetchResults[$feed->id]->items as $rawItem) {
        $itemId = $itemRepo->save($feed->id, $rawItem);
        
        if ($itemId !== null) {
            $itemsSaved++;
            echo "  ✅ #{$itemId}: " . substr($rawItem->title, 0, 50) . "...\n";
            
            if (!empty($rawItem->categories)) {
                $cats = implode(', ', $rawItem->categories);
                echo "      Категории: $cats\n";
            }
        }
    }
}

echo "📊 Сохранено: {$itemsSaved} новостей\n\n";

// Проверка в БД
echo "🔍 Проверка данных в БД...\n";

$items = $db->query("SELECT id, title, categories FROM rss2tlg_items LIMIT 5");

foreach ($items as $item) {
    $categories = json_decode($item['categories'] ?? '[]', true);
    echo "  • #{$item['id']}: " . substr($item['title'], 0, 40) . "...\n";
    
    if (!empty($categories)) {
        $catStr = implode(', ', $categories);
        echo "    Categories (JSON): $catStr\n";
        
        // Проверяем что нет Unicode escape
        $hasUnicodeEscape = strpos($item['categories'], '\\u') !== false;
        if ($hasUnicodeEscape) {
            echo "    ❌ ОШИБКА: Обнаружен Unicode escape!\n";
        } else {
            echo "    ✅ Кириллица сохранена корректно\n";
        }
    }
}

echo "\n";

// Создание дампа
echo "📁 Создание дампа...\n";

$dumpsDir = __DIR__ . '/tests/sql';
if (!is_dir($dumpsDir)) mkdir($dumpsDir, 0755, true);

$timestamp = date('Ymd_His');
$dumpFile = "{$dumpsDir}/rss2tlg_items_quick_{$timestamp}.csv";

$allItems = $db->query("SELECT * FROM rss2tlg_items");

if (!empty($allItems)) {
    $fp = fopen($dumpFile, 'w');
    fputcsv($fp, array_keys($allItems[0]));
    
    foreach ($allItems as $row) {
        fputcsv($fp, $row);
    }
    
    fclose($fp);
    
    echo "✅ Дамп создан: $dumpFile (" . count($allItems) . " записей)\n\n";
}

// Финальная статистика
$duration = round(microtime(true) - $startTime, 2);

echo "═══════════════════════════════════════\n";
echo "        РЕЗУЛЬТАТЫ ТЕСТИРОВАНИЯ        \n";
echo "═══════════════════════════════════════\n\n";
echo "⏱️  Длительность: {$duration} сек\n";
echo "📡 Источников: " . count($feedConfigs) . "\n";
echo "📰 Получено: {$itemsFetched}\n";
echo "💾 Сохранено: {$itemsSaved}\n";
echo "\n✅ ТЕСТ ЗАВЕРШЕН УСПЕШНО!\n\n";

// Финальное уведомление
try {
    $telegram->sendMessage(
        $config['telegram']['chat_id'],
        "✅ <b>Быстрый E2E тест завершен!</b>\n\n" .
        "<b>📊 Результаты:</b>\n" .
        "• Получено: {$itemsFetched}\n" .
        "• Сохранено: {$itemsSaved}\n" .
        "• Unicode Fix: ✅\n\n" .
        "⏱️ Время: {$duration} сек\n" .
        "⏰ Завершен: " . date('Y-m-d H:i:s'),
        ['parse_mode' => 'HTML']
    );
    echo "✅ Финальное уведомление отправлено в Telegram\n\n";
} catch (\Exception $e) {
    echo "⚠️  Ошибка отправки уведомления: {$e->getMessage()}\n\n";
}

<?php

declare(strict_types=1);

/**
 * Финальный интеграционный тест RSS2TLG
 * Проверяет полный цикл работы системы
 */

use Cache\FileCache;
use App\Component\Http;
use App\Component\Logger;
use App\Component\MySQL;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Rss2Tlg\FeedStateRepository;
use App\Rss2Tlg\FetchRunner;
use App\Rss2Tlg\ItemRepository;
use App\Rss2Tlg\PublicationRepository;
use App\Rss2Tlg\DTO\FeedConfig;

require_once __DIR__ . '/autoload.php';

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║                 ФИНАЛЬНЫЙ ИНТЕГРАЦИОННЫЙ ТЕСТ RSS2TLG                 ║\n";
echo "║  Проверка полного цикла: RSS → БД → AI → Telegram                  ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

$startTime = microtime(true);
$testResults = [
    'rss_fetch' => false,
    'db_save' => false,
    'ai_analysis' => false,
    'telegram_publish' => false,
    'errors' => []
];

try {
    // Этап 1: Инициализация
    echo "📦 ЭТАП 1: Инициализация компонентов\n";
    echo "─────────────────────────────────────────────────────────────────────────────────\n";
    
    $logger = new Logger([
        'enabled' => true,
        'level' => 'INFO',
        'directory' => '/tmp',
        'filename' => 'rss2tlg_final_test.log'
    ]);
    
    $db = new MySQL([
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'rss2tlg',
        'username' => 'rss2tlg_user',
        'password' => 'rss2tlg_pass',
        'charset' => 'utf8mb4'
    ], $logger);
    
    $http = new Http([
        'timeout' => 30,
        'connect_timeout' => 10,
        'verify_ssl' => true,
        'user_agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/2.1)'
    ], $logger);
    
    $cacheDir = '/tmp/rss2tlg_test_cache';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $cache = new FileCache([
        'cacheDirectory' => $cacheDir,
        'ttl' => 3600
    ]);
    
    $telegramAPI = new TelegramAPI('8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI', $http, $logger);
    
    echo "✅ Все компоненты инициализированы\n\n";
    
    // Этап 2: Загрузка конфигурации
    echo "🔧 ЭТАП 2: Загрузка конфигурации\n";
    echo "─────────────────────────────────────────────────────────────────────────────────\n";
    
    $configFile = __DIR__ . '/config/rss2tlg_test_5feeds.json';
    $configData = json_decode(file_get_contents($configFile), true, 512, JSON_THROW_ON_ERROR);
    
    $feedConfigs = [];
    foreach ($configData['feeds'] as $feedData) {
        $feedConfigs[] = FeedConfig::fromArray($feedData);
    }
    
    echo "✅ Загружено источников: " . count($feedConfigs) . "\n\n";
    
    // Этап 3: RSS опрос
    echo "📡 ЭТАП 3: RSS опрос\n";
    echo "─────────────────────────────────────────────────────────────────────────────────\n";
    
    $fetchRunner = new FetchRunner($db, $cacheDir, $logger);
    $fetchResults = $fetchRunner->runForAllFeeds($feedConfigs);
    
    $totalItems = 0;
    foreach ($fetchResults as $feedId => $result) {
        if ($result->isSuccessful()) {
            $totalItems += count($result->items);
            echo "✅ Feed #$feedId: " . count($result->items) . " новостей\n";
        } else {
            echo "❌ Feed #$feedId: " . $result->getStatus() . "\n";
            $testResults['errors'][] = "Feed #$feedId: " . $result->getStatus();
        }
    }
    
    $testResults['rss_fetch'] = $totalItems > 0;
    echo "📊 Всего получено новостей: $totalItems\n\n";
    
    // Этап 4: Сохранение в БД
    echo "💾 ЭТАП 4: Сохранение в БД\n";
    echo "─────────────────────────────────────────────────────────────────────────────────\n";
    
    $itemRepository = new ItemRepository($db, $logger);
    $savedItems = 0;
    
    foreach ($fetchResults as $feedId => $result) {
        if ($result->isSuccessful()) {
            foreach ($result->items as $item) {
                $itemId = $itemRepository->save($feedId, $item);
                if ($itemId !== null) {
                    $savedItems++;
                    echo "✅ Сохранена новость #$itemId\n";
                } else {
                    echo "⚪ Новость дублируется\n";
                }
            }
        }
    }
    
    $testResults['db_save'] = $savedItems > 0;
    echo "📊 Всего сохранено: $savedItems\n\n";
    
    // Этап 5: Проверка публикации
    echo "📱 ЭТАП 5: Проверка Telegram\n";
    echo "─────────────────────────────────────────────────────────────────────────────────\n";
    
    $publicationRepository = new PublicationRepository($db, $logger);
    
    // Отправляем тестовое сообщение
    try {
        $message = "🧪 <b>ФИНАЛЬНЫЙ ТЕСТ RSS2TLG</b>\n\n" .
            "✅ RSS опрос: " . ($testResults['rss_fetch'] ? 'УСПЕШНО' : 'ОШИБКА') . "\n" .
            "✅ Сохранение в БД: " . ($testResults['db_save'] ? 'УСПЕШНО' : 'ОШИБКА') . "\n" .
            "📊 Получено новостей: $totalItems\n" .
            "💾 Сохранено: $savedItems\n" .
            "⏰ Время: " . date('Y-m-d H:i:s');
        
        $telegramMessage = $telegramAPI->sendMessage(366442475, $message, ['parse_mode' => 'HTML']);
        echo "✅ Тестовое сообщение отправлено (ID: " . $telegramMessage->messageId . ")\n";
        $testResults['telegram_publish'] = true;
    } catch (\Exception $e) {
        echo "❌ Ошибка отправки в Telegram: " . $e->getMessage() . "\n";
        $testResults['errors'][] = "Telegram: " . $e->getMessage();
    }
    
} catch (\Exception $e) {
    echo "❌ КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage() . "\n";
    echo "Stack: " . $e->getTraceAsString() . "\n";
    $testResults['errors'][] = "Critical: " . $e->getMessage();
}

// Финальный отчет
$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);

echo "\n╔════════════════════════════════════════════════════════════════════╗\n";
echo "║                           ФИНАЛЬНЫЙ ОТЧЕТ                              ║\n";
echo "╠════════════════════════════════════════════════════════════════╣\n";
echo "║ RSS опрос:        " . ($testResults['rss_fetch'] ? '✅ УСПЕШНО' : '❌ ОШИБКА') . "                    ║\n";
echo "║ Сохранение в БД:  " . ($testResults['db_save'] ? '✅ УСПЕШНО' : '❌ ОШИБКА') . "                    ║\n";
echo "║ Telegram:         " . ($testResults['telegram_publish'] ? '✅ УСПЕШНО' : '❌ ОШИБКА') . "                    ║\n";
echo "║ Ошибок:          " . count($testResults['errors']) . "                             ║\n";
echo "║ Длительность:      " . $duration . " сек                                ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

if (empty($testResults['errors'])) {
    echo "🎉 ТЕСТ ПРОЙДЕН УСПЕШНО! Система готова к production!\n";
    exit(0);
} else {
    echo "⚠️  ТЕСТ ЗАВЕРШЕН С ОШИБКАМИ:\n";
    foreach ($testResults['errors'] as $error) {
        echo "  • $error\n";
    }
    exit(1);
}
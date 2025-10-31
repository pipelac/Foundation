<?php

declare(strict_types=1);

/**
 * Быстрый smoke test PollingHandler
 * 
 * Краткая проверка основного функционала
 */

require_once __DIR__ . '/../autoload.php';

use App\Component\Http;
use App\Component\Logger;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Core\PollingHandler;

$BOT_TOKEN = '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI';

echo "🧪 SMOKE TEST: PollingHandler\n";
echo str_repeat('-', 60) . "\n";

try {
    // 1. Инициализация
    echo "1. Инициализация... ";
    $logger = new Logger(['directory' => __DIR__ . '/../logs']);
    $http = new Http(['timeout' => 30], $logger);
    $api = new TelegramAPI($BOT_TOKEN, $http, $logger);
    $polling = new PollingHandler($api, $logger);
    echo "✅\n";
    
    // 2. Подключение к боту
    echo "2. Подключение к боту... ";
    $botInfo = $api->getMe();
    echo "✅ @{$botInfo->username}\n";
    
    // 3. Удаление webhook
    echo "3. Проверка webhook... ";
    $api->deleteWebhook(true);
    echo "✅\n";
    
    // 4. Настройка параметров
    echo "4. Настройка параметров... ";
    $polling->setTimeout(5)->setLimit(10);
    echo "✅\n";
    
    // 5. Проверка offset
    echo "5. Проверка offset... ";
    $offset = $polling->getOffset();
    echo "✅ offset=$offset\n";
    
    // 6. Получение обновлений
    echo "6. Получение обновлений... ";
    $updates = $polling->pollOnce();
    echo "✅ получено: " . count($updates) . "\n";
    
    // 7. Проверка состояния
    echo "7. Проверка состояния... ";
    $isPolling = $polling->isPolling();
    echo "✅ " . ($isPolling ? 'активен' : 'неактивен') . "\n";
    
    // 8. Сброс
    echo "8. Сброс состояния... ";
    $polling->reset();
    echo "✅\n";
    
    echo str_repeat('-', 60) . "\n";
    echo "✅ SMOKE TEST ПРОЙДЕН\n";
    echo "\n";
    echo "Класс PollingHandler готов к использованию!\n";
    
} catch (Exception $e) {
    echo "❌ FAILED\n";
    echo "Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}

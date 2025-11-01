<?php

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

use App\Component\Logger;
use App\Component\Http;
use App\Component\TelegramBot\Core\TelegramAPI;

echo "=== Триггер теста прогресс-бара ===\n\n";

// Данные для тестирования
$botToken = '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI';
$testChatId = 366442475;

// Инициализация компонентов
$logger = new Logger(['directory' => __DIR__ . '/logs']);
$http = new Http(['timeout' => 60], $logger);
$telegram = new TelegramAPI($botToken, $http, $logger);

echo "✓ Инициализация завершена\n\n";

// Отправляем команду /progress для активации бота
echo "Отправка команды /progress...\n";
try {
    $telegram->sendMessage($testChatId, '/progress');
    echo "✓ Команда /progress отправлена\n";
    echo "✓ В Telegram должно появиться меню с кнопками\n\n";
    
    // Ждем 3 секунды, чтобы бот обработал команду
    echo "Ожидание 3 секунды...\n";
    sleep(3);
    
    echo "\n=== Теперь можно нажать на кнопку в Telegram ===\n";
    echo "Или запустите прямой тест:\n";
    echo "  php trigger_progressbar_direct_test.php\n";
} catch (Exception $e) {
    echo "✗ Ошибка: {$e->getMessage()}\n";
}

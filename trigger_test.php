<?php

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

use App\Component\Logger;
use App\Component\Http;
use App\Component\TelegramBot\Core\TelegramAPI;

echo "=== Триггер теста счетчиков ===\n\n";

// Данные для тестирования
$botToken = '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI';
$testChatId = 366442475;

// Инициализация компонентов
$logger = new Logger(['directory' => __DIR__ . '/logs']);
$http = new Http(['timeout' => 60], $logger);
$telegram = new TelegramAPI($botToken, $http, $logger);

echo "✓ Инициализация завершена\n\n";

// Отправляем команду /start для активации бота
echo "Отправка команды /start...\n";
try {
    $telegram->sendMessage($testChatId, '/start');
    echo "✓ Команда /start отправлена\n";
} catch (Exception $e) {
    echo "✗ Ошибка: {$e->getMessage()}\n";
}

echo "\n=== Триггер завершен ===\n";
echo "Теперь в Telegram должно появиться меню с кнопками для тестирования счетчиков.\n";

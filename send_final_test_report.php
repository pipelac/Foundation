<?php

require_once __DIR__ . '/autoload.php';

use App\Component\Http;
use App\Component\TelegramBot\Core\TelegramAPI;

$botToken = '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI';
$chatId = 366442475;

$http = new Http(['timeout' => 30]);
$api = new TelegramAPI($botToken, $http);

$report = file_get_contents(__DIR__ . '/TELEGRAM_BOT_POLLING_TEST_REPORT.md');

// Разбиваем на части по 4000 символов
$chunks = str_split($report, 4000);

foreach ($chunks as $i => $chunk) {
    $api->sendMessage($chatId, $chunk);
    if ($i < count($chunks) - 1) {
        sleep(1);
    }
}

echo "✅ Финальный отчет отправлен в Telegram!\n";

#!/usr/bin/env php
<?php
/**
 * Telegram Notifier Helper
 * 
 * Вспомогательный скрипт для отправки уведомлений в Telegram бот
 * во время тестирования
 */

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use App\Component\Logger;
use App\Component\Telegram;

function sendTestNotification(string $message): bool
{
    try {
        $config = [
            'token' => '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI',
            'default_chat_id' => '366442475',
            'timeout' => 30,
        ];
        
        $logConfig = [
            'directory' => __DIR__ . '/../logs',
            'file_name' => 'telegram_notifier.log',
            'min_level' => 'INFO',
        ];
        
        $logger = new Logger($logConfig);
        $telegram = new Telegram($config, $logger);
        
        $result = $telegram->sendText(null, $message, [
            'parse_mode' => 'HTML',
            'disable_notification' => false,
        ]);
        
        return $result !== false;
        
    } catch (Exception $e) {
        echo "❌ Ошибка отправки: " . $e->getMessage() . "\n";
        return false;
    }
}

// Основная логика
if ($argc < 2) {
    echo "Usage: php telegram_notifier.php 'Message text'\n";
    exit(1);
}

$message = $argv[1];
$success = sendTestNotification($message);

exit($success ? 0 : 1);

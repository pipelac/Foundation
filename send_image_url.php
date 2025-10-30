<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Component\Telegram;
use App\Component\Logger;

$token = '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI';
$chatId = '366442475';

$logger = new Logger([
    'directory' => __DIR__ . '/test_media_real/logs',
    'file_name' => 'url_test.log',
    'log_buffer_size' => 0,
]);

$telegram = new Telegram(['token' => $token], $logger);

echo "\n🌐 Попытка отправки изображения с разных URL...\n\n";

// Попробуем несколько надежных источников
$urls = [
    'https://picsum.photos/200/300' => 'Случайное изображение с picsum.photos',
    'https://httpbin.org/image/png' => 'PNG изображение с httpbin.org',
    'https://raw.githubusercontent.com/github/explore/main/topics/php/php.png' => 'PHP лого с GitHub',
];

foreach ($urls as $url => $description) {
    echo "📸 Пробую: {$description}\n";
    echo "   URL: {$url}\n";
    
    try {
        $result = $telegram->sendPhoto($chatId, $url, [
            'caption' => "✅ {$description}",
        ]);
        
        echo "   ✅ Успешно! Message ID: " . ($result['result']['message_id'] ?? 'N/A') . "\n\n";
        break; // Один успешный достаточно
        
    } catch (Exception $e) {
        echo "   ❌ Ошибка: " . $e->getMessage() . "\n\n";
    }
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✅ Проверьте ваш Telegram!\n\n";

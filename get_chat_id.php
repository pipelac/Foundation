<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Component\Telegram;

/**
 * Помощник для получения Chat ID через long polling
 */
echo "\n";
echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║         ПОЛУЧЕНИЕ CHAT ID ДЛЯ TELEGRAM БОТА               ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

$token = '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI';

try {
    $telegram = new Telegram(['token' => $token]);
    
    // Получаем информацию о боте
    echo "🤖 Получение информации о боте...\n";
    $botInfo = $telegram->getMe();
    $username = $botInfo['result']['username'] ?? 'unknown';
    $botName = $botInfo['result']['first_name'] ?? 'Bot';
    
    echo "✅ Подключено к боту: {$botName} (@{$username})\n\n";
    echo str_repeat('─', 60) . "\n\n";
    echo "📝 ИНСТРУКЦИЯ:\n\n";
    echo "1. Откройте Telegram\n";
    echo "2. Найдите бота: @{$username}\n";
    echo "3. Нажмите START или отправьте любое сообщение\n";
    echo "4. Вернитесь сюда - ваш Chat ID будет отображен\n\n";
    echo "⏳ Ожидание сообщений (нажмите Ctrl+C для выхода)...\n\n";
    
    $offset = 0;
    $maxAttempts = 60; // 60 попыток по 2 секунды = 2 минуты
    $attempt = 0;
    
    while ($attempt < $maxAttempts) {
        try {
            // Получаем обновления через getUpdates
            $url = "https://api.telegram.org/bot{$token}/getUpdates";
            $data = ['offset' => $offset, 'timeout' => 2];
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            $result = json_decode($response, true);
            
            if (isset($result['result']) && !empty($result['result'])) {
                foreach ($result['result'] as $update) {
                    $offset = $update['update_id'] + 1;
                    
                    if (isset($update['message'])) {
                        $chatId = $update['message']['chat']['id'] ?? null;
                        $firstName = $update['message']['from']['first_name'] ?? 'User';
                        $username = $update['message']['from']['username'] ?? 'no_username';
                        $text = $update['message']['text'] ?? '';
                        
                        if ($chatId) {
                            echo "\n";
                            echo "✅ ПОЛУЧЕНО СООБЩЕНИЕ!\n\n";
                            echo "  👤 От: {$firstName} (@{$username})\n";
                            echo "  💬 Текст: {$text}\n";
                            echo "  🆔 Chat ID: {$chatId}\n\n";
                            echo str_repeat('─', 60) . "\n\n";
                            echo "📋 Для запуска теста выполните:\n\n";
                            echo "  php send_test_media.php {$chatId}\n\n";
                            
                            exit(0);
                        }
                    }
                }
            }
            
            $attempt++;
            echo "\r⏳ Ожидание... ({$attempt}/{$maxAttempts})";
            
        } catch (Exception $e) {
            echo "\n❌ Ошибка: " . $e->getMessage() . "\n";
            sleep(2);
        }
    }
    
    echo "\n\n⏱️  Время ожидания истекло.\n";
    echo "Попробуйте еще раз или укажите Chat ID вручную.\n\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка подключения к боту: " . $e->getMessage() . "\n\n";
    exit(1);
}

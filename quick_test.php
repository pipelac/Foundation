<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Component\Telegram;
use App\Component\Logger;

echo "\n";
echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║         БЫСТРЫЙ ТЕСТ TELEGRAM БОТА                        ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

$token = '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI';

try {
    // Создаем экземпляр
    $telegram = new Telegram(['token' => $token]);
    
    // Получаем информацию о боте
    echo "🤖 Проверка подключения...\n";
    $botInfo = $telegram->getMe();
    
    if (isset($botInfo['result'])) {
        $bot = $botInfo['result'];
        echo "✅ Бот активен!\n\n";
        echo "  📋 Имя: " . ($bot['first_name'] ?? 'N/A') . "\n";
        echo "  👤 Username: @" . ($bot['username'] ?? 'N/A') . "\n";
        echo "  🆔 Bot ID: " . ($bot['id'] ?? 'N/A') . "\n";
        echo "  🤖 Is Bot: " . ($bot['is_bot'] ? 'Да' : 'Нет') . "\n\n";
        
        echo str_repeat('─', 60) . "\n\n";
        echo "📱 СЛЕДУЮЩИЙ ШАГ:\n\n";
        echo "1. Откройте бота в Telegram: @" . ($bot['username'] ?? 'PipelacTest_bot') . "\n";
        echo "2. Отправьте боту /start\n";
        echo "3. Запустите скрипт для автоопределения Chat ID:\n\n";
        echo "   php get_chat_id.php\n\n";
        echo "   ИЛИ\n\n";
        echo "4. Если знаете свой Chat ID, запустите:\n\n";
        echo "   php send_test_media.php YOUR_CHAT_ID\n\n";
        
        echo "💡 Совет: Узнать свой Chat ID можно через @userinfobot\n\n";
        
    } else {
        echo "❌ Не удалось получить информацию о боте\n";
        echo "Ответ API: " . json_encode($botInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ ОШИБКА: " . $e->getMessage() . "\n\n";
    echo "Проверьте:\n";
    echo "  • Корректность токена\n";
    echo "  • Интернет-соединение\n";
    echo "  • Доступность Telegram API\n\n";
}

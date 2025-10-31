<?php

declare(strict_types=1);

/**
 * Интерактивный тест PollingHandler
 * 
 * Запускает реальный polling и ожидает сообщения от пользователя
 * Требует ручной отправки сообщений в бот для проверки
 */

require_once __DIR__ . '/../autoload.php';

use App\Component\Http;
use App\Component\Logger;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Core\PollingHandler;
use App\Component\TelegramBot\Entities\Update;
use App\Component\TelegramBot\Keyboards\InlineKeyboardBuilder;

// Конфигурация
$BOT_TOKEN = '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI';
$CHAT_ID = 366442475;

function printHeader(string $title): void
{
    echo PHP_EOL;
    echo str_repeat('=', 80) . PHP_EOL;
    echo $title . PHP_EOL;
    echo str_repeat('=', 80) . PHP_EOL;
}

try {
    printHeader('ИНТЕРАКТИВНЫЙ ТЕСТ POLLING HANDLER');
    
    echo "🤖 Bot Token: " . substr($BOT_TOKEN, 0, 20) . "..." . PHP_EOL;
    echo "💬 Chat ID: $CHAT_ID" . PHP_EOL;
    echo PHP_EOL;
    
    // Инициализация
    $logger = new Logger([
        'directory' => __DIR__ . '/../logs',
        'filename' => 'telegram_bot_polling_interactive',
    ]);
    
    $http = new Http(['timeout' => 60], $logger);
    $api = new TelegramAPI($BOT_TOKEN, $http, $logger);
    
    // Получаем информацию о боте
    $botInfo = $api->getMe();
    echo "✅ Подключен к боту: @{$botInfo->username}" . PHP_EOL;
    echo "   Имя: {$botInfo->firstName}" . PHP_EOL;
    echo "   ID: {$botInfo->id}" . PHP_EOL;
    
    // Удаляем webhook если установлен
    $webhookInfo = $api->getWebhookInfo();
    if (!empty($webhookInfo['url'])) {
        echo "🔄 Удаление webhook..." . PHP_EOL;
        $api->deleteWebhook(true);
        sleep(1);
    }
    
    // Создаем polling handler
    $polling = new PollingHandler($api, $logger);
    $polling->setTimeout(30); // 30 секунд long polling
    $polling->setLimit(10);
    
    // Пропускаем старые сообщения
    echo "🔄 Пропуск старых сообщений..." . PHP_EOL;
    $skipped = $polling->skipPendingUpdates();
    echo "   Пропущено: $skipped" . PHP_EOL;
    
    // Отправляем приветственное сообщение
    printHeader('ОТПРАВКА ПРИВЕТСТВЕННОГО СООБЩЕНИЯ');
    
    $keyboard = InlineKeyboardBuilder::makeSimple([
        '✅ Тест кнопки 1' => 'test_button_1',
        '🔔 Тест кнопки 2' => 'test_button_2',
    ], 2);
    
    $welcomeText = "🧪 *ИНТЕРАКТИВНЫЙ ТЕСТ POLLING*\n\n"
        . "Бот готов к работе в режиме polling!\n\n"
        . "📝 *Что можно протестировать:*\n"
        . "• Отправьте /start для приветствия\n"
        . "• Отправьте /echo текст для эха\n"
        . "• Отправьте /status для статуса\n"
        . "• Нажмите на кнопки ниже\n"
        . "• Отправьте /stop для остановки\n\n"
        . "⏱ Timeout: 30 секунд\n"
        . "📊 Limit: 10 обновлений";
    
    $api->sendMessage($CHAT_ID, $welcomeText, [
        'parse_mode' => 'Markdown',
        'reply_markup' => $keyboard,
    ]);
    
    echo "✅ Приветственное сообщение отправлено" . PHP_EOL;
    
    // Запускаем polling
    printHeader('ЗАПУСК POLLING РЕЖИМА');
    echo "⏳ Ожидание сообщений... (отправьте /stop для остановки)" . PHP_EOL;
    echo "📍 Отправляйте команды и сообщения в бот @{$botInfo->username}" . PHP_EOL;
    echo str_repeat('-', 80) . PHP_EOL;
    
    $messagesProcessed = 0;
    $startTime = time();
    
    $polling->startPolling(function(Update $update) use ($api, $polling, &$messagesProcessed, $startTime) {
        $messagesProcessed++;
        
        echo PHP_EOL . "📨 Получено обновление #{$update->updateId}" . PHP_EOL;
        
        // Обработка текстовых сообщений
        if ($update->isMessage() && $update->message->text) {
            $message = $update->message;
            $text = $message->text;
            $chatId = $message->chat->id;
            $userName = $message->from->firstName ?? 'Пользователь';
            
            echo "   👤 От: $userName (ID: {$message->from->id})" . PHP_EOL;
            echo "   💬 Текст: $text" . PHP_EOL;
            
            // Обработка команд
            if (str_starts_with($text, '/')) {
                $command = strtolower(trim(explode(' ', $text)[0], '/'));
                
                echo "   🔧 Команда: $command" . PHP_EOL;
                
                switch ($command) {
                    case 'start':
                        $api->sendMessage($chatId, 
                            "👋 Привет, $userName!\n\n"
                            . "Я работаю в режиме polling.\n"
                            . "Время работы: " . (time() - $startTime) . " сек\n"
                            . "Обработано сообщений: $messagesProcessed"
                        );
                        echo "   ✅ Отправлен ответ на /start" . PHP_EOL;
                        break;
                        
                    case 'echo':
                        $echoText = trim(substr($text, 5));
                        if (empty($echoText)) {
                            $echoText = "Отправьте /echo текст";
                        }
                        $api->sendMessage($chatId, "🔄 Эхо: $echoText");
                        echo "   ✅ Отправлено эхо" . PHP_EOL;
                        break;
                        
                    case 'status':
                        $uptime = time() - $startTime;
                        $status = "📊 *Статус бота:*\n\n"
                            . "✅ Режим: Polling\n"
                            . "⏱ Время работы: {$uptime} сек\n"
                            . "📨 Обработано: $messagesProcessed\n"
                            . "🔢 Offset: {$polling->getOffset()}\n"
                            . "🤖 Активен: " . ($polling->isPolling() ? 'Да' : 'Нет');
                        
                        $api->sendMessage($chatId, $status, [
                            'parse_mode' => 'Markdown',
                        ]);
                        echo "   ✅ Отправлен статус" . PHP_EOL;
                        break;
                        
                    case 'stop':
                        $api->sendMessage($chatId, 
                            "🛑 Останавливаю polling...\n"
                            . "Обработано сообщений: $messagesProcessed\n"
                            . "Время работы: " . (time() - $startTime) . " сек"
                        );
                        $polling->stopPolling();
                        echo "   🛑 Получена команда остановки" . PHP_EOL;
                        break;
                        
                    default:
                        $api->sendMessage($chatId, 
                            "❓ Неизвестная команда: $command\n\n"
                            . "Доступные команды:\n"
                            . "/start - Приветствие\n"
                            . "/echo текст - Эхо\n"
                            . "/status - Статус\n"
                            . "/stop - Остановить"
                        );
                        echo "   ⚠️ Неизвестная команда" . PHP_EOL;
                }
            } else {
                // Обычное сообщение - отвечаем эхом
                $api->sendMessage($chatId, "📝 Вы написали: $text");
                echo "   ✅ Отправлено эхо сообщения" . PHP_EOL;
            }
        }
        
        // Обработка callback запросов
        elseif ($update->isCallbackQuery()) {
            $query = $update->callbackQuery;
            $data = $query->data;
            $chatId = $query->message->chat->id;
            $messageId = $query->message->messageId;
            
            echo "   🔘 Callback: $data" . PHP_EOL;
            
            // Отвечаем на callback
            $api->answerCallbackQuery($query->id, [
                'text' => "Вы нажали на кнопку!",
                'show_alert' => false,
            ]);
            
            // Изменяем сообщение
            $newText = "✅ Вы нажали на кнопку: *$data*\n\n"
                . "Время: " . date('H:i:s') . "\n"
                . "Обработано: $messagesProcessed";
            
            $api->editMessageText($chatId, $messageId, $newText, [
                'parse_mode' => 'Markdown',
            ]);
            
            echo "   ✅ Обработан callback запрос" . PHP_EOL;
        }
        
        echo "   ✅ Обновление обработано" . PHP_EOL;
        
    }, null); // null = бесконечный цикл до вызова stopPolling()
    
    // Завершение
    printHeader('POLLING ОСТАНОВЛЕН');
    echo "✅ Обработано сообщений: $messagesProcessed" . PHP_EOL;
    echo "⏱ Время работы: " . (time() - $startTime) . " сек" . PHP_EOL;
    echo "🔢 Финальный offset: {$polling->getOffset()}" . PHP_EOL;
    
    // Отправляем финальное сообщение
    $api->sendMessage($CHAT_ID, 
        "✅ *Тест завершен*\n\n"
        . "📊 Статистика:\n"
        . "• Обработано: $messagesProcessed\n"
        . "• Время: " . (time() - $startTime) . " сек\n"
        . "• Offset: {$polling->getOffset()}\n\n"
        . "Спасибо за тестирование! 🎉",
        ['parse_mode' => 'Markdown']
    );
    
    echo PHP_EOL . "🎉 Тест завершен успешно!" . PHP_EOL;
    
} catch (Exception $e) {
    printHeader('ОШИБКА');
    echo "❌ " . $e->getMessage() . PHP_EOL;
    echo "   Файл: " . $e->getFile() . PHP_EOL;
    echo "   Строка: " . $e->getLine() . PHP_EOL;
    exit(1);
}

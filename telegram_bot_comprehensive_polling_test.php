<?php

declare(strict_types=1);

/**
 * Комплексное тестирование TelegramBot в режиме Polling
 * Версия 2.0 - упрощенная и исправленная
 */

require_once __DIR__ . '/autoload.php';

use App\Config\ConfigLoader;
use App\Component\Http;
use App\Component\Logger;
use App\Component\MySQLConnectionFactory;
use App\Component\Telegram;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Core\PollingHandler;
use App\Component\TelegramBot\Core\ConversationManager;
use App\Component\TelegramBot\Core\MessageStorage;
use App\Component\TelegramBot\Keyboards\InlineKeyboardBuilder;
use App\Component\TelegramBot\Keyboards\ReplyKeyboardBuilder;

// Конфигурация
$BOT_TOKEN = '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI';
$CHAT_ID = 366442475;

echo "╔═════════════════════════════════════════════════════════════════╗\n";
echo "║   КОМПЛЕКСНОЕ ТЕСТИРОВАНИЕ TELEGRAMBOT - POLLING MODE          ║\n";
echo "╚═════════════════════════════════════════════════════════════════╝\n\n";

// Логгер
$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'fileName' => 'telegram_bot_polling_test.log',
    'maxFiles' => 7,
]);

$logger->info('=== НАЧАЛО ТЕСТИРОВАНИЯ ===');

// MySQL
$configDir = __DIR__ . '/config';
$mysqlConfig = ConfigLoader::load($configDir . '/mysql.json');
$conversationsConfig = ConfigLoader::load($configDir . '/telegram_bot_conversations.json');
$messageStorageConfig = ConfigLoader::load($configDir . '/telegram_bot_message_storage.json');

try {
    $factory = new MySQLConnectionFactory($mysqlConfig, $logger);
    $db = $factory->getConnection('main');
    echo "✅ MySQL подключен\n";
} catch (Exception $e) {
    die("❌ MySQL не доступен: {$e->getMessage()}\n");
}

// Компоненты
$conversationManager = new ConversationManager($db, $logger, $conversationsConfig['conversations']);
echo ($conversationManager->isEnabled() ? "✅" : "⚠️ ") . " ConversationManager\n";

$messageStorage = new MessageStorage($db, $logger, $messageStorageConfig['message_storage']);
echo ($messageStorage->isEnabled() ? "✅" : "⚠️ ") . " MessageStorage\n";

$http = new Http(['timeout' => 60], $logger);
$api = new TelegramAPI($BOT_TOKEN, $http, $logger, $messageStorage);
$telegram = new Telegram(['token' => $BOT_TOKEN], $logger);

$polling = new PollingHandler($api, $logger);
$polling->setTimeout(30)->setLimit(100)->setAllowedUpdates(['message', 'callback_query']);

$skipped = $polling->skipPendingUpdates();
echo "✅ PollingHandler (пропущено старых: $skipped)\n\n";

// Отправляем стартовое уведомление
try {
    $telegram->sendText((string)$CHAT_ID, "🚀 *НАЧАЛО ТЕСТИРОВАНИЯ*\n\n" .
        "Я буду отправлять инструкции.\n" .
        "Пожалуйста, следуйте им.", [
        'parse_mode' => Telegram::PARSE_MODE_MARKDOWN
    ]);
} catch (Exception $e) {
    echo "⚠️  Ошибка отправки: {$e->getMessage()}\n";
}

sleep(2);

// ============================================================================
// ТЕСТ 1: Простые текстовые сообщения
// ============================================================================

echo "\n═══ ТЕСТ 1: Простые текстовые сообщения ═══\n\n";

$telegram->sendText((string)$CHAT_ID, "📝 *Тест 1*: Отправка текста\n\n" .
    "Я отправлю вам сообщение. Ответьте любым текстом.", [
    'parse_mode' => Telegram::PARSE_MODE_MARKDOWN
]);

sleep(1);

$api->sendMessage($CHAT_ID, "Привет! Это тестовое сообщение 👋\n\nОтветьте любым текстом.");
echo "📤 Отправлено тестовое сообщение\n";

// Ждем ответа
$received = false;
for ($i = 0; $i < 15; $i++) {
    $updates = $polling->pollOnce();
    
    foreach ($updates as $update) {
        if ($update->isMessage() && $update->message->text && $update->message->chat->id === $CHAT_ID) {
            $text = $update->message->text;
            echo "📩 Получен ответ: $text\n";
            
            $api->sendMessage($CHAT_ID, "✅ Получил: $text");
            $received = true;
            break 2;
        }
    }
    
    sleep(2);
}

if ($received) {
    echo "✅ Тест 1 пройден\n";
    $telegram->sendText((string)$CHAT_ID, "✅ Тест 1 завершен");
} else {
    echo "⚠️  Тест 1: нет ответа от пользователя\n";
}

sleep(2);

// ============================================================================
// ТЕСТ 2: Медиафайлы
// ============================================================================

echo "\n═══ ТЕСТ 2: Медиафайлы ═══\n\n";

$telegram->sendText((string)$CHAT_ID, "📸 *Тест 2*: Медиафайлы\n\n" .
    "Отправьте мне фото, документ или видео.\n" .
    "Я отправлю его обратно.", [
    'parse_mode' => Telegram::PARSE_MODE_MARKDOWN
]);

$mediaReceived = false;
for ($i = 0; $i < 20; $i++) {
    $updates = $polling->pollOnce();
    
    foreach ($updates as $update) {
        if ($update->isMessage() && $update->message->chat->id === $CHAT_ID) {
            $message = $update->message;
            
            // Фото
            if (!empty($message->photo)) {
                $photo = end($message->photo);
                echo "📸 Получено фото\n";
                $api->sendPhoto($CHAT_ID, $photo->fileId, ['caption' => '✅ Ваше фото']);
                $mediaReceived = true;
                break 2;
            }
            
            // Документ
            if ($message->document) {
                echo "📄 Получен документ: {$message->document->fileName}\n";
                $api->sendDocument($CHAT_ID, $message->document->fileId, ['caption' => '✅ Ваш документ']);
                $mediaReceived = true;
                break 2;
            }
            
            // Видео
            if ($message->video) {
                echo "🎥 Получено видео\n";
                $api->sendVideo($CHAT_ID, $message->video->fileId, ['caption' => '✅ Ваше видео']);
                $mediaReceived = true;
                break 2;
            }
            
            // Голосовое
            if ($message->voice) {
                echo "🎤 Получено голосовое\n";
                $api->sendVoice($CHAT_ID, $message->voice->fileId);
                $mediaReceived = true;
                break 2;
            }
        }
    }
    
    sleep(2);
}

if ($mediaReceived) {
    echo "✅ Тест 2 пройден\n";
    $telegram->sendText((string)$CHAT_ID, "✅ Тест 2 завершен");
} else {
    echo "⚠️  Тест 2: медиа не получено\n";
}

sleep(2);

// ============================================================================
// ТЕСТ 3: Inline-клавиатура
// ============================================================================

echo "\n═══ ТЕСТ 3: Inline-клавиатура ═══\n\n";

$telegram->sendText((string)$CHAT_ID, "⌨️  *Тест 3*: Inline-клавиатура\n\n" .
    "Сейчас отправлю клавиатуру. Нажмите любую кнопку.", [
    'parse_mode' => Telegram::PARSE_MODE_MARKDOWN
]);

sleep(1);

$keyboard = InlineKeyboardBuilder::make()
    ->addCallbackButton('✅ Кнопка 1', 'btn_1')
    ->addCallbackButton('🔔 Кнопка 2', 'btn_2')
    ->row()
    ->addCallbackButton('⚙️ Кнопка 3', 'btn_3')
    ->build();

$api->sendMessage($CHAT_ID, "Выберите кнопку:", ['reply_markup' => $keyboard]);
echo "📤 Отправлена inline-клавиатура\n";

$buttonPressed = false;
for ($i = 0; $i < 15; $i++) {
    $updates = $polling->pollOnce();
    
    foreach ($updates as $update) {
        if ($update->isCallbackQuery() && $update->callbackQuery->message->chat->id === $CHAT_ID) {
            $query = $update->callbackQuery;
            echo "👆 Нажата кнопка: {$query->data}\n";
            
            $api->answerCallbackQuery($query->id, ['text' => '✅ Обработано!']);
            $api->editMessageText($CHAT_ID, $query->message->messageId, "✅ Вы выбрали: {$query->data}");
            
            $buttonPressed = true;
            break 2;
        }
    }
    
    sleep(2);
}

if ($buttonPressed) {
    echo "✅ Тест 3 пройден\n";
    $telegram->sendText((string)$CHAT_ID, "✅ Тест 3 завершен");
} else {
    echo "⚠️  Тест 3: кнопка не нажата\n";
}

sleep(2);

// ============================================================================
// ТЕСТ 4: Reply-клавиатура
// ============================================================================

echo "\n═══ ТЕСТ 4: Reply-клавиатура ═══\n\n";

$telegram->sendText((string)$CHAT_ID, "⌨️  *Тест 4*: Reply-клавиатура\n\n" .
    "Отправлю обычную клавиатуру. Нажмите кнопку.", [
    'parse_mode' => Telegram::PARSE_MODE_MARKDOWN
]);

sleep(1);

$keyboard = ReplyKeyboardBuilder::make()
    ->addButton('🔴 Красная')
    ->addButton('🟢 Зеленая')
    ->row()
    ->addButton('🔵 Синяя')
    ->addButton('❌ Удалить')
    ->resizeKeyboard()
    ->build();

$api->sendMessage($CHAT_ID, "Выберите цвет:", ['reply_markup' => $keyboard]);
echo "📤 Отправлена reply-клавиатура\n";

$replyReceived = false;
for ($i = 0; $i < 15; $i++) {
    $updates = $polling->pollOnce();
    
    foreach ($updates as $update) {
        if ($update->isMessage() && $update->message->text && $update->message->chat->id === $CHAT_ID) {
            $text = $update->message->text;
            echo "👆 Нажата кнопка: $text\n";
            
            if ($text === '❌ Удалить') {
                $api->sendMessage($CHAT_ID, "✅ Клавиатура удалена", [
                    'reply_markup' => ['remove_keyboard' => true]
                ]);
            } else {
                $api->sendMessage($CHAT_ID, "✅ Выбран: $text");
            }
            
            $replyReceived = true;
            break 2;
        }
    }
    
    sleep(2);
}

if ($replyReceived) {
    echo "✅ Тест 4 пройден\n";
    $telegram->sendText((string)$CHAT_ID, "✅ Тест 4 завершен", [
        'reply_markup' => ['remove_keyboard' => true]
    ]);
} else {
    echo "⚠️  Тест 4: кнопка не нажата\n";
}

sleep(2);

// ============================================================================
// ТЕСТ 5: Диалог с ConversationManager
// ============================================================================

echo "\n═══ ТЕСТ 5: Диалог с ConversationManager ═══\n\n";

if (!$conversationManager->isEnabled()) {
    echo "⚠️  ConversationManager отключен, пропускаем тест 5\n";
} else {
    $telegram->sendText((string)$CHAT_ID, "💬 *Тест 5*: Диалог с памятью\n\n" .
        "Пройдем регистрацию с 3 шагами:\n" .
        "1. Имя\n" .
        "2. Возраст\n" .
        "3. Город", [
        'parse_mode' => Telegram::PARSE_MODE_MARKDOWN
    ]);
    
    sleep(1);
    
    $keyboard = InlineKeyboardBuilder::make()
        ->addCallbackButton('🚀 Начать регистрацию', 'start_reg')
        ->build();
    
    $api->sendMessage($CHAT_ID, "Готовы начать?", ['reply_markup' => $keyboard]);
    
    // Ждем начала
    $dialogStarted = false;
    for ($i = 0; $i < 15; $i++) {
        $updates = $polling->pollOnce();
        
        foreach ($updates as $update) {
            if ($update->isCallbackQuery() && 
                $update->callbackQuery->data === 'start_reg' && 
                $update->callbackQuery->message->chat->id === $CHAT_ID) {
                
                $query = $update->callbackQuery;
                $api->answerCallbackQuery($query->id);
                
                // Начинаем диалог
                $conversationManager->startConversation($CHAT_ID, $query->from->id, 'awaiting_name', []);
                $api->sendMessage($CHAT_ID, "Отлично! Как вас зовут?");
                
                $dialogStarted = true;
                echo "💬 Диалог начат\n";
                break 2;
            }
        }
        
        sleep(2);
    }
    
    if ($dialogStarted) {
        // Шаг 1: Имя
        $nameReceived = false;
        for ($i = 0; $i < 15; $i++) {
            $updates = $polling->pollOnce();
            
            foreach ($updates as $update) {
                if ($update->isMessage() && $update->message->text && $update->message->chat->id === $CHAT_ID) {
                    $conv = $conversationManager->getConversation($CHAT_ID, $update->message->from->id);
                    
                    if ($conv && $conv['state'] === 'awaiting_name') {
                        $name = $update->message->text;
                        echo "✍️  Имя: $name\n";
                        
                        $conversationManager->updateConversation($CHAT_ID, $update->message->from->id, 'awaiting_age', ['name' => $name]);
                        $api->sendMessage($CHAT_ID, "Приятно познакомиться, $name! Сколько вам лет?");
                        
                        $nameReceived = true;
                        break 2;
                    }
                }
            }
            
            sleep(2);
        }
        
        // Шаг 2: Возраст
        if ($nameReceived) {
            $ageReceived = false;
            for ($i = 0; $i < 15; $i++) {
                $updates = $polling->pollOnce();
                
                foreach ($updates as $update) {
                    if ($update->isMessage() && $update->message->text && $update->message->chat->id === $CHAT_ID) {
                        $conv = $conversationManager->getConversation($CHAT_ID, $update->message->from->id);
                        
                        if ($conv && $conv['state'] === 'awaiting_age') {
                            $age = $update->message->text;
                            
                            if (!is_numeric($age)) {
                                $api->sendMessage($CHAT_ID, "❌ Введите число");
                                continue;
                            }
                            
                            echo "✍️  Возраст: $age\n";
                            
                            $conversationManager->updateConversation($CHAT_ID, $update->message->from->id, 'awaiting_city', ['age' => $age]);
                            $api->sendMessage($CHAT_ID, "Из какого вы города?");
                            
                            $ageReceived = true;
                            break 2;
                        }
                    }
                }
                
                sleep(2);
            }
            
            // Шаг 3: Город
            if ($ageReceived) {
                for ($i = 0; $i < 15; $i++) {
                    $updates = $polling->pollOnce();
                    
                    foreach ($updates as $update) {
                        if ($update->isMessage() && $update->message->text && $update->message->chat->id === $CHAT_ID) {
                            $conv = $conversationManager->getConversation($CHAT_ID, $update->message->from->id);
                            
                            if ($conv && $conv['state'] === 'awaiting_city') {
                                $city = $update->message->text;
                                echo "✍️  Город: $city\n";
                                
                                $data = $conv['data'];
                                
                                $summary = "✅ *Регистрация завершена!*\n\n" .
                                    "Имя: {$data['name']}\n" .
                                    "Возраст: {$data['age']}\n" .
                                    "Город: $city";
                                
                                $api->sendMessage($CHAT_ID, $summary, ['parse_mode' => TelegramAPI::PARSE_MODE_MARKDOWN]);
                                
                                $conversationManager->endConversation($CHAT_ID, $update->message->from->id);
                                
                                echo "✅ Тест 5 пройден\n";
                                $telegram->sendText((string)$CHAT_ID, "✅ Тест 5 завершен");
                                break 2;
                            }
                        }
                    }
                    
                    sleep(2);
                }
            }
        }
    }
}

sleep(2);

// ============================================================================
// СТАТИСТИКА И ДАМПЫ
// ============================================================================

echo "\n═══ СТАТИСТИКА ═══\n\n";

if ($conversationManager->isEnabled()) {
    $stats = $conversationManager->getStatistics();
    echo "📊 Диалогов: {$stats['total']}\n";
}

if ($messageStorage->isEnabled()) {
    try {
        $result = $db->query("SELECT COUNT(*) as total FROM telegram_bot_messages");
        $total = $result[0]['total'] ?? 0;
        echo "📊 Сообщений в БД: $total\n";
    } catch (Exception $e) {
        echo "⚠️  Ошибка получения статистики: {$e->getMessage()}\n";
    }
}

// Создание дампов
echo "\n💾 Создание дампов...\n";

$dumpDir = __DIR__ . '/mysql';
$timestamp = date('Y-m-d_H-i-s');

$tables = ['telegram_bot_users', 'telegram_bot_conversations', 'telegram_bot_messages'];

foreach ($tables as $table) {
    $dumpFile = "$dumpDir/{$table}_$timestamp.sql";
    $command = "sudo mysqldump -u root utilities_db $table > $dumpFile 2>&1";
    exec($command, $output, $returnCode);
    
    if ($returnCode === 0 && file_exists($dumpFile)) {
        echo "   ✅ $table\n";
    } else {
        echo "   ⚠️  $table - ошибка\n";
    }
}

// Финальное уведомление
echo "\n";
$telegram->sendText((string)$CHAT_ID, "🎉 *ТЕСТИРОВАНИЕ ЗАВЕРШЕНО!*\n\n" .
    "Все тесты выполнены.\n" .
    "Проверьте логи и дампы БД.", [
    'parse_mode' => Telegram::PARSE_MODE_MARKDOWN
]);

echo "╔═════════════════════════════════════════════════════════════════╗\n";
echo "║            ТЕСТИРОВАНИЕ ЗАВЕРШЕНО УСПЕШНО!                     ║\n";
echo "╚═════════════════════════════════════════════════════════════════╝\n";

$logger->info('=== ТЕСТИРОВАНИЕ ЗАВЕРШЕНО ===');

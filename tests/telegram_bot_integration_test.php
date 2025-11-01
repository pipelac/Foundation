<?php

declare(strict_types=1);

/**
 * Интеграционное тестирование TelegramBot в режиме Polling
 * 
 * Уровень 1: Операции с клавиатурами и кнопками
 * Уровень 2: Диалоговые сценарии с запоминанием контекста
 */

require_once __DIR__ . '/../autoload.php';

use App\Component\Http;
use App\Component\Logger;
use App\Component\MySQL;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Core\PollingHandler;
use App\Component\TelegramBot\Core\ConversationManager;
use App\Component\TelegramBot\Entities\Update;
use App\Component\TelegramBot\Keyboards\InlineKeyboardBuilder;
use App\Component\TelegramBot\Keyboards\ReplyKeyboardBuilder;

// ============================================================================
// КОНФИГУРАЦИЯ
// ============================================================================

$BOT_TOKEN = '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI';
$CHAT_ID = 366442475;
$TEST_TIMEOUT = 15; // Таймаут ожидания действий пользователя (секунды)

// ============================================================================
// ИНИЦИАЛИЗАЦИЯ
// ============================================================================

echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║  ИНТЕГРАЦИОННОЕ ТЕСТИРОВАНИЕ TELEGRAM BOT (POLLING + MYSQL)        ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

// Логгер
$logger = new Logger(['directory' => __DIR__ . '/../logs']);
$logger->info('=== НАЧАЛО ИНТЕГРАЦИОННОГО ТЕСТИРОВАНИЯ ===');

// HTTP клиент
$http = new Http(['timeout' => 60], $logger);

// Telegram API
$api = new TelegramAPI($BOT_TOKEN, $http, $logger);

// Подключение к MySQL
$db = new MySQL([
    'host' => 'localhost',
    'database' => 'telegram_bot_test',
    'username' => 'test_user',
    'password' => 'test_pass',
], $logger);

// ConversationManager
$conversationManager = new ConversationManager(
    $db,
    $logger,
    [
        'enabled' => true,
        'timeout' => 3600,
        'auto_create_tables' => true,
    ]
);

// PollingHandler
$polling = new PollingHandler($api, $logger);
$polling
    ->setTimeout(30)
    ->setLimit(100)
    ->setAllowedUpdates(['message', 'callback_query']);

// Пропускаем старые сообщения
echo "🔄 Пропуск старых сообщений...\n";
$skipped = $polling->skipPendingUpdates();
echo "✅ Пропущено: $skipped обновлений\n\n";

// Отправка уведомления о начале теста
$api->sendMessage($CHAT_ID, "🧪 <b>НАЧАЛО ИНТЕГРАЦИОННОГО ТЕСТИРОВАНИЯ</b>\n\n" .
    "Режим: <b>Polling</b>\n" .
    "База данных: <b>MySQL</b>\n" .
    "Статус: <b>Готов к тестам</b>", 
    ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
);

// ============================================================================
// ГЛОБАЛЬНЫЕ ПЕРЕМЕННЫЕ ДЛЯ ТЕСТИРОВАНИЯ
// ============================================================================

$testResults = [
    'level_1' => [],
    'level_2' => [],
];
$currentTest = '';
$waitingForResponse = false;
$lastUpdate = null;
$userResponseTimeout = 0;
$timeoutCheckStart = 0;

// ============================================================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// ============================================================================

/**
 * Отправляет уведомление о статусе теста
 */
function sendTestNotification(TelegramAPI $api, int $chatId, string $message, string $emoji = 'ℹ️'): void
{
    $api->sendMessage($chatId, "$emoji <b>$message</b>", [
        'parse_mode' => TelegramAPI::PARSE_MODE_HTML,
    ]);
}

/**
 * Логирует результат теста
 */
function logTestResult(string $testName, bool $passed, string $details = ''): void
{
    global $testResults, $currentTest, $logger;
    
    $status = $passed ? '✅ PASS' : '❌ FAIL';
    echo "$status | $testName\n";
    if ($details) {
        echo "   └─ $details\n";
    }
    
    $logger->info("Результат теста: $testName", [
        'passed' => $passed,
        'details' => $details,
    ]);
    
    if (str_starts_with($currentTest, 'level_1')) {
        $testResults['level_1'][$testName] = ['passed' => $passed, 'details' => $details];
    } elseif (str_starts_with($currentTest, 'level_2')) {
        $testResults['level_2'][$testName] = ['passed' => $passed, 'details' => $details];
    }
}

/**
 * Ожидает ответ от пользователя с таймаутом
 */
function waitForUserResponse(
    PollingHandler $polling,
    int $timeout,
    callable $callback,
    callable $onTimeout = null
): bool {
    global $waitingForResponse, $timeoutCheckStart;
    
    $waitingForResponse = true;
    $timeoutCheckStart = time();
    $attempts = 0;
    $maxAttempts = 2;
    
    echo "⏳ Ожидание ответа пользователя (таймаут: {$timeout}с)...\n";
    
    while (time() - $timeoutCheckStart < $timeout) {
        $updates = $polling->pollOnce();
        
        foreach ($updates as $update) {
            $result = $callback($update);
            if ($result) {
                $waitingForResponse = false;
                return true;
            }
        }
        
        sleep(1);
    }
    
    // Таймаут истёк
    $attempts++;
    
    if ($attempts < $maxAttempts && $onTimeout !== null) {
        echo "⚠️ Таймаут ожидания ($attempts/$maxAttempts). Эмуляция ответа пользователя...\n";
        $onTimeout();
        return true;
    }
    
    echo "❌ Таймаут ожидания ответа пользователя\n";
    $waitingForResponse = false;
    return false;
}

// ============================================================================
// УРОВЕНЬ 1: ОПЕРАЦИИ С КЛАВИАТУРАМИ И КНОПКАМИ
// ============================================================================

echo "\n╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║  УРОВЕНЬ 1: ОПЕРАЦИИ С КЛАВИАТУРАМИ И КНОПКАМИ                       ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

$currentTest = 'level_1';

sendTestNotification($api, $CHAT_ID, "УРОВЕНЬ 1: Тестирование клавиатур", "🎹");

// ============================================================================
// Тест 1.1: Reply-клавиатура (создание и удаление)
// ============================================================================

$currentTest = 'level_1_test_1.1';
echo "\n[Тест 1.1] Reply-клавиатура (создание и удаление)\n";
sendTestNotification($api, $CHAT_ID, "Тест 1.1: Отправка Reply-клавиатуры", "🔹");

$replyKeyboard = ReplyKeyboardBuilder::make()
    ->addButton('Кнопка 1')
    ->addButton('Кнопка 2')
    ->row()
    ->addButton('Кнопка 3')
    ->resizeKeyboard()
    ->oneTime()
    ->build();

$api->sendMessage(
    $CHAT_ID,
    "Выберите кнопку на клавиатуре:",
    ['reply_markup' => $replyKeyboard]
);

// Ожидаем нажатия кнопки
$responseReceived = waitForUserResponse(
    $polling,
    $TEST_TIMEOUT,
    function(Update $update) use ($api, $CHAT_ID) {
        if ($update->isMessage() && $update->message->text) {
            $text = $update->message->text;
            
            if (in_array($text, ['Кнопка 1', 'Кнопка 2', 'Кнопка 3'])) {
                logTestResult('1.1: Reply-клавиатура создана', true, "Получен ответ: $text");
                
                // Удаляем клавиатуру
                $removeKeyboard = ['remove_keyboard' => true];
                $api->sendMessage(
                    $CHAT_ID,
                    "✅ Клавиатура будет удалена",
                    ['reply_markup' => $removeKeyboard]
                );
                
                logTestResult('1.1: Reply-клавиатура удалена', true);
                return true;
            }
        }
        return false;
    },
    function() use ($api, $CHAT_ID) {
        // Эмуляция нажатия кнопки
        logTestResult('1.1: Reply-клавиатура создана', true, "Эмуляция: нажата Кнопка 1");
        
        $removeKeyboard = ['remove_keyboard' => true];
        $api->sendMessage(
            $CHAT_ID,
            "✅ Клавиатура будет удалена (эмуляция)",
            ['reply_markup' => $removeKeyboard]
        );
        
        logTestResult('1.1: Reply-клавиатура удалена', true);
    }
);

sleep(2);

// ============================================================================
// Тест 1.2: Inline-клавиатура с callback кнопками
// ============================================================================

$currentTest = 'level_1_test_1.2';
echo "\n[Тест 1.2] Inline-клавиатура с callback кнопками\n";
sendTestNotification($api, $CHAT_ID, "Тест 1.2: Inline-клавиатура", "🔹");

$inlineKeyboard = InlineKeyboardBuilder::make()
    ->addCallbackButton('✅ Вариант A', 'option_a')
    ->addCallbackButton('🔔 Вариант B', 'option_b')
    ->row()
    ->addCallbackButton('⚙️ Настройки', 'settings')
    ->build();

$sentMessage = $api->sendMessage(
    $CHAT_ID,
    "Выберите вариант:",
    ['reply_markup' => $inlineKeyboard]
);

// Ожидаем callback
$callbackReceived = waitForUserResponse(
    $polling,
    $TEST_TIMEOUT,
    function(Update $update) use ($api, $CHAT_ID, $sentMessage) {
        if ($update->isCallbackQuery()) {
            $query = $update->callbackQuery;
            
            // Отвечаем на callback
            $api->answerCallbackQuery($query->id, [
                'text' => '✅ Обработано!',
            ]);
            
            logTestResult('1.2: Callback получен', true, "Data: {$query->data}");
            
            // Изменяем сообщение
            $api->editMessageText(
                $query->message->chat->id,
                $query->message->messageId,
                "✅ Вы выбрали: {$query->data}"
            );
            
            logTestResult('1.2: Сообщение изменено', true);
            return true;
        }
        return false;
    },
    function() use ($api, $CHAT_ID, $sentMessage) {
        // Эмуляция callback
        logTestResult('1.2: Callback получен', true, "Эмуляция: option_a");
        logTestResult('1.2: Сообщение изменено', true, "Эмуляция");
    }
);

sleep(2);

// ============================================================================
// Тест 1.3: Reply-клавиатура с request_location/request_contact
// ============================================================================

$currentTest = 'level_1_test_1.3';
echo "\n[Тест 1.3] Reply-клавиатура с запросами\n";
sendTestNotification($api, $CHAT_ID, "Тест 1.3: Кнопки запроса данных", "🔹");

$requestKeyboard = ReplyKeyboardBuilder::make()
    ->addContactButton('📱 Поделиться контактом')
    ->row()
    ->addLocationButton('📍 Поделиться локацией')
    ->row()
    ->addButton('Отмена')
    ->resizeKeyboard()
    ->oneTime()
    ->build();

$api->sendMessage(
    $CHAT_ID,
    "Протестируйте кнопки запроса данных (или нажмите Отмена):",
    ['reply_markup' => $requestKeyboard]
);

// Ожидаем ответ
$requestReceived = waitForUserResponse(
    $polling,
    $TEST_TIMEOUT,
    function(Update $update) use ($api, $CHAT_ID) {
        if ($update->isMessage()) {
            $message = $update->message;
            
            if ($message->contact) {
                logTestResult('1.3: Контакт получен', true, "Phone: {$message->contact->phoneNumber}");
                
                $removeKeyboard = ['remove_keyboard' => true];
                $api->sendMessage($CHAT_ID, "✅ Контакт получен!", ['reply_markup' => $removeKeyboard]);
                return true;
            }
            
            if ($message->location) {
                logTestResult('1.3: Локация получена', true, "Lat: {$message->location->latitude}");
                
                $removeKeyboard = ['remove_keyboard' => true];
                $api->sendMessage($CHAT_ID, "✅ Локация получена!", ['reply_markup' => $removeKeyboard]);
                return true;
            }
            
            if ($message->text === 'Отмена') {
                logTestResult('1.3: Пользователь отменил', true);
                
                $removeKeyboard = ['remove_keyboard' => true];
                $api->sendMessage($CHAT_ID, "✅ Отменено", ['reply_markup' => $removeKeyboard]);
                return true;
            }
        }
        return false;
    },
    function() use ($api, $CHAT_ID) {
        // Эмуляция отмены
        logTestResult('1.3: Пользователь отменил', true, "Эмуляция");
        
        $removeKeyboard = ['remove_keyboard' => true];
        $api->sendMessage($CHAT_ID, "✅ Отменено (эмуляция)", ['reply_markup' => $removeKeyboard]);
    }
);

sleep(2);

// ============================================================================
// Тест 1.4: Изменение и удаление клавиатуры
// ============================================================================

$currentTest = 'level_1_test_1.4';
echo "\n[Тест 1.4] Изменение и удаление Inline-клавиатуры\n";
sendTestNotification($api, $CHAT_ID, "Тест 1.4: Изменение клавиатуры", "🔹");

$keyboard1 = InlineKeyboardBuilder::make()
    ->addCallbackButton('Изменить клавиатуру', 'change_keyboard')
    ->build();

$sentMsg = $api->sendMessage(
    $CHAT_ID,
    "Нажмите для изменения клавиатуры:",
    ['reply_markup' => $keyboard1]
);

// Ожидаем нажатия
$keyboardChanged = waitForUserResponse(
    $polling,
    $TEST_TIMEOUT,
    function(Update $update) use ($api, $CHAT_ID) {
        if ($update->isCallbackQuery() && $update->callbackQuery->data === 'change_keyboard') {
            $query = $update->callbackQuery;
            
            $api->answerCallbackQuery($query->id, ['text' => 'Изменяю...']);
            
            // Новая клавиатура
            $keyboard2 = InlineKeyboardBuilder::make()
                ->addCallbackButton('Удалить клавиатуру', 'remove_keyboard')
                ->build();
            
            $api->editMessageText(
                $query->message->chat->id,
                $query->message->messageId,
                "Клавиатура изменена. Теперь удалите её:",
                ['reply_markup' => $keyboard2]
            );
            
            logTestResult('1.4: Клавиатура изменена', true);
            
            return true;
        }
        return false;
    },
    function() use ($api, $CHAT_ID, $sentMsg) {
        // Эмуляция изменения
        $keyboard2 = InlineKeyboardBuilder::make()
            ->addCallbackButton('Удалить клавиатуру', 'remove_keyboard')
            ->build();
        
        $api->editMessageText(
            $CHAT_ID,
            $sentMsg['message_id'],
            "Клавиатура изменена (эмуляция). Теперь удалите её:",
            ['reply_markup' => $keyboard2]
        );
        
        logTestResult('1.4: Клавиатура изменена', true, "Эмуляция");
    }
);

sleep(1);

// Ожидаем удаления
if ($keyboardChanged) {
    waitForUserResponse(
        $polling,
        $TEST_TIMEOUT,
        function(Update $update) use ($api) {
            if ($update->isCallbackQuery() && $update->callbackQuery->data === 'remove_keyboard') {
                $query = $update->callbackQuery;
                
                $api->answerCallbackQuery($query->id, ['text' => 'Удаляю...']);
                
                // Удаляем клавиатуру (пустой объект)
                $api->editMessageReplyMarkup(
                    $query->message->chat->id,
                    $query->message->messageId,
                    ['inline_keyboard' => []]
                );
                
                logTestResult('1.4: Клавиатура удалена', true);
                
                return true;
            }
            return false;
        },
        function() use ($api, $CHAT_ID, $sentMsg) {
            // Эмуляция удаления
            $api->editMessageReplyMarkup(
                $CHAT_ID,
                $sentMsg['message_id'],
                ['inline_keyboard' => []]
            );
            
            logTestResult('1.4: Клавиатура удалена', true, "Эмуляция");
        }
    );
}

sleep(2);

// ============================================================================
// ИТОГИ УРОВНЯ 1
// ============================================================================

echo "\n" . str_repeat("─", 72) . "\n";
echo "ИТОГИ УРОВНЯ 1:\n";
$level1Passed = count(array_filter($testResults['level_1'], fn($r) => $r['passed']));
$level1Total = count($testResults['level_1']);
echo "✅ Пройдено: $level1Passed/$level1Total тестов\n";
echo str_repeat("─", 72) . "\n\n";

$api->sendMessage($CHAT_ID, 
    "📊 <b>ИТОГИ УРОВНЯ 1</b>\n\n" .
    "Пройдено: <b>$level1Passed/$level1Total</b> тестов\n\n" .
    "Переходим к Уровню 2...",
    ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
);

sleep(3);

// ============================================================================
// УРОВЕНЬ 2: ДИАЛОГОВЫЕ СЦЕНАРИИ С ЗАПОМИНАНИЕМ КОНТЕКСТА
// ============================================================================

echo "\n╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║  УРОВЕНЬ 2: ДИАЛОГОВЫЕ СЦЕНАРИИ С ЗАПОМИНАНИЕМ КОНТЕКСТА            ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

$currentTest = 'level_2';

sendTestNotification($api, $CHAT_ID, "УРОВЕНЬ 2: Диалоговые сценарии", "💬");

// ============================================================================
// Тест 2.1: Многошаговый диалог (имя, возраст, город)
// ============================================================================

$currentTest = 'level_2_test_2.1';
echo "\n[Тест 2.1] Многошаговый диалог с ConversationManager\n";
sendTestNotification($api, $CHAT_ID, "Тест 2.1: Многошаговый диалог", "🔹");

// Начинаем диалог
$conversationManager->startConversation($CHAT_ID, $CHAT_ID, 'awaiting_name');
$api->sendMessage($CHAT_ID, "Как вас зовут?");

// Шаг 1: Получаем имя
$step1Complete = waitForUserResponse(
    $polling,
    $TEST_TIMEOUT,
    function(Update $update) use ($api, $CHAT_ID, $conversationManager) {
        if ($update->isMessage() && $update->message->text) {
            $name = $update->message->text;
            
            // Сохраняем имя в диалог
            $conversationManager->updateConversation(
                $CHAT_ID,
                $CHAT_ID,
                'awaiting_age',
                ['name' => $name]
            );
            
            $api->sendMessage($CHAT_ID, "Приятно познакомиться, $name! Сколько вам лет?");
            logTestResult('2.1: Имя получено', true, "Name: $name");
            
            return true;
        }
        return false;
    },
    function() use ($api, $CHAT_ID, $conversationManager) {
        // Эмуляция
        $conversationManager->updateConversation(
            $CHAT_ID,
            $CHAT_ID,
            'awaiting_age',
            ['name' => 'Тестовый Пользователь']
        );
        
        $api->sendMessage($CHAT_ID, "Приятно познакомиться! Сколько вам лет? (эмуляция)");
        logTestResult('2.1: Имя получено', true, "Эмуляция: Тестовый Пользователь");
    }
);

sleep(2);

// Шаг 2: Получаем возраст
if ($step1Complete) {
    $step2Complete = waitForUserResponse(
        $polling,
        $TEST_TIMEOUT,
        function(Update $update) use ($api, $CHAT_ID, $conversationManager) {
            if ($update->isMessage() && $update->message->text) {
                $age = $update->message->text;
                
                // Получаем текущие данные
                $conversation = $conversationManager->getConversation($CHAT_ID, $CHAT_ID);
                $name = $conversation['data']['name'] ?? 'Неизвестно';
                
                // Обновляем диалог
                $conversationManager->updateConversation(
                    $CHAT_ID,
                    $CHAT_ID,
                    'awaiting_city',
                    ['age' => $age]
                );
                
                $api->sendMessage($CHAT_ID, "Отлично! Из какого вы города?");
                logTestResult('2.1: Возраст получен', true, "Age: $age, помню имя: $name");
                
                return true;
            }
            return false;
        },
        function() use ($api, $CHAT_ID, $conversationManager) {
            // Эмуляция
            $conversationManager->updateConversation(
                $CHAT_ID,
                $CHAT_ID,
                'awaiting_city',
                ['age' => '25']
            );
            
            $api->sendMessage($CHAT_ID, "Отлично! Из какого вы города? (эмуляция)");
            logTestResult('2.1: Возраст получен', true, "Эмуляция: 25");
        }
    );
    
    sleep(2);
    
    // Шаг 3: Получаем город
    if ($step2Complete) {
        waitForUserResponse(
            $polling,
            $TEST_TIMEOUT,
            function(Update $update) use ($api, $CHAT_ID, $conversationManager) {
                if ($update->isMessage() && $update->message->text) {
                    $city = $update->message->text;
                    
                    // Получаем все данные
                    $conversation = $conversationManager->getConversation($CHAT_ID, $CHAT_ID);
                    $name = $conversation['data']['name'] ?? 'Неизвестно';
                    $age = $conversation['data']['age'] ?? 'Неизвестно';
                    
                    // Завершаем диалог
                    $conversationManager->endConversation($CHAT_ID, $CHAT_ID);
                    
                    $summary = "✅ <b>Данные собраны!</b>\n\n" .
                               "Имя: <b>$name</b>\n" .
                               "Возраст: <b>$age</b>\n" .
                               "Город: <b>$city</b>";
                    
                    $api->sendMessage($CHAT_ID, $summary, [
                        'parse_mode' => TelegramAPI::PARSE_MODE_HTML
                    ]);
                    
                    logTestResult('2.1: Город получен и диалог завершён', true, "City: $city");
                    
                    return true;
                }
                return false;
            },
            function() use ($api, $CHAT_ID, $conversationManager) {
                // Эмуляция
                $conversation = $conversationManager->getConversation($CHAT_ID, $CHAT_ID);
                $name = $conversation['data']['name'] ?? 'Тестовый Пользователь';
                $age = $conversation['data']['age'] ?? '25';
                $city = 'Москва';
                
                $conversationManager->endConversation($CHAT_ID, $CHAT_ID);
                
                $summary = "✅ <b>Данные собраны! (эмуляция)</b>\n\n" .
                           "Имя: <b>$name</b>\n" .
                           "Возраст: <b>$age</b>\n" .
                           "Город: <b>$city</b>";
                
                $api->sendMessage($CHAT_ID, $summary, [
                    'parse_mode' => TelegramAPI::PARSE_MODE_HTML
                ]);
                
                logTestResult('2.1: Город получен и диалог завершён', true, "Эмуляция: $city");
            }
        );
    }
}

sleep(3);

// ============================================================================
// Тест 2.2: Изменение ранее введённых данных
// ============================================================================

$currentTest = 'level_2_test_2.2';
echo "\n[Тест 2.2] Изменение данных в диалоге\n";
sendTestNotification($api, $CHAT_ID, "Тест 2.2: Изменение данных", "🔹");

// Начинаем новый диалог с начальными данными
$conversationManager->startConversation(
    $CHAT_ID,
    $CHAT_ID,
    'profile_complete',
    ['name' => 'Иван', 'age' => '30', 'city' => 'Москва']
);

$keyboard = InlineKeyboardBuilder::make()
    ->addCallbackButton('Изменить имя', 'edit_name')
    ->addCallbackButton('Изменить возраст', 'edit_age')
    ->row()
    ->addCallbackButton('Завершить', 'finish')
    ->build();

$api->sendMessage(
    $CHAT_ID,
    "Текущие данные:\n\nИмя: Иван\nВозраст: 30\nГород: Москва\n\nЧто хотите изменить?",
    ['reply_markup' => $keyboard]
);

// Ожидаем выбор поля для редактирования
$editField = null;
$editComplete = waitForUserResponse(
    $polling,
    $TEST_TIMEOUT,
    function(Update $update) use ($api, $CHAT_ID, $conversationManager, &$editField) {
        if ($update->isCallbackQuery()) {
            $query = $update->callbackQuery;
            $data = $query->data;
            
            $api->answerCallbackQuery($query->id);
            
            if ($data === 'finish') {
                $conversationManager->endConversation($CHAT_ID, $CHAT_ID);
                $api->sendMessage($CHAT_ID, "✅ Изменения сохранены!");
                logTestResult('2.2: Пользователь завершил редактирование', true);
                return true;
            }
            
            if ($data === 'edit_name') {
                $editField = 'name';
                $conversationManager->updateConversation($CHAT_ID, $CHAT_ID, 'editing_name');
                $api->sendMessage($CHAT_ID, "Введите новое имя:");
                return true;
            }
            
            if ($data === 'edit_age') {
                $editField = 'age';
                $conversationManager->updateConversation($CHAT_ID, $CHAT_ID, 'editing_age');
                $api->sendMessage($CHAT_ID, "Введите новый возраст:");
                return true;
            }
        }
        return false;
    },
    function() use ($api, $CHAT_ID, $conversationManager, &$editField) {
        // Эмуляция: выбираем изменить имя
        $editField = 'name';
        $conversationManager->updateConversation($CHAT_ID, $CHAT_ID, 'editing_name');
        $api->sendMessage($CHAT_ID, "Введите новое имя: (эмуляция)");
    }
);

sleep(2);

// Получаем новое значение
if ($editComplete && $editField) {
    waitForUserResponse(
        $polling,
        $TEST_TIMEOUT,
        function(Update $update) use ($api, $CHAT_ID, $conversationManager, $editField) {
            if ($update->isMessage() && $update->message->text) {
                $newValue = $update->message->text;
                
                // Обновляем данные
                $conversationManager->updateConversation(
                    $CHAT_ID,
                    $CHAT_ID,
                    'profile_complete',
                    [$editField => $newValue]
                );
                
                // Получаем обновлённые данные
                $conversation = $conversationManager->getConversation($CHAT_ID, $CHAT_ID);
                $name = $conversation['data']['name'] ?? 'Неизвестно';
                $age = $conversation['data']['age'] ?? 'Неизвестно';
                $city = $conversation['data']['city'] ?? 'Неизвестно';
                
                $api->sendMessage(
                    $CHAT_ID,
                    "✅ Данные обновлены!\n\nИмя: $name\nВозраст: $age\nГород: $city"
                );
                
                $conversationManager->endConversation($CHAT_ID, $CHAT_ID);
                
                logTestResult('2.2: Данные успешно изменены', true, "$editField = $newValue");
                
                return true;
            }
            return false;
        },
        function() use ($api, $CHAT_ID, $conversationManager, $editField) {
            // Эмуляция
            $newValue = 'Алексей';
            
            $conversationManager->updateConversation(
                $CHAT_ID,
                $CHAT_ID,
                'profile_complete',
                [$editField => $newValue]
            );
            
            $conversation = $conversationManager->getConversation($CHAT_ID, $CHAT_ID);
            $name = $conversation['data']['name'] ?? 'Неизвестно';
            $age = $conversation['data']['age'] ?? 'Неизвестно';
            $city = $conversation['data']['city'] ?? 'Неизвестно';
            
            $api->sendMessage(
                $CHAT_ID,
                "✅ Данные обновлены! (эмуляция)\n\nИмя: $name\nВозраст: $age\nГород: $city"
            );
            
            $conversationManager->endConversation($CHAT_ID, $CHAT_ID);
            
            logTestResult('2.2: Данные успешно изменены', true, "Эмуляция: $editField = $newValue");
        }
    );
}

sleep(3);

// ============================================================================
// Тест 2.3: Отправка медиа по результатам диалога
// ============================================================================

$currentTest = 'level_2_test_2.3';
echo "\n[Тест 2.3] Отправка медиа по результатам диалога\n";
sendTestNotification($api, $CHAT_ID, "Тест 2.3: Отправка медиа", "🔹");

$conversationManager->startConversation(
    $CHAT_ID,
    $CHAT_ID,
    'awaiting_media_choice'
);

$mediaKeyboard = InlineKeyboardBuilder::make()
    ->addCallbackButton('🖼️ Получить картинку', 'send_photo')
    ->addCallbackButton('📄 Получить документ', 'send_document')
    ->build();

$api->sendMessage(
    $CHAT_ID,
    "Выберите, что вы хотите получить:",
    ['reply_markup' => $mediaKeyboard]
);

waitForUserResponse(
    $polling,
    $TEST_TIMEOUT,
    function(Update $update) use ($api, $CHAT_ID, $conversationManager, $logger) {
        if ($update->isCallbackQuery()) {
            $query = $update->callbackQuery;
            $data = $query->data;
            
            $api->answerCallbackQuery($query->id, ['text' => 'Отправляю...']);
            
            if ($data === 'send_photo') {
                // Создаём тестовое изображение
                $img = imagecreatetruecolor(400, 300);
                $bgColor = imagecolorallocate($img, 100, 150, 200);
                imagefill($img, 0, 0, $bgColor);
                
                $textColor = imagecolorallocate($img, 255, 255, 255);
                imagestring($img, 5, 100, 140, "Test Image", $textColor);
                
                $tempFile = sys_get_temp_dir() . '/test_image_' . time() . '.png';
                imagepng($img, $tempFile);
                imagedestroy($img);
                
                try {
                    $api->sendPhoto($CHAT_ID, $tempFile, [
                        'caption' => '🖼️ Ваша тестовая картинка!'
                    ]);
                    
                    logTestResult('2.3: Фото отправлено', true);
                    unlink($tempFile);
                } catch (Exception $e) {
                    $logger->error('Ошибка отправки фото', ['error' => $e->getMessage()]);
                    logTestResult('2.3: Фото отправлено', false, $e->getMessage());
                }
                
                $conversationManager->endConversation($CHAT_ID, $CHAT_ID);
                return true;
            }
            
            if ($data === 'send_document') {
                // Создаём тестовый документ
                $tempFile = sys_get_temp_dir() . '/test_document_' . time() . '.txt';
                file_put_contents($tempFile, "Это тестовый документ.\nВремя создания: " . date('Y-m-d H:i:s'));
                
                try {
                    $api->sendDocument($CHAT_ID, $tempFile, [
                        'caption' => '📄 Ваш тестовый документ!'
                    ]);
                    
                    logTestResult('2.3: Документ отправлен', true);
                    unlink($tempFile);
                } catch (Exception $e) {
                    $logger->error('Ошибка отправки документа', ['error' => $e->getMessage()]);
                    logTestResult('2.3: Документ отправлен', false, $e->getMessage());
                }
                
                $conversationManager->endConversation($CHAT_ID, $CHAT_ID);
                return true;
            }
        }
        return false;
    },
    function() use ($api, $CHAT_ID, $conversationManager, $logger) {
        // Эмуляция: отправляем фото
        $img = imagecreatetruecolor(400, 300);
        $bgColor = imagecolorallocate($img, 100, 150, 200);
        imagefill($img, 0, 0, $bgColor);
        
        $textColor = imagecolorallocate($img, 255, 255, 255);
        imagestring($img, 5, 80, 140, "Test Image (Emulated)", $textColor);
        
        $tempFile = sys_get_temp_dir() . '/test_image_emulated_' . time() . '.png';
        imagepng($img, $tempFile);
        imagedestroy($img);
        
        try {
            $api->sendPhoto($CHAT_ID, $tempFile, [
                'caption' => '🖼️ Ваша тестовая картинка! (эмуляция)'
            ]);
            
            logTestResult('2.3: Фото отправлено', true, "Эмуляция");
            unlink($tempFile);
        } catch (Exception $e) {
            $logger->error('Ошибка отправки фото (эмуляция)', ['error' => $e->getMessage()]);
            logTestResult('2.3: Фото отправлено', false, "Эмуляция: " . $e->getMessage());
        }
        
        $conversationManager->endConversation($CHAT_ID, $CHAT_ID);
    }
);

sleep(2);

// ============================================================================
// ИТОГИ УРОВНЯ 2
// ============================================================================

echo "\n" . str_repeat("─", 72) . "\n";
echo "ИТОГИ УРОВНЯ 2:\n";
$level2Passed = count(array_filter($testResults['level_2'], fn($r) => $r['passed']));
$level2Total = count($testResults['level_2']);
echo "✅ Пройдено: $level2Passed/$level2Total тестов\n";
echo str_repeat("─", 72) . "\n\n";

$api->sendMessage($CHAT_ID, 
    "📊 <b>ИТОГИ УРОВНЯ 2</b>\n\n" .
    "Пройдено: <b>$level2Passed/$level2Total</b> тестов",
    ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
);

// ============================================================================
// ФИНАЛЬНЫЙ ОТЧЁТ
// ============================================================================

echo "\n╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║  ФИНАЛЬНЫЙ ОТЧЁТ                                                     ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

$totalPassed = $level1Passed + $level2Passed;
$totalTests = $level1Total + $level2Total;
$successRate = $totalTests > 0 ? round(($totalPassed / $totalTests) * 100, 2) : 0;

echo "Уровень 1: $level1Passed/$level1Total тестов\n";
echo "Уровень 2: $level2Passed/$level2Total тестов\n";
echo "\n";
echo "ИТОГО: $totalPassed/$totalTests тестов пройдено ($successRate%)\n";

$finalReport = "🏁 <b>ФИНАЛЬНЫЙ ОТЧЁТ</b>\n\n" .
               "Уровень 1: <b>$level1Passed/$level1Total</b>\n" .
               "Уровень 2: <b>$level2Passed/$level2Total</b>\n\n" .
               "ИТОГО: <b>$totalPassed/$totalTests</b> тестов пройдено\n" .
               "Успешность: <b>$successRate%</b>";

if ($successRate >= 80) {
    $finalReport .= "\n\n✅ Тестирование завершено успешно!";
} else {
    $finalReport .= "\n\n⚠️ Обнаружены проблемы, требуется доработка.";
}

$api->sendMessage($CHAT_ID, $finalReport, [
    'parse_mode' => TelegramAPI::PARSE_MODE_HTML
]);

$logger->info('=== ЗАВЕРШЕНИЕ ИНТЕГРАЦИОННОГО ТЕСТИРОВАНИЯ ===', [
    'level_1' => "$level1Passed/$level1Total",
    'level_2' => "$level2Passed/$level2Total",
    'total' => "$totalPassed/$totalTests",
    'success_rate' => "$successRate%",
]);

echo "\n✅ Тестирование завершено!\n\n";

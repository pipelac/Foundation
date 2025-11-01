<?php

declare(strict_types=1);

/**
 * Автоматический тест TelegramBot Polling
 * Проходит все уровни тестирования без участия пользователя
 */

require_once __DIR__ . '/autoload.php';

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\Http;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Core\PollingHandler;
use App\Component\TelegramBot\Core\MessageStorage;
use App\Component\TelegramBot\Core\ConversationManager;
use App\Component\TelegramBot\Keyboards\InlineKeyboardBuilder;
use App\Component\TelegramBot\Keyboards\ReplyKeyboardBuilder;

// Конфигурация
$config = [
    'bot_token' => '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI',
    'test_chat_id' => 366442475,
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'telegram_bot_test',
        'username' => 'telegram_bot',
        'password' => 'test_password_123',
        'charset' => 'utf8mb4',
    ],
];

// Инициализация
$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'file_name' => 'automated_polling_test.log',
]);

echo "🚀 Автоматическое тестирование TelegramBot (Polling)\n\n";

try {
    $db = new MySQL($config['db'], $logger);
    echo "✅ MySQL подключен\n";
} catch (\Exception $e) {
    echo "❌ Ошибка MySQL: " . $e->getMessage() . "\n";
    exit(1);
}

$http = new Http([], $logger);
$messageStorage = new MessageStorage($db, $logger, ['enabled' => true, 'auto_create_tables' => true]);
$conversationManager = new ConversationManager($db, $logger, ['enabled' => true, 'auto_create_tables' => true]);
$api = new TelegramAPI($config['bot_token'], $http, $logger, $messageStorage);
$polling = new PollingHandler($api, $logger);

// Результаты
$results = [
    'passed' => 0,
    'failed' => 0,
    'tests' => [],
];

function testResult(string $name, bool $success, string $details = ''): void {
    global $results, $api, $config;
    
    $status = $success ? '✅ PASS' : '❌ FAIL';
    $results['tests'][] = [
        'name' => $name,
        'success' => $success,
        'details' => $details
    ];
    
    if ($success) {
        $results['passed']++;
    } else {
        $results['failed']++;
    }
    
    echo "$status - $name\n";
    if ($details) {
        echo "  → $details\n";
    }
    
    // Отправка уведомления в Telegram
    try {
        $api->sendMessage($config['test_chat_id'], "$status $name\n\n$details");
    } catch (\Exception $e) {
        // Игнорируем ошибки отправки уведомлений
    }
}

// === ТЕСТИРОВАНИЕ ===

echo "\n📋 УРОВЕНЬ 1: Начальные операции\n";
echo str_repeat('=', 50) . "\n";

try {
    $api->sendMessage($config['test_chat_id'], "🧪 Тестирование уровень 1: Начальные операции");
} catch (\Exception $e) {
    // Игнорируем
}

// Тест 1.1: Простое сообщение
try {
    $msg = $api->sendMessage($config['test_chat_id'], "Тест 1.1: Простое текстовое сообщение");
    testResult('1.1 - Отправка простого сообщения', true, "Message ID: {$msg->messageId}");
} catch (\Exception $e) {
    testResult('1.1 - Отправка простого сообщения', false, $e->getMessage());
}

sleep(1);

// Тест 1.2: Сообщение с эмодзи
try {
    $msg = $api->sendMessage($config['test_chat_id'], "Тест 1.2: Эмодзи 😀 🚀 💯 ✨");
    testResult('1.2 - Отправка сообщения с эмодзи', true, "Message ID: {$msg->messageId}");
} catch (\Exception $e) {
    testResult('1.2 - Отправка сообщения с эмодзи', false, $e->getMessage());
}

sleep(1);

// Тест 1.3: HTML форматирование
try {
    $msg = $api->sendMessage($config['test_chat_id'], "Тест 1.3: <b>Жирный</b> <i>курсив</i> <code>код</code>", ['parse_mode' => 'HTML']);
    testResult('1.3 - HTML форматирование', true, "Message ID: {$msg->messageId}");
} catch (\Exception $e) {
    testResult('1.3 - HTML форматирование', false, $e->getMessage());
}

echo "\n📋 УРОВЕНЬ 2: Клавиатуры\n";
echo str_repeat('=', 50) . "\n";

try {
    $api->sendMessage($config['test_chat_id'], "🧪 Тестирование уровень 2: Клавиатуры");
} catch (\Exception $e) {
    // Игнорируем
}

sleep(1);

// Тест 2.1: Inline клавиатура
try {
    $keyboard = InlineKeyboardBuilder::make()
        ->addCallbackButton('Кнопка 1', 'test_btn_1')
        ->addCallbackButton('Кнопка 2', 'test_btn_2')
        ->row()
        ->addUrlButton('Ссылка', 'https://example.com');
    
    $msg = $api->sendMessage(
        $config['test_chat_id'],
        "Тест 2.1: Inline клавиатура",
        ['reply_markup' => $keyboard->build()]
    );
    testResult('2.1 - Создание Inline клавиатуры', true, "Message ID: {$msg->messageId}");
} catch (\Exception $e) {
    testResult('2.1 - Создание Inline клавиатуры', false, $e->getMessage());
}

sleep(1);

// Тест 2.2: Reply клавиатура
try {
    $keyboard = ReplyKeyboardBuilder::make()
        ->addButton('Опция A')
        ->addButton('Опция B')
        ->row()
        ->addButton('Отмена')
        ->resizeKeyboard();
    
    $msg = $api->sendMessage(
        $config['test_chat_id'],
        "Тест 2.2: Reply клавиатура",
        ['reply_markup' => $keyboard->build()]
    );
    testResult('2.2 - Создание Reply клавиатуры', true, "Message ID: {$msg->messageId}");
} catch (\Exception $e) {
    testResult('2.2 - Создание Reply клавиатуры', false, $e->getMessage());
}

sleep(1);

// Тест 2.3: Удаление клавиатуры
try {
    $msg = $api->sendMessage(
        $config['test_chat_id'],
        "Тест 2.3: Удаление клавиатуры",
        ['reply_markup' => ['remove_keyboard' => true]]
    );
    testResult('2.3 - Удаление клавиатуры', true, "Message ID: {$msg->messageId}");
} catch (\Exception $e) {
    testResult('2.3 - Удаление клавиатуры', false, $e->getMessage());
}

echo "\n📋 УРОВЕНЬ 3: Редактирование сообщений\n";
echo str_repeat('=', 50) . "\n";

try {
    $api->sendMessage($config['test_chat_id'], "🧪 Тестирование уровень 3: Редактирование");
} catch (\Exception $e) {
    // Игнорируем
}

sleep(1);

// Тест 3.1: Редактирование текста
try {
    $msg = $api->sendMessage($config['test_chat_id'], "Тест 3.1: Исходный текст");
    sleep(1);
    $edited = $api->editMessageText($config['test_chat_id'], $msg->messageId, "Тест 3.1: Отредактированный текст ✏️");
    testResult('3.1 - Редактирование сообщения', true, "Edited message ID: {$edited->messageId}");
} catch (\Exception $e) {
    testResult('3.1 - Редактирование сообщения', false, $e->getMessage());
}

sleep(1);

// Тест 3.2: Удаление сообщения
try {
    $msg = $api->sendMessage($config['test_chat_id'], "Тест 3.2: Сообщение будет удалено");
    sleep(1);
    $deleted = $api->deleteMessage($config['test_chat_id'], $msg->messageId);
    testResult('3.2 - Удаление сообщения', $deleted, "Deleted: " . ($deleted ? 'Yes' : 'No'));
} catch (\Exception $e) {
    testResult('3.2 - Удаление сообщения', false, $e->getMessage());
}

echo "\n📋 УРОВЕНЬ 4: Работа с базой данных\n";
echo str_repeat('=', 50) . "\n";

try {
    $api->sendMessage($config['test_chat_id'], "🧪 Тестирование уровень 4: База данных");
} catch (\Exception $e) {
    // Игнорируем
}

sleep(1);

// Тест 4.1: Сохранение пользователя
try {
    $success = $conversationManager->saveUser(
        $config['test_chat_id'],
        'Test User',
        'test_user',
        'TestLastName'
    );
    testResult('4.1 - Сохранение пользователя', $success, $success ? 'User saved' : 'Failed to save');
} catch (\Exception $e) {
    testResult('4.1 - Сохранение пользователя', false, $e->getMessage());
}

// Тест 4.2: Получение пользователя
try {
    $user = $conversationManager->getUser($config['test_chat_id']);
    testResult('4.2 - Получение пользователя', $user !== null, $user ? "User: {$user['first_name']}" : 'Not found');
} catch (\Exception $e) {
    testResult('4.2 - Получение пользователя', false, $e->getMessage());
}

// Тест 4.3: Начало диалога
try {
    $convId = $conversationManager->startConversation(
        $config['test_chat_id'],
        $config['test_chat_id'],
        'test_state',
        ['step' => 1, 'data' => 'test']
    );
    testResult('4.3 - Начало диалога', $convId !== null, $convId ? "Conversation ID: $convId" : 'Failed');
} catch (\Exception $e) {
    testResult('4.3 - Начало диалога', false, $e->getMessage());
}

// Тест 4.4: Получение диалога
try {
    $conv = $conversationManager->getConversation($config['test_chat_id'], $config['test_chat_id']);
    testResult('4.4 - Получение диалога', $conv !== null, $conv ? "State: {$conv['state']}" : 'Not found');
} catch (\Exception $e) {
    testResult('4.4 - Получение диалога', false, $e->getMessage());
}

// Тест 4.5: Обновление диалога
try {
    $success = $conversationManager->updateConversation(
        $config['test_chat_id'],
        $config['test_chat_id'],
        'updated_state',
        ['step' => 2, 'updated' => true]
    );
    testResult('4.5 - Обновление диалога', $success, $success ? 'Updated' : 'Failed');
} catch (\Exception $e) {
    testResult('4.5 - Обновление диалога', false, $e->getMessage());
}

// Тест 4.6: Завершение диалога
try {
    $success = $conversationManager->endConversation($config['test_chat_id'], $config['test_chat_id']);
    testResult('4.6 - Завершение диалога', $success, $success ? 'Ended' : 'Failed');
} catch (\Exception $e) {
    testResult('4.6 - Завершение диалога', false, $e->getMessage());
}

echo "\n📋 УРОВЕНЬ 5: Обработка ошибок\n";
echo str_repeat('=', 50) . "\n";

try {
    $api->sendMessage($config['test_chat_id'], "🧪 Тестирование уровень 5: Обработка ошибок");
} catch (\Exception $e) {
    // Игнорируем
}

sleep(1);

// Тест 5.1: Пустое сообщение (должна быть ошибка)
try {
    $msg = $api->sendMessage($config['test_chat_id'], '');
    testResult('5.1 - Отправка пустого сообщения', false, 'Пустое сообщение было принято (не должно)');
} catch (\Exception $e) {
    testResult('5.1 - Отправка пустого сообщения', true, "Корректная ошибка: " . substr($e->getMessage(), 0, 50));
}

sleep(1);

// Тест 5.2: Слишком длинное сообщение
try {
    $longText = str_repeat('A', 5000);
    $msg = $api->sendMessage($config['test_chat_id'], $longText);
    testResult('5.2 - Слишком длинное сообщение', false, 'Длинное сообщение было принято');
} catch (\Exception $e) {
    testResult('5.2 - Слишком длинное сообщение', true, "Корректная ошибка: " . substr($e->getMessage(), 0, 50));
}

sleep(1);

// Тест 5.3: Невалидный chat_id
try {
    $msg = $api->sendMessage(-999999999, 'Test');
    testResult('5.3 - Невалидный chat_id', false, 'Невалидный chat_id был принят');
} catch (\Exception $e) {
    testResult('5.3 - Невалидный chat_id', true, "Корректная ошибка: " . substr($e->getMessage(), 0, 50));
}

echo "\n📋 УРОВЕНЬ 6: Статистика базы данных\n";
echo str_repeat('=', 50) . "\n";

try {
    $api->sendMessage($config['test_chat_id'], "🧪 Тестирование уровень 6: Статистика");
} catch (\Exception $e) {
    // Игнорируем
}

sleep(1);

// Тест 6.1: Статистика сообщений
try {
    $stats = $messageStorage->getStatistics($config['test_chat_id']);
    $total = $stats['total'];
    testResult('6.1 - Статистика сообщений', true, "Всего сообщений: $total");
} catch (\Exception $e) {
    testResult('6.1 - Статистика сообщений', false, $e->getMessage());
}

// Тест 6.2: Проверка таблиц БД
try {
    $tables = $db->query("SHOW TABLES LIKE 'telegram_bot%'");
    $count = count($tables);
    testResult('6.2 - Проверка таблиц БД', $count >= 3, "Найдено таблиц: $count");
} catch (\Exception $e) {
    testResult('6.2 - Проверка таблиц БД', false, $e->getMessage());
}

// === ФИНАЛЬНЫЙ ОТЧЕТ ===

echo "\n" . str_repeat('=', 50) . "\n";
echo "📊 ФИНАЛЬНЫЕ РЕЗУЛЬТАТЫ\n";
echo str_repeat('=', 50) . "\n";

$total = $results['passed'] + $results['failed'];
$percentage = $total > 0 ? round(($results['passed'] / $total) * 100, 2) : 0;

echo "Всего тестов: $total\n";
echo "✅ Пройдено: {$results['passed']}\n";
echo "❌ Провалено: {$results['failed']}\n";
echo "📈 Процент успеха: $percentage%\n\n";

// Статистика БД
$dbStats = $db->queryOne("SELECT 
    (SELECT COUNT(*) FROM telegram_bot_messages) as messages,
    (SELECT COUNT(*) FROM telegram_bot_users) as users,
    (SELECT COUNT(*) FROM telegram_bot_conversations) as conversations
");

echo "💾 Статистика MySQL:\n";
echo "• Сообщений: {$dbStats['messages']}\n";
echo "• Пользователей: {$dbStats['users']}\n";
echo "• Диалогов: {$dbStats['conversations']}\n\n";

// Отправка финального отчета в Telegram
$report = "🎉 ТЕСТИРОВАНИЕ ЗАВЕРШЕНО\n\n";
$report .= "📊 Результаты:\n";
$report .= "• Всего: $total\n";
$report .= "• Успешно: {$results['passed']}\n";
$report .= "• Провалено: {$results['failed']}\n";
$report .= "• Успех: $percentage%\n\n";
$report .= "💾 База данных:\n";
$report .= "• Сообщений: {$dbStats['messages']}\n";
$report .= "• Пользователей: {$dbStats['users']}\n";
$report .= "• Диалогов: {$dbStats['conversations']}\n";

try {
    $api->sendMessage($config['test_chat_id'], $report);
    echo "✅ Финальный отчет отправлен в Telegram\n";
} catch (\Exception $e) {
    echo "⚠️ Не удалось отправить финальный отчет: " . $e->getMessage() . "\n";
}

$logger->info('Автоматическое тестирование завершено', [
    'total' => $total,
    'passed' => $results['passed'],
    'failed' => $results['failed'],
    'percentage' => $percentage,
]);

echo "\n✅ Тестирование завершено!\n";
echo "📁 Логи: logs/automated_polling_test.log\n\n";

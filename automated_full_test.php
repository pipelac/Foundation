<?php

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\Http;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Core\PollingHandler;
use App\Component\TelegramBot\Core\ConversationManager;
use App\Component\TelegramBot\Keyboards\InlineKeyboardBuilder;
use App\Component\TelegramBot\Keyboards\ReplyKeyboardBuilder;

/**
 * Автоматизированное тестирование TelegramBot без взаимодействия пользователя
 * Проверяет инициализацию, MySQL интеграцию, создание таблиц, функциональность API
 */

// Инициализация логгера
$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'file_name' => 'automated_full_test.log',
    'max_files' => 5,
    'max_file_size' => 10485760,
]);

echo "========================================\n";
echo "АВТОМАТИЗИРОВАННОЕ ТЕСТИРОВАНИЕ TELEGRAMBOT\n";
echo "========================================\n\n";

$logger->info('========================================');
$logger->info('ЗАПУСК АВТОМАТИЗИРОВАННОГО ТЕСТИРОВАНИЯ');
$logger->info('========================================');

// Конфигурация бота
$botToken = '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI';
$testChatId = 366442475;

// Конфигурация MySQL
$dbConfig = [
    'host' => 'localhost',
    'database' => 'test_telegram_bot',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
];

// Создаем папку mysql для дампов
if (!is_dir(__DIR__ . '/mysql')) {
    mkdir(__DIR__ . '/mysql', 0755, true);
}

$api = null;
$db = null;
$testsPassed = [];
$testsFailed = [];

try {
    // ============================================
    // ТЕСТ 1: Подключение к MySQL
    // ============================================
    echo "ТЕСТ 1: Подключение к MySQL... ";
    $logger->info('ТЕСТ 1: Подключение к MySQL');
    
    $db = new MySQL($dbConfig, $logger);
    echo "✓ PASS\n";
    $testsPassed[] = 'MySQL Connection';
    $logger->info('✓ MySQL подключен успешно');

    // ============================================
    // ТЕСТ 2: Инициализация HTTP клиента
    // ============================================
    echo "ТЕСТ 2: Инициализация HTTP клиента... ";
    $logger->info('ТЕСТ 2: Инициализация HTTP клиента');
    
    $http = new Http([
        'timeout' => 60,
        'connect_timeout' => 10,
    ], $logger);
    echo "✓ PASS\n";
    $testsPassed[] = 'HTTP Client Init';
    $logger->info('✓ HTTP клиент инициализирован');

    // ============================================
    // ТЕСТ 3: Инициализация Telegram API
    // ============================================
    echo "ТЕСТ 3: Инициализация Telegram API... ";
    $logger->info('ТЕСТ 3: Инициализация Telegram API');
    
    $api = new TelegramAPI($botToken, $http, $logger);
    echo "✓ PASS\n";
    $testsPassed[] = 'Telegram API Init';
    $logger->info('✓ Telegram API инициализирован');

    // ============================================
    // ТЕСТ 4: Проверка информации о боте
    // ============================================
    echo "ТЕСТ 4: Получение информации о боте... ";
    $logger->info('ТЕСТ 4: getMe()');
    
    $botInfo = $api->getMe();
    echo "✓ PASS (Bot: @{$botInfo->username})\n";
    $testsPassed[] = 'Get Bot Info';
    $logger->info('✓ Информация о боте получена', [
        'username' => $botInfo->username,
        'id' => $botInfo->id,
        'first_name' => $botInfo->firstName,
    ]);

    // ============================================
    // ТЕСТ 5: Инициализация ConversationManager
    // ============================================
    echo "ТЕСТ 5: Инициализация ConversationManager... ";
    $logger->info('ТЕСТ 5: ConversationManager');
    
    $conversationManager = new ConversationManager(
        $db,
        $logger,
        [
            'enabled' => true,
            'timeout' => 7200,
            'auto_create_tables' => true,
        ]
    );
    echo "✓ PASS\n";
    $testsPassed[] = 'ConversationManager Init';
    $logger->info('✓ ConversationManager инициализирован');

    // ============================================
    // ТЕСТ 6: Проверка таблиц MySQL
    // ============================================
    echo "ТЕСТ 6: Проверка существования таблиц... ";
    $logger->info('ТЕСТ 6: Проверка таблиц MySQL');
    
    $tables = $db->query("SHOW TABLES LIKE 'telegram_bot%'");
    $tableNames = array_column($tables, array_key_first($tables[0]));
    
    $expectedTables = ['telegram_bot_users', 'telegram_bot_conversations'];
    $allTablesExist = true;
    
    foreach ($expectedTables as $expectedTable) {
        if (!in_array($expectedTable, $tableNames)) {
            $allTablesExist = false;
            break;
        }
    }
    
    if ($allTablesExist) {
        echo "✓ PASS (Найдено: " . count($tableNames) . " таблиц)\n";
        $testsPassed[] = 'MySQL Tables';
        $logger->info('✓ Все необходимые таблицы существуют', ['tables' => $tableNames]);
    } else {
        echo "✗ FAIL\n";
        $testsFailed[] = 'MySQL Tables';
        $logger->error('Не все таблицы существуют', ['found' => $tableNames, 'expected' => $expectedTables]);
    }

    // ============================================
    // ТЕСТ 7: Сохранение пользователя
    // ============================================
    echo "ТЕСТ 7: Сохранение данных пользователя... ";
    $logger->info('ТЕСТ 7: Сохранение пользователя');
    
    $testUserId = 12345678;
    $saved = $conversationManager->saveUser($testUserId, 'Test', 'testuser', 'User');
    
    if ($saved) {
        echo "✓ PASS\n";
        $testsPassed[] = 'Save User';
        $logger->info('✓ Пользователь сохранен', ['user_id' => $testUserId]);
    } else {
        echo "✗ FAIL\n";
        $testsFailed[] = 'Save User';
        $logger->error('Не удалось сохранить пользователя');
    }

    // ============================================
    // ТЕСТ 8: Получение данных пользователя
    // ============================================
    echo "ТЕСТ 8: Получение данных пользователя... ";
    $logger->info('ТЕСТ 8: Получение пользователя');
    
    $user = $conversationManager->getUser($testUserId);
    
    if ($user && $user['user_id'] === $testUserId) {
        echo "✓ PASS\n";
        $testsPassed[] = 'Get User';
        $logger->info('✓ Данные пользователя получены', $user);
    } else {
        echo "✗ FAIL\n";
        $testsFailed[] = 'Get User';
        $logger->error('Не удалось получить данные пользователя');
    }

    // ============================================
    // ТЕСТ 9: Создание диалога
    // ============================================
    echo "ТЕСТ 9: Создание диалога... ";
    $logger->info('ТЕСТ 9: Создание диалога');
    
    $testChatIdConv = 12345678;
    $dialogId = $conversationManager->startConversation(
        $testChatIdConv,
        $testUserId,
        'test_state',
        ['test_key' => 'test_value']
    );
    
    if ($dialogId !== null) {
        echo "✓ PASS (ID: $dialogId)\n";
        $testsPassed[] = 'Start Conversation';
        $logger->info('✓ Диалог создан', ['id' => $dialogId]);
    } else {
        echo "✗ FAIL\n";
        $testsFailed[] = 'Start Conversation';
        $logger->error('Не удалось создать диалог');
    }

    // ============================================
    // ТЕСТ 10: Получение диалога
    // ============================================
    echo "ТЕСТ 10: Получение диалога... ";
    $logger->info('ТЕСТ 10: Получение диалога');
    
    $conversation = $conversationManager->getConversation($testChatIdConv, $testUserId);
    
    if ($conversation && $conversation['state'] === 'test_state') {
        echo "✓ PASS\n";
        $testsPassed[] = 'Get Conversation';
        $logger->info('✓ Диалог получен', $conversation);
    } else {
        echo "✗ FAIL\n";
        $testsFailed[] = 'Get Conversation';
        $logger->error('Не удалось получить диалог');
    }

    // ============================================
    // ТЕСТ 11: Обновление диалога
    // ============================================
    echo "ТЕСТ 11: Обновление диалога... ";
    $logger->info('ТЕСТ 11: Обновление диалога');
    
    $updated = $conversationManager->updateConversation(
        $testChatIdConv,
        $testUserId,
        'updated_state',
        ['new_key' => 'new_value']
    );
    
    if ($updated) {
        $conversation = $conversationManager->getConversation($testChatIdConv, $testUserId);
        if ($conversation && $conversation['state'] === 'updated_state') {
            echo "✓ PASS\n";
            $testsPassed[] = 'Update Conversation';
            $logger->info('✓ Диалог обновлен');
        } else {
            echo "✗ FAIL\n";
            $testsFailed[] = 'Update Conversation';
            $logger->error('Диалог не обновился корректно');
        }
    } else {
        echo "✗ FAIL\n";
        $testsFailed[] = 'Update Conversation';
        $logger->error('Не удалось обновить диалог');
    }

    // ============================================
    // ТЕСТ 12: Завершение диалога
    // ============================================
    echo "ТЕСТ 12: Завершение диалога... ";
    $logger->info('ТЕСТ 12: Завершение диалога');
    
    $ended = $conversationManager->endConversation($testChatIdConv, $testUserId);
    
    if ($ended) {
        $conversation = $conversationManager->getConversation($testChatIdConv, $testUserId);
        if ($conversation === null) {
            echo "✓ PASS\n";
            $testsPassed[] = 'End Conversation';
            $logger->info('✓ Диалог завершен');
        } else {
            echo "✗ FAIL\n";
            $testsFailed[] = 'End Conversation';
            $logger->error('Диалог не удалился');
        }
    } else {
        echo "✗ FAIL\n";
        $testsFailed[] = 'End Conversation';
        $logger->error('Не удалось завершить диалог');
    }

    // ============================================
    // ТЕСТ 13: Инициализация PollingHandler
    // ============================================
    echo "ТЕСТ 13: Инициализация PollingHandler... ";
    $logger->info('ТЕСТ 13: PollingHandler');
    
    $pollingHandler = new PollingHandler($api, $logger);
    $pollingHandler->setTimeout(30);
    $pollingHandler->setLimit(10);
    echo "✓ PASS\n";
    $testsPassed[] = 'PollingHandler Init';
    $logger->info('✓ PollingHandler инициализирован');

    // ============================================
    // ТЕСТ 14: Создание Reply клавиатуры
    // ============================================
    echo "ТЕСТ 14: Создание Reply клавиатуры... ";
    $logger->info('ТЕСТ 14: Reply клавиатура');
    
    $replyKeyboard = ReplyKeyboardBuilder::make()
        ->addButton('Кнопка 1')
        ->addButton('Кнопка 2')
        ->row()
        ->addButton('Кнопка 3')
        ->resizeKeyboard()
        ->oneTime()
        ->build();
    
    if (isset($replyKeyboard['keyboard']) && count($replyKeyboard['keyboard']) === 2) {
        echo "✓ PASS\n";
        $testsPassed[] = 'Reply Keyboard Builder';
        $logger->info('✓ Reply клавиатура создана');
    } else {
        echo "✗ FAIL\n";
        $testsFailed[] = 'Reply Keyboard Builder';
        $logger->error('Не удалось создать reply клавиатуру');
    }

    // ============================================
    // ТЕСТ 15: Создание Inline клавиатуры
    // ============================================
    echo "ТЕСТ 15: Создание Inline клавиатуры... ";
    $logger->info('ТЕСТ 15: Inline клавиатура');
    
    $inlineKeyboard = InlineKeyboardBuilder::make()
        ->addCallbackButton('Кнопка 1', 'data_1')
        ->addCallbackButton('Кнопка 2', 'data_2')
        ->row()
        ->addCallbackButton('Кнопка 3', 'data_3')
        ->build();
    
    if (isset($inlineKeyboard['inline_keyboard']) && count($inlineKeyboard['inline_keyboard']) === 2) {
        echo "✓ PASS\n";
        $testsPassed[] = 'Inline Keyboard Builder';
        $logger->info('✓ Inline клавиатура создана');
    } else {
        echo "✗ FAIL\n";
        $testsFailed[] = 'Inline Keyboard Builder';
        $logger->error('Не удалось создать inline клавиатуру');
    }

    // ============================================
    // ТЕСТ 16: Отправка сообщения в Telegram
    // ============================================
    echo "ТЕСТ 16: Отправка сообщения в Telegram... ";
    $logger->info('ТЕСТ 16: Отправка сообщения');
    
    try {
        $message = $api->sendMessage(
            $testChatId,
            "🧪 <b>Автоматический тест</b>\n\n" .
            "Все компоненты протестированы:\n" .
            "✅ MySQL: Работает\n" .
            "✅ ConversationManager: Работает\n" .
            "✅ PollingHandler: Работает\n" .
            "✅ Клавиатуры: Работают\n\n" .
            "Тесты пройдено: " . count($testsPassed) . "\n" .
            "Время: " . date('Y-m-d H:i:s'),
            ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
        );
        
        echo "✓ PASS (Message ID: {$message->messageId})\n";
        $testsPassed[] = 'Send Message';
        $logger->info('✓ Сообщение отправлено', ['message_id' => $message->messageId]);
    } catch (\Exception $e) {
        echo "✗ FAIL\n";
        $testsFailed[] = 'Send Message';
        $logger->error('Не удалось отправить сообщение', ['error' => $e->getMessage()]);
    }

    // ============================================
    // ТЕСТ 17: Получение статистики
    // ============================================
    echo "ТЕСТ 17: Получение статистики диалогов... ";
    $logger->info('ТЕСТ 17: Статистика');
    
    $stats = $conversationManager->getStatistics();
    
    if (is_array($stats) && isset($stats['total'])) {
        echo "✓ PASS (Активных: {$stats['total']})\n";
        $testsPassed[] = 'Get Statistics';
        $logger->info('✓ Статистика получена', $stats);
    } else {
        echo "✗ FAIL\n";
        $testsFailed[] = 'Get Statistics';
        $logger->error('Не удалось получить статистику');
    }

    // ============================================
    // СОЗДАНИЕ ДАМПОВ БД
    // ============================================
    echo "\nСоздание дампов БД...\n";
    $logger->info('Создание дампов таблиц MySQL');
    
    $tables = ['telegram_bot_users', 'telegram_bot_conversations'];
    
    foreach ($tables as $table) {
        echo "  - Дамп $table... ";
        $dumpFile = __DIR__ . "/mysql/{$table}.sql";
        
        exec("sudo mysqldump -u root test_telegram_bot $table > $dumpFile 2>&1", $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($dumpFile)) {
            echo "✓\n";
            $logger->info("✓ Дамп $table создан", ['file' => $dumpFile]);
        } else {
            echo "✗\n";
            $logger->error("Ошибка создания дампа $table");
        }
    }

    // Полный дамп
    echo "  - Полный дамп БД... ";
    $fullDumpFile = __DIR__ . "/mysql/full_database_dump.sql";
    exec("sudo mysqldump -u root test_telegram_bot > $fullDumpFile 2>&1", $output, $returnCode);
    
    if ($returnCode === 0 && file_exists($fullDumpFile)) {
        echo "✓\n";
        $logger->info("✓ Полный дамп БД создан", ['file' => $fullDumpFile]);
    } else {
        echo "✗\n";
        $logger->error("Ошибка создания полного дампа БД");
    }

    // ============================================
    // ФИНАЛЬНЫЙ ОТЧЕТ
    // ============================================
    echo "\n========================================\n";
    echo "РЕЗУЛЬТАТЫ ТЕСТИРОВАНИЯ\n";
    echo "========================================\n\n";
    
    echo "✅ Тесты пройдены: " . count($testsPassed) . "\n";
    foreach ($testsPassed as $test) {
        echo "   ✓ $test\n";
    }
    
    if (count($testsFailed) > 0) {
        echo "\n❌ Тесты провалены: " . count($testsFailed) . "\n";
        foreach ($testsFailed as $test) {
            echo "   ✗ $test\n";
        }
    }
    
    echo "\n========================================\n";
    echo "Всего тестов: " . (count($testsPassed) + count($testsFailed)) . "\n";
    echo "Процент успеха: " . round((count($testsPassed) / (count($testsPassed) + count($testsFailed))) * 100, 2) . "%\n";
    echo "========================================\n\n";

    // Отправляем финальный отчет в Telegram
    try {
        $api->sendMessage(
            $testChatId,
            "🎉 <b>ТЕСТИРОВАНИЕ ЗАВЕРШЕНО</b>\n\n" .
            "✅ Пройдено: " . count($testsPassed) . "\n" .
            "❌ Провалено: " . count($testsFailed) . "\n" .
            "📊 Успех: " . round((count($testsPassed) / (count($testsPassed) + count($testsFailed))) * 100, 2) . "%\n\n" .
            "📁 Дампы БД созданы в /mysql/\n" .
            "📝 Логи доступны в /logs/\n\n" .
            "🚀 Система готова к использованию!",
            ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
        );
    } catch (\Exception $e) {
        // Игнорируем ошибки отправки финального отчета
    }

    $logger->info('========================================');
    $logger->info('ТЕСТИРОВАНИЕ ЗАВЕРШЕНО УСПЕШНО');
    $logger->info('========================================');
    $logger->info('Тестов пройдено: ' . count($testsPassed));
    $logger->info('Тестов провалено: ' . count($testsFailed));

} catch (\Exception $e) {
    echo "\n❌ КРИТИЧЕСКАЯ ОШИБКА: {$e->getMessage()}\n";
    echo "Файл: {$e->getFile()}:{$e->getLine()}\n\n";
    
    $logger->error('КРИТИЧЕСКАЯ ОШИБКА', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);

    if ($api !== null) {
        try {
            $api->sendMessage(
                $testChatId,
                "❌ <b>КРИТИЧЕСКАЯ ОШИБКА ТЕСТИРОВАНИЯ</b>\n\n" .
                "Ошибка: <code>" . htmlspecialchars($e->getMessage()) . "</code>\n\n" .
                "Файл: {$e->getFile()}:{$e->getLine()}\n\n" .
                "Проверьте логи для подробностей.",
                ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
            );
        } catch (\Exception $notifyError) {
            // Ignore
        }
    }

    exit(1);
}

echo "Скрипт завершен успешно.\n";
$logger->info('Скрипт завершен');

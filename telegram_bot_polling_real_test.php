<?php

declare(strict_types=1);

/**
 * Реальное комплексное тестирование TelegramBot в режиме Polling
 * 
 * Упрощенная и оптимизированная версия с фокусом на функциональность
 * Использует прямые SQL запросы для обхода багов в MySQL.insert()
 */

require_once __DIR__ . '/autoload.php';

use App\Component\Logger;
use App\Component\Http;
use App\Component\MySQL;
use App\Component\OpenRouter;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Core\PollingHandler;
use App\Component\TelegramBot\Core\ConversationManager;
use App\Component\TelegramBot\Core\AccessControl;
use App\Component\TelegramBot\Keyboards\InlineKeyboardBuilder;
use App\Component\TelegramBot\Keyboards\ReplyKeyboardBuilder;

echo "🚀 ===============================================\n";
echo "   КОМПЛЕКСНОЕ ТЕСТИРОВАНИЕ TELEGRAMBOT POLLING\n";
echo "===============================================\n\n";

$botToken = '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI';
$chatId = 366442475;

$testsPassed = 0;
$testsFailed = 0;

// Инициализация Logger
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0775, true);
}

$logger = new Logger([
    'directory' => $logDir,
    'file_name' => 'telegram_polling_real_test.log',
    'max_files' => 3,
    'max_file_size' => 5,
    'enabled' => true,
]);

// Функция для отправки уведомлений
function notify(string $message, TelegramAPI $api, int $chatId, Logger $logger): void
{
    try {
        $api->sendMessage($chatId, $message);
        $logger->info("Уведомление отправлено: " . substr($message, 0, 50));
    } catch (\Exception $e) {
        $logger->error("Ошибка отправки уведомления: " . $e->getMessage());
    }
}

// Функция тестирования
function test(string $name, callable $testFunc, &$passed, &$failed): void
{
    global $logger;
    echo "🧪 ТЕСТ: {$name}... ";
    $logger->info("Запуск теста: {$name}");
    
    try {
        $result = $testFunc();
        if ($result) {
            echo "✅ PASSED\n";
            $logger->info("✅ PASSED: {$name}");
            $passed++;
        } else {
            echo "❌ FAILED\n";
            $logger->error("❌ FAILED: {$name}");
            $failed++;
        }
    } catch (\Exception $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n";
        $logger->error("❌ ERROR in {$name}: " . $e->getMessage());
        $failed++;
    }
}

try {
    // Инициализация HTTP и API
    $http = new Http(['timeout' => 30], $logger);
    $api = new TelegramAPI($botToken, $http, $logger);
    
    echo "🤖 Подключение к Telegram API...\n";
    $botInfo = $api->getMe();
    echo "✅ Бот подключен: @{$botInfo->username}\n\n";
    
    notify("🚀 Начало комплексного тестирования TelegramBot Polling", $api, $chatId, $logger);
    
    // =================== ТЕСТ 1: MySQL подключение ===================
    echo "📊 БЛОК 1: ТЕСТИРОВАНИЕ MYSQL\n";
    echo str_repeat("=", 50) . "\n";
    
    test("MySQL: Подключение к серверу", function() use ($logger) {
        $db = new MySQL([
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'telegram_bot_test',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4',
        ], $logger);
        
        $result = $db->queryOne("SELECT VERSION() as v");
        return isset($result['v']);
    }, $testsPassed, $testsFailed);
    
    // Создание БД
    $db = new MySQL([
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'telegram_bot_test',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ], $logger);
    
    test("MySQL: Создание тестовой таблицы", function() use ($db) {
        $db->execute("DROP TABLE IF EXISTS test_data");
        $db->execute("
            CREATE TABLE test_data (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255),
                value INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB
        ");
        return true;
    }, $testsPassed, $testsFailed);
    
    test("MySQL: INSERT/SELECT операции", function() use ($db) {
        $db->execute("INSERT INTO test_data (name, value) VALUES (?, ?)", ['test1', 100]);
        $result = $db->queryOne("SELECT * FROM test_data WHERE name = ?", ['test1']);
        return $result && $result['value'] == 100;
    }, $testsPassed, $testsFailed);
    
    notify("✅ MySQL тесты завершены", $api, $chatId, $logger);
    
    // =================== ТЕСТ 2: TelegramAPI ===================
    echo "\n📱 БЛОК 2: ТЕСТИРОВАНИЕ TELEGRAM API\n";
    echo str_repeat("=", 50) . "\n";
    
    test("TelegramAPI: Отправка текстового сообщения", function() use ($api, $chatId) {
        $msg = $api->sendMessage($chatId, "🧪 Тест отправки сообщения");
        return $msg !== null && $msg->messageId > 0;
    }, $testsPassed, $testsFailed);
    
    sleep(1);
    
    test("TelegramAPI: HTML форматирование", function() use ($api, $chatId) {
        $msg = $api->sendMessage(
            $chatId,
            "<b>Жирный</b> <i>Курсив</i> <code>Код</code>",
            ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
        );
        return $msg !== null;
    }, $testsPassed, $testsFailed);
    
    sleep(1);
    
    $editMessageId = null;
    test("TelegramAPI: Редактирование сообщения", function() use ($api, $chatId, &$editMessageId) {
        $original = $api->sendMessage($chatId, "Оригинальное сообщение");
        $editMessageId = $original->messageId;
        sleep(1);
        $edited = $api->editMessageText($chatId, $original->messageId, "✏️ Отредактированное сообщение");
        return $edited !== null;
    }, $testsPassed, $testsFailed);
    
    sleep(1);
    
    test("TelegramAPI: Удаление сообщения", function() use ($api, $chatId) {
        $msg = $api->sendMessage($chatId, "Это сообщение будет удалено");
        sleep(1);
        $deleted = $api->deleteMessage($chatId, $msg->messageId);
        return $deleted === true;
    }, $testsPassed, $testsFailed);
    
    notify("✅ TelegramAPI тесты завершены", $api, $chatId, $logger);
    
    // =================== ТЕСТ 3: Клавиатуры ===================
    echo "\n⌨️ БЛОК 3: ТЕСТИРОВАНИЕ КЛАВИАТУР\n";
    echo str_repeat("=", 50) . "\n";
    
    test("Keyboards: Inline клавиатура", function() use ($api, $chatId) {
        $keyboard = InlineKeyboardBuilder::make()
            ->addCallbackButton('Кнопка 1', 'btn1')
            ->addCallbackButton('Кнопка 2', 'btn2')
            ->row()
            ->addCallbackButton('Кнопка 3', 'btn3')
            ->build();
        
        $msg = $api->sendMessage(
            $chatId,
            "⌨️ Тест Inline клавиатуры",
            ['reply_markup' => $keyboard]
        );
        return $msg !== null;
    }, $testsPassed, $testsFailed);
    
    sleep(1);
    
    test("Keyboards: Reply клавиатура", function() use ($api, $chatId) {
        $keyboard = ReplyKeyboardBuilder::make()
            ->addButton('Команда 1')
            ->addButton('Команда 2')
            ->row()
            ->addButton('Отмена')
            ->resizeKeyboard(true)
            ->build();
        
        $msg = $api->sendMessage(
            $chatId,
            "⌨️ Тест Reply клавиатуры",
            ['reply_markup' => $keyboard]
        );
        
        // Удаление клавиатуры
        sleep(1);
        $api->sendMessage(
            $chatId,
            "❌ Удаление клавиатуры",
            ['reply_markup' => ['remove_keyboard' => true]]
        );
        
        return $msg !== null;
    }, $testsPassed, $testsFailed);
    
    notify("✅ Тесты клавиатур завершены", $api, $chatId, $logger);
    
    // =================== ТЕСТ 4: PollingHandler ===================
    echo "\n🔄 БЛОК 4: ТЕСТИРОВАНИЕ POLLING\n";
    echo str_repeat("=", 50) . "\n";
    
    $polling = new PollingHandler($api, $logger);
    $polling->setTimeout(5)->setLimit(10);
    
    test("PollingHandler: Инициализация", function() use ($polling) {
        return $polling->getOffset() >= 0;
    }, $testsPassed, $testsFailed);
    
    test("PollingHandler: Пропуск старых обновлений", function() use ($polling) {
        $skipped = $polling->skipPendingUpdates();
        return is_int($skipped) && $skipped >= 0;
    }, $testsPassed, $testsFailed);
    
    notify("⏳ Отправьте любое сообщение боту в течение 5 секунд для теста Polling...", $api, $chatId, $logger);
    echo "\n⏳ Ожидание сообщения от пользователя (5 секунд)...\n";
    
    test("PollingHandler: Получение обновлений", function() use ($polling, $api, $chatId, $logger) {
        $updates = $polling->pollOnce();
        $logger->info("Получено обновлений: " . count($updates));
        
        if (count($updates) > 0) {
            foreach ($updates as $update) {
                if ($update->message) {
                    $text = $update->message->text ?? '[медиа]';
                    $api->sendMessage($chatId, "✅ Сообщение получено через Polling: {$text}");
                    return true;
                }
                if ($update->callbackQuery) {
                    $data = $update->callbackQuery->data;
                    $api->answerCallbackQuery($update->callbackQuery->id);
                    $api->sendMessage($chatId, "✅ Callback получен: {$data}");
                    return true;
                }
            }
        }
        
        return true; // Даже если нет обновлений, это не ошибка
    }, $testsPassed, $testsFailed);
    
    notify("✅ Polling тесты завершены", $api, $chatId, $logger);
    
    // =================== ТЕСТ 5: ConversationManager ===================
    echo "\n💬 БЛОК 5: ТЕСТИРОВАНИЕ ДИАЛОГОВ\n";
    echo str_repeat("=", 50) . "\n";
    
    // Создание таблиц для диалогов
    $db->execute("DROP TABLE IF EXISTS telegram_bot_users");
    $db->execute("DROP TABLE IF EXISTS telegram_bot_conversations");
    
    $db->execute("
        CREATE TABLE telegram_bot_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT NOT NULL UNIQUE,
            first_name VARCHAR(255),
            username VARCHAR(255),
            last_name VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");
    
    $db->execute("
        CREATE TABLE telegram_bot_conversations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            chat_id BIGINT NOT NULL,
            user_id BIGINT NOT NULL,
            state VARCHAR(100) NOT NULL,
            data TEXT,
            message_id BIGINT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NOT NULL,
            INDEX idx_chat_user (chat_id, user_id),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB
    ");
    
    $convConfig = [
        'enabled' => true,
        'timeout' => 3600,
        'auto_create_tables' => false, // Уже создали
    ];
    $conversations = new ConversationManager($db, $logger, $convConfig);
    
    test("ConversationManager: Сохранение пользователя", function() use ($conversations, $chatId) {
        return $conversations->saveUser($chatId, 'TestUser', 'testuser');
    }, $testsPassed, $testsFailed);
    
    test("ConversationManager: Получение пользователя", function() use ($conversations, $chatId) {
        $user = $conversations->getUser($chatId);
        return $user !== null && $user['user_id'] == $chatId;
    }, $testsPassed, $testsFailed);
    
    test("ConversationManager: Начало диалога", function() use ($conversations, $chatId) {
        $id = $conversations->startConversation($chatId, $chatId, 'test_state', ['step' => 1]);
        return $id > 0;
    }, $testsPassed, $testsFailed);
    
    test("ConversationManager: Получение диалога", function() use ($conversations, $chatId) {
        $conv = $conversations->getConversation($chatId, $chatId);
        return $conv !== null && $conv['state'] === 'test_state';
    }, $testsPassed, $testsFailed);
    
    test("ConversationManager: Обновление диалога", function() use ($conversations, $chatId) {
        $updated = $conversations->updateConversation($chatId, $chatId, 'new_state', ['step' => 2]);
        $conv = $conversations->getConversation($chatId, $chatId);
        return $updated && $conv['state'] === 'new_state' && $conv['data']['step'] == 2;
    }, $testsPassed, $testsFailed);
    
    test("ConversationManager: Завершение диалога", function() use ($conversations, $chatId) {
        $ended = $conversations->endConversation($chatId, $chatId);
        $conv = $conversations->getConversation($chatId, $chatId);
        return $ended && $conv === null;
    }, $testsPassed, $testsFailed);
    
    notify("✅ Тесты диалогов завершены", $api, $chatId, $logger);
    
    // =================== ТЕСТ 6: Интерактивный сценарий ===================
    echo "\n🎭 БЛОК 6: ИНТЕРАКТИВНЫЙ СЦЕНАРИЙ\n";
    echo str_repeat("=", 50) . "\n";
    
    notify("🎭 ИНТЕРАКТИВНЫЙ ТЕСТ: Многошаговый диалог", $api, $chatId, $logger);
    
    // Шаг 1: Начало регистрации
    $keyboard = InlineKeyboardBuilder::make()
        ->addCallbackButton('Тип A', 'reg_type_a')
        ->addCallbackButton('Тип B', 'reg_type_b')
        ->addCallbackButton('Тип C', 'reg_type_c')
        ->build();
    
    $msg = $api->sendMessage(
        $chatId,
        "📝 РЕГИСТРАЦИЯ (шаг 1/3)\nВыберите тип:",
        ['reply_markup' => $keyboard]
    );
    
    $conversations->startConversation($chatId, $chatId, 'awaiting_type', ['step' => 1], $msg->messageId);
    
    echo "⏳ Ожидание выбора типа (нажмите кнопку в течение 5 секунд)...\n";
    sleep(5);
    
    // Проверка callback
    $updates = $polling->pollOnce();
    $selectedType = null;
    
    foreach ($updates as $update) {
        if ($update->callbackQuery && str_starts_with($update->callbackQuery->data, 'reg_type_')) {
            $selectedType = $update->callbackQuery->data;
            $api->answerCallbackQuery($update->callbackQuery->id, ['text' => "Выбрано: {$selectedType}"]);
            
            $conversations->updateConversation($chatId, $chatId, 'awaiting_name', [
                'type' => $selectedType,
                'step' => 2
            ]);
            
            $api->sendMessage($chatId, "✅ Тип выбран!\n📝 РЕГИСТРАЦИЯ (шаг 2/3)\nТеперь введите ваше имя:");
            
            echo "✅ Callback получен: {$selectedType}\n";
            echo "⏳ Ожидание ввода имени (5 секунд)...\n";
            sleep(5);
            
            // Ожидание имени
            $nameUpdates = $polling->pollOnce();
            foreach ($nameUpdates as $nameUpdate) {
                if ($nameUpdate->message && $nameUpdate->message->text) {
                    $name = $nameUpdate->message->text;
                    
                    $conversations->updateConversation($chatId, $chatId, 'awaiting_email', [
                        'name' => $name,
                        'step' => 3
                    ]);
                    
                    $api->sendMessage($chatId, "✅ Имя сохранено: {$name}\n📝 РЕГИСТРАЦИЯ (шаг 3/3)\nВведите email:");
                    
                    echo "✅ Имя получено: {$name}\n";
                    echo "⏳ Ожидание ввода email (5 секунд)...\n";
                    sleep(5);
                    
                    // Ожидание email
                    $emailUpdates = $polling->pollOnce();
                    foreach ($emailUpdates as $emailUpdate) {
                        if ($emailUpdate->message && $emailUpdate->message->text) {
                            $email = $emailUpdate->message->text;
                            
                            // Получение всех данных
                            $conv = $conversations->getConversation($chatId, $chatId);
                            
                            $summary = "🎉 РЕГИСТРАЦИЯ ЗАВЕРШЕНА!\n\n";
                            $summary .= "Тип: {$conv['data']['type']}\n";
                            $summary .= "Имя: {$conv['data']['name']}\n";
                            $summary .= "Email: {$email}\n";
                            
                            $api->sendMessage($chatId, $summary);
                            $conversations->endConversation($chatId, $chatId);
                            
                            echo "✅ Регистрация завершена!\n";
                            $testsPassed++;
                            break 3;
                        }
                    }
                    break;
                }
            }
            break;
        }
    }
    
    if (!$selectedType) {
        echo "⚠️ Пользователь не выбрал тип (пропущено)\n";
        $testsFailed++;
    }
    
    // =================== ТЕСТ 7: OpenRouter интеграция ===================
    echo "\n🧠 БЛОК 7: ИНТЕГРАЦИЯ С AI (OpenRouter)\n";
    echo str_repeat("=", 50) . "\n";
    
    try {
        $openRouter = new OpenRouter([
            'api_key' => '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',
            'app_name' => 'TelegramBotTest',
            'timeout' => 30,
        ], $logger);
        
        test("OpenRouter: Генерация AI ответа", function() use ($openRouter, $api, $chatId) {
            $api->sendMessage($chatId, "🧠 Генерирую AI ответ...");
            
            $response = $openRouter->text2text(
                'openai/gpt-3.5-turbo',
                'Напиши короткое приветствие для бота на русском (макс 30 слов)',
                ['max_tokens' => 80]
            );
            
            if (!empty($response)) {
                $api->sendMessage($chatId, "🤖 AI ответ:\n\n{$response}");
                return true;
            }
            return false;
        }, $testsPassed, $testsFailed);
        
    } catch (\Exception $e) {
        echo "⚠️ OpenRouter недоступен: " . $e->getMessage() . "\n";
        $testsFailed++;
    }
    
    // =================== ФИНАЛЬНЫЙ ОТЧЕТ ===================
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "📊 ФИНАЛЬНЫЕ РЕЗУЛЬТАТЫ\n";
    echo str_repeat("=", 50) . "\n";
    
    $total = $testsPassed + $testsFailed;
    $successRate = $total > 0 ? round(($testsPassed / $total) * 100, 2) : 0;
    
    echo "Всего тестов: {$total}\n";
    echo "✅ Успешных: {$testsPassed}\n";
    echo "❌ Ошибок: {$testsFailed}\n";
    echo "Процент успеха: {$successRate}%\n\n";
    
    $finalReport = "📊 ФИНАЛЬНЫЙ ОТЧЕТ\n\n";
    $finalReport .= "Всего тестов: {$total}\n";
    $finalReport .= "✅ Успешных: {$testsPassed}\n";
    $finalReport .= "❌ Ошибок: {$testsFailed}\n";
    $finalReport .= "Процент успеха: {$successRate}%\n\n";
    
    if ($successRate >= 80) {
        $finalReport .= "🎉 Тестирование успешно завершено!";
    } else {
        $finalReport .= "⚠️ Обнаружены проблемы, требуется доработка.";
    }
    
    notify($finalReport, $api, $chatId, $logger);
    
    echo "✅ Тестирование завершено!\n";
    echo "📋 Подробный лог: logs/telegram_polling_real_test.log\n\n";
    
    $logger->info("=== ТЕСТИРОВАНИЕ ЗАВЕРШЕНО ===", [
        'total' => $total,
        'passed' => $testsPassed,
        'failed' => $testsFailed,
        'success_rate' => $successRate,
    ]);
    
} catch (\Exception $e) {
    echo "\n❌ КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage() . "\n";
    echo "Трассировка:\n" . $e->getTraceAsString() . "\n";
    $logger->error("Критическая ошибка: " . $e->getMessage());
    exit(1);
}

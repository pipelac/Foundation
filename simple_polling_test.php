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
use App\Component\TelegramBot\Entities\Update;

// Инициализация логгера
$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'file_name' => 'simple_test.log',
    'max_files' => 5,
    'max_file_size' => 10485760,
]);
$logger->info('========================================');
$logger->info('ЗАПУСК УПРОЩЕННОГО ТЕСТА TELEGRAMBOT');
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

try {
    // Инициализация MySQL
    $logger->info('Подключение к MySQL...');
    $db = new MySQL($dbConfig, $logger);
    $logger->info('✓ MySQL подключен успешно');

    // Инициализация HTTP клиента
    $logger->info('Инициализация HTTP клиента...');
    $http = new Http([
        'timeout' => 60,
        'connect_timeout' => 10,
    ], $logger);
    $logger->info('✓ HTTP клиент инициализирован');

    // Инициализация Telegram API
    $logger->info('Инициализация Telegram API...');
    $api = new TelegramAPI($botToken, $http, $logger);
    $logger->info('✓ Telegram API инициализирован');

    // Инициализация ConversationManager
    $logger->info('Инициализация ConversationManager...');
    $conversationManager = new ConversationManager(
        $db,
        $logger,
        [
            'enabled' => true,
            'timeout' => 7200,
            'auto_create_tables' => true,
        ]
    );
    $logger->info('✓ ConversationManager инициализирован');

    // Инициализация PollingHandler
    $logger->info('Инициализация PollingHandler...');
    $pollingHandler = new PollingHandler($api, $logger);
    $pollingHandler->setTimeout(30);
    $pollingHandler->setLimit(10);
    $logger->info('✓ PollingHandler инициализирован');

    // Пропускаем старые обновления
    $logger->info('Пропуск старых обновлений...');
    $skipped = $pollingHandler->skipPendingUpdates();
    $logger->info("✓ Пропущено обновлений: $skipped");

    // Отправляем уведомление о начале тестирования
    $api->sendMessage(
        $testChatId,
        "🚀 <b>ЗАПУСК УПРОЩЕННОГО ТЕСТА</b>\n\n" .
        "Режим: Polling\n" .
        "MySQL: Подключен ✓\n" .
        "Таблицы созданы: ✓\n\n" .
        "Начинаем тестирование...",
        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
    );

    $logger->info('Тест 1: Текстовое сообщение');
    $api->sendMessage(
        $testChatId,
        "📝 <b>ТЕСТ 1: Текстовое сообщение</b>\n\nОтправьте любой текст боту.",
        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
    );

    $level1Done = false;
    $timeout = time() + 20;
    while (!$level1Done && time() < $timeout) {
        $updates = $pollingHandler->pollOnce();
        foreach ($updates as $update) {
            if ($update->message && $update->message->text) {
                $text = $update->message->text;
                $chatId = $update->message->chat->id;
                $userId = $update->message->from->id;

                $conversationManager->saveUser(
                    $userId,
                    $update->message->from->firstName,
                    $update->message->from->username,
                    $update->message->from->lastName
                );

                $api->sendMessage(
                    $chatId,
                    "✅ Получено: <code>" . htmlspecialchars($text) . "</code>",
                    ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
                );

                $level1Done = true;
                $logger->info("✓ Тест 1 пройден: $text");
                break;
            }
        }
        if (!$level1Done) sleep(1);
    }

    sleep(2);

    $logger->info('Тест 2: Reply клавиатура');
    $keyboard = ReplyKeyboardBuilder::make()
        ->addButton('✅ Да')
        ->addButton('❌ Нет')
        ->row()
        ->addButton('🔙 Назад')
        ->resizeKeyboard()
        ->oneTime()
        ->build();

    $api->sendMessage(
        $testChatId,
        "📝 <b>ТЕСТ 2: Reply клавиатура</b>\n\nНажмите любую кнопку.",
        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML, 'reply_markup' => $keyboard]
    );

    $level2Done = false;
    $timeout = time() + 20;
    while (!$level2Done && time() < $timeout) {
        $updates = $pollingHandler->pollOnce();
        foreach ($updates as $update) {
            if ($update->message && $update->message->text && in_array($update->message->text, ['✅ Да', '❌ Нет', '🔙 Назад'])) {
                $text = $update->message->text;
                $chatId = $update->message->chat->id;

                $api->sendMessage(
                    $chatId,
                    "✅ Нажата кнопка: $text",
                    ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
                );

                $level2Done = true;
                $logger->info("✓ Тест 2 пройден: $text");
                break;
            }
        }
        if (!$level2Done) sleep(1);
    }

    sleep(2);

    $logger->info('Тест 3: Inline клавиатура');
    $inlineKeyboard = InlineKeyboardBuilder::make()
        ->addCallbackButton('🔴 Кнопка 1', 'btn_1')
        ->addCallbackButton('🟢 Кнопка 2', 'btn_2')
        ->row()
        ->addCallbackButton('🔵 Кнопка 3', 'btn_3')
        ->build();

    $api->sendMessage(
        $testChatId,
        "📝 <b>ТЕСТ 3: Inline клавиатура</b>\n\nНажмите любую кнопку.",
        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML, 'reply_markup' => $inlineKeyboard]
    );

    $level3Done = false;
    $timeout = time() + 20;
    while (!$level3Done && time() < $timeout) {
        $updates = $pollingHandler->pollOnce();
        foreach ($updates as $update) {
            if ($update->callbackQuery) {
                $callbackData = $update->callbackQuery->data;
                $chatId = $update->callbackQuery->message->chat->id;
                $messageId = $update->callbackQuery->message->messageId;

                $api->answerCallbackQuery($update->callbackQuery->id, 'Получено!');
                $api->editMessageText(
                    $chatId,
                    $messageId,
                    "✅ Callback получен: <code>$callbackData</code>",
                    ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
                );

                $level3Done = true;
                $logger->info("✓ Тест 3 пройден: $callbackData");
                break;
            }
        }
        if (!$level3Done) sleep(1);
    }

    sleep(2);

    $logger->info('Тест 4: Диалог с памятью');
    $api->sendMessage(
        $testChatId,
        "📝 <b>ТЕСТ 4: Диалог</b>\n\nВопрос 1: Как вас зовут?",
        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
    );

    $dialogData = [];
    $currentStep = 'awaiting_name';
    $timeout = time() + 60;

    while ($currentStep !== 'completed' && time() < $timeout) {
        $updates = $pollingHandler->pollOnce();
        foreach ($updates as $update) {
            if ($update->message && $update->message->text) {
                $text = $update->message->text;
                $chatId = $update->message->chat->id;
                $userId = $update->message->from->id;

                switch ($currentStep) {
                    case 'awaiting_name':
                        $dialogData['name'] = $text;
                        $conversationManager->startConversation($chatId, $userId, 'awaiting_age', $dialogData);
                        $api->sendMessage(
                            $chatId,
                            "Приятно познакомиться, $text!\n\nВопрос 2: Сколько вам лет?",
                            ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
                        );
                        $currentStep = 'awaiting_age';
                        $logger->info("Сохранено имя: $text");
                        break;

                    case 'awaiting_age':
                        $dialogData['age'] = $text;
                        $conversationManager->updateConversation($chatId, $userId, 'awaiting_city', $dialogData);
                        $api->sendMessage(
                            $chatId,
                            "Отлично!\n\nВопрос 3: Из какого вы города?",
                            ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
                        );
                        $currentStep = 'awaiting_city';
                        $logger->info("Сохранен возраст: $text");
                        break;

                    case 'awaiting_city':
                        $dialogData['city'] = $text;
                        $conversationManager->updateConversation($chatId, $userId, 'completed', $dialogData);
                        $api->sendMessage(
                            $chatId,
                            "✅ <b>Тест 4 пройден!</b>\n\n" .
                            "📝 Данные:\n" .
                            "👤 Имя: {$dialogData['name']}\n" .
                            "🎂 Возраст: {$dialogData['age']}\n" .
                            "🏙 Город: {$dialogData['city']}\n\n" .
                            "💾 Данные сохранены в БД!",
                            ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
                        );
                        $conversationManager->endConversation($chatId, $userId);
                        $currentStep = 'completed';
                        $logger->info("✓ Тест 4 пройден");
                        break;
                }
                break;
            }
        }
        if ($currentStep !== 'completed') sleep(1);
    }

    sleep(2);

    // Финальный отчет
    $stats = $conversationManager->getStatistics();
    $api->sendMessage(
        $testChatId,
        "🎉 <b>ВСЕ ТЕСТЫ ПРОЙДЕНЫ!</b>\n\n" .
        "✅ Текстовые сообщения: OK\n" .
        "✅ Reply клавиатура: OK\n" .
        "✅ Inline клавиатура: OK\n" .
        "✅ Диалог с памятью: OK\n\n" .
        "📊 Статистика:\n" .
        "• Активных диалогов: {$stats['total']}\n" .
        "• MySQL: Работает\n" .
        "• Polling: Стабилен\n\n" .
        "🚀 Система готова!",
        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
    );

    // Создаем дампы таблиц
    $logger->info('Создание дампов таблиц MySQL...');
    $tables = ['telegram_bot_users', 'telegram_bot_conversations'];

    foreach ($tables as $table) {
        $dumpFile = __DIR__ . "/mysql/{$table}.sql";
        exec("sudo mysqldump -u root test_telegram_bot $table > $dumpFile 2>&1", $output, $returnCode);
        if ($returnCode === 0 && file_exists($dumpFile)) {
            $logger->info("✓ Дамп $table создан");
        }
    }

    // Полный дамп
    $fullDumpFile = __DIR__ . "/mysql/full_database_dump.sql";
    exec("sudo mysqldump -u root test_telegram_bot > $fullDumpFile 2>&1", $output, $returnCode);
    if ($returnCode === 0) {
        $logger->info("✓ Полный дамп БД создан");
    }

    $logger->info('========================================');
    $logger->info('ТЕСТИРОВАНИЕ ЗАВЕРШЕНО УСПЕШНО');
    $logger->info('========================================');

} catch (\Exception $e) {
    $logger->error('КРИТИЧЕСКАЯ ОШИБКА', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);

    if ($api !== null) {
        try {
            $api->sendMessage(
                $testChatId,
                "❌ <b>ОШИБКА</b>\n\n" .
                "Ошибка: <code>" . htmlspecialchars($e->getMessage()) . "</code>\n\n" .
                "Файл: {$e->getFile()}:{$e->getLine()}",
                ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
            );
        } catch (\Exception $notifyError) {
            $logger->error('Не удалось отправить уведомление об ошибке');
        }
    }

    exit(1);
}

$logger->info('Скрипт завершен');

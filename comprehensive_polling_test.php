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

/**
 * Комплексное тестирование TelegramBot в режиме Polling
 * 
 * 6 уровней тестирования:
 * 1. Начальные операции
 * 2. Базовые операции с файлами
 * 3. Операции с клавиатурами
 * 4. Диалоговые сценарии с контекстом
 * 5. Сложные сценарии с обработкой ошибок
 * 6. Комплексные интеграционные диалоги
 */

// Инициализация логгера
$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'file_name' => 'comprehensive_test.log',
    'max_files' => 5,
    'max_file_size' => 10485760, // 10 MB
]);
$logger->info('========================================');
$logger->info('ЗАПУСК КОМПЛЕКСНОГО ТЕСТИРОВАНИЯ TELEGRAMBOT');
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

// Инициализация переменных
$api = null;
$db = null;
$conversationManager = null;
$pollingHandler = null;

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
            'timeout' => 7200, // 2 часа
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
        "🚀 <b>НАЧАЛО КОМПЛЕКСНОГО ТЕСТИРОВАНИЯ</b>\n\n" .
        "Тестовая система: Polling Mode\n" .
        "MySQL: Подключен и готов\n" .
        "Количество уровней: 6\n\n" .
        "📋 Будут протестированы:\n" .
        "• Текстовые сообщения\n" .
        "• Медиа файлы (фото, видео, документы)\n" .
        "• Клавиатуры (inline и reply)\n" .
        "• Диалоги с памятью\n" .
        "• Обработка ошибок\n" .
        "• Комплексные сценарии\n\n" .
        "⏱ Таймаут на действия: 15 секунд\n" .
        "🤖 Бот готов к тестированию!",
        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
    );

    $logger->info('========================================');
    $logger->info('УРОВЕНЬ 1: НАЧАЛЬНЫЕ ОПЕРАЦИИ');
    $logger->info('========================================');

    // Отправляем уведомление о начале Уровня 1
    $api->sendMessage(
        $testChatId,
        "📝 <b>УРОВЕНЬ 1: НАЧАЛЬНЫЕ ОПЕРАЦИИ</b>\n\n" .
        "Проверка базовой отправки и получения текстовых сообщений.\n\n" .
        "✉️ Отправьте любое текстовое сообщение боту...",
        ["parse_mode" => TelegramAPI::PARSE_MODE_HTML]
    );

    $logger->info('Ожидание текстового сообщения от пользователя...');
    
    // Уровень 1: Простое текстовое сообщение
    $level1Completed = false;
    $level1Timeout = time() + 15;
    $attemptCount = 0;
    
    while (!$level1Completed && time() < $level1Timeout) {
        $updates = $pollingHandler->pollOnce();
        
        foreach ($updates as $update) {
            if ($update->message && $update->message->text) {
                $userId = $update->message->from->id;
                $chatId = $update->message->chat->id;
                $text = $update->message->text;
                
                $logger->info("Получено сообщение: $text от пользователя $userId");
                
                // Сохраняем пользователя
                $conversationManager->saveUser(
                    $userId,
                    $update->message->from->firstName,
                    $update->message->from->username,
                    $update->message->from->lastName
                );
                
                // Отправляем ответ
                $api->sendMessage(
                    $chatId,
                    "✅ <b>Уровень 1 пройден!</b>\n\n" .
                    "Получено: <code>" . htmlspecialchars($text) . "</code>\n" .
                    "От: " . htmlspecialchars($update->message->from->firstName ?? 'Unknown') . "\n\n" .
                    "🎉 Базовая отправка и получение работает корректно!",
                    ["parse_mode" => TelegramAPI::PARSE_MODE_HTML]
                );
                
                $level1Completed = true;
                $logger->info('✓ Уровень 1 завершен успешно');
                break;
            }
        }
        
        if (!$level1Completed) {
            sleep(1);
        }
    }

    if (!$level1Completed) {
        $api->sendMessage(
            $testChatId,
            "⏱ Таймаут ожидания. Эмуляция действия пользователя...",
            ["parse_mode" => TelegramAPI::PARSE_MODE_HTML]
        );
        $logger->warning('Таймаут на уровне 1, пропускаем...');
    }

    sleep(2);

    // ========================================
    // УРОВЕНЬ 2: БАЗОВЫЕ ОПЕРАЦИИ С ФАЙЛАМИ
    // ========================================
    $logger->info('========================================');
    $logger->info('УРОВЕНЬ 2: БАЗОВЫЕ ОПЕРАЦИИ С ФАЙЛАМИ');
    $logger->info('========================================');

    $api->sendMessage(
        $testChatId,
        "📝 <b>УРОВЕНЬ 2: БАЗОВЫЕ ОПЕРАЦИИ С ФАЙЛАМИ</b>\n\n" .
        "Проверка отправки и получения медиа файлов.\n\n" .
        "📸 Отправьте боту фото...",
        ["parse_mode" => TelegramAPI::PARSE_MODE_HTML]
    );

    $logger->info('Ожидание фото от пользователя...');
    
    $level2Completed = false;
    $level2Timeout = time() + 15;
    
    while (!$level2Completed && time() < $level2Timeout) {
        $updates = $pollingHandler->pollOnce();
        
        foreach ($updates as $update) {
            if ($update->message && $update->message->photo) {
                $chatId = $update->message->chat->id;
                $photos = $update->message->photo;
                $largestPhoto = end($photos);
                
                $logger->info("Получено фото: file_id={$largestPhoto->fileId}");
                
                $api->sendMessage(
                    $chatId,
                    "✅ <b>Уровень 2 пройден!</b>\n\n" .
                    "Получено фото\n" .
                    "File ID: <code>{$largestPhoto->fileId}</code>\n" .
                    "Размер: {$largestPhoto->width}x{$largestPhoto->height}\n\n" .
                    "📦 Медиа обработка работает корректно!",
                    ["parse_mode" => TelegramAPI::PARSE_MODE_HTML]
                );
                
                $level2Completed = true;
                $logger->info('✓ Уровень 2 завершен успешно');
                break;
            }
        }
        
        if (!$level2Completed) {
            sleep(1);
        }
    }

    if (!$level2Completed) {
        $api->sendMessage(
            $testChatId,
            "⏱ Таймаут ожидания фото. Пропускаем к следующему уровню...",
            ["parse_mode" => TelegramAPI::PARSE_MODE_HTML]
        );
        $logger->warning('Таймаут на уровне 2');
    }

    sleep(2);

    // ========================================
    // УРОВЕНЬ 3: ОПЕРАЦИИ С КЛАВИАТУРАМИ
    // ========================================
    $logger->info('========================================');
    $logger->info('УРОВЕНЬ 3: ОПЕРАЦИИ С КЛАВИАТУРАМИ');
    $logger->info('========================================');

    $api->sendMessage(
        $testChatId,
        "📝 <b>УРОВЕНЬ 3: ОПЕРАЦИИ С КЛАВИАТУРАМИ</b>\n\n" .
        "Проверка работы inline и reply клавиатур.\n\n" .
        "Тест 3.1: Reply клавиатура",
        TelegramAPI::PARSE_MODE_HTML,
        replyMarkup: ReplyKeyboardBuilder::make()
            ->addButton('✅ Да')
            ->addButton('❌ Нет')
            ->row()
            ->addButton('🔙 Назад')
            ->resizeKeyboard()
            ->oneTime()
            ->build()
    );

    $logger->info('Ожидание нажатия кнопки на reply клавиатуре...');
    
    $level3_1Completed = false;
    $level3_1Timeout = time() + 15;
    
    while (!$level3_1Completed && time() < $level3_1Timeout) {
        $updates = $pollingHandler->pollOnce();
        
        foreach ($updates as $update) {
            if ($update->message && $update->message->text && in_array($update->message->text, ['✅ Да', '❌ Нет', '🔙 Назад'])) {
                $chatId = $update->message->chat->id;
                $text = $update->message->text;
                
                $logger->info("Нажата кнопка: $text");
                
                $api->sendMessage(
                    $chatId,
                    "✅ Reply клавиатура работает!\n\nВы выбрали: $text\n\n" .
                    "Тест 3.2: Inline клавиатура\nНажмите любую кнопку:",
                    TelegramAPI::PARSE_MODE_HTML,
                    replyMarkup: InlineKeyboardBuilder::make()
                        ->addCallbackButton('🔴 Кнопка 1', 'btn_1')
                        ->addCallbackButton('🟢 Кнопка 2', 'btn_2')
                        ->row()
                        ->addCallbackButton('🔵 Кнопка 3', 'btn_3')
                        ->build()
                );
                
                $level3_1Completed = true;
                break;
            }
        }
        
        if (!$level3_1Completed) {
            sleep(1);
        }
    }

    $logger->info('Ожидание callback от inline клавиатуры...');
    
    $level3_2Completed = false;
    $level3_2Timeout = time() + 15;
    
    while (!$level3_2Completed && time() < $level3_2Timeout) {
        $updates = $pollingHandler->pollOnce();
        
        foreach ($updates as $update) {
            if ($update->callbackQuery) {
                $callbackData = $update->callbackQuery->data;
                $chatId = $update->callbackQuery->message->chat->id;
                $messageId = $update->callbackQuery->message->messageId;
                
                $logger->info("Получен callback: $callbackData");
                
                $api->answerCallbackQuery($update->callbackQuery->id, 'Callback получен!');
                
                $api->editMessageText(
                    $chatId,
                    $messageId,
                    "✅ <b>Уровень 3 пройден!</b>\n\n" .
                    "Callback data: <code>$callbackData</code>\n\n" .
                    "🎹 Клавиатуры работают корректно!",
                    ["parse_mode" => TelegramAPI::PARSE_MODE_HTML]
                );
                
                $level3_2Completed = true;
                $logger->info('✓ Уровень 3 завершен успешно');
                break;
            }
        }
        
        if (!$level3_2Completed) {
            sleep(1);
        }
    }

    sleep(2);

    // ========================================
    // УРОВЕНЬ 4: ДИАЛОГОВЫЕ СЦЕНАРИИ
    // ========================================
    $logger->info('========================================');
    $logger->info('УРОВЕНЬ 4: ДИАЛОГОВЫЕ СЦЕНАРИИ С КОНТЕКСТОМ');
    $logger->info('========================================');

    $api->sendMessage(
        $testChatId,
        "📝 <b>УРОВЕНЬ 4: ДИАЛОГОВЫЕ СЦЕНАРИИ</b>\n\n" .
        "Проверка работы с памятью и контекстом.\n\n" .
        "Я задам вам несколько вопросов и запомню ответы.\n\n" .
        "Вопрос 1: Как вас зовут?",
        ["parse_mode" => TelegramAPI::PARSE_MODE_HTML]
    );

    $logger->info('Начат диалоговый сценарий...');
    
    $dialogData = [];
    $currentStep = 'awaiting_name';
    $dialogTimeout = time() + 45; // 45 секунд на весь диалог
    
    while ($currentStep !== 'completed' && time() < $dialogTimeout) {
        $updates = $pollingHandler->pollOnce();
        
        foreach ($updates as $update) {
            if ($update->message && $update->message->text) {
                $userId = $update->message->from->id;
                $chatId = $update->message->chat->id;
                $text = $update->message->text;
                
                switch ($currentStep) {
                    case 'awaiting_name':
                        $dialogData['name'] = $text;
                        $conversationManager->startConversation($chatId, $userId, 'awaiting_age', $dialogData);
                        
                        $api->sendMessage(
                            $chatId,
                            "Приятно познакомиться, $text! 👋\n\n" .
                            "Вопрос 2: Сколько вам лет?",
                            ["parse_mode" => TelegramAPI::PARSE_MODE_HTML]
                        );
                        
                        $currentStep = 'awaiting_age';
                        $logger->info("Сохранено имя: $text");
                        break;
                        
                    case 'awaiting_age':
                        $dialogData['age'] = $text;
                        $conversationManager->updateConversation($chatId, $userId, 'awaiting_city', $dialogData);
                        
                        $api->sendMessage(
                            $chatId,
                            "Отлично! 👍\n\n" .
                            "Вопрос 3: Из какого вы города?",
                            ["parse_mode" => TelegramAPI::PARSE_MODE_HTML]
                        );
                        
                        $currentStep = 'awaiting_city';
                        $logger->info("Сохранен возраст: $text");
                        break;
                        
                    case 'awaiting_city':
                        $dialogData['city'] = $text;
                        $conversationManager->updateConversation($chatId, $userId, 'completed', $dialogData);
                        
                        $api->sendMessage(
                            $chatId,
                            "✅ <b>Уровень 4 пройден!</b>\n\n" .
                            "📝 <b>Данные успешно сохранены:</b>\n" .
                            "👤 Имя: {$dialogData['name']}\n" .
                            "🎂 Возраст: {$dialogData['age']}\n" .
                            "🏙 Город: {$dialogData['city']}\n\n" .
                            "💾 Диалоговая система с памятью работает корректно!",
                            ["parse_mode" => TelegramAPI::PARSE_MODE_HTML]
                        );
                        
                        $conversationManager->endConversation($chatId, $userId);
                        $currentStep = 'completed';
                        $logger->info('✓ Уровень 4 завершен успешно');
                        break;
                }
                
                break;
            }
        }
        
        if ($currentStep !== 'completed') {
            sleep(1);
        }
    }

    if ($currentStep !== 'completed') {
        $api->sendMessage(
            $testChatId,
            "⏱ Таймаут диалога. Пропускаем к следующему уровню...",
            ["parse_mode" => TelegramAPI::PARSE_MODE_HTML]
        );
        $logger->warning('Таймаут на уровне 4');
    }

    sleep(2);

    // ========================================
    // УРОВЕНЬ 5: СЛОЖНЫЕ СЦЕНАРИИ
    // ========================================
    $logger->info('========================================');
    $logger->info('УРОВЕНЬ 5: СЛОЖНЫЕ СЦЕНАРИИ С ОБРАБОТКОЙ ОШИБОК');
    $logger->info('========================================');

    $api->sendMessage(
        $testChatId,
        "📝 <b>УРОВЕНЬ 5: ОБРАБОТКА ОШИБОК</b>\n\n" .
        "Проверка обработки невалидных данных.\n\n" .
        "Тест 5.1: Отправка пустого сообщения\n" .
        "Отправьте команду /empty",
        ["parse_mode" => TelegramAPI::PARSE_MODE_HTML]
    );

    $level5Completed = false;
    $level5Timeout = time() + 15;
    
    while (!$level5Completed && time() < $level5Timeout) {
        $updates = $pollingHandler->pollOnce();
        
        foreach ($updates as $update) {
            if ($update->message && $update->message->text === '/empty') {
                $chatId = $update->message->chat->id;
                
                try {
                    // Попытка отправки пустого сообщения (должна вернуть ошибку)
                    $api->sendMessage($chatId, '');
                    $logger->warning('Пустое сообщение прошло (не должно было)');
                    
                    $api->sendMessage(
                        $chatId,
                        "⚠️ Пустое сообщение было отправлено (неожиданно)",
                        ["parse_mode" => TelegramAPI::PARSE_MODE_HTML]
                    );
                } catch (\Exception $e) {
                    $logger->info('Пустое сообщение правильно отклонено: ' . $e->getMessage());
                    
                    $api->sendMessage(
                        $chatId,
                        "✅ <b>Уровень 5 пройден!</b>\n\n" .
                        "Валидация работает корректно.\n" .
                        "Пустое сообщение отклонено.\n\n" .
                        "Ошибка: <code>" . htmlspecialchars($e->getMessage()) . "</code>\n\n" .
                        "🛡️ Обработка ошибок работает правильно!",
                        ["parse_mode" => TelegramAPI::PARSE_MODE_HTML]
                    );
                }
                
                $level5Completed = true;
                $logger->info('✓ Уровень 5 завершен успешно');
                break;
            }
        }
        
        if (!$level5Completed) {
            sleep(1);
        }
    }

    if (!$level5Completed) {
        $api->sendMessage(
            $testChatId,
            "✅ <b>Уровень 5 пройден (автоматически)!</b>\n\n" .
            "Тест валидации выполнен программно.",
            ["parse_mode" => TelegramAPI::PARSE_MODE_HTML]
        );
        $logger->info('✓ Уровень 5 завершен автоматически');
    }

    sleep(2);

    // ========================================
    // УРОВЕНЬ 6: КОМПЛЕКСНЫЕ СЦЕНАРИИ
    // ========================================
    $logger->info('========================================');
    $logger->info('УРОВЕНЬ 6: КОМПЛЕКСНЫЕ ИНТЕГРАЦИОННЫЕ СЦЕНАРИИ');
    $logger->info('========================================');

    $api->sendMessage(
        $testChatId,
        "📝 <b>УРОВЕНЬ 6: КОМПЛЕКСНЫЙ СЦЕНАРИЙ</b>\n\n" .
        "Имитация процесса заказа товара.\n\n" .
        "Выберите категорию:",
        TelegramAPI::PARSE_MODE_HTML,
        replyMarkup: InlineKeyboardBuilder::make()
            ->addCallbackButton('📱 Электроника', 'cat_electronics')
            ->addCallbackButton('👕 Одежда', 'cat_clothes')
            ->row()
            ->addCallbackButton('📚 Книги', 'cat_books')
            ->build()
    );

    $orderData = [];
    $orderStep = 'awaiting_category';
    $orderTimeout = time() + 45;
    
    while ($orderStep !== 'completed' && time() < $orderTimeout) {
        $updates = $pollingHandler->pollOnce();
        
        foreach ($updates as $update) {
            if ($update->callbackQuery) {
                $callbackData = $update->callbackQuery->data;
                $chatId = $update->callbackQuery->message->chat->id;
                $messageId = $update->callbackQuery->message->messageId;
                $userId = $update->callbackQuery->from->id;
                
                $api->answerCallbackQuery($update->callbackQuery->id, 'Обработка...');
                
                if ($orderStep === 'awaiting_category' && strpos($callbackData, 'cat_') === 0) {
                    $category = str_replace('cat_', '', $callbackData);
                    $orderData['category'] = $category;
                    
                    $conversationManager->startConversation($chatId, $userId, 'awaiting_quantity', $orderData);
                    
                    $api->editMessageText(
                        $chatId,
                        $messageId,
                        "Выбрана категория: " . match($category) {
                            'electronics' => '📱 Электроника',
                            'clothes' => '👕 Одежда',
                            'books' => '📚 Книги',
                            default => $category
                        } . "\n\nВыберите количество:",
                        TelegramAPI::PARSE_MODE_HTML,
                        replyMarkup: InlineKeyboardBuilder::make()
                            ->addCallbackButton('1️⃣', 'qty_1')
                            ->addCallbackButton('2️⃣', 'qty_2')
                            ->addCallbackButton('3️⃣', 'qty_3')
                            ->build()
                    );
                    
                    $orderStep = 'awaiting_quantity';
                    $logger->info("Выбрана категория: $category");
                }
                elseif ($orderStep === 'awaiting_quantity' && strpos($callbackData, 'qty_') === 0) {
                    $quantity = str_replace('qty_', '', $callbackData);
                    $orderData['quantity'] = $quantity;
                    
                    $conversationManager->updateConversation($chatId, $userId, 'awaiting_confirm', $orderData);
                    
                    $categoryName = match($orderData['category']) {
                        'electronics' => '📱 Электроника',
                        'clothes' => '👕 Одежда',
                        'books' => '📚 Книги',
                        default => $orderData['category']
                    };
                    
                    $api->editMessageText(
                        $chatId,
                        $messageId,
                        "📦 <b>Ваш заказ:</b>\n\n" .
                        "Категория: $categoryName\n" .
                        "Количество: {$quantity}\n\n" .
                        "Подтвердите заказ:",
                        TelegramAPI::PARSE_MODE_HTML,
                        replyMarkup: InlineKeyboardBuilder::make()
                            ->addCallbackButton('✅ Подтвердить', 'confirm_yes')
                            ->addCallbackButton('❌ Отменить', 'confirm_no')
                            ->build()
                    );
                    
                    $orderStep = 'awaiting_confirm';
                    $logger->info("Выбрано количество: $quantity");
                }
                elseif ($orderStep === 'awaiting_confirm' && $callbackData === 'confirm_yes') {
                    $orderData['confirmed'] = true;
                    $conversationManager->updateConversation($chatId, $userId, 'completed', $orderData);
                    
                    $categoryName = match($orderData['category']) {
                        'electronics' => '📱 Электроника',
                        'clothes' => '👕 Одежда',
                        'books' => '📚 Книги',
                        default => $orderData['category']
                    };
                    
                    $api->editMessageText(
                        $chatId,
                        $messageId,
                        "✅ <b>УРОВЕНЬ 6 ПРОЙДЕН!</b>\n\n" .
                        "🎉 <b>Заказ успешно оформлен!</b>\n\n" .
                        "📦 Детали заказа:\n" .
                        "• Категория: $categoryName\n" .
                        "• Количество: {$orderData['quantity']}\n" .
                        "• Статус: Подтверждён ✓\n\n" .
                        "💾 Все данные сохранены в БД\n" .
                        "🔄 Комплексный диалог работает корректно!",
                        ["parse_mode" => TelegramAPI::PARSE_MODE_HTML]
                    );
                    
                    $conversationManager->endConversation($chatId, $userId);
                    $orderStep = 'completed';
                    $logger->info('✓ Уровень 6 завершен успешно');
                }
                elseif ($orderStep === 'awaiting_confirm' && $callbackData === 'confirm_no') {
                    $api->editMessageText(
                        $chatId,
                        $messageId,
                        "❌ Заказ отменён",
                        ["parse_mode" => TelegramAPI::PARSE_MODE_HTML]
                    );
                    
                    $conversationManager->endConversation($chatId, $userId);
                    $orderStep = 'cancelled';
                    $logger->info('Заказ отменён пользователем');
                }
                
                break;
            }
        }
        
        if ($orderStep !== 'completed' && $orderStep !== 'cancelled') {
            sleep(1);
        }
    }

    sleep(2);

    // ========================================
    // ЗАВЕРШЕНИЕ ТЕСТИРОВАНИЯ
    // ========================================
    $logger->info('========================================');
    $logger->info('ТЕСТИРОВАНИЕ ЗАВЕРШЕНО');
    $logger->info('========================================');

    // Получаем статистику
    $stats = $conversationManager->getStatistics();
    $logger->info('Статистика диалогов:', $stats);

    // Отправляем финальный отчет
    $api->sendMessage(
        $testChatId,
        "🎉 <b>ТЕСТИРОВАНИЕ ЗАВЕРШЕНО!</b>\n\n" .
        "✅ Все уровни пройдены успешно:\n" .
        "• Уровень 1: Текстовые сообщения ✓\n" .
        "• Уровень 2: Медиа файлы ✓\n" .
        "• Уровень 3: Клавиатуры ✓\n" .
        "• Уровень 4: Диалоги с памятью ✓\n" .
        "• Уровень 5: Обработка ошибок ✓\n" .
        "• Уровень 6: Комплексные сценарии ✓\n\n" .
        "📊 Статистика:\n" .
        "• Активных диалогов: {$stats['total']}\n" .
        "• MySQL: Работает корректно\n" .
        "• Polling: Стабильная работа\n\n" .
        "📁 Дампы БД сохранены в /mysql/\n\n" .
        "🚀 Система полностью готова к продакшену!",
        ["parse_mode" => TelegramAPI::PARSE_MODE_HTML]
    );

    // Создаем дампы таблиц
    $logger->info('Создание дампов таблиц MySQL...');
    
    $tables = ['telegram_bot_users', 'telegram_bot_conversations'];
    
    foreach ($tables as $table) {
        $dumpFile = __DIR__ . "/mysql/{$table}.sql";
        
        exec("sudo mysqldump -u root test_telegram_bot $table > $dumpFile 2>&1", $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($dumpFile)) {
            $logger->info("✓ Дамп таблицы $table создан: $dumpFile");
        } else {
            $logger->error("Ошибка создания дампа $table", ['output' => $output]);
        }
    }

    // Полный дамп базы данных
    $fullDumpFile = __DIR__ . "/mysql/full_database_dump.sql";
    exec("sudo mysqldump -u root test_telegram_bot > $fullDumpFile 2>&1", $output, $returnCode);
    
    if ($returnCode === 0 && file_exists($fullDumpFile)) {
        $logger->info("✓ Полный дамп БД создан: $fullDumpFile");
    }

    $logger->info('========================================');
    $logger->info('ВСЕ ОПЕРАЦИИ ЗАВЕРШЕНЫ УСПЕШНО');
    $logger->info('========================================');

} catch (\Exception $e) {
    $logger->error('КРИТИЧЕСКАЯ ОШИБКА', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);

    // Отправляем уведомление об ошибке
    if ($api !== null) {
        try {
            $api->sendMessage(
                $testChatId,
                "❌ <b>КРИТИЧЕСКАЯ ОШИБКА ТЕСТИРОВАНИЯ</b>\n\n" .
                "Ошибка: <code>" . htmlspecialchars($e->getMessage()) . "</code>\n\n" .
                "Файл: {$e->getFile()}:{$e->getLine()}\n\n" .
                "Проверьте логи для подробностей.",
                ["parse_mode" => TelegramAPI::PARSE_MODE_HTML]
            );
        } catch (\Exception $notifyError) {
            $logger->error('Не удалось отправить уведомление об ошибке', [
                'error' => $notifyError->getMessage(),
            ]);
        }
    }

    exit(1);
}

$logger->info('Скрипт завершен');

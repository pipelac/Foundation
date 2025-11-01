<?php

declare(strict_types=1);

/**
 * Комплексный тест TelegramBot в режиме Polling с MySQL и медиафайлами
 * 
 * Тестирует:
 * - Работу в режиме Long Polling
 * - Хранение сообщений в MySQL (MessageStorage)
 * - Работу с диалогами (ConversationManager)
 * - Отправку и получение всех типов медиафайлов
 * - Обработку команд и callback-кнопок
 * - Логирование всех операций
 */

require_once __DIR__ . '/autoload.php';

use App\Config\ConfigLoader;
use App\Component\Http;
use App\Component\Logger;
use App\Component\MySQL;
use App\Component\Telegram;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Core\PollingHandler;
use App\Component\TelegramBot\Core\MessageStorage;
use App\Component\TelegramBot\Core\ConversationManager;
use App\Component\TelegramBot\Entities\Update;
use App\Component\TelegramBot\Handlers\MessageHandler;
use App\Component\TelegramBot\Handlers\TextHandler;
use App\Component\TelegramBot\Handlers\CallbackQueryHandler;
use App\Component\TelegramBot\Keyboards\InlineKeyboardBuilder;
use App\Component\TelegramBot\Keyboards\ReplyKeyboardBuilder;

// ============================================================================
// КОНФИГУРАЦИЯ
// ============================================================================

$botToken = '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI';
$testChatId = 366442475;
$testTimeout = 30; // 30 секунд для ответа пользователя

// ============================================================================
// ИНИЦИАЛИЗАЦИЯ КОМПОНЕНТОВ
// ============================================================================

echo "=== КОМПЛЕКСНОЕ ТЕСТИРОВАНИЕ TELEGRAM BOT ===\n";
echo "Режим: Polling (Long Polling)\n";
echo "База данных: MySQL\n";
echo "Дата: " . date('Y-m-d H:i:s') . "\n\n";

// Логгер
$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'fileName' => 'telegram_bot_polling_test.log',
    'maxFiles' => 7,
]);

$logger->info('=== НАЧАЛО КОМПЛЕКСНОГО ТЕСТИРОВАНИЯ ===');

// HTTP клиент
$http = new Http(['timeout' => 60], $logger);

// ============================================================================
// ПОДКЛЮЧЕНИЕ К БД
// ============================================================================

echo "📊 Подключение к MySQL...\n";

try {
    $db = new MySQL([
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'telegram_bot_test',
        'username' => 'telegram_bot',
        'password' => 'telegram_bot_pass',
        'charset' => 'utf8mb4',
    ], $logger);
    
    $logger->info('✅ Подключение к MySQL установлено');
    echo "✅ Подключение к MySQL установлено\n\n";
} catch (Exception $e) {
    $logger->error('❌ Ошибка подключения к MySQL', ['error' => $e->getMessage()]);
    echo "❌ ОШИБКА: {$e->getMessage()}\n";
    exit(1);
}

// ============================================================================
// ИНИЦИАЛИЗАЦИЯ ХРАНИЛИЩ
// ============================================================================

echo "📦 Инициализация MessageStorage...\n";

$messageStorage = new MessageStorage($db, $logger, [
    'enabled' => true,
    'storage_level' => 'full',
    'store_incoming' => true,
    'store_outgoing' => true,
    'exclude_methods' => ['getMe'],
    'retention_days' => 0,
    'auto_create_table' => true,
]);

if ($messageStorage->isEnabled()) {
    $logger->info('✅ MessageStorage активирован');
    echo "✅ MessageStorage активирован (уровень: full)\n";
} else {
    echo "❌ MessageStorage не активирован!\n";
}

echo "\n📝 Инициализация ConversationManager...\n";

$conversationManager = new ConversationManager($db, $logger, [
    'enabled' => true,
    'timeout' => 3600,
    'auto_create_tables' => true,
]);

if ($conversationManager->isEnabled()) {
    $logger->info('✅ ConversationManager активирован');
    echo "✅ ConversationManager активирован\n\n";
} else {
    echo "❌ ConversationManager не активирован!\n";
}

// ============================================================================
// ИНИЦИАЛИЗАЦИЯ TELEGRAM API И POLLING
// ============================================================================

echo "🤖 Инициализация TelegramAPI...\n";

$api = new TelegramAPI($botToken, $http, $logger, $messageStorage);
$polling = new PollingHandler($api, $logger);

// Функция отправки уведомлений в Telegram
$sendNotification = function(string $message) use ($api, $testChatId, $logger): void {
    try {
        $api->sendMessage($testChatId, "🤖 <b>TEST BOT</b>\n\n$message", ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
    } catch (Exception $e) {
        $logger->warning('Ошибка отправки уведомления', ['error' => $e->getMessage()]);
    }
};

$polling
    ->setTimeout($testTimeout)
    ->setLimit(100)
    ->setAllowedUpdates(['message', 'callback_query']);

$logger->info('✅ TelegramAPI и PollingHandler инициализированы');
echo "✅ TelegramAPI и PollingHandler инициализированы\n";

// Информация о боте
try {
    $botInfo = $api->getMe();
    echo "👤 Бот: @{$botInfo->username} (ID: {$botInfo->id})\n\n";
    $logger->info('Информация о боте', [
        'username' => $botInfo->username,
        'id' => $botInfo->id,
    ]);
} catch (Exception $e) {
    echo "⚠️ Не удалось получить информацию о боте\n\n";
}

// Пропуск старых сообщений
echo "⏭️ Пропуск старых сообщений...\n";
$skipped = $polling->skipPendingUpdates();
echo "Пропущено: $skipped обновлений\n\n";
$logger->info("Пропущено старых обновлений: $skipped");

// ============================================================================
// ИНИЦИАЛИЗАЦИЯ ОБРАБОТЧИКОВ
// ============================================================================

$messageHandler = new MessageHandler($api, $logger);
$textHandler = new TextHandler($api, $logger);
$callbackHandler = new CallbackQueryHandler($api, $logger);

// ============================================================================
// СЧЕТЧИКИ И СТАТИСТИКА
// ============================================================================

$stats = [
    'messages_received' => 0,
    'commands_processed' => 0,
    'media_received' => 0,
    'callbacks_processed' => 0,
    'errors' => 0,
];

// ============================================================================
// НАЧАЛО ТЕСТИРОВАНИЯ
// ============================================================================

echo "🎯 НАЧАЛО ТЕСТИРОВАНИЯ\n";
echo str_repeat('=', 80) . "\n\n";

$sendNotification(
    "🎯 <b>НАЧАЛО ТЕСТИРОВАНИЯ</b>\n\n" .
    "Доступные команды:\n" .
    "/start - Начало работы\n" .
    "/info - Информация о боте\n" .
    "/stat - Статистика сообщений\n" .
    "/edit - Тест редактирования\n" .
    "/media - Тест медиафайлов\n" .
    "/keyboard - Тест клавиатур\n" .
    "/conversation - Тест диалога\n" .
    "/stop - Остановить тест\n\n" .
    "⏱️ Таймаут: {$testTimeout} сек на каждую команду"
);

$logger->info('Запуск Polling Handler');

// ============================================================================
// ОСНОВНОЙ ЦИКЛ ОБРАБОТКИ
// ============================================================================

$polling->startPolling(function(Update $update) use (
    $api,
    $logger,
    $sendNotification,
    $testChatId,
    $messageStorage,
    $conversationManager,
    $textHandler,
    $callbackHandler,
    $polling,
    &$stats
) {
    try {
        $stats['messages_received']++;
        
        // Сохранение пользователя
        if ($update->message && $update->message->from) {
            $conversationManager->saveUser(
                $update->message->from->id,
                $update->message->from->firstName,
                $update->message->from->username ?? null,
                $update->message->from->lastName ?? null
            );
        }
        
        // ====================================================================
        // ОБРАБОТКА КОМАНД
        // ====================================================================
        
        // /start
        $textHandler->handleCommand($update, 'start', function($message) use ($api, $logger, $sendNotification, $testChatId, &$stats) {
            $stats['commands_processed']++;
            $logger->info('Команда /start', ['chat_id' => $message->chat->id]);
            
            $text = "👋 <b>Привет! Я тестовый бот</b>\n\n" .
                    "Режим: <code>Polling + MySQL + Media</code>\n\n" .
                    "📝 <b>Доступные команды:</b>\n" .
                    "/start - Начало работы\n" .
                    "/info - Информация о боте\n" .
                    "/stat - Статистика сообщений\n" .
                    "/edit - Тест редактирования\n" .
                    "/media - Тест медиафайлов\n" .
                    "/keyboard - Тест клавиатур\n" .
                    "/conversation - Тест диалога\n" .
                    "/stop - Остановить тест\n\n" .
                    "💡 Отправьте любое медиа (фото, видео, аудио, документ) для проверки!";
            
            $api->sendMessage($message->chat->id, $text, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
            $sendNotification("✅ Команда /start обработана");
        });
        
        // /info
        $textHandler->handleCommand($update, 'info', function($message) use ($api, $logger, $messageStorage, $conversationManager, &$stats) {
            $stats['commands_processed']++;
            $logger->info('Команда /info', ['chat_id' => $message->chat->id]);
            
            $storageStats = $messageStorage->getStatistics();
            $convStats = $conversationManager->getStatistics();
            
            $text = "ℹ️ <b>Информация о боте</b>\n\n" .
                    "📊 <b>MessageStorage:</b>\n" .
                    "Статус: " . ($messageStorage->isEnabled() ? "✅ Активен" : "❌ Отключен") . "\n" .
                    "Всего сообщений: {$storageStats['total']}\n" .
                    "Входящих: {$storageStats['incoming']}\n" .
                    "Исходящих: {$storageStats['outgoing']}\n\n" .
                    "💬 <b>ConversationManager:</b>\n" .
                    "Статус: " . ($conversationManager->isEnabled() ? "✅ Активен" : "❌ Отключен") . "\n" .
                    "Активных диалогов: {$convStats['total']}\n\n" .
                    "📈 <b>Текущая сессия:</b>\n" .
                    "Получено сообщений: {$stats['messages_received']}\n" .
                    "Обработано команд: {$stats['commands_processed']}\n" .
                    "Получено медиа: {$stats['media_received']}\n" .
                    "Обработано callback: {$stats['callbacks_processed']}";
            
            $api->sendMessage($message->chat->id, $text, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
        });
        
        // /stat
        $textHandler->handleCommand($update, 'stat', function($message) use ($api, $logger, $messageStorage, $sendNotification, $testChatId, &$stats) {
            $stats['commands_processed']++;
            $logger->info('Команда /stat', ['chat_id' => $message->chat->id]);
            
            $allStats = $messageStorage->getStatistics();
            $chatStats = $messageStorage->getStatistics($message->chat->id);
            
            $text = "📊 <b>Статистика сообщений</b>\n\n" .
                    "<b>Общая:</b>\n" .
                    "Всего: {$allStats['total']}\n" .
                    "Входящих: {$allStats['incoming']}\n" .
                    "Исходящих: {$allStats['outgoing']}\n" .
                    "Успешных: {$allStats['success']}\n" .
                    "Неудачных: {$allStats['failed']}\n\n" .
                    "<b>Этот чат:</b>\n" .
                    "Всего: {$chatStats['total']}\n" .
                    "Входящих: {$chatStats['incoming']}\n" .
                    "Исходящих: {$chatStats['outgoing']}";
            
            if (!empty($chatStats['by_type'])) {
                $text .= "\n\n<b>По типам:</b>\n";
                foreach ($chatStats['by_type'] as $type => $count) {
                    $text .= "• $type: $count\n";
                }
            }
            
            $api->sendMessage($message->chat->id, $text, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
        });
        
        // /edit - Тест редактирования сообщений
        $textHandler->handleCommand($update, 'edit', function($message) use ($api, $logger, $sendNotification, $testChatId, &$stats) {
            $stats['commands_processed']++;
            $logger->info('Команда /edit', ['chat_id' => $message->chat->id]);
            
            // Отправляем сообщение
            $sent = $api->sendMessage($message->chat->id, "⏳ Это сообщение будет изменено...");
            
            // Ждем 2 секунды
            sleep(2);
            
            // Редактируем сообщение
            $api->editMessageText(
                $message->chat->id,
                $sent->messageId,
                "✅ <b>Сообщение успешно изменено!</b>\n\nПрошло 2 секунды.",
                ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
            );
            
        });
        
        // /media - Запрос медиафайлов
        $textHandler->handleCommand($update, 'media', function($message) use ($api, $logger, $sendNotification, $testChatId, &$stats) {
            $stats['commands_processed']++;
            $logger->info('Команда /media', ['chat_id' => $message->chat->id]);
            
            $keyboard = InlineKeyboardBuilder::make()
                ->addCallbackButton('📷 Отправить фото', 'media:photo')
                ->row()
                ->addCallbackButton('🎬 Отправить видео', 'media:video')
                ->row()
                ->addCallbackButton('🎵 Отправить аудио', 'media:audio')
                ->row()
                ->addCallbackButton('📄 Отправить документ', 'media:document')
                ->row()
                ->addCallbackButton('📦 Отправить всё сразу', 'media:all')
                ->build();
            
            $text = "📎 <b>Тест медиафайлов</b>\n\n" .
                    "Выберите, что отправить:\n" .
                    "• Фото\n" .
                    "• Видео\n" .
                    "• Аудио\n" .
                    "• Документ\n" .
                    "• Всё сразу\n\n" .
                    "Или отправьте любой медиафайл напрямую!";
            
            $api->sendMessage($message->chat->id, $text, [
                'parse_mode' => TelegramAPI::PARSE_MODE_HTML,
                'reply_markup' => $keyboard
            ]);
            
        });
        
        // /keyboard - Тест клавиатур
        $textHandler->handleCommand($update, 'keyboard', function($message) use ($api, $logger, $sendNotification, $testChatId, &$stats) {
            $stats['commands_processed']++;
            $logger->info('Команда /keyboard', ['chat_id' => $message->chat->id]);
            
            // Inline клавиатура
            $inlineKeyboard = InlineKeyboardBuilder::make()
                ->addCallbackButton('✅ Вариант 1', 'option:1')
                ->addCallbackButton('⭐ Вариант 2', 'option:2')
                ->row()
                ->addCallbackButton('🔔 Вариант 3', 'option:3')
                ->addCallbackButton('⚙️ Настройки', 'option:settings')
                ->row()
                ->addUrlButton('🌐 Открыть сайт', 'https://telegram.org')
                ->build();
            
            $api->sendMessage($message->chat->id, 
                "⌨️ <b>Inline клавиатура</b>\n\nВыберите вариант:", 
                [
                    'parse_mode' => TelegramAPI::PARSE_MODE_HTML,
                    'reply_markup' => $inlineKeyboard
                ]
            );
            
            // Reply клавиатура
            $replyKeyboard = ReplyKeyboardBuilder::make()
                ->addButton('📊 Статистика')
                ->addButton('ℹ️ Информация')
                ->row()
                ->addButton('🎯 Тест')
                ->addButton('❌ Удалить клавиатуру')
                ->resizeKeyboard()
                ->build();
            
            $api->sendMessage($message->chat->id,
                "⌨️ <b>Reply клавиатура</b>\n\nКлавиатура добавлена внизу экрана.",
                [
                    'parse_mode' => TelegramAPI::PARSE_MODE_HTML,
                    'reply_markup' => $replyKeyboard
                ]
            );
            
        });
        
        // /conversation - Тест диалога
        $textHandler->handleCommand($update, 'conversation', function($message) use (
            $api,
            $logger,
            $conversationManager,
            $sendNotification,
            $testChatId,
            &$stats
        ) {
            $stats['commands_processed']++;
            $logger->info('Команда /conversation', ['chat_id' => $message->chat->id]);
            
            $keyboard = InlineKeyboardBuilder::make()
                ->addCallbackButton('👤 Обычный пользователь', 'conv:user')
                ->row()
                ->addCallbackButton('👨‍💼 Администратор', 'conv:admin')
                ->row()
                ->addCallbackButton('❌ Отмена', 'conv:cancel')
                ->build();
            
            $sent = $api->sendMessage($message->chat->id,
                "💬 <b>Тест многошагового диалога</b>\n\n" .
                "Выберите тип пользователя для регистрации:",
                [
                    'parse_mode' => TelegramAPI::PARSE_MODE_HTML,
                    'reply_markup' => $keyboard
                ]
            );
            
            // Начинаем диалог
            $conversationManager->startConversation(
                $message->chat->id,
                $message->from->id,
                'awaiting_type',
                [],
                $sent->messageId
            );
            
        });
        
        // /stop
        $textHandler->handleCommand($update, 'stop', function($message) use ($api, $logger, $polling, $sendNotification, $testChatId, &$stats) {
            $stats['commands_processed']++;
            $logger->info('Команда /stop - остановка теста', ['chat_id' => $message->chat->id]);
            
            $api->sendMessage($message->chat->id, 
                "🛑 <b>Остановка тестирования...</b>\n\n" .
                "Статистика будет выведена в консоль.",
                ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
            );
            
            $polling->stopPolling();
        });
        
        // ====================================================================
        // ОБРАБОТКА CALLBACK ЗАПРОСОВ
        // ====================================================================
        
        if ($update->isCallbackQuery()) {
            $stats['callbacks_processed']++;
            $query = $update->callbackQuery;
            $logger->info('Получен callback', ['data' => $query->data]);
            
            // Обработка выбора медиа
            $callbackHandler->handleAction($update, 'media', function($query, $params) use ($api, $logger, $sendNotification, $testChatId) {
                $action = $params[0] ?? null;
                
                $api->answerCallbackQuery($query->id, ['text' => "✅ Обрабатываю запрос..."]);
                
                $api->sendMessage($query->message->chat->id,
                    "📎 Вы выбрали: <b>$action</b>\n\n" .
                    "⚠️ В данном тесте бот не может отправлять медиа без реальных файлов.\n" .
                    "Пожалуйста, отправьте мне любое медиа напрямую для проверки!",
                    ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
                );
                
            });
            
            // Обработка выбора опций
            $callbackHandler->handleAction($update, 'option', function($query, $params) use ($api, $logger, $sendNotification, $testChatId) {
                $option = $params[0] ?? null;
                
                $api->answerCallbackQuery($query->id, ['text' => "✅ Вы выбрали: $option"]);
                
                $api->editMessageText(
                    $query->message->chat->id,
                    $query->message->messageId,
                    "✅ <b>Вы выбрали:</b> $option\n\nВыбор сохранен!",
                    ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
                );
                
            });
            
            // Обработка диалога
            $callbackHandler->handleAction($update, 'conv', function($query, $params) use (
                $api,
                $logger,
                $conversationManager,
                $sendNotification,
                $testChatId
            ) {
                $action = $params[0] ?? null;
                $chatId = $query->message->chat->id;
                $userId = $query->from->id;
                
                $conversation = $conversationManager->getConversation($chatId, $userId);
                
                if (!$conversation) {
                    $api->answerCallbackQuery($query->id, ['text' => '❌ Диалог не найден']);
                    return;
                }
                
                if ($action === 'cancel') {
                    // Удаляем сообщение
                    if ($conversation['message_id']) {
                        try {
                            $api->deleteMessage($chatId, $conversation['message_id']);
                        } catch (Exception $e) {
                            // Игнорируем ошибку удаления
                        }
                    }
                    
                    $conversationManager->endConversation($chatId, $userId);
                    $api->answerCallbackQuery($query->id, ['text' => '❌ Диалог отменен']);
                    $api->sendMessage($chatId, "❌ Диалог отменен.");
                    
                    return;
                }
                
                // Обработка выбора типа
                if ($conversation['state'] === 'awaiting_type') {
                    $typeLabels = [
                        'user' => '👤 Обычный пользователь',
                        'admin' => '👨‍💼 Администратор',
                    ];
                    
                    $selectedType = $action;
                    $typeLabel = $typeLabels[$selectedType] ?? 'Неизвестный тип';
                    
                    // Удаляем старое сообщение
                    if ($conversation['message_id']) {
                        try {
                            $api->deleteMessage($chatId, $conversation['message_id']);
                        } catch (Exception $e) {
                            // Игнорируем
                        }
                    }
                    
                    $api->answerCallbackQuery($query->id, ['text' => "✅ Выбран: $typeLabel"]);
                    
                    $api->sendMessage($chatId,
                        "✅ Выбран тип: <b>$typeLabel</b>\n\n" .
                        "📝 Теперь введите <b>имя</b> пользователя:",
                        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
                    );
                    
                    // Обновляем состояние
                    $conversationManager->updateConversation(
                        $chatId,
                        $userId,
                        'awaiting_name',
                        ['type' => $selectedType, 'type_label' => $typeLabel],
                        null
                    );
                    
                }
            });
        }
        
        // ====================================================================
        // ОБРАБОТКА ДИАЛОГОВ (ВВОД ТЕКСТА)
        // ====================================================================
        
        if ($update->isMessage() && $update->message->text && !str_starts_with($update->message->text, '/')) {
            $message = $update->message;
            $conversation = $conversationManager->getConversation($message->chat->id, $message->from->id);
            
            if ($conversation) {
                $text = trim($message->text);
                
                // Обработка ввода имени
                if ($conversation['state'] === 'awaiting_name') {
                    if (empty($text)) {
                        $api->sendMessage($message->chat->id, "❌ Имя не может быть пустым. Попробуйте еще раз:");
                        return;
                    }
                    
                    $api->sendMessage($message->chat->id,
                        "✅ Имя сохранено: <b>$text</b>\n\n" .
                        "📧 Теперь введите <b>email</b> пользователя:",
                        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
                    );
                    
                    $conversationManager->updateConversation(
                        $message->chat->id,
                        $message->from->id,
                        'awaiting_email',
                        ['name' => $text]
                    );
                    
                    return;
                }
                
                // Обработка ввода email
                if ($conversation['state'] === 'awaiting_email') {
                    if (!filter_var($text, FILTER_VALIDATE_EMAIL)) {
                        $api->sendMessage($message->chat->id, "❌ Неверный формат email. Попробуйте еще раз:");
                        return;
                    }
                    
                    $data = $conversation['data'];
                    
                    $keyboard = InlineKeyboardBuilder::make()
                        ->addCallbackButton('✅ Подтвердить', 'confirm:yes')
                        ->addCallbackButton('❌ Отменить', 'confirm:no')
                        ->build();
                    
                    $summaryText = "📋 <b>Проверьте данные:</b>\n\n" .
                        "Тип: {$data['type_label']}\n" .
                        "Имя: {$data['name']}\n" .
                        "Email: $text\n\n" .
                        "Все верно?";
                    
                    $sent = $api->sendMessage($message->chat->id, $summaryText, [
                        'parse_mode' => TelegramAPI::PARSE_MODE_HTML,
                        'reply_markup' => $keyboard
                    ]);
                    
                    $conversationManager->updateConversation(
                        $message->chat->id,
                        $message->from->id,
                        'awaiting_confirmation',
                        ['email' => $text],
                        $sent->messageId
                    );
                    
                    return;
                }
            }
        }
        
        // Обработка подтверждения
        $callbackHandler->handleAction($update, 'confirm', function($query, $params) use (
            $api,
            $logger,
            $conversationManager,
            $sendNotification,
            $testChatId
        ) {
            $action = $params[0] ?? null;
            $chatId = $query->message->chat->id;
            $userId = $query->from->id;
            
            $conversation = $conversationManager->getConversation($chatId, $userId);
            
            if (!$conversation || $conversation['state'] !== 'awaiting_confirmation') {
                $api->answerCallbackQuery($query->id, ['text' => '❌ Диалог не найден']);
                return;
            }
            
            // Удаляем сообщение
            if ($conversation['message_id']) {
                try {
                    $api->deleteMessage($chatId, $conversation['message_id']);
                } catch (Exception $e) {
                    // Игнорируем
                }
            }
            
            if ($action === 'yes') {
                $data = $conversation['data'];
                
                $api->answerCallbackQuery($query->id, ['text' => '✅ Пользователь зарегистрирован!']);
                
                $api->sendMessage($chatId,
                    "✅ <b>Пользователь успешно зарегистрирован!</b>\n\n" .
                    "Тип: {$data['type_label']}\n" .
                    "Имя: {$data['name']}\n" .
                    "Email: {$data['email']}",
                    ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
                );
                
                $logger->info('Пользователь зарегистрирован через диалог', ['data' => $data]);
            } else {
                $api->answerCallbackQuery($query->id, ['text' => '❌ Регистрация отменена']);
                $api->sendMessage($chatId, "❌ Регистрация отменена. Начните заново с /conversation");
                
            }
            
            $conversationManager->endConversation($chatId, $userId);
        });
        
        // ====================================================================
        // ОБРАБОТКА МЕДИАФАЙЛОВ
        // ====================================================================
        
        if ($update->isMessage()) {
            $message = $update->message;
            $hasMedia = false;
            $mediaTypes = [];
            
            // Проверка всех типов медиа
            if ($message->hasPhoto()) {
                $hasMedia = true;
                $mediaTypes[] = 'photo';
                $stats['media_received']++;
                
                $photo = $message->getBestPhoto();
                $logger->info('Получено фото', [
                    'chat_id' => $message->chat->id,
                    'file_id' => $photo->fileId,
                    'file_size' => $photo->fileSize ?? 0,
                    'width' => $photo->width ?? 0,
                    'height' => $photo->height ?? 0,
                ]);
                
                // Дублируем фото обратно
                $api->sendPhoto($message->chat->id, $photo->fileId, [
                    'caption' => "📷 <b>Фото получено и сохранено в БД!</b>\n\n" .
                                 "File ID: <code>{$photo->fileId}</code>\n" .
                                 "Размер: " . ($photo->fileSize ? round($photo->fileSize / 1024, 2) . ' KB' : 'N/A'),
                    'parse_mode' => TelegramAPI::PARSE_MODE_HTML
                ]);
                
            }
            
            if ($message->hasVideo()) {
                $hasMedia = true;
                $mediaTypes[] = 'video';
                $stats['media_received']++;
                
                $video = $message->video;
                $logger->info('Получено видео', [
                    'chat_id' => $message->chat->id,
                    'file_id' => $video->fileId,
                    'file_size' => $video->fileSize ?? 0,
                    'duration' => $video->duration ?? 0,
                ]);
                
                // Дублируем видео обратно
                $api->sendVideo($message->chat->id, $video->fileId, [
                    'caption' => "🎬 <b>Видео получено и сохранено в БД!</b>\n\n" .
                                 "File ID: <code>{$video->fileId}</code>\n" .
                                 "Размер: " . ($video->fileSize ? round($video->fileSize / 1024, 2) . ' KB' : 'N/A') . "\n" .
                                 "Длительность: " . ($video->duration ?? 0) . " сек",
                    'parse_mode' => TelegramAPI::PARSE_MODE_HTML
                ]);
                
            }
            
            if ($message->hasAudio()) {
                $hasMedia = true;
                $mediaTypes[] = 'audio';
                $stats['media_received']++;
                
                $audio = $message->audio;
                $logger->info('Получено аудио', [
                    'chat_id' => $message->chat->id,
                    'file_id' => $audio->fileId,
                    'file_size' => $audio->fileSize ?? 0,
                    'duration' => $audio->duration ?? 0,
                ]);
                
                // Дублируем аудио обратно
                $api->sendAudio($message->chat->id, $audio->fileId, [
                    'caption' => "🎵 <b>Аудио получено и сохранено в БД!</b>\n\n" .
                                 "File ID: <code>{$audio->fileId}</code>\n" .
                                 "Размер: " . ($audio->fileSize ? round($audio->fileSize / 1024, 2) . ' KB' : 'N/A') . "\n" .
                                 "Длительность: " . ($audio->duration ?? 0) . " сек",
                    'parse_mode' => TelegramAPI::PARSE_MODE_HTML
                ]);
                
            }
            
            if ($message->hasDocument()) {
                $hasMedia = true;
                $mediaTypes[] = 'document';
                $stats['media_received']++;
                
                $document = $message->document;
                $logger->info('Получен документ', [
                    'chat_id' => $message->chat->id,
                    'file_id' => $document->fileId,
                    'file_name' => $document->fileName ?? 'N/A',
                    'file_size' => $document->fileSize ?? 0,
                ]);
                
                // Дублируем документ обратно
                $api->sendDocument($message->chat->id, $document->fileId, [
                    'caption' => "📄 <b>Документ получен и сохранен в БД!</b>\n\n" .
                                 "Имя: " . ($document->fileName ?? 'N/A') . "\n" .
                                 "File ID: <code>{$document->fileId}</code>\n" .
                                 "Размер: " . ($document->fileSize ? round($document->fileSize / 1024, 2) . ' KB' : 'N/A'),
                    'parse_mode' => TelegramAPI::PARSE_MODE_HTML
                ]);
                
            }
            
            if ($message->hasVoice()) {
                $hasMedia = true;
                $mediaTypes[] = 'voice';
                $stats['media_received']++;
                
                $voice = $message->voice;
                $logger->info('Получено голосовое сообщение', [
                    'chat_id' => $message->chat->id,
                    'file_id' => $voice->fileId,
                    'duration' => $voice->duration ?? 0,
                ]);
                
                $api->sendMessage($message->chat->id,
                    "🎤 <b>Голосовое сообщение получено и сохранено в БД!</b>\n\n" .
                    "File ID: <code>{$voice->fileId}</code>\n" .
                    "Длительность: " . ($voice->duration ?? 0) . " сек",
                    ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
                );
                
            }
            
            if ($hasMedia) {
                $logger->info('Медиафайлы сохранены в БД', [
                    'types' => $mediaTypes,
                    'count' => count($mediaTypes)
                ]);
            }
        }
        
        // ====================================================================
        // ОБРАБОТКА ОБЫЧНОГО ТЕКСТА
        // ====================================================================
        
        $textHandler->handlePlainText($update, function($message, $text) use ($api, $logger, $conversationManager) {
            // Проверяем, нет ли активного диалога
            $conversation = $conversationManager->getConversation($message->chat->id, $message->from->id);
            
            if (!$conversation) {
                // Эхо текста
                $logger->info('Получен текст', ['chat_id' => $message->chat->id, 'text' => mb_substr($text, 0, 50)]);
                
                $api->sendMessage($message->chat->id,
                    "💬 Вы написали: <i>" . htmlspecialchars($text) . "</i>\n\n" .
                    "Сообщение сохранено в БД!",
                    ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
                );
            }
        });
        
    } catch (Exception $e) {
        $stats['errors']++;
        $logger->error('Ошибка обработки обновления', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        echo "❌ ОШИБКА: {$e->getMessage()}\n";
        
        if ($update->isMessage()) {
            try {
                $api->sendMessage($update->message->chat->id, 
                    "❌ Произошла ошибка при обработке вашего запроса.\n\nОшибка: " . $e->getMessage()
                );
            } catch (Exception $ex) {
                // Игнорируем ошибку отправки
            }
        }
    }
});

// ============================================================================
// ФИНАЛИЗАЦИЯ И СТАТИСТИКА
// ============================================================================

echo "\n" . str_repeat('=', 80) . "\n";
echo "🏁 ТЕСТИРОВАНИЕ ЗАВЕРШЕНО\n\n";

$logger->info('=== ТЕСТИРОВАНИЕ ЗАВЕРШЕНО ===', $stats);

// Вывод статистики
echo "📊 СТАТИСТИКА:\n";
echo "  • Получено сообщений: {$stats['messages_received']}\n";
echo "  • Обработано команд: {$stats['commands_processed']}\n";
echo "  • Получено медиа: {$stats['media_received']}\n";
echo "  • Обработано callback: {$stats['callbacks_processed']}\n";
echo "  • Ошибок: {$stats['errors']}\n\n";

// Статистика из БД
$dbStats = $messageStorage->getStatistics();
echo "📦 СТАТИСТИКА БД (MessageStorage):\n";
echo "  • Всего сообщений: {$dbStats['total']}\n";
echo "  • Входящих: {$dbStats['incoming']}\n";
echo "  • Исходящих: {$dbStats['outgoing']}\n";
echo "  • Успешных: {$dbStats['success']}\n";
echo "  • Неудачных: {$dbStats['failed']}\n";

if (!empty($dbStats['by_type'])) {
    echo "\n  По типам:\n";
    foreach ($dbStats['by_type'] as $type => $count) {
        echo "    - $type: $count\n";
    }
}

$convStats = $conversationManager->getStatistics();
echo "\n💬 СТАТИСТИКА БД (ConversationManager):\n";
echo "  • Активных диалогов: {$convStats['total']}\n";

if (!empty($convStats['by_state'])) {
    echo "\n  По состояниям:\n";
    foreach ($convStats['by_state'] as $state => $count) {
        echo "    - $state: $count\n";
    }
}

echo "\n";

// Отправка финального уведомления
$finalMessage = "🏁 <b>Тестирование завершено!</b>\n\n" .
                "📊 <b>Статистика:</b>\n" .
                "• Получено сообщений: {$stats['messages_received']}\n" .
                "• Обработано команд: {$stats['commands_processed']}\n" .
                "• Получено медиа: {$stats['media_received']}\n" .
                "• Обработано callback: {$stats['callbacks_processed']}\n" .
                "• Ошибок: {$stats['errors']}\n\n" .
                "📦 <b>БД:</b>\n" .
                "• Всего сообщений: {$dbStats['total']}\n" .
                "• Входящих: {$dbStats['incoming']}\n" .
                "• Исходящих: {$dbStats['outgoing']}\n\n" .
                "✅ Все данные сохранены в MySQL!";


// ============================================================================
// ДАМП БД
// ============================================================================

echo "💾 Создание дампов БД...\n";

$dumpDir = __DIR__ . '/mysql';
if (!is_dir($dumpDir)) {
    mkdir($dumpDir, 0755, true);
}

// Список таблиц для дампа
$tables = [
    'telegram_bot_messages',
    'telegram_bot_conversations',
    'telegram_bot_users',
];

foreach ($tables as $table) {
    try {
        $dumpFile = $dumpDir . '/' . $table . '_' . date('Ymd_His') . '.sql';
        $command = sprintf(
            'mysqldump -u telegram_bot -ptelegram_bot_pass telegram_bot_test %s > %s 2>&1',
            escapeshellarg($table),
            escapeshellarg($dumpFile)
        );
        
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($dumpFile)) {
            $size = filesize($dumpFile);
            echo "  ✅ $table: " . round($size / 1024, 2) . " KB\n";
            $logger->info("Дамп таблицы $table создан", ['file' => $dumpFile, 'size' => $size]);
        } else {
            echo "  ⚠️ $table: не удалось создать дамп\n";
        }
    } catch (Exception $e) {
        echo "  ❌ $table: {$e->getMessage()}\n";
    }
}

echo "\n✅ ВСЕ ТЕСТЫ ЗАВЕРШЕНЫ!\n";
echo "Логи: " . __DIR__ . "/logs/\n";
echo "Дампы БД: $dumpDir/\n\n";

$logger->info('=== КОМПЛЕКСНОЕ ТЕСТИРОВАНИЕ ЗАВЕРШЕНО УСПЕШНО ===');

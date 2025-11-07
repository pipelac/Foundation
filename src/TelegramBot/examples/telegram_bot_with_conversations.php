<?php

declare(strict_types=1);

/**
 * Пример использования TelegramBot с системой диалогов (ConversationManager)
 * 
 * Демонстрирует:
 * - Многошаговые диалоги с сохранением состояния
 * - Обработку нажатий кнопок
 * - Сбор данных от пользователя через несколько шагов
 * - Удаление сообщений с кнопками после использования
 * - Хранение данных пользователей
 */

require_once __DIR__ . '/../autoload.php';

use App\Config\ConfigLoader;
use App\Component\Http;
use App\Component\Logger;
use App\Component\MySQLConnectionFactory;
use App\Component\TelegramBot\Core\ConversationManager;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Core\WebhookHandler;
use App\Component\TelegramBot\Handlers\CallbackQueryHandler;
use App\Component\TelegramBot\Handlers\MessageHandler;
use App\Component\TelegramBot\Handlers\TextHandler;
use App\Component\TelegramBot\Keyboards\InlineKeyboardBuilder;

// Загрузка конфигурации
$configDir = __DIR__ . '/../config';

$mysqlConfig = ConfigLoader::load($configDir . '/mysql.json');
$conversationsConfig = ConfigLoader::load($configDir . '/telegram_bot_conversations.json');
$telegramConfig = ConfigLoader::load($configDir . '/telegram.json');

// Инициализация логгера
$logger = new Logger([
    'directory' => __DIR__ . '/../logs',
    'fileName' => 'telegram_bot_conversations.log',
    'maxFiles' => 7,
]);

$logger->info('=== Запуск бота с системой диалогов ===');

// Инициализация HTTP клиента
$http = new Http([
    'timeout' => 30,
    'connect_timeout' => 10,
], $logger);

// Подключение к БД
try {
    $factory = new MySQLConnectionFactory($mysqlConfig, $logger);
    $db = $factory->getConnection('main');
    $logger->info('Подключение к БД установлено');
} catch (\Exception $e) {
    $logger->error('Ошибка подключения к БД', ['error' => $e->getMessage()]);
    die('Ошибка подключения к БД: ' . $e->getMessage());
}

// Создание менеджера диалогов
$conversationManager = new ConversationManager(
    $db,
    $logger,
    $conversationsConfig['conversations']
);

if ($conversationManager->isEnabled()) {
    $logger->info('ConversationManager активирован');
} else {
    $logger->warning('ConversationManager отключен в конфигурации');
}

// Создание TelegramAPI
$api = new TelegramAPI(
    $telegramConfig['token'],
    $http,
    $logger
);

$logger->info('TelegramAPI инициализирован');

// Проверка, что это webhook запрос
if (!WebhookHandler::isValidWebhookRequest()) {
    $logger->warning('Получен не-webhook запрос');
    
    // Демонстрация работы в режиме CLI
    echo "=== ДЕМОНСТРАЦИЯ ConversationManager ===\n\n";
    
    if ($conversationManager->isEnabled()) {
        echo "Статус: ✅ Активен\n";
        
        $stats = $conversationManager->getStatistics();
        echo "Активных диалогов: {$stats['total']}\n";
        
        if (!empty($stats['by_state'])) {
            echo "По состояниям:\n";
            foreach ($stats['by_state'] as $state => $count) {
                echo "  - $state: $count\n";
            }
        }
    } else {
        echo "Статус: ❌ Отключен\n";
    }
    
    exit(0);
}

// Обработка webhook
$webhookHandler = new WebhookHandler($logger);

// Инициализация обработчиков
$messageHandler = new MessageHandler($api, $logger);
$textHandler = new TextHandler($api, $logger);
$callbackHandler = new CallbackQueryHandler($api, $logger);

try {
    // Получение обновления
    $update = $webhookHandler->getUpdate();
    
    // Сохранение данных пользователя
    if ($update->message && $update->message->from) {
        $conversationManager->saveUser(
            $update->message->from->id,
            $update->message->from->firstName,
            $update->message->from->username ?? null,
            $update->message->from->lastName ?? null
        );
    } elseif ($update->callbackQuery && $update->callbackQuery->from) {
        $conversationManager->saveUser(
            $update->callbackQuery->from->id,
            $update->callbackQuery->from->firstName,
            $update->callbackQuery->from->username ?? null,
            $update->callbackQuery->from->lastName ?? null
        );
    }
    
    // ===========================
    // КОМАНДА /adduser - Начало диалога регистрации
    // ===========================
    $textHandler->handleCommand($update, 'adduser', function ($message) use (
        $api,
        $conversationManager,
        $logger
    ) {
        $chatId = $message->chat->id;
        $userId = $message->from->id;
        
        // Создаем клавиатуру с типами регистрации
        $keyboard = (new InlineKeyboardBuilder())
            ->addCallbackButton('👤 Обычный пользователь', 'reg:user')
            ->row()
            ->addCallbackButton('👨‍💼 Администратор', 'reg:admin')
            ->row()
            ->addCallbackButton('🔧 Модератор', 'reg:moderator')
            ->row()
            ->addCallbackButton('❌ Отмена', 'reg:cancel')
            ->build();
        
        $sentMessage = $api->sendMessage(
            $chatId,
            "📝 *Регистрация нового пользователя*\n\n" .
            "Выберите тип пользователя:",
            [
                'parse_mode' => TelegramAPI::PARSE_MODE_MARKDOWN,
                'reply_markup' => $keyboard,
            ]
        );
        
        // Начинаем диалог и сохраняем ID сообщения для последующего удаления
        $conversationManager->startConversation(
            $chatId,
            $userId,
            'awaiting_type_selection',
            [],
            $sentMessage->messageId
        );
        
        $logger->info('Начат диалог регистрации', [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'message_id' => $sentMessage->messageId,
        ]);
    });
    
    // ===========================
    // ОБРАБОТКА ВЫБОРА ТИПА ПОЛЬЗОВАТЕЛЯ
    // ===========================
    $callbackHandler->handleAction($update, 'reg', function ($query, $params) use (
        $api,
        $callbackHandler,
        $conversationManager,
        $logger
    ) {
        $chatId = $query->message->chat->id;
        $userId = $query->from->id;
        $action = $params[0] ?? null;
        
        // Получаем текущий диалог
        $conversation = $conversationManager->getConversation($chatId, $userId);
        
        if (!$conversation) {
            $callbackHandler->answerWithText($query, '❌ Диалог не найден. Начните заново с /adduser');
            return;
        }
        
        // Обработка отмены
        if ($action === 'cancel') {
            // Удаляем сообщение с кнопками
            if ($conversation['message_id']) {
                try {
                    $api->deleteMessage($chatId, $conversation['message_id']);
                } catch (\Exception $e) {
                    $logger->warning('Не удалось удалить сообщение', [
                        'message_id' => $conversation['message_id'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            $conversationManager->endConversation($chatId, $userId);
            $callbackHandler->answerWithText($query, '❌ Регистрация отменена');
            
            $api->sendMessage($chatId, "Регистрация отменена.");
            return;
        }
        
        // Проверяем состояние диалога
        if ($conversation['state'] === 'awaiting_type_selection') {
            $typeLabels = [
                'user' => '👤 Обычный пользователь',
                'admin' => '👨‍💼 Администратор',
                'moderator' => '🔧 Модератор',
            ];
            
            $selectedType = $action;
            $typeLabel = $typeLabels[$selectedType] ?? 'Неизвестный тип';
            
            // Удаляем старое сообщение с кнопками
            if ($conversation['message_id']) {
                try {
                    $api->deleteMessage($chatId, $conversation['message_id']);
                } catch (\Exception $e) {
                    $logger->warning('Не удалось удалить сообщение', [
                        'message_id' => $conversation['message_id'],
                    ]);
                }
            }
            
            // Отвечаем на callback
            $callbackHandler->answerWithText($query, "✅ Выбран тип: $typeLabel");
            
            // Отправляем новое сообщение
            $api->sendMessage(
                $chatId,
                "✅ Выбран тип: *$typeLabel*\n\n" .
                "📝 Теперь введите *имя* пользователя:",
                ['parse_mode' => TelegramAPI::PARSE_MODE_MARKDOWN]
            );
            
            // Обновляем состояние диалога
            $conversationManager->updateConversation(
                $chatId,
                $userId,
                'awaiting_name',
                ['type' => $selectedType, 'type_label' => $typeLabel],
                null
            );
            
            $logger->info('Тип пользователя выбран', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'type' => $selectedType,
            ]);
        }
    });
    
    // ===========================
    // ОБРАБОТКА ВВОДА ИМЕНИ
    // ===========================
    $textHandler->handlePlainText($update, function ($message, $text) use (
        $api,
        $conversationManager,
        $logger
    ) {
        $chatId = $message->chat->id;
        $userId = $message->from->id;
        
        // Получаем текущий диалог
        $conversation = $conversationManager->getConversation($chatId, $userId);
        
        if (!$conversation) {
            // Нет активного диалога - обычная обработка
            return;
        }
        
        // Обработка состояния "ожидание имени"
        if ($conversation['state'] === 'awaiting_name') {
            $name = trim($text);
            
            if (empty($name)) {
                $api->sendMessage($chatId, "❌ Имя не может быть пустым. Попробуйте еще раз:");
                return;
            }
            
            // Сохраняем имя и переходим к следующему шагу
            $api->sendMessage(
                $chatId,
                "✅ Имя сохранено: *$name*\n\n" .
                "📧 Теперь введите *email* пользователя:",
                ['parse_mode' => TelegramAPI::PARSE_MODE_MARKDOWN]
            );
            
            $conversationManager->updateConversation(
                $chatId,
                $userId,
                'awaiting_email',
                ['name' => $name]
            );
            
            $logger->info('Имя пользователя введено', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'name' => $name,
            ]);
            
            return;
        }
        
        // Обработка состояния "ожидание email"
        if ($conversation['state'] === 'awaiting_email') {
            $email = trim($text);
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $api->sendMessage($chatId, "❌ Неверный формат email. Попробуйте еще раз:");
                return;
            }
            
            // Создаем клавиатуру подтверждения
            $keyboard = (new InlineKeyboardBuilder())
                ->addCallbackButton('✅ Подтвердить', 'confirm:yes')
                ->addCallbackButton('❌ Отменить', 'confirm:no')
                ->build();
            
            $data = $conversation['data'];
            $summaryText = "📋 *Проверьте введенные данные:*\n\n" .
                "Тип: {$data['type_label']}\n" .
                "Имя: {$data['name']}\n" .
                "Email: $email\n\n" .
                "Все верно?";
            
            $sentMessage = $api->sendMessage(
                $chatId,
                $summaryText,
                [
                    'parse_mode' => TelegramAPI::PARSE_MODE_MARKDOWN,
                    'reply_markup' => $keyboard,
                ]
            );
            
            // Обновляем состояние с новым ID сообщения
            $conversationManager->updateConversation(
                $chatId,
                $userId,
                'awaiting_confirmation',
                ['email' => $email],
                $sentMessage->messageId
            );
            
            $logger->info('Email введен, ожидание подтверждения', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'email' => $email,
            ]);
            
            return;
        }
    });
    
    // ===========================
    // ОБРАБОТКА ПОДТВЕРЖДЕНИЯ
    // ===========================
    $callbackHandler->handleAction($update, 'confirm', function ($query, $params) use (
        $api,
        $callbackHandler,
        $conversationManager,
        $logger,
        $db
    ) {
        $chatId = $query->message->chat->id;
        $userId = $query->from->id;
        $action = $params[0] ?? null;
        
        // Получаем текущий диалог
        $conversation = $conversationManager->getConversation($chatId, $userId);
        
        if (!$conversation || $conversation['state'] !== 'awaiting_confirmation') {
            $callbackHandler->answerWithText($query, '❌ Диалог не найден или устарел');
            return;
        }
        
        // Удаляем сообщение с кнопками
        if ($conversation['message_id']) {
            try {
                $api->deleteMessage($chatId, $conversation['message_id']);
            } catch (\Exception $e) {
                $logger->warning('Не удалось удалить сообщение', [
                    'message_id' => $conversation['message_id'],
                ]);
            }
        }
        
        if ($action === 'yes') {
            $data = $conversation['data'];
            
            // Здесь можно сохранить данные в основную таблицу пользователей
            // Пример: сохранение в отдельную таблицу
            try {
                // Проверяем существование таблицы для примера
                $tableExists = $db->querySingle("SHOW TABLES LIKE 'app_users'");
                
                if ($tableExists) {
                    $db->insert('app_users', [
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'type' => $data['type'],
                        'telegram_user_id' => $userId,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            } catch (\Exception $e) {
                $logger->warning('Не удалось сохранить в app_users (таблица может не существовать)', [
                    'error' => $e->getMessage(),
                ]);
            }
            
            $callbackHandler->answerWithText($query, '✅ Пользователь зарегистрирован!');
            
            $api->sendMessage(
                $chatId,
                "✅ *Пользователь успешно зарегистрирован!*\n\n" .
                "Тип: {$data['type_label']}\n" .
                "Имя: {$data['name']}\n" .
                "Email: {$data['email']}",
                ['parse_mode' => TelegramAPI::PARSE_MODE_MARKDOWN]
            );
            
            $logger->info('Пользователь зарегистрирован', [
                'chat_id' => $chatId,
                'data' => $data,
            ]);
        } else {
            $callbackHandler->answerWithText($query, '❌ Регистрация отменена');
            
            $api->sendMessage($chatId, "❌ Регистрация отменена. Начните заново с /adduser");
            
            $logger->info('Регистрация отменена пользователем', [
                'chat_id' => $chatId,
                'user_id' => $userId,
            ]);
        }
        
        // Завершаем диалог
        $conversationManager->endConversation($chatId, $userId);
    });
    
    // ===========================
    // КОМАНДА /start
    // ===========================
    $textHandler->handleCommand($update, 'start', function ($message) use ($api) {
        $api->sendMessage(
            $message->chat->id,
            "👋 Привет! Я бот с системой диалогов.\n\n" .
            "Доступные команды:\n" .
            "/start - Начать работу\n" .
            "/adduser - Зарегистрировать пользователя\n" .
            "/stats - Статистика диалогов\n" .
            "/help - Помощь"
        );
    });
    
    // ===========================
    // КОМАНДА /stats
    // ===========================
    $textHandler->handleCommand($update, 'stats', function ($message) use (
        $api,
        $conversationManager
    ) {
        if (!$conversationManager->isEnabled()) {
            $api->sendMessage($message->chat->id, "❌ Менеджер диалогов отключен");
            return;
        }
        
        $stats = $conversationManager->getStatistics();
        
        $text = "📊 *Статистика диалогов*\n\n";
        $text .= "Активных диалогов: {$stats['total']}\n\n";
        
        if (!empty($stats['by_state'])) {
            $text .= "*По состояниям:*\n";
            foreach ($stats['by_state'] as $state => $count) {
                $text .= "• $state: $count\n";
            }
        }
        
        $api->sendMessage(
            $message->chat->id,
            $text,
            ['parse_mode' => TelegramAPI::PARSE_MODE_MARKDOWN]
        );
    });
    
    // Отправка ответа webhook
    $webhookHandler->sendResponse();
    
    // Очистка устаревших диалогов (с вероятностью 5%)
    if ($conversationManager->isEnabled() && rand(1, 20) === 1) {
        $deleted = $conversationManager->cleanupExpiredConversations();
        if ($deleted > 0) {
            $logger->info("Очищено устаревших диалогов: $deleted");
        }
    }
    
} catch (\Exception $e) {
    $logger->error('Ошибка обработки webhook', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    
    $webhookHandler->sendResponse();
}

$logger->info('=== Обработка завершена ===');

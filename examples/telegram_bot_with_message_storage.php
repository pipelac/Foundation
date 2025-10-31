<?php

declare(strict_types=1);

/**
 * Пример использования TelegramBot с системой хранения сообщений в БД
 * 
 * Демонстрирует:
 * - Подключение MessageStorage к TelegramAPI
 * - Автоматическое сохранение исходящих сообщений
 * - Сохранение входящих сообщений
 * - Получение статистики
 * - Настройку уровней хранения
 */

require_once __DIR__ . '/../autoload.php';

use App\Config\ConfigLoader;
use App\Component\Http;
use App\Component\Logger;
use App\Component\MySQLConnectionFactory;
use App\Component\TelegramBot\Core\MessageStorage;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Core\WebhookHandler;
use App\Component\TelegramBot\Handlers\MessageHandler;
use App\Component\TelegramBot\Handlers\TextHandler;

// Загрузка конфигурации
$configDir = __DIR__ . '/../config';

$mysqlConfig = ConfigLoader::load($configDir . '/mysql.json');
$storageConfig = ConfigLoader::load($configDir . '/telegram_bot_message_storage.json');
$telegramConfig = ConfigLoader::load($configDir . '/telegram.json');

// Инициализация логгера
$logger = new Logger([
    'directory' => __DIR__ . '/../logs',
    'fileName' => 'telegram_bot_with_storage.log',
    'maxFiles' => 7,
]);

$logger->info('=== Запуск бота с хранилищем сообщений ===');

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
    $logger->error('Ошибка подключения к БД', [
        'error' => $e->getMessage(),
    ]);
    die('Ошибка подключения к БД: ' . $e->getMessage());
}

// Создание хранилища сообщений
$messageStorage = new MessageStorage(
    $db,
    $logger,
    $storageConfig['message_storage']
);

if ($messageStorage->isEnabled()) {
    $logger->info('MessageStorage активирован', [
        'level' => $storageConfig['message_storage']['storage_level'] ?? 'standard',
    ]);
} else {
    $logger->warning('MessageStorage отключен в конфигурации');
}

// Создание TelegramAPI с хранилищем
$api = new TelegramAPI(
    $telegramConfig['token'],
    $http,
    $logger,
    $messageStorage  // ← Передаем хранилище
);

$logger->info('TelegramAPI инициализирован');

// Проверка, что это webhook запрос
if (!WebhookHandler::isValidWebhookRequest()) {
    $logger->warning('Получен не-webhook запрос');
    
    // Демонстрация работы в режиме CLI
    echo "=== ДЕМОНСТРАЦИЯ MessageStorage ===\n\n";
    
    // Получение информации о боте (этот метод можно исключить из сохранения)
    try {
        $botInfo = $api->getMe();
        echo "Бот: @{$botInfo->username}\n";
        echo "ID: {$botInfo->id}\n\n";
    } catch (\Exception $e) {
        echo "Ошибка: " . $e->getMessage() . "\n";
    }
    
    // Получение статистики
    if ($messageStorage->isEnabled()) {
        echo "=== СТАТИСТИКА СООБЩЕНИЙ ===\n\n";
        
        $stats = $messageStorage->getStatistics();
        echo "Всего сообщений: {$stats['total']}\n";
        echo "  Входящих: {$stats['incoming']}\n";
        echo "  Исходящих: {$stats['outgoing']}\n";
        echo "  Успешных: {$stats['success']}\n";
        echo "  Неудачных: {$stats['failed']}\n\n";
        
        if (!empty($stats['by_type'])) {
            echo "По типам:\n";
            foreach ($stats['by_type'] as $type => $count) {
                echo "  $type: $count\n";
            }
        }
        
        echo "\n";
    }
    
    exit(0);
}

// Обработка webhook
$webhookHandler = new WebhookHandler($logger);

// Инициализация обработчиков
$messageHandler = new MessageHandler($api, $logger);
$textHandler = new TextHandler($api, $logger);

try {
    // Получение обновления
    $update = $webhookHandler->getUpdate();
    
    // === СОХРАНЕНИЕ ВХОДЯЩИХ СООБЩЕНИЙ ===
    if ($update->message) {
        // Сохраняем входящее сообщение
        if ($messageStorage->isEnabled()) {
            $storageId = $messageStorage->storeIncoming($update->message);
            
            if ($storageId) {
                $logger->info('Входящее сообщение сохранено в БД', [
                    'storage_id' => $storageId,
                    'message_id' => $update->message->messageId,
                    'chat_id' => $update->message->chat->id,
                ]);
            }
        }
    }
    
    // === ОБРАБОТКА КОМАНД ===
    
    // Команда /start
    $textHandler->handleCommand($update, 'start', function ($message) use ($api, $messageStorage) {
        // Исходящее сообщение сохраняется автоматически
        $response = $api->sendMessage(
            $message->chat->id,
            "👋 Привет! Я бот с системой хранения сообщений.\n\n" .
            "Статус хранилища: " . ($messageStorage->isEnabled() ? "✅ Активно" : "❌ Отключено") . "\n\n" .
            "Доступные команды:\n" .
            "/start - Начать работу\n" .
            "/stats - Статистика сообщений\n" .
            "/info - Информация о боте"
        );
        
        // Сообщение уже сохранено автоматически в БД
    });
    
    // Команда /stats
    $textHandler->handleCommand($update, 'stats', function ($message) use ($api, $messageStorage) {
        if (!$messageStorage->isEnabled()) {
            $api->sendMessage(
                $message->chat->id,
                "❌ Хранилище сообщений отключено в конфигурации"
            );
            return;
        }
        
        // Получаем общую статистику
        $stats = $messageStorage->getStatistics();
        
        // Получаем статистику для текущего чата
        $chatStats = $messageStorage->getStatistics($message->chat->id);
        
        $text = "📊 *Статистика сообщений*\n\n";
        
        $text .= "*Общая:*\n";
        $text .= "Всего: {$stats['total']}\n";
        $text .= "Входящих: {$stats['incoming']}\n";
        $text .= "Исходящих: {$stats['outgoing']}\n";
        $text .= "Успешных: {$stats['success']}\n";
        $text .= "Неудачных: {$stats['failed']}\n\n";
        
        $text .= "*Этот чат:*\n";
        $text .= "Всего: {$chatStats['total']}\n";
        $text .= "Входящих: {$chatStats['incoming']}\n";
        $text .= "Исходящих: {$chatStats['outgoing']}\n\n";
        
        if (!empty($chatStats['by_type'])) {
            $text .= "*По типам:*\n";
            foreach ($chatStats['by_type'] as $type => $count) {
                $text .= "• $type: $count\n";
            }
        }
        
        $api->sendMessage(
            $message->chat->id,
            $text,
            ['parse_mode' => TelegramAPI::PARSE_MODE_MARKDOWN]
        );
    });
    
    // Команда /info
    $textHandler->handleCommand($update, 'info', function ($message) use ($api, $messageStorage, $storageConfig) {
        $text = "ℹ️ *Информация о системе хранения*\n\n";
        
        $text .= "Статус: " . ($messageStorage->isEnabled() ? "✅ Активно" : "❌ Отключено") . "\n";
        
        if ($messageStorage->isEnabled()) {
            $level = $storageConfig['message_storage']['storage_level'] ?? 'standard';
            $text .= "Уровень: `$level`\n";
            
            $text .= "Входящие: " . 
                ($storageConfig['message_storage']['store_incoming'] ?? true ? "✅" : "❌") . "\n";
            $text .= "Исходящие: " . 
                ($storageConfig['message_storage']['store_outgoing'] ?? true ? "✅" : "❌") . "\n";
            
            $retentionDays = $storageConfig['message_storage']['retention_days'] ?? 0;
            $text .= "Хранение: " . ($retentionDays > 0 ? "$retentionDays дней" : "∞ бесконечно") . "\n\n";
            
            $text .= "*Уровни детализации:*\n";
            $text .= "• `minimal` - базовая статистика\n";
            $text .= "• `standard` - текст и файлы\n";
            $text .= "• `extended` - медиа метаданные\n";
            $text .= "• `full` - полные данные API\n";
        }
        
        $api->sendMessage(
            $message->chat->id,
            $text,
            ['parse_mode' => TelegramAPI::PARSE_MODE_MARKDOWN]
        );
    });
    
    // Обработка обычного текста
    $textHandler->handlePlainText($update, function ($message, $text) use ($api, $messageStorage) {
        $response = "Получено: " . mb_substr($text, 0, 50);
        
        if ($messageStorage->isEnabled()) {
            $response .= "\n\n✅ Сообщение сохранено в БД";
        }
        
        $api->sendMessage($message->chat->id, $response);
    });
    
    // Отправка ответа webhook
    $webhookHandler->sendResponse();
    
} catch (\Exception $e) {
    $logger->error('Ошибка обработки webhook', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    
    $webhookHandler->sendResponse();
}

// Очистка старых записей (опционально, обычно выполняется через cron)
if ($messageStorage->isEnabled()) {
    $retentionDays = $storageConfig['message_storage']['retention_days'] ?? 0;
    
    if ($retentionDays > 0 && rand(1, 100) === 1) {
        // Выполняем очистку с вероятностью 1%
        $deleted = $messageStorage->cleanupOldMessages();
        
        if ($deleted > 0) {
            $logger->info("Очищено старых записей: $deleted");
        }
    }
}

$logger->info('=== Обработка завершена ===');

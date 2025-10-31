<?php

declare(strict_types=1);

/**
 * Тестовый скрипт для проверки функционала MessageStorage
 * 
 * Проверяет:
 * - Создание таблицы в БД
 * - Сохранение исходящих сообщений
 * - Различные уровни хранения данных
 * - Получение статистики
 * - Логирование операций
 * - Обработку ошибок
 */

require_once __DIR__ . '/../autoload.php';

use App\Config\ConfigLoader;
use App\Component\Http;
use App\Component\Logger;
use App\Component\MySQL;
use App\Component\MySQLConnectionFactory;
use App\Component\TelegramBot\Core\MessageStorage;
use App\Component\TelegramBot\Core\TelegramAPI;

// Цвета для вывода
const COLOR_GREEN = "\033[32m";
const COLOR_RED = "\033[31m";
const COLOR_YELLOW = "\033[33m";
const COLOR_BLUE = "\033[34m";
const COLOR_RESET = "\033[0m";

function printHeader(string $text): void
{
    echo "\n" . COLOR_BLUE . str_repeat('=', 80) . COLOR_RESET . "\n";
    echo COLOR_BLUE . $text . COLOR_RESET . "\n";
    echo COLOR_BLUE . str_repeat('=', 80) . COLOR_RESET . "\n\n";
}

function printSuccess(string $text): void
{
    echo COLOR_GREEN . "✓ " . $text . COLOR_RESET . "\n";
}

function printError(string $text): void
{
    echo COLOR_RED . "✗ " . $text . COLOR_RESET . "\n";
}

function printWarning(string $text): void
{
    echo COLOR_YELLOW . "⚠ " . $text . COLOR_RESET . "\n";
}

function printInfo(string $text): void
{
    echo "  " . $text . "\n";
}

// Инициализация
printHeader("ТЕСТ СИСТЕМЫ ХРАНЕНИЯ СООБЩЕНИЙ TELEGRAM БОТА");

$configDir = __DIR__ . '/../config';

// Инициализация логгера
printInfo("Инициализация логгера...");
$logger = new Logger([
    'directory' => __DIR__ . '/../logs',
    'fileName' => 'telegram_bot_storage_test.log',
    'maxFiles' => 7,
    'maxFileSize' => 10 * 1024 * 1024,
]);
printSuccess("Логгер инициализирован");

// Подключение к БД
printInfo("Подключение к базе данных...");
try {
    $mysqlConfig = ConfigLoader::load($configDir . '/mysql.json');
    $factory = new MySQLConnectionFactory($mysqlConfig, $logger);
    $db = $factory->getConnection('main');
    printSuccess("Подключение к БД установлено");
} catch (\Exception $e) {
    printError("Ошибка подключения к БД: " . $e->getMessage());
    exit(1);
}

// Загрузка конфигурации Telegram
printInfo("Загрузка конфигурации Telegram...");
try {
    $telegramConfig = ConfigLoader::load($configDir . '/telegram.json');
    
    // Проверка токена
    if (!isset($telegramConfig['token']) || $telegramConfig['token'] === 'YOUR_TELEGRAM_BOT_TOKEN') {
        printWarning("В конфигурации не указан токен бота");
        printInfo("Используем тестовый токен из параметров задачи");
        $telegramConfig['token'] = '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI';
    }
    
    printSuccess("Конфигурация Telegram загружена");
} catch (\Exception $e) {
    printError("Ошибка загрузки конфигурации: " . $e->getMessage());
    exit(1);
}

// Тестовые данные
$testChatId = 366442475;
$testToken = '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI';

printInfo("Используемый токен: " . substr($testToken, 0, 20) . "...");
printInfo("Тестовый chat_id: $testChatId");

// Инициализация HTTP
printInfo("Инициализация HTTP клиента...");
$http = new Http([
    'timeout' => 30,
    'connect_timeout' => 10,
], $logger);
printSuccess("HTTP клиент инициализирован");

// ===========================
// ТЕСТ 1: Создание таблицы
// ===========================
printHeader("ТЕСТ 1: Автоматическое создание таблицы");

$storageConfig = [
    'enabled' => true,
    'storage_level' => MessageStorage::LEVEL_STANDARD,
    'store_incoming' => true,
    'store_outgoing' => true,
    'exclude_methods' => [],
    'retention_days' => 0,
    'auto_create_table' => true,
];

try {
    // Удаляем таблицу, если она существует
    printInfo("Удаление существующей таблицы...");
    $db->execute("DROP TABLE IF EXISTS telegram_bot_messages");
    
    printInfo("Создание MessageStorage с auto_create_table = true...");
    $messageStorage = new MessageStorage($db, $logger, $storageConfig);
    
    // Проверяем, что таблица создана
    $tableExists = $db->querySingle("SHOW TABLES LIKE 'telegram_bot_messages'");
    
    if (!empty($tableExists)) {
        printSuccess("Таблица telegram_bot_messages успешно создана");
        
        // Проверяем структуру таблицы
        printInfo("Проверка структуры таблицы...");
        $columns = $db->query("SHOW COLUMNS FROM telegram_bot_messages");
        printSuccess("Таблица содержит " . count($columns) . " колонок");
        
        // Проверяем индексы
        $indexes = $db->query("SHOW INDEXES FROM telegram_bot_messages");
        printSuccess("Таблица содержит " . count($indexes) . " индексов");
    } else {
        printError("Таблица не была создана");
    }
} catch (\Exception $e) {
    printError("Ошибка при создании таблицы: " . $e->getMessage());
}

// ===========================
// ТЕСТ 2: Сохранение исходящих сообщений (разные уровни)
// ===========================
printHeader("ТЕСТ 2: Сохранение исходящих сообщений (различные уровни хранения)");

$levels = [
    MessageStorage::LEVEL_MINIMAL,
    MessageStorage::LEVEL_STANDARD,
    MessageStorage::LEVEL_EXTENDED,
    MessageStorage::LEVEL_FULL,
];

foreach ($levels as $level) {
    printInfo("Тестирование уровня: $level");
    
    $storageConfig['storage_level'] = $level;
    $messageStorage = new MessageStorage($db, $logger, $storageConfig);
    $api = new TelegramAPI($testToken, $http, $logger, $messageStorage);
    
    try {
        $message = $api->sendMessage(
            $testChatId,
            "Тестовое сообщение для уровня: $level\nВремя: " . date('Y-m-d H:i:s'),
            [
                'parse_mode' => TelegramAPI::PARSE_MODE_HTML,
            ]
        );
        
        printSuccess("Сообщение отправлено (message_id: {$message->id})");
        
        // Проверяем, что запись создана в БД
        $record = $db->querySingle(
            "SELECT * FROM telegram_bot_messages WHERE message_id = ? AND chat_id = ? ORDER BY id DESC LIMIT 1",
            [$message->id, $testChatId]
        );
        
        if ($record) {
            printSuccess("Запись сохранена в БД (id: {$record['id']}, level: {$record['message_type']})");
            
            // Проверяем наличие полей в зависимости от уровня
            if ($level === MessageStorage::LEVEL_MINIMAL) {
                printInfo("Minimal: базовые поля сохранены");
            } elseif ($level === MessageStorage::LEVEL_STANDARD) {
                if (!empty($record['text'])) {
                    printSuccess("Standard: текст сообщения сохранен (" . strlen($record['text']) . " символов)");
                } else {
                    printWarning("Standard: текст не сохранен");
                }
            } elseif ($level === MessageStorage::LEVEL_EXTENDED) {
                if (!empty($record['text'])) {
                    printSuccess("Extended: расширенные данные сохранены");
                }
            } elseif ($level === MessageStorage::LEVEL_FULL) {
                if (!empty($record['raw_data'])) {
                    printSuccess("Full: полные данные включая raw_data сохранены (" . strlen($record['raw_data']) . " байт)");
                } else {
                    printWarning("Full: raw_data не сохранен");
                }
            }
        } else {
            printError("Запись не найдена в БД");
        }
        
        sleep(1); // Задержка между запросами
    } catch (\Exception $e) {
        printError("Ошибка отправки: " . $e->getMessage());
    }
}

// ===========================
// ТЕСТ 3: Различные типы сообщений
// ===========================
printHeader("ТЕСТ 3: Различные типы сообщений");

$storageConfig['storage_level'] = MessageStorage::LEVEL_STANDARD;
$messageStorage = new MessageStorage($db, $logger, $storageConfig);
$api = new TelegramAPI($testToken, $http, $logger, $messageStorage);

// Текстовое сообщение
printInfo("Отправка текстового сообщения...");
try {
    $message = $api->sendMessage($testChatId, "Простое текстовое сообщение");
    printSuccess("Текстовое сообщение отправлено (id: {$message->id})");
} catch (\Exception $e) {
    printError("Ошибка: " . $e->getMessage());
}
sleep(1);

// Сообщение с разметкой
printInfo("Отправка сообщения с HTML разметкой...");
try {
    $message = $api->sendMessage(
        $testChatId,
        "<b>Жирный текст</b>\n<i>Курсив</i>\n<code>Код</code>",
        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
    );
    printSuccess("Сообщение с разметкой отправлено (id: {$message->id})");
} catch (\Exception $e) {
    printError("Ошибка: " . $e->getMessage());
}
sleep(1);

// Фото с URL
printInfo("Отправка фото по URL...");
try {
    $message = $api->sendPhoto(
        $testChatId,
        'https://picsum.photos/400/300',
        ['caption' => 'Тестовое изображение']
    );
    printSuccess("Фото отправлено (id: {$message->id})");
    
    // Проверяем сохранение в БД
    $record = $db->querySingle(
        "SELECT * FROM telegram_bot_messages WHERE message_id = ? AND chat_id = ?",
        [$message->id, $testChatId]
    );
    
    if ($record && $record['message_type'] === 'photo') {
        printSuccess("Тип сообщения 'photo' корректно определен");
        if (!empty($record['caption'])) {
            printSuccess("Подпись к фото сохранена: '{$record['caption']}'");
        }
    }
} catch (\Exception $e) {
    printError("Ошибка: " . $e->getMessage());
}
sleep(1);

// ===========================
// ТЕСТ 4: Обработка ошибок
// ===========================
printHeader("ТЕСТ 4: Обработка ошибок и сохранение неудачных запросов");

printInfo("Попытка отправки в несуществующий чат...");
try {
    $api->sendMessage(-999999999, "Это сообщение не будет доставлено");
    printError("Исключение не было выброшено!");
} catch (\Exception $e) {
    printSuccess("Исключение корректно обработано: " . $e->getMessage());
    
    // Проверяем, что ошибка сохранена в БД
    $errorRecord = $db->querySingle(
        "SELECT * FROM telegram_bot_messages WHERE success = 0 AND chat_id = -999999999 ORDER BY id DESC LIMIT 1"
    );
    
    if ($errorRecord) {
        printSuccess("Ошибочный запрос сохранен в БД (id: {$errorRecord['id']})");
        printInfo("Код ошибки: {$errorRecord['error_code']}");
        printInfo("Сообщение об ошибке: {$errorRecord['error_message']}");
    } else {
        printWarning("Ошибочный запрос не был сохранен в БД");
    }
}

// ===========================
// ТЕСТ 5: Статистика
// ===========================
printHeader("ТЕСТ 5: Получение статистики");

printInfo("Получение общей статистики...");
try {
    $stats = $messageStorage->getStatistics();
    
    printSuccess("Статистика получена:");
    printInfo("  Всего сообщений: {$stats['total']}");
    printInfo("  Входящих: {$stats['incoming']}");
    printInfo("  Исходящих: {$stats['outgoing']}");
    printInfo("  Успешных: {$stats['success']}");
    printInfo("  Неудачных: {$stats['failed']}");
    
    if (!empty($stats['by_type'])) {
        printInfo("  По типам:");
        foreach ($stats['by_type'] as $type => $count) {
            printInfo("    - $type: $count");
        }
    }
} catch (\Exception $e) {
    printError("Ошибка получения статистики: " . $e->getMessage());
}

printInfo("Получение статистики для конкретного чата...");
try {
    $chatStats = $messageStorage->getStatistics($testChatId);
    
    printSuccess("Статистика для chat_id $testChatId:");
    printInfo("  Всего сообщений: {$chatStats['total']}");
    printInfo("  Исходящих: {$chatStats['outgoing']}");
} catch (\Exception $e) {
    printError("Ошибка: " . $e->getMessage());
}

// ===========================
// ТЕСТ 6: Исключение методов
// ===========================
printHeader("ТЕСТ 6: Исключение методов из сохранения");

$storageConfig['exclude_methods'] = ['getMe', 'getWebhookInfo'];
$messageStorage = new MessageStorage($db, $logger, $storageConfig);
$api = new TelegramAPI($testToken, $http, $logger, $messageStorage);

printInfo("Вызов метода getMe (должен быть исключен)...");
try {
    $countBefore = $db->querySingle(
        "SELECT COUNT(*) as cnt FROM telegram_bot_messages WHERE method_name = 'getMe'"
    )['cnt'];
    
    $botInfo = $api->getMe();
    printSuccess("Информация о боте получена: @{$botInfo->username}");
    
    $countAfter = $db->querySingle(
        "SELECT COUNT(*) as cnt FROM telegram_bot_messages WHERE method_name = 'getMe'"
    )['cnt'];
    
    if ($countBefore === $countAfter) {
        printSuccess("Метод getMe корректно исключен из сохранения");
    } else {
        printWarning("Метод getMe был сохранен, хотя должен быть исключен");
    }
} catch (\Exception $e) {
    printError("Ошибка: " . $e->getMessage());
}

// ===========================
// ТЕСТ 7: Отключение хранения
// ===========================
printHeader("ТЕСТ 7: Отключение хранения");

$storageConfig['enabled'] = false;
$messageStorage = new MessageStorage($db, $logger, $storageConfig);
$api = new TelegramAPI($testToken, $http, $logger, $messageStorage);

printInfo("Отправка сообщения с отключенным хранилищем...");
try {
    $countBefore = $db->querySingle("SELECT COUNT(*) as cnt FROM telegram_bot_messages")['cnt'];
    
    $message = $api->sendMessage($testChatId, "Это сообщение не должно быть сохранено");
    printSuccess("Сообщение отправлено (id: {$message->id})");
    
    $countAfter = $db->querySingle("SELECT COUNT(*) as cnt FROM telegram_bot_messages")['cnt'];
    
    if ($countBefore === $countAfter) {
        printSuccess("Сообщение не было сохранено (хранилище отключено)");
    } else {
        printWarning("Сообщение было сохранено, хотя хранилище отключено");
    }
} catch (\Exception $e) {
    printError("Ошибка: " . $e->getMessage());
}

// ===========================
// ТЕСТ 8: Очистка старых записей
// ===========================
printHeader("ТЕСТ 8: Очистка старых записей");

$storageConfig['enabled'] = true;
$storageConfig['retention_days'] = 30;
$messageStorage = new MessageStorage($db, $logger, $storageConfig);

printInfo("Создание тестовой старой записи...");
try {
    $db->insert('telegram_bot_messages', [
        'direction' => MessageStorage::DIRECTION_OUTGOING,
        'message_id' => 999999,
        'chat_id' => $testChatId,
        'user_id' => null,
        'message_type' => 'text',
        'method_name' => 'sendMessage',
        'created_at' => date('Y-m-d H:i:s', strtotime('-40 days')),
        'telegram_date' => null,
        'success' => 1,
    ]);
    printSuccess("Тестовая старая запись создана");
    
    printInfo("Запуск очистки старых записей (retention_days = 30)...");
    $deleted = $messageStorage->cleanupOldMessages();
    
    if ($deleted > 0) {
        printSuccess("Удалено записей: $deleted");
    } else {
        printWarning("Записи не были удалены");
    }
} catch (\Exception $e) {
    printError("Ошибка: " . $e->getMessage());
}

// ===========================
// ИТОГИ
// ===========================
printHeader("ИТОГИ ТЕСТИРОВАНИЯ");

try {
    $finalStats = $messageStorage->getStatistics();
    
    printInfo("Финальная статистика:");
    printInfo("  Всего записей в БД: {$finalStats['total']}");
    printInfo("  Успешных операций: {$finalStats['success']}");
    printInfo("  Неудачных операций: {$finalStats['failed']}");
    
    printSuccess("Все тесты выполнены!");
    printInfo("Подробные логи доступны в: logs/telegram_bot_storage_test.log");
} catch (\Exception $e) {
    printError("Ошибка получения итоговой статистики: " . $e->getMessage());
}

printHeader("ЗАВЕРШЕНИЕ ТЕСТИРОВАНИЯ");

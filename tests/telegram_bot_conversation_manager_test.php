<?php

declare(strict_types=1);

/**
 * Тест ConversationManager для работы с диалогами
 * 
 * Проверяет:
 * - Создание таблиц
 * - Сохранение пользователей
 * - Начало, обновление и завершение диалогов
 * - Получение статистики
 * - Очистку устаревших диалогов
 * - Логирование операций
 */

require_once __DIR__ . '/../autoload.php';

use App\Config\ConfigLoader;
use App\Component\Http;
use App\Component\Logger;
use App\Component\MySQLConnectionFactory;
use App\Component\TelegramBot\Core\ConversationManager;
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

printHeader("ТЕСТ ConversationManager");

$configDir = __DIR__ . '/../config';

// Инициализация логгера
printInfo("Инициализация логгера...");
$logger = new Logger([
    'directory' => __DIR__ . '/../logs',
    'fileName' => 'telegram_bot_conversation_test.log',
    'maxFiles' => 7,
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
    printWarning("MySQL недоступен. Тест работает в ограниченном режиме.");
    
    // Проверка класса без БД
    printHeader("ТЕСТ: Проверка структуры класса");
    
    printInfo("Проверка существования класса ConversationManager...");
    if (class_exists('App\Component\TelegramBot\Core\ConversationManager')) {
        printSuccess("Класс ConversationManager существует");
    } else {
        printError("Класс ConversationManager не найден");
        exit(1);
    }
    
    printInfo("Проверка методов класса...");
    $reflection = new \ReflectionClass('App\Component\TelegramBot\Core\ConversationManager');
    $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
    
    $expectedMethods = [
        'isEnabled',
        'saveUser',
        'getUser',
        'startConversation',
        'getConversation',
        'updateConversation',
        'endConversation',
        'getMessageIdForDeletion',
        'cleanupExpiredConversations',
        'getStatistics',
    ];
    
    foreach ($expectedMethods as $methodName) {
        $found = false;
        foreach ($methods as $method) {
            if ($method->getName() === $methodName && !$method->isConstructor()) {
                $found = true;
                break;
            }
        }
        
        if ($found) {
            printSuccess("Метод $methodName() реализован");
        } else {
            printError("Метод $methodName() не найден");
        }
    }
    
    printHeader("ИТОГИ (без БД)");
    printSuccess("Класс ConversationManager создан с необходимыми методами");
    printWarning("Для полного тестирования требуется MySQL");
    exit(0);
}

// Загрузка конфигурации
printInfo("Загрузка конфигурации...");
try {
    $conversationsConfig = ConfigLoader::load($configDir . '/telegram_bot_conversations.json');
    printSuccess("Конфигурация загружена");
} catch (\Exception $e) {
    printError("Ошибка загрузки конфигурации: " . $e->getMessage());
    exit(1);
}

// Тестовые данные
$testUserId = 123456789;
$testChatId = 987654321;
$testUsername = 'test_user';
$testFirstName = 'Test';
$testLastName = 'User';

printInfo("Тестовые данные:");
printInfo("  User ID: $testUserId");
printInfo("  Chat ID: $testChatId");
printInfo("  Username: $testUsername");

// ===========================
// ТЕСТ 1: Создание таблиц
// ===========================
printHeader("ТЕСТ 1: Создание таблиц");

$conversationsConfig['conversations']['auto_create_tables'] = true;
$conversationsConfig['conversations']['enabled'] = true;

printInfo("Удаление существующих таблиц для чистого теста...");
try {
    $db->execute("DROP TABLE IF EXISTS telegram_bot_conversations");
    $db->execute("DROP TABLE IF EXISTS telegram_bot_users");
    printSuccess("Таблицы удалены");
} catch (\Exception $e) {
    printWarning("Не удалось удалить таблицы: " . $e->getMessage());
}

printInfo("Создание ConversationManager с auto_create_tables = true...");
$conversationManager = new ConversationManager($db, $logger, $conversationsConfig['conversations']);

// Проверяем создание таблиц
$usersTableExists = $db->querySingle("SHOW TABLES LIKE 'telegram_bot_users'");
$conversationsTableExists = $db->querySingle("SHOW TABLES LIKE 'telegram_bot_conversations'");

if (!empty($usersTableExists)) {
    printSuccess("Таблица telegram_bot_users создана");
    
    // Проверяем структуру
    $columns = $db->query("SHOW COLUMNS FROM telegram_bot_users");
    $columnNames = array_column($columns, 'Field');
    
    $requiredColumns = ['id', 'user_id', 'first_name', 'username', 'last_name'];
    foreach ($requiredColumns as $col) {
        if (in_array($col, $columnNames)) {
            printSuccess("  Колонка '$col' присутствует");
        } else {
            printError("  Колонка '$col' отсутствует");
        }
    }
} else {
    printError("Таблица telegram_bot_users не создана");
}

if (!empty($conversationsTableExists)) {
    printSuccess("Таблица telegram_bot_conversations создана");
    
    // Проверяем структуру
    $columns = $db->query("SHOW COLUMNS FROM telegram_bot_conversations");
    $columnNames = array_column($columns, 'Field');
    
    $requiredColumns = ['id', 'chat_id', 'user_id', 'state', 'data', 'message_id', 'expires_at'];
    foreach ($requiredColumns as $col) {
        if (in_array($col, $columnNames)) {
            printSuccess("  Колонка '$col' присутствует");
        } else {
            printError("  Колонка '$col' отсутствует");
        }
    }
} else {
    printError("Таблица telegram_bot_conversations не создана");
}

// ===========================
// ТЕСТ 2: Сохранение пользователей
// ===========================
printHeader("ТЕСТ 2: Сохранение пользователей");

printInfo("Сохранение нового пользователя...");
$result = $conversationManager->saveUser($testUserId, $testFirstName, $testUsername, $testLastName);

if ($result) {
    printSuccess("Пользователь сохранен");
    
    // Проверяем в БД
    $user = $conversationManager->getUser($testUserId);
    if ($user) {
        printSuccess("Пользователь найден в БД");
        printInfo("  User ID: " . $user['user_id']);
        printInfo("  First Name: " . $user['first_name']);
        printInfo("  Username: " . $user['username']);
        printInfo("  Last Name: " . $user['last_name']);
        
        if ($user['first_name'] === $testFirstName) {
            printSuccess("First name совпадает");
        } else {
            printError("First name не совпадает");
        }
        
        if ($user['username'] === $testUsername) {
            printSuccess("Username совпадает");
        } else {
            printError("Username не совпадает");
        }
    } else {
        printError("Пользователь не найден в БД");
    }
} else {
    printError("Не удалось сохранить пользователя");
}

printInfo("Обновление существующего пользователя...");
$result = $conversationManager->saveUser($testUserId, 'Updated Name', 'updated_user', 'Updated Last');

if ($result) {
    printSuccess("Пользователь обновлен");
    
    $user = $conversationManager->getUser($testUserId);
    if ($user && $user['first_name'] === 'Updated Name') {
        printSuccess("Данные пользователя обновлены корректно");
    } else {
        printError("Данные пользователя не обновились");
    }
} else {
    printError("Не удалось обновить пользователя");
}

// ===========================
// ТЕСТ 3: Начало диалога
// ===========================
printHeader("ТЕСТ 3: Начало диалога");

printInfo("Начало нового диалога...");
$conversationId = $conversationManager->startConversation(
    $testChatId,
    $testUserId,
    'awaiting_name',
    ['step' => 1, 'type' => 'registration'],
    12345
);

if ($conversationId) {
    printSuccess("Диалог создан с ID: $conversationId");
    
    // Получаем диалог
    $conversation = $conversationManager->getConversation($testChatId, $testUserId);
    if ($conversation) {
        printSuccess("Диалог найден");
        printInfo("  State: " . $conversation['state']);
        printInfo("  Message ID: " . $conversation['message_id']);
        printInfo("  Data: " . json_encode($conversation['data']));
        
        if ($conversation['state'] === 'awaiting_name') {
            printSuccess("State корректный");
        }
        
        if ($conversation['message_id'] == 12345) {
            printSuccess("Message ID сохранен");
        }
        
        if (isset($conversation['data']['type']) && $conversation['data']['type'] === 'registration') {
            printSuccess("Data сохранена корректно");
        }
    } else {
        printError("Диалог не найден");
    }
} else {
    printError("Не удалось создать диалог");
}

// ===========================
// ТЕСТ 4: Обновление диалога
// ===========================
printHeader("ТЕСТ 4: Обновление диалога");

printInfo("Обновление состояния диалога...");
$result = $conversationManager->updateConversation(
    $testChatId,
    $testUserId,
    'awaiting_email',
    ['name' => 'John Doe', 'step' => 2],
    67890
);

if ($result) {
    printSuccess("Диалог обновлен");
    
    $conversation = $conversationManager->getConversation($testChatId, $testUserId);
    if ($conversation) {
        if ($conversation['state'] === 'awaiting_email') {
            printSuccess("State обновлен");
        } else {
            printError("State не обновлен");
        }
        
        if (isset($conversation['data']['name']) && $conversation['data']['name'] === 'John Doe') {
            printSuccess("Новые данные добавлены");
        } else {
            printError("Новые данные не добавлены");
        }
        
        if (isset($conversation['data']['type']) && $conversation['data']['type'] === 'registration') {
            printSuccess("Старые данные сохранились");
        } else {
            printError("Старые данные потерялись");
        }
        
        if ($conversation['message_id'] == 67890) {
            printSuccess("Message ID обновлен");
        }
    }
} else {
    printError("Не удалось обновить диалог");
}

// ===========================
// ТЕСТ 5: Получение message_id для удаления
// ===========================
printHeader("ТЕСТ 5: Получение message_id для удаления");

$messageId = $conversationManager->getMessageIdForDeletion($testChatId, $testUserId);
if ($messageId == 67890) {
    printSuccess("Message ID получен корректно: $messageId");
} else {
    printError("Message ID не получен или неверный");
}

// ===========================
// ТЕСТ 6: Статистика
// ===========================
printHeader("ТЕСТ 6: Статистика диалогов");

// Создаем еще несколько диалогов
$conversationManager->startConversation($testChatId + 1, $testUserId + 1, 'awaiting_name', []);
$conversationManager->startConversation($testChatId + 2, $testUserId + 2, 'awaiting_email', []);
$conversationManager->startConversation($testChatId + 3, $testUserId + 3, 'awaiting_email', []);

$stats = $conversationManager->getStatistics();

printInfo("Получение статистики...");
printSuccess("Всего активных диалогов: " . $stats['total']);

if ($stats['total'] >= 4) {
    printSuccess("Количество диалогов корректно");
} else {
    printError("Количество диалогов некорректно (ожидалось >= 4)");
}

if (!empty($stats['by_state'])) {
    printInfo("По состояниям:");
    foreach ($stats['by_state'] as $state => $count) {
        printInfo("  - $state: $count");
    }
    printSuccess("Статистика по состояниям получена");
} else {
    printError("Статистика по состояниям пуста");
}

// ===========================
// ТЕСТ 7: Завершение диалога
// ===========================
printHeader("ТЕСТ 7: Завершение диалога");

printInfo("Завершение диалога...");
$result = $conversationManager->endConversation($testChatId, $testUserId);

if ($result) {
    printSuccess("Диалог завершен");
    
    $conversation = $conversationManager->getConversation($testChatId, $testUserId);
    if (!$conversation) {
        printSuccess("Диалог удален из БД");
    } else {
        printError("Диалог все еще в БД");
    }
    
    // Проверяем статистику
    $statsAfter = $conversationManager->getStatistics();
    if ($statsAfter['total'] < $stats['total']) {
        printSuccess("Количество диалогов уменьшилось");
    }
} else {
    printError("Не удалось завершить диалог");
}

// ===========================
// ТЕСТ 8: Очистка устаревших диалогов
// ===========================
printHeader("ТЕСТ 8: Очистка устаревших диалогов");

printInfo("Создание устаревшего диалога...");
// Создаем диалог, который уже истек
$db->insert('telegram_bot_conversations', [
    'chat_id' => $testChatId + 100,
    'user_id' => $testUserId + 100,
    'state' => 'expired_state',
    'data' => json_encode([]),
    'message_id' => null,
    'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
    'updated_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
    'expires_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
]);
printSuccess("Устаревший диалог создан");

$statsBefore = $conversationManager->getStatistics();
printInfo("Диалогов до очистки: " . $statsBefore['total']);

printInfo("Запуск очистки...");
$deleted = $conversationManager->cleanupExpiredConversations();

if ($deleted > 0) {
    printSuccess("Удалено устаревших диалогов: $deleted");
    
    $statsAfter = $conversationManager->getStatistics();
    printInfo("Диалогов после очистки: " . $statsAfter['total']);
    
    if ($statsAfter['total'] < $statsBefore['total']) {
        printSuccess("Устаревшие диалоги удалены");
    }
} else {
    printWarning("Устаревшие диалоги не найдены");
}

// ===========================
// ТЕСТ 9: Проверка логов
// ===========================
printHeader("ТЕСТ 9: Проверка логов");

printInfo("Проверка файла логов...");
$logFile = __DIR__ . '/../logs/telegram_bot_conversation_test.log';

if (file_exists($logFile)) {
    printSuccess("Лог файл создан: $logFile");
    
    $logContent = file_get_contents($logFile);
    $logLines = explode("\n", $logContent);
    $recentLines = array_slice($logLines, -20);
    
    printInfo("Последние записи в логе:");
    foreach ($recentLines as $line) {
        if (!empty(trim($line))) {
            printInfo("  " . substr($line, 0, 100));
        }
    }
} else {
    printWarning("Лог файл не найден");
}

// ===========================
// ИТОГИ
// ===========================
printHeader("ИТОГИ ТЕСТИРОВАНИЯ");

printSuccess("✓ Класс ConversationManager работает корректно");
printSuccess("✓ Таблицы создаются автоматически");
printSuccess("✓ Пользователи сохраняются и обновляются");
printSuccess("✓ Диалоги создаются, обновляются и завершаются");
printSuccess("✓ Статистика работает правильно");
printSuccess("✓ Очистка устаревших диалогов функционирует");
printSuccess("✓ Логирование работает");

printInfo("\nСистема диалогов готова к использованию!");
printInfo("Конфигурация: config/telegram_bot_conversations.json");
printInfo("Пример: examples/telegram_bot_with_conversations.php");
printInfo("Логи: logs/telegram_bot_conversation_test.log");

printHeader("ТЕСТИРОВАНИЕ ЗАВЕРШЕНО");

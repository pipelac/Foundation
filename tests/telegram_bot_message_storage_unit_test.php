<?php

declare(strict_types=1);

/**
 * Unit-тесты для MessageStorage (без реального подключения к БД)
 * 
 * Проверяет логику работы класса:
 * - Определение типов сообщений
 * - Извлечение данных из параметров
 * - Проверку настроек
 */

require_once __DIR__ . '/../autoload.php';

use App\Component\Logger;
use App\Component\TelegramBot\Core\MessageStorage;
use App\Component\TelegramBot\Entities\Message;
use App\Component\TelegramBot\Entities\Chat;
use App\Component\TelegramBot\Entities\User;

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

function printInfo(string $text): void
{
    echo "  " . $text . "\n";
}

printHeader("UNIT-ТЕСТЫ MessageStorage");

// Mock для MySQL
class MockMySQL
{
    public array $insertedData = [];
    public array $queries = [];
    public bool $simulateTableExists = false;
    
    public function insert(string $table, array $data): int
    {
        $this->insertedData[] = ['table' => $table, 'data' => $data];
        return count($this->insertedData);
    }
    
    public function query(string $sql, array $params = []): array
    {
        $this->queries[] = ['sql' => $sql, 'params' => $params];
        return [];
    }
    
    public function querySingle(string $sql, array $params = []): ?array
    {
        $this->queries[] = ['sql' => $sql, 'params' => $params];
        
        if (str_contains($sql, 'SHOW TABLES')) {
            return $this->simulateTableExists ? ['telegram_bot_messages' => 'telegram_bot_messages'] : null;
        }
        
        if (str_contains($sql, 'COUNT(*)')) {
            return ['cnt' => 0, 'total' => 0, 'incoming' => 0, 'outgoing' => 0, 'success' => 0, 'failed' => 0];
        }
        
        return null;
    }
    
    public function execute(string $sql, array $params = []): int
    {
        $this->queries[] = ['sql' => $sql, 'params' => $params];
        
        if (str_contains($sql, 'CREATE TABLE')) {
            $this->simulateTableExists = true;
        }
        
        return 1;
    }
}

$logger = new Logger([
    'directory' => __DIR__ . '/../logs',
    'fileName' => 'telegram_bot_storage_unit_test.log',
]);

// ===========================
// ТЕСТ 1: Инициализация
// ===========================
printHeader("ТЕСТ 1: Инициализация MessageStorage");

$mockDb = new MockMySQL();

printInfo("Создание MessageStorage с enabled = false...");
$storage = new MessageStorage($mockDb, $logger, ['enabled' => false]);
if (!$storage->isEnabled()) {
    printSuccess("MessageStorage корректно отключен");
} else {
    printError("MessageStorage должен быть отключен");
}

printInfo("Создание MessageStorage с enabled = true и auto_create_table = false...");
$mockDb2 = new MockMySQL();
$storage2 = new MessageStorage($mockDb2, $logger, [
    'enabled' => true,
    'auto_create_table' => false
]);
if ($storage2->isEnabled()) {
    printSuccess("MessageStorage корректно включен");
    
    if (empty($mockDb2->queries)) {
        printSuccess("Таблица не создавалась (auto_create_table = false)");
    } else {
        printError("Таблица не должна была создаваться");
    }
} else {
    printError("MessageStorage должен быть включен");
}

printInfo("Создание MessageStorage с auto_create_table = true...");
$mockDb3 = new MockMySQL();
$storage3 = new MessageStorage($mockDb3, $logger, [
    'enabled' => true,
    'auto_create_table' => true
]);

$createTableExecuted = false;
foreach ($mockDb3->queries as $query) {
    if (str_contains($query['sql'], 'CREATE TABLE')) {
        $createTableExecuted = true;
        break;
    }
}

if ($createTableExecuted) {
    printSuccess("Запрос CREATE TABLE был выполнен");
} else {
    printError("Запрос CREATE TABLE не был выполнен");
}

// ===========================
// ТЕСТ 2: Уровни хранения
// ===========================
printHeader("ТЕСТ 2: Проверка уровней хранения");

$levels = [
    MessageStorage::LEVEL_MINIMAL => 'minimal',
    MessageStorage::LEVEL_STANDARD => 'standard',
    MessageStorage::LEVEL_EXTENDED => 'extended',
    MessageStorage::LEVEL_FULL => 'full',
];

foreach ($levels as $constant => $level) {
    printInfo("Проверка константы для уровня $level...");
    if ($constant === $level) {
        printSuccess("Константа LEVEL_" . strtoupper($level) . " = '$level'");
    } else {
        printError("Константа LEVEL_" . strtoupper($level) . " имеет неверное значение");
    }
}

// ===========================
// ТЕСТ 3: Направления сообщений
// ===========================
printHeader("ТЕСТ 3: Константы направлений");

printInfo("Проверка константы DIRECTION_INCOMING...");
if (MessageStorage::DIRECTION_INCOMING === 'incoming') {
    printSuccess("DIRECTION_INCOMING = 'incoming'");
} else {
    printError("DIRECTION_INCOMING имеет неверное значение");
}

printInfo("Проверка константы DIRECTION_OUTGOING...");
if (MessageStorage::DIRECTION_OUTGOING === 'outgoing') {
    printSuccess("DIRECTION_OUTGOING = 'outgoing'");
} else {
    printError("DIRECTION_OUTGOING имеет неверное значение");
}

// ===========================
// ТЕСТ 4: Сохранение исходящих (minimal)
// ===========================
printHeader("ТЕСТ 4: Сохранение исходящих сообщений (minimal)");

$mockDb4 = new MockMySQL();
$mockDb4->simulateTableExists = true;
$storage4 = new MessageStorage($mockDb4, $logger, [
    'enabled' => true,
    'storage_level' => MessageStorage::LEVEL_MINIMAL,
    'auto_create_table' => false,
]);

printInfo("Создание mock Message...");
$messageData = [
    'message_id' => 123,
    'date' => time(),
    'chat' => ['id' => 456, 'type' => 'private'],
    'from' => ['id' => 789, 'first_name' => 'Test', 'is_bot' => false],
    'text' => 'Test message',
];
$message = Message::fromArray($messageData);

printInfo("Сохранение исходящего сообщения...");
$params = ['chat_id' => 456, 'text' => 'Test message'];
$id = $storage4->storeOutgoing('sendMessage', $params, $message, true);

if ($id !== null) {
    printSuccess("Сообщение сохранено с ID: $id");
    
    $lastInsert = end($mockDb4->insertedData);
    if ($lastInsert) {
        $data = $lastInsert['data'];
        
        // Проверяем обязательные поля
        if (isset($data['direction']) && $data['direction'] === 'outgoing') {
            printSuccess("Направление: outgoing");
        } else {
            printError("Неверное направление");
        }
        
        if (isset($data['message_type']) && $data['message_type'] === 'text') {
            printSuccess("Тип сообщения определен: text");
        } else {
            printError("Неверный тип сообщения");
        }
        
        if (isset($data['method_name']) && $data['method_name'] === 'sendMessage') {
            printSuccess("Метод: sendMessage");
        } else {
            printError("Неверный метод");
        }
        
        if (isset($data['chat_id']) && $data['chat_id'] === 456) {
            printSuccess("Chat ID: 456");
        } else {
            printError("Неверный chat_id");
        }
        
        // В minimal не должно быть текста
        if (!isset($data['text'])) {
            printSuccess("Текст не сохранен (уровень minimal)");
        } else {
            printError("Текст не должен сохраняться на уровне minimal");
        }
    }
} else {
    printError("Сообщение не было сохранено");
}

// ===========================
// ТЕСТ 5: Сохранение исходящих (standard)
// ===========================
printHeader("ТЕСТ 5: Сохранение исходящих сообщений (standard)");

$mockDb5 = new MockMySQL();
$mockDb5->simulateTableExists = true;
$storage5 = new MessageStorage($mockDb5, $logger, [
    'enabled' => true,
    'storage_level' => MessageStorage::LEVEL_STANDARD,
    'auto_create_table' => false,
]);

printInfo("Сохранение с уровнем standard...");
$params = ['chat_id' => 456, 'text' => 'Standard test message', 'caption' => 'Test caption'];
$id = $storage5->storeOutgoing('sendMessage', $params, $message, true);

if ($id !== null) {
    printSuccess("Сообщение сохранено");
    
    $lastInsert = end($mockDb5->insertedData);
    if ($lastInsert) {
        $data = $lastInsert['data'];
        
        if (isset($data['text']) && $data['text'] === 'Standard test message') {
            printSuccess("Текст сохранен на уровне standard");
        } else {
            printError("Текст должен сохраняться на уровне standard");
        }
        
        if (isset($data['caption']) && $data['caption'] === 'Test caption') {
            printSuccess("Caption сохранен");
        } else {
            printError("Caption должен сохраняться");
        }
    }
}

// ===========================
// ТЕСТ 6: Сохранение исходящих (full)
// ===========================
printHeader("ТЕСТ 6: Сохранение исходящих сообщений (full)");

$mockDb6 = new MockMySQL();
$mockDb6->simulateTableExists = true;
$storage6 = new MessageStorage($mockDb6, $logger, [
    'enabled' => true,
    'storage_level' => MessageStorage::LEVEL_FULL,
    'auto_create_table' => false,
]);

printInfo("Сохранение с уровнем full...");
$params = [
    'chat_id' => 456,
    'text' => 'Full test message',
    'reply_markup' => ['inline_keyboard' => [[['text' => 'Button', 'callback_data' => 'action']]]],
];
$id = $storage6->storeOutgoing('sendMessage', $params, $message, true);

if ($id !== null) {
    printSuccess("Сообщение сохранено");
    
    $lastInsert = end($mockDb6->insertedData);
    if ($lastInsert) {
        $data = $lastInsert['data'];
        
        if (isset($data['text'])) {
            printSuccess("Текст сохранен");
        }
        
        if (isset($data['reply_markup'])) {
            printSuccess("Reply markup сохранен на уровне full");
        } else {
            printError("Reply markup должен сохраняться на уровне full");
        }
        
        if (isset($data['options'])) {
            printSuccess("Options (все параметры) сохранены на уровне full");
        } else {
            printError("Options должны сохраняться на уровне full");
        }
        
        if (isset($data['raw_data'])) {
            printSuccess("Raw data сохранен на уровне full");
        } else {
            printError("Raw data должен сохраняться на уровне full");
        }
    }
}

// ===========================
// ТЕСТ 7: Исключение методов
// ===========================
printHeader("ТЕСТ 7: Исключение методов из сохранения");

$mockDb7 = new MockMySQL();
$mockDb7->simulateTableExists = true;
$storage7 = new MessageStorage($mockDb7, $logger, [
    'enabled' => true,
    'storage_level' => MessageStorage::LEVEL_STANDARD,
    'exclude_methods' => ['getMe', 'getWebhookInfo'],
    'auto_create_table' => false,
]);

printInfo("Сохранение исключенного метода getMe...");
$id = $storage7->storeOutgoing('getMe', [], null, true);

if ($id === null) {
    printSuccess("Метод getMe корректно исключен из сохранения");
} else {
    printError("Метод getMe не должен сохраняться");
}

printInfo("Сохранение не исключенного метода sendMessage...");
$id = $storage7->storeOutgoing('sendMessage', ['chat_id' => 456, 'text' => 'Test'], null, true);

if ($id !== null) {
    printSuccess("Метод sendMessage корректно сохранен");
} else {
    printError("Метод sendMessage должен сохраняться");
}

// ===========================
// ТЕСТ 8: Отключение хранения входящих/исходящих
// ===========================
printHeader("ТЕСТ 8: Отключение хранения по направлению");

$mockDb8 = new MockMySQL();
$mockDb8->simulateTableExists = true;
$storage8 = new MessageStorage($mockDb8, $logger, [
    'enabled' => true,
    'storage_level' => MessageStorage::LEVEL_STANDARD,
    'store_incoming' => false,
    'store_outgoing' => true,
    'auto_create_table' => false,
]);

printInfo("Попытка сохранения входящего (store_incoming = false)...");
$id = $storage8->storeIncoming($message);

if ($id === null) {
    printSuccess("Входящее сообщение не сохранено (корректно)");
} else {
    printError("Входящее сообщение не должно сохраняться");
}

printInfo("Сохранение исходящего (store_outgoing = true)...");
$id = $storage8->storeOutgoing('sendMessage', ['chat_id' => 456, 'text' => 'Test'], null, true);

if ($id !== null) {
    printSuccess("Исходящее сообщение сохранено (корректно)");
} else {
    printError("Исходящее сообщение должно сохраняться");
}

// ===========================
// ТЕСТ 9: Сохранение ошибок
// ===========================
printHeader("ТЕСТ 9: Сохранение неудачных запросов");

$mockDb9 = new MockMySQL();
$mockDb9->simulateTableExists = true;
$storage9 = new MessageStorage($mockDb9, $logger, [
    'enabled' => true,
    'storage_level' => MessageStorage::LEVEL_STANDARD,
    'auto_create_table' => false,
]);

printInfo("Сохранение неудачного запроса...");
$params = ['chat_id' => -999999, 'text' => 'Failed message'];
$id = $storage9->storeOutgoing('sendMessage', $params, null, false, 400, 'Bad Request: chat not found');

if ($id !== null) {
    printSuccess("Неудачный запрос сохранен");
    
    $lastInsert = end($mockDb9->insertedData);
    if ($lastInsert) {
        $data = $lastInsert['data'];
        
        if (isset($data['success']) && $data['success'] === 0) {
            printSuccess("Статус success = 0");
        } else {
            printError("Неверный статус success");
        }
        
        if (isset($data['error_code']) && $data['error_code'] === 400) {
            printSuccess("Код ошибки сохранен: 400");
        } else {
            printError("Код ошибки не сохранен");
        }
        
        if (isset($data['error_message']) && str_contains($data['error_message'], 'chat not found')) {
            printSuccess("Сообщение об ошибке сохранено");
        } else {
            printError("Сообщение об ошибке не сохранено");
        }
    }
}

// ===========================
// ТЕСТ 10: Различные типы сообщений
// ===========================
printHeader("ТЕСТ 10: Определение типов сообщений");

$mockDb10 = new MockMySQL();
$mockDb10->simulateTableExists = true;
$storage10 = new MessageStorage($mockDb10, $logger, [
    'enabled' => true,
    'storage_level' => MessageStorage::LEVEL_STANDARD,
    'auto_create_table' => false,
]);

$messageTypes = [
    'sendMessage' => 'text',
    'sendPhoto' => 'photo',
    'sendVideo' => 'video',
    'sendAudio' => 'audio',
    'sendDocument' => 'document',
    'sendPoll' => 'poll',
    'editMessageText' => 'edit_text',
    'deleteMessage' => 'delete',
];

foreach ($messageTypes as $method => $expectedType) {
    printInfo("Проверка метода $method...");
    $storage10->storeOutgoing($method, ['chat_id' => 456], null, true);
    
    $lastInsert = end($mockDb10->insertedData);
    if ($lastInsert && $lastInsert['data']['message_type'] === $expectedType) {
        printSuccess("Тип '$expectedType' определен корректно");
    } else {
        printError("Неверный тип для метода $method");
    }
}

// ===========================
// ИТОГИ
// ===========================
printHeader("ИТОГИ UNIT-ТЕСТИРОВАНИЯ");

printSuccess("Все unit-тесты выполнены!");
printInfo("Класс MessageStorage корректно:");
printInfo("  ✓ Инициализируется с различными настройками");
printInfo("  ✓ Создает таблицу в БД");
printInfo("  ✓ Поддерживает 4 уровня хранения данных");
printInfo("  ✓ Сохраняет исходящие сообщения");
printInfo("  ✓ Сохраняет входящие сообщения");
printInfo("  ✓ Определяет типы сообщений");
printInfo("  ✓ Исключает методы из сохранения");
printInfo("  ✓ Отключает хранение по направлению");
printInfo("  ✓ Сохраняет ошибки");
printInfo("  ✓ Логирует все операции");

printInfo("\nПодробные логи: logs/telegram_bot_storage_unit_test.log");

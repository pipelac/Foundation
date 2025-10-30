<?php

declare(strict_types=1);

/**
 * Полноценный тест класса MySQLConnectionFactory на реальной БД MySQL
 * 
 * Проверяет все методы класса:
 * - Создание соединений с несколькими БД
 * - Кеширование соединений
 * - Получение соединения по умолчанию
 * - Проверка активности соединений
 * - Работа с версиями MySQL
 * - Управление кешем соединений
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Component\MySQLConnectionFactory;
use App\Component\Logger;
use App\Component\Exception\MySQLException;

// Цвета для консольного вывода
const COLOR_GREEN = "\033[0;32m";
const COLOR_RED = "\033[0;31m";
const COLOR_YELLOW = "\033[1;33m";
const COLOR_BLUE = "\033[0;34m";
const COLOR_CYAN = "\033[0;36m";
const COLOR_RESET = "\033[0m";
const COLOR_BOLD = "\033[1m";

/**
 * Выводит успешное сообщение
 */
function printSuccess(string $message): void
{
    echo COLOR_GREEN . "✓ " . $message . COLOR_RESET . PHP_EOL;
}

/**
 * Выводит сообщение об ошибке
 */
function printError(string $message): void
{
    echo COLOR_RED . "✗ " . $message . COLOR_RESET . PHP_EOL;
}

/**
 * Выводит информационное сообщение
 */
function printInfo(string $message): void
{
    echo COLOR_CYAN . "ℹ " . $message . COLOR_RESET . PHP_EOL;
}

/**
 * Выводит заголовок теста
 */
function printTestHeader(string $message): void
{
    echo PHP_EOL . COLOR_BOLD . COLOR_BLUE . "=== " . $message . " ===" . COLOR_RESET . PHP_EOL;
}

/**
 * Выводит предупреждение
 */
function printWarning(string $message): void
{
    echo COLOR_YELLOW . "⚠ " . $message . COLOR_RESET . PHP_EOL;
}

/**
 * Подсчёт результатов тестов
 */
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

/**
 * Выполняет тест и считает результаты
 */
function runTest(string $testName, callable $testFunction): void
{
    global $totalTests, $passedTests, $failedTests;
    
    $totalTests++;
    
    try {
        $testFunction();
        $passedTests++;
        printSuccess($testName);
    } catch (Throwable $e) {
        $failedTests++;
        printError($testName);
        printError("  Причина: " . $e->getMessage());
        printError("  Файл: " . $e->getFile() . ":" . $e->getLine());
    }
}

// ============================================================================
// Начало тестирования
// ============================================================================

echo COLOR_BOLD . PHP_EOL;
echo "╔══════════════════════════════════════════════════════════════════════╗" . PHP_EOL;
echo "║  ТЕСТИРОВАНИЕ MySQLConnectionFactory НА РЕАЛЬНОЙ БД MYSQL           ║" . PHP_EOL;
echo "╚══════════════════════════════════════════════════════════════════════╝" . PHP_EOL;
echo COLOR_RESET . PHP_EOL;

// Создаём директорию для логов
$logDir = sys_get_temp_dir() . '/mysql_factory_test_' . uniqid();
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

// Создаём логгер для отладки
printInfo("Создание логгера для отладки...");
$logger = new Logger([
    'directory' => $logDir,
    'file_name' => 'factory_test.log',
    'max_files' => 3,
    'max_file_size' => 10,
    'enabled' => true,
]);
printSuccess("Логгер создан: " . $logDir . "/factory_test.log");

// Конфигурация подключения к БД
$config = [
    'databases' => [
        'main' => [
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'test_db_main',
            'username' => 'test_user',
            'password' => 'Test_Pass123!',
            'charset' => 'utf8mb4',
            'persistent' => false,
        ],
        'analytics' => [
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'test_db_analytics',
            'username' => 'test_user',
            'password' => 'Test_Pass123!',
            'charset' => 'utf8mb4',
        ],
        'logs' => [
            'host' => '127.0.0.1',
            'database' => 'test_db_logs',
            'username' => 'test_user',
            'password' => 'Test_Pass123!',
        ],
    ],
    'default' => 'main',
];

// ============================================================================
// ТЕСТ 1: Создание фабрики соединений
// ============================================================================

printTestHeader("ТЕСТ 1: Создание фабрики соединений");

runTest("Создание MySQLConnectionFactory с валидной конфигурацией", function () use ($config, $logger) {
    $factory = new MySQLConnectionFactory($config, $logger);
    if (!($factory instanceof MySQLConnectionFactory)) {
        throw new Exception("Фабрика не создана");
    }
});

// ============================================================================
// ТЕСТ 2: Получение списка доступных баз данных
// ============================================================================

printTestHeader("ТЕСТ 2: Получение списка доступных баз данных");

runTest("Получение списка всех доступных баз данных", function () use ($config, $logger) {
    $factory = new MySQLConnectionFactory($config, $logger);
    $databases = $factory->getAvailableDatabases();
    
    $expected = ['main', 'analytics', 'logs'];
    if ($databases !== $expected) {
        throw new Exception("Ожидалось: " . json_encode($expected) . ", получено: " . json_encode($databases));
    }
    
    printInfo("  Доступные БД: " . implode(", ", $databases));
});

runTest("Получение имени базы данных по умолчанию", function () use ($config, $logger) {
    $factory = new MySQLConnectionFactory($config, $logger);
    $defaultDb = $factory->getDefaultDatabaseName();
    
    if ($defaultDb !== 'main') {
        throw new Exception("Ожидалось 'main', получено: " . $defaultDb);
    }
    
    printInfo("  База данных по умолчанию: " . $defaultDb);
});

// ============================================================================
// ТЕСТ 3: Проверка существования конфигурации базы данных
// ============================================================================

printTestHeader("ТЕСТ 3: Проверка существования конфигурации базы данных");

runTest("Проверка существующей базы данных (main)", function () use ($config, $logger) {
    $factory = new MySQLConnectionFactory($config, $logger);
    if (!$factory->hasDatabase('main')) {
        throw new Exception("База данных 'main' должна существовать");
    }
});

runTest("Проверка несуществующей базы данных", function () use ($config, $logger) {
    $factory = new MySQLConnectionFactory($config, $logger);
    if ($factory->hasDatabase('nonexistent')) {
        throw new Exception("База данных 'nonexistent' не должна существовать");
    }
});

// ============================================================================
// ТЕСТ 4: Получение соединений с разными базами данных
// ============================================================================

printTestHeader("ТЕСТ 4: Получение соединений с разными базами данных");

runTest("Получение соединения с основной БД (main)", function () use ($config, $logger) {
    $factory = new MySQLConnectionFactory($config, $logger);
    $connection = $factory->getConnection('main');
    
    if (!($connection instanceof \App\Component\MySQL)) {
        throw new Exception("Соединение не является экземпляром MySQL");
    }
    
    // Проверяем, что соединение работает
    $result = $connection->queryScalar('SELECT DATABASE()');
    if ($result !== 'test_db_main') {
        throw new Exception("Ожидалась БД 'test_db_main', получена: " . $result);
    }
    
    printInfo("  Активная БД: " . $result);
});

runTest("Получение соединения с аналитической БД (analytics)", function () use ($config, $logger) {
    $factory = new MySQLConnectionFactory($config, $logger);
    $connection = $factory->getConnection('analytics');
    
    $result = $connection->queryScalar('SELECT DATABASE()');
    if ($result !== 'test_db_analytics') {
        throw new Exception("Ожидалась БД 'test_db_analytics', получена: " . $result);
    }
    
    printInfo("  Активная БД: " . $result);
});

runTest("Получение соединения с БД логов (logs)", function () use ($config, $logger) {
    $factory = new MySQLConnectionFactory($config, $logger);
    $connection = $factory->getConnection('logs');
    
    $result = $connection->queryScalar('SELECT DATABASE()');
    if ($result !== 'test_db_logs') {
        throw new Exception("Ожидалась БД 'test_db_logs', получена: " . $result);
    }
    
    printInfo("  Активная БД: " . $result);
});

runTest("Получение соединения по умолчанию (null)", function () use ($config, $logger) {
    $factory = new MySQLConnectionFactory($config, $logger);
    $connection = $factory->getConnection(null);
    
    $result = $connection->queryScalar('SELECT DATABASE()');
    if ($result !== 'test_db_main') {
        throw new Exception("Ожидалась БД по умолчанию 'test_db_main', получена: " . $result);
    }
    
    printInfo("  Активная БД по умолчанию: " . $result);
});

runTest("Получение соединения через getDefaultConnection()", function () use ($config, $logger) {
    $factory = new MySQLConnectionFactory($config, $logger);
    $connection = $factory->getDefaultConnection();
    
    $result = $connection->queryScalar('SELECT DATABASE()');
    if ($result !== 'test_db_main') {
        throw new Exception("Ожидалась БД по умолчанию 'test_db_main', получена: " . $result);
    }
});

// ============================================================================
// ТЕСТ 5: Кеширование соединений
// ============================================================================

printTestHeader("ТЕСТ 5: Кеширование соединений");

runTest("Проверка кеширования соединений", function () use ($config, $logger) {
    $factory = new MySQLConnectionFactory($config, $logger);
    
    // Очищаем кеш перед тестом
    $factory->clearConnectionCache(null);
    
    // Первое обращение - создаёт соединение
    $connection1 = $factory->getConnection('main');
    $count1 = $factory->getCachedConnectionsCount();
    
    // Второе обращение - возвращает кешированное соединение
    $connection2 = $factory->getConnection('main');
    $count2 = $factory->getCachedConnectionsCount();
    
    if ($connection1 !== $connection2) {
        throw new Exception("Соединения должны быть идентичными (кеширование)");
    }
    
    if ($count1 !== 1 || $count2 !== 1) {
        throw new Exception("Должно быть ровно одно закешированное соединение");
    }
    
    printInfo("  Кешированных соединений: " . $count2);
});

runTest("Проверка кеширования нескольких соединений", function () use ($config, $logger) {
    $factory = new MySQLConnectionFactory($config, $logger);
    
    // Очищаем кеш перед тестом
    $factory->clearConnectionCache(null);
    
    $factory->getConnection('main');
    $factory->getConnection('analytics');
    $factory->getConnection('logs');
    
    $count = $factory->getCachedConnectionsCount();
    if ($count !== 3) {
        throw new Exception("Ожидалось 3 закешированных соединения, получено: " . $count);
    }
    
    $activeDbs = $factory->getActiveDatabases();
    $expected = ['main', 'analytics', 'logs'];
    
    sort($activeDbs);
    sort($expected);
    
    if ($activeDbs !== $expected) {
        throw new Exception("Неверный список активных БД");
    }
    
    printInfo("  Кешированных соединений: " . $count);
    printInfo("  Активные БД: " . implode(", ", $activeDbs));
});

// ============================================================================
// ТЕСТ 6: Проверка активности соединений
// ============================================================================

printTestHeader("ТЕСТ 6: Проверка активности соединений");

runTest("Проверка активности существующего соединения", function () use ($config, $logger) {
    $factory = new MySQLConnectionFactory($config, $logger);
    
    // Очищаем кеш перед тестом
    $factory->clearConnectionCache(null);
    
    $factory->getConnection('main');
    
    if (!$factory->isConnectionAlive('main')) {
        throw new Exception("Соединение должно быть активным");
    }
});

runTest("Проверка активности несуществующего соединения", function () use ($config, $logger) {
    $factory = new MySQLConnectionFactory($config, $logger);
    
    // Очищаем кеш перед тестом
    $factory->clearConnectionCache(null);
    
    if ($factory->isConnectionAlive('analytics')) {
        throw new Exception("Соединение не должно быть активным (не создано)");
    }
});

runTest("Проверка активности соединения по умолчанию", function () use ($config, $logger) {
    $factory = new MySQLConnectionFactory($config, $logger);
    $factory->getDefaultConnection();
    
    if (!$factory->isConnectionAlive(null)) {
        throw new Exception("Соединение по умолчанию должно быть активным");
    }
});

// ============================================================================
// ТЕСТ 7: Получение информации о версиях MySQL
// ============================================================================

printTestHeader("ТЕСТ 7: Получение информации о версиях MySQL");

runTest("Получение версий MySQL для всех активных соединений", function () use ($config, $logger) {
    $factory = new MySQLConnectionFactory($config, $logger);
    
    // Очищаем кеш перед тестом
    $factory->clearConnectionCache(null);
    
    $factory->getConnection('main');
    $factory->getConnection('analytics');
    
    $versions = $factory->getMySQLVersions();
    
    if (count($versions) !== 2) {
        throw new Exception("Должно быть 2 версии (main, analytics), получено: " . count($versions));
    }
    
    if (!isset($versions['main']) || !isset($versions['analytics'])) {
        throw new Exception("Отсутствуют версии для main или analytics");
    }
    
    foreach ($versions as $dbName => $versionInfo) {
        printInfo("  БД '{$dbName}':");
        printInfo("    Версия: {$versionInfo['version']}");
        printInfo("    Major: {$versionInfo['major']}, Minor: {$versionInfo['minor']}, Patch: {$versionInfo['patch']}");
        printInfo("    Поддерживается: " . ($versionInfo['is_supported'] ? 'Да' : 'Нет'));
        printInfo("    Рекомендуется: " . ($versionInfo['is_recommended'] ? 'Да' : 'Нет'));
    }
});

runTest("Проверка поддержки версий MySQL", function () use ($config, $logger) {
    $factory = new MySQLConnectionFactory($config, $logger);
    
    $factory->getConnection('main');
    $factory->getConnection('analytics');
    
    if (!$factory->areAllVersionsSupported()) {
        throw new Exception("Все версии должны быть поддерживаемыми");
    }
});

runTest("Проверка рекомендуемых версий MySQL", function () use ($config, $logger) {
    $factory = new MySQLConnectionFactory($config, $logger);
    
    $factory->getConnection('main');
    
    if (!$factory->areAllVersionsRecommended()) {
        printWarning("  Используется не рекомендованная версия MySQL");
    }
});

// ============================================================================
// ТЕСТ 8: Очистка кеша соединений
// ============================================================================

printTestHeader("ТЕСТ 8: Очистка кеша соединений");

runTest("Очистка кеша конкретного соединения", function () use ($config, $logger) {
    $factory = new MySQLConnectionFactory($config, $logger);
    
    // Очищаем кеш перед тестом
    $factory->clearConnectionCache(null);
    
    $factory->getConnection('main');
    $factory->getConnection('analytics');
    
    $countBefore = $factory->getCachedConnectionsCount();
    $factory->clearConnectionCache('main');
    $countAfter = $factory->getCachedConnectionsCount();
    
    if ($countBefore !== 2) {
        throw new Exception("До очистки должно быть 2 соединения, получено: " . $countBefore);
    }
    
    if ($countAfter !== 1) {
        throw new Exception("После очистки должно быть 1 соединение, получено: " . $countAfter);
    }
    
    printInfo("  До очистки: {$countBefore}, После: {$countAfter}");
});

runTest("Очистка всего кеша соединений", function () use ($config, $logger) {
    $factory = new MySQLConnectionFactory($config, $logger);
    
    // Очищаем кеш перед тестом
    $factory->clearConnectionCache(null);
    
    $factory->getConnection('main');
    $factory->getConnection('analytics');
    $factory->getConnection('logs');
    
    $countBefore = $factory->getCachedConnectionsCount();
    $factory->clearConnectionCache(null);
    $countAfter = $factory->getCachedConnectionsCount();
    
    if ($countBefore !== 3) {
        throw new Exception("До очистки должно быть 3 соединения, получено: " . $countBefore);
    }
    
    if ($countAfter !== 0) {
        throw new Exception("После очистки должно быть 0 соединений, получено: " . $countAfter);
    }
    
    printInfo("  До очистки: {$countBefore}, После: {$countAfter}");
});

// ============================================================================
// ТЕСТ 9: Работа с реальными данными в БД
// ============================================================================

printTestHeader("ТЕСТ 9: Работа с реальными данными в БД");

runTest("Создание таблицы и вставка данных в main БД", function () use ($config, $logger) {
    $factory = new MySQLConnectionFactory($config, $logger);
    $db = $factory->getConnection('main');
    
    // Создаём таблицу
    $db->execute('DROP TABLE IF EXISTS users');
    $db->execute('
        CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');
    
    // Вставляем данные
    $userId = $db->insert('INSERT INTO users (name, email) VALUES (?, ?)', ['Иван Иванов', 'ivan@example.com']);
    
    if ($userId <= 0) {
        throw new Exception("ID должен быть больше 0");
    }
    
    printInfo("  Создана таблица users, вставлена запись с ID: {$userId}");
});

runTest("Чтение данных из main БД", function () use ($config, $logger) {
    $factory = new MySQLConnectionFactory($config, $logger);
    $db = $factory->getConnection('main');
    
    $users = $db->query('SELECT * FROM users');
    
    if (count($users) !== 1) {
        throw new Exception("Ожидалась 1 запись, получено: " . count($users));
    }
    
    $user = $users[0];
    if ($user['name'] !== 'Иван Иванов' || $user['email'] !== 'ivan@example.com') {
        throw new Exception("Неверные данные пользователя");
    }
    
    printInfo("  Прочитана запись: {$user['name']} ({$user['email']})");
});

runTest("Массовая вставка данных через insertBatch", function () use ($config, $logger) {
    $factory = new MySQLConnectionFactory($config, $logger);
    $db = $factory->getConnection('main');
    
    $rows = [
        ['name' => 'Пётр Петров', 'email' => 'petr@example.com'],
        ['name' => 'Мария Сидорова', 'email' => 'maria@example.com'],
        ['name' => 'Алексей Смирнов', 'email' => 'alexey@example.com'],
    ];
    
    $inserted = $db->insertBatch('users', $rows);
    
    if ($inserted !== 3) {
        throw new Exception("Ожидалось 3 вставленных записи, получено: {$inserted}");
    }
    
    $totalUsers = $db->queryScalar('SELECT COUNT(*) FROM users');
    if ($totalUsers !== 4) {
        throw new Exception("Всего должно быть 4 пользователя");
    }
    
    printInfo("  Вставлено записей: {$inserted}, Всего: {$totalUsers}");
});

runTest("Обновление данных в БД", function () use ($config, $logger) {
    $factory = new MySQLConnectionFactory($config, $logger);
    $db = $factory->getConnection('main');
    
    $affected = $db->update('UPDATE users SET email = ? WHERE name = ?', 
        ['new_ivan@example.com', 'Иван Иванов']);
    
    if ($affected !== 1) {
        throw new Exception("Ожидалась 1 обновлённая запись");
    }
    
    $user = $db->queryOne('SELECT * FROM users WHERE name = ?', ['Иван Иванов']);
    if ($user['email'] !== 'new_ivan@example.com') {
        throw new Exception("Email не обновлён");
    }
    
    printInfo("  Обновлено записей: {$affected}");
});

runTest("Удаление данных из БД", function () use ($config, $logger) {
    $factory = new MySQLConnectionFactory($config, $logger);
    $db = $factory->getConnection('main');
    
    $deleted = $db->delete('DELETE FROM users WHERE name = ?', ['Пётр Петров']);
    
    if ($deleted !== 1) {
        throw new Exception("Ожидалась 1 удалённая запись");
    }
    
    $totalUsers = $db->queryScalar('SELECT COUNT(*) FROM users');
    if ($totalUsers !== 3) {
        throw new Exception("Всего должно остаться 3 пользователя");
    }
    
    printInfo("  Удалено записей: {$deleted}, Осталось: {$totalUsers}");
});

// ============================================================================
// ТЕСТ 10: Работа с несколькими БД одновременно
// ============================================================================

printTestHeader("ТЕСТ 10: Работа с несколькими БД одновременно");

runTest("Создание таблиц в analytics и logs БД", function () use ($config, $logger) {
    $factory = new MySQLConnectionFactory($config, $logger);
    
    // Analytics БД
    $analyticsDb = $factory->getConnection('analytics');
    $analyticsDb->execute('DROP TABLE IF EXISTS statistics');
    $analyticsDb->execute('
        CREATE TABLE statistics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            metric_name VARCHAR(100) NOT NULL,
            value DECIMAL(10,2) NOT NULL,
            recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');
    
    // Logs БД
    $logsDb = $factory->getConnection('logs');
    $logsDb->execute('DROP TABLE IF EXISTS app_logs');
    $logsDb->execute('
        CREATE TABLE app_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            level VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');
    
    printInfo("  Созданы таблицы statistics и app_logs");
});

runTest("Вставка данных в разные БД", function () use ($config, $logger) {
    $factory = new MySQLConnectionFactory($config, $logger);
    
    // Вставляем в analytics
    $analyticsDb = $factory->getConnection('analytics');
    $analyticsDb->insert('INSERT INTO statistics (metric_name, value) VALUES (?, ?)', 
        ['users_count', 150]);
    $analyticsDb->insert('INSERT INTO statistics (metric_name, value) VALUES (?, ?)', 
        ['page_views', 5280]);
    
    // Вставляем в logs
    $logsDb = $factory->getConnection('logs');
    $logsDb->insert('INSERT INTO app_logs (level, message) VALUES (?, ?)', 
        ['INFO', 'Приложение запущено']);
    $logsDb->insert('INSERT INTO app_logs (level, message) VALUES (?, ?)', 
        ['ERROR', 'Ошибка соединения с внешним API']);
    
    // Проверяем количество
    $statsCount = $analyticsDb->queryScalar('SELECT COUNT(*) FROM statistics');
    $logsCount = $logsDb->queryScalar('SELECT COUNT(*) FROM app_logs');
    
    if ($statsCount !== 2 || $logsCount !== 2) {
        throw new Exception("Неверное количество записей");
    }
    
    printInfo("  Statistics: {$statsCount} записей, Logs: {$logsCount} записей");
});

runTest("Чтение данных из разных БД одновременно", function () use ($config, $logger) {
    $factory = new MySQLConnectionFactory($config, $logger);
    
    $mainDb = $factory->getConnection('main');
    $analyticsDb = $factory->getConnection('analytics');
    $logsDb = $factory->getConnection('logs');
    
    $usersCount = $mainDb->queryScalar('SELECT COUNT(*) FROM users');
    $statsCount = $analyticsDb->queryScalar('SELECT COUNT(*) FROM statistics');
    $logsCount = $logsDb->queryScalar('SELECT COUNT(*) FROM app_logs');
    
    printInfo("  Main (users): {$usersCount}");
    printInfo("  Analytics (statistics): {$statsCount}");
    printInfo("  Logs (app_logs): {$logsCount}");
    
    if ($factory->getCachedConnectionsCount() !== 3) {
        throw new Exception("Должно быть 3 активных соединения");
    }
});

// ============================================================================
// ТЕСТ 11: Обработка ошибок
// ============================================================================

printTestHeader("ТЕСТ 11: Обработка ошибок");

runTest("Ошибка при получении соединения с несуществующей БД", function () use ($config, $logger) {
    $factory = new MySQLConnectionFactory($config, $logger);
    
    try {
        $factory->getConnection('nonexistent');
        throw new Exception("Должно быть выброшено исключение MySQLException");
    } catch (MySQLException $e) {
        if (strpos($e->getMessage(), 'не найдена в конфигурации') === false) {
            throw new Exception("Неверное сообщение об ошибке: " . $e->getMessage());
        }
    }
});

runTest("Ошибка при создании фабрики без секции databases", function () use ($logger) {
    try {
        new MySQLConnectionFactory(['default' => 'main'], $logger);
        throw new Exception("Должно быть выброшено исключение MySQLException");
    } catch (MySQLException $e) {
        if (strpos($e->getMessage(), 'databases') === false) {
            throw new Exception("Неверное сообщение об ошибке");
        }
    }
});

runTest("Ошибка при создании фабрики с пустой секцией databases", function () use ($logger) {
    try {
        new MySQLConnectionFactory(['databases' => []], $logger);
        throw new Exception("Должно быть выброшено исключение MySQLException");
    } catch (MySQLException $e) {
        if (strpos($e->getMessage(), 'не должна быть пустой') === false) {
            throw new Exception("Неверное сообщение об ошибке");
        }
    }
});

runTest("Ошибка при отсутствии обязательных полей в конфигурации БД", function () use ($logger) {
    try {
        new MySQLConnectionFactory([
            'databases' => [
                'test' => [
                    'host' => 'localhost',
                    // Отсутствуют database, username, password
                ],
            ],
        ], $logger);
        throw new Exception("Должно быть выброшено исключение MySQLException");
    } catch (MySQLException $e) {
        if (strpos($e->getMessage(), 'обязательное поле') === false) {
            throw new Exception("Неверное сообщение об ошибке");
        }
    }
});

// ============================================================================
// ТЕСТ 12: Транзакции
// ============================================================================

printTestHeader("ТЕСТ 12: Транзакции");

runTest("Успешная транзакция с commit", function () use ($config, $logger) {
    $factory = new MySQLConnectionFactory($config, $logger);
    $db = $factory->getConnection('main');
    
    $db->beginTransaction();
    $db->insert('INSERT INTO users (name, email) VALUES (?, ?)', ['Транзакция Тест', 'trans@test.com']);
    $db->commit();
    
    $user = $db->queryOne('SELECT * FROM users WHERE email = ?', ['trans@test.com']);
    if ($user === null) {
        throw new Exception("Запись должна быть сохранена после commit");
    }
    
    printInfo("  Транзакция успешно выполнена");
});

runTest("Откат транзакции с rollback", function () use ($config, $logger) {
    $factory = new MySQLConnectionFactory($config, $logger);
    $db = $factory->getConnection('main');
    
    $countBefore = $db->queryScalar('SELECT COUNT(*) FROM users');
    
    $db->beginTransaction();
    $db->insert('INSERT INTO users (name, email) VALUES (?, ?)', ['Откат Тест', 'rollback@test.com']);
    $db->rollback();
    
    $countAfter = $db->queryScalar('SELECT COUNT(*) FROM users');
    
    if ($countBefore !== $countAfter) {
        throw new Exception("Количество записей должно остаться прежним после rollback");
    }
    
    $user = $db->queryOne('SELECT * FROM users WHERE email = ?', ['rollback@test.com']);
    if ($user !== null) {
        throw new Exception("Запись не должна быть сохранена после rollback");
    }
    
    printInfo("  Транзакция успешно отменена");
});

// ============================================================================
// Итоговые результаты
// ============================================================================

printTestHeader("ИТОГОВЫЕ РЕЗУЛЬТАТЫ");

echo COLOR_BOLD;
echo "Всего тестов: " . COLOR_CYAN . $totalTests . COLOR_RESET . COLOR_BOLD . PHP_EOL;
echo "Успешных: " . COLOR_GREEN . $passedTests . COLOR_RESET . COLOR_BOLD . PHP_EOL;
echo "Неудачных: " . COLOR_RED . $failedTests . COLOR_RESET . COLOR_BOLD . PHP_EOL;
echo COLOR_RESET;

$successRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 2) : 0;
echo PHP_EOL . "Процент успешных тестов: " . COLOR_CYAN . $successRate . "%" . COLOR_RESET . PHP_EOL;

if ($failedTests === 0) {
    echo PHP_EOL . COLOR_GREEN . COLOR_BOLD;
    echo "╔══════════════════════════════════════════════════════════════════════╗" . PHP_EOL;
    echo "║              ВСЕ ТЕСТЫ УСПЕШНО ПРОЙДЕНЫ! ✓                          ║" . PHP_EOL;
    echo "╚══════════════════════════════════════════════════════════════════════╝" . PHP_EOL;
    echo COLOR_RESET;
} else {
    echo PHP_EOL . COLOR_RED . COLOR_BOLD;
    echo "╔══════════════════════════════════════════════════════════════════════╗" . PHP_EOL;
    echo "║              НЕКОТОРЫЕ ТЕСТЫ НЕ ПРОШЛИ! ✗                           ║" . PHP_EOL;
    echo "╚══════════════════════════════════════════════════════════════════════╝" . PHP_EOL;
    echo COLOR_RESET;
}

// Выводим информацию о логах
printInfo("Логи тестирования сохранены в: " . $logDir . "/factory_test.log");

// Очистка тестовых данных
printTestHeader("ОЧИСТКА ТЕСТОВЫХ ДАННЫХ");

try {
    $factory = new MySQLConnectionFactory($config, $logger);
    
    $mainDb = $factory->getConnection('main');
    $mainDb->execute('DROP TABLE IF EXISTS users');
    printSuccess("Удалена таблица users из main БД");
    
    $analyticsDb = $factory->getConnection('analytics');
    $analyticsDb->execute('DROP TABLE IF EXISTS statistics');
    printSuccess("Удалена таблица statistics из analytics БД");
    
    $logsDb = $factory->getConnection('logs');
    $logsDb->execute('DROP TABLE IF EXISTS app_logs');
    printSuccess("Удалена таблица app_logs из logs БД");
    
} catch (Throwable $e) {
    printError("Ошибка при очистке: " . $e->getMessage());
}

echo PHP_EOL;

exit($failedTests > 0 ? 1 : 0);

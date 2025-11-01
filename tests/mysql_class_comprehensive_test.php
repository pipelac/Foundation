<?php

declare(strict_types=1);

/**
 * Полноценный тест класса MySQL.class.php
 * 
 * Тестирует все методы класса с реальной базой данных MySQL 8.0
 * Включает проверку логирования и обработки ошибок
 */

require_once __DIR__ . '/../autoload.php';

use App\Component\MySQL;
use App\Component\Logger;
use App\Component\Exception\MySQL\MySQLConnectionException;
use App\Component\Exception\MySQL\MySQLException;
use App\Component\Exception\MySQL\MySQLTransactionException;

// Настройка цветного вывода
const COLOR_GREEN = "\033[32m";
const COLOR_RED = "\033[31m";
const COLOR_YELLOW = "\033[33m";
const COLOR_BLUE = "\033[34m";
const COLOR_CYAN = "\033[36m";
const COLOR_RESET = "\033[0m";

// Счетчики тестов
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;
$testResults = [];

/**
 * Выводит заголовок секции
 */
function section(string $title): void {
    echo "\n" . COLOR_CYAN . "═══════════════════════════════════════════════════════" . COLOR_RESET . "\n";
    echo COLOR_CYAN . "  " . $title . COLOR_RESET . "\n";
    echo COLOR_CYAN . "═══════════════════════════════════════════════════════" . COLOR_RESET . "\n\n";
}

/**
 * Выполняет тест и возвращает результат
 */
function test(string $name, callable $callback): bool {
    global $totalTests, $passedTests, $failedTests, $testResults;
    
    $totalTests++;
    echo COLOR_BLUE . "► Тест #{$totalTests}: " . COLOR_RESET . $name . " ... ";
    
    try {
        $result = $callback();
        if ($result === true) {
            echo COLOR_GREEN . "✓ PASSED" . COLOR_RESET . "\n";
            $passedTests++;
            $testResults[] = ['name' => $name, 'status' => 'PASSED', 'error' => null];
            return true;
        } else {
            echo COLOR_RED . "✗ FAILED" . COLOR_RESET . " (вернул false)\n";
            $failedTests++;
            $testResults[] = ['name' => $name, 'status' => 'FAILED', 'error' => 'Callback вернул false'];
            return false;
        }
    } catch (Throwable $e) {
        echo COLOR_RED . "✗ FAILED" . COLOR_RESET . "\n";
        echo COLOR_RED . "   Ошибка: " . $e->getMessage() . COLOR_RESET . "\n";
        echo COLOR_YELLOW . "   Файл: " . $e->getFile() . ":" . $e->getLine() . COLOR_RESET . "\n";
        $failedTests++;
        $testResults[] = ['name' => $name, 'status' => 'FAILED', 'error' => $e->getMessage()];
        return false;
    }
}

/**
 * Проверяет условие
 */
function assert_true(bool $condition, string $message = ''): void {
    if (!$condition) {
        throw new Exception($message ?: 'Утверждение провалилось');
    }
}

/**
 * Проверяет равенство
 */
function assert_equals($expected, $actual, string $message = ''): void {
    if ($expected !== $actual) {
        $msg = $message ?: sprintf('Ожидалось %s, получено %s', 
            var_export($expected, true), 
            var_export($actual, true)
        );
        throw new Exception($msg);
    }
}

/**
 * Выводит итоговую статистику
 */
function printSummary(): void {
    global $totalTests, $passedTests, $failedTests, $testResults;
    
    echo "\n" . COLOR_CYAN . "═══════════════════════════════════════════════════════" . COLOR_RESET . "\n";
    echo COLOR_CYAN . "  ИТОГОВАЯ СТАТИСТИКА" . COLOR_RESET . "\n";
    echo COLOR_CYAN . "═══════════════════════════════════════════════════════" . COLOR_RESET . "\n\n";
    
    echo "Всего тестов: " . COLOR_BLUE . $totalTests . COLOR_RESET . "\n";
    echo "Успешных: " . COLOR_GREEN . $passedTests . COLOR_RESET . "\n";
    echo "Провалено: " . COLOR_RED . $failedTests . COLOR_RESET . "\n";
    
    $successRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 2) : 0;
    echo "Успешность: " . ($successRate >= 90 ? COLOR_GREEN : COLOR_RED) . $successRate . "%" . COLOR_RESET . "\n";
    
    if ($failedTests > 0) {
        echo "\n" . COLOR_RED . "Провалившиеся тесты:" . COLOR_RESET . "\n";
        foreach ($testResults as $index => $result) {
            if ($result['status'] === 'FAILED') {
                echo COLOR_RED . "  " . ($index + 1) . ". " . $result['name'] . COLOR_RESET . "\n";
                echo COLOR_YELLOW . "     " . $result['error'] . COLOR_RESET . "\n";
            }
        }
    }
    
    echo "\n";
}

// ============================================
// НАЧАЛО ТЕСТИРОВАНИЯ
// ============================================

echo COLOR_CYAN . "\n";
echo "████████╗███████╗███████╗████████╗    ███╗   ███╗██╗   ██╗███████╗ ██████╗ ██╗     \n";
echo "╚══██╔══╝██╔════╝██╔════╝╚══██╔══╝    ████╗ ████║╚██╗ ██╔╝██╔════╝██╔═══██╗██║     \n";
echo "   ██║   █████╗  ███████╗   ██║       ██╔████╔██║ ╚████╔╝ ███████╗██║   ██║██║     \n";
echo "   ██║   ██╔══╝  ╚════██║   ██║       ██║╚██╔╝██║  ╚██╔╝  ╚════██║██║▄▄ ██║██║     \n";
echo "   ██║   ███████╗███████║   ██║       ██║ ╚═╝ ██║   ██║   ███████║╚██████╔╝███████╗\n";
echo "   ╚═╝   ╚══════╝╚══════╝   ╚═╝       ╚═╝     ╚═╝   ╚═╝   ╚══════╝ ╚══▀▀═╝ ╚══════╝\n";
echo COLOR_RESET . "\n";

echo "Полноценный тест класса MySQL.class.php\n";
echo "MySQL версия: 8.0.43\n";
echo "PHP версия: " . PHP_VERSION . "\n\n";

// ============================================
// РАЗДЕЛ 1: ИНИЦИАЛИЗАЦИЯ И ПОДКЛЮЧЕНИЕ
// ============================================

section("1. ИНИЦИАЛИЗАЦИЯ И ПОДКЛЮЧЕНИЕ");

$logger = null;
$db = null;

// Настройка логгера
$logDir = __DIR__ . '/../logs/mysql_tests';
$logFileName = 'mysql_test_' . date('Y-m-d_H-i-s') . '.log';
$logFilePath = $logDir . '/' . $logFileName;

test('Создание логгера', function () use (&$logger, $logDir, $logFileName) {
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    $logger = new Logger([
        'directory' => $logDir,
        'file_name' => $logFileName,
        'max_files' => 5,
        'max_file_size' => 10, // MB
        'enabled' => true
    ]);
    
    assert_true($logger !== null, 'Логгер не создан');
    return true;
});

// Конфигурация подключения
$config = [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'app_integration_test',
    'username' => 'app_test',
    'password' => 'test_password_123',
    'charset' => 'utf8mb4',
    'persistent' => false,
    'cache_statements' => true,
];

test('Подключение к БД с правильными параметрами', function () use ($config, $logger, &$db) {
    $db = new MySQL($config, $logger);
    assert_true($db !== null, 'Не удалось создать экземпляр MySQL');
    return true;
});

test('Проверка состояния подключения (ping)', function () use ($db) {
    assert_true($db->ping(), 'Подключение не активно');
    return true;
});

test('Получение информации о подключении', function () use ($db) {
    $info = $db->getConnectionInfo();
    assert_true(is_array($info), 'Информация не является массивом');
    assert_true(isset($info['server_version']), 'Нет информации о версии сервера');
    assert_true(isset($info['in_transaction']), 'Нет информации о транзакции');
    return true;
});

test('Получение версии MySQL', function () use ($db) {
    $version = $db->getMySQLVersion();
    assert_true(is_array($version), 'Версия не является массивом');
    assert_true($version['major'] >= 5, 'Версия MySQL слишком старая');
    assert_true($version['is_supported'], 'Версия MySQL не поддерживается');
    return true;
});

test('Попытка подключения с неправильным паролем', function () use ($config, $logger) {
    try {
        $badConfig = $config;
        $badConfig['password'] = 'wrong_password';
        new MySQL($badConfig, $logger);
        return false; // Должно было выбросить исключение
    } catch (MySQLConnectionException $e) {
        return true; // Ожидаемое поведение
    }
});

test('Валидация конфигурации - отсутствует database', function () use ($logger) {
    try {
        new MySQL(['username' => 'test', 'password' => 'test'], $logger);
        return false;
    } catch (MySQLException $e) {
        return strpos($e->getMessage(), 'database') !== false;
    }
});

test('Валидация конфигурации - отсутствует username', function () use ($logger) {
    try {
        new MySQL(['database' => 'test', 'password' => 'test'], $logger);
        return false;
    } catch (MySQLException $e) {
        return strpos($e->getMessage(), 'username') !== false;
    }
});

// ============================================
// РАЗДЕЛ 2: СОЗДАНИЕ ТЕСТОВЫХ ТАБЛИЦ
// ============================================

section("2. СОЗДАНИЕ ТЕСТОВЫХ ТАБЛИЦ И СТРУКТУРЫ");

test('Удаление старых тестовых таблиц (если существуют)', function () use ($db) {
    $db->execute('DROP TABLE IF EXISTS test_users');
    $db->execute('DROP TABLE IF EXISTS test_products');
    $db->execute('DROP TABLE IF EXISTS test_orders');
    $db->execute('DROP TABLE IF EXISTS test_unique');
    return true;
});

test('Создание таблицы test_users', function () use ($db) {
    $sql = <<<SQL
    CREATE TABLE test_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        email VARCHAR(255) NOT NULL,
        age INT NULL,
        status VARCHAR(50) DEFAULT 'active',
        balance DECIMAL(10, 2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    SQL;
    
    $db->execute($sql);
    return true;
});

test('Создание таблицы test_products', function () use ($db) {
    $sql = <<<SQL
    CREATE TABLE test_products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        price DECIMAL(10, 2) NOT NULL,
        stock INT DEFAULT 0,
        category VARCHAR(100) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    SQL;
    
    $db->execute($sql);
    return true;
});

test('Создание таблицы test_orders', function () use ($db) {
    $sql = <<<SQL
    CREATE TABLE test_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        total_price DECIMAL(10, 2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES test_users(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES test_products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    SQL;
    
    $db->execute($sql);
    return true;
});

test('Создание таблицы test_unique для проверки UNIQUE ключей', function () use ($db) {
    $sql = <<<SQL
    CREATE TABLE test_unique (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL UNIQUE,
        value VARCHAR(100)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    SQL;
    
    $db->execute($sql);
    return true;
});

test('Проверка существования таблицы test_users', function () use ($db) {
    assert_true($db->tableExists('test_users'), 'Таблица test_users не существует');
    return true;
});

test('Проверка НЕсуществования несуществующей таблицы', function () use ($db) {
    assert_true(!$db->tableExists('non_existent_table'), 'Таблица не должна существовать');
    return true;
});

// ============================================
// РАЗДЕЛ 3: ОПЕРАЦИИ ВСТАВКИ (INSERT)
// ============================================

section("3. ОПЕРАЦИИ ВСТАВКИ (INSERT)");

$userId1 = 0;
$userId2 = 0;
$userId3 = 0;

test('Вставка записи с методом insert()', function () use ($db, &$userId1) {
    $userId1 = $db->insert('test_users', [
        'username' => 'john_doe',
        'email' => 'john@example.com',
        'age' => 30,
        'balance' => 100.50
    ]);
    
    assert_true($userId1 > 0, 'ID должен быть больше 0');
    return true;
});

test('Вставка второй записи', function () use ($db, &$userId2, &$userId1) {
    $userId2 = $db->insert('test_users', [
        'username' => 'jane_smith',
        'email' => 'jane@example.com',
        'age' => 25,
        'balance' => 250.75
    ]);
    
    assert_true($userId2 > $userId1, 'ID должен увеличиваться');
    return true;
});

test('Вставка записи с NULL значением', function () use ($db, &$userId3) {
    $userId3 = $db->insert('test_users', [
        'username' => 'bob_null',
        'email' => 'bob@example.com',
        'age' => null // NULL значение
    ]);
    
    assert_true($userId3 > 0, 'Вставка с NULL должна работать');
    return true;
});

test('Получение последнего вставленного ID', function () use ($db, $userId3) {
    $lastId = $db->getLastInsertId();
    assert_equals($userId3, $lastId, 'LastInsertId должен совпадать с последней вставкой');
    return true;
});

test('Вставка с пустым массивом должна выбросить исключение', function () use ($db) {
    try {
        $db->insert('test_users', []);
        return false;
    } catch (MySQLException $e) {
        return strpos($e->getMessage(), 'данных') !== false;
    }
});

test('Вставка дубликата UNIQUE ключа должна выбросить исключение', function () use ($db) {
    try {
        $db->insert('test_users', [
            'username' => 'john_doe', // дубликат
            'email' => 'duplicate@example.com'
        ]);
        return false;
    } catch (MySQLException $e) {
        return true; // Ожидаемое поведение
    }
});

// ============================================
// РАЗДЕЛ 4: INSERT СПЕЦИАЛЬНЫЕ ОПЕРАЦИИ
// ============================================

section("4. INSERT СПЕЦИАЛЬНЫЕ ОПЕРАЦИИ");

test('INSERT IGNORE - вставка дубликата игнорируется', function () use ($db) {
    $id = $db->insertIgnore('test_unique', [
        'email' => 'test@example.com',
        'value' => 'first'
    ]);
    
    assert_true($id > 0, 'Первая вставка должна быть успешной');
    
    // Попытка вставить дубликат
    $id2 = $db->insertIgnore('test_unique', [
        'email' => 'test@example.com', // дубликат
        'value' => 'second'
    ]);
    
    assert_equals(0, $id2, 'INSERT IGNORE должен вернуть 0 для дубликата');
    return true;
});

test('REPLACE - замена существующей записи', function () use ($db) {
    $id1 = $db->replace('test_unique', [
        'email' => 'replace@example.com',
        'value' => 'original'
    ]);
    
    $id2 = $db->replace('test_unique', [
        'email' => 'replace@example.com', // тот же email
        'value' => 'updated'
    ]);
    
    // Проверяем, что значение обновилось
    $result = $db->queryOne('SELECT value FROM test_unique WHERE email = ?', ['replace@example.com']);
    assert_equals('updated', $result['value'], 'REPLACE должен обновить значение');
    
    return true;
});

test('UPSERT - вставка или обновление при конфликте', function () use ($db) {
    $id1 = $db->upsert('test_unique', [
        'email' => 'upsert@example.com',
        'value' => 'first'
    ]);
    
    assert_true($id1 > 0, 'Первая вставка должна быть успешной');
    
    // Попытка вставить тот же email
    $db->upsert('test_unique', [
        'email' => 'upsert@example.com',
        'value' => 'second'
    ], [
        'value' => 'updated_by_upsert'
    ]);
    
    $result = $db->queryOne('SELECT value FROM test_unique WHERE email = ?', ['upsert@example.com']);
    assert_equals('updated_by_upsert', $result['value'], 'UPSERT должен обновить значение');
    
    return true;
});

// ============================================
// РАЗДЕЛ 5: МАССОВАЯ ВСТАВКА (BATCH INSERT)
// ============================================

section("5. МАССОВАЯ ВСТАВКА (BATCH INSERT)");

test('Массовая вставка продуктов (insertBatch)', function () use ($db) {
    $products = [
        ['name' => 'Laptop', 'price' => 1299.99, 'stock' => 10, 'category' => 'Electronics'],
        ['name' => 'Mouse', 'price' => 29.99, 'stock' => 100, 'category' => 'Electronics'],
        ['name' => 'Keyboard', 'price' => 79.99, 'stock' => 50, 'category' => 'Electronics'],
        ['name' => 'Monitor', 'price' => 399.99, 'stock' => 20, 'category' => 'Electronics'],
        ['name' => 'Desk Chair', 'price' => 249.99, 'stock' => 15, 'category' => 'Furniture'],
    ];
    
    $count = $db->insertBatch('test_products', $products);
    assert_equals(5, $count, 'Должно быть вставлено 5 записей');
    
    return true;
});

test('Массовая вставка с пустым массивом', function () use ($db) {
    $count = $db->insertBatch('test_products', []);
    assert_equals(0, $count, 'Пустой массив должен вернуть 0');
    return true;
});

// ============================================
// РАЗДЕЛ 6: ЗАПРОСЫ ВЫБОРКИ (SELECT)
// ============================================

section("6. ЗАПРОСЫ ВЫБОРКИ (SELECT)");

test('SELECT всех пользователей (query)', function () use ($db) {
    $users = $db->query('SELECT * FROM test_users ORDER BY id');
    assert_true(count($users) >= 3, 'Должно быть минимум 3 пользователя');
    assert_true(isset($users[0]['username']), 'Должно быть поле username');
    return true;
});

test('SELECT с параметрами (WHERE)', function () use ($db, $userId1) {
    $users = $db->query('SELECT * FROM test_users WHERE id = ?', [$userId1]);
    assert_equals(1, count($users), 'Должен быть найден 1 пользователь');
    assert_equals('john_doe', $users[0]['username'], 'Имя пользователя должно совпадать');
    return true;
});

test('SELECT одной записи (queryOne)', function () use ($db, $userId2) {
    $user = $db->queryOne('SELECT * FROM test_users WHERE id = ?', [$userId2]);
    assert_true($user !== null, 'Пользователь должен быть найден');
    assert_equals('jane_smith', $user['username'], 'Имя должно совпадать');
    return true;
});

test('queryOne возвращает NULL если запись не найдена', function () use ($db) {
    $user = $db->queryOne('SELECT * FROM test_users WHERE id = ?', [999999]);
    assert_true($user === null, 'Должен вернуть NULL для несуществующей записи');
    return true;
});

test('SELECT скалярного значения (queryScalar)', function () use ($db) {
    $count = $db->queryScalar('SELECT COUNT(*) FROM test_users');
    assert_true($count >= 3, 'Должно быть минимум 3 пользователя');
    assert_true(is_int($count) || is_string($count), 'Должно быть число');
    return true;
});

test('SELECT столбца (queryColumn)', function () use ($db) {
    $usernames = $db->queryColumn('SELECT username FROM test_users ORDER BY id');
    assert_true(count($usernames) >= 3, 'Должно быть минимум 3 имени');
    assert_true(in_array('john_doe', $usernames), 'Должен содержать john_doe');
    return true;
});

test('Проверка существования записи (exists)', function () use ($db, $userId1) {
    $exists = $db->exists('SELECT 1 FROM test_users WHERE id = ?', [$userId1]);
    assert_true($exists, 'Запись должна существовать');
    
    $notExists = $db->exists('SELECT 1 FROM test_users WHERE id = ?', [999999]);
    assert_true(!$notExists, 'Запись не должна существовать');
    
    return true;
});

test('Подсчет записей (count)', function () use ($db) {
    $totalCount = $db->count('test_users');
    assert_true($totalCount >= 3, 'Должно быть минимум 3 записи');
    
    $filteredCount = $db->count('test_users', ['status' => 'active']);
    assert_true($filteredCount > 0, 'Должны быть активные пользователи');
    
    return true;
});

// ============================================
// РАЗДЕЛ 7: ОПЕРАЦИИ ОБНОВЛЕНИЯ (UPDATE)
// ============================================

section("7. ОПЕРАЦИИ ОБНОВЛЕНИЯ (UPDATE)");

test('Обновление одной записи', function () use ($db, $userId1) {
    $affected = $db->update('test_users', 
        ['balance' => 500.00, 'status' => 'premium'],
        ['id' => $userId1]
    );
    
    assert_equals(1, $affected, 'Должна быть обновлена 1 запись');
    
    // Проверяем обновление
    $user = $db->queryOne('SELECT balance, status FROM test_users WHERE id = ?', [$userId1]);
    assert_equals('500.00', $user['balance'], 'Баланс должен быть обновлен');
    assert_equals('premium', $user['status'], 'Статус должен быть обновлен');
    
    return true;
});

test('Обновление нескольких записей', function () use ($db) {
    $affected = $db->update('test_users', 
        ['status' => 'verified'],
        ['status' => 'active']
    );
    
    assert_true($affected > 0, 'Должна быть обновлена минимум 1 запись');
    return true;
});

test('Обновление без условий (все записи)', function () use ($db) {
    $affected = $db->update('test_products', 
        ['stock' => 100],
        [] // без условий
    );
    
    assert_true($affected > 0, 'Должны быть обновлены все записи');
    return true;
});

test('Обновление с пустыми данными должно выбросить исключение', function () use ($db) {
    try {
        $db->update('test_users', [], ['id' => 1]);
        return false;
    } catch (MySQLException $e) {
        return strpos($e->getMessage(), 'данных') !== false;
    }
});

// ============================================
// РАЗДЕЛ 8: ОПЕРАЦИИ УДАЛЕНИЯ (DELETE)
// ============================================

section("8. ОПЕРАЦИИ УДАЛЕНИЯ (DELETE)");

test('Удаление одной записи', function () use ($db, $userId3) {
    $countBefore = $db->count('test_users');
    
    $affected = $db->delete('test_users', ['id' => $userId3]);
    assert_equals(1, $affected, 'Должна быть удалена 1 запись');
    
    $countAfter = $db->count('test_users');
    assert_equals($countBefore - 1, $countAfter, 'Количество записей должно уменьшиться на 1');
    
    return true;
});

test('Удаление по условию', function () use ($db) {
    // Сначала вставим тестовые записи
    $db->insert('test_users', ['username' => 'temp_user1', 'email' => 'temp1@test.com', 'status' => 'temporary']);
    $db->insert('test_users', ['username' => 'temp_user2', 'email' => 'temp2@test.com', 'status' => 'temporary']);
    
    $affected = $db->delete('test_users', ['status' => 'temporary']);
    assert_true($affected >= 2, 'Должно быть удалено минимум 2 записи');
    
    return true;
});

// ============================================
// РАЗДЕЛ 9: ТРАНЗАКЦИИ
// ============================================

section("9. ТРАНЗАКЦИИ");

test('Успешная транзакция с commit', function () use ($db) {
    $db->beginTransaction();
    
    $id1 = $db->insert('test_users', [
        'username' => 'transaction_user1',
        'email' => 'trans1@example.com'
    ]);
    
    $id2 = $db->insert('test_users', [
        'username' => 'transaction_user2',
        'email' => 'trans2@example.com'
    ]);
    
    $db->commit();
    
    // Проверяем, что записи вставлены
    assert_true($db->exists('SELECT 1 FROM test_users WHERE id = ?', [$id1]), 'Первая запись должна существовать');
    assert_true($db->exists('SELECT 1 FROM test_users WHERE id = ?', [$id2]), 'Вторая запись должна существовать');
    
    return true;
});

test('Транзакция с rollback', function () use ($db) {
    $countBefore = $db->count('test_users');
    
    $db->beginTransaction();
    
    $db->insert('test_users', [
        'username' => 'rollback_user',
        'email' => 'rollback@example.com'
    ]);
    
    $db->rollback();
    
    $countAfter = $db->count('test_users');
    assert_equals($countBefore, $countAfter, 'Количество записей не должно измениться');
    
    return true;
});

test('Проверка флага inTransaction', function () use ($db) {
    assert_true(!$db->inTransaction(), 'Не должно быть активной транзакции');
    
    $db->beginTransaction();
    assert_true($db->inTransaction(), 'Транзакция должна быть активна');
    
    $db->rollback();
    assert_true(!$db->inTransaction(), 'Транзакция должна быть завершена');
    
    return true;
});

test('Транзакция через callback (transaction)', function () use ($db) {
    $result = $db->transaction(function () use ($db) {
        $db->insert('test_users', [
            'username' => 'callback_user',
            'email' => 'callback@example.com'
        ]);
        
        return 'success';
    });
    
    assert_equals('success', $result, 'Callback должен вернуть результат');
    assert_true($db->exists('SELECT 1 FROM test_users WHERE username = ?', ['callback_user']), 'Запись должна существовать');
    
    return true;
});

test('Транзакция с rollback при исключении', function () use ($db) {
    $countBefore = $db->count('test_users');
    
    try {
        $db->transaction(function () use ($db) {
            $db->insert('test_users', [
                'username' => 'exception_user',
                'email' => 'exception@example.com'
            ]);
            
            throw new Exception('Тестовое исключение');
        });
        return false;
    } catch (Exception $e) {
        // Проверяем, что rollback выполнился
        $countAfter = $db->count('test_users');
        assert_equals($countBefore, $countAfter, 'Rollback должен отменить вставку');
        return true;
    }
});

test('Попытка начать вложенную транзакцию должна выбросить исключение', function () use ($db) {
    try {
        $db->beginTransaction();
        $db->beginTransaction(); // вложенная транзакция
        $db->rollback();
        return false;
    } catch (MySQLTransactionException $e) {
        $db->rollback();
        return strpos($e->getMessage(), 'уже активна') !== false;
    }
});

// ============================================
// РАЗДЕЛ 10: ОЧИСТКА И DDL ОПЕРАЦИИ
// ============================================

section("10. ОЧИСТКА И DDL ОПЕРАЦИИ");

test('Очистка таблицы (truncate)', function () use ($db) {
    // Вставляем тестовые данные
    $db->insert('test_orders', [
        'user_id' => 1,
        'product_id' => 1,
        'quantity' => 5,
        'total_price' => 100.00
    ]);
    
    $countBefore = $db->count('test_orders');
    assert_true($countBefore > 0, 'Должны быть записи перед очисткой');
    
    $db->truncate('test_orders');
    
    $countAfter = $db->count('test_orders');
    assert_equals(0, $countAfter, 'Таблица должна быть пуста после TRUNCATE');
    
    return true;
});

test('Создание временной таблицы через execute', function () use ($db) {
    $db->execute('DROP TABLE IF EXISTS test_temp');
    
    $sql = <<<SQL
    CREATE TEMPORARY TABLE test_temp (
        id INT AUTO_INCREMENT PRIMARY KEY,
        value VARCHAR(100)
    )
    SQL;
    
    $db->execute($sql);
    
    // Вставляем данные
    $db->insert('test_temp', ['value' => 'test']);
    
    $count = $db->count('test_temp');
    assert_equals(1, $count, 'В временной таблице должна быть 1 запись');
    
    return true;
});

// ============================================
// РАЗДЕЛ 11: КЕШИРОВАНИЕ И ПРОИЗВОДИТЕЛЬНОСТЬ
// ============================================

section("11. КЕШИРОВАНИЕ И ПРОИЗВОДИТЕЛЬНОСТЬ");

test('Кеширование prepared statements', function () use ($db) {
    // Выполняем один и тот же запрос несколько раз
    for ($i = 0; $i < 5; $i++) {
        $db->query('SELECT * FROM test_users WHERE id = ?', [1]);
    }
    
    // Если кеширование работает, нет исключений
    return true;
});

test('Очистка кеша prepared statements', function () use ($db) {
    $db->clearStatementCache();
    
    // После очистки запросы должны работать
    $result = $db->query('SELECT * FROM test_users LIMIT 1');
    assert_true(is_array($result), 'Запрос должен работать после очистки кеша');
    
    return true;
});

test('Создание подключения без кеширования', function () use ($config, $logger) {
    $configNoCache = $config;
    $configNoCache['cache_statements'] = false;
    
    $dbNoCache = new MySQL($configNoCache, $logger);
    $result = $dbNoCache->query('SELECT * FROM test_users LIMIT 1');
    
    assert_true(is_array($result), 'Запрос должен работать без кеширования');
    return true;
});

// ============================================
// РАЗДЕЛ 12: ПРОВЕРКА ЛОГИРОВАНИЯ
// ============================================

section("12. ПРОВЕРКА ЛОГИРОВАНИЯ");

test('Проверка создания лог-файла', function () use ($logFilePath) {
    assert_true(file_exists($logFilePath), 'Лог-файл должен существовать');
    assert_true(filesize($logFilePath) > 0, 'Лог-файл не должен быть пустым');
    return true;
});

test('Проверка логирования ошибок', function () use ($db, $logFilePath) {
    try {
        $db->query('SELECT * FROM non_existent_table_xyz');
    } catch (MySQLException $e) {
        // Ошибка ожидаема
    }
    
    $logContent = file_get_contents($logFilePath);
    
    assert_true(strpos($logContent, 'ERROR') !== false, 'Лог должен содержать сообщения об ошибках');
    return true;
});

// ============================================
// РАЗДЕЛ 13: EDGE CASES И СПЕЦИАЛЬНЫЕ СЛУЧАИ
// ============================================

section("13. EDGE CASES И СПЕЦИАЛЬНЫЕ СЛУЧАИ");

test('Работа с пустыми строками', function () use ($db) {
    $id = $db->insert('test_users', [
        'username' => 'empty_email_user',
        'email' => ''
    ]);
    
    assert_true($id > 0, 'Вставка с пустой строкой должна работать');
    
    $user = $db->queryOne('SELECT email FROM test_users WHERE id = ?', [$id]);
    assert_equals('', $user['email'], 'Пустая строка должна сохраниться');
    
    return true;
});

test('Работа с очень длинными строками', function () use ($db) {
    $longString = str_repeat('A', 200);
    
    $id = $db->insert('test_products', [
        'name' => $longString,
        'price' => 99.99
    ]);
    
    $product = $db->queryOne('SELECT name FROM test_products WHERE id = ?', [$id]);
    assert_equals($longString, $product['name'], 'Длинная строка должна сохраниться полностью');
    
    return true;
});

test('Работа с специальными символами и эмодзи', function () use ($db) {
    $specialText = "Тест 测试 🚀 <script>alert('xss')</script>";
    
    $id = $db->insert('test_users', [
        'username' => 'special_chars_user',
        'email' => 'special@test.com',
        'status' => $specialText
    ]);
    
    $user = $db->queryOne('SELECT status FROM test_users WHERE id = ?', [$id]);
    assert_equals($specialText, $user['status'], 'Специальные символы должны сохраниться');
    
    return true;
});

test('Работа с большими числами', function () use ($db) {
    $bigNumber = 99999999.99;
    
    $id = $db->insert('test_users', [
        'username' => 'big_number_user',
        'email' => 'big@test.com',
        'balance' => $bigNumber
    ]);
    
    $user = $db->queryOne('SELECT balance FROM test_users WHERE id = ?', [$id]);
    assert_equals((string)$bigNumber, $user['balance'], 'Большое число должно сохраниться');
    
    return true;
});

test('SQL инъекция защита (prepared statements)', function () use ($db) {
    $maliciousInput = "' OR '1'='1";
    
    $result = $db->query('SELECT * FROM test_users WHERE username = ?', [$maliciousInput]);
    
    assert_equals(0, count($result), 'SQL инъекция должна быть предотвращена');
    return true;
});

// ============================================
// РАЗДЕЛ 14: СЛОЖНЫЕ ЗАПРОСЫ
// ============================================

section("14. СЛОЖНЫЕ ЗАПРОСЫ");

test('JOIN запрос через query', function () use ($db, $userId1) {
    // Сначала создадим заказ
    $db->insert('test_orders', [
        'user_id' => $userId1,
        'product_id' => 1,
        'quantity' => 2,
        'total_price' => 2599.98
    ]);
    
    $sql = <<<SQL
    SELECT 
        u.username,
        p.name as product_name,
        o.quantity,
        o.total_price
    FROM test_orders o
    JOIN test_users u ON o.user_id = u.id
    JOIN test_products p ON o.product_id = p.id
    WHERE u.id = ?
    SQL;
    
    $result = $db->query($sql, [$userId1]);
    
    assert_true(count($result) > 0, 'JOIN запрос должен вернуть результаты');
    assert_true(isset($result[0]['username']), 'Должно быть поле username');
    assert_true(isset($result[0]['product_name']), 'Должно быть поле product_name');
    
    return true;
});

test('Подзапрос (subquery)', function () use ($db) {
    $sql = <<<SQL
    SELECT username, balance
    FROM test_users
    WHERE balance > (SELECT AVG(balance) FROM test_users)
    SQL;
    
    $result = $db->query($sql);
    
    // Результат может быть пустым, главное что запрос выполнился
    assert_true(is_array($result), 'Подзапрос должен выполниться');
    return true;
});

test('GROUP BY с агрегацией', function () use ($db) {
    $sql = <<<SQL
    SELECT 
        category,
        COUNT(*) as product_count,
        AVG(price) as avg_price,
        SUM(stock) as total_stock
    FROM test_products
    WHERE category IS NOT NULL
    GROUP BY category
    ORDER BY product_count DESC
    SQL;
    
    $result = $db->query($sql);
    
    assert_true(count($result) > 0, 'GROUP BY должен вернуть результаты');
    if (count($result) > 0) {
        assert_true(isset($result[0]['product_count']), 'Должно быть поле product_count');
        assert_true(isset($result[0]['avg_price']), 'Должно быть поле avg_price');
    }
    
    return true;
});

// ============================================
// РАЗДЕЛ 15: ОЧИСТКА ПОСЛЕ ТЕСТОВ
// ============================================

section("15. ОЧИСТКА ПОСЛЕ ТЕСТОВ");

test('Удаление тестовых таблиц', function () use ($db) {
    $db->execute('DROP TABLE IF EXISTS test_orders');
    $db->execute('DROP TABLE IF EXISTS test_products');
    $db->execute('DROP TABLE IF EXISTS test_users');
    $db->execute('DROP TABLE IF EXISTS test_unique');
    
    return true;
});

test('Проверка удаления таблиц', function () use ($db) {
    assert_true(!$db->tableExists('test_users'), 'Таблица test_users должна быть удалена');
    assert_true(!$db->tableExists('test_products'), 'Таблица test_products должна быть удалена');
    assert_true(!$db->tableExists('test_orders'), 'Таблица test_orders должна быть удалена');
    
    return true;
});

// ============================================
// ВЫВОД ИТОГОВОЙ СТАТИСТИКИ
// ============================================

printSummary();

// Вывод информации о лог-файле
if ($logger !== null && file_exists($logFilePath)) {
    echo "\n" . COLOR_CYAN . "Лог-файл сохранен: " . COLOR_RESET;
    echo $logFilePath . "\n";
    echo COLOR_YELLOW . "Размер лог-файла: " . round(filesize($logFilePath) / 1024, 2) . " KB" . COLOR_RESET . "\n\n";
}

// Выход с кодом ошибки, если есть проваленные тесты
exit($failedTests > 0 ? 1 : 0);

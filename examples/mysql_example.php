<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use App\Component\MySQL;
use App\Component\Logger;
use App\Component\Exception\ConnectionException;
use App\Component\Exception\DatabaseException;
use App\Component\Exception\TransactionException;

echo "=== Примеры использования класса MySQL ===\n\n";

$logger = new Logger([
    'directory' => __DIR__ . '/../logs',
    'file_name' => 'mysql_example.log',
    'max_files' => 3,
    'max_file_size' => 5,
]);

try {
    $db = new MySQL([
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => (int)(getenv('DB_PORT') ?: 3306),
        'database' => getenv('DB_NAME') ?: 'test_db',
        'username' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
        'persistent' => false,
        'cache_statements' => true,
    ], $logger);
    
    echo "✓ Подключение к БД успешно установлено\n\n";
    
} catch (ConnectionException $e) {
    die("✗ Ошибка подключения к БД: {$e->getMessage()}\n");
} catch (DatabaseException $e) {
    die("✗ Ошибка конфигурации БД: {$e->getMessage()}\n");
}

echo "--- Информация о подключении ---\n";
$info = $db->getConnectionInfo();
echo "Версия сервера: {$info['server_version']}\n";
echo "Версия клиента: {$info['client_version']}\n";
echo "Статус соединения: {$info['connection_status']}\n";
echo "Активная транзакция: " . ($info['in_transaction'] ? 'Да' : 'Нет') . "\n\n";

echo "--- Проверка подключения (ping) ---\n";
if ($db->ping()) {
    echo "✓ Подключение активно\n\n";
} else {
    echo "✗ Подключение неактивно\n\n";
}

try {
    echo "--- Создание тестовой таблицы ---\n";
    $db->execute('DROP TABLE IF EXISTS test_users');
    $db->execute('
        CREATE TABLE test_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            status ENUM("active", "inactive") DEFAULT "active",
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');
    echo "✓ Таблица test_users создана\n\n";
    
} catch (DatabaseException $e) {
    echo "✗ Ошибка создания таблицы: {$e->getMessage()}\n\n";
}

echo "--- INSERT: Вставка одной записи ---\n";
try {
    $userId = $db->insert(
        'INSERT INTO test_users (name, email, status) VALUES (?, ?, ?)',
        ['Иван Иванов', 'ivan@example.com', 'active']
    );
    echo "✓ Создан пользователь с ID: {$userId}\n\n";
} catch (DatabaseException $e) {
    echo "✗ Ошибка вставки: {$e->getMessage()}\n\n";
}

echo "--- INSERT BATCH: Массовая вставка ---\n";
try {
    $users = [
        ['name' => 'Петр Петров', 'email' => 'petr@example.com', 'status' => 'active'],
        ['name' => 'Мария Сидорова', 'email' => 'maria@example.com', 'status' => 'active'],
        ['name' => 'Алексей Смирнов', 'email' => 'alexey@example.com', 'status' => 'inactive'],
        ['name' => 'Ольга Козлова', 'email' => 'olga@example.com', 'status' => 'active'],
    ];
    
    $inserted = $db->insertBatch('test_users', $users);
    echo "✓ Вставлено пользователей: {$inserted}\n\n";
} catch (DatabaseException | TransactionException $e) {
    echo "✗ Ошибка массовой вставки: {$e->getMessage()}\n\n";
}

echo "--- SELECT: Получение всех записей ---\n";
try {
    $allUsers = $db->query('SELECT * FROM test_users ORDER BY id');
    echo "✓ Найдено пользователей: " . count($allUsers) . "\n";
    foreach ($allUsers as $user) {
        echo "  - [{$user['id']}] {$user['name']} ({$user['email']}) - {$user['status']}\n";
    }
    echo "\n";
} catch (DatabaseException $e) {
    echo "✗ Ошибка запроса: {$e->getMessage()}\n\n";
}

echo "--- SELECT ONE: Получение одной записи ---\n";
try {
    $user = $db->queryOne('SELECT * FROM test_users WHERE status = ? LIMIT 1', ['active']);
    if ($user !== null) {
        echo "✓ Найден пользователь: {$user['name']} ({$user['email']})\n\n";
    } else {
        echo "✗ Пользователь не найден\n\n";
    }
} catch (DatabaseException $e) {
    echo "✗ Ошибка запроса: {$e->getMessage()}\n\n";
}

echo "--- SCALAR: Получение скалярного значения ---\n";
try {
    $totalCount = $db->queryScalar('SELECT COUNT(*) FROM test_users');
    $activeCount = $db->queryScalar('SELECT COUNT(*) FROM test_users WHERE status = ?', ['active']);
    $maxId = $db->queryScalar('SELECT MAX(id) FROM test_users');
    
    echo "✓ Всего пользователей: {$totalCount}\n";
    echo "✓ Активных пользователей: {$activeCount}\n";
    echo "✓ Максимальный ID: {$maxId}\n\n";
} catch (DatabaseException $e) {
    echo "✗ Ошибка запроса: {$e->getMessage()}\n\n";
}

echo "--- UPDATE: Обновление записей ---\n";
try {
    $updated = $db->update(
        'UPDATE test_users SET status = ? WHERE email LIKE ?',
        ['inactive', '%@example.com']
    );
    echo "✓ Обновлено записей: {$updated}\n\n";
} catch (DatabaseException $e) {
    echo "✗ Ошибка обновления: {$e->getMessage()}\n\n";
}

echo "--- TRANSACTION: Ручное управление ---\n";
try {
    $db->beginTransaction();
    echo "✓ Транзакция начата\n";
    
    $newUserId = $db->insert(
        'INSERT INTO test_users (name, email, status) VALUES (?, ?, ?)',
        ['Транзакционный Пользователь', 'transaction@example.com', 'active']
    );
    echo "✓ Вставлена запись с ID: {$newUserId}\n";
    
    $db->update(
        'UPDATE test_users SET name = ? WHERE id = ?',
        ['Обновленный Пользователь', $newUserId]
    );
    echo "✓ Запись обновлена\n";
    
    $db->commit();
    echo "✓ Транзакция подтверждена\n\n";
    
} catch (DatabaseException | TransactionException $e) {
    if ($db->inTransaction()) {
        $db->rollback();
        echo "✗ Транзакция откачена\n";
    }
    echo "✗ Ошибка транзакции: {$e->getMessage()}\n\n";
}

echo "--- TRANSACTION: Автоматическое управление ---\n";
try {
    $result = $db->transaction(function() use ($db) {
        $id1 = $db->insert(
            'INSERT INTO test_users (name, email, status) VALUES (?, ?, ?)',
            ['Авто Транзакция 1', 'auto1@example.com', 'active']
        );
        
        $id2 = $db->insert(
            'INSERT INTO test_users (name, email, status) VALUES (?, ?, ?)',
            ['Авто Транзакция 2', 'auto2@example.com', 'active']
        );
        
        return ['id1' => $id1, 'id2' => $id2];
    });
    
    echo "✓ Автоматическая транзакция выполнена успешно\n";
    echo "✓ Созданы записи: ID {$result['id1']} и ID {$result['id2']}\n\n";
    
} catch (\Throwable $e) {
    echo "✗ Ошибка автоматической транзакции: {$e->getMessage()}\n\n";
}

echo "--- DELETE: Удаление записей ---\n";
try {
    $deleted = $db->delete('DELETE FROM test_users WHERE status = ?', ['inactive']);
    echo "✓ Удалено записей: {$deleted}\n\n";
} catch (DatabaseException $e) {
    echo "✗ Ошибка удаления: {$e->getMessage()}\n\n";
}

echo "--- Финальная статистика ---\n";
try {
    $finalCount = $db->queryScalar('SELECT COUNT(*) FROM test_users');
    echo "✓ Осталось пользователей в таблице: {$finalCount}\n\n";
} catch (DatabaseException $e) {
    echo "✗ Ошибка запроса: {$e->getMessage()}\n\n";
}

echo "--- Очистка ---\n";
try {
    $db->execute('DROP TABLE IF EXISTS test_users');
    echo "✓ Тестовая таблица удалена\n\n";
} catch (DatabaseException $e) {
    echo "✗ Ошибка очистки: {$e->getMessage()}\n\n";
}

echo "--- Информация о кеше ---\n";
$db->clearStatementCache();
echo "✓ Кеш prepared statements очищен\n\n";

echo "=== Завершение примеров ===\n";

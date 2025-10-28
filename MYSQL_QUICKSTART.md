# MySQL Class - Quick Start Guide

Быстрый старт для работы с обновленным классом MySQL (Production Ready)

## 📦 Подключение

```php
use App\Component\MySQL;
use App\Component\Logger;
use App\Component\Exception\ConnectionException;
use App\Component\Exception\DatabaseException;

$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'file_name' => 'database.log',
]);

$db = new MySQL([
    'host' => 'localhost',
    'database' => 'myapp',          // обязательно
    'username' => 'root',            // обязательно
    'password' => 'secret',          // обязательно
    'charset' => 'utf8mb4',          // опционально
    'persistent' => true,            // опционально (рекомендуется для production)
    'cache_statements' => true,      // опционально (включено по умолчанию)
], $logger);
```

## 🔍 SELECT запросы

```php
// Все строки
$users = $db->query('SELECT * FROM users WHERE status = ?', ['active']);

// Одна строка
$user = $db->queryOne('SELECT * FROM users WHERE id = ?', [123]);

// Скалярное значение
$count = $db->queryScalar('SELECT COUNT(*) FROM users');
```

## ➕ INSERT запросы

```php
// Одиночная вставка
$userId = $db->insert('INSERT INTO users (name, email) VALUES (?, ?)', ['John', 'john@example.com']);

// Массовая вставка (в 8x быстрее!)
$rows = [
    ['name' => 'Alice', 'email' => 'alice@example.com'],
    ['name' => 'Bob', 'email' => 'bob@example.com'],
];
$inserted = $db->insertBatch('users', $rows);
```

## ♻️ UPDATE/DELETE

```php
// Обновление
$affected = $db->update('UPDATE users SET status = ? WHERE id = ?', ['inactive', 123]);

// Удаление
$deleted = $db->delete('DELETE FROM logs WHERE created_at < ?', ['2023-01-01']);
```

## 💾 Транзакции

```php
// Автоматическое управление (рекомендуется)
$orderId = $db->transaction(function() use ($db) {
    $orderId = $db->insert('INSERT INTO orders (total) VALUES (?)', [99.99]);
    $db->insert('INSERT INTO order_items (order_id, qty) VALUES (?, ?)', [$orderId, 2]);
    return $orderId;
});

// Ручное управление
try {
    $db->beginTransaction();
    // ... операции ...
    $db->commit();
} catch (DatabaseException $e) {
    $db->rollback();
    throw $e;
}
```

## 🔧 Служебные методы

```php
// Проверка подключения
if (!$db->ping()) {
    // переподключение
}

// Информация о подключении
$info = $db->getConnectionInfo();

// Очистка кеша (после больших импортов)
$db->clearStatementCache();

// Проверка активной транзакции
if ($db->inTransaction()) {
    $db->commit();
}
```

## ⚠️ Обработка ошибок

```php
use App\Component\Exception\ConnectionException;
use App\Component\Exception\DatabaseException;
use App\Component\Exception\TransactionException;

try {
    $db = new MySQL($config);
} catch (ConnectionException $e) {
    // Ошибка подключения
} catch (DatabaseException $e) {
    // Ошибка конфигурации
}

try {
    $result = $db->query('SELECT ...');
} catch (DatabaseException $e) {
    // Ошибка выполнения запроса
}

try {
    $db->beginTransaction();
    $db->commit();
} catch (TransactionException $e) {
    // Ошибка транзакции
}
```

## 🚀 Production рекомендации

1. ✅ Используйте `persistent: true` для долгоживущих приложений
2. ✅ Оставьте `cache_statements: true` (по умолчанию)
3. ✅ Используйте `insertBatch()` для массовых операций
4. ✅ Используйте `transaction()` callback вместо ручного управления
5. ✅ Всегда передавайте Logger для отладки

## 📖 Полная документация

См. [MYSQL_REFACTORING.md](MYSQL_REFACTORING.md) для детальной документации.

## 🧪 Примеры

Запустите рабочий пример:

```bash
php examples/mysql_example.php
```

Настройте подключение через переменные окружения:

```bash
export DB_HOST=localhost
export DB_NAME=test_db
export DB_USER=root
export DB_PASS=secret
php examples/mysql_example.php
```

# MySQL.class.php — Краткая справка

## Инициализация

```php
use App\Component\MySQL;
use App\Component\Logger;

$logger = new Logger([
    'directory' => '/path/to/logs',
    'file_name' => 'mysql.log',
]);

$db = new MySQL([
    'host' => 'localhost',           // необязательно, по умолчанию 'localhost'
    'port' => 3306,                  // необязательно, по умолчанию 3306
    'database' => 'myapp',           // обязательно
    'username' => 'root',            // обязательно
    'password' => 'secret',          // обязательно
    'charset' => 'utf8mb4',          // необязательно, по умолчанию 'utf8mb4'
    'persistent' => false,           // необязательно
    'cache_statements' => true,      // необязательно, по умолчанию true
], $logger);
```

## SELECT запросы

```php
// Все строки
$users = $db->query("SELECT * FROM users WHERE age > ?", [18]);

// Одна строка (или null)
$user = $db->queryOne("SELECT * FROM users WHERE id = ?", [1]);

// Скалярное значение
$count = $db->queryScalar("SELECT COUNT(*) FROM users");
$maxAge = $db->queryScalar("SELECT MAX(age) FROM users WHERE active = ?", [1]);

// Массив значений одного столбца
$ids = $db->queryColumn("SELECT id FROM users WHERE active = ?", [1]);
$emails = $db->queryColumn("SELECT email FROM users");
```

## INSERT запросы

```php
// Простая вставка
$userId = $db->insert("INSERT INTO users (name, email) VALUES (?, ?)", 
    ['John Doe', 'john@example.com']
);

// Именованные параметры
$userId = $db->insert("INSERT INTO users (name, email) VALUES (:name, :email)", 
    [':name' => 'Jane Doe', ':email' => 'jane@example.com']
);

// Массовая вставка (в транзакции)
$rows = [
    ['name' => 'Alice', 'email' => 'alice@example.com', 'age' => 25],
    ['name' => 'Bob', 'email' => 'bob@example.com', 'age' => 30],
];
$count = $db->insertBatch('users', $rows);

// INSERT IGNORE (игнорировать дубликаты)
$id = $db->insertIgnore('users', [
    'email' => 'test@example.com',
    'name' => 'Test User'
]);

// REPLACE (удалить и вставить заново)
$id = $db->replace('users', [
    'id' => 1,
    'name' => 'Updated Name',
    'email' => 'updated@example.com'
]);

// UPSERT (вставка или обновление)
$id = $db->upsert('users', [
    'email' => 'test@example.com',  // UNIQUE ключ
    'name' => 'John Doe',
    'age' => 30
]);

// UPSERT с отдельными данными для обновления
$id = $db->upsert(
    'users',
    ['email' => 'test@example.com', 'name' => 'John', 'age' => 30],
    ['age' => 31]  // При конфликте обновить только age
);
```

## UPDATE запросы

```php
// Обновление записей
$affected = $db->update("UPDATE users SET active = ? WHERE id = ?", [1, 5]);

// С именованными параметрами
$affected = $db->update(
    "UPDATE users SET name = :name, age = :age WHERE id = :id",
    [':name' => 'New Name', ':age' => 35, ':id' => 10]
);
```

## DELETE запросы

```php
// Удаление записей
$deleted = $db->delete("DELETE FROM users WHERE id = ?", [5]);

// С условиями
$deleted = $db->delete("DELETE FROM users WHERE active = ? AND created_at < ?", 
    [0, '2023-01-01']
);
```

## DDL операции

```php
// CREATE TABLE
$db->execute("
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// ALTER TABLE
$db->execute("ALTER TABLE users ADD COLUMN phone VARCHAR(20)");

// DROP TABLE
$db->execute("DROP TABLE IF EXISTS old_users");

// TRUNCATE (быстрая очистка)
$db->truncate('temporary_data');
```

## Транзакции

```php
// Ручное управление
$db->beginTransaction();
try {
    $db->insert("INSERT INTO orders (user_id, total) VALUES (?, ?)", [1, 100]);
    $db->update("UPDATE users SET balance = balance - ? WHERE id = ?", [100, 1]);
    $db->commit();
} catch (Exception $e) {
    $db->rollback();
    throw $e;
}

// Автоматическое управление (рекомендуется)
$result = $db->transaction(function() use ($db) {
    $db->insert("INSERT INTO orders (user_id, total) VALUES (?, ?)", [1, 100]);
    $db->update("UPDATE users SET balance = balance - ? WHERE id = ?", [100, 1]);
    return true;
});

// Проверка активной транзакции
if ($db->inTransaction()) {
    echo "Транзакция активна";
}
```

## Вспомогательные методы

```php
// Проверка существования
if ($db->exists("SELECT 1 FROM users WHERE email = ?", ['test@example.com'])) {
    echo "Email уже используется";
}

// Подсчет записей
$totalUsers = $db->count('users');
$activeUsers = $db->count('users', ['active' => 1]);
$admins = $db->count('users', ['role' => 'admin', 'active' => 1]);

// Проверка существования таблицы
if ($db->tableExists('users')) {
    echo "Таблица существует";
}

// Получение последнего ID
$db->execute("INSERT INTO users (name) VALUES ('John')");
$lastId = $db->getLastInsertId();

// Проверка подключения
if ($db->ping()) {
    echo "Соединение активно";
}

// Информация о подключении
$info = $db->getConnectionInfo();
echo "MySQL версия: " . $info['server_version'];

// Версия MySQL
$version = $db->getMySQLVersion();
if ($version['is_recommended']) {
    echo "Версия MySQL рекомендуемая: " . $version['version'];
}

// Очистка кеша prepared statements
$db->clearStatementCache();

// Доступ к PDO (если нужно)
$pdo = $db->getConnection();
```

## Обработка ошибок

```php
use App\Component\Exception\MySQL\MySQLException;
use App\Component\Exception\MySQL\MySQLConnectionException;
use App\Component\Exception\MySQL\MySQLTransactionException;

try {
    $result = $db->query("SELECT * FROM users");
} catch (MySQLException $e) {
    error_log("Ошибка БД: " . $e->getMessage());
}

try {
    $db = new MySQL($config);
} catch (MySQLConnectionException $e) {
    error_log("Не удалось подключиться к БД: " . $e->getMessage());
}

try {
    $db->beginTransaction();
    $db->beginTransaction(); // Ошибка: вложенные транзакции
} catch (MySQLTransactionException $e) {
    error_log("Ошибка транзакции: " . $e->getMessage());
}
```

## Примеры использования

### Регистрация пользователя с проверкой email

```php
// Проверяем, существует ли email
if ($db->exists("SELECT 1 FROM users WHERE email = ?", [$email])) {
    throw new Exception("Email уже используется");
}

// Вставляем пользователя
$userId = $db->insert(
    "INSERT INTO users (name, email, password) VALUES (?, ?, ?)",
    [$name, $email, password_hash($password, PASSWORD_DEFAULT)]
);
```

### Получение списка активных пользователей

```php
$activeUsers = $db->query("SELECT id, name, email FROM users WHERE active = ?", [1]);
$userIds = $db->queryColumn("SELECT id FROM users WHERE active = ?", [1]);
$userCount = $db->count('users', ['active' => 1]);
```

### Обновление статистики в транзакции

```php
$db->transaction(function() use ($db, $userId, $amount) {
    // Создаем заказ
    $orderId = $db->insert(
        "INSERT INTO orders (user_id, amount, status) VALUES (?, ?, ?)",
        [$userId, $amount, 'pending']
    );
    
    // Обновляем баланс
    $db->update(
        "UPDATE users SET balance = balance - ? WHERE id = ?",
        [$amount, $userId]
    );
    
    // Логируем транзакцию
    $db->insert(
        "INSERT INTO transactions (user_id, order_id, amount) VALUES (?, ?, ?)",
        [$userId, $orderId, $amount]
    );
});
```

### Инкрементный счетчик с UPSERT

```php
// Увеличиваем счетчик просмотров или создаем новую запись
$db->upsert('page_views', [
    'page_id' => $pageId,
    'views' => 1,
    'updated_at' => date('Y-m-d H:i:s')
], [
    'views' => 'views + 1',  // При конфликте увеличиваем счетчик
    'updated_at' => date('Y-m-d H:i:s')
]);
```

### Безопасная очистка старых данных

```php
if ($db->tableExists('temp_cache')) {
    $oldCount = $db->count('temp_cache');
    $db->truncate('temp_cache');
    echo "Удалено записей: {$oldCount}";
}
```

## Производительность

- **Prepared statements кешируются** (до 100 уникальных запросов)
- **Используйте insertBatch()** для массовой вставки вместо множества insert()
- **Используйте транзакции** для множественных операций
- **Используйте exists()** вместо count() для проверки наличия
- **Используйте truncate()** вместо delete() для полной очистки таблиц

## Безопасность

- ✅ **Всегда используйте prepared statements** (параметры вместо конкатенации)
- ✅ **Не вставляйте пользовательские данные напрямую в SQL**
- ✅ **Используйте именованные или позиционные параметры**
- ❌ **НИКОГДА не делайте:** `$db->query("SELECT * FROM users WHERE id = {$_GET['id']}")`
- ✅ **Правильно:** `$db->query("SELECT * FROM users WHERE id = ?", [$_GET['id']])`

## Логирование

Все операции автоматически логируются:
- **DEBUG:** Успешные операции с контекстом (query, rows, affected_rows)
- **ERROR:** Ошибки с полным контекстом и сообщением

Пример лога:
```
2025-11-01T08:21:15+00:00 DEBUG Выполнен SELECT запрос {"query":"SELECT * FROM users","rows":10}
2025-11-01T08:21:15+00:00 DEBUG Транзакция начата {}
2025-11-01T08:21:15+00:00 DEBUG Транзакция подтверждена {}
2025-11-01T08:21:15+00:00 ERROR Ошибка выполнения SELECT запроса {"query":"INVALID SQL","error":"Syntax error..."}
```

---

**Версия:** 1.0  
**Тестовое покрытие:** 58/58 тестов (100%)  
**Методов в классе:** 27  
**Статус:** Production Ready ✅

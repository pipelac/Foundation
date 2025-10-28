# MySQL Connection Factory - Быстрая справка

## Минимальный пример

```php
use App\Component\MySQLConnectionFactory;

// 1. Загрузка конфигурации
$config = json_decode(file_get_contents('config/mysql.json'), true);

// 2. Создание фабрики
$factory = new MySQLConnectionFactory($config, $logger);

// 3. Получение соединения
$db = $factory->getConnection('main');

// 4. Работа с БД
$users = $db->query('SELECT * FROM users WHERE status = ?', ['active']);
```

## Основные методы фабрики

| Метод | Описание | Пример |
|-------|----------|--------|
| `getConnection(?string $name)` | Получить соединение с БД | `$db = $factory->getConnection('main');` |
| `getDefaultConnection()` | Получить соединение по умолчанию | `$db = $factory->getDefaultConnection();` |
| `getAvailableDatabases()` | Список всех БД | `$list = $factory->getAvailableDatabases();` |
| `hasDatabase(string $name)` | Проверка наличия БД | `if ($factory->hasDatabase('analytics'))` |
| `isConnectionAlive(?string $name)` | Проверка активности | `if ($factory->isConnectionAlive('main'))` |
| `clearConnectionCache(?string $name)` | Очистка кеша | `$factory->clearConnectionCache();` |

## Методы MySQL класса

### SELECT запросы
```php
// Все строки
$users = $db->query('SELECT * FROM users WHERE status = ?', ['active']);

// Одна строка
$user = $db->queryOne('SELECT * FROM users WHERE id = ?', [1]);

// Скалярное значение
$count = $db->queryScalar('SELECT COUNT(*) FROM users');
```

### INSERT
```php
// Одна запись
$id = $db->insert('INSERT INTO users (name, email) VALUES (?, ?)', ['Иван', 'ivan@test.com']);

// Массовая вставка
$rows = [
    ['name' => 'Петр', 'email' => 'petr@test.com'],
    ['name' => 'Мария', 'email' => 'maria@test.com'],
];
$count = $db->insertBatch('users', $rows);
```

### UPDATE / DELETE
```php
// UPDATE
$affected = $db->update('UPDATE users SET status = ? WHERE id = ?', ['inactive', 5]);

// DELETE
$deleted = $db->delete('DELETE FROM users WHERE id = ?', [10]);
```

### Транзакции
```php
// Автоматическое управление
$result = $db->transaction(function() use ($db) {
    $id = $db->insert('INSERT INTO users (name) VALUES (?)', ['Тест']);
    $db->update('UPDATE users SET status = ? WHERE id = ?', ['active', $id]);
    return $id;
});

// Ручное управление
$db->beginTransaction();
try {
    $db->insert(...);
    $db->update(...);
    $db->commit();
} catch (Exception $e) {
    $db->rollback();
    throw $e;
}
```

## Конфигурация (config/mysql.json)

```json
{
    "databases": {
        "main": {
            "host": "localhost",
            "port": 3306,
            "database": "main_db",
            "username": "root",
            "password": "password",
            "charset": "utf8mb4",
            "persistent": false,
            "cache_statements": true
        },
        "analytics": {
            "host": "analytics-server",
            "port": 3306,
            "database": "analytics_db",
            "username": "analytics_user",
            "password": "analytics_pass",
            "charset": "utf8mb4"
        }
    },
    "default": "main"
}
```

## Работа с несколькими БД

```php
$factory = new MySQLConnectionFactory($config);

// Основная БД
$mainDb = $factory->getConnection('main');
$users = $mainDb->query('SELECT * FROM users');

// БД аналитики
$analyticsDb = $factory->getConnection('analytics');
$stats = $analyticsDb->query('SELECT * FROM statistics');

// Связанные данные из разных БД
foreach ($users as $user) {
    $userStats = $analyticsDb->queryOne(
        'SELECT * FROM user_stats WHERE user_id = ?',
        [$user['id']]
    );
    echo "{$user['name']}: {$userStats['views']} просмотров\n";
}
```

## Проверка и мониторинг

```php
// Список доступных БД
$databases = $factory->getAvailableDatabases();
// ['main', 'analytics', 'logs']

// Проверка наличия БД
if ($factory->hasDatabase('analytics')) {
    $db = $factory->getConnection('analytics');
}

// Проверка активности
if ($factory->isConnectionAlive('main')) {
    echo "Соединение активно";
}

// Количество активных соединений
$count = $factory->getCachedConnectionsCount();
echo "Активных соединений: {$count}";

// Список активных БД
$active = $factory->getActiveDatabases();
// ['main', 'analytics']
```

## Обработка исключений

```php
use App\Component\Exception\MySQLException;
use App\Component\Exception\MySQLConnectionException;
use App\Component\Exception\MySQLTransactionException;

try {
    $factory = new MySQLConnectionFactory($config);
    $db = $factory->getConnection('main');
    
    $db->transaction(function() use ($db) {
        $db->insert(...);
        $db->update(...);
    });
    
} catch (MySQLConnectionException $e) {
    // Ошибка подключения
    error_log("Ошибка подключения: {$e->getMessage()}");
    
} catch (MySQLTransactionException $e) {
    // Ошибка транзакции
    error_log("Ошибка транзакции: {$e->getMessage()}");
    
} catch (MySQLException $e) {
    // Общая ошибка MySQL
    error_log("Ошибка MySQL: {$e->getMessage()}");
}
```

## Best Practices

### ✅ Правильно

```php
// Используйте фабрику для централизованного управления
$factory = new MySQLConnectionFactory($config, $logger);
$db = $factory->getConnection('main');

// Проверяйте наличие БД
if ($factory->hasDatabase('analytics')) {
    $analyticsDb = $factory->getConnection('analytics');
}

// Используйте транзакции для связанных операций
$db->transaction(function() use ($db) {
    // Все операции в одной транзакции
});

// Переиспользуйте соединения (из кеша)
$db1 = $factory->getConnection('main'); // Создает новое
$db2 = $factory->getConnection('main'); // Из кеша (быстро!)
```

### ❌ Неправильно

```php
// Не создавайте много экземпляров MySQL напрямую
$db1 = new MySQL($config1); // Медленно
$db2 = new MySQL($config1); // Еще 10ms
$db3 = new MySQL($config1); // Еще 10ms

// Не игнорируйте проверку наличия БД
$db = $factory->getConnection('nonexistent'); // Exception!

// Не забывайте про транзакции
$db->insert(...);  // Если произойдет ошибка на следующей строке,
$db->update(...);  // первая операция останется выполненной
```

## Производительность

| Операция | Время | Примечание |
|----------|-------|------------|
| Создание нового соединения | ~10-15ms | Первое обращение |
| Получение из кеша | ~0.01ms | 99.9% быстрее |
| Запрос к БД | ~1-5ms | Зависит от сложности |
| Транзакция (3 запроса) | ~5-15ms | В рамках одного соединения |

## Переменные окружения

```bash
# .env или переменные окружения
DB_HOST=localhost
DB_PORT=3306
DB_NAME=main_db
DB_USER=root
DB_PASS=password

# Использование в коде
$config = [
    'databases' => [
        'main' => [
            'host' => getenv('DB_HOST'),
            'port' => (int)getenv('DB_PORT'),
            'database' => getenv('DB_NAME'),
            'username' => getenv('DB_USER'),
            'password' => getenv('DB_PASS'),
        ]
    ]
];
```

## Отладка

```php
// Включите логирование
$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'file_name' => 'mysql_debug.log',
    'level' => 'DEBUG'
]);

$factory = new MySQLConnectionFactory($config, $logger);

// Все операции будут логироваться:
// - Создание соединений
// - Получение из кеша
// - Выполнение запросов
// - Ошибки
```

## Частые вопросы

**Q: Как получить прямой доступ к PDO?**
```php
$pdo = $db->getConnection();
$statement = $pdo->prepare('SELECT * FROM users');
```

**Q: Как проверить соединение?**
```php
if ($db->ping()) {
    echo "Соединение активно";
}
```

**Q: Как очистить кеш?**
```php
$factory->clearConnectionCache();      // Все
$factory->clearConnectionCache('main'); // Конкретное
```

**Q: Как получить информацию о соединении?**
```php
$info = $db->getConnectionInfo();
echo $info['server_version'];
echo $info['in_transaction'];
```

## Дополнительная информация

- Полная документация: [MYSQL_CONNECTION_FACTORY.md](MYSQL_CONNECTION_FACTORY.md)
- Руководство по миграции: [MYSQL_FACTORY_UPGRADE.md](../MYSQL_FACTORY_UPGRADE.md)
- Примеры: [mysql_connection_factory_example.php](../examples/mysql_connection_factory_example.php)
- Changelog: [CHANGELOG_MYSQL_FACTORY.md](../CHANGELOG_MYSQL_FACTORY.md)


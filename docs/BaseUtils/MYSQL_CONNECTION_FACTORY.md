# MySQLConnectionFactory - Фабрика соединений MySQL с кешированием

## Обзор

`MySQLConnectionFactory` - это паттерн фабрики для управления множественными соединениями с базами данных MySQL с автоматическим кешированием. Класс обеспечивает эффективное переиспользование соединений и поддержку параллельной работы с несколькими БД.

## Основные возможности

✅ **Кеширование соединений** - соединение создается только один раз и переиспользуется  
✅ **Множественные БД** - поддержка подключения к нескольким БД одновременно  
✅ **Ленивая инициализация** - соединение создается только при первом обращении  
✅ **Строгая типизация PHP 8.1+** - полная типобезопасность  
✅ **PHPDoc на русском** - детальная документация всех методов  
✅ **Обработка исключений** - специализированные исключения для каждого типа ошибок  
✅ **Логирование** - интеграция с Logger для отладки и мониторинга  

## Требования

- PHP 8.1 или выше
- Расширение PDO с драйвером MySQL
- MySQL 5.7+ / MariaDB 10.2+

## Быстрый старт

### 1. Конфигурация

Создайте файл конфигурации `config/mysql.json`:

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
            "host": "analytics-server.local",
            "port": 3306,
            "database": "analytics_db",
            "username": "analytics_user",
            "password": "analytics_pass",
            "charset": "utf8mb4",
            "persistent": false,
            "cache_statements": true
        }
    },
    "default": "main"
}
```

### 2. Инициализация фабрики

```php
<?php

use App\Component\MySQLConnectionFactory;
use App\Component\Logger;

// Загрузка конфигурации
$config = json_decode(file_get_contents('config/mysql.json'), true);

// Опциональный логгер
$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'file_name' => 'mysql.log'
]);

// Создание фабрики
$factory = new MySQLConnectionFactory($config, $logger);
```

### 3. Получение соединений

```php
// Соединение по умолчанию
$mainDb = $factory->getDefaultConnection();
$users = $mainDb->query('SELECT * FROM users');

// Именованные соединения
$analyticsDb = $factory->getConnection('analytics');
$stats = $analyticsDb->query('SELECT * FROM statistics WHERE date = ?', [date('Y-m-d')]);

// Повторное получение соединения (из кеша)
$mainDb2 = $factory->getConnection('main');
// $mainDb2 === $mainDb (true - одно и то же соединение)
```

## Конфигурация баз данных

### Структура конфигурации

| Параметр | Тип | Обязательный | Описание |
|----------|-----|--------------|----------|
| `databases` | object | Да | Объект с конфигурациями всех БД |
| `default` | string | Нет | Имя БД по умолчанию (первая из списка, если не указано) |

### Параметры каждой БД

| Параметр | Тип | Обязательный | По умолчанию | Описание |
|----------|-----|--------------|--------------|----------|
| `host` | string | Нет | `localhost` | Адрес сервера MySQL |
| `port` | int | Нет | `3306` | Порт MySQL |
| `database` | string | Да | - | Имя базы данных |
| `username` | string | Да | - | Имя пользователя |
| `password` | string | Да | - | Пароль пользователя |
| `charset` | string | Нет | `utf8mb4` | Кодировка соединения |
| `persistent` | bool | Нет | `false` | Использовать персистентное соединение |
| `cache_statements` | bool | Нет | `true` | Кешировать prepared statements |
| `options` | object | Нет | `{}` | Дополнительные опции PDO |

## API методов

### Основные методы

#### `getConnection(?string $databaseName = null): MySQL`

Получает соединение с указанной базой данных.

```php
// Получение соединения с БД по умолчанию
$db = $factory->getConnection();

// Получение соединения с конкретной БД
$analyticsDb = $factory->getConnection('analytics');
```

**Параметры:**
- `$databaseName` - имя БД из конфигурации (null = БД по умолчанию)

**Возвращает:** объект `MySQL` с активным соединением

**Исключения:**
- `MySQLException` - если БД не найдена в конфигурации
- `MySQLConnectionException` - если не удалось подключиться к БД

---

#### `getDefaultConnection(): MySQL`

Получает соединение с базой данных по умолчанию.

```php
$db = $factory->getDefaultConnection();
```

---

#### `getAvailableDatabases(): array`

Возвращает список всех доступных имен баз данных.

```php
$databases = $factory->getAvailableDatabases();
// ['main', 'analytics', 'logs']
```

---

#### `getDefaultDatabaseName(): string`

Возвращает имя базы данных по умолчанию.

```php
$defaultDb = $factory->getDefaultDatabaseName();
// 'main'
```

---

#### `hasDatabase(string $databaseName): bool`

Проверяет наличие конфигурации для указанной БД.

```php
if ($factory->hasDatabase('analytics')) {
    $db = $factory->getConnection('analytics');
}
```

---

#### `isConnectionAlive(?string $databaseName = null): bool`

Проверяет активность соединения с БД.

```php
if ($factory->isConnectionAlive('main')) {
    echo "Соединение активно";
}
```

---

#### `clearConnectionCache(?string $databaseName = null): void`

Очищает кеш соединений.

```php
// Очистить все соединения
$factory->clearConnectionCache();

// Очистить конкретное соединение
$factory->clearConnectionCache('analytics');
```

---

#### `getCachedConnectionsCount(): int`

Возвращает количество активных закешированных соединений.

```php
$count = $factory->getCachedConnectionsCount();
echo "Активных соединений: {$count}";
```

---

#### `getActiveDatabases(): array`

Возвращает список имен БД с активными соединениями.

```php
$active = $factory->getActiveDatabases();
// ['main', 'analytics']
```

## Примеры использования

### Пример 1: Простое использование

```php
$factory = new MySQLConnectionFactory($config);

// Работа с основной БД
$mainDb = $factory->getConnection('main');
$users = $mainDb->query('SELECT * FROM users WHERE status = ?', ['active']);

foreach ($users as $user) {
    echo "User: {$user['name']} ({$user['email']})\n";
}
```

### Пример 2: Работа с несколькими БД одновременно

```php
$factory = new MySQLConnectionFactory($config);

// Основная БД - пользователи
$mainDb = $factory->getConnection('main');
$users = $mainDb->query('SELECT id, name FROM users LIMIT 10');

// БД аналитики - статистика
$analyticsDb = $factory->getConnection('analytics');

foreach ($users as $user) {
    // Получаем статистику для каждого пользователя из другой БД
    $stats = $analyticsDb->queryOne(
        'SELECT views, clicks FROM user_stats WHERE user_id = ?',
        [$user['id']]
    );
    
    echo "{$user['name']}: {$stats['views']} просмотров, {$stats['clicks']} кликов\n";
}
```

### Пример 3: Транзакции в разных БД

```php
$factory = new MySQLConnectionFactory($config);

// Транзакция в основной БД
$mainDb = $factory->getConnection('main');
$mainDb->transaction(function() use ($mainDb) {
    $userId = $mainDb->insert(
        'INSERT INTO users (name, email) VALUES (?, ?)',
        ['Иван Иванов', 'ivan@example.com']
    );
    
    $mainDb->insert(
        'INSERT INTO user_profiles (user_id, bio) VALUES (?, ?)',
        [$userId, 'Программист']
    );
});

// Независимая транзакция в БД логов
$logsDb = $factory->getConnection('logs');
$logsDb->transaction(function() use ($logsDb) {
    $logsDb->insert(
        'INSERT INTO audit_log (action, timestamp) VALUES (?, NOW())',
        ['user_created']
    );
});
```

### Пример 4: Batch операции

```php
$factory = new MySQLConnectionFactory($config);
$mainDb = $factory->getConnection('main');

// Массовая вставка данных
$users = [
    ['name' => 'Иван', 'email' => 'ivan@test.com'],
    ['name' => 'Петр', 'email' => 'petr@test.com'],
    ['name' => 'Мария', 'email' => 'maria@test.com'],
];

$inserted = $mainDb->insertBatch('users', $users);
echo "Вставлено {$inserted} пользователей\n";
```

### Пример 5: Мониторинг соединений

```php
$factory = new MySQLConnectionFactory($config, $logger);

// Получаем соединения
$factory->getConnection('main');
$factory->getConnection('analytics');

// Статистика
echo "Всего БД: " . count($factory->getAvailableDatabases()) . "\n";
echo "Активных соединений: {$factory->getCachedConnectionsCount()}\n";
echo "Активные БД: " . implode(', ', $factory->getActiveDatabases()) . "\n";

// Проверка активности
foreach ($factory->getAvailableDatabases() as $dbName) {
    $status = $factory->isConnectionAlive($dbName) ? 'OK' : 'НЕТ';
    echo "  {$dbName}: {$status}\n";
}
```

## Обработка исключений

Фабрика использует специализированные исключения для разных типов ошибок:

```php
use App\Component\Exception\MySQLException;
use App\Component\Exception\MySQLConnectionException;

try {
    $factory = new MySQLConnectionFactory($config);
    $db = $factory->getConnection('main');
    
    // Ваш код работы с БД
    
} catch (MySQLConnectionException $e) {
    // Ошибка подключения к БД
    error_log("Не удалось подключиться к БД: {$e->getMessage()}");
    
} catch (MySQLException $e) {
    // Общая ошибка MySQL (валидация конфигурации, отсутствие БД в конфиге и т.д.)
    error_log("Ошибка MySQL: {$e->getMessage()}");
}
```

## Архитектурные особенности

### Кеширование соединений

Фабрика использует статический кеш для хранения соединений:

```php
// Первый вызов - создает новое соединение
$db1 = $factory->getConnection('main'); // ~10ms

// Второй вызов - возвращает из кеша
$db2 = $factory->getConnection('main'); // ~0.01ms

// $db1 === $db2 (true)
```

### Ленивая инициализация

Соединение с БД создается только при первом обращении:

```php
// Фабрика создана, но соединений еще нет
$factory = new MySQLConnectionFactory($config);

// Соединение создается только здесь
$mainDb = $factory->getConnection('main');
```

### Потокобезопасность

Статический кеш позволяет переиспользовать соединения между экземплярами фабрики:

```php
$factory1 = new MySQLConnectionFactory($config);
$db1 = $factory1->getConnection('main');

$factory2 = new MySQLConnectionFactory($config);
$db2 = $factory2->getConnection('main');

// $db1 === $db2 (true) - одно и то же соединение
```

## Best Practices

### 1. Используйте БД по умолчанию для основных операций

```php
// Хорошо
$db = $factory->getDefaultConnection();

// Избыточно для основной БД
$db = $factory->getConnection('main');
```

### 2. Проверяйте наличие БД перед использованием

```php
if ($factory->hasDatabase('analytics')) {
    $db = $factory->getConnection('analytics');
    // работа с БД
} else {
    // fallback логика
}
```

### 3. Используйте транзакции для связанных операций

```php
$db->transaction(function() use ($db) {
    // Все операции в рамках одной транзакции
    $id = $db->insert(...);
    $db->update(...);
});
```

### 4. Логируйте все операции

```php
// Создавайте фабрику с логгером
$factory = new MySQLConnectionFactory($config, $logger);

// Все операции будут автоматически логироваться
```

### 5. Очищайте кеш при необходимости

```php
// Например, в конце long-running процесса
if (memory_get_usage() > 100 * 1024 * 1024) { // 100MB
    $factory->clearConnectionCache();
}
```

## Миграция с обычного MySQL класса

### Было (без фабрики):

```php
$mainDb = new MySQL([
    'host' => 'localhost',
    'database' => 'main_db',
    'username' => 'root',
    'password' => 'password'
]);

$analyticsDb = new MySQL([
    'host' => 'analytics-server',
    'database' => 'analytics_db',
    'username' => 'analytics_user',
    'password' => 'analytics_pass'
]);
```

### Стало (с фабрикой):

```php
// Конфигурация в mysql.json
$config = json_decode(file_get_contents('config/mysql.json'), true);
$factory = new MySQLConnectionFactory($config);

$mainDb = $factory->getConnection('main');
$analyticsDb = $factory->getConnection('analytics');
```

### Преимущества:

- ✅ Соединения кешируются и переиспользуются
- ✅ Централизованная конфигурация
- ✅ Нет дублирования кода подключения
- ✅ Легко добавлять новые БД
- ✅ Удобное управление всеми соединениями

## Производительность

### Тесты кеширования

```
Создание нового соединения: ~10-15ms
Получение из кеша: ~0.01ms
Экономия: ~99.9%
```

### Рекомендации по оптимизации

1. **Используйте persistent соединения** для high-traffic приложений:
   ```json
   {
       "persistent": true
   }
   ```

2. **Включайте кеширование prepared statements**:
   ```json
   {
       "cache_statements": true
   }
   ```

3. **Переиспользуйте соединения** вместо создания новых

4. **Используйте batch операции** для массовой вставки

## Отладка

### Включение детального логирования

```php
$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'file_name' => 'mysql_debug.log',
    'level' => 'DEBUG'
]);

$factory = new MySQLConnectionFactory($config, $logger);

// Все операции будут детально логироваться:
// - Создание соединений
// - Получение из кеша
// - Запросы к БД
// - Ошибки и исключения
```

### Мониторинг активных соединений

```php
// Периодическая проверка состояния
function monitorConnections(MySQLConnectionFactory $factory): void
{
    $stats = [
        'total_databases' => count($factory->getAvailableDatabases()),
        'active_connections' => $factory->getCachedConnectionsCount(),
        'active_databases' => $factory->getActiveDatabases(),
    ];
    
    foreach ($factory->getAvailableDatabases() as $dbName) {
        $stats['alive'][$dbName] = $factory->isConnectionAlive($dbName);
    }
    
    error_log('MySQL Connections Stats: ' . json_encode($stats));
}
```

## Дополнительные материалы

- [MySQL.class.php документация](../src/MySQL.class.php)
- [Примеры использования](../examples/mysql_connection_factory_example.php)
- [Конфигурация](../config/mysql.json)

## Лицензия

Этот компонент является частью проекта утилит и следует основной лицензии проекта.


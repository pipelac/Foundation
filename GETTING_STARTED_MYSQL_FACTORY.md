# MySQLConnectionFactory - Начало работы

## 🚀 За 3 минуты

### Шаг 1: Настройка конфигурации

Откройте `config/mysql.json` и настройте ваши базы данных:

```json
{
    "databases": {
        "main": {
            "host": "localhost",
            "port": 3306,
            "database": "your_database",
            "username": "your_username",
            "password": "your_password",
            "charset": "utf8mb4"
        }
    },
    "default": "main"
}
```

### Шаг 2: Создание фабрики

```php
<?php

require_once __DIR__ . '/autoload.php';

use App\Component\MySQLConnectionFactory;
use App\Component\Logger;

// Загрузка конфигурации
$config = json_decode(file_get_contents(__DIR__ . '/config/mysql.json'), true);

// Опциональный логгер
$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'file_name' => 'mysql.log'
]);

// Создание фабрики
$factory = new MySQLConnectionFactory($config, $logger);
```

### Шаг 3: Использование

```php
// Получение соединения
$db = $factory->getConnection('main');

// Работа с БД
$users = $db->query('SELECT * FROM users WHERE status = ?', ['active']);

foreach ($users as $user) {
    echo "User: {$user['name']} ({$user['email']})\n";
}
```

## 🎯 Готовый пример

Скопируйте и запустите:

```php
<?php

require_once __DIR__ . '/autoload.php';

use App\Component\MySQLConnectionFactory;

try {
    // 1. Загрузка конфигурации
    $config = json_decode(
        file_get_contents(__DIR__ . '/config/mysql.json'), 
        true
    );
    
    // 2. Создание фабрики
    $factory = new MySQLConnectionFactory($config);
    
    // 3. Получение соединения
    $db = $factory->getConnection('main');
    
    // 4. Проверка соединения
    if ($db->ping()) {
        echo "✓ Соединение установлено!\n";
        
        // 5. Простой запрос
        $result = $db->queryScalar('SELECT VERSION()');
        echo "MySQL версия: {$result}\n";
    }
    
} catch (\Exception $e) {
    echo "✗ Ошибка: {$e->getMessage()}\n";
}
```

Сохраните как `test_connection.php` и запустите:

```bash
php test_connection.php
```

## 📚 Что дальше?

### Изучите документацию:
- [Полная документация](docs/MYSQL_CONNECTION_FACTORY.md) - все возможности
- [Быстрая справка](docs/MYSQL_QUICK_REFERENCE.md) - краткий справочник
- [Руководство по обновлению](MYSQL_FACTORY_UPGRADE.md) - миграция с MySQL

### Запустите примеры:
```bash
php examples/mysql_connection_factory_example.php
```

### Прочитайте Changelog:
- [История изменений](CHANGELOG_MYSQL_FACTORY.md)

## 💡 Частые сценарии

### Добавление новой БД

**1. Добавьте в config/mysql.json:**
```json
{
    "databases": {
        "main": { ... },
        "analytics": {
            "host": "analytics-server",
            "database": "analytics_db",
            "username": "analytics_user",
            "password": "analytics_pass"
        }
    }
}
```

**2. Используйте в коде:**
```php
$analyticsDb = $factory->getConnection('analytics');
$stats = $analyticsDb->query('SELECT * FROM statistics');
```

### Работа с несколькими БД

```php
// Основная БД
$mainDb = $factory->getConnection('main');
$users = $mainDb->query('SELECT id, name FROM users');

// БД аналитики
$analyticsDb = $factory->getConnection('analytics');

// Связанные данные
foreach ($users as $user) {
    $stats = $analyticsDb->queryOne(
        'SELECT views FROM user_stats WHERE user_id = ?',
        [$user['id']]
    );
    echo "{$user['name']}: {$stats['views']} просмотров\n";
}
```

### Транзакции

```php
$db = $factory->getConnection('main');

// Автоматическое управление транзакцией
$userId = $db->transaction(function() use ($db) {
    // Вставка пользователя
    $userId = $db->insert(
        'INSERT INTO users (name, email) VALUES (?, ?)',
        ['Иван Иванов', 'ivan@example.com']
    );
    
    // Вставка профиля
    $db->insert(
        'INSERT INTO profiles (user_id, bio) VALUES (?, ?)',
        [$userId, 'Программист']
    );
    
    return $userId;
});

echo "Создан пользователь с ID: {$userId}\n";
```

## 🔧 Устранение проблем

### Ошибка: "База данных не найдена в конфигурации"

**Проблема:**
```php
$db = $factory->getConnection('analytics');
// MySQLException: База данных "analytics" не найдена
```

**Решение:**
1. Проверьте `config/mysql.json` - есть ли секция `analytics`
2. Используйте `hasDatabase()` для проверки:
```php
if ($factory->hasDatabase('analytics')) {
    $db = $factory->getConnection('analytics');
} else {
    echo "БД 'analytics' не настроена\n";
}
```

### Ошибка подключения

**Проблема:**
```
MySQLConnectionException: Не удалось подключиться к базе данных
```

**Решение:**
1. Проверьте параметры в `config/mysql.json`
2. Убедитесь что MySQL сервер запущен
3. Проверьте права доступа пользователя
4. Используйте `ping()` для проверки:
```php
$db = $factory->getConnection('main');
if (!$db->ping()) {
    echo "Соединение неактивно\n";
}
```

### Проблемы с кешированием

**Если нужно сбросить кеш:**
```php
// Очистить все соединения
$factory->clearConnectionCache();

// Очистить конкретное соединение
$factory->clearConnectionCache('main');
```

## ✅ Проверка установки

Запустите этот код для проверки:

```php
<?php

require_once __DIR__ . '/autoload.php';

use App\Component\MySQLConnectionFactory;

echo "=== Проверка MySQLConnectionFactory ===\n\n";

try {
    // Загрузка конфигурации
    $config = json_decode(
        file_get_contents(__DIR__ . '/config/mysql.json'),
        true
    );
    echo "✓ Конфигурация загружена\n";
    
    // Создание фабрики
    $factory = new MySQLConnectionFactory($config);
    echo "✓ Фабрика создана\n";
    
    // Проверка доступных БД
    $databases = $factory->getAvailableDatabases();
    echo "✓ Доступных БД: " . count($databases) . "\n";
    echo "  " . implode(', ', $databases) . "\n";
    
    // БД по умолчанию
    $default = $factory->getDefaultDatabaseName();
    echo "✓ БД по умолчанию: {$default}\n";
    
    // Получение соединения
    $db = $factory->getConnection($default);
    echo "✓ Соединение получено\n";
    
    // Проверка активности
    if ($db->ping()) {
        echo "✓ Соединение активно\n";
    } else {
        echo "✗ Соединение неактивно\n";
    }
    
    // Информация о соединении
    $info = $db->getConnectionInfo();
    echo "✓ MySQL версия: {$info['server_version']}\n";
    
    echo "\n=== Все проверки пройдены! ===\n";
    
} catch (\Exception $e) {
    echo "\n✗ ОШИБКА: {$e->getMessage()}\n";
    echo "Проверьте конфигурацию в config/mysql.json\n";
}
```

## 📞 Поддержка

**Документация:**
- [docs/MYSQL_CONNECTION_FACTORY.md](docs/MYSQL_CONNECTION_FACTORY.md)
- [docs/MYSQL_QUICK_REFERENCE.md](docs/MYSQL_QUICK_REFERENCE.md)

**Примеры:**
- [examples/mysql_connection_factory_example.php](examples/mysql_connection_factory_example.php)

**Миграция:**
- [MYSQL_FACTORY_UPGRADE.md](MYSQL_FACTORY_UPGRADE.md)

---

Успешной работы с MySQLConnectionFactory! 🚀


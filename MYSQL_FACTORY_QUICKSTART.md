# MySQLConnectionFactory - Быстрый старт

## 5 минут до работы с множественными БД

### Шаг 1: Настройте конфигурацию

Откройте `config/mysql.json` и добавьте ваши подключения:

```json
{
    "connections": {
        "default": {
            "host": "localhost",
            "database": "main_db",
            "username": "root",
            "password": "your_password"
        },
        "analytics": {
            "host": "analytics.server.com",
            "database": "analytics_db",
            "username": "analytics_user",
            "password": "analytics_password"
        }
    },
    "default_connection": "default"
}
```

### Шаг 2: Инициализируйте фабрику

```php
<?php

use App\Component\MySQLConnectionFactory;
use App\Config\ConfigLoader;

// Загрузите конфигурацию
$config = ConfigLoader::load(__DIR__ . '/config/mysql.json');

// Инициализируйте фабрику
$factory = MySQLConnectionFactory::initialize($config);
```

### Шаг 3: Используйте подключения

```php
// Получите нужное подключение
$mainDb = $factory->getConnection('default');
$analyticsDb = $factory->getConnection('analytics');

// Работайте с разными БД
$users = $mainDb->query('SELECT * FROM users');
$analyticsDb->insert('INSERT INTO events (type) VALUES (?)', ['login']);
```

## Готово! 🎉

Теперь вы можете:
- Работать с несколькими БД одновременно
- Получать автоматическое кеширование соединений
- Использовать фабрику из любого места кода через `getInstance()`

## Основные методы

```php
// Инициализация (один раз при запуске приложения)
MySQLConnectionFactory::initialize($config, $logger);

// Получение экземпляра из любого места
$factory = MySQLConnectionFactory::getInstance();

// Получение соединения по имени
$db = $factory->getConnection('default');

// Получение соединения по умолчанию
$db = $factory->getDefaultConnection();

// Проверка наличия подключения
if ($factory->hasConnection('cache')) {
    $cacheDb = $factory->getConnection('cache');
}

// Список доступных подключений
$available = $factory->getAvailableConnections();

// Проверка состояния всех соединений
$status = $factory->pingAll();
```

## Практический пример

### Микросервисная архитектура

```php
// Инициализация при старте приложения
$factory = MySQLConnectionFactory::initialize($config);

// В контроллере пользователей
class UserController
{
    public function login(int $userId)
    {
        $factory = MySQLConnectionFactory::getInstance();
        
        // Получаем данные из основной БД
        $usersDb = $factory->getConnection('users');
        $user = $usersDb->queryOne('SELECT * FROM users WHERE id = ?', [$userId]);
        
        // Логируем событие в БД аналитики
        $analyticsDb = $factory->getConnection('analytics');
        $analyticsDb->insert(
            'INSERT INTO events (user_id, event_type) VALUES (?, ?)',
            [$userId, 'login']
        );
        
        // Сохраняем лог в БД логов
        $logsDb = $factory->getConnection('logs');
        $logsDb->insert(
            'INSERT INTO logs (level, message, context) VALUES (?, ?, ?)',
            ['info', 'User logged in', json_encode(['user_id' => $userId])]
        );
        
        return $user;
    }
}
```

## Преимущества

✅ **Производительность**: Соединения кешируются автоматически  
✅ **Простота**: Один раз инициализировал - используешь везде  
✅ **Гибкость**: Легко добавлять новые подключения  
✅ **Надежность**: Строгая типизация и обработка ошибок  
✅ **Масштабируемость**: Подходит для микросервисов и больших проектов  

## Следующие шаги

📖 **Полная документация**: [MYSQL_FACTORY_README.md](MYSQL_FACTORY_README.md)  
💻 **Примеры кода**: [examples/mysql_multi_connection_example.php](examples/mysql_multi_connection_example.php)  
🧪 **Тестирование**: `php bin/test_mysql_factory.php`  

## Обратная совместимость

Не волнуйтесь! Старый код продолжает работать:

```php
// Старый способ (по-прежнему работает)
$mysql = new MySQL([
    'host' => 'localhost',
    'database' => 'mydb',
    'username' => 'user',
    'password' => 'pass'
]);

// Новый способ (рекомендуется для новых проектов)
$factory = MySQLConnectionFactory::initialize($config);
$mysql = $factory->getConnection('default');
```

## Поддержка

Возникли вопросы? Проверьте:
- [MYSQL_FACTORY_README.md](MYSQL_FACTORY_README.md) - подробная документация
- [examples/mysql_multi_connection_example.php](examples/mysql_multi_connection_example.php) - рабочие примеры

---

**Совет**: Для production окружения используйте `"persistent": true` в конфигурации часто используемых подключений для максимальной производительности!

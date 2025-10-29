# MySQL - Документация

## Описание

`MySQL` - профессиональный класс для работы с MySQL через PDO. Обеспечивает типобезопасную и надежную работу с базой данных с поддержкой транзакций, batch-операций и кеширования подготовленных запросов.

## Возможности

- ✅ Строгая типизация на уровне PHP 8.1+
- ✅ Кеширование подготовленных запросов для повышения производительности
- ✅ Поддержка персистентных соединений
- ✅ Управление транзакциями с контролем состояния
- ✅ Batch-операции для массовой вставки данных
- ✅ Специализированные методы для SELECT, INSERT, UPDATE, DELETE
- ✅ Получение скалярных значений
- ✅ Структурированное логирование через Logger
- ✅ Специализированные исключения для разных типов ошибок
- ✅ Автоматическое переподключение при потере соединения
- ✅ Защита от SQL-инъекций через prepared statements

## Требования

- PHP 8.1+
- Расширения: `pdo`, `pdo_mysql`
- MySQL 5.7+ или MariaDB 10.2+

## Установка

```bash
composer install
```

## Конфигурация

Создайте файл `config/mysql.json`:

```json
{
    "host": "localhost",
    "port": 3306,
    "database": "myapp",
    "username": "root",
    "password": "secret",
    "charset": "utf8mb4",
    "persistent": false,
    "cache_statements": true,
    "options": {
        "PDO::ATTR_TIMEOUT": 5
    }
}
```

### Параметры конфигурации

| Параметр | Тип | Обязательный | По умолчанию | Описание |
|----------|-----|--------------|--------------|----------|
| `host` | string | Нет | "localhost" | Хост MySQL сервера |
| `port` | int | Нет | 3306 | Порт MySQL сервера |
| `database` | string | Да | - | Имя базы данных |
| `username` | string | Да | - | Имя пользователя БД |
| `password` | string | Да | - | Пароль пользователя |
| `charset` | string | Нет | "utf8mb4" | Кодировка соединения |
| `persistent` | bool | Нет | false | Использовать персистентное соединение |
| `cache_statements` | bool | Нет | true | Кешировать подготовленные запросы |
| `options` | array | Нет | [] | Дополнительные опции PDO |

## Использование

### Инициализация

```php
use App\Component\MySQL;
use App\Component\Logger;
use App\Config\ConfigLoader;

// С логгером
$config = ConfigLoader::load(__DIR__ . '/config/mysql.json');
$loggerConfig = ConfigLoader::load(__DIR__ . '/config/logger.json');
$logger = new Logger($loggerConfig);
$mysql = new MySQL($config, $logger);

// Без логгера
$mysql = new MySQL($config);
```

### SELECT запросы

#### Получение всех строк

```php
// Все пользователи со статусом 'active'
$users = $mysql->query('SELECT * FROM users WHERE status = ?', ['active']);

foreach ($users as $user) {
    echo $user['name'] . "\n";
}

// С несколькими параметрами
$users = $mysql->query(
    'SELECT * FROM users WHERE status = ? AND age > ? ORDER BY created_at DESC',
    ['active', 18]
);
```

#### Получение одной строки

```php
// Получить пользователя по ID
$user = $mysql->queryOne('SELECT * FROM users WHERE id = ?', [1]);

if ($user !== null) {
    echo "Найден: {$user['name']}\n";
} else {
    echo "Пользователь не найден\n";
}
```

#### Получение скалярного значения

```php
// Подсчет пользователей
$count = $mysql->queryScalar('SELECT COUNT(*) FROM users WHERE status = ?', ['active']);
echo "Активных пользователей: {$count}\n";

// Максимальный ID
$maxId = $mysql->queryScalar('SELECT MAX(id) FROM users');

// Получить email по ID
$email = $mysql->queryScalar('SELECT email FROM users WHERE id = ?', [123]);
```

### INSERT запросы

#### Одиночная вставка

```php
// Вставка нового пользователя
$userId = $mysql->insert(
    'INSERT INTO users (name, email, status) VALUES (?, ?, ?)',
    ['Иван Иванов', 'ivan@example.com', 'active']
);

echo "Создан пользователь с ID: {$userId}\n";
```

#### Массовая вставка (Batch Insert)

```php
// Подготовка данных
$users = [
    ['name' => 'Пользователь 1', 'email' => 'user1@example.com', 'status' => 'active'],
    ['name' => 'Пользователь 2', 'email' => 'user2@example.com', 'status' => 'active'],
    ['name' => 'Пользователь 3', 'email' => 'user3@example.com', 'status' => 'inactive'],
];

// Массовая вставка в рамках одной транзакции
$insertedCount = $mysql->insertBatch('users', $users);
echo "Вставлено записей: {$insertedCount}\n";
```

### UPDATE запросы

```php
// Обновление одного пользователя
$affected = $mysql->update(
    'UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?',
    ['inactive', 5]
);
echo "Обновлено записей: {$affected}\n";

// Массовое обновление
$affected = $mysql->update(
    'UPDATE users SET last_login = NOW() WHERE status = ?',
    ['active']
);
```

### DELETE запросы

```php
// Удаление одного пользователя
$deleted = $mysql->delete('DELETE FROM users WHERE id = ?', [10]);
echo "Удалено записей: {$deleted}\n";

// Удаление по условию
$deleted = $mysql->delete('DELETE FROM users WHERE status = ? AND created_at < ?', ['inactive', '2020-01-01']);
```

### Транзакции

#### Базовое использование

```php
try {
    $mysql->beginTransaction();
    
    // Создание пользователя
    $userId = $mysql->insert('INSERT INTO users (name, email) VALUES (?, ?)', ['Тест', 'test@example.com']);
    
    // Создание профиля
    $mysql->insert('INSERT INTO profiles (user_id, bio) VALUES (?, ?)', [$userId, 'Тестовый профиль']);
    
    // Подтверждение транзакции
    $mysql->commit();
    
    echo "Транзакция успешно выполнена\n";
} catch (Exception $e) {
    // Откат транзакции при ошибке
    $mysql->rollback();
    echo "Ошибка: " . $e->getMessage() . "\n";
}
```

#### Проверка состояния транзакции

```php
if ($mysql->inTransaction()) {
    echo "Транзакция активна\n";
}

// Безопасный откат
if ($mysql->inTransaction()) {
    $mysql->rollback();
}
```

### Произвольные SQL команды

```php
// Создание таблицы
$mysql->execute('
    CREATE TABLE IF NOT EXISTS logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
');

// Очистка таблицы
$mysql->execute('TRUNCATE TABLE logs');

// Изменение структуры
$mysql->execute('ALTER TABLE users ADD COLUMN phone VARCHAR(20)');
```

### Прямой доступ к PDO

```php
// Получить PDO объект для расширенных операций
$pdo = $mysql->getConnection();

// Использовать напрямую
$statement = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$statement->execute(['id' => 1]);
$user = $statement->fetch();
```

## API Reference

### Конструктор

```php
public function __construct(array $config, ?Logger $logger = null)
```

Создает соединение с базой данных.

**Параметры:**
- `$config` (array) - Конфигурация подключения
- `$logger` (Logger|null) - Опциональный логгер

**Исключения:**
- `MySQLConnectionException` - Ошибка подключения к БД
- `MySQLException` - Некорректная конфигурация

### SELECT методы

#### query()

```php
public function query(string $query, array $params = []): array
```

Выполняет SELECT запрос и возвращает все строки.

**Возвращает:** Массив ассоциативных массивов

#### queryOne()

```php
public function queryOne(string $query, array $params = []): ?array
```

Возвращает первую строку результата или null.

**Возвращает:** Ассоциативный массив или null

#### queryScalar()

```php
public function queryScalar(string $query, array $params = []): mixed
```

Возвращает значение первого столбца первой строки.

**Возвращает:** Скалярное значение или null

### INSERT методы

#### insert()

```php
public function insert(string $query, array $params = []): int
```

Выполняет INSERT и возвращает ID вставленной записи.

**Возвращает:** Last insert ID (0 для таблиц без AUTO_INCREMENT)

#### insertBatch()

```php
public function insertBatch(string $table, array $rows): int
```

Выполняет массовую вставку в рамках транзакции.

**Параметры:**
- `$table` (string) - Имя таблицы
- `$rows` (array) - Массив строк для вставки

**Возвращает:** Количество вставленных строк

### UPDATE методы

#### update()

```php
public function update(string $query, array $params = []): int
```

Выполняет UPDATE и возвращает количество обновленных строк.

**Возвращает:** Количество затронутых строк

### DELETE методы

#### delete()

```php
public function delete(string $query, array $params = []): int
```

Выполняет DELETE и возвращает количество удаленных строк.

**Возвращает:** Количество удаленных строк

### Транзакции

#### beginTransaction()

```php
public function beginTransaction(): void
```

Начинает новую транзакцию.

**Исключения:**
- `MySQLTransactionException` - Если транзакция уже активна

#### commit()

```php
public function commit(): void
```

Подтверждает активную транзакцию.

#### rollback()

```php
public function rollback(): void
```

Откатывает активную транзакцию.

#### inTransaction()

```php
public function inTransaction(): bool
```

Проверяет, активна ли транзакция.

### Утилиты

#### execute()

```php
public function execute(string $query): int
```

Выполняет произвольную SQL команду (DDL, DML).

#### getConnection()

```php
public function getConnection(): PDO
```

Возвращает объект PDO для расширенных операций.

## Примеры использования

### CRUD операции

```php
// CREATE
$userId = $mysql->insert(
    'INSERT INTO users (name, email, created_at) VALUES (?, ?, NOW())',
    ['Новый пользователь', 'new@example.com']
);

// READ
$user = $mysql->queryOne('SELECT * FROM users WHERE id = ?', [$userId]);

// UPDATE
$mysql->update(
    'UPDATE users SET name = ?, updated_at = NOW() WHERE id = ?',
    ['Обновленное имя', $userId]
);

// DELETE
$mysql->delete('DELETE FROM users WHERE id = ?', [$userId]);
```

### Сложные запросы

```php
// JOIN запрос
$results = $mysql->query('
    SELECT u.*, p.bio, p.avatar
    FROM users u
    LEFT JOIN profiles p ON u.id = p.user_id
    WHERE u.status = ?
    ORDER BY u.created_at DESC
    LIMIT ?
', ['active', 10]);

// Группировка
$stats = $mysql->query('
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as count,
        AVG(amount) as avg_amount
    FROM orders
    WHERE created_at >= ?
    GROUP BY DATE(created_at)
', ['2024-01-01']);

// Подзапросы
$users = $mysql->query('
    SELECT * FROM users
    WHERE id IN (
        SELECT user_id FROM orders
        WHERE total > ?
    )
', [1000]);
```

### Транзакции с несколькими операциями

```php
try {
    $mysql->beginTransaction();
    
    // 1. Создать заказ
    $orderId = $mysql->insert('
        INSERT INTO orders (user_id, total, status)
        VALUES (?, ?, ?)
    ', [$userId, $total, 'pending']);
    
    // 2. Добавить товары в заказ
    foreach ($items as $item) {
        $mysql->insert('
            INSERT INTO order_items (order_id, product_id, quantity, price)
            VALUES (?, ?, ?, ?)
        ', [$orderId, $item['product_id'], $item['quantity'], $item['price']]);
    }
    
    // 3. Обновить остатки товаров
    foreach ($items as $item) {
        $mysql->update('
            UPDATE products
            SET stock = stock - ?
            WHERE id = ?
        ', [$item['quantity'], $item['product_id']]);
    }
    
    // 4. Создать запись в логах
    $mysql->insert('
        INSERT INTO activity_logs (user_id, action, details)
        VALUES (?, ?, ?)
    ', [$userId, 'order_created', json_encode(['order_id' => $orderId])]);
    
    $mysql->commit();
    
    echo "Заказ #{$orderId} успешно создан\n";
    
} catch (Exception $e) {
    $mysql->rollback();
    echo "Ошибка создания заказа: " . $e->getMessage() . "\n";
    throw $e;
}
```

### Пагинация

```php
function getUsers(MySQL $mysql, int $page = 1, int $perPage = 20): array
{
    $offset = ($page - 1) * $perPage;
    
    // Получить записи
    $users = $mysql->query('
        SELECT * FROM users
        WHERE status = ?
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ', ['active', $perPage, $offset]);
    
    // Получить общее количество
    $total = $mysql->queryScalar('
        SELECT COUNT(*) FROM users WHERE status = ?
    ', ['active']);
    
    return [
        'data' => $users,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => ceil($total / $perPage),
    ];
}

// Использование
$result = getUsers($mysql, page: 2, perPage: 50);
```

### Работа с датами

```php
// Вставка с текущей датой
$mysql->insert('
    INSERT INTO posts (title, content, created_at, updated_at)
    VALUES (?, ?, NOW(), NOW())
', [$title, $content]);

// Фильтрация по дате
$recentPosts = $mysql->query('
    SELECT * FROM posts
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY created_at DESC
');

// Форматирование даты в запросе
$posts = $mysql->query('
    SELECT 
        id,
        title,
        DATE_FORMAT(created_at, "%d.%m.%Y %H:%i") as formatted_date
    FROM posts
');
```

## Обработка ошибок

### Исключения

- `MySQLException` - Базовое исключение MySQL класса
- `MySQLConnectionException` - Ошибка подключения к БД
- `MySQLTransactionException` - Ошибка работы с транзакциями

```php
use App\Component\Exception\MySQLConnectionException;
use App\Component\Exception\MySQLTransactionException;
use App\Component\Exception\MySQLException;

// Обработка ошибки подключения
try {
    $mysql = new MySQL($config);
} catch (MySQLConnectionException $e) {
    echo "Не удалось подключиться к БД: " . $e->getMessage();
    // Отправить уведомление администратору
}

// Обработка ошибки запроса
try {
    $users = $mysql->query('SELECT * FROM users WHERE id = ?', [$id]);
} catch (MySQLException $e) {
    echo "Ошибка выполнения запроса: " . $e->getMessage();
}

// Обработка ошибки транзакции
try {
    $mysql->beginTransaction();
    // ... операции
    $mysql->commit();
} catch (MySQLTransactionException $e) {
    $mysql->rollback();
    echo "Ошибка транзакции: " . $e->getMessage();
}
```

## Лучшие практики

1. **Всегда используйте prepared statements** для защиты от SQL-инъекций:
   ```php
   // ✅ Правильно
   $mysql->query('SELECT * FROM users WHERE email = ?', [$email]);
   
   // ❌ Неправильно
   $mysql->execute("SELECT * FROM users WHERE email = '$email'");
   ```

2. **Используйте транзакции** для атомарных операций:
   ```php
   $mysql->beginTransaction();
   try {
       // Несколько связанных операций
       $mysql->commit();
   } catch (Exception $e) {
       $mysql->rollback();
       throw $e;
   }
   ```

3. **Используйте insertBatch()** для массовых вставок:
   ```php
   // ✅ Быстро (одна транзакция)
   $mysql->insertBatch('users', $rows);
   
   // ❌ Медленно (много транзакций)
   foreach ($rows as $row) {
       $mysql->insert('INSERT INTO users ...', $row);
   }
   ```

4. **Проверяйте результаты** перед использованием:
   ```php
   $user = $mysql->queryOne('SELECT * FROM users WHERE id = ?', [$id]);
   if ($user === null) {
       throw new Exception('Пользователь не найден');
   }
   ```

5. **Используйте индексы** для ускорения запросов:
   ```php
   $mysql->execute('CREATE INDEX idx_email ON users(email)');
   $mysql->execute('CREATE INDEX idx_status_created ON users(status, created_at)');
   ```

6. **Логируйте ошибки БД** для отладки:
   ```php
   $mysql = new MySQL($config, $logger);
   ```

7. **Используйте персистентные соединения** для высоконагруженных приложений:
   ```php
   ['persistent' => true]
   ```

8. **Включите кеширование запросов** для повторяющихся запросов:
   ```php
   ['cache_statements' => true]
   ```

## Производительность

### Оптимизация

- **Кеширование prepared statements** - автоматически кеширует до 100 запросов
- **Персистентные соединения** - переиспользование соединений между запросами
- **Batch операции** - массовая вставка в одной транзакции
- **Ленивая загрузка** - подключение только при необходимости

### Рекомендации

```php
// 1. Используйте лимиты для больших выборок
$mysql->query('SELECT * FROM logs ORDER BY created_at DESC LIMIT 1000');

// 2. Оптимизируйте JOIN запросы
$mysql->query('
    SELECT u.id, u.name, COUNT(o.id) as order_count
    FROM users u
    LEFT JOIN orders o ON u.id = o.user_id
    GROUP BY u.id
    HAVING order_count > 10
');

// 3. Используйте queryScalar() для агрегатов
$count = $mysql->queryScalar('SELECT COUNT(*) FROM users');

// 4. Избегайте SELECT * в production
$mysql->query('SELECT id, name, email FROM users');
```

## Безопасность

1. **Никогда не конкатенируйте SQL** с пользовательским вводом
2. **Всегда используйте параметризованные запросы**
3. **Ограничивайте права пользователя БД**
4. **Используйте SSL для подключения**:
   ```php
   [
       'options' => [
           PDO::MYSQL_ATTR_SSL_CA => '/path/to/ca.pem',
           PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
       ]
   ]
   ```
5. **Не храните пароли в открытом виде** - используйте переменные окружения

## См. также

- [Logger документация](LOGGER.md) - для логирования запросов к БД
- [FileCache документация](FILECACHE.md) - для кеширования результатов запросов

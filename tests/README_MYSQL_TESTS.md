# Руководство по тестированию MySQL.class.php

## Быстрый запуск

### 1. Установка MySQL сервера

```bash
sudo apt-get update
sudo apt-get install -y mysql-server
sudo systemctl start mysql
```

### 2. Настройка тестового окружения

```bash
# Создание пользователя, баз данных и тестовых данных
sudo mysql < tests/setup_mysql_test_env.sql
```

Это создаст:
- Пользователя `test_user` с паролем `test_password_123`
- 3 тестовые базы данных
- 7 таблиц с тестовыми данными (40+ записей)

### 3. Запуск тестов

```bash
php tests/mysql_e2e_test.php
```

## Что тестируется

### Базовые операции (26 тестов)
- ✅ Подключение к БД (валидное/невалидное)
- ✅ Различные charset (utf8, utf8mb4)
- ✅ Персистентные соединения
- ✅ SELECT запросы (query, queryOne, queryScalar)
- ✅ INSERT запросы (с AUTO_INCREMENT и без)
- ✅ UPDATE запросы
- ✅ DELETE запросы
- ✅ Массовая вставка (insertBatch)

### Транзакции (5 тестов)
- ✅ Commit/Rollback
- ✅ Вложенные транзакции (ошибка)
- ✅ Callback транзакции
- ✅ Изоляция транзакций

### Обработка ошибок (3 теста)
- ✅ Синтаксические ошибки SQL
- ✅ Нарушение внешних ключей
- ✅ Дублирование записей

### Дополнительно (4 теста)
- ✅ Кеширование prepared statements
- ✅ DDL команды (CREATE, DROP)
- ✅ Сложные JOIN запросы
- ✅ Массовые операции (100+ записей)

## Структура тестового окружения

### test_database_main
```
users (10 записей)
├─ id, username, email, age, balance, is_active
├─ Индексы: username, email, is_active
└─ AUTO_INCREMENT

products (10 записей)
├─ id, name, description, price, stock, category
├─ Индексы: category, price
└─ AUTO_INCREMENT

orders (10 записей)
├─ id, user_id, product_id, quantity, total_price, status
├─ FOREIGN KEY: user_id -> users(id)
├─ FOREIGN KEY: product_id -> products(id)
└─ AUTO_INCREMENT

logs (5 записей)
├─ id, level, message, context (JSON)
└─ AUTO_INCREMENT
```

### test_database_transactions
```
accounts (4 записи)
├─ id, account_number, balance
└─ Для тестирования транзакций

transactions
├─ id, from_account_id, to_account_id, amount, type, status
└─ История транзакций
```

### test_database_secondary
```
settings (5 записей)
├─ id, key_name, key_value, description
└─ Для дополнительных тестов
```

## Примеры использования

### Базовые запросы

```php
use App\Component\MySQL;
use App\Component\Logger;

$config = [
    'host' => 'localhost',
    'database' => 'test_database_main',
    'username' => 'test_user',
    'password' => 'test_password_123',
    'charset' => 'utf8mb4',
];

$logger = new Logger(['directory' => './logs', 'filename' => 'app.log']);
$db = new MySQL($config, $logger);

// SELECT всех пользователей
$users = $db->query("SELECT * FROM users WHERE is_active = 1");

// SELECT одного пользователя
$user = $db->queryOne("SELECT * FROM users WHERE username = ?", ['john_doe']);

// Подсчет записей
$count = $db->queryScalar("SELECT COUNT(*) FROM users");

// INSERT
$lastId = $db->insert(
    "INSERT INTO users (username, email, age) VALUES (?, ?, ?)",
    ['new_user', 'new@example.com', 25]
);

// UPDATE
$affectedRows = $db->update(
    "UPDATE users SET balance = balance + ? WHERE id = ?",
    [100, $lastId]
);

// DELETE
$deleted = $db->delete("DELETE FROM users WHERE id = ?", [$lastId]);
```

### Транзакции

```php
// Ручное управление
$db->beginTransaction();
try {
    $db->update("UPDATE accounts SET balance = balance - 100 WHERE id = 1");
    $db->update("UPDATE accounts SET balance = balance + 100 WHERE id = 2");
    $db->commit();
} catch (\Exception $e) {
    $db->rollback();
    throw $e;
}

// Через callback
$result = $db->transaction(function() use ($db) {
    $db->update("UPDATE accounts SET balance = balance - 100 WHERE id = 1");
    $db->update("UPDATE accounts SET balance = balance + 100 WHERE id = 2");
    return 'success';
});
```

### Массовая вставка

```php
$products = [
    ['name' => 'Product 1', 'price' => 99.99, 'stock' => 10],
    ['name' => 'Product 2', 'price' => 149.99, 'stock' => 20],
    ['name' => 'Product 3', 'price' => 199.99, 'stock' => 30],
];

$insertedCount = $db->insertBatch('products', $products);
// Вставлено 3 записи за одну транзакцию
```

### Проверка подключения

```php
if ($db->ping()) {
    echo "MySQL сервер доступен\n";
}

$info = $db->getConnectionInfo();
// ['server_version' => '8.0.43', 'client_version' => '...', ...]

$version = $db->getMySQLVersion();
// ['version' => '8.0.43', 'major' => 8, 'minor' => 0, 'patch' => 43, ...]
```

## Логирование

Все операции логируются в файл `logs/app.log`:

```
2025-11-01T06:21:03+00:00 DEBUG Успешное подключение к БД
2025-11-01T06:21:03+00:00 DEBUG Выполнен SELECT запрос {"query":"...", "rows":10}
2025-11-01T06:21:03+00:00 DEBUG Выполнен INSERT запрос {"last_insert_id":42}
2025-11-01T06:21:03+00:00 ERROR Ошибка выполнения SQL {"error":"..."}
```

## Очистка тестового окружения

```bash
# Удаление тестовых баз данных
sudo mysql -e "DROP DATABASE IF EXISTS test_database_main;"
sudo mysql -e "DROP DATABASE IF EXISTS test_database_secondary;"
sudo mysql -e "DROP DATABASE IF EXISTS test_database_transactions;"

# Удаление тестового пользователя
sudo mysql -e "DROP USER IF EXISTS 'test_user'@'localhost';"
```

## Повторный запуск тестов

Тесты можно запускать многократно. При каждом запуске:
- Создаются новые записи (с новыми ID)
- Транзакции используют текущие значения балансов
- Временные таблицы автоматически очищаются

```bash
# Очистить данные и начать заново
sudo mysql < tests/setup_mysql_test_env.sql

# Запустить тесты
php tests/mysql_e2e_test.php
```

## Требования

- PHP 8.1+
- MySQL 5.5.62+ (рекомендуется 8.0+)
- PDO расширение с драйвером MySQL
- Права на создание БД и пользователей

## Результаты тестирования

После запуска тестов будет создан отчет:
- Консольный вывод с результатами каждого теста
- Лог-файл `logs/app.log` с детальной информацией
- Полный отчет в `tests/MYSQL_E2E_TEST_REPORT.md`

## Устранение неполадок

### MySQL сервер не запускается
```bash
sudo systemctl status mysql
sudo systemctl start mysql
sudo journalctl -u mysql -n 50
```

### Ошибка подключения
```bash
# Проверка существования пользователя
sudo mysql -e "SELECT User, Host FROM mysql.user WHERE User='test_user';"

# Проверка прав доступа
sudo mysql -e "SHOW GRANTS FOR 'test_user'@'localhost';"
```

### Ошибка "Access denied"
```bash
# Пересоздание пользователя
sudo mysql < tests/setup_mysql_test_env.sql
```

## Дополнительная информация

- Все тестовые данные создаются в отдельных базах данных
- Тесты не влияют на производственные данные
- Используются реальные внешние ключи и ограничения
- Логирование настраивается через конфигурацию Logger

---

**Документация:** `tests/MYSQL_E2E_TEST_REPORT.md`  
**Скрипт настройки:** `tests/setup_mysql_test_env.sql`  
**Файл тестов:** `tests/mysql_e2e_test.php`

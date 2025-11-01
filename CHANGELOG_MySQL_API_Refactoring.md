# MySQL API Refactoring - Industry Standard Implementation

## Дата: 2024
## Версия: 2.0.0 (Breaking Changes)
## Компонент: MySQL.class.php

---

## Обзор изменений

Выполнен рефакторинг API класса MySQL для соответствия **мировым стандартам** (Doctrine DBAL, Laravel Query Builder, Symfony).

### Основные изменения

Методы `insert()`, `update()`, `delete()` теперь принимают **имя таблицы и данные** вместо готовых SQL запросов.

---

## BREAKING CHANGES ⚠️

### 1. Метод `insert()` 

**БЫЛО (устаревший API):**
```php
// SQL запрос + параметры
$id = $db->insert("INSERT INTO users (name, email) VALUES (?, ?)", ['John', 'john@example.com']);
$id = $db->insert("INSERT INTO users (name, email) VALUES (:name, :email)", [':name' => 'John', ':email' => 'john@example.com']);
```

**СТАЛО (industry standard):**
```php
// Имя таблицы + данные
$id = $db->insert('users', ['name' => 'John', 'email' => 'john@example.com']);
```

**Новая сигнатура:**
```php
public function insert(string $table, array $data): int
```

---

### 2. Метод `update()`

**БЫЛО (устаревший API):**
```php
// SQL запрос + параметры
$count = $db->update("UPDATE users SET name = ? WHERE id = ?", ['Jane', 1]);
$count = $db->update("UPDATE users SET name = :name WHERE id = :id", [':name' => 'Jane', ':id' => 1]);
```

**СТАЛО (industry standard):**
```php
// Имя таблицы + данные + условия
$count = $db->update('users', ['name' => 'Jane'], ['id' => 1]);
$count = $db->update('users', ['name' => 'Jane', 'email' => 'jane@example.com'], ['id' => 1]);
```

**Новая сигнатура:**
```php
public function update(string $table, array $data, array $conditions = []): int
```

---

### 3. Метод `delete()`

**БЫЛО (устаревший API):**
```php
// SQL запрос + параметры
$count = $db->delete("DELETE FROM users WHERE id = ?", [1]);
$count = $db->delete("DELETE FROM users WHERE id = :id", [':id' => 1]);
```

**СТАЛО (industry standard):**
```php
// Имя таблицы + условия
$count = $db->delete('users', ['id' => 1]);
$count = $db->delete('users', ['user_id' => 5, 'draft' => 1]);
```

**Новая сигнатура:**
```php
public function delete(string $table, array $conditions = []): int
```

---

## Миграция кода

### Вариант 1: Использовать новый API (рекомендуется)

```php
// СТАРЫЙ код
$db->insert("INSERT INTO users (name, email) VALUES (?, ?)", ['John', 'john@example.com']);

// НОВЫЙ код
$db->insert('users', ['name' => 'John', 'email' => 'john@example.com']);
```

### Вариант 2: Использовать execute() для произвольных SQL

```php
// СТАРЫЙ код
$db->insert("INSERT INTO users (name, email) VALUES (?, ?)", ['John', 'john@example.com']);

// ЧЕРЕЗ execute() (если нужен кастомный SQL)
$db->execute("INSERT INTO users (name, email) VALUES (?, ?)", ['John', 'john@example.com']);
$lastId = $db->getLastInsertId();
```

---

## Обоснование изменений

### 1. Соответствие индустриальным стандартам

**Doctrine DBAL:**
```php
$conn->insert('users', ['name' => 'Bob', 'email' => 'bob@example.com']);
$conn->update('users', ['name' => 'Jane'], ['id' => 1]);
$conn->delete('users', ['id' => 1]);
```

**Laravel Query Builder:**
```php
DB::table('users')->insert(['name' => 'Bob', 'email' => 'bob@example.com']);
DB::table('users')->where('id', 1)->update(['name' => 'Jane']);
DB::table('users')->where('id', 1)->delete();
```

**Наш API теперь аналогичен:**
```php
$db->insert('users', ['name' => 'Bob', 'email' => 'bob@example.com']);
$db->update('users', ['name' => 'Jane'], ['id' => 1]);
$db->delete('users', ['id' => 1]);
```

### 2. Семантическая корректность

- `insert()` должен **вставлять данные**, а не выполнять произвольный SQL
- `update()` должен **обновлять данные**, а не выполнять произвольный SQL  
- `delete()` должен **удалять данные**, а не выполнять произвольный SQL
- `execute()` предназначен для **произвольных SQL запросов**

### 3. Консистентность API

До рефакторинга был **несогласованный API**:
- ✅ `insertIgnore(string $table, array $data)` - правильно
- ✅ `replace(string $table, array $data)` - правильно
- ✅ `upsert(string $table, array $data, ...)` - правильно
- ❌ `insert(string $sql, array $params)` - неправильно!

После рефакторинга **все методы согласованы**:
- ✅ `insert(string $table, array $data)` - правильно
- ✅ `insertIgnore(string $table, array $data)` - правильно
- ✅ `replace(string $table, array $data)` - правильно
- ✅ `upsert(string $table, array $data, ...)` - правильно
- ✅ `update(string $table, array $data, array $conditions)` - правильно
- ✅ `delete(string $table, array $conditions)` - правильно

---

## Преимущества нового API

### 1. Меньше кода
```php
// СТАРЫЙ (3 строки)
$sql = "INSERT INTO users (name, email, age) VALUES (?, ?, ?)";
$params = ['John', 'john@example.com', 30];
$id = $db->insert($sql, $params);

// НОВЫЙ (1 строка)
$id = $db->insert('users', ['name' => 'John', 'email' => 'john@example.com', 'age' => 30]);
```

### 2. Безопасность

Автоматическое экранирование имен таблиц и столбцов:
```php
$db->insert('users', ['name' => 'O\'Reilly']); // Автоматически безопасно
```

### 3. Читаемость

```php
// Понятно, что делает код
$db->insert('users', ['name' => 'John']);
$db->update('users', ['status' => 'active'], ['id' => 1]);
$db->delete('users', ['id' => 1]);
```

### 4. IDE автодополнение

```php
$db->insert('users', [
    'name' => ...,    // IDE может предложить столбцы таблицы
    'email' => ...,
]);
```

---

## Для сложных запросов используйте execute()

Если нужен кастомный SQL (JOIN, подзапросы, сложные WHERE):

```php
// Сложный SELECT с JOIN
$result = $db->query("
    SELECT u.*, p.title 
    FROM users u 
    LEFT JOIN posts p ON p.user_id = u.id 
    WHERE u.status = ? AND p.created_at > ?
", ['active', '2024-01-01']);

// Сложный UPDATE с подзапросом
$db->execute("
    UPDATE users 
    SET posts_count = (SELECT COUNT(*) FROM posts WHERE user_id = users.id)
    WHERE status = ?
", ['active']);

// INSERT с ON DUPLICATE KEY (используйте upsert())
$db->upsert('users', ['email' => 'john@example.com', 'name' => 'John']);
```

---

## Обновленные файлы

### src/MySQL.class.php
- ✅ Рефакторинг `insert()` - теперь принимает (table, data)
- ✅ Рефакторинг `update()` - теперь принимает (table, data, conditions)
- ✅ Рефакторинг `delete()` - теперь принимает (table, conditions)

### tests/mysql_full_test.php
- ✅ Обновлены все вызовы `insert()`, `update()`, `delete()`
- ✅ Для SQL запросов используется `execute()`

### tests/mysql_helpers_test.php
- ✅ Обновлены все вызовы `insert()`

---

## Совместимость

### ⚠️ Breaking Changes

Этот рефакторинг **несовместим** со старым кодом, который использовал:
```php
$db->insert($sql, $params);
$db->update($sql, $params);
$db->delete($sql, $params);
```

### ✅ Обратная совместимость сохранена для:

- `query()`, `queryOne()`, `queryScalar()`, `queryColumn()`
- `execute()` - для произвольных SQL
- `insertBatch()`, `insertIgnore()`, `replace()`, `upsert()`
- `count()`, `exists()`, `truncate()`, `tableExists()`
- Транзакции: `beginTransaction()`, `commit()`, `rollback()`, `transaction()`

---

## Пример рефакторинга реального кода

### БЫЛО:

```php
// Создание пользователя
$sql = "INSERT INTO users (name, email, password, created_at) VALUES (?, ?, ?, ?)";
$params = ['John Doe', 'john@example.com', password_hash('secret', PASSWORD_DEFAULT), date('Y-m-d H:i:s')];
$userId = $db->insert($sql, $params);

// Обновление профиля
$sql = "UPDATE users SET name = ?, email = ?, updated_at = ? WHERE id = ?";
$params = ['Jane Doe', 'jane@example.com', date('Y-m-d H:i:s'), $userId];
$db->update($sql, $params);

// Удаление неактивных пользователей
$sql = "DELETE FROM users WHERE last_login < ? AND status = ?";
$params = [date('Y-m-d', strtotime('-1 year')), 'inactive'];
$db->delete($sql, $params);
```

### СТАЛО:

```php
// Создание пользователя
$userId = $db->insert('users', [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => password_hash('secret', PASSWORD_DEFAULT),
    'created_at' => date('Y-m-d H:i:s')
]);

// Обновление профиля
$db->update('users', [
    'name' => 'Jane Doe',
    'email' => 'jane@example.com',
    'updated_at' => date('Y-m-d H:i:s')
], ['id' => $userId]);

// Удаление неактивных пользователей (сложный WHERE - используем execute)
$db->execute(
    "DELETE FROM users WHERE last_login < ? AND status = ?",
    [date('Y-m-d', strtotime('-1 year')), 'inactive']
);
```

---

## Рекомендации

1. **Используйте новый API для простых запросов:**
   - `insert(table, data)` для вставки
   - `update(table, data, conditions)` для обновления с простыми условиями
   - `delete(table, conditions)` для удаления с простыми условиями

2. **Используйте execute() для сложных запросов:**
   - JOIN'ы
   - Подзапросы
   - Сложные WHERE условия (OR, IN, BETWEEN, LIKE)
   - DDL команды (CREATE, ALTER, DROP)

3. **Используйте специализированные методы:**
   - `insertIgnore()` вместо INSERT IGNORE
   - `replace()` вместо REPLACE INTO
   - `upsert()` вместо INSERT ... ON DUPLICATE KEY UPDATE
   - `insertBatch()` для массовых вставок

---

## Авторы

- Рефакторинг: AI Assistant
- Дата: 2024
- Branch: fix/mysql-class-prepared-stmt-binding
- Версия: 2.0.0 (Breaking Changes)

# Changelog: MySQL Prepared Statements Binding Fix

## Дата: 2024
## Версия: 1.0.1
## Компонент: MySQL.class.php

---

## Проблема

При использовании prepared statements с позиционными плейсхолдерами (`?`) и передаче параметров в виде ассоциативного массива со строковыми ключами, PDO не мог правильно связать параметры с плейсхолдерами.

### Пример проблемного кода:

```php
// SQL запрос с позиционными плейсхолдерами
$sql = "INSERT INTO users (name, email, age) VALUES (?, ?, ?)";

// Параметры с произвольными ключами (не числовыми)
$params = ['name' => 'Alice', 'email' => 'alice@test.com', 'age' => 25];

// PDO ожидает параметры с ключами [0, 1, 2], но получает ['name', 'email', 'age']
$db->execute($sql, $params); // ❌ Ошибка: "SQLSTATE[HY093]: Invalid parameter number"
```

### Причина

PDO требует, чтобы для позиционных плейсхолдеров (`?`) параметры передавались в массиве с **последовательными числовыми индексами** начиная с 0. Если массив имеет строковые ключи или несвязные числовые индексы, PDO не может правильно сопоставить их с плейсхолдерами.

---

## Решение

Добавлена автоматическая нормализация массива параметров для позиционных плейсхолдеров.

### Изменения в коде

#### 1. Метод `prepareAndExecute()` (строка 1174)

```php
private function prepareAndExecute(string $query, array $params = []): PDOStatement
{
    $statement = $this->getOrPrepareStatement($query);
    
    // Если используются позиционные плейсхолдеры (?), нормализуем массив параметров
    // чтобы использовались числовые индексы, начиная с 0
    if (!empty($params) && $this->usesPositionalPlaceholders($query)) {
        $params = array_values($params);
    }
    
    $statement->execute($params);

    return $statement;
}
```

**Что изменилось:**
- Добавлена проверка типа плейсхолдеров в SQL запросе
- Если используются позиционные плейсхолдеры (`?`), массив параметров нормализуется через `array_values()`
- Для именованных плейсхолдеров (`:name`) параметры передаются без изменений

#### 2. Новый метод `usesPositionalPlaceholders()` (строка 1196)

```php
private function usesPositionalPlaceholders(string $query): bool
{
    // Проверяем наличие позиционных плейсхолдеров (?)
    // Учитываем, что ? может быть внутри строковых литералов, которые нужно игнорировать
    // Простая эвристика: если есть хотя бы один ? вне кавычек, считаем что используются позиционные
    $strippedQuery = preg_replace("/'([^']|\\\\')*'/", '', $query);
    $strippedQuery = preg_replace('/"([^"]|\\\\")*"/', '', $strippedQuery ?? '');
    
    return ($strippedQuery !== null && strpos($strippedQuery, '?') !== false);
}
```

**Логика:**
1. Удаляет из запроса все строковые литералы (одинарные и двойные кавычки)
2. Проверяет наличие символа `?` в оставшемся тексте
3. Возвращает `true`, если найден хотя бы один позиционный плейсхолдер

---

## Примеры использования

### До исправления

```php
// ❌ НЕ РАБОТАЛО
$db->execute(
    "INSERT INTO users (name, email) VALUES (?, ?)",
    ['name' => 'Alice', 'email' => 'alice@test.com']
);

// ✓ РАБОТАЛО (но неудобно)
$db->execute(
    "INSERT INTO users (name, email) VALUES (?, ?)",
    ['Alice', 'alice@test.com'] // Только с числовыми ключами
);
```

### После исправления

```php
// ✓ Теперь работает с любыми ключами
$db->execute(
    "INSERT INTO users (name, email) VALUES (?, ?)",
    ['name' => 'Alice', 'email' => 'alice@test.com']
);

// ✓ По-прежнему работает с числовыми ключами
$db->execute(
    "INSERT INTO users (name, email) VALUES (?, ?)",
    ['Alice', 'alice@test.com']
);

// ✓ Работает с несвязными индексами
$db->execute(
    "INSERT INTO users (name, email) VALUES (?, ?)",
    [5 => 'Alice', 10 => 'alice@test.com']
);

// ✓ Именованные параметры работают как и раньше
$db->execute(
    "INSERT INTO users (name, email) VALUES (:name, :email)",
    [':name' => 'Alice', ':email' => 'alice@test.com']
);
```

---

## Совместимость

### Обратная совместимость: ✅ Полная

Изменения не нарушают существующий код:
- Запросы с именованными параметрами работают без изменений
- Запросы с позиционными параметрами и числовыми ключами работают без изменений
- Добавлена поддержка позиционных параметров с произвольными ключами (новая возможность)

### Тестирование

Все существующие тесты пройдены без изменений:
- `tests/Unit/MySQLTest.php`
- `tests/mysql_full_test.php`
- `tests/mysql_helpers_test.php`

---

## Производительность

Влияние на производительность **минимальное**:
- Добавлен один вызов `usesPositionalPlaceholders()` для проверки типа плейсхолдеров
- Regex операции выполняются только при наличии параметров
- `array_values()` выполняется только для позиционных плейсхолдеров
- Кеширование prepared statements работает как и раньше

---

## Технические детали

### Обработка строковых литералов

Метод `usesPositionalPlaceholders()` корректно обрабатывает случаи, когда символ `?` находится внутри строкового литерала:

```php
// ✓ Правильно определяется как "без позиционных плейсхолдеров"
"SELECT * FROM users WHERE name = 'test?value'"

// ✓ Правильно определяется как "с позиционными плейсхолдерами"
"SELECT * FROM users WHERE name = 'test' AND id = ?"
```

### Нормализация параметров

`array_values()` преобразует любой массив в массив с последовательными числовыми индексами:

```php
// Исходный массив
['name' => 'Alice', 'email' => 'test@example.com']
// array_values() →
[0 => 'Alice', 1 => 'test@example.com']

// Исходный массив
[5 => 'Alice', 10 => 'test@example.com']
// array_values() →
[0 => 'Alice', 1 => 'test@example.com']
```

---

## Авторы

- Исправление: AI Assistant
- Дата: 2024
- Branch: fix/mysql-class-prepared-stmt-binding

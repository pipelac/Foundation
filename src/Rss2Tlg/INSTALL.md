# Установка модуля Rss2Tlg Fetch

Пошаговая инструкция по установке и настройке модуля.

## Требования

- PHP 8.1 или выше
- MySQL 5.7+ / MySQL 8.0+ / MariaDB 10.3+
- Composer
- Расширения PHP: `json`, `curl`, `pdo`, `pdo_mysql`, `dom`, `mbstring`

## Шаг 1: Проверка зависимостей

Убедитесь что все зависимости установлены:

```bash
# Проверка PHP
php -v  # Должно быть >= 8.1

# Проверка расширений
php -m | grep -E "(json|curl|pdo|pdo_mysql|dom|mbstring)"

# Проверка Composer
composer --version
```

## Шаг 2: Установка зависимостей Composer

```bash
cd /path/to/project
composer install
```

Будут установлены:
- `guzzlehttp/guzzle` - HTTP клиент
- `simplepie/simplepie` - RSS/Atom парсер
- `fivefilters/readability.php` - Извлечение контента

## Шаг 3: Настройка MySQL

### 3.1. Создание базы данных

```bash
# Подключение к MySQL
mysql -u root -p

# Создание БД
CREATE DATABASE rss2tlg CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Создание пользователя (опционально)
CREATE USER 'rss2tlg_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON rss2tlg.* TO 'rss2tlg_user'@'localhost';
FLUSH PRIVILEGES;
```

### 3.2. Импорт схемы таблиц

```bash
# Импорт из SQL файла
mysql -u root -p rss2tlg < src/Rss2Tlg/docs/schema.sql

# Или через командную строку MySQL:
USE rss2tlg;
SOURCE src/Rss2Tlg/docs/schema.sql;
```

### 3.3. Проверка таблицы

```sql
USE rss2tlg;
SHOW TABLES;  -- Должна быть таблица rss2tlg_feed_state
DESCRIBE rss2tlg_feed_state;
```

## Шаг 4: Создание директорий

```bash
# Директория для кеша SimplePie
mkdir -p cache/rss2tlg
chmod 755 cache/rss2tlg

# Директория для логов
mkdir -p logs
chmod 755 logs

# Директория для конфигов
mkdir -p config
chmod 755 config
```

## Шаг 5: Настройка конфигурации

```bash
# Копирование примера конфигурации
cp src/Rss2Tlg/docs/config.example.json config/rss2tlg.json

# Редактирование конфигурации
nano config/rss2tlg.json
```

Настройте параметры подключения к БД:

```json
{
  "database": {
    "host": "localhost",
    "port": 3306,
    "database": "rss2tlg",
    "username": "rss2tlg_user",
    "password": "secure_password",
    "charset": "utf8mb4"
  },
  "cache": {
    "directory": "/absolute/path/to/cache/rss2tlg",
    "enabled": true
  },
  "logging": {
    "level": "info",
    "file": "/absolute/path/to/logs/rss2tlg_fetch.log"
  },
  "feeds": [
    {
      "id": 1,
      "url": "https://news.ycombinator.com/rss",
      "enabled": true,
      "timeout": 30,
      "retries": 3,
      "polling_interval": 300,
      "headers": {
        "User-Agent": "Rss2Tlg/1.0"
      },
      "parser_options": {
        "max_items": 50,
        "enable_cache": true
      }
    }
  ]
}
```

**Важно:** Используйте абсолютные пути для `cache.directory` и `logging.file`.

## Шаг 6: Обновление автозагрузки

После создания всех файлов обновите автозагрузку Composer:

```bash
composer dump-autoload
```

## Шаг 7: Проверка установки

### 7.1. Быстрый тест DTO классов (без БД)

```bash
php examples/rss2tlg/quick_test.php
```

Ожидаемый результат:
```
=== Quick Test: Rss2Tlg DTO Classes ===
1. Тест FeedConfig...
   ✓ FeedConfig создан
2. Тест FeedState...
   ✓ Начальное состояние создано
   ✓ Состояние после успешного fetch
   ✓ Состояние после ошибки
...
✓ Все базовые тесты пройдены успешно!
```

### 7.2. Демо парсинга RSS (без БД)

```bash
php examples/rss2tlg/parse_rss_demo.php
```

или с кастомным URL:

```bash
php examples/rss2tlg/parse_rss_demo.php "https://habr.com/ru/rss/hub/php/all/"
```

### 7.3. Тест с БД (опрос одного источника)

```bash
php examples/rss2tlg/fetch_single.php
```

или с кастомным URL:

```bash
php examples/rss2tlg/fetch_single.php "https://news.ycombinator.com/rss"
```

### 7.4. Полный тест (опрос всех источников из конфига)

```bash
php examples/rss2tlg/fetch_example.php
```

## Шаг 8: Запуск unit тестов (опционально)

```bash
# Установка PHPUnit (если не установлен)
composer require --dev phpunit/phpunit

# Запуск тестов DTO классов
vendor/bin/phpunit tests/Rss2Tlg/DTO/FeedConfigTest.php
vendor/bin/phpunit tests/Rss2Tlg/DTO/FeedStateTest.php
```

## Troubleshooting

### Ошибка "Class not found"

Обновите автозагрузку:
```bash
composer dump-autoload
```

### Ошибка подключения к БД

Проверьте параметры в `config/rss2tlg.json`:
```bash
# Проверка подключения
mysql -h localhost -u rss2tlg_user -p rss2tlg
```

### Ошибка записи в кеш

Проверьте права доступа:
```bash
ls -la cache/
chmod 755 cache/rss2tlg
```

### SimplePie ошибка парсинга

Проверьте валидность RSS:
```bash
curl -s "https://example.com/feed.xml" | xmllint --noout -
```

### "Table doesn't exist"

Импортируйте схему:
```bash
mysql -u root -p rss2tlg < src/Rss2Tlg/docs/schema.sql
```

## Настройка cron для автоматического опроса

Добавьте в crontab задачу для периодического опроса:

```bash
crontab -e
```

Пример: опрос каждые 5 минут:
```
*/5 * * * * cd /path/to/project && php examples/rss2tlg/fetch_example.php >> logs/cron_fetch.log 2>&1
```

## Мониторинг

### Проверка состояния источников

```sql
SELECT 
    feed_id,
    url,
    last_status,
    error_count,
    backoff_until,
    fetched_at
FROM rss2tlg_feed_state
ORDER BY fetched_at DESC;
```

### Статистика

```sql
SELECT 
    COUNT(*) AS total_feeds,
    SUM(CASE WHEN last_status = 200 THEN 1 ELSE 0 END) AS success_200,
    SUM(CASE WHEN last_status = 304 THEN 1 ELSE 0 END) AS not_modified_304,
    SUM(CASE WHEN last_status >= 400 THEN 1 ELSE 0 END) AS errors,
    SUM(CASE WHEN backoff_until IS NOT NULL AND backoff_until > NOW() THEN 1 ELSE 0 END) AS in_backoff
FROM rss2tlg_feed_state;
```

### Источники в backoff

```sql
SELECT 
    feed_id,
    url,
    error_count,
    backoff_until,
    TIMESTAMPDIFF(SECOND, NOW(), backoff_until) AS backoff_remaining_sec
FROM rss2tlg_feed_state
WHERE backoff_until IS NOT NULL AND backoff_until > NOW()
ORDER BY backoff_remaining_sec DESC;
```

### Сброс ошибок для источника

```sql
UPDATE rss2tlg_feed_state 
SET error_count = 0, backoff_until = NULL 
WHERE feed_id = 1;
```

## Следующие шаги

После успешной установки модуля fetch вы можете:

1. Добавить новые источники в `config/rss2tlg.json`
2. Настроить cron для автоматического опроса
3. Интегрировать следующие этапы конвейера:
   - Анализ контента (фильтрация, категоризация)
   - Дедупликация элементов
   - Публикация в Telegram

## Полезные ссылки

- [SimplePie Documentation](https://simplepie.org/wiki/)
- [Guzzle HTTP Client](https://docs.guzzlephp.org/)
- [RSS 2.0 Specification](https://www.rssboard.org/rss-specification)
- [Atom Specification](https://datatracker.ietf.org/doc/html/rfc4287)

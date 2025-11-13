# RSS Ingest Production Script

## Описание
Скрипт собирает новости из RSS источников и сохраняет их в базу данных. Предназначен для запуска по cron каждую минуту.

## Версия: 1.0.0

---

## Основные возможности

✅ **Автоинициализация БД**
- Автоматическая загрузка лент из `configs/feeds.json` при первом запуске
- Синхронизация конфигурации с таблицей `rss2tlg_feeds`
- Корректная обработка флага `enabled: true/false`

✅ **Сбор новостей**
- Поддержка RSS 2.0 и Atom форматов
- Извлечение всех стандартных полей (title, link, description, content)
- Парсинг мета-данных (категории, вложения, медиа)

✅ **Дедупликация**
- MD5 хеш для быстрой проверки дубликатов
- Игнорирование повторных записей

✅ **Отказоустойчивость**
- Обработка ошибок на каждом уровне
- Логирование всех операций
- Отслеживание состояния источников

---

## Установка и настройка

### 1. Конфигурация feeds.json

Файл: `production/configs/feeds.json`

```json
{
    "feeds": [
        {
            "name": "Название источника",
            "feed_url": "https://example.com/feed.xml",
            "website_url": "https://example.com",
            "enabled": true
        }
    ]
}
```

**Параметры:**
- `name` (обязательный) - название источника
- `feed_url` (обязательный) - URL RSS/Atom ленты
- `website_url` (опциональный) - URL сайта источника
- `enabled` (опциональный, по умолчанию: true) - флаг активности

### 2. Конфигурация БД

Файл: `production/configs/database.json`

```json
{
    "host": "localhost",
    "port": 3306,
    "database": "rss2tlg",
    "username": "user",
    "password": "pass",
    "charset": "utf8mb4"
}
```

### 3. Основная конфигурация

Файл: `production/configs/main.json`

```json
{
    "log_directory": "/var/log/rss2tlg",
    "log_level": "info"
}
```

---

## Запуск

### Вручную
```bash
php production/rss_ingest.php
```

### Cron (каждую минуту)
```cron
* * * * * cd /path/to/project && php production/rss_ingest.php >> /var/log/rss2tlg/cron.log 2>&1
```

---

## Синхронизация БД с конфигом

### Принцип работы

✅ **feeds.json является источником истины**  
✅ **Таблица rss2tlg_feeds - актуальный слепок конфигурации**  
✅ **Синхронизация происходит при КАЖДОМ запуске скрипта**

### Процесс синхронизации

При каждом запуске скрипт автоматически:

1. Загружает `configs/feeds.json`
2. Для каждой ленты в конфиге:
   - Если feed_url уже есть в БД:
     - Сравнивает поля (name, website_url, enabled)
     - Обновляет только если данные изменились
   - Если feed_url нет в БД:
     - Создает новую запись
3. Отключает ленты, которых нет в конфиге:
   - Ленты в БД, отсутствующие в конфиге, автоматически получают enabled=0
4. Логирует все операции

### Примеры вывода

**Первый запуск (пустая БД):**
```
🔄 Синхронизация лент из feeds.json...
   ✅ Добавлен: РИА Новости (enabled: 1)
   ✅ Добавлен: Коммерсантъ (enabled: 0)
   ✅ Добавлен: Интерфакс (enabled: 1)
✅ Синхронизация завершена: добавлено 3, обновлено 0, пропущено 0
```

**Повторный запуск (без изменений):**
```
🔄 Синхронизация лент из feeds.json...
✅ Ленты синхронизированы: изменений не требуется
```

**Изменение конфига (enabled: false → true):**
```
🔄 Синхронизация лент из feeds.json...
   ✏️  Обновлен: Коммерсантъ (enabled: 1)
✅ Синхронизация завершена: добавлено 0, обновлено 1, пропущено 0
```

**Удаление ленты из конфига:**
```
🔄 Синхронизация лент из feeds.json...
   ⚠️  Отключен (не в конфиге): Старая лента
✅ Синхронизация завершена: добавлено 0, обновлено 0, пропущено 0
```

---

## Обработка флага enabled

### Корректное преобразование типов

```php
// boolean (JSON) → TINYINT (MySQL)
enabled: true  → 1 (обрабатывается)
enabled: false → 0 (пропускается)
```

### Включение/выключение лент

**Способ 1: Через feeds.json (рекомендуется)**
1. Измените `enabled: false` в `configs/feeds.json`
2. Перезапустите скрипт - изменения применятся автоматически

```json
{
    "feeds": [
        {
            "name": "РИА Новости",
            "feed_url": "https://ria.ru/export/rss2/index.xml",
            "enabled": false  // ← изменить здесь
        }
    ]
}
```

**Способ 2: Напрямую в БД (для временных изменений)**
⚠️ **Внимание:** Изменения в БД будут перезаписаны при следующей синхронизации!

```sql
-- Отключить источник (временно)
UPDATE rss2tlg_feeds SET enabled = 0 WHERE id = 1;

-- Включить источник (временно)
UPDATE rss2tlg_feeds SET enabled = 1 WHERE id = 1;
```

**Рекомендация:** Всегда используйте feeds.json как источник истины. Изменения в БД будут синхронизированы с конфигом при следующем запуске скрипта.

---

## Структура таблиц

### rss2tlg_feeds
Хранит настройки RSS источников.

```sql
CREATE TABLE rss2tlg_feeds (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    feed_url VARCHAR(1024) NOT NULL,
    website_url VARCHAR(1024),
    enabled TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### rss2tlg_items
Хранит собранные новости.

```sql
CREATE TABLE rss2tlg_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    feed_id INT UNSIGNED NOT NULL,
    content_hash VARCHAR(32) NOT NULL,
    guid VARCHAR(512),
    title VARCHAR(512) NOT NULL,
    link VARCHAR(1024) NOT NULL,
    description TEXT,
    content MEDIUMTEXT,
    pub_date DATETIME,
    author VARCHAR(255),
    categories JSON,
    enclosures JSON,
    extraction_status ENUM('pending','success','failed','skipped') DEFAULT 'pending',
    is_published TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### rss2tlg_feed_state
Отслеживает состояние источников.

```sql
CREATE TABLE rss2tlg_feed_state (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    feed_id INT UNSIGNED NOT NULL,
    url VARCHAR(512) NOT NULL,
    last_status SMALLINT UNSIGNED DEFAULT 0,
    last_error TEXT,
    error_count SMALLINT UNSIGNED DEFAULT 0,
    backoff_until DATETIME,
    fetched_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

---

## Логирование

### Консольный вывод
```
╔═══════════════════════════════════════════════════════════════╗
║           RSS INGEST PRODUCTION SCRIPT v1.0.0                 ║
╚═══════════════════════════════════════════════════════════════╝
🕐 Start: 2025-01-13 12:00:00

✅ Активных лент в БД: 2

📊 Найдено источников: 2

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📡 Источник: РИА Новости
🔗 URL: https://ria.ru/export/rss2/index.xml
✅ Успешно обработан
   📥 Получено: 30
   ✨ Новых: 30
   🔁 Дубликатов: 0

╔═══════════════════════════════════════════════════════════════╗
║                    ИТОГОВАЯ СТАТИСТИКА                        ║
╚═══════════════════════════════════════════════════════════════╝
📊 Источников обработано: 2
   ✅ Успешно: 2
   ❌ Ошибок: 0

📰 Всего элементов получено: 60
   ✨ Новых: 60
   🔁 Дубликатов: 0

⏱️  Время выполнения: 2.34 сек
🕐 Завершено: 2025-01-13 12:00:02
```

### Лог-файл (logs/rss_ingest.log)
Формат: JSON, одна строка = одно событие

```json
{"level":"info","message":"[RSS_INGEST] Script started","context":{"version":"1.0.0","pid":12345}}
{"level":"info","message":"[RSS_INGEST] Active feeds already exist in DB","context":{"count":2}}
{"level":"info","message":"[RSS_INGEST] Active feeds loaded","context":{"count":2}}
{"level":"info","message":"[RSS_INGEST] Feed processed successfully","context":{"feed_id":1,"feed_name":"РИА Новости","items_total":30,"items_new":30,"items_duplicates":0}}
{"level":"info","message":"[RSS_INGEST] Script completed","context":{"stats":{...},"execution_time":2.34}}
```

---

## Мониторинг

### SQL запросы для мониторинга

```sql
-- Статистика по источникам
SELECT 
    f.id,
    f.name,
    f.enabled,
    COUNT(i.id) as items_count,
    MAX(i.created_at) as last_item_at
FROM rss2tlg_feeds f
LEFT JOIN rss2tlg_items i ON i.feed_id = f.id
GROUP BY f.id
ORDER BY f.id;

-- Последние ошибки
SELECT 
    fs.feed_id,
    f.name,
    fs.last_status,
    fs.last_error,
    fs.error_count,
    fs.fetched_at
FROM rss2tlg_feed_state fs
JOIN rss2tlg_feeds f ON f.id = fs.feed_id
WHERE fs.last_status != 200
ORDER BY fs.fetched_at DESC;

-- Статистика по новостям за сегодня
SELECT 
    DATE(created_at) as date,
    COUNT(*) as total_items,
    COUNT(DISTINCT feed_id) as feeds_count
FROM rss2tlg_items
WHERE DATE(created_at) = CURDATE()
GROUP BY DATE(created_at);
```

---

## Обработка ошибок

### HTTP ошибки
- 404, 500, 503 и т.д. - записываются в `rss2tlg_feed_state`
- Счетчик ошибок увеличивается
- При превышении порога возможна блокировка (backoff_until)

### XML ошибки
- Невалидный XML - логируется и пропускается
- Пустые ленты - предупреждение в логе

### Ошибки БД
- Дубликаты - игнорируются (нормальное поведение)
- Ошибки INSERT/UPDATE - логируются

---

## FAQ

**Q: Как добавить новый источник?**
A: Добавьте запись в `configs/feeds.json` и очистите таблицу `rss2tlg_feeds` для автосинхронизации, либо добавьте напрямую в БД.

**Q: Как временно отключить источник?**
A: Установите `enabled: false` в feeds.json + очистите таблицу, либо выполните `UPDATE rss2tlg_feeds SET enabled = 0 WHERE id = ?`

**Q: Что делать при ошибках парсинга?**
A: Проверьте лог-файл, убедитесь что URL ленты корректен и возвращает валидный RSS/Atom.

**Q: Как часто запускать скрипт?**
A: Рекомендуется каждую минуту через cron. Скрипт быстрый и не нагружает систему.

**Q: Где хранятся логи?**
A: По умолчанию в директории указанной в `main.json` (`log_directory`).

---

## Зависимости

- PHP 8.1+
- MySQL 5.5.62+ (рекомендуется MySQL 5.7+ или MariaDB 10.3+)
- PDO с драйвером MySQL
- curl расширение
- simplexml расширение

---

## Лицензия

Проприетарное ПО. Все права защищены.

---

## Контакты

По вопросам работы скрипта обращайтесь к команде разработки.

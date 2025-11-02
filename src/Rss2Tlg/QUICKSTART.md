# Rss2Tlg Fetch — Быстрый старт за 5 минут

Минимальная инструкция для начала работы с модулем.

## Шаг 1: Проверка зависимостей (30 сек)

```bash
cd /path/to/project

# Установка Composer зависимостей
composer install

# Обновление автозагрузки
composer dump-autoload
```

## Шаг 2: Тест без БД (1 мин)

```bash
# Быстрый тест DTO классов
php examples/rss2tlg/quick_test.php

# Демо парсинга Hacker News
php examples/rss2tlg/parse_rss_demo.php

# Демо парсинга Habr
php examples/rss2tlg/parse_rss_demo.php "https://habr.com/ru/rss/best/daily/"
```

**Ожидаемый результат:**
```
=== Quick Test: Rss2Tlg DTO Classes ===
✓ FeedConfig создан
✓ FeedState работает
✓ Валидация работает
✓ Все базовые тесты пройдены успешно!
```

## Шаг 3: Настройка БД (2 мин)

```bash
# Создать БД
mysql -u root -p
CREATE DATABASE rss2tlg CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;

# Импортировать схему
mysql -u root -p rss2tlg < src/Rss2Tlg/docs/schema.sql

# Проверить таблицу
mysql -u root -p rss2tlg
SHOW TABLES;  -- Должна быть rss2tlg_feed_state
DESCRIBE rss2tlg_feed_state;
EXIT;
```

## Шаг 4: Создать директории (30 сек)

```bash
mkdir -p cache/rss2tlg logs config
chmod 755 cache/rss2tlg logs config
```

## Шаг 5: Создать конфигурацию (1 мин)

```bash
# Копировать пример
cp src/Rss2Tlg/docs/config.example.json config/rss2tlg.json

# Минимальная конфигурация (замените пути на абсолютные!)
cat > config/rss2tlg.json << 'EOF'
{
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
  ],
  "cache": {
    "directory": "/ABSOLUTE/PATH/TO/cache/rss2tlg",
    "enabled": true
  },
  "database": {
    "host": "localhost",
    "port": 3306,
    "database": "rss2tlg",
    "username": "root",
    "password": "",
    "charset": "utf8mb4"
  },
  "logging": {
    "level": "info",
    "file": "/ABSOLUTE/PATH/TO/logs/rss2tlg_fetch.log"
  }
}
EOF

# Отредактировать и заменить /ABSOLUTE/PATH/TO/ на реальные пути
nano config/rss2tlg.json
```

**Важно:** Используйте абсолютные пути, например:
- `/home/user/project/cache/rss2tlg`
- `/var/www/project/logs/rss2tlg_fetch.log`

## Шаг 6: Тест с БД (1 мин)

```bash
# Опрос одного источника
php examples/rss2tlg/fetch_single.php

# Опрос всех источников из конфига
php examples/rss2tlg/fetch_example.php
```

**Ожидаемый результат:**
```
=== Rss2Tlg Fetch Example ===
✓ Конфигурация загружена
✓ Логгер инициализирован
✓ Подключено к rss2tlg
✓ Feed #1: news.ycombinator.com (enabled)

Feed #1 (news.ycombinator.com):
  ✓ SUCCESS (200 OK)
    - Items: 30 (valid: 30)
    - Duration: 1.234 sec
    - Body size: 45,632 bytes

==============================================================
Всего запросов:      1
  ✓ Успешно (200):   1
  ⟳ Not Modified (304): 0
  ✗ Ошибки:          0

Элементов извлечено: 30
✓ Пример успешно завершён
```

## Готово! 🎉

Модуль fetch установлен и работает.

### Что дальше?

#### Автоматический опрос (cron)

```bash
# Добавить в crontab
crontab -e

# Опрос каждые 5 минут
*/5 * * * * cd /path/to/project && php examples/rss2tlg/fetch_example.php >> logs/cron_fetch.log 2>&1
```

#### Добавить новые источники

Отредактируйте `config/rss2tlg.json` и добавьте новые feeds:

```json
{
  "id": 2,
  "url": "https://habr.com/ru/rss/best/daily/",
  "enabled": true,
  "timeout": 30,
  "retries": 3,
  "polling_interval": 600,
  "headers": {
    "User-Agent": "Rss2Tlg/1.0",
    "Accept-Language": "ru-RU,ru;q=0.9"
  },
  "parser_options": {
    "max_items": 30
  }
}
```

#### Мониторинг

```sql
-- Статистика источников
SELECT * FROM rss2tlg_feed_state;

-- Источники с ошибками
SELECT feed_id, url, last_status, error_count 
FROM rss2tlg_feed_state 
WHERE last_status >= 400;
```

#### Интеграция в код

```php
use App\Rss2Tlg\FetchRunner;
use App\Rss2Tlg\DTO\FeedConfig;

$fetchRunner = new FetchRunner($db, $cacheDir, $logger);
$result = $fetchRunner->runForFeed($config);

foreach ($result->getValidItems() as $item) {
    // Обработка элемента
    echo $item->title . "\n";
    echo $item->contentHash . "\n"; // Для дедупликации
}
```

## Документация

- **[README.md](README.md)** — Обзор модуля
- **[INSTALL.md](INSTALL.md)** — Подробная установка
- **[docs/API.md](docs/API.md)** — API справочник
- **[docs/README.md](docs/README.md)** — Полная документация

## Troubleshooting

### Class not found
```bash
composer dump-autoload
```

### Cannot connect to database
```bash
# Проверьте параметры в config/rss2tlg.json
mysql -h localhost -u root -p rss2tlg
```

### Permission denied на cache/
```bash
chmod 755 cache/rss2tlg
```

### SimplePie parsing error
```bash
# Проверьте валидность RSS
curl -s "https://example.com/feed.xml" | xmllint --noout -
```

## Помощь

Если что-то не работает:

1. Проверьте логи: `tail -f logs/rss2tlg_fetch.log`
2. Запустите quick_test: `php examples/rss2tlg/quick_test.php`
3. Проверьте БД: `SELECT * FROM rss2tlg_feed_state;`
4. Прочитайте [docs/README.md](docs/README.md)

Успехов! 🚀

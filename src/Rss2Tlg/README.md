# Rss2Tlg Fetch Module

**Модуль для эффективного опроса RSS/Atom лент с поддержкой Conditional GET, backoff и метрик.**

## Описание

Rss2Tlg Fetch — это первый этап конвейера обработки RSS/Atom лент для публикации в Telegram. Модуль обеспечивает:

- ✅ **Conditional GET** через ETag и Last-Modified для экономии трафика
- ✅ **Exponential Backoff** для автоматического управления rate limiting
- ✅ **SimplePie Integration** для универсальной поддержки RSS/Atom форматов
- ✅ **Нормализация данных** в единый формат `RawItem`
- ✅ **Content Hashing** для эффективной дедупликации
- ✅ **Детальные метрики** (200, 304, ошибки, парсинг)
- ✅ **Персистентное состояние** в MySQL для восстановления между запусками

## Архитектура

```
Fetch Pipeline:
┌──────────────┐
│ FeedConfig   │──┐
└──────────────┘  │
                  ▼
┌──────────────────────────────────┐
│      FetchRunner                 │
│  1. Проверка backoff             │
│  2. HTTP GET + Conditional GET   │
│  3. Парсинг через Rss.class.php  │
│  4. Нормализация в RawItem[]     │
│  5. Обновление FeedState         │
└──────────────────────────────────┘
                  │
                  ▼
┌──────────────┐     ┌──────────────┐
│ FetchResult  │────►│ RawItem[]    │
│ + FeedState  │     │ + contentHash│
└──────────────┘     └──────────────┘
                            │
                            ▼
                  Следующие этапы:
                  - Анализ контента
                  - Дедупликация
                  - Публикация в Telegram
```

## Интеграция с существующими компонентами

Модуль использует готовые production-ready компоненты проекта:

- **App\Component\Rss** — парсинг RSS/Atom лент (на базе SimplePie)
- **App\Component\Http** — HTTP клиент с retry и timeout (на базе Guzzle)
- **App\Component\MySQL** — обёртка над PDO для работы с БД
- **App\Component\Logger** — логирование событий и ошибок

## Структура модуля

```
src/Rss2Tlg/
├── DTO/
│   ├── FeedConfig.php      # Конфигурация источника
│   ├── FeedState.php       # Состояние (ETag, Last-Modified, backoff)
│   ├── RawItem.php         # Нормализованный элемент ленты
│   └── FetchResult.php     # Результат fetch операции
├── FetchRunner.php         # Основной класс опроса
├── FeedStateRepository.php # Репозиторий для работы с БД
├── docs/
│   ├── README.md           # Подробная документация
│   ├── API.md              # API документация
│   ├── schema.sql          # SQL схема таблицы feed_state
│   └── config.example.json # Пример конфигурации
├── INSTALL.md              # Инструкция по установке
└── README.md               # Этот файл

examples/rss2tlg/
├── quick_test.php          # Быстрый тест DTO классов
├── parse_rss_demo.php      # Демо парсинга RSS без БД
├── fetch_single.php        # Опрос одного источника
└── fetch_example.php       # Опрос всех источников

tests/Rss2Tlg/DTO/
├── FeedConfigTest.php      # Unit тесты для FeedConfig
└── FeedStateTest.php       # Unit тесты для FeedState
```

## Быстрый старт

### 1. Установка зависимостей

```bash
composer install
composer dump-autoload
```

### 2. Создание таблицы БД

```bash
mysql -u root -p rss2tlg < src/Rss2Tlg/docs/schema.sql
```

### 3. Подготовка конфигурации

```bash
cp src/Rss2Tlg/docs/config.example.json config/rss2tlg.json
nano config/rss2tlg.json  # Настройте БД и источники
```

### 4. Создание директорий

```bash
mkdir -p cache/rss2tlg logs
chmod 755 cache/rss2tlg logs
```

### 5. Тестирование

```bash
# Быстрый тест DTO (без БД)
php examples/rss2tlg/quick_test.php

# Демо парсинга RSS (без БД)
php examples/rss2tlg/parse_rss_demo.php

# Опрос одного источника (с БД)
php examples/rss2tlg/fetch_single.php

# Опрос всех источников (с БД)
php examples/rss2tlg/fetch_example.php
```

## Пример использования

```php
<?php

use App\Component\MySQL;
use App\Component\Logger;
use App\Rss2Tlg\FetchRunner;
use App\Rss2Tlg\DTO\FeedConfig;

// Инициализация
$db = new MySQL([
    'database' => 'rss2tlg',
    'username' => 'root',
    'password' => '',
]);

$logger = new Logger(['log_file' => 'logs/fetch.log']);
$fetchRunner = new FetchRunner($db, 'cache/rss2tlg', $logger);

// Конфигурация источника
$config = FeedConfig::fromArray([
    'id' => 1,
    'url' => 'https://news.ycombinator.com/rss',
    'enabled' => true,
    'timeout' => 30,
    'retries' => 3,
    'polling_interval' => 300,
    'headers' => ['User-Agent' => 'Rss2Tlg/1.0'],
    'parser_options' => ['max_items' => 50],
]);

// Опрос источника
$result = $fetchRunner->runForFeed($config);

// Обработка результата
if ($result->isSuccessful()) {
    echo "Получено элементов: " . $result->getItemCount() . "\n";
    
    foreach ($result->getValidItems() as $item) {
        echo "- {$item->title}\n";
        echo "  Link: {$item->link}\n";
        echo "  Hash: {$item->contentHash}\n";
        
        // Дальнейшая обработка...
    }
} elseif ($result->isNotModified()) {
    echo "Источник не изменился (304 Not Modified)\n";
} else {
    echo "Ошибка: " . $result->state->lastStatus . "\n";
}

// Метрики
print_r($fetchRunner->getMetrics());
```

## Conditional GET

Модуль автоматически использует Conditional GET для оптимизации:

1. **При первом запросе:** Сохраняет `ETag` и `Last-Modified` из ответа
2. **При следующих запросах:** Отправляет `If-None-Match` и `If-Modified-Since`
3. **Если источник не изменился:** Сервер возвращает `304 Not Modified` без тела
4. **Результат:** Экономия ~90% трафика при отсутствии обновлений

### HTTP заголовки

**Request:**
```
GET /rss HTTP/1.1
Host: example.com
If-None-Match: "abc123"
If-Modified-Since: Mon, 15 Jan 2024 10:00:00 GMT
```

**Response (304):**
```
HTTP/1.1 304 Not Modified
ETag: "abc123"
Last-Modified: Mon, 15 Jan 2024 10:00:00 GMT
```

**Response (200):**
```
HTTP/1.1 200 OK
ETag: "xyz789"
Last-Modified: Mon, 15 Jan 2024 11:00:00 GMT
Content-Length: 45632

<?xml version="1.0"?>
<rss version="2.0">...</rss>
```

## Exponential Backoff

При последовательных ошибках модуль увеличивает интервал до следующей попытки:

| Номер ошибки | Backoff (секунды) | Backoff (минуты) |
|--------------|-------------------|------------------|
| 1            | 120               | 2                |
| 2            | 240               | 4                |
| 3            | 480               | 8                |
| 4            | 900 (макс.)       | 15               |

При успешном запросе счётчик ошибок и backoff сбрасываются.

### Поддержка Retry-After

Для статусов `429 Too Many Requests` и `503 Service Unavailable` модуль уважает заголовок `Retry-After`:

```
HTTP/1.1 429 Too Many Requests
Retry-After: 3600
```

Источник будет заблокирован на указанное время (3600 секунд).

## Метрики

После выполнения доступны детальные метрики:

```php
$metrics = $fetchRunner->getMetrics();
// [
//     'fetch_total' => 10,      // Всего запросов
//     'fetch_200' => 6,         // Успешных (200 OK)
//     'fetch_304' => 3,         // Not Modified (304)
//     'fetch_errors' => 1,      // Ошибки (4xx, 5xx, сеть)
//     'parse_errors' => 0,      // Ошибки парсинга
//     'items_parsed' => 142,    // Элементов извлечено
// ]
```

## Content Hash

Каждый `RawItem` содержит стабильный MD5 хэш для дедупликации:

```php
$item->contentHash; // "bdecc80336a3d349750d7247c6d7c97a"
```

Хэш вычисляется из:
1. **GUID** (если доступен)
2. Или комбинация: **Link + Title + Content** (первые 500 символов)

Хэш устойчив к:
- Изменениям форматирования (пробелы, переносы)
- Различиям в HTML тегах
- Изменениям кодировки

## Интеграция с конвейером

Модуль fetch — первый этап обработки:

```
┌────────┐    ┌─────────┐    ┌──────────────┐    ┌─────────┐
│ Fetch  │───▶│ Analyze │───▶│ Deduplicate  │───▶│ Publish │
└────────┘    └─────────┘    └──────────────┘    └─────────┘
   │              │                  │                  │
RawItem[]   Filtering         By contentHash      Telegram
+ State     + Scoring         + Store seen        Bot API
```

Следующие этапы получают:
- `RawItem[]` с нормализованными данными
- `contentHash` для проверки дубликатов
- `FeedState` для мониторинга источников

## Мониторинг

### SQL запросы для мониторинга

```sql
-- Статистика источников
SELECT 
    COUNT(*) AS total,
    SUM(CASE WHEN last_status = 200 THEN 1 ELSE 0 END) AS success_200,
    SUM(CASE WHEN last_status = 304 THEN 1 ELSE 0 END) AS not_modified_304,
    SUM(CASE WHEN last_status >= 400 THEN 1 ELSE 0 END) AS errors,
    SUM(CASE WHEN backoff_until > NOW() THEN 1 ELSE 0 END) AS in_backoff
FROM rss2tlg_feed_state;

-- Источники с ошибками
SELECT feed_id, url, last_status, error_count, backoff_until
FROM rss2tlg_feed_state
WHERE last_status >= 400
ORDER BY error_count DESC;

-- Источники в backoff
SELECT 
    feed_id,
    url,
    TIMESTAMPDIFF(SECOND, NOW(), backoff_until) AS remaining_sec
FROM rss2tlg_feed_state
WHERE backoff_until > NOW()
ORDER BY remaining_sec DESC;
```

### Сброс ошибок

```sql
-- Сбросить ошибки для всех источников
UPDATE rss2tlg_feed_state SET error_count = 0, backoff_until = NULL;

-- Сбросить для конкретного источника
UPDATE rss2tlg_feed_state 
SET error_count = 0, backoff_until = NULL 
WHERE feed_id = 1;
```

## Требования

- PHP 8.1+
- MySQL 5.7+ / MySQL 8.0+ / MariaDB 10.3+
- SimplePie 1.8+
- Guzzle 7.8+
- Расширения: `json`, `curl`, `pdo`, `pdo_mysql`, `dom`, `mbstring`

## Документация

- **[INSTALL.md](INSTALL.md)** — Подробная инструкция по установке
- **[docs/README.md](docs/README.md)** — Полная документация модуля
- **[docs/API.md](docs/API.md)** — Справочник по API классов
- **[docs/schema.sql](docs/schema.sql)** — SQL схема таблицы feed_state
- **[docs/config.example.json](docs/config.example.json)** — Пример конфигурации

## Примеры

- `examples/rss2tlg/quick_test.php` — Быстрый тест DTO (без БД)
- `examples/rss2tlg/parse_rss_demo.php` — Демо парсинга RSS (без БД)
- `examples/rss2tlg/fetch_single.php` — Опрос одного источника
- `examples/rss2tlg/fetch_example.php` — Опрос всех источников из конфига

## Unit тесты

```bash
# Установка PHPUnit
composer require --dev phpunit/phpunit

# Запуск тестов
vendor/bin/phpunit tests/Rss2Tlg/DTO/
```

## Производительность

- **Conditional GET**: Экономия ~90% трафика при 304 Not Modified
- **SimplePie кеш**: Ускорение парсинга повторяющихся структур
- **Backoff**: Автоматическое снижение нагрузки на проблемные источники
- **Concurrent safe**: Безопасная параллельная обработка через БД состояние

### Benchmarks

Типичная производительность на современном сервере:

| Операция | Время | Элементов/сек |
|----------|-------|---------------|
| Fetch (200 OK) | 1-3 сек | 15-50 |
| Fetch (304 Not Modified) | 0.1-0.3 сек | N/A |
| Parse RSS (30 items) | 0.5-1.5 сек | 20-60 |

## Troubleshooting

### Ошибка "Class not found"
```bash
composer dump-autoload
```

### Ошибка подключения к БД
```bash
mysql -u root -p rss2tlg  # Проверьте подключение
```

### SimplePie ошибка парсинга
```bash
curl -s "URL" | xmllint --noout -  # Проверьте валидность XML
```

### Источник постоянно в backoff
```sql
UPDATE rss2tlg_feed_state SET error_count = 0, backoff_until = NULL WHERE feed_id = 1;
```

## Лицензия

Proprietary

## Автор

Разработано как часть проекта **Rss2Tlg** (RSS to Telegram Aggregator).

---

**Следующие этапы:**
1. Анализ и фильтрация контента
2. Дедупликация элементов
3. Публикация в Telegram через Bot API

# Rss2Tlg Fetch Module

Модуль для эффективного опроса RSS/Atom лент с поддержкой Conditional GET, backoff и метрик.

## 🔥 Последнее обновление (2025-11-09)

✅ **Unicode Fix:** Все модули AI Pipeline v2.0 исправлены для корректного хранения кириллицы в БД  
📊 **Протестировано:** 10 вызовов `json_encode()` заменены на `json_encode(..., JSON_UNESCAPED_UNICODE)`  
🎯 **Результат:** Кириллические данные теперь сохраняются в читаемом виде без Unicode Escape  

Подробности: [UNICODE_FIX_REPORT.md](./UNICODE_FIX_REPORT.md)

---

## Возможности

- ✅ **Conditional GET**: Оптимизация трафика через ETag и Last-Modified
- ✅ **Exponential Backoff**: Автоматическое управление rate limiting при ошибках
- ✅ **SimplePie Integration**: Универсальная поддержка RSS 0.9x/1.0/2.0 и Atom 0.3/1.0
- ✅ **Метрики**: Детальная статистика операций (200, 304, ошибки, парсинг)
- ✅ **Идемпотентность**: Безопасная параллельная обработка источников
- ✅ **Нормализация**: Единый формат данных для последующих этапов конвейера
- ✅ **Content Hash**: Стабильный хэш для дедупликации элементов

## Архитектура

```
Rss2Tlg/
├── DTO/
│   ├── FeedConfig.php      # Конфигурация источника
│   ├── FeedState.php       # Состояние источника (ETag, Last-Modified, backoff)
│   ├── RawItem.php         # Нормализованный элемент ленты
│   └── FetchResult.php     # Результат операции fetch
├── FetchRunner.php         # Основной класс для опроса источников
├── FeedStateRepository.php # Репозиторий для работы с БД
└── docs/
    ├── README.md           # Документация (этот файл)
    ├── schema.sql          # SQL схема таблицы feed_state
    └── config.example.json # Пример конфигурации источников
```

## Установка

### 1. Создание таблицы БД

Выполните SQL скрипт для создания таблицы состояния источников:

```bash
mysql -u root -p rss2tlg < src/Rss2Tlg/docs/schema.sql
```

### 2. Создание директории кеша

Создайте директорию для кеша SimplePie:

```bash
mkdir -p /tmp/rss2tlg_cache
chmod 755 /tmp/rss2tlg_cache
```

### 3. Подготовка конфигурации

Скопируйте пример конфигурации и настройте параметры:

```bash
cp src/Rss2Tlg/docs/config.example.json config/rss2tlg.json
```

Отредактируйте `config/rss2tlg.json` и укажите:
- URL RSS/Atom лент
- Параметры подключения к БД
- Пути для кеша и логов

## Использование

### Базовый пример

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Component\MySQL;
use App\Component\Logger;
use App\Component\Config\ConfigLoader;
use App\Rss2Tlg\FetchRunner;
use App\Rss2Tlg\DTO\FeedConfig;

// Загрузка конфигурации
$config = ConfigLoader::load(__DIR__ . '/config/rss2tlg.json');

// Инициализация БД
$db = new MySQL($config['database']);

// Инициализация логгера
$logger = new Logger([
    'log_file' => $config['logging']['file'],
    'log_level' => $config['logging']['level'],
]);

// Создание директории кеша
$cacheDir = $config['cache']['directory'];
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

// Парсинг конфигурации источников
$feeds = array_map(
    fn($feedData) => FeedConfig::fromArray($feedData),
    $config['feeds']
);

// Инициализация FetchRunner
$fetchRunner = new FetchRunner($db, $cacheDir, $logger);

// Запуск опроса всех источников
$results = $fetchRunner->runForAllFeeds($feeds);

// Обработка результатов
foreach ($results as $feedId => $result) {
    if ($result->isSuccessful()) {
        echo sprintf(
            "Feed #%d: SUCCESS - %d items fetched\n",
            $feedId,
            $result->getItemCount()
        );
        
        foreach ($result->getValidItems() as $item) {
            echo sprintf(
                "  - %s (%s)\n",
                $item->title ?? 'No title',
                $item->link ?? 'No link'
            );
        }
    } elseif ($result->isNotModified()) {
        echo sprintf("Feed #%d: NOT MODIFIED (304)\n", $feedId);
    } else {
        echo sprintf(
            "Feed #%d: ERROR - Status %d\n",
            $feedId,
            $result->state->lastStatus
        );
    }
}

// Метрики
print_r($fetchRunner->getMetrics());
```

### Опрос одного источника

```php
<?php

use App\Rss2Tlg\DTO\FeedConfig;

// Конфигурация источника
$feedConfig = FeedConfig::fromArray([
    'id' => 1,
    'url' => 'https://news.ycombinator.com/rss',
    'enabled' => true,
    'timeout' => 30,
    'retries' => 3,
    'polling_interval' => 300,
    'headers' => [
        'User-Agent' => 'Rss2Tlg/1.0',
    ],
    'parser_options' => [
        'max_items' => 50,
        'enable_cache' => true,
    ],
]);

// Опрос источника
$result = $fetchRunner->runForFeed($feedConfig);

if ($result->isSuccessful()) {
    echo "Items fetched: " . $result->getItemCount() . "\n";
    
    foreach ($result->items as $item) {
        echo $item->title . "\n";
        echo $item->link . "\n";
        echo "Hash: " . $item->contentHash . "\n";
        echo "---\n";
    }
}
```

## Conditional GET

Модуль автоматически использует Conditional GET для снижения трафика:

1. При первом запросе сохраняет `ETag` и `Last-Modified` из ответа
2. При следующих запросах отправляет:
   - `If-None-Match: <etag>`
   - `If-Modified-Since: <last-modified>`
3. Если источник не изменился, сервер возвращает **304 Not Modified** без тела ответа
4. Модуль пропускает парсинг и возвращает пустой список элементов

### Пример логов

**200 OK (новые данные):**
```
[2024-01-15 10:00:00] INFO: Начало fetch источника {"feed_id":1,"url":"https://example.com/rss"}
[2024-01-15 10:00:01] INFO: Источник вернул новые данные (200) {"feed_id":1,"body_size":45632,"duration":1.234}
[2024-01-15 10:00:02] INFO: Лента успешно распарсена {"feed_id":1,"items_count":25}
```

**304 Not Modified (без изменений):**
```
[2024-01-15 10:05:00] INFO: Начало fetch источника {"feed_id":1,"url":"https://example.com/rss"}
[2024-01-15 10:05:00] INFO: Источник не изменился (304) {"feed_id":1,"duration":0.123}
```

**Ошибка с backoff:**
```
[2024-01-15 10:10:00] ERROR: Источник вернул ошибку {"feed_id":2,"status_code":503,"duration":2.456}
[2024-01-15 10:10:00] INFO: Установлен backoff до 2024-01-15 10:12:00 {"feed_id":2,"backoff_sec":120}
```

## Exponential Backoff

При последовательных ошибках модуль автоматически увеличивает интервал до следующей попытки:

| Номер ошибки | Backoff (секунды) |
|--------------|-------------------|
| 1            | 120               |
| 2            | 240               |
| 3            | 480               |
| 4+           | 900 (макс. 15 мин)|

При успешном запросе счётчик ошибок сбрасывается.

### Обработка Retry-After

Для статусов **429 Too Many Requests** и **503 Service Unavailable** модуль уважает заголовок `Retry-After`:

```
HTTP/1.1 429 Too Many Requests
Retry-After: 3600
```

Источник будет заблокирован на указанное время (3600 секунд = 1 час).

## Метрики

После выполнения `runForAllFeeds()` доступны метрики:

```php
$metrics = $fetchRunner->getMetrics();

print_r($metrics);
// Array
// (
//     [fetch_total] => 10      // Всего запросов
//     [fetch_200] => 6         // Успешных (200 OK)
//     [fetch_304] => 3         // Not Modified (304)
//     [fetch_errors] => 1      // Ошибки (4xx, 5xx, сеть)
//     [parse_errors] => 0      // Ошибки парсинга
//     [items_parsed] => 142    // Всего элементов извлечено
// )
```

## DTO классы

### FeedConfig

Конфигурация источника с валидацией:

```php
$config = FeedConfig::fromArray([
    'id' => 1,
    'url' => 'https://example.com/feed.xml',
    'enabled' => true,
    'timeout' => 30,
    'retries' => 3,
    'polling_interval' => 300,
    'headers' => ['User-Agent' => 'MyBot/1.0'],
    'parser_options' => ['max_items' => 50],
    'proxy' => null,
]);
```

### FeedState

Состояние источника (сохраняется в БД):

```php
$state = FeedState::createInitial();
$state = $state->withSuccessfulFetch($etag, $lastModified, 200);
$state = $state->withFailedFetch(503, 3600);

if ($state->isInBackoff()) {
    echo "Backoff remaining: " . $state->getBackoffRemaining() . " sec\n";
}
```

### RawItem

Нормализованный элемент ленты:

```php
$item = RawItem::fromSimplePieItem($simplePieItem);

echo $item->title;          // Заголовок
echo $item->link;           // Ссылка
echo $item->summary;        // Краткое описание
echo $item->content;        // Полный контент
echo $item->contentHash;    // MD5 хэш для дедупликации
print_r($item->authors);    // Массив авторов
print_r($item->categories); // Массив категорий

if ($item->isValid()) {
    // Элемент валиден (есть GUID/ссылка и контент)
}
```

### FetchResult

Результат операции fetch:

```php
if ($result->isSuccessful()) {
    foreach ($result->getValidItems() as $item) {
        // Обработка элемента
    }
}

$duration = $result->getMetric('duration');
$itemsCount = $result->getItemCount();
```

## Интеграция с конвейером

Модуль fetch — первый этап конвейера обработки RSS/Atom:

```
[Fetch] → [Analyze] → [Deduplicate] → [Publish]
   ↓
RawItem[] → анализ контента → фильтрация дубликатов → публикация в Telegram
```

Следующие этапы получают:
- Массив `RawItem` с нормализованными данными
- `contentHash` для быстрой проверки дубликатов
- Обновлённое `FeedState` для мониторинга

## Требования

- PHP 8.1+
- MySQL 5.7+ / MySQL 8.0+
- SimplePie 1.8+
- Guzzle 7.8+

## Производительность

- **Conditional GET**: Экономия ~90% трафика при отсутствии обновлений (304)
- **SimplePie кеш**: Ускорение парсинга повторяющихся структур
- **Backoff**: Снижение нагрузки на проблемные источники
- **Параллельная обработка**: Безопасна благодаря состоянию в БД

## Troubleshooting

### Ошибка "Таблица не найдена"

Выполните SQL скрипт:
```bash
mysql -u root -p rss2tlg < src/Rss2Tlg/docs/schema.sql
```

### Ошибка записи в кеш

Проверьте права доступа:
```bash
chmod 755 /tmp/rss2tlg_cache
```

### Источник постоянно в backoff

Проверьте доступность URL и сбросьте ошибки:
```sql
UPDATE rss2tlg_feed_state 
SET error_count = 0, backoff_until = NULL 
WHERE feed_id = 1;
```

### SimplePie ошибка парсинга

Проверьте корректность XML:
```bash
curl -s https://example.com/feed.xml | xmllint --noout -
```

## Лицензия

Proprietary

## Автор

Разработано как часть проекта Rss2Tlg (RSS to Telegram).

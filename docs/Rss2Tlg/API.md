# Rss2Tlg Fetch API Documentation

Документация по программному интерфейсу модуля fetch.

## Основные классы

### FetchRunner

Основной класс для опроса RSS/Atom источников.

#### Конструктор

```php
public function __construct(
    MySQL $db,           // Подключение к БД
    string $cacheDir,    // Директория для кеша SimplePie
    ?Logger $logger = null // Логгер (опционально)
)
```

**Пример:**
```php
use App\Component\MySQL;
use App\Component\Logger;
use App\Rss2Tlg\FetchRunner;

$db = new MySQL([
    'host' => 'localhost',
    'database' => 'rss2tlg',
    'username' => 'root',
    'password' => '',
]);

$logger = new Logger(['log_file' => 'logs/fetch.log']);

$fetchRunner = new FetchRunner($db, '/tmp/cache', $logger);
```

#### Методы

##### runForAllFeeds()

Опрашивает все источники из списка.

```php
public function runForAllFeeds(array $feeds): array
```

**Параметры:**
- `$feeds` - Массив объектов `FeedConfig`

**Возвращает:**
- Ассоциативный массив `[feed_id => FetchResult]`

**Пример:**
```php
$feeds = [
    FeedConfig::fromArray(['id' => 1, 'url' => 'https://example.com/rss']),
    FeedConfig::fromArray(['id' => 2, 'url' => 'https://example.com/atom']),
];

$results = $fetchRunner->runForAllFeeds($feeds);

foreach ($results as $feedId => $result) {
    if ($result->isSuccessful()) {
        echo "Feed #{$feedId}: {$result->getItemCount()} items\n";
    }
}
```

##### runForFeed()

Опрашивает один источник.

```php
public function runForFeed(FeedConfig $config): FetchResult
```

**Параметры:**
- `$config` - Конфигурация источника

**Возвращает:**
- Объект `FetchResult` с результатом операции

**Пример:**
```php
$config = FeedConfig::fromArray([
    'id' => 1,
    'url' => 'https://news.ycombinator.com/rss',
    'enabled' => true,
    'timeout' => 30,
    'retries' => 3,
    'polling_interval' => 300,
    'headers' => ['User-Agent' => 'MyBot/1.0'],
    'parser_options' => ['max_items' => 50],
]);

$result = $fetchRunner->runForFeed($config);

if ($result->isSuccessful()) {
    foreach ($result->items as $item) {
        echo $item->title . "\n";
    }
}
```

##### getMetrics()

Возвращает метрики выполнения.

```php
public function getMetrics(): array
```

**Возвращает:**
- Массив с метриками:
  - `fetch_total` - Всего запросов
  - `fetch_200` - Успешных (200 OK)
  - `fetch_304` - Not Modified
  - `fetch_errors` - Ошибки
  - `parse_errors` - Ошибки парсинга
  - `items_parsed` - Элементов извлечено

**Пример:**
```php
$metrics = $fetchRunner->getMetrics();
echo "Total requests: {$metrics['fetch_total']}\n";
echo "Items parsed: {$metrics['items_parsed']}\n";
```

---

## DTO классы

### FeedConfig

Конфигурация RSS/Atom источника.

#### Создание

```php
// Из массива (с валидацией)
$config = FeedConfig::fromArray([
    'id' => 1,
    'url' => 'https://example.com/rss',
    'enabled' => true,
    'timeout' => 30,
    'retries' => 3,
    'polling_interval' => 300,
    'headers' => ['User-Agent' => 'Bot/1.0'],
    'parser_options' => ['max_items' => 50, 'enable_cache' => true],
    'proxy' => null,
]);

// Прямое создание
$config = new FeedConfig(
    id: 1,
    url: 'https://example.com/rss',
    enabled: true,
    timeout: 30,
    retries: 3,
    pollingInterval: 300,
    headers: [],
    parserOptions: [],
    proxy: null
);
```

#### Свойства

| Свойство | Тип | Описание |
|----------|-----|----------|
| `id` | `int` | Уникальный ID источника |
| `url` | `string` | URL RSS/Atom ленты |
| `enabled` | `bool` | Активен ли источник |
| `timeout` | `int` | Таймаут запроса (сек) |
| `retries` | `int` | Количество повторов |
| `pollingInterval` | `int` | Интервал опроса (сек) |
| `headers` | `array` | HTTP заголовки |
| `parserOptions` | `array` | Опции парсера |
| `proxy` | `?string` | Прокси (опционально) |

#### Методы

```php
// Конвертация в массив
$array = $config->toArray();
```

---

### FeedState

Состояние источника (ETag, Last-Modified, backoff).

#### Создание

```php
// Начальное состояние
$state = FeedState::createInitial();

// Из массива
$state = FeedState::fromArray([
    'etag' => '"abc123"',
    'last_modified' => 'Mon, 15 Jan 2024 10:00:00 GMT',
    'last_status' => 200,
    'error_count' => 0,
    'backoff_until' => null,
    'fetched_at' => time(),
]);

// Прямое создание
$state = new FeedState(
    etag: '"abc123"',
    lastModified: 'Mon, 15 Jan 2024 10:00:00 GMT',
    lastStatus: 200,
    errorCount: 0,
    backoffUntil: null,
    fetchedAt: time()
);
```

#### Свойства

| Свойство | Тип | Описание |
|----------|-----|----------|
| `etag` | `?string` | ETag из ответа |
| `lastModified` | `?string` | Last-Modified из ответа |
| `lastStatus` | `int` | HTTP статус последнего запроса |
| `errorCount` | `int` | Счётчик ошибок |
| `backoffUntil` | `?int` | Unix timestamp до которого блокировка |
| `fetchedAt` | `int` | Unix timestamp последнего запроса |

#### Методы

```php
// Обновление после успешного fetch
$newState = $state->withSuccessfulFetch(
    etag: '"new-etag"',
    lastModified: 'New Date',
    statusCode: 200
);

// Обновление после ошибки
$newState = $state->withFailedFetch(
    statusCode: 503,
    backoffSeconds: 3600 // Опционально
);

// Проверка backoff
if ($state->isInBackoff()) {
    $remaining = $state->getBackoffRemaining(); // секунды
    echo "Backoff: {$remaining} sec remaining\n";
}

// Конвертация в массив
$array = $state->toArray();
```

---

### RawItem

Нормализованный элемент RSS/Atom ленты.

#### Создание

```php
// Из SimplePie Item (основной способ)
$rawItem = RawItem::fromSimplePieItem($simplePieItem);

// Прямое создание (редко используется)
$rawItem = new RawItem(
    guid: 'unique-id',
    link: 'https://example.com/article',
    title: 'Article Title',
    summary: 'Short description',
    content: 'Full content',
    authors: ['John Doe'],
    categories: ['Tech', 'PHP'],
    enclosure: ['url' => 'https://...', 'type' => 'image/jpeg', 'length' => 12345],
    pubDate: time(),
    contentHash: 'md5hash'
);
```

#### Свойства

| Свойство | Тип | Описание |
|----------|-----|----------|
| `guid` | `?string` | Глобальный ID элемента |
| `link` | `?string` | Ссылка на элемент |
| `title` | `?string` | Заголовок |
| `summary` | `?string` | Краткое описание |
| `content` | `?string` | Полный контент |
| `authors` | `array` | Список авторов |
| `categories` | `array` | Список категорий |
| `enclosure` | `?array` | Вложение (медиа) |
| `pubDate` | `?int` | Дата публикации (timestamp) |
| `contentHash` | `string` | MD5 хэш для дедупликации |

#### Методы

```php
// Проверка валидности
if ($item->isValid()) {
    // Элемент имеет обязательные поля
}

// Конвертация в массив
$array = $item->toArray();
```

---

### FetchResult

Результат операции fetch.

#### Создание

```php
// Успешный fetch (через фабричный метод)
$result = FetchResult::success(
    feedId: 1,
    state: $newState,
    items: [$item1, $item2],
    metrics: ['duration' => 1.234, 'body_size' => 45632]
);

// 304 Not Modified
$result = FetchResult::notModified(
    feedId: 1,
    state: $newState,
    metrics: ['duration' => 0.123]
);

// Ошибка
$result = FetchResult::error(
    feedId: 1,
    state: $newState,
    metrics: ['status_code' => 503, 'error' => 'Service Unavailable']
);

// Прямое создание
$result = new FetchResult(
    feedId: 1,
    state: $newState,
    items: [],
    metrics: []
);
```

#### Свойства

| Свойство | Тип | Описание |
|----------|-----|----------|
| `feedId` | `int` | ID источника |
| `state` | `FeedState` | Обновлённое состояние |
| `items` | `array` | Список `RawItem` |
| `metrics` | `array` | Метрики операции |

#### Методы

```php
// Проверка статуса
if ($result->isSuccessful()) { /* 200-299 */ }
if ($result->isNotModified()) { /* 304 */ }
if ($result->isError()) { /* 4xx, 5xx, 0 */ }

// Получение элементов
$count = $result->getItemCount();
$validItems = $result->getValidItems(); // Только валидные

// Метрики
$duration = $result->getMetric('duration', 0);
$bodySize = $result->getMetric('body_size');

// Конвертация в массив
$array = $result->toArray();
```

---

## FeedStateRepository

Репозиторий для работы с состоянием источников в БД.

#### Конструктор

```php
public function __construct(
    MySQL $db,
    ?Logger $logger = null
)
```

#### Методы

```php
use App\Rss2Tlg\FeedStateRepository;

$repo = new FeedStateRepository($db, $logger);

// Получить состояние по ID
$state = $repo->getByFeedId(1);

// Получить состояние по URL
$state = $repo->getByUrl('https://example.com/rss');

// Сохранить состояние (INSERT или UPDATE)
$repo->save(
    feedId: 1,
    url: 'https://example.com/rss',
    state: $state
);

// Удалить состояние
$repo->delete(1);

// Получить все источники в backoff
$inBackoff = $repo->getInBackoff();
// Returns: [['feed_id' => 1, 'backoff_until' => 1705320000], ...]

// Сбросить ошибки
$repo->resetErrors(1);
```

---

## Примеры использования

### Пример 1: Базовый опрос одного источника

```php
use App\Component\MySQL;
use App\Component\Logger;
use App\Rss2Tlg\FetchRunner;
use App\Rss2Tlg\DTO\FeedConfig;

$db = new MySQL(['database' => 'rss2tlg', 'username' => 'root', 'password' => '']);
$logger = new Logger(['log_file' => 'logs/fetch.log']);
$fetchRunner = new FetchRunner($db, '/tmp/cache', $logger);

$config = FeedConfig::fromArray([
    'id' => 1,
    'url' => 'https://news.ycombinator.com/rss',
    'enabled' => true,
    'timeout' => 30,
    'retries' => 3,
    'polling_interval' => 300,
    'headers' => [],
    'parser_options' => ['max_items' => 50],
]);

$result = $fetchRunner->runForFeed($config);

if ($result->isSuccessful()) {
    echo "Items: " . $result->getItemCount() . "\n";
    foreach ($result->items as $item) {
        echo "- {$item->title}\n";
    }
} elseif ($result->isNotModified()) {
    echo "No updates (304)\n";
} else {
    echo "Error: " . $result->state->lastStatus . "\n";
}
```

### Пример 2: Опрос множества источников

```php
use App\Component\Config\ConfigLoader;

$config = ConfigLoader::load('config/rss2tlg.json');

$feeds = array_map(
    fn($data) => FeedConfig::fromArray($data),
    $config['feeds']
);

$results = $fetchRunner->runForAllFeeds($feeds);

foreach ($results as $feedId => $result) {
    echo "Feed #{$feedId}: ";
    
    if ($result->isSuccessful()) {
        echo "✓ {$result->getItemCount()} items\n";
    } elseif ($result->isNotModified()) {
        echo "⟳ Not Modified\n";
    } else {
        echo "✗ Error {$result->state->lastStatus}\n";
    }
}

print_r($fetchRunner->getMetrics());
```

### Пример 3: Обработка backoff

```php
$state = $repo->getByFeedId(1);

if ($state === null) {
    $state = FeedState::createInitial();
}

if ($state->isInBackoff()) {
    $remaining = $state->getBackoffRemaining();
    echo "Feed in backoff for {$remaining} seconds\n";
    exit;
}

$result = $fetchRunner->runForFeed($config);

// Состояние автоматически сохраняется в БД через FetchRunner
```

### Пример 4: Кастомная обработка элементов

```php
$result = $fetchRunner->runForFeed($config);

if ($result->isSuccessful()) {
    foreach ($result->getValidItems() as $item) {
        // Фильтрация по категориям
        if (in_array('PHP', $item->categories)) {
            // Обработка элемента
            processItem($item);
        }
        
        // Дедупликация по хэшу
        if (!isDuplicate($item->contentHash)) {
            storeItem($item);
        }
    }
}
```

---

## Обработка ошибок

Все классы выбрасывают исключения при критических ошибках:

```php
try {
    $config = FeedConfig::fromArray($data);
} catch (\InvalidArgumentException $e) {
    echo "Invalid config: " . $e->getMessage() . "\n";
}

try {
    $result = $fetchRunner->runForFeed($config);
} catch (\Exception $e) {
    echo "Fetch failed: " . $e->getMessage() . "\n";
}
```

FetchRunner обрабатывает ошибки внутри и возвращает `FetchResult` с состоянием ошибки вместо выброса исключения.

---

## Метрики и логирование

FetchRunner автоматически логирует:
- Начало/конец операции fetch
- HTTP статусы
- Количество элементов
- Ошибки и backoff события

Метрики доступны через `getMetrics()`:

```php
$metrics = $fetchRunner->getMetrics();

echo "Total: {$metrics['fetch_total']}\n";
echo "Success (200): {$metrics['fetch_200']}\n";
echo "Not Modified (304): {$metrics['fetch_304']}\n";
echo "Errors: {$metrics['fetch_errors']}\n";
echo "Parse Errors: {$metrics['parse_errors']}\n";
echo "Items Parsed: {$metrics['items_parsed']}\n";
```

---

## Исключения

Модуль Rss2Tlg использует иерархию специализированных исключений для типизированной обработки ошибок.

### Иерархия исключений

```
RuntimeException
└── Rss2TlgException (базовое для всех исключений модуля)
    ├── FeedConfigException (ошибки конфигурации фидов)
    │   └── FeedValidationException (валидация параметров фида)
    ├── PromptException (ошибки работы с промптами)
    │   ├── PromptNotFoundException (отсутствие файла промпта)
    │   └── PromptLoadException (ошибка загрузки промпта)
    ├── AIAnalysisException (ошибки AI-анализа)
    │   ├── AIParsingException (парсинг JSON ответа от AI)
    │   └── AIValidationException (валидация результата анализа)
    └── RepositoryException (ошибки работы с БД)
        └── SaveException (ошибки сохранения данных)
```

### Использование исключений

#### Feed исключения

```php
use App\Rss2Tlg\Exception\Feed\FeedConfigException;
use App\Rss2Tlg\Exception\Feed\FeedValidationException;

try {
    $config = FeedConfig::fromArray($data);
} catch (FeedValidationException $e) {
    // Некорректные параметры фида (отсутствует id, url, или некорректный timeout)
    $logger->error("Feed validation failed: " . $e->getMessage());
} catch (FeedConfigException $e) {
    // Общая ошибка конфигурации
    $logger->error("Feed config error: " . $e->getMessage());
}
```

#### Prompt исключения

```php
use App\Rss2Tlg\Exception\Prompt\PromptException;
use App\Rss2Tlg\Exception\Prompt\PromptNotFoundException;
use App\Rss2Tlg\Exception\Prompt\PromptLoadException;

try {
    $prompt = $promptManager->getSystemPrompt('INoT_v1');
} catch (PromptNotFoundException $e) {
    // Файл промпта не найден
    $logger->error("Prompt file not found: " . $e->getMessage());
} catch (PromptLoadException $e) {
    // Ошибка чтения файла (нет прав доступа и т.д.)
    $logger->error("Failed to load prompt: " . $e->getMessage());
} catch (PromptException $e) {
    // Общая ошибка работы с промптами
    $logger->error("Prompt error: " . $e->getMessage());
}
```

#### AI Analysis исключения

```php
use App\Rss2Tlg\Exception\AI\AIAnalysisException;
use App\Rss2Tlg\Exception\AI\AIParsingException;
use App\Rss2Tlg\Exception\AI\AIValidationException;

try {
    $analysis = $aiService->analyze($item);
} catch (AIParsingException $e) {
    // JSON ответ от AI невалидный или не соответствует схеме
    $logger->error("AI response parsing failed: " . $e->getMessage());
} catch (AIValidationException $e) {
    // Результат анализа не прошел валидацию
    $logger->error("AI result validation failed: " . $e->getMessage());
} catch (AIAnalysisException $e) {
    // Общая ошибка AI-анализа (сеть, API лимиты и т.д.)
    $logger->error("AI analysis error: " . $e->getMessage());
}
```

#### Repository исключения

```php
use App\Rss2Tlg\Exception\Repository\RepositoryException;
use App\Rss2Tlg\Exception\Repository\SaveException;

try {
    $itemId = $itemRepository->save($feedId, $item);
} catch (SaveException $e) {
    // Ошибка сохранения в БД (constraint violation, connection lost)
    $logger->error("Failed to save item: " . $e->getMessage());
} catch (RepositoryException $e) {
    // Общая ошибка репозитория
    $logger->error("Repository error: " . $e->getMessage());
}
```

#### Отлов всех исключений модуля

```php
use App\Rss2Tlg\Exception\Rss2TlgException;

try {
    // Любая операция модуля Rss2Tlg
    $result = $someService->doSomething();
} catch (Rss2TlgException $e) {
    // Ловим все исключения модуля одним блоком
    $logger->error("Rss2Tlg module error: " . $e->getMessage());
    // Можно проверить конкретный тип через instanceof
    if ($e instanceof AIParsingException) {
        // Специфичная обработка для AI парсинга
    }
}
```

### Рекомендации по обработке

1. **Типизированный catch**: Ловите специфичные исключения первыми, базовые - последними
2. **Логирование**: Всегда логируйте исключения с контекстом
3. **Graceful degradation**: При ошибках AI или промптов используйте fallback логику
4. **Retry logic**: Для `SaveException` и сетевых ошибок используйте повторные попытки
5. **Validation**: Проверяйте данные до операций, используйте validation исключения

### Примеры graceful degradation

```php
// Пример с fallback при ошибке AI
try {
    $analysis = $aiService->analyze($item);
} catch (AIParsingException $e) {
    $logger->warning("AI parsing failed, using basic analysis");
    $analysis = $basicAnalyzer->analyze($item);
}

// Пример с retry для сохранения
$maxRetries = 3;
$attempt = 0;

while ($attempt < $maxRetries) {
    try {
        $itemId = $itemRepository->save($feedId, $item);
        break; // Успех
    } catch (SaveException $e) {
        $attempt++;
        if ($attempt >= $maxRetries) {
            $logger->error("Failed to save after {$maxRetries} attempts");
            throw $e;
        }
        sleep(1); // Задержка перед повтором
    }
}
```

---

## AI Pipeline: TranslationService

### Описание

`TranslationService` - третий этап AI Pipeline для перевода новостей на множественные языки.

### Конструктор

```php
public function __construct(
    MySQL $db,
    OpenRouter $openRouter,
    array $config,
    ?Logger $logger = null
)
```

**Параметры:**
- `$db` - Подключение к БД
- `$openRouter` - Клиент OpenRouter API
- `$config` - Конфигурация модуля (см. ниже)
- `$logger` - Логгер (опционально)

**Конфигурация ($config):**
```php
[
    'enabled' => true,                     // Включен ли модуль
    'target_languages' => ['ru', 'uk'],    // Целевые языки (ISO 639-1)
    'models' => [                          // AI модели в порядке приоритета
        'anthropic/claude-3.5-sonnet',
        'deepseek/deepseek-chat',
    ],
    'retry_count' => 2,                    // Количество повторов (default: 2)
    'timeout' => 120,                      // Таймаут в секундах (default: 120)
    'fallback_strategy' => 'sequential',   // 'sequential' или 'random'
    'prompt_file' => '/path/to/prompt.txt' // Путь к файлу с промптом
]
```

### Методы

#### processItem()

Переводит одну новость на все целевые языки.

```php
public function processItem(int $itemId): bool
```

**Параметры:**
- `$itemId` - ID новости

**Возвращает:**
- `true` - если все переводы выполнены успешно
- `false` - если произошли ошибки

#### processBatch()

Переводит несколько новостей.

```php
public function processBatch(array $itemIds): array
```

**Возвращает:**
```php
[
    'success' => 5,
    'failed' => 0,
    'skipped' => 0,
]
```

#### getMetrics()

```php
public function getMetrics(): array
```

**Возвращает метрики обработки:**
- `total_processed` - Всего обработано новостей
- `translations_created` - Создано переводов
- `total_tokens` - Использовано токенов
- `languages_processed` - Массив [язык => количество]
- `model_attempts` - Массив [модель => попыток]


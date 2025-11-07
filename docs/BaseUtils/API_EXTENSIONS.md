# Расширения API готовых классов

Документация новых методов, добавленных для поддержки проекта Rss2Tlg.

---

## MySQL::escape()

### Назначение
Экранирует строку для безопасного использования в SQL запросах.

### Сигнатура
```php
public function escape(string $value): string
```

### Параметры
- `$value` (string) - строка для экранирования

### Возвращает
- (string) - экранированная строка **с кавычками**

### Использование

```php
$db = new MySQL($config, $logger);

// Экранирование значений
$url = "https://example.com/feed?id=1&type='rss'";
$urlEscaped = $db->escape($url);
// Результат: "'https://example.com/feed?id=1&type=\'rss\''"

// Использование в SQL
$sql = "INSERT INTO feeds (url) VALUES ({$urlEscaped})";
$db->execute($sql);
```

### ⚠️ Важно

1. **Возвращает строку с кавычками**
   ```php
   $escaped = $db->escape("test"); // "'test'" (с кавычками)
   ```

2. **NULL обрабатывается отдельно**
   ```php
   $value = null;
   $escaped = $value !== null ? $db->escape($value) : 'NULL';
   ```

3. **Предпочтительнее prepared statements**
   ```php
   // Лучше:
   $db->query("SELECT * FROM feeds WHERE url = ?", [$url]);
   
   // Чем:
   $db->query("SELECT * FROM feeds WHERE url = " . $db->escape($url));
   ```

4. **Не использовать для имен таблиц/колонок**
   ```php
   // ❌ Неправильно:
   $table = $db->escape($_GET['table']);
   $db->query("SELECT * FROM {$table}");
   
   // ✅ Правильно: whitelist
   $allowedTables = ['feeds', 'items', 'users'];
   $table = in_array($_GET['table'], $allowedTables) ? $_GET['table'] : 'feeds';
   ```

### Когда использовать

✅ **Используйте** для:
- Динамически формируемых SQL запросов
- Значений, которые нельзя передать через параметры
- Миграции legacy кода

❌ **Не используйте** для:
- Имен таблиц и колонок (используйте whitelist)
- Обычных SELECT/INSERT/UPDATE (используйте prepared statements)
- NULL значений (используйте 'NULL' строку)

---

## Rss::parseXml()

### Назначение
Парсит RSS/Atom ленту из готового XML контента.

### Сигнатура
```php
public function parseXml(string $xmlContent): array
```

### Параметры
- `$xmlContent` (string) - XML контент RSS/Atom ленты

### Возвращает
- (array) - структурированные данные ленты

### Формат возвращаемых данных

```php
[
    'type' => 'rss',           // Тип: 'rss', 'atom', 'rdf', 'unknown'
    'title' => 'Заголовок',
    'description' => 'Описание',
    'link' => 'https://...',
    'language' => 'ru',
    'image' => 'https://...',  // URL изображения
    'copyright' => '© 2025',
    'generator' => 'WordPress',
    'items' => [
        [
            'id' => 'guid-123',
            'link' => 'https://...',
            'title' => 'Заголовок новости',
            'description' => 'Краткое описание',
            'content' => 'Полный текст',
            'author' => 'Иван Иванов',
            'categories' => ['Технологии', 'AI'],
            'enclosures' => [
                [
                    'url' => 'https://...',
                    'type' => 'image/jpeg',
                    'length' => 12345,
                ],
            ],
            'published_at' => DateTimeImmutable,
        ],
        // ... другие элементы
    ],
]
```

### Использование

#### Базовый пример

```php
$rss = new Rss([
    'timeout' => 30,
    'max_content_size' => 10485760, // 10 MB
    'enable_cache' => true,
    'cache_directory' => '/path/to/cache',
], $logger);

// XML уже загружен через HTTP
$xmlContent = $http->get('https://example.com/feed.xml')->getBody();

// Парсим
$feedData = $rss->parseXml($xmlContent);

echo "Заголовок: " . $feedData['title'] . "\n";
echo "Элементов: " . count($feedData['items']) . "\n";

foreach ($feedData['items'] as $item) {
    echo "- " . $item['title'] . "\n";
}
```

#### С Conditional GET

```php
$rss = new Rss($config, $logger);

// Загружаем с ETag
$response = $http->get($url, [
    'headers' => [
        'If-None-Match' => $lastEtag,
    ],
]);

if ($response->getStatusCode() === 304) {
    echo "Не изменилось\n";
} else {
    $xmlContent = (string)$response->getBody();
    $feedData = $rss->parseXml($xmlContent);
    
    // Сохраняем новый ETag
    $newEtag = $response->getHeader('ETag')[0] ?? null;
}
```

#### С ограничением элементов

```php
$feedData = $rss->parseXml($xmlContent);

// Ограничиваем до 10 элементов
$items = array_slice($feedData['items'], 0, 10);

foreach ($items as $item) {
    // Обработка
}
```

### Конфигурация

```php
$rssConfig = [
    // HTTP таймаут (секунды)
    'timeout' => 30,
    
    // Максимальный размер контента (байты)
    'max_content_size' => 10485760, // 10 MB
    
    // Кеширование
    'enable_cache' => true,
    'cache_directory' => '/path/to/cache',
    'cache_duration' => 3600, // секунды
    
    // Санитизация HTML
    'enable_sanitization' => true,
    
    // User-Agent
    'user_agent' => 'MyRSSReader/1.0',
];
```

### Исключения

#### RssException
Выбрасывается при ошибках парсинга:

```php
try {
    $feedData = $rss->parseXml($xmlContent);
} catch (RssException $e) {
    echo "Ошибка парсинга: " . $e->getMessage();
}
```

Типичные ошибки:
- Невалидный XML
- Неподдерживаемый формат
- Превышен размер контента
- Ошибка SimplePie

### Преимущества перед SimplePie напрямую

| Аспект | SimplePie напрямую | Rss::parseXml() |
|--------|-------------------|-----------------|
| Настройка | Вручную каждый раз | Один раз в конфиге |
| Валидация | Нет | Автоматически |
| Логирование | Нет | Структурированное |
| Ошибки | Exception или false | RssException с контекстом |
| Кеш | Настраивать вручную | Автоматически |
| Формат | SimplePie\Item | Массив с типами |
| Размер | Не проверяется | Проверяется |

### Различия с Rss::fetch()

| Метод | Назначение | HTTP | Валидация URL |
|-------|-----------|------|---------------|
| `fetch($url)` | Загрузить и парсить | ✅ Да | ✅ Да |
| `parseXml($xml)` | Парсить готовый XML | ❌ Нет | ❌ Нет |

```php
// fetch() - всё в одном
$feedData = $rss->fetch('https://example.com/feed.xml');

// parseXml() - только парсинг
$xmlContent = $http->get($url)->getBody();
$feedData = $rss->parseXml($xmlContent);
```

### Когда использовать

✅ **Используйте parseXml()** для:
- Conditional GET (ETag, Last-Modified)
- Кастомных HTTP заголовков
- Прокси или особых настроек HTTP
- Предварительной валидации XML
- Загрузки через другие методы (cURL, file_get_contents)

✅ **Используйте fetch()** для:
- Простой загрузки по URL
- Когда не нужен контроль над HTTP
- Быстрого прототипирования

---

## Примеры интеграции

### Пример 1: Rss2Tlg FetchRunner

```php
class FetchRunner
{
    public function __construct(
        private readonly MySQL $db,
        private readonly string $cacheDir,
        private readonly ?Logger $logger = null
    ) {}
    
    private function parseFeed(string $xmlContent, FeedConfig $config): array
    {
        // Конфигурация Rss
        $rssConfig = [
            'timeout' => $config->timeout,
            'max_content_size' => strlen($xmlContent) + 1024,
            'enable_cache' => $config->parserOptions['enable_cache'] ?? true,
            'cache_directory' => $this->cacheDir,
        ];

        // Используем Rss::parseXml()
        $rss = new Rss($rssConfig, $this->logger);
        $feedData = $rss->parseXml($xmlContent);
        
        return $this->convertToRawItems($feedData['items']);
    }
}
```

### Пример 2: FeedStateRepository

```php
class FeedStateRepository
{
    public function __construct(
        private readonly MySQL $db,
        private readonly ?Logger $logger = null
    ) {}
    
    public function save(int $feedId, string $url, FeedState $state): bool
    {
        // Используем MySQL::escape()
        $urlEscaped = $this->db->escape($url);
        $etagEscaped = $state->etag !== null 
            ? $this->db->escape($state->etag) 
            : 'NULL';
        
        $sql = sprintf(
            "INSERT INTO feeds (feed_id, url, etag) VALUES (%d, %s, %s)",
            $feedId,
            $urlEscaped,
            $etagEscaped
        );
        
        $this->db->execute($sql);
        return true;
    }
}
```

---

## Миграция существующего кода

### SimplePie → Rss::parseXml()

```php
// До:
$feed = new SimplePie();
$feed->set_cache_location($cacheDir);
$feed->set_raw_data($xmlContent);
$feed->init();
$items = $feed->get_items();

foreach ($items as $item) {
    $title = $item->get_title();
    $link = $item->get_permalink();
}

// После:
$rss = new Rss(['cache_directory' => $cacheDir], $logger);
$feedData = $rss->parseXml($xmlContent);

foreach ($feedData['items'] as $item) {
    $title = $item['title'];
    $link = $item['link'];
}
```

### PDO::quote() → MySQL::escape()

```php
// До:
$pdo = $db->getConnection();
$value = $pdo->quote($string);

// После:
$value = $db->escape($string);
```

---

## Совместимость

| Класс | Версия PHP | MySQL | Зависимости |
|-------|-----------|-------|-------------|
| MySQL::escape() | 8.1+ | 5.5+ | PDO |
| Rss::parseXml() | 8.1+ | - | SimplePie 1.7+ |

---

## Тестирование

### MySQL::escape()

```php
$db = new MySQL($config);

// Тест 1: обычная строка
$result = $db->escape("test");
assert($result === "'test'");

// Тест 2: специальные символы
$result = $db->escape("test's \"quote\"");
assert(strpos($result, "test\\'s") !== false);

// Тест 3: NULL
$value = null;
$result = $value !== null ? $db->escape($value) : 'NULL';
assert($result === 'NULL');
```

### Rss::parseXml()

```php
$rss = new Rss(['enable_cache' => false]);

// Тест 1: валидный RSS
$xml = '<?xml version="1.0"?>
<rss version="2.0">
    <channel>
        <title>Test</title>
        <item>
            <title>Item 1</title>
            <link>https://example.com</link>
        </item>
    </channel>
</rss>';

$result = $rss->parseXml($xml);
assert($result['type'] === 'rss');
assert(count($result['items']) === 1);

// Тест 2: невалидный XML
try {
    $rss->parseXml('invalid xml');
    assert(false, "Should throw RssException");
} catch (RssException $e) {
    assert(true);
}
```

---

## FAQ

### Q: Почему escape() возвращает строку с кавычками?
**A:** Потому что использует PDO::quote(), который добавляет кавычки автоматически. Это безопаснее, так как нельзя забыть добавить кавычки в SQL.

### Q: Можно ли использовать parseXml() для загрузки по URL?
**A:** Нет, используйте fetch() для этого. parseXml() только парсит готовый XML.

### Q: Нужно ли проверять размер XML перед parseXml()?
**A:** Нет, метод автоматически валидирует размер согласно max_content_size из конфига.

### Q: Что делать если нужен доступ к SimplePie\Item?
**A:** Используйте RawItem::fromRssArray() для конвертации массива в объект, или работайте напрямую с массивом.

### Q: Кешируется ли результат parseXml()?
**A:** SimplePie кеширует внутри, но сам parseXml() каждый раз парсит заново. Для кеширования результата используйте внешний кеш.

---

**Документация обновлена: 02 ноября 2025**

# RSS - Документация

## Описание

`Rss` - класс для безопасной загрузки и парсинга RSS/Atom лент с защитой от XML-атак. Предоставляет единый интерфейс для работы с различными форматами новостных лент.

## Возможности

- ✅ Поддержка RSS 2.0 и Atom 1.0 форматов
- ✅ Автоматическое определение типа ленты
- ✅ Защита от XXE (XML External Entity) атак
- ✅ Валидация URL перед загрузкой
- ✅ Ограничение размера загружаемого контента
- ✅ Нормализация данных в единый формат
- ✅ Парсинг дат в объекты DateTimeImmutable
- ✅ Извлечение категорий/тегов
- ✅ Поддержка редиректов
- ✅ Настраиваемые таймауты и User-Agent
- ✅ Интеграция с Logger для отладки
- ✅ Обработка ошибок на каждом уровне

## Требования

- PHP 8.1+
- Расширения: `libxml`, `curl`, `json`
- Guzzle HTTP клиент (устанавливается через Composer)

## Установка

```bash
composer install
```

## Конфигурация

Создайте файл `config/rss.json`:

```json
{
    "user_agent": "MyRSSReader/1.0 (+https://example.com)",
    "timeout": 15,
    "max_content_size": 10485760
}
```

### Параметры конфигурации

| Параметр | Тип | Обязательный | По умолчанию | Описание |
|----------|-----|--------------|--------------|----------|
| `user_agent` | string | Нет | "RSSClient/1.0" | User-Agent для HTTP запросов |
| `timeout` | int | Нет | 10 | Таймаут соединения в секундах |
| `max_content_size` | int | Нет | 10485760 | Максимальный размер контента (10 МБ) |

## Использование

### Базовое использование

```php
use App\Component\Rss;
use App\Component\Logger;
use App\Config\ConfigLoader;

// С конфигурацией и логгером
$config = ConfigLoader::load(__DIR__ . '/config/rss.json');
$loggerConfig = ConfigLoader::load(__DIR__ . '/config/logger.json');
$logger = new Logger($loggerConfig);
$rss = new Rss($config, $logger);

// Загрузка и парсинг ленты
$feed = $rss->fetch('https://example.com/feed.xml');

// Информация о ленте
echo "Заголовок: {$feed['title']}\n";
echo "Описание: {$feed['description']}\n";
echo "Ссылка: {$feed['link']}\n";
echo "Язык: {$feed['language']}\n";
echo "Тип: {$feed['type']}\n"; // 'rss' или 'atom'

// Элементы ленты
foreach ($feed['items'] as $item) {
    echo "Заголовок: {$item['title']}\n";
    echo "Ссылка: {$item['link']}\n";
    echo "Описание: {$item['description']}\n";
    
    if ($item['published_at'] !== null) {
        echo "Дата: {$item['published_at']->format('d.m.Y H:i')}\n";
    }
    
    if ($item['author'] !== '') {
        echo "Автор: {$item['author']}\n";
    }
    
    if (!empty($item['categories'])) {
        echo "Категории: " . implode(', ', $item['categories']) . "\n";
    }
    
    echo "\n";
}
```

### Минимальная конфигурация

```php
// Без конфигурации (используются значения по умолчанию)
$rss = new Rss();

$feed = $rss->fetch('https://news.ycombinator.com/rss');
```

### Пользовательские настройки

```php
$rss = new Rss([
    'user_agent' => 'MyCustomBot/2.0',
    'timeout' => 30,
    'max_content_size' => 20 * 1024 * 1024, // 20 МБ
]);

$feed = $rss->fetch('https://example.com/feed.xml');
```

## Структура данных

### Структура Feed

```php
[
    'type' => 'rss',              // Тип ленты: 'rss' или 'atom'
    'title' => 'Название',        // Заголовок ленты
    'description' => 'Описание',  // Описание ленты
    'link' => 'https://...',      // Ссылка на источник
    'language' => 'ru',           // Язык контента
    'items' => [...]              // Массив элементов
]
```

### Структура Item

```php
[
    'title' => 'Заголовок',                      // Заголовок элемента
    'link' => 'https://...',                     // Ссылка на элемент
    'description' => 'Описание',                 // Описание/контент
    'published_at' => DateTimeImmutable|null,    // Дата публикации
    'author' => 'Автор',                         // Автор элемента
    'categories' => ['cat1', 'cat2']             // Массив категорий/тегов
]
```

## Примеры использования

### Загрузка нескольких лент

```php
$feeds = [
    'https://news.ycombinator.com/rss',
    'https://www.reddit.com/.rss',
    'https://habr.com/ru/rss/hub/php/all/',
];

$rss = new Rss(['timeout' => 10]);

foreach ($feeds as $feedUrl) {
    try {
        $feed = $rss->fetch($feedUrl);
        
        echo "=== {$feed['title']} ===\n";
        echo "Элементов: " . count($feed['items']) . "\n\n";
        
    } catch (Exception $e) {
        echo "Ошибка загрузки {$feedUrl}: {$e->getMessage()}\n\n";
    }
}
```

### Фильтрация элементов по дате

```php
$feed = $rss->fetch('https://example.com/feed.xml');

$yesterday = new DateTimeImmutable('-1 day');

$recentItems = array_filter($feed['items'], function ($item) use ($yesterday) {
    return $item['published_at'] !== null && $item['published_at'] >= $yesterday;
});

echo "Новых элементов за последние 24 часа: " . count($recentItems) . "\n";

foreach ($recentItems as $item) {
    echo "- {$item['title']}\n";
}
```

### Поиск по категориям

```php
$feed = $rss->fetch('https://example.com/feed.xml');

$category = 'технологии';

$filtered = array_filter($feed['items'], function ($item) use ($category) {
    foreach ($item['categories'] as $cat) {
        if (stripos($cat, $category) !== false) {
            return true;
        }
    }
    return false;
});

echo "Найдено элементов в категории '{$category}': " . count($filtered) . "\n";
```

### Сохранение в базу данных

```php
use App\Component\MySQL;

$mysql = new MySQL($mysqlConfig);
$rss = new Rss();

$feed = $rss->fetch('https://example.com/feed.xml');

foreach ($feed['items'] as $item) {
    // Проверяем, не существует ли уже
    $exists = $mysql->queryScalar(
        'SELECT COUNT(*) FROM feed_items WHERE link = ?',
        [$item['link']]
    );
    
    if ($exists > 0) {
        continue;
    }
    
    // Вставляем новый элемент
    $mysql->insert('
        INSERT INTO feed_items (
            title, link, description, author,
            published_at, categories, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, NOW())
    ', [
        $item['title'],
        $item['link'],
        $item['description'],
        $item['author'],
        $item['published_at'] ? $item['published_at']->format('Y-m-d H:i:s') : null,
        json_encode($item['categories']),
    ]);
}
```

### Агрегатор новостей

```php
class NewsAggregator
{
    private Rss $rss;
    private MySQL $mysql;
    private Logger $logger;
    
    public function __construct(Rss $rss, MySQL $mysql, Logger $logger)
    {
        $this->rss = $rss;
        $this->mysql = $mysql;
        $this->logger = $logger;
    }
    
    public function updateFeeds(): void
    {
        // Получить список подписанных лент
        $feeds = $this->mysql->query('SELECT * FROM rss_feeds WHERE active = 1');
        
        foreach ($feeds as $feedData) {
            try {
                $feed = $this->rss->fetch($feedData['url']);
                
                $newItems = 0;
                foreach ($feed['items'] as $item) {
                    if ($this->saveItem($feedData['id'], $item)) {
                        $newItems++;
                    }
                }
                
                // Обновить статистику
                $this->mysql->update('
                    UPDATE rss_feeds
                    SET last_checked = NOW(), items_count = items_count + ?
                    WHERE id = ?
                ', [$newItems, $feedData['id']]);
                
                $this->logger->info('Лента обновлена', [
                    'feed_id' => $feedData['id'],
                    'new_items' => $newItems,
                ]);
                
            } catch (Exception $e) {
                $this->logger->error('Ошибка обновления ленты', [
                    'feed_id' => $feedData['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
    
    private function saveItem(int $feedId, array $item): bool
    {
        $exists = $this->mysql->queryScalar(
            'SELECT COUNT(*) FROM feed_items WHERE link = ?',
            [$item['link']]
        );
        
        if ($exists > 0) {
            return false;
        }
        
        $this->mysql->insert('
            INSERT INTO feed_items (
                feed_id, title, link, description,
                author, published_at, categories, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ', [
            $feedId,
            $item['title'],
            $item['link'],
            $item['description'],
            $item['author'],
            $item['published_at'] ? $item['published_at']->format('Y-m-d H:i:s') : null,
            json_encode($item['categories']),
        ]);
        
        return true;
    }
}

// Использование
$aggregator = new NewsAggregator($rss, $mysql, $logger);
$aggregator->updateFeeds();
```

### Отправка новых элементов в Telegram

```php
use App\Component\Telegram;

$rss = new Rss();
$telegram = new Telegram($telegramConfig);

$feed = $rss->fetch('https://example.com/feed.xml');

// Отправить только первые 5 новых элементов
$items = array_slice($feed['items'], 0, 5);

foreach ($items as $item) {
    $message = "📰 <b>{$item['title']}</b>\n\n";
    $message .= strip_tags($item['description']) . "\n\n";
    $message .= "🔗 <a href=\"{$item['link']}\">Читать полностью</a>";
    
    if ($item['published_at'] !== null) {
        $message .= "\n\n📅 " . $item['published_at']->format('d.m.Y H:i');
    }
    
    try {
        $telegram->sendText($chatId, $message, [
            'parse_mode' => Telegram::PARSE_MODE_HTML,
            'disable_web_page_preview' => false,
        ]);
        
        sleep(1); // Пауза между сообщениями
        
    } catch (Exception $e) {
        echo "Ошибка отправки: {$e->getMessage()}\n";
    }
}
```

### Кеширование лент

```php
use Cache\FileCache;
use Cache\FileCacheConfig;

$cache = new FileCache(new FileCacheConfig([
    'cacheDirectory' => './cache/rss',
    'defaultTtl' => 3600, // 1 час
]));

$rss = new Rss();

function getFeed(string $url, Rss $rss, FileCache $cache): array
{
    $cacheKey = 'feed_' . md5($url);
    
    // Попытка получить из кеша
    $feed = $cache->get($cacheKey);
    
    if ($feed !== null) {
        return $feed;
    }
    
    // Загрузить и закешировать
    $feed = $rss->fetch($url);
    $cache->set($cacheKey, $feed, 3600); // Кешировать на 1 час
    
    return $feed;
}

// Использование
$feed = getFeed('https://example.com/feed.xml', $rss, $cache);
```

## API Reference

### Конструктор

```php
public function __construct(array $config = [], ?Logger $logger = null)
```

Создает экземпляр RSS парсера.

**Параметры:**
- `$config` (array) - Параметры конфигурации
- `$logger` (Logger|null) - Опциональный логгер

### fetch()

```php
public function fetch(string $url): array
```

Загружает и парсит RSS/Atom ленту.

**Параметры:**
- `$url` (string) - URL ленты (HTTP/HTTPS)

**Возвращает:** Массив с данными ленты

**Исключения:**
- `RssException` - Ошибка загрузки или парсинга
- `RssValidationException` - Невалидный URL

## Обработка ошибок

### Исключения

- `RssException` - Базовое исключение RSS класса
- `RssValidationException` - Ошибка валидации URL

```php
use App\Component\Exception\RssException;
use App\Component\Exception\RssValidationException;

try {
    $feed = $rss->fetch('https://example.com/feed.xml');
} catch (RssValidationException $e) {
    echo "Невалидный URL: {$e->getMessage()}\n";
} catch (RssException $e) {
    echo "Ошибка загрузки ленты: {$e->getMessage()}\n";
} catch (Exception $e) {
    echo "Неожиданная ошибка: {$e->getMessage()}\n";
}
```

### Обработка недоступных лент

```php
function fetchFeedSafely(Rss $rss, string $url, int $retries = 3): ?array
{
    for ($attempt = 1; $attempt <= $retries; $attempt++) {
        try {
            return $rss->fetch($url);
        } catch (RssException $e) {
            if ($attempt === $retries) {
                error_log("Не удалось загрузить ленту после {$retries} попыток: {$url}");
                return null;
            }
            sleep($attempt); // Увеличивающаяся задержка
        }
    }
    
    return null;
}
```

## Лучшие практики

1. **Используйте кеширование** для часто запрашиваемых лент:
   ```php
   $cache->set('feed_' . md5($url), $feed, 3600);
   ```

2. **Устанавливайте разумные таймауты**:
   ```php
   ['timeout' => 15] // 15 секунд для медленных серверов
   ```

3. **Обрабатывайте исключения** для каждой ленты отдельно:
   ```php
   foreach ($feedUrls as $url) {
       try {
           $feed = $rss->fetch($url);
       } catch (Exception $e) {
           // Логировать и продолжить
       }
   }
   ```

4. **Проверяйте даты** перед использованием:
   ```php
   if ($item['published_at'] !== null) {
       echo $item['published_at']->format('d.m.Y');
   }
   ```

5. **Ограничивайте количество элементов**:
   ```php
   $items = array_slice($feed['items'], 0, 10);
   ```

6. **Используйте уникальные идентификаторы**:
   ```php
   $itemId = md5($item['link'] . $item['title']);
   ```

7. **Санитизируйте HTML** в описаниях:
   ```php
   $clean = strip_tags($item['description'], '<p><br><a>');
   ```

8. **Устанавливайте правильный User-Agent**:
   ```php
   ['user_agent' => 'YourApp/1.0 (+https://yoursite.com)']
   ```

## Безопасность

### Защита от XXE атак

Класс автоматически защищает от XXE атак через настройки libxml:
- `LIBXML_NOENT` - запрещает подстановку сущностей
- `LIBXML_NONET` - запрещает сетевой доступ

### Ограничение размера

```php
$rss = new Rss([
    'max_content_size' => 5 * 1024 * 1024, // 5 МБ максимум
]);
```

### Валидация URL

Только HTTP и HTTPS протоколы разрешены:

```php
// ✅ Разрешено
$rss->fetch('https://example.com/feed.xml');
$rss->fetch('http://example.com/feed.xml');

// ❌ Запрещено
$rss->fetch('file:///etc/passwd');
$rss->fetch('ftp://example.com/feed.xml');
```

## Производительность

### Оптимизация

- Используйте HTTP клиент с keepalive соединениями
- Кешируйте результаты парсинга
- Ограничивайте размер загружаемого контента
- Используйте асинхронную загрузку для множества лент

### Параллельная загрузка

```php
// Пример с использованием promise для параллельной загрузки
$urls = [
    'https://example1.com/feed.xml',
    'https://example2.com/feed.xml',
    'https://example3.com/feed.xml',
];

$feeds = [];
foreach ($urls as $url) {
    try {
        $feeds[$url] = $rss->fetch($url);
    } catch (Exception $e) {
        $feeds[$url] = null;
    }
}
```

## Поддерживаемые форматы

### RSS 2.0

```xml
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Example Feed</title>
    <link>https://example.com</link>
    <description>Feed description</description>
    <item>
      <title>Item title</title>
      <link>https://example.com/item</link>
      <description>Item description</description>
      <pubDate>Mon, 01 Jan 2024 12:00:00 GMT</pubDate>
      <category>Technology</category>
    </item>
  </channel>
</rss>
```

### Atom 1.0

```xml
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Example Feed</title>
  <link href="https://example.com"/>
  <updated>2024-01-01T12:00:00Z</updated>
  <entry>
    <title>Entry title</title>
    <link href="https://example.com/entry"/>
    <published>2024-01-01T12:00:00Z</published>
    <content>Entry content</content>
    <category term="Technology"/>
  </entry>
</feed>
```

## Примеры в коде

См. `examples/rss_example.php` для полных примеров использования.

## См. также

- [Http документация](HTTP.md) - используется для загрузки лент
- [Logger документация](LOGGER.md) - для логирования операций
- [MySQL документация](MYSQL.md) - для сохранения элементов в БД
- [Telegram документация](TELEGRAM.md) - для отправки уведомлений
- [FileCache документация](FILECACHE.md) - для кеширования лент

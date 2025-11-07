# FileCache - Документация

## Описание

`FileCache` - мощная и гибкая система файлового кеширования для PHP с поддержкой тегов, сжатия, блокировок и автоматической сборки мусора. Предоставляет PSR-16 совместимый интерфейс с расширенными возможностями.

## Возможности

- ✅ PSR-16 (Simple Cache) совместимость
- ✅ Система тегов для группировки элементов
- ✅ Сжатие данных (gzip, zstd, lz4)
- ✅ Блокировки для конкурентного доступа
- ✅ Автоматическая сборка мусора (GC)
- ✅ Ограничение размера кеша
- ✅ Предзагрузка кеша в память
- ✅ Атомарные операции increment/decrement
- ✅ Статистика использования (hits/misses)
- ✅ Пакетные операции (getMultiple, setMultiple)
- ✅ Remember pattern
- ✅ Pull pattern (get and delete)
- ✅ Touch (обновление TTL)

## Требования

- PHP 8.1+
- Расширения: `json`
- Опционально: `zlib`, `zstd`, `lz4` (для сжатия)

## Установка

```bash
composer install
```

## Конфигурация

### Базовая конфигурация

```php
use Cache\FileCache;
use Cache\FileCacheConfig;

$cache = new FileCache(new FileCacheConfig([
    'cacheDirectory' => './cache',
    'defaultTtl' => 3600,
]));
```

### Расширенная конфигурация

```php
$config = new FileCacheConfig([
    'cacheDirectory' => './cache',
    'defaultTtl' => 3600,
    'fileExtension' => '.cache',
    'directoryLevel' => 2,
    'filePermissions' => 0644,
    'directoryPermissions' => 0755,
    
    // Сжатие
    'compressionEnabled' => true,
    'compressionMethod' => 'gzip',
    'compressionLevel' => 6,
    'compressionThreshold' => 1024,
    
    // Ограничения
    'maxCacheSize' => 104857600, // 100 МБ
    'maxItemSize' => 10485760,   // 10 МБ
    
    // Производительность
    'preloadEnabled' => false,
    'maxMemoryCacheItems' => 100,
    'checkExpiredOnRead' => true,
    
    // Сборка мусора
    'gcEnabled' => true,
    'gcProbability' => 0.01,
    
    // Обработка ошибок
    'throwOnError' => false,
]);

$cache = new FileCache($config);
```

### Загрузка из JSON файла

```json
{
    "cacheDirectory": "./cache",
    "defaultTtl": 3600,
    "compressionEnabled": true,
    "compressionMethod": "gzip",
    "maxCacheSize": 104857600
}
```

```php
$cache = new FileCache(['configFile' => 'config/cache.json']);
```

### Параметры конфигурации

| Параметр | Тип | По умолчанию | Описание |
|----------|-----|--------------|----------|
| `cacheDirectory` | string | "./cache" | Директория кеша |
| `defaultTtl` | int | 3600 | TTL по умолчанию (секунды) |
| `fileExtension` | string | ".cache" | Расширение файлов |
| `directoryLevel` | int | 2 | Уровень вложенности директорий |
| `compressionEnabled` | bool | false | Включить сжатие |
| `compressionMethod` | string | "gzip" | Метод сжатия (gzip/zstd/lz4) |
| `compressionLevel` | int | 6 | Уровень сжатия |
| `compressionThreshold` | int | 1024 | Порог сжатия (байты) |
| `maxCacheSize` | int\|null | null | Макс размер кеша (байты) |
| `maxItemSize` | int\|null | null | Макс размер элемента (байты) |
| `preloadEnabled` | bool | false | Предзагрузка в память |
| `maxMemoryCacheItems` | int | 100 | Макс элементов в памяти |
| `checkExpiredOnRead` | bool | true | Проверять истечение при чтении |
| `gcEnabled` | bool | true | Включить сборку мусора |
| `gcProbability` | float | 0.01 | Вероятность запуска GC |
| `throwOnError` | bool | false | Выбрасывать исключения |

## Использование

### Базовые операции

```php
use Cache\FileCache;
use Cache\FileCacheConfig;

$cache = new FileCache(new FileCacheConfig([
    'cacheDirectory' => './cache',
]));

// Сохранение
$cache->set('user_123', ['name' => 'John', 'email' => 'john@example.com']);

// Получение
$user = $cache->get('user_123');
echo $user['name']; // John

// Проверка существования
if ($cache->has('user_123')) {
    echo "Exists";
}

// Удаление
$cache->delete('user_123');

// Очистка всего кеша
$cache->clear();
```

### TTL (Time To Live)

```php
// С указанием TTL в секундах
$cache->set('temp_data', $data, 3600); // 1 час

// С DateInterval
$cache->set('session', $sessionData, new DateInterval('PT30M')); // 30 минут

// Бесконечное время жизни
$cache->set('config', $config, null);

// Значение по умолчанию (из конфигурации)
$cache->set('data', $value);
```

### Пакетные операции

```php
// Получить несколько значений
$keys = ['user_1', 'user_2', 'user_3'];
$users = $cache->getMultiple($keys);

foreach ($users as $key => $user) {
    if ($user !== null) {
        echo "{$key}: {$user['name']}\n";
    }
}

// Сохранить несколько значений
$cache->setMultiple([
    'user_1' => ['name' => 'Alice'],
    'user_2' => ['name' => 'Bob'],
    'user_3' => ['name' => 'Charlie'],
], 3600);

// Удалить несколько значений
$cache->deleteMultiple(['user_1', 'user_2', 'user_3']);
```

### Remember Pattern

```php
// Получить из кеша или выполнить callback
$users = $cache->remember('users_list', function () {
    // Эта функция выполнится только если значение не в кеше
    return $mysql->query('SELECT * FROM users');
}, 3600);

// Пример с API запросом
$data = $cache->remember('api_response', function () use ($http) {
    $response = $http->get('https://api.example.com/data');
    return json_decode($response->getBody(), true);
}, 1800);
```

### Теги

```php
// Сохранение с тегами
$cache->tags(['users', 'admin'])->set('user_123', $userData);
$cache->tags(['users', 'customer'])->set('user_456', $userData2);

// Сохранить несколько элементов с тегами
$cache->tags(['products', 'featured'])->setMultiple([
    'product_1' => $product1,
    'product_2' => $product2,
]);

// Удалить все элементы с тегом
$cache->deleteByTag('users'); // Удалит user_123 и user_456

// Инвалидация кеша категории
$cache->deleteByTag('products');
```

### Инкремент/Декремент

```php
// Инкремент счетчика
$cache->set('page_views', 0);
$cache->increment('page_views'); // 1
$cache->increment('page_views', 5); // 6

// Декремент
$cache->decrement('page_views'); // 5
$cache->decrement('page_views', 3); // 2

// Получить значение
$views = $cache->get('page_views'); // 2

// Атомарный инкремент (с блокировкой)
for ($i = 0; $i < 100; $i++) {
    $cache->increment('counter');
}
```

### Pull Pattern

```php
// Получить и удалить одной операцией
$tempToken = $cache->pull('temp_token_123');

if ($tempToken !== null) {
    // Использовать токен (он уже удален из кеша)
    validateToken($tempToken);
}

// С значением по умолчанию
$data = $cache->pull('optional_data', ['default' => 'value']);
```

### Touch (Обновление TTL)

```php
// Продлить время жизни элемента
$cache->set('session_123', $sessionData, 1800); // 30 минут

// Позже продлить еще на 30 минут
$cache->touch('session_123', 1800);

// Продлить с DateInterval
$cache->touch('session_123', new DateInterval('PT1H'));
```

## Примеры использования

### Кеширование запросов к БД

```php
class UserRepository
{
    private MySQL $mysql;
    private FileCache $cache;
    
    public function __construct(MySQL $mysql, FileCache $cache)
    {
        $this->mysql = $mysql;
        $this->cache = $cache;
    }
    
    public function findById(int $id): ?array
    {
        $cacheKey = "user_{$id}";
        
        return $this->cache->remember($cacheKey, function () use ($id) {
            return $this->mysql->queryOne('SELECT * FROM users WHERE id = ?', [$id]);
        }, 3600);
    }
    
    public function findAll(): array
    {
        return $this->cache->remember('users_all', function () {
            return $this->mysql->query('SELECT * FROM users');
        }, 1800);
    }
    
    public function update(int $id, array $data): void
    {
        $this->mysql->update('UPDATE users SET ... WHERE id = ?', [$id]);
        
        // Инвалидация кеша
        $this->cache->delete("user_{$id}");
        $this->cache->delete('users_all');
    }
    
    public function deleteUser(int $id): void
    {
        $this->mysql->delete('DELETE FROM users WHERE id = ?', [$id]);
        
        // Инвалидация через тег
        $this->cache->deleteByTag('users');
    }
}

// Использование
$repo = new UserRepository($mysql, $cache);
$user = $repo->findById(123); // Первый раз из БД
$user = $repo->findById(123); // Второй раз из кеша
```

### Кеширование API ответов

```php
class ApiClient
{
    private Http $http;
    private FileCache $cache;
    
    public function __construct(Http $http, FileCache $cache)
    {
        $this->http = $http;
        $this->cache = $cache;
    }
    
    public function fetchWeather(string $city): array
    {
        $cacheKey = "weather_{$city}";
        
        return $this->cache->remember($cacheKey, function () use ($city) {
            $response = $this->http->get("https://api.weather.com/forecast", [
                'query' => ['city' => $city],
            ]);
            
            return json_decode($response->getBody(), true);
        }, 1800); // Кешировать на 30 минут
    }
    
    public function fetchExchangeRates(): array
    {
        return $this->cache->remember('exchange_rates', function () {
            $response = $this->http->get('https://api.exchangerate.com/rates');
            return json_decode($response->getBody(), true);
        }, 3600); // Обновлять раз в час
    }
}

$api = new ApiClient($http, $cache);
$weather = $api->fetchWeather('Moscow');
```

### Кеширование RSS лент

```php
class CachedRssReader
{
    private Rss $rss;
    private FileCache $cache;
    
    public function __construct(Rss $rss, FileCache $cache)
    {
        $this->rss = $rss;
        $this->cache = $cache;
    }
    
    public function getFeed(string $url): array
    {
        $cacheKey = 'rss_' . md5($url);
        
        return $this->cache->remember($cacheKey, function () use ($url) {
            return $this->rss->fetch($url);
        }, 3600); // Обновлять раз в час
    }
    
    public function getMultipleFeeds(array $urls): array
    {
        $feeds = [];
        
        foreach ($urls as $url) {
            try {
                $feeds[$url] = $this->getFeed($url);
            } catch (Exception $e) {
                error_log("Failed to fetch feed: {$url}");
            }
        }
        
        return $feeds;
    }
}

$reader = new CachedRssReader($rss, $cache);
$feed = $reader->getFeed('https://example.com/feed.xml');
```

### Счетчики и статистика

```php
class PageViewCounter
{
    private FileCache $cache;
    
    public function __construct(FileCache $cache)
    {
        $this->cache = $cache;
    }
    
    public function incrementPageView(string $pageId): int
    {
        $key = "page_views_{$pageId}";
        
        // Атомарный инкремент
        $views = $this->cache->increment($key);
        
        // Сохранить в БД каждые 100 просмотров
        if ($views % 100 === 0) {
            $this->saveToDatabase($pageId, $views);
        }
        
        return $views;
    }
    
    public function getPageViews(string $pageId): int
    {
        $key = "page_views_{$pageId}";
        return $this->cache->get($key, 0);
    }
    
    public function getTopPages(int $limit = 10): array
    {
        return $this->cache->remember('top_pages', function () use ($limit) {
            // Получить из БД
            return $mysql->query('
                SELECT page_id, views
                FROM page_stats
                ORDER BY views DESC
                LIMIT ?
            ', [$limit]);
        }, 300); // Обновлять каждые 5 минут
    }
    
    private function saveToDatabase(string $pageId, int $views): void
    {
        // Сохранить в БД
    }
}

$counter = new PageViewCounter($cache);
$views = $counter->incrementPageView('homepage');
echo "Просмотров: {$views}";
```

### Session хранилище

```php
class CacheSessionHandler implements SessionHandlerInterface
{
    private FileCache $cache;
    private int $ttl;
    
    public function __construct(FileCache $cache, int $ttl = 1800)
    {
        $this->cache = $cache;
        $this->ttl = $ttl;
    }
    
    public function open($savePath, $sessionName): bool
    {
        return true;
    }
    
    public function close(): bool
    {
        return true;
    }
    
    public function read($sessionId): string
    {
        $data = $this->cache->get("session_{$sessionId}");
        return $data === null ? '' : $data;
    }
    
    public function write($sessionId, $data): bool
    {
        // Продлить TTL при каждой записи
        return $this->cache->set("session_{$sessionId}", $data, $this->ttl);
    }
    
    public function destroy($sessionId): bool
    {
        return $this->cache->delete("session_{$sessionId}");
    }
    
    public function gc($maxlifetime): int
    {
        // FileCache автоматически удаляет истекшие элементы
        return $this->cache->gc(true);
    }
}

// Использование
$handler = new CacheSessionHandler($cache, 3600);
session_set_save_handler($handler, true);
session_start();
```

### Кеширование с тегами

```php
class ProductCatalog
{
    private MySQL $mysql;
    private FileCache $cache;
    
    public function __construct(MySQL $mysql, FileCache $cache)
    {
        $this->mysql = $mysql;
        $this->cache = $cache;
    }
    
    public function getProduct(int $id): array
    {
        $cacheKey = "product_{$id}";
        
        return $this->cache
            ->tags(['products', "product_{$id}"])
            ->remember($cacheKey, function () use ($id) {
                return $this->mysql->queryOne('SELECT * FROM products WHERE id = ?', [$id]);
            }, 3600);
    }
    
    public function getProductsByCategory(int $categoryId): array
    {
        $cacheKey = "products_category_{$categoryId}";
        
        return $this->cache
            ->tags(['products', "category_{$categoryId}"])
            ->remember($cacheKey, function () use ($categoryId) {
                return $this->mysql->query('SELECT * FROM products WHERE category_id = ?', [$categoryId]);
            }, 1800);
    }
    
    public function updateProduct(int $id, array $data): void
    {
        $this->mysql->update('UPDATE products SET ... WHERE id = ?', [$id]);
        
        // Инвалидировать кеш продукта
        $this->cache->deleteByTag("product_{$id}");
        
        // Также инвалидировать кеш категории
        $product = $this->mysql->queryOne('SELECT category_id FROM products WHERE id = ?', [$id]);
        $this->cache->deleteByTag("category_{$product['category_id']}");
    }
    
    public function deleteProduct(int $id): void
    {
        $this->mysql->delete('DELETE FROM products WHERE id = ?', [$id]);
        
        // Инвалидировать весь кеш продуктов
        $this->cache->deleteByTag('products');
    }
}

$catalog = new ProductCatalog($mysql, $cache);
$product = $catalog->getProduct(123);
```

## API Reference

### Основные методы

#### get()
```php
public function get(string $key, mixed $default = null): mixed
```
Получает значение из кеша.

#### set()
```php
public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
```
Сохраняет значение в кеш.

#### has()
```php
public function has(string $key): bool
```
Проверяет существование ключа.

#### delete()
```php
public function delete(string $key): bool
```
Удаляет значение из кеша.

#### clear()
```php
public function clear(): bool
```
Очищает весь кеш.

### Пакетные операции

#### getMultiple()
```php
public function getMultiple(iterable $keys, mixed $default = null): iterable
```

#### setMultiple()
```php
public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
```

#### deleteMultiple()
```php
public function deleteMultiple(iterable $keys): bool
```

### Расширенные методы

#### remember()
```php
public function remember(string $key, callable $callback, null|int|DateInterval $ttl = null): mixed
```

#### increment()
```php
public function increment(string $key, int $value = 1): int|false
```

#### decrement()
```php
public function decrement(string $key, int $value = 1): int|false
```

#### touch()
```php
public function touch(string $key, null|int|DateInterval $ttl = null): bool
```

#### pull()
```php
public function pull(string $key, mixed $default = null): mixed
```

#### tags()
```php
public function tags(array $tags): self
```

#### deleteByTag()
```php
public function deleteByTag(string $tag): bool
```

#### gc()
```php
public function gc(bool $force = false): int
```

#### getStats()
```php
public function getStats(): array
```

Возвращает статистику: hits, misses, writes, deletes.

## Лучшие практики

1. **Используйте осмысленные ключи**:
   ```php
   $cache->set('user_' . $userId, $user); // ✅
   $cache->set('u' . $id, $user);         // ❌
   ```

2. **Устанавливайте адекватный TTL**:
   ```php
   $cache->set('session', $data, 1800);     // 30 минут
   $cache->set('config', $config, 86400);   // 24 часа
   ```

3. **Используйте теги для группировки**:
   ```php
   $cache->tags(['users'])->set('user_123', $user);
   ```

4. **Remember pattern для сложных операций**:
   ```php
   $data = $cache->remember('key', fn() => $mysql->query(...), 3600);
   ```

5. **Включайте сжатие для больших данных**:
   ```php
   'compressionEnabled' => true,
   'compressionThreshold' => 1024,
   ```

6. **Настройте сборку мусора**:
   ```php
   'gcEnabled' => true,
   'gcProbability' => 0.01,
   ```

7. **Ограничьте размер кеша**:
   ```php
   'maxCacheSize' => 100 * 1024 * 1024, // 100 МБ
   ```

8. **Используйте предзагрузку** для hot данных:
   ```php
   'preloadEnabled' => true,
   ```

## Производительность

- Используйте `directoryLevel` для распределения файлов
- Включайте сжатие для больших объектов
- Настройте `maxMemoryCacheItems` для горячего кеша
- Используйте теги вместо массового удаления
- Периодически запускайте GC
- Избегайте слишком коротких TTL

## Безопасность

- Устанавливайте правильные права на файлы
- Не кешируйте чувствительные данные без шифрования
- Валидируйте ключи кеша
- Ограничивайте размер кеша
- Регулярно очищайте старый кеш

## См. также

- [MySQL документация](MYSQL.md) - кеширование запросов к БД
- [RSS документация](RSS.md) - кеширование RSS лент
- [Http документация](HTTP.md) - кеширование HTTP ответов
- [OpenRouter документация](OPENROUTER.md) - кеширование AI ответов

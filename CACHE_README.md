# Библиотека файлового кеширования

Полнофункциональная библиотека файлового кеширования для PHP с поддержкой расширенной конфигурации, тегирования, блокировок, сжатия и многого другого.

## Оглавление

- [Установка](#установка)
- [Быстрый старт](#быстрый-старт)
- [Конфигурация](#конфигурация)
- [Основные методы](#основные-методы)
- [Расширенные возможности](#расширенные-возможности)
- [Примеры использования](#примеры-использования)

## Установка

Библиотека уже интегрирована в проект. Для использования достаточно подключить autoload:

```php
require_once __DIR__ . '/autoload.php';

use Cache\FileCache;
use Cache\FileCacheConfig;
```

## Быстрый старт

```php
// Создание кеша с минимальной конфигурацией
$cache = new FileCache([
    'cacheDirectory' => '/path/to/cache',
]);

// Сохранение данных
$cache->set('user:1', ['name' => 'John Doe', 'email' => 'john@example.com']);

// Получение данных
$user = $cache->get('user:1');

// Проверка существования
if ($cache->has('user:1')) {
    echo "Cache exists!";
}

// Удаление
$cache->delete('user:1');

// Полная очистка кеша
$cache->clear();
```

## Конфигурация

### Базовые параметры хранилища

```php
$config = new FileCacheConfig([
    // Путь к директории кеша (обязательный)
    'cacheDirectory' => '/var/cache/app',
    
    // Права доступа для создаваемых директорий
    'directoryPermissions' => 0755,
    
    // Права доступа для файлов кеша
    'filePermissions' => 0644,
    
    // Расширение файлов кеша
    'fileExtension' => '.cache',
    
    // Включение шардирования (распределение по поддиректориям)
    'useSharding' => true,
    
    // Глубина вложенности при шардировании (1-2 рекомендуется)
    'shardingDepth' => 2,
]);
```

**Шардирование** полезно при большом количестве файлов (>10000), так как улучшает производительность файловой системы.

### Параметры времени жизни и истечения

```php
$config = new FileCacheConfig([
    // Время жизни по умолчанию в секундах (null = бессрочно)
    'defaultTtl' => 3600,
    
    // Максимально допустимое время жизни
    'maxTtl' => 86400,
    
    // Вероятность запуска сборки мусора (0-100)
    'gcProbability' => 1,
    
    // Делитель вероятности (вероятность = gcProbability/gcDivisor)
    'gcDivisor' => 100,
    
    // Проверять истечение при чтении
    'checkExpiredOnRead' => true,
]);
```

### Параметры сериализации

```php
$config = new FileCacheConfig([
    // Способ сериализации: 'native', 'json', 'igbinary', 'msgpack'
    'serializer' => 'json',
    
    // Флаги для json_encode/json_decode
    'jsonOptions' => JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
    
    // Включение сжатия данных (gzip)
    'compressionEnabled' => true,
    
    // Уровень сжатия (1-9, где 9 - максимальное сжатие)
    'compressionLevel' => 6,
    
    // Минимальный размер в байтах для применения сжатия
    'compressionThreshold' => 1024,
]);
```

### Параметры блокировки и конкуренции

```php
$config = new FileCacheConfig([
    // Использование file locking для предотвращения race conditions
    'fileLocking' => true,
    
    // Максимальное время ожидания блокировки в секундах
    'lockTimeout' => 5,
    
    // Количество повторных попыток получения блокировки
    'lockRetries' => 3,
    
    // Атомарная запись через временные файлы с rename()
    'atomicWrites' => true,
]);
```

### Параметры префиксов и изоляции

```php
$config = new FileCacheConfig([
    // Глобальный префикс для всех ключей
    'keyPrefix' => 'app_',
    
    // Пространство имен для логической изоляции
    'namespace' => 'production',
    
    // Алгоритм хеширования ключей: 'sha1', 'md5', 'xxh3'
    'keyHashAlgorithm' => 'sha1',
]);
```

### Параметры производительности

```php
$config = new FileCacheConfig([
    // Вызов fsync() для гарантированной записи на диск (медленнее)
    'fsyncOnWrite' => false,
    
    // Отключение stat cache PHP для актуальности данных
    'statCacheDisabled' => true,
    
    // Предзагрузка часто используемых ключей при инициализации
    'preloadEnabled' => false,
    
    // Максимальный размер кеша в байтах
    'maxCacheSize' => 1024 * 1024 * 100, // 100 MB
    
    // Максимальный размер одного элемента кеша
    'maxItemSize' => 1024 * 1024, // 1 MB
]);
```

### Параметры отладки и мониторинга

```php
$config = new FileCacheConfig([
    // Сбор статистики попаданий/промахов
    'enableStatistics' => true,
    
    // Стратегия обработки ошибок: 'throw', 'log', 'silent'
    'errorHandling' => 'throw',
]);
```

## Основные методы

### get(string $key, mixed $default = null): mixed

Получение значения из кеша.

```php
$value = $cache->get('user:1');
$value = $cache->get('nonexistent', 'default value');
```

### set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool

Сохранение значения в кеш.

```php
// Без TTL (используется defaultTtl)
$cache->set('user:1', $userData);

// С TTL в секундах
$cache->set('session:abc', $sessionData, 3600);

// С DateInterval
$cache->set('temp', $data, new DateInterval('PT1H'));
```

### delete(string $key): bool

Удаление элемента из кеша.

```php
$cache->delete('user:1');
```

### clear(): bool

Полная очистка кеша.

```php
$cache->clear();
```

### has(string $key): bool

Проверка существования ключа в кеше.

```php
if ($cache->has('user:1')) {
    // Ключ существует
}
```

### getMultiple(iterable $keys, mixed $default = null): iterable

Пакетное чтение.

```php
$values = $cache->getMultiple(['key1', 'key2', 'key3']);
// Результат: ['key1' => value1, 'key2' => value2, 'key3' => value3]
```

### setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool

Пакетная запись.

```php
$cache->setMultiple([
    'key1' => 'value1',
    'key2' => 'value2',
    'key3' => 'value3',
], 3600);
```

### deleteMultiple(iterable $keys): bool

Пакетное удаление.

```php
$cache->deleteMultiple(['key1', 'key2', 'key3']);
```

## Расширенные возможности

### remember(string $key, callable $callback, null|int $ttl = null): mixed

Получить значение или вычислить и закешировать.

```php
$expensiveData = $cache->remember('stats:daily', function() {
    // Дорогостоящая операция
    return calculateDailyStats();
}, 3600);
```

### increment(string $key, int $value = 1): int|false

Атомарное инкрементирование числового значения.

```php
$cache->set('counter', 10);
$newValue = $cache->increment('counter', 5); // 15
```

### decrement(string $key, int $value = 1): int|false

Атомарное декрементирование числового значения.

```php
$newValue = $cache->decrement('counter', 3); // 12
```

### touch(string $key, null|int $ttl = null): bool

Обновление времени жизни без изменения значения.

```php
$cache->touch('important_data', 7200);
```

### pull(string $key, mixed $default = null): mixed

Получить значение и удалить из кеша.

```php
$token = $cache->pull('one_time_token');
// Токен получен и удален из кеша
```

### Работа с тегами

```php
// Сохранение с тегами
$cache->tags(['users', 'premium'])->set('user:1', $userData);
$cache->tags(['users', 'free'])->set('user:2', $userData);

// Удаление всех элементов с тегом
$cache->deleteByTag('premium');
```

### Сборка мусора и обслуживание

```php
// Запуск сборки мусора (удаление истекших элементов)
$deletedCount = $cache->gc(true);

// Синоним gc()
$deletedCount = $cache->prune();

// Оптимизация структуры директорий
$cache->vacuum();

// Получение размера кеша в байтах
$size = $cache->getSize();

// Получение количества элементов
$count = $cache->getItemCount();
```

### Статистика и мониторинг

```php
// Получение статистики
$stats = $cache->getStats();
/*
Array (
    'hits' => 150,
    'misses' => 50,
    'writes' => 100,
    'deletes' => 25,
    'hit_rate' => 75.0,
    'size' => 1048576,
    'item_count' => 100
)
*/

// Сброс статистики
$cache->resetStats();

// Получение метаданных элемента
$metadata = $cache->getMetadata('user:1');
/*
Array (
    'created' => 1634567890,
    'expires' => 1634571490,
    'size' => 512,
    'hits' => 10,
    'tags' => ['users', 'premium']
)
*/
```

### Валидация и диагностика

```php
// Проверка истечения срока действия
if ($cache->isExpired('user:1')) {
    // Кеш истек
}

// Проверка корректности ключа (PSR-16)
$cache->validateKey('my-key'); // true или исключение

// Предзагрузка ключей в память
$loadedCount = $cache->warmup(['key1', 'key2', 'key3']);

// Проверка работоспособности
$health = $cache->healthCheck();
/*
Array (
    'directory_exists' => true,
    'directory_writable' => true,
    'directory_readable' => true,
    'free_space' => 5368709120,
    'serializer_available' => true,
    'compression_available' => true
)
*/
```

### Вспомогательные методы

```php
// Получение пути к файлу кеша
$path = $cache->getPath('user:1');

// Публичная сериализация (для тестирования)
$serialized = $cache->serialize(['data' => 'value']);

// Публичная десериализация
$data = $cache->unserialize($serialized);

// Явная блокировка ключа
$cache->lock('critical_operation', 10);
// ... критическая операция ...
$cache->unlock('critical_operation');
```

## Примеры использования

### Пример 1: Кеширование результатов запросов к БД

```php
$cache = new FileCache([
    'cacheDirectory' => '/var/cache/db',
    'defaultTtl' => 300,
    'enableStatistics' => true,
]);

function getUser($userId) {
    global $cache, $db;
    
    return $cache->remember("user:{$userId}", function() use ($db, $userId) {
        return $db->query("SELECT * FROM users WHERE id = ?", [$userId]);
    }, 600);
}

$user = getUser(123);
```

### Пример 2: Кеширование API ответов с тегами

```php
$cache = new FileCache([
    'cacheDirectory' => '/var/cache/api',
    'compressionEnabled' => true,
    'compressionThreshold' => 1024,
]);

// Сохранение с тегами
$response = fetchApiData('/users');
$cache->tags(['api', 'users'])->set('api:users', $response, 3600);

$response = fetchApiData('/products');
$cache->tags(['api', 'products'])->set('api:products', $response, 3600);

// Инвалидация всех API запросов
$cache->deleteByTag('api');
```

### Пример 3: Счетчик с атомарными операциями

```php
$cache = new FileCache([
    'cacheDirectory' => '/var/cache/counters',
    'fileLocking' => true,
]);

// Инициализация
$cache->set('page_views', 0);

// Инкремент при каждом просмотре (безопасно в многопоточной среде)
$cache->increment('page_views');

// Получение текущего значения
$views = $cache->get('page_views');
```

### Пример 4: Сессии с автоматическим истечением

```php
$cache = new FileCache([
    'cacheDirectory' => '/var/cache/sessions',
    'defaultTtl' => 1800, // 30 минут
    'namespace' => 'sessions',
]);

// Создание сессии
$sessionId = bin2hex(random_bytes(16));
$cache->set($sessionId, [
    'user_id' => 123,
    'login_time' => time(),
]);

// Продление сессии при активности
$cache->touch($sessionId, 1800);

// Получение и удаление (logout)
$sessionData = $cache->pull($sessionId);
```

### Пример 5: Мониторинг и обслуживание

```php
$cache = new FileCache([
    'cacheDirectory' => '/var/cache/app',
    'enableStatistics' => true,
    'maxCacheSize' => 1024 * 1024 * 100, // 100 MB
]);

// Периодическая проверка
$stats = $cache->getStats();
if ($stats['hit_rate'] < 50) {
    error_log('Low cache hit rate: ' . $stats['hit_rate'] . '%');
}

// Периодическая очистка (например, через cron)
$deleted = $cache->prune();
error_log("Garbage collection: {$deleted} items deleted");

// Оптимизация структуры
$cache->vacuum();

// Проверка здоровья
$health = $cache->healthCheck();
if (!$health['directory_writable']) {
    error_log('Cache directory is not writable!');
}
```

### Пример 6: Разные серверы с namespace

```php
// Production
$prodCache = new FileCache([
    'cacheDirectory' => '/var/cache/app',
    'namespace' => 'production',
]);

// Staging
$stagingCache = new FileCache([
    'cacheDirectory' => '/var/cache/app',
    'namespace' => 'staging',
]);

// Данные изолированы друг от друга
$prodCache->set('config', ['env' => 'prod']);
$stagingCache->set('config', ['env' => 'staging']);
```

## Обработка ошибок

```php
// Выброс исключений (по умолчанию)
$cache = new FileCache([
    'cacheDirectory' => '/var/cache/app',
    'errorHandling' => 'throw',
]);

try {
    $cache->set('key', $value);
} catch (\Exception $e) {
    error_log($e->getMessage());
}

// Логирование ошибок
$cache = new FileCache([
    'errorHandling' => 'log',
]);

// Тихий режим
$cache = new FileCache([
    'errorHandling' => 'silent',
]);
```

## Производительность

### Рекомендации

1. **Используйте шардирование** при >10000 файлов
2. **Включите сжатие** для больших объектов (>1KB)
3. **Выберите быстрый сериализатор**: igbinary > msgpack > native > json
4. **Отключите fsyncOnWrite** для лучшей производительности
5. **Настройте GC** согласно паттернам использования
6. **Используйте namespace** для изоляции разных типов кеша

### Бенчмарк серверов

```php
// JSON (медленно, но универсально)
'serializer' => 'json'

// Native (средняя скорость, PHP-специфично)
'serializer' => 'native'

// Igbinary (быстро, требует расширение)
'serializer' => 'igbinary'

// Msgpack (очень быстро, требует расширение)
'serializer' => 'msgpack'
```

## Совместимость

- PHP 8.1+
- Расширения (опционально): zlib, igbinary, msgpack

## Лицензия

Часть проекта app/basic-utilities

# FileCache - Модуль файлового кэширования

## Описание

FileCache - это мощный и гибкий модуль для кэширования данных в файловой системе. Он предоставляет широкий набор функций для эффективного управления кэшем с поддержкой тегов, сжатия, блокировок и статистики.

## Основные возможности

- **Несколько сериализаторов**: native, JSON, igbinary, msgpack
- **Сжатие данных**: автоматическое сжатие больших значений с помощью zlib
- **Шардирование**: распределение файлов по поддиректориям для оптимизации производительности
- **Теги**: группировка элементов кэша для массового удаления
- **Блокировки**: поддержка эксклюзивных блокировок для атомарных операций
- **Статистика**: отслеживание попаданий, промахов и эффективности кэша
- **TTL**: гибкое управление временем жизни элементов
- **Атомарные операции**: инкремент, декремент, touch
- **Сборка мусора**: автоматическое удаление устаревших элементов

## Установка и настройка

### Использование конфигурационного файла

Создайте или используйте существующий файл `/config/filecache.json`:

```json
{
    "cacheDirectory": "/tmp/file_cache",
    "useSharding": true,
    "shardingDepth": 2,
    "defaultTtl": 3600,
    "enableStatistics": true
}
```

Загрузка конфигурации из файла:

```php
use Cache\FileCache;

// Загрузка из файла конфигурации
$cache = new FileCache([
    'configFile' => __DIR__ . '/config/filecache.json'
]);

// Или с переопределением параметров
$cache = new FileCache([
    'configFile' => __DIR__ . '/config/filecache.json',
    'cacheDirectory' => '/custom/path' // переопределяет значение из файла
]);
```

### Использование массива конфигурации

```php
use Cache\FileCache;
use Cache\FileCacheConfig;

$config = new FileCacheConfig([
    'cacheDirectory' => __DIR__ . '/cache',
    'useSharding' => true,
    'shardingDepth' => 2,
    'defaultTtl' => 3600,
    'enableStatistics' => true,
    'compressionEnabled' => true,
    'serializer' => 'json',
]);

$cache = new FileCache($config);
```

## Параметры конфигурации

### Основные параметры

- `cacheDirectory` - абсолютный путь к директории для хранения файлов кэша
- `directoryPermissions` - права доступа для создаваемых директорий (0755)
- `filePermissions` - права доступа для создаваемых файлов (0644)
- `fileExtension` - расширение файлов кэша (по умолчанию `.cache`)

### Шардирование

- `useSharding` - использовать шардирование файлов по поддиректориям
- `shardingDepth` - глубина шардирования (количество уровней вложенности)

### Время жизни

- `defaultTtl` - время жизни по умолчанию в секундах (null = без ограничений)
- `maxTtl` - максимальное время жизни в секундах
- `checkExpiredOnRead` - проверять истечение при чтении

### Сериализация и сжатие

- `serializer` - сериализатор: `native`, `json`, `igbinary`, `msgpack`
- `jsonOptions` - опции для JSON-сериализации
- `compressionEnabled` - включить сжатие данных
- `compressionLevel` - уровень сжатия (1-9)
- `compressionThreshold` - минимальный размер для сжатия в байтах

### Блокировки и безопасность

- `fileLocking` - использовать блокировку файлов
- `lockTimeout` - время ожидания блокировки в секундах
- `lockRetries` - количество попыток получения блокировки
- `atomicWrites` - атомарная запись через временные файлы

### Производительность

- `keyHashAlgorithm` - алгоритм хеширования: `sha1`, `md5`, `xxh3`
- `fsyncOnWrite` - синхронизация данных на диск при записи
- `statCacheDisabled` - отключить кэширование метаданных PHP
- `preloadEnabled` - загружать весь кэш в память при инициализации

### Ограничения

- `maxCacheSize` - максимальный размер кэша в байтах
- `maxItemSize` - максимальный размер одного элемента в байтах

### Сборка мусора

- `gcProbability` - вероятность запуска сборщика мусора (числитель)
- `gcDivisor` - делитель для вероятности (знаменатель)

### Дополнительные параметры

- `keyPrefix` - префикс для всех ключей
- `namespace` - пространство имен для изоляции групп
- `enableStatistics` - собирать статистику использования
- `errorHandling` - стратегия обработки ошибок: `throw`, `log`, `silent`

## Примеры использования

### Базовые операции

```php
// Сохранение значения
$cache->set('user:1', ['name' => 'Иван', 'email' => 'ivan@example.com']);

// Получение значения
$user = $cache->get('user:1');

// Удаление значения
$cache->delete('user:1');

// Проверка существования
if ($cache->has('user:1')) {
    // ключ существует
}

// Очистка всего кэша
$cache->clear();
```

### Работа с TTL

```php
// Сохранение с TTL 60 секунд
$cache->set('session:token', 'abc123', 60);

// С использованием DateInterval
$cache->set('key', 'value', new DateInterval('PT1H')); // 1 час

// Продление срока действия
$cache->touch('session:token', 120); // продлить на 120 секунд
```

### Множественные операции

```php
// Сохранение множества значений
$cache->setMultiple([
    'key1' => 'value1',
    'key2' => 'value2',
    'key3' => 'value3',
], 300);

// Получение множества значений
$values = $cache->getMultiple(['key1', 'key2', 'key3']);

// Удаление множества значений
$cache->deleteMultiple(['key1', 'key2']);
```

### Паттерн Remember

```php
// Получить из кэша или выполнить callback
$data = $cache->remember('expensive:calculation', function() {
    // Тяжелые вычисления
    return calculateExpensiveData();
}, 3600);
```

### Атомарные операции

```php
// Инкремент
$cache->set('counter', 0);
$newValue = $cache->increment('counter', 5); // 5

// Декремент
$newValue = $cache->decrement('counter', 2); // 3
```

### Работа с тегами

```php
// Сохранение с тегами
$cache->tags(['users', 'premium'])->set('user:100', $userData);
$cache->tags(['users', 'free'])->set('user:101', $userData);

// Удаление всех элементов с тегом
$cache->deleteByTag('premium'); // удалит user:100
```

### Pull (получить и удалить)

```php
// Получить значение и сразу удалить его
$token = $cache->pull('one_time_token');
```

### Метаданные и статистика

```php
// Получить метаданные элемента
$metadata = $cache->getMetadata('key');
// ['created' => 1234567890, 'expires' => 1234571490, 'size' => 1024, 'hits' => 5, 'tags' => []]

// Статистика кэша
$stats = $cache->getStats();
// ['hits' => 100, 'misses' => 10, 'writes' => 50, 'deletes' => 5, 'hit_rate' => 90.91, ...]

// Размер и количество элементов
$size = $cache->getSize(); // в байтах
$count = $cache->getItemCount();
```

### Сборка мусора

```php
// Принудительная сборка мусора
$deleted = $cache->prune(); // удаляет все истекшие элементы

// Автоматическая сборка (с учетом вероятности)
$deleted = $cache->gc();

// Удаление пустых директорий
$cache->vacuum();
```

### Предзагрузка

```php
// Предзагрузить указанные ключи в память
$loaded = $cache->warmup(['key1', 'key2', 'key3']);
```

### Проверка состояния

```php
// Проверка здоровья кэша
$health = $cache->healthCheck();
/*
[
    'directory_exists' => true,
    'directory_readable' => true,
    'directory_writable' => true,
    'free_space' => 1073741824,
    'serializer_available' => true,
    'compression_available' => true
]
*/
```

### Блокировки

```php
// Ручная блокировка (для критических секций)
if ($cache->lock('critical:resource', 10)) {
    try {
        // Критическая секция
        $value = $cache->get('critical:resource');
        // ... обработка ...
        $cache->set('critical:resource', $newValue);
    } finally {
        $cache->unlock('critical:resource');
    }
}
```

## Расширенные возможности

### Различные сериализаторы

```php
// JSON (удобно для отладки и совместимости)
$cache = new FileCache(['serializer' => 'json']);

// Native PHP (универсальный, но больше размер)
$cache = new FileCache(['serializer' => 'native']);

// igbinary (быстрый и компактный, требует расширение)
$cache = new FileCache(['serializer' => 'igbinary']);

// msgpack (быстрый и компактный, требует расширение)
$cache = new FileCache(['serializer' => 'msgpack']);
```

### Сжатие

```php
$cache = new FileCache([
    'compressionEnabled' => true,
    'compressionLevel' => 6,       // 1-9 (1=быстро, 9=сильнее)
    'compressionThreshold' => 1024, // сжимать только если > 1KB
]);
```

### Пространства имен

```php
// Изолировать группы кэша
$userCache = new FileCache(['namespace' => 'users:']);
$productCache = new FileCache(['namespace' => 'products:']);

// Ключи не будут конфликтовать
$userCache->set('123', $userData);
$productCache->set('123', $productData);
```

## Обработка ошибок

```php
// Выбрасывать исключения (по умолчанию)
$cache = new FileCache(['errorHandling' => 'throw']);

// Логировать ошибки
$cache = new FileCache(['errorHandling' => 'log']);

// Игнорировать ошибки
$cache = new FileCache(['errorHandling' => 'silent']);
```

## Производительность

### Рекомендации для высокой нагрузки

1. **Включите шардирование** для больших кэшей (>10000 элементов)
   ```php
   'useSharding' => true,
   'shardingDepth' => 2,
   ```

2. **Используйте быстрые сериализаторы** (igbinary или msgpack)

3. **Настройте сборку мусора** для избежания переполнения
   ```php
   'gcProbability' => 1,
   'gcDivisor' => 100,
   ```

4. **Установите maxCacheSize** для контроля размера
   ```php
   'maxCacheSize' => 1024 * 1024 * 1024, // 1GB
   ```

5. **Отключите fsync** если не критична потеря данных при сбое
   ```php
   'fsyncOnWrite' => false,
   ```

6. **Используйте xxh3** для хеширования (если доступен)
   ```php
   'keyHashAlgorithm' => 'xxh3',
   ```

## Примечания

- Все методы безопасны для многопроцессного использования при включенной блокировке
- Класс автоматически создает необходимые директории
- При уничтожении объекта все блокировки освобождаются автоматически
- Теги сохраняются в отдельном индексном файле `.tag_index.json`

## См. также

- `examples/cache_example.php` - полный набор примеров
- `config/filecache.json` - пример конфигурационного файла

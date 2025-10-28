<?php

declare(strict_types=1);

namespace Cache;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class FileCache
{
    private const COMPRESSED_PREFIX = 'compressed:';
    private const TAG_INDEX_FILENAME = '.tag_index.json';
    private const LOCKS_DIRECTORY = 'locks';

    private FileCacheConfig $config;

    /**
     * @var array<string, int>
     */
    private array $stats = [
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
        'deletes' => 0,
    ];

    /**
     * @var array<string, resource>
     */
    private array $locks = [];

    /**
     * @var array<string, array<int, string>>
     */
    private array $tagIndex = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $memoryCache = [];

    /**
     * @var array<int, string>|null
     */
    private ?array $currentTags = null;

    /**
     * Конструктор файлового кэша
     * 
     * Инициализирует файловый кэш с заданной конфигурацией. Конфигурация может быть
     * передана в виде массива или объекта FileCacheConfig. При использовании массива,
     * можно указать путь к JSON-файлу конфигурации через ключ 'configFile'.
     * 
     * @param FileCacheConfig|array $config Конфигурация кэша (массив или объект FileCacheConfig)
     * @throws \InvalidArgumentException Если конфигурация невалидна
     * @throws \RuntimeException Если не удается создать директорию кэша
     */
    public function __construct(FileCacheConfig|array $config = [])
    {
        if (is_array($config)) {
            // Поддержка загрузки конфигурации из файла
            if (isset($config['configFile']) && is_string($config['configFile'])) {
                $configFile = $config['configFile'];
                if (file_exists($configFile)) {
                    $fileConfig = json_decode(file_get_contents($configFile), true);
                    if (is_array($fileConfig)) {
                        // Удаляем служебные поля из конфигурации
                        unset($fileConfig['_comment'], $fileConfig['_fields']);
                        $config = array_merge($fileConfig, $config);
                        unset($config['configFile']);
                    }
                }
            }
            $config = new FileCacheConfig($config);
        }

        $config->validate();
        $config->cacheDirectory = rtrim($config->cacheDirectory, DIRECTORY_SEPARATOR);
        $this->config = $config;

        $this->ensureDirectoryExists($this->config->cacheDirectory);
        $this->initializeTagIndex();

        if ($this->config->preloadEnabled) {
            $this->preloadCache();
        }
    }

    /**
     * Получает значение из кэша по ключу
     * 
     * Возвращает закэшированное значение, если оно существует и не истекло.
     * В противном случае возвращает значение по умолчанию.
     * 
     * @param string $key Ключ кэша
     * @param mixed $default Значение по умолчанию, если ключ не найден
     * @return mixed Закэшированное значение или значение по умолчанию
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->validateKey($key);

        try {
            if (array_key_exists($key, $this->memoryCache)) {
                $data = $this->memoryCache[$key];
                if ($this->config->checkExpiredOnRead && $this->isExpiredData($data)) {
                    $this->deleteInternal($key, $this->getPath($key), $data, false);
                    $this->incrementStat('misses');
                    return $default;
                }

                $this->incrementStat('hits');
                $this->incrementItemHits($key, $data);
                return $data['value'];
            }

            $filePath = $this->getPath($key);
            if (!is_file($filePath)) {
                $this->incrementStat('misses');
                return $default;
            }

            $data = $this->readFile($filePath);
            if ($data === null) {
                $this->deleteInternal($key, $filePath, null, false);
                $this->incrementStat('misses');
                return $default;
            }

            if ($this->config->checkExpiredOnRead && $this->isExpiredData($data)) {
                $this->deleteInternal($key, $filePath, $data, false);
                $this->incrementStat('misses');
                return $default;
            }

            $this->rememberInMemory($key, $data);
            $this->incrementStat('hits');
            $this->incrementItemHits($key, $data);

            return $data['value'];
        } catch (Throwable $exception) {
            $this->handleError($exception);
            return $default;
        }
    }

    /**
     * Сохраняет значение в кэш
     * 
     * Записывает значение в кэш с указанным ключом и временем жизни.
     * 
     * @param string $key Ключ кэша
     * @param mixed $value Значение для сохранения
     * @param null|int|DateInterval $ttl Время жизни в секундах или DateInterval (null = использовать defaultTtl)
     * @return bool True в случае успеха, false в случае ошибки
     */
    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $this->validateKey($key);
        $tags = $this->consumeTags();

        try {
            return $this->doSet($key, $value, $ttl, $tags, null);
        } catch (Throwable $exception) {
            $this->handleError($exception);
            return false;
        }
    }

    /**
     * Удаляет значение из кэша
     * 
     * Удаляет элемент кэша с указанным ключом.
     * 
     * @param string $key Ключ кэша для удаления
     * @return bool True в случае успеха, false в случае ошибки
     */
    public function delete(string $key): bool
    {
        $this->validateKey($key);

        try {
            return $this->deleteInternal($key, $this->getPath($key));
        } catch (Throwable $exception) {
            $this->handleError($exception);
            return false;
        }
    }

    /**
     * Очищает весь кэш
     * 
     * Удаляет все элементы из кэша, включая индексы тегов и статистику.
     * 
     * @return bool True в случае успеха, false в случае ошибки
     */
    public function clear(): bool
    {
        try {
            $this->deleteDirectoryContents($this->config->cacheDirectory);
            $this->memoryCache = [];
            $this->tagIndex = [];
            $this->saveTagIndex();
            $this->resetStats();
            $this->ensureDirectoryExists($this->config->cacheDirectory);
            return true;
        } catch (Throwable $exception) {
            $this->handleError($exception);
            return false;
        }
    }

    /**
     * Проверяет существование ключа в кэше
     * 
     * Проверяет, существует ли элемент с указанным ключом в кэше и не истек ли срок его действия.
     * 
     * @param string $key Ключ кэша для проверки
     * @return bool True если ключ существует и актуален, false в противном случае
     */
    public function has(string $key): bool
    {
        $this->validateKey($key);

        try {
            if (array_key_exists($key, $this->memoryCache)) {
                $data = $this->memoryCache[$key];
                if ($this->config->checkExpiredOnRead && $this->isExpiredData($data)) {
                    $this->deleteInternal($key, $this->getPath($key), $data, false);
                    return false;
                }
                return true;
            }

            $filePath = $this->getPath($key);
            if (!is_file($filePath)) {
                return false;
            }

            $data = $this->readFile($filePath);
            if ($data === null) {
                $this->deleteInternal($key, $filePath, null, false);
                return false;
            }

            if ($this->config->checkExpiredOnRead && $this->isExpiredData($data)) {
                $this->deleteInternal($key, $filePath, $data, false);
                return false;
            }

            $this->rememberInMemory($key, $data);
            return true;
        } catch (Throwable $exception) {
            $this->handleError($exception);
            return false;
        }
    }

    /**
     * Получает несколько значений из кэша
     * 
     * Возвращает массив значений для указанных ключей.
     * 
     * @param iterable $keys Список ключей для получения
     * @param mixed $default Значение по умолчанию для несуществующих ключей
     * @return iterable Ассоциативный массив ключей и значений
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get((string) $key, $default);
        }
        return $result;
    }

    /**
     * Сохраняет несколько значений в кэш
     * 
     * Записывает множество пар ключ-значение в кэш с указанным временем жизни.
     * 
     * @param iterable $values Ассоциативный массив или итератор пар ключ-значение
     * @param null|int|DateInterval $ttl Время жизни для всех элементов
     * @return bool True если все операции успешны, false если хотя бы одна не удалась
     */
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        $tags = $this->consumeTags();
        $success = true;

        foreach ($values as $key => $value) {
            try {
                if (!$this->doSet((string) $key, $value, $ttl, $tags, null)) {
                    $success = false;
                }
            } catch (Throwable $exception) {
                $this->handleError($exception);
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Удаляет несколько значений из кэша
     * 
     * Удаляет элементы кэша по списку ключей.
     * 
     * @param iterable $keys Список ключей для удаления
     * @return bool True если все операции успешны, false если хотя бы одна не удалась
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            if (!$this->delete((string) $key)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Получает значение из кэша или выполняет callback и сохраняет результат
     * 
     * Возвращает значение из кэша, если оно существует. В противном случае выполняет callback,
     * сохраняет его результат в кэш и возвращает этот результат.
     * 
     * @param string $key Ключ кэша
     * @param callable $callback Функция для получения значения, если оно отсутствует в кэше
     * @param null|int|DateInterval $ttl Время жизни для сохраняемого значения
     * @return mixed Значение из кэша или результат выполнения callback
     */
    public function remember(string $key, callable $callback, null|int|DateInterval $ttl = null): mixed
    {
        $sentinel = new \stdClass();
        $value = $this->get($key, $sentinel);

        if ($value !== $sentinel) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Инкрементирует числовое значение в кэше
     * 
     * Увеличивает числовое значение в кэше на указанную величину.
     * Если ключ не существует, создается с начальным значением равным инкременту.
     * 
     * @param string $key Ключ кэша
     * @param int $value Значение инкремента (по умолчанию 1)
     * @return int|false Новое значение или false в случае ошибки
     * @throws RuntimeException Если значение в кэше не является числом
     */
    public function increment(string $key, int $value = 1): int|false
    {
        $this->validateKey($key);

        if (!$this->lock($key)) {
            return false;
        }

        try {
            $filePath = $this->getPath($key);
            $data = array_key_exists($key, $this->memoryCache)
                ? $this->memoryCache[$key]
                : (is_file($filePath) ? $this->readFile($filePath) : null);

            if ($data !== null && $this->config->checkExpiredOnRead && $this->isExpiredData($data)) {
                $this->deleteInternal($key, $filePath, $data);
                $data = null;
            }

            $current = 0;
            if ($data !== null) {
                if (!is_numeric($data['value'])) {
                    throw new RuntimeException('Невозможно инкрементировать нечисловое значение кэша.');
                }
                $current = (int) $data['value'];
            }

            $newValue = $current + $value;
            $remainingTtl = $data !== null ? $this->calculateRemainingTtl($data) : null;
            $tags = $data !== null ? (array) ($data['tags'] ?? []) : [];

            if ($this->currentTags !== null) {
                $tags = $this->consumeTags();
            }

            if (!$this->doSet($key, $newValue, $remainingTtl, $tags, $data)) {
                return false;
            }

            return $newValue;
        } catch (Throwable $exception) {
            $this->handleError($exception);
            return false;
        } finally {
            $this->unlock($key);
        }
    }

    /**
     * Декрементирует числовое значение в кэше
     * 
     * Уменьшает числовое значение в кэше на указанную величину.
     * 
     * @param string $key Ключ кэша
     * @param int $value Значение декремента (по умолчанию 1)
     * @return int|false Новое значение или false в случае ошибки
     */
    public function decrement(string $key, int $value = 1): int|false
    {
        return $this->increment($key, -$value);
    }

    /**
     * Обновляет время жизни элемента кэша
     * 
     * Продлевает срок действия существующего элемента кэша без изменения его значения.
     * 
     * @param string $key Ключ кэша
     * @param null|int|DateInterval $ttl Новое время жизни
     * @return bool True в случае успеха, false если ключ не существует или произошла ошибка
     */
    public function touch(string $key, null|int|DateInterval $ttl = null): bool
    {
        $this->validateKey($key);

        try {
            $filePath = $this->getPath($key);
            $data = $this->readFile($filePath);

            if ($data === null) {
                return false;
            }

            if ($this->isExpiredData($data)) {
                $this->deleteInternal($key, $filePath, $data);
                return false;
            }

            $ttlSeconds = $this->normalizeTtl($ttl);
            if ($ttlSeconds !== null && $ttlSeconds <= 0) {
                return $this->delete($key);
            }

            $data['expires'] = $ttlSeconds !== null ? time() + $ttlSeconds : null;
            $payload = $this->serialize($data);

            if ($this->config->maxItemSize !== null && strlen($payload) > $this->config->maxItemSize) {
                throw new InvalidArgumentException('Размер элемента превышает ограничение maxItemSize.');
            }

            if (!$this->writeFile($filePath, $payload)) {
                return false;
            }

            $this->rememberInMemory($key, $data);
            return true;
        } catch (Throwable $exception) {
            $this->handleError($exception);
            return false;
        }
    }

    /**
     * Получает значение из кэша и удаляет его
     * 
     * Возвращает значение по ключу и сразу же удаляет его из кэша.
     * 
     * @param string $key Ключ кэша
     * @param mixed $default Значение по умолчанию, если ключ не найден
     * @return mixed Значение из кэша или значение по умолчанию
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $sentinel = new \stdClass();
        $value = $this->get($key, $sentinel);

        if ($value === $sentinel) {
            return $default;
        }

        $this->delete($key);
        return $value;
    }

    /**
     * Устанавливает теги для следующей операции записи в кэш
     * 
     * Позволяет группировать элементы кэша по тегам для последующего удаления по тегу.
     * Теги применяются только к следующей операции set или setMultiple.
     * 
     * @param array $tags Массив тегов
     * @return self Возвращает текущий экземпляр для цепочки вызовов
     */
    public function tags(array $tags): self
    {
        $normalized = [];
        foreach ($tags as $tag) {
            $tag = (string) $tag;
            if ($tag !== '') {
                $normalized[] = $tag;
            }
        }

        $this->currentTags = array_values(array_unique($normalized));
        return $this;
    }

    /**
     * Удаляет все элементы кэша с указанным тегом
     * 
     * Удаляет все элементы кэша, которые были помечены указанным тегом.
     * 
     * @param string $tag Тег для удаления
     * @return bool True в случае успеха, false если хотя бы одна операция удаления не удалась
     */
    public function deleteByTag(string $tag): bool
    {
        $tag = (string) $tag;
        if ($tag === '' || !isset($this->tagIndex[$tag])) {
            return true;
        }

        $keys = $this->tagIndex[$tag];
        $success = true;

        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }

        unset($this->tagIndex[$tag]);
        $this->saveTagIndex();

        return $success;
    }

    /**
     * Очищает весь кэш (алиас для clear)
     * 
     * Удаляет все элементы из кэша. Эквивалентен методу clear().
     * 
     * @return bool True в случае успеха, false в случае ошибки
     */
    public function flush(): bool
    {
        return $this->clear();
    }

    /**
     * Запускает сборщик мусора для удаления устаревших элементов кэша
     * 
     * Удаляет все истекшие элементы кэша и при необходимости обеспечивает соблюдение maxCacheSize.
     * 
     * @param bool $force Принудительный запуск сборщика мусора (игнорирование вероятности)
     * @return int Количество удаленных элементов
     */
    public function gc(bool $force = false): int
    {
        if (!$force && !$this->shouldRunGc()) {
            return 0;
        }

        $deleted = 0;

        try {
            $iterator = $this->createCacheIterator();

            foreach ($iterator as $fileInfo) {
                if (!$fileInfo->isFile()) {
                    continue;
                }

                $filename = $fileInfo->getFilename();
                if ($filename === self::TAG_INDEX_FILENAME || !str_ends_with($filename, $this->config->fileExtension)) {
                    continue;
                }

                $path = $fileInfo->getPathname();
                $data = null;

                try {
                    $data = $this->readFile($path);
                } catch (Throwable $exception) {
                    $this->handleError($exception);
                }

                if ($data === null || $this->isExpiredData($data)) {
                    if (@unlink($path)) {
                        $deleted++;
                        $this->incrementStat('deletes');

                        if ($data !== null && isset($data['key'])) {
                            $key = (string) $data['key'];
                            $this->removeFromTagIndex($key, (array) ($data['tags'] ?? []));
                            unset($this->memoryCache[$key]);
                        }
                    }
                }
            }

            if ($this->config->maxCacheSize !== null) {
                $deleted += $this->enforceMaxCacheSize();
            }
        } catch (Throwable $exception) {
            $this->handleError($exception);
        }

        return $deleted;
    }

    /**
     * Удаляет все истекшие элементы из кэша
     * 
     * Принудительно запускает сборщик мусора для очистки устаревших данных.
     * Эквивалентен gc(true).
     * 
     * @return int Количество удаленных элементов
     */
    public function prune(): int
    {
        return $this->gc(true);
    }

    /**
     * Удаляет пустые директории из кэша
     * 
     * Очищает структуру директорий кэша от пустых поддиректорий.
     * 
     * @return bool True в случае успеха, false в случае ошибки
     */
    public function vacuum(): bool
    {
        try {
            $this->removeEmptyDirectories($this->config->cacheDirectory);
            return true;
        } catch (Throwable $exception) {
            $this->handleError($exception);
            return false;
        }
    }

    /**
     * Возвращает общий размер кэша в байтах
     * 
     * Подсчитывает и возвращает размер всех файлов кэша.
     * 
     * @return int Размер кэша в байтах
     */
    public function getSize(): int
    {
        $total = 0;

        try {
            $iterator = $this->createCacheIterator();
            foreach ($iterator as $fileInfo) {
                if (!$fileInfo->isFile()) {
                    continue;
                }

                $filename = $fileInfo->getFilename();
                if ($filename === self::TAG_INDEX_FILENAME || !str_ends_with($filename, $this->config->fileExtension)) {
                    continue;
                }

                $total += $fileInfo->getSize();
            }
        } catch (Throwable $exception) {
            $this->handleError($exception);
        }

        return $total;
    }

    /**
     * Возвращает количество элементов в кэше
     * 
     * Подсчитывает и возвращает количество файлов кэша.
     * 
     * @return int Количество элементов в кэше
     */
    public function getItemCount(): int
    {
        $count = 0;

        try {
            $iterator = $this->createCacheIterator();
            foreach ($iterator as $fileInfo) {
                if (!$fileInfo->isFile()) {
                    continue;
                }

                $filename = $fileInfo->getFilename();
                if ($filename === self::TAG_INDEX_FILENAME || !str_ends_with($filename, $this->config->fileExtension)) {
                    continue;
                }

                $count++;
            }
        } catch (Throwable $exception) {
            $this->handleError($exception);
        }

        return $count;
    }

    /**
     * Возвращает статистику использования кэша
     * 
     * Возвращает массив со статистикой: попадания, промахи, записи, удаления,
     * процент попаданий, размер кэша и количество элементов.
     * 
     * @return array Массив статистики кэша
     */
    public function getStats(): array
    {
        $total = $this->stats['hits'] + $this->stats['misses'];
        $hitRate = $total > 0 ? round(($this->stats['hits'] / $total) * 100, 2) : 0.0;

        return [
            'hits' => $this->stats['hits'],
            'misses' => $this->stats['misses'],
            'writes' => $this->stats['writes'],
            'deletes' => $this->stats['deletes'],
            'hit_rate' => $hitRate,
            'size' => $this->getSize(),
            'item_count' => $this->getItemCount(),
        ];
    }

    /**
     * Сбрасывает статистику использования кэша
     * 
     * Обнуляет все счетчики статистики (попадания, промахи, записи, удаления).
     */
    public function resetStats(): void
    {
        $this->stats = [
            'hits' => 0,
            'misses' => 0,
            'writes' => 0,
            'deletes' => 0,
        ];
    }

    /**
     * Возвращает метаданные элемента кэша
     * 
     * Получает информацию о элементе кэша: время создания, истечения, размер, количество обращений и теги.
     * 
     * @param string $key Ключ кэша
     * @return array|null Массив метаданных или null если ключ не найден
     */
    public function getMetadata(string $key): ?array
    {
        $this->validateKey($key);
        $filePath = $this->getPath($key);

        try {
            $data = array_key_exists($key, $this->memoryCache)
                ? $this->memoryCache[$key]
                : $this->readFile($filePath);

            if ($data === null) {
                return null;
            }

            if ($this->config->statCacheDisabled) {
                clearstatcache(true, $filePath);
            }

            if (!is_file($filePath)) {
                return null;
            }

            return [
                'created' => $data['created'] ?? null,
                'expires' => $data['expires'] ?? null,
                'size' => filesize($filePath) ?: 0,
                'hits' => (int) ($data['hits'] ?? 0),
                'tags' => (array) ($data['tags'] ?? []),
            ];
        } catch (Throwable $exception) {
            $this->handleError($exception);
            return null;
        }
    }

    /**
     * Проверяет, истек ли срок действия элемента кэша
     * 
     * Проверяет, существует ли элемент и не истек ли срок его действия.
     * 
     * @param string $key Ключ кэша
     * @return bool True если элемент истек или не существует, false если элемент актуален
     */
    public function isExpired(string $key): bool
    {
        $this->validateKey($key);

        try {
            $data = array_key_exists($key, $this->memoryCache)
                ? $this->memoryCache[$key]
                : $this->readFile($this->getPath($key));

            if ($data === null) {
                return true;
            }

            return $this->isExpiredData($data);
        } catch (Throwable $exception) {
            $this->handleError($exception);
            return true;
        }
    }

    /**
     * Валидирует ключ кэша
     * 
     * Проверяет, что ключ кэша соответствует требованиям: не пустой, не длиннее 255 символов,
     * и не содержит недопустимых символов.
     * 
     * @param string $key Ключ кэша для проверки
     * @return bool True если ключ валиден
     * @throws InvalidArgumentException Если ключ невалиден
     */
    public function validateKey(string $key): bool
    {
        if ($key === '') {
            throw new InvalidArgumentException('Ключ кэша не может быть пустым.');
        }

        if (strlen($key) > 255) {
            throw new InvalidArgumentException('Ключ кэша слишком длинный (максимум 255 символов).');
        }

        if (preg_match('/[{}()\/\\@:]/', $key)) {
            throw new InvalidArgumentException('Ключ кэша содержит недопустимые символы.');
        }

        return true;
    }

    /**
     * Предзагружает указанные ключи в память
     * 
     * Загружает данные для указанных ключей из файлов в память для быстрого доступа.
     * 
     * @param array $keys Массив ключей для предзагрузки
     * @return int Количество успешно загруженных элементов
     */
    public function warmup(array $keys): int
    {
        $loaded = 0;

        foreach ($keys as $key) {
            $this->validateKey($key);
            $filePath = $this->getPath($key);

            if (!is_file($filePath)) {
                continue;
            }

            try {
                $data = $this->readFile($filePath);
            } catch (Throwable $exception) {
                $this->handleError($exception);
                continue;
            }

            if ($data === null || $this->isExpiredData($data)) {
                continue;
            }

            $this->memoryCache[$key] = $data;
            $loaded++;
        }

        return $loaded;
    }

    /**
     * Проверяет состояние кэша
     * 
     * Возвращает информацию о доступности директории кэша, свободном месте и
     * доступности необходимых расширений PHP.
     * 
     * @return array Массив с результатами проверки состояния
     */
    public function healthCheck(): array
    {
        $directory = $this->config->cacheDirectory;

        return [
            'directory_exists' => is_dir($directory),
            'directory_readable' => is_readable($directory),
            'directory_writable' => is_writable($directory),
            'free_space' => is_dir($directory) ? @disk_free_space($directory) : 0,
            'serializer_available' => match ($this->config->serializer) {
                'igbinary' => extension_loaded('igbinary'),
                'msgpack' => extension_loaded('msgpack'),
                default => true,
            },
            'compression_available' => extension_loaded('zlib'),
        ];
    }

    /**
     * Возвращает путь к файлу кэша для указанного ключа
     * 
     * Генерирует путь к файлу кэша на основе хеша ключа с учетом настроек шардирования.
     * 
     * @param string $key Ключ кэша
     * @return string Полный путь к файлу кэша
     */
    public function getPath(string $key): string
    {
        $hash = $this->hashKey($this->getFullKey($key));
        $path = $this->config->cacheDirectory;

        if ($this->config->useSharding && $this->config->shardingDepth > 0) {
            $segmentLength = 2;
            for ($i = 0; $i < $this->config->shardingDepth; $i++) {
                $segment = substr($hash, $i * $segmentLength, $segmentLength);
                if ($segment === '') {
                    break;
                }
                $path .= DIRECTORY_SEPARATOR . $segment;
            }
        }

        return $path . DIRECTORY_SEPARATOR . $hash . $this->config->fileExtension;
    }

    /**
     * Сериализует значение для сохранения в кэш
     * 
     * Преобразует значение в строку с использованием выбранного сериализатора
     * и применяет сжатие при необходимости.
     * 
     * @param mixed $value Значение для сериализации
     * @return string Сериализованная строка
     * @throws RuntimeException Если сериализация или сжатие не удались
     */
    public function serialize(mixed $value): string
    {
        $payload = match ($this->config->serializer) {
            'json' => $this->encodeJson($value),
            'igbinary' => $this->encodeIgbinary($value),
            'msgpack' => $this->encodeMsgpack($value),
            default => serialize($value),
        };

        if ($this->config->compressionEnabled && strlen($payload) >= $this->config->compressionThreshold) {
            if (!extension_loaded('zlib')) {
                throw new RuntimeException('Невозможно сжать данные кэша без расширения zlib.');
            }

            $compressed = gzcompress($payload, $this->config->compressionLevel);
            if ($compressed === false) {
                throw new RuntimeException('Не удалось сжать данные кэша.');
            }

            return self::COMPRESSED_PREFIX . $compressed;
        }

        return $payload;
    }

    /**
     * Десериализует значение из кэша
     * 
     * Преобразует сериализованную строку обратно в значение с учетом
     * сжатия и выбранного сериализатора.
     * 
     * @param string $payload Сериализованная строка
     * @return mixed Десериализованное значение
     * @throws RuntimeException Если десериализация или распаковка не удались
     */
    public function unserialize(string $payload): mixed
    {
        $data = $payload;

        if (str_starts_with($data, self::COMPRESSED_PREFIX)) {
            if (!extension_loaded('zlib')) {
                throw new RuntimeException('Невозможно распаковать данные кэша без расширения zlib.');
            }

            $decompressed = gzuncompress(substr($data, strlen(self::COMPRESSED_PREFIX)));
            if ($decompressed === false) {
                throw new RuntimeException('Не удалось распаковать данные кэша.');
            }

            $data = $decompressed;
        }

        return match ($this->config->serializer) {
            'json' => $this->decodeJson($data),
            'igbinary' => $this->decodeIgbinary($data),
            'msgpack' => $this->decodeMsgpack($data),
            default => unserialize($data),
        };
    }

    /**
     * Устанавливает блокировку для ключа кэша
     * 
     * Создает эксклюзивную блокировку для предотвращения одновременного доступа
     * к элементу кэша из разных процессов.
     * 
     * @param string $key Ключ кэша для блокировки
     * @param int|null $timeout Время ожидания блокировки в секундах (null = использовать lockTimeout из конфига)
     * @return bool True если блокировка успешна, false в противном случае
     */
    public function lock(string $key, int $timeout = null): bool
    {
        if (!$this->config->fileLocking) {
            return true;
        }

        $timeout = $timeout ?? $this->config->lockTimeout;
        $lockPath = $this->getLockPath($key);
        $this->ensureDirectoryExists(dirname($lockPath));

        $handle = fopen($lockPath, 'c+');
        if ($handle === false) {
            return false;
        }

        $attempts = 0;
        $deadline = $timeout > 0 ? microtime(true) + $timeout : null;

        while (true) {
            if (flock($handle, LOCK_EX | LOCK_NB)) {
                $this->locks[$key] = $handle;
                return true;
            }

            $attempts++;
            if (($this->config->lockRetries > 0 && $attempts > $this->config->lockRetries) ||
                ($deadline !== null && microtime(true) >= $deadline)) {
                break;
            }

            usleep(100000);
        }

        fclose($handle);
        return false;
    }

    /**
     * Снимает блокировку с ключа кэша
     * 
     * Освобождает ранее установленную блокировку для указанного ключа.
     * 
     * @param string $key Ключ кэша для разблокировки
     * @return bool True если разблокировка успешна, false если блокировка не была установлена
     */
    public function unlock(string $key): bool
    {
        if (!$this->config->fileLocking) {
            return true;
        }

        if (!isset($this->locks[$key])) {
            return false;
        }

        $handle = $this->locks[$key];
        flock($handle, LOCK_UN);
        fclose($handle);

        unset($this->locks[$key]);
        @unlink($this->getLockPath($key));

        return true;
    }

    /**
     * Деструктор класса
     * 
     * Автоматически освобождает все блокировки при уничтожении объекта.
     */
    public function __destruct()
    {
        foreach (array_keys($this->locks) as $key) {
            $this->unlock($key);
        }
    }

    /**
     * Потребляет и возвращает текущие теги
     * 
     * Возвращает установленные теги и сбрасывает их для следующей операции.
     * 
     * @return array Массив тегов
     */
    private function consumeTags(): array
    {
        $tags = $this->currentTags ?? [];
        $this->currentTags = null;

        $filtered = [];
        foreach ($tags as $tag) {
            $tag = (string) $tag;
            if ($tag !== '') {
                $filtered[] = $tag;
            }
        }

        return array_values(array_unique($filtered));
    }

    /**
     * Внутренний метод для сохранения значения в кэш
     * 
     * @param string $key Ключ кэша
     * @param mixed $value Значение для сохранения
     * @param null|int|DateInterval $ttl Время жизни
     * @param array $tags Теги для элемента
     * @param array|null $existingData Существующие данные элемента (если есть)
     * @return bool True в случае успеха, false в случае ошибки
     */
    private function doSet(string $key, mixed $value, null|int|DateInterval $ttl, array $tags, ?array $existingData): bool
    {
        $ttlSeconds = $this->normalizeTtl($ttl);

        if ($ttlSeconds !== null && $ttlSeconds <= 0) {
            return $this->delete($key);
        }

        $filePath = $this->getPath($key);
        $data = $existingData;

        if ($data === null && is_file($filePath)) {
            $data = $this->readFile($filePath);
        }

        if ($data !== null) {
            $this->removeFromTagIndex($key, (array) ($data['tags'] ?? []));
        }

        $now = time();

        $payloadData = [
            'key' => $key,
            'value' => $value,
            'created' => $data['created'] ?? $now,
            'updated' => $now,
            'expires' => $ttlSeconds !== null ? $now + $ttlSeconds : null,
            'hits' => $this->config->enableStatistics ? (int) ($data['hits'] ?? 0) : 0,
            'tags' => $tags,
        ];

        $serialized = $this->serialize($payloadData);
        if ($this->config->maxItemSize !== null && strlen($serialized) > $this->config->maxItemSize) {
            throw new InvalidArgumentException('Размер элемента превышает ограничение maxItemSize.');
        }

        if (!$this->writeFile($filePath, $serialized)) {
            return false;
        }

        $this->rememberInMemory($key, $payloadData);
        $this->updateTagIndex($key, $tags);
        $this->incrementStat('writes');

        if ($this->config->maxCacheSize !== null) {
            $this->enforceMaxCacheSize();
        }

        if ($this->shouldRunGc()) {
            $this->gc(true);
        }

        return true;
    }

    /**
     * Нормализует время жизни к секундам
     * 
     * @param null|int|DateInterval $ttl Время жизни
     * @return int|null Время жизни в секундах или null
     */
    private function normalizeTtl(null|int|DateInterval $ttl): ?int
    {
        if ($ttl === null) {
            return $this->config->defaultTtl;
        }

        if ($ttl instanceof DateInterval) {
            $now = new DateTimeImmutable();
            $ttl = $now->add($ttl)->getTimestamp() - $now->getTimestamp();
        }

        if ($ttl < 0) {
            return 0;
        }

        if ($this->config->maxTtl !== null) {
            $ttl = min($ttl, $this->config->maxTtl);
        }

        return $ttl;
    }

    /**
     * Вычисляет оставшееся время жизни элемента
     * 
     * @param array $data Данные элемента кэша
     * @return int|null Оставшееся время в секундах или null если без ограничений
     */
    private function calculateRemainingTtl(array $data): ?int
    {
        if (!isset($data['expires']) || $data['expires'] === null) {
            return null;
        }

        $remaining = (int) $data['expires'] - time();
        return $remaining > 0 ? $remaining : 0;
    }

    /**
     * Сохраняет данные в памяти
     * 
     * @param string $key Ключ кэша
     * @param array $data Данные для сохранения
     */
    private function rememberInMemory(string $key, array $data): void
    {
        if ($this->config->preloadEnabled || array_key_exists($key, $this->memoryCache)) {
            $this->memoryCache[$key] = $data;
        }
    }

    /**
     * Внутренний метод для удаления элемента кэша
     * 
     * @param string $key Ключ кэша
     * @param string $filePath Путь к файлу кэша
     * @param array|null $data Данные элемента (опционально)
     * @param bool $countStats Учитывать ли удаление в статистике
     * @return bool True в случае успеха
     */
    private function deleteInternal(string $key, string $filePath, ?array $data = null, bool $countStats = true): bool
    {
        if (!is_file($filePath)) {
            $this->removeFromTagIndex($key);
            unset($this->memoryCache[$key]);
            return true;
        }

        if ($data === null) {
            try {
                $data = $this->readFile($filePath);
            } catch (Throwable $exception) {
                $this->handleError($exception);
                $data = null;
            }
        }

        $result = @unlink($filePath);
        if ($result) {
            if ($countStats) {
                $this->incrementStat('deletes');
            }

            $this->removeFromTagIndex($key, (array) ($data['tags'] ?? []));
            unset($this->memoryCache[$key]);

            if ($this->config->statCacheDisabled) {
                clearstatcache(true, $filePath);
            }
        }

        return $result;
    }

    /**
     * Определяет, нужно ли запускать сборщик мусора
     * 
     * @return bool True если нужно запустить сборщик мусора
     */
    private function shouldRunGc(): bool
    {
        if ($this->config->gcProbability <= 0) {
            return false;
        }

        $random = mt_rand(1, $this->config->gcDivisor);
        return $random <= $this->config->gcProbability;
    }

    /**
     * Инкрементирует счетчик статистики
     * 
     * @param string $name Название счетчика
     */
    private function incrementStat(string $name): void
    {
        if (!$this->config->enableStatistics) {
            return;
        }

        if (isset($this->stats[$name])) {
            $this->stats[$name]++;
        }
    }

    /**
     * Инкрементирует счетчик обращений к элементу
     * 
     * @param string $key Ключ кэша
     * @param array $data Данные элемента
     */
    private function incrementItemHits(string $key, array $data): void
    {
        if (!$this->config->enableStatistics) {
            return;
        }

        $data['hits'] = (int) ($data['hits'] ?? 0) + 1;

        try {
            $this->writeFile($this->getPath($key), $this->serialize($data));
            $this->rememberInMemory($key, $data);
        } catch (Throwable $exception) {
            $this->handleError($exception);
        }
    }

    /**
     * Читает и десериализует файл кэша
     * 
     * @param string $filePath Путь к файлу кэша
     * @return array|null Данные из файла или null если файл не найден/поврежден
     */
    private function readFile(string $filePath): ?array
    {
        if (!is_file($filePath)) {
            return null;
        }

        if ($this->config->statCacheDisabled) {
            clearstatcache(true, $filePath);
        }

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            return null;
        }

        if ($this->config->fileLocking) {
            flock($handle, LOCK_SH);
        }

        $contents = stream_get_contents($handle);

        if ($this->config->fileLocking) {
            flock($handle, LOCK_UN);
        }

        fclose($handle);

        if ($contents === false || $contents === '') {
            return null;
        }

        $data = $this->unserialize($contents);
        if (!is_array($data)) {
            return null;
        }

        if (!isset($data['tags']) || !is_array($data['tags'])) {
            $data['tags'] = [];
        }

        return $data;
    }

    /**
     * Записывает данные в файл кэша
     * 
     * @param string $filePath Путь к файлу кэша
     * @param string $payload Данные для записи
     * @return bool True в случае успеха, false в случае ошибки
     */
    private function writeFile(string $filePath, string $payload): bool
    {
        $directory = dirname($filePath);
        $this->ensureDirectoryExists($directory);

        $tempPath = null;

        if ($this->config->atomicWrites) {
            $tempPath = tempnam($directory, 'cache_');
            if ($tempPath === false) {
                return false;
            }
            $handle = fopen($tempPath, 'wb');
        } else {
            $handle = fopen($filePath, 'wb');
        }

        if ($handle === false) {
            if ($tempPath !== null) {
                @unlink($tempPath);
            }
            return false;
        }

        if ($this->config->fileLocking) {
            if (!flock($handle, LOCK_EX)) {
                fclose($handle);
                if ($tempPath !== null) {
                    @unlink($tempPath);
                }
                return false;
            }
        }

        $written = fwrite($handle, $payload);

        if ($written === false || $written !== strlen($payload)) {
            if ($this->config->fileLocking) {
                flock($handle, LOCK_UN);
            }
            fclose($handle);
            if ($tempPath !== null) {
                @unlink($tempPath);
            }
            return false;
        }

        if ($this->config->fsyncOnWrite) {
            fflush($handle);
            if (function_exists('fsync')) {
                @fsync($handle);
            }
        }

        if ($this->config->fileLocking) {
            flock($handle, LOCK_UN);
        }

        fclose($handle);

        if ($tempPath !== null) {
            if (!@rename($tempPath, $filePath)) {
                @unlink($tempPath);
                return false;
            }
        }

        @chmod($filePath, $this->config->filePermissions);

        if ($this->config->statCacheDisabled) {
            clearstatcache(true, $filePath);
        }

        return true;
    }

    /**
     * Создает директорию, если она не существует
     * 
     * @param string $directory Путь к директории
     * @throws RuntimeException Если не удается создать директорию
     */
    private function ensureDirectoryExists(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!@mkdir($directory, $this->config->directoryPermissions, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Не удается создать директорию кэша: %s', $directory));
        }
    }

    /**
     * Удаляет содержимое директории
     * 
     * @param string $directory Путь к директории
     */
    private function deleteDirectoryContents(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
    }

    /**
     * Удаляет пустые поддиректории
     * 
     * @param string $directory Путь к директории
     */
    private function removeEmptyDirectories(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeEmptyDirectories($path);

                $subItems = scandir($path);
                if ($subItems !== false && count($subItems) === 2) {
                    @rmdir($path);
                }
            }
        }
    }

    /**
     * Хеширует ключ кэша
     * 
     * @param string $value Значение для хеширования
     * @return string Хеш значения
     */
    private function hashKey(string $value): string
    {
        return match ($this->config->keyHashAlgorithm) {
            'md5' => md5($value),
            'xxh3' => in_array('xxh3', hash_algos(), true) ? hash('xxh3', $value) : hash('sha256', $value),
            default => sha1($value),
        };
    }

    /**
     * Обеспечивает соблюдение максимального размера кэша
     * 
     * @return int Количество удаленных элементов
     */
    private function enforceMaxCacheSize(): int
    {
        $limit = $this->config->maxCacheSize;
        if ($limit === null) {
            return 0;
        }

        $files = [];
        $totalSize = 0;

        $iterator = $this->createCacheIterator();
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $filename = $fileInfo->getFilename();
            if ($filename === self::TAG_INDEX_FILENAME || !str_ends_with($filename, $this->config->fileExtension)) {
                continue;
            }

            $size = $fileInfo->getSize();
            $totalSize += $size;

            $files[] = [
                'path' => $fileInfo->getPathname(),
                'size' => $size,
                'mtime' => $fileInfo->getMTime(),
            ];
        }

        if ($totalSize <= $limit) {
            return 0;
        }

        usort($files, static fn(array $a, array $b) => $a['mtime'] <=> $b['mtime']);

        $deleted = 0;
        foreach ($files as $file) {
            if ($totalSize <= $limit) {
                break;
            }

            $data = null;
            try {
                $data = $this->readFile($file['path']);
            } catch (Throwable $exception) {
                $this->handleError($exception);
            }

            if (@unlink($file['path'])) {
                $deleted++;
                $totalSize -= $file['size'];
                $this->incrementStat('deletes');

                if ($data !== null && isset($data['key'])) {
                    $key = (string) $data['key'];
                    $this->removeFromTagIndex($key, (array) ($data['tags'] ?? []));
                    unset($this->memoryCache[$key]);
                }
            }
        }

        return $deleted;
    }

    /**
     * Инициализирует индекс тегов
     * 
     * Загружает индекс тегов из файла или создает пустой индекс.
     */
    private function initializeTagIndex(): void
    {
        $path = $this->getTagIndexPath();
        if (!is_file($path)) {
            $this->tagIndex = [];
            return;
        }

        $contents = @file_get_contents($path);
        if ($contents === false || $contents === '') {
            $this->tagIndex = [];
            return;
        }

        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            $this->tagIndex = [];
            return;
        }

        foreach ($decoded as $tag => $keys) {
            if (is_array($keys)) {
                $this->tagIndex[(string) $tag] = array_values(array_unique(array_map('strval', $keys)));
            }
        }
    }

    /**
     * Сохраняет индекс тегов в файл
     * 
     * @throws RuntimeException Если не удается закодировать индекс
     */
    private function saveTagIndex(): void
    {
        $path = $this->getTagIndexPath();
        $this->ensureDirectoryExists(dirname($path));

        $json = json_encode($this->tagIndex, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Не удалось закодировать индекс тегов.');
        }

        file_put_contents($path, $json, LOCK_EX);
        @chmod($path, $this->config->filePermissions);
    }

    /**
     * Возвращает путь к файлу индекса тегов
     * 
     * @return string Путь к файлу индекса тегов
     */
    private function getTagIndexPath(): string
    {
        return $this->config->cacheDirectory . DIRECTORY_SEPARATOR . self::TAG_INDEX_FILENAME;
    }

    /**
     * Обновляет индекс тегов для ключа
     * 
     * @param string $key Ключ кэша
     * @param array $tags Теги для ключа
     */
    private function updateTagIndex(string $key, array $tags): void
    {
        $tags = array_values(array_unique(array_map('strval', $tags)));

        if ($tags === []) {
            $this->removeFromTagIndex($key);
            return;
        }

        $changed = false;

        foreach ($tags as $tag) {
            if ($tag === '') {
                continue;
            }

            $this->tagIndex[$tag] ??= [];
            if (!in_array($key, $this->tagIndex[$tag], true)) {
                $this->tagIndex[$tag][] = $key;
                $changed = true;
            }
        }

        if ($changed) {
            $this->saveTagIndex();
        }
    }

    /**
     * Удаляет ключ из индекса тегов
     * 
     * @param string $key Ключ кэша
     * @param array|null $onlyTags Удалить только из указанных тегов (null = из всех)
     */
    private function removeFromTagIndex(string $key, ?array $onlyTags = null): void
    {
        $changed = false;
        $tags = $onlyTags ?? array_keys($this->tagIndex);

        foreach ($tags as $tag) {
            $tag = (string) $tag;
            if ($tag === '' || !isset($this->tagIndex[$tag])) {
                continue;
            }

            $index = array_search($key, $this->tagIndex[$tag], true);
            if ($index !== false) {
                unset($this->tagIndex[$tag][$index]);
                $this->tagIndex[$tag] = array_values($this->tagIndex[$tag]);
                $changed = true;
            }

            if (empty($this->tagIndex[$tag])) {
                unset($this->tagIndex[$tag]);
                $changed = true;
            }
        }

        if ($changed) {
            $this->saveTagIndex();
        }
    }

    /**
     * Предзагружает все данные кэша в память
     * 
     * Загружает все актуальные элементы кэша в память при инициализации.
     */
    private function preloadCache(): void
    {
        try {
            $iterator = $this->createCacheIterator();
            foreach ($iterator as $fileInfo) {
                if (!$fileInfo->isFile()) {
                    continue;
                }

                $filename = $fileInfo->getFilename();
                if ($filename === self::TAG_INDEX_FILENAME || !str_ends_with($filename, $this->config->fileExtension)) {
                    continue;
                }

                $data = $this->readFile($fileInfo->getPathname());
                if ($data === null || $this->isExpiredData($data) || !isset($data['key'])) {
                    continue;
                }

                $this->memoryCache[(string) $data['key']] = $data;
            }
        } catch (Throwable $exception) {
            $this->handleError($exception);
        }
    }

    /**
     * Создает итератор для обхода файлов кэша
     * 
     * @return \RecursiveIteratorIterator Итератор для обхода директории кэша
     */
    private function createCacheIterator(): \RecursiveIteratorIterator
    {
        return new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->config->cacheDirectory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
    }

    /**
     * Проверяет, истекли ли данные кэша
     * 
     * @param array $data Данные элемента кэша
     * @return bool True если данные истекли, false в противном случае
     */
    private function isExpiredData(array $data): bool
    {
        if (!isset($data['expires']) || $data['expires'] === null) {
            return false;
        }

        return (int) $data['expires'] < time();
    }

    /**
     * Кодирует значение в JSON
     * 
     * @param mixed $value Значение для кодирования
     * @return string JSON-строка
     * @throws RuntimeException Если не удается закодировать значение
     */
    private function encodeJson(mixed $value): string
    {
        $encoded = json_encode($value, $this->config->jsonOptions);
        if ($encoded === false) {
            throw new RuntimeException('Не удалось закодировать значение в JSON: ' . json_last_error_msg());
        }

        return $encoded;
    }

    /**
     * Декодирует JSON-строку
     * 
     * @param string $data JSON-строка
     * @return mixed Декодированное значение
     * @throws RuntimeException Если не удается декодировать JSON
     */
    private function decodeJson(string $data): mixed
    {
        $decoded = json_decode($data, true, 512, $this->config->jsonOptions);
        if ($decoded === null && $data !== 'null' && ($this->config->jsonOptions & JSON_THROW_ON_ERROR) === 0) {
            throw new RuntimeException('Не удалось декодировать JSON: ' . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Кодирует значение с помощью igbinary
     * 
     * @param mixed $value Значение для кодирования
     * @return string Закодированная строка
     * @throws RuntimeException Если расширение igbinary недоступно
     */
    private function encodeIgbinary(mixed $value): string
    {
        if (!function_exists('igbinary_serialize')) {
            throw new RuntimeException('Для сериализации igbinary требуется расширение Igbinary.');
        }

        return igbinary_serialize($value);
    }

    /**
     * Декодирует строку igbinary
     * 
     * @param string $data Закодированная строка
     * @return mixed Декодированное значение
     * @throws RuntimeException Если расширение igbinary недоступно
     */
    private function decodeIgbinary(string $data): mixed
    {
        if (!function_exists('igbinary_unserialize')) {
            throw new RuntimeException('Для сериализации igbinary требуется расширение Igbinary.');
        }

        return igbinary_unserialize($data);
    }

    /**
     * Кодирует значение с помощью msgpack
     * 
     * @param mixed $value Значение для кодирования
     * @return string Закодированная строка
     * @throws RuntimeException Если расширение msgpack недоступно
     */
    private function encodeMsgpack(mixed $value): string
    {
        if (!function_exists('msgpack_pack')) {
            throw new RuntimeException('Для сериализации msgpack требуется расширение Msgpack.');
        }

        return msgpack_pack($value);
    }

    /**
     * Декодирует строку msgpack
     * 
     * @param string $data Закодированная строка
     * @return mixed Декодированное значение
     * @throws RuntimeException Если расширение msgpack недоступно
     */
    private function decodeMsgpack(string $data): mixed
    {
        if (!function_exists('msgpack_unpack')) {
            throw new RuntimeException('Для сериализации msgpack требуется расширение Msgpack.');
        }

        return msgpack_unpack($data);
    }

    /**
     * Возвращает полный ключ с учетом namespace и prefix
     * 
     * @param string $key Ключ кэша
     * @return string Полный ключ
     */
    private function getFullKey(string $key): string
    {
        return $this->config->namespace . $this->config->keyPrefix . $key;
    }

    /**
     * Возвращает путь к файлу блокировки для ключа
     * 
     * @param string $key Ключ кэша
     * @return string Путь к файлу блокировки
     */
    private function getLockPath(string $key): string
    {
        $hash = $this->hashKey($this->getFullKey($key));
        return $this->config->cacheDirectory . DIRECTORY_SEPARATOR . self::LOCKS_DIRECTORY . DIRECTORY_SEPARATOR . $hash . '.lock';
    }

    /**
     * Обрабатывает ошибки в соответствии с настройками
     * 
     * @param Throwable $exception Исключение для обработки
     * @throws Throwable Если errorHandling установлен в 'throw'
     */
    private function handleError(Throwable $exception): void
    {
        if ($this->config->errorHandling === 'throw') {
            throw $exception;
        }

        if ($this->config->errorHandling === 'log') {
            error_log($exception->getMessage());
        }
    }
}

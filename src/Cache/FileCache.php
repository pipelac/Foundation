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

    public function __construct(FileCacheConfig|array $config = [])
    {
        if (is_array($config)) {
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

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get((string) $key, $default);
        }
        return $result;
    }

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
                    throw new RuntimeException('Cannot increment a non-numeric cache value.');
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

    public function decrement(string $key, int $value = 1): int|false
    {
        return $this->increment($key, -$value);
    }

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
                throw new InvalidArgumentException('Item size exceeds maxItemSize constraint.');
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

    public function flush(): bool
    {
        return $this->clear();
    }

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

    public function prune(): int
    {
        return $this->gc(true);
    }

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

    public function resetStats(): void
    {
        $this->stats = [
            'hits' => 0,
            'misses' => 0,
            'writes' => 0,
            'deletes' => 0,
        ];
    }

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

    public function validateKey(string $key): bool
    {
        if ($key === '') {
            throw new InvalidArgumentException('Cache key cannot be empty.');
        }

        if (strlen($key) > 255) {
            throw new InvalidArgumentException('Cache key is too long (max 255 characters).');
        }

        if (preg_match('/[{}()\/\\@:]/', $key)) {
            throw new InvalidArgumentException('Cache key contains invalid characters.');
        }

        return true;
    }

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
                throw new RuntimeException('Cannot compress cache payload without zlib extension.');
            }

            $compressed = gzcompress($payload, $this->config->compressionLevel);
            if ($compressed === false) {
                throw new RuntimeException('Failed to compress cache payload.');
            }

            return self::COMPRESSED_PREFIX . $compressed;
        }

        return $payload;
    }

    public function unserialize(string $payload): mixed
    {
        $data = $payload;

        if (str_starts_with($data, self::COMPRESSED_PREFIX)) {
            if (!extension_loaded('zlib')) {
                throw new RuntimeException('Cannot decompress cache payload without zlib extension.');
            }

            $decompressed = gzuncompress(substr($data, strlen(self::COMPRESSED_PREFIX)));
            if ($decompressed === false) {
                throw new RuntimeException('Failed to decompress cache payload.');
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

    public function __destruct()
    {
        foreach (array_keys($this->locks) as $key) {
            $this->unlock($key);
        }
    }

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
            throw new InvalidArgumentException('Item size exceeds maxItemSize constraint.');
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

    private function calculateRemainingTtl(array $data): ?int
    {
        if (!isset($data['expires']) || $data['expires'] === null) {
            return null;
        }

        $remaining = (int) $data['expires'] - time();
        return $remaining > 0 ? $remaining : 0;
    }

    private function rememberInMemory(string $key, array $data): void
    {
        if ($this->config->preloadEnabled || array_key_exists($key, $this->memoryCache)) {
            $this->memoryCache[$key] = $data;
        }
    }

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

    private function shouldRunGc(): bool
    {
        if ($this->config->gcProbability <= 0) {
            return false;
        }

        $random = mt_rand(1, $this->config->gcDivisor);
        return $random <= $this->config->gcProbability;
    }

    private function incrementStat(string $name): void
    {
        if (!$this->config->enableStatistics) {
            return;
        }

        if (isset($this->stats[$name])) {
            $this->stats[$name]++;
        }
    }

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

    private function ensureDirectoryExists(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!@mkdir($directory, $this->config->directoryPermissions, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create cache directory: %s', $directory));
        }
    }

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

    private function hashKey(string $value): string
    {
        return match ($this->config->keyHashAlgorithm) {
            'md5' => md5($value),
            'xxh3' => in_array('xxh3', hash_algos(), true) ? hash('xxh3', $value) : hash('sha256', $value),
            default => sha1($value),
        };
    }

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

    private function saveTagIndex(): void
    {
        $path = $this->getTagIndexPath();
        $this->ensureDirectoryExists(dirname($path));

        $json = json_encode($this->tagIndex, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Failed to encode tag index.');
        }

        file_put_contents($path, $json, LOCK_EX);
        @chmod($path, $this->config->filePermissions);
    }

    private function getTagIndexPath(): string
    {
        return $this->config->cacheDirectory . DIRECTORY_SEPARATOR . self::TAG_INDEX_FILENAME;
    }

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

    private function createCacheIterator(): \RecursiveIteratorIterator
    {
        return new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->config->cacheDirectory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
    }

    private function isExpiredData(array $data): bool
    {
        if (!isset($data['expires']) || $data['expires'] === null) {
            return false;
        }

        return (int) $data['expires'] < time();
    }

    private function encodeJson(mixed $value): string
    {
        $encoded = json_encode($value, $this->config->jsonOptions);
        if ($encoded === false) {
            throw new RuntimeException('Failed to encode value to JSON: ' . json_last_error_msg());
        }

        return $encoded;
    }

    private function decodeJson(string $data): mixed
    {
        $decoded = json_decode($data, true, 512, $this->config->jsonOptions);
        if ($decoded === null && $data !== 'null' && ($this->config->jsonOptions & JSON_THROW_ON_ERROR) === 0) {
            throw new RuntimeException('Failed to decode JSON payload: ' . json_last_error_msg());
        }

        return $decoded;
    }

    private function encodeIgbinary(mixed $value): string
    {
        if (!function_exists('igbinary_serialize')) {
            throw new RuntimeException('Igbinary extension is required for igbinary serialization.');
        }

        return igbinary_serialize($value);
    }

    private function decodeIgbinary(string $data): mixed
    {
        if (!function_exists('igbinary_unserialize')) {
            throw new RuntimeException('Igbinary extension is required for igbinary serialization.');
        }

        return igbinary_unserialize($data);
    }

    private function encodeMsgpack(mixed $value): string
    {
        if (!function_exists('msgpack_pack')) {
            throw new RuntimeException('Msgpack extension is required for msgpack serialization.');
        }

        return msgpack_pack($value);
    }

    private function decodeMsgpack(string $data): mixed
    {
        if (!function_exists('msgpack_unpack')) {
            throw new RuntimeException('Msgpack extension is required for msgpack serialization.');
        }

        return msgpack_unpack($data);
    }

    private function getFullKey(string $key): string
    {
        return $this->config->namespace . $this->config->keyPrefix . $key;
    }

    private function getLockPath(string $key): string
    {
        $hash = $this->hashKey($this->getFullKey($key));
        return $this->config->cacheDirectory . DIRECTORY_SEPARATOR . self::LOCKS_DIRECTORY . DIRECTORY_SEPARATOR . $hash . '.lock';
    }

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

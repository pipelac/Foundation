<?php

declare(strict_types=1);

namespace Cache;

use InvalidArgumentException;

class FileCacheConfig
{
    public string $cacheDirectory;
    public int $directoryPermissions = 0o755;
    public int $filePermissions = 0o644;
    public string $fileExtension = '.cache';
    public bool $useSharding = false;
    public int $shardingDepth = 0;

    public ?int $defaultTtl = null;
    public ?int $maxTtl = null;
    public int $gcProbability = 1;
    public int $gcDivisor = 100;
    public bool $checkExpiredOnRead = true;

    public string $serializer = 'native';
    public int $jsonOptions = 0;
    public bool $compressionEnabled = false;
    public int $compressionLevel = 6;
    public int $compressionThreshold = 1024;

    public bool $fileLocking = true;
    public int $lockTimeout = 5;
    public int $lockRetries = 3;
    public bool $atomicWrites = true;

    public string $keyPrefix = '';
    public string $namespace = '';
    public string $keyHashAlgorithm = 'sha1';

    public bool $fsyncOnWrite = false;
    public bool $statCacheDisabled = true;
    public bool $preloadEnabled = false;
    public ?int $maxCacheSize = null;
    public ?int $maxItemSize = null;

    public bool $enableStatistics = false;
    public string $errorHandling = 'throw';

    public function __construct(array $config = [])
    {
        foreach ($config as $property => $value) {
            if (property_exists($this, $property)) {
                $this->$property = $value;
            }
        }

        if (!isset($config['cacheDirectory']) || $config['cacheDirectory'] === '') {
            $this->cacheDirectory = sys_get_temp_dir() . '/file_cache';
        }

        $this->cacheDirectory = rtrim($this->cacheDirectory, DIRECTORY_SEPARATOR);
    }

    public function validate(): void
    {
        if ($this->cacheDirectory === '') {
            throw new InvalidArgumentException('cacheDirectory cannot be empty.');
        }

        if (!$this->isAbsolutePath($this->cacheDirectory)) {
            throw new InvalidArgumentException('cacheDirectory must be an absolute path.');
        }

        if ($this->directoryPermissions < 0 || $this->directoryPermissions > 0o777) {
            throw new InvalidArgumentException('directoryPermissions must be between 0000 and 0777.');
        }

        if ($this->filePermissions < 0 || $this->filePermissions > 0o777) {
            throw new InvalidArgumentException('filePermissions must be between 0000 and 0777.');
        }

        if ($this->fileExtension === '' || $this->fileExtension[0] !== '.') {
            throw new InvalidArgumentException('fileExtension must start with a dot.');
        }

        if ($this->shardingDepth < 0) {
            throw new InvalidArgumentException('shardingDepth cannot be negative.');
        }

        if ($this->defaultTtl !== null && $this->defaultTtl < 0) {
            throw new InvalidArgumentException('defaultTtl cannot be negative.');
        }

        if ($this->maxTtl !== null && $this->maxTtl <= 0) {
            throw new InvalidArgumentException('maxTtl must be greater than zero when provided.');
        }

        if ($this->defaultTtl !== null && $this->maxTtl !== null && $this->defaultTtl > $this->maxTtl) {
            throw new InvalidArgumentException('defaultTtl cannot be greater than maxTtl.');
        }

        if ($this->gcDivisor <= 0) {
            throw new InvalidArgumentException('gcDivisor must be greater than zero.');
        }

        if ($this->gcProbability < 0 || $this->gcProbability > $this->gcDivisor) {
            throw new InvalidArgumentException('gcProbability must be between 0 and gcDivisor.');
        }

        if (!in_array($this->serializer, ['native', 'json', 'igbinary', 'msgpack'], true)) {
            throw new InvalidArgumentException('Unsupported serializer: ' . $this->serializer);
        }

        if ($this->serializer === 'igbinary' && !extension_loaded('igbinary')) {
            throw new InvalidArgumentException('Serializer "igbinary" requires the igbinary extension.');
        }

        if ($this->serializer === 'msgpack' && !extension_loaded('msgpack')) {
            throw new InvalidArgumentException('Serializer "msgpack" requires the msgpack extension.');
        }

        if ($this->compressionEnabled) {
            if (!extension_loaded('zlib')) {
                throw new InvalidArgumentException('Compression requires the zlib extension.');
            }

            if ($this->compressionLevel < 1 || $this->compressionLevel > 9) {
                throw new InvalidArgumentException('compressionLevel must be between 1 and 9.');
            }

            if ($this->compressionThreshold < 0) {
                throw new InvalidArgumentException('compressionThreshold cannot be negative.');
            }
        }

        if ($this->lockTimeout < 0) {
            throw new InvalidArgumentException('lockTimeout cannot be negative.');
        }

        if ($this->lockRetries < 0) {
            throw new InvalidArgumentException('lockRetries cannot be negative.');
        }

        if (!in_array($this->keyHashAlgorithm, ['sha1', 'md5', 'xxh3'], true)) {
            throw new InvalidArgumentException('Unsupported keyHashAlgorithm: ' . $this->keyHashAlgorithm);
        }

        if ($this->maxCacheSize !== null && $this->maxCacheSize <= 0) {
            throw new InvalidArgumentException('maxCacheSize must be greater than zero.');
        }

        if ($this->maxItemSize !== null && $this->maxItemSize <= 0) {
            throw new InvalidArgumentException('maxItemSize must be greater than zero.');
        }

        if (!in_array($this->errorHandling, ['throw', 'log', 'silent'], true)) {
            throw new InvalidArgumentException('Unsupported errorHandling strategy: ' . $this->errorHandling);
        }
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return true;
        }

        return preg_match('/^[A-Za-z]:[\\\\/]/', $path) === 1;
    }
}

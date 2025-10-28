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
            throw new InvalidArgumentException('Директория кэша не может быть пустой.');
        }

        if (!$this->isAbsolutePath($this->cacheDirectory)) {
            throw new InvalidArgumentException('Директория кэша должна быть абсолютным путем.');
        }

        if ($this->directoryPermissions < 0 || $this->directoryPermissions > 0o777) {
            throw new InvalidArgumentException('Права доступа директории должны быть между 0000 и 0777.');
        }

        if ($this->filePermissions < 0 || $this->filePermissions > 0o777) {
            throw new InvalidArgumentException('Права доступа файла должны быть между 0000 и 0777.');
        }

        if ($this->fileExtension === '' || $this->fileExtension[0] !== '.') {
            throw new InvalidArgumentException('Расширение файла должно начинаться с точки.');
        }

        if ($this->shardingDepth < 0) {
            throw new InvalidArgumentException('Глубина шардирования не может быть отрицательной.');
        }

        if ($this->defaultTtl !== null && $this->defaultTtl < 0) {
            throw new InvalidArgumentException('Время жизни по умолчанию не может быть отрицательным.');
        }

        if ($this->maxTtl !== null && $this->maxTtl <= 0) {
            throw new InvalidArgumentException('Максимальное время жизни должно быть больше нуля.');
        }

        if ($this->defaultTtl !== null && $this->maxTtl !== null && $this->defaultTtl > $this->maxTtl) {
            throw new InvalidArgumentException('Время жизни по умолчанию не может быть больше максимального времени жизни.');
        }

        if ($this->gcDivisor <= 0) {
            throw new InvalidArgumentException('Делитель сборщика мусора должен быть больше нуля.');
        }

        if ($this->gcProbability < 0 || $this->gcProbability > $this->gcDivisor) {
            throw new InvalidArgumentException('Вероятность сборщика мусора должна быть между 0 и делителем.');
        }

        if (!in_array($this->serializer, ['native', 'json', 'igbinary', 'msgpack'], true)) {
            throw new InvalidArgumentException('Неподдерживаемый сериализатор: ' . $this->serializer);
        }

        if ($this->serializer === 'igbinary' && !extension_loaded('igbinary')) {
            throw new InvalidArgumentException('Сериализатор "igbinary" требует расширения igbinary.');
        }

        if ($this->serializer === 'msgpack' && !extension_loaded('msgpack')) {
            throw new InvalidArgumentException('Сериализатор "msgpack" требует расширения msgpack.');
        }

        if ($this->compressionEnabled) {
            if (!extension_loaded('zlib')) {
                throw new InvalidArgumentException('Сжатие требует расширения zlib.');
            }

            if ($this->compressionLevel < 1 || $this->compressionLevel > 9) {
                throw new InvalidArgumentException('Уровень сжатия должен быть между 1 и 9.');
            }

            if ($this->compressionThreshold < 0) {
                throw new InvalidArgumentException('Порог сжатия не может быть отрицательным.');
            }
        }

        if ($this->lockTimeout < 0) {
            throw new InvalidArgumentException('Время ожидания блокировки не может быть отрицательным.');
        }

        if ($this->lockRetries < 0) {
            throw new InvalidArgumentException('Количество попыток блокировки не может быть отрицательным.');
        }

        if (!in_array($this->keyHashAlgorithm, ['sha1', 'md5', 'xxh3'], true)) {
            throw new InvalidArgumentException('Неподдерживаемый алгоритм хеширования ключей: ' . $this->keyHashAlgorithm);
        }

        if ($this->maxCacheSize !== null && $this->maxCacheSize <= 0) {
            throw new InvalidArgumentException('Максимальный размер кэша должен быть больше нуля.');
        }

        if ($this->maxItemSize !== null && $this->maxItemSize <= 0) {
            throw new InvalidArgumentException('Максимальный размер элемента должен быть больше нуля.');
        }

        if (!in_array($this->errorHandling, ['throw', 'log', 'silent'], true)) {
            throw new InvalidArgumentException('Неподдерживаемая стратегия обработки ошибок: ' . $this->errorHandling);
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

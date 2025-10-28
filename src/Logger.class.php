<?php

declare(strict_types=1);

namespace App\Component;

use App\Component\Exception\LoggerException;
use App\Component\Exception\LoggerValidationException;
use DateTimeImmutable;

/**
 * Класс структурированного логирования с поддержкой ротации файлов,
 * кеширования настроек в памяти и оптимизированной работы с файловой системой
 */
class Logger
{
    private const DEFAULT_PATTERN = '{timestamp} {level} {message} {context}';
    private const DEFAULT_DATE_FORMAT = DateTimeImmutable::ATOM;
    
    /**
     * Допустимые уровни логирования
     */
    private const ALLOWED_LEVELS = ['DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL'];
    
    /**
     * Статический кеш конфигураций по ключу директории
     * @var array<string, array<string, mixed>>
     */
    private static array $configCache = [];
    
    /**
     * Статический кеш метаданных файлов для оптимизации
     * @var array<string, array{size: int, exists: bool, lastCheck: float}>
     */
    private static array $fileMetadataCache = [];
    
    /**
     * Время жизни кеша метаданных в секундах
     */
    private const METADATA_CACHE_TTL = 1.0;

    private string $directory;
    private string $fileName;
    private int $maxFiles;
    private int $maxFileSize;
    private string $pattern;
    private string $dateFormat;
    private int $logBufferSizeBytes;
    private string $logBuffer = '';
    private int $currentBufferSize = 0;
    private bool $enabled = true;
    private string $cacheKey;

    /**
     * Конструктор с поддержкой кеширования конфигурации в памяти
     * 
     * @param array<string, mixed> $config Параметры логгера
     * @throws LoggerValidationException Если конфигурация некорректна
     */
    public function __construct(array $config)
    {
        $this->directory = rtrim((string)($config['directory'] ?? ''), DIRECTORY_SEPARATOR);
        $this->cacheKey = $this->directory;
        
        // Проверка кеша конфигураций
        if (isset(self::$configCache[$this->cacheKey])) {
            $this->loadFromCache(self::$configCache[$this->cacheKey]);
            $this->validateDirectoryAccess();
            return;
        }
        
        // Инициализация новой конфигурации
        $this->initializeConfiguration($config);
        
        // Сохранение конфигурации в кеш
        self::$configCache[$this->cacheKey] = [
            'file_name' => $this->fileName,
            'max_files' => $this->maxFiles,
            'max_file_size' => $this->maxFileSize,
            'pattern' => $this->pattern,
            'date_format' => $this->dateFormat,
            'log_buffer_size_bytes' => $this->logBufferSizeBytes,
            'enabled' => $this->enabled,
        ];
    }
    
    /**
     * Инициализирует конфигурацию логгера с валидацией
     * 
     * @param array<string, mixed> $config Параметры конфигурации
     * @throws LoggerValidationException Если конфигурация некорректна
     */
    private function initializeConfiguration(array $config): void
    {
        $this->fileName = (string)($config['file_name'] ?? 'app.log');
        $this->maxFiles = max(1, (int)($config['max_files'] ?? 5));
        
        $maxFileSizeMb = (int)($config['max_file_size'] ?? 1);
        $this->maxFileSize = max(1, $maxFileSizeMb) * 1024 * 1024;
        
        $this->pattern = (string)($config['pattern'] ?? self::DEFAULT_PATTERN);
        $this->dateFormat = (string)($config['date_format'] ?? self::DEFAULT_DATE_FORMAT);
        
        $logBufferSizeKb = max(0, (int)($config['log_buffer_size'] ?? 0));
        $this->logBufferSizeBytes = $logBufferSizeKb * 1024;
        
        $this->enabled = (bool)($config['enabled'] ?? true);
        
        $this->validateConfiguration();
        $this->validateDirectoryAccess();
    }
    
    /**
     * Загружает конфигурацию из кеша
     * 
     * @param array<string, mixed> $cachedConfig Закешированная конфигурация
     */
    private function loadFromCache(array $cachedConfig): void
    {
        $this->fileName = $cachedConfig['file_name'];
        $this->maxFiles = $cachedConfig['max_files'];
        $this->maxFileSize = $cachedConfig['max_file_size'];
        $this->pattern = $cachedConfig['pattern'];
        $this->dateFormat = $cachedConfig['date_format'];
        $this->logBufferSizeBytes = $cachedConfig['log_buffer_size_bytes'];
        $this->enabled = $cachedConfig['enabled'];
    }
    
    /**
     * Валидирует конфигурацию логгера
     * 
     * @throws LoggerValidationException Если конфигурация некорректна
     */
    private function validateConfiguration(): void
    {
        if ($this->directory === '') {
            throw new LoggerValidationException('Не указана директория для логов.');
        }
        
        if ($this->fileName === '') {
            throw new LoggerValidationException('Не указано имя файла лога.');
        }
        
        if ($this->maxFiles < 1) {
            throw new LoggerValidationException('Количество файлов должно быть не меньше 1.');
        }
        
        if ($this->maxFileSize < 1024) {
            throw new LoggerValidationException('Размер файла должен быть не меньше 1 КБ.');
        }
    }
    
    /**
     * Валидирует доступ к директории логов
     * 
     * @throws LoggerValidationException Если директория недоступна
     */
    private function validateDirectoryAccess(): void
    {
        if (!is_dir($this->directory)) {
            if (!mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
                throw new LoggerValidationException('Не удалось создать директорию для логов: ' . $this->directory);
            }
        }

        if (!is_writable($this->directory)) {
            throw new LoggerValidationException('Недостаточно прав на запись в директорию: ' . $this->directory);
        }
    }

    /**
     * Деструктор для автоматического сброса буфера при завершении работы
     */
    public function __destruct()
    {
        try {
            $this->flushBuffer();
        } catch (Exception $e) {
            // Подавляем исключения в деструкторе для предотвращения фатальных ошибок
            error_log('Ошибка при сбросе буфера логгера: ' . $e->getMessage());
        }
    }
    
    /**
     * Включает логирование
     */
    public function enable(): void
    {
        $this->enabled = true;
    }
    
    /**
     * Отключает логирование
     */
    public function disable(): void
    {
        $this->enabled = false;
    }
    
    /**
     * Проверяет, включено ли логирование
     * 
     * @return bool Возвращает true если логирование включено
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Записывает информационное сообщение в лог
     *
     * @param string $message Текст сообщения
     * @param array<string, mixed> $context Дополнительные данные
     * @throws LoggerException Если не удалось записать лог
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    /**
     * Записывает предупреждение в лог
     *
     * @param string $message Текст сообщения
     * @param array<string, mixed> $context Дополнительные данные
     * @throws LoggerException Если не удалось записать лог
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    /**
     * Записывает сообщение об ошибке в лог
     *
     * @param string $message Текст сообщения
     * @param array<string, mixed> $context Дополнительные данные
     * @throws LoggerException Если не удалось записать лог
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    /**
     * Записывает отладочное сообщение в лог
     *
     * @param string $message Текст сообщения
     * @param array<string, mixed> $context Дополнительные данные
     * @throws LoggerException Если не удалось записать лог
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }
    
    /**
     * Записывает критическое сообщение в лог
     *
     * @param string $message Текст сообщения
     * @param array<string, mixed> $context Дополнительные данные
     * @throws LoggerException Если не удалось записать лог
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log('CRITICAL', $message, $context);
    }

    /**
     * Базовый метод логирования с форматированием и валидацией
     *
     * @param string $level Уровень логирования
     * @param string $message Текст сообщения
     * @param array<string, mixed> $context Дополнительные данные
     * @throws LoggerException Если не удалось записать лог
     */
    public function log(string $level, string $message, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $normalizedLevel = strtoupper(trim($level));
        
        if (!in_array($normalizedLevel, self::ALLOWED_LEVELS, true)) {
            throw new LoggerException('Недопустимый уровень логирования: ' . $level);
        }
        
        $record = $this->formatRecord($normalizedLevel, $message, $context) . PHP_EOL;
        $this->writeLog($record);
    }
    
    /**
     * Принудительно сбрасывает буфер логов в файл (публичный метод)
     * 
     * @throws LoggerException Если не удалось записать лог
     */
    public function flush(): void
    {
        $this->flushBuffer();
    }

    /**
     * Возвращает путь к текущему лог-файлу
     * 
     * @param int $index Индекс файла для ротации
     * @return string Полный путь к файлу
     */
    private function getLogFilePath(int $index = 0): string
    {
        $suffix = $index === 0 ? '' : '.' . $index;
        return $this->directory . DIRECTORY_SEPARATOR . $this->fileName . $suffix;
    }

    /**
     * Формирует строку лога на основе шаблона с обработкой ошибок
     *
     * @param string $level Уровень логирования (уже нормализованный)
     * @param string $message Текст сообщения
     * @param array<string, mixed> $context Дополнительные данные
     * @return string Отформатированная строка лога
     */
    private function formatRecord(string $level, string $message, array $context): string
    {
        $timestamp = (new DateTimeImmutable())->format($this->dateFormat);

        try {
            $normalizedContext = $context === []
                ? '{}'
                : json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            $normalizedContext = json_encode([
                'error' => 'Невозможно сериализовать контекст',
                'reason' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }

        $replacements = [
            '{timestamp}' => $timestamp,
            '{level}' => $level,
            '{message}' => $message,
            '{context}' => (string)$normalizedContext,
        ];

        return strtr($this->pattern, $replacements);
    }

    /**
     * Выполняет запись сообщения в файл с обработкой ошибок
     *
     * @param string $record Готовая строка лога
     * @throws LoggerException Если не удалось записать в файл
     */
    private function writeLog(string $record): void
    {
        $recordSize = strlen($record);
        
        if ($this->logBufferSizeBytes <= 0) {
            $this->rotateIfNeeded($recordSize);
            $this->writeToFile($record);
            return;
        }

        $this->logBuffer .= $record;
        $this->currentBufferSize += $recordSize;

        if ($this->currentBufferSize >= $this->logBufferSizeBytes) {
            $this->flushBuffer();
        }
    }
    
    /**
     * Выполняет физическую запись данных в файл с обработкой ошибок
     * 
     * @param string $content Содержимое для записи
     * @throws LoggerException Если не удалось записать в файл
     */
    private function writeToFile(string $content): void
    {
        $filePath = $this->getLogFilePath();
        $result = @file_put_contents($filePath, $content, FILE_APPEND | LOCK_EX);
        
        if ($result === false) {
            $error = error_get_last();
            $errorMessage = $error !== null ? $error['message'] : 'Неизвестная ошибка';
            throw new LoggerException('Не удалось записать в лог-файл: ' . $errorMessage);
        }
        
        $this->invalidateFileCache($filePath);
    }

    /**
     * Выполняет ротацию файлов лога при достижении лимита размера с кешированием
     * 
     * @param int $incomingRecordSize Размер входящей записи в байтах
     */
    private function rotateIfNeeded(int $incomingRecordSize): void
    {
        $logFile = $this->getLogFilePath();
        $metadata = $this->getFileMetadata($logFile);

        if (!$metadata['exists']) {
            return;
        }

        if ($metadata['size'] + $incomingRecordSize <= $this->maxFileSize) {
            return;
        }

        $this->rotateFiles();
    }
    
    /**
     * Получает метаданные файла с кешированием для оптимизации
     * 
     * @param string $filePath Путь к файлу
     * @return array{size: int, exists: bool, lastCheck: float} Метаданные файла
     */
    private function getFileMetadata(string $filePath): array
    {
        $now = microtime(true);
        
        if (isset(self::$fileMetadataCache[$filePath])) {
            $cached = self::$fileMetadataCache[$filePath];
            
            if (($now - $cached['lastCheck']) < self::METADATA_CACHE_TTL) {
                return $cached;
            }
        }
        
        clearstatcache(true, $filePath);
        
        $exists = file_exists($filePath);
        $size = $exists ? (@filesize($filePath) ?: 0) : 0;
        
        $metadata = [
            'size' => $size,
            'exists' => $exists,
            'lastCheck' => $now,
        ];
        
        self::$fileMetadataCache[$filePath] = $metadata;
        
        return $metadata;
    }
    
    /**
     * Инвалидирует кеш метаданных для файла
     * 
     * @param string $filePath Путь к файлу
     */
    private function invalidateFileCache(string $filePath): void
    {
        unset(self::$fileMetadataCache[$filePath]);
        clearstatcache(true, $filePath);
    }

    /**
     * Производит ротацию файлов лога с учётом ограничения по количеству
     * и обработкой ошибок файловых операций
     * 
     * @throws LoggerException Если не удалось выполнить ротацию
     */
    private function rotateFiles(): void
    {
        $lastIndex = $this->maxFiles - 1;

        if ($lastIndex <= 0) {
            $filePath = $this->getLogFilePath();
            if (file_exists($filePath) && !@unlink($filePath)) {
                throw new LoggerException('Не удалось удалить файл при ротации: ' . $filePath);
            }
            $this->invalidateFileCache($filePath);
            return;
        }

        $lastFile = $this->getLogFilePath($lastIndex);
        if (file_exists($lastFile)) {
            if (!@unlink($lastFile)) {
                throw new LoggerException('Не удалось удалить старый файл при ротации: ' . $lastFile);
            }
            $this->invalidateFileCache($lastFile);
        }

        for ($index = $lastIndex - 1; $index >= 0; $index--) {
            $source = $this->getLogFilePath($index);
            if (!file_exists($source)) {
                continue;
            }

            $target = $this->getLogFilePath($index + 1);
            
            if (!@rename($source, $target)) {
                throw new LoggerException("Не удалось переместить файл: {$source} -> {$target}");
            }
            
            $this->invalidateFileCache($source);
            $this->invalidateFileCache($target);
        }
    }

    /**
     * Сбрасывает содержимое буфера в лог-файл с обработкой ошибок
     * 
     * @throws LoggerException Если не удалось записать в файл
     */
    private function flushBuffer(): void
    {
        if ($this->logBuffer === '') {
            return;
        }

        $this->rotateIfNeeded($this->currentBufferSize);
        $this->writeToFile($this->logBuffer);

        $this->logBuffer = '';
        $this->currentBufferSize = 0;
    }
    
    /**
     * Очищает все статические кеши (конфигурации и метаданных)
     * Полезно для тестирования или при изменении файловой системы извне
     */
    public static function clearAllCaches(): void
    {
        self::$configCache = [];
        self::$fileMetadataCache = [];
        clearstatcache();
    }
    
    /**
     * Очищает кеш метаданных для конкретной директории
     * 
     * @param string $directory Директория для очистки кеша
     */
    public static function clearCacheForDirectory(string $directory): void
    {
        $normalizedDir = rtrim($directory, DIRECTORY_SEPARATOR);
        unset(self::$configCache[$normalizedDir]);
        
        foreach (array_keys(self::$fileMetadataCache) as $filePath) {
            if (str_starts_with($filePath, $normalizedDir)) {
                unset(self::$fileMetadataCache[$filePath]);
            }
        }
        
        clearstatcache();
    }
}

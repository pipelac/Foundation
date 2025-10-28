<?php

declare(strict_types=1);

namespace App\Component;

use DateTimeImmutable;
use Exception;
use RuntimeException;

/**
 * Класс структурированного логирования с поддержкой ротации файлов,
 * кеширования настроек в памяти и оптимизированной работы с файловой системой
 * 
 * Поддерживает отправку email уведомлений администратору при критических ошибках
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
     * Email адрес(а) администратора для уведомлений
     * @var array<int, string>
     */
    private array $adminEmails = [];
    
    /**
     * Конфигурация для Email класса
     * @var array<string, mixed>|null
     */
    private ?array $emailConfig = null;
    
    /**
     * Уровни логирования, при которых отправлять email уведомления
     * @var array<int, string>
     */
    private array $emailOnLevels = ['CRITICAL'];
    
    /**
     * Инстанс Email класса для отправки уведомлений
     */
    private ?Email $emailInstance = null;

    /**
     * Конструктор с поддержкой кеширования конфигурации в памяти
     * 
     * @param array<string, mixed> $config Параметры логгера
     * @throws Exception Если конфигурация некорректна
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
            'admin_emails' => $this->adminEmails,
            'email_config' => $this->emailConfig,
            'email_on_levels' => $this->emailOnLevels,
        ];
    }
    
    /**
     * Инициализирует конфигурацию логгера с валидацией
     * 
     * @param array<string, mixed> $config Параметры конфигурации
     * @throws Exception Если конфигурация некорректна
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
        
        $this->initializeEmailConfiguration($config);
        
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
        $this->adminEmails = $cachedConfig['admin_emails'] ?? [];
        $this->emailConfig = $cachedConfig['email_config'] ?? null;
        $this->emailOnLevels = $cachedConfig['email_on_levels'] ?? ['CRITICAL'];
    }
    
    /**
     * Валидирует конфигурацию логгера
     * 
     * @throws Exception Если конфигурация некорректна
     */
    private function validateConfiguration(): void
    {
        if ($this->directory === '') {
            throw new Exception('Не указана директория для логов.');
        }
        
        if ($this->fileName === '') {
            throw new Exception('Не указано имя файла лога.');
        }
        
        if ($this->maxFiles < 1) {
            throw new Exception('Количество файлов должно быть не меньше 1.');
        }
        
        if ($this->maxFileSize < 1024) {
            throw new Exception('Размер файла должен быть не меньше 1 КБ.');
        }
    }
    
    /**
     * Валидирует доступ к директории логов
     * 
     * @throws Exception Если директория недоступна
     */
    private function validateDirectoryAccess(): void
    {
        if (!is_dir($this->directory)) {
            if (!mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
                throw new Exception('Не удалось создать директорию для логов: ' . $this->directory);
            }
        }

        if (!is_writable($this->directory)) {
            throw new Exception('Недостаточно прав на запись в директорию: ' . $this->directory);
        }
    }
    
    /**
     * Инициализирует конфигурацию email уведомлений
     * 
     * @param array<string, mixed> $config Параметры конфигурации
     * @throws Exception Если конфигурация некорректна
     */
    private function initializeEmailConfiguration(array $config): void
    {
        if (!isset($config['admin_email']) && !isset($config['email_config'])) {
            return;
        }
        
        if (isset($config['admin_email'])) {
            $adminEmail = $config['admin_email'];
            
            if (is_string($adminEmail)) {
                $this->adminEmails = $this->validateAndNormalizeEmails([$adminEmail]);
            } elseif (is_array($adminEmail)) {
                $this->adminEmails = $this->validateAndNormalizeEmails($adminEmail);
            } else {
                throw new Exception('Параметр admin_email должен быть строкой или массивом email адресов.');
            }
        }
        
        if (isset($config['email_config'])) {
            if (!is_array($config['email_config'])) {
                throw new Exception('Параметр email_config должен быть массивом.');
            }
            $this->emailConfig = $config['email_config'];
        }
        
        if (isset($config['email_on_levels'])) {
            if (!is_array($config['email_on_levels'])) {
                throw new Exception('Параметр email_on_levels должен быть массивом.');
            }
            
            $normalizedLevels = [];
            foreach ($config['email_on_levels'] as $level) {
                $normalizedLevel = strtoupper(trim((string)$level));
                if (!in_array($normalizedLevel, self::ALLOWED_LEVELS, true)) {
                    throw new Exception("Недопустимый уровень логирования в email_on_levels: {$level}");
                }
                $normalizedLevels[] = $normalizedLevel;
            }
            
            $this->emailOnLevels = array_values(array_unique($normalizedLevels));
        }
    }
    
    /**
     * Валидирует и нормализует email адреса
     * 
     * @param array<int|string, mixed> $emails Массив email адресов
     * @return array<int, string> Массив валидных email адресов
     * @throws Exception Если найден невалидный email адрес
     */
    private function validateAndNormalizeEmails(array $emails): array
    {
        $validEmails = [];
        
        foreach ($emails as $email) {
            $emailStr = trim((string)$email);
            
            if ($emailStr === '') {
                continue;
            }
            
            if (!filter_var($emailStr, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Некорректный email адрес администратора: {$emailStr}");
            }
            
            $validEmails[] = $emailStr;
        }
        
        return array_values(array_unique($validEmails));
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
     * @throws RuntimeException Если не удалось записать лог
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
     * @throws RuntimeException Если не удалось записать лог
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
     * @throws RuntimeException Если не удалось записать лог
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
     * @throws RuntimeException Если не удалось записать лог
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
     * @throws RuntimeException Если не удалось записать лог
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
     * @throws RuntimeException Если не удалось записать лог
     */
    public function log(string $level, string $message, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $normalizedLevel = strtoupper(trim($level));
        
        if (!in_array($normalizedLevel, self::ALLOWED_LEVELS, true)) {
            throw new RuntimeException('Недопустимый уровень логирования: ' . $level);
        }
        
        $record = $this->formatRecord($normalizedLevel, $message, $context) . PHP_EOL;
        $this->writeLog($record);
        
        if ($this->shouldSendEmailNotification($normalizedLevel)) {
            $this->sendEmailNotification($normalizedLevel, $message, $context);
        }
    }
    
    /**
     * Принудительно сбрасывает буфер логов в файл (публичный метод)
     * 
     * @throws RuntimeException Если не удалось записать лог
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
     * @throws RuntimeException Если не удалось записать в файл
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
     * @throws RuntimeException Если не удалось записать в файл
     */
    private function writeToFile(string $content): void
    {
        $filePath = $this->getLogFilePath();
        $result = @file_put_contents($filePath, $content, FILE_APPEND | LOCK_EX);
        
        if ($result === false) {
            $error = error_get_last();
            $errorMessage = $error !== null ? $error['message'] : 'Неизвестная ошибка';
            throw new RuntimeException('Не удалось записать в лог-файл: ' . $errorMessage);
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
     * @throws RuntimeException Если не удалось выполнить ротацию
     */
    private function rotateFiles(): void
    {
        $lastIndex = $this->maxFiles - 1;

        if ($lastIndex <= 0) {
            $filePath = $this->getLogFilePath();
            if (file_exists($filePath) && !@unlink($filePath)) {
                throw new RuntimeException('Не удалось удалить файл при ротации: ' . $filePath);
            }
            $this->invalidateFileCache($filePath);
            return;
        }

        $lastFile = $this->getLogFilePath($lastIndex);
        if (file_exists($lastFile)) {
            if (!@unlink($lastFile)) {
                throw new RuntimeException('Не удалось удалить старый файл при ротации: ' . $lastFile);
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
                throw new RuntimeException("Не удалось переместить файл: {$source} -> {$target}");
            }
            
            $this->invalidateFileCache($source);
            $this->invalidateFileCache($target);
        }
    }

    /**
     * Сбрасывает содержимое буфера в лог-файл с обработкой ошибок
     * 
     * @throws RuntimeException Если не удалось записать в файл
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
    
    /**
     * Проверяет, нужно ли отправлять email уведомление для данного уровня логирования
     * 
     * @param string $level Нормализованный уровень логирования
     * @return bool Возвращает true если нужно отправить уведомление
     */
    private function shouldSendEmailNotification(string $level): bool
    {
        if ($this->adminEmails === [] || $this->emailConfig === null) {
            return false;
        }
        
        return in_array($level, $this->emailOnLevels, true);
    }
    
    /**
     * Отправляет email уведомление администратору о событии логирования
     * 
     * @param string $level Уровень логирования
     * @param string $message Текст сообщения
     * @param array<string, mixed> $context Дополнительные данные
     */
    private function sendEmailNotification(string $level, string $message, array $context): void
    {
        try {
            if ($this->emailInstance === null) {
                $this->emailInstance = new Email($this->emailConfig);
            }
            
            $timestamp = (new DateTimeImmutable())->format($this->dateFormat);
            $subject = "[{$level}] Уведомление от системы логирования";
            
            $body = $this->buildEmailBody($level, $message, $context, $timestamp);
            
            $this->emailInstance->send(
                $this->adminEmails,
                $subject,
                $body,
                ['is_html' => true]
            );
            
        } catch (Exception $e) {
            error_log(
                'Не удалось отправить email уведомление администратору: ' . 
                $e->getMessage() . 
                ' (исходное сообщение: ' . $message . ')'
            );
        }
    }
    
    /**
     * Формирует тело email уведомления в HTML формате
     * 
     * @param string $level Уровень логирования
     * @param string $message Текст сообщения
     * @param array<string, mixed> $context Дополнительные данные
     * @param string $timestamp Временная метка
     * @return string HTML содержимое письма
     */
    private function buildEmailBody(string $level, string $message, array $context, string $timestamp): string
    {
        $levelColors = [
            'DEBUG' => '#6c757d',
            'INFO' => '#0dcaf0',
            'WARNING' => '#ffc107',
            'ERROR' => '#dc3545',
            'CRITICAL' => '#8b0000',
        ];
        
        $color = $levelColors[$level] ?? '#333333';
        
        $contextHtml = '';
        if ($context !== []) {
            try {
                $contextJson = json_encode(
                    $context,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
                );
            } catch (Exception $e) {
                $contextJson = json_encode(['error' => 'Невозможно сериализовать контекст']);
            }
            
            $contextHtml = '
                <tr>
                    <td style="padding: 12px; border: 1px solid #dee2e6; background-color: #f8f9fa; font-weight: bold; width: 150px;">Контекст:</td>
                    <td style="padding: 12px; border: 1px solid #dee2e6;"><pre style="margin: 0; white-space: pre-wrap; word-wrap: break-word; font-family: monospace; font-size: 12px;">' . htmlspecialchars((string)$contextJson, ENT_QUOTES, 'UTF-8') . '</pre></td>
                </tr>';
        }
        
        $hostname = gethostname() ?: 'unknown';
        
        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Уведомление от системы логирования</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f4f4f4;">
    <div style="max-width: 800px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="background-color: ' . htmlspecialchars($color, ENT_QUOTES, 'UTF-8') . '; color: #ffffff; padding: 20px; text-align: center;">
            <h1 style="margin: 0; font-size: 24px;">⚠️ Уведомление от системы логирования</h1>
            <p style="margin: 10px 0 0 0; font-size: 18px; font-weight: bold;">' . htmlspecialchars($level, ENT_QUOTES, 'UTF-8') . '</p>
        </div>
        
        <div style="padding: 20px;">
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                <tr>
                    <td style="padding: 12px; border: 1px solid #dee2e6; background-color: #f8f9fa; font-weight: bold; width: 150px;">Время события:</td>
                    <td style="padding: 12px; border: 1px solid #dee2e6;">' . htmlspecialchars($timestamp, ENT_QUOTES, 'UTF-8') . '</td>
                </tr>
                <tr>
                    <td style="padding: 12px; border: 1px solid #dee2e6; background-color: #f8f9fa; font-weight: bold;">Уровень:</td>
                    <td style="padding: 12px; border: 1px solid #dee2e6;"><span style="background-color: ' . htmlspecialchars($color, ENT_QUOTES, 'UTF-8') . '; color: #ffffff; padding: 4px 12px; border-radius: 4px; font-weight: bold;">' . htmlspecialchars($level, ENT_QUOTES, 'UTF-8') . '</span></td>
                </tr>
                <tr>
                    <td style="padding: 12px; border: 1px solid #dee2e6; background-color: #f8f9fa; font-weight: bold;">Сообщение:</td>
                    <td style="padding: 12px; border: 1px solid #dee2e6; word-wrap: break-word;">' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</td>
                </tr>' . $contextHtml . '
                <tr>
                    <td style="padding: 12px; border: 1px solid #dee2e6; background-color: #f8f9fa; font-weight: bold;">Сервер:</td>
                    <td style="padding: 12px; border: 1px solid #dee2e6;">' . htmlspecialchars($hostname, ENT_QUOTES, 'UTF-8') . '</td>
                </tr>
                <tr>
                    <td style="padding: 12px; border: 1px solid #dee2e6; background-color: #f8f9fa; font-weight: bold;">Директория логов:</td>
                    <td style="padding: 12px; border: 1px solid #dee2e6;">' . htmlspecialchars($this->directory, ENT_QUOTES, 'UTF-8') . '</td>
                </tr>
            </table>
            
            <div style="background-color: #f8f9fa; padding: 15px; border-left: 4px solid ' . htmlspecialchars($color, ENT_QUOTES, 'UTF-8') . '; margin-top: 20px;">
                <p style="margin: 0; font-size: 14px; color: #666;">
                    <strong>Примечание:</strong> Это автоматическое уведомление от системы логирования. 
                    Подробности события записаны в лог-файл: <code>' . htmlspecialchars($this->fileName, ENT_QUOTES, 'UTF-8') . '</code>
                </p>
            </div>
        </div>
        
        <div style="background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #dee2e6;">
            <p style="margin: 0;">Система логирования | ' . htmlspecialchars($hostname, ENT_QUOTES, 'UTF-8') . '</p>
        </div>
    </div>
</body>
</html>';
    }
}

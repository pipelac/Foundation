<?php

declare(strict_types=1);

namespace App\Component;

use DateTimeImmutable;
use Exception;

/**
 * Класс структурированного логирования с поддержкой ротации файлов
 */
class Logger
{
    private const DEFAULT_PATTERN = '{timestamp} {level} {message} {context}';
    private const DEFAULT_DATE_FORMAT = DateTimeImmutable::ATOM;

    private string $directory;
    private string $fileName;
    private int $maxFiles;
    private int $maxFileSize;
    private string $pattern;
    private string $dateFormat;
    private int $logBufferSizeBytes;
    private string $logBuffer = '';
    private int $currentBufferSize = 0;

    /**
     * @param array<string, mixed> $config Параметры логгера
     * @throws Exception Если конфигурация некорректна
     */
    public function __construct(array $config)
    {
        $this->directory = rtrim((string)($config['directory'] ?? ''), DIRECTORY_SEPARATOR);
        $this->fileName = (string)($config['file_name'] ?? 'app.log');
        $this->maxFiles = max(1, (int)($config['max_files'] ?? 5));
        $maxFileSizeMb = (int)($config['max_file_size'] ?? 1);
        $this->maxFileSize = max(1, $maxFileSizeMb) * 1024 * 1024;
        $this->pattern = (string)($config['pattern'] ?? self::DEFAULT_PATTERN);
        $this->dateFormat = (string)($config['date_format'] ?? self::DEFAULT_DATE_FORMAT);
        $logBufferSizeKb = max(0, (int)($config['log_buffer_size'] ?? 0));
        $this->logBufferSizeBytes = $logBufferSizeKb * 1024;

        if ($this->directory === '') {
            throw new Exception('Не указана директория для логов.');
        }

        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new Exception('Не удалось создать директорию для логов: ' . $this->directory);
        }

        if (!is_writable($this->directory)) {
            throw new Exception('Недостаточно прав на запись в директорию: ' . $this->directory);
        }
    }

    /**
     * Деструктор для автоматического сброса буфера при завершении работы
     */
    public function __destruct()
    {
        $this->flushBuffer();
    }

    /**
     * Записывает информационное сообщение в лог
     *
     * @param string $message Текст сообщения
     * @param array<string, mixed> $context Дополнительные данные
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
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    /**
     * Базовый метод логирования с форматированием
     *
     * @param string $level Уровень логирования
     * @param string $message Текст сообщения
     * @param array<string, mixed> $context Дополнительные данные
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $record = $this->formatRecord($level, $message, $context) . PHP_EOL;
        $this->writeLog($record);
    }

    /**
     * Возвращает путь к текущему лог-файлу
     */
    private function getLogFilePath(int $index = 0): string
    {
        $suffix = $index === 0 ? '' : '.' . $index;

        return $this->directory . DIRECTORY_SEPARATOR . $this->fileName . $suffix;
    }

    /**
     * Формирует строку лога на основе шаблона
     *
     * @param string $level Уровень логирования
     * @param string $message Текст сообщения
     * @param array<string, mixed> $context Дополнительные данные
     */
    private function formatRecord(string $level, string $message, array $context): string
    {
        $timestamp = (new DateTimeImmutable())->format($this->dateFormat);

        try {
            $normalizedContext = $context === []
                ? '{}'
                : json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (Exception) {
            $normalizedContext = json_encode(['error' => 'Невозможно сериализовать контекст'], JSON_UNESCAPED_UNICODE);
        }

        $replacements = [
            '{timestamp}' => $timestamp,
            '{level}' => strtoupper($level),
            '{message}' => $message,
            '{context}' => (string)$normalizedContext,
        ];

        return strtr($this->pattern, $replacements);
    }

    /**
     * Выполняет запись сообщения в файл
     *
     * @param string $record Готовая строка лога
     */
    private function writeLog(string $record): void
    {
        if ($this->logBufferSizeBytes <= 0) {
            $this->rotateIfNeeded(strlen($record));
            file_put_contents($this->getLogFilePath(), $record, FILE_APPEND | LOCK_EX);

            return;
        }

        $this->logBuffer .= $record;
        $this->currentBufferSize += strlen($record);

        if ($this->currentBufferSize >= $this->logBufferSizeBytes) {
            $this->flushBuffer();
        }
    }

    /**
     * Выполняет ротацию файлов лога при достижении лимита размера
     */
    private function rotateIfNeeded(int $incomingRecordSize): void
    {
        $logFile = $this->getLogFilePath();

        if (!file_exists($logFile)) {
            return;
        }

        $currentSize = filesize($logFile);
        if ($currentSize === false) {
            return;
        }

        if ($currentSize + $incomingRecordSize <= $this->maxFileSize) {
            return;
        }

        $this->rotateFiles();
    }

    /**
     * Производит ротацию файлов лога с учётом ограничения по количеству
     */
    private function rotateFiles(): void
    {
        $lastIndex = $this->maxFiles - 1;

        if ($lastIndex <= 0) {
            unlink($this->getLogFilePath());

            return;
        }

        $lastFile = $this->getLogFilePath($lastIndex);
        if (file_exists($lastFile)) {
            unlink($lastFile);
        }

        for ($index = $lastIndex - 1; $index >= 0; $index--) {
            $source = $this->getLogFilePath($index);
            if (!file_exists($source)) {
                continue;
            }

            $target = $this->getLogFilePath($index + 1);
            rename($source, $target);
        }
    }

    /**
     * Сбрасывает содержимое буфера в лог-файл
     */
    private function flushBuffer(): void
    {
        if ($this->logBuffer === '') {
            return;
        }

        $this->rotateIfNeeded($this->currentBufferSize);
        file_put_contents($this->getLogFilePath(), $this->logBuffer, FILE_APPEND | LOCK_EX);

        $this->logBuffer = '';
        $this->currentBufferSize = 0;
    }
}

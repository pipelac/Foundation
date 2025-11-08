<?php

declare(strict_types=1);

namespace App\Rss2Tlg\Pipeline;

use App\Component\Logger;
use App\Component\MySQL;

/**
 * Абстрактный базовый класс для модулей AI Pipeline
 * 
 * Предоставляет общий функционал:
 * - Логирование
 * - Работа с метриками
 * - Валидация конфигурации
 * - Общие методы работы с БД
 */
abstract class AbstractPipelineModule implements PipelineModuleInterface
{
    protected MySQL $db;
    protected ?Logger $logger;
    protected array $config;
    protected array $metrics = [];

    /**
     * Название модуля (для логирования)
     */
    abstract protected function getModuleName(): string;

    /**
     * Валидирует специфичную для модуля конфигурацию
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     * @throws \Exception
     */
    abstract protected function validateModuleConfig(array $config): array;

    /**
     * Инициализирует метрики модуля
     *
     * @return array<string, mixed>
     */
    abstract protected function initializeMetrics(): array;

    /**
     * Базовая валидация конфигурации
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     * @throws \Exception
     */
    protected function validateConfig(array $config): array
    {
        $baseConfig = [
            'enabled' => $config['enabled'] ?? true,
            'retry_count' => max(0, (int)($config['retry_count'] ?? 2)),
            'timeout' => max(30, (int)($config['timeout'] ?? 120)),
        ];

        // Валидация специфичных настроек модуля
        $moduleConfig = $this->validateModuleConfig($config);

        return array_merge($baseConfig, $moduleConfig);
    }

    /**
     * {@inheritdoc}
     */
    public function processBatch(array $itemIds): array
    {
        $stats = [
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        foreach ($itemIds as $itemId) {
            $result = $this->processItem($itemId);
            
            if ($result) {
                $stats['success']++;
            } else {
                $existingStatus = $this->getStatus($itemId);
                if ($this->isSkippedStatus($existingStatus)) {
                    $stats['skipped']++;
                } else {
                    $stats['failed']++;
                }
            }
        }

        return $stats;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * {@inheritdoc}
     */
    public function resetMetrics(): void
    {
        $this->metrics = $this->initializeMetrics();
    }

    /**
     * Проверяет является ли статус "пропущенным"
     *
     * @param string|null $status
     * @return bool
     */
    protected function isSkippedStatus(?string $status): bool
    {
        return in_array($status, ['success', 'checked', 'skipped'], true);
    }

    /**
     * Обновляет счетчик метрик
     *
     * @param string $key
     * @param int $increment
     */
    protected function incrementMetric(string $key, int $increment = 1): void
    {
        if (!isset($this->metrics[$key])) {
            $this->metrics[$key] = 0;
        }
        $this->metrics[$key] += $increment;
    }

    /**
     * Записывает время выполнения в метрики
     *
     * @param float $startTime
     */
    protected function recordProcessingTime(float $startTime): int
    {
        $processingTime = (int)((microtime(true) - $startTime) * 1000);
        $this->incrementMetric('total_time_ms', $processingTime);
        return $processingTime;
    }

    // ============================================
    // МЕТОДЫ ЛОГИРОВАНИЯ
    // ============================================

    /**
     * Логирует отладочное сообщение
     *
     * @param string $message
     * @param array<string, mixed> $context
     */
    protected function logDebug(string $message, array $context = []): void
    {
        if ($this->logger) {
            $context['module'] = $this->getModuleName();
            $this->logger->debug($message, $context);
        }
    }

    /**
     * Логирует информационное сообщение
     *
     * @param string $message
     * @param array<string, mixed> $context
     */
    protected function logInfo(string $message, array $context = []): void
    {
        if ($this->logger) {
            $context['module'] = $this->getModuleName();
            $this->logger->info($message, $context);
        }
    }

    /**
     * Логирует предупреждение
     *
     * @param string $message
     * @param array<string, mixed> $context
     */
    protected function logWarning(string $message, array $context = []): void
    {
        if ($this->logger) {
            $context['module'] = $this->getModuleName();
            $this->logger->warning($message, $context);
        }
    }

    /**
     * Логирует ошибку
     *
     * @param string $message
     * @param array<string, mixed> $context
     */
    protected function logError(string $message, array $context = []): void
    {
        if ($this->logger) {
            $context['module'] = $this->getModuleName();
            $this->logger->error($message, $context);
        }
    }

    /**
     * Загружает промпт из файла
     *
     * @param string $filePath
     * @return string
     * @throws \Exception
     */
    protected function loadPromptFromFile(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new \Exception("Файл промпта не найден: {$filePath}");
        }

        $prompt = file_get_contents($filePath);
        
        if ($prompt === false) {
            throw new \Exception("Не удалось прочитать файл промпта: {$filePath}");
        }

        return $prompt;
    }

    /**
     * Безопасно получает значение из массива с дефолтом
     *
     * @param array<string, mixed> $array
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getArrayValue(array $array, string $key, $default = null)
    {
        return $array[$key] ?? $default;
    }

    /**
     * Проверяет существование записи в БД
     *
     * @param string $table
     * @param int $itemId
     * @return bool
     */
    protected function recordExists(string $table, int $itemId): bool
    {
        $query = "SELECT 1 FROM {$table} WHERE item_id = :item_id LIMIT 1";
        $result = $this->db->queryOne($query, ['item_id' => $itemId]);
        return !empty($result);
    }
}

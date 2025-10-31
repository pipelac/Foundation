<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Utils;

use App\Component\Logger;

/**
 * Сборщик метрик производительности для Telegram Bot
 * 
 * Отслеживает производительность методов API, время отклика,
 * частоту ошибок и другие метрики для мониторинга и оптимизации.
 */
class MetricsCollector
{
    /**
     * История выполнения методов [method => [duration, duration, ...]]
     * 
     * @var array<string, array<float>>
     */
    private array $methodDurations = [];

    /**
     * Счётчики успешных выполнений [method => count]
     * 
     * @var array<string, int>
     */
    private array $successCount = [];

    /**
     * Счётчики неудачных выполнений [method => count]
     * 
     * @var array<string, int>
     */
    private array $failureCount = [];

    /**
     * Общее количество запросов
     */
    private int $totalRequests = 0;

    /**
     * Время начала сбора метрик
     */
    private float $startTime;

    /**
     * Максимальное количество сохраняемых замеров для каждого метода
     */
    private int $maxMeasurements = 1000;

    /**
     * @param Logger|null $logger Логгер
     */
    public function __construct(
        private readonly ?Logger $logger = null
    ) {
        $this->startTime = microtime(true);
    }

    /**
     * Записывает метрику выполнения метода
     *
     * @param string $method Название метода API
     * @param float $duration Длительность выполнения в секундах
     * @param bool $success Успешность выполнения
     * @return void
     */
    public function track(string $method, float $duration, bool $success = true): void
    {
        // Инициализация массивов если ещё нет
        if (!isset($this->methodDurations[$method])) {
            $this->methodDurations[$method] = [];
            $this->successCount[$method] = 0;
            $this->failureCount[$method] = 0;
        }

        // Добавляем замер
        $this->methodDurations[$method][] = $duration;

        // Ограничиваем размер истории
        if (count($this->methodDurations[$method]) > $this->maxMeasurements) {
            array_shift($this->methodDurations[$method]);
        }

        // Обновляем счётчики
        if ($success) {
            $this->successCount[$method]++;
        } else {
            $this->failureCount[$method]++;
        }

        $this->totalRequests++;

        $this->logger?->debug('Метрика записана', [
            'method' => $method,
            'duration' => round($duration * 1000, 2) . 'ms',
            'success' => $success,
        ]);
    }

    /**
     * Получает среднее время отклика для метода
     *
     * @param string $method Название метода (если null - общее среднее)
     * @return float Среднее время в секундах
     */
    public function getAverageResponseTime(?string $method = null): float
    {
        if ($method !== null) {
            if (!isset($this->methodDurations[$method]) || empty($this->methodDurations[$method])) {
                return 0.0;
            }

            return array_sum($this->methodDurations[$method]) / count($this->methodDurations[$method]);
        }

        // Общее среднее
        $allDurations = [];
        foreach ($this->methodDurations as $durations) {
            $allDurations = array_merge($allDurations, $durations);
        }

        if (empty($allDurations)) {
            return 0.0;
        }

        return array_sum($allDurations) / count($allDurations);
    }

    /**
     * Получает медианное время отклика
     *
     * @param string|null $method Название метода
     * @return float Медианное время в секундах
     */
    public function getMedianResponseTime(?string $method = null): float
    {
        $durations = [];

        if ($method !== null) {
            if (!isset($this->methodDurations[$method]) || empty($this->methodDurations[$method])) {
                return 0.0;
            }
            $durations = $this->methodDurations[$method];
        } else {
            foreach ($this->methodDurations as $methodDurations) {
                $durations = array_merge($durations, $methodDurations);
            }
        }

        if (empty($durations)) {
            return 0.0;
        }

        sort($durations);
        $count = count($durations);
        $middle = (int)floor($count / 2);

        if ($count % 2 === 0) {
            return ($durations[$middle - 1] + $durations[$middle]) / 2;
        }

        return $durations[$middle];
    }

    /**
     * Получает процент неудачных выполнений
     *
     * @param string|null $method Название метода (если null - общий процент)
     * @return float Процент ошибок (0-100)
     */
    public function getFailureRate(?string $method = null): float
    {
        if ($method !== null) {
            $success = $this->successCount[$method] ?? 0;
            $failure = $this->failureCount[$method] ?? 0;
            $total = $success + $failure;

            if ($total === 0) {
                return 0.0;
            }

            return ($failure / $total) * 100;
        }

        // Общий процент
        $totalSuccess = array_sum($this->successCount);
        $totalFailure = array_sum($this->failureCount);
        $total = $totalSuccess + $totalFailure;

        if ($total === 0) {
            return 0.0;
        }

        return ($totalFailure / $total) * 100;
    }

    /**
     * Получает полную статистику
     *
     * @return array{
     *     total_requests: int,
     *     average_response_time: float,
     *     median_response_time: float,
     *     failure_rate: float,
     *     uptime: float,
     *     methods: array<string, array{
     *         calls: int,
     *         success: int,
     *         failures: int,
     *         avg_time: float,
     *         min_time: float,
     *         max_time: float,
     *         failure_rate: float
     *     }>
     * }
     */
    public function getStatistics(): array
    {
        $methods = [];

        foreach ($this->methodDurations as $method => $durations) {
            $success = $this->successCount[$method] ?? 0;
            $failures = $this->failureCount[$method] ?? 0;
            $total = $success + $failures;

            $methods[$method] = [
                'calls' => $total,
                'success' => $success,
                'failures' => $failures,
                'avg_time' => $this->getAverageResponseTime($method),
                'min_time' => !empty($durations) ? min($durations) : 0.0,
                'max_time' => !empty($durations) ? max($durations) : 0.0,
                'failure_rate' => $this->getFailureRate($method),
            ];
        }

        return [
            'total_requests' => $this->totalRequests,
            'average_response_time' => $this->getAverageResponseTime(),
            'median_response_time' => $this->getMedianResponseTime(),
            'failure_rate' => $this->getFailureRate(),
            'uptime' => microtime(true) - $this->startTime,
            'methods' => $methods,
        ];
    }

    /**
     * Получает топ самых медленных методов
     *
     * @param int $limit Количество методов
     * @return array<array{method: string, avg_time: float, calls: int}> Топ методов
     */
    public function getSlowestMethods(int $limit = 5): array
    {
        $stats = [];

        foreach ($this->methodDurations as $method => $durations) {
            if (empty($durations)) {
                continue;
            }

            $stats[] = [
                'method' => $method,
                'avg_time' => array_sum($durations) / count($durations),
                'calls' => count($durations),
            ];
        }

        usort($stats, fn($a, $b) => $b['avg_time'] <=> $a['avg_time']);

        return array_slice($stats, 0, $limit);
    }

    /**
     * Получает топ методов с самым высоким процентом ошибок
     *
     * @param int $limit Количество методов
     * @return array<array{method: string, failure_rate: float, failures: int, total: int}> Топ методов
     */
    public function getMostFailedMethods(int $limit = 5): array
    {
        $stats = [];

        foreach ($this->successCount as $method => $success) {
            $failures = $this->failureCount[$method] ?? 0;
            $total = $success + $failures;

            if ($total === 0) {
                continue;
            }

            $stats[] = [
                'method' => $method,
                'failure_rate' => ($failures / $total) * 100,
                'failures' => $failures,
                'total' => $total,
            ];
        }

        usort($stats, fn($a, $b) => $b['failure_rate'] <=> $a['failure_rate']);

        return array_slice($stats, 0, $limit);
    }

    /**
     * Сбрасывает все метрики
     *
     * @return void
     */
    public function reset(): void
    {
        $this->methodDurations = [];
        $this->successCount = [];
        $this->failureCount = [];
        $this->totalRequests = 0;
        $this->startTime = microtime(true);

        $this->logger?->info('Метрики сброшены');
    }

    /**
     * Экспортирует метрики в JSON
     *
     * @return string JSON строка с метриками
     */
    public function exportToJson(): string
    {
        return json_encode($this->getStatistics(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Экспортирует метрики в массив для Prometheus
     *
     * @param string $prefix Префикс метрик
     * @return array<array{name: string, value: float|int, labels: array<string, string>}> Метрики в формате Prometheus
     */
    public function exportToPrometheus(string $prefix = 'telegram_bot'): array
    {
        $metrics = [];

        // Общие метрики
        $metrics[] = [
            'name' => "{$prefix}_total_requests",
            'value' => $this->totalRequests,
            'labels' => [],
        ];

        $metrics[] = [
            'name' => "{$prefix}_avg_response_time_seconds",
            'value' => $this->getAverageResponseTime(),
            'labels' => [],
        ];

        $metrics[] = [
            'name' => "{$prefix}_failure_rate_percent",
            'value' => $this->getFailureRate(),
            'labels' => [],
        ];

        // Метрики по методам
        foreach ($this->methodDurations as $method => $durations) {
            $success = $this->successCount[$method] ?? 0;
            $failures = $this->failureCount[$method] ?? 0;

            $metrics[] = [
                'name' => "{$prefix}_method_calls_total",
                'value' => $success + $failures,
                'labels' => ['method' => $method],
            ];

            $metrics[] = [
                'name' => "{$prefix}_method_success_total",
                'value' => $success,
                'labels' => ['method' => $method],
            ];

            $metrics[] = [
                'name' => "{$prefix}_method_failures_total",
                'value' => $failures,
                'labels' => ['method' => $method],
            ];

            if (!empty($durations)) {
                $metrics[] = [
                    'name' => "{$prefix}_method_response_time_seconds",
                    'value' => array_sum($durations) / count($durations),
                    'labels' => ['method' => $method],
                ];
            }
        }

        return $metrics;
    }

    /**
     * Устанавливает максимальное количество сохраняемых замеров
     *
     * @param int $max Максимальное количество
     * @return self
     */
    public function setMaxMeasurements(int $max): self
    {
        $this->maxMeasurements = max(100, $max);
        return $this;
    }

    /**
     * Получает время работы (uptime) в секундах
     *
     * @return float Время работы в секундах
     */
    public function getUptime(): float
    {
        return microtime(true) - $this->startTime;
    }

    /**
     * Получает форматированное время работы
     *
     * @return string Форматированное время (например, "2h 15m 30s")
     */
    public function getFormattedUptime(): string
    {
        $uptime = $this->getUptime();
        $hours = (int)floor($uptime / 3600);
        $minutes = (int)floor(($uptime % 3600) / 60);
        $seconds = (int)($uptime % 60);

        $parts = [];
        if ($hours > 0) {
            $parts[] = "{$hours}h";
        }
        if ($minutes > 0) {
            $parts[] = "{$minutes}m";
        }
        $parts[] = "{$seconds}s";

        return implode(' ', $parts);
    }
}

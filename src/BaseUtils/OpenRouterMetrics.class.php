<?php

declare(strict_types=1);

namespace App\Component;

use App\Component\Exception\OpenRouterApiException;
use App\Component\Exception\OpenRouter\OpenRouterException;
use App\Component\Exception\OpenRouterValidationException;
use JsonException;

/**
 * Класс для работы с метриками и информацией OpenRouter API
 * 
 * Предоставляет методы для получения:
 * - Информации о API ключе (баланс, лимиты, использование)
 * - Статистики использования токенов
 * - Информации о конкретных генерациях
 * - Лимитов запросов (rate limits)
 * - Списка доступных моделей
 */
class OpenRouterMetrics
{
    private const BASE_URL = 'https://openrouter.ai/api/v1/';
    private const DEFAULT_TIMEOUT = 30;

    private string $apiKey;
    private string $appName;
    private int $timeout;
    private ?Logger $logger;
    private Http $http;

    /**
     * Конструктор класса OpenRouterMetrics
     *
     * @param array<string, mixed> $config Конфигурация OpenRouter API:
     *                                     - api_key (string, обязательно): API ключ OpenRouter
     *                                     - app_name (string, необязательно): Название приложения
     *                                     - timeout (int, необязательно): Таймаут соединения в секундах
     *                                     - retries (int, необязательно): Количество повторных попыток
     * @param Logger|null $logger Экземпляр логгера для записи событий
     * @throws OpenRouterValidationException Если API ключ не указан или конфигурация некорректна
     */
    public function __construct(array $config, ?Logger $logger = null)
    {
        $this->validateConfiguration($config);
        
        $this->apiKey = $config['api_key'];
        $this->appName = (string)($config['app_name'] ?? 'BasicUtilitiesApp');
        $this->timeout = max(1, (int)($config['timeout'] ?? self::DEFAULT_TIMEOUT));
        $this->logger = $logger;

        $httpConfig = [
            'base_uri' => self::BASE_URL,
            'timeout' => $this->timeout,
            'connect_timeout' => $this->timeout,
        ];

        if (isset($config['retries'])) {
            $httpConfig['retries'] = max(1, (int)$config['retries']);
        }

        $this->http = new Http($httpConfig, $logger);
    }

    /**
     * Получает информацию о текущем API ключе
     * 
     * Возвращает данные о балансе, лимитах и использовании ключа.
     *
     * @return array<string, mixed> Информация о ключе:
     *                              - label (string): Название ключа
     *                              - usage (float): Использованная сумма в USD
     *                              - limit (float|null): Лимит расходов в USD (null = без лимита)
     *                              - is_free_tier (bool): Является ли ключ бесплатным
     *                              - rate_limit (array): Информация о лимитах запросов
     * @throws OpenRouterApiException Если API вернул ошибку
     * @throws OpenRouterException Если не удалось декодировать ответ
     */
    public function getKeyInfo(): array
    {
        $response = $this->sendRequest('GET', '/auth/key');

        if (!isset($response['data'])) {
            throw new OpenRouterException('API не вернул информацию о ключе.');
        }

        return [
            'label' => (string)($response['data']['label'] ?? ''),
            'usage' => (float)($response['data']['usage'] ?? 0.0),
            'limit' => isset($response['data']['limit']) ? (float)$response['data']['limit'] : null,
            'is_free_tier' => (bool)($response['data']['is_free_tier'] ?? false),
            'rate_limit' => [
                'requests' => (int)($response['data']['rate_limit']['requests'] ?? 0),
                'interval' => (string)($response['data']['rate_limit']['interval'] ?? ''),
            ],
        ];
    }

    /**
     * Получает баланс текущего API ключа
     * 
     * Возвращает доступный баланс в USD. Для ключей с лимитом возвращает
     * разницу между лимитом и использованием.
     *
     * @return float Доступный баланс в USD
     * @throws OpenRouterApiException Если API вернул ошибку
     * @throws OpenRouterException Если не удалось получить информацию о балансе
     */
    public function getBalance(): float
    {
        $keyInfo = $this->getKeyInfo();

        if ($keyInfo['limit'] !== null) {
            return max(0.0, $keyInfo['limit'] - $keyInfo['usage']);
        }

        // Для ключей без лимита возвращаем отрицательное значение использования
        // (показывает сколько потрачено)
        return -$keyInfo['usage'];
    }

    /**
     * Получает общую статистику использования API ключа
     *
     * @return array<string, mixed> Статистика использования:
     *                              - total_usage (float): Общее использование в USD
     *                              - limit (float|null): Лимит расходов в USD
     *                              - remaining (float): Оставшийся баланс в USD
     *                              - usage_percent (float): Процент использования (0-100)
     *                              - is_free_tier (bool): Бесплатный уровень
     * @throws OpenRouterApiException Если API вернул ошибку
     * @throws OpenRouterException Если не удалось получить статистику
     */
    public function getUsageStats(): array
    {
        $keyInfo = $this->getKeyInfo();
        
        $totalUsage = $keyInfo['usage'];
        $limit = $keyInfo['limit'];
        $remaining = $limit !== null ? max(0.0, $limit - $totalUsage) : 0.0;
        $usagePercent = $limit !== null && $limit > 0 ? ($totalUsage / $limit) * 100 : 0.0;

        return [
            'total_usage' => $totalUsage,
            'limit' => $limit,
            'remaining' => $remaining,
            'usage_percent' => round($usagePercent, 2),
            'is_free_tier' => $keyInfo['is_free_tier'],
        ];
    }

    /**
     * Получает информацию о лимитах запросов (rate limits)
     *
     * @return array<string, mixed> Информация о rate limits:
     *                              - requests (int): Количество запросов
     *                              - interval (string): Интервал времени
     *                              - description (string): Описание лимита
     * @throws OpenRouterApiException Если API вернул ошибку
     * @throws OpenRouterException Если не удалось получить информацию о лимитах
     */
    public function getRateLimits(): array
    {
        $keyInfo = $this->getKeyInfo();
        
        $requests = $keyInfo['rate_limit']['requests'];
        $interval = $keyInfo['rate_limit']['interval'];

        return [
            'requests' => $requests,
            'interval' => $interval,
            'description' => $requests > 0 
                ? sprintf('%d запросов за %s', $requests, $interval)
                : 'Лимиты не установлены',
        ];
    }

    /**
     * Получает список доступных моделей с их параметрами
     * 
     * Возвращает информацию обо всех доступных моделях, включая их характеристики,
     * стоимость и возможности.
     *
     * @return array<int, array<string, mixed>> Массив моделей с параметрами:
     *                                          - id (string): Идентификатор модели
     *                                          - name (string): Название модели
     *                                          - description (string): Описание модели
     *                                          - pricing (array): Информация о стоимости
     *                                          - context_length (int): Максимальная длина контекста
     *                                          - architecture (array): Архитектура модели
     *                                          - top_provider (array): Информация о провайдере
     * @throws OpenRouterApiException Если API вернул ошибку
     * @throws OpenRouterException Если не удалось получить список моделей
     */
    public function getModels(): array
    {
        $response = $this->sendRequest('GET', '/models');

        if (!isset($response['data']) || !is_array($response['data'])) {
            throw new OpenRouterException('API не вернул список моделей.');
        }

        $models = [];
        
        foreach ($response['data'] as $model) {
            if (!is_array($model)) {
                continue;
            }

            $models[] = [
                'id' => (string)($model['id'] ?? ''),
                'name' => (string)($model['name'] ?? ''),
                'description' => (string)($model['description'] ?? ''),
                'pricing' => [
                    'prompt' => (string)($model['pricing']['prompt'] ?? '0'),
                    'completion' => (string)($model['pricing']['completion'] ?? '0'),
                    'image' => (string)($model['pricing']['image'] ?? '0'),
                    'request' => (string)($model['pricing']['request'] ?? '0'),
                ],
                'context_length' => (int)($model['context_length'] ?? 0),
                'architecture' => [
                    'modality' => (string)($model['architecture']['modality'] ?? ''),
                    'tokenizer' => (string)($model['architecture']['tokenizer'] ?? ''),
                    'instruct_type' => isset($model['architecture']['instruct_type']) 
                        ? (string)$model['architecture']['instruct_type'] 
                        : null,
                ],
                'top_provider' => [
                    'max_completion_tokens' => isset($model['top_provider']['max_completion_tokens']) 
                        ? (int)$model['top_provider']['max_completion_tokens'] 
                        : null,
                    'is_moderated' => (bool)($model['top_provider']['is_moderated'] ?? false),
                ],
            ];
        }

        return $models;
    }

    /**
     * Получает информацию о конкретной модели по её идентификатору
     *
     * @param string $modelId Идентификатор модели (например, "openai/gpt-4")
     * @return array<string, mixed> Информация о модели
     * @throws OpenRouterValidationException Если идентификатор модели пустой
     * @throws OpenRouterException Если модель не найдена
     * @throws OpenRouterApiException Если API вернул ошибку
     */
    public function getModelInfo(string $modelId): array
    {
        $this->validateNotEmpty($modelId, 'modelId');

        $models = $this->getModels();

        foreach ($models as $model) {
            if ($model['id'] === $modelId) {
                return $model;
            }
        }

        throw new OpenRouterException(sprintf('Модель "%s" не найдена.', $modelId));
    }

    /**
     * Вычисляет примерную стоимость запроса на основе количества токенов
     * 
     * Позволяет оценить стоимость до выполнения запроса.
     *
     * @param string $modelId Идентификатор модели
     * @param int $promptTokens Количество токенов в запросе
     * @param int $completionTokens Ожидаемое количество токенов в ответе
     * @return array<string, mixed> Информация о стоимости:
     *                              - prompt_cost (float): Стоимость запроса в USD
     *                              - completion_cost (float): Стоимость ответа в USD
     *                              - total_cost (float): Общая стоимость в USD
     *                              - model (string): Использованная модель
     * @throws OpenRouterValidationException Если параметры некорректны
     * @throws OpenRouterException Если модель не найдена
     * @throws OpenRouterApiException Если API вернул ошибку
     */
    public function estimateCost(string $modelId, int $promptTokens, int $completionTokens = 0): array
    {
        $this->validateNotEmpty($modelId, 'modelId');

        if ($promptTokens < 0) {
            throw new OpenRouterValidationException('Количество токенов запроса не может быть отрицательным.');
        }

        if ($completionTokens < 0) {
            throw new OpenRouterValidationException('Количество токенов ответа не может быть отрицательным.');
        }

        $modelInfo = $this->getModelInfo($modelId);
        
        // Стоимость указана за миллион токенов, конвертируем в USD
        $promptCostPerToken = (float)$modelInfo['pricing']['prompt'] / 1000000;
        $completionCostPerToken = (float)$modelInfo['pricing']['completion'] / 1000000;

        $promptCost = $promptTokens * $promptCostPerToken;
        $completionCost = $completionTokens * $completionCostPerToken;
        $totalCost = $promptCost + $completionCost;

        return [
            'prompt_cost' => round($promptCost, 6),
            'completion_cost' => round($completionCost, 6),
            'total_cost' => round($totalCost, 6),
            'model' => $modelId,
            'tokens' => [
                'prompt' => $promptTokens,
                'completion' => $completionTokens,
                'total' => $promptTokens + $completionTokens,
            ],
        ];
    }

    /**
     * Получает информацию о конкретной генерации по её ID
     * 
     * Возвращает детальную информацию о выполненном запросе, включая
     * использованные токены и стоимость.
     *
     * @param string $generationId ID генерации из заголовка X-Request-Id
     * @return array<string, mixed> Информация о генерации:
     *                              - id (string): ID генерации
     *                              - model (string): Использованная модель
     *                              - created_at (string): Время создания
     *                              - usage (array): Информация об использовании токенов
     *                              - cost (float): Стоимость запроса в USD
     * @throws OpenRouterValidationException Если ID генерации пустой
     * @throws OpenRouterApiException Если API вернул ошибку
     * @throws OpenRouterException Если не удалось получить информацию о генерации
     */
    public function getGenerationInfo(string $generationId): array
    {
        $this->validateNotEmpty($generationId, 'generationId');

        $response = $this->sendRequest('GET', '/generation?id=' . urlencode($generationId));

        if (!isset($response['data'])) {
            throw new OpenRouterException('API не вернул информацию о генерации.');
        }

        $data = $response['data'];

        return [
            'id' => (string)($data['id'] ?? $generationId),
            'model' => (string)($data['model'] ?? ''),
            'created_at' => (string)($data['created_at'] ?? ''),
            'usage' => [
                'prompt_tokens' => (int)($data['usage']['prompt_tokens'] ?? 0),
                'completion_tokens' => (int)($data['usage']['completion_tokens'] ?? 0),
                'total_tokens' => (int)($data['usage']['total_tokens'] ?? 0),
            ],
            'cost' => isset($data['native_tokens_prompt']) && isset($data['native_tokens_completion'])
                ? $this->calculateCostFromUsage($data)
                : 0.0,
        ];
    }

    /**
     * Проверяет, достаточно ли баланса для выполнения запроса
     *
     * @param float $estimatedCost Ожидаемая стоимость запроса в USD
     * @return bool True если баланс достаточен, false в противном случае
     * @throws OpenRouterValidationException Если стоимость отрицательная
     * @throws OpenRouterApiException Если API вернул ошибку
     * @throws OpenRouterException Если не удалось получить информацию о балансе
     */
    public function hasEnoughBalance(float $estimatedCost): bool
    {
        if ($estimatedCost < 0) {
            throw new OpenRouterValidationException('Стоимость не может быть отрицательной.');
        }

        $balance = $this->getBalance();

        // Если баланс отрицательный (нет лимита), всегда возвращаем true
        if ($balance < 0) {
            return true;
        }

        return $balance >= $estimatedCost;
    }

    /**
     * Получает информацию о текущем состоянии аккаунта
     * 
     * Возвращает полную информацию о ключе, балансе, лимитах и статистике.
     *
     * @return array<string, mixed> Полная информация об аккаунте:
     *                              - key_info (array): Информация о ключе
     *                              - balance (float): Текущий баланс
     *                              - usage_stats (array): Статистика использования
     *                              - rate_limits (array): Лимиты запросов
     * @throws OpenRouterApiException Если API вернул ошибку
     * @throws OpenRouterException Если не удалось получить информацию
     */
    public function getAccountStatus(): array
    {
        $keyInfo = $this->getKeyInfo();
        $usageStats = $this->getUsageStats();
        $rateLimits = $this->getRateLimits();
        $balance = $this->getBalance();

        return [
            'key_info' => $keyInfo,
            'balance' => $balance,
            'usage_stats' => $usageStats,
            'rate_limits' => $rateLimits,
        ];
    }

    /**
     * Валидирует конфигурацию при создании экземпляра класса
     *
     * @param array<string, mixed> $config Конфигурация для валидации
     * @throws OpenRouterValidationException Если конфигурация некорректна
     */
    private function validateConfiguration(array $config): void
    {
        if (!isset($config['api_key']) || !is_string($config['api_key']) || trim($config['api_key']) === '') {
            throw new OpenRouterValidationException('API ключ OpenRouter не указан или пустой.');
        }

        $config['api_key'] = trim($config['api_key']);
    }

    /**
     * Валидирует строковый параметр на пустоту
     *
     * @param string $value Значение для проверки
     * @param string $paramName Название параметра (для сообщения об ошибке)
     * @throws OpenRouterValidationException Если значение пустое
     */
    private function validateNotEmpty(string $value, string $paramName): void
    {
        if (trim($value) === '') {
            throw new OpenRouterValidationException(
                sprintf('Параметр "%s" не может быть пустым.', $paramName)
            );
        }
    }

    /**
     * Формирует стандартные заголовки для запросов к API
     *
     * @return array<string, string> Массив заголовков
     */
    private function buildHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'HTTP-Referer' => $this->appName,
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Выполняет HTTP-запрос к API
     *
     * @param string $method HTTP метод (GET, POST и т.д.)
     * @param string $endpoint Endpoint API (например, "/auth/key")
     * @param array<string, mixed> $payload Данные для отправки (для POST/PUT запросов)
     * @return array<string, mixed> Декодированный ответ API
     * @throws OpenRouterApiException Если API вернул код ошибки >= 400
     * @throws OpenRouterException Если не удалось декодировать JSON ответ
     */
    private function sendRequest(string $method, string $endpoint, array $payload = []): array
    {
        $headers = $this->buildHeaders();

        $options = [
            'headers' => $headers,
        ];

        if ($payload !== [] && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $options['json'] = $payload;
        }

        $response = $this->http->request($method, ltrim($endpoint, '/'), $options);

        $statusCode = $response->getStatusCode();
        $body = (string)$response->getBody();

        if ($statusCode >= 400) {
            $this->logError('Сервер OpenRouter вернул ошибку при запросе метрик', [
                'status_code' => $statusCode,
                'endpoint' => $endpoint,
                'response' => $body
            ]);

            throw new OpenRouterApiException('Сервер вернул код ошибки', $statusCode, $body);
        }

        try {
            return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new OpenRouterException(
                'Не удалось декодировать ответ API: ' . $exception->getMessage(),
                0,
                $exception
            );
        }
    }

    /**
     * Вычисляет стоимость на основе данных об использовании
     *
     * @param array<string, mixed> $data Данные о генерации
     * @return float Стоимость в USD
     */
    private function calculateCostFromUsage(array $data): float
    {
        $promptTokens = (int)($data['native_tokens_prompt'] ?? 0);
        $completionTokens = (int)($data['native_tokens_completion'] ?? 0);
        
        // Стоимость указана в USD за токен (уже в правильном масштабе)
        $promptCost = $promptTokens * (float)($data['cost_prompt'] ?? 0);
        $completionCost = $completionTokens * (float)($data['cost_completion'] ?? 0);

        return round($promptCost + $completionCost, 6);
    }

    /**
     * Извлекает детальные метрики из заголовков ответа OpenRouter
     * 
     * Парсит специфичные для OpenRouter заголовки, содержащие информацию о
     * стоимости, токенах, кешировании и производительности.
     *
     * @param array<string, mixed> $responseHeaders Массив заголовков HTTP ответа
     * @return array<string, mixed> Массив детальных метрик:
     *                              - model_used (string|null): Фактически использованная модель
     *                              - tokens (array): Детализация токенов (prompt, completion, total, cached)
     *                              - cost (array): Детализация стоимости (prompt, completion, total)
     *                              - cache (array): Метрики кеширования (hit_rate, hits, misses)
     *                              - timing (array): Временные метрики (queue_time, processing_time)
     *                              - generation_id (string|null): ID генерации для отслеживания
     */
    public function extractMetricsFromHeaders(array $responseHeaders): array
    {
        $metrics = [
            'model_used' => $this->getHeaderValue($responseHeaders, 'x-openrouter-model'),
            'tokens' => [
                'prompt_tokens' => (int)$this->getHeaderValue($responseHeaders, 'x-openrouter-tokens-prompt', 0),
                'completion_tokens' => (int)$this->getHeaderValue($responseHeaders, 'x-openrouter-tokens-completion', 0),
                'total_tokens' => (int)$this->getHeaderValue($responseHeaders, 'x-openrouter-tokens-total', 0),
                'cached_tokens' => (int)$this->getHeaderValue($responseHeaders, 'x-openrouter-tokens-cached', 0),
            ],
            'cost' => [
                'prompt_cost' => (float)$this->getHeaderValue($responseHeaders, 'x-openrouter-cost-prompt', 0.0),
                'completion_cost' => (float)$this->getHeaderValue($responseHeaders, 'x-openrouter-cost-completion', 0.0),
                'total_cost' => (float)$this->getHeaderValue($responseHeaders, 'x-openrouter-cost-total', 0.0),
            ],
            'cache' => [
                'hit_rate' => (float)$this->getHeaderValue($responseHeaders, 'x-openrouter-cache-hit-rate', 0.0),
                'hits' => (int)$this->getHeaderValue($responseHeaders, 'x-openrouter-cache-hits', 0),
                'misses' => (int)$this->getHeaderValue($responseHeaders, 'x-openrouter-cache-misses', 0),
            ],
            'timing' => [
                'queue_time_ms' => (int)$this->getHeaderValue($responseHeaders, 'x-openrouter-queue-time', 0),
                'processing_time_ms' => (int)$this->getHeaderValue($responseHeaders, 'x-openrouter-processing-time', 0),
            ],
            'generation_id' => $this->getHeaderValue($responseHeaders, 'x-request-id'),
        ];

        // Вычисляем производные метрики
        if ($metrics['tokens']['total_tokens'] > 0 && $metrics['tokens']['cached_tokens'] > 0) {
            $metrics['cache']['calculated_hit_rate'] = round(
                ($metrics['tokens']['cached_tokens'] / $metrics['tokens']['total_tokens']) * 100,
                2
            );
        } else {
            $metrics['cache']['calculated_hit_rate'] = 0.0;
        }

        $this->logInfo('Метрики OpenRouter извлечены из заголовков', $metrics);

        return $metrics;
    }

    /**
     * Получает значение заголовка из массива заголовков (case-insensitive)
     *
     * @param array<string, mixed> $headers Массив заголовков
     * @param string $headerName Название заголовка
     * @param mixed $default Значение по умолчанию
     * @return mixed Значение заголовка или значение по умолчанию
     */
    private function getHeaderValue(array $headers, string $headerName, $default = null)
    {
        $headerNameLower = strtolower($headerName);
        
        foreach ($headers as $key => $value) {
            if (strtolower($key) === $headerNameLower) {
                return is_array($value) ? $value[0] : $value;
            }
        }
        
        return $default;
    }

    /**
     * Создает детальный отчет по метрикам использования AI моделей
     * 
     * Агрегирует метрики из нескольких запросов и создает сводный отчет
     * с группировкой по моделям, временным периодам и другим параметрам.
     *
     * @param array<int, array<string, mixed>> $requestMetrics Массив метрик отдельных запросов
     * @return array<string, mixed> Детальный отчет:
     *                              - summary (array): Общая статистика
     *                              - by_model (array): Группировка по моделям
     *                              - cache_efficiency (array): Эффективность кеширования
     *                              - cost_breakdown (array): Детализация стоимости
     *                              - performance (array): Метрики производительности
     */
    public function createDetailedReport(array $requestMetrics): array
    {
        $report = [
            'summary' => [
                'total_requests' => count($requestMetrics),
                'total_tokens' => 0,
                'total_cost' => 0.0,
                'cached_tokens' => 0,
                'average_processing_time_ms' => 0,
            ],
            'by_model' => [],
            'cache_efficiency' => [
                'total_cacheable_tokens' => 0,
                'cached_tokens' => 0,
                'cache_hit_rate' => 0.0,
                'estimated_savings' => 0.0,
            ],
            'cost_breakdown' => [
                'prompt_cost' => 0.0,
                'completion_cost' => 0.0,
                'total_cost' => 0.0,
            ],
            'performance' => [
                'total_queue_time_ms' => 0,
                'total_processing_time_ms' => 0,
                'average_queue_time_ms' => 0,
                'average_processing_time_ms' => 0,
                'min_processing_time_ms' => PHP_INT_MAX,
                'max_processing_time_ms' => 0,
            ],
        ];

        foreach ($requestMetrics as $metrics) {
            // Summary
            $report['summary']['total_tokens'] += $metrics['tokens']['total_tokens'] ?? 0;
            $report['summary']['total_cost'] += $metrics['cost']['total_cost'] ?? 0.0;
            $report['summary']['cached_tokens'] += $metrics['tokens']['cached_tokens'] ?? 0;

            // By model
            $model = $metrics['model_used'] ?? 'unknown';
            if (!isset($report['by_model'][$model])) {
                $report['by_model'][$model] = [
                    'requests' => 0,
                    'total_tokens' => 0,
                    'total_cost' => 0.0,
                ];
            }
            $report['by_model'][$model]['requests']++;
            $report['by_model'][$model]['total_tokens'] += $metrics['tokens']['total_tokens'] ?? 0;
            $report['by_model'][$model]['total_cost'] += $metrics['cost']['total_cost'] ?? 0.0;

            // Cache efficiency
            $report['cache_efficiency']['total_cacheable_tokens'] += $metrics['tokens']['total_tokens'] ?? 0;
            $report['cache_efficiency']['cached_tokens'] += $metrics['tokens']['cached_tokens'] ?? 0;

            // Cost breakdown
            $report['cost_breakdown']['prompt_cost'] += $metrics['cost']['prompt_cost'] ?? 0.0;
            $report['cost_breakdown']['completion_cost'] += $metrics['cost']['completion_cost'] ?? 0.0;
            $report['cost_breakdown']['total_cost'] += $metrics['cost']['total_cost'] ?? 0.0;

            // Performance
            $processingTime = $metrics['timing']['processing_time_ms'] ?? 0;
            $queueTime = $metrics['timing']['queue_time_ms'] ?? 0;
            
            $report['performance']['total_processing_time_ms'] += $processingTime;
            $report['performance']['total_queue_time_ms'] += $queueTime;
            
            if ($processingTime > 0) {
                $report['performance']['min_processing_time_ms'] = min(
                    $report['performance']['min_processing_time_ms'],
                    $processingTime
                );
                $report['performance']['max_processing_time_ms'] = max(
                    $report['performance']['max_processing_time_ms'],
                    $processingTime
                );
            }
        }

        // Вычисляем средние значения
        $totalRequests = $report['summary']['total_requests'];
        if ($totalRequests > 0) {
            $report['summary']['average_processing_time_ms'] = round(
                $report['performance']['total_processing_time_ms'] / $totalRequests,
                2
            );
            $report['performance']['average_processing_time_ms'] = $report['summary']['average_processing_time_ms'];
            $report['performance']['average_queue_time_ms'] = round(
                $report['performance']['total_queue_time_ms'] / $totalRequests,
                2
            );
        }

        // Вычисляем cache hit rate
        if ($report['cache_efficiency']['total_cacheable_tokens'] > 0) {
            $report['cache_efficiency']['cache_hit_rate'] = round(
                ($report['cache_efficiency']['cached_tokens'] / 
                 $report['cache_efficiency']['total_cacheable_tokens']) * 100,
                2
            );
        }

        // Оцениваем сэкономленные средства (примерно)
        if ($report['summary']['total_tokens'] > 0) {
            $avgCostPerToken = $report['cost_breakdown']['total_cost'] / $report['summary']['total_tokens'];
            $report['cache_efficiency']['estimated_savings'] = round(
                $report['summary']['cached_tokens'] * $avgCostPerToken,
                6
            );
        }

        // Корректируем min_processing_time
        if ($report['performance']['min_processing_time_ms'] === PHP_INT_MAX) {
            $report['performance']['min_processing_time_ms'] = 0;
        }

        $this->logInfo('Создан детальный отчет по метрикам', [
            'total_requests' => $totalRequests,
            'total_cost' => $report['cost_breakdown']['total_cost'],
            'cache_hit_rate' => $report['cache_efficiency']['cache_hit_rate'],
        ]);

        return $report;
    }

    /**
     * Форматирует отчет в читаемый текстовый формат
     *
     * @param array<string, mixed> $report Отчет от createDetailedReport()
     * @return string Форматированный текстовый отчет
     */
    public function formatReportAsText(array $report): string
    {
        $output = "╔══════════════════════════════════════════════════════════════╗\n";
        $output .= "║           ДЕТАЛЬНЫЙ ОТЧЕТ ПО OPENROUTER МЕТРИКАМ            ║\n";
        $output .= "╚══════════════════════════════════════════════════════════════╝\n\n";

        // Summary
        $output .= "📊 ОБЩАЯ СТАТИСТИКА:\n";
        $output .= sprintf("  • Всего запросов: %d\n", $report['summary']['total_requests']);
        $output .= sprintf("  • Всего токенов: %d\n", $report['summary']['total_tokens']);
        $output .= sprintf("  • Кешированных токенов: %d\n", $report['summary']['cached_tokens']);
        $output .= sprintf("  • Общая стоимость: $%.6f\n", $report['cost_breakdown']['total_cost']);
        $output .= sprintf("  • Среднее время обработки: %d мс\n\n", 
            (int)$report['summary']['average_processing_time_ms']);

        // By Model
        $output .= "🤖 ПО МОДЕЛЯМ:\n";
        foreach ($report['by_model'] as $model => $stats) {
            $output .= sprintf("  • %s:\n", $model);
            $output .= sprintf("    - Запросов: %d\n", $stats['requests']);
            $output .= sprintf("    - Токенов: %d\n", $stats['total_tokens']);
            $output .= sprintf("    - Стоимость: $%.6f\n", $stats['total_cost']);
        }
        $output .= "\n";

        // Cache Efficiency
        $output .= "💾 ЭФФЕКТИВНОСТЬ КЕШИРОВАНИЯ:\n";
        $output .= sprintf("  • Cache Hit Rate: %.2f%%\n", $report['cache_efficiency']['cache_hit_rate']);
        $output .= sprintf("  • Сэкономлено (оценка): $%.6f\n\n", 
            $report['cache_efficiency']['estimated_savings']);

        // Cost Breakdown
        $output .= "💰 ДЕТАЛИЗАЦИЯ СТОИМОСТИ:\n";
        $output .= sprintf("  • Промпты: $%.6f\n", $report['cost_breakdown']['prompt_cost']);
        $output .= sprintf("  • Ответы: $%.6f\n", $report['cost_breakdown']['completion_cost']);
        $output .= sprintf("  • Всего: $%.6f\n\n", $report['cost_breakdown']['total_cost']);

        // Performance
        $output .= "⚡ ПРОИЗВОДИТЕЛЬНОСТЬ:\n";
        $output .= sprintf("  • Среднее время в очереди: %d мс\n", 
            (int)$report['performance']['average_queue_time_ms']);
        $output .= sprintf("  • Среднее время обработки: %d мс\n", 
            (int)$report['performance']['average_processing_time_ms']);
        $output .= sprintf("  • Мин. время обработки: %d мс\n", 
            $report['performance']['min_processing_time_ms']);
        $output .= sprintf("  • Макс. время обработки: %d мс\n", 
            $report['performance']['max_processing_time_ms']);

        return $output;
    }

    /**
     * Записывает информационное сообщение в лог при наличии логгера
     *
     * @param string $message Информационное сообщение
     * @param array<string, mixed> $context Контекст сообщения
     */
    private function logInfo(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->info($message, $context);
        }
    }

    /**
     * Записывает ошибку в лог при наличии логгера
     *
     * @param string $message Сообщение об ошибке
     * @param array<string, mixed> $context Контекст ошибки
     */
    private function logError(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->error($message, $context);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Component;

use App\Component\Exception\OpenRouterApiException;
use App\Component\Exception\OpenRouterException;
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
    private const BASE_URL = 'https://openrouter.ai/api/v1';
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

        $response = $this->http->request($method, $endpoint, $options);

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

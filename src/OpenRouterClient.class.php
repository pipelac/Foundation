<?php

declare(strict_types=1);

namespace App\Component;

use App\Component\Exception\OpenRouterApiException;
use App\Component\Exception\OpenRouterException;
use App\Component\Exception\OpenRouterNetworkException;
use App\Component\Exception\OpenRouterValidationException;
use JsonException;

/**
 * Класс для работы с внутренней информацией OpenRouter API
 * 
 * Предоставляет методы для получения детальной информации о:
 * - Балансе и кредитах аккаунта
 * - Использованных токенах
 * - Истории генераций
 * - Лимитах и ограничениях
 * - Статистике использования API
 */
class OpenRouterClient
{
    private const BASE_URL = 'https://openrouter.ai/api/v1';
    private const DEFAULT_TIMEOUT = 30;

    private string $apiKey;
    private string $appName;
    private int $timeout;
    private ?Logger $logger;
    private Http $http;

    /**
     * Конструктор класса OpenRouterClient
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
     * Получает информацию об API ключе, балансе и лимитах аккаунта
     *
     * @return array<string, mixed> Информация об аккаунте:
     *                              - label (string): Название API ключа
     *                              - usage (float): Использованная сумма в USD
     *                              - limit (float|null): Лимит использования в USD (null = безлимитный)
     *                              - is_free_tier (bool): Является ли аккаунт бесплатным
     *                              - rate_limit (array): Информация о лимитах запросов
     * @throws OpenRouterApiException Если API вернул ошибку
     * @throws OpenRouterException Если не удалось декодировать ответ
     */
    public function getKeyInfo(): array
    {
        $response = $this->sendGetRequest('/auth/key');

        $this->logInfo('Получена информация об API ключе', [
            'label' => $response['data']['label'] ?? 'unknown',
            'usage' => $response['data']['usage'] ?? 0,
        ]);

        return $response['data'] ?? [];
    }

    /**
     * Получает детальную информацию о конкретной генерации по ID
     *
     * @param string $generationId ID генерации (получается из заголовка X-OpenRouter-Generation-Id)
     * @return array<string, mixed> Информация о генерации:
     *                              - id (string): ID генерации
     *                              - model (string): Использованная модель
     *                              - usage (array): Детали использования токенов
     *                              - created_at (int): Временная метка создания
     *                              - total_cost (float): Общая стоимость в USD
     * @throws OpenRouterValidationException Если ID генерации пустой
     * @throws OpenRouterApiException Если API вернул ошибку
     * @throws OpenRouterException Если не удалось декодировать ответ
     */
    public function getGenerationInfo(string $generationId): array
    {
        $this->validateNotEmpty($generationId, 'generationId');

        $response = $this->sendGetRequest('/generation?id=' . urlencode($generationId));

        $this->logInfo('Получена информация о генерации', [
            'generation_id' => $generationId,
            'model' => $response['data']['model'] ?? 'unknown',
        ]);

        return $response['data'] ?? [];
    }

    /**
     * Получает список доступных моделей с их параметрами и стоимостью
     *
     * @return array<int, array<string, mixed>> Список моделей, где каждая модель содержит:
     *                                          - id (string): ID модели
     *                                          - name (string): Название модели
     *                                          - pricing (array): Информация о ценах
     *                                          - context_length (int): Максимальная длина контекста
     *                                          - architecture (array): Архитектура модели
     * @throws OpenRouterApiException Если API вернул ошибку
     * @throws OpenRouterException Если не удалось декодировать ответ
     */
    public function getModels(): array
    {
        $response = $this->sendGetRequest('/models');

        $models = $response['data'] ?? [];

        $this->logInfo('Получен список доступных моделей', [
            'count' => count($models),
        ]);

        return $models;
    }

    /**
     * Получает информацию о конкретной модели
     *
     * @param string $modelId ID модели (например, "openai/gpt-4")
     * @return array<string, mixed> Детальная информация о модели:
     *                              - id (string): ID модели
     *                              - name (string): Название модели
     *                              - pricing (array): Цены за промпт и completion токены
     *                              - context_length (int): Максимальная длина контекста
     *                              - architecture (array): Информация об архитектуре
     *                              - top_provider (array): Информация о провайдере
     * @throws OpenRouterValidationException Если ID модели пустой
     * @throws OpenRouterApiException Если API вернул ошибку
     * @throws OpenRouterException Если модель не найдена
     */
    public function getModelInfo(string $modelId): array
    {
        $this->validateNotEmpty($modelId, 'modelId');

        $models = $this->getModels();

        foreach ($models as $model) {
            if (isset($model['id']) && $model['id'] === $modelId) {
                $this->logInfo('Найдена информация о модели', [
                    'model_id' => $modelId,
                ]);

                return $model;
            }
        }

        throw new OpenRouterException(sprintf('Модель "%s" не найдена', $modelId));
    }

    /**
     * Получает текущий баланс аккаунта в USD
     *
     * @return float Баланс в USD (может быть отрицательным при превышении лимита)
     * @throws OpenRouterApiException Если API вернул ошибку
     * @throws OpenRouterException Если не удалось получить информацию о балансе
     */
    public function getBalance(): float
    {
        $keyInfo = $this->getKeyInfo();

        $usage = (float)($keyInfo['usage'] ?? 0.0);
        $limit = isset($keyInfo['limit']) ? (float)$keyInfo['limit'] : PHP_FLOAT_MAX;

        $balance = $limit - $usage;

        $this->logInfo('Рассчитан текущий баланс', [
            'balance' => $balance,
            'usage' => $usage,
            'limit' => $limit,
        ]);

        return $balance;
    }

    /**
     * Получает статистику использования токенов из информации об аккаунте
     *
     * @return array<string, mixed> Статистика использования:
     *                              - total_usage_usd (float): Общая сумма использования в USD
     *                              - limit_usd (float|null): Лимит в USD
     *                              - remaining_usd (float): Оставшийся баланс в USD
     *                              - is_free_tier (bool): Бесплатный аккаунт
     *                              - usage_percentage (float): Процент использованного лимита
     * @throws OpenRouterApiException Если API вернул ошибку
     * @throws OpenRouterException Если не удалось получить информацию
     */
    public function getUsageStats(): array
    {
        $keyInfo = $this->getKeyInfo();

        $usage = (float)($keyInfo['usage'] ?? 0.0);
        $limit = isset($keyInfo['limit']) ? (float)$keyInfo['limit'] : null;
        $isFree = (bool)($keyInfo['is_free_tier'] ?? false);

        $remaining = $limit !== null ? ($limit - $usage) : PHP_FLOAT_MAX;
        $usagePercentage = $limit !== null && $limit > 0 ? ($usage / $limit * 100) : 0.0;

        $stats = [
            'total_usage_usd' => $usage,
            'limit_usd' => $limit,
            'remaining_usd' => $remaining,
            'is_free_tier' => $isFree,
            'usage_percentage' => round($usagePercentage, 2),
        ];

        $this->logInfo('Получена статистика использования', $stats);

        return $stats;
    }

    /**
     * Рассчитывает стоимость запроса на основе использованных токенов
     *
     * @param string $modelId ID модели
     * @param int $promptTokens Количество токенов в промпте
     * @param int $completionTokens Количество токенов в ответе
     * @return array<string, mixed> Информация о стоимости:
     *                              - model_id (string): ID модели
     *                              - prompt_tokens (int): Токены промпта
     *                              - completion_tokens (int): Токены ответа
     *                              - total_tokens (int): Всего токенов
     *                              - prompt_cost_usd (float): Стоимость промпта в USD
     *                              - completion_cost_usd (float): Стоимость ответа в USD
     *                              - total_cost_usd (float): Общая стоимость в USD
     * @throws OpenRouterValidationException Если параметры невалидны
     * @throws OpenRouterException Если модель не найдена или нет информации о ценах
     */
    public function calculateCost(string $modelId, int $promptTokens, int $completionTokens): array
    {
        $this->validateNotEmpty($modelId, 'modelId');

        if ($promptTokens < 0) {
            throw new OpenRouterValidationException('Количество токенов промпта не может быть отрицательным');
        }

        if ($completionTokens < 0) {
            throw new OpenRouterValidationException('Количество токенов ответа не может быть отрицательным');
        }

        $modelInfo = $this->getModelInfo($modelId);

        if (!isset($modelInfo['pricing'])) {
            throw new OpenRouterException(sprintf('Нет информации о ценах для модели "%s"', $modelId));
        }

        $pricing = $modelInfo['pricing'];

        $promptPrice = (float)($pricing['prompt'] ?? 0.0);
        $completionPrice = (float)($pricing['completion'] ?? 0.0);

        $promptCost = ($promptTokens / 1000000) * $promptPrice;
        $completionCost = ($completionTokens / 1000000) * $completionPrice;
        $totalCost = $promptCost + $completionCost;

        $result = [
            'model_id' => $modelId,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $promptTokens + $completionTokens,
            'prompt_cost_usd' => round($promptCost, 6),
            'completion_cost_usd' => round($completionCost, 6),
            'total_cost_usd' => round($totalCost, 6),
        ];

        $this->logInfo('Рассчитана стоимость запроса', $result);

        return $result;
    }

    /**
     * Проверяет валидность API ключа
     *
     * @return bool true если ключ валидный, false иначе
     */
    public function validateApiKey(): bool
    {
        try {
            $this->getKeyInfo();
            $this->logInfo('API ключ валидный');
            return true;
        } catch (OpenRouterApiException $e) {
            $this->logError('API ключ невалидный', [
                'status_code' => $e->getStatusCode(),
                'error' => $e->getMessage(),
            ]);
            return false;
        } catch (OpenRouterException $e) {
            $this->logError('Ошибка при проверке API ключа', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Получает информацию о лимитах запросов
     *
     * @return array<string, mixed> Информация о лимитах:
     *                              - requests_per_minute (int|null): Запросов в минуту
     *                              - requests_per_day (int|null): Запросов в день
     *                              - tokens_per_minute (int|null): Токенов в минуту
     * @throws OpenRouterApiException Если API вернул ошибку
     * @throws OpenRouterException Если не удалось получить информацию
     */
    public function getRateLimits(): array
    {
        $keyInfo = $this->getKeyInfo();

        $rateLimits = [
            'requests_per_minute' => $keyInfo['rate_limit']['requests_per_minute'] ?? null,
            'requests_per_day' => $keyInfo['rate_limit']['requests_per_day'] ?? null,
            'tokens_per_minute' => $keyInfo['rate_limit']['tokens_per_minute'] ?? null,
        ];

        $this->logInfo('Получена информация о лимитах', $rateLimits);

        return $rateLimits;
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
        ];
    }

    /**
     * Выполняет GET запрос к API
     *
     * @param string $endpoint Endpoint API (например, "/auth/key")
     * @return array<string, mixed> Декодированный ответ API
     * @throws OpenRouterApiException Если API вернул код ошибки >= 400
     * @throws OpenRouterException Если не удалось декодировать JSON ответ
     */
    private function sendGetRequest(string $endpoint): array
    {
        $response = $this->http->request('GET', $endpoint, [
            'headers' => $this->buildHeaders(),
        ]);

        $statusCode = $response->getStatusCode();
        $body = (string)$response->getBody();

        if ($statusCode >= 400) {
            $this->logError('Сервер OpenRouter вернул ошибку', [
                'status_code' => $statusCode,
                'endpoint' => $endpoint,
                'response' => $body,
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
     * Записывает информационное сообщение в лог при наличии логгера
     *
     * @param string $message Сообщение для записи
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

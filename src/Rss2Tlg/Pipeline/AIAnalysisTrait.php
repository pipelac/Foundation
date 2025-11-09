<?php

declare(strict_types=1);

namespace App\Rss2Tlg\Pipeline;

use App\Component\OpenRouter;
use Exception;

/**
 * Трейт для AI анализа с fallback механизмом
 * 
 * Предоставляет универсальный функционал для работы с AI моделями:
 * - Fallback между моделями
 * - Retry механизм
 * - Кеширование промптов (Claude)
 * - Сбор метрик использования
 */
trait AIAnalysisTrait
{
    protected OpenRouter $openRouter;

    /**
     * Анализирует с использованием fallback моделей
     *
     * @param string $systemPrompt Системный промпт
     * @param string $userPrompt Пользовательский промпт
     * @param array<string, mixed>|null $options Дополнительные опции
     * @return array<string, mixed>|null
     */
    protected function analyzeWithFallback(
        string $systemPrompt,
        string $userPrompt,
        ?array $options = null
    ): ?array {
        $models = $this->config['models'] ?? [];
        
        if ($this->config['fallback_strategy'] === 'random') {
            shuffle($models);
        }

        $lastError = null;

        foreach ($models as $modelConfig) {
            $modelName = is_array($modelConfig) ? ($modelConfig['model'] ?? '') : $modelConfig;
            $retryCount = $this->config['retry_count'] ?? 2;

            // Увеличиваем счетчик попыток для модели
            if (!isset($this->metrics['model_attempts'][$modelName])) {
                $this->metrics['model_attempts'][$modelName] = 0;
            }
            $this->metrics['model_attempts'][$modelName]++;

            $this->logDebug('Попытка AI анализа', [
                'model' => $modelName,
            ]);

            for ($attempt = 0; $attempt <= $retryCount; $attempt++) {
                try {
                    $result = $this->callAI($modelName, $modelConfig, $systemPrompt, $userPrompt, $options);
                    
                    if ($result) {
                        // Обновляем метрики
                        if (isset($result['tokens_used'])) {
                            $this->incrementMetric('total_tokens', $result['tokens_used']);
                        }
                        if (isset($result['cache_hit']) && $result['cache_hit']) {
                            $this->incrementMetric('cache_hits');
                        }

                        $result['model_used'] = $modelName;
                        return $result;
                    }

                } catch (Exception $e) {
                    $lastError = $e->getMessage();
                    
                    $this->logWarning('Ошибка при вызове AI', [
                        'model' => $modelName,
                        'attempt' => $attempt + 1,
                        'error' => $lastError,
                    ]);

                    if ($attempt < $retryCount) {
                        // Экспоненциальная задержка перед повтором
                        usleep(min(5000000, 500000 * (2 ** $attempt)));
                    }
                }
            }
        }

        $this->logError('Все модели не смогли выполнить анализ', [
            'last_error' => $lastError,
        ]);

        return null;
    }

    /**
     * Вызывает AI модель для анализа
     *
     * @param string $model Название модели
     * @param array<string, mixed>|string $modelConfig Конфигурация модели
     * @param string $systemPrompt Системный промпт
     * @param string $userPrompt Пользовательский промпт
     * @param array<string, mixed>|null $extraOptions Дополнительные опции
     * @return array<string, mixed>|null
     * @throws Exception
     */
    protected function callAI(
        string $model,
        $modelConfig,
        string $systemPrompt,
        string $userPrompt,
        ?array $extraOptions = null
    ): ?array {
        // Формируем messages для chatWithMessages
        $messages = $this->prepareMessages($systemPrompt, $userPrompt, $model);

        // Извлекаем параметры модели из конфигурации
        $options = $this->prepareOptions($modelConfig, $extraOptions);

        $response = $this->openRouter->chatWithMessages($model, $messages, $options);

        if (!$response || !isset($response['content'])) {
            return null;
        }

        $content = $response['content'];
        $analysisData = json_decode($content, true);

        if (!$analysisData) {
            throw new Exception('Не удалось распарсить JSON ответ от AI');
        }

        // Извлекаем метрики
        $usage = $response['usage'] ?? [];
        
        return [
            'analysis_data' => $analysisData,
            'tokens_used' => $usage['total_tokens'] ?? 0,
            'tokens_prompt' => $usage['prompt_tokens'] ?? 0,
            'tokens_completion' => $usage['completion_tokens'] ?? 0,
            'tokens_cached' => $usage['cached_tokens'] ?? 0,
            'cache_hit' => ($usage['cached_tokens'] ?? 0) > 0,
        ];
    }

    /**
     * Подготавливает messages для AI запроса
     *
     * @param string $systemPrompt
     * @param string $userPrompt
     * @param string $model
     * @return array<int, array<string, mixed>>
     */
    protected function prepareMessages(string $systemPrompt, string $userPrompt, string $model): array
    {
        $messages = [];

        // Для Claude используем кеширование промптов
        if (str_contains($model, 'claude')) {
            $messages[] = [
                'role' => 'system',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $systemPrompt,
                        'cache_control' => ['type' => 'ephemeral'],
                    ],
                ],
            ];
        } else {
            $messages[] = [
                'role' => 'system',
                'content' => $systemPrompt,
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $userPrompt,
        ];

        return $messages;
    }

    /**
     * Подготавливает опции для AI запроса
     *
     * @param array<string, mixed>|string $modelConfig
     * @param array<string, mixed>|null $extraOptions
     * @return array<string, mixed>
     */
    protected function prepareOptions($modelConfig, ?array $extraOptions = null): array
    {
        $options = ['response_format' => ['type' => 'json_object']];
        
        if (is_array($modelConfig)) {
            // Копируем параметры из конфигурации модели
            $allowedParams = ['max_tokens', 'temperature', 'top_p', 'frequency_penalty', 'presence_penalty'];
            foreach ($allowedParams as $param) {
                if (isset($modelConfig[$param])) {
                    $options[$param] = $modelConfig[$param];
                }
            }
        } else {
            // Дефолтные значения для обратной совместимости
            $options['max_tokens'] = 1500;
            $options['temperature'] = 0.2;
        }

        // Объединяем с дополнительными опциями
        if ($extraOptions) {
            $options = array_merge($options, $extraOptions);
        }

        return $options;
    }

    /**
     * Валидирует конфигурацию AI модулей
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     * @throws Exception
     */
    protected function validateAIConfig(array $config): array
    {
        if (empty($config['models']) || !is_array($config['models'])) {
            throw new Exception('Не указаны AI модели');
        }

        if (empty($config['prompt_file']) || !file_exists($config['prompt_file'])) {
            throw new Exception('Не указан или не найден файл промпта');
        }

        return [
            'models' => $config['models'],
            'fallback_strategy' => $config['fallback_strategy'] ?? 'sequential',
            'prompt_file' => $config['prompt_file'],
        ];
    }
}

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
 * - Детальное хранение метрик OpenRouter в БД
 */
trait AIAnalysisTrait
{
    protected OpenRouter $openRouter;
    protected ?\App\Component\MySQL $metricsDb = null;

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
        
        // Извлекаем JSON из ответа (может быть обернут в markdown блоки или иметь префикс)
        $jsonContent = $this->extractJSON($content);
        
        $analysisData = json_decode($jsonContent, true);

        if (!$analysisData) {
            throw new Exception('Не удалось распарсить JSON ответ от AI: ' . json_last_error_msg());
        }

        // Извлекаем метрики
        $usage = $response['usage'] ?? [];
        
        // Записываем детальные метрики в БД (если доступна)
        if (isset($response['detailed_metrics'])) {
            $pipelineModule = get_class($this); // Получаем название класса как pipeline_module
            $pipelineModule = str_replace('App\\Rss2Tlg\\Pipeline\\', '', $pipelineModule);
            
            $this->recordDetailedMetrics(
                $response['detailed_metrics'],
                $pipelineModule,
                null, // batch_id можно передать извне при необходимости
                null  // task_context можно передать извне при необходимости
            );
        }
        
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
     * Извлекает JSON из ответа AI (удаляет markdown блоки и префиксы)
     *
     * @param string $content Ответ от AI
     * @return string Очищенный JSON
     */
    protected function extractJSON(string $content): string
    {
        // Убираем лишние пробелы в начале и конце
        $content = trim($content);
        
        // Паттерн 1: JSON внутри markdown блоков ```json...``` или ```...```
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?```/s', $content, $matches)) {
            return trim($matches[1]);
        }
        
        // Паттерн 2: JSON начинается с { и заканчивается }
        if (preg_match('/(\{.*\})/s', $content, $matches)) {
            return $matches[1];
        }
        
        // Паттерн 3: JSON начинается с [ и заканчивается ]
        if (preg_match('/(\[.*\])/s', $content, $matches)) {
            return $matches[1];
        }
        
        // Возвращаем как есть, если не нашли паттернов
        return $content;
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

    /**
     * Записывает детальные метрики OpenRouter в БД
     *
     * @param array<string, mixed> $detailedMetrics Детальные метрики из OpenRouter.parseDetailedMetrics()
     * @param string|null $pipelineModule Название модуля pipeline (Summarization, Deduplication, etc)
     * @param int|null $batchId ID batch обработки
     * @param string|null $taskContext Дополнительный контекст задачи (JSON)
     * @return int|null ID записанной записи или null при ошибке
     */
    protected function recordDetailedMetrics(
        array $detailedMetrics,
        ?string $pipelineModule = null,
        ?int $batchId = null,
        ?string $taskContext = null
    ): ?int {
        // Проверяем доступность БД для метрик
        if ($this->metricsDb === null) {
            $this->logWarning('Metrics DB не инициализирована, пропускаем запись метрик');
            return null;
        }

        try {
            $sql = "
                INSERT INTO openrouter_metrics (
                    generation_id, model, provider_name, created_at,
                    generation_time, latency, moderation_latency,
                    tokens_prompt, tokens_completion,
                    native_tokens_prompt, native_tokens_completion, native_tokens_cached, native_tokens_reasoning,
                    usage_total, usage_cache, usage_data, usage_file,
                    finish_reason,
                    pipeline_module, batch_id, task_context,
                    full_response
                ) VALUES (
                    :generation_id, :model, :provider_name, :created_at,
                    :generation_time, :latency, :moderation_latency,
                    :tokens_prompt, :tokens_completion,
                    :native_tokens_prompt, :native_tokens_completion, :native_tokens_cached, :native_tokens_reasoning,
                    :usage_total, :usage_cache, :usage_data, :usage_file,
                    :finish_reason,
                    :pipeline_module, :batch_id, :task_context,
                    :full_response
                )
            ";

            $params = [
                ':generation_id' => $detailedMetrics['generation_id'] ?? null,
                ':model' => $detailedMetrics['model'] ?? null,
                ':provider_name' => $detailedMetrics['provider_name'] ?? null,
                ':created_at' => $detailedMetrics['created_at'] ?? null,
                
                ':generation_time' => $detailedMetrics['generation_time'] ?? null,
                ':latency' => $detailedMetrics['latency'] ?? null,
                ':moderation_latency' => $detailedMetrics['moderation_latency'] ?? null,
                
                ':tokens_prompt' => $detailedMetrics['tokens_prompt'] ?? null,
                ':tokens_completion' => $detailedMetrics['tokens_completion'] ?? null,
                
                ':native_tokens_prompt' => $detailedMetrics['native_tokens_prompt'] ?? null,
                ':native_tokens_completion' => $detailedMetrics['native_tokens_completion'] ?? null,
                ':native_tokens_cached' => $detailedMetrics['native_tokens_cached'] ?? null,
                ':native_tokens_reasoning' => $detailedMetrics['native_tokens_reasoning'] ?? null,
                
                ':usage_total' => $detailedMetrics['usage_total'] ?? null,
                ':usage_cache' => $detailedMetrics['usage_cache'] ?? null,
                ':usage_data' => $detailedMetrics['usage_data'] ?? null,
                ':usage_file' => $detailedMetrics['usage_file'] ?? null,
                
                ':finish_reason' => $detailedMetrics['finish_reason'] ?? null,
                
                ':pipeline_module' => $pipelineModule,
                ':batch_id' => $batchId,
                ':task_context' => $taskContext,
                
                ':full_response' => $detailedMetrics['full_response'] ?? null,
            ];

            $this->metricsDb->execute($sql, $params);
            $insertId = (int)$this->metricsDb->getLastInsertId();

            $this->logDebug('Детальные метрики записаны в БД', [
                'metrics_id' => $insertId,
                'generation_id' => $detailedMetrics['generation_id'] ?? null,
                'model' => $detailedMetrics['model'] ?? null,
                'tokens_total' => ($detailedMetrics['tokens_prompt'] ?? 0) + ($detailedMetrics['tokens_completion'] ?? 0),
                'usage_total' => $detailedMetrics['usage_total'] ?? null,
            ]);

            return $insertId;

        } catch (Exception $e) {
            $this->logError('Ошибка записи детальных метрик в БД', [
                'error' => $e->getMessage(),
                'generation_id' => $detailedMetrics['generation_id'] ?? null,
            ]);
            return null;
        }
    }

    /**
     * Получает детальные метрики из БД по различным критериям
     *
     * @param array<string, mixed> $filters Фильтры поиска:
     *                                       - generation_id (string): ID генерации
     *                                       - model (string): Название модели
     *                                       - pipeline_module (string): Модуль pipeline
     *                                       - batch_id (int): ID batch
     *                                       - date_from (string): Дата начала (YYYY-MM-DD)
     *                                       - date_to (string): Дата окончания (YYYY-MM-DD)
     *                                       - limit (int): Лимит записей (по умолчанию 100)
     * @return array<int, array<string, mixed>> Массив записей метрик
     */
    protected function getDetailedMetrics(array $filters = []): array
    {
        // Проверяем доступность БД для метрик
        if ($this->metricsDb === null) {
            $this->logWarning('Metrics DB не инициализирована, возвращаем пустой массив');
            return [];
        }

        try {
            $where = [];
            $params = [];

            if (isset($filters['generation_id'])) {
                $where[] = 'generation_id = :generation_id';
                $params[':generation_id'] = $filters['generation_id'];
            }

            if (isset($filters['model'])) {
                $where[] = 'model = :model';
                $params[':model'] = $filters['model'];
            }

            if (isset($filters['pipeline_module'])) {
                $where[] = 'pipeline_module = :pipeline_module';
                $params[':pipeline_module'] = $filters['pipeline_module'];
            }

            if (isset($filters['batch_id'])) {
                $where[] = 'batch_id = :batch_id';
                $params[':batch_id'] = $filters['batch_id'];
            }

            if (isset($filters['date_from'])) {
                $where[] = 'recorded_at >= :date_from';
                $params[':date_from'] = $filters['date_from'];
            }

            if (isset($filters['date_to'])) {
                $where[] = 'recorded_at <= :date_to';
                $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
            }

            $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
            $limit = isset($filters['limit']) ? (int)$filters['limit'] : 100;

            $sql = "
                SELECT *
                FROM openrouter_metrics
                {$whereClause}
                ORDER BY recorded_at DESC
                LIMIT {$limit}
            ";

            $results = $this->metricsDb->query($sql, $params);

            $this->logDebug('Получены детальные метрики из БД', [
                'filters' => $filters,
                'count' => count($results),
            ]);

            return $results;

        } catch (Exception $e) {
            $this->logError('Ошибка получения детальных метрик из БД', [
                'error' => $e->getMessage(),
                'filters' => $filters,
            ]);
            return [];
        }
    }

    /**
     * Устанавливает БД для записи метрик
     *
     * @param \App\Component\MySQL $db Экземпляр MySQL для записи метрик
     */
    protected function setMetricsDb(\App\Component\MySQL $db): void
    {
        $this->metricsDb = $db;
    }
}

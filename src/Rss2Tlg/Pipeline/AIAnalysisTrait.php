<?php

declare(strict_types=1);

namespace App\Rss2Tlg\Pipeline;

use App\Component\OpenRouter;
use DateTimeImmutable;
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
     * Получает сводную статистику по метрикам OpenRouter за указанный период
     *
     * @param string $periodType Тип периода: day, week, month, custom
     * @param string|null $dateFrom Дата начала (используется для periodType=custom или в качестве опорной даты)
     * @param string|null $dateTo Дата окончания (используется для periodType=custom)
     * @param string|null $pipelineModule Фильтр по модулю pipeline
     * @return array<string, mixed> Сводная статистика по запросам и моделям
     */
    protected function getSummaryByPeriod(
        string $periodType,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $pipelineModule = null
    ): array {
        if ($this->metricsDb === null) {
            $this->logWarning('Metrics DB не инициализирована, возвращаем пустую сводку по периодам');
            return [];
        }

        try {
            $range = $this->resolvePeriodRange($periodType, $dateFrom, $dateTo);

            $params = [
                ':date_from' => $range['start'],
                ':date_to' => $range['end'],
                ':pipeline_module' => $pipelineModule,
            ];

            $baseSql = "
                SELECT
                    COUNT(*) AS total_requests,
                    COALESCE(SUM(COALESCE(tokens_prompt, 0) + COALESCE(tokens_completion, 0)), 0) AS total_tokens,
                    COALESCE(SUM(usage_total), 0) AS total_cost,
                    AVG(generation_time) AS avg_generation_time,
                    AVG(latency) AS avg_latency
                FROM openrouter_metrics
                WHERE recorded_at BETWEEN :date_from AND :date_to
                    AND (:pipeline_module IS NULL OR pipeline_module = :pipeline_module)
            ";

            $baseStats = $this->metricsDb->queryOne($baseSql, $params) ?? [
                'total_requests' => 0,
                'total_tokens' => 0,
                'total_cost' => 0.0,
                'avg_generation_time' => null,
                'avg_latency' => null,
            ];

            $modelsSql = "
                SELECT
                    model,
                    COUNT(*) AS requests,
                    COALESCE(SUM(usage_total), 0) AS cost,
                    COALESCE(SUM(COALESCE(tokens_prompt, 0) + COALESCE(tokens_completion, 0)), 0) AS tokens
                FROM openrouter_metrics
                WHERE recorded_at BETWEEN :date_from AND :date_to
                    AND (:pipeline_module IS NULL OR pipeline_module = :pipeline_module)
                GROUP BY model
                ORDER BY cost DESC
            ";

            $modelsRaw = $this->metricsDb->query($modelsSql, $params);
            $models = [];
            foreach ($modelsRaw as $row) {
                $modelName = (string)$row['model'];
                $models[$modelName] = [
                    'requests' => (int)($row['requests'] ?? 0),
                    'cost' => isset($row['cost']) ? (float)$row['cost'] : 0.0,
                    'tokens' => (int)($row['tokens'] ?? 0),
                ];
            }

            $pipelineSql = "
                SELECT
                    COALESCE(pipeline_module, 'unknown') AS module_name,
                    COUNT(*) AS requests,
                    COALESCE(SUM(usage_total), 0) AS cost
                FROM openrouter_metrics
                WHERE recorded_at BETWEEN :date_from AND :date_to
                    AND (:pipeline_module IS NULL OR pipeline_module = :pipeline_module)
                GROUP BY module_name
                ORDER BY cost DESC
            ";

            $pipelineRaw = $this->metricsDb->query($pipelineSql, $params);
            $pipelineSummary = [];
            foreach ($pipelineRaw as $row) {
                $moduleName = (string)$row['module_name'];
                $pipelineSummary[$moduleName] = [
                    'requests' => (int)($row['requests'] ?? 0),
                    'cost' => isset($row['cost']) ? (float)$row['cost'] : 0.0,
                ];
            }

            $avgGenerationTime = $baseStats['avg_generation_time'] ?? null;
            $avgLatency = $baseStats['avg_latency'] ?? null;

            $summary = [
                'period' => $range['label'],
                'date_from' => $range['start'],
                'date_to' => $range['end'],
                'total_requests' => (int)($baseStats['total_requests'] ?? 0),
                'total_cost' => isset($baseStats['total_cost']) ? (float)$baseStats['total_cost'] : 0.0,
                'total_tokens' => (int)($baseStats['total_tokens'] ?? 0),
                'avg_generation_time' => $avgGenerationTime !== null ? (float)round((float)$avgGenerationTime, 2) : null,
                'avg_latency' => $avgLatency !== null ? (float)round((float)$avgLatency, 2) : null,
                'models' => $models,
                'pipeline_modules' => $pipelineSummary,
            ];

            $this->logDebug('Сформирована сводка метрик за период', [
                'period_type' => $periodType,
                'period' => $summary['period'],
                'pipeline_module' => $pipelineModule,
                'total_requests' => $summary['total_requests'],
            ]);

            return $summary;
        } catch (Exception $e) {
            $this->logError('Ошибка формирования сводки метрик за период', [
                'period_type' => $periodType,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'pipeline_module' => $pipelineModule,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Получает детальную статистику по моделям OpenRouter
     *
     * @param string|null $dateFrom Дата начала выборки (формат YYYY-MM-DD или YYYY-MM-DD HH:MM:SS)
     * @param string|null $dateTo Дата окончания выборки (формат YYYY-MM-DD или YYYY-MM-DD HH:MM:SS)
     * @param string|null $pipelineModule Фильтр по модулю pipeline
     * @return array<string, array<string, float|int|null>> Ассоциативный массив статистики по моделям
     */
    protected function getSummaryByModel(
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $pipelineModule = null
    ): array {
        if ($this->metricsDb === null) {
            $this->logWarning('Metrics DB не инициализирована, возвращаем пустую сводку по моделям');
            return [];
        }

        try {
            $bounds = $this->resolveDateBounds($dateFrom, $dateTo);

            $params = [
                ':date_from' => $bounds['start'],
                ':date_to' => $bounds['end'],
                ':pipeline_module' => $pipelineModule,
            ];

            $sql = "
                SELECT
                    model,
                    COUNT(*) AS total_requests,
                    COALESCE(SUM(usage_total), 0) AS total_cost,
                    COALESCE(SUM(COALESCE(tokens_prompt, 0) + COALESCE(tokens_completion, 0)), 0) AS total_tokens,
                    AVG(generation_time) AS avg_generation_time,
                    AVG(COALESCE(tokens_prompt, 0) + COALESCE(tokens_completion, 0)) AS avg_tokens_per_request,
                    AVG(usage_total) AS avg_cost_per_request,
                    SUM(CASE WHEN COALESCE(native_tokens_cached, 0) > 0 THEN 1 ELSE 0 END) AS cache_hits,
                    AVG(CASE WHEN COALESCE(native_tokens_cached, 0) > 0 THEN 1 ELSE 0 END) AS cache_rate
                FROM openrouter_metrics
                WHERE (:date_from IS NULL OR recorded_at >= :date_from)
                    AND (:date_to IS NULL OR recorded_at <= :date_to)
                    AND (:pipeline_module IS NULL OR pipeline_module = :pipeline_module)
                GROUP BY model
                ORDER BY total_cost DESC
            ";

            $rows = $this->metricsDb->query($sql, $params);
            $summary = [];

            foreach ($rows as $row) {
                $model = (string)$row['model'];
                $summary[$model] = [
                    'total_requests' => (int)($row['total_requests'] ?? 0),
                    'total_cost' => isset($row['total_cost']) ? (float)$row['total_cost'] : 0.0,
                    'total_tokens' => (int)($row['total_tokens'] ?? 0),
                    'avg_generation_time' => isset($row['avg_generation_time']) && $row['avg_generation_time'] !== null
                        ? (float)round((float)$row['avg_generation_time'], 2)
                        : null,
                    'avg_tokens_per_request' => isset($row['avg_tokens_per_request']) && $row['avg_tokens_per_request'] !== null
                        ? (float)round((float)$row['avg_tokens_per_request'], 2)
                        : null,
                    'avg_cost_per_request' => isset($row['avg_cost_per_request']) && $row['avg_cost_per_request'] !== null
                        ? (float)round((float)$row['avg_cost_per_request'], 6)
                        : null,
                    'cache_hits' => (int)($row['cache_hits'] ?? 0),
                    'cache_rate' => isset($row['cache_rate']) && $row['cache_rate'] !== null
                        ? (float)round((float)$row['cache_rate'], 4)
                        : null,
                ];
            }

            $this->logDebug('Сформирована сводка метрик по моделям', [
                'date_from' => $bounds['start'],
                'date_to' => $bounds['end'],
                'pipeline_module' => $pipelineModule,
                'models' => count($summary),
            ]);

            return $summary;
        } catch (Exception $e) {
            $this->logError('Ошибка формирования сводки метрик по моделям', [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'pipeline_module' => $pipelineModule,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Формирует аналитику эффективности кеширования запросов OpenRouter
     *
     * @param string|null $dateFrom Дата начала периода (YYYY-MM-DD или YYYY-MM-DD HH:MM:SS)
     * @param string|null $dateTo Дата окончания периода (YYYY-MM-DD или YYYY-MM-DD HH:MM:SS)
     * @param string|null $model Фильтр по конкретной модели OpenRouter
     * @return array<string, mixed> Структурированная аналитика кеша
     */
    protected function getCacheAnalytics(
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $model = null
    ): array {
        if ($this->metricsDb === null) {
            $this->logWarning('Metrics DB не инициализирована, возвращаем пустую аналитику кеша');
            return [];
        }

        try {
            $bounds = $this->resolveDateBounds($dateFrom, $dateTo);

            $params = [
                ':date_from' => $bounds['start'],
                ':date_to' => $bounds['end'],
                ':model' => $model,
            ];

            $baseSql = "
                SELECT
                    COUNT(*) AS total_requests,
                    SUM(CASE WHEN COALESCE(native_tokens_cached, 0) > 0 THEN 1 ELSE 0 END) AS requests_with_cache,
                    AVG(CASE WHEN COALESCE(native_tokens_cached, 0) > 0 THEN 1 ELSE 0 END) AS cache_hit_rate,
                    COALESCE(SUM(COALESCE(tokens_prompt, 0)), 0) AS total_prompt,
                    COALESCE(SUM(COALESCE(native_tokens_cached, 0)), 0) AS total_cached,
                    COALESCE(SUM(usage_total), 0) AS total_cost,
                    COALESCE(SUM(usage_cache), 0) AS cache_cost_saved
                FROM openrouter_metrics
                WHERE (:date_from IS NULL OR recorded_at >= :date_from)
                    AND (:date_to IS NULL OR recorded_at <= :date_to)
                    AND (:model IS NULL OR model = :model)
            ";

            $baseStats = $this->metricsDb->queryOne($baseSql, $params) ?? [
                'total_requests' => 0,
                'requests_with_cache' => 0,
                'cache_hit_rate' => 0.0,
                'total_prompt' => 0,
                'total_cached' => 0,
                'total_cost' => 0.0,
                'cache_cost_saved' => 0.0,
            ];

            $totalRequests = (int)($baseStats['total_requests'] ?? 0);
            $requestsWithCache = (int)($baseStats['requests_with_cache'] ?? 0);
            $cacheHitRateRaw = $baseStats['cache_hit_rate'] ?? null;
            $cacheHitRate = $cacheHitRateRaw !== null ? (float)round((float)$cacheHitRateRaw, 4) : 0.0;
            $totalPromptTokens = (int)($baseStats['total_prompt'] ?? 0);
            $totalCachedTokens = (int)($baseStats['total_cached'] ?? 0);
            $totalCost = isset($baseStats['total_cost']) ? (float)$baseStats['total_cost'] : 0.0;
            $cacheSavings = isset($baseStats['cache_cost_saved']) ? (float)$baseStats['cache_cost_saved'] : 0.0;

            $cacheTokenPercentage = $totalPromptTokens > 0
                ? (float)round($totalCachedTokens / $totalPromptTokens, 4)
                : 0.0;

            $costWithoutCache = $totalCost + $cacheSavings;
            $savingsPercentage = $costWithoutCache > 0.0
                ? (float)round($cacheSavings / $costWithoutCache, 4)
                : 0.0;

            $byModelSql = "
                SELECT
                    model,
                    COUNT(*) AS total_requests,
                    SUM(CASE WHEN COALESCE(native_tokens_cached, 0) > 0 THEN 1 ELSE 0 END) AS requests_with_cache,
                    AVG(CASE WHEN COALESCE(native_tokens_cached, 0) > 0 THEN 1 ELSE 0 END) AS cache_hit_rate,
                    COALESCE(SUM(COALESCE(native_tokens_cached, 0)), 0) AS tokens_cached,
                    COALESCE(SUM(COALESCE(tokens_prompt, 0)), 0) AS tokens_prompt,
                    COALESCE(SUM(usage_cache), 0) AS cost_savings
                FROM openrouter_metrics
                WHERE (:date_from IS NULL OR recorded_at >= :date_from)
                    AND (:date_to IS NULL OR recorded_at <= :date_to)
                    AND (:model IS NULL OR model = :model)
                GROUP BY model
                ORDER BY cache_hit_rate DESC, model ASC
            ";

            $byModelRows = $this->metricsDb->query($byModelSql, $params);
            $byModel = [];

            foreach ($byModelRows as $row) {
                $modelName = (string)$row['model'];
                $modelTotalRequests = (int)($row['total_requests'] ?? 0);
                $modelRequestsWithCache = (int)($row['requests_with_cache'] ?? 0);
                $modelCacheRateRaw = $row['cache_hit_rate'] ?? null;
                $modelCacheRate = $modelCacheRateRaw !== null ? (float)round((float)$modelCacheRateRaw, 4) : 0.0;
                $modelPromptTokens = (int)($row['tokens_prompt'] ?? 0);
                $modelCachedTokens = (int)($row['tokens_cached'] ?? 0);
                $modelCacheTokenPercentage = $modelPromptTokens > 0
                    ? (float)round($modelCachedTokens / $modelPromptTokens, 4)
                    : 0.0;
                $modelCostSavings = isset($row['cost_savings']) ? (float)$row['cost_savings'] : 0.0;

                $byModel[$modelName] = [
                    'total_requests' => $modelTotalRequests,
                    'requests_with_cache' => $modelRequestsWithCache,
                    'cache_hit_rate' => $modelCacheRate,
                    'tokens_prompt' => $modelPromptTokens,
                    'tokens_cached' => $modelCachedTokens,
                    'cache_tokens_percentage' => $modelCacheTokenPercentage,
                    'cost_savings' => (float)round($modelCostSavings, 6),
                ];
            }

            $analytics = [
                'date_from' => $bounds['start'],
                'date_to' => $bounds['end'],
                'model' => $model,
                'total_requests' => $totalRequests,
                'requests_with_cache' => $requestsWithCache,
                'cache_hit_rate' => $cacheHitRate,
                'tokens' => [
                    'total_prompt' => $totalPromptTokens,
                    'total_cached' => $totalCachedTokens,
                    'cache_percentage' => $cacheTokenPercentage,
                ],
                'cost_savings' => [
                    'total_cost' => (float)round($totalCost, 6),
                    'cost_without_cache' => (float)round($costWithoutCache, 6),
                    'savings' => (float)round($cacheSavings, 6),
                    'savings_percentage' => $savingsPercentage,
                ],
                'by_model' => $byModel,
            ];

            $this->logDebug('Сформирована аналитика кеша OpenRouter', [
                'date_from' => $bounds['start'],
                'date_to' => $bounds['end'],
                'model' => $model,
                'total_requests' => $totalRequests,
                'cache_hit_rate' => $cacheHitRate,
                'total_cache_savings' => $analytics['cost_savings']['savings'],
            ]);

            return $analytics;
        } catch (Exception $e) {
            $this->logError('Ошибка формирования аналитики кеша OpenRouter', [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'model' => $model,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Генерирует детальный отчет по метрикам OpenRouter в указанном формате
     *
     * @param array<string, mixed> $filters Фильтры выборки (model, pipeline_module, generation_id, batch_id, provider_name, finish_reason, date_from, date_to)
     * @param string $format Формат отчета: array, json, csv
     * @param string|null $outputFile Путь для сохранения отчета (только для json/csv)
     * @return array<string, mixed>|string Детальный отчет в выбранном формате
     */
    protected function getDetailReport(array $filters, string $format, ?string $outputFile = null): array|string
    {
        $normalizedFormat = strtolower(trim($format));
        if (!in_array($normalizedFormat, ['array', 'json', 'csv'], true)) {
            $this->logError('Неподдерживаемый формат детального отчета', [
                'format' => $format,
            ]);
            return [];
        }

        $failureResult = $normalizedFormat === 'array' ? [] : '';

        if ($this->metricsDb === null) {
            $this->logWarning('Metrics DB не инициализирована, возвращаем пустой детальный отчет');
            return $failureResult;
        }

        if ($normalizedFormat === 'array' && $outputFile !== null) {
            $this->logWarning('Формат array не поддерживает сохранение в файл, параметр outputFile будет проигнорирован', [
                'output_file' => $outputFile,
            ]);
        }

        try {
            $bounds = $this->resolveDateBounds(
                isset($filters['date_from']) ? (string)$filters['date_from'] : null,
                isset($filters['date_to']) ? (string)$filters['date_to'] : null
            );

            $conditions = [];
            $params = [];

            if ($bounds['start'] !== null) {
                $conditions[] = 'recorded_at >= :date_from';
                $params[':date_from'] = $bounds['start'];
            }

            if ($bounds['end'] !== null) {
                $conditions[] = 'recorded_at <= :date_to';
                $params[':date_to'] = $bounds['end'];
            }

            $filterMap = [
                'model' => 'model',
                'pipeline_module' => 'pipeline_module',
                'generation_id' => 'generation_id',
                'batch_id' => 'batch_id',
                'provider_name' => 'provider_name',
                'finish_reason' => 'finish_reason',
                'task_context' => 'task_context',
            ];

            foreach ($filterMap as $filterKey => $column) {
                if (isset($filters[$filterKey]) && $filters[$filterKey] !== '') {
                    $paramName = ':' . $filterKey;
                    $conditions[] = sprintf('%s = %s', $column, $paramName);
                    $params[$paramName] = $filters[$filterKey];
                }
            }

            $whereClause = count($conditions) > 0 ? 'WHERE ' . implode(' AND ', $conditions) : '';

            $sql = "
                SELECT
                    recorded_at,
                    generation_id,
                    model,
                    provider_name,
                    pipeline_module,
                    tokens_prompt,
                    tokens_completion,
                    native_tokens_cached,
                    generation_time,
                    latency,
                    usage_total,
                    usage_cache,
                    usage_data,
                    usage_file,
                    finish_reason
                FROM openrouter_metrics
                {$whereClause}
                ORDER BY recorded_at DESC
            ";

            $rows = $this->metricsDb->query($sql, $params);

            $totalTokens = 0;
            $totalCachedTokens = 0;
            $totalCost = 0.0;
            $totalCacheSavings = 0.0;
            $cacheHits = 0;
            $details = [];

            foreach ($rows as $row) {
                $tokensPrompt = (int)($row['tokens_prompt'] ?? 0);
                $tokensCompletion = (int)($row['tokens_completion'] ?? 0);
                $tokensCached = (int)($row['native_tokens_cached'] ?? 0);
                $generationTime = $row['generation_time'] !== null ? (float)$row['generation_time'] : null;
                $latency = $row['latency'] !== null ? (float)$row['latency'] : null;
                $cost = isset($row['usage_total']) ? (float)$row['usage_total'] : 0.0;
                $cacheSaved = isset($row['usage_cache']) ? (float)$row['usage_cache'] : 0.0;
                $dataCost = isset($row['usage_data']) ? (float)$row['usage_data'] : 0.0;
                $fileCost = isset($row['usage_file']) ? (float)$row['usage_file'] : 0.0;

                $totalTokens += $tokensPrompt + $tokensCompletion;
                $totalCachedTokens += $tokensCached;
                $totalCost += $cost;
                $totalCacheSavings += $cacheSaved;

                if ($tokensCached > 0) {
                    $cacheHits++;
                }

                $details[] = [
                    'date' => (string)$row['recorded_at'],
                    'generation_id' => $row['generation_id'],
                    'model' => $row['model'],
                    'provider' => $row['provider_name'],
                    'pipeline_module' => $row['pipeline_module'],
                    'tokens_prompt' => $tokensPrompt,
                    'tokens_completion' => $tokensCompletion,
                    'tokens_cached' => $tokensCached,
                    'generation_time' => $generationTime,
                    'latency' => $latency,
                    'cost' => (float)round($cost, 6),
                    'cache_saved' => (float)round($cacheSaved, 6),
                    'data_cost' => (float)round($dataCost, 6),
                    'file_cost' => (float)round($fileCost, 6),
                    'finish_reason' => $row['finish_reason'],
                ];
            }

            $totalRequests = count($details);
            $cacheHitRate = $totalRequests > 0
                ? (float)round($cacheHits / $totalRequests, 4)
                : 0.0;

            $normalizedFilters = array_filter([
                'date_from' => $bounds['start'],
                'date_to' => $bounds['end'],
                'model' => $filters['model'] ?? null,
                'pipeline_module' => $filters['pipeline_module'] ?? null,
                'generation_id' => $filters['generation_id'] ?? null,
                'batch_id' => $filters['batch_id'] ?? null,
                'provider_name' => $filters['provider_name'] ?? null,
                'finish_reason' => $filters['finish_reason'] ?? null,
                'task_context' => $filters['task_context'] ?? null,
            ], static fn($value) => $value !== null && $value !== '');

            $report = [
                'report_generated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                'filters' => $normalizedFilters,
                'summary' => [
                    'total_requests' => $totalRequests,
                    'total_cost' => (float)round($totalCost, 6),
                    'total_tokens' => $totalTokens,
                    'total_cached_tokens' => $totalCachedTokens,
                    'total_cache_savings' => (float)round($totalCacheSavings, 6),
                    'cache_hit_rate' => $cacheHitRate,
                ],
                'details' => $details,
            ];

            switch ($normalizedFormat) {
                case 'array':
                    $this->logDebug('Сформирован детальный отчет OpenRouter (array)', [
                        'filters' => $normalizedFilters,
                        'records' => $totalRequests,
                    ]);
                    return $report;

                case 'json':
                    $json = json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    if ($json === false) {
                        throw new Exception('Не удалось сериализовать отчет в JSON');
                    }

                    if ($outputFile !== null) {
                        $fileHandle = fopen($outputFile, 'wb');
                        if ($fileHandle === false) {
                            throw new Exception(sprintf('Не удалось открыть файл "%s" для записи JSON отчета', $outputFile));
                        }
                        if (fwrite($fileHandle, $json) === false) {
                            fclose($fileHandle);
                            throw new Exception(sprintf('Не удалось записать JSON отчет в файл "%s"', $outputFile));
                        }
                        fclose($fileHandle);

                        $this->logInfo('JSON отчет OpenRouter сохранен в файл', [
                            'path' => $outputFile,
                            'records' => $totalRequests,
                        ]);
                    }

                    $this->logDebug('Сформирован детальный отчет OpenRouter (json)', [
                        'filters' => $normalizedFilters,
                        'records' => $totalRequests,
                    ]);

                    return $json;

                case 'csv':
                    $csvHandle = fopen('php://temp', 'r+');
                    if ($csvHandle === false) {
                        throw new Exception('Не удалось создать временный поток для CSV отчета');
                    }

                    $header = [
                        'Date',
                        'Generation ID',
                        'Model',
                        'Provider',
                        'Pipeline Module',
                        'Tokens Prompt',
                        'Tokens Completion',
                        'Tokens Cached',
                        'Generation Time (ms)',
                        'Latency (ms)',
                        'Cost (USD)',
                        'Cache Saved (USD)',
                        'Data Cost (USD)',
                        'File Cost (USD)',
                        'Finish Reason',
                    ];

                    if (fputcsv($csvHandle, $header) === false) {
                        fclose($csvHandle);
                        throw new Exception('Не удалось записать заголовок CSV отчета');
                    }

                    foreach ($details as $row) {
                        $csvRow = [
                            $row['date'],
                            $row['generation_id'],
                            $row['model'],
                            $row['provider'],
                            $row['pipeline_module'],
                            $row['tokens_prompt'],
                            $row['tokens_completion'],
                            $row['tokens_cached'],
                            $row['generation_time'],
                            $row['latency'],
                            $row['cost'],
                            $row['cache_saved'],
                            $row['data_cost'],
                            $row['file_cost'],
                            $row['finish_reason'],
                        ];

                        if (fputcsv($csvHandle, $csvRow) === false) {
                            fclose($csvHandle);
                            throw new Exception('Не удалось записать строку CSV отчета');
                        }
                    }

                    rewind($csvHandle);
                    $csvContent = stream_get_contents($csvHandle);
                    fclose($csvHandle);

                    if ($csvContent === false) {
                        throw new Exception('Не удалось прочитать CSV отчет из временного потока');
                    }

                    if ($outputFile !== null) {
                        $fileHandle = fopen($outputFile, 'wb');
                        if ($fileHandle === false) {
                            throw new Exception(sprintf('Не удалось открыть файл "%s" для записи CSV отчета', $outputFile));
                        }
                        if (fwrite($fileHandle, $csvContent) === false) {
                            fclose($fileHandle);
                            throw new Exception(sprintf('Не удалось записать CSV отчет в файл "%s"', $outputFile));
                        }
                        fclose($fileHandle);

                        $this->logInfo('CSV отчет OpenRouter сохранен в файл', [
                            'path' => $outputFile,
                            'records' => $totalRequests,
                        ]);
                    }

                    $this->logDebug('Сформирован детальный отчет OpenRouter (csv)', [
                        'filters' => $normalizedFilters,
                        'records' => $totalRequests,
                    ]);

                    return $csvContent;
            }

            return $failureResult;
        } catch (Exception $e) {
            $this->logError('Ошибка генерации детального отчета OpenRouter', [
                'filters' => $filters,
                'format' => $format,
                'error' => $e->getMessage(),
            ]);

            return $failureResult;
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

    /**
     * Определяет временные границы периода для агрегации метрик
     *
     * @param string $periodType Тип периода
     * @param string|null $dateFrom Опорная дата или начало периода
     * @param string|null $dateTo Конечная дата (для кастомного периода)
     * @return array{start: string, end: string, label: string} Подготовленные временные границы
     *
     * @throws Exception При некорректных параметрах периода
     */
    private function resolvePeriodRange(string $periodType, ?string $dateFrom, ?string $dateTo): array
    {
        $normalizedType = strtolower($periodType);

        switch ($normalizedType) {
            case 'day':
                $reference = $this->createDateTime($dateFrom ?? 'now', false);
                $start = $reference->setTime(0, 0, 0);
                $end = $reference->setTime(23, 59, 59);
                break;

            case 'week':
                $reference = $this->createDateTime($dateFrom ?? 'now', false);
                $start = $reference->modify('monday this week')->setTime(0, 0, 0);
                $end = $start->modify('+6 days')->setTime(23, 59, 59);
                break;

            case 'month':
                $reference = $this->createDateTime($dateFrom ?? 'now', false);
                $start = $reference->modify('first day of this month')->setTime(0, 0, 0);
                $end = $start->modify('last day of this month')->setTime(23, 59, 59);
                break;

            case 'custom':
                if ($dateFrom === null || $dateTo === null) {
                    throw new Exception('Для кастомного периода необходимо указать даты начала и окончания');
                }

                $start = $this->createDateTime($dateFrom, false);
                $end = $this->createDateTime($dateTo, true);

                if ($end < $start) {
                    throw new Exception('Дата окончания периода не может быть меньше даты начала');
                }
                break;

            default:
                throw new Exception(sprintf('Неподдерживаемый тип периода: %s', $periodType));
        }

        return [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
            'label' => sprintf('%s - %s', $start->format('Y-m-d'), $end->format('Y-m-d')),
        ];
    }

    /**
     * Приводит произвольные границы дат к формату, пригодному для SQL запросов
     *
     * @param string|null $dateFrom Дата начала периода
     * @param string|null $dateTo Дата окончания периода
     * @return array{start: string|null, end: string|null} Нормализованные границы периода
     *
     * @throws Exception При некорректных датах
     */
    private function resolveDateBounds(?string $dateFrom, ?string $dateTo): array
    {
        $startDate = null;
        $endDate = null;

        if ($dateFrom !== null) {
            $startDate = $this->createDateTime($dateFrom, false);
        }

        if ($dateTo !== null) {
            $endDate = $this->createDateTime($dateTo, true);
        }

        if ($startDate !== null && $endDate !== null && $endDate < $startDate) {
            throw new Exception('Дата окончания периода не может быть меньше даты начала');
        }

        return [
            'start' => $startDate?->format('Y-m-d H:i:s'),
            'end' => $endDate?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Создает объект даты/времени с учетом необходимости установки начала или конца дня
     *
     * @param string $input Входная дата или дата-время
     * @param bool $endOfDay Флаг установки времени в конец дня
     * @return DateTimeImmutable Нормализованная дата
     *
     * @throws Exception При ошибке парсинга даты
     */
    private function createDateTime(string $input, bool $endOfDay): DateTimeImmutable
    {
        try {
            $dateTime = new DateTimeImmutable($input);
        } catch (Exception $e) {
            throw new Exception(sprintf('Некорректная дата "%s": %s', $input, $e->getMessage()), (int)$e->getCode(), $e);
        }

        if (!preg_match('/\d{2}:\d{2}/', $input)) {
            $dateTime = $dateTime->setTime(0, 0, 0);
        }

        if ($endOfDay) {
            $dateTime = $dateTime->setTime(23, 59, 59);
        }

        return $dateTime;
    }
}

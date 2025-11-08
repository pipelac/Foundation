<?php

declare(strict_types=1);

namespace App\Rss2Tlg\Pipeline;

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\OpenRouter;
use App\Rss2Tlg\Exception\AI\AIAnalysisException;
use Exception;

/**
 * Сервис дедупликации новостей
 * 
 * Второй этап AI Pipeline:
 * - Проверка новостей на дубликаты
 * - Сравнение с существующими новостями за последние N дней
 * - Определение схожести через AI анализ
 * - Маркировка дубликатов и определение возможности публикации
 */
class DeduplicationService implements PipelineModuleInterface
{
    private MySQL $db;
    private OpenRouter $openRouter;
    private ?Logger $logger;
    private array $config;
    
    private array $metrics = [
        'total_processed' => 0,
        'successful' => 0,
        'failed' => 0,
        'skipped' => 0,
        'duplicates_found' => 0,
        'unique_items' => 0,
        'total_tokens' => 0,
        'total_time_ms' => 0,
        'total_comparisons' => 0,
        'model_attempts' => [],
    ];

    /**
     * Конструктор сервиса дедупликации
     *
     * @param MySQL $db Подключение к БД
     * @param OpenRouter $openRouter Клиент OpenRouter API
     * @param array<string, mixed> $config Конфигурация модуля:
     *   - enabled (bool): Включен ли модуль (default: true)
     *   - similarity_threshold (float): Порог схожести для дубликатов 0-100 (default: 70.0)
     *   - compare_last_n_days (int): Период сравнения в днях (default: 7)
     *   - max_comparisons (int): Максимум новостей для сравнения (default: 50)
     *   - models (array): Массив AI моделей в порядке приоритета
     *   - retry_count (int): Количество повторов при ошибке (default: 2)
     *   - timeout (int): Таймаут запроса в секундах (default: 120)
     *   - fallback_strategy (string): 'sequential'|'random' (default: 'sequential')
     *   - prompt_file (string): Путь к файлу с промптом
     * @param Logger|null $logger Логгер
     */
    public function __construct(
        MySQL $db,
        OpenRouter $openRouter,
        array $config,
        ?Logger $logger = null
    ) {
        $this->db = $db;
        $this->openRouter = $openRouter;
        $this->config = $this->validateConfig($config);
        $this->logger = $logger;
    }

    /**
     * Валидирует конфигурацию модуля
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     * @throws AIAnalysisException
     */
    private function validateConfig(array $config): array
    {
        if (empty($config['models']) || !is_array($config['models'])) {
            throw new AIAnalysisException('Не указаны AI модели для дедупликации');
        }

        if (empty($config['prompt_file']) || !file_exists($config['prompt_file'])) {
            throw new AIAnalysisException('Не указан или не найден файл промпта');
        }

        $similarityThreshold = (float)($config['similarity_threshold'] ?? 70.0);
        if ($similarityThreshold < 0 || $similarityThreshold > 100) {
            throw new AIAnalysisException('similarity_threshold должен быть между 0 и 100');
        }

        return [
            'enabled' => $config['enabled'] ?? true,
            'similarity_threshold' => $similarityThreshold,
            'compare_last_n_days' => max(1, (int)($config['compare_last_n_days'] ?? 7)),
            'max_comparisons' => max(10, (int)($config['max_comparisons'] ?? 50)),
            'models' => $config['models'],
            'retry_count' => max(0, (int)($config['retry_count'] ?? 2)),
            'timeout' => max(30, (int)($config['timeout'] ?? 120)),
            'fallback_strategy' => $config['fallback_strategy'] ?? 'sequential',
            'prompt_file' => $config['prompt_file'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function processItem(int $itemId): bool
    {
        if (!$this->config['enabled']) {
            $this->logDebug('Модуль дедупликации отключен', ['item_id' => $itemId]);
            return false;
        }

        $startTime = microtime(true);
        $this->metrics['total_processed']++;

        try {
            // Проверяем не обработана ли уже новость
            $existingStatus = $this->getStatus($itemId);
            if ($existingStatus === 'checked') {
                $this->logInfo('Новость уже проверена на дубликаты', ['item_id' => $itemId]);
                $this->metrics['skipped']++;
                return true;
            }

            // Получаем данные новости из суммаризации
            $itemData = $this->getSummarizationData($itemId);
            if (!$itemData) {
                throw new AIAnalysisException("Данные суммаризации для новости {$itemId} не найдены");
            }

            // Проверяем что суммаризация успешна
            if ($itemData['summarization_status'] !== 'success') {
                $this->logWarning('Суммаризация не завершена, пропускаем', [
                    'item_id' => $itemId,
                    'status' => $itemData['summarization_status'],
                ]);
                $this->updateStatus($itemId, $itemData['feed_id'], 'skipped');
                $this->metrics['skipped']++;
                return false;
            }

            // Обновляем статус на processing
            $this->updateStatus($itemId, $itemData['feed_id'], 'processing');

            // Получаем похожие новости для сравнения
            $similarItems = $this->getSimilarItems($itemId, $itemData);

            if (empty($similarItems)) {
                // Нет похожих новостей - точно не дубликат
                $this->saveDedupResult($itemId, $itemData['feed_id'], [
                    'is_duplicate' => false,
                    'can_be_published' => true,
                    'similarity_score' => 0.0,
                    'similarity_method' => 'ai',
                    'items_compared' => 0,
                ]);

                $this->metrics['successful']++;
                $this->metrics['unique_items']++;

                $this->logInfo('Похожих новостей не найдено - уникальна', ['item_id' => $itemId]);
                return true;
            }

            // Выполняем AI анализ дедупликации
            $dedupResult = $this->analyzeDeduplication($itemId, $itemData, $similarItems);

            if (!$dedupResult) {
                throw new AIAnalysisException("Не удалось получить результат дедупликации от AI");
            }

            // Сохраняем результат
            $this->saveDedupResult($itemId, $itemData['feed_id'], $dedupResult);

            $processingTime = (int)((microtime(true) - $startTime) * 1000);
            $this->metrics['successful']++;
            $this->metrics['total_time_ms'] += $processingTime;

            if ($dedupResult['is_duplicate']) {
                $this->metrics['duplicates_found']++;
            } else {
                $this->metrics['unique_items']++;
            }

            $this->logInfo('Дедупликация завершена', [
                'item_id' => $itemId,
                'is_duplicate' => $dedupResult['is_duplicate'],
                'similarity_score' => $dedupResult['similarity_score'],
                'processing_time_ms' => $processingTime,
            ]);

            return true;

        } catch (Exception $e) {
            $this->metrics['failed']++;
            
            $this->updateStatus($itemId, $itemData['feed_id'] ?? 0, 'failed', [
                'error_message' => $e->getMessage(),
                'error_code' => (string)$e->getCode(),
            ]);

            $this->logError('Ошибка дедупликации новости', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
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
                if ($existingStatus === 'checked') {
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
    public function getStatus(int $itemId): ?string
    {
        $query = "SELECT status FROM rss2tlg_deduplication WHERE item_id = :item_id LIMIT 1";
        $result = $this->db->queryOne($query, ['item_id' => $itemId]);
        
        return $result['status'] ?? null;
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
        $this->metrics = [
            'total_processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
            'duplicates_found' => 0,
            'unique_items' => 0,
            'total_tokens' => 0,
            'total_time_ms' => 0,
            'total_comparisons' => 0,
            'model_attempts' => [],
        ];
    }

    /**
     * Получает данные суммаризации новости
     *
     * @param int $itemId
     * @return array<string, mixed>|null
     */
    private function getSummarizationData(int $itemId): ?array
    {
        $query = "
            SELECT 
                s.item_id,
                s.feed_id,
                s.status as summarization_status,
                s.headline,
                s.summary,
                s.article_language,
                s.category_primary,
                s.importance_rating,
                s.dedup_canonical_entities,
                s.dedup_core_event,
                s.dedup_numeric_facts,
                i.title as original_title,
                i.link,
                i.pub_date
            FROM rss2tlg_summarization s
            INNER JOIN rss2tlg_items i ON s.item_id = i.id
            WHERE s.item_id = :item_id
            LIMIT 1
        ";
        
        return $this->db->queryOne($query, ['item_id' => $itemId]);
    }

    /**
     * Получает похожие новости для сравнения
     *
     * @param int $itemId Текущая новость
     * @param array<string, mixed> $itemData Данные текущей новости
     * @return array<array<string, mixed>>
     */
    private function getSimilarItems(int $itemId, array $itemData): array
    {
        $daysBack = $this->config['compare_last_n_days'];
        $maxComparisons = $this->config['max_comparisons'];
        
        // Ищем новости:
        // 1. Из того же или похожих источников
        // 2. За последние N дней
        // 3. С той же категорией или языком
        // 4. Успешно прошедшие суммаризацию
        // 5. Уже проверенные на дубликаты (не являющиеся дубликатами)
        
        $query = "
            SELECT 
                s.item_id,
                s.headline,
                s.summary,
                s.article_language,
                s.category_primary,
                s.dedup_canonical_entities,
                s.dedup_core_event,
                s.dedup_numeric_facts,
                i.pub_date
            FROM rss2tlg_summarization s
            INNER JOIN rss2tlg_items i ON s.item_id = i.id
            WHERE s.item_id != :item_id
              AND s.status = 'success'
              AND i.pub_date >= DATE_SUB(NOW(), INTERVAL :days_back DAY)
              AND (
                  s.category_primary = :category
                  OR s.article_language = :language
              )
            ORDER BY i.pub_date DESC
            LIMIT :max_limit
        ";
        
        $params = [
            'item_id' => $itemId,
            'days_back' => $daysBack,
            'category' => $itemData['category_primary'] ?? '',
            'language' => $itemData['article_language'] ?? 'en',
            'max_limit' => $maxComparisons,
        ];
        
        $results = $this->db->query($query, $params);
        
        $this->logDebug('Найдено похожих новостей для сравнения', [
            'item_id' => $itemId,
            'count' => count($results),
        ]);
        
        return $results;
    }

    /**
     * Анализирует дедупликацию через AI
     *
     * @param int $itemId
     * @param array<string, mixed> $itemData
     * @param array<array<string, mixed>> $similarItems
     * @return array<string, mixed>|null
     */
    private function analyzeDeduplication(int $itemId, array $itemData, array $similarItems): ?array
    {
        $systemPrompt = $this->loadPrompt();
        $userPrompt = $this->prepareComparisonPrompt($itemData, $similarItems);

        // Выполняем анализ с fallback
        $result = $this->analyzeWithFallback($itemId, $systemPrompt, $userPrompt);

        if (!$result) {
            return null;
        }

        $analysisData = $result['analysis_data'];

        // Формируем финальный результат
        $isDuplicate = $analysisData['is_duplicate'] ?? false;
        $similarityScore = (float)($analysisData['similarity_score'] ?? 0.0);

        // Проверяем порог схожести
        if ($similarityScore >= $this->config['similarity_threshold']) {
            $isDuplicate = true;
        }

        return [
            'is_duplicate' => $isDuplicate,
            'duplicate_of_item_id' => $analysisData['duplicate_of_item_id'] ?? null,
            'similarity_score' => $similarityScore,
            'similarity_method' => 'ai',
            'can_be_published' => !$isDuplicate,
            'matched_entities' => json_encode($analysisData['matched_entities'] ?? []),
            'matched_events' => $analysisData['matched_events'] ?? null,
            'matched_facts' => json_encode($analysisData['matched_facts'] ?? []),
            'model_used' => $result['model_used'],
            'tokens_used' => $result['tokens_used'],
            'processing_time_ms' => $result['processing_time_ms'] ?? 0,
            'items_compared' => count($similarItems),
        ];
    }

    /**
     * Загружает промпт из файла
     *
     * @return string
     * @throws AIAnalysisException
     */
    private function loadPrompt(): string
    {
        $prompt = file_get_contents($this->config['prompt_file']);
        
        if ($prompt === false) {
            throw new AIAnalysisException('Не удалось загрузить промпт из файла');
        }

        return $prompt;
    }

    /**
     * Подготавливает промпт для сравнения
     *
     * @param array<string, mixed> $newItem
     * @param array<array<string, mixed>> $existingItems
     * @return string
     */
    private function prepareComparisonPrompt(array $newItem, array $existingItems): string
    {
        $prompt = "# NEW ARTICLE TO CHECK\n\n";
        $prompt .= "Item ID: {$newItem['item_id']}\n";
        $prompt .= "Headline: {$newItem['headline']}\n";
        $prompt .= "Summary: {$newItem['summary']}\n";
        $prompt .= "Language: {$newItem['article_language']}\n";
        $prompt .= "Category: {$newItem['category_primary']}\n";
        $prompt .= "Published: {$newItem['pub_date']}\n\n";
        
        if (!empty($newItem['dedup_canonical_entities'])) {
            $entities = json_decode($newItem['dedup_canonical_entities'], true);
            $prompt .= "Key Entities: " . implode(', ', $entities ?? []) . "\n";
        }
        
        if (!empty($newItem['dedup_core_event'])) {
            $prompt .= "Core Event: {$newItem['dedup_core_event']}\n";
        }
        
        if (!empty($newItem['dedup_numeric_facts'])) {
            $facts = json_decode($newItem['dedup_numeric_facts'], true);
            if ($facts) {
                $prompt .= "Key Facts: " . json_encode($facts) . "\n";
            }
        }

        $prompt .= "\n# EXISTING ARTICLES TO COMPARE WITH\n\n";

        foreach ($existingItems as $idx => $item) {
            $prompt .= "## Article " . ($idx + 1) . "\n";
            $prompt .= "Item ID: {$item['item_id']}\n";
            $prompt .= "Headline: {$item['headline']}\n";
            $prompt .= "Summary: {$item['summary']}\n";
            $prompt .= "Published: {$item['pub_date']}\n";
            
            if (!empty($item['dedup_canonical_entities'])) {
                $entities = json_decode($item['dedup_canonical_entities'], true);
                $prompt .= "Key Entities: " . implode(', ', $entities ?? []) . "\n";
            }
            
            if (!empty($item['dedup_core_event'])) {
                $prompt .= "Core Event: {$item['dedup_core_event']}\n";
            }
            
            $prompt .= "\n";
        }

        $prompt .= "\nAnalyze if the NEW ARTICLE is a duplicate of any EXISTING ARTICLE. Respond in JSON format.";

        return $prompt;
    }

    /**
     * Анализирует с использованием fallback моделей
     *
     * @param int $itemId
     * @param string $systemPrompt
     * @param string $userPrompt
     * @return array<string, mixed>|null
     */
    private function analyzeWithFallback(int $itemId, string $systemPrompt, string $userPrompt): ?array
    {
        $models = $this->config['models'];
        
        if ($this->config['fallback_strategy'] === 'random') {
            shuffle($models);
        }

        $lastError = null;

        foreach ($models as $modelConfig) {
            $modelName = is_array($modelConfig) ? ($modelConfig['model'] ?? '') : $modelConfig;
            $retryCount = $this->config['retry_count'];

            if (!isset($this->metrics['model_attempts'][$modelName])) {
                $this->metrics['model_attempts'][$modelName] = 0;
            }
            $this->metrics['model_attempts'][$modelName]++;

            $this->logDebug('Попытка дедупликации с моделью', [
                'item_id' => $itemId,
                'model' => $modelName,
            ]);

            for ($attempt = 0; $attempt <= $retryCount; $attempt++) {
                try {
                    $startTime = microtime(true);
                    $result = $this->callAI($modelName, $modelConfig, $systemPrompt, $userPrompt);
                    $processingTime = (int)((microtime(true) - $startTime) * 1000);
                    
                    if ($result) {
                        if (isset($result['tokens_used'])) {
                            $this->metrics['total_tokens'] += $result['tokens_used'];
                        }
                        
                        $result['model_used'] = $modelName;
                        $result['processing_time_ms'] = $processingTime;
                        
                        return $result;
                    }

                } catch (Exception $e) {
                    $lastError = $e->getMessage();
                    
                    $this->logWarning('Ошибка при вызове AI', [
                        'item_id' => $itemId,
                        'model' => $modelName,
                        'attempt' => $attempt + 1,
                        'error' => $lastError,
                    ]);

                    if ($attempt < $retryCount) {
                        sleep(1);
                    }
                }
            }
        }

        $this->logError('Все модели не смогли выполнить дедупликацию', [
            'item_id' => $itemId,
            'last_error' => $lastError,
        ]);

        return null;
    }

    /**
     * Вызывает AI модель для анализа
     *
     * @param string $model
     * @param array<string, mixed>|string $modelConfig
     * @param string $systemPrompt
     * @param string $userPrompt
     * @return array<string, mixed>|null
     * @throws Exception
     */
    private function callAI(string $model, $modelConfig, string $systemPrompt, string $userPrompt): ?array
    {
        $messages = [
            [
                'role' => 'system',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $systemPrompt,
                        'cache_control' => ['type' => 'ephemeral'],
                    ],
                ],
            ],
            [
                'role' => 'user',
                'content' => $userPrompt,
            ],
        ];

        // Извлекаем параметры модели из конфигурации
        $options = ['response_format' => ['type' => 'json_object']];
        
        if (is_array($modelConfig)) {
            if (isset($modelConfig['max_tokens'])) {
                $options['max_tokens'] = $modelConfig['max_tokens'];
            }
            if (isset($modelConfig['temperature'])) {
                $options['temperature'] = $modelConfig['temperature'];
            }
            if (isset($modelConfig['top_p'])) {
                $options['top_p'] = $modelConfig['top_p'];
            }
            if (isset($modelConfig['frequency_penalty'])) {
                $options['frequency_penalty'] = $modelConfig['frequency_penalty'];
            }
            if (isset($modelConfig['presence_penalty'])) {
                $options['presence_penalty'] = $modelConfig['presence_penalty'];
            }
        } else {
            // Дефолтные значения для обратной совместимости
            $options['max_tokens'] = 1000;
            $options['temperature'] = 0.1;
        }

        $response = $this->openRouter->chatWithMessages($model, $messages, $options);

        if (!$response || !isset($response['content'])) {
            return null;
        }

        $content = $response['content'];
        $analysisData = json_decode($content, true);

        if (!$analysisData) {
            throw new AIAnalysisException('Не удалось распарсить JSON ответ от AI');
        }

        $usage = $response['usage'] ?? [];
        
        return [
            'analysis_data' => $analysisData,
            'tokens_used' => $usage['total_tokens'] ?? 0,
            'tokens_prompt' => $usage['prompt_tokens'] ?? 0,
            'tokens_completion' => $usage['completion_tokens'] ?? 0,
        ];
    }

    /**
     * Обновляет статус проверки в БД
     *
     * @param int $itemId
     * @param int $feedId
     * @param string $status
     * @param array<string, mixed> $extraData
     */
    private function updateStatus(int $itemId, int $feedId, string $status, array $extraData = []): void
    {
        $params = [
            'item_id' => $itemId,
            'feed_id' => $feedId,
            'status' => $status,
        ];

        $updateParts = ['status = :status_update'];
        $params['status_update'] = $status;
        
        if (!empty($extraData)) {
            foreach ($extraData as $key => $value) {
                $paramKey = $key . '_update';
                $updateParts[] = "{$key} = :{$paramKey}";
                $params[$paramKey] = $value;
            }
        }
        
        $updateParts[] = 'updated_at = NOW()';
        
        $query = "
            INSERT INTO rss2tlg_deduplication (item_id, feed_id, status, created_at, updated_at)
            VALUES (:item_id, :feed_id, :status, NOW(), NOW())
            ON DUPLICATE KEY UPDATE " . implode(', ', $updateParts) . "
        ";

        $this->db->execute($query, $params);
    }

    /**
     * Сохраняет результат дедупликации в БД
     *
     * @param int $itemId
     * @param int $feedId
     * @param array<string, mixed> $result
     */
    private function saveDedupResult(int $itemId, int $feedId, array $result): void
    {
        $query = "
            INSERT INTO rss2tlg_deduplication (
                item_id,
                feed_id,
                status,
                is_duplicate,
                duplicate_of_item_id,
                similarity_score,
                similarity_method,
                can_be_published,
                matched_entities,
                matched_events,
                matched_facts,
                model_used,
                tokens_used,
                processing_time_ms,
                items_compared,
                checked_at,
                created_at,
                updated_at
            ) VALUES (
                :item_id,
                :feed_id,
                'checked',
                :is_duplicate,
                :duplicate_of_item_id,
                :similarity_score,
                :similarity_method,
                :can_be_published,
                :matched_entities,
                :matched_events,
                :matched_facts,
                :model_used,
                :tokens_used,
                :processing_time_ms,
                :items_compared,
                NOW(),
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                is_duplicate = VALUES(is_duplicate),
                duplicate_of_item_id = VALUES(duplicate_of_item_id),
                similarity_score = VALUES(similarity_score),
                similarity_method = VALUES(similarity_method),
                can_be_published = VALUES(can_be_published),
                matched_entities = VALUES(matched_entities),
                matched_events = VALUES(matched_events),
                matched_facts = VALUES(matched_facts),
                model_used = VALUES(model_used),
                tokens_used = VALUES(tokens_used),
                processing_time_ms = VALUES(processing_time_ms),
                items_compared = VALUES(items_compared),
                checked_at = NOW(),
                updated_at = NOW()
        ";

        $this->db->execute($query, [
            'item_id' => $itemId,
            'feed_id' => $feedId,
            'is_duplicate' => (int)($result['is_duplicate'] ?? false),
            'duplicate_of_item_id' => $result['duplicate_of_item_id'] ?? null,
            'similarity_score' => $result['similarity_score'] ?? 0.0,
            'similarity_method' => $result['similarity_method'] ?? 'ai',
            'can_be_published' => (int)($result['can_be_published'] ?? true),
            'matched_entities' => $result['matched_entities'] ?? null,
            'matched_events' => $result['matched_events'] ?? null,
            'matched_facts' => $result['matched_facts'] ?? null,
            'model_used' => $result['model_used'] ?? null,
            'tokens_used' => $result['tokens_used'] ?? 0,
            'processing_time_ms' => $result['processing_time_ms'] ?? 0,
            'items_compared' => $result['items_compared'] ?? 0,
        ]);
    }

    /**
     * Логирует сообщение уровня DEBUG
     *
     * @param string $message
     * @param array<string, mixed> $context
     */
    private function logDebug(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->debug('[DeduplicationService] ' . $message, $context);
        }
    }

    /**
     * Логирует информационное сообщение
     *
     * @param string $message
     * @param array<string, mixed> $context
     */
    private function logInfo(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->info('[DeduplicationService] ' . $message, $context);
        }
    }

    /**
     * Логирует предупреждение
     *
     * @param string $message
     * @param array<string, mixed> $context
     */
    private function logWarning(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->warning('[DeduplicationService] ' . $message, $context);
        }
    }

    /**
     * Логирует ошибку
     *
     * @param string $message
     * @param array<string, mixed> $context
     */
    private function logError(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->error('[DeduplicationService] ' . $message, $context);
        }
    }
}

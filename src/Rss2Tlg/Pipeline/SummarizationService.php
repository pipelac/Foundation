<?php

declare(strict_types=1);

namespace App\Rss2Tlg\Pipeline;

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\OpenRouter;
use App\Rss2Tlg\Exception\AI\AIAnalysisException;
use Exception;

/**
 * Сервис суммаризации и категоризации новостей
 * 
 * Первый этап AI Pipeline:
 * - Суммаризация полного текста новости
 * - Категоризация (основная + 2 дополнительные категории)
 * - Определение языка статьи
 * - Подготовка данных для дедупликации
 * - Оценка важности новости (1-20)
 */
class SummarizationService implements PipelineModuleInterface
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
        'total_tokens' => 0,
        'total_time_ms' => 0,
        'cache_hits' => 0,
        'model_attempts' => [],
    ];

    /**
     * Конструктор сервиса суммаризации
     *
     * @param MySQL $db Подключение к БД
     * @param OpenRouter $openRouter Клиент OpenRouter API
     * @param array<string, mixed> $config Конфигурация модуля:
     *   - enabled (bool): Включен ли модуль
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
            throw new AIAnalysisException('Не указаны AI модели для суммаризации');
        }

        if (empty($config['prompt_file']) || !file_exists($config['prompt_file'])) {
            throw new AIAnalysisException('Не указан или не найден файл промпта');
        }

        return [
            'enabled' => $config['enabled'] ?? true,
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
            $this->logDebug('Модуль суммаризации отключен', ['item_id' => $itemId]);
            return false;
        }

        $startTime = microtime(true);
        $this->metrics['total_processed']++;

        try {
            // Проверяем не обработана ли уже новость
            $existingStatus = $this->getStatus($itemId);
            if ($existingStatus === 'success') {
                $this->logInfo('Новость уже обработана', ['item_id' => $itemId]);
                $this->metrics['skipped']++;
                return true;
            }

            // Получаем данные новости
            $item = $this->getItem($itemId);
            if (!$item) {
                throw new AIAnalysisException("Новость с ID {$itemId} не найдена");
            }

            // Обновляем статус на processing
            $this->updateStatus($itemId, $item['feed_id'], 'processing');

            // Подготавливаем промпт
            $systemPrompt = $this->loadPrompt();
            $userPrompt = $this->prepareUserPrompt($item);

            // Получаем результат от AI с fallback
            $result = $this->analyzeWithFallback($itemId, $item['feed_id'], $systemPrompt, $userPrompt);

            if (!$result) {
                throw new AIAnalysisException("Не удалось получить результат от AI");
            }

            // Сохраняем результат
            $this->saveResult($itemId, $item['feed_id'], $result);

            $processingTime = (int)((microtime(true) - $startTime) * 1000);
            $this->metrics['successful']++;
            $this->metrics['total_time_ms'] += $processingTime;

            $this->logInfo('Новость успешно обработана', [
                'item_id' => $itemId,
                'processing_time_ms' => $processingTime,
            ]);

            return true;

        } catch (Exception $e) {
            $this->metrics['failed']++;
            
            $this->updateStatus($itemId, $item['feed_id'] ?? 0, 'failed', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ]);

            $this->logError('Ошибка обработки новости', [
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
                if ($existingStatus === 'success') {
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
        $query = "SELECT status FROM rss2tlg_summarization WHERE item_id = :item_id LIMIT 1";
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
            'total_tokens' => 0,
            'total_time_ms' => 0,
            'cache_hits' => 0,
            'model_attempts' => [],
        ];
    }

    /**
     * Получает данные новости из БД
     *
     * @param int $itemId
     * @return array<string, mixed>|null
     */
    private function getItem(int $itemId): ?array
    {
        $query = "
            SELECT id, feed_id, title, description, content, extracted_content, link, pub_date
            FROM rss2tlg_items
            WHERE id = :item_id
            LIMIT 1
        ";
        
        return $this->db->queryOne($query, ['item_id' => $itemId]);
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
     * Подготавливает пользовательский промпт с данными новости
     *
     * @param array<string, mixed> $item
     * @return string
     */
    private function prepareUserPrompt(array $item): string
    {
        $content = $item['extracted_content'] ?? $item['content'] ?? $item['description'] ?? '';
        $title = $item['title'] ?? '';
        
        $prompt = "Analyze the following article:\n\n";
        $prompt .= "Title: {$title}\n\n";
        $prompt .= "Content:\n{$content}\n\n";
        $prompt .= "Provide analysis in JSON format according to the schema.";
        
        return $prompt;
    }

    /**
     * Анализирует новость через AI с использованием fallback моделей
     *
     * @param int $itemId
     * @param int $feedId
     * @param string $systemPrompt
     * @param string $userPrompt
     * @return array<string, mixed>|null
     */
    private function analyzeWithFallback(
        int $itemId,
        int $feedId,
        string $systemPrompt,
        string $userPrompt
    ): ?array {
        $models = $this->config['models'];
        
        if ($this->config['fallback_strategy'] === 'random') {
            shuffle($models);
        }

        $lastError = null;

        foreach ($models as $modelConfig) {
            $modelName = $modelConfig['model'] ?? $modelConfig;
            $retryCount = $this->config['retry_count'];

            // Увеличиваем счетчик попыток для модели
            if (!isset($this->metrics['model_attempts'][$modelName])) {
                $this->metrics['model_attempts'][$modelName] = 0;
            }
            $this->metrics['model_attempts'][$modelName]++;

            $this->logDebug('Попытка анализа с моделью', [
                'item_id' => $itemId,
                'model' => $modelName,
            ]);

            for ($attempt = 0; $attempt <= $retryCount; $attempt++) {
                try {
                    $result = $this->callAI($modelName, $systemPrompt, $userPrompt);
                    
                    if ($result) {
                        // Обновляем метрики
                        if (isset($result['tokens_used'])) {
                            $this->metrics['total_tokens'] += $result['tokens_used'];
                        }
                        if (isset($result['cache_hit']) && $result['cache_hit']) {
                            $this->metrics['cache_hits']++;
                        }

                        $result['model_used'] = $modelName;
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
                        sleep(1); // Пауза перед повтором
                    }
                }
            }
        }

        $this->logError('Все модели не смогли обработать новость', [
            'item_id' => $itemId,
            'last_error' => $lastError,
        ]);

        return null;
    }

    /**
     * Вызывает AI модель для анализа
     *
     * @param string $model
     * @param string $systemPrompt
     * @param string $userPrompt
     * @return array<string, mixed>|null
     * @throws Exception
     */
    private function callAI(string $model, string $systemPrompt, string $userPrompt): ?array
    {
        // Формируем messages для chatWithMessages
        $messages = [
            [
                'role' => 'system',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $systemPrompt,
                        'cache_control' => ['type' => 'ephemeral'], // Для кеширования Claude
                    ],
                ],
            ],
            [
                'role' => 'user',
                'content' => $userPrompt,
            ],
        ];

        $options = [
            'temperature' => 0.3,
            'max_tokens' => 2000,
            'response_format' => ['type' => 'json_object'],
        ];

        $response = $this->openRouter->chatWithMessages($model, $messages, $options);

        if (!$response || !isset($response['content'])) {
            return null;
        }

        $content = $response['content'];
        $analysisData = json_decode($content, true);

        if (!$analysisData) {
            throw new AIAnalysisException('Не удалось распарсить JSON ответ от AI');
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
     * Обновляет статус обработки в БД
     *
     * @param int $itemId
     * @param int $feedId
     * @param string $status
     * @param array<string, mixed> $extraData
     */
    private function updateStatus(int $itemId, int $feedId, string $status, array $extraData = []): void
    {
        // Базовые параметры
        $params = [
            'item_id' => $itemId,
            'feed_id' => $feedId,
            'status' => $status,
        ];

        // Формируем UPDATE часть
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
            INSERT INTO rss2tlg_summarization (item_id, feed_id, status, created_at, updated_at)
            VALUES (:item_id, :feed_id, :status, NOW(), NOW())
            ON DUPLICATE KEY UPDATE " . implode(', ', $updateParts) . "
        ";

        $this->db->execute($query, $params);
    }

    /**
     * Сохраняет результат анализа в БД
     *
     * @param int $itemId
     * @param int $feedId
     * @param array<string, mixed> $result
     */
    private function saveResult(int $itemId, int $feedId, array $result): void
    {
        $analysisData = $result['analysis_data'];

        $query = "
            UPDATE rss2tlg_summarization
            SET
                status = 'success',
                article_language = :article_language,
                category_primary = :category_primary,
                category_secondary = :category_secondary,
                headline = :headline,
                summary = :summary,
                keywords = :keywords,
                importance_rating = :importance_rating,
                dedup_canonical_entities = :dedup_canonical_entities,
                dedup_core_event = :dedup_core_event,
                dedup_numeric_facts = :dedup_numeric_facts,
                model_used = :model_used,
                tokens_used = :tokens_used,
                tokens_prompt = :tokens_prompt,
                tokens_completion = :tokens_completion,
                tokens_cached = :tokens_cached,
                cache_hit = :cache_hit,
                processed_at = NOW(),
                updated_at = NOW()
            WHERE item_id = :item_id
        ";

        $params = [
            'item_id' => $itemId,
            'article_language' => $analysisData['article_language'] ?? null,
            'category_primary' => $analysisData['category']['primary'] ?? null,
            'category_secondary' => json_encode($analysisData['category']['secondary'] ?? []),
            'headline' => $analysisData['content']['headline'] ?? null,
            'summary' => $analysisData['content']['summary'] ?? null,
            'keywords' => json_encode($analysisData['content']['keywords'] ?? []),
            'importance_rating' => $analysisData['importance']['rating'] ?? null,
            'dedup_canonical_entities' => json_encode($analysisData['deduplication']['canonical_entities'] ?? []),
            'dedup_core_event' => $analysisData['deduplication']['core_event'] ?? null,
            'dedup_numeric_facts' => json_encode($analysisData['deduplication']['numeric_facts'] ?? []),
            'model_used' => $result['model_used'],
            'tokens_used' => $result['tokens_used'],
            'tokens_prompt' => $result['tokens_prompt'],
            'tokens_completion' => $result['tokens_completion'],
            'tokens_cached' => $result['tokens_cached'],
            'cache_hit' => $result['cache_hit'] ? 1 : 0,
        ];

        $this->db->execute($query, $params);
    }

    /**
     * Логирование debug
     */
    private function logDebug(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->debug("[Summarization] {$message}", $context);
        }
    }

    /**
     * Логирование info
     */
    private function logInfo(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->info("[Summarization] {$message}", $context);
        }
    }

    /**
     * Логирование warning
     */
    private function logWarning(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->warning("[Summarization] {$message}", $context);
        }
    }

    /**
     * Логирование error
     */
    private function logError(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->error("[Summarization] {$message}", $context);
        }
    }
}

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
 * 
 * @version 2.0 - Рефакторинг с использованием AbstractPipelineModule и AIAnalysisTrait
 */
class DeduplicationService extends AbstractPipelineModule
{
    use AIAnalysisTrait;

    /**
     * Конструктор сервиса дедупликации
     *
     * @param MySQL $db Подключение к БД
     * @param OpenRouter $openRouter Клиент OpenRouter API
     * @param array<string, mixed> $config Конфигурация модуля
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
        $this->logger = $logger;
        $this->config = $this->validateConfig($config);
        $this->metrics = $this->initializeMetrics();
    }

    /**
     * {@inheritdoc}
     */
    protected function getModuleName(): string
    {
        return 'Deduplication';
    }

    /**
     * {@inheritdoc}
     */
    protected function validateModuleConfig(array $config): array
    {
        $aiConfig = $this->validateAIConfig($config);

        $similarityThreshold = (float)($config['similarity_threshold'] ?? 70.0);
        if ($similarityThreshold < 0 || $similarityThreshold > 100) {
            throw new AIAnalysisException('similarity_threshold должен быть между 0 и 100');
        }

        return array_merge($aiConfig, [
            'similarity_threshold' => $similarityThreshold,
            'compare_last_n_days' => max(1, (int)($config['compare_last_n_days'] ?? 7)),
            'max_comparisons' => max(10, (int)($config['max_comparisons'] ?? 50)),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function initializeMetrics(): array
    {
        return [
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
     * {@inheritdoc}
     */
    public function processItem(int $itemId): bool
    {
        if (!$this->config['enabled']) {
            $this->logDebug('Модуль отключен', ['item_id' => $itemId]);
            return false;
        }

        $startTime = microtime(true);
        $this->incrementMetric('total_processed');

        try {
            // Проверяем не обработана ли уже новость
            $existingStatus = $this->getStatus($itemId);
            if ($existingStatus === 'checked') {
                $this->logInfo('Новость уже проверена на дубликаты', ['item_id' => $itemId]);
                $this->incrementMetric('skipped');
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
                $this->updateStatus($itemId, (int)$itemData['feed_id'], 'skipped');
                $this->incrementMetric('skipped');
                return false;
            }

            // Обновляем статус на processing
            $this->updateStatus($itemId, (int)$itemData['feed_id'], 'processing');

            // Получаем похожие новости для сравнения
            $similarItems = $this->getSimilarItems($itemId, $itemData);

            if (empty($similarItems)) {
                // Нет похожих новостей - точно не дубликат
                $this->saveDedupResult($itemId, (int)$itemData['feed_id'], [
                    'is_duplicate' => false,
                    'can_be_published' => true,
                    'similarity_score' => 0.0,
                    'similarity_method' => 'ai',
                    'items_compared' => 0,
                ]);

                $this->incrementMetric('successful');
                $this->incrementMetric('unique_items');

                $this->logInfo('Похожих новостей не найдено - уникальна', ['item_id' => $itemId]);
                return true;
            }

            // Выполняем AI анализ дедупликации
            $dedupResult = $this->analyzeDeduplication($itemId, $itemData, $similarItems);

            if (!$dedupResult) {
                throw new AIAnalysisException("Не удалось получить результат дедупликации от AI");
            }

            // Сохраняем результат
            $this->saveDedupResult($itemId, (int)$itemData['feed_id'], $dedupResult);

            $processingTime = $this->recordProcessingTime($startTime);
            $this->incrementMetric('successful');

            if ($dedupResult['is_duplicate']) {
                $this->incrementMetric('duplicates_found');
            } else {
                $this->incrementMetric('unique_items');
            }

            $this->logInfo('Дедупликация завершена', [
                'item_id' => $itemId,
                'is_duplicate' => $dedupResult['is_duplicate'],
                'similarity_score' => $dedupResult['similarity_score'],
                'processing_time_ms' => $processingTime,
            ]);

            return true;

        } catch (Exception $e) {
            $this->incrementMetric('failed');
            
            $feedId = isset($itemData) ? (int)($itemData['feed_id'] ?? 0) : 0;
            $this->updateStatus($itemId, $feedId, 'failed', [
                'error_message' => $e->getMessage(),
                'error_code' => (string)$e->getCode(),
            ]);

            $this->logError('Ошибка дедупликации новости', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
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
        $systemPrompt = $this->loadPromptFromFile($this->config['prompt_file']);
        $userPrompt = $this->prepareComparisonPrompt($itemData, $similarItems);

        // Выполняем анализ с fallback
        $result = $this->analyzeWithFallback($systemPrompt, $userPrompt);

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

        $this->incrementMetric('total_comparisons', count($similarItems));

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
            'processing_time_ms' => 0, // будет заполнено в processItem
            'items_compared' => count($similarItems),
        ];
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
                checked_at = VALUES(checked_at),
                updated_at = NOW()
        ";

        $params = [
            'item_id' => $itemId,
            'feed_id' => $feedId,
            'is_duplicate' => $result['is_duplicate'] ? 1 : 0,
            'duplicate_of_item_id' => $result['duplicate_of_item_id'] ?? null,
            'similarity_score' => $result['similarity_score'],
            'similarity_method' => $result['similarity_method'],
            'can_be_published' => $result['can_be_published'] ? 1 : 0,
            'matched_entities' => $result['matched_entities'] ?? null,
            'matched_events' => $result['matched_events'] ?? null,
            'matched_facts' => $result['matched_facts'] ?? null,
            'model_used' => $result['model_used'] ?? null,
            'tokens_used' => $result['tokens_used'] ?? 0,
            'processing_time_ms' => $result['processing_time_ms'] ?? 0,
            'items_compared' => $result['items_compared'] ?? 0,
        ];

        $this->db->execute($query, $params);
    }
}

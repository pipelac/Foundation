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
 * - Подготовка данных для дедупликации (оригинал + английский)
 * - Оценка важности новости (1-20)
 * - Нормализация метаданных на английский язык для кроссязычной дедупликации
 * 
 * @version 2.1 - Добавлена поддержка билингвальных метаданных (оригинал + английский)
 * @version 2.0 - Рефакторинг с использованием AbstractPipelineModule и AIAnalysisTrait
 */
class SummarizationService extends AbstractPipelineModule
{
    use AIAnalysisTrait;

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
        $this->logger = $logger;
        $this->config = $this->validateConfig($config);
        $this->metrics = $this->initializeMetrics();
    }

    /**
     * {@inheritdoc}
     */
    protected function getModuleName(): string
    {
        return 'Summarization';
    }

    /**
     * {@inheritdoc}
     */
    protected function validateModuleConfig(array $config): array
    {
        $aiConfig = $this->validateAIConfig($config);

        return $aiConfig;
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
            'total_tokens' => 0,
            'total_time_ms' => 0,
            'cache_hits' => 0,
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
            if ($existingStatus === 'success') {
                $this->logInfo('Новость уже обработана', ['item_id' => $itemId]);
                $this->incrementMetric('skipped');
                return true;
            }

            // Получаем данные новости
            $item = $this->getItem($itemId);
            if (!$item) {
                throw new AIAnalysisException("Новость с ID {$itemId} не найдена");
            }

            // Обновляем статус на processing
            $this->updateStatus($itemId, (int)$item['feed_id'], 'processing');

            // Подготавливаем промпт
            $systemPrompt = $this->loadPromptFromFile($this->config['prompt_file']);
            $userPrompt = $this->prepareUserPrompt($item);

            // Получаем результат от AI с fallback
            $result = $this->analyzeWithFallback($systemPrompt, $userPrompt);

            if (!$result) {
                throw new AIAnalysisException("Не удалось получить результат от AI");
            }

            // Сохраняем результат
            $this->saveResult($itemId, (int)$item['feed_id'], $result);

            $processingTime = $this->recordProcessingTime($startTime);
            $this->incrementMetric('successful');

            $this->logInfo('Новость успешно обработана', [
                'item_id' => $itemId,
                'processing_time_ms' => $processingTime,
            ]);

            return true;

        } catch (Exception $e) {
            $this->incrementMetric('failed');
            
            $feedId = isset($item) ? (int)($item['feed_id'] ?? 0) : 0;
            $this->updateStatus($itemId, $feedId, 'failed', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ]);

            $this->logError('Ошибка обработки новости', [
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
        $query = "SELECT status FROM rss2tlg_summarization WHERE item_id = :item_id LIMIT 1";
        $result = $this->db->queryOne($query, ['item_id' => $itemId]);
        
        return $result['status'] ?? null;
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

        // Категории: всегда на английском (стандартизированные идентификаторы)
        $categoryPrimary = $analysisData['category']['primary'] ?? null;
        $categoryPrimaryEn = $analysisData['category']['primary_en'] ?? $categoryPrimary;
        $categorySecondary = $analysisData['category']['secondary'] ?? [];
        $categorySecondaryEn = $analysisData['category']['secondary_en'] ?? $categorySecondary;

        // Ключевые слова: оригинал + английский
        $keywords = $analysisData['content']['keywords'] ?? [];
        $keywordsEn = $analysisData['content']['keywords_en'] ?? $keywords;

        // Сущности для дедупликации: оригинал + английский
        $entities = $analysisData['deduplication']['canonical_entities'] ?? [];
        $entitiesEn = $analysisData['deduplication']['canonical_entities_en'] ?? $entities;

        // Описание события: оригинал + английский
        $coreEvent = $analysisData['deduplication']['core_event'] ?? null;
        $coreEventEn = $analysisData['deduplication']['core_event_en'] ?? $coreEvent;

        $query = "
            UPDATE rss2tlg_summarization
            SET
                status = 'success',
                article_language = :article_language,
                category_primary = :category_primary,
                category_primary_en = :category_primary_en,
                category_secondary = :category_secondary,
                category_secondary_en = :category_secondary_en,
                headline = :headline,
                summary = :summary,
                keywords = :keywords,
                keywords_en = :keywords_en,
                importance_rating = :importance_rating,
                dedup_canonical_entities = :dedup_canonical_entities,
                dedup_canonical_entities_en = :dedup_canonical_entities_en,
                dedup_core_event = :dedup_core_event,
                dedup_core_event_en = :dedup_core_event_en,
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
            'category_primary' => $categoryPrimary,
            'category_primary_en' => $categoryPrimaryEn,
            'category_secondary' => json_encode($categorySecondary, JSON_UNESCAPED_UNICODE),
            'category_secondary_en' => json_encode($categorySecondaryEn, JSON_UNESCAPED_UNICODE),
            'headline' => $analysisData['content']['headline'] ?? null,
            'summary' => $analysisData['content']['summary'] ?? null,
            'keywords' => json_encode($keywords, JSON_UNESCAPED_UNICODE),
            'keywords_en' => json_encode($keywordsEn, JSON_UNESCAPED_UNICODE),
            'importance_rating' => $analysisData['importance']['rating'] ?? null,
            'dedup_canonical_entities' => json_encode($entities, JSON_UNESCAPED_UNICODE),
            'dedup_canonical_entities_en' => json_encode($entitiesEn, JSON_UNESCAPED_UNICODE),
            'dedup_core_event' => $coreEvent,
            'dedup_core_event_en' => $coreEventEn,
            'dedup_numeric_facts' => json_encode($analysisData['deduplication']['numeric_facts'] ?? [], JSON_UNESCAPED_UNICODE),
            'model_used' => $result['model_used'],
            'tokens_used' => $result['tokens_used'],
            'tokens_prompt' => $result['tokens_prompt'],
            'tokens_completion' => $result['tokens_completion'],
            'tokens_cached' => $result['tokens_cached'],
            'cache_hit' => $result['cache_hit'] ? 1 : 0,
        ];

        $this->db->execute($query, $params);
    }
}

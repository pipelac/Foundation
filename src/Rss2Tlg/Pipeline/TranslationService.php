<?php

declare(strict_types=1);

namespace App\Rss2Tlg\Pipeline;

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\OpenRouter;
use App\Rss2Tlg\Exception\AI\AIAnalysisException;
use Exception;

/**
 * Сервис перевода новостей
 * 
 * Третий этап AI Pipeline:
 * - Перевод заголовка и краткого содержания на целевые языки
 * - Поддержка множественных переводов (1 новость → N языков)
 * - Оценка качества перевода (1-10)
 * - Fallback механизм между AI моделями
 * 
 * @version 2.0 - Рефакторинг с использованием AbstractPipelineModule и AIAnalysisTrait
 */
class TranslationService extends AbstractPipelineModule
{
    use AIAnalysisTrait;

    /**
     * Конструктор сервиса перевода
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
        return 'Translation';
    }

    /**
     * {@inheritdoc}
     */
    protected function validateModuleConfig(array $config): array
    {
        $aiConfig = $this->validateAIConfig($config);

        if (empty($config['target_languages']) || !is_array($config['target_languages'])) {
            throw new AIAnalysisException('Не указаны целевые языки для перевода');
        }

        return array_merge($aiConfig, [
            'target_languages' => $config['target_languages'],
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
            'total_tokens' => 0,
            'total_time_ms' => 0,
            'translations_created' => 0,
            'languages_processed' => [],
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
            // Получаем данные новости из суммаризации
            $summarization = $this->getSummarization($itemId);
            if (!$summarization) {
                throw new AIAnalysisException("Суммаризация для новости {$itemId} не найдена");
            }

            // Проверяем можно ли публиковать (прошла ли дедупликацию)
            if (!$this->canBePublished($itemId)) {
                $this->logInfo('Новость не прошла дедупликацию, пропускаем перевод', ['item_id' => $itemId]);
                $this->incrementMetric('skipped');
                return true;
            }

            $sourceLanguage = $summarization['article_language'] ?? 'unknown';
            $allSuccess = true;

            // Переводим на все целевые языки
            foreach ($this->config['target_languages'] as $targetLanguage) {
                // Пропускаем если язык совпадает с источником
                if ($sourceLanguage === $targetLanguage) {
                    $this->logDebug('Пропускаем перевод: язык совпадает с источником', [
                        'item_id' => $itemId,
                        'language' => $targetLanguage,
                    ]);
                    continue;
                }

                // Проверяем не переведена ли уже
                $existingStatus = $this->getTranslationStatus($itemId, $targetLanguage);
                if ($existingStatus === 'success') {
                    $this->logDebug('Перевод уже существует', [
                        'item_id' => $itemId,
                        'language' => $targetLanguage,
                    ]);
                    continue;
                }

                // Переводим
                $success = $this->translateToLanguage(
                    $itemId,
                    (int)$summarization['feed_id'],
                    $sourceLanguage,
                    $targetLanguage,
                    $summarization['headline'],
                    $summarization['summary']
                );

                if ($success) {
                    $this->incrementMetric('translations_created');
                    
                    if (!isset($this->metrics['languages_processed'][$targetLanguage])) {
                        $this->metrics['languages_processed'][$targetLanguage] = 0;
                    }
                    $this->metrics['languages_processed'][$targetLanguage]++;
                } else {
                    $allSuccess = false;
                }
            }

            $processingTime = $this->recordProcessingTime($startTime);

            if ($allSuccess) {
                $this->incrementMetric('successful');
                $this->logInfo('Новость успешно переведена на все языки', [
                    'item_id' => $itemId,
                    'languages' => $this->config['target_languages'],
                    'processing_time_ms' => $processingTime,
                ]);
            } else {
                $this->incrementMetric('failed');
                $this->logWarning('Не все переводы выполнены успешно', [
                    'item_id' => $itemId,
                ]);
            }

            return $allSuccess;

        } catch (Exception $e) {
            $this->incrementMetric('failed');
            
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
        // Проверяем статус для первого целевого языка
        $targetLanguage = $this->config['target_languages'][0] ?? null;
        if (!$targetLanguage) {
            return null;
        }

        return $this->getTranslationStatus($itemId, $targetLanguage);
    }

    /**
     * Переводит новость на конкретный язык
     *
     * @param int $itemId
     * @param int $feedId
     * @param string $sourceLanguage
     * @param string $targetLanguage
     * @param string $headline
     * @param string $summary
     * @return bool
     */
    private function translateToLanguage(
        int $itemId,
        int $feedId,
        string $sourceLanguage,
        string $targetLanguage,
        string $headline,
        string $summary
    ): bool {
        try {
            // Обновляем статус на processing
            $this->updateStatus($itemId, $feedId, $sourceLanguage, $targetLanguage, 'processing');

            // Подготавливаем промпт
            $systemPrompt = $this->loadPromptFromFile($this->config['prompt_file']);
            $userPrompt = $this->prepareUserPrompt($sourceLanguage, $targetLanguage, $headline, $summary);

            // Получаем результат от AI с fallback
            $result = $this->analyzeWithFallback($systemPrompt, $userPrompt);

            if (!$result) {
                throw new AIAnalysisException("Не удалось получить перевод от AI");
            }

            // Сохраняем результат
            $this->saveResult($itemId, $feedId, $sourceLanguage, $targetLanguage, $result);

            $this->logInfo('Перевод успешно выполнен', [
                'item_id' => $itemId,
                'source_language' => $sourceLanguage,
                'target_language' => $targetLanguage,
            ]);

            return true;

        } catch (Exception $e) {
            $this->updateStatus($itemId, $feedId, $sourceLanguage, $targetLanguage, 'failed', [
                'error_message' => $e->getMessage(),
                'error_code' => (string)$e->getCode(),
            ]);

            $this->logError('Ошибка перевода', [
                'item_id' => $itemId,
                'target_language' => $targetLanguage,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Получает данные суммаризации новости
     *
     * @param int $itemId
     * @return array<string, mixed>|null
     */
    private function getSummarization(int $itemId): ?array
    {
        $query = "
            SELECT feed_id, article_language, headline, summary, status
            FROM rss2tlg_summarization
            WHERE item_id = :item_id AND status = 'success'
            LIMIT 1
        ";
        
        return $this->db->queryOne($query, ['item_id' => $itemId]);
    }

    /**
     * Проверяет можно ли публиковать новость (прошла ли дедупликацию)
     *
     * @param int $itemId
     * @return bool
     */
    private function canBePublished(int $itemId): bool
    {
        $query = "
            SELECT can_be_published
            FROM rss2tlg_deduplication
            WHERE item_id = :item_id
            LIMIT 1
        ";
        
        $result = $this->db->queryOne($query, ['item_id' => $itemId]);
        
        // Если нет записи в дедупликации - считаем что можно публиковать
        if (!$result) {
            return true;
        }
        
        return ($result['can_be_published'] ?? 0) == 1;
    }

    /**
     * Получает статус перевода для конкретного языка
     *
     * @param int $itemId
     * @param string $targetLanguage
     * @return string|null
     */
    private function getTranslationStatus(int $itemId, string $targetLanguage): ?string
    {
        $query = "
            SELECT status
            FROM rss2tlg_translation
            WHERE item_id = :item_id AND target_language = :target_language
            LIMIT 1
        ";
        
        $result = $this->db->queryOne($query, [
            'item_id' => $itemId,
            'target_language' => $targetLanguage,
        ]);
        
        return $result['status'] ?? null;
    }

    /**
     * Подготавливает пользовательский промпт с данными для перевода
     *
     * @param string $sourceLanguage
     * @param string $targetLanguage
     * @param string $headline
     * @param string $summary
     * @return string
     */
    private function prepareUserPrompt(
        string $sourceLanguage,
        string $targetLanguage,
        string $headline,
        string $summary
    ): string {
        $prompt = "Translate the following news article:\n\n";
        $prompt .= "Source Language: {$sourceLanguage}\n";
        $prompt .= "Target Language: {$targetLanguage}\n\n";
        $prompt .= "Headline: {$headline}\n\n";
        $prompt .= "Summary:\n{$summary}\n\n";
        $prompt .= "Provide the translation in JSON format according to the schema.";
        
        return $prompt;
    }

    /**
     * Обновляет статус обработки в БД
     *
     * @param int $itemId
     * @param int $feedId
     * @param string $sourceLanguage
     * @param string $targetLanguage
     * @param string $status
     * @param array<string, mixed> $extraData
     */
    private function updateStatus(
        int $itemId,
        int $feedId,
        string $sourceLanguage,
        string $targetLanguage,
        string $status,
        array $extraData = []
    ): void {
        $params = [
            'item_id' => $itemId,
            'feed_id' => $feedId,
            'source_language' => $sourceLanguage,
            'target_language' => $targetLanguage,
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
            INSERT INTO rss2tlg_translation (item_id, feed_id, source_language, target_language, status, created_at, updated_at)
            VALUES (:item_id, :feed_id, :source_language, :target_language, :status, NOW(), NOW())
            ON DUPLICATE KEY UPDATE " . implode(', ', $updateParts) . "
        ";

        $this->db->execute($query, $params);
    }

    /**
     * Сохраняет результат перевода в БД
     *
     * @param int $itemId
     * @param int $feedId
     * @param string $sourceLanguage
     * @param string $targetLanguage
     * @param array<string, mixed> $result
     */
    private function saveResult(
        int $itemId,
        int $feedId,
        string $sourceLanguage,
        string $targetLanguage,
        array $result
    ): void {
        $analysisData = $result['analysis_data'];

        $query = "
            UPDATE rss2tlg_translation
            SET
                status = 'success',
                translated_headline = :translated_headline,
                translated_summary = :translated_summary,
                translation_quality = :translation_quality,
                model_used = :model_used,
                tokens_used = :tokens_used,
                tokens_prompt = :tokens_prompt,
                tokens_completion = :tokens_completion,
                tokens_cached = :tokens_cached,
                cache_hit = :cache_hit,
                translated_at = NOW(),
                updated_at = NOW()
            WHERE item_id = :item_id AND target_language = :target_language
        ";

        $params = [
            'item_id' => $itemId,
            'target_language' => $targetLanguage,
            'translated_headline' => $analysisData['translated_headline'] ?? null,
            'translated_summary' => $analysisData['translated_summary'] ?? null,
            'translation_quality' => $analysisData['quality_score'] ?? null,
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

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
 */
class TranslationService implements PipelineModuleInterface
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
        'translations_created' => 0,
        'languages_processed' => [],
        'model_attempts' => [],
    ];

    /**
     * Конструктор сервиса перевода
     *
     * @param MySQL $db Подключение к БД
     * @param OpenRouter $openRouter Клиент OpenRouter API
     * @param array<string, mixed> $config Конфигурация модуля:
     *   - enabled (bool): Включен ли модуль
     *   - target_languages (array): Список языков для перевода ['en', 'ru', 'es']
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
            throw new AIAnalysisException('Не указаны AI модели для перевода');
        }

        if (empty($config['prompt_file']) || !file_exists($config['prompt_file'])) {
            throw new AIAnalysisException('Не указан или не найден файл промпта');
        }

        if (empty($config['target_languages']) || !is_array($config['target_languages'])) {
            throw new AIAnalysisException('Не указаны целевые языки для перевода');
        }

        return [
            'enabled' => $config['enabled'] ?? true,
            'target_languages' => $config['target_languages'],
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
            $this->logDebug('Модуль перевода отключен', ['item_id' => $itemId]);
            return false;
        }

        $startTime = microtime(true);
        $this->metrics['total_processed']++;

        try {
            // Получаем данные новости из суммаризации
            $summarization = $this->getSummarization($itemId);
            if (!$summarization) {
                throw new AIAnalysisException("Суммаризация для новости {$itemId} не найдена");
            }

            // Проверяем можно ли публиковать (прошла ли дедупликацию)
            if (!$this->canBePublished($itemId)) {
                $this->logInfo('Новость не прошла дедупликацию, пропускаем перевод', ['item_id' => $itemId]);
                $this->metrics['skipped']++;
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
                    $summarization['feed_id'],
                    $sourceLanguage,
                    $targetLanguage,
                    $summarization['headline'],
                    $summarization['summary']
                );

                if ($success) {
                    $this->metrics['translations_created']++;
                    
                    if (!isset($this->metrics['languages_processed'][$targetLanguage])) {
                        $this->metrics['languages_processed'][$targetLanguage] = 0;
                    }
                    $this->metrics['languages_processed'][$targetLanguage]++;
                } else {
                    $allSuccess = false;
                }
            }

            $processingTime = (int)((microtime(true) - $startTime) * 1000);
            $this->metrics['total_time_ms'] += $processingTime;

            if ($allSuccess) {
                $this->metrics['successful']++;
                $this->logInfo('Новость успешно переведена на все языки', [
                    'item_id' => $itemId,
                    'languages' => $this->config['target_languages'],
                    'processing_time_ms' => $processingTime,
                ]);
            } else {
                $this->metrics['failed']++;
                $this->logWarning('Не все переводы выполнены успешно', [
                    'item_id' => $itemId,
                ]);
            }

            return $allSuccess;

        } catch (Exception $e) {
            $this->metrics['failed']++;
            
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
                // Проверяем не была ли пропущена
                $summarization = $this->getSummarization($itemId);
                if (!$summarization || !$this->canBePublished($itemId)) {
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
        // Проверяем статус для первого целевого языка
        $targetLanguage = $this->config['target_languages'][0] ?? null;
        if (!$targetLanguage) {
            return null;
        }

        return $this->getTranslationStatus($itemId, $targetLanguage);
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
            'translations_created' => 0,
            'languages_processed' => [],
            'model_attempts' => [],
        ];
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
            $systemPrompt = $this->loadPrompt();
            $userPrompt = $this->prepareUserPrompt($sourceLanguage, $targetLanguage, $headline, $summary);

            // Получаем результат от AI с fallback
            $result = $this->translateWithFallback(
                $itemId,
                $feedId,
                $sourceLanguage,
                $targetLanguage,
                $systemPrompt,
                $userPrompt
            );

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
     * Переводит с использованием fallback моделей
     *
     * @param int $itemId
     * @param int $feedId
     * @param string $sourceLanguage
     * @param string $targetLanguage
     * @param string $systemPrompt
     * @param string $userPrompt
     * @return array<string, mixed>|null
     */
    private function translateWithFallback(
        int $itemId,
        int $feedId,
        string $sourceLanguage,
        string $targetLanguage,
        string $systemPrompt,
        string $userPrompt
    ): ?array {
        $models = $this->config['models'];
        
        if ($this->config['fallback_strategy'] === 'random') {
            shuffle($models);
        }

        $lastError = null;

        foreach ($models as $modelConfig) {
            $modelName = is_array($modelConfig) ? ($modelConfig['model'] ?? '') : $modelConfig;
            $retryCount = $this->config['retry_count'];

            // Увеличиваем счетчик попыток для модели
            if (!isset($this->metrics['model_attempts'][$modelName])) {
                $this->metrics['model_attempts'][$modelName] = 0;
            }
            $this->metrics['model_attempts'][$modelName]++;

            $this->logDebug('Попытка перевода с моделью', [
                'item_id' => $itemId,
                'model' => $modelName,
                'target_language' => $targetLanguage,
            ]);

            for ($attempt = 0; $attempt <= $retryCount; $attempt++) {
                try {
                    $result = $this->callAI($modelName, $systemPrompt, $userPrompt);
                    
                    if ($result) {
                        // Обновляем метрики
                        if (isset($result['tokens_used'])) {
                            $this->metrics['total_tokens'] += $result['tokens_used'];
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

        $this->logError('Все модели не смогли перевести новость', [
            'item_id' => $itemId,
            'target_language' => $targetLanguage,
            'last_error' => $lastError,
        ]);

        return null;
    }

    /**
     * Вызывает AI модель для перевода
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
            'max_tokens' => 3000,
            'response_format' => ['type' => 'json_object'],
        ];

        $response = $this->openRouter->chatWithMessages($model, $messages, $options);

        if (!$response || !isset($response['content'])) {
            return null;
        }

        $content = $response['content'];
        $translationData = json_decode($content, true);

        if (!$translationData) {
            throw new AIAnalysisException('Не удалось распарсить JSON ответ от AI');
        }

        // Проверяем статус перевода
        if (($translationData['translation_status'] ?? '') !== 'completed') {
            throw new AIAnalysisException(
                'Перевод не завершен: ' . ($translationData['translation_quality']['issues'] ?? 'Unknown error')
            );
        }

        // Извлекаем метрики
        $usage = $response['usage'] ?? [];
        
        return [
            'translation_data' => $translationData,
            'tokens_used' => $usage['total_tokens'] ?? 0,
            'tokens_prompt' => $usage['prompt_tokens'] ?? 0,
            'tokens_completion' => $usage['completion_tokens'] ?? 0,
        ];
    }

    /**
     * Обновляет статус перевода в БД
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
        // Базовые параметры
        $params = [
            'item_id' => $itemId,
            'feed_id' => $feedId,
            'source_language' => $sourceLanguage,
            'target_language' => $targetLanguage,
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
            INSERT INTO rss2tlg_translation (
                item_id, feed_id, source_language, target_language, status, created_at, updated_at
            )
            VALUES (
                :item_id, :feed_id, :source_language, :target_language, :status, NOW(), NOW()
            )
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
        $translationData = $result['translation_data'];
        $qualityData = $translationData['translation_quality'] ?? [];

        $query = "
            UPDATE rss2tlg_translation
            SET
                status = 'success',
                translated_headline = :translated_headline,
                translated_summary = :translated_summary,
                quality_score = :quality_score,
                quality_issues = :quality_issues,
                model_used = :model_used,
                tokens_used = :tokens_used,
                tokens_prompt = :tokens_prompt,
                tokens_completion = :tokens_completion,
                translated_at = NOW(),
                updated_at = NOW()
            WHERE item_id = :item_id AND target_language = :target_language
        ";

        $params = [
            'item_id' => $itemId,
            'target_language' => $targetLanguage,
            'translated_headline' => $translationData['translated_headline'] ?? '',
            'translated_summary' => $translationData['translated_summary'] ?? '',
            'quality_score' => $qualityData['score'] ?? 0,
            'quality_issues' => $qualityData['issues'] ?? null,
            'model_used' => $result['model_used'] ?? '',
            'tokens_used' => $result['tokens_used'] ?? 0,
            'tokens_prompt' => $result['tokens_prompt'] ?? 0,
            'tokens_completion' => $result['tokens_completion'] ?? 0,
        ];

        $this->db->execute($query, $params);
    }

    /**
     * Логирует debug сообщение
     *
     * @param string $message
     * @param array<string, mixed> $context
     */
    private function logDebug(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->debug("[TranslationService] {$message}", $context);
        }
    }

    /**
     * Логирует info сообщение
     *
     * @param string $message
     * @param array<string, mixed> $context
     */
    private function logInfo(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->info("[TranslationService] {$message}", $context);
        }
    }

    /**
     * Логирует warning сообщение
     *
     * @param string $message
     * @param array<string, mixed> $context
     */
    private function logWarning(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->warning("[TranslationService] {$message}", $context);
        }
    }

    /**
     * Логирует error сообщение
     *
     * @param string $message
     * @param array<string, mixed> $context
     */
    private function logError(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->error("[TranslationService] {$message}", $context);
        }
    }
}

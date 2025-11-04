<?php

declare(strict_types=1);

namespace App\Rss2Tlg;

use App\Component\Logger;
use App\Component\MySQL;

/**
 * Репозиторий для работы с результатами AI-анализа новостей в БД
 * 
 * Управляет хранением результатов обработки новостей через OpenRouter API.
 */
class AIAnalysisRepository
{
    private const TABLE_NAME = 'rss2tlg_ai_analysis';

    /**
     * Конструктор репозитория
     * 
     * @param MySQL $db Подключение к БД
     * @param Logger|null $logger Логгер для отладки
     * @param bool $autoCreateTables Автоматическое создание таблиц (по умолчанию true)
     */
    public function __construct(
        private readonly MySQL $db,
        private readonly ?Logger $logger = null,
        bool $autoCreateTables = true
    ) {
        if ($autoCreateTables) {
            $this->createTableIfNotExist();
        }
    }

    /**
     * Сохраняет результат AI-анализа новости
     * 
     * @param int $itemId ID новости из rss2tlg_items
     * @param int $feedId ID источника
     * @param string $promptId ID использованного промпта
     * @param array<string, mixed> $analysisData Полный JSON ответ от AI
     * @param string $modelUsed Название модели AI
     * @param int|null $tokensUsed Количество использованных токенов
     * @param int|null $processingTimeMs Время обработки в мс
     * @param bool $cacheHit Был ли использован кеш
     * @return int|null ID сохраненной записи или null при ошибке
     */
    public function save(
        int $itemId,
        int $feedId,
        string $promptId,
        array $analysisData,
        string $modelUsed,
        ?int $tokensUsed = null,
        ?int $processingTimeMs = null,
        bool $cacheHit = false
    ): ?int {
        try {
            // Извлекаем основные поля из analysisData
            $status = $analysisData['analysis_status'] ?? 'success';
            $articleLanguage = $analysisData['article_language'] ?? null;
            $translationStatus = $analysisData['translation_status'] ?? null;
            $translationQualityScore = $analysisData['translation_quality']['overall_score'] ?? null;
            
            $categoryPrimary = $analysisData['category']['primary'] ?? null;
            $categoryConfidence = $analysisData['category']['confidence'] ?? null;
            $categorySecondary = isset($analysisData['category']['secondary']) 
                ? json_encode($analysisData['category']['secondary']) 
                : null;
            
            $contentHeadline = $analysisData['content']['headline'] ?? null;
            $contentSummary = $analysisData['content']['summary'] ?? null;
            $contentKeywords = isset($analysisData['content']['keywords']) 
                ? json_encode($analysisData['content']['keywords']) 
                : null;
            
            $importanceRating = $analysisData['importance']['rating'] ?? null;
            $importanceJustification = $analysisData['importance']['justification'] ?? null;
            
            $deduplicationData = isset($analysisData['deduplication']) 
                ? json_encode($analysisData['deduplication']) 
                : null;
            
            $qualityFlags = isset($analysisData['quality_flags']) 
                ? json_encode($analysisData['quality_flags']) 
                : null;

            // Подготовка данных для INSERT
            $data = [
                'item_id' => $itemId,
                'feed_id' => $feedId,
                'prompt_id' => $promptId,
                'analysis_status' => $status,
                'analysis_data' => json_encode($analysisData, JSON_UNESCAPED_UNICODE),
                'article_language' => $articleLanguage,
                'translation_status' => $translationStatus,
                'translation_quality_score' => $translationQualityScore,
                'category_primary' => $categoryPrimary,
                'category_confidence' => $categoryConfidence,
                'category_secondary' => $categorySecondary,
                'content_headline' => $contentHeadline,
                'content_summary' => $contentSummary,
                'content_keywords' => $contentKeywords,
                'importance_rating' => $importanceRating,
                'importance_justification' => $importanceJustification,
                'deduplication_data' => $deduplicationData,
                'quality_flags' => $qualityFlags,
                'model_used' => $modelUsed,
                'tokens_used' => $tokensUsed,
                'processing_time_ms' => $processingTimeMs,
                'cache_hit' => $cacheHit ? 1 : 0,
                'analyzed_at' => date('Y-m-d H:i:s'),
            ];

            $insertId = $this->db->insert(self::TABLE_NAME, $data);

            $this->logDebug('AI анализ сохранен', [
                'id' => $insertId,
                'item_id' => $itemId,
                'prompt_id' => $promptId,
                'importance_rating' => $importanceRating,
            ]);

            return $insertId;
        } catch (\Exception $e) {
            $this->logError('Ошибка сохранения AI анализа', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Сохраняет ошибку анализа
     * 
     * @param int $itemId ID новости
     * @param int $feedId ID источника
     * @param string $promptId ID промпта
     * @param string $errorMessage Сообщение об ошибке
     * @return int|null ID записи или null при ошибке
     */
    public function saveError(
        int $itemId,
        int $feedId,
        string $promptId,
        string $errorMessage
    ): ?int {
        try {
            $data = [
                'item_id' => $itemId,
                'feed_id' => $feedId,
                'prompt_id' => $promptId,
                'analysis_status' => 'failed',
                'error_message' => $errorMessage,
            ];

            $insertId = $this->db->insert(self::TABLE_NAME, $data);

            $this->logDebug('Ошибка анализа сохранена', [
                'id' => $insertId,
                'item_id' => $itemId,
                'error' => $errorMessage,
            ]);

            return $insertId;
        } catch (\Exception $e) {
            $this->logError('Ошибка сохранения ошибки анализа', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Получает анализ по ID новости
     * 
     * @param int $itemId ID новости
     * @return array<string, mixed>|null Данные анализа или null
     */
    public function getByItemId(int $itemId): ?array
    {
        try {
            $sql = sprintf("SELECT * FROM %s WHERE item_id = ? LIMIT 1", self::TABLE_NAME);
            $result = $this->db->query($sql, [$itemId]);
            
            return !empty($result) ? $result[0] : null;
        } catch (\Exception $e) {
            $this->logError('Ошибка получения анализа по item_id', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Проверяет, существует ли анализ для новости
     * 
     * @param int $itemId ID новости
     * @return bool true если анализ существует
     */
    public function exists(int $itemId): bool
    {
        return $this->getByItemId($itemId) !== null;
    }

    /**
     * Получает новости, ожидающие анализа
     * 
     * @param int $feedId ID источника (0 = все источники)
     * @param int $limit Максимальное количество
     * @return array<int, array<string, mixed>> Массив новостей
     */
    public function getPendingItems(int $feedId = 0, int $limit = 10): array
    {
        try {
            $whereFeed = $feedId > 0 ? "AND i.feed_id = {$feedId}" : "";
            
            $sql = sprintf(
                "SELECT i.* 
                FROM rss2tlg_items i
                LEFT JOIN %s a ON i.id = a.item_id
                WHERE a.id IS NULL
                %s
                ORDER BY i.created_at DESC
                LIMIT %d",
                self::TABLE_NAME,
                $whereFeed,
                $limit
            );
            
            return $this->db->query($sql);
        } catch (\Exception $e) {
            $this->logError('Ошибка получения новостей для анализа', [
                'feed_id' => $feedId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Получает анализы по рейтингу важности
     * 
     * @param int $minRating Минимальный рейтинг (1-20)
     * @param int $limit Максимальное количество
     * @return array<int, array<string, mixed>> Массив анализов
     */
    public function getByImportance(int $minRating = 10, int $limit = 20): array
    {
        try {
            $sql = sprintf(
                "SELECT * FROM %s 
                WHERE analysis_status = 'success' 
                AND importance_rating >= %d
                ORDER BY importance_rating DESC, analyzed_at DESC
                LIMIT %d",
                self::TABLE_NAME,
                $minRating,
                $limit
            );
            
            return $this->db->query($sql);
        } catch (\Exception $e) {
            $this->logError('Ошибка получения анализов по важности', [
                'min_rating' => $minRating,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Получает статистику по анализам
     * 
     * @return array<string, mixed> Статистика
     */
    public function getStats(): array
    {
        try {
            $sql = sprintf(
                "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN analysis_status = 'success' THEN 1 ELSE 0 END) as success,
                    SUM(CASE WHEN analysis_status = 'failed' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN analysis_status = 'pending' THEN 1 ELSE 0 END) as pending,
                    AVG(CASE WHEN importance_rating IS NOT NULL THEN importance_rating ELSE NULL END) as avg_importance,
                    AVG(CASE WHEN processing_time_ms IS NOT NULL THEN processing_time_ms ELSE NULL END) as avg_processing_time_ms,
                    SUM(CASE WHEN cache_hit = 1 THEN 1 ELSE 0 END) as cache_hits,
                    SUM(tokens_used) as total_tokens
                FROM %s",
                self::TABLE_NAME
            );
            
            $result = $this->db->queryOne($sql);
            return $result ?? [];
        } catch (\Exception $e) {
            $this->logError('Ошибка получения статистики', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Логирует отладочную информацию
     * 
     * @param string $message Сообщение
     * @param array<string, mixed> $context Контекст
     */
    private function logDebug(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->debug($message, $context);
        }
    }

    /**
     * Логирует ошибку
     * 
     * @param string $message Сообщение об ошибке
     * @param array<string, mixed> $context Контекст
     */
    private function logError(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->error($message, $context);
        }
    }

    /**
     * Создаёт таблицу анализа если она не существует
     * 
     * @return bool true при успехе
     */
    private function createTableIfNotExist(): bool
    {
        try {
            // Проверяем существование таблицы
            $tableExists = $this->db->queryOne(
                "SELECT COUNT(*) as count FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
                [self::TABLE_NAME]
            );

            if (empty($tableExists) || $tableExists['count'] == 0) {
                $schemaPath = __DIR__ . '/schema_ai_analysis.sql';
                
                if (!file_exists($schemaPath)) {
                    throw new \RuntimeException("Файл схемы не найден: {$schemaPath}");
                }

                $sql = file_get_contents($schemaPath);
                if ($sql === false) {
                    throw new \RuntimeException("Не удалось прочитать файл схемы");
                }

                $this->db->execute($sql);

                $this->logDebug('Таблица AI анализа создана', [
                    'table' => self::TABLE_NAME,
                ]);
            }

            return true;
        } catch (\Exception $e) {
            $this->logError('Ошибка создания таблицы AI анализа', [
                'table' => self::TABLE_NAME,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

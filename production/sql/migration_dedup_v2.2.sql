-- ======================================================================
-- Миграция для DeduplicationService v2.2
-- ======================================================================
-- Дата: 2025-11-10
-- Описание: Добавление поля skip_reason для аналитики пропущенных новостей
-- ======================================================================

USE rss2tlg;

-- Добавляем поле skip_reason для отслеживания причины пропуска
ALTER TABLE rss2tlg_deduplication
ADD COLUMN skip_reason ENUM('low_importance', 'none') DEFAULT 'none' 
    COMMENT 'Причина пропуска дедупликации (low_importance = importance < threshold)' 
    AFTER similarity_method;

-- Создаем индекс для быстрых аналитических запросов
CREATE INDEX idx_skip_reason ON rss2tlg_deduplication(skip_reason);

-- Создаем индекс для отбора новостей по can_be_published
CREATE INDEX idx_can_be_published ON rss2tlg_deduplication(can_be_published);

-- Создаем составной индекс для частых запросов
CREATE INDEX idx_status_can_publish ON rss2tlg_deduplication(status, can_be_published);

-- ======================================================================
-- Аналитические запросы (для справки)
-- ======================================================================

-- Статистика пропущенных по важности
-- SELECT 
--     COUNT(*) as total_skipped,
--     COUNT(CASE WHEN skip_reason = 'low_importance' THEN 1 END) as low_importance_count
-- FROM rss2tlg_deduplication
-- WHERE status = 'checked';

-- Распределение причин пропуска
-- SELECT 
--     skip_reason, 
--     COUNT(*) as count,
--     ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM rss2tlg_deduplication), 2) as percentage
-- FROM rss2tlg_deduplication
-- GROUP BY skip_reason;

-- Новости готовые к публикации
-- SELECT COUNT(*) 
-- FROM rss2tlg_deduplication 
-- WHERE status = 'checked' 
--   AND can_be_published = 1 
--   AND is_duplicate = 0;

-- ======================================================================
-- Конец миграции
-- ======================================================================

-- Migration: DeduplicationService v3.0 - Stepwise Multi-Language AI Deduplication
-- Date: 2025-11-10
-- Purpose: Add preliminary similarity fields for two-stage deduplication process

-- Добавляем поля для предварительной оценки схожести
ALTER TABLE `rss2tlg_deduplication`
    ADD COLUMN `preliminary_similarity_score` DECIMAL(5,2) DEFAULT NULL 
        COMMENT 'Предварительная оценка схожести (0.00-100.00)' 
        AFTER `similarity_score`,
    ADD COLUMN `preliminary_method` VARCHAR(50) DEFAULT 'hybrid_v1' 
        COMMENT 'Метод предварительной оценки (hybrid_v1, jaccard, etc.)' 
        AFTER `similarity_method`,
    ADD COLUMN `ai_analysis_triggered` TINYINT(1) NOT NULL DEFAULT 0 
        COMMENT 'Был ли вызван AI анализ (0=fast path, 1=AI used)' 
        AFTER `preliminary_method`;

-- Добавляем индекс для аналитики preliminary scores
CREATE INDEX idx_preliminary_score ON rss2tlg_deduplication(preliminary_similarity_score);

-- Добавляем индекс для аналитики AI usage
CREATE INDEX idx_ai_triggered ON rss2tlg_deduplication(ai_analysis_triggered);

-- Комментарий к миграции
-- Эти поля позволяют:
-- 1. Отслеживать preliminary similarity score для каждой новости
-- 2. Анализировать эффективность двухэтапного подхода
-- 3. Мониторить сколько AI вызовов было сэкономлено (ai_analysis_triggered=0)
-- 4. Подстраивать пороги на основе реальных данных

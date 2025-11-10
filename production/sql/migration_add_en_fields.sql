-- Migration: Add English normalized fields for cross-language deduplication
-- Date: 2025-11-10
-- Purpose: Store English versions of metadata for effective cross-language news deduplication

-- Добавляем поля для английских версий метаданных
ALTER TABLE `rss2tlg_summarization`
    ADD COLUMN `category_primary_en` varchar(100) DEFAULT NULL COMMENT 'Основная категория на английском' AFTER `category_primary`,
    ADD COLUMN `category_secondary_en` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Массив дополнительных категорий на английском' CHECK (json_valid(`category_secondary_en`)) AFTER `category_secondary`,
    ADD COLUMN `keywords_en` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Массив ключевых слов на английском' CHECK (json_valid(`keywords_en`)) AFTER `keywords`,
    ADD COLUMN `dedup_canonical_entities_en` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Ключевые сущности на английском для дедупликации' CHECK (json_valid(`dedup_canonical_entities_en`)) AFTER `dedup_canonical_entities`,
    ADD COLUMN `dedup_core_event_en` text DEFAULT NULL COMMENT 'Описание ключевого события на английском' AFTER `dedup_core_event`;

-- Добавляем индексы для оптимизации поиска
CREATE INDEX idx_category_primary_en ON rss2tlg_summarization(category_primary_en);

-- Комментарий к миграции
-- Эти поля будут заполняться AI моделью одновременно с оригинальными версиями
-- Для статей на английском языке оригинальные и английские версии будут идентичны
-- Для статей на других языках AI переведет ключевые метаданные на английский

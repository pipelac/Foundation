-- ============================================================================
-- PUBLICATION SCHEMA для модуля публикаций новостей
-- ============================================================================
-- Версия: 1.0
-- Дата: 2025-11-08
-- Описание: Расширение таблицы публикаций и создание таблицы для конфигурации
-- ============================================================================

-- ----------------------------------------------------------------------------
-- РАСШИРЕНИЕ ТАБЛИЦЫ ПУБЛИКАЦИЙ
-- ----------------------------------------------------------------------------
-- Добавляем поля для детальной информации о публикациях

ALTER TABLE `rss2tlg_publications` 
    ADD COLUMN IF NOT EXISTS `published_headline` VARCHAR(500) NULL DEFAULT NULL COMMENT 'Опубликованный заголовок' AFTER `message_id`,
    ADD COLUMN IF NOT EXISTS `published_text` TEXT NULL DEFAULT NULL COMMENT 'Опубликованный текст' AFTER `published_headline`,
    ADD COLUMN IF NOT EXISTS `published_language` VARCHAR(10) NULL DEFAULT NULL COMMENT 'Язык публикации' AFTER `published_text`,
    ADD COLUMN IF NOT EXISTS `published_media` JSON NULL DEFAULT NULL COMMENT 'Массив опубликованных медиа-файлов' AFTER `published_language`,
    ADD COLUMN IF NOT EXISTS `published_categories` JSON NULL DEFAULT NULL COMMENT 'Категории новости' AFTER `published_media`,
    ADD COLUMN IF NOT EXISTS `importance_rating` TINYINT UNSIGNED NULL DEFAULT NULL COMMENT 'Рейтинг важности' AFTER `published_categories`,
    ADD COLUMN IF NOT EXISTS `publication_status` ENUM('pending', 'processing', 'published', 'failed', 'skipped') 
        NOT NULL DEFAULT 'pending' COMMENT 'Статус публикации' AFTER `importance_rating`,
    ADD COLUMN IF NOT EXISTS `retry_count` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Количество повторов публикации' AFTER `publication_status`,
    ADD COLUMN IF NOT EXISTS `error_message` TEXT NULL DEFAULT NULL COMMENT 'Сообщение об ошибке' AFTER `retry_count`,
    ADD COLUMN IF NOT EXISTS `error_code` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Код ошибки Telegram API' AFTER `error_message`;

-- Добавляем индексы для оптимизации запросов
ALTER TABLE `rss2tlg_publications`
    ADD INDEX IF NOT EXISTS `idx_publication_status` (`publication_status`),
    ADD INDEX IF NOT EXISTS `idx_published_language` (`published_language`),
    ADD INDEX IF NOT EXISTS `idx_importance_rating` (`importance_rating`),
    ADD INDEX IF NOT EXISTS `idx_feed_destination` (`feed_id`, `destination_type`, `destination_id`);

-- ----------------------------------------------------------------------------
-- ТАБЛИЦА КОНФИГУРАЦИИ ПУБЛИКАЦИЙ
-- ----------------------------------------------------------------------------
-- Хранит правила публикации для каждого RSS источника и destination

CREATE TABLE IF NOT EXISTS `rss2tlg_publication_rules` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Первичный ключ',
    `feed_id` INT UNSIGNED NOT NULL COMMENT 'ID источника RSS',
    `destination_type` ENUM('bot', 'channel', 'group') NOT NULL COMMENT 'Тип назначения',
    `destination_id` VARCHAR(255) NOT NULL COMMENT 'ID чата/канала/группы (username или numeric ID)',
    
    -- Правила фильтрации
    `enabled` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Активно ли правило',
    `categories` JSON NULL DEFAULT NULL COMMENT 'Массив разрешенных категорий (null = все)',
    `min_importance` TINYINT UNSIGNED NULL DEFAULT NULL COMMENT 'Минимальный рейтинг важности (1-20)',
    `languages` JSON NULL DEFAULT NULL COMMENT 'Массив разрешенных языков (null = все)',
    
    -- Настройки публикации
    `include_image` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Прикреплять иллюстрацию',
    `include_link` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Включать ссылку на источник',
    `template` TEXT NULL DEFAULT NULL COMMENT 'Шаблон сообщения (опционально)',
    
    -- Метаданные
    `priority` TINYINT UNSIGNED NOT NULL DEFAULT 10 COMMENT 'Приоритет правила (1-100, выше = важнее)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Время создания',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Время обновления',
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_feed_destination` (`feed_id`, `destination_type`, `destination_id`),
    KEY `idx_enabled` (`enabled`),
    KEY `idx_priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Правила публикации новостей в каналы и группы';

-- ----------------------------------------------------------------------------
-- ПРЕДСТАВЛЕНИЕ для готовых к публикации новостей
-- ----------------------------------------------------------------------------
-- Объединяет все этапы pipeline и показывает новости готовые к публикации

CREATE OR REPLACE VIEW `v_rss2tlg_ready_to_publish` AS
SELECT 
    i.id AS item_id,
    i.feed_id,
    i.link AS source_link,
    i.pub_date,
    
    -- Контент для публикации
    s.headline,
    s.summary,
    s.article_language,
    s.category_primary,
    s.category_secondary,
    s.importance_rating,
    
    -- Переводы (если есть)
    t.target_language AS translation_language,
    t.translated_headline,
    t.translated_summary,
    t.quality_score AS translation_quality,
    
    -- Иллюстрация (если есть)
    il.image_path,
    il.image_format,
    
    -- Статусы обработки
    d.is_duplicate,
    d.can_be_published,
    d.similarity_score,
    
    -- Проверка наличия публикации
    IFNULL(p.id, 0) AS is_published
    
FROM rss2tlg_items i
INNER JOIN rss2tlg_summarization s ON i.id = s.item_id 
    AND s.status = 'success'
INNER JOIN rss2tlg_deduplication d ON i.id = d.item_id 
    AND d.status = 'checked' 
    AND d.can_be_published = 1
    AND d.is_duplicate = 0
LEFT JOIN rss2tlg_translation t ON i.id = t.item_id 
    AND t.status = 'success'
LEFT JOIN rss2tlg_illustration il ON i.id = il.item_id 
    AND il.status = 'success'
LEFT JOIN rss2tlg_publications p ON i.id = p.item_id
    AND p.publication_status = 'published'
WHERE i.is_published = 0
ORDER BY s.importance_rating DESC, i.pub_date DESC;

-- ----------------------------------------------------------------------------
-- ПРЕДСТАВЛЕНИЕ статистики публикаций
-- ----------------------------------------------------------------------------

CREATE OR REPLACE VIEW `v_rss2tlg_publication_stats` AS
SELECT 
    p.feed_id,
    p.destination_type,
    p.destination_id,
    COUNT(*) AS total_publications,
    COUNT(CASE WHEN p.publication_status = 'published' THEN 1 END) AS successful,
    COUNT(CASE WHEN p.publication_status = 'failed' THEN 1 END) AS failed,
    COUNT(CASE WHEN p.published_media IS NOT NULL THEN 1 END) AS with_media,
    AVG(p.importance_rating) AS avg_importance,
    MIN(p.published_at) AS first_publication,
    MAX(p.published_at) AS last_publication
FROM rss2tlg_publications p
GROUP BY p.feed_id, p.destination_type, p.destination_id;

-- ============================================================================
-- КОММЕНТАРИИ
-- ============================================================================
-- 1. Таблица rss2tlg_publications расширена для хранения полной информации
-- 2. Таблица rss2tlg_publication_rules управляет правилами публикации
-- 3. VIEW v_rss2tlg_ready_to_publish фильтрует готовые к публикации новости
-- 4. VIEW v_rss2tlg_publication_stats предоставляет статистику
-- 5. Поддержка множественных destinations для одного источника
-- 6. Гибкая фильтрация по категориям, важности, языкам
-- ============================================================================

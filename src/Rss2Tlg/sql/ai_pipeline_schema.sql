-- ============================================================================
-- AI PIPELINE SCHEMA для обработки RSS новостей
-- ============================================================================
-- Версия: 1.0
-- Дата: 2025-11-08
-- Описание: Схема БД для модульной обработки новостей через AI pipeline
-- ============================================================================

-- ----------------------------------------------------------------------------
-- ЭТАП 1: СУММАРИЗАЦИЯ + КАТЕГОРИЗАЦИЯ
-- ----------------------------------------------------------------------------
-- Таблица для хранения результатов AI суммаризации и категоризации новостей
-- Этот этап обязателен для всех новостей

CREATE TABLE IF NOT EXISTS `rss2tlg_summarization` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Первичный ключ',
    `item_id` INT UNSIGNED NOT NULL COMMENT 'ID новости (FK -> rss2tlg_items.id)',
    `feed_id` INT UNSIGNED NOT NULL COMMENT 'ID источника RSS',
    
    -- Статус обработки
    `status` ENUM('pending', 'processing', 'success', 'failed', 'skipped') 
        NOT NULL DEFAULT 'pending' COMMENT 'Статус суммаризации',
    
    -- Языковая обработка
    `article_language` VARCHAR(10) NULL DEFAULT NULL COMMENT 'Язык статьи (en, ru, и т.д.)',
    
    -- Категоризация
    `category_primary` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Основная категория',
    `category_secondary` JSON NULL DEFAULT NULL COMMENT 'Массив дополнительных категорий (до 2)',
    
    -- Контент
    `headline` VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'Заголовок новости',
    `summary` TEXT NULL DEFAULT NULL COMMENT 'Краткое содержание (суммаризация)',
    `keywords` JSON NULL DEFAULT NULL COMMENT 'Массив ключевых слов (до 5)',
    
    -- Важность
    `importance_rating` TINYINT UNSIGNED NULL DEFAULT NULL COMMENT 'Рейтинг важности (1-20)',
    
    -- Данные для дедупликации (подготовленные на этом этапе)
    `dedup_canonical_entities` JSON NULL DEFAULT NULL COMMENT 'Ключевые сущности для дедупликации',
    `dedup_core_event` TEXT NULL DEFAULT NULL COMMENT 'Описание ключевого события (1-2 предложения)',
    `dedup_numeric_facts` JSON NULL DEFAULT NULL COMMENT 'Числовые факты и даты',
    
    -- Метрики обработки
    `model_used` VARCHAR(150) NULL DEFAULT NULL COMMENT 'Модель AI использованная для анализа',
    `tokens_used` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Количество использованных токенов',
    `tokens_prompt` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Токены промпта',
    `tokens_completion` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Токены completion',
    `tokens_cached` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Токены из кеша',
    `processing_time_ms` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Время обработки в миллисекундах',
    `cache_hit` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Был ли использован кеш',
    `retry_count` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Количество повторов при ошибках',
    
    -- Ошибки
    `error_message` TEXT NULL DEFAULT NULL COMMENT 'Сообщение об ошибке',
    `error_code` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Код ошибки',
    
    -- Timestamps
    `processed_at` DATETIME NULL DEFAULT NULL COMMENT 'Время успешной обработки',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Время создания записи',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Время последнего обновления',
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_item_id` (`item_id`),
    KEY `idx_feed_id` (`feed_id`),
    KEY `idx_status` (`status`),
    KEY `idx_importance_rating` (`importance_rating`),
    KEY `idx_category_primary` (`category_primary`),
    KEY `idx_article_language` (`article_language`),
    KEY `idx_processed_at` (`processed_at`),
    KEY `idx_feed_status` (`feed_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Результаты AI суммаризации и категоризации новостей';

-- ----------------------------------------------------------------------------
-- ЭТАП 2: ДЕДУПЛИКАЦИЯ
-- ----------------------------------------------------------------------------
-- Таблица для хранения результатов проверки на дубликаты
-- Определяет можно ли публиковать новость

CREATE TABLE IF NOT EXISTS `rss2tlg_deduplication` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Первичный ключ',
    `item_id` INT UNSIGNED NOT NULL COMMENT 'ID новости (FK -> rss2tlg_items.id)',
    `feed_id` INT UNSIGNED NOT NULL COMMENT 'ID источника RSS',
    
    -- Статус обработки
    `status` ENUM('pending', 'processing', 'checked', 'failed', 'skipped') 
        NOT NULL DEFAULT 'pending' COMMENT 'Статус проверки',
    
    -- Результаты дедупликации
    `is_duplicate` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Является ли дубликатом (0/1)',
    `duplicate_of_item_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'ID оригинальной новости (FK)',
    `similarity_score` DECIMAL(5,2) NULL DEFAULT NULL COMMENT 'Оценка схожести (0.00-100.00)',
    `similarity_method` ENUM('ai', 'hash', 'hybrid') NULL DEFAULT NULL COMMENT 'Метод определения схожести',
    `can_be_published` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Можно ли публиковать (0/1)',
    
    -- Детали проверки
    `matched_entities` JSON NULL DEFAULT NULL COMMENT 'Совпавшие сущности',
    `matched_events` TEXT NULL DEFAULT NULL COMMENT 'Совпавшие события',
    `matched_facts` JSON NULL DEFAULT NULL COMMENT 'Совпавшие факты',
    
    -- Метрики обработки
    `model_used` VARCHAR(150) NULL DEFAULT NULL COMMENT 'Модель AI для проверки',
    `tokens_used` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Количество использованных токенов',
    `processing_time_ms` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Время обработки в миллисекундах',
    `items_compared` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Количество новостей для сравнения',
    `retry_count` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Количество повторов при ошибках',
    
    -- Ошибки
    `error_message` TEXT NULL DEFAULT NULL COMMENT 'Сообщение об ошибке',
    `error_code` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Код ошибки',
    
    -- Timestamps
    `checked_at` DATETIME NULL DEFAULT NULL COMMENT 'Время успешной проверки',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Время создания записи',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Время последнего обновления',
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_item_id` (`item_id`),
    KEY `idx_feed_id` (`feed_id`),
    KEY `idx_status` (`status`),
    KEY `idx_is_duplicate` (`is_duplicate`),
    KEY `idx_can_be_published` (`can_be_published`),
    KEY `idx_duplicate_of` (`duplicate_of_item_id`),
    KEY `idx_similarity_score` (`similarity_score`),
    KEY `idx_checked_at` (`checked_at`),
    KEY `idx_feed_status` (`feed_id`, `status`),
    KEY `idx_publish_ready` (`can_be_published`, `is_duplicate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Результаты проверки новостей на дубликаты';

-- ----------------------------------------------------------------------------
-- ЭТАП 3: ПЕРЕВОД
-- ----------------------------------------------------------------------------
-- Таблица для хранения результатов перевода новостей
-- Этот этап опционален и зависит от конфигурации источника

CREATE TABLE IF NOT EXISTS `rss2tlg_translation` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Первичный ключ',
    `item_id` INT UNSIGNED NOT NULL COMMENT 'ID новости (FK -> rss2tlg_items.id)',
    `feed_id` INT UNSIGNED NOT NULL COMMENT 'ID источника RSS',
    
    -- Статус обработки
    `status` ENUM('pending', 'processing', 'success', 'failed', 'skipped') 
        NOT NULL DEFAULT 'pending' COMMENT 'Статус перевода',
    
    -- Языки
    `source_language` VARCHAR(10) NOT NULL COMMENT 'Исходный язык',
    `target_language` VARCHAR(10) NOT NULL COMMENT 'Целевой язык',
    
    -- Переведенный контент
    `translated_headline` VARCHAR(500) NULL DEFAULT NULL COMMENT 'Переведенный заголовок',
    `translated_summary` TEXT NULL DEFAULT NULL COMMENT 'Переведенное краткое содержание',
    
    -- Качество перевода
    `quality_score` TINYINT UNSIGNED NULL DEFAULT NULL COMMENT 'Оценка качества (1-10)',
    `quality_issues` TEXT NULL DEFAULT NULL COMMENT 'Проблемы качества перевода',
    
    -- Метрики обработки
    `model_used` VARCHAR(150) NULL DEFAULT NULL COMMENT 'Модель AI для перевода',
    `tokens_used` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Количество использованных токенов',
    `tokens_prompt` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Токены промпта',
    `tokens_completion` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Токены completion',
    `processing_time_ms` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Время обработки в миллисекундах',
    `retry_count` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Количество повторов при ошибках',
    
    -- Ошибки
    `error_message` TEXT NULL DEFAULT NULL COMMENT 'Сообщение об ошибке',
    `error_code` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Код ошибки',
    
    -- Timestamps
    `translated_at` DATETIME NULL DEFAULT NULL COMMENT 'Время успешного перевода',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Время создания записи',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Время последнего обновления',
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_item_lang` (`item_id`, `target_language`),
    KEY `idx_feed_id` (`feed_id`),
    KEY `idx_status` (`status`),
    KEY `idx_source_language` (`source_language`),
    KEY `idx_target_language` (`target_language`),
    KEY `idx_quality_score` (`quality_score`),
    KEY `idx_translated_at` (`translated_at`),
    KEY `idx_feed_status` (`feed_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Результаты AI перевода новостей';

-- ----------------------------------------------------------------------------
-- ЭТАП 4: ИЛЛЮСТРАЦИИ
-- ----------------------------------------------------------------------------
-- Таблица для хранения сгенерированных иллюстраций
-- Этот этап опционален и зависит от конфигурации источника

CREATE TABLE IF NOT EXISTS `rss2tlg_illustration` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Первичный ключ',
    `item_id` INT UNSIGNED NOT NULL COMMENT 'ID новости (FK -> rss2tlg_items.id)',
    `feed_id` INT UNSIGNED NOT NULL COMMENT 'ID источника RSS',
    
    -- Статус обработки
    `status` ENUM('pending', 'processing', 'success', 'failed', 'skipped') 
        NOT NULL DEFAULT 'pending' COMMENT 'Статус генерации',
    
    -- Данные изображения
    `image_path` VARCHAR(1024) NULL DEFAULT NULL COMMENT 'Путь к файлу изображения',
    `image_url` VARCHAR(1024) NULL DEFAULT NULL COMMENT 'URL изображения (если загружено)',
    `image_width` SMALLINT UNSIGNED NULL DEFAULT NULL COMMENT 'Ширина изображения',
    `image_height` SMALLINT UNSIGNED NULL DEFAULT NULL COMMENT 'Высота изображения',
    `image_size_bytes` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Размер файла в байтах',
    `image_format` VARCHAR(20) NULL DEFAULT NULL COMMENT 'Формат изображения (png, jpg, webp)',
    
    -- Промпт для генерации
    `prompt_used` TEXT NULL DEFAULT NULL COMMENT 'Промпт использованный для генерации',
    
    -- Метрики обработки
    `model_used` VARCHAR(150) NULL DEFAULT NULL COMMENT 'Модель AI для генерации',
    `generation_time_ms` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Время генерации в миллисекундах',
    `retry_count` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Количество повторов при ошибках',
    
    -- Ошибки
    `error_message` TEXT NULL DEFAULT NULL COMMENT 'Сообщение об ошибке',
    `error_code` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Код ошибки',
    
    -- Timestamps
    `generated_at` DATETIME NULL DEFAULT NULL COMMENT 'Время успешной генерации',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Время создания записи',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Время последнего обновления',
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_item_id` (`item_id`),
    KEY `idx_feed_id` (`feed_id`),
    KEY `idx_status` (`status`),
    KEY `idx_image_path` (`image_path`(255)),
    KEY `idx_generated_at` (`generated_at`),
    KEY `idx_feed_status` (`feed_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Сгенерированные AI иллюстрации для новостей';

-- ----------------------------------------------------------------------------
-- РАСШИРЕНИЕ ТАБЛИЦЫ ПУБЛИКАЦИЙ
-- ----------------------------------------------------------------------------
-- Добавляем новые поля в существующую таблицу публикаций

ALTER TABLE `rss2tlg_publications` 
    ADD COLUMN IF NOT EXISTS `published_headline` VARCHAR(500) NULL DEFAULT NULL COMMENT 'Опубликованный заголовок' AFTER `message_id`,
    ADD COLUMN IF NOT EXISTS `published_text` TEXT NULL DEFAULT NULL COMMENT 'Опубликованный текст' AFTER `published_headline`,
    ADD COLUMN IF NOT EXISTS `published_language` VARCHAR(10) NULL DEFAULT NULL COMMENT 'Язык публикации' AFTER `published_text`,
    ADD COLUMN IF NOT EXISTS `published_media` JSON NULL DEFAULT NULL COMMENT 'Массив опубликованных медиа-файлов' AFTER `published_language`,
    ADD COLUMN IF NOT EXISTS `published_categories` JSON NULL DEFAULT NULL COMMENT 'Категории новости' AFTER `published_media`,
    ADD COLUMN IF NOT EXISTS `importance_rating` TINYINT UNSIGNED NULL DEFAULT NULL COMMENT 'Рейтинг важности' AFTER `published_categories`;

-- ============================================================================
-- ПРЕДСТАВЛЕНИЯ (VIEWS) для удобной работы с данными
-- ============================================================================

-- Представление: Полная информация о новости со всеми этапами обработки
CREATE OR REPLACE VIEW `v_rss2tlg_full_pipeline` AS
SELECT 
    i.id AS item_id,
    i.feed_id,
    i.title AS original_title,
    i.link,
    i.pub_date,
    i.created_at AS item_created_at,
    
    -- Суммаризация
    s.id AS summarization_id,
    s.status AS summarization_status,
    s.headline,
    s.summary,
    s.category_primary,
    s.category_secondary,
    s.importance_rating,
    s.article_language,
    s.processed_at AS summarized_at,
    
    -- Дедупликация
    d.id AS deduplication_id,
    d.status AS deduplication_status,
    d.is_duplicate,
    d.can_be_published,
    d.similarity_score,
    d.duplicate_of_item_id,
    d.checked_at AS deduplicated_at,
    
    -- Перевод
    t.id AS translation_id,
    t.status AS translation_status,
    t.target_language,
    t.translated_headline,
    t.translated_summary,
    t.quality_score AS translation_quality,
    t.translated_at,
    
    -- Иллюстрация
    il.id AS illustration_id,
    il.status AS illustration_status,
    il.image_path,
    il.generated_at AS illustrated_at,
    
    -- Публикация
    p.id AS publication_id,
    p.destination_type,
    p.destination_id,
    p.message_id,
    p.published_at

FROM rss2tlg_items i
LEFT JOIN rss2tlg_summarization s ON i.id = s.item_id
LEFT JOIN rss2tlg_deduplication d ON i.id = d.item_id
LEFT JOIN rss2tlg_translation t ON i.id = t.item_id
LEFT JOIN rss2tlg_illustration il ON i.id = il.item_id
LEFT JOIN rss2tlg_publications p ON i.id = p.item_id;

-- Представление: Новости готовые к публикации
CREATE OR REPLACE VIEW `v_rss2tlg_ready_to_publish` AS
SELECT 
    i.id AS item_id,
    i.feed_id,
    s.headline,
    COALESCE(t.translated_summary, s.summary) AS text_to_publish,
    COALESCE(t.target_language, s.article_language) AS language,
    s.category_primary,
    s.importance_rating,
    il.image_path,
    i.link,
    i.pub_date
FROM rss2tlg_items i
INNER JOIN rss2tlg_summarization s ON i.id = s.item_id AND s.status = 'success'
INNER JOIN rss2tlg_deduplication d ON i.id = d.item_id AND d.status = 'checked' AND d.can_be_published = 1
LEFT JOIN rss2tlg_translation t ON i.id = t.item_id AND t.status = 'success'
LEFT JOIN rss2tlg_illustration il ON i.id = il.item_id AND il.status = 'success'
WHERE i.is_published = 0
ORDER BY s.importance_rating DESC, i.pub_date DESC;

-- ============================================================================
-- ИНДЕКСЫ для оптимизации производительности
-- ============================================================================
-- Все необходимые индексы уже созданы в таблицах выше

-- ============================================================================
-- КОММЕНТАРИИ
-- ============================================================================
-- 1. Все таблицы используют InnoDB для поддержки транзакций и FK
-- 2. Использован utf8mb4 для поддержки всех Unicode символов включая emoji
-- 3. JSON поля для гибкого хранения массивов и объектов
-- 4. ENUM для статусов обеспечивает строгую типизацию
-- 5. Индексы созданы для всех часто используемых запросов
-- 6. Timestamps для отслеживания истории обработки
-- 7. Метрики AI моделей для мониторинга и оптимизации
-- 8. Поддержка retry_count для отказоустойчивости
-- ============================================================================

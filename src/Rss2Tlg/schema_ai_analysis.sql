-- Таблица для хранения результатов AI-анализа новостей

CREATE TABLE IF NOT EXISTS `rss2tlg_ai_analysis` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Первичный ключ',
    `item_id` INT UNSIGNED NOT NULL COMMENT 'ID новости (FK -> rss2tlg_items.id)',
    `feed_id` INT UNSIGNED NOT NULL COMMENT 'ID источника RSS',
    `prompt_id` VARCHAR(64) NOT NULL COMMENT 'ID промпта для анализа',
    
    -- Статус обработки
    `analysis_status` ENUM('pending', 'processing', 'success', 'failed') NOT NULL DEFAULT 'pending' COMMENT 'Статус анализа',
    `analysis_data` JSON NULL DEFAULT NULL COMMENT 'Полный JSON ответ от AI',
    
    -- Языковая обработка
    `article_language` VARCHAR(10) NULL DEFAULT NULL COMMENT 'Язык статьи (en, ru, и т.д.)',
    `translation_status` VARCHAR(20) NULL DEFAULT NULL COMMENT 'translated|original|failed',
    `translation_quality_score` TINYINT UNSIGNED NULL DEFAULT NULL COMMENT 'Оценка качества перевода (1-10)',
    
    -- Категоризация
    `category_primary` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Основная категория',
    `category_confidence` VARCHAR(20) NULL DEFAULT NULL COMMENT 'high|medium|low',
    `category_secondary` JSON NULL DEFAULT NULL COMMENT 'Дополнительные категории',
    
    -- Контент
    `content_headline` VARCHAR(200) NULL DEFAULT NULL COMMENT 'Заголовок (max 80 символов на русском)',
    `content_summary` TEXT NULL DEFAULT NULL COMMENT 'Краткое содержание (3-10 предложений)',
    `content_keywords` JSON NULL DEFAULT NULL COMMENT 'Ключевые слова',
    
    -- Важность
    `importance_rating` TINYINT UNSIGNED NULL DEFAULT NULL COMMENT 'Рейтинг важности (1-20)',
    `importance_justification` TEXT NULL DEFAULT NULL COMMENT 'Обоснование рейтинга',
    
    -- Дедупликация
    `deduplication_data` JSON NULL DEFAULT NULL COMMENT 'canonical_entities, core_event, numeric_facts, semantic_fingerprint, impact_vector',
    
    -- Флаги качества
    `quality_flags` JSON NULL DEFAULT NULL COMMENT 'direct_speech_preserved, numeric_data_intact, translation_issues_exist, requires_source_link, potential_sensitivity',
    
    -- Метрики обработки
    `model_used` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Модель AI использованная для анализа',
    `tokens_used` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Количество использованных токенов',
    `processing_time_ms` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Время обработки в миллисекундах',
    `cache_hit` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Был ли использован кеш (system prompt)',
    
    -- Ошибки
    `error_message` TEXT NULL DEFAULT NULL COMMENT 'Сообщение об ошибке при анализе',
    
    -- Timestamps
    `analyzed_at` DATETIME NULL DEFAULT NULL COMMENT 'Время успешного анализа',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Время создания записи',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Время последнего обновления',
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_item_id` (`item_id`),
    KEY `idx_feed_id` (`feed_id`),
    KEY `idx_prompt_id` (`prompt_id`),
    KEY `idx_analysis_status` (`analysis_status`),
    KEY `idx_importance_rating` (`importance_rating`),
    KEY `idx_category_primary` (`category_primary`),
    KEY `idx_analyzed_at` (`analyzed_at`),
    KEY `idx_feed_prompt` (`feed_id`, `prompt_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Результаты AI-анализа новостей через OpenRouter';

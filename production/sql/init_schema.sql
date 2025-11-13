-- ============================================================================
-- ПОЛНАЯ СХЕМА БД ДЛЯ RSS2TLG PRODUCTION
-- ============================================================================
-- Версия: 2.0
-- Дата: 2025-11-13
-- Обновления:
--   - Добавлено поле last_error в rss2tlg_feed_state
--   - Добавлены английские поля для кросс-языковой дедупликации в rss2tlg_summarization
--   - Добавлены поля двухэтапной дедупликации в rss2tlg_deduplication
--   - Добавлены поля usage_web и final_cost в openrouter_metrics
-- ============================================================================

-- ----------------------------------------------------------------------------
-- ТАБЛИЦА: rss2tlg_feeds - Источники RSS
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rss2tlg_feeds` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Первичный ключ',
    `name` VARCHAR(255) NOT NULL COMMENT 'Название источника',
    `feed_url` VARCHAR(1024) NOT NULL COMMENT 'URL RSS ленты',
    `website_url` VARCHAR(1024) NULL COMMENT 'URL сайта',
    `enabled` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Активен ли источник',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Время создания',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Время обновления',
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_feed_url` (`feed_url`(255)),
    KEY `idx_enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='RSS источники новостей';

-- ----------------------------------------------------------------------------
-- ТАБЛИЦА: rss2tlg_feed_state - Состояние источников
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rss2tlg_feed_state` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Первичный ключ',
    `feed_id` INT UNSIGNED NOT NULL COMMENT 'Идентификатор источника',
    `url` VARCHAR(512) NOT NULL COMMENT 'URL RSS/Atom ленты',
    `etag` VARCHAR(255) NULL DEFAULT NULL COMMENT 'ETag из последнего успешного ответа',
    `last_modified` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Last-Modified из последнего успешного ответа',
    `last_status` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'HTTP статус код последнего запроса',
    `last_error` TEXT NULL DEFAULT NULL COMMENT 'Текст последней ошибки',
    `error_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Счётчик последовательных ошибок',
    `backoff_until` DATETIME NULL DEFAULT NULL COMMENT 'Время до которого запросы заблокированы',
    `fetched_at` DATETIME NOT NULL COMMENT 'Время последнего запроса',
    `updated_at` DATETIME NOT NULL COMMENT 'Время последнего обновления записи',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Время создания записи',
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_feed_id` (`feed_id`),
    UNIQUE KEY `idx_url` (`url`),
    KEY `idx_backoff_until` (`backoff_until`),
    KEY `idx_error_count` (`error_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Состояние RSS/Atom источников';

-- ----------------------------------------------------------------------------
-- ТАБЛИЦА: rss2tlg_items - Новости из RSS
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rss2tlg_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Первичный ключ',
    `feed_id` INT UNSIGNED NOT NULL COMMENT 'Идентификатор источника',
    `content_hash` VARCHAR(32) NOT NULL COMMENT 'MD5 хеш контента для дедупликации',
    `guid` VARCHAR(512) NULL DEFAULT NULL COMMENT 'GUID элемента из RSS',
    `title` VARCHAR(512) NOT NULL COMMENT 'Заголовок новости',
    `link` VARCHAR(1024) NOT NULL COMMENT 'Ссылка на новость',
    `description` TEXT NULL COMMENT 'Краткое описание',
    `content` MEDIUMTEXT NULL COMMENT 'Полный контент',
    `pub_date` DATETIME NULL DEFAULT NULL COMMENT 'Дата публикации в источнике',
    `author` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Автор',
    `categories` JSON NULL DEFAULT NULL COMMENT 'Категории (массив)',
    `enclosures` JSON NULL DEFAULT NULL COMMENT 'Вложения: изображения, аудио, видео',
    `extracted_content` MEDIUMTEXT NULL COMMENT 'Текст статьи извлеченный с веб-страницы',
    `extracted_images` JSON NULL DEFAULT NULL COMMENT 'Массив изображений из статьи',
    `extracted_metadata` JSON NULL DEFAULT NULL COMMENT 'Мета-данные страницы',
    `extraction_status` ENUM('pending','success','failed','skipped') NOT NULL DEFAULT 'pending' COMMENT 'Статус извлечения контента',
    `extraction_error` TEXT NULL COMMENT 'Сообщение об ошибке при извлечении',
    `extracted_at` DATETIME NULL DEFAULT NULL COMMENT 'Дата и время извлечения контента',
    `is_published` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Флаг публикации в Telegram',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Время создания записи',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Время последнего обновления',
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_content_hash` (`content_hash`),
    KEY `idx_feed_id` (`feed_id`),
    KEY `idx_is_published` (`is_published`),
    KEY `idx_pub_date` (`pub_date`),
    KEY `idx_feed_published` (`feed_id`,`is_published`),
    KEY `idx_extraction_status` (`extraction_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Новости из RSS/Atom источников с извлеченным контентом';

-- ----------------------------------------------------------------------------
-- ТАБЛИЦА: rss2tlg_summarization - AI Суммаризация
-- ----------------------------------------------------------------------------
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
    `category_primary_en` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Основная категория на английском',
    `category_secondary` JSON NULL DEFAULT NULL COMMENT 'Массив дополнительных категорий (до 2)',
    `category_secondary_en` JSON NULL DEFAULT NULL COMMENT 'Массив дополнительных категорий на английском',
    
    -- Контент
    `headline` VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'Заголовок новости',
    `summary` TEXT NULL DEFAULT NULL COMMENT 'Краткое содержание (суммаризация)',
    `keywords` JSON NULL DEFAULT NULL COMMENT 'Массив ключевых слов (до 5)',
    `keywords_en` JSON NULL DEFAULT NULL COMMENT 'Массив ключевых слов на английском',
    
    -- Важность
    `importance_rating` TINYINT UNSIGNED NULL DEFAULT NULL COMMENT 'Рейтинг важности (1-20)',
    
    -- Данные для дедупликации
    `dedup_canonical_entities` JSON NULL DEFAULT NULL COMMENT 'Ключевые сущности для дедупликации',
    `dedup_canonical_entities_en` JSON NULL DEFAULT NULL COMMENT 'Ключевые сущности на английском для дедупликации',
    `dedup_core_event` TEXT NULL DEFAULT NULL COMMENT 'Описание ключевого события',
    `dedup_core_event_en` TEXT NULL DEFAULT NULL COMMENT 'Описание ключевого события на английском',
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
    KEY `idx_category_primary_en` (`category_primary_en`),
    KEY `idx_article_language` (`article_language`),
    KEY `idx_processed_at` (`processed_at`),
    KEY `idx_feed_status` (`feed_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Результаты AI суммаризации и категоризации новостей';

-- ----------------------------------------------------------------------------
-- ТАБЛИЦА: rss2tlg_deduplication - Дедупликация
-- ----------------------------------------------------------------------------
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
    `preliminary_similarity_score` DECIMAL(5,2) NULL DEFAULT NULL COMMENT 'Предварительная оценка схожести (0.00-100.00)',
    `similarity_method` ENUM('ai', 'hash', 'hybrid', 'preliminary') NULL DEFAULT NULL COMMENT 'Метод определения схожести',
    `preliminary_method` VARCHAR(50) NULL DEFAULT 'hybrid_v1' COMMENT 'Метод предварительной оценки (hybrid_v1, jaccard, etc.)',
    `ai_analysis_triggered` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Был ли вызван AI анализ (0=fast path, 1=AI used)',
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
    KEY `idx_preliminary_score` (`preliminary_similarity_score`),
    KEY `idx_ai_triggered` (`ai_analysis_triggered`),
    KEY `idx_checked_at` (`checked_at`),
    KEY `idx_feed_status` (`feed_id`, `status`),
    KEY `idx_publish_ready` (`can_be_published`, `is_duplicate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Результаты проверки новостей на дубликаты';

-- ----------------------------------------------------------------------------
-- ТАБЛИЦА: rss2tlg_publications - Публикации в Telegram
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rss2tlg_publications` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Первичный ключ',
    `item_id` INT UNSIGNED NOT NULL COMMENT 'ID новости (FK -> rss2tlg_items)',
    `feed_id` INT UNSIGNED NOT NULL COMMENT 'ID источника',
    `destination_type` ENUM('bot','channel') NOT NULL COMMENT 'Тип назначения',
    `destination_id` VARCHAR(255) NOT NULL COMMENT 'ID чата или канала',
    `message_id` INT UNSIGNED NOT NULL COMMENT 'ID сообщения в Telegram',
    `published_at` DATETIME NOT NULL COMMENT 'Время публикации',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Время создания записи',
    PRIMARY KEY (`id`),
    KEY `idx_item_id` (`item_id`),
    KEY `idx_feed_id` (`feed_id`),
    KEY `idx_destination` (`destination_type`,`destination_id`),
    KEY `idx_published_at` (`published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Журнал публикаций новостей в Telegram';

-- ----------------------------------------------------------------------------
-- ТАБЛИЦА: openrouter_metrics - Детальные метрики OpenRouter API
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `openrouter_metrics` (
    `id` INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Уникальный идентификатор записи',
    
    -- Идентификация запроса
    `generation_id` VARCHAR(255) NULL COMMENT 'Уникальный ID генерации от OpenRouter',
    `model` VARCHAR(255) NOT NULL COMMENT 'Название модели (например, deepseek/deepseek-chat)',
    `provider_name` VARCHAR(255) NULL COMMENT 'Провайдер модели (DeepInfra, Anthropic, Google)',
    `created_at` BIGINT NULL COMMENT 'Unix timestamp создания запроса от OpenRouter',
    
    -- Временные метрики (миллисекунды)
    `generation_time` INT NULL COMMENT 'Время генерации ответа в мс',
    `latency` INT NULL COMMENT 'Общая задержка запроса в мс',
    `moderation_latency` INT NULL COMMENT 'Время модерации контента в мс',
    
    -- Токены (наши, OpenRouter)
    `tokens_prompt` INT NULL COMMENT 'Количество токенов в промпте (OpenRouter подсчет)',
    `tokens_completion` INT NULL COMMENT 'Количество токенов в ответе (OpenRouter подсчет)',
    
    -- Токены (native провайдера)
    `native_tokens_prompt` INT NULL COMMENT 'Токены промпта по подсчету провайдера',
    `native_tokens_completion` INT NULL COMMENT 'Токены ответа по подсчету провайдера',
    `native_tokens_cached` INT NULL COMMENT 'Закешированные токены (prompt caching)',
    `native_tokens_reasoning` INT NULL COMMENT 'Токены рассуждений (для reasoning моделей)',
    
    -- Стоимость (USD)
    `usage_total` DECIMAL(10, 8) NULL COMMENT 'Общая стоимость запроса в USD',
    `usage_cache` DECIMAL(10, 8) NULL COMMENT 'Стоимость использования кеша в USD',
    `usage_data` DECIMAL(10, 8) NULL COMMENT 'Стоимость веб-поиска/data retrieval в USD',
    `usage_web` DECIMAL(10, 8) NULL COMMENT 'Стоимость веб-поиска в USD',
    `usage_file` DECIMAL(10, 8) NULL COMMENT 'Стоимость обработки файлов в USD',
    `final_cost` DECIMAL(10, 8) NULL COMMENT 'Финальная стоимость после всех скидок (копия usage_total)',
    
    -- Статус завершения
    `finish_reason` VARCHAR(50) NULL COMMENT 'Причина завершения (stop, length, content_filter)',
    
    -- Контекст использования
    `pipeline_module` VARCHAR(100) NULL COMMENT 'Модуль pipeline (Summarization, Deduplication, Translation)',
    `batch_id` INT NULL COMMENT 'ID batch обработки (если применимо)',
    `task_context` TEXT NULL COMMENT 'Дополнительный контекст задачи (JSON)',
    
    -- Полный ответ для расширения
    `full_response` JSON NULL COMMENT 'Полный JSON ответ от OpenRouter для будущего анализа',
    
    -- Timestamp записи
    `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Время записи метрик в БД',
    
    -- Индексы для оптимизации запросов
    INDEX `idx_model` (`model`),
    INDEX `idx_provider` (`provider_name`),
    INDEX `idx_generation_id` (`generation_id`),
    INDEX `idx_pipeline_module` (`pipeline_module`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_recorded_at` (`recorded_at`),
    INDEX `idx_batch_id` (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Детальные метрики OpenRouter API';

-- ============================================================================
-- ИНИЦИАЛИЗАЦИЯ ДАННЫХ
-- ============================================================================

-- Таблицы для RSS2TLG с AI анализом

DROP TABLE IF EXISTS `rss2tlg_ai_analysis`;
DROP TABLE IF EXISTS `rss2tlg_publications`;
DROP TABLE IF EXISTS `rss2tlg_items`;
DROP TABLE IF EXISTS `rss2tlg_feed_state`;

CREATE TABLE `rss2tlg_feed_state` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Первичный ключ',
  `feed_id` int unsigned NOT NULL COMMENT 'Идентификатор источника (из конфигурации)',
  `url` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'URL RSS/Atom ленты',
  `etag` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ETag из последнего успешного ответа',
  `last_modified` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Last-Modified из последнего успешного ответа',
  `last_status` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'HTTP статус код последнего запроса (0 = сетевая ошибка)',
  `last_error` TEXT DEFAULT NULL COMMENT 'Текст последней ошибки',
  `error_count` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'Счётчик последовательных ошибок',
  `backoff_until` datetime DEFAULT NULL COMMENT 'Время до которого запросы заблокированы (exponential backoff)',
  `fetched_at` datetime NOT NULL COMMENT 'Время последнего запроса',
  `updated_at` datetime NOT NULL COMMENT 'Время последнего обновления записи',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Время создания записи',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_feed_id` (`feed_id`),
  UNIQUE KEY `idx_url` (`url`),
  KEY `idx_backoff_until` (`backoff_until`),
  KEY `idx_error_count` (`error_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Состояние RSS/Atom источников для модуля fetch';

CREATE TABLE `rss2tlg_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Первичный ключ',
  `feed_id` int unsigned NOT NULL COMMENT 'Идентификатор источника',
  `content_hash` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'MD5 хеш контента для дедупликации',
  `guid` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'GUID элемента из RSS',
  `title` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Заголовок новости',
  `link` varchar(1024) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ссылка на новость',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT 'Краткое описание',
  `content` mediumtext COLLATE utf8mb4_unicode_ci COMMENT 'Полный контент',
  `pub_date` datetime DEFAULT NULL COMMENT 'Дата публикации в источнике',
  `author` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Автор',
  `categories` json DEFAULT NULL COMMENT 'Категории (массив)',
  `enclosures` json DEFAULT NULL COMMENT 'Вложения: изображения, аудио, видео',
  `extracted_content` mediumtext COLLATE utf8mb4_unicode_ci COMMENT 'Текст статьи, извлеченный с веб-страницы',
  `extracted_images` json DEFAULT NULL COMMENT 'Массив изображений из статьи',
  `extracted_metadata` json DEFAULT NULL COMMENT 'Мета-данные страницы (Open Graph, Twitter Cards)',
  `extraction_status` enum('pending','success','failed','skipped') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'Статус извлечения контента',
  `extraction_error` text COLLATE utf8mb4_unicode_ci COMMENT 'Сообщение об ошибке при извлечении',
  `extracted_at` datetime DEFAULT NULL COMMENT 'Дата и время извлечения контента',
  `is_published` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Флаг публикации в Telegram',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Время создания записи',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Время последнего обновления',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_content_hash` (`content_hash`),
  KEY `idx_feed_id` (`feed_id`),
  KEY `idx_is_published` (`is_published`),
  KEY `idx_pub_date` (`pub_date`),
  KEY `idx_feed_published` (`feed_id`,`is_published`),
  KEY `idx_extraction_status` (`extraction_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Новости из RSS/Atom источников с извлеченным контентом';

CREATE TABLE `rss2tlg_publications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Первичный ключ',
  `item_id` int unsigned NOT NULL COMMENT 'ID новости (FK -> rss2tlg_items)',
  `feed_id` int unsigned NOT NULL COMMENT 'ID источника',
  `destination_type` enum('bot','channel') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Тип назначения',
  `destination_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ID чата или канала',
  `message_id` int unsigned NOT NULL COMMENT 'ID сообщения в Telegram',
  `published_at` datetime NOT NULL COMMENT 'Время публикации',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Время создания записи',
  PRIMARY KEY (`id`),
  KEY `idx_item_id` (`item_id`),
  KEY `idx_feed_id` (`feed_id`),
  KEY `idx_destination` (`destination_type`,`destination_id`),
  KEY `idx_published_at` (`published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Журнал публикаций новостей в Telegram';

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

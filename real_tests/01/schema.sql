-- RSS2TLG Database Schema
-- Created for real testing

-- RSS Feed State Table
CREATE TABLE IF NOT EXISTS `rss2tlg_feed_state` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Первичный ключ',
  `feed_id` int unsigned NOT NULL COMMENT 'Идентификатор источника (из конфигурации)',
  `url` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'URL RSS/Atom ленты',
  `etag` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ETag из последнего успешного ответа',
  `last_modified` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Last-Modified из последнего успешного ответа',
  `last_status` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'HTTP статус код последнего запроса (0 = сетевая ошибка)',
  `error_count` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'Счётчик последовательных ошибок',
  `backoff_until` datetime DEFAULT NULL COMMENT 'Время до которого запросы заблокированы (exponential backoff)',
  `fetched_at` datetime NOT NULL COMMENT 'Время последнего запроса',
  `updated_at` datetime NOT NULL COMMENT 'Время последнего обновления записи',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Время создания записи',
  `last_error` text COLLATE utf8mb4_unicode_ci COMMENT 'Сообщение об ошибке последнего запроса',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_feed_id` (`feed_id`),
  UNIQUE KEY `idx_url` (`url`),
  KEY `idx_backoff_until` (`backoff_until`),
  KEY `idx_error_count` (`error_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Состояние RSS/Atom источников для модуля fetch';

-- RSS Items Table
CREATE TABLE IF NOT EXISTS `rss2tlg_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Первичный ключ',
  `feed_id` int unsigned NOT NULL COMMENT 'Идентификатор источника',
  `content_hash` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'MD5 хеш контента для дедупликации',
  `guid` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'GUID элемента из RSS',
  `title` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Заголовок новости',
  `link` varchar(1024) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ссылка на новость',
  `summary` text COLLATE utf8mb4_unicode_ci COMMENT 'Краткое описание',
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

-- Publications Table
CREATE TABLE IF NOT EXISTS `rss2tlg_publications` (
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

-- AI Analysis Table
CREATE TABLE IF NOT EXISTS `rss2tlg_ai_analysis` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Первичный ключ',
  `item_id` int unsigned NOT NULL COMMENT 'ID новости (FK -> rss2tlg_items)',
  `prompt_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ID промпта',
  `analysis_data` json DEFAULT NULL COMMENT 'Результат анализа в JSON',
  `category_primary` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Основная категория',
  `importance_rating` tinyint unsigned DEFAULT NULL COMMENT 'Рейтинг важности (0-20)',
  `deduplication_data` json DEFAULT NULL COMMENT 'Данные для дедупликации',
  `translation_status` enum('pending','completed','rejected','translation_issues','success','failed') COLLATE utf8mb4_unicode_ci DEFAULT 'pending' COMMENT 'Статус перевода',
  `translation_quality_score` tinyint unsigned DEFAULT NULL COMMENT 'Оценка качества перевода (0-100)',
  `content_headline` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Переведенный заголовок',
  `content_summary` text COLLATE utf8mb4_unicode_ci COMMENT 'Переведенное описание',
  `content_body` mediumtext COLLATE utf8mb4_unicode_ci COMMENT 'Переведенный текст статьи',
  `article_language` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Язык статьи',
  `analysis_status` enum('pending','processing','success','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'Статус анализа',
  `tokens_used` int unsigned DEFAULT NULL COMMENT 'Количество использованных токенов',
  `processing_time_ms` int unsigned DEFAULT NULL COMMENT 'Время обработки в миллисекундах',
  `model_used` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Использованная модель',
  `cache_hit` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Был ли использован кеш',
  `error_message` text COLLATE utf8mb4_unicode_ci COMMENT 'Сообщение об ошибке',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Время создания записи',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Время последнего обновления',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_item_prompt` (`item_id`,`prompt_id`),
  KEY `idx_item_id` (`item_id`),
  KEY `idx_status` (`analysis_status`),
  KEY `idx_category` (`category_primary`),
  KEY `idx_importance` (`importance_rating`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Результаты AI анализа новостей';

-- Feed Configuration Table (для теста)
CREATE TABLE IF NOT EXISTS `rss2tlg_feed_config` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Первичный ключ',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Название источника',
  `url` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'URL RSS/Atom ленты',
  `language` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Язык контента',
  `category` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Категория',
  `enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Активен ли источник',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Время создания',
  PRIMARY KEY (`id`),
  KEY `idx_enabled` (`enabled`),
  KEY `idx_language` (`language`),
  KEY `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Конфигурация RSS источников';
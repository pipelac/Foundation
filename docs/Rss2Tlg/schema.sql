-- Таблица для хранения состояния RSS/Atom источников
-- Используется модулем fetch для оптимизации запросов через Conditional GET

CREATE TABLE IF NOT EXISTS `rss2tlg_feed_state` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Первичный ключ',
    `feed_id` INT UNSIGNED NOT NULL COMMENT 'Идентификатор источника (из конфигурации)',
    `url` VARCHAR(512) NOT NULL COMMENT 'URL RSS/Atom ленты',
    
    -- Conditional GET заголовки
    `etag` VARCHAR(255) NULL DEFAULT NULL COMMENT 'ETag из последнего успешного ответа',
    `last_modified` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Last-Modified из последнего успешного ответа',
    
    -- Статус и метрики
    `last_status` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'HTTP статус код последнего запроса (0 = сетевая ошибка)',
    `error_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Счётчик последовательных ошибок',
    `backoff_until` DATETIME NULL DEFAULT NULL COMMENT 'Время до которого запросы заблокированы (exponential backoff)',
    
    -- Временные метки
    `fetched_at` DATETIME NOT NULL COMMENT 'Время последнего запроса',
    `updated_at` DATETIME NOT NULL COMMENT 'Время последнего обновления записи',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Время создания записи',
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_feed_id` (`feed_id`),
    UNIQUE KEY `idx_url` (`url`),
    KEY `idx_backoff_until` (`backoff_until`),
    KEY `idx_error_count` (`error_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Состояние RSS/Atom источников для модуля fetch';

-- Примеры использования:

-- Получить состояние источника по ID
-- SELECT * FROM rss2tlg_feed_state WHERE feed_id = 1;

-- Получить все источники в backoff
-- SELECT feed_id, url, backoff_until, TIMESTAMPDIFF(SECOND, NOW(), backoff_until) AS backoff_remaining_sec
-- FROM rss2tlg_feed_state
-- WHERE backoff_until IS NOT NULL AND backoff_until > NOW();

-- Сбросить ошибки для источника
-- UPDATE rss2tlg_feed_state SET error_count = 0, backoff_until = NULL WHERE feed_id = 1;

-- Получить статистику по источникам
-- SELECT 
--     COUNT(*) AS total_feeds,
--     SUM(CASE WHEN last_status = 200 THEN 1 ELSE 0 END) AS success_200,
--     SUM(CASE WHEN last_status = 304 THEN 1 ELSE 0 END) AS not_modified_304,
--     SUM(CASE WHEN last_status >= 400 THEN 1 ELSE 0 END) AS errors_4xx_5xx,
--     SUM(CASE WHEN backoff_until IS NOT NULL AND backoff_until > NOW() THEN 1 ELSE 0 END) AS in_backoff
-- FROM rss2tlg_feed_state;

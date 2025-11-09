-- ============================================================================
-- Схема для хранения статистики AI Pipeline
-- ============================================================================

-- Таблица для хранения статистики запусков скриптов
CREATE TABLE IF NOT EXISTS `rss2tlg_statistics` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `script_name` varchar(100) NOT NULL COMMENT 'Название скрипта',
  `script_version` varchar(20) NOT NULL COMMENT 'Версия скрипта',
  `run_date` datetime NOT NULL COMMENT 'Дата и время запуска',
  `items_total` int unsigned NOT NULL DEFAULT 0 COMMENT 'Всего новостей',
  `items_processed` int unsigned NOT NULL DEFAULT 0 COMMENT 'Обработано новостей',
  `items_success` int unsigned NOT NULL DEFAULT 0 COMMENT 'Успешно обработано',
  `items_failed` int unsigned NOT NULL DEFAULT 0 COMMENT 'Ошибок при обработке',
  `items_skipped` int unsigned NOT NULL DEFAULT 0 COMMENT 'Пропущено (уже обработано)',
  `total_tokens` int unsigned NOT NULL DEFAULT 0 COMMENT 'Всего токенов использовано',
  `total_tokens_prompt` int unsigned NOT NULL DEFAULT 0 COMMENT 'Токены в промптах',
  `total_tokens_completion` int unsigned NOT NULL DEFAULT 0 COMMENT 'Токены в ответах',
  `total_tokens_cached` int unsigned NOT NULL DEFAULT 0 COMMENT 'Кешированные токены',
  `cache_hits` int unsigned NOT NULL DEFAULT 0 COMMENT 'Количество cache hits',
  `processing_time_ms` int unsigned NOT NULL DEFAULT 0 COMMENT 'Время обработки в миллисекундах',
  `models_used` json DEFAULT NULL COMMENT 'Использованные модели {model: count}',
  `errors` json DEFAULT NULL COMMENT 'Список ошибок',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_script_name` (`script_name`),
  KEY `idx_run_date` (`run_date`),
  KEY `idx_script_date` (`script_name`, `run_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Статистика запусков AI скриптов';

-- Таблица для хранения ежесуточных сводок
CREATE TABLE IF NOT EXISTS `rss2tlg_daily_summaries` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `summary_date` date NOT NULL COMMENT 'Дата сводки (за какой день)',
  `script_name` varchar(100) NOT NULL COMMENT 'Название скрипта',
  `summary_data` json NOT NULL COMMENT 'Детальные данные сводки',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_date_script` (`summary_date`, `script_name`),
  KEY `idx_summary_date` (`summary_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ежесуточные сводки работы AI Pipeline';

-- Представление для быстрого доступа к статистике за период
CREATE OR REPLACE VIEW `v_rss2tlg_statistics_daily` AS
SELECT 
    DATE(run_date) as date,
    script_name,
    COUNT(*) as total_runs,
    SUM(items_total) as total_items,
    SUM(items_processed) as total_processed,
    SUM(items_success) as total_success,
    SUM(items_failed) as total_failed,
    SUM(items_skipped) as total_skipped,
    SUM(total_tokens) as total_tokens,
    SUM(total_tokens_prompt) as total_tokens_prompt,
    SUM(total_tokens_completion) as total_tokens_completion,
    SUM(total_tokens_cached) as total_tokens_cached,
    SUM(cache_hits) as total_cache_hits,
    ROUND(AVG(processing_time_ms), 2) as avg_time_ms,
    ROUND(SUM(total_tokens) / NULLIF(SUM(items_success), 0), 2) as avg_tokens_per_item
FROM rss2tlg_statistics
GROUP BY DATE(run_date), script_name
ORDER BY date DESC, script_name;

-- Представление для статистики по моделям
CREATE OR REPLACE VIEW `v_rss2tlg_model_usage` AS
SELECT 
    DATE(run_date) as date,
    script_name,
    JSON_UNQUOTE(JSON_EXTRACT(models_used, CONCAT('$."', k.model_key, '"'))) as usage_count,
    k.model_key as model_name
FROM rss2tlg_statistics
CROSS JOIN (
    SELECT 'anthropic/claude-3.5-sonnet' as model_key
    UNION ALL
    SELECT 'deepseek/deepseek-chat'
) k
WHERE models_used IS NOT NULL
  AND JSON_EXTRACT(models_used, CONCAT('$."', k.model_key, '"')) IS NOT NULL
ORDER BY date DESC, script_name, model_name;

-- ============================================================================
-- Проверка версии схемы RSS2TLG Production
-- ============================================================================
-- Использование:
--   mysql -u root -p rss2tlg_production < check_schema_version.sql
-- ============================================================================

SELECT 
    '=== RSS2TLG PRODUCTION SCHEMA VERSION CHECK ===' AS '';

-- Проверка существования таблиц
SELECT 
    '📊 Existing Tables:' AS '',
    GROUP_CONCAT(TABLE_NAME ORDER BY TABLE_NAME SEPARATOR ', ') AS tables
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME LIKE 'rss2tlg_%' OR TABLE_NAME = 'openrouter_metrics';

-- Проверка новых полей версии 2.0
SELECT 
    '✅ Version 2.0 Fields Check:' AS '';

SELECT 
    CASE 
        WHEN COUNT(*) = 5 THEN '✅ Schema Version 2.0 - All fields present'
        WHEN COUNT(*) = 0 THEN '⚠️  Schema Version 1.0 - Update required'
        ELSE CONCAT('⚠️  Schema Version MIXED - Found ', COUNT(*), ' of 5 expected fields')
    END AS version_status,
    COUNT(*) AS fields_found,
    5 AS fields_expected
FROM (
    SELECT COLUMN_NAME, TABLE_NAME
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
      AND (
          (TABLE_NAME = 'rss2tlg_feed_state' AND COLUMN_NAME = 'last_error')
          OR (TABLE_NAME = 'rss2tlg_summarization' AND COLUMN_NAME = 'category_primary_en')
          OR (TABLE_NAME = 'rss2tlg_deduplication' AND COLUMN_NAME = 'preliminary_similarity_score')
          OR (TABLE_NAME = 'openrouter_metrics' AND COLUMN_NAME = 'usage_web')
          OR (TABLE_NAME = 'openrouter_metrics' AND COLUMN_NAME = 'final_cost')
      )
) AS v2_fields;

-- Детальная информация о полях v2.0
SELECT 
    '📋 Detailed Fields Info:' AS '';

SELECT 
    TABLE_NAME AS 'table',
    COLUMN_NAME AS 'field',
    COLUMN_TYPE AS 'type',
    IS_NULLABLE AS 'nullable',
    COLUMN_DEFAULT AS 'default',
    COLUMN_COMMENT AS 'comment',
    CASE 
        WHEN COLUMN_NAME IS NOT NULL THEN '✅'
        ELSE '❌'
    END AS 'status'
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE()
  AND (
      (TABLE_NAME = 'rss2tlg_feed_state' AND COLUMN_NAME = 'last_error')
      OR (TABLE_NAME = 'rss2tlg_summarization' AND COLUMN_NAME IN ('category_primary_en', 'category_secondary_en', 'keywords_en', 'dedup_canonical_entities_en', 'dedup_core_event_en'))
      OR (TABLE_NAME = 'rss2tlg_deduplication' AND COLUMN_NAME IN ('preliminary_similarity_score', 'preliminary_method', 'ai_analysis_triggered'))
      OR (TABLE_NAME = 'openrouter_metrics' AND COLUMN_NAME IN ('usage_web', 'final_cost'))
  )
ORDER BY TABLE_NAME, COLUMN_NAME;

-- Проверка индексов v2.0
SELECT 
    '🔍 Version 2.0 Indexes Check:' AS '';

SELECT 
    TABLE_NAME AS 'table',
    INDEX_NAME AS 'index',
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ', ') AS 'columns',
    CASE 
        WHEN NON_UNIQUE = 0 THEN 'UNIQUE'
        ELSE 'INDEX'
    END AS 'type'
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE()
  AND INDEX_NAME IN (
      'idx_category_primary_en',
      'idx_preliminary_score',
      'idx_ai_triggered'
  )
GROUP BY TABLE_NAME, INDEX_NAME, NON_UNIQUE
ORDER BY TABLE_NAME, INDEX_NAME;

-- Статистика таблиц
SELECT 
    '📈 Tables Statistics:' AS '';

SELECT 
    TABLE_NAME AS 'table',
    TABLE_ROWS AS 'rows',
    ROUND(DATA_LENGTH / 1024 / 1024, 2) AS 'data_mb',
    ROUND(INDEX_LENGTH / 1024 / 1024, 2) AS 'index_mb',
    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS 'total_mb',
    ENGINE AS 'engine',
    TABLE_COLLATION AS 'collation'
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE()
  AND (TABLE_NAME LIKE 'rss2tlg_%' OR TABLE_NAME = 'openrouter_metrics')
ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC;

-- Рекомендации по обновлению
SELECT 
    '💡 Upgrade Recommendations:' AS '';

SELECT 
    CASE 
        WHEN (SELECT COUNT(*) FROM information_schema.COLUMNS 
              WHERE TABLE_SCHEMA = DATABASE() 
                AND COLUMN_NAME = 'last_error' 
                AND TABLE_NAME = 'rss2tlg_feed_state') = 0
        THEN '⚠️  Missing: rss2tlg_feed_state.last_error - Run migration_add_last_error.sql'
        ELSE '✅ OK: rss2tlg_feed_state.last_error'
    END AS recommendation
UNION ALL
SELECT 
    CASE 
        WHEN (SELECT COUNT(*) FROM information_schema.COLUMNS 
              WHERE TABLE_SCHEMA = DATABASE() 
                AND COLUMN_NAME = 'category_primary_en' 
                AND TABLE_NAME = 'rss2tlg_summarization') = 0
        THEN '⚠️  Missing: EN fields in rss2tlg_summarization - Run migration_add_en_fields.sql'
        ELSE '✅ OK: rss2tlg_summarization EN fields'
    END AS recommendation
UNION ALL
SELECT 
    CASE 
        WHEN (SELECT COUNT(*) FROM information_schema.COLUMNS 
              WHERE TABLE_SCHEMA = DATABASE() 
                AND COLUMN_NAME = 'preliminary_similarity_score' 
                AND TABLE_NAME = 'rss2tlg_deduplication') = 0
        THEN '⚠️  Missing: Preliminary fields in rss2tlg_deduplication - Run migration_dedup_v3.sql'
        ELSE '✅ OK: rss2tlg_deduplication preliminary fields'
    END AS recommendation
UNION ALL
SELECT 
    CASE 
        WHEN (SELECT COUNT(*) FROM information_schema.COLUMNS 
              WHERE TABLE_SCHEMA = DATABASE() 
                AND COLUMN_NAME = 'usage_web' 
                AND TABLE_NAME = 'openrouter_metrics') = 0
        THEN '⚠️  Missing: openrouter_metrics.usage_web - Run migration_add_usage_web.sql'
        ELSE '✅ OK: openrouter_metrics.usage_web'
    END AS recommendation;

SELECT 
    '=== END OF VERSION CHECK ===' AS '';

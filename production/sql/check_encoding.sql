-- ============================================================================
-- ПРОВЕРКА КОДИРОВКИ БД И ТАБЛИЦ
-- ============================================================================
-- Используйте этот скрипт для проверки правильности установки кодировки
-- после импорта init_schema.sql
-- 
-- Запуск:
-- mysql -u rss2tlg_user -p rss2tlg < production/sql/check_encoding.sql
-- ============================================================================

SELECT '============================================' as '';
SELECT 'ПРОВЕРКА КОДИРОВКИ БАЗЫ ДАННЫХ' as '';
SELECT '============================================' as '';

-- Проверка кодировки БД
SELECT 
    SCHEMA_NAME as 'База данных',
    DEFAULT_CHARACTER_SET_NAME as 'Кодировка',
    DEFAULT_COLLATION_NAME as 'Collation'
FROM information_schema.SCHEMATA 
WHERE SCHEMA_NAME = DATABASE();

SELECT '' as '';
SELECT '============================================' as '';
SELECT 'ПРОВЕРКА КОДИРОВКИ ТАБЛИЦ' as '';
SELECT '============================================' as '';

-- Проверка кодировки таблиц
SELECT 
    TABLE_NAME as 'Таблица',
    TABLE_COLLATION as 'Collation',
    CASE 
        WHEN TABLE_COLLATION = 'utf8mb4_unicode_ci' THEN '✅ OK'
        ELSE '❌ НЕВЕРНО'
    END as 'Статус'
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME;

SELECT '' as '';
SELECT '============================================' as '';
SELECT 'ПРОВЕРКА ТЕКСТОВЫХ ПОЛЕЙ' as '';
SELECT '============================================' as '';

-- Проверка кодировки полей
SELECT 
    TABLE_NAME as 'Таблица',
    COLUMN_NAME as 'Поле',
    CHARACTER_SET_NAME as 'Кодировка',
    COLLATION_NAME as 'Collation',
    CASE 
        WHEN CHARACTER_SET_NAME = 'utf8mb4' THEN '✅ OK'
        WHEN CHARACTER_SET_NAME IS NULL THEN '⚪ N/A'
        ELSE '❌ НЕВЕРНО'
    END as 'Статус'
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
    AND DATA_TYPE IN ('varchar', 'char', 'text', 'mediumtext', 'longtext', 'tinytext')
ORDER BY TABLE_NAME, COLUMN_NAME;

SELECT '' as '';
SELECT '============================================' as '';
SELECT 'ТЕКУЩИЕ НАСТРОЙКИ СОЕДИНЕНИЯ' as '';
SELECT '============================================' as '';

-- Текущие настройки кодировки соединения
SHOW VARIABLES LIKE 'character_set_%';
SHOW VARIABLES LIKE 'collation_%';

SELECT '' as '';
SELECT '============================================' as '';
SELECT 'ИТОГ' as '';
SELECT '============================================' as '';

-- Итоговая проверка
SELECT 
    CASE 
        WHEN COUNT(*) = 0 THEN '✅ ВСЕ ТАБЛИЦЫ ИСПОЛЬЗУЮТ ПРАВИЛЬНУЮ КОДИРОВКУ'
        ELSE CONCAT('❌ НАЙДЕНО ', COUNT(*), ' ТАБЛИЦ С НЕПРАВИЛЬНОЙ КОДИРОВКОЙ')
    END as 'Результат'
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_COLLATION != 'utf8mb4_unicode_ci';

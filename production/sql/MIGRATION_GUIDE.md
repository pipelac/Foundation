# 🚀 Руководство по миграции схемы RSS2TLG

## 📌 Краткая информация

**Текущая версия схемы**: 2.0 (2025-11-13)  
**Предыдущая версия**: 1.0 (2025-11-11)

## 🎯 Основные изменения v2.0

### 1️⃣ Поддержка хранения ошибок
**Таблица**: `rss2tlg_feed_state`  
**Новое поле**: `last_error TEXT`

Зачем: Диагностика проблем с RSS источниками

```sql
-- Пример использования
SELECT feed_id, url, last_status, last_error, error_count 
FROM rss2tlg_feed_state 
WHERE error_count > 0 
ORDER BY error_count DESC;
```

### 2️⃣ Кросс-языковая дедупликация
**Таблица**: `rss2tlg_summarization`  
**Новые поля**: 5 английских полей для нормализации метаданных

| Поле | Тип | Назначение |
|------|-----|------------|
| `category_primary_en` | VARCHAR(100) | Категория на английском |
| `category_secondary_en` | JSON | Доп. категории на английском |
| `keywords_en` | JSON | Ключевые слова на английском |
| `dedup_canonical_entities_en` | JSON | Сущности на английском |
| `dedup_core_event_en` | TEXT | Событие на английском |

Зачем: Дедупликация новостей на русском, английском, и других языках через единый английский pivot

```sql
-- Пример: Найти статьи об одном событии на разных языках
SELECT 
    s1.item_id,
    s1.article_language,
    s1.headline,
    s1.dedup_core_event_en
FROM rss2tlg_summarization s1
JOIN rss2tlg_summarization s2 
    ON s1.dedup_core_event_en = s2.dedup_core_event_en
    AND s1.article_language != s2.article_language
WHERE s1.dedup_core_event_en IS NOT NULL;
```

### 3️⃣ Двухэтапная дедупликация
**Таблица**: `rss2tlg_deduplication`  
**Новые поля**: Поддержка экономии на AI вызовах

| Поле | Тип | Назначение |
|------|-----|------------|
| `preliminary_similarity_score` | DECIMAL(5,2) | Быстрая оценка (0-100) |
| `preliminary_method` | VARCHAR(50) | Метод (hybrid_v1, jaccard) |
| `ai_analysis_triggered` | TINYINT(1) | Был ли вызов AI (0/1) |

**Алгоритм**:
1. **Preliminary check** - быстрая эвристика (Jaccard, TF-IDF)
2. Если схожесть > 70% → **AI анализ**
3. Если схожесть < 30% → пропускаем (экономим токены)

```sql
-- Аналитика эффективности двухэтапной дедупликации
SELECT 
    COUNT(*) AS total_checks,
    SUM(CASE WHEN ai_analysis_triggered = 1 THEN 1 ELSE 0 END) AS ai_used,
    SUM(CASE WHEN ai_analysis_triggered = 0 THEN 1 ELSE 0 END) AS fast_path,
    ROUND(100.0 * SUM(ai_analysis_triggered) / COUNT(*), 2) AS ai_usage_percent,
    AVG(preliminary_similarity_score) AS avg_preliminary_score
FROM rss2tlg_deduplication
WHERE preliminary_similarity_score IS NOT NULL;
```

### 4️⃣ Расширенные метрики OpenRouter
**Таблица**: `openrouter_metrics`  
**Новые поля**: Детальная стоимость API вызовов

| Поле | Тип | Назначение |
|------|-----|------------|
| `usage_web` | DECIMAL(10,8) | Стоимость веб-поиска |
| `final_cost` | DECIMAL(10,8) | Финальная стоимость |

**Важно**: 
- `usage_total` УЖЕ содержит финальную стоимость от OpenRouter
- `usage_cache` и `usage_data` - информационные поля (приходят отрицательными = скидки)
- `final_cost` = копия `usage_total` для удобства

```sql
-- Анализ стоимости по модулям pipeline
SELECT 
    pipeline_module,
    COUNT(*) AS requests,
    SUM(final_cost) AS total_cost,
    AVG(final_cost) AS avg_cost,
    SUM(tokens_prompt + tokens_completion) AS total_tokens
FROM openrouter_metrics
WHERE final_cost IS NOT NULL
GROUP BY pipeline_module
ORDER BY total_cost DESC;
```

---

## 🔧 Инструкции по миграции

### ✅ Вариант А: Новая установка (рекомендуется)

```bash
# 1. Создать БД
mysql -u root -p << 'EOF'
CREATE DATABASE IF NOT EXISTS rss2tlg_production 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'rss2tlg'@'localhost' 
    IDENTIFIED BY 'your_secure_password';

GRANT ALL PRIVILEGES ON rss2tlg_production.* 
    TO 'rss2tlg'@'localhost';

FLUSH PRIVILEGES;
EOF

# 2. Применить схему v2.0
mysql -u root -p rss2tlg_production < init_schema.sql

# 3. Проверить версию
mysql -u root -p rss2tlg_production < check_schema_version.sql
```

### ✅ Вариант Б: Обновление существующей БД

**⚠️ Важно**: Сделайте бэкап перед миграцией!

```bash
# 1. Бэкап
mysqldump -u root -p rss2tlg_production > backup_$(date +%Y%m%d_%H%M%S).sql

# 2. Проверить текущую версию
mysql -u root -p rss2tlg_production < check_schema_version.sql

# 3. Применить миграции (только недостающие!)
mysql -u root -p rss2tlg_production < migration_add_en_fields.sql
mysql -u root -p rss2tlg_production < migration_dedup_v3.sql
mysql -u root -p rss2tlg_production < migration_add_usage_web.sql

# 4. Добавить поле last_error (если нет отдельной миграции)
mysql -u root -p rss2tlg_production << 'EOF'
ALTER TABLE rss2tlg_feed_state 
ADD COLUMN IF NOT EXISTS last_error TEXT NULL DEFAULT NULL 
COMMENT 'Текст последней ошибки' 
AFTER last_status;

ALTER TABLE openrouter_metrics 
ADD COLUMN IF NOT EXISTS final_cost DECIMAL(10, 8) NULL 
COMMENT 'Финальная стоимость после всех скидок' 
AFTER usage_file;
EOF

# 5. Проверить результат
mysql -u root -p rss2tlg_production < check_schema_version.sql
```

### ✅ Вариант В: Миграция без даунтайма

**Для production с активным трафиком**

```bash
# 1. Создать тестовую БД
mysql -u root -p << 'EOF'
CREATE DATABASE rss2tlg_test LIKE rss2tlg_production;
EOF

# 2. Скопировать структуру (без данных)
mysqldump -u root -p --no-data rss2tlg_production | \
    mysql -u root -p rss2tlg_test

# 3. Применить миграции на тестовой БД
mysql -u root -p rss2tlg_test < migration_add_en_fields.sql
mysql -u root -p rss2tlg_test < migration_dedup_v3.sql
mysql -u root -p rss2tlg_test < migration_add_usage_web.sql

# 4. Проверить тестовую БД
mysql -u root -p rss2tlg_test < check_schema_version.sql

# 5. Если всё OK - применить на production (быстрая операция)
mysql -u root -p rss2tlg_production < migration_add_en_fields.sql
mysql -u root -p rss2tlg_production < migration_dedup_v3.sql
mysql -u root -p rss2tlg_production < migration_add_usage_web.sql
```

---

## 🔍 Проверка успешности миграции

### Автоматическая проверка
```bash
mysql -u root -p rss2tlg_production < check_schema_version.sql
```

Ожидаемый результат:
```
✅ Schema Version 2.0 - All fields present
fields_found: 5
fields_expected: 5
```

### Ручная проверка
```sql
-- Проверить новые поля
SHOW COLUMNS FROM rss2tlg_feed_state LIKE 'last_error';
SHOW COLUMNS FROM rss2tlg_summarization LIKE '%_en';
SHOW COLUMNS FROM rss2tlg_deduplication LIKE 'preliminary%';
SHOW COLUMNS FROM rss2tlg_deduplication LIKE 'ai_analysis_triggered';
SHOW COLUMNS FROM openrouter_metrics LIKE 'usage_web';
SHOW COLUMNS FROM openrouter_metrics LIKE 'final_cost';

-- Проверить индексы
SHOW INDEX FROM rss2tlg_summarization WHERE Key_name = 'idx_category_primary_en';
SHOW INDEX FROM rss2tlg_deduplication WHERE Key_name = 'idx_preliminary_score';
SHOW INDEX FROM rss2tlg_deduplication WHERE Key_name = 'idx_ai_triggered';
```

---

## 🛠️ Откат миграции

Если что-то пошло не так:

```bash
# 1. Восстановить из бэкапа
mysql -u root -p rss2tlg_production < backup_YYYYMMDD_HHMMSS.sql

# 2. Проверить версию
mysql -u root -p rss2tlg_production < check_schema_version.sql
```

Или удалить новые поля вручную:

```sql
-- Откат v2.0 → v1.0
ALTER TABLE rss2tlg_feed_state DROP COLUMN last_error;

ALTER TABLE rss2tlg_summarization 
    DROP COLUMN category_primary_en,
    DROP COLUMN category_secondary_en,
    DROP COLUMN keywords_en,
    DROP COLUMN dedup_canonical_entities_en,
    DROP COLUMN dedup_core_event_en;

DROP INDEX idx_category_primary_en ON rss2tlg_summarization;

ALTER TABLE rss2tlg_deduplication 
    DROP COLUMN preliminary_similarity_score,
    DROP COLUMN preliminary_method,
    DROP COLUMN ai_analysis_triggered;

DROP INDEX idx_preliminary_score ON rss2tlg_deduplication;
DROP INDEX idx_ai_triggered ON rss2tlg_deduplication;

ALTER TABLE openrouter_metrics 
    DROP COLUMN usage_web,
    DROP COLUMN final_cost;
```

---

## 📊 Влияние на производительность

### Миграция
- ✅ **Время**: < 1 секунда на пустой БД
- ✅ **Время**: 1-10 секунд на БД с данными (зависит от размера)
- ✅ **Блокировка**: Минимальная (ALTER TABLE с NULL полями)
- ✅ **Даунтайм**: Не требуется

### После миграции
- ✅ **Запись**: Без изменений (новые поля опциональные)
- ✅ **Чтение**: Ускорение аналитических запросов (новые индексы)
- ✅ **Размер БД**: +5-10% (новые поля и индексы)

---

## 📝 Контрольный список миграции

- [ ] Создан бэкап БД
- [ ] Проверена текущая версия схемы
- [ ] Применены миграции
- [ ] Запущен check_schema_version.sql
- [ ] Все 5 новых полей присутствуют
- [ ] Новые индексы созданы
- [ ] Приложение протестировано
- [ ] Документация обновлена

---

## 🆘 Помощь и поддержка

**Проблемы при миграции?**

1. Проверьте логи MySQL: `sudo tail -f /var/log/mysql/error.log`
2. Проверьте права пользователя: `SHOW GRANTS FOR 'rss2tlg'@'localhost';`
3. Проверьте версию MariaDB/MySQL: `SELECT VERSION();`

**Требования**:
- MariaDB 10.5+ или MySQL 8.0+
- Поддержка JSON полей
- Поддержка utf8mb4

---

## 📚 Дополнительные ресурсы

- [README.md](README.md) - Общая информация о SQL схемах
- [CHANGELOG.md](CHANGELOG.md) - Полная история изменений
- [init_schema.sql](init_schema.sql) - Актуальная схема v2.0
- [check_schema_version.sql](check_schema_version.sql) - Проверка версии

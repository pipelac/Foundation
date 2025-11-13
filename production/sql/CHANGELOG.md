# Changelog - RSS2TLG Production Schema

Все значимые изменения схемы БД документируются в этом файле.

## [2.0] - 2025-11-13

### 🎯 Главное
Полное обновление схемы с интеграцией всех миграций. Теперь `init_schema.sql` является единственным источником истины.

### ✅ Добавлено

#### rss2tlg_feed_state
- `last_error TEXT NULL DEFAULT NULL` - Хранение текста последней ошибки при обработке фида
  - Помогает диагностировать проблемы с RSS источниками
  - Позволяет отслеживать повторяющиеся ошибки

#### rss2tlg_summarization - Кросс-языковая дедупликация
- `category_primary_en VARCHAR(100)` - Основная категория на английском
- `category_secondary_en JSON` - Дополнительные категории на английском
- `keywords_en JSON` - Ключевые слова на английском
- `dedup_canonical_entities_en JSON` - Канонические сущности на английском
- `dedup_core_event_en TEXT` - Описание ключевого события на английском
- Индекс `idx_category_primary_en` - Оптимизация поиска по английским категориям

**Цель**: Эффективная дедупликация новостей на разных языках через нормализованные английские метаданные

#### rss2tlg_deduplication - Двухэтапная дедупликация
- `preliminary_similarity_score DECIMAL(5,2)` - Предварительная оценка схожести (0.00-100.00)
- `preliminary_method VARCHAR(50)` - Метод предварительной оценки (hybrid_v1, jaccard, etc.)
- `ai_analysis_triggered TINYINT(1)` - Флаг вызова AI анализа (0=fast path, 1=AI used)
- Индекс `idx_preliminary_score` - Аналитика preliminary scores
- Индекс `idx_ai_triggered` - Мониторинг использования AI

**Цель**: 
- Снижение затрат на AI через двухэтапный подход
- Быстрая отсеивание очевидно разных новостей
- AI анализ только для пограничных случаев

#### openrouter_metrics - Расширенные метрики стоимости
- `usage_web DECIMAL(10, 8)` - Стоимость веб-поиска в USD
- `final_cost DECIMAL(10, 8)` - Финальная стоимость после всех скидок (копия usage_total)

**Важно**: Поле `usage` от OpenRouter API УЖЕ содержит финальную стоимость после скидок!
- `usage_cache` и `usage_data` - информационные поля о скидках (приходят отрицательными)
- `final_cost` - просто копия `usage_total` для удобства

### 🗑️ Удалено
- Все SQL дампы таблиц (заменены единой схемой):
  - `openrouter_metrics_dump.sql`
  - `rss2tlg_deduplication_dump.sql`
  - `rss2tlg_feed_state_dump.sql`
  - `rss2tlg_feeds_dump.sql`
  - `rss2tlg_items_dump.sql`
  - `rss2tlg_summarization_dump.sql`

**Причина**: Схема `init_schema.sql` v2.0 - единственный источник истины

### 📝 Изменено
- Обновлен комментарий `similarity_method` ENUM: включает 'preliminary' значение
- Комментарии всех новых полей на русском языке
- Версия схемы: 1.0 → 2.0

### 🔧 Технические детали

#### Совместимость
- ✅ Обратно совместима с данными v1.0
- ✅ Новые поля имеют `NULL DEFAULT NULL` - не требуют миграции данных
- ✅ Существующие индексы сохранены

#### Производительность
- ✅ Новые индексы не влияют на запись (малая нагрузка)
- ✅ Ускоряют аналитические запросы
- ✅ Оптимизированы для типичных паттернов использования

---

## [1.0] - 2025-11-11

### ✅ Добавлено
- Первоначальная схема production БД
- 7 таблиц для полного цикла обработки RSS → AI → Telegram:
  - `rss2tlg_feeds` - Источники RSS лент
  - `rss2tlg_feed_state` - Состояние источников
  - `rss2tlg_items` - Новости с извлеченным контентом
  - `rss2tlg_summarization` - AI суммаризация
  - `rss2tlg_deduplication` - Дедупликация
  - `rss2tlg_publications` - Журнал публикаций
  - `openrouter_metrics` - Метрики OpenRouter API

### 🎯 Архитектурные решения
- InnoDB для транзакционной надежности
- utf8mb4 для полной поддержки Unicode (emoji, специальные символы)
- JSON поля для гибкого хранения структурированных данных
- Оптимизированные составные индексы

---

## Формат версионирования

Схема использует [Semantic Versioning](https://semver.org/):
- **MAJOR** (X.0.0): Несовместимые изменения API/схемы
- **MINOR** (0.X.0): Новая функциональность с обратной совместимостью
- **PATCH** (0.0.X): Исправления ошибок

---

## Миграция между версиями

### 1.0 → 2.0

**Автоматическая миграция не требуется** - все новые поля опциональные.

#### Опция 1: Пересоздание БД (рекомендуется для новых инсталляций)
```bash
mysql -u root -p << EOF
DROP DATABASE IF EXISTS rss2tlg_production;
CREATE DATABASE rss2tlg_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EOF

mysql -u root -p rss2tlg_production < init_schema.sql
```

#### Опция 2: Применение отдельных миграций (для существующих БД с данными)
```bash
mysql -u root -p rss2tlg_production < migration_add_en_fields.sql
mysql -u root -p rss2tlg_production < migration_dedup_v3.sql
mysql -u root -p rss2tlg_production < migration_add_usage_web.sql
```

#### Опция 3: ALTER вручную
```sql
-- rss2tlg_feed_state
ALTER TABLE rss2tlg_feed_state 
ADD COLUMN last_error TEXT NULL DEFAULT NULL 
COMMENT 'Текст последней ошибки' 
AFTER last_status;

-- rss2tlg_summarization
ALTER TABLE rss2tlg_summarization
ADD COLUMN category_primary_en VARCHAR(100) NULL AFTER category_primary,
ADD COLUMN category_secondary_en JSON NULL AFTER category_secondary,
ADD COLUMN keywords_en JSON NULL AFTER keywords,
ADD COLUMN dedup_canonical_entities_en JSON NULL AFTER dedup_canonical_entities,
ADD COLUMN dedup_core_event_en TEXT NULL AFTER dedup_core_event;

CREATE INDEX idx_category_primary_en ON rss2tlg_summarization(category_primary_en);

-- rss2tlg_deduplication
ALTER TABLE rss2tlg_deduplication
ADD COLUMN preliminary_similarity_score DECIMAL(5,2) NULL AFTER similarity_score,
ADD COLUMN preliminary_method VARCHAR(50) NULL DEFAULT 'hybrid_v1' AFTER similarity_method,
ADD COLUMN ai_analysis_triggered TINYINT(1) NOT NULL DEFAULT 0 AFTER preliminary_method;

CREATE INDEX idx_preliminary_score ON rss2tlg_deduplication(preliminary_similarity_score);
CREATE INDEX idx_ai_triggered ON rss2tlg_deduplication(ai_analysis_triggered);

-- openrouter_metrics
ALTER TABLE openrouter_metrics 
ADD COLUMN usage_web DECIMAL(10, 8) NULL AFTER usage_data,
ADD COLUMN final_cost DECIMAL(10, 8) NULL AFTER usage_file;
```

---

## Проверка версии

```sql
-- Проверить наличие новых полей v2.0
SELECT 
    COLUMN_NAME, 
    TABLE_NAME,
    COLUMN_TYPE
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = 'rss2tlg_production'
  AND COLUMN_NAME IN (
    'last_error',
    'category_primary_en',
    'preliminary_similarity_score',
    'usage_web',
    'final_cost'
  )
ORDER BY TABLE_NAME, COLUMN_NAME;
```

Если запрос возвращает 5 строк - у вас версия 2.0 ✅

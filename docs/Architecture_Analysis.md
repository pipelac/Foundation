# 🏗️ Анализ архитектуры AI Pipeline для обработки RSS новостей

**Дата:** 2025-11-08  
**Версия:** 1.0  
**Статус:** ⚠️ Требуется принятие решения

---

## 📋 Текущая ситуация

### Существующая структура БД

```
rss2tlg_items              → Сырые данные RSS (новости)
rss2tlg_ai_analysis        → AI анализ (СМЕШАННЫЕ данные всех этапов)
rss2tlg_publications       → Журнал публикаций
rss2tlg_feed_state         → Состояние RSS источников
```

### Проблемы текущей архитектуры

1. ❌ **Таблица `rss2tlg_ai_analysis` смешивает все этапы:**
   - Суммаризация (analysis_data)
   - Перевод (translation_status, translation_quality_score)
   - Дедупликация (deduplication_data)
   - Это нарушает Single Responsibility Principle

2. ❌ **Сложно отлаживать:** нельзя точно понять на каком этапе произошла ошибка

3. ❌ **Невозможно перезапустить отдельный этап:** при ошибке перевода придется запускать весь анализ заново

---

## 🎯 Предложенная пользователем архитектура

Пользователь предлагает:
- ✅ Правильная логика pipeline: суммаризация → дедупликация → перевод → иллюстрация
- ⚠️ **ОДНА таблица** для хранения всех подготовленных данных

### Проблемы этого подхода:

1. ❌ **Монолитная таблица** - сложная структура с множеством полей
2. ❌ **Сложная отладка** - все данные перемешаны
3. ❌ **Невозможность независимого перезапуска этапов**
4. ❌ **Сложное тестирование** - нельзя протестировать этапы отдельно
5. ❌ **Проблемы с масштабированием** - разные этапы могут требовать разной производительности

---

## ✅ РЕКОМЕНДУЕМАЯ АРХИТЕКТУРА (Вариант 1)

### Pipeline с отдельными таблицами для каждого этапа

```
📥 ВХОДНЫЕ ДАННЫЕ
┌─────────────────────────────────┐
│  rss2tlg_items                  │ ← Сырые данные RSS
│  (без изменений)                │
└─────────────────────────────────┘
              ↓
              
🤖 ЭТАП 1: СУММАРИЗАЦИЯ + КАТЕГОРИЗАЦИЯ
┌─────────────────────────────────┐
│  rss2tlg_summarization          │
│  ─────────────────────────────  │
│  • item_id (FK)                 │
│  • feed_id                      │
│  • status (pending/success/...)  │
│  • article_language             │
│  • headline                     │
│  • summary                      │
│  • keywords                     │
│  • category_primary             │
│  • category_secondary (JSON)    │
│  • importance_rating (1-20)     │
│  • dedup_canonical_entities     │
│  • dedup_core_event             │
│  • dedup_numeric_facts          │
│  • model_used                   │
│  • tokens_used                  │
│  • cache_hit                    │
│  • error_message                │
│  • processed_at                 │
└─────────────────────────────────┘
              ↓
              
🔍 ЭТАП 2: ДЕДУПЛИКАЦИЯ
┌─────────────────────────────────┐
│  rss2tlg_deduplication          │
│  ─────────────────────────────  │
│  • item_id (FK)                 │
│  • feed_id                      │
│  • status (pending/checked/...) │
│  • is_duplicate (0/1)           │
│  • duplicate_of_item_id (FK)    │
│  • similarity_score (0-100)     │
│  • similarity_method (ai/hash)  │
│  • can_be_published (0/1)       │
│  • model_used                   │
│  • tokens_used                  │
│  • error_message                │
│  • checked_at                   │
└─────────────────────────────────┘
              ↓
              
🌐 ЭТАП 3: ПЕРЕВОД (опционально)
┌─────────────────────────────────┐
│  rss2tlg_translation            │
│  ─────────────────────────────  │
│  • item_id (FK)                 │
│  • feed_id                      │
│  • status (pending/success/...) │
│  • source_language              │
│  • target_language              │
│  • translated_headline          │
│  • translated_summary           │
│  • quality_score (1-10)         │
│  • quality_issues (text)        │
│  • model_used                   │
│  • tokens_used                  │
│  • error_message                │
│  • translated_at                │
└─────────────────────────────────┘
              ↓
              
🎨 ЭТАП 4: ИЛЛЮСТРАЦИИ (опционально)
┌─────────────────────────────────┐
│  rss2tlg_illustration           │
│  ─────────────────────────────  │
│  • item_id (FK)                 │
│  • feed_id                      │
│  • status (pending/success/...) │
│  • image_path                   │
│  • image_url                    │
│  • image_width                  │
│  • image_height                 │
│  • prompt_used                  │
│  • model_used                   │
│  • generation_time_ms           │
│  • error_message                │
│  • generated_at                 │
└─────────────────────────────────┘
              ↓
              
📤 ВЫХОДНЫЕ ДАННЫЕ
┌─────────────────────────────────┐
│  rss2tlg_publications           │ ← Журнал публикаций
│  (расширенная версия)           │
│  • все поля из текущей таблицы  │
│  • published_headline           │
│  • published_text               │
│  • published_language           │
│  • published_media (JSON)       │
└─────────────────────────────────┘
```

### ✅ Преимущества этого подхода

1. ✅ **Разделение ответственности** - каждая таблица отвечает за свой этап
2. ✅ **Легкая отладка** - видно на каком этапе произошла ошибка
3. ✅ **Независимый перезапуск** - можно перезапустить только нужный этап
4. ✅ **Простое тестирование** - каждый модуль тестируется отдельно
5. ✅ **Гибкое масштабирование** - разные этапы можно масштабировать независимо
6. ✅ **Понятная история обработки** - видно весь путь новости
7. ✅ **Удобная аналитика** - легко собирать метрики по каждому этапу

### ⚠️ Недостатки

1. ⚠️ Больше JOIN-ов при выборке полных данных
2. ⚠️ Немного сложнее структура БД
3. ⚠️ Больше кода для миграции данных

---

## 🔄 АЛЬТЕРНАТИВНАЯ АРХИТЕКТУРА (Вариант 2)

### Одна таблица со статусами этапов (компромисс)

```sql
CREATE TABLE `rss2tlg_processed_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `item_id` INT UNSIGNED NOT NULL,
    `feed_id` INT UNSIGNED NOT NULL,
    
    -- ЭТАП 1: Суммаризация
    `summarization_status` ENUM('pending', 'processing', 'success', 'failed'),
    `summarization_data` JSON,
    `summarization_model` VARCHAR(100),
    `summarization_tokens` INT UNSIGNED,
    `summarization_cache_hit` TINYINT(1),
    `summarized_at` DATETIME,
    
    -- ЭТАП 2: Дедупликация
    `deduplication_status` ENUM('pending', 'processing', 'checked', 'failed'),
    `is_duplicate` TINYINT(1),
    `duplicate_of_item_id` INT UNSIGNED,
    `similarity_score` TINYINT UNSIGNED,
    `can_be_published` TINYINT(1),
    `deduplicated_at` DATETIME,
    
    -- ЭТАП 3: Перевод
    `translation_status` ENUM('pending', 'processing', 'success', 'failed', 'skipped'),
    `translation_data` JSON,
    `translation_model` VARCHAR(100),
    `translation_tokens` INT UNSIGNED,
    `translation_quality_score` TINYINT UNSIGNED,
    `translated_at` DATETIME,
    
    -- ЭТАП 4: Иллюстрация
    `illustration_status` ENUM('pending', 'processing', 'success', 'failed', 'skipped'),
    `illustration_data` JSON,
    `illustration_model` VARCHAR(100),
    `illustration_path` VARCHAR(512),
    `illustrated_at` DATETIME,
    
    -- ФИНАЛЬНЫЕ ДАННЫЕ
    `published_headline` VARCHAR(500),
    `published_text` TEXT,
    `published_language` VARCHAR(10),
    `published_media` JSON,
    `published_categories` JSON,
    `importance_rating` TINYINT UNSIGNED,
    
    -- ПУБЛИКАЦИЯ
    `is_ready_to_publish` TINYINT(1) DEFAULT 0,
    `is_published` TINYINT(1) DEFAULT 0,
    `published_at` DATETIME,
    
    -- TIMESTAMPS
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_item_id` (`item_id`),
    KEY `idx_feed_id` (`feed_id`),
    KEY `idx_summarization_status` (`summarization_status`),
    KEY `idx_deduplication_status` (`deduplication_status`),
    KEY `idx_translation_status` (`translation_status`),
    KEY `idx_illustration_status` (`illustration_status`),
    KEY `idx_ready_to_publish` (`is_ready_to_publish`),
    KEY `idx_published` (`is_published`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Обработанные новости со всеми этапами AI pipeline';
```

### ✅ Преимущества Варианта 2

1. ✅ **Простые запросы** - все данные в одной таблице
2. ✅ **Меньше JOIN-ов** - быстрее выборка
3. ✅ **Проще миграция** - похоже на текущую структуру

### ⚠️ Недостатки Варианта 2

1. ❌ **Монолитная структура** - много полей в одной таблице
2. ❌ **Сложнее отладка** - данные перемешаны
3. ❌ **Сложнее масштабирование** - нельзя отдельно масштабировать этапы
4. ❌ **Менее гибко** - сложно добавить новый этап

---

## 🎯 ОКОНЧАТЕЛЬНАЯ РЕКОМЕНДАЦИЯ

### ✅ Использовать **ВАРИАНТ 1** (отдельные таблицы)

**Почему:**

1. ✅ **Production-ready** - проверенный подход в индустрии
2. ✅ **Легкая поддержка** - понятная структура для разработчиков
3. ✅ **Гибкость** - легко добавлять новые этапы
4. ✅ **Масштабируемость** - каждый этап можно оптимизировать отдельно
5. ✅ **Мониторинг** - легко собирать метрики по каждому этапу
6. ✅ **Тестирование** - каждый модуль тестируется независимо

**Когда использовать Вариант 2:**

- Если нужна **максимальная скорость** выборки данных
- Если pipeline **не будет расширяться** новыми этапами
- Если команда разработки **очень маленькая**

---

## 📊 Сравнительная таблица

| Критерий | Вариант 1 (Раздельные) | Вариант 2 (Монолит) | Текущая |
|----------|------------------------|---------------------|---------|
| Разделение ответственности | ✅ Отлично | ⚠️ Среднее | ❌ Плохо |
| Отладка | ✅ Легко | ⚠️ Средне | ❌ Сложно |
| Тестирование | ✅ Легко | ⚠️ Средне | ❌ Сложно |
| Масштабируемость | ✅ Отлично | ⚠️ Среднее | ❌ Плохо |
| Скорость запросов | ⚠️ Средняя | ✅ Быстро | ✅ Быстро |
| Сложность миграции | ⚠️ Средняя | ✅ Легко | - |
| Гибкость расширения | ✅ Отлично | ⚠️ Среднее | ❌ Плохо |
| Независимый перезапуск | ✅ Да | ⚠️ Частично | ❌ Нет |

---

## 🚀 План реализации (Вариант 1)

### Этап 1: Создание таблиц (1 день)
```sql
-- Создать 4 новые таблицы:
- rss2tlg_summarization
- rss2tlg_deduplication
- rss2tlg_translation
- rss2tlg_illustration
```

### Этап 2: Разработка модулей (4 дня)
```php
- SummarizationService + Repository
- DeduplicationService + Repository
- TranslationService + Repository
- IllustrationService + Repository
```

### Этап 3: Миграция данных (1 день)
```sql
-- Перенести данные из rss2tlg_ai_analysis
-- в новые таблицы
```

### Этап 4: Тестирование (2 дня)
```bash
- Unit тесты для каждого модуля
- Integration тесты для pipeline
- Performance тесты
```

### Этап 5: Деплой (1 день)
```bash
- Запуск в production
- Мониторинг метрик
```

---

## ❓ Вопросы для принятия решения

1. **Какова ожидаемая нагрузка?**
   - < 10,000 новостей/день → любой вариант подойдет
   - > 10,000 новостей/день → **Вариант 1** (раздельные таблицы)

2. **Планируется ли расширение pipeline?**
   - Да → **Вариант 1**
   - Нет → Вариант 2

3. **Какая команда разработчиков?**
   - Опытная команда → **Вариант 1**
   - Начинающие → Вариант 2

4. **Важна ли скорость разработки?**
   - Да, нужно быстро → Вариант 2
   - Нет, важно качество → **Вариант 1**

---

## ✅ МОЯ ФИНАЛЬНАЯ РЕКОМЕНДАЦИЯ

Использовать **ВАРИАНТ 1** с отдельными таблицами для каждого этапа.

Это production-ready решение, которое будет легко поддерживать и масштабировать.

**Начинаем реализацию?** 🚀

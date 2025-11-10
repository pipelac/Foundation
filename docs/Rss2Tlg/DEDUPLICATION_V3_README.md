# 📚 DeduplicationService v3.0 - Документация

**Дата:** 2025-11-10  
**Версия:** 3.0 - Stepwise Multi-Language AI Deduplication  
**Статус:** 📋 PLAN READY - Готов к реализации

---

## 📖 ОБЗОР

DeduplicationService v3.0 - это переработанный модуль дедупликации новостей с двухэтапным анализом и поддержкой кроссязычной дедупликации.

### Ключевые отличия от v2.0

| Аспект | v2.0 (старая) | v3.0 (новая) |
|--------|---------------|--------------|
| **Метод** | AI для всех новостей | Preliminary + AI только для подозрительных |
| **AI вызовов** | 100% | 20-50% (экономия 50-80%) |
| **Время обработки** | ~60 сек | ~5-60 сек (avg ~30 сек) |
| **Стоимость** | $0.05/новость | $0.01-0.05 (avg $0.025) |
| **Кросс-язык** | ❌ Нет | ✅ Да (через EN метаданные) |
| **Прозрачность** | Низкая | Высокая (preliminary_score) |

---

## 🎯 ДВУХЭТАПНАЯ АРХИТЕКТУРА

### Этап 1: Preliminary Similarity Check (Fast Path)

**Цель:** Быстро отфильтровать явно уникальные новости БЕЗ дорогого AI вызова

**Метод:** Hybrid Similarity Model v1

**Компоненты (score 0-100):**
- ⏰ **Temporal proximity** (10%) - близость по времени публикации
- 📁 **Category match** (20%) - совпадение категорий (EN версии)
- 👥 **Entity overlap** (35%) - Jaccard similarity для entities_en
- 📰 **Event similarity** (20%) - Word overlap + Levenshtein
- 🔑 **Keyword overlap** (10%) - Jaccard similarity для keywords_en
- 🔢 **Numeric facts** (5%) - совпадение числовых фактов

**Решение:**
```
preliminary_score < 60  → УНИКАЛЬНАЯ (без AI, fast path)
preliminary_score >= 60 → → Этап 2 (AI анализ)
```

**Производительность:**
- Время: 1-2 мс/новость
- Экономия: 50-80% AI вызовов

### Этап 2: AI Semantic Analysis

**Цель:** Глубокий семантический анализ для подозрительных новостей

**Метод:** AI анализ (текущий промпт v2)

**Фильтрация:**
- Только новости с `preliminary_score >= 60`
- Топ-N по score (max 10 новостей)

**Производительность:**
- Время: 10-60 сек/новость
- Точность: Высокая (AI семантический анализ)

---

## 🗄️ ИЗМЕНЕНИЯ В БД

### Новые поля в rss2tlg_deduplication

```sql
-- Предварительная оценка схожести (0.00-100.00)
preliminary_similarity_score DECIMAL(5,2) DEFAULT NULL

-- Метод предварительной оценки (hybrid_v1, jaccard, etc.)
preliminary_method VARCHAR(50) DEFAULT 'hybrid_v1'

-- Был ли вызван AI анализ (0=fast path, 1=AI used)
ai_analysis_triggered TINYINT(1) NOT NULL DEFAULT 0
```

**Миграция:** `production/sql/migration_dedup_v3.sql`

---

## ⚙️ КОНФИГУРАЦИЯ

### production/configs/deduplication.json

```json
{
    "enabled": true,
    "models": [
        "google/gemma-3-27b-it",
        "deepseek/deepseek-chat",
        "deepseek/deepseek-v3.2-exp"
    ],
    "prompt_file": "production/prompts/deduplication_prompt_v2.txt",
    "fallback_strategy": "sequential",
    "retry_count": 2,
    "timeout": 120,
    
    "compare_last_n_days": 7,
    "max_comparisons": 50,
    "max_ai_comparisons": 10,           // NEW: макс для AI анализа
    
    "preliminary_similarity_threshold": 60,  // NEW: порог для AI
    "similarity_threshold": 70
}
```

### Ключевые параметры

| Параметр | Тип | Default | Описание |
|----------|-----|---------|----------|
| `preliminary_similarity_threshold` | int | 60 | Порог для запуска AI (0-100). Если preliminary_score < threshold, новость считается уникальной без AI. |
| `max_ai_comparisons` | int | 10 | Максимальное количество новостей для AI анализа. Ограничивает токены и время. |
| `compare_last_n_days` | int | 7 | Сравнивать с новостями за последние N дней |
| `max_comparisons` | int | 50 | Максимум похожих новостей для preliminary анализа |

---

## 📊 НОВЫЕ МЕТРИКИ

```php
'preliminary_checks' => 0,        // Количество preliminary проверок
'ai_calls_saved' => 0,            // Сколько AI вызовов избежали
'fast_path_unique' => 0,          // Помечено уникальными без AI
'ai_triggered' => 0,              // Вызовов AI после preliminary
```

**Анализ эффективности:**

```php
$metrics = $service->getMetrics();

$totalProcessed = $metrics['total_processed'];
$aiCallsSaved = $metrics['ai_calls_saved'];
$aiTriggered = $metrics['ai_triggered'];

$aiSavingRate = ($aiCallsSaved / $totalProcessed) * 100;
echo "AI Saving Rate: {$aiSavingRate}%\n";
// Ожидаемый результат: 50-80%
```

---

## 🌍 КРОССЯЗЫЧНАЯ ДЕДУПЛИКАЦИЯ

### Как это работает

**Проблема:** Русская и английская версии одной новости не распознаются как дубликаты.

**Решение:** Используем английские версии метаданных для сравнения.

### Пример

**Русская новость:**
```php
[
    'category_primary' => 'политика',
    'category_primary_en' => 'politics',        // ← ДЛЯ СРАВНЕНИЯ
    'dedup_canonical_entities' => ['Путин', 'Кремль'],
    'dedup_canonical_entities_en' => ['Putin', 'Kremlin'],  // ← ДЛЯ СРАВНЕНИЯ
    'dedup_core_event' => 'Путин выступил на саммите',
    'dedup_core_event_en' => 'Putin spoke at summit',  // ← ДЛЯ СРАВНЕНИЯ
]
```

**Английская новость:**
```php
[
    'category_primary' => 'politics',
    'category_primary_en' => 'politics',        // ← ДЛЯ СРАВНЕНИЯ
    'dedup_canonical_entities' => ['Putin', 'Kremlin'],
    'dedup_canonical_entities_en' => ['Putin', 'Kremlin'],  // ← ДЛЯ СРАВНЕНИЯ
    'dedup_core_event' => 'Putin addressed the summit',
    'dedup_core_event_en' => 'Putin addressed the summit',  // ← ДЛЯ СРАВНЕНИЯ
]
```

**Сравнение:**
```
Entity overlap: ['Putin', 'Kremlin'] ∩ ['Putin', 'Kremlin'] = 100%
Event similarity: "Putin spoke at summit" vs "Putin addressed the summit" = 85%
→ preliminary_score = 78 >= 60
→ AI анализ → DUPLICATE обнаружен! ✅
```

---

## 🔧 API МЕТОДОВ

### Публичные методы (без изменений)

```php
public function processItem(int $itemId): bool
public function processBatch(array $itemIds): array
public function getStatus(int $itemId): ?string
public function getMetrics(): array
public function resetMetrics(): void
```

### Новые приватные методы

#### Preliminary Similarity

```php
// Главный метод расчета схожести
private function calculatePreliminarySimilarity(
    array $newItem, 
    array $existingItem
): float

// Анализ всех похожих новостей
private function analyzePreliminarySimilarity(
    array $newItem, 
    array $similarItems
): array

// Фильтрация подозрительных для AI
private function filterSuspiciousItems(
    array $similarItems, 
    array $preliminaryResults
): array
```

#### Компоненты схожести

```php
private function calculateTemporalSimilarity(string $date1, string $date2): float
private function calculateCategorySimilarity(array $item1, array $item2): float
private function calculateEntityOverlap(array $entities1, array $entities2): float
private function calculateEventSimilarity(string $event1, string $event2): float
private function calculateKeywordOverlap(array $keywords1, array $keywords2): float
private function calculateNumericFactsOverlap(array $facts1, array $facts2): float
```

#### Вспомогательные методы

```php
private function calculateJaccardSimilarity(array $arr1, array $arr2): float
private function calculateLevenshteinSimilarity(string $str1, string $str2): float
private function extractSignificantWords(string $text): array
private function extractNumbers(array $facts): array
private function decodeJsonField(?string $jsonString): array
```

---

## 💰 ЭКОНОМИКА

### Расчет для 1,000 новостей/день

**Сценарий 1: v2.0 (текущая)**
```
1,000 новостей × 100% AI = 1,000 AI вызовов
1,000 × $0.05 = $50/день = $1,500/месяц
```

**Сценарий 2: v3.0 (новая, консервативная оценка)**
```
Fast path (60%): 600 новостей × $0.001 = $0.60
AI path (40%):   400 новостей × $0.05 = $20.00
──────────────────────────────────────────
Итого: $20.60/день = $618/месяц

Экономия: $1,500 - $618 = $882/месяц = $10,584/год
```

**Сценарий 3: v3.0 (оптимистичная оценка)**
```
Fast path (75%): 750 новостей × $0.001 = $0.75
AI path (25%):   250 новостей × $0.05 = $12.50
──────────────────────────────────────────
Итого: $13.25/день = $398/месяц

Экономия: $1,500 - $398 = $1,102/месяц = $13,224/год 🎉
```

### ROI (Return on Investment)

**Затраты на разработку:**
- Время разработки: ~2 часа
- Стоимость разработки: $100-200 (условно)

**Окупаемость:**
- При экономии $882/месяц: окупится за 1 день! 🚀
- При экономии $1,102/месяц: окупится за 1 день! 🚀

---

## 📈 ОЖИДАЕМЫЕ РЕЗУЛЬТАТЫ

### Производительность

| Метрика | v2.0 | v3.0 (консерв.) | v3.0 (оптим.) | Улучшение |
|---------|------|-----------------|---------------|-----------|
| **AI вызовов** | 100% | 40% | 25% | ↓ 60-75% |
| **Avg время** | 60 сек | 36 сек | 30 сек | ↓ 40-50% |
| **Avg стоимость** | $0.05 | $0.021 | $0.013 | ↓ 58-74% |
| **Токенов** | 7,000 | 2,840 | 1,800 | ↓ 59-74% |

### Точность

| Показатель | v2.0 | v3.0 | Комментарий |
|------------|------|------|-------------|
| **False Positives** | Low | Low | AI все еще используется |
| **False Negatives** | Low | Medium-Low | Зависит от порога (настраиваемо) |
| **Кросс-язык** | ❌ Нет | ✅ Да | Английские метаданные |
| **Прозрачность** | Low | High | Сохраняем preliminary_score |

---

## 🎓 РЕКОМЕНДАЦИИ

### Настройка порога

**preliminary_similarity_threshold** - ключевой параметр!

- **40-50:** Очень агрессивный (почти все → AI, мало экономии)
- **60-70:** ✅ **Рекомендуемый** (баланс точности и экономии)
- **80-90:** Очень консервативный (много false negatives)

**Стратегия:**
1. Начать с 60
2. Мониторить false negatives (пропущенные дубликаты)
3. Если FN > 5%, снизить до 55
4. Если FN < 1%, повысить до 65

### Мониторинг

**Ключевые метрики для отслеживания:**

```sql
-- Распределение preliminary scores
SELECT 
    FLOOR(preliminary_similarity_score / 10) * 10 as score_range,
    COUNT(*) as count,
    SUM(ai_analysis_triggered) as ai_triggered_count
FROM rss2tlg_deduplication
GROUP BY score_range
ORDER BY score_range;

-- Эффективность экономии
SELECT 
    COUNT(*) as total_checked,
    SUM(ai_analysis_triggered = 0) as fast_path_count,
    SUM(ai_analysis_triggered = 1) as ai_path_count,
    ROUND(SUM(ai_analysis_triggered = 0) / COUNT(*) * 100, 2) as saving_rate_pct
FROM rss2tlg_deduplication
WHERE status = 'checked';

-- False Negatives (manual review required)
-- Новости помечены уникальными, но на самом деле дубликаты
SELECT d1.item_id, d1.preliminary_similarity_score, s1.headline
FROM rss2tlg_deduplication d1
JOIN rss2tlg_summarization s1 ON d1.item_id = s1.item_id
WHERE d1.is_duplicate = 0 
  AND d1.ai_analysis_triggered = 0
  AND d1.preliminary_similarity_score BETWEEN 55 AND 65
ORDER BY d1.preliminary_similarity_score DESC
LIMIT 20;
```

---

## 📚 ДОКУМЕНТАЦИЯ

### Полная документация

1. **План рефакторинга (детальный):**
   - `docs/Rss2Tlg/DEDUPLICATION_REFACTORING_PLAN_V3.md` (100+ разделов)
   - Архитектура, алгоритмы, примеры

2. **Краткая сводка:**
   - `docs/Rss2Tlg/DEDUPLICATION_V3_SUMMARY.md`
   - Быстрый обзор изменений

3. **Пошаговая инструкция:**
   - `docs/Rss2Tlg/DEDUPLICATION_V3_IMPLEMENTATION_STEPS.md`
   - Этапы реализации с чек-листами

4. **Этот README:**
   - `docs/Rss2Tlg/DEDUPLICATION_V3_README.md`
   - Документация для пользователей

### SQL

1. **Миграция БД:**
   - `production/sql/migration_dedup_v3.sql`

### Конфигурация

1. **Config файл:**
   - `production/configs/deduplication.json`

---

## 🚀 СЛЕДУЮЩИЕ ШАГИ

### Реализация (сейчас)

1. ✅ Создать миграцию БД
2. ✅ Обновить конфигурацию
3. ✅ Написать документацию
4. ⏸️ Реализовать код в `DeduplicationService.php`
5. ⏸️ Обновить PHPDoc и версию класса

### Тестирование (после реализации)

1. ⏸️ Применить миграцию БД
2. ⏸️ Создать тестовый скрипт
3. ⏸️ Протестировать на реальных данных
4. ⏸️ Проверить метрики (saving_rate, accuracy)
5. ⏸️ Подстроить порог при необходимости

### Production (после тестирования)

1. ⏸️ Обновить API документацию
2. ⏸️ Развернуть в production
3. ⏸️ Настроить мониторинг метрик
4. ⏸️ Собрать статистику за месяц
5. ⏸️ Оптимизировать веса компонентов если нужно

---

## ⚠️ ВАЖНЫЕ ЗАМЕЧАНИЯ

### 1. Зависимость от качества AI переводов

Preliminary check зависит от качества билингвальных полей:
- `category_primary_en`
- `keywords_en`
- `dedup_canonical_entities_en`
- `dedup_core_event_en`

**Мониторить:** Качество переводов в `SummarizationService`!

### 2. Stop words

Список в `extractSignificantWords()` может потребовать расширения:
```php
// Добавить по мере необходимости
'el', 'la', 'de', 'que', ...  // Испанский
'le', 'la', 'de', 'que', ...  // Французский
'der', 'die', 'das', 'und', ... // Немецкий
```

### 3. Levenshtein ограничение

`levenshtein()` в PHP работает только для строк <255 символов.

**Решение:** Обрезка в `calculateLevenshteinSimilarity()`:
```php
$str1 = mb_substr($str1, 0, 200);
$str2 = mb_substr($str2, 0, 200);
```

---

## 📞 ПОДДЕРЖКА

**Вопросы по реализации:**
- См. `DEDUPLICATION_V3_IMPLEMENTATION_STEPS.md`

**Вопросы по архитектуре:**
- См. `DEDUPLICATION_REFACTORING_PLAN_V3.md`

**Быстрый обзор:**
- См. `DEDUPLICATION_V3_SUMMARY.md`

---

**Дата документа:** 2025-11-10  
**Автор:** AI Developer  
**Версия:** 1.0  
**Статус:** 📚 DOCUMENTATION READY

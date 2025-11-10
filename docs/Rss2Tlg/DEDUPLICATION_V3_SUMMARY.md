# 🎯 DeduplicationService v3.0 - Краткая сводка

**Дата:** 2025-11-10  
**Версия:** 3.0 - Stepwise Multi-Language AI Deduplication

---

## 📌 СУТЬ ИЗМЕНЕНИЙ

### До (v2.0)
```
Новость → Поиск похожих → AI анализ ВСЕХ → Результат
                          ↑
                     ДОРОГО! (~60 сек, $0.05)
```

### После (v3.0)
```
Новость → Поиск похожих → Preliminary Check (1-2ms)
                          ↓
                    Score < 60?
                    ↓         ↓
                  ДА         НЕТ
                  ↓           ↓
            УНИКАЛЬНАЯ    AI анализ
            (fast path)   подозрительных
            ↓              ↓
         Результат      Результат
         
Экономия: 50-80% AI вызовов!
```

---

## 🎨 ДВУХЭТАПНАЯ АРХИТЕКТУРА

### Этап 1: Preliminary Similarity Model (Fast Path)

**Компоненты модели (score 0-100):**

| Компонент | Вес | Алгоритм |
|-----------|-----|----------|
| ⏰ **Temporal proximity** | 10% | Time difference |
| 📁 **Category match** | 20% | EN category equality |
| 👥 **Entity overlap** | 35% | Jaccard(entities_en) |
| 📰 **Event similarity** | 20% | Word overlap + Levenshtein |
| 🔑 **Keyword overlap** | 10% | Jaccard(keywords_en) |
| 🔢 **Numeric facts** | 5% | Number matches |

**Решение:**
- `preliminary_score < 60` → **УНИКАЛЬНАЯ** (без AI)
- `preliminary_score >= 60` → **→ Этап 2** (AI анализ)

### Этап 2: AI Semantic Analysis (только подозрительные)

**Фильтрация:**
- Только новости с `preliminary_score >= 60`
- Топ-N по score (max 10 новостей)
- Отправка на AI с текущим промптом

**Преимущества:**
- ✅ AI видит только подозрительные пары
- ✅ Меньше токенов в промпте
- ✅ Сохраняется точность для сложных случаев

---

## 🌍 КРОССЯЗЫЧНАЯ ДЕДУПЛИКАЦИЯ

**Используем английские версии метаданных:**

```
Русская новость:
- category_primary: "политика"
- category_primary_en: "politics"  ← ДЛЯ СРАВНЕНИЯ
- entities: ["Путин", "Кремль"]
- entities_en: ["Putin", "Kremlin"] ← ДЛЯ СРАВНЕНИЯ

Английская новость:
- category_primary: "politics"
- category_primary_en: "politics"  ← ДЛЯ СРАВНЕНИЯ
- entities: ["Putin", "Kremlin"]
- entities_en: ["Putin", "Kremlin"] ← ДЛЯ СРАВНЕНИЯ

→ СРАВНЕНИЕ entities_en: 100% match!
→ ДУБЛИКАТ обнаружен независимо от языка!
```

---

## 💰 ЭКОНОМИЯ

### Производительность

| Метрика | v2.0 | v3.0 | Улучшение |
|---------|------|------|-----------|
| AI вызовов | 100% | 20-50% | ↓ 50-80% |
| Время | ~60 сек | ~5-60 сек | ↓ ~50% avg |
| Стоимость | $0.05 | $0.01-0.05 | ↓ 50-80% |

### Расчет для 1,000 новостей/день

**v2.0:**
- 1,000 × $0.05 = **$50/день** = **$1,500/месяц**

**v3.0:**
- Fast path (70%): 700 × $0.001 = $0.70
- AI path (30%): 300 × $0.05 = $15.00
- **Итого:** $15.70/день = **$471/месяц**

**💰 Экономия:** $1,029/месяц = **$12,348/год**

---

## 🔧 КЛЮЧЕВЫЕ МЕТОДЫ

### Новые методы

```php
// Анализ preliminary схожести
private function analyzePreliminarySimilarity(
    array $newItem, 
    array $similarItems
): array

// Расчет схожести между двумя новостями
private function calculatePreliminarySimilarity(
    array $newItem, 
    array $existingItem
): float

// Фильтрация подозрительных для AI
private function filterSuspiciousItems(
    array $similarItems, 
    array $preliminaryResults
): array

// 6 компонентов схожести
private function calculateTemporalSimilarity(string $date1, string $date2): float
private function calculateCategorySimilarity(array $item1, array $item2): float
private function calculateEntityOverlap(array $entities1, array $entities2): float
private function calculateEventSimilarity(string $event1, string $event2): float
private function calculateKeywordOverlap(array $keywords1, array $keywords2): float
private function calculateNumericFactsOverlap(array $facts1, array $facts2): float

// Вспомогательные
private function calculateJaccardSimilarity(array $arr1, array $arr2): float
private function calculateLevenshteinSimilarity(string $str1, string $str2): float
private function extractSignificantWords(string $text): array
private function extractNumbers(array $facts): array
```

### Обновленные методы

```php
public function processItem(int $itemId): bool
// + Двухэтапная логика
// + Метрики fast path

private function getSummarizationData(int $itemId): ?array
// + Билингвальные поля

private function getSimilarItems(int $itemId, array $itemData): array
// + category_primary_en
// + Улучшенные фильтры

private function saveDedupResult(int $itemId, int $feedId, array $result): void
// + preliminary_similarity_score
// + ai_analysis_triggered
```

---

## ⚙️ КОНФИГУРАЦИЯ

```json
{
    "enabled": true,
    "models": ["google/gemma-3-27b-it", "deepseek/deepseek-chat"],
    "prompt_file": "production/prompts/deduplication_prompt_v2.txt",
    
    "compare_last_n_days": 7,
    "max_comparisons": 50,
    "max_ai_comparisons": 10,           // NEW: макс для AI
    
    "preliminary_similarity_threshold": 60,  // NEW: порог для AI
    "similarity_threshold": 70
}
```

**Ключевые параметры:**

| Параметр | Значение | Описание |
|----------|----------|----------|
| `preliminary_similarity_threshold` | 60 | Порог для запуска AI (0-100) |
| `max_ai_comparisons` | 10 | Макс. новостей для AI анализа |

---

## 📊 НОВЫЕ МЕТРИКИ

```php
'preliminary_checks' => 0,        // Количество preliminary проверок
'ai_calls_saved' => 0,            // Сколько AI вызовов избежали
'fast_path_unique' => 0,          // Помечено уникальными без AI
'ai_triggered' => 0,              // Вызовов AI после preliminary
'avg_preliminary_score' => 0.0,   // Средний preliminary score
```

---

## 🗄️ ИЗМЕНЕНИЯ БД

```sql
ALTER TABLE `rss2tlg_deduplication`
    ADD COLUMN `preliminary_similarity_score` DECIMAL(5,2) DEFAULT NULL,
    ADD COLUMN `preliminary_method` VARCHAR(50) DEFAULT 'hybrid_v1',
    ADD COLUMN `ai_analysis_triggered` TINYINT(1) NOT NULL DEFAULT 0;

CREATE INDEX idx_preliminary_score ON rss2tlg_deduplication(preliminary_similarity_score);
CREATE INDEX idx_ai_triggered ON rss2tlg_deduplication(ai_analysis_triggered);
```

---

## 🎯 ПРИМЕРЫ РАБОТЫ

### Пример 1: Явно уникальная новость (Fast Path)

```
Новость: "Elon Musk announces Mars colony plan"
Похожие: 2 новости о Tesla earnings

Preliminary Analysis:
- Temporal: 2 days ago → 5.0 баллов
- Category: business vs business → 15.0 баллов
- Entities: [Musk, SpaceX] vs [Musk, Tesla] → Jaccard=0.33 → 11.6 баллов
- Event: "Mars colony" vs "earnings report" → 3.0 баллов
- Keywords: minimal overlap → 2.0 баллов
- Numeric: no matches → 0.0 баллов

→ preliminary_score = 36.6 < 60
→ УНИКАЛЬНАЯ (без AI вызова)
→ Экономия: ~60 сек, $0.05, 7000 токенов ✅
```

### Пример 2: Подозрительная новость (AI Path)

```
Новость: "Biden signs infrastructure bill into law"
Похожие: "Biden podpisuje zakon o infrastrukturze" (польский)

Preliminary Analysis:
- Temporal: same day → 10.0 баллов
- Category: politics vs politics → 15.0 баллов
- Entities: [Biden, infrastructure] → Jaccard=1.0 → 35.0 баллов
- Event: "signs into law" vs "signs law" → 18.0 баллов
- Keywords: [biden, infrastructure, law] → Jaccard=0.8 → 8.0 баллов
- Numeric: no matches → 0.0 баллов

→ preliminary_score = 86.0 >= 60
→ ПОДОЗРИТЕЛЬНАЯ → AI анализ
→ AI: similarity=95%, is_duplicate=true ✅
```

---

## ⚠️ ВАЖНЫЕ ЗАМЕЧАНИЯ

### 1. Настройка порога

**preliminary_similarity_threshold** - критический параметр!

- ❌ **Низкий (30-40):** Почти все → AI (нет экономии)
- ✅ **Оптимальный (60-70):** Баланс точности/экономии
- ⚠️ **Высокий (80-90):** Много false negatives

**Рекомендация:** Начать с 60, мониторить, подстраивать.

### 2. Качество AI переводов

Preliminary check зависит от качества:
- `category_primary_en`
- `keywords_en`
- `dedup_canonical_entities_en`
- `dedup_core_event_en`

**Мониторить:** Качество переводов в SummarizationService!

### 3. Stop words

Список в `extractSignificantWords()` можно расширять:
```php
$stopWords = [
    'a', 'an', 'the', ...   // English
    'в', 'и', 'на', ...     // Russian
    'el', 'la', 'de', ...   // Spanish (добавить при необходимости)
];
```

---

## ✅ ПРЕИМУЩЕСТВА ПОДХОДА

1. ✅ **Монолитная реализация** - нет излишних абстракций
2. ✅ **Простые алгоритмы** - Jaccard, Levenshtein, time diff
3. ✅ **Экономия 50-80%** AI вызовов
4. ✅ **Кроссязычная дедупликация** через EN метаданные
5. ✅ **Настраиваемость** - пороги в конфиге
6. ✅ **Прозрачность** - сохраняем preliminary_score
7. ✅ **Обратная совместимость** - extends AbstractPipelineModule

---

## 📚 ДОКУМЕНТАЦИЯ

**Полная документация:**
- `/docs/Rss2Tlg/DEDUPLICATION_REFACTORING_PLAN_V3.md` - детальный план (100+ разделов)

**API документация:**
- `/docs/Rss2Tlg/Pipeline_Deduplication_README.md` - будет обновлена для v3.0

**Миграция БД:**
- `/production/sql/migration_dedup_v3.sql`

**Конфигурация:**
- `/production/configs/deduplication.json`

---

## 🚀 СТАТУС

**📋 PLAN READY - ГОТОВ К РЕАЛИЗАЦИИ**

**Следующие шаги:**
1. Реализовать методы в `DeduplicationService.php`
2. Обновить конфигурацию `deduplication.json`
3. Применить миграцию `migration_dedup_v3.sql`
4. Обновить документацию `Pipeline_Deduplication_README.md`
5. ⏸️ Тестирование (ПОЗЖЕ!)

---

**Дата:** 2025-11-10  
**Автор:** AI Developer  
**Версия документа:** 1.0

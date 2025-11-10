# 📋 ПЛАН РЕФАКТОРИНГА: DeduplicationService v3.0

**Дата:** 2025-11-10  
**Версия:** 3.0 - Stepwise Multi-Language AI Deduplication  
**Автор:** AI Developer

---

## 🎯 ЦЕЛЬ РЕФАКТОРИНГА

Реализовать **двухэтапную дедупликацию новостей** с использованием билингвальных метаданных для кроссязычного анализа и экономии AI вызовов.

### Проблемы текущей версии (v2.0)

1. ❌ **Дорогой AI анализ для всех новостей** - даже для явно уникальных
2. ❌ **Не использует билингвальные поля** - category_primary_en, keywords_en, entities_en, core_event_en
3. ❌ **Нет кроссязычной дедупликации** - русская и английская версия одной новости не распознаются как дубликаты
4. ❌ **Примитивный отбор похожих новостей** - только по категории ИЛИ языку
5. ❌ **Нет быстрого пути** для явно уникальных новостей

### Преимущества новой версии (v3.0)

1. ✅ **Экономия 50-80% AI вызовов** - быстрый preliminary фильтр
2. ✅ **Кроссязычная дедупликация** - используем английские версии метаданных
3. ✅ **Двухэтапный анализ:**
   - Этап 1: Быстрая модель схожести (1-2ms)
   - Этап 2: AI семантический анализ (только для подозрительных)
4. ✅ **Настраиваемый порог** - preliminary_similarity_threshold в конфиге
5. ✅ **Прозрачность** - сохраняем preliminary_score для анализа
6. ✅ **Улучшенные метрики** - отслеживаем экономию AI вызовов

---

## 🏗️ АРХИТЕКТУРНОЕ РЕШЕНИЕ

### Монолитный подход (БЕЗ лишних абстракций)

**✅ ЧТО ДЕЛАЕМ:**
- Все в `DeduplicationService` v3.0
- Новые приватные методы для расчета схожести
- Используем простые алгоритмы (Jaccard, Levenshtein, time diff)
- Никаких ML моделей - чистый PHP код

**❌ ЧТО НЕ ДЕЛАЕМ:**
- ❌ Отдельный класс `SimilarityCalculator`
- ❌ Trait для similarity расчетов
- ❌ Сложные абстракции и паттерны
- ❌ Внешние ML библиотеки

---

## 📊 ДВУХЭТАПНАЯ ЛОГИКА ДЕДУПЛИКАЦИИ

### Этап 1: Preliminary Similarity Check (Fast Path)

**Цель:** Быстро отфильтровать явно уникальные новости БЕЗ AI вызова

**Алгоритм:**
```
1. Загрузить похожие новости из БД (last N days, same category_en)
2. Для каждой похожей новости рассчитать preliminary_similarity_score (0-100)
3. Найти MAX(preliminary_similarity_score)
4. Если MAX < preliminary_threshold (default 60):
   → Пометить как УНИКАЛЬНУЮ (fast path)
   → Сохранить результат БЕЗ AI вызова
5. Если MAX >= preliminary_threshold:
   → Перейти к Этапу 2 (AI анализ)
```

**Компоненты модели схожести:**

| Компонент | Вес | Описание |
|-----------|-----|----------|
| **Temporal proximity** | 10% | Близость по времени публикации |
| **Category match** | 20% | Совпадение категорий (EN версии) |
| **Entity overlap** | 35% | Jaccard similarity для entities_en |
| **Event similarity** | 20% | Text similarity для core_event_en |
| **Keyword overlap** | 10% | Jaccard similarity для keywords_en |
| **Numeric facts** | 5% | Совпадение числовых фактов |

**Итого:** preliminary_similarity_score = 0-100

### Этап 2: AI Semantic Analysis (только для подозрительных)

**Цель:** Глубокий семантический анализ для новостей с высоким preliminary_score

**Алгоритм:**
```
1. Фильтровать только suspicious items (preliminary_score >= threshold)
2. Ограничить количество (топ-N по score, default max_ai_comparisons=10)
3. Отправить на AI анализ с текущим промптом
4. AI возвращает окончательное решение (is_duplicate, similarity_score)
5. Сохранить результат с AI метриками
```

**Преимущества:**
- ✅ AI видит только действительно подозрительные пары
- ✅ Меньше токенов в промпте (меньше новостей для сравнения)
- ✅ Экономия 50-80% AI вызовов
- ✅ Сохраняется точность для сложных случаев

---

## 🔢 АЛГОРИТМЫ РАСЧЕТА СХОЖЕСТИ

### 1. Temporal Proximity (10 баллов max)

```php
private function calculateTemporalSimilarity(string $date1, string $date2): float
```

**Логика:**
- Same day (0 hours): **10.0 баллов**
- ±6 hours: **9.0 баллов**
- ±12 hours: **8.0 баллов**
- ±1 day: **7.0 баллов**
- ±2 days: **5.0 баллов**
- ±3 days: **3.0 баллов**
- ±7 days: **1.0 балл**
- >7 days: **0.0 баллов**

**Обоснование:** Новости о одном событии обычно публикуются в течение нескольких часов/дней.

### 2. Category Match (20 баллов max)

```php
private function calculateCategorySimilarity(array $item1, array $item2): float
```

**Логика:**
- Primary category match (category_primary_en): **15.0 баллов**
- Secondary category overlap (category_secondary_en):
  - 1 совпадение: **+2.5 балла**
  - 2 совпадения: **+5.0 баллов**

**Обоснование:** Дубликаты почти всегда в одной категории (politics, war, sports и т.д.)

### 3. Entity Overlap (35 баллов max) - САМЫЙ ВАЖНЫЙ!

```php
private function calculateEntityOverlap(array $entities1, array $entities2): float
```

**Алгоритм:** Jaccard Similarity
```
Jaccard = |A ∩ B| / |A ∪ B|
Score = Jaccard × 35
```

**Нормализация entities:**
- Lowercase: "Putin" → "putin"
- Trim пробелов
- Удаление пунктуации в конце

**Примеры:**
- entities1 = ["Putin", "Kremlin", "Russia"]
- entities2 = ["Putin", "Russia", "Ukraine"]
- Intersection = ["Putin", "Russia"] = 2
- Union = ["Putin", "Kremlin", "Russia", "Ukraine"] = 4
- Jaccard = 2/4 = 0.5
- Score = 0.5 × 35 = **17.5 баллов**

**Обоснование:** Сущности - ключевой индикатор дубликатов (одни и те же люди, организации, места)

### 4. Event Similarity (20 баллов max)

```php
private function calculateEventSimilarity(string $event1, string $event2): float
```

**Алгоритм:** Word Overlap + Levenshtein Distance

**Шаг 1 - Word Overlap:**
```
1. Разбить на слова: explode(' ', strtolower($event))
2. Удалить stop words (и, в, на, the, a, an, of и т.д.)
3. Рассчитать Jaccard similarity для слов
```

**Шаг 2 - Levenshtein Distance:**
```
1. Нормализовать строки (lowercase, trim)
2. Рассчитать levenshtein distance
3. Нормализовать: similarity = 1 - (distance / max_length)
```

**Финальный Score:**
```
Score = (word_overlap × 0.6 + levenshtein_similarity × 0.4) × 20
```

**Обоснование:** Описание события - семантическая основа дедупликации

### 5. Keyword Overlap (10 баллов max)

```php
private function calculateKeywordOverlap(array $keywords1, array $keywords2): float
```

**Алгоритм:** Jaccard Similarity (аналогично entities)
```
Jaccard = |A ∩ B| / |A ∪ B|
Score = Jaccard × 10
```

**Обоснование:** Ключевые слова дополняют entity и event анализ

### 6. Numeric Facts Overlap (5 баллов max)

```php
private function calculateNumericFactsOverlap(array $facts1, array $facts2): float
```

**Логика:**
- Извлечь числа из обоих массивов фактов
- Подсчитать совпадающие числа (exact match)
- Score = min(matches, 5) баллов

**Примеры совпадений:**
- Даты: "2024-03-15"
- Числа: "100", "$1000", "50%"
- Времена: "18:30"

**Обоснование:** Числовые факты - сильный индикатор дубликатов

### Итоговый расчет

```php
private function calculatePreliminarySimilarity(
    array $newItem, 
    array $existingItem
): float {
    $score = 0.0;
    
    $score += $this->calculateTemporalSimilarity($newItem['pub_date'], $existingItem['pub_date']);
    $score += $this->calculateCategorySimilarity($newItem, $existingItem);
    $score += $this->calculateEntityOverlap($newItem['entities_en'], $existingItem['entities_en']);
    $score += $this->calculateEventSimilarity($newItem['core_event_en'], $existingItem['core_event_en']);
    $score += $this->calculateKeywordOverlap($newItem['keywords_en'], $existingItem['keywords_en']);
    $score += $this->calculateNumericFactsOverlap($newItem['numeric_facts'], $existingItem['numeric_facts']);
    
    return min(100.0, max(0.0, $score));
}
```

---

## 🗄️ ИЗМЕНЕНИЯ В БД

### Новые поля в rss2tlg_deduplication

```sql
ALTER TABLE `rss2tlg_deduplication`
    ADD COLUMN `preliminary_similarity_score` DECIMAL(5,2) DEFAULT NULL 
        COMMENT 'Предварительная оценка схожести (0.00-100.00)' 
        AFTER `similarity_score`,
    ADD COLUMN `preliminary_method` VARCHAR(50) DEFAULT 'hybrid_v1' 
        COMMENT 'Метод предварительной оценки' 
        AFTER `similarity_method`,
    ADD COLUMN `ai_analysis_triggered` TINYINT(1) NOT NULL DEFAULT 0 
        COMMENT 'Был ли вызван AI анализ (0/1)' 
        AFTER `preliminary_method`;

-- Индекс для аналитики
CREATE INDEX idx_preliminary_score ON rss2tlg_deduplication(preliminary_similarity_score);
```

### Обновление getSummarizationData()

**Добавить выборку билингвальных полей:**

```sql
SELECT 
    s.item_id,
    s.feed_id,
    s.status as summarization_status,
    s.headline,
    s.summary,
    s.article_language,
    s.category_primary,
    s.category_primary_en,          -- NEW!
    s.category_secondary,
    s.category_secondary_en,        -- NEW!
    s.keywords,
    s.keywords_en,                  -- NEW!
    s.importance_rating,
    s.dedup_canonical_entities,
    s.dedup_canonical_entities_en,  -- NEW!
    s.dedup_core_event,
    s.dedup_core_event_en,          -- NEW!
    s.dedup_numeric_facts,
    i.title as original_title,
    i.link,
    i.pub_date
FROM rss2tlg_summarization s
INNER JOIN rss2tlg_items i ON s.item_id = i.id
WHERE s.item_id = :item_id
LIMIT 1
```

### Обновление getSimilarItems()

**Улучшенные критерии поиска:**

```sql
SELECT 
    s.item_id,
    s.headline,
    s.summary,
    s.article_language,
    s.category_primary,
    s.category_primary_en,          -- NEW!
    s.category_secondary,
    s.category_secondary_en,        -- NEW!
    s.keywords,
    s.keywords_en,                  -- NEW!
    s.dedup_canonical_entities,
    s.dedup_canonical_entities_en,  -- NEW!
    s.dedup_core_event,
    s.dedup_core_event_en,          -- NEW!
    s.dedup_numeric_facts,
    i.pub_date
FROM rss2tlg_summarization s
INNER JOIN rss2tlg_items i ON s.item_id = i.id
WHERE s.item_id != :item_id
  AND s.status = 'success'
  AND i.pub_date >= DATE_SUB(:ref_date, INTERVAL :days_back DAY)
  AND i.pub_date <= DATE_ADD(:ref_date, INTERVAL 1 DAY)
  AND s.category_primary_en = :category_en          -- Используем EN версию!
ORDER BY i.pub_date DESC
LIMIT :max_limit
```

**Обоснование изменений:**
- ✅ Используем `category_primary_en` для кроссязычного поиска
- ✅ Временной диапазон: от (ref_date - N days) до (ref_date + 1 day)
- ✅ Загружаем билингвальные поля для preliminary анализа

---

## 📝 НОВЫЕ/ОБНОВЛЕННЫЕ МЕТОДЫ

### 1. processItem() - ОБНОВИТЬ

**Добавить логику двухэтапной обработки:**

```php
public function processItem(int $itemId): bool
{
    // ... существующая валидация ...
    
    // Получаем похожие новости
    $similarItems = $this->getSimilarItems($itemId, $itemData);
    
    if (empty($similarItems)) {
        // Fast path: нет похожих новостей
        $this->saveDedupResult($itemId, (int)$itemData['feed_id'], [
            'is_duplicate' => false,
            'can_be_published' => true,
            'similarity_score' => 0.0,
            'preliminary_similarity_score' => 0.0,
            'ai_analysis_triggered' => false,
        ]);
        
        $this->incrementMetric('fast_path_unique');
        return true;
    }
    
    // ЭТАП 1: Preliminary Similarity Analysis
    $preliminaryResults = $this->analyzePreliminarySimilarity($itemData, $similarItems);
    $maxPreliminaryScore = $preliminaryResults['max_score'];
    
    // Проверяем порог
    if ($maxPreliminaryScore < $this->config['preliminary_similarity_threshold']) {
        // Fast path: явно уникальная
        $this->saveDedupResult($itemId, (int)$itemData['feed_id'], [
            'is_duplicate' => false,
            'can_be_published' => true,
            'similarity_score' => $maxPreliminaryScore,
            'preliminary_similarity_score' => $maxPreliminaryScore,
            'ai_analysis_triggered' => false,
        ]);
        
        $this->incrementMetric('fast_path_unique');
        $this->incrementMetric('ai_calls_saved');
        return true;
    }
    
    // ЭТАП 2: AI Semantic Analysis (только для подозрительных)
    $suspiciousItems = $this->filterSuspiciousItems($similarItems, $preliminaryResults);
    $dedupResult = $this->analyzeDeduplicationWithAI($itemId, $itemData, $suspiciousItems);
    
    // Добавляем preliminary метрики
    $dedupResult['preliminary_similarity_score'] = $maxPreliminaryScore;
    $dedupResult['ai_analysis_triggered'] = true;
    
    $this->saveDedupResult($itemId, (int)$itemData['feed_id'], $dedupResult);
    
    // ... метрики ...
}
```

### 2. analyzePreliminarySimilarity() - НОВЫЙ

```php
/**
 * Анализирует предварительную схожесть с похожими новостями
 *
 * @param array<string, mixed> $newItem Новая новость
 * @param array<array<string, mixed>> $similarItems Похожие новости
 * @return array{max_score: float, scores: array<int, float>}
 */
private function analyzePreliminarySimilarity(array $newItem, array $similarItems): array
{
    $scores = [];
    $maxScore = 0.0;
    
    foreach ($similarItems as $existingItem) {
        $score = $this->calculatePreliminarySimilarity($newItem, $existingItem);
        $scores[(int)$existingItem['item_id']] = $score;
        
        if ($score > $maxScore) {
            $maxScore = $score;
        }
    }
    
    $this->logDebug('Preliminary similarity analysis', [
        'max_score' => $maxScore,
        'items_analyzed' => count($similarItems),
    ]);
    
    return [
        'max_score' => $maxScore,
        'scores' => $scores,
    ];
}
```

### 3. calculatePreliminarySimilarity() - НОВЫЙ

```php
/**
 * Рассчитывает предварительную схожесть между двумя новостями
 *
 * @param array<string, mixed> $newItem Новая новость
 * @param array<string, mixed> $existingItem Существующая новость
 * @return float Оценка схожести (0.0-100.0)
 */
private function calculatePreliminarySimilarity(array $newItem, array $existingItem): float
{
    $score = 0.0;
    
    // 1. Temporal proximity (10%)
    $score += $this->calculateTemporalSimilarity(
        $newItem['pub_date'], 
        $existingItem['pub_date']
    );
    
    // 2. Category match (20%)
    $score += $this->calculateCategorySimilarity($newItem, $existingItem);
    
    // 3. Entity overlap (35%) - САМЫЙ ВАЖНЫЙ!
    $entitiesNew = $this->decodeJsonField($newItem['dedup_canonical_entities_en']);
    $entitiesExisting = $this->decodeJsonField($existingItem['dedup_canonical_entities_en']);
    $score += $this->calculateEntityOverlap($entitiesNew, $entitiesExisting);
    
    // 4. Event similarity (20%)
    $score += $this->calculateEventSimilarity(
        $newItem['dedup_core_event_en'] ?? '',
        $existingItem['dedup_core_event_en'] ?? ''
    );
    
    // 5. Keyword overlap (10%)
    $keywordsNew = $this->decodeJsonField($newItem['keywords_en']);
    $keywordsExisting = $this->decodeJsonField($existingItem['keywords_en']);
    $score += $this->calculateKeywordOverlap($keywordsNew, $keywordsExisting);
    
    // 6. Numeric facts (5%)
    $factsNew = $this->decodeJsonField($newItem['dedup_numeric_facts']);
    $factsExisting = $this->decodeJsonField($existingItem['dedup_numeric_facts']);
    $score += $this->calculateNumericFactsOverlap($factsNew, $factsExisting);
    
    // Нормализация к диапазону 0-100
    return min(100.0, max(0.0, $score));
}
```

### 4. Вспомогательные методы расчета

```php
/**
 * Рассчитывает схожесть по времени публикации
 *
 * @param string $date1
 * @param string $date2
 * @return float Баллы (0.0-10.0)
 */
private function calculateTemporalSimilarity(string $date1, string $date2): float
{
    $timestamp1 = strtotime($date1);
    $timestamp2 = strtotime($date2);
    
    if ($timestamp1 === false || $timestamp2 === false) {
        return 0.0;
    }
    
    $hoursDiff = abs($timestamp1 - $timestamp2) / 3600;
    
    if ($hoursDiff <= 6) return 10.0;
    if ($hoursDiff <= 12) return 9.0;
    if ($hoursDiff <= 24) return 8.0;
    if ($hoursDiff <= 48) return 5.0;
    if ($hoursDiff <= 72) return 3.0;
    if ($hoursDiff <= 168) return 1.0;
    
    return 0.0;
}

/**
 * Рассчитывает схожесть по категориям
 *
 * @param array<string, mixed> $item1
 * @param array<string, mixed> $item2
 * @return float Баллы (0.0-20.0)
 */
private function calculateCategorySimilarity(array $item1, array $item2): float
{
    $score = 0.0;
    
    // Primary category match (15 баллов)
    if (($item1['category_primary_en'] ?? '') === ($item2['category_primary_en'] ?? '')) {
        $score += 15.0;
    }
    
    // Secondary categories overlap (5 баллов max)
    $secondary1 = $this->decodeJsonField($item1['category_secondary_en']);
    $secondary2 = $this->decodeJsonField($item2['category_secondary_en']);
    
    $overlap = count(array_intersect($secondary1, $secondary2));
    $score += min(5.0, $overlap * 2.5);
    
    return $score;
}

/**
 * Рассчитывает Jaccard similarity для массивов
 *
 * @param array<string> $array1
 * @param array<string> $array2
 * @return float Jaccard coefficient (0.0-1.0)
 */
private function calculateJaccardSimilarity(array $array1, array $array2): float
{
    if (empty($array1) && empty($array2)) {
        return 0.0;
    }
    
    // Нормализация: lowercase, trim
    $array1 = array_map(fn($s) => strtolower(trim($s)), $array1);
    $array2 = array_map(fn($s) => strtolower(trim($s)), $array2);
    
    $intersection = array_intersect($array1, $array2);
    $union = array_unique(array_merge($array1, $array2));
    
    if (count($union) === 0) {
        return 0.0;
    }
    
    return count($intersection) / count($union);
}

/**
 * Рассчитывает overlap сущностей
 *
 * @param array<string> $entities1
 * @param array<string> $entities2
 * @return float Баллы (0.0-35.0)
 */
private function calculateEntityOverlap(array $entities1, array $entities2): float
{
    $jaccard = $this->calculateJaccardSimilarity($entities1, $entities2);
    return $jaccard * 35.0;
}

/**
 * Рассчитывает схожесть описаний событий
 *
 * @param string $event1
 * @param string $event2
 * @return float Баллы (0.0-20.0)
 */
private function calculateEventSimilarity(string $event1, string $event2): float
{
    if (empty($event1) || empty($event2)) {
        return 0.0;
    }
    
    // Word overlap (60%)
    $words1 = $this->extractSignificantWords($event1);
    $words2 = $this->extractSignificantWords($event2);
    $wordOverlap = $this->calculateJaccardSimilarity($words1, $words2);
    
    // Levenshtein similarity (40%)
    $levenshteinSim = $this->calculateLevenshteinSimilarity($event1, $event2);
    
    $similarity = ($wordOverlap * 0.6) + ($levenshteinSim * 0.4);
    
    return $similarity * 20.0;
}

/**
 * Извлекает значимые слова (без stop words)
 *
 * @param string $text
 * @return array<string>
 */
private function extractSignificantWords(string $text): array
{
    $stopWords = [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from',
        'has', 'he', 'in', 'is', 'it', 'its', 'of', 'on', 'that', 'the',
        'to', 'was', 'will', 'with',
        // Русские stop words
        'в', 'и', 'на', 'с', 'по', 'к', 'о', 'от', 'из', 'за', 'у', 'для'
    ];
    
    $words = preg_split('/\s+/', strtolower($text));
    $words = array_map('trim', $words);
    $words = array_filter($words, fn($w) => !empty($w) && !in_array($w, $stopWords));
    
    return array_values($words);
}

/**
 * Рассчитывает Levenshtein similarity
 *
 * @param string $str1
 * @param string $str2
 * @return float Similarity (0.0-1.0)
 */
private function calculateLevenshteinSimilarity(string $str1, string $str2): float
{
    $str1 = strtolower(trim($str1));
    $str2 = strtolower(trim($str2));
    
    $maxLength = max(mb_strlen($str1), mb_strlen($str2));
    
    if ($maxLength === 0) {
        return 0.0;
    }
    
    $distance = levenshtein($str1, $str2);
    
    return 1.0 - ($distance / $maxLength);
}

/**
 * Рассчитывает overlap ключевых слов
 *
 * @param array<string> $keywords1
 * @param array<string> $keywords2
 * @return float Баллы (0.0-10.0)
 */
private function calculateKeywordOverlap(array $keywords1, array $keywords2): float
{
    $jaccard = $this->calculateJaccardSimilarity($keywords1, $keywords2);
    return $jaccard * 10.0;
}

/**
 * Рассчитывает overlap числовых фактов
 *
 * @param array<string> $facts1
 * @param array<string> $facts2
 * @return float Баллы (0.0-5.0)
 */
private function calculateNumericFactsOverlap(array $facts1, array $facts2): float
{
    if (empty($facts1) || empty($facts2)) {
        return 0.0;
    }
    
    // Извлекаем числа из массивов
    $numbers1 = $this->extractNumbers($facts1);
    $numbers2 = $this->extractNumbers($facts2);
    
    // Подсчитываем совпадения
    $matches = count(array_intersect($numbers1, $numbers2));
    
    return min(5.0, (float)$matches);
}

/**
 * Извлекает числа из массива фактов
 *
 * @param array<string> $facts
 * @return array<string>
 */
private function extractNumbers(array $facts): array
{
    $numbers = [];
    
    foreach ($facts as $fact) {
        // Извлекаем все числа из строки
        if (preg_match_all('/\d+(?:[.,]\d+)?/', $fact, $matches)) {
            $numbers = array_merge($numbers, $matches[0]);
        }
    }
    
    return array_unique($numbers);
}

/**
 * Декодирует JSON поле
 *
 * @param string|null $jsonString
 * @return array<string>
 */
private function decodeJsonField(?string $jsonString): array
{
    if (empty($jsonString)) {
        return [];
    }
    
    $decoded = json_decode($jsonString, true);
    
    return is_array($decoded) ? $decoded : [];
}
```

### 5. filterSuspiciousItems() - НОВЫЙ

```php
/**
 * Фильтрует подозрительные новости для AI анализа
 *
 * @param array<array<string, mixed>> $similarItems
 * @param array{max_score: float, scores: array<int, float>} $preliminaryResults
 * @return array<array<string, mixed>>
 */
private function filterSuspiciousItems(array $similarItems, array $preliminaryResults): array
{
    $threshold = $this->config['preliminary_similarity_threshold'];
    $maxAiComparisons = $this->config['max_ai_comparisons'] ?? 10;
    
    // Фильтруем только suspicious
    $suspicious = [];
    foreach ($similarItems as $item) {
        $itemId = (int)$item['item_id'];
        $score = $preliminaryResults['scores'][$itemId] ?? 0.0;
        
        if ($score >= $threshold) {
            $suspicious[] = [
                'item' => $item,
                'preliminary_score' => $score,
            ];
        }
    }
    
    // Сортируем по убыванию score
    usort($suspicious, fn($a, $b) => $b['preliminary_score'] <=> $a['preliminary_score']);
    
    // Ограничиваем количество
    $suspicious = array_slice($suspicious, 0, $maxAiComparisons);
    
    // Извлекаем только item
    return array_map(fn($s) => $s['item'], $suspicious);
}
```

### 6. analyzeDeduplicationWithAI() - ПЕРЕИМЕНОВАТЬ analyzeDeduplication()

```php
/**
 * Анализирует дедупликацию через AI (только для подозрительных)
 *
 * @param int $itemId
 * @param array<string, mixed> $itemData
 * @param array<array<string, mixed>> $suspiciousItems Только подозрительные новости
 * @return array<string, mixed>|null
 */
private function analyzeDeduplicationWithAI(
    int $itemId, 
    array $itemData, 
    array $suspiciousItems
): ?array {
    // ... существующая логика analyzeDeduplication ...
    // Без изменений - только переименовать
}
```

---

## ⚙️ ОБНОВЛЕНИЕ КОНФИГУРАЦИИ

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
    "max_ai_comparisons": 10,
    
    "preliminary_similarity_threshold": 60,
    "similarity_threshold": 70
}
```

**Новые параметры:**

| Параметр | Тип | Default | Описание |
|----------|-----|---------|----------|
| `preliminary_similarity_threshold` | int | 60 | Порог для запуска AI анализа (0-100) |
| `max_ai_comparisons` | int | 10 | Макс. количество новостей для AI анализа |

---

## 📊 НОВЫЕ МЕТРИКИ

### Обновление initializeMetrics()

```php
protected function initializeMetrics(): array
{
    return [
        'total_processed' => 0,
        'successful' => 0,
        'failed' => 0,
        'skipped' => 0,
        'duplicates_found' => 0,
        'unique_items' => 0,
        'total_tokens' => 0,
        'total_time_ms' => 0,
        'total_comparisons' => 0,
        'model_attempts' => [],
        
        // NEW METRICS v3.0
        'preliminary_checks' => 0,           // Количество preliminary проверок
        'ai_calls_saved' => 0,               // Сколько AI вызовов избежали
        'fast_path_unique' => 0,             // Помечено уникальными без AI
        'ai_triggered' => 0,                 // Вызовов AI после preliminary
        'avg_preliminary_score' => 0.0,      // Средний preliminary score
        'similarity_distribution' => [],     // Распределение preliminary scores
    ];
}
```

### Обновление saveDedupResult()

**Добавить сохранение новых полей:**

```php
private function saveDedupResult(int $itemId, int $feedId, array $result): void
{
    $query = "
        INSERT INTO rss2tlg_deduplication (
            item_id,
            feed_id,
            status,
            is_duplicate,
            duplicate_of_item_id,
            similarity_score,
            preliminary_similarity_score,      -- NEW!
            preliminary_method,                 -- NEW!
            ai_analysis_triggered,              -- NEW!
            similarity_method,
            can_be_published,
            matched_entities,
            matched_events,
            matched_facts,
            model_used,
            tokens_used,
            processing_time_ms,
            items_compared,
            checked_at,
            created_at,
            updated_at
        ) VALUES (
            :item_id,
            :feed_id,
            'checked',
            :is_duplicate,
            :duplicate_of_item_id,
            :similarity_score,
            :preliminary_similarity_score,     -- NEW!
            :preliminary_method,                -- NEW!
            :ai_analysis_triggered,             -- NEW!
            :similarity_method,
            :can_be_published,
            :matched_entities,
            :matched_events,
            :matched_facts,
            :model_used,
            :tokens_used,
            :processing_time_ms,
            :items_compared,
            NOW(),
            NOW(),
            NOW()
        )
        ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            is_duplicate = VALUES(is_duplicate),
            duplicate_of_item_id = VALUES(duplicate_of_item_id),
            similarity_score = VALUES(similarity_score),
            preliminary_similarity_score = VALUES(preliminary_similarity_score),
            preliminary_method = VALUES(preliminary_method),
            ai_analysis_triggered = VALUES(ai_analysis_triggered),
            similarity_method = VALUES(similarity_method),
            can_be_published = VALUES(can_be_published),
            matched_entities = VALUES(matched_entities),
            matched_events = VALUES(matched_events),
            matched_facts = VALUES(matched_facts),
            model_used = VALUES(model_used),
            tokens_used = VALUES(tokens_used),
            processing_time_ms = VALUES(processing_time_ms),
            items_compared = VALUES(items_compared),
            checked_at = VALUES(checked_at),
            updated_at = NOW()
    ";
    
    $params = [
        'item_id' => $itemId,
        'feed_id' => $feedId,
        'is_duplicate' => $result['is_duplicate'] ? 1 : 0,
        'duplicate_of_item_id' => $result['duplicate_of_item_id'] ?? null,
        'similarity_score' => $result['similarity_score'] ?? 0.0,
        'preliminary_similarity_score' => $result['preliminary_similarity_score'] ?? null,
        'preliminary_method' => $result['preliminary_method'] ?? 'hybrid_v1',
        'ai_analysis_triggered' => $result['ai_analysis_triggered'] ?? 0,
        'similarity_method' => $result['similarity_method'] ?? 'hybrid',
        'can_be_published' => $result['can_be_published'] ? 1 : 0,
        'matched_entities' => $result['matched_entities'] ?? '[]',
        'matched_events' => $result['matched_events'] ?? null,
        'matched_facts' => $result['matched_facts'] ?? '[]',
        'model_used' => $result['model_used'] ?? null,
        'tokens_used' => $result['tokens_used'] ?? null,
        'processing_time_ms' => $result['processing_time_ms'] ?? 0,
        'items_compared' => $result['items_compared'] ?? 0,
    ];
    
    $this->db->execute($query, $params);
}
```

---

## 🗂️ ФАЙЛОВАЯ СТРУКТУРА

```
src/Rss2Tlg/Pipeline/
├── DeduplicationService.php         ← ОБНОВИТЬ (v3.0)
├── AbstractPipelineModule.php       ← БЕЗ ИЗМЕНЕНИЙ
└── AIAnalysisTrait.php              ← БЕЗ ИЗМЕНЕНИЙ

production/
├── configs/
│   └── deduplication.json           ← ОБНОВИТЬ (новые параметры)
├── prompts/
│   └── deduplication_prompt_v2.txt  ← БЕЗ ИЗМЕНЕНИЙ (опционально: добавить preliminary_score)
└── sql/
    └── migration_dedup_v3.sql       ← СОЗДАТЬ (новые поля в БД)

docs/Rss2Tlg/
├── DEDUPLICATION_REFACTORING_PLAN_V3.md  ← ЭТОТ ДОКУМЕНТ
└── Pipeline_Deduplication_README.md      ← ОБНОВИТЬ (v3.0 API)
```

---

## 🎯 ЭТАПЫ РЕАЛИЗАЦИИ

### Этап 1: Подготовка БД (5 мин)
- ✅ Создать миграцию `migration_dedup_v3.sql`
- ✅ Добавить поля: preliminary_similarity_score, preliminary_method, ai_analysis_triggered
- ✅ Создать индексы

### Этап 2: Обновление SQL запросов (10 мин)
- ✅ Обновить `getSummarizationData()` - добавить билингвальные поля
- ✅ Обновить `getSimilarItems()` - улучшенные критерии поиска
- ✅ Протестировать запросы

### Этап 3: Реализация Preliminary Similarity (40 мин)
- ✅ Создать `calculatePreliminarySimilarity()`
- ✅ Создать `calculateTemporalSimilarity()`
- ✅ Создать `calculateCategorySimilarity()`
- ✅ Создать `calculateEntityOverlap()`
- ✅ Создать `calculateEventSimilarity()`
- ✅ Создать `calculateKeywordOverlap()`
- ✅ Создать `calculateNumericFactsOverlap()`
- ✅ Создать вспомогательные методы:
  - `calculateJaccardSimilarity()`
  - `calculateLevenshteinSimilarity()`
  - `extractSignificantWords()`
  - `extractNumbers()`
  - `decodeJsonField()`

### Этап 4: Двухэтапная логика (20 мин)
- ✅ Создать `analyzePreliminarySimilarity()`
- ✅ Создать `filterSuspiciousItems()`
- ✅ Переименовать `analyzeDeduplication()` → `analyzeDeduplicationWithAI()`
- ✅ Обновить `processItem()` - двухэтапная логика

### Этап 5: Метрики и сохранение (10 мин)
- ✅ Обновить `initializeMetrics()`
- ✅ Обновить `saveDedupResult()`
- ✅ Добавить логирование preliminary scores

### Этап 6: Конфигурация и документация (10 мин)
- ✅ Обновить `production/configs/deduplication.json`
- ✅ Обновить `docs/Rss2Tlg/Pipeline_Deduplication_README.md`
- ✅ Добавить примеры использования

### Этап 7: Тестирование (НЕ ДЕЛАТЬ СЕЙЧАС!)
- ⏸️ Создать тестовый скрипт (ПОЗЖЕ)
- ⏸️ Протестировать на реальных данных (ПОЗЖЕ)

---

## 📈 ОЖИДАЕМЫЕ РЕЗУЛЬТАТЫ

### Производительность

| Метрика | v2.0 (текущая) | v3.0 (планируемая) | Улучшение |
|---------|----------------|---------------------|-----------|
| **AI вызовов** | 100% | 20-50% | ↓ 50-80% |
| **Время обработки** | ~60 сек/новость | ~5-60 сек | ↓ ~50% avg |
| **Стоимость** | $0.05/новость | $0.01-0.05 | ↓ 50-80% |
| **Токенов** | ~7,000/новость | ~1,500-7,000 | ↓ 50-80% |

### Точность

| Показатель | v2.0 | v3.0 | Комментарий |
|------------|------|------|-------------|
| **False Positives** | Low | Low | Без изменений (AI все еще используется) |
| **False Negatives** | Low | Medium-Low | Возможны из-за порога (настраиваемо) |
| **Кросс-язык** | ❌ Нет | ✅ Да | Английские метаданные! |

### Экономия

- **50-80% новостей** будут помечены уникальными БЕЗ AI вызова
- **Экономия токенов:** ~5,000-6,000 токенов/новость (для fast path)
- **Экономия времени:** ~55-58 сек/новость (для fast path)
- **Экономия денег:** ~$0.04/новость (для fast path)

**Пример расчета для 1,000 новостей/день:**
- v2.0: 1,000 × $0.05 = **$50/день**
- v3.0: (300 × $0.05) + (700 × $0.001) = **$15.70/день**
- **Экономия:** $34.30/день = **$1,029/месяц** = **$12,348/год** 🎉

---

## ⚠️ КРИТИЧЕСКИЕ ЗАМЕЧАНИЯ

### 1. Настройка порога

**Preliminary Similarity Threshold** - ключевой параметр!

- **Слишком низкий (30-40):** Почти все пойдут на AI → нет экономии
- **Оптимальный (60-70):** Баланс точности и экономии
- **Слишком высокий (80-90):** Много false negatives (пропущенные дубликаты)

**Рекомендация:** Начать с 60, мониторить False Negatives, подстраивать.

### 2. Кроссязычная дедупликация

**Зависимость от качества AI перевода:**
- Если AI плохо переводит entities/events на английский → preliminary фильтр не сработает
- **Решение:** Мониторить качество переводов в суммаризации

### 3. Stop words

**Список stop words** в `extractSignificantWords()` может потребовать расширения для разных языков.

**Рекомендация:** Добавлять по мере необходимости (испанский, немецкий и т.д.)

### 4. Levenshtein ограничения

**levenshtein()** в PHP работает только для коротких строк (<255 символов).

**Решение:** Обрезать `core_event_en` до 200 символов перед сравнением:
```php
$event1 = mb_substr($event1, 0, 200);
$event2 = mb_substr($event2, 0, 200);
```

---

## 🎓 ВЫВОДЫ

### Достоинства подхода

1. ✅ **Монолитная реализация** - нет излишних абстракций
2. ✅ **Простые алгоритмы** - Jaccard, Levenshtein, time diff
3. ✅ **Настраиваемость** - пороги в конфиге
4. ✅ **Прозрачность** - сохраняем preliminary_score
5. ✅ **Экономия** - 50-80% AI вызовов
6. ✅ **Кросс-язык** - через английские метаданные

### Недостатки подхода

1. ⚠️ **Требует настройки** порога preliminary_similarity_threshold
2. ⚠️ **Зависимость от качества** AI переводов entities/events
3. ⚠️ **Возможны false negatives** при высоком пороге
4. ⚠️ **Сложность алгоритма** - больше кода для поддержки

### Рекомендации

1. **Начать с консервативного порога** (60) и мониторить
2. **Собирать метрики** preliminary_score distribution
3. **Анализировать false negatives** и подстраивать веса компонентов
4. **Мониторить качество** AI переводов в суммаризации
5. **A/B тестирование** разных порогов (60 vs 70 vs 80)

---

## 📄 ФАЙЛЫ ДЛЯ СОЗДАНИЯ/ОБНОВЛЕНИЯ

### СОЗДАТЬ:
1. `production/sql/migration_dedup_v3.sql` - миграция БД

### ОБНОВИТЬ:
1. `src/Rss2Tlg/Pipeline/DeduplicationService.php` - основной класс (v3.0)
2. `production/configs/deduplication.json` - конфигурация
3. `docs/Rss2Tlg/Pipeline_Deduplication_README.md` - API документация

### НЕ ИЗМЕНЯТЬ:
1. `src/Rss2Tlg/Pipeline/AbstractPipelineModule.php`
2. `src/Rss2Tlg/Pipeline/AIAnalysisTrait.php`
3. `production/prompts/deduplication_prompt_v2.txt` (опционально: можно добавить preliminary_score в промпт)

---

## ✅ ЧЕКЛИСТ РЕАЛИЗАЦИИ

- [ ] Создать миграцию `migration_dedup_v3.sql`
- [ ] Обновить SQL запросы (getSummarizationData, getSimilarItems)
- [ ] Реализовать calculatePreliminarySimilarity() + 6 компонентов
- [ ] Реализовать analyzePreliminarySimilarity()
- [ ] Реализовать filterSuspiciousItems()
- [ ] Переименовать analyzeDeduplication() → analyzeDeduplicationWithAI()
- [ ] Обновить processItem() - двухэтапная логика
- [ ] Обновить initializeMetrics()
- [ ] Обновить saveDedupResult()
- [ ] Обновить конфигурацию deduplication.json
- [ ] Обновить документацию Pipeline_Deduplication_README.md
- [ ] Добавить примеры использования
- [ ] Код-ревью и оптимизация
- [ ] ⏸️ Тестирование (ПОЗЖЕ!)

---

**Дата документа:** 2025-11-10  
**Версия:** 1.0  
**Статус:** 📋 PLAN READY - ГОТОВ К РЕАЛИЗАЦИИ

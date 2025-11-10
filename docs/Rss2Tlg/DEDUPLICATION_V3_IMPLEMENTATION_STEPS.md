# 🔨 DeduplicationService v3.0 - Пошаговая инструкция по реализации

**Дата:** 2025-11-10  
**Версия:** 3.0 - Stepwise Multi-Language AI Deduplication

---

## 📋 ОБЩИЙ ПЛАН

Всего **7 этапов**, каждый с четкими задачами и чек-листом.

**Общее время:** ~95 минут чистого кодирования

**НЕ ДЕЛАТЬ СЕЙЧАС:**
- ❌ Тестирование
- ❌ Создание тестовых скриптов
- ❌ Запуск на production данных

---

## ЭТАП 1: Подготовка БД (5 мин) ✅ ГОТОВО

### Задачи
- [x] Создать миграцию `migration_dedup_v3.sql`
- [x] Добавить поля в таблицу `rss2tlg_deduplication`
- [x] Создать индексы

### Файл
✅ `/home/engine/project/production/sql/migration_dedup_v3.sql`

### Проверка
```bash
# Применить миграцию (ПОЗЖЕ, после реализации кода)
mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg < production/sql/migration_dedup_v3.sql
```

---

## ЭТАП 2: Обновление SQL запросов (10 мин)

### Задача 2.1: Обновить getSummarizationData()

**Файл:** `src/Rss2Tlg/Pipeline/DeduplicationService.php`

**Строки:** ~220-245

**Изменения:**
```php
// ДОБАВИТЬ в SELECT:
s.category_primary_en,          -- НОВОЕ
s.category_secondary_en,        -- НОВОЕ
s.keywords_en,                  -- НОВОЕ
s.dedup_canonical_entities_en,  -- НОВОЕ
s.dedup_core_event_en,          -- НОВОЕ
```

**Полный запрос:**
```php
private function getSummarizationData(int $itemId): ?array
{
    $query = "
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
    ";
    
    return $this->db->queryOne($query, ['item_id' => $itemId]);
}
```

### Задача 2.2: Обновить getSimilarItems()

**Файл:** `src/Rss2Tlg/Pipeline/DeduplicationService.php`

**Строки:** ~254-299

**Изменения:**
1. Добавить билингвальные поля в SELECT
2. Изменить WHERE: использовать `category_primary_en` вместо `category_primary`
3. Улучшить временной фильтр

**Полный запрос:**
```php
private function getSimilarItems(int $itemId, array $itemData): array
{
    $daysBack = $this->config['compare_last_n_days'];
    $maxComparisons = $this->config['max_comparisons'];
    
    // Получаем pub_date текущей новости для reference
    $refDate = $itemData['pub_date'] ?? date('Y-m-d H:i:s');
    
    $query = "
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
          AND s.category_primary_en = :category_en
        ORDER BY i.pub_date DESC
        LIMIT :max_limit
    ";
    
    $params = [
        'item_id' => $itemId,
        'ref_date' => $refDate,
        'days_back' => $daysBack,
        'category_en' => $itemData['category_primary_en'] ?? '',
        'max_limit' => $maxComparisons,
    ];
    
    $results = $this->db->query($query, $params);
    
    $this->logDebug('Найдено похожих новостей для сравнения', [
        'item_id' => $itemId,
        'count' => count($results),
        'category_en' => $params['category_en'],
    ]);
    
    return $results;
}
```

### Чек-лист Этап 2
- [ ] Обновлен `getSummarizationData()` - добавлено 5 билингвальных полей
- [ ] Обновлен `getSimilarItems()` - добавлено 5 билингвальных полей + улучшен WHERE
- [ ] Протестированы запросы (визуально, синтаксис SQL)

---

## ЭТАП 3: Вспомогательные методы (20 мин)

### Задача 3.1: Добавить decodeJsonField()

**Место:** В конец класса, перед закрывающей скобкой

```php
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

### Задача 3.2: Добавить calculateJaccardSimilarity()

```php
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
```

### Задача 3.3: Добавить calculateLevenshteinSimilarity()

```php
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
    
    // Ограничиваем длину для levenshtein (PHP limit ~255 символов)
    $str1 = mb_substr($str1, 0, 200);
    $str2 = mb_substr($str2, 0, 200);
    
    $maxLength = max(mb_strlen($str1), mb_strlen($str2));
    
    if ($maxLength === 0) {
        return 0.0;
    }
    
    $distance = levenshtein($str1, $str2);
    
    return 1.0 - ($distance / $maxLength);
}
```

### Задача 3.4: Добавить extractSignificantWords()

```php
/**
 * Извлекает значимые слова (без stop words)
 *
 * @param string $text
 * @return array<string>
 */
private function extractSignificantWords(string $text): array
{
    $stopWords = [
        // English stop words
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from',
        'has', 'he', 'in', 'is', 'it', 'its', 'of', 'on', 'that', 'the',
        'to', 'was', 'will', 'with',
        // Russian stop words
        'в', 'и', 'на', 'с', 'по', 'к', 'о', 'от', 'из', 'за', 'у', 'для',
    ];
    
    $words = preg_split('/\s+/', strtolower($text));
    $words = array_map('trim', $words);
    $words = array_filter($words, fn($w) => !empty($w) && !in_array($w, $stopWords));
    
    return array_values($words);
}
```

### Задача 3.5: Добавить extractNumbers()

```php
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
```

### Чек-лист Этап 3
- [ ] Добавлен `decodeJsonField()`
- [ ] Добавлен `calculateJaccardSimilarity()`
- [ ] Добавлен `calculateLevenshteinSimilarity()`
- [ ] Добавлен `extractSignificantWords()`
- [ ] Добавлен `extractNumbers()`

---

## ЭТАП 4: Компоненты схожести (25 мин)

### Задача 4.1: calculateTemporalSimilarity()

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
```

### Задача 4.2: calculateCategorySimilarity()

```php
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
    $secondary1 = $this->decodeJsonField($item1['category_secondary_en'] ?? null);
    $secondary2 = $this->decodeJsonField($item2['category_secondary_en'] ?? null);
    
    $overlap = count(array_intersect($secondary1, $secondary2));
    $score += min(5.0, $overlap * 2.5);
    
    return $score;
}
```

### Задача 4.3: calculateEntityOverlap()

```php
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
```

### Задача 4.4: calculateEventSimilarity()

```php
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
```

### Задача 4.5: calculateKeywordOverlap()

```php
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
```

### Задача 4.6: calculateNumericFactsOverlap()

```php
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
```

### Чек-лист Этап 4
- [ ] Добавлен `calculateTemporalSimilarity()`
- [ ] Добавлен `calculateCategorySimilarity()`
- [ ] Добавлен `calculateEntityOverlap()`
- [ ] Добавлен `calculateEventSimilarity()`
- [ ] Добавлен `calculateKeywordOverlap()`
- [ ] Добавлен `calculateNumericFactsOverlap()`

---

## ЭТАП 5: Главный метод Preliminary Similarity (15 мин)

### Задача 5.1: calculatePreliminarySimilarity()

**Место:** После компонентов схожести

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
        $newItem['pub_date'] ?? '',
        $existingItem['pub_date'] ?? ''
    );
    
    // 2. Category match (20%)
    $score += $this->calculateCategorySimilarity($newItem, $existingItem);
    
    // 3. Entity overlap (35%) - САМЫЙ ВАЖНЫЙ!
    $entitiesNew = $this->decodeJsonField($newItem['dedup_canonical_entities_en'] ?? null);
    $entitiesExisting = $this->decodeJsonField($existingItem['dedup_canonical_entities_en'] ?? null);
    $score += $this->calculateEntityOverlap($entitiesNew, $entitiesExisting);
    
    // 4. Event similarity (20%)
    $score += $this->calculateEventSimilarity(
        $newItem['dedup_core_event_en'] ?? '',
        $existingItem['dedup_core_event_en'] ?? ''
    );
    
    // 5. Keyword overlap (10%)
    $keywordsNew = $this->decodeJsonField($newItem['keywords_en'] ?? null);
    $keywordsExisting = $this->decodeJsonField($existingItem['keywords_en'] ?? null);
    $score += $this->calculateKeywordOverlap($keywordsNew, $keywordsExisting);
    
    // 6. Numeric facts (5%)
    $factsNew = $this->decodeJsonField($newItem['dedup_numeric_facts'] ?? null);
    $factsExisting = $this->decodeJsonField($existingItem['dedup_numeric_facts'] ?? null);
    $score += $this->calculateNumericFactsOverlap($factsNew, $factsExisting);
    
    // Нормализация к диапазону 0-100
    return min(100.0, max(0.0, $score));
}
```

### Задача 5.2: analyzePreliminarySimilarity()

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
        'max_score' => round($maxScore, 2),
        'items_analyzed' => count($similarItems),
    ]);
    
    return [
        'max_score' => $maxScore,
        'scores' => $scores,
    ];
}
```

### Задача 5.3: filterSuspiciousItems()

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
    
    $this->logDebug('Filtered suspicious items for AI', [
        'threshold' => $threshold,
        'suspicious_count' => count($suspicious),
        'max_allowed' => $maxAiComparisons,
    ]);
    
    // Извлекаем только item
    return array_map(fn($s) => $s['item'], $suspicious);
}
```

### Чек-лист Этап 5
- [ ] Добавлен `calculatePreliminarySimilarity()`
- [ ] Добавлен `analyzePreliminarySimilarity()`
- [ ] Добавлен `filterSuspiciousItems()`

---

## ЭТАП 6: Обновление основных методов (15 мин)

### Задача 6.1: Переименовать analyzeDeduplication()

**Старое имя:** `analyzeDeduplication()`  
**Новое имя:** `analyzeDeduplicationWithAI()`

**Изменения:**
1. Переименовать метод
2. Обновить PHPDoc
3. Обновить вызов в `processItem()`

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
    // ... СУЩЕСТВУЮЩИЙ КОД БЕЗ ИЗМЕНЕНИЙ ...
    // Просто переименовать метод
}
```

### Задача 6.2: Обновить processItem()

**Файл:** `src/Rss2Tlg/Pipeline/DeduplicationService.php`

**Строки:** ~98-201

**Заменить логику после получения similarItems:**

```php
public function processItem(int $itemId): bool
{
    // ... существующий код до $similarItems ...
    
    // Получаем похожие новости для сравнения
    $similarItems = $this->getSimilarItems($itemId, $itemData);

    if (empty($similarItems)) {
        // Нет похожих новостей - точно не дубликат
        $this->saveDedupResult($itemId, (int)$itemData['feed_id'], [
            'is_duplicate' => false,
            'can_be_published' => true,
            'similarity_score' => 0.0,
            'preliminary_similarity_score' => 0.0,
            'preliminary_method' => 'hybrid_v1',
            'ai_analysis_triggered' => false,
            'similarity_method' => 'hybrid',
            'items_compared' => 0,
        ]);

        $this->incrementMetric('successful');
        $this->incrementMetric('unique_items');
        $this->incrementMetric('fast_path_unique');

        $this->logInfo('Похожих новостей не найдено - уникальна', ['item_id' => $itemId]);
        return true;
    }

    // ЭТАП 1: Preliminary Similarity Analysis
    $preliminaryResults = $this->analyzePreliminarySimilarity($itemData, $similarItems);
    $maxPreliminaryScore = $preliminaryResults['max_score'];
    
    $this->incrementMetric('preliminary_checks');
    
    // Проверяем порог
    $threshold = $this->config['preliminary_similarity_threshold'];
    if ($maxPreliminaryScore < $threshold) {
        // Fast path: явно уникальная
        $this->saveDedupResult($itemId, (int)$itemData['feed_id'], [
            'is_duplicate' => false,
            'can_be_published' => true,
            'similarity_score' => $maxPreliminaryScore,
            'preliminary_similarity_score' => $maxPreliminaryScore,
            'preliminary_method' => 'hybrid_v1',
            'ai_analysis_triggered' => false,
            'similarity_method' => 'hybrid',
            'items_compared' => count($similarItems),
        ]);
        
        $processingTime = $this->recordProcessingTime($startTime);
        $this->incrementMetric('successful');
        $this->incrementMetric('unique_items');
        $this->incrementMetric('fast_path_unique');
        $this->incrementMetric('ai_calls_saved');
        
        $this->logInfo('Fast path: уникальная (preliminary < threshold)', [
            'item_id' => $itemId,
            'preliminary_score' => round($maxPreliminaryScore, 2),
            'threshold' => $threshold,
            'processing_time_ms' => $processingTime,
        ]);
        
        return true;
    }
    
    // ЭТАП 2: AI Semantic Analysis (только для подозрительных)
    $suspiciousItems = $this->filterSuspiciousItems($similarItems, $preliminaryResults);
    
    $this->logInfo('AI analysis triggered', [
        'item_id' => $itemId,
        'preliminary_score' => round($maxPreliminaryScore, 2),
        'threshold' => $threshold,
        'suspicious_items' => count($suspiciousItems),
    ]);
    
    $dedupResult = $this->analyzeDeduplicationWithAI($itemId, $itemData, $suspiciousItems);

    if (!$dedupResult) {
        throw new AIAnalysisException("Не удалось получить результат дедупликации от AI");
    }

    // Добавляем preliminary метрики
    $dedupResult['preliminary_similarity_score'] = $maxPreliminaryScore;
    $dedupResult['preliminary_method'] = 'hybrid_v1';
    $dedupResult['ai_analysis_triggered'] = true;

    // Сохраняем результат
    $this->saveDedupResult($itemId, (int)$itemData['feed_id'], $dedupResult);

    $processingTime = $this->recordProcessingTime($startTime);
    $this->incrementMetric('successful');
    $this->incrementMetric('ai_triggered');

    if ($dedupResult['is_duplicate']) {
        $this->incrementMetric('duplicates_found');
    } else {
        $this->incrementMetric('unique_items');
    }

    $this->logInfo('Дедупликация завершена (AI)', [
        'item_id' => $itemId,
        'is_duplicate' => $dedupResult['is_duplicate'],
        'similarity_score' => $dedupResult['similarity_score'],
        'preliminary_score' => round($maxPreliminaryScore, 2),
        'processing_time_ms' => $processingTime,
    ]);

    return true;
}
```

### Задача 6.3: Обновить initializeMetrics()

**Файл:** `src/Rss2Tlg/Pipeline/DeduplicationService.php`

**Строки:** ~79-93

**Добавить новые метрики:**

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
        'preliminary_checks' => 0,      // Количество preliminary проверок
        'ai_calls_saved' => 0,          // Сколько AI вызовов избежали
        'fast_path_unique' => 0,        // Помечено уникальными без AI
        'ai_triggered' => 0,            // Вызовов AI после preliminary
    ];
}
```

### Задача 6.4: Обновить saveDedupResult()

**Файл:** `src/Rss2Tlg/Pipeline/DeduplicationService.php`

**Строки:** ~454-534

**Добавить новые поля:**

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
            similarity_method,
            preliminary_method,                 -- NEW!
            ai_analysis_triggered,              -- NEW!
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
            :similarity_method,
            :preliminary_method,                -- NEW!
            :ai_analysis_triggered,             -- NEW!
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
            similarity_method = VALUES(similarity_method),
            preliminary_method = VALUES(preliminary_method),
            ai_analysis_triggered = VALUES(ai_analysis_triggered),
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
        'similarity_method' => $result['similarity_method'] ?? 'hybrid',
        'preliminary_method' => $result['preliminary_method'] ?? 'hybrid_v1',
        'ai_analysis_triggered' => $result['ai_analysis_triggered'] ?? 0,
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

### Чек-лист Этап 6
- [ ] Переименован `analyzeDeduplication()` → `analyzeDeduplicationWithAI()`
- [ ] Обновлен `processItem()` - двухэтапная логика
- [ ] Обновлен `initializeMetrics()` - новые метрики
- [ ] Обновлен `saveDedupResult()` - новые поля

---

## ЭТАП 7: Конфигурация и версия (5 мин)

### Задача 7.1: Обновить PHPDoc класса

**Файл:** `src/Rss2Tlg/Pipeline/DeduplicationService.php`

**Строки:** 13-23

**Обновить:**

```php
/**
 * Сервис дедупликации новостей
 * 
 * Второй этап AI Pipeline:
 * - Проверка новостей на дубликаты
 * - Двухэтапная дедупликация (Preliminary + AI)
 * - Кроссязычная дедупликация через английские метаданные
 * - Сравнение с существующими новостями за последние N дней
 * - Определение схожести через AI анализ (только для подозрительных)
 * - Маркировка дубликатов и определение возможности публикации
 * 
 * @version 3.0 - Stepwise Multi-Language AI Deduplication
 */
```

### Задача 7.2: Обновить validateModuleConfig()

**Файл:** `src/Rss2Tlg/Pipeline/DeduplicationService.php`

**Строки:** ~60-74

**Добавить валидацию новых параметров:**

```php
protected function validateModuleConfig(array $config): array
{
    $aiConfig = $this->validateAIConfig($config);

    $similarityThreshold = (float)($config['similarity_threshold'] ?? 70.0);
    if ($similarityThreshold < 0 || $similarityThreshold > 100) {
        throw new AIAnalysisException('similarity_threshold должен быть между 0 и 100');
    }
    
    // NEW: валидация preliminary_similarity_threshold
    $preliminaryThreshold = (float)($config['preliminary_similarity_threshold'] ?? 60.0);
    if ($preliminaryThreshold < 0 || $preliminaryThreshold > 100) {
        throw new AIAnalysisException('preliminary_similarity_threshold должен быть между 0 и 100');
    }

    return array_merge($aiConfig, [
        'similarity_threshold' => $similarityThreshold,
        'preliminary_similarity_threshold' => $preliminaryThreshold,  // NEW!
        'compare_last_n_days' => max(1, (int)($config['compare_last_n_days'] ?? 7)),
        'max_comparisons' => max(10, (int)($config['max_comparisons'] ?? 50)),
        'max_ai_comparisons' => max(1, (int)($config['max_ai_comparisons'] ?? 10)),  // NEW!
    ]);
}
```

### Задача 7.3: Обновить конфигурацию

**Файл:** `production/configs/deduplication.json`

**Добавить новые параметры:**

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

### Чек-лист Этап 7
- [ ] Обновлен PHPDoc класса - версия 3.0
- [ ] Обновлен `validateModuleConfig()` - новые параметры
- [ ] Обновлен `production/configs/deduplication.json`

---

## 🎯 ФИНАЛЬНЫЙ ЧЕК-ЛИСТ

### Код
- [ ] ✅ Этап 1: БД миграция создана
- [ ] ✅ Этап 2: SQL запросы обновлены (2 метода)
- [ ] ✅ Этап 3: Вспомогательные методы (5 методов)
- [ ] ✅ Этап 4: Компоненты схожести (6 методов)
- [ ] ✅ Этап 5: Preliminary similarity (3 метода)
- [ ] ✅ Этап 6: Основные методы (4 обновления)
- [ ] ✅ Этап 7: Конфигурация и версия

### Файлы обновлены
- [ ] `src/Rss2Tlg/Pipeline/DeduplicationService.php` - v3.0
- [ ] `production/configs/deduplication.json`
- [ ] `production/sql/migration_dedup_v3.sql` ✅

### Файлы созданы
- [ ] `docs/Rss2Tlg/DEDUPLICATION_REFACTORING_PLAN_V3.md` ✅
- [ ] `docs/Rss2Tlg/DEDUPLICATION_V3_SUMMARY.md` ✅
- [ ] `docs/Rss2Tlg/DEDUPLICATION_V3_IMPLEMENTATION_STEPS.md` ✅

### Количество методов
**Всего новых/обновленных:** ~25 методов

**Новые приватные методы (20):**
1. `decodeJsonField()`
2. `calculateJaccardSimilarity()`
3. `calculateLevenshteinSimilarity()`
4. `extractSignificantWords()`
5. `extractNumbers()`
6. `calculateTemporalSimilarity()`
7. `calculateCategorySimilarity()`
8. `calculateEntityOverlap()`
9. `calculateEventSimilarity()`
10. `calculateKeywordOverlap()`
11. `calculateNumericFactsOverlap()`
12. `calculatePreliminarySimilarity()`
13. `analyzePreliminarySimilarity()`
14. `filterSuspiciousItems()`

**Обновленные методы (6):**
1. `getSummarizationData()`
2. `getSimilarItems()`
3. `processItem()`
4. `initializeMetrics()`
5. `validateModuleConfig()`
6. `saveDedupResult()`

**Переименованные методы (1):**
1. `analyzeDeduplication()` → `analyzeDeduplicationWithAI()`

---

## 📄 СЛЕДУЮЩИЕ ШАГИ (ПОСЛЕ РЕАЛИЗАЦИИ)

**НЕ ДЕЛАТЬ СЕЙЧАС:**

1. ⏸️ Применить миграцию БД
2. ⏸️ Создать тестовый скрипт
3. ⏸️ Протестировать на реальных данных
4. ⏸️ Обновить API документацию
5. ⏸️ Мониторинг метрик в production

---

**Дата:** 2025-11-10  
**Версия:** 1.0  
**Статус:** 📋 IMPLEMENTATION GUIDE READY

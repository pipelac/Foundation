# 🎯 План реализации предварительной проверки схожести для дедупликации

**Дата:** 2025-01-XX  
**Статус:** 📋 В разработке  
**Версия:** 1.0

---

## 📊 Анализ текущей ситуации

### Проблема

Текущая реализация `DeduplicationService` отправляет **ВСЕ** похожие новости (до 50 штук) в AI для анализа, что приводит к:

1. **Высокой стоимости** - каждый AI запрос обрабатывает 50 новостей
2. **Медленной работе** - большие промпты требуют больше времени
3. **Неэффективности** - многие новости явно не дубликаты, но все равно анализируются

### Конфигурация (текущая)

```json
{
    "compare_last_n_days": 7,
    "max_comparisons": 50,              // ← используется при выборке из БД
    "max_ai_comparisons": 10,           // ← НЕ ИСПОЛЬЗУЕТСЯ! ❌
    "preliminary_similarity_threshold": 60,  // ← НЕ ИСПОЛЬЗУЕТСЯ! ❌
    "similarity_threshold": 70          // ← используется AI для определения дубликата
}
```

**Вопрос:** Зачем три поля, если используется только одно?

---

## ✅ Оптимальное решение

### Концепция

Добавить **двухэтапную проверку**:

```
Этап 1: Быстрая текстовая проверка (preliminary similarity)
         ↓
    similarity < 60% → пропускаем (не дубликат)
    similarity >= 60% → отправляем в AI
         ↓
Этап 2: AI анализ (только топ-10 самых похожих)
```

### Логика работы

1. **Получаем** до 50 похожих новостей из БД (`max_comparisons`)
2. **Проверяем** каждую через быструю текстовую схожесть
3. **Фильтруем** новости с similarity < 60% (`preliminary_similarity_threshold`)
4. **Сортируем** оставшиеся по убыванию схожести
5. **Берем топ-10** (`max_ai_comparisons`) для отправки в AI
6. **Анализируем** через AI только эти 10 новостей

### Экономия

- **До 80% токенов** - вместо 50 новостей отправляем 10
- **До 70% времени** - меньше новостей = быстрее обработка
- **До 80% стоимости** - пропорционально токенам

---

## 🔧 Технические детали

### Какие поля использовать?

❌ **НЕ используем:**
- `headline` - на языке оригинала (ru, en, etc)
- `summary` - на языке оригинала

✅ **Используем билингвальные поля:**
- `dedup_canonical_entities_en` - JSON массив сущностей на английском
- `dedup_core_event_en` - TEXT описание события на английском  
- `keywords_en` - JSON массив ключевых слов на английском

**Преимущества:**
- Работает для любого языка (ru, en, zh, etc)
- Нормализованные данные (all lowercase, без спецсимволов)
- Созданы специально для дедупликации

### Алгоритм вычисления схожести

**Метод:** Weighted Average (взвешенное среднее)

```php
similarity = 
    jaccard(entities_en) * 40% +
    cosine(core_event_en) * 30% +
    jaccard(keywords_en) * 30%
```

**Метрики:**

1. **Jaccard Similarity** для массивов (entities, keywords)
   ```
   J(A,B) = |A ∩ B| / |A ∪ B|
   ```
   - Быстро
   - Просто
   - Эффективно для наборов
   
2. **Cosine Similarity** для текста (core_event)
   ```
   cos(A,B) = (A·B) / (||A|| * ||B||)
   ```
   - Bag-of-words векторизация
   - Учитывает частоту слов
   - Устойчиво к длине текста

### Веса компонентов

| Компонент | Вес | Обоснование |
|-----------|-----|-------------|
| Entities | 40% | Самый важный - одинаковые люди/организации = высокая вероятность дубликата |
| Event | 30% | Важный - описание события показывает суть новости |
| Keywords | 30% | Дополнительный - помогает уточнить схожесть |

---

## 📝 Изменения в коде

### 1. Обновить `getSimilarItems()`

**Добавить билингвальные поля в SELECT:**

```php
SELECT 
    s.item_id,
    s.headline,
    s.summary,
    s.article_language,
    s.category_primary,
    s.dedup_canonical_entities,
    s.dedup_core_event,
    s.dedup_numeric_facts,
    -- ✅ НОВЫЕ ПОЛЯ
    s.dedup_canonical_entities_en,
    s.dedup_core_event_en,
    s.keywords_en,
    i.pub_date
FROM rss2tlg_summarization s
...
```

### 2. Добавить метод `calculatePreliminarySimilarity()`

```php
/**
 * Вычисляет предварительную схожесть между двумя новостями
 *
 * @param array<string, mixed> $newItem Новая новость
 * @param array<string, mixed> $existingItem Существующая новость
 * @return float Схожесть 0-100
 */
private function calculatePreliminarySimilarity(
    array $newItem,
    array $existingItem
): float {
    $scores = [];
    
    // 1. Entities similarity (40%)
    $newEntities = json_decode($newItem['dedup_canonical_entities_en'] ?? '[]', true) ?: [];
    $existEntities = json_decode($existingItem['dedup_canonical_entities_en'] ?? '[]', true) ?: [];
    $scores['entities'] = $this->jaccardSimilarity($newEntities, $existEntities) * 40.0;
    
    // 2. Event similarity (30%)
    $newEvent = $newItem['dedup_core_event_en'] ?? '';
    $existEvent = $existingItem['dedup_core_event_en'] ?? '';
    $scores['event'] = $this->cosineSimilarity($newEvent, $existEvent) * 30.0;
    
    // 3. Keywords similarity (30%)
    $newKeywords = json_decode($newItem['keywords_en'] ?? '[]', true) ?: [];
    $existKeywords = json_decode($existingItem['keywords_en'] ?? '[]', true) ?: [];
    $scores['keywords'] = $this->jaccardSimilarity($newKeywords, $existKeywords) * 30.0;
    
    return array_sum($scores);
}
```

### 3. Добавить вспомогательные методы

```php
/**
 * Вычисляет Jaccard similarity для двух массивов
 *
 * @param array<string> $arr1
 * @param array<string> $arr2
 * @return float 0-1
 */
private function jaccardSimilarity(array $arr1, array $arr2): float
{
    if (empty($arr1) && empty($arr2)) {
        return 1.0; // оба пустые = идентичны
    }
    
    if (empty($arr1) || empty($arr2)) {
        return 0.0; // один пустой = разные
    }
    
    $arr1 = array_map('mb_strtolower', $arr1);
    $arr2 = array_map('mb_strtolower', $arr2);
    
    $intersection = count(array_intersect($arr1, $arr2));
    $union = count(array_unique(array_merge($arr1, $arr2)));
    
    return $union > 0 ? ($intersection / $union) : 0.0;
}

/**
 * Вычисляет Cosine similarity для двух текстов
 *
 * @param string $text1
 * @param string $text2
 * @return float 0-1
 */
private function cosineSimilarity(string $text1, string $text2): float
{
    if (empty($text1) && empty($text2)) {
        return 1.0;
    }
    
    if (empty($text1) || empty($text2)) {
        return 0.0;
    }
    
    // Bag of words
    $words1 = $this->tokenize($text1);
    $words2 = $this->tokenize($text2);
    
    if (empty($words1) || empty($words2)) {
        return 0.0;
    }
    
    // Frequency vectors
    $freq1 = array_count_values($words1);
    $freq2 = array_count_values($words2);
    
    $allWords = array_unique(array_merge(array_keys($freq1), array_keys($freq2)));
    
    $dotProduct = 0.0;
    $magnitude1 = 0.0;
    $magnitude2 = 0.0;
    
    foreach ($allWords as $word) {
        $f1 = $freq1[$word] ?? 0;
        $f2 = $freq2[$word] ?? 0;
        
        $dotProduct += $f1 * $f2;
        $magnitude1 += $f1 * $f1;
        $magnitude2 += $f2 * $f2;
    }
    
    $magnitude1 = sqrt($magnitude1);
    $magnitude2 = sqrt($magnitude2);
    
    if ($magnitude1 == 0 || $magnitude2 == 0) {
        return 0.0;
    }
    
    return $dotProduct / ($magnitude1 * $magnitude2);
}

/**
 * Токенизирует текст в слова
 *
 * @param string $text
 * @return array<string>
 */
private function tokenize(string $text): array
{
    $text = mb_strtolower($text);
    $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
    $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    
    return $words ?: [];
}
```

### 4. Обновить `analyzeDeduplication()`

**Добавить фильтрацию перед AI:**

```php
private function analyzeDeduplication(int $itemId, array $itemData, array $similarItems): ?array
{
    // ✅ НОВЫЙ КОД - Предварительная фильтрация
    $preliminaryThreshold = $this->config['preliminary_similarity_threshold'];
    $maxAIComparisons = $this->config['max_ai_comparisons'];
    
    $scoredItems = [];
    foreach ($similarItems as $item) {
        $similarity = $this->calculatePreliminarySimilarity($itemData, $item);
        
        $this->incrementMetric('preliminary_checks');
        
        if ($similarity >= $preliminaryThreshold) {
            $scoredItems[] = [
                'item' => $item,
                'preliminary_score' => $similarity,
            ];
        } else {
            $this->incrementMetric('preliminary_filtered');
            $this->logDebug('Пропущен по preliminary similarity', [
                'item_id' => $itemId,
                'compared_with' => $item['item_id'],
                'similarity' => round($similarity, 2),
                'threshold' => $preliminaryThreshold,
            ]);
        }
    }
    
    // Если все отфильтровались - точно не дубликат
    if (empty($scoredItems)) {
        $this->incrementMetric('ai_skipped');
        $this->logInfo('Все новости отфильтрованы preliminary check - уникальна', [
            'item_id' => $itemId,
            'checked' => count($similarItems),
        ]);
        
        return [
            'is_duplicate' => false,
            'can_be_published' => true,
            'similarity_score' => 0.0,
            'similarity_method' => 'preliminary',
            'items_compared' => count($similarItems),
            'model_used' => null,
            'tokens_used' => 0,
        ];
    }
    
    // Сортируем по убыванию схожести
    usort($scoredItems, function($a, $b) {
        return $b['preliminary_score'] <=> $a['preliminary_score'];
    });
    
    // Берем топ-N для AI
    $topItems = array_slice($scoredItems, 0, $maxAIComparisons);
    $itemsForAI = array_map(fn($x) => $x['item'], $topItems);
    
    $this->logInfo('Отобрано для AI анализа после preliminary filter', [
        'item_id' => $itemId,
        'total_similar' => count($similarItems),
        'passed_filter' => count($scoredItems),
        'sent_to_ai' => count($itemsForAI),
        'top_score' => round($scoredItems[0]['preliminary_score'], 2),
    ]);
    
    // ✅ ДАЛЬШЕ СТАРЫЙ КОД - AI анализ
    $systemPrompt = $this->loadPromptFromFile($this->config['prompt_file']);
    $userPrompt = $this->prepareComparisonPrompt($itemData, $itemsForAI);
    
    // ... остальной код без изменений
}
```

### 5. Добавить метрики

**В `initializeMetrics()`:**

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
        // ✅ НОВЫЕ МЕТРИКИ
        'preliminary_checks' => 0,      // сколько сделано preliminary проверок
        'preliminary_filtered' => 0,    // сколько отфильтровано (< threshold)
        'ai_skipped' => 0,              // сколько новостей не отправлено в AI
        'model_attempts' => [],
    ];
}
```

### 6. Обновить конфигурацию

**Переименовать для ясности:**

```json
{
    "enabled": true,
    "models": ["google/gemma-3-27b-it", "deepseek/deepseek-chat"],
    "prompt_file": "production/prompts/deduplication_prompt_v2.txt",
    "fallback_strategy": "sequential",
    "retry_count": 2,
    "timeout": 120,
    
    "compare_last_n_days": 7,          // период выборки из БД
    "max_preliminary_comparisons": 50, // максимум новостей из БД для preliminary check
    "preliminary_similarity_threshold": 60, // порог для фильтрации (0-100)
    "max_ai_comparisons": 10,          // максимум новостей для AI анализа
    "similarity_threshold": 70         // порог дубликата от AI (0-100)
}
```

---

## 📊 Ожидаемые результаты

### До оптимизации

- Получено из БД: **50 новостей**
- Отправлено в AI: **50 новостей**
- Токенов: ~10,000
- Время: ~60 сек
- Стоимость: ~$0.02

### После оптимизации

- Получено из БД: **50 новостей**
- Preliminary check: **50 проверок** (быстро, <1 сек)
- Отфильтровано: **~35-40 новостей** (similarity < 60%)
- Отправлено в AI: **10-15 новостей** (топ по similarity)
- Токенов: ~2,000 (**↓80%**)
- Время: ~15 сек (**↓75%**)
- Стоимость: ~$0.004 (**↓80%**)

### Качество

- **Точность не снизится** - все подозрительные новости (similarity >= 60%) все равно проверяются AI
- **Ложных негативов нет** - не пропускаем дубликаты
- **Скорость выше** - быстрая фильтрация явно непохожих новостей

---

## ✅ Чеклист реализации

- [ ] Обновить `getSimilarItems()` - добавить билингвальные поля
- [ ] Добавить `calculatePreliminarySimilarity()`
- [ ] Добавить `jaccardSimilarity()`
- [ ] Добавить `cosineSimilarity()`
- [ ] Добавить `tokenize()`
- [ ] Обновить `analyzeDeduplication()` - интегрировать фильтрацию
- [ ] Обновить `initializeMetrics()` - добавить новые метрики
- [ ] Обновить конфигурацию - переименовать поля
- [ ] Добавить логирование предварительных проверок
- [ ] Протестировать на реальных данных
- [ ] Обновить документацию

---

## 🧪 План тестирования

1. **Запустить на 10 новостях** - проверить корректность работы
2. **Сравнить метрики** - до/после оптимизации
3. **Проверить качество** - нет ли ложных негативов
4. **Замерить производительность** - время, токены, стоимость
5. **Telegram уведомления** - отправлять прогресс теста

---

**Автор:** AI Assistant  
**Дата создания:** 2025-01-XX

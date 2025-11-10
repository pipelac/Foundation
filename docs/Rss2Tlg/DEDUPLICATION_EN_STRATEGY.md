# 🎯 Стратегия дедупликации с английскими метаданными

**Версия:** DeduplicationService v2.1 (Планируется)  
**Дата:** 2025-11-10  
**Статус:** 📝 Design Document

---

## 📋 Концепция

С внедрением билингвальных метаданных в SummarizationService v2.1, DeduplicationService получает возможность:

1. **Быстрая фильтрация** без AI (экономия токенов)
2. **Кроссязычное сравнение** (RU ↔ EN ↔ DE и т.д.)
3. **Улучшенная точность** (стандартизированные данные)

---

## 🔄 Двухэтапная дедупликация

### Этап 1: Быстрое сравнение (БЕЗ AI)

**Цель:** Отсеять очевидно разные новости без затрат токенов.

**Алгоритм:**

```php
function quickCompare(int $itemId1, int $itemId2): float
{
    // Получить метаданные из БД
    $item1 = getItemMetadata($itemId1);
    $item2 = getItemMetadata($itemId2);
    
    // 1. Сравнить категории (должны совпадать)
    if ($item1['category_primary_en'] !== $item2['category_primary_en']) {
        return 0.0; // Разные категории → точно не дубликат
    }
    
    // 2. Сравнить сущности (Jaccard similarity)
    $entities1 = json_decode($item1['dedup_canonical_entities_en'], true);
    $entities2 = json_decode($item2['dedup_canonical_entities_en'], true);
    $entitiesSimilarity = jaccardSimilarity($entities1, $entities2);
    
    // 3. Сравнить ключевые слова (Jaccard similarity)
    $keywords1 = json_decode($item1['keywords_en'], true);
    $keywords2 = json_decode($item2['keywords_en'], true);
    $keywordsSimilarity = jaccardSimilarity($keywords1, $keywords2);
    
    // 4. Взвешенная схожесть
    $totalSimilarity = ($entitiesSimilarity * 0.7) + ($keywordsSimilarity * 0.3);
    
    return $totalSimilarity;
}
```

**Решение:**
```php
$similarity = quickCompare($newItemId, $existingItemId);

if ($similarity < 0.30) {
    // Схожесть < 30% → точно не дубликат
    return ['is_duplicate' => false, 'method' => 'quick_filter'];
}

if ($similarity > 0.70) {
    // Схожесть > 70% → вероятный дубликат, отправить в AI для подтверждения
    return deepCompare($newItemId, $existingItemId);
}

// Схожесть 30-70% → граничный случай, отправить в AI
return deepCompare($newItemId, $existingItemId);
```

---

### Этап 2: Глубокое сравнение (С AI)

**Цель:** Точное определение дубликатов для граничных случаев (30-70%).

**Промпт для AI:**

```
You are a professional news deduplication expert. Compare two news articles and determine if they are duplicates.

# Article 1
Language: {article_language_1}
Category: {category_primary_en_1}
Entities: {dedup_canonical_entities_en_1}
Core Event: {dedup_core_event_en_1}
Numeric Facts: {dedup_numeric_facts_1}

# Article 2
Language: {article_language_2}
Category: {category_primary_en_2}
Entities: {dedup_canonical_entities_en_2}
Core Event: {dedup_core_event_en_2}
Numeric Facts: {dedup_numeric_facts_2}

Compare these articles and return:
{
  "is_duplicate": true/false,
  "similarity_score": 0-100,
  "reasoning": "Brief explanation"
}
```

**Особенности:**
- ✅ Все поля на **английском** (универсально для всех языков)
- ✅ AI получает **структурированные данные** (не полный текст)
- ✅ Экономия токенов: ~500-1000 токенов вместо 5000-10000

---

## 📊 Метрики эффективности

### Сравнение подходов:

| Метрика                  | БЕЗ быстрого фильтра | С быстрым фильтром |
|--------------------------|----------------------|--------------------|
| Запросов к AI            | 100%                 | 30-40%             |
| Стоимость                | 100%                 | 30-40%             |
| Скорость обработки       | 100%                 | 200-300%           |
| Экономия токенов         | 0%                   | 60-70%             |
| Кроссязычная дедупликация| ❌ Не работает       | ✅ Работает        |

---

## 🧮 Алгоритмы сходства

### Jaccard Similarity

**Формула:**
```
Jaccard(A, B) = |A ∩ B| / |A ∪ B|
```

**Реализация:**
```php
function jaccardSimilarity(array $set1, array $set2): float
{
    if (empty($set1) && empty($set2)) {
        return 1.0;
    }
    
    if (empty($set1) || empty($set2)) {
        return 0.0;
    }
    
    $set1 = array_map('strtolower', $set1);
    $set2 = array_map('strtolower', $set2);
    
    $intersection = count(array_intersect($set1, $set2));
    $union = count(array_unique(array_merge($set1, $set2)));
    
    return $union > 0 ? $intersection / $union : 0.0;
}
```

**Примеры:**
```php
// Пример 1: Полное совпадение
$entities1 = ["Elon Musk", "Tesla", "China"];
$entities2 = ["Elon Musk", "Tesla", "China"];
jaccardSimilarity($entities1, $entities2); // 1.0 (100%)

// Пример 2: Частичное совпадение
$entities1 = ["Elon Musk", "Tesla", "SpaceX"];
$entities2 = ["Elon Musk", "Tesla", "China"];
jaccardSimilarity($entities1, $entities2); // 0.5 (50%)

// Пример 3: Нет совпадений
$entities1 = ["Donald Trump", "USA"];
$entities2 = ["Elon Musk", "Tesla"];
jaccardSimilarity($entities1, $entities2); // 0.0 (0%)
```

---

## 🎯 Пороговые значения

### Рекомендованные thresholds:

| Диапазон схожести | Решение                          | Метод                |
|-------------------|----------------------------------|----------------------|
| 0% - 30%          | Точно НЕ дубликат                | Быстрый фильтр (✅)  |
| 30% - 70%         | Граничный случай → отправить в AI| AI анализ (🤖)       |
| 70% - 100%        | Вероятный дубликат → проверить AI| AI подтверждение (🤖)|

**Примечание:** Пороги можно настроить в конфигурации:
```json
{
  "quick_filter_threshold_low": 0.30,
  "quick_filter_threshold_high": 0.70,
  "entity_weight": 0.7,
  "keyword_weight": 0.3
}
```

---

## 🌍 Кроссязычные примеры

### Пример 1: Русская + Английская новость (дубликат)

**Русская новость (item_id: 100):**
```json
{
  "article_language": "ru",
  "category_primary_en": "technology",
  "dedup_canonical_entities_en": ["Elon Musk", "Tesla", "China"],
  "keywords_en": ["elon musk", "tesla", "electric vehicle", "china"],
  "dedup_core_event_en": "Tesla announced a new $25,000 electric vehicle for the Chinese market"
}
```

**Английская новость (item_id: 105):**
```json
{
  "article_language": "en",
  "category_primary_en": "technology",
  "dedup_canonical_entities_en": ["Elon Musk", "Tesla", "China"],
  "keywords_en": ["elon musk", "tesla", "electric vehicle", "china"],
  "dedup_core_event_en": "Tesla launched a new $25K EV in China"
}
```

**Быстрое сравнение:**
```php
// Категории совпадают: ✅
category_primary_en: "technology" vs "technology"

// Сущности (Jaccard):
entities1: ["Elon Musk", "Tesla", "China"]
entities2: ["Elon Musk", "Tesla", "China"]
similarity: 1.0 (100%)

// Ключевые слова (Jaccard):
keywords1: ["elon musk", "tesla", "electric vehicle", "china"]
keywords2: ["elon musk", "tesla", "electric vehicle", "china"]
similarity: 1.0 (100%)

// Общая схожесть: (1.0 * 0.7) + (1.0 * 0.3) = 1.0 (100%)
```

**Решение:**
- Схожесть 100% → вероятный дубликат
- Отправить в AI для подтверждения
- AI подтверждает: дубликат ✅

---

### Пример 2: Разные новости (не дубликат)

**Новость 1:**
```json
{
  "category_primary_en": "technology",
  "dedup_canonical_entities_en": ["Elon Musk", "Tesla", "China"],
  "keywords_en": ["elon musk", "tesla", "electric vehicle"]
}
```

**Новость 2:**
```json
{
  "category_primary_en": "politics",
  "dedup_canonical_entities_en": ["Joe Biden", "USA", "Congress"],
  "keywords_en": ["joe biden", "politics", "congress"]
}
```

**Быстрое сравнение:**
```php
// Категории НЕ совпадают: ❌
category_primary_en: "technology" vs "politics"

// Решение: точно НЕ дубликат (без AI)
```

---

## 💻 Псевдокод DeduplicationService v2.1

```php
class DeduplicationService
{
    public function processItem(int $itemId): bool
    {
        // Получить метаданные новой новости
        $newItem = $this->getItemMetadata($itemId);
        
        // Получить существующие новости за последние N часов
        $existingItems = $this->getRecentItems($this->config['lookback_hours']);
        
        foreach ($existingItems as $existingItem) {
            // Этап 1: Быстрое сравнение
            $quickSimilarity = $this->quickCompare($newItem, $existingItem);
            
            if ($quickSimilarity < 0.30) {
                // Схожесть < 30% → точно не дубликат, пропустить
                $this->logDebug('Quick filter: not duplicate', [
                    'similarity' => $quickSimilarity,
                    'method' => 'quick_filter'
                ]);
                continue;
            }
            
            // Схожесть >= 30% → отправить в AI для глубокого анализа
            $this->logInfo('Quick filter: possible duplicate, sending to AI', [
                'similarity' => $quickSimilarity
            ]);
            
            // Этап 2: Глубокое сравнение с AI
            $aiResult = $this->deepCompareWithAI($newItem, $existingItem);
            
            if ($aiResult['is_duplicate']) {
                // Найден дубликат
                $this->saveDuplicateResult($itemId, $existingItem['item_id'], $aiResult);
                return true;
            }
        }
        
        // Дубликатов не найдено
        $this->saveUniqueResult($itemId);
        return true;
    }
    
    private function quickCompare(array $item1, array $item2): float
    {
        // Сравнить категории
        if ($item1['category_primary_en'] !== $item2['category_primary_en']) {
            return 0.0;
        }
        
        // Сравнить сущности (Jaccard)
        $entities1 = json_decode($item1['dedup_canonical_entities_en'], true);
        $entities2 = json_decode($item2['dedup_canonical_entities_en'], true);
        $entitySimilarity = $this->jaccardSimilarity($entities1, $entities2);
        
        // Сравнить ключевые слова (Jaccard)
        $keywords1 = json_decode($item1['keywords_en'], true);
        $keywords2 = json_decode($item2['keywords_en'], true);
        $keywordSimilarity = $this->jaccardSimilarity($keywords1, $keywords2);
        
        // Взвешенная схожесть
        return ($entitySimilarity * 0.7) + ($keywordSimilarity * 0.3);
    }
    
    private function deepCompareWithAI(array $item1, array $item2): array
    {
        // Подготовить промпт с английскими метаданными
        $prompt = $this->prepareComparisonPrompt($item1, $item2);
        
        // Отправить в AI
        $result = $this->analyzeWithFallback($systemPrompt, $prompt);
        
        return $result;
    }
}
```

---

## 📈 Ожидаемые результаты

### Метрики до/после:

**БЕЗ билингвальных метаданных:**
- Обработано новостей: 100
- Отправлено в AI: 100 (100%)
- Стоимость: ~$5.00
- Время: ~300 сек
- Кроссязычная дедупликация: ❌ не работает

**С билингвальными метаданными:**
- Обработано новостей: 100
- Быстрый фильтр (< 30%): 60 (60%) ✅ без AI
- Отправлено в AI: 40 (40%)
- Стоимость: ~$2.00 (экономия 60%)
- Время: ~150 сек (ускорение 2x)
- Кроссязычная дедупликация: ✅ работает

---

## ✅ Следующие шаги

1. **Реализовать `quickCompare()` метод**
   - Jaccard similarity для сущностей и ключевых слов
   - Настраиваемые веса и пороги

2. **Обновить `prepareComparisonPrompt()`**
   - Использовать `*_en` поля вместо оригинальных
   - Сократить промпт (только ключевые поля)

3. **Добавить метрики:**
   - Количество отфильтрованных новостей (без AI)
   - Количество отправленных в AI
   - Экономия токенов

4. **Тестирование:**
   - Кроссязычная дедупликация (RU ↔ EN)
   - Точность quick_filter (false positives/negatives)
   - Измерение производительности

---

## 🎯 Ключевые преимущества

1. **Экономия 60-70% токенов** - большинство новостей отсеиваются без AI
2. **Ускорение в 2-3 раза** - быстрое сравнение занимает миллисекунды
3. **Кроссязычная поддержка** - работает для любых комбинаций языков
4. **Высокая точность** - AI используется только для граничных случаев
5. **Масштабируемость** - легко добавить новые языки

---

**Готово к реализации!** 🚀

---

**Дата:** 2025-11-10  
**Версия:** DeduplicationService v2.1 (Планируется)  
**Связанные документы:**
- `REFACTORING_BILINGUAL_METADATA.md`
- `REFACTORING_SUMMARY.md`
- `QUICKSTART_BILINGUAL_REFACTORING.md`

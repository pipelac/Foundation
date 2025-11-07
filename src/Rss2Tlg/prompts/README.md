# AI Prompts для анализа новостей

Директория содержит XML-промпты для AI-анализа новостных статей через OpenRouter API.

## Структура промптов

### INoT_v1.xml ⭐ PRODUCTION-READY
**INoT NEWS ANALYZER v1** — современный промпт для глубокого анализа новостей.

**Возможности:**
- ✅ **Шкала важности 1-20** (детальная калибровка от спама до событий мирового масштаба)
- ✅ **Дедупликация новостей** (структурированные данные для сравнения схожих статей)
- ✅ **Кеширование промптов** (оптимизация для DeepSeek/Qwen с разделением system/user)
- ✅ **Перевод с валидацией** (автоматический перевод иноязычных новостей на русский)
- ✅ **Multi-agent анализ** (4 внутренних агента: Translator, Validator_Accuracy, Validator_Russian, Analyzer)
- ✅ **20-уровневая шкала важности** с детальной методологией оценки

**Выходной формат JSON:**
```json
{
  "analysis_status": "completed|rejected|translation_issues",
  "article_language": "en|ru|...",
  "translation_status": "translated|original|failed",
  "translation_quality": {
    "overall_score": 1-10,
    "issues": "string or null"
  },
  "category": {
    "primary": "category_name",
    "confidence": "high|medium|low",
    "secondary": ["cat2", "cat3"]
  },
  "content": {
    "headline": "Russian, max 80 chars",
    "summary": "Russian, 3-10 sentences, max 500 chars",
    "keywords": ["tag1", "tag2", "tag3", "tag4", "tag5"]
  },
  "importance": {
    "rating": 1-20,
    "justification": "1-2 sentence explanation based on impact scope/novelty/cascades"
  },
  "deduplication": {
    "canonical_entities": ["Entity1", "Entity2", "Entity3"],
    "core_event": "Short description of what actually happened",
    "numeric_facts": ["number1", "date1", "metric1"],
    "semantic_fingerprint": "List of 5-7 key concepts capturing essence",
    "impact_vector": {
      "scope": "personal|local|industry|global",
      "severity": 1-10,
      "urgency": "low|medium|high|critical",
      "affected_stakeholders_count": "tens|thousands|millions|billions"
    }
  },
  "quality_flags": {
    "direct_speech_preserved": true|false,
    "numeric_data_intact": true|false,
    "translation_issues_exist": true|false,
    "requires_source_link": true|false,
    "potential_sensitivity": true|false
  }
}
```

**Использование:**
```json
{
  "feeds": [
    {
      "id": 1,
      "url": "https://example.com/rss",
      "language": "ru",
      "prompt_id": "INoT_v1"
    }
  ]
}
```

### news_analysis_ru.xml
Промпт для анализа русскоязычных новостей (legacy).

**Функции:**
- Определение категории новости
- Генерация короткого описания
- Извлечение ключевых слов

### news_analysis_en.xml
Промпт для анализа англоязычных новостей (legacy).

**Функции:**
- Анализ новости на английском
- Перевод на русский
- Категоризация и извлечение ключевых данных

## Конфигурация

Промпты подключаются через конфигурацию RSS-лент в `config/rss2tlg_e2e_v4_test.json`:

```json
{
  "feeds": [
    {
      "id": 1,
      "name": "example_feed",
      "url": "https://example.com/rss",
      "language": "ru",
      "prompt_id": "INoT_v1"
    }
  ]
}
```

## Управление промптами

Промпты загружаются через `PromptManager`:

```php
use App\Rss2Tlg\PromptManager;

$promptManager = new PromptManager(__DIR__ . '/prompts', $logger);

// Загрузка системного промпта
$systemPrompt = $promptManager->getSystemPrompt('INoT_v1');

// Формирование динамического запроса
$userMessage = $promptManager->buildUserMessage(
    $articleTitle,
    $articleText,
    $articleLanguage
);
```

## Разработка новых промптов

1. Создайте XML-файл в этой директории
2. Используйте понятное имя файла (например, `my_prompt_v1.xml`)
3. Следуйте структуре существующих промптов
4. Добавьте `prompt_id` в конфигурацию ленты
5. Протестируйте через E2E тест

## Кеширование

OpenRouter поддерживает кеширование промптов для моделей DeepSeek и Qwen.  
**Важно:** Разделяйте статическую часть (system prompt) и динамическую (user message).

**Статическая часть (кешируется):**
- Инструкции по анализу
- Список категорий
- Шкала важности
- Правила валидации

**Динамическая часть (не кешируется):**
- Заголовок новости
- Текст статьи
- Язык источника

## Метрики и отладка

При анализе новости через `AIAnalysisService` сохраняются метрики:
- Количество токенов (prompt + completion)
- Время обработки
- Использованная модель
- Статус кеширования

Метрики доступны через `getLastApiMetrics()` после вызова `analyze()` или `analyzeWithFallback()`.

## См. также
- [AIAnalysisService.php](../AIAnalysisService.php) - Сервис AI-анализа
- [PromptManager.php](../PromptManager.php) - Менеджер промптов
- [AIAnalysisRepository.php](../AIAnalysisRepository.php) - Хранение результатов
- [tests_rss2tlg_e2e_v4.php](../tests/tests_rss2tlg_e2e_v4.php) - E2E тестирование

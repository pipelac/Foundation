# AI Analysis Module для Rss2Tlg

**Модуль для автоматического AI-анализа новостей через OpenRouter API (DeepSeek, Qwen, и другие LLM модели).**

## Описание

AI Analysis Module — это компонент системы Rss2Tlg, который обрабатывает новости через большие языковые модели (LLM) для:

- ✅ **Категоризация** — автоматическое определение категории новости (Technology, Business, Finance, и т.д.)
- ✅ **Рейтинг важности** — оценка по шкале 1-20 на основе влияния и охвата
- ✅ **Перевод и валидация** — автоматический перевод с английского на русский с проверкой качества
- ✅ **Извлечение ключевых данных** — заголовок, краткое содержание, ключевые слова
- ✅ **Дедупликация** — структурированные данные для обнаружения дубликатов
- ✅ **Кеширование** — оптимизация затрат через кеширование системных промптов (DeepSeek/Qwen)

## Архитектура

```
┌──────────────────┐
│ RSS Items        │
│ (rss2tlg_items)  │
└────────┬─────────┘
         │
         ▼
┌──────────────────────────────────┐
│   AIAnalysisService              │
│   1. Load Item                   │
│   2. Load Prompt by ID           │
│   3. Call OpenRouter API         │
│   4. Parse JSON Response         │
│   5. Save to DB                  │
└──────────────────────────────────┘
         │
         ├──► PromptManager (loads prompts/*.xml)
         ├──► OpenRouter.class.php (API calls)
         └──► AIAnalysisRepository (saves to DB)
                        │
                        ▼
         ┌────────────────────────────┐
         │ rss2tlg_ai_analysis        │
         │ - category, importance     │
         │ - translation, summary     │
         │ - deduplication_data       │
         └────────────────────────────┘
```

## Компоненты

### 1. AIAnalysisService.php

Основной сервис для анализа новостей.

**Методы:**
- `analyze(array $item, string $promptId, string $model)` — Анализирует одну новость
- `analyzeBatch(array $items, string $promptId, string $model)` — Пакетный анализ
- `getMetrics()` — Получает метрики работы сервиса

**Пример:**
```php
$analysis = $analysisService->analyze($item, 'INoT_v1', 'deepseek/deepseek-chat');
```

### 2. PromptManager.php

Управляет загрузкой и кешированием промптов.

**Методы:**
- `getSystemPrompt(string $promptId)` — Загружает системный промпт из файла
- `buildUserMessage(string $title, string $text, string $lang)` — Формирует динамический запрос
- `getAvailablePrompts()` — Возвращает список доступных промптов
- `hasPrompt(string $promptId)` — Проверяет существование промпта

**Структура файлов промптов:**
```
prompts/
  ├── INoT_v1.xml          # Базовый промпт для анализа новостей
  ├── Financial_v1.xml     # Промпт для финансовых новостей (пример)
  └── Tech_v1.xml          # Промпт для технологических новостей (пример)
```

### 3. AIAnalysisRepository.php

Репозиторий для работы с таблицей `rss2tlg_ai_analysis`.

**Методы:**
- `save(...)` — Сохраняет результат анализа
- `saveError(...)` — Сохраняет ошибку анализа
- `getByItemId(int $itemId)` — Получает анализ по ID новости
- `getPendingItems(int $feedId, int $limit)` — Получает новости без анализа
- `getByImportance(int $minRating, int $limit)` — Получает важные новости
- `getStats()` — Статистика по анализам

## Таблица БД: rss2tlg_ai_analysis

Схема создается автоматически при первом запуске. Файл: `src/Rss2Tlg/schema_ai_analysis.sql`

**Основные поля:**
- `item_id` — ID новости (FK → rss2tlg_items.id)
- `feed_id` — ID источника RSS
- `prompt_id` — ID использованного промпта
- `analysis_status` — pending/processing/success/failed
- `analysis_data` — полный JSON ответ от AI
- `category_primary` — основная категория
- `importance_rating` — рейтинг важности (1-20)
- `content_headline` — заголовок на русском
- `content_summary` — краткое содержание
- `deduplication_data` — данные для дедупликации (JSON)
- `model_used` — название модели AI
- `tokens_used` — количество использованных токенов
- `processing_time_ms` — время обработки в мс

## Настройка

### 1. Конфигурация источников RSS

В файле `config/rss2tlg_stress_test.json` добавьте поле `prompt_id` для каждого источника:

```json
{
  "feeds": [
    {
      "id": 1,
      "url": "https://example.com/rss",
      "title": "Example News",
      "prompt_id": "INoT_v1",
      ...
    }
  ]
}
```

### 2. Конфигурация OpenRouter

Файл `config/openrouter.json`:

```json
{
  "api_key": "sk-or-v1-...",
  "app_name": "Rss2Tlg",
  "timeout": 60
}
```

### 3. Создание промптов

Создайте файл `prompts/INoT_v1.xml` с системным промптом (см. пример в репозитории).

Промпт должен содержать:
- Роль и задачу анализатора
- Список категорий
- Шкалу важности (1-20)
- Схему выходных данных (JSON)
- Логику обработки

## Использование

### Простой пример

```php
use App\Rss2Tlg\AIAnalysisService;
use App\Rss2Tlg\AIAnalysisRepository;
use App\Rss2Tlg\PromptManager;
use App\Component\OpenRouter;

// Инициализация
$promptManager = new PromptManager(__DIR__ . '/prompts', $logger);
$analysisRepository = new AIAnalysisRepository($db, $logger);
$openRouter = new OpenRouter($openRouterConfig, $logger);

$analysisService = new AIAnalysisService(
    $promptManager,
    $analysisRepository,
    $openRouter,
    $logger
);

// Анализ одной новости
$item = $itemRepository->getByItemId(123);
$analysis = $analysisService->analyze($item, 'INoT_v1', 'deepseek/deepseek-chat');

if ($analysis !== null) {
    echo "Категория: {$analysis['category_primary']}\n";
    echo "Важность: {$analysis['importance_rating']}/20\n";
    echo "Заголовок: {$analysis['content_headline']}\n";
}
```

### Пакетный анализ

```php
// Получаем новости без анализа
$pendingItems = $analysisRepository->getPendingItems(0, 10);

// Анализируем пакетом
$results = $analysisService->analyzeBatch($pendingItems, 'INoT_v1', 'deepseek/deepseek-chat');

echo "Успешно: {$results['successful']}\n";
echo "Ошибок: {$results['failed']}\n";
```

### Получение важных новостей

```php
// Новости с рейтингом >= 12
$importantNews = $analysisRepository->getByImportance(12, 20);

foreach ($importantNews as $news) {
    echo "[{$news['importance_rating']}/20] {$news['content_headline']}\n";
}
```

## Кеширование промптов

Модуль оптимизирован для кеширования системных промптов на стороне API (DeepSeek, Qwen).

**Разделение запроса:**
1. **System Message** (кешируется на 5-10 минут) — весь XML промпт (~1000 токенов)
2. **User Message** (НЕ кешируется) — только title, text, language (~200 токенов)

**Экономия токенов:**
- Без кеша: 1200 токенов × 100 новостей = 120,000 токенов
- С кешем: 1200 + (200 × 99) = 21,000 токенов
- **Экономия: 82.5%**

**Стоимость (DeepSeek $0.5 per 1M tokens):**
- Без кеша: $0.06
- С кешем: $0.01
- **Экономия: $50 на 1000 новостей**

## Модели AI

Поддерживаются все модели OpenRouter:

### Рекомендуемые модели

**DeepSeek Chat** (оптимально по цене/качеству)
```php
$model = 'deepseek/deepseek-chat';
```
- Стоимость: $0.14 / 1M input tokens, $0.28 / 1M output tokens
- Качество: отличное для анализа новостей
- Кеширование: 5-10 минут

**Qwen 2.5 72B**
```php
$model = 'qwen/qwen-2.5-72b-instruct';
```
- Стоимость: $0.35 / 1M tokens
- Качество: очень хорошее
- Кеширование: 5-10 минут

**GPT-4 Turbo** (дорого, но максимальное качество)
```php
$model = 'openai/gpt-4-turbo';
```
- Стоимость: $10 / 1M input tokens
- Качество: лучшее
- Кеширование: не поддерживается

## Формат выходных данных

```json
{
  "analysis_status": "completed",
  "article_language": "en",
  "translation_status": "translated",
  "translation_quality": {
    "overall_score": 9,
    "issues": null
  },
  "category": {
    "primary": "Technology",
    "confidence": "high",
    "secondary": ["Business", "Investment"]
  },
  "content": {
    "headline": "OpenAI запустила GPT-5 с расширенным рассуждением",
    "summary": "OpenAI анонсировала выпуск модели GPT-5...",
    "keywords": ["OpenAI", "GPT-5", "AI", "API", "LLM"]
  },
  "importance": {
    "rating": 12,
    "justification": "Революционный продукт в нише ИИ..."
  },
  "deduplication": {
    "canonical_entities": ["OpenAI", "GPT-5", "API"],
    "core_event": "OpenAI released GPT-5 model...",
    "numeric_facts": ["$0.10 per 1K tokens", "December 15"],
    "semantic_fingerprint": ["product_launch", "AI_model", ...],
    "impact_vector": {
      "scope": "global",
      "severity": 9,
      "urgency": "high",
      "affected_stakeholders_count": "millions"
    }
  },
  "quality_flags": {
    "direct_speech_preserved": false,
    "numeric_data_intact": true,
    "translation_issues_exist": false,
    "requires_source_link": true,
    "potential_sensitivity": false
  }
}
```

## Дедупликация новостей

Модуль предоставляет структурированные данные для обнаружения дубликатов из разных источников.

**Пример сравнения:**
```php
// Статья A уже проанализирована
$analysisA = $analysisRepository->getByItemId(123);
$dedupDataA = json_decode($analysisA['deduplication_data'], true);

// Новая статья B
$articleB = ['title' => '...', 'text' => '...'];

// Сравнение через LLM
$comparisonPrompt = sprintf(
    'Article A: %s
    
    Article B: %s
    
    Are these articles about the same event? Answer: duplicate|related|unrelated',
    json_encode($dedupDataA),
    json_encode($articleB)
);

$result = $openRouter->text2text('deepseek/deepseek-chat', $comparisonPrompt);
// Result: "duplicate" | "related" | "unrelated"
```

## Метрики и мониторинг

### Метрики сервиса

```php
$metrics = $analysisService->getMetrics();
// [
//     'total_analyzed' => 100,
//     'successful' => 98,
//     'failed' => 2,
//     'total_tokens' => 25000,
//     'total_time_ms' => 45000,
//     'cache_hits' => 85,
// ]
```

### Статистика репозитория

```php
$stats = $analysisRepository->getStats();
// [
//     'total' => 100,
//     'success' => 98,
//     'failed' => 2,
//     'avg_importance' => 7.5,
//     'avg_processing_time_ms' => 450,
//     'cache_hits' => 85,
//     'total_tokens' => 25000,
// ]
```

### SQL запросы для мониторинга

```sql
-- Статистика по категориям
SELECT 
    category_primary,
    COUNT(*) as count,
    AVG(importance_rating) as avg_importance
FROM rss2tlg_ai_analysis
WHERE analysis_status = 'success'
GROUP BY category_primary
ORDER BY count DESC;

-- Самые важные новости за последний день
SELECT 
    content_headline,
    importance_rating,
    category_primary,
    analyzed_at
FROM rss2tlg_ai_analysis
WHERE analysis_status = 'success'
AND analyzed_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
ORDER BY importance_rating DESC
LIMIT 10;

-- Статистика по источникам
SELECT 
    feed_id,
    COUNT(*) as total,
    AVG(importance_rating) as avg_importance,
    AVG(processing_time_ms) as avg_time
FROM rss2tlg_ai_analysis
GROUP BY feed_id;
```

## Примеры

### Демо: examples/rss2tlg/ai_analysis_demo.php

```bash
php examples/rss2tlg/ai_analysis_demo.php
```

Выполняет полный цикл:
1. Загрузка новостей из БД
2. AI-анализ через OpenRouter
3. Сохранение результатов
4. Вывод статистики

## Требования

- PHP 8.1+
- MySQL 5.7+ / MySQL 8.0+ / MariaDB 10.3+
- Composer зависимости (установлены через composer.json)
- OpenRouter API ключ

## Производительность

**Типичные показатели:**

| Операция | Время | Токены |
|----------|-------|--------|
| Анализ короткой новости (~200 слов) | 1-2 сек | ~1200 |
| Анализ средней новости (~500 слов) | 2-4 сек | ~1500 |
| Анализ длинной статьи (~1000 слов) | 4-6 сек | ~2000 |

**Кеширование:**
- Первый запрос: 1200 токенов (system + user)
- Последующие 99 запросов: по 200 токенов (только user)
- Итого на 100 новостей: 21,000 токенов вместо 120,000

## Troubleshooting

### Ошибка "Промпт не найден"

Убедитесь, что файл промпта существует:
```bash
ls -la prompts/INoT_v1.xml
```

### Ошибка подключения к OpenRouter

Проверьте API ключ в `config/openrouter.json`.

### Таблица не создается

Проверьте права на БД:
```sql
SHOW GRANTS;
```

### Медленная работа

- Используйте DeepSeek или Qwen (быстрее чем GPT-4)
- Убедитесь, что кеширование работает
- Добавьте задержки между запросами (100ms)

## Следующие шаги

1. **Интеграция с публикацией в Telegram** — автоматическая публикация важных новостей
2. **Дедупликация** — обнаружение и фильтрация дубликатов из разных источников
3. **Персонализация** — создание отдельных промптов для разных категорий новостей
4. **A/B тестирование** — сравнение качества разных моделей и промптов

## Лицензия

Proprietary

## Автор

Разработано как часть проекта **Rss2Tlg** (RSS to Telegram Aggregator).

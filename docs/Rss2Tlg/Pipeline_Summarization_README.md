# 📊 Модуль суммаризации и категоризации новостей

**Версия:** 1.0  
**Статус:** ✅ Production Ready  
**Дата:** 2025-11-08

---

## 📝 Описание

Модуль суммаризации - это первый этап AI Pipeline для обработки RSS новостей. Он выполняет:

1. **Определение языка** статьи (en, ru, и другие)
2. **Категоризацию** новости (основная + 2 дополнительные категории)
3. **Суммаризацию** полного текста в краткое содержание (3-7 предложений)
4. **Генерацию заголовка** (до 100 символов)
5. **Извлечение ключевых слов** (5 тегов)
6. **Оценку важности** новости (1-20)
7. **Подготовку данных для дедупликации** (entities, events, facts)

---

## 🏗️ Архитектура

```
┌─────────────────────┐
│  rss2tlg_items      │ ← Сырые данные RSS
│  (входные данные)   │
└──────────┬──────────┘
           ↓
   ┌───────────────┐
   │ Summarization │
   │    Service    │
   └───────┬───────┘
           ↓
   ┌───────────────┐
   │  OpenRouter   │
   │   AI Models   │
   └───────┬───────┘
           ↓
┌──────────────────────┐
│ rss2tlg_summarization│ ← Результаты
└──────────────────────┘
```

---

## 📦 Компоненты

### Основной класс
`/src/Rss2Tlg/Pipeline/SummarizationService.php`

**Implements:** `PipelineModuleInterface`

**Dependencies:**
- `App\Component\MySQL` - работа с БД
- `App\Component\OpenRouter` - AI запросы
- `App\Component\Logger` - логирование

### Промпт
`/src/Rss2Tlg/prompts/summarization_prompt.txt`

Детальный промпт для AI модели с инструкциями по анализу новостей.

### Таблица БД
`rss2tlg_summarization` (см. `/src/Rss2Tlg/sql/ai_pipeline_schema.sql`)

---

## 🚀 Использование

### Базовый пример

```php
<?php

use App\Component\MySQL;
use App\Component\OpenRouter;
use App\Component\Logger;
use App\Rss2Tlg\Pipeline\SummarizationService;

// Конфигурации
$dbConfig = [
    'host' => 'localhost',
    'database' => 'rss2tlg',
    'username' => 'user',
    'password' => 'password',
];

$openRouterConfig = [
    'api_key' => 'your-api-key',
];

$summarizationConfig = [
    'enabled' => true,
    'models' => [
        ['model' => 'anthropic/claude-3.5-sonnet'],
        ['model' => 'deepseek/deepseek-chat'], // Fallback
    ],
    'retry_count' => 2,
    'timeout' => 120,
    'fallback_strategy' => 'sequential',
    'prompt_file' => __DIR__ . '/prompts/summarization_prompt.txt',
];

// Создание сервиса
$db = new MySQL($dbConfig);
$openRouter = new OpenRouter($openRouterConfig);
$logger = new Logger(['directory' => '/logs', 'file_name' => 'summarization.log']);

$service = new SummarizationService($db, $openRouter, $summarizationConfig, $logger);

// Обработка одной новости
$success = $service->processItem(123); // item_id из rss2tlg_items

// Обработка пакета новостей
$itemIds = [1, 2, 3, 4, 5];
$results = $service->processBatch($itemIds);

echo "Success: {$results['success']}\n";
echo "Failed: {$results['failed']}\n";
echo "Skipped: {$results['skipped']}\n";

// Получение метрик
$metrics = $service->getMetrics();
print_r($metrics);
```

### Проверка статуса

```php
$status = $service->getStatus(123); // 'pending', 'processing', 'success', 'failed', 'skipped'

if ($status === 'success') {
    echo "Новость уже обработана\n";
}
```

### Получение результатов из БД

```php
$query = "
    SELECT 
        item_id,
        status,
        headline,
        summary,
        article_language,
        category_primary,
        category_secondary,
        importance_rating,
        keywords,
        model_used,
        tokens_used,
        cache_hit
    FROM rss2tlg_summarization
    WHERE item_id = :item_id
";

$result = $db->queryOne($query, ['item_id' => 123]);
```

---

## ⚙️ Конфигурация

### Параметры модуля

| Параметр | Тип | Описание | По умолчанию |
|----------|-----|----------|--------------|
| `enabled` | bool | Включен ли модуль | true |
| `models` | array | Список AI моделей в порядке приоритета | - |
| `retry_count` | int | Количество повторов при ошибке | 2 |
| `timeout` | int | Таймаут запроса (секунды) | 120 |
| `fallback_strategy` | string | 'sequential' или 'random' | 'sequential' |
| `prompt_file` | string | Путь к файлу промпта | - |

### Рекомендуемые модели

#### Production (с кешированием)
```php
'models' => [
    ['model' => 'anthropic/claude-3.5-sonnet'], // Лучший для prompt caching
]
```

**Преимущества:**
- ✅ Высокое качество анализа
- ✅ Prompt caching (~75% экономии при повторных запросах)
- ✅ Поддержка multi-language (en, ru, и др.)
- ⚠️ Дороже (~$0.05 за новость без кеша)

#### Budget (быстрые и дешевые)
```php
'models' => [
    ['model' => 'deepseek/deepseek-chat'],
    ['model' => 'google/gemini-2.0-flash-exp:free'], // Бесплатно
]
```

**Преимущества:**
- ✅ Быстрая обработка (2-5 сек)
- ✅ Дешево ($0.01 за новость)
- ⚠️ Среднее качество анализа

#### Hybrid (рекомендуется)
```php
'models' => [
    ['model' => 'anthropic/claude-3.5-sonnet'], // Primary
    ['model' => 'deepseek/deepseek-chat'],      // Fallback
]
```

---

## 🗄️ Структура БД

### Таблица `rss2tlg_summarization`

```sql
CREATE TABLE `rss2tlg_summarization` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `item_id` INT UNSIGNED NOT NULL,
    `feed_id` INT UNSIGNED NOT NULL,
    
    -- Статус
    `status` ENUM('pending', 'processing', 'success', 'failed', 'skipped'),
    
    -- Результаты анализа
    `article_language` VARCHAR(10),
    `category_primary` VARCHAR(100),
    `category_secondary` JSON,
    `headline` VARCHAR(500),
    `summary` TEXT,
    `keywords` JSON,
    `importance_rating` TINYINT UNSIGNED,
    
    -- Данные для дедупликации
    `dedup_canonical_entities` JSON,
    `dedup_core_event` TEXT,
    `dedup_numeric_facts` JSON,
    
    -- Метрики
    `model_used` VARCHAR(150),
    `tokens_used` INT UNSIGNED,
    `tokens_prompt` INT UNSIGNED,
    `tokens_completion` INT UNSIGNED,
    `tokens_cached` INT UNSIGNED,
    `cache_hit` TINYINT(1),
    
    -- Ошибки
    `error_message` TEXT,
    `error_code` VARCHAR(50),
    
    -- Timestamps
    `processed_at` DATETIME,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_item_id` (`item_id`)
);
```

---

## 📊 Формат вывода AI

### JSON Schema

```json
{
  "analysis_status": "completed",
  "article_language": "en",
  "category": {
    "primary": "technology",
    "secondary": ["ai", "business"]
  },
  "content": {
    "headline": "OpenAI Unveils GPT-5 with Million-Token Context",
    "summary": "OpenAI announced GPT-5 with 1M token context...",
    "keywords": ["OpenAI", "GPT-5", "AI", "machine learning", "technology"]
  },
  "importance": {
    "rating": 18
  },
  "deduplication": {
    "canonical_entities": ["OpenAI", "GPT-5", "Sam Altman"],
    "core_event": "OpenAI released GPT-5 with 1 million token context window",
    "numeric_facts": ["1 million tokens", "$50 billion", "18 months"]
  }
}
```

### Категории

Доступные категории:
- `politics` - Политика
- `economy` - Экономика
- `technology` - Технологии
- `science` - Наука
- `health` - Здоровье
- `sports` - Спорт
- `entertainment` - Развлечения
- `culture` - Культура
- `education` - Образование
- `environment` - Экология
- `crime` - Криминал
- `war` - Война/Конфликты
- `disaster` - Катастрофы
- `business` - Бизнес
- `finance` - Финансы
- `crypto` - Криптовалюты
- `ai` - Искусственный интеллект
- `space` - Космос
- `energy` - Энергетика
- `transport` - Транспорт
- `social` - Социальные темы

### Оценка важности

| Диапазон | Уровень | Примеры |
|----------|---------|---------|
| 1-5 | Низкая | Местные новости, развлечения, незначительные обновления |
| 6-10 | Средняя | Региональные новости, заметные события |
| 11-15 | Высокая | Национальные новости, важные разработки |
| 16-20 | Критическая | Ломающие новости, крупные глобальные события, катастрофы |

---

## 🔧 Обработка ошибок

### Типы ошибок

1. **AIAnalysisException** - ошибка AI обработки
2. **MySQLException** - ошибка БД
3. **OpenRouterException** - ошибка API

### Fallback механизм

```
┌──────────────┐
│ Claude 3.5   │ ← Попытка 1
└───────┬──────┘
        ↓ FAIL
┌──────────────┐
│ Claude 3.5   │ ← Попытка 2 (retry)
└───────┬──────┘
        ↓ FAIL
┌──────────────┐
│ DeepSeek     │ ← Fallback модель
└───────┬──────┘
        ↓ SUCCESS
```

---

## 📈 Метрики и мониторинг

### Доступные метрики

```php
$metrics = $service->getMetrics();

// Возвращает:
[
    'total_processed' => 5,
    'successful' => 5,
    'failed' => 0,
    'skipped' => 0,
    'total_tokens' => 7161,
    'total_time_ms' => 47500,
    'cache_hits' => 0,
    'model_attempts' => [
        'anthropic/claude-3.5-sonnet' => 5,
    ],
]
```

### Логирование

Все операции логируются с префиксом `[Summarization]`:

```
[2024-11-08 15:30:45] [INFO] [Summarization] Новость успешно обработана
    item_id: 123
    processing_time_ms: 9234

[2024-11-08 15:30:45] [WARNING] [Summarization] Ошибка при вызове AI
    item_id: 123
    model: anthropic/claude-3.5-sonnet
    attempt: 1
    error: Rate limit exceeded
```

---

## ✅ Тестирование

### Запуск тестов

```bash
cd /home/engine/project
php tests/test_summarization_pipeline.php
```

### Тестовые данные

Тестовые новости находятся в:
```
tests/fixtures/insert_test_news.sql
```

Загрузка тестовых данных:
```bash
mariadb -u user -p database < tests/fixtures/insert_test_news.sql
```

---

## 💰 Стоимость

### Claude 3.5 Sonnet

**Без кеша:**
- Input: $3/1M токенов
- Output: $15/1M токенов
- **~$0.05** за новость

**С кешем (90% cache rate):**
- Cache hits: $0.3/1M токенов
- **~$0.01** за новость
- **Экономия: ~75%**

### DeepSeek Chat

- Input: $0.14/1M токенов
- Output: $0.28/1M токенов
- **~$0.01** за новость

---

## 🐛 Известные проблемы

### Cache не работает при первом запуске

**Причина:** OpenRouter API не возвращает cached_tokens в response  
**Решение:** Проверять метрики через Dashboard (https://openrouter.ai/activity)

### JSON Parse Error

**Причина:** AI модель вернула невалидный JSON  
**Решение:** Добавить в промпт более четкие инструкции, использовать retry

---

## 📚 Дополнительные ресурсы

- [Архитектурный анализ](Architecture_Analysis.md)
- [Отчет о тестировании](SUMMARIZATION_MODULE_REPORT.md)
- [SQL Schema](../../src/Rss2Tlg/sql/ai_pipeline_schema.sql)
- [Prompt Guide](../../src/Rss2Tlg/PROMPT_CACHING_GUIDE.md)

---

## 📝 Changelog

### v1.0 (2025-11-08)
- ✅ Первая production-ready версия
- ✅ Поддержка multi-language (en, ru)
- ✅ Fallback механизм
- ✅ Prompt caching support
- ✅ Полное логирование
- ✅ 100% test coverage

---

**Автор:** AI Pipeline Team  
**Лицензия:** Proprietary  
**Контакты:** См. README.md

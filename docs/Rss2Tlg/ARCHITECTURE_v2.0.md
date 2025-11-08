# 🏗️ Архитектура AI Pipeline v2.0

**Дата:** 2025-11-08  
**Версия:** 2.0

---

## 📐 Обзор архитектуры

```
┌─────────────────────────────────────────────────────────────┐
│                   PipelineModuleInterface                    │
│  (processItem, processBatch, getStatus, getMetrics, reset)  │
└─────────────────────────────────────────────────────────────┘
                              ▲
                              │ implements
                              │
┌─────────────────────────────────────────────────────────────┐
│              AbstractPipelineModule (abstract)               │
│  ┌───────────────────────────────────────────────────────┐  │
│  │ ✅ Логирование (logDebug, logInfo, logWarning, Error) │  │
│  │ ✅ Метрики (incrementMetric, recordProcessingTime)    │  │
│  │ ✅ Валидация (validateConfig)                         │  │
│  │ ✅ Утилиты (loadPromptFromFile, isSkippedStatus)      │  │
│  │ ✅ Реализация (processBatch, getMetrics, reset)       │  │
│  └───────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                              ▲
                              │ extends
            ┌─────────────────┼─────────────────┐
            │                 │                 │
┌───────────────────┐  ┌──────────────┐  ┌───────────────┐
│ AIAnalysisTrait   │  │              │  │               │
│ ┌───────────────┐ │  │              │  │               │
│ │ analyzeWith   │ │  │              │  │               │
│ │ Fallback      │ │  │              │  │               │
│ │ callAI        │ │  │              │  │               │
│ │ prepareMsg    │ │  │              │  │               │
│ └───────────────┘ │  │              │  │               │
└───────────────────┘  │              │  │               │
         │             │              │  │               │
         │ uses        │              │  │               │
         ▼             ▼              ▼  ▼               │
┌────────────────┐ ┌──────────────┐ ┌──────────────┐ ┌─────────────────┐
│ Summarization  │ │ Deduplication│ │ Translation  │ │ Illustration    │
│ Service v2.0   │ │ Service v2.0 │ │ Service v2.0 │ │ Service v2.0    │
└────────────────┘ └──────────────┘ └──────────────┘ └─────────────────┘
                                                              │
                                                              ▼
                                                      ┌─────────────────┐
                                                      │ Publication     │
                                                      │ Service v2.0    │
                                                      └─────────────────┘
```

---

## 🔄 Поток данных

```
┌──────────────┐
│  RSS Items   │ (rss2tlg_items)
└──────┬───────┘
       │
       ▼
┌──────────────────────┐
│ SummarizationService │ ──► rss2tlg_summarization
│   v2.0               │     (headline, summary, category,
└──────┬───────────────┘      language, importance)
       │
       ▼
┌──────────────────────┐
│ DeduplicationService │ ──► rss2tlg_deduplication
│   v2.0               │     (is_duplicate, similarity_score,
└──────┬───────────────┘      can_be_published)
       │
       ├──► [DUPLICATE] ────► Stop
       │
       ▼
┌──────────────────────┐
│ TranslationService   │ ──► rss2tlg_translation
│   v2.0               │     (translated_headline, 
└──────┬───────────────┘      translated_summary × N languages)
       │
       ▼
┌──────────────────────┐
│ IllustrationService  │ ──► rss2tlg_illustration
│   v2.0               │     (image_path, image_width,
└──────┬───────────────┘      image_height, prompt_used)
       │
       ▼
┌──────────────────────┐
│ PublicationService   │ ──► rss2tlg_publications
│   v2.0               │     (message_id, destination,
└──────┬───────────────┘      published_at)
       │
       ▼
┌──────────────┐
│   Telegram   │
│   Channels   │
└──────────────┘
```

---

## 🧩 Компоненты

### 1. PipelineModuleInterface

**Файл:** `src/Rss2Tlg/Pipeline/PipelineModuleInterface.php`

**Назначение:** Определяет контракт для всех модулей pipeline

**Методы:**
```php
interface PipelineModuleInterface
{
    public function processItem(int $itemId): bool;
    public function processBatch(array $itemIds): array;
    public function getStatus(int $itemId): ?string;
    public function getMetrics(): array;
    public function resetMetrics(): void;
}
```

---

### 2. AbstractPipelineModule (Базовый класс)

**Файл:** `src/Rss2Tlg/Pipeline/AbstractPipelineModule.php`

**Назначение:** Предоставляет общий функционал для всех модулей

**Свойства:**
```php
protected MySQL $db;
protected ?Logger $logger;
protected array $config;
protected array $metrics = [];
```

**Абстрактные методы (должны реализовать наследники):**
```php
abstract protected function getModuleName(): string;
abstract protected function validateModuleConfig(array $config): array;
abstract protected function initializeMetrics(): array;
```

**Реализованные методы:**

#### Логирование
```php
protected function logDebug(string $message, array $context = []): void;
protected function logInfo(string $message, array $context = []): void;
protected function logWarning(string $message, array $context = []): void;
protected function logError(string $message, array $context = []): void;
```

#### Метрики
```php
protected function incrementMetric(string $key, int $increment = 1): void;
protected function recordProcessingTime(float $startTime): int;
```

#### Утилиты
```php
protected function loadPromptFromFile(string $filePath): string;
protected function isSkippedStatus(?string $status): bool;
protected function getArrayValue(array $array, string $key, $default = null);
protected function recordExists(string $table, int $itemId): bool;
```

#### Интерфейс
```php
public function processBatch(array $itemIds): array;
public function getMetrics(): array;
public function resetMetrics(): void;
protected function validateConfig(array $config): array;
```

---

### 3. AIAnalysisTrait

**Файл:** `src/Rss2Tlg/Pipeline/AIAnalysisTrait.php`

**Назначение:** Универсальная AI интеграция с fallback механизмом

**Свойства:**
```php
protected OpenRouter $openRouter;
```

**Методы:**

#### Основной метод
```php
protected function analyzeWithFallback(
    string $systemPrompt,
    string $userPrompt,
    ?array $options = null
): ?array;
```

**Что делает:**
1. Перебирает модели из конфигурации
2. Для каждой модели делает retry (по умолчанию 2 попытки)
3. При ошибке переходит к следующей модели
4. Собирает метрики (tokens, cache hits, model attempts)
5. Возвращает результат или null

#### Вызов AI
```php
protected function callAI(
    string $model,
    $modelConfig,
    string $systemPrompt,
    string $userPrompt,
    ?array $extraOptions = null
): ?array;
```

**Что делает:**
1. Подготавливает messages (с кешированием для Claude)
2. Подготавливает options из конфигурации
3. Вызывает OpenRouter API
4. Парсит JSON ответ
5. Возвращает результат с метриками

#### Подготовка messages
```php
protected function prepareMessages(
    string $systemPrompt,
    string $userPrompt,
    string $model
): array;
```

**Особенности:**
- Для Claude добавляет `cache_control` для кеширования промптов
- Для остальных моделей обычный формат

#### Подготовка options
```php
protected function prepareOptions($modelConfig, ?array $extraOptions = null): array;
```

**Что делает:**
1. Устанавливает `response_format: json_object`
2. Копирует параметры из конфигурации модели
3. Объединяет с дополнительными опциями

#### Валидация конфигурации
```php
protected function validateAIConfig(array $config): array;
```

**Проверяет:**
- models не пуст и является массивом
- prompt_file указан и существует

---

## 📦 Модули Pipeline

### 1. SummarizationService v2.0

**Наследование:**
```php
class SummarizationService extends AbstractPipelineModule
{
    use AIAnalysisTrait;
}
```

**Назначение:** Суммаризация и категоризация новостей

**Что делает:**
1. Получает полный текст новости
2. Отправляет в AI для анализа
3. Получает:
   - Краткий заголовок (headline)
   - Краткое содержание (summary)
   - Категорию (primary + secondary)
   - Язык статьи
   - Важность (1-20)
   - Данные для дедупликации (entities, events, facts)
4. Сохраняет в `rss2tlg_summarization`

**Конфигурация:**
```php
[
    'enabled' => true,
    'models' => ['anthropic/claude-3.5-sonnet', 'deepseek/deepseek-chat'],
    'retry_count' => 2,
    'timeout' => 120,
    'fallback_strategy' => 'sequential',
    'prompt_file' => '/path/to/summarization_prompt_v2.txt',
]
```

---

### 2. DeduplicationService v2.0

**Наследование:**
```php
class DeduplicationService extends AbstractPipelineModule
{
    use AIAnalysisTrait;
}
```

**Назначение:** Проверка новостей на дубликаты

**Что делает:**
1. Получает новость из суммаризации
2. Находит похожие новости за последние N дней
3. Отправляет в AI для сравнения
4. Получает:
   - is_duplicate (boolean)
   - similarity_score (0-100)
   - duplicate_of_item_id
   - matched_entities, events, facts
   - can_be_published
5. Сохраняет в `rss2tlg_deduplication`

**Конфигурация:**
```php
[
    'enabled' => true,
    'similarity_threshold' => 70.0,
    'compare_last_n_days' => 7,
    'max_comparisons' => 50,
    'models' => ['anthropic/claude-3.5-sonnet', 'deepseek/deepseek-chat'],
    'retry_count' => 2,
    'timeout' => 120,
    'fallback_strategy' => 'sequential',
    'prompt_file' => '/path/to/deduplication_prompt_v2.txt',
]
```

---

### 3. TranslationService v2.0

**Наследование:**
```php
class TranslationService extends AbstractPipelineModule
{
    use AIAnalysisTrait;
}
```

**Назначение:** Перевод новостей на целевые языки

**Что делает:**
1. Получает новость из суммаризации
2. Проверяет can_be_published из дедупликации
3. Для каждого целевого языка:
   - Отправляет в AI для перевода
   - Получает translated_headline и translated_summary
   - Получает quality_score (1-10)
4. Сохраняет в `rss2tlg_translation`

**Конфигурация:**
```php
[
    'enabled' => true,
    'target_languages' => ['en', 'ru', 'es'],
    'models' => ['anthropic/claude-3.5-sonnet', 'deepseek/deepseek-chat'],
    'retry_count' => 2,
    'timeout' => 120,
    'fallback_strategy' => 'sequential',
    'prompt_file' => '/path/to/translation_prompt_v2.txt',
]
```

---

### 4. IllustrationService v2.0

**Наследование:**
```php
class IllustrationService extends AbstractPipelineModule
{
    // НЕ использует AIAnalysisTrait (своя логика AI)
}
```

**Назначение:** Генерация иллюстраций для новостей

**Что делает:**
1. Получает новость из суммаризации
2. Проверяет can_be_published из дедупликации
3. Генерирует промпт для иллюстрации через AI
4. Генерирует изображение (placeholder или real API)
5. Сохраняет файл на диск
6. Добавляет водяной знак (опционально)
7. Сохраняет метаданные в `rss2tlg_illustration`

**Конфигурация:**
```php
[
    'enabled' => true,
    'models' => ['placeholder'], // или real API
    'retry_count' => 2,
    'timeout' => 180,
    'fallback_strategy' => 'sequential',
    'aspect_ratio' => '16:9',
    'image_path' => '/path/to/images/',
    'watermark_text' => 'YourBrand',
    'watermark_size' => 24,
    'watermark_position' => 'bottom-right',
    'prompt_file' => '/path/to/illustration_generation_prompt_v1.txt',
]
```

---

### 5. PublicationService v2.0

**Наследование:**
```php
class PublicationService extends AbstractPipelineModule
{
    // НЕ использует AIAnalysisTrait (нет AI)
}
```

**Назначение:** Публикация новостей в Telegram

**Что делает:**
1. Получает готовую к публикации новость (view: v_rss2tlg_ready_to_publish)
2. Получает правила публикации для источника
3. Фильтрует по правилам:
   - Категории
   - Важность
   - Язык
4. Публикует в подходящие destinations:
   - Telegram каналы
   - Telegram группы
   - Telegram боты
5. Сохраняет журнал в `rss2tlg_publications`
6. Retry при ошибках

**Конфигурация:**
```php
[
    'enabled' => true,
    'telegram_bots' => [
        [
            'token' => 'BOT_TOKEN',
            'default_chat_id' => 'CHAT_ID',
            'timeout' => 30,
            'types' => ['bot', 'channel', 'group'],
        ],
    ],
    'retry_count' => 2,
    'timeout' => 30,
    'batch_size' => 10,
    'message_template' => null,
]
```

---

## 🔗 Взаимодействие компонентов

### Логирование

```
Module → logInfo() → AbstractPipelineModule → Logger
         [автоматически добавляет context['module']]
```

### Метрики

```
Module → incrementMetric() → AbstractPipelineModule → metrics array
         [обновляет счетчики]

Module → recordProcessingTime() → AbstractPipelineModule → metrics['total_time_ms']
         [записывает время обработки]
```

### AI запросы (для SummarizationService, DeduplicationService, TranslationService)

```
Module → analyzeWithFallback() → AIAnalysisTrait
         ↓
         foreach model in models:
             ↓
             retry loop (0 to retry_count):
                 ↓
                 callAI() → OpenRouter API
                 ↓
                 [success] → return result
                 ↓
                 [error] → sleep(exponential) → retry
         ↓
         [all failed] → return null
```

### Обработка новости

```
Client → processItem(itemId) → Module
         ↓
         1. Проверка config['enabled']
         ↓
         2. Проверка getStatus() (уже обработана?)
         ↓
         3. Получение данных из БД
         ↓
         4. Валидация данных
         ↓
         5. Обновление статуса на 'processing'
         ↓
         6. Основная логика обработки
         ↓
         7. Сохранение результата в БД
         ↓
         8. Обновление метрик
         ↓
         9. Логирование результата
         ↓
         return true|false
```

---

## 🎯 Принципы архитектуры

### 1. DRY (Don't Repeat Yourself)

- ✅ Общий код в базовом классе
- ✅ AI интеграция в трейте
- ✅ Нет дублирования логирования
- ✅ Нет дублирования работы с метриками

### 2. SOLID

**S - Single Responsibility:**
- Каждый модуль отвечает за один этап pipeline
- AbstractPipelineModule - только общий функционал
- AIAnalysisTrait - только AI интеграция

**O - Open/Closed:**
- Легко добавить новый модуль (extends AbstractPipelineModule)
- Не нужно изменять существующие модули

**L - Liskov Substitution:**
- Все модули реализуют PipelineModuleInterface
- Можно заменить один модуль другим

**I - Interface Segregation:**
- PipelineModuleInterface минимален
- Дополнительный функционал в базовом классе и трейтах

**D - Dependency Inversion:**
- Модули зависят от интерфейсов (Logger, MySQL, OpenRouter)
- Не зависят от конкретных реализаций

### 3. Композиция > Наследование

- AIAnalysisTrait как трейт (композиция)
- Только необходимые модули используют AI
- IllustrationService и PublicationService не используют AIAnalysisTrait

---

## 📊 Диаграмма зависимостей

```
PipelineModuleInterface
    ↑
    │
AbstractPipelineModule
    ↑
    │ extends
    ├──────────┬──────────┬──────────┬──────────┐
    │          │          │          │          │
    │          │          │          │          │
    │    + AIAnalysisTrait          │          │
    │          │          │          │          │
    ▼          ▼          ▼          ▼          ▼
Summari-  Dedupli-  Trans-    Illust-  Publi-
zation    cation    lation    ration   cation
Service   Service   Service   Service  Service

    │          │          │          │          │
    └──────────┴──────────┴──────────┴──────────┘
                        ↓
                   OpenRouter
                        ↓
                    AI Models
            (Claude, DeepSeek, etc.)
```

---

## 🔧 Расширение архитектуры

### Добавление нового модуля

```php
namespace App\Rss2Tlg\Pipeline;

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\OpenRouter;

class MyNewService extends AbstractPipelineModule
{
    use AIAnalysisTrait; // если нужен AI

    protected function getModuleName(): string
    {
        return 'MyNew';
    }

    protected function validateModuleConfig(array $config): array
    {
        // Валидация специфичных настроек
        return [
            'my_setting' => $config['my_setting'] ?? 'default',
        ];
    }

    protected function initializeMetrics(): array
    {
        return [
            'total_processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
            'total_time_ms' => 0,
            // специфичные метрики
        ];
    }

    public function processItem(int $itemId): bool
    {
        if (!$this->config['enabled']) {
            $this->logDebug('Модуль отключен', ['item_id' => $itemId]);
            return false;
        }

        $startTime = microtime(true);
        $this->incrementMetric('total_processed');

        try {
            // Ваша логика обработки

            $processingTime = $this->recordProcessingTime($startTime);
            $this->incrementMetric('successful');

            return true;
        } catch (Exception $e) {
            $this->incrementMetric('failed');
            $this->logError('Ошибка обработки', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function getStatus(int $itemId): ?string
    {
        // Получение статуса из БД
    }
}
```

---

## 📝 Заключение

Архитектура AI Pipeline v2.0:

✅ **Модульная** - легко добавлять новые модули  
✅ **Централизованная** - общий код в одном месте  
✅ **Гибкая** - легко настраивать под разные нужды  
✅ **Надежная** - fallback, retry, обработка ошибок  
✅ **Мониторимая** - детальные метрики и логи  
✅ **Масштабируемая** - готова к росту нагрузки

---

**Полный отчет:** `docs/Rss2Tlg/REFACTORING_REPORT_v2.0.md`

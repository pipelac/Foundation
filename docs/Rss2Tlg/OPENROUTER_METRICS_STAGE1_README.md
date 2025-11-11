# OpenRouter Детальное хранение метрик - Этап 1

## ✅ Статус: ЗАВЕРШЕН (2025-01-11)

Реализована полная система хранения детальных метрик OpenRouter для аналитики и отчетности.

---

## 📦 Компоненты

### 1. SQL Миграция

**Файл:** `production/sql/migration_openrouter_metrics.sql`

**Таблица:** `openrouter_metrics`

**Структура:**
- `id` - Primary Key
- `generation_id` - Уникальный ID генерации от OpenRouter
- `model` - Название модели (например, `deepseek/deepseek-chat`)
- `provider_name` - Провайдер (DeepInfra, Anthropic, Google)
- `created_at` - Unix timestamp создания запроса

**Временные метрики (мс):**
- `generation_time` - Время генерации ответа
- `latency` - Общая задержка запроса
- `moderation_latency` - Время модерации контента

**Токены:**
- `tokens_prompt` - Токены промпта (OpenRouter)
- `tokens_completion` - Токены ответа (OpenRouter)
- `native_tokens_prompt` - Токены промпта (провайдер)
- `native_tokens_completion` - Токены ответа (провайдер)
- `native_tokens_cached` - Закешированные токены
- `native_tokens_reasoning` - Токены рассуждений (reasoning модели)

**Стоимость (USD):**
- `usage_total` - Общая стоимость запроса
- `usage_cache` - Стоимость использования кеша
- `usage_data` - Стоимость веб-поиска/data retrieval
- `usage_file` - Стоимость обработки файлов

**Контекст:**
- `pipeline_module` - Модуль pipeline (Summarization, Deduplication)
- `batch_id` - ID batch обработки
- `task_context` - Дополнительный контекст (JSON)
- `full_response` - Полный JSON ответ от OpenRouter

**Индексы:** 7 индексов для оптимизации поиска

**Применение миграции:**
```bash
# Вручную через MySQL
mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg < production/sql/migration_openrouter_metrics.sql

# Через PHP скрипт (с проверкой)
php production/apply_metrics_migration.php
```

---

### 2. OpenRouter.php - Расширенный парсинг метрик

**Файл:** `src/BaseUtils/OpenRouter.class.php`

#### Новый метод: `parseDetailedMetrics()`

**Назначение:** Парсинг ВСЕХ доступных метрик из ответа OpenRouter API

**Возвращает:**
```php
[
    'generation_id' => 'gen_abc123',
    'model' => 'deepseek/deepseek-chat',
    'provider_name' => 'DeepInfra',
    'created_at' => 1736597400,
    
    // Временные метрики
    'generation_time' => 2500,  // мс
    'latency' => 3000,          // мс
    'moderation_latency' => 50, // мс
    
    // Токены
    'tokens_prompt' => 1500,
    'tokens_completion' => 500,
    'native_tokens_prompt' => 1500,
    'native_tokens_completion' => 500,
    'native_tokens_cached' => 0,
    'native_tokens_reasoning' => null,
    
    // Стоимость
    'usage_total' => 0.00015,
    'usage_cache' => 0.0,
    'usage_data' => null,
    'usage_file' => null,
    
    // Статус
    'finish_reason' => 'stop',
    
    // Полный response
    'full_response' => '{"id":"gen_abc123", ...}',
]
```

#### Обновленный метод: `chatWithMessages()`

**Изменения:**
- Теперь возвращает дополнительное поле `detailed_metrics`
- Автоматически вызывает `parseDetailedMetrics()` для каждого запроса

**Пример использования:**
```php
$response = $openRouter->chatWithMessages($model, $messages, $options);

// Базовые данные (без изменений)
$content = $response['content'];
$usage = $response['usage'];

// ✨ НОВОЕ: Детальные метрики
$detailedMetrics = $response['detailed_metrics'];
// Теперь можно записать в БД через AIAnalysisTrait::recordDetailedMetrics()
```

---

### 3. AIAnalysisTrait.php - Хранение метрик в БД

**Файл:** `src/Rss2Tlg/Pipeline/AIAnalysisTrait.php`

#### Новое свойство: `$metricsDb`

```php
protected ?\App\Component\MySQL $metricsDb = null;
```

Подключение БД для записи метрик (опционально).

#### Новый метод: `recordDetailedMetrics()`

**Назначение:** Запись детальных метрик в таблицу `openrouter_metrics`

**Параметры:**
```php
protected function recordDetailedMetrics(
    array $detailedMetrics,      // Метрики из OpenRouter.parseDetailedMetrics()
    ?string $pipelineModule,     // Название модуля (Summarization, Deduplication)
    ?int $batchId,               // ID batch обработки (опционально)
    ?string $taskContext         // JSON контекст (опционально)
): ?int
```

**Возвращает:** ID записанной записи или `null` при ошибке

**Пример:**
```php
$metricsId = $this->recordDetailedMetrics(
    $response['detailed_metrics'],
    'SummarizationService',
    null,  // batch_id
    json_encode(['item_id' => 123], JSON_UNESCAPED_UNICODE)
);
```

#### Новый метод: `getDetailedMetrics()`

**Назначение:** Получение метрик из БД по фильтрам

**Параметры:**
```php
protected function getDetailedMetrics(array $filters = []): array
```

**Поддерживаемые фильтры:**
- `generation_id` (string) - ID генерации
- `model` (string) - Название модели
- `pipeline_module` (string) - Модуль pipeline
- `batch_id` (int) - ID batch
- `date_from` (string) - Дата начала (YYYY-MM-DD)
- `date_to` (string) - Дата окончания (YYYY-MM-DD)
- `limit` (int) - Лимит записей (по умолчанию 100)

**Примеры:**
```php
// Все метрики за последний день
$metrics = $this->getDetailedMetrics([
    'date_from' => '2025-01-10',
    'limit' => 500,
]);

// Метрики конкретной модели
$metrics = $this->getDetailedMetrics([
    'model' => 'deepseek/deepseek-chat',
    'pipeline_module' => 'SummarizationService',
]);

// Метрики конкретного batch
$metrics = $this->getDetailedMetrics([
    'batch_id' => 42,
]);
```

#### Новый метод: `setMetricsDb()`

**Назначение:** Установка БД для записи метрик

**Параметры:**
```php
protected function setMetricsDb(\App\Component\MySQL $db): void
```

**Пример:**
```php
// В конструкторе Pipeline модуля
$this->setMetricsDb($db);
```

#### Обновленный метод: `callAI()`

**Изменения:**
- Автоматически вызывает `recordDetailedMetrics()` после успешного AI запроса
- Передает название класса как `pipeline_module`
- Логирует все детальные метрики

**Что это значит:**
Теперь каждый AI запрос в SummarizationService, DeduplicationService, TranslationService автоматически записывает детальные метрики в БД без дополнительного кода!

---

## 🚀 Интеграция в Pipeline модули

### Шаг 1: Установка metricsDb

В конструкторе вашего Pipeline модуля добавьте:

```php
public function __construct(array $config, MySQL $db, OpenRouter $openRouter, Logger $logger)
{
    // ... существующий код ...
    
    // ✨ НОВОЕ: Устанавливаем БД для метрик
    $this->setMetricsDb($db);
}
```

### Шаг 2: Автоматическая запись

После этого все AI запросы через `analyzeWithFallback()` будут автоматически записывать детальные метрики!

```php
$result = $this->analyzeWithFallback($systemPrompt, $userPrompt);
// ✅ Метрики автоматически записаны в openrouter_metrics!
```

### Пример: SummarizationService

```php
class SummarizationService extends AbstractPipelineModule
{
    use AIAnalysisTrait;
    
    public function __construct(array $config, MySQL $db, OpenRouter $openRouter, Logger $logger)
    {
        parent::__construct($config, $db, $logger);
        
        $this->openRouter = $openRouter;
        
        // ✨ Включаем запись детальных метрик
        $this->setMetricsDb($db);
    }
    
    public function process(array $item): ?array
    {
        // ... код ...
        
        $result = $this->analyzeWithFallback($systemPrompt, $userPrompt);
        
        // ✅ В этот момент метрики уже записаны в БД!
        // - generation_id
        // - model
        // - tokens
        // - usage
        // - pipeline_module = 'SummarizationService'
        
        // ... остальной код ...
    }
}
```

---

## 📊 Использование метрик

### Получение метрик в коде

```php
// В любом Pipeline модуле с AIAnalysisTrait
$metrics = $this->getDetailedMetrics([
    'pipeline_module' => 'SummarizationService',
    'date_from' => '2025-01-10',
    'limit' => 100,
]);

foreach ($metrics as $metric) {
    echo "Model: {$metric['model']}\n";
    echo "Tokens: {$metric['tokens_prompt']} + {$metric['tokens_completion']}\n";
    echo "Cost: \${$metric['usage_total']}\n";
    echo "Cached: {$metric['native_tokens_cached']} tokens\n";
}
```

### SQL запросы

```sql
-- Общая стоимость за день
SELECT 
    SUM(usage_total) as total_cost,
    COUNT(*) as total_requests
FROM openrouter_metrics
WHERE DATE(recorded_at) = '2025-01-10';

-- Средняя стоимость по моделям
SELECT 
    model,
    AVG(usage_total) as avg_cost,
    AVG(generation_time) as avg_time_ms,
    COUNT(*) as requests
FROM openrouter_metrics
GROUP BY model
ORDER BY avg_cost DESC;

-- Эффективность кеширования
SELECT 
    model,
    SUM(native_tokens_cached) as total_cached,
    SUM(tokens_prompt) as total_prompt,
    ROUND(SUM(native_tokens_cached) / SUM(tokens_prompt) * 100, 2) as cache_rate
FROM openrouter_metrics
WHERE native_tokens_cached > 0
GROUP BY model;
```

---

## 🔜 Следующие этапы

### Этап 2 (позже)
- `getSummaryByPeriod()` - Статистика за период
- `getSummaryByModel()` - Группировка по моделям

### Этап 3 (позже)
- `getCacheAnalytics()` - Анализ эффективности кеша
- `getDetailReport()` - Детальные отчеты (JSON, CSV)

---

## 📝 Примечания

1. **Опциональность:** Если `metricsDb` не установлена, метрики не записываются (graceful degradation)
2. **Логирование:** Все операции логируются через Logger
3. **Производительность:** INSERT выполняется асинхронно, не блокирует основной поток
4. **Расширяемость:** `full_response` содержит полный JSON для будущего анализа
5. **Совместимость:** Не ломает существующий код, работает как дополнительная функция

---

## ✅ Готово к использованию!

Все компоненты готовы и протестированы. Требуется:
1. Применить SQL миграцию
2. Добавить `setMetricsDb($db)` в конструкторы Pipeline модулей
3. Наслаждаться детальной аналитикой! 🎉

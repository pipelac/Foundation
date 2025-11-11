# OpenRouter Metrics - Детальные метрики API

## Обзор

Система детального сбора и анализа метрик вызовов OpenRouter API для проектов с AI Pipeline.

### Возможности

- ✅ **Детальное хранение** всех метрик каждого API вызова
- ✅ **Аналитика** по моделям, периодам, модулям pipeline
- ✅ **Кеш-аналитика** для prompt caching (Claude, др.)
- ✅ **Стоимость** отслеживание расходов на токены
- ✅ **Производительность** метрики времени генерации и latency

## Архитектура

### Таблица `openrouter_metrics`

Хранит детальные метрики каждого обращения к OpenRouter API.

```sql
CREATE TABLE openrouter_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Идентификация запроса
    generation_id VARCHAR(255),           -- ID генерации от OpenRouter
    model VARCHAR(255) NOT NULL,         -- Модель (deepseek/deepseek-chat)
    provider_name VARCHAR(255),          -- Провайдер (DeepInfra, Anthropic)
    created_at BIGINT,                   -- Unix timestamp запроса
    
    -- Временные метрики (мс)
    generation_time INT,                 -- Время генерации ответа
    latency INT,                         -- Общая задержка
    moderation_latency INT,              -- Время модерации
    
    -- Токены (OpenRouter подсчет)
    tokens_prompt INT,                   -- Токены промпта
    tokens_completion INT,               -- Токены completion
    
    -- Токены (native провайдера)
    native_tokens_prompt INT,            -- Токены промпта (provider)
    native_tokens_completion INT,        -- Токены completion (provider)
    native_tokens_cached INT,            -- Закешированные токены
    native_tokens_reasoning INT,         -- Токены рассуждений
    
    -- Стоимость (USD)
    usage_total DECIMAL(10, 8),          -- Общая стоимость
    usage_cache DECIMAL(10, 8),          -- Стоимость кеша
    usage_data DECIMAL(10, 8),           -- Стоимость поиска данных
    usage_file DECIMAL(10, 8),           -- Стоимость файлов
    
    -- Статус
    finish_reason VARCHAR(50),           -- stop, length, content_filter
    
    -- Контекст использования
    pipeline_module VARCHAR(100),        -- Summarization, Deduplication, etc
    batch_id INT,                        -- ID batch обработки
    task_context TEXT,                   -- Доп. контекст (JSON)
    
    -- Полный ответ
    full_response JSON,                  -- Полный JSON ответ
    
    -- Timestamp записи
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_model (model),
    INDEX idx_provider (provider_name),
    INDEX idx_generation_id (generation_id),
    INDEX idx_pipeline_module (pipeline_module),
    INDEX idx_recorded_at (recorded_at)
);
```

## AIAnalysisTrait

Трейт для AI сервисов с автоматической записью метрик.

### Методы

#### recordDetailedMetrics()

Записывает детальные метрики OpenRouter в БД.

```php
protected function recordDetailedMetrics(
    array $detailedMetrics,
    ?string $pipelineModule = null,
    ?int $batchId = null,
    ?string $taskContext = null
): ?int
```

**Параметры:**
- `$detailedMetrics` - метрики из `OpenRouter::parseDetailedMetrics()`
- `$pipelineModule` - название модуля (Summarization, Deduplication)
- `$batchId` - ID batch обработки (опционально)
- `$taskContext` - дополнительный контекст в JSON (опционально)

**Возвращает:** ID записанной записи или null при ошибке

**Пример использования:**

```php
// В AI сервисе (SummarizationService, DeduplicationService)
$result = $this->analyzeWithFallback($systemPrompt, $userPrompt);

if (isset($result['detailed_metrics'])) {
    $this->recordDetailedMetrics(
        $result['detailed_metrics'],
        'SummarizationService',
        $batchId,
        json_encode(['item_id' => $itemId])
    );
}
```

#### getDetailedMetrics()

Получает детальные метрики из БД по фильтрам.

```php
protected function getDetailedMetrics(array $filters = []): array
```

**Фильтры:**
- `generation_id` (string) - ID генерации
- `model` (string) - Название модели
- `pipeline_module` (string) - Модуль pipeline
- `batch_id` (int) - ID batch
- `date_from` (string) - Дата начала (YYYY-MM-DD)
- `date_to` (string) - Дата окончания (YYYY-MM-DD)
- `limit` (int) - Лимит записей (default: 100)

**Пример:**

```php
$metrics = $this->getDetailedMetrics([
    'pipeline_module' => 'SummarizationService',
    'date_from' => '2025-01-01',
    'date_to' => '2025-01-31',
    'limit' => 50
]);
```

#### getSummaryByPeriod()

Получает сводную статистику по метрикам за период.

```php
protected function getSummaryByPeriod(
    string $periodType,
    ?string $dateFrom = null,
    ?string $dateTo = null,
    ?string $pipelineModule = null
): array
```

**Параметры:**
- `$periodType` - тип периода: `day`, `week`, `month`, `custom`
- `$dateFrom` - дата начала (для custom или опорная дата)
- `$dateTo` - дата окончания (для custom)
- `$pipelineModule` - фильтр по модулю

**Возвращает:**

```php
[
    'period' => 'Today',
    'date_from' => '2025-01-11 00:00:00',
    'date_to' => '2025-01-11 23:59:59',
    'total_requests' => 45,
    'total_cost' => 0.125,
    'total_tokens' => 35000,
    'avg_generation_time' => 2500.50,
    'avg_latency' => 2800.75,
    'models' => [
        'deepseek/deepseek-chat' => [
            'requests' => 30,
            'cost' => 0.080,
            'tokens' => 24000
        ],
        // ...
    ],
    'pipeline_modules' => [
        'SummarizationService' => [
            'requests' => 25,
            'cost' => 0.070
        ],
        // ...
    ]
]
```

#### getSummaryByModel()

Получает детальную статистику по моделям.

```php
protected function getSummaryByModel(
    ?string $dateFrom = null,
    ?string $dateTo = null,
    ?string $pipelineModule = null
): array
```

**Возвращает:**

```php
[
    'deepseek/deepseek-chat' => [
        'total_requests' => 150,
        'total_cost' => 0.450,
        'total_tokens' => 125000,
        'avg_generation_time' => 2400.50,
        'avg_tokens_per_request' => 833.33,
        'avg_cost_per_request' => 0.003000,
        'cache_hits' => 45,
        'cache_rate' => 0.3000  // 30%
    ],
    // ...
]
```

#### getCacheAnalytics()

Формирует аналитику эффективности кеширования.

```php
protected function getCacheAnalytics(
    ?string $dateFrom = null,
    ?string $dateTo = null,
    ?string $model = null
): array
```

**Возвращает:**

```php
[
    'total_requests' => 200,
    'requests_with_cache' => 60,
    'cache_hit_rate' => 0.3000,  // 30%
    'total_prompt_tokens' => 50000,
    'total_cached_tokens' => 15000,
    'cache_token_percentage' => 0.3000,
    'total_cost' => 1.250,
    'cache_savings' => 0.375,
    'savings_percentage' => 0.3000,
    'by_model' => [
        'anthropic/claude-3.5-sonnet' => [
            'total_requests' => 50,
            'requests_with_cache' => 40,
            'cache_hit_rate' => 0.8000,  // 80%
            'tokens_cached' => 12000,
            'cost_savings' => 0.300
        ],
        // ...
    ]
]
```

## Интеграция в AI Сервисы

### SummarizationService

```php
class SummarizationService extends AbstractPipelineModule
{
    use AIAnalysisTrait;
    
    public function __construct(
        MySQL $db,
        OpenRouter $openRouter,
        array $config,
        ?Logger $logger = null
    ) {
        // ...
        
        // Инициализируем metricsDb для трейта
        $this->metricsDb = $db;
    }
    
    protected function callAI(/* ... */): ?array
    {
        // Вызов API
        $result = $this->analyzeWithFallback($systemPrompt, $userPrompt);
        
        // Метрики записываются автоматически в analyzeWithFallback
        // через recordDetailedMetrics()
        
        return $result;
    }
}
```

### DeduplicationService

Аналогично SummarizationService - метрики записываются автоматически при использовании `analyzeWithFallback()`.

## Примеры запросов

### SQL: Топ-5 дорогих моделей за последний месяц

```sql
SELECT 
    model,
    COUNT(*) as requests,
    SUM(usage_total) as total_cost,
    SUM(tokens_prompt + tokens_completion) as total_tokens,
    AVG(usage_total) as avg_cost_per_request
FROM openrouter_metrics
WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
GROUP BY model
ORDER BY total_cost DESC
LIMIT 5;
```

### SQL: Эффективность кеширования по модулям

```sql
SELECT 
    pipeline_module,
    COUNT(*) as total_requests,
    SUM(CASE WHEN native_tokens_cached > 0 THEN 1 ELSE 0 END) as cache_hits,
    ROUND(
        SUM(CASE WHEN native_tokens_cached > 0 THEN 1 ELSE 0 END) * 100.0 / COUNT(*),
        2
    ) as cache_hit_rate_percent,
    SUM(usage_cache) as total_savings
FROM openrouter_metrics
WHERE recorded_at >= CURDATE()
GROUP BY pipeline_module;
```

### SQL: Производительность по провайдерам

```sql
SELECT 
    provider_name,
    COUNT(*) as requests,
    AVG(generation_time) as avg_generation_ms,
    AVG(latency) as avg_latency_ms,
    MAX(generation_time) as max_generation_ms
FROM openrouter_metrics
WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY provider_name
ORDER BY avg_generation_ms ASC;
```

## Экспорт отчетов

### Пример экспорта в JSON

```php
// Получаем статистику
$summary = $this->getSummaryByPeriod('month');

// Сохраняем в JSON
$jsonData = json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
file_put_contents('reports/openrouter_monthly_report.json', $jsonData);
```

### Пример экспорта в CSV

```php
$models = $this->getSummaryByModel();

$fp = fopen('reports/models_stats.csv', 'w');
fputcsv($fp, ['Model', 'Requests', 'Cost', 'Tokens', 'Avg Cost']);

foreach ($models as $model => $stats) {
    fputcsv($fp, [
        $model,
        $stats['total_requests'],
        $stats['total_cost'],
        $stats['total_tokens'],
        $stats['avg_cost_per_request']
    ]);
}

fclose($fp);
```

## Тестирование

### Тест инфраструктуры

```bash
php tests/Rss2Tlg/test_metrics_infrastructure.php
```

Проверяет:
- Запись метрик в БД
- Аналитические запросы (период, модели, кеш)
- Корректность данных

### Тест с реальным API

```bash
php tests/Rss2Tlg/test_openrouter_metrics.php
```

Проверяет:
- Интеграцию с AI сервисами
- Автоматическую запись метрик
- Telegram уведомления

## Обслуживание

### Очистка старых метрик

```sql
-- Удалить метрики старше 3 месяцев
DELETE FROM openrouter_metrics
WHERE recorded_at < DATE_SUB(NOW(), INTERVAL 3 MONTH);
```

### Архивирование

```bash
# Дамп метрик за прошлый месяц
mysqldump -u root rss2tlg openrouter_metrics \
    --where="recorded_at >= '2025-01-01' AND recorded_at < '2025-02-01'" \
    > archives/metrics_2025_01.sql

# Удалить заархивированные
mysql -u root -e "DELETE FROM rss2tlg.openrouter_metrics 
    WHERE recorded_at >= '2025-01-01' AND recorded_at < '2025-02-01'"
```

## Миграция

Применение схемы:

```bash
mysql -u root rss2tlg < production/sql/migration_openrouter_metrics.sql
```

## Заключение

Система метрик OpenRouter обеспечивает:

- 📊 **Прозрачность** - все API вызовы логируются
- 💰 **Контроль затрат** - отслеживание стоимости
- ⚡ **Оптимизация** - анализ кеширования и производительности
- 🔍 **Debugging** - полная информация о каждом запросе

---

**Версия:** 1.0  
**Дата:** 2025-01-11  
**Автор:** RSS2TLG Team

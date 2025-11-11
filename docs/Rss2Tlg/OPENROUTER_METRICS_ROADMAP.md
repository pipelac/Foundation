# OpenRouter Детальные метрики - Roadmap

## 📋 Общий план

Полная система аналитики и отчетности по использованию OpenRouter API.

---

## ✅ Этап 1: Хранение детальных метрик (ЗАВЕРШЕН 2025-01-11)

### Компоненты
- ✅ SQL миграция `openrouter_metrics`
- ✅ `OpenRouter::parseDetailedMetrics()` - парсинг всех метрик
- ✅ `AIAnalysisTrait::recordDetailedMetrics()` - запись в БД
- ✅ `AIAnalysisTrait::getDetailedMetrics()` - получение метрик
- ✅ `AIAnalysisTrait::setMetricsDb()` - установка БД
- ✅ Автоматическая интеграция в `callAI()`

### Что хранится
- Временные метрики (generation_time, latency, moderation_latency)
- Токены (prompt, completion, cached, reasoning)
- Стоимость (usage, cache, data, file)
- Контекст (pipeline_module, batch_id, task_context)
- Полный response (JSON)

### Документация
- ✅ `docs/Rss2Tlg/OPENROUTER_METRICS_STAGE1_README.md`
- ✅ `production/apply_metrics_migration.php` - скрипт применения миграции
- ✅ `production/notify_metrics_stage1_complete.php` - уведомление в Telegram

---

## ✅ Этап 2: Методы аналитики по периодам и моделям (ЗАВЕРШЕН 2025-01-12)
> ✅ Реализовано: методы `getSummaryByPeriod()` и `getSummaryByModel()` добавлены в `AIAnalysisTrait`.

### Цель
Создать методы для получения сводной статистики по использованию API.

### Компоненты

#### 1. `getSummaryByPeriod()`

**Назначение:** Получение статистики за период (день, неделя, месяц, кастомный диапазон)

**Параметры:**
```php
protected function getSummaryByPeriod(
    string $periodType,  // 'day', 'week', 'month', 'custom'
    ?string $dateFrom,   // Для 'custom'
    ?string $dateTo,     // Для 'custom'
    ?string $pipelineModule = null  // Фильтр по модулю
): array
```

**Возвращает:**
```php
[
    'period' => '2025-01-10 - 2025-01-11',
    'total_requests' => 1500,
    'total_cost' => 0.45,  // USD
    'total_tokens' => 2500000,
    'avg_generation_time' => 2500,  // мс
    'models' => [
        'deepseek/deepseek-chat' => [
            'requests' => 1000,
            'cost' => 0.30,
            'tokens' => 1800000,
        ],
        'anthropic/claude-3.5-sonnet' => [
            'requests' => 500,
            'cost' => 0.15,
            'tokens' => 700000,
        ],
    ],
    'pipeline_modules' => [
        'SummarizationService' => [
            'requests' => 800,
            'cost' => 0.25,
        ],
        'DeduplicationService' => [
            'requests' => 700,
            'cost' => 0.20,
        ],
    ],
]
```

**SQL запросы:**
```sql
-- Базовая статистика
SELECT 
    COUNT(*) as total_requests,
    SUM(usage_total) as total_cost,
    SUM(tokens_prompt + tokens_completion) as total_tokens,
    AVG(generation_time) as avg_generation_time,
    AVG(latency) as avg_latency
FROM openrouter_metrics
WHERE recorded_at BETWEEN :date_from AND :date_to
    AND (:pipeline_module IS NULL OR pipeline_module = :pipeline_module);

-- По моделям
SELECT 
    model,
    COUNT(*) as requests,
    SUM(usage_total) as cost,
    SUM(tokens_prompt + tokens_completion) as tokens
FROM openrouter_metrics
WHERE recorded_at BETWEEN :date_from AND :date_to
GROUP BY model;

-- По pipeline модулям
SELECT 
    pipeline_module,
    COUNT(*) as requests,
    SUM(usage_total) as cost
FROM openrouter_metrics
WHERE recorded_at BETWEEN :date_from AND :date_to
GROUP BY pipeline_module;
```

**Примеры использования:**
```php
// Статистика за сегодня
$summary = $this->getSummaryByPeriod('day');

// Статистика за неделю
$summary = $this->getSummaryByPeriod('week');

// Статистика за кастомный диапазон
$summary = $this->getSummaryByPeriod('custom', '2025-01-01', '2025-01-10');

// Статистика только для Summarization
$summary = $this->getSummaryByPeriod('day', null, null, 'SummarizationService');
```

---

#### 2. `getSummaryByModel()`

**Назначение:** Группировка статистики по моделям

**Параметры:**
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
        'total_requests' => 1000,
        'total_cost' => 0.30,
        'total_tokens' => 1800000,
        'avg_generation_time' => 2000,  // мс
        'avg_tokens_per_request' => 1800,
        'avg_cost_per_request' => 0.0003,
        'cache_hits' => 200,
        'cache_rate' => 0.20,  // 20%
    ],
    'anthropic/claude-3.5-sonnet' => [
        'total_requests' => 500,
        'total_cost' => 0.15,
        'total_tokens' => 700000,
        'avg_generation_time' => 3500,
        'avg_tokens_per_request' => 1400,
        'avg_cost_per_request' => 0.0003,
        'cache_hits' => 450,
        'cache_rate' => 0.90,  // 90%
    ],
]
```

**SQL запросы:**
```sql
SELECT 
    model,
    COUNT(*) as total_requests,
    SUM(usage_total) as total_cost,
    SUM(tokens_prompt + tokens_completion) as total_tokens,
    AVG(generation_time) as avg_generation_time,
    AVG(tokens_prompt + tokens_completion) as avg_tokens_per_request,
    AVG(usage_total) as avg_cost_per_request,
    SUM(CASE WHEN native_tokens_cached > 0 THEN 1 ELSE 0 END) as cache_hits,
    AVG(CASE WHEN native_tokens_cached > 0 THEN 1 ELSE 0 END) as cache_rate
FROM openrouter_metrics
WHERE (:date_from IS NULL OR recorded_at >= :date_from)
    AND (:date_to IS NULL OR recorded_at <= :date_to)
    AND (:pipeline_module IS NULL OR pipeline_module = :pipeline_module)
GROUP BY model
ORDER BY total_cost DESC;
```

**Примеры использования:**
```php
// Все модели за весь период
$summary = $this->getSummaryByModel();

// Модели за последний месяц
$summary = $this->getSummaryByModel('2024-12-01', '2025-01-01');

// Модели только для Summarization
$summary = $this->getSummaryByModel(null, null, 'SummarizationService');
```

---

## ⏳ Этап 3: Анализ кеша и детальные отчеты

### Цель
Создать методы для анализа эффективности кеширования и генерации отчетов.

### Компоненты

#### 1. `getCacheAnalytics()`

**Назначение:** Анализ эффективности prompt caching

**Параметры:**
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
    'total_requests' => 1500,
    'requests_with_cache' => 700,
    'cache_hit_rate' => 0.467,  // 46.7%
    
    'tokens' => [
        'total_prompt' => 2000000,
        'total_cached' => 900000,
        'cache_percentage' => 0.45,  // 45%
    ],
    
    'cost_savings' => [
        'total_cost' => 0.45,
        'cost_without_cache' => 0.60,
        'savings' => 0.15,  // $0.15 сэкономлено
        'savings_percentage' => 0.25,  // 25%
    ],
    
    'by_model' => [
        'anthropic/claude-3.5-sonnet' => [
            'requests_with_cache' => 450,
            'cache_hit_rate' => 0.90,
            'tokens_cached' => 800000,
            'cost_savings' => 0.12,
        ],
        'deepseek/deepseek-chat' => [
            'requests_with_cache' => 250,
            'cache_hit_rate' => 0.25,
            'tokens_cached' => 100000,
            'cost_savings' => 0.03,
        ],
    ],
]
```

**SQL запросы:**
```sql
-- Базовая статистика кеша
SELECT 
    COUNT(*) as total_requests,
    SUM(CASE WHEN native_tokens_cached > 0 THEN 1 ELSE 0 END) as requests_with_cache,
    AVG(CASE WHEN native_tokens_cached > 0 THEN 1 ELSE 0 END) as cache_hit_rate,
    SUM(tokens_prompt) as total_prompt,
    SUM(native_tokens_cached) as total_cached,
    SUM(usage_total) as total_cost,
    SUM(usage_cache) as cache_cost_saved
FROM openrouter_metrics
WHERE (:date_from IS NULL OR recorded_at >= :date_from)
    AND (:date_to IS NULL OR recorded_at <= :date_to)
    AND (:model IS NULL OR model = :model);

-- По моделям
SELECT 
    model,
    COUNT(*) as total_requests,
    SUM(CASE WHEN native_tokens_cached > 0 THEN 1 ELSE 0 END) as requests_with_cache,
    AVG(CASE WHEN native_tokens_cached > 0 THEN 1 ELSE 0 END) as cache_hit_rate,
    SUM(native_tokens_cached) as tokens_cached,
    SUM(usage_cache) as cost_savings
FROM openrouter_metrics
WHERE native_tokens_cached > 0
GROUP BY model
ORDER BY cache_hit_rate DESC;
```

**Примеры использования:**
```php
// Анализ кеша за все время
$analytics = $this->getCacheAnalytics();

// Анализ кеша за последнюю неделю
$analytics = $this->getCacheAnalytics('2025-01-04', '2025-01-11');

// Анализ кеша для Claude
$analytics = $this->getCacheAnalytics(null, null, 'anthropic/claude-3.5-sonnet');
```

---

#### 2. `getDetailReport()`

**Назначение:** Генерация детальных отчетов в JSON или CSV

**Параметры:**
```php
protected function getDetailReport(
    array $filters,      // Фильтры (model, pipeline_module, dates, etc)
    string $format,      // 'json', 'csv', 'array'
    ?string $outputFile = null  // Путь для сохранения файла
): string|array
```

**Примеры использования:**
```php
// JSON отчет
$jsonReport = $this->getDetailReport(
    ['date_from' => '2025-01-01', 'pipeline_module' => 'SummarizationService'],
    'json'
);

// CSV отчет с сохранением в файл
$this->getDetailReport(
    ['date_from' => '2025-01-01'],
    'csv',
    '/path/to/report.csv'
);

// Массив для программной обработки
$data = $this->getDetailReport(
    ['model' => 'deepseek/deepseek-chat'],
    'array'
);
```

**Структура CSV:**
```csv
Date,Generation ID,Model,Provider,Pipeline Module,Tokens Prompt,Tokens Completion,Tokens Cached,Generation Time (ms),Cost (USD)
2025-01-10 14:30:00,gen_abc123,deepseek/deepseek-chat,DeepInfra,SummarizationService,1500,500,0,2500,0.0003
...
```

**Структура JSON:**
```json
{
    "report_date": "2025-01-11",
    "filters": {
        "date_from": "2025-01-01",
        "pipeline_module": "SummarizationService"
    },
    "summary": {
        "total_requests": 1000,
        "total_cost": 0.30,
        "total_tokens": 1800000
    },
    "details": [
        {
            "date": "2025-01-10 14:30:00",
            "generation_id": "gen_abc123",
            "model": "deepseek/deepseek-chat",
            "provider": "DeepInfra",
            "pipeline_module": "SummarizationService",
            "tokens_prompt": 1500,
            "tokens_completion": 500,
            "tokens_cached": 0,
            "generation_time": 2500,
            "cost": 0.0003
        }
    ]
}
```

---

## 📅 Timeline

- **Этап 1:** ✅ Завершен (2025-01-11)
- **Этап 2:** ✅ Завершен (2025-01-12)
- **Этап 3:** ⏳ По запросу пользователя

---

## 🎯 Acceptance Criteria

### Этап 2
- ✅ `getSummaryByPeriod()` возвращает статистику за период
- ✅ Поддержка типов периодов: day, week, month, custom
- ✅ Группировка по моделям и pipeline модулям
- ✅ `getSummaryByModel()` возвращает детальную статистику по каждой модели
- ✅ Расчет средних значений (время, стоимость, токены)
- ✅ Информация о cache hit rate

### Этап 3
- ✅ `getCacheAnalytics()` показывает эффект кеширования
- ✅ Расчет cost savings от кеширования
- ✅ Группировка по моделям с cache hit rate
- ✅ `getDetailReport()` генерирует отчеты в JSON/CSV
- ✅ Сохранение отчетов в файл
- ✅ Программная обработка данных

---

## 📚 Документация

- ✅ `OPENROUTER_METRICS_STAGE1_README.md` - Этап 1 (полная документация)
- ⏳ `OPENROUTER_METRICS_STAGE2_README.md` - Этап 2 (будет создана)
- ⏳ `OPENROUTER_METRICS_STAGE3_README.md` - Этап 3 (будет создана)

---

## 💡 Примечания

1. Все методы работают через трейт `AIAnalysisTrait`
2. Опциональная зависимость от `metricsDb` (graceful degradation)
3. Полное логирование всех операций
4. SQL запросы оптимизированы с индексами
5. Поддержка фильтрации по всем ключевым полям

---

## ✅ Готовность

**Этап 1:** 🟢 Готов к использованию  
**Этап 2:** 🟡 Ожидает запроса  
**Этап 3:** 🟡 Ожидает запроса

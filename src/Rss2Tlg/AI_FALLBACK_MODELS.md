# AI Analysis с Fallback моделями

## Описание

Модуль AI Analysis теперь поддерживает автоматический fallback между несколькими моделями AI. Если первая модель не отвечает или возвращает ошибку, система автоматически переключается на следующую модель из списка.

## Преимущества

1. **Повышенная надежность** — если одна модель недоступна, используется другая
2. **Оптимизация затрат** — можно использовать бесплатные модели с fallback на платные
3. **Гибкая настройка** — разные списки моделей для разных источников RSS
4. **Автоматическое восстановление** — система сама выбирает рабочую модель

## Конфигурация

### Глобальная конфигурация (config/rss2tlg_stress_test.json)

```json
{
  "ai_analysis": {
    "enabled": true,
    "models": [
      "deepseek/deepseek-chat-v3.1:free",
      "qwen/qwen3-235b-a22b:free",
      "z-ai/glm-4.5-air:free",
      "deepseek/deepseek-r1-0528-qwen3-8b:free",
      "deepseek/deepseek-r1-0528:free"
    ],
    "timeout": 60,
    "retries": 2,
    "retry_delay_ms": 1000
  }
}
```

**Параметры:**
- `enabled` — включить/выключить AI-анализ
- `models` — список моделей в порядке приоритета
- `timeout` — таймаут на запрос к модели (в секундах)
- `retries` — количество повторных попыток для каждой модели
- `retry_delay_ms` — задержка между попытками (в миллисекундах)

### Конфигурация для отдельного источника RSS

В конфигурации каждого источника можно переопределить список моделей:

```json
{
  "feeds": [
    {
      "id": 1,
      "url": "https://example.com/rss",
      "title": "Example News",
      "prompt_id": "INoT_v1",
      "ai_models": null,
      ...
    },
    {
      "id": 2,
      "url": "https://tech-news.com/rss",
      "title": "Tech News",
      "prompt_id": "INoT_v1",
      "ai_models": [
        "deepseek/deepseek-chat-v3.1:free",
        "qwen/qwen3-235b-a22b:free"
      ],
      ...
    }
  ]
}
```

**Логика:**
- Если `ai_models` = `null` → используются модели из секции `ai_analysis.models`
- Если `ai_models` = `[...]` → используются модели из этого массива
- Если `ai_models` отсутствует → используются модели по умолчанию

## Использование

### Метод analyzeWithFallback()

```php
use App\Rss2Tlg\AIAnalysisService;

$analysisService = new AIAnalysisService(
    $promptManager,
    $analysisRepository,
    $openRouter,
    $db,
    $logger
);

// Анализ с автоматическим fallback
$models = [
    'deepseek/deepseek-chat-v3.1:free',
    'qwen/qwen3-235b-a22b:free',
    'z-ai/glm-4.5-air:free'
];

$analysis = $analysisService->analyzeWithFallback(
    $item,           // Данные новости
    'INoT_v1',       // ID промпта
    $models,         // Список моделей (или null для использования из конфига)
    $options         // Дополнительные опции
);

if ($analysis !== null) {
    echo "Анализ выполнен успешно!\n";
    echo "Модель: {$analysis['model_used']}\n";
} else {
    echo "Все модели не смогли проанализировать новость\n";
}
```

### Логика работы

1. **Попытка с первой моделью**
   - Отправляем запрос к первой модели из списка
   - Если успех → возвращаем результат
   - Если ошибка → переходим к следующей модели

2. **Задержка между попытками**
   - После неудачной попытки делается задержка (retry_delay_ms)
   - По умолчанию: 1000 мс

3. **Логирование**
   - Каждая попытка логируется
   - Записывается информация о модели, попытке, ошибке
   - В метрики добавляется статистика по моделям

4. **Результат**
   - При успехе → возвращается результат анализа
   - При неудаче всех моделей → возвращается `null`

## Метрики

Сервис собирает расширенную статистику:

```php
$metrics = $analysisService->getMetrics();

print_r($metrics);
// [
//     'total_analyzed' => 100,
//     'successful' => 98,
//     'failed' => 2,
//     'total_tokens' => 25000,
//     'total_time_ms' => 45000,
//     'cache_hits' => 85,
//     'fallback_used' => 12,
//     'model_attempts' => [
//         'deepseek/deepseek-chat-v3.1:free' => 100,
//         'qwen/qwen3-235b-a22b:free' => 12,
//         'z-ai/glm-4.5-air:free' => 2,
//     ]
// ]
```

**Новые метрики:**
- `fallback_used` — количество случаев, когда использовался fallback
- `model_attempts` — количество попыток для каждой модели

## Рекомендуемые модели

### Бесплатные модели (free tier)

1. **deepseek/deepseek-chat-v3.1:free**
   - Качество: отличное
   - Скорость: быстрая (1-3 сек)
   - Лимиты: есть rate limiting
   - **Рекомендация:** первая в списке

2. **qwen/qwen3-235b-a22b:free**
   - Качество: очень хорошее
   - Скорость: средняя (2-4 сек)
   - Лимиты: есть rate limiting
   - **Рекомендация:** вторая в списке

3. **z-ai/glm-4.5-air:free**
   - Качество: хорошее
   - Скорость: средняя (2-5 сек)
   - Лимиты: есть rate limiting
   - **Рекомендация:** третья в списке

4. **deepseek/deepseek-r1-0528-qwen3-8b:free**
   - Качество: хорошее
   - Скорость: быстрая (1-3 сек)
   - Лимиты: есть rate limiting
   - **Рекомендация:** запасной вариант

5. **deepseek/deepseek-r1-0528:free**
   - Качество: хорошее
   - Скорость: средняя (2-4 сек)
   - Лимиты: есть rate limiting
   - **Рекомендация:** запасной вариант

### Платные модели (для критичных источников)

```json
"ai_models": [
  "deepseek/deepseek-chat",
  "anthropic/claude-3.5-sonnet",
  "openai/gpt-4-turbo"
]
```

## Сценарии использования

### Сценарий 1: Все источники используют одинаковые модели

```json
{
  "ai_analysis": {
    "models": [
      "deepseek/deepseek-chat-v3.1:free",
      "qwen/qwen3-235b-a22b:free"
    ]
  },
  "feeds": [
    {
      "id": 1,
      "ai_models": null  // Используются модели из ai_analysis.models
    },
    {
      "id": 2,
      "ai_models": null  // Используются модели из ai_analysis.models
    }
  ]
}
```

### Сценарий 2: Критичные источники используют платные модели

```json
{
  "ai_analysis": {
    "models": [
      "deepseek/deepseek-chat-v3.1:free",
      "qwen/qwen3-235b-a22b:free"
    ]
  },
  "feeds": [
    {
      "id": 1,
      "title": "Обычный источник",
      "ai_models": null  // Бесплатные модели
    },
    {
      "id": 2,
      "title": "VIP источник",
      "ai_models": [
        "openai/gpt-4-turbo",
        "anthropic/claude-3.5-sonnet"
      ]
    }
  ]
}
```

### Сценарий 3: Смешанная стратегия

```json
{
  "ai_analysis": {
    "models": [
      "deepseek/deepseek-chat-v3.1:free",
      "qwen/qwen3-235b-a22b:free",
      "deepseek/deepseek-chat"  // Платный fallback
    ]
  },
  "feeds": [
    {
      "id": 1,
      "ai_models": null  // Используется mix из бесплатных и платной
    }
  ]
}
```

## Обработка ошибок

### Типы ошибок

1. **Сетевые ошибки** (timeout, connection refused)
   - Автоматически переключается на следующую модель
   - Логируется как network error

2. **Ошибки API** (rate limiting, invalid API key)
   - Автоматически переключается на следующую модель
   - Логируется как API error

3. **Ошибки парсинга** (некорректный JSON ответ)
   - Автоматически переключается на следующую модель
   - Логируется как parsing error

### Логирование ошибок

```
[DEBUG] Попытка анализа с моделью: deepseek/deepseek-chat-v3.1:free (attempt 1/3)
[ERROR] Ошибка при анализе с моделью deepseek/deepseek-chat-v3.1:free: Rate limit exceeded
[DEBUG] Переход к следующей модели: qwen/qwen3-235b-a22b:free (delay: 1000ms)
[DEBUG] Попытка анализа с моделью: qwen/qwen3-235b-a22b:free (attempt 2/3)
[DEBUG] Fallback успешен: модель qwen/qwen3-235b-a22b:free
```

## Мониторинг

### SQL запросы для анализа

```sql
-- Статистика по использованным моделям
SELECT 
    model_used,
    COUNT(*) as count,
    AVG(processing_time_ms) as avg_time,
    AVG(tokens_used) as avg_tokens
FROM rss2tlg_ai_analysis
WHERE analysis_status = 'success'
GROUP BY model_used
ORDER BY count DESC;

-- Источники с высоким процентом ошибок (нужен fallback)
SELECT 
    feed_id,
    COUNT(CASE WHEN analysis_status = 'success' THEN 1 END) as success,
    COUNT(CASE WHEN analysis_status = 'failed' THEN 1 END) as failed,
    ROUND(COUNT(CASE WHEN analysis_status = 'failed' THEN 1 END) * 100.0 / COUNT(*), 2) as error_rate
FROM rss2tlg_ai_analysis
GROUP BY feed_id
HAVING error_rate > 10
ORDER BY error_rate DESC;
```

## Best Practices

1. **Порядок моделей**
   - Начинайте с бесплатных моделей
   - Заканчивайте платными моделями (как последний резерв)

2. **Количество моделей**
   - Оптимально: 3-5 моделей
   - Минимум: 2 модели
   - Максимум: не более 7 (чтобы не увеличивать латентность)

3. **Задержки**
   - `retry_delay_ms`: 1000-2000 мс (оптимально)
   - Слишком малые задержки → rate limiting
   - Слишком большие задержки → медленная обработка

4. **Мониторинг**
   - Регулярно проверяйте метрику `fallback_used`
   - Если fallback используется часто (>20%) → проверьте первую модель
   - Анализируйте `model_attempts` для оптимизации списка

5. **Тестирование**
   - Проверяйте все модели перед продакшеном
   - Убедитесь, что API ключи валидны
   - Тестируйте с реальными данными

## Troubleshooting

### Проблема: Все модели возвращают ошибки

**Решение:**
1. Проверьте API ключ OpenRouter
2. Проверьте лимиты использования
3. Проверьте сетевое соединение
4. Попробуйте другие модели

### Проблема: Fallback используется слишком часто

**Решение:**
1. Проверьте rate limits первой модели
2. Увеличьте `retry_delay_ms`
3. Добавьте больше моделей в список
4. Переместите более надежную модель на первое место

### Проблема: Медленная обработка

**Решение:**
1. Уменьшите `retry_delay_ms`
2. Уменьшите количество моделей в fallback
3. Используйте более быстрые модели
4. Оптимизируйте промпты (уменьшите размер)

## Changelog

### v1.1.0 (2024-11-04)
- ✅ Добавлена поддержка fallback моделей
- ✅ Добавлен метод `analyzeWithFallback()`
- ✅ Добавлена конфигурация `ai_analysis` в config
- ✅ Добавлено поле `ai_models` в конфигурацию источников
- ✅ Расширены метрики: `fallback_used`, `model_attempts`
- ✅ Обновлен демо-пример

## Автор

Разработано как часть проекта **Rss2Tlg** (RSS to Telegram Aggregator).

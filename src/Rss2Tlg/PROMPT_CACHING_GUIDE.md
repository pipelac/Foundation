# Руководство по кешированию промптов в OpenRouter

## Проблема

При анализе множества новостей через OpenRouter API мы отправляем одинаковый system prompt каждый раз, но получаем `cached_tokens: 0`. Это означает, что кеширование не работает, и мы платим за повторную обработку одного и того же контекста.

## Решение

### 1. App ID для идентификации проекта

OpenRouter требует идентификацию приложения через HTTP headers:
- `HTTP-Referer` - URL приложения (например, `https://RSS2TLG-E2E-Test`)
- `X-Title` - название приложения (например, `RSS2TLG-E2E-Test`)

**Что исправлено:**

```php
// src/BaseUtils/OpenRouter.class.php

private function buildHeaders(): array
{
    return [
        'Authorization' => 'Bearer ' . $this->apiKey,
        'HTTP-Referer' => 'https://' . $this->appName,  // ✅ Добавлен
        'X-Title' => $this->appName,                     // ✅ Добавлен
    ];
}
```

**Конфигурация:**

```json
{
  "openrouter": {
    "api_key": "sk-or-v1-...",
    "app_name": "RSS2TLG-E2E-Test",  // ✅ Имя проекта
    ...
  }
}
```

Теперь в веб-интерфейсе OpenRouter в столбце **App** будет отображаться `RSS2TLG-E2E-Test` вместо `Unknown`.

### 2. Multi-Message Format для кеширования

OpenRouter кеширует **только** system messages при использовании нативного multi-message формата:

```json
{
  "model": "qwen/qwen-2.5-72b-instruct",
  "messages": [
    {
      "role": "system",
      "content": "Ты - аналитик новостей..."  // ✅ КЕШИРУЕТСЯ
    },
    {
      "role": "user", 
      "content": "Проанализируй новость..."   // ❌ НЕ КЕШИРУЕТСЯ
    }
  ]
}
```

**Что было (❌ НЕПРАВИЛЬНО):**

```php
// AIAnalysisService старый код

$prompt = "=== SYSTEM PROMPT (CACHEABLE) ===\nТы - аналитик...\n=== USER INPUT ===\nПроанализируй...";

$response = $openRouter->text2textWithMetrics($model, $prompt, $options);
// Результат: cached_tokens = 0
```

System и user промпты смешивались в один текст, OpenRouter не мог определить, что кешировать.

**Что стало (✅ ПРАВИЛЬНО):**

```php
// AIAnalysisService новый код

$messages = [
    ['role' => 'system', 'content' => 'Ты - аналитик...'],
    ['role' => 'user', 'content' => 'Проанализируй...']
];

$response = $openRouter->chatWithMessages($model, $messages, $options);
// Результат: cached_tokens > 0 (после первого запроса)
```

### 3. Новый метод chatWithMessages()

Добавлен метод `OpenRouter::chatWithMessages()` для работы с multi-message форматом:

```php
/**
 * Отправляет запрос с поддержкой multi-message и кеширования
 *
 * @param string $model Модель ИИ
 * @param array<int, array<string, string>> $messages Массив сообщений
 * @param array<string, mixed> $options Дополнительные параметры
 * @return array<string, mixed> Полный ответ с метриками (включая cached_tokens)
 */
public function chatWithMessages(string $model, array $messages, array $options = []): array
{
    // ...
    return [
        'content' => $response,
        'usage' => [
            'prompt_tokens' => 3808,
            'completion_tokens' => 566,
            'total_tokens' => 4374,
            'cached_tokens' => 3200,  // ✅ Будет > 0 при кешировании
        ],
        'model' => $model,
        'id' => $generationId,
        'created' => $timestamp,
    ];
}
```

### 4. Обновленный AIAnalysisService

```php
// src/Rss2Tlg/AIAnalysisService.php

private function sendRequestToOpenRouter(string $model, array $options): ?string
{
    $messages = $options['messages'] ?? [];
    unset($options['messages']);

    // ✅ Используем chatWithMessages вместо text2textWithMetrics
    $fullResponse = $this->openRouter->chatWithMessages($model, $messages, $options);
    
    $this->lastApiResponse = $fullResponse;
    return $fullResponse['content'];
}
```

## Условия работы кеширования

1. **Модели с поддержкой кеширования:**
   - Claude 3.5 Sonnet (лучшая поддержка)
   - GPT-4 Turbo/GPT-4o
   - Qwen 2.5 72B Instruct (частичная поддержка)
   - DeepSeek V3 (частичная поддержка)

2. **Требования:**
   - System message должен быть **одинаковым** между запросами
   - Минимальная длина для кеширования: ~1024 токена
   - Запросы должны идти с одного API ключа
   - Время между запросами: до 5-10 минут

3. **Структура запроса:**
   ```json
   {
     "messages": [
       {"role": "system", "content": "..."},  // КЕШИРУЕТСЯ
       {"role": "user", "content": "..."}     // НЕ КЕШИРУЕТСЯ
     ]
   }
   ```

## Проверка кеширования

### В коде

```php
$response = $aiAnalysisService->analyze($item, $promptId, $model);

// Получаем метрики
$metrics = $aiAnalysisService->getLastApiMetrics();

if ($metrics['usage']['cached_tokens'] > 0) {
    echo "✅ Кеш работает! Кешировано: {$metrics['usage']['cached_tokens']} токенов\n";
} else {
    echo "❌ Кеш не работает (первый запрос или модель не поддерживает)\n";
}
```

### В веб-интерфейсе OpenRouter

1. Перейдите на https://openrouter.ai/activity
2. Найдите свои запросы
3. Проверьте столбцы:
   - **App** - должно быть `RSS2TLG-E2E-Test` (не `Unknown`)
   - **Cached Tokens** - должно быть > 0 после первого запроса

### В логах

```
📊 Модель: qwen/qwen-2.5-72b-instruct
📊 Токены: prompt=3808, completion=566, total=4374
💾 Кешировано: 3200 токенов  // ✅ Кеш работает!
```

## Экономия

### Без кеширования
- 5 анализов × 3800 токенов = 19,000 prompt tokens
- Стоимость: 19,000 × $0.15/1M = $0.00285

### С кешированием
- 1-й анализ: 3800 prompt tokens (полная стоимость)
- 2-5 анализы: 600 токенов каждый (только user content)
- Итого: 3800 + (4 × 600) = 6,200 prompt tokens
- Стоимость: 6,200 × $0.15/1M = $0.00093

**Экономия: 67.4%** на prompt tokens!

## Типичные ошибки

### ❌ Смешивание system и user в одном промпте

```php
// НЕПРАВИЛЬНО
$prompt = "System: " . $systemPrompt . "\nUser: " . $userContent;
$response = $openRouter->text2textWithMetrics($model, $prompt);
// cached_tokens = 0
```

### ✅ Раздельные сообщения

```php
// ПРАВИЛЬНО
$messages = [
    ['role' => 'system', 'content' => $systemPrompt],
    ['role' => 'user', 'content' => $userContent]
];
$response = $openRouter->chatWithMessages($model, $messages);
// cached_tokens > 0 (со 2-го запроса)
```

### ❌ Отсутствие App ID

```php
// НЕПРАВИЛЬНО - в веб-интерфейсе будет "Unknown"
$config = [
    'api_key' => '...',
    // app_name НЕ указан
];
```

### ✅ App ID указан

```php
// ПРАВИЛЬНО - в веб-интерфейсе будет "RSS2TLG-E2E-Test"
$config = [
    'api_key' => '...',
    'app_name' => 'RSS2TLG-E2E-Test',  // ✅
];
```

## Тестирование

Запустите E2E тест дважды подряд:

```bash
# Первый запуск - кеш заполняется
php tests/Rss2Tlg/tests_rss2tlg_e2e_v5.php

# Второй запуск (в течение 5 минут) - кеш используется
php tests/Rss2Tlg/tests_rss2tlg_e2e_v5.php
```

**Ожидаемый результат:**

Первый запуск:
```
💾 Кешировано: 0 токенов (первый запрос)
💾 Кешировано: 0 токенов
💾 Кешировано: 0 токенов
```

Второй запуск:
```
💾 Кешировано: 3200 токенов ✅
💾 Кешировано: 3200 токенов ✅
💾 Кешировано: 3200 токенов ✅
```

## Ссылки

- [OpenRouter API Documentation](https://openrouter.ai/docs)
- [OpenRouter Prompt Caching](https://openrouter.ai/docs/features/prompt-caching)
- [Models with Caching Support](https://openrouter.ai/models?supported_parameters=prompt_caching)

---

*Последнее обновление: 2025-11-07*

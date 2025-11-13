# OpenRouterResponseAnalysis - Утилиты для работы с ответами AI

## Описание

`OpenRouterResponseAnalysis` - минималистичный базовый класс со статическими методами для работы с ответами от OpenRouter API и других AI сервисов.

**Основные возможности:**
- 🔍 Извлечение JSON из ответов AI (с обработкой markdown блоков)
- 💬 Подготовка сообщений для запросов (с кешированием для Claude)
- ⚙️ Подготовка опций для запросов
- ✅ Валидация конфигурации AI модулей

**Особенности:**
- ⚡ Статические методы (не требует создания экземпляра)
- 🪶 Без зависимостей от БД
- 📝 Опциональное логирование
- 🔄 Переиспользуемый в любых проектах

---

## Быстрый старт

### Подключение

```php
use App\Component\OpenRouterResponseAnalysis;
```

### Извлечение JSON из ответа AI

```php
// Ответ от AI с markdown блоком
$response = <<<'RESPONSE'
Вот результат анализа:

```json
{
    "status": "success",
    "data": [1, 2, 3],
    "confidence": 0.95
}
```

Анализ завершен.
RESPONSE;

// Парсим JSON
$data = OpenRouterResponseAnalysis::parseJSONResponse($response);

// Используем данные
echo "Status: {$data['status']}\n";
echo "Confidence: {$data['confidence']}\n";
print_r($data['data']);
```

### Подготовка сообщений для Claude (с кешированием!)

```php
$messages = OpenRouterResponseAnalysis::prepareMessages(
    systemPrompt: 'You are an expert news analyst',
    userPrompt: 'Analyze this article...',
    model: 'anthropic/claude-3.5-sonnet'
);

// Результат содержит cache_control для экономии токенов
// Можно использовать напрямую с OpenRouter API
```

### Подготовка опций из конфигурации

```php
$modelConfig = [
    'model' => 'openai/gpt-4',
    'max_tokens' => 2000,
    'temperature' => 0.7
];

$options = OpenRouterResponseAnalysis::prepareOptions($modelConfig);

// Результат готов для использования с OpenRouter API
```

---

## API Методы

### extractJSON(string $content): string

Извлекает чистый JSON из ответа AI.

**Поддерживаемые форматы:**
- JSON в markdown блоках: ` ```json...``` ` или ` ```...``` `
- JSON объект: `{...}`
- JSON массив: `[...]`
- Текст с префиксом перед JSON

**Пример:**
```php
$json = OpenRouterResponseAnalysis::extractJSON($response);
```

---

### parseJSONResponse(string $content): ?array

Извлекает и парсит JSON с обработкой ошибок.

**Возвращает:**
- `array` - успешно распарсенный JSON
- `null` - ошибка парсинга

**Пример:**
```php
$data = OpenRouterResponseAnalysis::parseJSONResponse($response);
if ($data !== null) {
    // Используем данные
}
```

---

### prepareMessages(string $systemPrompt, string $userPrompt, string $model): array

Подготавливает сообщения для запроса.

**Автоматически:**
- Добавляет `cache_control` для моделей Claude (экономия токенов!)
- Использует правильный формат для каждой модели

**Пример:**
```php
$messages = OpenRouterResponseAnalysis::prepareMessages(
    'You are a helpful assistant',
    'Hello!',
    'anthropic/claude-3.5-sonnet'
);
```

---

### prepareOptions($modelConfig, ?array $extraOptions = null): array

Подготавливает опции для запроса.

**Параметры:**
- `$modelConfig` - массив конфигурации или строка с названием модели
- `$extraOptions` - дополнительные опции для переопределения

**Пример:**
```php
$options = OpenRouterResponseAnalysis::prepareOptions([
    'max_tokens' => 2000,
    'temperature' => 0.7
]);
```

---

### validateAIConfig(array $config): array

Валидирует конфигурацию AI модулей.

**Проверяет:**
- Наличие массива моделей
- Существование файла промпта

**Пример:**
```php
$validatedConfig = OpenRouterResponseAnalysis::validateAIConfig([
    'models' => ['openai/gpt-4', 'anthropic/claude-3.5-sonnet'],
    'prompt_file' => '/path/to/prompt.txt'
]);
```

---

## Дополнительные утилиты

### detectJSONInText(string $content): ?array

Обнаруживает JSON в произвольном тексте.

**Пример:**
```php
$text = "Here is data: {\"key\": \"value\"} - ready!";
$json = OpenRouterResponseAnalysis::detectJSONInText($text);
```

---

### cleanMarkdown(string $content): string

Очищает markdown блоки из текста.

**Пример:**
```php
$clean = OpenRouterResponseAnalysis::cleanMarkdown($markdownText);
```

---

### extractCodeBlock(string $content, string $language = ''): ?string

Извлекает code block по языку.

**Пример:**
```php
// Извлечь JSON блок
$jsonBlock = OpenRouterResponseAnalysis::extractCodeBlock($text, 'json');

// Извлечь любой блок
$anyBlock = OpenRouterResponseAnalysis::extractCodeBlock($text);
```

---

## Полный пример использования

```php
use App\Component\OpenRouter;
use App\Component\OpenRouterResponseAnalysis;
use App\Component\Logger;

// 1. Настройка логирования (опционально)
$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'file_name' => 'openrouter.log'
]);
OpenRouterResponseAnalysis::setLogger($logger);

// 2. Инициализация OpenRouter
$openRouter = new OpenRouter([
    'api_key' => 'your-api-key',
    'app_name' => 'MyApp'
], $logger);

// 3. Подготовка запроса
$systemPrompt = 'You are a helpful assistant that responds in JSON';
$userPrompt = 'Analyze this text and return sentiment';
$model = 'anthropic/claude-3.5-sonnet';

$messages = OpenRouterResponseAnalysis::prepareMessages(
    $systemPrompt,
    $userPrompt,
    $model
);

$options = OpenRouterResponseAnalysis::prepareOptions([
    'max_tokens' => 2000,
    'temperature' => 0.3
]);

// 4. Отправка запроса
$response = $openRouter->chatWithMessages($model, $messages, $options);

// 5. Обработка ответа
$data = OpenRouterResponseAnalysis::parseJSONResponse($response['content']);

if ($data !== null) {
    echo "Sentiment: {$data['sentiment']}\n";
    echo "Confidence: {$data['confidence']}\n";
} else {
    echo "Failed to parse response\n";
}
```

---

## Интеграция с AIAnalysisTrait

Класс используется в `AIAnalysisTrait` для делегирования базовых операций:

```php
trait AIAnalysisTrait
{
    protected function prepareMessages(...): array
    {
        return OpenRouterResponseAnalysis::prepareMessages(...);
    }
    
    protected function prepareOptions(...): array
    {
        return OpenRouterResponseAnalysis::prepareOptions(...);
    }
    
    protected function extractJSON(...): string
    {
        return OpenRouterResponseAnalysis::extractJSON(...);
    }
    
    protected function validateAIConfig(...): array
    {
        return OpenRouterResponseAnalysis::validateAIConfig(...);
    }
}
```

---

## Преимущества

### 1. Автоматическое кеширование для Claude
Метод `prepareMessages()` автоматически добавляет `cache_control` для моделей Claude, что экономит токены и деньги при повторных запросах.

### 2. Обработка разных форматов
Не нужно беспокоиться о том, в каком формате AI вернет JSON - класс обработает все популярные варианты.

### 3. Безопасный парсинг
Метод `parseJSONResponse()` возвращает `null` при ошибке, а не бросает исключение - удобно для обработки.

### 4. Без состояния (stateless)
Статические методы не хранят состояние - можно использовать в многопоточной среде.

### 5. Минимальные зависимости
Только Logger (опционально) - можно использовать в любом проекте.

---

## Сравнение с AIAnalysisTrait

| Функция | AIAnalysisTrait | OpenRouterResponseAnalysis |
|---------|----------------|---------------------------|
| Парсинг JSON | ✅ (делегирует) | ✅ |
| Подготовка сообщений | ✅ (делегирует) | ✅ |
| Подготовка опций | ✅ (делегирует) | ✅ |
| Fallback между моделями | ✅ | ❌ |
| Retry механизм | ✅ | ❌ |
| Запись метрик в БД | ✅ | ❌ |
| Аналитика | ✅ | ❌ |
| Зависимость от БД | Да | Нет |
| Использование | Trait для pipeline | Статические методы |

**Вывод**: 
- `OpenRouterResponseAnalysis` - чистый базовый класс для парсинга и подготовки данных
- `AIAnalysisTrait` - высокоуровневая обертка с БД и аналитикой для конкретного проекта

---

## FAQ

### Нужно ли создавать экземпляр класса?
Нет, все методы статические.

### Можно ли использовать без Logger?
Да, логирование опционально.

### Работает ли с другими AI API (не только OpenRouter)?
Да, методы универсальны и работают с любыми API, возвращающими JSON.

### Что делать, если JSON не парсится?
`parseJSONResponse()` вернет `null` - проверяйте результат.

### Как добавить поддержку нового формата ответа?
Расширьте метод `extractJSON()` новым паттерном регулярного выражения.

---

## См. также

- **Детальный анализ**: `ANALYSIS_OpenRouterResponseAnalysis.md`
- **Краткая сводка**: `SUMMARY_OpenRouterResponseAnalysis.md`
- **Примеры**: `docs/examples/OpenRouterResponseAnalysis_examples.php`
- **Рефакторинг AIAnalysisTrait**: `docs/Rss2Tlg/REFACTORING_AIAnalysisTrait.md`
- **OpenRouter API**: `OPENROUTER.md`
- **OpenRouter метрики**: `OPENROUTER_METRICS.md`

---

**Версия:** 1.0  
**Расположение:** `src/BaseUtils/OpenRouterResponseAnalysis.class.php`  
**Namespace:** `App\Component`

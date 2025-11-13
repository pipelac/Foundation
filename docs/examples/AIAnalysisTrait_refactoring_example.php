<?php

declare(strict_types=1);

/**
 * Пример использования AIAnalysisTrait после рефакторинга
 * 
 * Демонстрирует как трейт теперь использует базовый класс OpenRouterResponseAnalysis
 * для делегирования базовых операций парсинга и подготовки данных.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\OpenRouter;
use App\Component\OpenRouterResponseAnalysis;

// ============================================================================
// ПРИМЕР 1: Прямое использование OpenRouterResponseAnalysis (без AIAnalysisTrait)
// ============================================================================

echo "=== ПРИМЕР 1: Базовое использование OpenRouterResponseAnalysis ===\n\n";

// Настройка логирования (опционально)
$logger = new Logger([
    'directory' => __DIR__ . '/../../logs',
    'file_name' => 'openrouter_example.log'
]);
OpenRouterResponseAnalysis::setLogger($logger);

// Инициализация OpenRouter
$openRouter = new OpenRouter([
    'api_key' => getenv('OPENROUTER_API_KEY'),
    'app_name' => 'ExampleApp'
], $logger);

// Подготовка запроса
$systemPrompt = 'You are a helpful assistant that responds in JSON format';
$userPrompt = 'Analyze this sentence and return sentiment: "I love this product!"';
$model = 'anthropic/claude-3.5-sonnet';

// Используем OpenRouterResponseAnalysis для подготовки
$messages = OpenRouterResponseAnalysis::prepareMessages(
    $systemPrompt,
    $userPrompt,
    $model
);

$options = OpenRouterResponseAnalysis::prepareOptions([
    'max_tokens' => 500,
    'temperature' => 0.3
]);

echo "Сообщения подготовлены:\n";
echo "- Модель: {$model}\n";
echo "- Кеширование для Claude: " . (str_contains($model, 'claude') ? 'Да' : 'Нет') . "\n";
echo "- Опции: max_tokens={$options['max_tokens']}, temperature={$options['temperature']}\n\n";

// Отправка запроса
try {
    $response = $openRouter->chatWithMessages($model, $messages, $options);
    
    // Парсинг ответа с использованием OpenRouterResponseAnalysis
    $data = OpenRouterResponseAnalysis::parseJSONResponse($response['content']);
    
    if ($data !== null) {
        echo "✅ Ответ успешно распарсен:\n";
        print_r($data);
    } else {
        echo "❌ Не удалось распарсить JSON ответ\n";
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка при обращении к API: {$e->getMessage()}\n";
}

echo "\n" . str_repeat('=', 80) . "\n\n";

// ============================================================================
// ПРИМЕР 2: Использование AIAnalysisTrait в Pipeline модуле
// ============================================================================

echo "=== ПРИМЕР 2: Использование AIAnalysisTrait ===\n\n";

// Пример класса, использующего AIAnalysisTrait
use App\Rss2Tlg\Pipeline\AIAnalysisTrait;

class ExamplePipelineModule
{
    use AIAnalysisTrait;
    
    protected OpenRouter $openRouter;
    protected ?MySQL $metricsDb = null;
    protected array $config;
    protected array $metrics;
    protected ?Logger $logger;
    
    public function __construct(OpenRouter $openRouter, array $config, ?Logger $logger = null)
    {
        $this->openRouter = $openRouter;
        $this->config = $config;
        $this->logger = $logger;
        $this->metrics = [
            'total_processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'model_attempts' => [],
        ];
    }
    
    public function analyze(string $text): ?array
    {
        $systemPrompt = 'You are a helpful assistant that analyzes text and returns JSON';
        $userPrompt = "Analyze this text: {$text}";
        
        // AIAnalysisTrait использует OpenRouterResponseAnalysis внутри
        return $this->analyzeWithFallback($systemPrompt, $userPrompt);
    }
    
    protected function logDebug(string $message, array $context = []): void
    {
        $this->logger?->debug($message, $context);
    }
    
    protected function logWarning(string $message, array $context = []): void
    {
        $this->logger?->warning($message, $context);
    }
    
    protected function logError(string $message, array $context = []): void
    {
        $this->logger?->error($message, $context);
    }
    
    protected function incrementMetric(string $key, int $value = 1): void
    {
        if (!isset($this->metrics[$key])) {
            $this->metrics[$key] = 0;
        }
        $this->metrics[$key] += $value;
    }
}

// Использование
$config = [
    'enabled' => true,
    'models' => [
        [
            'model' => 'anthropic/claude-3.5-sonnet',
            'max_tokens' => 2000,
            'temperature' => 0.3
        ],
        [
            'model' => 'openai/gpt-4-turbo',
            'max_tokens' => 2000,
            'temperature' => 0.3
        ]
    ],
    'fallback_strategy' => 'sequential',
    'retry_count' => 2,
    'prompt_file' => __DIR__ . '/prompts/analysis.txt'
];

try {
    // Валидация конфигурации использует OpenRouterResponseAnalysis
    $validatedConfig = OpenRouterResponseAnalysis::validateAIConfig($config);
    
    echo "✅ Конфигурация валидирована:\n";
    echo "- Моделей: " . count($validatedConfig['models']) . "\n";
    echo "- Стратегия: {$validatedConfig['fallback_strategy']}\n";
    echo "- Промпт файл: {$validatedConfig['prompt_file']}\n\n";
    
    // Создание модуля
    $module = new ExamplePipelineModule($openRouter, $config, $logger);
    
    // Анализ текста с fallback между моделями
    $result = $module->analyze('This is an amazing product that I highly recommend!');
    
    if ($result !== null) {
        echo "✅ Анализ успешно выполнен:\n";
        echo "- Модель: {$result['model_used']}\n";
        echo "- Токенов использовано: {$result['tokens_used']}\n";
        echo "- Cache hit: " . ($result['cache_hit'] ? 'Да' : 'Нет') . "\n";
        echo "- Данные анализа:\n";
        print_r($result['analysis_data']);
    } else {
        echo "❌ Не удалось выполнить анализ\n";
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка: {$e->getMessage()}\n";
}

echo "\n" . str_repeat('=', 80) . "\n\n";

// ============================================================================
// ПРИМЕР 3: Различные форматы ответов AI
// ============================================================================

echo "=== ПРИМЕР 3: Обработка различных форматов ответов ===\n\n";

// Формат 1: JSON в markdown блоке
$response1 = <<<'RESPONSE'
Here is the analysis:

```json
{
    "sentiment": "positive",
    "score": 0.95,
    "confidence": "high"
}
```

Analysis complete.
RESPONSE;

echo "Формат 1 (JSON в markdown):\n";
$data1 = OpenRouterResponseAnalysis::parseJSONResponse($response1);
print_r($data1);
echo "\n";

// Формат 2: JSON объект в тексте
$response2 = 'Result: {"status": "ok", "value": 42} - done!';

echo "Формат 2 (JSON объект в тексте):\n";
$data2 = OpenRouterResponseAnalysis::parseJSONResponse($response2);
print_r($data2);
echo "\n";

// Формат 3: Чистый JSON
$response3 = '{"status": "success", "data": [1, 2, 3]}';

echo "Формат 3 (чистый JSON):\n";
$data3 = OpenRouterResponseAnalysis::parseJSONResponse($response3);
print_r($data3);
echo "\n";

echo str_repeat('=', 80) . "\n\n";

// ============================================================================
// ПРИМЕР 4: Дополнительные утилиты
// ============================================================================

echo "=== ПРИМЕР 4: Дополнительные утилиты ===\n\n";

// Извлечение code block
$markdown = <<<'MD'
Here is the code:

```php
echo "Hello, World!";
```

And here is JSON:

```json
{"message": "hello"}
```
MD;

echo "Извлечение PHP блока:\n";
$phpCode = OpenRouterResponseAnalysis::extractCodeBlock($markdown, 'php');
echo $phpCode . "\n\n";

echo "Извлечение JSON блока:\n";
$jsonCode = OpenRouterResponseAnalysis::extractCodeBlock($markdown, 'json');
echo $jsonCode . "\n\n";

// Очистка markdown
$cleanText = OpenRouterResponseAnalysis::cleanMarkdown($markdown);
echo "Очищенный текст:\n";
echo $cleanText . "\n\n";

echo str_repeat('=', 80) . "\n\n";

// ============================================================================
// ИТОГИ
// ============================================================================

echo "=== ИТОГИ РЕФАКТОРИНГА ===\n\n";

echo "✅ Преимущества использования OpenRouterResponseAnalysis:\n";
echo "   - Переиспользование кода в разных проектах\n";
echo "   - Упрощение AIAnalysisTrait (делегирование базовых операций)\n";
echo "   - Статические методы (не требуют создания экземпляра)\n";
echo "   - Обработка различных форматов ответов AI\n";
echo "   - Автоматическое кеширование для Claude\n";
echo "   - Без зависимостей от БД\n\n";

echo "✅ AIAnalysisTrait сохраняет свою специфичную логику:\n";
echo "   - Fallback между моделями\n";
echo "   - Retry механизм с экспоненциальной задержкой\n";
echo "   - Запись метрик в БД\n";
echo "   - Аналитика по периодам, моделям, кешированию\n";
echo "   - Экспорт отчетов в JSON/CSV\n\n";

echo "📚 См. документацию:\n";
echo "   - docs/BaseUtils/OPENROUTER_RESPONSE_ANALYSIS.md\n";
echo "   - docs/Rss2Tlg/REFACTORING_AIAnalysisTrait.md\n";
echo "   - REFACTORING_SUMMARY.md\n\n";

echo "Готово! 🎉\n";

<?php

declare(strict_types=1);

/**
 * Примеры использования класса OpenRouterResponseAnalysis
 * 
 * Этот файл содержит практические примеры работы с классом
 * OpenRouterResponseAnalysis для обработки ответов от AI моделей.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Component\OpenRouterResponseAnalysis;
use App\Component\Logger;

// ============================================================================
// НАСТРОЙКА ЛОГИРОВАНИЯ (опционально)
// ============================================================================

$logger = new Logger([
    'directory' => __DIR__ . '/../../logs',
    'file_name' => 'openrouter_response_analysis.log',
    'level' => 'debug'
]);

OpenRouterResponseAnalysis::setLogger($logger);

// ============================================================================
// ПРИМЕР 1: Извлечение JSON из markdown блока
// ============================================================================

echo "=== ПРИМЕР 1: Извлечение JSON из markdown блока ===\n\n";

$responseWithMarkdown = <<<'MARKDOWN'
Вот результат анализа:

```json
{
    "status": "success",
    "confidence": 0.95,
    "categories": ["tech", "AI", "machine-learning"],
    "summary": "Article about AI developments"
}
```

Анализ завершен успешно.
MARKDOWN;

$json = OpenRouterResponseAnalysis::extractJSON($responseWithMarkdown);
echo "Извлеченный JSON:\n";
echo $json . "\n\n";

$data = OpenRouterResponseAnalysis::parseJSONResponse($responseWithMarkdown);
echo "Распарсенные данные:\n";
print_r($data);
echo "\n";

// ============================================================================
// ПРИМЕР 2: Извлечение JSON объекта из обычного текста
// ============================================================================

echo "=== ПРИМЕР 2: Извлечение JSON объекта из текста ===\n\n";

$responseWithText = 'Результат: {"status": "ok", "value": 42, "items": [1, 2, 3]} - готово!';

$json = OpenRouterResponseAnalysis::extractJSON($responseWithText);
echo "Извлеченный JSON:\n";
echo $json . "\n\n";

$data = OpenRouterResponseAnalysis::parseJSONResponse($responseWithText);
echo "Распарсенные данные:\n";
print_r($data);
echo "\n";

// ============================================================================
// ПРИМЕР 3: Извлечение JSON массива
// ============================================================================

echo "=== ПРИМЕР 3: Извлечение JSON массива ===\n\n";

$responseWithArray = 'Items: [{"id": 1, "name": "Item 1"}, {"id": 2, "name": "Item 2"}]';

$json = OpenRouterResponseAnalysis::extractJSON($responseWithArray);
echo "Извлеченный JSON:\n";
echo $json . "\n\n";

$data = OpenRouterResponseAnalysis::parseJSONResponse($responseWithArray);
echo "Распарсенные данные:\n";
print_r($data);
echo "\n";

// ============================================================================
// ПРИМЕР 4: Подготовка сообщений для Claude с кешированием
// ============================================================================

echo "=== ПРИМЕР 4: Подготовка сообщений для Claude ===\n\n";

$systemPrompt = <<<'PROMPT'
You are an expert news analyst. Analyze the following article and provide:
1. Main topics and categories
2. Sentiment analysis
3. Key entities mentioned
4. Summary in 2-3 sentences

Always respond in valid JSON format.
PROMPT;

$userPrompt = 'Analyze this article: [article text here...]';

$messagesForClaude = OpenRouterResponseAnalysis::prepareMessages(
    $systemPrompt,
    $userPrompt,
    'anthropic/claude-3.5-sonnet'
);

echo "Messages для Claude (с кешированием):\n";
print_r($messagesForClaude);
echo "\n";

// ============================================================================
// ПРИМЕР 5: Подготовка сообщений для обычной модели (GPT-4)
// ============================================================================

echo "=== ПРИМЕР 5: Подготовка сообщений для GPT-4 ===\n\n";

$messagesForGPT = OpenRouterResponseAnalysis::prepareMessages(
    $systemPrompt,
    $userPrompt,
    'openai/gpt-4'
);

echo "Messages для GPT-4 (стандартный формат):\n";
print_r($messagesForGPT);
echo "\n";

// ============================================================================
// ПРИМЕР 6: Подготовка опций из конфигурации модели
// ============================================================================

echo "=== ПРИМЕР 6: Подготовка опций из конфигурации ===\n\n";

$modelConfig = [
    'model' => 'openai/gpt-4',
    'max_tokens' => 2000,
    'temperature' => 0.7,
    'top_p' => 0.9,
    'frequency_penalty' => 0.1,
    'presence_penalty' => 0.1
];

$options = OpenRouterResponseAnalysis::prepareOptions($modelConfig);

echo "Опции для запроса:\n";
print_r($options);
echo "\n";

// ============================================================================
// ПРИМЕР 7: Подготовка опций с переопределением
// ============================================================================

echo "=== ПРИМЕР 7: Переопределение опций ===\n\n";

$extraOptions = [
    'temperature' => 0.3,  // Переопределяем температуру
    'max_tokens' => 3000   // Переопределяем max_tokens
];

$options = OpenRouterResponseAnalysis::prepareOptions($modelConfig, $extraOptions);

echo "Опции с переопределением:\n";
print_r($options);
echo "\n";

// ============================================================================
// ПРИМЕР 8: Подготовка дефолтных опций
// ============================================================================

echo "=== ПРИМЕР 8: Дефолтные опции ===\n\n";

$defaultOptions = OpenRouterResponseAnalysis::prepareOptions('openai/gpt-4');

echo "Дефолтные опции:\n";
print_r($defaultOptions);
echo "\n";

// ============================================================================
// ПРИМЕР 9: Валидация конфигурации AI модулей
// ============================================================================

echo "=== ПРИМЕР 9: Валидация конфигурации ===\n\n";

// Создаем временный файл с промптом для примера
$tempPromptFile = sys_get_temp_dir() . '/test_prompt.txt';
file_put_contents($tempPromptFile, 'Test prompt content');

try {
    $config = [
        'models' => [
            'openai/gpt-4',
            'anthropic/claude-3.5-sonnet',
            'google/gemini-pro'
        ],
        'prompt_file' => $tempPromptFile,
        'fallback_strategy' => 'sequential'
    ];
    
    $validatedConfig = OpenRouterResponseAnalysis::validateAIConfig($config);
    
    echo "Валидированная конфигурация:\n";
    print_r($validatedConfig);
    echo "\n";
    
} catch (\Exception $e) {
    echo "Ошибка валидации: " . $e->getMessage() . "\n\n";
}

// Удаляем временный файл
unlink($tempPromptFile);

// ============================================================================
// ПРИМЕР 10: Обработка ошибок парсинга
// ============================================================================

echo "=== ПРИМЕР 10: Обработка ошибок парсинга ===\n\n";

$invalidJSON = 'Result: {invalid json structure';

$data = OpenRouterResponseAnalysis::parseJSONResponse($invalidJSON);

if ($data === null) {
    echo "Не удалось распарсить JSON (ожидаемо для невалидного JSON)\n";
} else {
    echo "Данные:\n";
    print_r($data);
}
echo "\n";

// ============================================================================
// ПРИМЕР 11: Обнаружение JSON в произвольном тексте
// ============================================================================

echo "=== ПРИМЕР 11: Обнаружение JSON в тексте ===\n\n";

$arbitraryText = <<<'TEXT'
Добрый день! Вот результаты анализа статьи.

Основные выводы:
{"sentiment": "positive", "score": 8.5, "topics": ["technology", "innovation"]}

Спасибо за использование нашего сервиса!
TEXT;

$detected = OpenRouterResponseAnalysis::detectJSONInText($arbitraryText);

if ($detected !== null) {
    echo "JSON обнаружен и распарсен:\n";
    print_r($detected);
} else {
    echo "JSON не найден в тексте\n";
}
echo "\n";

// ============================================================================
// ПРИМЕР 12: Очистка markdown блоков
// ============================================================================

echo "=== ПРИМЕР 12: Очистка markdown блоков ===\n\n";

$markdownText = <<<'MARKDOWN'
Вот код:

```json
{"key": "value"}
```

И еще один:

```python
print("Hello")
```

Готово!
MARKDOWN;

$cleaned = OpenRouterResponseAnalysis::cleanMarkdown($markdownText);

echo "Очищенный текст:\n";
echo $cleaned . "\n\n";

// ============================================================================
// ПРИМЕР 13: Извлечение code block по языку
// ============================================================================

echo "=== ПРИМЕР 13: Извлечение code block по языку ===\n\n";

$multipleBlocks = <<<'MARKDOWN'
JSON данные:

```json
{"status": "ok", "value": 123}
```

Python код:

```python
def hello():
    print("Hello, World!")
```

JavaScript код:

```javascript
console.log("Hello");
```
MARKDOWN;

// Извлекаем JSON блок
$jsonBlock = OpenRouterResponseAnalysis::extractCodeBlock($multipleBlocks, 'json');
echo "JSON блок:\n";
echo $jsonBlock . "\n\n";

// Извлекаем Python блок
$pythonBlock = OpenRouterResponseAnalysis::extractCodeBlock($multipleBlocks, 'python');
echo "Python блок:\n";
echo $pythonBlock . "\n\n";

// Извлекаем первый попавшийся блок
$anyBlock = OpenRouterResponseAnalysis::extractCodeBlock($multipleBlocks);
echo "Первый найденный блок:\n";
echo $anyBlock . "\n\n";

// ============================================================================
// ПРИМЕР 14: Реальный пример обработки ответа Claude
// ============================================================================

echo "=== ПРИМЕР 14: Реальный пример ответа Claude ===\n\n";

$claudeResponse = <<<'RESPONSE'
Based on my analysis of the article, here are the results:

```json
{
    "sentiment": {
        "overall": "positive",
        "score": 8.2,
        "confidence": 0.89
    },
    "categories": [
        "artificial-intelligence",
        "technology",
        "business"
    ],
    "entities": {
        "organizations": ["OpenAI", "Google", "Microsoft"],
        "technologies": ["GPT-4", "Claude", "Gemini"],
        "people": ["Sam Altman", "Sundar Pichai"]
    },
    "summary": "The article discusses recent developments in AI technology, focusing on competition between major tech companies. It highlights new model releases and their potential impact on the industry.",
    "key_points": [
        "Multiple AI models released in Q4 2024",
        "Focus on multimodal capabilities",
        "Increased competition drives innovation"
    ]
}
```

This analysis was performed using advanced natural language processing techniques.
RESPONSE;

// Парсим ответ
$analysisData = OpenRouterResponseAnalysis::parseJSONResponse($claudeResponse);

if ($analysisData !== null) {
    echo "Результаты анализа:\n";
    echo "- Общий сентимент: {$analysisData['sentiment']['overall']}\n";
    echo "- Оценка: {$analysisData['sentiment']['score']}/10\n";
    echo "- Категории: " . implode(', ', $analysisData['categories']) . "\n";
    echo "- Организации: " . implode(', ', $analysisData['entities']['organizations']) . "\n";
    echo "- Резюме: {$analysisData['summary']}\n";
} else {
    echo "Не удалось распарсить ответ\n";
}
echo "\n";

// ============================================================================
// ПРИМЕР 15: Использование в реальном workflow
// ============================================================================

echo "=== ПРИМЕР 15: Полный workflow обработки ===\n\n";

/**
 * Симуляция полного процесса обработки AI запроса
 */
function processAIRequest(string $systemPrompt, string $userPrompt, string $model): ?array
{
    echo "1. Подготовка сообщений...\n";
    $messages = OpenRouterResponseAnalysis::prepareMessages($systemPrompt, $userPrompt, $model);
    
    echo "2. Подготовка опций...\n";
    $options = OpenRouterResponseAnalysis::prepareOptions([
        'model' => $model,
        'max_tokens' => 2000,
        'temperature' => 0.3
    ]);
    
    echo "3. Отправка запроса к API...\n";
    // Здесь был бы реальный вызов OpenRouter API
    // $response = $openRouter->chatWithMessages($model, $messages, $options);
    
    // Симулируем ответ от API
    $simulatedResponse = <<<'JSON'
    {
        "status": "completed",
        "analysis": {
            "topics": ["tech", "innovation"],
            "sentiment": "positive"
        }
    }
    JSON;
    
    echo "4. Парсинг ответа...\n";
    $data = OpenRouterResponseAnalysis::parseJSONResponse($simulatedResponse);
    
    if ($data !== null) {
        echo "5. Успешно обработано!\n";
        return $data;
    } else {
        echo "5. Ошибка парсинга ответа\n";
        return null;
    }
}

$result = processAIRequest(
    'You are a helpful assistant',
    'Analyze this text',
    'anthropic/claude-3.5-sonnet'
);

if ($result !== null) {
    echo "\nРезультат обработки:\n";
    print_r($result);
}
echo "\n";

echo "=== Все примеры выполнены ===\n";

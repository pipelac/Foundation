# OpenRouterClient - Работа с внутренней информацией OpenRouter API

## Описание

`OpenRouterClient` — это специализированный класс для получения детальной информации о вашем аккаунте OpenRouter, включая:

- ✅ Баланс и кредиты
- ✅ Использованные токены и их стоимость
- ✅ Лимиты запросов (rate limits)
- ✅ Список доступных моделей и их параметры
- ✅ Расчёт стоимости запросов
- ✅ Информация о конкретных генерациях
- ✅ Валидация API ключа

## Отличие от OpenRouter

| Класс | Назначение |
|-------|-----------|
| **OpenRouter** | Взаимодействие с AI моделями (генерация текста, изображений, распознавание и т.д.) |
| **OpenRouterClient** | Получение информации о балансе, токенах, статистике и настройках аккаунта |

## Установка и конфигурация

Класс использует тот же конфигурационный файл `config/openrouter.json`:

```json
{
    "api_key": "YOUR_OPENROUTER_API_KEY",
    "app_name": "BasicUtilitiesApp",
    "timeout": 30,
    "retries": 3
}
```

## Основные методы

### 1. Проверка валидности API ключа

```php
$client = new OpenRouterClient($config, $logger);

if ($client->validateApiKey()) {
    echo "API ключ валидный";
} else {
    echo "API ключ невалидный";
}
```

### 2. Получение информации об аккаунте

```php
$keyInfo = $client->getKeyInfo();

// Возвращает:
// [
//     'label' => 'My API Key',
//     'usage' => 5.2345,              // Использовано в USD
//     'limit' => 100.00,              // Лимит в USD (null = безлимитный)
//     'is_free_tier' => false,        // Бесплатный аккаунт
//     'rate_limit' => [...]           // Лимиты запросов
// ]

echo "Использовано: $" . $keyInfo['usage'];
echo "Лимит: $" . ($keyInfo['limit'] ?? 'Безлимитный');
```

### 3. Получение текущего баланса

```php
$balance = $client->getBalance();

echo "Баланс: $" . number_format($balance, 2);

// Баланс = Лимит - Использование
// Для безлимитных аккаунтов возвращает PHP_FLOAT_MAX
```

### 4. Статистика использования

```php
$stats = $client->getUsageStats();

// Возвращает:
// [
//     'total_usage_usd' => 5.2345,
//     'limit_usd' => 100.00,
//     'remaining_usd' => 94.7655,
//     'is_free_tier' => false,
//     'usage_percentage' => 5.23
// ]

echo "Использовано: {$stats['usage_percentage']}%";
echo "Осталось: $" . number_format($stats['remaining_usd'], 2);
```

### 5. Информация о лимитах запросов (Rate Limits)

```php
$rateLimits = $client->getRateLimits();

// Возвращает:
// [
//     'requests_per_minute' => 60,    // null если не установлено
//     'requests_per_day' => 1000,     // null если не установлено
//     'tokens_per_minute' => 90000    // null если не установлено
// ]

if ($rateLimits['requests_per_minute']) {
    echo "Лимит запросов в минуту: " . $rateLimits['requests_per_minute'];
}
```

### 6. Список доступных моделей

```php
$models = $client->getModels();

foreach ($models as $model) {
    echo $model['id'];                  // openai/gpt-4
    echo $model['name'];                // GPT-4
    echo $model['context_length'];      // 8192
    echo $model['pricing']['prompt'];   // 0.03 (за 1M токенов)
    echo $model['pricing']['completion']; // 0.06
}
```

### 7. Информация о конкретной модели

```php
$modelInfo = $client->getModelInfo('openai/gpt-3.5-turbo');

echo "Модель: " . $modelInfo['name'];
echo "Контекст: " . $modelInfo['context_length'] . " токенов";
echo "Цена промпта: $" . $modelInfo['pricing']['prompt'] . " за 1M токенов";
echo "Цена ответа: $" . $modelInfo['pricing']['completion'] . " за 1M токенов";
```

### 8. Расчёт стоимости запроса

```php
// Расчёт для 1000 токенов промпта + 500 токенов ответа
$cost = $client->calculateCost('openai/gpt-3.5-turbo', 1000, 500);

// Возвращает:
// [
//     'model_id' => 'openai/gpt-3.5-turbo',
//     'prompt_tokens' => 1000,
//     'completion_tokens' => 500,
//     'total_tokens' => 1500,
//     'prompt_cost_usd' => 0.0005,
//     'completion_cost_usd' => 0.001,
//     'total_cost_usd' => 0.0015
// ]

echo "Общая стоимость: $" . $cost['total_cost_usd'];
```

### 9. Информация о конкретной генерации

```php
// ID генерации можно получить из заголовка X-OpenRouter-Generation-Id
// при выполнении запроса через класс OpenRouter

$generationId = 'gen_abc123xyz';
$info = $client->getGenerationInfo($generationId);

// Возвращает:
// [
//     'id' => 'gen_abc123xyz',
//     'model' => 'openai/gpt-3.5-turbo',
//     'usage' => [
//         'prompt_tokens' => 100,
//         'completion_tokens' => 200,
//         'total_tokens' => 300
//     ],
//     'created_at' => 1699999999,
//     'total_cost' => 0.00045
// ]

echo "Модель: " . $info['model'];
echo "Использовано токенов: " . $info['usage']['total_tokens'];
echo "Стоимость: $" . $info['total_cost'];
```

## Полный пример использования

```php
<?php

use App\Component\Logger;
use App\Component\OpenRouterClient;
use App\Config\ConfigLoader;

// Загрузка конфигурации
$loggerConfig = ConfigLoader::load(__DIR__ . '/config/logger.json');
$logger = new Logger($loggerConfig);

$config = ConfigLoader::load(__DIR__ . '/config/openrouter.json');
$client = new OpenRouterClient($config, $logger);

// 1. Проверка API ключа
if (!$client->validateApiKey()) {
    die("Невалидный API ключ!\n");
}

// 2. Информация об аккаунте
$keyInfo = $client->getKeyInfo();
echo "Аккаунт: " . $keyInfo['label'] . "\n";

// 3. Баланс
$balance = $client->getBalance();
echo "Баланс: $" . number_format($balance, 2) . "\n";

// 4. Статистика
$stats = $client->getUsageStats();
echo "Использовано: {$stats['usage_percentage']}%\n";

// 5. Лимиты
$rateLimits = $client->getRateLimits();
if ($rateLimits['requests_per_minute']) {
    echo "Лимит: " . $rateLimits['requests_per_minute'] . " req/min\n";
}

// 6. Список моделей
$models = $client->getModels();
echo "Доступно моделей: " . count($models) . "\n";

// 7. Информация о модели
$modelInfo = $client->getModelInfo('openai/gpt-3.5-turbo');
echo "GPT-3.5 Turbo - контекст: " . $modelInfo['context_length'] . " токенов\n";

// 8. Расчёт стоимости
$cost = $client->calculateCost('openai/gpt-3.5-turbo', 1000, 500);
echo "Стоимость запроса (1000+500 токенов): $" . $cost['total_cost_usd'] . "\n";
```

## Обработка ошибок

```php
use App\Component\Exception\OpenRouterException;
use App\Component\Exception\OpenRouterApiException;
use App\Component\Exception\OpenRouterValidationException;

try {
    $balance = $client->getBalance();
    echo "Баланс: $" . $balance;
    
} catch (OpenRouterApiException $e) {
    // Ошибка API (неверный ключ, превышен лимит и т.д.)
    echo "API Error: " . $e->getMessage();
    echo "Status Code: " . $e->getStatusCode();
    echo "Response: " . $e->getResponseBody();
    
} catch (OpenRouterValidationException $e) {
    // Ошибка валидации параметров
    echo "Validation Error: " . $e->getMessage();
    
} catch (OpenRouterException $e) {
    // Общая ошибка OpenRouter
    echo "Error: " . $e->getMessage();
}
```

## Практические примеры

### Мониторинг баланса

```php
$stats = $client->getUsageStats();

if ($stats['usage_percentage'] > 90) {
    echo "⚠️ Предупреждение: использовано {$stats['usage_percentage']}% лимита!\n";
    echo "Осталось: $" . number_format($stats['remaining_usd'], 2) . "\n";
}

if ($stats['remaining_usd'] < 5.0) {
    echo "🚨 Критично: баланс менее $5!\n";
    // Отправка уведомления администратору
}
```

### Выбор оптимальной модели по цене

```php
$models = $client->getModels();

// Фильтруем модели с поддержкой text generation
$textModels = array_filter($models, function($model) {
    return isset($model['pricing']['prompt']) && 
           isset($model['pricing']['completion']);
});

// Сортируем по цене
usort($textModels, function($a, $b) {
    $priceA = $a['pricing']['prompt'] + $a['pricing']['completion'];
    $priceB = $b['pricing']['prompt'] + $b['pricing']['completion'];
    return $priceA <=> $priceB;
});

// Выводим топ-5 самых дешевых моделей
echo "Топ-5 дешёвых моделей:\n";
foreach (array_slice($textModels, 0, 5) as $model) {
    $totalPrice = $model['pricing']['prompt'] + $model['pricing']['completion'];
    echo "{$model['id']} - \${$totalPrice} за 1M токенов\n";
}
```

### Оценка стоимости перед запросом

```php
// Предположим, что у нас есть промпт
$prompt = "Расскажи подробно о PHP 8.3";
$estimatedPromptTokens = strlen($prompt) / 4; // Грубая оценка
$estimatedCompletionTokens = 500;

$cost = $client->calculateCost(
    'openai/gpt-3.5-turbo',
    (int)$estimatedPromptTokens,
    $estimatedCompletionTokens
);

if ($cost['total_cost_usd'] > 0.01) {
    echo "Запрос будет стоить ${cost['total_cost_usd']}\n";
    echo "Продолжить? (y/n): ";
    // Запрос подтверждения пользователя
}
```

### Отслеживание конкретной генерации

```php
// При выполнении запроса через OpenRouter
$openRouter = new OpenRouter($config, $logger);
$response = $openRouter->text2text('openai/gpt-3.5-turbo', 'Привет!');

// Получаем ID генерации из ответа (если он возвращается)
// или из заголовков HTTP запроса
$generationId = 'gen_abc123'; // Пример

// Получаем детальную информацию о генерации
$info = $client->getGenerationInfo($generationId);

echo "Использовано токенов: {$info['usage']['total_tokens']}\n";
echo "Стоимость: \${$info['total_cost']}\n";
```

## API Endpoints

Класс использует следующие endpoints OpenRouter API:

- `GET /api/v1/auth/key` - Информация об API ключе и балансе
- `GET /api/v1/models` - Список доступных моделей
- `GET /api/v1/generation?id={id}` - Информация о конкретной генерации

## Требования

- PHP 8.1+
- Валидный API ключ OpenRouter (получить на https://openrouter.ai/keys)
- Интернет-соединение для запросов к API

## Логирование

Все операции автоматически логируются (если передан экземпляр Logger):

- Информационные сообщения (info) - успешные операции
- Сообщения об ошибках (error) - проблемы с API

```php
// Пример логов
[2024-01-15 10:30:45] INFO: Получена информация об API ключе {"label":"My Key","usage":5.23}
[2024-01-15 10:30:46] INFO: Рассчитан текущий баланс {"balance":94.77,"usage":5.23,"limit":100}
[2024-01-15 10:30:47] ERROR: Сервер OpenRouter вернул ошибку {"status_code":401,"endpoint":"/auth/key"}
```

## Примеры использования

Полный рабочий пример доступен в файле:
```
examples/openrouter_client_example.php
```

Запуск:
```bash
php examples/openrouter_client_example.php
```

## Лицензия

MIT

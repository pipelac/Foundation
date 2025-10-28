# Интеграция OpenRouter и OpenRouterClient

## Обзор

В проекте присутствуют два класса для работы с OpenRouter API:

### OpenRouter
**Назначение:** Работа с AI моделями  
**Основные возможности:**
- Генерация текста (text2text)
- Генерация изображений (text2image)
- Распознавание изображений (image2text)
- Распознавание речи (audio2text)
- Извлечение текста из PDF (pdf2text)
- Потоковая передача (streaming)

### OpenRouterClient
**Назначение:** Мониторинг и управление аккаунтом  
**Основные возможности:**
- Проверка баланса и кредитов
- Статистика использования токенов
- Информация о доступных моделях
- Расчёт стоимости запросов
- Мониторинг лимитов

## Совместное использование

### Пример 1: Проверка баланса перед запросом

```php
use App\Component\OpenRouter;
use App\Component\OpenRouterClient;
use App\Config\ConfigLoader;

$config = ConfigLoader::load(__DIR__ . '/config/openrouter.json');
$logger = new Logger($loggerConfig);

// Инициализация клиентов
$client = new OpenRouterClient($config, $logger);
$openRouter = new OpenRouter($config, $logger);

// Проверка баланса перед отправкой запроса
$balance = $client->getBalance();

if ($balance < 1.0) {
    echo "Недостаточный баланс для выполнения запросов: $" . $balance;
    exit(1);
}

// Выполнение запроса
$response = $openRouter->text2text('openai/gpt-3.5-turbo', 'Привет!');
echo $response;
```

### Пример 2: Расчёт стоимости перед запросом

```php
// Оценка токенов (грубая)
$prompt = "Напиши длинную статью о PHP";
$estimatedPromptTokens = (int)(strlen($prompt) / 4);
$estimatedCompletionTokens = 1000;

// Расчёт стоимости
$cost = $client->calculateCost(
    'openai/gpt-3.5-turbo',
    $estimatedPromptTokens,
    $estimatedCompletionTokens
);

echo "Ожидаемая стоимость: $" . $cost['total_cost_usd'] . "\n";

// Запрос подтверждения
if ($cost['total_cost_usd'] > 0.05) {
    echo "Запрос дорогой, продолжить? (y/n): ";
    $confirmation = trim(fgets(STDIN));
    if ($confirmation !== 'y') {
        exit(0);
    }
}

// Выполнение запроса
$response = $openRouter->text2text('openai/gpt-3.5-turbo', $prompt);
```

### Пример 3: Выбор оптимальной модели

```php
// Получаем список моделей
$models = $client->getModels();

// Фильтруем модели с text generation
$textModels = array_filter($models, function($model) {
    return isset($model['pricing']['prompt']) && 
           $model['context_length'] >= 4000;
});

// Сортируем по цене
usort($textModels, function($a, $b) {
    $priceA = $a['pricing']['prompt'] + $a['pricing']['completion'];
    $priceB = $b['pricing']['prompt'] + $b['pricing']['completion'];
    return $priceA <=> $priceB;
});

// Берём самую дешёвую модель
$cheapestModel = $textModels[0];
echo "Используем модель: {$cheapestModel['id']}\n";

// Выполняем запрос
$response = $openRouter->text2text($cheapestModel['id'], 'Привет!');
```

### Пример 4: Мониторинг использования

```php
// Выполнение нескольких запросов
$queries = [
    'Что такое PHP?',
    'Расскажи о Laravel',
    'Объясни Symfony',
];

$totalCost = 0.0;

foreach ($queries as $query) {
    // Оценка стоимости
    $estimatedTokens = (int)(strlen($query) / 4);
    $cost = $client->calculateCost('openai/gpt-3.5-turbo', $estimatedTokens, 200);
    
    // Выполнение запроса
    $response = $openRouter->text2text('openai/gpt-3.5-turbo', $query);
    
    // Накопление стоимости
    $totalCost += $cost['total_cost_usd'];
    
    echo "Запрос: $query\n";
    echo "Стоимость: \${$cost['total_cost_usd']}\n";
    echo "Ответ: $response\n\n";
}

// Проверка баланса после запросов
$balance = $client->getBalance();
echo "Общая стоимость: $$totalCost\n";
echo "Текущий баланс: $$balance\n";
```

### Пример 5: Автоматический выбор модели по бюджету

```php
function selectModelByBudget(
    OpenRouterClient $client,
    float $maxCostPerRequest,
    int $estimatedTokens
): string {
    $models = $client->getModels();
    
    foreach ($models as $model) {
        if (!isset($model['pricing']['prompt']) || !isset($model['pricing']['completion'])) {
            continue;
        }
        
        $cost = $client->calculateCost(
            $model['id'],
            $estimatedTokens,
            $estimatedTokens
        );
        
        if ($cost['total_cost_usd'] <= $maxCostPerRequest) {
            return $model['id'];
        }
    }
    
    throw new Exception('Не найдена модель в рамках бюджета');
}

// Использование
$modelId = selectModelByBudget($client, 0.01, 1000);
$response = $openRouter->text2text($modelId, 'Привет!');
```

### Пример 6: Уведомления о низком балансе

```php
function checkBalanceAndNotify(OpenRouterClient $client, float $threshold = 5.0): void
{
    $stats = $client->getUsageStats();
    
    if ($stats['remaining_usd'] < $threshold) {
        // Отправка уведомления (через Email или Telegram)
        $message = sprintf(
            "⚠️ Предупреждение: Баланс OpenRouter составляет $%.2f\n" .
            "Использовано: %.2f%%\n" .
            "Осталось: $%.2f",
            $stats['remaining_usd'],
            $stats['usage_percentage'],
            $stats['remaining_usd']
        );
        
        // Отправка через Telegram
        $telegram = new Telegram($telegramConfig);
        $telegram->sendText('ADMIN_CHAT_ID', $message);
    }
}

// Проверка перед каждым запросом
checkBalanceAndNotify($client);
$response = $openRouter->text2text('openai/gpt-3.5-turbo', 'Привет!');
```

### Пример 7: Логирование использования

```php
class OpenRouterUsageTracker
{
    private OpenRouterClient $client;
    private Logger $logger;
    
    public function __construct(OpenRouterClient $client, Logger $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }
    
    public function executeWithTracking(
        OpenRouter $openRouter,
        string $model,
        string $prompt
    ): string {
        // Получаем баланс до запроса
        $balanceBefore = $this->client->getBalance();
        
        // Выполняем запрос
        $startTime = microtime(true);
        $response = $openRouter->text2text($model, $prompt);
        $duration = microtime(true) - $startTime;
        
        // Получаем баланс после запроса
        $balanceAfter = $this->client->getBalance();
        $cost = $balanceBefore - $balanceAfter;
        
        // Логируем
        $this->logger->info('OpenRouter request completed', [
            'model' => $model,
            'prompt_length' => strlen($prompt),
            'response_length' => strlen($response),
            'duration' => round($duration, 3),
            'cost' => round($cost, 6),
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
        ]);
        
        return $response;
    }
}

// Использование
$tracker = new OpenRouterUsageTracker($client, $logger);
$response = $tracker->executeWithTracking($openRouter, 'openai/gpt-3.5-turbo', 'Привет!');
```

## Рекомендации

1. **Всегда проверяйте баланс** перед выполнением дорогих операций
2. **Используйте расчёт стоимости** для оценки затрат
3. **Настройте мониторинг** баланса и использования
4. **Выбирайте оптимальные модели** на основе баланса цены и качества
5. **Логируйте все операции** для анализа использования
6. **Настройте уведомления** при низком балансе

## Конфигурация

Оба класса используют один конфигурационный файл:

```json
{
    "api_key": "YOUR_OPENROUTER_API_KEY",
    "app_name": "BasicUtilitiesApp",
    "timeout": 60,
    "retries": 3
}
```

## Обработка ошибок

```php
try {
    $balance = $client->getBalance();
    $response = $openRouter->text2text('openai/gpt-3.5-turbo', 'Привет!');
    
} catch (OpenRouterApiException $e) {
    // API ошибка (неверный ключ, превышен лимит)
    $logger->error('OpenRouter API Error', [
        'status_code' => $e->getStatusCode(),
        'response' => $e->getResponseBody(),
    ]);
    
} catch (OpenRouterValidationException $e) {
    // Ошибка валидации параметров
    $logger->error('Validation Error', ['message' => $e->getMessage()]);
    
} catch (OpenRouterException $e) {
    // Общая ошибка OpenRouter
    $logger->error('OpenRouter Error', ['message' => $e->getMessage()]);
}
```

## Тестирование

```bash
# Проверка загрузки класса
php bin/test_openrouter_client.php

# Полный пример использования
php examples/openrouter_client_example.php
```

## Дополнительная информация

- [Документация OpenRouter API](https://openrouter.ai/docs)
- [README OpenRouterClient](../OPENROUTER_CLIENT_README.md)
- [Основной README проекта](../README.md)

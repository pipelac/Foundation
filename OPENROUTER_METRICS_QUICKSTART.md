# OpenRouterMetrics - Быстрый старт

Краткое руководство по использованию класса `OpenRouterMetrics` для мониторинга и управления метриками OpenRouter API.

## Установка

```bash
composer install
```

## Минимальная конфигурация

Создайте `config/openrouter.json`:

```json
{
    "api_key": "ваш_api_ключ"
}
```

## Быстрый пример

```php
<?php

require_once __DIR__ . '/autoload.php';

use App\Component\OpenRouterMetrics;
use App\Config\ConfigLoader;

// Загрузка конфигурации
$config = ConfigLoader::load(__DIR__ . '/config/openrouter.json');
$metrics = new OpenRouterMetrics($config);

// Проверка баланса
$balance = $metrics->getBalance();
echo "Доступный баланс: \${$balance}\n";

// Статистика использования
$stats = $metrics->getUsageStats();
echo "Использовано: {$stats['usage_percent']}% бюджета\n";
echo "Остаток: \${$stats['remaining']}\n";

// Список моделей
$models = $metrics->getModels();
echo "Доступно моделей: " . count($models) . "\n";

// Оценка стоимости
$estimate = $metrics->estimateCost('openai/gpt-3.5-turbo', 1000, 500);
echo "Стоимость запроса: \${$estimate['total_cost']}\n";

// Проверка баланса
if ($metrics->hasEnoughBalance($estimate['total_cost'])) {
    echo "✓ Баланса достаточно\n";
} else {
    echo "✗ Недостаточно средств\n";
}
```

## Основные методы

### 1. Баланс и лимиты

```php
// Текущий баланс
$balance = $metrics->getBalance();

// Детальная информация о ключе
$keyInfo = $metrics->getKeyInfo();
print_r($keyInfo);

// Статистика использования
$stats = $metrics->getUsageStats();
echo "Использовано: {$stats['total_usage']} USD\n";
echo "Лимит: {$stats['limit']} USD\n";
echo "Остаток: {$stats['remaining']} USD\n";
```

### 2. Модели

```php
// Все модели
$models = $metrics->getModels();

// Конкретная модель
$modelInfo = $metrics->getModelInfo('openai/gpt-4');
echo "Контекст: {$modelInfo['context_length']} токенов\n";
echo "Цена prompt: \${$modelInfo['pricing']['prompt']} за 1M токенов\n";
```

### 3. Оценка стоимости

```php
// Оценка стоимости запроса
$estimate = $metrics->estimateCost(
    'openai/gpt-3.5-turbo',  // модель
    1000,                     // токены в запросе
    500                       // токены в ответе
);

echo "Стоимость: \${$estimate['total_cost']}\n";

// Проверка баланса
if ($metrics->hasEnoughBalance($estimate['total_cost'])) {
    // Выполнить запрос
}
```

### 4. Полная информация

```php
// Вся информация об аккаунте
$status = $metrics->getAccountStatus();

echo "Ключ: {$status['key_info']['label']}\n";
echo "Баланс: \${$status['balance']}\n";
echo "Использовано: {$status['usage_stats']['usage_percent']}%\n";
echo "Rate limit: {$status['rate_limits']['description']}\n";
```

## Практические сценарии

### Мониторинг бюджета

```php
$stats = $metrics->getUsageStats();

if ($stats['usage_percent'] > 80) {
    // Отправить уведомление
    echo "⚠️ ВНИМАНИЕ: Использовано более 80% бюджета!\n";
    echo "Остаток: \${$stats['remaining']}\n";
}
```

### Выбор оптимальной модели

```php
$models = $metrics->getModels();
$promptTokens = 1000;

// Найти самую дешёвую GPT модель
$cheapest = null;
$minCost = PHP_FLOAT_MAX;

foreach ($models as $model) {
    if (!str_contains($model['id'], 'gpt')) continue;
    
    $estimate = $metrics->estimateCost($model['id'], $promptTokens, 500);
    if ($estimate['total_cost'] < $minCost) {
        $minCost = $estimate['total_cost'];
        $cheapest = $model;
    }
}

echo "Самая дешёвая модель: {$cheapest['id']}\n";
echo "Стоимость: \${$minCost}\n";
```

### Проверка перед запросом

```php
$modelId = 'openai/gpt-4';
$promptTokens = 5000;
$completionTokens = 2000;

try {
    // Оценка стоимости
    $estimate = $metrics->estimateCost($modelId, $promptTokens, $completionTokens);
    echo "Ожидаемая стоимость: \${$estimate['total_cost']}\n";
    
    // Проверка баланса
    if (!$metrics->hasEnoughBalance($estimate['total_cost'])) {
        throw new Exception('Недостаточно средств на балансе!');
    }
    
    // Выполнить запрос к OpenRouter
    // $response = $openRouter->text2text($modelId, $prompt);
    
} catch (Exception $e) {
    echo "Ошибка: {$e->getMessage()}\n";
}
```

## Обработка ошибок

```php
use App\Component\Exception\OpenRouterApiException;
use App\Component\Exception\OpenRouterValidationException;

try {
    $balance = $metrics->getBalance();
} catch (OpenRouterValidationException $e) {
    echo "Ошибка валидации: {$e->getMessage()}\n";
} catch (OpenRouterApiException $e) {
    echo "Ошибка API: {$e->getMessage()}\n";
    echo "Код: {$e->getStatusCode()}\n";
} catch (Exception $e) {
    echo "Общая ошибка: {$e->getMessage()}\n";
}
```

## С логированием

```php
use App\Component\Logger;

$loggerConfig = ConfigLoader::load(__DIR__ . '/config/logger.json');
$logger = new Logger($loggerConfig);

$metrics = new OpenRouterMetrics($config, $logger);

// Все ошибки будут автоматически логироваться
try {
    $balance = $metrics->getBalance();
} catch (Exception $e) {
    // Ошибка уже в логе
}
```

## Запуск примера

```bash
php examples/openrouter_metrics_example.php
```

## Полная документация

Для детального описания всех методов и возможностей см. `docs/OPENROUTER_METRICS.md`

## API Endpoints

Класс использует следующие OpenRouter API endpoints:

- `GET /auth/key` - информация о ключе
- `GET /models` - список моделей
- `GET /generation` - информация о генерации

## Лицензия

MIT

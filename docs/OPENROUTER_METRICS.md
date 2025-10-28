# OpenRouterMetrics - Документация

## Описание

`OpenRouterMetrics` - класс для работы с метриками и статистикой OpenRouter API. Предоставляет полный набор методов для мониторинга использования API, проверки баланса, получения информации о моделях и оценки стоимости запросов.

## Возможности

- ✅ Получение информации о API ключе (баланс, лимиты, использование)
- ✅ Проверка баланса аккаунта
- ✅ Статистика использования токенов и расходов
- ✅ Информация о лимитах запросов (rate limits)
- ✅ Список всех доступных моделей с параметрами
- ✅ Детальная информация о конкретной модели
- ✅ Оценка стоимости запросов до их выполнения
- ✅ Проверка достаточности баланса
- ✅ Получение информации о выполненных генерациях
- ✅ Полная информация о состоянии аккаунта
- ✅ Строгая типизация и обработка ошибок
- ✅ Поддержка логирования

## Требования

- PHP 8.1+
- Расширения: `json`, `curl`
- API ключ OpenRouter
- Composer (для Guzzle HTTP клиента)

## Установка

```bash
composer install
```

## Конфигурация

Создайте файл `config/openrouter.json`:

```json
{
    "api_key": "ваш_api_ключ_openrouter",
    "app_name": "YourAppName",
    "timeout": 30,
    "retries": 3
}
```

### Параметры конфигурации

| Параметр | Тип | Обязательный | По умолчанию | Описание |
|----------|-----|--------------|--------------|----------|
| `api_key` | string | Да | - | API ключ OpenRouter |
| `app_name` | string | Нет | "BasicUtilitiesApp" | Название приложения для идентификации |
| `timeout` | int | Нет | 30 | Таймаут запросов в секундах |
| `retries` | int | Нет | - | Количество повторных попыток при ошибках |

## Использование

### Инициализация

```php
use App\Component\OpenRouterMetrics;
use App\Component\Logger;
use App\Config\ConfigLoader;

// С логгером
$config = ConfigLoader::load(__DIR__ . '/config/openrouter.json');
$loggerConfig = ConfigLoader::load(__DIR__ . '/config/logger.json');
$logger = new Logger($loggerConfig);
$metrics = new OpenRouterMetrics($config, $logger);

// Без логгера
$metrics = new OpenRouterMetrics($config);
```

### Основные методы

#### 1. Получение информации о API ключе

```php
$keyInfo = $metrics->getKeyInfo();

echo "Название: {$keyInfo['label']}\n";
echo "Использовано: \${$keyInfo['usage']}\n";
echo "Лимит: \${$keyInfo['limit']}\n";
echo "Бесплатный: " . ($keyInfo['is_free_tier'] ? 'Да' : 'Нет') . "\n";
echo "Rate limit: {$keyInfo['rate_limit']['requests']} за {$keyInfo['rate_limit']['interval']}\n";
```

**Возвращаемые данные:**
```php
[
    'label' => 'Мой API ключ',
    'usage' => 12.45,
    'limit' => 100.0,
    'is_free_tier' => false,
    'rate_limit' => [
        'requests' => 200,
        'interval' => '10s'
    ]
]
```

#### 2. Проверка баланса

```php
$balance = $metrics->getBalance();

if ($balance >= 0) {
    echo "Доступный баланс: \${$balance}\n";
} else {
    echo "Использовано (без лимита): \$" . abs($balance) . "\n";
}
```

**Примечание:** Для ключей без лимита возвращается отрицательное значение, показывающее общую сумму расходов.

#### 3. Статистика использования

```php
$stats = $metrics->getUsageStats();

echo "Использовано: \${$stats['total_usage']}\n";
echo "Лимит: \${$stats['limit']}\n";
echo "Остаток: \${$stats['remaining']}\n";
echo "Процент: {$stats['usage_percent']}%\n";
echo "Бесплатный: " . ($stats['is_free_tier'] ? 'Да' : 'Нет') . "\n";
```

**Возвращаемые данные:**
```php
[
    'total_usage' => 12.45,
    'limit' => 100.0,
    'remaining' => 87.55,
    'usage_percent' => 12.45,
    'is_free_tier' => false
]
```

#### 4. Информация о лимитах запросов

```php
$rateLimits = $metrics->getRateLimits();

echo "Запросов: {$rateLimits['requests']}\n";
echo "Интервал: {$rateLimits['interval']}\n";
echo "Описание: {$rateLimits['description']}\n";
```

#### 5. Список доступных моделей

```php
$models = $metrics->getModels();

foreach ($models as $model) {
    echo "ID: {$model['id']}\n";
    echo "Название: {$model['name']}\n";
    echo "Описание: {$model['description']}\n";
    echo "Контекст: {$model['context_length']} токенов\n";
    echo "Prompt: \${$model['pricing']['prompt']} за 1M токенов\n";
    echo "Completion: \${$model['pricing']['completion']} за 1M токенов\n";
    echo "Модальность: {$model['architecture']['modality']}\n";
    echo "---\n";
}
```

#### 6. Информация о конкретной модели

```php
$modelInfo = $metrics->getModelInfo('openai/gpt-4');

echo "Название: {$modelInfo['name']}\n";
echo "Контекст: {$modelInfo['context_length']} токенов\n";
echo "Prompt: \${$modelInfo['pricing']['prompt']} за 1M токенов\n";
echo "Completion: \${$modelInfo['pricing']['completion']} за 1M токенов\n";
```

#### 7. Оценка стоимости запроса

```php
$estimate = $metrics->estimateCost(
    'openai/gpt-3.5-turbo',
    1000,  // токенов в запросе
    500    // токенов в ответе
);

echo "Модель: {$estimate['model']}\n";
echo "Стоимость запроса: \${$estimate['prompt_cost']}\n";
echo "Стоимость ответа: \${$estimate['completion_cost']}\n";
echo "Общая стоимость: \${$estimate['total_cost']}\n";
echo "Токенов: {$estimate['tokens']['total']}\n";
```

**Возвращаемые данные:**
```php
[
    'prompt_cost' => 0.0015,
    'completion_cost' => 0.001,
    'total_cost' => 0.0025,
    'model' => 'openai/gpt-3.5-turbo',
    'tokens' => [
        'prompt' => 1000,
        'completion' => 500,
        'total' => 1500
    ]
]
```

#### 8. Проверка достаточности баланса

```php
$estimatedCost = 0.05; // USD

if ($metrics->hasEnoughBalance($estimatedCost)) {
    echo "Баланса достаточно для выполнения запроса\n";
} else {
    echo "Недостаточно средств!\n";
}
```

#### 9. Информация о генерации

```php
// ID из заголовка X-Request-Id ответа API
$generationId = 'gen_xxxxxxxxxxxxx';

$genInfo = $metrics->getGenerationInfo($generationId);

echo "ID: {$genInfo['id']}\n";
echo "Модель: {$genInfo['model']}\n";
echo "Создано: {$genInfo['created_at']}\n";
echo "Токенов в запросе: {$genInfo['usage']['prompt_tokens']}\n";
echo "Токенов в ответе: {$genInfo['usage']['completion_tokens']}\n";
echo "Всего токенов: {$genInfo['usage']['total_tokens']}\n";
echo "Стоимость: \${$genInfo['cost']}\n";
```

#### 10. Полная информация об аккаунте

```php
$status = $metrics->getAccountStatus();

echo "Ключ: {$status['key_info']['label']}\n";
echo "Баланс: \${$status['balance']}\n";
echo "Использовано: {$status['usage_stats']['usage_percent']}%\n";
echo "Rate limit: {$status['rate_limits']['description']}\n";
```

## Практические примеры

### Мониторинг бюджета

```php
$stats = $metrics->getUsageStats();

if ($stats['usage_percent'] > 80) {
    echo "⚠️ Использовано более 80% бюджета!\n";
    echo "Остаток: \${$stats['remaining']}\n";
}
```

### Выбор оптимальной модели по стоимости

```php
$models = $metrics->getModels();
$promptTokens = 1000;
$completionTokens = 500;

$bestModel = null;
$lowestCost = PHP_FLOAT_MAX;

foreach ($models as $model) {
    if (strpos($model['id'], 'gpt') === false) {
        continue; // Только GPT модели
    }
    
    try {
        $estimate = $metrics->estimateCost(
            $model['id'],
            $promptTokens,
            $completionTokens
        );
        
        if ($estimate['total_cost'] < $lowestCost) {
            $lowestCost = $estimate['total_cost'];
            $bestModel = $model;
        }
    } catch (Exception $e) {
        continue;
    }
}

if ($bestModel !== null) {
    echo "Самая дешёвая модель: {$bestModel['id']}\n";
    echo "Стоимость: \${$lowestCost}\n";
}
```

### Проверка перед дорогим запросом

```php
$modelId = 'openai/gpt-4';
$promptTokens = 5000;
$completionTokens = 2000;

try {
    $estimate = $metrics->estimateCost($modelId, $promptTokens, $completionTokens);
    
    echo "Ожидаемая стоимость: \${$estimate['total_cost']}\n";
    
    if (!$metrics->hasEnoughBalance($estimate['total_cost'])) {
        throw new Exception('Недостаточно средств на балансе!');
    }
    
    // Выполнить запрос к OpenRouter API
    // $response = $openRouter->text2text($modelId, $prompt);
    
} catch (Exception $e) {
    echo "Ошибка: {$e->getMessage()}\n";
}
```

### Автоматические уведомления о балансе

```php
$balance = $metrics->getBalance();
$threshold = 10.0; // USD

if ($balance < $threshold && $balance >= 0) {
    // Отправить уведомление администратору
    $logger->warning('Низкий баланс OpenRouter', [
        'balance' => $balance,
        'threshold' => $threshold
    ]);
    
    // Или отправить email
    // $email->send('admin@example.com', 'Низкий баланс', "Остаток: \${$balance}");
}
```

## Обработка ошибок

Класс использует иерархию исключений:

```php
try {
    $keyInfo = $metrics->getKeyInfo();
} catch (OpenRouterValidationException $e) {
    // Ошибка валидации параметров
    echo "Ошибка валидации: {$e->getMessage()}\n";
} catch (OpenRouterApiException $e) {
    // Ошибка API (4xx, 5xx коды)
    echo "Ошибка API: {$e->getMessage()}\n";
    echo "Код: {$e->getStatusCode()}\n";
    echo "Ответ: {$e->getResponseBody()}\n";
} catch (OpenRouterException $e) {
    // Другие ошибки OpenRouter
    echo "Ошибка: {$e->getMessage()}\n";
} catch (Exception $e) {
    // Общие ошибки
    echo "Неожиданная ошибка: {$e->getMessage()}\n";
}
```

## Логирование

При передаче экземпляра `Logger` в конструктор, все ошибки API автоматически логируются:

```php
$logger = new Logger($loggerConfig);
$metrics = new OpenRouterMetrics($config, $logger);

// Ошибки будут автоматически записаны в лог
try {
    $keyInfo = $metrics->getKeyInfo();
} catch (OpenRouterApiException $e) {
    // Ошибка уже залогирована
}
```

## Примеры использования

Полный пример использования доступен в файле:
```
examples/openrouter_metrics_example.php
```

Запуск примера:
```bash
php examples/openrouter_metrics_example.php
```

## API Reference

### Методы

| Метод | Возвращает | Описание |
|-------|-----------|----------|
| `getKeyInfo()` | `array` | Информация о API ключе |
| `getBalance()` | `float` | Доступный баланс в USD |
| `getUsageStats()` | `array` | Статистика использования |
| `getRateLimits()` | `array` | Информация о rate limits |
| `getModels()` | `array` | Список всех моделей |
| `getModelInfo(string $modelId)` | `array` | Информация о конкретной модели |
| `estimateCost(string $modelId, int $promptTokens, int $completionTokens)` | `array` | Оценка стоимости |
| `hasEnoughBalance(float $estimatedCost)` | `bool` | Проверка баланса |
| `getGenerationInfo(string $generationId)` | `array` | Информация о генерации |
| `getAccountStatus()` | `array` | Полная информация об аккаунте |

## Лучшие практики

1. **Кэширование списка моделей**: Список моделей меняется редко, рекомендуется кэшировать результат `getModels()`.

2. **Регулярная проверка баланса**: Проверяйте баланс перед дорогими операциями.

3. **Мониторинг использования**: Регулярно проверяйте `getUsageStats()` для контроля расходов.

4. **Оценка стоимости**: Используйте `estimateCost()` для прогнозирования расходов.

5. **Обработка ошибок**: Всегда оборачивайте вызовы в try-catch блоки.

## Ограничения

- API ключ должен иметь соответствующие права доступа
- Rate limits определяются OpenRouter для каждого ключа индивидуально
- Информация о генерациях доступна ограниченное время после выполнения запроса

## Поддержка

Для вопросов и предложений:
- Документация OpenRouter: https://openrouter.ai/docs
- GitHub Issues: создайте issue в репозитории проекта

## Changelog

### v1.0.0 (2024)
- Первый релиз
- Все основные функции для работы с метриками
- Поддержка оценки стоимости
- Информация о моделях и балансе

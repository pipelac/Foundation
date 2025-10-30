# 🚀 Быстрый старт: OpenRouter + OpenRouterMetrics

## Установка

```bash
composer install
```

## Базовое использование

### 1. Текстовая генерация

```php
use App\Component\OpenRouter;
use App\Component\Logger;

$logger = new Logger(['directory' => 'logs', 'file_name' => 'app.log']);

$openRouter = new OpenRouter([
    'api_key' => 'sk-or-v1-YOUR_API_KEY',
    'app_name' => 'MyApp',
], $logger);

$response = $openRouter->text2text(
    'openai/gpt-3.5-turbo',
    'Hello, how are you?',
    ['max_tokens' => 100]
);

echo $response;
```

### 2. Распознавание изображений

```php
$description = $openRouter->image2text(
    'openai/gpt-4o',
    'https://example.com/image.jpg',
    'What is in this image?',
    ['max_tokens' => 200]
);

echo $description;
```

### 3. Генерация изображений

```php
$imageData = $openRouter->text2image(
    'google/gemini-2.5-flash-image',
    'Draw a red circle',
    ['max_tokens' => 2000]
);

// $imageData содержит base64-encoded PNG
file_put_contents('image.png', base64_decode($imageData));
```

### 4. Потоковая передача

```php
$openRouter->textStream(
    'openai/gpt-3.5-turbo',
    'Tell me a story',
    function (string $chunk): void {
        echo $chunk;
        flush();
    },
    ['max_tokens' => 500]
);
```

## Метрики и мониторинг

### Проверка баланса

```php
use App\Component\OpenRouterMetrics;

$metrics = new OpenRouterMetrics([
    'api_key' => 'sk-or-v1-YOUR_API_KEY',
], $logger);

$balance = $metrics->getBalance();
echo "Остаток: $" . $balance['limit_remaining'] . "\n";
echo "Лимит: $" . $balance['limit'] . "\n";
```

### Получение списка моделей

```php
$models = $metrics->getModels();
foreach ($models as $model) {
    echo "{$model['id']}: {$model['pricing']['prompt']} / {$model['pricing']['completion']}\n";
}
```

### Оценка стоимости

```php
$cost = $metrics->estimateCost('openai/gpt-4o', 1000, 500);
echo "Стоимость: $" . $cost . "\n";
```

### Проверка достаточности баланса

```php
if ($metrics->hasEnoughBalance(0.05)) {
    echo "Баланса достаточно для операции\n";
} else {
    echo "Недостаточно средств\n";
}
```

## Запуск тестов

### Unit-тесты

```bash
./vendor/bin/phpunit tests/Unit/OpenRouterTest.php
./vendor/bin/phpunit tests/Unit/OpenRouterMetricsTest.php
```

### Интеграционные тесты

```bash
php tests/Integration/OpenRouterCompleteTest.php "sk-or-v1-YOUR_API_KEY"
```

## Логирование

Все операции автоматически логируются:

```
INFO HTTP запрос выполнен [POST chat/completions] код 200
INFO ✓ OpenRouter::text2text() - успешно
ERROR Сервер OpenRouter вернул ошибку
```

Логи сохраняются в директории, указанной при создании Logger.

## Обработка ошибок

```php
use App\Component\Exception\OpenRouterException;
use App\Component\Exception\OpenRouterApiException;
use App\Component\Exception\OpenRouterValidationException;

try {
    $response = $openRouter->text2text('gpt-3.5-turbo', 'Hello');
} catch (OpenRouterValidationException $e) {
    echo "Ошибка валидации: " . $e->getMessage();
} catch (OpenRouterApiException $e) {
    echo "Ошибка API: " . $e->getMessage();
} catch (OpenRouterException $e) {
    echo "Общая ошибка: " . $e->getMessage();
}
```

## 💰 Важно: Стоимость операций

| Операция | Модель | Примерная стоимость |
|----------|--------|---------------------|
| text2text | gpt-3.5-turbo | ~$0.000003 |
| textStream | gpt-3.5-turbo | ~$0.000005 |
| image2text | gpt-4o | ~$0.003 |
| **text2image** | **gemini-image** | **~$0.078** |

⚠️ **Генерация изображений - ДОРОГАЯ операция!**

## 📚 Полная документация

- **FINAL_SUMMARY_RU.md** - Финальная сводка всех тестов
- **OPENROUTER_COMPLETE_TEST_RESULTS.md** - Полный отчет о тестировании
- **OPENROUTER_TEST_REPORT.md** - Базовый отчет
- **OPENROUTER_README.md** - Подробное руководство

## ✅ Статус

- ✅ Все методы протестированы
- ✅ Логирование работает
- ✅ Готово к продакшену
- ✅ Потрачено: $0.081 из $1.00 (8.1%)

## 🎉 Готово к использованию!

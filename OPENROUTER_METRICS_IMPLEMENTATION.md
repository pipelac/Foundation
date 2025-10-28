# OpenRouterMetrics - Документация внедрения

## Обзор

Реализован новый класс `OpenRouterMetrics` для полноценной работы с метриками и внутренней информацией сервиса OpenRouter API.

## Реализованный функционал

### Основные возможности

✅ **Мониторинг баланса и лимитов**
- Получение текущего баланса API ключа
- Информация о лимитах расходов
- Проверка достаточности средств
- Rate limits (лимиты запросов)

✅ **Статистика использования**
- Общая статистика использования токенов
- Расчет процента использования бюджета
- Информация о потраченных средствах
- Данные о типе ключа (бесплатный/платный)

✅ **Работа с моделями**
- Получение списка всех доступных моделей
- Детальная информация о каждой модели
- Стоимость использования моделей (prompt/completion)
- Параметры моделей (контекст, архитектура)

✅ **Оценка стоимости**
- Предварительный расчет стоимости запроса
- Оценка на основе количества токенов
- Расчет стоимости prompt и completion отдельно

✅ **Информация о генерациях**
- Получение данных о выполненных генерациях
- Статистика использованных токенов
- Фактическая стоимость запросов

## Созданные файлы

### Основной класс
```
src/OpenRouterMetrics.class.php (25KB)
```
Полнофункциональный класс с 10 публичными методами.

### Документация
```
docs/OPENROUTER_METRICS.md (15KB)
```
Полная документация с описанием всех методов, примерами использования и API reference.

```
OPENROUTER_METRICS_QUICKSTART.md (6.6KB)
```
Краткое руководство для быстрого старта с практическими примерами.

```
CHANGELOG_OPENROUTER_METRICS.md (10.5KB)
```
Подробный changelog с описанием всех возможностей класса.

### Примеры
```
examples/openrouter_metrics_example.php (8.4KB)
```
Полноценный пример использования всех методов класса.

### Обновленные файлы
```
README.md
```
Добавлен раздел об OpenRouterMetrics с примерами использования.

## Архитектура

### Используемые паттерны

**Constructor Injection**
```php
public function __construct(array $config, ?Logger $logger = null)
```

**Композиция**
- Использует `Http` класс для всех HTTP запросов
- Опциональная интеграция с `Logger` для логирования

**Валидация на всех уровнях**
- Валидация конфигурации в конструкторе
- Валидация параметров методов
- Проверка структуры ответов API

### Обработка ошибок

Использует существующую иерархию исключений:
- `OpenRouterValidationException` - ошибки валидации
- `OpenRouterApiException` - ошибки API
- `OpenRouterException` - базовые ошибки

### Типизация

**Строгая типизация**
```php
declare(strict_types=1);
```

**PHPDoc для всех методов**
```php
/**
 * @param string $modelId
 * @param int $promptTokens
 * @param int $completionTokens
 * @return array<string, mixed>
 * @throws OpenRouterValidationException
 */
```

## API методы

### 1. getKeyInfo()
Получает полную информацию о API ключе.

**Возвращает:**
- label (string) - название ключа
- usage (float) - использованная сумма
- limit (float|null) - лимит расходов
- is_free_tier (bool) - бесплатный уровень
- rate_limit (array) - лимиты запросов

### 2. getBalance()
Возвращает текущий доступный баланс в USD.

**Особенности:**
- Для ключей с лимитом: возвращает остаток
- Для ключей без лимита: возвращает отрицательное значение (потраченную сумму)

### 3. getUsageStats()
Детальная статистика использования.

**Возвращает:**
- total_usage (float) - всего потрачено
- limit (float|null) - лимит
- remaining (float) - остаток
- usage_percent (float) - процент использования
- is_free_tier (bool) - тип аккаунта

### 4. getRateLimits()
Информация о лимитах запросов.

**Возвращает:**
- requests (int) - количество запросов
- interval (string) - интервал времени
- description (string) - читаемое описание

### 5. getModels()
Список всех доступных моделей с параметрами.

**Возвращает массив моделей с:**
- id, name, description
- pricing (prompt, completion, image, request)
- context_length
- architecture (modality, tokenizer, instruct_type)
- top_provider информация

### 6. getModelInfo($modelId)
Детальная информация о конкретной модели.

**Параметры:**
- modelId (string) - идентификатор модели

### 7. estimateCost($modelId, $promptTokens, $completionTokens)
Оценка стоимости запроса до его выполнения.

**Возвращает:**
- prompt_cost (float) - стоимость запроса
- completion_cost (float) - стоимость ответа
- total_cost (float) - общая стоимость
- model (string) - модель
- tokens (array) - детали по токенам

### 8. hasEnoughBalance($estimatedCost)
Проверка достаточности баланса.

**Возвращает:**
- bool - true если баланса достаточно

### 9. getGenerationInfo($generationId)
Информация о выполненной генерации.

**Параметры:**
- generationId (string) - ID генерации из X-Request-Id

**Возвращает:**
- id, model, created_at
- usage (prompt_tokens, completion_tokens, total_tokens)
- cost (float) - фактическая стоимость

### 10. getAccountStatus()
Полная информация о состоянии аккаунта.

**Возвращает:**
- key_info (array)
- balance (float)
- usage_stats (array)
- rate_limits (array)

## Примеры использования

### Базовый пример
```php
use App\Component\OpenRouterMetrics;
use App\Config\ConfigLoader;

$config = ConfigLoader::load(__DIR__ . '/config/openrouter.json');
$metrics = new OpenRouterMetrics($config);

$balance = $metrics->getBalance();
echo "Баланс: \${$balance}\n";
```

### Мониторинг бюджета
```php
$stats = $metrics->getUsageStats();
if ($stats['usage_percent'] > 80) {
    echo "⚠️ Использовано более 80% бюджета!\n";
}
```

### Оценка стоимости
```php
$estimate = $metrics->estimateCost('openai/gpt-3.5-turbo', 1000, 500);

if ($metrics->hasEnoughBalance($estimate['total_cost'])) {
    // Выполнить запрос к OpenRouter
    $response = $openRouter->text2text($model, $prompt);
} else {
    echo "Недостаточно средств!\n";
}
```

### Выбор оптимальной модели
```php
$models = $metrics->getModels();
$cheapest = null;
$minCost = PHP_FLOAT_MAX;

foreach ($models as $model) {
    $estimate = $metrics->estimateCost($model['id'], 1000, 500);
    if ($estimate['total_cost'] < $minCost) {
        $minCost = $estimate['total_cost'];
        $cheapest = $model;
    }
}
```

## Интеграция с существующим кодом

### С логированием
```php
$logger = new Logger($loggerConfig);
$metrics = new OpenRouterMetrics($config, $logger);

// Все ошибки автоматически логируются
$balance = $metrics->getBalance();
```

### С существующим OpenRouter классом
```php
$openRouter = new OpenRouter($config, $logger);
$metrics = new OpenRouterMetrics($config, $logger);

// Проверка перед запросом
$estimate = $metrics->estimateCost($model, 1000, 500);
if ($metrics->hasEnoughBalance($estimate['total_cost'])) {
    $response = $openRouter->text2text($model, $prompt);
}
```

## Технические детали

### Константы
```php
private const BASE_URL = 'https://openrouter.ai/api/v1';
private const DEFAULT_TIMEOUT = 30;
```

### Приватные методы
- `validateConfiguration()` - валидация конфигурации
- `validateNotEmpty()` - валидация строковых параметров
- `buildHeaders()` - формирование заголовков
- `sendRequest()` - выполнение HTTP запросов
- `calculateCostFromUsage()` - расчет стоимости
- `logError()` - логирование ошибок

### Зависимости
- PHP 8.1+
- App\Component\Http
- App\Component\Logger (опционально)
- GuzzleHttp\Guzzle (через Http)

## Стандарты кода

✅ Строгая типизация всех параметров и возвращаемых значений
✅ PHPDoc документация на русском языке
✅ Описательные имена классов и методов
✅ Обработка исключений на каждом уровне
✅ PSR-12 стандарт кодирования
✅ PHP 8.1+ синтаксис

## Тестирование

### Ручное тестирование
```bash
php examples/openrouter_metrics_example.php
```

### Проверка синтаксиса
```bash
php -l src/OpenRouterMetrics.class.php
```

## Использованные OpenRouter API endpoints

1. **GET /api/v1/auth/key**
   - Информация о ключе API
   - Баланс и лимиты
   - Rate limits

2. **GET /api/v1/models**
   - Список доступных моделей
   - Параметры и стоимость моделей

3. **GET /api/v1/generation**
   - Информация о конкретной генерации
   - Использованные токены
   - Фактическая стоимость

## Совместимость

- ✅ Полностью совместим с существующей архитектурой
- ✅ Использует те же конфигурационные файлы
- ✅ Не конфликтует с OpenRouter классом
- ✅ Может работать независимо

## Документация

| Файл | Назначение |
|------|-----------|
| `docs/OPENROUTER_METRICS.md` | Полная документация |
| `OPENROUTER_METRICS_QUICKSTART.md` | Быстрый старт |
| `CHANGELOG_OPENROUTER_METRICS.md` | История изменений |
| `examples/openrouter_metrics_example.php` | Примеры кода |

## Ограничения

1. Требуется валидный API ключ OpenRouter
2. Информация о генерациях хранится ограниченное время
3. Rate limits индивидуальны для каждого ключа
4. Стоимость моделей может меняться

## Рекомендации

1. **Кэширование**: кэшируйте список моделей (меняется редко)
2. **Мониторинг**: регулярно проверяйте баланс
3. **Оценка**: используйте `estimateCost()` перед дорогими операциями
4. **Логирование**: всегда передавайте Logger для отладки
5. **Обработка ошибок**: оборачивайте вызовы в try-catch

## Ссылки

- [OpenRouter API Documentation](https://openrouter.ai/docs)
- [OpenRouter Dashboard](https://openrouter.ai/dashboard)

## Автор

Реализовано в соответствии со стандартами проекта PHP 8.1+ Utilities.

## Лицензия

MIT (как и весь проект)

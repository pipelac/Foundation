# OpenAI - Быстрый старт

Краткое руководство по началу работы с классом OpenAi.

## Установка

```bash
# Класс уже включен в проект
# Убедитесь, что установлены зависимости
composer install
```

## Получение API ключа

1. Зарегистрируйтесь на [platform.openai.com](https://platform.openai.com/)
2. Перейдите в [API keys](https://platform.openai.com/api-keys)
3. Создайте новый API ключ
4. Скопируйте ключ (он показывается только один раз!)

## Базовая настройка

### Вариант 1: Прямая инициализация

```php
<?php

require_once 'autoload.php';

use App\Component\OpenAi;

$openAi = new OpenAi([
    'api_key' => 'sk-proj-your-api-key-here'
]);
```

### Вариант 2: Через конфигурационный файл

Отредактируйте `config/openai.json`:

```json
{
    "api_key": "sk-proj-your-actual-api-key",
    "organization": "",
    "timeout": 60,
    "retries": 3
}
```

```php
<?php

use App\Component\OpenAi;
use App\Component\Logger;
use App\Config\ConfigLoader;

$config = ConfigLoader::load(__DIR__ . '/config/openai.json');
$logger = new Logger(['directory' => __DIR__ . '/logs']);
$openAi = new OpenAi($config, $logger);
```

### Вариант 3: Через переменные окружения (рекомендуется)

```bash
export OPENAI_API_KEY="sk-proj-your-api-key"
```

```php
<?php

$openAi = new OpenAi([
    'api_key' => getenv('OPENAI_API_KEY')
]);
```

## 5 основных примеров

### 1. Простой чат

```php
$response = $openAi->text2text('Привет! Расскажи анекдот');
echo $response;
```

### 2. Генерация изображения

```php
$imageUrl = $openAi->text2image('Кот в космосе, реалистичный стиль');
echo "Изображение: {$imageUrl}\n";
```

### 3. Анализ изображения

```php
$description = $openAi->image2text(
    imageUrl: 'https://example.com/photo.jpg',
    question: 'Что изображено на фото?'
);
echo $description;
```

### 4. Потоковый ответ

```php
$openAi->textStream(
    prompt: 'Напиши короткое эссе о космосе',
    callback: function(string $chunk) {
        echo $chunk;
        flush();
    }
);
```

### 5. Модерация контента

```php
$result = $openAi->moderation('Текст для проверки');

if ($result['flagged']) {
    echo "⚠️ Найдены нарушения!\n";
} else {
    echo "✅ Контент безопасен\n";
}
```

## Обработка ошибок

```php
use App\Component\Exception\OpenAiException;
use App\Component\Exception\OpenAiValidationException;
use App\Component\Exception\OpenAiApiException;

try {
    $response = $openAi->text2text('Привет!');
} catch (OpenAiValidationException $e) {
    // Неверные параметры
    echo "Ошибка валидации: {$e->getMessage()}";
} catch (OpenAiApiException $e) {
    // Ошибка от API
    echo "API ошибка [{$e->getStatusCode()}]: {$e->getMessage()}";
} catch (OpenAiException $e) {
    // Другие ошибки
    echo "Ошибка: {$e->getMessage()}";
}
```

## Продвинутое использование

### Чат с контекстом

```php
$response = $openAi->text2text(
    prompt: 'Как создать REST API?',
    model: 'gpt-4o',
    options: [
        'temperature' => 0.7,
        'max_tokens' => 1000,
        'system' => 'Ты - опытный backend разработчик с 10+ годами опыта'
    ]
);
```

### Генерация HD изображения

```php
$imageUrl = $openAi->text2image(
    prompt: 'Профессиональный логотип IT компании, минималистичный дизайн',
    model: 'dall-e-3',
    options: [
        'size' => '1024x1024',
        'quality' => 'hd',
        'style' => 'natural'
    ]
);
```

### Создание эмбеддингов

```php
$embeddings = $openAi->embeddings(
    input: [
        'Документ 1: О машинном обучении',
        'Документ 2: О веб-разработке',
        'Документ 3: О базах данных'
    ],
    model: 'text-embedding-3-small'
);

foreach ($embeddings as $i => $embedding) {
    echo "Документ " . ($i + 1) . ": " . count($embedding) . " измерений\n";
}
```

## Полезные советы

### 💡 Экономия токенов

```php
// Используйте gpt-4o-mini для простых задач
$openAi->text2text('Привет', 'gpt-4o-mini');

// Ограничивайте max_tokens
$openAi->text2text('Объясни AI', options: ['max_tokens' => 100]);
```

### 🔒 Безопасность

```php
// НИКОГДА не коммитьте API ключи в Git!
// Используйте .env файлы или переменные окружения

// Добавьте в .gitignore:
# config/openai.json  # если храните ключ здесь
# .env
```

### 📊 Мониторинг

```php
// Включите логирование для отладки
$logger = new Logger(['directory' => './logs']);
$openAi = new OpenAi($config, $logger);

// Логи будут содержать информацию о всех запросах и ошибках
```

### ⚡ Производительность

```php
// Кэшируйте эмбеддинги
$cache = new FileCache(['directory' => './cache']);
$cacheKey = md5($text);

if (!$cache->has($cacheKey)) {
    $embedding = $openAi->embeddings($text);
    $cache->set($cacheKey, $embedding, 3600);
}
```

## Стоимость

Примерные цены (актуальны на момент написания):

| Модель | Входные токены | Выходные токены |
|--------|----------------|-----------------|
| gpt-4o | $2.50 / 1M | $10.00 / 1M |
| gpt-4o-mini | $0.15 / 1M | $0.60 / 1M |
| dall-e-3 | $0.040 / изображение (standard) | - |
| text-embedding-3-small | $0.02 / 1M | - |

⚠️ Актуальные цены смотрите на [OpenAI Pricing](https://openai.com/pricing)

## Ограничения

### Rate Limits (по умолчанию для новых аккаунтов)

- **GPT-4o**: 500 запросов/мин
- **GPT-4o-mini**: 500 запросов/мин
- **DALL-E 3**: 5 запросов/мин
- **Embeddings**: 3000 запросов/мин

### Token Limits

- **GPT-4o**: 128K контекст
- **GPT-4o-mini**: 128K контекст
- **Embeddings**: 8191 токенов

## Следующие шаги

1. 📖 Прочитайте полную документацию: `OPENAI_README.md`
2. 💻 Изучите примеры: `examples/openai_example.php`
3. 🧪 Запустите тесты: `vendor/bin/phpunit tests/Unit/OpenAiTest.php`
4. 🌐 Посетите [OpenAI Playground](https://platform.openai.com/playground) для экспериментов

## Поддержка

- 📝 Issues: Создайте issue в репозитории
- 📚 Документация OpenAI: https://platform.openai.com/docs
- 💬 Community: https://community.openai.com

## Полезные ссылки

- [OpenAI Platform](https://platform.openai.com/)
- [API Reference](https://platform.openai.com/docs/api-reference)
- [Model Pricing](https://openai.com/pricing)
- [Usage Dashboard](https://platform.openai.com/usage)
- [Rate Limits](https://platform.openai.com/account/limits)

---

✨ **Готово!** Теперь вы можете начать использовать OpenAI API в своих проектах!

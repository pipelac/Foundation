# OpenRouter - Генерация изображений через text2image()

## ✅ Подтверждено: OpenRouter ПОДДЕРЖИВАЕТ генерацию изображений!

**Дата тестирования:** 2025-11-10  
**Статус:** ✅ РАБОТАЕТ  
**Endpoint:** `/chat/completions`  
**Документация:** https://openrouter.ai/docs/features/multimodal/image-generation

## Рабочие модели (протестировано)

### 1. google/gemini-2.5-flash-image-preview ✅
- **Статус:** ✅ РАБОТАЕТ (протестировано 2025-11-10)
- **Описание:** Preview версия с возможностью генерации изображений
- **Время генерации:** ~8-9 секунд
- **Размер изображения:** ~1.5MB (PNG, 1280x1280)
- **Формат ответа:** data URI (data:image/png;base64,...)
- **Рекомендация:** ⭐⭐⭐⭐⭐ Отлично для production

```php
$imageDataUri = $openRouter->text2image(
    'google/gemini-2.5-flash-image-preview',
    'Create a modern, vibrant illustration representing AI breakthrough',
    ['max_tokens' => 4096]
);

// Извлечь base64 из data URI
$parts = explode(',', $imageDataUri, 2);
$imageData = base64_decode($parts[1]);
file_put_contents('image.png', $imageData);
```

### 2. google/gemini-2.5-flash-image
- **Статус:** ✅ ПОДДЕРЖИВАЕТСЯ
- **Описание:** Стабильная версия генерации изображений от Google
- **Рекомендация:** ⭐⭐⭐⭐ Для production

### 3. anthropic/claude-3-5-sonnet
- **Статус:** ⚠️ НЕ ПОДТВЕРЖДЕНО
- **Описание:** Claude может поддерживать генерацию изображений, требует тестирования
- **Рекомендация:** Требует дополнительной проверки

## Формат ответа OpenRouter

OpenRouter возвращает изображение в специальном поле `message.images[]`:

```json
{
  "choices": [
    {
      "message": {
        "role": "assistant",
        "content": "",
        "images": [
          {
            "type": "image_url",
            "image_url": {
              "url": "data:image/png;base64,iVBORw0KGgoAAAA..."
            },
            "index": 0
          }
        ]
      }
    }
  ],
  "usage": {
    "prompt_tokens": 8,
    "completion_tokens": 1290,
    "total_tokens": 1298,
    "image_tokens": 1290
  }
}
```

## Использование метода text2image()

### Базовое использование

```php
use App\Component\OpenRouter;

$openRouter = new OpenRouter([
    'api_key' => 'sk-or-v1-...',
    'app_name' => 'MyApp',
    'timeout' => 120, // Важно! Генерация может занять 10-30 сек
], $logger);

// Генерация изображения
$imageDataUri = $openRouter->text2image(
    'google/gemini-2.5-flash-image-preview',
    'A simple red circle on white background',
    ['max_tokens' => 4096]
);

// Результат - data URI формата: data:image/png;base64,...
echo "Получен data URI длиной: " . strlen($imageDataUri) . " символов\n";
```

### Сохранение в файл

```php
// Извлекаем base64 из data URI
$parts = explode(',', $imageDataUri, 2);
if (count($parts) === 2) {
    $base64Data = $parts[1];
    $imageData = base64_decode($base64Data);
    
    // Сохраняем файл
    file_put_contents('generated_image.png', $imageData);
    echo "Изображение сохранено: " . filesize('generated_image.png') . " байт\n";
}
```

### Отправка в Telegram

```php
use App\Component\Telegram;

// Сначала сохраняем файл (извлекаем base64)
$parts = explode(',', $imageDataUri, 2);
$imageData = base64_decode($parts[1]);
$filepath = '/tmp/image.png';
file_put_contents($filepath, $imageData);

// Отправляем через Telegram
$telegram = new Telegram([...], $logger);
$telegram->sendPhoto('CHAT_ID', $filepath, [
    'caption' => 'Сгенерировано через OpenRouter AI'
]);
```

### С использованием illustration_generation_prompt_v1.txt

```php
// Загружаем промпт-шаблон
$promptTemplate = file_get_contents('prompts/illustration_generation_prompt_v1.txt');

// Данные новости
$newsData = [
    'title' => 'Прорыв в области искусственного интеллекта',
    'summary' => 'Российские ученые создали революционную систему...',
];

// Сначала используем AI для создания промпта генерации
$analysisResponse = $openRouter->chatWithMessages(
    'deepseek/deepseek-chat',
    [
        ['role' => 'system', 'content' => $promptTemplate],
        ['role' => 'user', 'content' => json_encode($newsData, JSON_UNESCAPED_UNICODE)]
    ]
);

$analysis = json_decode($analysisResponse['content'], true);
$imagePrompt = $analysis['final_prompt'];

// Генерируем изображение
$imageDataUri = $openRouter->text2image(
    'google/gemini-2.5-flash-image-preview',
    $imagePrompt,
    ['max_tokens' => 4096]
);
```

## Параметры генерации

### max_tokens (обязательный)
```php
['max_tokens' => 4096]  // Рекомендуется для image generation
```

### Дополнительные параметры (если поддерживаются моделью)
```php
[
    'max_tokens' => 4096,
    'temperature' => 0.7,  // Креативность (если поддерживается)
]
```

## Лучшие практики

### 1. Промпты для генерации
- ✅ Используйте английский язык для промптов
- ✅ Будьте конкретны: стиль, композиция, цвета
- ✅ Указывайте желаемый формат (например, "flat design", "photorealistic")
- ❌ Не используйте кириллицу в промпте (хотя модель поймёт)
- ❌ Не просите текст на изображении (модели плохо его генерируют)

**Хороший промпт:**
```
Create a modern, vibrant flat illustration of AI neural network. 
Style: bold colors, high contrast, minimalist design. 
No text or labels in the image.
```

**Плохой промпт:**
```
Нарисуй что-нибудь красивое про AI
```

### 2. Обработка результатов
```php
try {
    $imageDataUri = $openRouter->text2image($model, $prompt, $options);
    
    // Проверяем формат
    if (str_starts_with($imageDataUri, 'data:image')) {
        // Извлекаем base64
        list($type, $base64) = explode(',', $imageDataUri, 2);
        $imageData = base64_decode($base64);
        
        // Сохраняем
        $filename = 'image_' . time() . '.png';
        file_put_contents($filename, $imageData);
    }
    
} catch (OpenRouterException $e) {
    logger->error('Image generation failed', [
        'model' => $model,
        'error' => $e->getMessage(),
    ]);
}
```

### 3. Timeout и ресурсы
- ⏱️ Генерация изображения занимает 8-30 секунд
- 💾 Размер результата: 500KB - 2MB
- 🔧 Установите timeout минимум 120 секунд
- 📊 Учитывайте лимиты API и стоимость

```php
$openRouter = new OpenRouter([
    'api_key' => '...',
    'timeout' => 120,  // ⚠️ Важно!
], $logger);
```

## Стоимость и лимиты

**Примерная стоимость (зависит от модели):**
- google/gemini-2.5-flash-image-preview: ~$0.001-0.01 за изображение
- Проверяйте актуальные цены: https://openrouter.ai/docs/pricing

**Лимиты:**
- Размер промпта: до 4096 токенов
- Размер изображения: зависит от модели (обычно 1024x1024 или 1280x1280)
- Rate limits: зависят от вашего аккаунта OpenRouter

## Обработка ошибок

```php
use App\Component\Exception\OpenRouterException;
use App\Component\Exception\OpenRouterApiException;
use App\Component\Exception\OpenRouterValidationException;
use App\Component\Exception\OpenRouterNetworkException;

try {
    $imageDataUri = $openRouter->text2image($model, $prompt, $options);
    
} catch (OpenRouterValidationException $e) {
    // Ошибки валидации параметров
    echo "Неверные параметры: " . $e->getMessage();
    
} catch (OpenRouterApiException $e) {
    // Ошибки от API (rate limit, недостаточно средств, и т.д.)
    echo "Ошибка API: " . $e->getMessage();
    echo "HTTP код: " . $e->getStatusCode();
    
} catch (OpenRouterNetworkException $e) {
    // Сетевые ошибки (timeout, connection failed)
    echo "Сетевая ошибка: " . $e->getMessage();
    
} catch (OpenRouterException $e) {
    // Общие ошибки
    echo "Ошибка: " . $e->getMessage();
}
```

## Тестирование

Простой тест для проверки генерации:

```php
<?php
require_once 'vendor/autoload.php';

use App\Component\Logger;
use App\Component\OpenRouter;

$logger = new Logger(['directory' => 'logs', 'file_name' => 'test.log', 'min_level' => 'debug']);
$openRouter = new OpenRouter([
    'api_key' => 'YOUR_API_KEY',
    'timeout' => 120,
], $logger);

try {
    echo "Генерация изображения...\n";
    $start = microtime(true);
    
    $imageDataUri = $openRouter->text2image(
        'google/gemini-2.5-flash-image-preview',
        'A simple red circle on white background',
        ['max_tokens' => 4096]
    );
    
    $duration = round(microtime(true) - $start, 2);
    echo "✅ Успех! Время: {$duration}с\n";
    echo "Размер: " . strlen($imageDataUri) . " символов\n";
    
    // Сохраняем
    list($type, $base64) = explode(',', $imageDataUri, 2);
    file_put_contents('test.png', base64_decode($base64));
    echo "Сохранено: test.png\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}
```

**Запуск:**
```bash
php test_image_generation.php
```

## История изменений

**2025-11-10:**
- ✅ Подтверждена поддержка генерации изображений в OpenRouter
- ✅ Протестирована модель `google/gemini-2.5-flash-image-preview`
- ✅ Исправлен метод `text2image()` для корректной обработки ответа
- ✅ Добавлена поддержка формата `message.images[]`
- ⚠️ Удалены неверные утверждения о проблемах с генерацией

**Предыдущие версии:**
- ❌ Ошибочно указывалось, что OpenRouter не поддерживает генерацию изображений
- ❌ Неверный формат парсинга ответа API

## Дополнительные ресурсы

- [OpenRouter Image Generation Docs](https://openrouter.ai/docs/features/multimodal/image-generation)
- [OpenRouter API Reference](https://openrouter.ai/docs/api-reference)
- [Supported Models](https://openrouter.ai/docs/models)
- [Pricing Information](https://openrouter.ai/docs/pricing)

## FAQ

**Q: Почему content пустое, а изображение в images[]?**  
A: Это новый формат OpenRouter для мультимодального контента. Изображения возвращаются отдельно от текста.

**Q: Можно ли сгенерировать несколько изображений за раз?**  
A: Зависит от модели. Проверяйте документацию конкретной модели.

**Q: Почему изображение не отображается в Telegram?**  
A: Telegram не принимает data URI напрямую. Сначала извлеките base64 и сохраните как файл PNG.

**Q: Какой размер изображений генерируется?**  
A: google/gemini-2.5-flash-image-preview генерирует ~1280x1280 PNG (~1.5MB).

---

**Последнее обновление:** 2025-11-10  
**Версия OpenRouter API:** v1  
**Статус документации:** ✅ Актуально и протестировано

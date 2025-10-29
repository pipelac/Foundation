# OpenRouter - Документация

## Описание

`OpenRouter` - класс для работы с OpenRouter API, предоставляющий доступ к различным AI моделям через единый интерфейс. Поддерживает текстовую генерацию, работу с изображениями, аудио, PDF и потоковую передачу данных.

## Возможности

- ✅ **text2text** - Текстовая генерация (ChatGPT, Claude, Gemini и др.)
- ✅ **text2image** - Генерация изображений (DALL-E, Stable Diffusion)
- ✅ **image2text** - Распознавание и описание изображений (GPT-4 Vision)
- ✅ **audio2text** - Распознавание речи (Whisper)
- ✅ **text2audio** - Синтез речи (TTS)
- ✅ **pdf2text** - Извлечение текста из PDF
- ✅ **Streaming** - Потоковая передача текста
- ✅ Строгая типизация и валидация
- ✅ Поддержка всех моделей OpenRouter
- ✅ Автоматическая обработка ошибок
- ✅ Интеграция с Logger
- ✅ Настраиваемые таймауты и retry

## Требования

- PHP 8.1+
- Расширения: `json`, `curl`
- API ключ OpenRouter ([получить здесь](https://openrouter.ai/))
- Composer (для Guzzle HTTP клиента)

## Установка

```bash
composer install
```

## Конфигурация

Создайте файл `config/openrouter.json`:

```json
{
    "api_key": "sk-or-v1-...",
    "app_name": "MyApp",
    "timeout": 60,
    "retries": 3
}
```

### Параметры конфигурации

| Параметр | Тип | Обязательный | По умолчанию | Описание |
|----------|-----|--------------|--------------|----------|
| `api_key` | string | Да | - | API ключ OpenRouter |
| `app_name` | string | Нет | "BasicUtilitiesApp" | Название приложения |
| `timeout` | int | Нет | 60 | Таймаут запросов в секундах |
| `retries` | int | Нет | - | Количество повторных попыток |

## Использование

### Инициализация

```php
use App\Component\OpenRouter;
use App\Component\Logger;
use App\Config\ConfigLoader;

// С логгером
$config = ConfigLoader::load(__DIR__ . '/config/openrouter.json');
$loggerConfig = ConfigLoader::load(__DIR__ . '/config/logger.json');
$logger = new Logger($loggerConfig);
$openRouter = new OpenRouter($config, $logger);

// Без логгера
$openRouter = new OpenRouter($config);
```

### Text to Text (Текстовая генерация)

```php
// Простой запрос
$response = $openRouter->text2text(
    'openai/gpt-3.5-turbo',
    'Привет! Расскажи анекдот про программистов'
);
echo $response;

// С дополнительными параметрами
$response = $openRouter->text2text(
    'anthropic/claude-3-sonnet',
    'Объясни квантовую физику простыми словами',
    [
        'temperature' => 0.7,
        'max_tokens' => 1000,
        'top_p' => 0.9,
    ]
);

// Использование разных моделей
$models = [
    'openai/gpt-4',
    'openai/gpt-3.5-turbo',
    'anthropic/claude-3-opus',
    'google/gemini-pro',
    'meta-llama/llama-3-70b-instruct',
];

foreach ($models as $model) {
    $response = $openRouter->text2text($model, 'Привет!');
    echo "{$model}: {$response}\n\n";
}
```

### Text to Image (Генерация изображений)

```php
// Генерация изображения
$imageUrl = $openRouter->text2image(
    'openai/dall-e-3',
    'Красивый закат над океаном, фотореалистично'
);

// Сохранить изображение
file_put_contents('sunset.png', file_get_contents($imageUrl));

// С параметрами качества
$imageUrl = $openRouter->text2image(
    'stability-ai/stable-diffusion-xl',
    'Футуристический город, киберпанк стиль',
    [
        'size' => '1024x1024',
        'quality' => 'hd',
        'style' => 'vivid',
    ]
);
```

### Image to Text (Распознавание изображений)

```php
// Описание изображения
$description = $openRouter->image2text(
    'openai/gpt-4-vision-preview',
    'https://example.com/photo.jpg',
    'Что изображено на этой фотографии?'
);
echo $description;

// Анализ содержимого
$analysis = $openRouter->image2text(
    'anthropic/claude-3-opus',
    'https://example.com/document.jpg',
    'Извлеки текст с этого изображения'
);

// Проверка на содержимое
$result = $openRouter->image2text(
    'openai/gpt-4-vision-preview',
    'https://example.com/photo.jpg',
    'Есть ли на изображении люди? Ответь да или нет'
);
```

### Audio to Text (Распознавание речи)

```php
// Транскрибация аудио файла
$transcript = $openRouter->audio2text(
    'openai/whisper-1',
    'https://example.com/audio.mp3'
);
echo "Транскрипция: {$transcript}\n";

// С указанием языка
$transcript = $openRouter->audio2text(
    'openai/whisper-1',
    '/path/to/local/audio.mp3',
    [
        'language' => 'ru',
        'temperature' => 0.2,
    ]
);

// С подсказкой для модели
$transcript = $openRouter->audio2text(
    'openai/whisper-1',
    'https://example.com/meeting.mp3',
    [
        'prompt' => 'Это запись совещания о проекте',
    ]
);
```

### Text to Audio (Синтез речи)

```php
// Генерация речи
$audioData = $openRouter->text2audio(
    'openai/tts-1',
    'Привет! Это синтезированная речь.',
    'alloy' // Голос
);

// Сохранить аудио
file_put_contents('speech.mp3', $audioData);

// Разные голоса
$voices = ['alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer'];

foreach ($voices as $voice) {
    $audio = $openRouter->text2audio(
        'openai/tts-1-hd',
        'Тестирование голоса',
        $voice
    );
    file_put_contents("voice_{$voice}.mp3", $audio);
}
```

### PDF to Text (Извлечение текста из PDF)

```php
// Извлечь текст из PDF
$text = $openRouter->pdf2text(
    'anthropic/claude-3-haiku',
    'https://example.com/document.pdf'
);
echo $text;

// С локальным файлом
$text = $openRouter->pdf2text(
    'anthropic/claude-3-sonnet',
    '/path/to/document.pdf'
);

// Анализ документа
$summary = $openRouter->pdf2text(
    'anthropic/claude-3-opus',
    'https://example.com/report.pdf',
    ['prompt' => 'Составь краткое резюме этого документа']
);
```

### Streaming (Потоковая передача)

```php
// Потоковый вывод текста
$openRouter->textStream(
    'openai/gpt-3.5-turbo',
    'Расскажи длинную историю про космос',
    function (string $chunk) {
        echo $chunk;
        flush();
    }
);

// С параметрами
$openRouter->textStream(
    'anthropic/claude-3-sonnet',
    'Напиши подробную статью о PHP 8.1',
    function (string $chunk) {
        // Сохранение в файл по мере получения
        file_put_contents('article.txt', $chunk, FILE_APPEND);
        echo $chunk;
    },
    [
        'temperature' => 0.8,
        'max_tokens' => 2000,
    ]
);
```

## Примеры использования

### Чат-бот

```php
class ChatBot
{
    private OpenRouter $ai;
    private array $conversationHistory = [];
    
    public function __construct(OpenRouter $ai)
    {
        $this->ai = $ai;
    }
    
    public function chat(string $userMessage): string
    {
        // Добавить сообщение пользователя в историю
        $this->conversationHistory[] = [
            'role' => 'user',
            'content' => $userMessage
        ];
        
        // Получить ответ
        $response = $this->ai->text2text(
            'openai/gpt-4',
            $this->buildPrompt()
        );
        
        // Добавить ответ в историю
        $this->conversationHistory[] = [
            'role' => 'assistant',
            'content' => $response
        ];
        
        return $response;
    }
    
    private function buildPrompt(): string
    {
        $prompt = "Ты дружелюбный помощник.\n\n";
        
        foreach ($this->conversationHistory as $message) {
            $role = $message['role'] === 'user' ? 'Пользователь' : 'Ассистент';
            $prompt .= "{$role}: {$message['content']}\n\n";
        }
        
        return $prompt;
    }
    
    public function reset(): void
    {
        $this->conversationHistory = [];
    }
}

// Использование
$bot = new ChatBot($openRouter);

echo $bot->chat("Привет! Как дела?") . "\n\n";
echo $bot->chat("Расскажи про PHP") . "\n\n";
echo $bot->chat("А что нового в PHP 8.1?") . "\n\n";
```

### Обработка изображений

```php
// Массовая обработка изображений
$images = [
    'photo1.jpg',
    'photo2.jpg',
    'photo3.jpg',
];

$results = [];

foreach ($images as $image) {
    try {
        $description = $openRouter->image2text(
            'openai/gpt-4-vision-preview',
            "https://example.com/images/{$image}",
            'Опиши это изображение подробно'
        );
        
        $results[$image] = $description;
        
    } catch (Exception $e) {
        echo "Ошибка обработки {$image}: {$e->getMessage()}\n";
    }
}

// Сохранить результаты
file_put_contents('descriptions.json', json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
```

### Генератор контента

```php
class ContentGenerator
{
    private OpenRouter $ai;
    
    public function __construct(OpenRouter $ai)
    {
        $this->ai = $ai;
    }
    
    public function generateArticle(string $topic, int $words = 500): array
    {
        // Генерация статьи
        $content = $this->ai->text2text(
            'anthropic/claude-3-opus',
            "Напиши статью на тему '{$topic}' примерно на {$words} слов",
            ['max_tokens' => $words * 2]
        );
        
        // Генерация изображения
        $imageUrl = $this->ai->text2image(
            'openai/dall-e-3',
            "Иллюстрация для статьи: {$topic}"
        );
        
        // Генерация аудио версии (первый абзац)
        $firstParagraph = explode("\n\n", $content)[0];
        $audio = $this->ai->text2audio(
            'openai/tts-1',
            $firstParagraph,
            'nova'
        );
        
        return [
            'title' => $topic,
            'content' => $content,
            'image_url' => $imageUrl,
            'audio_data' => $audio,
        ];
    }
}

// Использование
$generator = new ContentGenerator($openRouter);
$article = $generator->generateArticle('Будущее искусственного интеллекта');

file_put_contents('article.txt', $article['content']);
file_put_contents('article.mp3', $article['audio_data']);
```

### Анализ документов

```php
class DocumentAnalyzer
{
    private OpenRouter $ai;
    
    public function __construct(OpenRouter $ai)
    {
        $this->ai = $ai;
    }
    
    public function analyze(string $pdfPath): array
    {
        // Извлечь текст
        $text = $this->ai->pdf2text(
            'anthropic/claude-3-sonnet',
            $pdfPath
        );
        
        // Создать резюме
        $summary = $this->ai->text2text(
            'openai/gpt-4',
            "Создай краткое резюме этого текста:\n\n{$text}"
        );
        
        // Извлечь ключевые слова
        $keywords = $this->ai->text2text(
            'openai/gpt-3.5-turbo',
            "Извлеки 10 ключевых слов из текста (через запятую):\n\n{$text}"
        );
        
        return [
            'full_text' => $text,
            'summary' => $summary,
            'keywords' => array_map('trim', explode(',', $keywords)),
            'word_count' => str_word_count($text),
        ];
    }
}

// Использование
$analyzer = new DocumentAnalyzer($openRouter);
$result = $analyzer->analyze('https://example.com/document.pdf');

print_r($result);
```

## API Reference

### Конструктор

```php
public function __construct(array $config, ?Logger $logger = null)
```

**Параметры:**
- `$config` (array) - Конфигурация OpenRouter
- `$logger` (Logger|null) - Опциональный логгер

### text2text()

```php
public function text2text(string $model, string $prompt, array $options = []): string
```

Текстовая генерация.

**Опции:**
- `temperature` (float) - Температура генерации (0.0-2.0)
- `max_tokens` (int) - Максимум токенов
- `top_p` (float) - Top-p sampling
- `frequency_penalty` (float) - Штраф за частоту
- `presence_penalty` (float) - Штраф за присутствие

### text2image()

```php
public function text2image(string $model, string $prompt, array $options = []): string
```

Генерация изображения. Возвращает URL изображения.

**Опции:**
- `size` (string) - Размер изображения
- `quality` (string) - Качество (standard, hd)
- `style` (string) - Стиль (vivid, natural)

### image2text()

```php
public function image2text(string $model, string $imageUrl, string $question = 'Опиши это изображение', array $options = []): string
```

Распознавание изображения.

### audio2text()

```php
public function audio2text(string $model, string $audioSource, array $options = []): string
```

Транскрибация аудио.

**Опции:**
- `language` (string) - Код языка (ISO-639-1)
- `temperature` (float) - Температура
- `prompt` (string) - Подсказка для модели

### text2audio()

```php
public function text2audio(string $model, string $text, string $voice = 'alloy', array $options = []): string
```

Синтез речи. Возвращает бинарные данные MP3.

**Голоса:** alloy, echo, fable, onyx, nova, shimmer

### pdf2text()

```php
public function pdf2text(string $model, string $pdfSource, array $options = []): string
```

Извлечение текста из PDF.

### textStream()

```php
public function textStream(string $model, string $prompt, callable $callback, array $options = []): void
```

Потоковая генерация текста.

**Callback:** `function(string $chunk): void`

## Обработка ошибок

### Исключения

- `OpenRouterException` - Базовое исключение
- `OpenRouterValidationException` - Ошибка валидации параметров
- `OpenRouterApiException` - Ошибка API (код, сообщение)
- `OpenRouterNetworkException` - Сетевая ошибка

```php
use App\Component\Exception\OpenRouterApiException;
use App\Component\Exception\OpenRouterValidationException;

try {
    $response = $openRouter->text2text('openai/gpt-4', 'Привет!');
} catch (OpenRouterValidationException $e) {
    echo "Ошибка валидации: {$e->getMessage()}\n";
} catch (OpenRouterApiException $e) {
    echo "Ошибка API: {$e->getMessage()}\n";
    echo "Код: {$e->getCode()}\n";
} catch (OpenRouterException $e) {
    echo "Общая ошибка: {$e->getMessage()}\n";
}
```

## Лучшие практики

1. **Используйте подходящие модели** для каждой задачи:
   - GPT-3.5-turbo - быстрые, простые задачи
   - GPT-4 - сложные задачи, анализ
   - Claude - длинные тексты, анализ документов
   - Whisper - распознавание речи

2. **Настраивайте temperature**:
   - 0.0-0.3 - точные, детерминированные ответы
   - 0.7-0.9 - креативные ответы
   - 1.0-2.0 - очень креативные, случайные

3. **Используйте max_tokens** для контроля стоимости

4. **Кешируйте результаты** для одинаковых запросов

5. **Обрабатывайте ошибки** на всех уровнях

6. **Используйте streaming** для длинных ответов

7. **Мониторьте расходы** через OpenRouterMetrics

## Производительность

- Используйте GPT-3.5-turbo для быстрых ответов
- Включите retry для надежности
- Кешируйте частые запросы
- Используйте batch-обработку где возможно
- Оптимизируйте промпты для сокращения токенов

## Популярные модели

### Текстовые модели

- `openai/gpt-4` - Самая мощная модель OpenAI
- `openai/gpt-3.5-turbo` - Быстрая и доступная
- `anthropic/claude-3-opus` - Лучшая модель Claude
- `anthropic/claude-3-sonnet` - Баланс скорости и качества
- `google/gemini-pro` - Модель Google
- `meta-llama/llama-3-70b-instruct` - Open-source модель

### Модели изображений

- `openai/dall-e-3` - Генерация изображений OpenAI
- `stability-ai/stable-diffusion-xl` - Open-source генерация

### Модели распознавания

- `openai/gpt-4-vision-preview` - Анализ изображений
- `openai/whisper-1` - Распознавание речи

## См. также

- [OpenRouterMetrics документация](OPENROUTER_METRICS.md) - мониторинг использования и стоимости
- [Http документация](HTTP.md) - HTTP клиент
- [Logger документация](LOGGER.md) - логирование запросов

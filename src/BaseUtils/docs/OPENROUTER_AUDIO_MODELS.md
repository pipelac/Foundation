# OpenRouter - Поддерживаемые модели для распознавания речи (Audio to Text)

## Актуальные модели (2024)

OpenRouter API поддерживает следующие модели для распознавания речи через endpoint `/chat/completions` с multimodal content:

## OpenAI модели

### 1. openai/gpt-4o-audio-preview
- **Описание:** Высококачественная модель распознавания речи от OpenAI
- **Особенности:** Отличное качество транскрипции, поддержка множества языков, понимание контекста
- **Использование:** Профессиональная транскрипция, интервью, лекции, подкасты

```php
$transcript = $openRouter->audio2text(
    'openai/gpt-4o-audio-preview',
    'https://example.com/audio.mp3',
    [
        'language' => 'ru',
        'prompt' => 'Это интервью с экспертом по AI технологиям'
    ]
);
```

## Google Gemini модели

### 2. google/gemini-2.5-flash
- **Описание:** Быстрая и эффективная модель от Google
- **Особенности:** Оптимальный баланс скорости и качества
- **Использование:** Универсальная транскрипция, быстрая обработка

```php
$transcript = $openRouter->audio2text(
    'google/gemini-2.5-flash',
    'https://example.com/meeting.mp3'
);
```

### 3. google/gemini-2.5-flash-lite
- **Описание:** Облегченная версия для быстрой обработки
- **Особенности:** Максимальная скорость, оптимизированная стоимость
- **Использование:** Массовая обработка, простые задачи

```php
$transcript = $openRouter->audio2text(
    'google/gemini-2.5-flash-lite',
    'https://example.com/short-audio.mp3'
);
```

### 4. google/gemini-2.5-pro-preview
- **Описание:** Продвинутая модель с улучшенными возможностями
- **Особенности:** Высокое качество, лучшее понимание контекста
- **Использование:** Сложные задачи транскрипции, технический контент

```php
$transcript = $openRouter->audio2text(
    'google/gemini-2.5-pro-preview',
    'https://example.com/technical-lecture.mp3',
    [
        'prompt' => 'Техническая лекция о машинном обучении'
    ]
);
```

### 5. google/gemini-2.0-flash-001
- **Описание:** Оптимизированная версия 2.0
- **Особенности:** Стабильная производительность, проверенное качество
- **Использование:** Надежная транскрипция для production

```php
$transcript = $openRouter->audio2text(
    'google/gemini-2.0-flash-001',
    'https://example.com/podcast.mp3'
);
```

### 6. google/gemini-2.0-flash-lite-001
- **Описание:** Lite версия 2.0 для быстрой обработки
- **Особенности:** Быстрая обработка, низкая стоимость
- **Использование:** Быстрые задачи, массовая обработка

```php
$transcript = $openRouter->audio2text(
    'google/gemini-2.0-flash-lite-001',
    'https://example.com/voice-message.mp3'
);
```

### Дополнительные preview модели

- **google/gemini-2.5-flash-preview-09-2025** - Preview версия с новыми функциями
- **google/gemini-2.5-flash-lite-preview-09-2025** - Preview lite версия
- **google/gemini-2.5-flash-lite-preview-06-17** - Ранняя preview версия
- **google/gemini-2.5-pro-preview-05-06** - Ранняя pro preview версия

## Сравнение моделей

| Модель | Качество | Скорость | Стоимость | Лучше для |
|--------|----------|----------|-----------|-----------|
| `openai/gpt-4o-audio-preview` | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | $$$ | Профессиональная работа |
| `google/gemini-2.5-pro-preview` | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | $$$ | Сложные задачи |
| `google/gemini-2.5-flash` | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | $$ | Универсальные задачи |
| `google/gemini-2.5-flash-lite` | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | $ | Быстрая обработка |
| `google/gemini-2.0-flash-001` | ⭐⭐⭐⭐ | ⭐⭐⭐⭐ | $$ | Production use |
| `google/gemini-2.0-flash-lite-001` | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | $ | Массовая обработка |

## Параметры распознавания

Все модели поддерживают следующие параметры через `$options`:

### language (string)
Код языка для улучшения точности распознавания:
- `"ru"` - Русский
- `"en"` - Английский
- `"es"` - Испанский
- `"fr"` - Французский
- И другие ISO 639-1 коды

### prompt (string)
Текстовая подсказка для улучшения точности транскрипции:
- Контекст аудио (тема, тип контента)
- Специфичные термины или имена
- Ожидаемый стиль речи

## Примеры использования

### Профессиональная транскрипция интервью
```php
$transcript = $openRouter->audio2text(
    'openai/gpt-4o-audio-preview',
    'https://example.com/interview.mp3',
    [
        'language' => 'ru',
        'prompt' => 'Интервью с CEO технологического стартапа. ' .
                    'Обсуждаются AI технологии, машинное обучение и нейросети.'
    ]
);

echo "Транскрипция:\n{$transcript}\n";
```

### Быстрая транскрипция совещания
```php
$transcript = $openRouter->audio2text(
    'google/gemini-2.5-flash',
    'https://example.com/meeting.mp3',
    [
        'language' => 'ru',
        'prompt' => 'Рабочее совещание команды разработки'
    ]
);

// Сохранить результат
file_put_contents('meeting-transcript.txt', $transcript);
```

### Массовая обработка голосовых сообщений
```php
$audioFiles = [
    'message1.mp3',
    'message2.mp3',
    'message3.mp3',
];

foreach ($audioFiles as $file) {
    try {
        $transcript = $openRouter->audio2text(
            'google/gemini-2.5-flash-lite',
            "https://example.com/audio/{$file}",
            ['language' => 'ru']
        );
        
        echo "Файл {$file}:\n{$transcript}\n\n";
        
    } catch (Exception $e) {
        echo "Ошибка обработки {$file}: {$e->getMessage()}\n";
    }
}
```

### Транскрипция подкаста с высоким качеством
```php
$transcript = $openRouter->audio2text(
    'google/gemini-2.5-pro-preview',
    'https://example.com/podcast-episode.mp3',
    [
        'language' => 'en',
        'prompt' => 'Tech podcast discussing artificial intelligence, ' .
                    'machine learning frameworks, and software engineering practices'
    ]
);

// Разбить на параграфы
$paragraphs = explode("\n\n", $transcript);
foreach ($paragraphs as $i => $paragraph) {
    echo "Параграф " . ($i + 1) . ":\n{$paragraph}\n\n";
}
```

### Производственная транскрипция
```php
// Используем стабильную версию для production
$transcript = $openRouter->audio2text(
    'google/gemini-2.0-flash-001',
    'https://example.com/customer-call.mp3',
    [
        'language' => 'ru',
        'prompt' => 'Звонок клиента в службу поддержки'
    ]
);

// Сохранить с метаданными
$result = [
    'timestamp' => date('Y-m-d H:i:s'),
    'audio_url' => 'https://example.com/customer-call.mp3',
    'transcript' => $transcript,
    'model' => 'google/gemini-2.0-flash-001'
];

file_put_contents('transcript.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
```

## Лучшие практики

### 1. Выбор модели

**Для высокого качества:**
- `openai/gpt-4o-audio-preview` - лучшее качество от OpenAI
- `google/gemini-2.5-pro-preview` - продвинутые возможности Google

**Для баланса качества и скорости:**
- `google/gemini-2.5-flash` - оптимальный выбор
- `google/gemini-2.0-flash-001` - стабильная версия

**Для быстрой обработки:**
- `google/gemini-2.5-flash-lite` - максимальная скорость
- `google/gemini-2.0-flash-lite-001` - стабильная lite версия

### 2. Улучшение точности

**Всегда указывайте язык:**
```php
['language' => 'ru']  // Значительно улучшает точность
```

**Предоставляйте контекст через prompt:**
```php
[
    'language' => 'ru',
    'prompt' => 'Техническое интервью о Python, Django, FastAPI, Docker'
]
```

**Для специфичных терминов:**
```php
[
    'language' => 'ru',
    'prompt' => 'Медицинская консультация. Термины: МРТ, КТ, УЗИ, диагностика'
]
```

### 3. Оптимизация стоимости

**Для разработки и тестов:**
- Используйте lite модели
- Обрабатывайте только необходимые фрагменты

**Для production:**
- Кешируйте результаты транскрипции
- Используйте batch обработку
- Выбирайте подходящую модель для задачи

### 4. Обработка ошибок

```php
use App\Component\Exception\OpenRouterValidationException;
use App\Component\Exception\OpenRouterApiException;
use App\Component\Exception\OpenRouterException;

try {
    $transcript = $openRouter->audio2text(
        'openai/gpt-4o-audio-preview',
        $audioUrl,
        ['language' => 'ru']
    );
    
    if (empty($transcript)) {
        throw new Exception('Пустая транскрипция');
    }
    
    // Обработка результата
    
} catch (OpenRouterValidationException $e) {
    // Невалидные параметры
    error_log("Ошибка валидации: " . $e->getMessage());
} catch (OpenRouterApiException $e) {
    // Ошибка API
    error_log("Ошибка API: " . $e->getMessage());
    error_log("HTTP код: " . $e->getCode());
} catch (Exception $e) {
    // Другие ошибки
    error_log("Общая ошибка: " . $e->getMessage());
}
```

## Форматы аудио

OpenRouter поддерживает различные форматы аудио:
- MP3
- WAV
- M4A
- FLAC
- OGG
- WebM

## Ограничения

- Максимальная длина аудио зависит от модели
- URL должен быть публично доступен
- Качество транскрипции зависит от качества аудио

## Дополнительные ресурсы

- [OpenRouter Audio Documentation](https://openrouter.ai/docs/features/multimodal/audio)
- [OpenRouter API Reference](https://openrouter.ai/docs/api-reference)
- [Pricing Information](https://openrouter.ai/docs/pricing)

## Обновления

Эта документация отражает актуальное состояние API на 2024 год. Список поддерживаемых моделей может обновляться.

Всегда проверяйте актуальный список моделей на официальной странице OpenRouter:
https://openrouter.ai/docs/features/multimodal/audio

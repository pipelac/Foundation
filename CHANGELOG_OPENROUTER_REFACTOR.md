# Changelog - Рефакторинг OpenRouter (добавление multimodal функций)

## [Версия рефакторинга] - 2024

### Восстановлено и обновлено

После уточнения с официальной документацией OpenRouter восстановлены и правильно реализованы multimodal методы:

- **text2image()** - Генерация изображений через `/images/generations` (официальный OpenRouter endpoint)
- **pdf2text()** - Извлечение текста из PDF через `/chat/completions` с `image_url` типом
- **audio2text()** - Распознавание речи через `/chat/completions` с `audio_url` типом

### Реализация согласно документации OpenRouter

Все методы теперь корректно используют официальные OpenRouter API endpoints:

#### Text to Image
- Использует `/images/generations` endpoint
- Поддерживает модели:
  - `openai/gpt-5-image` - Высококачественная генерация
  - `openai/gpt-5-image-mini` - Быстрая генерация с оптимизированной стоимостью
  - `google/gemini-2.5-flash-image` - Генерация от Google
  - `google/gemini-2.5-flash-image-preview` - Preview версия
- Параметры: `size`, `quality`, `n`

#### PDF to Text
- Использует `/chat/completions` endpoint с multimodal content
- Тип контента: `image_url` (OpenRouter поддерживает PDF как изображения)
- Поддерживает модели с vision: `openai/gpt-4-vision-preview`, `anthropic/claude-3-opus`

#### Audio to Text
- Использует `/chat/completions` endpoint с multimodal content
- Тип контента: `audio_url`
- Поддерживает модели:
  - `openai/gpt-4o-audio-preview` - Высококачественное распознавание от OpenAI
  - `google/gemini-2.5-flash` - Быстрое распознавание от Google
  - `google/gemini-2.5-flash-lite` - Облегченная версия
  - `google/gemini-2.5-pro-preview` - Продвинутая модель Google
  - `google/gemini-2.0-flash-001` - Оптимизированная версия 2.0
  - `google/gemini-2.0-flash-lite-001` - Lite версия 2.0
  - И другие Gemini preview модели
- Параметры: `language`, `prompt`

### Сохранено

Класс содержит полный набор методов OpenRouter API:

- ✅ **text2text()** - Текстовая генерация через `/chat/completions`
- ✅ **text2image()** - Генерация изображений через `/images/generations`
- ✅ **image2text()** - Анализ изображений через `/chat/completions` с vision моделями
- ✅ **pdf2text()** - Извлечение текста из PDF через `/chat/completions` с multimodal
- ✅ **audio2text()** - Распознавание речи через `/chat/completions` с multimodal
- ✅ **textStream()** - Потоковая передача текста через `/chat/completions` с `stream=true`

### Обновлена документация

- `docs/OPENROUTER.md` - Добавлены примеры и описания всех методов
- `examples/README_OPENROUTER.md` - Обновлены примеры использования
- `README.md` - Обновлено описание компонента OpenRouter

### Технические характеристики

- ✅ Строгая типизация всех параметров и возвращаемых значений
- ✅ PHPDoc документация на русском языке для всех методов
- ✅ Обработка исключений на каждом уровне
- ✅ Соответствие официальной документации OpenRouter API
- ✅ Поддержка всех multimodal функций OpenRouter

### Ссылки на документацию OpenRouter

- [OpenRouter Quickstart](https://openrouter.ai/docs/quickstart)
- [Multimodal: Images](https://openrouter.ai/docs/features/multimodal/images)
- [Multimodal: Image Generation](https://openrouter.ai/docs/features/multimodal/image-generation)
- [Multimodal: PDFs](https://openrouter.ai/docs/features/multimodal/pdfs)
- [Multimodal: Audio](https://openrouter.ai/docs/features/multimodal/audio)

### Примеры использования

```php
use App\Component\OpenRouter;

$openRouter = new OpenRouter([
    'api_key' => 'sk-or-v1-...',
], $logger);

// Текстовая генерация
$text = $openRouter->text2text('openai/gpt-4', 'Привет!');

// Генерация изображений
$imageUrl = $openRouter->text2image('openai/gpt-5-image', 'Красивый закат');

// Анализ изображений
$description = $openRouter->image2text(
    'openai/gpt-4-vision-preview',
    'https://example.com/image.jpg',
    'Что на картинке?'
);

// Извлечение текста из PDF
$pdfText = $openRouter->pdf2text(
    'anthropic/claude-3-opus',
    'https://example.com/document.pdf'
);

// Распознавание речи
$transcript = $openRouter->audio2text(
    'openai/gpt-4o-audio-preview',
    'https://example.com/audio.mp3',
    ['language' => 'ru']
);

// Потоковая передача
$openRouter->textStream('openai/gpt-3.5-turbo', 'История', function($chunk) {
    echo $chunk;
});
```

### Улучшения

1. **Унифицированный API** - Все методы используют одинаковый стиль и обработку ошибок
2. **Гибкость** - Поддержка дополнительных параметров через `$options`
3. **Безопасность** - Валидация всех входных параметров
4. **Надежность** - Обработка всех типов исключений
5. **Документация** - Подробные PHPDoc комментарии на русском языке

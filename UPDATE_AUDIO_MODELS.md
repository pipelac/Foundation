# Обновление: Актуальные модели для распознавания речи (Audio to Text)

## Дата обновления
2024

## Суть изменений

Обновлена документация и примеры для отражения актуального списка поддерживаемых моделей распознавания речи в OpenRouter API.

## Актуальные модели audio2text

Согласно официальной документации OpenRouter, для распознавания речи поддерживаются следующие модели:

### OpenAI модели
1. **openai/gpt-4o-audio-preview** - Высококачественное распознавание речи

### Google Gemini модели
2. **google/gemini-2.5-flash** - Быстрое и эффективное распознавание
3. **google/gemini-2.5-flash-lite** - Облегченная версия для быстрой обработки
4. **google/gemini-2.5-pro-preview** - Продвинутая модель
5. **google/gemini-2.0-flash-001** - Стабильная версия 2.0
6. **google/gemini-2.0-flash-lite-001** - Lite версия 2.0

### Preview модели
7. **google/gemini-2.5-flash-preview-09-2025**
8. **google/gemini-2.5-flash-lite-preview-09-2025**
9. **google/gemini-2.5-flash-lite-preview-06-17**
10. **google/gemini-2.5-pro-preview-05-06**

## Изменения в коде

### src/OpenRouter.class.php
Обновлены примеры моделей в PHPDoc комментарии метода `audio2text()`:
```php
/**
 * @param string $model Модель распознавания речи (например, "openai/gpt-4o-audio-preview", "google/gemini-2.5-flash")
 */
```

### README.md
Обновлен пример использования:
```php
// Было:
$transcript = $openRouter->audio2text('openai/whisper-1', 'https://example.com/audio.mp3');

// Стало:
$transcript = $openRouter->audio2text('openai/gpt-4o-audio-preview', 'https://example.com/audio.mp3');
```

### docs/OPENROUTER.md
- Добавлен список поддерживаемых моделей в начале секции Audio to Text
- Обновлены все примеры кода для использования актуальных моделей
- Добавлены примеры с разными моделями (OpenAI и Google)
- Обновлена секция "Модели для аудио"
- Добавлена ссылка на новую документацию `OPENROUTER_AUDIO_MODELS.md`

### Новые файлы

#### docs/OPENROUTER_AUDIO_MODELS.md
Создана подробная документация о моделях распознавания речи, включающая:
- Описание каждой модели
- Сравнительная таблица характеристик
- Примеры использования для разных сценариев
- Параметры распознавания (language, prompt)
- Лучшие практики
- Обработка ошибок
- Поддерживаемые форматы аудио

### Обновленные файлы

1. **CHANGELOG_OPENROUTER_REFACTOR.md**
   - Обновлен список поддерживаемых моделей
   - Исправлены примеры кода

2. **OPENROUTER_MULTIMODAL_SUMMARY.md**
   - Обновлен список моделей в разделе audio2text
   - Добавлены примеры для разных моделей

## Почему это важно

1. **Актуальность** - Документация теперь соответствует текущему состоянию OpenRouter API
2. **Корректность** - Удалены ссылки на несуществующую модель (whisper-1)
3. **Выбор** - Пользователи могут выбрать оптимальную модель для своих задач
4. **Практичность** - Предоставлены рабочие примеры для разных сценариев

## Что удалено

Упоминания следующей модели, которая НЕ поддерживается OpenRouter для audio2text:
- ❌ `openai/whisper-1`

*Примечание: Whisper может работать через прямое обращение к OpenAI API, но не через OpenRouter.*

## Что добавлено

Документация для актуальных моделей:
- ✅ `openai/gpt-4o-audio-preview`
- ✅ `google/gemini-2.5-flash`
- ✅ `google/gemini-2.5-flash-lite`
- ✅ `google/gemini-2.5-pro-preview`
- ✅ `google/gemini-2.0-flash-001`
- ✅ `google/gemini-2.0-flash-lite-001`
- ✅ И другие Gemini preview модели

## Рекомендации для пользователей

### Для существующего кода
Если вы использовали старую модель в своем коде, обновите её:

```php
// Старый код (не работает):
$transcript = $openRouter->audio2text('openai/whisper-1', $audioUrl);

// Новый код (работает):
$transcript = $openRouter->audio2text('openai/gpt-4o-audio-preview', $audioUrl);
```

### Выбор модели

**Для высокого качества:**
```php
$transcript = $openRouter->audio2text(
    'openai/gpt-4o-audio-preview',
    $audioUrl,
    ['language' => 'ru', 'prompt' => 'Интервью с экспертом']
);
```

**Для баланса качества и скорости:**
```php
$transcript = $openRouter->audio2text(
    'google/gemini-2.5-flash',
    $audioUrl,
    ['language' => 'ru']
);
```

**Для быстрой массовой обработки:**
```php
$transcript = $openRouter->audio2text(
    'google/gemini-2.5-flash-lite',
    $audioUrl
);
```

**Для production (стабильная версия):**
```php
$transcript = $openRouter->audio2text(
    'google/gemini-2.0-flash-001',
    $audioUrl,
    ['language' => 'ru']
);
```

## Сравнение моделей

| Применение | Рекомендуемая модель | Причина |
|------------|---------------------|---------|
| Профессиональная транскрипция | `openai/gpt-4o-audio-preview` | Лучшее качество |
| Универсальные задачи | `google/gemini-2.5-flash` | Баланс качества и скорости |
| Быстрая обработка | `google/gemini-2.5-flash-lite` | Максимальная скорость |
| Production окружение | `google/gemini-2.0-flash-001` | Стабильность |
| Массовая обработка | `google/gemini-2.0-flash-lite-001` | Низкая стоимость |

## Параметры для улучшения точности

### Всегда указывайте язык
```php
['language' => 'ru']  // Значительно улучшает точность
```

### Предоставляйте контекст
```php
[
    'language' => 'ru',
    'prompt' => 'Техническое интервью о Python, Django, FastAPI'
]
```

## Поддерживаемые форматы аудио

- MP3
- WAV
- M4A
- FLAC
- OGG
- WebM

## Ссылки

- [OpenRouter Audio Documentation](https://openrouter.ai/docs/features/multimodal/audio)
- [Локальная документация моделей](docs/OPENROUTER_AUDIO_MODELS.md)
- [Основная документация OpenRouter](docs/OPENROUTER.md)

## Проверка изменений

Все файлы были обновлены для обеспечения консистентности:
- ✅ Исходный код (src/OpenRouter.class.php)
- ✅ Основной README.md
- ✅ Документация (docs/OPENROUTER.md)
- ✅ Changelog (CHANGELOG_OPENROUTER_REFACTOR.md)
- ✅ Summary (OPENROUTER_MULTIMODAL_SUMMARY.md)
- ✅ Новая документация по моделям (docs/OPENROUTER_AUDIO_MODELS.md)

## Примеры использования

### Транскрипция интервью
```php
$transcript = $openRouter->audio2text(
    'openai/gpt-4o-audio-preview',
    'https://example.com/interview.mp3',
    [
        'language' => 'ru',
        'prompt' => 'Интервью с CEO о AI технологиях и машинном обучении'
    ]
);
```

### Быстрая обработка совещания
```php
$transcript = $openRouter->audio2text(
    'google/gemini-2.5-flash',
    'https://example.com/meeting.mp3',
    ['language' => 'ru', 'prompt' => 'Рабочее совещание']
);
```

### Массовая обработка голосовых сообщений
```php
foreach ($audioFiles as $file) {
    $transcript = $openRouter->audio2text(
        'google/gemini-2.5-flash-lite',
        $file,
        ['language' => 'ru']
    );
}
```

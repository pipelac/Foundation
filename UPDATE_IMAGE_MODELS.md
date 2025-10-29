# Обновление: Актуальные модели для генерации изображений

## Дата обновления
2024

## Суть изменений

Обновлена документация и примеры для отражения актуального списка поддерживаемых моделей генерации изображений в OpenRouter API.

## Актуальные модели text2image

Согласно официальной документации OpenRouter, для генерации изображений поддерживаются следующие модели:

### OpenAI модели
1. **openai/gpt-5-image** - Полнофункциональная модель высокого качества
2. **openai/gpt-5-image-mini** - Оптимизированная версия для быстрой генерации

### Google Gemini модели
3. **google/gemini-2.5-flash-image** - Стабильная версия от Google
4. **google/gemini-2.5-flash-image-preview** - Preview версия с новыми функциями

## Изменения в коде

### src/OpenRouter.class.php
Обновлены примеры моделей в PHPDoc комментарии метода `text2image()`:
```php
/**
 * @param string $model Модель генерации изображений (например, "openai/gpt-5-image", "google/gemini-2.5-flash-image")
 */
```

### README.md
Обновлен пример использования:
```php
// Было:
$imageUrl = $openRouter->text2image('openai/dall-e-3', 'Красивый закат над океаном');

// Стало:
$imageUrl = $openRouter->text2image('openai/gpt-5-image', 'Красивый закат над океаном');
```

### docs/OPENROUTER.md
- Добавлен список поддерживаемых моделей в начале секции Text to Image
- Обновлены все примеры кода для использования актуальных моделей
- Добавлен пример с `openai/gpt-5-image-mini`
- Обновлена секция "Популярные модели"
- Добавлена ссылка на новую документацию `OPENROUTER_IMAGE_MODELS.md`

### Новые файлы

#### docs/OPENROUTER_IMAGE_MODELS.md
Создана подробная документация о моделях генерации изображений, включающая:
- Описание каждой модели
- Сравнительная таблица характеристик
- Примеры использования
- Параметры генерации (size, quality, n)
- Лучшие практики
- Обработка ошибок

### Обновленные файлы

1. **CHANGELOG_OPENROUTER_REFACTOR.md**
   - Обновлен список поддерживаемых моделей
   - Исправлены примеры кода

2. **OPENROUTER_MULTIMODAL_SUMMARY.md**
   - Обновлен список моделей в разделе text2image
   - Добавлены примеры для всех моделей

## Почему это важно

1. **Актуальность** - Документация теперь соответствует текущему состоянию OpenRouter API
2. **Корректность** - Удалены ссылки на несуществующие модели (dall-e-3, stable-diffusion-xl)
3. **Полнота** - Добавлены все поддерживаемые модели с описаниями
4. **Практичность** - Пользователи получают рабочие примеры

## Что удалено

Упоминания следующих моделей, которые НЕ поддерживаются OpenRouter для text2image:
- ❌ `openai/dall-e-3`
- ❌ `stability-ai/stable-diffusion-xl`

*Примечание: Эти модели могут работать через прямое обращение к OpenAI API, но не через OpenRouter.*

## Что добавлено

Документация для актуальных моделей:
- ✅ `openai/gpt-5-image`
- ✅ `openai/gpt-5-image-mini`
- ✅ `google/gemini-2.5-flash-image`
- ✅ `google/gemini-2.5-flash-image-preview`

## Рекомендации для пользователей

### Для существующего кода
Если вы использовали старые модели в своем коде, обновите их:

```php
// Старый код (не работает):
$imageUrl = $openRouter->text2image('openai/dall-e-3', $prompt);

// Новый код (работает):
$imageUrl = $openRouter->text2image('openai/gpt-5-image', $prompt);
```

### Выбор модели

**Для профессиональной работы:**
```php
$imageUrl = $openRouter->text2image('openai/gpt-5-image', $prompt, ['quality' => 'hd']);
```

**Для быстрых задач:**
```php
$imageUrl = $openRouter->text2image('openai/gpt-5-image-mini', $prompt);
```

**Для экспериментов с Google:**
```php
$imageUrl = $openRouter->text2image('google/gemini-2.5-flash-image', $prompt);
```

## Ссылки

- [OpenRouter Image Generation Documentation](https://openrouter.ai/docs/features/multimodal/image-generation)
- [Локальная документация моделей](docs/OPENROUTER_IMAGE_MODELS.md)
- [Основная документация OpenRouter](docs/OPENROUTER.md)

## Проверка изменений

Все файлы были обновлены для обеспечения консистентности:
- ✅ Исходный код (src/OpenRouter.class.php)
- ✅ Основной README.md
- ✅ Документация (docs/OPENROUTER.md)
- ✅ Changelog (CHANGELOG_OPENROUTER_REFACTOR.md)
- ✅ Summary (OPENROUTER_MULTIMODAL_SUMMARY.md)
- ✅ Примеры (examples/README_OPENROUTER.md)
- ✅ Новая документация по моделям (docs/OPENROUTER_IMAGE_MODELS.md)

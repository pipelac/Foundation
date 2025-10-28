# Примеры использования OpenRouter API

Этот каталог содержит примеры работы с классом OpenRouter для интеграции с AI моделями через OpenRouter API.

## Файлы с примерами

### openrouter_example.php

Полный набор примеров использования всех методов класса OpenRouter:

1. **text2text** - Текстовая генерация с AI моделями
2. **text2image** - Генерация изображений по текстовому описанию
3. **image2text** - Распознавание и описание изображений
4. **audio2text** - Распознавание речи из аудиофайлов
5. **text2audio** - Синтез речи из текста (TTS)
6. **pdf2text** - Извлечение текста из PDF документов
7. **textStream** - Потоковая передача текста

**Запуск:**
```bash
export OPENROUTER_API_KEY="your-api-key-here"
php examples/openrouter_example.php
```

### openrouter_audio_example.php

Упрощенные примеры специально для работы с аудио (audio2text и text2audio):

1. Базовый синтез речи
2. Синтез с параметрами (скорость, формат)
3. Сравнение различных голосов
4. Распознавание речи из файла
5. Работа с URL аудиофайлов
6. Обработка ошибок

**Запуск:**
```bash
export OPENROUTER_API_KEY="your-api-key-here"
php examples/openrouter_audio_example.php
```

## Требования

- PHP 8.1 или выше
- Composer зависимости установлены
- API ключ OpenRouter

## Получение API ключа

1. Зарегистрируйтесь на [OpenRouter](https://openrouter.ai)
2. Перейдите в раздел API Keys
3. Создайте новый ключ
4. Экспортируйте его в переменную окружения:
   ```bash
   export OPENROUTER_API_KEY="sk-or-v1-..."
   ```

## Структура примеров

Каждый пример следует единому паттерну:

```php
try {
    // Инициализация
    $openRouter = new OpenRouter([
        'api_key' => getenv('OPENROUTER_API_KEY'),
        'app_name' => 'MyApp',
    ], $logger);
    
    // Вызов метода
    $result = $openRouter->someMethod(...);
    
    // Обработка результата
    echo "✓ Успешно!\n";
    
} catch (OpenRouterException $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}
```

## Особенности примеров text2audio

### Доступные голоса

- `alloy` - Универсальный нейтральный
- `echo` - С характерным звучанием  
- `fable` - Выразительный и эмоциональный
- `onyx` - Глубокий и насыщенный
- `nova` - Яркий и энергичный
- `shimmer` - Мягкий и приятный

### Параметры

```php
$audioData = $openRouter->text2audio(
    'openai/tts-1',        // Модель
    'Текст для синтеза',    // Текст
    'nova',                 // Голос
    [
        'speed' => 1.25,              // Скорость (0.25 - 4.0)
        'response_format' => 'mp3',   // Формат (mp3, opus, aac, flac)
    ]
);

// Сохранение результата
file_put_contents('output.mp3', $audioData);
```

## Генерируемые файлы

Примеры создают временные файлы в директории `/temp/`:

```
temp/
├── example1_basic.mp3
├── example2_parameters.mp3
├── example3_voice_*.mp3
├── example4_test_audio.mp3
├── voice_*.mp3
├── speed_*.mp3
└── format_test.*
```

**Примечание:** Директория `/temp/` включена в `.gitignore` и не попадает в репозиторий.

## Логирование

Все примеры записывают логи в директорию `/logs/`:

- `logs/openrouter.log` - общие операции
- `logs/openrouter_audio.log` - операции с аудио

## Обработка ошибок

Примеры демонстрируют правильную обработку всех типов исключений:

```php
use App\Component\Exception\OpenRouterException;
use App\Component\Exception\OpenRouterApiException;
use App\Component\Exception\OpenRouterValidationException;
use App\Component\Exception\OpenRouterNetworkException;

try {
    $result = $openRouter->text2audio(...);
} catch (OpenRouterValidationException $e) {
    // Ошибки валидации параметров
} catch (OpenRouterApiException $e) {
    // Ошибки API (код доступен через $e->getStatusCode())
} catch (OpenRouterNetworkException $e) {
    // Сетевые ошибки
} catch (OpenRouterException $e) {
    // Общие ошибки
}
```

## Расширенные примеры

### Полный цикл: text2audio → audio2text

```php
// 1. Генерация аудио
$originalText = 'Тестовая фраза';
$audioData = $openRouter->text2audio(
    'openai/tts-1',
    $originalText,
    'nova'
);
file_put_contents('test.mp3', $audioData);

// 2. Распознавание
$transcription = $openRouter->audio2text(
    'openai/whisper-1',
    'test.mp3',
    ['language' => 'ru']
);

echo "Оригинал: $originalText\n";
echo "Распознано: $transcription\n";
```

### Генерация в разных форматах

```php
$formats = ['mp3', 'opus', 'aac', 'flac'];

foreach ($formats as $format) {
    $audioData = $openRouter->text2audio(
        'openai/tts-1',
        'Тест формата ' . $format,
        'alloy',
        ['response_format' => $format]
    );
    
    file_put_contents("output.$format", $audioData);
}
```

## Полезные ссылки

- [Документация OpenRouter Audio](../docs/OPENROUTER_AUDIO.md)
- [Changelog](../OPENROUTER_AUDIO_CHANGELOG.md)
- [Основной README проекта](../README.md)
- [OpenRouter API Docs](https://openrouter.ai/docs)

## Советы

1. **Используйте переменные окружения** для API ключей
2. **Кэшируйте результаты** для экономии средств и времени
3. **Логируйте все операции** для отладки
4. **Обрабатывайте все исключения** для надежности
5. **Тестируйте с разными голосами** для выбора оптимального
6. **Начинайте с формата mp3** - он универсальный и компактный

## Тестирование без API ключа

Большинство примеров требуют реальный API ключ. Для проверки синтаксиса без выполнения запросов:

```bash
php -l examples/openrouter_example.php
php -l examples/openrouter_audio_example.php
```

## Производительность

**Примерное время выполнения:**
- text2text: 1-5 секунд
- text2audio: 1-3 секунды (зависит от длины текста)
- audio2text: 2-10 секунд (зависит от длины аудио)
- text2image: 10-30 секунд

**Размеры файлов:**
- MP3 (128kbps): ~1 КБ/сек
- OPUS (64kbps): ~0.5 КБ/сек
- FLAC (lossless): ~5-8 КБ/сек

## Поддержка

Если вы столкнулись с проблемами:

1. Проверьте наличие API ключа
2. Убедитесь в корректности параметров
3. Посмотрите логи в `/logs/`
4. Изучите полную документацию в `/docs/`
5. Проверьте баланс на OpenRouter

---

**Последнее обновление:** 2024
**Версия PHP:** 8.1+

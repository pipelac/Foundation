# OpenRouter Audio API - Методы audio2text и text2audio

Подробная документация по работе с аудио через OpenRouter API.

## Содержание

1. [Обзор](#обзор)
2. [Метод text2audio](#метод-text2audio)
3. [Метод audio2text](#метод-audio2text)
4. [Примеры использования](#примеры-использования)
5. [Обработка ошибок](#обработка-ошибок)
6. [Лучшие практики](#лучшие-практики)

---

## Обзор

Класс `OpenRouter` предоставляет два метода для работы с аудио:

- **text2audio** - преобразование текста в речь (Text-to-Speech, TTS)
- **audio2text** - распознавание речи из аудио (Speech-to-Text, STT)

Оба метода следуют единому стилю кода с строгой типизацией, полной обработкой исключений и подробной документацией на русском языке.

---

## Метод text2audio

### Описание

Преобразует текстовую строку в речь, возвращая бинарное содержимое аудиофайла.

### Сигнатура

```php
public function text2audio(
    string $model, 
    string $text, 
    string $voice, 
    array $options = []
): string
```

### Параметры

| Параметр | Тип | Описание | Обязательный |
|----------|-----|----------|--------------|
| `$model` | string | Модель синтеза речи (например, "openai/tts-1") | Да |
| `$text` | string | Текст для преобразования в речь | Да |
| `$voice` | string | Голос для синтеза | Да |
| `$options` | array | Дополнительные параметры | Нет |

#### Доступные модели

- `openai/tts-1` - Стандартная модель (быстрее, дешевле)
- `openai/tts-1-hd` - HD модель (выше качество)

#### Доступные голоса

- `alloy` - Универсальный нейтральный голос
- `echo` - Голос с характерным звучанием
- `fable` - Выразительный и эмоциональный голос
- `onyx` - Глубокий и насыщенный голос
- `nova` - Яркий и энергичный голос
- `shimmer` - Мягкий и приятный голос

#### Опции

| Ключ | Тип | Описание | Диапазон/Значения |
|------|-----|----------|-------------------|
| `speed` | float | Скорость речи | 0.25 - 4.0 (по умолчанию 1.0) |
| `response_format` | string | Формат аудио | mp3, opus, aac, flac (по умолчанию mp3) |

### Возвращаемое значение

Бинарная строка с содержимым аудиофайла.

### Исключения

- `OpenRouterValidationException` - Если параметры невалидны
- `OpenRouterApiException` - Если API вернул ошибку
- `OpenRouterException` - Если не удалось получить аудиоданные

### Примеры

#### Базовое использование

```php
$openRouter = new OpenRouter([
    'api_key' => 'your-api-key',
    'app_name' => 'MyApp',
]);

$audioData = $openRouter->text2audio(
    'openai/tts-1',
    'Привет! Это пример синтеза речи.',
    'alloy'
);

file_put_contents('output.mp3', $audioData);
```

#### С параметрами

```php
$audioData = $openRouter->text2audio(
    'openai/tts-1-hd',
    'Текст с повышенным качеством и скоростью.',
    'nova',
    [
        'speed' => 1.25,
        'response_format' => 'opus',
    ]
);

file_put_contents('output.opus', $audioData);
```

#### Различные голоса

```php
$voices = ['alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer'];
$text = 'Демонстрация голоса';

foreach ($voices as $voice) {
    $audioData = $openRouter->text2audio(
        'openai/tts-1',
        $text,
        $voice
    );
    
    file_put_contents("voice_{$voice}.mp3", $audioData);
}
```

---

## Метод audio2text

### Описание

Распознает речь из аудиофайла и возвращает текстовую транскрипцию.

### Сигнатура

```php
public function audio2text(
    string $model, 
    string $audioSource, 
    array $options = []
): string
```

### Параметры

| Параметр | Тип | Описание | Обязательный |
|----------|-----|----------|--------------|
| `$model` | string | Модель распознавания речи | Да |
| `$audioSource` | string | URL аудиофайла или путь к локальному файлу | Да |
| `$options` | array | Дополнительные параметры | Нет |

#### Доступные модели

- `openai/whisper-1` - Модель Whisper для распознавания речи

#### Опции

| Ключ | Тип | Описание |
|------|-----|----------|
| `language` | string | Код языка (ISO-639-1), например: "ru", "en" |
| `temperature` | float | Температура генерации (0.0 - 1.0) |
| `prompt` | string | Подсказка для модели (для улучшения качества) |

### Возвращаемое значение

Строка с распознанным текстом.

### Исключения

- `OpenRouterValidationException` - Если параметры невалидны
- `OpenRouterApiException` - Если API вернул ошибку
- `OpenRouterNetworkException` - Если файл не удалось загрузить
- `OpenRouterException` - Если модель не вернула транскрипцию
- `JsonException` - Если не удалось декодировать ответ API

### Примеры

#### Локальный файл

```php
$openRouter = new OpenRouter([
    'api_key' => 'your-api-key',
    'app_name' => 'MyApp',
]);

$transcription = $openRouter->audio2text(
    'openai/whisper-1',
    '/path/to/audio.mp3'
);

echo "Транскрипция: $transcription\n";
```

#### URL аудиофайла

```php
$transcription = $openRouter->audio2text(
    'openai/whisper-1',
    'https://example.com/audio.mp3',
    ['language' => 'ru']
);

echo "Транскрипция: $transcription\n";
```

#### С параметрами

```php
$transcription = $openRouter->audio2text(
    'openai/whisper-1',
    '/path/to/audio.mp3',
    [
        'language' => 'ru',
        'temperature' => 0.0,
        'prompt' => 'Транскрипция технического доклада',
    ]
);
```

---

## Примеры использования

### Полный цикл: text2audio -> audio2text

```php
use App\Component\OpenRouter;
use App\Component\Exception\OpenRouterException;

try {
    $openRouter = new OpenRouter([
        'api_key' => getenv('OPENROUTER_API_KEY'),
        'app_name' => 'AudioDemo',
    ]);
    
    // Шаг 1: Генерация аудио
    $originalText = 'Это тестовая фраза для демонстрации.';
    
    $audioData = $openRouter->text2audio(
        'openai/tts-1',
        $originalText,
        'nova'
    );
    
    $tempFile = '/tmp/test_audio.mp3';
    file_put_contents($tempFile, $audioData);
    
    echo "Аудио создано: $tempFile\n";
    
    // Шаг 2: Распознавание аудио
    $transcription = $openRouter->audio2text(
        'openai/whisper-1',
        $tempFile,
        ['language' => 'ru']
    );
    
    echo "Исходный текст: $originalText\n";
    echo "Распознанный текст: $transcription\n";
    
} catch (OpenRouterException $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}
```

### Генерация аудио в разных форматах

```php
$formats = ['mp3', 'opus', 'aac', 'flac'];
$text = 'Тестовое аудио в различных форматах';

foreach ($formats as $format) {
    try {
        $audioData = $openRouter->text2audio(
            'openai/tts-1',
            $text,
            'alloy',
            ['response_format' => $format]
        );
        
        file_put_contents("output.{$format}", $audioData);
        echo "✓ Создан: output.{$format}\n";
        
    } catch (OpenRouterException $e) {
        echo "✗ Ошибка для $format: " . $e->getMessage() . "\n";
    }
}
```

### Обработка больших текстов

```php
function textToAudioLarge(OpenRouter $openRouter, string $text, string $voice): array
{
    // Разбиваем текст на части (макс. ~4000 символов для оптимальной работы)
    $maxLength = 4000;
    $parts = str_split($text, $maxLength);
    $audioFiles = [];
    
    foreach ($parts as $index => $part) {
        try {
            $audioData = $openRouter->text2audio(
                'openai/tts-1',
                $part,
                $voice
            );
            
            $filename = "part_{$index}.mp3";
            file_put_contents($filename, $audioData);
            $audioFiles[] = $filename;
            
        } catch (OpenRouterException $e) {
            echo "Ошибка в части $index: " . $e->getMessage() . "\n";
        }
    }
    
    return $audioFiles;
}

// Использование
$largeText = file_get_contents('large_document.txt');
$audioFiles = textToAudioLarge($openRouter, $largeText, 'nova');
```

---

## Обработка ошибок

### Типы исключений

```php
use App\Component\Exception\OpenRouterException;
use App\Component\Exception\OpenRouterApiException;
use App\Component\Exception\OpenRouterValidationException;
use App\Component\Exception\OpenRouterNetworkException;

try {
    $audioData = $openRouter->text2audio(
        'openai/tts-1',
        'Тестовый текст',
        'alloy'
    );
    
} catch (OpenRouterValidationException $e) {
    // Ошибки валидации параметров
    echo "Неверные параметры: " . $e->getMessage() . "\n";
    
} catch (OpenRouterApiException $e) {
    // Ошибки API (неверный ключ, превышен лимит, и т.д.)
    echo "Ошибка API (код " . $e->getStatusCode() . "): ";
    echo $e->getMessage() . "\n";
    
} catch (OpenRouterNetworkException $e) {
    // Сетевые ошибки (не удалось загрузить файл)
    echo "Сетевая ошибка: " . $e->getMessage() . "\n";
    
} catch (OpenRouterException $e) {
    // Общие ошибки
    echo "Ошибка: " . $e->getMessage() . "\n";
}
```

### Graceful degradation

```php
function generateAudioSafe(
    OpenRouter $openRouter, 
    string $text, 
    string $voice = 'alloy'
): ?string {
    try {
        return $openRouter->text2audio('openai/tts-1', $text, $voice);
    } catch (OpenRouterApiException $e) {
        // Логируем ошибку API
        error_log("API Error: " . $e->getMessage());
        return null;
    } catch (OpenRouterException $e) {
        // Логируем общую ошибку
        error_log("OpenRouter Error: " . $e->getMessage());
        return null;
    }
}

// Использование с fallback
$audioData = generateAudioSafe($openRouter, $text);

if ($audioData !== null) {
    file_put_contents('output.mp3', $audioData);
} else {
    echo "Не удалось сгенерировать аудио, используем текстовый режим\n";
}
```

---

## Лучшие практики

### 1. Валидация текста перед синтезом

```php
function validateTextForTTS(string $text): void
{
    if (strlen($text) === 0) {
        throw new \InvalidArgumentException('Текст не может быть пустым');
    }
    
    if (strlen($text) > 4096) {
        throw new \InvalidArgumentException('Текст слишком длинный (макс. 4096 символов)');
    }
}

try {
    validateTextForTTS($text);
    $audioData = $openRouter->text2audio('openai/tts-1', $text, 'alloy');
} catch (\InvalidArgumentException $e) {
    echo "Ошибка валидации: " . $e->getMessage() . "\n";
}
```

### 2. Кэширование аудио

```php
use App\Component\FileCache;

$cache = new FileCache(['directory' => '/tmp/audio_cache']);

function getCachedAudio(
    OpenRouter $openRouter, 
    FileCache $cache,
    string $text, 
    string $voice
): string {
    $cacheKey = 'audio_' . md5($text . $voice);
    
    $cached = $cache->get($cacheKey);
    if ($cached !== null) {
        return $cached;
    }
    
    $audioData = $openRouter->text2audio('openai/tts-1', $text, $voice);
    $cache->set($cacheKey, $audioData, 86400); // 24 часа
    
    return $audioData;
}
```

### 3. Использование логирования

```php
use App\Component\Logger;

$logger = new Logger(['directory' => '/var/log/app']);

$openRouter = new OpenRouter([
    'api_key' => getenv('OPENROUTER_API_KEY'),
], $logger);

// Все ошибки автоматически логируются
try {
    $audioData = $openRouter->text2audio('openai/tts-1', $text, 'nova');
} catch (OpenRouterException $e) {
    // Ошибка уже залогирована классом OpenRouter
    // Можно добавить дополнительный контекст
    $logger->error('Failed to generate audio', [
        'text_length' => strlen($text),
        'voice' => 'nova',
    ]);
}
```

### 4. Поддержка различных форматов аудио

```php
function saveAudioWithFormat(
    string $audioData, 
    string $format, 
    string $baseName = 'output'
): string {
    $extension = match($format) {
        'mp3' => '.mp3',
        'opus' => '.opus',
        'aac' => '.aac',
        'flac' => '.flac',
        default => '.mp3',
    };
    
    $filename = $baseName . $extension;
    file_put_contents($filename, $audioData);
    
    return $filename;
}

// Использование
$audioData = $openRouter->text2audio(
    'openai/tts-1',
    $text,
    'alloy',
    ['response_format' => 'opus']
);

$savedFile = saveAudioWithFormat($audioData, 'opus', 'my_audio');
```

### 5. Retry логика для надежности

```php
function text2audioWithRetry(
    OpenRouter $openRouter,
    string $text,
    string $voice,
    int $maxRetries = 3
): string {
    $attempt = 0;
    
    while ($attempt < $maxRetries) {
        try {
            return $openRouter->text2audio('openai/tts-1', $text, $voice);
        } catch (OpenRouterNetworkException $e) {
            $attempt++;
            if ($attempt >= $maxRetries) {
                throw $e;
            }
            sleep(pow(2, $attempt)); // Exponential backoff
        }
    }
    
    throw new \RuntimeException('Failed after all retries');
}
```

---

## Дополнительные ресурсы

- [Основная документация OpenRouter](../README.md)
- [Примеры использования](../examples/openrouter_example.php)
- [Упрощенные примеры аудио](../examples/openrouter_audio_example.php)
- [OpenRouter API Documentation](https://openrouter.ai/docs)

---

**Последнее обновление:** 2024
**Версия PHP:** 8.1+

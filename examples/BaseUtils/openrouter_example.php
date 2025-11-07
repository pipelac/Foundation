<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../autoload.php';

use App\Component\OpenRouter;
use App\Component\Logger;
use App\Component\Exception\OpenRouterException;
use App\Component\Exception\OpenRouterApiException;
use App\Component\Exception\OpenRouterValidationException;
use App\Component\Exception\OpenRouterNetworkException;

/**
 * Примеры использования класса OpenRouter
 * Демонстрация всех возможностей работы с OpenRouter API
 */

echo "=== Примеры использования класса OpenRouter ===\n\n";

// Инициализация логгера
$logger = new Logger([
    'directory' => __DIR__ . '/../logs',
    'file_name' => 'openrouter.log',
    'max_files' => 5,
    'max_file_size' => 10, // МБ
]);

// Конфигурация OpenRouter (замените на свой API ключ)
$config = [
    'api_key' => getenv('OPENROUTER_API_KEY') ?: 'your-api-key-here',
    'app_name' => 'OpenRouter-Example-App',
    'timeout' => 60,
    'retries' => 3,
];

try {
    $openRouter = new OpenRouter($config, $logger);
    echo "✓ OpenRouter клиент создан успешно\n\n";
} catch (OpenRouterValidationException $e) {
    echo "❌ Ошибка валидации конфигурации: " . $e->getMessage() . "\n";
    echo "Подсказка: Установите переменную окружения OPENROUTER_API_KEY или замените 'your-api-key-here' на реальный ключ.\n";
    exit(1);
}

// Пример 1: Текстовая генерация (text2text)
echo "1. Текстовая генерация (text2text):\n";
echo "Отправка запроса к модели для генерации текста...\n";
try {
    $response = $openRouter->text2text(
        'openai/gpt-3.5-turbo',
        'Напиши короткое стихотворение о программировании на PHP',
        [
            'temperature' => 0.7,
            'max_tokens' => 200,
        ]
    );
    echo "Ответ модели:\n";
    echo $response . "\n";
    echo "✓ text2text выполнен успешно\n\n";
} catch (OpenRouterApiException $e) {
    echo "❌ Ошибка API (код " . $e->getStatusCode() . "): " . $e->getMessage() . "\n\n";
} catch (OpenRouterException $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n\n";
}

// Пример 2: Генерация изображений (text2image)
echo "2. Генерация изображений (text2image):\n";
echo "Генерация изображения по текстовому описанию...\n";
try {
    $imageUrl = $openRouter->text2image(
        'openai/dall-e-3',
        'A serene landscape with mountains, a lake, and pine trees at sunset',
        [
            'size' => '1024x1024',
            'quality' => 'standard',
        ]
    );
    echo "URL сгенерированного изображения:\n";
    echo $imageUrl . "\n";
    echo "✓ text2image выполнен успешно\n\n";
} catch (OpenRouterApiException $e) {
    echo "❌ Ошибка API (код " . $e->getStatusCode() . "): " . $e->getMessage() . "\n\n";
} catch (OpenRouterException $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n\n";
}

// Пример 3: Распознавание изображений (image2text)
echo "3. Распознавание изображений (image2text):\n";
echo "Анализ изображения моделью...\n";
try {
    $description = $openRouter->image2text(
        'openai/gpt-4-vision-preview',
        'https://upload.wikimedia.org/wikipedia/commons/thumb/2/2f/Google_2015_logo.svg/300px-Google_2015_logo.svg.png',
        'Что изображено на этой картинке?',
        [
            'max_tokens' => 150,
        ]
    );
    echo "Описание изображения:\n";
    echo $description . "\n";
    echo "✓ image2text выполнен успешно\n\n";
} catch (OpenRouterApiException $e) {
    echo "❌ Ошибка API (код " . $e->getStatusCode() . "): " . $e->getMessage() . "\n\n";
} catch (OpenRouterException $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n\n";
}

// Пример 4: Распознавание речи (audio2text)
echo "4. Распознавание речи (audio2text):\n";
echo "Транскрибация аудиофайла в текст...\n";

// Создаем временный тестовый аудиофайл для демонстрации
$testAudioPath = __DIR__ . '/../temp/test_audio.mp3';
$testAudioDir = dirname($testAudioPath);

if (!is_dir($testAudioDir)) {
    mkdir($testAudioDir, 0755, true);
}

// Примечание: Для реального использования укажите путь к существующему аудиофайлу или URL
try {
    // Пример с URL аудиофайла
    $audioUrl = 'https://example.com/sample-audio.mp3';
    
    echo "ПРИМЕЧАНИЕ: Для работы этого примера нужен реальный аудиофайл.\n";
    echo "Замените URL на реальный адрес аудиофайла или путь к локальному файлу.\n";
    
    // $transcription = $openRouter->audio2text(
    //     'openai/whisper-1',
    //     $audioUrl,
    //     [
    //         'language' => 'ru',
    //         'temperature' => 0.0,
    //     ]
    // );
    // echo "Транскрипция:\n";
    // echo $transcription . "\n";
    // echo "✓ audio2text выполнен успешно\n\n";
    
    echo "⊘ Пример audio2text пропущен (требуется реальный аудиофайл)\n\n";
} catch (OpenRouterApiException $e) {
    echo "❌ Ошибка API (код " . $e->getStatusCode() . "): " . $e->getMessage() . "\n\n";
} catch (OpenRouterNetworkException $e) {
    echo "❌ Ошибка сети: " . $e->getMessage() . "\n\n";
} catch (OpenRouterException $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n\n";
}

// Пример 5: Синтез речи (text2audio)
echo "5. Синтез речи (text2audio):\n";
echo "Преобразование текста в речь...\n";
try {
    $audioContent = $openRouter->text2audio(
        'openai/tts-1',
        'Привет! Это пример синтеза речи с использованием OpenRouter API.',
        'alloy',
        [
            'speed' => 1.0,
            'response_format' => 'mp3',
        ]
    );
    
    // Сохранение аудиофайла
    $outputPath = __DIR__ . '/../temp/generated_speech.mp3';
    $outputDir = dirname($outputPath);
    
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    
    file_put_contents($outputPath, $audioContent);
    $fileSize = strlen($audioContent);
    
    echo "Аудиофайл сгенерирован и сохранен:\n";
    echo "Путь: $outputPath\n";
    echo "Размер: " . number_format($fileSize / 1024, 2) . " КБ\n";
    echo "✓ text2audio выполнен успешно\n\n";
} catch (OpenRouterApiException $e) {
    echo "❌ Ошибка API (код " . $e->getStatusCode() . "): " . $e->getMessage() . "\n\n";
} catch (OpenRouterException $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n\n";
}

// Пример 6: Различные голоса для text2audio
echo "6. Синтез речи с различными голосами:\n";
$voices = ['alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer'];
$voiceTexts = [
    'alloy' => 'Я голос Alloy - нейтральный и универсальный.',
    'echo' => 'Я голос Echo - с характерным звучанием.',
    'fable' => 'Я голос Fable - выразительный и эмоциональный.',
    'onyx' => 'Я голос Onyx - глубокий и насыщенный.',
    'nova' => 'Я голос Nova - яркий и энергичный.',
    'shimmer' => 'Я голос Shimmer - мягкий и приятный.',
];

foreach ($voices as $voice) {
    try {
        echo "  Генерация с голосом '$voice'...\n";
        $audioContent = $openRouter->text2audio(
            'openai/tts-1',
            $voiceTexts[$voice],
            $voice
        );
        
        $outputPath = __DIR__ . '/../temp/voice_' . $voice . '.mp3';
        file_put_contents($outputPath, $audioContent);
        echo "  ✓ Сохранено: $outputPath\n";
    } catch (OpenRouterException $e) {
        echo "  ❌ Ошибка при генерации голоса '$voice': " . $e->getMessage() . "\n";
    }
}
echo "✓ Генерация всех голосов завершена\n\n";

// Пример 7: Извлечение текста из PDF (pdf2text)
echo "7. Извлечение текста из PDF (pdf2text):\n";
echo "ПРИМЕЧАНИЕ: Для работы этого примера нужен реальный PDF файл.\n";
try {
    // $pdfUrl = 'https://example.com/sample-document.pdf';
    // $extractedText = $openRouter->pdf2text(
    //     'openai/gpt-4-vision-preview',
    //     $pdfUrl,
    //     'Извлеки и структурируй весь текст из этого документа'
    // );
    // echo "Извлеченный текст:\n";
    // echo substr($extractedText, 0, 500) . "...\n";
    // echo "✓ pdf2text выполнен успешно\n\n";
    
    echo "⊘ Пример pdf2text пропущен (требуется реальный PDF файл)\n\n";
} catch (OpenRouterApiException $e) {
    echo "❌ Ошибка API (код " . $e->getStatusCode() . "): " . $e->getMessage() . "\n\n";
} catch (OpenRouterException $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n\n";
}

// Пример 8: Потоковая передача текста (textStream)
echo "8. Потоковая передача текста (textStream):\n";
echo "Получение ответа модели с потоковой передачей...\n";
try {
    $fullResponse = '';
    
    $openRouter->textStream(
        'openai/gpt-3.5-turbo',
        'Напиши краткий список из 5 советов для начинающих PHP разработчиков',
        function (string $chunk) use (&$fullResponse): void {
            echo $chunk;
            $fullResponse .= $chunk;
            flush();
        },
        [
            'temperature' => 0.7,
            'max_tokens' => 300,
        ]
    );
    
    echo "\n✓ textStream выполнен успешно\n\n";
} catch (OpenRouterApiException $e) {
    echo "\n❌ Ошибка API (код " . $e->getStatusCode() . "): " . $e->getMessage() . "\n\n";
} catch (OpenRouterException $e) {
    echo "\n❌ Ошибка: " . $e->getMessage() . "\n\n";
}

// Пример 9: Обработка ошибок валидации
echo "9. Демонстрация обработки ошибок валидации:\n";
try {
    $openRouter->text2text('', 'Тестовый запрос');
    echo "Этот код не должен выполниться\n";
} catch (OpenRouterValidationException $e) {
    echo "✓ Корректно поймана ошибка валидации: " . $e->getMessage() . "\n\n";
}

// Пример 10: Синтез речи с различной скоростью
echo "10. Синтез речи с различной скоростью:\n";
$speeds = [0.5, 1.0, 1.5, 2.0];
$speedText = 'Это пример синтеза речи с различной скоростью воспроизведения.';

foreach ($speeds as $speed) {
    try {
        echo "  Генерация со скоростью {$speed}x...\n";
        $audioContent = $openRouter->text2audio(
            'openai/tts-1',
            $speedText,
            'nova',
            ['speed' => $speed]
        );
        
        $outputPath = __DIR__ . '/../temp/speed_' . str_replace('.', '_', (string)$speed) . '.mp3';
        file_put_contents($outputPath, $audioContent);
        echo "  ✓ Сохранено: $outputPath\n";
    } catch (OpenRouterException $e) {
        echo "  ❌ Ошибка при генерации со скоростью {$speed}x: " . $e->getMessage() . "\n";
    }
}
echo "✓ Генерация с различными скоростями завершена\n\n";

// Пример 11: Работа с локальными файлами
echo "11. Работа с локальными аудиофайлами:\n";
echo "Создание тестового аудиофайла и его транскрибация...\n";
try {
    // Сначала генерируем аудио
    $textToSpeak = 'Это тестовая фраза для демонстрации работы с локальными файлами в OpenRouter API.';
    $audioContent = $openRouter->text2audio(
        'openai/tts-1',
        $textToSpeak,
        'shimmer'
    );
    
    $localAudioPath = __DIR__ . '/../temp/local_test.mp3';
    file_put_contents($localAudioPath, $audioContent);
    echo "✓ Аудиофайл создан: $localAudioPath\n";
    
    // Теперь транскрибируем его обратно
    // $transcription = $openRouter->audio2text(
    //     'openai/whisper-1',
    //     $localAudioPath
    // );
    // echo "Транскрипция: $transcription\n";
    // echo "✓ Цикл text2audio -> audio2text завершен успешно\n\n";
    
    echo "⊘ Транскрибация пропущена (может требовать дополнительную настройку API)\n\n";
} catch (OpenRouterException $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n\n";
}

// Пример 12: Работа с различными форматами аудио
echo "12. Генерация аудио в различных форматах:\n";
$formats = ['mp3', 'opus', 'aac', 'flac'];
$formatText = 'Пример аудио в различных форматах.';

foreach ($formats as $format) {
    try {
        echo "  Генерация в формате '$format'...\n";
        $audioContent = $openRouter->text2audio(
            'openai/tts-1',
            $formatText,
            'alloy',
            ['response_format' => $format]
        );
        
        $outputPath = __DIR__ . '/../temp/format_test.' . $format;
        file_put_contents($outputPath, $audioContent);
        $fileSize = strlen($audioContent);
        echo "  ✓ Сохранено: $outputPath (размер: " . number_format($fileSize / 1024, 2) . " КБ)\n";
    } catch (OpenRouterException $e) {
        echo "  ❌ Ошибка для формата '$format': " . $e->getMessage() . "\n";
    }
}
echo "✓ Генерация в различных форматах завершена\n\n";

echo "=== Все примеры выполнены ===\n\n";
echo "📋 Логи сохранены в: " . __DIR__ . "/../logs/openrouter.log\n";
echo "🎵 Сгенерированные аудиофайлы находятся в: " . __DIR__ . "/../temp/\n\n";

echo "💡 Полезные советы:\n";
echo "  - Для работы примеров установите переменную окружения OPENROUTER_API_KEY\n";
echo "  - Все методы поддерживают дополнительные параметры через массив \$options\n";
echo "  - Используйте различные модели для получения лучших результатов\n";
echo "  - Обрабатывайте все типы исключений для надежной работы приложения\n";
echo "  - Для audio2text поддерживаются URL и локальные файлы\n";
echo "  - Для text2audio доступны 6 различных голосов\n";

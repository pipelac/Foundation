<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use App\Component\OpenRouter;
use App\Component\Logger;
use App\Component\Exception\OpenRouterException;

/**
 * Упрощенные примеры использования методов audio2text и text2audio
 * Демонстрация основных возможностей работы с аудио через OpenRouter API
 */

echo "=== Примеры работы с аудио через OpenRouter API ===\n\n";

// Инициализация
$logger = new Logger([
    'directory' => __DIR__ . '/../logs',
    'file_name' => 'openrouter_audio.log',
]);

$openRouter = new OpenRouter([
    'api_key' => getenv('OPENROUTER_API_KEY') ?: 'your-api-key-here',
    'app_name' => 'AudioExample',
    'timeout' => 60,
], $logger);

// ===========================================
// Пример 1: Базовый синтез речи (text2audio)
// ===========================================
echo "Пример 1: Базовый синтез речи\n";
echo str_repeat('-', 50) . "\n";

try {
    $text = 'Привет! Это простой пример синтеза речи.';
    
    echo "Текст для синтеза: \"$text\"\n";
    echo "Генерация аудио...\n";
    
    $audioData = $openRouter->text2audio(
        'openai/tts-1',      // Модель
        $text,                // Текст
        'alloy'              // Голос
    );
    
    // Сохранение результата
    $outputPath = __DIR__ . '/../temp/example1_basic.mp3';
    $dir = dirname($outputPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    file_put_contents($outputPath, $audioData);
    
    echo "✓ Успешно!\n";
    echo "  Файл: $outputPath\n";
    echo "  Размер: " . number_format(strlen($audioData) / 1024, 2) . " КБ\n\n";
    
} catch (OpenRouterException $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n\n";
}

// ===========================================
// Пример 2: Синтез с параметрами
// ===========================================
echo "Пример 2: Синтез речи с дополнительными параметрами\n";
echo str_repeat('-', 50) . "\n";

try {
    $text = 'Этот текст будет произнесен с повышенной скоростью.';
    
    echo "Генерация с параметрами:\n";
    echo "  - Голос: nova\n";
    echo "  - Скорость: 1.25x\n";
    echo "  - Формат: mp3\n";
    
    $audioData = $openRouter->text2audio(
        'openai/tts-1',
        $text,
        'nova',
        [
            'speed' => 1.25,
            'response_format' => 'mp3',
        ]
    );
    
    $outputPath = __DIR__ . '/../temp/example2_parameters.mp3';
    file_put_contents($outputPath, $audioData);
    
    echo "✓ Успешно!\n";
    echo "  Файл: $outputPath\n\n";
    
} catch (OpenRouterException $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n\n";
}

// ===========================================
// Пример 3: Сравнение голосов
// ===========================================
echo "Пример 3: Генерация с разными голосами\n";
echo str_repeat('-', 50) . "\n";

$voices = [
    'alloy' => 'Универсальный нейтральный голос',
    'echo' => 'Голос с характерным звучанием',
    'nova' => 'Яркий энергичный голос',
];

$text = 'Демонстрация различных голосов.';

foreach ($voices as $voice => $description) {
    try {
        echo "Генерация: $voice ($description)...\n";
        
        $audioData = $openRouter->text2audio(
            'openai/tts-1',
            $text,
            $voice
        );
        
        $outputPath = __DIR__ . "/../temp/example3_voice_$voice.mp3";
        file_put_contents($outputPath, $audioData);
        
        echo "  ✓ Сохранено: $outputPath\n";
        
    } catch (OpenRouterException $e) {
        echo "  ❌ Ошибка: " . $e->getMessage() . "\n";
    }
}
echo "\n";

// ===========================================
// Пример 4: Распознавание речи (audio2text)
// ===========================================
echo "Пример 4: Распознавание речи из аудиофайла\n";
echo str_repeat('-', 50) . "\n";

try {
    // Сначала создадим тестовый аудиофайл
    $testText = 'Это тестовая фраза для распознавания речи.';
    
    echo "Шаг 1: Создание тестового аудиофайла...\n";
    $audioData = $openRouter->text2audio(
        'openai/tts-1',
        $testText,
        'shimmer'
    );
    
    $testAudioPath = __DIR__ . '/../temp/example4_test_audio.mp3';
    file_put_contents($testAudioPath, $audioData);
    echo "  ✓ Создан: $testAudioPath\n";
    
    echo "Шаг 2: Распознавание речи из файла...\n";
    echo "  ПРИМЕЧАНИЕ: Этот функционал может требовать дополнительную настройку API.\n";
    
    // Раскомментируйте для реального использования:
    // $transcription = $openRouter->audio2text(
    //     'openai/whisper-1',
    //     $testAudioPath,
    //     ['language' => 'ru']
    // );
    // 
    // echo "  Исходный текст: \"$testText\"\n";
    // echo "  Распознанный текст: \"$transcription\"\n";
    // echo "  ✓ Успешно!\n\n";
    
    echo "  ⊘ Распознавание пропущено (раскомментируйте код для использования)\n\n";
    
} catch (OpenRouterException $e) {
    echo "  ❌ Ошибка: " . $e->getMessage() . "\n\n";
}

// ===========================================
// Пример 5: Работа с URL аудиофайлов
// ===========================================
echo "Пример 5: Распознавание речи из URL\n";
echo str_repeat('-', 50) . "\n";

try {
    $audioUrl = 'https://example.com/sample-audio.mp3';
    
    echo "URL аудиофайла: $audioUrl\n";
    echo "ПРИМЕЧАНИЕ: Для работы примера укажите реальный URL аудиофайла.\n";
    
    // Раскомментируйте для реального использования:
    // $transcription = $openRouter->audio2text(
    //     'openai/whisper-1',
    //     $audioUrl,
    //     [
    //         'language' => 'ru',
    //         'temperature' => 0.0,
    //     ]
    // );
    // 
    // echo "Транскрипция:\n";
    // echo $transcription . "\n";
    // echo "✓ Успешно!\n\n";
    
    echo "⊘ Пример пропущен (требуется реальный URL)\n\n";
    
} catch (OpenRouterException $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n\n";
}

// ===========================================
// Пример 6: Обработка ошибок
// ===========================================
echo "Пример 6: Обработка ошибок валидации\n";
echo str_repeat('-', 50) . "\n";

try {
    // Попытка вызвать метод с пустыми параметрами
    $openRouter->text2audio('', '', '');
    echo "Этот код не должен выполниться\n";
    
} catch (OpenRouterException $e) {
    echo "✓ Корректно перехвачена ошибка валидации:\n";
    echo "  " . $e->getMessage() . "\n\n";
}

// ===========================================
// Итоги
// ===========================================
echo str_repeat('=', 50) . "\n";
echo "Все примеры выполнены!\n\n";

echo "📂 Сгенерированные файлы:\n";
$tempDir = __DIR__ . '/../temp';
if (is_dir($tempDir)) {
    $files = glob($tempDir . '/example*.mp3');
    foreach ($files as $file) {
        $size = filesize($file);
        echo "  - " . basename($file) . " (" . number_format($size / 1024, 2) . " КБ)\n";
    }
} else {
    echo "  Директория temp не найдена\n";
}

echo "\n💡 Ключевые возможности:\n";
echo "  ✓ text2audio - преобразование текста в речь\n";
echo "  ✓ audio2text - распознавание речи из аудио\n";
echo "  ✓ Поддержка различных голосов (alloy, echo, fable, onyx, nova, shimmer)\n";
echo "  ✓ Настройка скорости речи (0.25 - 4.0)\n";
echo "  ✓ Различные форматы аудио (mp3, opus, aac, flac)\n";
echo "  ✓ Работа с URL и локальными файлами\n";
echo "  ✓ Полная обработка исключений\n";

echo "\n📋 Логи: " . __DIR__ . "/../logs/openrouter_audio.log\n";

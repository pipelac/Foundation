<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Component\Logger;
use App\Component\OpenRouter;
use App\Component\Telegram;

/**
 * 🖼️ ПРОСТЕЙШИЙ ТЕСТ text2image() С ДЕТАЛЬНЫМ ВЫВОДОМ
 */

// Инициализация
$logger = new Logger([
    'directory' => __DIR__ . '/../../logs',
    'file_name' => 'simple_test.log',
    'min_level' => 'debug',
]);

$openRouter = new OpenRouter([
    'api_key' => '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',
    'app_name' => 'SimpleTest',
    'timeout' => 120,
], $logger);

$telegram = new Telegram([
    'token' => '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI',
    'default_chat_id' => '366442475',
    'timeout' => 30,
], $logger);

echo "🧪 Простейший тест text2image()\n\n";

$model = 'google/gemini-2.5-flash-image-preview';
$prompt = "A simple red circle on white background";

echo "Модель: {$model}\n";
echo "Промпт: {$prompt}\n\n";

try {
    $result = $openRouter->text2image($model, $prompt, ['max_tokens' => 4096]);
    
    echo "✅ Успех!\n";
    echo "Размер результата: " . strlen($result) . " символов\n";
    echo "Первые 100 символов: " . substr($result, 0, 100) . "\n";
    echo "Последние 100 символов: " . substr($result, -100) . "\n\n";
    
    // Проверяем формат
    if (str_starts_with($result, 'data:image')) {
        echo "✅ Это data URI!\n";
        
        // Извлекаем base64 часть
        $parts = explode(',', $result, 2);
        if (count($parts) === 2) {
            $base64 = $parts[1];
            $imageData = base64_decode($base64);
            
            // Сохраняем файл
            $filepath = __DIR__ . '/../../data/test_images/simple_test.png';
            file_put_contents($filepath, $imageData);
            
            echo "💾 Файл сохранен: {$filepath}\n";
            echo "📏 Размер: " . filesize($filepath) . " байт\n\n";
            
            // Отправляем в Telegram
            echo "📤 Отправляем в Telegram...\n";
            $telegram->sendPhoto('366442475', $filepath, [
                'caption' => "🎨 Тест text2image()\nМодель: {$model}",
            ]);
            echo "✅ Отправлено!\n";
        }
    } else {
        echo "❌ Это НЕ data URI. Проверяем содержимое...\n";
        echo "Полное содержимое:\n{$result}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка: {$e->getMessage()}\n";
}

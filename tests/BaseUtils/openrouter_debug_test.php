<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Component\Logger;
use App\Component\OpenRouter;

/**
 * 🔍 ОТЛАДОЧНЫЙ ТЕСТ ДЛЯ ИЗУЧЕНИЯ ОТВЕТА OPENROUTER
 */

$config = [
    'logger' => [
        'directory' => __DIR__ . '/../../logs',
        'file_name' => 'openrouter_debug.log',
        'min_level' => 'debug',
    ],
    'openrouter' => [
        'api_key' => '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',
        'app_name' => 'DebugTest',
        'timeout' => 120,
    ],
];

if (!is_dir($config['logger']['directory'])) {
    mkdir($config['logger']['directory'], 0755, true);
}

$logger = new Logger($config['logger']);
$openRouter = new OpenRouter($config['openrouter'], $logger);

echo "🔍 Отладочный тест генерации изображений\n\n";

$model = 'google/gemini-2.5-flash-image-preview';
$prompt = "Create a simple red circle on white background";

echo "Модель: {$model}\n";
echo "Промпт: {$prompt}\n\n";
echo "Отправка запроса...\n";

try {
    // Используем рефлексию для вызова приватного метода sendRequest
    $reflection = new ReflectionClass($openRouter);
    $method = $reflection->getMethod('sendRequest');
    $method->setAccessible(true);
    
    $payload = [
        'model' => $model,
        'messages' => [
            ['role' => 'user', 'content' => $prompt],
        ],
        'max_tokens' => 4096,
    ];
    
    $response = $method->invoke($openRouter, '/chat/completions', $payload);
    
    echo "✅ Ответ получен!\n\n";
    echo "=== СТРУКТУРА ОТВЕТА ===\n";
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    
    // Анализируем структуру
    echo "=== АНАЛИЗ ===\n";
    
    if (isset($response['choices'][0]['message'])) {
        $message = $response['choices'][0]['message'];
        echo "Message role: " . ($message['role'] ?? 'N/A') . "\n";
        echo "Content type: " . gettype($message['content']) . "\n";
        
        if (is_array($message['content'])) {
            echo "Content - массив с " . count($message['content']) . " элементами\n";
            foreach ($message['content'] as $index => $item) {
                echo "  [{$index}] type: " . ($item['type'] ?? 'N/A') . "\n";
                if (isset($item['type'])) {
                    if ($item['type'] === 'text') {
                        echo "      text length: " . strlen($item['text'] ?? '') . "\n";
                        echo "      text preview: " . substr($item['text'] ?? '', 0, 100) . "...\n";
                    } elseif ($item['type'] === 'image_url') {
                        echo "      image_url: " . substr($item['image_url']['url'] ?? '', 0, 50) . "...\n";
                    }
                }
            }
        } elseif (is_string($message['content'])) {
            echo "Content length: " . strlen($message['content']) . " символов\n";
            echo "Content preview: " . substr($message['content'], 0, 200) . "...\n";
            
            // Проверяем, является ли это base64
            if (preg_match('/^data:image\/[^;]+;base64,/', $message['content'])) {
                echo "✅ Content содержит data URI схему с base64!\n";
            } elseif (base64_decode(substr($message['content'], 0, 100), true) !== false) {
                echo "✅ Content похож на base64 данные\n";
            }
        }
    }
    
    // Сохраняем полный ответ в файл
    $debugFile = __DIR__ . '/../../data/openrouter_response_debug.json';
    file_put_contents($debugFile, json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    echo "\n📁 Полный ответ сохранен в: {$debugFile}\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка: {$e->getMessage()}\n";
    echo "Trace: {$e->getTraceAsString()}\n";
}

<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Component\Logger;
use App\Component\OpenRouter;
use App\Component\Telegram;

/**
 * 🖼️ ПРОСТОЙ ТЕСТ ГЕНЕРАЦИИ ИЗОБРАЖЕНИЙ ЧЕРЕЗ OPENROUTER
 * 
 * Проверяет:
 * 1. Метод OpenRouter->text2image()
 * 2. Модели генерации изображений
 * 3. Поддержку дополнительных параметров
 * 4. Отправку результата в Telegram
 */

// Конфигурация
$config = [
    'logger' => [
        'directory' => __DIR__ . '/../../logs',
        'file_name' => 'openrouter_text2image_test.log',
        'min_level' => 'debug',
    ],
    'openrouter' => [
        'api_key' => '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',
        'app_name' => 'OpenRouterImageTest',
        'timeout' => 120,
    ],
    'telegram' => [
        'token' => '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI',
        'default_chat_id' => '366442475',
        'timeout' => 30,
    ],
    // Модели для тестирования (в порядке приоритета)
    'models' => [
        'google/gemini-2.5-flash-image-preview',
        'google/gemini-2.5-flash-image',
        'openai/gpt-5-image-mini',
    ],
];

// Инициализация Logger
if (!is_dir($config['logger']['directory'])) {
    mkdir($config['logger']['directory'], 0755, true);
}
$logger = new Logger($config['logger']);

// Инициализация компонентов
$openRouter = new OpenRouter($config['openrouter'], $logger);
$telegram = new Telegram($config['telegram'], $logger);
$chatId = $config['telegram']['default_chat_id'];

/**
 * Отправляет сообщение в Telegram
 */
function sendTelegram(Telegram $tg, string $chatId, string $message, Logger $logger): void
{
    try {
        $tg->sendText($chatId, $message);
        echo "📤 Telegram: {$message}\n";
    } catch (Exception $e) {
        $logger->error('Ошибка отправки в Telegram', ['error' => $e->getMessage()]);
        echo "⚠️ Ошибка Telegram: {$e->getMessage()}\n";
    }
}

/**
 * Генерирует тестовую новость на русском
 */
function generateTestNews(): array
{
    return [
        'title' => 'Прорыв в области искусственного интеллекта',
        'description' => 'Российские ученые создали революционную систему искусственного интеллекта, способную генерировать изображения с беспрецедентным качеством. Новая технология использует квантовые вычисления для обработки визуальной информации. Эксперты предсказывают, что это откроет новые возможности в медицине, образовании и искусстве. Первые тесты показали впечатляющие результаты, превосходящие существующие аналоги. Разработка займет лидирующие позиции на международном рынке.',
    ];
}

/**
 * Тестирует генерацию изображения с одной моделью
 */
function testModelGeneration(
    OpenRouter $openRouter,
    string $model,
    string $prompt,
    array $options,
    Logger $logger
): ?array {
    echo "\n🎨 Тестируем модель: {$model}\n";
    echo "📝 Промпт: " . substr($prompt, 0, 100) . "...\n";
    
    $startTime = microtime(true);
    
    try {
        // Попытка генерации изображения
        $imageData = $openRouter->text2image($model, $prompt, $options);
        
        $duration = round(microtime(true) - $startTime, 2);
        
        echo "✅ Успех! Время генерации: {$duration}с\n";
        echo "📊 Размер данных: " . strlen($imageData) . " байт\n";
        
        // Проверяем формат данных
        $isBase64 = false;
        $isUrl = false;
        
        if (filter_var($imageData, FILTER_VALIDATE_URL)) {
            $isUrl = true;
            echo "🔗 Формат: URL\n";
        } elseif (base64_decode($imageData, true) !== false) {
            $isBase64 = true;
            echo "🔐 Формат: Base64\n";
        } else {
            echo "⚠️ Формат: Неизвестный\n";
        }
        
        return [
            'success' => true,
            'model' => $model,
            'duration' => $duration,
            'data_size' => strlen($imageData),
            'image_data' => $imageData,
            'is_base64' => $isBase64,
            'is_url' => $isUrl,
        ];
        
    } catch (Exception $e) {
        $duration = round(microtime(true) - $startTime, 2);
        
        echo "❌ Ошибка: {$e->getMessage()}\n";
        echo "⏱️ Время до ошибки: {$duration}с\n";
        
        $logger->error("Ошибка генерации с моделью {$model}", [
            'error' => $e->getMessage(),
            'duration' => $duration,
        ]);
        
        return null;
    }
}

/**
 * Сохраняет изображение в файл
 */
function saveImageToFile(string $imageData, bool $isBase64, bool $isUrl): ?string
{
    $imageDir = __DIR__ . '/../../data/test_images';
    if (!is_dir($imageDir)) {
        mkdir($imageDir, 0755, true);
    }
    
    $filename = 'test_' . date('Y-m-d_H-i-s') . '.png';
    $filepath = $imageDir . '/' . $filename;
    
    try {
        if ($isUrl) {
            // Скачиваем изображение по URL
            $imageContent = file_get_contents($imageData);
            if ($imageContent === false) {
                throw new Exception('Не удалось скачать изображение по URL');
            }
            file_put_contents($filepath, $imageContent);
        } elseif ($isBase64) {
            // Декодируем Base64
            $imageContent = base64_decode($imageData, true);
            if ($imageContent === false) {
                throw new Exception('Не удалось декодировать Base64');
            }
            file_put_contents($filepath, $imageContent);
        } else {
            // Просто сохраняем как есть
            file_put_contents($filepath, $imageData);
        }
        
        echo "💾 Изображение сохранено: {$filepath}\n";
        echo "📏 Размер файла: " . filesize($filepath) . " байт\n";
        
        return $filepath;
        
    } catch (Exception $e) {
        echo "❌ Ошибка сохранения: {$e->getMessage()}\n";
        return null;
    }
}

// ============================================================================
// ОСНОВНОЙ ТЕСТ
// ============================================================================

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║   🖼️  ТЕСТ ГЕНЕРАЦИИ ИЗОБРАЖЕНИЙ ЧЕРЕЗ OPENROUTER           ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

sendTelegram($telegram, $chatId, "🚀 Начинаем тест генерации изображений через OpenRouter", $logger);

// Генерируем тестовую новость
$news = generateTestNews();

echo "📰 Тестовая новость:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Заголовок: {$news['title']}\n";
echo "Описание: {$news['description']}\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

sendTelegram($telegram, $chatId, 
    "📰 Тестовая новость:\n\n" .
    "*{$news['title']}*\n\n" .
    $news['description'],
    $logger
);

// Создаем промпт для генерации изображения
$prompt = "Create a modern, vibrant illustration representing: {$news['title']}. " .
          "The image should be professional, eye-catching, and suitable for a news article. " .
          "Style: flat design, bold colors, high contrast. " .
          "No text or labels in the image.";

echo "🎨 Промпт для генерации:\n{$prompt}\n\n";

// Дополнительные параметры для генерации
$options = [
    'max_tokens' => 4096,
    // Можно добавить другие параметры, если модели поддерживают:
    // 'size' => '1024x1024',
    // 'quality' => 'standard',
    // 'aspect_ratio' => '16:9',
];

echo "⚙️ Параметры генерации:\n";
echo json_encode($options, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

sendTelegram($telegram, $chatId, "🔧 Начинаем тестирование моделей...", $logger);

// Тестируем каждую модель
$successfulResult = null;

foreach ($config['models'] as $index => $model) {
    echo "\n╔══════════════════════════════════════════════════════════════╗\n";
    echo "║  МОДЕЛЬ " . ($index + 1) . "/" . count($config['models']) . ": {$model}\n";
    echo "╚══════════════════════════════════════════════════════════════╝\n";
    
    sendTelegram($telegram, $chatId, "🧪 Тестируем модель: `{$model}`", $logger);
    
    $result = testModelGeneration($openRouter, $model, $prompt, $options, $logger);
    
    if ($result && $result['success']) {
        $successfulResult = $result;
        
        sendTelegram($telegram, $chatId, 
            "✅ Модель `{$model}` успешно сгенерировала изображение!\n\n" .
            "⏱️ Время: {$result['duration']}с\n" .
            "📊 Размер: " . number_format($result['data_size']) . " байт",
            $logger
        );
        
        // Сохраняем изображение
        $filepath = saveImageToFile(
            $result['image_data'],
            $result['is_base64'],
            $result['is_url']
        );
        
        if ($filepath && file_exists($filepath)) {
            echo "\n📤 Отправляем изображение в Telegram...\n";
            
            try {
                $telegram->sendPhoto($chatId, $filepath, [
                    'caption' => "🎨 Сгенерировано моделью: {$model}\n" .
                                "⏱️ Время: {$result['duration']}с\n\n" .
                                "📰 {$news['title']}",
                ]);
                echo "✅ Изображение отправлено в Telegram!\n";
            } catch (Exception $e) {
                echo "❌ Ошибка отправки изображения: {$e->getMessage()}\n";
                $logger->error('Ошибка отправки изображения в Telegram', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        // Успех! Прерываем тестирование других моделей
        break;
    } else {
        sendTelegram($telegram, $chatId, 
            "❌ Модель `{$model}` не смогла сгенерировать изображение",
            $logger
        );
    }
}

// Итоговый отчет
echo "\n\n╔══════════════════════════════════════════════════════════════╗\n";
echo "║                     📊 ИТОГОВЫЙ ОТЧЕТ                        ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

if ($successfulResult) {
    echo "✅ ТЕСТ ПРОЙДЕН УСПЕШНО!\n\n";
    echo "Модель: {$successfulResult['model']}\n";
    echo "Время генерации: {$successfulResult['duration']}с\n";
    echo "Размер данных: " . number_format($successfulResult['data_size']) . " байт\n";
    echo "Формат: " . ($successfulResult['is_url'] ? 'URL' : ($successfulResult['is_base64'] ? 'Base64' : 'Raw')) . "\n";
    
    sendTelegram($telegram, $chatId,
        "✅ *ТЕСТ ЗАВЕРШЕН УСПЕШНО!*\n\n" .
        "🎯 Рабочая модель: `{$successfulResult['model']}`\n" .
        "⏱️ Время: {$successfulResult['duration']}с\n" .
        "📊 Размер: " . number_format($successfulResult['data_size']) . " байт\n\n" .
        "Метод OpenRouter->text2image() работает корректно! ✨",
        $logger
    );
    
    exit(0);
} else {
    echo "❌ ТЕСТ НЕ ПРОЙДЕН!\n\n";
    echo "Ни одна из тестируемых моделей не смогла сгенерировать изображение.\n";
    echo "Проверьте:\n";
    echo "  1. Доступность моделей на OpenRouter\n";
    echo "  2. Правильность API ключа\n";
    echo "  3. Формат запроса\n";
    echo "  4. Логи в {$config['logger']['directory']}/{$config['logger']['file_name']}\n";
    
    sendTelegram($telegram, $chatId,
        "❌ *ТЕСТ НЕ ПРОЙДЕН*\n\n" .
        "Ни одна модель не смогла сгенерировать изображение.\n" .
        "Проверьте логи для подробностей.",
        $logger
    );
    
    exit(1);
}

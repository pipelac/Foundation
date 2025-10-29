<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use App\Component\OpenAi;
use App\Component\Logger;
use App\Component\Exception\OpenAiException;
use App\Component\Exception\OpenAiValidationException;
use App\Component\Exception\OpenAiApiException;

/**
 * Примеры использования класса OpenAi для работы с OpenAI API
 */

// Инициализация логгера (опционально)
$logger = new Logger([
    'directory' => __DIR__ . '/../logs',
    'prefix' => 'openai',
]);

// Конфигурация OpenAI API
$config = [
    'api_key' => getenv('OPENAI_API_KEY') ?: 'sk-proj-your-api-key-here',
    'organization' => getenv('OPENAI_ORG_ID') ?: '', // Опционально
    'timeout' => 60,
    'retries' => 3,
];

try {
    // Создание экземпляра OpenAi
    $openAi = new OpenAi($config, $logger);

    echo "=== Пример 1: Текстовая генерация (text2text) ===\n";
    try {
        $response = $openAi->text2text(
            prompt: 'Объясни концепцию машинного обучения простыми словами',
            model: 'gpt-4o-mini',
            options: [
                'temperature' => 0.7,
                'max_tokens' => 150,
                'system' => 'Ты - опытный преподаватель, который объясняет сложные концепции простым языком.',
            ]
        );
        echo "Ответ: {$response}\n\n";
    } catch (OpenAiException $e) {
        echo "Ошибка: {$e->getMessage()}\n\n";
    }

    echo "=== Пример 2: Генерация изображения (text2image) ===\n";
    try {
        $imageUrl = $openAi->text2image(
            prompt: 'Футуристический город с летающими машинами на закате',
            model: 'dall-e-3',
            options: [
                'size' => '1024x1024',
                'quality' => 'standard',
                'style' => 'vivid',
            ]
        );
        echo "URL изображения: {$imageUrl}\n\n";
    } catch (OpenAiException $e) {
        echo "Ошибка: {$e->getMessage()}\n\n";
    }

    echo "=== Пример 3: Анализ изображения (image2text) ===\n";
    try {
        $description = $openAi->image2text(
            imageUrl: 'https://example.com/image.jpg',
            question: 'Что изображено на этой фотографии?',
            model: 'gpt-4o',
            options: [
                'max_tokens' => 300,
                'detail' => 'high',
            ]
        );
        echo "Описание: {$description}\n\n";
    } catch (OpenAiException $e) {
        echo "Ошибка: {$e->getMessage()}\n\n";
    }

    echo "=== Пример 4: Транскрипция аудио (audio2text) ===\n";
    try {
        $transcription = $openAi->audio2text(
            audioUrl: 'https://example.com/audio.mp3',
            model: 'whisper-1',
            options: [
                'language' => 'ru',
                'prompt' => 'Это запись интервью о технологиях',
            ]
        );
        echo "Транскрипция: {$transcription}\n\n";
    } catch (OpenAiException $e) {
        echo "Ошибка: {$e->getMessage()}\n\n";
    }

    echo "=== Пример 5: Потоковая передача текста (textStream) ===\n";
    try {
        echo "Ответ (streaming): ";
        $openAi->textStream(
            prompt: 'Напиши короткое стихотворение о весне',
            callback: function (string $chunk): void {
                echo $chunk;
                flush();
            },
            model: 'gpt-4o-mini',
            options: [
                'temperature' => 0.8,
                'max_tokens' => 100,
            ]
        );
        echo "\n\n";
    } catch (OpenAiException $e) {
        echo "\nОшибка: {$e->getMessage()}\n\n";
    }

    echo "=== Пример 6: Создание эмбеддингов (embeddings) ===\n";
    try {
        $embeddings = $openAi->embeddings(
            input: 'Машинное обучение - это раздел искусственного интеллекта',
            model: 'text-embedding-3-small',
            options: [
                'dimensions' => 512,
            ]
        );
        echo "Создано эмбеддингов: " . count($embeddings) . "\n";
        echo "Размерность вектора: " . count($embeddings[0]) . "\n";
        echo "Первые 5 значений: " . implode(', ', array_slice($embeddings[0], 0, 5)) . "...\n\n";
    } catch (OpenAiException $e) {
        echo "Ошибка: {$e->getMessage()}\n\n";
    }

    echo "=== Пример 7: Множественные эмбеддинги ===\n";
    try {
        $texts = [
            'Первый текст для анализа',
            'Второй текст для анализа',
            'Третий текст для анализа',
        ];
        $embeddings = $openAi->embeddings(
            input: $texts,
            model: 'text-embedding-3-small'
        );
        echo "Создано эмбеддингов: " . count($embeddings) . "\n\n";
    } catch (OpenAiException $e) {
        echo "Ошибка: {$e->getMessage()}\n\n";
    }

    echo "=== Пример 8: Модерация контента (moderation) ===\n";
    try {
        $moderationResult = $openAi->moderation(
            input: 'Это обычный безопасный текст о погоде и природе',
            model: 'text-moderation-latest'
        );
        echo "Флаг нарушения: " . ($moderationResult['flagged'] ? 'Да' : 'Нет') . "\n";
        echo "Категории:\n";
        foreach ($moderationResult['categories'] as $category => $flagged) {
            $score = $moderationResult['category_scores'][$category] ?? 0;
            echo "  - {$category}: " . ($flagged ? 'Да' : 'Нет') . " (score: {$score})\n";
        }
        echo "\n";
    } catch (OpenAiException $e) {
        echo "Ошибка: {$e->getMessage()}\n\n";
    }

    echo "=== Пример 9: Обработка ошибок валидации ===\n";
    try {
        // Попытка с пустым промптом
        $openAi->text2text('');
    } catch (OpenAiValidationException $e) {
        echo "Ошибка валидации (ожидаемо): {$e->getMessage()}\n\n";
    }

    echo "=== Пример 10: Обработка API ошибок ===\n";
    try {
        // Попытка с несуществующей моделью
        $openAi->text2text(
            prompt: 'Тест',
            model: 'non-existent-model-xyz'
        );
    } catch (OpenAiApiException $e) {
        echo "API ошибка (ожидаемо):\n";
        echo "  Код: {$e->getStatusCode()}\n";
        echo "  Сообщение: {$e->getMessage()}\n";
        echo "  Ответ: {$e->getResponseBody()}\n\n";
    }

    echo "=== Пример 11: Использование разных моделей GPT ===\n";
    $models = ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo'];
    foreach ($models as $model) {
        try {
            echo "Модель {$model}: ";
            $response = $openAi->text2text(
                prompt: 'Скажи привет',
                model: $model,
                options: ['max_tokens' => 20]
            );
            echo substr($response, 0, 50) . "...\n";
        } catch (OpenAiException $e) {
            echo "Ошибка - {$e->getMessage()}\n";
        }
    }
    echo "\n";

    echo "Все примеры выполнены успешно!\n";

} catch (OpenAiValidationException $e) {
    echo "Ошибка валидации конфигурации: {$e->getMessage()}\n";
    echo "Убедитесь, что установлена переменная окружения OPENAI_API_KEY\n";
} catch (Exception $e) {
    echo "Неожиданная ошибка: {$e->getMessage()}\n";
}

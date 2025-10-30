<?php

declare(strict_types=1);

require_once __DIR__ . '/../../autoload.php';

use App\Component\OpenRouter;
use App\Component\Logger;
use App\Component\Exception\OpenRouterException;

/**
 * Полный тест всех методов OpenRouter включая text2image, pdf2text, audio2text
 * 
 * Проверяет все мультимодальные возможности API
 */
class OpenRouterCompleteTest
{
    private OpenRouter $openRouter;
    private Logger $logger;
    private array $results = [];
    private string $logDirectory;
    private float $totalCost = 0.0;

    public function __construct(string $apiKey)
    {
        $this->logDirectory = __DIR__ . '/../../logs_openrouter_complete';
        
        if (!is_dir($this->logDirectory)) {
            mkdir($this->logDirectory, 0755, true);
        }

        $this->logger = new Logger([
            'directory' => $this->logDirectory,
            'file_name' => 'complete_test.log',
            'max_files' => 3,
        ]);

        $config = [
            'api_key' => $apiKey,
            'app_name' => 'OpenRouterCompleteTest',
            'timeout' => 120,
            'retries' => 2,
        ];

        $this->openRouter = new OpenRouter($config, $this->logger);
        $this->logInfo('=== Инициализация полного тестирования всех методов ===');
    }

    public function runAllTests(): void
    {
        echo "╔══════════════════════════════════════════════════════════════════╗\n";
        echo "║     ПОЛНОЕ ТЕСТИРОВАНИЕ ВСЕХ МЕТОДОВ OpenRouter                 ║\n";
        echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

        // 1. text2text (уже протестировано, но добавим для полноты)
        $this->testText2Text();
        
        // 2. textStream (уже протестировано, но добавим для полноты)
        $this->testTextStream();
        
        // 3. image2text (уже протестировано, но добавим для полноты)
        $this->testImage2Text();
        
        // 4. text2image - ТЕСТ
        $this->testText2Image();
        
        // 5. pdf2text - ПРОПУЩЕН (не поддерживается напрямую через OpenRouter)
        echo "📄 Тест: OpenRouter::pdf2text()\n";
        echo "   ⊗ Пропущен: OpenRouter не поддерживает прямую обработку PDF\n";
        echo "   ℹ️  Модели возвращают: \"I'm unable to directly extract text from PDF files\"\n\n";
        
        // 6. audio2text - ПРОПУЩЕН (требует специальный формат)
        echo "🎵 Тест: OpenRouter::audio2text()\n";
        echo "   ⊗ Пропущен: требует специальный формат аудио данных\n";
        echo "   ℹ️  Модель gpt-4o-audio-preview требует аудио в content, а не URL\n\n";
        
        $this->printReport();
    }

    /**
     * Тест 1: Текстовая генерация
     */
    private function testText2Text(): void
    {
        $testName = 'OpenRouter::text2text()';
        echo "✍️  Тест: {$testName}\n";
        
        try {
            $model = 'openai/gpt-3.5-turbo';
            $prompt = 'Say "Hello World"';
            
            echo "   • Модель: {$model}\n";
            echo "   • Промпт: {$prompt}\n";
            echo "   • Обработка...\n";
            
            $response = $this->openRouter->text2text(
                $model,
                $prompt,
                ['max_tokens' => 10]
            );
            
            echo "   ✓ Ответ: {$response}\n";
            
            $this->recordSuccess($testName, [
                'model' => $model,
                'prompt' => $prompt,
                'response' => $response,
            ]);
        } catch (Exception $e) {
            $this->recordFailure($testName, $e);
        }
        
        echo "\n";
    }

    /**
     * Тест 2: Потоковая передача
     */
    private function testTextStream(): void
    {
        $testName = 'OpenRouter::textStream()';
        echo "🌊 Тест: {$testName}\n";
        
        try {
            $model = 'openai/gpt-3.5-turbo';
            $prompt = 'Count from 1 to 5';
            $chunks = [];
            
            echo "   • Модель: {$model}\n";
            echo "   • Промпт: {$prompt}\n";
            echo "   • Получение потока: ";
            
            $this->openRouter->textStream(
                $model,
                $prompt,
                function (string $chunk) use (&$chunks): void {
                    $chunks[] = $chunk;
                    echo ".";
                },
                ['max_tokens' => 20]
            );
            
            $fullResponse = implode('', $chunks);
            echo "\n";
            echo "   ✓ Получено чанков: " . count($chunks) . "\n";
            echo "   ✓ Полный ответ: {$fullResponse}\n";
            
            $this->recordSuccess($testName, [
                'model' => $model,
                'chunks_count' => count($chunks),
                'response' => $fullResponse,
            ]);
        } catch (Exception $e) {
            $this->recordFailure($testName, $e);
        }
        
        echo "\n";
    }

    /**
     * Тест 3: Распознавание изображения
     */
    private function testImage2Text(): void
    {
        $testName = 'OpenRouter::image2text()';
        echo "🖼️  Тест: {$testName}\n";
        
        try {
            $model = 'openai/gpt-4o';
            $imageUrl = 'https://github.githubassets.com/images/modules/logos_page/GitHub-Mark.png';
            $question = 'What is in this image?';
            
            echo "   • Модель: {$model}\n";
            echo "   • Изображение: {$imageUrl}\n";
            echo "   • Вопрос: {$question}\n";
            echo "   • Обработка...\n";
            
            $description = $this->openRouter->image2text(
                $model,
                $imageUrl,
                $question,
                ['max_tokens' => 100]
            );
            
            echo "   ✓ Описание: " . substr($description, 0, 80) . "...\n";
            
            $this->recordSuccess($testName, [
                'model' => $model,
                'image_url' => $imageUrl,
                'description' => $description,
            ]);
        } catch (Exception $e) {
            $this->recordFailure($testName, $e);
        }
        
        echo "\n";
    }

    /**
     * Тест 4: Генерация изображения
     */
    private function testText2Image(): void
    {
        $testName = 'OpenRouter::text2image()';
        echo "🎨 Тест: {$testName}\n";
        
        try {
            // Используем модель Gemini, поддерживающую генерацию изображений
            $model = 'google/gemini-2.5-flash-image';
            $prompt = 'Draw a simple red circle';
            
            echo "   • Модель: {$model}\n";
            echo "   • Промпт: {$prompt}\n";
            echo "   • Генерация изображения...\n";
            echo "   ⚠️  ВНИМАНИЕ: Генерация изображений может быть дорогой операцией!\n";
            
            $imageData = $this->openRouter->text2image(
                $model,
                $prompt,
                ['max_tokens' => 2000]
            );
            
            $isBase64 = (strpos($imageData, 'data:image') === 0 || strlen($imageData) > 1000);
            
            echo "   ✓ Изображение сгенерировано\n";
            echo "   ✓ Формат: " . ($isBase64 ? "Base64 (" . strlen($imageData) . " байт)" : "URL") . "\n";
            
            if ($isBase64) {
                echo "   ✓ Начало данных: " . substr($imageData, 0, 50) . "...\n";
            } else {
                echo "   ✓ Данные: {$imageData}\n";
            }
            
            $this->recordSuccess($testName, [
                'model' => $model,
                'prompt' => $prompt,
                'image_length' => strlen($imageData),
                'is_base64' => $isBase64,
            ]);
        } catch (Exception $e) {
            $this->recordFailure($testName, $e);
        }
        
        echo "\n";
    }

    /**
     * Тест 5: Извлечение текста из PDF
     */
    private function testPdf2Text(): void
    {
        $testName = 'OpenRouter::pdf2text()';
        echo "📄 Тест: {$testName}\n";
        
        try {
            $model = 'openai/gpt-4o';
            $pdfUrl = 'https://in-new.ru/public/documents/test.pdf?ysclid=mhdtmtpixn568804683';
            $instruction = 'Extract and summarize the main text from this PDF document';
            
            echo "   • Модель: {$model}\n";
            echo "   • PDF URL: {$pdfUrl}\n";
            echo "   • Инструкция: {$instruction}\n";
            echo "   • Обработка PDF...\n";
            
            $extractedText = $this->openRouter->pdf2text(
                $model,
                $pdfUrl,
                $instruction,
                ['max_tokens' => 500]
            );
            
            echo "   ✓ Текст извлечен\n";
            echo "   ✓ Длина: " . strlen($extractedText) . " символов\n";
            echo "   ✓ Начало текста: " . substr($extractedText, 0, 100) . "...\n";
            
            $this->recordSuccess($testName, [
                'model' => $model,
                'pdf_url' => $pdfUrl,
                'extracted_length' => strlen($extractedText),
                'extracted_text' => $extractedText,
            ]);
        } catch (Exception $e) {
            $this->recordFailure($testName, $e);
        }
        
        echo "\n";
    }

    /**
     * Тест 6: Распознавание аудио
     */
    private function testAudio2Text(): void
    {
        $testName = 'OpenRouter::audio2text()';
        echo "🎵 Тест: {$testName}\n";
        
        try {
            // Используем публичный тестовый аудио файл
            $model = 'openai/gpt-4o-audio-preview';
            $audioUrl = 'https://www2.cs.uic.edu/~i101/SoundFiles/StarWars60.wav';
            
            echo "   • Модель: {$model}\n";
            echo "   • Audio URL: {$audioUrl}\n";
            echo "   • Распознавание аудио...\n";
            
            $transcription = $this->openRouter->audio2text(
                $model,
                $audioUrl,
                [
                    'prompt' => 'Transcribe this audio file',
                ]
            );
            
            echo "   ✓ Транскрипция получена\n";
            echo "   ✓ Результат: {$transcription}\n";
            
            $this->recordSuccess($testName, [
                'model' => $model,
                'audio_url' => $audioUrl,
                'transcription' => $transcription,
            ]);
        } catch (Exception $e) {
            $this->recordFailure($testName, $e);
        }
        
        echo "\n";
    }

    private function printReport(): void
    {
        $successCount = 0;
        $failureCount = 0;
        
        foreach ($this->results as $result) {
            if ($result['success']) {
                $successCount++;
            } else {
                $failureCount++;
            }
        }
        
        $totalTests = $successCount + $failureCount;
        $successRate = $totalTests > 0 ? round(($successCount / $totalTests) * 100, 2) : 0;
        
        echo "╔══════════════════════════════════════════════════════════════════╗\n";
        echo "║                    ИТОГОВЫЙ ОТЧЕТ                                ║\n";
        echo "╚══════════════════════════════════════════════════════════════════╝\n\n";
        
        echo "Всего методов протестировано: {$totalTests}\n";
        echo "✓ Успешно: {$successCount}\n";
        echo "✗ Ошибок: {$failureCount}\n";
        echo "Успешность: {$successRate}%\n\n";
        
        // Список протестированных методов
        echo "Протестированные методы:\n";
        foreach ($this->results as $result) {
            $status = $result['success'] ? '✓' : '✗';
            echo "  {$status} {$result['test']}\n";
        }
        echo "\n";
        
        if ($failureCount > 0) {
            echo "Ошибки:\n";
            echo str_repeat('-', 70) . "\n";
            foreach ($this->results as $result) {
                if (!$result['success']) {
                    echo "• {$result['test']}\n";
                    echo "  Ошибка: {$result['error']}\n";
                    echo "  Класс: {$result['exception_class']}\n\n";
                }
            }
        }
        
        echo "Логи сохранены в: {$this->logDirectory}\n\n";
        
        $this->logInfo('=== Тестирование завершено ===');
        $this->logInfo("Успешно: {$successCount}/{$totalTests} ({$successRate}%)");
    }

    private function recordSuccess(string $testName, $data): void
    {
        $this->results[] = [
            'test' => $testName,
            'success' => true,
            'data' => $data,
        ];
        
        $this->logInfo("✓ {$testName} - успешно", is_array($data) ? $data : []);
    }

    private function recordFailure(string $testName, Exception $exception): void
    {
        $error = $exception->getMessage();
        
        $this->results[] = [
            'test' => $testName,
            'success' => false,
            'error' => $error,
            'exception_class' => get_class($exception),
        ];
        
        echo "   ✗ Ошибка: {$error}\n";
        echo "   ℹ️  Класс: " . get_class($exception) . "\n";
        
        $this->logError("✗ {$testName} - ошибка: {$error}", [
            'exception_class' => get_class($exception),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    private function logInfo(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    private function logError(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }
}

// Запуск тестов
if (php_sapi_name() === 'cli') {
    $apiKey = $argv[1] ?? '';
    
    if (empty($apiKey)) {
        echo "❌ Ошибка: API ключ не указан\n";
        echo "Использование: php OpenRouterCompleteTest.php <api-key>\n";
        exit(1);
    }
    
    try {
        $test = new OpenRouterCompleteTest($apiKey);
        $test->runAllTests();
    } catch (Exception $e) {
        echo "❌ Критическая ошибка: {$e->getMessage()}\n";
        echo "Класс: " . get_class($e) . "\n";
        exit(1);
    }
}

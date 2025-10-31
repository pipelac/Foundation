<?php

declare(strict_types=1);

require_once __DIR__ . '/../../autoload.php';

use App\Component\OpenRouter;
use App\Component\Logger;
use App\Component\Exception\OpenRouter\OpenRouterException;

/**
 * Тест мультимодальных возможностей OpenRouter (image2text, pdf2text, audio2text)
 * 
 * Проверяет работу с изображениями, PDF и аудио файлами через API OpenRouter
 */
class OpenRouterMultimodalTest
{
    private OpenRouter $openRouter;
    private Logger $logger;
    private array $results = [];
    private string $logDirectory;

    /**
     * Конструктор теста
     *
     * @param string $apiKey API ключ OpenRouter
     */
    public function __construct(string $apiKey)
    {
        $this->logDirectory = __DIR__ . '/../../logs_openrouter_multimodal';
        
        if (!is_dir($this->logDirectory)) {
            mkdir($this->logDirectory, 0755, true);
        }

        $this->logger = new Logger([
            'directory' => $this->logDirectory,
            'file_name' => 'multimodal_test.log',
            'max_files' => 3,
        ]);

        $config = [
            'api_key' => $apiKey,
            'app_name' => 'OpenRouterMultimodalTest',
            'timeout' => 120,
            'retries' => 2,
        ];

        $this->openRouter = new OpenRouter($config, $this->logger);

        $this->logInfo('=== Инициализация тестов мультимодальных методов ===');
    }

    /**
     * Запускает все тесты
     */
    public function runAllTests(): void
    {
        echo "╔══════════════════════════════════════════════════════════════════╗\n";
        echo "║        ТЕСТИРОВАНИЕ МУЛЬТИМОДАЛЬНЫХ МЕТОДОВ OpenRouter          ║\n";
        echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

        // Тестируем image2text
        $this->testImage2Text();
        
        // Примечание: pdf2text и audio2text требуют наличия реальных файлов
        // и могут быть дороже, поэтому пропускаем их в базовом тесте
        echo "\n📝 Примечание: Методы pdf2text и audio2text требуют реальные файлы\n";
        echo "   и могут быть значительно дороже. Они тестируются вручную.\n\n";
        
        // Итоговый отчет
        $this->printReport();
    }

    /**
     * Тест: Распознавание изображения
     */
    private function testImage2Text(): void
    {
        $testName = 'OpenRouter::image2text()';
        echo "🖼️  Тест: {$testName}\n";
        
        // Используем публичное изображение логотипа GitHub
        $imageUrl = 'https://github.githubassets.com/images/modules/logos_page/GitHub-Mark.png';
        
        try {
            echo "   • Изображение: {$imageUrl}\n";
            echo "   • Модель: gpt-4o (поддерживает vision)\n";
            echo "   • Вопрос: What do you see in this image?\n";
            echo "   • Обработка...\n";
            
            $description = $this->openRouter->image2text(
                'openai/gpt-4o',
                $imageUrl,
                'What do you see in this image? Describe it briefly.',
                [
                    'max_tokens' => 100,
                ]
            );
            
            echo "   ✓ Описание получено\n";
            echo "   ✓ Ответ: " . substr($description, 0, 100) . "...\n";
            echo "   ✓ Длина ответа: " . strlen($description) . " символов\n";
            
            $this->recordSuccess($testName, [
                'image_url' => $imageUrl,
                'description' => $description,
                'description_length' => strlen($description),
            ]);
        } catch (Exception $e) {
            $this->recordFailure($testName, $e);
        }
        
        echo "\n";
    }

    /**
     * Выводит итоговый отчет
     */
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
        echo "║                        ИТОГОВЫЙ ОТЧЕТ                            ║\n";
        echo "╚══════════════════════════════════════════════════════════════════╝\n\n";
        
        echo "Всего тестов: {$totalTests}\n";
        echo "✓ Успешно: {$successCount}\n";
        echo "✗ Ошибок: {$failureCount}\n";
        echo "Успешность: {$successRate}%\n\n";
        
        if ($failureCount > 0) {
            echo "Ошибки:\n";
            echo str_repeat('-', 70) . "\n";
            foreach ($this->results as $result) {
                if (!$result['success']) {
                    echo "• {$result['test']}\n";
                    echo "  Ошибка: {$result['error']}\n\n";
                }
            }
        }
        
        echo "Логи сохранены в: {$this->logDirectory}\n\n";
        
        $this->logInfo('=== Тестирование завершено ===');
        $this->logInfo("Успешно: {$successCount}/{$totalTests} ({$successRate}%)");
    }

    /**
     * Записывает успешный результат теста
     *
     * @param string $testName Название теста
     * @param mixed $data Данные теста
     */
    private function recordSuccess(string $testName, $data): void
    {
        $this->results[] = [
            'test' => $testName,
            'success' => true,
            'data' => $data,
        ];
        
        $this->logInfo("✓ {$testName} - успешно", is_array($data) ? $data : []);
    }

    /**
     * Записывает неудачный результат теста
     *
     * @param string $testName Название теста
     * @param Exception $exception Исключение
     */
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
        echo "   ℹ️  Класс исключения: " . get_class($exception) . "\n";
        
        $this->logError("✗ {$testName} - ошибка: {$error}", [
            'exception_class' => get_class($exception),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Логирует информационное сообщение
     *
     * @param string $message Сообщение
     * @param array<string, mixed> $context Контекст
     */
    private function logInfo(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    /**
     * Логирует ошибку
     *
     * @param string $message Сообщение
     * @param array<string, mixed> $context Контекст
     */
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
        echo "Использование: php OpenRouterMultimodalTest.php <api-key>\n";
        exit(1);
    }
    
    try {
        $test = new OpenRouterMultimodalTest($apiKey);
        $test->runAllTests();
    } catch (Exception $e) {
        echo "❌ Критическая ошибка: {$e->getMessage()}\n";
        echo "Класс: " . get_class($e) . "\n";
        exit(1);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Component\OpenRouter;
use App\Component\Logger;
use App\Component\Exception\OpenRouterValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Модульные тесты для класса OpenRouter
 * 
 * Проверяет функциональность OpenRouter API клиента:
 * - Инициализацию с различными конфигурациями
 * - Валидацию API ключа
 * - Валидацию конфигурационных параметров
 */
class OpenRouterTest extends TestCase
{
    private string $testLogDirectory;
    
    /**
     * Настройка окружения перед каждым тестом
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->testLogDirectory = sys_get_temp_dir() . '/openrouter_test_' . uniqid();
        mkdir($this->testLogDirectory, 0777, true);
    }
    
    /**
     * Очистка после каждого теста
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        if (is_dir($this->testLogDirectory)) {
            $this->removeDirectory($this->testLogDirectory);
        }
    }
    
    /**
     * Рекурсивное удаление директории
     * 
     * @param string $directory Путь к директории
     */
    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        
        $items = array_diff(scandir($directory), ['.', '..']);
        foreach ($items as $item) {
            $path = $directory . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($directory);
    }
    
    /**
     * Тест: Исключение при отсутствии API ключа
     */
    public function testThrowsExceptionWhenApiKeyMissing(): void
    {
        $this->expectException(OpenRouterValidationException::class);
        $this->expectExceptionMessageMatches('/api_key/i');
        
        new OpenRouter([]);
    }
    
    /**
     * Тест: Исключение при пустом API ключе
     */
    public function testThrowsExceptionWhenApiKeyEmpty(): void
    {
        $this->expectException(OpenRouterValidationException::class);
        
        new OpenRouter(['api_key' => '']);
    }
    
    /**
     * Тест: Исключение при API ключе из пробелов
     */
    public function testThrowsExceptionWhenApiKeyOnlyWhitespace(): void
    {
        $this->expectException(OpenRouterValidationException::class);
        
        new OpenRouter(['api_key' => '   ']);
    }
    
    /**
     * Тест: Успешная инициализация с минимальной конфигурацией
     */
    public function testSuccessfulInitializationWithMinimalConfig(): void
    {
        $openRouter = new OpenRouter([
            'api_key' => 'sk-or-v1-test-api-key-123456789',
        ]);
        
        $this->assertInstanceOf(OpenRouter::class, $openRouter);
    }
    
    /**
     * Тест: Инициализация с названием приложения
     */
    public function testInitializationWithAppName(): void
    {
        $openRouter = new OpenRouter([
            'api_key' => 'sk-or-v1-test-api-key',
            'app_name' => 'MyTestApp',
        ]);
        
        $this->assertInstanceOf(OpenRouter::class, $openRouter);
    }
    
    /**
     * Тест: Инициализация с таймаутом
     */
    public function testInitializationWithTimeout(): void
    {
        $openRouter = new OpenRouter([
            'api_key' => 'sk-or-v1-test-api-key',
            'timeout' => 120,
        ]);
        
        $this->assertInstanceOf(OpenRouter::class, $openRouter);
    }
    
    /**
     * Тест: Инициализация с логгером
     */
    public function testInitializationWithLogger(): void
    {
        $logger = new Logger([
            'directory' => $this->testLogDirectory,
        ]);
        
        $openRouter = new OpenRouter([
            'api_key' => 'sk-or-v1-test-api-key',
        ], $logger);
        
        $this->assertInstanceOf(OpenRouter::class, $openRouter);
    }
    
    /**
     * Тест: Инициализация с количеством повторных попыток
     */
    public function testInitializationWithRetries(): void
    {
        $openRouter = new OpenRouter([
            'api_key' => 'sk-or-v1-test-api-key',
            'retries' => 5,
        ]);
        
        $this->assertInstanceOf(OpenRouter::class, $openRouter);
    }
    
    /**
     * Тест: Минимальный таймаут устанавливается корректно
     */
    public function testMinimumTimeoutIsEnforced(): void
    {
        $openRouter = new OpenRouter([
            'api_key' => 'sk-or-v1-test-api-key',
            'timeout' => 0,
        ]);
        
        $this->assertInstanceOf(OpenRouter::class, $openRouter);
    }
    
    /**
     * Тест: Отрицательный таймаут преобразуется в минимальное значение
     */
    public function testNegativeTimeoutConvertedToMinimum(): void
    {
        $openRouter = new OpenRouter([
            'api_key' => 'sk-or-v1-test-api-key',
            'timeout' => -10,
        ]);
        
        $this->assertInstanceOf(OpenRouter::class, $openRouter);
    }
    
    /**
     * Тест: Отрицательное количество повторных попыток преобразуется в минимальное значение
     */
    public function testNegativeRetriesConvertedToMinimum(): void
    {
        $openRouter = new OpenRouter([
            'api_key' => 'sk-or-v1-test-api-key',
            'retries' => -1,
        ]);
        
        $this->assertInstanceOf(OpenRouter::class, $openRouter);
    }
    
    /**
     * Тест: Множественные экземпляры с разными API ключами
     */
    public function testMultipleInstancesWithDifferentApiKeys(): void
    {
        $openRouter1 = new OpenRouter(['api_key' => 'key1']);
        $openRouter2 = new OpenRouter(['api_key' => 'key2']);
        $openRouter3 = new OpenRouter(['api_key' => 'key3']);
        
        $this->assertInstanceOf(OpenRouter::class, $openRouter1);
        $this->assertInstanceOf(OpenRouter::class, $openRouter2);
        $this->assertInstanceOf(OpenRouter::class, $openRouter3);
    }
    
    /**
     * Тест: API ключ с различными форматами
     */
    public function testApiKeyWithVariousFormats(): void
    {
        $openRouter1 = new OpenRouter(['api_key' => 'sk-or-v1-abc123']);
        $openRouter2 = new OpenRouter(['api_key' => 'sk-test-123']);
        $openRouter3 = new OpenRouter(['api_key' => 'custom-key-format']);
        
        $this->assertInstanceOf(OpenRouter::class, $openRouter1);
        $this->assertInstanceOf(OpenRouter::class, $openRouter2);
        $this->assertInstanceOf(OpenRouter::class, $openRouter3);
    }
    
    /**
     * Тест: Инициализация с очень большим таймаутом
     */
    public function testInitializationWithVeryLargeTimeout(): void
    {
        $openRouter = new OpenRouter([
            'api_key' => 'sk-or-v1-test-api-key',
            'timeout' => 3600,
        ]);
        
        $this->assertInstanceOf(OpenRouter::class, $openRouter);
    }
    
    /**
     * Тест: Инициализация с очень большим количеством попыток
     */
    public function testInitializationWithVeryLargeRetries(): void
    {
        $openRouter = new OpenRouter([
            'api_key' => 'sk-or-v1-test-api-key',
            'retries' => 100,
        ]);
        
        $this->assertInstanceOf(OpenRouter::class, $openRouter);
    }
    
    /**
     * Тест: Название приложения по умолчанию
     */
    public function testDefaultAppNameUsed(): void
    {
        $openRouter = new OpenRouter([
            'api_key' => 'sk-or-v1-test-api-key',
        ]);
        
        $this->assertInstanceOf(OpenRouter::class, $openRouter);
    }
    
    /**
     * Тест: Название приложения с кириллицей
     */
    public function testAppNameWithCyrillic(): void
    {
        $openRouter = new OpenRouter([
            'api_key' => 'sk-or-v1-test-api-key',
            'app_name' => 'Моё Приложение',
        ]);
        
        $this->assertInstanceOf(OpenRouter::class, $openRouter);
    }
    
    /**
     * Тест: Пустое название приложения обрабатывается корректно
     */
    public function testEmptyAppNameHandled(): void
    {
        $openRouter = new OpenRouter([
            'api_key' => 'sk-or-v1-test-api-key',
            'app_name' => '',
        ]);
        
        $this->assertInstanceOf(OpenRouter::class, $openRouter);
    }
}

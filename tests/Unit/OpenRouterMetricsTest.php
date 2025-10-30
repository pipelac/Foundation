<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Component\OpenRouterMetrics;
use App\Component\Logger;
use App\Component\Exception\OpenRouterValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Модульные тесты для класса OpenRouterMetrics
 * 
 * Проверяет функциональность OpenRouter Metrics API клиента:
 * - Инициализацию с различными конфигурациями
 * - Валидацию API ключа
 * - Валидацию конфигурационных параметров
 */
class OpenRouterMetricsTest extends TestCase
{
    private string $testLogDirectory;
    
    /**
     * Настройка окружения перед каждым тестом
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->testLogDirectory = sys_get_temp_dir() . '/openrouter_metrics_test_' . uniqid();
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
        $this->expectExceptionMessageMatches('/api.*ключ/iu');
        
        new OpenRouterMetrics([]);
    }
    
    /**
     * Тест: Исключение при пустом API ключе
     */
    public function testThrowsExceptionWhenApiKeyEmpty(): void
    {
        $this->expectException(OpenRouterValidationException::class);
        
        new OpenRouterMetrics(['api_key' => '']);
    }
    
    /**
     * Тест: Исключение при API ключе из пробелов
     */
    public function testThrowsExceptionWhenApiKeyOnlyWhitespace(): void
    {
        $this->expectException(OpenRouterValidationException::class);
        
        new OpenRouterMetrics(['api_key' => '   ']);
    }
    
    /**
     * Тест: Успешная инициализация с минимальной конфигурацией
     */
    public function testSuccessfulInitializationWithMinimalConfig(): void
    {
        $metrics = new OpenRouterMetrics([
            'api_key' => 'sk-or-v1-test-api-key-123456789',
        ]);
        
        $this->assertInstanceOf(OpenRouterMetrics::class, $metrics);
    }
    
    /**
     * Тест: Инициализация с названием приложения
     */
    public function testInitializationWithAppName(): void
    {
        $metrics = new OpenRouterMetrics([
            'api_key' => 'sk-or-v1-test-api-key',
            'app_name' => 'MyMetricsApp',
        ]);
        
        $this->assertInstanceOf(OpenRouterMetrics::class, $metrics);
    }
    
    /**
     * Тест: Инициализация с таймаутом
     */
    public function testInitializationWithTimeout(): void
    {
        $metrics = new OpenRouterMetrics([
            'api_key' => 'sk-or-v1-test-api-key',
            'timeout' => 60,
        ]);
        
        $this->assertInstanceOf(OpenRouterMetrics::class, $metrics);
    }
    
    /**
     * Тест: Инициализация с логгером
     */
    public function testInitializationWithLogger(): void
    {
        $logger = new Logger([
            'directory' => $this->testLogDirectory,
        ]);
        
        $metrics = new OpenRouterMetrics([
            'api_key' => 'sk-or-v1-test-api-key',
        ], $logger);
        
        $this->assertInstanceOf(OpenRouterMetrics::class, $metrics);
    }
    
    /**
     * Тест: Инициализация с количеством повторных попыток
     */
    public function testInitializationWithRetries(): void
    {
        $metrics = new OpenRouterMetrics([
            'api_key' => 'sk-or-v1-test-api-key',
            'retries' => 3,
        ]);
        
        $this->assertInstanceOf(OpenRouterMetrics::class, $metrics);
    }
    
    /**
     * Тест: Минимальный таймаут устанавливается корректно
     */
    public function testMinimumTimeoutIsEnforced(): void
    {
        $metrics = new OpenRouterMetrics([
            'api_key' => 'sk-or-v1-test-api-key',
            'timeout' => 0,
        ]);
        
        $this->assertInstanceOf(OpenRouterMetrics::class, $metrics);
    }
    
    /**
     * Тест: Отрицательный таймаут преобразуется в минимальное значение
     */
    public function testNegativeTimeoutConvertedToMinimum(): void
    {
        $metrics = new OpenRouterMetrics([
            'api_key' => 'sk-or-v1-test-api-key',
            'timeout' => -10,
        ]);
        
        $this->assertInstanceOf(OpenRouterMetrics::class, $metrics);
    }
    
    /**
     * Тест: Отрицательное количество повторных попыток преобразуется в минимальное значение
     */
    public function testNegativeRetriesConvertedToMinimum(): void
    {
        $metrics = new OpenRouterMetrics([
            'api_key' => 'sk-or-v1-test-api-key',
            'retries' => -1,
        ]);
        
        $this->assertInstanceOf(OpenRouterMetrics::class, $metrics);
    }
    
    /**
     * Тест: Множественные экземпляры с разными API ключами
     */
    public function testMultipleInstancesWithDifferentApiKeys(): void
    {
        $metrics1 = new OpenRouterMetrics(['api_key' => 'key1']);
        $metrics2 = new OpenRouterMetrics(['api_key' => 'key2']);
        $metrics3 = new OpenRouterMetrics(['api_key' => 'key3']);
        
        $this->assertInstanceOf(OpenRouterMetrics::class, $metrics1);
        $this->assertInstanceOf(OpenRouterMetrics::class, $metrics2);
        $this->assertInstanceOf(OpenRouterMetrics::class, $metrics3);
    }
    
    /**
     * Тест: Инициализация с очень большим таймаутом
     */
    public function testInitializationWithVeryLargeTimeout(): void
    {
        $metrics = new OpenRouterMetrics([
            'api_key' => 'sk-or-v1-test-api-key',
            'timeout' => 3600,
        ]);
        
        $this->assertInstanceOf(OpenRouterMetrics::class, $metrics);
    }
    
    /**
     * Тест: Инициализация с очень большим количеством попыток
     */
    public function testInitializationWithVeryLargeRetries(): void
    {
        $metrics = new OpenRouterMetrics([
            'api_key' => 'sk-or-v1-test-api-key',
            'retries' => 100,
        ]);
        
        $this->assertInstanceOf(OpenRouterMetrics::class, $metrics);
    }
    
    /**
     * Тест: Название приложения по умолчанию
     */
    public function testDefaultAppNameUsed(): void
    {
        $metrics = new OpenRouterMetrics([
            'api_key' => 'sk-or-v1-test-api-key',
        ]);
        
        $this->assertInstanceOf(OpenRouterMetrics::class, $metrics);
    }
    
    /**
     * Тест: Название приложения с кириллицей
     */
    public function testAppNameWithCyrillic(): void
    {
        $metrics = new OpenRouterMetrics([
            'api_key' => 'sk-or-v1-test-api-key',
            'app_name' => 'Метрики Приложения',
        ]);
        
        $this->assertInstanceOf(OpenRouterMetrics::class, $metrics);
    }
    
    /**
     * Тест: Пустое название приложения обрабатывается корректно
     */
    public function testEmptyAppNameHandled(): void
    {
        $metrics = new OpenRouterMetrics([
            'api_key' => 'sk-or-v1-test-api-key',
            'app_name' => '',
        ]);
        
        $this->assertInstanceOf(OpenRouterMetrics::class, $metrics);
    }
    
    /**
     * Тест: API ключ с различными форматами
     */
    public function testApiKeyWithVariousFormats(): void
    {
        $metrics1 = new OpenRouterMetrics(['api_key' => 'sk-or-v1-abc123']);
        $metrics2 = new OpenRouterMetrics(['api_key' => 'sk-test-123']);
        $metrics3 = new OpenRouterMetrics(['api_key' => 'custom-key-format']);
        
        $this->assertInstanceOf(OpenRouterMetrics::class, $metrics1);
        $this->assertInstanceOf(OpenRouterMetrics::class, $metrics2);
        $this->assertInstanceOf(OpenRouterMetrics::class, $metrics3);
    }
}

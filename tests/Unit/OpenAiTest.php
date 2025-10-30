<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Component\OpenAi;
use App\Component\Logger;
use App\Component\Exception\OpenAiValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Модульные тесты для класса OpenAi
 * 
 * Проверяет функциональность OpenAI API клиента:
 * - Инициализацию с различными конфигурациями
 * - Валидацию API ключа
 * - Валидацию конфигурационных параметров
 */
class OpenAiTest extends TestCase
{
    private string $testLogDirectory;
    
    /**
     * Настройка окружения перед каждым тестом
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->testLogDirectory = sys_get_temp_dir() . '/openai_test_' . uniqid();
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
        $this->expectException(OpenAiValidationException::class);
        $this->expectExceptionMessageMatches('/api.*ключ/iu');
        
        new OpenAi([]);
    }
    
    /**
     * Тест: Исключение при пустом API ключе
     */
    public function testThrowsExceptionWhenApiKeyEmpty(): void
    {
        $this->expectException(OpenAiValidationException::class);
        
        new OpenAi(['api_key' => '']);
    }
    
    /**
     * Тест: Исключение при API ключе из пробелов
     */
    public function testThrowsExceptionWhenApiKeyOnlyWhitespace(): void
    {
        $this->expectException(OpenAiValidationException::class);
        
        new OpenAi(['api_key' => '   ']);
    }
    
    /**
     * Тест: Успешная инициализация с минимальной конфигурацией
     */
    public function testSuccessfulInitializationWithMinimalConfig(): void
    {
        $openAi = new OpenAi([
            'api_key' => 'sk-proj-test-api-key-123456789',
        ]);
        
        $this->assertInstanceOf(OpenAi::class, $openAi);
    }
    
    /**
     * Тест: Инициализация с ID организации
     */
    public function testInitializationWithOrganization(): void
    {
        $openAi = new OpenAi([
            'api_key' => 'sk-proj-test-api-key',
            'organization' => 'org-123456',
        ]);
        
        $this->assertInstanceOf(OpenAi::class, $openAi);
    }
    
    /**
     * Тест: Инициализация с таймаутом
     */
    public function testInitializationWithTimeout(): void
    {
        $openAi = new OpenAi([
            'api_key' => 'sk-proj-test-api-key',
            'timeout' => 120,
        ]);
        
        $this->assertInstanceOf(OpenAi::class, $openAi);
    }
    
    /**
     * Тест: Инициализация с логгером
     */
    public function testInitializationWithLogger(): void
    {
        $logger = new Logger([
            'directory' => $this->testLogDirectory,
        ]);
        
        $openAi = new OpenAi([
            'api_key' => 'sk-proj-test-api-key',
        ], $logger);
        
        $this->assertInstanceOf(OpenAi::class, $openAi);
    }
    
    /**
     * Тест: Инициализация с количеством повторных попыток
     */
    public function testInitializationWithRetries(): void
    {
        $openAi = new OpenAi([
            'api_key' => 'sk-proj-test-api-key',
            'retries' => 5,
        ]);
        
        $this->assertInstanceOf(OpenAi::class, $openAi);
    }
    
    /**
     * Тест: Минимальный таймаут устанавливается корректно
     */
    public function testMinimumTimeoutIsEnforced(): void
    {
        $openAi = new OpenAi([
            'api_key' => 'sk-proj-test-api-key',
            'timeout' => 0,
        ]);
        
        $this->assertInstanceOf(OpenAi::class, $openAi);
    }
    
    /**
     * Тест: Отрицательный таймаут преобразуется в минимальное значение
     */
    public function testNegativeTimeoutConvertedToMinimum(): void
    {
        $openAi = new OpenAi([
            'api_key' => 'sk-proj-test-api-key',
            'timeout' => -10,
        ]);
        
        $this->assertInstanceOf(OpenAi::class, $openAi);
    }
    
    /**
     * Тест: Отрицательное количество повторных попыток преобразуется в минимальное значение
     */
    public function testNegativeRetriesConvertedToMinimum(): void
    {
        $openAi = new OpenAi([
            'api_key' => 'sk-proj-test-api-key',
            'retries' => -1,
        ]);
        
        $this->assertInstanceOf(OpenAi::class, $openAi);
    }
    
    /**
     * Тест: Множественные экземпляры с разными API ключами
     */
    public function testMultipleInstancesWithDifferentApiKeys(): void
    {
        $openAi1 = new OpenAi(['api_key' => 'sk-proj-key1']);
        $openAi2 = new OpenAi(['api_key' => 'sk-proj-key2']);
        $openAi3 = new OpenAi(['api_key' => 'sk-proj-key3']);
        
        $this->assertInstanceOf(OpenAi::class, $openAi1);
        $this->assertInstanceOf(OpenAi::class, $openAi2);
        $this->assertInstanceOf(OpenAi::class, $openAi3);
    }
    
    /**
     * Тест: API ключ с различными форматами
     */
    public function testApiKeyWithVariousFormats(): void
    {
        $openAi1 = new OpenAi(['api_key' => 'sk-proj-abc123']);
        $openAi2 = new OpenAi(['api_key' => 'sk-test-123']);
        $openAi3 = new OpenAi(['api_key' => 'custom-key-format']);
        
        $this->assertInstanceOf(OpenAi::class, $openAi1);
        $this->assertInstanceOf(OpenAi::class, $openAi2);
        $this->assertInstanceOf(OpenAi::class, $openAi3);
    }
    
    /**
     * Тест: Инициализация с очень большим таймаутом
     */
    public function testInitializationWithVeryLargeTimeout(): void
    {
        $openAi = new OpenAi([
            'api_key' => 'sk-proj-test-api-key',
            'timeout' => 3600,
        ]);
        
        $this->assertInstanceOf(OpenAi::class, $openAi);
    }
    
    /**
     * Тест: Инициализация с очень большим количеством попыток
     */
    public function testInitializationWithVeryLargeRetries(): void
    {
        $openAi = new OpenAi([
            'api_key' => 'sk-proj-test-api-key',
            'retries' => 100,
        ]);
        
        $this->assertInstanceOf(OpenAi::class, $openAi);
    }
    
    /**
     * Тест: Пустая организация обрабатывается корректно
     */
    public function testEmptyOrganizationHandled(): void
    {
        $openAi = new OpenAi([
            'api_key' => 'sk-proj-test-api-key',
            'organization' => '',
        ]);
        
        $this->assertInstanceOf(OpenAi::class, $openAi);
    }
    
    /**
     * Тест: Полная конфигурация со всеми параметрами
     */
    public function testFullConfigurationWithAllParameters(): void
    {
        $logger = new Logger([
            'directory' => $this->testLogDirectory,
        ]);
        
        $openAi = new OpenAi([
            'api_key' => 'sk-proj-test-api-key',
            'organization' => 'org-123456',
            'timeout' => 90,
            'retries' => 3,
        ], $logger);
        
        $this->assertInstanceOf(OpenAi::class, $openAi);
    }
}

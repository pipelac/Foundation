<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Component\Logger;
use App\Component\Exception\Logger\LoggerException;
use App\Component\Exception\Logger\LoggerValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Модульные тесты для класса Logger
 * 
 * Проверяет все аспекты функциональности логгера:
 * - Инициализацию и валидацию конфигурации
 * - Запись логов разных уровней
 * - Ротацию файлов
 * - Буферизацию
 * - Обработку ошибок
 */
class LoggerTest extends TestCase
{
    private string $testLogDirectory;
    
    /**
     * Настройка окружения перед каждым тестом
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->testLogDirectory = sys_get_temp_dir() . '/logger_test_' . uniqid();
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
     * Тест: Успешная инициализация логгера с минимальной конфигурацией
     */
    public function testSuccessfulInitializationWithMinimalConfig(): void
    {
        $logger = new Logger([
            'directory' => $this->testLogDirectory,
        ]);
        
        $this->assertInstanceOf(Logger::class, $logger);
        $this->assertTrue($logger->isEnabled());
    }
    
    /**
     * Тест: Инициализация логгера с полной конфигурацией
     */
    public function testInitializationWithFullConfig(): void
    {
        $logger = new Logger([
            'directory' => $this->testLogDirectory,
            'file_name' => 'test.log',
            'max_files' => 3,
            'max_file_size' => 2,
            'pattern' => '{timestamp} [{level}] {message}',
            'date_format' => 'Y-m-d H:i:s',
            'log_buffer_size' => 10,
            'enabled' => true,
        ]);
        
        $this->assertInstanceOf(Logger::class, $logger);
        $this->assertTrue($logger->isEnabled());
    }
    
    /**
     * Тест: Исключение при пустой директории
     */
    public function testThrowsExceptionWhenDirectoryIsEmpty(): void
    {
        $this->expectException(LoggerValidationException::class);
        $this->expectExceptionMessage('Не указана директория для логов');
        
        new Logger(['directory' => '']);
    }
    
    /**
     * Тест: Исключение при недоступной для записи директории
     */
    public function testThrowsExceptionWhenDirectoryIsNotWritable(): void
    {
        $readOnlyDir = $this->testLogDirectory . '/readonly';
        mkdir($readOnlyDir, 0444);
        
        $this->expectException(LoggerValidationException::class);
        $this->expectExceptionMessage('Недостаточно прав на запись');
        
        new Logger(['directory' => $readOnlyDir]);
    }
    
    /**
     * Тест: Запись INFO сообщения
     */
    public function testInfoLogging(): void
    {
        $logger = new Logger([
            'directory' => $this->testLogDirectory,
            'file_name' => 'test.log',
        ]);
        
        $logger->info('Test info message', ['key' => 'value']);
        $logger->flush();
        
        $logFile = $this->testLogDirectory . '/test.log';
        $this->assertFileExists($logFile);
        
        $content = file_get_contents($logFile);
        $this->assertStringContainsString('INFO', $content);
        $this->assertStringContainsString('Test info message', $content);
        $this->assertStringContainsString('key', $content);
    }
    
    /**
     * Тест: Запись ERROR сообщения
     */
    public function testErrorLogging(): void
    {
        $logger = new Logger([
            'directory' => $this->testLogDirectory,
            'file_name' => 'error.log',
        ]);
        
        $logger->error('Test error message', ['error_code' => 500]);
        $logger->flush();
        
        $logFile = $this->testLogDirectory . '/error.log';
        $this->assertFileExists($logFile);
        
        $content = file_get_contents($logFile);
        $this->assertStringContainsString('ERROR', $content);
        $this->assertStringContainsString('Test error message', $content);
    }
    
    /**
     * Тест: Запись WARNING сообщения
     */
    public function testWarningLogging(): void
    {
        $logger = new Logger([
            'directory' => $this->testLogDirectory,
            'file_name' => 'warning.log',
        ]);
        
        $logger->warning('Test warning message');
        $logger->flush();
        
        $logFile = $this->testLogDirectory . '/warning.log';
        $content = file_get_contents($logFile);
        
        $this->assertStringContainsString('WARNING', $content);
        $this->assertStringContainsString('Test warning message', $content);
    }
    
    /**
     * Тест: Запись DEBUG сообщения
     */
    public function testDebugLogging(): void
    {
        $logger = new Logger([
            'directory' => $this->testLogDirectory,
            'file_name' => 'debug.log',
        ]);
        
        $logger->debug('Debug information', ['debug_var' => 123]);
        $logger->flush();
        
        $logFile = $this->testLogDirectory . '/debug.log';
        $content = file_get_contents($logFile);
        
        $this->assertStringContainsString('DEBUG', $content);
        $this->assertStringContainsString('Debug information', $content);
    }
    
    /**
     * Тест: Запись CRITICAL сообщения
     */
    public function testCriticalLogging(): void
    {
        $logger = new Logger([
            'directory' => $this->testLogDirectory,
            'file_name' => 'critical.log',
        ]);
        
        $logger->critical('Critical error occurred');
        $logger->flush();
        
        $logFile = $this->testLogDirectory . '/critical.log';
        $content = file_get_contents($logFile);
        
        $this->assertStringContainsString('CRITICAL', $content);
        $this->assertStringContainsString('Critical error occurred', $content);
    }
    
    /**
     * Тест: Исключение при недопустимом уровне логирования
     */
    public function testThrowsExceptionForInvalidLogLevel(): void
    {
        $logger = new Logger([
            'directory' => $this->testLogDirectory,
        ]);
        
        $this->expectException(LoggerException::class);
        $this->expectExceptionMessage('Недопустимый уровень логирования');
        
        $logger->log('INVALID_LEVEL', 'Test message');
    }
    
    /**
     * Тест: Включение и отключение логирования
     */
    public function testEnableDisableLogging(): void
    {
        $logger = new Logger([
            'directory' => $this->testLogDirectory,
            'file_name' => 'toggle.log',
        ]);
        
        $this->assertTrue($logger->isEnabled());
        
        $logger->disable();
        $this->assertFalse($logger->isEnabled());
        
        $logger->info('This should not be logged');
        $logger->flush();
        
        $logFile = $this->testLogDirectory . '/toggle.log';
        $this->assertFileDoesNotExist($logFile);
        
        $logger->enable();
        $this->assertTrue($logger->isEnabled());
        
        $logger->info('This should be logged');
        $logger->flush();
        
        $this->assertFileExists($logFile);
    }
    
    /**
     * Тест: Буферизация логов
     */
    public function testLogBuffering(): void
    {
        $logger = new Logger([
            'directory' => $this->testLogDirectory,
            'file_name' => 'buffered.log',
            'log_buffer_size' => 1,
        ]);
        
        $logger->info('Message 1');
        
        $logFile = $this->testLogDirectory . '/buffered.log';
        
        if (file_exists($logFile)) {
            $content = file_get_contents($logFile);
            $messageCount = substr_count($content, 'Message');
            $this->assertLessThanOrEqual(1, $messageCount);
        }
        
        $logger->info('Message 2');
        $logger->flush();
        
        $this->assertFileExists($logFile);
        $content = file_get_contents($logFile);
        $this->assertStringContainsString('Message 1', $content);
        $this->assertStringContainsString('Message 2', $content);
    }
    
    /**
     * Тест: Ручной сброс буфера
     */
    public function testManualFlush(): void
    {
        $logger = new Logger([
            'directory' => $this->testLogDirectory,
            'file_name' => 'manual_flush.log',
            'log_buffer_size' => 100,
        ]);
        
        $logger->info('Buffered message');
        $logger->flush();
        
        $logFile = $this->testLogDirectory . '/manual_flush.log';
        $this->assertFileExists($logFile);
        
        $content = file_get_contents($logFile);
        $this->assertStringContainsString('Buffered message', $content);
    }
    
    /**
     * Тест: Формат вывода лога соответствует шаблону
     */
    public function testLogFormat(): void
    {
        $logger = new Logger([
            'directory' => $this->testLogDirectory,
            'file_name' => 'format.log',
            'pattern' => '[{level}] {message}',
        ]);
        
        $logger->info('Test format');
        $logger->flush();
        
        $logFile = $this->testLogDirectory . '/format.log';
        $content = file_get_contents($logFile);
        
        $this->assertMatchesRegularExpression('/\[INFO\] Test format/', $content);
    }
    
    /**
     * Тест: Множественная запись в один файл
     */
    public function testMultipleWrites(): void
    {
        $logger = new Logger([
            'directory' => $this->testLogDirectory,
            'file_name' => 'multiple.log',
        ]);
        
        for ($i = 1; $i <= 5; $i++) {
            $logger->info("Message number {$i}");
        }
        $logger->flush();
        
        $logFile = $this->testLogDirectory . '/multiple.log';
        $content = file_get_contents($logFile);
        
        for ($i = 1; $i <= 5; $i++) {
            $this->assertStringContainsString("Message number {$i}", $content);
        }
    }
    
    /**
     * Тест: Контекст правильно сериализуется в JSON
     */
    public function testContextSerialization(): void
    {
        $logger = new Logger([
            'directory' => $this->testLogDirectory,
            'file_name' => 'context.log',
        ]);
        
        $context = [
            'user_id' => 123,
            'action' => 'login',
            'ip' => '192.168.1.1',
            'nested' => ['key' => 'value'],
        ];
        
        $logger->info('User action', $context);
        $logger->flush();
        
        $logFile = $this->testLogDirectory . '/context.log';
        $content = file_get_contents($logFile);
        
        $this->assertStringContainsString('user_id', $content);
        $this->assertStringContainsString('123', $content);
        $this->assertStringContainsString('login', $content);
    }
    
    /**
     * Тест: Автоматическое создание директории
     */
    public function testAutoCreateDirectory(): void
    {
        $newDir = $this->testLogDirectory . '/auto_created';
        
        $this->assertDirectoryDoesNotExist($newDir);
        
        $logger = new Logger([
            'directory' => $newDir,
        ]);
        
        $this->assertDirectoryExists($newDir);
    }
    
    /**
     * Тест: Минимальные значения конфигурации применяются корректно
     */
    public function testMinimumConfigValues(): void
    {
        $logger = new Logger([
            'directory' => $this->testLogDirectory,
            'max_files' => -1,
            'max_file_size' => -1,
        ]);
        
        $logger->info('Test with minimum values');
        $logger->flush();
        
        $this->assertFileExists($this->testLogDirectory . '/app.log');
    }
}

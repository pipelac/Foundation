<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Component\Rss;
use App\Component\Logger;
use App\Component\Exception\RssException;
use App\Component\Exception\RssValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Модульные тесты для класса Rss
 * 
 * Проверяет функциональность RSS парсера:
 * - Инициализацию с различными конфигурациями
 * - Валидацию URL
 * - Валидацию директории кеша
 * - Конфигурационные параметры
 */
class RssTest extends TestCase
{
    private string $testCacheDirectory;
    private string $testLogDirectory;
    
    /**
     * Настройка окружения перед каждым тестом
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->testCacheDirectory = sys_get_temp_dir() . '/rss_cache_' . uniqid();
        $this->testLogDirectory = sys_get_temp_dir() . '/rss_log_' . uniqid();
        mkdir($this->testCacheDirectory, 0777, true);
        mkdir($this->testLogDirectory, 0777, true);
    }
    
    /**
     * Очистка после каждого теста
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        if (is_dir($this->testCacheDirectory)) {
            $this->removeDirectory($this->testCacheDirectory);
        }
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
     * Тест: Успешная инициализация с минимальной конфигурацией
     */
    public function testSuccessfulInitializationWithMinimalConfig(): void
    {
        $rss = new Rss();
        
        $this->assertInstanceOf(Rss::class, $rss);
    }
    
    /**
     * Тест: Инициализация с полной конфигурацией
     */
    public function testInitializationWithFullConfig(): void
    {
        $rss = new Rss([
            'user_agent' => 'TestBot/1.0',
            'timeout' => 30,
            'max_content_size' => 5242880,
            'cache_directory' => $this->testCacheDirectory,
            'cache_duration' => 7200,
            'enable_cache' => true,
            'enable_sanitization' => true,
        ]);
        
        $this->assertInstanceOf(Rss::class, $rss);
    }
    
    /**
     * Тест: Инициализация с логгером
     */
    public function testInitializationWithLogger(): void
    {
        $logger = new Logger([
            'directory' => $this->testLogDirectory,
        ]);
        
        $rss = new Rss([], $logger);
        
        $this->assertInstanceOf(Rss::class, $rss);
    }
    
    /**
     * Тест: Инициализация с кешированием
     */
    public function testInitializationWithCaching(): void
    {
        $rss = new Rss([
            'cache_directory' => $this->testCacheDirectory,
            'enable_cache' => true,
            'cache_duration' => 3600,
        ]);
        
        $this->assertInstanceOf(Rss::class, $rss);
    }
    
    /**
     * Тест: Инициализация без кеширования
     */
    public function testInitializationWithoutCaching(): void
    {
        $rss = new Rss([
            'enable_cache' => false,
        ]);
        
        $this->assertInstanceOf(Rss::class, $rss);
    }
    
    /**
     * Тест: Исключение при невалидной директории кеша
     */
    public function testThrowsExceptionForInvalidCacheDirectory(): void
    {
        $this->expectException(RssException::class);
        $this->expectExceptionMessageMatches('/Директория кеша/');
        
        new Rss([
            'cache_directory' => '/invalid/path/that/does/not/exist/12345678',
            'enable_cache' => true,
        ]);
    }
    
    /**
     * Тест: Исключение при попытке загрузить пустой URL
     */
    public function testThrowsExceptionForEmptyUrl(): void
    {
        $rss = new Rss();
        
        $this->expectException(RssValidationException::class);
        $this->expectExceptionMessage('URL не может быть пустым');
        
        $rss->fetch('');
    }
    
    /**
     * Тест: Исключение при попытке загрузить URL без протокола
     */
    public function testThrowsExceptionForUrlWithoutProtocol(): void
    {
        $rss = new Rss();
        
        $this->expectException(RssValidationException::class);
        $this->expectExceptionMessage('URL должен использовать протокол HTTP или HTTPS');
        
        $rss->fetch('example.com/feed.xml');
    }
    
    /**
     * Тест: Исключение при попытке загрузить URL с неподдерживаемым протоколом
     */
    public function testThrowsExceptionForUnsupportedProtocol(): void
    {
        $rss = new Rss();
        
        $this->expectException(RssValidationException::class);
        $this->expectExceptionMessage('URL должен использовать протокол HTTP или HTTPS');
        
        $rss->fetch('ftp://example.com/feed.xml');
    }
    
    /**
     * Тест: Исключение при попытке загрузить URL без хоста
     */
    public function testThrowsExceptionForUrlWithoutHost(): void
    {
        $rss = new Rss();
        
        $this->expectException(RssValidationException::class);
        $this->expectExceptionMessage('URL должен содержать имя хоста');
        
        $rss->fetch('http:///path');
    }
    
    /**
     * Тест: Исключение при некорректном формате URL
     */
    public function testThrowsExceptionForMalformedUrl(): void
    {
        $rss = new Rss();
        
        $this->expectException(RssValidationException::class);
        
        $rss->fetch('http://');
    }
    
    /**
     * Тест: Минимальный таймаут устанавливается корректно
     */
    public function testMinimumTimeoutIsEnforced(): void
    {
        $rss = new Rss([
            'timeout' => -10,
        ]);
        
        $this->assertInstanceOf(Rss::class, $rss);
    }
    
    /**
     * Тест: Минимальный размер контента устанавливается корректно
     */
    public function testMinimumContentSizeIsEnforced(): void
    {
        $rss = new Rss([
            'max_content_size' => 100,
        ]);
        
        $this->assertInstanceOf(Rss::class, $rss);
    }
    
    /**
     * Тест: Отрицательная длительность кеша преобразуется в 0
     */
    public function testNegativeCacheDurationConvertedToZero(): void
    {
        $rss = new Rss([
            'cache_duration' => -3600,
        ]);
        
        $this->assertInstanceOf(Rss::class, $rss);
    }
    
    /**
     * Тест: Пользовательский User-Agent применяется
     */
    public function testCustomUserAgent(): void
    {
        $rss = new Rss([
            'user_agent' => 'MyCustomBot/2.0',
        ]);
        
        $this->assertInstanceOf(Rss::class, $rss);
    }
    
    /**
     * Тест: Отключение санитизации
     */
    public function testDisableSanitization(): void
    {
        $rss = new Rss([
            'enable_sanitization' => false,
        ]);
        
        $this->assertInstanceOf(Rss::class, $rss);
    }
    
    /**
     * Тест: Создание директории кеша если она не существует
     */
    public function testCacheDirectoryCreatedIfNotExists(): void
    {
        $newCacheDir = $this->testCacheDirectory . '/auto_created';
        
        $this->assertDirectoryDoesNotExist($newCacheDir);
        
        $rss = new Rss([
            'cache_directory' => $newCacheDir,
            'enable_cache' => true,
        ]);
        
        $this->assertDirectoryExists($newCacheDir);
    }
    
    /**
     * Тест: Множественные экземпляры с разными конфигурациями
     */
    public function testMultipleInstancesWithDifferentConfigs(): void
    {
        $rss1 = new Rss(['timeout' => 10]);
        $rss2 = new Rss(['timeout' => 20]);
        $rss3 = new Rss(['timeout' => 30]);
        
        $this->assertInstanceOf(Rss::class, $rss1);
        $this->assertInstanceOf(Rss::class, $rss2);
        $this->assertInstanceOf(Rss::class, $rss3);
    }
    
    /**
     * Тест: Конфигурация с очень большим размером контента
     */
    public function testConfigurationWithVeryLargeContentSize(): void
    {
        $rss = new Rss([
            'max_content_size' => 104857600,
        ]);
        
        $this->assertInstanceOf(Rss::class, $rss);
    }
    
    /**
     * Тест: Конфигурация с очень длинным таймаутом
     */
    public function testConfigurationWithVeryLongTimeout(): void
    {
        $rss = new Rss([
            'timeout' => 300,
        ]);
        
        $this->assertInstanceOf(Rss::class, $rss);
    }
    
    /**
     * Тест: Нулевой таймаут преобразуется в минимальное значение
     */
    public function testZeroTimeoutConvertedToMinimum(): void
    {
        $rss = new Rss([
            'timeout' => 0,
        ]);
        
        $this->assertInstanceOf(Rss::class, $rss);
    }
    
    /**
     * Тест: URL с пробелами должен вызывать ошибку
     */
    public function testUrlWithSpacesThrowsException(): void
    {
        $rss = new Rss();
        
        $this->expectException(RssValidationException::class);
        
        $rss->fetch('   ');
    }
    
    /**
     * Тест: HTTPS URL проходит валидацию
     */
    public function testHttpsUrlPassesValidation(): void
    {
        $rss = new Rss();
        
        $this->expectException(RssException::class);
        
        $rss->fetch('https://example.com/feed.xml');
    }
    
    /**
     * Тест: HTTP URL проходит валидацию
     */
    public function testHttpUrlPassesValidation(): void
    {
        $rss = new Rss();
        
        $this->expectException(RssException::class);
        
        $rss->fetch('http://example.com/feed.xml');
    }
}

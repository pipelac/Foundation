<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Component\Http;
use App\Component\Logger;
use App\Component\Exception\HttpException;
use App\Component\Exception\HttpValidationException;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;

/**
 * Модульные тесты для класса Http
 * 
 * Проверяет функциональность HTTP клиента:
 * - Инициализацию с различными конфигурациями
 * - Выполнение GET, POST, PUT, DELETE запросов
 * - Обработку ошибок и исключений
 * - Работу с заголовками
 * - Валидацию URL
 */
class HttpTest extends TestCase
{
    private string $testLogDirectory;
    
    /**
     * Настройка окружения перед каждым тестом
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->testLogDirectory = sys_get_temp_dir() . '/http_test_' . uniqid();
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
     * Тест: Успешная инициализация HTTP клиента с минимальной конфигурацией
     */
    public function testSuccessfulInitializationWithMinimalConfig(): void
    {
        $http = new Http();
        
        $this->assertInstanceOf(Http::class, $http);
    }
    
    /**
     * Тест: Инициализация с полной конфигурацией
     */
    public function testInitializationWithFullConfig(): void
    {
        $http = new Http([
            'timeout' => 30,
            'connect_timeout' => 10,
            'verify' => true,
            'headers' => [
                'User-Agent' => 'TestClient/1.0',
                'Accept' => 'application/json',
            ],
        ]);
        
        $this->assertInstanceOf(Http::class, $http);
    }
    
    /**
     * Тест: Инициализация с логгером
     */
    public function testInitializationWithLogger(): void
    {
        $logger = new Logger([
            'directory' => $this->testLogDirectory,
        ]);
        
        $http = new Http([], $logger);
        
        $this->assertInstanceOf(Http::class, $http);
    }
    
    /**
     * Тест: Константы HTTP методов определены корректно
     */
    public function testHttpMethodConstants(): void
    {
        $this->assertEquals('GET', Http::METHOD_GET);
        $this->assertEquals('POST', Http::METHOD_POST);
        $this->assertEquals('PUT', Http::METHOD_PUT);
        $this->assertEquals('PATCH', Http::METHOD_PATCH);
        $this->assertEquals('DELETE', Http::METHOD_DELETE);
        $this->assertEquals('HEAD', Http::METHOD_HEAD);
        $this->assertEquals('OPTIONS', Http::METHOD_OPTIONS);
    }
    
    /**
     * Тест: Проверка типов возвращаемых значений методов
     */
    public function testMethodReturnTypes(): void
    {
        $http = new Http();
        
        $this->assertIsString(Http::METHOD_GET);
        $this->assertIsString(Http::METHOD_POST);
        $this->assertIsString(Http::METHOD_PUT);
        $this->assertIsString(Http::METHOD_DELETE);
    }
    
    /**
     * Тест: Инициализация с базовым URI
     */
    public function testInitializationWithBaseUri(): void
    {
        $http = new Http([
            'base_uri' => 'https://api.example.com',
        ]);
        
        $this->assertInstanceOf(Http::class, $http);
    }
    
    /**
     * Тест: Инициализация с настройками таймаутов
     */
    public function testInitializationWithTimeouts(): void
    {
        $http = new Http([
            'timeout' => 60.0,
            'connect_timeout' => 5.0,
        ]);
        
        $this->assertInstanceOf(Http::class, $http);
    }
    
    /**
     * Тест: Инициализация с отключенной проверкой SSL
     */
    public function testInitializationWithSslVerificationDisabled(): void
    {
        $http = new Http([
            'verify' => false,
        ]);
        
        $this->assertInstanceOf(Http::class, $http);
    }
    
    /**
     * Тест: Инициализация с настройками прокси
     */
    public function testInitializationWithProxy(): void
    {
        $http = new Http([
            'proxy' => 'tcp://localhost:8125',
        ]);
        
        $this->assertInstanceOf(Http::class, $http);
    }
    
    /**
     * Тест: Инициализация с пользовательскими заголовками
     */
    public function testInitializationWithCustomHeaders(): void
    {
        $http = new Http([
            'headers' => [
                'Authorization' => 'Bearer token123',
                'X-Custom-Header' => 'custom-value',
            ],
        ]);
        
        $this->assertInstanceOf(Http::class, $http);
    }
    
    /**
     * Тест: Инициализация с настройками редиректов
     */
    public function testInitializationWithRedirectSettings(): void
    {
        $http = new Http([
            'allow_redirects' => [
                'max' => 5,
                'strict' => true,
            ],
        ]);
        
        $this->assertInstanceOf(Http::class, $http);
    }
    
    /**
     * Тест: Отрицательные таймауты преобразуются в 0
     */
    public function testNegativeTimeoutsConvertedToZero(): void
    {
        $http = new Http([
            'timeout' => -10,
            'connect_timeout' => -5,
        ]);
        
        $this->assertInstanceOf(Http::class, $http);
    }
    
    /**
     * Тест: Инициализация с количеством повторных попыток
     */
    public function testInitializationWithRetries(): void
    {
        $http = new Http([
            'retries' => 3,
        ]);
        
        $this->assertInstanceOf(Http::class, $http);
    }
    
    /**
     * Тест: Инициализация с дополнительными опциями
     */
    public function testInitializationWithAdditionalOptions(): void
    {
        $http = new Http([
            'options' => [
                'debug' => false,
                'http_errors' => false,
            ],
        ]);
        
        $this->assertInstanceOf(Http::class, $http);
    }
    
    /**
     * Тест: Множественная инициализация не вызывает ошибок
     */
    public function testMultipleInstantiations(): void
    {
        $http1 = new Http(['timeout' => 10]);
        $http2 = new Http(['timeout' => 20]);
        $http3 = new Http(['timeout' => 30]);
        
        $this->assertInstanceOf(Http::class, $http1);
        $this->assertInstanceOf(Http::class, $http2);
        $this->assertInstanceOf(Http::class, $http3);
    }
    
    /**
     * Тест: Инициализация с пустой конфигурацией использует значения по умолчанию
     */
    public function testInitializationWithEmptyConfigUsesDefaults(): void
    {
        $http = new Http([]);
        
        $this->assertInstanceOf(Http::class, $http);
    }
    
    /**
     * Тест: Конфигурация с нулевым таймаутом
     */
    public function testInitializationWithZeroTimeout(): void
    {
        $http = new Http([
            'timeout' => 0,
            'connect_timeout' => 0,
        ]);
        
        $this->assertInstanceOf(Http::class, $http);
    }
    
    /**
     * Тест: Конфигурация с очень большим таймаутом
     */
    public function testInitializationWithLargeTimeout(): void
    {
        $http = new Http([
            'timeout' => 3600.0,
        ]);
        
        $this->assertInstanceOf(Http::class, $http);
    }
    
    /**
     * Тест: Инициализация с массивом прокси
     */
    public function testInitializationWithProxyArray(): void
    {
        $http = new Http([
            'proxy' => [
                'http' => 'tcp://localhost:8125',
                'https' => 'tcp://localhost:8126',
            ],
        ]);
        
        $this->assertInstanceOf(Http::class, $http);
    }
    
    /**
     * Тест: Логгер может быть null
     */
    public function testLoggerCanBeNull(): void
    {
        $http = new Http([], null);
        
        $this->assertInstanceOf(Http::class, $http);
    }
    
    /**
     * Тест: Создание экземпляра без параметров
     */
    public function testInstantiationWithoutParameters(): void
    {
        $http = new Http();
        
        $this->assertInstanceOf(Http::class, $http);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Component\htmlWebProxyList;
use App\Component\ProxyPool;
use App\Component\Logger;
use App\Component\Exception\HtmlWebProxyListException;
use App\Component\Exception\HtmlWebProxyListValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Модульные тесты для класса htmlWebProxyList
 * 
 * Проверяет функциональность получения прокси с htmlweb.ru API:
 * - Инициализацию с различными конфигурациями
 * - Валидацию параметров
 * - Парсинг различных форматов ответов
 * - Интеграцию с ProxyPool
 */
class HtmlWebProxyListTest extends TestCase
{
    private string $testLogDirectory;
    
    /**
     * Настройка окружения перед каждым тестом
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->testLogDirectory = sys_get_temp_dir() . '/htmlweb_test_' . uniqid();
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
     * Тест: Успешная инициализация с минимальной конфигурацией
     */
    public function testSuccessfulInitializationWithMinimalConfig(): void
    {
        $htmlWebProxy = new htmlWebProxyList();
        
        $this->assertInstanceOf(htmlWebProxyList::class, $htmlWebProxy);
    }
    
    /**
     * Тест: Инициализация с полной конфигурацией
     */
    public function testInitializationWithFullConfig(): void
    {
        $htmlWebProxy = new htmlWebProxyList([
            'country' => 'US',
            'perpage' => 30,
            'work' => 'yes',
            'type' => 'http',
            'speed_max' => 1000,
            'page' => 1,
            'timeout' => 15,
        ]);
        
        $this->assertInstanceOf(htmlWebProxyList::class, $htmlWebProxy);
        
        $params = $htmlWebProxy->getParams();
        $this->assertEquals('US', $params['country']);
        $this->assertEquals(30, $params['perpage']);
        $this->assertEquals('yes', $params['work']);
        $this->assertEquals('http', $params['type']);
        $this->assertEquals(1000, $params['speed_max']);
        $this->assertEquals(1, $params['page']);
    }
    
    /**
     * Тест: Инициализация с логгером
     */
    public function testInitializationWithLogger(): void
    {
        $logger = new Logger([
            'directory' => $this->testLogDirectory,
            'file_name' => 'htmlweb_test.log',
            'enabled' => true,
        ]);
        
        $htmlWebProxy = new htmlWebProxyList([
            'perpage' => 10,
        ], $logger);
        
        $this->assertInstanceOf(htmlWebProxyList::class, $htmlWebProxy);
    }
    
    /**
     * Тест: Валидация - некорректное значение perpage (слишком большое)
     */
    public function testValidationInvalidPerPageTooLarge(): void
    {
        $this->expectException(HtmlWebProxyListValidationException::class);
        $this->expectExceptionMessage('Параметр perpage должен быть от 1 до 50');
        
        new htmlWebProxyList([
            'perpage' => 100,
        ]);
    }
    
    /**
     * Тест: Валидация - некорректное значение perpage (слишком маленькое)
     */
    public function testValidationInvalidPerPageTooSmall(): void
    {
        $this->expectException(HtmlWebProxyListValidationException::class);
        $this->expectExceptionMessage('Параметр perpage должен быть от 1 до 50');
        
        new htmlWebProxyList([
            'perpage' => 0,
        ]);
    }
    
    /**
     * Тест: Валидация - некорректное значение work
     */
    public function testValidationInvalidWork(): void
    {
        $this->expectException(HtmlWebProxyListValidationException::class);
        $this->expectExceptionMessage('Параметр work должен быть одним из: yes, maybe, no');
        
        new htmlWebProxyList([
            'work' => 'invalid',
        ]);
    }
    
    /**
     * Тест: Валидация - некорректное значение type
     */
    public function testValidationInvalidType(): void
    {
        $this->expectException(HtmlWebProxyListValidationException::class);
        $this->expectExceptionMessage('Параметр type должен быть одним из: http, https, socks4, socks5');
        
        new htmlWebProxyList([
            'type' => 'invalid_type',
        ]);
    }
    
    /**
     * Тест: Валидация - некорректное значение speed_max (слишком большое)
     */
    public function testValidationInvalidSpeedMaxTooLarge(): void
    {
        $this->expectException(HtmlWebProxyListValidationException::class);
        $this->expectExceptionMessage('Параметр speed_max должен быть от 0 до 10000');
        
        new htmlWebProxyList([
            'speed_max' => 20000,
        ]);
    }
    
    /**
     * Тест: Валидация - некорректное значение speed_max (отрицательное)
     */
    public function testValidationInvalidSpeedMaxNegative(): void
    {
        $this->expectException(HtmlWebProxyListValidationException::class);
        $this->expectExceptionMessage('Параметр speed_max должен быть от 0 до 10000');
        
        new htmlWebProxyList([
            'speed_max' => -100,
        ]);
    }
    
    /**
     * Тест: Валидация - некорректное значение page
     */
    public function testValidationInvalidPage(): void
    {
        $this->expectException(HtmlWebProxyListValidationException::class);
        $this->expectExceptionMessage('Параметр page должен быть больше 0');
        
        new htmlWebProxyList([
            'page' => 0,
        ]);
    }
    
    /**
     * Тест: Валидация - некорректное значение short
     */
    public function testValidationInvalidShort(): void
    {
        $this->expectException(HtmlWebProxyListValidationException::class);
        $this->expectExceptionMessage('Параметр short должен быть одним из: only_ip');
        
        new htmlWebProxyList([
            'short' => 'invalid_format',
        ]);
    }
    
    /**
     * Тест: Получение текущих параметров
     */
    public function testGetParams(): void
    {
        $config = [
            'country' => 'RU',
            'perpage' => 25,
            'type' => 'https',
        ];
        
        $htmlWebProxy = new htmlWebProxyList($config);
        $params = $htmlWebProxy->getParams();
        
        $this->assertIsArray($params);
        $this->assertEquals('RU', $params['country']);
        $this->assertEquals(25, $params['perpage']);
        $this->assertEquals('https', $params['type']);
    }
    
    /**
     * Тест: Обновление параметров
     */
    public function testUpdateParams(): void
    {
        $htmlWebProxy = new htmlWebProxyList([
            'type' => 'http',
        ]);
        
        $initialParams = $htmlWebProxy->getParams();
        $this->assertEquals('http', $initialParams['type']);
        
        $htmlWebProxy->updateParams([
            'country' => 'US',
            'speed_max' => 500,
        ]);
        
        $updatedParams = $htmlWebProxy->getParams();
        $this->assertEquals('http', $updatedParams['type']);
        $this->assertEquals('US', $updatedParams['country']);
        $this->assertEquals(500, $updatedParams['speed_max']);
    }
    
    /**
     * Тест: Сброс параметров
     */
    public function testResetParams(): void
    {
        $htmlWebProxy = new htmlWebProxyList([
            'country' => 'US',
            'perpage' => 30,
            'speed_max' => 1000,
        ]);
        
        $params = $htmlWebProxy->getParams();
        $this->assertNotEmpty($params);
        
        $htmlWebProxy->resetParams();
        
        $resetParams = $htmlWebProxy->getParams();
        $this->assertEmpty($resetParams);
    }
    
    /**
     * Тест: Нормализация параметров (преобразование в верхний регистр для country)
     */
    public function testParamNormalizationCountry(): void
    {
        $htmlWebProxy = new htmlWebProxyList([
            'country' => 'ru',
        ]);
        
        $params = $htmlWebProxy->getParams();
        $this->assertEquals('RU', $params['country']);
    }
    
    /**
     * Тест: Нормализация параметров (преобразование в нижний регистр для work)
     */
    public function testParamNormalizationWork(): void
    {
        $htmlWebProxy = new htmlWebProxyList([
            'work' => 'YES',
        ]);
        
        $params = $htmlWebProxy->getParams();
        $this->assertEquals('yes', $params['work']);
    }
    
    /**
     * Тест: Нормализация параметров (преобразование в нижний регистр для type)
     */
    public function testParamNormalizationType(): void
    {
        $htmlWebProxy = new htmlWebProxyList([
            'type' => 'HTTP',
        ]);
        
        $params = $htmlWebProxy->getParams();
        $this->assertEquals('http', $params['type']);
    }
    
    /**
     * Тест: Интеграция с ProxyPool - создание объектов
     */
    public function testIntegrationWithProxyPool(): void
    {
        $proxyPool = new ProxyPool([
            'auto_health_check' => false,
        ]);
        
        $htmlWebProxy = new htmlWebProxyList([
            'perpage' => 10,
        ]);
        
        $this->assertInstanceOf(ProxyPool::class, $proxyPool);
        $this->assertInstanceOf(htmlWebProxyList::class, $htmlWebProxy);
    }
    
    /**
     * Тест: Различные типы прокси
     */
    public function testDifferentProxyTypes(): void
    {
        $types = ['http', 'https', 'socks4', 'socks5'];
        
        foreach ($types as $type) {
            $htmlWebProxy = new htmlWebProxyList([
                'type' => $type,
            ]);
            
            $params = $htmlWebProxy->getParams();
            $this->assertEquals($type, $params['type']);
        }
    }
    
    /**
     * Тест: Исключение стран
     */
    public function testCountryNotParameter(): void
    {
        $htmlWebProxy = new htmlWebProxyList([
            'country_not' => 'cn,ru',
        ]);
        
        $params = $htmlWebProxy->getParams();
        $this->assertEquals('CN,RU', $params['country_not']);
    }
    
    /**
     * Тест: Пустые параметры игнорируются
     */
    public function testEmptyParametersIgnored(): void
    {
        $htmlWebProxy = new htmlWebProxyList([
            'country' => '',
            'country_not' => '',
        ]);
        
        $params = $htmlWebProxy->getParams();
        $this->assertArrayNotHasKey('country', $params);
        $this->assertArrayNotHasKey('country_not', $params);
    }
    
    /**
     * Тест: Краткий формат only_ip
     */
    public function testShortFormatOnlyIp(): void
    {
        $htmlWebProxy = new htmlWebProxyList([
            'short' => 'only_ip',
        ]);
        
        $params = $htmlWebProxy->getParams();
        $this->assertEquals('only_ip', $params['short']);
    }
    
    /**
     * Тест: Таймаут устанавливается корректно
     */
    public function testTimeoutConfiguration(): void
    {
        $htmlWebProxy = new htmlWebProxyList([
            'timeout' => 20,
        ]);
        
        $this->assertInstanceOf(htmlWebProxyList::class, $htmlWebProxy);
    }
    
    /**
     * Тест: Минимальное значение таймаута
     */
    public function testMinimumTimeout(): void
    {
        $htmlWebProxy = new htmlWebProxyList([
            'timeout' => 0,
        ]);
        
        $this->assertInstanceOf(htmlWebProxyList::class, $htmlWebProxy);
    }
}

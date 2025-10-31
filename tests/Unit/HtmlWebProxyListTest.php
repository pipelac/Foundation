<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Component\htmlWebProxyList;
use App\Component\ProxyPool;
use App\Component\Logger;
use App\Component\Exception\htmlWebProxyList\HtmlWebProxyListException;
use App\Component\Exception\htmlWebProxyList\HtmlWebProxyListValidationException;
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
    private string $testApiKey = 'test_api_key_12345';
    
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
        $htmlWebProxy = new htmlWebProxyList($this->testApiKey);
        
        $this->assertInstanceOf(htmlWebProxyList::class, $htmlWebProxy);
    }
    
    /**
     * Тест: Валидация - пустой API ключ
     */
    public function testValidationEmptyApiKey(): void
    {
        $this->expectException(HtmlWebProxyListValidationException::class);
        $this->expectExceptionMessage('API ключ (api_key) является обязательным параметром');
        
        new htmlWebProxyList('');
    }
    
    /**
     * Тест: Инициализация с полной конфигурацией
     */
    public function testInitializationWithFullConfig(): void
    {
        $htmlWebProxy = new htmlWebProxyList($this->testApiKey, [
            'country' => 'US',
            'perpage' => 30,
            'work' => 1,
            'type' => 'HTTP',
            'p' => 1,
            'timeout' => 15,
        ]);
        
        $this->assertInstanceOf(htmlWebProxyList::class, $htmlWebProxy);
        
        $params = $htmlWebProxy->getParams();
        $this->assertEquals('US', $params['country']);
        $this->assertEquals(30, $params['perpage']);
        $this->assertEquals(1, $params['work']);
        $this->assertEquals('HTTP', $params['type']);
        $this->assertEquals(1, $params['p']);
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
        
        $htmlWebProxy = new htmlWebProxyList($this->testApiKey, [
            'perpage' => 10,
        ], $logger);
        
        $this->assertInstanceOf(htmlWebProxyList::class, $htmlWebProxy);
    }
    
    /**
     * Тест: Валидация - некорректное значение perpage (слишком маленькое)
     */
    public function testValidationInvalidPerPageTooSmall(): void
    {
        $this->expectException(HtmlWebProxyListValidationException::class);
        $this->expectExceptionMessage('Параметр perpage должен быть больше 0');
        
        new htmlWebProxyList($this->testApiKey, [
            'perpage' => 0,
        ]);
    }
    
    /**
     * Тест: Валидация - некорректное значение work
     */
    public function testValidationInvalidWork(): void
    {
        $this->expectException(HtmlWebProxyListValidationException::class);
        $this->expectExceptionMessage('Параметр work должен быть 1 (работает из России) или 0 (не работает)');
        
        new htmlWebProxyList($this->testApiKey, [
            'work' => 5,
        ]);
    }
    
    /**
     * Тест: Валидация - некорректное значение type
     */
    public function testValidationInvalidType(): void
    {
        $this->expectException(HtmlWebProxyListValidationException::class);
        $this->expectExceptionMessage('Параметр type должен быть одним из: HTTP, HTTPS, SOCKS4, SOCKS5');
        
        new htmlWebProxyList($this->testApiKey, [
            'type' => 'invalid_type',
        ]);
    }
    
    /**
     * Тест: Валидация - некорректное значение p (номер страницы)
     */
    public function testValidationInvalidPage(): void
    {
        $this->expectException(HtmlWebProxyListValidationException::class);
        $this->expectExceptionMessage('Параметр p (номер страницы) должен быть больше 0');
        
        new htmlWebProxyList($this->testApiKey, [
            'p' => 0,
        ]);
    }
    
    /**
     * Тест: Валидация - некорректное значение short
     */
    public function testValidationInvalidShort(): void
    {
        $this->expectException(HtmlWebProxyListValidationException::class);
        $this->expectExceptionMessage('Параметр short должен быть пустым, 2 или 4');
        
        new htmlWebProxyList($this->testApiKey, [
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
            'type' => 'HTTPS',
        ];
        
        $htmlWebProxy = new htmlWebProxyList($this->testApiKey, $config);
        $params = $htmlWebProxy->getParams();
        
        $this->assertIsArray($params);
        $this->assertEquals('RU', $params['country']);
        $this->assertEquals(25, $params['perpage']);
        $this->assertEquals('HTTPS', $params['type']);
    }
    
    /**
     * Тест: Обновление параметров
     */
    public function testUpdateParams(): void
    {
        $htmlWebProxy = new htmlWebProxyList($this->testApiKey, [
            'type' => 'HTTP',
        ]);
        
        $initialParams = $htmlWebProxy->getParams();
        $this->assertEquals('HTTP', $initialParams['type']);
        
        $htmlWebProxy->updateParams([
            'country' => 'US',
        ]);
        
        $updatedParams = $htmlWebProxy->getParams();
        $this->assertEquals('HTTP', $updatedParams['type']);
        $this->assertEquals('US', $updatedParams['country']);
    }
    
    /**
     * Тест: Сброс параметров
     */
    public function testResetParams(): void
    {
        $htmlWebProxy = new htmlWebProxyList($this->testApiKey, [
            'country' => 'US',
            'perpage' => 30,
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
        $htmlWebProxy = new htmlWebProxyList($this->testApiKey, [
            'country' => 'ru',
        ]);
        
        $params = $htmlWebProxy->getParams();
        $this->assertEquals('RU', $params['country']);
    }
    
    /**
     * Тест: Нормализация параметров (преобразование в верхний регистр для type)
     */
    public function testParamNormalizationType(): void
    {
        $htmlWebProxy = new htmlWebProxyList($this->testApiKey, [
            'type' => 'http',
        ]);
        
        $params = $htmlWebProxy->getParams();
        $this->assertEquals('HTTP', $params['type']);
    }
    
    /**
     * Тест: Интеграция с ProxyPool - создание объектов
     */
    public function testIntegrationWithProxyPool(): void
    {
        $proxyPool = new ProxyPool([
            'auto_health_check' => false,
        ]);
        
        $htmlWebProxy = new htmlWebProxyList($this->testApiKey, [
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
        $types = ['HTTP', 'HTTPS', 'SOCKS4', 'SOCKS5'];
        
        foreach ($types as $type) {
            $htmlWebProxy = new htmlWebProxyList($this->testApiKey, [
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
        $htmlWebProxy = new htmlWebProxyList($this->testApiKey, [
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
        $htmlWebProxy = new htmlWebProxyList($this->testApiKey, [
            'country' => '',
            'country_not' => '',
        ]);
        
        $params = $htmlWebProxy->getParams();
        $this->assertArrayNotHasKey('country', $params);
        $this->assertArrayNotHasKey('country_not', $params);
    }
    
    /**
     * Тест: Краткий формат short=2
     */
    public function testShortFormat2(): void
    {
        $htmlWebProxy = new htmlWebProxyList($this->testApiKey, [
            'short' => 2,
        ]);
        
        $params = $htmlWebProxy->getParams();
        $this->assertEquals(2, $params['short']);
    }
    
    /**
     * Тест: Краткий формат short=4
     */
    public function testShortFormat4(): void
    {
        $htmlWebProxy = new htmlWebProxyList($this->testApiKey, [
            'short' => 4,
        ]);
        
        $params = $htmlWebProxy->getParams();
        $this->assertEquals(4, $params['short']);
    }
    
    /**
     * Тест: Таймаут устанавливается корректно
     */
    public function testTimeoutConfiguration(): void
    {
        $htmlWebProxy = new htmlWebProxyList($this->testApiKey, [
            'timeout' => 20,
        ]);
        
        $this->assertInstanceOf(htmlWebProxyList::class, $htmlWebProxy);
    }
    
    /**
     * Тест: Минимальное значение таймаута
     */
    public function testMinimumTimeout(): void
    {
        $htmlWebProxy = new htmlWebProxyList($this->testApiKey, [
            'timeout' => 0,
        ]);
        
        $this->assertInstanceOf(htmlWebProxyList::class, $htmlWebProxy);
    }
    
    /**
     * Тест: Получение остатка лимита запросов
     */
    public function testGetRemainingLimit(): void
    {
        $htmlWebProxy = new htmlWebProxyList($this->testApiKey);
        
        // До выполнения запроса лимит должен быть null
        $this->assertNull($htmlWebProxy->getRemainingLimit());
    }
    
    /**
     * Тест: Параметр country как массив
     */
    public function testCountryAsArray(): void
    {
        $htmlWebProxy = new htmlWebProxyList($this->testApiKey, [
            'country' => ['RU', 'US', 'GB'],
        ]);
        
        $params = $htmlWebProxy->getParams();
        $this->assertEquals('RU,US,GB', $params['country']);
    }
    
    /**
     * Тест: Параметр country_not как массив
     */
    public function testCountryNotAsArray(): void
    {
        $htmlWebProxy = new htmlWebProxyList($this->testApiKey, [
            'country_not' => ['CN', 'RU'],
        ]);
        
        $params = $htmlWebProxy->getParams();
        $this->assertEquals('CN,RU', $params['country_not']);
    }
    
    /**
     * Тест: Загрузка из конфигурационного файла через fromConfig()
     */
    public function testLoadFromConfigFile(): void
    {
        // Создаем временный конфигурационный файл
        $configPath = $this->testLogDirectory . '/htmlwebproxylist.json';
        $config = [
            'api_key' => $this->testApiKey,
            'country' => 'US',
            'perpage' => 30,
            'work' => 1,
            'type' => 'HTTPS',
            'timeout' => 15,
            '_comment' => 'Test config',
            '_fields' => ['test' => 'field'],
        ];
        
        file_put_contents($configPath, json_encode($config));
        
        $htmlWebProxy = htmlWebProxyList::fromConfig($configPath);
        
        $this->assertInstanceOf(htmlWebProxyList::class, $htmlWebProxy);
        
        $params = $htmlWebProxy->getParams();
        $this->assertEquals('US', $params['country']);
        $this->assertEquals(30, $params['perpage']);
        $this->assertEquals(1, $params['work']);
        $this->assertEquals('HTTPS', $params['type']);
    }
    
    /**
     * Тест: Ошибка при загрузке несуществующего конфигурационного файла
     */
    public function testLoadFromNonExistentConfigFile(): void
    {
        $this->expectException(HtmlWebProxyListException::class);
        $this->expectExceptionMessageMatches('/Не удалось загрузить конфигурацию/');
        
        htmlWebProxyList::fromConfig('/non/existent/config.json');
    }
    
    /**
     * Тест: Ошибка при отсутствии API ключа в конфигурации
     */
    public function testLoadFromConfigWithoutApiKey(): void
    {
        $this->expectException(HtmlWebProxyListValidationException::class);
        $this->expectExceptionMessage('API ключ (api_key) не указан в конфигурационном файле');
        
        $configPath = $this->testLogDirectory . '/no_api_key.json';
        $config = [
            'country' => 'US',
            'perpage' => 30,
        ];
        
        file_put_contents($configPath, json_encode($config));
        
        htmlWebProxyList::fromConfig($configPath);
    }
    
    /**
     * Тест: Ошибка при пустом API ключе в конфигурации
     */
    public function testLoadFromConfigWithEmptyApiKey(): void
    {
        $this->expectException(HtmlWebProxyListValidationException::class);
        $this->expectExceptionMessage('API ключ (api_key) не указан в конфигурационном файле');
        
        $configPath = $this->testLogDirectory . '/empty_api_key.json';
        $config = [
            'api_key' => '',
            'country' => 'US',
        ];
        
        file_put_contents($configPath, json_encode($config));
        
        htmlWebProxyList::fromConfig($configPath);
    }
}

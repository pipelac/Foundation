<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Component\ProxyPool;
use App\Component\Logger;
use App\Component\Exception\ProxyPoolException;
use App\Component\Exception\ProxyPoolValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Модульные тесты для класса ProxyPool
 * 
 * Проверяет функциональность менеджера пула прокси:
 * - Инициализацию с различными конфигурациями
 * - Добавление и удаление прокси
 * - Ротацию прокси (round-robin и random)
 * - Получение статистики
 * - Валидацию конфигурации
 */
class ProxyPoolTest extends TestCase
{
    private string $testLogDirectory;
    
    /**
     * Настройка окружения перед каждым тестом
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->testLogDirectory = sys_get_temp_dir() . '/proxypool_test_' . uniqid();
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
     * Тест: Успешная инициализация ProxyPool с минимальной конфигурацией
     */
    public function testSuccessfulInitializationWithMinimalConfig(): void
    {
        $proxyPool = new ProxyPool([
            'auto_health_check' => false, // Отключаем для теста
        ]);
        
        $this->assertInstanceOf(ProxyPool::class, $proxyPool);
    }
    
    /**
     * Тест: Инициализация с полной конфигурацией
     */
    public function testInitializationWithFullConfig(): void
    {
        $proxyPool = new ProxyPool([
            'proxies' => [
                'http://proxy1.example.com:8080',
                'http://proxy2.example.com:8080',
            ],
            'rotation_strategy' => ProxyPool::ROTATION_ROUND_ROBIN,
            'health_check_url' => 'https://www.google.com',
            'health_check_timeout' => 5,
            'health_check_interval' => 300,
            'auto_health_check' => false,
            'max_retries' => 3,
        ]);
        
        $this->assertInstanceOf(ProxyPool::class, $proxyPool);
    }
    
    /**
     * Тест: Инициализация с логгером
     */
    public function testInitializationWithLogger(): void
    {
        $logger = new Logger([
            'directory' => $this->testLogDirectory,
            'file_name' => 'proxypool.log',
            'enabled' => true,
        ]);
        
        $proxyPool = new ProxyPool([
            'auto_health_check' => false,
        ], $logger);
        
        $this->assertInstanceOf(ProxyPool::class, $proxyPool);
    }
    
    /**
     * Тест: Ошибка при недопустимой стратегии ротации
     */
    public function testThrowsExceptionForInvalidRotationStrategy(): void
    {
        $this->expectException(ProxyPoolValidationException::class);
        $this->expectExceptionMessageMatches('/Недопустимая стратегия ротации/');
        
        new ProxyPool([
            'rotation_strategy' => 'invalid_strategy',
            'auto_health_check' => false,
        ]);
    }
    
    /**
     * Тест: Добавление прокси в пул
     */
    public function testAddProxy(): void
    {
        $proxyPool = new ProxyPool([
            'auto_health_check' => false,
        ]);
        
        $proxyPool->addProxy('http://proxy1.example.com:8080');
        $proxyPool->addProxy('http://proxy2.example.com:8080');
        
        $stats = $proxyPool->getStatistics();
        $this->assertEquals(2, $stats['total_proxies']);
    }
    
    /**
     * Тест: Добавление невалидного прокси
     */
    public function testThrowsExceptionForInvalidProxy(): void
    {
        $proxyPool = new ProxyPool([
            'auto_health_check' => false,
        ]);
        
        $this->expectException(ProxyPoolValidationException::class);
        $this->expectExceptionMessageMatches('/Невалидный формат прокси URL/');
        
        $proxyPool->addProxy('invalid-proxy-format');
    }
    
    /**
     * Тест: Добавление пустого прокси
     */
    public function testThrowsExceptionForEmptyProxy(): void
    {
        $proxyPool = new ProxyPool([
            'auto_health_check' => false,
        ]);
        
        $this->expectException(ProxyPoolValidationException::class);
        $this->expectExceptionMessageMatches('/Прокси URL не может быть пустым/');
        
        $proxyPool->addProxy('');
    }
    
    /**
     * Тест: Удаление прокси из пула
     */
    public function testRemoveProxy(): void
    {
        $proxyPool = new ProxyPool([
            'proxies' => [
                'http://proxy1.example.com:8080',
                'http://proxy2.example.com:8080',
            ],
            'auto_health_check' => false,
        ]);
        
        $proxyPool->removeProxy('http://proxy1.example.com:8080');
        
        $stats = $proxyPool->getStatistics();
        $this->assertEquals(1, $stats['total_proxies']);
    }
    
    /**
     * Тест: Очистка всех прокси
     */
    public function testClearProxies(): void
    {
        $proxyPool = new ProxyPool([
            'proxies' => [
                'http://proxy1.example.com:8080',
                'http://proxy2.example.com:8080',
                'http://proxy3.example.com:8080',
            ],
            'auto_health_check' => false,
        ]);
        
        $proxyPool->clearProxies();
        
        $stats = $proxyPool->getStatistics();
        $this->assertEquals(0, $stats['total_proxies']);
    }
    
    /**
     * Тест: Round-robin ротация прокси
     */
    public function testRoundRobinRotation(): void
    {
        $proxyPool = new ProxyPool([
            'proxies' => [
                'http://proxy1.example.com:8080',
                'http://proxy2.example.com:8080',
                'http://proxy3.example.com:8080',
            ],
            'rotation_strategy' => ProxyPool::ROTATION_ROUND_ROBIN,
            'auto_health_check' => false,
        ]);
        
        // Первый цикл
        $proxy1 = $proxyPool->getNextProxy();
        $proxy2 = $proxyPool->getNextProxy();
        $proxy3 = $proxyPool->getNextProxy();
        
        // Второй цикл (должны повториться)
        $proxy4 = $proxyPool->getNextProxy();
        
        $this->assertNotNull($proxy1);
        $this->assertNotNull($proxy2);
        $this->assertNotNull($proxy3);
        $this->assertEquals($proxy1, $proxy4);
        $this->assertNotEquals($proxy1, $proxy2);
        $this->assertNotEquals($proxy2, $proxy3);
    }
    
    /**
     * Тест: Random ротация прокси
     */
    public function testRandomRotation(): void
    {
        $proxyPool = new ProxyPool([
            'proxies' => [
                'http://proxy1.example.com:8080',
                'http://proxy2.example.com:8080',
                'http://proxy3.example.com:8080',
            ],
            'rotation_strategy' => ProxyPool::ROTATION_RANDOM,
            'auto_health_check' => false,
        ]);
        
        $proxy1 = $proxyPool->getNextProxy();
        $proxy2 = $proxyPool->getNextProxy();
        
        $this->assertNotNull($proxy1);
        $this->assertNotNull($proxy2);
        
        // Проверяем, что оба прокси из нашего списка
        $validProxies = [
            'http://proxy1.example.com:8080',
            'http://proxy2.example.com:8080',
            'http://proxy3.example.com:8080',
        ];
        
        $this->assertContains($proxy1, $validProxies);
        $this->assertContains($proxy2, $validProxies);
    }
    
    /**
     * Тест: Получение случайного прокси
     */
    public function testGetRandomProxy(): void
    {
        $proxyPool = new ProxyPool([
            'proxies' => [
                'http://proxy1.example.com:8080',
                'http://proxy2.example.com:8080',
            ],
            'auto_health_check' => false,
        ]);
        
        $proxy = $proxyPool->getRandomProxy();
        
        $this->assertNotNull($proxy);
        $this->assertContains($proxy, [
            'http://proxy1.example.com:8080',
            'http://proxy2.example.com:8080',
        ]);
    }
    
    /**
     * Тест: Получение null когда нет доступных прокси
     */
    public function testGetNextProxyReturnsNullWhenNoProxies(): void
    {
        $proxyPool = new ProxyPool([
            'auto_health_check' => false,
        ]);
        
        $proxy = $proxyPool->getNextProxy();
        
        $this->assertNull($proxy);
    }
    
    /**
     * Тест: Пометка прокси как живого
     */
    public function testMarkProxyAsAlive(): void
    {
        $proxyPool = new ProxyPool([
            'proxies' => [
                'http://proxy1.example.com:8080',
            ],
            'auto_health_check' => false,
        ]);
        
        $proxyPool->markProxyAsAlive('http://proxy1.example.com:8080');
        
        $stats = $proxyPool->getStatistics();
        $this->assertEquals(1, $stats['alive_proxies']);
        $this->assertEquals(1, $stats['proxies'][0]['success_count']);
    }
    
    /**
     * Тест: Пометка прокси как мёртвого
     */
    public function testMarkProxyAsDead(): void
    {
        $proxyPool = new ProxyPool([
            'proxies' => [
                'http://proxy1.example.com:8080',
            ],
            'auto_health_check' => false,
        ]);
        
        $proxyPool->markProxyAsDead('http://proxy1.example.com:8080');
        
        $stats = $proxyPool->getStatistics();
        $this->assertEquals(0, $stats['alive_proxies']);
        $this->assertEquals(1, $stats['dead_proxies']);
        $this->assertEquals(1, $stats['proxies'][0]['fail_count']);
    }
    
    /**
     * Тест: Получение всех прокси
     */
    public function testGetAllProxies(): void
    {
        $proxyPool = new ProxyPool([
            'proxies' => [
                'http://proxy1.example.com:8080',
                'http://proxy2.example.com:8080',
            ],
            'auto_health_check' => false,
        ]);
        
        $allProxies = $proxyPool->getAllProxies();
        
        $this->assertCount(2, $allProxies);
        $this->assertArrayHasKey('http://proxy1.example.com:8080', $allProxies);
        $this->assertArrayHasKey('http://proxy2.example.com:8080', $allProxies);
        
        foreach ($allProxies as $proxy) {
            $this->assertArrayHasKey('url', $proxy);
            $this->assertArrayHasKey('alive', $proxy);
        }
    }
    
    /**
     * Тест: Получение детальной статистики
     */
    public function testGetStatistics(): void
    {
        $proxyPool = new ProxyPool([
            'proxies' => [
                'http://proxy1.example.com:8080',
                'http://proxy2.example.com:8080',
            ],
            'rotation_strategy' => ProxyPool::ROTATION_ROUND_ROBIN,
            'auto_health_check' => false,
        ]);
        
        $stats = $proxyPool->getStatistics();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_proxies', $stats);
        $this->assertArrayHasKey('alive_proxies', $stats);
        $this->assertArrayHasKey('dead_proxies', $stats);
        $this->assertArrayHasKey('rotation_strategy', $stats);
        $this->assertArrayHasKey('total_requests', $stats);
        $this->assertArrayHasKey('successful_requests', $stats);
        $this->assertArrayHasKey('failed_requests', $stats);
        $this->assertArrayHasKey('total_retries', $stats);
        $this->assertArrayHasKey('success_rate', $stats);
        $this->assertArrayHasKey('proxies', $stats);
        
        $this->assertEquals(2, $stats['total_proxies']);
        $this->assertEquals(2, $stats['alive_proxies']);
        $this->assertEquals(0, $stats['dead_proxies']);
        $this->assertEquals(ProxyPool::ROTATION_ROUND_ROBIN, $stats['rotation_strategy']);
        $this->assertIsArray($stats['proxies']);
        $this->assertCount(2, $stats['proxies']);
    }
    
    /**
     * Тест: Сброс статистики
     */
    public function testResetStatistics(): void
    {
        $proxyPool = new ProxyPool([
            'proxies' => [
                'http://proxy1.example.com:8080',
            ],
            'auto_health_check' => false,
        ]);
        
        // Добавляем статистику
        $proxyPool->markProxyAsAlive('http://proxy1.example.com:8080');
        $proxyPool->markProxyAsAlive('http://proxy1.example.com:8080');
        
        $statsBefore = $proxyPool->getStatistics();
        $this->assertEquals(2, $statsBefore['proxies'][0]['success_count']);
        
        // Сбрасываем
        $proxyPool->resetStatistics();
        
        $statsAfter = $proxyPool->getStatistics();
        $this->assertEquals(0, $statsAfter['proxies'][0]['success_count']);
        $this->assertEquals(0, $statsAfter['total_requests']);
    }
    
    /**
     * Тест: Статистика содержит правильную информацию о прокси
     */
    public function testStatisticsContainsProxyDetails(): void
    {
        $proxyPool = new ProxyPool([
            'proxies' => [
                'http://proxy1.example.com:8080',
            ],
            'auto_health_check' => false,
        ]);
        
        $stats = $proxyPool->getStatistics();
        
        $this->assertCount(1, $stats['proxies']);
        
        $proxyInfo = $stats['proxies'][0];
        $this->assertArrayHasKey('url', $proxyInfo);
        $this->assertArrayHasKey('alive', $proxyInfo);
        $this->assertArrayHasKey('last_check', $proxyInfo);
        $this->assertArrayHasKey('last_check_human', $proxyInfo);
        $this->assertArrayHasKey('success_count', $proxyInfo);
        $this->assertArrayHasKey('fail_count', $proxyInfo);
        $this->assertArrayHasKey('total_requests', $proxyInfo);
        $this->assertArrayHasKey('success_rate', $proxyInfo);
        $this->assertArrayHasKey('last_error', $proxyInfo);
        
        $this->assertEquals('http://proxy1.example.com:8080', $proxyInfo['url']);
        $this->assertTrue($proxyInfo['alive']);
        $this->assertEquals(0, $proxyInfo['success_count']);
        $this->assertEquals(0, $proxyInfo['fail_count']);
    }
    
    /**
     * Тест: Поддержка различных форматов прокси URL
     */
    public function testSupportsVariousProxyFormats(): void
    {
        $proxyPool = new ProxyPool([
            'auto_health_check' => false,
        ]);
        
        // HTTP прокси
        $proxyPool->addProxy('http://proxy.example.com:8080');
        
        // HTTPS прокси
        $proxyPool->addProxy('https://proxy.example.com:8443');
        
        // SOCKS4 прокси
        $proxyPool->addProxy('socks4://proxy.example.com:1080');
        
        // SOCKS5 прокси
        $proxyPool->addProxy('socks5://proxy.example.com:1080');
        
        // Прокси с аутентификацией
        $proxyPool->addProxy('http://user:pass@proxy.example.com:8080');
        
        $stats = $proxyPool->getStatistics();
        $this->assertEquals(5, $stats['total_proxies']);
    }
    
    /**
     * Тест: Загрузка из конфигурационного файла через fromConfig()
     */
    public function testLoadFromConfigFile(): void
    {
        // Создаем временный конфигурационный файл
        $configPath = $this->testLogDirectory . '/proxypool.json';
        $config = [
            'proxies' => [
                'http://proxy1.example.com:8080',
                'http://proxy2.example.com:8080',
                'socks5://proxy3.example.com:1080',
            ],
            'rotation_strategy' => 'round_robin',
            'health_check_url' => 'https://httpbin.org/ip',
            'health_check_timeout' => 5,
            'auto_health_check' => false,
            'max_retries' => 3,
            '_comment' => 'Test config',
            '_fields' => ['test' => 'field'],
        ];
        
        file_put_contents($configPath, json_encode($config));
        
        $proxyPool = ProxyPool::fromConfig($configPath);
        
        $this->assertInstanceOf(ProxyPool::class, $proxyPool);
        
        $stats = $proxyPool->getStatistics();
        $this->assertEquals(3, $stats['total_proxies']);
        $this->assertEquals('round_robin', $stats['rotation_strategy']);
    }
    
    /**
     * Тест: Загрузка из конфигурационного файла с логгером
     */
    public function testLoadFromConfigFileWithLogger(): void
    {
        $configPath = $this->testLogDirectory . '/proxypool_with_logger.json';
        $config = [
            'proxies' => [
                'http://proxy1.example.com:8080',
            ],
            'auto_health_check' => false,
        ];
        
        file_put_contents($configPath, json_encode($config));
        
        $logger = new Logger([
            'directory' => $this->testLogDirectory,
            'file_name' => 'proxypool_test.log',
            'enabled' => true,
        ]);
        
        $proxyPool = ProxyPool::fromConfig($configPath, $logger);
        
        $this->assertInstanceOf(ProxyPool::class, $proxyPool);
        
        $stats = $proxyPool->getStatistics();
        $this->assertEquals(1, $stats['total_proxies']);
    }
    
    /**
     * Тест: Ошибка при загрузке несуществующего конфигурационного файла
     */
    public function testLoadFromNonExistentConfigFile(): void
    {
        $this->expectException(ProxyPoolException::class);
        $this->expectExceptionMessageMatches('/Не удалось загрузить конфигурацию/');
        
        ProxyPool::fromConfig('/non/existent/config.json');
    }
    
    /**
     * Тест: Загрузка из конфигурации с некорректным JSON
     */
    public function testLoadFromConfigWithInvalidJson(): void
    {
        $this->expectException(ProxyPoolException::class);
        $this->expectExceptionMessageMatches('/Не удалось загрузить конфигурацию/');
        
        $configPath = $this->testLogDirectory . '/invalid.json';
        file_put_contents($configPath, '{invalid json}');
        
        ProxyPool::fromConfig($configPath);
    }
}

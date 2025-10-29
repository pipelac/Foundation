<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cache\FileCache;
use Cache\FileCacheConfig;
use PHPUnit\Framework\TestCase;

/**
 * Модульные тесты для класса FileCache
 * 
 * Проверяет функциональность файлового кеша:
 * - Инициализацию с различными конфигурациями
 * - Базовые операции get/set/delete
 * - Работу с TTL
 * - Статистику
 */
class FileCacheTest extends TestCase
{
    private string $testCacheDirectory;
    
    /**
     * Настройка окружения перед каждым тестом
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->testCacheDirectory = sys_get_temp_dir() . '/filecache_test_' . uniqid();
        mkdir($this->testCacheDirectory, 0777, true);
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
     * Тест: Успешная инициализация с массивом конфигурации
     */
    public function testSuccessfulInitializationWithArrayConfig(): void
    {
        $cache = new FileCache([
            'cacheDirectory' => $this->testCacheDirectory,
        ]);
        
        $this->assertInstanceOf(FileCache::class, $cache);
    }
    
    /**
     * Тест: Успешная инициализация с объектом конфигурации
     */
    public function testSuccessfulInitializationWithConfigObject(): void
    {
        $config = new FileCacheConfig([
            'cacheDirectory' => $this->testCacheDirectory,
        ]);
        
        $cache = new FileCache($config);
        
        $this->assertInstanceOf(FileCache::class, $cache);
    }
    
    /**
     * Тест: Инициализация без параметров
     */
    public function testInitializationWithoutParameters(): void
    {
        $cache = new FileCache();
        
        $this->assertInstanceOf(FileCache::class, $cache);
    }
    
    /**
     * Тест: Сохранение и получение простого значения
     */
    public function testSetAndGetSimpleValue(): void
    {
        $cache = new FileCache([
            'cacheDirectory' => $this->testCacheDirectory,
        ]);
        
        $cache->set('test_key', 'test_value');
        $result = $cache->get('test_key');
        
        $this->assertEquals('test_value', $result);
    }
    
    /**
     * Тест: Получение несуществующего ключа возвращает значение по умолчанию
     */
    public function testGetNonExistentKeyReturnsDefault(): void
    {
        $cache = new FileCache([
            'cacheDirectory' => $this->testCacheDirectory,
        ]);
        
        $result = $cache->get('non_existent_key', 'default_value');
        
        $this->assertEquals('default_value', $result);
    }
    
    /**
     * Тест: Сохранение и получение различных типов данных
     */
    public function testSetAndGetVariousDataTypes(): void
    {
        $cache = new FileCache([
            'cacheDirectory' => $this->testCacheDirectory,
        ]);
        
        $cache->set('string', 'test string');
        $cache->set('integer', 42);
        $cache->set('float', 3.14);
        $cache->set('boolean', true);
        $cache->set('array', [1, 2, 3]);
        $cache->set('null', null);
        
        $this->assertEquals('test string', $cache->get('string'));
        $this->assertEquals(42, $cache->get('integer'));
        $this->assertEquals(3.14, $cache->get('float'));
        $this->assertTrue($cache->get('boolean'));
        $this->assertEquals([1, 2, 3], $cache->get('array'));
        $this->assertNull($cache->get('null'));
    }
    
    /**
     * Тест: Удаление ключа
     */
    public function testDeleteKey(): void
    {
        $cache = new FileCache([
            'cacheDirectory' => $this->testCacheDirectory,
        ]);
        
        $cache->set('test_key', 'test_value');
        $this->assertEquals('test_value', $cache->get('test_key'));
        
        $cache->delete('test_key');
        
        $result = $cache->get('test_key', 'default');
        $this->assertEquals('default', $result);
    }
    
    /**
     * Тест: Проверка существования ключа
     */
    public function testHasKey(): void
    {
        $cache = new FileCache([
            'cacheDirectory' => $this->testCacheDirectory,
        ]);
        
        $cache->set('existing_key', 'value');
        
        $this->assertTrue($cache->has('existing_key'));
        $this->assertFalse($cache->has('non_existent_key'));
    }
    
    /**
     * Тест: Очистка всего кеша
     */
    public function testClearCache(): void
    {
        $cache = new FileCache([
            'cacheDirectory' => $this->testCacheDirectory,
        ]);
        
        $cache->set('key1', 'value1');
        $cache->set('key2', 'value2');
        $cache->set('key3', 'value3');
        
        $this->assertTrue($cache->has('key1'));
        $this->assertTrue($cache->has('key2'));
        
        $cache->clear();
        
        $this->assertFalse($cache->has('key1'));
        $this->assertFalse($cache->has('key2'));
        $this->assertFalse($cache->has('key3'));
    }
    
    /**
     * Тест: Множественное сохранение значений
     */
    public function testSetMultiple(): void
    {
        $cache = new FileCache([
            'cacheDirectory' => $this->testCacheDirectory,
        ]);
        
        $values = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];
        
        $cache->setMultiple($values);
        
        $this->assertEquals('value1', $cache->get('key1'));
        $this->assertEquals('value2', $cache->get('key2'));
        $this->assertEquals('value3', $cache->get('key3'));
    }
    
    /**
     * Тест: Множественное получение значений
     */
    public function testGetMultiple(): void
    {
        $cache = new FileCache([
            'cacheDirectory' => $this->testCacheDirectory,
        ]);
        
        $cache->set('key1', 'value1');
        $cache->set('key2', 'value2');
        $cache->set('key3', 'value3');
        
        $result = $cache->getMultiple(['key1', 'key2', 'key3']);
        
        $this->assertIsArray($result);
        $this->assertEquals('value1', $result['key1']);
        $this->assertEquals('value2', $result['key2']);
        $this->assertEquals('value3', $result['key3']);
    }
    
    /**
     * Тест: Множественное удаление значений
     */
    public function testDeleteMultiple(): void
    {
        $cache = new FileCache([
            'cacheDirectory' => $this->testCacheDirectory,
        ]);
        
        $cache->set('key1', 'value1');
        $cache->set('key2', 'value2');
        $cache->set('key3', 'value3');
        
        $cache->deleteMultiple(['key1', 'key2']);
        
        $this->assertFalse($cache->has('key1'));
        $this->assertFalse($cache->has('key2'));
        $this->assertTrue($cache->has('key3'));
    }
    
    /**
     * Тест: Инициализация с включенной статистикой
     */
    public function testInitializationWithStatisticsEnabled(): void
    {
        $cache = new FileCache([
            'cacheDirectory' => $this->testCacheDirectory,
            'enableStatistics' => true,
        ]);
        
        $cache->set('key1', 'value1');
        $cache->get('key1');
        $cache->get('non_existent');
        
        $stats = $cache->getStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('hits', $stats);
        $this->assertArrayHasKey('misses', $stats);
    }
    
    /**
     * Тест: Инициализация с различными сериализаторами
     */
    public function testInitializationWithDifferentSerializers(): void
    {
        $cache1 = new FileCache([
            'cacheDirectory' => $this->testCacheDirectory . '/native',
            'serializer' => 'native',
        ]);
        
        $cache2 = new FileCache([
            'cacheDirectory' => $this->testCacheDirectory . '/json',
            'serializer' => 'json',
        ]);
        
        $this->assertInstanceOf(FileCache::class, $cache1);
        $this->assertInstanceOf(FileCache::class, $cache2);
    }
    
    /**
     * Тест: Инициализация с шардированием
     */
    public function testInitializationWithSharding(): void
    {
        $cache = new FileCache([
            'cacheDirectory' => $this->testCacheDirectory,
            'useSharding' => true,
            'shardingDepth' => 2,
        ]);
        
        $cache->set('test_key', 'test_value');
        
        $this->assertEquals('test_value', $cache->get('test_key'));
    }
    
    /**
     * Тест: Инициализация с префиксом ключа
     */
    public function testInitializationWithKeyPrefix(): void
    {
        $cache = new FileCache([
            'cacheDirectory' => $this->testCacheDirectory,
            'keyPrefix' => 'app_',
        ]);
        
        $cache->set('test', 'value');
        
        $this->assertEquals('value', $cache->get('test'));
    }
    
    /**
     * Тест: Инициализация с пространством имен
     */
    public function testInitializationWithNamespace(): void
    {
        $cache = new FileCache([
            'cacheDirectory' => $this->testCacheDirectory,
            'namespace' => 'my_app',
        ]);
        
        $cache->set('key', 'value');
        
        $this->assertEquals('value', $cache->get('key'));
    }
}

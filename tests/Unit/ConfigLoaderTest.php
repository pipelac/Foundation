<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Config\ConfigLoader;
use Exception;
use PHPUnit\Framework\TestCase;

/**
 * Модульные тесты для класса ConfigLoader
 * 
 * Проверяет функциональность загрузки конфигурационных файлов:
 * - Загрузку валидных JSON файлов
 * - Обработку ошибок при отсутствии файла
 * - Обработку невалидного JSON
 */
class ConfigLoaderTest extends TestCase
{
    private string $testConfigDirectory;
    
    /**
     * Настройка окружения перед каждым тестом
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->testConfigDirectory = sys_get_temp_dir() . '/config_test_' . uniqid();
        mkdir($this->testConfigDirectory, 0777, true);
    }
    
    /**
     * Очистка после каждого теста
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        if (is_dir($this->testConfigDirectory)) {
            $this->removeDirectory($this->testConfigDirectory);
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
     * Тест: Успешная загрузка валидного JSON конфигурационного файла
     */
    public function testLoadValidJsonConfig(): void
    {
        $configPath = $this->testConfigDirectory . '/config.json';
        $configData = [
            'database' => [
                'host' => 'localhost',
                'port' => 3306,
                'name' => 'testdb',
            ],
            'api_key' => 'secret123',
            'debug' => true,
        ];
        
        file_put_contents($configPath, json_encode($configData));
        
        $result = ConfigLoader::load($configPath);
        
        $this->assertIsArray($result);
        $this->assertEquals('localhost', $result['database']['host']);
        $this->assertEquals(3306, $result['database']['port']);
        $this->assertEquals('testdb', $result['database']['name']);
        $this->assertEquals('secret123', $result['api_key']);
        $this->assertTrue($result['debug']);
    }
    
    /**
     * Тест: Загрузка пустого JSON объекта
     */
    public function testLoadEmptyJsonObject(): void
    {
        $configPath = $this->testConfigDirectory . '/empty.json';
        file_put_contents($configPath, '{}');
        
        $result = ConfigLoader::load($configPath);
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
    
    /**
     * Тест: Загрузка конфигурации с вложенными объектами
     */
    public function testLoadNestedJsonConfig(): void
    {
        $configPath = $this->testConfigDirectory . '/nested.json';
        $configData = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'value' => 'deep_value',
                    ],
                ],
            ],
        ];
        
        file_put_contents($configPath, json_encode($configData));
        
        $result = ConfigLoader::load($configPath);
        
        $this->assertEquals('deep_value', $result['level1']['level2']['level3']['value']);
    }
    
    /**
     * Тест: Загрузка конфигурации с различными типами данных
     */
    public function testLoadConfigWithMixedTypes(): void
    {
        $configPath = $this->testConfigDirectory . '/mixed.json';
        $configData = [
            'string' => 'text',
            'integer' => 42,
            'float' => 3.14,
            'boolean_true' => true,
            'boolean_false' => false,
            'null_value' => null,
            'array' => [1, 2, 3],
        ];
        
        file_put_contents($configPath, json_encode($configData));
        
        $result = ConfigLoader::load($configPath);
        
        $this->assertIsString($result['string']);
        $this->assertIsInt($result['integer']);
        $this->assertIsFloat($result['float']);
        $this->assertIsBool($result['boolean_true']);
        $this->assertIsBool($result['boolean_false']);
        $this->assertNull($result['null_value']);
        $this->assertIsArray($result['array']);
    }
    
    /**
     * Тест: Исключение при попытке загрузить несуществующий файл
     */
    public function testThrowsExceptionWhenFileNotFound(): void
    {
        $nonExistentPath = $this->testConfigDirectory . '/does_not_exist.json';
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Конфигурационный файл не найден');
        
        ConfigLoader::load($nonExistentPath);
    }
    
    /**
     * Тест: Исключение при невалидном JSON
     */
    public function testThrowsExceptionForInvalidJson(): void
    {
        $configPath = $this->testConfigDirectory . '/invalid.json';
        file_put_contents($configPath, '{invalid json content}');
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Ошибка парсинга JSON');
        
        ConfigLoader::load($configPath);
    }
    
    /**
     * Тест: Исключение при JSON с синтаксической ошибкой
     */
    public function testThrowsExceptionForMalformedJson(): void
    {
        $configPath = $this->testConfigDirectory . '/malformed.json';
        file_put_contents($configPath, '{"key": "value",}');
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Ошибка парсинга JSON');
        
        ConfigLoader::load($configPath);
    }
    
    /**
     * Тест: Загрузка конфигурации с Unicode символами
     */
    public function testLoadConfigWithUnicodeCharacters(): void
    {
        $configPath = $this->testConfigDirectory . '/unicode.json';
        $configData = [
            'russian' => 'Привет мир',
            'chinese' => '你好世界',
            'arabic' => 'مرحبا بالعالم',
            'emoji' => '😀🎉',
        ];
        
        file_put_contents($configPath, json_encode($configData, JSON_UNESCAPED_UNICODE));
        
        $result = ConfigLoader::load($configPath);
        
        $this->assertEquals('Привет мир', $result['russian']);
        $this->assertEquals('你好世界', $result['chinese']);
        $this->assertEquals('مرحبا بالعالم', $result['arabic']);
        $this->assertEquals('😀🎉', $result['emoji']);
    }
    
    /**
     * Тест: Загрузка конфигурации с большими числами
     */
    public function testLoadConfigWithLargeNumbers(): void
    {
        $configPath = $this->testConfigDirectory . '/large_numbers.json';
        $configData = [
            'large_int' => 9223372036854775807,
            'small_float' => 0.0000000001,
            'large_float' => 1.7976931348623157E+308,
        ];
        
        file_put_contents($configPath, json_encode($configData));
        
        $result = ConfigLoader::load($configPath);
        
        $this->assertIsNumeric($result['large_int']);
        $this->assertIsFloat($result['small_float']);
        $this->assertIsFloat($result['large_float']);
    }
    
    /**
     * Тест: Загрузка конфигурации с комментариями (стандартный JSON не поддерживает)
     */
    public function testLoadConfigWithoutComments(): void
    {
        $configPath = $this->testConfigDirectory . '/with_field.json';
        $configData = [
            '_comment' => 'This is a comment field',
            'actual_data' => 'value',
        ];
        
        file_put_contents($configPath, json_encode($configData));
        
        $result = ConfigLoader::load($configPath);
        
        $this->assertArrayHasKey('_comment', $result);
        $this->assertArrayHasKey('actual_data', $result);
    }
}

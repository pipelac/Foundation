<?php

declare(strict_types=1);

namespace Tests\Unit;

use Cache\FileCacheConfig;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Модульные тесты для класса FileCacheConfig
 * 
 * Проверяет функциональность конфигурации файлового кеша:
 * - Инициализацию с различными параметрами
 * - Валидацию всех конфигурационных полей
 * - Обработку невалидных значений
 */
class FileCacheConfigTest extends TestCase
{
    /**
     * Тест: Успешная инициализация с минимальной конфигурацией
     */
    public function testSuccessfulInitializationWithMinimalConfig(): void
    {
        $config = new FileCacheConfig([
            'cacheDirectory' => '/tmp/test_cache',
        ]);
        
        $this->assertInstanceOf(FileCacheConfig::class, $config);
        $this->assertEquals('/tmp/test_cache', $config->cacheDirectory);
    }
    
    /**
     * Тест: Инициализация без параметров использует временную директорию
     */
    public function testInitializationWithoutParametersUsesTempDir(): void
    {
        $config = new FileCacheConfig();
        
        $this->assertInstanceOf(FileCacheConfig::class, $config);
        $this->assertStringContainsString('file_cache', $config->cacheDirectory);
    }
    
    /**
     * Тест: Значения по умолчанию устанавливаются корректно
     */
    public function testDefaultValuesAreSetCorrectly(): void
    {
        $config = new FileCacheConfig([
            'cacheDirectory' => '/tmp/test_cache',
        ]);
        
        $this->assertEquals(0o755, $config->directoryPermissions);
        $this->assertEquals(0o644, $config->filePermissions);
        $this->assertEquals('.cache', $config->fileExtension);
        $this->assertFalse($config->useSharding);
        $this->assertTrue($config->fileLocking);
        $this->assertEquals('native', $config->serializer);
    }
    
    /**
     * Тест: Валидация проходит успешно с корректной конфигурацией
     */
    public function testValidationPassesWithValidConfig(): void
    {
        $config = new FileCacheConfig([
            'cacheDirectory' => '/tmp/test_cache',
        ]);
        
        $config->validate();
        
        $this->assertTrue(true);
    }
    
    /**
     * Тест: Исключение при пустой директории кеша после инициализации
     */
    public function testThrowsExceptionForEmptyCacheDirectory(): void
    {
        $config = new FileCacheConfig([
            'cacheDirectory' => '/tmp/test',
        ]);
        $config->cacheDirectory = '';
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Директория кэша не может быть пустой');
        
        $config->validate();
    }
    
    /**
     * Тест: Исключение при невалидных правах доступа директории
     */
    public function testThrowsExceptionForInvalidDirectoryPermissions(): void
    {
        $config = new FileCacheConfig([
            'cacheDirectory' => '/tmp/test_cache',
            'directoryPermissions' => 0o1000,
        ]);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Права доступа директории должны быть между 0000 и 0777');
        
        $config->validate();
    }
    
    /**
     * Тест: Исключение при отрицательных правах доступа директории
     */
    public function testThrowsExceptionForNegativeDirectoryPermissions(): void
    {
        $config = new FileCacheConfig([
            'cacheDirectory' => '/tmp/test_cache',
            'directoryPermissions' => -1,
        ]);
        
        $this->expectException(InvalidArgumentException::class);
        
        $config->validate();
    }
    
    /**
     * Тест: Исключение при невалидных правах доступа файла
     */
    public function testThrowsExceptionForInvalidFilePermissions(): void
    {
        $config = new FileCacheConfig([
            'cacheDirectory' => '/tmp/test_cache',
            'filePermissions' => 0o1000,
        ]);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Права доступа файла должны быть между 0000 и 0777');
        
        $config->validate();
    }
    
    /**
     * Тест: Исключение при расширении файла без точки
     */
    public function testThrowsExceptionForFileExtensionWithoutDot(): void
    {
        $config = new FileCacheConfig([
            'cacheDirectory' => '/tmp/test_cache',
            'fileExtension' => 'cache',
        ]);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Расширение файла должно начинаться с точки');
        
        $config->validate();
    }
    
    /**
     * Тест: Исключение при отрицательной глубине шардирования
     */
    public function testThrowsExceptionForNegativeShardingDepth(): void
    {
        $config = new FileCacheConfig([
            'cacheDirectory' => '/tmp/test_cache',
            'shardingDepth' => -1,
        ]);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Глубина шардирования не может быть отрицательной');
        
        $config->validate();
    }
    
    /**
     * Тест: Исключение при отрицательном TTL по умолчанию
     */
    public function testThrowsExceptionForNegativeDefaultTtl(): void
    {
        $config = new FileCacheConfig([
            'cacheDirectory' => '/tmp/test_cache',
            'defaultTtl' => -1,
        ]);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Время жизни по умолчанию не может быть отрицательным');
        
        $config->validate();
    }
    
    /**
     * Тест: Исключение при нулевом или отрицательном максимальном TTL
     */
    public function testThrowsExceptionForZeroOrNegativeMaxTtl(): void
    {
        $config = new FileCacheConfig([
            'cacheDirectory' => '/tmp/test_cache',
            'maxTtl' => 0,
        ]);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Максимальное время жизни должно быть больше нуля');
        
        $config->validate();
    }
    
    /**
     * Тест: Исключение когда defaultTtl больше maxTtl
     */
    public function testThrowsExceptionWhenDefaultTtlGreaterThanMaxTtl(): void
    {
        $config = new FileCacheConfig([
            'cacheDirectory' => '/tmp/test_cache',
            'defaultTtl' => 7200,
            'maxTtl' => 3600,
        ]);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Время жизни по умолчанию не может быть больше максимального');
        
        $config->validate();
    }
    
    /**
     * Тест: Исключение при невалидном делителе сборщика мусора
     */
    public function testThrowsExceptionForInvalidGcDivisor(): void
    {
        $config = new FileCacheConfig([
            'cacheDirectory' => '/tmp/test_cache',
            'gcDivisor' => 0,
        ]);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Делитель сборщика мусора должен быть больше нуля');
        
        $config->validate();
    }
    
    /**
     * Тест: Исключение при невалидной вероятности сборщика мусора
     */
    public function testThrowsExceptionForInvalidGcProbability(): void
    {
        $config = new FileCacheConfig([
            'cacheDirectory' => '/tmp/test_cache',
            'gcProbability' => 200,
            'gcDivisor' => 100,
        ]);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Вероятность сборщика мусора должна быть между 0 и делителем');
        
        $config->validate();
    }
    
    /**
     * Тест: Исключение при неподдерживаемом сериализаторе
     */
    public function testThrowsExceptionForUnsupportedSerializer(): void
    {
        $config = new FileCacheConfig([
            'cacheDirectory' => '/tmp/test_cache',
            'serializer' => 'invalid_serializer',
        ]);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Неподдерживаемый сериализатор');
        
        $config->validate();
    }
    
    /**
     * Тест: Исключение при невалидном уровне сжатия
     */
    public function testThrowsExceptionForInvalidCompressionLevel(): void
    {
        $config = new FileCacheConfig([
            'cacheDirectory' => '/tmp/test_cache',
            'compressionEnabled' => true,
            'compressionLevel' => 10,
        ]);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Уровень сжатия должен быть между 1 и 9');
        
        $config->validate();
    }
    
    /**
     * Тест: Исключение при отрицательном пороге сжатия
     */
    public function testThrowsExceptionForNegativeCompressionThreshold(): void
    {
        $config = new FileCacheConfig([
            'cacheDirectory' => '/tmp/test_cache',
            'compressionEnabled' => true,
            'compressionThreshold' => -1,
        ]);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Порог сжатия не может быть отрицательным');
        
        $config->validate();
    }
    
    /**
     * Тест: Исключение при отрицательном таймауте блокировки
     */
    public function testThrowsExceptionForNegativeLockTimeout(): void
    {
        $config = new FileCacheConfig([
            'cacheDirectory' => '/tmp/test_cache',
            'lockTimeout' => -1,
        ]);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Время ожидания блокировки не может быть отрицательным');
        
        $config->validate();
    }
    
    /**
     * Тест: Исключение при неподдерживаемом алгоритме хеширования
     */
    public function testThrowsExceptionForUnsupportedKeyHashAlgorithm(): void
    {
        $config = new FileCacheConfig([
            'cacheDirectory' => '/tmp/test_cache',
            'keyHashAlgorithm' => 'invalid_algorithm',
        ]);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Неподдерживаемый алгоритм хеширования ключей');
        
        $config->validate();
    }
    
    /**
     * Тест: Исключение при невалидном максимальном размере кеша
     */
    public function testThrowsExceptionForInvalidMaxCacheSize(): void
    {
        $config = new FileCacheConfig([
            'cacheDirectory' => '/tmp/test_cache',
            'maxCacheSize' => 0,
        ]);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Максимальный размер кэша должен быть больше нуля');
        
        $config->validate();
    }
    
    /**
     * Тест: Исключение при невалидном максимальном размере элемента
     */
    public function testThrowsExceptionForInvalidMaxItemSize(): void
    {
        $config = new FileCacheConfig([
            'cacheDirectory' => '/tmp/test_cache',
            'maxItemSize' => -1,
        ]);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Максимальный размер элемента должен быть больше нуля');
        
        $config->validate();
    }
    
    /**
     * Тест: Исключение при неподдерживаемой стратегии обработки ошибок
     */
    public function testThrowsExceptionForUnsupportedErrorHandling(): void
    {
        $config = new FileCacheConfig([
            'cacheDirectory' => '/tmp/test_cache',
            'errorHandling' => 'invalid_strategy',
        ]);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Неподдерживаемая стратегия обработки ошибок');
        
        $config->validate();
    }
    
    /**
     * Тест: Инициализация со всеми параметрами
     */
    public function testInitializationWithAllParameters(): void
    {
        $config = new FileCacheConfig([
            'cacheDirectory' => '/tmp/test_cache',
            'directoryPermissions' => 0o755,
            'filePermissions' => 0o644,
            'fileExtension' => '.tmp',
            'useSharding' => true,
            'shardingDepth' => 2,
            'defaultTtl' => 3600,
            'maxTtl' => 7200,
            'gcProbability' => 1,
            'gcDivisor' => 100,
            'serializer' => 'json',
            'compressionEnabled' => false,
            'fileLocking' => true,
            'keyPrefix' => 'app_',
            'namespace' => 'default',
            'enableStatistics' => true,
            'errorHandling' => 'log',
        ]);
        
        $this->assertInstanceOf(FileCacheConfig::class, $config);
        $config->validate();
    }
}

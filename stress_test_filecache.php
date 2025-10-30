<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Cache\FileCache;
use Cache\FileCacheConfig;

/**
 * Комплексный нагрузочный тест для FileCache
 * 
 * Тестирует все методы класса под нагрузкой с различными сценариями
 */
class FileCacheStressTest
{
    private FileCache $cache;
    private array $results = [];
    private int $totalTests = 0;
    private int $passedTests = 0;
    private int $failedTests = 0;
    private array $errors = [];
    private float $startTime;

    public function __construct()
    {
        $this->startTime = microtime(true);
        echo "╔══════════════════════════════════════════════════════════════╗\n";
        echo "║     КОМПЛЕКСНЫЙ НАГРУЗОЧНЫЙ ТЕСТ FileCache                   ║\n";
        echo "╚══════════════════════════════════════════════════════════════╝\n\n";
    }

    /**
     * Запускает все тесты
     */
    public function runAllTests(): void
    {
        // 1. Тест базовой конфигурации
        $this->testBasicConfiguration();
        
        // 2. Тест базовых операций
        $this->testBasicOperations();
        
        // 3. Тест работы с TTL
        $this->testTTLOperations();
        
        // 4. Тест множественных операций
        $this->testMultipleOperations();
        
        // 5. Тест работы с тегами
        $this->testTagOperations();
        
        // 6. Тест атомарных операций
        $this->testAtomicOperations();
        
        // 7. Тест сборщика мусора
        $this->testGarbageCollection();
        
        // 8. Тест сериализаторов
        $this->testSerializers();
        
        // 9. Тест сжатия
        $this->testCompression();
        
        // 10. Тест конкурентного доступа
        $this->testConcurrentAccess();
        
        // 11. Тест больших данных
        $this->testLargeData();
        
        // 12. Тест метаданных и статистики
        $this->testMetadataAndStats();
        
        // 13. Тест граничных условий
        $this->testEdgeCases();
        
        // 14. Нагрузочный тест
        $this->testHighLoad();
        
        // Вывод результатов
        $this->printResults();
    }

    /**
     * Тест 1: Базовая конфигурация
     */
    private function testBasicConfiguration(): void
    {
        $this->printTestHeader("Тест базовой конфигурации");
        
        try {
            // Тест с массивом конфигурации
            $config = [
                'cacheDirectory' => sys_get_temp_dir() . '/stress_test_cache',
                'defaultTtl' => 3600,
                'useSharding' => true,
                'shardingDepth' => 2,
                'enableStatistics' => true,
            ];
            
            $this->cache = new FileCache($config);
            $this->assertTrue("Создание кэша с массивом конфигурации", true);
            
            // Очистка для чистого теста
            $this->cache->clear();
            
            // Тест валидации конфигурации
            try {
                new FileCacheConfig(['cacheDirectory' => '']);
                $this->assertTrue("Проверка валидации пустой директории", false);
            } catch (InvalidArgumentException $e) {
                $this->assertTrue("Проверка валидации пустой директории", true);
            }
            
            // Тест health check
            $health = $this->cache->healthCheck();
            $this->assertTrue("Health check - директория существует", $health['directory_exists']);
            $this->assertTrue("Health check - директория читаема", $health['directory_readable']);
            $this->assertTrue("Health check - директория записываема", $health['directory_writable']);
            
        } catch (Throwable $e) {
            $this->recordError("Базовая конфигурация", $e);
        }
    }

    /**
     * Тест 2: Базовые операции (set, get, has, delete)
     */
    private function testBasicOperations(): void
    {
        $this->printTestHeader("Тест базовых операций");
        
        try {
            // Test set и get
            $key = 'test_key_1';
            $value = 'test_value_1';
            
            $setResult = $this->cache->set($key, $value);
            $this->assertTrue("Set операция", $setResult);
            
            $getValue = $this->cache->get($key);
            $this->assertEquals("Get операция", $value, $getValue);
            
            // Test has
            $hasResult = $this->cache->has($key);
            $this->assertTrue("Has операция для существующего ключа", $hasResult);
            
            $hasResult2 = $this->cache->has('non_existent_key');
            $this->assertTrue("Has операция для несуществующего ключа", !$hasResult2);
            
            // Test delete
            $deleteResult = $this->cache->delete($key);
            $this->assertTrue("Delete операция", $deleteResult);
            
            $hasResult3 = $this->cache->has($key);
            $this->assertTrue("Has после delete", !$hasResult3);
            
            // Test с различными типами данных
            $testData = [
                'string' => 'test string',
                'integer' => 12345,
                'float' => 123.45,
                'array' => ['a' => 1, 'b' => 2, 'c' => [1, 2, 3]],
                'object' => (object)['prop1' => 'value1', 'prop2' => 'value2'],
                'boolean_true' => true,
                'boolean_false' => false,
                'null' => null,
            ];
            
            foreach ($testData as $type => $data) {
                $key = "type_test_{$type}";
                $this->cache->set($key, $data);
                $retrieved = $this->cache->get($key);
                
                // Объекты сравниваем через == (не ===), так как это разные инстансы
                if ($type === 'object') {
                    $this->assertTrue("Тип данных: {$type}", $data == $retrieved);
                } else {
                    $this->assertEquals("Тип данных: {$type}", $data, $retrieved);
                }
            }
            
        } catch (Throwable $e) {
            $this->recordError("Базовые операции", $e);
        }
    }

    /**
     * Тест 3: Работа с TTL
     */
    private function testTTLOperations(): void
    {
        $this->printTestHeader("Тест работы с TTL");
        
        try {
            // Тест с коротким TTL
            $key = 'ttl_test_1';
            $value = 'ttl_value_1';
            
            $this->cache->set($key, $value, 2); // 2 секунды
            $this->assertTrue("Set с TTL=2", $this->cache->has($key));
            
            sleep(3);
            $this->assertTrue("Проверка истечения TTL", !$this->cache->has($key));
            
            // Тест touch
            $key2 = 'ttl_test_2';
            $value2 = 'ttl_value_2';
            $this->cache->set($key2, $value2, 5);
            
            sleep(2);
            $touchResult = $this->cache->touch($key2, 10);
            $this->assertTrue("Touch операция", $touchResult);
            
            $metadata = $this->cache->getMetadata($key2);
            $this->assertTrue("Метаданные после touch существуют", $metadata !== null);
            
            // Тест isExpired
            $key3 = 'ttl_test_3';
            $this->cache->set($key3, 'value', 1);
            $this->assertTrue("isExpired до истечения", !$this->cache->isExpired($key3));
            
            sleep(2);
            $this->assertTrue("isExpired после истечения", $this->cache->isExpired($key3));
            
            // Тест с DateInterval
            $key4 = 'ttl_test_4';
            $interval = new DateInterval('PT5S'); // 5 секунд
            $this->cache->set($key4, 'value', $interval);
            $this->assertTrue("Set с DateInterval", $this->cache->has($key4));
            
        } catch (Throwable $e) {
            $this->recordError("Работа с TTL", $e);
        }
    }

    /**
     * Тест 4: Множественные операции
     */
    private function testMultipleOperations(): void
    {
        $this->printTestHeader("Тест множественных операций");
        
        try {
            // Test setMultiple
            $items = [
                'multi_key_1' => 'value_1',
                'multi_key_2' => 'value_2',
                'multi_key_3' => 'value_3',
                'multi_key_4' => 'value_4',
                'multi_key_5' => 'value_5',
            ];
            
            $setMultiResult = $this->cache->setMultiple($items);
            $this->assertTrue("SetMultiple операция", $setMultiResult);
            
            // Test getMultiple
            $keys = array_keys($items);
            $retrieved = $this->cache->getMultiple($keys);
            
            $allMatch = true;
            foreach ($items as $key => $value) {
                if (!isset($retrieved[$key]) || $retrieved[$key] !== $value) {
                    $allMatch = false;
                    break;
                }
            }
            $this->assertTrue("GetMultiple операция", $allMatch);
            
            // Test deleteMultiple
            $deleteKeys = ['multi_key_1', 'multi_key_3', 'multi_key_5'];
            $deleteMultiResult = $this->cache->deleteMultiple($deleteKeys);
            $this->assertTrue("DeleteMultiple операция", $deleteMultiResult);
            
            foreach ($deleteKeys as $key) {
                $this->assertTrue("Проверка удаления {$key}", !$this->cache->has($key));
            }
            
        } catch (Throwable $e) {
            $this->recordError("Множественные операции", $e);
        }
    }

    /**
     * Тест 5: Работа с тегами
     */
    private function testTagOperations(): void
    {
        $this->printTestHeader("Тест работы с тегами");
        
        try {
            // Создание элементов с тегами
            $this->cache->tags(['tag1', 'tag2'])->set('tagged_key_1', 'value_1');
            $this->cache->tags(['tag1'])->set('tagged_key_2', 'value_2');
            $this->cache->tags(['tag2', 'tag3'])->set('tagged_key_3', 'value_3');
            $this->cache->tags(['tag3'])->set('tagged_key_4', 'value_4');
            
            $this->assertTrue("Создание элементов с тегами", 
                $this->cache->has('tagged_key_1') && 
                $this->cache->has('tagged_key_2') &&
                $this->cache->has('tagged_key_3') &&
                $this->cache->has('tagged_key_4')
            );
            
            // Удаление по тегу
            $deleteResult = $this->cache->deleteByTag('tag1');
            $this->assertTrue("DeleteByTag операция", $deleteResult);
            
            $this->assertTrue("Проверка удаления по tag1", 
                !$this->cache->has('tagged_key_1') && 
                !$this->cache->has('tagged_key_2')
            );
            
            $this->assertTrue("Проверка сохранения других тегов",
                $this->cache->has('tagged_key_3') &&
                $this->cache->has('tagged_key_4')
            );
            
            // Удаление по другому тегу
            $this->cache->deleteByTag('tag2');
            $this->assertTrue("Удаление по tag2", !$this->cache->has('tagged_key_3'));
            
            // Множественная установка с тегами
            $this->cache->tags(['bulk_tag'])->setMultiple([
                'bulk_1' => 'value_1',
                'bulk_2' => 'value_2',
                'bulk_3' => 'value_3',
            ]);
            
            $this->assertTrue("Множественная установка с тегами", 
                $this->cache->has('bulk_1') &&
                $this->cache->has('bulk_2') &&
                $this->cache->has('bulk_3')
            );
            
            $this->cache->deleteByTag('bulk_tag');
            $this->assertTrue("Удаление множественных элементов по тегу",
                !$this->cache->has('bulk_1') &&
                !$this->cache->has('bulk_2') &&
                !$this->cache->has('bulk_3')
            );
            
        } catch (Throwable $e) {
            $this->recordError("Работа с тегами", $e);
        }
    }

    /**
     * Тест 6: Атомарные операции (increment, decrement, remember, pull)
     */
    private function testAtomicOperations(): void
    {
        $this->printTestHeader("Тест атомарных операций");
        
        try {
            // Test increment
            $key = 'counter';
            $result1 = $this->cache->increment($key);
            $this->assertEquals("Increment с 0", 1, $result1);
            
            $result2 = $this->cache->increment($key, 5);
            $this->assertEquals("Increment на 5", 6, $result2);
            
            // Test decrement
            $result3 = $this->cache->decrement($key, 2);
            $this->assertEquals("Decrement на 2", 4, $result3);
            
            // Test remember
            $rememberKey = 'remember_test';
            $callCount = 0;
            
            $value1 = $this->cache->remember($rememberKey, function() use (&$callCount) {
                $callCount++;
                return 'computed_value';
            });
            
            $this->assertEquals("Remember первый вызов", 'computed_value', $value1);
            $this->assertEquals("Remember callback вызван", 1, $callCount);
            
            $value2 = $this->cache->remember($rememberKey, function() use (&$callCount) {
                $callCount++;
                return 'computed_value';
            });
            
            $this->assertEquals("Remember второй вызов (из кэша)", 'computed_value', $value2);
            $this->assertEquals("Remember callback не вызван повторно", 1, $callCount);
            
            // Test pull
            $pullKey = 'pull_test';
            $this->cache->set($pullKey, 'pull_value');
            
            $pulledValue = $this->cache->pull($pullKey);
            $this->assertEquals("Pull значение", 'pull_value', $pulledValue);
            $this->assertTrue("Pull удалил ключ", !$this->cache->has($pullKey));
            
            $pulledDefault = $this->cache->pull('non_existent', 'default');
            $this->assertEquals("Pull с default", 'default', $pulledDefault);
            
        } catch (Throwable $e) {
            $this->recordError("Атомарные операции", $e);
        }
    }

    /**
     * Тест 7: Сборщик мусора
     */
    private function testGarbageCollection(): void
    {
        $this->printTestHeader("Тест сборщика мусора");
        
        try {
            // Создание элементов с истекшим сроком
            for ($i = 1; $i <= 10; $i++) {
                $this->cache->set("gc_test_{$i}", "value_{$i}", 1);
            }
            
            // Создание актуальных элементов
            for ($i = 11; $i <= 20; $i++) {
                $this->cache->set("gc_test_{$i}", "value_{$i}", 3600);
            }
            
            sleep(2); // Ждем истечения первых 10 элементов
            
            // Запуск GC
            $deleted = $this->cache->gc(true);
            $this->assertTrue("GC удалил истекшие элементы", $deleted >= 10);
            
            // Проверка что истекшие удалены
            $expired = 0;
            for ($i = 1; $i <= 10; $i++) {
                if (!$this->cache->has("gc_test_{$i}")) {
                    $expired++;
                }
            }
            $this->assertEquals("Истекшие элементы удалены", 10, $expired);
            
            // Проверка что актуальные остались
            $active = 0;
            for ($i = 11; $i <= 20; $i++) {
                if ($this->cache->has("gc_test_{$i}")) {
                    $active++;
                }
            }
            $this->assertEquals("Актуальные элементы сохранены", 10, $active);
            
            // Test prune (алиас для gc(true))
            $this->cache->set('prune_test', 'value', 1);
            sleep(2);
            $pruned = $this->cache->prune();
            $this->assertTrue("Prune операция", $pruned >= 1);
            
            // Test vacuum (удаление пустых директорий)
            $vacuumResult = $this->cache->vacuum();
            $this->assertTrue("Vacuum операция", $vacuumResult);
            
        } catch (Throwable $e) {
            $this->recordError("Сборщик мусора", $e);
        }
    }

    /**
     * Тест 8: Различные сериализаторы
     */
    private function testSerializers(): void
    {
        $this->printTestHeader("Тест сериализаторов");
        
        $testData = [
            'simple_string' => 'Hello, World!',
            'complex_array' => ['a' => 1, 'b' => [2, 3, 4], 'c' => ['nested' => true]],
            'object' => (object)['x' => 10, 'y' => 20],
        ];
        
        $serializers = ['native', 'json'];
        
        foreach ($serializers as $serializer) {
            try {
                $config = [
                    'cacheDirectory' => sys_get_temp_dir() . '/stress_test_cache_' . $serializer,
                    'serializer' => $serializer,
                ];
                
                $cache = new FileCache($config);
                $cache->clear();
                
                $allPassed = true;
                foreach ($testData as $key => $value) {
                    $cache->set($key, $value);
                    $retrieved = $cache->get($key);
                    
                    // JSON преобразует объекты в массивы - это ограничение формата
                    if ($serializer === 'json' && $key === 'object') {
                        // Проверяем что объект превратился в массив с теми же данными
                        $expectedArray = ['x' => 10, 'y' => 20];
                        if ($retrieved != $expectedArray) {
                            $allPassed = false;
                            break;
                        }
                    } else {
                        if ($retrieved != $value) {
                            $allPassed = false;
                            break;
                        }
                    }
                }
                
                $this->assertTrue("Сериализатор {$serializer}", $allPassed);
                
            } catch (Throwable $e) {
                $this->recordError("Сериализатор {$serializer}", $e);
            }
        }
    }

    /**
     * Тест 9: Сжатие данных
     */
    private function testCompression(): void
    {
        $this->printTestHeader("Тест сжатия данных");
        
        try {
            $config = [
                'cacheDirectory' => sys_get_temp_dir() . '/stress_test_cache_compression',
                'compressionEnabled' => true,
                'compressionLevel' => 6,
                'compressionThreshold' => 1024,
            ];
            
            $cache = new FileCache($config);
            $cache->clear();
            
            // Маленькие данные (не должны сжиматься)
            $smallData = str_repeat('a', 512);
            $cache->set('small_data', $smallData);
            $retrieved = $cache->get('small_data');
            $this->assertEquals("Маленькие данные без сжатия", $smallData, $retrieved);
            
            // Большие данные (должны сжиматься)
            $largeData = str_repeat('test data for compression ', 100);
            $cache->set('large_data', $largeData);
            $retrieved2 = $cache->get('large_data');
            $this->assertEquals("Большие данные со сжатием", $largeData, $retrieved2);
            
            // Проверка размера файла со сжатием
            $pathLarge = $cache->getPath('large_data');
            $compressedSize = filesize($pathLarge);
            
            // Создание без сжатия для сравнения
            $cacheNoCompression = new FileCache([
                'cacheDirectory' => sys_get_temp_dir() . '/stress_test_cache_no_compression',
                'compressionEnabled' => false,
            ]);
            $cacheNoCompression->clear();
            $cacheNoCompression->set('large_data', $largeData);
            $pathNoCompression = $cacheNoCompression->getPath('large_data');
            $uncompressedSize = filesize($pathNoCompression);
            
            $this->assertTrue("Сжатие уменьшило размер файла", $compressedSize < $uncompressedSize);
            
        } catch (Throwable $e) {
            $this->recordError("Сжатие данных", $e);
        }
    }

    /**
     * Тест 10: Конкурентный доступ
     */
    private function testConcurrentAccess(): void
    {
        $this->printTestHeader("Тест конкурентного доступа");
        
        try {
            $key = 'concurrent_counter';
            $this->cache->set($key, 0);
            
            $iterations = 50;
            
            // Последовательные инкременты для проверки блокировки
            for ($i = 0; $i < $iterations; $i++) {
                $this->cache->increment($key);
            }
            
            $finalValue = $this->cache->get($key);
            $this->assertEquals("Конкурентные инкременты", $iterations, $finalValue);
            
            // Тест блокировки при записи
            $lockTestKey = 'lock_test';
            $this->cache->set($lockTestKey, 'initial');
            
            // Быстрые последовательные записи
            for ($i = 0; $i < 10; $i++) {
                $this->cache->set($lockTestKey, "value_{$i}");
            }
            
            $value = $this->cache->get($lockTestKey);
            $this->assertTrue("Последняя запись сохранена", str_starts_with($value, 'value_'));
            
        } catch (Throwable $e) {
            $this->recordError("Конкурентный доступ", $e);
        }
    }

    /**
     * Тест 11: Большие данные
     */
    private function testLargeData(): void
    {
        $this->printTestHeader("Тест больших данных");
        
        try {
            // Массив с большим количеством элементов
            $largeArray = [];
            for ($i = 0; $i < 1000; $i++) {
                $largeArray["key_{$i}"] = "value_{$i}_" . str_repeat('x', 100);
            }
            
            $this->cache->set('large_array', $largeArray);
            $retrieved = $this->cache->get('large_array');
            
            $this->assertEquals("Большой массив", count($largeArray), count($retrieved));
            $this->assertTrue("Содержимое большого массива", $largeArray === $retrieved);
            
            // Большая строка
            $largeString = str_repeat('Large data test ', 10000); // ~160KB
            $this->cache->set('large_string', $largeString);
            $retrieved2 = $this->cache->get('large_string');
            
            $this->assertEquals("Большая строка", strlen($largeString), strlen($retrieved2));
            
            // Проверка размера кэша
            $cacheSize = $this->cache->getSize();
            $this->assertTrue("Размер кэша больше нуля", $cacheSize > 0);
            
        } catch (Throwable $e) {
            $this->recordError("Большие данные", $e);
        }
    }

    /**
     * Тест 12: Метаданные и статистика
     */
    private function testMetadataAndStats(): void
    {
        $this->printTestHeader("Тест метаданных и статистики");
        
        try {
            $this->cache->resetStats();
            
            // Создание элементов
            for ($i = 1; $i <= 5; $i++) {
                $this->cache->set("stats_test_{$i}", "value_{$i}");
            }
            
            // Чтение элементов (попадания)
            for ($i = 1; $i <= 5; $i++) {
                $this->cache->get("stats_test_{$i}");
            }
            
            // Чтение несуществующих (промахи)
            for ($i = 6; $i <= 8; $i++) {
                $this->cache->get("stats_test_{$i}", 'default');
            }
            
            // Получение статистики
            $stats = $this->cache->getStats();
            
            $this->assertTrue("Статистика: записи >= 5", $stats['writes'] >= 5);
            $this->assertTrue("Статистика: попадания >= 5", $stats['hits'] >= 5);
            $this->assertTrue("Статистика: промахи >= 3", $stats['misses'] >= 3);
            $this->assertTrue("Статистика: процент попаданий", $stats['hit_rate'] > 0);
            $this->assertTrue("Статистика: количество элементов", $stats['item_count'] >= 5);
            
            // Метаданные элемента
            $metadata = $this->cache->getMetadata('stats_test_1');
            $this->assertTrue("Метаданные существуют", $metadata !== null);
            $this->assertTrue("Метаданные: created", isset($metadata['created']));
            $this->assertTrue("Метаданные: size", isset($metadata['size']) && $metadata['size'] > 0);
            
            // Количество элементов
            $itemCount = $this->cache->getItemCount();
            $this->assertTrue("Количество элементов >= 5", $itemCount >= 5);
            
            // Сброс статистики
            $this->cache->resetStats();
            $statsAfterReset = $this->cache->getStats();
            $this->assertEquals("Сброс статистики: попадания", 0, $statsAfterReset['hits']);
            $this->assertEquals("Сброс статистики: промахи", 0, $statsAfterReset['misses']);
            
        } catch (Throwable $e) {
            $this->recordError("Метаданные и статистика", $e);
        }
    }

    /**
     * Тест 13: Граничные условия
     */
    private function testEdgeCases(): void
    {
        $this->printTestHeader("Тест граничных условий");
        
        try {
            // Пустой ключ
            try {
                $this->cache->set('', 'value');
                $this->assertTrue("Пустой ключ должен вызвать исключение", false);
            } catch (InvalidArgumentException $e) {
                $this->assertTrue("Пустой ключ вызвал исключение", true);
            }
            
            // Ключ с недопустимыми символами
            try {
                $this->cache->set('key{with}bad:chars', 'value');
                $this->assertTrue("Недопустимые символы должны вызвать исключение", false);
            } catch (InvalidArgumentException $e) {
                $this->assertTrue("Недопустимые символы вызвали исключение", true);
            }
            
            // Очень длинный ключ
            try {
                $longKey = str_repeat('a', 300);
                $this->cache->set($longKey, 'value');
                $this->assertTrue("Длинный ключ должен вызвать исключение", false);
            } catch (InvalidArgumentException $e) {
                $this->assertTrue("Длинный ключ вызвал исключение", true);
            }
            
            // Нулевой TTL
            $this->cache->set('zero_ttl', 'value', 0);
            $this->assertTrue("Нулевой TTL", !$this->cache->has('zero_ttl'));
            
            // Отрицательный TTL
            $this->cache->set('negative_ttl', 'value', -1);
            $this->assertTrue("Отрицательный TTL", !$this->cache->has('negative_ttl'));
            
            // Null значение
            $this->cache->set('null_value', null);
            $sentinel = new stdClass();
            $retrieved = $this->cache->get('null_value', $sentinel);
            $this->assertTrue("Null значение сохранено", $retrieved === null);
            
            // False значение
            $this->cache->set('false_value', false);
            $retrieved2 = $this->cache->get('false_value', $sentinel);
            $this->assertTrue("False значение сохранено", $retrieved2 === false);
            
            // Пустая строка
            $this->cache->set('empty_string', '');
            $retrieved3 = $this->cache->get('empty_string', $sentinel);
            $this->assertTrue("Пустая строка сохранена", $retrieved3 === '');
            
            // Clear и flush
            $this->cache->set('before_clear', 'value');
            $this->cache->clear();
            $this->assertTrue("Clear удалил все элементы", !$this->cache->has('before_clear'));
            
            $this->cache->set('before_flush', 'value');
            $this->cache->flush();
            $this->assertTrue("Flush удалил все элементы", !$this->cache->has('before_flush'));
            
        } catch (Throwable $e) {
            $this->recordError("Граничные условия", $e);
        }
    }

    /**
     * Тест 14: Нагрузочный тест
     */
    private function testHighLoad(): void
    {
        $this->printTestHeader("Нагрузочный тест (высокая нагрузка)");
        
        try {
            $itemCount = 1000;
            $keyPrefix = 'load_test_';
            
            // Массовая запись
            $writeStart = microtime(true);
            for ($i = 0; $i < $itemCount; $i++) {
                $key = $keyPrefix . $i;
                $value = [
                    'id' => $i,
                    'data' => str_repeat('x', 100),
                    'timestamp' => time(),
                    'random' => rand(1, 1000),
                ];
                $this->cache->set($key, $value, 3600);
            }
            $writeTime = microtime(true) - $writeStart;
            
            echo "  ⏱  Запись {$itemCount} элементов: " . number_format($writeTime, 3) . " сек\n";
            echo "  ⚡ " . number_format($itemCount / $writeTime, 2) . " операций/сек\n";
            
            // Массовое чтение
            $readStart = microtime(true);
            $readCount = 0;
            for ($i = 0; $i < $itemCount; $i++) {
                $key = $keyPrefix . $i;
                $value = $this->cache->get($key);
                if ($value !== null) {
                    $readCount++;
                }
            }
            $readTime = microtime(true) - $readStart;
            
            echo "  ⏱  Чтение {$itemCount} элементов: " . number_format($readTime, 3) . " сек\n";
            echo "  ⚡ " . number_format($itemCount / $readTime, 2) . " операций/сек\n";
            
            $this->assertEquals("Прочитано элементов", $itemCount, $readCount);
            
            // Смешанные операции
            $mixedStart = microtime(true);
            for ($i = 0; $i < 500; $i++) {
                $key = $keyPrefix . rand(0, $itemCount - 1);
                
                $op = rand(1, 4);
                switch ($op) {
                    case 1:
                        $this->cache->get($key);
                        break;
                    case 2:
                        $this->cache->set($key, ['updated' => time()]);
                        break;
                    case 3:
                        $this->cache->has($key);
                        break;
                    case 4:
                        $this->cache->getMetadata($key);
                        break;
                }
            }
            $mixedTime = microtime(true) - $mixedStart;
            
            echo "  ⏱  Смешанные операции (500): " . number_format($mixedTime, 3) . " сек\n";
            echo "  ⚡ " . number_format(500 / $mixedTime, 2) . " операций/сек\n";
            
            // Финальная статистика
            $finalStats = $this->cache->getStats();
            echo "\n  📊 Финальная статистика:\n";
            echo "     Записей: {$finalStats['writes']}\n";
            echo "     Попаданий: {$finalStats['hits']}\n";
            echo "     Промахов: {$finalStats['misses']}\n";
            echo "     Удалений: {$finalStats['deletes']}\n";
            echo "     Процент попаданий: {$finalStats['hit_rate']}%\n";
            echo "     Размер кэша: " . $this->formatBytes($finalStats['size']) . "\n";
            echo "     Элементов: {$finalStats['item_count']}\n";
            
            $this->assertTrue("Нагрузочный тест завершен", true);
            
        } catch (Throwable $e) {
            $this->recordError("Нагрузочный тест", $e);
        }
    }

    /**
     * Вспомогательные методы
     */
    
    private function printTestHeader(string $title): void
    {
        echo "\n┌" . str_repeat("─", 60) . "┐\n";
        echo "│ " . str_pad($title, 58) . " │\n";
        echo "└" . str_repeat("─", 60) . "┘\n";
    }

    private function assertTrue(string $testName, bool $condition): void
    {
        $this->totalTests++;
        if ($condition) {
            $this->passedTests++;
            echo "  ✓ {$testName}\n";
        } else {
            $this->failedTests++;
            echo "  ✗ {$testName}\n";
            $this->errors[] = $testName;
        }
    }

    private function assertEquals(string $testName, mixed $expected, mixed $actual): void
    {
        $this->totalTests++;
        if ($expected === $actual) {
            $this->passedTests++;
            echo "  ✓ {$testName}\n";
        } else {
            $this->failedTests++;
            echo "  ✗ {$testName} (Expected: " . var_export($expected, true) . ", Got: " . var_export($actual, true) . ")\n";
            $this->errors[] = $testName;
        }
    }

    private function recordError(string $context, Throwable $e): void
    {
        $error = "{$context}: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}";
        echo "  ✗ ОШИБКА: {$error}\n";
        $this->errors[] = $error;
        $this->failedTests++;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    private function printResults(): void
    {
        $totalTime = microtime(true) - $this->startTime;
        
        echo "\n\n";
        echo "╔══════════════════════════════════════════════════════════════╗\n";
        echo "║                    РЕЗУЛЬТАТЫ ТЕСТИРОВАНИЯ                   ║\n";
        echo "╚══════════════════════════════════════════════════════════════╝\n\n";
        
        echo "Всего тестов: {$this->totalTests}\n";
        echo "Успешных: {$this->passedTests} (" . ($this->totalTests > 0 ? round($this->passedTests / $this->totalTests * 100, 2) : 0) . "%)\n";
        echo "Провалено: {$this->failedTests}\n";
        echo "Время выполнения: " . number_format($totalTime, 3) . " сек\n";
        
        if (!empty($this->errors)) {
            echo "\n❌ Список ошибок:\n";
            foreach ($this->errors as $i => $error) {
                echo "   " . ($i + 1) . ". {$error}\n";
            }
        } else {
            echo "\n✅ Все тесты прошли успешно!\n";
        }
        
        echo "\n";
    }
}

// Запуск тестов
$test = new FileCacheStressTest();
$test->runAllTests();

<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Cache\FileCache;
use Cache\FileCacheConfig;

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║         ТЕСТ ЛОГИРОВАНИЯ И ОБРАБОТКИ ОШИБОК FileCache        ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Создаем временный файл для логов
$logFile = sys_get_temp_dir() . '/filecache_test.log';
if (file_exists($logFile)) {
    unlink($logFile);
}

// Настраиваем error_log для записи в файл
ini_set('error_log', $logFile);
ini_set('log_errors', '1');

// =============================================================================
echo "1. Тест стратегии 'throw' (по умолчанию)\n";
echo str_repeat("─", 62) . "\n";

try {
    $cache1 = new FileCache([
        'cacheDirectory' => sys_get_temp_dir() . '/test_throw',
        'errorHandling' => 'throw',
    ]);
    $cache1->clear();
    
    // Попробуем вызвать ошибку - недопустимый ключ
    try {
        $cache1->set('key{invalid}', 'value');
        echo "❌ ОШИБКА: Исключение не выброшено\n";
    } catch (InvalidArgumentException $e) {
        echo "✅ Исключение выброшено: {$e->getMessage()}\n";
    }
    
    // Тест с валидными операциями
    $cache1->set('valid_key', 'value');
    echo "✅ Валидная операция прошла успешно\n";
    
} catch (Throwable $e) {
    echo "❌ Неожиданная ошибка: {$e->getMessage()}\n";
}

echo "\n";

// =============================================================================
echo "2. Тест стратегии 'log'\n";
echo str_repeat("─", 62) . "\n";

try {
    $cache2 = new FileCache([
        'cacheDirectory' => sys_get_temp_dir() . '/test_log',
        'errorHandling' => 'log',
    ]);
    $cache2->clear();
    
    // Создаем ситуацию с ошибкой - попробуем прочитать поврежденный файл
    $key = 'corrupted_key';
    $filePath = $cache2->getPath($key);
    
    // Создаем директорию если нужно
    $dir = dirname($filePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    // Записываем невалидный serialize
    file_put_contents($filePath, 'invalid serialized data');
    
    // Пытаемся прочитать - должно залогировать ошибку, но не выбросить исключение
    $value = $cache2->get($key, 'default');
    echo "✅ Ошибка обработана без исключения, вернулось значение по умолчанию: '{$value}'\n";
    
    // Проверяем наличие записи в логе
    if (file_exists($logFile)) {
        $logContent = file_get_contents($logFile);
        if (str_contains($logContent, 'unserialize')) {
            echo "✅ Ошибка записана в лог\n";
        } else {
            echo "⚠️  Ошибка НЕ найдена в логе (возможно, другой формат)\n";
        }
    } else {
        echo "⚠️  Лог-файл не создан\n";
    }
    
} catch (Throwable $e) {
    echo "❌ Неожиданная ошибка: {$e->getMessage()}\n";
}

echo "\n";

// =============================================================================
echo "3. Тест стратегии 'silent'\n";
echo str_repeat("─", 62) . "\n";

try {
    $cache3 = new FileCache([
        'cacheDirectory' => sys_get_temp_dir() . '/test_silent',
        'errorHandling' => 'silent',
    ]);
    $cache3->clear();
    
    // Создаем поврежденный файл
    $key = 'silent_key';
    $filePath = $cache3->getPath($key);
    
    $dir = dirname($filePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    file_put_contents($filePath, 'corrupted');
    
    $value = $cache3->get($key, 'default_silent');
    echo "✅ Silent mode: ошибка проигнорирована, вернулось: '{$value}'\n";
    
    // Валидная операция
    $cache3->set('valid_silent', 'value');
    echo "✅ Валидная операция в silent mode работает\n";
    
} catch (Throwable $e) {
    echo "❌ Неожиданная ошибка: {$e->getMessage()}\n";
}

echo "\n";

// =============================================================================
echo "4. Тест операций с логированием\n";
echo str_repeat("─", 62) . "\n";

$cache = new FileCache([
    'cacheDirectory' => sys_get_temp_dir() . '/test_operations',
    'errorHandling' => 'throw',
    'enableStatistics' => true,
]);
$cache->clear();

$operations = [
    'SET' => function() use ($cache) {
        return $cache->set('test_key_1', 'value_1');
    },
    'GET' => function() use ($cache) {
        return $cache->get('test_key_1');
    },
    'HAS' => function() use ($cache) {
        return $cache->has('test_key_1');
    },
    'DELETE' => function() use ($cache) {
        return $cache->delete('test_key_1');
    },
    'SET_MULTIPLE' => function() use ($cache) {
        return $cache->setMultiple(['key1' => 'val1', 'key2' => 'val2']);
    },
    'GET_MULTIPLE' => function() use ($cache) {
        return $cache->getMultiple(['key1', 'key2']);
    },
    'INCREMENT' => function() use ($cache) {
        return $cache->increment('counter');
    },
    'DECREMENT' => function() use ($cache) {
        return $cache->decrement('counter');
    },
    'TAGS' => function() use ($cache) {
        return $cache->tags(['tag1', 'tag2'])->set('tagged', 'value');
    },
    'DELETE_BY_TAG' => function() use ($cache) {
        return $cache->deleteByTag('tag1');
    },
    'CLEAR' => function() use ($cache) {
        return $cache->clear();
    },
];

foreach ($operations as $name => $operation) {
    try {
        $result = $operation();
        $status = $result !== false ? '✅' : '⚠️';
        echo "{$status} {$name}: " . var_export($result, true) . "\n";
    } catch (Throwable $e) {
        echo "❌ {$name}: {$e->getMessage()}\n";
    }
}

echo "\n";

// =============================================================================
echo "5. Статистика операций\n";
echo str_repeat("─", 62) . "\n";

$cache4 = new FileCache([
    'cacheDirectory' => sys_get_temp_dir() . '/test_stats',
    'errorHandling' => 'throw',
    'enableStatistics' => true,
]);
$cache4->clear();
$cache4->resetStats();

// Выполняем операции
for ($i = 1; $i <= 10; $i++) {
    $cache4->set("key_{$i}", "value_{$i}");
}

for ($i = 1; $i <= 10; $i++) {
    $cache4->get("key_{$i}");
}

for ($i = 11; $i <= 15; $i++) {
    $cache4->get("key_{$i}", 'default'); // промахи
}

for ($i = 1; $i <= 5; $i++) {
    $cache4->delete("key_{$i}");
}

$stats = $cache4->getStats();

echo "Статистика:\n";
echo "  Записей: {$stats['writes']}\n";
echo "  Попаданий: {$stats['hits']}\n";
echo "  Промахов: {$stats['misses']}\n";
echo "  Удалений: {$stats['deletes']}\n";
echo "  Процент попаданий: {$stats['hit_rate']}%\n";
echo "  Размер кэша: " . formatBytes($stats['size']) . "\n";
echo "  Количество элементов: {$stats['item_count']}\n";

echo "\n";

// =============================================================================
echo "6. Health Check\n";
echo str_repeat("─", 62) . "\n";

$health = $cache4->healthCheck();

foreach ($health as $check => $status) {
    $icon = $status ? '✅' : '❌';
    $value = is_bool($status) ? ($status ? 'ДА' : 'НЕТ') : formatBytes($status);
    echo "{$icon} " . str_replace('_', ' ', ucfirst($check)) . ": {$value}\n";
}

echo "\n";

// =============================================================================
echo "7. Метаданные элементов\n";
echo str_repeat("─", 62) . "\n";

$cache4->set('meta_test', 'meta_value', 3600);
$cache4->get('meta_test'); // Увеличиваем hits

$metadata = $cache4->getMetadata('meta_test');

if ($metadata) {
    echo "Метаданные для 'meta_test':\n";
    echo "  Создан: " . date('Y-m-d H:i:s', $metadata['created']) . "\n";
    echo "  Истекает: " . ($metadata['expires'] ? date('Y-m-d H:i:s', $metadata['expires']) : 'никогда') . "\n";
    echo "  Размер: " . formatBytes($metadata['size']) . "\n";
    echo "  Обращений: {$metadata['hits']}\n";
    echo "  Теги: " . (empty($metadata['tags']) ? 'нет' : implode(', ', $metadata['tags'])) . "\n";
} else {
    echo "⚠️  Метаданные не найдены\n";
}

echo "\n";

// =============================================================================
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║                  ТЕСТИРОВАНИЕ ЗАВЕРШЕНО                      ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";

// Вспомогательная функция
function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

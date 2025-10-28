<?php

require_once __DIR__ . '/../autoload.php';

use Cache\FileCache;
use Cache\FileCacheConfig;

echo "=== File Cache Examples ===\n\n";

$config = new FileCacheConfig([
    'cacheDirectory' => __DIR__ . '/cache',
    'useSharding' => true,
    'shardingDepth' => 2,
    'defaultTtl' => 3600,
    'enableStatistics' => true,
    'compressionEnabled' => true,
    'compressionThreshold' => 100,
    'serializer' => 'json',
]);

$cache = new FileCache($config);

echo "1. Basic set/get operations:\n";
$cache->set('user:1', ['name' => 'John Doe', 'email' => 'john@example.com']);
$user = $cache->get('user:1');
echo "User: " . json_encode($user) . "\n\n";

echo "2. Set with TTL (5 seconds):\n";
$cache->set('temp_data', 'This will expire in 5 seconds', 5);
echo "Has temp_data: " . ($cache->has('temp_data') ? 'yes' : 'no') . "\n";
sleep(6);
echo "After 5 seconds - Has temp_data: " . ($cache->has('temp_data') ? 'yes' : 'no') . "\n\n";

echo "3. Multiple operations:\n";
$cache->setMultiple([
    'key1' => 'value1',
    'key2' => 'value2',
    'key3' => 'value3',
]);
$values = $cache->getMultiple(['key1', 'key2', 'key3']);
echo "Multiple values: " . json_encode($values) . "\n\n";

echo "4. Remember pattern:\n";
$expensiveData = $cache->remember('expensive_calculation', function() {
    echo "Computing expensive calculation...\n";
    sleep(1);
    return ['result' => 42, 'computed_at' => date('H:i:s')];
}, 60);
echo "Result: " . json_encode($expensiveData) . "\n";
echo "Second call (from cache):\n";
$expensiveData = $cache->remember('expensive_calculation', function() {
    echo "Computing expensive calculation...\n";
    return ['result' => 42, 'computed_at' => date('H:i:s')];
}, 60);
echo "Result: " . json_encode($expensiveData) . "\n\n";

echo "5. Increment/Decrement:\n";
$cache->set('counter', 10);
echo "Initial counter: " . $cache->get('counter') . "\n";
$cache->increment('counter', 5);
echo "After increment by 5: " . $cache->get('counter') . "\n";
$cache->decrement('counter', 3);
echo "After decrement by 3: " . $cache->get('counter') . "\n\n";

echo "6. Tags:\n";
$cache->tags(['users', 'premium'])->set('user:premium:1', ['name' => 'VIP User']);
$cache->tags(['users', 'free'])->set('user:free:1', ['name' => 'Free User']);
echo "Created tagged cache items\n";
echo "Deleting 'premium' tag...\n";
$cache->deleteByTag('premium');
echo "user:premium:1 exists: " . ($cache->has('user:premium:1') ? 'yes' : 'no') . "\n";
echo "user:free:1 exists: " . ($cache->has('user:free:1') ? 'yes' : 'no') . "\n\n";

echo "7. Pull (get and delete):\n";
$cache->set('one_time_token', 'secret123');
$token = $cache->pull('one_time_token');
echo "Token: $token\n";
echo "Token still exists: " . ($cache->has('one_time_token') ? 'yes' : 'no') . "\n\n";

echo "8. Metadata:\n";
$cache->set('metadata_test', 'some data');
$cache->get('metadata_test');
$cache->get('metadata_test');
$metadata = $cache->getMetadata('metadata_test');
echo "Metadata: " . json_encode($metadata, JSON_PRETTY_PRINT) . "\n\n";

echo "9. Statistics:\n";
$stats = $cache->getStats();
echo "Cache statistics: " . json_encode($stats, JSON_PRETTY_PRINT) . "\n\n";

echo "10. Health check:\n";
$health = $cache->healthCheck();
echo "Health status: " . json_encode($health, JSON_PRETTY_PRINT) . "\n\n";

echo "11. Garbage collection:\n";
$cache->set('expire1', 'data1', 1);
$cache->set('expire2', 'data2', 1);
sleep(2);
$deleted = $cache->gc(true);
echo "Deleted expired items: $deleted\n\n";

echo "12. Cache size and item count:\n";
echo "Cache size: " . $cache->getSize() . " bytes\n";
echo "Item count: " . $cache->getItemCount() . "\n\n";

echo "13. Touch (extend TTL):\n";
$cache->set('touchable', 'data', 10);
$metaBefore = $cache->getMetadata('touchable');
echo "Expires before touch: " . date('H:i:s', $metaBefore['expires']) . "\n";
sleep(2);
$cache->touch('touchable', 20);
$metaAfter = $cache->getMetadata('touchable');
echo "Expires after touch: " . date('H:i:s', $metaAfter['expires']) . "\n\n";

echo "Cleaning up...\n";
$cache->clear();
echo "Cache cleared.\n";

echo "\n=== Examples completed ===\n";

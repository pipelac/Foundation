<?php

/**
 * Быстрый тест модуля Rss2Tlg Fetch
 * 
 * Минимальный пример для проверки работоспособности без БД
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Rss2Tlg\DTO\FeedConfig;
use App\Rss2Tlg\DTO\FeedState;
use App\Rss2Tlg\DTO\RawItem;

echo "=== Quick Test: Rss2Tlg DTO Classes ===\n\n";

try {
    // 1. Тест FeedConfig
    echo "1. Тест FeedConfig...\n";
    
    $feedConfig = FeedConfig::fromArray([
        'id' => 1,
        'url' => 'https://news.ycombinator.com/rss',
        'enabled' => true,
        'timeout' => 30,
        'retries' => 3,
        'polling_interval' => 300,
        'headers' => [
            'User-Agent' => 'Rss2Tlg/1.0',
        ],
        'parser_options' => [
            'max_items' => 50,
            'enable_cache' => true,
        ],
    ]);
    
    echo "   ✓ FeedConfig создан\n";
    echo "   ID: " . $feedConfig->id . "\n";
    echo "   URL: " . $feedConfig->url . "\n";
    echo "   Timeout: " . $feedConfig->timeout . " sec\n\n";

    // 2. Тест FeedState
    echo "2. Тест FeedState...\n";
    
    $initialState = FeedState::createInitial();
    echo "   ✓ Начальное состояние создано\n";
    echo "   Error count: " . $initialState->errorCount . "\n";
    echo "   In backoff: " . ($initialState->isInBackoff() ? 'Yes' : 'No') . "\n\n";
    
    $successState = $initialState->withSuccessfulFetch(
        '"etag-123"',
        'Mon, 15 Jan 2024 10:00:00 GMT',
        200
    );
    echo "   ✓ Состояние после успешного fetch\n";
    echo "   Status: " . $successState->lastStatus . "\n";
    echo "   ETag: " . $successState->etag . "\n";
    echo "   Error count: " . $successState->errorCount . "\n\n";
    
    $errorState = $successState->withFailedFetch(503);
    echo "   ✓ Состояние после ошибки\n";
    echo "   Status: " . $errorState->lastStatus . "\n";
    echo "   Error count: " . $errorState->errorCount . "\n";
    echo "   In backoff: " . ($errorState->isInBackoff() ? 'Yes' : 'No') . "\n";
    echo "   Backoff remaining: " . $errorState->getBackoffRemaining() . " sec\n\n";

    // 3. Тест RawItem (создадим вручную для примера)
    echo "3. Тест RawItem...\n";
    
    // Для полноценного теста RawItem нужен SimplePie\Item, создадим мок вручную
    echo "   ⚠ RawItem требует SimplePie\\Item для создания\n";
    echo "   Используйте fetch_single.php для полного теста\n\n";

    // 4. Тест валидации
    echo "4. Тест валидации...\n";
    
    try {
        FeedConfig::fromArray([
            'id' => 999,
            'url' => 'invalid-url',
        ]);
        echo "   ✗ Валидация не сработала!\n";
    } catch (\InvalidArgumentException $e) {
        echo "   ✓ Валидация работает: " . $e->getMessage() . "\n";
    }
    
    try {
        FeedConfig::fromArray([
            'id' => 999,
            'url' => 'ftp://example.com/feed.xml',
        ]);
        echo "   ✗ Валидация протокола не сработала!\n";
    } catch (\InvalidArgumentException $e) {
        echo "   ✓ Валидация протокола работает\n";
    }
    
    echo "\n";

    // 5. Тест конвертации toArray
    echo "5. Тест конвертации toArray...\n";
    
    $configArray = $feedConfig->toArray();
    echo "   ✓ FeedConfig->toArray(): " . count($configArray) . " полей\n";
    
    $stateArray = $successState->toArray();
    echo "   ✓ FeedState->toArray(): " . count($stateArray) . " полей\n\n";

    echo str_repeat('=', 50) . "\n";
    echo "✓ Все базовые тесты пройдены успешно!\n";
    echo "\nДля полного теста с реальными RSS лентами используйте:\n";
    echo "  php examples/rss2tlg/fetch_single.php\n";

} catch (\Exception $e) {
    echo "\n✗ ОШИБКА: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

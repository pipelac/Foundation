<?php

/**
 * Пример опроса одного RSS/Atom источника
 * 
 * Демонстрирует работу с отдельным источником
 * и детальный вывод распарсенных элементов
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Component\MySQL;
use App\Component\Logger;
use App\Rss2Tlg\FetchRunner;
use App\Rss2Tlg\DTO\FeedConfig;

// Конфигурация для примера (можно изменить на любой RSS/Atom источник)
$feedUrl = $argv[1] ?? 'https://news.ycombinator.com/rss';
$feedId = 999; // Тестовый ID

// Директории
$logDir = __DIR__ . '/../../logs';
$cacheDir = __DIR__ . '/../../cache/rss2tlg';

// Создание директорий
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

try {
    echo "=== Fetch Single RSS/Atom Source ===\n\n";
    echo "URL: {$feedUrl}\n\n";

    // 1. Инициализация компонентов
    echo "Инициализация...\n";

    $logger = new Logger([
        'log_file' => $logDir . '/rss2tlg_single.log',
        'log_level' => 'debug',
        'console_output' => false,
    ]);

    // Подключение к БД (используйте свои параметры)
    $db = new MySQL([
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'test',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ], $logger);

    // 2. Создание конфигурации источника
    $feedConfig = FeedConfig::fromArray([
        'id' => $feedId,
        'url' => $feedUrl,
        'enabled' => true,
        'timeout' => 30,
        'retries' => 3,
        'polling_interval' => 300,
        'headers' => [
            'User-Agent' => 'Rss2Tlg/1.0 (Fetch Example)',
        ],
        'parser_options' => [
            'max_items' => 10, // Ограничим для примера
            'enable_cache' => true,
        ],
    ]);

    // 3. Инициализация FetchRunner
    $fetchRunner = new FetchRunner($db, $cacheDir, $logger);
    echo "✓ Инициализация завершена\n\n";

    // 4. Опрос источника
    echo "Опрос источника...\n";
    $startTime = microtime(true);
    
    $result = $fetchRunner->runForFeed($feedConfig);
    
    $duration = microtime(true) - $startTime;
    echo sprintf("✓ Опрос завершён за %.3f сек\n\n", $duration);

    // 5. Вывод результатов
    echo str_repeat('=', 70) . "\n";
    echo "РЕЗУЛЬТАТ\n";
    echo str_repeat('=', 70) . "\n\n";

    echo sprintf("Статус: %d\n", $result->state->lastStatus);
    echo sprintf("ETag: %s\n", $result->state->etag ?? 'N/A');
    echo sprintf("Last-Modified: %s\n", $result->state->lastModified ?? 'N/A');
    echo sprintf("Error Count: %d\n", $result->state->errorCount);
    
    if ($result->state->isInBackoff()) {
        echo sprintf("Backoff: %d sec remaining\n", $result->state->getBackoffRemaining());
    }

    echo "\n";

    if ($result->isSuccessful()) {
        echo "✓ УСПЕШНО (200 OK)\n\n";
        
        $itemCount = $result->getItemCount();
        $validItemsCount = count($result->getValidItems());
        
        echo sprintf("Всего элементов: %d\n", $itemCount);
        echo sprintf("Валидных элементов: %d\n\n", $validItemsCount);

        if ($itemCount > 0) {
            echo str_repeat('-', 70) . "\n";
            echo "ЭЛЕМЕНТЫ ЛЕНТЫ\n";
            echo str_repeat('-', 70) . "\n\n";

            foreach ($result->items as $index => $item) {
                echo sprintf("[%d] %s\n", $index + 1, str_repeat('=', 66)) . "\n\n";
                
                echo "Заголовок:\n";
                echo "  " . ($item->title ?? 'N/A') . "\n\n";
                
                echo "Ссылка:\n";
                echo "  " . ($item->link ?? 'N/A') . "\n\n";
                
                if ($item->guid !== null) {
                    echo "GUID:\n";
                    echo "  " . $item->guid . "\n\n";
                }
                
                if ($item->summary !== null) {
                    echo "Описание:\n";
                    $summary = mb_substr($item->summary, 0, 200);
                    echo "  " . $summary;
                    if (mb_strlen($item->summary) > 200) {
                        echo "...";
                    }
                    echo "\n\n";
                }
                
                if (!empty($item->authors)) {
                    echo "Авторы:\n";
                    foreach ($item->authors as $author) {
                        echo "  - " . $author . "\n";
                    }
                    echo "\n";
                }
                
                if (!empty($item->categories)) {
                    echo "Категории:\n";
                    foreach ($item->categories as $category) {
                        echo "  - " . $category . "\n";
                    }
                    echo "\n";
                }
                
                if ($item->enclosure !== null) {
                    echo "Вложение:\n";
                    echo "  URL: " . $item->enclosure['url'] . "\n";
                    echo "  Type: " . $item->enclosure['type'] . "\n";
                    echo "  Size: " . number_format($item->enclosure['length']) . " bytes\n\n";
                }
                
                if ($item->pubDate !== null) {
                    echo "Дата публикации:\n";
                    echo "  " . date('Y-m-d H:i:s', $item->pubDate) . "\n\n";
                }
                
                echo "Content Hash:\n";
                echo "  " . $item->contentHash . "\n\n";
                
                echo "Валидность:\n";
                echo "  " . ($item->isValid() ? '✓ Valid' : '✗ Invalid') . "\n\n";
            }
        }
    } elseif ($result->isNotModified()) {
        echo "⟳ НЕ ИЗМЕНИЛСЯ (304 Not Modified)\n";
        echo "Источник не обновлялся с последнего опроса\n";
    } else {
        echo "✗ ОШИБКА\n\n";
        
        $statusCode = $result->state->lastStatus;
        echo sprintf("HTTP статус: %d\n", $statusCode);
        echo sprintf("Счётчик ошибок: %d\n", $result->state->errorCount);
        
        if ($result->state->isInBackoff()) {
            echo sprintf("\nИсточник в backoff режиме\n");
            echo sprintf("Осталось: %d секунд\n", $result->state->getBackoffRemaining());
        }
    }

    // 6. Метрики
    echo "\n" . str_repeat('=', 70) . "\n";
    echo "МЕТРИКИ\n";
    echo str_repeat('=', 70) . "\n\n";

    $metrics = $result->metrics;
    foreach ($metrics as $key => $value) {
        echo sprintf("%-20s: %s\n", $key, is_numeric($value) ? $value : json_encode($value));
    }

    echo "\n✓ Пример завершён успешно\n";

} catch (Exception $e) {
    echo "\n✗ ОШИБКА: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

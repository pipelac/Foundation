<?php

/**
 * Пример использования модуля Rss2Tlg Fetch
 * 
 * Демонстрирует базовую работу с модулем:
 * - Загрузка конфигурации
 * - Инициализация компонентов
 * - Опрос RSS/Atom источников
 * - Обработка результатов
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Component\MySQL;
use App\Component\Logger;
use App\Component\Config\ConfigLoader;
use App\Rss2Tlg\FetchRunner;
use App\Rss2Tlg\DTO\FeedConfig;

// Конфигурационные пути
$configPath = __DIR__ . '/../../config/rss2tlg.json';
$logDir = __DIR__ . '/../../logs';
$cacheDir = __DIR__ . '/../../cache/rss2tlg';

// Создание необходимых директорий
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

try {
    echo "=== Rss2Tlg Fetch Example ===\n\n";

    // 1. Загрузка конфигурации
    echo "1. Загрузка конфигурации...\n";
    
    if (!file_exists($configPath)) {
        throw new Exception("Файл конфигурации не найден: {$configPath}\n" .
            "Создайте конфиг на основе примера:\n" .
            "cp src/Rss2Tlg/docs/config.example.json config/rss2tlg.json");
    }
    
    $config = ConfigLoader::load($configPath);
    echo "   ✓ Конфигурация загружена\n\n";

    // 2. Инициализация логгера
    echo "2. Инициализация логгера...\n";
    $logger = new Logger([
        'log_file' => $logDir . '/rss2tlg_fetch.log',
        'log_level' => $config['logging']['level'] ?? 'info',
        'console_output' => true,
    ]);
    echo "   ✓ Логгер инициализирован\n\n";

    // 3. Подключение к БД
    echo "3. Подключение к БД...\n";
    $db = new MySQL($config['database'], $logger);
    echo "   ✓ Подключено к " . $config['database']['database'] . "\n\n";

    // 4. Парсинг конфигурации источников
    echo "4. Парсинг конфигурации источников...\n";
    
    if (!isset($config['feeds']) || !is_array($config['feeds'])) {
        throw new Exception("Секция 'feeds' не найдена в конфигурации");
    }

    $feeds = [];
    foreach ($config['feeds'] as $feedData) {
        try {
            $feedConfig = FeedConfig::fromArray($feedData);
            $feeds[] = $feedConfig;
            
            echo sprintf(
                "   ✓ Feed #%d: %s (%s)\n",
                $feedConfig->id,
                parse_url($feedConfig->url, PHP_URL_HOST),
                $feedConfig->enabled ? 'enabled' : 'disabled'
            );
        } catch (Exception $e) {
            echo "   ✗ Ошибка парсинга feed: " . $e->getMessage() . "\n";
        }
    }
    
    echo sprintf("\n   Всего источников: %d\n\n", count($feeds));

    // 5. Инициализация FetchRunner
    echo "5. Инициализация FetchRunner...\n";
    $fetchRunner = new FetchRunner($db, $cacheDir, $logger);
    echo "   ✓ FetchRunner готов к работе\n\n";

    // 6. Опрос источников
    echo "6. Опрос RSS/Atom источников...\n";
    echo str_repeat('-', 60) . "\n\n";
    
    $results = $fetchRunner->runForAllFeeds($feeds);

    // 7. Обработка результатов
    echo "\n" . str_repeat('-', 60) . "\n";
    echo "7. Результаты опроса:\n\n";

    $successCount = 0;
    $notModifiedCount = 0;
    $errorCount = 0;
    $totalItems = 0;

    foreach ($results as $feedId => $result) {
        $feedUrl = '';
        foreach ($feeds as $feed) {
            if ($feed->id === $feedId) {
                $feedUrl = parse_url($feed->url, PHP_URL_HOST);
                break;
            }
        }

        echo sprintf("Feed #%d (%s):\n", $feedId, $feedUrl);

        if ($result->isSuccessful()) {
            $successCount++;
            $itemCount = $result->getItemCount();
            $totalItems += $itemCount;
            $validItemsCount = count($result->getValidItems());

            echo sprintf(
                "  ✓ SUCCESS (200 OK)\n" .
                "    - Items: %d (valid: %d)\n" .
                "    - Duration: %.3f sec\n" .
                "    - Body size: %s\n",
                $itemCount,
                $validItemsCount,
                $result->getMetric('duration', 0),
                $result->getMetric('body_size') 
                    ? number_format($result->getMetric('body_size')) . ' bytes'
                    : 'N/A'
            );

            // Показываем первые 3 элемента
            if ($itemCount > 0) {
                echo "    - Последние элементы:\n";
                $displayItems = array_slice($result->items, 0, 3);
                foreach ($displayItems as $item) {
                    $title = mb_substr($item->title ?? 'No title', 0, 60);
                    echo "      • {$title}\n";
                }
                if ($itemCount > 3) {
                    echo sprintf("      ... и ещё %d элементов\n", $itemCount - 3);
                }
            }
        } elseif ($result->isNotModified()) {
            $notModifiedCount++;
            echo sprintf(
                "  ⟳ NOT MODIFIED (304)\n" .
                "    - Duration: %.3f sec\n" .
                "    - Источник не изменился\n",
                $result->getMetric('duration', 0)
            );
        } else {
            $errorCount++;
            $statusCode = $result->state->lastStatus;
            $statusText = match (true) {
                $statusCode === 0 => 'Network Error',
                $statusCode >= 500 => 'Server Error',
                $statusCode >= 400 => 'Client Error',
                default => 'Unknown Error',
            };

            echo sprintf(
                "  ✗ ERROR (%d %s)\n" .
                "    - Error count: %d\n" .
                "    - Duration: %.3f sec\n",
                $statusCode,
                $statusText,
                $result->state->errorCount,
                $result->getMetric('duration', 0)
            );

            if ($result->state->isInBackoff()) {
                echo sprintf(
                    "    - Backoff: %d sec remaining\n",
                    $result->state->getBackoffRemaining()
                );
            }
        }

        echo "\n";
    }

    // 8. Метрики
    echo str_repeat('=', 60) . "\n";
    echo "8. Общие метрики:\n\n";

    $metrics = $fetchRunner->getMetrics();
    
    echo sprintf("Всего запросов:      %d\n", $metrics['fetch_total']);
    echo sprintf("  ✓ Успешно (200):   %d\n", $metrics['fetch_200']);
    echo sprintf("  ⟳ Not Modified (304): %d\n", $metrics['fetch_304']);
    echo sprintf("  ✗ Ошибки:          %d\n", $metrics['fetch_errors']);
    echo sprintf("  ✗ Ошибки парсинга: %d\n", $metrics['parse_errors']);
    echo sprintf("\nЭлементов извлечено: %d\n", $metrics['items_parsed']);
    
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "✓ Пример успешно завершён\n";

} catch (Exception $e) {
    echo "\n✗ ОШИБКА: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

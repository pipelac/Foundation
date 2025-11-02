<?php

/**
 * Реальное тестирование Rss2Tlg с живыми RSS лентами
 * 
 * Выполняет:
 * - Загрузку реальных RSS лент
 * - Парсинг новостей
 * - Публикацию в Telegram канал
 * - Отправку уведомлений о ходе теста в бот
 * - Логирование всех операций
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Component\MySQL;
use App\Component\Logger;
use App\Component\Telegram;
use App\Rss2Tlg\FetchRunner;
use App\Rss2Tlg\DTO\FeedConfig;

// Конфигурация
$testConfig = [
    'bot_token' => '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI',
    'notification_chat_id' => '366442475',
    'channel_id' => '366442475', // Используем личный чат вместо канала для теста
    'feeds' => [
        [
            'id' => 1,
            'url' => 'https://ria.ru/export/rss2/index.xml?page_type=google_newsstand',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => [],
            'parser_options' => [
                'max_items' => 10,
                'enable_cache' => true,
            ],
        ],
        [
            'id' => 2,
            'url' => 'https://arstechnica.com/ai/feed',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => [],
            'parser_options' => [
                'max_items' => 10,
                'enable_cache' => true,
            ],
        ],
        [
            'id' => 3,
            'url' => 'https://techcrunch.com/startups/feed',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => [],
            'parser_options' => [
                'max_items' => 10,
                'enable_cache' => true,
            ],
        ],
    ],
];

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

// Глобальные переменные для отслеживания прогресса
$testStartTime = microtime(true);
$telegram = null;
$logger = null;

/**
 * Отправляет уведомление в Telegram бот
 */
function sendNotification(string $message, bool $isError = false): void
{
    global $telegram, $testConfig;
    
    if ($telegram === null) {
        return;
    }
    
    try {
        $emoji = $isError ? '❌' : '✅';
        $text = "{$emoji} <b>RSS2TLG Test</b>\n\n{$message}";
        
        $telegram->sendText(
            $testConfig['notification_chat_id'],
            $text,
            ['parse_mode' => Telegram::PARSE_MODE_HTML]
        );
    } catch (Exception $e) {
        echo "⚠️  Ошибка отправки уведомления: " . $e->getMessage() . "\n";
    }
}

/**
 * Публикует новость в Telegram канал
 */
function publishToChannel(string $title, string $link, ?string $summary = null): void
{
    global $telegram, $testConfig;
    
    if ($telegram === null) {
        return;
    }
    
    try {
        // Формируем сообщение
        $text = "<b>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</b>\n\n";
        
        if ($summary !== null && trim($summary) !== '') {
            $shortSummary = mb_substr($summary, 0, 300, 'UTF-8');
            if (mb_strlen($summary, 'UTF-8') > 300) {
                $shortSummary .= '...';
            }
            $text .= htmlspecialchars($shortSummary, ENT_QUOTES, 'UTF-8') . "\n\n";
        }
        
        $text .= "🔗 <a href=\"" . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . "\">Читать далее</a>";
        
        $telegram->sendText(
            $testConfig['channel_id'],
            $text,
            [
                'parse_mode' => Telegram::PARSE_MODE_HTML,
                'disable_web_page_preview' => false,
            ]
        );
        
        // Небольшая задержка между публикациями
        usleep(500000); // 0.5 секунды
        
    } catch (Exception $e) {
        echo "⚠️  Ошибка публикации в канал: " . $e->getMessage() . "\n";
    }
}

try {
    echo "\n";
    echo "╔══════════════════════════════════════════════════════════════╗\n";
    echo "║        RSS2TLG REAL FEED TEST WITH TELEGRAM PUBLISHING       ║\n";
    echo "╚══════════════════════════════════════════════════════════════╝\n";
    echo "\n";
    
    // 1. Инициализация Telegram
    echo "📱 1. Инициализация Telegram клиента...\n";
    $telegram = new Telegram([
        'token' => $testConfig['bot_token'],
        'timeout' => 30,
        'retries' => 3,
    ]);
    
    // Проверка бота
    $botInfo = $telegram->getMe();
    echo "   ✓ Бот: @" . $botInfo['result']['username'] . "\n";
    echo "   ✓ Канал: " . $testConfig['channel_id'] . "\n\n";
    
    sendNotification("🚀 Начало тестирования RSS2TLG\n\n" .
        "📊 Источников: " . count($testConfig['feeds']) . "\n" .
        "📝 Канал публикации: " . $testConfig['channel_id']);
    
    // 2. Инициализация логгера
    echo "📝 2. Инициализация логгера...\n";
    $logger = new Logger([
        'directory' => $logDir,
        'file_name' => 'rss2tlg_test.log',
        'log_level' => 'debug',
        'console_output' => true,
    ]);
    echo "   ✓ Логи: " . $logDir . "/rss2tlg_test.log\n\n";
    
    // 3. Проверка MySQL
    echo "💾 3. Проверка MySQL сервера...\n";
    sendNotification("💾 Подключение к MySQL...");
    
    $dbConfig = [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'utilities_db',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ];
    
    $db = new MySQL($dbConfig, $logger);
    echo "   ✓ Подключено к БД: " . $dbConfig['database'] . "\n\n";
    
    // 4. Создание таблицы
    echo "🗄️  4. Создание таблицы rss2tlg_feed_state...\n";
    $createTableSql = "
        CREATE TABLE IF NOT EXISTS rss2tlg_feed_state (
            feed_id INT PRIMARY KEY,
            url VARCHAR(512) NOT NULL,
            etag VARCHAR(255) DEFAULT NULL,
            last_modified VARCHAR(255) DEFAULT NULL,
            last_status INT DEFAULT 0,
            error_count INT DEFAULT 0,
            backoff_until DATETIME DEFAULT NULL,
            fetched_at DATETIME DEFAULT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_url (url(255)),
            INDEX idx_backoff (backoff_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->execute($createTableSql);
    echo "   ✓ Таблица готова\n\n";
    
    // 5. Парсинг конфигурации источников
    echo "📰 5. Подготовка RSS источников...\n";
    sendNotification("📰 Загрузка конфигурации источников:\n\n" .
        "1. РИА Новости\n" .
        "2. Ars Technica AI\n" .
        "3. TechCrunch Startups");
    
    $feeds = [];
    foreach ($testConfig['feeds'] as $feedData) {
        $feedConfig = FeedConfig::fromArray($feedData);
        $feeds[] = $feedConfig;
        
        $domain = parse_url($feedConfig->url, PHP_URL_HOST);
        echo sprintf("   ✓ Feed #%d: %s\n", $feedConfig->id, $domain);
    }
    echo "\n";
    
    // 6. Инициализация FetchRunner
    echo "⚙️  6. Инициализация FetchRunner...\n";
    $fetchRunner = new FetchRunner($db, $cacheDir, $logger);
    echo "   ✓ FetchRunner готов\n\n";
    
    // 7. Опрос источников
    echo "🔄 7. Опрос RSS/Atom источников...\n";
    echo str_repeat('─', 64) . "\n\n";
    
    sendNotification("🔄 Начало опроса RSS лент...");
    
    $results = $fetchRunner->runForAllFeeds($feeds);
    
    // 8. Обработка результатов и публикация
    echo "\n" . str_repeat('─', 64) . "\n";
    echo "📊 8. Обработка результатов и публикация...\n\n";
    
    $successCount = 0;
    $notModifiedCount = 0;
    $errorCount = 0;
    $totalItems = 0;
    $publishedItems = 0;
    
    foreach ($results as $feedId => $result) {
        $feedUrl = '';
        foreach ($feeds as $feed) {
            if ($feed->id === $feedId) {
                $feedUrl = parse_url($feed->url, PHP_URL_HOST) ?? $feed->url;
                break;
            }
        }
        
        echo sprintf("📌 Feed #%d: %s\n", $feedId, $feedUrl);
        
        if ($result->isSuccessful()) {
            $successCount++;
            $itemCount = $result->getItemCount();
            $totalItems += $itemCount;
            $validItems = $result->getValidItems();
            
            echo sprintf(
                "   ✅ SUCCESS (200 OK)\n" .
                "   📦 Items: %d (valid: %d)\n" .
                "   ⏱️  Duration: %.2f sec\n" .
                "   📏 Body size: %s\n",
                $itemCount,
                count($validItems),
                $result->getMetric('duration', 0),
                number_format($result->getMetric('body_size', 0)) . ' bytes'
            );
            
            // Публикуем первую новость для теста
            if (!empty($validItems)) {
                echo "   📤 Публикация в личный чат (тест):\n";
                $publishLimit = min(1, count($validItems));
                
                for ($i = 0; $i < $publishLimit; $i++) {
                    $item = $validItems[$i];
                    
                    $title = $item->title ?? 'Без заголовка';
                    $link = $item->link ?? '';
                    $summary = $item->summary ?? $item->content;
                    
                    if ($link !== '') {
                        echo sprintf("      • %s\n", mb_substr($title, 0, 60));
                        publishToChannel($title, $link, $summary);
                        $publishedItems++;
                    }
                }
                
                if ($itemCount > $publishLimit) {
                    echo sprintf("      ... и ещё %d новостей\n", $itemCount - $publishLimit);
                }
            }
            
        } elseif ($result->isNotModified()) {
            $notModifiedCount++;
            echo sprintf(
                "   ⟳ NOT MODIFIED (304)\n" .
                "   ⏱️  Duration: %.2f sec\n" .
                "   ℹ️  Источник не изменился\n",
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
                "   ❌ ERROR (%d %s)\n" .
                "   🔢 Error count: %d\n" .
                "   ⏱️  Duration: %.2f sec\n",
                $statusCode,
                $statusText,
                $result->state->errorCount,
                $result->getMetric('duration', 0)
            );
            
            if ($result->state->isInBackoff()) {
                echo sprintf(
                    "   ⏰ Backoff: %d sec remaining\n",
                    $result->state->getBackoffRemaining()
                );
            }
        }
        
        echo "\n";
    }
    
    // 9. Итоговая статистика
    $totalDuration = microtime(true) - $testStartTime;
    
    echo str_repeat('═', 64) . "\n";
    echo "📈 9. ИТОГОВАЯ СТАТИСТИКА\n\n";
    
    $metrics = $fetchRunner->getMetrics();
    
    echo sprintf("⏱️  Время выполнения: %.2f sec\n\n", $totalDuration);
    echo sprintf("📡 Всего запросов:      %d\n", $metrics['fetch_total']);
    echo sprintf("   ✅ Успешно (200):    %d\n", $metrics['fetch_200']);
    echo sprintf("   ⟳  Not Modified (304): %d\n", $metrics['fetch_304']);
    echo sprintf("   ❌ Ошибки:           %d\n", $metrics['fetch_errors']);
    echo sprintf("   ❌ Ошибки парсинга:  %d\n\n", $metrics['parse_errors']);
    
    echo sprintf("📰 Новостей получено:   %d\n", $metrics['items_parsed']);
    echo sprintf("📤 Опубликовано:        %d\n", $publishedItems);
    
    echo "\n" . str_repeat('═', 64) . "\n";
    echo "✅ ТЕСТИРОВАНИЕ УСПЕШНО ЗАВЕРШЕНО!\n\n";
    
    // Отправляем итоговое уведомление
    $summaryMessage = "✅ <b>Тестирование завершено!</b>\n\n" .
        "⏱️ Время: " . round($totalDuration, 2) . " сек\n\n" .
        "📊 <b>Результаты:</b>\n" .
        "✅ Успешно: {$metrics['fetch_200']}\n" .
        "⟳ Not Modified: {$metrics['fetch_304']}\n" .
        "❌ Ошибки: {$metrics['fetch_errors']}\n\n" .
        "📰 Новостей получено: {$metrics['items_parsed']}\n" .
        "📤 Опубликовано: {$publishedItems}\n\n" .
        "🎯 Канал: " . $testConfig['channel_id'];
    
    sendNotification($summaryMessage);
    
} catch (Exception $e) {
    $errorMessage = "❌ ОШИБКА: " . $e->getMessage();
    echo "\n{$errorMessage}\n";
    echo "📍 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "📜 Trace:\n" . $e->getTraceAsString() . "\n";
    
    if ($telegram !== null) {
        sendNotification(
            "<b>Ошибка тестирования!</b>\n\n" .
            "❌ " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "\n\n" .
            "📍 " . basename($e->getFile()) . ":" . $e->getLine(),
            true
        );
    }
    
    exit(1);
}

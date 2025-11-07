<?php

/**
 * Production-ready пример использования Rss2Tlg
 * 
 * Демонстрирует полноценную настройку для продакшен окружения:
 * - Загрузка конфигурации из JSON
 * - Подключение к MySQL с обработкой ошибок
 * - Опрос RSS источников с Conditional GET
 * - Публикация новостей в Telegram канал/чат
 * - Логирование всех операций
 * - Обработка исключений
 * 
 * Использование:
 * php examples/rss2tlg/production_example.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Component\MySQL;
use App\Component\Logger;
use App\Component\Telegram;
use App\Rss2Tlg\FetchRunner;
use App\Rss2Tlg\DTO\FeedConfig;

try {
    // ========================================================================
    // 1. КОНФИГУРАЦИЯ
    // ========================================================================
    
    echo "🚀 RSS2TLG Production Example\n\n";
    
    // Конфигурация RSS источников
    $feedsConfig = [
        [
            'id' => 1,
            'url' => 'https://ria.ru/export/rss2/index.xml',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300, // 5 минут
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 RSS Reader',
            ],
            'parser_options' => [
                'max_items' => 20,
                'enable_cache' => true,
                'cache_duration' => 3600,
            ],
        ],
        [
            'id' => 2,
            'url' => 'https://techcrunch.com/feed',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 600, // 10 минут
            'headers' => [],
            'parser_options' => [
                'max_items' => 15,
                'enable_cache' => true,
            ],
        ],
    ];
    
    // Telegram конфигурация
    $telegramConfig = [
        'token' => getenv('TELEGRAM_BOT_TOKEN') ?: 'YOUR_BOT_TOKEN',
        'channel_id' => getenv('TELEGRAM_CHANNEL_ID') ?: '@your_channel',
        'notification_chat_id' => getenv('TELEGRAM_ADMIN_CHAT_ID') ?: null,
    ];
    
    // MySQL конфигурация
    $dbConfig = [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int)(getenv('DB_PORT') ?: 3306),
        'database' => getenv('DB_NAME') ?: 'utilities_db',
        'username' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset' => 'utf8mb4',
        'persistent' => false,
        'cache_statements' => true,
    ];
    
    // Директории
    $logDir = __DIR__ . '/../../logs';
    $cacheDir = __DIR__ . '/../../cache/rss2tlg';
    
    // Создание директорий
    foreach ([$logDir, $cacheDir] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    
    // ========================================================================
    // 2. ИНИЦИАЛИЗАЦИЯ КОМПОНЕНТОВ
    // ========================================================================
    
    echo "📝 Инициализация компонентов...\n";
    
    // Logger
    $logger = new Logger([
        'directory' => $logDir,
        'file_name' => 'rss2tlg.log',
        'log_level' => 'info',
        'console_output' => true,
    ]);
    
    // MySQL
    $db = new MySQL($dbConfig, $logger);
    
    // Создание таблицы состояний (если не существует)
    $db->execute("
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
    ");
    
    // Telegram
    $telegram = new Telegram([
        'token' => $telegramConfig['token'],
        'timeout' => 30,
        'retries' => 3,
    ], $logger);
    
    // FetchRunner
    $fetchRunner = new FetchRunner($db, $cacheDir, $logger);
    
    echo "✓ Компоненты инициализированы\n\n";
    
    // ========================================================================
    // 3. ПАРСИНГ КОНФИГУРАЦИИ ИСТОЧНИКОВ
    // ========================================================================
    
    echo "📰 Загрузка конфигурации источников...\n";
    
    $feeds = [];
    foreach ($feedsConfig as $feedData) {
        try {
            $feedConfig = FeedConfig::fromArray($feedData);
            $feeds[] = $feedConfig;
            
            echo sprintf(
                "  ✓ Feed #%d: %s\n",
                $feedConfig->id,
                parse_url($feedConfig->url, PHP_URL_HOST)
            );
        } catch (Exception $e) {
            echo "  ✗ Ошибка парсинга feed: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n";
    
    // ========================================================================
    // 4. ОПРОС RSS ИСТОЧНИКОВ
    // ========================================================================
    
    echo "🔄 Опрос RSS источников...\n";
    
    $results = $fetchRunner->runForAllFeeds($feeds);
    
    // ========================================================================
    // 5. ОБРАБОТКА РЕЗУЛЬТАТОВ И ПУБЛИКАЦИЯ
    // ========================================================================
    
    echo "\n📊 Обработка результатов...\n";
    
    $publishedCount = 0;
    $totalItems = 0;
    
    foreach ($results as $feedId => $result) {
        $feedUrl = '';
        foreach ($feeds as $feed) {
            if ($feed->id === $feedId) {
                $feedUrl = parse_url($feed->url, PHP_URL_HOST) ?? $feed->url;
                break;
            }
        }
        
        echo sprintf("\nFeed #%d (%s):\n", $feedId, $feedUrl);
        
        if ($result->isSuccessful()) {
            $items = $result->getValidItems();
            $totalItems += count($items);
            
            echo sprintf("  ✓ Получено %d новых элементов\n", count($items));
            
            // Публикуем в Telegram
            foreach ($items as $item) {
                try {
                    $title = $item->title ?? 'Без заголовка';
                    $link = $item->link ?? '';
                    $summary = $item->summary ?? $item->content;
                    
                    if ($link === '') {
                        continue;
                    }
                    
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
                    
                    // Отправляем в канал
                    $telegram->sendText(
                        $telegramConfig['channel_id'],
                        $text,
                        [
                            'parse_mode' => Telegram::PARSE_MODE_HTML,
                            'disable_web_page_preview' => false,
                        ]
                    );
                    
                    $publishedCount++;
                    
                    // Задержка между публикациями
                    usleep(500000); // 0.5 секунды
                    
                } catch (Exception $e) {
                    $logger->error('Ошибка публикации в Telegram', [
                        'feed_id' => $feedId,
                        'item_title' => $item->title,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
        } elseif ($result->isNotModified()) {
            echo "  ⟳ Источник не изменился (304)\n";
            
        } else {
            echo sprintf(
                "  ✗ Ошибка (статус %d, попыток: %d)\n",
                $result->state->lastStatus,
                $result->state->errorCount
            );
            
            if ($result->state->isInBackoff()) {
                echo sprintf(
                    "  ⏰ Backoff: %d сек\n",
                    $result->state->getBackoffRemaining()
                );
            }
        }
    }
    
    // ========================================================================
    // 6. ИТОГОВАЯ СТАТИСТИКА
    // ========================================================================
    
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "📈 ИТОГИ\n\n";
    
    $metrics = $fetchRunner->getMetrics();
    
    echo sprintf("📡 Запросов: %d\n", $metrics['fetch_total']);
    echo sprintf("✅ Успешно: %d\n", $metrics['fetch_200']);
    echo sprintf("⟳  Not Modified: %d\n", $metrics['fetch_304']);
    echo sprintf("❌ Ошибок: %d\n", $metrics['fetch_errors']);
    echo sprintf("\n📰 Новостей получено: %d\n", $totalItems);
    echo sprintf("📤 Опубликовано: %d\n", $publishedCount);
    
    // Отправляем уведомление админу (если настроено)
    if ($telegramConfig['notification_chat_id'] !== null) {
        try {
            $notificationText = "✅ <b>RSS2TLG - Опрос завершен</b>\n\n" .
                "📡 Запросов: {$metrics['fetch_total']}\n" .
                "✅ Успешно: {$metrics['fetch_200']}\n" .
                "📰 Новостей: {$totalItems}\n" .
                "📤 Опубликовано: {$publishedCount}";
            
            $telegram->sendText(
                $telegramConfig['notification_chat_id'],
                $notificationText,
                ['parse_mode' => Telegram::PARSE_MODE_HTML]
            );
        } catch (Exception $e) {
            $logger->warning('Не удалось отправить уведомление админу', [
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    echo "\n✓ Завершено успешно!\n";
    
} catch (Exception $e) {
    echo "\n❌ КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage() . "\n";
    echo "📍 " . $e->getFile() . ":" . $e->getLine() . "\n";
    
    if (isset($logger)) {
        $logger->error('Критическая ошибка в production_example', [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }
    
    exit(1);
}

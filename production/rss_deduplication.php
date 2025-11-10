#!/usr/bin/env php
<?php
/**
 * RSS Deduplication Production Script
 * 
 * Проверяет суммаризованные новости на дубликаты с помощью AI анализа.
 * Извлекает из таблицы rss2tlg_summarization успешно обработанные записи,
 * сравнивает их с предыдущими новостями и определяет дубликаты.
 * 
 * Функции:
 * - AI проверка на дубликаты (семантический анализ)
 * - Сравнение сущностей, событий, фактов
 * - Определение процента схожести (0-100)
 * - Решение о публикуемости (can_be_published)
 * - Telegram уведомления о ходе работы
 * - Детальное логирование всех операций
 * 
 * @package Rss2Tlg
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\OpenRouter;
use App\Component\Telegram;
use App\Rss2Tlg\Pipeline\DeduplicationService;

// ============================================================================
// КОНСТАНТЫ
// ============================================================================

const SCRIPT_NAME = 'RSS Deduplication';
const SCRIPT_VERSION = '1.0.0';
const LOG_PREFIX = '[RSS_DEDUPLICATION]';

// PRODUCTION РЕЖИМ: Обработать все новости без ограничения
// TEST РЕЖИМ: Обработать только указанное количество новостей
const TEST_MODE = true; // Установите false для production
const TEST_ITEMS_LIMIT = 10; // Количество новостей для тестового режима

// ============================================================================
// ГЛАВНАЯ ФУНКЦИЯ
// ============================================================================

function main(): void
{
    $startTime = microtime(true);
    $scriptStart = date('Y-m-d H:i:s');
    
    echo "\n";
    echo "╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║        RSS DEDUPLICATION PRODUCTION SCRIPT v1.0.0             ║\n";
    echo "╚═══════════════════════════════════════════════════════════════╝\n";
    echo "🕐 Start: {$scriptStart}\n";
    echo "🔧 Mode: " . (TEST_MODE ? "TEST (limit " . TEST_ITEMS_LIMIT . " items)" : "PRODUCTION (no limit)") . "\n\n";
    
    try {
        // Инициализация компонентов
        $config = loadConfiguration();
        $logger = initLogger($config);
        $db = initDatabase($config, $logger);
        $openRouter = initOpenRouter($config, $logger);
        $telegram = initTelegram($config, $logger);
        
        $logger->info(LOG_PREFIX . ' Script started', [
            'version' => SCRIPT_VERSION,
            'mode' => TEST_MODE ? 'test' : 'production',
            'pid' => getmypid()
        ]);
        
        // Отправка уведомления в Telegram
        sendTelegramNotification(
            $telegram, 
            $logger, 
            "🔍 <b>RSS Deduplication запущен</b>\n" .
            "⏱ Время: {$scriptStart}\n" .
            "🔧 Режим: " . (TEST_MODE ? "TEST" : "PRODUCTION")
        );
        
        // Получение суммаризованных новостей для проверки
        $items = getSummarizedItems($db, $logger);
        
        if (empty($items)) {
            $logger->info(LOG_PREFIX . ' No summarized items to check');
            echo "✅ Нет новостей для проверки на дубликаты\n";
            sendTelegramNotification($telegram, $logger, "✅ Нет новостей для проверки");
            return;
        }
        
        echo "📊 Найдено суммаризованных новостей: " . count($items) . "\n\n";
        $logger->info(LOG_PREFIX . ' Summarized items loaded', ['count' => count($items)]);
        
        // Инициализация сервиса дедупликации
        $dedupConfig = loadDeduplicationConfig();
        $dedupService = new DeduplicationService(
            $db,
            $openRouter,
            $dedupConfig,
            $logger
        );
        
        // Вывод AI моделей
        $models = $dedupConfig['models'] ?? [];
        echo "🚀 AI модели: " . implode(', ', $models) . "\n";
        echo str_repeat('━', 63) . "\n\n";
        
        // Обработка новостей
        $stats = processItems($dedupService, $db, $items, $logger, $telegram);
        
        // Получение метрик
        $metrics = $dedupService->getMetrics();
        
        // Финальная статистика
        $duration = microtime(true) - $startTime;
        displayFinalStats($stats, $metrics, $duration, $scriptStart);
        
        // Telegram уведомление о завершении
        sendFinalNotification($telegram, $logger, $stats, $metrics, $duration);
        
        $logger->info(LOG_PREFIX . ' Script completed', [
            'duration_sec' => round($duration, 2),
            'stats' => $stats
        ]);
        
    } catch (Exception $e) {
        $errorMsg = "❌ Критическая ошибка: " . $e->getMessage();
        echo "\n{$errorMsg}\n";
        echo "📍 Файл: " . $e->getFile() . ":" . $e->getLine() . "\n";
        echo "📋 Trace:\n" . $e->getTraceAsString() . "\n";
        
        if (isset($logger)) {
            $logger->error(LOG_PREFIX . ' Script failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
        
        if (isset($telegram)) {
            sendTelegramNotification(
                $telegram, 
                $logger ?? null, 
                "❌ <b>Ошибка Deduplication</b>\n\n" . $e->getMessage()
            );
        }
        
        exit(1);
    }
}

// ============================================================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// ============================================================================

/**
 * Загрузка конфигурации
 */
function loadConfiguration(): array
{
    $configFiles = [
        'main' => __DIR__ . '/configs/main.json',
        'database' => __DIR__ . '/configs/database.json',
        'telegram' => __DIR__ . '/configs/telegram.json',
        'openrouter' => __DIR__ . '/configs/openrouter.json',
    ];
    
    $config = [];
    
    foreach ($configFiles as $key => $file) {
        if (!file_exists($file)) {
            throw new Exception("Конфигурационный файл не найден: {$file}");
        }
        
        $data = json_decode(file_get_contents($file), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Ошибка парсинга JSON в {$file}: " . json_last_error_msg());
        }
        
        $config[$key] = $data;
    }
    
    return $config;
}

/**
 * Загрузка конфигурации дедупликации
 */
function loadDeduplicationConfig(): array
{
    $configFile = __DIR__ . '/configs/deduplication.json';
    
    if (!file_exists($configFile)) {
        throw new Exception("Конфигурационный файл не найден: {$configFile}");
    }
    
    $config = json_decode(file_get_contents($configFile), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Ошибка парсинга JSON: " . json_last_error_msg());
    }
    
    // Проверка промпта
    if (!empty($config['prompt_file'])) {
        $promptPath = __DIR__ . '/../' . $config['prompt_file'];
        if (!file_exists($promptPath)) {
            throw new Exception("Файл промпта не найден: {$promptPath}");
        }
    }
    
    return $config;
}

/**
 * Инициализация Logger
 */
function initLogger(array $config): Logger
{
    $logConfig = [
        'directory' => __DIR__ . '/../logs',
        'file_name' => 'rss_deduplication.log',
        'min_level' => $config['main']['log_level'] ?? 'INFO',
    ];
    
    // Создание директории если не существует
    if (!is_dir($logConfig['directory'])) {
        mkdir($logConfig['directory'], 0755, true);
    }
    
    $logger = new Logger($logConfig);
    echo "✅ Logger инициализирован\n";
    
    return $logger;
}

/**
 * Инициализация Database
 */
function initDatabase(array $config, Logger $logger): MySQL
{
    $db = new MySQL($config['database'], $logger);
    echo "✅ MariaDB подключен: " . $config['database']['database'] . "\n";
    
    return $db;
}

/**
 * Инициализация OpenRouter
 */
function initOpenRouter(array $config, Logger $logger): OpenRouter
{
    $openRouter = new OpenRouter($config['openrouter'], $logger);
    echo "✅ OpenRouter инициализирован\n";
    
    return $openRouter;
}

/**
 * Инициализация Telegram
 */
function initTelegram(array $config, Logger $logger): Telegram
{
    $telegramConfig = [
        'token' => $config['telegram']['token'],
        'default_chat_id' => (string)$config['telegram']['default_chat_id'],
        'timeout' => $config['telegram']['timeout'] ?? 30,
    ];
    
    $telegram = new Telegram($telegramConfig, $logger);
    echo "✅ Telegram инициализирован\n\n";
    
    return $telegram;
}

/**
 * Получение суммаризованных новостей для проверки
 */
function getSummarizedItems(MySQL $db, Logger $logger): array
{
    $limitClause = TEST_MODE ? ' LIMIT ' . TEST_ITEMS_LIMIT : '';
    
    $query = "
        SELECT 
            s.item_id,
            s.feed_id,
            s.headline,
            i.title as original_title,
            i.pub_date
        FROM rss2tlg_summarization s
        INNER JOIN rss2tlg_items i ON s.item_id = i.id
        WHERE s.status = 'success'
        AND NOT EXISTS (
            SELECT 1 
            FROM rss2tlg_deduplication d 
            WHERE d.item_id = s.item_id
        )
        ORDER BY i.pub_date DESC
        {$limitClause}
    ";
    
    $items = $db->query($query);
    
    $logger->debug(LOG_PREFIX . ' Fetching summarized items', [
        'query' => trim(preg_replace('/\s+/', ' ', $query)),
        'count' => count($items)
    ]);
    
    return $items;
}

/**
 * Обработка новостей
 */
function processItems(
    DeduplicationService $dedupService,
    MySQL $db,
    array $items,
    Logger $logger,
    Telegram $telegram
): array {
    $stats = [
        'total_items' => count($items),
        'processed' => 0,
        'success' => 0,
        'failed' => 0,
        'skipped' => 0,
        'duplicates' => 0,
        'unique' => 0,
        'total_tokens' => 0,
        'cache_hits' => 0,
        'errors' => []
    ];
    
    foreach ($items as $idx => $item) {
        $itemId = (int)$item['item_id'];
        $feedId = (int)$item['feed_id'];
        $headline = $item['headline'] ?? $item['original_title'] ?? 'Без заголовка';
        $pubDate = $item['pub_date'] ?? '';
        
        $num = $idx + 1;
        $total = $stats['total_items'];
        
        echo str_repeat('━', 63) . "\n";
        echo "📰 Новость [{$num}/{$total}]\n";
        echo "🆔 ID: {$itemId} | Feed ID: {$feedId}\n";
        echo "📌 Заголовок: " . mb_substr($headline, 0, 60) . "...\n";
        echo "📅 Дата: {$pubDate}\n";
        
        $stats['processed']++;
        
        $startTime = microtime(true);
        
        try {
            $success = $dedupService->processItem($itemId);
            
            if ($success) {
                $stats['success']++;
                
                // Получение результата дедупликации
                $dedupData = $db->queryOne(
                    "SELECT is_duplicate, similarity_score, duplicate_of_item_id, items_compared 
                     FROM rss2tlg_deduplication 
                     WHERE item_id = :item_id",
                    ['item_id' => $itemId]
                );
                
                if ($dedupData) {
                    $isDup = (bool)$dedupData['is_duplicate'];
                    $similarity = (float)$dedupData['similarity_score'];
                    $compared = (int)$dedupData['items_compared'];
                    
                    if ($isDup) {
                        $stats['duplicates']++;
                        echo "⚠️  ДУБЛИКАТ! Схожесть: {$similarity}%\n";
                        
                        // Отправка уведомления о дубликате
                        sendTelegramNotification(
                            $telegram,
                            $logger,
                            "⚠️ <b>ДУБЛИКАТ [{$num}/{$total}]</b>\n\n" .
                            "📌 {$headline}\n" .
                            "📊 Схожесть: {$similarity}%\n" .
                            "🔍 Сравнено: {$compared} новостей",
                            true // silent
                        );
                    } else {
                        $stats['unique']++;
                        echo "✅ Уникальная новость (схожесть: {$similarity}%)\n";
                        
                        // Отправка уведомления об уникальной новости
                        sendTelegramNotification(
                            $telegram,
                            $logger,
                            "✅ <b>УНИКАЛЬНАЯ [{$num}/{$total}]</b>\n\n" .
                            "📌 {$headline}\n" .
                            "📊 Схожесть: {$similarity}%\n" .
                            "🔍 Сравнено: {$compared} новостей",
                            true // silent
                        );
                    }
                }
                
                $duration = (int)((microtime(true) - $startTime) * 1000);
                echo "✅ Обработано успешно за {$duration}ms\n";
                
            } else {
                $stats['failed']++;
                echo "❌ Ошибка обработки\n";
                
                $logger->error(LOG_PREFIX . ' Item processing failed', ['item_id' => $itemId]);
            }
            
        } catch (Exception $e) {
            $stats['failed']++;
            $stats['errors'][] = [
                'item_id' => $itemId,
                'error' => $e->getMessage()
            ];
            
            echo "❌ Исключение: " . $e->getMessage() . "\n";
            
            $logger->error(LOG_PREFIX . ' Exception during processing', [
                'item_id' => $itemId,
                'error' => $e->getMessage()
            ]);
        }
        
        // Небольшая пауза между запросами
        if ($num < $total) {
            usleep(500000); // 0.5 секунды
        }
    }
    
    return $stats;
}

/**
 * Отображение финальной статистики
 */
function displayFinalStats(array $stats, array $metrics, float $duration, string $scriptStart): void
{
    echo "\n";
    echo "╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║                   ИТОГОВАЯ СТАТИСТИКА                         ║\n";
    echo "╚═══════════════════════════════════════════════════════════════╝\n";
    
    $successRate = $stats['total_items'] > 0 
        ? round(($stats['success'] / $stats['total_items']) * 100, 1) 
        : 0;
    
    echo "⏱️  Время выполнения: " . round($duration, 2) . " сек\n";
    echo "📊 Обработано новостей: " . $stats['processed'] . "\n";
    echo "✅ Успешно: " . $stats['success'] . "\n";
    echo "🆕 Уникальных: " . $stats['unique'] . "\n";
    echo "⚠️  Дубликатов: " . $stats['duplicates'] . "\n";
    echo "❌ Ошибок: " . $stats['failed'] . "\n";
    echo "⏭️  Пропущено: " . $stats['skipped'] . "\n";
    echo "🎯 Успешность: {$successRate}%\n";
    echo "💰 Использовано токенов: " . ($metrics['total_tokens'] ?? 0) . "\n";
    echo "📦 Cache hits: " . ($metrics['cache_hits'] ?? 0) . "\n";
    
    $cacheRate = ($metrics['total_tokens'] ?? 0) > 0
        ? round((($metrics['cache_hits'] ?? 0) / ($metrics['total_tokens'] ?? 1)) * 100, 1)
        : 0;
    echo "📈 Cache rate: {$cacheRate}%\n";
    
    echo "🏁 Завершено: " . date('Y-m-d H:i:s') . "\n";
}

/**
 * Отправка финального уведомления в Telegram
 */
function sendFinalNotification(
    Telegram $telegram,
    Logger $logger,
    array $stats,
    array $metrics,
    float $duration
): void {
    $successRate = $stats['total_items'] > 0 
        ? round(($stats['success'] / $stats['total_items']) * 100, 1) 
        : 0;
    
    $message = "🏁 <b>RSS Deduplication завершен</b>\n\n";
    $message .= "⏱ Время: " . round($duration, 2) . " сек\n";
    $message .= "📊 Обработано: {$stats['success']}/{$stats['processed']}\n";
    $message .= "🆕 Уникальных: {$stats['unique']}\n";
    $message .= "⚠️ Дубликатов: {$stats['duplicates']}\n";
    $message .= "❌ Ошибок: {$stats['failed']}\n";
    $message .= "🎯 Успешность: {$successRate}%\n";
    $message .= "💰 Токенов: " . ($metrics['total_tokens'] ?? 0) . "\n";
    
    sendTelegramNotification($telegram, $logger, $message);
}

/**
 * Отправка уведомления в Telegram
 */
function sendTelegramNotification(
    Telegram $telegram,
    ?Logger $logger,
    string $message,
    bool $silent = false
): void {
    try {
        $telegramConfigFile = __DIR__ . '/configs/telegram.json';
        $telegramConfig = json_decode(file_get_contents($telegramConfigFile), true);
        $chatId = $telegramConfig['default_chat_id'];
        
        $telegram->sendText(
            $chatId,
            $message,
            [
                'parse_mode' => 'HTML',
                'disable_notification' => $silent,
            ]
        );
        
        if ($logger) {
            $logger->debug(LOG_PREFIX . ' Telegram notification sent', [
                'chat_id' => $chatId,
                'message_length' => strlen($message)
            ]);
        }
    } catch (Exception $e) {
        if ($logger) {
            $logger->warning(LOG_PREFIX . ' Failed to send Telegram notification', [
                'error' => $e->getMessage()
            ]);
        }
    }
}

// ============================================================================
// ЗАПУСК СКРИПТА
// ============================================================================

main();

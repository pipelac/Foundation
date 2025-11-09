#!/usr/bin/env php
<?php
/**
 * RSS Summarization Production Script
 * 
 * Обрабатывает сырые RSS данные через AI суммаризацию.
 * Извлекает из таблицы rss2tlg_items непроцессированные записи,
 * отправляет на AI анализ и сохраняет результаты в rss2tlg_summarization.
 * 
 * Функции:
 * - AI суммаризация и категоризация новостей
 * - Определение языка статьи (en, ru)
 * - Оценка важности (1-20)
 * - Подготовка данных для дедупликации
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
use App\Rss2Tlg\Pipeline\SummarizationService;

// ============================================================================
// КОНСТАНТЫ
// ============================================================================

const SCRIPT_NAME = 'RSS Summarization';
const SCRIPT_VERSION = '1.0.0';
const LOG_PREFIX = '[RSS_SUMMARIZATION]';

// PRODUCTION РЕЖИМ: Снять ограничение на количество новостей
// TEST РЕЖИМ: Обработать только последние 3 новости
const TEST_MODE = true; // Установите false для production
const TEST_ITEMS_LIMIT = 3; // Количество новостей для тестового режима

// ============================================================================
// ГЛАВНАЯ ФУНКЦИЯ
// ============================================================================

function main(): void
{
    $startTime = microtime(true);
    $scriptStart = date('Y-m-d H:i:s');
    
    echo "\n";
    echo "╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║        RSS SUMMARIZATION PRODUCTION SCRIPT v1.0.0             ║\n";
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
            "🤖 <b>RSS Summarization запущен</b>\n" .
            "⏱ Время: {$scriptStart}\n" .
            "🔧 Режим: " . (TEST_MODE ? "TEST" : "PRODUCTION")
        );
        
        // Получение непроцессированных новостей
        $items = getUnprocessedItems($db, $logger);
        
        if (empty($items)) {
            $logger->info(LOG_PREFIX . ' No unprocessed items found');
            echo "✅ Нет новостей для обработки\n";
            sendTelegramNotification($telegram, $logger, "✅ Нет новостей для обработки");
            return;
        }
        
        echo "📊 Найдено непроцессированных новостей: " . count($items) . "\n\n";
        $logger->info(LOG_PREFIX . ' Unprocessed items loaded', ['count' => count($items)]);
        
        // Инициализация сервиса суммаризации
        $summarizationConfig = loadSummarizationConfig();
        $summarizationService = new SummarizationService(
            $db,
            $openRouter,
            $summarizationConfig,
            $logger
        );
        
        echo "🚀 AI модели: " . implode(', ', $summarizationConfig['models']) . "\n\n";
        
        // Статистика
        $stats = [
            'total_items' => count($items),
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'total_tokens' => 0,
            'cache_hits' => 0,
            'errors' => []
        ];
        
        // Обработка каждой новости
        $counter = 0;
        foreach ($items as $item) {
            $counter++;
            
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            echo "📰 Новость [{$counter}/{$stats['total_items']}]\n";
            echo "🆔 ID: {$item['id']} | Feed ID: {$item['feed_id']}\n";
            echo "📌 Заголовок: " . mb_substr($item['title'], 0, 70) . "...\n";
            echo "🔗 URL: {$item['link']}\n";
            echo "📅 Дата: {$item['pub_date']}\n";
            
            $itemStartTime = microtime(true);
            
            try {
                $stats['processed']++;
                
                $success = $summarizationService->processItem((int)$item['id']);
                
                $duration = round((microtime(true) - $itemStartTime) * 1000);
                
                if ($success) {
                    $stats['success']++;
                    echo "✅ Обработано успешно за {$duration}ms\n";
                    $logger->info(LOG_PREFIX . ' Item processed successfully', [
                        'item_id' => $item['id'],
                        'duration_ms' => $duration
                    ]);
                } else {
                    $stats['failed']++;
                    echo "❌ Ошибка обработки\n";
                    $logger->error(LOG_PREFIX . ' Item processing failed', [
                        'item_id' => $item['id'],
                        'duration_ms' => $duration
                    ]);
                }
                
            } catch (Exception $e) {
                $stats['failed']++;
                $stats['errors'][] = "Item {$item['id']}: " . $e->getMessage();
                
                echo "❌ Ошибка: " . $e->getMessage() . "\n";
                $logger->error(LOG_PREFIX . ' Item processing exception', [
                    'item_id' => $item['id'],
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            // Отправляем промежуточное уведомление каждые 5 новостей или на последней
            if ($counter % 5 === 0 || $counter === $stats['total_items']) {
                $progressMessage = "⏳ <b>Прогресс суммаризации</b>\n\n" .
                    "Обработано: {$counter}/{$stats['total_items']}\n" .
                    "✅ Успешно: {$stats['success']}\n" .
                    "❌ Ошибок: {$stats['failed']}";
                
                sendTelegramNotification($telegram, $logger, $progressMessage);
            }
            
            // Небольшая пауза между запросами для стабильности
            if ($counter < $stats['total_items']) {
                usleep(500000); // 0.5 секунды
            }
        }
        
        // Получаем финальные метрики от сервиса
        $metrics = $summarizationService->getMetrics();
        $stats['total_tokens'] = $metrics['total_tokens'] ?? 0;
        $stats['cache_hits'] = $metrics['cache_hits'] ?? 0;
        
        // Финальная статистика
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        $scriptEnd = date('Y-m-d H:i:s');
        
        echo "\n";
        echo "╔═══════════════════════════════════════════════════════════════╗\n";
        echo "║                   ИТОГОВАЯ СТАТИСТИКА                         ║\n";
        echo "╚═══════════════════════════════════════════════════════════════╝\n";
        echo "\n";
        echo "⏱️  Время выполнения: {$duration} сек\n";
        echo "📊 Обработано новостей: {$stats['processed']}\n";
        echo "✅ Успешно: {$stats['success']}\n";
        echo "❌ Ошибок: {$stats['failed']}\n";
        echo "⏭️  Пропущено: {$stats['skipped']}\n";
        echo "🎯 Успешность: " . ($stats['processed'] > 0 ? round($stats['success'] / $stats['processed'] * 100, 2) : 0) . "%\n";
        echo "\n";
        echo "💰 Использовано токенов: {$stats['total_tokens']}\n";
        echo "📦 Cache hits: {$stats['cache_hits']}\n";
        echo "📈 Cache rate: " . ($stats['processed'] > 0 ? round($stats['cache_hits'] / $stats['processed'] * 100, 2) : 0) . "%\n";
        echo "\n";
        
        if (!empty($stats['errors'])) {
            echo "🔴 Ошибки:\n";
            foreach ($stats['errors'] as $error) {
                echo "  • {$error}\n";
            }
            echo "\n";
        }
        
        // Финальное уведомление
        $finalMessage = ($stats['failed'] === 0 ? "✅" : "⚠️") . " <b>Суммаризация завершена</b>\n\n" .
            "⏱ Время: {$duration} сек\n" .
            "📊 Обработано: {$stats['processed']}\n" .
            "✅ Успешно: {$stats['success']}\n" .
            "❌ Ошибок: {$stats['failed']}\n" .
            "💰 Токенов: {$stats['total_tokens']}\n" .
            "📦 Cache: {$stats['cache_hits']}";
        
        sendTelegramNotification($telegram, $logger, $finalMessage);
        
        $logger->info(LOG_PREFIX . ' Script completed', [
            'duration_sec' => $duration,
            'stats' => $stats
        ]);
        
        echo "🏁 Завершено: {$scriptEnd}\n\n";
        
    } catch (Exception $e) {
        echo "\n❌ КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n\n";
        
        if (isset($logger)) {
            $logger->error(LOG_PREFIX . ' Critical error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        if (isset($telegram)) {
            sendTelegramNotification(
                $telegram, 
                $logger ?? null, 
                "🔴 <b>КРИТИЧЕСКАЯ ОШИБКА</b>\n\n" . 
                "<code>" . htmlspecialchars($e->getMessage()) . "</code>"
            );
        }
        
        exit(1);
    }
}

// ============================================================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// ============================================================================

/**
 * Загружает главную конфигурацию
 *
 * @return array<string, mixed>
 */
function loadConfiguration(): array
{
    $configFile = __DIR__ . '/configs/main.json';
    
    if (!file_exists($configFile)) {
        throw new RuntimeException("Configuration file not found: {$configFile}");
    }
    
    $config = json_decode(file_get_contents($configFile), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException("Invalid JSON in configuration file: " . json_last_error_msg());
    }
    
    return $config;
}

/**
 * Загружает конфигурацию суммаризации
 *
 * @return array<string, mixed>
 */
function loadSummarizationConfig(): array
{
    $configFile = __DIR__ . '/configs/summarization.json';
    
    if (!file_exists($configFile)) {
        throw new RuntimeException("Summarization config not found: {$configFile}");
    }
    
    $config = json_decode(file_get_contents($configFile), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException("Invalid JSON in summarization config: " . json_last_error_msg());
    }
    
    return $config;
}

/**
 * Инициализирует Logger
 *
 * @param array<string, mixed> $config
 * @return Logger
 */
function initLogger(array $config): Logger
{
    $loggerConfig = [
        'directory' => $config['log_directory'] ?? '/home/engine/project/logs',
        'file_name' => 'rss_summarization.log',
        'min_level' => $config['log_level'] ?? 'info'
    ];
    
    // Создаем директорию для логов если не существует
    if (!is_dir($loggerConfig['directory'])) {
        mkdir($loggerConfig['directory'], 0755, true);
    }
    
    $logger = new Logger($loggerConfig);
    echo "✅ Logger инициализирован\n";
    
    return $logger;
}

/**
 * Инициализирует подключение к БД
 *
 * @param array<string, mixed> $config
 * @param Logger $logger
 * @return MySQL
 */
function initDatabase(array $config, Logger $logger): MySQL
{
    $dbConfigFile = __DIR__ . '/configs/database.json';
    
    if (!file_exists($dbConfigFile)) {
        throw new RuntimeException("Database config not found: {$dbConfigFile}");
    }
    
    $dbConfig = json_decode(file_get_contents($dbConfigFile), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException("Invalid JSON in database config: " . json_last_error_msg());
    }
    
    $db = new MySQL($dbConfig, $logger);
    echo "✅ MariaDB подключен: {$dbConfig['database']}\n";
    
    return $db;
}

/**
 * Инициализирует OpenRouter клиент
 *
 * @param array<string, mixed> $config
 * @param Logger $logger
 * @return OpenRouter
 */
function initOpenRouter(array $config, Logger $logger): OpenRouter
{
    $openRouterConfigFile = __DIR__ . '/configs/openrouter.json';
    
    if (!file_exists($openRouterConfigFile)) {
        throw new RuntimeException("OpenRouter config not found: {$openRouterConfigFile}");
    }
    
    $openRouterConfig = json_decode(file_get_contents($openRouterConfigFile), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException("Invalid JSON in OpenRouter config: " . json_last_error_msg());
    }
    
    $openRouter = new OpenRouter($openRouterConfig, $logger);
    echo "✅ OpenRouter инициализирован\n";
    
    return $openRouter;
}

/**
 * Инициализирует Telegram клиент
 *
 * @param array<string, mixed> $config
 * @param Logger $logger
 * @return Telegram
 */
function initTelegram(array $config, Logger $logger): Telegram
{
    $telegramConfigFile = __DIR__ . '/configs/telegram.json';
    
    if (!file_exists($telegramConfigFile)) {
        throw new RuntimeException("Telegram config not found: {$telegramConfigFile}");
    }
    
    $telegramConfig = json_decode(file_get_contents($telegramConfigFile), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException("Invalid JSON in Telegram config: " . json_last_error_msg());
    }
    
    $telegram = new Telegram($telegramConfig, $logger);
    echo "✅ Telegram инициализирован\n";
    
    return $telegram;
}

/**
 * Получает список непроцессированных новостей
 *
 * @param MySQL $db
 * @param Logger $logger
 * @return array<int, array<string, mixed>>
 */
function getUnprocessedItems(MySQL $db, Logger $logger): array
{
    // Получаем новости которых еще нет в таблице rss2tlg_summarization
    // или статус которых не 'success'
    $query = "
        SELECT 
            i.id,
            i.feed_id,
            i.title,
            i.link,
            i.pub_date,
            i.created_at
        FROM rss2tlg_items i
        LEFT JOIN rss2tlg_summarization s ON i.id = s.item_id
        WHERE s.id IS NULL OR s.status != 'success'
        ORDER BY i.created_at DESC
    ";
    
    // В тестовом режиме ограничиваем количество
    if (TEST_MODE) {
        $query .= " LIMIT " . TEST_ITEMS_LIMIT;
    }
    
    $items = $db->query($query);
    
    $logger->debug(LOG_PREFIX . ' Unprocessed items query executed', [
        'count' => count($items),
        'test_mode' => TEST_MODE,
        'limit' => TEST_MODE ? TEST_ITEMS_LIMIT : 'none'
    ]);
    
    return $items;
}

/**
 * Отправляет уведомление в Telegram
 *
 * @param Telegram $telegram
 * @param Logger|null $logger
 * @param string $message
 */
function sendTelegramNotification(Telegram $telegram, ?Logger $logger, string $message): void
{
    try {
        $telegramConfigFile = __DIR__ . '/configs/telegram.json';
        $telegramConfig = json_decode(file_get_contents($telegramConfigFile), true);
        $chatId = $telegramConfig['default_chat_id'];
        
        $telegram->sendText($chatId, $message, ['parse_mode' => 'HTML']);
        
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
// ЗАПУСК
// ============================================================================

main();

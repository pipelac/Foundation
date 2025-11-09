#!/usr/bin/env php
<?php
/**
 * AI Summarization Production Script
 * 
 * Обрабатывает сырые RSS данные через AI модуль суммаризации.
 * Запускается по cron каждую 1 минуту.
 * 
 * Функции:
 * - Суммаризация и категоризация новостей
 * - Определение языка и важности
 * - Подготовка данных для дедупликации
 * - Отправка уведомлений в Telegram при ошибках
 * - Ежесуточная детальная сводка (в 00:00)
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

const SCRIPT_NAME = 'AI Summarization';
const SCRIPT_VERSION = '1.0.0';
const LOG_PREFIX = '[AI_SUMMARIZATION]';

// Лимит для тестирования (закомментировать для production)
const TEST_MODE = true;
const TEST_LIMIT = 3; // Обрабатывать только последние 3 новости

// Время отправки ежесуточной сводки (часы, в UTC)
const DAILY_SUMMARY_HOUR = 21; // 00:00 MSK = 21:00 UTC

// ============================================================================
// ГЛАВНАЯ ФУНКЦИЯ
// ============================================================================

function main(): void
{
    $startTime = microtime(true);
    $scriptStart = date('Y-m-d H:i:s');
    
    echo "\n";
    echo "╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║        AI SUMMARIZATION PRODUCTION SCRIPT v1.0.0              ║\n";
    echo "╚═══════════════════════════════════════════════════════════════╝\n";
    echo "🕐 Start: {$scriptStart}\n";
    
    if (TEST_MODE) {
        echo "⚠️  TEST MODE: Processing last " . TEST_LIMIT . " items only\n";
    }
    
    echo "\n";
    
    try {
        // Инициализация
        $config = loadConfiguration();
        $logger = initLogger($config);
        $db = initDatabase($config, $logger);
        $telegram = initTelegram($config, $logger);
        $openRouter = initOpenRouter($config, $logger);
        $summarizationService = initSummarizationService($db, $openRouter, $config, $logger);
        
        $logger->info(LOG_PREFIX . ' Script started', [
            'version' => SCRIPT_VERSION,
            'pid' => getmypid(),
            'test_mode' => TEST_MODE
        ]);
        
        // Отправка уведомления в Telegram
        $modeText = TEST_MODE ? "TEST MODE (последние " . TEST_LIMIT . " новостей)" : "PRODUCTION MODE";
        sendTelegramNotification($telegram, $logger, 
            "🤖 <b>AI Summarization запущен</b>\n" .
            "⏱ Время: {$scriptStart}\n" .
            "📊 Режим: {$modeText}"
        );
        
        // Получение необработанных новостей
        $items = getUnprocessedItems($db, $logger);
        
        if (empty($items)) {
            $logger->info(LOG_PREFIX . ' No unprocessed items found');
            echo "✅ Нет необработанных новостей\n";
            return;
        }
        
        echo "📊 Найдено необработанных новостей: " . count($items) . "\n\n";
        $logger->info(LOG_PREFIX . ' Unprocessed items found', ['count' => count($items)]);
        
        // Статистика
        $stats = [
            'items_total' => count($items),
            'items_processed' => 0,
            'items_success' => 0,
            'items_failed' => 0,
            'items_skipped' => 0,
            'total_tokens' => 0,
            'total_tokens_prompt' => 0,
            'total_tokens_completion' => 0,
            'total_tokens_cached' => 0,
            'cache_hits' => 0,
            'processing_time_ms' => 0,
            'models_used' => [],
            'errors' => []
        ];
        
        // Обработка каждой новости
        foreach ($items as $index => $item) {
            $itemNumber = $index + 1;
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            echo "📰 Новость {$itemNumber}/{$stats['items_total']}\n";
            echo "🆔 ID: {$item['id']}\n";
            echo "📌 Заголовок: " . mb_substr($item['title'], 0, 60) . "...\n";
            echo "🔗 Источник: {$item['feed_name']}\n";
            
            $itemStartTime = microtime(true);
            $stats['items_processed']++;
            
            try {
                $success = $summarizationService->processItem((int)$item['id']);
                
                if ($success) {
                    $stats['items_success']++;
                    
                    // Получение метрик обработки
                    $metrics = $summarizationService->getMetrics();
                    $stats['total_tokens'] += $metrics['total_tokens'];
                    $stats['cache_hits'] += $metrics['cache_hits'];
                    
                    // Получение деталей из БД
                    $details = getProcessingDetails($db, (int)$item['id'], $logger);
                    if ($details) {
                        $stats['total_tokens_prompt'] += $details['tokens_prompt'] ?? 0;
                        $stats['total_tokens_completion'] += $details['tokens_completion'] ?? 0;
                        $stats['total_tokens_cached'] += $details['tokens_cached'] ?? 0;
                        
                        $modelUsed = $details['model_used'] ?? 'unknown';
                        if (!isset($stats['models_used'][$modelUsed])) {
                            $stats['models_used'][$modelUsed] = 0;
                        }
                        $stats['models_used'][$modelUsed]++;
                        
                        echo "✅ Успешно обработано\n";
                        echo "   • Категория: {$details['category_primary']}\n";
                        echo "   • Язык: {$details['article_language']}\n";
                        echo "   • Важность: {$details['importance_rating']}/20\n";
                        echo "   • Модель: {$modelUsed}\n";
                        echo "   • Токены: {$details['tokens_used']} (cached: {$details['tokens_cached']})\n";
                    } else {
                        echo "✅ Успешно обработано\n";
                    }
                } else {
                    $stats['items_skipped']++;
                    echo "⏭️  Пропущено (уже обработано)\n";
                }
                
                $itemTime = round((microtime(true) - $itemStartTime) * 1000);
                $stats['processing_time_ms'] += $itemTime;
                echo "⏱  Время обработки: {$itemTime} ms\n";
                
            } catch (Exception $e) {
                $stats['items_failed']++;
                $stats['errors'][] = [
                    'item_id' => $item['id'],
                    'title' => $item['title'],
                    'error' => $e->getMessage()
                ];
                
                echo "❌ Ошибка: {$e->getMessage()}\n";
                
                $logger->error(LOG_PREFIX . ' Item processing failed', [
                    'item_id' => $item['id'],
                    'error' => $e->getMessage()
                ]);
                
                // Отправка уведомления об ошибке в Telegram
                sendTelegramNotification($telegram, $logger,
                    "❌ <b>Ошибка обработки новости</b>\n" .
                    "🆔 ID: {$item['id']}\n" .
                    "📌 Заголовок: " . mb_substr($item['title'], 0, 100) . "\n" .
                    "⚠️ Ошибка: " . htmlspecialchars($e->getMessage())
                );
            }
            
            echo "\n";
        }
        
        // Финальный отчет
        $totalTime = round((microtime(true) - $startTime) * 1000);
        $stats['total_time_ms'] = $totalTime;
        
        echo "╔═══════════════════════════════════════════════════════════════╗\n";
        echo "║                    ФИНАЛЬНЫЙ ОТЧЕТ                            ║\n";
        echo "╚═══════════════════════════════════════════════════════════════╝\n";
        echo "📊 Обработано: {$stats['items_processed']} из {$stats['items_total']}\n";
        echo "✅ Успешно: {$stats['items_success']}\n";
        echo "❌ Ошибок: {$stats['items_failed']}\n";
        echo "⏭️  Пропущено: {$stats['items_skipped']}\n";
        echo "🎯 Общее количество токенов: {$stats['total_tokens']}\n";
        echo "📈 Cache hits: {$stats['cache_hits']}\n";
        echo "⏱  Общее время: {$totalTime} ms\n";
        echo "⚡ Среднее время на новость: " . ($stats['items_processed'] > 0 ? round($totalTime / $stats['items_processed']) : 0) . " ms\n";
        
        if (!empty($stats['models_used'])) {
            echo "\n🤖 Используемые модели:\n";
            foreach ($stats['models_used'] as $model => $count) {
                echo "   • {$model}: {$count} запросов\n";
            }
        }
        
        if (!empty($stats['errors'])) {
            echo "\n⚠️  Ошибки:\n";
            foreach ($stats['errors'] as $error) {
                echo "   • ID {$error['item_id']}: {$error['error']}\n";
            }
        }
        
        echo "\n";
        
        $logger->info(LOG_PREFIX . ' Script completed', $stats);
        
        // Сохранение статистики в БД
        saveStatistics($db, $stats, $logger);
        
        // Отправка финального отчета в Telegram
        sendFinalReport($telegram, $logger, $stats);
        
        // Проверка необходимости отправки ежесуточной сводки
        checkAndSendDailySummary($db, $telegram, $logger);
        
    } catch (Exception $e) {
        $logger->error(LOG_PREFIX . ' Fatal error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        echo "❌ КРИТИЧЕСКАЯ ОШИБКА: {$e->getMessage()}\n";
        
        // Отправка уведомления о критической ошибке
        if (isset($telegram)) {
            sendTelegramNotification($telegram, $logger,
                "🚨 <b>КРИТИЧЕСКАЯ ОШИБКА AI Summarization</b>\n" .
                "⚠️ Ошибка: " . htmlspecialchars($e->getMessage())
            );
        }
        
        exit(1);
    }
}

// ============================================================================
// ФУНКЦИИ ИНИЦИАЛИЗАЦИИ
// ============================================================================

/**
 * Загружает конфигурацию из файлов
 *
 * @return array<string, mixed>
 */
function loadConfiguration(): array
{
    $configDir = __DIR__ . '/configs';
    
    $mainConfig = json_decode(file_get_contents($configDir . '/main.json'), true);
    $aiConfig = json_decode(file_get_contents($configDir . '/ai_pipeline.json'), true);
    
    return array_merge($mainConfig, ['ai' => $aiConfig]);
}

/**
 * Инициализирует Logger
 *
 * @param array<string, mixed> $config
 * @return Logger
 */
function initLogger(array $config): Logger
{
    $logDir = $config['log_directory'] ?? '/home/engine/project/logs';
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    return new Logger([
        'directory' => $logDir,
        'file_name' => 'ai_summarization.log',
        'min_level' => $config['log_level'] ?? 'info',
    ]);
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
    $dbConfig = json_decode(file_get_contents(__DIR__ . '/configs/database.json'), true);
    return new MySQL($dbConfig, $logger);
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
    $telegramConfig = json_decode(file_get_contents(__DIR__ . '/configs/telegram.json'), true);
    return new Telegram($telegramConfig, $logger);
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
    $openRouterConfig = $config['ai']['openrouter'];
    
    return new OpenRouter([
        'api_key' => $openRouterConfig['api_key'],
        'base_url' => $openRouterConfig['base_url'],
        'timeout' => $openRouterConfig['timeout'],
    ], $logger);
}

/**
 * Инициализирует SummarizationService
 *
 * @param MySQL $db
 * @param OpenRouter $openRouter
 * @param array<string, mixed> $config
 * @param Logger $logger
 * @return SummarizationService
 */
function initSummarizationService(
    MySQL $db,
    OpenRouter $openRouter,
    array $config,
    Logger $logger
): SummarizationService {
    return new SummarizationService(
        $db,
        $openRouter,
        $config['ai']['summarization'],
        $logger
    );
}

// ============================================================================
// ФУНКЦИИ РАБОТЫ С ДАННЫМИ
// ============================================================================

/**
 * Получает список необработанных новостей
 *
 * @param MySQL $db
 * @param Logger $logger
 * @return array<int, array<string, mixed>>
 */
function getUnprocessedItems(MySQL $db, Logger $logger): array
{
    $query = "
        SELECT 
            i.id,
            i.feed_id,
            i.title,
            i.description,
            i.content,
            i.extracted_content,
            i.link,
            i.pub_date,
            f.name as feed_name
        FROM rss2tlg_items i
        INNER JOIN rss2tlg_feeds f ON i.feed_id = f.id
        LEFT JOIN rss2tlg_summarization s ON i.id = s.item_id
        WHERE s.item_id IS NULL OR s.status IN ('pending', 'failed')
        ORDER BY i.pub_date DESC
    ";
    
    // В тестовом режиме ограничиваем количество
    if (TEST_MODE) {
        $query .= " LIMIT " . TEST_LIMIT;
    }
    
    try {
        $items = $db->query($query);
        $logger->debug(LOG_PREFIX . ' Unprocessed items query executed', [
            'count' => count($items),
            'test_mode' => TEST_MODE
        ]);
        return $items;
    } catch (Exception $e) {
        $logger->error(LOG_PREFIX . ' Failed to get unprocessed items', [
            'error' => $e->getMessage()
        ]);
        return [];
    }
}

/**
 * Получает детали обработки из БД
 *
 * @param MySQL $db
 * @param int $itemId
 * @param Logger $logger
 * @return array<string, mixed>|null
 */
function getProcessingDetails(MySQL $db, int $itemId, Logger $logger): ?array
{
    $query = "
        SELECT 
            article_language,
            category_primary,
            category_secondary,
            importance_rating,
            model_used,
            tokens_used,
            tokens_prompt,
            tokens_completion,
            tokens_cached,
            cache_hit
        FROM rss2tlg_summarization
        WHERE item_id = :item_id
        LIMIT 1
    ";
    
    try {
        return $db->queryOne($query, ['item_id' => $itemId]);
    } catch (Exception $e) {
        $logger->error(LOG_PREFIX . ' Failed to get processing details', [
            'item_id' => $itemId,
            'error' => $e->getMessage()
        ]);
        return null;
    }
}

/**
 * Сохраняет статистику запуска в БД
 *
 * @param MySQL $db
 * @param array<string, mixed> $stats
 * @param Logger $logger
 */
function saveStatistics(MySQL $db, array $stats, Logger $logger): void
{
    $query = "
        INSERT INTO rss2tlg_statistics (
            script_name,
            script_version,
            run_date,
            items_total,
            items_processed,
            items_success,
            items_failed,
            items_skipped,
            total_tokens,
            total_tokens_prompt,
            total_tokens_completion,
            total_tokens_cached,
            cache_hits,
            processing_time_ms,
            models_used,
            errors,
            created_at
        ) VALUES (
            :script_name,
            :script_version,
            :run_date,
            :items_total,
            :items_processed,
            :items_success,
            :items_failed,
            :items_skipped,
            :total_tokens,
            :total_tokens_prompt,
            :total_tokens_completion,
            :total_tokens_cached,
            :cache_hits,
            :processing_time_ms,
            :models_used,
            :errors,
            NOW()
        )
    ";
    
    try {
        $db->execute($query, [
            'script_name' => SCRIPT_NAME,
            'script_version' => SCRIPT_VERSION,
            'run_date' => date('Y-m-d H:i:s'),
            'items_total' => $stats['items_total'],
            'items_processed' => $stats['items_processed'],
            'items_success' => $stats['items_success'],
            'items_failed' => $stats['items_failed'],
            'items_skipped' => $stats['items_skipped'],
            'total_tokens' => $stats['total_tokens'],
            'total_tokens_prompt' => $stats['total_tokens_prompt'],
            'total_tokens_completion' => $stats['total_tokens_completion'],
            'total_tokens_cached' => $stats['total_tokens_cached'],
            'cache_hits' => $stats['cache_hits'],
            'processing_time_ms' => $stats['processing_time_ms'],
            'models_used' => json_encode($stats['models_used'], JSON_UNESCAPED_UNICODE),
            'errors' => json_encode($stats['errors'], JSON_UNESCAPED_UNICODE),
        ]);
        
        $logger->info(LOG_PREFIX . ' Statistics saved', ['stats' => $stats]);
    } catch (Exception $e) {
        $logger->error(LOG_PREFIX . ' Failed to save statistics', [
            'error' => $e->getMessage()
        ]);
    }
}

// ============================================================================
// ФУНКЦИИ ОТПРАВКИ УВЕДОМЛЕНИЙ
// ============================================================================

/**
 * Отправляет уведомление в Telegram
 *
 * @param Telegram $telegram
 * @param Logger $logger
 * @param string $message
 */
function sendTelegramNotification(Telegram $telegram, Logger $logger, string $message): void
{
    try {
        $telegram->sendText(null, $message, ['parse_mode' => 'HTML']);
        $logger->debug(LOG_PREFIX . ' Telegram notification sent');
    } catch (Exception $e) {
        $logger->error(LOG_PREFIX . ' Failed to send Telegram notification', [
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Отправляет финальный отчет в Telegram
 *
 * @param Telegram $telegram
 * @param Logger $logger
 * @param array<string, mixed> $stats
 */
function sendFinalReport(Telegram $telegram, Logger $logger, array $stats): void
{
    $avgTime = $stats['items_processed'] > 0 
        ? round($stats['total_time_ms'] / $stats['items_processed']) 
        : 0;
    
    $cacheRate = $stats['items_success'] > 0 
        ? round(($stats['cache_hits'] / $stats['items_success']) * 100) 
        : 0;
    
    $message = "📊 <b>AI Summarization - Финальный отчет</b>\n\n";
    $message .= "📰 <b>Обработка:</b>\n";
    $message .= "   • Всего: {$stats['items_total']}\n";
    $message .= "   • Обработано: {$stats['items_processed']}\n";
    $message .= "   • Успешно: {$stats['items_success']} ✅\n";
    $message .= "   • Ошибок: {$stats['items_failed']} ❌\n";
    $message .= "   • Пропущено: {$stats['items_skipped']} ⏭️\n\n";
    
    $message .= "🎯 <b>Токены:</b>\n";
    $message .= "   • Всего: {$stats['total_tokens']}\n";
    $message .= "   • Prompt: {$stats['total_tokens_prompt']}\n";
    $message .= "   • Completion: {$stats['total_tokens_completion']}\n";
    $message .= "   • Cached: {$stats['total_tokens_cached']}\n";
    $message .= "   • Cache rate: {$cacheRate}%\n\n";
    
    $message .= "⏱ <b>Производительность:</b>\n";
    $message .= "   • Общее время: {$stats['total_time_ms']} ms\n";
    $message .= "   • Среднее время: {$avgTime} ms/новость\n\n";
    
    if (!empty($stats['models_used'])) {
        $message .= "🤖 <b>Модели:</b>\n";
        foreach ($stats['models_used'] as $model => $count) {
            $shortModel = str_replace(['anthropic/', 'deepseek/'], '', $model);
            $message .= "   • {$shortModel}: {$count}\n";
        }
        $message .= "\n";
    }
    
    if (!empty($stats['errors'])) {
        $message .= "⚠️ <b>Ошибки:</b> {$stats['items_failed']}\n";
    }
    
    $message .= "🕐 " . date('Y-m-d H:i:s');
    
    sendTelegramNotification($telegram, $logger, $message);
}

/**
 * Проверяет и отправляет ежесуточную сводку если необходимо
 *
 * @param MySQL $db
 * @param Telegram $telegram
 * @param Logger $logger
 */
function checkAndSendDailySummary(MySQL $db, Telegram $telegram, Logger $logger): void
{
    $currentHour = (int)date('H');
    
    // Проверяем, что текущий час = часу отправки сводки
    if ($currentHour !== DAILY_SUMMARY_HOUR) {
        return;
    }
    
    // Проверяем, не отправляли ли мы уже сводку сегодня
    $query = "
        SELECT COUNT(*) as count
        FROM rss2tlg_daily_summaries
        WHERE summary_date = CURDATE()
        LIMIT 1
    ";
    
    try {
        $result = $db->queryOne($query);
        if ($result && $result['count'] > 0) {
            $logger->debug(LOG_PREFIX . ' Daily summary already sent today');
            return;
        }
    } catch (Exception $e) {
        $logger->error(LOG_PREFIX . ' Failed to check daily summary status', [
            'error' => $e->getMessage()
        ]);
        return;
    }
    
    // Отправляем ежесуточную сводку
    sendDailySummary($db, $telegram, $logger);
}

/**
 * Отправляет детальную ежесуточную сводку
 *
 * @param MySQL $db
 * @param Telegram $telegram
 * @param Logger $logger
 */
function sendDailySummary(MySQL $db, Telegram $telegram, Logger $logger): void
{
    $logger->info(LOG_PREFIX . ' Generating daily summary');
    
    // Получаем статистику за вчерашний день
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    $query = "
        SELECT 
            COUNT(*) as total_runs,
            SUM(items_total) as total_items,
            SUM(items_processed) as total_processed,
            SUM(items_success) as total_success,
            SUM(items_failed) as total_failed,
            SUM(items_skipped) as total_skipped,
            SUM(total_tokens) as total_tokens,
            SUM(total_tokens_prompt) as total_tokens_prompt,
            SUM(total_tokens_completion) as total_tokens_completion,
            SUM(total_tokens_cached) as total_tokens_cached,
            SUM(cache_hits) as total_cache_hits,
            SUM(processing_time_ms) as total_time_ms,
            AVG(processing_time_ms) as avg_time_ms
        FROM rss2tlg_statistics
        WHERE script_name = :script_name
          AND DATE(run_date) = :yesterday
    ";
    
    try {
        $stats = $db->queryOne($query, [
            'script_name' => SCRIPT_NAME,
            'yesterday' => $yesterday
        ]);
        
        if (!$stats || $stats['total_runs'] == 0) {
            $logger->info(LOG_PREFIX . ' No statistics for yesterday');
            return;
        }
        
        // Получаем статистику по моделям
        $modelsQuery = "
            SELECT models_used
            FROM rss2tlg_statistics
            WHERE script_name = :script_name
              AND DATE(run_date) = :yesterday
              AND models_used IS NOT NULL
        ";
        
        $modelsData = $db->query($modelsQuery, [
            'script_name' => SCRIPT_NAME,
            'yesterday' => $yesterday
        ]);
        
        $modelsTotal = [];
        foreach ($modelsData as $row) {
            $models = json_decode($row['models_used'], true);
            if ($models) {
                foreach ($models as $model => $count) {
                    if (!isset($modelsTotal[$model])) {
                        $modelsTotal[$model] = 0;
                    }
                    $modelsTotal[$model] += $count;
                }
            }
        }
        
        // Рассчитываем стоимость
        $costs = calculateCosts($stats, $modelsTotal);
        
        // Формируем детальную сводку
        $message = "📊 <b>ЕЖЕСУТОЧНАЯ СВОДКА AI SUMMARIZATION</b>\n";
        $message .= "📅 За: {$yesterday}\n\n";
        
        $message .= "🚀 <b>ЗАПУСКИ:</b>\n";
        $message .= "   • Всего запусков: {$stats['total_runs']}\n";
        $message .= "   • Интервал: каждую 1 минуту\n\n";
        
        $message .= "📰 <b>ОБРАБОТКА НОВОСТЕЙ:</b>\n";
        $message .= "   • Всего новостей: {$stats['total_items']}\n";
        $message .= "   • Обработано: {$stats['total_processed']}\n";
        $message .= "   • Успешно: {$stats['total_success']} ✅\n";
        $message .= "   • Ошибок: {$stats['total_failed']} ❌\n";
        $message .= "   • Пропущено: {$stats['total_skipped']} ⏭️\n";
        
        $successRate = $stats['total_processed'] > 0 
            ? round(($stats['total_success'] / $stats['total_processed']) * 100, 2) 
            : 0;
        $message .= "   • Success rate: {$successRate}%\n\n";
        
        $message .= "🎯 <b>ИСПОЛЬЗОВАНИЕ ТОКЕНОВ:</b>\n";
        $message .= "   • Всего токенов: " . number_format($stats['total_tokens']) . "\n";
        $message .= "   • Prompt токены: " . number_format($stats['total_tokens_prompt']) . "\n";
        $message .= "   • Completion токены: " . number_format($stats['total_tokens_completion']) . "\n";
        $message .= "   • Cached токены: " . number_format($stats['total_tokens_cached']) . "\n";
        
        $cacheRate = $stats['total_success'] > 0 
            ? round(($stats['total_cache_hits'] / $stats['total_success']) * 100, 2) 
            : 0;
        $message .= "   • Cache hits: {$stats['total_cache_hits']} ({$cacheRate}%)\n";
        
        $avgTokens = $stats['total_success'] > 0 
            ? round($stats['total_tokens'] / $stats['total_success']) 
            : 0;
        $message .= "   • Среднее на новость: {$avgTokens} токенов\n\n";
        
        $message .= "💰 <b>СТОИМОСТЬ:</b>\n";
        $message .= "   • Claude 3.5 Sonnet: \${$costs['claude']}\n";
        $message .= "   • DeepSeek Chat: \${$costs['deepseek']}\n";
        $message .= "   • Экономия от cache: \${$costs['cache_savings']}\n";
        $message .= "   • <b>ИТОГО: \${$costs['total']}</b>\n\n";
        
        $message .= "⏱ <b>ПРОИЗВОДИТЕЛЬНОСТЬ:</b>\n";
        $totalTimeSec = round($stats['total_time_ms'] / 1000);
        $message .= "   • Общее время: {$totalTimeSec} сек\n";
        $avgTimeMs = round($stats['avg_time_ms']);
        $message .= "   • Среднее время запуска: {$avgTimeMs} ms\n";
        $avgPerItem = $stats['total_success'] > 0 
            ? round($stats['total_time_ms'] / $stats['total_success']) 
            : 0;
        $message .= "   • Среднее на новость: {$avgPerItem} ms\n\n";
        
        if (!empty($modelsTotal)) {
            $message .= "🤖 <b>ИСПОЛЬЗУЕМЫЕ МОДЕЛИ:</b>\n";
            arsort($modelsTotal);
            foreach ($modelsTotal as $model => $count) {
                $shortModel = str_replace(['anthropic/', 'deepseek/'], '', $model);
                $percentage = $stats['total_success'] > 0 
                    ? round(($count / $stats['total_success']) * 100, 1) 
                    : 0;
                $message .= "   • {$shortModel}: {$count} ({$percentage}%)\n";
            }
            $message .= "\n";
        }
        
        $message .= "📈 <b>КАТЕГОРИИ:</b>\n";
        $categories = getCategoryStatistics($db, $yesterday, $logger);
        if (!empty($categories)) {
            arsort($categories);
            $topCategories = array_slice($categories, 0, 5, true);
            foreach ($topCategories as $category => $count) {
                $message .= "   • {$category}: {$count}\n";
            }
            $message .= "\n";
        }
        
        $message .= "🌍 <b>ЯЗЫКИ:</b>\n";
        $languages = getLanguageStatistics($db, $yesterday, $logger);
        if (!empty($languages)) {
            arsort($languages);
            foreach ($languages as $lang => $count) {
                $langName = $lang === 'ru' ? '🇷🇺 Русский' : ($lang === 'en' ? '🇬🇧 English' : $lang);
                $message .= "   • {$langName}: {$count}\n";
            }
            $message .= "\n";
        }
        
        $message .= "⭐ <b>ВАЖНОСТЬ:</b>\n";
        $importance = getImportanceStatistics($db, $yesterday, $logger);
        if (!empty($importance)) {
            $message .= "   • Высокая (15-20): {$importance['high']}\n";
            $message .= "   • Средняя (10-14): {$importance['medium']}\n";
            $message .= "   • Низкая (1-9): {$importance['low']}\n";
            $avgImportance = round($importance['avg'], 1);
            $message .= "   • Средняя: {$avgImportance}/20\n\n";
        }
        
        $message .= "🕐 Сгенерировано: " . date('Y-m-d H:i:s');
        
        // Отправляем сводку
        sendTelegramNotification($telegram, $logger, $message);
        
        // Сохраняем отметку об отправке
        $saveQuery = "
            INSERT INTO rss2tlg_daily_summaries (
                summary_date,
                script_name,
                summary_data,
                created_at
            ) VALUES (
                :summary_date,
                :script_name,
                :summary_data,
                NOW()
            )
        ";
        
        $db->execute($saveQuery, [
            'summary_date' => $yesterday,
            'script_name' => SCRIPT_NAME,
            'summary_data' => json_encode(array_merge($stats, [
                'models' => $modelsTotal,
                'costs' => $costs,
                'categories' => $categories ?? [],
                'languages' => $languages ?? [],
                'importance' => $importance ?? []
            ]), JSON_UNESCAPED_UNICODE),
        ]);
        
        $logger->info(LOG_PREFIX . ' Daily summary sent', ['date' => $yesterday]);
        
    } catch (Exception $e) {
        $logger->error(LOG_PREFIX . ' Failed to send daily summary', [
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Рассчитывает стоимость использования API
 *
 * @param array<string, mixed> $stats
 * @param array<string, int> $modelsTotal
 * @return array<string, string>
 */
function calculateCosts(array $stats, array $modelsTotal): array
{
    // Цены за 1M токенов (OpenRouter)
    $prices = [
        'anthropic/claude-3.5-sonnet' => [
            'prompt' => 3.00,     // $3/1M input tokens
            'completion' => 15.00, // $15/1M output tokens
            'cached' => 0.30,     // $0.30/1M cached tokens (90% discount)
        ],
        'deepseek/deepseek-chat' => [
            'prompt' => 0.14,     // $0.14/1M input tokens
            'completion' => 0.28, // $0.28/1M output tokens
            'cached' => 0.014,    // Cache discount (estimated)
        ],
    ];
    
    $claudeCost = 0;
    $deepseekCost = 0;
    $cacheSavings = 0;
    
    // Примерное распределение токенов по моделям
    $claudeShare = ($modelsTotal['anthropic/claude-3.5-sonnet'] ?? 0) / max($stats['total_success'], 1);
    $deepseekShare = ($modelsTotal['deepseek/deepseek-chat'] ?? 0) / max($stats['total_success'], 1);
    
    // Claude costs
    $claudePromptTokens = $stats['total_tokens_prompt'] * $claudeShare;
    $claudeCompletionTokens = $stats['total_tokens_completion'] * $claudeShare;
    $claudeCachedTokens = $stats['total_tokens_cached'] * $claudeShare;
    
    $claudeCost += ($claudePromptTokens / 1000000) * $prices['anthropic/claude-3.5-sonnet']['prompt'];
    $claudeCost += ($claudeCompletionTokens / 1000000) * $prices['anthropic/claude-3.5-sonnet']['completion'];
    
    // DeepSeek costs
    $deepseekPromptTokens = $stats['total_tokens_prompt'] * $deepseekShare;
    $deepseekCompletionTokens = $stats['total_tokens_completion'] * $deepseekShare;
    
    $deepseekCost += ($deepseekPromptTokens / 1000000) * $prices['deepseek/deepseek-chat']['prompt'];
    $deepseekCost += ($deepseekCompletionTokens / 1000000) * $prices['deepseek/deepseek-chat']['completion'];
    
    // Cache savings (экономия от кеша)
    $normalCacheCost = ($claudeCachedTokens / 1000000) * $prices['anthropic/claude-3.5-sonnet']['prompt'];
    $actualCacheCost = ($claudeCachedTokens / 1000000) * $prices['anthropic/claude-3.5-sonnet']['cached'];
    $cacheSavings = $normalCacheCost - $actualCacheCost;
    
    $total = $claudeCost + $deepseekCost;
    
    return [
        'claude' => number_format($claudeCost, 4),
        'deepseek' => number_format($deepseekCost, 4),
        'cache_savings' => number_format($cacheSavings, 4),
        'total' => number_format($total, 4),
    ];
}

/**
 * Получает статистику по категориям
 *
 * @param MySQL $db
 * @param string $date
 * @param Logger $logger
 * @return array<string, int>
 */
function getCategoryStatistics(MySQL $db, string $date, Logger $logger): array
{
    $query = "
        SELECT category_primary, COUNT(*) as count
        FROM rss2tlg_summarization
        WHERE DATE(processed_at) = :date
          AND status = 'success'
          AND category_primary IS NOT NULL
        GROUP BY category_primary
        ORDER BY count DESC
    ";
    
    try {
        $results = $db->query($query, ['date' => $date]);
        $categories = [];
        foreach ($results as $row) {
            $categories[$row['category_primary']] = (int)$row['count'];
        }
        return $categories;
    } catch (Exception $e) {
        $logger->error(LOG_PREFIX . ' Failed to get category statistics', [
            'error' => $e->getMessage()
        ]);
        return [];
    }
}

/**
 * Получает статистику по языкам
 *
 * @param MySQL $db
 * @param string $date
 * @param Logger $logger
 * @return array<string, int>
 */
function getLanguageStatistics(MySQL $db, string $date, Logger $logger): array
{
    $query = "
        SELECT article_language, COUNT(*) as count
        FROM rss2tlg_summarization
        WHERE DATE(processed_at) = :date
          AND status = 'success'
          AND article_language IS NOT NULL
        GROUP BY article_language
        ORDER BY count DESC
    ";
    
    try {
        $results = $db->query($query, ['date' => $date]);
        $languages = [];
        foreach ($results as $row) {
            $languages[$row['article_language']] = (int)$row['count'];
        }
        return $languages;
    } catch (Exception $e) {
        $logger->error(LOG_PREFIX . ' Failed to get language statistics', [
            'error' => $e->getMessage()
        ]);
        return [];
    }
}

/**
 * Получает статистику по важности
 *
 * @param MySQL $db
 * @param string $date
 * @param Logger $logger
 * @return array<string, int|float>
 */
function getImportanceStatistics(MySQL $db, string $date, Logger $logger): array
{
    $query = "
        SELECT 
            COUNT(CASE WHEN importance_rating >= 15 THEN 1 END) as high,
            COUNT(CASE WHEN importance_rating >= 10 AND importance_rating < 15 THEN 1 END) as medium,
            COUNT(CASE WHEN importance_rating < 10 THEN 1 END) as low,
            AVG(importance_rating) as avg
        FROM rss2tlg_summarization
        WHERE DATE(processed_at) = :date
          AND status = 'success'
          AND importance_rating IS NOT NULL
    ";
    
    try {
        $result = $db->queryOne($query, ['date' => $date]);
        return [
            'high' => (int)($result['high'] ?? 0),
            'medium' => (int)($result['medium'] ?? 0),
            'low' => (int)($result['low'] ?? 0),
            'avg' => (float)($result['avg'] ?? 0),
        ];
    } catch (Exception $e) {
        $logger->error(LOG_PREFIX . ' Failed to get importance statistics', [
            'error' => $e->getMessage()
        ]);
        return [];
    }
}

// ============================================================================
// ЗАПУСК СКРИПТА
// ============================================================================

main();

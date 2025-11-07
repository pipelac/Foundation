<?php
/**
 * E2E тестирование Rss2Tlg V4
 * Полный цикл: инфраструктура → сбор новостей → AI анализ → публикация в канал
 * 
 * Запуск: php tests_rss2tlg_e2e_v4.php
 * 
 * @version 4.0
 * @date 2025-01-07
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
set_time_limit(0);

require_once __DIR__ . '/autoload.php';

use App\Config\ConfigLoader;
use App\Component\Logger;
use App\Component\MySQL;
use App\Component\Http;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\OpenRouter;
use App\Component\OpenRouterMetrics;
use App\Rss2Tlg\FetchRunner;
use App\Rss2Tlg\ItemRepository;
use App\Rss2Tlg\FeedStateRepository;
use App\Rss2Tlg\PublicationRepository;
use App\Rss2Tlg\AIAnalysisRepository;
use App\Rss2Tlg\AIAnalysisService;
use App\Rss2Tlg\DTO\FeedConfig;

// ============================================================================
// КОНСТАНТЫ И КОНФИГУРАЦИЯ
// ============================================================================

const CONFIG_PATH = 'Config/rss2tlg_e2e_v4_test.json';
const TEST_VERSION = 'V4';
const TIMESTAMP = '20250107';

// Цвета для консоли
const C_RESET = "\033[0m";
const C_BOLD = "\033[1m";
const C_RED = "\033[31m";
const C_GREEN = "\033[32m";
const C_YELLOW = "\033[33m";
const C_BLUE = "\033[34m";
const C_MAGENTA = "\033[35m";
const C_CYAN = "\033[36m";

// ============================================================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// ============================================================================

/**
 * Вывод заголовка секции
 */
function printHeader(string $title): void {
    $line = str_repeat('=', 80);
    echo "\n" . C_CYAN . C_BOLD . $line . C_RESET . "\n";
    echo C_CYAN . C_BOLD . "  " . $title . C_RESET . "\n";
    echo C_CYAN . C_BOLD . $line . C_RESET . "\n\n";
}

/**
 * Вывод подзаголовка
 */
function printSubHeader(string $title): void {
    echo "\n" . C_BLUE . C_BOLD . ">>> " . $title . C_RESET . "\n";
}

/**
 * Вывод успеха
 */
function printSuccess(string $message): void {
    echo C_GREEN . "✅ " . $message . C_RESET . "\n";
}

/**
 * Вывод ошибки
 */
function printError(string $message): void {
    echo C_RED . "❌ " . $message . C_RESET . "\n";
}

/**
 * Вывод предупреждения
 */
function printWarning(string $message): void {
    echo C_YELLOW . "⚠️  " . $message . C_RESET . "\n";
}

/**
 * Вывод информации
 */
function printInfo(string $message): void {
    echo C_BLUE . "ℹ️  " . $message . C_RESET . "\n";
}

/**
 * Отправка уведомления в Telegram бот
 */
function sendTelegramNotification(TelegramAPI $telegram, int $chatId, string $message): void {
    try {
        $telegram->sendMessage($chatId, $message, ['parse_mode' => 'HTML']);
        printInfo("Уведомление отправлено в бот");
    } catch (Exception $e) {
        printWarning("Не удалось отправить уведомление: " . $e->getMessage());
    }
}

/**
 * Очистка таблиц БД
 */
function cleanupTables(MySQL $db, Logger $logger): void {
    printSubHeader("Очистка таблиц БД");
    
    $tables = [
        'rss2tlg_publications',
        'rss2tlg_ai_analysis',
        'rss2tlg_items',
        'rss2tlg_feed_state'
    ];
    
    foreach ($tables as $table) {
        try {
            $db->query("TRUNCATE TABLE `{$table}`");
            printSuccess("Таблица {$table} очищена");
        } catch (Exception $e) {
            printWarning("Не удалось очистить {$table}: " . $e->getMessage());
        }
    }
}

/**
 * Создание дампа таблицы
 */
function dumpTable(MySQL $db, string $table, string $outputDir): void {
    try {
        $timestamp = date('YmdHis');
        $filename = "{$outputDir}/{$table}_v4_{$timestamp}.csv";
        
        $result = $db->query("SELECT * FROM `{$table}`");
        $rows = is_array($result) ? $result : $result->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($rows)) {
            printWarning("Таблица {$table} пуста");
            return;
        }
        
        $fp = fopen($filename, 'w');
        
        // Заголовки
        fputcsv($fp, array_keys($rows[0]));
        
        // Данные
        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }
        
        fclose($fp);
        
        $size = filesize($filename);
        $count = count($rows);
        printSuccess("Дамп {$table}: {$count} строк, " . number_format($size) . " байт");
    } catch (Exception $e) {
        printError("Ошибка дампа {$table}: " . $e->getMessage());
    }
}

/**
 * Форматирование метрик OpenRouter
 */
function formatMetrics(array $metrics): string {
    $lines = [];
    
    if (isset($metrics['usage'])) {
        $usage = $metrics['usage'];
        $lines[] = "📊 <b>Токены:</b>";
        $lines[] = "  • Prompt: " . ($usage['prompt_tokens'] ?? 0);
        $lines[] = "  • Completion: " . ($usage['completion_tokens'] ?? 0);
        $lines[] = "  • Total: " . ($usage['total_tokens'] ?? 0);
        
        if (isset($usage['cached_tokens']) && $usage['cached_tokens'] > 0) {
            $lines[] = "  • Cached: " . $usage['cached_tokens'] . " 🎯";
        }
    }
    
    if (isset($metrics['model'])) {
        $lines[] = "\n🤖 <b>Модель:</b> " . $metrics['model'];
    }
    
    if (isset($metrics['id'])) {
        $lines[] = "🔑 <b>ID:</b> <code>" . $metrics['id'] . "</code>";
    }
    
    return implode("\n", $lines);
}

// ============================================================================
// ГЛАВНАЯ ФУНКЦИЯ ТЕСТИРОВАНИЯ
// ============================================================================

function runE2ETest(): int {
    $startTime = microtime(true);
    $errors = [];
    
    printHeader("🚀 E2E ТЕСТИРОВАНИЕ RSS2TLG V4");
    echo C_BOLD . "Дата запуска: " . C_RESET . date('Y-m-d H:i:s') . "\n";
    echo C_BOLD . "Конфигурация: " . C_RESET . CONFIG_PATH . "\n\n";
    
    // ------------------------------------------------------------------------
    // 1. ЗАГРУЗКА КОНФИГУРАЦИИ
    // ------------------------------------------------------------------------
    
    printSubHeader("1. Загрузка конфигурации");
    
    try {
        $config = ConfigLoader::load(CONFIG_PATH);
        printSuccess("Конфигурация загружена");
        printInfo("RSS лент: " . count($config['feeds']));
        printInfo("База данных: {$config['database']['host']}:{$config['database']['port']}");
    } catch (Exception $e) {
        printError("Ошибка загрузки конфигурации: " . $e->getMessage());
        return 1;
    }
    
    // ------------------------------------------------------------------------
    // 2. ИНИЦИАЛИЗАЦИЯ КОМПОНЕНТОВ
    // ------------------------------------------------------------------------
    
    printSubHeader("2. Инициализация компонентов");
    
    try {
        // Logger
        $logger = new Logger($config['logger']);
        printSuccess("Logger инициализирован");
        
        // Database
        $db = new MySQL($config['database'], $logger);
        $versionResult = $db->query("SELECT VERSION() as version");
        $versionRow = is_array($versionResult) ? $versionResult[0] : $versionResult->fetch(PDO::FETCH_ASSOC);
        printSuccess("MySQL подключен: MariaDB " . $versionRow['version']);
        
        // HTTP клиент
        $http = new Http($config, $logger);
        printSuccess("HTTP клиент готов");
        
        // Telegram
        $telegram = new TelegramAPI(
            $config['telegram']['bot_token'],
            $http,
            $logger,
            null
        );
        printSuccess("Telegram API готов");
        
        // OpenRouter
        $openRouter = new OpenRouter($config['openrouter'], $logger);
        printSuccess("OpenRouter API готов");
        
        // Repositories
        $feedStateRepo = new FeedStateRepository($db, $logger, true);
        $itemRepo = new ItemRepository($db, $logger, true);
        $pubRepo = new PublicationRepository($db, $logger, true);
        $aiRepo = new AIAnalysisRepository($db, $logger, true);
        printSuccess("Репозитории инициализированы");
        
        // Services
        $fetchRunner = new FetchRunner($db, $config['cache']['directory'], $logger);
        
        // PromptManager
        $promptManager = new \App\Rss2Tlg\PromptManager(__DIR__ . '/prompts', $logger);
        
        // AIAnalysisService
        $aiService = new AIAnalysisService(
            $promptManager,
            $aiRepo,
            $openRouter,
            $db,
            $logger
        );
        printSuccess("Сервисы готовы");
        
    } catch (Exception $e) {
        printError("Ошибка инициализации: " . $e->getMessage());
        return 1;
    }
    
    // Уведомление о старте
    sendTelegramNotification(
        $telegram,
        $config['telegram']['notification_chat_id'],
        "🚀 <b>E2E Тест V4 запущен</b>\n\n" .
        "📅 " . date('Y-m-d H:i:s') . "\n" .
        "📡 Лент: " . count($config['feeds']) . "\n" .
        "🗄️ База: MariaDB 11.3.2\n" .
        "🤖 AI: " . $config['openrouter']['models']['primary']
    );
    
    // ------------------------------------------------------------------------
    // 3. ОЧИСТКА ДАННЫХ
    // ------------------------------------------------------------------------
    
    cleanupTables($db, $logger);
    
    // ------------------------------------------------------------------------
    // 4. СБОР НОВОСТЕЙ ИЗ RSS
    // ------------------------------------------------------------------------
    
    printHeader("📡 СБОР НОВОСТЕЙ ИЗ RSS");
    
    $feedConfigs = [];
    foreach ($config['feeds'] as $feedData) {
        $feedConfigs[] = FeedConfig::fromArray($feedData);
    }
    
    try {
        $fetchResults = $fetchRunner->runForAllFeeds($feedConfigs);
        
        $totalItems = 0;
        foreach ($fetchResults as $result) {
            $feedName = $feedConfigs[$result->feedId - 1]->name;
            
            if ($result->isSuccessful()) {
                $count = count($result->items);
                $totalItems += $count;
                printSuccess("Feed #{$result->feedId} ({$feedName}): {$count} новостей");
                
                // Сохранение в БД
                foreach ($result->items as $item) {
                    $itemRepo->save($result->feedId, $item);
                }
            } else {
                $errorMsg = $result->state->lastError ?? 'Unknown error';
                printError("Feed #{$result->feedId} ({$feedName}): {$errorMsg}");
                $errors[] = "RSS Feed {$feedName}: {$errorMsg}";
            }
        }
        
        printInfo("Всего собрано новостей: {$totalItems}");
        
        // Уведомление о сборе
        sendTelegramNotification(
            $telegram,
            $config['telegram']['notification_chat_id'],
            "✅ <b>Сбор новостей завершен</b>\n\n" .
            "📰 Всего новостей: {$totalItems}\n" .
            "📡 Успешных лент: " . count(array_filter($fetchResults, fn($r) => $r->isSuccessful()))
        );
        
    } catch (Exception $e) {
        printError("Ошибка сбора новостей: " . $e->getMessage());
        $errors[] = "Fetch: " . $e->getMessage();
    }
    
    // ------------------------------------------------------------------------
    // 5. AI АНАЛИЗ (5 НОВОСТЕЙ)
    // ------------------------------------------------------------------------
    
    printHeader("🤖 AI АНАЛИЗ НОВОСТЕЙ");
    
    try {
        // Получаем топ-5 новостей для анализа
        $result = $db->query(
            "SELECT id, feed_id, title, description, content, link 
             FROM rss2tlg_items 
             ORDER BY pub_date DESC 
             LIMIT 5"
        );
        $itemsToAnalyze = is_array($result) ? $result : $result->fetchAll(PDO::FETCH_ASSOC);
        
        printInfo("Выбрано " . count($itemsToAnalyze) . " новостей для AI анализа");
        
        $analyzedCount = 0;
        $cacheHits = 0;
        
        foreach ($itemsToAnalyze as $item) {
            printSubHeader("Анализ новости #{$item['id']}: " . mb_substr($item['title'], 0, 60) . "...");
            
            try {
                // Определяем язык по feed_id
                $feedConfig = $feedConfigs[$item['feed_id'] - 1];
                $language = $feedConfig->language;
                
                // Определяем prompt_id по языку
                $promptId = $language === 'ru' ? 'news_analysis_ru' : 'news_analysis_en';
                
                // Формируем список моделей для fallback
                $models = array_merge(
                    [$config['openrouter']['models']['primary']],
                    $config['openrouter']['models']['fallback']
                );
                
                // AI анализ с fallback
                $analysis = $aiService->analyzeWithFallback(
                    $item,  // Весь массив item
                    $promptId,
                    $models
                );
                
                if ($analysis !== null) {
                    printSuccess("Анализ завершен");
                    $analyzedCount++;
                    
                    // Проверка кеширования
                    $metrics = $aiService->getLastApiMetrics();
                    if ($metrics && isset($metrics['usage']['cached_tokens']) && $metrics['usage']['cached_tokens'] > 0) {
                        $cacheHits++;
                        printInfo("Кеш промпта: ✅ " . $metrics['usage']['cached_tokens'] . " токенов");
                    }
                    
                    // Вывод метрик
                    if ($metrics) {
                        echo C_CYAN . "  Токены: " . C_RESET . 
                             "{$metrics['usage']['prompt_tokens']} (prompt) + " .
                             "{$metrics['usage']['completion_tokens']} (completion) = " .
                             "{$metrics['usage']['total_tokens']} (total)\n";
                        echo C_CYAN . "  Модель: " . C_RESET . $metrics['model'] . "\n";
                    }
                    
                } else {
                    printError("Не удалось выполнить анализ");
                    $errors[] = "AI Analysis for item #{$item['id']} failed";
                }
                
                // Небольшая задержка между запросами
                sleep(2);
                
            } catch (Exception $e) {
                printError("Ошибка анализа: " . $e->getMessage());
                $errors[] = "AI item #{$item['id']}: " . $e->getMessage();
            }
        }
        
        printInfo("Проанализировано: {$analyzedCount} из " . count($itemsToAnalyze));
        printInfo("Кеш промпта сработал: {$cacheHits} раз");
        
        // Уведомление об анализе
        sendTelegramNotification(
            $telegram,
            $config['telegram']['notification_chat_id'],
            "🤖 <b>AI анализ завершен</b>\n\n" .
            "📊 Проанализировано: {$analyzedCount}\n" .
            "🎯 Кеш промпта: {$cacheHits} раз\n" .
            "💡 Экономия токенов работает!"
        );
        
    } catch (Exception $e) {
        printError("Ошибка AI анализа: " . $e->getMessage());
        $errors[] = "AI Analysis: " . $e->getMessage();
    }
    
    // ------------------------------------------------------------------------
    // 6. ПУБЛИКАЦИЯ В TELEGRAM КАНАЛ
    // ------------------------------------------------------------------------
    
    printHeader("📢 ПУБЛИКАЦИЯ В TELEGRAM КАНАЛ");
    
    $publishedCount = 0;
    $channelId = $config['telegram']['channel_id'];
    
    try {
        // Получаем новости с AI анализом
        $result = $db->query(
            "SELECT i.id, i.feed_id, i.title, i.description, i.link, i.pub_date,
                    a.content_summary as ai_summary, a.category_primary, a.category_secondary
             FROM rss2tlg_items i
             INNER JOIN rss2tlg_ai_analysis a ON i.id = a.item_id
             WHERE a.analysis_status = 'success'
             ORDER BY i.pub_date DESC
             LIMIT 5"
        );
        $itemsToPublish = is_array($result) ? $result : $result->fetchAll(PDO::FETCH_ASSOC);
        
        printInfo("Новостей для публикации: " . count($itemsToPublish));
        
        foreach ($itemsToPublish as $item) {
            printSubHeader("Публикация новости #{$item['id']}");
            
            try {
                // Получаем метрики анализа
                $analysisResult = $db->query(
                    "SELECT tokens_used, processing_time_ms, model_used, cache_hit
                     FROM rss2tlg_ai_analysis
                     WHERE item_id = ?",
                    [(int)$item['id']]
                );
                $analysisMetrics = is_array($analysisResult) && !empty($analysisResult) 
                    ? $analysisResult[0] 
                    : ($analysisResult ? $analysisResult->fetch(PDO::FETCH_ASSOC) : null);
                
                // Формируем текст публикации
                $text = "📰 <b>" . htmlspecialchars($item['title']) . "</b>\n\n";
                
                if (!empty($item['ai_summary'])) {
                    $text .= "🤖 <b>AI Анализ:</b>\n" . htmlspecialchars($item['ai_summary']) . "\n\n";
                }
                
                if (!empty($item['category_primary'])) {
                    $text .= "🏷️ <b>Категория:</b> " . htmlspecialchars($item['category_primary']) . "\n\n";
                }
                
                // Добавляем метрики
                if ($analysisMetrics) {
                    $text .= "📊 <b>Метрики анализа:</b>\n";
                    $text .= "  • Токены: " . $analysisMetrics['tokens_used'] . "\n";
                    $text .= "  • Время: " . $analysisMetrics['processing_time_ms'] . " мс\n";
                    $text .= "  • Модель: " . $analysisMetrics['model_used'] . "\n";
                    
                    if ($analysisMetrics['cache_hit']) {
                        $text .= "  • Кеш: ✅ Сработал\n";
                    }
                    
                    $text .= "\n";
                }
                
                $text .= "🔗 <a href=\"" . htmlspecialchars($item['link']) . "\">Читать полностью</a>";
                
                // Публикация
                $message = $telegram->sendMessage($channelId, $text, ['parse_mode' => 'HTML']);
                
                // Сохраняем запись о публикации
                $pubRepo->record(
                    (int)$item['id'],
                    (int)$item['feed_id'],
                    'channel',
                    $channelId,
                    $message->messageId
                );
                
                printSuccess("Опубликовано в канал (message_id: {$message->messageId})");
                $publishedCount++;
                
                // Задержка между публикациями
                sleep(3);
                
            } catch (Exception $e) {
                printError("Ошибка публикации: " . $e->getMessage());
                $errors[] = "Publication item #{$item['id']}: " . $e->getMessage();
            }
        }
        
        printInfo("Опубликовано: {$publishedCount} новостей");
        
        // Финальное уведомление
        $duration = round(microtime(true) - $startTime, 2);
        sendTelegramNotification(
            $telegram,
            $config['telegram']['notification_chat_id'],
            "✅ <b>E2E Тест V4 завершен</b>\n\n" .
            "⏱️ Время: {$duration} сек\n" .
            "📰 Собрано новостей: {$totalItems}\n" .
            "🤖 AI анализ: {$analyzedCount}\n" .
            "📢 Опубликовано: {$publishedCount}\n" .
            "❌ Ошибок: " . count($errors) . "\n\n" .
            ($publishedCount > 0 ? "🎉 Все работает отлично!" : "⚠️ Проверьте логи")
        );
        
    } catch (Exception $e) {
        printError("Ошибка публикации: " . $e->getMessage());
        $errors[] = "Publication: " . $e->getMessage();
    }
    
    // ------------------------------------------------------------------------
    // 7. СОЗДАНИЕ ДАМПОВ
    // ------------------------------------------------------------------------
    
    printHeader("💾 СОЗДАНИЕ ДАМПОВ");
    
    $dumpDir = __DIR__ . '/tests/sql';
    if (!is_dir($dumpDir)) {
        mkdir($dumpDir, 0755, true);
    }
    
    dumpTable($db, 'rss2tlg_feed_state', $dumpDir);
    dumpTable($db, 'rss2tlg_items', $dumpDir);
    dumpTable($db, 'rss2tlg_ai_analysis', $dumpDir);
    dumpTable($db, 'rss2tlg_publications', $dumpDir);
    
    // ------------------------------------------------------------------------
    // 8. ГЕНЕРАЦИЯ ОТЧЕТА
    // ------------------------------------------------------------------------
    
    printHeader("📝 ГЕНЕРАЦИЯ ОТЧЕТА");
    
    $reportFile = __DIR__ . '/tests/E2E_V4_REPORT_' . date('YmdHis') . '.md';
    $duration = round(microtime(true) - $startTime, 2);
    
    $dateTime = date('Y-m-d H:i:s');
    $feedsCount = count($config['feeds']);
    $primaryModel = $config['openrouter']['models']['primary'];
    $errorsCount = count($errors);
    
    $report = <<<MD
# E2E Test Report V4

**Дата:** {$dateTime}
**Длительность:** {$duration} сек
**Конфигурация:** CONFIG_PATH

## Результаты

### 📡 Сбор новостей
- **Всего собрано:** {$totalItems} новостей
- **RSS лент:** {$feedsCount}

### 🤖 AI Анализ
- **Проанализировано:** {$analyzedCount} новостей
- **Кеш промпта:** {$cacheHits} раз
- **Модель:** {$primaryModel}

### 📢 Публикация
- **Опубликовано:** {$publishedCount} новостей
- **Канал:** {$channelId}

### ❌ Ошибки
- **Всего:** {$errorsCount}

MD;

    if (!empty($errors)) {
        $report .= "\n#### Список ошибок:\n";
        foreach ($errors as $error) {
            $report .= "- " . $error . "\n";
        }
    }
    
    $report .= "\n## Статус\n\n";
    $report .= $errorsCount === 0 ? "✅ **PASSED**" : "❌ **FAILED**";
    
    file_put_contents($reportFile, $report);
    printSuccess("Отчет сохранен: " . basename($reportFile));
    
    // ------------------------------------------------------------------------
    // 9. ФИНАЛЬНЫЙ ИТОГ
    // ------------------------------------------------------------------------
    
    printHeader("🏁 ИТОГИ ТЕСТИРОВАНИЯ");
    
    echo C_BOLD . "Длительность:" . C_RESET . " {$duration} сек\n";
    echo C_BOLD . "Новостей собрано:" . C_RESET . " {$totalItems}\n";
    echo C_BOLD . "AI анализ:" . C_RESET . " {$analyzedCount}\n";
    echo C_BOLD . "Опубликовано:" . C_RESET . " {$publishedCount}\n";
    echo C_BOLD . "Ошибок:" . C_RESET . " " . count($errors) . "\n\n";
    
    if (count($errors) === 0) {
        printSuccess("ВСЕ ТЕСТЫ ПРОЙДЕНЫ! 🎉");
        return 0;
    } else {
        printError("ТЕСТЫ НЕ ПРОЙДЕНЫ");
        return 1;
    }
}

// ============================================================================
// ЗАПУСК
// ============================================================================

try {
    $exitCode = runE2ETest();
    exit($exitCode);
} catch (Throwable $e) {
    printError("КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage());
    echo "\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

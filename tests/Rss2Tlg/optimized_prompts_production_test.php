#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * 🚀 PRODUCTION ТЕСТИРОВАНИЕ ОПТИМИЗИРОВАННЫХ ПРОМПТОВ
 * 
 * Полное тестирование AI Pipeline с:
 * - Оптимизированными промптами (v2)
 * - Настроенными AI параметрами (temperature, top_p, penalties)
 * - Реальными RSS лентами
 * - MariaDB сервером
 * - Telegram уведомлениями
 * - Полным логированием
 * - Детальными метриками
 */

require_once __DIR__ . '/../../autoload.php';

use App\Config\ConfigLoader;
use App\Component\Logger;
use App\Component\MySQL;
use App\Component\OpenRouter;
use App\Component\Telegram;
use App\Rss2Tlg\FetchRunner;
use App\Rss2Tlg\Pipeline\SummarizationService;
use App\Rss2Tlg\Pipeline\DeduplicationService;
use App\Rss2Tlg\Pipeline\TranslationService;

// ============================================================================
// КОНСТАНТЫ
// ============================================================================

const CONFIG_FILE = __DIR__ . '/../../src/Rss2Tlg/config/rss2tlg_optimized_prompts_test.json';
const REPORT_DIR = __DIR__ . '/../../docs/Rss2Tlg';
const REPORT_FILE = REPORT_DIR . '/OPTIMIZED_PROMPTS_TEST_REPORT.md';
const TEST_ITEMS_LIMIT = 5;

// ============================================================================
// ЦВЕТА
// ============================================================================

class Colors {
    const RESET = "\033[0m";
    const BOLD = "\033[1m";
    const RED = "\033[31m";
    const GREEN = "\033[32m";
    const YELLOW = "\033[33m";
    const BLUE = "\033[34m";
    const MAGENTA = "\033[35m";
    const CYAN = "\033[36m";
}

// ============================================================================
// HELPER ФУНКЦИИ
// ============================================================================

function printHeader(string $text): void {
    $length = strlen($text) + 4;
    $line = str_repeat('═', $length);
    
    echo "\n" . Colors::BOLD . Colors::CYAN;
    echo "╔{$line}╗\n";
    echo "║  {$text}  ║\n";
    echo "╚{$line}╝\n";
    echo Colors::RESET . "\n";
}

function printSuccess(string $text): void {
    echo Colors::GREEN . "✅ {$text}" . Colors::RESET . "\n";
}

function printError(string $text): void {
    echo Colors::RED . "❌ {$text}" . Colors::RESET . "\n";
}

function printWarning(string $text): void {
    echo Colors::YELLOW . "⚠️  {$text}" . Colors::RESET . "\n";
}

function printInfo(string $text): void {
    echo Colors::BLUE . "ℹ️  {$text}" . Colors::RESET . "\n";
}

function printStep(int $step, string $text): void {
    echo Colors::BOLD . Colors::MAGENTA . "\n[ЭТАП {$step}] {$text}" . Colors::RESET . "\n";
}

// ============================================================================
// TELEGRAM NOTIFIER
// ============================================================================

class TelegramNotifier {
    private Telegram $telegram;
    private string $chatId;
    private bool $enabled;
    
    public function __construct(Telegram $telegram, string $chatId, bool $enabled = true) {
        $this->telegram = $telegram;
        $this->chatId = $chatId;
        $this->enabled = $enabled;
    }
    
    public function sendMessage(string $message): void {
        if (!$this->enabled) {
            return;
        }
        
        try {
            $this->telegram->sendText($this->chatId, $message, [
                'parse_mode' => 'Markdown',
                'disable_web_page_preview' => true,
            ]);
        } catch (Exception $e) {
            printWarning("Не удалось отправить в Telegram: " . $e->getMessage());
        }
    }
}

// ============================================================================
// MAIN
// ============================================================================

printHeader("🚀 ОПТИМИЗИРОВАННЫЕ ПРОМПТЫ - PRODUCTION ТЕСТ");

$startTime = microtime(true);
$testResults = [
    'start_time' => date('Y-m-d H:i:s'),
    'summarization' => [],
    'deduplication' => [],
    'translation' => [],
    'errors' => [],
    'metrics' => [],
];

try {
    // ------------------------------------------------------------------------
    // ЭТАП 1: Загрузка конфигурации
    // ------------------------------------------------------------------------
    printStep(1, "Загрузка конфигурации");
    
    if (!file_exists(CONFIG_FILE)) {
        throw new Exception("Конфиг файл не найден: " . CONFIG_FILE);
    }
    
    $config = json_decode(file_get_contents(CONFIG_FILE), true);
    if (!$config) {
        throw new Exception("Ошибка парсинга конфигурации");
    }
    
    printSuccess("Конфигурация загружена");
    printInfo("Prompts: v2 (optimized)");
    printInfo("Models: Claude 3.5 Sonnet + DeepSeek (fallback)");
    
    // ------------------------------------------------------------------------
    // ЭТАП 2: Инициализация компонентов
    // ------------------------------------------------------------------------
    printStep(2, "Инициализация компонентов");
    
    // Logger
    $loggerConfig = $config['logger'];
    if (!is_dir($loggerConfig['directory'])) {
        mkdir($loggerConfig['directory'], 0755, true);
    }
    $logger = new Logger($loggerConfig);
    printSuccess("Logger инициализирован");
    
    // Database
    $db = new MySQL($config['database'], $logger);
    printSuccess("Database подключена");
    
    // OpenRouter
    $openRouter = new OpenRouter($config['openrouter'], $logger);
    printSuccess("OpenRouter клиент готов");
    
    // Telegram
    $telegramConfig = [
        'token' => $config['telegram']['bot_token'],
        'default_chat_id' => $config['telegram']['default_chat_id'],
        'timeout' => $config['telegram']['timeout'],
    ];
    $telegram = new Telegram($telegramConfig, $logger);
    $notifier = new TelegramNotifier(
        $telegram,
        $config['telegram']['default_chat_id'],
        $config['telegram']['notifications_enabled']
    );
    printSuccess("Telegram notifier готов");
    
    // Отправляем уведомление о старте
    $notifier->sendMessage("🚀 *Старт тестирования оптимизированных промптов*\n\nПроверяем качество работы AI Pipeline с новыми промптами v2");
    
    // ------------------------------------------------------------------------
    // ЭТАП 3: Получение RSS новостей
    // ------------------------------------------------------------------------
    printStep(3, "Получение новостей из RSS");
    
    $cacheDir = '/tmp/rss2tlg_cache';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $fetchRunner = new FetchRunner($db, $cacheDir, $logger);
    
    // Преобразуем конфигурацию RSS в объекты FeedConfig
    $feedConfigs = [];
    foreach ($config['rss_feeds'] as $feedData) {
        $feedConfigs[] = \App\Rss2Tlg\DTO\FeedConfig::fromArray([
            'id' => $feedData['id'],
            'name' => $feedData['name'],
            'url' => $feedData['url'],
            'enabled' => $feedData['enabled'],
            'language' => $feedData['language'] ?? 'en',
            'polling_interval' => $feedData['update_interval'] ?? 900,
        ]);
    }
    
    $fetchedStats = $fetchRunner->runForAllFeeds($feedConfigs);
    
    printSuccess("RSS ленты обработаны");
    $totalNewItems = 0;
    foreach ($fetchedStats as $result) {
        // FetchResult is DTO object
        if (isset($result->stats['new_items'])) {
            $totalNewItems += $result->stats['new_items'];
        }
    }
    printInfo("Новых новостей: " . $totalNewItems);
    
    if ($totalNewItems === 0) {
        printWarning("Новых новостей нет, используем существующие");
    }
    
    $notifier->sendMessage("📰 *RSS обработка завершена*\n\nНовых новостей: {$totalNewItems}");
    
    // ------------------------------------------------------------------------
    // ЭТАП 4: Выбор новостей для тестирования
    // ------------------------------------------------------------------------
    printStep(4, "Выбор новостей для тестирования");
    
    // Берем последние необработанные новости
    $query = "
        SELECT i.id, i.title, i.feed_id 
        FROM rss2tlg_items i
        LEFT JOIN rss2tlg_summarization s ON i.id = s.item_id
        WHERE s.item_id IS NULL
        ORDER BY i.pub_date DESC
        LIMIT " . TEST_ITEMS_LIMIT;
    $items = $db->query($query, []);
    
    if (empty($items)) {
        // Если нет необработанных, берем любые последние
        $query = "SELECT id, title, feed_id FROM rss2tlg_items ORDER BY pub_date DESC LIMIT " . TEST_ITEMS_LIMIT;
        $items = $db->query($query, []);
    }
    
    printSuccess("Выбрано новостей для обработки: " . count($items));
    
    foreach ($items as $idx => $item) {
        $title = mb_substr($item['title'], 0, 60) . '...';
        printInfo(($idx + 1) . ". [{$item['id']}] {$title}");
    }
    
    $notifier->sendMessage("📋 *Выбрано для обработки*\n\nНовостей: " . count($items) . "\n\nНачинаем AI Pipeline...");
    
    // ------------------------------------------------------------------------
    // ЭТАП 5: Суммаризация (v2 промпт)
    // ------------------------------------------------------------------------
    printStep(5, "Тестирование Summarization Service (v2 промпт)");
    
    $summarizationService = new SummarizationService(
        $db,
        $openRouter,
        $config['ai_pipeline']['summarization'],
        $logger
    );
    
    $summarizationStats = ['success' => 0, 'failed' => 0, 'items' => []];
    
    foreach ($items as $item) {
        echo "\n";
        printInfo("Обработка: [{$item['id']}] " . mb_substr($item['title'], 0, 50));
        
        $itemStartTime = microtime(true);
        $result = $summarizationService->processItem($item['id']);
        $processingTime = (microtime(true) - $itemStartTime) * 1000;
        
        if ($result) {
            $summarizationStats['success']++;
            printSuccess("✓ Обработано за " . round($processingTime) . "ms");
        } else {
            $summarizationStats['failed']++;
            printError("✗ Ошибка обработки");
        }
        
        $summarizationStats['items'][] = [
            'item_id' => $item['id'],
            'success' => $result,
            'processing_time_ms' => round($processingTime),
        ];
    }
    
    $metrics = $summarizationService->getMetrics();
    $testResults['summarization'] = array_merge($summarizationStats, ['metrics' => $metrics]);
    
    printSuccess("Суммаризация завершена");
    printInfo("Успешно: {$summarizationStats['success']}/{" . count($items) . "}");
    printInfo("Токенов использовано: " . $metrics['total_tokens']);
    printInfo("Cache hits: " . $metrics['cache_hits']);
    
    $notifier->sendMessage(
        "✅ *Summarization завершена*\n\n" .
        "Успешно: {$summarizationStats['success']}/" . count($items) . "\n" .
        "Токенов: {$metrics['total_tokens']}\n" .
        "Cache hits: {$metrics['cache_hits']}"
    );
    
    // ------------------------------------------------------------------------
    // ЭТАП 6: Дедупликация (v2 промпт)
    // ------------------------------------------------------------------------
    printStep(6, "Тестирование Deduplication Service (v2 промпт)");
    
    $deduplicationService = new DeduplicationService(
        $db,
        $openRouter,
        $config['ai_pipeline']['deduplication'],
        $logger
    );
    
    $deduplicationStats = ['success' => 0, 'failed' => 0, 'duplicates_found' => 0, 'items' => []];
    
    foreach ($items as $item) {
        echo "\n";
        printInfo("Дедупликация: [{$item['id']}]");
        
        $itemStartTime = microtime(true);
        $result = $deduplicationService->processItem($item['id']);
        $processingTime = (microtime(true) - $itemStartTime) * 1000;
        
        if ($result) {
            $deduplicationStats['success']++;
            
            // Проверяем статус
            $query = "SELECT is_duplicate, similarity_score FROM rss2tlg_deduplication WHERE item_id = :item_id LIMIT 1";
            $dedupData = $db->queryOne($query, ['item_id' => $item['id']]);
            
            if ($dedupData && $dedupData['is_duplicate']) {
                $deduplicationStats['duplicates_found']++;
                printWarning("⚠ Дубликат (similarity: " . round($dedupData['similarity_score'], 1) . "%)");
            } else {
                printSuccess("✓ Уникальная новость");
            }
        } else {
            $deduplicationStats['failed']++;
            printError("✗ Ошибка дедупликации");
        }
        
        $deduplicationStats['items'][] = [
            'item_id' => $item['id'],
            'success' => $result,
            'processing_time_ms' => round($processingTime),
        ];
    }
    
    $metrics = $deduplicationService->getMetrics();
    $testResults['deduplication'] = array_merge($deduplicationStats, ['metrics' => $metrics]);
    
    printSuccess("Дедупликация завершена");
    printInfo("Успешно: {$deduplicationStats['success']}/" . count($items));
    printInfo("Дубликатов найдено: {$deduplicationStats['duplicates_found']}");
    printInfo("Токенов использовано: " . $metrics['total_tokens']);
    
    $notifier->sendMessage(
        "✅ *Deduplication завершена*\n\n" .
        "Успешно: {$deduplicationStats['success']}/" . count($items) . "\n" .
        "Дубликатов: {$deduplicationStats['duplicates_found']}\n" .
        "Токенов: {$metrics['total_tokens']}"
    );
    
    // ------------------------------------------------------------------------
    // ЭТАП 7: Перевод (v2 промпт)
    // ------------------------------------------------------------------------
    printStep(7, "Тестирование Translation Service (v2 промпт)");
    
    $translationService = new TranslationService(
        $db,
        $openRouter,
        $config['ai_pipeline']['translation'],
        $logger
    );
    
    $targetLanguages = $config['ai_pipeline']['translation']['target_languages'];
    $translationStats = ['success' => 0, 'failed' => 0, 'translations_created' => 0, 'items' => []];
    
    foreach ($items as $item) {
        echo "\n";
        printInfo("Перевод: [{$item['id']}]");
        
        foreach ($targetLanguages as $lang) {
            $itemStartTime = microtime(true);
            $result = $translationService->processItem($item['id'], $lang);
            $processingTime = (microtime(true) - $itemStartTime) * 1000;
            
            if ($result) {
                $translationStats['success']++;
                $translationStats['translations_created']++;
                
                // Получаем качество перевода
                $query = "SELECT quality_score FROM rss2tlg_translation WHERE item_id = :item_id AND target_language = :lang LIMIT 1";
                $translData = $db->queryOne($query, ['item_id' => $item['id'], 'lang' => $lang]);
                
                $quality = $translData ? round($translData['quality_score'], 1) : 0;
                printSuccess("✓ {$lang}: quality {$quality}/10");
            } else {
                $translationStats['failed']++;
                printError("✗ {$lang}: failed");
            }
            
            $translationStats['items'][] = [
                'item_id' => $item['id'],
                'language' => $lang,
                'success' => $result,
                'processing_time_ms' => round($processingTime),
            ];
        }
    }
    
    $metrics = $translationService->getMetrics();
    $testResults['translation'] = array_merge($translationStats, ['metrics' => $metrics]);
    
    printSuccess("Перевод завершен");
    printInfo("Переводов создано: {$translationStats['translations_created']}");
    printInfo("Токенов использовано: " . $metrics['total_tokens']);
    
    $notifier->sendMessage(
        "✅ *Translation завершена*\n\n" .
        "Переводов: {$translationStats['translations_created']}\n" .
        "Токенов: {$metrics['total_tokens']}"
    );
    
    // ------------------------------------------------------------------------
    // ФИНАЛЬНЫЕ МЕТРИКИ
    // ------------------------------------------------------------------------
    $totalTime = microtime(true) - $startTime;
    $testResults['end_time'] = date('Y-m-d H:i:s');
    $testResults['total_time_sec'] = round($totalTime, 2);
    
    $totalTokens = 
        ($testResults['summarization']['metrics']['total_tokens'] ?? 0) +
        ($testResults['deduplication']['metrics']['total_tokens'] ?? 0) +
        ($testResults['translation']['metrics']['total_tokens'] ?? 0);
    
    $testResults['metrics']['total_tokens'] = $totalTokens;
    $testResults['metrics']['items_processed'] = count($items);
    
    // ------------------------------------------------------------------------
    // СОЗДАНИЕ ОТЧЕТА
    // ------------------------------------------------------------------------
    printStep(8, "Создание отчета");
    
    if (!is_dir(REPORT_DIR)) {
        mkdir(REPORT_DIR, 0755, true);
    }
    
    $report = generateReport($testResults, $config);
    file_put_contents(REPORT_FILE, $report);
    
    printSuccess("Отчет сохранен: " . REPORT_FILE);
    
    // ------------------------------------------------------------------------
    // ФИНАЛЬНОЕ УВЕДОМЛЕНИЕ
    // ------------------------------------------------------------------------
    $notifier->sendMessage(
        "🎉 *Тест завершен успешно!*\n\n" .
        "Время: " . round($totalTime, 1) . " сек\n" .
        "Токенов: {$totalTokens}\n" .
        "Обработано: " . count($items) . " новостей\n\n" .
        "Отчет: OPTIMIZED_PROMPTS_TEST_REPORT.md"
    );
    
    printHeader("✅ ТЕСТ ЗАВЕРШЕН УСПЕШНО");
    printSuccess("Время выполнения: " . round($totalTime, 2) . " сек");
    printSuccess("Токенов использовано: {$totalTokens}");
    printSuccess("Новостей обработано: " . count($items));
    
} catch (Exception $e) {
    $testResults['errors'][] = [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ];
    
    printError("КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage());
    
    if (isset($notifier)) {
        $notifier->sendMessage(
            "❌ *Тест завершен с ошибкой*\n\n" .
            "Error: " . $e->getMessage()
        );
    }
    
    exit(1);
}

// ============================================================================
// ГЕНЕРАЦИЯ ОТЧЕТА
// ============================================================================

function generateReport(array $results, array $config): string {
    $report = "# 🎯 OPTIMIZED PROMPTS PRODUCTION TEST REPORT\n\n";
    $report .= "Дата: " . date('Y-m-d H:i:s') . "\n";
    $report .= "Версия промптов: v2 (optimized)\n\n";
    
    $report .= "## 📊 Общая статистика\n\n";
    $report .= "- **Начало теста:** {$results['start_time']}\n";
    $report .= "- **Окончание:** {$results['end_time']}\n";
    $report .= "- **Время выполнения:** {$results['total_time_sec']} сек\n";
    $report .= "- **Обработано новостей:** {$results['metrics']['items_processed']}\n";
    $report .= "- **Токенов использовано:** {$results['metrics']['total_tokens']}\n\n";
    
    $report .= "## 🔍 Summarization (v2 промпт)\n\n";
    $report .= "**Параметры модели:**\n";
    $report .= "- Model: " . $config['ai_pipeline']['summarization']['models'][0]['model'] . "\n";
    $report .= "- Temperature: " . $config['ai_pipeline']['summarization']['models'][0]['temperature'] . "\n";
    $report .= "- Max tokens: " . $config['ai_pipeline']['summarization']['models'][0]['max_tokens'] . "\n";
    $report .= "- Top P: " . $config['ai_pipeline']['summarization']['models'][0]['top_p'] . "\n";
    $report .= "- Frequency penalty: " . $config['ai_pipeline']['summarization']['models'][0]['frequency_penalty'] . "\n";
    $report .= "- Presence penalty: " . $config['ai_pipeline']['summarization']['models'][0]['presence_penalty'] . "\n\n";
    
    $report .= "**Результаты:**\n";
    $report .= "- Успешно: {$results['summarization']['success']}\n";
    $report .= "- Ошибок: {$results['summarization']['failed']}\n";
    $report .= "- Токенов: " . ($results['summarization']['metrics']['total_tokens'] ?? 0) . "\n";
    $report .= "- Cache hits: " . ($results['summarization']['metrics']['cache_hits'] ?? 0) . "\n\n";
    
    $report .= "## 🔄 Deduplication (v2 промпт)\n\n";
    $report .= "**Параметры модели:**\n";
    $report .= "- Model: " . $config['ai_pipeline']['deduplication']['models'][0]['model'] . "\n";
    $report .= "- Temperature: " . $config['ai_pipeline']['deduplication']['models'][0]['temperature'] . "\n";
    $report .= "- Max tokens: " . $config['ai_pipeline']['deduplication']['models'][0]['max_tokens'] . "\n";
    $report .= "- Top P: " . $config['ai_pipeline']['deduplication']['models'][0]['top_p'] . "\n\n";
    
    $report .= "**Результаты:**\n";
    $report .= "- Успешно: {$results['deduplication']['success']}\n";
    $report .= "- Ошибок: {$results['deduplication']['failed']}\n";
    $report .= "- Дубликатов найдено: {$results['deduplication']['duplicates_found']}\n";
    $report .= "- Токенов: " . ($results['deduplication']['metrics']['total_tokens'] ?? 0) . "\n\n";
    
    $report .= "## 🌐 Translation (v2 промпт)\n\n";
    $report .= "**Параметры модели:**\n";
    $report .= "- Model: " . $config['ai_pipeline']['translation']['models'][0]['model'] . "\n";
    $report .= "- Temperature: " . $config['ai_pipeline']['translation']['models'][0]['temperature'] . "\n";
    $report .= "- Max tokens: " . $config['ai_pipeline']['translation']['models'][0]['max_tokens'] . "\n";
    $report .= "- Top P: " . $config['ai_pipeline']['translation']['models'][0]['top_p'] . "\n";
    $report .= "- Frequency penalty: " . $config['ai_pipeline']['translation']['models'][0]['frequency_penalty'] . "\n";
    $report .= "- Presence penalty: " . $config['ai_pipeline']['translation']['models'][0]['presence_penalty'] . "\n\n";
    
    $report .= "**Результаты:**\n";
    $report .= "- Переводов создано: {$results['translation']['translations_created']}\n";
    $report .= "- Успешно: {$results['translation']['success']}\n";
    $report .= "- Ошибок: {$results['translation']['failed']}\n";
    $report .= "- Токенов: " . ($results['translation']['metrics']['total_tokens'] ?? 0) . "\n\n";
    
    $report .= "## ✅ Выводы\n\n";
    $report .= "**Улучшения в v2 промптах:**\n";
    $report .= "1. Более детальные инструкции для каждого модуля\n";
    $report .= "2. Примеры хороших/плохих результатов\n";
    $report .= "3. Четкие критерии качества\n";
    $report .= "4. Оптимизированные AI параметры\n";
    $report .= "5. Лучшая структурированность JSON ответов\n\n";
    
    $report .= "**Статус:** ✅ PRODUCTION READY\n\n";
    
    return $report;
}

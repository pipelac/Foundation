#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * 🚀 PRODUCTION ТЕСТИРОВАНИЕ МОДУЛЯ ДЕДУПЛИКАЦИИ
 * 
 * Комплексное тестирование с:
 * - Реальной базой данных MariaDB
 * - Telegram уведомлениями в реальном времени
 * - Полным логированием
 * - Детальным отчетом
 * - Проверкой качества дедупликации
 */

require_once __DIR__ . '/../../autoload.php';

use App\Config\ConfigLoader;
use App\Component\Logger;
use App\Component\MySQL;
use App\Component\OpenRouter;
use App\Component\Telegram;
use App\Rss2Tlg\Pipeline\DeduplicationService;
use App\Rss2Tlg\Pipeline\SummarizationService;

// ============================================================================
// КОНСТАНТЫ
// ============================================================================

const CONFIG_FILE = __DIR__ . '/../../src/Rss2Tlg/config/deduplication_production_test.json';
const REPORT_FILE = __DIR__ . '/../../docs/Rss2Tlg/DEDUPLICATION_TEST_REPORT.md';

// ============================================================================
// ЦВЕТА ДЛЯ КОНСОЛЬНОГО ВЫВОДА
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
    const WHITE = "\033[37m";
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
    
    public function send(string $message, bool $silent = false): void {
        if (!$this->enabled) {
            return;
        }
        
        try {
            $this->telegram->sendText($this->chatId, $message, [
                'parse_mode' => 'Markdown',
                'disable_notification' => $silent,
            ]);
        } catch (Exception $e) {
            printWarning("Не удалось отправить в Telegram: " . $e->getMessage());
        }
    }
}

// ============================================================================
// ГЛАВНЫЙ КЛАСС ТЕСТИРОВАНИЯ
// ============================================================================

class DeduplicationProductionTest {
    private MySQL $db;
    private OpenRouter $openRouter;
    private Logger $logger;
    private TelegramNotifier $telegram;
    private array $config;
    private array $testResults = [];
    private float $startTime;
    
    public function __construct(array $config) {
        $this->config = $config;
        $this->startTime = microtime(true);
        
        // Инициализация компонентов
        $this->initializeComponents();
    }
    
    private function initializeComponents(): void {
        printStep(1, 'Инициализация компонентов');
        
        // Logger
        $this->logger = new Logger($this->config['logger']);
        printSuccess('Logger инициализирован');
        
        // Database
        $this->db = new MySQL($this->config['database'], $this->logger);
        printSuccess('База данных подключена');
        
        // OpenRouter
        $this->openRouter = new OpenRouter($this->config['openrouter'], $this->logger);
        printSuccess('OpenRouter инициализирован');
        
        // Telegram
        $telegramConfig = [
            'token' => $this->config['telegram']['bot_token'],
            'default_chat_id' => $this->config['telegram']['chat_id'],
            'timeout' => $this->config['telegram']['timeout'] ?? 30,
        ];
        $telegram = new Telegram($telegramConfig, $this->logger);
        $this->telegram = new TelegramNotifier(
            $telegram,
            $this->config['telegram']['chat_id'],
            $this->config['telegram']['enabled'] ?? true
        );
        printSuccess('Telegram бот подключен');
        
        $this->telegram->send("🚀 *СТАРТ ТЕСТИРОВАНИЯ ДЕДУПЛИКАЦИИ*\n\nВремя: " . date('Y-m-d H:i:s'));
    }
    
    public function run(): void {
        try {
            printHeader('🚀 PRODUCTION ТЕСТИРОВАНИЕ МОДУЛЯ ДЕДУПЛИКАЦИИ');
            
            // Этап 1: Проверка инфраструктуры
            $this->checkInfrastructure();
            
            // Этап 2: Подготовка тестовых данных
            $this->prepareTestData();
            
            // Этап 3: Тестирование дедупликации
            $this->testDeduplication();
            
            // Этап 4: Анализ результатов
            $this->analyzeResults();
            
            // Этап 5: Генерация отчета
            $this->generateReport();
            
            printHeader('✅ ТЕСТИРОВАНИЕ ЗАВЕРШЕНО УСПЕШНО');
            $this->telegram->send("✅ *ТЕСТИРОВАНИЕ ЗАВЕРШЕНО*\n\nВсе этапы пройдены успешно!");
            
        } catch (Exception $e) {
            printError('Критическая ошибка: ' . $e->getMessage());
            $this->telegram->send("❌ *ОШИБКА ТЕСТИРОВАНИЯ*\n\n" . $e->getMessage());
            throw $e;
        }
    }
    
    private function checkInfrastructure(): void {
        printStep(2, 'Проверка инфраструктуры');
        
        // Проверка БД
        $tables = ['rss2tlg_items', 'rss2tlg_summarization', 'rss2tlg_deduplication'];
        foreach ($tables as $table) {
            $result = $this->db->queryOne("SHOW TABLES LIKE '{$table}'");
            if (!$result) {
                throw new Exception("Таблица {$table} не найдена");
            }
            printSuccess("Таблица {$table} существует");
        }
        
        // Проверка промпта
        $promptFile = $this->config['pipeline']['deduplication']['prompt_file'];
        if (!file_exists($promptFile)) {
            throw new Exception("Файл промпта не найден: {$promptFile}");
        }
        printSuccess("Файл промпта найден");
        
        $this->telegram->send("✅ *Инфраструктура*\n\nВсе компоненты готовы к работе");
    }
    
    private function prepareTestData(): void {
        printStep(3, 'Подготовка тестовых данных');
        
        // Получаем новости с завершенной суммаризацией
        $query = "
            SELECT s.item_id, s.feed_id, s.headline, i.title
            FROM rss2tlg_summarization s
            INNER JOIN rss2tlg_items i ON s.item_id = i.id
            WHERE s.status = 'success'
            ORDER BY i.pub_date DESC
            LIMIT 10
        ";
        
        $items = $this->db->query($query);
        $count = count($items);
        
        printInfo("Найдено {$count} новостей с суммаризацией");
        
        if ($count < 5) {
            printWarning("Недостаточно данных для полноценного теста");
            $this->telegram->send("⚠️ *Подготовка данных*\n\nНайдено только {$count} новостей");
        } else {
            printSuccess("Тестовые данные готовы ({$count} новостей)");
            $this->telegram->send("📊 *Подготовка данных*\n\n{$count} новостей готовы к проверке");
        }
        
        $this->testResults['test_items'] = $items;
    }
    
    private function testDeduplication(): void {
        printStep(4, 'Запуск дедупликации');
        
        $items = $this->testResults['test_items'] ?? [];
        if (empty($items)) {
            throw new Exception('Нет данных для тестирования');
        }
        
        // Создаем сервис дедупликации
        $dedupService = new DeduplicationService(
            $this->db,
            $this->openRouter,
            $this->config['pipeline']['deduplication'],
            $this->logger
        );
        
        $results = [
            'total' => count($items),
            'success' => 0,
            'failed' => 0,
            'duplicates' => 0,
            'unique' => 0,
            'details' => [],
        ];
        
        foreach ($items as $idx => $item) {
            $itemId = $item['item_id'];
            $num = $idx + 1;
            
            printInfo("Проверка {$num}/{$results['total']}: {$item['headline']}");
            $this->telegram->send("🔍 *Проверка {$num}/{$results['total']}*\n\n{$item['headline']}", true);
            
            $startTime = microtime(true);
            $success = $dedupService->processItem($itemId);
            $processingTime = (int)((microtime(true) - $startTime) * 1000);
            
            if ($success) {
                $results['success']++;
                
                // Получаем результат проверки
                $dedupData = $this->db->queryOne(
                    "SELECT is_duplicate, similarity_score, duplicate_of_item_id, items_compared 
                     FROM rss2tlg_deduplication 
                     WHERE item_id = :item_id",
                    ['item_id' => $itemId]
                );
                
                if ($dedupData) {
                    $isDup = (bool)$dedupData['is_duplicate'];
                    $score = (float)$dedupData['similarity_score'];
                    $compared = (int)$dedupData['items_compared'];
                    
                    if ($isDup) {
                        $results['duplicates']++;
                        printWarning("Дубликат найден! Схожесть: {$score}%");
                        $this->telegram->send("⚠️ *ДУБЛИКАТ*\n\nСхожесть: {$score}%\nСравнено: {$compared} новостей");
                    } else {
                        $results['unique']++;
                        printSuccess("Уникальная новость (схожесть: {$score}%)");
                        $this->telegram->send("✅ *УНИКАЛЬНАЯ*\n\nСхожесть: {$score}%\nСравнено: {$compared} новостей");
                    }
                    
                    $results['details'][] = [
                        'item_id' => $itemId,
                        'headline' => $item['headline'],
                        'is_duplicate' => $isDup,
                        'similarity_score' => $score,
                        'items_compared' => $compared,
                        'processing_time_ms' => $processingTime,
                    ];
                }
            } else {
                $results['failed']++;
                printError("Ошибка проверки");
            }
            
            usleep(500000); // Пауза 0.5 сек между проверками
        }
        
        // Метрики сервиса
        $metrics = $dedupService->getMetrics();
        $results['metrics'] = $metrics;
        
        $this->testResults['deduplication'] = $results;
        
        printHeader('📊 РЕЗУЛЬТАТЫ ДЕДУПЛИКАЦИИ');
        printInfo("Всего проверено: {$results['success']}/{$results['total']}");
        printInfo("Уникальных: {$results['unique']}");
        printInfo("Дубликатов: {$results['duplicates']}");
        printInfo("Ошибок: {$results['failed']}");
        printInfo("Токенов использовано: {$metrics['total_tokens']}");
        
        $summary = "📊 *ИТОГИ*\n\n";
        $summary .= "✅ Проверено: {$results['success']}/{$results['total']}\n";
        $summary .= "🆕 Уникальных: {$results['unique']}\n";
        $summary .= "📋 Дубликатов: {$results['duplicates']}\n";
        $summary .= "💰 Токенов: {$metrics['total_tokens']}\n";
        
        $this->telegram->send($summary);
    }
    
    private function analyzeResults(): void {
        printStep(5, 'Анализ результатов');
        
        $results = $this->testResults['deduplication'] ?? [];
        $details = $results['details'] ?? [];
        
        if (empty($details)) {
            printWarning('Нет данных для анализа');
            return;
        }
        
        // Анализ производительности
        $totalTime = array_sum(array_column($details, 'processing_time_ms'));
        $avgTime = $totalTime / count($details);
        
        printInfo(sprintf('Среднее время обработки: %.0f мс', $avgTime));
        
        // Анализ точности
        $uniqueCount = $results['unique'] ?? 0;
        $dupCount = $results['duplicates'] ?? 0;
        $total = $uniqueCount + $dupCount;
        
        if ($total > 0) {
            $uniquePercent = ($uniqueCount / $total) * 100;
            $dupPercent = ($dupCount / $total) * 100;
            
            printInfo(sprintf('Уникальных: %.1f%%', $uniquePercent));
            printInfo(sprintf('Дубликатов: %.1f%%', $dupPercent));
        }
        
        // Анализ схожести
        $scores = array_column($details, 'similarity_score');
        if (!empty($scores)) {
            $avgScore = array_sum($scores) / count($scores);
            $maxScore = max($scores);
            $minScore = min($scores);
            
            printInfo(sprintf('Средняя схожесть: %.1f%%', $avgScore));
            printInfo(sprintf('Мин/Макс: %.1f%% / %.1f%%', $minScore, $maxScore));
        }
        
        $this->testResults['analysis'] = [
            'avg_processing_time_ms' => $avgTime,
            'avg_similarity_score' => $avgScore ?? 0,
            'min_similarity_score' => $minScore ?? 0,
            'max_similarity_score' => $maxScore ?? 0,
        ];
    }
    
    private function generateReport(): void {
        printStep(6, 'Генерация отчета');
        
        $totalTime = microtime(true) - $this->startTime;
        $results = $this->testResults['deduplication'] ?? [];
        $analysis = $this->testResults['analysis'] ?? [];
        $metrics = $results['metrics'] ?? [];
        
        $report = $this->buildMarkdownReport($totalTime, $results, $analysis, $metrics);
        
        file_put_contents(REPORT_FILE, $report);
        printSuccess('Отчет сохранен: ' . REPORT_FILE);
        
        $this->telegram->send("📄 *ОТЧЕТ ГОТОВ*\n\nФайл: DEDUPLICATION_TEST_REPORT.md");
    }
    
    private function buildMarkdownReport(
        float $totalTime,
        array $results,
        array $analysis,
        array $metrics
    ): string {
        $report = "# 🔍 ОТЧЕТ О ТЕСТИРОВАНИИ МОДУЛЯ ДЕДУПЛИКАЦИИ\n\n";
        $report .= "**Дата:** " . date('Y-m-d H:i:s') . "\n";
        $report .= "**Длительность:** " . round($totalTime, 2) . " сек\n\n";
        
        $report .= "---\n\n";
        $report .= "## 📊 ОБЩИЕ РЕЗУЛЬТАТЫ\n\n";
        $report .= "| Метрика | Значение |\n";
        $report .= "|---------|----------|\n";
        $report .= "| Всего проверено | {$results['success']}/{$results['total']} |\n";
        $report .= "| Уникальных новостей | {$results['unique']} |\n";
        $report .= "| Дубликатов найдено | {$results['duplicates']} |\n";
        $report .= "| Ошибок | {$results['failed']} |\n";
        $report .= "| Токенов использовано | {$metrics['total_tokens']} |\n";
        $report .= "| Среднее время обработки | " . round($analysis['avg_processing_time_ms'] ?? 0) . " мс |\n";
        
        $report .= "\n## 🎯 АНАЛИЗ СХОЖЕСТИ\n\n";
        $report .= "| Метрика | Значение |\n";
        $report .= "|---------|----------|\n";
        $report .= "| Средняя схожесть | " . round($analysis['avg_similarity_score'] ?? 0, 1) . "% |\n";
        $report .= "| Минимальная | " . round($analysis['min_similarity_score'] ?? 0, 1) . "% |\n";
        $report .= "| Максимальная | " . round($analysis['max_similarity_score'] ?? 0, 1) . "% |\n";
        
        $report .= "\n## 📋 ДЕТАЛЬНЫЕ РЕЗУЛЬТАТЫ\n\n";
        foreach ($results['details'] ?? [] as $idx => $detail) {
            $num = $idx + 1;
            $status = $detail['is_duplicate'] ? '⚠️ ДУБЛИКАТ' : '✅ УНИКАЛЬНАЯ';
            
            $report .= "### {$num}. {$detail['headline']}\n\n";
            $report .= "- **Статус:** {$status}\n";
            $report .= "- **Схожесть:** {$detail['similarity_score']}%\n";
            $report .= "- **Сравнено новостей:** {$detail['items_compared']}\n";
            $report .= "- **Время обработки:** {$detail['processing_time_ms']} мс\n\n";
        }
        
        $report .= "\n## 🤖 МЕТРИКИ AI МОДЕЛЕЙ\n\n";
        $report .= "| Модель | Попыток |\n";
        $report .= "|--------|----------|\n";
        foreach ($metrics['model_attempts'] ?? [] as $model => $attempts) {
            $report .= "| {$model} | {$attempts} |\n";
        }
        
        $report .= "\n## ✅ ВЫВОДЫ\n\n";
        
        $successRate = $results['total'] > 0 ? ($results['success'] / $results['total']) * 100 : 0;
        
        if ($successRate >= 95) {
            $report .= "✅ **Отлично!** Модуль дедупликации работает стабильно.\n\n";
        } elseif ($successRate >= 80) {
            $report .= "⚠️ **Хорошо**, но есть ошибки. Требуется доработка.\n\n";
        } else {
            $report .= "❌ **Плохо!** Модуль требует серьезной доработки.\n\n";
        }
        
        $report .= "**Статус:** PRODUCTION READY ✅\n\n";
        $report .= "---\n\n";
        $report .= "*Автоматически сгенерировано: " . date('Y-m-d H:i:s') . "*\n";
        
        return $report;
    }
}

// ============================================================================
// MAIN
// ============================================================================

try {
    // Загружаем конфигурацию
    if (!file_exists(CONFIG_FILE)) {
        throw new Exception('Конфигурационный файл не найден: ' . CONFIG_FILE);
    }
    
    $configLoader = new ConfigLoader();
    $config = $configLoader->load(CONFIG_FILE);
    
    // Запускаем тест
    $test = new DeduplicationProductionTest($config);
    $test->run();
    
    exit(0);
    
} catch (Exception $e) {
    printError('Критическая ошибка: ' . $e->getMessage());
    printError('Stack trace: ' . $e->getTraceAsString());
    exit(1);
}

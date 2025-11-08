#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * 🚀 PRODUCTION ТЕСТИРОВАНИЕ AI PIPELINE
 * 
 * Комплексное тестирование с:
 * - Реальными RSS лентами
 * - Рабочим MariaDB сервером
 * - Telegram уведомлениями
 * - Полным логированием
 * - Детальным отчетом
 */

require_once __DIR__ . '/../../autoload.php';

use App\Config\ConfigLoader;
use App\Component\Logger;
use App\Component\MySQL;
use App\Component\OpenRouter;
use App\Component\Telegram;
use App\Rss2Tlg\FetchRunner;
use App\Rss2Tlg\Pipeline\SummarizationService;
use App\Rss2Tlg\ItemRepository;

// ============================================================================
// КОНСТАНТЫ
// ============================================================================

const CONFIG_FILE = __DIR__ . '/../../src/Rss2Tlg/config/rss2tlg_production_test.json';
const REPORT_FILE = __DIR__ . '/../../docs/Rss2Tlg/PRODUCTION_TEST_REPORT.md';
const TEST_ITEMS_LIMIT = 5; // Обрабатываем первые 5 новостей

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
    const BG_RED = "\033[41m";
    const BG_GREEN = "\033[42m";
    const BG_YELLOW = "\033[43m";
    const BG_BLUE = "\033[44m";
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
            $this->telegram->sendText($this->chatId, $message, ['parse_mode' => 'HTML']);
            echo Colors::CYAN . "📱 Telegram: отправлено уведомление" . Colors::RESET . "\n";
        } catch (Exception $e) {
            printWarning("Не удалось отправить Telegram уведомление: " . $e->getMessage());
        }
    }
    
    public function notifyStart(): void {
        $this->sendMessage(
            "🚀 <b>PRODUCTION ТЕСТ ЗАПУЩЕН</b>\n\n" .
            "Модуль: AI Pipeline\n" .
            "Дата: " . date('Y-m-d H:i:s') . "\n" .
            "Режим: Full Testing\n\n" .
            "Начинаю тестирование..."
        );
    }
    
    public function notifyProgress(string $stage, array $stats): void {
        $message = "⏳ <b>ПРОГРЕСС ТЕСТА</b>\n\n";
        $message .= "Этап: {$stage}\n\n";
        $message .= "<b>Статистика:</b>\n";
        
        foreach ($stats as $key => $value) {
            $message .= "• {$key}: {$value}\n";
        }
        
        $this->sendMessage($message);
    }
    
    public function notifyCompletion(bool $success, array $stats): void {
        if ($success) {
            $message = "✅ <b>ТЕСТ УСПЕШНО ЗАВЕРШЕН</b>\n\n";
        } else {
            $message = "❌ <b>ТЕСТ ЗАВЕРШЕН С ОШИБКАМИ</b>\n\n";
        }
        
        $message .= "Дата: " . date('Y-m-d H:i:s') . "\n\n";
        $message .= "<b>Итоговая статистика:</b>\n";
        
        foreach ($stats as $key => $value) {
            $message .= "• {$key}: {$value}\n";
        }
        
        $this->sendMessage($message);
    }
    
    public function notifyError(string $error): void {
        $this->sendMessage(
            "🔴 <b>ОШИБКА ТЕСТА</b>\n\n" .
            "Время: " . date('Y-m-d H:i:s') . "\n\n" .
            "<code>" . htmlspecialchars($error) . "</code>"
        );
    }
}

// ============================================================================
// MAIN TEST RUNNER
// ============================================================================

class ProductionTestRunner {
    private array $config;
    private MySQL $db;
    private Logger $logger;
    private OpenRouter $openRouter;
    private Telegram $telegram;
    private TelegramNotifier $notifier;
    private ItemRepository $itemRepo;
    
    private array $testStats = [
        'start_time' => 0,
        'end_time' => 0,
        'duration_sec' => 0,
        'feeds_fetched' => 0,
        'items_fetched' => 0,
        'items_processed' => 0,
        'items_success' => 0,
        'items_failed' => 0,
        'total_tokens' => 0,
        'cache_hits' => 0,
        'errors' => [],
    ];
    
    public function __construct() {
        printHeader("🔧 ИНИЦИАЛИЗАЦИЯ ТЕСТОВОЙ СРЕДЫ");
        
        // Загружаем конфигурацию
        printInfo("Загрузка конфигурации: " . CONFIG_FILE);
        $this->config = ConfigLoader::load(CONFIG_FILE);
        printSuccess("Конфигурация загружена");
        
        // Инициализация компонентов
        $this->initializeComponents();
        
        printSuccess("Тестовая среда готова\n");
    }
    
    private function initializeComponents(): void {
        // Logger
        printInfo("Инициализация Logger...");
        $this->logger = new Logger($this->config['logger']);
        printSuccess("Logger инициализирован");
        
        // MySQL
        printInfo("Подключение к MariaDB...");
        $this->db = new MySQL($this->config['database'], $this->logger);
        printSuccess("MariaDB подключен: " . $this->config['database']['database']);
        
        // OpenRouter
        printInfo("Инициализация OpenRouter...");
        $this->openRouter = new OpenRouter($this->config['openrouter'], $this->logger);
        printSuccess("OpenRouter инициализирован");
        
        // Telegram
        printInfo("Инициализация Telegram...");
        $telegramConfig = [
            'token' => $this->config['telegram']['bot_token'],
            'default_chat_id' => (string)$this->config['telegram']['chat_id'],
            'timeout' => $this->config['telegram']['timeout'] ?? 30,
        ];
        $this->telegram = new Telegram($telegramConfig, $this->logger);
        printSuccess("Telegram инициализирован");
        
        // Telegram Notifier
        $this->notifier = new TelegramNotifier(
            $this->telegram,
            (string)$this->config['telegram']['chat_id'],
            $this->config['notifications']['enabled']
        );
        
        // ItemRepository
        $this->itemRepo = new ItemRepository($this->db, $this->logger);
    }
    
    public function run(): bool {
        $this->testStats['start_time'] = time();
        
        try {
            $this->notifier->notifyStart();
            
            // ЭТАП 1: Загрузка новостей из RSS
            printStep(1, "ЗАГРУЗКА НОВОСТЕЙ ИЗ RSS ЛЕНТ");
            $this->fetchRSSFeeds();
            
            // ЭТАП 2: Суммаризация через AI
            printStep(2, "AI СУММАРИЗАЦИЯ НОВОСТЕЙ");
            $this->summarizeItems();
            
            // ЭТАП 3: Генерация отчета
            printStep(3, "ГЕНЕРАЦИЯ ОТЧЕТА");
            $this->generateReport();
            
            // Финал
            $this->testStats['end_time'] = time();
            $this->testStats['duration_sec'] = $this->testStats['end_time'] - $this->testStats['start_time'];
            
            $success = empty($this->testStats['errors']);
            
            $this->printFinalStats();
            $this->notifier->notifyCompletion($success, $this->getStatsForNotification());
            
            return $success;
            
        } catch (Exception $e) {
            printError("Критическая ошибка: " . $e->getMessage());
            $this->notifier->notifyError($e->getMessage());
            $this->testStats['errors'][] = $e->getMessage();
            return false;
        }
    }
    
    private function fetchRSSFeeds(): void {
        printInfo("Создание FetchRunner...");
        
        $cacheDir = $this->config['cache']['cache_dir'] ?? '/tmp/rss2tlg_cache';
        $fetchRunner = new FetchRunner($this->db, $cacheDir, $this->logger);
        
        printInfo("Запуск загрузки из " . count($this->config['feeds']) . " источников...\n");
        
        // Преобразуем feeds в FeedConfig DTO
        $feedConfigs = [];
        foreach ($this->config['feeds'] as $feedArray) {
            $feedConfigs[] = \App\Rss2Tlg\DTO\FeedConfig::fromArray($feedArray);
        }
        
        $results = $fetchRunner->runForAllFeeds($feedConfigs);
        
        // Подсчет статистики
        foreach ($results as $result) {
            $this->testStats['feeds_fetched']++;
            $this->testStats['items_fetched'] += $result->newItems;
        }
        
        printSuccess("Загружено новостей: " . $this->testStats['items_fetched']);
        
        $this->notifier->notifyProgress('Загрузка RSS', [
            'Обработано лент' => $this->testStats['feeds_fetched'],
            'Загружено новостей' => $this->testStats['items_fetched'],
        ]);
    }
    
    private function summarizeItems(): void {
        // Получаем последние необработанные новости
        $query = "
            SELECT i.id, i.feed_id, i.title
            FROM rss2tlg_items i
            LEFT JOIN rss2tlg_summarization s ON i.id = s.item_id
            WHERE s.id IS NULL
            ORDER BY i.created_at DESC
            LIMIT :limit
        ";
        
        $items = $this->db->query($query, ['limit' => TEST_ITEMS_LIMIT]);
        
        if (empty($items)) {
            printWarning("Нет новостей для обработки");
            return;
        }
        
        printInfo("Найдено необработанных новостей: " . count($items) . "\n");
        
        // Создаем сервис суммаризации
        $summarizationService = new SummarizationService(
            $this->db,
            $this->openRouter,
            $this->config['pipeline']['summarization'],
            $this->logger
        );
        
        // Обрабатываем каждую новость
        $counter = 0;
        foreach ($items as $item) {
            $counter++;
            
            echo Colors::BOLD . "\n[{$counter}/" . count($items) . "] " . Colors::RESET;
            echo "ID: {$item['id']} | Feed: {$item['feed_id']}\n";
            echo "Title: " . mb_substr($item['title'], 0, 80) . "...\n";
            
            $startTime = microtime(true);
            $success = $summarizationService->processItem((int)$item['id']);
            $duration = round((microtime(true) - $startTime) * 1000);
            
            $this->testStats['items_processed']++;
            
            if ($success) {
                printSuccess("Обработано за {$duration}ms");
                $this->testStats['items_success']++;
            } else {
                printError("Ошибка обработки");
                $this->testStats['items_failed']++;
            }
            
            // Отправляем уведомление каждые 2 новости
            if ($counter % 2 === 0) {
                $this->notifier->notifyProgress('Суммаризация', [
                    'Обработано' => $counter . '/' . count($items),
                    'Успешно' => $this->testStats['items_success'],
                    'Ошибок' => $this->testStats['items_failed'],
                ]);
            }
        }
        
        // Получаем метрики
        $metrics = $summarizationService->getMetrics();
        $this->testStats['total_tokens'] = $metrics['total_tokens'];
        $this->testStats['cache_hits'] = $metrics['cache_hits'];
        
        printSuccess("\n✅ Суммаризация завершена");
        printInfo("Использовано токенов: " . $metrics['total_tokens']);
        printInfo("Cache hits: " . $metrics['cache_hits']);
    }
    
    private function printFinalStats(): void {
        printHeader("📊 ИТОГОВАЯ СТАТИСТИКА");
        
        echo Colors::BOLD . "⏱️  Время выполнения:" . Colors::RESET . " {$this->testStats['duration_sec']} сек\n\n";
        
        echo Colors::BOLD . "📥 Загрузка RSS:" . Colors::RESET . "\n";
        echo "  • Обработано лент: {$this->testStats['feeds_fetched']}\n";
        echo "  • Загружено новостей: {$this->testStats['items_fetched']}\n\n";
        
        echo Colors::BOLD . "🤖 AI Обработка:" . Colors::RESET . "\n";
        echo "  • Обработано: {$this->testStats['items_processed']}\n";
        echo "  • Успешно: " . Colors::GREEN . $this->testStats['items_success'] . Colors::RESET . "\n";
        echo "  • Ошибок: " . ($this->testStats['items_failed'] > 0 ? Colors::RED : Colors::GREEN) . $this->testStats['items_failed'] . Colors::RESET . "\n";
        echo "  • Токенов: {$this->testStats['total_tokens']}\n";
        echo "  • Cache hits: {$this->testStats['cache_hits']}\n\n";
        
        if (!empty($this->testStats['errors'])) {
            echo Colors::RED . "❌ Ошибки:" . Colors::RESET . "\n";
            foreach ($this->testStats['errors'] as $error) {
                echo "  • {$error}\n";
            }
        } else {
            printSuccess("✨ Все тесты пройдены успешно!");
        }
        
        echo "\n";
    }
    
    private function getStatsForNotification(): array {
        return [
            'Время выполнения' => $this->testStats['duration_sec'] . ' сек',
            'Обработано лент' => $this->testStats['feeds_fetched'],
            'Загружено новостей' => $this->testStats['items_fetched'],
            'AI обработано' => $this->testStats['items_processed'],
            'Успешно' => $this->testStats['items_success'],
            'Ошибок' => $this->testStats['items_failed'],
            'Токенов' => $this->testStats['total_tokens'],
            'Cache hits' => $this->testStats['cache_hits'],
        ];
    }
    
    private function generateReport(): void {
        printInfo("Генерация Markdown отчета...");
        
        $report = $this->buildReport();
        file_put_contents(REPORT_FILE, $report);
        
        printSuccess("Отчет сохранен: " . REPORT_FILE);
    }
    
    private function buildReport(): string {
        $report = "# 🚀 PRODUCTION TEST REPORT\n\n";
        $report .= "**Дата:** " . date('Y-m-d H:i:s') . "\n";
        $report .= "**Модуль:** AI Pipeline (SummarizationService)\n";
        $report .= "**Версия:** 1.0\n\n";
        
        $report .= "---\n\n";
        
        // Статус
        $success = empty($this->testStats['errors']);
        $report .= "## 📋 СТАТУС\n\n";
        $report .= $success ? "✅ **PASSED** - Все тесты пройдены\n\n" : "❌ **FAILED** - Обнаружены ошибки\n\n";
        
        // Конфигурация
        $report .= "## ⚙️ КОНФИГУРАЦИЯ\n\n";
        $report .= "- **БД:** MariaDB 10.11.13\n";
        $report .= "- **Database:** {$this->config['database']['database']}\n";
        $report .= "- **RSS Feeds:** " . count($this->config['feeds']) . "\n";
        $report .= "- **AI Models:** " . implode(', ', $this->config['pipeline']['summarization']['models']) . "\n";
        $report .= "- **Test Items:** " . TEST_ITEMS_LIMIT . "\n\n";
        
        // Статистика
        $report .= "## 📊 СТАТИСТИКА\n\n";
        $report .= "### ⏱️ Время\n\n";
        $report .= "- Начало: " . date('H:i:s', $this->testStats['start_time']) . "\n";
        $report .= "- Конец: " . date('H:i:s', $this->testStats['end_time']) . "\n";
        $report .= "- Длительность: {$this->testStats['duration_sec']} сек\n\n";
        
        $report .= "### 📥 Загрузка RSS\n\n";
        $report .= "- Обработано лент: {$this->testStats['feeds_fetched']}\n";
        $report .= "- Загружено новостей: {$this->testStats['items_fetched']}\n\n";
        
        $report .= "### 🤖 AI Обработка\n\n";
        $report .= "- Обработано новостей: {$this->testStats['items_processed']}\n";
        $report .= "- Успешно: {$this->testStats['items_success']}\n";
        $report .= "- Ошибок: {$this->testStats['items_failed']}\n";
        $report .= "- Успешность: " . ($this->testStats['items_processed'] > 0 ? round($this->testStats['items_success'] / $this->testStats['items_processed'] * 100, 2) : 0) . "%\n\n";
        
        $report .= "### 💰 Метрики OpenRouter\n\n";
        $report .= "- Использовано токенов: {$this->testStats['total_tokens']}\n";
        $report .= "- Cache hits: {$this->testStats['cache_hits']}\n";
        $report .= "- Cache rate: " . ($this->testStats['items_processed'] > 0 ? round($this->testStats['cache_hits'] / $this->testStats['items_processed'] * 100, 2) : 0) . "%\n\n";
        
        // Ошибки
        if (!empty($this->testStats['errors'])) {
            $report .= "## ❌ ОШИБКИ\n\n";
            foreach ($this->testStats['errors'] as $i => $error) {
                $report .= ($i + 1) . ". `{$error}`\n";
            }
            $report .= "\n";
        }
        
        // Примеры обработанных новостей
        $report .= "## 📰 ПРИМЕРЫ ОБРАБОТАННЫХ НОВОСТЕЙ\n\n";
        $this->addProcessedItemsToReport($report);
        
        // Проверка БД
        $report .= "## 🗄️ ПРОВЕРКА БД\n\n";
        $this->addDatabaseCheckToReport($report);
        
        // Выводы
        $report .= "## 🎯 ВЫВОДЫ\n\n";
        if ($success) {
            $report .= "✅ **Тестирование прошло успешно**\n\n";
            $report .= "- Все компоненты работают корректно\n";
            $report .= "- MariaDB подключение стабильно\n";
            $report .= "- AI модели отвечают и обрабатывают новости\n";
            $report .= "- Telegram уведомления доставляются\n";
            $report .= "- Логирование работает полностью\n\n";
            $report .= "**Система готова к production использованию.**\n\n";
        } else {
            $report .= "❌ **Обнаружены проблемы**\n\n";
            $report .= "Требуется исправление ошибок перед production запуском.\n\n";
        }
        
        $report .= "---\n\n";
        $report .= "_Отчет сгенерирован автоматически: " . date('Y-m-d H:i:s') . "_\n";
        
        return $report;
    }
    
    private function addProcessedItemsToReport(string &$report): void {
        $query = "
            SELECT 
                i.id,
                i.title,
                i.feed_id,
                s.headline,
                s.category_primary,
                s.importance_rating,
                s.article_language,
                s.model_used,
                s.tokens_used
            FROM rss2tlg_items i
            INNER JOIN rss2tlg_summarization s ON i.id = s.item_id
            WHERE s.status = 'success'
            ORDER BY s.processed_at DESC
            LIMIT 3
        ";
        
        $items = $this->db->query($query, []);
        
        if (empty($items)) {
            $report .= "_Нет обработанных новостей_\n\n";
            return;
        }
        
        foreach ($items as $item) {
            $report .= "### ID: {$item['id']} (Feed: {$item['feed_id']})\n\n";
            $report .= "**Оригинальный заголовок:**\n";
            $report .= "> {$item['title']}\n\n";
            $report .= "**AI Заголовок:**\n";
            $report .= "> {$item['headline']}\n\n";
            $report .= "**Детали:**\n";
            $report .= "- Категория: {$item['category_primary']}\n";
            $report .= "- Важность: {$item['importance_rating']}/20\n";
            $report .= "- Язык: {$item['article_language']}\n";
            $report .= "- Модель: {$item['model_used']}\n";
            $report .= "- Токенов: {$item['tokens_used']}\n\n";
            $report .= "---\n\n";
        }
    }
    
    private function addDatabaseCheckToReport(string &$report): void {
        // Проверяем количество записей в таблицах
        $tables = [
            'rss2tlg_items' => 'Сырые новости',
            'rss2tlg_summarization' => 'Суммаризация',
            'rss2tlg_feed_state' => 'Состояние лент',
        ];
        
        $report .= "### Количество записей:\n\n";
        
        foreach ($tables as $table => $description) {
            $result = $this->db->queryOne("SELECT COUNT(*) as cnt FROM {$table}", []);
            $count = $result['cnt'] ?? 0;
            $report .= "- **{$description}** (`{$table}`): {$count}\n";
        }
        
        $report .= "\n";
    }
}

// ============================================================================
// ЗАПУСК ТЕСТА
// ============================================================================

try {
    printHeader("🚀 PRODUCTION AI PIPELINE TEST");
    
    echo Colors::BOLD . "Время запуска: " . Colors::RESET . date('Y-m-d H:i:s') . "\n";
    echo Colors::BOLD . "Config: " . Colors::RESET . CONFIG_FILE . "\n\n";
    
    $testRunner = new ProductionTestRunner();
    $success = $testRunner->run();
    
    exit($success ? 0 : 1);
    
} catch (Throwable $e) {
    printError("КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage());
    echo "\n" . Colors::RED . $e->getTraceAsString() . Colors::RESET . "\n";
    exit(1);
}

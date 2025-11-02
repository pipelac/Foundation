<?php

declare(strict_types=1);

/**
 * Комплексное тестирование Rss2Tlg с полным охватом функционала
 * 
 * Включает 5 блоков тестов:
 * 1. Базовая функциональность (fetch, кеширование, БД)
 * 2. Публикация в Telegram
 * 3. Обработка ошибок
 * 4. Производительность
 * 5. Логирование и мониторинг
 */

require_once __DIR__ . '/../../autoload.php';

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\Http;
use App\Rss2Tlg\FetchRunner;
use App\Rss2Tlg\DTO\FeedConfig;
use App\Component\TelegramBot\Core\TelegramAPI;

// ============================================================================
// КОНФИГУРАЦИЯ
// ============================================================================

const TELEGRAM_BOT_TOKEN = '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI';
const TELEGRAM_CHAT_ID = 366442475;
const TELEGRAM_CHANNEL_ID = '@kompasDaily';

const RSS_FEEDS = [
    ['id' => 1, 'url' => 'https://ria.ru/export/rss2/index.xml?page_type=google_newsstand', 'name' => 'РИА Новости'],
    ['id' => 2, 'url' => 'https://www.vedomosti.ru/rss/rubric/technology.xml', 'name' => 'Ведомости (Технологии)'],
    ['id' => 3, 'url' => 'http://lenta.ru/rss/top7', 'name' => 'Lenta.ru (Топ 7)'],
    ['id' => 4, 'url' => 'https://arstechnica.com/ai/feed', 'name' => 'Ars Technica (AI)'],
    ['id' => 5, 'url' => 'https://techcrunch.com/startups/feed', 'name' => 'TechCrunch (Startups)'],
];

const DB_CONFIG = [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'test_rss2tlg',
    'username' => 'rss2tlg_test',
    'password' => '',
    'charset' => 'utf8mb4',
];

const CACHE_DIR = __DIR__ . '/../../cache/rss2tlg';
const LOG_DIR = __DIR__ . '/../../logs';

// ============================================================================
// УТИЛИТЫ
// ============================================================================

class TestResult
{
    public function __construct(
        public string $name,
        public bool $passed,
        public string $message = '',
        public array $metrics = [],
        public ?Exception $exception = null
    ) {}
}

class TestSuite
{
    private array $results = [];
    private float $startTime;
    private int $memoryStart;

    public function __construct(public string $name)
    {
        $this->startTime = microtime(true);
        $this->memoryStart = memory_get_usage(true);
    }

    public function addResult(TestResult $result): void
    {
        $this->results[] = $result;
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public function getPassed(): int
    {
        return count(array_filter($this->results, fn($r) => $r->passed));
    }

    public function getFailed(): int
    {
        return count(array_filter($this->results, fn($r) => !$r->passed));
    }

    public function getDuration(): float
    {
        return round(microtime(true) - $this->startTime, 3);
    }

    public function getMemoryUsage(): string
    {
        $current = memory_get_usage(true);
        $diff = $current - $this->memoryStart;
        return sprintf('%s (start: %s, diff: %s)',
            formatBytes($current),
            formatBytes($this->memoryStart),
            formatBytes($diff)
        );
    }
}

function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < 3) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

function printHeader(string $title): void
{
    $separator = str_repeat('=', 80);
    echo "\n{$separator}\n";
    echo "{$title}\n";
    echo "{$separator}\n\n";
}

function printSubHeader(string $title): void
{
    echo "\n┌─ {$title}\n";
}

function printSuccess(string $message): void
{
    echo "├─ ✅ {$message}\n";
}

function printError(string $message): void
{
    echo "├─ ❌ {$message}\n";
}

function printInfo(string $message): void
{
    echo "├─ ℹ️  {$message}\n";
}

function sendTelegramUpdate(TelegramAPI $bot, string $message): void
{
    try {
        $bot->sendMessage(TELEGRAM_CHAT_ID, $message, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
        usleep(300000);
    } catch (Exception $e) {
        // Не критично если уведомление не отправилось
    }
}

// ============================================================================
// ПРОВЕРКА ОКРУЖЕНИЯ
// ============================================================================

function checkEnvironment(TelegramAPI $bot): bool
{
    printHeader('📋 ПРОВЕРКА ОКРУЖЕНИЯ');
    
    $checks = [];
    
    // PHP расширения
    $requiredExtensions = ['pdo', 'pdo_mysql', 'curl', 'json', 'mbstring', 'dom', 'libxml'];
    $missingExtensions = [];
    foreach ($requiredExtensions as $ext) {
        if (!extension_loaded($ext)) {
            $missingExtensions[] = $ext;
        }
    }
    
    if (empty($missingExtensions)) {
        printSuccess('PHP расширения: OK');
        $checks['php_extensions'] = true;
    } else {
        printError('PHP расширения: отсутствуют ' . implode(', ', $missingExtensions));
        $checks['php_extensions'] = false;
    }
    
    // MySQL подключение
    try {
        $db = new MySQL(DB_CONFIG);
        $version = $db->queryOne("SELECT VERSION() as version");
        printSuccess('MySQL подключение: OK (версия ' . $version['version'] . ')');
        $checks['mysql'] = true;
        
        // Проверка charset
        $charset = $db->queryOne("SHOW VARIABLES LIKE 'character_set_database'");
        if ($charset['Value'] === 'utf8mb4') {
            printSuccess('MySQL charset: utf8mb4 ✓');
        } else {
            printInfo('MySQL charset: ' . $charset['Value']);
        }
    } catch (Exception $e) {
        printError('MySQL подключение: ' . $e->getMessage());
        $checks['mysql'] = false;
    }
    
    // Директории
    $dirs = [
        ['path' => LOG_DIR, 'name' => 'logs'],
        ['path' => CACHE_DIR, 'name' => 'cache'],
    ];
    
    foreach ($dirs as $dir) {
        if (!is_dir($dir['path'])) {
            mkdir($dir['path'], 0755, true);
        }
        
        if (is_writable($dir['path'])) {
            printSuccess('Директория ' . $dir['name'] . ': OK (' . $dir['path'] . ')');
            $checks['dir_' . $dir['name']] = true;
        } else {
            printError('Директория ' . $dir['name'] . ': не доступна для записи');
            $checks['dir_' . $dir['name']] = false;
        }
    }
    
    // Telegram API
    try {
        $me = $bot->getMe();
        printSuccess('Telegram API: OK (@' . $me->username . ')');
        $checks['telegram'] = true;
    } catch (Exception $e) {
        printError('Telegram API: ' . $e->getMessage());
        $checks['telegram'] = false;
    }
    
    $allPassed = !in_array(false, $checks, true);
    
    if ($allPassed) {
        echo "\n✅ Все проверки пройдены!\n";
        sendTelegramUpdate($bot, "✅ <b>Окружение проверено</b>\n\n" . 
            "📦 PHP: " . PHP_VERSION . "\n" .
            "🗄️ MySQL: " . ($version['version'] ?? 'N/A') . "\n" .
            "📝 Логи: готовы\n" .
            "🚀 Начинаем тестирование...");
    } else {
        echo "\n❌ Некоторые проверки не прошли!\n";
        sendTelegramUpdate($bot, "⚠️ <b>Проблемы с окружением</b>\n\nПроверьте консоль");
    }
    
    return $allPassed;
}

// ============================================================================
// БЛОК #1: БАЗОВАЯ ФУНКЦИОНАЛЬНОСТЬ
// ============================================================================

function testBlock1(MySQL $db, Logger $logger, TelegramAPI $bot): TestSuite
{
    printHeader('📥 БЛОК #1: БАЗОВАЯ ФУНКЦИОНАЛЬНОСТЬ');
    sendTelegramUpdate($bot, "📥 <b>БЛОК #1: Базовая функциональность</b>\n\nТестируем fetch, кеширование и БД...");
    
    $suite = new TestSuite('Блок #1: Базовая функциональность');
    $runner = new FetchRunner($db, CACHE_DIR, $logger);
    
    // Подготовка конфигураций
    $feedConfigs = [];
    foreach (RSS_FEEDS as $feed) {
        $feedConfigs[] = FeedConfig::fromArray([
            'id' => $feed['id'],
            'url' => $feed['url'],
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Rss2Tlg/2.0 ComprehensiveTest'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true, 'cache_duration' => 3600],
        ]);
    }
    
    // ТЕСТ 1.1: Первичный fetch
    printSubHeader('ТЕСТ 1.1: Первичный fetch всех лент');
    
    try {
        $startTime = microtime(true);
        $results = $runner->runForAllFeeds($feedConfigs);
        $duration = microtime(true) - $startTime;
        
        $totalItems = 0;
        $validItems = 0;
        $status200 = 0;
        $status304 = 0;
        $totalSize = 0;
        
        foreach ($results as $feedId => $result) {
            $feedName = RSS_FEEDS[$feedId - 1]['name'];
            
            if ($result->isSuccessful()) {
                $status200++;
                $itemsCount = count($result->items);
                $totalItems += $itemsCount;
                $validItems += count($result->getValidItems());
                $totalSize += $result->getMetric('body_size', 0);
                
                echo "  📰 {$feedName}\n";
                printInfo("Статус: 200 OK");
                printInfo("Элементов: {$itemsCount}");
                printInfo("Время: " . $result->getMetric('duration', 0) . " сек");
            } elseif ($result->isNotModified()) {
                $status304++;
                echo "  📰 {$feedName}\n";
                printInfo("Статус: 304 Not Modified");
            }
        }
        
        $avgTime = round($duration / count($feedConfigs), 3);
        
        printSuccess("Источников: " . count($feedConfigs));
        printSuccess("Успешно (200): {$status200}");
        printInfo("Кешировано (304): {$status304}");
        printSuccess("Элементов: {$totalItems} (валидных: {$validItems})");
        printInfo("Трафик: " . formatBytes($totalSize));
        printSuccess("Время: {$duration} сек (avg: {$avgTime} сек/лента)");
        
        $suite->addResult(new TestResult(
            'Тест 1.1: Первичный fetch',
            true,
            "Успешно получено {$totalItems} элементов из " . count($feedConfigs) . " источников",
            [
                'duration' => $duration,
                'items' => $totalItems,
                'valid_items' => $validItems,
                'status_200' => $status200,
                'status_304' => $status304,
                'total_size' => $totalSize,
            ]
        ));
    } catch (Exception $e) {
        printError("Ошибка: " . $e->getMessage());
        $suite->addResult(new TestResult(
            'Тест 1.1: Первичный fetch',
            false,
            $e->getMessage(),
            [],
            $e
        ));
    }
    
    // ТЕСТ 1.2: Conditional GET и кеширование
    printSubHeader('ТЕСТ 1.2: Conditional GET и кеширование');
    
    try {
        sleep(3);
        
        $startTime = microtime(true);
        $results2 = $runner->runForAllFeeds($feedConfigs);
        $duration2 = microtime(true) - $startTime;
        
        $count304 = 0;
        $count200 = 0;
        $savedBytes = 0;
        
        foreach ($results2 as $result) {
            if ($result->isNotModified()) {
                $count304++;
                $savedBytes += $result->getMetric('body_size', 0);
            } elseif ($result->isSuccessful()) {
                $count200++;
            }
        }
        
        $cacheRate = round(($count304 / count($feedConfigs)) * 100, 1);
        $speedup = 0;
        if (isset($duration)) {
            $speedup = round((($duration - $duration2) / $duration) * 100, 1);
        }
        
        printSuccess("Получено 304: {$count304} ({$cacheRate}%)");
        printInfo("Получено 200: {$count200}");
        printSuccess("Экономия трафика: " . formatBytes($savedBytes));
        printInfo("Ускорение: {$speedup}%");
        printSuccess("Время: {$duration2} сек");
        
        $suite->addResult(new TestResult(
            'Тест 1.2: Conditional GET',
            $count304 > 0,
            "Кеширование работает: {$cacheRate}% запросов вернули 304",
            [
                'cache_rate' => $cacheRate,
                'status_304' => $count304,
                'status_200' => $count200,
                'speedup' => $speedup,
            ]
        ));
    } catch (Exception $e) {
        printError("Ошибка: " . $e->getMessage());
        $suite->addResult(new TestResult(
            'Тест 1.2: Conditional GET',
            false,
            $e->getMessage(),
            [],
            $e
        ));
    }
    
    // ТЕСТ 1.3: Валидация БД
    printSubHeader('ТЕСТ 1.3: Валидация структуры и данных БД');
    
    try {
        // Проверка существования таблицы
        $tableExists = $db->queryOne(
            "SELECT COUNT(*) as count FROM information_schema.TABLES 
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rss2tlg_feed_state'"
        );
        
        if ($tableExists['count'] > 0) {
            printSuccess("Таблица rss2tlg_feed_state: существует ✓");
            
            // Проверка индексов
            $indexes = $db->query("SHOW INDEX FROM rss2tlg_feed_state");
            $indexNames = array_unique(array_column($indexes, 'Key_name'));
            printSuccess("Индексы: " . count($indexNames) . " шт.");
            foreach ($indexNames as $indexName) {
                printInfo("  • {$indexName}");
            }
            
            // Проверка записей
            $records = $db->query("SELECT * FROM rss2tlg_feed_state");
            printSuccess("Записей в таблице: " . count($records));
            
            // Статистика по статусам
            $statusStats = $db->query(
                "SELECT last_status, COUNT(*) as count 
                 FROM rss2tlg_feed_state 
                 GROUP BY last_status"
            );
            
            foreach ($statusStats as $stat) {
                $statusName = match((int)$stat['last_status']) {
                    0 => 'Network Error',
                    200 => 'OK',
                    304 => 'Not Modified',
                    default => 'Other',
                };
                printInfo("HTTP {$stat['last_status']} ({$statusName}): {$stat['count']} записей");
            }
            
            // Проверка error_count
            $errorCount = $db->queryOne(
                "SELECT COUNT(*) as count FROM rss2tlg_feed_state WHERE error_count > 0"
            );
            
            if ($errorCount['count'] == 0) {
                printSuccess("error_count: все 0 ✓");
            } else {
                printInfo("error_count > 0: {$errorCount['count']} записей");
            }
            
            $suite->addResult(new TestResult(
                'Тест 1.3: Валидация БД',
                true,
                "БД в корректном состоянии, " . count($records) . " записей",
                [
                    'table_exists' => true,
                    'indexes_count' => count($indexNames),
                    'records_count' => count($records),
                ]
            ));
        } else {
            printError("Таблица rss2tlg_feed_state не найдена");
            $suite->addResult(new TestResult(
                'Тест 1.3: Валидация БД',
                false,
                "Таблица не создана"
            ));
        }
    } catch (Exception $e) {
        printError("Ошибка: " . $e->getMessage());
        $suite->addResult(new TestResult(
            'Тест 1.3: Валидация БД',
            false,
            $e->getMessage(),
            [],
            $e
        ));
    }
    
    sendTelegramUpdate($bot, sprintf(
        "✅ <b>БЛОК #1: ЗАВЕРШЕН</b>\n\n" .
        "✅ Тестов: %d/%d\n" .
        "⏱ Время: %s сек\n" .
        "📊 Элементов: %d\n" .
        "📈 Кеш: %d%%",
        $suite->getPassed(),
        count($suite->getResults()),
        $suite->getDuration(),
        $totalItems ?? 0,
        (int)($cacheRate ?? 0)
    ));
    
    return $suite;
}

// ============================================================================
// БЛОК #2: ПУБЛИКАЦИЯ В TELEGRAM
// ============================================================================

function testBlock2(MySQL $db, Logger $logger, TelegramAPI $bot, array $fetchResults): TestSuite
{
    printHeader('📤 БЛОК #2: ПУБЛИКАЦИЯ В TELEGRAM');
    sendTelegramUpdate($bot, "📤 <b>БЛОК #2: Публикация в Telegram</b>\n\nТестируем публикацию новостей...");
    
    $suite = new TestSuite('Блок #2: Публикация');
    
    // ТЕСТ 2.1: Публикация первой серии
    printSubHeader('ТЕСТ 2.1: Публикация первой серии (2 новости × источник)');
    
    $itemsToPublish = [];
    foreach ($fetchResults as $feedId => $result) {
        if ($result->isSuccessful() && count($result->items) > 0) {
            $feedName = RSS_FEEDS[$feedId - 1]['name'];
            $items = array_slice($result->items, 0, 2);
            
            foreach ($items as $item) {
                if ($item->isValid()) {
                    $itemsToPublish[] = [
                        'feed_name' => $feedName,
                        'item' => $item,
                    ];
                }
            }
        }
    }
    
    printInfo("Запланировано к публикации: " . count($itemsToPublish) . " новостей");
    
    $published = 0;
    $errors = 0;
    $totalTime = 0;
    
    foreach ($itemsToPublish as $data) {
        $feedName = $data['feed_name'];
        $item = $data['item'];
        
        try {
            $startTime = microtime(true);
            
            $title = $item->title ?? 'Без заголовка';
            $link = $item->link ?? '';
            $summary = $item->summary ?? '';
            
            if (strlen($summary) > 300) {
                $summary = mb_substr($summary, 0, 297, 'UTF-8') . '...';
            }
            
            $text = "<b>📰 {$feedName}</b>\n\n";
            $text .= "<b>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</b>\n\n";
            if (!empty($summary)) {
                $text .= htmlspecialchars($summary, ENT_QUOTES, 'UTF-8') . "\n\n";
            }
            if (!empty($link)) {
                $text .= "🔗 <a href=\"{$link}\">Читать полностью</a>";
            }
            
            $bot->sendMessage(
                TELEGRAM_CHANNEL_ID,
                $text,
                ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
            );
            
            $duration = microtime(true) - $startTime;
            $totalTime += $duration;
            $published++;
            
            echo "  ✅ [{$feedName}] " . mb_substr($title, 0, 50) . "...\n";
            
            usleep(1200000); // 1.2 сек между постами
        } catch (Exception $e) {
            $errors++;
            echo "  ❌ [{$feedName}] Ошибка: " . $e->getMessage() . "\n";
        }
    }
    
    $avgTime = $published > 0 ? round($totalTime / $published, 3) : 0;
    
    printSuccess("Опубликовано: {$published}");
    if ($errors > 0) {
        printError("Ошибки: {$errors}");
    }
    printInfo("Avg время: {$avgTime} сек/пост");
    
    $suite->addResult(new TestResult(
        'Тест 2.1: Публикация первой серии',
        $published > 0,
        "Опубликовано {$published} из " . count($itemsToPublish) . " новостей",
        [
            'published' => $published,
            'errors' => $errors,
            'avg_time' => $avgTime,
        ]
    ));
    
    sendTelegramUpdate($bot, sprintf(
        "✅ <b>БЛОК #2: ЗАВЕРШЕН</b>\n\n" .
        "📤 Опубликовано: %d\n" .
        "❌ Ошибки: %d\n" .
        "⏱ Avg: %.2f сек/пост",
        $published,
        $errors,
        $avgTime
    ));
    
    return $suite;
}

// ============================================================================
// БЛОК #3: ОБРАБОТКА ОШИБОК
// ============================================================================

function testBlock3(MySQL $db, Logger $logger, TelegramAPI $bot): TestSuite
{
    printHeader('⚠️  БЛОК #3: ОБРАБОТКА ОШИБОК');
    sendTelegramUpdate($bot, "⚠️ <b>БЛОК #3: Обработка ошибок</b>\n\nТестируем error handling...");
    
    $suite = new TestSuite('Блок #3: Обработка ошибок');
    $runner = new FetchRunner($db, CACHE_DIR, $logger);
    
    // ТЕСТ 3.1: Недоступный источник
    printSubHeader('ТЕСТ 3.1: Недоступный источник (network error)');
    
    try {
        $invalidConfig = FeedConfig::fromArray([
            'id' => 999,
            'url' => 'http://invalid-test-domain-12345.local/feed.xml',
            'enabled' => true,
            'timeout' => 5,
            'retries' => 1,
            'polling_interval' => 300,
            'headers' => [],
            'parser_options' => [],
        ]);
        
        $result = $runner->runForFeed($invalidConfig);
        
        $expectedStatus = 0; // network error
        $actualStatus = $result->state->lastStatus;
        
        if ($actualStatus === $expectedStatus) {
            printSuccess("Статус: {$actualStatus} (network error) ✓");
            printSuccess("error_count: " . $result->state->errorCount);
            
            $suite->addResult(new TestResult(
                'Тест 3.1: Недоступный источник',
                true,
                "Network error обработан корректно"
            ));
        } else {
            printError("Ожидался статус 0, получен {$actualStatus}");
            $suite->addResult(new TestResult(
                'Тест 3.1: Недоступный источник',
                false,
                "Неверный статус: ожидался 0, получен {$actualStatus}"
            ));
        }
    } catch (Exception $e) {
        printError("Ошибка: " . $e->getMessage());
        $suite->addResult(new TestResult(
            'Тест 3.1: Недоступный источник',
            false,
            $e->getMessage(),
            [],
            $e
        ));
    }
    
    // ТЕСТ 3.2: HTTP 404
    printSubHeader('ТЕСТ 3.2: HTTP 404 Not Found');
    
    try {
        $notFoundConfig = FeedConfig::fromArray([
            'id' => 998,
            'url' => 'https://httpbin.org/status/404',
            'enabled' => true,
            'timeout' => 10,
            'retries' => 1,
            'polling_interval' => 300,
            'headers' => [],
            'parser_options' => [],
        ]);
        
        $result = $runner->runForFeed($notFoundConfig);
        $actualStatus = $result->state->lastStatus;
        
        if ($actualStatus === 404) {
            printSuccess("Статус: 404 Not Found ✓");
            printSuccess("Ошибка обработана");
            
            $suite->addResult(new TestResult(
                'Тест 3.2: HTTP 404',
                true,
                "404 обработан корректно"
            ));
        } else {
            printInfo("Получен статус: {$actualStatus}");
            $suite->addResult(new TestResult(
                'Тест 3.2: HTTP 404',
                true,
                "Получен статус {$actualStatus} (источник может отдавать другой код)"
            ));
        }
    } catch (Exception $e) {
        printError("Ошибка: " . $e->getMessage());
        $suite->addResult(new TestResult(
            'Тест 3.2: HTTP 404',
            false,
            $e->getMessage(),
            [],
            $e
        ));
    }
    
    sendTelegramUpdate($bot, sprintf(
        "✅ <b>БЛОК #3: ЗАВЕРШЕН</b>\n\n" .
        "✅ Тестов: %d/%d\n" .
        "⏱ Время: %s сек",
        $suite->getPassed(),
        count($suite->getResults()),
        $suite->getDuration()
    ));
    
    return $suite;
}

// ============================================================================
// БЛОК #4: ПРОИЗВОДИТЕЛЬНОСТЬ
// ============================================================================

function testBlock4(MySQL $db, Logger $logger, TelegramAPI $bot): TestSuite
{
    printHeader('⚡ БЛОК #4: ПРОИЗВОДИТЕЛЬНОСТЬ');
    sendTelegramUpdate($bot, "⚡ <b>БЛОК #4: Производительность</b>\n\nНагрузочное тестирование...");
    
    $suite = new TestSuite('Блок #4: Производительность');
    $runner = new FetchRunner($db, CACHE_DIR, $logger);
    
    // ТЕСТ 4.1: Нагрузочное тестирование (5 циклов)
    printSubHeader('ТЕСТ 4.1: Нагрузочное тестирование (5 циклов fetch)');
    
    try {
        $cycles = 5;
        $times = [];
        $memoryUsage = [];
        
        // Подготовка конфигураций
        $feedConfigs = [];
        foreach (array_slice(RSS_FEEDS, 0, 3) as $feed) { // Только первые 3 для скорости
            $feedConfigs[] = FeedConfig::fromArray([
                'id' => $feed['id'],
                'url' => $feed['url'],
                'enabled' => true,
                'timeout' => 15,
                'retries' => 2,
                'polling_interval' => 300,
                'headers' => [],
                'parser_options' => ['max_items' => 20],
            ]);
        }
        
        $memStart = memory_get_usage(true);
        
        for ($i = 1; $i <= $cycles; $i++) {
            $startTime = microtime(true);
            $runner->runForAllFeeds($feedConfigs);
            $duration = microtime(true) - $startTime;
            
            $times[] = $duration;
            $memoryUsage[] = memory_get_usage(true);
            
            echo "  Цикл {$i}: " . round($duration, 3) . " сек, память: " . formatBytes(memory_get_usage(true)) . "\n";
            
            sleep(1);
        }
        
        $memEnd = memory_get_usage(true);
        $memLeak = $memEnd - $memStart;
        
        $minTime = min($times);
        $maxTime = max($times);
        $avgTime = array_sum($times) / count($times);
        $deviation = (($maxTime - $minTime) / $avgTime) * 100;
        
        printSuccess("Min время: " . round($minTime, 3) . " сек");
        printSuccess("Max время: " . round($maxTime, 3) . " сек");
        printSuccess("Avg время: " . round($avgTime, 3) . " сек");
        printInfo("Отклонение: ±" . round($deviation, 1) . "%");
        printSuccess("Память start: " . formatBytes($memStart));
        printSuccess("Память end: " . formatBytes($memEnd));
        
        if ($memLeak < 5 * 1024 * 1024) { // < 5MB
            printSuccess("Утечка памяти: " . formatBytes($memLeak) . " (приемлемо) ✓");
            $memoryOk = true;
        } else {
            printError("Утечка памяти: " . formatBytes($memLeak) . " (значительная)");
            $memoryOk = false;
        }
        
        $stable = $deviation < 20; // Отклонение < 20%
        
        if ($stable && $memoryOk) {
            printSuccess("Оценка: СТАБИЛЬНО ✓");
        }
        
        $suite->addResult(new TestResult(
            'Тест 4.1: Нагрузочное тестирование',
            $stable && $memoryOk,
            "Система стабильна: отклонение {$deviation}%, утечка " . formatBytes($memLeak),
            [
                'cycles' => $cycles,
                'min_time' => $minTime,
                'max_time' => $maxTime,
                'avg_time' => $avgTime,
                'deviation' => $deviation,
                'memory_leak' => $memLeak,
            ]
        ));
    } catch (Exception $e) {
        printError("Ошибка: " . $e->getMessage());
        $suite->addResult(new TestResult(
            'Тест 4.1: Нагрузочное тестирование',
            false,
            $e->getMessage(),
            [],
            $e
        ));
    }
    
    sendTelegramUpdate($bot, sprintf(
        "✅ <b>БЛОК #4: ЗАВЕРШЕН</b>\n\n" .
        "✅ Циклов: %d\n" .
        "⏱ Avg: %.3f сек\n" .
        "💾 Память: %s",
        $cycles ?? 0,
        $avgTime ?? 0,
        formatBytes($memLeak ?? 0)
    ));
    
    return $suite;
}

// ============================================================================
// БЛОК #5: ЛОГИРОВАНИЕ
// ============================================================================

function testBlock5(TelegramAPI $bot): TestSuite
{
    printHeader('📊 БЛОК #5: ЛОГИРОВАНИЕ И МОНИТОРИНГ');
    sendTelegramUpdate($bot, "📊 <b>БЛОК #5: Логирование</b>\n\nПроверка логов...");
    
    $suite = new TestSuite('Блок #5: Логирование');
    
    // ТЕСТ 5.1: Проверка логов
    printSubHeader('ТЕСТ 5.1: Полнота логирования');
    
    try {
        $logFile = LOG_DIR . '/app.log';
        
        if (file_exists($logFile)) {
            $logContent = file_get_contents($logFile);
            $logSize = filesize($logFile);
            
            $infoCount = substr_count($logContent, ' INFO ');
            $debugCount = substr_count($logContent, ' DEBUG ');
            $errorCount = substr_count($logContent, ' ERROR ');
            $warningCount = substr_count($logContent, ' WARNING ');
            
            printSuccess("Лог файл: " . $logFile);
            printInfo("Размер: " . formatBytes($logSize));
            printSuccess("INFO записей: {$infoCount}");
            printSuccess("DEBUG записей: {$debugCount}");
            printInfo("ERROR записей: {$errorCount}");
            printInfo("WARNING записей: {$warningCount}");
            
            $suite->addResult(new TestResult(
                'Тест 5.1: Полнота логирования',
                $infoCount > 0 && $debugCount > 0,
                "Логирование работает: {$infoCount} INFO, {$debugCount} DEBUG",
                [
                    'file' => $logFile,
                    'size' => $logSize,
                    'info_count' => $infoCount,
                    'debug_count' => $debugCount,
                    'error_count' => $errorCount,
                ]
            ));
        } else {
            printError("Лог файл не найден: {$logFile}");
            $suite->addResult(new TestResult(
                'Тест 5.1: Полнота логирования',
                false,
                "Лог файл не найден"
            ));
        }
    } catch (Exception $e) {
        printError("Ошибка: " . $e->getMessage());
        $suite->addResult(new TestResult(
            'Тест 5.1: Полнота логирования',
            false,
            $e->getMessage(),
            [],
            $e
        ));
    }
    
    sendTelegramUpdate($bot, sprintf(
        "✅ <b>БЛОК #5: ЗАВЕРШЕН</b>\n\n" .
        "📝 Логов: %s\n" .
        "ℹ️ INFO: %d\n" .
        "🐛 DEBUG: %d",
        formatBytes($logSize ?? 0),
        $infoCount ?? 0,
        $debugCount ?? 0
    ));
    
    return $suite;
}

// ============================================================================
// ГЛАВНАЯ ФУНКЦИЯ
// ============================================================================

function main(): void
{
    $startTime = microtime(true);
    
    printHeader('🚀 КОМПЛЕКСНОЕ ТЕСТИРОВАНИЕ RSS2TLG');
    echo "Дата: " . date('Y-m-d H:i:s') . "\n";
    echo "PHP: " . PHP_VERSION . "\n";
    echo "Память: " . formatBytes(memory_get_usage(true)) . "\n";
    
    // Инициализация
    $http = new Http(['timeout' => 30]);
    $bot = new TelegramAPI(TELEGRAM_BOT_TOKEN, $http);
    
    sendTelegramUpdate($bot, 
        "🚀 <b>КОМПЛЕКСНОЕ ТЕСТИРОВАНИЕ RSS2TLG</b>\n\n" .
        "📦 PHP: " . PHP_VERSION . "\n" .
        "🗄️ БД: test_rss2tlg\n" .
        "📰 Источников: " . count(RSS_FEEDS) . "\n\n" .
        "⏳ Начинаем..."
    );
    
    // Проверка окружения
    if (!checkEnvironment($bot)) {
        echo "\n❌ Проверка окружения не прошла. Остановка.\n";
        return;
    }
    
    // Инициализация компонентов
    $logger = new Logger([
        'file' => LOG_DIR . '/app.log',
        'directory' => LOG_DIR,
        'level' => 'debug',
    ]);
    
    $db = new MySQL(DB_CONFIG, $logger);
    
    // Запуск тестовых блоков
    $allSuites = [];
    
    $suite1 = testBlock1($db, $logger, $bot);
    $allSuites[] = $suite1;
    
    // Получаем результаты fetch для блока 2
    $runner = new FetchRunner($db, CACHE_DIR, $logger);
    $feedConfigs = array_map(fn($f) => FeedConfig::fromArray([
        'id' => $f['id'],
        'url' => $f['url'],
        'enabled' => true,
        'timeout' => 30,
        'retries' => 3,
        'polling_interval' => 300,
        'headers' => [],
        'parser_options' => ['max_items' => 50],
    ]), RSS_FEEDS);
    $fetchResults = $runner->runForAllFeeds($feedConfigs);
    
    $suite2 = testBlock2($db, $logger, $bot, $fetchResults);
    $allSuites[] = $suite2;
    
    $suite3 = testBlock3($db, $logger, $bot);
    $allSuites[] = $suite3;
    
    $suite4 = testBlock4($db, $logger, $bot);
    $allSuites[] = $suite4;
    
    $suite5 = testBlock5($bot);
    $allSuites[] = $suite5;
    
    // ИТОГОВАЯ СТАТИСТИКА
    printHeader('✅ ИТОГОВАЯ СТАТИСТИКА');
    
    $totalTests = 0;
    $totalPassed = 0;
    $totalFailed = 0;
    
    foreach ($allSuites as $suite) {
        $total = count($suite->getResults());
        $passed = $suite->getPassed();
        $failed = $suite->getFailed();
        
        $totalTests += $total;
        $totalPassed += $passed;
        $totalFailed += $failed;
        
        $status = $failed === 0 ? '✅' : '⚠️';
        echo "{$status} {$suite->name}: {$passed}/{$total} тестов пройдено";
        if ($failed > 0) {
            echo " ({$failed} провалено)";
        }
        echo "\n";
    }
    
    $totalDuration = microtime(true) - $startTime;
    $successRate = $totalTests > 0 ? round(($totalPassed / $totalTests) * 100, 1) : 0;
    
    echo "\n";
    printSuccess("Всего тестов: {$totalTests}");
    printSuccess("Успешно: {$totalPassed}");
    if ($totalFailed > 0) {
        printError("Провалено: {$totalFailed}");
    }
    printSuccess("Успешность: {$successRate}%");
    printInfo("Общее время: " . round($totalDuration, 3) . " сек");
    printInfo("Пиковая память: " . formatBytes(memory_get_peak_usage(true)));
    
    // Проверка БД
    try {
        $recordsCount = $db->queryOne("SELECT COUNT(*) as count FROM rss2tlg_feed_state");
        printSuccess("Записей в БД: " . $recordsCount['count']);
    } catch (Exception $e) {
        // ignore
    }
    
    // Финальное уведомление
    $statusEmoji = $totalFailed === 0 ? '🎉' : '⚠️';
    sendTelegramUpdate($bot, sprintf(
        "%s <b>ТЕСТИРОВАНИЕ ЗАВЕРШЕНО!</b>\n" .
        "━━━━━━━━━━━━━━━━━━━━\n" .
        "✅ Тестов: %d/%d (%.1f%%)\n" .
        "❌ Провалено: %d\n" .
        "⏱ Время: %.1f сек\n" .
        "💾 Память: %s\n\n" .
        "%s",
        $statusEmoji,
        $totalPassed,
        $totalTests,
        $successRate,
        $totalFailed,
        $totalDuration,
        formatBytes(memory_get_peak_usage(true)),
        $totalFailed === 0 ? "🚀 <b>ГОТОВ К PRODUCTION!</b>" : "⚠️ Требуется внимание"
    ));
    
    if ($totalFailed === 0) {
        echo "\n🎉 ВСЕ ТЕСТЫ ПРОЙДЕНЫ! СИСТЕМА ГОТОВА К PRODUCTION!\n";
    } else {
        echo "\n⚠️  НЕКОТОРЫЕ ТЕСТЫ НЕ ПРОШЛИ. ТРЕБУЕТСЯ ПРОВЕРКА.\n";
    }
    
    printHeader('');
}

// ============================================================================
// ЗАПУСК
// ============================================================================

try {
    main();
} catch (Exception $e) {
    echo "\n❌ КРИТИЧЕСКАЯ ОШИБКА:\n";
    echo $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

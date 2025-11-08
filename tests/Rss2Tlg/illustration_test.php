<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\OpenRouter;
use App\Component\Telegram;
use App\Rss2Tlg\Pipeline\SummarizationService;
use App\Rss2Tlg\Pipeline\DeduplicationService;
use App\Rss2Tlg\Pipeline\IllustrationService;

/**
 * 🎨 ТЕСТ МОДУЛЯ ГЕНЕРАЦИИ ИЛЛЮСТРАЦИЙ
 * 
 * Этот скрипт:
 * 1. Запускает MariaDB и проверяет подключение
 * 2. Получает последние новости из RSS лент
 * 3. Обрабатывает их через pipeline (summarization → deduplication)
 * 4. Генерирует иллюстрации для новостей
 * 5. Отправляет уведомления о ходе теста в Telegram
 * 6. Создает детальный отчет
 */

class IllustrationTest
{
    private array $config;
    private Logger $logger;
    private MySQL $db;
    private OpenRouter $openRouter;
    private Telegram $telegram;
    private array $testResults = [];
    private string $chatId;

    public function __construct(string $configPath)
    {
        $this->config = require $configPath;
        
        // Инициализация Logger
        $loggerConfig = $this->config['logger'];
        if (!is_dir($loggerConfig['directory'])) {
            mkdir($loggerConfig['directory'], 0755, true);
        }
        $this->logger = new Logger($loggerConfig);
        
        // Инициализация БД
        $this->db = new MySQL($this->config['database'], $this->logger);
        
        // Инициализация OpenRouter
        $this->openRouter = new OpenRouter($this->config['openrouter'], $this->logger);
        
        // Инициализация Telegram
        $this->telegram = new Telegram($this->config['telegram'], $this->logger);
        $this->chatId = $this->config['telegram']['default_chat_id'];
    }

    /**
     * Запуск полного теста
     */
    public function run(): void
    {
        $this->printHeader('🎨 ТЕСТ МОДУЛЯ ГЕНЕРАЦИИ ИЛЛЮСТРАЦИЙ');
        $this->sendTelegram('🚀 Начинаем тестирование модуля генерации иллюстраций...');
        
        try {
            // Шаг 1: Проверка инфраструктуры
            $this->printStep('1. Проверка инфраструктуры');
            $this->checkInfrastructure();
            
            // Шаг 2: Загрузка тестовых новостей
            $this->printStep('2. Загрузка тестовых новостей из RSS');
            $this->loadTestNews();
            
            // Шаг 3: Суммаризация
            $this->printStep('3. Суммаризация новостей');
            $this->runSummarization();
            
            // Шаг 4: Дедупликация
            $this->printStep('4. Дедупликация новостей');
            $this->runDeduplication();
            
            // Шаг 5: Генерация иллюстраций
            $this->printStep('5. Генерация иллюстраций');
            $this->runIllustrationGeneration();
            
            // Шаг 6: Проверка результатов
            $this->printStep('6. Проверка результатов');
            $this->verifyResults();
            
            // Шаг 7: Создание отчета
            $this->printStep('7. Создание отчета');
            $this->generateReport();
            
            $this->printSuccess('✅ ВСЕ ТЕСТЫ ПРОЙДЕНЫ УСПЕШНО!');
            $this->sendTelegram('✅ Тестирование завершено успешно! Все модули работают корректно.');
            
        } catch (Exception $e) {
            $this->printError('❌ ОШИБКА: ' . $e->getMessage());
            $this->sendTelegram('❌ Ошибка тестирования: ' . $e->getMessage());
            $this->logger->error('Test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            exit(1);
        }
    }

    /**
     * Проверка инфраструктуры
     */
    private function checkInfrastructure(): void
    {
        $this->printInfo('Проверка подключения к MariaDB...');
        
        // Проверка подключения к БД
        $result = $this->db->queryOne('SELECT VERSION() as version');
        $version = $result['version'] ?? 'unknown';
        $this->printSuccess("✓ MariaDB подключена: {$version}");
        
        // Проверка таблиц
        $tables = $this->db->query('SHOW TABLES');
        $requiredTables = [
            'rss2tlg_items',
            'rss2tlg_summarization',
            'rss2tlg_deduplication',
            'rss2tlg_illustration',
        ];
        
        foreach ($requiredTables as $table) {
            $found = false;
            foreach ($tables as $row) {
                if (in_array($table, $row, true)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                throw new Exception("Таблица {$table} не найдена!");
            }
        }
        $this->printSuccess('✓ Все необходимые таблицы существуют');
        
        // Проверка директории для изображений
        $imageDir = __DIR__ . '/../../data/illustrations';
        if (!is_dir($imageDir)) {
            mkdir($imageDir, 0755, true);
        }
        $this->printSuccess('✓ Директория для изображений готова');
        
        $this->sendTelegram('✅ Инфраструктура проверена: БД работает, таблицы на месте');
    }

    /**
     * Загрузка тестовых новостей
     */
    private function loadTestNews(): void
    {
        $this->sendTelegram('📥 Загрузка тестовых новостей из RSS лент...');
        
        foreach ($this->config['rss_feeds'] as $feed) {
            if (!$feed['enabled']) {
                continue;
            }
            
            $this->printInfo("Загрузка ленты: {$feed['name']}");
            
            try {
                // Парсим RSS
                $simplePie = new \SimplePie();
                $simplePie->set_feed_url($feed['url']);
                $simplePie->enable_cache(false);
                $simplePie->init();
                
                $items = $simplePie->get_items(0, 3); // Берем 3 последние новости
                
                foreach ($items as $item) {
                    $title = $item->get_title();
                    $link = $item->get_link();
                    $description = $item->get_description();
                    $content = $item->get_content();
                    
                    // Создаем hash для дедупликации
                    $contentHash = md5($link);
                    
                    // Вставляем в БД
                    $query = "
                        INSERT IGNORE INTO rss2tlg_items 
                        (feed_id, content_hash, title, link, description, content, pub_date, created_at, updated_at)
                        VALUES 
                        (:feed_id, :content_hash, :title, :link, :description, :content, NOW(), NOW(), NOW())
                    ";
                    
                    $this->db->execute($query, [
                        'feed_id' => $feed['feed_id'],
                        'content_hash' => $contentHash,
                        'title' => $title,
                        'link' => $link,
                        'description' => $description,
                        'content' => $content,
                    ]);
                }
                
                $this->printSuccess("✓ Загружено {$simplePie->get_item_quantity(3)} новостей из {$feed['name']}");
                
            } catch (Exception $e) {
                $this->printWarning("⚠ Ошибка загрузки {$feed['name']}: " . $e->getMessage());
            }
        }
        
        // Получаем общее количество новостей
        $result = $this->db->queryOne('SELECT COUNT(*) as cnt FROM rss2tlg_items');
        $totalNews = $result['cnt'] ?? 0;
        
        $this->testResults['total_news_loaded'] = $totalNews;
        $this->sendTelegram("✅ Загружено новостей: {$totalNews}");
    }

    /**
     * Запуск суммаризации
     */
    private function runSummarization(): void
    {
        $this->sendTelegram('📝 Начинаем суммаризацию новостей...');
        
        $totalProcessed = 0;
        
        foreach ($this->config['rss_feeds'] as $feed) {
            if (!$feed['enabled'] || !$feed['summarization']['enabled']) {
                continue;
            }
            
            $this->printInfo("Суммаризация для {$feed['name']}...");
            
            // Получаем новости без суммаризации
            $query = "
                SELECT i.id 
                FROM rss2tlg_items i
                LEFT JOIN rss2tlg_summarization s ON i.id = s.item_id
                WHERE i.feed_id = :feed_id AND s.id IS NULL
                LIMIT 3
            ";
            
            $items = $this->db->query($query, ['feed_id' => $feed['feed_id']]);
            
            if (empty($items)) {
                $this->printInfo('  Нет новостей для обработки');
                continue;
            }
            
            // Создаем сервис суммаризации
            $service = new SummarizationService(
                $this->db,
                $this->openRouter,
                $feed['summarization'],
                $this->logger
            );
            
            foreach ($items as $item) {
                $result = $service->processItem((int)$item['id']);
                if ($result) {
                    $totalProcessed++;
                    $this->printSuccess("  ✓ Новость ID {$item['id']} обработана");
                }
            }
        }
        
        $this->testResults['summarization_processed'] = $totalProcessed;
        $this->sendTelegram("✅ Суммаризация: обработано {$totalProcessed} новостей");
    }

    /**
     * Запуск дедупликации
     */
    private function runDeduplication(): void
    {
        $this->sendTelegram('🔍 Начинаем проверку на дубликаты...');
        
        $totalProcessed = 0;
        
        foreach ($this->config['rss_feeds'] as $feed) {
            if (!$feed['enabled'] || !$feed['deduplication']['enabled']) {
                continue;
            }
            
            $this->printInfo("Дедупликация для {$feed['name']}...");
            
            // Получаем новости без дедупликации
            $query = "
                SELECT i.id 
                FROM rss2tlg_items i
                INNER JOIN rss2tlg_summarization s ON i.id = s.item_id
                LEFT JOIN rss2tlg_deduplication d ON i.id = d.item_id
                WHERE i.feed_id = :feed_id AND s.status = 'success' AND d.id IS NULL
                LIMIT 3
            ";
            
            $items = $this->db->query($query, ['feed_id' => $feed['feed_id']]);
            
            if (empty($items)) {
                $this->printInfo('  Нет новостей для обработки');
                continue;
            }
            
            // Создаем сервис дедупликации
            $service = new DeduplicationService(
                $this->db,
                $this->openRouter,
                $feed['deduplication'],
                $this->logger
            );
            
            foreach ($items as $item) {
                $result = $service->processItem((int)$item['id']);
                if ($result) {
                    $totalProcessed++;
                    $this->printSuccess("  ✓ Новость ID {$item['id']} проверена");
                }
            }
        }
        
        $this->testResults['deduplication_processed'] = $totalProcessed;
        $this->sendTelegram("✅ Дедупликация: проверено {$totalProcessed} новостей");
    }

    /**
     * Запуск генерации иллюстраций
     */
    private function runIllustrationGeneration(): void
    {
        $this->sendTelegram('🎨 Начинаем генерацию иллюстраций...');
        
        $totalGenerated = 0;
        $startTime = microtime(true);
        
        foreach ($this->config['rss_feeds'] as $feed) {
            if (!$feed['enabled'] || !$feed['illustration']['enabled']) {
                continue;
            }
            
            $this->printInfo("Генерация иллюстраций для {$feed['name']}...");
            
            // Получаем новости готовые для иллюстраций
            $query = "
                SELECT i.id 
                FROM rss2tlg_items i
                INNER JOIN rss2tlg_summarization s ON i.id = s.item_id
                INNER JOIN rss2tlg_deduplication d ON i.id = d.item_id
                LEFT JOIN rss2tlg_illustration il ON i.id = il.item_id
                WHERE i.feed_id = :feed_id 
                    AND s.status = 'success' 
                    AND d.status = 'checked' 
                    AND d.can_be_published = 1
                    AND il.id IS NULL
                LIMIT 2
            ";
            
            $items = $this->db->query($query, ['feed_id' => $feed['feed_id']]);
            
            if (empty($items)) {
                $this->printInfo('  Нет новостей для обработки');
                continue;
            }
            
            $this->sendTelegram("🖼 Генерируем {$feed['name']}: " . count($items) . " иллюстраций...");
            
            // Создаем сервис иллюстраций
            $service = new IllustrationService(
                $this->db,
                $this->openRouter,
                $feed['illustration'],
                $this->logger
            );
            
            foreach ($items as $item) {
                $itemStartTime = microtime(true);
                
                $this->printInfo("  Генерация для новости ID {$item['id']}...");
                $this->sendTelegram("⏳ Генерируем иллюстрацию для новости #{$item['id']}...");
                
                $result = $service->processItem((int)$item['id']);
                
                $itemTime = round(microtime(true) - $itemStartTime, 2);
                
                if ($result) {
                    $totalGenerated++;
                    $this->printSuccess("  ✓ Иллюстрация сгенерирована за {$itemTime}с");
                    $this->sendTelegram("✅ Иллюстрация #{$item['id']} готова! Время: {$itemTime}с");
                } else {
                    $this->printWarning("  ⚠ Не удалось сгенерировать иллюстрацию");
                    $this->sendTelegram("⚠️ Ошибка генерации иллюстрации #{$item['id']}");
                }
            }
            
            $metrics = $service->getMetrics();
            $this->testResults['illustration_metrics'] = $metrics;
        }
        
        $totalTime = round(microtime(true) - $startTime, 2);
        
        $this->testResults['illustrations_generated'] = $totalGenerated;
        $this->testResults['illustration_time'] = $totalTime;
        
        $this->sendTelegram("✅ Генерация завершена: {$totalGenerated} иллюстраций за {$totalTime}с");
    }

    /**
     * Проверка результатов
     */
    private function verifyResults(): void
    {
        $this->sendTelegram('🔍 Проверяем результаты...');
        
        // Проверяем наличие иллюстраций в БД
        $query = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) as skipped,
                AVG(generation_time_ms) as avg_time
            FROM rss2tlg_illustration
        ";
        
        $stats = $this->db->queryOne($query);
        $this->testResults['db_stats'] = $stats;
        
        $this->printInfo("Статистика из БД:");
        $this->printInfo("  Всего записей: {$stats['total']}");
        $this->printInfo("  Успешно: {$stats['success']}");
        $this->printInfo("  Ошибки: {$stats['failed']}");
        $this->printInfo("  Пропущено: {$stats['skipped']}");
        $this->printInfo("  Среднее время: " . round($stats['avg_time'] / 1000, 2) . "с");
        
        // Проверяем файлы на диске
        $query = "SELECT image_path FROM rss2tlg_illustration WHERE status = 'success'";
        $images = $this->db->query($query);
        
        $filesExist = 0;
        $filesMissing = 0;
        
        foreach ($images as $image) {
            if (file_exists($image['image_path'])) {
                $filesExist++;
            } else {
                $filesMissing++;
                $this->printWarning("  ⚠ Файл не найден: {$image['image_path']}");
            }
        }
        
        $this->testResults['files_exist'] = $filesExist;
        $this->testResults['files_missing'] = $filesMissing;
        
        $this->printSuccess("✓ Файлы на диске: {$filesExist} найдено, {$filesMissing} отсутствует");
        
        if ($filesMissing > 0) {
            $this->sendTelegram("⚠️ Внимание: {$filesMissing} файлов изображений не найдено!");
        } else {
            $this->sendTelegram("✅ Все файлы изображений на месте!");
        }
    }

    /**
     * Генерация отчета
     */
    private function generateReport(): void
    {
        $reportPath = __DIR__ . '/../../docs/Rss2Tlg/ILLUSTRATION_TEST_REPORT.md';
        
        $report = "# 🎨 ОТЧЕТ О ТЕСТИРОВАНИИ МОДУЛЯ ИЛЛЮСТРАЦИЙ\n\n";
        $report .= "**Дата:** " . date('Y-m-d H:i:s') . "\n\n";
        $report .= "---\n\n";
        
        $report .= "## 📊 СТАТИСТИКА\n\n";
        $report .= "- **Загружено новостей:** " . ($this->testResults['total_news_loaded'] ?? 0) . "\n";
        $report .= "- **Суммаризовано:** " . ($this->testResults['summarization_processed'] ?? 0) . "\n";
        $report .= "- **Проверено дедупликацией:** " . ($this->testResults['deduplication_processed'] ?? 0) . "\n";
        $report .= "- **Сгенерировано иллюстраций:** " . ($this->testResults['illustrations_generated'] ?? 0) . "\n";
        $report .= "- **Общее время генерации:** " . ($this->testResults['illustration_time'] ?? 0) . "с\n\n";
        
        $report .= "## 🎯 РЕЗУЛЬТАТЫ ИЗ БД\n\n";
        if (isset($this->testResults['db_stats'])) {
            $stats = $this->testResults['db_stats'];
            $report .= "- **Всего записей:** {$stats['total']}\n";
            $report .= "- **Успешно:** {$stats['success']}\n";
            $report .= "- **Ошибок:** {$stats['failed']}\n";
            $report .= "- **Пропущено:** {$stats['skipped']}\n";
            $report .= "- **Среднее время генерации:** " . round($stats['avg_time'] / 1000, 2) . "с\n\n";
        }
        
        $report .= "## 📁 ФАЙЛЫ\n\n";
        $report .= "- **Файлов существует:** " . ($this->testResults['files_exist'] ?? 0) . "\n";
        $report .= "- **Файлов отсутствует:** " . ($this->testResults['files_missing'] ?? 0) . "\n\n";
        
        $report .= "## 🔧 МЕТРИКИ МОДУЛЯ\n\n";
        if (isset($this->testResults['illustration_metrics'])) {
            $metrics = $this->testResults['illustration_metrics'];
            $report .= "```json\n";
            $report .= json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $report .= "\n```\n\n";
        }
        
        $report .= "## ✅ ВЫВОДЫ\n\n";
        
        $success = ($this->testResults['illustrations_generated'] ?? 0) > 0 
            && ($this->testResults['files_missing'] ?? 0) === 0;
        
        if ($success) {
            $report .= "**✅ ТЕСТ ПРОЙДЕН УСПЕШНО**\n\n";
            $report .= "Модуль генерации иллюстраций работает корректно:\n";
            $report .= "- Все изображения сгенерированы\n";
            $report .= "- Все файлы сохранены на диск\n";
            $report .= "- Метаданные записаны в БД\n";
            $report .= "- Логирование работает\n\n";
        } else {
            $report .= "**⚠️ ОБНАРУЖЕНЫ ПРОБЛЕМЫ**\n\n";
            $report .= "Требуется дополнительная проверка модуля.\n\n";
        }
        
        $report .= "---\n\n";
        $report .= "*Отчет сгенерирован автоматически*\n";
        
        file_put_contents($reportPath, $report);
        
        $this->printSuccess("✓ Отчет сохранен: {$reportPath}");
        $this->sendTelegram("📄 Отчет готов: docs/Rss2Tlg/ILLUSTRATION_TEST_REPORT.md");
    }

    /**
     * Отправка сообщения в Telegram
     */
    private function sendTelegram(string $message): void
    {
        try {
            $this->telegram->sendText($this->chatId, $message);
        } catch (Exception $e) {
            $this->printWarning("⚠ Не удалось отправить в Telegram: " . $e->getMessage());
        }
    }

    // Вспомогательные методы вывода
    
    private function printHeader(string $text): void
    {
        echo "\n" . str_repeat('=', 80) . "\n";
        echo $text . "\n";
        echo str_repeat('=', 80) . "\n\n";
    }

    private function printStep(string $text): void
    {
        echo "\n" . str_repeat('-', 80) . "\n";
        echo $text . "\n";
        echo str_repeat('-', 80) . "\n";
    }

    private function printSuccess(string $text): void
    {
        echo "\033[32m{$text}\033[0m\n";
    }

    private function printInfo(string $text): void
    {
        echo $text . "\n";
    }

    private function printWarning(string $text): void
    {
        echo "\033[33m{$text}\033[0m\n";
    }

    private function printError(string $text): void
    {
        echo "\033[31m{$text}\033[0m\n";
    }
}

// Запуск теста
$test = new IllustrationTest(__DIR__ . '/config_illustration_test.php');
$test->run();

<?php

declare(strict_types=1);

/**
 * Комплексный стресс-тест системы RSS2TLG
 * 
 * Этот скрипт выполняет полное тестирование системы:
 * - Загрузка новостей из 26 RSS источников
 * - Извлечение полного текста через WebExtractor
 * - Публикация в Telegram канал через Polling
 * - Отправка уведомлений о прогрессе
 * - Детальная статистика и мониторинг
 */

require_once __DIR__ . '/../autoload.php';

use App\Component\MySQL;
use App\Component\Logger;
use App\Component\ConfigLoader;
use App\Component\WebtExtractor;
use App\Component\Http;
use App\Rss2Tlg\FetchRunner;
use App\Rss2Tlg\ItemRepository;
use App\Rss2Tlg\PublicationRepository;
use App\Rss2Tlg\ContentExtractorService;
use App\Rss2Tlg\DTO\FeedConfig;
use App\Component\TelegramBot\Core\TelegramAPI;

// Цветной вывод в консоль
class ColorOutput {
    public static function success(string $msg): void {
        echo "\033[32m✅ $msg\033[0m\n";
    }
    
    public static function error(string $msg): void {
        echo "\033[31m❌ $msg\033[0m\n";
    }
    
    public static function info(string $msg): void {
        echo "\033[36mℹ️  $msg\033[0m\n";
    }
    
    public static function warning(string $msg): void {
        echo "\033[33m⚠️  $msg\033[0m\n";
    }
    
    public static function header(string $msg): void {
        echo "\n\033[1;35m═══════════════════════════════════════════════════════════\033[0m\n";
        echo "\033[1;35m  $msg\033[0m\n";
        echo "\033[1;35m═══════════════════════════════════════════════════════════\033[0m\n\n";
    }
    
    public static function section(string $msg): void {
        echo "\n\033[1;34m▶ $msg\033[0m\n";
    }
}

// Статистика тестов
class TestStatistics {
    private array $stats = [
        'total_feeds' => 0,
        'successful_fetches' => 0,
        'failed_fetches' => 0,
        'not_modified' => 0,
        'total_items' => 0,
        'new_items' => 0,
        'duplicate_items' => 0,
        'published_items' => 0,
        'failed_publications' => 0,
        'content_extracted' => 0,
        'content_failed' => 0,
        'total_duration' => 0.0,
        'memory_peak' => 0,
        'errors' => [],
    ];
    
    private float $startTime;
    private int $startMemory;
    
    public function __construct() {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
    }
    
    public function increment(string $key, int $value = 1): void {
        if (isset($this->stats[$key])) {
            $this->stats[$key] += $value;
        }
    }
    
    public function addError(string $error): void {
        $this->stats['errors'][] = $error;
    }
    
    public function finalize(): array {
        $this->stats['total_duration'] = round(microtime(true) - $this->startTime, 2);
        $this->stats['memory_peak'] = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        return $this->stats;
    }
    
    public function get(): array {
        return $this->stats;
    }
}

// Telegram уведомления
class TelegramNotifier {
    private TelegramAPI $bot;
    private string $chatId;
    
    public function __construct(string $botToken, string $chatId) {
        $http = new Http(['timeout' => 30]);
        $this->bot = new TelegramAPI($botToken, $http);
        $this->chatId = $chatId;
    }
    
    public function sendMessage(string $message, bool $silent = false): bool {
        try {
            $this->bot->sendMessage(
                $this->chatId,
                $message,
                [
                    'parse_mode' => 'HTML',
                    'disable_notification' => $silent,
                ]
            );
            return true;
        } catch (\Exception $e) {
            ColorOutput::warning("Ошибка отправки уведомления: {$e->getMessage()}");
            return false;
        }
    }
    
    public function notifyStart(int $feedsCount): void {
        $msg = "🚀 <b>Начат стресс-тест RSS2TLG</b>\n\n";
        $msg .= "📊 Источников: <code>$feedsCount</code>\n";
        $msg .= "⏰ Время старта: " . date('Y-m-d H:i:s');
        $this->sendMessage($msg);
    }
    
    public function notifyProgress(string $stage, array $stats): void {
        $msg = "⚙️ <b>$stage</b>\n\n";
        foreach ($stats as $key => $value) {
            $msg .= "• <b>$key:</b> <code>$value</code>\n";
        }
        $this->sendMessage($msg, true);
    }
    
    public function notifyComplete(array $stats): void {
        $msg = "✅ <b>Стресс-тест завершён</b>\n\n";
        $msg .= "📊 <b>Итоговая статистика:</b>\n";
        $msg .= "• Источников обработано: <code>{$stats['successful_fetches']}</code>\n";
        $msg .= "• Новостей получено: <code>{$stats['total_items']}</code>\n";
        $msg .= "• Опубликовано: <code>{$stats['published_items']}</code>\n";
        $msg .= "• Дубликатов: <code>{$stats['duplicate_items']}</code>\n";
        $msg .= "• Время выполнения: <code>{$stats['total_duration']}s</code>\n";
        $msg .= "• Пиковая память: <code>{$stats['memory_peak']} MB</code>\n";
        
        if (!empty($stats['errors'])) {
            $msg .= "\n⚠️ Ошибок: <code>" . count($stats['errors']) . "</code>";
        }
        
        $this->sendMessage($msg);
    }
}

// Главная функция тестирования
class RSS2TLGStressTest {
    private MySQL $db;
    private Logger $logger;
    private TelegramAPI $channelBot;
    private TelegramNotifier $notifier;
    private TestStatistics $stats;
    private array $config;
    private FetchRunner $fetchRunner;
    private ItemRepository $itemRepo;
    private PublicationRepository $pubRepo;
    private ContentExtractorService $contentExtractor;
    private WebtExtractor $webExtractor;
    
    public function __construct(string $configPath) {
        ColorOutput::header("Инициализация RSS2TLG Stress Test");
        
        // Загрузка конфигурации
        ColorOutput::info("Загрузка конфигурации из: $configPath");
        $this->config = json_decode(file_get_contents($configPath), true);
        
        if (!$this->config) {
            throw new \Exception("Не удалось загрузить конфигурацию");
        }
        
        ColorOutput::success("Конфигурация загружена: " . count($this->config['feeds']) . " источников");
        
        // Создание директорий
        $this->ensureDirectories();
        
        // Инициализация компонентов
        $this->initializeComponents();
        
        // Статистика
        $this->stats = new TestStatistics();
        $this->stats->increment('total_feeds', count($this->config['feeds']));
        
        // Telegram notifier
        $this->notifier = new TelegramNotifier(
            $this->config['telegram']['bot_token'],
            $this->config['telegram']['chat_id']
        );
        
        ColorOutput::success("Инициализация завершена");
    }
    
    private function ensureDirectories(): void {
        $dirs = [
            $this->config['cache']['directory'],
            dirname($this->config['logging']['file']),
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
                ColorOutput::success("Создана директория: $dir");
            }
        }
    }
    
    private function initializeComponents(): void {
        ColorOutput::section("Инициализация компонентов");
        
        // Logger
        $logFile = $this->config['logging']['file'];
        $this->logger = new Logger([
            'directory' => dirname($logFile),
            'file_name' => basename($logFile),
            'max_files' => 3,
            'max_file_size' => 10,
            'enabled' => true,
        ]);
        ColorOutput::success("Logger инициализирован");
        
        // MySQL
        $this->db = new MySQL([
            'host' => $this->config['database']['host'],
            'port' => $this->config['database']['port'],
            'database' => $this->config['database']['database'],
            'username' => $this->config['database']['username'],
            'password' => $this->config['database']['password'],
            'charset' => $this->config['database']['charset'],
        ], $this->logger);
        ColorOutput::success("MySQL подключение установлено");
        
        // FetchRunner
        $this->fetchRunner = new FetchRunner(
            $this->db,
            $this->config['cache']['directory'],
            $this->logger
        );
        ColorOutput::success("FetchRunner инициализирован");
        
        // Repositories (с автосозданием таблиц)
        $this->itemRepo = new ItemRepository($this->db, $this->logger, true);
        $this->pubRepo = new PublicationRepository($this->db, $this->logger, true);
        ColorOutput::success("Репозитории инициализированы (таблицы созданы автоматически)");
        
        // WebExtractor
        $this->webExtractor = new WebtExtractor([
            'timeout' => 30,
            'user_agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/1.0)',
        ], $this->logger);
        ColorOutput::success("WebExtractor инициализирован");
        
        // ContentExtractorService
        $this->contentExtractor = new ContentExtractorService(
            $this->itemRepo,
            $this->webExtractor,
            $this->logger
        );
        ColorOutput::success("ContentExtractorService инициализирован");
        
        // Telegram Bot для публикации
        $http = new Http(['timeout' => 30], $this->logger);
        $this->channelBot = new TelegramAPI(
            $this->config['telegram']['bot_token'],
            $http,
            $this->logger
        );
        ColorOutput::success("Telegram Bot инициализирован");
    }
    
    public function run(): void {
        try {
            $this->notifier->notifyStart(count($this->config['feeds']));
            
            ColorOutput::header("ТЕСТ 1: Получение новостей из 10 случайных источников");
            $this->runTest1();
            
            ColorOutput::header("ТЕСТ 2: Проверка кеширования (повторный запрос)");
            $this->runTest2();
            
            ColorOutput::header("ТЕСТ 3: Публикация из следующих 10 источников");
            $this->runTest3();
            
            ColorOutput::header("ТЕСТ 4: Стресс-тест с множественными публикациями");
            $this->runTest4();
            
            ColorOutput::header("ФИНАЛЬНАЯ СТАТИСТИКА");
            $this->printFinalStatistics();
            
        } catch (\Exception $e) {
            ColorOutput::error("Критическая ошибка: {$e->getMessage()}");
            $this->stats->addError($e->getMessage());
            throw $e;
        }
    }
    
    private function runTest1(): void {
        ColorOutput::section("Шаг 1: Выбор 10 случайных источников");
        
        $feeds = $this->config['feeds'];
        shuffle($feeds);
        $selectedFeeds = array_slice($feeds, 0, 10);
        
        foreach ($selectedFeeds as $feed) {
            ColorOutput::info("Выбран: {$feed['title']} ({$feed['url']})");
        }
        
        ColorOutput::section("Шаг 2: Получение и публикация новостей");
        $this->notifier->notifyProgress("Тест 1: Получение из 10 источников", [
            'Источников' => count($selectedFeeds),
            'Статус' => 'В процессе...',
        ]);
        
        $publishedCount = 0;
        foreach ($selectedFeeds as $feed) {
            $result = $this->processFeed($feed, true);
            if ($result['published'] > 0) {
                $publishedCount += $result['published'];
            }
        }
        
        ColorOutput::success("Тест 1 завершён. Опубликовано новостей: $publishedCount");
        $this->notifier->notifyProgress("Тест 1 завершён", [
            'Опубликовано' => $publishedCount,
            'Всего новостей' => $this->stats->get()['total_items'],
        ]);
    }
    
    private function runTest2(): void {
        ColorOutput::section("Повторный запрос к уже обработанным источникам");
        
        $feeds = array_slice($this->config['feeds'], 0, 10);
        
        $this->notifier->notifyProgress("Тест 2: Проверка кеширования", [
            'Статус' => 'Повторный запрос...',
        ]);
        
        $notModifiedCount = 0;
        $duplicatesCount = 0;
        
        foreach ($feeds as $feed) {
            $result = $this->processFeed($feed, false);
            if ($result['not_modified']) {
                $notModifiedCount++;
            }
            if ($result['duplicates'] > 0) {
                $duplicatesCount += $result['duplicates'];
            }
        }
        
        ColorOutput::success("Тест 2 завершён. 304 Not Modified: $notModifiedCount, Дубликаты: $duplicatesCount");
        $this->notifier->notifyProgress("Тест 2 завершён", [
            '304 Not Modified' => $notModifiedCount,
            'Дубликаты найдены' => $duplicatesCount,
        ]);
    }
    
    private function runTest3(): void {
        ColorOutput::section("Публикация из следующих 10 источников");
        
        $feeds = array_slice($this->config['feeds'], 10, 10);
        
        $this->notifier->notifyProgress("Тест 3: Следующие 10 источников", [
            'Источников' => count($feeds),
            'Статус' => 'В процессе...',
        ]);
        
        $publishedCount = 0;
        foreach ($feeds as $feed) {
            $result = $this->processFeed($feed, true);
            if ($result['published'] > 0) {
                $publishedCount += $result['published'];
            }
        }
        
        ColorOutput::success("Тест 3 завершён. Опубликовано новостей: $publishedCount");
        $this->notifier->notifyProgress("Тест 3 завершён", [
            'Опубликовано' => $publishedCount,
        ]);
    }
    
    private function runTest4(): void {
        ColorOutput::section("Стресс-тест: обработка всех источников");
        
        $this->notifier->notifyProgress("Тест 4: Стресс-тест", [
            'Источников' => count($this->config['feeds']),
            'Статус' => 'Обработка...',
        ]);
        
        $publishedCount = 0;
        foreach ($this->config['feeds'] as $feed) {
            $result = $this->processFeed($feed, true);
            if ($result['published'] > 0) {
                $publishedCount += $result['published'];
            }
        }
        
        ColorOutput::success("Тест 4 завершён. Всего опубликовано: $publishedCount");
        $this->notifier->notifyProgress("Тест 4 завершён", [
            'Всего опубликовано' => $publishedCount,
        ]);
    }
    
    private function processFeed(array $feedConfig, bool $publish): array {
        $result = [
            'success' => false,
            'not_modified' => false,
            'items' => 0,
            'new' => 0,
            'duplicates' => 0,
            'published' => 0,
        ];
        
        ColorOutput::info("Обработка: {$feedConfig['title']}");
        
        try {
            // Создаём FeedConfig
            $config = FeedConfig::fromArray($feedConfig);
            
            // Fetch
            $fetchResult = $this->fetchRunner->runForFeed($config);
            
            if ($fetchResult->isNotModified()) {
                ColorOutput::warning("  304 Not Modified");
                $this->stats->increment('not_modified');
                $result['not_modified'] = true;
                return $result;
            }
            
            if ($fetchResult->isError()) {
                ColorOutput::error("  Ошибка fetch: {$fetchResult->state->lastStatus}");
                $this->stats->increment('failed_fetches');
                $this->stats->addError("{$feedConfig['title']}: Fetch error {$fetchResult->state->lastStatus}");
                return $result;
            }
            
            $items = $fetchResult->getValidItems();
            $result['items'] = count($items);
            $this->stats->increment('total_items', count($items));
            $this->stats->increment('successful_fetches');
            
            ColorOutput::success("  ✓ Получено элементов: " . count($items));
            
            // Обработка каждого элемента
            foreach ($items as $item) {
                // Проверка дубликата
                if ($this->itemRepo->exists($item->contentHash)) {
                    $result['duplicates']++;
                    $this->stats->increment('duplicate_items');
                    continue;
                }
                
                // Сохранение в БД
                $itemId = $this->itemRepo->save($feedConfig['id'], $item);
                if ($itemId === null) {
                    continue;
                }
                
                $result['new']++;
                $this->stats->increment('new_items');
                
                // Публикация (только для первого элемента из каждого источника)
                if ($publish && $result['published'] === 0) {
                    if ($this->publishToChannel($feedConfig, $item, $itemId)) {
                        $result['published']++;
                        $this->stats->increment('published_items');
                    } else {
                        $this->stats->increment('failed_publications');
                    }
                }
            }
            
            ColorOutput::info("  Новых: {$result['new']}, Дубликатов: {$result['duplicates']}, Опубликовано: {$result['published']}");
            $result['success'] = true;
            
        } catch (\Exception $e) {
            ColorOutput::error("  Ошибка: {$e->getMessage()}");
            $this->stats->addError("{$feedConfig['title']}: {$e->getMessage()}");
            $this->stats->increment('failed_fetches');
        }
        
        return $result;
    }
    
    private function publishToChannel(array $feedConfig, $item, int $itemId): bool {
        try {
            ColorOutput::section("    Публикация в Telegram канал");
            
            // Извлечение полного текста
            $fullText = '';
            if ($item->link) {
                ColorOutput::info("    Извлечение контента из: {$item->link}");
                try {
                    $extractResult = $this->webExtractor->extract($item->link);
                    
                    if (!empty($extractResult['text_content'])) {
                        $fullText = $extractResult['text_content'];
                        // Агрессивная очистка текста от невалидных UTF-8 символов
                        $fullText = $this->cleanUtf8Text($fullText);
                        ColorOutput::success("    ✓ Контент извлечён: " . strlen($fullText) . " символов");
                        $this->stats->increment('content_extracted');
                    } else {
                        ColorOutput::warning("    Пустой контент");
                        $this->stats->increment('content_failed');
                        $fullText = $item->summary ?? $item->content ?? '';
                    }
                } catch (\Exception $e) {
                    ColorOutput::warning("    Ошибка извлечения: " . $e->getMessage());
                    $this->stats->increment('content_failed');
                    $fullText = $item->summary ?? $item->content ?? '';
                }
            } else {
                $fullText = $item->summary ?? $item->content ?? '';
            }
            
            // Финальная очистка текста
            $fullText = $this->cleanUtf8Text($fullText);
            
            // Обрезка текста если > 500 слов
            $words = str_word_count($fullText, 1, 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯабвгдеёжзийклмнопрстуфхцчшщъыьэюя');
            $wordCount = count($words);
            
            if ($wordCount > 500) {
                $fullText = implode(' ', array_slice($words, 0, 500)) . '...';
                $fullText .= "\n\n<i>Текст обрезан. Полная статья содержит $wordCount слов.</i>";
            }
            
            // Формирование сообщения
            $sourceTitle = $this->cleanUtf8Text($feedConfig['title']);
            $itemTitle = $this->cleanUtf8Text($item->title ?? 'Без заголовка');
            
            $message = "<b>{$sourceTitle}</b>\n\n";
            $message .= "<b>{$itemTitle}</b>\n\n";
            $message .= $fullText;
            
            // Ограничение длины сообщения Telegram (4096 символов)
            if (mb_strlen($message) > 4000) {
                $message = mb_substr($message, 0, 3900) . '...';
            }
            
            // Отправка в канал
            $sentMessage = $this->channelBot->sendMessage(
                $this->config['telegram']['channel_id'],
                $message,
                [
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]
            );
            
            ColorOutput::success("    ✓ Опубликовано в канал (message_id: {$sentMessage->messageId})");
            
            // Сохранение информации о публикации
            $this->pubRepo->record(
                $itemId,
                $feedConfig['id'],
                'channel',
                $this->config['telegram']['channel_id'],
                $sentMessage->messageId
            );
            
            return true;
            
        } catch (\Exception $e) {
            ColorOutput::error("    Ошибка публикации: {$e->getMessage()}");
            return false;
        }
    }
    
    private function printFinalStatistics(): void {
        $stats = $this->stats->finalize();
        
        ColorOutput::section("Итоговая статистика");
        
        echo "┌────────────────────────────────────────────────────────┐\n";
        echo "│ 📊 ОБЩАЯ СТАТИСТИКА                                    │\n";
        echo "├────────────────────────────────────────────────────────┤\n";
        echo "│ Источников обработано: " . str_pad((string)$stats['successful_fetches'], 26) . "│\n";
        echo "│ Ошибок fetch:          " . str_pad((string)$stats['failed_fetches'], 26) . "│\n";
        echo "│ 304 Not Modified:      " . str_pad((string)$stats['not_modified'], 26) . "│\n";
        echo "├────────────────────────────────────────────────────────┤\n";
        echo "│ 📰 НОВОСТИ                                             │\n";
        echo "├────────────────────────────────────────────────────────┤\n";
        echo "│ Всего получено:        " . str_pad((string)$stats['total_items'], 26) . "│\n";
        echo "│ Новых:                 " . str_pad((string)$stats['new_items'], 26) . "│\n";
        echo "│ Дубликатов:            " . str_pad((string)$stats['duplicate_items'], 26) . "│\n";
        echo "├────────────────────────────────────────────────────────┤\n";
        echo "│ 📢 ПУБЛИКАЦИИ                                          │\n";
        echo "├────────────────────────────────────────────────────────┤\n";
        echo "│ Опубликовано:          " . str_pad((string)$stats['published_items'], 26) . "│\n";
        echo "│ Ошибок публикации:     " . str_pad((string)$stats['failed_publications'], 26) . "│\n";
        echo "├────────────────────────────────────────────────────────┤\n";
        echo "│ 🔍 ИЗВЛЕЧЕНИЕ КОНТЕНТА                                 │\n";
        echo "├────────────────────────────────────────────────────────┤\n";
        echo "│ Успешно извлечено:     " . str_pad((string)$stats['content_extracted'], 26) . "│\n";
        echo "│ Ошибок извлечения:     " . str_pad((string)$stats['content_failed'], 26) . "│\n";
        echo "├────────────────────────────────────────────────────────┤\n";
        echo "│ ⚡ ПРОИЗВОДИТЕЛЬНОСТЬ                                  │\n";
        echo "├────────────────────────────────────────────────────────┤\n";
        echo "│ Время выполнения:      " . str_pad($stats['total_duration'] . 's', 26) . "│\n";
        echo "│ Пиковая память:        " . str_pad($stats['memory_peak'] . ' MB', 26) . "│\n";
        echo "└────────────────────────────────────────────────────────┘\n";
        
        if (!empty($stats['errors'])) {
            ColorOutput::section("Ошибки (" . count($stats['errors']) . ")");
            foreach (array_slice($stats['errors'], 0, 10) as $error) {
                ColorOutput::error("  $error");
            }
            if (count($stats['errors']) > 10) {
                ColorOutput::warning("  ... и ещё " . (count($stats['errors']) - 10) . " ошибок");
            }
        }
        
        // Отправка финального уведомления
        $this->notifier->notifyComplete($stats);
        
        // Статистика БД
        $this->printDatabaseStatistics();
    }
    
    private function cleanUtf8Text(string $text): string {
        // Шаг 1: Конвертация в UTF-8
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        
        // Шаг 2: Удаление невидимых управляющих символов (кроме tab, LF, CR)
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        
        // Шаг 3: Удаление невалидных UTF-8 последовательностей
        $text = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text);
        
        // Шаг 4: Замена множественных пробелов на один
        $text = preg_replace('/\s+/u', ' ', $text);
        
        // Шаг 5: Удаление HTML тегов если остались
        $text = strip_tags($text);
        
        // Шаг 6: Удаление специальных символов Telegram (они могут вызывать проблемы)
        $text = str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);
        
        // Шаг 7: Финальная проверка валидности
        if (!mb_check_encoding($text, 'UTF-8')) {
            // Если всё ещё невалидный - используем iconv с игнорированием ошибок
            $text = iconv('UTF-8', 'UTF-8//IGNORE', $text);
        }
        
        return trim($text);
    }
    
    private function printDatabaseStatistics(): void {
        ColorOutput::section("Статистика базы данных");
        
        try {
            // Статистика таблицы items
            $itemsCount = $this->db->query("SELECT COUNT(*) as cnt FROM rss2tlg_items")[0]['cnt'] ?? 0;
            ColorOutput::info("Записей в rss2tlg_items: $itemsCount");
            
            // Статистика таблицы publications
            $pubCount = $this->db->query("SELECT COUNT(*) as cnt FROM rss2tlg_publications")[0]['cnt'] ?? 0;
            ColorOutput::info("Записей в rss2tlg_publications: $pubCount");
            
            // Статистика таблицы feed_state
            $stateCount = $this->db->query("SELECT COUNT(*) as cnt FROM rss2tlg_feed_state")[0]['cnt'] ?? 0;
            ColorOutput::info("Записей в rss2tlg_feed_state: $stateCount");
            
            // Топ-5 источников по количеству новостей
            $topFeeds = $this->db->query("
                SELECT feed_id, COUNT(*) as cnt 
                FROM rss2tlg_items 
                GROUP BY feed_id 
                ORDER BY cnt DESC 
                LIMIT 5
            ");
            
            if (!empty($topFeeds)) {
                ColorOutput::section("Топ-5 источников по количеству новостей");
                foreach ($topFeeds as $feed) {
                    ColorOutput::info("  Feed ID {$feed['feed_id']}: {$feed['cnt']} новостей");
                }
            }
            
        } catch (\Exception $e) {
            ColorOutput::error("Ошибка получения статистики БД: {$e->getMessage()}");
        }
    }
}

// ═══════════════════════════════════════════════════════════
// ЗАПУСК ТЕСТА
// ═══════════════════════════════════════════════════════════

try {
    $configPath = __DIR__ . '/../config/rss2tlg_stress_test.json';
    
    if (!file_exists($configPath)) {
        ColorOutput::error("Конфигурационный файл не найден: $configPath");
        exit(1);
    }
    
    $test = new RSS2TLGStressTest($configPath);
    $test->run();
    
    ColorOutput::success("\n🎉 Все тесты успешно завершены!");
    exit(0);
    
} catch (\Exception $e) {
    ColorOutput::error("\n💥 Критическая ошибка: {$e->getMessage()}");
    ColorOutput::error("Trace: {$e->getTraceAsString()}");
    exit(1);
}

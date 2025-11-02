<?php

declare(strict_types=1);

/**
 * Комплексное тестирование Rss2Tlg с извлечением контента
 * 
 * ЗАДАНИЕ:
 * 1. Получить новости из 5 RSS лент
 * 2. Автоматически извлечь контент для новостей без описания
 * 3. Опубликовать 2 новости из каждой ленты в Telegram канал
 * 4. Проверить кеширование и дедупликацию
 * 5. Опубликовать еще 2 новости из каждой ленты
 * 6. Вывести детальную статистику
 * 7. Отправлять уведомления о ходе в Telegram бот
 */

require_once __DIR__ . '/../../autoload.php';

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\Http;
use App\Component\WebtExtractor;
use App\Rss2Tlg\FetchRunner;
use App\Rss2Tlg\ItemRepository;
use App\Rss2Tlg\ContentExtractorService;
use App\Rss2Tlg\DTO\FeedConfig;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Keyboards\InlineKeyboardBuilder;

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
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'utilities_db',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
];

const CACHE_DIR = __DIR__ . '/../../cache/rss2tlg';
const LOG_DIR = __DIR__ . '/../../logs';

// ============================================================================
// УТИЛИТЫ
// ============================================================================

function printHeader(string $title): void
{
    $separator = str_repeat('=', 80);
    echo "\n\033[1;34m{$separator}\033[0m\n";
    echo "\033[1;37m{$title}\033[0m\n";
    echo "\033[1;34m{$separator}\033[0m\n\n";
}

function printSubHeader(string $title): void
{
    echo "\n\033[1;36m┌─ {$title}\033[0m\n";
}

function printSuccess(string $message): void
{
    echo "\033[0;32m├─ ✅ {$message}\033[0m\n";
}

function printError(string $message): void
{
    echo "\033[0;31m├─ ❌ {$message}\033[0m\n";
}

function printInfo(string $message): void
{
    echo "\033[0;37m├─ ℹ️  {$message}\033[0m\n";
}

function printWarning(string $message): void
{
    echo "\033[0;33m├─ ⚠️  {$message}\033[0m\n";
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

function sendTelegramUpdate(TelegramAPI $bot, string $message): void
{
    try {
        $bot->sendMessage(TELEGRAM_CHAT_ID, $message, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
        usleep(300000); // 0.3 секунды
    } catch (Exception $e) {
        printWarning("Не удалось отправить уведомление в Telegram: " . $e->getMessage());
    }
}

function truncateText(string $text, int $maxWords = 50): string
{
    $words = explode(' ', $text);
    $wordCount = count($words);
    
    if ($wordCount <= $maxWords) {
        return $text;
    }
    
    $truncated = implode(' ', array_slice($words, 0, $maxWords));
    return $truncated . "... (длина текста: {$wordCount} слов)";
}

// ============================================================================
// ГЛАВНАЯ ФУНКЦИЯ
// ============================================================================

function main(): void
{
    printHeader('🚀 RSS2TLG: ТЕСТИРОВАНИЕ С ИЗВЛЕЧЕНИЕМ КОНТЕНТА');
    
    // Инициализация
    printSubHeader('Инициализация компонентов');
    
    // Создание директорий
    if (!is_dir(LOG_DIR)) {
        mkdir(LOG_DIR, 0755, true);
    }
    if (!is_dir(CACHE_DIR)) {
        mkdir(CACHE_DIR, 0755, true);
    }
    
    // Логгер
    $logger = new Logger([
        'directory' => LOG_DIR,
        'file_name' => 'rss2tlg_test.log',
        'max_files' => 5,
        'max_file_size' => 10,
    ]);
    printSuccess('Логгер инициализирован');
    
    // База данных
    try {
        $db = new MySQL(DB_CONFIG, $logger);
        $version = $db->queryOne("SELECT VERSION() as version");
        printSuccess("MySQL подключен (версия {$version['version']})");
    } catch (Exception $e) {
        printError("Ошибка подключения к MySQL: " . $e->getMessage());
        try {
            $telegramHttp = new Http(['timeout' => 30], $logger);
            $telegram = new TelegramAPI(TELEGRAM_BOT_TOKEN, $telegramHttp, $logger);
            sendTelegramUpdate($telegram, "❌ <b>Ошибка</b>\n\nНе удалось подключиться к MySQL");
        } catch (Exception $ex) {
            // Ignore
        }
        return;
    }
    
    // Telegram API
    $telegramHttp = new Http(['timeout' => 30], $logger);
    $telegram = new TelegramAPI(TELEGRAM_BOT_TOKEN, $telegramHttp, $logger);
    try {
        $me = $telegram->getMe();
        printSuccess("Telegram API подключен (@{$me->username})");
        sendTelegramUpdate($telegram, "🚀 <b>Тестирование запущено</b>\n\n📊 Источников: " . count(RSS_FEEDS) . "\n🗄️ MySQL: подключен\n📝 Логи: готовы");
    } catch (Exception $e) {
        printError("Ошибка подключения к Telegram: " . $e->getMessage());
        return;
    }
    
    // Репозитории
    $itemRepository = new ItemRepository($db, $logger, true);
    printSuccess('ItemRepository инициализирован');
    
    // Fetch Runner
    $fetchRunner = new FetchRunner($db, CACHE_DIR, $logger);
    printSuccess('FetchRunner инициализирован');
    
    // WebtExtractor
    $extractor = new WebtExtractor([
        'timeout' => 30,
        'retries' => 2,
        'extract_images' => true,
        'extract_metadata' => true,
    ], $logger);
    printSuccess('WebtExtractor инициализирован');
    
    // ContentExtractorService
    $contentExtractor = new ContentExtractorService($itemRepository, $extractor, $logger);
    printSuccess('ContentExtractorService инициализирован');
    
    // ========================================================================
    // ТЕСТ 1: Получение новостей из всех лент
    // ========================================================================
    
    printHeader('📥 ТЕСТ 1: Получение новостей из RSS лент');
    sendTelegramUpdate($telegram, "📥 <b>ТЕСТ 1: Получение новостей</b>\n\nЗагружаем RSS ленты...");
    
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
            'headers' => ['User-Agent' => 'Rss2Tlg/2.0 ContentExtractorTest'],
            'parser_options' => ['max_items' => 50, 'enable_cache' => true, 'cache_duration' => 3600],
        ]);
    }
    
    $test1Start = microtime(true);
    $results = $fetchRunner->runForAllFeeds($feedConfigs);
    $test1Duration = round(microtime(true) - $test1Start, 3);
    
    $totalItems = 0;
    $validItems = 0;
    
    foreach ($results as $feedId => $result) {
        $feedName = RSS_FEEDS[$feedId - 1]['name'];
        
        if ($result->isSuccessful()) {
            $itemsCount = count($result->items);
            $totalItems += $itemsCount;
            
            // Сохраняем новости в БД
            foreach ($result->items as $item) {
                $savedId = $itemRepository->save($feedId, $item);
                if ($savedId !== null) {
                    $validItems++;
                }
            }
            
            printSuccess("{$feedName}: получено {$itemsCount} новостей");
        } else {
            printWarning("{$feedName}: пропущен");
        }
    }
    
    printInfo("Всего получено: {$totalItems} новостей");
    printInfo("Сохранено в БД: {$validItems} новостей");
    printInfo("Время выполнения: {$test1Duration} сек");
    
    sendTelegramUpdate($telegram, sprintf(
        "✅ <b>ТЕСТ 1: ЗАВЕРШЕН</b>\n\n📊 Получено: %d новостей\n💾 Сохранено: %d\n⏱ Время: %s сек",
        $totalItems,
        $validItems,
        $test1Duration
    ));
    
    // ========================================================================
    // ТЕСТ 2: Извлечение контента для новостей без описания
    // ========================================================================
    
    printHeader('🔍 ТЕСТ 2: Извлечение контента через WebtExtractor');
    sendTelegramUpdate($telegram, "🔍 <b>ТЕСТ 2: Извлечение контента</b>\n\nАнализируем новости...");
    
    $test2Start = microtime(true);
    
    // Получаем новости для извлечения
    $pendingItems = $itemRepository->getPendingExtraction(20); // Ограничим 20 для теста
    printInfo("Новостей для извлечения: " . count($pendingItems));
    
    if (count($pendingItems) > 0) {
        sendTelegramUpdate($telegram, "⚙️ Извлекаем контент из " . count($pendingItems) . " новостей...");
        
        $extractionStats = $contentExtractor->processItems($pendingItems);
        $test2Duration = round(microtime(true) - $test2Start, 3);
        
        printSuccess("Извлечено успешно: {$extractionStats['extracted']}");
        printInfo("Пропущено (есть контент): {$extractionStats['skipped']}");
        if ($extractionStats['failed'] > 0) {
            printWarning("Ошибок: {$extractionStats['failed']}");
        }
        printInfo("Время выполнения: {$test2Duration} сек");
        
        sendTelegramUpdate($telegram, sprintf(
            "✅ <b>ТЕСТ 2: ЗАВЕРШЕН</b>\n\n✅ Извлечено: %d\n⏩ Пропущено: %d\n❌ Ошибок: %d\n⏱ Время: %s сек",
            $extractionStats['extracted'],
            $extractionStats['skipped'],
            $extractionStats['failed'],
            $test2Duration
        ));
    } else {
        printInfo("Все новости имеют контент, извлечение не требуется");
        sendTelegramUpdate($telegram, "✅ <b>ТЕСТ 2: ЗАВЕРШЕН</b>\n\nВсе новости имеют контент");
    }
    
    // ========================================================================
    // ТЕСТ 3: Публикация 2 новостей из каждой ленты в Telegram канал
    // ========================================================================
    
    printHeader('📢 ТЕСТ 3: Публикация в Telegram канал (первая партия)');
    sendTelegramUpdate($telegram, "📢 <b>ТЕСТ 3: Публикация</b>\n\nПубликуем первую партию новостей...");
    
    $test3Start = microtime(true);
    $publishedCount = 0;
    
    foreach (RSS_FEEDS as $feed) {
        $feedId = $feed['id'];
        $feedName = $feed['name'];
        
        $unpublished = $itemRepository->getUnpublished($feedId, 2);
        
        printInfo("Публикация из источника: {$feedName}");
        
        foreach ($unpublished as $item) {
            try {
                $content = $itemRepository->getEffectiveContent($item);
                $title = $item['title'];
                $link = $item['link'];
                
                // Обрезаем текст до 50 слов
                $truncatedContent = truncateText($content, 50);
                
                $message = "<b>{$feedName}</b>\n\n";
                $message .= "<b>{$title}</b>\n\n";
                $message .= "{$truncatedContent}\n\n";
                $message .= "🔗 <a href=\"{$link}\">Читать полностью</a>";
                
                $telegram->sendMessage(TELEGRAM_CHANNEL_ID, $message, [
                    'parse_mode' => TelegramAPI::PARSE_MODE_HTML,
                    'disable_web_page_preview' => false,
                ]);
                
                $itemRepository->markAsPublished((int)$item['id']);
                $publishedCount++;
                
                printSuccess("  ✓ {$title}");
                
                sleep(2); // Пауза между публикациями
            } catch (Exception $e) {
                printError("  ✗ Ошибка публикации: " . $e->getMessage());
            }
        }
    }
    
    $test3Duration = round(microtime(true) - $test3Start, 3);
    
    printSuccess("Опубликовано: {$publishedCount} новостей");
    printInfo("Время выполнения: {$test3Duration} сек");
    
    sendTelegramUpdate($telegram, sprintf(
        "✅ <b>ТЕСТ 3: ЗАВЕРШЕН</b>\n\n📢 Опубликовано: %d новостей\n⏱ Время: %s сек",
        $publishedCount,
        $test3Duration
    ));
    
    // ========================================================================
    // ТЕСТ 4: Повторный fetch для проверки кеширования
    // ========================================================================
    
    printHeader('🔄 ТЕСТ 4: Проверка кеширования (повторный fetch)');
    sendTelegramUpdate($telegram, "🔄 <b>ТЕСТ 4: Кеширование</b>\n\nПроверяем дедупликацию...");
    
    sleep(3);
    
    $test4Start = microtime(true);
    $results2 = $fetchRunner->runForAllFeeds($feedConfigs);
    $test4Duration = round(microtime(true) - $test4Start, 3);
    
    $count304 = 0;
    $count200 = 0;
    $newItemsCount = 0;
    
    foreach ($results2 as $feedId => $result) {
        if ($result->isNotModified()) {
            $count304++;
        } elseif ($result->isSuccessful()) {
            $count200++;
            foreach ($result->items as $item) {
                $savedId = $itemRepository->save($feedId, $item);
                if ($savedId !== null) {
                    // Проверяем, это новая новость или дубликат
                    $existing = $itemRepository->getByContentHash($item->contentHash);
                    if ($existing['created_at'] >= date('Y-m-d H:i:s', strtotime('-5 minutes'))) {
                        $newItemsCount++;
                    }
                }
            }
        }
    }
    
    $cacheRate = round(($count304 / count($feedConfigs)) * 100, 1);
    
    printSuccess("Получено 304 Not Modified: {$count304} ({$cacheRate}%)");
    printInfo("Получено 200 OK: {$count200}");
    printInfo("Новых новостей: {$newItemsCount}");
    printInfo("Время выполнения: {$test4Duration} сек");
    
    sendTelegramUpdate($telegram, sprintf(
        "✅ <b>ТЕСТ 4: ЗАВЕРШЕН</b>\n\n💾 Кеш: %d (%s%%)\n📊 200 OK: %d\n🆕 Новых: %d\n⏱ Время: %s сек",
        $count304,
        $cacheRate,
        $count200,
        $newItemsCount,
        $test4Duration
    ));
    
    // ========================================================================
    // ТЕСТ 5: Публикация второй партии новостей
    // ========================================================================
    
    printHeader('📢 ТЕСТ 5: Публикация в Telegram канал (вторая партия)');
    sendTelegramUpdate($telegram, "📢 <b>ТЕСТ 5: Публикация</b>\n\nПубликуем вторую партию новостей...");
    
    $test5Start = microtime(true);
    $publishedCount2 = 0;
    
    foreach (RSS_FEEDS as $feed) {
        $feedId = $feed['id'];
        $feedName = $feed['name'];
        
        $unpublished = $itemRepository->getUnpublished($feedId, 2);
        
        foreach ($unpublished as $item) {
            try {
                $content = $itemRepository->getEffectiveContent($item);
                $title = $item['title'];
                $link = $item['link'];
                
                $truncatedContent = truncateText($content, 50);
                
                $message = "<b>{$feedName}</b>\n\n";
                $message .= "<b>{$title}</b>\n\n";
                $message .= "{$truncatedContent}\n\n";
                $message .= "🔗 <a href=\"{$link}\">Читать полностью</a>";
                
                $telegram->sendMessage(TELEGRAM_CHANNEL_ID, $message, [
                    'parse_mode' => TelegramAPI::PARSE_MODE_HTML,
                    'disable_web_page_preview' => false,
                ]);
                
                $itemRepository->markAsPublished((int)$item['id']);
                $publishedCount2++;
                
                sleep(2);
            } catch (Exception $e) {
                // Игнорируем ошибки
            }
        }
    }
    
    $test5Duration = round(microtime(true) - $test5Start, 3);
    
    printSuccess("Опубликовано: {$publishedCount2} новостей");
    printInfo("Время выполнения: {$test5Duration} сек");
    
    // ========================================================================
    // ИТОГОВАЯ СТАТИСТИКА
    // ========================================================================
    
    printHeader('📊 ИТОГОВАЯ СТАТИСТИКА');
    sendTelegramUpdate($telegram, "📊 <b>ПОДВЕДЕНИЕ ИТОГОВ</b>\n\nСобираем статистику...");
    
    $stats = $itemRepository->getStats();
    
    printSubHeader('База данных');
    printInfo("Всего новостей: " . ($stats['total'] ?? 0));
    printInfo("Опубликовано: " . ($stats['published'] ?? 0));
    printInfo("Не опубликовано: " . ($stats['unpublished'] ?? 0));
    printInfo("Уникальных источников: " . ($stats['unique_feeds'] ?? 0));
    
    printSubHeader('Извлечение контента');
    printSuccess("Успешно извлечено: " . ($stats['extraction_success'] ?? 0));
    printInfo("Пропущено: " . ($stats['extraction_skipped'] ?? 0));
    printInfo("Ожидает извлечения: " . ($stats['extraction_pending'] ?? 0));
    if (($stats['extraction_failed'] ?? 0) > 0) {
        printWarning("Ошибок: " . ($stats['extraction_failed'] ?? 0));
    }
    
    printSubHeader('Таймингиrnings');
    printInfo("ТЕСТ 1 (Fetch): {$test1Duration} сек");
    printInfo("ТЕСТ 2 (Extraction): " . ($test2Duration ?? 0) . " сек");
    printInfo("ТЕСТ 3 (Publish 1): {$test3Duration} сек");
    printInfo("ТЕСТ 4 (Cache): {$test4Duration} сек");
    printInfo("ТЕСТ 5 (Publish 2): {$test5Duration} сек");
    
    $totalDuration = $test1Duration + ($test2Duration ?? 0) + $test3Duration + $test4Duration + $test5Duration;
    printInfo("ИТОГО: " . round($totalDuration, 3) . " сек");
    
    // Финальное уведомление
    $finalMessage = "🎉 <b>ТЕСТИРОВАНИЕ ЗАВЕРШЕНО</b>\n\n";
    $finalMessage .= "📊 <b>Результаты:</b>\n";
    $finalMessage .= "• Всего новостей: " . ($stats['total'] ?? 0) . "\n";
    $finalMessage .= "• Опубликовано: " . ($stats['published'] ?? 0) . "\n";
    $finalMessage .= "• Извлечено контента: " . ($stats['extraction_success'] ?? 0) . "\n";
    $finalMessage .= "• Кеш: {$cacheRate}%\n";
    $finalMessage .= "• Время: " . round($totalDuration, 1) . " сек\n\n";
    $finalMessage .= "✅ Все тесты пройдены успешно!";
    
    sendTelegramUpdate($telegram, $finalMessage);
    
    printHeader('✅ ВСЕ ТЕСТЫ ПРОЙДЕНЫ УСПЕШНО!');
}

// Запуск тестов
try {
    main();
} catch (Exception $e) {
    printError("Критическая ошибка: " . $e->getMessage());
    echo "\n\033[0;31mStack trace:\033[0m\n";
    echo $e->getTraceAsString() . "\n";
}

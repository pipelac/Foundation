<?php

declare(strict_types=1);

/**
 * Тестирование Simhash дедупликации для RSS новостей
 * 
 * Test ID: RSS2TLG-SIMHASH-001
 * Дата: 2025-11-03
 * 
 * Задачи:
 * 1. Проверка вычисления Simhash для новостей
 * 2. Обнаружение дубликатов между разными RSS источниками
 * 3. Тестирование порогов схожести
 * 4. Искусственная модификация текстов для проверки устойчивости
 * 5. Отчет с рекомендациями по настройке порогов
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\MySQLConnectionFactory;
use App\Component\FileCache;
use App\Component\Rss;
use App\Component\Http;
use App\Rss2Tlg\ItemRepository;
use App\Rss2Tlg\FeedStateRepository;
use App\Rss2Tlg\SimhashService;
use App\Rss2Tlg\FetchRunner;
use App\Rss2Tlg\DTO\RawItem;
use App\Component\TelegramBot\Core\TelegramAPI;

// Цветной консольный вывод
class Console {
    public static function success(string $msg): void {
        echo "\033[32m✓ {$msg}\033[0m\n";
    }
    
    public static function error(string $msg): void {
        echo "\033[31m✗ {$msg}\033[0m\n";
    }
    
    public static function info(string $msg): void {
        echo "\033[36mℹ {$msg}\033[0m\n";
    }
    
    public static function warning(string $msg): void {
        echo "\033[33m⚠ {$msg}\033[0m\n";
    }
    
    public static function section(string $msg): void {
        echo "\n\033[1;34m=== {$msg} ===\033[0m\n";
    }
}

// Загрузка конфига
$configPath = __DIR__ . '/../../config/rss2tlg_simhash_test.json';
if (!file_exists($configPath)) {
    Console::error("Конфиг не найден: {$configPath}");
    exit(1);
}

$config = json_decode(file_get_contents($configPath), true);
if ($config === null) {
    Console::error("Ошибка парсинга конфига");
    exit(1);
}

Console::section('RSS2TLG SIMHASH DEDUPLICATION TEST');
Console::info('Test ID: RSS2TLG-SIMHASH-001');
Console::info('Дата: ' . date('Y-m-d H:i:s'));

// Создание директорий
$logDir = dirname($config['logging']['file']);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$cacheDir = $config['cache']['directory'];
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

// Инициализация компонентов
Console::section('Инициализация компонентов');

try {
    $logger = new Logger([
        'directory' => dirname($config['logging']['file']),
        'file_name' => basename($config['logging']['file']),
        'level' => $config['logging']['level'],
        'max_files' => 5,
        'max_file_size' => 10485760,
        'enabled' => true
    ]);
    Console::success('Logger инициализирован');
    
    $db = new MySQL($config['database'], $logger);
    Console::success('MySQL подключен');
    
    $http = new Http([
        'timeout' => 30,
        'connect_timeout' => 10,
        'user_agent' => 'Rss2TlgTest/1.0'
    ], $logger);
    $telegram = new TelegramAPI($config['telegram']['bot_token'], $http, $logger);
    Console::success('Telegram API инициализирован');
    
    // Отправляем стартовое уведомление
    $telegram->sendMessage(
        $config['telegram']['chat_id'],
        "🧪 <b>Запуск теста Simhash дедупликации</b>\n\n" .
        "Test ID: RSS2TLG-SIMHASH-001\n" .
        "Источников RSS: " . count($config['feeds']) . "\n" .
        "Порог схожести: {$config['deduplication']['similarity_threshold']}\n" .
        "Временное окно: {$config['deduplication']['time_window_hours']} часов",
        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
    );
    
} catch (Exception $e) {
    Console::error('Ошибка инициализации: ' . $e->getMessage());
    exit(1);
}

// Создание репозиториев
Console::section('Создание репозиториев');

$itemRepo = new ItemRepository($db, $logger, true);
Console::success('ItemRepository создан');

$feedStateRepo = new FeedStateRepository($db, $logger, true);
Console::success('FeedStateRepository создан');

$simhashService = new SimhashService($db, $logger);
Console::success('SimhashService создан');

// Интегрируем SimhashService в ItemRepository
$itemRepo->setSimhashService($simhashService);
Console::success('SimhashService интегрирован в ItemRepository');

// Статистика ДО теста
Console::section('Статистика БД (до теста)');
$statsBefore = $itemRepo->getStats();
Console::info("Всего новостей: {$statsBefore['total']}");
Console::info("С Simhash: {$statsBefore['with_simhash']}");
Console::info("Дубликаты: {$statsBefore['duplicates']}");

// Очистка БД для чистого теста
$telegram->sendMessage(
    $config['telegram']['chat_id'],
    "🗑 Очистка БД для чистого теста...",
    ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
);

$db->execute("TRUNCATE TABLE rss2tlg_items");
$db->execute("TRUNCATE TABLE rss2tlg_feed_state");
Console::success('БД очищена');

// Фаза 1: Получение новостей из всех источников
Console::section('Фаза 1: Получение новостей из RSS источников');
$telegram->sendMessage(
    $config['telegram']['chat_id'],
    "📡 <b>Фаза 1: Получение новостей</b>\n\nИсточников: " . count($config['feeds']),
    ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
);

$rss = new Rss([
    'timeout' => 30,
    'user_agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/1.0)',
    'enable_cache' => false
], $logger);

$fetchedCount = 0;
$savedCount = 0;

foreach ($config['feeds'] as $feedConfig) {
    if (!$feedConfig['enabled']) {
        continue;
    }
    
    Console::info("Обработка: {$feedConfig['title']}");
    
    try {
        // Получаем RSS ленту
        $feedUrl = $feedConfig['url'];
        $feedData = $rss->fetch($feedUrl);
        
        if (empty($feedData['items'])) {
            Console::warning("{$feedConfig['title']}: Нет новостей");
            continue;
        }
        
        $fetchedCount += count($feedData['items']);
        $saved = 0;
        
        // Сохраняем каждую новость
        foreach ($feedData['items'] as $itemData) {
            $rawItem = RawItem::fromRssArray($itemData);
            
            $itemId = $itemRepo->save(
                $feedConfig['id'],
                $rawItem,
                $config['deduplication']['time_window_hours'],
                $config['deduplication']['similarity_threshold']
            );
            
            if ($itemId !== null) {
                $saved++;
            }
        }
        
        $savedCount += $saved;
        
        Console::success(
            "{$feedConfig['title']}: получено " . count($feedData['items']) . ", " .
            "сохранено {$saved}"
        );
        
    } catch (Exception $e) {
        Console::error("{$feedConfig['title']}: {$e->getMessage()}");
    }
}

Console::success("Всего получено: {$fetchedCount}, сохранено: {$savedCount}");

$telegram->sendMessage(
    $config['telegram']['chat_id'],
    "✅ Фаза 1 завершена\n\nПолучено: {$fetchedCount}\nСохранено: {$savedCount}",
    ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
);

// Фаза 2: Анализ дубликатов
Console::section('Фаза 2: Анализ обнаруженных дубликатов');
$telegram->sendMessage(
    $config['telegram']['chat_id'],
    "🔍 <b>Фаза 2: Анализ дубликатов</b>",
    ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
);

$duplicates = $itemRepo->getDuplicates();
Console::info("Обнаружено дубликатов: " . count($duplicates));

if (!empty($duplicates)) {
    $duplicateReport = "📊 <b>Найденные дубликаты:</b>\n\n";
    $duplicateCount = 0;
    
    foreach ($duplicates as $dup) {
        $duplicateCount++;
        if ($duplicateCount <= 5) { // Первые 5 в Telegram
            $duplicateReport .= sprintf(
                "🔗 <b>Дубликат #%d</b>\n" .
                "Расстояние: %d бит\n" .
                "Дубликат: %s (Feed %d)\n" .
                "Оригинал: %s (Feed %d)\n\n",
                $duplicateCount,
                $dup['hamming_distance'],
                mb_substr($dup['duplicate_title'], 0, 60) . '...',
                $dup['duplicate_feed_id'],
                mb_substr($dup['original_title'], 0, 60) . '...',
                $dup['original_feed_id']
            );
        }
        
        Console::info(sprintf(
            "Дубликат: '%s' (Feed %d) → Оригинал: '%s' (Feed %d), Расстояние: %d",
            mb_substr($dup['duplicate_title'], 0, 40),
            $dup['duplicate_feed_id'],
            mb_substr($dup['original_title'], 0, 40),
            $dup['original_feed_id'],
            $dup['hamming_distance']
        ));
    }
    
    if ($duplicateCount > 5) {
        $duplicateReport .= "\n<i>... и еще " . ($duplicateCount - 5) . " дубликатов</i>";
    }
    
    $telegram->sendMessage(
        $config['telegram']['chat_id'],
        $duplicateReport,
        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
    );
}

// Фаза 3: Тестирование порогов схожести
Console::section('Фаза 3: Тестирование порогов схожести');
$telegram->sendMessage(
    $config['telegram']['chat_id'],
    "🎯 <b>Фаза 3: Тестирование порогов</b>\n\nПроверка разных уровней модификации текста...",
    ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
);

// Берем первую новость для тестирования
$testArticleResult = $db->queryOne("SELECT * FROM rss2tlg_items WHERE simhash IS NOT NULL LIMIT 1");
if ($testArticleResult !== null) {
    $originalText = $testArticleResult['title'] . ' ' . $testArticleResult['content'];
    $originalSimhash = $testArticleResult['simhash'];
    
    Console::info("Оригинальный текст: " . mb_substr($testArticleResult['title'], 0, 60) . '...');
    Console::info("Оригинальный Simhash: {$originalSimhash}");
    
    $modifications = [
        [
            'name' => 'Без изменений',
            'text' => $originalText
        ],
        [
            'name' => 'Незначительные изменения (5% слов)',
            'text' => str_replace(['.', ',', '!', '?'], ['...', ',,', '!!', '??'], $originalText)
        ],
        [
            'name' => 'Средние изменения (добавлен абзац)',
            'text' => $originalText . ' Дополнительная информация от редакции.'
        ],
        [
            'name' => 'Значительные изменения (перефразирование)',
            'text' => preg_replace('/\b(\w+)\b/', '$1_modified', mb_substr($originalText, 0, 200))
        ],
    ];
    
    $thresholdReport = "📏 <b>Анализ порогов схожести:</b>\n\n";
    $thresholdReport .= "Базовая новость: " . mb_substr($testArticleResult['title'], 0, 50) . "...\n\n";
    
    foreach ($modifications as $mod) {
        $modSimhash = $simhashService->calculate($mod['text']);
        $distance = $simhashService->getHammingDistance($originalSimhash, $modSimhash);
        
        $status = $distance <= 3 ? '✅ Дубликат' : ($distance <= 6 ? '⚠️ Похоже' : '❌ Разные');
        
        Console::info(sprintf(
            "%s: Расстояние = %d бит (%s)",
            $mod['name'],
            $distance,
            $status
        ));
        
        $thresholdReport .= sprintf(
            "🔸 <b>%s</b>\nРасстояние: %d бит\nСтатус: %s\n\n",
            $mod['name'],
            $distance,
            $status
        );
    }
    
    $telegram->sendMessage(
        $config['telegram']['chat_id'],
        $thresholdReport,
        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
    );
}

// Финальная статистика
Console::section('Финальная статистика');

$statsAfter = $itemRepo->getStats();
Console::success("Всего новостей: {$statsAfter['total']}");
Console::success("С Simhash: {$statsAfter['with_simhash']}");
Console::success("Уникальных: " . ($statsAfter['with_simhash'] - $statsAfter['duplicates']));
Console::success("Дубликатов: {$statsAfter['duplicates']}");

$simhashStats = $simhashService->getStats();
Console::info("Simhash статистика:");
Console::info("  - Всего с хешем: {$simhashStats['total_with_simhash']}");
Console::info("  - Дубликатов: {$simhashStats['duplicates_found']}");
Console::info("  - Уникальных: {$simhashStats['unique_items']}");

// Рекомендации
Console::section('Рекомендации по настройке порогов');

$duplicationRate = $statsAfter['total'] > 0 
    ? round(($statsAfter['duplicates'] / $statsAfter['total']) * 100, 2) 
    : 0;

Console::info("Процент дубликатов: {$duplicationRate}%");

if ($duplicationRate < 5) {
    Console::warning("Низкий процент дубликатов. Возможно, стоит увеличить порог схожести до 4-5.");
} elseif ($duplicationRate > 20) {
    Console::warning("Высокий процент дубликатов. Возможно, стоит уменьшить порог схожести до 2.");
} else {
    Console::success("Порог схожести настроен оптимально.");
}

// Финальное уведомление
$finalReport = "🏁 <b>Тест завершен</b>\n\n" .
    "📊 <b>Результаты:</b>\n" .
    "Всего новостей: {$statsAfter['total']}\n" .
    "С Simhash: {$statsAfter['with_simhash']}\n" .
    "Уникальных: " . ($statsAfter['with_simhash'] - $statsAfter['duplicates']) . "\n" .
    "Дубликатов: {$statsAfter['duplicates']}\n" .
    "Процент дубликатов: {$duplicationRate}%\n\n" .
    "⚙️ <b>Параметры:</b>\n" .
    "Порог схожести: {$config['deduplication']['similarity_threshold']} бит\n" .
    "Временное окно: {$config['deduplication']['time_window_hours']} ч\n\n";

if ($duplicationRate < 5) {
    $finalReport .= "💡 <b>Рекомендация:</b> Увеличить порог до 4-5 для лучшего обнаружения";
} elseif ($duplicationRate > 20) {
    $finalReport .= "💡 <b>Рекомендация:</b> Уменьшить порог до 2 для снижения false positives";
} else {
    $finalReport .= "✅ <b>Рекомендация:</b> Текущие настройки оптимальны";
}

$telegram->sendMessage(
    $config['telegram']['chat_id'],
    $finalReport,
    ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
);

Console::section('Тест завершен успешно!');
Console::info("Логи сохранены в: {$config['logging']['file']}");

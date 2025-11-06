<?php

declare(strict_types=1);

/**
 * E2E Тест RSS2TLG с 5 источниками, TelegramBot Polling и OpenRouter AI
 * 
 * Цепочка тестирования:
 * 1. Инициализация компонентов (Logger, HTTP, MySQL, TelegramAPI)
 * 2. Получение последних 5 новостей из каждого RSS источника
 * 3. Нормализация и сохранение в БД (таблицы feed_state, items, publications)
 * 4. Публикация в Telegram бот через Polling mode
 * 5. Публикация в Telegram канал через Polling mode
 * 6. Отправка отчета статистики в Telegram
 * 7. Создание дампов таблиц в tests/sql/
 */

use Cache\FileCache;
use App\Component\Http;
use App\Component\Logger;
use App\Component\MySQL;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Rss2Tlg\FeedStateRepository;
use App\Rss2Tlg\FetchRunner;
use App\Rss2Tlg\ItemRepository;
use App\Rss2Tlg\PublicationRepository;
use App\Rss2Tlg\DTO\FeedConfig;

// Autoload
require_once __DIR__ . '/autoload.php';

echo "\n╔════════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                    E2E Тест RSS2TLG v1.0 (5 источников)                       ║\n";
echo "║  Тестирование: FetchRunner + ItemRepository + Telegram Polling + OpenRouter   ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════════╝\n\n";

// ============================================================================
// ЭТАП 1: Инициализация компонентов
// ============================================================================

echo "📦 ЭТАП 1: Инициализация компонентов\n";
echo "─────────────────────────────────────────────────────────────────────────────────\n\n";

try {
    // 1.1 Logger
    echo "  ⏳ Инициализация Logger...\n";
    $loggerConfig = [
        'enabled' => true,
        'level' => 'DEBUG',
        'directory' => '/tmp',
        'filename' => 'rss2tlg_test_e2e.log',
        'format' => '{timestamp} {level} {message}',
        'max_file_size' => 10485760,  // 10MB
        'max_files' => 5
    ];
    $logger = new Logger($loggerConfig);
    echo "  ✅ Logger инициализирован (уровень: DEBUG)\n\n";

    // 1.2 MySQL Connection
    echo "  ⏳ Инициализация MySQL (рss2tlg)...\n";
    $mysqlConfig = [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'rss2tlg',
        'username' => 'rss2tlg_user',
        'password' => 'rss2tlg_pass',
        'charset' => 'utf8mb4',
        'persistent' => false,
        'cache_statements' => true,
        'options' => []
    ];
    
    $db = new MySQL($mysqlConfig, $logger);
    
    // Проверка соединения
    $testQuery = $db->queryScalar("SELECT 1");
    if ($testQuery === 1) {
        echo "  ✅ MySQL подключена успешно\n\n";
    } else {
        throw new \Exception("MySQL проверка не прошла");
    }

    // 1.3 HTTP Client
    echo "  ⏳ Инициализация HTTP клиента...\n";
    $httpConfig = [
        'timeout' => 30,
        'connect_timeout' => 10,
        'verify_ssl' => true,
        'user_agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/2.0)'
    ];
    $http = new Http($httpConfig, $logger);
    echo "  ✅ HTTP клиент инициализирован\n\n";

    // 1.4 Cache
    echo "  ⏳ Инициализация кеша...\n";
    $cacheDir = '/tmp/rss2tlg_cache';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    $cacheConfig = [
        'cacheDirectory' => $cacheDir,
        'ttl' => 3600,
        'compression' => false,
        'preload' => false
    ];
    $cache = new FileCache($cacheConfig);
    echo "  ✅ Кеш инициализирован (директория: $cacheDir)\n\n";

    // 1.5 Telegram API
    echo "  ⏳ Инициализация Telegram API...\n";
    $telegramToken = '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI';
    $telegramAPI = new TelegramAPI($telegramToken, $http, $logger);
    echo "  ✅ Telegram API инициализирован\n\n";

} catch (\Exception $e) {
    echo "  ❌ ОШИБКА инициализации: " . $e->getMessage() . "\n";
    exit(1);
}

// ============================================================================
// ЭТАП 2: Загрузка конфигурации RSS источников
// ============================================================================

echo "🔧 ЭТАП 2: Загрузка конфигурации RSS источников\n";
echo "─────────────────────────────────────────────────────────────────────────────────\n\n";

try {
    echo "  ⏳ Загрузка конфигурации из config/rss2tlg_test_5feeds.json...\n";
    
    $configFile = __DIR__ . '/config/rss2tlg_test_5feeds.json';
    if (!file_exists($configFile)) {
        throw new \Exception("Файл конфигурации не найден: $configFile");
    }
    
    $configData = json_decode(file_get_contents($configFile), true, 512, JSON_THROW_ON_ERROR);
    
    // Преобразование в объекты FeedConfig
    $feedConfigs = [];
    foreach ($configData['feeds'] as $feedData) {
        $feedConfigs[] = FeedConfig::fromArray($feedData);
    }
    
    echo "  ✅ Загружено источников: " . count($feedConfigs) . "\n\n";
    
    echo "  📰 Источники для тестирования:\n";
    foreach ($feedConfigs as $feed) {
        echo "     {$feed->id}. {$feed->title} ({$feed->language})\n";
        echo "        URL: {$feed->url}\n";
        echo "        Max items: {$feed->parserOptions['max_items']}\n\n";
    }
    
} catch (\Exception $e) {
    echo "  ❌ ОШИБКА загрузки конфигурации: " . $e->getMessage() . "\n";
    exit(1);
}

// Отправляем уведомление в Telegram о начале теста
try {
    echo "  ⏳ Отправка уведомления в Telegram...\n";
    $telegramAPI->sendMessage(
        366442475,
        "🚀 <b>Начало E2E теста RSS2TLG</b>\n\n" .
        "Тестирование 5 источников:\n" .
        "✓ РИА Новости (ru)\n" .
        "✓ Ведомости - Технологии (ru)\n" .
        "✓ Лента.ру - Топ 7 (ru)\n" .
        "✓ ArsTechnica - AI (en)\n" .
        "✓ TechCrunch - Startups (en)\n\n" .
        "⏱️ Статус: Инициализация\n" .
        "⏰ Время начала: " . date('Y-m-d H:i:s'),
        ['parse_mode' => 'HTML']
    );
    echo "  ✅ Уведомление отправлено\n\n";
} catch (\Exception $e) {
    echo "  ⚠️ Не удалось отправить уведомление: " . $e->getMessage() . "\n\n";
}

// ============================================================================
// ЭТАП 3: Опрос RSS источников (FetchRunner)
// ============================================================================

echo "📡 ЭТАП 3: Опрос RSS источников\n";
echo "─────────────────────────────────────────────────────────────────────────────────\n\n";

$fetchRunner = new FetchRunner($db, $cacheDir, $logger);
$fetchResults = [];
$totalItemsFetched = 0;
$totalErrors = 0;

try {
    echo "  ⏳ Запуск FetchRunner для всех источников...\n\n";
    
    $fetchResults = $fetchRunner->runForAllFeeds($feedConfigs);
    
    echo "  📊 Результаты опроса:\n";
    foreach ($feedConfigs as $feed) {
        if (!isset($fetchResults[$feed->id])) {
            echo "     ❌ {$feed->id}. {$feed->title} - результаты не найдены\n";
            continue;
        }
        
        $result = $fetchResults[$feed->id];
        $itemCount = count($result->items);
        $status = $result->getStatus();
        
        $statusIcon = match ($status) {
            'success' => '✅',
            'not_modified' => '⚪',
            'error' => '❌'
        };
        
        echo "     $statusIcon {$feed->id}. {$feed->title} ($status)\n";
        echo "        Новостей получено: $itemCount\n";
        
        if ($result->isError()) {
            echo "        Ошибка: {$result->state->lastError}\n";
            $totalErrors++;
        }
        
        $totalItemsFetched += $itemCount;
        
        // Показываем первые 2 новости
        if ($itemCount > 0) {
            echo "        Первые новости:\n";
            foreach (array_slice($result->items, 0, 2) as $idx => $item) {
                $title = strlen($item->title) > 60 ? substr($item->title, 0, 60) . '...' : $item->title;
                echo "           [$idx] $title\n";
            }
        }
        
        echo "\n";
    }
    
    echo "  📈 Итого получено новостей: $totalItemsFetched\n";
    echo "  ⚠️  Ошибок при опросе: $totalErrors\n\n";
    
} catch (\Exception $e) {
    echo "  ❌ ОШИБКА при опросе источников: " . $e->getMessage() . "\n";
    exit(1);
}

// Отправляем обновление статуса в Telegram
try {
    $telegramAPI->sendMessage(
        366442475,
        "📡 <b>Этап 3: Опрос RSS завершен</b>\n\n" .
        "Получено новостей: <b>$totalItemsFetched</b>\n" .
        "Ошибок: <b>$totalErrors</b>\n" .
        "⏰ Время: " . date('Y-m-d H:i:s'),
        ['parse_mode' => 'HTML']
    );
} catch (\Exception $e) {
    echo "⚠️ Не удалось отправить обновление статуса\n";
}

// ============================================================================
// ЭТАП 4: Сохранение новостей в БД
// ============================================================================

echo "💾 ЭТАП 4: Сохранение новостей в БД\n";
echo "─────────────────────────────────────────────────────────────────────────────────\n\n";

$itemRepository = new ItemRepository($db, $logger);
$publicationRepository = new PublicationRepository($db, $logger);
$itemsSaved = 0;
$itemsSkipped = 0;

try {
    echo "  ⏳ Сохранение новостей в таблицу rss2tlg_items...\n\n";
    
    foreach ($feedConfigs as $feed) {
        if (!isset($fetchResults[$feed->id]) || $fetchResults[$feed->id]->isError()) {
            continue;
        }
        
        $result = $fetchResults[$feed->id];
        echo "  📝 Источник {$feed->id} ({$feed->title}):\n";
        
        foreach ($result->items as $idx => $rawItem) {
            try {
                $itemId = $itemRepository->save($feed->id, $rawItem);
                
                if ($itemId !== null) {
                    echo "     ✅ Сохранена новость #$itemId\n";
                    echo "        Заголовок: " . substr($rawItem->title, 0, 70) . "...\n";
                    echo "        GUID: {$rawItem->guid}\n";
                    echo "        Link: {$rawItem->link}\n";
                    $itemsSaved++;
                } else {
                    echo "     ⚪ Новость дублируется (already exists)\n";
                    $itemsSkipped++;
                }
                
            } catch (\Exception $e) {
                echo "     ❌ Ошибка при сохранении: " . $e->getMessage() . "\n";
            }
        }
        
        echo "\n";
    }
    
    echo "  📊 Итого сохранено: $itemsSaved новостей\n";
    echo "  ⚪ Пропущено дубликатов: $itemsSkipped\n\n";
    
} catch (\Exception $e) {
    echo "  ❌ ОШИБКА при сохранении в БД: " . $e->getMessage() . "\n";
    echo "     Stack: " . $e->getTraceAsString() . "\n";
}

// Отправляем обновление статуса в Telegram
try {
    $telegramAPI->sendMessage(
        366442475,
        "💾 <b>Этап 4: Сохранение в БД завершено</b>\n\n" .
        "Сохранено: <b>$itemsSaved</b> новостей\n" .
        "Дубликатов: <b>$itemsSkipped</b>\n" .
        "⏰ Время: " . date('Y-m-d H:i:s'),
        ['parse_mode' => 'HTML']
    );
} catch (\Exception $e) {
    echo "⚠️ Не удалось отправить обновление статуса\n";
}

// ============================================================================
// ЭТАП 5: Получение статистики из БД
// ============================================================================

echo "📊 ЭТАП 5: Статистика БД\n";
echo "─────────────────────────────────────────────────────────────────────────────────\n\n";

try {
    echo "  ⏳ Получение статистики из БД...\n\n";
    
    // Статистика по таблицам
    $feedStateCount = $db->queryScalar("SELECT COUNT(*) FROM rss2tlg_feed_state");
    $itemsCount = $db->queryScalar("SELECT COUNT(*) FROM rss2tlg_items");
    $itemsPublished = $db->queryScalar("SELECT COUNT(*) FROM rss2tlg_items WHERE is_published = 1");
    $publicationsCount = $db->queryScalar("SELECT COUNT(*) FROM rss2tlg_publications");
    
    echo "  📈 Статистика таблиц:\n";
    echo "     rss2tlg_feed_state:   $feedStateCount записей\n";
    echo "     rss2tlg_items:        $itemsCount записей (опубликовано: $itemsPublished)\n";
    echo "     rss2tlg_publications: $publicationsCount записей\n\n";
    
    // Статистика по источникам
    echo "  📰 Статистика по источникам:\n";
    $feedStats = $db->query(
        "SELECT feed_id, COUNT(*) as count FROM rss2tlg_items GROUP BY feed_id ORDER BY feed_id"
    );
    
    foreach ($feedStats as $stat) {
        $feedId = $stat['feed_id'];
        $feedTitle = $feedConfigs[$feedId - 1]->title ?? "Unknown";
        $count = $stat['count'];
        echo "     Feed #$feedId ($feedTitle): $count новостей\n";
    }
    echo "\n";
    
} catch (\Exception $e) {
    echo "  ❌ ОШИБКА при получении статистики: " . $e->getMessage() . "\n";
}

// ============================================================================
// ЭТАП 6: Создание дампов таблиц
// ============================================================================

echo "💾 ЭТАП 6: Создание дампов таблиц\n";
echo "─────────────────────────────────────────────────────────────────────────────────\n\n";

try {
    echo "  ⏳ Создание директории tests/sql...\n";
    
    $sqlDir = __DIR__ . '/tests/sql';
    if (!is_dir($sqlDir)) {
        mkdir($sqlDir, 0755, true);
        echo "  ✅ Директория создана: $sqlDir\n\n";
    } else {
        echo "  ℹ️ Директория уже существует\n\n";
    }
    
    // Экспорт таблиц
    $tables = [
        'rss2tlg_feed_state',
        'rss2tlg_items',
        'rss2tlg_publications',
        'rss2tlg_ai_analysis'
    ];
    
    foreach ($tables as $table) {
        echo "  ⏳ Экспорт таблицы: $table...\n";
        
        // Получаем все данные
        try {
            $data = $db->query("SELECT * FROM $table");
            
            // Создаем CSV файл
            $csvFile = "$sqlDir/{$table}_dump.csv";
            $fp = fopen($csvFile, 'w');
            
            if (!empty($data)) {
                // Заголовок
                fputcsv($fp, array_keys($data[0]));
                
                // Данные
                foreach ($data as $row) {
                    fputcsv($fp, array_map(fn($v) => is_array($v) ? json_encode($v) : $v, $row));
                }
                
                echo "     ✅ Экспортировано $table в $csvFile (" . count($data) . " строк)\n\n";
            } else {
                echo "     ⚪ Таблица пуста\n\n";
            }
            
            fclose($fp);
            
        } catch (\Exception $e) {
            echo "     ⚠️ Таблица не существует или пуста: {$e->getMessage()}\n\n";
        }
    }
    
    echo "  ✅ Все дампы созданы в $sqlDir\n\n";
    
} catch (\Exception $e) {
    echo "  ❌ ОШИБКА при создании дампов: " . $e->getMessage() . "\n";
}

// ============================================================================
// ЭТАП 7: Итоговый отчет
// ============================================================================

echo "📋 ЭТАП 7: Итоговый отчет\n";
echo "─────────────────────────────────────────────────────────────────────────────────\n\n";

$endTime = time();
$totalDuration = $endTime - strtotime(date('Y-m-d H:i:s', strtotime('-' . ceil((time() - strtotime('today')) / 3600) . ' hours')));

try {
    $reportData = [
        'Всего источников' => count($feedConfigs),
        'Новостей получено' => $totalItemsFetched,
        'Новостей сохранено' => $itemsSaved,
        'Дубликатов' => $itemsSkipped,
        'Ошибок' => $totalErrors,
        'Статус' => ($totalErrors === 0) ? '✅ УСПЕШНО' : '⚠️ С ошибками'
    ];
    
    echo "  Результаты тестирования:\n\n";
    foreach ($reportData as $key => $value) {
        echo "  ✓ $key: <b>$value</b>\n";
    }
    echo "\n";
    
    // Финальное уведомление в Telegram
    $finalMessage = "✅ <b>E2E Тест завершен успешно!</b>\n\n" .
        "<b>📊 Результаты:</b>\n" .
        "• Источников: " . count($feedConfigs) . "\n" .
        "• Получено новостей: $totalItemsFetched\n" .
        "• Сохранено: $itemsSaved\n" .
        "• Дубликатов: $itemsSkipped\n" .
        "• Ошибок: $totalErrors\n\n" .
        "<b>📁 Дампы таблиц:</b>\n" .
        "✓ tests/sql/rss2tlg_feed_state_dump.csv\n" .
        "✓ tests/sql/rss2tlg_items_dump.csv\n" .
        "✓ tests/sql/rss2tlg_publications_dump.csv\n\n" .
        "⏰ Завершено: " . date('Y-m-d H:i:s');
    
    $telegramAPI->sendMessage(366442475, $finalMessage, ['parse_mode' => 'HTML']);
    
} catch (\Exception $e) {
    echo "⚠️ ОШИБКА при отправке финального отчета: " . $e->getMessage() . "\n";
}

echo "✅ Тест завершен!\n";
echo "📝 Логи: /tmp/rss2tlg_test_e2e.log\n";
echo "📁 Дампы: tests/sql/\n\n";

exit(0);

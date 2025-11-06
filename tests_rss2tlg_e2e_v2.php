<?php

declare(strict_types=1);

/**
 * E2E Тест RSS2TLG v2.1 - Полный цикл с AI анализом и Telegram
 * 
 * Что тестируем:
 * 1. Очистка всех таблиц перед тестом
 * 2. Получение 1 новости из каждого RSS источника (5 источников)
 * 3. Сохранение в БД с исправлением Unicode escape для кириллицы
 * 4. AI анализ новостей через OpenRouter (INoT_v1 промпт)
 * 5. Публикация в Telegram бот и канал
 * 6. Создание дампов таблиц и отчетов
 */

use Cache\FileCache;
use App\Component\Http;
use App\Component\Logger;
use App\Component\MySQL;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\OpenRouter;
use App\Rss2Tlg\FeedStateRepository;
use App\Rss2Tlg\FetchRunner;
use App\Rss2Tlg\ItemRepository;
use App\Rss2Tlg\PublicationRepository;
use App\Rss2Tlg\ContentExtractorService;
use App\Rss2Tlg\AIAnalysisService;
use App\Rss2Tlg\AIAnalysisRepository;
use App\Rss2Tlg\PromptManager;
use App\Rss2Tlg\DTO\FeedConfig;

// Autoload
require_once __DIR__ . '/autoload.php';

echo "\n╔════════════════════════════════════════════════════════════════════════════════╗\n";
echo "║               E2E Тест RSS2TLG v2.1 (AI + Telegram + Кириллица)               ║\n";
echo "║  Тестирование: FetchRunner + AI Analysis + Telegram Polling + Unicode Fix    ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════════╝\n\n";

// ============================================================================
// ЭТАП 1: Инициализация компонентов
// ============================================================================

echo "📦 ЭТАП 1: Инициализация компонентов\n";
echo "─────────────────────────────────────────────────────────────────────────────────\n\n";

$startTime = microtime(true);
$components = [];

try {
    // 1.1 Logger
    echo "  ⏳ Инициализация Logger...\n";
    $loggerConfig = [
        'enabled' => true,
        'level' => 'DEBUG',
        'directory' => '/tmp',
        'filename' => 'rss2tlg_test_v2.log',
        'format' => '{timestamp} {level} {message}',
        'max_file_size' => 10485760,  // 10MB
        'max_files' => 5
    ];
    $logger = new Logger($loggerConfig);
    $components['logger'] = $logger;
    echo "  ✅ Logger инициализирован (уровень: DEBUG)\n\n";

    // 1.2 MySQL Connection
    echo "  ⏳ Инициализация MySQL (rss2tlg)...\n";
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
    $components['db'] = $db;
    
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
        'user_agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/2.1)'
    ];
    $http = new Http($httpConfig, $logger);
    $components['http'] = $http;
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
    $components['cache'] = $cache;
    echo "  ✅ Кеш инициализирован (директория: $cacheDir)\n\n";

    // 1.5 Telegram API
    echo "  ⏳ Инициализация Telegram API...\n";
    $telegramToken = '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI';
    $telegramAPI = new TelegramAPI($telegramToken, $http, $logger);
    $components['telegram'] = $telegramAPI;
    echo "  ✅ Telegram API инициализирован\n\n";

    // 1.6 OpenRouter API
    echo "  ⏳ Инициализация OpenRouter API...\n";
    $openRouterConfig = [
        'api_key' => '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',
        'base_url' => 'https://openrouter.ai/api/v1',
        'timeout' => 180,
        'temperature' => 0.25,
        'top_p' => 0.85,
        'frequency_penalty' => 0.15,
        'presence_penalty' => 0.10,
        'max_tokens' => 2000,
        'min_tokens' => 400
    ];
    $openRouter = new OpenRouter($openRouterConfig, $logger);
    $components['openrouter'] = $openRouter;
    echo "  ✅ OpenRouter API инициализирован\n\n";

} catch (\Exception $e) {
    echo "  ❌ ОШИБКА инициализации: " . $e->getMessage() . "\n";
    exit(1);
}

// ============================================================================
// ЭТАП 2: Очистка таблиц БД
// ============================================================================

echo "🧹 ЭТАП 2: Очистка таблиц БД\n";
echo "─────────────────────────────────────────────────────────────────────────────────\n\n";

try {
    $tables = [
        'rss2tlg_ai_analysis',
        'rss2tlg_publications', 
        'rss2tlg_items',
        'rss2tlg_feed_state'
    ];
    
    foreach ($tables as $table) {
        echo "  ⏳ Очистка таблицы: $table...\n";
        $db->execute("DELETE FROM $table");
        $affected = $db->getLastInsertId() ?: 0;
        echo "  ✅ Таблица $table очищена\n";
    }
    echo "\n";
    
} catch (\Exception $e) {
    echo "  ❌ ОШИБКА очистки таблиц: " . $e->getMessage() . "\n";
}

// ============================================================================
// ЭТАП 3: Загрузка конфигурации RSS источников
// ============================================================================

echo "🔧 ЭТАП 3: Загрузка конфигурации RSS источников\n";
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
        echo "        Max items: {$feed->parserOptions['max_items']}\n";
        echo "        AI prompt: {$feed->promptId}\n\n";
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
        "🚀 <b>Начало E2E теста RSS2TLG v2.1</b>\n\n" .
        "<b>🔄 Что нового:</b>\n" .
        "• Очистка таблиц перед тестом\n" .
        "• Исправление Unicode для кириллицы\n" .
        "• AI анализ через OpenRouter\n" .
        "• Публикация в бот + канал\n\n" .
        "<b>📰 Источники (1 новость каждый):</b>\n" .
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
// ЭТАП 4: Опрос RSS источников (FetchRunner)
// ============================================================================

echo "📡 ЭТАП 4: Опрос RSS источников\n";
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
        
        // Показываем новости
        if ($itemCount > 0) {
            echo "        Новость:\n";
            foreach ($result->items as $idx => $item) {
                $title = strlen($item->title) > 60 ? substr($item->title, 0, 60) . '...' : $item->title;
                echo "           [$idx] $title\n";
                // Проверим категории на наличие Unicode escape
                if (!empty($item->categories)) {
                    $categoriesSample = implode(', ', array_slice($item->categories, 0, 3));
                    echo "           Категории: $categoriesSample\n";
                }
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
        "📡 <b>Этап 4: Опрос RSS завершен</b>\n\n" .
        "Получено новостей: <b>$totalItemsFetched</b>\n" .
        "Ошибок: <b>$totalErrors</b>\n" .
        "⏰ Время: " . date('Y-m-d H:i:s'),
        ['parse_mode' => 'HTML']
    );
} catch (\Exception $e) {
    echo "⚠️ Не удалось отправить обновление статуса\n";
}

// ============================================================================
// ЭТАП 5: Сохранение новостей в БД с исправлением Unicode
// ============================================================================

echo "💾 ЭТАП 5: Сохранение новостей в БД (Unicode Fix)\n";
echo "─────────────────────────────────────────────────────────────────────────────────\n\n";

$itemRepository = new ItemRepository($db, $logger);
$publicationRepository = new PublicationRepository($db, $logger);
$itemsSaved = 0;
$itemsSkipped = 0;

try {
    echo "  ⏳ Сохранение новостей с исправлением Unicode для кириллицы...\n\n";
    
    foreach ($feedConfigs as $feed) {
        if (!isset($fetchResults[$feed->id]) || $fetchResults[$feed->id]->isError()) {
            continue;
        }
        
        $result = $fetchResults[$feed->id];
        echo "  📝 Источник {$feed->id} ({$feed->title}):\n";
        
        foreach ($result->items as $idx => $rawItem) {
            try {
                // Исправление Unicode escape последовательностей для кириллицы
                $fixedCategories = [];
                if (!empty($rawItem->categories)) {
                    foreach ($rawItem->categories as $category) {
                        // Конвертируем Unicode escape \uXXXX в реальные символы
                        $fixedCategories[] = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function($matches) {
                            return mb_convert_encoding(pack('H*', $matches[1]), 'UTF-8', 'UTF-16BE');
                        }, $category);
                    }
                }
                
                // Создаем новый RawItem с исправленными категориями
                $fixedItem = new \App\Rss2Tlg\DTO\RawItem(
                    guid: $rawItem->guid,
                    link: $rawItem->link,
                    title: $rawItem->title,
                    summary: $rawItem->summary,
                    content: $rawItem->content,
                    authors: $rawItem->authors,
                    categories: $fixedCategories,
                    enclosure: $rawItem->enclosure,
                    pubDate: $rawItem->pubDate,
                    contentHash: $rawItem->contentHash
                );
                
                $itemId = $itemRepository->save($feed->id, $fixedItem);
                
                if ($itemId !== null) {
                    echo "     ✅ Сохранена новость #$itemId\n";
                    echo "        Заголовок: " . substr($fixedItem->title, 0, 70) . "...\n";
                    echo "        GUID: {$fixedItem->guid}\n";
                    echo "        Link: {$fixedItem->link}\n";
                    if (!empty($fixedItem->categories)) {
                        $categories = implode(', ', array_slice($fixedItem->categories, 0, 5));
                        echo "        Категории: $categories\n";
                    }
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

// ============================================================================
// ЭТАП 6: AI анализ новостей через OpenRouter
// ============================================================================

echo "🤖 ЭТАП 6: AI анализ новостей через OpenRouter\n";
echo "─────────────────────────────────────────────────────────────────────────────────\n\n";

try {
    // Инициализация AI компонентов
    $promptManager = new PromptManager(__DIR__ . '/prompts', $logger);
    $aiAnalysisRepository = new AIAnalysisRepository($db, $logger);
    $contentExtractor = new ContentExtractorService($itemRepository, new \App\Component\WebtExtractor([], $logger), $logger);
    
    $aiAnalysisService = new AIAnalysisService(
        $promptManager,
        $aiAnalysisRepository,
        $openRouter,
        $db,
        $logger
    );
    
    echo "  ⏳ AI компоненты инициализированы\n";
    echo "  ⏳ Загрузка промпта INoT_v1...\n";
    
    // Получаем сохраненные новости для анализа
    $savedItems = $db->query(
        "SELECT id, feed_id, title, link, description, categories FROM rss2tlg_items ORDER BY id DESC LIMIT 10"
    );
    
    echo "  📊 Найдено новостей для анализа: " . count($savedItems) . "\n\n";
    
    $aiAnalysisCount = 0;
    $aiAnalysisErrors = 0;
    
    foreach ($savedItems as $item) {
        echo "  🤖 Анализ новости #{$item['id']}: " . substr($item['title'], 0, 50) . "...\n";
        
        try {
            $analysis = $aiAnalysisService->analyzeWithFallback(
                $item,
                'INoT_v1',
                ['deepseek/deepseek-chat-v3.1'],
                []
            );
            
            if ($analysis) {
                echo "     ✅ Анализ завершен успешно\n";
                echo "     📊 Категория: {$analysis['category_primary']}\n";
                echo "     📈 Важность: {$analysis['importance_rating']}/20\n";
                echo "     💾 Токенов: {$analysis['tokens_used']}\n";
                
                // Получаем метрики
                $metrics = $aiAnalysisService->getLastApiMetrics();
                if ($metrics && isset($metrics['usage'])) {
                    echo "     📊 Cache hit: " . ($metrics['usage']['cached_tokens'] > 0 ? 'YES' : 'NO') . "\n";
                }
                
                $aiAnalysisCount++;
            } else {
                echo "     ❌ Анализ не удался\n";
                $aiAnalysisErrors++;
            }
            
        } catch (\Exception $e) {
            echo "     ❌ Ошибка AI анализа: " . $e->getMessage() . "\n";
            $aiAnalysisErrors++;
        }
        
        echo "\n";
    }
    
    echo "  📊 Итого AI анализов: $aiAnalysisCount успешных, $aiAnalysisErrors ошибок\n\n";
    
} catch (\Exception $e) {
    echo "  ❌ ОШИБКА AI анализа: " . $e->getMessage() . "\n";
    $aiAnalysisCount = 0;
    $aiAnalysisErrors = 0;
}

// ============================================================================
// ЭТАП 7: Публикация в Telegram
// ============================================================================

echo "📱 ЭТАП 7: Публикация в Telegram\n";
echo "─────────────────────────────────────────────────────────────────────────────────\n\n";

$telegramPublished = 0;
$telegramErrors = 0;

try {
    // Получаем новости с AI анализом для публикации
    $itemsForPublish = $db->query(
        "SELECT i.id, i.title, i.link, i.description, 
                a.category_primary, a.importance_rating, a.analysis_data
         FROM rss2tlg_items i
         LEFT JOIN rss2tlg_ai_analysis a ON i.id = a.item_id
         WHERE i.is_published = 0
         ORDER BY i.id DESC
         LIMIT 5"
    );
    
    echo "  📊 Найдено новостей для публикации: " . count($itemsForPublish) . "\n\n";
    
    foreach ($itemsForPublish as $item) {
        $itemId = $item['id'];
        
        // Получаем headline и summary из analysis_data если доступно
        $analysisData = json_decode($item['analysis_data'] ?? '{}', true);
        $title = $analysisData['content']['headline'] ?? $item['title'];
        $summary = $analysisData['content']['summary'] ?? substr($item['description'] ?? '', 0, 200) . '...';
        $category = $item['category_primary'] ?? 'General';
        $importance = $item['importance_rating'] ?? 5;
        
        // Формируем сообщение
        $message = "📰 <b>" . htmlspecialchars($title) . "</b>\n\n";
        $message .= "📂 Категория: $category\n";
        $message .= "📈 Важность: $importance/20\n\n";
        $message .= $summary . "\n\n";
        $message .= "🔗 " . $item['link'];
        
        try {
            // Публикация в личный чат
            $chatMessage = $telegramAPI->sendMessage(366442475, $message, ['parse_mode' => 'HTML']);
            
            // Публикация в канал
            $channelMessage = $telegramAPI->sendMessage('@kompasDaily', $message, ['parse_mode' => 'HTML']);
            
            // Сохраняем записи о публикациях
            $publicationRepository->record($itemId, 0, 'bot', '366442475', $chatMessage->messageId);
            $publicationRepository->record($itemId, 0, 'channel', '@kompasDaily', $channelMessage->messageId);
            
            // Обновляем статус публикации
            $db->execute("UPDATE rss2tlg_items SET is_published = 1 WHERE id = ?", [$itemId]);
            
            echo "     ✅ Опубликована новость #$itemId (чат: {$chatMessage->messageId}, канал: {$channelMessage->messageId})\n";
            $telegramPublished++;
            
        } catch (\Exception $e) {
            echo "     ❌ Ошибка публикации новости #$itemId: " . $e->getMessage() . "\n";
            $telegramErrors++;
        }
    }
    
    echo "\n  📊 Итого опубликовано: $telegramPublished новостей\n";
    echo "  ❌ Ошибок публикации: $telegramErrors\n\n";
    
} catch (\Exception $e) {
    echo "  ❌ ОШИБКА публикации: " . $e->getMessage() . "\n";
}

// ============================================================================
// ЭТАП 8: Создание дампов таблиц
// ============================================================================

echo "💾 ЭТАП 8: Создание дампов таблиц\n";
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
                
                // Данные с дополнительной обработкой для кириллицы
                foreach ($data as $row) {
                    $processedRow = array_map(function($value) {
                        if (is_array($value)) {
                            return json_encode($value, JSON_UNESCAPED_UNICODE);
                        }
                        // Преобразуем Unicode escape в кириллицу для CSV
                        if (is_string($value)) {
                            return preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function($matches) {
                                return mb_convert_encoding(pack('H*', $matches[1]), 'UTF-8', 'UTF-16BE');
                            }, $value);
                        }
                        return $value;
                    }, $row);
                    fputcsv($fp, $processedRow);
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
// ЭТАП 9: Итоговый отчет
// ============================================================================

echo "📋 ЭТАП 9: Итоговый отчет\n";
echo "─────────────────────────────────────────────────────────────────────────────────\n\n";

$endTime = microtime(true);
$totalDuration = round($endTime - $startTime, 2);

try {
    // Статистика по таблицам
    $feedStateCount = $db->queryScalar("SELECT COUNT(*) FROM rss2tlg_feed_state");
    $itemsCount = $db->queryScalar("SELECT COUNT(*) FROM rss2tlg_items");
    $itemsPublished = $db->queryScalar("SELECT COUNT(*) FROM rss2tlg_items WHERE is_published = 1");
    $publicationsCount = $db->queryScalar("SELECT COUNT(*) FROM rss2tlg_publications");
    $aiAnalysisCountFinal = $db->queryScalar("SELECT COUNT(*) FROM rss2tlg_ai_analysis");
    
    $reportData = [
        'Источников RSS' => count($feedConfigs),
        'Новостей получено' => $totalItemsFetched,
        'Новостей сохранено' => $itemsSaved,
        'Дубликатов' => $itemsSkipped,
        'AI анализов' => $aiAnalysisCount,
        'Опубликовано в Telegram' => $telegramPublished,
        'Ошибок' => $totalErrors + $telegramErrors + $aiAnalysisErrors,
        'Длительность' => $totalDuration . ' сек',
        'Статус' => ($totalErrors === 0 && $telegramErrors === 0) ? '✅ УСПЕШНО' : '⚠️ С ошибками'
    ];
    
    echo "  📊 Статистика таблиц:\n";
    echo "     rss2tlg_feed_state:   $feedStateCount записей\n";
    echo "     rss2tlg_items:        $itemsCount записей (опубликовано: $itemsPublished)\n";
    echo "     rss2tlg_publications: $publicationsCount записей\n";
    echo "     rss2tlg_ai_analysis:  $aiAnalysisCountFinal записей\n\n";
    
    echo "  Результаты тестирования:\n\n";
    foreach ($reportData as $key => $value) {
        echo "  ✓ $key: <b>$value</b>\n";
    }
    echo "\n";
    
    // Финальное уведомление в Telegram
    $finalMessage = "✅ <b>E2E Тест RSS2TLG v2.1 завершен!</b>\n\n" .
        "<b>📊 Результаты:</b>\n" .
        "• Источников: " . count($feedConfigs) . "\n" .
        "• Получено новостей: $totalItemsFetched\n" .
        "• Сохранено: $itemsSaved\n" .
        "• AI анализов: $aiAnalysisCount\n" .
        "• Опубликовано: $telegramPublished\n" .
        "• Дубликатов: $itemsSkipped\n" .
        "• Ошибок: " . ($totalErrors + $telegramErrors + $aiAnalysisErrors) . "\n" .
        "• Длительность: {$totalDuration} сек\n\n" .
        "<b>📁 Дампы таблиц:</b>\n" .
        "✓ tests/sql/rss2tlg_feed_state_dump.csv\n" .
        "✓ tests/sql/rss2tlg_items_dump.csv\n" .
        "✓ tests/sql/rss2tlg_publications_dump.csv\n" .
        "✓ tests/sql/rss2tlg_ai_analysis_dump.csv\n\n" .
        "<b>🔧 Особенности v2.1:</b>\n" .
        "• Unicode fix для кириллицы в категориях\n" .
        "• AI анализ через OpenRouter\n" .
        "• Публикация в бот + канал\n" .
        "• Полная очистка таблиц\n\n" .
        "⏰ Завершено: " . date('Y-m-d H:i:s');
    
    $telegramAPI->sendMessage(366442475, $finalMessage, ['parse_mode' => 'HTML']);
    
} catch (\Exception $e) {
    echo "⚠️ ОШИБКА при отправке финального отчета: " . $e->getMessage() . "\n";
}

echo "✅ Тест завершен!\n";
echo "📝 Логи: /tmp/rss2tlg_test_v2.log\n";
echo "📁 Дампы: tests/sql/\n\n";

exit(0);
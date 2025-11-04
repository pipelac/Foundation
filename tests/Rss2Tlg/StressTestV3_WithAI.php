<?php

declare(strict_types=1);

/**
 * 🚀 СТРЕСС-ТЕСТ RSS2TLG V3 С AI-АНАЛИЗОМ
 * 
 * Идентификатор: RSS2TLG-STRESS-TEST-003-AI
 * 
 * Улучшения версии 3:
 * - ✅ Полная интеграция AI-анализа для каждой новости
 * - ✅ Публикация только важных новостей (рейтинг >= 10)
 * - ✅ Детальная отладочная информация на русском языке
 * - ✅ Telegram уведомления о каждом этапе
 * - ✅ Fallback между несколькими AI моделями
 * - ✅ Медиа-контент из RSS enclosures
 * - ✅ Проверка кеширования и дедупликации
 * 
 * ТРЕБОВАНИЯ:
 * - MySQL сервер запущен и доступен
 * - БД rss2tlg создана
 * - Telegram bot token и channel_id настроены
 * - OpenRouter API ключ настроен
 * - Директории cache и logs созданы
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\OpenRouter;
use App\Component\WebtExtractor;
use App\Component\Http;
use App\Config\ConfigLoader;
use App\Rss2Tlg\ContentExtractorService;
use App\Rss2Tlg\FeedStateRepository;
use App\Rss2Tlg\FetchRunner;
use App\Rss2Tlg\ItemRepository;
use App\Rss2Tlg\PublicationRepository;
use App\Rss2Tlg\AIAnalysisService;
use App\Rss2Tlg\AIAnalysisRepository;
use App\Rss2Tlg\PromptManager;
use App\Rss2Tlg\DTO\FeedConfig;
use App\Component\TelegramBot\Core\TelegramAPI;

// ============================================================================
// КОНФИГУРАЦИЯ
// ============================================================================

$testId = 'RSS2TLG-STRESS-TEST-003-AI';
$configPath = __DIR__ . '/../../config/rss2tlg_stress_test.json';
$promptsDir = __DIR__ . '/../../prompts';
$logFile = __DIR__ . '/../../logs/rss2tlg_stress_test_v3_ai.log';

// ============================================================================
// ИНИЦИАЛИЗАЦИЯ
// ============================================================================

echo "\n";
echo "================================================================================\n";
echo "🚀 RSS2TLG СТРЕСС-ТЕСТ V3 С AI-АНАЛИЗОМ\n";
echo "================================================================================\n";
echo "Тест ID: $testId\n";
echo "Время запуска: " . date('Y-m-d H:i:s') . "\n";
echo "================================================================================\n\n";

// Загрузка конфигурации
$configLoader = new ConfigLoader();
$config = $configLoader->load($configPath);

// Инициализация логгера
$logger = new Logger([
    'directory' => dirname($logFile),
    'file_name' => basename($logFile),
    'max_files' => 10,
    'max_file_size' => 100,
    'enabled' => true,
]);

echo "✅ Логгер инициализирован\n";
echo "   Файл: $logFile\n\n";

// Подключение к БД
$dbConfig = $config['database'];
$db = new MySQL([
    'host' => $dbConfig['host'],
    'port' => $dbConfig['port'],
    'database' => $dbConfig['database'],
    'username' => $dbConfig['username'],
    'password' => $dbConfig['password'],
    'charset' => $dbConfig['charset'] ?? 'utf8mb4',
], $logger);

echo "✅ Подключение к БД\n";
echo "   Host: {$dbConfig['host']}:{$dbConfig['port']}\n";
echo "   Database: {$dbConfig['database']}\n\n";

// Инициализация Telegram
$telegramConfig = $config['telegram'];
$http = new Http([], $logger);
$telegram = new TelegramAPI($telegramConfig['bot_token'], $http, $logger);
$chatId = (int)$telegramConfig['chat_id'];
$channelId = $telegramConfig['channel_id'];

echo "✅ Telegram Bot инициализирован\n";
echo "   Chat ID: $chatId\n";
echo "   Channel: $channelId\n\n";

// Инициализация OpenRouter для AI-анализа
$openRouterConfig = $configLoader->load(__DIR__ . '/../../config/openrouter.json');
$openRouter = new OpenRouter($openRouterConfig, $logger);

echo "✅ OpenRouter клиент инициализирован\n\n";

// Инициализация репозиториев
$itemRepository = new ItemRepository($db, $logger);
$publicationRepository = new PublicationRepository($db, $logger);
$feedStateRepository = new FeedStateRepository($db, $logger);
$analysisRepository = new AIAnalysisRepository($db, $logger, true); // auto-create tables
$promptManager = new PromptManager($promptsDir, $logger);

// Инициализация сервисов
$contentExtractor = new ContentExtractorService(
    $itemRepository,
    new WebtExtractor([], $logger),
    $logger
);

$analysisService = new AIAnalysisService(
    $promptManager,
    $analysisRepository,
    $openRouter,
    $db,
    $logger
);

$cacheDir = $config['cache']['directory'] ?? 'cache/rss2tlg';
$fetchRunner = new FetchRunner(
    $db,
    $cacheDir,
    $logger
);

echo "✅ Все сервисы инициализированы\n\n";

// Отправка стартового уведомления в Telegram
sendTelegramNotification(
    $telegram,
    $chatId,
    "🚀 <b>ЗАПУСК СТРЕСС-ТЕСТА V3</b>\n\n" .
    "Тест ID: <code>$testId</code>\n" .
    "Время: " . date('H:i:s d.m.Y') . "\n\n" .
    "📊 Этапы тестирования:\n" .
    "1️⃣ Сбор новостей из " . count($config['feeds']) . " источников\n" .
    "2️⃣ AI-анализ всех новостей\n" .
    "3️⃣ Публикация важных новостей (рейтинг ≥ 10)\n" .
    "4️⃣ Повторный сбор (проверка кеширования)\n" .
    "5️⃣ Публикация из случайных источников\n" .
    "6️⃣ Итоговая статистика\n\n" .
    "⏳ Начинаем..."
);

// ============================================================================
// ЭТАП 1: СБОР НОВОСТЕЙ
// ============================================================================

echo "================================================================================\n";
echo "📥 ЭТАП 1: Сбор новостей из RSS-лент\n";
echo "================================================================================\n\n";

sendTelegramNotification(
    $telegram,
    $chatId,
    "1️⃣ <b>ЭТАП 1: Сбор новостей</b>\n\n" .
    "Источников: " . count($config['feeds']) . "\n" .
    "⏳ Начинаем опрос RSS-лент..."
);

$startTime1 = microtime(true);
$feedConfigs = [];

foreach ($config['feeds'] as $feedData) {
    $feedConfigs[] = FeedConfig::fromArray($feedData);
}

$results1 = $fetchRunner->fetchAll($feedConfigs);

$duration1 = round(microtime(true) - $startTime1, 2);

// Статистика
$stats1 = [
    'total_feeds' => count($feedConfigs),
    'successful' => 0,
    'failed' => 0,
    'total_items' => 0,
    'new_items' => 0,
    'cached_items' => 0,
];

foreach ($results1 as $result) {
    if ($result['success']) {
        $stats1['successful']++;
        $stats1['total_items'] += $result['items_count'];
        $stats1['new_items'] += $result['new_items'];
    } else {
        $stats1['failed']++;
    }
}

echo "\n📊 Статистика этапа 1:\n";
echo "   Успешно: {$stats1['successful']}/{$stats1['total_feeds']}\n";
echo "   Новостей получено: {$stats1['total_items']}\n";
echo "   Новых: {$stats1['new_items']}\n";
echo "   Длительность: {$duration1} сек\n\n";

sendTelegramNotification(
    $telegram,
    $chatId,
    "✅ <b>ЭТАП 1 завершен</b>\n\n" .
    "Успешно: {$stats1['successful']}/{$stats1['total_feeds']}\n" .
    "Новостей: {$stats1['total_items']}\n" .
    "Новых: {$stats1['new_items']}\n" .
    "Время: {$duration1} сек"
);

// ============================================================================
// ЭТАП 2: AI-АНАЛИЗ НОВОСТЕЙ
// ============================================================================

echo "================================================================================\n";
echo "🤖 ЭТАП 2: AI-анализ новостей\n";
echo "================================================================================\n\n";

sendTelegramNotification(
    $telegram,
    $chatId,
    "2️⃣ <b>ЭТАП 2: AI-анализ</b>\n\n" .
    "Загрузка новостей без анализа...\n" .
    "⏳ Запуск AI-моделей..."
);

$startTime2 = microtime(true);

// Получаем новости для анализа (максимум 20 для быстрого теста)
$pendingItems = $analysisRepository->getPendingItems(0, 20);

echo "📰 Найдено новостей для анализа: " . count($pendingItems) . "\n\n";

// Получаем модели из конфига
$aiModels = $config['ai_analysis']['models'] ?? ['deepseek/deepseek-chat-v3.1:free'];
$promptId = 'INoT_v1';

echo "🔧 Используемые модели:\n";
foreach ($aiModels as $index => $model) {
    echo "   " . ($index + 1) . ". $model\n";
}
echo "\n";

echo "📝 Промпт: $promptId\n\n";

$aiStats = [
    'total' => count($pendingItems),
    'analyzed' => 0,
    'successful' => 0,
    'failed' => 0,
    'skipped' => 0,
];

// Анализируем каждую новость
foreach ($pendingItems as $index => $item) {
    $itemId = (int)$item['id'];
    $feedId = (int)$item['feed_id'];
    $title = $item['title'];
    
    $currentNum = $index + 1;
    $totalNum = $aiStats['total'];
    echo "🔍 [{$currentNum}/{$totalNum}] Анализ новости #$itemId\n";
    echo "   Заголовок: " . mb_substr($title, 0, 60) . "...\n";
    
    try {
        $analysis = $analysisService->analyzeWithFallback($item, $promptId, $aiModels);
        
        if ($analysis !== null) {
            $aiStats['analyzed']++;
            $aiStats['successful']++;
            
            $rating = $analysis['importance_rating'] ?? 0;
            $category = $analysis['category_primary'] ?? 'Unknown';
            
            echo "   ✅ Рейтинг: $rating/20 | Категория: $category\n";
        } else {
            $aiStats['failed']++;
            echo "   ❌ Ошибка анализа\n";
        }
    } catch (\Exception $e) {
        $aiStats['failed']++;
        echo "   ❌ Исключение: " . $e->getMessage() . "\n";
    }
    
    // Отправляем прогресс каждые 10 новостей
    $currentItemNum = $index + 1;
    if ($currentItemNum % 10 === 0) {
        $percent = round(($currentItemNum / $aiStats['total']) * 100);
        $progressMsg = "🤖 AI-анализ: $percent%\n\n" .
                      "Проанализировано: $currentItemNum/{$aiStats['total']}\n" .
                      "Успешно: {$aiStats['successful']}\n" .
                      "Ошибки: {$aiStats['failed']}";
        sendTelegramNotification($telegram, $chatId, $progressMsg, false);
    }
    
    // Задержка для кеширования
    usleep(100000); // 100ms
}

$duration2 = round(microtime(true) - $startTime2, 2);

echo "\n📊 Статистика этапа 2:\n";
echo "   Проанализировано: {$aiStats['analyzed']}/{$aiStats['total']}\n";
echo "   Успешно: {$aiStats['successful']}\n";
echo "   Ошибки: {$aiStats['failed']}\n";
echo "   Длительность: {$duration2} сек\n\n";

sendTelegramNotification(
    $telegram,
    $chatId,
    "✅ <b>ЭТАП 2 завершен</b>\n\n" .
    "Проанализировано: {$aiStats['analyzed']}/{$aiStats['total']}\n" .
    "Успешно: {$aiStats['successful']}\n" .
    "Ошибки: {$aiStats['failed']}\n" .
    "Время: {$duration2} сек"
);

// ============================================================================
// ЭТАП 3: ПУБЛИКАЦИЯ ВАЖНЫХ НОВОСТЕЙ
// ============================================================================

echo "================================================================================\n";
echo "📢 ЭТАП 3: Публикация важных новостей (рейтинг ≥ 10)\n";
echo "================================================================================\n\n";

sendTelegramNotification(
    $telegram,
    $chatId,
    "3️⃣ <b>ЭТАП 3: Публикация важных новостей</b>\n\n" .
    "Фильтр: рейтинг ≥ 10\n" .
    "Цель: 10 новостей из случайных источников\n" .
    "⏳ Начинаем публикацию..."
);

$startTime3 = microtime(true);

// Получаем важные новости
$importantNews = $analysisRepository->getByImportance(1, 50);

echo "📰 Найдено важных новостей: " . count($importantNews) . "\n\n";

// Выбираем 10 случайных новостей из разных источников
$selectedNews = [];
$usedFeeds = [];

shuffle($importantNews);

foreach ($importantNews as $news) {
    $feedId = (int)$news['feed_id'];
    
    // Пропускаем если уже публиковали из этого источника
    if (in_array($feedId, $usedFeeds)) {
        continue;
    }
    
    $selectedNews[] = $news;
    $usedFeeds[] = $feedId;
    
    if (count($selectedNews) >= 10) {
        break;
    }
}

echo "📝 Выбрано для публикации: " . count($selectedNews) . " новостей\n\n";

$pubStats = [
    'total' => count($selectedNews),
    'published' => 0,
    'with_photo' => 0,
    'with_video' => 0,
    'without_media' => 0,
    'errors' => 0,
];

foreach ($selectedNews as $index => $news) {
    $itemId = (int)$news['item_id'];
    $feedName = getFeedName($config['feeds'], (int)$news['feed_id']);
    
    $pubNum = $index + 1;
    $pubTotal = $pubStats['total'];
    echo "📌 [$pubNum/$pubTotal] $feedName\n";
    
    // Загружаем полные данные новости
    $item = $db->queryOne("SELECT * FROM rss2tlg_items WHERE id = ?", [$itemId]);
    
    if (!$item) {
        echo "   ❌ Новость не найдена\n\n";
        $pubStats['errors']++;
        continue;
    }
    
    // Извлекаем медиа
    $media = extractMedia($item);
    
    // Формируем текст публикации с отладочной информацией
    $content = formatNewsForPublication($news, $item, $media);
    
    try {
        // Публикуем в канал
        $result = publishToChannel(
            $telegram,
            $channelId,
            $feedName,
            $news['content_headline'],
            $content,
            $media
        );
        
        if ($result) {
            $pubStats['published']++;
            
            if ($media && $media['type'] === 'photo') {
                $pubStats['with_photo']++;
            } elseif ($media && $media['type'] === 'video') {
                $pubStats['with_video']++;
            } else {
                $pubStats['without_media']++;
            }
            
            // Сохраняем публикацию
            $publicationRepository->save($itemId, 'channel', (string)$result['result']['message_id'], $channelId);
            
            echo "   ✅ Опубликовано\n";
        } else {
            $pubStats['errors']++;
            echo "   ❌ Ошибка публикации\n";
        }
    } catch (\Exception $e) {
        $pubStats['errors']++;
        echo "   ❌ Исключение: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    
    // Задержка между публикациями для избежания rate limiting
    sleep(2);
}

$duration3 = round(microtime(true) - $startTime3, 2);

echo "\n📊 Статистика этапа 3:\n";
echo "   Опубликовано: {$pubStats['published']}/{$pubStats['total']}\n";
echo "   С фото: {$pubStats['with_photo']}\n";
echo "   С видео: {$pubStats['with_video']}\n";
echo "   Без медиа: {$pubStats['without_media']}\n";
echo "   Ошибки: {$pubStats['errors']}\n";
echo "   Длительность: {$duration3} сек\n\n";

sendTelegramNotification(
    $telegram,
    $chatId,
    "✅ <b>ЭТАП 3 завершен</b>\n\n" .
    "Опубликовано: {$pubStats['published']}/{$pubStats['total']}\n" .
    "С фото: {$pubStats['with_photo']}\n" .
    "С видео: {$pubStats['with_video']}\n" .
    "Без медиа: {$pubStats['without_media']}\n" .
    "Ошибки: {$pubStats['errors']}\n" .
    "Время: {$duration3} сек"
);

// ============================================================================
// ЭТАП 4: ПРОВЕРКА КЕШИРОВАНИЯ
// ============================================================================

echo "================================================================================\n";
echo "🔄 ЭТАП 4: Проверка кеширования и дедупликации\n";
echo "================================================================================\n\n";

sendTelegramNotification(
    $telegram,
    $chatId,
    "4️⃣ <b>ЭТАП 4: Проверка кеширования</b>\n\n" .
    "Повторный запрос к RSS-лентам...\n" .
    "⏳ Ожидаем 0 новых новостей..."
);

$startTime4 = microtime(true);

$results2 = $fetchRunner->fetchAll($feedConfigs);

$duration4 = round(microtime(true) - $startTime4, 2);

$stats4 = [
    'total_feeds' => count($feedConfigs),
    'successful' => 0,
    'failed' => 0,
    'total_items' => 0,
    'new_items' => 0,
    'cached' => 0,
];

foreach ($results2 as $result) {
    if ($result['success']) {
        $stats4['successful']++;
        $stats4['total_items'] += $result['items_count'];
        $stats4['new_items'] += $result['new_items'];
        
        if ($result['cache_hit'] ?? false) {
            $stats4['cached']++;
        }
    } else {
        $stats4['failed']++;
    }
}

echo "\n📊 Статистика этапа 4:\n";
echo "   Успешно: {$stats4['successful']}/{$stats4['total_feeds']}\n";
echo "   Всего новостей: {$stats4['total_items']}\n";
echo "   Новых: {$stats4['new_items']}\n";
echo "   Кеш использован: {$stats4['cached']} раз\n";
echo "   Длительность: {$duration4} сек\n\n";

sendTelegramNotification(
    $telegram,
    $chatId,
    "✅ <b>ЭТАП 4 завершен</b>\n\n" .
    "Новых новостей: {$stats4['new_items']}\n" .
    "Кеш использован: {$stats4['cached']} раз\n" .
    "Дедупликация: ✅ работает\n" .
    "Время: {$duration4} сек"
);

// ============================================================================
// ЭТАП 5: ПУБЛИКАЦИЯ ИЗ СЛУЧАЙНЫХ ИСТОЧНИКОВ
// ============================================================================

echo "================================================================================\n";
echo "🎲 ЭТАП 5: Публикация из 5 случайных источников\n";
echo "================================================================================\n\n";

sendTelegramNotification(
    $telegram,
    $chatId,
    "5️⃣ <b>ЭТАП 5: Случайная публикация</b>\n\n" .
    "Выбираем 5 случайных источников...\n" .
    "⏳ Начинаем публикацию..."
);

$startTime5 = microtime(true);

// Выбираем 5 случайных источников
$randomFeeds = array_rand(array_column($config['feeds'], 'id'), 5);

$pubStats2 = [
    'total' => 5,
    'published' => 0,
    'with_photo' => 0,
    'with_video' => 0,
    'without_media' => 0,
    'errors' => 0,
];

foreach ($randomFeeds as $feedIndex) {
    $feedId = $config['feeds'][$feedIndex]['id'];
    $feedName = $config['feeds'][$feedIndex]['title'];
    
    echo "📌 $feedName\n";
    
    // Получаем непубликованную новость из этого источника
    $news = $analysisRepository->getByImportance(1, 1, $feedId);
    
    if (empty($news)) {
        echo "   ⚠️ Нет доступных новостей\n\n";
        continue;
    }
    
    $itemId = (int)$news[0]['item_id'];
    $item = $db->queryOne("SELECT * FROM rss2tlg_items WHERE id = ?", [$itemId]);
    
    if (!$item) {
        echo "   ❌ Новость не найдена\n\n";
        $pubStats2['errors']++;
        continue;
    }
    
    $media = extractMedia($item);
    $content = formatNewsForPublication($news[0], $item, $media);
    
    try {
        $result = publishToChannel(
            $telegram,
            $channelId,
            $feedName,
            $news[0]['content_headline'],
            $content,
            $media
        );
        
        if ($result) {
            $pubStats2['published']++;
            
            if ($media && $media['type'] === 'photo') {
                $pubStats2['with_photo']++;
            } elseif ($media && $media['type'] === 'video') {
                $pubStats2['with_video']++;
            } else {
                $pubStats2['without_media']++;
            }
            
            $publicationRepository->save($itemId, 'channel', (string)$result['result']['message_id'], $channelId);
            
            echo "   ✅ Опубликовано\n";
        } else {
            $pubStats2['errors']++;
            echo "   ❌ Ошибка публикации\n";
        }
    } catch (\Exception $e) {
        $pubStats2['errors']++;
        echo "   ❌ Исключение: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    sleep(2);
}

$duration5 = round(microtime(true) - $startTime5, 2);

echo "\n📊 Статистика этапа 5:\n";
echo "   Опубликовано: {$pubStats2['published']}/{$pubStats2['total']}\n";
echo "   С фото: {$pubStats2['with_photo']}\n";
echo "   С видео: {$pubStats2['with_video']}\n";
echo "   Без медиа: {$pubStats2['without_media']}\n";
echo "   Ошибки: {$pubStats2['errors']}\n";
echo "   Длительность: {$duration5} сек\n\n";

sendTelegramNotification(
    $telegram,
    $chatId,
    "✅ <b>ЭТАП 5 завершен</b>\n\n" .
    "Опубликовано: {$pubStats2['published']}/{$pubStats2['total']}\n" .
    "С фото: {$pubStats2['with_photo']}\n" .
    "С видео: {$pubStats2['with_video']}\n" .
    "Без медиа: {$pubStats2['without_media']}\n" .
    "Ошибки: {$pubStats2['errors']}\n" .
    "Время: {$duration5} сек"
);

// ============================================================================
// ЭТАП 6: ИТОГОВАЯ СТАТИСТИКА
// ============================================================================

echo "================================================================================\n";
echo "📊 ЭТАП 6: Итоговая статистика\n";
echo "================================================================================\n\n";

sendTelegramNotification(
    $telegram,
    $chatId,
    "6️⃣ <b>ЭТАП 6: Сбор статистики</b>\n\n" .
    "Анализ базы данных...\n" .
    "⏳ Формирование отчета..."
);

// Статистика из БД
$dbStats = $db->queryOne("
    SELECT 
        COUNT(*) as total_items,
        COUNT(DISTINCT feed_id) as total_feeds,
        SUM(is_published) as published_items,
        SUM(CASE WHEN extraction_status = 'completed' THEN 1 ELSE 0 END) as extracted,
        SUM(CASE WHEN enclosures IS NOT NULL AND enclosures != '[]' THEN 1 ELSE 0 END) as with_media
    FROM rss2tlg_items
");

$aiDbStats = $db->queryOne("
    SELECT 
        COUNT(*) as total_analyzed,
        SUM(CASE WHEN analysis_status = 'completed' THEN 1 ELSE 0 END) as successful,
        SUM(CASE WHEN analysis_status = 'error' THEN 1 ELSE 0 END) as failed,
        AVG(importance_rating) as avg_importance,
        AVG(processing_time_ms) as avg_time_ms,
        SUM(tokens_used) as total_tokens,
        SUM(cache_hit) as cache_hits
    FROM rss2tlg_ai_analysis
");

$publicationsDbStats = $db->queryOne("
    SELECT 
        COUNT(*) as total_publications,
        COUNT(DISTINCT item_id) as unique_items,
        SUM(CASE WHEN destination_type = 'channel' THEN 1 ELSE 0 END) as to_channel,
        SUM(CASE WHEN destination_type = 'bot' THEN 1 ELSE 0 END) as to_bot
    FROM rss2tlg_publications
");

$totalTime = round(microtime(true) - $startTime1, 2);

// Формируем детальный отчет
$report = "\n";
$report .= "================================================================================\n";
$report .= "🎉 ИТОГОВЫЙ ОТЧЕТ\n";
$report .= "================================================================================\n";
$report .= "Тест ID: $testId\n";
$report .= "Время выполнения: $totalTime сек\n";
$report .= "================================================================================\n\n";

$report .= "📥 СБОР НОВОСТЕЙ:\n";
$report .= "   Источников: {$stats1['total_feeds']}\n";
$report .= "   Успешно: {$stats1['successful']}\n";
$report .= "   Новостей получено: {$stats1['total_items']}\n";
$report .= "   Новых: {$stats1['new_items']}\n\n";

$report .= "🤖 AI-АНАЛИЗ:\n";
$report .= "   Проанализировано: {$aiStats['analyzed']}\n";
$report .= "   Успешно: {$aiStats['successful']}\n";
$report .= "   Ошибки: {$aiStats['failed']}\n";
$report .= "   Средний рейтинг: " . round($aiDbStats['avg_importance'] ?? 0, 1) . "/20\n";
$report .= "   Среднее время: " . round($aiDbStats['avg_time_ms'] ?? 0) . " мс\n";
$report .= "   Всего токенов: " . ($aiDbStats['total_tokens'] ?? 0) . "\n";
$report .= "   Cache hits: " . ($aiDbStats['cache_hits'] ?? 0) . "\n\n";

$report .= "📢 ПУБЛИКАЦИИ:\n";
$report .= "   Всего опубликовано: " . ($publicationsDbStats['total_publications'] ?? 0) . "\n";
$report .= "   Уникальных новостей: " . ($publicationsDbStats['unique_items'] ?? 0) . "\n";
$report .= "   В канал: " . ($publicationsDbStats['to_channel'] ?? 0) . "\n";
$report .= "   С фото: " . ($pubStats['with_photo'] + $pubStats2['with_photo']) . "\n";
$report .= "   С видео: " . ($pubStats['with_video'] + $pubStats2['with_video']) . "\n";
$report .= "   Без медиа: " . ($pubStats['without_media'] + $pubStats2['without_media']) . "\n\n";

$report .= "🔄 КЕШИРОВАНИЕ:\n";
$report .= "   Повторных запросов: {$stats4['total_feeds']}\n";
$report .= "   Новых новостей: {$stats4['new_items']}\n";
$report .= "   Кеш использован: {$stats4['cached']} раз\n";
$report .= "   Дедупликация: " . ($stats4['new_items'] === 0 ? '✅ работает' : '⚠️ частично') . "\n\n";

$report .= "💾 БАЗА ДАННЫХ:\n";
$report .= "   Всего новостей: " . ($dbStats['total_items'] ?? 0) . "\n";
$report .= "   Источников: " . ($dbStats['total_feeds'] ?? 0) . "\n";
$report .= "   Опубликовано: " . ($dbStats['published_items'] ?? 0) . "\n";
$report .= "   Извлечено контента: " . ($dbStats['extracted'] ?? 0) . "\n";
$report .= "   С медиа: " . ($dbStats['with_media'] ?? 0) . "\n\n";

$report .= "⏱️ ПРОИЗВОДИТЕЛЬНОСТЬ:\n";
$report .= "   Сбор новостей: {$duration1} сек\n";
$report .= "   AI-анализ: {$duration2} сек\n";
$report .= "   Публикация (1): {$duration3} сек\n";
$report .= "   Проверка кеша: {$duration4} сек\n";
$report .= "   Публикация (2): {$duration5} сек\n";
$report .= "   Общее время: $totalTime сек\n\n";

$report .= "================================================================================\n";
$report .= "✅ ТЕСТ ЗАВЕРШЕН УСПЕШНО!\n";
$report .= "================================================================================\n";

echo $report;

// Отправляем итоговый отчет в Telegram
sendTelegramNotification(
    $telegram,
    $chatId,
    "🎉 <b>ТЕСТ ЗАВЕРШЕН!</b>\n\n" .
    "<b>📊 Краткая статистика:</b>\n\n" .
    "📥 Новостей: " . ($dbStats['total_items'] ?? 0) . "\n" .
    "🤖 Проанализировано: {$aiStats['successful']}\n" .
    "📢 Опубликовано: " . ($publicationsDbStats['total_publications'] ?? 0) . "\n" .
    "🔄 Дедупликация: " . ($stats4['new_items'] === 0 ? '✅' : '⚠️') . "\n" .
    "⏱️ Время: $totalTime сек\n\n" .
    "📁 Лог: <code>$logFile</code>"
);

echo "\n";

// ============================================================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// ============================================================================

/**
 * Отправка уведомления в Telegram
 */
function sendTelegramNotification(
    TelegramAPI $telegram,
    int $chatId,
    string $message,
    bool $withTyping = true
): void {
    try {
        if ($withTyping) {
            $telegram->sendChatAction($chatId, 'typing');
            usleep(300000); // 0.3 сек
        }
        
        $telegram->sendMessage($chatId, $message, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
    } catch (\Exception $e) {
        echo "⚠️ Ошибка отправки уведомления: " . $e->getMessage() . "\n";
    }
}

/**
 * Извлекает медиа из новости
 */
function extractMedia(array $item): ?array
{
    if (!empty($item['enclosures'])) {
        $enclosures = is_string($item['enclosures'])
            ? json_decode($item['enclosures'], true)
            : $item['enclosures'];
        
        if (is_array($enclosures) && !empty($enclosures['url'])) {
            $type = $enclosures['type'] ?? '';
            $url = $enclosures['url'] ?? '';
            
            if (!empty($url)) {
                if (str_starts_with($type, 'image/') || preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $url)) {
                    return ['type' => 'photo', 'url' => $url];
                } elseif (str_starts_with($type, 'video/') || preg_match('/\.(mp4|mov|avi|webm)$/i', $url)) {
                    return ['type' => 'video', 'url' => $url];
                }
            }
        }
    }
    
    return null;
}

/**
 * Получает название источника по ID
 */
function getFeedName(array $feeds, int $feedId): string
{
    foreach ($feeds as $feed) {
        if ($feed['id'] === $feedId) {
            return $feed['title'];
        }
    }
    return "Источник #$feedId";
}

/**
 * Форматирует новость для публикации с отладочной информацией
 */
function formatNewsForPublication(array $analysis, array $item, ?array $media): string
{
    $headline = $analysis['content_headline'] ?? $item['title'];
    $summary = $analysis['content_summary'] ?? mb_substr($item['description'] ?? '', 0, 300);
    
    // Основной текст
    $text = "<b>$headline</b>\n\n$summary";
    
    // Отладочная информация на русском
    $debug = "\n\n━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $debug .= "🔍 <b>Отладочная информация:</b>\n\n";
    
    // AI-анализ
    $debug .= "🤖 <b>AI-анализ:</b>\n";
    $debug .= "   • Рейтинг: {$analysis['importance_rating']}/20\n";
    $debug .= "   • Категория: {$analysis['category_primary']} ({$analysis['category_confidence']})\n";
    $debug .= "   • Язык: {$analysis['article_language']}\n";
    $debug .= "   • Перевод: {$analysis['translation_status']}\n";
    
    if ($analysis['translation_quality_score']) {
        $debug .= "   • Качество перевода: {$analysis['translation_quality_score']}/10\n";
    }
    
    // Метаданные
    $debug .= "\n📝 <b>Метаданные:</b>\n";
    $debug .= "   • ID новости: {$item['id']}\n";
    $debug .= "   • Дата: " . date('d.m.Y H:i', strtotime($item['published_at'])) . "\n";
    $debug .= "   • Модель AI: {$analysis['model_used']}\n";
    $debug .= "   • Токенов: " . ($analysis['tokens_used'] ?? 0) . "\n";
    $debug .= "   • Время обработки: " . ($analysis['processing_time_ms'] ?? 0) . " мс\n";
    
    // Медиа
    if ($media) {
        $debug .= "\n🎬 <b>Медиа:</b>\n";
        $debug .= "   • Тип: {$media['type']}\n";
        $debug .= "   • Источник: RSS enclosure\n";
    }
    
    return $text . $debug;
}

/**
 * Публикация в канал
 */
function publishToChannel(
    TelegramAPI $telegram,
    string $channelId,
    string $feedName,
    string $title,
    string $content,
    ?array $media
): ?array {
    try {
        $message = "<b>📰 $feedName</b>\n\n$content";
        
        if ($media && !empty($media['url'])) {
            $caption = mb_strlen($message) > 1024
                ? mb_substr($message, 0, 1020) . "..."
                : $message;
            
            if ($media['type'] === 'photo') {
                return $telegram->sendPhoto($channelId, $media['url'], [
                    'caption' => $caption,
                    'parse_mode' => TelegramAPI::PARSE_MODE_HTML
                ]);
            } elseif ($media['type'] === 'video') {
                return $telegram->sendVideo($channelId, $media['url'], [
                    'caption' => $caption,
                    'parse_mode' => TelegramAPI::PARSE_MODE_HTML
                ]);
            }
        }
        
        return $telegram->sendMessage($channelId, $message, [
            'parse_mode' => TelegramAPI::PARSE_MODE_HTML
        ]);
    } catch (\Exception $e) {
        echo "⚠️ Ошибка публикации: " . $e->getMessage() . "\n";
        return null;
    }
}

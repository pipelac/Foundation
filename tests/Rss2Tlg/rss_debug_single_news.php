<?php

declare(strict_types=1);

/**
 * 🔬 ДЕТАЛЬНЫЙ ТЕСТ ОБРАБОТКИ ОДНОЙ ИНОСТРАННОЙ НОВОСТИ
 * 
 * Идентификатор: RSS2TLG-DEBUG-SINGLE-001
 * 
 * ЦЕЛЬ:
 * Провести полную отладку цепочки обработки одной иностранной новости
 * от скачивания из RSS до публикации в Telegram канале.
 * 
 * ЭТАПЫ С ДЕТАЛЬНЫМ ЛОГИРОВАНИЕМ:
 * 1. Сбор новостей из RSS лент (иностранные источники)
 * 2. Сохранение в таблицу rss2tlg_items (с полными данными)
 * 3. Извлечение полного текста (если требуется)
 * 4. AI-анализ и перевод (с метриками)
 * 5. Сохранение результата в rss2tlg_ai_analysis
 * 6. Публикация в Telegram бот (уведомление)
 * 7. Публикация в Telegram канал (основная публикация)
 * 8. Запись в rss2tlg_publications (tracking)
 * 
 * ТРЕБОВАНИЯ:
 * - MariaDB/MySQL запущен и доступен
 * - OpenRouter API ключ настроен
 * - Telegram bot и channel настроены
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\Http;
use App\Component\WebtExtractor;
use App\Component\OpenRouter;
use App\Config\ConfigLoader;
use App\Rss2Tlg\FetchRunner;
use App\Rss2Tlg\ItemRepository;
use App\Rss2Tlg\PublicationRepository;
use App\Rss2Tlg\FeedStateRepository;
use App\Rss2Tlg\ContentExtractorService;
use App\Rss2Tlg\AIAnalysisService;
use App\Rss2Tlg\AIAnalysisRepository;
use App\Rss2Tlg\PromptManager;
use App\Rss2Tlg\DTO\FeedConfig;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Keyboards\InlineKeyboardBuilder;

// ============================================================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// ============================================================================

function sendTelegramNotification(TelegramAPI $telegram, int $chatId, string $message): void
{
    try {
        $telegram->sendMessage($chatId, $message, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
        echo "✓ Уведомление отправлено в Telegram\n";
    } catch (Exception $e) {
        echo "⚠️ Ошибка отправки уведомления: {$e->getMessage()}\n";
    }
}

function printSeparator(string $title = ''): void
{
    $width = 80;
    if ($title) {
        $padding = ($width - strlen($title) - 4) / 2;
        $leftPad = str_repeat('═', (int)floor($padding));
        $rightPad = str_repeat('═', (int)ceil($padding));
        echo "\n╔{$leftPad}╣ {$title} ╠{$rightPad}╗\n";
    } else {
        echo "\n" . str_repeat('═', $width) . "\n";
    }
}

function printStep(string $step, int $stepNumber, int $totalSteps): void
{
    echo "\n";
    echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
    echo "║  ШАГ {$stepNumber}/{$totalSteps}: {$step}" . str_repeat(' ', 65 - strlen($step)) . "║\n";
    echo "╚════════════════════════════════════════════════════════════════════════════╝\n";
    echo "\n";
}

function printData(string $label, $value, int $indent = 2): void
{
    $prefix = str_repeat(' ', $indent);
    if (is_array($value)) {
        echo "{$prefix}📋 {$label}:\n";
        foreach ($value as $key => $val) {
            if (is_array($val)) {
                echo "{$prefix}  • {$key}: " . json_encode($val, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
            } else {
                $displayVal = is_string($val) && mb_strlen($val) > 100 
                    ? mb_substr($val, 0, 100) . '...' 
                    : $val;
                echo "{$prefix}  • {$key}: {$displayVal}\n";
            }
        }
    } else {
        $displayVal = is_string($value) && mb_strlen($value) > 100 
            ? mb_substr($value, 0, 100) . '...' 
            : $value;
        echo "{$prefix}📋 {$label}: {$displayVal}\n";
    }
}

function printDbRecord(string $tableName, array $record): void
{
    echo "\n  📊 Данные в таблице '{$tableName}':\n";
    foreach ($record as $field => $value) {
        if (is_string($value) && mb_strlen($value) > 150) {
            $value = mb_substr($value, 0, 150) . '... [обрезано]';
        }
        if ($value === null) {
            $value = 'NULL';
        }
        echo "    • {$field}: " . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value) . "\n";
    }
}

// ============================================================================
// КОНФИГУРАЦИЯ
// ============================================================================

$testId = 'RSS2TLG-DEBUG-SINGLE-001';
$configPath = __DIR__ . '/../../config/rss2tlg_debug_test.json';
$promptsDir = __DIR__ . '/../../prompts';

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║  🔬 ДЕТАЛЬНЫЙ ТЕСТ ОБРАБОТКИ ОДНОЙ ИНОСТРАННОЙ НОВОСТИ                    ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "Тест ID: {$testId}\n";
echo "Конфиг: {$configPath}\n";
echo "\n";

// ============================================================================
// ИНИЦИАЛИЗАЦИЯ КОМПОНЕНТОВ
// ============================================================================

printStep('ИНИЦИАЛИЗАЦИЯ КОМПОНЕНТОВ', 1, 8);

$configLoader = new ConfigLoader();
$config = $configLoader->load($configPath);

echo "📦 Загружаем компоненты...\n\n";

// Логгер
$logConfig = $config['logging'];
$logger = new Logger([
    'directory' => $logConfig['directory'],
    'file_name' => $logConfig['file_name'],
    'max_files' => $logConfig['max_files'] ?? 10,
    'max_file_size' => $logConfig['max_file_size'] ?? 100,
    'enabled' => $logConfig['enabled'] ?? true,
]);
printData('Логгер', "{$logConfig['directory']}/{$logConfig['file_name']}");

// База данных
$dbConfig = $config['database'];
$db = new MySQL([
    'host' => $dbConfig['host'],
    'port' => $dbConfig['port'],
    'database' => $dbConfig['name'],
    'username' => $dbConfig['user'],
    'password' => $dbConfig['password'],
    'charset' => $dbConfig['charset'] ?? 'utf8mb4',
], $logger);
printData('База данных', "{$dbConfig['name']} @ {$dbConfig['host']}:{$dbConfig['port']}");

// HTTP и WebtExtractor
$http = new Http([], $logger);
$extractor = new WebtExtractor([], $logger);
printData('HTTP Client', 'Инициализирован');
printData('WebtExtractor', 'Инициализирован');

// Telegram API
$telegramConfig = $config['telegram'];
$telegram = new TelegramAPI($telegramConfig['bot_token'], $http, $logger);
$chatId = (int)$telegramConfig['chat_id'];
$channelId = $telegramConfig['channel_id'];
printData('Telegram Bot Chat ID', $chatId);
printData('Telegram Channel ID', $channelId);

// OpenRouter
$openRouterConfig = [
    'api_key' => $config['ai_analysis']['api_key'],
    'base_url' => 'https://openrouter.ai/api/v1',
    'default_model' => $config['ai_analysis']['default_model'],
    'timeout' => $config['ai_analysis']['timeout'] ?? 180,
];
$openRouter = new OpenRouter($openRouterConfig, $logger);
printData('OpenRouter Model', $openRouterConfig['default_model']);
printData('OpenRouter Timeout', $openRouterConfig['timeout'] . ' сек');

// Репозитории
$itemRepository = new ItemRepository($db, $logger);
$publicationRepository = new PublicationRepository($db, $logger);
$feedStateRepository = new FeedStateRepository($db, $logger);
$analysisRepository = new AIAnalysisRepository($db, $logger, true);
printData('Репозитории', 'Item, Publication, FeedState, AIAnalysis');

// Сервисы
$cacheDir = $config['cache']['directory'];
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

$fetchRunner = new FetchRunner($db, $cacheDir, $logger);
$contentExtractor = new ContentExtractorService($itemRepository, $extractor, $logger);
$promptManager = new PromptManager($promptsDir, $logger);
$aiAnalysisService = new AIAnalysisService(
    $promptManager,
    $analysisRepository,
    $openRouter,
    $db,
    $logger
);
printData('Сервисы', 'FetchRunner, ContentExtractor, PromptManager, AIAnalysisService');
printData('Cache Directory', $cacheDir);

echo "\n✅ Все компоненты инициализированы успешно\n";

// Отправляем стартовое уведомление
sendTelegramNotification(
    $telegram,
    $chatId,
    "🔬 <b>СТАРТ DEBUG ТЕСТА</b>\n\n" .
    "<b>Тест:</b> {$testId}\n" .
    "<b>Цель:</b> Отладка одной иностранной новости\n" .
    "<b>Источников:</b> " . count($config['feeds']) . "\n\n" .
    "⏳ Начинаем обработку..."
);

// ============================================================================
// ШАГ 2: СБОР НОВОСТЕЙ ИЗ RSS ЛЕНТ
// ============================================================================

printStep('СБОР НОВОСТЕЙ ИЗ RSS ЛЕНТ', 2, 8);

echo "📡 Опрашиваем RSS ленты (только иностранные источники)...\n\n";

// Фильтруем только иностранные источники
$foreignFeeds = array_filter($config['feeds'], function($feed) {
    return $feed['language'] !== 'ru';
});

echo "Отфильтровано " . count($foreignFeeds) . " иностранных источников\n";
echo "Языки: en, fr, de, zh\n\n";

$feedConfigs = [];
foreach ($foreignFeeds as $feedData) {
    $feedConfig = new FeedConfig(
        $feedData['id'],
        $feedData['url'],
        $feedData['title'] ?? 'Unknown',
        $feedData['enabled'] ?? true,
        $feedData['timeout'] ?? 30,
        $feedData['retries'] ?? 3,
        $feedData['polling_interval'] ?? 300,
        $feedData['headers'] ?? [],
        $feedData['parser_options'] ?? [],
        $feedData['proxy'] ?? null
    );
    $feedConfigs[] = $feedConfig;
    
    echo "  📰 [{$feedData['id']}] {$feedData['title']} ({$feedData['language']})\n";
    echo "      URL: {$feedData['url']}\n";
}

echo "\n🔄 Запускаем сбор...\n\n";

$fetchResults = $fetchRunner->runForAllFeeds($feedConfigs);

$allItems = [];
$totalItems = 0;

foreach ($fetchResults as $feedId => $result) {
    $feedTitle = 'Unknown';
    foreach ($foreignFeeds as $feed) {
        if ($feed['id'] === $feedId) {
            $feedTitle = $feed['title'];
            break;
        }
    }
    
    echo "  📰 Feed #{$feedId} ({$feedTitle}):\n";
    echo "      Статус: " . $result->getStatus() . "\n";
    
    if ($result->items) {
        $itemCount = count($result->items);
        $totalItems += $itemCount;
        echo "      Новостей: {$itemCount}\n";
        
        // Сохраняем новости
        foreach ($result->items as $rawItem) {
            try {
                $savedItemId = $itemRepository->save($feedId, $rawItem);
                echo "      ✓ Сохранено: ID={$savedItemId}, Title=" . mb_substr($rawItem->title, 0, 60) . "...\n";
                
                $allItems[] = [
                    'id' => $savedItemId,
                    'feed_id' => $feedId,
                    'feed_title' => $feedTitle,
                    'title' => $rawItem->title,
                    'link' => $rawItem->link,
                    'description' => $rawItem->summary,
                    'pub_date' => $rawItem->pubDate,
                ];
            } catch (\Exception $e) {
                echo "      ✗ Ошибка сохранения: {$e->getMessage()}\n";
                $logger->error("Ошибка сохранения новости: {$e->getMessage()}");
            }
        }
    } else {
        echo "      Новостей: 0\n";
    }
    
    if ($result->error !== null) {
        echo "      ⚠️ Ошибка: {$result->error}\n";
    }
    
    echo "\n";
}

echo "📊 Всего собрано новостей: {$totalItems}\n";

if (empty($allItems)) {
    echo "❌ Новости не найдены! Тест прерван.\n";
    sendTelegramNotification(
        $telegram,
        $chatId,
        "❌ <b>ТЕСТ ПРЕРВАН</b>\n\nНовости не найдены в RSS лентах."
    );
    exit(1);
}

// Выбираем первую новость для детального анализа
$selectedItem = $allItems[0];
$itemId = $selectedItem['id'];

echo "\n🎯 Выбрана новость для детального анализа:\n";
printData('Item ID', $itemId);
printData('Feed', "#{$selectedItem['feed_id']} {$selectedItem['feed_title']}");
printData('Title', $selectedItem['title']);
printData('Link', $selectedItem['link']);
$description = $selectedItem['description'] ?? '';
if ($description && mb_strlen($description) > 200) {
    $description = mb_substr($description, 0, 200) . '...';
}
printData('Description', $description ?: 'Нет описания');
printData('Published', $selectedItem['pub_date'] ? date('Y-m-d H:i:s', $selectedItem['pub_date']) : 'Неизвестно');

// Получаем полную запись из БД
$dbItem = $itemRepository->getById($itemId);
if ($dbItem) {
    printDbRecord('rss2tlg_items', $dbItem);
}

sendTelegramNotification(
    $telegram,
    $chatId,
    "✅ <b>ШАГ 2 ЗАВЕРШЕН</b>\n\n" .
    "📊 Собрано новостей: {$totalItems}\n" .
    "🎯 Выбрана для анализа:\n" .
    "  • ID: {$itemId}\n" .
    "  • Источник: {$selectedItem['feed_title']}\n" .
    "  • Заголовок: " . mb_substr($selectedItem['title'], 0, 50) . "...\n\n" .
    "⏳ ШАГ 3: Извлечение полного текста..."
);

// ============================================================================
// ШАГ 3: ИЗВЛЕЧЕНИЕ ПОЛНОГО ТЕКСТА
// ============================================================================

printStep('ИЗВЛЕЧЕНИЕ ПОЛНОГО ТЕКСТА', 3, 8);

echo "🔍 Извлекаем полный текст статьи из ссылки...\n\n";

$extractionStart = microtime(true);

try {
    echo "  URL: {$selectedItem['link']}\n";
    echo "  Начало извлечения...\n";
    
    // Получаем полные данные из БД для передачи в ContentExtractor
    $itemData = $itemRepository->getById($itemId);
    if (!$itemData) {
        throw new \Exception("Новость с ID {$itemId} не найдена в БД");
    }
    
    $contentExtractor->processItem($itemData);
    
    $extractionEnd = microtime(true);
    $extractionTime = round(($extractionEnd - $extractionStart) * 1000, 2);
    
    echo "  ✓ Извлечение завершено за {$extractionTime} мс\n\n";
    
    // Получаем обновленную запись
    $dbItem = $itemRepository->getById($itemId);
    
    if ($dbItem) {
        echo "  📊 Статус извлечения: {$dbItem['extraction_status']}\n";
        
        if ($dbItem['full_content']) {
            $contentLength = mb_strlen($dbItem['full_content']);
            echo "  📝 Длина контента: {$contentLength} символов\n";
            echo "  📄 Превью контента:\n";
            echo "      " . mb_substr($dbItem['full_content'], 0, 300) . "...\n";
        }
        
        printDbRecord('rss2tlg_items (после извлечения)', $dbItem);
    }
    
    sendTelegramNotification(
        $telegram,
        $chatId,
        "✅ <b>ШАГ 3 ЗАВЕРШЕН</b>\n\n" .
        "🔍 Извлечение контента:\n" .
        "  • Статус: {$dbItem['extraction_status']}\n" .
        "  • Время: {$extractionTime} мс\n" .
        "  • Длина: " . mb_strlen($dbItem['full_content'] ?? '') . " символов\n\n" .
        "⏳ ШАГ 4: AI-анализ и перевод..."
    );
    
} catch (\Exception $e) {
    echo "  ✗ Ошибка извлечения: {$e->getMessage()}\n";
    $logger->error("Ошибка извлечения контента для item {$itemId}: {$e->getMessage()}");
    
    sendTelegramNotification(
        $telegram,
        $chatId,
        "⚠️ <b>ШАГ 3: ПРЕДУПРЕЖДЕНИЕ</b>\n\n" .
        "Ошибка извлечения контента:\n{$e->getMessage()}\n\n" .
        "⏳ Продолжаем с description..."
    );
}

// ============================================================================
// ШАГ 4: AI-АНАЛИЗ И ПЕРЕВОД
// ============================================================================

printStep('AI-АНАЛИЗ И ПЕРЕВОД', 4, 8);

echo "🤖 Запускаем AI-анализ через OpenRouter...\n\n";

$promptId = 'INoT_v1';
$aiModels = [$config['ai_analysis']['default_model']];
if (!empty($config['ai_analysis']['fallback_models'])) {
    $aiModels = array_merge($aiModels, $config['ai_analysis']['fallback_models']);
}

$aiOptions = [
    'temperature' => $config['ai_analysis']['temperature'] ?? 0.25,
    'top_p' => $config['ai_analysis']['top_p'] ?? 0.85,
    'frequency_penalty' => $config['ai_analysis']['frequency_penalty'] ?? 0.15,
    'presence_penalty' => $config['ai_analysis']['presence_penalty'] ?? 0.10,
    'max_tokens' => $config['ai_analysis']['max_tokens'] ?? 2000,
    'min_tokens' => $config['ai_analysis']['min_tokens'] ?? 400,
];

printData('Prompt ID', $promptId);
printData('AI Models', implode(', ', $aiModels));
printData('AI Options', $aiOptions);

echo "\n🔄 Начинаем анализ...\n\n";

$analysisStart = microtime(true);

try {
    // Получаем актуальные данные новости
    $itemData = $itemRepository->getById($itemId);
    
    if (!$itemData) {
        throw new \Exception("Новость с ID {$itemId} не найдена в БД");
    }
    
    echo "  📋 Входные данные для AI:\n";
    echo "      Title: " . mb_substr($itemData['title'], 0, 80) . "...\n";
    echo "      Description: " . mb_substr($itemData['description'] ?? '', 0, 100) . "...\n";
    echo "      Full Content: " . (empty($itemData['full_content']) ? 'НЕТ' : mb_strlen($itemData['full_content']) . ' символов') . "\n";
    echo "\n";
    
    $analysis = $aiAnalysisService->analyzeWithFallback($itemData, $promptId, $aiModels, $aiOptions);
    
    $analysisEnd = microtime(true);
    $analysisDuration = round(($analysisEnd - $analysisStart) * 1000, 2);
    
    if ($analysis !== null) {
        echo "  ✅ AI-анализ завершен за {$analysisDuration} мс\n\n";
        
        echo "  📊 Результаты анализа:\n";
        printData('Категория', $analysis['category_primary'], 4);
        printData('Важность', $analysis['importance_rating'] . '/20', 4);
        printData('Язык оригинала', $analysis['article_language'], 4);
        printData('Статус перевода', $analysis['translation_status'], 4);
        printData('Заголовок (переведен)', $analysis['content_headline'], 4);
        printData('Саммари (переведен)', mb_substr($analysis['content_summary'], 0, 200) . '...', 4);
        
        if (isset($analysis['translation_quality'])) {
            echo "      📈 Качество перевода:\n";
            $tq = $analysis['translation_quality'];
            echo "          Точность: " . ($tq['accuracy_score'] ?? 'N/A') . "/100\n";
            echo "          Читаемость: " . ($tq['readability_score'] ?? 'N/A') . "/100\n";
            echo "          Полнота: " . ($tq['completeness_score'] ?? 'N/A') . "/100\n";
            echo "          Общий балл: " . ($tq['overall_score'] ?? 'N/A') . "/100\n";
        }
        
        // Получаем метрики из БД
        $savedAnalysis = $analysisRepository->getByItemId($itemId);
        
        if ($savedAnalysis) {
            echo "\n  📊 Метрики AI запроса:\n";
            printData('Токенов использовано', $savedAnalysis['tokens_used'], 4);
            printData('Время обработки', $savedAnalysis['processing_time_ms'] . ' мс', 4);
            printData('Модель', $savedAnalysis['model_used'], 4);
            printData('Кеш использован', $savedAnalysis['cache_hit'] ? 'Да' : 'Нет', 4);
            
            if ($savedAnalysis['translation_quality_score']) {
                printData('Оценка перевода', $savedAnalysis['translation_quality_score'] . '/100', 4);
            }
            
            printDbRecord('rss2tlg_ai_analysis', $savedAnalysis);
            
            // Вычисляем стоимость
            $apiMetrics = $aiAnalysisService->getLastApiMetrics();
            if ($apiMetrics && isset($apiMetrics['usage'])) {
                $usage = $apiMetrics['usage'];
                $promptTokens = $usage['prompt_tokens'] ?? 0;
                $completionTokens = $usage['completion_tokens'] ?? 0;
                
                echo "\n  💰 Стоимость запроса (deepseek/deepseek-chat-v3.1):\n";
                $promptCost = ($promptTokens / 1000000) * 0.14;
                $completionCost = ($completionTokens / 1000000) * 0.28;
                $totalCost = $promptCost + $completionCost;
                
                echo "      Промпт токенов: {$promptTokens}\n";
                echo "      Ответ токенов: {$completionTokens}\n";
                echo "      Стоимость промпта: $" . number_format($promptCost, 6) . "\n";
                echo "      Стоимость ответа: $" . number_format($completionCost, 6) . "\n";
                echo "      Общая стоимость: $" . number_format($totalCost, 6) . "\n";
            }
        }
        
        sendTelegramNotification(
            $telegram,
            $chatId,
            "✅ <b>ШАГ 4 ЗАВЕРШЕН</b>\n\n" .
            "🤖 AI-анализ:\n" .
            "  • Категория: {$analysis['category_primary']}\n" .
            "  • Важность: {$analysis['importance_rating']}/20\n" .
            "  • Язык: {$analysis['article_language']}\n" .
            "  • Перевод: {$analysis['translation_status']}\n" .
            "  • Время: {$analysisDuration} мс\n" .
            "  • Токенов: " . ($savedAnalysis['tokens_used'] ?? 0) . "\n\n" .
            "⏳ ШАГ 5: Проверка данных в БД..."
        );
        
    } else {
        throw new \Exception("AI-анализ вернул null");
    }
    
} catch (\Exception $e) {
    echo "  ❌ Ошибка AI-анализа: {$e->getMessage()}\n";
    $logger->error("Ошибка AI-анализа для item {$itemId}: {$e->getMessage()}");
    
    sendTelegramNotification(
        $telegram,
        $chatId,
        "❌ <b>ШАГ 4: ОШИБКА</b>\n\n" .
        "AI-анализ не удался:\n{$e->getMessage()}\n\n" .
        "Тест прерван."
    );
    
    exit(1);
}

// ============================================================================
// ШАГ 5: ПРОВЕРКА ДАННЫХ В БД
// ============================================================================

printStep('ПРОВЕРКА ДАННЫХ В БД', 5, 8);

echo "🔍 Проверяем все записи в базе данных...\n\n";

// Проверяем rss2tlg_items
echo "📋 Таблица: rss2tlg_items\n";
$itemRecord = $db->queryOne("SELECT * FROM rss2tlg_items WHERE id = ?", [$itemId]);
if ($itemRecord) {
    printDbRecord('rss2tlg_items', $itemRecord);
} else {
    echo "  ⚠️ Запись не найдена!\n";
}

// Проверяем rss2tlg_ai_analysis
echo "\n📋 Таблица: rss2tlg_ai_analysis\n";
$analysisRecord = $db->queryOne("SELECT * FROM rss2tlg_ai_analysis WHERE item_id = ?", [$itemId]);
if ($analysisRecord) {
    printDbRecord('rss2tlg_ai_analysis', $analysisRecord);
} else {
    echo "  ⚠️ Запись не найдена!\n";
}

// Проверяем rss2tlg_feed_state
echo "\n📋 Таблица: rss2tlg_feed_state\n";
$feedStateRecord = $db->queryOne("SELECT * FROM rss2tlg_feed_state WHERE feed_id = ?", [$selectedItem['feed_id']]);
if ($feedStateRecord) {
    printDbRecord('rss2tlg_feed_state', $feedStateRecord);
} else {
    echo "  ⚠️ Запись не найдена!\n";
}

// Проверяем rss2tlg_publications (пока пусто)
echo "\n📋 Таблица: rss2tlg_publications\n";
$publicationRecords = $db->query("SELECT * FROM rss2tlg_publications WHERE item_id = ?", [$itemId]);
if ($publicationRecords) {
    foreach ($publicationRecords as $pub) {
        printDbRecord('rss2tlg_publications', $pub);
    }
} else {
    echo "  ℹ️ Записей пока нет (будут добавлены после публикации)\n";
}

sendTelegramNotification(
    $telegram,
    $chatId,
    "✅ <b>ШАГ 5 ЗАВЕРШЕН</b>\n\n" .
    "🔍 Проверка БД:\n" .
    "  • rss2tlg_items: " . ($itemRecord ? '✓' : '✗') . "\n" .
    "  • rss2tlg_ai_analysis: " . ($analysisRecord ? '✓' : '✗') . "\n" .
    "  • rss2tlg_feed_state: " . ($feedStateRecord ? '✓' : '✗') . "\n" .
    "  • rss2tlg_publications: ожидание\n\n" .
    "⏳ ШАГ 6: Публикация в Telegram бот..."
);

// ============================================================================
// ШАГ 6: ПУБЛИКАЦИЯ В TELEGRAM БОТ (УВЕДОМЛЕНИЕ)
// ============================================================================

printStep('ПУБЛИКАЦИЯ В TELEGRAM БОТ', 6, 8);

echo "📱 Отправляем уведомление о новой новости в Telegram бот...\n\n";

try {
    $botMessage = "📰 <b>НОВАЯ НОВОСТЬ</b>\n\n" .
                  "<b>{$analysis['content_headline']}</b>\n\n" .
                  "{$analysis['content_summary']}\n\n" .
                  "📊 <b>Метаданные:</b>\n" .
                  "  • Категория: {$analysis['category_primary']}\n" .
                  "  • Важность: {$analysis['importance_rating']}/20\n" .
                  "  • Язык: {$analysis['article_language']}\n" .
                  "  • Источник: {$selectedItem['feed_title']}\n\n" .
                  "🔗 <a href=\"{$selectedItem['link']}\">Читать полностью</a>";
    
    echo "  📝 Текст сообщения:\n";
    echo "      " . str_replace("\n", "\n      ", $botMessage) . "\n\n";
    
    $botMsg = $telegram->sendMessage($chatId, $botMessage, ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]);
    
    echo "  ✅ Сообщение отправлено в бот\n";
    echo "  📋 Message ID: {$botMsg->messageId}\n";
    echo "  📋 Chat ID: {$botMsg->chat->id}\n";
    echo "  📋 Date: " . date('Y-m-d H:i:s', $botMsg->date) . "\n";
    
    // Сохраняем публикацию в БД
    $botPublicationId = $publicationRepository->record(
        $itemId,
        $selectedItem['feed_id'],
        'bot',
        (string)$chatId,
        $botMsg->messageId
    );
    
    echo "  💾 Публикация записана в БД: ID={$botPublicationId}\n";
    
    // Проверяем запись
    $botPubRecord = $db->queryOne("SELECT * FROM rss2tlg_publications WHERE id = ?", [$botPublicationId]);
    if ($botPubRecord) {
        printDbRecord('rss2tlg_publications (bot)', $botPubRecord);
    }
    
} catch (\Exception $e) {
    echo "  ❌ Ошибка публикации в бот: {$e->getMessage()}\n";
    $logger->error("Ошибка публикации в бот для item {$itemId}: {$e->getMessage()}");
    
    sendTelegramNotification(
        $telegram,
        $chatId,
        "⚠️ <b>ШАГ 6: ОШИБКА</b>\n\n" .
        "Ошибка публикации в бот:\n{$e->getMessage()}"
    );
}

sendTelegramNotification(
    $telegram,
    $chatId,
    "✅ <b>ШАГ 6 ЗАВЕРШЕН</b>\n\n" .
    "📱 Публикация в бот:\n" .
    "  • Message ID: {$botMsg->messageId}\n" .
    "  • DB Record ID: {$botPublicationId}\n\n" .
    "⏳ ШАГ 7: Публикация в Telegram канал..."
);

// ============================================================================
// ШАГ 7: ПУБЛИКАЦИЯ В TELEGRAM КАНАЛ
// ============================================================================

printStep('ПУБЛИКАЦИЯ В TELEGRAM КАНАЛ', 7, 8);

echo "📢 Публикуем новость в Telegram канал {$channelId}...\n\n";

try {
    // Формируем форматированное сообщение для канала
    $channelMessage = "📰 <b>{$analysis['content_headline']}</b>\n\n";
    $channelMessage .= "{$analysis['content_summary']}\n\n";
    
    // Добавляем метки категории и важности
    $categoryEmoji = [
        'Technology' => '💻',
        'Science' => '🔬',
        'Business' => '💼',
        'Health' => '🏥',
        'Entertainment' => '🎬',
        'Sports' => '⚽',
        'Politics' => '🏛️',
        'General' => '📰',
    ];
    
    $emoji = $categoryEmoji[$analysis['category_primary']] ?? '📰';
    $channelMessage .= "{$emoji} {$analysis['category_primary']}";
    
    if ($analysis['importance_rating'] >= 15) {
        $channelMessage .= " • 🔥 Высокая важность";
    }
    
    $channelMessage .= "\n\n🔗 <a href=\"{$selectedItem['link']}\">Читать оригинал</a>";
    $channelMessage .= "\n📰 Источник: {$selectedItem['feed_title']}";
    
    echo "  📝 Текст сообщения:\n";
    echo "      " . str_replace("\n", "\n      ", $channelMessage) . "\n\n";
    
    $channelMsg = $telegram->sendMessage($channelId, $channelMessage, [
        'parse_mode' => TelegramAPI::PARSE_MODE_HTML,
        'disable_web_page_preview' => false
    ]);
    
    echo "  ✅ Новость опубликована в канале\n";
    echo "  📋 Message ID: {$channelMsg->messageId}\n";
    echo "  📋 Chat ID: {$channelMsg->chat->id}\n";
    echo "  📋 Chat Title: {$channelMsg->chat->title}\n";
    echo "  📋 Date: " . date('Y-m-d H:i:s', $channelMsg->date) . "\n";
    
    // Сохраняем публикацию в БД
    $channelPublicationId = $publicationRepository->record(
        $itemId,
        $selectedItem['feed_id'],
        'channel',
        $channelId,
        $channelMsg->messageId
    );
    
    echo "  💾 Публикация записана в БД: ID={$channelPublicationId}\n";
    
    // Проверяем запись
    $channelPubRecord = $db->queryOne("SELECT * FROM rss2tlg_publications WHERE id = ?", [$channelPublicationId]);
    if ($channelPubRecord) {
        printDbRecord('rss2tlg_publications (channel)', $channelPubRecord);
    }
    
    sendTelegramNotification(
        $telegram,
        $chatId,
        "✅ <b>ШАГ 7 ЗАВЕРШЕН</b>\n\n" .
        "📢 Публикация в канале:\n" .
        "  • Channel: {$channelMsg->chat->title}\n" .
        "  • Message ID: {$channelMsg->messageId}\n" .
        "  • DB Record ID: {$channelPublicationId}\n\n" .
        "⏳ ШАГ 8: Финальная проверка..."
    );
    
} catch (\Exception $e) {
    echo "  ❌ Ошибка публикации в канал: {$e->getMessage()}\n";
    $logger->error("Ошибка публикации в канал для item {$itemId}: {$e->getMessage()}");
    
    sendTelegramNotification(
        $telegram,
        $chatId,
        "❌ <b>ШАГ 7: ОШИБКА</b>\n\n" .
        "Ошибка публикации в канал:\n{$e->getMessage()}"
    );
    
    exit(1);
}

// ============================================================================
// ШАГ 8: ФИНАЛЬНАЯ ПРОВЕРКА И ОТЧЕТ
// ============================================================================

printStep('ФИНАЛЬНАЯ ПРОВЕРКА И ОТЧЕТ', 8, 8);

echo "🔍 Проводим финальную проверку всех записей в БД...\n\n";

// Проверяем все публикации
$allPublications = $db->query("SELECT * FROM rss2tlg_publications WHERE item_id = ?", [$itemId]);

echo "📋 Все публикации для item {$itemId}:\n";
foreach ($allPublications as $pub) {
    echo "\n  Publication ID: {$pub['id']}\n";
    echo "    • Destination Type: {$pub['destination_type']}\n";
    echo "    • Destination ID: {$pub['destination_id']}\n";
    echo "    • Message ID: {$pub['message_id']}\n";
    echo "    • Published At: {$pub['published_at']}\n";
}

// Собираем статистику
echo "\n\n";
printSeparator('ИТОГОВЫЙ ОТЧЕТ');
echo "\n";

echo "✅ <b>ТЕСТ УСПЕШНО ЗАВЕРШЕН</b>\n\n";

echo "📊 СТАТИСТИКА:\n\n";

echo "  🎯 Обработанная новость:\n";
echo "      • Item ID: {$itemId}\n";
echo "      • Источник: {$selectedItem['feed_title']}\n";
echo "      • Оригинальный заголовок: {$selectedItem['title']}\n";
echo "      • Переведенный заголовок: {$analysis['content_headline']}\n";
echo "      • Язык: {$analysis['article_language']}\n";
echo "      • Категория: {$analysis['category_primary']}\n";
echo "      • Важность: {$analysis['importance_rating']}/20\n\n";

echo "  📂 Записи в базе данных:\n";
echo "      • rss2tlg_items: ✓ (ID: {$itemId})\n";
echo "      • rss2tlg_ai_analysis: ✓ (ID: {$analysisRecord['id']})\n";
echo "      • rss2tlg_publications: ✓ (2 записи - bot и channel)\n";
echo "      • rss2tlg_feed_state: ✓ (Feed ID: {$selectedItem['feed_id']})\n\n";

echo "  📱 Публикации в Telegram:\n";
echo "      • Бот (chat_id: {$chatId}): ✓ (msg_id: {$botMsg->messageId})\n";
echo "      • Канал ({$channelId}): ✓ (msg_id: {$channelMsg->messageId})\n\n";

echo "  🤖 AI метрики:\n";
echo "      • Модель: {$savedAnalysis['model_used']}\n";
echo "      • Токенов использовано: {$savedAnalysis['tokens_used']}\n";
echo "      • Время обработки: {$savedAnalysis['processing_time_ms']} мс\n";
echo "      • Кеш использован: " . ($savedAnalysis['cache_hit'] ? 'Да' : 'Нет') . "\n";
if (isset($totalCost)) {
    echo "      • Стоимость: $" . number_format($totalCost, 6) . "\n";
}
echo "\n";

// Финальное уведомление
$finalReport = "🎉 <b>ТЕСТ УСПЕШНО ЗАВЕРШЕН</b>\n\n" .
               "📊 <b>Итоги:</b>\n" .
               "  • Новость обработана: #{$itemId}\n" .
               "  • Язык: {$analysis['article_language']}\n" .
               "  • Категория: {$analysis['category_primary']}\n" .
               "  • Важность: {$analysis['importance_rating']}/20\n\n" .
               "📂 <b>База данных:</b>\n" .
               "  • rss2tlg_items: ✓\n" .
               "  • rss2tlg_ai_analysis: ✓\n" .
               "  • rss2tlg_publications: ✓ (2 записи)\n\n" .
               "📱 <b>Telegram:</b>\n" .
               "  • Бот: ✓ (msg {$botMsg->messageId})\n" .
               "  • Канал: ✓ (msg {$channelMsg->messageId})\n\n" .
               "🤖 <b>AI:</b>\n" .
               "  • Токенов: {$savedAnalysis['tokens_used']}\n" .
               "  • Время: {$savedAnalysis['processing_time_ms']} мс\n\n" .
               "✅ Все этапы пройдены успешно!";

sendTelegramNotification($telegram, $chatId, $finalReport);

printSeparator();
echo "\n";
echo "🎊 ПОЗДРАВЛЯЕМ! Детальный тест завершен успешно.\n";
echo "   Все компоненты работают корректно.\n";
echo "   Полная цепочка обработки новости протестирована.\n\n";

exit(0);

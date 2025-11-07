<?php

declare(strict_types=1);

/**
 * E2E Тест Telegram публикаций и AI анализа RSS2TLG
 * 
 * Цепочка тестирования:
 * 1. Загрузка существующих новостей из БД
 * 2. AI анализ новостей через OpenRouter
 * 3. Публикация в Telegram бот
 * 4. Публикация в Telegram канал
 * 5. Отправка отчета статистики
 */

use Cache\FileCache;
use App\Component\Http;
use App\Component\Logger;
use App\Component\MySQL;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\OpenRouter;
use App\Rss2Tlg\ItemRepository;
use App\Rss2Tlg\PublicationRepository;
use App\Rss2Tlg\AIAnalysisService;
use App\Rss2Tlg\AIAnalysisRepository;
use App\Rss2Tlg\ContentExtractorService;
use App\Rss2Tlg\PromptManager;
use App\Component\WebtExtractor;

// Autoload
require_once __DIR__ . '/autoload.php';

echo "\n╔════════════════════════════════════════════════════════════════════════════════╗\n";
echo "║              E2E Тест Telegram + AI RSS2TLG v1.0                              ║\n";
echo "║         Тестирование: OpenRouter AI + Telegram Bot + Channel                 ║\n";
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
        'filename' => 'rss2tlg_telegram_ai_test.log',
        'format' => '{timestamp} {level} {message}',
        'max_file_size' => 10485760,  // 10MB
        'max_files' => 5
    ];
    $logger = new Logger($loggerConfig);
    echo "  ✅ Logger инициализирован\n\n";

    // 1.2 MySQL Connection
    echo "  ⏳ Инициализация MySQL...\n";
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

    // 1.4 OpenRouter AI
    echo "  ⏳ Инициализация OpenRouter...\n";
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
    echo "  ✅ OpenRouter инициализирован\n\n";

    // 1.5 Telegram API
    echo "  ⏳ Инициализация Telegram API...\n";
    $telegramToken = '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI';
    $telegramAPI = new TelegramAPI($telegramToken, $http, $logger);
    echo "  ✅ Telegram API инициализирован\n\n";

    // 1.6 Webt Extractor
    echo "  ⏳ Инициализация Webt Extractor...\n";
    $extractorConfig = [
        'readability' => true,
        'timeout' => 30,
        'user_agent' => 'Mozilla/5.0 (compatible; Rss2Tlg/2.0)',
        'max_content_size' => 100000
    ];
    $extractor = new WebtExtractor($extractorConfig, $logger);
    echo "  ✅ Webt Extractor инициализирован\n\n";

    // 1.7 PromptManager
    echo "  ⏳ Инициализация PromptManager...\n";
    $promptsDirectory = __DIR__ . '/config/prompts';
    if (!is_dir($promptsDirectory)) {
        mkdir($promptsDirectory, 0755, true);
        // Создаем базовый промпт
        $basicPrompt = "Проанализируй новость и определи:
1. Категорию (technology, business, politics, science, sports, entertainment, other)
2. Важность (low, medium, high)
3. Краткую сводку (1-2 предложения)
4. Ключевые теги (3-5 штук)

Ответ в формате JSON:
{
    \"category_primary\": \"technology\",
    \"importance_rating\": \"medium\",
    \"summary\": \"Краткая сводка новости\",
    \"tags\": [\"тег1\", \"тег2\", \"тег3\"]
}";
        file_put_contents($promptsDirectory . '/INoT_v1.txt', $basicPrompt);
    }
    $promptManager = new PromptManager($promptsDirectory, $logger);
    echo "  ✅ PromptManager инициализирован\n\n";

    // 1.8 Repositories
    $itemRepository = new ItemRepository($db, $logger);
    $publicationRepository = new PublicationRepository($db, $logger);
    $aiAnalysisRepository = new AIAnalysisRepository($db, $logger);
    $contentExtractorService = new ContentExtractorService($itemRepository, $extractor, $logger);
    $aiAnalysisService = new AIAnalysisService($promptManager, $aiAnalysisRepository, $openRouter, $db, $logger);

    echo "  ✅ Все репозитории инициализированы\n\n";

} catch (\Exception $e) {
    echo "  ❌ ОШИБКА инициализации: " . $e->getMessage() . "\n";
    exit(1);
}

// ============================================================================
// ЭТАП 2: Загрузка новостей из БД
// ============================================================================

echo "📰 ЭТАП 2: Загрузка новостей из БД\n";
echo "─────────────────────────────────────────────────────────────────────────────────\n\n";

try {
    echo "  ⏳ Загрузка 5 последних новостей для теста...\n";
    
    $items = $db->query("
        SELECT id, feed_id, title, link, description, content, pub_date 
        FROM rss2tlg_items 
        WHERE is_published = 0 
        ORDER BY pub_date DESC 
        LIMIT 5
    ");
    
    if (empty($items)) {
        echo "  ⚠️ Неопубликованных новостей не найдено\n";
        exit(0);
    }
    
    echo "  ✅ Загружено новостей: " . count($items) . "\n\n";
    
    foreach ($items as $idx => $item) {
        echo "  📝 [$idx] {$item['title']}\n";
        echo "      Link: {$item['link']}\n";
        echo "      Pub Date: {$item['pub_date']}\n\n";
    }
    
} catch (\Exception $e) {
    echo "  ❌ ОШИБКА загрузки новостей: " . $e->getMessage() . "\n";
    exit(1);
}

// ============================================================================
// ЭТАП 3: AI анализ новостей
// ============================================================================

echo "🤖 ЭТАП 3: AI анализ новостей\n";
echo "─────────────────────────────────────────────────────────────────────────────────\n\n";

$aiResults = [];
$aiErrors = 0;

try {
    echo "  ⏳ Запуск AI анализа для " . count($items) . " новостей...\n\n";
    
    foreach ($items as $idx => $item) {
        echo "  🔍 Анализ новости #$idx: " . substr($item['title'], 0, 50) . "...\n";
        
        try {
            // Подготовка данных новости для анализа
            $itemData = [
                'id' => $item['id'],
                'feed_id' => $item['feed_id'] ?? 1, // Добавляем feed_id
                'title' => $item['title'],
                'content' => $item['content'] ?: $item['description'],
                'link' => $item['link'],
                'pub_date' => $item['pub_date']
            ];
            
            // AI анализ
            $analysisResult = $aiAnalysisService->analyzeWithFallback(
                $itemData,
                'INoT_v1',
                ['deepseek/deepseek-chat-v3.1']
            );
            
            if ($analysisResult !== null && isset($analysisResult['status']) && $analysisResult['status'] === 'completed') {
                echo "     ✅ Анализ завершен успешно\n";
                echo "     📊 Токенов использовано: " . ($analysisResult['tokens_used'] ?? 'N/A') . "\n";
                
                // Сохранение результата
                $aiAnalysisRepository->save(
                    $item['id'],
                    'INoT_v1',
                    $analysisResult,
                    $analysisResult['category_primary'] ?? 'other',
                    $analysisResult['importance_rating'] ?? 'medium',
                    [],
                    $analysisResult['tokens_used'] ?? 0,
                    $analysisResult['processing_time_ms'] ?? 0,
                    $analysisResult['model_used'] ?? 'unknown',
                    $analysisResult['cache_hit'] ?? false
                );
                
                $aiResults[] = [
                    'item' => $item,
                    'analysis' => $analysisResult
                ];
                
            } else {
                echo "     ❌ Анализ не удался: " . ($analysisResult['error'] ?? 'Unknown error') . "\n";
                $aiErrors++;
            }
            
        } catch (\Exception $e) {
            echo "     ❌ Ошибка AI анализа: " . $e->getMessage() . "\n";
            $aiErrors++;
        }
        
        echo "\n";
    }
    
    echo "  📊 Результаты AI анализа:\n";
    echo "     ✅ Успешно: " . count($aiResults) . "\n";
    echo "     ❌ Ошибок: $aiErrors\n\n";
    
} catch (\Exception $e) {
    echo "  ❌ ОШИБКА AI анализа: " . $e->getMessage() . "\n";
}

// ============================================================================
// ЭТАП 4: Публикация в Telegram
// ============================================================================

echo "📤 ЭТАП 4: Публикация в Telegram\n";
echo "─────────────────────────────────────────────────────────────────────────────────\n\n";

$botPublications = 0;
$channelPublications = 0;
$publicationErrors = 0;

try {
    echo "  ⏳ Публикация в Telegram бот (chat_id: 366442475)...\n\n";
    
    foreach ($aiResults as $idx => $result) {
        $item = $result['item'];
        $analysis = $result['analysis'];
        
        // Формирование сообщения
        $category = $analysis['category_primary'] ?? 'other';
        $importance = $analysis['importance_rating'] ?? 'medium';
        $importanceIcon = match($importance) {
            'high' => '🔴',
            'medium' => '🟡', 
            'low' => '🟢',
            default => '⚪'
        };
        
        $message = "📰 *{$item['title']}*\n\n";
        $message .= "{$importanceIcon} *Важность:* $importance\n";
        $message .= "🏷️ *Категория:* $category\n";
        $message .= "🔗 [Читать далее]({$item['link']})\n\n";
        $message .= "#RSS #Новости";
        
        try {
            // Публикация в бот
            $botMessage = $telegramAPI->sendMessage(
                366442475,
                $message,
                ['parse_mode' => 'Markdown', 'disable_web_page_preview' => false]
            );
            
            if ($botMessage && $botMessage->messageId) {
                echo "  ✅ Опубликовано в бот (message_id: {$botMessage->messageId})\n";
                
                // Сохранение публикации
                $publicationRepository->record(
                    $item['id'],
                    $item['feed_id'],
                    'bot',
                    '366442475',
                    $botMessage->messageId
                );
                
                $botPublications++;
                
                // Публикация в канал (только для важных новостей)
                if ($importance === 'high') {
                    $channelMessage = $telegramAPI->sendMessage(
                        '@kompasDaily',
                        $message,
                        ['parse_mode' => 'Markdown', 'disable_web_page_preview' => false]
                    );
                    
                    if ($channelMessage && $channelMessage->messageId) {
                        echo "     ✅ Опубликовано в канал @kompasDaily (message_id: {$channelMessage->messageId})\n";
                        
                        $publicationRepository->record(
                            $item['id'],
                            $item['feed_id'],
                            'channel',
                            '@kompasDaily',
                            $channelMessage->messageId
                        );
                        
                        $channelPublications++;
                    }
                }
            }
            
        } catch (\Exception $e) {
            echo "  ❌ Ошибка публикации: " . $e->getMessage() . "\n";
            $publicationErrors++;
        }
        
        echo "\n";
    }
    
    echo "  📊 Статистика публикаций:\n";
    echo "     ✅ В бот: $botPublications\n";
    echo "     ✅ В канал: $channelPublications\n";
    echo "     ❌ Ошибок: $publicationErrors\n\n";
    
} catch (\Exception $e) {
    echo "  ❌ ОШИБКА публикации: " . $e->getMessage() . "\n";
}

// ============================================================================
// ЭТАП 5: Итоговый отчет
// ============================================================================

echo "📋 ЭТАП 5: Итоговый отчет\n";
echo "─────────────────────────────────────────────────────────────────────────────────\n\n";

try {
    // Финальная статистика БД
    $totalItems = $db->queryScalar("SELECT COUNT(*) FROM rss2tlg_items");
    $publishedItems = $db->queryScalar("SELECT COUNT(*) FROM rss2tlg_items WHERE is_published = 1");
    $totalPublications = $db->queryScalar("SELECT COUNT(*) FROM rss2tlg_publications");
    $totalAiAnalysis = $db->queryScalar("SELECT COUNT(*) FROM rss2tlg_ai_analysis");
    
    echo "  📊 Финальная статистика:\n";
    echo "     📰 Всего новостей: $totalItems\n";
    echo "     ✅ Опубликовано: $publishedItems\n";
    echo "     📤 Публикаций: $totalPublications\n";
    echo "     🤖 AI анализов: $totalAiAnalysis\n\n";
    
    // Финальное сообщение в Telegram
    $finalMessage = "✅ *Тест Telegram + AI завершен!*\n\n" .
        "📊 *Результаты:*\n" .
        "• AI анализов: " . count($aiResults) . "\n" .
        "• Публикаций в бот: $botPublications\n" .
        "• Публикаций в канал: $channelPublications\n" .
        "• Ошибок: " . ($aiErrors + $publicationErrors) . "\n\n" .
        "📈 *Статистика БД:*\n" .
        "• Новостей всего: $totalItems\n" .
        "• Опубликовано: $publishedItems\n" .
        "• AI анализов: $totalAiAnalysis\n\n" .
        "⏰ Завершено: " . date('Y-m-d H:i:s');
    
    $telegramAPI->sendMessage(366442475, $finalMessage, ['parse_mode' => 'Markdown']);
    
} catch (\Exception $e) {
    echo "⚠️ Ошибка при отправке финального отчета: " . $e->getMessage() . "\n";
}

echo "✅ Тест Telegram + AI завершен!\n";
echo "📝 Логи: /tmp/rss2tlg_telegram_ai_test.log\n\n";

exit(0);
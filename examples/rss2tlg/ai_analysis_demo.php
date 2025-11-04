<?php

declare(strict_types=1);

/**
 * Демонстрация AI-анализа новостей через OpenRouter
 * 
 * Этот пример показывает:
 * 1. Загрузку новостей из БД
 * 2. AI-анализ через OpenRouter (DeepSeek/Qwen)
 * 3. Сохранение результатов в rss2tlg_ai_analysis
 * 4. Использование кеширования промптов
 */

require_once __DIR__ . '/../../autoload.php';

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\OpenRouter;
use App\Component\Config\ConfigLoader;
use App\Rss2Tlg\ItemRepository;
use App\Rss2Tlg\AIAnalysisService;
use App\Rss2Tlg\AIAnalysisRepository;
use App\Rss2Tlg\PromptManager;

// ============================================================================
// КОНФИГУРАЦИЯ
// ============================================================================

$configPath = __DIR__ . '/../../config/rss2tlg_stress_test.json';
$promptsDir = __DIR__ . '/../../prompts';
$logFile = __DIR__ . '/../../logs/ai_analysis_demo.log';

// Промпт для анализа
$promptId = 'INoT_v1';

// Модели для анализа (с fallback) - будут браться из конфига, но можно переопределить
$aiModels = null; // null = использовать из конфига

// ============================================================================
// ИНИЦИАЛИЗАЦИЯ КОМПОНЕНТОВ
// ============================================================================

echo "=== AI Analysis Demo ===\n\n";

// Загрузка конфигурации
$configLoader = new ConfigLoader();
$config = $configLoader->load($configPath);

// Инициализация логгера
$logger = new Logger([
    'log_file' => $logFile,
    'log_level' => Logger::LEVEL_DEBUG,
    'rotation' => true,
]);

echo "✓ Логгер инициализирован: {$logFile}\n";

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

echo "✓ Подключение к БД: {$dbConfig['database']}\n";

// Инициализация OpenRouter
$openRouterConfig = $configLoader->load(__DIR__ . '/../../config/openrouter.json');
$openRouter = new OpenRouter($openRouterConfig, $logger);

echo "✓ OpenRouter клиент инициализирован\n";

// Инициализация репозиториев и сервисов
$itemRepository = new ItemRepository($db, $logger);
$analysisRepository = new AIAnalysisRepository($db, $logger);
$promptManager = new PromptManager($promptsDir, $logger);
$analysisService = new AIAnalysisService(
    $promptManager,
    $analysisRepository,
    $openRouter,
    $db,
    $logger
);

echo "✓ Сервисы инициализированы\n\n";

// ============================================================================
// ПРОВЕРКА ПРОМПТОВ
// ============================================================================

echo "=== Доступные промпты ===\n";
$availablePrompts = $promptManager->getAvailablePrompts();
foreach ($availablePrompts as $prompt) {
    echo "  - {$prompt}\n";
}

if (!$promptManager->hasPrompt($promptId)) {
    echo "\n❌ Промпт '{$promptId}' не найден!\n";
    exit(1);
}

echo "\n✓ Используемый промпт: {$promptId}\n\n";

// ============================================================================
// ЗАГРУЗКА КОНФИГУРАЦИИ AI-АНАЛИЗА
// ============================================================================

echo "=== Конфигурация AI-анализа ===\n";

// Получаем модели из конфига (с fallback по умолчанию)
if ($aiModels === null && isset($config['ai_analysis']['models'])) {
    $aiModels = $config['ai_analysis']['models'];
}

// Если модели все еще не указаны, используем значение по умолчанию
if ($aiModels === null || empty($aiModels)) {
    $aiModels = ['deepseek/deepseek-chat-v3.1:free'];
}

echo "Модели для анализа (в порядке приоритета):\n";
foreach ($aiModels as $index => $model) {
    echo "  " . ($index + 1) . ". {$model}\n";
}
echo "\n";

// ============================================================================
// ПОЛУЧЕНИЕ НОВОСТЕЙ ДЛЯ АНАЛИЗА
// ============================================================================

echo "=== Получение новостей ===\n";

// Получаем новости, которые еще не анализировались
$pendingItems = $analysisRepository->getPendingItems(0, 5);

if (empty($pendingItems)) {
    echo "❌ Нет новостей для анализа.\n";
    echo "Сначала выполните fetch новостей: php examples/rss2tlg/fetch_example.php\n";
    exit(0);
}

echo "✓ Найдено новостей для анализа: " . count($pendingItems) . "\n\n";

// ============================================================================
// АНАЛИЗ НОВОСТЕЙ
// ============================================================================

echo "=== Анализ новостей через AI ===\n\n";

foreach ($pendingItems as $index => $item) {
    $itemId = (int)$item['id'];
    $feedId = (int)$item['feed_id'];
    $title = $item['title'];
    
    echo "--- Новость #{$itemId} (Feed #{$feedId}) ---\n";
    echo "Заголовок: " . mb_substr($title, 0, 80) . "...\n";
    echo "Модели: " . implode(', ', $aiModels) . "\n";
    echo "Промпт: {$promptId}\n\n";
    
    $startTime = microtime(true);
    
    // Выполняем анализ с fallback
    $analysis = $analysisService->analyzeWithFallback($item, $promptId, $aiModels);
    
    $processingTime = round((microtime(true) - $startTime) * 1000);
    
    if ($analysis !== null) {
        echo "✓ Анализ выполнен успешно за {$processingTime} мс\n";
        echo "  - Категория: {$analysis['category_primary']} ({$analysis['category_confidence']})\n";
        echo "  - Важность: {$analysis['importance_rating']}/20\n";
        echo "  - Заголовок: {$analysis['content_headline']}\n";
        echo "  - Язык: {$analysis['article_language']} -> {$analysis['translation_status']}\n";
        
        if ($analysis['translation_quality_score']) {
            echo "  - Качество перевода: {$analysis['translation_quality_score']}/10\n";
        }
        
        // Дедупликация данные
        if ($analysis['deduplication_data']) {
            $dedupData = json_decode($analysis['deduplication_data'], true);
            if (isset($dedupData['canonical_entities'])) {
                echo "  - Сущности: " . implode(', ', $dedupData['canonical_entities']) . "\n";
            }
        }
        
        echo "\n";
    } else {
        echo "❌ Ошибка анализа\n\n";
    }
    
    // Задержка между запросами для кеширования
    if ($index < count($pendingItems) - 1) {
        echo "⏳ Задержка 100ms...\n\n";
        usleep(100000);
    }
}

// ============================================================================
// СТАТИСТИКА
// ============================================================================

echo "=== Статистика ===\n\n";

$serviceMetrics = $analysisService->getMetrics();
echo "Сервис AI анализа:\n";
echo "  - Всего проанализировано: {$serviceMetrics['total_analyzed']}\n";
echo "  - Успешно: {$serviceMetrics['successful']}\n";
echo "  - Ошибки: {$serviceMetrics['failed']}\n";
echo "  - Всего токенов: {$serviceMetrics['total_tokens']}\n";
echo "  - Среднее время: " . round($serviceMetrics['total_time_ms'] / max(1, $serviceMetrics['total_analyzed'])) . " мс\n";
echo "  - Cache hits: {$serviceMetrics['cache_hits']}\n";
echo "  - Fallback использований: {$serviceMetrics['fallback_used']}\n";

if (!empty($serviceMetrics['model_attempts'])) {
    echo "  - Попытки по моделям:\n";
    foreach ($serviceMetrics['model_attempts'] as $model => $attempts) {
        echo "    * {$model}: {$attempts}\n";
    }
}
echo "\n";

$repoStats = $analysisRepository->getStats();
echo "Репозиторий анализов:\n";
echo "  - Всего записей: {$repoStats['total']}\n";
echo "  - Успешных: {$repoStats['success']}\n";
echo "  - Ошибок: {$repoStats['failed']}\n";
echo "  - В ожидании: {$repoStats['pending']}\n";
echo "  - Средняя важность: " . round($repoStats['avg_importance'] ?? 0, 1) . "/20\n";
echo "  - Среднее время обработки: " . round($repoStats['avg_processing_time_ms'] ?? 0) . " мс\n";
echo "  - Cache hits: {$repoStats['cache_hits']}\n";
echo "  - Всего токенов: {$repoStats['total_tokens']}\n\n";

// ============================================================================
// ПРИМЕРЫ ЗАПРОСОВ
// ============================================================================

echo "=== Примеры запросов к результатам ===\n\n";

// Получить самые важные новости
$importantNews = $analysisRepository->getByImportance(10, 5);
echo "Самые важные новости (рейтинг >= 10):\n";
foreach ($importantNews as $news) {
    echo "  - [{$news['importance_rating']}/20] {$news['content_headline']}\n";
}

echo "\n✓ Демонстрация завершена!\n\n";

echo "Следующие шаги:\n";
echo "1. Просмотр результатов в БД: SELECT * FROM rss2tlg_ai_analysis;\n";
echo "2. Интеграция с публикацией в Telegram\n";
echo "3. Настройка разных промптов для разных источников\n";

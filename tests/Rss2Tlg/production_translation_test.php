<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\OpenRouter;
use App\Component\Telegram;
use App\Rss2Tlg\Pipeline\TranslationService;

// ============================================================================
// PRODUCTION TEST: TranslationService
// ============================================================================
// Цель: Проверить работу модуля перевода новостей
// - Множественные переводы (1 новость → N языков)
// - Fallback механизм между моделями
// - Качество переводов
// - Telegram уведомления о ходе теста
// ============================================================================

echo "\n";
echo "╔══════════════════════════════════════════════════════════════════════════╗\n";
echo "║         PRODUCTION TEST: TranslationService (Перевод новостей)          ║\n";
echo "╚══════════════════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Telegram bot для уведомлений
$telegramConfig = [
    'token' => '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI',
    'default_chat_id' => '366442475',
    'timeout' => 30,
];
$telegram = new Telegram($telegramConfig);
$chatId = '366442475';

// Отправляем стартовое уведомление
$telegram->sendText($chatId, "🚀 <b>СТАРТ ТЕСТА: TranslationService</b>\n\nНачинаем тестирование модуля перевода новостей...", ['parse_mode' => 'HTML']);

try {
    // Настраиваем логирование
    echo "📋 Инициализация компонентов...\n";
    $loggerConfig = [
        'directory' => __DIR__ . '/../../logs',
        'file_name' => 'translation_test_' . date('Y-m-d') . '.log',
        'min_level' => 'debug',
    ];
    $logger = new Logger($loggerConfig);
    $logger->info('=== PRODUCTION TEST: TranslationService START ===');
    
    echo "✅ Logger инициализирован\n\n";
    
    // Подключаемся к БД
    echo "🗄️  Подключение к MariaDB...\n";
    $telegram->sendText($chatId, "🗄️ Подключаемся к MariaDB...");
    
    $dbConfig = [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'rss2tlg',
        'username' => 'rss2tlg_user',
        'password' => 'rss2tlg_password_2024',
        'charset' => 'utf8mb4',
    ];
    $db = new MySQL($dbConfig, $logger);
    
    echo "✅ Подключение к БД установлено\n\n";
    $telegram->sendText($chatId, "✅ Подключение к БД установлено");
    
    // Инициализируем OpenRouter
    echo "🤖 Инициализация OpenRouter API...\n";
    $openRouterConfig = [
        'api_key' => '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',
        'base_url' => 'https://openrouter.ai/api/v1',
        'timeout' => 120,
    ];
    $openRouter = new OpenRouter($openRouterConfig, $logger);
    
    echo "✅ OpenRouter API готов\n\n";
    
    // Конфигурация TranslationService
    echo "⚙️  Конфигурация TranslationService...\n";
    $translationConfig = [
        'enabled' => true,
        'target_languages' => ['ru', 'uk', 'es'],  // Переводим на русский, украинский, испанский
        'models' => [
            'anthropic/claude-3.5-sonnet',  // Primary: Claude 3.5 Sonnet
            'deepseek/deepseek-chat',       // Fallback: DeepSeek
        ],
        'retry_count' => 2,
        'timeout' => 120,
        'fallback_strategy' => 'sequential',
        'prompt_file' => __DIR__ . '/../../src/Rss2Tlg/prompts/translation_prompt.txt',
    ];
    
    echo "  - Целевые языки: " . implode(', ', $translationConfig['target_languages']) . "\n";
    echo "  - Модели: " . implode(', ', $translationConfig['models']) . "\n";
    echo "  - Retry count: {$translationConfig['retry_count']}\n";
    echo "  - Fallback: {$translationConfig['fallback_strategy']}\n";
    echo "✅ Конфигурация готова\n\n";
    
    $telegram->sendText($chatId, "⚙️ Конфигурация:\n- Языки: ru, en\n- Модели: Claude 3.5 + DeepSeek\n- Retry: 2");
    
    // Создаем сервис
    echo "🔧 Создание TranslationService...\n";
    $translationService = new TranslationService($db, $openRouter, $translationConfig, $logger);
    echo "✅ TranslationService создан\n\n";
    
    // Получаем новости для перевода (прошедшие суммаризацию и дедупликацию)
    echo "📰 Получение новостей для перевода...\n";
    $telegram->sendText($chatId, "📰 Получаем новости для перевода...");
    
    $query = "
        SELECT i.id, i.title, s.headline, s.summary, s.article_language
        FROM rss2tlg_items i
        INNER JOIN rss2tlg_summarization s ON i.id = s.item_id AND s.status = 'success'
        WHERE i.is_published = 0
            AND s.headline IS NOT NULL AND s.headline != ''
            AND s.summary IS NOT NULL AND s.summary != ''
        ORDER BY i.pub_date DESC
        LIMIT 3
    ";
    
    $items = $db->query($query);
    
    if (empty($items)) {
        echo "⚠️  Нет новостей для перевода\n";
        $telegram->sendText($chatId, "⚠️ Нет новостей для перевода");
        exit(1);
    }
    
    echo "✅ Найдено новостей: " . count($items) . "\n\n";
    $telegram->sendText($chatId, "✅ Найдено новостей: " . count($items));
    
    // Отображаем список новостей
    echo "╔═══════════════════════════════════════════════════════════════════╗\n";
    echo "║                    НОВОСТИ ДЛЯ ПЕРЕВОДА                           ║\n";
    echo "╚═══════════════════════════════════════════════════════════════════╝\n\n";
    
    foreach ($items as $idx => $item) {
        $num = $idx + 1;
        echo "{$num}. ID: {$item['id']} | Язык: {$item['article_language']}\n";
        echo "   Заголовок: " . mb_substr($item['headline'], 0, 80) . "...\n";
        echo "\n";
    }
    
    echo "\n";
    
    // Начинаем обработку
    echo "╔═══════════════════════════════════════════════════════════════════╗\n";
    echo "║                  НАЧАЛО ОБРАБОТКИ НОВОСТЕЙ                        ║\n";
    echo "╚═══════════════════════════════════════════════════════════════════╝\n\n";
    
    $telegram->sendText($chatId, "🔄 <b>НАЧАЛО ОБРАБОТКИ</b>\n\nПереводим " . count($items) . " новостей...", ['parse_mode' => 'HTML']);
    
    $startTime = microtime(true);
    $results = [];
    
    foreach ($items as $idx => $item) {
        $num = $idx + 1;
        $itemId = $item['id'];
        
        echo "\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "📝 НОВОСТЬ #{$num} (ID: {$itemId})\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "Оригинальный язык: {$item['article_language']}\n";
        echo "Заголовок: {$item['headline']}\n";
        echo "\n";
        
        $telegram->sendText($chatId, "🔄 Обрабатываем #{$num}/{" . count($items) . "}\nID: {$itemId}");
        
        $itemStartTime = microtime(true);
        
        // Переводим
        $success = $translationService->processItem($itemId);
        
        $itemTime = round((microtime(true) - $itemStartTime) * 1000);
        
        $results[$itemId] = [
            'success' => $success,
            'time_ms' => $itemTime,
            'headline' => $item['headline'],
            'language' => $item['article_language'],
        ];
        
        if ($success) {
            echo "✅ Перевод выполнен успешно\n";
            echo "⏱️  Время: {$itemTime} мс\n";
            
            // Получаем переводы
            $translationsQuery = "
                SELECT target_language, translated_headline, quality_score
                FROM rss2tlg_translation
                WHERE item_id = :item_id AND status = 'success'
            ";
            $translations = $db->query($translationsQuery, ['item_id' => $itemId]);
            
            echo "\n📋 Переводы:\n";
            foreach ($translations as $trans) {
                echo "  - {$trans['target_language']}: {$trans['translated_headline']}\n";
                echo "    Качество: {$trans['quality_score']}/10\n";
            }
            
        } else {
            echo "❌ Ошибка перевода\n";
            $telegram->sendText($chatId, "❌ Ошибка перевода новости #{$num}");
        }
    }
    
    $totalTime = round((microtime(true) - $startTime) * 1000);
    
    // Получаем метрики
    $metrics = $translationService->getMetrics();
    
    echo "\n\n";
    echo "╔═══════════════════════════════════════════════════════════════════╗\n";
    echo "║                       РЕЗУЛЬТАТЫ ТЕСТА                            ║\n";
    echo "╚═══════════════════════════════════════════════════════════════════╝\n\n";
    
    echo "📊 Метрики обработки:\n";
    echo "  - Всего обработано: {$metrics['total_processed']}\n";
    echo "  - Успешно: {$metrics['successful']}\n";
    echo "  - Ошибок: {$metrics['failed']}\n";
    echo "  - Пропущено: {$metrics['skipped']}\n";
    echo "  - Переводов создано: {$metrics['translations_created']}\n";
    echo "\n";
    
    echo "⏱️  Производительность:\n";
    echo "  - Общее время: " . round($totalTime / 1000, 2) . " сек\n";
    echo "  - Среднее время на новость: " . round($totalTime / count($items)) . " мс\n";
    echo "  - Среднее время на перевод: " . round($metrics['total_time_ms'] / max($metrics['translations_created'], 1)) . " мс\n";
    echo "\n";
    
    echo "🪙 Токены:\n";
    echo "  - Всего использовано: {$metrics['total_tokens']}\n";
    echo "  - Средне на перевод: " . round($metrics['total_tokens'] / max($metrics['translations_created'], 1)) . "\n";
    echo "\n";
    
    echo "🌍 Языки:\n";
    foreach ($metrics['languages_processed'] as $lang => $count) {
        echo "  - {$lang}: {$count} переводов\n";
    }
    echo "\n";
    
    echo "🤖 Использование моделей:\n";
    foreach ($metrics['model_attempts'] as $model => $attempts) {
        echo "  - {$model}: {$attempts} попыток\n";
    }
    echo "\n";
    
    // Проверяем качество переводов
    echo "📈 Качество переводов:\n";
    $qualityQuery = "
        SELECT target_language, AVG(quality_score) as avg_score, COUNT(*) as count
        FROM rss2tlg_translation
        WHERE status = 'success' AND item_id IN (" . implode(',', array_keys($results)) . ")
        GROUP BY target_language
    ";
    $qualityStats = $db->query($qualityQuery);
    
    foreach ($qualityStats as $stat) {
        $avgScore = round((float)$stat['avg_score'], 1);
        echo "  - {$stat['target_language']}: {$avgScore}/10 (переводов: {$stat['count']})\n";
    }
    echo "\n";
    
    // Итоговая статистика
    $successRate = round(($metrics['successful'] / $metrics['total_processed']) * 100, 1);
    
    echo "╔═══════════════════════════════════════════════════════════════════╗\n";
    echo "║                     ИТОГОВАЯ ОЦЕНКА                               ║\n";
    echo "╚═══════════════════════════════════════════════════════════════════╝\n\n";
    
    if ($successRate >= 90) {
        echo "🎉 ОТЛИЧНО! Успешность: {$successRate}%\n";
        $status = "✅ ОТЛИЧНО";
    } elseif ($successRate >= 70) {
        echo "✅ ХОРОШО! Успешность: {$successRate}%\n";
        $status = "✅ ХОРОШО";
    } else {
        echo "⚠️  ТРЕБУЕТ ВНИМАНИЯ! Успешность: {$successRate}%\n";
        $status = "⚠️ ТРЕБУЕТ ВНИМАНИЯ";
    }
    echo "\n";
    
    // Финальное уведомление в Telegram
    $report = "🎯 <b>ТЕСТ ЗАВЕРШЕН: TranslationService</b>\n\n";
    $report .= "📊 <b>Результаты:</b>\n";
    $report .= "• Обработано: {$metrics['total_processed']}\n";
    $report .= "• Успешно: {$metrics['successful']}\n";
    $report .= "• Ошибок: {$metrics['failed']}\n";
    $report .= "• Переводов: {$metrics['translations_created']}\n\n";
    $report .= "⏱️ <b>Производительность:</b>\n";
    $report .= "• Общее время: " . round($totalTime / 1000, 2) . " сек\n";
    $report .= "• На новость: " . round($totalTime / count($items)) . " мс\n\n";
    $report .= "🪙 <b>Токены:</b> {$metrics['total_tokens']}\n\n";
    $report .= "🎯 <b>Статус:</b> {$status}\n";
    $report .= "🎯 <b>Успешность:</b> {$successRate}%";
    
    $telegram->sendText($chatId, $report, ['parse_mode' => 'HTML']);
    
    $logger->info('=== PRODUCTION TEST: TranslationService END ===', [
        'metrics' => $metrics,
        'success_rate' => $successRate,
    ]);
    
    echo "✅ Тест завершен успешно!\n\n";
    
} catch (Exception $e) {
    echo "\n❌ КРИТИЧЕСКАЯ ОШИБКА: {$e->getMessage()}\n";
    echo "Trace: {$e->getTraceAsString()}\n\n";
    
    $telegram->sendText($chatId, "❌ <b>ОШИБКА ТЕСТА</b>\n\n{$e->getMessage()}", ['parse_mode' => 'HTML']);
    
    if (isset($logger)) {
        $logger->error('Production test failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
    
    exit(1);
}

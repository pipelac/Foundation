<?php

declare(strict_types=1);

/**
 * Скрипт отправки итогового уведомления о завершении Этапа 1
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Component\Telegram;
use App\Component\Logger;
use App\Config\ConfigLoader;

try {
    // Загрузка конфигурации
    $configLoader = new ConfigLoader();
    $telegramConfig = $configLoader->load(__DIR__ . '/configs/telegram.json');
    
    // Настройка логгера
    $loggerConfig = [
        'directory' => __DIR__ . '/../logs',
        'file_name' => 'metrics_notification',
        'min_level' => 'debug',
    ];
    $logger = new Logger($loggerConfig);
    
    // Инициализация Telegram
    $telegram = new Telegram($telegramConfig, $logger);
    
    // Формируем итоговое сообщение
    $message = "📊 <b>ИТОГИ: Детальное хранение метрик OpenRouter - Этап 1</b>\n\n";
    
    $message .= "🎯 <b>Цель достигнута!</b>\n";
    $message .= "Реализована полная система хранения детальных метрик OpenRouter для аналитики.\n\n";
    
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $message .= "📦 <b>Созданные компоненты:</b>\n\n";
    
    $message .= "1️⃣ <b>SQL миграция</b>\n";
    $message .= "   📄 <code>migration_openrouter_metrics.sql</code>\n";
    $message .= "   • Таблица: openrouter_metrics\n";
    $message .= "   • 23 поля данных\n";
    $message .= "   • 7 индексов\n";
    $message .= "   • Поддержка JSON\n\n";
    
    $message .= "2️⃣ <b>OpenRouter.class.php</b>\n";
    $message .= "   ✨ <code>parseDetailedMetrics()</code>\n";
    $message .= "   • Парсинг всех метрик из API\n";
    $message .= "   • Временные метрики\n";
    $message .= "   • Токены (prompt, completion, cached, reasoning)\n";
    $message .= "   • Стоимость (usage, cache, data, file)\n";
    $message .= "   • Провайдер и статус\n\n";
    
    $message .= "3️⃣ <b>AIAnalysisTrait.php</b>\n";
    $message .= "   ✨ <code>recordDetailedMetrics()</code>\n";
    $message .= "   • Запись в БД\n";
    $message .= "   • Поддержка контекста\n\n";
    $message .= "   ✨ <code>getDetailedMetrics()</code>\n";
    $message .= "   • Фильтрация по 7 параметрам\n";
    $message .= "   • Гибкие запросы\n\n";
    $message .= "   ✨ <code>setMetricsDb()</code>\n";
    $message .= "   • Опциональная интеграция\n\n";
    
    $message .= "4️⃣ <b>Документация</b>\n";
    $message .= "   📚 <code>OPENROUTER_METRICS_STAGE1_README.md</code>\n";
    $message .= "   • Полная документация (100+ строк)\n";
    $message .= "   • Примеры использования\n";
    $message .= "   • SQL запросы\n\n";
    $message .= "   📚 <code>OPENROUTER_METRICS_ROADMAP.md</code>\n";
    $message .= "   • План этапов 2 и 3\n";
    $message .= "   • Спецификации методов\n\n";
    
    $message .= "5️⃣ <b>Скрипты</b>\n";
    $message .= "   🔧 <code>apply_metrics_migration.php</code>\n";
    $message .= "   • Применение миграции\n";
    $message .= "   • Проверка структуры\n\n";
    
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $message .= "💾 <b>Что хранится в БД:</b>\n\n";
    $message .= "🔹 <b>Идентификация</b>\n";
    $message .= "   • generation_id\n";
    $message .= "   • model\n";
    $message .= "   • provider_name\n";
    $message .= "   • created_at\n\n";
    
    $message .= "🔹 <b>Время (мс)</b>\n";
    $message .= "   • generation_time\n";
    $message .= "   • latency\n";
    $message .= "   • moderation_latency\n\n";
    
    $message .= "🔹 <b>Токены</b>\n";
    $message .= "   • tokens_prompt/completion\n";
    $message .= "   • native_tokens_*\n";
    $message .= "   • cached_tokens\n";
    $message .= "   • reasoning_tokens\n\n";
    
    $message .= "🔹 <b>Стоимость (USD)</b>\n";
    $message .= "   • usage_total\n";
    $message .= "   • usage_cache\n";
    $message .= "   • usage_data\n";
    $message .= "   • usage_file\n\n";
    
    $message .= "🔹 <b>Контекст</b>\n";
    $message .= "   • pipeline_module\n";
    $message .= "   • batch_id\n";
    $message .= "   • task_context\n";
    $message .= "   • full_response (JSON)\n\n";
    
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $message .= "🚀 <b>Автоматическая интеграция:</b>\n\n";
    $message .= "Все Pipeline модули теперь автоматически\n";
    $message .= "записывают детальные метрики при каждом\n";
    $message .= "AI запросе через <code>analyzeWithFallback()</code>!\n\n";
    
    $message .= "Требуется только добавить в конструктор:\n";
    $message .= "<code>\$this->setMetricsDb(\$db);</code>\n\n";
    
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $message .= "📊 <b>Примеры использования:</b>\n\n";
    
    $message .= "1️⃣ <b>Получение метрик за день</b>\n";
    $message .= "<code>\$metrics = \$this->getDetailedMetrics([\n";
    $message .= "    'date_from' => '2025-01-10',\n";
    $message .= "    'limit' => 500\n";
    $message .= "]);</code>\n\n";
    
    $message .= "2️⃣ <b>SQL: Общая стоимость</b>\n";
    $message .= "<code>SELECT SUM(usage_total), COUNT(*)\n";
    $message .= "FROM openrouter_metrics\n";
    $message .= "WHERE DATE(recorded_at) = '2025-01-10';</code>\n\n";
    
    $message .= "3️⃣ <b>SQL: Эффективность кеша</b>\n";
    $message .= "<code>SELECT model,\n";
    $message .= "  SUM(native_tokens_cached) / SUM(tokens_prompt) * 100\n";
    $message .= "FROM openrouter_metrics\n";
    $message .= "GROUP BY model;</code>\n\n";
    
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $message .= "🔜 <b>Следующие этапы (по запросу):</b>\n\n";
    $message .= "<b>Этап 2:</b>\n";
    $message .= "   • getSummaryByPeriod()\n";
    $message .= "   • getSummaryByModel()\n\n";
    
    $message .= "<b>Этап 3:</b>\n";
    $message .= "   • getCacheAnalytics()\n";
    $message .= "   • getDetailReport()\n\n";
    
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    $message .= "✅ <b>Статус: ЭТАП 1 ЗАВЕРШЕН</b>\n";
    $message .= "🎉 Готово к использованию!\n\n";
    
    $message .= "📝 <b>Действия:</b>\n";
    $message .= "1. Применить SQL миграцию\n";
    $message .= "2. Добавить setMetricsDb() в модули\n";
    $message .= "3. Наслаждаться аналитикой! 🚀\n\n";
    
    $message .= "📂 <b>Файлы:</b>\n";
    $message .= "• <code>src/BaseUtils/OpenRouter.class.php</code>\n";
    $message .= "• <code>src/Rss2Tlg/Pipeline/AIAnalysisTrait.php</code>\n";
    $message .= "• <code>production/sql/migration_openrouter_metrics.sql</code>\n";
    $message .= "• <code>docs/Rss2Tlg/OPENROUTER_METRICS_*.md</code>\n";
    
    // Отправляем уведомление
    $telegram->sendText($telegramConfig['default_chat_id'], $message, [
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ]);
    
    echo "✅ Итоговое уведомление отправлено в Telegram!\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка отправки уведомления: " . $e->getMessage() . "\n";
    exit(1);
}

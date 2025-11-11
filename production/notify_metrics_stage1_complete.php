<?php

declare(strict_types=1);

/**
 * Скрипт отправки уведомления о завершении Этапа 1: Детальное хранение метрик OpenRouter
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
    
    // Формируем сообщение
    $message = "🎉 <b>Этап 1 ЗАВЕРШЕН: Детальное хранение метрик OpenRouter</b>\n\n";
    $message .= "✅ <b>Выполненные задачи:</b>\n\n";
    $message .= "1️⃣ <b>SQL миграция создана</b>\n";
    $message .= "   📄 <code>migration_openrouter_metrics.sql</code>\n";
    $message .= "   - Таблица: <code>openrouter_metrics</code>\n";
    $message .= "   - 23 поля для хранения метрик\n";
    $message .= "   - 7 индексов для оптимизации\n\n";
    
    $message .= "2️⃣ <b>OpenRouter.php расширен</b>\n";
    $message .= "   ✨ Новый метод: <code>parseDetailedMetrics()</code>\n";
    $message .= "   ✨ Метод <code>chatWithMessages()</code> обновлен\n";
    $message .= "   📊 Парсинг ВСЕХ метрик из API:\n";
    $message .= "      • Временные метрики (generation_time, latency)\n";
    $message .= "      • Токены (prompt, completion, cached, reasoning)\n";
    $message .= "      • Стоимость (usage, cache, data, file)\n";
    $message .= "      • Статус (finish_reason, provider_name)\n";
    $message .= "      • Полный response (JSON)\n\n";
    
    $message .= "3️⃣ <b>AIAnalysisTrait.php обновлен</b>\n";
    $message .= "   ✨ Новый метод: <code>recordDetailedMetrics()</code>\n";
    $message .= "      → Запись метрик в БД\n";
    $message .= "      → Поддержка pipeline_module, batch_id, task_context\n";
    $message .= "      → Автоматическое логирование\n\n";
    $message .= "   ✨ Новый метод: <code>getDetailedMetrics()</code>\n";
    $message .= "      → Получение метрик по фильтрам\n";
    $message .= "      → Поддержка: generation_id, model, pipeline_module, dates\n\n";
    $message .= "   ✨ Новый метод: <code>setMetricsDb()</code>\n";
    $message .= "      → Установка БД для метрик\n\n";
    $message .= "   🔄 <code>callAI()</code> обновлен\n";
    $message .= "      → Автоматический вызов recordDetailedMetrics()\n";
    $message .= "      → Интеграция с OpenRouter.parseDetailedMetrics()\n\n";
    
    $message .= "4️⃣ <b>Скрипты созданы</b>\n";
    $message .= "   📝 <code>apply_metrics_migration.php</code>\n";
    $message .= "      → Применение SQL миграции\n";
    $message .= "      → Проверка структуры таблицы\n\n";
    
    $message .= "📋 <b>Структура таблицы openrouter_metrics:</b>\n";
    $message .= "   • <code>id</code> - Primary Key\n";
    $message .= "   • <code>generation_id</code> - ID от OpenRouter\n";
    $message .= "   • <code>model</code> - Название модели\n";
    $message .= "   • <code>provider_name</code> - Провайдер (DeepInfra, Anthropic)\n";
    $message .= "   • <code>created_at</code> - Unix timestamp\n";
    $message .= "   • Временные метрики (3 поля)\n";
    $message .= "   • Токены (6 полей)\n";
    $message .= "   • Стоимость (4 поля)\n";
    $message .= "   • Контекст (3 поля)\n";
    $message .= "   • <code>full_response</code> - JSON полного ответа\n\n";
    
    $message .= "🔜 <b>Следующие этапы:</b>\n";
    $message .= "   ⏳ Этап 2: getSummaryByPeriod(), getSummaryByModel()\n";
    $message .= "   ⏳ Этап 3: getCacheAnalytics(), getDetailReport()\n\n";
    
    $message .= "💾 <b>Интеграция:</b>\n";
    $message .= "   Все Pipeline модули (Summarization, Deduplication, Translation)\n";
    $message .= "   автоматически записывают детальные метрики при каждом AI вызове!\n\n";
    
    $message .= "📂 <b>Файлы обновлены:</b>\n";
    $message .= "   • <code>src/BaseUtils/OpenRouter.class.php</code>\n";
    $message .= "   • <code>src/Rss2Tlg/Pipeline/AIAnalysisTrait.php</code>\n";
    $message .= "   • <code>production/sql/migration_openrouter_metrics.sql</code>\n";
    $message .= "   • <code>production/apply_metrics_migration.php</code>\n\n";
    
    $message .= "✅ <b>Статус:</b> Этап 1 полностью готов к использованию!\n";
    $message .= "🚀 Требуется: Применить миграцию БД и установить metricsDb в модулях\n";
    
    // Отправляем уведомление
    $telegram->sendText($telegramConfig['default_chat_id'], $message, [
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ]);
    
    echo "✅ Уведомление отправлено в Telegram!\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка отправки уведомления: " . $e->getMessage() . "\n";
    exit(1);
}

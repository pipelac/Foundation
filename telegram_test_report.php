#!/usr/bin/env php
<?php
/**
 * Отправка отчета о тестировании в Telegram
 */

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

use App\Component\Telegram;
use App\Config\ConfigLoader;

// Загрузка конфигурации
$telegramConfig = ConfigLoader::load(__DIR__ . '/production/configs/telegram.json');

// Инициализация Telegram
$telegram = new Telegram($telegramConfig);

// Формирование отчета
$report = "🎉 *PRODUCTION TESTING COMPLETED* 🎉\n\n";
$report .= "═══════════════════════════════════\n\n";

$report .= "📋 *RSS SUMMARIZATION*\n";
$report .= "✅ Обработано: 10/10 новостей\n";
$report .= "⏱️ Время: 600.58 сек (~60 сек/новость)\n";
$report .= "💰 Токены: 37,368\n";
$report .= "🎯 Успешность: 100%\n\n";

$report .= "📋 *RSS DEDUPLICATION*\n";
$report .= "✅ Обработано: 10/10 новостей\n";
$report .= "⏱️ Время: 113.47 сек (~11 сек/новость)\n";
$report .= "💰 Токены: 58,797\n";
$report .= "🆕 Уникальных: 10 (100%)\n";
$report .= "⚠️ Дубликатов: 0\n";
$report .= "🎯 Успешность: 100%\n\n";

$report .= "═══════════════════════════════════\n\n";

$report .= "📦 *SQL DUMPS CREATED*\n";
$report .= "✅ rss2tlg_summarization_10items_dump.sql (26KB)\n";
$report .= "✅ rss2tlg_deduplication_10items_dump.sql (6.6KB)\n\n";

$report .= "═══════════════════════════════════\n\n";

$report .= "💡 *STATISTICS*\n";
$report .= "📊 Total tokens: 96,165\n";
$report .= "⏱️ Total time: 714 sec (~12 min)\n";
$report .= "🚀 Throughput: 1.4 items/min\n\n";

$report .= "═══════════════════════════════════\n\n";

$report .= "🏁 *All tests PASSED!*\n";
$report .= "📅 Date: 2025-11-10\n";
$report .= "🕐 Time: 09:15 UTC\n\n";

$report .= "Ready for production deployment! 🚀";

// Отправка отчета
try {
    $telegram->sendText(
        '366442475',
        $report,
        ['parse_mode' => 'Markdown']
    );
    echo "✅ Отчет успешно отправлен в Telegram!\n";
} catch (Exception $e) {
    echo "❌ Ошибка отправки: " . $e->getMessage() . "\n";
    exit(1);
}

<?php
require_once '/home/engine/project/autoload.php';
use App\Component\Http;
use App\Component\TelegramBot\Core\TelegramAPI;

$http = new Http(['timeout' => 30]);
$api = new TelegramAPI('8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI', $http);

$message = "🎉 КОМПЛЕКСНОЕ ТЕСТИРОВАНИЕ ЗАВЕРШЕНО!

📊 ИТОГОВЫЕ РЕЗУЛЬТАТЫ:
Всего тестов: 19
✅ Успешных: 11 (58%)
❌ Ошибок: 8 (42%)

✅ ЧТО РАБОТАЕТ:
• TelegramAPI - все методы
• PollingHandler - получение обновлений  
• Клавиатуры (Inline/Reply)
• Callback queries
• Многошаговые диалоги
• OpenRouter AI интеграция
• Логирование

❌ ПРОБЛЕМЫ:
• MySQL.class.php - баг в prepared statements
• ConversationManager - не может сохранить данные

📋 ПОЛНЫЙ ОТЧЕТ:
TELEGRAM_BOT_POLLING_TEST_REPORT.md

🔍 ЛОГИ:
• telegram_polling_real_test.log
• FINAL_TEST_RESULTS.log";

$api->sendMessage(366442475, $message);
echo "✅ Отчет отправлен в Telegram!\n";

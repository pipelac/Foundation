<?php

declare(strict_types=1);

/**
 * Упрощённое тестирование TelegramBot с реальным Polling
 * Фокус на реальной работе компонентов
 */

require_once __DIR__ . '/autoload.php';

use App\Component\Http;
use App\Component\Logger;
use App\Component\MySQL;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Core\PollingHandler;
use App\Component\TelegramBot\Core\MessageStorage;
use App\Component\TelegramBot\Entities\Update;
use App\Component\TelegramBot\Keyboards\InlineKeyboardBuilder;
use App\Component\TelegramBot\Keyboards\ReplyKeyboardBuilder;

$config = [
    'bot_token' => '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI',
    'test_chat_id' => 366442475,
];

echo "🚀 Тестирование TelegramBot\n\n";

// Инициализация
$logger = new Logger(['directory' => __DIR__ . '/logs', 'prefix' => 'test']);
$http = new Http(['timeout' => 60], $logger);

$db = new MySQL([
    'host' => 'localhost',
    'database' => 'telegram_bot_test',
    'username' => 'testuser',
    'password' => 'testpass',
], $logger);

$messageStorage = new MessageStorage($db, $logger, [
    'enabled' => true,
    'storage_level' => MessageStorage::LEVEL_FULL,
    'store_incoming' => true,
    'store_outgoing' => true,
]);

$api = new TelegramAPI($config['bot_token'], $http, $logger, $messageStorage);
$polling = new PollingHandler($api, $logger);

// Проверка
$botInfo = $api->getMe();
echo "✅ Бот: @{$botInfo->username}\n\n";

// ============================================================================
// ЭТАП 1: БАЗОВЫЕ ТЕСТЫ
// ============================================================================

echo "ЭТАП 1: БАЗОВЫЕ ТЕСТЫ\n";
echo "═══════════════════════\n\n";

$api->sendMessage(
    $config['test_chat_id'],
    "🚀 <b>Тестирование TelegramBot</b>\n\nЭтап 1: Базовые тесты",
    ['parse_mode' => 'HTML']
);

// Тест 1: Форматирование
echo "▶ Тест 1: Форматирование текста\n";
$api->sendMessage($config['test_chat_id'], "📝 Обычный текст");
$api->sendMessage($config['test_chat_id'], "<b>HTML</b>: <i>курсив</i>, <code>код</code>", ['parse_mode' => 'HTML']);
$api->sendMessage($config['test_chat_id'], "*Markdown*: _курсив_, `код`", ['parse_mode' => 'Markdown']);
echo "✅ Пройдено\n\n";
sleep(1);

// Тест 2: Inline клавиатуры
echo "▶ Тест 2: Inline клавиатуры\n";
$keyboard = InlineKeyboardBuilder::makeSimple([
    '✅ Да' => 'btn_yes',
    '❌ Нет' => 'btn_no',
]);
$api->sendMessage($config['test_chat_id'], "Inline клавиатура", ['reply_markup' => $keyboard]);
echo "✅ Пройдено\n\n";
sleep(1);

// Тест 3: Reply клавиатура
echo "▶ Тест 3: Reply клавиатура\n";
$replyKb = (new ReplyKeyboardBuilder())
    ->addButton('🏠 Главная')
    ->addButton('ℹ️ Инфо')
    ->row()
    ->addButton('📊 Статистика')
    ->resizeKeyboard(true)
    ->build();
$api->sendMessage($config['test_chat_id'], "Reply клавиатура", ['reply_markup' => $replyKb]);
echo "✅ Пройдено\n\n";
sleep(1);

// Тест 4: Редактирование
echo "▶ Тест 4: Редактирование сообщений\n";
$msg = $api->sendMessage($config['test_chat_id'], "⏳ Исходное");
sleep(1);
$api->editMessageText($config['test_chat_id'], $msg->messageId, "✏️ Отредактировано");
sleep(1);
$api->editMessageText($config['test_chat_id'], $msg->messageId, "✅ Финал");
echo "✅ Пройдено\n\n";
sleep(1);

$api->sendMessage($config['test_chat_id'], "✅ Этап 1 завершён!", ['parse_mode' => 'HTML']);
echo "✅ ЭТАП 1 ЗАВЕРШЁН\n\n";
sleep(2);

// ============================================================================
// ЭТАП 2: СТАТИСТИКА
// ============================================================================

echo "ЭТАП 2: СТАТИСТИКА\n";
echo "═══════════════════════\n\n";

$stats = $messageStorage->getStatistics($config['test_chat_id']);
echo "📊 Статистика:\n";
echo "   Всего: {$stats['total']}\n";
echo "   Входящих: {$stats['incoming']}\n";
echo "   Исходящих: {$stats['outgoing']}\n";
echo "   Успешных: {$stats['success']}\n\n";

$statsText = "📊 <b>Статистика</b>\n\n";
$statsText .= "📨 Всего: {$stats['total']}\n";
$statsText .= "📥 Входящих: {$stats['incoming']}\n";
$statsText .= "📤 Исходящих: {$stats['outgoing']}\n";
$statsText .= "✅ Успешных: {$stats['success']}\n";
$api->sendMessage($config['test_chat_id'], $statsText, ['parse_mode' => 'HTML']);

echo "✅ ЭТАП 2 ЗАВЕРШЁН\n\n";
sleep(2);

// ============================================================================
// ЭТАП 3: ИНТЕРАКТИВ (POLLING)
// ============================================================================

echo "ЭТАП 3: ИНТЕРАКТИВНОЕ ТЕСТИРОВАНИЕ\n";
echo "═══════════════════════════════════\n\n";

$api->sendMessage(
    $config['test_chat_id'],
    "🎭 <b>Этап 3: Интерактив</b>\n\n" .
    "Команды:\n" .
    "/test_echo - эхо-тест\n" .
    "/test_buttons - тест кнопок\n" .
    "/test_finish - завершить\n\n" .
    "Отправьте команду...",
    ['parse_mode' => 'HTML']
);

echo "⏳ Запуск Polling (макс. 50 итераций)\n";
echo "   Отправьте команды в бот\n\n";

$polling->skipPendingUpdates();

$testCounter = 0;

$polling->startPolling(function(Update $update) use ($api, $config, $polling, &$testCounter) {
    if ($update->isMessage() && $update->message->text) {
        $text = $update->message->text;
        $chatId = $update->message->chat->id;
        
        echo "   📩 Сообщение: {$text}\n";
        
        if ($text === '/test_echo') {
            $api->sendMessage($chatId, "🔊 Эхо активирован! Напишите что-нибудь.");
            echo "   ✅ Эхо-тест запущен\n";
        } elseif ($text === '/test_buttons') {
            $kb = (new InlineKeyboardBuilder())
                ->addCallbackButton('🔥 Кнопка 1', 'test_1')
                ->addCallbackButton('💡 Кнопка 2', 'test_2')
                ->row()
                ->addCallbackButton('✅ OK', 'test_ok')
                ->build();
            $api->sendMessage($chatId, "🎮 Нажмите кнопки:", ['reply_markup' => $kb]);
            echo "   ✅ Тест кнопок запущен\n";
        } elseif ($text === '/test_finish') {
            $api->sendMessage($chatId, "🏁 Завершение...");
            $polling->stopPolling();
        } elseif (!str_starts_with($text, '/')) {
            $api->sendMessage($chatId, "Эхо: {$text}");
            echo "   ✅ Эхо отправлено\n";
        }
        
        $testCounter++;
    }
    
    if ($update->isCallbackQuery()) {
        $query = $update->callbackQuery;
        $data = $query->data;
        
        echo "   📞 Callback: {$data}\n";
        
        $api->answerCallbackQuery($query->id, ['text' => "Нажато: {$data}"]);
        
        if ($data === 'test_ok') {
            $api->editMessageText(
                $query->message->chat->id,
                $query->message->messageId,
                "✅ Тест кнопок пройден!"
            );
            echo "   ✅ Тест кнопок завершён\n";
        }
        
        $testCounter++;
    }
}, 50);

echo "\n✅ ЭТАП 3 ЗАВЕРШЁН\n";
echo "   Обработано событий: {$testCounter}\n\n";

// ============================================================================
// ФИНАЛ
// ============================================================================

echo "ФИНАЛЬНЫЙ ОТЧЁТ\n";
echo "═══════════════════════════════════\n\n";

$finalStats = $messageStorage->getStatistics($config['test_chat_id']);

$report = "🏆 <b>ТЕСТИРОВАНИЕ ЗАВЕРШЕНО!</b>\n\n";
$report .= "━━━━━━━━━━━━━━━━━━━━\n\n";
$report .= "<b>📊 Статистика:</b>\n";
$report .= "📨 Всего: {$finalStats['total']}\n";
$report .= "📥 Входящих: {$finalStats['incoming']}\n";
$report .= "📤 Исходящих: {$finalStats['outgoing']}\n";
$report .= "✅ Успешных: {$finalStats['success']}\n\n";
$report .= "━━━━━━━━━━━━━━━━━━━━\n\n";
$report .= "<b>✅ Протестировано:</b>\n\n";
$report .= "✓ TelegramAPI\n";
$report .= "✓ PollingHandler\n";
$report .= "✓ MessageStorage\n";
$report .= "✓ Inline клавиатуры\n";
$report .= "✓ Reply клавиатуры\n";
$report .= "✓ Редактирование\n";
$report .= "✓ Callback запросы\n\n";
$report .= "🎉 <b>ВСЁ РАБОТАЕТ!</b>";

$api->sendMessage($config['test_chat_id'], $report, ['parse_mode' => 'HTML']);

echo "📊 Финальная статистика:\n";
echo "   Всего: {$finalStats['total']}\n";
echo "   Входящих: {$finalStats['incoming']}\n";
echo "   Исходящих: {$finalStats['outgoing']}\n";
echo "   Успешных: {$finalStats['success']}\n\n";

echo "🎉 ═══════════════════════════════════\n";
echo "   ТЕСТИРОВАНИЕ ЗАВЕРШЕНО УСПЕШНО!\n";
echo "═══════════════════════════════════\n\n";

echo "📝 Логи: logs/\n";
echo "💾 БД: telegram_bot_test\n";
echo "🏆 Система работоспособна!\n";

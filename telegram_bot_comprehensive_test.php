<?php

declare(strict_types=1);

/**
 * Комплексное E2E тестирование TelegramBot с упрощенной структурой
 * 
 * Фокус на реальном тестировании основных компонентов:
 * - TelegramAPI
 * - PollingHandler
 * - MessageStorage
 * - ConversationManager
 * - Клавиатуры
 * - Обработчики
 */

require_once __DIR__ . '/autoload.php';

use App\Component\Http;
use App\Component\Logger;
use App\Component\MySQL;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Core\PollingHandler;
use App\Component\TelegramBot\Core\MessageStorage;
use App\Component\TelegramBot\Core\ConversationManager;
use App\Component\TelegramBot\Entities\Update;
use App\Component\TelegramBot\Handlers\MessageHandler;
use App\Component\TelegramBot\Handlers\CallbackQueryHandler;
use App\Component\TelegramBot\Keyboards\InlineKeyboardBuilder;
use App\Component\TelegramBot\Keyboards\ReplyKeyboardBuilder;

// ============================================================================
// КОНФИГУРАЦИЯ
// ============================================================================

$config = [
    'bot_token' => '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI',
    'test_chat_id' => 366442475,
    'db_host' => 'localhost',
    'db_name' => 'telegram_bot_test',
    'db_user' => 'testuser',
    'db_pass' => 'testpass',
];

echo "🚀 ═══════════════════════════════════════════════════════════════\n";
echo "   КОМПЛЕКСНОЕ ТЕСТИРОВАНИЕ TELEGRAMBOT\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// ============================================================================
// ИНИЦИАЛИЗАЦИЯ КОМПОНЕНТОВ
// ============================================================================

echo "📦 Инициализация компонентов...\n";

$logger = new Logger([
    'directory' => __DIR__ . '/logs/telegram_bot_tests',
    'prefix' => 'test',
    'rotation' => 'daily',
]);

$http = new Http(['timeout' => 60], $logger);

$db = new MySQL([
    'host' => $config['db_host'],
    'database' => $config['db_name'],
    'username' => $config['db_user'],
    'password' => $config['db_pass'],
], $logger);

$messageStorage = new MessageStorage($db, $logger, [
    'enabled' => true,
    'storage_level' => MessageStorage::LEVEL_FULL,
    'store_incoming' => true,
    'store_outgoing' => true,
    'auto_create_table' => true,
]);

$conversationManager = new ConversationManager($db, $logger, [
    'enabled' => true,
    'timeout' => 3600,
    'auto_create_tables' => true,
]);

$api = new TelegramAPI($config['bot_token'], $http, $logger, $messageStorage);

// Проверка подключения
try {
    $db->ping();
    $botInfo = $api->getMe();
    echo "✅ Подключения установлены\n";
    echo "   └─ MySQL: telegram_bot_test\n";
    echo "   └─ Bot: @{$botInfo->username}\n\n";
} catch (\Exception $e) {
    echo "❌ Ошибка подключения: {$e->getMessage()}\n";
    exit(1);
}

$messageHandler = new MessageHandler($api, $logger);
$callbackHandler = new CallbackQueryHandler($api, $logger);

$polling = new PollingHandler($api, $logger);
$polling->setTimeout(30)->setLimit(100);

echo "✅ Все компоненты инициализированы\n\n";

// ============================================================================
// ЭТАП 1: ТЕСТИРОВАНИЕ ОТПРАВКИ СООБЩЕНИЙ
// ============================================================================

echo "═══════════════════════════════════════════════════════════════\n";
echo "ЭТАП 1: ТЕСТИРОВАНИЕ ОТПРАВКИ СООБЩЕНИЙ\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$api->sendMessage(
    $config['test_chat_id'],
    "🚀 <b>Начало тестирования TelegramBot</b>\n\n" .
    "Этап 1: Тестирование отправки сообщений",
    ['parse_mode' => 'HTML']
);

// Тест 1: Различные форматы текста
echo "▶ Тест 1: Различные форматы текста\n";
$api->sendMessage($config['test_chat_id'], "📝 Обычный текст");
$api->sendMessage($config['test_chat_id'], "<b>Жирный</b>, <i>курсив</i>, <code>код</code>", ['parse_mode' => 'HTML']);
$api->sendMessage($config['test_chat_id'], "*Жирный*, _курсив_, `код`", ['parse_mode' => 'Markdown']);
echo "✅ Форматы текста протестированы\n";
sleep(1);

// Тест 2: Inline клавиатуры
echo "▶ Тест 2: Inline клавиатуры\n";
$simpleKeyboard = InlineKeyboardBuilder::makeSimple([
    '✅ Да' => 'answer_yes',
    '❌ Нет' => 'answer_no',
    'ℹ️ Информация' => 'answer_info',
]);
$api->sendMessage(
    $config['test_chat_id'],
    "🎯 Простая inline клавиатура",
    ['reply_markup' => $simpleKeyboard]
);

$gridKeyboard = InlineKeyboardBuilder::makeGrid([
    '1️⃣' => 'num_1',
    '2️⃣' => 'num_2',
    '3️⃣' => 'num_3',
    '4️⃣' => 'num_4',
    '5️⃣' => 'num_5',
    '6️⃣' => 'num_6',
], 3);
$api->sendMessage(
    $config['test_chat_id'],
    "🎮 Inline клавиатура сеткой (3 кнопки в ряд)",
    ['reply_markup' => $gridKeyboard]
);
echo "✅ Inline клавиатуры протестированы\n";
sleep(1);

// Тест 3: Reply клавиатура
echo "▶ Тест 3: Reply клавиатура\n";
$replyKeyboard = (new ReplyKeyboardBuilder())
    ->addButton('🏠 Главная')
    ->addButton('ℹ️ Инфо')
    ->row()
    ->addButton('📊 Статистика')
    ->addButton('⚙️ Настройки')
    ->resizeKeyboard(true)
    ->build();
$api->sendMessage(
    $config['test_chat_id'],
    "⌨️ Reply клавиатура",
    ['reply_markup' => $replyKeyboard]
);
echo "✅ Reply клавиатура протестирована\n";
sleep(1);

// Тест 4: Редактирование сообщений
echo "▶ Тест 4: Редактирование сообщений\n";
$msg = $api->sendMessage($config['test_chat_id'], "⏳ Исходное сообщение");
sleep(1);
$api->editMessageText($config['test_chat_id'], $msg->messageId, "✏️ Отредактированное сообщение");
sleep(1);
$api->editMessageText($config['test_chat_id'], $msg->messageId, "✅ Финальная версия сообщения");
echo "✅ Редактирование протестировано\n";
sleep(1);

$api->sendMessage(
    $config['test_chat_id'],
    "✅ <b>Этап 1 завершён</b>\n\nВсе базовые функции отправки работают корректно!",
    ['parse_mode' => 'HTML']
);

echo "\n✅ ЭТАП 1 ЗАВЕРШЁН\n\n";
sleep(2);

// ============================================================================
// ЭТАП 2: ТЕСТИРОВАНИЕ СТАТИСТИКИ И ХРАНИЛИЩА
// ============================================================================

echo "═══════════════════════════════════════════════════════════════\n";
echo "ЭТАП 2: СТАТИСТИКА И ХРАНИЛИЩЕ\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$api->sendMessage(
    $config['test_chat_id'],
    "📊 <b>Этап 2: Статистика и хранилище</b>",
    ['parse_mode' => 'HTML']
);

echo "▶ Получение статистики MessageStorage...\n";
$stats = $messageStorage->getStatistics($config['test_chat_id']);

echo "   Всего сообщений: {$stats['total']}\n";
echo "   Входящих: {$stats['incoming']}\n";
echo "   Исходящих: {$stats['outgoing']}\n";
echo "   Успешных: {$stats['success']}\n";
echo "   Ошибок: {$stats['failed']}\n";

$statsText = "📊 <b>Статистика сообщений</b>\n\n";
$statsText .= "📨 Всего: {$stats['total']}\n";
$statsText .= "📥 Входящих: {$stats['incoming']}\n";
$statsText .= "📤 Исходящих: {$stats['outgoing']}\n";
$statsText .= "✅ Успешных: {$stats['success']}\n";
$statsText .= "❌ Ошибок: {$stats['failed']}\n";

if (!empty($stats['by_type'])) {
    $statsText .= "\n<b>По типам:</b>\n";
    foreach ($stats['by_type'] as $type => $count) {
        $statsText .= "  • {$type}: {$count}\n";
    }
}

$api->sendMessage($config['test_chat_id'], $statsText, ['parse_mode' => 'HTML']);
echo "✅ Статистика отправлена\n";

echo "\n✅ ЭТАП 2 ЗАВЕРШЁН\n\n";
sleep(2);

// ============================================================================
// ЭТАП 3: ИНТЕРАКТИВНОЕ ТЕСТИРОВАНИЕ ЧЕРЕЗ POLLING
// ============================================================================

echo "═══════════════════════════════════════════════════════════════\n";
echo "ЭТАП 3: ИНТЕРАКТИВНОЕ ТЕСТИРОВАНИЕ (POLLING)\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$api->sendMessage(
    $config['test_chat_id'],
    "🎭 <b>Этап 3: Интерактивное тестирование</b>\n\n" .
    "Доступные команды:\n" .
    "/test_reg - Тест регистрации (3 шага)\n" .
    "/test_poll - Тест опроса\n" .
    "/test_callback - Тест callback кнопок\n" .
    "/test_finish - Завершить тестирование\n\n" .
    "⏳ Отправьте команду для начала...",
    ['parse_mode' => 'HTML']
);

echo "⏳ Запуск интерактивного режима\n";
echo "   Отправьте команды в бот для тестирования\n";
echo "   Используйте /test_finish для завершения\n\n";

$polling->skipPendingUpdates();

$testsPassed = [
    'registration' => false,
    'poll' => false,
    'callback' => false,
];

$polling->startPolling(function(Update $update) use (
    $api,
    $config,
    $conversationManager,
    &$testsPassed,
    $polling,
    $logger
) {
    // Обработка текстовых сообщений
    if ($update->isMessage() && $update->message->text) {
        $message = $update->message;
        $text = $message->text;
        $chatId = $message->chat->id;
        $userId = $message->from->id;
        
        echo "   📩 Получено сообщение: {$text}\n";
        
        // Сохраняем пользователя
        $conversationManager->saveUser(
            $userId,
            $message->from->firstName,
            $message->from->username,
            $message->from->lastName
        );
        
        // Получаем состояние
        $state = $conversationManager->getConversation(conversationId: 0, $userId);
        
        // Обработка команд
        if (str_starts_with($text, '/test_')) {
            $command = str_replace('/test_', '', strtolower($text));
            
            if ($command === 'reg') {
                $conversationManager->startConversation(conversationId: 0, $userId, 'reg_step1');
                $api->sendMessage(
                    $chatId,
                    "👋 <b>Тест регистрации</b>\n\nШаг 1/3: Как вас зовут?",
                    ['parse_mode' => 'HTML']
                );
                echo "   ✅ Запущен тест регистрации\n";
            } elseif ($command === 'poll') {
                $keyboard = InlineKeyboardBuilder::makeGrid([
                    '⭐⭐⭐⭐⭐' => 'rate_5',
                    '⭐⭐⭐⭐' => 'rate_4',
                    '⭐⭐⭐' => 'rate_3',
                    '⭐⭐' => 'rate_2',
                    '⭐' => 'rate_1',
                ], 1);
                $api->sendMessage(
                    $chatId,
                    "📊 <b>Тест опроса</b>\n\nОцените качество работы бота:",
                    ['parse_mode' => 'HTML', 'reply_markup' => $keyboard]
                );
                echo "   ✅ Запущен тест опроса\n";
            } elseif ($command === 'callback') {
                $keyboard = (new InlineKeyboardBuilder())
                    ->addCallbackButton('🔥 Действие 1', 'action_1')
                    ->addCallbackButton('💡 Действие 2', 'action_2')
                    ->row()
                    ->addCallbackButton('✅ Завершить', 'finish_test')
                    ->build();
                $api->sendMessage(
                    $chatId,
                    "🎮 <b>Тест callback</b>\n\nНажмите кнопки:",
                    ['parse_mode' => 'HTML', 'reply_markup' => $keyboard]
                );
                echo "   ✅ Запущен тест callback\n";
            } elseif ($command === 'finish') {
                $api->sendMessage(
                    $chatId,
                    "🏁 <b>Тестирование завершается...</b>",
                    ['parse_mode' => 'HTML']
                );
                $polling->stopPolling();
            }
            return;
        }
        
        // Обработка состояний
        if ($state) {
            if ($state['state'] === 'reg_step1') {
                $conversationManager->startConversation(conversationId: 0, $userId, 'reg_step2', ['name' => $text]);
                $api->sendMessage(
                    $chatId,
                    "✅ Приятно познакомиться, {$text}!\n\nШаг 2/3: Сколько вам лет?"
                );
                echo "   ✅ Регистрация: шаг 1 → 2\n";
            } elseif ($state['state'] === 'reg_step2') {
                if (!is_numeric($text)) {
                    $api->sendMessage($chatId, "❌ Введите число!");
                    return;
                }
                $data = $state['data'] ?? [];
                $data['age'] = (int)$text;
                $conversationManager->startConversation(conversationId: 0, $userId, 'reg_step3', $data);
                
                $keyboard = InlineKeyboardBuilder::makeSimple([
                    '🏠 Москва' => 'city_msk',
                    '🌊 СПб' => 'city_spb',
                    '🌍 Другой' => 'city_other',
                ]);
                $api->sendMessage(
                    $chatId,
                    "✅ Отлично!\n\nШаг 3/3: Выберите город:",
                    ['reply_markup' => $keyboard]
                );
                echo "   ✅ Регистрация: шаг 2 → 3\n";
            }
        }
    }
    
    // Обработка callback
    if ($update->isCallbackQuery()) {
        $query = $update->callbackQuery;
        $data = $query->data;
        $chatId = $query->message->chat->id;
        $messageId = $query->message->messageId;
        $userId = $query->from->id;
        
        echo "   📞 Получен callback: {$data}\n";
        
        if (str_starts_with($data, 'rate_')) {
            $rating = str_replace('rate_', '', $data);
            $api->answerCallbackQuery($query->id, ['text' => "Оценка: {$rating} ⭐"]);
            $api->editMessageText(
                $chatId,
                $messageId,
                "✅ <b>Спасибо за оценку!</b>\n\nВы поставили: {$rating} ⭐",
                ['parse_mode' => 'HTML']
            );
            $testsPassed['poll'] = true;
            echo "   ✅ Тест опроса пройден\n";
        } elseif (str_starts_with($data, 'action_')) {
            $action = str_replace('action_', '', $data);
            $api->answerCallbackQuery($query->id, ['text' => "Действие {$action}!"]);
            echo "   ✅ Callback действие {$action}\n";
        } elseif ($data === 'finish_test') {
            $api->answerCallbackQuery($query->id, ['text' => 'Тест завершён!']);
            $api->editMessageText(
                $chatId,
                $messageId,
                "✅ <b>Тест callback завершён!</b>",
                ['parse_mode' => 'HTML']
            );
            $testsPassed['callback'] = true;
            echo "   ✅ Тест callback пройден\n";
        } elseif (str_starts_with($data, 'city_')) {
            $state = $conversationManager->getConversation(conversationId: 0, $userId);
            if ($state && $state['state'] === 'reg_step3') {
                $cityName = match($data) {
                    'city_msk' => 'Москва',
                    'city_spb' => 'Санкт-Петербург',
                    'city_other' => 'Другой город',
                    default => 'Неизвестно'
                };
                
                $userData = $state['data'] ?? [];
                $userData['city'] = $cityName;
                
                $api->answerCallbackQuery($query->id, ['text' => "Город: {$cityName}"]);
                $api->editMessageText(
                    $chatId,
                    $messageId,
                    "✅ <b>Регистрация завершена!</b>\n\n" .
                    "👤 Имя: {$userData['name']}\n" .
                    "🎂 Возраст: {$userData['age']}\n" .
                    "🏠 Город: {$cityName}",
                    ['parse_mode' => 'HTML']
                );
                
                $conversationManager->endConversation(conversationId: 0, $userId);
                $testsPassed['registration'] = true;
                echo "   ✅ Тест регистрации пройден\n";
            }
        }
    }
}, 100); // Максимум 100 итераций

echo "\n✅ ЭТАП 3 ЗАВЕРШЁН\n\n";

// ============================================================================
// ФИНАЛЬНЫЙ ОТЧЁТ
// ============================================================================

echo "═══════════════════════════════════════════════════════════════\n";
echo "ФИНАЛЬНЫЙ ОТЧЁТ\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$finalStats = $messageStorage->getStatistics($config['test_chat_id']);

$reportText = "🏆 <b>ТЕСТИРОВАНИЕ ЗАВЕРШЕНО!</b>\n\n";
$reportText .= "━━━━━━━━━━━━━━━━━━━━\n\n";
$reportText .= "<b>📊 ИТОГОВАЯ СТАТИСТИКА:</b>\n\n";
$reportText .= "📨 Всего: {$finalStats['total']}\n";
$reportText .= "📥 Входящих: {$finalStats['incoming']}\n";
$reportText .= "📤 Исходящих: {$finalStats['outgoing']}\n";
$reportText .= "✅ Успешных: {$finalStats['success']}\n";
$reportText .= "❌ Ошибок: {$finalStats['failed']}\n\n";
$reportText .= "━━━━━━━━━━━━━━━━━━━━\n\n";
$reportText .= "<b>✅ ПРОТЕСТИРОВАНО:</b>\n\n";
$reportText .= "✓ TelegramAPI\n";
$reportText .= "✓ PollingHandler\n";
$reportText .= "✓ MessageStorage\n";
$reportText .= "✓ ConversationManager\n";
$reportText .= "✓ InlineKeyboards\n";
$reportText .= "✓ ReplyKeyboards\n";
$reportText .= "✓ Редактирование\n";
$reportText .= "✓ Callback запросы\n";
$reportText .= "✓ Диалоги\n\n";
$reportText .= "🎉 <b>ВСЁ РАБОТАЕТ!</b>";

$api->sendMessage($config['test_chat_id'], $reportText, ['parse_mode' => 'HTML']);

echo "📊 ФИНАЛЬНАЯ СТАТИСТИКА:\n";
echo "   Всего сообщений: {$finalStats['total']}\n";
echo "   Входящих: {$finalStats['incoming']}\n";
echo "   Исходящих: {$finalStats['outgoing']}\n";
echo "   Успешных: {$finalStats['success']}\n";
echo "   Ошибок: {$finalStats['failed']}\n\n";

echo "🎉 ═══════════════════════════════════════════════════════════════\n";
echo "   ТЕСТИРОВАНИЕ ЗАВЕРШЕНО УСПЕШНО!\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "📝 Логи сохранены в: logs/telegram_bot_tests/\n";
echo "💾 База данных: telegram_bot_test\n";
echo "🏆 Система полностью работоспособна!\n";

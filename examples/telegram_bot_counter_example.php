<?php

declare(strict_types=1);

/**
 * Пример использования метода sendCounter() для TelegramBot
 * 
 * Демонстрирует различные варианты использования анимированного счетчика:
 * - Счетчик вверх с обычными цифрами
 * - Счетчик вниз с обычными цифрами  
 * - Счетчик с эмодзи цифрами
 * - Обратный отсчет перед действием
 * - Использование с клавиатурами
 */

require_once __DIR__ . '/../autoload.php';

use App\Component\Http;
use App\Component\Logger;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Core\PollingHandler;
use App\Component\TelegramBot\Entities\Update;
use App\Component\TelegramBot\Keyboards\InlineKeyboardBuilder;

// Конфигурация
$botToken = 'YOUR_BOT_TOKEN';

// Инициализация зависимостей
$logger = new Logger(['directory' => __DIR__ . '/../logs']);
$http = new Http(['timeout' => 60], $logger);
$api = new TelegramAPI($botToken, $http, $logger);

// Создание PollingHandler
$polling = new PollingHandler($api, $logger);
$polling->setTimeout(30)->setLimit(100);

echo "Бот запущен. Отправьте команду для теста:\n";
echo "  /counter_up - Счетчик вверх\n";
echo "  /counter_down - Счетчик вниз\n";
echo "  /counter_emoji - Счетчик с эмодзи\n";
echo "  /countdown - Обратный отсчет\n";
echo "  /menu - Меню с кнопками\n\n";

// ============================================================================
// Обработчик обновлений
// ============================================================================

$polling->startPolling(function (Update $update) use ($api, $logger) {
    try {
        // Обработка callback query (нажатие кнопок)
        if ($update->callbackQuery) {
            $callback = $update->callbackQuery;
            $chatId = $callback->message->chat->id;
            $data = $callback->data;
            
            $api->answerCallbackQuery($callback->id);
            
            handleCallback($api, $chatId, $data);
            return;
        }
        
        // Обработка текстовых команд
        if ($update->message) {
            $message = $update->message;
            $chatId = $message->chat->id;
            $text = $message->text ?? '';
            
            handleCommand($api, $chatId, $text);
        }
    } catch (Exception $e) {
        $logger->error('Ошибка при обработке обновления', [
            'error' => $e->getMessage(),
        ]);
    }
});

// ============================================================================
// Обработчик команд
// ============================================================================

function handleCommand(TelegramAPI $api, int $chatId, string $text): void
{
    switch ($text) {
        case '/start':
            $api->sendMessage(
                $chatId,
                "👋 Привет! Я демонстрирую работу метода sendCounter().\n\n"
                . "Доступные команды:\n"
                . "/counter_up - Счетчик вверх (1→10)\n"
                . "/counter_down - Счетчик вниз (10→1)\n"
                . "/counter_emoji - Счетчик с эмодзи (0→9)\n"
                . "/countdown - Обратный отсчет (5→0)\n"
                . "/menu - Меню с кнопками"
            );
            break;
            
        case '/counter_up':
            example_counterUp($api, $chatId);
            break;
            
        case '/counter_down':
            example_counterDown($api, $chatId);
            break;
            
        case '/counter_emoji':
            example_counterEmoji($api, $chatId);
            break;
            
        case '/countdown':
            example_countdown($api, $chatId);
            break;
            
        case '/menu':
            showMenu($api, $chatId);
            break;
            
        default:
            if (str_starts_with($text, '/')) {
                $api->sendMessage($chatId, "Неизвестная команда. Используйте /start для списка команд.");
            }
    }
}

// ============================================================================
// Обработчик callback запросов
// ============================================================================

function handleCallback(TelegramAPI $api, int $chatId, string $data): void
{
    switch ($data) {
        case 'demo_up':
            example_counterUp($api, $chatId);
            break;
            
        case 'demo_down':
            example_counterDown($api, $chatId);
            break;
            
        case 'demo_emoji':
            example_counterEmoji($api, $chatId);
            break;
            
        case 'demo_countdown':
            example_countdown($api, $chatId);
            break;
            
        case 'demo_all':
            example_allCounters($api, $chatId);
            break;
            
        case 'menu':
            showMenu($api, $chatId);
            break;
    }
}

// ============================================================================
// ПРИМЕРЫ ИСПОЛЬЗОВАНИЯ
// ============================================================================

/**
 * Пример 1: Счетчик ВВЕРХ (обычные цифры)
 */
function example_counterUp(TelegramAPI $api, int $chatId): void
{
    $api->sendMessage($chatId, "🔼 Запускаю счетчик ВВЕРХ (1 → 10)...");
    $api->sendCounter($chatId, 1, 10);
    $api->sendMessage($chatId, "✅ Счет завершен!");
}

/**
 * Пример 2: Счетчик ВНИЗ (обычные цифры)
 */
function example_counterDown(TelegramAPI $api, int $chatId): void
{
    $api->sendMessage($chatId, "🔽 Запускаю счетчик ВНИЗ (10 → 1)...");
    $api->sendCounter($chatId, 10, 1);
    $api->sendMessage($chatId, "✅ Счет завершен!");
}

/**
 * Пример 3: Счетчик с ЭМОДЗИ
 */
function example_counterEmoji(TelegramAPI $api, int $chatId): void
{
    $api->sendMessage($chatId, "🎨 Запускаю счетчик с ЭМОДЗИ (0 → 9)...");
    $api->sendCounter($chatId, 0, 9, true);
    $api->sendMessage($chatId, "✅ Красиво, правда? 😊");
}

/**
 * Пример 4: Обратный ОТСЧЕТ перед действием
 */
function example_countdown(TelegramAPI $api, int $chatId): void
{
    $api->sendMessage($chatId, "🚀 Приготовьтесь! Запуск через...");
    sleep(1);
    $api->sendCounter($chatId, 5, 0);
    $api->sendMessage($chatId, "💥 СТАРТ! Ракета запущена! 🚀");
}

/**
 * Пример 5: Все счетчики подряд
 */
function example_allCounters(TelegramAPI $api, int $chatId): void
{
    $api->sendMessage($chatId, "📊 Запускаю ВСЕ демо счетчики...\n\nЭто займет около минуты.");
    
    // Счетчик вверх
    sleep(2);
    $api->sendMessage($chatId, "1️⃣ Счетчик ВВЕРХ (1→5)");
    $api->sendCounter($chatId, 1, 5);
    
    // Счетчик вниз
    sleep(2);
    $api->sendMessage($chatId, "2️⃣ Счетчик ВНИЗ (10→5)");
    $api->sendCounter($chatId, 10, 5);
    
    // Эмодзи счетчик
    sleep(2);
    $api->sendMessage($chatId, "3️⃣ Счетчик ЭМОДЗИ (0→5)");
    $api->sendCounter($chatId, 0, 5, true);
    
    // Обратный отсчет
    sleep(2);
    $api->sendMessage($chatId, "4️⃣ Обратный отсчет");
    $api->sendCounter($chatId, 5, 1);
    
    // Завершение
    $api->sendMessage(
        $chatId,
        "🎉 Все демонстрации завершены!\n\n"
        . "Используйте /menu для повторного выбора."
    );
}

// ============================================================================
// Меню с кнопками
// ============================================================================

function showMenu(TelegramAPI $api, int $chatId): void
{
    $keyboard = InlineKeyboardBuilder::make()
        ->addCallbackButton('🔼 Счетчик ВВЕРХ', 'demo_up')
        ->row()
        ->addCallbackButton('🔽 Счетчик ВНИЗ', 'demo_down')
        ->row()
        ->addCallbackButton('🎨 Счетчик ЭМОДЗИ', 'demo_emoji')
        ->row()
        ->addCallbackButton('🚀 Обратный отсчет', 'demo_countdown')
        ->row()
        ->addCallbackButton('▶️ ЗАПУСТИТЬ ВСЕ', 'demo_all')
        ->build();
    
    $api->sendMessage(
        $chatId,
        "🎯 <b>Демонстрация метода sendCounter()</b>\n\n"
        . "Выберите пример:\n\n"
        . "• <b>Счетчик ВВЕРХ</b> - от меньшего к большему\n"
        . "• <b>Счетчик ВНИЗ</b> - от большего к меньшему\n"
        . "• <b>Счетчик ЭМОДЗИ</b> - с эмодзи цифрами\n"
        . "• <b>Обратный отсчет</b> - перед действием\n"
        . "• <b>Запустить все</b> - показать все примеры\n\n"
        . "⏱️ Интервал между обновлениями: 1 секунда",
        [
            'parse_mode' => TelegramAPI::PARSE_MODE_HTML,
            'reply_markup' => $keyboard,
        ]
    );
}

// ============================================================================
// ДОПОЛНИТЕЛЬНЫЕ ПРИМЕРЫ
// ============================================================================

/**
 * Пример: Игровой таймер
 */
function example_gameTimer(TelegramAPI $api, int $chatId, int $seconds): void
{
    $api->sendMessage(
        $chatId,
        "🎮 Игра началась!\n⏰ Время на раунд: {$seconds} секунд"
    );
    
    $api->sendCounter($chatId, $seconds, 0);
    
    $api->sendMessage($chatId, "⏱️ Время вышло! Раунд завершен.");
}

/**
 * Пример: Процесс с визуализацией
 */
function example_processWithCounter(TelegramAPI $api, int $chatId): void
{
    $api->sendMessage($chatId, "⚙️ Начинаю обработку...");
    
    // Показываем прогресс
    $api->sendMessage($chatId, "Обработано элементов:");
    $api->sendCounter($chatId, 0, 10);
    
    $api->sendMessage($chatId, "✅ Обработка завершена!");
}

/**
 * Пример: Эмодзи демонстрация
 */
function example_emojiShowcase(TelegramAPI $api, int $chatId): void
{
    $api->sendMessage($chatId, "🎨 Демонстрация эмодзи счетчика\n\nСчет от 0 до 9:");
    $api->sendCounter($chatId, 0, 9, true);
    
    sleep(2);
    
    $api->sendMessage($chatId, "А теперь в обратном порядке:");
    $api->sendCounter($chatId, 9, 0, true);
    
    sleep(2);
    
    $api->sendMessage($chatId, "Двузначные числа:");
    $api->sendCounter($chatId, 15, 20, true);
}

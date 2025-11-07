<?php

declare(strict_types=1);

/**
 * Пример использования PollingHandler для работы бота в режиме long polling
 * 
 * Демонстрирует основные возможности класса:
 * - Инициализация и настройка
 * - Обработка обновлений в цикле
 * - Обработка команд и callback запросов
 * - Корректное логирование
 */

require_once __DIR__ . '/../../../autoload.php';

use App\Component\Http;
use App\Component\Logger;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Core\PollingHandler;
use App\Component\TelegramBot\Entities\Update;

// Конфигурация
$botToken = 'YOUR_BOT_TOKEN';

// Инициализация зависимостей
$logger = new Logger(['directory' => __DIR__ . '/../logs']);
$http = new Http(['timeout' => 60], $logger);
$api = new TelegramAPI($botToken, $http, $logger);

// Создание PollingHandler
$polling = new PollingHandler($api, $logger);

// Настройка параметров
$polling
    ->setTimeout(30)         // Long polling timeout: 30 секунд
    ->setLimit(100)          // Максимум 100 обновлений за запрос
    ->setAllowedUpdates([    // Фильтр типов обновлений
        'message',
        'callback_query',
    ]);

// Пропуск старых сообщений при первом запуске
$skipped = $polling->skipPendingUpdates();
echo "Пропущено старых обновлений: $skipped\n";

// ============================================================================
// ПРИМЕР 1: Простой обработчик с эхом
// ============================================================================

echo "Запуск polling (Пример 1)...\n";

$polling->startPolling(function(Update $update) use ($api) {
    if ($update->isMessage() && $update->message->text) {
        $message = $update->message;
        $api->sendMessage(
            $message->chat->id,
            "Вы написали: " . $message->text
        );
    }
}, 5); // Максимум 5 итераций для примера

echo "Пример 1 завершен\n\n";

// ============================================================================
// ПРИМЕР 2: Обработка команд
// ============================================================================

echo "Запуск polling (Пример 2)...\n";

// Сбрасываем состояние для нового примера
$polling->reset();

$polling->startPolling(function(Update $update) use ($api, $polling) {
    if (!$update->isMessage() || !$update->message->text) {
        return;
    }
    
    $message = $update->message;
    $text = $message->text;
    $chatId = $message->chat->id;
    
    // Обработка команд
    if (str_starts_with($text, '/')) {
        $command = strtolower(trim($text, '/'));
        
        match($command) {
            'start' => $api->sendMessage($chatId, "👋 Привет! Я работаю через polling."),
            'help' => $api->sendMessage($chatId, "📚 Доступные команды:\n/start\n/help\n/stop"),
            'stop' => function() use ($api, $chatId, $polling) {
                $api->sendMessage($chatId, "🛑 Останавливаю бот...");
                $polling->stopPolling();
            },
            default => $api->sendMessage($chatId, "❓ Неизвестная команда: $command"),
        };
    } else {
        // Эхо для обычных сообщений
        $api->sendMessage($chatId, "📝 Эхо: $text");
    }
}, 10); // Максимум 10 итераций

echo "Пример 2 завершен\n\n";

// ============================================================================
// ПРИМЕР 3: Обработка callback запросов
// ============================================================================

use App\Component\TelegramBot\Keyboards\InlineKeyboardBuilder;

echo "Запуск polling (Пример 3)...\n";

$polling->reset();

$polling->startPolling(function(Update $update) use ($api) {
    // Обработка текстовых сообщений
    if ($update->isMessage() && $update->message->text === '/menu') {
        $keyboard = InlineKeyboardBuilder::makeSimple([
            '✅ Вариант 1' => 'option_1',
            '🔔 Вариант 2' => 'option_2',
            '⚙️ Настройки' => 'settings',
        ]);
        
        $api->sendMessage(
            $update->message->chat->id,
            "Выберите вариант:",
            ['reply_markup' => $keyboard]
        );
    }
    
    // Обработка callback запросов
    if ($update->isCallbackQuery()) {
        $query = $update->callbackQuery;
        
        // Отвечаем на callback
        $api->answerCallbackQuery($query->id, [
            'text' => 'Обработано!',
        ]);
        
        // Изменяем сообщение
        $api->editMessageText(
            $query->message->chat->id,
            $query->message->messageId,
            "✅ Вы выбрали: " . $query->data
        );
    }
}, 5);

echo "Пример 3 завершен\n\n";

// ============================================================================
// ПРИМЕР 4: Однократное получение обновлений (без цикла)
// ============================================================================

echo "Пример 4: Однократное получение\n";

$polling->reset();

// Получаем обновления один раз
$updates = $polling->pollOnce();

echo "Получено обновлений: " . count($updates) . "\n";

foreach ($updates as $update) {
    echo "  - Update ID: {$update->updateId}\n";
    
    if ($update->isMessage()) {
        echo "    Тип: Message\n";
    } elseif ($update->isCallbackQuery()) {
        echo "    Тип: CallbackQuery\n";
    }
}

echo "\n";

// ============================================================================
// ПРИМЕР 5: Обработка ошибок
// ============================================================================

echo "Пример 5: Обработка ошибок\n";

$polling->reset();

$polling->startPolling(function(Update $update) use ($api, $logger) {
    try {
        // Ваш код обработки
        if ($update->isMessage()) {
            $message = $update->message;
            
            // Симуляция ошибки
            if ($message->text === '/error') {
                throw new Exception('Тестовая ошибка');
            }
            
            $api->sendMessage($message->chat->id, "OK");
        }
    } catch (Exception $e) {
        // Логируем ошибку, но продолжаем работу
        $logger->error('Ошибка обработки обновления', [
            'error' => $e->getMessage(),
            'update_id' => $update->updateId,
        ]);
        
        // Можно отправить сообщение об ошибке пользователю
        if ($update->isMessage()) {
            $api->sendMessage(
                $update->message->chat->id,
                "❌ Произошла ошибка при обработке вашего запроса"
            );
        }
    }
}, 5);

echo "Пример 5 завершен\n\n";

// ============================================================================
// ПРИМЕР 6: Использование собственного цикла
// ============================================================================

echo "Пример 6: Собственный цикл обработки\n";

$polling->reset();
$maxIterations = 3;

for ($i = 0; $i < $maxIterations; $i++) {
    echo "Итерация " . ($i + 1) . "...\n";
    
    $updates = $polling->getUpdates();
    
    foreach ($updates as $update) {
        // Ваша логика обработки
        echo "  - Обработка update {$update->updateId}\n";
        
        if ($update->isMessage() && $update->message->text) {
            $api->sendMessage(
                $update->message->chat->id,
                "Обработано в итерации " . ($i + 1)
            );
        }
    }
    
    echo "  Получено: " . count($updates) . " обновлений\n";
}

echo "Пример 6 завершен\n\n";

// ============================================================================
// Информация о состоянии
// ============================================================================

echo "Финальное состояние:\n";
echo "  - Текущий offset: {$polling->getOffset()}\n";
echo "  - Polling активен: " . ($polling->isPolling() ? 'Да' : 'Нет') . "\n";
echo "\nВсе примеры завершены!\n";

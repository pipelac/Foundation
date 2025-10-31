<?php

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

use App\Component\Logger;
use App\Component\Telegram;
use App\Component\Exception\TelegramApiException;

/**
 * Интерактивный тест обработки callback queries
 * 
 * Этот тест создаёт интерактивное меню с кнопками и ожидает нажатий,
 * демонстрируя полный цикл работы с inline клавиатурами
 */

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║     ИНТЕРАКТИВНЫЙ ТЕСТ CALLBACK QUERIES И КЛАВИАТУР            ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

try {
    $loggerConfig = [
        'directory' => __DIR__ . '/logs',
        'file_name' => 'telegram_interactive.log',
        'max_files' => 3,
        'max_file_size' => 5,
        'enabled' => true,
    ];

    $logger = new Logger($loggerConfig);

    $telegramConfig = [
        'token' => '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI',
        'default_chat_id' => '366442475',
        'timeout' => 30,
        'retries' => 3,
    ];

    $telegram = new Telegram($telegramConfig, $logger);

    echo "🤖 Отправка интерактивного меню...\n\n";

    $keyboard = $telegram->buildInlineKeyboard([
        [
            ['text' => '🔴 Красная кнопка', 'callback_data' => 'btn_red'],
            ['text' => '🔵 Синяя кнопка', 'callback_data' => 'btn_blue'],
        ],
        [
            ['text' => '🟢 Зелёная кнопка', 'callback_data' => 'btn_green'],
            ['text' => '🟡 Жёлтая кнопка', 'callback_data' => 'btn_yellow'],
        ],
        [
            ['text' => '📊 Счётчик: 0', 'callback_data' => 'counter'],
        ],
        [
            ['text' => '🔄 Сбросить', 'callback_data' => 'reset'],
            ['text' => '❌ Закрыть меню', 'callback_data' => 'close'],
        ],
    ]);

    $result = $telegram->sendText(
        null,
        "🎮 <b>Интерактивное меню</b>\n\n" .
        "Нажмите на любую кнопку ниже.\n" .
        "Меню будет обновляться в реальном времени!",
        [
            'parse_mode' => Telegram::PARSE_MODE_HTML,
            'reply_markup' => $keyboard,
        ]
    );

    $menuMessageId = $result['result']['message_id'] ?? null;
    echo "✓ Интерактивное меню отправлено (Message ID: {$menuMessageId})\n\n";

    $counter = 0;
    $lastColors = [];
    $processedUpdates = [];

    echo "⏳ Ожидание нажатий на кнопки (30 секунд)...\n";
    echo "   Нажмите Ctrl+C для выхода\n\n";

    $startTime = time();
    $lastUpdateId = 0;

    while ((time() - $startTime) < 30) {
        try {
            $updates = $telegram->getUpdates([
                'offset' => $lastUpdateId + 1,
                'timeout' => 3,
                'allowed_updates' => ['callback_query'],
            ]);

            foreach ($updates['result'] ?? [] as $update) {
                $updateId = $update['update_id'];
                $lastUpdateId = max($lastUpdateId, $updateId);

                if (in_array($updateId, $processedUpdates)) {
                    continue;
                }

                $processedUpdates[] = $updateId;

                if (!isset($update['callback_query'])) {
                    continue;
                }

                $callback = $update['callback_query'];
                $callbackId = $callback['id'];
                $callbackData = $callback['data'] ?? '';
                $userName = $callback['from']['first_name'] ?? 'Пользователь';

                echo "[" . date('H:i:s') . "] ";

                switch ($callbackData) {
                    case 'btn_red':
                        echo "🔴 {$userName} нажал красную кнопку\n";
                        $lastColors[] = '🔴';
                        $telegram->answerCallbackQuery($callbackId, [
                            'text' => '🔴 Красная кнопка нажата!',
                        ]);
                        break;

                    case 'btn_blue':
                        echo "🔵 {$userName} нажал синюю кнопку\n";
                        $lastColors[] = '🔵';
                        $telegram->answerCallbackQuery($callbackId, [
                            'text' => '🔵 Синяя кнопка нажата!',
                        ]);
                        break;

                    case 'btn_green':
                        echo "🟢 {$userName} нажал зелёную кнопку\n";
                        $lastColors[] = '🟢';
                        $telegram->answerCallbackQuery($callbackId, [
                            'text' => '🟢 Зелёная кнопка нажата!',
                        ]);
                        break;

                    case 'btn_yellow':
                        echo "🟡 {$userName} нажал жёлтую кнопку\n";
                        $lastColors[] = '🟡';
                        $telegram->answerCallbackQuery($callbackId, [
                            'text' => '🟡 Жёлтая кнопка нажата!',
                        ]);
                        break;

                    case 'counter':
                        $counter++;
                        echo "📊 Счётчик увеличен: {$counter}\n";
                        $telegram->answerCallbackQuery($callbackId, [
                            'text' => "Счётчик: {$counter}",
                        ]);
                        break;

                    case 'reset':
                        echo "🔄 Сброс состояния\n";
                        $counter = 0;
                        $lastColors = [];
                        $telegram->answerCallbackQuery($callbackId, [
                            'text' => '✅ Сброшено!',
                            'show_alert' => true,
                        ]);
                        break;

                    case 'close':
                        echo "❌ Закрытие меню\n";
                        $telegram->answerCallbackQuery($callbackId, [
                            'text' => '👋 До свидания!',
                        ]);
                        
                        $telegram->editMessageText(
                            null,
                            $menuMessageId,
                            "✅ <b>Меню закрыто</b>\n\n" .
                            "Спасибо за тестирование!",
                            [
                                'parse_mode' => Telegram::PARSE_MODE_HTML,
                            ]
                        );
                        
                        echo "\n✓ Меню закрыто пользователем\n";
                        break 3;

                    default:
                        echo "❓ Неизвестное действие: {$callbackData}\n";
                        $telegram->answerCallbackQuery($callbackId);
                        continue 2;
                }

                if ($callbackData !== 'close') {
                    $colorsHistory = empty($lastColors) 
                        ? 'пусто' 
                        : implode(' ', array_slice($lastColors, -5));

                    $updatedKeyboard = $telegram->buildInlineKeyboard([
                        [
                            ['text' => '🔴 Красная кнопка', 'callback_data' => 'btn_red'],
                            ['text' => '🔵 Синяя кнопка', 'callback_data' => 'btn_blue'],
                        ],
                        [
                            ['text' => '🟢 Зелёная кнопка', 'callback_data' => 'btn_green'],
                            ['text' => '🟡 Жёлтая кнопка', 'callback_data' => 'btn_yellow'],
                        ],
                        [
                            ['text' => "📊 Счётчик: {$counter}", 'callback_data' => 'counter'],
                        ],
                        [
                            ['text' => '🔄 Сбросить', 'callback_data' => 'reset'],
                            ['text' => '❌ Закрыть меню', 'callback_data' => 'close'],
                        ],
                    ]);

                    $telegram->editMessageText(
                        null,
                        $menuMessageId,
                        "🎮 <b>Интерактивное меню</b>\n\n" .
                        "Счётчик: <b>{$counter}</b>\n" .
                        "Последние цвета: {$colorsHistory}\n\n" .
                        "Обновлено: " . date('H:i:s'),
                        [
                            'parse_mode' => Telegram::PARSE_MODE_HTML,
                            'reply_markup' => $updatedKeyboard,
                        ]
                    );
                }
            }

        } catch (TelegramApiException $e) {
            echo "⚠ Ошибка: " . $e->getMessage() . "\n";
        }

        usleep(500000);
    }

    echo "\n⏱ Время ожидания истекло (30 секунд)\n";

    echo "\n═══════════════════════════════════════════════════════════════\n";
    echo "  СТАТИСТИКА\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";
    echo "Обработано обновлений: " . count($processedUpdates) . "\n";
    echo "Счётчик нажатий: {$counter}\n";
    echo "Нажато цветных кнопок: " . count($lastColors) . "\n\n";

} catch (Exception $e) {
    echo "\n✗ Ошибка: " . $e->getMessage() . "\n";
    echo "Файл: " . $e->getFile() . "\n";
    echo "Строка: " . $e->getLine() . "\n\n";
    exit(1);
}

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║              ИНТЕРАКТИВНЫЙ ТЕСТ ЗАВЕРШЕН                       ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

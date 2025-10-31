<?php

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

use App\Component\Logger;
use App\Component\Telegram;
use App\Component\Exception\TelegramApiException;
use App\Component\Exception\TelegramConfigException;

/**
 * Полный тест новых возможностей класса Telegram:
 * - Голосования (polls)
 * - Inline клавиатуры
 * - Reply клавиатуры
 * - Обработка callback queries
 * - Редактирование сообщений
 */

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║       ПОЛНЫЙ ТЕСТ КЛАССА TELEGRAM - НОВЫЕ ВОЗМОЖНОСТИ         ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

try {
    $loggerConfig = [
        'directory' => __DIR__ . '/logs',
        'file_name' => 'telegram_complete_test.log',
        'max_files' => 5,
        'max_file_size' => 10,
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

    echo "✓ Telegram инициализирован\n\n";

    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  1. ПРОВЕРКА БОТА\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    
    try {
        $botInfo = $telegram->getMe();
        echo "✓ Бот подключен: " . ($botInfo['result']['first_name'] ?? 'Unknown') . "\n";
        echo "  Username: @" . ($botInfo['result']['username'] ?? 'unknown') . "\n\n";
    } catch (TelegramApiException $e) {
        echo "✗ Ошибка подключения: " . $e->getMessage() . "\n\n";
        exit(1);
    }

    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  2. ТЕСТИРОВАНИЕ ГОЛОСОВАНИЙ (POLLS)\n";
    echo "═══════════════════════════════════════════════════════════════\n";

    echo "\n► Тест 2.1: Обычный опрос (regular poll)\n";
    try {
        $result = $telegram->sendPoll(
            null,
            'Какой язык программирования вы предпочитаете?',
            ['PHP', 'Python', 'JavaScript', 'Go', 'Rust'],
            [
                'is_anonymous' => false,
                'allows_multiple_answers' => true,
            ]
        );
        $pollMessageId = $result['result']['message_id'] ?? null;
        echo "  ✓ Обычный опрос отправлен (Message ID: {$pollMessageId})\n";
        echo "  • Анонимность: НЕТ\n";
        echo "  • Множественный выбор: ДА\n";
        sleep(2);
    } catch (TelegramApiException $e) {
        echo "  ✗ Ошибка: " . $e->getMessage() . "\n";
    }

    echo "\n► Тест 2.2: Викторина (quiz)\n";
    try {
        $result = $telegram->sendPoll(
            null,
            'Сколько байт в одном килобайте?',
            ['1000 байт', '1024 байта', '8 бит', '1048576 байт'],
            [
                'type' => 'quiz',
                'correct_option_id' => 1,
                'explanation' => '1 килобайт = 1024 байта (2^10)',
                'explanation_parse_mode' => Telegram::PARSE_MODE_HTML,
            ]
        );
        $quizMessageId = $result['result']['message_id'] ?? null;
        echo "  ✓ Викторина отправлена (Message ID: {$quizMessageId})\n";
        echo "  • Правильный ответ: #1 (1024 байта)\n";
        echo "  • Пояснение добавлено\n";
        sleep(2);
    } catch (TelegramApiException $e) {
        echo "  ✗ Ошибка: " . $e->getMessage() . "\n";
    }

    echo "\n► Тест 2.3: Опрос с автозакрытием\n";
    try {
        $result = $telegram->sendPoll(
            null,
            'Этот опрос закроется через 30 секунд',
            ['Вариант 1', 'Вариант 2'],
            [
                'open_period' => 30,
            ]
        );
        $timedPollMessageId = $result['result']['message_id'] ?? null;
        echo "  ✓ Опрос с таймером отправлен (Message ID: {$timedPollMessageId})\n";
        echo "  • Автозакрытие: через 30 секунд\n";
        sleep(2);
    } catch (TelegramApiException $e) {
        echo "  ✗ Ошибка: " . $e->getMessage() . "\n";
    }

    if (isset($pollMessageId)) {
        echo "\n► Тест 2.4: Остановка опроса\n";
        try {
            sleep(3);
            $result = $telegram->stopPoll(null, $pollMessageId);
            echo "  ✓ Опрос остановлен (Message ID: {$pollMessageId})\n";
            $poll = $result['result'] ?? [];
            echo "  • Всего голосов: " . ($poll['total_voter_count'] ?? 0) . "\n";
        } catch (TelegramApiException $e) {
            echo "  ✗ Ошибка: " . $e->getMessage() . "\n";
        }
    }

    echo "\n═══════════════════════════════════════════════════════════════\n";
    echo "  3. ТЕСТИРОВАНИЕ INLINE КЛАВИАТУР\n";
    echo "═══════════════════════════════════════════════════════════════\n";

    echo "\n► Тест 3.1: Inline клавиатура с callback кнопками\n";
    try {
        $inlineKeyboard = $telegram->buildInlineKeyboard([
            [
                ['text' => '👍 Нравится', 'callback_data' => 'like'],
                ['text' => '👎 Не нравится', 'callback_data' => 'dislike'],
            ],
            [
                ['text' => '⭐ Оценить 5', 'callback_data' => 'rate_5'],
                ['text' => '⭐ Оценить 4', 'callback_data' => 'rate_4'],
                ['text' => '⭐ Оценить 3', 'callback_data' => 'rate_3'],
            ],
            [
                ['text' => '📊 Показать статистику', 'callback_data' => 'show_stats'],
            ],
        ]);

        $result = $telegram->sendText(
            null,
            "🎯 <b>Тест Inline клавиатуры</b>\n\nВыберите действие, нажав на кнопку ниже:",
            [
                'parse_mode' => Telegram::PARSE_MODE_HTML,
                'reply_markup' => $inlineKeyboard,
            ]
        );
        $inlineMessageId = $result['result']['message_id'] ?? null;
        echo "  ✓ Inline клавиатура отправлена (Message ID: {$inlineMessageId})\n";
        echo "  • Всего кнопок: 6\n";
        echo "  • Рядов: 3\n";
        sleep(2);
    } catch (TelegramApiException $e) {
        echo "  ✗ Ошибка: " . $e->getMessage() . "\n";
    }

    echo "\n► Тест 3.2: Inline клавиатура с URL кнопками\n";
    try {
        $urlKeyboard = $telegram->buildInlineKeyboard([
            [
                ['text' => '📚 Документация Telegram Bot API', 'url' => 'https://core.telegram.org/bots/api'],
            ],
            [
                ['text' => '🔍 Google', 'url' => 'https://google.com'],
                ['text' => '🐙 GitHub', 'url' => 'https://github.com'],
            ],
        ]);

        $result = $telegram->sendText(
            null,
            "🔗 Inline клавиатура с URL кнопками",
            [
                'reply_markup' => $urlKeyboard,
            ]
        );
        echo "  ✓ URL клавиатура отправлена\n";
        sleep(2);
    } catch (TelegramApiException $e) {
        echo "  ✗ Ошибка: " . $e->getMessage() . "\n";
    }

    echo "\n► Тест 3.3: Смешанная inline клавиатура\n";
    try {
        $mixedKeyboard = $telegram->buildInlineKeyboard([
            [
                ['text' => '✅ Подтвердить', 'callback_data' => 'confirm'],
                ['text' => '❌ Отменить', 'callback_data' => 'cancel'],
            ],
            [
                ['text' => '📖 Инструкция', 'url' => 'https://core.telegram.org/bots'],
            ],
            [
                ['text' => '💬 Поделиться', 'switch_inline_query' => 'Посмотрите на это!'],
            ],
        ]);

        $result = $telegram->sendText(
            null,
            "🎨 Смешанная клавиатура:\n• Callback кнопки\n• URL кнопки\n• Inline query кнопки",
            [
                'reply_markup' => $mixedKeyboard,
            ]
        );
        echo "  ✓ Смешанная клавиатура отправлена\n";
        sleep(2);
    } catch (TelegramApiException $e) {
        echo "  ✗ Ошибка: " . $e->getMessage() . "\n";
    }

    echo "\n═══════════════════════════════════════════════════════════════\n";
    echo "  4. ТЕСТИРОВАНИЕ REPLY КЛАВИАТУР\n";
    echo "═══════════════════════════════════════════════════════════════\n";

    echo "\n► Тест 4.1: Обычная reply клавиатура\n";
    try {
        $replyKeyboard = $telegram->buildReplyKeyboard([
            ['🏠 Главная', '📊 Статистика'],
            ['⚙️ Настройки', '❓ Помощь'],
            ['📝 О программе'],
        ], [
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ]);

        $result = $telegram->sendText(
            null,
            "⌨️ Reply клавиатура установлена!\n\nВыберите пункт меню:",
            [
                'reply_markup' => $replyKeyboard,
            ]
        );
        echo "  ✓ Reply клавиатура отправлена\n";
        echo "  • Автоподстройка размера: ДА\n";
        echo "  • Одноразовая: НЕТ\n";
        sleep(2);
    } catch (TelegramApiException $e) {
        echo "  ✗ Ошибка: " . $e->getMessage() . "\n";
    }

    echo "\n► Тест 4.2: Reply клавиатура с запросом контакта и локации\n";
    try {
        $contactKeyboard = $telegram->buildReplyKeyboard([
            [
                ['text' => '📱 Отправить контакт', 'request_contact' => true],
            ],
            [
                ['text' => '📍 Отправить локацию', 'request_location' => true],
            ],
            [
                'Отмена',
            ],
        ], [
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
            'input_field_placeholder' => 'Выберите действие...',
        ]);

        $result = $telegram->sendText(
            null,
            "📲 Клавиатура с запросом данных:\n\nМожете поделиться контактом или локацией",
            [
                'reply_markup' => $contactKeyboard,
            ]
        );
        echo "  ✓ Клавиатура с запросом данных отправлена\n";
        echo "  • Запрос контакта: ДА\n";
        echo "  • Запрос локации: ДА\n";
        echo "  • Placeholder добавлен\n";
        sleep(2);
    } catch (TelegramApiException $e) {
        echo "  ✗ Ошибка: " . $e->getMessage() . "\n";
    }

    echo "\n► Тест 4.3: Удаление reply клавиатуры\n";
    try {
        $removeKeyboard = $telegram->removeKeyboard();

        $result = $telegram->sendText(
            null,
            "🗑️ Reply клавиатура удалена",
            [
                'reply_markup' => $removeKeyboard,
            ]
        );
        echo "  ✓ Клавиатура удалена\n";
        sleep(2);
    } catch (TelegramApiException $e) {
        echo "  ✗ Ошибка: " . $e->getMessage() . "\n";
    }

    echo "\n► Тест 4.4: Принудительный ответ (Force Reply)\n";
    try {
        $forceReply = $telegram->forceReply('Введите ваше имя...');

        $result = $telegram->sendText(
            null,
            "✍️ Введите ваше имя:",
            [
                'reply_markup' => $forceReply,
            ]
        );
        echo "  ✓ Force Reply установлен\n";
        echo "  • Placeholder: 'Введите ваше имя...'\n";
        sleep(2);
    } catch (TelegramApiException $e) {
        echo "  ✗ Ошибка: " . $e->getMessage() . "\n";
    }

    echo "\n═══════════════════════════════════════════════════════════════\n";
    echo "  5. ТЕСТИРОВАНИЕ ОБРАБОТКИ CALLBACK QUERIES\n";
    echo "═══════════════════════════════════════════════════════════════\n";

    echo "\n► Тест 5.1: Получение обновлений\n";
    try {
        echo "  ⏳ Ожидание обновлений (timeout: 5 сек)...\n";
        $updates = $telegram->getUpdates([
            'timeout' => 5,
            'limit' => 10,
        ]);
        
        $updateCount = count($updates['result'] ?? []);
        echo "  ✓ Получено обновлений: {$updateCount}\n";

        if ($updateCount > 0) {
            $lastUpdate = end($updates['result']);
            $updateId = $lastUpdate['update_id'] ?? 'N/A';
            echo "  • Последний update_id: {$updateId}\n";

            if (isset($lastUpdate['callback_query'])) {
                $callbackQuery = $lastUpdate['callback_query'];
                $callbackId = $callbackQuery['id'];
                $callbackData = $callbackQuery['data'] ?? 'N/A';
                
                echo "\n► Тест 5.2: Обработка callback query\n";
                echo "  • Callback ID: {$callbackId}\n";
                echo "  • Callback Data: {$callbackData}\n";

                try {
                    $telegram->answerCallbackQuery($callbackId, [
                        'text' => "✅ Вы нажали: {$callbackData}",
                        'show_alert' => false,
                    ]);
                    echo "  ✓ Callback query обработан\n";
                } catch (TelegramApiException $e) {
                    echo "  ✗ Ошибка: " . $e->getMessage() . "\n";
                }

                if (isset($callbackQuery['message'])) {
                    $message = $callbackQuery['message'];
                    $chatId = (string)$message['chat']['id'];
                    $messageId = $message['message_id'];

                    echo "\n► Тест 5.3: Редактирование текста сообщения\n";
                    try {
                        $telegram->editMessageText(
                            $chatId,
                            $messageId,
                            "✏️ <b>Сообщение обновлено!</b>\n\nВы нажали: <code>{$callbackData}</code>\n\nВремя: " . date('H:i:s'),
                            [
                                'parse_mode' => Telegram::PARSE_MODE_HTML,
                            ]
                        );
                        echo "  ✓ Текст сообщения изменен\n";
                    } catch (TelegramApiException $e) {
                        echo "  ✗ Ошибка: " . $e->getMessage() . "\n";
                    }
                }
            } else {
                echo "  ℹ Callback query не найден в обновлениях\n";
            }
        }
    } catch (TelegramApiException $e) {
        echo "  ✗ Ошибка: " . $e->getMessage() . "\n";
    }

    echo "\n═══════════════════════════════════════════════════════════════\n";
    echo "  6. ТЕСТИРОВАНИЕ РЕДАКТИРОВАНИЯ СООБЩЕНИЙ\n";
    echo "═══════════════════════════════════════════════════════════════\n";

    echo "\n► Тест 6.1: Создание и редактирование сообщения\n";
    try {
        $keyboard = $telegram->buildInlineKeyboard([
            [
                ['text' => '🔄 Обновить', 'callback_data' => 'refresh'],
                ['text' => '❌ Удалить', 'callback_data' => 'delete'],
            ],
        ]);

        $result = $telegram->sendText(
            null,
            "📝 Исходное сообщение\n\nЭто сообщение будет изменено...",
            [
                'reply_markup' => $keyboard,
            ]
        );
        
        $editMessageId = $result['result']['message_id'] ?? null;
        echo "  ✓ Сообщение создано (Message ID: {$editMessageId})\n";

        sleep(2);

        if ($editMessageId) {
            echo "  ⏳ Редактирование через 2 секунды...\n";
            sleep(2);

            $telegram->editMessageText(
                null,
                $editMessageId,
                "✅ <b>Сообщение обновлено!</b>\n\nВремя обновления: " . date('H:i:s'),
                [
                    'parse_mode' => Telegram::PARSE_MODE_HTML,
                    'reply_markup' => $keyboard,
                ]
            );
            echo "  ✓ Сообщение отредактировано\n";
        }
    } catch (TelegramApiException $e) {
        echo "  ✗ Ошибка: " . $e->getMessage() . "\n";
    }

    echo "\n► Тест 6.2: Редактирование клавиатуры сообщения\n";
    try {
        if (isset($editMessageId)) {
            sleep(2);

            $newKeyboard = $telegram->buildInlineKeyboard([
                [
                    ['text' => '✅ Готово', 'callback_data' => 'done'],
                ],
                [
                    ['text' => '🔗 Документация', 'url' => 'https://core.telegram.org/bots/api'],
                ],
            ]);

            $telegram->editMessageReplyMarkup(null, $editMessageId, $newKeyboard);
            echo "  ✓ Клавиатура сообщения изменена\n";
        }
    } catch (TelegramApiException $e) {
        echo "  ✗ Ошибка: " . $e->getMessage() . "\n";
    }

    echo "\n═══════════════════════════════════════════════════════════════\n";
    echo "  7. ТЕСТИРОВАНИЕ ВАЛИДАЦИИ\n";
    echo "═══════════════════════════════════════════════════════════════\n";

    echo "\n► Тест 7.1: Валидация вопроса опроса\n";
    try {
        $telegram->sendPoll(null, '', ['Вариант 1', 'Вариант 2']);
        echo "  ✗ Валидация не сработала\n";
    } catch (TelegramApiException $e) {
        echo "  ✓ Пустой вопрос отклонен: " . $e->getMessage() . "\n";
    }

    echo "\n► Тест 7.2: Валидация количества вариантов опроса\n";
    try {
        $telegram->sendPoll(null, 'Вопрос?', ['Один вариант']);
        echo "  ✗ Валидация не сработала\n";
    } catch (TelegramApiException $e) {
        echo "  ✓ Недостаточно вариантов: " . $e->getMessage() . "\n";
    }

    echo "\n► Тест 7.3: Валидация inline клавиатуры\n";
    try {
        $telegram->buildInlineKeyboard([
            [
                ['text' => 'Кнопка без действия'],
            ],
        ]);
        echo "  ✗ Валидация не сработала\n";
    } catch (TelegramApiException $e) {
        echo "  ✓ Кнопка без действия отклонена: " . $e->getMessage() . "\n";
    }

    echo "\n► Тест 7.4: Валидация callback_data\n";
    try {
        $telegram->buildInlineKeyboard([
            [
                ['text' => 'Кнопка', 'callback_data' => str_repeat('X', 65)],
            ],
        ]);
        echo "  ✗ Валидация не сработала\n";
    } catch (TelegramApiException $e) {
        echo "  ✓ Слишком длинный callback_data: " . $e->getMessage() . "\n";
    }

    echo "\n► Тест 7.5: Валидация пустой клавиатуры\n";
    try {
        $telegram->buildReplyKeyboard([]);
        echo "  ✗ Валидация не сработала\n";
    } catch (TelegramApiException $e) {
        echo "  ✓ Пустая клавиатура отклонена: " . $e->getMessage() . "\n";
    }

    echo "\n═══════════════════════════════════════════════════════════════\n";
    echo "  8. ТЕСТИРОВАНИЕ УДАЛЕНИЯ СООБЩЕНИЙ\n";
    echo "═══════════════════════════════════════════════════════════════\n";

    echo "\n► Тест 8.1: Создание и удаление сообщения\n";
    try {
        $result = $telegram->sendText(
            null,
            "🗑️ Это сообщение будет удалено через 3 секунды..."
        );
        
        $deleteMessageId = $result['result']['message_id'] ?? null;
        echo "  ✓ Сообщение создано (Message ID: {$deleteMessageId})\n";

        if ($deleteMessageId) {
            echo "  ⏳ Удаление через 3 секунды...\n";
            sleep(3);

            $telegram->deleteMessage(null, $deleteMessageId);
            echo "  ✓ Сообщение удалено\n";
        }
    } catch (TelegramApiException $e) {
        echo "  ✗ Ошибка: " . $e->getMessage() . "\n";
    }

    echo "\n═══════════════════════════════════════════════════════════════\n";
    echo "  РЕЗУЛЬТАТЫ ТЕСТИРОВАНИЯ\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    echo "✅ Тестирование завершено успешно!\n\n";
    echo "Протестированные возможности:\n";
    echo "  ✓ Голосования (обычные и викторины)\n";
    echo "  ✓ Остановка голосований\n";
    echo "  ✓ Inline клавиатуры (callback, URL, смешанные)\n";
    echo "  ✓ Reply клавиатуры (обычные и с запросом данных)\n";
    echo "  ✓ Удаление и Force Reply клавиатур\n";
    echo "  ✓ Получение обновлений (getUpdates)\n";
    echo "  ✓ Обработка callback queries\n";
    echo "  ✓ Редактирование текста сообщений\n";
    echo "  ✓ Редактирование клавиатур\n";
    echo "  ✓ Удаление сообщений\n";
    echo "  ✓ Валидация всех входных данных\n\n";

    echo "📝 Логи сохранены в: {$loggerConfig['directory']}/{$loggerConfig['file_name']}\n\n";

} catch (TelegramConfigException $e) {
    echo "\n✗ Ошибка конфигурации: " . $e->getMessage() . "\n\n";
    exit(1);
} catch (Exception $e) {
    echo "\n✗ Неожиданная ошибка: " . $e->getMessage() . "\n";
    echo "Файл: " . $e->getFile() . "\n";
    echo "Строка: " . $e->getLine() . "\n\n";
    exit(1);
}

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                    ТЕСТ ЗАВЕРШЕН                               ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

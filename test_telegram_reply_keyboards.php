<?php

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

use App\Component\Logger;
use App\Component\Telegram;
use App\Component\Exception\TelegramApiException;

/**
 * Детальный тест Reply клавиатур
 * Демонстрирует все типы reply клавиатур и их возможности
 */

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║         ДЕТАЛЬНЫЙ ТЕСТ REPLY КЛАВИАТУР                         ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

try {
    $loggerConfig = [
        'directory' => __DIR__ . '/logs',
        'file_name' => 'telegram_reply_keyboards.log',
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

    echo "✓ Telegram инициализирован\n\n";

    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  1. ПРОСТАЯ REPLY КЛАВИАТУРА (СТРОКИ)\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    $simpleKeyboard = $telegram->buildReplyKeyboard([
        ['Да', 'Нет'],
        ['Возможно', 'Не знаю'],
    ]);

    $telegram->sendText(
        null,
        "Простая клавиатура из строк",
        ['reply_markup' => $simpleKeyboard]
    );
    echo "✓ Простая клавиатура отправлена\n";
    sleep(1);

    echo "\n═══════════════════════════════════════════════════════════════\n";
    echo "  2. КЛАВИАТУРА С ЭМОДЗИ\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    $emojiKeyboard = $telegram->buildReplyKeyboard([
        ['🏠 Главная', '📊 Статистика', '⚙️ Настройки'],
        ['📁 Файлы', '📝 Заметки', '🔍 Поиск'],
        ['❓ Помощь', '💬 Чат', '👤 Профиль'],
    ], [
        'resize_keyboard' => true,
    ]);

    $telegram->sendText(
        null,
        "🎨 Клавиатура с эмодзи и автоподстройкой размера",
        ['reply_markup' => $emojiKeyboard]
    );
    echo "✓ Клавиатура с эмодзи отправлена\n";
    sleep(1);

    echo "\n═══════════════════════════════════════════════════════════════\n";
    echo "  3. ОДНОРАЗОВАЯ КЛАВИАТУРА\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    $oneTimeKeyboard = $telegram->buildReplyKeyboard([
        ['✅ Подтвердить'],
        ['❌ Отменить'],
    ], [
        'resize_keyboard' => true,
        'one_time_keyboard' => true,
        'input_field_placeholder' => 'Выберите действие',
    ]);

    $telegram->sendText(
        null,
        "Одноразовая клавиатура - скроется после выбора",
        ['reply_markup' => $oneTimeKeyboard]
    );
    echo "✓ Одноразовая клавиатура отправлена\n";
    sleep(1);

    echo "\n═══════════════════════════════════════════════════════════════\n";
    echo "  4. КЛАВИАТУРА С ЗАПРОСОМ КОНТАКТА\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    $contactKeyboard = $telegram->buildReplyKeyboard([
        [
            ['text' => '📱 Отправить номер телефона', 'request_contact' => true],
        ],
        ['Пропустить'],
    ], [
        'resize_keyboard' => true,
        'one_time_keyboard' => true,
    ]);

    $telegram->sendText(
        null,
        "📱 Клавиатура с запросом контакта",
        ['reply_markup' => $contactKeyboard]
    );
    echo "✓ Клавиатура с запросом контакта отправлена\n";
    sleep(1);

    echo "\n═══════════════════════════════════════════════════════════════\n";
    echo "  5. КЛАВИАТУРА С ЗАПРОСОМ ЛОКАЦИИ\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    $locationKeyboard = $telegram->buildReplyKeyboard([
        [
            ['text' => '📍 Отправить местоположение', 'request_location' => true],
        ],
        ['Отмена'],
    ], [
        'resize_keyboard' => true,
        'one_time_keyboard' => true,
    ]);

    $telegram->sendText(
        null,
        "📍 Клавиатура с запросом местоположения",
        ['reply_markup' => $locationKeyboard]
    );
    echo "✓ Клавиатура с запросом локации отправлена\n";
    sleep(1);

    echo "\n═══════════════════════════════════════════════════════════════\n";
    echo "  6. КЛАВИАТУРА С ЗАПРОСОМ ОПРОСА\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    $pollKeyboard = $telegram->buildReplyKeyboard([
        [
            [
                'text' => '📊 Создать опрос', 
                'request_poll' => ['type' => 'regular']
            ],
        ],
        [
            [
                'text' => '🧠 Создать викторину', 
                'request_poll' => ['type' => 'quiz']
            ],
        ],
        ['Назад'],
    ], [
        'resize_keyboard' => true,
    ]);

    $telegram->sendText(
        null,
        "📊 Клавиатура с запросом создания опроса",
        ['reply_markup' => $pollKeyboard]
    );
    echo "✓ Клавиатура с запросом опроса отправлена\n";
    sleep(1);

    echo "\n═══════════════════════════════════════════════════════════════\n";
    echo "  7. МНОГОРЯДНАЯ КЛАВИАТУРА\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    $multiRowKeyboard = $telegram->buildReplyKeyboard([
        ['1️⃣'],
        ['2️⃣', '3️⃣'],
        ['4️⃣', '5️⃣', '6️⃣'],
        ['7️⃣', '8️⃣', '9️⃣', '0️⃣'],
        ['⬅️ Назад', '✅ Готово', '❌ Отмена'],
    ], [
        'resize_keyboard' => true,
    ]);

    $telegram->sendText(
        null,
        "🔢 Многорядная клавиатура с разным количеством кнопок в ряду",
        ['reply_markup' => $multiRowKeyboard]
    );
    echo "✓ Многорядная клавиатура отправлена\n";
    sleep(1);

    echo "\n═══════════════════════════════════════════════════════════════\n";
    echo "  8. SELECTIVE КЛАВИАТУРА\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    $selectiveKeyboard = $telegram->buildReplyKeyboard([
        ['Вариант A', 'Вариант B'],
        ['Вариант C'],
    ], [
        'resize_keyboard' => true,
        'selective' => true,
    ]);

    $telegram->sendText(
        null,
        "🎯 Selective клавиатура (показывается только упомянутым пользователям)",
        ['reply_markup' => $selectiveKeyboard]
    );
    echo "✓ Selective клавиатура отправлена\n";
    sleep(1);

    echo "\n═══════════════════════════════════════════════════════════════\n";
    echo "  9. FORCE REPLY\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    $forceReply = $telegram->forceReply('Введите ваш ответ здесь...', false);

    $telegram->sendText(
        null,
        "✍️ Force Reply - принудительный ответ",
        ['reply_markup' => $forceReply]
    );
    echo "✓ Force Reply отправлен\n";
    sleep(1);

    echo "\n═══════════════════════════════════════════════════════════════\n";
    echo "  10. УДАЛЕНИЕ КЛАВИАТУРЫ\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    $removeKeyboard = $telegram->removeKeyboard();

    $telegram->sendText(
        null,
        "🗑️ Все reply клавиатуры удалены",
        ['reply_markup' => $removeKeyboard]
    );
    echo "✓ Клавиатура удалена\n";

    echo "\n═══════════════════════════════════════════════════════════════\n";
    echo "  РЕЗУЛЬТАТЫ ТЕСТИРОВАНИЯ\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    echo "✅ Протестировано 10 типов reply клавиатур:\n\n";
    echo "  1. ✓ Простая клавиатура (строки)\n";
    echo "  2. ✓ Клавиатура с эмодзи\n";
    echo "  3. ✓ Одноразовая клавиатура\n";
    echo "  4. ✓ Запрос контакта\n";
    echo "  5. ✓ Запрос локации\n";
    echo "  6. ✓ Запрос опроса\n";
    echo "  7. ✓ Многорядная клавиатура\n";
    echo "  8. ✓ Selective клавиатура\n";
    echo "  9. ✓ Force Reply\n";
    echo " 10. ✓ Удаление клавиатуры\n\n";

    echo "💡 Особенности:\n";
    echo "  • resize_keyboard - автоподстройка размера\n";
    echo "  • one_time_keyboard - скрывать после использования\n";
    echo "  • input_field_placeholder - текст-подсказка\n";
    echo "  • selective - показ только определенным пользователям\n";
    echo "  • request_contact - запрос контакта\n";
    echo "  • request_location - запрос местоположения\n";
    echo "  • request_poll - запрос создания опроса\n\n";

} catch (TelegramApiException $e) {
    echo "\n✗ Ошибка API: " . $e->getMessage() . "\n\n";
    exit(1);
} catch (Exception $e) {
    echo "\n✗ Ошибка: " . $e->getMessage() . "\n";
    echo "Файл: " . $e->getFile() . "\n";
    echo "Строка: " . $e->getLine() . "\n\n";
    exit(1);
}

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║           ТЕСТ REPLY КЛАВИАТУР ЗАВЕРШЕН                        ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

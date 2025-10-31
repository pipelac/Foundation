<?php

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

use App\Component\Logger;
use App\Component\Telegram;
use App\Component\Exception\TelegramApiException;

/**
 * Детальный тест опросов и голосований
 * Демонстрирует все типы и возможности polls
 */

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║         ДЕТАЛЬНЫЙ ТЕСТ ОПРОСОВ И ГОЛОСОВАНИЙ                   ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

try {
    $loggerConfig = [
        'directory' => __DIR__ . '/logs',
        'file_name' => 'telegram_polls_detailed.log',
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
    echo "  1. ОБЫЧНЫЙ ПУБЛИЧНЫЙ ОПРОС\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    $result = $telegram->sendPoll(
        null,
        'Какой фреймворк вы предпочитаете для PHP?',
        ['Laravel', 'Symfony', 'Yii2', 'CodeIgniter', 'Pure PHP']
    );

    $pollId1 = $result['result']['message_id'] ?? null;
    $poll = $result['result']['poll'] ?? [];
    
    echo "✓ Публичный опрос отправлен\n";
    echo "  • ID: {$poll['id']}\n";
    echo "  • Анонимность: " . ($poll['is_anonymous'] ? 'ДА' : 'НЕТ') . "\n";
    echo "  • Тип: {$poll['type']}\n";
    echo "  • Вариантов: " . count($poll['options']) . "\n\n";
    sleep(1);

    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  2. АНОНИМНЫЙ ОПРОС С МНОЖЕСТВЕННЫМ ВЫБОРОМ\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    $result = $telegram->sendPoll(
        null,
        'Какие языки программирования вы знаете? (можно выбрать несколько)',
        ['PHP', 'JavaScript', 'Python', 'Java', 'C++', 'Go', 'Rust', 'Ruby'],
        [
            'is_anonymous' => true,
            'allows_multiple_answers' => true,
        ]
    );

    $pollId2 = $result['result']['message_id'] ?? null;
    $poll = $result['result']['poll'] ?? [];
    
    echo "✓ Опрос с множественным выбором отправлен\n";
    echo "  • Анонимность: " . ($poll['is_anonymous'] ? 'ДА' : 'НЕТ') . "\n";
    echo "  • Множественный выбор: " . ($poll['allows_multiple_answers'] ? 'ДА' : 'НЕТ') . "\n";
    echo "  • Вариантов: " . count($poll['options']) . "\n\n";
    sleep(1);

    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  3. ВИКТОРИНА С ПРАВИЛЬНЫМ ОТВЕТОМ\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    $result = $telegram->sendPoll(
        null,
        'Какой год считается годом изобретения PHP?',
        ['1993', '1994', '1995', '1996'],
        [
            'type' => 'quiz',
            'correct_option_id' => 2,
            'explanation' => 'PHP был создан Расмусом Лердорфом в 1994 году, но публичный релиз состоялся в 1995 году',
        ]
    );

    $quizId1 = $result['result']['message_id'] ?? null;
    $poll = $result['result']['poll'] ?? [];
    
    echo "✓ Викторина отправлена\n";
    echo "  • Тип: {$poll['type']}\n";
    echo "  • Правильный ответ: #" . ($poll['correct_option_id'] ?? 'N/A') . "\n";
    echo "  • Пояснение: есть\n\n";
    sleep(1);

    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  4. ВИКТОРИНА С HTML ФОРМАТИРОВАНИЕМ В ПОЯСНЕНИИ\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    $result = $telegram->sendPoll(
        null,
        'Сколько битов в одном байте?',
        ['4 бита', '6 битов', '8 битов', '10 битов'],
        [
            'type' => 'quiz',
            'correct_option_id' => 2,
            'explanation' => '<b>1 байт = 8 битов</b>\n\nЭто <i>фундаментальная</i> единица информации в вычислительной технике.',
            'explanation_parse_mode' => Telegram::PARSE_MODE_HTML,
        ]
    );

    echo "✓ Викторина с HTML форматированием отправлена\n";
    echo "  • Форматирование: HTML\n\n";
    sleep(1);

    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  5. ОПРОС С АВТОМАТИЧЕСКИМ ЗАКРЫТИЕМ (60 секунд)\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    $result = $telegram->sendPoll(
        null,
        'Экспресс-опрос: что вы предпочитаете?',
        ['Кофе', 'Чай'],
        [
            'open_period' => 60,
        ]
    );

    $timedPollId = $result['result']['message_id'] ?? null;
    
    echo "✓ Опрос с таймером отправлен\n";
    echo "  • Время жизни: 60 секунд\n";
    echo "  • Закроется автоматически\n\n";
    sleep(1);

    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  6. ОПРОС С КОНКРЕТНОЙ ДАТОЙ ЗАКРЫТИЯ\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    $closeDate = time() + 120;
    
    $result = $telegram->sendPoll(
        null,
        'Опрос с фиксированной датой закрытия',
        ['Вариант А', 'Вариант Б', 'Вариант В'],
        [
            'close_date' => $closeDate,
        ]
    );

    echo "✓ Опрос с датой закрытия отправлен\n";
    echo "  • Закроется: " . date('H:i:s', $closeDate) . "\n";
    echo "  • Через: 120 секунд\n\n";
    sleep(1);

    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  7. ЗАКРЫТЫЙ ОПРОС (ИЗНАЧАЛЬНО)\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    $result = $telegram->sendPoll(
        null,
        'Этот опрос уже закрыт',
        ['Вариант 1', 'Вариант 2'],
        [
            'is_closed' => true,
        ]
    );

    echo "✓ Закрытый опрос отправлен\n";
    echo "  • Статус: закрыт изначально\n\n";
    sleep(1);

    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  8. ОПРОС С INLINE КЛАВИАТУРОЙ\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    $keyboard = $telegram->buildInlineKeyboard([
        [
            ['text' => '📊 Подробнее об опросе', 'callback_data' => 'poll_details'],
        ],
        [
            ['text' => '🔗 Поделиться', 'switch_inline_query' => 'Проголосуйте в опросе!'],
        ],
    ]);

    $result = $telegram->sendPoll(
        null,
        'Опрос с дополнительными действиями',
        ['Отлично', 'Хорошо', 'Нормально', 'Плохо'],
        [
            'reply_markup' => $keyboard,
        ]
    );

    echo "✓ Опрос с inline клавиатурой отправлен\n";
    echo "  • Кнопок: 2\n\n";
    sleep(1);

    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  9. НЕАНОНИМНАЯ ВИКТОРИНА\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    $result = $telegram->sendPoll(
        null,
        'Публичная викторина: столица России?',
        ['Санкт-Петербург', 'Москва', 'Екатеринбург', 'Новосибирск'],
        [
            'type' => 'quiz',
            'is_anonymous' => false,
            'correct_option_id' => 1,
            'explanation' => 'Москва - столица Российской Федерации',
        ]
    );

    echo "✓ Неанонимная викторина отправлена\n";
    echo "  • Будут видны ответы участников\n\n";
    sleep(1);

    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  10. ПРОВЕРКА ФУНКЦИИ ОСТАНОВКИ ОПРОСА\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    if ($pollId1) {
        echo "⏳ Ожидание 3 секунды перед остановкой...\n";
        sleep(3);

        $result = $telegram->stopPoll(null, $pollId1);
        $stoppedPoll = $result['result'] ?? [];
        
        echo "✓ Опрос остановлен\n";
        echo "  • ID опроса: {$stoppedPoll['id']}\n";
        echo "  • Статус: " . ($stoppedPoll['is_closed'] ? 'закрыт' : 'открыт') . "\n";
        echo "  • Всего голосов: " . ($stoppedPoll['total_voter_count'] ?? 0) . "\n";
        
        if (isset($stoppedPoll['options'])) {
            echo "  • Результаты:\n";
            foreach ($stoppedPoll['options'] as $index => $option) {
                $votes = $option['voter_count'] ?? 0;
                echo "    " . ($index + 1) . ". {$option['text']}: {$votes} голос(ов)\n";
            }
        }
        echo "\n";
    }

    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  11. ОСТАНОВКА С ЗАМЕНОЙ КЛАВИАТУРЫ\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    if ($pollId2) {
        sleep(2);

        $newKeyboard = $telegram->buildInlineKeyboard([
            [
                ['text' => '✅ Опрос завершен', 'callback_data' => 'poll_closed'],
            ],
            [
                ['text' => '📊 Посмотреть статистику', 'url' => 'https://t.me'],
            ],
        ]);

        $result = $telegram->stopPoll(null, $pollId2, $newKeyboard);
        
        echo "✓ Опрос остановлен с новой клавиатурой\n";
        echo "  • Клавиатура заменена\n\n";
    }

    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  РЕЗУЛЬТАТЫ ТЕСТИРОВАНИЯ\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    echo "✅ Протестировано 11 типов опросов:\n\n";
    echo "  1. ✓ Обычный публичный опрос\n";
    echo "  2. ✓ Анонимный с множественным выбором\n";
    echo "  3. ✓ Викторина с правильным ответом\n";
    echo "  4. ✓ Викторина с HTML форматированием\n";
    echo "  5. ✓ Опрос с автозакрытием (60 сек)\n";
    echo "  6. ✓ Опрос с фиксированной датой закрытия\n";
    echo "  7. ✓ Изначально закрытый опрос\n";
    echo "  8. ✓ Опрос с inline клавиатурой\n";
    echo "  9. ✓ Неанонимная викторина\n";
    echo " 10. ✓ Остановка опроса\n";
    echo " 11. ✓ Остановка с заменой клавиатуры\n\n";

    echo "💡 Поддерживаемые параметры опросов:\n";
    echo "  • is_anonymous - анонимность (true/false)\n";
    echo "  • type - тип ('regular' или 'quiz')\n";
    echo "  • allows_multiple_answers - множественный выбор\n";
    echo "  • correct_option_id - правильный ответ (для quiz)\n";
    echo "  • explanation - пояснение к ответу\n";
    echo "  • explanation_parse_mode - форматирование пояснения\n";
    echo "  • open_period - время жизни (5-600 сек)\n";
    echo "  • close_date - Unix timestamp закрытия\n";
    echo "  • is_closed - закрыть сразу\n";
    echo "  • reply_markup - inline клавиатура\n\n";

} catch (TelegramApiException $e) {
    echo "\n✗ Ошибка API: " . $e->getMessage() . "\n";
    echo "Код: " . $e->getStatusCode() . "\n\n";
    exit(1);
} catch (Exception $e) {
    echo "\n✗ Ошибка: " . $e->getMessage() . "\n";
    echo "Файл: " . $e->getFile() . "\n";
    echo "Строка: " . $e->getLine() . "\n\n";
    exit(1);
}

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║        ТЕСТ ОПРОСОВ И ГОЛОСОВАНИЙ ЗАВЕРШЕН                     ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

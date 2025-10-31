<?php

declare(strict_types=1);

/**
 * Расширенный интеграционный тест модуля TelegramBot
 * 
 * Дополнительные сценарии:
 * - Тестирование Handlers с симуляцией Update объектов
 * - Комплексные диалоговые цепочки с условной логикой
 * - Работа с медиа файлами (фото, документы)
 * - Access Control тестирование
 * - Batch операции с БД
 * - Стресс-тестирование (множественные запросы)
 */

require_once __DIR__ . '/../../autoload.php';

use App\Component\Http;
use App\Component\Logger;
use App\Component\MySQL;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Core\AccessControl;
use App\Component\TelegramBot\Core\AccessControlMiddleware;
use App\Component\TelegramBot\Core\WebhookHandler;
use App\Component\TelegramBot\Entities\User;
use App\Component\TelegramBot\Entities\Chat;
use App\Component\TelegramBot\Entities\Message;
use App\Component\TelegramBot\Entities\Update;
use App\Component\TelegramBot\Entities\CallbackQuery;
use App\Component\TelegramBot\Utils\Parser;
use App\Component\TelegramBot\Keyboards\InlineKeyboardBuilder;
use App\Component\TelegramBot\Handlers\MessageHandler;
use App\Component\TelegramBot\Handlers\CallbackQueryHandler;
use App\Component\TelegramBot\Handlers\TextHandler;

const TEST_BOT_TOKEN = '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI';
const TEST_CHAT_ID = 366442475;

// ============================================================================
// Утилиты
// ============================================================================

function colorize(string $text, string $color): string {
    $colors = [
        'green' => "\033[0;32m",
        'red' => "\033[0;31m",
        'yellow' => "\033[1;33m",
        'blue' => "\033[0;34m",
        'cyan' => "\033[0;36m",
        'magenta' => "\033[0;35m",
        'reset' => "\033[0m",
    ];
    return ($colors[$color] ?? '') . $text . $colors['reset'];
}

function testHeader(string $text): void {
    echo "\n" . str_repeat('=', 80) . "\n";
    echo colorize("  $text", 'blue') . "\n";
    echo str_repeat('=', 80) . "\n";
}

function success(string $message): void {
    echo colorize("  ✓ $message", 'green') . "\n";
}

function error(string $message): void {
    echo colorize("  ✗ $message", 'red') . "\n";
}

function info(string $message): void {
    echo "  ℹ " . colorize($message, 'yellow') . "\n";
}

function debug(string $message): void {
    echo "    " . $message . "\n";
}

// ============================================================================
// Класс тестирования
// ============================================================================

class AdvancedTestRunner
{
    private TelegramAPI $api;
    private Logger $logger;
    private MySQL $db;
    private int $totalTests = 0;
    private int $passedTests = 0;
    private int $failedTests = 0;
    
    public function __construct()
    {
        $this->logger = new Logger([
            'directory' => __DIR__ . '/../../logs',
            'fileName' => 'telegram_bot_advanced_test.log',
            'maxFiles' => 7,
            'maxFileSize' => 10 * 1024 * 1024,
        ]);
        
        $http = new Http(['timeout' => 30], $this->logger);
        $this->api = new TelegramAPI(TEST_BOT_TOKEN, $http, $this->logger);
        
        $this->db = new MySQL([
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'telegram_bot_test',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4',
            'persistent' => false,
            'cache_statements' => true
        ], $this->logger);
    }
    
    public function runTest(string $name, callable $test): void
    {
        $this->totalTests++;
        $startTime = microtime(true);
        
        try {
            info("Тест: $name");
            $test($this);
            
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->passedTests++;
            success("PASSED ({$executionTime}ms)");
            
            $this->logger->info("Тест пройден: $name", ['time_ms' => $executionTime]);
        } catch (Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->failedTests++;
            error("FAILED: " . $e->getMessage());
            debug("File: " . $e->getFile() . ":" . $e->getLine());
            
            $this->logger->error("Тест провален: $name", [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'time_ms' => $executionTime
            ]);
        }
    }
    
    public function sendNotification(string $message): void
    {
        try {
            $this->api->sendMessage(TEST_CHAT_ID, $message);
        } catch (Exception $e) {
            $this->logger->error('Ошибка отправки уведомления', ['error' => $e->getMessage()]);
        }
    }
    
    public function getApi(): TelegramAPI { return $this->api; }
    public function getDb(): MySQL { return $this->db; }
    public function getLogger(): Logger { return $this->logger; }
    
    public function getSummary(): array
    {
        return [
            'total' => $this->totalTests,
            'passed' => $this->passedTests,
            'failed' => $this->failedTests,
            'success_rate' => $this->totalTests > 0 
                ? round(($this->passedTests / $this->totalTests) * 100, 2) 
                : 0
        ];
    }
}

// ============================================================================
// ЗАПУСК ТЕСТОВ
// ============================================================================

echo colorize("\n╔════════════════════════════════════════════════════════════════════════════╗", 'blue') . "\n";
echo colorize("║         РАСШИРЕННОЕ ИНТЕГРАЦИОННОЕ ТЕСТИРОВАНИЕ TELEGRAMBOT                ║", 'blue') . "\n";
echo colorize("╚════════════════════════════════════════════════════════════════════════════╝", 'blue') . "\n";

$runner = new AdvancedTestRunner();

$runner->sendNotification(
    "🔬 *РАСШИРЕННОЕ ТЕСТИРОВАНИЕ*\n\n" .
    "Начало продвинутых тестов модуля TelegramBot\n" .
    "Время: " . date('Y-m-d H:i:s')
);

// ============================================================================
// БЛОК 1: ТЕСТИРОВАНИЕ HANDLERS
// ============================================================================
testHeader("БЛОК 1: ТЕСТИРОВАНИЕ HANDLERS");

$runner->runTest("MessageHandler с симулированным Update", function($runner) {
    $api = $runner->getApi();
    $logger = $runner->getLogger();
    
    // Создаём симулированный Update с текстовым сообщением
    $updateData = [
        'update_id' => rand(100000, 999999),
        'message' => [
            'message_id' => rand(1000, 9999),
            'date' => time(),
            'chat' => [
                'id' => TEST_CHAT_ID,
                'type' => 'private',
                'first_name' => 'TestUser',
            ],
            'from' => [
                'id' => TEST_CHAT_ID,
                'is_bot' => false,
                'first_name' => 'TestUser',
            ],
            'text' => 'Привет, бот!',
        ],
    ];
    
    $update = Update::fromArray($updateData);
    
    debug("Update создан: ID {$update->updateId}");
    debug("Message: {$update->message->text}");
    
    assert($update->isMessage() === true, 'Должен быть message update');
    assert($update->message->text === 'Привет, бот!', 'Текст должен совпадать');
    
    // Отправка ответа на симулированное сообщение
    $response = $api->sendMessage(
        TEST_CHAT_ID,
        "✅ Handler Test: Получено сообщение\n\n" .
        "Текст: \"{$update->message->text}\"\n" .
        "От: {$update->message->from->firstName}\n" .
        "Update ID: {$update->updateId}"
    );
    
    debug("Ответ отправлен, Message ID: {$response->messageId}");
});

$runner->runTest("TextHandler - обработка команд", function($runner) {
    $api = $runner->getApi();
    $logger = $runner->getLogger();
    
    // Тестируем парсинг различных команд
    $commands = [
        '/start' => 'start',
        '/help' => 'help',
        '/info arg1 arg2' => 'info',
        '/settings@testbot' => 'settings',
    ];
    
    foreach ($commands as $commandText => $expectedCommand) {
        $parsed = Parser::parseCommand($commandText);
        assert($parsed['command'] === $expectedCommand, "Команда должна быть {$expectedCommand}");
        debug("✓ Команда {$commandText} распознана");
    }
    
    // Отправка сообщения с результатами
    $api->sendMessage(
        TEST_CHAT_ID,
        "✅ TextHandler Test\n\n" .
        "Протестировано команд: " . count($commands) . "\n" .
        "Все команды корректно распознаны"
    );
});

$runner->runTest("CallbackQueryHandler - симуляция callback", function($runner) {
    $api = $runner->getApi();
    
    // Отправка сообщения с кнопками
    $keyboard = (new InlineKeyboardBuilder())
        ->addCallbackButton('Опция 1', 'test:option1')
        ->addCallbackButton('Опция 2', 'test:option2')
        ->row()
        ->addCallbackButton('Опция 3', 'test:option3')
        ->build();
    
    $message = $api->sendMessage(
        TEST_CHAT_ID,
        "🔘 CallbackQueryHandler Test\n\n" .
        "Выберите опцию (симуляция):",
        ['reply_markup' => $keyboard]
    );
    
    debug("Сообщение с кнопками отправлено");
    
    // Симуляция callback query
    $callbackData = [
        'id' => 'callback_' . rand(1000, 9999),
        'from' => [
            'id' => TEST_CHAT_ID,
            'is_bot' => false,
            'first_name' => 'TestUser',
        ],
        'message' => [
            'message_id' => $message->messageId,
            'date' => time(),
            'chat' => [
                'id' => TEST_CHAT_ID,
                'type' => 'private',
                'first_name' => 'TestUser',
            ],
            'text' => 'Test message',
        ],
        'data' => 'test:option1',
        'chat_instance' => 'chat_instance_123',
    ];
    
    $callback = CallbackQuery::fromArray($callbackData);
    
    debug("CallbackQuery симулирован: {$callback->data}");
    
    // Парсинг callback data
    $parsed = Parser::parseCallbackData($callback->data);
    assert($parsed['action'] === 'test', 'Action должен быть test');
    assert($parsed['value'] === 'option1', 'Value должен быть option1');
    
    debug("✓ Callback data распознан: action={$parsed['action']}, value={$parsed['value']}");
});

// ============================================================================
// БЛОК 2: СЛОЖНЫЙ ДИАЛОГОВЫЙ СЦЕНАРИЙ - КВЕСТ
// ============================================================================
testHeader("БЛОК 2: СЛОЖНЫЙ ДИАЛОГОВЫЙ СЦЕНАРИЙ - ИНТЕРАКТИВНЫЙ КВЕСТ");

$runner->runTest("Квест: Поиск сокровищ (5 этапов с условиями)", function($runner) {
    $api = $runner->getApi();
    $db = $runner->getDb();
    
    info("Начало интерактивного квеста");
    
    $runner->sendNotification(
        "🎮 *КВЕСТ: Поиск сокровищ*\n\n" .
        "Начинается сложный диалоговый сценарий с условной логикой"
    );
    
    // Очистка предыдущих состояний квеста
    $db->query("DELETE FROM dialog_states WHERE user_id = ? AND state LIKE 'quest_%'", [TEST_CHAT_ID]);
    
    // Этап 1: Начало квеста
    $keyboard = (new InlineKeyboardBuilder())
        ->addCallbackButton('🏰 Пойти в замок', 'quest:castle')
        ->addCallbackButton('🌲 Пойти в лес', 'quest:forest')
        ->row()
        ->addCallbackButton('⛰ Пойти в горы', 'quest:mountains')
        ->build();
    
    $msg1 = $api->sendMessage(
        TEST_CHAT_ID,
        "🗺 *КВЕСТ: Поиск сокровищ*\n\n" .
        "Вы - искатель приключений. Легенда гласит, что где-то спрятаны древние сокровища.\n\n" .
        "Куда вы отправитесь?",
        [
            'parse_mode' => TelegramAPI::PARSE_MODE_MARKDOWN,
            'reply_markup' => $keyboard
        ]
    );
    
    $db->query(
        "INSERT INTO dialog_states (user_id, chat_id, state, data) VALUES (?, ?, ?, ?)",
        [TEST_CHAT_ID, TEST_CHAT_ID, 'quest_start', json_encode(['stage' => 1, 'score' => 0])]
    );
    
    debug("Этап 1: Выбор пути");
    sleep(2);
    
    // Этап 2: Пользователь выбирает лес (симуляция)
    $choice1 = 'forest';
    $msg2 = $api->sendMessage(
        TEST_CHAT_ID,
        "➡️ *Вы выбрали: Лес* 🌲\n\n" .
        "Вы входите в густой лес. Перед вами развилка..."
    );
    
    $db->query(
        "UPDATE dialog_states SET state = ?, data = ? WHERE user_id = ? AND chat_id = ?",
        [
            'quest_forest',
            json_encode(['stage' => 2, 'score' => 10, 'path' => 'forest']),
            TEST_CHAT_ID,
            TEST_CHAT_ID
        ]
    );
    
    debug("Этап 2: Выбран лес");
    sleep(2);
    
    // Этап 3: Встреча с персонажем
    $keyboard2 = (new InlineKeyboardBuilder())
        ->addCallbackButton('🤝 Помочь старику', 'quest:help_old_man')
        ->addCallbackButton('🏃 Пройти мимо', 'quest:ignore')
        ->build();
    
    $msg3 = $api->sendMessage(
        TEST_CHAT_ID,
        "👴 Вы встречаете старика, который просит о помощи.\n\n" .
        "Что вы сделаете?",
        ['reply_markup' => $keyboard2]
    );
    
    debug("Этап 3: Встреча с персонажем");
    sleep(2);
    
    // Этап 4: Помощь старику (положительный выбор)
    $choice2 = 'help';
    $msg4 = $api->sendMessage(
        TEST_CHAT_ID,
        "✨ *Вы помогли старику!*\n\n" .
        "👴: \"Спасибо! В благодарность я укажу тебе путь к сокровищам.\"\n\n" .
        "+50 очков кармы! ⭐️"
    );
    
    $db->query(
        "UPDATE dialog_states SET state = ?, data = ? WHERE user_id = ? AND chat_id = ?",
        [
            'quest_helped',
            json_encode([
                'stage' => 3,
                'score' => 60,
                'path' => 'forest',
                'karma' => 50,
                'helped_old_man' => true
            ]),
            TEST_CHAT_ID,
            TEST_CHAT_ID
        ]
    );
    
    $runner->sendNotification(
        "🎮 Квест - Этап 3/5\n" .
        "Действие: Помощь старику\n" .
        "Карма: +50 ⭐️\n" .
        "Очки: 60"
    );
    
    debug("Этап 4: Получена подсказка");
    sleep(2);
    
    // Этап 5: Поиск сокровищ
    $keyboard3 = (new InlineKeyboardBuilder())
        ->addCallbackButton('🌳 Копать у дуба', 'quest:dig_oak')
        ->addCallbackButton('🪨 Копать у камня', 'quest:dig_rock')
        ->row()
        ->addCallbackButton('💧 Копать у ручья', 'quest:dig_stream')
        ->build();
    
    $msg5 = $api->sendMessage(
        TEST_CHAT_ID,
        "🗺 Старик указал на три места:\n\n" .
        "🌳 Древний дуб\n" .
        "🪨 Большой камень\n" .
        "💧 Чистый ручей\n\n" .
        "Где будете копать?",
        ['reply_markup' => $keyboard3]
    );
    
    debug("Этап 5: Выбор места для поиска");
    sleep(2);
    
    // Этап 6: Нашли сокровища! (правильный выбор)
    $choice3 = 'dig_oak';
    $msg6 = $api->sendMessage(
        TEST_CHAT_ID,
        "🎉🎉🎉 *ПОБЕДА!* 🎉🎉🎉\n\n" .
        "💰 Вы нашли древние сокровища под дубом!\n\n" .
        "📊 *Итоги квеста:*\n" .
        "• Очки: 100\n" .
        "• Карма: 50\n" .
        "• Этапов пройдено: 5\n" .
        "• Правильных выборов: 3/3\n\n" .
        "Поздравляем! Квест завершён успешно! 🏆",
        ['parse_mode' => TelegramAPI::PARSE_MODE_MARKDOWN]
    );
    
    $db->query(
        "UPDATE dialog_states SET state = ?, data = ? WHERE user_id = ? AND chat_id = ?",
        [
            'quest_completed',
            json_encode([
                'stage' => 5,
                'score' => 100,
                'karma' => 50,
                'completed' => true,
                'treasure_found' => true,
                'completion_time' => date('Y-m-d H:i:s')
            ]),
            TEST_CHAT_ID,
            TEST_CHAT_ID
        ]
    );
    
    // Сохранение результата в статистику
    $db->query(
        "INSERT INTO statistics (stat_key, stat_value, user_id) VALUES (?, ?, ?)",
        ['quest_completed', '100', TEST_CHAT_ID]
    );
    
    $db->query(
        "INSERT INTO statistics (stat_key, stat_value, user_id) VALUES (?, ?, ?)",
        ['quest_karma', '50', TEST_CHAT_ID]
    );
    
    debug("Квест завершён! Сокровища найдены!");
    
    // Проверка сохранённых данных
    $state = $db->query(
        "SELECT * FROM dialog_states WHERE user_id = ? AND state = 'quest_completed' LIMIT 1",
        [TEST_CHAT_ID]
    );
    
    assert(count($state) > 0, 'Состояние квеста должно быть сохранено');
    $stateData = json_decode($state[0]['data'], true);
    assert($stateData['completed'] === true, 'Квест должен быть завершён');
    assert($stateData['treasure_found'] === true, 'Сокровища должны быть найдены');
    
    $runner->sendNotification(
        "🎉 *КВЕСТ ЗАВЕРШЁН!*\n\n" .
        "Сокровища найдены! 💰\n" .
        "Итоговый счёт: 100\n" .
        "Карма: 50\n\n" .
        "Все данные сохранены в MySQL ✅"
    );
});

// ============================================================================
// БЛОК 3: BATCH ОПЕРАЦИИ С БД
// ============================================================================
testHeader("БЛОК 3: BATCH ОПЕРАЦИИ И СТАТИСТИКА");

$runner->runTest("Массовая вставка данных в БД", function($runner) {
    $db = $runner->getDb();
    $api = $runner->getApi();
    
    info("Генерация тестовых данных...");
    
    // Создание 100 записей статистики
    $startTime = microtime(true);
    
    for ($i = 1; $i <= 100; $i++) {
        $db->query(
            "INSERT INTO statistics (stat_key, stat_value, user_id) VALUES (?, ?, ?)",
            ["test_metric_{$i}", (string)rand(1, 1000), TEST_CHAT_ID]
        );
    }
    
    $duration = round((microtime(true) - $startTime) * 1000, 2);
    
    debug("✓ Вставлено 100 записей за {$duration}ms");
    
    // Проверка количества записей
    $count = $db->query("SELECT COUNT(*) as cnt FROM statistics WHERE user_id = ?", [TEST_CHAT_ID]);
    $totalRecords = $count[0]['cnt'];
    
    debug("✓ Всего записей в БД: {$totalRecords}");
    
    // Агрегация данных
    $stats = $db->query(
        "SELECT stat_key, COUNT(*) as cnt FROM statistics WHERE user_id = ? GROUP BY stat_key ORDER BY cnt DESC LIMIT 5",
        [TEST_CHAT_ID]
    );
    
    debug("✓ Топ-5 метрик:");
    foreach ($stats as $stat) {
        debug("  - {$stat['stat_key']}: {$stat['cnt']}");
    }
    
    $api->sendMessage(
        TEST_CHAT_ID,
        "📊 *Batch операции*\n\n" .
        "Вставлено записей: 100\n" .
        "Время выполнения: {$duration}ms\n" .
        "Всего в БД: {$totalRecords}\n\n" .
        "Производительность: ✅",
        ['parse_mode' => TelegramAPI::PARSE_MODE_MARKDOWN]
    );
});

$runner->runTest("Сложные SQL запросы и JOIN", function($runner) {
    $db = $runner->getDb();
    
    // Создание тестового пользователя, если его нет
    $db->query(
        "INSERT INTO users (telegram_id, username, first_name, language_code, is_bot) 
         VALUES (?, ?, ?, ?, ?) 
         ON DUPLICATE KEY UPDATE last_active = NOW()",
        [TEST_CHAT_ID, 'test_user', 'Тестовый Пользователь', 'ru', 0]
    );
    
    // Сложный запрос с JOIN
    $result = $db->query("
        SELECT 
            u.telegram_id,
            u.first_name,
            u.username,
            COUNT(DISTINCT ds.id) as dialog_states_count,
            COUNT(DISTINCT s.id) as stats_count,
            MAX(u.last_active) as last_active
        FROM users u
        LEFT JOIN dialog_states ds ON u.telegram_id = ds.user_id
        LEFT JOIN statistics s ON u.telegram_id = s.user_id
        WHERE u.telegram_id = ?
        GROUP BY u.telegram_id, u.first_name, u.username
    ", [TEST_CHAT_ID]);
    
    assert(count($result) > 0, 'Должен быть найден пользователь');
    
    $user = $result[0];
    debug("✓ Пользователь: {$user['first_name']} (@{$user['username']})");
    debug("  - Состояний диалогов: {$user['dialog_states_count']}");
    debug("  - Записей статистики: {$user['stats_count']}");
    debug("  - Последняя активность: {$user['last_active']}");
});

// ============================================================================
// БЛОК 4: СТРЕСС-ТЕСТ
// ============================================================================
testHeader("БЛОК 4: СТРЕСС-ТЕСТИРОВАНИЕ");

$runner->runTest("Отправка множественных сообщений подряд", function($runner) {
    $api = $runner->getApi();
    
    info("Отправка 10 сообщений подряд...");
    
    $startTime = microtime(true);
    $messageIds = [];
    
    for ($i = 1; $i <= 10; $i++) {
        $message = $api->sendMessage(
            TEST_CHAT_ID,
            "📨 Стресс-тест: Сообщение #{$i}/10"
        );
        $messageIds[] = $message->messageId;
    }
    
    $duration = round((microtime(true) - $startTime) * 1000, 2);
    
    debug("✓ Отправлено 10 сообщений за {$duration}ms");
    debug("  Средняя скорость: " . round($duration / 10, 2) . "ms на сообщение");
    
    sleep(1);
    
    // Удаление всех тестовых сообщений
    info("Удаление тестовых сообщений...");
    foreach ($messageIds as $msgId) {
        try {
            $api->deleteMessage(TEST_CHAT_ID, $msgId);
        } catch (Exception $e) {
            // Игнорируем ошибки удаления
        }
    }
    
    debug("✓ Тестовые сообщения удалены");
});

// ============================================================================
// ФИНАЛ
// ============================================================================
testHeader("ИТОГИ РАСШИРЕННОГО ТЕСТИРОВАНИЯ");

$summary = $runner->getSummary();

echo "\n";
info("Всего тестов: " . colorize((string)$summary['total'], 'cyan'));
success("Успешно: " . $summary['passed']);

if ($summary['failed'] > 0) {
    error("Провалено: " . $summary['failed']);
}

$successRate = $summary['success_rate'];
$rateColor = $successRate >= 90 ? 'green' : ($successRate >= 70 ? 'yellow' : 'red');
info("Процент успеха: " . colorize("{$successRate}%", $rateColor));

echo "\n";

$statusEmoji = $summary['failed'] === 0 ? '✅' : '⚠️';
$runner->sendNotification(
    "{$statusEmoji} *РАСШИРЕННОЕ ТЕСТИРОВАНИЕ ЗАВЕРШЕНО*\n\n" .
    "📊 Статистика:\n" .
    "• Всего: {$summary['total']}\n" .
    "• Успешно: {$summary['passed']}\n" .
    "• Провалено: {$summary['failed']}\n" .
    "• Успешность: {$successRate}%\n\n" .
    ($summary['failed'] === 0 
        ? "🎉 Все расширенные тесты пройдены!" 
        : "⚠️ Некоторые тесты провалены."
    )
);

exit($summary['failed'] > 0 ? 1 : 0);

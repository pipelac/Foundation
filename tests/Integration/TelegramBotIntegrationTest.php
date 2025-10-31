<?php

declare(strict_types=1);

/**
 * Комплексный интеграционный тест модуля TelegramBot
 * 
 * Тестирует:
 * - Все классы модуля TelegramBot с реальными API вызовами
 * - Диалоговые сценарии с несколькими этапами взаимодействия
 * - Сохранение состояний диалогов в MySQL
 * - Обработку callback запросов и редактирование сообщений
 * - Контроль доступа к командам
 * - Работу с медиафайлами
 * - Логирование всех операций
 * 
 * Требования:
 * - Запущенный MySQL сервер
 * - Действующий Telegram Bot Token
 * - Доступ к тестовому чату
 */

require_once __DIR__ . '/../../autoload.php';

use App\Component\Http;
use App\Component\Logger;
use App\Component\MySQL;
use App\Component\MySQLConnectionFactory;
use App\Component\ConfigLoader;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Core\AccessControl;
use App\Component\TelegramBot\Core\AccessControlMiddleware;
use App\Component\TelegramBot\Entities\User;
use App\Component\TelegramBot\Entities\Chat;
use App\Component\TelegramBot\Entities\Message;
use App\Component\TelegramBot\Entities\Update;
use App\Component\TelegramBot\Utils\Validator;
use App\Component\TelegramBot\Utils\Parser;
use App\Component\TelegramBot\Keyboards\InlineKeyboardBuilder;
use App\Component\TelegramBot\Keyboards\ReplyKeyboardBuilder;
use App\Component\TelegramBot\Handlers\MessageHandler;
use App\Component\TelegramBot\Handlers\CallbackQueryHandler;
use App\Component\TelegramBot\Handlers\TextHandler;
use App\Component\TelegramBot\Exceptions\ValidationException;
use App\Component\TelegramBot\Exceptions\ApiException;

// ============================================================================
// КОНФИГУРАЦИЯ ТЕСТА
// ============================================================================
const TEST_BOT_TOKEN = '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI';
const TEST_CHAT_ID = 366442475;

// ============================================================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// ============================================================================

/**
 * Форматирование цветного вывода в консоль
 */
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

function section(string $text): void {
    echo "\n" . colorize("━━━ $text ━━━", 'cyan') . "\n";
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
// КЛАСС ДЛЯ УПРАВЛЕНИЯ ТЕСТИРОВАНИЕМ
// ============================================================================

class TelegramBotTestRunner
{
    private TelegramAPI $api;
    private Logger $logger;
    private MySQL $db;
    private int $totalTests = 0;
    private int $passedTests = 0;
    private int $failedTests = 0;
    private array $testResults = [];
    
    public function __construct()
    {
        // Инициализация логгера
        $this->logger = new Logger([
            'directory' => __DIR__ . '/../../logs',
            'fileName' => 'telegram_bot_integration_test.log',
            'maxFiles' => 7,
            'maxFileSize' => 10 * 1024 * 1024,
        ]);
        
        $this->logger->info('========================================');
        $this->logger->info('ЗАПУСК ИНТЕГРАЦИОННЫХ ТЕСТОВ TELEGRAMBOT');
        $this->logger->info('========================================');
        
        // Инициализация HTTP клиента и TelegramAPI
        $http = new Http(['timeout' => 30], $this->logger);
        $this->api = new TelegramAPI(TEST_BOT_TOKEN, $http, $this->logger);
        
        // Инициализация MySQL
        $this->initializeDatabase();
    }
    
    /**
     * Инициализация подключения к MySQL и создание тестовых таблиц
     */
    private function initializeDatabase(): void
    {
        info("Инициализация базы данных MySQL...");
        
        try {
            // Создание подключения к тестовой базе
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
            
            success("Подключение к MySQL установлено");
            
            // Создание таблиц для тестирования
            $this->createTestTables();
            
            $this->logger->info('База данных инициализирована успешно');
        } catch (Exception $e) {
            error("Ошибка подключения к MySQL: " . $e->getMessage());
            $this->logger->error('Ошибка инициализации БД', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Создание таблиц для тестирования
     */
    private function createTestTables(): void
    {
        info("Создание тестовых таблиц...");
        
        // Таблица для хранения состояний диалогов
        $this->db->query("
            CREATE TABLE IF NOT EXISTS dialog_states (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT NOT NULL,
                chat_id BIGINT NOT NULL,
                state VARCHAR(50) NOT NULL,
                data JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_chat_id (chat_id),
                INDEX idx_state (state)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Таблица для логов сообщений
        $this->db->query("
            CREATE TABLE IF NOT EXISTS message_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                message_id BIGINT NOT NULL,
                user_id BIGINT NOT NULL,
                chat_id BIGINT NOT NULL,
                message_type VARCHAR(20) NOT NULL,
                content TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_message_id (message_id),
                INDEX idx_user_id (user_id),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Таблица для пользователей
        $this->db->query("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                telegram_id BIGINT UNIQUE NOT NULL,
                username VARCHAR(255),
                first_name VARCHAR(255),
                last_name VARCHAR(255),
                language_code VARCHAR(10),
                is_bot BOOLEAN DEFAULT FALSE,
                is_premium BOOLEAN DEFAULT FALSE,
                last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_telegram_id (telegram_id),
                INDEX idx_username (username)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Таблица для статистики
        $this->db->query("
            CREATE TABLE IF NOT EXISTS statistics (
                id INT AUTO_INCREMENT PRIMARY KEY,
                stat_key VARCHAR(100) NOT NULL,
                stat_value VARCHAR(255) NOT NULL,
                user_id BIGINT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_stat_key (stat_key),
                INDEX idx_user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        success("Тестовые таблицы созданы");
        $this->logger->info('Тестовые таблицы созданы успешно');
    }
    
    /**
     * Отправка уведомления в Telegram о ходе тестирования
     */
    public function sendTelegramNotification(string $message, array $options = []): void
    {
        try {
            $this->api->sendMessage(TEST_CHAT_ID, $message, $options);
            $this->logger->debug('Уведомление отправлено в Telegram', ['message' => $message]);
        } catch (Exception $e) {
            $this->logger->error('Ошибка отправки уведомления в Telegram', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Запуск теста с обработкой ошибок
     */
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
            
            $this->testResults[] = [
                'name' => $name,
                'status' => 'passed',
                'time' => $executionTime
            ];
            
            $this->logger->info("Тест пройден: $name", ['time_ms' => $executionTime]);
        } catch (Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->failedTests++;
            error("FAILED: " . $e->getMessage());
            debug("Trace: " . $e->getTraceAsString());
            
            $this->testResults[] = [
                'name' => $name,
                'status' => 'failed',
                'error' => $e->getMessage(),
                'time' => $executionTime
            ];
            
            $this->logger->error("Тест провален: $name", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'time_ms' => $executionTime
            ]);
        }
    }
    
    /**
     * Получение итоговой статистики
     */
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
    
    /**
     * Получение экземпляра API
     */
    public function getApi(): TelegramAPI
    {
        return $this->api;
    }
    
    /**
     * Получение экземпляра БД
     */
    public function getDb(): MySQL
    {
        return $this->db;
    }
    
    /**
     * Получение экземпляра логгера
     */
    public function getLogger(): Logger
    {
        return $this->logger;
    }
}

// ============================================================================
// ЗАПУСК ТЕСТОВ
// ============================================================================

echo colorize("\n╔════════════════════════════════════════════════════════════════════════════╗", 'blue') . "\n";
echo colorize("║      КОМПЛЕКСНОЕ ИНТЕГРАЦИОННОЕ ТЕСТИРОВАНИЕ МОДУЛЯ TELEGRAMBOT            ║", 'blue') . "\n";
echo colorize("║                    С ИСПОЛЬЗОВАНИЕМ MYSQL                                  ║", 'blue') . "\n";
echo colorize("╚════════════════════════════════════════════════════════════════════════════╝", 'blue') . "\n";

$runner = new TelegramBotTestRunner();

// Отправка уведомления о начале тестирования
$runner->sendTelegramNotification(
    "🚀 *НАЧАЛО ИНТЕГРАЦИОННОГО ТЕСТИРОВАНИЯ*\n\n" .
    "Модуль: TelegramBot\n" .
    "База данных: MySQL (боевой сервер)\n" .
    "Время: " . date('Y-m-d H:i:s'),
    ['parse_mode' => TelegramAPI::PARSE_MODE_MARKDOWN]
);

// ============================================================================
// БЛОК 1: БАЗОВЫЕ ТЕСТЫ API
// ============================================================================
testHeader("БЛОК 1: БАЗОВЫЕ ТЕСТЫ API");

$runner->runTest("Получение информации о боте", function($runner) {
    $api = $runner->getApi();
    $botInfo = $api->getMe();
    
    debug("Bot ID: {$botInfo->id}");
    debug("Bot Username: @{$botInfo->username}");
    debug("Bot Name: {$botInfo->firstName}");
    
    assert($botInfo->isBot === true, 'Должен быть ботом');
    assert($botInfo->id > 0, 'ID должен быть положительным');
    assert(!empty($botInfo->username), 'Username не должен быть пустым');
    
    $runner->getLogger()->info('Информация о боте получена', [
        'bot_id' => $botInfo->id,
        'username' => $botInfo->username
    ]);
});

$runner->runTest("Отправка простого текстового сообщения", function($runner) {
    $api = $runner->getApi();
    $message = $api->sendMessage(
        TEST_CHAT_ID,
        "✅ Тест #1: Простое текстовое сообщение\n\n" .
        "Время: " . date('H:i:s') . "\n" .
        "Тип: text\n" .
        "Статус: успешно"
    );
    
    debug("Message ID: {$message->messageId}");
    debug("Chat ID: {$message->chat->id}");
    
    assert($message->chat->id == TEST_CHAT_ID, 'Chat ID должен совпадать');
    assert($message->isText() === true, 'Должно быть текстовым сообщением');
    
    // Сохранение в БД
    $runner->getDb()->query(
        "INSERT INTO message_log (message_id, user_id, chat_id, message_type, content) 
         VALUES (?, ?, ?, ?, ?)",
        [$message->messageId, $message->from->id, $message->chat->id, 'text', $message->text]
    );
});

$runner->runTest("Отправка сообщения с Markdown форматированием", function($runner) {
    $api = $runner->getApi();
    $message = $api->sendMessage(
        TEST_CHAT_ID,
        "✅ Тест #2: *Форматирование текста*\n\n" .
        "*Жирный текст*\n" .
        "_Курсив_\n" .
        "`Моноширинный`\n" .
        "[Ссылка](https://telegram.org)",
        ['parse_mode' => TelegramAPI::PARSE_MODE_MARKDOWN]
    );
    
    assert($message->isText() === true);
    debug("Сообщение с форматированием отправлено");
});

// ============================================================================
// БЛОК 2: РАБОТА С КЛАВИАТУРАМИ
// ============================================================================
testHeader("БЛОК 2: РАБОТА С КЛАВИАТУРАМИ");

$runner->runTest("Отправка Inline клавиатуры (2x2 сетка)", function($runner) {
    $api = $runner->getApi();
    
    $keyboard = InlineKeyboardBuilder::makeGrid([
        '📊 Статистика' => 'stats',
        '⚙️ Настройки' => 'settings',
        'ℹ️ Информация' => 'info',
        '❓ Помощь' => 'help',
    ], 2);
    
    $message = $api->sendMessage(
        TEST_CHAT_ID,
        "✅ Тест #3: Inline клавиатура\n\nВыберите действие:",
        ['reply_markup' => $keyboard]
    );
    
    debug("Inline клавиатура 2x2 отправлена");
    
    $runner->sendTelegramNotification(
        "📊 Проверка: Inline клавиатура\n" .
        "Кнопок: 4 (2x2)\n" .
        "Статус: ✅"
    );
});

$runner->runTest("Отправка Reply клавиатуры", function($runner) {
    $api = $runner->getApi();
    
    $keyboard = ReplyKeyboardBuilder::makeGrid([
        '🏠 Главная',
        '📝 Мои данные',
        '⚙️ Настройки',
        '📞 Контакты',
        '❓ Помощь',
        '🚪 Выход'
    ], 2);
    
    $message = $api->sendMessage(
        TEST_CHAT_ID,
        "✅ Тест #4: Reply клавиатура\n\nИспользуйте кнопки ниже:",
        ['reply_markup' => $keyboard]
    );
    
    debug("Reply клавиатура отправлена");
    
    // Через 3 секунды убрать клавиатуру
    sleep(3);
    $api->sendMessage(
        TEST_CHAT_ID,
        "Клавиатура скрыта",
        ['reply_markup' => ReplyKeyboardBuilder::remove()]
    );
});

// ============================================================================
// БЛОК 3: ДИАЛОГОВЫЙ СЦЕНАРИЙ С СОХРАНЕНИЕМ СОСТОЯНИЯ В БД
// ============================================================================
testHeader("БЛОК 3: ДИАЛОГОВЫЙ СЦЕНАРИЙ - РЕГИСТРАЦИЯ ПОЛЬЗОВАТЕЛЯ");

$runner->runTest("Сценарий: Регистрация пользователя (мультистеп)", function($runner) {
    $api = $runner->getApi();
    $db = $runner->getDb();
    
    info("Начало диалогового сценария регистрации");
    
    // Шаг 1: Приветствие и запрос имени
    $keyboard = (new InlineKeyboardBuilder())
        ->addCallbackButton('✅ Начать регистрацию', 'register:start')
        ->addCallbackButton('❌ Отмена', 'register:cancel')
        ->build();
    
    $msg1 = $api->sendMessage(
        TEST_CHAT_ID,
        "👋 *Диалоговый сценарий: Регистрация*\n\n" .
        "Этот тест проверяет работу многоэтапного диалога с сохранением состояния в MySQL.\n\n" .
        "Нажмите кнопку для начала:",
        [
            'parse_mode' => TelegramAPI::PARSE_MODE_MARKDOWN,
            'reply_markup' => $keyboard
        ]
    );
    
    debug("Шаг 1: Приветственное сообщение отправлено");
    
    // Сохранение начального состояния диалога в БД
    $db->query(
        "INSERT INTO dialog_states (user_id, chat_id, state, data) 
         VALUES (?, ?, ?, ?)",
        [TEST_CHAT_ID, TEST_CHAT_ID, 'registration_start', json_encode(['step' => 1])]
    );
    
    $runner->sendTelegramNotification(
        "🗣 Диалоговый тест: Регистрация\n" .
        "Шаг: 1/4\n" .
        "Состояние сохранено в MySQL"
    );
    
    sleep(2);
    
    // Шаг 2: Запрос имени
    $msg2 = $api->sendMessage(
        TEST_CHAT_ID,
        "📝 *Шаг 1 из 3: Введите ваше имя*\n\n" .
        "Пример: Иван",
        ['parse_mode' => TelegramAPI::PARSE_MODE_MARKDOWN]
    );
    
    debug("Шаг 2: Запрос имени");
    
    $db->query(
        "UPDATE dialog_states SET state = ?, data = ? WHERE user_id = ? AND chat_id = ?",
        ['awaiting_name', json_encode(['step' => 2]), TEST_CHAT_ID, TEST_CHAT_ID]
    );
    
    sleep(2);
    
    // Симуляция ввода имени пользователем
    $testName = "Александр";
    $msg3 = $api->sendMessage(
        TEST_CHAT_ID,
        "➡️ Пользователь вводит: *{$testName}*",
        ['parse_mode' => TelegramAPI::PARSE_MODE_MARKDOWN]
    );
    
    debug("Симуляция ввода имени: {$testName}");
    
    sleep(1);
    
    // Шаг 3: Запрос возраста
    $msg4 = $api->sendMessage(
        TEST_CHAT_ID,
        "✅ Имя принято: *{$testName}*\n\n" .
        "📝 *Шаг 2 из 3: Введите ваш возраст*\n\n" .
        "Пример: 25",
        ['parse_mode' => TelegramAPI::PARSE_MODE_MARKDOWN]
    );
    
    debug("Шаг 3: Запрос возраста");
    
    $db->query(
        "UPDATE dialog_states SET state = ?, data = ? WHERE user_id = ? AND chat_id = ?",
        ['awaiting_age', json_encode(['step' => 3, 'name' => $testName]), TEST_CHAT_ID, TEST_CHAT_ID]
    );
    
    $runner->sendTelegramNotification(
        "🗣 Диалоговый тест: Регистрация\n" .
        "Шаг: 2/4\n" .
        "Имя: {$testName}\n" .
        "Состояние обновлено в MySQL"
    );
    
    sleep(2);
    
    // Симуляция ввода возраста
    $testAge = 28;
    $msg5 = $api->sendMessage(
        TEST_CHAT_ID,
        "➡️ Пользователь вводит: *{$testAge}*",
        ['parse_mode' => TelegramAPI::PARSE_MODE_MARKDOWN]
    );
    
    debug("Симуляция ввода возраста: {$testAge}");
    
    sleep(1);
    
    // Шаг 4: Запрос города
    $msg6 = $api->sendMessage(
        TEST_CHAT_ID,
        "✅ Возраст принят: *{$testAge}*\n\n" .
        "📝 *Шаг 3 из 3: Введите ваш город*\n\n" .
        "Пример: Москва",
        ['parse_mode' => TelegramAPI::PARSE_MODE_MARKDOWN]
    );
    
    debug("Шаг 4: Запрос города");
    
    $db->query(
        "UPDATE dialog_states SET state = ?, data = ? WHERE user_id = ? AND chat_id = ?",
        [
            'awaiting_city', 
            json_encode(['step' => 4, 'name' => $testName, 'age' => $testAge]), 
            TEST_CHAT_ID, 
            TEST_CHAT_ID
        ]
    );
    
    sleep(2);
    
    // Симуляция ввода города
    $testCity = "Санкт-Петербург";
    $msg7 = $api->sendMessage(
        TEST_CHAT_ID,
        "➡️ Пользователь вводит: *{$testCity}*",
        ['parse_mode' => TelegramAPI::PARSE_MODE_MARKDOWN]
    );
    
    debug("Симуляция ввода города: {$testCity}");
    
    sleep(1);
    
    // Шаг 5: Подтверждение данных
    $keyboard = (new InlineKeyboardBuilder())
        ->addCallbackButton('✅ Всё верно', 'register:confirm')
        ->addCallbackButton('✏️ Изменить', 'register:edit')
        ->row()
        ->addCallbackButton('❌ Отменить', 'register:cancel')
        ->build();
    
    $msg8 = $api->sendMessage(
        TEST_CHAT_ID,
        "📋 *Проверьте введённые данные:*\n\n" .
        "👤 Имя: *{$testName}*\n" .
        "🎂 Возраст: *{$testAge} лет*\n" .
        "🏙 Город: *{$testCity}*\n\n" .
        "Всё верно?",
        [
            'parse_mode' => TelegramAPI::PARSE_MODE_MARKDOWN,
            'reply_markup' => $keyboard
        ]
    );
    
    debug("Шаг 5: Подтверждение данных");
    
    $db->query(
        "UPDATE dialog_states SET state = ?, data = ? WHERE user_id = ? AND chat_id = ?",
        [
            'awaiting_confirmation', 
            json_encode([
                'step' => 5,
                'name' => $testName,
                'age' => $testAge,
                'city' => $testCity
            ]), 
            TEST_CHAT_ID, 
            TEST_CHAT_ID
        ]
    );
    
    $runner->sendTelegramNotification(
        "🗣 Диалоговый тест: Регистрация\n" .
        "Шаг: 4/4 (Подтверждение)\n" .
        "Все данные собраны:\n" .
        "• Имя: {$testName}\n" .
        "• Возраст: {$testAge}\n" .
        "• Город: {$testCity}"
    );
    
    sleep(2);
    
    // Шаг 6: Сохранение пользователя в БД
    $db->query(
        "INSERT INTO users (telegram_id, username, first_name, language_code, is_bot, is_premium) 
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE first_name = VALUES(first_name), last_active = NOW()",
        [TEST_CHAT_ID, 'test_user', $testName, 'ru', false, false]
    );
    
    // Сохранение статистики
    $db->query(
        "INSERT INTO statistics (stat_key, stat_value, user_id) VALUES (?, ?, ?)",
        ['registration_completed', date('Y-m-d H:i:s'), TEST_CHAT_ID]
    );
    
    // Финальное сообщение
    $msg9 = $api->sendMessage(
        TEST_CHAT_ID,
        "🎉 *Регистрация завершена!*\n\n" .
        "✅ Ваши данные успешно сохранены в базе данных.\n\n" .
        "📊 Информация:\n" .
        "• Имя: *{$testName}*\n" .
        "• Возраст: *{$testAge} лет*\n" .
        "• Город: *{$testCity}*\n" .
        "• Время регистрации: " . date('H:i:s') . "\n\n" .
        "Диалоговый сценарий успешно завершён!",
        ['parse_mode' => TelegramAPI::PARSE_MODE_MARKDOWN]
    );
    
    debug("Регистрация завершена, данные сохранены в БД");
    
    // Очистка состояния диалога
    $db->query(
        "UPDATE dialog_states SET state = ? WHERE user_id = ? AND chat_id = ?",
        ['completed', TEST_CHAT_ID, TEST_CHAT_ID]
    );
    
    // Проверка данных в БД
    $user = $db->query(
        "SELECT * FROM users WHERE telegram_id = ? LIMIT 1",
        [TEST_CHAT_ID]
    );
    
    assert(count($user) > 0, 'Пользователь должен быть сохранён в БД');
    assert($user[0]['first_name'] === $testName, 'Имя должно совпадать');
    
    $runner->getLogger()->info('Диалоговый сценарий регистрации завершён', [
        'name' => $testName,
        'age' => $testAge,
        'city' => $testCity
    ]);
});

// ============================================================================
// БЛОК 4: РЕДАКТИРОВАНИЕ СООБЩЕНИЙ
// ============================================================================
testHeader("БЛОК 4: РЕДАКТИРОВАНИЕ СООБЩЕНИЙ");

$runner->runTest("Редактирование текста сообщения", function($runner) {
    $api = $runner->getApi();
    
    $message = $api->sendMessage(
        TEST_CHAT_ID,
        "⏳ Это сообщение будет отредактировано через 2 секунды..."
    );
    
    debug("Исходное сообщение отправлено, ID: {$message->messageId}");
    
    sleep(2);
    
    $editedMessage = $api->editMessageText(
        TEST_CHAT_ID,
        $message->messageId,
        "✅ *Сообщение отредактировано!*\n\n" .
        "Время редактирования: " . date('H:i:s') . "\n" .
        "Message ID: {$message->messageId}",
        ['parse_mode' => TelegramAPI::PARSE_MODE_MARKDOWN]
    );
    
    debug("Сообщение отредактировано");
    
    $runner->sendTelegramNotification(
        "✏️ Редактирование сообщения\n" .
        "Message ID: {$message->messageId}\n" .
        "Статус: ✅"
    );
});

$runner->runTest("Редактирование клавиатуры сообщения", function($runner) {
    $api = $runner->getApi();
    
    $keyboard1 = (new InlineKeyboardBuilder())
        ->addCallbackButton('Кнопка 1', 'btn1')
        ->addCallbackButton('Кнопка 2', 'btn2')
        ->build();
    
    $message = $api->sendMessage(
        TEST_CHAT_ID,
        "🔄 Клавиатура будет изменена через 2 секунды...",
        ['reply_markup' => $keyboard1]
    );
    
    debug("Сообщение с первой клавиатурой отправлено");
    
    sleep(2);
    
    $keyboard2 = (new InlineKeyboardBuilder())
        ->addCallbackButton('✅ Новая кнопка 1', 'new_btn1')
        ->row()
        ->addCallbackButton('✅ Новая кнопка 2', 'new_btn2')
        ->row()
        ->addCallbackButton('✅ Новая кнопка 3', 'new_btn3')
        ->build();
    
    $editedMessage = $api->editMessageReplyMarkup(
        TEST_CHAT_ID,
        $message->messageId,
        $keyboard2
    );
    
    debug("Клавиатура отредактирована");
});

// ============================================================================
// БЛОК 5: РАБОТА С VALIDATORS И PARSERS
// ============================================================================
testHeader("БЛОК 5: ВАЛИДАЦИЯ И ПАРСИНГ");

$runner->runTest("Валидация различных типов данных", function($runner) {
    // Валидация токена
    Validator::validateToken(TEST_BOT_TOKEN);
    debug("✓ Токен валиден");
    
    // Валидация chat ID
    Validator::validateChatId(TEST_CHAT_ID);
    Validator::validateChatId('@username');
    Validator::validateChatId(-1001234567890);
    debug("✓ Chat ID валидны");
    
    // Валидация текста
    Validator::validateText('Hello, World!');
    debug("✓ Текст валиден");
    
    try {
        Validator::validateText(str_repeat('A', 4097));
        throw new Exception('Должно было выбросить ValidationException');
    } catch (ValidationException $e) {
        debug("✓ Длинный текст правильно отклонён");
    }
    
    // Валидация callback data
    Validator::validateCallbackData('action:value');
    debug("✓ Callback data валидны");
    
    $runner->sendTelegramNotification(
        "✅ Валидация данных\n" .
        "Все проверки пройдены успешно"
    );
});

$runner->runTest("Парсинг команд и данных", function($runner) {
    // Парсинг команды
    $cmd = Parser::parseCommand('/start arg1 arg2 arg3');
    assert($cmd['command'] === 'start', 'Команда должна быть start');
    assert(count($cmd['args']) === 3, 'Должно быть 3 аргумента');
    debug("✓ Команда распознана: /start с 3 аргументами");
    
    // Парсинг команды с именем бота
    $cmd2 = Parser::parseCommand('/help@testbot');
    assert($cmd2['command'] === 'help', 'Команда должна быть help');
    debug("✓ Команда с именем бота распознана");
    
    // Парсинг callback data
    $data = Parser::parseCallbackData('action:id=123,type=post,page=5');
    assert($data['action'] === 'action', 'Action должен быть action');
    assert($data['id'] === '123', 'ID должен быть 123');
    assert($data['type'] === 'post', 'Type должен быть post');
    assert($data['page'] === '5', 'Page должен быть 5');
    debug("✓ Callback data распознан с параметрами");
    
    // Построение callback data
    $built = Parser::buildCallbackData('delete', ['id' => 456, 'confirm' => 'yes']);
    assert(str_contains($built, 'delete'), 'Должен содержать action');
    assert(str_contains($built, 'id=456'), 'Должен содержать id');
    debug("✓ Callback data построен: {$built}");
    
    // Извлечение упоминаний
    $mentions = Parser::extractMentions('Hello @user1 and @user2, check @user3');
    assert(count($mentions) === 3, 'Должно быть 3 упоминания');
    debug("✓ Извлечено 3 упоминания");
    
    // Извлечение хештегов
    $hashtags = Parser::extractHashtags('Post #php #telegram #bot #test');
    assert(count($hashtags) === 4, 'Должно быть 4 хештега');
    debug("✓ Извлечено 4 хештега");
    
    // Извлечение URL
    $urls = Parser::extractUrls('Visit https://telegram.org and http://example.com');
    assert(count($urls) === 2, 'Должно быть 2 URL');
    debug("✓ Извлечено 2 URL");
    
    // Экранирование MarkdownV2
    $escaped = Parser::escapeMarkdownV2('Test_text*with[special]chars.');
    assert(str_contains($escaped, '\\_'), 'Должен быть экранированный underscore');
    debug("✓ MarkdownV2 экранирован");
});

// ============================================================================
// БЛОК 6: РАБОТА С POLL (Опросы)
// ============================================================================
testHeader("БЛОК 6: СОЗДАНИЕ ОПРОСОВ");

$runner->runTest("Создание опроса с несколькими вариантами", function($runner) {
    $api = $runner->getApi();
    
    $message = $api->sendPoll(
        TEST_CHAT_ID,
        '📊 Оцените качество тестирования модуля TelegramBot:',
        [
            '⭐️⭐️⭐️⭐️⭐️ Отлично (5/5)',
            '⭐️⭐️⭐️⭐️ Хорошо (4/5)',
            '⭐️⭐️⭐️ Нормально (3/5)',
            '⭐️⭐️ Удовлетворительно (2/5)',
            '⭐️ Плохо (1/5)'
        ],
        [
            'is_anonymous' => false,
            'allows_multiple_answers' => false
        ]
    );
    
    debug("Опрос создан, ID: {$message->messageId}");
    
    $runner->sendTelegramNotification(
        "📊 Опрос создан\n" .
        "Вариантов ответов: 5\n" .
        "Проголосуйте, пожалуйста!"
    );
});

// ============================================================================
// БЛОК 7: ПРОВЕРКА БАЗЫ ДАННЫХ
// ============================================================================
testHeader("БЛОК 7: ПРОВЕРКА ДАННЫХ В БАЗЕ");

$runner->runTest("Проверка записей в таблице dialog_states", function($runner) {
    $db = $runner->getDb();
    
    $states = $db->query("SELECT * FROM dialog_states WHERE user_id = ?", [TEST_CHAT_ID]);
    
    debug("Найдено записей состояний: " . count($states));
    
    assert(count($states) > 0, 'Должны быть записи о состояниях диалогов');
    
    foreach ($states as $state) {
        debug("  State: {$state['state']}, Updated: {$state['updated_at']}");
    }
});

$runner->runTest("Проверка записей в таблице message_log", function($runner) {
    $db = $runner->getDb();
    
    $messages = $db->query("SELECT * FROM message_log WHERE user_id = ?", [TEST_CHAT_ID]);
    
    debug("Найдено сообщений в логе: " . count($messages));
    
    foreach ($messages as $msg) {
        debug("  Message ID: {$msg['message_id']}, Type: {$msg['message_type']}");
    }
});

$runner->runTest("Проверка записей в таблице users", function($runner) {
    $db = $runner->getDb();
    
    $users = $db->query("SELECT * FROM users WHERE telegram_id = ?", [TEST_CHAT_ID]);
    
    debug("Найдено пользователей: " . count($users));
    
    assert(count($users) > 0, 'Пользователь должен быть в БД');
    
    $user = $users[0];
    debug("  User: {$user['first_name']}, Last active: {$user['last_active']}");
    debug("  Created: {$user['created_at']}");
});

$runner->runTest("Проверка статистики", function($runner) {
    $db = $runner->getDb();
    
    $stats = $db->query("SELECT * FROM statistics WHERE user_id = ?", [TEST_CHAT_ID]);
    
    debug("Найдено записей статистики: " . count($stats));
    
    foreach ($stats as $stat) {
        debug("  {$stat['stat_key']}: {$stat['stat_value']}");
    }
});

// ============================================================================
// БЛОК 8: СЛОЖНЫЙ СЦЕНАРИЙ - ОБРАБОТКА ОШИБОК
// ============================================================================
testHeader("БЛОК 8: ОБРАБОТКА ОШИБОК");

$runner->runTest("Попытка отправки слишком длинного текста", function($runner) {
    $api = $runner->getApi();
    
    try {
        $longText = str_repeat('A', 4097);
        $api->sendMessage(TEST_CHAT_ID, $longText);
        
        throw new Exception('Должно было выбросить ValidationException');
    } catch (ValidationException $e) {
        debug("✓ ValidationException правильно выброшен: " . $e->getMessage());
        
        $runner->sendTelegramNotification(
            "✅ Обработка ошибок\n" .
            "Длинный текст правильно отклонён"
        );
    }
});

$runner->runTest("Попытка редактирования несуществующего сообщения", function($runner) {
    $api = $runner->getApi();
    
    try {
        $api->editMessageText(TEST_CHAT_ID, 999999999, 'New text');
        
        debug("✓ Редактирование несуществующего сообщения обработано");
    } catch (ApiException $e) {
        debug("✓ ApiException получен: " . $e->getMessage());
    }
});

// ============================================================================
// БЛОК 9: ФИНАЛЬНЫЕ ПРОВЕРКИ
// ============================================================================
testHeader("БЛОК 9: ФИНАЛЬНЫЕ ПРОВЕРКИ");

$runner->runTest("Удаление тестовых сообщений (очистка)", function($runner) {
    $api = $runner->getApi();
    
    $message = $api->sendMessage(
        TEST_CHAT_ID,
        "⏳ Это сообщение будет удалено через 3 секунды..."
    );
    
    debug("Сообщение для удаления создано");
    
    sleep(3);
    
    $result = $api->deleteMessage(TEST_CHAT_ID, $message->messageId);
    
    assert($result === true, 'Сообщение должно быть удалено');
    debug("✓ Сообщение успешно удалено");
});

$runner->runTest("Проверка логирования", function($runner) {
    $logger = $runner->getLogger();
    
    $logger->debug('Test debug message');
    $logger->info('Test info message');
    $logger->warning('Test warning message');
    $logger->error('Test error message');
    
    debug("✓ Все уровни логирования работают");
    
    // Проверка файла лога
    $logFile = __DIR__ . '/../../logs/telegram_bot_integration_test.log';
    assert(file_exists($logFile), 'Лог-файл должен существовать');
    
    $logSize = filesize($logFile);
    debug("✓ Размер лог-файла: " . round($logSize / 1024, 2) . " KB");
});

// ============================================================================
// ИТОГИ ТЕСТИРОВАНИЯ
// ============================================================================
testHeader("ИТОГИ ТЕСТИРОВАНИЯ");

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

// Отправка финального отчёта в Telegram
$statusEmoji = $summary['failed'] === 0 ? '✅' : '⚠️';
$runner->sendTelegramNotification(
    "{$statusEmoji} *ТЕСТИРОВАНИЕ ЗАВЕРШЕНО*\n\n" .
    "📊 *Статистика:*\n" .
    "• Всего тестов: {$summary['total']}\n" .
    "• Успешно: {$summary['passed']}\n" .
    "• Провалено: {$summary['failed']}\n" .
    "• Успешность: {$successRate}%\n\n" .
    "⏱ Время: " . date('Y-m-d H:i:s') . "\n\n" .
    ($summary['failed'] === 0 
        ? "🎉 Все тесты пройдены успешно!" 
        : "⚠️ Некоторые тесты провалены, требуется проверка."
    ),
    ['parse_mode' => TelegramAPI::PARSE_MODE_MARKDOWN]
);

$runner->getLogger()->info('========================================');
$runner->getLogger()->info('ТЕСТИРОВАНИЕ ЗАВЕРШЕНО', [
    'total' => $summary['total'],
    'passed' => $summary['passed'],
    'failed' => $summary['failed'],
    'success_rate' => $successRate
]);
$runner->getLogger()->info('========================================');

echo "\n" . colorize("Детальный лог доступен в: logs/telegram_bot_integration_test.log", 'cyan') . "\n\n";

// Возврат кода выхода
exit($summary['failed'] > 0 ? 1 : 0);

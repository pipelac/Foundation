<?php

declare(strict_types=1);

/**
 * Комплексное тестирование TelegramBot в режиме Polling с MySQL
 * 
 * Тесты включают:
 * - Инициализация MySQL и создание таблиц
 * - Тестирование TelegramAPI (отправка сообщений, медиа, кнопок)
 * - Тестирование PollingHandler (получение обновлений)
 * - Тестирование ConversationManager (диалоги с состояниями)
 * - Тестирование AccessControl (управление доступом)
 * - Интеграция с OpenRouter (AI ответы)
 * - Комплексные диалоговые сценарии
 * - Все результаты отправляются в Telegram бот
 */

require_once __DIR__ . '/autoload.php';

use App\Component\Logger;
use App\Component\Http;
use App\Component\MySQL;
use App\Component\OpenRouter;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Core\PollingHandler;
use App\Component\TelegramBot\Core\ConversationManager;
use App\Component\TelegramBot\Core\MessageStorage;
use App\Component\TelegramBot\Core\AccessControl;
use App\Component\TelegramBot\Keyboards\InlineKeyboardBuilder;
use App\Component\TelegramBot\Keyboards\ReplyKeyboardBuilder;
use App\Component\TelegramBot\Entities\Update;

class TelegramBotPollingComprehensiveTest
{
    private Logger $logger;
    private MySQL $db;
    private TelegramAPI $api;
    private PollingHandler $polling;
    private ConversationManager $conversations;
    private MessageStorage $messageStorage;
    private AccessControl $accessControl;
    private ?OpenRouter $openRouter;
    
    private string $botToken;
    private int $testChatId;
    
    private array $testResults = [];
    private int $testsTotal = 0;
    private int $testsPassed = 0;
    private int $testsFailed = 0;
    
    private const EMOJIS = [
        'start' => '🚀',
        'success' => '✅',
        'error' => '❌',
        'warning' => '⚠️',
        'info' => 'ℹ️',
        'test' => '🧪',
        'database' => '🗄️',
        'bot' => '🤖',
        'dialog' => '💬',
        'ai' => '🧠',
        'keyboard' => '⌨️',
        'media' => '📸',
        'finish' => '🏁',
    ];
    
    public function __construct()
    {
        $this->botToken = '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI';
        $this->testChatId = 366442475;
        
        $this->initializeLogger();
        $this->sendTestNotification('start', "Начало комплексного тестирования TelegramBot Polling");
    }
    
    private function initializeLogger(): void
    {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }
        
        $logConfig = [
            'directory' => $logDir,
            'file_name' => 'telegram_bot_polling_test.log',
            'max_files' => 5,
            'max_file_size' => 10,
            'enabled' => true,
        ];
        
        $this->logger = new Logger($logConfig);
        $this->logger->info('Инициализация системы тестирования');
    }
    
    private function sendTestNotification(string $type, string $message): void
    {
        $emoji = self::EMOJIS[$type] ?? '📌';
        $fullMessage = "{$emoji} {$message}";
        
        try {
            $http = new Http(['timeout' => 30]);
            $api = new TelegramAPI($this->botToken, $http);
            $api->sendMessage($this->testChatId, $fullMessage);
            $this->logger->debug("Уведомление отправлено: {$message}");
        } catch (\Exception $e) {
            $this->logger->error("Не удалось отправить уведомление: " . $e->getMessage());
        }
    }
    
    private function recordTestResult(string $testName, bool $passed, string $details = ''): void
    {
        $this->testsTotal++;
        
        if ($passed) {
            $this->testsPassed++;
            $status = self::EMOJIS['success'] . ' PASSED';
        } else {
            $this->testsFailed++;
            $status = self::EMOJIS['error'] . ' FAILED';
        }
        
        $this->testResults[] = [
            'name' => $testName,
            'passed' => $passed,
            'details' => $details,
        ];
        
        $logMessage = "{$status}: {$testName}";
        if ($details) {
            $logMessage .= " - {$details}";
        }
        
        $this->logger->info($logMessage);
        
        // Отправка уведомления о результате теста
        $this->sendTestNotification(
            $passed ? 'success' : 'error',
            "{$testName}\n{$details}"
        );
    }
    
    public function runAllTests(): void
    {
        $this->logger->info('=== НАЧАЛО КОМПЛЕКСНОГО ТЕСТИРОВАНИЯ ===');
        $this->sendTestNotification('test', "Запуск всех тестов");
        
        try {
            // 1. Тестирование MySQL
            $this->testMySQL();
            
            // 2. Инициализация компонентов
            $this->initializeComponents();
            
            // 3. Тестирование TelegramAPI
            $this->testTelegramAPI();
            
            // 4. Тестирование MessageStorage
            $this->testMessageStorage();
            
            // 5. Тестирование ConversationManager
            $this->testConversationManager();
            
            // 6. Тестирование AccessControl
            $this->testAccessControl();
            
            // 7. Тестирование клавиатур
            $this->testKeyboards();
            
            // 8. Тестирование PollingHandler
            $this->testPollingHandler();
            
            // 9. Интеграция с OpenRouter
            $this->testOpenRouterIntegration();
            
            // 10. Комплексные сценарии
            $this->testComplexScenarios();
            
            // 11. Финальные результаты
            $this->displayResults();
            
        } catch (\Exception $e) {
            $this->logger->error('Критическая ошибка тестирования: ' . $e->getMessage());
            $this->sendTestNotification('error', "КРИТИЧЕСКАЯ ОШИБКА:\n" . $e->getMessage());
        }
    }
    
    private function testMySQL(): void
    {
        $this->sendTestNotification('database', "Тестирование MySQL");
        
        try {
            // Проверка MySQL сервера
            $this->logger->info('Проверка MySQL сервера');
            exec('sudo service mysql status 2>&1', $output, $returnCode);
            
            if ($returnCode !== 0) {
                $this->logger->info('MySQL не запущен, запускаем...');
                exec('sudo service mysql start 2>&1', $startOutput, $startCode);
                sleep(3);
            } else {
                $this->logger->info('MySQL уже запущен');
            }
            
            // Подключение к MySQL
            $dbConfig = [
                'host' => 'localhost',
                'port' => 3306,
                'database' => 'telegram_bot_test',
                'username' => 'root',
                'password' => '',
                'charset' => 'utf8mb4',
                'persistent' => false,
                'cache_statements' => true,
            ];
            
            // Создание базы данных если не существует
            $tempDb = new MySQL([
                'host' => 'localhost',
                'port' => 3306,
                'database' => 'mysql',
                'username' => 'root',
                'password' => '',
                'charset' => 'utf8mb4',
            ], $this->logger);
            
            $tempDb->execute("CREATE DATABASE IF NOT EXISTS telegram_bot_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->recordTestResult('MySQL: Создание базы данных', true, 'База данных telegram_bot_test создана');
            
            // Подключение к тестовой БД
            $this->db = new MySQL($dbConfig, $this->logger);
            
            // Проверка подключения
            $result = $this->db->queryOne("SELECT VERSION() as version");
            $version = $result['version'] ?? 'unknown';
            $this->recordTestResult('MySQL: Подключение', true, "MySQL версия: {$version}");
            
            // Тестирование базовых операций
            $this->testMySQLOperations();
            
        } catch (\Exception $e) {
            $this->recordTestResult('MySQL: Инициализация', false, $e->getMessage());
            throw $e;
        }
    }
    
    private function testMySQLOperations(): void
    {
        try {
            // Создание тестовой таблицы
            $this->db->execute("DROP TABLE IF EXISTS test_table");
            $this->db->execute("
                CREATE TABLE test_table (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    name VARCHAR(255) NOT NULL,
                    value INT NOT NULL,
                    created_at DATETIME NOT NULL,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $this->recordTestResult('MySQL: Создание таблицы', true);
            
            // INSERT
            $insertId = $this->db->insert('test_table', [
                'name' => 'test_record',
                'value' => 100,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $this->recordTestResult('MySQL: INSERT', $insertId > 0, "ID: {$insertId}");
            
            // SELECT
            $record = $this->db->queryOne("SELECT * FROM test_table WHERE id = ?", [$insertId]);
            $this->recordTestResult('MySQL: SELECT', $record !== null && $record['name'] === 'test_record');
            
            // UPDATE
            $updated = $this->db->update('test_table', ['value' => 200], ['id' => $insertId]);
            $this->recordTestResult('MySQL: UPDATE', $updated > 0);
            
            // DELETE
            $deleted = $this->db->execute("DELETE FROM test_table WHERE id = ?", [$insertId]);
            $this->recordTestResult('MySQL: DELETE', $deleted > 0);
            
            // Транзакции
            $this->db->beginTransaction();
            $this->db->insert('test_table', ['name' => 'tx_test', 'value' => 1, 'created_at' => date('Y-m-d H:i:s')]);
            $this->db->commit();
            $this->recordTestResult('MySQL: Транзакции', true);
            
        } catch (\Exception $e) {
            $this->recordTestResult('MySQL: Операции', false, $e->getMessage());
        }
    }
    
    private function initializeComponents(): void
    {
        $this->sendTestNotification('bot', "Инициализация компонентов бота");
        
        try {
            // HTTP клиент
            $http = new Http(['timeout' => 30], $this->logger);
            
            // MessageStorage
            $messageStorageConfig = [
                'enabled' => true,
                'auto_create_tables' => true,
                'retention_days' => 30,
            ];
            $this->messageStorage = new MessageStorage($this->db, $this->logger, $messageStorageConfig);
            $this->recordTestResult('Инициализация: MessageStorage', true);
            
            // TelegramAPI
            $this->api = new TelegramAPI($this->botToken, $http, $this->logger, $this->messageStorage);
            $botInfo = $this->api->getMe();
            $this->recordTestResult('Инициализация: TelegramAPI', true, "Бот: @{$botInfo->username}");
            
            // PollingHandler
            $this->polling = new PollingHandler($this->api, $this->logger);
            $this->polling->setTimeout(10)->setLimit(10);
            $this->recordTestResult('Инициализация: PollingHandler', true);
            
            // ConversationManager
            $conversationConfig = [
                'enabled' => true,
                'timeout' => 3600,
                'auto_create_tables' => true,
            ];
            $this->conversations = new ConversationManager($this->db, $this->logger, $conversationConfig);
            $this->recordTestResult('Инициализация: ConversationManager', true);
            
            // AccessControl
            $accessConfig = [
                'enabled' => true,
                'auto_create_tables' => true,
                'default_role' => 'guest',
            ];
            $this->accessControl = new AccessControl($this->db, $this->logger, $accessConfig);
            $this->recordTestResult('Инициализация: AccessControl', true);
            
            // OpenRouter (опционально)
            try {
                $openRouterConfig = [
                    'api_key' => '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',
                    'app_name' => 'TelegramBotTest',
                    'timeout' => 30,
                ];
                $this->openRouter = new OpenRouter($openRouterConfig, $this->logger);
                $this->recordTestResult('Инициализация: OpenRouter', true);
            } catch (\Exception $e) {
                $this->openRouter = null;
                $this->recordTestResult('Инициализация: OpenRouter', false, $e->getMessage());
            }
            
        } catch (\Exception $e) {
            $this->recordTestResult('Инициализация компонентов', false, $e->getMessage());
            throw $e;
        }
    }
    
    private function testTelegramAPI(): void
    {
        $this->sendTestNotification('bot', "Тестирование Telegram API");
        
        try {
            // Тест отправки текстового сообщения
            $message = $this->api->sendMessage(
                $this->testChatId,
                "🧪 Тест отправки текстового сообщения\nВремя: " . date('Y-m-d H:i:s')
            );
            $this->recordTestResult('TelegramAPI: sendMessage', $message !== null, "Message ID: {$message->messageId}");
            sleep(1);
            
            // Тест с HTML форматированием
            $htmlMessage = $this->api->sendMessage(
                $this->testChatId,
                "<b>Тест HTML</b>\n<i>Курсив</i>\n<code>Код</code>",
                ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
            );
            $this->recordTestResult('TelegramAPI: HTML форматирование', $htmlMessage !== null);
            sleep(1);
            
            // Тест редактирования сообщения
            $editedMessage = $this->api->editMessageText(
                $this->testChatId,
                $htmlMessage->messageId,
                "<b>Отредактировано!</b>\n<i>Сообщение было изменено</i>",
                ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
            );
            $this->recordTestResult('TelegramAPI: editMessageText', $editedMessage !== null);
            sleep(1);
            
            // Тест удаления сообщения
            $toDelete = $this->api->sendMessage($this->testChatId, "Это сообщение будет удалено");
            sleep(1);
            $deleted = $this->api->deleteMessage($this->testChatId, $toDelete->messageId);
            $this->recordTestResult('TelegramAPI: deleteMessage', $deleted === true);
            
        } catch (\Exception $e) {
            $this->recordTestResult('TelegramAPI: Общий тест', false, $e->getMessage());
        }
    }
    
    private function testMessageStorage(): void
    {
        $this->sendTestNotification('database', "Тестирование MessageStorage");
        
        try {
            // Сохранение исходящего сообщения
            $outgoingId = $this->messageStorage->storeOutgoing(
                'sendMessage',
                ['chat_id' => $this->testChatId, 'text' => 'Test'],
                ['message_id' => 12345],
                true
            );
            $this->recordTestResult('MessageStorage: storeOutgoing', $outgoingId > 0);
            
            // Сохранение входящего сообщения
            $incomingId = $this->messageStorage->storeIncoming(
                123456789,
                $this->testChatId,
                366442475,
                'message',
                ['text' => 'Тестовое сообщение']
            );
            $this->recordTestResult('MessageStorage: storeIncoming', $incomingId > 0);
            
            // Получение статистики
            $stats = $this->messageStorage->getStatistics();
            $this->recordTestResult(
                'MessageStorage: getStatistics',
                isset($stats['total_outgoing']) && isset($stats['total_incoming']),
                "Исходящих: {$stats['total_outgoing']}, Входящих: {$stats['total_incoming']}"
            );
            
            // Получение последних сообщений
            $recent = $this->messageStorage->getRecentOutgoing(5);
            $this->recordTestResult('MessageStorage: getRecentOutgoing', is_array($recent));
            
        } catch (\Exception $e) {
            $this->recordTestResult('MessageStorage', false, $e->getMessage());
        }
    }
    
    private function testConversationManager(): void
    {
        $this->sendTestNotification('dialog', "Тестирование ConversationManager");
        
        try {
            $testUserId = 366442475;
            
            // Сохранение пользователя
            $userSaved = $this->conversations->saveUser($testUserId, 'TestUser', 'testuser');
            $this->recordTestResult('ConversationManager: saveUser', $userSaved);
            
            // Получение пользователя
            $user = $this->conversations->getUser($testUserId);
            $this->recordTestResult(
                'ConversationManager: getUser',
                $user !== null && $user['user_id'] === $testUserId,
                "Username: {$user['username']}"
            );
            
            // Начало диалога
            $convId = $this->conversations->startConversation(
                $this->testChatId,
                $testUserId,
                'awaiting_name',
                ['step' => 1]
            );
            $this->recordTestResult('ConversationManager: startConversation', $convId > 0, "ID: {$convId}");
            
            // Получение диалога
            $conversation = $this->conversations->getConversation($this->testChatId, $testUserId);
            $this->recordTestResult(
                'ConversationManager: getConversation',
                $conversation !== null && $conversation['state'] === 'awaiting_name'
            );
            
            // Обновление диалога
            $updated = $this->conversations->updateConversation(
                $this->testChatId,
                $testUserId,
                'awaiting_email',
                ['name' => 'John Doe', 'step' => 2]
            );
            $this->recordTestResult('ConversationManager: updateConversation', $updated);
            
            // Проверка обновления
            $updatedConv = $this->conversations->getConversation($this->testChatId, $testUserId);
            $this->recordTestResult(
                'ConversationManager: Проверка обновления',
                $updatedConv['state'] === 'awaiting_email' && $updatedConv['data']['name'] === 'John Doe'
            );
            
            // Статистика
            $stats = $this->conversations->getStatistics();
            $this->recordTestResult(
                'ConversationManager: getStatistics',
                isset($stats['total']),
                "Активных диалогов: {$stats['total']}"
            );
            
            // Завершение диалога
            $ended = $this->conversations->endConversation($this->testChatId, $testUserId);
            $this->recordTestResult('ConversationManager: endConversation', $ended);
            
            // Проверка завершения
            $endedConv = $this->conversations->getConversation($this->testChatId, $testUserId);
            $this->recordTestResult('ConversationManager: Проверка завершения', $endedConv === null);
            
        } catch (\Exception $e) {
            $this->recordTestResult('ConversationManager', false, $e->getMessage());
        }
    }
    
    private function testAccessControl(): void
    {
        $this->sendTestNotification('info', "Тестирование AccessControl");
        
        try {
            $testUserId = 366442475;
            
            // Регистрация пользователя
            $registered = $this->accessControl->registerUser($testUserId, 'admin');
            $this->recordTestResult('AccessControl: registerUser', $registered);
            
            // Проверка роли
            $role = $this->accessControl->getUserRole($testUserId);
            $this->recordTestResult('AccessControl: getUserRole', $role === 'admin', "Роль: {$role}");
            
            // Проверка прав доступа
            $hasAccess = $this->accessControl->hasAccess($testUserId, '/admin');
            $this->recordTestResult('AccessControl: hasAccess', $hasAccess);
            
            // Смена роли
            $roleChanged = $this->accessControl->setUserRole($testUserId, 'user');
            $this->recordTestResult('AccessControl: setUserRole', $roleChanged);
            
            // Проверка новой роли
            $newRole = $this->accessControl->getUserRole($testUserId);
            $this->recordTestResult('AccessControl: Проверка новой роли', $newRole === 'user');
            
            // Получение списка пользователей
            $users = $this->accessControl->getAllUsers();
            $this->recordTestResult('AccessControl: getAllUsers', is_array($users) && count($users) > 0);
            
            // Возврат роли admin для дальнейших тестов
            $this->accessControl->setUserRole($testUserId, 'admin');
            
        } catch (\Exception $e) {
            $this->recordTestResult('AccessControl', false, $e->getMessage());
        }
    }
    
    private function testKeyboards(): void
    {
        $this->sendTestNotification('keyboard', "Тестирование клавиатур");
        
        try {
            // Inline клавиатура
            $inlineKeyboard = InlineKeyboardBuilder::create()
                ->addButton('Кнопка 1', 'callback_1')
                ->addButton('Кнопка 2', 'callback_2')
                ->newRow()
                ->addButton('URL кнопка', null, 'https://example.com')
                ->build();
            
            $inlineMessage = $this->api->sendMessage(
                $this->testChatId,
                "🎹 Тест Inline клавиатуры",
                ['reply_markup' => $inlineKeyboard]
            );
            $this->recordTestResult('Keyboards: InlineKeyboard', $inlineMessage !== null);
            sleep(1);
            
            // Reply клавиатура
            $replyKeyboard = ReplyKeyboardBuilder::create()
                ->addButton('Команда 1')
                ->addButton('Команда 2')
                ->newRow()
                ->addButton('Отмена')
                ->setResizeKeyboard(true)
                ->setOneTimeKeyboard(false)
                ->build();
            
            $replyMessage = $this->api->sendMessage(
                $this->testChatId,
                "⌨️ Тест Reply клавиатуры",
                ['reply_markup' => $replyKeyboard]
            );
            $this->recordTestResult('Keyboards: ReplyKeyboard', $replyMessage !== null);
            sleep(1);
            
            // Удаление Reply клавиатуры
            $removeMessage = $this->api->sendMessage(
                $this->testChatId,
                "❌ Удаление Reply клавиатуры",
                ['reply_markup' => ['remove_keyboard' => true]]
            );
            $this->recordTestResult('Keyboards: RemoveKeyboard', $removeMessage !== null);
            
        } catch (\Exception $e) {
            $this->recordTestResult('Keyboards', false, $e->getMessage());
        }
    }
    
    private function testPollingHandler(): void
    {
        $this->sendTestNotification('bot', "Тестирование PollingHandler");
        
        try {
            // Пропуск старых обновлений
            $skipped = $this->polling->skipPendingUpdates();
            $this->recordTestResult('PollingHandler: skipPendingUpdates', true, "Пропущено: {$skipped}");
            
            // Получение offset
            $offset = $this->polling->getOffset();
            $this->recordTestResult('PollingHandler: getOffset', $offset >= 0, "Offset: {$offset}");
            
            // Проверка активности
            $isPolling = $this->polling->isPolling();
            $this->recordTestResult('PollingHandler: isPolling', $isPolling === true);
            
            // Тест pollOnce
            $this->api->sendMessage($this->testChatId, "📨 Отправьте любое сообщение в течение 10 секунд для теста Polling...");
            
            $updates = $this->polling->pollOnce();
            $this->recordTestResult(
                'PollingHandler: pollOnce',
                is_array($updates),
                "Получено обновлений: " . count($updates)
            );
            
            // Обработка полученных обновлений
            if (count($updates) > 0) {
                foreach ($updates as $update) {
                    $this->processUpdate($update);
                }
            }
            
        } catch (\Exception $e) {
            $this->recordTestResult('PollingHandler', false, $e->getMessage());
        }
    }
    
    private function processUpdate(Update $update): void
    {
        try {
            if ($update->message) {
                $message = $update->message;
                $chatId = $message->chat->id;
                $userId = $message->from?->id ?? 0;
                $text = $message->text ?? '';
                
                $this->logger->info("Получено сообщение", [
                    'update_id' => $update->updateId,
                    'chat_id' => $chatId,
                    'user_id' => $userId,
                    'text' => $text,
                ]);
                
                // Сохранение в MessageStorage
                $this->messageStorage->storeIncoming(
                    $update->updateId,
                    $chatId,
                    $userId,
                    'message',
                    ['text' => $text]
                );
                
                // Ответ пользователю
                $this->api->sendMessage(
                    $chatId,
                    "✅ Ваше сообщение получено через Polling!\nТекст: {$text}"
                );
            }
        } catch (\Exception $e) {
            $this->logger->error("Ошибка обработки обновления: " . $e->getMessage());
        }
    }
    
    private function testOpenRouterIntegration(): void
    {
        if (!$this->openRouter) {
            $this->recordTestResult('OpenRouter: Интеграция', false, 'OpenRouter не инициализирован');
            return;
        }
        
        $this->sendTestNotification('ai', "Тестирование интеграции с OpenRouter");
        
        try {
            // Генерация AI ответа
            $prompt = "Напиши короткое приветствие для Telegram бота на русском языке (максимум 50 слов)";
            
            $this->api->sendMessage($this->testChatId, "🧠 Генерация AI ответа...");
            
            $aiResponse = $this->openRouter->text2text(
                'openai/gpt-3.5-turbo',
                $prompt,
                ['max_tokens' => 100]
            );
            
            $this->recordTestResult('OpenRouter: text2text', !empty($aiResponse));
            
            // Отправка AI ответа в чат
            $this->api->sendMessage(
                $this->testChatId,
                "🤖 AI ответ:\n\n{$aiResponse}"
            );
            
        } catch (\Exception $e) {
            $this->recordTestResult('OpenRouter: Интеграция', false, $e->getMessage());
        }
    }
    
    private function testComplexScenarios(): void
    {
        $this->sendTestNotification('dialog', "Запуск комплексных сценариев");
        
        // Сценарий 1: Многошаговый диалог с сохранением состояния
        $this->testMultiStepDialog();
        
        // Сценарий 2: Обработка callback запросов
        $this->testCallbackHandling();
        
        // Сценарий 3: Интерактивное меню с навигацией
        $this->testInteractiveMenu();
    }
    
    private function testMultiStepDialog(): void
    {
        $this->sendTestNotification('dialog', "Сценарий: Многошаговый диалог");
        
        try {
            $testUserId = $this->testChatId;
            
            // Шаг 1: Начало регистрации
            $keyboard = InlineKeyboardBuilder::create()
                ->addButton('Тип 1', 'type_1')
                ->addButton('Тип 2', 'type_2')
                ->addButton('Тип 3', 'type_3')
                ->build();
            
            $msg = $this->api->sendMessage(
                $this->testChatId,
                "📝 Начнем регистрацию!\nВыберите тип:",
                ['reply_markup' => $keyboard]
            );
            
            // Сохранение состояния диалога
            $this->conversations->startConversation(
                $this->testChatId,
                $testUserId,
                'awaiting_type_selection',
                ['step' => 1],
                $msg->messageId
            );
            
            $this->recordTestResult('Комплексный сценарий: Шаг 1 (Выбор типа)', true);
            
            // Информирование пользователя о необходимости действия
            sleep(2);
            $this->api->sendMessage(
                $this->testChatId,
                "⏳ Ожидание: Пожалуйста, нажмите одну из кнопок выше.\n\nЯ жду вашего действия для продолжения теста многошагового диалога..."
            );
            
            $this->recordTestResult('Комплексный сценарий: Ожидание действия пользователя', true, 'Пользователь должен нажать кнопку');
            
            // Даем пользователю время на действие
            sleep(5);
            
            // Проверяем обновления
            $updates = $this->polling->pollOnce();
            $callbackReceived = false;
            
            foreach ($updates as $update) {
                if ($update->callbackQuery) {
                    $callbackReceived = true;
                    $callbackData = $update->callbackQuery->data;
                    
                    // Ответ на callback
                    $this->api->answerCallbackQuery($update->callbackQuery->id, [
                        'text' => "Выбран: {$callbackData}",
                    ]);
                    
                    // Обновление состояния
                    $this->conversations->updateConversation(
                        $this->testChatId,
                        $testUserId,
                        'awaiting_name',
                        ['type' => $callbackData, 'step' => 2]
                    );
                    
                    // Запрос имени
                    $this->api->sendMessage(
                        $this->testChatId,
                        "✅ Тип выбран: {$callbackData}\n\nТеперь введите ваше имя:"
                    );
                    
                    $this->recordTestResult('Комплексный сценарий: Шаг 2 (Обработка callback)', true, "Data: {$callbackData}");
                    
                    // Ожидание ввода имени
                    sleep(10);
                    $nameUpdates = $this->polling->pollOnce();
                    
                    foreach ($nameUpdates as $nameUpdate) {
                        if ($nameUpdate->message && $nameUpdate->message->text) {
                            $name = $nameUpdate->message->text;
                            
                            // Обновление состояния
                            $this->conversations->updateConversation(
                                $this->testChatId,
                                $testUserId,
                                'awaiting_email',
                                ['name' => $name, 'step' => 3]
                            );
                            
                            $this->api->sendMessage(
                                $this->testChatId,
                                "✅ Имя сохранено: {$name}\n\nТеперь введите ваш email:"
                            );
                            
                            $this->recordTestResult('Комплексный сценарий: Шаг 3 (Ввод имени)', true, "Имя: {$name}");
                            
                            // Ожидание ввода email
                            sleep(10);
                            $emailUpdates = $this->polling->pollOnce();
                            
                            foreach ($emailUpdates as $emailUpdate) {
                                if ($emailUpdate->message && $emailUpdate->message->text) {
                                    $email = $emailUpdate->message->text;
                                    
                                    // Получение всех данных
                                    $conv = $this->conversations->getConversation($this->testChatId, $testUserId);
                                    $finalData = $conv['data'];
                                    
                                    // Финальное сообщение
                                    $summary = "🎉 Регистрация завершена!\n\n";
                                    $summary .= "Тип: {$finalData['type']}\n";
                                    $summary .= "Имя: {$finalData['name']}\n";
                                    $summary .= "Email: {$email}";
                                    
                                    $this->api->sendMessage($this->testChatId, $summary);
                                    
                                    // Завершение диалога
                                    $this->conversations->endConversation($this->testChatId, $testUserId);
                                    
                                    $this->recordTestResult('Комплексный сценарий: Шаг 4 (Завершение)', true, "Email: {$email}");
                                    break;
                                }
                            }
                            break;
                        }
                    }
                    break;
                }
            }
            
            if (!$callbackReceived) {
                $this->recordTestResult('Комплексный сценарий: Callback не получен', false, 'Пользователь не нажал кнопку');
            }
            
        } catch (\Exception $e) {
            $this->recordTestResult('Комплексный сценарий: Многошаговый диалог', false, $e->getMessage());
        }
    }
    
    private function testCallbackHandling(): void
    {
        $this->sendTestNotification('keyboard', "Сценарий: Обработка callback запросов");
        
        try {
            $keyboard = InlineKeyboardBuilder::create()
                ->addButton('Тест 1', 'test_callback_1')
                ->addButton('Тест 2', 'test_callback_2')
                ->newRow()
                ->addButton('Отмена', 'cancel_callback')
                ->build();
            
            $msg = $this->api->sendMessage(
                $this->testChatId,
                "🎯 Тест callback обработки\nНажмите любую кнопку:",
                ['reply_markup' => $keyboard]
            );
            
            $this->recordTestResult('Callback: Отправка сообщения с кнопками', true);
            
            sleep(2);
            $this->api->sendMessage(
                $this->testChatId,
                "⏳ Нажмите кнопку в течение 10 секунд..."
            );
            
            sleep(10);
            
            $updates = $this->polling->pollOnce();
            foreach ($updates as $update) {
                if ($update->callbackQuery) {
                    $data = $update->callbackQuery->data;
                    
                    $this->api->answerCallbackQuery($update->callbackQuery->id, [
                        'text' => "Обработан: {$data}",
                        'show_alert' => true,
                    ]);
                    
                    $this->api->editMessageText(
                        $this->testChatId,
                        $update->callbackQuery->message->messageId,
                        "✅ Callback обработан: {$data}"
                    );
                    
                    $this->recordTestResult('Callback: Обработка и ответ', true, "Data: {$data}");
                    break;
                }
            }
            
        } catch (\Exception $e) {
            $this->recordTestResult('Callback: Обработка', false, $e->getMessage());
        }
    }
    
    private function testInteractiveMenu(): void
    {
        $this->sendTestNotification('keyboard', "Сценарий: Интерактивное меню");
        
        try {
            $mainMenu = InlineKeyboardBuilder::create()
                ->addButton('📊 Статистика', 'menu_stats')
                ->addButton('⚙️ Настройки', 'menu_settings')
                ->newRow()
                ->addButton('ℹ️ Помощь', 'menu_help')
                ->addButton('🚪 Выход', 'menu_exit')
                ->build();
            
            $msg = $this->api->sendMessage(
                $this->testChatId,
                "📱 Главное меню\nВыберите раздел:",
                ['reply_markup' => $mainMenu]
            );
            
            $this->recordTestResult('Интерактивное меню: Отображение главного меню', true);
            
            sleep(2);
            $this->api->sendMessage($this->testChatId, "⏳ Выберите пункт меню...");
            
            sleep(10);
            
            $updates = $this->polling->pollOnce();
            foreach ($updates as $update) {
                if ($update->callbackQuery) {
                    $data = $update->callbackQuery->data;
                    
                    $this->api->answerCallbackQuery($update->callbackQuery->id);
                    
                    $response = match($data) {
                        'menu_stats' => "📊 Статистика:\n• Всего тестов: {$this->testsTotal}\n• Успешных: {$this->testsPassed}\n• Ошибок: {$this->testsFailed}",
                        'menu_settings' => "⚙️ Настройки:\n• База данных: подключена\n• Логирование: активно\n• OpenRouter: " . ($this->openRouter ? 'да' : 'нет'),
                        'menu_help' => "ℹ️ Помощь:\nДоступные команды:\n/start - начало\n/info - информация\n/stat - статистика",
                        'menu_exit' => "🚪 Выход из меню",
                        default => "Неизвестная команда",
                    };
                    
                    $this->api->editMessageText(
                        $this->testChatId,
                        $update->callbackQuery->message->messageId,
                        $response
                    );
                    
                    $this->recordTestResult('Интерактивное меню: Навигация', true, "Выбрано: {$data}");
                    break;
                }
            }
            
        } catch (\Exception $e) {
            $this->recordTestResult('Интерактивное меню', false, $e->getMessage());
        }
    }
    
    private function displayResults(): void
    {
        $this->sendTestNotification('finish', "Завершение тестирования");
        
        $this->logger->info('=== РЕЗУЛЬТАТЫ ТЕСТИРОВАНИЯ ===');
        
        $passRate = $this->testsTotal > 0 ? round(($this->testsPassed / $this->testsTotal) * 100, 2) : 0;
        
        $summary = "📊 ИТОГОВЫЕ РЕЗУЛЬТАТЫ ТЕСТИРОВАНИЯ\n\n";
        $summary .= "Всего тестов: {$this->testsTotal}\n";
        $summary .= self::EMOJIS['success'] . " Успешных: {$this->testsPassed}\n";
        $summary .= self::EMOJIS['error'] . " Ошибок: {$this->testsFailed}\n";
        $summary .= "Процент успеха: {$passRate}%\n\n";
        
        if ($this->testsFailed > 0) {
            $summary .= "❌ НЕУДАЧНЫЕ ТЕСТЫ:\n";
            foreach ($this->testResults as $result) {
                if (!$result['passed']) {
                    $summary .= "• {$result['name']}\n";
                    if ($result['details']) {
                        $summary .= "  └ {$result['details']}\n";
                    }
                }
            }
        }
        
        $this->logger->info($summary);
        
        // Отправка финального отчета
        $this->api->sendMessage($this->testChatId, $summary);
        
        // Детальный отчет
        if ($this->testsTotal > 0) {
            $detailedReport = "📋 ДЕТАЛЬНЫЙ ОТЧЕТ:\n\n";
            foreach ($this->testResults as $i => $result) {
                $status = $result['passed'] ? self::EMOJIS['success'] : self::EMOJIS['error'];
                $detailedReport .= ($i + 1) . ". {$status} {$result['name']}\n";
                if ($result['details']) {
                    $detailedReport .= "   {$result['details']}\n";
                }
                $detailedReport .= "\n";
                
                // Отправляем частями, если слишком длинный
                if (strlen($detailedReport) > 3000) {
                    $this->api->sendMessage($this->testChatId, $detailedReport);
                    $detailedReport = "";
                    sleep(1);
                }
            }
            
            if (!empty($detailedReport)) {
                $this->api->sendMessage($this->testChatId, $detailedReport);
            }
        }
        
        $this->logger->info('=== ТЕСТИРОВАНИЕ ЗАВЕРШЕНО ===');
    }
}

// Запуск тестирования
try {
    $test = new TelegramBotPollingComprehensiveTest();
    $test->runAllTests();
    
    echo "\n✅ Тестирование завершено! Проверьте логи и сообщения в Telegram.\n";
    echo "Лог файл: logs/telegram_bot_polling_test.log\n";
    
} catch (\Exception $e) {
    echo "\n❌ Критическая ошибка: " . $e->getMessage() . "\n";
    echo "Трассировка: " . $e->getTraceAsString() . "\n";
    exit(1);
}

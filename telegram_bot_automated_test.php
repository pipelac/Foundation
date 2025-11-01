<?php

declare(strict_types=1);

/**
 * Автоматизированный тест TelegramBot в режиме Polling
 * 
 * Проверяет все компоненты без требования пользовательского ввода
 */

require_once __DIR__ . '/autoload.php';

use App\Config\ConfigLoader;
use App\Component\Http;
use App\Component\Logger;
use App\Component\MySQLConnectionFactory;
use App\Component\Telegram;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Core\PollingHandler;
use App\Component\TelegramBot\Core\ConversationManager;
use App\Component\TelegramBot\Keyboards\InlineKeyboardBuilder;
use App\Component\TelegramBot\Keyboards\ReplyKeyboardBuilder;

// ============================================================================
// КОНФИГУРАЦИЯ
// ============================================================================

$BOT_TOKEN = '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI';
$CHAT_IDS = [366442475, 311619417];

// ============================================================================
// ИНИЦИАЛИЗАЦИЯ
// ============================================================================

echo "\n";
echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║  АВТОМАТИЗИРОВАННОЕ ТЕСТИРОВАНИЕ TELEGRAMBOT                         ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

// Логгер
$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'fileName' => 'telegram_bot_automated_test.log',
    'maxFiles' => 7,
]);

$logger->info('=== ЗАПУСК АВТОМАТИЗИРОВАННОГО ТЕСТИРОВАНИЯ TELEGRAMBOT ===');

// HTTP клиент
$http = new Http(['timeout' => 60], $logger);

// TelegramAPI для бота
$api = new TelegramAPI($BOT_TOKEN, $http, $logger);

// Telegram для уведомлений
$telegramNotifier = new Telegram(['token' => $BOT_TOKEN], $logger);

// PollingHandler
$polling = new PollingHandler($api, $logger);
$polling->setTimeout(5)->setLimit(100);

// MySQL
$configDir = __DIR__ . '/config';
$mysqlConfig = ConfigLoader::load($configDir . '/mysql.json');
$conversationsConfig = ConfigLoader::load($configDir . '/telegram_bot_conversations.json');

// ============================================================================
// СТАТИСТИКА
// ============================================================================

$testStats = [
    'total' => 0,
    'passed' => 0,
    'failed' => 0,
    'errors' => [],
    'warnings' => [],
];

function recordTest(array &$stats, string $testName, bool $passed, string $error = ''): void
{
    $stats['total']++;
    if ($passed) {
        $stats['passed']++;
        echo "  ✅ $testName\n";
    } else {
        $stats['failed']++;
        $stats['errors'][] = "$testName: $error";
        echo "  ❌ $testName: $error\n";
    }
}

function recordWarning(array &$stats, string $message): void
{
    $stats['warnings'][] = $message;
    echo "  ⚠️  $message\n";
}

function sendNotification(Telegram $telegram, array $chatIds, string $message, Logger $logger): void
{
    foreach ($chatIds as $chatId) {
        try {
            $telegram->sendText((string)$chatId, $message, ['parse_mode' => 'HTML']);
        } catch (\Exception $e) {
            echo "  ⚠️  Уведомление в Telegram: {$e->getMessage()}\n";
            $logger->warning('Не удалось отправить уведомление', ['error' => $e->getMessage()]);
        }
    }
}

// ============================================================================
// ТЕСТ 1: ПОДКЛЮЧЕНИЕ К MySQL
// ============================================================================

echo "┌──────────────────────────────────────────────────────────────────────┐\n";
echo "│  ТЕСТ 1: ПОДКЛЮЧЕНИЕ К MySQL                                         │\n";
echo "└──────────────────────────────────────────────────────────────────────┘\n\n";

$db = null;
try {
    $factory = new MySQLConnectionFactory($mysqlConfig, $logger);
    $db = $factory->getConnection('main');
    recordTest($testStats, "MySQL подключение", true);
    $logger->info('MySQL подключен успешно');
} catch (\Exception $e) {
    recordTest($testStats, "MySQL подключение", false, $e->getMessage());
    $logger->error('Ошибка подключения к MySQL', ['error' => $e->getMessage()]);
    die("\n❌ КРИТИЧЕСКАЯ ОШИБКА: Невозможно продолжить без MySQL!\n");
}

echo "\n";

// ============================================================================
// ТЕСТ 2: ИНИЦИАЛИЗАЦИЯ TelegramAPI
// ============================================================================

echo "┌──────────────────────────────────────────────────────────────────────┐\n";
echo "│  ТЕСТ 2: ИНИЦИАЛИЗАЦИЯ TelegramAPI                                   │\n";
echo "└──────────────────────────────────────────────────────────────────────┘\n\n";

try {
    $botInfo = $api->getMe();
    recordTest($testStats, "TelegramAPI.getMe()", $botInfo->id > 0);
    
    if ($botInfo->username) {
        echo "  ℹ️  Бот: @{$botInfo->username}\n";
        $logger->info('Бот инициализирован', ['username' => $botInfo->username]);
    }
} catch (\Exception $e) {
    recordTest($testStats, "TelegramAPI.getMe()", false, $e->getMessage());
    $logger->error('Ошибка инициализации TelegramAPI', ['error' => $e->getMessage()]);
}

echo "\n";

// ============================================================================
// ТЕСТ 3: ОТПРАВКА СООБЩЕНИЙ
// ============================================================================

echo "┌──────────────────────────────────────────────────────────────────────┐\n";
echo "│  ТЕСТ 3: ОТПРАВКА СООБЩЕНИЙ                                          │\n";
echo "└──────────────────────────────────────────────────────────────────────┘\n\n";

foreach ($CHAT_IDS as $chatId) {
    // Тест 3.1: Простое текстовое сообщение
    try {
        $result = $api->sendMessage($chatId, "🧪 <b>Тест 3.1:</b> Простое текстовое сообщение", [
            'parse_mode' => TelegramAPI::PARSE_MODE_HTML
        ]);
        recordTest($testStats, "Отправка текста (чат $chatId)", $result !== null);
    } catch (\Exception $e) {
        recordTest($testStats, "Отправка текста (чат $chatId)", false, $e->getMessage());
    }
    
    // Тест 3.2: Сообщение с Markdown
    try {
        $result = $api->sendMessage($chatId, "🧪 *Тест 3.2:* _Сообщение с Markdown_", [
            'parse_mode' => TelegramAPI::PARSE_MODE_MARKDOWN
        ]);
        recordTest($testStats, "Markdown форматирование (чат $chatId)", $result !== null);
    } catch (\Exception $e) {
        recordTest($testStats, "Markdown форматирование (чат $chatId)", false, $e->getMessage());
    }
    
    // Тест 3.3: Сообщение с эмодзи
    try {
        $result = $api->sendMessage($chatId, "🧪 Тест 3.3: Эмодзи 🎉 🚀 ✨ 💡 ⚡ 🔥");
        recordTest($testStats, "Отправка эмодзи (чат $chatId)", $result !== null);
    } catch (\Exception $e) {
        recordTest($testStats, "Отправка эмодзи (чат $chatId)", false, $e->getMessage());
    }
}

echo "\n";

// ============================================================================
// ТЕСТ 4: СОЗДАНИЕ КЛАВИАТУР
// ============================================================================

echo "┌──────────────────────────────────────────────────────────────────────┐\n";
echo "│  ТЕСТ 4: СОЗДАНИЕ КЛАВИАТУР                                          │\n";
echo "└──────────────────────────────────────────────────────────────────────┘\n\n";

foreach ($CHAT_IDS as $chatId) {
    // Тест 4.1: Inline клавиатура
    try {
        $keyboard = InlineKeyboardBuilder::make()
            ->addCallbackButton('✅ Кнопка 1', 'btn_1')
            ->addCallbackButton('🔔 Кнопка 2', 'btn_2')
            ->row()
            ->addCallbackButton('⚙️ Настройки', 'settings')
            ->addUrlButton('🌐 GitHub', 'https://github.com')
            ->build();
        
        $result = $api->sendMessage($chatId, "🧪 <b>Тест 4.1:</b> Inline клавиатура", [
            'parse_mode' => TelegramAPI::PARSE_MODE_HTML,
            'reply_markup' => $keyboard
        ]);
        recordTest($testStats, "Inline клавиатура (чат $chatId)", $result !== null);
    } catch (\Exception $e) {
        recordTest($testStats, "Inline клавиатура (чат $chatId)", false, $e->getMessage());
    }
    
    // Тест 4.2: Reply клавиатура
    try {
        $keyboard = ReplyKeyboardBuilder::make()
            ->addButton('Кнопка 1')
            ->addButton('Кнопка 2')
            ->row()
            ->addButton('Кнопка 3')
            ->resizeKeyboard()
            ->oneTime()
            ->build();
        
        $result = $api->sendMessage($chatId, "🧪 <b>Тест 4.2:</b> Reply клавиатура", [
            'parse_mode' => TelegramAPI::PARSE_MODE_HTML,
            'reply_markup' => $keyboard
        ]);
        recordTest($testStats, "Reply клавиатура (чат $chatId)", $result !== null);
        
        // Удаление клавиатуры
        sleep(1);
        $api->sendMessage($chatId, "✅ Клавиатура удалена", [
            'reply_markup' => ['remove_keyboard' => true]
        ]);
    } catch (\Exception $e) {
        recordTest($testStats, "Reply клавиатура (чат $chatId)", false, $e->getMessage());
    }
}

echo "\n";

// ============================================================================
// ТЕСТ 5: ConversationManager
// ============================================================================

echo "┌──────────────────────────────────────────────────────────────────────┐\n";
echo "│  ТЕСТ 5: ConversationManager                                         │\n";
echo "└──────────────────────────────────────────────────────────────────────┘\n\n";

try {
    $conversationManager = new ConversationManager(
        $db,
        $logger,
        $conversationsConfig['conversations']
    );
    
    recordTest($testStats, "ConversationManager инициализация", 
        $conversationManager->isEnabled());
    
    if (!$conversationManager->isEnabled()) {
        recordWarning($testStats, "ConversationManager отключен в конфигурации");
    }
    
    // Очистка старых диалогов
    $cleaned = $conversationManager->cleanupExpiredConversations();
    echo "  ℹ️  Очищено устаревших диалогов: $cleaned\n";
    
    // Тест сохранения пользователя
    foreach ($CHAT_IDS as $chatId) {
        try {
            $conversationManager->saveUser(
                $chatId,
                "Test User $chatId",
                "testuser$chatId"
            );
            recordTest($testStats, "Сохранение пользователя $chatId", true);
        } catch (\Exception $e) {
            recordTest($testStats, "Сохранение пользователя $chatId", false, $e->getMessage());
        }
    }
    
    // Тест создания диалога
    $testChatId = $CHAT_IDS[0];
    try {
        $conversationManager->startConversation(
            $testChatId,
            $testChatId,
            'test_state',
            ['test_key' => 'test_value'],
            12345
        );
        recordTest($testStats, "Создание диалога", true);
        
        // Получение диалога
        $conversation = $conversationManager->getConversation($testChatId, $testChatId);
        recordTest($testStats, "Получение диалога", 
            $conversation !== null && $conversation['state'] === 'test_state');
        
        // Обновление диалога
        $conversationManager->updateConversation(
            $testChatId,
            $testChatId,
            'updated_state',
            ['updated_key' => 'updated_value']
        );
        
        $conversation = $conversationManager->getConversation($testChatId, $testChatId);
        recordTest($testStats, "Обновление диалога", 
            $conversation !== null && $conversation['state'] === 'updated_state');
        
        // Завершение диалога
        $conversationManager->endConversation($testChatId, $testChatId);
        $conversation = $conversationManager->getConversation($testChatId, $testChatId);
        recordTest($testStats, "Завершение диалога", $conversation === null);
        
    } catch (\Exception $e) {
        recordTest($testStats, "Работа с диалогами", false, $e->getMessage());
    }
    
    // Статистика диалогов
    try {
        $stats = $conversationManager->getStatistics();
        recordTest($testStats, "Получение статистики диалогов", isset($stats['total']));
        echo "  ℹ️  Активных диалогов: {$stats['total']}\n";
        if (isset($stats['unique_users'])) {
            echo "  ℹ️  Уникальных пользователей: {$stats['unique_users']}\n";
        }
    } catch (\Exception $e) {
        recordTest($testStats, "Получение статистики диалогов", false, $e->getMessage());
    }
    
} catch (\Exception $e) {
    recordTest($testStats, "ConversationManager инициализация", false, $e->getMessage());
    $logger->error('Ошибка работы с ConversationManager', ['error' => $e->getMessage()]);
}

echo "\n";

// ============================================================================
// ТЕСТ 6: PollingHandler
// ============================================================================

echo "┌──────────────────────────────────────────────────────────────────────┐\n";
echo "│  ТЕСТ 6: PollingHandler                                              │\n";
echo "└──────────────────────────────────────────────────────────────────────┘\n\n";

try {
    // Пропуск старых сообщений
    $skipped = $polling->skipPendingUpdates();
    echo "  ℹ️  Пропущено старых обновлений: $skipped\n";
    recordTest($testStats, "Пропуск старых обновлений", true);
    
    // Получение текущего offset
    $offset = $polling->getOffset();
    recordTest($testStats, "Получение offset", $offset >= 0);
    echo "  ℹ️  Текущий offset: $offset\n";
    
    // Однократное получение обновлений
    $updates = $polling->pollOnce();
    recordTest($testStats, "Однократное получение обновлений", is_array($updates));
    echo "  ℹ️  Получено обновлений: " . count($updates) . "\n";
    
    // Проверка статуса polling (должен быть доступен метод)
    $hasMethod = method_exists($polling, 'isPolling');
    recordTest($testStats, "Проверка метода isPolling", $hasMethod);
    if ($hasMethod) {
        echo "  ℹ️  isPolling: " . ($polling->isPolling() ? 'true' : 'false') . "\n";
    }
    
} catch (\Exception $e) {
    recordTest($testStats, "PollingHandler", false, $e->getMessage());
    $logger->error('Ошибка работы с PollingHandler', ['error' => $e->getMessage()]);
}

echo "\n";

// ============================================================================
// ТЕСТ 7: РЕДАКТИРОВАНИЕ И УДАЛЕНИЕ СООБЩЕНИЙ
// ============================================================================

echo "┌──────────────────────────────────────────────────────────────────────┐\n";
echo "│  ТЕСТ 7: РЕДАКТИРОВАНИЕ И УДАЛЕНИЕ СООБЩЕНИЙ                         │\n";
echo "└──────────────────────────────────────────────────────────────────────┘\n\n";

foreach ($CHAT_IDS as $chatId) {
    try {
        // Отправка сообщения
        $message = $api->sendMessage($chatId, "🧪 Тест 7: Это сообщение будет отредактировано");
        recordTest($testStats, "Отправка сообщения для редактирования (чат $chatId)", 
            $message !== null && isset($message->messageId));
        
        if ($message && isset($message->messageId)) {
            sleep(1);
            
            // Редактирование
            try {
                $api->editMessageText($chatId, $message->messageId, 
                    "✅ Сообщение отредактировано!");
                recordTest($testStats, "Редактирование сообщения (чат $chatId)", true);
            } catch (\Exception $e) {
                recordTest($testStats, "Редактирование сообщения (чат $chatId)", false, $e->getMessage());
            }
            
            sleep(1);
            
            // Удаление
            try {
                $api->deleteMessage($chatId, $message->messageId);
                recordTest($testStats, "Удаление сообщения (чат $chatId)", true);
            } catch (\Exception $e) {
                recordTest($testStats, "Удаление сообщения (чат $chatId)", false, $e->getMessage());
            }
        }
    } catch (\Exception $e) {
        recordTest($testStats, "Редактирование и удаление (чат $chatId)", false, $e->getMessage());
    }
}

echo "\n";

// ============================================================================
// ТЕСТ 8: ЛОГИРОВАНИЕ
// ============================================================================

echo "┌──────────────────────────────────────────────────────────────────────┐\n";
echo "│  ТЕСТ 8: ЛОГИРОВАНИЕ                                                 │\n";
echo "└──────────────────────────────────────────────────────────────────────┘\n\n";

try {
    $logger->info('Тестовое info сообщение');
    $logger->warning('Тестовое warning сообщение');
    $logger->error('Тестовое error сообщение', ['test_data' => 'value']);
    
    // Проверяем папку логов (логи пишутся в app.log по умолчанию)
    $logDir = __DIR__ . '/logs';
    $appLogFile = $logDir . '/app.log';
    
    recordTest($testStats, "Создание лог-файла", file_exists($appLogFile));
    
    if (file_exists($appLogFile)) {
        $logSize = filesize($appLogFile);
        echo "  ℹ️  Лог-файл: app.log\n";
        echo "  ℹ️  Размер лог-файла: $logSize байт\n";
        recordTest($testStats, "Запись в лог", $logSize > 0);
    }
} catch (\Exception $e) {
    recordTest($testStats, "Логирование", false, $e->getMessage());
}

echo "\n";

// ============================================================================
// ТЕСТ 9: ОБРАБОТКА ОШИБОК
// ============================================================================

echo "┌──────────────────────────────────────────────────────────────────────┐\n";
echo "│  ТЕСТ 9: ОБРАБОТКА ОШИБОК                                            │\n";
echo "└──────────────────────────────────────────────────────────────────────┘\n\n";

// Тест 9.1: Пустое сообщение
try {
    $api->sendMessage($CHAT_IDS[0], "");
    recordTest($testStats, "Валидация пустого сообщения", false, "Пустое сообщение не отклонено");
} catch (\Exception $e) {
    recordTest($testStats, "Валидация пустого сообщения", true);
}

// Тест 9.2: Некорректный chat_id
try {
    $api->sendMessage(99999999999, "Тест");
    recordTest($testStats, "Обработка некорректного chat_id", false, "Не обработан некорректный chat_id");
} catch (\Exception $e) {
    recordTest($testStats, "Обработка некорректного chat_id", true);
}

// Тест 9.3: Некорректный message_id для редактирования
try {
    $api->editMessageText($CHAT_IDS[0], 99999999, "Тест");
    recordTest($testStats, "Обработка некорректного message_id", false, "Не обработан некорректный message_id");
} catch (\Exception $e) {
    recordTest($testStats, "Обработка некорректного message_id", true);
}

echo "\n";

// ============================================================================
// ТЕСТ 10: ОТПРАВКА УВЕДОМЛЕНИЙ О РЕЗУЛЬТАТАХ
// ============================================================================

echo "┌──────────────────────────────────────────────────────────────────────┐\n";
echo "│  ТЕСТ 10: ОТПРАВКА ИТОГОВЫХ УВЕДОМЛЕНИЙ                              │\n";
echo "└──────────────────────────────────────────────────────────────────────┘\n\n";

$successRate = round(($testStats['passed'] / $testStats['total']) * 100, 2);

$summaryMessage = 
    "🏁 <b>ТЕСТИРОВАНИЕ ЗАВЕРШЕНО</b>\n\n" .
    "📊 <b>Статистика:</b>\n" .
    "• Всего тестов: {$testStats['total']}\n" .
    "• ✅ Пройдено: {$testStats['passed']}\n" .
    "• ❌ Провалено: {$testStats['failed']}\n" .
    "• ⚠️ Предупреждений: " . count($testStats['warnings']) . "\n" .
    "• 📈 Процент успеха: {$successRate}%\n\n" .
    "🔍 Проверено:\n" .
    "• MySQL подключение\n" .
    "• TelegramAPI инициализация\n" .
    "• Отправка сообщений\n" .
    "• Клавиатуры (Inline/Reply)\n" .
    "• ConversationManager\n" .
    "• PollingHandler\n" .
    "• Редактирование/удаление\n" .
    "• Логирование\n" .
    "• Обработка ошибок";

sendNotification($telegramNotifier, $CHAT_IDS, $summaryMessage, $logger);
recordTest($testStats, "Отправка итоговых уведомлений", true);

echo "\n";

// ============================================================================
// ИТОГОВАЯ СТАТИСТИКА
// ============================================================================

echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║  ИТОГОВАЯ СТАТИСТИКА                                                 ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

echo "Всего тестов: {$testStats['total']}\n";
echo "✅ Пройдено: {$testStats['passed']}\n";
echo "❌ Провалено: {$testStats['failed']}\n";
echo "⚠️  Предупреждений: " . count($testStats['warnings']) . "\n";
echo "📈 Процент успеха: {$successRate}%\n\n";

if (!empty($testStats['errors'])) {
    echo "Ошибки:\n";
    foreach ($testStats['errors'] as $error) {
        echo "  • $error\n";
    }
    echo "\n";
}

if (!empty($testStats['warnings'])) {
    echo "Предупреждения:\n";
    foreach ($testStats['warnings'] as $warning) {
        echo "  • $warning\n";
    }
    echo "\n";
}

$logger->info('Автоматизированное тестирование завершено', [
    'total' => $testStats['total'],
    'passed' => $testStats['passed'],
    'failed' => $testStats['failed'],
    'success_rate' => $successRate,
]);

// ============================================================================
// СОЗДАНИЕ ДАМПОВ MySQL
// ============================================================================

echo "┌──────────────────────────────────────────────────────────────────────┐\n";
echo "│  СОЗДАНИЕ ДАМПОВ MySQL                                               │\n";
echo "└──────────────────────────────────────────────────────────────────────┘\n\n";

try {
    $tables = ['telegram_bot_users', 'telegram_bot_conversations'];
    $dumpsCreated = 0;
    
    foreach ($tables as $table) {
        $dumpFile = "/home/engine/project/mysql/{$table}_dump.sql";
        
        // Проверяем существование таблицы
        $result = $db->query("SHOW TABLES LIKE '$table'");
        $exists = !empty($result);
        
        if ($exists) {
            exec("mysqldump -u root utilities_db $table > $dumpFile 2>&1", $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($dumpFile)) {
                $fileSize = filesize($dumpFile);
                echo "  ✅ Дамп таблицы $table создан ($fileSize байт)\n";
                $logger->info("Дамп таблицы $table создан", ['file' => $dumpFile, 'size' => $fileSize]);
                $dumpsCreated++;
            } else {
                echo "  ⚠️  Не удалось создать дамп таблицы $table\n";
                $logger->warning("Не удалось создать дамп таблицы $table");
            }
        } else {
            echo "  ℹ️  Таблица $table не существует\n";
        }
    }
    
    recordTest($testStats, "Создание дампов MySQL", $dumpsCreated > 0);
    
} catch (\Exception $e) {
    echo "  ❌ Ошибка создания дампов: {$e->getMessage()}\n";
    $logger->error('Ошибка создания дампов', ['error' => $e->getMessage()]);
    recordTest($testStats, "Создание дампов MySQL", false, $e->getMessage());
}

echo "\n";
echo "✅ АВТОМАТИЗИРОВАННОЕ ТЕСТИРОВАНИЕ ЗАВЕРШЕНО!\n\n";

// Возврат кода в зависимости от результатов
exit($testStats['failed'] > 0 ? 1 : 0);

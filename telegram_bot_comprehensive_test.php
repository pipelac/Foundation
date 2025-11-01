<?php

declare(strict_types=1);

/**
 * Комплексный тест TelegramBot в режиме Polling
 * 
 * Проверяет все уровни функциональности:
 * 1. Начальные операции (текст, эмодзи)
 * 2. Базовые операции (текст, медиа)
 * 3. Операции с клавиатурами
 * 4. Диалоговые сценарии с контекстом
 * 5. Обработка ошибок
 * 6. Комплексные сценарии
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
use App\Component\TelegramBot\Entities\Update;
use App\Component\TelegramBot\Keyboards\InlineKeyboardBuilder;
use App\Component\TelegramBot\Keyboards\ReplyKeyboardBuilder;

// ============================================================================
// КОНФИГУРАЦИЯ
// ============================================================================

$BOT_TOKEN = '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI';
$CHAT_IDS = [366442475, 311619417];
$TEST_TIMEOUT = 30; // секунд на реакцию пользователя
$MAX_AUTO_RETRIES = 2; // количество попыток эмуляции при отсутствии реакции

// ============================================================================
// ИНИЦИАЛИЗАЦИЯ
// ============================================================================

echo "\n╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║  КОМПЛЕКСНОЕ ТЕСТИРОВАНИЕ TELEGRAMBOT В РЕЖИМЕ POLLING              ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

// Логгер
$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'fileName' => 'telegram_bot_comprehensive_test.log',
    'maxFiles' => 7,
]);

$logger->info('=== ЗАПУСК КОМПЛЕКСНОГО ТЕСТИРОВАНИЯ TELEGRAMBOT ===');

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

echo "📦 Подключение к MySQL...\n";
try {
    $factory = new MySQLConnectionFactory($mysqlConfig, $logger);
    $db = $factory->getConnection('main');
    echo "✅ MySQL подключен\n";
    $logger->info('MySQL подключен успешно');
} catch (\Exception $e) {
    echo "❌ ОШИБКА: Не удалось подключиться к MySQL: {$e->getMessage()}\n";
    $logger->error('Ошибка подключения к MySQL', ['error' => $e->getMessage()]);
    die("Тестирование невозможно без MySQL!\n");
}

// ConversationManager
$conversationManager = new ConversationManager(
    $db,
    $logger,
    $conversationsConfig['conversations']
);

if (!$conversationManager->isEnabled()) {
    echo "❌ ПРЕДУПРЕЖДЕНИЕ: ConversationManager отключен в конфиге\n";
}

// Очистка старых диалогов
$conversationManager->cleanupExpiredConversations();

echo "\n";

// ============================================================================
// УТИЛИТЫ
// ============================================================================

/**
 * Отправка уведомления в тестовый Telegram
 */
function sendNotification(Telegram $telegram, array $chatIds, string $message): void
{
    foreach ($chatIds as $chatId) {
        try {
            $telegram->sendText((string)$chatId, $message, ['parse_mode' => 'HTML']);
        } catch (\Exception $e) {
            echo "⚠️  Не удалось отправить уведомление в Telegram: {$e->getMessage()}\n";
        }
    }
}

/**
 * Ожидание сообщения от пользователя
 */
function waitForUserMessage(
    PollingHandler $polling,
    int $chatId,
    int $timeout = 30,
    ?callable $validator = null
): ?Update {
    $startTime = time();
    
    while (time() - $startTime < $timeout) {
        $updates = $polling->pollOnce();
        
        foreach ($updates as $update) {
            if ($update->isMessage() && $update->message->chat->id === $chatId) {
                if ($validator === null || $validator($update)) {
                    return $update;
                }
            }
        }
        
        sleep(1);
    }
    
    return null;
}

/**
 * Ожидание callback запроса
 */
function waitForCallback(
    PollingHandler $polling,
    int $chatId,
    int $timeout = 30,
    ?string $expectedData = null
): ?Update {
    $startTime = time();
    
    while (time() - $startTime < $timeout) {
        $updates = $polling->pollOnce();
        
        foreach ($updates as $update) {
            if ($update->isCallbackQuery() && $update->callbackQuery->message->chat->id === $chatId) {
                if ($expectedData === null || $update->callbackQuery->data === $expectedData) {
                    return $update;
                }
            }
        }
        
        sleep(1);
    }
    
    return null;
}

// ============================================================================
// СТАТИСТИКА ТЕСТИРОВАНИЯ
// ============================================================================

$testStats = [
    'total' => 0,
    'passed' => 0,
    'failed' => 0,
    'errors' => [],
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

// ============================================================================
// УРОВЕНЬ 1: НАЧАЛЬНЫЕ ОПЕРАЦИИ
// ============================================================================

echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║  УРОВЕНЬ 1: НАЧАЛЬНЫЕ ОПЕРАЦИИ (SMOKE TESTS)                        ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

sendNotification($telegramNotifier, $CHAT_IDS, 
    "🚀 <b>НАЧАЛО ТЕСТИРОВАНИЯ</b>\n\n" .
    "📋 Уровень 1: Начальные операции\n" .
    "⏱️ Таймаут: {$TEST_TIMEOUT} сек"
);

// Пропуск старых сообщений
$skipped = $polling->skipPendingUpdates();
echo "🔄 Пропущено старых обновлений: $skipped\n\n";

foreach ($CHAT_IDS as $chatId) {
    echo "--- ТЕСТИРОВАНИЕ ЧАТА: $chatId ---\n\n";
    
    // Тест 1.1: Отправка простого текста
    try {
        $api->sendMessage($chatId, "🧪 <b>Тест 1.1:</b> Простое текстовое сообщение\n\nОтветьте любым текстом", [
            'parse_mode' => TelegramAPI::PARSE_MODE_HTML
        ]);
        
        $update = waitForUserMessage($polling, $chatId, $TEST_TIMEOUT);
        recordTest($testStats, "Тест 1.1 (чат $chatId): Отправка и получение текста", 
            $update !== null && $update->message->text !== null,
            $update === null ? "Таймаут ожидания" : ""
        );
        
        if ($update && $update->message->text) {
            $api->sendMessage($chatId, "✅ Получен текст: " . $update->message->text);
        }
    } catch (\Exception $e) {
        recordTest($testStats, "Тест 1.1 (чат $chatId): Отправка и получение текста", false, $e->getMessage());
        $logger->error('Тест 1.1 failed', ['error' => $e->getMessage()]);
    }
    
    sleep(2);
    
    // Тест 1.2: Текст с эмодзи
    try {
        $api->sendMessage($chatId, "🧪 <b>Тест 1.2:</b> Сообщение с эмодзи 🎉🚀✨\n\nОтветьте текстом с эмодзи", [
            'parse_mode' => TelegramAPI::PARSE_MODE_HTML
        ]);
        
        $update = waitForUserMessage($polling, $chatId, $TEST_TIMEOUT);
        recordTest($testStats, "Тест 1.2 (чат $chatId): Текст с эмодзи", 
            $update !== null && $update->message->text !== null,
            $update === null ? "Таймаут ожидания" : ""
        );
        
        if ($update && $update->message->text) {
            $api->sendMessage($chatId, "✅ Получен текст с эмодзи: " . $update->message->text);
        }
    } catch (\Exception $e) {
        recordTest($testStats, "Тест 1.2 (чат $chatId): Текст с эмодзи", false, $e->getMessage());
        $logger->error('Тест 1.2 failed', ['error' => $e->getMessage()]);
    }
    
    echo "\n";
}

sendNotification($telegramNotifier, $CHAT_IDS, 
    "✅ <b>Уровень 1 завершен</b>\n\n" .
    "Пройдено: {$testStats['passed']}/{$testStats['total']}"
);

sleep(3);

// ============================================================================
// УРОВЕНЬ 2: БАЗОВЫЕ ОПЕРАЦИИ
// ============================================================================

echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║  УРОВЕНЬ 2: БАЗОВЫЕ ОПЕРАЦИИ                                         ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

sendNotification($telegramNotifier, $CHAT_IDS, 
    "📋 <b>Уровень 2:</b> Базовые операции\n" .
    "Тестирование медиа файлов"
);

foreach ($CHAT_IDS as $chatId) {
    echo "--- ТЕСТИРОВАНИЕ ЧАТА: $chatId ---\n\n";
    
    // Тест 2.1: Отправка фото
    try {
        $api->sendMessage($chatId, "🧪 <b>Тест 2.1:</b> Отправка изображения\n\nОтправьте любое фото", [
            'parse_mode' => TelegramAPI::PARSE_MODE_HTML
        ]);
        
        $update = waitForUserMessage($polling, $chatId, $TEST_TIMEOUT, function($u) {
            return $u->message->photo !== null;
        });
        
        recordTest($testStats, "Тест 2.1 (чат $chatId): Получение фото", 
            $update !== null && !empty($update->message->photo),
            $update === null ? "Таймаут ожидания" : ""
        );
        
        if ($update && !empty($update->message->photo)) {
            $photos = $update->message->photo;
            $photoId = $photos[count($photos) - 1]->fileId;
            $api->sendMessage($chatId, "✅ Фото получено!\nFile ID: $photoId");
            
            // Эхо фото
            $api->sendPhoto($chatId, $photoId, ['caption' => 'Эхо вашего фото']);
        }
    } catch (\Exception $e) {
        recordTest($testStats, "Тест 2.1 (чат $chatId): Получение фото", false, $e->getMessage());
        $logger->error('Тест 2.1 failed', ['error' => $e->getMessage()]);
    }
    
    sleep(2);
    
    // Тест 2.2: Отправка документа
    try {
        $api->sendMessage($chatId, "🧪 <b>Тест 2.2:</b> Отправка документа\n\nОтправьте любой файл (документ)", [
            'parse_mode' => TelegramAPI::PARSE_MODE_HTML
        ]);
        
        $update = waitForUserMessage($polling, $chatId, $TEST_TIMEOUT, function($u) {
            return $u->message->document !== null;
        });
        
        recordTest($testStats, "Тест 2.2 (чат $chatId): Получение документа", 
            $update !== null && $update->message->document !== null,
            $update === null ? "Таймаут ожидания" : ""
        );
        
        if ($update && $update->message->document) {
            $docId = $update->message->document->fileId;
            $fileName = $update->message->document->fileName ?? 'unknown';
            $api->sendMessage($chatId, "✅ Документ получен!\nИмя: $fileName\nFile ID: $docId");
        }
    } catch (\Exception $e) {
        recordTest($testStats, "Тест 2.2 (чат $chatId): Получение документа", false, $e->getMessage());
        $logger->error('Тест 2.2 failed', ['error' => $e->getMessage()]);
    }
    
    echo "\n";
}

sendNotification($telegramNotifier, $CHAT_IDS, 
    "✅ <b>Уровень 2 завершен</b>\n\n" .
    "Пройдено: {$testStats['passed']}/{$testStats['total']}"
);

sleep(3);

// ============================================================================
// УРОВЕНЬ 3: ОПЕРАЦИИ С КЛАВИАТУРАМИ
// ============================================================================

echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║  УРОВЕНЬ 3: ОПЕРАЦИИ С КЛАВИАТУРАМИ                                  ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

sendNotification($telegramNotifier, $CHAT_IDS, 
    "⌨️ <b>Уровень 3:</b> Тестирование клавиатур\n" .
    "Reply и Inline клавиатуры"
);

foreach ($CHAT_IDS as $chatId) {
    echo "--- ТЕСТИРОВАНИЕ ЧАТА: $chatId ---\n\n";
    
    // Тест 3.1: Reply клавиатура
    try {
        $keyboard = ReplyKeyboardBuilder::make()
            ->addButton('Кнопка 1')
            ->addButton('Кнопка 2')
            ->row()
            ->addButton('Кнопка 3')
            ->resizeKeyboard()
            ->oneTime()
            ->build();
        
        $api->sendMessage($chatId, "🧪 <b>Тест 3.1:</b> Reply клавиатура\n\nНажмите любую кнопку", [
            'parse_mode' => TelegramAPI::PARSE_MODE_HTML,
            'reply_markup' => $keyboard
        ]);
        
        $update = waitForUserMessage($polling, $chatId, $TEST_TIMEOUT, function($u) {
            return in_array($u->message->text, ['Кнопка 1', 'Кнопка 2', 'Кнопка 3']);
        });
        
        recordTest($testStats, "Тест 3.1 (чат $chatId): Reply клавиатура", 
            $update !== null,
            $update === null ? "Таймаут ожидания" : ""
        );
        
        if ($update && $update->message->text) {
            $api->sendMessage($chatId, "✅ Нажата: " . $update->message->text, [
                'reply_markup' => ['remove_keyboard' => true]
            ]);
        }
    } catch (\Exception $e) {
        recordTest($testStats, "Тест 3.1 (чат $chatId): Reply клавиатура", false, $e->getMessage());
        $logger->error('Тест 3.1 failed', ['error' => $e->getMessage()]);
    }
    
    sleep(2);
    
    // Тест 3.2: Inline клавиатура
    try {
        $keyboard = InlineKeyboardBuilder::make()
            ->addCallbackButton('✅ Вариант 1', 'test_option_1')
            ->addCallbackButton('🔔 Вариант 2', 'test_option_2')
            ->row()
            ->addCallbackButton('⚙️ Настройки', 'test_settings')
            ->build();
        
        $sentMsg = $api->sendMessage($chatId, "🧪 <b>Тест 3.2:</b> Inline клавиатура\n\nНажмите любую кнопку", [
            'parse_mode' => TelegramAPI::PARSE_MODE_HTML,
            'reply_markup' => $keyboard
        ]);
        
        $update = waitForCallback($polling, $chatId, $TEST_TIMEOUT);
        
        recordTest($testStats, "Тест 3.2 (чат $chatId): Inline клавиатура", 
            $update !== null,
            $update === null ? "Таймаут ожидания" : ""
        );
        
        if ($update && $update->callbackQuery) {
            $api->answerCallbackQuery($update->callbackQuery->id, [
                'text' => 'Кнопка нажата!',
                'show_alert' => false
            ]);
            
            $api->editMessageText(
                $chatId,
                $sentMsg->messageId,
                "✅ Вы выбрали: " . $update->callbackQuery->data
            );
        }
    } catch (\Exception $e) {
        recordTest($testStats, "Тест 3.2 (чат $chatId): Inline клавиатура", false, $e->getMessage());
        $logger->error('Тест 3.2 failed', ['error' => $e->getMessage()]);
    }
    
    echo "\n";
}

sendNotification($telegramNotifier, $CHAT_IDS, 
    "✅ <b>Уровень 3 завершен</b>\n\n" .
    "Пройдено: {$testStats['passed']}/{$testStats['total']}"
);

sleep(3);

// ============================================================================
// УРОВЕНЬ 4: ДИАЛОГОВЫЕ СЦЕНАРИИ С КОНТЕКСТОМ
// ============================================================================

echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║  УРОВЕНЬ 4: ДИАЛОГОВЫЕ СЦЕНАРИИ С ЗАПОМИНАНИЕМ КОНТЕКСТА            ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

sendNotification($telegramNotifier, $CHAT_IDS, 
    "💬 <b>Уровень 4:</b> Диалоги с контекстом\n" .
    "Многошаговые сценарии"
);

foreach ($CHAT_IDS as $chatId) {
    echo "--- ТЕСТИРОВАНИЕ ЧАТА: $chatId ---\n\n";
    
    // Тест 4.1: Многошаговый диалог
    try {
        $userId = $chatId; // для упрощения
        
        // Начало диалога
        $keyboard = InlineKeyboardBuilder::make()
            ->addCallbackButton('👤 Пользователь', 'reg_user')
            ->addCallbackButton('👨‍💼 Админ', 'reg_admin')
            ->build();
        
        $sentMsg = $api->sendMessage($chatId, 
            "🧪 <b>Тест 4.1:</b> Многошаговый диалог\n\n" .
            "Выберите тип пользователя:", [
            'parse_mode' => TelegramAPI::PARSE_MODE_HTML,
            'reply_markup' => $keyboard
        ]);
        
        // Сохраняем диалог
        $conversationManager->startConversation(
            $chatId,
            $userId,
            'awaiting_type',
            [],
            $sentMsg->messageId
        );
        
        // Шаг 1: Выбор типа
        $update = waitForCallback($polling, $chatId, $TEST_TIMEOUT);
        
        if ($update && $update->callbackQuery) {
            $type = str_replace('reg_', '', $update->callbackQuery->data);
            
            $api->answerCallbackQuery($update->callbackQuery->id);
            $api->deleteMessage($chatId, $sentMsg->messageId);
            
            $conversationManager->updateConversation(
                $chatId,
                $userId,
                'awaiting_name',
                ['type' => $type]
            );
            
            $api->sendMessage($chatId, "✅ Тип выбран: $type\n\nВведите ваше имя:");
            
            // Шаг 2: Ввод имени
            $update = waitForUserMessage($polling, $chatId, $TEST_TIMEOUT);
            
            if ($update && $update->message->text) {
                $name = $update->message->text;
                
                $conversationManager->updateConversation(
                    $chatId,
                    $userId,
                    'awaiting_email',
                    ['name' => $name]
                );
                
                $api->sendMessage($chatId, "✅ Имя сохранено: $name\n\nВведите ваш email:");
                
                // Шаг 3: Ввод email
                $update = waitForUserMessage($polling, $chatId, $TEST_TIMEOUT);
                
                if ($update && $update->message->text) {
                    $email = $update->message->text;
                    
                    $conversation = $conversationManager->getConversation($chatId, $userId);
                    $data = $conversation['data'];
                    
                    $api->sendMessage($chatId, 
                        "✅ <b>Данные сохранены!</b>\n\n" .
                        "Тип: {$data['type']}\n" .
                        "Имя: {$data['name']}\n" .
                        "Email: $email", [
                        'parse_mode' => TelegramAPI::PARSE_MODE_HTML
                    ]);
                    
                    $conversationManager->endConversation($chatId, $userId);
                    
                    recordTest($testStats, "Тест 4.1 (чат $chatId): Многошаговый диалог", true);
                } else {
                    recordTest($testStats, "Тест 4.1 (чат $chatId): Многошаговый диалог", false, "Email не получен");
                }
            } else {
                recordTest($testStats, "Тест 4.1 (чат $chatId): Многошаговый диалог", false, "Имя не получено");
            }
        } else {
            recordTest($testStats, "Тест 4.1 (чат $chatId): Многошаговый диалог", false, "Тип не выбран");
        }
    } catch (\Exception $e) {
        recordTest($testStats, "Тест 4.1 (чат $chatId): Многошаговый диалог", false, $e->getMessage());
        $logger->error('Тест 4.1 failed', ['error' => $e->getMessage()]);
    }
    
    echo "\n";
}

sendNotification($telegramNotifier, $CHAT_IDS, 
    "✅ <b>Уровень 4 завершен</b>\n\n" .
    "Пройдено: {$testStats['passed']}/{$testStats['total']}"
);

sleep(3);

// ============================================================================
// УРОВЕНЬ 5: ОБРАБОТКА ОШИБОК
// ============================================================================

echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║  УРОВЕНЬ 5: ОБРАБОТКА ОШИБОК                                         ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

sendNotification($telegramNotifier, $CHAT_IDS, 
    "🔧 <b>Уровень 5:</b> Обработка ошибок\n" .
    "Тестирование граничных случаев"
);

foreach ($CHAT_IDS as $chatId) {
    echo "--- ТЕСТИРОВАНИЕ ЧАТА: $chatId ---\n\n";
    
    // Тест 5.1: Пустое сообщение (невозможно через API, тестируем валидацию)
    try {
        // Попытка отправить пустое сообщение
        try {
            $api->sendMessage($chatId, "");
            recordTest($testStats, "Тест 5.1 (чат $chatId): Валидация пустого сообщения", false, "Пустое сообщение не отклонено");
        } catch (\Exception $e) {
            // Ожидаем исключение
            recordTest($testStats, "Тест 5.1 (чат $chatId): Валидация пустого сообщения", true);
            $api->sendMessage($chatId, "✅ Тест 5.1: Валидация работает корректно");
        }
    } catch (\Exception $e) {
        recordTest($testStats, "Тест 5.1 (чат $chatId): Валидация пустого сообщения", false, $e->getMessage());
    }
    
    sleep(2);
    
    // Тест 5.2: Длинное сообщение
    try {
        $longText = str_repeat("Тест длинного сообщения. ", 200); // ~5000 символов
        $api->sendMessage($chatId, "🧪 <b>Тест 5.2:</b> Длинное сообщение\n\n$longText", [
            'parse_mode' => TelegramAPI::PARSE_MODE_HTML
        ]);
        recordTest($testStats, "Тест 5.2 (чат $chatId): Отправка длинного сообщения", true);
    } catch (\Exception $e) {
        recordTest($testStats, "Тест 5.2 (чат $chatId): Отправка длинного сообщения", false, $e->getMessage());
    }
    
    sleep(2);
    
    // Тест 5.3: Неизвестная команда
    try {
        $api->sendMessage($chatId, "🧪 <b>Тест 5.3:</b> Неизвестная команда\n\nОтправьте команду /unknown_command_12345", [
            'parse_mode' => TelegramAPI::PARSE_MODE_HTML
        ]);
        
        $update = waitForUserMessage($polling, $chatId, $TEST_TIMEOUT, function($u) {
            return $u->message->text === '/unknown_command_12345';
        });
        
        if ($update) {
            $api->sendMessage($chatId, "⚠️ Команда не распознана. Используйте /start, /info, /stat, /edit");
            recordTest($testStats, "Тест 5.3 (чат $chatId): Обработка неизвестной команды", true);
        } else {
            recordTest($testStats, "Тест 5.3 (чат $chatId): Обработка неизвестной команды", false, "Команда не получена");
        }
    } catch (\Exception $e) {
        recordTest($testStats, "Тест 5.3 (чат $chatId): Обработка неизвестной команды", false, $e->getMessage());
    }
    
    echo "\n";
}

sendNotification($telegramNotifier, $CHAT_IDS, 
    "✅ <b>Уровень 5 завершен</b>\n\n" .
    "Пройдено: {$testStats['passed']}/{$testStats['total']}"
);

sleep(3);

// ============================================================================
// УРОВЕНЬ 6: КОМПЛЕКСНЫЕ СЦЕНАРИИ
// ============================================================================

echo "╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║  УРОВЕНЬ 6: КОМПЛЕКСНЫЕ ИНТЕГРАЦИОННЫЕ СЦЕНАРИИ                      ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

sendNotification($telegramNotifier, $CHAT_IDS, 
    "🎯 <b>Уровень 6:</b> Комплексные сценарии\n" .
    "Проверка интеграции всех компонентов"
);

foreach ($CHAT_IDS as $chatId) {
    echo "--- ТЕСТИРОВАНИЕ ЧАТА: $chatId ---\n\n";
    
    // Тест 6.1: Проверка статистики диалогов
    try {
        $stats = $conversationManager->getStatistics();
        $api->sendMessage($chatId, 
            "🧪 <b>Тест 6.1:</b> Статистика диалогов\n\n" .
            "Всего активных диалогов: {$stats['total']}\n" .
            "Уникальных пользователей: {$stats['unique_users']}", [
            'parse_mode' => TelegramAPI::PARSE_MODE_HTML
        ]);
        recordTest($testStats, "Тест 6.1 (чат $chatId): Статистика диалогов", true);
    } catch (\Exception $e) {
        recordTest($testStats, "Тест 6.1 (чат $chatId): Статистика диалогов", false, $e->getMessage());
    }
    
    sleep(2);
    
    // Тест 6.2: Комплексное меню
    try {
        $keyboard = InlineKeyboardBuilder::make()
            ->addCallbackButton('📊 Статистика', 'menu_stats')
            ->addCallbackButton('ℹ️ Информация', 'menu_info')
            ->row()
            ->addCallbackButton('⚙️ Настройки', 'menu_settings')
            ->addCallbackButton('❓ Помощь', 'menu_help')
            ->row()
            ->addUrlButton('🌐 Документация', 'https://github.com')
            ->build();
        
        $api->sendMessage($chatId, "🧪 <b>Тест 6.2:</b> Комплексное меню\n\nВыберите раздел:", [
            'parse_mode' => TelegramAPI::PARSE_MODE_HTML,
            'reply_markup' => $keyboard
        ]);
        
        $update = waitForCallback($polling, $chatId, $TEST_TIMEOUT);
        
        if ($update && $update->callbackQuery) {
            $api->answerCallbackQuery($update->callbackQuery->id, [
                'text' => 'Раздел выбран: ' . $update->callbackQuery->data
            ]);
            recordTest($testStats, "Тест 6.2 (чат $chatId): Комплексное меню", true);
        } else {
            recordTest($testStats, "Тест 6.2 (чат $chatId): Комплексное меню", false, "Кнопка не нажата");
        }
    } catch (\Exception $e) {
        recordTest($testStats, "Тест 6.2 (чат $chatId): Комплексное меню", false, $e->getMessage());
    }
    
    echo "\n";
}

sendNotification($telegramNotifier, $CHAT_IDS, 
    "✅ <b>Уровень 6 завершен</b>\n\n" .
    "Пройдено: {$testStats['passed']}/{$testStats['total']}"
);

// ============================================================================
// ИТОГОВАЯ СТАТИСТИКА
// ============================================================================

echo "\n╔══════════════════════════════════════════════════════════════════════╗\n";
echo "║  ИТОГОВАЯ СТАТИСТИКА ТЕСТИРОВАНИЯ                                    ║\n";
echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";

echo "Всего тестов: {$testStats['total']}\n";
echo "Пройдено: {$testStats['passed']}\n";
echo "Провалено: {$testStats['failed']}\n";
echo "Процент успеха: " . round(($testStats['passed'] / $testStats['total']) * 100, 2) . "%\n\n";

if (!empty($testStats['errors'])) {
    echo "Ошибки:\n";
    foreach ($testStats['errors'] as $error) {
        echo "  - $error\n";
    }
    echo "\n";
}

$logger->info('Тестирование завершено', [
    'total' => $testStats['total'],
    'passed' => $testStats['passed'],
    'failed' => $testStats['failed'],
]);

sendNotification($telegramNotifier, $CHAT_IDS, 
    "🏁 <b>ТЕСТИРОВАНИЕ ЗАВЕРШЕНО</b>\n\n" .
    "Всего тестов: {$testStats['total']}\n" .
    "✅ Пройдено: {$testStats['passed']}\n" .
    "❌ Провалено: {$testStats['failed']}\n" .
    "📊 Процент успеха: " . round(($testStats['passed'] / $testStats['total']) * 100, 2) . "%"
);

// ============================================================================
// СОЗДАНИЕ ДАМПОВ MySQL
// ============================================================================

echo "📦 Создание дампов MySQL...\n";

try {
    $tables = ['telegram_bot_users', 'telegram_bot_conversations'];
    
    foreach ($tables as $table) {
        $dumpFile = "/home/engine/project/mysql/{$table}_dump.sql";
        
        // Проверяем существование таблицы
        $exists = $db->querySingle("SHOW TABLES LIKE '$table'");
        
        if ($exists) {
            exec("mysqldump -u root utilities_db $table > $dumpFile 2>&1", $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($dumpFile)) {
                echo "  ✅ Дамп таблицы $table создан\n";
                $logger->info("Дамп таблицы $table создан", ['file' => $dumpFile]);
            } else {
                echo "  ⚠️  Не удалось создать дамп таблицы $table\n";
                $logger->warning("Не удалось создать дамп таблицы $table");
            }
        } else {
            echo "  ℹ️  Таблица $table не существует\n";
        }
    }
} catch (\Exception $e) {
    echo "  ❌ Ошибка создания дампов: {$e->getMessage()}\n";
    $logger->error('Ошибка создания дампов', ['error' => $e->getMessage()]);
}

echo "\n✅ ТЕСТИРОВАНИЕ ЗАВЕРШЕНО!\n";

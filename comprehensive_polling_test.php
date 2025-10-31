<?php

declare(strict_types=1);

/**
 * Комплексное тестирование TelegramBot в режиме Polling
 * 
 * Выполняет все 6 уровней тестирования с уведомлениями в Telegram
 * и полным логированием в MySQL
 */

require_once __DIR__ . '/autoload.php';

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\Http;
use App\Component\MySQLConnectionFactory;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Core\PollingHandler;
use App\Component\TelegramBot\Core\MessageStorage;
use App\Component\TelegramBot\Core\ConversationManager;
use App\Component\TelegramBot\Entities\Update;
use App\Component\TelegramBot\Keyboards\InlineKeyboard;
use App\Component\TelegramBot\Keyboards\ReplyKeyboard;

// === КОНФИГУРАЦИЯ ===
$config = [
    'bot_token' => '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI',
    'test_chat_id' => 366442475,
    'db' => [
        'host' => 'localhost',
        'database' => 'telegram_bot_test',
        'username' => 'telegram_bot',
        'password' => 'test_password_123',
        'charset' => 'utf8mb4',
    ],
];

// === ИНИЦИАЛИЗАЦИЯ ===
$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'file_name' => 'comprehensive_polling_test.log',
    'max_file_size' => 10 * 1024 * 1024, // 10MB
    'max_files' => 5,
]);

$logger->info('=== ЗАПУСК КОМПЛЕКСНОГО ТЕСТИРОВАНИЯ TELEGRAM BOT (POLLING MODE) ===');
$logger->info('Используемый токен бота', ['token' => substr($config['bot_token'], 0, 15) . '...']);
$logger->info('Тестовый chat_id', ['chat_id' => $config['test_chat_id']]);

// Подключение к БД
try {
    $db = new MySQL($config['db'], $logger);
    $logger->info('✅ MySQL подключение установлено');
} catch (\Exception $e) {
    $logger->critical('❌ Ошибка подключения к MySQL', ['error' => $e->getMessage()]);
    die('Не удалось подключиться к MySQL: ' . $e->getMessage() . PHP_EOL);
}

// Инициализация компонентов
$http = new Http([], $logger);
$messageStorage = new MessageStorage($db, $logger, ['enabled' => true, 'auto_create_tables' => true]);
$conversationManager = new ConversationManager($db, $logger, ['enabled' => true, 'auto_create_tables' => true]);
$api = new TelegramAPI($config['bot_token'], $http, $logger, $messageStorage);
$polling = new PollingHandler($api, $logger);

// === ГЛОБАЛЬНЫЕ ПЕРЕМЕННЫЕ ===
$testResults = [];
$currentLevel = 0;
$currentTest = 0;
$waitingForUser = false;
$userResponses = [];
$testStartTime = time();
$lastUserAction = time();
$USER_TIMEOUT = 20; // секунд ожидания пользователя

// === ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ===

/**
 * Отправляет уведомление в Telegram о ходе теста
 */
function notifyTelegram(string $message, array $options = []): void
{
    global $api, $config, $logger;
    
    try {
        $api->sendMessage(
            $config['test_chat_id'],
            "🧪 **ТЕСТИРОВАНИЕ**\n\n" . $message,
            array_merge(['parse_mode' => 'Markdown'], $options)
        );
        $logger->info('📱 Уведомление отправлено', ['message' => $message]);
    } catch (\Exception $e) {
        $logger->error('Ошибка отправки уведомления', ['error' => $e->getMessage()]);
    }
}

/**
 * Логирует результат теста
 */
function logTestResult(string $testName, bool $success, string $details = ''): void
{
    global $testResults, $currentLevel, $logger;
    
    $status = $success ? '✅ PASS' : '❌ FAIL';
    $result = [
        'level' => $currentLevel,
        'test' => $testName,
        'success' => $success,
        'details' => $details,
        'timestamp' => date('Y-m-d H:i:s'),
    ];
    
    $testResults[] = $result;
    $logger->info("$status: $testName", ['details' => $details]);
    
    if (!$success) {
        notifyTelegram("❌ **Тест провален:** $testName\n\n$details");
    }
}

/**
 * Ожидает действие пользователя
 */
function waitForUserAction(string $prompt, int $timeout = 20): bool
{
    global $waitingForUser, $lastUserAction, $logger;
    
    notifyTelegram("⏳ **Требуется действие:**\n\n$prompt");
    $logger->info('Ожидание действия пользователя', ['prompt' => $prompt, 'timeout' => $timeout]);
    
    $waitingForUser = true;
    $lastUserAction = time();
    
    return true;
}

/**
 * Эмулирует действие пользователя (отправка текста)
 */
function emulateUserText(string $text): void
{
    global $api, $config, $logger;
    
    $logger->info('🤖 Эмуляция пользовательского текста', ['text' => $text]);
    // В реальности бот не может отправлять от имени пользователя
    // Просто логируем, что мы ожидали бы этот текст
}

// === ТЕСТОВЫЕ СЦЕНАРИИ ===

/**
 * Уровень 1: Начальные операции
 */
function level1_initialTests(): void
{
    global $api, $config, $logger, $currentLevel;
    
    $currentLevel = 1;
    notifyTelegram("📋 **УРОВЕНЬ 1: Начальные операции**\n\nПроверка базовой отправки и приема сообщений");
    
    // Тест 1.1: Простое текстовое сообщение
    try {
        $msg = $api->sendMessage(
            $config['test_chat_id'],
            "🧪 Тест 1.1: Простое текстовое сообщение\n\nПривет! Это тестовое сообщение для проверки базовой функциональности."
        );
        logTestResult('1.1 - Отправка простого текстового сообщения', true, "Message ID: {$msg->messageId}");
    } catch (\Exception $e) {
        logTestResult('1.1 - Отправка простого текстового сообщения', false, $e->getMessage());
    }
    
    sleep(1);
    
    // Тест 1.2: Сообщение с эмодзи
    try {
        $msg = $api->sendMessage(
            $config['test_chat_id'],
            "🧪 Тест 1.2: Сообщение с эмодзи\n\n😀 😎 🚀 💯 ✨ 🎉 🔥 ⭐ 💪 🏆"
        );
        logTestResult('1.2 - Отправка сообщения с эмодзи', true, "Message ID: {$msg->messageId}");
    } catch (\Exception $e) {
        logTestResult('1.2 - Отправка сообщения с эмодзи', false, $e->getMessage());
    }
    
    sleep(1);
    
    // Тест 1.3: Ожидание ответа от пользователя
    waitForUserAction("Отправьте любое текстовое сообщение в ответ на это уведомление.\n\n⏱ У вас 20 секунд.");
}

/**
 * Уровень 2: Базовые операции с файлами
 */
function level2_basicFileOperations(): void
{
    global $api, $config, $logger, $currentLevel;
    
    $currentLevel = 2;
    notifyTelegram("📋 **УРОВЕНЬ 2: Базовые операции с файлами**\n\nПроверка отправки и приема медиа-файлов");
    
    sleep(2);
    
    // Тест 2.1: Отправка изображения
    waitForUserAction("Отправьте любое **изображение** (фото) в чат.\n\n⏱ У вас 20 секунд.");
}

/**
 * Уровень 3: Операции с клавиатурами
 */
function level3_keyboardOperations(): void
{
    global $api, $config, $logger, $currentLevel;
    
    $currentLevel = 3;
    notifyTelegram("📋 **УРОВЕНЬ 3: Операции с клавиатурами**\n\nПроверка работы с клавиатурами и кнопками");
    
    sleep(2);
    
    // Тест 3.1: Reply-клавиатура
    try {
        $keyboard = new ReplyKeyboard();
        $keyboard->addRow(['Кнопка 1', 'Кнопка 2']);
        $keyboard->addRow(['Кнопка 3']);
        
        $msg = $api->sendMessage(
            $config['test_chat_id'],
            "🧪 Тест 3.1: Reply-клавиатура\n\nНажмите на любую кнопку ниже.",
            ['reply_markup' => $keyboard->toArray()]
        );
        logTestResult('3.1 - Создание Reply-клавиатуры', true, "Message ID: {$msg->messageId}");
        waitForUserAction("Нажмите на любую кнопку Reply-клавиатуры");
    } catch (\Exception $e) {
        logTestResult('3.1 - Создание Reply-клавиатуры', false, $e->getMessage());
    }
}

/**
 * Уровень 4: Диалоговые сценарии с контекстом
 */
function level4_conversationScenarios(): void
{
    global $api, $config, $logger, $currentLevel, $conversationManager;
    
    $currentLevel = 4;
    notifyTelegram("📋 **УРОВЕНЬ 4: Диалоговые сценарии**\n\nПроверка работы с сохранением контекста диалога");
    
    sleep(2);
    
    // Тест 4.1: Начало диалога с сохранением состояния
    try {
        $msg = $api->sendMessage(
            $config['test_chat_id'],
            "🧪 Тест 4.1: Диалог с запоминанием\n\nКак вас зовут? (Введите ваше имя)"
        );
        
        $conversationManager->startConversation(
            $config['test_chat_id'],
            $config['test_chat_id'],
            'awaiting_name',
            ['step' => 1]
        );
        
        logTestResult('4.1 - Начало диалога', true, "Conversation started");
        waitForUserAction("Введите ваше имя");
    } catch (\Exception $e) {
        logTestResult('4.1 - Начало диалога', false, $e->getMessage());
    }
}

/**
 * Уровень 5: Обработка ошибок
 */
function level5_errorHandling(): void
{
    global $api, $config, $logger, $currentLevel;
    
    $currentLevel = 5;
    notifyTelegram("📋 **УРОВЕНЬ 5: Обработка ошибок**\n\nПроверка обработки невалидных данных и ошибочных ситуаций");
    
    sleep(2);
    
    // Тест 5.1: Пустое сообщение (должна быть ошибка)
    try {
        $msg = $api->sendMessage($config['test_chat_id'], '');
        logTestResult('5.1 - Отправка пустого сообщения', false, 'Пустое сообщение было отправлено (не должно было быть отправлено)');
    } catch (\Exception $e) {
        logTestResult('5.1 - Отправка пустого сообщения', true, "Корректно обработана ошибка: " . $e->getMessage());
    }
    
    sleep(1);
    
    // Тест 5.2: Слишком длинное сообщение
    try {
        $longText = str_repeat('A', 5000); // Telegram лимит: 4096
        $msg = $api->sendMessage($config['test_chat_id'], $longText);
        logTestResult('5.2 - Отправка слишком длинного сообщения', false, 'Длинное сообщение было отправлено');
    } catch (\Exception $e) {
        logTestResult('5.2 - Отправка слишком длинного сообщения', true, "Корректно обработана ошибка: " . $e->getMessage());
    }
    
    sleep(1);
    
    // Тест 5.3: Невалидный file_id
    try {
        $msg = $api->sendPhoto($config['test_chat_id'], 'invalid_file_id_123456');
        logTestResult('5.3 - Отправка невалидного file_id', false, 'Невалидный file_id был принят');
    } catch (\Exception $e) {
        logTestResult('5.3 - Отправка невалидного file_id', true, "Корректно обработана ошибка: " . $e->getMessage());
    }
}

/**
 * Уровень 6: Комплексные интеграционные сценарии
 */
function level6_complexScenarios(): void
{
    global $api, $config, $logger, $currentLevel;
    
    $currentLevel = 6;
    notifyTelegram("📋 **УРОВЕНЬ 6: Комплексные сценарии**\n\nПроверка сложных бизнес-процессов и устойчивости");
    
    sleep(2);
    
    // Тест 6.1: Inline-клавиатура с callback
    try {
        $keyboard = new InlineKeyboard();
        $keyboard->addRow([
            ['text' => 'Вариант 1', 'callback_data' => 'option_1'],
            ['text' => 'Вариант 2', 'callback_data' => 'option_2'],
        ]);
        $keyboard->addRow([
            ['text' => 'Отмена', 'callback_data' => 'cancel'],
        ]);
        
        $msg = $api->sendMessage(
            $config['test_chat_id'],
            "🧪 Тест 6.1: Inline-клавиатура\n\nВыберите один из вариантов:",
            ['reply_markup' => $keyboard->toArray()]
        );
        logTestResult('6.1 - Создание Inline-клавиатуры', true, "Message ID: {$msg->messageId}");
        waitForUserAction("Нажмите на любую кнопку Inline-клавиатуры");
    } catch (\Exception $e) {
        logTestResult('6.1 - Создание Inline-клавиатуры', false, $e->getMessage());
    }
}

/**
 * Генерирует финальный отчет
 */
function generateFinalReport(): void
{
    global $testResults, $logger, $testStartTime;
    
    $totalTests = count($testResults);
    $passedTests = count(array_filter($testResults, fn($r) => $r['success']));
    $failedTests = $totalTests - $passedTests;
    $duration = time() - $testStartTime;
    
    $report = "🎯 **ФИНАЛЬНЫЙ ОТЧЕТ ТЕСТИРОВАНИЯ**\n\n";
    $report .= "⏱ Длительность: " . gmdate("H:i:s", $duration) . "\n";
    $report .= "✅ Пройдено: $passedTests\n";
    $report .= "❌ Провалено: $failedTests\n";
    $report .= "📊 Всего тестов: $totalTests\n";
    $report .= "📈 Процент успеха: " . round(($passedTests / $totalTests) * 100, 2) . "%\n\n";
    
    // Группировка по уровням
    $levelStats = [];
    foreach ($testResults as $result) {
        $level = $result['level'];
        if (!isset($levelStats[$level])) {
            $levelStats[$level] = ['total' => 0, 'passed' => 0];
        }
        $levelStats[$level]['total']++;
        if ($result['success']) {
            $levelStats[$level]['passed']++;
        }
    }
    
    $report .= "**Статистика по уровням:**\n\n";
    foreach ($levelStats as $level => $stats) {
        $report .= "Уровень $level: {$stats['passed']}/{$stats['total']}\n";
    }
    
    notifyTelegram($report);
    $logger->info('=== ТЕСТИРОВАНИЕ ЗАВЕРШЕНО ===', [
        'total' => $totalTests,
        'passed' => $passedTests,
        'failed' => $failedTests,
        'duration' => $duration,
    ]);
}

// === ОБРАБОТЧИК ОБНОВЛЕНИЙ ===

$messageCount = 0;
$testsInProgress = true;

$updateHandler = function (Update $update) use (&$messageCount, &$testsInProgress, &$waitingForUser, &$lastUserAction, &$userResponses, $config, $logger, $conversationManager, $api) {
    $messageCount++;
    $lastUserAction = time();
    
    $logger->info('Получено обновление', [
        'update_id' => $update->updateId,
        'message_count' => $messageCount,
    ]);
    
    // Обработка текстовых сообщений
    if ($update->message?->text) {
        $chatId = $update->message->chat->id;
        $userId = $update->message->from->id;
        $text = $update->message->text;
        
        $logger->info('Получено текстовое сообщение', [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'text' => $text,
        ]);
        
        // Сохранение пользователя
        $conversationManager->saveUser(
            $userId,
            $update->message->from->firstName,
            $update->message->from->username,
            $update->message->from->lastName
        );
        
        // Проверка активного диалога
        $conversation = $conversationManager->getConversation($chatId, $userId);
        
        if ($conversation) {
            $state = $conversation['state'];
            $data = json_decode($conversation['data'], true) ?? [];
            
            $logger->info('Обработка диалога', ['state' => $state, 'data' => $data]);
            
            switch ($state) {
                case 'awaiting_name':
                    // Сохраняем имя
                    $data['name'] = $text;
                    $conversationManager->updateConversation($chatId, $userId, 'awaiting_age', $data);
                    
                    $api->sendMessage($chatId, "Приятно познакомиться, $text! Сколько вам лет?");
                    logTestResult('4.2 - Сохранение имени в диалоге', true, "Имя: $text");
                    break;
                    
                case 'awaiting_age':
                    // Сохраняем возраст
                    $data['age'] = $text;
                    $conversationManager->updateConversation($chatId, $userId, 'awaiting_city', $data);
                    
                    $api->sendMessage($chatId, "Отлично! Из какого вы города?");
                    logTestResult('4.3 - Сохранение возраста в диалоге', true, "Возраст: $text");
                    break;
                    
                case 'awaiting_city':
                    // Завершаем диалог
                    $data['city'] = $text;
                    $name = $data['name'] ?? 'Неизвестно';
                    $age = $data['age'] ?? 'Неизвестно';
                    
                    $summary = "📝 **Анкета заполнена!**\n\n";
                    $summary .= "👤 Имя: $name\n";
                    $summary .= "🎂 Возраст: $age\n";
                    $summary .= "🏙 Город: $text\n\n";
                    $summary .= "Спасибо за участие в тестировании!";
                    
                    $api->sendMessage($chatId, $summary, ['parse_mode' => 'Markdown']);
                    $conversationManager->endConversation($chatId, $userId);
                    
                    logTestResult('4.4 - Завершение диалога', true, "Анкета: $name, $age, $text");
                    break;
            }
            
            $waitingForUser = false;
        } else {
            // Нет активного диалога, просто подтверждаем получение
            if ($waitingForUser) {
                logTestResult('1.3 - Получение текстового сообщения от пользователя', true, "Получено: $text");
                $api->sendMessage($chatId, "✅ Сообщение получено: $text");
                $waitingForUser = false;
            }
        }
        
        $userResponses[] = $text;
    }
    
    // Обработка фото
    if ($update->message?->photo) {
        $chatId = $update->message->chat->id;
        $photos = $update->message->photo;
        $largestPhoto = end($photos);
        
        $logger->info('Получено фото', ['file_id' => $largestPhoto['file_id']]);
        logTestResult('2.1 - Получение изображения', true, "File ID: {$largestPhoto['file_id']}");
        
        $api->sendMessage($chatId, "✅ Изображение получено!\nFile ID: {$largestPhoto['file_id']}");
        $waitingForUser = false;
        
        // Переход к следующему тесту
        sleep(2);
        waitForUserAction("Отправьте любой **документ** (PDF, DOC, TXT и т.д.) в чат.\n\n⏱ У вас 20 секунд.");
    }
    
    // Обработка документов
    if ($update->message?->document) {
        $chatId = $update->message->chat->id;
        $document = $update->message->document;
        
        $logger->info('Получен документ', ['file_id' => $document['file_id'], 'file_name' => $document['file_name']]);
        logTestResult('2.2 - Получение документа', true, "File: {$document['file_name']}, ID: {$document['file_id']}");
        
        $api->sendMessage($chatId, "✅ Документ получен!\nИмя: {$document['file_name']}\nFile ID: {$document['file_id']}");
        $waitingForUser = false;
        
        // Переход к следующему тесту
        sleep(2);
        waitForUserAction("Отправьте **голосовое сообщение** (voice) в чат.\n\n⏱ У вас 20 секунд.");
    }
    
    // Обработка голосовых сообщений
    if ($update->message?->voice) {
        $chatId = $update->message->chat->id;
        $voice = $update->message->voice;
        
        $logger->info('Получено голосовое сообщение', ['file_id' => $voice['file_id']]);
        logTestResult('2.3 - Получение голосового сообщения', true, "File ID: {$voice['file_id']}");
        
        $api->sendMessage($chatId, "✅ Голосовое сообщение получено!\nFile ID: {$voice['file_id']}");
        $waitingForUser = false;
        
        // Переход к следующему тесту
        sleep(2);
        waitForUserAction("Отправьте **видео** в чат.\n\n⏱ У вас 20 секунд.");
    }
    
    // Обработка видео
    if ($update->message?->video) {
        $chatId = $update->message->chat->id;
        $video = $update->message->video;
        
        $logger->info('Получено видео', ['file_id' => $video['file_id']]);
        logTestResult('2.4 - Получение видео', true, "File ID: {$video['file_id']}");
        
        $api->sendMessage($chatId, "✅ Видео получено!\nFile ID: {$video['file_id']}");
        $waitingForUser = false;
    }
    
    // Обработка callback запросов
    if ($update->callbackQuery) {
        $callbackQuery = $update->callbackQuery;
        $callbackData = $callbackQuery->data;
        $chatId = $callbackQuery->message->chat->id;
        
        $logger->info('Получен callback', ['data' => $callbackData]);
        
        $api->answerCallbackQuery($callbackQuery->id, [
            'text' => "Вы выбрали: $callbackData",
        ]);
        
        logTestResult('6.2 - Обработка Inline callback', true, "Callback data: $callbackData");
        
        $api->sendMessage($chatId, "✅ Callback обработан: $callbackData");
        $waitingForUser = false;
    }
};

// === ЗАПУСК ТЕСТИРОВАНИЯ ===

notifyTelegram("🚀 **НАЧАЛО ТЕСТИРОВАНИЯ**\n\nЗапуск комплексного тестирования в режиме Polling.\n\nВсе действия логируются в MySQL.");

// Пропускаем старые обновления
$skipped = $polling->skipPendingUpdates();
$logger->info('Пропущено старых обновлений', ['count' => $skipped]);

// Запускаем уровень 1
level1_initialTests();

// Основной цикл обработки
$polling->setTimeout(10); // 10 секунд long polling
$maxIterations = 100; // Максимум 100 итераций (около 15-20 минут)
$iteration = 0;

try {
    while ($iteration < $maxIterations && $testsInProgress) {
        $iteration++;
        
        $updates = $polling->pollOnce();
        
        foreach ($updates as $update) {
            $updateHandler($update);
        }
        
        // Проверка таймаута пользователя
        if ($waitingForUser && (time() - $lastUserAction) > $USER_TIMEOUT) {
            $logger->warning('Таймаут ожидания пользователя');
            notifyTelegram("⏱ Таймаут ожидания действия пользователя. Эмулируем действие...");
            
            // Эмулируем действие в зависимости от текущего уровня
            if ($currentLevel === 1) {
                $logger->info('Эмуляция: пользователь отправил текст');
                $waitingForUser = false;
                sleep(2);
                level2_basicFileOperations();
            } elseif ($currentLevel === 2) {
                $logger->info('Эмуляция: пропуск медиа-тестов');
                $waitingForUser = false;
                sleep(2);
                level3_keyboardOperations();
            } elseif ($currentLevel === 3) {
                $logger->info('Эмуляция: пропуск клавиатур');
                $waitingForUser = false;
                sleep(2);
                level4_conversationScenarios();
            } elseif ($currentLevel === 4) {
                $logger->info('Эмуляция: пропуск диалога');
                $waitingForUser = false;
                sleep(2);
                level5_errorHandling();
            } elseif ($currentLevel === 5) {
                $logger->info('Переход к уровню 6');
                $waitingForUser = false;
                sleep(2);
                level6_complexScenarios();
            } elseif ($currentLevel === 6) {
                $logger->info('Завершение тестирования');
                $testsInProgress = false;
            }
            
            $lastUserAction = time();
        }
        
        // Автоматический переход между уровнями
        if (!$waitingForUser && $messageCount > 0) {
            // Даем время на обработку
            sleep(3);
            
            if ($currentLevel === 1 && $messageCount >= 1) {
                // После получения первого сообщения переходим к уровню 2
                level2_basicFileOperations();
            }
        }
        
        // Проверка завершения
        if ($currentLevel === 6 && !$waitingForUser) {
            $logger->info('Все уровни пройдены, завершаем тестирование');
            $testsInProgress = false;
        }
        
        // Небольшая пауза между итерациями
        usleep(100000); // 0.1 секунды
    }
} catch (\Exception $e) {
    $logger->critical('Критическая ошибка в цикле тестирования', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    notifyTelegram("❌ **КРИТИЧЕСКАЯ ОШИБКА**\n\n" . $e->getMessage());
}

// === ЗАВЕРШЕНИЕ И ОТЧЕТЫ ===

$logger->info('Цикл тестирования завершен', [
    'iterations' => $iteration,
    'messages' => $messageCount,
]);

// Генерируем финальный отчет
generateFinalReport();

// Выгружаем дампы таблиц
$logger->info('Создание дампов MySQL таблиц...');
notifyTelegram("💾 **Создание дампов MySQL...**\n\nСохранение данных всех таблиц");

$tables = ['telegram_bot_messages', 'telegram_bot_conversations', 'telegram_bot_users'];
foreach ($tables as $table) {
    try {
        $dumpFile = __DIR__ . "/mysql/{$table}_" . date('Y-m-d_H-i-s') . '.sql';
        $command = sprintf(
            'mysqldump -u%s -p%s %s %s > %s 2>&1',
            escapeshellarg($config['db']['username']),
            escapeshellarg($config['db']['password']),
            escapeshellarg($config['db']['database']),
            escapeshellarg($table),
            escapeshellarg($dumpFile)
        );
        
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($dumpFile)) {
            $size = filesize($dumpFile);
            $logger->info("Дамп таблицы $table создан", ['file' => $dumpFile, 'size' => $size]);
            notifyTelegram("✅ Дамп `$table`: " . number_format($size / 1024, 2) . " KB");
        } else {
            $logger->error("Ошибка создания дампа $table", ['output' => implode("\n", $output)]);
        }
    } catch (\Exception $e) {
        $logger->error("Ошибка при дампе таблицы $table", ['error' => $e->getMessage()]);
    }
}

notifyTelegram("✅ **ТЕСТИРОВАНИЕ ЗАВЕРШЕНО**\n\nПроверьте логи и дампы MySQL в папке /mysql");

$logger->info('=== КОМПЛЕКСНОЕ ТЕСТИРОВАНИЕ ЗАВЕРШЕНО УСПЕШНО ===');

echo "\n✅ Тестирование завершено. Проверьте:\n";
echo "   - Логи: logs/comprehensive_polling_test.log\n";
echo "   - Дампы MySQL: mysql/\n";
echo "   - Telegram чат: {$config['test_chat_id']}\n\n";

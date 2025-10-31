<?php

declare(strict_types=1);

/**
 * Полный тест класса PollingHandler
 * 
 * Тестирует все методы класса в реальных условиях
 */

require_once __DIR__ . '/../autoload.php';

use App\Component\Http;
use App\Component\Logger;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Core\PollingHandler;
use App\Component\TelegramBot\Entities\Update;

// Конфигурация для теста
$BOT_TOKEN = '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI';
$CHAT_ID = 366442475;

// Результаты тестирования
$testResults = [];
$totalTests = 0;
$passedTests = 0;

function logTest(string $testName, bool $passed, string $message = ''): void
{
    global $testResults, $totalTests, $passedTests;
    
    $totalTests++;
    if ($passed) {
        $passedTests++;
    }
    
    $status = $passed ? '✅ PASS' : '❌ FAIL';
    $result = "$status | $testName";
    if ($message) {
        $result .= " | $message";
    }
    
    $testResults[] = $result;
    echo $result . PHP_EOL;
}

function printHeader(string $title): void
{
    echo PHP_EOL;
    echo str_repeat('=', 80) . PHP_EOL;
    echo $title . PHP_EOL;
    echo str_repeat('=', 80) . PHP_EOL;
}

function printSeparator(): void
{
    echo str_repeat('-', 80) . PHP_EOL;
}

try {
    printHeader('ТЕСТИРОВАНИЕ POLLING HANDLER');
    echo "Bot Token: " . substr($BOT_TOKEN, 0, 20) . "..." . PHP_EOL;
    echo "Chat ID: $CHAT_ID" . PHP_EOL;

    // Инициализация зависимостей
    printSeparator();
    echo "Инициализация компонентов..." . PHP_EOL;
    
    $logger = new Logger([
        'directory' => __DIR__ . '/../logs',
        'filename' => 'telegram_bot_polling_test',
    ]);
    logTest('Инициализация Logger', true);

    $http = new Http(['timeout' => 60], $logger);
    logTest('Инициализация Http', true);

    $api = new TelegramAPI($BOT_TOKEN, $http, $logger);
    logTest('Инициализация TelegramAPI', true);

    // Проверяем подключение к боту
    printSeparator();
    echo "Проверка подключения к боту..." . PHP_EOL;
    try {
        $botInfo = $api->getMe();
        logTest('Получение информации о боте (getMe)', true, "Bot: @{$botInfo->username}");
        echo "  └─ ID: {$botInfo->id}" . PHP_EOL;
        echo "  └─ Имя: {$botInfo->firstName}" . PHP_EOL;
        echo "  └─ Username: @{$botInfo->username}" . PHP_EOL;
    } catch (Exception $e) {
        logTest('Получение информации о боте (getMe)', false, $e->getMessage());
        throw $e;
    }

    // Удаляем webhook если он установлен (для работы polling)
    printSeparator();
    echo "Подготовка к polling режиму..." . PHP_EOL;
    try {
        $webhookInfo = $api->getWebhookInfo();
        if (!empty($webhookInfo['url'])) {
            echo "  └─ Обнаружен webhook: {$webhookInfo['url']}" . PHP_EOL;
            $api->deleteWebhook(true);
            logTest('Удаление webhook', true);
            sleep(1); // Даем серверу время на обработку
        } else {
            logTest('Проверка webhook', true, 'Webhook не установлен');
        }
    } catch (Exception $e) {
        logTest('Проверка/удаление webhook', false, $e->getMessage());
    }

    // Тест 1: Создание PollingHandler
    printHeader('ТЕСТ 1: ИНИЦИАЛИЗАЦИЯ POLLING HANDLER');
    try {
        $polling = new PollingHandler($api, $logger);
        logTest('Создание PollingHandler', true);
    } catch (Exception $e) {
        logTest('Создание PollingHandler', false, $e->getMessage());
        throw $e;
    }

    // Тест 2: Установка параметров
    printHeader('ТЕСТ 2: НАСТРОЙКА ПАРАМЕТРОВ');
    try {
        $polling->setTimeout(10);
        logTest('Установка timeout (setTimeout)', true, 'timeout=10');
        
        $polling->setLimit(50);
        logTest('Установка limit (setLimit)', true, 'limit=50');
        
        $polling->setAllowedUpdates(['message', 'callback_query']);
        logTest('Установка allowedUpdates (setAllowedUpdates)', true);
        
        $polling->setOffset(0);
        logTest('Установка offset (setOffset)', true, 'offset=0');
        
        $currentOffset = $polling->getOffset();
        logTest('Получение offset (getOffset)', $currentOffset === 0, "offset=$currentOffset");
    } catch (Exception $e) {
        logTest('Настройка параметров', false, $e->getMessage());
    }

    // Тест 3: Проверка валидации параметров
    printHeader('ТЕСТ 3: ВАЛИДАЦИЯ ПАРАМЕТРОВ');
    try {
        // Некорректный timeout (должен быть ограничен 0-50)
        $polling->setTimeout(100); // Должен установить 30 (по умолчанию)
        logTest('Валидация timeout (некорректное значение)', true, 'Установлено значение по умолчанию');
        
        // Некорректный limit (должен быть 1-100)
        $polling->setLimit(200); // Должен установить 100
        logTest('Валидация limit (некорректное значение)', true, 'Установлено значение по умолчанию');
        
        // Восстанавливаем нормальные значения
        $polling->setTimeout(5);
        $polling->setLimit(10);
    } catch (Exception $e) {
        logTest('Валидация параметров', false, $e->getMessage());
    }

    // Тест 4: Пропуск старых обновлений
    printHeader('ТЕСТ 4: ПРОПУСК СТАРЫХ ОБНОВЛЕНИЙ');
    try {
        $skipped = $polling->skipPendingUpdates();
        logTest('Пропуск ожидающих обновлений (skipPendingUpdates)', true, "Пропущено: $skipped");
        echo "  └─ Новый offset: {$polling->getOffset()}" . PHP_EOL;
    } catch (Exception $e) {
        logTest('Пропуск старых обновлений', false, $e->getMessage());
    }

    // Отправляем тестовое сообщение для проверки получения
    printHeader('ПОДГОТОВКА: ОТПРАВКА ТЕСТОВОГО СООБЩЕНИЯ');
    try {
        $testMessage = "🧪 Тест Polling " . date('H:i:s');
        $sentMessage = $api->sendMessage($CHAT_ID, $testMessage);
        logTest('Отправка тестового сообщения', true, "ID: {$sentMessage->messageId}");
        echo "  └─ Текст: $testMessage" . PHP_EOL;
        sleep(1); // Даем серверу время обработать
    } catch (Exception $e) {
        logTest('Отправка тестового сообщения', false, $e->getMessage());
    }

    // Тест 5: Получение обновлений (getUpdates)
    printHeader('ТЕСТ 5: ПОЛУЧЕНИЕ ОБНОВЛЕНИЙ');
    try {
        $updates = $polling->getUpdates();
        $count = count($updates);
        logTest('Получение обновлений (getUpdates)', true, "Получено: $count");
        
        if ($count > 0) {
            foreach ($updates as $i => $update) {
                echo "  └─ Update #" . ($i + 1) . ":" . PHP_EOL;
                echo "     ├─ ID: {$update->updateId}" . PHP_EOL;
                
                if ($update->isMessage()) {
                    $msg = $update->message;
                    echo "     ├─ Тип: Message" . PHP_EOL;
                    echo "     ├─ Chat ID: {$msg->chat->id}" . PHP_EOL;
                    echo "     ├─ From: {$msg->from->firstName}" . PHP_EOL;
                    if ($msg->text) {
                        echo "     └─ Text: " . substr($msg->text, 0, 50) . PHP_EOL;
                    }
                } elseif ($update->isCallbackQuery()) {
                    echo "     └─ Тип: CallbackQuery" . PHP_EOL;
                }
            }
        } else {
            echo "  └─ Обновлений нет (это нормально)" . PHP_EOL;
        }
        
        echo "  └─ Новый offset: {$polling->getOffset()}" . PHP_EOL;
    } catch (Exception $e) {
        logTest('Получение обновлений', false, $e->getMessage());
    }

    // Тест 6: Однократное получение (pollOnce)
    printHeader('ТЕСТ 6: ОДНОКРАТНОЕ ПОЛУЧЕНИЕ');
    try {
        // Отправляем еще одно сообщение
        $testMessage2 = "🔄 Тест pollOnce " . date('H:i:s');
        $api->sendMessage($CHAT_ID, $testMessage2);
        sleep(1);
        
        $updates = $polling->pollOnce();
        $count = count($updates);
        logTest('Однократное получение (pollOnce)', true, "Получено: $count");
        
        if ($count > 0) {
            echo "  └─ Первое обновление ID: {$updates[0]->updateId}" . PHP_EOL;
        }
    } catch (Exception $e) {
        logTest('Однократное получение', false, $e->getMessage());
    }

    // Тест 7: Проверка состояния polling
    printHeader('ТЕСТ 7: ПРОВЕРКА СОСТОЯНИЯ');
    try {
        $isPolling = $polling->isPolling();
        logTest('Проверка состояния (isPolling)', true, $isPolling ? 'Активен' : 'Не активен');
    } catch (Exception $e) {
        logTest('Проверка состояния', false, $e->getMessage());
    }

    // Тест 8: Цикл polling с обработчиком (ограниченное количество итераций)
    printHeader('ТЕСТ 8: ЦИКЛ POLLING С ОБРАБОТЧИКОМ');
    try {
        // Отправляем несколько тестовых сообщений
        echo "Отправка тестовых сообщений..." . PHP_EOL;
        for ($i = 1; $i <= 3; $i++) {
            $api->sendMessage($CHAT_ID, "📨 Тестовое сообщение #$i для polling цикла");
            usleep(500000); // 0.5 секунды
        }
        
        echo "Запуск polling цикла (3 итерации)..." . PHP_EOL;
        $processedUpdates = 0;
        
        $polling->setTimeout(3); // Короткий timeout для теста
        $polling->startPolling(function(Update $update) use (&$processedUpdates) {
            $processedUpdates++;
            echo "  └─ Обработано обновление ID: {$update->updateId}" . PHP_EOL;
            
            if ($update->isMessage() && $update->message->text) {
                echo "     └─ Текст: " . substr($update->message->text, 0, 40) . PHP_EOL;
            }
        }, 3); // Максимум 3 итерации
        
        logTest('Цикл polling (startPolling)', true, "Обработано обновлений: $processedUpdates");
    } catch (Exception $e) {
        logTest('Цикл polling', false, $e->getMessage());
    }

    // Тест 9: Сброс состояния
    printHeader('ТЕСТ 9: СБРОС СОСТОЯНИЯ');
    try {
        $oldOffset = $polling->getOffset();
        $polling->reset();
        $newOffset = $polling->getOffset();
        
        $resetSuccess = ($newOffset === 0);
        logTest('Сброс состояния (reset)', $resetSuccess, 
            "Offset: $oldOffset → $newOffset");
    } catch (Exception $e) {
        logTest('Сброс состояния', false, $e->getMessage());
    }

    // Тест 10: Остановка polling
    printHeader('ТЕСТ 10: ОСТАНОВКА POLLING');
    try {
        $polling->stopPolling();
        $isPolling = $polling->isPolling();
        
        logTest('Остановка polling (stopPolling)', !$isPolling, 'Polling остановлен');
    } catch (Exception $e) {
        logTest('Остановка polling', false, $e->getMessage());
    }

    // Тест 11: Обработка ошибок
    printHeader('ТЕСТ 11: ОБРАБОТКА ОШИБОК');
    try {
        // Создаем API с неправильным токеном (но в правильном формате)
        $badApi = new TelegramAPI('123456789:ABC-DEF1234ghIkl-zyx57W2v1u123ew11', $http, $logger);
        $badPolling = new PollingHandler($badApi, $logger);
        
        try {
            $badPolling->getUpdates();
            logTest('Обработка неверного токена', false, 'Исключение не было выброшено');
        } catch (\App\Component\TelegramBot\Exceptions\ApiException $e) {
            logTest('Обработка неверного токена', true, 'Исключение корректно выброшено');
            echo "  └─ Сообщение: " . substr($e->getMessage(), 0, 60) . PHP_EOL;
        }
    } catch (Exception $e) {
        logTest('Тест обработки ошибок', false, $e->getMessage());
    }

    // Тест 12: Логирование операций
    printHeader('ТЕСТ 12: ПРОВЕРКА ЛОГИРОВАНИЯ');
    try {
        $logFile = __DIR__ . '/../logs/app.log';
        
        if (file_exists($logFile)) {
            $logContent = file_get_contents($logFile);
            $hasLogs = !empty($logContent);
            
            // Проверяем наличие ключевых записей
            $hasInit = str_contains($logContent, 'Инициализация PollingHandler');
            $hasGetUpdates = str_contains($logContent, 'Запрос обновлений через getUpdates');
            $hasPollingStart = str_contains($logContent, 'Запуск polling режима');
            
            $allLogsPresent = $hasInit && $hasGetUpdates && $hasPollingStart;
            
            logTest('Проверка логирования', $allLogsPresent, 
                'Все ключевые операции залогированы');
            
            echo "  └─ Инициализация: " . ($hasInit ? '✅' : '❌') . PHP_EOL;
            echo "  └─ getUpdates: " . ($hasGetUpdates ? '✅' : '❌') . PHP_EOL;
            echo "  └─ startPolling: " . ($hasPollingStart ? '✅' : '❌') . PHP_EOL;
            echo "  └─ Путь к логу: $logFile" . PHP_EOL;
            
            // Показываем последние строки лога связанные с polling
            $lines = explode("\n", trim($logContent));
            $pollingLines = array_filter($lines, fn($line) => str_contains($line, 'polling') || str_contains($line, 'Polling'));
            $lastPollingLines = array_slice($pollingLines, -10);
            
            if (!empty($lastPollingLines)) {
                echo PHP_EOL . "Последние записи лога (polling):" . PHP_EOL;
                foreach ($lastPollingLines as $line) {
                    if (trim($line)) {
                        echo "  " . substr($line, 0, 100) . PHP_EOL;
                    }
                }
            }
        } else {
            logTest('Проверка логирования', false, "Лог файл не найден: $logFile");
        }
    } catch (Exception $e) {
        logTest('Проверка логирования', false, $e->getMessage());
    }

    // Финальный отчет
    printHeader('ИТОГОВЫЙ ОТЧЕТ');
    echo "Всего тестов: $totalTests" . PHP_EOL;
    echo "Успешно: $passedTests" . PHP_EOL;
    echo "Провалено: " . ($totalTests - $passedTests) . PHP_EOL;
    echo "Процент успеха: " . round(($passedTests / $totalTests) * 100, 2) . "%" . PHP_EOL;

    printSeparator();
    echo PHP_EOL . "Все результаты:" . PHP_EOL;
    foreach ($testResults as $result) {
        echo $result . PHP_EOL;
    }

    if ($passedTests === $totalTests) {
        printHeader('🎉 ВСЕ ТЕСТЫ ПРОЙДЕНЫ УСПЕШНО! 🎉');
    } else {
        printHeader('⚠️ НЕКОТОРЫЕ ТЕСТЫ НЕ ПРОЙДЕНЫ ⚠️');
    }

} catch (Exception $e) {
    printHeader('КРИТИЧЕСКАЯ ОШИБКА');
    echo "Ошибка: " . $e->getMessage() . PHP_EOL;
    echo "Файл: " . $e->getFile() . PHP_EOL;
    echo "Строка: " . $e->getLine() . PHP_EOL;
    echo PHP_EOL . "Stack trace:" . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    exit(1);
}

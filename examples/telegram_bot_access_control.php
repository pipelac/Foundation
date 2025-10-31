<?php

/**
 * Пример использования системы контроля доступа для Telegram бота
 * 
 * Демонстрирует:
 * - Инициализацию AccessControl
 * - Проверку доступа к командам
 * - Использование AccessControlMiddleware
 * - Обработку команд с проверкой прав
 */

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use App\Component\Http;
use App\Component\Logger;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Core\WebhookHandler;
use App\Component\TelegramBot\Core\AccessControl;
use App\Component\TelegramBot\Core\AccessControlMiddleware;
use App\Component\TelegramBot\Handlers\TextHandler;

// Инициализация зависимостей
$logger = new Logger(['directory' => __DIR__ . '/../logs']);
$http = new Http(['timeout' => 30], $logger);

// Загрузка конфигурации бота
$telegramConfig = json_decode(file_get_contents(__DIR__ . '/../config/telegram.json'), true);
$api = new TelegramAPI($telegramConfig['token'], $http, $logger);

// === 1. ИНИЦИАЛИЗАЦИЯ КОНТРОЛЯ ДОСТУПА ===

// Создание системы контроля доступа
$accessControl = new AccessControl(
    __DIR__ . '/../config/telegram_bot_access_control.json',
    $logger
);

// Создание middleware
$accessMiddleware = new AccessControlMiddleware($accessControl, $api, $logger);

echo "Статус контроля доступа: " . ($accessControl->isEnabled() ? "ВКЛЮЧЕН" : "ВЫКЛЮЧЕН") . "\n";

// === 2. ПРОВЕРКА ДОСТУПА ВРУЧНУЮ ===

// Пример проверки доступа для конкретного пользователя
$userId = 366442475; // Admin
$command = '/adduser';

if ($accessControl->checkAccess($userId, $command)) {
    echo "✓ Пользователь {$userId} имеет доступ к команде {$command}\n";
} else {
    echo "✗ Пользователь {$userId} НЕ имеет доступа к команде {$command}\n";
}

// Получение роли пользователя
$role = $accessControl->getUserRole($userId);
echo "Роль пользователя {$userId}: {$role}\n";

// Получение разрешенных команд
$allowedCommands = $accessControl->getAllowedCommands($userId);
echo "Разрешенные команды: " . implode(', ', $allowedCommands) . "\n\n";

// === 3. ИСПОЛЬЗОВАНИЕ С WEBHOOK HANDLER ===

// Получение обновления из webhook
$webhookHandler = new WebhookHandler($logger);

// Проверка, что это webhook запрос
if (!WebhookHandler::isValidWebhookRequest()) {
    echo "Этот скрипт должен быть вызван через webhook от Telegram\n";
    exit;
}

$update = $webhookHandler->getUpdate(false);
$textHandler = new TextHandler($api, $logger);

// === 4. ОБРАБОТКА КОМАНДЫ /START ===

$textHandler->handleCommand($update, 'start', function ($message) use ($api, $accessMiddleware) {
    // Проверка доступа через middleware
    if (!$accessMiddleware->checkAndNotify($message, '/start')) {
        return; // Middleware уже отправил сообщение об отказе
    }
    
    // Выполнение команды
    $api->sendMessage(
        $message->chat->id,
        "Добро пожаловать! Бот успешно запущен."
    );
});

// === 5. ОБРАБОТКА КОМАНДЫ /USERINFO (для админов) ===

$textHandler->handleCommand($update, 'userinfo', function ($message) use ($api, $accessControl, $accessMiddleware) {
    // Проверка доступа
    if (!$accessMiddleware->checkAndNotify($message, '/userinfo')) {
        return;
    }
    
    // Получение информации о пользователе
    $userId = $message->from->id;
    $userInfo = $accessControl->getUserInfo($userId);
    $role = $accessControl->getUserRole($userId);
    $commands = $accessControl->getAllowedCommands($userId);
    
    $response = "📋 Информация о пользователе:\n\n";
    $response .= "ID: {$userId}\n";
    $response .= "Роль: {$role}\n";
    
    if ($userInfo) {
        $response .= "Имя: {$userInfo['first_name']} {$userInfo['last_name']}\n";
        if (!empty($userInfo['email'])) {
            $response .= "Email: {$userInfo['email']}\n";
        }
    }
    
    $response .= "\nДоступные команды:\n" . implode(', ', $commands);
    
    $api->sendMessage($message->chat->id, $response);
});

// === 6. ОБРАБОТКА КОМАНДЫ /ADDUSER (только для админов) ===

$textHandler->handleCommand($update, 'adduser', function ($message) use ($api, $accessMiddleware) {
    // Проверка доступа
    if (!$accessMiddleware->checkAndNotify($message, '/adduser')) {
        return;
    }
    
    // Выполнение команды (только для админов)
    $api->sendMessage(
        $message->chat->id,
        "🔧 Функция добавления пользователя доступна только администраторам."
    );
});

// === 7. ИСПОЛЬЗОВАНИЕ ОБЕРНУТОГО CALLBACK ===

// Альтернативный способ - обернуть callback через middleware
$wrappedCallback = $accessMiddleware->wrapCommandHandler(
    '/stat',
    function ($message) use ($api) {
        $api->sendMessage(
            $message->chat->id,
            "📊 Статистика системы:\n\nЗдесь будет статистика..."
        );
    }
);

$textHandler->handleCommand($update, 'stat', $wrappedCallback);

// === 8. ПРОВЕРКА СТАТУСА ПОЛЬЗОВАТЕЛЯ ===

$textHandler->handleCommand($update, 'mystatus', function ($message) use ($api, $accessControl) {
    $userId = $message->from->id;
    
    $isRegistered = $accessControl->isUserRegistered($userId);
    $role = $accessControl->getUserRole($userId);
    
    $response = "👤 Ваш статус:\n\n";
    $response .= $isRegistered ? "✓ Вы зарегистрированы в системе\n" : "✗ Вы не зарегистрированы\n";
    $response .= "Роль: {$role}\n";
    
    $api->sendMessage($message->chat->id, $response);
});

// === 9. СПИСОК ВСЕХ РОЛЕЙ (для админов) ===

$textHandler->handleCommand($update, 'roles', function ($message) use ($api, $accessControl, $accessMiddleware) {
    if (!$accessMiddleware->checkAndNotify($message, '/roles')) {
        return;
    }
    
    $roles = $accessControl->getAllRoles();
    $response = "📋 Список ролей в системе:\n\n";
    
    foreach ($roles as $roleName) {
        $roleInfo = $accessControl->getRoleInfo($roleName);
        $commandsCount = count($roleInfo['commands'] ?? []);
        $response .= "• {$roleName} ({$commandsCount} команд)\n";
    }
    
    $api->sendMessage($message->chat->id, $response);
});

// Отправка ответа Telegram
$webhookHandler->sendResponse();

echo "\n=== Пример завершен ===\n";
echo "Для активации контроля доступа:\n";
echo "1. Откройте config/telegram_bot_access_control.json\n";
echo "2. Установите 'enabled': true\n";
echo "3. Настройте пользователей в config/telegram_bot_users.json\n";
echo "4. Настройте роли в config/telegram_bot_roles.json\n";

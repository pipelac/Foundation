# Система контроля доступа для TelegramBot

Полнофункциональная система управления правами доступа пользователей к командам Telegram бота на основе ролей.

## Возможности

- ✅ Управление доступом на основе ролей (RBAC)
- ✅ JSON конфигурация пользователей и ролей
- ✅ Возможность включения/выключения без изменения кода
- ✅ Роль по умолчанию для незарегистрированных пользователей
- ✅ Middleware для автоматической проверки доступа
- ✅ Настраиваемое сообщение об отказе в доступе
- ✅ Логирование всех проверок доступа
- ✅ API для программной проверки прав

## Архитектура

### Компоненты

```
src/TelegramBot/Core/
├── AccessControl.php              # Основная логика контроля доступа
└── AccessControlMiddleware.php    # Middleware для handlers

src/TelegramBot/Exceptions/
└── AccessControlException.php     # Исключения системы

config/
├── telegram_bot_access_control.json  # Главная конфигурация
├── telegram_bot_users.json          # База пользователей
└── telegram_bot_roles.json          # Определение ролей
```

## Быстрый старт

### 1. Настройка конфигурации

#### config/telegram_bot_access_control.json
```json
{
    "enabled": true,
    "users_file": "config/telegram_bot_users.json",
    "roles_file": "config/telegram_bot_roles.json",
    "default_role": "default",
    "access_denied_message": "У вас нет доступа к этой команде."
}
```

**Параметры:**
- `enabled` - включить/выключить контроль доступа
- `users_file` - путь к файлу с пользователями
- `roles_file` - путь к файлу с ролями
- `default_role` - роль для незарегистрированных пользователей
- `access_denied_message` - сообщение об отказе

### 2. Настройка пользователей

#### config/telegram_bot_users.json
```json
{
    "default": {
        "first_name": "Гость",
        "last_name": "",
        "email": "",
        "role": "default",
        "mac": ""
    },
    "366442475": {
        "first_name": "Иван",
        "last_name": "Иванов",
        "email": "ivan@example.com",
        "role": "admin",
        "mac": "50:af:73:1b:96:c3"
    }
}
```

**Как узнать chat_id пользователя:**
- Напишите боту @userinfobot
- Или используйте @getidsbot

### 3. Настройка ролей

#### config/telegram_bot_roles.json
```json
{
    "default": {
        "commands": ["/start", "/help"]
    },
    "admin": {
        "commands": [
            "/start", "/help", "/adduser", 
            "/userinfo", "/settings"
        ]
    }
}
```

**Команды можно указывать:**
- С `/` - `/start`
- Без `/` - `start` (автоматически нормализуется)

## Использование

### Базовая инициализация

```php
use App\Component\TelegramBot\Core\AccessControl;
use App\Component\TelegramBot\Core\AccessControlMiddleware;

// Создание системы контроля доступа
$accessControl = new AccessControl(
    __DIR__ . '/config/telegram_bot_access_control.json',
    $logger
);

// Создание middleware
$accessMiddleware = new AccessControlMiddleware(
    $accessControl,
    $api,
    $logger
);
```

### Проверка статуса

```php
// Проверить, включен ли контроль доступа
if ($accessControl->isEnabled()) {
    echo "Контроль доступа активирован";
}
```

### Проверка доступа вручную

```php
$userId = 366442475;
$command = '/adduser';

// Простая проверка
if ($accessControl->checkAccess($userId, $command)) {
    // Пользователь имеет доступ
    echo "Доступ разрешен";
} else {
    // Доступ запрещен
    echo "Доступ запрещен";
}
```

### Получение информации о пользователе

```php
// Роль пользователя
$role = $accessControl->getUserRole($userId);
echo "Роль: {$role}";

// Полная информация
$userInfo = $accessControl->getUserInfo($userId);
echo "Имя: {$userInfo['first_name']}";

// Разрешенные команды
$commands = $accessControl->getAllowedCommands($userId);
echo "Доступные команды: " . implode(', ', $commands);

// Проверка регистрации
if ($accessControl->isUserRegistered($userId)) {
    echo "Пользователь зарегистрирован";
}
```

### Использование с обработчиками команд

#### Способ 1: Через middleware

```php
use App\Component\TelegramBot\Handlers\TextHandler;

$textHandler = new TextHandler($api, $logger);

$textHandler->handleCommand($update, 'admin', function ($message) use ($api, $accessMiddleware) {
    // Проверка доступа
    if (!$accessMiddleware->checkAndNotify($message, '/admin')) {
        return; // Middleware отправит сообщение об отказе
    }
    
    // Выполнение команды
    $api->sendMessage($message->chat->id, "Панель администратора");
});
```

#### Способ 2: Обернутый callback

```php
// Middleware автоматически проверит доступ
$wrappedCallback = $accessMiddleware->wrapCommandHandler(
    '/settings',
    function ($message) use ($api) {
        $api->sendMessage($message->chat->id, "⚙️ Настройки");
    }
);

$textHandler->handleCommand($update, 'settings', $wrappedCallback);
```

#### Способ 3: Прямая проверка

```php
$textHandler->handleCommand($update, 'userinfo', function ($message) use ($api, $accessControl) {
    $userId = $message->from->id;
    
    if (!$accessControl->checkAccess($userId, '/userinfo')) {
        $api->sendMessage(
            $message->chat->id,
            $accessControl->getAccessDeniedMessage()
        );
        return;
    }
    
    // Выполнение команды
    $userInfo = $accessControl->getUserInfo($userId);
    $api->sendMessage($message->chat->id, json_encode($userInfo));
});
```

## Работа с ролями

### Получение списка ролей

```php
$roles = $accessControl->getAllRoles();
foreach ($roles as $roleName) {
    echo "Роль: {$roleName}\n";
}
```

### Информация о роли

```php
$roleInfo = $accessControl->getRoleInfo('admin');
$commands = $roleInfo['commands'] ?? [];
echo "Команды роли admin: " . implode(', ', $commands);
```

## Перезагрузка конфигурации

```php
// Горячая перезагрузка без перезапуска бота
try {
    $accessControl->reload(__DIR__ . '/config/telegram_bot_access_control.json');
    echo "Конфигурация обновлена";
} catch (AccessControlException $e) {
    echo "Ошибка: " . $e->getMessage();
}
```

## Примеры использования

### Команда для проверки своего статуса

```php
$textHandler->handleCommand($update, 'mystatus', function ($message) use ($api, $accessControl) {
    $userId = $message->from->id;
    $role = $accessControl->getUserRole($userId);
    $commands = $accessControl->getAllowedCommands($userId);
    
    $response = "👤 Ваш статус:\n";
    $response .= "ID: {$userId}\n";
    $response .= "Роль: {$role}\n";
    $response .= "Доступные команды:\n" . implode(', ', $commands);
    
    $api->sendMessage($message->chat->id, $response);
});
```

### Команда для списка ролей (админы)

```php
$textHandler->handleCommand($update, 'roles', function ($message) use ($api, $accessControl, $accessMiddleware) {
    if (!$accessMiddleware->checkAndNotify($message, '/roles')) {
        return;
    }
    
    $roles = $accessControl->getAllRoles();
    $response = "📋 Роли в системе:\n\n";
    
    foreach ($roles as $roleName) {
        $roleInfo = $accessControl->getRoleInfo($roleName);
        $cmdCount = count($roleInfo['commands'] ?? []);
        $response .= "• {$roleName} ({$cmdCount} команд)\n";
    }
    
    $api->sendMessage($message->chat->id, $response);
});
```

## Включение и выключение

### Временное отключение

Отредактируйте `config/telegram_bot_access_control.json`:
```json
{
    "enabled": false
}
```

При `enabled: false`:
- Все проверки доступа возвращают `true`
- Команды доступны всем пользователям
- Логирование не ведется

### Программное отключение

```php
// Создать конфиг с enabled: false
$tempConfig = [
    'enabled' => false,
    'users_file' => 'config/telegram_bot_users.json',
    'roles_file' => 'config/telegram_bot_roles.json',
    'default_role' => 'default',
    'access_denied_message' => 'Доступ запрещен'
];

file_put_contents(
    '/tmp/access_control.json',
    json_encode($tempConfig, JSON_PRETTY_PRINT)
);

$accessControl = new AccessControl('/tmp/access_control.json', $logger);
```

## Логирование

Система автоматически логирует:

```php
// Активация
[INFO] Контроль доступа TelegramBot активирован
       users_count: 3, roles_count: 2

// Деактивация  
[INFO] Контроль доступа TelegramBot деактивирован

// Проверка доступа
[DEBUG] Проверка доступа к команде
        chat_id: 366442475, command: /admin, 
        role: admin, allowed: true

// Отказ в доступе
[WARNING] Доступ к команде запрещен
          user_id: 123456, username: john_doe,
          command: /admin, role: default
```

## Обработка ошибок

```php
use App\Component\TelegramBot\Exceptions\AccessControlException;

try {
    $accessControl = new AccessControl($configPath, $logger);
} catch (AccessControlException $e) {
    // Ошибка загрузки конфигурации
    echo "Ошибка: " . $e->getMessage();
}
```

**Типичные ошибки:**
- Файл конфигурации не найден
- Некорректный JSON
- Отсутствуют обязательные поля
- Файлы пользователей/ролей не найдены

## Миграция с INI на JSON

### Было (INI):
```ini
[366442475]
    first_name = Z
    last_name = B
    role = admin
```

### Стало (JSON):
```json
{
    "366442475": {
        "first_name": "Z",
        "last_name": "B",
        "role": "admin"
    }
}
```

### Скрипт конвертации

```php
function convertIniToJson(string $iniFile): array {
    $data = parse_ini_file($iniFile, true);
    $json = [];
    
    foreach ($data as $key => $values) {
        $json[$key] = $values;
    }
    
    return $json;
}

// Конвертация users.ini
$users = convertIniToJson('users.ini');
file_put_contents(
    'telegram_bot_users.json',
    json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

// Конвертация roles.ini
$roles = convertIniToJson('roles.ini');
foreach ($roles as &$role) {
    if (isset($role['commands'])) {
        // Преобразуем строку команд в массив
        $role['commands'] = array_map(
            'trim',
            explode(',', $role['commands'])
        );
    }
}
file_put_contents(
    'telegram_bot_roles.json',
    json_encode($roles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);
```

## Best Practices

### 1. Роль по умолчанию

Всегда создавайте роль `default` с минимальными правами:
```json
{
    "default": {
        "commands": ["/start", "/help"]
    }
}
```

### 2. Безопасность

- Не храните конфиги в публичных директориях
- Используйте `.gitignore` для файлов с реальными ID
- Регулярно проверяйте логи на подозрительную активность

### 3. Организация команд

Группируйте команды по уровням доступа:
```json
{
    "guest": {
        "commands": ["/start", "/help", "/about"]
    },
    "user": {
        "commands": ["/start", "/help", "/profile", "/settings"]
    },
    "moderator": {
        "commands": ["/start", "/help", "/profile", "/settings", "/users", "/reports"]
    },
    "admin": {
        "commands": ["/start", "/help", "/profile", "/settings", "/users", "/reports", "/system", "/logs"]
    }
}
```

### 4. Тестирование

```php
// Создайте тестовый конфиг для разработки
$testConfig = [
    'enabled' => true,
    'users_file' => 'config/test_users.json',
    'roles_file' => 'config/test_roles.json',
    'default_role' => 'admin', // Все как админы для тестов
    'access_denied_message' => '[TEST] Доступ запрещен'
];
```

## Полный пример

См. `/examples/telegram_bot_access_control.php`

## Требования

- PHP 8.1+
- TelegramBot модуль
- Logger компонент

## См. также

- [TelegramBot README](../src/TelegramBot/README.md)
- [Документация Telegram Bot API](https://core.telegram.org/bots/api)

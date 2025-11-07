# Быстрый старт: Контроль доступа TelegramBot

## 3 шага для активации

### Шаг 1: Включите контроль доступа

Отредактируйте `config/telegram_bot_access_control.json`:

```json
{
    "enabled": true
}
```

### Шаг 2: Добавьте пользователей

Отредактируйте `config/telegram_bot_users.json`, добавьте свой chat_id:

```json
{
    "default": {
        "first_name": "Гость",
        "role": "default"
    },
    "ВАШ_CHAT_ID": {
        "first_name": "Ваше имя",
        "role": "admin"
    }
}
```

**Как узнать свой chat_id:**
1. Напишите боту @userinfobot в Telegram
2. Он ответит вашим ID

### Шаг 3: Настройте роли

Отредактируйте `config/telegram_bot_roles.json`:

```json
{
    "default": {
        "commands": ["/start", "/help"]
    },
    "admin": {
        "commands": [
            "/start", "/help", "/admin",
            "/settings", "/users"
        ]
    }
}
```

## Использование в коде

### Инициализация

```php
use App\Component\TelegramBot\Core\AccessControl;
use App\Component\TelegramBot\Core\AccessControlMiddleware;

$accessControl = new AccessControl(
    __DIR__ . '/config/telegram_bot_access_control.json',
    $logger
);

$middleware = new AccessControlMiddleware($accessControl, $api, $logger);
```

### Защита команды

```php
$textHandler->handleCommand($update, 'admin', function($message) use ($api, $middleware) {
    // Проверка доступа
    if (!$middleware->checkAndNotify($message, '/admin')) {
        return; // Автоматически отправит сообщение об отказе
    }
    
    // Выполнение команды
    $api->sendMessage($message->chat->id, "Админ-панель");
});
```

## Проверка работы

### 1. Отправьте боту команду

Отправьте `/admin` вашему боту

### 2. Результат для админа

```
Админ-панель
```

### 3. Результат для обычного пользователя

```
У вас нет доступа к этой команде.
```

## Временное отключение

Чтобы временно отключить контроль доступа:

```json
{
    "enabled": false
}
```

При `enabled: false` все команды доступны всем пользователям.

## Полная документация

См. `/docs/TELEGRAM_BOT_ACCESS_CONTROL.md`

## Примеры кода

См. `/examples/telegram_bot_access_control.php`

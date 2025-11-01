# Система проверки подписки на каналы для Telegram Bot

## Обзор

Система проверки подписки на каналы позволяет ограничить доступ к командам бота только для пользователей, подписанных на указанные Telegram каналы.

## Возможности

- ✅ Проверка подписки пользователя на Telegram каналы через API метод `getChatMember`
- ✅ Три режима проверки:
  - **ALL** - пользователь должен быть подписан на ВСЕ каналы из списка
  - **ANY** - пользователь должен быть подписан хотя бы на ОДИН канал из списка
  - **EXACT** - проверка подписки на конкретный канал
- ✅ Кеширование результатов проверки (настраиваемое время жизни кеша)
- ✅ Интеграция с существующей системой контроля доступа (AccessControl)
- ✅ Детальная информация о подписках пользователя
- ✅ Настраиваемые сообщения об отказе в доступе

## Новые компоненты

### 1. ChatMember Entity

**Файл:** `src/TelegramBot/Entities/ChatMember.php`

Представляет информацию о статусе пользователя в чате/канале.

**Статусы:**
- `creator` - создатель канала/группы
- `administrator` - администратор
- `member` - участник
- `restricted` - ограничен
- `left` - покинул чат
- `kicked` - заблокирован

**Основные методы:**
```php
$chatMember->isSubscribed();  // true если creator/administrator/member
$chatMember->isAdmin();       // true если creator/administrator
$chatMember->hasLeft();       // true если left
$chatMember->isKicked();      // true если kicked
```

### 2. TelegramAPI::getChatMember()

**Файл:** `src/TelegramBot/Core/TelegramAPI.php`

Метод для получения информации о членстве пользователя в канале/группе.

**Использование:**
```php
$chatMember = $api->getChatMember('@channel_username', $userId);

echo "Статус: {$chatMember->status}\n";
echo "Подписан: " . ($chatMember->isSubscribed() ? 'Да' : 'Нет') . "\n";
```

**Требования:**
- Бот должен быть добавлен администратором в канал
- Канал должен быть публичным или бот должен иметь доступ

### 3. ChannelSubscriptionChecker

**Файл:** `src/TelegramBot/Core/ChannelSubscriptionChecker.php`

Основной класс для проверки подписки на каналы.

**Конфигурация:**
```php
$config = [
    'enabled' => true,
    'mode' => 'any',  // 'all', 'any', или 'exact'
    'channels' => [
        '@channel1',
        '@channel2',
    ],
    'cache_ttl' => 300,  // 5 минут
    'access_denied_message' => 'Подпишитесь на канал для доступа',
];

$checker = new ChannelSubscriptionChecker($api, $config, $logger);
```

**Основные методы:**
```php
// Проверка подписки
$isSubscribed = $checker->checkSubscription($userId);

// Детальная информация
$details = $checker->getSubscriptionDetails($userId);
// ['@channel1' => true, '@channel2' => false]

// Очистка кеша
$checker->clearCache($userId);

// Получение списка каналов
$channels = $checker->getChannels();

// Форматирование для отображения
$list = $checker->formatChannelsList();
```

### 4. Интеграция с AccessControl

**Файл:** `src/TelegramBot/Core/AccessControl.php`

AccessControl теперь поддерживает проверку подписки на каналы.

**Инициализация:**
```php
// Передайте экземпляр TelegramAPI для активации проверки подписки
$accessControl = new AccessControl(
    'config/telegram_bot_access_control.json',
    $logger,
    $api  // Обязательно для проверки подписки
);
```

**Новые методы:**
```php
// Проверка, включена ли проверка подписки
$accessControl->isSubscriptionCheckEnabled();

// Получение объекта ChannelSubscriptionChecker
$checker = $accessControl->getSubscriptionChecker();

// Детальная информация о подписках
$details = $accessControl->getUserSubscriptionDetails($userId);

// Очистка кеша
$accessControl->clearSubscriptionCache($userId);
```

## Конфигурация

### telegram_bot_access_control.json

Добавлена новая секция `channel_subscription`:

```json
{
    "enabled": true,
    "users_file": "config/telegram_bot_users.json",
    "roles_file": "config/telegram_bot_roles.json",
    "default_role": "default",
    "access_denied_message": "У вас нет доступа к этой команде.",
    "channel_subscription": {
        "enabled": true,
        "mode": "any",
        "channels": [
            "@kompasDaily"
        ],
        "cache_ttl": 300,
        "access_denied_message": "⛔ Для использования бота необходимо подписаться на канал."
    }
}
```

**Параметры channel_subscription:**

| Параметр | Тип | Описание |
|----------|-----|----------|
| `enabled` | boolean | Включить/выключить проверку подписки |
| `mode` | string | Режим: `all`, `any`, `exact` |
| `channels` | array | Список username каналов (с @ или без) |
| `cache_ttl` | int | Время жизни кеша в секундах |
| `access_denied_message` | string | Сообщение при отказе в доступе |

## Режимы проверки

### MODE_ALL - Все каналы

Пользователь должен быть подписан на **ВСЕ** каналы из списка.

```json
{
    "mode": "all",
    "channels": ["@channel1", "@channel2", "@channel3"]
}
```

Доступ будет разрешен только если пользователь подписан на все три канала.

### MODE_ANY - Хотя бы один

Пользователь должен быть подписан хотя бы на **ОДИН** канал из списка.

```json
{
    "mode": "any",
    "channels": ["@channel1", "@channel2", "@channel3"]
}
```

Доступ будет разрешен если пользователь подписан хотя бы на один из каналов.

### MODE_EXACT - Конкретный канал

Проверка подписки на конкретный канал (первый в списке).

```json
{
    "mode": "exact",
    "channels": ["@main_channel"]
}
```

Доступ будет разрешен только если пользователь подписан на указанный канал.

## Примеры использования

### Пример 1: Базовая настройка

```php
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Core\AccessControl;
use App\Component\TelegramBot\Core\AccessControlMiddleware;

// Инициализация
$api = new TelegramAPI($botToken, $http, $logger);
$accessControl = new AccessControl(
    'config/telegram_bot_access_control.json',
    $logger,
    $api  // Важно: передаем API для проверки подписки
);

$middleware = new AccessControlMiddleware($accessControl, $api, $logger);

// Проверка доступа автоматически включает проверку подписки
if (!$middleware->checkAndNotify($message, '/start')) {
    // Пользователь получит сообщение о необходимости подписки
    return;
}

// Команда выполняется
$api->sendMessage($chatId, "Добро пожаловать!");
```

### Пример 2: Ручная проверка подписки

```php
// Получаем checker
$checker = $accessControl->getSubscriptionChecker();

if ($checker !== null && $checker->isEnabled()) {
    // Проверяем подписку
    $isSubscribed = $checker->checkSubscription($userId);
    
    if (!$isSubscribed) {
        // Пользователь не подписан
        $channels = $checker->formatChannelsList();
        $api->sendMessage($chatId, "Подпишитесь на каналы:\n{$channels}");
        return;
    }
}
```

### Пример 3: Детальная информация о подписках

```php
// Получаем детальную информацию
$details = $accessControl->getUserSubscriptionDetails($userId);

if ($details !== null) {
    $response = "Ваши подписки:\n";
    
    foreach ($details as $channel => $subscribed) {
        $icon = $subscribed ? "✓" : "✗";
        $response .= "{$icon} {$channel}\n";
    }
    
    $api->sendMessage($chatId, $response);
}
```

### Пример 4: Команда для проверки статуса

```php
$textHandler->handleCommand($update, 'status', function($message) use ($api, $accessControl) {
    $userId = $message->from->id;
    
    if (!$accessControl->isSubscriptionCheckEnabled()) {
        $api->sendMessage($message->chat->id, "Проверка подписки отключена");
        return;
    }
    
    $checker = $accessControl->getSubscriptionChecker();
    $details = $checker->getSubscriptionDetails($userId);
    
    $response = "📊 Ваш статус подписки:\n\n";
    
    foreach ($details as $channel => $subscribed) {
        $status = $subscribed ? "✅ Подписан" : "❌ Не подписан";
        $response .= "{$channel}: {$status}\n";
    }
    
    $mode = $checker->getMode();
    $response .= "\n📌 Режим проверки: {$mode}";
    
    $api->sendMessage($message->chat->id, $response);
});
```

## Работа с кешем

Система автоматически кеширует результаты проверки для снижения нагрузки на Telegram API.

### Настройка времени жизни кеша

```json
{
    "cache_ttl": 300  // 5 минут в секундах
}
```

### Ручная очистка кеша

```php
// Очистить кеш для конкретного пользователя
$accessControl->clearSubscriptionCache($userId);

// Очистить весь кеш
$accessControl->clearSubscriptionCache(null);
```

### Когда очищать кеш

- После подписки/отписки пользователя от канала
- При изменении конфигурации каналов
- При обновлении прав пользователя

## Обработка ошибок

### Ошибка: Бот не является администратором канала

```
API Error: Bad Request: CHAT_ADMIN_REQUIRED
```

**Решение:** Добавьте бота администратором в канал.

### Ошибка: Канал не найден

```
API Error: Bad Request: CHAT_NOT_FOUND
```

**Решение:** Проверьте правильность username канала (с @ в начале).

### Ошибка: API недоступен в AccessControl

```
AccessControlException: Для проверки подписки на каналы необходимо передать экземпляр TelegramAPI в AccessControl.
```

**Решение:** Передайте экземпляр TelegramAPI при создании AccessControl:
```php
$accessControl = new AccessControl($configPath, $logger, $api);
```

## Взаимодействие с ролями

Проверка подписки **приоритетнее** проверки ролей:

1. Сначала проверяется подписка на каналы
2. Если подписка не прошла - отказ в доступе
3. Если подписка прошла - проверяются роли

Это позволяет комбинировать оба механизма:
- Подписка на каналы для всех пользователей
- Роли для дополнительного разграничения доступа

## Рекомендации

### Безопасность

1. **Добавляйте бота администратором** только в те каналы, где это необходимо
2. **Используйте приватные каналы** для более строгого контроля
3. **Настройте cache_ttl** в зависимости от требований к актуальности данных

### Производительность

1. **Увеличьте cache_ttl** для снижения нагрузки на API (рекомендуется 300-600 секунд)
2. **Используйте режим ANY** если достаточно подписки на один из каналов
3. **Очищайте кеш выборочно** (только для конкретных пользователей)

### Пользовательский опыт

1. **Указывайте понятные сообщения** в access_denied_message
2. **Показывайте список каналов** в сообщении об отказе
3. **Добавьте команду для проверки статуса** подписки

## Тестирование

Для тестирования функционала используйте режим Polling:

```bash
php examples/telegram_bot_polling_example.php
```

Или создайте собственный тестовый скрипт:

```php
$polling->startPolling(function($update) use ($api, $accessMiddleware) {
    if ($update->isMessage() && $update->message->text === '/start') {
        if (!$accessMiddleware->checkAndNotify($update->message, '/start')) {
            return;
        }
        
        $api->sendMessage(
            $update->message->chat->id,
            "Вы успешно подписаны на канал!"
        );
    }
});
```

## Поддержка

Для вопросов и предложений используйте:
- GitHub Issues
- Документацию Telegram Bot API: https://core.telegram.org/bots/api

---

**Версия:** 1.0.0  
**Дата:** 2024  
**Автор:** PHP Telegram Bot Toolkit

# Новые параметры ролей в системе контроля доступа TelegramBot

## Обзор

В систему контроля доступа добавлены два новых параметра для ролей:

1. **reconstructionModeIgnore** - управление доступом в режиме профилактики
2. **disable_sound_notification** - управление беззвучными уведомлениями по времени

## Параметры

### 1. reconstructionModeIgnore

Определяет, может ли роль работать в режиме профилактических работ.

**Значения:**
- `"yes"` - роль работает даже в режиме профилактики
- `"no"` - роль блокируется, бот отвечает сообщением о профилактике

**Пример в roles.json:**
```json
{
    "admin": {
        "commands": ["/start", "/admin"],
        "reconstructionModeIgnore": "yes"
    },
    "default": {
        "commands": ["/start"],
        "reconstructionModeIgnore": "no"
    }
}
```

**Использование в коде:**

```php
$userId = 366442475;

// Проверка доступа в режиме профилактики
if ($accessControl->canIgnoreReconstructionMode($userId)) {
    // Пользователь может работать
    executeCommand();
} else {
    // Отправить сообщение о профилактике
    $api->sendMessage($chatId, "⚠️ Ведутся профилактические работы. Повторите попытку позже.");
}

// Получить значение параметра
$mode = $accessControl->getReconstructionModeIgnore($userId); // "yes" или "no"
```

### 2. disable_sound_notification

Определяет временной диапазон, когда сообщения отправляются беззвучно.

**Значения:**
- `"HH:MM-HH:MM"` - диапазон времени (например, `"22:00-09:00"`)
- `null` - звуковые уведомления всегда включены

**Особенности:**
- Поддерживает диапазоны через полночь (22:00-09:00)
- Автоматически определяет текущее время
- Можно проверить для конкретного времени

**Пример в roles.json:**
```json
{
    "admin": {
        "commands": ["/start", "/admin"],
        "reconstructionModeIgnore": "yes",
        "disable_sound_notification": "22:00-09:00"
    },
    "L2": {
        "commands": ["/start", "/support"],
        "reconstructionModeIgnore": "yes",
        "disable_sound_notification": "23:00-08:00"
    },
    "default": {
        "commands": ["/start"],
        "reconstructionModeIgnore": "no",
        "disable_sound_notification": null
    }
}
```

**Использование в коде:**

```php
$userId = 366442475;

// Проверка для текущего времени
if ($accessControl->shouldDisableSoundNotification($userId)) {
    // Отправить беззвучно
    $api->sendMessage($chatId, $text, ['disable_notification' => true]);
} else {
    // Отправить со звуком
    $api->sendMessage($chatId, $text);
}

// Проверка для конкретного времени
$time = new \DateTime('2024-01-01 23:30:00');
if ($accessControl->shouldDisableSoundNotification($userId, $time)) {
    echo "В 23:30 нужно отправлять беззвучно";
}

// Получить диапазон
$range = $accessControl->getDisableSoundNotification($userId); // "22:00-09:00" или null
```

## Примеры использования

### Проверка в режиме профилактики

```php
// Глобальный флаг режима профилактики
$isMaintenanceMode = true;

$textHandler->handleCommand($update, 'admin', function($message) use ($api, $accessControl, $isMaintenanceMode) {
    $userId = $message->from->id;
    
    // Если режим профилактики и пользователь не может его игнорировать
    if ($isMaintenanceMode && !$accessControl->canIgnoreReconstructionMode($userId)) {
        $api->sendMessage(
            $message->chat->id,
            "⚠️ Ведутся профилактические работы.\nПожалуйста, повторите попытку позже."
        );
        return;
    }
    
    // Выполнение команды
    $api->sendMessage($message->chat->id, "Админ-панель");
});
```

### Автоматические беззвучные уведомления

```php
function sendNotification($api, $accessControl, $userId, $text) {
    $chatId = $userId; // или получить из БД
    
    // Автоматически определить, нужно ли отключить звук
    $options = [
        'disable_notification' => $accessControl->shouldDisableSoundNotification($userId)
    ];
    
    $api->sendMessage($chatId, $text, $options);
}

// Использование
sendNotification($api, $accessControl, 366442475, "Новое уведомление!");
```

### Информация о настройках пользователя

```php
$textHandler->handleCommand($update, 'settings', function($message) use ($api, $accessControl) {
    $userId = $message->from->id;
    
    $role = $accessControl->getUserRole($userId);
    $canWorkInMaintenance = $accessControl->canIgnoreReconstructionMode($userId);
    $quietHours = $accessControl->getDisableSoundNotification($userId);
    
    $response = "⚙️ Ваши настройки:\n\n";
    $response .= "Роль: {$role}\n";
    $response .= "Работа в профилактику: " . ($canWorkInMaintenance ? "✓" : "✗") . "\n";
    
    if ($quietHours) {
        $response .= "Тихие часы: {$quietHours}\n";
    } else {
        $response .= "Тихие часы: не установлены\n";
    }
    
    $api->sendMessage($message->chat->id, $response);
});
```

## Тестирование

### Unit тесты

Добавлено 8 новых тестов:

```bash
./vendor/bin/phpunit tests/Unit/TelegramBotAccessControlTest.php --filter reconstruction
./vendor/bin/phpunit tests/Unit/TelegramBotAccessControlTest.php --filter notification
```

**Тесты покрывают:**
- ✅ Проверку режима профилактики для админов
- ✅ Проверку режима профилактики при отключенном контроле доступа
- ✅ Беззвучные уведомления в диапазоне
- ✅ Беззвучные уведомления для ролей без диапазона
- ✅ Беззвучные уведомления при отключенном контроле
- ✅ Получение параметра reconstructionModeIgnore
- ✅ Получение параметра disable_sound_notification
- ✅ Диапазоны времени через полночь (22:00-09:00)

### Примеры тестирования

```php
// Тест диапазона через полночь
$time = new \DateTime('2024-01-01 23:30:00'); // 23:30 - в диапазоне
$result = $accessControl->shouldDisableSoundNotification($userId, $time);
// $result = true

$time = new \DateTime('2024-01-01 02:00:00'); // 02:00 - в диапазоне
$result = $accessControl->shouldDisableSoundNotification($userId, $time);
// $result = true

$time = new \DateTime('2024-01-01 12:00:00'); // 12:00 - вне диапазона
$result = $accessControl->shouldDisableSoundNotification($userId, $time);
// $result = false
```

## API методы

### Новые публичные методы AccessControl

```php
// Проверка режима профилактики
public function canIgnoreReconstructionMode(int $chatId): bool

// Проверка беззвучных уведомлений
public function shouldDisableSoundNotification(int $chatId, ?\DateTimeInterface $time = null): bool

// Получение параметра reconstructionModeIgnore
public function getReconstructionModeIgnore(int $chatId): string

// Получение параметра disable_sound_notification
public function getDisableSoundNotification(int $chatId): ?string
```

## Миграция

### Обновление существующих roles.json

Добавьте новые параметры к существующим ролям:

```json
{
    "admin": {
        "commands": ["/start", "/admin"],
        "reconstructionModeIgnore": "yes",
        "disable_sound_notification": "22:00-09:00"
    }
}
```

### Значения по умолчанию

Если параметры не указаны:
- `reconstructionModeIgnore` = `"no"`
- `disable_sound_notification` = `null`

## Best Practices

### 1. Режим профилактики

```php
// Всегда проверяйте перед выполнением критичных операций
if ($maintenanceMode && !$accessControl->canIgnoreReconstructionMode($userId)) {
    sendMaintenanceMessage();
    return;
}
```

### 2. Беззвучные уведомления

```php
// Используйте для всех автоматических уведомлений
$options = ['disable_notification' => $accessControl->shouldDisableSoundNotification($userId)];
$api->sendMessage($chatId, $text, $options);
```

### 3. Логирование

```php
$logger->info('Режим профилактики', [
    'user_id' => $userId,
    'can_ignore' => $accessControl->canIgnoreReconstructionMode($userId),
]);
```

## Документация

- **Полная документация:** `/docs/TELEGRAM_BOT_ACCESS_CONTROL.md`
- **Примеры:** `/examples/telegram_bot_access_control.php`
- **Тесты:** `/tests/Unit/TelegramBotAccessControlTest.php`

## Changelog

### Версия 1.1.0

- ✅ Добавлен параметр `reconstructionModeIgnore`
- ✅ Добавлен параметр `disable_sound_notification`
- ✅ Добавлены методы проверки режима профилактики
- ✅ Добавлены методы проверки беззвучных уведомлений
- ✅ Добавлена поддержка временных диапазонов через полночь
- ✅ Добавлено 8 новых unit тестов
- ✅ Обновлена документация и примеры

# Система управления диалогами Telegram бота (ConversationManager)

## Описание

Полнофункциональная система для управления многошаговыми диалогами с пользователями Telegram бота. Позволяет создавать сложные интерактивные сценарии с сохранением состояния между шагами, автоматическим удалением кнопок после использования и хранением данных пользователей.

## Возможности

### ✅ Основной функционал

- **Многошаговые диалоги** с сохранением состояния между шагами
- **Хранение данных пользователей** (id, first_name, username, last_name)
- **Автоматическое создание таблиц** при первом использовании
- **Тайм-ауты диалогов** для автоматического завершения устаревших
- **Удаление сообщений с кнопками** после обработки
- **Сохранение произвольных данных** в JSON формате
- **Статистика активных диалогов** по состояниям
- **Автоматическая очистка** устаревших диалогов
- **Полное логирование** всех операций

## Структура таблиц

### Таблица: `telegram_bot_users`

Хранит данные пользователей бота:

```sql
CREATE TABLE telegram_bot_users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT NOT NULL,              -- ID пользователя в Telegram
    first_name VARCHAR(255) DEFAULT NULL, -- Имя пользователя
    username VARCHAR(255) DEFAULT NULL,   -- Username (без @)
    last_name VARCHAR(255) DEFAULT NULL,  -- Фамилия пользователя
    created_at DATETIME NOT NULL,         -- Дата первого обращения
    updated_at DATETIME NOT NULL,         -- Дата последнего обновления
    
    PRIMARY KEY (id),
    UNIQUE KEY idx_user_id (user_id),
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Таблица: `telegram_bot_conversations`

Хранит активные состояния диалогов:

```sql
CREATE TABLE telegram_bot_conversations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    chat_id BIGINT NOT NULL,              -- ID чата
    user_id BIGINT NOT NULL,              -- ID пользователя
    state VARCHAR(100) NOT NULL,          -- Текущее состояние диалога
    data JSON DEFAULT NULL,               -- Данные диалога (произвольные)
    message_id BIGINT UNSIGNED DEFAULT NULL, -- ID сообщения с кнопками для удаления
    created_at DATETIME NOT NULL,         -- Время начала диалога
    updated_at DATETIME NOT NULL,         -- Время последнего обновления
    expires_at DATETIME NOT NULL,         -- Время истечения диалога
    
    PRIMARY KEY (id),
    INDEX idx_chat_user (chat_id, user_id),
    INDEX idx_expires (expires_at),
    INDEX idx_state (state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Конфигурация

### Файл: `config/telegram_bot_conversations.json`

```json
{
    "conversations": {
        "enabled": true,
        "timeout": 3600,
        "auto_create_tables": true
    }
}
```

### Параметры

| Параметр | Тип | Описание | Значение по умолчанию |
|----------|-----|----------|----------------------|
| `enabled` | bool | Включить/выключить менеджер | `false` |
| `timeout` | int | Время жизни диалога в секундах | `3600` (1 час) |
| `auto_create_tables` | bool | Автоматически создать таблицы | `true` |

## Использование

### 1. Базовая настройка

```php
use App\Component\TelegramBot\Core\ConversationManager;

// Подключение к БД
$db = $factory->getConnection('main');

// Загрузка конфигурации
$config = ConfigLoader::load(__DIR__ . '/config/telegram_bot_conversations.json');

// Создание менеджера
$conversationManager = new ConversationManager(
    $db,
    $logger,
    $config['conversations']
);
```

### 2. Сохранение пользователя

```php
// При получении сообщения сохраняем/обновляем данные пользователя
if ($update->message && $update->message->from) {
    $conversationManager->saveUser(
        $update->message->from->id,
        $update->message->from->firstName,
        $update->message->from->username,
        $update->message->from->lastName
    );
}
```

### 3. Многошаговый диалог

#### Шаг 1: Начало диалога

```php
// Пользователь нажимает /adduser
$textHandler->handleCommand($update, 'adduser', function ($message) use (
    $api,
    $conversationManager
) {
    $chatId = $message->chat->id;
    $userId = $message->from->id;
    
    // Создаем клавиатуру с вариантами
    $keyboard = (new InlineKeyboardBuilder())
        ->addCallbackButton('👤 Обычный', 'type:user')
        ->addCallbackButton('👨‍💼 Админ', 'type:admin')
        ->build();
    
    $sentMessage = $api->sendMessage(
        $chatId,
        "Выберите тип:",
        ['reply_markup' => $keyboard]
    );
    
    // Начинаем диалог, сохраняя ID сообщения
    $conversationManager->startConversation(
        $chatId,
        $userId,
        'awaiting_type',      // начальное состояние
        [],                   // пустые данные
        $sentMessage->messageId  // ID для последующего удаления
    );
});
```

#### Шаг 2: Обработка выбора и переход к следующему шагу

```php
// Пользователь нажимает кнопку
$callbackHandler->handleAction($update, 'type', function ($query, $params) use (
    $api,
    $conversationManager
) {
    $chatId = $query->message->chat->id;
    $userId = $query->from->id;
    $selectedType = $params[0]; // 'user' или 'admin'
    
    // Получаем текущий диалог
    $conversation = $conversationManager->getConversation($chatId, $userId);
    
    if (!$conversation) {
        return; // Диалог не найден
    }
    
    // Удаляем сообщение с кнопками
    if ($conversation['message_id']) {
        $api->deleteMessage($chatId, $conversation['message_id']);
    }
    
    // Запрашиваем следующий шаг
    $api->sendMessage($chatId, "Введите имя:");
    
    // Обновляем состояние диалога
    $conversationManager->updateConversation(
        $chatId,
        $userId,
        'awaiting_name',                    // новое состояние
        ['type' => $selectedType],          // сохраняем выбор
        null                                // нет нового сообщения с кнопками
    );
});
```

#### Шаг 3: Обработка ввода текста

```php
// Пользователь вводит имя
$textHandler->handlePlainText($update, function ($message, $text) use (
    $api,
    $conversationManager
) {
    $chatId = $message->chat->id;
    $userId = $message->from->id;
    
    // Получаем текущий диалог
    $conversation = $conversationManager->getConversation($chatId, $userId);
    
    if (!$conversation) {
        return; // Нет активного диалога
    }
    
    // Проверяем состояние
    if ($conversation['state'] === 'awaiting_name') {
        $name = trim($text);
        
        // Валидация
        if (empty($name)) {
            $api->sendMessage($chatId, "Имя не может быть пустым!");
            return;
        }
        
        // Переходим к следующему шагу
        $api->sendMessage($chatId, "Введите email:");
        
        $conversationManager->updateConversation(
            $chatId,
            $userId,
            'awaiting_email',
            ['name' => $name]  // добавляем имя к существующим данным
        );
    }
    
    if ($conversation['state'] === 'awaiting_email') {
        $email = trim($text);
        
        // Валидация email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $api->sendMessage($chatId, "Неверный формат email!");
            return;
        }
        
        // Показываем итоги
        $data = $conversation['data'];
        $keyboard = (new InlineKeyboardBuilder())
            ->addCallbackButton('✅ Подтвердить', 'confirm:yes')
            ->addCallbackButton('❌ Отменить', 'confirm:no')
            ->build();
        
        $sentMessage = $api->sendMessage(
            $chatId,
            "Проверьте данные:\n" .
            "Тип: {$data['type']}\n" .
            "Имя: {$data['name']}\n" .
            "Email: $email",
            ['reply_markup' => $keyboard]
        );
        
        $conversationManager->updateConversation(
            $chatId,
            $userId,
            'awaiting_confirmation',
            ['email' => $email],
            $sentMessage->messageId
        );
    }
});
```

#### Шаг 4: Завершение диалога

```php
// Пользователь подтверждает
$callbackHandler->handleAction($update, 'confirm', function ($query, $params) use (
    $api,
    $conversationManager,
    $db
) {
    $chatId = $query->message->chat->id;
    $userId = $query->from->id;
    $action = $params[0];
    
    $conversation = $conversationManager->getConversation($chatId, $userId);
    
    if (!$conversation) {
        return;
    }
    
    // Удаляем сообщение с кнопками
    if ($conversation['message_id']) {
        $api->deleteMessage($chatId, $conversation['message_id']);
    }
    
    if ($action === 'yes') {
        $data = $conversation['data'];
        
        // Сохраняем данные в основную таблицу
        $db->insert('users', [
            'type' => $data['type'],
            'name' => $data['name'],
            'email' => $data['email'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        
        $api->sendMessage($chatId, "✅ Пользователь зарегистрирован!");
    } else {
        $api->sendMessage($chatId, "❌ Регистрация отменена");
    }
    
    // Завершаем диалог
    $conversationManager->endConversation($chatId, $userId);
});
```

### 4. Получение данных пользователя

```php
// Получить данные пользователя
$user = $conversationManager->getUser($userId);

if ($user) {
    echo "Имя: " . $user['first_name'] . "\n";
    echo "Username: @" . $user['username'] . "\n";
    echo "Зарегистрирован: " . $user['created_at'] . "\n";
}
```

### 5. Статистика диалогов

```php
$stats = $conversationManager->getStatistics();

echo "Активных диалогов: {$stats['total']}\n";

foreach ($stats['by_state'] as $state => $count) {
    echo "$state: $count\n";
}
```

### 6. Очистка устаревших диалогов

```php
// Ручная очистка
$deleted = $conversationManager->cleanupExpiredConversations();
echo "Удалено: $deleted\n";

// Автоматическая очистка (в webhook обработчике)
if (rand(1, 20) === 1) {  // с вероятностью 5%
    $conversationManager->cleanupExpiredConversations();
}
```

## Примеры сценариев

### Сценарий 1: Регистрация пользователя

**Состояния:**
1. `awaiting_type` - ожидание выбора типа пользователя
2. `awaiting_name` - ожидание ввода имени
3. `awaiting_email` - ожидание ввода email
4. `awaiting_confirmation` - ожидание подтверждения

**Данные в JSON:**
```json
{
    "type": "admin",
    "type_label": "👨‍💼 Администратор",
    "name": "John Doe",
    "email": "john@example.com"
}
```

### Сценарий 2: Создание заказа

**Состояния:**
1. `select_category` - выбор категории товара
2. `select_product` - выбор конкретного товара
3. `enter_quantity` - ввод количества
4. `enter_address` - ввод адреса доставки
5. `confirm_order` - подтверждение заказа

### Сценарий 3: Настройки бота

**Состояния:**
1. `main_settings` - главное меню настроек
2. `notification_settings` - настройки уведомлений
3. `language_settings` - выбор языка
4. `timezone_settings` - выбор часового пояса

## Работа с кнопками и удаление сообщений

### Паттерн: Однократное нажатие кнопки

```php
// Сохраняем ID сообщения при отправке
$sentMessage = $api->sendMessage($chatId, "Выберите:", ['reply_markup' => $keyboard]);
$conversationManager->startConversation($chatId, $userId, 'state', [], $sentMessage->messageId);

// При обработке кнопки удаляем сообщение
$conversation = $conversationManager->getConversation($chatId, $userId);
if ($conversation['message_id']) {
    $api->deleteMessage($chatId, $conversation['message_id']);
}
```

### Паттерн: Обновление кнопок

```php
// Обновляем клавиатуру вместо удаления
$newKeyboard = InlineKeyboardBuilder::makeSimple(['Далее' => 'next']);
$api->editMessageReplyMarkup($chatId, $conversation['message_id'], $newKeyboard);
```

## Управление тайм-аутами

### Настройка времени жизни

```json
{
    "conversations": {
        "enabled": true,
        "timeout": 1800  // 30 минут
    }
}
```

### Продление диалога

```php
// При каждом обновлении диалога тайм-аут обновляется автоматически
$conversationManager->updateConversation($chatId, $userId, $newState, $data);
```

## Очистка и обслуживание

### Автоматическая очистка через cron

```bash
# Каждый час
0 * * * * php /path/to/project/bin/telegram_bot_cleanup_conversations.php

# Каждые 15 минут
*/15 * * * * php /path/to/project/bin/telegram_bot_cleanup_conversations.php
```

### Ручная очистка

```php
$deleted = $conversationManager->cleanupExpiredConversations();
```

## Отладка и мониторинг

### Логирование

Все операции логируются:

```
[INFO] Начат новый диалог: chat_id=123, user_id=456, state=awaiting_name
[DEBUG] Диалог обновлен: id=1, new_state=awaiting_email
[INFO] Диалог завершен: chat_id=123, user_id=456
[INFO] Очищены устаревшие диалоги: deleted=5
```

### Мониторинг активных диалогов

```php
$stats = $conversationManager->getStatistics();

// Отправить в систему мониторинга
if ($stats['total'] > 100) {
    $logger->warning('Много активных диалогов', ['total' => $stats['total']]);
}
```

## Лучшие практики

### 1. Всегда проверяйте существование диалога

```php
$conversation = $conversationManager->getConversation($chatId, $userId);
if (!$conversation) {
    $api->sendMessage($chatId, "Диалог не найден. Начните заново с /start");
    return;
}
```

### 2. Валидируйте состояние

```php
if ($conversation['state'] !== 'expected_state') {
    $logger->warning('Неожиданное состояние диалога');
    return;
}
```

### 3. Обрабатывайте ошибки удаления сообщений

```php
try {
    $api->deleteMessage($chatId, $messageId);
} catch (\Exception $e) {
    $logger->warning('Не удалось удалить сообщение', [
        'message_id' => $messageId,
        'error' => $e->getMessage(),
    ]);
}
```

### 4. Используйте описательные состояния

```php
// Хорошо
'awaiting_user_email'
'confirming_order_details'
'selecting_payment_method'

// Плохо
'step1'
'state2'
'wait'
```

### 5. Сохраняйте минимум данных

```php
// Храните только необходимое
$conversationManager->updateConversation($chatId, $userId, 'state', [
    'selected_id' => 123,
    'step' => 2,
]);

// Не храните большие объекты
// ❌ Плохо: ['full_user_object' => $userObject]
```

## Ограничения

- **Один активный диалог** на пользователя в чате
- **JSON данные** ограничены размером поля (обычно до 64KB в MySQL)
- **Тайм-аут** нужно выбирать разумно (не слишком короткий, не слишком длинный)
- **Удаление сообщений** работает только для сообщений бота (не старше 48 часов)

## Устранение неполадок

### Диалоги не сохраняются

- Проверьте `enabled = true` в конфигурации
- Убедитесь, что таблицы созданы
- Проверьте подключение к БД
- Просмотрите логи

### Диалоги не удаляются

- Проверьте вызов `endConversation()`
- Убедитесь, что очистка устаревших диалогов работает
- Проверьте `expires_at` в БД

### Сообщения не удаляются

- Проверьте, что `message_id` сохранен
- Убедитесь, что сообщение не старше 48 часов
- Проверьте права бота

## Примеры

📖 **Полный пример:** [examples/telegram_bot_with_conversations.php](examples/telegram_bot_with_conversations.php)
📊 **Тест:** [tests/telegram_bot_conversation_manager_test.php](tests/telegram_bot_conversation_manager_test.php)
🔧 **Утилита очистки:** [bin/telegram_bot_cleanup_conversations.php](bin/telegram_bot_cleanup_conversations.php)

## Поддержка

- PHP 8.1+
- MySQL 5.7+ / MySQL 8.0+
- InnoDB engine
- utf8mb4 кодировка
- JSON support в MySQL

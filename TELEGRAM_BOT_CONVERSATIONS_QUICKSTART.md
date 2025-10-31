# Быстрый старт: Система диалогов Telegram бота

## Что это?

Система для создания многошаговых интерактивных диалогов с пользователями Telegram бота с сохранением состояния, автоматическим удалением кнопок и хранением данных пользователей.

## Возможности

✅ Многошаговые диалоги с сохранением состояния  
✅ Хранение данных пользователей (id, name, username)  
✅ Автоматическое удаление кнопок после использования  
✅ Тайм-ауты для автоматического завершения  
✅ Статистика активных диалогов  
✅ Автоматическая очистка устаревших  

## Установка за 3 шага

### 1. Настройте конфигурацию

Отредактируйте `config/telegram_bot_conversations.json`:

```json
{
    "conversations": {
        "enabled": true,
        "timeout": 3600
    }
}
```

### 2. Добавьте в код

```php
use App\Component\TelegramBot\Core\ConversationManager;

// Подключение к БД
$db = $factory->getConnection('main');

// Создание менеджера
$conversationManager = new ConversationManager($db, $logger, $config['conversations']);
```

### 3. Готово!

Таблицы создаются автоматически при первом использовании.

## Простой пример: Регистрация пользователя

### Шаг 1: Начало диалога

```php
$textHandler->handleCommand($update, 'register', function ($message) use ($api, $conversationManager) {
    $keyboard = InlineKeyboardBuilder::makeSimple([
        '👤 Обычный' => 'type:user',
        '👨‍💼 Админ' => 'type:admin',
    ]);
    
    $sent = $api->sendMessage($message->chat->id, "Выберите тип:", ['reply_markup' => $keyboard]);
    
    // Сохраняем ID сообщения для последующего удаления
    $conversationManager->startConversation(
        $message->chat->id,
        $message->from->id,
        'awaiting_type',
        [],
        $sent->messageId
    );
});
```

### Шаг 2: Обработка кнопки

```php
$callbackHandler->handleAction($update, 'type', function ($query, $params) use ($api, $conversationManager) {
    $chatId = $query->message->chat->id;
    $userId = $query->from->id;
    
    // Получаем диалог
    $conversation = $conversationManager->getConversation($chatId, $userId);
    
    // Удаляем сообщение с кнопками
    if ($conversation['message_id']) {
        $api->deleteMessage($chatId, $conversation['message_id']);
    }
    
    // Переходим к следующему шагу
    $api->sendMessage($chatId, "Введите имя:");
    
    $conversationManager->updateConversation(
        $chatId,
        $userId,
        'awaiting_name',
        ['type' => $params[0]]
    );
});
```

### Шаг 3: Обработка ввода

```php
$textHandler->handlePlainText($update, function ($message, $text) use ($api, $conversationManager) {
    $chatId = $message->chat->id;
    $userId = $message->from->id;
    
    $conversation = $conversationManager->getConversation($chatId, $userId);
    
    if ($conversation && $conversation['state'] === 'awaiting_name') {
        $api->sendMessage($chatId, "Спасибо! Регистрация завершена.");
        
        // Завершаем диалог
        $conversationManager->endConversation($chatId, $userId);
    }
});
```

## Основные методы

### Начало диалога

```php
$conversationManager->startConversation(
    $chatId,
    $userId,
    'state_name',        // состояние
    ['key' => 'value'],  // данные
    $messageId           // ID сообщения с кнопками
);
```

### Получение диалога

```php
$conversation = $conversationManager->getConversation($chatId, $userId);

if ($conversation) {
    $state = $conversation['state'];
    $data = $conversation['data'];
    $messageId = $conversation['message_id'];
}
```

### Обновление диалога

```php
$conversationManager->updateConversation(
    $chatId,
    $userId,
    'new_state',
    ['new_key' => 'new_value'],  // добавляется к существующим
    $newMessageId
);
```

### Завершение диалога

```php
$conversationManager->endConversation($chatId, $userId);
```

### Сохранение пользователя

```php
$conversationManager->saveUser(
    $userId,
    $firstName,
    $username,
    $lastName
);
```

## Паттерн: Удаление кнопок после нажатия

```php
// 1. При создании сообщения сохраняем ID
$sent = $api->sendMessage($chatId, "Выберите:", ['reply_markup' => $keyboard]);
$conversationManager->startConversation($chatId, $userId, 'state', [], $sent->messageId);

// 2. При обработке кнопки удаляем сообщение
$conversation = $conversationManager->getConversation($chatId, $userId);
if ($conversation['message_id']) {
    $api->deleteMessage($chatId, $conversation['message_id']);
}
```

## Работа с данными между шагами

```php
// Шаг 1: Сохраняем имя
$conversationManager->updateConversation($chatId, $userId, 'awaiting_email', [
    'name' => 'John'
]);

// Шаг 2: Добавляем email (name сохранится)
$conversationManager->updateConversation($chatId, $userId, 'awaiting_phone', [
    'email' => 'john@example.com'
]);

// Шаг 3: Все данные доступны
$conversation = $conversationManager->getConversation($chatId, $userId);
// $conversation['data'] = ['name' => 'John', 'email' => 'john@example.com']
```

## Статистика

```php
$stats = $conversationManager->getStatistics();

echo "Активных диалогов: {$stats['total']}\n";

foreach ($stats['by_state'] as $state => $count) {
    echo "- $state: $count\n";
}
```

## Очистка устаревших диалогов

### Автоматическая (через cron)

```bash
# Каждый час
0 * * * * php /path/to/bin/telegram_bot_cleanup_conversations.php
```

### В webhook (с вероятностью)

```php
if (rand(1, 20) === 1) {  // 5% вероятность
    $conversationManager->cleanupExpiredConversations();
}
```

## Структура диалога

### Пример: Многошаговая регистрация

```
/register
    ↓
[awaiting_type] → Выбор типа пользователя (кнопки)
    ↓
[awaiting_name] → Ввод имени (текст)
    ↓
[awaiting_email] → Ввод email (текст + валидация)
    ↓
[awaiting_confirmation] → Подтверждение (кнопки)
    ↓
[completed] → Сохранение в БД + завершение диалога
```

### Данные на каждом шаге

```php
// awaiting_type
data = {}

// awaiting_name
data = {type: 'admin'}

// awaiting_email  
data = {type: 'admin', name: 'John'}

// awaiting_confirmation
data = {type: 'admin', name: 'John', email: 'john@example.com'}

// completed
endConversation() // данные удаляются
```

## Проверка состояния

```php
$conversation = $conversationManager->getConversation($chatId, $userId);

if (!$conversation) {
    // Нет активного диалога
    return;
}

switch ($conversation['state']) {
    case 'awaiting_name':
        // Обработка ввода имени
        break;
        
    case 'awaiting_email':
        // Обработка ввода email
        break;
}
```

## Таймауты

```json
{
    "conversations": {
        "timeout": 3600  // 1 час в секундах
    }
}
```

- При каждом `updateConversation()` таймаут обновляется
- Устаревшие диалоги удаляются автоматически
- Можно настроить отдельно для разных ботов

## Лучшие практики

### ✅ Делайте

- Проверяйте существование диалога перед обработкой
- Используйте описательные названия состояний
- Валидируйте ввод пользователя
- Удаляйте кнопки после использования
- Обрабатывайте ошибки удаления сообщений

### ❌ Не делайте

- Не храните большие объекты в data
- Не используйте слишком короткие таймауты
- Не забывайте завершать диалоги
- Не создавайте циклические переходы

## Отладка

### Просмотр активных диалогов

```sql
SELECT * FROM telegram_bot_conversations WHERE expires_at > NOW();
```

### Просмотр пользователей

```sql
SELECT * FROM telegram_bot_users ORDER BY created_at DESC LIMIT 10;
```

### Логи

```
logs/telegram_bot_conversations.log
```

## Примеры

📖 **Полный пример:** [examples/telegram_bot_with_conversations.php](examples/telegram_bot_with_conversations.php)  
📚 **Документация:** [TELEGRAM_BOT_CONVERSATIONS.md](TELEGRAM_BOT_CONVERSATIONS.md)  
🧪 **Тест:** [tests/telegram_bot_conversation_manager_test.php](tests/telegram_bot_conversation_manager_test.php)

## Поддержка

- PHP 8.1+
- MySQL 5.7+ / MySQL 8.0+
- InnoDB engine
- utf8mb4 кодировка

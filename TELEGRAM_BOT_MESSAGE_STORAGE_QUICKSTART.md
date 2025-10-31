# Быстрый старт: Хранение сообщений Telegram бота

## Что это?

Система автоматического сохранения всех входящих и исходящих сообщений Telegram бота в MySQL БД с гибкой настройкой уровня детализации.

## Возможности

✅ Автоматическое сохранение всех сообщений  
✅ 4 уровня детализации (minimal → full)  
✅ Автоматическое создание таблицы  
✅ Статистика по сообщениям  
✅ Очистка старых записей  
✅ Исключение методов  
✅ Сохранение ошибок  

## Установка за 3 шага

### 1. Настройте конфигурацию

Отредактируйте `config/telegram_bot_message_storage.json`:

```json
{
    "message_storage": {
        "enabled": true,
        "storage_level": "standard",
        "retention_days": 90
    }
}
```

### 2. Добавьте в код

```php
use App\Component\TelegramBot\Core\MessageStorage;

// Подключение к БД
$db = $factory->getConnection('main');

// Создание хранилища
$messageStorage = new MessageStorage($db, $logger, $config['message_storage']);

// Создание API с хранилищем
$api = new TelegramAPI($token, $http, $logger, $messageStorage);
```

### 3. Готово!

Все сообщения теперь автоматически сохраняются в БД.

## Уровни хранения

| Уровень | Данные | Размер | Использование |
|---------|--------|--------|---------------|
| **minimal** | ID, тип, статус | 100 байт | Базовая статистика |
| **standard** | + текст, файлы | 500 байт | История переписки |
| **extended** | + метаданные | 1 КБ | Анализ медиа |
| **full** | + все данные API | 5-10 КБ | Полная отладка |

## Получение статистики

```php
$stats = $messageStorage->getStatistics();

echo "Всего: {$stats['total']}\n";
echo "Исходящих: {$stats['outgoing']}\n";
echo "Входящих: {$stats['incoming']}\n";

// По типам
foreach ($stats['by_type'] as $type => $count) {
    echo "$type: $count\n";
}
```

## Очистка старых записей

### Автоматическая (через cron)

```bash
# Добавьте в crontab (ежедневно в 2:00)
0 2 * * * php /path/to/project/bin/telegram_bot_cleanup_messages.php
```

### Ручная

```php
$deleted = $messageStorage->cleanupOldMessages();
echo "Удалено: $deleted записей\n";
```

## Структура таблицы

Таблица `telegram_bot_messages` создается автоматически:

```sql
CREATE TABLE telegram_bot_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    direction ENUM('incoming', 'outgoing'),
    message_id BIGINT UNSIGNED,
    chat_id BIGINT,
    user_id BIGINT,
    message_type VARCHAR(50),
    created_at DATETIME,
    text TEXT,
    -- ... и другие поля в зависимости от уровня
    
    INDEX idx_chat_id (chat_id),
    INDEX idx_created_at (created_at)
);
```

## Примеры запросов

### Все сообщения чата

```sql
SELECT * FROM telegram_bot_messages
WHERE chat_id = 123456
ORDER BY created_at DESC
LIMIT 100;
```

### Неудачные отправки

```sql
SELECT * FROM telegram_bot_messages
WHERE success = 0;
```

### Поиск по тексту

```sql
SELECT * FROM telegram_bot_messages
WHERE text LIKE '%поиск%';
```

## Оценка объема

Для 10,000 сообщений в день:

- **minimal:** 1 МБ/день, 365 МБ/год
- **standard:** 5 МБ/день, 1.8 ГБ/год
- **extended:** 10 МБ/день, 3.6 ГБ/год
- **full:** 50-100 МБ/день, 18-36 ГБ/год

## Рекомендации

### Production
```json
{
    "enabled": true,
    "storage_level": "standard",
    "retention_days": 90,
    "exclude_methods": ["getMe", "getWebhookInfo"]
}
```

### Development
```json
{
    "enabled": true,
    "storage_level": "full",
    "retention_days": 7
}
```

### Высокая нагрузка
```json
{
    "enabled": true,
    "storage_level": "minimal",
    "retention_days": 30,
    "store_incoming": false
}
```

## Документация

📖 **Полная документация:** [TELEGRAM_BOT_MESSAGE_STORAGE.md](TELEGRAM_BOT_MESSAGE_STORAGE.md)  
📊 **Отчет о тестировании:** [TELEGRAM_MESSAGE_STORAGE_TEST_REPORT.md](TELEGRAM_MESSAGE_STORAGE_TEST_REPORT.md)  
💡 **Пример использования:** [examples/telegram_bot_with_message_storage.php](examples/telegram_bot_with_message_storage.php)

## Поддержка

- PHP 8.1+
- MySQL 5.7+ / MySQL 8.0+
- InnoDB engine
- utf8mb4 кодировка

# Система хранения сообщений Telegram бота в БД MySQL

## Описание

Полнофункциональная система для хранения всех входящих и исходящих сообщений Telegram бота в базе данных MySQL. Предоставляет гибкую настройку уровня детализации хранимых данных, автоматическое создание таблиц, статистику и очистку старых записей.

## Возможности

### ✅ Основной функционал

- **Автоматическое сохранение** всех исходящих и входящих сообщений
- **Автоматическое создание таблицы** при первом использовании
- **4 уровня детализации** хранимых данных (minimal/standard/extended/full)
- **Гибкая конфигурация** через JSON файл
- **Индексы БД** для быстрого поиска и фильтрации
- **Статистика** по сообщениям (общая и по чату)
- **Автоматическая очистка** старых записей
- **Сохранение ошибок** для отладки
- **Исключение методов** из сохранения
- **Раздельное управление** входящими и исходящими сообщениями
- **Полное логирование** всех операций

## Структура таблицы

### Таблица: `telegram_bot_messages`

```sql
CREATE TABLE telegram_bot_messages (
    -- Базовые поля (LEVEL_MINIMAL)
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    direction ENUM('incoming', 'outgoing') NOT NULL,
    message_id BIGINT UNSIGNED DEFAULT NULL,
    chat_id BIGINT NOT NULL,
    user_id BIGINT DEFAULT NULL,
    message_type VARCHAR(50) NOT NULL,
    method_name VARCHAR(50) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    telegram_date DATETIME DEFAULT NULL,
    
    -- Стандартные поля (LEVEL_STANDARD)
    text TEXT DEFAULT NULL,
    caption TEXT DEFAULT NULL,
    file_id VARCHAR(255) DEFAULT NULL,
    file_name VARCHAR(255) DEFAULT NULL,
    reply_to_message_id BIGINT UNSIGNED DEFAULT NULL,
    
    -- Расширенные поля (LEVEL_EXTENDED)
    file_size INT UNSIGNED DEFAULT NULL,
    mime_type VARCHAR(100) DEFAULT NULL,
    media_metadata JSON DEFAULT NULL,
    forward_from_chat_id BIGINT DEFAULT NULL,
    entities JSON DEFAULT NULL,
    
    -- Полные поля (LEVEL_FULL)
    reply_markup JSON DEFAULT NULL,
    options JSON DEFAULT NULL,
    raw_data JSON DEFAULT NULL,
    
    -- Статусные поля
    success TINYINT(1) NOT NULL DEFAULT 1,
    error_code INT DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    
    -- Индексы
    PRIMARY KEY (id),
    INDEX idx_chat_id (chat_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_direction_type (direction, message_type),
    INDEX idx_message_id (message_id),
    INDEX idx_telegram_date (telegram_date),
    UNIQUE KEY idx_unique_message (direction, chat_id, message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Индексы

- `PRIMARY KEY (id)` - первичный ключ
- `idx_chat_id` - быстрый поиск по чату
- `idx_user_id` - поиск по пользователю
- `idx_created_at` - сортировка по времени создания
- `idx_direction_type` - фильтрация по направлению и типу
- `idx_message_id` - поиск конкретного сообщения
- `idx_telegram_date` - сортировка по времени в Telegram
- `idx_unique_message` - предотвращение дубликатов

## Уровни хранения

### 1. LEVEL_MINIMAL - Минимальный

**Назначение:** Базовая статистика и мониторинг доставляемости

**Хранимые поля:**
- id, direction, message_id, chat_id, user_id
- message_type, method_name
- created_at, telegram_date
- success, error_code, error_message

**Объем данных:** ~100 байт на сообщение

**Пример использования:**
```php
$storageConfig = [
    'enabled' => true,
    'storage_level' => MessageStorage::LEVEL_MINIMAL,
];
```

### 2. LEVEL_STANDARD - Стандартный (по умолчанию)

**Назначение:** Полноценная история переписки с возможностью поиска по тексту

**Дополнительные поля:**
- text, caption
- file_id, file_name
- reply_to_message_id

**Объем данных:** ~500 байт на сообщение (зависит от длины текста)

**Пример использования:**
```php
$storageConfig = [
    'enabled' => true,
    'storage_level' => MessageStorage::LEVEL_STANDARD, // по умолчанию
];
```

### 3. LEVEL_EXTENDED - Расширенный

**Назначение:** Детальный анализ медиа-контента и отслеживание пересылок

**Дополнительные поля:**
- file_size, mime_type
- media_metadata (JSON: width, height, duration)
- forward_from_chat_id
- entities (JSON: форматирование текста)

**Объем данных:** ~1 КБ на сообщение

**Пример использования:**
```php
$storageConfig = [
    'enabled' => true,
    'storage_level' => MessageStorage::LEVEL_EXTENDED,
];
```

### 4. LEVEL_FULL - Полный

**Назначение:** Полная отладка, реплей сообщений, глубокий аудит

**Дополнительные поля:**
- reply_markup (JSON: клавиатуры)
- options (JSON: все параметры запроса)
- raw_data (JSON: полный ответ API)

**Объем данных:** ~5-10 КБ на сообщение

**Пример использования:**
```php
$storageConfig = [
    'enabled' => true,
    'storage_level' => MessageStorage::LEVEL_FULL,
];
```

## Конфигурация

### Файл: `config/telegram_bot_message_storage.json`

```json
{
    "message_storage": {
        "enabled": true,
        "storage_level": "standard",
        "store_incoming": true,
        "store_outgoing": true,
        "exclude_methods": [
            "getMe",
            "getWebhookInfo",
            "answerCallbackQuery"
        ],
        "retention_days": 90,
        "auto_create_table": true
    }
}
```

### Параметры конфигурации

| Параметр | Тип | Описание | Значение по умолчанию |
|----------|-----|----------|----------------------|
| `enabled` | bool | Включить/выключить хранилище | `false` |
| `storage_level` | string | Уровень детализации (`minimal`/`standard`/`extended`/`full`) | `standard` |
| `store_incoming` | bool | Сохранять входящие сообщения | `true` |
| `store_outgoing` | bool | Сохранять исходящие сообщения | `true` |
| `exclude_methods` | array | Список методов API для исключения из сохранения | `[]` |
| `retention_days` | int | Количество дней хранения (0 = бесконечно) | `0` |
| `auto_create_table` | bool | Автоматически создать таблицу при первом использовании | `true` |

## Использование

### 1. Базовая настройка

```php
use App\Component\Config\ConfigLoader;
use App\Component\MySQLConnectionFactory;
use App\Component\Http;
use App\Component\Logger;
use App\Component\TelegramBot\Core\MessageStorage;
use App\Component\TelegramBot\Core\TelegramAPI;

// Загрузка конфигураций
$mysqlConfig = ConfigLoader::load(__DIR__ . '/config/mysql.json');
$storageConfig = ConfigLoader::load(__DIR__ . '/config/telegram_bot_message_storage.json');
$telegramConfig = ConfigLoader::load(__DIR__ . '/config/telegram.json');

// Инициализация зависимостей
$logger = new Logger(['directory' => __DIR__ . '/logs']);
$http = new Http(['timeout' => 30], $logger);

// Подключение к БД
$factory = new MySQLConnectionFactory($mysqlConfig, $logger);
$db = $factory->getConnection('main');

// Создание хранилища
$messageStorage = new MessageStorage(
    $db,
    $logger,
    $storageConfig['message_storage']
);

// Создание TelegramAPI с хранилищем
$api = new TelegramAPI(
    $telegramConfig['token'],
    $http,
    $logger,
    $messageStorage
);
```

### 2. Автоматическое сохранение

После настройки все сообщения сохраняются автоматически:

```php
// Исходящее сообщение - сохраняется автоматически
$message = $api->sendMessage($chatId, 'Привет!');

// Входящее сообщение - сохраняется через метод
$update = $webhookHandler->getUpdate();
if ($update->message) {
    $messageStorage->storeIncoming($update->message);
}
```

### 3. Получение статистики

```php
// Общая статистика
$stats = $messageStorage->getStatistics();
echo "Всего сообщений: {$stats['total']}\n";
echo "Исходящих: {$stats['outgoing']}\n";
echo "Входящих: {$stats['incoming']}\n";
echo "Успешных: {$stats['success']}\n";
echo "Неудачных: {$stats['failed']}\n";

// По типам
foreach ($stats['by_type'] as $type => $count) {
    echo "$type: $count\n";
}

// Статистика по конкретному чату
$chatStats = $messageStorage->getStatistics($chatId);

// Статистика за последние 7 дней
$weekStats = $messageStorage->getStatistics(null, 7);
```

### 4. Очистка старых записей

```php
// Ручная очистка (если retention_days > 0)
$deleted = $messageStorage->cleanupOldMessages();
echo "Удалено записей: $deleted\n";

// Автоматическая очистка через cron
// 0 2 * * * php /path/to/cleanup_script.php
```

### 5. Работа без БД

MessageStorage опционален - можно работать без него:

```php
// Без хранилища
$api = new TelegramAPI($token, $http, $logger);

// Или с отключенным хранилищем
$api = new TelegramAPI($token, $http, $logger, null);
```

## Примеры запросов к БД

### Все сообщения определенного чата

```sql
SELECT * FROM telegram_bot_messages
WHERE chat_id = 366442475
ORDER BY created_at DESC
LIMIT 100;
```

### Неудачные отправки

```sql
SELECT * FROM telegram_bot_messages
WHERE success = 0
ORDER BY created_at DESC;
```

### Статистика по типам за последний месяц

```sql
SELECT 
    message_type,
    COUNT(*) as count,
    SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as success_count
FROM telegram_bot_messages
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY message_type;
```

### Поиск по тексту

```sql
SELECT * FROM telegram_bot_messages
WHERE text LIKE '%поиск%'
ORDER BY created_at DESC;
```

### Сообщения с ошибками определенного типа

```sql
SELECT * FROM telegram_bot_messages
WHERE error_code = 400
GROUP BY error_message;
```

## Оценка объема хранилища

### Расчет для 10,000 сообщений в день

| Уровень | Размер на сообщение | Размер в день | Размер в месяц | Размер в год |
|---------|---------------------|---------------|----------------|--------------|
| minimal | 100 байт | ~1 МБ | 30 МБ | 365 МБ |
| standard | 500 байт | ~5 МБ | 150 МБ | ~1.8 ГБ |
| extended | 1 КБ | ~10 МБ | 300 МБ | ~3.6 ГБ |
| full | 5-10 КБ | 50-100 МБ | 1.5-3 ГБ | 18-36 ГБ |

## Рекомендации

### По выбору уровня хранения

- **minimal** - для production ботов с высокой нагрузкой
- **standard** - универсальный вариант для большинства случаев
- **extended** - для ботов с активным обменом медиа
- **full** - для отладки и аудита (временно)

### По безопасности

1. Используйте `retention_days` для автоматической очистки
2. Регулярно создавайте резервные копии БД
3. Ограничьте доступ к таблице на уровне MySQL
4. Исключайте чувствительные методы через `exclude_methods`

### По производительности

1. Таблица оптимизирована индексами для быстрого поиска
2. Используйте партиционирование для больших объемов
3. Настройте буферы InnoDB для лучшей производительности
4. Рассмотрите архивирование старых данных

## Тестирование

### Запуск тестов

```bash
# Интеграционный тест (без БД)
php tests/telegram_bot_message_storage_integration_test.php

# Полный тест (требуется MySQL)
php tests/telegram_bot_message_storage_test.php
```

### Результаты тестирования

✅ Все тесты пройдены успешно:

- Автоматическое создание таблицы
- Сохранение исходящих сообщений (все уровни)
- Сохранение различных типов сообщений
- Обработка и сохранение ошибок
- Получение статистики
- Исключение методов
- Отключение хранения
- Очистка старых записей
- Интеграция с TelegramAPI

## Архитектура

### Класс: `MessageStorage`

```php
namespace App\Component\TelegramBot\Core;

class MessageStorage
{
    // Константы уровней
    public const LEVEL_MINIMAL = 'minimal';
    public const LEVEL_STANDARD = 'standard';
    public const LEVEL_EXTENDED = 'extended';
    public const LEVEL_FULL = 'full';
    
    // Константы направлений
    public const DIRECTION_INCOMING = 'incoming';
    public const DIRECTION_OUTGOING = 'outgoing';
    
    // Публичные методы
    public function isEnabled(): bool;
    public function storeOutgoing(...): ?int;
    public function storeIncoming(Message $message): ?int;
    public function getStatistics(...): array;
    public function cleanupOldMessages(): int;
}
```

### Интеграция с TelegramAPI

MessageStorage автоматически вызывается из `TelegramAPI::sendRequest()` для всех исходящих запросов.

## Логирование

Все операции логируются через `Logger`:

```
[INFO] Таблица создана успешно: telegram_bot_messages
[DEBUG] Исходящее сообщение сохранено: id=1, method=sendMessage, chat_id=123
[DEBUG] Входящее сообщение сохранено: id=2, message_id=456, chat_id=123
[ERROR] Ошибка сохранения сообщения: Connection lost
[INFO] Очистка старых сообщений: retention_days=90, deleted=150
```

## Устранение неполадок

### Таблица не создается

- Проверьте права MySQL пользователя (CREATE TABLE)
- Убедитесь что `auto_create_table = true`
- Проверьте логи на наличие ошибок

### Сообщения не сохраняются

- Проверьте `enabled = true`
- Убедитесь что метод не в списке `exclude_methods`
- Проверьте подключение к БД
- Просмотрите логи

### Большой объем данных

- Уменьшите `storage_level`
- Установите `retention_days`
- Исключите ненужные методы
- Отключите `store_incoming` или `store_outgoing`

## Лицензия

Часть проекта utilities-php, см. LICENSE.

## Авторы

Разработано в рамках модульной системы TelegramBot.

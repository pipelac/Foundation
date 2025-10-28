# Email уведомления в Logger

## Версия: 2.1 (с поддержкой email уведомлений)

Класс Logger теперь поддерживает отправку email уведомлений администратору при возникновении событий определенных уровней (по умолчанию - критических ошибок).

---

## 🎯 Основные возможности

### ✉️ Автоматическая отправка email уведомлений
- Отправка писем при критических ошибках (CRITICAL)
- Настройка отправки для любых уровней логирования
- Поддержка множественных получателей
- Красиво оформленные HTML письма с деталями события

### 🔒 Надежность
- Ошибки отправки email не прерывают логирование
- Email отправка изолирована от основного процесса
- Логирование продолжает работать даже при недоступности SMTP
- Отсутствие циклических зависимостей

### 🎨 Красивые HTML письма
- Цветовая индикация уровня события
- Структурированная таблица с деталями
- Форматированный JSON контекст
- Информация о сервере и времени события

---

## 📝 Конфигурация

### Базовая конфигурация с email уведомлениями

```php
$config = [
    // Стандартные параметры логгера
    'directory' => '/var/log/app',
    'file_name' => 'app.log',
    'max_files' => 5,
    'max_file_size' => 10,
    'enabled' => true,
    
    // Параметры email уведомлений
    'admin_email' => 'admin@example.com', // или массив ['admin1@example.com', 'admin2@example.com']
    'email_config' => [
        'from_email' => 'noreply@example.com',
        'from_name' => 'Logger System',
        'reply_to' => 'support@example.com',
        'reply_name' => 'Support Team',
        'charset' => 'UTF-8',
        // Опциональные SMTP настройки
        'smtp' => [
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'your-email@gmail.com',
            'password' => 'your-app-password',
        ],
        'delivery' => [
            'retry_attempts' => 3,
            'retry_delay' => 5,
            'timeout' => 30,
        ],
    ],
    'email_on_levels' => ['CRITICAL'], // Уровни для отправки email
];

$logger = new Logger($config);
```

---

## 🔧 Параметры конфигурации

### admin_email
**Тип:** `string` или `array<string>`  
**Описание:** Email адрес(а) администратора для получения уведомлений  
**Примеры:**
```php
'admin_email' => 'admin@example.com'
// или
'admin_email' => ['admin1@example.com', 'admin2@example.com', 'admin3@example.com']
```

### email_config
**Тип:** `array` или `null`  
**Описание:** Конфигурация для класса Email  
**Обязательные параметры:**
- `from_email` - адрес отправителя
- `from_name` - имя отправителя

**Опциональные параметры:**
- `reply_to` - адрес для ответов
- `reply_name` - имя для ответов
- `charset` - кодировка (по умолчанию UTF-8)
- `smtp` - настройки SMTP сервера
- `delivery` - настройки доставки (retry, timeout)

Если `email_config` равен `null`, email уведомления будут отключены.

### email_on_levels
**Тип:** `array<string>`  
**По умолчанию:** `['CRITICAL']`  
**Описание:** Массив уровней логирования, при которых отправлять email  
**Допустимые значения:** `DEBUG`, `INFO`, `WARNING`, `ERROR`, `CRITICAL`

**Примеры:**
```php
'email_on_levels' => ['CRITICAL']                    // Только критические ошибки
'email_on_levels' => ['ERROR', 'CRITICAL']           // Ошибки и критические события
'email_on_levels' => ['WARNING', 'ERROR', 'CRITICAL'] // Предупреждения и выше
```

---

## 💡 Примеры использования

### Пример 1: Простая конфигурация

```php
$logger = new Logger([
    'directory' => '/var/log',
    'file_name' => 'app.log',
    'admin_email' => 'admin@example.com',
    'email_config' => [
        'from_email' => 'noreply@example.com',
        'from_name' => 'My Application',
    ],
]);

// Обычное логирование - email не отправляется
$logger->info('Пользователь вошел в систему');
$logger->warning('Низкая производительность');

// Критическая ошибка - email отправляется
$logger->critical('База данных недоступна', [
    'host' => 'localhost',
    'error' => 'Connection refused',
]);
```

### Пример 2: Множественные администраторы

```php
$logger = new Logger([
    'directory' => '/var/log',
    'file_name' => 'app.log',
    'admin_email' => [
        'admin1@example.com',
        'admin2@example.com',
        'devops@example.com',
    ],
    'email_config' => [
        'from_email' => 'alerts@example.com',
        'from_name' => 'Alert System',
    ],
]);

// Email будет отправлен всем администраторам
$logger->critical('Критический сбой системы');
```

### Пример 3: Email для ERROR и CRITICAL

```php
$logger = new Logger([
    'directory' => '/var/log',
    'file_name' => 'app.log',
    'admin_email' => 'admin@example.com',
    'email_config' => [
        'from_email' => 'noreply@example.com',
        'from_name' => 'Application',
    ],
    'email_on_levels' => ['ERROR', 'CRITICAL'],
]);

$logger->info('Запрос обработан');          // Email НЕ отправляется
$logger->warning('Кеш очищен');             // Email НЕ отправляется
$logger->error('Ошибка API');               // Email отправляется
$logger->critical('Система недоступна');     // Email отправляется
```

### Пример 4: С SMTP конфигурацией

```php
$logger = new Logger([
    'directory' => '/var/log',
    'file_name' => 'app.log',
    'admin_email' => 'admin@example.com',
    'email_config' => [
        'from_email' => 'noreply@example.com',
        'from_name' => 'Production Server',
        'smtp' => [
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'your-email@gmail.com',
            'password' => 'your-app-password',
        ],
        'delivery' => [
            'retry_attempts' => 5,
            'retry_delay' => 3,
            'timeout' => 60,
        ],
    ],
]);
```

### Пример 5: Отключение email уведомлений

```php
// Способ 1: Не указывать параметры
$logger = new Logger([
    'directory' => '/var/log',
    'file_name' => 'app.log',
]);

// Способ 2: Установить email_config в null
$logger = new Logger([
    'directory' => '/var/log',
    'file_name' => 'app.log',
    'admin_email' => 'admin@example.com',
    'email_config' => null, // Email уведомления отключены
]);
```

---

## 📧 Формат email уведомления

### Тема письма
```
[CRITICAL] Уведомление от системы логирования
```

### Содержимое письма

Email содержит красиво оформленную HTML таблицу с:

1. **Время события** - timestamp в настроенном формате
2. **Уровень** - цветная метка (CRITICAL = темно-красный)
3. **Сообщение** - текст события
4. **Контекст** - форматированный JSON с дополнительными данными
5. **Сервер** - hostname сервера
6. **Директория логов** - путь к файлам логов

### Цветовая схема

| Уровень | Цвет | Hex |
|---------|------|-----|
| DEBUG | Серый | #6c757d |
| INFO | Голубой | #0dcaf0 |
| WARNING | Желтый | #ffc107 |
| ERROR | Красный | #dc3545 |
| CRITICAL | Темно-красный | #8b0000 |

---

## 🔒 Безопасность и надежность

### Изоляция ошибок
```php
// Даже если отправка email не удается, логирование продолжает работать
$logger->critical('Ошибка'); // Запишется в файл в любом случае
```

Все ошибки отправки email:
- Подавляются (try-catch)
- Логируются через `error_log()`
- Не прерывают основной процесс логирования

### Отсутствие циклических зависимостей

Email класс создается **без** Logger инстанса:
```php
// В Logger.php
$this->emailInstance = new Email($this->emailConfig);
// Logger НЕ передается в Email, чтобы избежать циклического логирования
```

### Валидация email адресов

Все email адреса валидируются при инициализации:
```php
// Выбросит Exception если email невалиден
$logger = new Logger([
    'admin_email' => 'invalid-email', // Exception!
    'email_config' => [...],
]);
```

---

## ⚡ Производительность

### Ленивая инициализация Email класса

Email инстанс создается только при первой отправке:
```php
$logger = new Logger([
    'admin_email' => 'admin@example.com',
    'email_config' => [...],
]);

// Email объект еще НЕ создан

$logger->info('Лог');     // Email объект еще НЕ создан
$logger->warning('Лог');  // Email объект еще НЕ создан

$logger->critical('Ошибка'); // Здесь создается Email инстанс
```

### Минимальные накладные расходы

- Проверка `shouldSendEmailNotification()` - O(1)
- Email отправка в фоне через `error_log()` при ошибках
- Кеширование Email конфигурации

---

## 🧪 Тестирование

### Ручное тестирование

```php
// Создайте тестовый logger
$logger = new Logger([
    'directory' => '/tmp/test_logs',
    'file_name' => 'test.log',
    'admin_email' => 'your-email@example.com',
    'email_config' => [
        'from_email' => 'test@example.com',
        'from_name' => 'Test Logger',
        // Используйте реальные SMTP настройки для теста
        'smtp' => [
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'your-gmail@gmail.com',
            'password' => 'your-app-password',
        ],
    ],
]);

// Отправьте тестовое событие
$logger->critical('Тестовое критическое событие', [
    'test' => true,
    'timestamp' => time(),
]);

echo "Проверьте ваш email: your-email@example.com\n";
```

---

## 🚨 Типичные проблемы и решения

### Email не отправляются

**Причины:**
1. Не указан `admin_email` или `email_config`
2. SMTP сервер недоступен
3. Неверные SMTP учетные данные
4. Функция `mail()` не настроена (если SMTP не используется)

**Решение:**
- Проверьте error_log системы
- Убедитесь, что SMTP настройки корректны
- Используйте Gmail App Password для Gmail SMTP

### Email отправляются дублями

**Причина:** Logger инициализируется несколько раз

**Решение:** Используйте паттерн Singleton для Logger

### Email не содержит контекст

**Причина:** Контекст не сериализуется в JSON

**Решение:** Убедитесь, что контекст содержит только сериализуемые данные

---

## 📦 Зависимости

Для работы email уведомлений требуется:
- PHP 8.1+
- Класс `Email` из `App\Component\Email`
- Расширение `json`
- Расширение `filter` (для валидации email)

---

## 🎓 Лучшие практики

### 1. Используйте email только для важных событий

```php
// ❌ Плохо - спам администратору
'email_on_levels' => ['DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL']

// ✅ Хорошо - только критические события
'email_on_levels' => ['CRITICAL']

// ✅ Хорошо - ошибки и критические события
'email_on_levels' => ['ERROR', 'CRITICAL']
```

### 2. Настройте реальные SMTP параметры

```php
// ❌ Плохо - может не работать
'email_config' => [
    'from_email' => 'noreply@example.com',
    'from_name' => 'App',
]

// ✅ Хорошо - с настроенным SMTP
'email_config' => [
    'from_email' => 'noreply@yourdomain.com',
    'from_name' => 'Production Alert',
    'smtp' => [
        'host' => 'smtp.yourdomain.com',
        'port' => 587,
        'encryption' => 'tls',
        'username' => 'alerts@yourdomain.com',
        'password' => getenv('SMTP_PASSWORD'),
    ],
]
```

### 3. Используйте несколько администраторов для критических систем

```php
'admin_email' => [
    'primary-admin@example.com',
    'backup-admin@example.com',
    'devops-team@example.com',
]
```

### 4. Храните чувствительные данные в переменных окружения

```php
'email_config' => [
    'from_email' => getenv('LOGGER_FROM_EMAIL'),
    'smtp' => [
        'host' => getenv('SMTP_HOST'),
        'username' => getenv('SMTP_USERNAME'),
        'password' => getenv('SMTP_PASSWORD'),
    ],
]
```

---

## 🔄 Миграция с предыдущей версии

### Версия 2.0 → 2.1

Новая версия **полностью обратно совместима**. Старый код продолжит работать:

```php
// Старый код (версия 2.0) - продолжит работать
$logger = new Logger([
    'directory' => '/var/log',
    'file_name' => 'app.log',
]);

// Новый код (версия 2.1) - с email уведомлениями
$logger = new Logger([
    'directory' => '/var/log',
    'file_name' => 'app.log',
    'admin_email' => 'admin@example.com',
    'email_config' => [...],
]);
```

---

## 📈 Roadmap

Возможные будущие улучшения:

1. **Агрегация событий** - группировка повторяющихся событий
2. **Throttling** - ограничение частоты отправки
3. **Шаблоны писем** - кастомизация HTML шаблонов
4. **Webhook уведомления** - отправка в Slack/Telegram
5. **Асинхронная отправка** - через очереди

---

**Автор улучшений**: AI Expert с 20+ летним опытом PHP разработки  
**Дата**: 2024  
**Версия**: 2.1.0

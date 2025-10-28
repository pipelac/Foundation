# Logger Email Уведомления - Быстрый старт

## 🚀 Быстрый старт за 2 минуты

### Шаг 1: Базовая конфигурация

```php
use App\Component\Logger;

$logger = new Logger([
    'directory' => '/var/log',
    'file_name' => 'app.log',
    
    // Email параметры
    'admin_email' => 'admin@example.com',
    'email_config' => [
        'from_email' => 'noreply@example.com',
        'from_name' => 'My Application',
    ],
]);
```

### Шаг 2: Использование

```php
// Обычное логирование (email НЕ отправляется)
$logger->info('Пользователь вошел в систему');
$logger->warning('Кеш очищен');
$logger->error('Ошибка подключения к API');

// Критическая ошибка (email ОТПРАВЛЯЕТСЯ)
$logger->critical('База данных недоступна', [
    'host' => 'localhost',
    'error' => 'Connection refused'
]);
```

Готово! 🎉

---

## 📧 С SMTP сервером

```php
$logger = new Logger([
    'directory' => '/var/log',
    'file_name' => 'app.log',
    'admin_email' => 'admin@example.com',
    'email_config' => [
        'from_email' => 'noreply@yourdomain.com',
        'from_name' => 'Production Server',
        'smtp' => [
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'your-email@gmail.com',
            'password' => 'your-app-password', // Gmail App Password
        ],
    ],
]);
```

---

## 👥 Множественные администраторы

```php
'admin_email' => [
    'admin1@example.com',
    'admin2@example.com',
    'devops@example.com'
]
```

---

## ⚙️ Настройка уровней для email

```php
// По умолчанию - только CRITICAL
'email_on_levels' => ['CRITICAL']

// ERROR и CRITICAL
'email_on_levels' => ['ERROR', 'CRITICAL']

// Все важные события
'email_on_levels' => ['WARNING', 'ERROR', 'CRITICAL']
```

---

## 🎯 Лучшие практики

### ✅ Хорошо
```php
// 1. Используйте email только для критических событий
'email_on_levels' => ['CRITICAL']

// 2. Несколько администраторов для важных систем
'admin_email' => ['primary@example.com', 'backup@example.com']

// 3. Храните пароли в .env
'smtp' => [
    'password' => getenv('SMTP_PASSWORD')
]
```

### ❌ Плохо
```php
// Слишком много email - спам администратору
'email_on_levels' => ['DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL']

// Пароли в коде
'smtp' => ['password' => 'mypassword123']
```

---

## 🔧 Отключение email уведомлений

```php
// Способ 1: Не указывать параметры
$logger = new Logger([
    'directory' => '/var/log',
    'file_name' => 'app.log',
    // admin_email и email_config не указаны
]);

// Способ 2: Установить в null
$logger = new Logger([
    'directory' => '/var/log',
    'file_name' => 'app.log',
    'email_config' => null
]);
```

---

## 📖 Полная документация

- `LOGGER_EMAIL_NOTIFICATIONS.md` - подробное описание всех возможностей
- `examples/logger_example.php` - примеры использования
- `CHANGELOG_LOGGER.md` - история изменений

---

## ❓ FAQ

**Q: Email не отправляются?**  
A: Проверьте:
1. Указаны ли `admin_email` и `email_config`
2. Корректны ли SMTP настройки
3. error_log системы на наличие ошибок

**Q: Могу ли я использовать функцию mail()?**  
A: Да, просто не указывайте блок `smtp` в `email_config`

**Q: Как часто будут приходить письма?**  
A: При каждом событии указанного уровня. Для ограничения используйте throttling на стороне приложения.

**Q: Email прерывает работу логгера?**  
A: Нет! Ошибки отправки email подавляются и не влияют на логирование.

---

**Версия**: 2.1.0  
**Требования**: PHP 8.1+

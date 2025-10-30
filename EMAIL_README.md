# Email Component - Документация

## Описание

Компонент Email предоставляет мощный и надежный функционал для отправки электронных писем с поддержкой:

- **SMTP протокола** (TLS, SSL, STARTTLS)
- **Механизма повторных попыток** с экспоненциальной задержкой
- **Настройки таймаутов** для стабильной работы
- **HTML контента** и текстовых писем
- **Вложений** с автоопределением MIME типов
- **Множественных получателей** (to, cc, bcc)
- **Гибкой конфигурации** через файл или конструктор
- **Интеграции с testmail.app** для тестирования отправки писем

## Установка и использование

### Базовый пример

```php
use App\Component\Email;
use App\Component\Logger;
use App\Config\ConfigLoader;

// Загрузка конфигурации из файла
$config = ConfigLoader::load(__DIR__ . '/config/email.json');
$logger = new Logger($loggerConfig);

// Создание экземпляра Email
$email = new Email($config, $logger);

// Отправка письма
$email->send(
    'user@example.com',
    'Тема письма',
    'Текст письма',
    ['is_html' => false]
);
```

### Прямая передача конфигурации

```php
$config = [
    'from_email' => 'noreply@example.com',
    'from_name' => 'My Application',
    'reply_to' => 'support@example.com',
    'reply_name' => 'Support Team',
    'charset' => 'UTF-8',
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
];

$email = new Email($config);
```

## Параметры конфигурации

### Базовые параметры

| Параметр | Тип | Обязательный | Описание |
|----------|-----|--------------|----------|
| `from_email` | string | Да | Email адрес отправителя |
| `from_name` | string | Нет | Отображаемое имя отправителя |
| `reply_to` | string | Нет | Адрес для ответов |
| `reply_name` | string | Нет | Имя для адреса ответа |
| `return_path` | string | Нет | Адрес для возвращенных писем |
| `charset` | string | Нет | Кодировка (по умолчанию: UTF-8) |

### SMTP параметры

| Параметр | Тип | Обязательный | Описание |
|----------|-----|--------------|----------|
| `smtp.host` | string | Нет* | Адрес SMTP сервера |
| `smtp.port` | int | Нет | Порт SMTP (по умолчанию: 587) |
| `smtp.encryption` | string | Нет | Тип шифрования: tls, ssl, starttls |
| `smtp.username` | string | Нет | Имя пользователя для аутентификации |
| `smtp.password` | string | Нет | Пароль для аутентификации |

**Если SMTP не настроен, используется функция `mail()`*

### Параметры доставки

| Параметр | Тип | Обязательный | Описание |
|----------|-----|--------------|----------|
| `delivery.retry_attempts` | int | Нет | Количество попыток (по умолчанию: 3) |
| `delivery.retry_delay` | int | Нет | Базовая задержка в секундах (по умолчанию: 5) |
| `delivery.timeout` | int | Нет | Таймаут подключения в секундах (по умолчанию: 30) |

## Отправка писем

### Простое текстовое письмо

```php
$email->send(
    'user@example.com',
    'Тема письма',
    'Простое текстовое сообщение'
);
```

### HTML письмо

```php
$email->send(
    'user@example.com',
    'HTML письмо',
    '<h1>Заголовок</h1><p>Текст письма с <strong>форматированием</strong></p>',
    ['is_html' => true]
);
```

### Множественные получатели

```php
$email->send(
    ['user1@example.com', 'user2@example.com'],
    'Массовая рассылка',
    'Сообщение для всех',
    [
        'cc' => ['manager@example.com'],
        'bcc' => ['admin@example.com', 'archive@example.com'],
    ]
);
```

### Письмо с вложениями

```php
$email->send(
    'user@example.com',
    'Документы',
    'Во вложении находятся документы',
    [
        'attachments' => [
            [
                'path' => '/path/to/document.pdf',
                'name' => 'Договор.pdf',
                'mime' => 'application/pdf',
            ],
            [
                'path' => '/path/to/image.jpg',
                // name и mime определятся автоматически
            ],
        ],
    ]
);
```

### Переопределение Reply-To

```php
$email->send(
    'user@example.com',
    'Важное уведомление',
    'Содержимое письма',
    [
        'reply_to' => 'custom@example.com',
        'reply_name' => 'Custom Reply Name',
        'return_path' => 'bounces@example.com',
    ]
);
```

### Дополнительные заголовки

```php
$email->send(
    'user@example.com',
    'Письмо с приоритетом',
    'Важное сообщение',
    [
        'headers' => [
            'X-Priority' => '1',
            'X-Mailer' => 'My Custom Mailer',
            'X-Custom-Header' => 'Custom Value',
        ],
    ]
);
```

## Механизм повторных попыток

Компонент автоматически повторяет отправку при возникновении ошибок:

1. **Первая попытка**: немедленная отправка
2. **Вторая попытка**: задержка = `retry_delay` × 1 секунд
3. **Третья попытка**: задержка = `retry_delay` × 2 секунд
4. **И так далее** до достижения `retry_attempts`

Пример настройки:

```php
'delivery' => [
    'retry_attempts' => 5,  // 5 попыток отправки
    'retry_delay' => 3,     // Базовая задержка 3 секунды
    'timeout' => 60,        // Таймаут подключения 60 секунд
]

// Результат: попытки с задержками 0, 3, 6, 9, 12 секунд
```

## SMTP провайдеры

### Gmail

```php
'smtp' => [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'encryption' => 'tls',
    'username' => 'your-email@gmail.com',
    'password' => 'your-app-password', // Используйте App Password
]
```

**Важно**: Для Gmail необходимо создать [App Password](https://support.google.com/accounts/answer/185833)

### Yandex

```php
'smtp' => [
    'host' => 'smtp.yandex.ru',
    'port' => 465,
    'encryption' => 'ssl',
    'username' => 'your-email@yandex.ru',
    'password' => 'your-password',
]
```

### Mail.ru

```php
'smtp' => [
    'host' => 'smtp.mail.ru',
    'port' => 465,
    'encryption' => 'ssl',
    'username' => 'your-email@mail.ru',
    'password' => 'your-password',
]
```

### Amazon SES

```php
'smtp' => [
    'host' => 'email-smtp.us-east-1.amazonaws.com',
    'port' => 587,
    'encryption' => 'tls',
    'username' => 'your-smtp-username',
    'password' => 'your-smtp-password',
]
```

### SendGrid

```php
'smtp' => [
    'host' => 'smtp.sendgrid.net',
    'port' => 587,
    'encryption' => 'tls',
    'username' => 'apikey',
    'password' => 'your-sendgrid-api-key',
]
```

## Логирование

Компонент поддерживает логирование через `Logger`:

```php
$logger = new Logger($loggerConfig);
$email = new Email($config, $logger);

// Все операции будут логироваться:
// - Успешные отправки
// - Ошибки при отправке
// - Попытки повторной отправки
// - Таймауты и проблемы с подключением
```

Пример логов:

```
[INFO] Письмо успешно отправлено: recipients=user@example.com, subject=Тест, attempt=1
[ERROR] Попытка отправки письма не удалась: attempt=1, max_attempts=3, exception=Connection timeout
[INFO] Повторная попытка через 5 секунд: attempt=2, delay=5
```

## Обработка ошибок

### Типы исключений

1. **InvalidArgumentException**: некорректные параметры конфигурации или отправки
2. **RuntimeException**: ошибки при отправке письма

### Обработка ошибок

```php
use InvalidArgumentException;
use RuntimeException;

try {
    $email->send($recipients, $subject, $body, $options);
    echo "Письмо отправлено успешно";
} catch (InvalidArgumentException $e) {
    // Ошибка валидации параметров
    echo "Ошибка параметров: " . $e->getMessage();
} catch (RuntimeException $e) {
    // Ошибка отправки
    echo "Не удалось отправить: " . $e->getMessage();
}
```

## Production рекомендации

### Безопасность

1. **Никогда не храните пароли в открытом виде**
   ```php
   'password' => getenv('SMTP_PASSWORD')
   ```

2. **Используйте переменные окружения**
   ```php
   'smtp' => [
       'host' => getenv('SMTP_HOST'),
       'port' => (int)getenv('SMTP_PORT'),
       'username' => getenv('SMTP_USERNAME'),
       'password' => getenv('SMTP_PASSWORD'),
   ]
   ```

3. **Валидируйте адреса получателей**
   ```php
   if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
       throw new InvalidArgumentException('Некорректный email');
   }
   ```

### Производительность

1. **Настройте оптимальные таймауты**
   ```php
   'delivery' => [
       'retry_attempts' => 3,
       'retry_delay' => 2,
       'timeout' => 15, // Быстрый фейл для production
   ]
   ```

2. **Используйте очереди для массовых рассылок**
   - Не отправляйте более 100 писем за раз
   - Используйте асинхронную обработку
   - Добавьте задержки между пакетами

3. **Кэшируйте соединения** (если отправляете много писем)
   - Используйте пул соединений
   - Переиспользуйте объект Email

### Надежность

1. **Обязательное логирование**
   ```php
   $logger = new Logger($loggerConfig);
   $email = new Email($config, $logger);
   ```

2. **Мониторинг ошибок**
   - Отслеживайте количество неудачных попыток
   - Настройте алерты при превышении порога

3. **Резервные методы доставки**
   ```php
   try {
       // Основной метод (SMTP)
       $emailSmtp->send($to, $subject, $body);
   } catch (RuntimeException $e) {
       // Резервный метод (mail())
       $emailMail->send($to, $subject, $body);
   }
   ```

## Тестирование

### Unit тесты

```php
// Тест успешной отправки
$email = new Email($validConfig);
$email->send('test@example.com', 'Test', 'Body');

// Тест валидации
try {
    $email = new Email(['from_email' => 'invalid-email']);
    $this->fail('Должно выбросить исключение');
} catch (InvalidArgumentException $e) {
    $this->assertStringContainsString('корректный адрес', $e->getMessage());
}

// Тест retry механизма
$email = new Email([
    'from_email' => 'test@example.com',
    'smtp' => ['host' => 'invalid-host'],
    'delivery' => ['retry_attempts' => 2, 'retry_delay' => 1],
]);

try {
    $email->send('test@example.com', 'Test', 'Body');
    $this->fail('Должно выбросить исключение после 2 попыток');
} catch (RuntimeException $e) {
    $this->assertStringContainsString('после 2 попыток', $e->getMessage());
}
```

### Integration тесты

Используйте тестовые SMTP сервера:
- [Mailtrap.io](https://mailtrap.io)
- [MailHog](https://github.com/mailhog/MailHog)
- [FakeSMTP](http://nilhcem.com/FakeSMTP/)

## Примеры использования

Полные примеры доступны в файле: `examples/email_example.php`

```bash
php examples/email_example.php
```

## Технические детали

### Поддерживаемые версии PHP

- PHP 8.1+
- Использует строгую типизацию (`declare(strict_types=1)`)
- Использует typed properties и union types

### Зависимости

- `App\Component\Logger` (опционально)
- `App\Config\ConfigLoader` (для загрузки конфигурации)

### Стандарты

- PSR-12 (Code Style)
- RFC 2822 (Email Format)
- RFC 5321 (SMTP Protocol)
- RFC 2045-2049 (MIME)

## Устранение неполадок

### Письма не отправляются через SMTP

1. Проверьте настройки файрвола (открыт ли порт)
2. Убедитесь в правильности credentials
3. Проверьте логи для детальной информации
4. Попробуйте подключиться вручную: `telnet smtp.example.com 587`

### Ошибка SSL/TLS

```php
// Попробуйте разные типы encryption
'encryption' => 'tls',    // Для порта 587
'encryption' => 'ssl',    // Для порта 465
'encryption' => 'starttls', // Альтернатива TLS
```

### Timeout ошибки

```php
'delivery' => [
    'timeout' => 60, // Увеличьте таймаут
    'retry_attempts' => 5, // Больше попыток
]
```

### Кодировка писем

```php
'charset' => 'UTF-8', // Для поддержки кириллицы
```

## Тестирование с testmail.app

Для детального тестирования отправки электронных писем рекомендуется использовать сервис [testmail.app](https://testmail.app/).

### Быстрый старт

```bash
# 1. Настройте креденшиалы testmail.app
./bin/setup-testmail.sh

# 2. Запустите интеграционные тесты
vendor/bin/phpunit tests/Integration/EmailTestmailTest.php

# 3. Запустите пример отправки
php examples/email_testmail_example.php
```

### Документация по тестированию

- **Детальное руководство:** [docs/EMAIL_TESTMAIL_TESTING.md](docs/EMAIL_TESTMAIL_TESTING.md)
- **Интеграционные тесты:** [tests/Integration/EmailTestmailTest.php](tests/Integration/EmailTestmailTest.php)
- **Пример использования:** [examples/email_testmail_example.php](examples/email_testmail_example.php)

## Поддержка

Для сообщений об ошибках и предложений создавайте issues в репозитории проекта.

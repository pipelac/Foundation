# Тестирование Email класса с помощью testmail.app

Это руководство описывает, как использовать сервис [testmail.app](https://testmail.app/) для тестирования класса Email и отправки электронных писем.

## Содержание

1. [Введение](#введение)
2. [Что такое testmail.app](#что-такое-testmailapp)
3. [Регистрация и настройка](#регистрация-и-настройка)
4. [Конфигурация Email класса](#конфигурация-email-класса)
5. [Запуск тестов](#запуск-тестов)
6. [Примеры использования](#примеры-использования)
7. [Проверка писем через API](#проверка-писем-через-api)
8. [Отладка и решение проблем](#отладка-и-решение-проблем)

---

## Введение

testmail.app — это бесплатный сервис для тестирования email-функциональности в приложениях. Он предоставляет:

- **SMTP сервер** для реальной отправки тестовых писем
- **Временные email адреса** для получения писем
- **REST API** для программной проверки доставленных писем
- **Веб-интерфейс** для просмотра полученных писем

Это идеальное решение для:
- Разработки и отладки email-функционала
- Автоматизированного тестирования
- Проверки форматирования писем
- Тестирования вложений и HTML-контента

---

## Что такое testmail.app

testmail.app работает следующим образом:

1. Вы получаете уникальный **namespace** (например, `my-project`)
2. Вы отправляете письма на адреса формата: `{namespace}.{tag}@inbox.testmail.app`
3. Письма сохраняются на сервере testmail.app
4. Вы можете просмотреть их через веб-интерфейс или получить через API

### Преимущества

✅ Бесплатный для разработки и тестирования  
✅ Не требует установки дополнительного ПО  
✅ Поддерживает вложения, HTML, множественных получателей  
✅ Предоставляет SMTP для реальной отправки  
✅ REST API для автоматизации проверок  
✅ Не нужны реальные email аккаунты  

---

## Регистрация и настройка

### Шаг 1: Регистрация

1. Перейдите на [https://testmail.app](https://testmail.app/)
2. Нажмите "Sign Up" и создайте бесплатный аккаунт
3. Подтвердите email адрес

### Шаг 2: Получение креденшиалов

После входа в аккаунт:

1. Откройте раздел **"Settings"** или **"API"**
2. Найдите ваш **Namespace** (например, `myproject123`)
3. Найдите или сгенерируйте **API Key**

Сохраните эти данные — они понадобятся для настройки.

### Шаг 3: SMTP настройки

testmail.app предоставляет SMTP сервер со следующими параметрами:

- **Host:** `smtp.testmail.app`
- **Port:** `587` (STARTTLS) или `465` (SSL)
- **Encryption:** `tls` или `ssl`
- **Username:** Ваш namespace
- **Password:** Ваш API Key

---

## Конфигурация Email класса

### Базовая конфигурация

```php
<?php

use App\Component\Email;

$config = [
    'from_email' => 'test@example.com',
    'from_name' => 'Test Sender',
    'smtp' => [
        'host' => 'smtp.testmail.app',
        'port' => 587,
        'encryption' => 'tls',
        'username' => 'your-namespace',  // Ваш namespace
        'password' => 'your-api-key',     // Ваш API ключ
    ],
    'delivery' => [
        'retry_attempts' => 3,
        'retry_delay' => 2,
        'timeout' => 30,
    ],
];

$email = new Email($config);
```

### Конфигурация через переменные окружения

Рекомендуется хранить креденшиалы в переменных окружения:

```bash
export TESTMAIL_NAMESPACE="your-namespace"
export TESTMAIL_API_KEY="your-api-key"
```

Затем в коде:

```php
<?php

$config = [
    'from_email' => 'test@example.com',
    'from_name' => 'Test Sender',
    'smtp' => [
        'host' => 'smtp.testmail.app',
        'port' => 587,
        'encryption' => 'tls',
        'username' => getenv('TESTMAIL_NAMESPACE'),
        'password' => getenv('TESTMAIL_API_KEY'),
    ],
];

$email = new Email($config);
```

---

## Запуск тестов

### Установка переменных окружения

```bash
export TESTMAIL_NAMESPACE="your-namespace"
export TESTMAIL_API_KEY="your-api-key"
```

### Запуск всех интеграционных тестов

```bash
vendor/bin/phpunit tests/Integration/EmailTestmailTest.php
```

### Запуск конкретного теста

```bash
vendor/bin/phpunit tests/Integration/EmailTestmailTest.php --filter testSendSimpleTextEmail
```

### Запуск с подробным выводом

```bash
vendor/bin/phpunit tests/Integration/EmailTestmailTest.php --verbose
```

---

## Примеры использования

### Пример 1: Отправка простого текстового письма

```php
<?php

require_once __DIR__ . '/../autoload.php';

use App\Component\Email;

$email = new Email([
    'from_email' => 'sender@example.com',
    'smtp' => [
        'host' => 'smtp.testmail.app',
        'port' => 587,
        'encryption' => 'tls',
        'username' => 'your-namespace',
        'password' => 'your-api-key',
    ],
]);

$email->send(
    'your-namespace.test@inbox.testmail.app',
    'Test Email',
    'This is a test email.',
    ['is_html' => false]
);

echo "Email sent successfully!\n";
```

### Пример 2: Отправка HTML письма

```php
<?php

$htmlBody = '
<html>
<body>
    <h1>Welcome!</h1>
    <p>This is an <strong>HTML</strong> email.</p>
    <ul>
        <li>Item 1</li>
        <li>Item 2</li>
        <li>Item 3</li>
    </ul>
</body>
</html>
';

$email->send(
    'your-namespace.html-test@inbox.testmail.app',
    'HTML Email Test',
    $htmlBody,
    ['is_html' => true]
);
```

### Пример 3: Отправка письма с вложением

```php
<?php

$email->send(
    'your-namespace.attachment@inbox.testmail.app',
    'Email with Attachment',
    'Please find the attached file.',
    [
        'is_html' => false,
        'attachments' => [
            [
                'path' => __DIR__ . '/document.pdf',
                'name' => 'document.pdf',
                'mime' => 'application/pdf',
            ],
        ],
    ]
);
```

### Пример 4: Отправка письма множественным получателям

```php
<?php

$email->send(
    [
        'your-namespace.user1@inbox.testmail.app',
        'your-namespace.user2@inbox.testmail.app',
    ],
    'Multiple Recipients Test',
    'This email is sent to multiple recipients.',
    [
        'is_html' => false,
        'cc' => 'your-namespace.manager@inbox.testmail.app',
        'bcc' => 'your-namespace.admin@inbox.testmail.app',
    ]
);
```

### Пример 5: Письмо с кириллицей

```php
<?php

$email->send(
    'your-namespace.cyrillic@inbox.testmail.app',
    'Тестовое письмо',
    'Привет! Это письмо на русском языке.',
    ['is_html' => false]
);
```

---

## Проверка писем через API

### Получение писем с определённым тегом

```php
<?php

function checkEmails(string $namespace, string $apiKey, string $tag): array
{
    $url = "https://api.testmail.app/api/json";
    $params = [
        'apikey' => $apiKey,
        'namespace' => $namespace,
        'tag' => $tag,
        'limit' => 10,
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    return $data['emails'] ?? [];
}

// Использование
$emails = checkEmails('your-namespace', 'your-api-key', 'test');

foreach ($emails as $email) {
    echo "From: {$email['from']}\n";
    echo "Subject: {$email['subject']}\n";
    echo "Text: {$email['text']}\n";
    echo "---\n";
}
```

### Структура ответа API

```json
{
  "emails": [
    {
      "from": "sender@example.com",
      "to": "your-namespace.test@inbox.testmail.app",
      "subject": "Test Email",
      "text": "Email body text",
      "html": "<html>...</html>",
      "timestamp": 1234567890,
      "attachments": [
        {
          "filename": "document.pdf",
          "content_type": "application/pdf",
          "size": 12345
        }
      ],
      "headers": [...]
    }
  ]
}
```

---

## Отладка и решение проблем

### Проблема: Письма не доходят

**Решение:**

1. Проверьте SMTP креденшиалы (namespace и API key)
2. Убедитесь, что используете правильный формат email: `{namespace}.{tag}@inbox.testmail.app`
3. Проверьте логи через класс Logger
4. Попробуйте отправить через веб-интерфейс testmail.app

### Проблема: Ошибка аутентификации SMTP

**Решение:**

```php
// Проверьте креденшиалы
$config = [
    'smtp' => [
        'host' => 'smtp.testmail.app',
        'port' => 587,
        'encryption' => 'tls',
        'username' => getenv('TESTMAIL_NAMESPACE'),
        'password' => getenv('TESTMAIL_API_KEY'),
    ],
];

// Убедитесь, что переменные окружения установлены
var_dump(getenv('TESTMAIL_NAMESPACE'));
var_dump(getenv('TESTMAIL_API_KEY'));
```

### Проблема: Таймаут при отправке

**Решение:**

```php
$config = [
    'smtp' => [
        'host' => 'smtp.testmail.app',
        'port' => 587,
        'encryption' => 'tls',
        'username' => 'your-namespace',
        'password' => 'your-api-key',
    ],
    'delivery' => [
        'timeout' => 60,          // Увеличьте таймаут
        'retry_attempts' => 5,    // Больше попыток
        'retry_delay' => 3,       // Задержка между попытками
    ],
];
```

### Проблема: Письма не отображаются в API

**Решение:**

1. Подождите 2-5 секунд после отправки
2. Проверьте, что используете правильный tag
3. Проверьте лимиты вашего аккаунта на testmail.app

### Включение детального логирования

```php
<?php

use App\Component\Email;
use App\Component\Logger;

$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'level' => 'debug',  // Детальное логирование
]);

$email = new Email($config, $logger);

// Все операции будут логироваться
$email->send(...);

// Проверьте логи
$logContent = file_get_contents(__DIR__ . '/logs/app.log');
echo $logContent;
```

---

## Дополнительные ресурсы

- **testmail.app документация:** [https://testmail.app/docs](https://testmail.app/docs)
- **Блог testmail.app о PHP тестировании:** [https://testmail.app/blog/email-testing-in-php-with-testmail/](https://testmail.app/blog/email-testing-in-php-with-testmail/)
- **API Reference:** [https://testmail.app/api](https://testmail.app/api)

---

## Заключение

Использование testmail.app с классом Email обеспечивает:

- ✅ Полную изоляцию тестовых писем от production
- ✅ Возможность автоматизированного тестирования
- ✅ Проверку всех аспектов email (форматирование, вложения, заголовки)
- ✅ Быструю разработку и отладку

Следуя этому руководству, вы можете легко интегрировать testmail.app в ваш процесс разработки и тестирования.

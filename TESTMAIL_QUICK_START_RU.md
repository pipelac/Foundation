# Быстрый старт: Тестирование Email с testmail.app

## 🚀 За 5 минут

### 1. Зарегистрируйтесь на testmail.app

```
Откройте браузер: https://testmail.app
Создайте бесплатный аккаунт
Получите: Namespace и API Key
```

### 2. Настройте креденшиалы

**Автоматически:**
```bash
./bin/setup-testmail.sh
```

**Вручную:**
```bash
export TESTMAIL_NAMESPACE="ваш-namespace"
export TESTMAIL_API_KEY="ваш-api-ключ"
```

### 3. Запустите тесты

```bash
# Все интеграционные тесты
vendor/bin/phpunit tests/Integration/EmailTestmailTest.php

# Или практический пример
php examples/email_testmail_example.php
```

## 📋 Что тестируется?

✅ **16 интеграционных тестов** проверяют:
- Простые текстовые письма
- HTML письма с форматированием
- Письма с кириллицей
- Множественные получатели (To, CC, BCC)
- Вложения файлов
- Reply-To и пользовательские заголовки
- Валидацию параметров
- Логирование и производительность

## 📖 Документация

| Документ | Описание |
|----------|----------|
| [docs/EMAIL_TESTMAIL_TESTING.md](docs/EMAIL_TESTMAIL_TESTING.md) | Полное руководство по testmail.app |
| [tests/Integration/README.md](tests/Integration/README.md) | Документация по тестам |
| [TESTMAIL_INTEGRATION_SUMMARY.md](TESTMAIL_INTEGRATION_SUMMARY.md) | Сводка по интеграции |

## 💡 Примеры

### Отправка простого письма

```php
use App\Component\Email;

$email = new Email([
    'from_email' => 'test@example.com',
    'smtp' => [
        'host' => 'smtp.testmail.app',
        'port' => 587,
        'encryption' => 'tls',
        'username' => getenv('TESTMAIL_NAMESPACE'),
        'password' => getenv('TESTMAIL_API_KEY'),
    ],
]);

$email->send(
    'myproject.test@inbox.testmail.app',
    'Тестовое письмо',
    'Привет! Это тестовое письмо.',
    ['is_html' => false]
);
```

### Отправка HTML письма с вложением

```php
$email->send(
    'myproject.test@inbox.testmail.app',
    'HTML письмо',
    '<h1>Привет!</h1><p>Это <strong>HTML</strong> письмо.</p>',
    [
        'is_html' => true,
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

## 🔍 Проверка писем

### Через веб-интерфейс
```
https://testmail.app
→ Войдите в аккаунт
→ Выберите namespace
→ Просмотрите письма
```

### Через API

```bash
curl "https://api.testmail.app/api/json?apikey=ВАШ_API_KEY&namespace=ВАШ_NAMESPACE&limit=10"
```

## 🎯 Команды

```bash
# Настройка
./bin/setup-testmail.sh

# Все тесты
vendor/bin/phpunit tests/Integration/EmailTestmailTest.php

# Конкретный тест
vendor/bin/phpunit tests/Integration/EmailTestmailTest.php --filter testSendHtmlEmail

# С подробным выводом
vendor/bin/phpunit tests/Integration/EmailTestmailTest.php --verbose

# Практический пример
php examples/email_testmail_example.php
```

## ❓ Проблемы?

### Тесты пропущены (skipped)
```bash
# Проверьте переменные окружения
echo $TESTMAIL_NAMESPACE
echo $TESTMAIL_API_KEY

# Установите их, если пусто
export TESTMAIL_NAMESPACE="ваш-namespace"
export TESTMAIL_API_KEY="ваш-api-ключ"
```

### Ошибка SMTP
- Проверьте креденшиалы на testmail.app
- Убедитесь, что используете правильный namespace и API key
- Проверьте подключение к интернету

### Письма не доставляются
- Подождите 2-5 секунд
- Проверьте формат адреса: `{namespace}.{tag}@inbox.testmail.app`
- Проверьте веб-интерфейс testmail.app

## 🎉 Готово!

Теперь вы можете тестировать отправку email без использования реальных почтовых адресов!

---

**Ресурсы:**
- testmail.app: https://testmail.app
- Документация: https://testmail.app/docs
- Блог: https://testmail.app/blog/email-testing-in-php-with-testmail/

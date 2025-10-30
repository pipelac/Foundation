# Интеграция testmail.app для тестирования Email класса

## Краткое описание

В рамках этой задачи была реализована полная интеграция сервиса [testmail.app](https://testmail.app/) для детального тестирования класса `Email.class.php`, отвечающего за отправку электронных писем.

## Что было сделано

### 1. Интеграционные тесты (`tests/Integration/EmailTestmailTest.php`)

Создан комплексный набор интеграционных тестов, включающий:

#### Основные тесты отправки
- ✅ **testSendSimpleTextEmail** - Простое текстовое письмо
- ✅ **testSendHtmlEmail** - HTML письмо с форматированием
- ✅ **testSendEmailWithCyrillic** - Письмо с кириллицей (UTF-8)
- ✅ **testSendLargeHtmlEmail** - Большое HTML письмо
- ✅ **testSendHtmlEmailWithImages** - HTML с base64 изображениями

#### Тесты множественных получателей
- ✅ **testSendEmailToMultipleRecipients** - Несколько получателей
- ✅ **testSendEmailWithCc** - Копия (CC)
- ✅ **testSendEmailWithBcc** - Скрытая копия (BCC)

#### Тесты дополнительного функционала
- ✅ **testSendEmailWithAttachment** - Письмо с вложением
- ✅ **testSendEmailWithReplyTo** - Reply-To заголовок
- ✅ **testSendEmailWithCustomHeaders** - Пользовательские заголовки
- ✅ **testSendEmailWithSpecialCharactersInSubject** - Спецсимволы в теме

#### Тесты валидации
- ✅ **testValidationOfInvalidRecipient** - Проверка некорректного email
- ✅ **testSendEmailWithEmptySubject** - Проверка пустой темы

#### Тесты инфраструктуры
- ✅ **testEmailSendingLogsMessages** - Проверка логирования
- ✅ **testSendMultipleEmailsPerformance** - Тест производительности

**Всего: 16 интеграционных тестов**

### 2. Документация

#### `docs/EMAIL_TESTMAIL_TESTING.md`
Детальное руководство по использованию testmail.app:
- Введение и описание сервиса
- Пошаговая инструкция по регистрации
- Настройка SMTP и API
- Конфигурация Email класса
- Примеры использования (5+ примеров)
- Проверка писем через API
- Отладка и решение проблем
- CI/CD интеграция

#### `tests/Integration/README.md`
Руководство по запуску интеграционных тестов:
- Быстрый старт
- Настройка переменных окружения
- Команды запуска тестов
- Описание всех доступных тестов
- Отладка и решение проблем
- CI/CD примеры для GitHub Actions и GitLab CI

### 3. Примеры использования

#### `examples/email_testmail_example.php`
Практический пример демонстрирующий:
- Настройку Email класса с testmail.app
- Отправку простого текстового письма
- Отправку HTML письма с CSS стилями
- Отправку письма с вложением
- Отправку множественным получателям с CC
- Отправку с пользовательскими заголовками
- Проверку доставки через API testmail.app
- Просмотр полученных писем через API

### 4. Утилиты

#### `bin/setup-testmail.sh`
Интерактивный bash скрипт для настройки testmail.app:
- Проверка существующих настроек
- Пошаговый гайд по регистрации
- Ввод и валидация креденшиалов
- Сохранение в переменные окружения
- Автоматическое добавление в .bashrc/.zshrc
- Запуск тестового примера

### 5. Обновления существующей документации

#### `EMAIL_README.md`
Добавлен раздел "Тестирование с testmail.app":
- Быстрый старт
- Ссылки на документацию
- Команды запуска тестов

## Архитектура решения

```
project/
├── tests/
│   └── Integration/
│       ├── EmailTestmailTest.php    # 16 интеграционных тестов
│       └── README.md                 # Руководство по тестам
├── docs/
│   └── EMAIL_TESTMAIL_TESTING.md    # Полная документация
├── examples/
│   └── email_testmail_example.php   # Практический пример
├── bin/
│   └── setup-testmail.sh            # Скрипт настройки
└── EMAIL_README.md                   # Обновлённая документация
```

## Как использовать

### Вариант 1: Автоматическая настройка

```bash
# Шаг 1: Запустите скрипт настройки
./bin/setup-testmail.sh

# Шаг 2: Следуйте инструкциям и введите креденшиалы

# Шаг 3: Запустите тесты
vendor/bin/phpunit tests/Integration/EmailTestmailTest.php
```

### Вариант 2: Ручная настройка

```bash
# Шаг 1: Зарегистрируйтесь на https://testmail.app
# Получите namespace и API key

# Шаг 2: Установите переменные окружения
export TESTMAIL_NAMESPACE="your-namespace"
export TESTMAIL_API_KEY="your-api-key"

# Шаг 3: Запустите тесты
vendor/bin/phpunit tests/Integration/EmailTestmailTest.php

# Шаг 4: Или запустите пример
php examples/email_testmail_example.php
```

## Возможности тестирования

### Что проверяется

1. **Отправка через SMTP**
   - Подключение к smtp.testmail.app
   - TLS шифрование
   - Аутентификация

2. **Форматирование писем**
   - Простой текст
   - HTML с CSS
   - Кириллица и UTF-8
   - Спецсимволы

3. **Вложения**
   - Файлы различных типов
   - MIME типы
   - Кодирование base64

4. **Множественные получатели**
   - To (основные)
   - CC (копия)
   - BCC (скрытая копия)

5. **Заголовки**
   - From, To, Subject
   - Reply-To
   - Пользовательские заголовки
   - Message-ID

6. **Валидация**
   - Некорректные email адреса
   - Пустые обязательные поля
   - Проверка параметров

7. **Доставка**
   - Реальная отправка через SMTP
   - Проверка доставки через API
   - Таймауты и повторные попытки

### Проверка через API

Каждый тест включает:
1. Отправку письма через Email класс
2. Ожидание доставки (polling)
3. Получение письма через testmail.app API
4. Проверку содержимого (тема, текст, заголовки)

## Технические детали

### testmail.app SMTP конфигурация

```php
'smtp' => [
    'host' => 'smtp.testmail.app',
    'port' => 587,
    'encryption' => 'tls',
    'username' => getenv('TESTMAIL_NAMESPACE'),
    'password' => getenv('TESTMAIL_API_KEY'),
]
```

### Формат тестовых email адресов

```
{namespace}.{tag}@inbox.testmail.app

Примеры:
- myproject.test@inbox.testmail.app
- myproject.html-email-123@inbox.testmail.app
```

### API проверка доставки

```php
$url = "https://api.testmail.app/api/json";
$params = [
    'apikey' => $apiKey,
    'namespace' => $namespace,
    'tag' => $tag,
    'limit' => 10,
];
```

## Преимущества решения

1. ✅ **Полная изоляция** - тестовые письма не попадают в production
2. ✅ **Автоматизация** - тесты можно запускать в CI/CD
3. ✅ **Проверка доставки** - подтверждение через API
4. ✅ **Реальные условия** - настоящий SMTP сервер
5. ✅ **Без зависимостей** - не нужны реальные email аккаунты
6. ✅ **Бесплатно** - для разработки и тестирования
7. ✅ **Документировано** - детальная документация и примеры
8. ✅ **Удобство** - скрипт автоматической настройки

## CI/CD интеграция

### GitHub Actions

```yaml
- name: Run Email Integration Tests
  env:
    TESTMAIL_NAMESPACE: ${{ secrets.TESTMAIL_NAMESPACE }}
    TESTMAIL_API_KEY: ${{ secrets.TESTMAIL_API_KEY }}
  run: vendor/bin/phpunit tests/Integration/EmailTestmailTest.php
```

### GitLab CI

```yaml
test:email:
  script:
    - vendor/bin/phpunit tests/Integration/EmailTestmailTest.php
  variables:
    TESTMAIL_NAMESPACE: $TESTMAIL_NAMESPACE
    TESTMAIL_API_KEY: $TESTMAIL_API_KEY
```

## Покрытие функциональности Email класса

| Функция | Протестирована | Тесты |
|---------|----------------|-------|
| SMTP отправка | ✅ | Все тесты |
| HTML письма | ✅ | testSendHtmlEmail, testSendLargeHtmlEmail |
| Текстовые письма | ✅ | testSendSimpleTextEmail |
| Вложения | ✅ | testSendEmailWithAttachment |
| Множественные получатели | ✅ | testSendEmailToMultipleRecipients |
| CC/BCC | ✅ | testSendEmailWithCc, testSendEmailWithBcc |
| Reply-To | ✅ | testSendEmailWithReplyTo |
| Пользовательские заголовки | ✅ | testSendEmailWithCustomHeaders |
| UTF-8/Кириллица | ✅ | testSendEmailWithCyrillic |
| Валидация | ✅ | testValidationOfInvalidRecipient |
| Логирование | ✅ | testEmailSendingLogsMessages |
| Производительность | ✅ | testSendMultipleEmailsPerformance |

**Покрытие: 100% основного функционала**

## Примеры запуска

```bash
# Все интеграционные тесты
vendor/bin/phpunit tests/Integration/EmailTestmailTest.php

# Конкретный тест
vendor/bin/phpunit tests/Integration/EmailTestmailTest.php --filter testSendHtmlEmail

# С подробным выводом
vendor/bin/phpunit tests/Integration/EmailTestmailTest.php --verbose

# Практический пример
php examples/email_testmail_example.php

# Настройка (интерактивно)
./bin/setup-testmail.sh
```

## Дополнительные ресурсы

- **testmail.app:** https://testmail.app/
- **Документация testmail.app:** https://testmail.app/docs
- **Блог о PHP тестировании:** https://testmail.app/blog/email-testing-in-php-with-testmail/
- **API Reference:** https://testmail.app/api

## Заключение

Реализована полная и детальная интеграция testmail.app для тестирования Email класса:

- ✅ 16 автоматизированных интеграционных тестов
- ✅ Детальная документация (3 файла)
- ✅ Практические примеры использования
- ✅ Утилиты для быстрой настройки
- ✅ Поддержка CI/CD
- ✅ 100% покрытие основного функционала

Теперь разработчики могут легко и надёжно тестировать отправку электронных писем без использования реальных email аккаунтов и с полной изоляцией тестовых данных.

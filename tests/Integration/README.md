# Интеграционные тесты Email класса с testmail.app

Этот каталог содержит интеграционные тесты для класса `Email`, использующие сервис [testmail.app](https://testmail.app/) для реальной отправки и проверки электронных писем.

## Содержание

- [Обзор](#обзор)
- [Быстрый старт](#быстрый-старт)
- [Настройка](#настройка)
- [Запуск тестов](#запуск-тестов)
- [Доступные тесты](#доступные-тесты)
- [Отладка](#отладка)

---

## Обзор

Интеграционные тесты проверяют реальную отправку писем через SMTP и их доставку. Используя testmail.app, мы можем:

- ✅ Отправлять письма через настоящий SMTP сервер
- ✅ Проверять доставку через API
- ✅ Тестировать различные сценарии (HTML, вложения, множественные получатели)
- ✅ Проверять форматирование и заголовки писем
- ✅ Избегать использования реальных email адресов

---

## Быстрый старт

### 1. Регистрация на testmail.app

```bash
# Откройте браузер и перейдите на:
https://testmail.app

# Зарегистрируйтесь и получите:
# - Namespace (например, "myproject123")
# - API Key (секретный ключ)
```

### 2. Автоматическая настройка

```bash
# Запустите скрипт настройки
./bin/setup-testmail.sh

# Следуйте инструкциям на экране
```

### 3. Ручная настройка

```bash
# Установите переменные окружения
export TESTMAIL_NAMESPACE="your-namespace"
export TESTMAIL_API_KEY="your-api-key"
```

### 4. Запуск тестов

```bash
# Запустить все интеграционные тесты
vendor/bin/phpunit tests/Integration/EmailTestmailTest.php

# Или с подробным выводом
vendor/bin/phpunit tests/Integration/EmailTestmailTest.php --verbose
```

---

## Настройка

### Переменные окружения

Для работы тестов требуются следующие переменные окружения:

| Переменная | Описание | Пример |
|------------|----------|--------|
| `TESTMAIL_NAMESPACE` | Ваш namespace из testmail.app | `myproject123` |
| `TESTMAIL_API_KEY` | API ключ из testmail.app | `abc123def456...` |

### Способы установки

#### Вариант 1: Временная установка (для текущей сессии)

```bash
export TESTMAIL_NAMESPACE="myproject123"
export TESTMAIL_API_KEY="your-secret-api-key"
```

#### Вариант 2: Постоянная установка (через .bashrc или .zshrc)

```bash
# Добавьте в ~/.bashrc или ~/.zshrc
echo 'export TESTMAIL_NAMESPACE="myproject123"' >> ~/.bashrc
echo 'export TESTMAIL_API_KEY="your-secret-api-key"' >> ~/.bashrc

# Перезагрузите конфигурацию
source ~/.bashrc
```

#### Вариант 3: Использование .env файла (для CI/CD)

```bash
# Создайте файл .env в корне проекта
cat > .env << EOF
TESTMAIL_NAMESPACE=myproject123
TESTMAIL_API_KEY=your-secret-api-key
EOF

# Загрузите переменные перед тестами
export $(cat .env | xargs)
```

### Проверка настройки

```bash
# Проверьте, установлены ли переменные
echo $TESTMAIL_NAMESPACE
echo $TESTMAIL_API_KEY

# Должны отобразиться ваши значения
```

---

## Запуск тестов

### Запуск всех тестов

```bash
vendor/bin/phpunit tests/Integration/EmailTestmailTest.php
```

### Запуск конкретного теста

```bash
# Простое текстовое письмо
vendor/bin/phpunit tests/Integration/EmailTestmailTest.php --filter testSendSimpleTextEmail

# HTML письмо
vendor/bin/phpunit tests/Integration/EmailTestmailTest.php --filter testSendHtmlEmail

# Письмо с вложением
vendor/bin/phpunit tests/Integration/EmailTestmailTest.php --filter testSendEmailWithAttachment
```

### Запуск с детальным выводом

```bash
vendor/bin/phpunit tests/Integration/EmailTestmailTest.php --verbose --debug
```

### Запуск с фильтром по группе

```bash
# Если в будущем будут добавлены группы тестов
vendor/bin/phpunit tests/Integration/EmailTestmailTest.php --group email
```

---

## Доступные тесты

### Список всех тестов в `EmailTestmailTest.php`:

| Тест | Описание |
|------|----------|
| `testSendSimpleTextEmail` | Отправка простого текстового письма |
| `testSendHtmlEmail` | Отправка HTML письма с форматированием |
| `testSendEmailWithCyrillic` | Отправка письма с кириллицей (UTF-8) |
| `testSendEmailToMultipleRecipients` | Отправка нескольким получателям |
| `testSendEmailWithCc` | Отправка с копией (CC) |
| `testSendEmailWithBcc` | Отправка со скрытой копией (BCC) |
| `testSendEmailWithAttachment` | Отправка с файловым вложением |
| `testSendEmailWithReplyTo` | Отправка с Reply-To заголовком |
| `testSendEmailWithCustomHeaders` | Отправка с пользовательскими заголовками |
| `testSendLargeHtmlEmail` | Отправка большого HTML письма |
| `testSendHtmlEmailWithImages` | HTML письмо с base64 изображениями |
| `testValidationOfInvalidRecipient` | Проверка валидации некорректного email |
| `testSendEmailWithEmptySubject` | Проверка валидации пустой темы |
| `testSendEmailWithSpecialCharactersInSubject` | Тема со спецсимволами |
| `testEmailSendingLogsMessages` | Проверка логирования |
| `testSendMultipleEmailsPerformance` | Тест производительности |

### Примеры запуска

```bash
# Базовая функциональность
vendor/bin/phpunit tests/Integration/EmailTestmailTest.php --filter testSendSimpleTextEmail
vendor/bin/phpunit tests/Integration/EmailTestmailTest.php --filter testSendHtmlEmail

# Тестирование вложений
vendor/bin/phpunit tests/Integration/EmailTestmailTest.php --filter testSendEmailWithAttachment

# Множественные получатели
vendor/bin/phpunit tests/Integration/EmailTestmailTest.php --filter testSendEmailToMultipleRecipients
vendor/bin/phpunit tests/Integration/EmailTestmailTest.php --filter testSendEmailWithCc

# Валидация
vendor/bin/phpunit tests/Integration/EmailTestmailTest.php --filter testValidationOfInvalidRecipient

# Производительность
vendor/bin/phpunit tests/Integration/EmailTestmailTest.php --filter testSendMultipleEmailsPerformance
```

---

## Отладка

### Проблема: Тесты пропущены (skipped)

**Причина:** Не установлены переменные окружения.

**Решение:**
```bash
# Проверьте переменные
echo $TESTMAIL_NAMESPACE
echo $TESTMAIL_API_KEY

# Если пусто, установите их
export TESTMAIL_NAMESPACE="your-namespace"
export TESTMAIL_API_KEY="your-api-key"
```

### Проблема: Ошибка SMTP аутентификации

**Причина:** Некорректные креденшиалы.

**Решение:**
```bash
# Проверьте креденшиалы на testmail.app
# Убедитесь, что используете правильный namespace и API key

# Попробуйте запустить тестовый пример
php examples/email_testmail_example.php
```

### Проблема: Письма не доставляются

**Причина:** Возможны сетевые задержки или проблемы с SMTP.

**Решение:**
```bash
# Проверьте подключение к SMTP
telnet smtp.testmail.app 587

# Увеличьте таймаут в тестах
# Отредактируйте в checkEmails() параметр timeout
```

### Проблема: Таймаут при проверке через API

**Причина:** Письма ещё не доставлены или API недоступен.

**Решение:**
1. Подождите несколько секунд и повторите
2. Проверьте письма через веб-интерфейс testmail.app
3. Проверьте доступность API:
   ```bash
   curl "https://api.testmail.app/api/json?apikey=YOUR_API_KEY&namespace=YOUR_NAMESPACE&limit=1"
   ```

### Включение детального логирования

Для отладки можно включить детальное логирование в тестах:

```php
// В методе createEmailInstance() измените уровень логирования
$logger = new Logger([
    'directory' => $this->testLogDirectory,
    'level' => 'debug',  // Добавьте эту строку
]);
```

### Просмотр логов

```bash
# Логи создаются во временной директории
# Путь выводится в тестах, например:
ls -la /tmp/email_testmail_test_*/
cat /tmp/email_testmail_test_*/app.log
```

### Проверка писем через веб-интерфейс

Если тесты не проходят, проверьте письма вручную:

1. Откройте [https://testmail.app](https://testmail.app)
2. Войдите в свой аккаунт
3. Выберите свой namespace
4. Просмотрите полученные письма
5. Проверьте их содержимое и заголовки

### Проверка через curl

```bash
# Получить последние письма
curl "https://api.testmail.app/api/json?apikey=YOUR_API_KEY&namespace=YOUR_NAMESPACE&limit=10" | jq

# С определённым тегом
curl "https://api.testmail.app/api/json?apikey=YOUR_API_KEY&namespace=YOUR_NAMESPACE&tag=test&limit=10" | jq
```

---

## Дополнительная информация

- **Документация testmail.app:** [https://testmail.app/docs](https://testmail.app/docs)
- **Детальное руководство:** [docs/EMAIL_TESTMAIL_TESTING.md](../../docs/EMAIL_TESTMAIL_TESTING.md)
- **Примеры использования:** [examples/email_testmail_example.php](../../examples/email_testmail_example.php)
- **Класс Email:** [src/Email.class.php](../../src/Email.class.php)

---

## CI/CD интеграция

Для использования в CI/CD pipeline:

### GitHub Actions

```yaml
# .github/workflows/test.yml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
      
      - name: Install dependencies
        run: composer install
      
      - name: Run integration tests
        env:
          TESTMAIL_NAMESPACE: ${{ secrets.TESTMAIL_NAMESPACE }}
          TESTMAIL_API_KEY: ${{ secrets.TESTMAIL_API_KEY }}
        run: vendor/bin/phpunit tests/Integration/EmailTestmailTest.php
```

### GitLab CI

```yaml
# .gitlab-ci.yml
test:
  image: php:8.1
  script:
    - composer install
    - export TESTMAIL_NAMESPACE=$TESTMAIL_NAMESPACE
    - export TESTMAIL_API_KEY=$TESTMAIL_API_KEY
    - vendor/bin/phpunit tests/Integration/EmailTestmailTest.php
  variables:
    TESTMAIL_NAMESPACE: $TESTMAIL_NAMESPACE
    TESTMAIL_API_KEY: $TESTMAIL_API_KEY
```

---

## Лицензия

Интеграционные тесты являются частью проекта и распространяются под той же лицензией.

---

**Примечание:** Всегда держите ваш API ключ в секрете и не коммитьте его в систему контроля версий!

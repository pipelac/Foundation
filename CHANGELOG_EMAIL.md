# Email Component - Changelog

## Версия 2.0.0 (Текущая)

### Новые возможности

#### 1. Поддержка SMTP протокола
- ✅ Добавлена полная поддержка SMTP для отправки писем
- ✅ Поддержка шифрования: TLS, SSL, STARTTLS
- ✅ Аутентификация на SMTP серверах (LOGIN)
- ✅ Настройка хоста, порта, credentials
- ✅ Автоматический выбор между SMTP и функцией mail()

**Конфигурация:**
```json
{
  "smtp": {
    "host": "smtp.gmail.com",
    "port": 587,
    "encryption": "tls",
    "username": "user@gmail.com",
    "password": "app-password"
  }
}
```

#### 2. Механизм повторных попыток (Retry)
- ✅ Автоматическая повторная отправка при сбоях
- ✅ Настраиваемое количество попыток
- ✅ Экспоненциальная задержка между попытками
- ✅ Детальное логирование каждой попытки

**Конфигурация:**
```json
{
  "delivery": {
    "retry_attempts": 3,
    "retry_delay": 5,
    "timeout": 30
  }
}
```

**Механизм работы:**
- Попытка 1: отправка немедленно
- Попытка 2: задержка = retry_delay × 1 сек
- Попытка 3: задержка = retry_delay × 2 сек
- И так далее...

#### 3. Поддержка reply_name
- ✅ Добавлен параметр `reply_name` для указания имени в Reply-To
- ✅ Можно указать в конфигурации или при отправке
- ✅ Корректное форматирование заголовка Reply-To

**Использование:**
```php
$config = [
    'reply_to' => 'support@example.com',
    'reply_name' => 'Support Team',
];

// Или при отправке
$email->send($to, $subject, $body, [
    'reply_to' => 'custom@example.com',
    'reply_name' => 'Custom Name',
]);
```

#### 4. Настройка таймаутов
- ✅ Параметр `timeout` для контроля времени подключения
- ✅ Отдельный таймаут для SMTP команд (5 сек)
- ✅ Обработка таймаутов с понятными сообщениями

#### 5. Production улучшения
- ✅ Улучшенная обработка ошибок с детальными сообщениями
- ✅ Защита от null-байтов в email адресах
- ✅ Расширенная валидация всех параметров
- ✅ Константы для значений по умолчанию
- ✅ Безопасное закрытие соединений (finally блок)
- ✅ Информационное логирование успешных отправок

### Улучшения кода

#### Архитектура
- 🔄 Разделение логики SMTP и mail() на отдельные методы
- 🔄 Вынос валидации в отдельные методы (validateAndSet*)
- 🔄 Улучшена структура и читаемость кода
- 🔄 Добавлены константы для магических значений

#### Безопасность
- 🔒 Добавлена фильтрация null-байтов (\0)
- 🔒 Улучшена санитизация email адресов
- 🔒 Валидация SMTP параметров
- 🔒 Защита от инъекций в заголовках

#### Производительность
- ⚡ Константа CRLF вместо повторяющихся строк
- ⚡ Оптимизация генерации Message-ID и Boundary
- ⚡ Эффективная работа с потоками (streams)

#### Надежность
- 🛡️ Retry механизм для устойчивости к временным сбоям
- 🛡️ Proper error handling во всех методах
- 🛡️ Таймауты для предотвращения зависаний
- 🛡️ Graceful degradation (fallback на mail())

### Документация

#### Добавлено
- 📚 EMAIL_README.md - полная документация компонента
- 📚 CHANGELOG_EMAIL.md - список изменений
- 📚 examples/email_example.php - 5 подробных примеров
- 📚 Обновлен config/email.json с описанием всех параметров
- 📚 PHPDoc на русском для всех методов

#### Примеры использования
- Отправка через mail()
- Отправка через SMTP
- HTML письма с вложениями
- Механизм retry
- Множественные получатели
- Настройка через конфиг файл

### Обратная совместимость

✅ **Полная обратная совместимость с версией 1.x**

Все существующие конфигурации продолжат работать:
```php
// Старая конфигурация - работает!
$email = new Email([
    'from_email' => 'test@example.com',
    'from_name' => 'Test',
]);
```

Новые параметры полностью опциональны:
- Если SMTP не настроен - используется mail()
- Все новые параметры имеют разумные значения по умолчанию
- Signature метода send() не изменился

### Миграция с 1.x на 2.0

#### Шаг 1: Добавить SMTP конфигурацию (опционально)

```json
{
  "smtp": {
    "host": "smtp.example.com",
    "port": 587,
    "encryption": "tls",
    "username": "user@example.com",
    "password": "password"
  }
}
```

#### Шаг 2: Настроить retry (опционально)

```json
{
  "delivery": {
    "retry_attempts": 3,
    "retry_delay": 5,
    "timeout": 30
  }
}
```

#### Шаг 3: Добавить reply_name (опционально)

```json
{
  "reply_name": "Support Team"
}
```

Готово! Все существующие вызовы будут работать без изменений.

### Технические детали

#### Требования
- PHP 8.1+
- Расширения: openssl (для SSL/TLS), mbstring (опционально)

#### Новые методы (приватные)
- `validateAndSetSmtpConfig()` - валидация SMTP параметров
- `validateAndSetDeliveryConfig()` - валидация параметров доставки
- `isSmtpConfigured()` - проверка наличия SMTP конфигурации
- `sendViaSmtp()` - отправка через SMTP
- `connectToSmtp()` - подключение к SMTP серверу
- `smtpAuthenticate()` - аутентификация SMTP
- `smtpCommand()` - выполнение SMTP команды
- `readSmtpResponse()` - чтение ответа от SMTP
- `buildEmailContent()` - построение email для SMTP
- `resolveReplyName()` - определение reply_name
- `logInfo()` - информационное логирование

#### Новые свойства
```php
private ?string $replyName;
private ?string $smtpHost;
private ?int $smtpPort;
private ?string $smtpEncryption;
private ?string $smtpUsername;
private ?string $smtpPassword;
private int $retryAttempts;
private int $retryDelay;
private int $timeout;
```

#### Новые константы
```php
private const DEFAULT_RETRY_ATTEMPTS = 3;
private const DEFAULT_RETRY_DELAY = 5;
private const DEFAULT_TIMEOUT = 30;
private const DEFAULT_SMTP_PORT = 587;
private const DEFAULT_CHARSET = 'UTF-8';
private const SMTP_RESPONSE_TIMEOUT = 5;
private const CRLF = "\r\n";
```

### Тестирование

Рекомендуется протестировать:
1. ✅ Отправку через mail() (существующий функционал)
2. ✅ Отправку через SMTP с различными провайдерами
3. ✅ Retry механизм при недоступности сервера
4. ✅ Таймауты и обработку ошибок
5. ✅ HTML письма и вложения через SMTP
6. ✅ Множественные получатели

### Известные ограничения

1. **SMTP AUTH**: поддерживается только LOGIN метод
   - Не поддерживается: PLAIN, CRAM-MD5, XOAUTH2
   - Для большинства провайдеров LOGIN достаточно

2. **Параллельная отправка**: класс не поддерживает batch отправку
   - Используйте пул соединений или очереди для массовых рассылок

3. **DKIM/SPF**: класс не добавляет DKIM подписи
   - Используйте настройки на уровне SMTP сервера

### Roadmap (будущие версии)

#### Версия 2.1
- [ ] Поддержка альтернативных методов аутентификации (PLAIN, XOAUTH2)
- [ ] Async/Promise API для неблокирующей отправки
- [ ] Пул SMTP соединений
- [ ] DKIM подписи

#### Версия 2.2
- [ ] Templating система для писем
- [ ] Tracking (открытия, клики)
- [ ] Unsubscribe header
- [ ] Bounce handling

#### Версия 3.0
- [ ] Поддержка PHP 8.2+ features (readonly properties)
- [ ] Event system для расширяемости
- [ ] Middleware support
- [ ] Metrics и мониторинг

---

**Дата релиза**: 2024
**Автор**: Development Team
**Лицензия**: См. LICENSE файл

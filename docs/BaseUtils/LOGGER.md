# Logger - Документация

## Описание

`Logger` - класс структурированного логирования с поддержкой ротации файлов, кеширования настроек в памяти и оптимизированной работы с файловой системой. Поддерживает отправку email уведомлений администратору при критических ошибках.

## Возможности

- ✅ Структурированное логирование в формате JSON
- ✅ Пять уровней логирования (DEBUG, INFO, WARNING, ERROR, CRITICAL)
- ✅ Автоматическая ротация лог-файлов по размеру
- ✅ Буферизация записей для оптимизации производительности
- ✅ Кеширование конфигурации в памяти
- ✅ Email уведомления администратору при критических ошибках (v1.0.0)
- ✅ Настраиваемый формат записей и временных меток
- ✅ Автоматическое создание директорий для логов
- ✅ Потокобезопасная запись с использованием блокировок
- ✅ Защита от переполнения диска

## Требования

- PHP 8.1+
- Расширение `json`
- Права на запись в директорию логов

## Установка

```bash
composer install
```

## Конфигурация

Создайте файл `config/logger.json`:

```json
{
    "directory": "/var/log/myapp",
    "file_name": "app.log",
    "log_level": "INFO",
    "max_files": 5,
    "max_file_size": 10,
    "pattern": "{timestamp} {level} {message} {context}",
    "date_format": "Y-m-d H:i:s",
    "log_buffer_size": 64,
    "enabled": true,
    "admin_email": "admin@example.com",
    "email_config": {
        "from_email": "noreply@example.com",
        "from_name": "Logger System",
        "smtp": {
            "host": "smtp.example.com",
            "port": 587,
            "encryption": "tls",
            "username": "user@example.com",
            "password": "password"
        }
    },
    "email_on_levels": ["CRITICAL"]
}
```

### Параметры конфигурации

| Параметр | Тип | Обязательный | По умолчанию | Описание |
|----------|-----|--------------|--------------|----------|
| `directory` | string | Да | - | Путь к директории для хранения логов |
| `file_name` | string | Нет | "app.log" | Имя файла лога |
| `log_level` / `min_level` | string | Нет | "DEBUG" | Минимальный уровень логирования (DEBUG, INFO, WARNING, ERROR, CRITICAL) |
| `max_files` | int | Нет | 5 | Максимальное количество файлов ротации |
| `max_file_size` | int | Нет | 1 | Максимальный размер файла в МБ |
| `pattern` | string | Нет | "{timestamp} {level} {message} {context}" | Шаблон формата записи |
| `date_format` | string | Нет | ATOM | Формат временной метки |
| `log_buffer_size` | int | Нет | 0 | Размер буфера в КБ (0 = без буферизации) |
| `enabled` | bool | Нет | true | Включить/выключить логирование |
| `admin_email` | string\|array | Нет | - | Email адрес(а) для уведомлений |
| `email_config` | array | Нет | - | Конфигурация Email класса |
| `email_on_levels` | array | Нет | ["CRITICAL"] | Уровни для отправки email |

## Использование

### Базовое использование

```php
use App\Component\Logger;
use App\Component\Config\ConfigLoader;

$config = ConfigLoader::load(__DIR__ . '/config/logger.json');
$logger = new Logger($config);

// Информационное сообщение
$logger->info('Пользователь вошел в систему', ['user_id' => 123, 'ip' => '192.168.1.1']);

// Предупреждение
$logger->warning('Превышено количество попыток входа', ['user_id' => 456, 'attempts' => 5]);

// Ошибка
$logger->error('Не удалось подключиться к БД', ['host' => 'localhost', 'error' => 'Connection refused']);

// Критическая ошибка (отправит email администратору)
$logger->critical('Критическая ошибка системы', ['exception' => $e->getMessage()]);

// Отладочная информация
$logger->debug('Детальная информация для отладки', ['query' => 'SELECT * FROM users']);
```

### Минимальная конфигурация

```php
$logger = new Logger([
    'directory' => '/var/log/myapp',
]);
```

### Email уведомления (v1.0.0)

```php
$logger = new Logger([
    'directory' => '/var/log',
    'file_name' => 'app.log',
    'admin_email' => ['admin@example.com', 'team@example.com'],
    'email_config' => [
        'from_email' => 'noreply@example.com',
        'from_name' => 'Logger System',
        'smtp' => [
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'your-email@gmail.com',
            'password' => 'your-password',
        ],
    ],
    'email_on_levels' => ['CRITICAL', 'ERROR'], // Отправлять email на CRITICAL и ERROR
]);

// Отправит email
$logger->critical('Сервер перегружен', ['cpu' => '95%', 'memory' => '98%']);
$logger->error('Ошибка обработки платежа', ['order_id' => 12345]);

// Не отправит email
$logger->warning('Медленный запрос', ['duration' => 2.5]);
```

### Буферизация для повышения производительности

```php
$logger = new Logger([
    'directory' => '/var/log/myapp',
    'log_buffer_size' => 128, // 128 КБ буфер
]);

// Записи накапливаются в буфере
for ($i = 0; $i < 1000; $i++) {
    $logger->info("Обработка записи #{$i}");
}

// Принудительно сбросить буфер
$logger->flush();

// Или буфер автоматически сбросится при уничтожении объекта
```

### Фильтрация по уровню логирования

```php
// Установить минимальный уровень INFO - DEBUG не будет логироваться
$logger = new Logger([
    'directory' => '/var/log',
    'log_level' => 'INFO', // или 'min_level' => 'INFO'
]);

$logger->debug('Это сообщение НЕ попадет в лог'); // DEBUG < INFO
$logger->info('Это сообщение попадет в лог');      // INFO >= INFO
$logger->warning('Это сообщение попадет в лог');   // WARNING > INFO
$logger->error('Это сообщение попадет в лог');     // ERROR > INFO
$logger->critical('Это сообщение попадет в лог');  // CRITICAL > INFO

// Для production рекомендуется использовать INFO или WARNING
$productionLogger = new Logger([
    'directory' => '/var/log/production',
    'log_level' => 'INFO',  // Отключить DEBUG логи
]);

// Для development можно использовать DEBUG
$devLogger = new Logger([
    'directory' => './logs',
    'log_level' => 'DEBUG',  // Логировать все
]);
```

### Управление логированием

```php
// Отключить логирование
$logger->disable();

// Логи не будут записываться
$logger->info('Это не будет записано');

// Включить логирование
$logger->enable();

// Проверить статус
if ($logger->isEnabled()) {
    $logger->info('Логирование включено');
}
```

### Настройка формата записей

```php
// Пользовательский формат
$logger = new Logger([
    'directory' => '/var/log',
    'pattern' => '[{timestamp}] {level}: {message} | Context: {context}',
    'date_format' => 'Y-m-d H:i:s.u',
]);

// Пример вывода:
// [2024-01-15 10:30:45.123456] INFO: Пользователь вошел | Context: {"user_id":123}
```

### Ротация файлов

```php
$logger = new Logger([
    'directory' => '/var/log',
    'file_name' => 'app.log',
    'max_files' => 7,        // Хранить 7 файлов
    'max_file_size' => 50,   // Максимум 50 МБ на файл
]);

// При превышении max_file_size создается:
// app.log       - текущий файл
// app.log.1     - предыдущий
// app.log.2     - еще более старый
// ...
// app.log.7     - самый старый (удаляется при новой ротации)
```

## Уровни логирования

| Уровень | Метод | Описание | Использование |
|---------|-------|----------|---------------|
| DEBUG | `debug()` | Отладочная информация | Детальная информация для разработчиков |
| INFO | `info()` | Информационные сообщения | Обычные события приложения |
| WARNING | `warning()` | Предупреждения | Потенциальные проблемы, не критичные |
| ERROR | `error()` | Ошибки | Ошибки, требующие внимания |
| CRITICAL | `critical()` | Критические ошибки | Серьезные проблемы, требующие немедленного действия |

## API Reference

### Конструктор

```php
public function __construct(array $config)
```

Создает экземпляр логгера с указанной конфигурацией.

**Параметры:**
- `$config` (array) - Массив параметров конфигурации

**Исключения:**
- `LoggerValidationException` - Если конфигурация некорректна

### Методы логирования

#### info()

```php
public function info(string $message, array $context = []): void
```

Записывает информационное сообщение.

#### warning()

```php
public function warning(string $message, array $context = []): void
```

Записывает предупреждение.

#### error()

```php
public function error(string $message, array $context = []): void
```

Записывает сообщение об ошибке.

#### critical()

```php
public function critical(string $message, array $context = []): void
```

Записывает критическое сообщение и отправляет email (если настроено).

#### debug()

```php
public function debug(string $message, array $context = []): void
```

Записывает отладочное сообщение.

#### log()

```php
public function log(string $level, string $message, array $context = []): void
```

Базовый метод логирования с произвольным уровнем.

### Управление

#### enable()

```php
public function enable(): void
```

Включает логирование.

#### disable()

```php
public function disable(): void
```

Отключает логирование.

#### isEnabled()

```php
public function isEnabled(): bool
```

Проверяет, включено ли логирование.

#### flush()

```php
public function flush(): void
```

Принудительно сбрасывает буфер логов в файл.

## Примеры использования

### Логирование с контекстом

```php
// Логирование входа пользователя
$logger->info('Пользователь аутентифицирован', [
    'user_id' => $userId,
    'username' => $username,
    'ip_address' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
    'timestamp' => time(),
]);

// Логирование ошибки БД
try {
    $mysql->query('SELECT * FROM users');
} catch (Exception $e) {
    $logger->error('Ошибка выполнения SQL запроса', [
        'exception' => get_class($e),
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}
```

### Производственное окружение

```php
// Конфигурация для production
$logger = new Logger([
    'directory' => '/var/log/production',
    'file_name' => 'app.log',
    'log_level' => 'INFO',       // Отключить DEBUG логи в production
    'max_files' => 30,           // 30 дней истории
    'max_file_size' => 100,      // 100 МБ на файл
    'log_buffer_size' => 256,    // Буфер 256 КБ
    'enabled' => true,
    'admin_email' => ['admin@company.com', 'devops@company.com'],
    'email_config' => $emailConfig,
    'email_on_levels' => ['CRITICAL'],
]);
```

### Разработка и отладка

```php
// Конфигурация для development
$logger = new Logger([
    'directory' => './logs',
    'file_name' => 'debug.log',
    'log_level' => 'DEBUG',      // Логировать все уровни включая DEBUG
    'max_files' => 3,
    'max_file_size' => 10,
    'log_buffer_size' => 0,      // Без буферизации для немедленной записи
    'date_format' => 'Y-m-d H:i:s.u',
    'pattern' => '[{timestamp}] {level} {message} {context}',
]);

// Отладочные логи
$logger->debug('Начало обработки запроса', ['request_id' => uniqid()]);
$logger->debug('Параметры запроса', $_REQUEST);
$logger->debug('Сессия пользователя', $_SESSION);
```

### Интеграция с обработчиком исключений

```php
set_exception_handler(function (Throwable $e) use ($logger) {
    $logger->critical('Необработанное исключение', [
        'exception' => get_class($e),
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
});

set_error_handler(function ($errno, $errstr, $errfile, $errline) use ($logger) {
    $logger->error('PHP Error', [
        'errno' => $errno,
        'errstr' => $errstr,
        'errfile' => $errfile,
        'errline' => $errline,
    ]);
});
```

## Обработка ошибок

### Исключения

- `LoggerException` - Базовое исключение логгера
- `LoggerValidationException` - Ошибка валидации конфигурации

```php
try {
    $logger = new Logger(['directory' => '']);
} catch (LoggerValidationException $e) {
    echo "Ошибка конфигурации: " . $e->getMessage();
}

try {
    $logger->info('Test message');
} catch (LoggerException $e) {
    echo "Ошибка записи лога: " . $e->getMessage();
}
```

## Лучшие практики

1. **Используйте буферизацию** для высоконагруженных приложений:
   ```php
   'log_buffer_size' => 128, // 128 КБ
   ```

2. **Настройте ротацию** для предотвращения переполнения диска:
   ```php
   'max_files' => 30,
   'max_file_size' => 100,
   ```

3. **Добавляйте контекст** для упрощения отладки:
   ```php
   $logger->error('Ошибка', ['user_id' => $id, 'action' => $action]);
   ```

4. **Используйте правильные уровни**:
   - `DEBUG` - только для разработки
   - `INFO` - обычные события
   - `WARNING` - потенциальные проблемы
   - `ERROR` - ошибки, требующие исправления
   - `CRITICAL` - срочные проблемы, требующие немедленного внимания

4.1. **Фильтруйте логи по уровню**:
   ```php
   // Production - только INFO и выше
   'log_level' => 'INFO',
   
   // Development - все логи включая DEBUG
   'log_level' => 'DEBUG',
   
   // Критические системы - только ошибки
   'log_level' => 'ERROR',
   ```

5. **Настройте email уведомления** для критических ошибок:
   ```php
   'admin_email' => 'admin@company.com',
   'email_on_levels' => ['CRITICAL'],
   ```

6. **Защитите логи** от несанкционированного доступа:
   ```bash
   chmod 750 /var/log/myapp
   ```

7. **Не логируйте чувствительные данные** (пароли, токены, ключи API)

8. **Используйте structured logging** с контекстом вместо конкатенации строк

## Производительность

### Оптимизация

- **Кеширование конфигурации** - конфигурация кешируется в памяти
- **Буферизация записей** - записи накапливаются перед физической записью
- **Кеш метаданных файлов** - снижает количество системных вызовов
- **Блокировки LOCK_EX** - потокобезопасная запись

### Рекомендации

- Для высоконагруженных систем используйте `log_buffer_size` >= 128 КБ
- **Устанавливайте `log_level` = 'INFO' в production** для фильтрации DEBUG логов
- Используйте ротацию файлов для предотвращения роста размера файлов
- Рассмотрите использование асинхронного логирования для критичных к производительности участков
- В development используйте `log_level` = 'DEBUG' для полной отладки

## Примеры в коде

См. `examples/logger_example.php` для полных примеров использования.

## Возможности v1.0.0

В версии v1.0.0 включена поддержка email уведомлений:

```php
// Базовая конфигурация
$logger = new Logger([
    'directory' => '/var/log',
    'file_name' => 'app.log',
]);

// С email уведомлениями
$logger = new Logger([
    'directory' => '/var/log',
    'file_name' => 'app.log',
    'admin_email' => 'admin@example.com',
    'email_config' => [...],
    'email_on_levels' => ['CRITICAL'],
]);
```

## См. также

- [Email документация](EMAIL.md) - для настройки email уведомлений
- [MySQL документация](MYSQL.md) - пример интеграции с БД
- [OpenRouter документация](OPENROUTER.md) - пример логирования API запросов

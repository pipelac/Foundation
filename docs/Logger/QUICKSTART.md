# Logger - Быстрый старт

## 📦 Установка

```bash
composer require your-vendor/logger
```

## ⚡ Быстрый старт

### Минимальная конфигурация (2 параметра)

```php
use App\Component\Logger;

$logger = new Logger([
    'directory' => '/var/www/logs',
    'file_name' => 'app.log'
]);

$logger->info("Приложение запущено!");
```

### Production конфигурация (рекомендуется)

```php
$logger = new Logger([
    'directory' => '/var/www/logs/production',
    'file_name' => 'app.log',
    'log_level' => 'INFO',         // Только INFO и выше
    'max_files' => 7,              // Неделя логов
    'max_file_size' => 50,         // 50 МБ на файл
    'log_buffer_size' => 128,      // 128 КБ буфер
    'pattern' => '[{timestamp}] {level}: {message} {context}',
    'date_format' => 'Y-m-d H:i:s'
]);
```

## 📝 Основные методы

### Логирование

```php
$logger->debug("Отладка", ['var' => $value]);
$logger->info("Информация", ['user_id' => 123]);
$logger->warning("Предупреждение", ['cpu' => 85]);
$logger->error("Ошибка", ['error' => $e->getMessage()]);
$logger->critical("Критическая ошибка", ['reason' => 'OOM']);
```

### Управление

```php
$logger->enable();      // Включить
$logger->disable();     // Выключить
$logger->isEnabled();   // Проверить статус
$logger->flush();       // Сбросить буфер
```

### Статические методы

```php
Logger::clearAllCaches();                 // Очистить все кеши
Logger::clearCacheForDirectory('/logs');  // Очистить для директории
```

## 🎯 Уровни логирования

| Уровень | Приоритет | Когда использовать |
|---------|-----------|-------------------|
| **DEBUG** | 0 | Отладка, детальная информация |
| **INFO** | 1 | Информационные события (старт, остановка) |
| **WARNING** | 2 | Предупреждения (высокая нагрузка, устаревший API) |
| **ERROR** | 3 | Ошибки, которые не останавливают работу |
| **CRITICAL** | 4 | Критические ошибки (система недоступна) |

### Фильтрация

```php
'log_level' => 'WARNING'  // Записываются только WARNING, ERROR, CRITICAL
```

## 🔄 Ротация файлов

```php
'max_file_size' => 50,  // МБ - размер одного файла
'max_files' => 7        // Количество файлов
```

**Создаются файлы:**
- `app.log` (текущий)
- `app.log.1` (предыдущий)
- `app.log.2` (еще старше)
- ...
- `app.log.6` (самый старый)

**Общий объем:** `max_files × max_file_size = 7 × 50 = 350 МБ`

## 🚀 Буферизация (производительность)

```php
'log_buffer_size' => 128  // КБ - размер буфера
```

**Рекомендации:**
- Development: `0` (без буфера)
- Production: `64-128` КБ
- High-load: `256-512` КБ

**Сброс:**
- Автоматически при заполнении
- Автоматически в деструкторе
- Вручную через `$logger->flush()`

## 🎨 Форматирование

### Шаблон (pattern)

```php
'pattern' => '[{timestamp}] {level}: {message} {context}'
```

**Плейсхолдеры:**
- `{timestamp}` - дата/время
- `{level}` - уровень (DEBUG, INFO, и т.д.)
- `{message}` - сообщение
- `{context}` - JSON контекст

**Примеры:**
```
[2024-01-15 10:30:45] INFO: Сообщение {"user_id":123}
2024-01-15 | INFO | Сообщение | {"user_id":123}
INFO - Сообщение
```

### Формат даты

```php
'date_format' => 'Y-m-d H:i:s'  // 2024-01-15 10:30:45
'date_format' => 'Y-m-d H:i:s.u'  // С микросекундами
'date_format' => DateTimeImmutable::ATOM  // RFC 3339
```

## 📋 Контекст

Любые данные сериализуются в JSON с поддержкой Unicode:

```php
$logger->error("Ошибка заказа", [
    'order_id' => 'ORD-12345',
    'user' => [
        'id' => 123,
        'email' => 'user@example.com'
    ],
    'error' => [
        'code' => 'PAYMENT_FAILED',
        'message' => 'Недостаточно средств'
    ]
]);
```

**Вывод:**
```json
{
  "order_id": "ORD-12345",
  "user": {
    "id": 123,
    "email": "user@example.com"
  },
  "error": {
    "code": "PAYMENT_FAILED",
    "message": "Недостаточно средств"
  }
}
```

## ⚙️ Все параметры конфигурации

### Обязательные

| Параметр | Тип | Описание |
|----------|-----|----------|
| `directory` | string | Путь к директории логов |
| `file_name` | string | Имя файла (по умолчанию: app.log) |

### Опциональные

| Параметр | Тип | По умолчанию | Описание |
|----------|-----|--------------|----------|
| `enabled` | bool | `true` | Включить/выключить логирование |
| `log_level` | string | `'DEBUG'` | Минимальный уровень (DEBUG, INFO, WARNING, ERROR, CRITICAL) |
| `max_files` | int | `5` | Количество файлов при ротации |
| `max_file_size` | int | `1` | Размер файла в МБ |
| `pattern` | string | `'{timestamp} {level} {message} {context}'` | Шаблон формата |
| `date_format` | string | `DateTimeImmutable::ATOM` | Формат даты |
| `log_buffer_size` | int | `0` | Размер буфера в КБ (0 = без буфера) |

## 🔍 Примеры сценариев

### 1. Простое приложение

```php
$logger = new Logger([
    'directory' => '/var/www/logs',
    'file_name' => 'app.log'
]);

$logger->info("Приложение запущено");
```

### 2. Веб API

```php
$logger = new Logger([
    'directory' => '/var/www/logs/api',
    'file_name' => 'api.log',
    'log_level' => 'INFO',
    'max_files' => 7,
    'max_file_size' => 50
]);

$logger->info("API request", [
    'method' => 'POST',
    'endpoint' => '/api/users',
    'ip' => $_SERVER['REMOTE_ADDR']
]);
```

### 3. Фоновый воркер

```php
$logger = new Logger([
    'directory' => '/var/www/logs/workers',
    'file_name' => 'worker.log',
    'log_level' => 'WARNING',
    'log_buffer_size' => 256,
    'max_file_size' => 100,
    'max_files' => 10
]);

while (true) {
    $logger->info("Обработка задачи", ['task_id' => $taskId]);
    processTask($taskId);
    $logger->flush();  // Сброс буфера после задачи
}
```

### 4. Микросервис с несколькими логгерами

```php
// Основной лог
$appLogger = new Logger([
    'directory' => '/logs/app',
    'file_name' => 'app.log',
    'log_level' => 'INFO'
]);

// Лог БД
$dbLogger = new Logger([
    'directory' => '/logs/database',
    'file_name' => 'queries.log',
    'log_level' => 'WARNING'
]);

// Лог безопасности
$secLogger = new Logger([
    'directory' => '/logs/security',
    'file_name' => 'security.log',
    'log_level' => 'ERROR'
]);

$appLogger->info("Запуск приложения");
$dbLogger->warning("Медленный запрос", ['duration' => 5.2]);
$secLogger->error("Неудачная попытка входа", ['ip' => $ip]);
```

## ⚡ Производительность

### Тесты (1000 записей)

| Режим | Время | Ускорение |
|-------|-------|-----------|
| Без буфера | ~16 мс | 1x |
| С буфером 128 КБ | ~2 мс | **8x быстрее** |
| С фильтрацией | <1 мс | **16x быстрее** |

### Оптимизация

```php
$logger = new Logger([
    'directory' => '/logs',
    'file_name' => 'app.log',
    'log_level' => 'INFO',        // Блокирует DEBUG
    'log_buffer_size' => 128,     // Буферизация
    'pattern' => '{level} {message}'  // Минимальный формат
]);
```

## 🔧 Отладка

### Временно включить DEBUG

```php
$logger = new Logger([
    'directory' => '/logs',
    'file_name' => 'app.log',
    'log_level' => getenv('APP_DEBUG') ? 'DEBUG' : 'INFO'
]);
```

### Временно отключить логирование

```php
$logger->disable();
// Чувствительные операции
$logger->enable();
```

### Проверка файлов

```bash
# Текущий лог
tail -f /var/www/logs/app.log

# Ротированные логи
ls -lh /var/www/logs/
```

## 🐛 Обработка ошибок

### Исключения

```php
use App\Component\Exception\Logger\LoggerException;
use App\Component\Exception\Logger\LoggerValidationException;

try {
    $logger = new Logger([
        'directory' => '/invalid/path',
        'file_name' => 'app.log'
    ]);
} catch (LoggerValidationException $e) {
    echo "Ошибка конфигурации: " . $e->getMessage();
}

try {
    $logger->log('INVALID_LEVEL', 'Message');
} catch (LoggerException $e) {
    echo "Ошибка логирования: " . $e->getMessage();
}
```

## 📚 Дополнительная документация

- **Полная документация:** `docs/Logger/README.md`
- **Конфигурация с комментариями:** `production/configs/logger.json`
- **Примеры кода:** `production/configs/logger.examples.php`
- **Отчет о тестировании:** `tests/LOGGER_TEST_REPORT.md`

## ✅ Готовые конфигурации

### Development

```php
[
    'directory' => '/var/www/logs/dev',
    'file_name' => 'app.log',
    'log_level' => 'DEBUG',
    'log_buffer_size' => 0
]
```

### Staging

```php
[
    'directory' => '/var/www/logs/staging',
    'file_name' => 'app.log',
    'log_level' => 'INFO',
    'max_files' => 5,
    'max_file_size' => 20,
    'log_buffer_size' => 64
]
```

### Production

```php
[
    'directory' => '/var/www/logs/production',
    'file_name' => 'app.log',
    'log_level' => 'INFO',
    'max_files' => 7,
    'max_file_size' => 50,
    'log_buffer_size' => 128,
    'pattern' => '[{timestamp}] {level}: {message} {context}',
    'date_format' => 'Y-m-d H:i:s'
]
```

---

**🚀 Готово! Начните с минимальной конфигурации и расширяйте по мере необходимости.**

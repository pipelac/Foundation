# Обзор стандартизации именования классов исключений

## Проблема
Некоторые классы использовали общие имена исключений (например, `ConnectionException`, `DatabaseException`) или стандартные PHP исключения (`RuntimeException`, `InvalidArgumentException`), которые не соответствовали паттерну именования, где имя класса исключения должно начинаться с названия основного класса.

## Решение
Приведены в соответствие с паттерном именования все классы исключений:

### MySQL.class.php
- ❌ `DatabaseException` → ✅ `MySQLException`
- ❌ `ConnectionException` → ✅ `MySQLConnectionException`
- ❌ `TransactionException` → ✅ `MySQLTransactionException`

### Http.class.php
- ❌ `RuntimeException` → ✅ `HttpException`
- ❌ `InvalidArgumentException` → ✅ `HttpValidationException`

### Email.class.php
- ❌ `RuntimeException` → ✅ `EmailException`
- ❌ `InvalidArgumentException` → ✅ `EmailValidationException`

### Logger.class.php
- ❌ `RuntimeException` → ✅ `LoggerException`
- ❌ `Exception` → ✅ `LoggerValidationException`

### Rss.class.php
- ❌ `RuntimeException` → ✅ `RssException`
- ❌ `RuntimeException` (для валидации) → ✅ `RssValidationException`

### Уже соответствовали паттерну ✅
- **OpenRouter.class.php** - `OpenRouterException`, `OpenRouterApiException`, `OpenRouterNetworkException`, `OpenRouterValidationException`
- **Telegram.class.php** - `TelegramException`, `TelegramApiException`, `TelegramConfigException`, `TelegramFileException`

## Результат
Все классы теперь используют исключения с именами, начинающимися с названия основного класса, что улучшает читаемость кода и соответствует требованиям стиля кодирования.

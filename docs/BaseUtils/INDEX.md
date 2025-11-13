# BaseUtils - Документация

Набор универсальных утилит и компонентов для PHP проектов.

---

## 📚 Основные компоненты

### Сетевые утилиты
- **[HTTP](HTTP.md)** - HTTP клиент с поддержкой proxy
- **[RSS](RSS.md)** - Парсер RSS/Atom лент
- **[PROXY_PROTOCOLS_SUPPORT](PROXY_PROTOCOLS_SUPPORT.md)** - Поддержка proxy протоколов

### Работа с данными
- **[MySQL](MYSQL.md)** - Обертка для работы с MySQL/MariaDB
- **[MySQL Connection Factory](MYSQL_CONNECTION_FACTORY.md)** - Фабрика подключений к MySQL
- **[MySQL Quick Reference](MYSQL_QUICK_REFERENCE.md)** - Краткий справочник
- **[MySQL Version Compatibility](MYSQL_VERSION_COMPATIBILITY.md)** - Совместимость версий
- **[FileCache](FILECACHE.md)** - Файловый кеш

### Логирование и мониторинг
- **[Logger](LOGGER.md)** - Система логирования
- **[SNMP OID Loader](SNMP_OID_LOADER.md)** - Загрузчик SNMP OID

### Email и уведомления
- **[Email](EMAIL.md)** - Отправка email
- **[Telegram](TELEGRAM.md)** - Telegram Bot API

### AI и Machine Learning
- **[OpenRouter](OPENROUTER.md)** - Клиент OpenRouter AI API
- **[OpenRouter Response Analysis](OPENROUTER_RESPONSE_ANALYSIS.md)** ⭐ - Утилиты для работы с ответами AI
- **[OpenRouter Metrics](OPENROUTER_METRICS.md)** - Сбор и анализ метрик
- **[OpenRouter Audio Models](OPENROUTER_AUDIO_MODELS.md)** - Работа с аудио моделями
- **[OpenRouter Image Models](OPENROUTER_IMAGE_MODELS.md)** - Работа с изображениями

### Другое
- **[API Extensions](API_EXTENSIONS.md)** - Расширения API
- **[NetMap Examples](NETMAP_EXAMPLES.md)** - Примеры работы с NetMap

---

## ⭐ Новое: OpenRouterResponseAnalysis

Минималистичный базовый класс для работы с ответами AI API:

```php
use App\Component\OpenRouterResponseAnalysis;

// Парсинг JSON из ответа AI
$data = OpenRouterResponseAnalysis::parseJSONResponse($response);

// Подготовка сообщений с кешированием для Claude
$messages = OpenRouterResponseAnalysis::prepareMessages($sys, $user, $model);

// Подготовка опций для запроса
$options = OpenRouterResponseAnalysis::prepareOptions($modelConfig);
```

**Особенности:**
- ⚡ Статические методы (не требует создания экземпляра)
- 🪶 Без зависимостей от БД
- 🔄 Переиспользуемый в любых проектах
- 🎯 Автоматическое кеширование для Claude

**См. также:**
- [Детальный анализ](../../ANALYSIS_OpenRouterResponseAnalysis.md)
- [Краткая сводка](../../SUMMARY_OpenRouterResponseAnalysis.md)
- [Примеры использования](../examples/OpenRouterResponseAnalysis_examples.php)
- [Рефакторинг AIAnalysisTrait](../Rss2Tlg/REFACTORING_AIAnalysisTrait.md)

---

## 📖 Структура документации

```
docs/BaseUtils/
├── INDEX.md (этот файл)
├── OPENROUTER_RESPONSE_ANALYSIS.md (⭐ новое)
├── OPENROUTER.md
├── OPENROUTER_METRICS.md
├── OPENROUTER_AUDIO_MODELS.md
├── OPENROUTER_IMAGE_MODELS.md
├── MYSQL.md
├── MYSQL_CONNECTION_FACTORY.md
├── MYSQL_QUICK_REFERENCE.md
├── MYSQL_VERSION_COMPATIBILITY.md
├── HTTP.md
├── RSS.md
├── LOGGER.md
├── FILECACHE.md
├── EMAIL.md
├── TELEGRAM.md
├── API_EXTENSIONS.md
├── NETMAP_EXAMPLES.md
├── PROXY_PROTOCOLS_SUPPORT.md
└── SNMP_OID_LOADER.md
```

---

## 🚀 Быстрый старт

### Установка

```bash
# Убедитесь, что у вас PHP 8.1+
php -v

# Подключите autoloader
require_once __DIR__ . '/vendor/autoload.php';
```

### Базовое использование

```php
use App\Component\OpenRouter;
use App\Component\OpenRouterResponseAnalysis;
use App\Component\Logger;
use App\Component\MySQL;

// Логирование
$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'file_name' => 'app.log'
]);

// База данных
$db = new MySQL([
    'host' => 'localhost',
    'database' => 'mydb',
    'username' => 'user',
    'password' => 'pass'
], $logger);

// OpenRouter AI
$openRouter = new OpenRouter([
    'api_key' => 'your-api-key',
    'app_name' => 'MyApp'
], $logger);

// Работа с AI ответами
$data = OpenRouterResponseAnalysis::parseJSONResponse($aiResponse);
```

---

## 📝 Соглашения

Все компоненты следуют единым соглашениям:

- ✅ Строгая типизация (`declare(strict_types=1)`)
- ✅ PHP 8.1+ features
- ✅ Опциональное логирование через Logger
- ✅ Обработка исключений на каждом уровне
- ✅ PHPDoc документация на русском языке
- ✅ Описательные имена методов и классов

---

**Версия:** 2.0  
**Последнее обновление:** 2024  
**Расположение:** `src/BaseUtils/`

# BaseUtils - Полный индекс

## 📑 Навигация

- [README.md](README.md) - Обзор модуля
- [MIGRATION_GUIDE.md](MIGRATION_GUIDE.md) - Руководство по миграции

## 📦 Базовые классы

### Работа с базами данных

#### MySQL.class.php
**Namespace:** `App\Component\MySQL`  
**Описание:** Обертка над PDO для работы с MySQL/MariaDB  
**Пример:** [examples/mysql_example.php](examples/mysql_example.php)  
**Основные методы:**
- `query()` - выполнение запросов
- `insert()` - вставка данных
- `update()` - обновление данных
- `delete()` - удаление данных
- `select()` - выборка данных
- `beginTransaction()`, `commit()`, `rollback()` - транзакции

#### MySQLConnectionFactory.class.php
**Namespace:** `App\Component\MySQLConnectionFactory`  
**Описание:** Фабрика для создания подключений к MySQL  
**Пример:** [examples/mysql_connection_factory_example.php](examples/mysql_connection_factory_example.php)  
**Основные методы:**
- `createFromConfig()` - создание из конфигурации
- `create()` - создание с параметрами

---

### HTTP и сеть

#### Http.class.php
**Namespace:** `App\Component\Http`  
**Описание:** HTTP клиент с поддержкой streaming и прокси  
**Пример:** [examples/http_example.php](examples/http_example.php)  
**Основные методы:**
- `get()` - GET запрос
- `post()` - POST запрос
- `download()` - загрузка файлов
- `stream()` - streaming запросы

#### NetworkUtil.class.php
**Namespace:** `App\Component\NetworkUtil`  
**Описание:** Системные сетевые утилиты (ping, nmap, fping)  
**Пример:** [examples/network_util_example.php](examples/network_util_example.php)  
**Основные методы:**
- `ping()` - ICMP ping
- `pingParse()` - парсинг результата ping
- `nmap()` - сканирование портов
- `whois()` - WHOIS запросы

#### ProxyPool.class.php
**Namespace:** `App\Component\ProxyPool`  
**Описание:** Пул прокси с автоматической ротацией и health-check  
**Пример:** [examples/proxypool_example.php](examples/proxypool_example.php)  
**Основные методы:**
- `addProxy()` - добавление прокси
- `getProxy()` - получение следующего прокси
- `healthCheck()` - проверка работоспособности
- `getStatistics()` - статистика

#### htmlWebProxyList.class.php
**Namespace:** `App\Component\htmlWebProxyList`  
**Описание:** Парсинг публичных прокси-листов с htmlweb.ru  
**Пример:** [examples/htmlweb_proxylist_example.php](examples/htmlweb_proxylist_example.php)  
**Основные методы:**
- `fetch()` - получение списка прокси
- `fetchForProxyPool()` - получение в формате ProxyPool

---

### Логирование и кеширование

#### Logger.class.php
**Namespace:** `App\Component\Logger`  
**Описание:** Ротация логов с email alerts  
**Пример:** [examples/logger_example.php](examples/logger_example.php)  
**Основные методы:**
- `debug()`, `info()`, `warning()`, `error()`, `critical()`
- `log()` - универсальный метод
- `rotate()` - ротация логов
- `sendEmailAlert()` - отправка уведомлений

#### Cache/FileCache.php
**Namespace:** `Cache\FileCache`  
**Описание:** Файловое кеширование  
**Пример:** [examples/cache_example.php](examples/cache_example.php)  
**Основные методы:**
- `set()` - сохранение в кеш
- `get()` - получение из кеша
- `delete()` - удаление
- `clear()` - очистка кеша

---

### Парсинг контента

#### Rss.class.php
**Namespace:** `App\Component\Rss`  
**Описание:** RSS/Atom парсер с кешированием  
**Пример:** [examples/rss_example.php](examples/rss_example.php)  
**Основные методы:**
- `fetch()` - получение ленты
- `parse()` - парсинг XML
- `getItems()` - получение элементов

#### WebtExtractor.class.php
**Namespace:** `App\Component\WebtExtractor`  
**Описание:** Извлечение контента из веб-страниц (Readability)  
**Пример:** [examples/webt-extractor-example.php](examples/webt-extractor-example.php)  
**Основные методы:**
- `extract()` - извлечение контента
- `clean()` - очистка HTML

---

### Уведомления

#### Email.class.php
**Namespace:** `App\Component\Email`  
**Описание:** Отправка email через SMTP  
**Пример:** [examples/email_example.php](examples/email_example.php)  
**Основные методы:**
- `send()` - отправка email
- `addAttachment()` - добавление вложений
- `setHtml()` - HTML письмо

#### Telegram.class.php
**Namespace:** `App\Component\Telegram`  
**Описание:** Базовый Telegram API клиент  
**Основные методы:**
- `sendMessage()` - отправка сообщения
- `sendPhoto()` - отправка фото
- `sendDocument()` - отправка документа

---

### AI сервисы

#### OpenAi.class.php
**Namespace:** `App\Component\OpenAi`  
**Описание:** OpenAI API клиент  
**Пример:** [examples/openai_example.php](examples/openai_example.php)  
**Основные методы:**
- `text2text()` - генерация текста
- `text2image()` - генерация изображений
- `text2speech()` - синтез речи
- `speech2text()` - распознавание речи

#### OpenRouter.class.php
**Namespace:** `App\Component\OpenRouter`  
**Описание:** OpenRouter API клиент (множество LLM моделей)  
**Пример:** [examples/openrouter_example.php](examples/openrouter_example.php)  
**Основные методы:**
- `text2text()` - генерация текста
- `text2textWithMetrics()` - генерация с метриками
- `audio2text()` - транскрипция аудио

#### OpenRouterMetrics.class.php
**Namespace:** `App\Component\OpenRouterMetrics`  
**Описание:** Детальная метрика OpenRouter API  
**Пример:** [examples/openrouter_metrics_example.php](examples/openrouter_metrics_example.php)  
**Основные методы:**
- `extractMetricsFromHeaders()` - извлечение метрик из заголовков
- `createDetailedReport()` - создание отчета
- `getKeyInfo()` - информация об API ключе
- `estimateCost()` - оценка стоимости

---

### SNMP

#### Snmp.class.php
**Namespace:** `App\Component\Snmp`  
**Описание:** SNMP клиент для работы с сетевым оборудованием  
**Основные методы:**
- `get()` - получение OID
- `walk()` - обход OID дерева
- `set()` - установка значения

#### SnmpOid.class.php
**Namespace:** `App\Component\SnmpOid`  
**Описание:** SNMP OID утилиты  

---

### Конфигурация

#### Config/ConfigLoader.php
**Namespace:** `App\Component\Config\ConfigLoader`  
**Описание:** Загрузка JSON конфигураций  
**Основные методы:**
- `load()` - загрузка конфига
- `validate()` - валидация

---

### Исключения

#### Exception/
**Namespace:** `App\Component\Exception\*`  
**Описание:** Иерархия исключений для всех компонентов  

Доступные исключения:
- `HttpException` - HTTP ошибки
- `MySQLException` - ошибки БД
- `LoggerException` - ошибки логирования
- `ProxyPool\*` - ошибки прокси пула
- `htmlWebProxyList\*` - ошибки прокси листов
- И многие другие...

---

### Netmap

#### Netmap/
**Namespace:** `App\Component\Netmap\*`  
**Описание:** Утилиты для работы с сетевыми топологиями  
**Документация:** [docs/NETMAP_EXAMPLES.md](docs/NETMAP_EXAMPLES.md)  
**Пример:** [examples/netmap_topology_scan.php](examples/netmap_topology_scan.php)  

---

## 📚 Документация

### Общая документация
- [README.md](README.md) - Обзор и быстрый старт
- [MIGRATION_GUIDE.md](MIGRATION_GUIDE.md) - Руководство по миграции с версии 1.0

### Специализированная документация
- [docs/NETMAP_EXAMPLES.md](docs/NETMAP_EXAMPLES.md) - Примеры работы с Netmap
- [docs/README_OPENROUTER.md](docs/README_OPENROUTER.md) - OpenRouter API

---

## 📝 Примеры использования

Все примеры находятся в директории [examples/](examples/):

1. **cache_example.php** - Файловое кеширование
2. **email_example.php** - Отправка email
3. **http_example.php** - HTTP клиент
4. **logger_example.php** - Логирование
5. **mysql_example.php** - Работа с MySQL
6. **mysql_connection_factory_example.php** - Фабрика подключений
7. **network_util_example.php** - Сетевые утилиты
8. **openai_example.php** - OpenAI API
9. **openrouter_example.php** - OpenRouter API
10. **openrouter_audio_example.php** - OpenRouter Audio API
11. **openrouter_metrics_example.php** - Метрики OpenRouter
12. **proxypool_example.php** - Пул прокси
13. **proxypool_protocols_example.php** - Протоколы прокси
14. **htmlweb_proxylist_example.php** - Парсинг прокси листов
15. **rss_example.php** - RSS парсер
16. **webt-extractor-example.php** - Извлечение контента
17. **netmap_topology_scan.php** - Сканирование топологии

---

## 🔧 Быстрый старт

```php
require_once __DIR__ . '/../../autoload.php';

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\Http;
use App\Component\Config\ConfigLoader;

// Создание логгера
$logger = new Logger([
    'directory' => '/path/to/logs',
    'fileName' => 'app.log',
]);

// Подключение к БД
$dbConfig = ConfigLoader::load('/path/to/mysql.json');
$db = MySQL::createFromConfig($dbConfig, $logger);

// HTTP клиент
$http = new Http([], $logger);

// Использование
$logger->info('Application started');
$result = $db->query('SELECT * FROM users WHERE id = ?', [1]);
$response = $http->get('https://api.example.com/data');
```

---

## 🔗 Связь с проектами

Базовые классы используются в:

- **TelegramBot** (`src/TelegramBot/`) - Полнофункциональный Telegram бот
- **Rss2Tlg** (`src/Rss2Tlg/`) - RSS to Telegram мониторинг с AI
- **UTM** (`src/UTM/`) - API для биллинговой системы UTM5

---

## 📊 Статистика

- **Всего классов:** 16
- **Директорий:** 4 (Cache, Config, Exception, Netmap)
- **Примеров:** 17
- **Документов:** 3

---

**Версия:** 2.0  
**Дата обновления:** 2025-11-07

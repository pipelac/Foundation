# Базовый набор утилит

Базовый набор компонентов на PHP 8.1+ с интеграцией OpenRouter AI, Telegram, MySQL и инструментов для работы с RSS.

## Архитектура

Монолитная слоистая архитектура с независимыми компонентами:

- **Rss** — парсинг RSS/Atom лент на базе SimplePie (v3.0) с кешированием и санитизацией
- **MySQL** — работа с БД через PDO с строгой типизацией
- **MySQLConnectionFactory** ⚡ — фабрика соединений с кешированием для работы с несколькими БД одновременно
- **OpenRouter** — интеграция с ИИ моделями через OpenRouter API (text2text, text2image, image2text, pdf2text, audio2text, streaming)
- **OpenRouterMetrics** — мониторинг метрик OpenRouter (баланс, токены, стоимость, модели)
- **Telegram** — отправка сообщений и медиафайлов
- **Email** — отправка электронных писем с поддержкой вложений
- **Logger** — структурированное логирование с ротацией файлов + email уведомления администратору (v2.1)
- **Http** — унифицированный HTTP клиент на базе Guzzle

## Требования

- PHP 8.1 или выше
- MySQL 5.5.62 или выше (рекомендуется MySQL 5.7+ или MySQL 8.0+)
- MariaDB 10.0+ также поддерживается
- Расширения: `json`, `libxml`, `curl`, `pdo`, `pdo_mysql`
- Composer (для установки зависимостей: Guzzle, SimplePie)

## Установка

```bash
composer install
```

Если Composer недоступен, можно использовать автозагрузчик `autoload.php`, поставляемый в комплекте.

## Конфигурация

Все компоненты конфигурируются через JSON файлы в директории `config/`:

- `config/logger.json` — настройки логирования
- `config/mysql.json` — параметры подключения к MySQL
- `config/rss.json` — настройки RSS парсера
- `config/openrouter.json` — API ключ OpenRouter
- `config/telegram.json` — токен Telegram бота
- `config/email.json` — параметры отправки почты

## Использование

### Logger

```php
use App\Component\Logger;
use App\Config\ConfigLoader;

$config = ConfigLoader::load(__DIR__ . '/config/logger.json');
$logger = new Logger($config);

$logger->info('Информационное сообщение', ['user_id' => 123]);
$logger->warning('Предупреждение');
$logger->error('Ошибка', ['exception' => 'Детали ошибки']);
$logger->critical('Критическая ошибка системы'); // Отправит email администратору (если настроено)
$logger->debug('Отладочная информация');
```

**Новинка v2.1: Email уведомления администратору**

```php
$logger = new Logger([
    'directory' => '/var/log',
    'file_name' => 'app.log',
    'admin_email' => 'admin@example.com', // Email для критических уведомлений
    'email_config' => [
        'from_email' => 'noreply@example.com',
        'from_name' => 'Logger System',
        'smtp' => [...], // Опционально
    ],
    'email_on_levels' => ['CRITICAL'], // Уровни для отправки email
]);
```

**Конфигурационные параметры:**

- `max_file_size` — максимальный размер одного лог-файла в мегабайтах.
- `log_buffer_size` — размер буфера логов в килобайтах (0 отключает буферизацию).
- `admin_email` — email адрес(а) администратора для уведомлений (строка или массив).
- `email_config` — конфигурация Email класса для отправки уведомлений.
- `email_on_levels` — уровни логирования для отправки email (по умолчанию: ['CRITICAL']).

📖 **Подробная документация:**
- `examples/logger_example.php` — примеры использования

### MySQL

#### Вариант 1: Прямое использование (одна БД)

```php
use App\Component\MySQL;

$config = ConfigLoader::load(__DIR__ . '/config/mysql.json');
$mysql = new MySQL($config['databases']['main'], $logger);

// SELECT запросы
$users = $mysql->query('SELECT * FROM users WHERE status = ?', ['active']);
$user = $mysql->queryOne('SELECT * FROM users WHERE id = ?', [1]);

// INSERT
$userId = $mysql->insert('INSERT INTO users (name, email) VALUES (?, ?)', ['Иван', 'ivan@example.com']);

// UPDATE
$affected = $mysql->update('UPDATE users SET status = ? WHERE id = ?', ['inactive', 5]);

// DELETE
$deleted = $mysql->delete('DELETE FROM users WHERE id = ?', [10]);

// Транзакции
$mysql->beginTransaction();
try {
    $mysql->insert('INSERT INTO users (name) VALUES (?)', ['Тест']);
    $mysql->commit();
} catch (Exception $e) {
    $mysql->rollback();
}
```

#### Вариант 2: Фабрика соединений (несколько БД, кеширование)

**Новое в версии 1.0:** MySQLConnectionFactory для работы с несколькими БД одновременно с автоматическим кешированием соединений.

```php
use App\Component\MySQLConnectionFactory;

$config = ConfigLoader::load(__DIR__ . '/config/mysql.json');
$factory = new MySQLConnectionFactory($config, $logger);

// Работа с основной БД
$mainDb = $factory->getConnection('main');
$users = $mainDb->query('SELECT * FROM users');

// Работа с БД аналитики
$analyticsDb = $factory->getConnection('analytics');
$stats = $analyticsDb->query('SELECT * FROM statistics');

// Повторное получение - из кеша (в 1000x быстрее!)
$mainDb2 = $factory->getConnection('main'); // Возвращает то же соединение
```

**Преимущества фабрики:**
- 🚀 Кеширование соединений (экономия до 99.9% времени на повторных обращениях)
- 🔄 Поддержка множественных БД одновременно
- ⚡ Ленивая инициализация (соединение создается только при необходимости)
- 📊 Централизованное управление всеми соединениями
- ✅ Автоматическая проверка версии MySQL для обеспечения совместимости

**Проверка версии MySQL:**

```php
// Для одного соединения
$version = $mysql->getMySQLVersion();
echo "MySQL версия: {$version['version']}\n";
echo "Поддерживается: " . ($version['is_supported'] ? 'Да' : 'Нет') . "\n";
echo "Рекомендуется: " . ($version['is_recommended'] ? 'Да (5.5.62+)' : 'Обновление рекомендуется') . "\n";

// Для всех соединений через фабрику
$versions = $factory->getMySQLVersions();
$allSupported = $factory->areAllVersionsSupported();
$allRecommended = $factory->areAllVersionsRecommended();
```

📖 **Подробная документация:** 
- `docs/MYSQL_CONNECTION_FACTORY.md` — полная документация фабрики
- `docs/MYSQL_VERSION_COMPATIBILITY.md` — совместимость версий MySQL
- `MYSQL_FACTORY_UPGRADE.md` — руководство по миграции

### RSS (SimplePie)

**Версия 3.0** с использованием SimplePie для улучшенной производительности и надежности.

```php
use App\Component\Rss;

$config = ConfigLoader::load(__DIR__ . '/config/rss.json');
$rss = new Rss($config, $logger);

$feed = $rss->fetch('https://example.com/feed.xml');

echo $feed['title'];
echo $feed['description'];
echo $feed['image']; // Новое: URL логотипа ленты

foreach ($feed['items'] as $item) {
    echo $item['title'];
    echo $item['link'];
    
    // Используем полный контент, если доступен
    $text = !empty($item['content']) ? $item['content'] : $item['description'];
    echo $text;
    
    // Дата публикации
    if ($item['published_at'] !== null) {
        echo $item['published_at']->format('Y-m-d H:i:s');
    }
    
    // Медиа вложения (подкасты, видео)
    foreach ($item['enclosures'] as $media) {
        echo $media['url']; // URL медиа файла
        echo $media['type']; // audio/mpeg, video/mp4, и т.д.
    }
}
```

**Новые возможности:**
- Встроенное кеширование для повышения производительности
- Санитизация HTML контента
- Поддержка RSS 0.9-2.0, Atom 0.3-1.0, RDF
- Медиа вложения (enclosures)
- Расширенная информация (image, copyright, generator, content)

📖 **Подробная документация:** `RSS_README.md` и `MIGRATION_GUIDE_RSS.md`

### OpenRouter

```php
use App\Component\OpenRouter;

$config = ConfigLoader::load(__DIR__ . '/config/openrouter.json');
$openRouter = new OpenRouter($config, $logger);

// Text to Text - текстовая генерация
$response = $openRouter->text2text('openai/gpt-3.5-turbo', 'Привет, как дела?');

// Text to Image - генерация изображений
$imageUrl = $openRouter->text2image('openai/gpt-5-image', 'Красивый закат над океаном');

// Image to Text - анализ изображений
$description = $openRouter->image2text(
    'openai/gpt-4-vision-preview',
    'https://example.com/image.jpg',
    'Что на изображении?'
);

// PDF to Text - извлечение текста из PDF
$pdfText = $openRouter->pdf2text(
    'anthropic/claude-3-opus',
    'https://example.com/document.pdf'
);

// Audio to Text - распознавание речи
$transcript = $openRouter->audio2text(
    'openai/gpt-4o-audio-preview',
    'https://example.com/audio.mp3'
);

// Streaming - потоковая передача текста
$openRouter->textStream('openai/gpt-3.5-turbo', 'Расскажи историю', function (string $chunk) {
    echo $chunk;
});
```

📖 **Подробная документация:** `docs/OPENROUTER.md`

### OpenRouterMetrics

```php
use App\Component\OpenRouterMetrics;

$config = ConfigLoader::load(__DIR__ . '/config/openrouter.json');
$metrics = new OpenRouterMetrics($config, $logger);

// Информация о ключе и балансе
$keyInfo = $metrics->getKeyInfo();
$balance = $metrics->getBalance();
echo "Баланс: \${$balance}\n";

// Статистика использования
$stats = $metrics->getUsageStats();
echo "Использовано: {$stats['usage_percent']}%\n";

// Список доступных моделей
$models = $metrics->getModels();
foreach ($models as $model) {
    echo "{$model['name']} - \${$model['pricing']['prompt']} за 1M токенов\n";
}

// Оценка стоимости запроса
$estimate = $metrics->estimateCost('openai/gpt-3.5-turbo', 1000, 500);
echo "Стоимость: \${$estimate['total_cost']}\n";

// Проверка баланса перед запросом
if ($metrics->hasEnoughBalance($estimate['total_cost'])) {
    // Выполнить запрос
}

// Полная информация об аккаунте
$status = $metrics->getAccountStatus();
```

📖 **Подробная документация:** `docs/OPENROUTER_METRICS.md`

### Telegram

```php
use App\Component\Telegram;

$config = ConfigLoader::load(__DIR__ . '/config/telegram.json');
$telegram = new Telegram($config, $logger);

// Отправка текста
$telegram->sendText('123456789', 'Привет из PHP!');

// Отправка изображения
$telegram->sendPhoto('123456789', '/path/to/image.jpg', ['caption' => 'Описание']);

// Отправка видео
$telegram->sendVideo('123456789', 'https://example.com/video.mp4');

// Отправка аудио
$telegram->sendAudio('123456789', '/path/to/audio.mp3');

// Отправка документа
$telegram->sendDocument('123456789', '/path/to/document.pdf');
```

### Email

```php
use App\Component\Email;

$config = ConfigLoader::load(__DIR__ . '/config/email.json');
$email = new Email($config, $logger);

$email->send(
    ['user@example.com', 'team@example.com'],
    'Добро пожаловать',
    '<p>Спасибо за регистрацию!</p>',
    [
        'is_html' => true,
        'cc' => 'manager@example.com',
        'attachments' => [
            ['path' => __DIR__ . '/files/presentation.pdf', 'name' => 'Презентация.pdf'],
        ],
    ]
);
```

### Http

```php
use App\Component\Http;

// Простой GET запрос
$http = new Http(['timeout' => 10], $logger);
$response = $http->request('GET', 'https://example.com/api/data');
echo $response->getBody();

// POST запрос с JSON
$response = $http->request('POST', 'https://example.com/api', [
    'json' => ['key' => 'value'],
    'headers' => ['Authorization' => 'Bearer token'],
]);

// Streaming запрос
$http->requestStream('GET', 'https://example.com/stream', function (string $chunk) {
    echo $chunk;
}, ['headers' => ['Accept' => 'text/event-stream']]);
```

## Пример запуска

```bash
chmod +x bin/example.php
php bin/example.php

# Проверка автозагрузки
php bin/test_autoload.php
```

## Структура проекта

```
.
├── bin/                    # Исполняемые скрипты
│   └── example.php
├── config/                 # Конфигурационные файлы
│   ├── email.json
│   ├── logger.json
│   ├── mysql.json          # Конфигурация с поддержкой множественных БД
│   ├── openrouter.json
│   ├── rss.json
│   └── telegram.json
├── docs/                   # Документация
│   └── MYSQL_CONNECTION_FACTORY.md
├── examples/               # Примеры использования
│   ├── mysql_example.php
│   ├── mysql_connection_factory_example.php
│   └── ...
├── logs/                   # Директория логов
├── src/                    # Исходный код
│   ├── Config/
│   │   └── ConfigLoader.php
│   ├── Exception/
│   │   ├── MySQLException.php
│   │   ├── MySQLConnectionException.php
│   │   └── MySQLTransactionException.php
│   ├── Email.class.php
│   ├── Http.class.php
│   ├── Logger.class.php
│   ├── MySQL.class.php
│   ├── MySQLConnectionFactory.class.php    # Новое
│   ├── OpenRouter.class.php
│   ├── OpenRouterMetrics.class.php
│   ├── Rss.class.php
│   └── Telegram.class.php
├── .gitignore
├── composer.json
├── MYSQL_FACTORY_UPGRADE.md    # Руководство по обновлению
└── README.md
```

## Стандарты кодирования

- PSR-12
- Строгая типизация (`declare(strict_types=1)`)
- PHP 8.1+ синтаксис
- Полная PHPDoc документация на русском языке
- Обработка исключений на каждом уровне

## Лицензия

MIT

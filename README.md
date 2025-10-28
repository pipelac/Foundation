# RSS Service Boilerplate

Базовый набор компонентов на PHP 8.1+ для создания RSS сервисов с интеграцией OpenRouter AI, Telegram и MySQL.

## Архитектура

Монолитная слоистая архитектура с независимыми компонентами:

- **Rss** — парсинг RSS/Atom лент
- **MySQL** — работа с БД через PDO
- **OpenRouter** — интеграция с ИИ моделями (text2text, text2image, image2text, streaming)
- **Telegram** — отправка сообщений и медиафайлов
- **Email** — отправка электронных писем с поддержкой вложений
- **Logger** — структурированное логирование с ротацией файлов
- **Http** — унифицированный HTTP клиент на базе Guzzle

## Требования

- PHP 8.1 или выше
- Расширения: `json`, `libxml`, `curl`, `pdo`, `pdo_mysql`
- Composer (для установки Guzzle)

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
$logger->debug('Отладочная информация');
```

### MySQL

```php
use App\Component\MySQL;

$config = ConfigLoader::load(__DIR__ . '/config/mysql.json');
$mysql = new MySQL($config, $logger);

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

### RSS

```php
use App\Component\Rss;

$config = ConfigLoader::load(__DIR__ . '/config/rss.json');
$rss = new Rss($config, $logger);

$feed = $rss->fetch('https://example.com/feed.xml');

echo $feed['title'];
echo $feed['description'];

foreach ($feed['items'] as $item) {
    echo $item['title'];
    echo $item['link'];
    echo $item['description'];
    echo $item['published_at']->format('Y-m-d H:i:s');
}
```

### OpenRouter

```php
use App\Component\OpenRouter;

$config = ConfigLoader::load(__DIR__ . '/config/openrouter.json');
$openRouter = new OpenRouter($config, $logger);

// Text to Text
$response = $openRouter->text2text('openai/gpt-3.5-turbo', 'Привет, как дела?');

// Text to Image
$imageUrl = $openRouter->text2image('stability-ai/stable-diffusion-xl', 'Beautiful landscape');

// Image to Text
$description = $openRouter->image2text('openai/gpt-4-vision', 'https://example.com/image.jpg', 'Что на изображении?');

// Streaming
$openRouter->textStream('openai/gpt-3.5-turbo', 'Расскажи историю', function (string $chunk) {
    echo $chunk;
});
```

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
│   ├── mysql.json
│   ├── openrouter.json
│   ├── rss.json
│   └── telegram.json
├── logs/                   # Директория логов
├── src/                    # Исходный код
│   ├── Config/
│   │   └── ConfigLoader.php
│   ├── Email.class.php
│   ├── Http.class.php
│   ├── Logger.class.php
│   ├── MySQL.class.php
│   ├── OpenRouter.class.php
│   ├── Rss.class.php
│   └── Telegram.class.php
├── .gitignore
├── composer.json
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

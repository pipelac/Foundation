# Базовый набор утилит

Базовый набор компонентов на PHP 8.1+ с интеграцией OpenRouter AI, Telegram, MySQL и инструментов для работы с RSS.

## Архитектура

Монолитная слоистая архитектура с независимыми компонентами:

- **Rss** — парсинг RSS/Atom лент на базе SimplePie (v1.0.0) с кешированием и санитизацией
- **MySQL** — работа с БД через PDO с строгой типизацией
- **MySQLConnectionFactory** ⚡ — фабрика соединений с кешированием для работы с несколькими БД одновременно
- **OpenRouter** — интеграция с ИИ моделями через OpenRouter API (text2text, text2image, image2text, pdf2text, audio2text, streaming)
- **OpenRouterMetrics** — мониторинг метрик OpenRouter (баланс, токены, стоимость, модели)
- **OpenAi** ✨ — интеграция с OpenAI API (GPT-4o, DALL-E 3, Whisper, Vision, embeddings, moderation, streaming)
- **Telegram** — отправка сообщений и медиафайлов
- **Email** — отправка электронных писем с поддержкой вложений
- **Logger** — структурированное логирование с ротацией файлов + email уведомления администратору (v1.0.0)
- **Http** — унифицированный HTTP клиент на базе Guzzle
- **ProxyPool** 🔄 — легковесный менеджер пула прокси-серверов с ротацией, health-check и автоматическим retry
- **htmlWebProxyList** 🌐 — получение списка прокси-серверов с htmlweb.ru API для использования в ProxyPool
- **UTM Module** 💼 — API для работы с биллинговой системой UTM5 (лицевые счета, тарифы, услуги, группы) + утилиты (валидация, форматирование, транслитерация)

## Требования

- PHP 8.1 или выше
- MySQL 5.5.62 или выше (рекомендуется MySQL 5.7+ или MySQL 8.0+)
- MariaDB 10.0+ также поддерживается
- Расширения: `ext-json`, `ext-curl`, `ext-pdo`, `ext-pdo_mysql`
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
- `config/proxypool.json` — конфигурация пула прокси-серверов

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

**Новинка v1.0.0: Email уведомления администратору**

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

**Новое в версии v1.0.0:** MySQLConnectionFactory для работы с несколькими БД одновременно с автоматическим кешированием соединений.

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

**Версия v1.0.0** с использованием SimplePie для улучшенной производительности и надежности.

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

### OpenAi

```php
use App\Component\OpenAi;

$config = [
    'api_key' => 'sk-proj-your-api-key',
    'organization' => 'org-123456', // Опционально
    'timeout' => 60,
    'retries' => 3,
];
$openAi = new OpenAi($config, $logger);

// Text to Text - текстовая генерация (GPT-4o, GPT-4o-mini)
$response = $openAi->text2text(
    prompt: 'Объясни квантовую физику простым языком',
    model: 'gpt-4o-mini',
    options: [
        'temperature' => 0.7,
        'max_tokens' => 500,
        'system' => 'Ты - опытный преподаватель физики',
    ]
);

// Text to Image - генерация изображений (DALL-E 3)
$imageUrl = $openAi->text2image(
    prompt: 'Футуристический город на закате',
    model: 'dall-e-3',
    options: [
        'size' => '1024x1024',
        'quality' => 'hd',
        'style' => 'vivid',
    ]
);

// Image to Text - анализ изображений (GPT-4 Vision)
$description = $openAi->image2text(
    imageUrl: 'https://example.com/image.jpg',
    question: 'Что изображено на этой фотографии?',
    model: 'gpt-4o',
    options: ['detail' => 'high']
);

// Audio to Text - транскрипция аудио (Whisper)
$transcript = $openAi->audio2text(
    audioUrl: 'https://example.com/audio.mp3',
    options: [
        'language' => 'ru',
        'prompt' => 'Это интервью о технологиях',
    ]
);

// Streaming - потоковая передача текста
$openAi->textStream(
    prompt: 'Напиши стихотворение о весне',
    callback: function (string $chunk): void {
        echo $chunk;
        flush();
    }
);

// Embeddings - создание эмбеддингов для текста
$embeddings = $openAi->embeddings(
    input: 'Текст для создания эмбеддингов',
    model: 'text-embedding-3-small',
    options: ['dimensions' => 512]
);

// Moderation - проверка контента на нарушения
$result = $openAi->moderation('Текст для проверки');
if ($result['flagged']) {
    echo "Найдены нарушения правил модерации";
}
```

📖 **Подробная документация:** `OPENAI_README.md` и `examples/openai_example.php`

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

### ProxyPool

```php
use App\Component\ProxyPool;

// Инициализация с конфигурацией
// Поддержка протоколов: HTTP, HTTPS, SOCKS4, SOCKS5
$config = [
    'proxies' => [
        'http://proxy1.example.com:8080',
        'http://user:pass@proxy2.example.com:3128',
        'https://secure-proxy.example.com:8443',
        'socks4://socks4-proxy.example.com:1080',
        'socks5://proxy3.example.com:1080',
    ],
    'rotation_strategy' => ProxyPool::ROTATION_ROUND_ROBIN, // или ROTATION_RANDOM
    'health_check_url' => 'https://httpbin.org/ip',
    'health_check_timeout' => 5,
    'auto_health_check' => true,
    'max_retries' => 3,
];
$proxyPool = new ProxyPool($config, $logger);

// HTTP запросы через прокси с автоматическим retry
$response = $proxyPool->get('https://api.example.com/data');
$response = $proxyPool->post('https://api.example.com/create', [
    'json' => ['name' => 'John Doe']
]);

// Управление прокси
$proxyPool->addProxy('http://new-proxy.example.com:8080');
$proxyPool->removeProxy('http://old-proxy.example.com:8080');

// Health-check и статистика
$proxyPool->checkAllProxies();
$stats = $proxyPool->getStatistics();
echo "Живых прокси: {$stats['alive_proxies']}\n";
echo "Мёртвых прокси: {$stats['dead_proxies']}\n";
echo "Успешность запросов: {$stats['success_rate']}%\n";

// Получение прокси вручную
$proxy = $proxyPool->getNextProxy(); // По стратегии ротации
$proxy = $proxyPool->getRandomProxy(); // Случайный
```

📖 **Подробная документация:** `PROXYPOOL_README.md` и `examples/proxypool_example.php`

### htmlWebProxyList

Получение списка прокси-серверов с htmlweb.ru API для автоматической загрузки в ProxyPool.

```php
use App\Component\htmlWebProxyList;
use App\Component\ProxyPool;

// Создаем источник прокси
$htmlWebProxy = new htmlWebProxyList([
    'country' => 'US',
    'work' => 'yes',
    'perpage' => 50,
    'type' => 'http',
    'speed_max' => 2000,
], $logger);

// Получаем список прокси
$proxies = $htmlWebProxy->getProxies();
echo "Получено прокси: " . count($proxies);

// Или загружаем напрямую в ProxyPool
$proxyPool = new ProxyPool([
    'rotation_strategy' => ProxyPool::ROTATION_ROUND_ROBIN,
]);

$added = $htmlWebProxy->loadIntoProxyPool($proxyPool);
echo "Добавлено в пул: {$added}";
```

📖 **Подробная документация:** `HTMLWEB_PROXYLIST_README.md` и `examples/htmlweb_proxylist_example.php`

### UTM Module

Современный API для работы с биллинговой системой UTM5 (полная переработка старого PHP5.6 кода).

```php
use App\Config\ConfigLoader;
use App\Component\Logger;
use App\Component\MySQL;
use App\Component\UTM\Account;
use App\Component\UTM\Utils;

// Загрузка конфигурации
$config = ConfigLoader::load(__DIR__ . '/Config/utm.json');

// Инициализация компонентов
$logger = new Logger($config['logger']);
$db = new MySQL($config['database'], $logger);
$account = new Account($db, $logger);

// Работа с лицевыми счетами
try {
    // Баланс в разных форматах
    $balance = $account->getBalance(123);
    echo "Баланс: {$balance}\n";
    
    // Текущие тарифы
    $tariffs = $account->getCurrentTariff(123, 'array');
    foreach ($tariffs as $id => $name) {
        echo "Тариф ID {$id}: {$name}\n";
    }
    
    // Подключенные услуги с ценами
    $services = $account->getServices(123, 'array');
    foreach ($services as $id => $info) {
        echo "{$info['name']}: {$info['cost']} руб.\n";
    }
} catch (\App\Component\Exception\UTM\AccountException $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}

// Использование утилит
$phone = Utils::validateMobileNumber('+7 909 123-45-67'); // "79091234567"
$rounded = Utils::doRound(1234.5678, 2); // "1234.57"
$time = Utils::min2hour(90, true); // "1 час 30 минут"
$word = Utils::numWord(5, ['день', 'дня', 'дней']); // "5 дней"
```

📖 **Подробная документация:** `src/UTM/docs/UTM_MODULE.md` (30+ методов), `src/UTM/README.md` и `src/UTM/examples/utm_account_example.php`

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
├── docs/                   # Документация компонентов
│   ├── MYSQL_CONNECTION_FACTORY.md
│   └── ...
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
│   │   ├── MySQLTransactionException.php
│   │   ├── HtmlWebProxyListException.php
│   │   ├── HtmlWebProxyListValidationException.php
│   │   └── UTM/                           # Исключения UTM модуля
│   │       ├── AccountException.php
│   │       └── UtilsValidationException.php
│   ├── Email.class.php
│   ├── Http.class.php
│   ├── htmlWebProxyList.class.php
│   ├── Logger.class.php
│   ├── MySQL.class.php
│   ├── MySQLConnectionFactory.class.php
│   ├── OpenRouter.class.php
│   ├── OpenRouterMetrics.class.php
│   ├── ProxyPool.class.php
│   ├── Rss.class.php
│   ├── Telegram.class.php
│   └── UTM/                                 # UTM Module (полный проект)
│       ├── Account.php                      # API для UTM5 биллинга (30+ методов)
│       ├── Utils.php                        # Утилиты и валидация
│       ├── INDEX.md                         # Навигация по модулю
│       ├── README.md                        # Быстрый старт
│       ├── MIGRATION_GUIDE.md               # Руководство по миграции
│       ├── SUMMARY.md                       # Краткая сводка
│       ├── config/                          # Конфигурация
│       │   ├── account.json                 # Дилеры, тарифы, VLAN
│       │   ├── utm_example.json             # Пример для подключения к БД
│       │   └── README.md
│       ├── docs/                            # Документация
│       │   ├── UTM_MODULE.md                # 📖 Полная документация API
│       │   ├── UTM_README_FIRST.md
│       │   ├── UTM_CHANGELOG.md
│       │   ├── UTM_MIGRATION_SUMMARY.md
│       │   └── CHANGELOG_UTM_ACCOUNT.md
│       ├── examples/                        # Примеры использования
│       │   ├── utm_account_example.php
│       │   └── utm_account_search_example.php
│       └── tests/                           # Unit-тесты
│           └── README.md
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

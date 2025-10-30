# ProxyPool - Менеджер пула прокси-серверов

Легковесный менеджер пула прокси-серверов с автоматической ротацией, health-check и retry механизмом для PHP 8.1+.

## Основные возможности

- ✅ **Управление пулом прокси** - добавление, удаление, получение прокси
- 🔄 **Гибкая ротация** - поддержка Round-robin и Random стратегий
- 🏥 **Health-check** - автоматическая проверка доступности прокси
- ♻️ **Автоматический retry** - повторные попытки с переключением на другой прокси
- 📊 **Детальная статистика** - отслеживание успешности каждого прокси
- 🔗 **Интеграция с Http.class.php** - выполнение HTTP запросов через прокси
- 📝 **Полное логирование** - поддержка Logger.class.php
- 🎯 **Строгая типизация** - использование strict types для надежности

## Установка и подключение

```php
require_once __DIR__ . '/autoload.php';

use App\Component\ProxyPool;
use App\Component\Logger;
```

## Быстрый старт

### Инициализация из конфигурационного файла

```php
use App\Component\ProxyPool;
use App\Component\Logger;

$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'file_name' => 'proxypool.log',
]);

// Загрузка из конфигурационного файла
$proxyPool = ProxyPool::fromConfig(
    __DIR__ . '/config/proxypool.json',
    $logger
);
```

### Базовая инициализация

```php
// Без конфигурации (минимальные настройки)
$proxyPool = new ProxyPool();

// С конфигурацией
$config = [
    'proxies' => [
        'http://proxy1.example.com:8080',
        'http://user:pass@proxy2.example.com:3128',
        'https://secure-proxy.example.com:8443',
        'socks4://socks4-proxy.example.com:1080',
        'socks5://proxy3.example.com:1080',
    ],
    'rotation_strategy' => ProxyPool::ROTATION_ROUND_ROBIN,
    'max_retries' => 3,
];

$proxyPool = new ProxyPool($config);
```

### С логированием

```php
$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'file_name' => 'proxypool.log',
]);

$proxyPool = new ProxyPool($config, $logger);
```

## Конфигурация

### Параметры конструктора

| Параметр | Тип | По умолчанию | Описание |
|----------|-----|--------------|----------|
| `proxies` | `string[]` | `[]` | Массив прокси URL |
| `rotation_strategy` | `string` | `round_robin` | Стратегия ротации: `round_robin` или `random` |
| `health_check_url` | `string` | `https://www.google.com` | URL для проверки доступности прокси |
| `health_check_timeout` | `int` | `5` | Таймаут health-check в секундах |
| `health_check_interval` | `int` | `300` | Интервал между проверками (секунды) |
| `auto_health_check` | `bool` | `true` | Автоматическая проверка при инициализации |
| `max_retries` | `int` | `3` | Максимальное количество попыток |
| `http_config` | `array` | `[]` | Конфигурация для Http клиента |

### Форматы прокси URL

Поддерживаются следующие форматы:

```
http://host:port
https://host:port
socks4://host:port
socks5://host:port
http://username:password@host:port
```

> 📖 **Подробная документация о поддержке протоколов:**  
> См. [docs/PROXY_PROTOCOLS_SUPPORT.md](docs/PROXY_PROTOCOLS_SUPPORT.md) для детальной информации о всех поддерживаемых типах прокси, примерах и ограничениях.

### Пример конфигурационного файла

Создайте файл `config/proxypool.json`:

```json
{
    "proxies": [
        "http://proxy1.example.com:8080",
        "http://user:pass@proxy2.example.com:3128",
        "https://secure-proxy.example.com:8443",
        "socks4://socks4-proxy.example.com:1080",
        "socks5://proxy3.example.com:1080",
        "socks5://admin:secret@socks5-auth.example.com:1080"
    ],
    "rotation_strategy": "round_robin",
    "health_check_url": "https://httpbin.org/ip",
    "health_check_timeout": 5,
    "max_retries": 3
}
```

## Использование

### Управление прокси

#### Добавление прокси

```php
$proxyPool->addProxy('http://new-proxy.example.com:8080');
$proxyPool->addProxy('socks5://user:pass@proxy.example.com:1080');
```

#### Удаление прокси

```php
$proxyPool->removeProxy('http://proxy.example.com:8080');
```

#### Очистка всего пула

```php
$proxyPool->clearProxies();
```

### Получение прокси

#### Получение следующего прокси (по стратегии ротации)

```php
$proxy = $proxyPool->getNextProxy();
// Вернет: 'http://proxy1.example.com:8080' или null если нет доступных
```

#### Получение случайного прокси

```php
$proxy = $proxyPool->getRandomProxy();
```

#### Получение всех прокси

```php
$allProxies = $proxyPool->getAllProxies();
// Возвращает: ['proxy_url' => ['url' => '...', 'alive' => true], ...]
```

### Ротация прокси

#### Round-robin стратегия

```php
$proxyPool = new ProxyPool([
    'proxies' => ['http://p1:8080', 'http://p2:8080', 'http://p3:8080'],
    'rotation_strategy' => ProxyPool::ROTATION_ROUND_ROBIN,
]);

// Последовательный обход
$proxy1 = $proxyPool->getNextProxy(); // p1
$proxy2 = $proxyPool->getNextProxy(); // p2
$proxy3 = $proxyPool->getNextProxy(); // p3
$proxy4 = $proxyPool->getNextProxy(); // p1 (начинается заново)
```

#### Random стратегия

```php
$proxyPool = new ProxyPool([
    'proxies' => ['http://p1:8080', 'http://p2:8080', 'http://p3:8080'],
    'rotation_strategy' => ProxyPool::ROTATION_RANDOM,
]);

// Случайный выбор
$proxy1 = $proxyPool->getNextProxy(); // p2 (случайный)
$proxy2 = $proxyPool->getNextProxy(); // p1 (случайный)
$proxy3 = $proxyPool->getNextProxy(); // p3 (случайный)
```

### Health-check

#### Проверка конкретного прокси

```php
$isAlive = $proxyPool->checkProxyHealth('http://proxy.example.com:8080');

if ($isAlive) {
    echo "Прокси доступен";
} else {
    echo "Прокси недоступен";
}
```

#### Проверка всех прокси

```php
$proxyPool->checkAllProxies();

// Получение результатов
$stats = $proxyPool->getStatistics();
echo "Живых прокси: " . $stats['alive_proxies'];
echo "Мёртвых прокси: " . $stats['dead_proxies'];
```

#### Ручное управление статусом

```php
// Пометить как живой
$proxyPool->markProxyAsAlive('http://proxy.example.com:8080');

// Пометить как мёртвый
$proxyPool->markProxyAsDead('http://proxy.example.com:8080');
```

### HTTP запросы через прокси

#### GET запрос

```php
try {
    $response = $proxyPool->get('https://api.example.com/data');
    
    echo "Код ответа: " . $response->getStatusCode();
    echo "Тело: " . $response->getBody()->getContents();
} catch (ProxyPoolException $e) {
    echo "Ошибка: " . $e->getMessage();
}
```

#### POST запрос

```php
$response = $proxyPool->post('https://api.example.com/create', [
    'json' => [
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ]
]);
```

#### PUT и DELETE запросы

```php
$response = $proxyPool->put('https://api.example.com/update/123', [
    'json' => ['status' => 'active']
]);

$response = $proxyPool->delete('https://api.example.com/delete/123');
```

#### Универсальный запрос

```php
$response = $proxyPool->request('PATCH', 'https://api.example.com/patch/123', [
    'json' => ['field' => 'value']
]);
```

#### Настройка retry

```php
// Использование кастомного количества попыток
$response = $proxyPool->get(
    'https://api.example.com/data',
    [], // options
    5   // maxRetries - 5 попыток вместо значения по умолчанию
);
```

### Получение HTTP клиента

Для более сложных запросов можно получить прямой доступ к Http клиенту:

```php
// Получить клиент с автоматически выбранным прокси
$http = $proxyPool->getHttpClient();

// Получить клиент с конкретным прокси
$http = $proxyPool->getHttpClient('http://specific-proxy.example.com:8080');

// Использовать клиент
$response = $http->get('https://api.example.com/data');
```

### Статистика

#### Получение полной статистики

```php
$stats = $proxyPool->getStatistics();

echo "=== Общая статистика ===\n";
echo "Всего прокси: {$stats['total_proxies']}\n";
echo "Живых: {$stats['alive_proxies']}\n";
echo "Мёртвых: {$stats['dead_proxies']}\n";
echo "Стратегия: {$stats['rotation_strategy']}\n";
echo "Всего запросов: {$stats['total_requests']}\n";
echo "Успешных: {$stats['successful_requests']}\n";
echo "Неудачных: {$stats['failed_requests']}\n";
echo "Повторов: {$stats['total_retries']}\n";
echo "Успешность: {$stats['success_rate']}%\n";

echo "\n=== Статистика по прокси ===\n";
foreach ($stats['proxies'] as $proxy) {
    echo "Прокси: {$proxy['url']}\n";
    echo "  Статус: " . ($proxy['alive'] ? 'Живой' : 'Мёртвый') . "\n";
    echo "  Успешных запросов: {$proxy['success_count']}\n";
    echo "  Неудачных запросов: {$proxy['fail_count']}\n";
    echo "  Успешность: {$proxy['success_rate']}%\n";
    echo "  Последняя проверка: {$proxy['last_check_human']}\n";
    if ($proxy['last_error']) {
        echo "  Последняя ошибка: {$proxy['last_error']}\n";
    }
    echo "\n";
}
```

#### Сброс статистики

```php
// Сбросить счетчики, но сохранить прокси
$proxyPool->resetStatistics();
```

## Работа с RSS и парсингом

ProxyPool идеально подходит для работы с RSS лентами и парсингом сайтов:

### Пример с RSS

```php
use App\Component\Rss;

// Создаем пул прокси
$proxyPool = new ProxyPool([
    'proxies' => [
        'http://proxy1.example.com:8080',
        'http://proxy2.example.com:8080',
    ],
]);

// Получаем HTTP клиент с прокси
$httpForRss = $proxyPool->getHttpClient();

// Загружаем RSS ленту через прокси
$rss = new Rss([
    'timeout' => 30,
], $logger);

try {
    // Выполняем запрос к RSS через прокси
    $response = $proxyPool->get('https://example.com/feed.xml');
    $xmlContent = $response->getBody()->getContents();
    
    // Парсим RSS
    // ... обработка RSS данных
    
} catch (ProxyPoolException $e) {
    echo "Не удалось загрузить RSS через прокси: " . $e->getMessage();
}
```

### Пример парсинга сайта

```php
// Парсинг нескольких страниц через разные прокси
$urls = [
    'https://example.com/page1',
    'https://example.com/page2',
    'https://example.com/page3',
];

foreach ($urls as $url) {
    try {
        $response = $proxyPool->get($url);
        $html = $response->getBody()->getContents();
        
        // Обработка HTML
        // ...
        
        echo "Загружена страница: {$url}\n";
    } catch (ProxyPoolException $e) {
        echo "Ошибка загрузки {$url}: {$e->getMessage()}\n";
    }
}
```

## Обработка ошибок

ProxyPool использует собственные исключения:

```php
use App\Component\Exception\ProxyPoolException;
use App\Component\Exception\ProxyPoolValidationException;

try {
    $proxyPool = new ProxyPool($config);
    $response = $proxyPool->get('https://api.example.com/data');
    
} catch (ProxyPoolValidationException $e) {
    // Ошибка конфигурации или валидации
    echo "Ошибка валидации: " . $e->getMessage();
    
} catch (ProxyPoolException $e) {
    // Общая ошибка ProxyPool (все прокси недоступны, нет прокси и т.д.)
    echo "Ошибка ProxyPool: " . $e->getMessage();
    
} catch (Exception $e) {
    // Прочие ошибки
    echo "Неожиданная ошибка: " . $e->getMessage();
}
```

## Продвинутые примеры

### Динамическое управление пулом

```php
$proxyPool = new ProxyPool([
    'rotation_strategy' => ProxyPool::ROTATION_ROUND_ROBIN,
    'auto_health_check' => true,
]);

// Добавляем прокси динамически
$newProxies = [
    'http://proxy1.example.com:8080',
    'http://proxy2.example.com:8080',
    'http://proxy3.example.com:8080',
];

foreach ($newProxies as $proxy) {
    $proxyPool->addProxy($proxy);
}

// Проверяем все прокси
$proxyPool->checkAllProxies();

// Удаляем мёртвые прокси
$stats = $proxyPool->getStatistics();
foreach ($stats['proxies'] as $proxyInfo) {
    if (!$proxyInfo['alive']) {
        $proxyPool->removeProxy($proxyInfo['url']);
        echo "Удален мёртвый прокси: {$proxyInfo['url']}\n";
    }
}
```

### Мониторинг производительности

```php
// Периодический мониторинг
while (true) {
    // Проверяем прокси каждые 5 минут
    $proxyPool->checkAllProxies();
    
    $stats = $proxyPool->getStatistics();
    
    // Отправляем алерт если мало живых прокси
    if ($stats['alive_proxies'] < 2) {
        // Отправка уведомления администратору
        mail('admin@example.com', 
             'ProxyPool Alert', 
             "Внимание! Осталось только {$stats['alive_proxies']} живых прокси!");
    }
    
    sleep(300); // 5 минут
}
```

### Интеграция с базой данных

```php
use App\Component\MySQL;

$mysql = new MySQL($dbConfig);

// Загружаем прокси из базы данных
$proxiesFromDb = $mysql->query('SELECT proxy_url FROM proxies WHERE active = 1');

$proxyPool = new ProxyPool([
    'rotation_strategy' => ProxyPool::ROTATION_RANDOM,
]);

foreach ($proxiesFromDb as $row) {
    $proxyPool->addProxy($row['proxy_url']);
}

// Выполняем работу
try {
    $response = $proxyPool->get('https://api.example.com/data');
    
    // Сохраняем результат в БД
    $mysql->query(
        'INSERT INTO requests_log (url, status, proxy_used) VALUES (?, ?, ?)',
        ['https://api.example.com/data', $response->getStatusCode(), $proxyPool->getNextProxy()]
    );
    
} catch (ProxyPoolException $e) {
    // Логируем ошибку в БД
    $mysql->query(
        'INSERT INTO errors_log (error_message) VALUES (?)',
        [$e->getMessage()]
    );
}
```

## API Reference

### Публичные методы

#### Конструктор
```php
__construct(array $config = [], ?Logger $logger = null): void
```

#### Управление прокси
```php
addProxy(string $proxy): void
removeProxy(string $proxy): void
clearProxies(): void
getAllProxies(): array
```

#### Получение прокси
```php
getNextProxy(): ?string
getRandomProxy(): ?string
```

#### Health-check
```php
checkProxyHealth(string $proxy): bool
checkAllProxies(): void
markProxyAsAlive(string $proxy): void
markProxyAsDead(string $proxy): void
```

#### HTTP запросы
```php
request(string $method, string $uri, array $options = [], int $maxRetries = 0): ResponseInterface
get(string $uri, array $options = [], int $maxRetries = 0): ResponseInterface
post(string $uri, array $options = [], int $maxRetries = 0): ResponseInterface
put(string $uri, array $options = [], int $maxRetries = 0): ResponseInterface
delete(string $uri, array $options = [], int $maxRetries = 0): ResponseInterface
```

#### Утилиты
```php
getStatistics(): array
resetStatistics(): void
getHttpClient(?string $proxy = null): Http
```

### Константы

```php
ProxyPool::ROTATION_ROUND_ROBIN  // 'round_robin'
ProxyPool::ROTATION_RANDOM       // 'random'
```

## Требования

- PHP 8.1 или выше
- Guzzle HTTP Client (для Http.class.php)
- Logger.class.php (опционально, для логирования)
- Http.class.php (для HTTP запросов)

## Производительность

- Легковесная реализация без избыточных абстракций
- Минимальное использование памяти
- Эффективная ротация O(1) для round-robin
- Кэширование статуса прокси для уменьшения health-check запросов

## Безопасность

- Валидация всех входных данных
- Строгая типизация параметров
- Обработка исключений на каждом уровне
- Поддержка SSL/TLS прокси
- Опциональное отключение верификации SSL

## Лучшие практики

1. **Всегда используйте логирование** в production среде
2. **Настройте адекватные таймауты** для health-check
3. **Регулярно проверяйте статистику** для мониторинга производительности
4. **Используйте health-check интервалы** чтобы не перегружать прокси
5. **Храните прокси в конфигурационном файле** для удобного управления
6. **Обрабатывайте исключения** при работе с внешними API

## Лицензия

Этот компонент является частью корпоративной библиотеки PHP компонентов.

## Поддержка

Для вопросов и поддержки обращайтесь к документации проекта или к команде разработки.

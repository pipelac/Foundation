# Поддержка протоколов прокси в ProxyPool

## Обзор

Библиотека **ProxyPool** полностью поддерживает следующие типы прокси-серверов:

- ✅ **HTTP** - стандартный HTTP прокси
- ✅ **HTTPS** - защищенный HTTPS прокси  
- ✅ **SOCKS4** - SOCKS версии 4 прокси
- ✅ **SOCKS5** - SOCKS версии 5 прокси (с поддержкой аутентификации)

## Поддерживаемые форматы URL

### Базовый формат (без аутентификации)

```
protocol://host:port
```

Примеры:
```
http://proxy.example.com:8080
https://secure-proxy.example.com:8443
socks4://socks4-proxy.example.com:1080
socks5://socks5-proxy.example.com:1080
```

### Формат с аутентификацией

```
protocol://username:password@host:port
```

Примеры:
```
http://user:password@proxy.example.com:8080
https://admin:secret@secure-proxy.example.com:8443
socks5://user123:pass456@socks5-proxy.example.com:1080
```

## Технические детали

### Валидация протоколов

В коде библиотеки используется регулярное выражение для проверки поддерживаемых протоколов:

```php
if (!preg_match('#^(https?|socks4|socks5)://.*#i', $proxy)) {
    throw new ProxyPoolValidationException(
        'Невалидный формат прокси URL'
    );
}
```

Это обеспечивает:
- Поддержку `http://` и `https://` (через `https?`)
- Поддержку `socks4://`
- Поддержку `socks5://`
- Регистронезависимую проверку (флаг `i`)

### Интеграция с HTTP клиентом

ProxyPool использует класс `Http` (на базе Guzzle), который поддерживает все указанные типы прокси через конфигурацию:

```php
$http = new Http([
    'proxy' => $proxyUrl, // Любой поддерживаемый формат
    'verify' => false,
], $logger);
```

## Примеры использования

### 1. Добавление разных типов прокси

```php
use App\Component\ProxyPool;

$proxyPool = new ProxyPool([
    'auto_health_check' => false,
]);

// HTTP прокси
$proxyPool->addProxy('http://proxy.example.com:8080');

// HTTPS прокси
$proxyPool->addProxy('https://secure-proxy.example.com:8443');

// SOCKS4 прокси
$proxyPool->addProxy('socks4://socks4-proxy.example.com:1080');

// SOCKS5 прокси
$proxyPool->addProxy('socks5://socks5-proxy.example.com:1080');
```

### 2. Инициализация пула со всеми типами

```php
$config = [
    'proxies' => [
        'http://http-proxy.example.com:8080',
        'https://https-proxy.example.com:8443',
        'socks4://socks4-proxy.example.com:1080',
        'socks5://socks5-proxy.example.com:1080',
    ],
    'rotation_strategy' => ProxyPool::ROTATION_ROUND_ROBIN,
];

$proxyPool = new ProxyPool($config);
```

### 3. Прокси с аутентификацией

```php
$config = [
    'proxies' => [
        // HTTP с базовой аутентификацией
        'http://user:password@proxy.example.com:8080',
        
        // HTTPS с аутентификацией
        'https://admin:secret@secure-proxy.example.com:8443',
        
        // SOCKS5 с аутентификацией
        'socks5://user123:pass456@socks5-proxy.example.com:1080',
    ],
];

$proxyPool = new ProxyPool($config);
```

### 4. Ротация через разные типы прокси

```php
$proxyPool = new ProxyPool([
    'proxies' => [
        'http://http-proxy.example.com:8080',
        'https://https-proxy.example.com:8443',
        'socks4://socks4-proxy.example.com:1080',
        'socks5://socks5-proxy.example.com:1080',
    ],
    'rotation_strategy' => ProxyPool::ROTATION_ROUND_ROBIN,
]);

// Последовательно используются все типы прокси
$proxy1 = $proxyPool->getNextProxy(); // HTTP
$proxy2 = $proxyPool->getNextProxy(); // HTTPS
$proxy3 = $proxyPool->getNextProxy(); // SOCKS4
$proxy4 = $proxyPool->getNextProxy(); // SOCKS5
$proxy5 = $proxyPool->getNextProxy(); // HTTP (цикл повторяется)
```

## Тестирование

Библиотека включает полное покрытие тестами для всех типов прокси.

### Модульный тест

В файле `tests/Unit/ProxyPoolTest.php` есть специальный тест:

```php
/**
 * Тест: Поддержка различных форматов прокси URL
 */
public function testSupportsVariousProxyFormats(): void
{
    $proxyPool = new ProxyPool([
        'auto_health_check' => false,
    ]);
    
    // HTTP прокси
    $proxyPool->addProxy('http://proxy.example.com:8080');
    
    // HTTPS прокси
    $proxyPool->addProxy('https://proxy.example.com:8443');
    
    // SOCKS4 прокси
    $proxyPool->addProxy('socks4://proxy.example.com:1080');
    
    // SOCKS5 прокси
    $proxyPool->addProxy('socks5://proxy.example.com:1080');
    
    // Прокси с аутентификацией
    $proxyPool->addProxy('http://user:pass@proxy.example.com:8080');
    
    $stats = $proxyPool->getStatistics();
    $this->assertEquals(5, $stats['total_proxies']);
}
```

### Запуск тестов

```bash
php vendor/bin/phpunit tests/Unit/ProxyPoolTest.php --filter testSupportsVariousProxyFormats
```

## Демонстрационный пример

Для демонстрации всех возможностей создан специальный файл примера:

```bash
php examples/proxypool_protocols_example.php
```

Этот скрипт демонстрирует:
- Добавление всех типов прокси
- Работу с аутентификацией
- Ротацию через разные типы
- Валидацию протоколов
- Статистику по типам прокси

## Ограничения и особенности

### Поддерживаемые протоколы

✅ **Поддерживаются:**
- `http://`
- `https://`
- `socks4://`
- `socks5://`

❌ **НЕ поддерживаются:**
- `ftp://` - FTP прокси
- `ssh://` - SSH туннели
- `telnet://` - Telnet прокси
- `socks://` - неспецифицированная версия SOCKS (используйте socks4 или socks5)

### Аутентификация

- ✅ HTTP/HTTPS поддерживают базовую HTTP аутентификацию
- ✅ SOCKS5 поддерживает аутентификацию username/password
- ⚠️ SOCKS4 **НЕ** поддерживает аутентификацию (но можно указать username в URL)

### Health Check

Health check работает для всех типов прокси:

```php
$proxyPool = new ProxyPool([
    'proxies' => [
        'http://http-proxy.example.com:8080',
        'socks5://socks5-proxy.example.com:1080',
    ],
    'health_check_url' => 'https://httpbin.org/ip',
    'auto_health_check' => true,
]);

// Проверка всех прокси (включая SOCKS)
$proxyPool->checkAllProxies();
```

## Конфигурационный файл

Пример `config/proxypool.json` со всеми типами прокси:

```json
{
    "proxies": [
        "http://proxy1.example.com:8080",
        "http://user:pass@proxy2.example.com:3128",
        "https://secure-proxy.example.com:8443",
        "socks4://socks4-proxy.example.com:1080",
        "socks5://socks5-proxy.example.com:1080",
        "socks5://admin:secret@socks5-auth.example.com:1080"
    ],
    "rotation_strategy": "round_robin",
    "health_check_url": "https://httpbin.org/ip",
    "health_check_timeout": 5,
    "max_retries": 3
}
```

Загрузка:

```php
$proxyPool = ProxyPool::fromConfig(__DIR__ . '/config/proxypool.json');
```

## Обработка ошибок

При попытке добавить прокси с неподдерживаемым протоколом выбрасывается исключение:

```php
use App\Component\Exception\ProxyPoolValidationException;

try {
    $proxyPool->addProxy('ftp://ftp-proxy.example.com:21');
} catch (ProxyPoolValidationException $e) {
    echo $e->getMessage();
    // Невалидный формат прокси URL: ftp://ftp-proxy.example.com:21
    // Ожидается формат: protocol://host:port
}
```

## Производительность

Все типы прокси имеют одинаковую производительность в рамках библиотеки, так как:
- Валидация выполняется один раз при добавлении прокси
- Ротация работает независимо от типа протокола
- HTTP клиент (Guzzle) эффективно обрабатывает все типы

## Дополнительная информация

### Документация
- [Основная документация ProxyPool](../PROXYPOOL_README.md)
- [Примеры использования](../examples/proxypool_example.php)
- [Демо протоколов](../examples/proxypool_protocols_example.php)

### Исходный код
- [ProxyPool.class.php](../src/ProxyPool.class.php) - строка 790 (валидация)
- [ProxyPoolTest.php](../tests/Unit/ProxyPoolTest.php) - строка 479 (тест)

### Зависимости
- **Guzzle HTTP** - обеспечивает поддержку всех типов прокси
- **cURL** - используется Guzzle для работы с прокси

## Заключение

Библиотека ProxyPool предоставляет **полную и надежную поддержку** всех основных типов прокси-серверов:

✅ HTTP  
✅ HTTPS  
✅ SOCKS4  
✅ SOCKS5  

С поддержкой аутентификации, health-check, автоматической ротации и retry механизма для всех типов.

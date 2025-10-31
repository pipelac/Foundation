# Быстрый старт: Интеграция htmlWebProxyList + ProxyPool + RSS

## Введение

Данное руководство показывает, как использовать три класса вместе для загрузки RSS лент через прокси-серверы с автоматической ротацией и retry механизмом.

---

## Простой пример использования

### Шаг 1: Получить прокси через htmlWebProxyList

```php
<?php

require_once 'autoload.php';

use App\Component\htmlWebProxyList;
use App\Component\ProxyPool;
use App\Component\Rss;
use App\Component\Logger;

// Инициализация логгера (опционально)
$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'file_name' => 'integration.log',
]);

// Получаем список прокси с work=1 (только работающие из России)
$htmlWeb = new htmlWebProxyList('YOUR_API_KEY', [
    'work' => 1,        // Только работающие из России прокси
    'perpage' => 10,    // Получаем 10 прокси (1 кредит)
    'type' => 'HTTP',   // Тип прокси
    'timeout' => 15,
], $logger);

$proxies = $htmlWeb->getProxies();

echo "Получено прокси: " . count($proxies) . "\n";
echo "Остаток кредитов: " . $htmlWeb->getRemainingLimit() . "\n";
```

### Шаг 2: Загрузить прокси в ProxyPool

```php
// Создаем пул прокси с автоматической ротацией
$proxyPool = new ProxyPool([
    'rotation_strategy' => ProxyPool::ROTATION_ROUND_ROBIN,
    'max_retries' => 3,
    'health_check_timeout' => 10,
    'auto_health_check' => false, // Отключаем для экономии времени
], $logger);

// Загружаем прокси в пул
foreach ($proxies as $proxy) {
    try {
        $proxyPool->addProxy($proxy);
    } catch (Exception $e) {
        echo "Ошибка добавления прокси: " . $e->getMessage() . "\n";
    }
}

echo "Прокси в пуле: " . $proxyPool->getStatistics()['total_proxies'] . "\n";
```

### Шаг 3: Парсить RSS через прокси

```php
// Создаем RSS клиент
$rss = new Rss([
    'timeout' => 20,
    'enable_cache' => false,
], $logger);

// URL RSS ленты для парсинга
$feedUrl = 'https://lenta.ru/rss';

// Пробуем загрузить RSS через прокси с автоматическим retry
$maxAttempts = 3;
$attempt = 0;
$success = false;

while ($attempt < $maxAttempts && !$success) {
    $attempt++;
    $proxy = $proxyPool->getNextProxy();
    
    if ($proxy === null) {
        echo "Нет доступных прокси\n";
        break;
    }
    
    echo "Попытка {$attempt} через {$proxy}... ";
    
    try {
        // Создаем новый RSS клиент с прокси
        $rssWithProxy = new Rss([
            'timeout' => 20,
            'enable_cache' => false,
        ], $logger);
        
        // Используем Reflection для установки прокси
        $httpReflection = new ReflectionClass($rssWithProxy);
        $httpProperty = $httpReflection->getProperty('http');
        $httpProperty->setAccessible(true);
        
        $httpWithProxy = new \App\Component\Http([
            'timeout' => 20,
            'proxy' => $proxy,
            'verify' => false,
        ], $logger);
        
        $httpProperty->setValue($rssWithProxy, $httpWithProxy);
        
        // Загружаем RSS
        $feedData = $rssWithProxy->fetch($feedUrl);
        
        if (isset($feedData['items']) && count($feedData['items']) > 0) {
            echo "УСПЕХ!\n";
            echo "Заголовок: {$feedData['title']}\n";
            echo "Элементов: " . count($feedData['items']) . "\n";
            
            $proxyPool->markProxyAsAlive($proxy);
            $success = true;
        } else {
            echo "Нет элементов\n";
            $proxyPool->markProxyAsDead($proxy);
        }
    } catch (Exception $e) {
        echo "ОШИБКА: " . $e->getMessage() . "\n";
        $proxyPool->markProxyAsDead($proxy);
    }
}

if (!$success) {
    echo "Не удалось загрузить RSS после {$attempt} попыток\n";
}
```

---

## Полный пример с упрощенным кодом

```php
<?php

require_once 'autoload.php';

use App\Component\htmlWebProxyList;
use App\Component\ProxyPool;
use App\Component\Rss;
use App\Component\Logger;

// Настройка
$apiKey = 'YOUR_API_KEY';
$feedUrl = 'https://lenta.ru/rss';

// Логгер
$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'file_name' => 'integration.log',
]);

// 1. Получаем прокси
$htmlWeb = new htmlWebProxyList($apiKey, [
    'work' => 1,
    'perpage' => 10,
    'type' => 'HTTP',
], $logger);

$proxies = $htmlWeb->getProxies();
echo "Получено прокси: " . count($proxies) . "\n";

// 2. Создаем ProxyPool
$proxyPool = new ProxyPool([
    'rotation_strategy' => ProxyPool::ROTATION_ROUND_ROBIN,
    'max_retries' => 3,
], $logger);

foreach ($proxies as $proxy) {
    $proxyPool->addProxy($proxy);
}

// 3. Загружаем RSS через прокси
function loadRssThroughProxy($feedUrl, $proxy, $logger) {
    $rss = new Rss(['timeout' => 20], $logger);
    
    $reflection = new ReflectionClass($rss);
    $httpProperty = $reflection->getProperty('http');
    $httpProperty->setAccessible(true);
    
    $http = new \App\Component\Http([
        'timeout' => 20,
        'proxy' => $proxy,
        'verify' => false,
    ], $logger);
    
    $httpProperty->setValue($rss, $http);
    
    return $rss->fetch($feedUrl);
}

// Пробуем загрузить
for ($i = 0; $i < 3; $i++) {
    $proxy = $proxyPool->getNextProxy();
    
    if ($proxy === null) {
        break;
    }
    
    try {
        echo "Попытка через {$proxy}... ";
        $data = loadRssThroughProxy($feedUrl, $proxy, $logger);
        echo "УСПЕХ! Элементов: " . count($data['items']) . "\n";
        $proxyPool->markProxyAsAlive($proxy);
        break;
    } catch (Exception $e) {
        echo "ОШИБКА\n";
        $proxyPool->markProxyAsDead($proxy);
    }
}

// Статистика
$stats = $proxyPool->getStatistics();
echo "\nСтатистика:\n";
echo "Всего прокси: {$stats['total_proxies']}\n";
echo "Живых: {$stats['alive_proxies']}\n";
echo "Мёртвых: {$stats['dead_proxies']}\n";
```

---

## Использование через конфигурационные файлы

### config/htmlweb_config.json
```json
{
    "api_key": "YOUR_API_KEY",
    "work": 1,
    "perpage": 10,
    "type": "HTTP",
    "timeout": 15
}
```

### config/proxypool_config.json
```json
{
    "rotation_strategy": "round_robin",
    "max_retries": 3,
    "health_check_timeout": 10,
    "health_check_url": "https://www.google.com",
    "auto_health_check": false
}
```

### Код с конфигурацией
```php
<?php

use App\Component\htmlWebProxyList;
use App\Component\ProxyPool;
use App\Component\Logger;

$logger = new Logger(['directory' => __DIR__ . '/logs']);

// Загрузка через конфиг
$htmlWeb = htmlWebProxyList::fromConfig(
    __DIR__ . '/config/htmlweb_config.json',
    $logger
);

$proxyPool = ProxyPool::fromConfig(
    __DIR__ . '/config/proxypool_config.json',
    $logger
);

// Загружаем прокси в пул
$proxies = $htmlWeb->getProxies();
foreach ($proxies as $proxy) {
    $proxyPool->addProxy($proxy);
}

echo "Готово! Прокси в пуле: " . $proxyPool->getStatistics()['total_proxies'];
```

---

## Лучшие практики

### 1. Используйте work=1 для экономии кредитов
```php
$htmlWeb = new htmlWebProxyList($apiKey, [
    'work' => 1, // Только работающие из России
]);
```

### 2. Используйте логгер для отладки
```php
$logger = new Logger(['directory' => __DIR__ . '/logs']);

// Все классы поддерживают логгер
$htmlWeb = new htmlWebProxyList($apiKey, [...], $logger);
$proxyPool = new ProxyPool([...], $logger);
$rss = new Rss([...], $logger);
```

### 3. Обрабатывайте исключения
```php
try {
    $proxies = $htmlWeb->getProxies();
} catch (HtmlWebProxyListException $e) {
    echo "Ошибка получения прокси: " . $e->getMessage();
}

try {
    $feedData = $rss->fetch($url);
} catch (RssException $e) {
    echo "Ошибка парсинга RSS: " . $e->getMessage();
}
```

### 4. Проверяйте статистику ProxyPool
```php
$stats = $proxyPool->getStatistics();

echo "Живых прокси: {$stats['alive_proxies']}\n";
echo "Успешность: {$stats['success_rate']}%\n";

// Детальная информация по каждому прокси
foreach ($stats['proxies'] as $proxy) {
    echo "{$proxy['url']}: ";
    echo "Успешных: {$proxy['success_count']}, ";
    echo "Неудачных: {$proxy['fail_count']}\n";
}
```

### 5. Используйте retry механизм
```php
$maxAttempts = 3;
$attempt = 0;

while ($attempt < $maxAttempts) {
    $proxy = $proxyPool->getNextProxy();
    
    try {
        // Ваш код
        break; // Успех
    } catch (Exception $e) {
        $attempt++;
        $proxyPool->markProxyAsDead($proxy);
    }
}
```

---

## Типичные проблемы и решения

### Проблема 1: Нет доступных прокси после health check

**Причина:** Google.com блокирует большинство прокси-серверов.

**Решение:** Отключите автоматический health check или используйте другой URL:
```php
$proxyPool = new ProxyPool([
    'auto_health_check' => false,
    // ИЛИ
    'health_check_url' => 'http://httpbin.org/ip',
]);
```

### Проблема 2: Прокси не работают для RSS

**Причина:** Некоторые прокси имеют таймауты или блокируются сайтами.

**Решение:** Увеличьте таймаут и используйте retry:
```php
$rss = new Rss([
    'timeout' => 30, // Увеличиваем до 30 секунд
]);

// Используйте retry механизм (см. пример выше)
```

### Проблема 3: Быстро заканчиваются API кредиты

**Причина:** Запрашивается много прокси или неправильный параметр perpage.

**Решение:** Используйте work=1 и оптимальный perpage:
```php
$htmlWeb = new htmlWebProxyList($apiKey, [
    'work' => 1,      // Только работающие
    'perpage' => 10,  // 10 прокси = 1 кредит
]);

// Проверяйте остаток
$remaining = $htmlWeb->getRemainingLimit();
echo "Остаток кредитов: {$remaining}\n";
```

---

## Запуск тестов

Проверьте работу интеграции с помощью готового теста:

```bash
php tests/manual/test_integration_work1_php81.php
```

Этот тест:
- ✅ Получает прокси с work=1
- ✅ Загружает их в ProxyPool
- ✅ Проверяет health check
- ✅ Парсит RSS через прокси
- ✅ Проверяет логирование
- ✅ Использует только 1 API кредит

---

## Полезные ссылки

- **Документация htmlWebProxyList:** `HTMLWEB_PROXYLIST_README.md`
- **Документация ProxyPool:** `PROXYPOOL_README.md`
- **Документация RSS:** `RSS_README.md`
- **Отчёт о тестировании:** `INTEGRATION_TEST_REPORT_WORK1_PHP81.md`
- **Краткая сводка:** `INTEGRATION_TEST_SUMMARY_RU.md`

---

## Заключение

Интеграция трёх классов позволяет:

✅ Автоматически получать прокси через API  
✅ Управлять пулом прокси с ротацией  
✅ Автоматически retry при ошибках  
✅ Парсить RSS ленты через прокси  
✅ Логировать все операции  
✅ Экономить API кредиты с work=1  

**Все работает из коробки!** 🎉

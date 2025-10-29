# htmlWebProxyList - Получение прокси-серверов с htmlweb.ru

Класс для получения списка прокси-серверов с API htmlweb.ru для использования в ProxyPool.

## Основные возможности

- ✅ **Получение прокси с API htmlweb.ru** - автоматическая загрузка актуальных прокси
- 🔍 **Гибкая фильтрация** - по стране, скорости, типу, работоспособности
- 🔗 **Интеграция с ProxyPool** - прямая загрузка прокси в пул
- 📝 **Полное логирование** - поддержка Logger.class.php
- 🎯 **Строгая типизация** - использование strict types для надежности
- ✔️ **Валидация IP и портов** - проверка корректности полученных данных
- 📄 **Поддержка пагинации** - загрузка прокси постранично

## Установка и подключение

```php
require_once __DIR__ . '/autoload.php';

use App\Component\htmlWebProxyList;
use App\Component\ProxyPool;
use App\Component\Logger;
```

## Быстрый старт

### Базовая инициализация

```php
// Без конфигурации (значения по умолчанию)
$htmlWebProxy = new htmlWebProxyList();
$proxies = $htmlWebProxy->getProxies();

// С конфигурацией
$htmlWebProxy = new htmlWebProxyList([
    'country' => 'RU',
    'perpage' => 30,
    'type' => 'http',
    'work' => 'yes',
]);

$proxies = $htmlWebProxy->getProxies();
echo "Получено прокси: " . count($proxies);
```

### С логированием

```php
$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'file_name' => 'htmlweb.log',
]);

$htmlWebProxy = new htmlWebProxyList([
    'perpage' => 50,
    'type' => 'http',
], $logger);

$proxies = $htmlWebProxy->getProxies();
```

## Конфигурация

### Параметры конструктора

| Параметр | Тип | По умолчанию | Описание |
|----------|-----|--------------|----------|
| `country` | `string` | - | Код страны (RU, US, GB и т.д.) |
| `country_not` | `string` | - | Исключить страны (через запятую: RU,CN) |
| `perpage` | `int` | `50` | Количество прокси на странице (макс. 50) |
| `work` | `string` | - | Работоспособность: `yes`, `maybe`, `no` |
| `type` | `string` | `http` | Тип прокси: `http`, `https`, `socks4`, `socks5` |
| `speed_max` | `int` | - | Максимальная скорость в миллисекундах |
| `page` | `int` | `1` | Номер страницы |
| `short` | `string` | - | Краткий формат: `only_ip` |
| `timeout` | `int` | `10` | Таймаут HTTP запроса в секундах |

### API htmlweb.ru

Класс использует публичный API: `https://htmlweb.ru/analiz/proxy_list.php`

Документация API: https://htmlweb.ru/analiz/proxy_list.php#api

### Допустимые значения параметров

**Работоспособность (work):**
- `yes` - только рабочие прокси
- `maybe` - возможно рабочие
- `no` - нерабочие

**Тип прокси (type):**
- `http` - HTTP прокси
- `https` - HTTPS прокси
- `socks4` - SOCKS4 прокси
- `socks5` - SOCKS5 прокси

**Краткий формат (short):**
- `only_ip` - возвращает только IP:PORT без HTML

## Использование

### Получение списка прокси

```php
$htmlWebProxy = new htmlWebProxyList([
    'perpage' => 20,
    'type' => 'http',
]);

$proxies = $htmlWebProxy->getProxies();

// Результат:
// [
//     'http://192.168.1.1:8080',
//     'http://10.0.0.1:3128',
//     ...
// ]
```

### Фильтрация по стране

```php
// Получить только российские прокси
$htmlWebProxy = new htmlWebProxyList([
    'country' => 'RU',
    'perpage' => 30,
    'type' => 'http',
]);

$proxies = $htmlWebProxy->getProxies();
```

### Исключение стран

```php
// Получить прокси, исключая Китай и Россию
$htmlWebProxy = new htmlWebProxyList([
    'country_not' => 'CN,RU',
    'perpage' => 25,
]);

$proxies = $htmlWebProxy->getProxies();
```

### Фильтрация по скорости

```php
// Только быстрые прокси (до 1000ms)
$htmlWebProxy = new htmlWebProxyList([
    'speed_max' => 1000,
    'work' => 'yes',
    'perpage' => 50,
]);

$proxies = $htmlWebProxy->getProxies();
```

### Различные типы прокси

```php
// HTTP прокси
$httpProxy = new htmlWebProxyList(['type' => 'http']);

// HTTPS прокси
$httpsProxy = new htmlWebProxyList(['type' => 'https']);

// SOCKS5 прокси
$socks5Proxy = new htmlWebProxyList(['type' => 'socks5']);
```

### Использование краткого формата

```php
// Краткий формат для более быстрой обработки
$htmlWebProxy = new htmlWebProxyList([
    'short' => 'only_ip',
    'perpage' => 50,
]);

$proxies = $htmlWebProxy->getProxies();
```

### Пагинация

```php
// Загрузка первой страницы
$page1 = new htmlWebProxyList(['page' => 1, 'perpage' => 50]);
$proxiesPage1 = $page1->getProxies();

// Загрузка второй страницы
$page2 = new htmlWebProxyList(['page' => 2, 'perpage' => 50]);
$proxiesPage2 = $page2->getProxies();

// Объединение результатов
$allProxies = array_merge($proxiesPage1, $proxiesPage2);
```

## Интеграция с ProxyPool

### Прямая загрузка в ProxyPool

```php
use App\Component\ProxyPool;
use App\Component\htmlWebProxyList;

// Создаем пул прокси
$proxyPool = new ProxyPool([
    'rotation_strategy' => ProxyPool::ROTATION_ROUND_ROBIN,
]);

// Создаем источник прокси
$htmlWebProxy = new htmlWebProxyList([
    'country' => 'US',
    'work' => 'yes',
    'perpage' => 50,
    'type' => 'http',
]);

// Загружаем прокси в пул
$addedCount = $htmlWebProxy->loadIntoProxyPool($proxyPool);
echo "Добавлено прокси в пул: {$addedCount}";

// Используем прокси
$proxy = $proxyPool->getNextProxy();
echo "Следующий прокси: {$proxy}";
```

### Полный пример с работой через прокси

```php
$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'file_name' => 'proxy_work.log',
]);

// Создаем пул
$proxyPool = new ProxyPool([
    'rotation_strategy' => ProxyPool::ROTATION_RANDOM,
    'max_retries' => 3,
    'auto_health_check' => true,
], $logger);

// Загружаем прокси с htmlweb.ru
$htmlWebProxy = new htmlWebProxyList([
    'country' => 'RU',
    'work' => 'yes',
    'speed_max' => 2000,
    'perpage' => 50,
    'type' => 'http',
], $logger);

$addedCount = $htmlWebProxy->loadIntoProxyPool($proxyPool);
echo "Загружено прокси: {$addedCount}\n";

// Выполняем запросы через прокси
try {
    $response = $proxyPool->get('https://api.example.com/data');
    echo "Статус: " . $response->getStatusCode() . "\n";
    echo "Данные: " . $response->getBody()->getContents() . "\n";
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}
```

## Управление параметрами

### Получение текущих параметров

```php
$htmlWebProxy = new htmlWebProxyList([
    'country' => 'US',
    'perpage' => 30,
]);

$params = $htmlWebProxy->getParams();
print_r($params);

// Результат:
// Array
// (
//     [country] => US
//     [perpage] => 30
// )
```

### Обновление параметров

```php
$htmlWebProxy = new htmlWebProxyList([
    'type' => 'http',
    'perpage' => 10,
]);

// Динамическое обновление
$htmlWebProxy->updateParams([
    'country' => 'RU',
    'speed_max' => 1000,
    'work' => 'yes',
]);

// Получаем прокси с новыми параметрами
$proxies = $htmlWebProxy->getProxies();
```

### Сброс параметров

```php
$htmlWebProxy = new htmlWebProxyList([
    'country' => 'US',
    'speed_max' => 500,
]);

// Сбросить все параметры к значениям по умолчанию
$htmlWebProxy->resetParams();

$proxies = $htmlWebProxy->getProxies();
```

## Обработка ошибок

Класс использует собственные исключения:

```php
use App\Component\Exception\HtmlWebProxyListException;
use App\Component\Exception\HtmlWebProxyListValidationException;

try {
    $htmlWebProxy = new htmlWebProxyList([
        'perpage' => 100, // Ошибка: максимум 50
        'type' => 'invalid',
    ]);
    
    $proxies = $htmlWebProxy->getProxies();
    
} catch (HtmlWebProxyListValidationException $e) {
    // Ошибка валидации параметров
    echo "Ошибка валидации: " . $e->getMessage();
    
} catch (HtmlWebProxyListException $e) {
    // Общая ошибка (сетевая ошибка, ошибка API и т.д.)
    echo "Ошибка получения прокси: " . $e->getMessage();
    
} catch (Exception $e) {
    // Прочие ошибки
    echo "Неожиданная ошибка: " . $e->getMessage();
}
```

## Продвинутые примеры

### Автоматическое обновление пула прокси

```php
function refreshProxyPool(ProxyPool $proxyPool, htmlWebProxyList $htmlWebProxy): int
{
    // Очищаем старые прокси
    $proxyPool->clearProxies();
    
    // Загружаем свежие прокси
    return $htmlWebProxy->loadIntoProxyPool($proxyPool);
}

$proxyPool = new ProxyPool();
$htmlWebProxy = new htmlWebProxyList([
    'work' => 'yes',
    'perpage' => 50,
]);

// Периодическое обновление
while (true) {
    $count = refreshProxyPool($proxyPool, $htmlWebProxy);
    echo "Обновлено прокси: {$count}\n";
    
    // Работа с прокси...
    
    sleep(3600); // Обновление каждый час
}
```

### Загрузка прокси из нескольких стран

```php
$proxyPool = new ProxyPool([
    'rotation_strategy' => ProxyPool::ROTATION_RANDOM,
]);

$countries = ['US', 'GB', 'DE', 'FR'];
$totalLoaded = 0;

foreach ($countries as $country) {
    $htmlWebProxy = new htmlWebProxyList([
        'country' => $country,
        'work' => 'yes',
        'perpage' => 20,
        'type' => 'http',
    ]);
    
    $count = $htmlWebProxy->loadIntoProxyPool($proxyPool);
    $totalLoaded += $count;
    
    echo "Загружено из {$country}: {$count}\n";
}

echo "Всего загружено прокси: {$totalLoaded}\n";
```

### Фильтрация и проверка прокси

```php
$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'file_name' => 'proxy_check.log',
]);

$proxyPool = new ProxyPool([
    'auto_health_check' => true,
    'health_check_timeout' => 5,
], $logger);

$htmlWebProxy = new htmlWebProxyList([
    'work' => 'yes',
    'speed_max' => 1500,
    'perpage' => 50,
    'type' => 'http',
], $logger);

// Загружаем прокси
$loaded = $htmlWebProxy->loadIntoProxyPool($proxyPool);
echo "Загружено прокси: {$loaded}\n";

// Проверяем здоровье всех прокси
$proxyPool->checkAllProxies();

// Получаем статистику
$stats = $proxyPool->getStatistics();
echo "Живых прокси: {$stats['alive_proxies']}\n";
echo "Мёртвых прокси: {$stats['dead_proxies']}\n";

// Удаляем мёртвые прокси
foreach ($stats['proxies'] as $proxyInfo) {
    if (!$proxyInfo['alive']) {
        $proxyPool->removeProxy($proxyInfo['url']);
    }
}

$finalStats = $proxyPool->getStatistics();
echo "Осталось живых прокси: {$finalStats['total_proxies']}\n";
```

### Комбинирование источников прокси

```php
// Загрузка прокси из htmlweb.ru
$htmlWebProxy = new htmlWebProxyList([
    'country' => 'US',
    'work' => 'yes',
    'perpage' => 30,
]);

$proxiesFromHtmlWeb = $htmlWebProxy->getProxies();

// Добавление собственных прокси
$ownProxies = [
    'http://my-proxy1.com:8080',
    'http://my-proxy2.com:8080',
];

// Объединение
$allProxies = array_merge($proxiesFromHtmlWeb, $ownProxies);

// Загрузка в пул
$proxyPool = new ProxyPool([
    'proxies' => $allProxies,
    'rotation_strategy' => ProxyPool::ROTATION_ROUND_ROBIN,
]);
```

## Ограничения API

- Максимальное количество прокси на странице: **50**
- API может иметь ограничения по частоте запросов
- Не все прокси могут быть рабочими в момент получения
- Рекомендуется использовать health-check для проверки

## Рекомендации

1. **Используйте фильтр work='yes'** для получения только рабочих прокси
2. **Ограничивайте скорость** через параметр `speed_max` для быстрых прокси
3. **Включайте логирование** для отладки и мониторинга
4. **Проверяйте прокси** через ProxyPool health-check после загрузки
5. **Кешируйте результаты** если нужно избежать частых запросов к API
6. **Обрабатывайте исключения** для устойчивости приложения

## Примеры использования

Смотрите полные примеры использования в файле:
```
examples/htmlweb_proxylist_example.php
```

Запуск примеров:
```bash
php examples/htmlweb_proxylist_example.php
```

## Связанные классы

- `ProxyPool` - менеджер пула прокси-серверов
- `Http` - HTTP клиент для запросов
- `Logger` - класс логирования

## Лицензия

См. LICENSE файл в корне проекта.

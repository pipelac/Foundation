# Руководство по миграции с старого AccountApi.php на новый Account.php

## Быстрый старт

### 1. Инициализация

**Было:**
```php
require_once 'coreApi.php';
require_once 'AccountApi.php';

$api = new AccountApi();
```

**Стало:**
```php
use App\Component\Config\ConfigLoader;
use App\Component\{Logger, MySQL};
use App\Component\UTM\Account;

$config = ConfigLoader::load('Config/utm.json');
$logger = new Logger($config['logger']);
$db = new MySQL($config['database'], $logger);
$account = new Account($db, $logger);
```

### 2. Обработка результатов

**Было:**
```php
$result = $api->getAccountByIP('192.168.1.100');

if ($result['status'] == 'OK') {
    $accountId = $result['result'];
    echo "Найден счет: {$accountId}";
} elseif ($result['status'] == 'NULL') {
    echo "Счет не найден";
} elseif ($result['status'] == 'ERROR') {
    echo "Ошибка: " . $result['error'];
}
```

**Стало:**
```php
try {
    $accountId = $account->getAccountByIP('192.168.1.100');
    if ($accountId !== null) {
        echo "Найден счет: {$accountId}";
    } else {
        echo "Счет не найден";
    }
} catch (AccountException $e) {
    echo "Ошибка: " . $e->getMessage();
}
```

## Таблица соответствия методов

| Старый метод (AccountApi.php) | Новый метод (Account.php) | Изменения |
|-------------------------------|---------------------------|-----------|
| `getUadParamsByAccount($accountId, $paramid, $limit, $separator)` | `getUadParamsByAccount(int $accountId, ?int $paramid = null, ?int $limit = null, string $separator = ','): ?string` | Типизация, возврат null вместо sendNull |
| `getDealerNameByAccount($accountId, $separator)` | `getDealerNameByAccount(int $accountId, string $separator = '\n'): string` | Типизация, всегда возвращает строку |
| `getAccountByIP($ip, $limit)` | `getAccountByIP(string $ip, ?int $limit = null): ?int` | Возвращает int вместо string |
| `getIpByAccount($accountId, $format, $separator)` | `getIpByAccount(int $accountId, string $format = 'ip', string $separator = '\n'): string\|array\|null` | Union type для разных форматов |
| `getAccountByPhone($phone, $separator)` | `getAccountByPhone(string $phone, string $separator = ','): ?string` | Типизация |
| `getAccountByAddress($address, $entrance, $floor, $flat, $separator)` | `getAccountByAddress(string $address, ?string $entrance = null, ?string $floor = null, ?string $flat = null, string $separator = ','): ?string` | Nullable параметры |
| `getAccountByFio($value, $separator)` | `getAccountByFio(string $value, string $separator = ','): ?string` | Типизация |
| `getAccountBySwitchPort($switch, $port, $separator)` | `getAccountBySwitchPort(string $switch, string $port, string $separator = ','): ?string` | Типизация |
| `getAccountByVlan($vlan, $separator, $limit)` | `getAccountByVlan(int $vlan, string $separator = ',', ?int $limit = null): ?string` | Типизация, vlan как int |
| `getAccountBySnWiFi($value, $separator)` | `getAccountBySnWiFi(string $value, string $separator = ','): ?string` | Типизация |
| `getAccountBySnStb($value, $separator)` | `getAccountBySnStb(string $value, string $separator = ','): ?string` | Типизация |
| `getAccountBySSID($value, $separator)` | `getAccountBySSID(string $value, string $separator = ','): ?string` | Типизация |
| `getAccountId($accountId, $limit)` | `getAccountId(int $accountId, int $limit = 1): int` | Throws вместо sendError |
| `getLoginAndPaswordByAccountId($accountId, $limit)` | `getLoginAndPaswordByAccountId(int $accountId, int $limit = 1): array` | Типизация, throws вместо sendError |
| `getNumberIdByAccount($accountId, $limit)` | `getNumberIdByAccount(int $accountId, int $limit = 1): int` | Типизация, throws вместо sendError |
| `getAccountByUserId($userId, $limit)` | `getAccountByUserId(int $userId, int $limit = 1): int` | Типизация, throws вместо sendError |
| `getLastAccountId($limit)` | `getLastAccountId(int $limit = 1): int` | Типизация, throws вместо sendError |

## Ключевые отличия

### 1. Возвращаемые значения

**Старый API:**
- Все методы возвращают массив `['status' => 'OK/ERROR/NULL', 'result' => ..., 'error' => ...]`
- Нужно всегда проверять статус

**Новый API:**
- Методы возвращают данные напрямую
- `null` для отсутствия результата
- Исключения для ошибок

### 2. Обработка ошибок

**Старый API:**
```php
if ($result['status'] == 'ERROR') {
    logError($result['error']);
}
```

**Новый API:**
```php
try {
    $data = $account->someMethod();
} catch (AccountException $e) {
    $logger->log('ERROR', $e->getMessage());
}
```

### 3. Типы данных

**Старый API:**
- Все параметры без типов
- Возвращаемые значения без типов
- Нужна ручная валидация

**Новый API:**
- Строгая типизация всех параметров
- Строгая типизация возвращаемых значений
- Автоматическая валидация (IP, телефон)

### 4. Валидация

**Старый API:**
```php
// Валидация встроена в методы, но возвращает массив
$result = $api->isValidIp($ip);
if ($result['status'] == 'OK') {
    $validIp = $result['result'];
}
```

**Новый API:**
```php
// Валидация через Utils, бросает исключение
try {
    $validIp = Utils::validateIp($ip);
} catch (UtilsValidationException $e) {
    // Обработка ошибки валидации
}
```

## Примеры миграции

### Пример 1: Поиск по телефону

**Было:**
```php
$result = $api->getAccountByPhone('79091234567');
if ($result['status'] == 'OK') {
    $accounts = explode(',', $result['result']);
    foreach ($accounts as $accountId) {
        processAccount($accountId);
    }
} elseif ($result['status'] == 'NULL') {
    echo "Не найдено";
}
```

**Стало:**
```php
try {
    $accountsStr = $account->getAccountByPhone('79091234567');
    if ($accountsStr !== null) {
        $accounts = explode(',', $accountsStr);
        foreach ($accounts as $accountId) {
            processAccount((int)$accountId);
        }
    } else {
        echo "Не найдено";
    }
} catch (AccountException $e) {
    $logger->log('ERROR', 'Search error: ' . $e->getMessage());
}
```

### Пример 2: Получение IP адресов

**Было:**
```php
// Получить массив
$result = $api->getIpByAccount(12345, 'array');
if ($result['status'] == 'OK') {
    foreach ($result['result'] as $ip => $mac) {
        echo "{$ip} => {$mac}\n";
    }
}
```

**Стало:**
```php
try {
    $ips = $account->getIpByAccount(12345, 'array');
    if ($ips !== null) {
        foreach ($ips as $ip => $mac) {
            echo "{$ip} => {$mac}\n";
        }
    }
} catch (AccountException $e) {
    $logger->log('ERROR', $e->getMessage());
}
```

### Пример 3: Проверка существования

**Было:**
```php
$result = $api->getAccountId(12345);
if ($result['status'] == 'OK') {
    echo "Счет существует";
} else {
    echo "Счет не существует";
}
```

**Стало:**
```php
try {
    $accountId = $account->getAccountId(12345);
    echo "Счет существует: {$accountId}";
} catch (AccountException $e) {
    echo "Счет не существует";
}
```

## Конфигурация

### account.json

Новый API использует конфигурационный файл `src/UTM/config/account.json` вместо `account.ini`.

**Загрузка конфигурации:**
```php
use App\Component\Config\ConfigLoader;

$accountConfig = ConfigLoader::load('src/UTM/config/account.json');

// Доступ к параметрам
$searchLimit = $accountConfig['general']['search_results_limit'];
$dealerGroups = $accountConfig['dealer']['88888']; // [1002, 1020, 1052, 1090]
$defaultTariffs = $accountConfig['phys_tariff']['default'];
```

## Преимущества нового API

✅ **Типизация** - ошибки ловятся на этапе разработки  
✅ **Исключения** - стандартный механизм обработки ошибок  
✅ **Автологирование** - все операции автоматически логируются  
✅ **Dependency Injection** - гибкая настройка компонентов  
✅ **PSR-4** - автозагрузка через Composer  
✅ **Современный PHP** - использование PHP 8.1+ возможностей  
✅ **Документация** - полный PHPDoc для всех методов  

## Поддержка

- 📖 Полная документация: `docs/UTM_MODULE.md`
- 💡 Примеры: `examples/utm_account_search_example.php`
- 🧪 Тесты: `tests/test_utm_utils.php`
- ⚙️ Конфигурация: `src/UTM/config/README.md`

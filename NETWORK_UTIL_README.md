# NetworkUtil - Обёртка для системных сетевых утилит

## Описание

`NetworkUtil` - это PHP класс для удобного выполнения системных сетевых команд через Symfony Process Component. Предоставляет типизированный интерфейс с полной поддержкой логирования, валидации и обработки ошибок.

## Возможности

✅ **15+ сетевых утилит** - ping, dig, nslookup, host, whois, traceroute, mtr, curl, wget, netstat, ss, ip, ifconfig, arp, nmap, tcpdump  
✅ **Полное логирование** - все операции записываются через Logger  
✅ **Защита от инъекций** - валидация всех входных параметров  
✅ **Настраиваемые таймауты** - для каждой команды индивидуально  
✅ **Обработка ошибок** - на всех уровнях с детальными сообщениями  
✅ **Строгая типизация** - PHP 8.1+ strict types  
✅ **Документация на русском** - PHPDoc для всех методов  

## Требования

- PHP 8.1 или выше
- Symfony Process Component ^7.3
- Системные утилиты (устанавливаются через apt):
  - `iputils-ping` - для ping
  - `dnsutils` - для dig, nslookup
  - `bind9-host` - для host
  - `whois` - для whois
  - `traceroute` - для traceroute
  - `mtr-tiny` - для mtr
  - `curl` - для curl
  - `wget` - для wget
  - `net-tools` - для netstat, ifconfig, arp
  - `iproute2` - для ip, ss
  - `nmap` - для nmap
  - `tcpdump` - для tcpdump (требует root)

## Установка

### 1. Установка через Composer

```bash
composer require symfony/process
```

### 2. Установка системных утилит

```bash
sudo apt-get update
sudo apt-get install -y iputils-ping dnsutils whois traceroute mtr-tiny \
    nmap net-tools tcpdump iproute2 bind9-host curl wget
```

## Использование

### Базовая инициализация

```php
use App\Component\NetworkUtil;
use App\Component\Logger;

// Инициализация логгера
$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'file_name' => 'network',
]);

// Инициализация NetworkUtil
$networkUtil = new NetworkUtil([
    'default_timeout' => 30,      // Таймаут по умолчанию (секунды)
    'throw_on_error' => false,    // Выбрасывать исключение при ошибке
], $logger);
```

### Примеры использования

#### 1. Проверка доступности хоста (PING)

```php
// Ping с 4 пакетами
$result = $networkUtil->ping('google.com', 4);

if ($result['success']) {
    echo "Хост доступен\n";
    echo "Время выполнения: {$result['duration']}с\n";
    echo "Вывод: {$result['output']}\n";
} else {
    echo "Ошибка: {$result['error']}\n";
}
```

#### 2. DNS запросы

```php
// Dig A запись
$result = $networkUtil->dig('github.com', 'A');

// Dig MX запись
$result = $networkUtil->dig('gmail.com', 'MX');

// Dig с указанием DNS сервера
$result = $networkUtil->dig('example.com', 'A', '8.8.8.8');

// Nslookup
$result = $networkUtil->nslookup('yahoo.com');

// Host
$result = $networkUtil->host('wikipedia.org');
```

#### 3. WHOIS информация

```php
// WHOIS домена
$result = $networkUtil->whois('google.com');

// WHOIS IP-адреса
$result = $networkUtil->whois('8.8.8.8');
```

#### 4. Трассировка маршрута

```php
// Traceroute с 15 хопами и таймаутом 20с
$result = $networkUtil->traceroute('google.com', 15, 20);

// MTR с 10 циклами
$result = $networkUtil->mtr('8.8.8.8', 10, true, 30);
```

#### 5. HTTP проверки

```php
// Curl - получение заголовков
$result = $networkUtil->curl('https://github.com', ['-I', '-L']);

// Curl с таймаутом
$result = $networkUtil->curl('https://example.com', ['--max-time', '5']);

// Wget - проверка доступности
$result = $networkUtil->wget('https://google.com', ['--spider', '-q']);
```

#### 6. Информация о сети

```php
// Netstat - открытые порты
$result = $networkUtil->netstat(['-tuln']);

// SS - статистика сокетов
$result = $networkUtil->ss(['-s']);

// IP - адреса интерфейсов
$result = $networkUtil->ip(['addr', 'show']);

// IP - таблица маршрутизации
$result = $networkUtil->ip(['route', 'show']);

// Ifconfig
$result = $networkUtil->ifconfig();
$result = $networkUtil->ifconfig('eth0');

// ARP таблица
$result = $networkUtil->arp(['-a']);
```

#### 7. Сканирование портов

```php
// Nmap - сканирование конкретных портов
$result = $networkUtil->nmap('localhost', '22,80,443');

// Nmap - сканирование диапазона портов с опциями
$result = $networkUtil->nmap('192.168.1.1', '1-1000', ['-sV']);
```

#### 8. Захват трафика

```php
// Tcpdump (требует root)
$result = $networkUtil->tcpdump('eth0', 10, 'port 80');
```

#### 9. Произвольная команда

```php
$result = $networkUtil->executeCustomCommand(
    'custom_ping',
    ['ping', '-c', '3', 'example.com'],
    15
);
```

### Формат результата

Все методы возвращают массив с унифицированной структурой:

```php
[
    'success' => true,                    // Успешность выполнения
    'output' => "...",                    // Вывод команды
    'error' => null,                      // Сообщение об ошибке (null если успех)
    'exit_code' => 0,                     // Код возврата команды
    'duration' => 0.123,                  // Время выполнения в секундах
    'command' => 'ping',                  // Имя команды
]
```

### Обработка ошибок

#### Режим без исключений (throw_on_error = false)

```php
$networkUtil = new NetworkUtil(['throw_on_error' => false], $logger);

$result = $networkUtil->ping('unreachable-host.local', 1, 5);

if (!$result['success']) {
    echo "Ошибка: {$result['error']}\n";
    echo "Код: {$result['exit_code']}\n";
}
```

#### Режим с исключениями (throw_on_error = true)

```php
$networkUtil = new NetworkUtil(['throw_on_error' => true], $logger);

try {
    $result = $networkUtil->ping('unreachable-host.local', 1, 5);
} catch (NetworkUtilException $e) {
    echo "Перехвачено исключение: {$e->getMessage()}\n";
}
```

### Валидация

Класс автоматически валидирует все входные параметры:

```php
// ❌ Пустой хост
try {
    $networkUtil->ping('', 1);
} catch (NetworkUtilValidationException $e) {
    echo $e->getMessage(); // "Хост не может быть пустым"
}

// ❌ Опасные символы
try {
    $networkUtil->ping('test;rm -rf', 1);
} catch (NetworkUtilValidationException $e) {
    echo $e->getMessage(); // "Хост содержит запрещённые символы"
}

// ❌ Некорректный URL
try {
    $networkUtil->curl('not-a-url', ['-I']);
} catch (NetworkUtilValidationException $e) {
    echo $e->getMessage(); // "Некорректный формат URL"
}

// ❌ Неподдерживаемый тип DNS записи
try {
    $networkUtil->dig('example.com', 'INVALID');
} catch (NetworkUtilValidationException $e) {
    echo $e->getMessage(); // "Неподдерживаемый тип записи: INVALID"
}
```

## API методов

### Проверка доступности

#### `ping(string $host, int $count = 4, ?int $timeout = null): array`

Выполняет ping проверку доступности хоста.

**Параметры:**
- `$host` - хост или IP-адрес
- `$count` - количество пакетов (1-100)
- `$timeout` - таймаут в секундах (null = default)

### DNS запросы

#### `dig(string $domain, string $recordType = 'A', ?string $nameserver = null, ?int $timeout = null): array`

Выполняет DNS запрос через dig.

**Параметры:**
- `$domain` - доменное имя
- `$recordType` - тип записи (A, AAAA, MX, NS, TXT, SOA, CNAME, PTR, SRV, CAA, ANY)
- `$nameserver` - DNS сервер (null = системный)
- `$timeout` - таймаут в секундах

#### `nslookup(string $host, ?string $nameserver = null, ?int $timeout = null): array`

Выполняет DNS lookup через nslookup.

#### `host(string $hostname, ?string $nameserver = null, ?int $timeout = null): array`

Выполняет быстрый DNS lookup через host.

### WHOIS

#### `whois(string $target, ?int $timeout = null): array`

Получает WHOIS информацию о домене или IP-адресе.

### Трассировка

#### `traceroute(string $host, int $maxHops = 30, ?int $timeout = null): array`

Выполняет трассировку маршрута до хоста.

**Параметры:**
- `$host` - хост или IP-адрес
- `$maxHops` - максимальное количество хопов (1-255)
- `$timeout` - таймаут в секундах

#### `mtr(string $host, int $count = 10, bool $report = true, ?int $timeout = null): array`

Выполняет MTR мониторинг (комбинация ping и traceroute).

**Параметры:**
- `$host` - хост или IP-адрес
- `$count` - количество циклов (1-1000)
- `$report` - режим отчёта
- `$timeout` - таймаут в секундах

### HTTP проверки

#### `curl(string $url, array $options = [], ?int $timeout = null): array`

Выполняет HTTP/HTTPS запрос через curl.

**Параметры:**
- `$url` - URL для проверки
- `$options` - дополнительные опции curl
- `$timeout` - таймаут в секундах

#### `wget(string $url, array $options = ['--spider', '-q'], ?int $timeout = null): array`

Проверяет доступность через wget.

### Сетевая информация

#### `netstat(array $options = ['-tuln'], ?int $timeout = null): array`

Статистика сетевых соединений.

#### `ss(array $options = ['-tuln'], ?int $timeout = null): array`

Информация о сокетах (современная замена netstat).

#### `ip(array $options = ['addr', 'show'], ?int $timeout = null): array`

Информация о сетевых интерфейсах и маршрутизации.

#### `ifconfig(?string $interface = null, ?int $timeout = null): array`

Информация о сетевых интерфейсах (legacy).

#### `arp(array $options = ['-a'], ?int $timeout = null): array`

Просмотр ARP таблицы.

### Сканирование

#### `nmap(string $host, ?string $ports = null, array $options = [], ?int $timeout = null): array`

Сканирование портов через nmap.

**Параметры:**
- `$host` - хост или IP-адрес
- `$ports` - диапазон портов ('80,443' или '1-1000')
- `$options` - дополнительные опции nmap
- `$timeout` - таймаут в секундах

#### `tcpdump(string $interface, int $count = 10, ?string $filter = null, ?int $timeout = null): array`

Захват сетевого трафика (требует root).

**Параметры:**
- `$interface` - сетевой интерфейс
- `$count` - количество пакетов (1-10000)
- `$filter` - BPF фильтр
- `$timeout` - таймаут в секундах

### Произвольные команды

#### `executeCustomCommand(string $commandName, array $command, ?int $timeout = null): array`

Выполняет произвольную сетевую команду.

## Логирование

Все операции автоматически логируются:

```
2025-10-31 09:23:21 [INFO] NetworkUtil инициализирован {"default_timeout":30,"throw_on_error":false}
2025-10-31 09:23:21 [INFO] Выполнение команды: ping {"command":"ping -c 4 google.com","timeout":30}
2025-10-31 09:23:22 [INFO] Команда ping успешно выполнена {"duration":0.009,"exit_code":0}
2025-10-31 09:24:10 [ERROR] Команда curl завершилась с ошибкой {"duration":5.007,"exit_code":28,"error":"..."}
2025-10-31 09:24:11 [CRITICAL] Критическая ошибка выполнения команды: tcpdump {"error":"...","duration":0.003}
```

## Безопасность

Класс включает защиту от инъекций:

✅ Валидация всех входных параметров  
✅ Проверка на опасные символы (`;`, `&`, `|`, `` ` ``, `$`)  
✅ Использование массивов команд вместо строк  
✅ Типизация всех параметров  

## Производительность

Результаты нагрузочного тестирования (39 тестов):

- **Успешных тестов:** 82.1%
- **Общее время:** 38.20с
- **Среднее время на тест:** 0.979с

Быстрые операции (< 0.1с): `ss`, `ip`, `ifconfig`, `arp`, `dig`, `nslookup`, `nmap`  
Средние операции (0.1-1с): `host`, `traceroute`, `netstat`, `wget`  
Медленные операции (> 1с): `curl`, `ping`, `mtr`

## Примеры

Полные примеры использования доступны в файле:
```
examples/network_util_example.php
```

Запуск примеров:
```bash
php examples/network_util_example.php
```

## Тестирование

Полноценный нагрузочный тест:
```bash
php test_network_util_load.php
```

Результаты тестирования в файле:
```
NETWORK_UTIL_TEST_REPORT.md
```

## Известные ограничения

- `tcpdump` требует прав root или специальных capabilities
- Некоторые утилиты могут быть недоступны по умолчанию (требуется установка)
- Время выполнения зависит от сетевых условий
- `ping` в некоторых окружениях может требовать специальных прав

## Лицензия

Класс является частью проекта `app/basic-utilities`.

## Автор

Разработано с использованием лучших практик PHP 8.1+ и Symfony Process Component.

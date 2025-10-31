# SNMP Класс - Полное руководство

## Оглавление

1. [Введение](#введение)
2. [Установка](#установка)
3. [Быстрый старт](#быстрый-старт)
4. [Подробная документация](#подробная-документация)
5. [Примеры использования](#примеры-использования)
6. [Обработка ошибок](#обработка-ошибок)
7. [Логирование](#логирование)
8. [Тестирование](#тестирование)
9. [FAQ](#faq)

## Введение

Класс `Snmp` представляет собой профессиональную обертку для работы с SNMP (Simple Network Management Protocol) в PHP 8.1+.

### Основные возможности

- ✅ Поддержка SNMPv1, SNMPv2c и SNMPv3
- ✅ Все основные SNMP операции (GET, GETNEXT, WALK, SET)
- ✅ Множественные операции (GET/SET нескольких OID одновременно)
- ✅ Строгая типизация и валидация параметров
- ✅ Структурированное логирование
- ✅ Специализированные исключения
- ✅ Вспомогательные методы (системная информация, сетевые интерфейсы)
- ✅ Автоматическое управление соединениями

### Требования

- PHP 8.1 или выше
- PHP расширение ext-snmp
- SNMP агент на целевом устройстве
- Класс `App\Component\Logger` (опционально)

## Установка

### 1. Установка PHP расширения SNMP

#### Ubuntu/Debian:
```bash
sudo apt-get update
sudo apt-get install php8.3-snmp snmpd snmp
```

#### CentOS/RHEL:
```bash
sudo yum install php-snmp net-snmp net-snmp-utils
```

### 2. Включение расширения

Проверьте, что расширение включено:
```bash
php -m | grep snmp
```

### 3. Копирование файлов класса

Скопируйте следующие файлы в ваш проект:
- `src/Snmp.class.php`
- `src/Exception/SnmpException.php`
- `src/Exception/SnmpConnectionException.php`
- `src/Exception/SnmpValidationException.php`

### 4. Автозагрузка

Обновите автозагрузку Composer:
```bash
composer dump-autoload
```

## Быстрый старт

### Простейший пример

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use App\Component\Snmp;

// Создание соединения
$snmp = new Snmp([
    'host' => '192.168.1.1',
    'community' => 'public',
    'version' => Snmp::VERSION_2C,
]);

// Получение системного описания
$sysDescr = $snmp->get('.1.3.6.1.2.1.1.1.0');
echo "System Description: {$sysDescr}\n";

// Закрытие соединения
$snmp->close();
```

### С логированием

```php
<?php
use App\Component\Snmp;
use App\Component\Logger;

// Создание логгера
$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'file_name' => 'snmp.log',
    'max_files' => 5,
    'max_file_size' => 10, // МБ
]);

// Создание SNMP соединения с логгером
$snmp = new Snmp([
    'host' => '192.168.1.1',
    'community' => 'public',
    'version' => Snmp::VERSION_2C,
    'timeout' => 1000000, // 1 секунда в микросекундах
    'retries' => 3,
], $logger);

// Все операции будут автоматически логироваться
$systemInfo = $snmp->getSystemInfo();
print_r($systemInfo);

$snmp->close();
```

## Подробная документация

### Конфигурация соединения

#### Параметры для всех версий:

| Параметр | Тип | По умолчанию | Описание |
|----------|-----|--------------|----------|
| `host` | string | обязательный | IP адрес или hostname SNMP агента |
| `community` | string | 'public' | Community string (v1/v2c) или username (v3) |
| `version` | int | `Snmp::VERSION_2C` | Версия SNMP протокола |
| `timeout` | int | 1000000 | Тайм-аут в микросекундах |
| `retries` | int | 3 | Количество попыток повтора |
| `port` | int | 161 | Порт SNMP агента |

#### Дополнительные параметры для SNMPv3:

| Параметр | Тип | Описание |
|----------|-----|----------|
| `v3_security_level` | string | 'noAuthNoPriv', 'authNoPriv', 'authPriv' |
| `v3_auth_protocol` | string | Протокол аутентификации ('MD5', 'SHA') |
| `v3_auth_passphrase` | string | Парольная фраза аутентификации |
| `v3_privacy_protocol` | string | Протокол конфиденциальности ('DES', 'AES') |
| `v3_privacy_passphrase` | string | Парольная фраза конфиденциальности |

### Методы класса

#### Основные операции

##### get(string $oid): string|false

Получает значение одного OID.

```php
$value = $snmp->get('.1.3.6.1.2.1.1.1.0');
if ($value !== false) {
    echo "Value: {$value}\n";
}
```

##### getMultiple(array $oids): array

Получает значения нескольких OID одновременно.

```php
$oids = [
    '.1.3.6.1.2.1.1.1.0', // sysDescr
    '.1.3.6.1.2.1.1.3.0', // sysUpTime
    '.1.3.6.1.2.1.1.5.0', // sysName
];

$results = $snmp->getMultiple($oids);
foreach ($results as $oid => $value) {
    echo "{$oid} = {$value}\n";
}
```

##### getNext(string $oid): string|false

Получает следующий OID в дереве MIB.

```php
$next = $snmp->getNext('.1.3.6.1.2.1.1.1.0');
echo "Next OID value: {$next}\n";
```

##### walk(string $oid, bool $suffixAsKey = false, int $maxRepetitions = 20, int $nonRepeaters = 0): array|false

Обходит дерево MIB начиная с указанного OID.

```php
// Получить все объекты в системной группе
$results = $snmp->walk('.1.3.6.1.2.1.1');
foreach ($results as $oid => $value) {
    echo "{$oid} = {$value}\n";
}

// С суффиксом в качестве ключа
$results = $snmp->walk('.1.3.6.1.2.1.2.2.1.2', true);
// Результат: ['1' => 'lo', '2' => 'eth0', ...]
```

##### set(string $oid, string $type, string|int $value): bool

Устанавливает значение OID.

```php
// Установка строкового значения
$success = $snmp->set(
    '.1.3.6.1.2.1.1.6.0',
    Snmp::TYPE_STRING,
    'Server Room 1'
);

// Установка целочисленного значения
$success = $snmp->set(
    '.1.3.6.1.2.1.1.7.0',
    Snmp::TYPE_INTEGER,
    72
);
```

**Типы данных:**
- `Snmp::TYPE_INTEGER` - Целое число
- `Snmp::TYPE_UNSIGNED` - Беззнаковое целое
- `Snmp::TYPE_STRING` - Строка
- `Snmp::TYPE_OBJID` - Object ID
- `Snmp::TYPE_IPADDRESS` - IP адрес
- `Snmp::TYPE_COUNTER32` - 32-битный счетчик
- `Snmp::TYPE_GAUGE32` - 32-битный gauge
- `Snmp::TYPE_TIMETICKS` - Time ticks
- `Snmp::TYPE_OPAQUE` - Opaque
- `Snmp::TYPE_COUNTER64` - 64-битный счетчик
- `Snmp::TYPE_BITS` - Bits

##### setMultiple(array $oids, array $types, array $values): bool

Устанавливает значения нескольких OID одновременно.

```php
$oids = [
    '.1.3.6.1.2.1.1.4.0',
    '.1.3.6.1.2.1.1.6.0',
];
$types = [
    Snmp::TYPE_STRING,
    Snmp::TYPE_STRING,
];
$values = [
    'admin@example.com',
    'Server Room 1',
];

$success = $snmp->setMultiple($oids, $types, $values);
```

#### Вспомогательные методы

##### getSystemInfo(): array

Получает системную информацию (sysDescr, sysObjectID, sysUpTime и т.д.).

```php
$info = $snmp->getSystemInfo();
echo "System: {$info['sysDescr']}\n";
echo "Name: {$info['sysName']}\n";
echo "Location: {$info['sysLocation']}\n";
echo "Contact: {$info['sysContact']}\n";
echo "Up Time: {$info['sysUpTime']}\n";
```

##### getNetworkInterfaces(): array

Получает список сетевых интерфейсов.

```php
$interfaces = $snmp->getNetworkInterfaces();
foreach ($interfaces as $interface) {
    echo "Interface #{$interface['index']}: {$interface['description']}\n";
}
```

##### getConnectionInfo(): array

Получает информацию о текущем соединении.

```php
$info = $snmp->getConnectionInfo();
echo "Host: {$info['host']}\n";
echo "Version: {$info['version']}\n";
echo "Timeout: {$info['timeout']}\n";
echo "Retries: {$info['retries']}\n";
echo "Connected: " . ($info['connected'] ? 'Yes' : 'No') . "\n";
```

##### getLastError(): string

Получает описание последней ошибки.

```php
$value = $snmp->get('.1.3.6.1.2.1.999.999');
if ($value === false) {
    echo "Error: " . $snmp->getLastError() . "\n";
}
```

##### setTimeout(int $timeout): void

Устанавливает тайм-аут соединения в микросекундах.

```php
$snmp->setTimeout(2000000); // 2 секунды
```

##### setRetries(int $retries): void

Устанавливает количество повторов запроса.

```php
$snmp->setRetries(5);
```

##### close(): void

Закрывает SNMP соединение.

```php
$snmp->close();
// Соединение закроется автоматически при уничтожении объекта
```

### Константы версий SNMP

```php
Snmp::VERSION_1    // SNMPv1
Snmp::VERSION_2C   // SNMPv2c (рекомендуется)
Snmp::VERSION_2c   // Альтернативное название для VERSION_2C
Snmp::VERSION_3    // SNMPv3
```

## Примеры использования

### Пример 1: Мониторинг системы

```php
<?php
use App\Component\Snmp;

$snmp = new Snmp([
    'host' => '192.168.1.1',
    'community' => 'public',
    'version' => Snmp::VERSION_2C,
]);

// Получение системной информации
$info = $snmp->getSystemInfo();
echo "=== System Information ===\n";
echo "Description: {$info['sysDescr']}\n";
echo "Name: {$info['sysName']}\n";
echo "Location: {$info['sysLocation']}\n";
echo "Contact: {$info['sysContact']}\n";
echo "Uptime: {$info['sysUpTime']}\n\n";

// Получение сетевых интерфейсов
echo "=== Network Interfaces ===\n";
$interfaces = $snmp->getNetworkInterfaces();
foreach ($interfaces as $interface) {
    echo "Interface #{$interface['index']}: {$interface['description']}\n";
}

$snmp->close();
```

### Пример 2: Обход дерева MIB

```php
<?php
use App\Component\Snmp;

$snmp = new Snmp([
    'host' => '192.168.1.1',
    'community' => 'public',
    'version' => Snmp::VERSION_2C,
]);

// Обход всей системной группы
echo "=== System Group Walk ===\n";
$results = $snmp->walk('.1.3.6.1.2.1.1');

foreach ($results as $oid => $value) {
    echo "{$oid} = {$value}\n";
}

$snmp->close();
```

### Пример 3: Установка значений (SNMPv2c с write community)

```php
<?php
use App\Component\Snmp;
use App\Component\Exception\SnmpException;

$snmp = new Snmp([
    'host' => '192.168.1.1',
    'community' => 'private', // write community
    'version' => Snmp::VERSION_2C,
]);

try {
    // Установка местоположения
    $success = $snmp->set(
        '.1.3.6.1.2.1.1.6.0',
        Snmp::TYPE_STRING,
        'Server Room 1, Rack 5'
    );
    
    if ($success) {
        echo "Location updated successfully\n";
    } else {
        echo "Failed to update location: " . $snmp->getLastError() . "\n";
    }
    
    // Установка контакта
    $success = $snmp->set(
        '.1.3.6.1.2.1.1.4.0',
        Snmp::TYPE_STRING,
        'admin@example.com'
    );
    
    if ($success) {
        echo "Contact updated successfully\n";
    }
    
} catch (SnmpException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$snmp->close();
```

### Пример 4: SNMPv3 с аутентификацией

```php
<?php
use App\Component\Snmp;

$snmp = new Snmp([
    'host' => '192.168.1.1',
    'community' => 'snmpuser', // username
    'version' => Snmp::VERSION_3,
    'v3_security_level' => 'authPriv',
    'v3_auth_protocol' => 'SHA',
    'v3_auth_passphrase' => 'authpassword',
    'v3_privacy_protocol' => 'AES',
    'v3_privacy_passphrase' => 'privpassword',
    'timeout' => 2000000, // 2 секунды
    'retries' => 5,
]);

$systemInfo = $snmp->getSystemInfo();
print_r($systemInfo);

$snmp->close();
```

### Пример 5: Массовый опрос нескольких устройств

```php
<?php
use App\Component\Snmp;
use App\Component\Logger;

$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'file_name' => 'snmp_poll.log',
]);

$devices = [
    ['host' => '192.168.1.1', 'name' => 'Router 1'],
    ['host' => '192.168.1.2', 'name' => 'Switch 1'],
    ['host' => '192.168.1.3', 'name' => 'Server 1'],
];

foreach ($devices as $device) {
    echo "Polling {$device['name']} ({$device['host']})...\n";
    
    try {
        $snmp = new Snmp([
            'host' => $device['host'],
            'community' => 'public',
            'version' => Snmp::VERSION_2C,
            'timeout' => 500000, // 0.5 секунды
            'retries' => 2,
        ], $logger);
        
        $info = $snmp->getSystemInfo();
        echo "  System: {$info['sysDescr']}\n";
        echo "  Uptime: {$info['sysUpTime']}\n\n";
        
        $snmp->close();
        
    } catch (\Exception $e) {
        echo "  Error: {$e->getMessage()}\n\n";
    }
}
```

## Обработка ошибок

Класс использует специализированные исключения для различных типов ошибок:

### Иерархия исключений

```
Exception
└── SnmpException (базовое исключение)
    ├── SnmpConnectionException (ошибки подключения)
    └── SnmpValidationException (ошибки валидации)
```

### Примеры обработки

#### Базовая обработка

```php
<?php
use App\Component\Snmp;
use App\Component\Exception\SnmpException;

try {
    $snmp = new Snmp([
        'host' => '192.168.1.1',
        'community' => 'public',
    ]);
    
    $value = $snmp->get('.1.3.6.1.2.1.1.1.0');
    echo "Value: {$value}\n";
    
} catch (SnmpException $e) {
    echo "SNMP Error: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
}
```

#### Детальная обработка

```php
<?php
use App\Component\Snmp;
use App\Component\Exception\SnmpConnectionException;
use App\Component\Exception\SnmpValidationException;
use App\Component\Exception\SnmpException;

try {
    $snmp = new Snmp([
        'host' => '192.168.1.1',
        'community' => 'public',
    ]);
    
    $value = $snmp->get('.1.3.6.1.2.1.1.1.0');
    
    if ($value === false) {
        echo "Warning: No response or empty value\n";
        echo "Last error: " . $snmp->getLastError() . "\n";
    } else {
        echo "Value: {$value}\n";
    }
    
} catch (SnmpConnectionException $e) {
    echo "Connection Error: " . $e->getMessage() . "\n";
    echo "Check network and SNMP agent status\n";
    
} catch (SnmpValidationException $e) {
    echo "Validation Error: " . $e->getMessage() . "\n";
    echo "Check configuration parameters\n";
    
} catch (SnmpException $e) {
    echo "SNMP Error: " . $e->getMessage() . "\n";
}
```

## Логирование

Класс поддерживает структурированное логирование через `Logger`.

### Уровни логирования

- **DEBUG:** Все SNMP запросы и ответы
- **INFO:** Успешные соединения, системная информация
- **WARNING:** Предупреждения (недоступные хосты, пустые результаты)
- **ERROR:** Критические ошибки операций

### Пример конфигурации логгера

```php
<?php
use App\Component\Logger;

$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'file_name' => 'snmp.log',
    'max_files' => 5,
    'max_file_size' => 10, // МБ
    'log_buffer_size' => 8, // КБ
    'enabled' => true,
    'pattern' => '{timestamp} {level} {message} {context}',
]);
```

### Примеры записей лога

```
2025-10-31T10:00:00+00:00 INFO SNMP соединение успешно установлено {"host":"192.168.1.1","version":"SNMPv2c"}
2025-10-31T10:00:01+00:00 DEBUG SNMP GET запрос {"oid":".1.3.6.1.2.1.1.1.0"}
2025-10-31T10:00:01+00:00 DEBUG SNMP GET ответ {"oid":".1.3.6.1.2.1.1.1.0","value":"Linux server 5.15.0"}
2025-10-31T10:00:02+00:00 WARNING Список интерфейсов пуст {}
2025-10-31T10:00:03+00:00 ERROR Ошибка SNMP GET {"oid":".1.3.6.1.2.1.999","error":"No such object"}
```

## Тестирование

### Запуск нагрузочного теста

```bash
cd /home/engine/project
php snmp_load_test.php
```

### Результаты тестирования

- **Всего тестов:** 34
- **Пройдено:** 34 (100%)
- **Провалено:** 0
- **Время выполнения:** ~0.64 сек

Подробный отчет: [SNMP_LOAD_TEST_REPORT.md](SNMP_LOAD_TEST_REPORT.md)

### Собственные тесты

```php
<?php
use App\Component\Snmp;

// Ваш код тестирования
$snmp = new Snmp([
    'host' => 'localhost',
    'community' => 'public',
]);

assert($snmp->get('.1.3.6.1.2.1.1.1.0') !== false, 'sysDescr should not be false');
assert(is_array($snmp->getSystemInfo()), 'getSystemInfo should return array');

echo "All tests passed!\n";
```

## FAQ

### Q: Какую версию SNMP использовать?

**A:** Рекомендуется использовать SNMPv2c для большинства задач. Она поддерживает bulk-операции и проще в настройке чем v3. SNMPv3 используйте когда требуется аутентификация и шифрование.

### Q: Почему GET возвращает false?

**A:** Это может быть из-за:
- Некорректного OID
- Недоступного SNMP агента
- Неправильного community string
- Тайм-аута соединения
- Ограничений прав доступа в SNMP агенте

Используйте `getLastError()` для диагностики.

### Q: Как увеличить производительность?

**A:** 
1. Используйте `getMultiple()` вместо множественных `get()`
2. Используйте SNMPv2c вместо v1 (поддержка bulk)
3. Уменьшите timeout если сеть быстрая
4. Используйте `walk()` для получения больших объемов данных

### Q: SET операция не работает?

**A:** Проверьте:
1. Используете ли вы write community (обычно 'private')
2. Разрешены ли SET операции в конфигурации SNMP агента
3. Корректный ли тип данных для OID
4. Имеет ли OID право на запись (некоторые OID read-only)

### Q: Как настроить SNMP агент для тестирования?

**A:** Пример конфигурации для Net-SNMP:

```bash
# /etc/snmp/snmpd.conf
agentaddress 127.0.0.1:161
rocommunity public 127.0.0.1
rwcommunity private 127.0.0.1
sysLocation Test Location
sysContact admin@example.com
```

Перезапустите сервис:
```bash
sudo service snmpd restart
```

### Q: Как узнать OID для нужного параметра?

**A:** Используйте команду snmptranslate или документацию производителя:

```bash
# Узнать OID по имени
snmptranslate -On SNMPv2-MIB::sysDescr.0

# Узнать имя по OID
snmptranslate -m ALL .1.3.6.1.2.1.1.1.0
```

Стандартные OID:
- `.1.3.6.1.2.1.1.1.0` - sysDescr (описание системы)
- `.1.3.6.1.2.1.1.3.0` - sysUpTime (время работы)
- `.1.3.6.1.2.1.1.5.0` - sysName (имя системы)
- `.1.3.6.1.2.1.1.6.0` - sysLocation (местоположение)
- `.1.3.6.1.2.1.2.2.1.2` - ifDescr (описание интерфейсов)

### Q: Класс потокобезопасен?

**A:** Каждый экземпляр `Snmp` управляет своим собственным соединением. Для многопоточности создавайте отдельные экземпляры в каждом потоке/процессе.

### Q: Есть ли лимит на количество OID в getMultiple()?

**A:** Технически нет, но рекомендуется не более 50-100 OID за один запрос для оптимальной производительности.

### Q: Как обрабатывать большие MIB деревья?

**A:** Используйте метод `walk()` с параметром `$maxRepetitions`:

```php
// Для больших объемов данных увеличьте maxRepetitions
$results = $snmp->walk('.1.3.6.1.2.1', false, 50);
```

## Дополнительные ресурсы

- [RFC 1157 - SNMPv1](https://tools.ietf.org/html/rfc1157)
- [RFC 1905 - SNMPv2c](https://tools.ietf.org/html/rfc1905)
- [RFC 3410 - SNMPv3](https://tools.ietf.org/html/rfc3410)
- [Net-SNMP Documentation](http://www.net-snmp.org/docs/)
- [PHP SNMP Documentation](https://www.php.net/manual/en/book.snmp.php)

---

**Версия документа:** 1.0  
**Дата:** 31 октября 2025  
**Автор:** AI Assistant

# SNMP OID Loader - Документация

## Описание

SNMP OID Loader - это гибкий механизм для управления SNMP OID конфигурациями различных типов сетевого оборудования. Вместо использования жестко закодированных OID строк в коде, вы можете использовать именованные идентификаторы, которые автоматически резолвятся в соответствующие OID в зависимости от типа устройства.

## Основные возможности

- **Централизованное управление OID** - все OID хранятся в одном JSON файле
- **Поддержка множества типов устройств** - D-Link, Extreme, Cisco и любые другие
- **Наследование OID** - общие (common) OID + специфичные для устройств
- **Метаданные OID** - описание, тип операции, тип значения, значение по умолчанию
- **Интеграция с классом Snmp** - работа через удобные методы `getByName()`, `setByName()`, `walkByName()`
- **Кеширование** - автоматическое кеширование объединенных OID для производительности
- **Строгая типизация** - PHP 8.1+ с полной поддержкой типов
- **Валидация** - автоматическая проверка структуры конфигурации
- **Логирование** - опциональное логирование через Logger

## Структура конфигурации

### Формат JSON файла

```json
{
    "common": {
        "sysName": {
            "oid": ".1.3.6.1.2.1.1.5.0",
            "description": "Имя системы",
            "type": "get"
        },
        "ifInOctets": {
            "oid": ".1.3.6.1.2.1.2.2.1.10.",
            "description": "Счетчик входящих данных (RX) 32 bit",
            "type": "get"
        }
    },
    "D-Link DES-3526": {
        "reboot": {
            "oid": ".1.3.6.1.4.1.171.12.1.2.3.0",
            "description": "Перезагрузка устройства",
            "type": "set",
            "value_type": "i",
            "value": "3"
        },
        "CPUutilizationIn5sec": {
            "oid": ".1.3.6.1.4.1.171.12.1.1.6.1.0",
            "description": "Утилизация CPU за 5 секунд",
            "type": "get"
        }
    }
}
```

### Поля OID записи

| Поле | Тип | Обязательно | Описание |
|------|-----|-------------|----------|
| `oid` | string | Да | OID строка (например, `.1.3.6.1.2.1.1.5.0`) |
| `description` | string | Нет | Человекочитаемое описание OID |
| `type` | string | Нет | Тип операции: `get`, `set`, `walk`, `getnext` |
| `value_type` | string | Нет | Тип значения для SET: `i`, `s`, `u`, `a`, и т.д. |
| `value` | string/int | Нет | Значение по умолчанию для SET операций |

## Использование

### 1. Базовое использование SnmpOid

```php
use App\Component\SnmpOid;
use App\Component\Logger;

// Создание логгера (опционально)
$logger = new Logger(['log_file' => 'logs/snmp.log']);

// Загрузка OID конфигурации
$oidLoader = new SnmpOid(
    __DIR__ . '/config/snmp-oids.json',
    $logger
);

// Получение OID по имени (common)
$sysNameOid = $oidLoader->getOid('sysName');
// Результат: ".1.3.6.1.2.1.1.5.0"

// Получение OID для конкретного типа устройства
$cpuOid = $oidLoader->getOid('CPUutilizationIn5sec', 'D-Link DES-3526');
// Результат: ".1.3.6.1.4.1.171.12.1.1.6.1.0"

// Получение OID с суффиксом (например, номер порта)
$portOid = $oidLoader->getOid('ifInOctets', null, '1');
// Результат: ".1.3.6.1.2.1.2.2.1.10.1"
```

### 2. Получение метаданных OID

```php
// Получение полных данных OID
$oidData = $oidLoader->getOidData('reboot', 'D-Link DES-3526');
/*
Результат:
[
    'oid' => '.1.3.6.1.4.1.171.12.1.2.3.0',
    'description' => 'Перезагрузка устройства',
    'type' => 'set',
    'value_type' => 'i',
    'value' => '3'
]
*/

// Получение отдельных полей
$description = $oidLoader->getDescription('reboot', 'D-Link DES-3526');
$operationType = $oidLoader->getOperationType('reboot', 'D-Link DES-3526');
$valueType = $oidLoader->getValueType('reboot', 'D-Link DES-3526');
$defaultValue = $oidLoader->getDefaultValue('reboot', 'D-Link DES-3526');
```

### 3. Проверка и получение списков

```php
// Проверка существования OID
if ($oidLoader->hasOid('sysName')) {
    echo "OID sysName существует\n";
}

// Получение списка всех OID для типа устройства
$oidNames = $oidLoader->getOidNames('D-Link DES-3526');
// Результат: ['sysName', 'sysDescr', 'reboot', 'CPUutilizationIn5sec', ...]

// Получение списка типов устройств
$deviceTypes = $oidLoader->getDeviceTypes();
// Результат: ['D-Link DES-3526', 'D-Link DES-3028', 'Extreme X670-48x', ...]

// Получение статистики
$stats = $oidLoader->getStats();
/*
Результат:
[
    'config_path' => '/path/to/snmp-oids.json',
    'device_types_count' => 8,
    'total_oids' => 245,
    'device_types' => [
        'common' => 52,
        'D-Link DES-3526' => 28,
        ...
    ]
]
*/
```

### 4. Интеграция с классом Snmp

```php
use App\Component\Snmp;
use App\Component\SnmpOid;

// Загрузка OID конфигурации
$oidLoader = new SnmpOid(__DIR__ . '/config/snmp-oids.json');

// Создание SNMP соединения с OID loader
$snmpConfig = [
    'host' => '192.168.1.1',
    'community' => 'public',
    'version' => Snmp::VERSION_2C,
    'device_type' => 'D-Link DES-3526', // Тип устройства
];

$snmp = new Snmp($snmpConfig, $logger, $oidLoader);

// Теперь можно использовать именованные OID вместо прямых строк
```

### 5. GET операции по имени OID

```php
// Получение одного значения
$sysName = $snmp->getByName('sysName');
echo "System Name: {$sysName}\n";

// Получение значения с суффиксом (номер порта)
$portInOctets = $snmp->getByName('ifInOctets', '1');
echo "Port 1 InOctets: {$portInOctets}\n";

// Получение нескольких значений
$systemInfo = $snmp->getMultipleByName([
    'sysName',
    'sysDescr',
    'sysUpTime',
    'sysLocation',
]);

foreach ($systemInfo as $oidName => $value) {
    echo "{$oidName}: {$value}\n";
}

// Получение статистики порта
$portStats = $snmp->getMultipleByName([
    'ifInOctets',
    'ifOutOctets',
    'ifInErrors',
    'ifOutErrors',
], '5'); // Порт 5

echo "Port 5 Statistics:\n";
echo "  IN: {$portStats['ifInOctets']} octets\n";
echo "  OUT: {$portStats['ifOutOctets']} octets\n";
echo "  IN Errors: {$portStats['ifInErrors']}\n";
echo "  OUT Errors: {$portStats['ifOutErrors']}\n";
```

### 6. WALK операции по имени OID

```php
// Обход дерева MIB по имени OID
$vlans = $snmp->walkByName('vlan_names');

foreach ($vlans as $oid => $vlanName) {
    echo "VLAN {$oid}: {$vlanName}\n";
}

// Обход с использованием суффикса
$interfaceDescriptions = $snmp->walkByName('sysInterfaceDescr', '', true);

foreach ($interfaceDescriptions as $index => $description) {
    echo "Interface {$index}: {$description}\n";
}
```

### 7. SET операции по имени OID

```php
// SET операция с автоматическим определением типа значения
// Тип берется из конфигурации OID
$snmp->setByName('save_config', 3);

// SET операция с явным указанием типа
$snmp->setByName('set_portLable', 'Uplink to Core', '1', Snmp::TYPE_STRING);

// SET с суффиксом (номер порта)
$snmp->setByName('startDiagnostics', 1, '5'); // Запуск диагностики порта 5

// Изменение типа устройства на лету
$snmp->setDeviceType('D-Link DES-3200-28');
$snmp->setByName('reboot', 2); // Теперь используется OID для DES-3200-28
```

## Принцип наследования OID

OID конфигурация поддерживает наследование через секцию `common`:

1. **Common OID** - общие OID, доступные для всех устройств
2. **Device-specific OID** - специфичные OID для конкретного типа устройства

При запросе OID для конкретного типа устройства:
- Сначала проверяются специфичные OID устройства
- Если OID не найден, используется OID из common секции
- Специфичные OID переопределяют common OID

**Пример:**

```json
{
    "common": {
        "sysName": {
            "oid": ".1.3.6.1.2.1.1.5.0"
        },
        "port_duplex": {
            "oid": ".1.3.6.1.2.1.10.7.2.1.19."
        }
    },
    "D-Link DES-3526": {
        "port_duplex": {
            "oid": "1.3.6.1.4.1.171.11.64.1.2.4.1.1.5."
        }
    }
}
```

Для D-Link DES-3526:
- `sysName` будет использовать common OID
- `port_duplex` будет использовать специфичный OID D-Link

## Добавление нового типа устройства

1. Откройте файл `config/snmp-oids.json`
2. Добавьте новую секцию с именем устройства:

```json
{
    "common": { ... },
    "D-Link DES-3526": { ... },
    "Новое устройство XYZ": {
        "oidName1": {
            "oid": ".1.3.6.1.x.x.x.x",
            "description": "Описание OID",
            "type": "get"
        },
        "oidName2": {
            "oid": ".1.3.6.1.y.y.y.y",
            "description": "Другой OID",
            "type": "set",
            "value_type": "i",
            "value": "1"
        }
    }
}
```

3. Используйте новый тип устройства:

```php
$snmpConfig = [
    'host' => '192.168.1.100',
    'community' => 'public',
    'device_type' => 'Новое устройство XYZ',
];

$snmp = new Snmp($snmpConfig, $logger, $oidLoader);
$value = $snmp->getByName('oidName1');
```

## Лучшие практики

### 1. Организация OID

- Используйте понятные имена для OID (например, `CPUutilizationIn5sec` вместо `cpu5`)
- Группируйте связанные OID (например, все OID для портов начинайте с `port_`)
- Добавляйте подробные описания в поле `description`
- Указывайте тип операции в поле `type`

### 2. Наследование

- Помещайте универсальные OID в секцию `common`
- Переопределяйте в специфичных секциях только те OID, которые отличаются
- Не дублируйте OID, которые одинаковы для всех устройств

### 3. Производительность

- OID конфигурация кешируется автоматически
- Избегайте частых вызовов `reload()`
- Используйте `getMultipleByName()` для получения нескольких значений за один запрос

### 4. Безопасность

- Храните конфигурационные файлы вне веб-директории
- Ограничьте права доступа к файлам конфигурации (chmod 600)
- Не храните чувствительные данные в JSON файлах (используйте отдельные конфиги)

## Миграция с INI на JSON

Если у вас есть существующие INI файлы с OID конфигурациями:

### Старый формат INI:
```ini
[common]
sysName = .1.3.6.1.2.1.1.5.0
sysDescr = .1.3.6.1.2.1.1.1.0

[D-Link DES-3526]
reboot = .1.3.6.1.4.1.171.12.1.2.3.0 i 3
CPUutilizationIn5sec = .1.3.6.1.4.1.171.12.1.1.6.1.0
```

### Новый формат JSON:
```json
{
    "common": {
        "sysName": {
            "oid": ".1.3.6.1.2.1.1.5.0",
            "type": "get"
        },
        "sysDescr": {
            "oid": ".1.3.6.1.2.1.1.1.0",
            "type": "get"
        }
    },
    "D-Link DES-3526": {
        "reboot": {
            "oid": ".1.3.6.1.4.1.171.12.1.2.3.0",
            "type": "set",
            "value_type": "i",
            "value": "3"
        },
        "CPUutilizationIn5sec": {
            "oid": ".1.3.6.1.4.1.171.12.1.1.6.1.0",
            "type": "get"
        }
    }
}
```

## Обработка ошибок

```php
use App\Component\Exception\SnmpException;
use App\Component\Exception\SnmpValidationException;

try {
    $oidLoader = new SnmpOid('/path/to/snmp-oids.json', $logger);
    
    // Проверка существования OID перед использованием
    if (!$oidLoader->hasOid('customOid', 'My Device')) {
        echo "OID 'customOid' не найден для устройства 'My Device'\n";
    }
    
    $value = $snmp->getByName('sysName');
    
} catch (SnmpValidationException $e) {
    // Ошибка валидации конфигурации или параметров
    echo "Ошибка валидации: {$e->getMessage()}\n";
    
} catch (SnmpException $e) {
    // Общая ошибка SNMP
    echo "Ошибка SNMP: {$e->getMessage()}\n";
    
} catch (\Exception $e) {
    // Любая другая ошибка
    echo "Неожиданная ошибка: {$e->getMessage()}\n";
}
```

## Примеры

Полный рабочий пример доступен в файле:
```
examples/snmp_oid_loader_example.php
```

Запуск примера:
```bash
php examples/snmp_oid_loader_example.php
```

## API Reference

### Класс SnmpOid

#### Конструктор
```php
public function __construct(string $configPath, ?Logger $logger = null)
```

#### Основные методы

| Метод | Описание |
|-------|----------|
| `getOid(string $oidName, ?string $deviceType = null, string $suffix = ''): string` | Получает OID строку по имени |
| `getOidData(string $oidName, ?string $deviceType = null): array` | Получает полные данные OID |
| `hasOid(string $oidName, ?string $deviceType = null): bool` | Проверяет существование OID |
| `getOidNames(?string $deviceType = null): array` | Получает список имен OID |
| `getDeviceTypes(): array` | Получает список типов устройств |
| `getDescription(string $oidName, ?string $deviceType = null): string` | Получает описание OID |
| `getOperationType(string $oidName, ?string $deviceType = null): string` | Получает тип операции |
| `getValueType(string $oidName, ?string $deviceType = null): ?string` | Получает тип значения |
| `getDefaultValue(string $oidName, ?string $deviceType = null): string\|int\|null` | Получает значение по умолчанию |
| `getStats(): array` | Получает статистику конфигурации |
| `reload(): void` | Перезагружает конфигурацию |

### Расширенные методы класса Snmp

| Метод | Описание |
|-------|----------|
| `getByName(string $oidName, string $suffix = ''): string\|false` | GET по имени OID |
| `getMultipleByName(array $oidNames, string $suffix = ''): array` | Множественный GET по именам |
| `walkByName(string $oidName, string $suffix = '', ...): array\|false` | WALK по имени OID |
| `setByName(string $oidName, string\|int $value, string $suffix = '', ?string $type = null): bool` | SET по имени OID |
| `setDeviceType(?string $deviceType): void` | Устанавливает тип устройства |
| `getDeviceType(): ?string` | Получает текущий тип устройства |
| `getOidLoader(): ?SnmpOid` | Получает объект SnmpOid |

## Заключение

SNMP OID Loader предоставляет гибкий и удобный способ управления OID конфигурациями для различных типов сетевого оборудования. Использование именованных OID делает код более читаемым и поддерживаемым, а централизованное хранение конфигурации упрощает добавление новых устройств и изменение существующих OID.

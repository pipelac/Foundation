# SNMP OID Loader - Краткое руководство

## Что это?

SNMP OID Loader - это механизм для удобной работы с SNMP OID через именованные идентификаторы вместо прямых OID строк. Поддерживает множество типов оборудования с автоматическим наследованием общих и специфичных OID.

## Быстрый старт

### 1. Создайте или используйте существующий конфиг

Файл: `config/snmp-oids.json`

```json
{
    "common": {
        "sysName": {
            "oid": ".1.3.6.1.2.1.1.5.0",
            "description": "Имя системы",
            "type": "get"
        }
    },
    "D-Link DES-3526": {
        "CPUutilizationIn5sec": {
            "oid": ".1.3.6.1.4.1.171.12.1.1.6.1.0",
            "description": "Утилизация CPU за 5 секунд",
            "type": "get"
        }
    }
}
```

### 2. Используйте в коде

```php
use App\Component\Snmp;
use App\Component\SnmpOid;
use App\Component\Logger;

// Загрузка OID конфигурации
$oidLoader = new SnmpOid(__DIR__ . '/config/snmp-oids.json');

// Создание SNMP соединения
$snmp = new Snmp([
    'host' => '192.168.1.1',
    'community' => 'public',
    'version' => Snmp::VERSION_2C,
    'device_type' => 'D-Link DES-3526',
], null, $oidLoader);

// Используйте именованные OID вместо прямых строк
$sysName = $snmp->getByName('sysName');
$cpuUsage = $snmp->getByName('CPUutilizationIn5sec');

echo "System: {$sysName}, CPU: {$cpuUsage}%\n";
```

## Основные возможности

### Получение значений по имени OID

```php
// Одно значение
$value = $snmp->getByName('sysName');

// С суффиксом (например, номер порта)
$portTraffic = $snmp->getByName('ifInOctets', '1');

// Несколько значений
$stats = $snmp->getMultipleByName([
    'ifInOctets',
    'ifOutOctets',
    'ifInErrors',
], '5'); // Для порта 5
```

### WALK операции

```php
// Обход VLAN
$vlans = $snmp->walkByName('vlan_names');

foreach ($vlans as $oid => $vlanName) {
    echo "VLAN: {$vlanName}\n";
}
```

### SET операции

```php
// Тип значения автоматически берется из конфигурации
$snmp->setByName('save_config', 3);

// Или указать явно
$snmp->setByName('set_portLable', 'Uplink', '1', Snmp::TYPE_STRING);
```

## Преимущества

✅ **Читаемость** - код с именами вместо OID строк легче понять  
✅ **Гибкость** - легко поддерживать разные типы устройств  
✅ **Наследование** - общие OID + специфичные для устройств  
✅ **Метаданные** - описания, типы операций, значения по умолчанию  
✅ **Централизация** - все OID в одном месте  
✅ **Типизация** - полная поддержка PHP 8.1+ типов  

## Структура конфигурации

```json
{
    "common": {
        "oidName": {
            "oid": ".1.3.6.1.x.x.x",       // Обязательно
            "description": "Описание",      // Опционально
            "type": "get",                  // get, set, walk, getnext
            "value_type": "i",              // Для SET: i, s, u, и т.д.
            "value": "1"                    // Значение по умолчанию
        }
    },
    "Тип устройства": {
        // Специфичные OID переопределяют common
    }
}
```

## Работа с SnmpOid напрямую

```php
$oidLoader = new SnmpOid('/path/to/snmp-oids.json');

// Получить OID строку
$oid = $oidLoader->getOid('sysName');
$oid = $oidLoader->getOid('CPUutilizationIn5sec', 'D-Link DES-3526');
$oid = $oidLoader->getOid('ifInOctets', null, '1'); // С суффиксом

// Получить метаданные
$data = $oidLoader->getOidData('reboot', 'D-Link DES-3526');
$description = $oidLoader->getDescription('sysName');
$type = $oidLoader->getOperationType('reboot', 'D-Link DES-3526');

// Проверить существование
if ($oidLoader->hasOid('customOid', 'My Device')) {
    // OID существует
}

// Получить списки
$oidNames = $oidLoader->getOidNames('D-Link DES-3526');
$deviceTypes = $oidLoader->getDeviceTypes();
$stats = $oidLoader->getStats();
```

## Добавление нового устройства

1. Откройте `config/snmp-oids.json`
2. Добавьте новую секцию:

```json
{
    "common": { ... },
    "Новое устройство": {
        "oid1": {
            "oid": ".1.3.6.1.x.x.x",
            "description": "Описание",
            "type": "get"
        }
    }
}
```

3. Используйте:

```php
$snmp = new Snmp([
    'host' => '192.168.1.100',
    'device_type' => 'Новое устройство',
], null, $oidLoader);

$value = $snmp->getByName('oid1');
```

## Пример из коробки

Запустите готовый пример:

```bash
php examples/snmp_oid_loader_example.php
```

## Полная документация

Смотрите: `docs/SNMP_OID_LOADER.md`

## Поддерживаемые устройства (из коробки)

- Common (стандартные MIB-II OID для всех устройств)
- D-Link DES-3526
- D-Link DGS-3627G
- D-Link DES-3028
- D-Link DES-3200-28
- D-Link DES-3200-26-C1
- D-Link DES-3200-28-C1
- Extreme X670-48x

## Миграция с INI

### Было (INI):
```ini
[common]
sysName = .1.3.6.1.2.1.1.5.0

[D-Link DES-3526]
reboot = .1.3.6.1.4.1.171.12.1.2.3.0 i 3
```

### Стало (JSON):
```json
{
    "common": {
        "sysName": {
            "oid": ".1.3.6.1.2.1.1.5.0",
            "type": "get"
        }
    },
    "D-Link DES-3526": {
        "reboot": {
            "oid": ".1.3.6.1.4.1.171.12.1.2.3.0",
            "type": "set",
            "value_type": "i",
            "value": "3"
        }
    }
}
```

## Классы

- **SnmpOid** - загрузчик и менеджер OID конфигураций
- **Snmp** - расширен методами для работы с именованными OID

## Требования

- PHP 8.1+
- JSON расширение
- SNMP расширение (для реальной работы с SNMP)

## Лицензия

Используется в рамках проекта.

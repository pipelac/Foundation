# Руководство по конфигурационному файлу SNMP

## Обзор

Класс `Snmp` поддерживает загрузку конфигурации из JSON файла через `ConfigLoader`, что соответствует архитектуре проекта и позволяет централизованно управлять параметрами подключения к SNMP устройствам.

## Расположение файла

**Путь:** `/config/snmp.json`

## Структура конфигурации

### Базовая структура

```json
{
    "devices": {
        "device_name": {
            "host": "192.168.1.1",
            "community": "public",
            "version": 1,
            "timeout": 1000000,
            "retries": 3,
            "port": 161
        }
    },
    "default": "device_name"
}
```

### Параметры устройства

| Параметр | Тип | Обязательно | По умолчанию | Описание |
|----------|-----|-------------|--------------|----------|
| `host` | string | ✅ Да | - | IP адрес или hostname SNMP агента |
| `community` | string | ❌ Нет | "public" | Community string (v1/v2c) или username (v3) |
| `version` | int | ❌ Нет | 1 | Версия SNMP: 0 = v1, 1 = v2c, 3 = v3 |
| `timeout` | int | ❌ Нет | 1000000 | Тайм-аут в микросекундах (1000000 = 1 сек) |
| `retries` | int | ❌ Нет | 3 | Количество попыток повтора запроса |
| `port` | int | ❌ Нет | 161 | Порт SNMP агента |

### Параметры SNMPv3

Дополнительные параметры для использования с `version: 3`:

| Параметр | Тип | Описание |
|----------|-----|----------|
| `v3_security_level` | string | Уровень безопасности: "noAuthNoPriv", "authNoPriv", "authPriv" |
| `v3_auth_protocol` | string | Протокол аутентификации: "MD5", "SHA" |
| `v3_auth_passphrase` | string | Парольная фраза аутентификации (минимум 8 символов) |
| `v3_privacy_protocol` | string | Протокол конфиденциальности: "DES", "AES" |
| `v3_privacy_passphrase` | string | Парольная фраза конфиденциальности (минимум 8 символов) |

## Примеры конфигураций

### 1. SNMPv2c (рекомендуется для большинства случаев)

```json
{
    "devices": {
        "localhost": {
            "host": "127.0.0.1",
            "community": "public",
            "version": 1,
            "timeout": 1000000,
            "retries": 3,
            "port": 161
        },
        "router": {
            "host": "192.168.1.1",
            "community": "public",
            "version": 1,
            "timeout": 2000000,
            "retries": 5,
            "port": 161
        }
    },
    "default": "localhost"
}
```

### 2. SNMPv3 с максимальной безопасностью

```json
{
    "devices": {
        "secure_server": {
            "host": "192.168.1.100",
            "community": "snmpuser",
            "version": 3,
            "timeout": 2000000,
            "retries": 3,
            "port": 161,
            "v3_security_level": "authPriv",
            "v3_auth_protocol": "SHA",
            "v3_auth_passphrase": "strong_auth_password_here",
            "v3_privacy_protocol": "AES",
            "v3_privacy_passphrase": "strong_priv_password_here"
        }
    },
    "default": "secure_server"
}
```

### 3. Множественные устройства

```json
{
    "devices": {
        "core_router": {
            "host": "192.168.1.1",
            "community": "monitor",
            "version": 1,
            "timeout": 1500000,
            "retries": 3
        },
        "distribution_switch": {
            "host": "192.168.1.10",
            "community": "monitor",
            "version": 1,
            "timeout": 1500000,
            "retries": 3
        },
        "access_switch_1": {
            "host": "192.168.1.20",
            "community": "monitor",
            "version": 1,
            "timeout": 1000000,
            "retries": 2
        },
        "server_farm": {
            "host": "10.0.0.100",
            "community": "admin",
            "version": 3,
            "timeout": 3000000,
            "retries": 5,
            "v3_security_level": "authPriv",
            "v3_auth_protocol": "SHA",
            "v3_auth_passphrase": "auth_password",
            "v3_privacy_protocol": "AES",
            "v3_privacy_passphrase": "priv_password"
        }
    },
    "default": "core_router"
}
```

## Использование в коде

### Базовое использование

```php
<?php
use App\Component\Snmp;
use App\Component\Logger;
use App\Config\ConfigLoader;

// Загрузка конфигурации
$config = ConfigLoader::load(__DIR__ . '/config/snmp.json');

// Создание логгера (опционально)
$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'file_name' => 'snmp.log',
]);

// Подключение к устройству по умолчанию
$defaultDevice = $config['default'];
$snmp = new Snmp($config['devices'][$defaultDevice], $logger);

// Получение системной информации
$systemInfo = $snmp->getSystemInfo();
print_r($systemInfo);

// Закрытие соединения
$snmp->close();
```

### Работа с несколькими устройствами

```php
<?php
use App\Component\Snmp;
use App\Config\ConfigLoader;

$config = ConfigLoader::load(__DIR__ . '/config/snmp.json');

// Перебор всех устройств
foreach ($config['devices'] as $deviceName => $deviceConfig) {
    echo "Опрос устройства: {$deviceName}\n";
    
    try {
        $snmp = new Snmp($deviceConfig, $logger);
        
        // Получение uptime
        $uptime = $snmp->get('.1.3.6.1.2.1.1.3.0');
        echo "  Uptime: {$uptime}\n";
        
        // Получение имени
        $name = $snmp->get('.1.3.6.1.2.1.1.5.0');
        echo "  Name: {$name}\n";
        
        $snmp->close();
        
    } catch (\Exception $e) {
        echo "  Ошибка: {$e->getMessage()}\n";
    }
    
    echo "\n";
}
```

### Динамический выбор устройства

```php
<?php
use App\Component\Snmp;
use App\Config\ConfigLoader;

function connectToDevice(string $deviceName, ?Logger $logger = null): Snmp
{
    $config = ConfigLoader::load(__DIR__ . '/config/snmp.json');
    
    if (!isset($config['devices'][$deviceName])) {
        throw new \InvalidArgumentException("Устройство '{$deviceName}' не найдено в конфигурации");
    }
    
    return new Snmp($config['devices'][$deviceName], $logger);
}

// Использование
$router = connectToDevice('router');
$switch = connectToDevice('switch');
```

## Версии SNMP

### Константы класса

```php
Snmp::VERSION_1    // 0 - SNMPv1
Snmp::VERSION_2C   // 1 - SNMPv2c (рекомендуется)
Snmp::VERSION_2c   // 1 - альтернативное название
Snmp::VERSION_3    // 3 - SNMPv3
```

### Рекомендации по выбору версии

| Версия | Когда использовать | Преимущества | Недостатки |
|--------|-------------------|--------------|------------|
| **SNMPv1** | Только для старого оборудования | Простота | Нет безопасности, медленнее |
| **SNMPv2c** | ✅ **Рекомендуется для большинства случаев** | Bulk операции, быстрее | Нет аутентификации |
| **SNMPv3** | Критичные системы, требования безопасности | Аутентификация, шифрование | Сложнее настройка |

## Уровни безопасности SNMPv3

### noAuthNoPriv
- **Безопасность:** Минимальная
- **Требуется:** Только `community` (username)
- **Использование:** Тестирование

```json
{
    "version": 3,
    "community": "username",
    "v3_security_level": "noAuthNoPriv"
}
```

### authNoPriv
- **Безопасность:** Средняя (только аутентификация)
- **Требуется:** `community`, `v3_auth_protocol`, `v3_auth_passphrase`
- **Использование:** Внутренние сети

```json
{
    "version": 3,
    "community": "username",
    "v3_security_level": "authNoPriv",
    "v3_auth_protocol": "SHA",
    "v3_auth_passphrase": "auth_password"
}
```

### authPriv
- **Безопасность:** ✅ **Максимальная** (аутентификация + шифрование)
- **Требуется:** Все параметры SNMPv3
- **Использование:** ✅ **Рекомендуется для продакшена**

```json
{
    "version": 3,
    "community": "username",
    "v3_security_level": "authPriv",
    "v3_auth_protocol": "SHA",
    "v3_auth_passphrase": "strong_auth_password",
    "v3_privacy_protocol": "AES",
    "v3_privacy_passphrase": "strong_priv_password"
}
```

## Общие OID для мониторинга

Конфигурационный файл включает справочник наиболее используемых OID:

| OID | Описание | Использование |
|-----|----------|---------------|
| `.1.3.6.1.2.1.1.1.0` | sysDescr | Описание системы |
| `.1.3.6.1.2.1.1.3.0` | sysUpTime | Время работы системы |
| `.1.3.6.1.2.1.1.5.0` | sysName | Имя системы |
| `.1.3.6.1.2.1.1.6.0` | sysLocation | Местоположение |
| `.1.3.6.1.2.1.2.2.1.2` | ifDescr | Описание интерфейсов |
| `.1.3.6.1.2.1.2.2.1.8` | ifOperStatus | Статус интерфейсов |
| `.1.3.6.1.2.1.2.2.1.10` | ifInOctets | Входящий трафик |
| `.1.3.6.1.2.1.2.2.1.16` | ifOutOctets | Исходящий трафик |

## Рекомендации по безопасности

### Для продакшен окружения

1. **✅ Используйте SNMPv3 с authPriv**
   ```json
   {
       "version": 3,
       "v3_security_level": "authPriv",
       "v3_auth_protocol": "SHA",
       "v3_privacy_protocol": "AES"
   }
   ```

2. **✅ Используйте сильные парольные фразы**
   - Минимум 16 символов
   - Смесь букв, цифр, символов
   - Уникальные для каждого устройства

3. **✅ Измените community string**
   ```json
   {
       "community": "my_unique_community_2024"
   }
   ```
   Не используйте стандартные "public" / "private"

4. **✅ Используйте read-only community для мониторинга**
   - Для GET операций: read-only community
   - Для SET операций: отдельный write community

5. **✅ Защитите конфигурационный файл**
   ```bash
   chmod 600 config/snmp.json
   chown www-data:www-data config/snmp.json
   ```

6. **✅ Ограничьте доступ на уровне SNMP агента**
   В `/etc/snmp/snmpd.conf`:
   ```
   rocommunity my_community 192.168.1.0/24
   ```

### Для разработки/тестирования

Допустимо использование SNMPv2c с community "public" для localhost:

```json
{
    "devices": {
        "localhost": {
            "host": "127.0.0.1",
            "community": "public",
            "version": 1
        }
    }
}
```

## Настройка тайм-аутов

### Быстрая локальная сеть
```json
{
    "timeout": 500000,  // 0.5 секунды
    "retries": 2
}
```

### Обычная сеть
```json
{
    "timeout": 1000000,  // 1 секунда (по умолчанию)
    "retries": 3
}
```

### Медленная/ненадежная сеть
```json
{
    "timeout": 3000000,  // 3 секунды
    "retries": 5
}
```

### Большие файлы/bulk операции
```json
{
    "timeout": 5000000,  // 5 секунд
    "retries": 3
}
```

## Проверка конфигурации

Скрипт для проверки корректности конфигурации:

```php
<?php
use App\Config\ConfigLoader;

try {
    $config = ConfigLoader::load(__DIR__ . '/config/snmp.json');
    
    // Проверка структуры
    if (!isset($config['devices']) || !is_array($config['devices'])) {
        throw new Exception("Отсутствует секция 'devices'");
    }
    
    if (empty($config['devices'])) {
        throw new Exception("Не определено ни одного устройства");
    }
    
    // Проверка каждого устройства
    foreach ($config['devices'] as $name => $device) {
        echo "Проверка устройства: {$name}\n";
        
        if (!isset($device['host']) || empty($device['host'])) {
            throw new Exception("  Ошибка: не указан host");
        }
        
        if (isset($device['version']) && $device['version'] === 3) {
            if (!isset($device['v3_security_level'])) {
                echo "  Предупреждение: не указан v3_security_level\n";
            }
        }
        
        echo "  ✓ OK\n";
    }
    
    echo "\n✓ Конфигурация корректна\n";
    
} catch (Exception $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}
```

## Миграция с прямых параметров

### Было (прямые параметры)

```php
$snmp = new Snmp([
    'host' => '192.168.1.1',
    'community' => 'public',
    'version' => Snmp::VERSION_2C,
], $logger);
```

### Стало (через конфигурацию)

```php
$config = ConfigLoader::load(__DIR__ . '/config/snmp.json');
$snmp = new Snmp($config['devices']['router'], $logger);
```

### Преимущества использования конфигурации

✅ Централизованное управление параметрами  
✅ Легко добавлять новые устройства  
✅ Безопасное хранение credentials  
✅ Простая смена параметров без изменения кода  
✅ Соответствие архитектуре проекта  
✅ Возможность разных конфигов для dev/prod  

## Переменные окружения для чувствительных данных

Для продакшена рекомендуется хранить чувствительные данные в переменных окружения:

```php
<?php
use App\Config\ConfigLoader;

// Загрузка базовой конфигурации
$config = ConfigLoader::load(__DIR__ . '/config/snmp.json');

// Переопределение из переменных окружения
foreach ($config['devices'] as $name => &$device) {
    $envPrefix = 'SNMP_' . strtoupper($name);
    
    if ($host = getenv("{$envPrefix}_HOST")) {
        $device['host'] = $host;
    }
    
    if ($community = getenv("{$envPrefix}_COMMUNITY")) {
        $device['community'] = $community;
    }
    
    if ($pass = getenv("{$envPrefix}_AUTH_PASS")) {
        $device['v3_auth_passphrase'] = $pass;
    }
    
    if ($pass = getenv("{$envPrefix}_PRIV_PASS")) {
        $device['v3_privacy_passphrase'] = $pass;
    }
}

// Использование
$snmp = new Snmp($config['devices']['router'], $logger);
```

В `.env`:
```bash
SNMP_ROUTER_HOST=192.168.1.1
SNMP_ROUTER_COMMUNITY=secret_community
SNMP_SERVER_AUTH_PASS=strong_auth_password
SNMP_SERVER_PRIV_PASS=strong_priv_password
```

## Дополнительные ресурсы

- Файл конфигурации: `/config/snmp.json`
- Пример использования: `/examples/snmp_example.php`
- Нагрузочный тест: `/snmp_load_test.php`
- Полная документация: `/SNMP_README.md`

---

**Версия:** 1.0  
**Дата:** 31 октября 2025  
**Статус:** ✅ Готово к использованию

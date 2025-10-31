# Netmap - Система сбора топологии сети через LLDP

## Описание

Система автоматического обнаружения и построения топологии сети с использованием протокола LLDP (Link Layer Discovery Protocol) через SNMP.

### Возможности

- **Автоматическое обнаружение устройств** - рекурсивный обход сети через LLDP
- **Поддержка изолированных сетей** - множественные точки входа для сканирования
- **Построение графа топологии** - определение связей и иерархии устройств
- **Хранение в БД** - сохранение и отслеживание изменений топологии
- **Детектирование циклов** - обнаружение петель в сети
- **Классификация устройств** - автоматическое определение типа (core/distribution/access)
- **История изменений** - отслеживание активности устройств

## Архитектура

Система состоит из трех основных компонентов:

### 1. LldpCollector

**Назначение:** Сбор данных LLDP с сетевых устройств через SNMP

**Основные методы:**
- `discoverTopology()` - запуск процесса обнаружения
- `getDiscoveredDevices()` - получение списка устройств
- `getDiscoveredLinks()` - получение списка связей
- `getStats()` - статистика сбора

**Собираемые данные:**
- Chassis ID (уникальный идентификатор)
- System Name (hostname)
- System Description (модель, версия ОС)
- Management IP (адрес управления)
- Capabilities (routing, switching, bridge)
- Локальные порты
- Информация о соседях

### 2. TopologyBuilder

**Назначение:** Построение графа сети из собранных данных

**Основные методы:**
- `buildTopology()` - построение графа
- `getNodes()` - получение узлов графа
- `getEdges()` - получение ребер графа
- `getRootNodes()` - получение корневых узлов
- `getCycles()` - получение обнаруженных циклов
- `getStats()` - статистика топологии

**Функции:**
- Построение графа смежности
- Определение корневых устройств (core switches/routers)
- Вычисление уровней в иерархии (BFS)
- Детектирование циклов (DFS)
- Классификация устройств по типам
- Вычисление метрик (степень узла, центральность)

### 3. TopologySaver

**Назначение:** Сохранение топологии в базу данных MySQL

**Основные методы:**
- `createTables()` - создание таблиц БД
- `startScan()` - начало нового сканирования
- `saveTopology()` - сохранение топологии
- `finishScan()` - завершение сканирования
- `getDeviceByChassisId()` - получение устройства
- `getActiveDevices()` - список активных устройств
- `getDeviceLinks()` - связи устройства
- `cleanupInactiveDevices()` - очистка старых данных
- `getTopologyStats()` - статистика из БД

## Структура базы данных

### Таблица: netmap_devices
Хранение информации об устройствах

| Поле | Тип | Описание |
|------|-----|----------|
| id | INT | Первичный ключ |
| chassis_id | VARCHAR(255) | Уникальный ID устройства |
| sys_name | VARCHAR(255) | Hostname |
| sys_desc | TEXT | Описание системы |
| management_ip | VARCHAR(45) | IP для управления |
| capabilities | JSON | Возможности устройства |
| node_level | INT | Уровень в иерархии |
| node_type | VARCHAR(50) | Тип узла (core/distribution/access) |
| is_root | BOOLEAN | Корневой узел |
| is_active | BOOLEAN | Активное устройство |
| discovered_at | DATETIME | Время обнаружения |
| last_seen_at | DATETIME | Последний раз видели |

### Таблица: netmap_device_ports
Хранение информации о портах устройств

| Поле | Тип | Описание |
|------|-----|----------|
| id | INT | Первичный ключ |
| device_id | INT | FK на устройство |
| port_index | INT | Индекс порта |
| port_id | VARCHAR(255) | Идентификатор порта |
| port_desc | VARCHAR(255) | Описание порта |

### Таблица: netmap_device_links
Хранение связей между устройствами

| Поле | Тип | Описание |
|------|-----|----------|
| id | INT | Первичный ключ |
| source_device_id | INT | FK на исходное устройство |
| source_port_id | INT | FK на исходный порт |
| target_device_id | INT | FK на целевое устройство |
| target_port_id | INT | FK на целевой порт |
| link_type | VARCHAR(50) | Тип связи (lldp) |
| is_active | BOOLEAN | Активная связь |
| discovered_at | DATETIME | Время обнаружения |
| last_seen_at | DATETIME | Последний раз видели |

### Таблица: netmap_topology_scans
История сканирований

| Поле | Тип | Описание |
|------|-----|----------|
| id | INT | Первичный ключ |
| scan_started_at | DATETIME | Начало сканирования |
| scan_finished_at | DATETIME | Завершение сканирования |
| devices_discovered | INT | Обнаружено устройств |
| links_discovered | INT | Обнаружено связей |
| status | ENUM | Статус (running/completed/failed) |
| error_message | TEXT | Сообщение об ошибке |

## Конфигурация

### lldp_topology.json

```json
{
    "seed_devices": [
        "192.168.1.1",
        "192.168.1.2",
        "10.0.0.1"
    ],
    "snmp": {
        "community": "public",
        "version": 1,
        "timeout": 2000000,
        "retries": 2
    },
    "connection_timeout": 5,
    "max_devices": 1000
}
```

**Параметры:**
- `seed_devices` - массив начальных устройств для сканирования (IP или hostname)
- `snmp.community` - SNMP community string
- `snmp.version` - версия SNMP (0=v1, 1=v2c, 3=v3)
- `snmp.timeout` - таймаут в микросекундах
- `snmp.retries` - количество повторов
- `connection_timeout` - таймаут подключения в секундах
- `max_devices` - максимальное количество устройств для обнаружения

### topology_db.json

```json
{
    "host": "localhost",
    "port": 3306,
    "database": "network_topology",
    "username": "topology_user",
    "password": "secure_password",
    "charset": "utf8mb4",
    "persistent": false
}
```

## Использование

### Базовый пример

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\Netmap\LldpCollector;
use App\Component\Netmap\TopologyBuilder;
use App\Component\Netmap\TopologySaver;
use App\Config\ConfigLoader;

// Инициализация логгера
$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'file_name' => 'topology.log',
    'max_files' => 5,
    'max_file_size' => 10,
]);

// Загрузка конфигурации
$config = ConfigLoader::load(__DIR__ . '/config/lldp_topology.json');

// 1. Сбор данных LLDP
$collector = new LldpCollector($config, $logger);
$data = $collector->discoverTopology();

echo "Обнаружено устройств: " . count($data['devices']) . "\n";
echo "Обнаружено связей: " . count($data['links']) . "\n";

// 2. Построение графа топологии
$builder = new TopologyBuilder($logger);
$topology = $builder->buildTopology($data);

echo "Корневых узлов: " . count($topology['root_nodes']) . "\n";
echo "Обнаружено циклов: " . count($topology['cycles']) . "\n";

// 3. Сохранение в БД
$dbConfig = ConfigLoader::load(__DIR__ . '/config/topology_db.json');
$mysql = new MySQL($dbConfig, $logger);
$saver = new TopologySaver($mysql, $logger);

// Создаем таблицы (если нужно)
$saver->createTables();

// Сохраняем топологию
$scanId = $saver->startScan();
$stats = $saver->saveTopology($topology);
$saver->finishScan($scanId, true);

echo "Устройств добавлено: " . $stats['devices_added'] . "\n";
echo "Связей добавлено: " . $stats['links_added'] . "\n";
```

### Получение данных из БД

```php
// Получить все активные устройства
$devices = $saver->getActiveDevices();

foreach ($devices as $device) {
    echo "{$device['sys_name']} - {$device['management_ip']}\n";
    echo "  Тип: {$device['node_type']}, Уровень: {$device['node_level']}\n";
    
    // Получить связи устройства
    $links = $saver->getDeviceLinks($device['id']);
    echo "  Связей: " . count($links) . "\n";
}

// Статистика топологии
$stats = $saver->getTopologyStats();
print_r($stats);
```

### Очистка старых данных

```php
// Удалить неактивные устройства старше 30 дней
$deleted = $saver->cleanupInactiveDevices(30);
echo "Удалено устройств: {$deleted}\n";
```

## LLDP OID Reference

Система использует следующие LLDP MIB OID:

### Локальная информация
- `1.0.8802.1.1.2.1.3.1.0` - lldpLocChassisIdSubtype
- `1.0.8802.1.1.2.1.3.2.0` - lldpLocChassisId
- `1.0.8802.1.1.2.1.3.3.0` - lldpLocSysName
- `1.0.8802.1.1.2.1.3.4.0` - lldpLocSysDesc
- `1.0.8802.1.1.2.1.3.5.0` - lldpLocSysCapSupported
- `1.0.8802.1.1.2.1.3.6.0` - lldpLocSysCapEnabled

### Локальные порты
- `1.0.8802.1.1.2.1.3.7.1.2` - lldpLocPortIdSubtype
- `1.0.8802.1.1.2.1.3.7.1.3` - lldpLocPortId
- `1.0.8802.1.1.2.1.3.7.1.4` - lldpLocPortDesc

### Удаленные устройства
- `1.0.8802.1.1.2.1.4.1.1.4` - lldpRemChassisIdSubtype
- `1.0.8802.1.1.2.1.4.1.1.5` - lldpRemChassisId
- `1.0.8802.1.1.2.1.4.1.1.6` - lldpRemPortIdSubtype
- `1.0.8802.1.1.2.1.4.1.1.7` - lldpRemPortId
- `1.0.8802.1.1.2.1.4.1.1.8` - lldpRemPortDesc
- `1.0.8802.1.1.2.1.4.1.1.9` - lldpRemSysName
- `1.0.8802.1.1.2.1.4.1.1.10` - lldpRemSysDesc
- `1.0.8802.1.1.2.1.4.1.1.11` - lldpRemSysCapSupported
- `1.0.8802.1.1.2.1.4.1.1.12` - lldpRemSysCapEnabled

### Адреса управления
- `1.0.8802.1.1.2.1.4.2.1.4` - lldpRemManAddr

## Системные требования

- PHP 8.1 или выше
- PHP SNMP расширение (ext-snmp)
- MySQL 5.7+ или MySQL 8.0+
- Устройства с поддержкой LLDP и SNMP
- Доступ к устройствам по SNMP (обычно порт 161 UDP)

## Тестирование

Для тестирования системы запустите:

```bash
php examples/netmap_topology_test.php
```

Тестовый скрипт:
- Создает виртуальную топологию из 5 устройств
- Собирает данные через LldpCollector
- Строит граф через TopologyBuilder
- Сохраняет в БД через TopologySaver
- Выводит детальную статистику

## Логирование

Все компоненты системы используют Logger для записи операций:

- **DEBUG** - детальная информация о процессе сканирования
- **INFO** - основные события (обнаружение устройств, завершение операций)
- **WARNING** - некритичные ошибки (недоступные устройства)
- **ERROR** - критичные ошибки (сбои SNMP, ошибки БД)

Логи сохраняются в файл, указанный в конфигурации Logger.

## Troubleshooting

### Устройства не обнаруживаются

1. Проверьте доступность устройств: `ping <IP>`
2. Проверьте SNMP доступ: `snmpwalk -v2c -c public <IP> 1.0.8802.1.1.2`
3. Убедитесь что LLDP включен на устройствах
4. Проверьте правильность community string
5. Проверьте firewall правила (UDP порт 161)

### Ошибки подключения к БД

1. Проверьте конфигурацию в `config/topology_db.json`
2. Убедитесь что БД существует: `CREATE DATABASE network_topology;`
3. Проверьте права пользователя БД
4. Проверьте доступность MySQL сервера

### Неправильная иерархия

1. Убедитесь что все устройства обнаружены
2. Проверьте что связи корректно определены
3. Возможно требуется настройка seed_devices - добавьте core switches

## Производительность

### Оптимизация сканирования

- Устанавливайте разумный `max_devices` для ограничения области сканирования
- Используйте короткие таймауты SNMP для быстрого определения недоступных устройств
- Начинайте сканирование с core устройств для более быстрого обхода

### Оптимизация БД

- Создайте индексы на часто используемых полях
- Регулярно запускайте `cleanupInactiveDevices()` для удаления старых данных
- Используйте `persistent: false` в конфигурации MySQL для коротких скриптов

## Лицензия

См. основной README.md проекта

## Поддержка

Для вопросов и сообщений об ошибках используйте систему issue tracking проекта.

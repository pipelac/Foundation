# Примеры использования Netmap

## Доступные примеры

### 1. netmap_topology_test.php
**Назначение:** Тестирование функциональности с моковыми данными

**Использование:**
```bash
php examples/netmap_topology_test.php
```

**Что делает:**
- Создает виртуальную топологию из 5 устройств (1 router, 2 distribution switches, 2 access switches)
- Тестирует LldpCollector с моковыми SNMP данными
- Тестирует TopologyBuilder для построения графа
- Тестирует TopologySaver для работы с БД (если доступна)
- Выводит детальную статистику и результаты

**Когда использовать:**
- Для проверки работоспособности системы
- При разработке и отладке
- Для демонстрации возможностей

### 2. netmap_topology_scan.php
**Назначение:** Production скрипт для реального сканирования сети

**Использование:**
```bash
php examples/netmap_topology_scan.php
```

**Что делает:**
- Загружает реальную конфигурацию из config/lldp_topology.json
- Подключается к реальным устройствам через SNMP
- Собирает LLDP данные рекурсивно
- Строит граф топологии
- Сохраняет результаты в БД
- Выполняет очистку старых данных

**Когда использовать:**
- Для регулярного сканирования production сети
- В задачах cron для автоматического мониторинга
- При изменениях в сети для обновления топологии

**Cron пример (ежедневное сканирование в 2:00 ночи):**
```bash
0 2 * * * cd /var/www/project && php examples/netmap_topology_scan.php >> logs/scan_cron.log 2>&1
```

## Подготовка к использованию

### Шаг 1: Конфигурация LLDP
Создайте или отредактируйте `config/lldp_topology.json`:

```json
{
    "seed_devices": [
        "192.168.1.1",
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
- `seed_devices` - IP адреса начальных устройств (обычно core switches/routers)
- `snmp.community` - SNMP community string (обычно "public" для чтения)
- `snmp.version` - версия SNMP (0=v1, 1=v2c, 3=v3)
- `snmp.timeout` - таймаут в микросекундах (2000000 = 2 секунды)
- `snmp.retries` - количество повторов при ошибке
- `connection_timeout` - таймаут подключения в секундах
- `max_devices` - максимальное количество устройств для обнаружения

### Шаг 2: Конфигурация БД
Создайте `config/topology_db.json`:

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

### Шаг 3: Создание БД
```sql
CREATE DATABASE network_topology CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'topology_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON network_topology.* TO 'topology_user'@'localhost';
FLUSH PRIVILEGES;
```

Таблицы создадутся автоматически при первом запуске.

### Шаг 4: Проверка SNMP доступа
Убедитесь что устройства доступны по SNMP:

```bash
# Проверка доступности SNMP
snmpwalk -v2c -c public 192.168.1.1 1.3.6.1.2.1.1.1.0

# Проверка LLDP данных
snmpwalk -v2c -c public 192.168.1.1 1.0.8802.1.1.2
```

Если команды не работают:
1. Проверьте что SNMP агент запущен на устройстве
2. Проверьте community string
3. Проверьте firewall правила (UDP порт 161)
4. Убедитесь что LLDP включен на устройстве

## Примеры использования API

### Простое сканирование

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Component\Logger;
use App\Component\Netmap\LldpCollector;
use App\Component\Config\ConfigLoader;

$logger = new Logger([
    'directory' => __DIR__ . '/../logs',
    'file_name' => 'scan.log',
]);

$config = ConfigLoader::load(__DIR__ . '/../config/lldp_topology.json');
$collector = new LldpCollector($config, $logger);

$data = $collector->discoverTopology();

echo "Найдено устройств: " . count($data['devices']) . "\n";
echo "Найдено связей: " . count($data['links']) . "\n";
```

### Построение графа

```php
<?php
use App\Component\Netmap\TopologyBuilder;

$builder = new TopologyBuilder($logger);
$topology = $builder->buildTopology($data);

// Корневые узлы
foreach ($topology['root_nodes'] as $rootId) {
    $node = $topology['nodes'][$rootId];
    echo "Root: {$node['sys_name']}\n";
}

// Статистика
$stats = $builder->getStats();
print_r($stats);
```

### Сохранение в БД

```php
<?php
use App\Component\MySQL;
use App\Component\Netmap\TopologySaver;

$dbConfig = ConfigLoader::load(__DIR__ . '/../config/topology_db.json');
$mysql = new MySQL($dbConfig, $logger);
$saver = new TopologySaver($mysql, $logger);

// Создать таблицы
$saver->createTables();

// Сохранить топологию
$scanId = $saver->startScan();
$stats = $saver->saveTopology($topology);
$saver->finishScan($scanId, true);
```

### Получение данных из БД

```php
<?php
// Все активные устройства
$devices = $saver->getActiveDevices();

foreach ($devices as $device) {
    echo "{$device['sys_name']} - {$device['management_ip']}\n";
    
    // Связи устройства
    $links = $saver->getDeviceLinks($device['id']);
    echo "  Связей: " . count($links) . "\n";
}

// Статистика
$stats = $saver->getTopologyStats();
echo "Активных устройств: {$stats['active_devices']}\n";
echo "Активных связей: {$stats['active_links']}\n";
```

### Работа с конкретным устройством

```php
<?php
// Получить устройство по Chassis ID
$device = $saver->getDeviceByChassisId('00:11:22:33:44:55');

if ($device !== null) {
    echo "Устройство: {$device['sys_name']}\n";
    echo "IP: {$device['management_ip']}\n";
    echo "Тип: {$device['node_type']}\n";
    echo "Уровень: {$device['node_level']}\n";
    
    // Получить связи
    $links = $saver->getDeviceLinks($device['id']);
    
    foreach ($links as $link) {
        if ($link['source_device_id'] == $device['id']) {
            echo "  -> {$link['target_name']}\n";
        } else {
            echo "  <- {$link['source_name']}\n";
        }
    }
}
```

## Troubleshooting

### Устройства не обнаруживаются
1. Проверьте доступность: `ping <IP>`
2. Проверьте SNMP: `snmpwalk -v2c -c public <IP> 1.3.6.1.2.1.1.1.0`
3. Проверьте LLDP: `snmpwalk -v2c -c public <IP> 1.0.8802.1.1.2`
4. Убедитесь что community string правильный
5. Проверьте firewall (UDP 161)

### Ошибки SNMP timeout
- Увеличьте `snmp.timeout` в конфигурации
- Увеличьте `snmp.retries`
- Проверьте сетевую связность

### Неполная топология
- Добавьте больше seed_devices
- Проверьте что LLDP включен на всех устройствах
- Убедитесь что устройства видят друг друга через LLDP

### Ошибки подключения к БД
- Проверьте что MySQL сервер запущен
- Проверьте параметры подключения в config/topology_db.json
- Проверьте права пользователя БД

### Долгое сканирование
- Уменьшите `max_devices`
- Уменьшите `snmp.timeout`
- Оптимизируйте список seed_devices (начинайте с core устройств)

## Мониторинг

### Проверка логов
```bash
# Последние записи
tail -f logs/topology_scan.log

# Поиск ошибок
grep ERROR logs/topology_scan.log

# Статистика сканирований
grep "Сканирование завершено" logs/topology_scan.log
```

### SQL запросы для мониторинга

```sql
-- Последнее сканирование
SELECT * FROM netmap_topology_scans 
ORDER BY scan_started_at DESC 
LIMIT 1;

-- Активные устройства
SELECT COUNT(*) FROM netmap_devices WHERE is_active = 1;

-- Устройства по типам
SELECT node_type, COUNT(*) as count 
FROM netmap_devices 
WHERE is_active = 1 
GROUP BY node_type;

-- История изменений топологии
SELECT 
    scan_started_at,
    devices_discovered,
    links_discovered,
    status
FROM netmap_topology_scans
ORDER BY scan_started_at DESC
LIMIT 10;

-- Недавно пропавшие устройства
SELECT sys_name, management_ip, last_seen_at
FROM netmap_devices
WHERE is_active = 0
ORDER BY last_seen_at DESC
LIMIT 20;
```

## Performance Tips

1. **Оптимизация seed_devices**
   - Начинайте с core switches
   - Избегайте дублирования начальных точек

2. **SNMP параметры**
   - Используйте SNMPv2c для лучшей производительности
   - Оптимизируйте timeout и retries

3. **База данных**
   - Регулярно запускайте OPTIMIZE TABLE
   - Используйте индексы (создаются автоматически)
   - Очищайте старые данные через cleanupInactiveDevices()

4. **Cron задачи**
   - Запускайте в off-peak часы (например 2:00 ночи)
   - Используйте flock для предотвращения параллельных запусков:
     ```bash
     0 2 * * * flock -n /tmp/topology_scan.lock -c "cd /var/www/project && php examples/netmap_topology_scan.php >> logs/scan_cron.log 2>&1"
     ```

## Интеграция

### Экспорт в JSON

```php
<?php
$devices = $saver->getActiveDevices();
file_put_contents('topology.json', json_encode($devices, JSON_PRETTY_PRINT));
```

### Визуализация (пример с D3.js)

```php
<?php
// Подготовка данных для визуализации
$nodes = [];
$links = [];

foreach ($topology['nodes'] as $node) {
    $nodes[] = [
        'id' => $node['chassis_id'],
        'name' => $node['sys_name'],
        'type' => $node['node_type'],
        'level' => $node['level'],
    ];
}

foreach ($topology['edges'] as $edge) {
    $links[] = [
        'source' => $edge['source_chassis_id'],
        'target' => $edge['target_chassis_id'],
    ];
}

$graph = ['nodes' => $nodes, 'links' => $links];
file_put_contents('topology_graph.json', json_encode($graph, JSON_PRETTY_PRINT));
```

## Дополнительная информация

См. также:
- [NETMAP_README.md](../NETMAP_README.md) - Полная документация
- [lldp_topology.json](../config/lldp_topology.json) - Пример конфигурации
- [topology_db.json](../config/topology_db.json) - Пример конфигурации БД

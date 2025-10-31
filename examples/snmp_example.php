<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Component\Snmp;
use App\Component\Logger;
use App\Config\ConfigLoader;

echo "=== Пример использования Snmp класса с конфигурацией ===\n\n";

try {
    // Создание логгера
    $logger = new Logger([
        'directory' => __DIR__ . '/../logs',
        'file_name' => 'snmp_example.log',
        'max_files' => 3,
        'max_file_size' => 5,
    ]);

    echo "✓ Логгер инициализирован\n\n";

    // Загрузка конфигурации SNMP
    $config = ConfigLoader::load(__DIR__ . '/../config/snmp.json');
    echo "✓ Конфигурация загружена\n";
    echo "  Устройства: " . implode(', ', array_keys($config['devices'])) . "\n";
    echo "  По умолчанию: {$config['default']}\n\n";

    // Пример 1: Подключение к устройству по умолчанию
    echo "--- Пример 1: Подключение к устройству по умолчанию ---\n";
    $defaultDevice = $config['default'];
    $snmp = new Snmp($config['devices'][$defaultDevice], $logger);
    echo "✓ Подключено к устройству: {$defaultDevice}\n";

    // Получение информации о соединении
    $connInfo = $snmp->getConnectionInfo();
    echo "  Хост: {$connInfo['host']}\n";
    echo "  Версия: {$connInfo['version']}\n";
    echo "  Тайм-аут: " . ($connInfo['timeout'] / 1000000) . " сек\n";
    echo "  Повторы: {$connInfo['retries']}\n\n";

    // Получение системной информации
    echo "--- Получение системной информации ---\n";
    $systemInfo = $snmp->getSystemInfo();
    
    if ($systemInfo['sysDescr']) {
        echo "✓ Системная информация получена:\n";
        echo "  sysDescr: " . substr((string)$systemInfo['sysDescr'], 0, 60) . "...\n";
        echo "  sysName: " . ($systemInfo['sysName'] ?? 'N/A') . "\n";
        echo "  sysLocation: " . ($systemInfo['sysLocation'] ?? 'N/A') . "\n";
        echo "  sysContact: " . ($systemInfo['sysContact'] ?? 'N/A') . "\n";
        echo "  sysUpTime: " . ($systemInfo['sysUpTime'] ?? 'N/A') . "\n";
    } else {
        echo "⚠ Не удалось получить системную информацию\n";
    }
    echo "\n";

    // Получение сетевых интерфейсов
    echo "--- Получение сетевых интерфейсов ---\n";
    $interfaces = $snmp->getNetworkInterfaces();
    
    if (count($interfaces) > 0) {
        echo "✓ Найдено интерфейсов: " . count($interfaces) . "\n";
        foreach (array_slice($interfaces, 0, 5) as $interface) {
            echo "  Interface #{$interface['index']}: {$interface['description']}\n";
        }
        if (count($interfaces) > 5) {
            echo "  ... и ещё " . (count($interfaces) - 5) . " интерфейсов\n";
        }
    } else {
        echo "⚠ Интерфейсы не найдены\n";
    }
    echo "\n";

    // Пример 2: Получение конкретных OID
    echo "--- Пример 2: Получение конкретных OID ---\n";
    $sysUpTime = $snmp->get('.1.3.6.1.2.1.1.3.0');
    if ($sysUpTime !== false) {
        echo "✓ sysUpTime: {$sysUpTime}\n";
    }
    
    $sysName = $snmp->get('.1.3.6.1.2.1.1.5.0');
    if ($sysName !== false) {
        echo "✓ sysName: {$sysName}\n";
    }
    echo "\n";

    // Пример 3: Множественное получение OID
    echo "--- Пример 3: Множественное получение OID ---\n";
    $oids = [
        '.1.3.6.1.2.1.1.1.0', // sysDescr
        '.1.3.6.1.2.1.1.3.0', // sysUpTime
        '.1.3.6.1.2.1.1.5.0', // sysName
    ];
    
    $results = $snmp->getMultiple($oids);
    echo "✓ Получено значений: " . count($results) . "\n";
    foreach ($results as $oid => $value) {
        echo "  {$oid} = " . substr((string)$value, 0, 50) . "\n";
    }
    echo "\n";

    // Пример 4: Обход дерева MIB (WALK)
    echo "--- Пример 4: Обход дерева MIB (системная группа) ---\n";
    $sysGroup = $snmp->walk('.1.3.6.1.2.1.1', false);
    
    if (is_array($sysGroup) && count($sysGroup) > 0) {
        echo "✓ Получено объектов: " . count($sysGroup) . "\n";
        $counter = 0;
        foreach ($sysGroup as $oid => $value) {
            if ($counter++ < 3) {
                echo "  {$oid} = " . substr((string)$value, 0, 40) . "...\n";
            }
        }
        if (count($sysGroup) > 3) {
            echo "  ... и ещё " . (count($sysGroup) - 3) . " объектов\n";
        }
    } else {
        echo "⚠ Не удалось получить данные через WALK\n";
    }
    echo "\n";

    // Закрытие соединения
    $snmp->close();
    echo "✓ SNMP соединение закрыто\n\n";

    // Пример 5: Работа с несколькими устройствами
    echo "--- Пример 5: Работа с несколькими устройствами ---\n";
    
    foreach ($config['devices'] as $deviceName => $deviceConfig) {
        echo "Устройство: {$deviceName}\n";
        echo "  Хост: {$deviceConfig['host']}\n";
        echo "  Версия SNMP: ";
        
        switch ($deviceConfig['version']) {
            case 0:
                echo "SNMPv1\n";
                break;
            case 1:
                echo "SNMPv2c\n";
                break;
            case 3:
                echo "SNMPv3";
                if (isset($deviceConfig['v3_security_level'])) {
                    echo " ({$deviceConfig['v3_security_level']})";
                }
                echo "\n";
                break;
        }
        
        echo "  Тайм-аут: " . ($deviceConfig['timeout'] / 1000000) . " сек\n";
        echo "\n";
    }

    echo "=== Пример завершен успешно! ===\n";
    echo "\nСовет: Проверьте лог-файл logs/snmp_example.log для просмотра детальных записей.\n";

} catch (\App\Component\Exception\SnmpConnectionException $e) {
    echo "\n✗ Ошибка подключения к SNMP агенту:\n";
    echo "  " . $e->getMessage() . "\n";
    echo "\nПроверьте:\n";
    echo "  1. Запущен ли SNMP агент (sudo service snmpd status)\n";
    echo "  2. Правильность IP адреса и порта\n";
    echo "  3. Правильность community string\n";
    echo "  4. Доступность хоста (ping)\n";
    
} catch (\App\Component\Exception\SnmpValidationException $e) {
    echo "\n✗ Ошибка валидации параметров:\n";
    echo "  " . $e->getMessage() . "\n";
    echo "\nПроверьте конфигурационный файл config/snmp.json\n";
    
} catch (\App\Component\Exception\SnmpException $e) {
    echo "\n✗ Ошибка SNMP:\n";
    echo "  " . $e->getMessage() . "\n";
    
} catch (\Exception $e) {
    echo "\n✗ Общая ошибка:\n";
    echo "  " . $e->getMessage() . "\n";
    echo "\nTrace:\n";
    echo $e->getTraceAsString() . "\n";
}

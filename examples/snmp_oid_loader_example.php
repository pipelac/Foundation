<?php

/**
 * Пример использования механизма загрузки OID из конфигурационных файлов
 * 
 * Демонстрирует:
 * - Загрузку OID конфигурации из JSON файла
 * - Получение OID по имени для разных типов устройств
 * - Использование общих (common) и специфичных OID
 * - Интеграцию SnmpOid с классом Snmp
 * - Работу с именованными OID вместо прямых OID строк
 */

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use App\Component\Snmp;
use App\Component\SnmpOid;
use App\Component\Logger;

// Инициализация логгера
$logger = new Logger([
    'directory' => __DIR__ . '/../logs',
    'file_name' => 'snmp_oid_example.log',
    'max_files' => 3,
    'max_file_size' => 5,
]);

echo "═══════════════════════════════════════════════════════════\n";
echo "  SNMP OID Loader - Пример использования\n";
echo "═══════════════════════════════════════════════════════════\n\n";

try {
    // 1. Загрузка OID конфигурации
    echo "1. Загрузка OID конфигурации...\n";
    $oidLoader = new SnmpOid(
        __DIR__ . '/../config/snmp-oids.json',
        $logger
    );
    
    echo "   ✓ Конфигурация загружена успешно\n\n";
    
    // 2. Получение статистики
    echo "2. Статистика загруженной конфигурации:\n";
    $stats = $oidLoader->getStats();
    echo "   Путь к файлу: {$stats['config_path']}\n";
    echo "   Количество типов устройств: {$stats['device_types_count']}\n";
    echo "   Всего OID: {$stats['total_oids']}\n";
    echo "   \n   Детализация по устройствам:\n";
    foreach ($stats['device_types'] as $deviceType => $count) {
        echo "   - {$deviceType}: {$count} OID\n";
    }
    echo "\n";
    
    // 3. Работа с common OID (без указания типа устройства)
    echo "3. Получение общих (common) OID:\n";
    
    $sysNameOid = $oidLoader->getOid('sysName');
    echo "   sysName: {$sysNameOid}\n";
    
    $sysDescrOid = $oidLoader->getOid('sysDescr');
    echo "   sysDescr: {$sysDescrOid}\n";
    
    $ifInOctetsOid = $oidLoader->getOid('ifInOctets', null, '1');
    echo "   ifInOctets для порта 1: {$ifInOctetsOid}\n\n";
    
    // 4. Получение специфичных OID для D-Link DES-3526
    echo "4. Получение специфичных OID для D-Link DES-3526:\n";
    $deviceType = 'D-Link DES-3526';
    
    $cpuOid = $oidLoader->getOid('CPUutilizationIn5sec', $deviceType);
    echo "   CPUutilizationIn5sec: {$cpuOid}\n";
    
    $tempOid = $oidLoader->getOid('temperatureCurrent', $deviceType);
    echo "   temperatureCurrent: {$tempOid}\n";
    
    $portTempOid = $oidLoader->getOid('port_temp', $deviceType, '49');
    echo "   port_temp для порта 49: {$portTempOid}\n\n";
    
    // 5. Получение метаданных OID
    echo "5. Получение метаданных OID:\n";
    
    $rebootData = $oidLoader->getOidData('reboot', $deviceType);
    echo "   OID для перезагрузки D-Link DES-3526:\n";
    echo "     - OID: {$rebootData['oid']}\n";
    echo "     - Описание: {$rebootData['description']}\n";
    echo "     - Тип операции: {$rebootData['type']}\n";
    echo "     - Тип значения: {$rebootData['value_type']}\n";
    echo "     - Значение: {$rebootData['value']}\n\n";
    
    // 6. Проверка существования OID
    echo "6. Проверка существования OID:\n";
    
    $exists1 = $oidLoader->hasOid('sysName');
    echo "   sysName существует: " . ($exists1 ? 'Да' : 'Нет') . "\n";
    
    $exists2 = $oidLoader->hasOid('nonExistentOid');
    echo "   nonExistentOid существует: " . ($exists2 ? 'Да' : 'Нет') . "\n\n";
    
    // 7. Получение списка доступных OID
    echo "7. Список доступных типов устройств:\n";
    $deviceTypes = $oidLoader->getDeviceTypes();
    foreach ($deviceTypes as $type) {
        echo "   - {$type}\n";
    }
    echo "\n";
    
    // 8. Интеграция с классом Snmp
    echo "8. Интеграция с классом Snmp:\n";
    echo "   Примечание: Для реальной работы требуется SNMP агент\n\n";
    
    echo "   Пример кода:\n";
    echo "   ```php\n";
    echo "   // Создание SNMP соединения с OID loader\n";
    echo "   \$snmpConfig = [\n";
    echo "       'host' => '192.168.1.1',\n";
    echo "       'community' => 'public',\n";
    echo "       'version' => Snmp::VERSION_2C,\n";
    echo "       'device_type' => 'D-Link DES-3526',\n";
    echo "   ];\n";
    echo "   \n";
    echo "   \$snmp = new Snmp(\$snmpConfig, \$logger, \$oidLoader);\n";
    echo "   \n";
    echo "   // Получение значения по имени OID вместо прямого OID\n";
    echo "   \$sysName = \$snmp->getByName('sysName');\n";
    echo "   \$cpuUsage = \$snmp->getByName('CPUutilizationIn5sec');\n";
    echo "   \n";
    echo "   // Получение статистики порта 1\n";
    echo "   \$portStats = \$snmp->getMultipleByName([\n";
    echo "       'ifInOctets',\n";
    echo "       'ifOutOctets',\n";
    echo "       'ifInErrors',\n";
    echo "       'ifOutErrors',\n";
    echo "   ], '1');\n";
    echo "   \n";
    echo "   // Обход VLAN\n";
    echo "   \$vlans = \$snmp->walkByName('vlan_names');\n";
    echo "   \n";
    echo "   // SET операция (сохранение конфигурации)\n";
    echo "   // Тип значения берется из конфигурации автоматически\n";
    echo "   \$snmp->setByName('save_config', 3);\n";
    echo "   \n";
    echo "   // SET операция с указанием порта\n";
    echo "   \$snmp->setByName('set_portLable', 'Uplink to Core', '1');\n";
    echo "   ```\n\n";
    
    // 9. Демонстрация наследования OID
    echo "9. Демонстрация наследования OID:\n";
    echo "   Common OID могут быть переопределены для конкретных устройств\n\n";
    
    // sysName определен и в common, и в некоторых устройствах
    $commonSysName = $oidLoader->getOid('sysName', null);
    $dlinkSysName = $oidLoader->getOid('sysName', 'D-Link DES-3526');
    
    echo "   sysName (common): {$commonSysName}\n";
    echo "   sysName (D-Link DES-3526): {$dlinkSysName}\n";
    echo "   Оба OID одинаковы, т.к. D-Link DES-3526 использует common OID\n\n";
    
    // port_duplex определен и в common, и переопределен в D-Link DES-3526
    $commonDuplex = $oidLoader->getOid('port_duplex', null);
    $dlinkDuplex = $oidLoader->getOid('port_duplex', 'D-Link DES-3526');
    
    echo "   port_duplex (common): {$commonDuplex}\n";
    echo "   port_duplex (D-Link DES-3526): {$dlinkDuplex}\n";
    if ($commonDuplex !== $dlinkDuplex) {
        echo "   OID различаются - D-Link использует специфичный OID!\n";
    }
    echo "\n";
    
    echo "═══════════════════════════════════════════════════════════\n";
    echo "  Пример завершен успешно\n";
    echo "═══════════════════════════════════════════════════════════\n";
    
} catch (\Exception $e) {
    echo "\n✗ Ошибка: {$e->getMessage()}\n";
    echo "  Тип: " . get_class($e) . "\n";
    echo "  Файл: {$e->getFile()}:{$e->getLine()}\n\n";
    
    if ($logger) {
        $logger->error('Ошибка в примере SNMP OID', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
    
    exit(1);
}

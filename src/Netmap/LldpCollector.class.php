<?php

declare(strict_types=1);

namespace App\Component\Netmap;

use App\Component\Snmp;
use App\Component\SnmpOid;
use App\Component\Logger;
use App\Component\Exception\LldpCollectorException;
use App\Component\Exception\SnmpException;

/**
 * Класс для сбора топологии сети через LLDP протокол
 * 
 * Возможности:
 * - Рекурсивное обнаружение устройств в сети через LLDP
 * - Сбор информации об устройствах и их портах
 * - Определение связей между устройствами
 * - Поддержка множественных изолированных сетей
 * - Автоматическое определение IP адресов управления
 * 
 * Системные требования:
 * - PHP 8.1 или выше
 * - SNMP расширение
 * - Доступ к устройствам по SNMP
 * - Поддержка LLDP на устройствах
 */
class LldpCollector
{
    /**
     * LLDP MIB OID константы
     */
    private const OID_LLDP_LOC_CHASSIS_ID_SUBTYPE = '1.0.8802.1.1.2.1.3.1.0';
    private const OID_LLDP_LOC_CHASSIS_ID = '1.0.8802.1.1.2.1.3.2.0';
    private const OID_LLDP_LOC_SYS_NAME = '1.0.8802.1.1.2.1.3.3.0';
    private const OID_LLDP_LOC_SYS_DESC = '1.0.8802.1.1.2.1.3.4.0';
    private const OID_LLDP_LOC_SYS_CAP_SUPPORTED = '1.0.8802.1.1.2.1.3.5.0';
    private const OID_LLDP_LOC_SYS_CAP_ENABLED = '1.0.8802.1.1.2.1.3.6.0';
    
    private const OID_LLDP_LOC_PORT_TABLE = '1.0.8802.1.1.2.1.3.7.1';
    private const OID_LLDP_LOC_PORT_ID_SUBTYPE = '1.0.8802.1.1.2.1.3.7.1.2';
    private const OID_LLDP_LOC_PORT_ID = '1.0.8802.1.1.2.1.3.7.1.3';
    private const OID_LLDP_LOC_PORT_DESC = '1.0.8802.1.1.2.1.3.7.1.4';
    
    private const OID_LLDP_REM_TABLE = '1.0.8802.1.1.2.1.4.1.1';
    private const OID_LLDP_REM_CHASSIS_ID_SUBTYPE = '1.0.8802.1.1.2.1.4.1.1.4';
    private const OID_LLDP_REM_CHASSIS_ID = '1.0.8802.1.1.2.1.4.1.1.5';
    private const OID_LLDP_REM_PORT_ID_SUBTYPE = '1.0.8802.1.1.2.1.4.1.1.6';
    private const OID_LLDP_REM_PORT_ID = '1.0.8802.1.1.2.1.4.1.1.7';
    private const OID_LLDP_REM_PORT_DESC = '1.0.8802.1.1.2.1.4.1.1.8';
    private const OID_LLDP_REM_SYS_NAME = '1.0.8802.1.1.2.1.4.1.1.9';
    private const OID_LLDP_REM_SYS_DESC = '1.0.8802.1.1.2.1.4.1.1.10';
    private const OID_LLDP_REM_SYS_CAP_SUPPORTED = '1.0.8802.1.1.2.1.4.1.1.11';
    private const OID_LLDP_REM_SYS_CAP_ENABLED = '1.0.8802.1.1.2.1.4.1.1.12';
    
    private const OID_LLDP_REM_MAN_ADDR_TABLE = '1.0.8802.1.1.2.1.4.2.1';
    private const OID_LLDP_REM_MAN_ADDR = '1.0.8802.1.1.2.1.4.2.1.4';
    
    /**
     * Опциональный логгер
     */
    private readonly ?Logger $logger;
    
    /**
     * Опциональный загрузчик OID
     */
    private readonly ?SnmpOid $oidLoader;
    
    /**
     * Список начальных устройств для сканирования
     * @var array<int, string>
     */
    private readonly array $seedDevices;
    
    /**
     * Параметры SNMP подключения
     * @var array<string, mixed>
     */
    private readonly array $snmpConfig;
    
    /**
     * Обнаруженные устройства (chassis_id => device_data)
     * @var array<string, array<string, mixed>>
     */
    private array $discoveredDevices = [];
    
    /**
     * Обнаруженные связи между устройствами
     * @var array<int, array<string, mixed>>
     */
    private array $discoveredLinks = [];
    
    /**
     * Очередь устройств для сканирования
     * @var array<int, string>
     */
    private array $scanQueue = [];
    
    /**
     * Список просканированных хостов (для избежания повторного сканирования)
     * @var array<string, bool>
     */
    private array $scannedHosts = [];
    
    /**
     * Таймаут подключения к устройству в секундах
     */
    private readonly int $connectionTimeout;
    
    /**
     * Максимальное количество устройств для сканирования
     */
    private readonly int $maxDevices;
    
    /**
     * Конструктор класса с конфигурацией
     * 
     * @param array{
     *     seed_devices: array<int, string>,
     *     snmp: array<string, mixed>,
     *     connection_timeout?: int,
     *     max_devices?: int
     * } $config Конфигурация коллектора
     * @param Logger|null $logger Логгер для записи операций
     * @param SnmpOid|null $oidLoader Загрузчик OID конфигураций
     * 
     * @throws LldpCollectorException Если конфигурация некорректна
     */
    public function __construct(array $config, ?Logger $logger = null, ?SnmpOid $oidLoader = null)
    {
        $this->logger = $logger;
        $this->oidLoader = $oidLoader;
        
        $this->validateConfig($config);
        
        $this->seedDevices = $config['seed_devices'];
        $this->snmpConfig = $config['snmp'];
        $this->connectionTimeout = (int)($config['connection_timeout'] ?? 5);
        $this->maxDevices = (int)($config['max_devices'] ?? 1000);
        
        $this->log('info', 'LldpCollector инициализирован', [
            'seed_devices' => $this->seedDevices,
            'max_devices' => $this->maxDevices,
        ]);
    }
    
    /**
     * Валидирует конфигурацию коллектора
     * 
     * @param array<string, mixed> $config Параметры конфигурации
     * @throws LldpCollectorException Если конфигурация некорректна
     */
    private function validateConfig(array $config): void
    {
        if (!isset($config['seed_devices']) || !is_array($config['seed_devices'])) {
            throw new LldpCollectorException('Не указаны начальные устройства (seed_devices)');
        }
        
        if (empty($config['seed_devices'])) {
            throw new LldpCollectorException('Список начальных устройств (seed_devices) не может быть пустым');
        }
        
        if (!isset($config['snmp']) || !is_array($config['snmp'])) {
            throw new LldpCollectorException('Не указана конфигурация SNMP');
        }
        
        if (!isset($config['snmp']['community'])) {
            throw new LldpCollectorException('Не указан SNMP community string');
        }
    }
    
    /**
     * Запускает процесс обнаружения топологии
     * 
     * @return array{devices: array<string, array<string, mixed>>, links: array<int, array<string, mixed>>}
     * @throws LldpCollectorException При критических ошибках
     */
    public function discoverTopology(): array
    {
        $this->log('info', 'Начало обнаружения топологии');
        
        $startTime = microtime(true);
        
        // Добавляем начальные устройства в очередь
        foreach ($this->seedDevices as $host) {
            $this->scanQueue[] = $host;
        }
        
        // Обрабатываем очередь
        while (!empty($this->scanQueue) && count($this->discoveredDevices) < $this->maxDevices) {
            $host = array_shift($this->scanQueue);
            
            // Пропускаем уже просканированные хосты
            if (isset($this->scannedHosts[$host])) {
                continue;
            }
            
            $this->log('debug', 'Сканирование устройства', ['host' => $host]);
            
            try {
                $this->collectFromDevice($host);
                $this->scannedHosts[$host] = true;
            } catch (\Exception $e) {
                $this->log('warning', 'Ошибка при сканировании устройства', [
                    'host' => $host,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        $duration = round(microtime(true) - $startTime, 2);
        
        $this->log('info', 'Обнаружение топологии завершено', [
            'devices_found' => count($this->discoveredDevices),
            'links_found' => count($this->discoveredLinks),
            'duration_seconds' => $duration,
        ]);
        
        return [
            'devices' => $this->discoveredDevices,
            'links' => $this->discoveredLinks,
        ];
    }
    
    /**
     * Собирает LLDP данные с одного устройства
     * 
     * @param string $host IP адрес или hostname устройства
     * @throws LldpCollectorException При ошибках сбора данных
     */
    private function collectFromDevice(string $host): void
    {
        // Создаем SNMP подключение
        $snmpConfig = array_merge($this->snmpConfig, ['host' => $host]);
        
        try {
            $snmp = new Snmp($snmpConfig, $this->logger, $this->oidLoader);
        } catch (SnmpException $e) {
            throw new LldpCollectorException(
                "Не удалось подключиться к устройству {$host}: " . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
        
        // Собираем локальную информацию об устройстве
        $localDevice = $this->collectLocalDeviceInfo($snmp, $host);
        
        if ($localDevice === null) {
            $this->log('warning', 'Не удалось получить локальную информацию об устройстве', ['host' => $host]);
            return;
        }
        
        $chassisId = $localDevice['chassis_id'];
        
        // Сохраняем устройство если еще не было обнаружено
        if (!isset($this->discoveredDevices[$chassisId])) {
            $this->discoveredDevices[$chassisId] = $localDevice;
            
            $this->log('info', 'Обнаружено новое устройство', [
                'chassis_id' => $chassisId,
                'sys_name' => $localDevice['sys_name'],
                'management_ip' => $localDevice['management_ip'],
            ]);
        }
        
        // Собираем информацию о соседях
        $this->collectNeighbors($snmp, $chassisId, $host);
        
        $snmp->close();
    }
    
    /**
     * Собирает локальную информацию об устройстве
     * 
     * @param Snmp $snmp SNMP подключение
     * @param string $host IP адрес устройства
     * @return array<string, mixed>|null Информация об устройстве или null при ошибке
     */
    private function collectLocalDeviceInfo(Snmp $snmp, string $host): ?array
    {
        try {
            // Получаем базовую информацию
            $chassisId = $snmp->get(self::OID_LLDP_LOC_CHASSIS_ID);
            $sysName = $snmp->get(self::OID_LLDP_LOC_SYS_NAME);
            $sysDesc = $snmp->get(self::OID_LLDP_LOC_SYS_DESC);
            $capSupported = $snmp->get(self::OID_LLDP_LOC_SYS_CAP_SUPPORTED);
            $capEnabled = $snmp->get(self::OID_LLDP_LOC_SYS_CAP_ENABLED);
            
            if ($chassisId === false || $chassisId === '') {
                return null;
            }
            
            // Форматируем chassis ID
            $chassisId = $this->formatChassisId($chassisId);
            
            // Собираем локальные порты
            $localPorts = $this->collectLocalPorts($snmp);
            
            // Разбираем capabilities
            $capabilities = $this->parseCapabilities($capEnabled !== false ? $capEnabled : $capSupported);
            
            return [
                'chassis_id' => $chassisId,
                'sys_name' => $sysName !== false ? (string)$sysName : 'Unknown',
                'sys_desc' => $sysDesc !== false ? (string)$sysDesc : '',
                'management_ip' => $host,
                'capabilities' => $capabilities,
                'local_ports' => $localPorts,
                'discovered_at' => date('Y-m-d H:i:s'),
            ];
            
        } catch (SnmpException $e) {
            $this->log('error', 'Ошибка при сборе локальной информации', [
                'host' => $host,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
    
    /**
     * Собирает информацию о локальных портах устройства
     * 
     * @param Snmp $snmp SNMP подключение
     * @return array<int, array<string, mixed>> Массив портов
     */
    private function collectLocalPorts(Snmp $snmp): array
    {
        $ports = [];
        
        try {
            $portIds = $snmp->walk(self::OID_LLDP_LOC_PORT_ID);
            $portDescs = $snmp->walk(self::OID_LLDP_LOC_PORT_DESC);
            
            if (!is_array($portIds)) {
                return [];
            }
            
            foreach ($portIds as $index => $portId) {
                $ports[] = [
                    'port_index' => (int)$index,
                    'port_id' => (string)$portId,
                    'port_desc' => isset($portDescs[$index]) ? (string)$portDescs[$index] : '',
                ];
            }
            
        } catch (SnmpException $e) {
            $this->log('debug', 'Не удалось получить локальные порты', [
                'error' => $e->getMessage(),
            ]);
        }
        
        return $ports;
    }
    
    /**
     * Собирает информацию о соседях устройства
     * 
     * @param Snmp $snmp SNMP подключение
     * @param string $sourceChassisId Chassis ID источника
     * @param string $sourceHost IP источника
     */
    private function collectNeighbors(Snmp $snmp, string $sourceChassisId, string $sourceHost): void
    {
        try {
            // Получаем таблицу удаленных устройств
            $remChassisIds = $snmp->walk(self::OID_LLDP_REM_CHASSIS_ID);
            $remSysNames = $snmp->walk(self::OID_LLDP_REM_SYS_NAME);
            $remSysDescs = $snmp->walk(self::OID_LLDP_REM_SYS_DESC);
            $remPortIds = $snmp->walk(self::OID_LLDP_REM_PORT_ID);
            $remPortDescs = $snmp->walk(self::OID_LLDP_REM_PORT_DESC);
            $remCapEnabled = $snmp->walk(self::OID_LLDP_REM_SYS_CAP_ENABLED);
            
            if (!is_array($remChassisIds) || empty($remChassisIds)) {
                $this->log('debug', 'Соседи не найдены', ['host' => $sourceHost]);
                return;
            }
            
            // Получаем IP адреса управления соседей
            $remManAddrs = $this->collectRemoteManagementAddresses($snmp);
            
            foreach ($remChassisIds as $index => $chassisId) {
                $chassisId = $this->formatChassisId($chassisId);
                
                // Парсим индекс для получения локального порта
                // Формат индекса: timeMark.localPortNum.remIndex
                $indexParts = explode('.', (string)$index);
                $localPortIndex = isset($indexParts[1]) ? (int)$indexParts[1] : 0;
                
                $sysName = isset($remSysNames[$index]) ? (string)$remSysNames[$index] : 'Unknown';
                $sysDesc = isset($remSysDescs[$index]) ? (string)$remSysDescs[$index] : '';
                $remotePortId = isset($remPortIds[$index]) ? (string)$remPortIds[$index] : '';
                $remotePortDesc = isset($remPortDescs[$index]) ? (string)$remPortDescs[$index] : '';
                $capabilities = isset($remCapEnabled[$index]) ? $this->parseCapabilities($remCapEnabled[$index]) : [];
                
                // Получаем IP адрес управления
                $managementIp = $remManAddrs[$chassisId] ?? null;
                
                // Создаем запись о соседе если еще не обнаружен
                if (!isset($this->discoveredDevices[$chassisId])) {
                    $this->discoveredDevices[$chassisId] = [
                        'chassis_id' => $chassisId,
                        'sys_name' => $sysName,
                        'sys_desc' => $sysDesc,
                        'management_ip' => $managementIp,
                        'capabilities' => $capabilities,
                        'local_ports' => [],
                        'discovered_at' => date('Y-m-d H:i:s'),
                    ];
                    
                    $this->log('info', 'Обнаружен сосед', [
                        'chassis_id' => $chassisId,
                        'sys_name' => $sysName,
                    ]);
                    
                    // Добавляем соседа в очередь для сканирования если есть IP
                    if ($managementIp !== null && !isset($this->scannedHosts[$managementIp])) {
                        $this->scanQueue[] = $managementIp;
                    }
                }
                
                // Создаем связь между устройствами
                $link = [
                    'source_chassis_id' => $sourceChassisId,
                    'source_port_index' => $localPortIndex,
                    'target_chassis_id' => $chassisId,
                    'target_port_id' => $remotePortId,
                    'target_port_desc' => $remotePortDesc,
                    'discovered_at' => date('Y-m-d H:i:s'),
                ];
                
                // Проверяем что такая связь еще не добавлена
                if (!$this->linkExists($link)) {
                    $this->discoveredLinks[] = $link;
                    
                    $this->log('debug', 'Обнаружена связь', [
                        'from' => $sourceChassisId,
                        'to' => $chassisId,
                    ]);
                }
            }
            
        } catch (SnmpException $e) {
            $this->log('error', 'Ошибка при сборе информации о соседях', [
                'host' => $sourceHost,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Собирает IP адреса управления удаленных устройств
     * 
     * @param Snmp $snmp SNMP подключение
     * @return array<string, string> Массив chassis_id => IP
     */
    private function collectRemoteManagementAddresses(Snmp $snmp): array
    {
        $addresses = [];
        
        try {
            $manAddrs = $snmp->walk(self::OID_LLDP_REM_MAN_ADDR);
            
            if (!is_array($manAddrs)) {
                return [];
            }
            
            foreach ($manAddrs as $index => $addr) {
                // Парсим индекс и преобразуем адрес
                $ip = $this->parseIpAddress($addr);
                
                if ($ip !== null) {
                    // Индекс содержит информацию о chassis ID, но проще связать через sys_name
                    // Сохраняем для последующего использования
                    $addresses[$index] = $ip;
                }
            }
            
        } catch (SnmpException $e) {
            $this->log('debug', 'Не удалось получить адреса управления', [
                'error' => $e->getMessage(),
            ]);
        }
        
        return $addresses;
    }
    
    /**
     * Проверяет существование связи в списке обнаруженных
     * 
     * @param array<string, mixed> $link Данные связи
     * @return bool True если связь уже существует
     */
    private function linkExists(array $link): bool
    {
        foreach ($this->discoveredLinks as $existingLink) {
            if ($existingLink['source_chassis_id'] === $link['source_chassis_id'] &&
                $existingLink['target_chassis_id'] === $link['target_chassis_id'] &&
                $existingLink['source_port_index'] === $link['source_port_index']) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Форматирует Chassis ID в удобочитаемый формат
     * 
     * @param string $chassisId Исходный chassis ID
     * @return string Отформатированный chassis ID
     */
    private function formatChassisId(string $chassisId): string
    {
        // Если это hex строка, преобразуем в MAC формат
        if (ctype_xdigit(str_replace([' ', ':', '-'], '', $chassisId))) {
            $chassisId = str_replace([' ', ':', '-'], '', $chassisId);
            if (strlen($chassisId) === 12) {
                return strtoupper(implode(':', str_split($chassisId, 2)));
            }
        }
        
        return $chassisId;
    }
    
    /**
     * Парсит capabilities битовую маску
     * 
     * @param string|int $capBits Битовая маска capabilities
     * @return array<int, string> Массив capabilities
     */
    private function parseCapabilities(string|int $capBits): array
    {
        $capabilities = [];
        $capValue = is_numeric($capBits) ? (int)$capBits : hexdec(ltrim((string)$capBits, '0x'));
        
        $capMap = [
            0x01 => 'other',
            0x02 => 'repeater',
            0x04 => 'bridge',
            0x08 => 'wlan-access-point',
            0x10 => 'router',
            0x20 => 'telephone',
            0x40 => 'docsis-cable-device',
            0x80 => 'station-only',
        ];
        
        foreach ($capMap as $bit => $capability) {
            if (($capValue & $bit) === $bit) {
                $capabilities[] = $capability;
            }
        }
        
        return $capabilities;
    }
    
    /**
     * Парсит IP адрес из SNMP значения
     * 
     * @param string $value SNMP значение
     * @return string|null IP адрес или null
     */
    private function parseIpAddress(string $value): ?string
    {
        // Пытаемся распарсить как IPv4
        if (preg_match('/(\d+)\.(\d+)\.(\d+)\.(\d+)/', $value, $matches)) {
            return "{$matches[1]}.{$matches[2]}.{$matches[3]}.{$matches[4]}";
        }
        
        // Если это hex строка, пытаемся преобразовать
        if (preg_match('/^[0-9a-fA-F\s]+$/', $value)) {
            $bytes = array_map('hexdec', str_split(str_replace(' ', '', $value), 2));
            if (count($bytes) === 4) {
                return implode('.', $bytes);
            }
        }
        
        return null;
    }
    
    /**
     * Получает список обнаруженных устройств
     * 
     * @return array<string, array<string, mixed>> Массив устройств
     */
    public function getDiscoveredDevices(): array
    {
        return $this->discoveredDevices;
    }
    
    /**
     * Получает список обнаруженных связей
     * 
     * @return array<int, array<string, mixed>> Массив связей
     */
    public function getDiscoveredLinks(): array
    {
        return $this->discoveredLinks;
    }
    
    /**
     * Получает статистику сбора
     * 
     * @return array<string, mixed> Статистика
     */
    public function getStats(): array
    {
        return [
            'devices_discovered' => count($this->discoveredDevices),
            'links_discovered' => count($this->discoveredLinks),
            'hosts_scanned' => count($this->scannedHosts),
            'scan_queue_size' => count($this->scanQueue),
        ];
    }
    
    /**
     * Сбрасывает состояние коллектора
     */
    public function reset(): void
    {
        $this->discoveredDevices = [];
        $this->discoveredLinks = [];
        $this->scanQueue = [];
        $this->scannedHosts = [];
        
        $this->log('info', 'Состояние коллектора сброшено');
    }
    
    /**
     * Логирует сообщение через Logger
     * 
     * @param string $level Уровень логирования
     * @param string $message Сообщение
     * @param array<string, mixed> $context Контекст
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger === null) {
            return;
        }
        
        try {
            $this->logger->log($level, '[LldpCollector] ' . $message, $context);
        } catch (\Exception $e) {
            error_log('Ошибка логирования LldpCollector: ' . $e->getMessage());
        }
    }
}

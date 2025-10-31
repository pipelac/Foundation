<?php

declare(strict_types=1);

namespace App\Component\Netmap;

use App\Component\MySQL;
use App\Component\Logger;
use App\Component\Exception\Netmap\TopologySaverException;
use App\Component\Exception\MySQLException;

/**
 * Класс для сохранения топологии сети в базу данных
 * 
 * Возможности:
 * - Сохранение устройств и их параметров
 * - Сохранение портов устройств
 * - Сохранение связей между устройствами
 * - Отслеживание изменений топологии
 * - Управление активностью устройств
 * - История сканирований
 * 
 * Системные требования:
 * - PHP 8.1 или выше
 * - MySQL 5.7+ или MySQL 8.0+
 */
class TopologySaver
{
    /**
     * MySQL подключение
     */
    private readonly MySQL $mysql;
    
    /**
     * Опциональный логгер
     */
    private readonly ?Logger $logger;
    
    /**
     * Префикс таблиц
     */
    private readonly string $tablePrefix;
    
    /**
     * ID текущего сканирования
     */
    private ?int $currentScanId = null;
    
    /**
     * Конструктор класса
     * 
     * @param MySQL $mysql MySQL подключение
     * @param Logger|null $logger Логгер для записи операций
     * @param string $tablePrefix Префикс таблиц (по умолчанию 'netmap_')
     */
    public function __construct(MySQL $mysql, ?Logger $logger = null, string $tablePrefix = 'netmap_')
    {
        $this->mysql = $mysql;
        $this->logger = $logger;
        $this->tablePrefix = $tablePrefix;
        
        $this->log('info', 'TopologySaver инициализирован');
    }
    
    /**
     * Создает таблицы БД если их нет
     * 
     * @throws TopologySaverException При ошибках создания таблиц
     */
    public function createTables(): void
    {
        $this->log('info', 'Создание таблиц БД');
        
        try {
            // Таблица устройств
            $this->mysql->execute("
                CREATE TABLE IF NOT EXISTS {$this->tablePrefix}devices (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    chassis_id VARCHAR(255) NOT NULL,
                    sys_name VARCHAR(255) NOT NULL,
                    sys_desc TEXT,
                    management_ip VARCHAR(45),
                    capabilities JSON,
                    node_level INT DEFAULT -1,
                    node_type VARCHAR(50) DEFAULT 'unknown',
                    is_root BOOLEAN DEFAULT FALSE,
                    is_active BOOLEAN DEFAULT TRUE,
                    discovered_at DATETIME NOT NULL,
                    last_seen_at DATETIME NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_chassis_id (chassis_id),
                    KEY idx_management_ip (management_ip),
                    KEY idx_sys_name (sys_name),
                    KEY idx_is_active (is_active),
                    KEY idx_node_type (node_type)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            // Таблица портов
            $this->mysql->execute("
                CREATE TABLE IF NOT EXISTS {$this->tablePrefix}device_ports (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    device_id INT UNSIGNED NOT NULL,
                    port_index INT NOT NULL,
                    port_id VARCHAR(255),
                    port_desc VARCHAR(255),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_device_port (device_id, port_index),
                    KEY idx_device_id (device_id),
                    FOREIGN KEY (device_id) REFERENCES {$this->tablePrefix}devices(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            // Таблица связей
            $this->mysql->execute("
                CREATE TABLE IF NOT EXISTS {$this->tablePrefix}device_links (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    source_device_id INT UNSIGNED NOT NULL,
                    source_port_id INT UNSIGNED,
                    target_device_id INT UNSIGNED NOT NULL,
                    target_port_id INT UNSIGNED,
                    link_type VARCHAR(50) DEFAULT 'lldp',
                    is_active BOOLEAN DEFAULT TRUE,
                    discovered_at DATETIME NOT NULL,
                    last_seen_at DATETIME NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY idx_source_device (source_device_id),
                    KEY idx_target_device (target_device_id),
                    KEY idx_is_active (is_active),
                    FOREIGN KEY (source_device_id) REFERENCES {$this->tablePrefix}devices(id) ON DELETE CASCADE,
                    FOREIGN KEY (target_device_id) REFERENCES {$this->tablePrefix}devices(id) ON DELETE CASCADE,
                    FOREIGN KEY (source_port_id) REFERENCES {$this->tablePrefix}device_ports(id) ON DELETE SET NULL,
                    FOREIGN KEY (target_port_id) REFERENCES {$this->tablePrefix}device_ports(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            // Таблица сканирований
            $this->mysql->execute("
                CREATE TABLE IF NOT EXISTS {$this->tablePrefix}topology_scans (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    scan_started_at DATETIME NOT NULL,
                    scan_finished_at DATETIME,
                    devices_discovered INT DEFAULT 0,
                    links_discovered INT DEFAULT 0,
                    status ENUM('running', 'completed', 'failed') DEFAULT 'running',
                    error_message TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_status (status),
                    KEY idx_scan_started (scan_started_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            $this->log('info', 'Таблицы БД успешно созданы');
            
        } catch (MySQLException $e) {
            $this->log('error', 'Ошибка при создании таблиц', ['error' => $e->getMessage()]);
            throw new TopologySaverException(
                'Не удалось создать таблицы БД: ' . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }
    
    /**
     * Начинает новое сканирование
     * 
     * @return int ID сканирования
     * @throws TopologySaverException При ошибках
     */
    public function startScan(): int
    {
        try {
            $this->mysql->insert($this->tablePrefix . 'topology_scans', [
                'scan_started_at' => date('Y-m-d H:i:s'),
                'status' => 'running',
            ]);
            
            $scanId = $this->mysql->lastInsertId();
            $this->currentScanId = $scanId;
            
            $this->log('info', 'Начато новое сканирование', ['scan_id' => $scanId]);
            
            return $scanId;
            
        } catch (MySQLException $e) {
            throw new TopologySaverException(
                'Не удалось начать сканирование: ' . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }
    
    /**
     * Завершает сканирование
     * 
     * @param int $scanId ID сканирования
     * @param bool $success Успешно ли завершено
     * @param string|null $errorMessage Сообщение об ошибке
     * @throws TopologySaverException При ошибках
     */
    public function finishScan(int $scanId, bool $success = true, ?string $errorMessage = null): void
    {
        try {
            // Подсчитываем количество устройств и связей
            $devicesCount = $this->mysql->fetchOne(
                "SELECT COUNT(*) FROM {$this->tablePrefix}devices WHERE is_active = 1"
            );
            
            $linksCount = $this->mysql->fetchOne(
                "SELECT COUNT(*) FROM {$this->tablePrefix}device_links WHERE is_active = 1"
            );
            
            $this->mysql->update(
                $this->tablePrefix . 'topology_scans',
                [
                    'scan_finished_at' => date('Y-m-d H:i:s'),
                    'devices_discovered' => (int)$devicesCount,
                    'links_discovered' => (int)$linksCount,
                    'status' => $success ? 'completed' : 'failed',
                    'error_message' => $errorMessage,
                ],
                ['id' => $scanId]
            );
            
            $this->log('info', 'Сканирование завершено', [
                'scan_id' => $scanId,
                'success' => $success,
                'devices' => $devicesCount,
                'links' => $linksCount,
            ]);
            
            $this->currentScanId = null;
            
        } catch (MySQLException $e) {
            throw new TopologySaverException(
                'Не удалось завершить сканирование: ' . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }
    
    /**
     * Сохраняет топологию в БД
     * 
     * @param array{nodes: array<string, array<string, mixed>>, edges: array<int, array<string, mixed>>} $topology
     * @return array{devices_added: int, devices_updated: int, links_added: int, links_updated: int}
     * @throws TopologySaverException При ошибках
     */
    public function saveTopology(array $topology): array
    {
        $this->log('info', 'Начало сохранения топологии');
        
        $startTime = microtime(true);
        $stats = [
            'devices_added' => 0,
            'devices_updated' => 0,
            'links_added' => 0,
            'links_updated' => 0,
        ];
        
        try {
            $this->mysql->beginTransaction();
            
            // Помечаем все устройства и связи как неактивные
            $this->mysql->execute("UPDATE {$this->tablePrefix}devices SET is_active = FALSE");
            $this->mysql->execute("UPDATE {$this->tablePrefix}device_links SET is_active = FALSE");
            
            // Сохраняем устройства
            $deviceStats = $this->saveDevices($topology['nodes']);
            $stats['devices_added'] = $deviceStats['added'];
            $stats['devices_updated'] = $deviceStats['updated'];
            
            // Сохраняем связи
            $linkStats = $this->saveLinks($topology['edges']);
            $stats['links_added'] = $linkStats['added'];
            $stats['links_updated'] = $linkStats['updated'];
            
            $this->mysql->commit();
            
            $duration = round(microtime(true) - $startTime, 2);
            
            $this->log('info', 'Топология успешно сохранена', array_merge($stats, [
                'duration_seconds' => $duration,
            ]));
            
            return $stats;
            
        } catch (MySQLException $e) {
            $this->mysql->rollback();
            $this->log('error', 'Ошибка при сохранении топологии', ['error' => $e->getMessage()]);
            throw new TopologySaverException(
                'Не удалось сохранить топологию: ' . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }
    
    /**
     * Сохраняет устройства в БД
     * 
     * @param array<string, array<string, mixed>> $nodes Узлы графа
     * @return array{added: int, updated: int} Статистика
     */
    private function saveDevices(array $nodes): array
    {
        $added = 0;
        $updated = 0;
        
        foreach ($nodes as $chassisId => $node) {
            // Проверяем существование устройства
            $existingDevice = $this->mysql->fetchRow(
                "SELECT id FROM {$this->tablePrefix}devices WHERE chassis_id = ?",
                [$chassisId]
            );
            
            $deviceData = [
                'chassis_id' => $chassisId,
                'sys_name' => $node['sys_name'] ?? 'Unknown',
                'sys_desc' => $node['sys_desc'] ?? '',
                'management_ip' => $node['management_ip'] ?? null,
                'capabilities' => json_encode($node['capabilities'] ?? []),
                'node_level' => $node['level'] ?? -1,
                'node_type' => $node['node_type'] ?? 'unknown',
                'is_root' => $node['is_root'] ?? false,
                'is_active' => true,
                'last_seen_at' => date('Y-m-d H:i:s'),
            ];
            
            if ($existingDevice === null) {
                // Добавляем новое устройство
                $deviceData['discovered_at'] = $node['discovered_at'] ?? date('Y-m-d H:i:s');
                $this->mysql->insert($this->tablePrefix . 'devices', $deviceData);
                $deviceId = $this->mysql->lastInsertId();
                $added++;
            } else {
                // Обновляем существующее
                $deviceId = (int)$existingDevice['id'];
                $this->mysql->update(
                    $this->tablePrefix . 'devices',
                    $deviceData,
                    ['id' => $deviceId]
                );
                $updated++;
            }
            
            // Сохраняем порты устройства
            if (!empty($node['local_ports'])) {
                $this->savePorts($deviceId, $node['local_ports']);
            }
        }
        
        return ['added' => $added, 'updated' => $updated];
    }
    
    /**
     * Сохраняет порты устройства
     * 
     * @param int $deviceId ID устройства
     * @param array<int, array<string, mixed>> $ports Порты
     */
    private function savePorts(int $deviceId, array $ports): void
    {
        foreach ($ports as $port) {
            $portIndex = $port['port_index'] ?? 0;
            
            // Проверяем существование порта
            $existingPort = $this->mysql->fetchRow(
                "SELECT id FROM {$this->tablePrefix}device_ports WHERE device_id = ? AND port_index = ?",
                [$deviceId, $portIndex]
            );
            
            $portData = [
                'device_id' => $deviceId,
                'port_index' => $portIndex,
                'port_id' => $port['port_id'] ?? null,
                'port_desc' => $port['port_desc'] ?? '',
            ];
            
            if ($existingPort === null) {
                $this->mysql->insert($this->tablePrefix . 'device_ports', $portData);
            } else {
                $this->mysql->update(
                    $this->tablePrefix . 'device_ports',
                    $portData,
                    ['id' => (int)$existingPort['id']]
                );
            }
        }
    }
    
    /**
     * Сохраняет связи между устройствами
     * 
     * @param array<int, array<string, mixed>> $edges Ребра графа
     * @return array{added: int, updated: int} Статистика
     */
    private function saveLinks(array $edges): array
    {
        $added = 0;
        $updated = 0;
        
        foreach ($edges as $edge) {
            $sourceChassisId = $edge['source_chassis_id'];
            $targetChassisId = $edge['target_chassis_id'];
            $sourcePortIndex = $edge['source_port_index'] ?? null;
            
            // Получаем ID устройств
            $sourceDevice = $this->mysql->fetchRow(
                "SELECT id FROM {$this->tablePrefix}devices WHERE chassis_id = ?",
                [$sourceChassisId]
            );
            
            $targetDevice = $this->mysql->fetchRow(
                "SELECT id FROM {$this->tablePrefix}devices WHERE chassis_id = ?",
                [$targetChassisId]
            );
            
            if ($sourceDevice === null || $targetDevice === null) {
                continue;
            }
            
            $sourceDeviceId = (int)$sourceDevice['id'];
            $targetDeviceId = (int)$targetDevice['id'];
            
            // Получаем ID портов
            $sourcePortId = null;
            if ($sourcePortIndex !== null) {
                $sourcePort = $this->mysql->fetchRow(
                    "SELECT id FROM {$this->tablePrefix}device_ports WHERE device_id = ? AND port_index = ?",
                    [$sourceDeviceId, $sourcePortIndex]
                );
                $sourcePortId = $sourcePort !== null ? (int)$sourcePort['id'] : null;
            }
            
            // Проверяем существование связи
            $existingLink = $this->mysql->fetchRow(
                "SELECT id FROM {$this->tablePrefix}device_links 
                 WHERE source_device_id = ? AND target_device_id = ? AND source_port_id <=> ?",
                [$sourceDeviceId, $targetDeviceId, $sourcePortId]
            );
            
            $linkData = [
                'source_device_id' => $sourceDeviceId,
                'source_port_id' => $sourcePortId,
                'target_device_id' => $targetDeviceId,
                'target_port_id' => null,
                'link_type' => 'lldp',
                'is_active' => true,
                'last_seen_at' => date('Y-m-d H:i:s'),
            ];
            
            if ($existingLink === null) {
                // Добавляем новую связь
                $linkData['discovered_at'] = $edge['discovered_at'] ?? date('Y-m-d H:i:s');
                $this->mysql->insert($this->tablePrefix . 'device_links', $linkData);
                $added++;
            } else {
                // Обновляем существующую
                $this->mysql->update(
                    $this->tablePrefix . 'device_links',
                    $linkData,
                    ['id' => (int)$existingLink['id']]
                );
                $updated++;
            }
        }
        
        return ['added' => $added, 'updated' => $updated];
    }
    
    /**
     * Получает устройство по chassis ID
     * 
     * @param string $chassisId Chassis ID
     * @return array<string, mixed>|null Данные устройства или null
     */
    public function getDeviceByChassisId(string $chassisId): ?array
    {
        try {
            $device = $this->mysql->fetchRow(
                "SELECT * FROM {$this->tablePrefix}devices WHERE chassis_id = ?",
                [$chassisId]
            );
            
            if ($device !== null && isset($device['capabilities'])) {
                $device['capabilities'] = json_decode((string)$device['capabilities'], true);
            }
            
            return $device;
            
        } catch (MySQLException $e) {
            $this->log('error', 'Ошибка при получении устройства', [
                'chassis_id' => $chassisId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
    
    /**
     * Получает все активные устройства
     * 
     * @return array<int, array<string, mixed>> Список устройств
     */
    public function getActiveDevices(): array
    {
        try {
            $devices = $this->mysql->fetchAll(
                "SELECT * FROM {$this->tablePrefix}devices WHERE is_active = TRUE ORDER BY sys_name"
            );
            
            foreach ($devices as &$device) {
                if (isset($device['capabilities'])) {
                    $device['capabilities'] = json_decode((string)$device['capabilities'], true);
                }
            }
            
            return $devices;
            
        } catch (MySQLException $e) {
            $this->log('error', 'Ошибка при получении активных устройств', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
    
    /**
     * Получает связи устройства
     * 
     * @param int $deviceId ID устройства
     * @return array<int, array<string, mixed>> Список связей
     */
    public function getDeviceLinks(int $deviceId): array
    {
        try {
            return $this->mysql->fetchAll(
                "SELECT l.*, 
                        sd.sys_name as source_name, 
                        td.sys_name as target_name
                 FROM {$this->tablePrefix}device_links l
                 JOIN {$this->tablePrefix}devices sd ON l.source_device_id = sd.id
                 JOIN {$this->tablePrefix}devices td ON l.target_device_id = td.id
                 WHERE (l.source_device_id = ? OR l.target_device_id = ?) AND l.is_active = TRUE",
                [$deviceId, $deviceId]
            );
            
        } catch (MySQLException $e) {
            $this->log('error', 'Ошибка при получении связей устройства', [
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
    
    /**
     * Удаляет неактивные устройства старше заданного количества дней
     * 
     * @param int $daysOld Количество дней
     * @return int Количество удаленных устройств
     */
    public function cleanupInactiveDevices(int $daysOld = 30): int
    {
        try {
            $date = date('Y-m-d H:i:s', strtotime("-{$daysOld} days"));
            
            $count = $this->mysql->fetchOne(
                "SELECT COUNT(*) FROM {$this->tablePrefix}devices 
                 WHERE is_active = FALSE AND last_seen_at < ?",
                [$date]
            );
            
            $this->mysql->execute(
                "DELETE FROM {$this->tablePrefix}devices 
                 WHERE is_active = FALSE AND last_seen_at < ?",
                [$date]
            );
            
            $this->log('info', 'Очищены неактивные устройства', [
                'days_old' => $daysOld,
                'count' => $count,
            ]);
            
            return (int)$count;
            
        } catch (MySQLException $e) {
            $this->log('error', 'Ошибка при очистке неактивных устройств', [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }
    
    /**
     * Получает статистику топологии из БД
     * 
     * @return array<string, mixed> Статистика
     */
    public function getTopologyStats(): array
    {
        try {
            $stats = [
                'total_devices' => 0,
                'active_devices' => 0,
                'total_links' => 0,
                'active_links' => 0,
                'devices_by_type' => [],
                'last_scan' => null,
            ];
            
            $stats['total_devices'] = (int)$this->mysql->fetchOne(
                "SELECT COUNT(*) FROM {$this->tablePrefix}devices"
            );
            
            $stats['active_devices'] = (int)$this->mysql->fetchOne(
                "SELECT COUNT(*) FROM {$this->tablePrefix}devices WHERE is_active = TRUE"
            );
            
            $stats['total_links'] = (int)$this->mysql->fetchOne(
                "SELECT COUNT(*) FROM {$this->tablePrefix}device_links"
            );
            
            $stats['active_links'] = (int)$this->mysql->fetchOne(
                "SELECT COUNT(*) FROM {$this->tablePrefix}device_links WHERE is_active = TRUE"
            );
            
            $devicesByType = $this->mysql->fetchAll(
                "SELECT node_type, COUNT(*) as count 
                 FROM {$this->tablePrefix}devices 
                 WHERE is_active = TRUE 
                 GROUP BY node_type"
            );
            
            foreach ($devicesByType as $row) {
                $stats['devices_by_type'][$row['node_type']] = (int)$row['count'];
            }
            
            $lastScan = $this->mysql->fetchRow(
                "SELECT * FROM {$this->tablePrefix}topology_scans 
                 WHERE status = 'completed' 
                 ORDER BY scan_finished_at DESC 
                 LIMIT 1"
            );
            
            $stats['last_scan'] = $lastScan;
            
            return $stats;
            
        } catch (MySQLException $e) {
            $this->log('error', 'Ошибка при получении статистики', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
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
            $this->logger->log($level, '[TopologySaver] ' . $message, $context);
        } catch (\Exception $e) {
            error_log('Ошибка логирования TopologySaver: ' . $e->getMessage());
        }
    }
}

-- Схема базы данных для Netmap - системы сбора топологии сети
-- MySQL 5.7+ / MySQL 8.0+

-- Создание базы данных
CREATE DATABASE IF NOT EXISTS network_topology 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE network_topology;

-- Таблица устройств
CREATE TABLE IF NOT EXISTS netmap_devices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chassis_id VARCHAR(255) NOT NULL COMMENT 'Уникальный идентификатор устройства (MAC, Serial)',
    sys_name VARCHAR(255) NOT NULL COMMENT 'Hostname устройства',
    sys_desc TEXT COMMENT 'Описание системы (модель, версия ОС)',
    management_ip VARCHAR(45) COMMENT 'IP адрес для управления (IPv4/IPv6)',
    capabilities JSON COMMENT 'Возможности устройства (routing, switching, bridge)',
    node_level INT DEFAULT -1 COMMENT 'Уровень в иерархии топологии',
    node_type VARCHAR(50) DEFAULT 'unknown' COMMENT 'Тип узла (core, distribution, access)',
    is_root BOOLEAN DEFAULT FALSE COMMENT 'Корневой узел топологии',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Активное устройство',
    discovered_at DATETIME NOT NULL COMMENT 'Время первого обнаружения',
    last_seen_at DATETIME NOT NULL COMMENT 'Время последнего обнаружения',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_chassis_id (chassis_id),
    KEY idx_management_ip (management_ip),
    KEY idx_sys_name (sys_name),
    KEY idx_is_active (is_active),
    KEY idx_node_type (node_type),
    KEY idx_last_seen (last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Сетевые устройства обнаруженные через LLDP';

-- Таблица портов устройств
CREATE TABLE IF NOT EXISTS netmap_device_ports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id INT UNSIGNED NOT NULL COMMENT 'ID устройства',
    port_index INT NOT NULL COMMENT 'Индекс порта',
    port_id VARCHAR(255) COMMENT 'Идентификатор порта (eth0, GigabitEthernet1/0/1)',
    port_desc VARCHAR(255) COMMENT 'Описание порта',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_device_port (device_id, port_index),
    KEY idx_device_id (device_id),
    
    FOREIGN KEY (device_id) REFERENCES netmap_devices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Порты сетевых устройств';

-- Таблица связей между устройствами
CREATE TABLE IF NOT EXISTS netmap_device_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_device_id INT UNSIGNED NOT NULL COMMENT 'ID исходного устройства',
    source_port_id INT UNSIGNED COMMENT 'ID исходного порта',
    target_device_id INT UNSIGNED NOT NULL COMMENT 'ID целевого устройства',
    target_port_id INT UNSIGNED COMMENT 'ID целевого порта',
    link_type VARCHAR(50) DEFAULT 'lldp' COMMENT 'Тип связи',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Активная связь',
    discovered_at DATETIME NOT NULL COMMENT 'Время обнаружения связи',
    last_seen_at DATETIME NOT NULL COMMENT 'Время последнего обнаружения',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    KEY idx_source_device (source_device_id),
    KEY idx_target_device (target_device_id),
    KEY idx_is_active (is_active),
    KEY idx_last_seen (last_seen_at),
    
    FOREIGN KEY (source_device_id) REFERENCES netmap_devices(id) ON DELETE CASCADE,
    FOREIGN KEY (target_device_id) REFERENCES netmap_devices(id) ON DELETE CASCADE,
    FOREIGN KEY (source_port_id) REFERENCES netmap_device_ports(id) ON DELETE SET NULL,
    FOREIGN KEY (target_port_id) REFERENCES netmap_device_ports(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Связи между сетевыми устройствами';

-- Таблица сканирований топологии
CREATE TABLE IF NOT EXISTS netmap_topology_scans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scan_started_at DATETIME NOT NULL COMMENT 'Время начала сканирования',
    scan_finished_at DATETIME COMMENT 'Время завершения сканирования',
    devices_discovered INT DEFAULT 0 COMMENT 'Количество обнаруженных устройств',
    links_discovered INT DEFAULT 0 COMMENT 'Количество обнаруженных связей',
    status ENUM('running', 'completed', 'failed') DEFAULT 'running' COMMENT 'Статус сканирования',
    error_message TEXT COMMENT 'Сообщение об ошибке',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    KEY idx_status (status),
    KEY idx_scan_started (scan_started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='История сканирований топологии сети';

-- Полезные представления (views)

-- Активная топология
CREATE OR REPLACE VIEW v_active_topology AS
SELECT 
    d.id,
    d.chassis_id,
    d.sys_name,
    d.management_ip,
    d.node_type,
    d.node_level,
    d.is_root,
    JSON_LENGTH(d.capabilities) as capabilities_count,
    (SELECT COUNT(*) FROM netmap_device_links WHERE source_device_id = d.id AND is_active = TRUE) as outgoing_links,
    (SELECT COUNT(*) FROM netmap_device_links WHERE target_device_id = d.id AND is_active = TRUE) as incoming_links,
    d.last_seen_at
FROM netmap_devices d
WHERE d.is_active = TRUE
ORDER BY d.node_level, d.sys_name;

-- Последнее сканирование
CREATE OR REPLACE VIEW v_last_scan AS
SELECT 
    s.*,
    TIMESTAMPDIFF(SECOND, s.scan_started_at, s.scan_finished_at) as duration_seconds
FROM netmap_topology_scans s
WHERE s.id = (SELECT MAX(id) FROM netmap_topology_scans WHERE status = 'completed');

-- Статистика топологии
CREATE OR REPLACE VIEW v_topology_stats AS
SELECT 
    (SELECT COUNT(*) FROM netmap_devices WHERE is_active = TRUE) as active_devices,
    (SELECT COUNT(*) FROM netmap_devices WHERE is_active = FALSE) as inactive_devices,
    (SELECT COUNT(*) FROM netmap_device_links WHERE is_active = TRUE) as active_links,
    (SELECT COUNT(*) FROM netmap_device_links WHERE is_active = FALSE) as inactive_links,
    (SELECT COUNT(*) FROM netmap_devices WHERE is_root = TRUE AND is_active = TRUE) as root_devices,
    (SELECT scan_finished_at FROM netmap_topology_scans WHERE status = 'completed' ORDER BY id DESC LIMIT 1) as last_scan_time;

-- Полезные запросы для мониторинга

-- Топ устройств по количеству связей
-- SELECT 
--     d.sys_name,
--     d.management_ip,
--     d.node_type,
--     COUNT(DISTINCT l1.id) + COUNT(DISTINCT l2.id) as total_links
-- FROM netmap_devices d
-- LEFT JOIN netmap_device_links l1 ON d.id = l1.source_device_id AND l1.is_active = TRUE
-- LEFT JOIN netmap_device_links l2 ON d.id = l2.target_device_id AND l2.is_active = TRUE
-- WHERE d.is_active = TRUE
-- GROUP BY d.id
-- ORDER BY total_links DESC
-- LIMIT 10;

-- Недавно пропавшие устройства
-- SELECT 
--     sys_name,
--     management_ip,
--     node_type,
--     last_seen_at,
--     TIMESTAMPDIFF(DAY, last_seen_at, NOW()) as days_offline
-- FROM netmap_devices
-- WHERE is_active = FALSE
-- ORDER BY last_seen_at DESC
-- LIMIT 20;

-- История изменений количества устройств
-- SELECT 
--     DATE(scan_finished_at) as scan_date,
--     COUNT(*) as scans_count,
--     AVG(devices_discovered) as avg_devices,
--     AVG(links_discovered) as avg_links
-- FROM netmap_topology_scans
-- WHERE status = 'completed'
--   AND scan_finished_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
-- GROUP BY DATE(scan_finished_at)
-- ORDER BY scan_date DESC;

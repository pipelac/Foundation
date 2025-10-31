<?php

declare(strict_types=1);

/**
 * Скрипт для сканирования и сохранения топологии сети
 * 
 * Использование:
 * php examples/netmap_topology_scan.php
 * 
 * Для использования в cron (ежедневное сканирование):
 * 0 2 * * * cd /path/to/project && php examples/netmap_topology_scan.php >> logs/scan_cron.log 2>&1
 */

// Засекаем время начала
define('SCRIPT_START', microtime(true));

require_once __DIR__ . '/../vendor/autoload.php';

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\Netmap\LldpCollector;
use App\Component\Netmap\TopologyBuilder;
use App\Component\Netmap\TopologySaver;
use App\Config\ConfigLoader;

// Определяем пути к конфигурациям
$lldpConfigFile = __DIR__ . '/../config/lldp_topology.json';
$dbConfigFile = __DIR__ . '/../config/topology_db.json';

echo "=== Сканирование топологии сети ===\n";
echo "Время начала: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // 1. Инициализация логгера
    echo "1. Инициализация логгера...\n";
    $logger = new Logger([
        'directory' => __DIR__ . '/../logs',
        'file_name' => 'topology_scan.log',
        'max_files' => 10,
        'max_file_size' => 20, // 20 MB
        'enabled' => true,
    ]);
    echo "   ✓ Логгер создан\n\n";
    
    // 2. Загрузка конфигурации LLDP
    echo "2. Загрузка конфигурации...\n";
    
    if (!file_exists($lldpConfigFile)) {
        throw new Exception("Файл конфигурации LLDP не найден: {$lldpConfigFile}");
    }
    
    $lldpConfig = ConfigLoader::load($lldpConfigFile);
    echo "   ✓ Конфигурация LLDP загружена\n";
    echo "   - Начальных устройств: " . count($lldpConfig['seed_devices']) . "\n";
    echo "   - SNMP community: " . $lldpConfig['snmp']['community'] . "\n";
    echo "   - Максимум устройств: " . ($lldpConfig['max_devices'] ?? 1000) . "\n\n";
    
    // 3. Сбор данных LLDP
    echo "3. Сбор данных через LLDP...\n";
    $startTime = microtime(true);
    
    $collector = new LldpCollector($lldpConfig, $logger);
    $collectedData = $collector->discoverTopology();
    
    $collectionTime = round(microtime(true) - $startTime, 2);
    
    echo "   ✓ Сбор данных завершен за {$collectionTime} секунд\n";
    echo "   - Обнаружено устройств: " . count($collectedData['devices']) . "\n";
    echo "   - Обнаружено связей: " . count($collectedData['links']) . "\n\n";
    
    if (empty($collectedData['devices'])) {
        echo "   ⚠ Устройства не обнаружены. Проверьте:\n";
        echo "   1. Доступность seed_devices\n";
        echo "   2. SNMP конфигурацию\n";
        echo "   3. Поддержку LLDP на устройствах\n";
        exit(0);
    }
    
    // Вывод списка обнаруженных устройств
    echo "   Обнаруженные устройства:\n";
    foreach ($collectedData['devices'] as $device) {
        $capabilities = !empty($device['capabilities']) ? implode(', ', $device['capabilities']) : 'none';
        echo "   - {$device['sys_name']} ({$device['management_ip']}) [{$capabilities}]\n";
    }
    echo "\n";
    
    // 4. Построение графа топологии
    echo "4. Построение графа топологии...\n";
    $startTime = microtime(true);
    
    $builder = new TopologyBuilder($logger);
    $topology = $builder->buildTopology($collectedData);
    
    $buildTime = round(microtime(true) - $startTime, 2);
    
    echo "   ✓ Граф построен за {$buildTime} секунд\n";
    echo "   - Узлов в графе: " . count($topology['nodes']) . "\n";
    echo "   - Ребер в графе: " . count($topology['edges']) . "\n";
    echo "   - Корневых узлов: " . count($topology['root_nodes']) . "\n";
    
    if (!empty($topology['cycles'])) {
        echo "   ⚠ Обнаружены циклы: " . count($topology['cycles']) . "\n";
    } else {
        echo "   - Циклов не обнаружено\n";
    }
    
    // Статистика по типам узлов
    $stats = $builder->getStats();
    if (!empty($stats['nodes_by_type'])) {
        echo "   - Распределение по типам:\n";
        foreach ($stats['nodes_by_type'] as $type => $count) {
            echo "     * {$type}: {$count}\n";
        }
    }
    echo "\n";
    
    // Вывод корневых устройств
    if (!empty($topology['root_nodes'])) {
        echo "   Корневые устройства:\n";
        foreach ($topology['root_nodes'] as $rootId) {
            $node = $topology['nodes'][$rootId];
            echo "   - {$node['sys_name']} (уровень: {$node['level']}, тип: {$node['node_type']})\n";
        }
        echo "\n";
    }
    
    // 5. Сохранение в БД
    echo "5. Сохранение в базу данных...\n";
    
    if (!file_exists($dbConfigFile)) {
        echo "   ⚠ Конфигурация БД не найдена: {$dbConfigFile}\n";
        echo "   Пропускаем сохранение в БД\n";
        echo "   Создайте конфигурацию БД для сохранения результатов\n\n";
    } else {
        $dbConfig = ConfigLoader::load($dbConfigFile);
        
        $mysql = new MySQL($dbConfig, $logger);
        echo "   ✓ Подключено к БД: {$dbConfig['database']}@{$dbConfig['host']}\n";
        
        $saver = new TopologySaver($mysql, $logger);
        
        // Создаем таблицы если их нет
        $saver->createTables();
        
        // Начинаем новое сканирование
        $scanId = $saver->startScan();
        echo "   ✓ Начато сканирование ID: {$scanId}\n";
        
        // Сохраняем топологию
        $startTime = microtime(true);
        $saveStats = $saver->saveTopology($topology);
        $saveTime = round(microtime(true) - $startTime, 2);
        
        echo "   ✓ Данные сохранены за {$saveTime} секунд\n";
        echo "   - Устройств добавлено: {$saveStats['devices_added']}\n";
        echo "   - Устройств обновлено: {$saveStats['devices_updated']}\n";
        echo "   - Связей добавлено: {$saveStats['links_added']}\n";
        echo "   - Связей обновлено: {$saveStats['links_updated']}\n";
        
        // Завершаем сканирование
        $saver->finishScan($scanId, true);
        echo "   ✓ Сканирование успешно завершено\n\n";
        
        // Получаем статистику из БД
        $dbStats = $saver->getTopologyStats();
        echo "   Статистика топологии в БД:\n";
        echo "   - Всего устройств: {$dbStats['total_devices']}\n";
        echo "   - Активных устройств: {$dbStats['active_devices']}\n";
        echo "   - Всего связей: {$dbStats['total_links']}\n";
        echo "   - Активных связей: {$dbStats['active_links']}\n\n";
        
        // Очистка старых данных (опционально)
        $cleanupDays = 90; // Удаляем неактивные устройства старше 90 дней
        echo "6. Очистка старых данных...\n";
        $deletedCount = $saver->cleanupInactiveDevices($cleanupDays);
        if ($deletedCount > 0) {
            echo "   ✓ Удалено неактивных устройств: {$deletedCount}\n\n";
        } else {
            echo "   - Нет устройств для удаления\n\n";
        }
    }
    
    // Итоговая информация
    $totalTime = round(microtime(true) - SCRIPT_START, 2);
    echo "=== Сканирование завершено успешно ===\n";
    echo "Время завершения: " . date('Y-m-d H:i:s') . "\n";
    echo "Общее время выполнения: {$totalTime} секунд\n";
    echo "\nДетальная информация в лог-файле: logs/topology_scan.log\n";
    
    exit(0);
    
} catch (\App\Component\Exception\LldpCollectorException $e) {
    echo "\n✗ Ошибка сбора LLDP данных:\n";
    echo "  " . $e->getMessage() . "\n";
    
    if (isset($logger)) {
        $logger->log('error', 'Критическая ошибка сканирования', [
            'type' => 'LldpCollectorException',
            'message' => $e->getMessage(),
        ]);
    }
    
    exit(1);
    
} catch (\App\Component\Exception\TopologyBuilderException $e) {
    echo "\n✗ Ошибка построения топологии:\n";
    echo "  " . $e->getMessage() . "\n";
    
    if (isset($logger)) {
        $logger->log('error', 'Критическая ошибка построения графа', [
            'type' => 'TopologyBuilderException',
            'message' => $e->getMessage(),
        ]);
    }
    
    exit(1);
    
} catch (\App\Component\Exception\TopologySaverException $e) {
    echo "\n✗ Ошибка сохранения в БД:\n";
    echo "  " . $e->getMessage() . "\n";
    
    if (isset($logger) && isset($scanId)) {
        $logger->log('error', 'Критическая ошибка сохранения', [
            'type' => 'TopologySaverException',
            'message' => $e->getMessage(),
            'scan_id' => $scanId,
        ]);
        
        // Помечаем сканирование как неудачное
        if (isset($saver)) {
            $saver->finishScan($scanId, false, $e->getMessage());
        }
    }
    
    exit(1);
    
} catch (\Exception $e) {
    echo "\n✗ Неожиданная ошибка:\n";
    echo "  " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    
    if (isset($logger)) {
        $logger->log('critical', 'Критическая системная ошибка', [
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
    
    exit(1);
}

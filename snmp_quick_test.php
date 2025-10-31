<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Component\Snmp;
use App\Component\Logger;

echo "=== Quick Test: Snmp класс ===\n\n";

// Создание логгера
$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'file_name' => 'snmp_quick.log',
]);

try {
    // Создание SNMP соединения
    $snmp = new Snmp([
        'host' => '127.0.0.1',
        'community' => 'public',
        'version' => Snmp::VERSION_2C,
    ], $logger);

    echo "✓ SNMP соединение установлено\n";

    // Получение системной информации
    $info = $snmp->getSystemInfo();
    echo "\n--- Системная информация ---\n";
    echo "Описание: " . ($info['sysDescr'] ?? 'N/A') . "\n";
    echo "Имя: " . ($info['sysName'] ?? 'N/A') . "\n";
    echo "Местоположение: " . ($info['sysLocation'] ?? 'N/A') . "\n";
    echo "Контакт: " . ($info['sysContact'] ?? 'N/A') . "\n";
    echo "Uptime: " . ($info['sysUpTime'] ?? 'N/A') . "\n";

    // Получение сетевых интерфейсов
    echo "\n--- Сетевые интерфейсы ---\n";
    $interfaces = $snmp->getNetworkInterfaces();
    foreach ($interfaces as $interface) {
        echo "Interface #{$interface['index']}: {$interface['description']}\n";
    }

    // Информация о соединении
    echo "\n--- Информация о соединении ---\n";
    $connInfo = $snmp->getConnectionInfo();
    echo "Хост: {$connInfo['host']}\n";
    echo "Версия: {$connInfo['version']}\n";
    echo "Тайм-аут: {$connInfo['timeout']} мкс\n";
    echo "Повторы: {$connInfo['retries']}\n";

    // Закрытие соединения
    $snmp->close();
    echo "\n✓ SNMP соединение закрыто\n";

    echo "\n=== Тест пройден успешно! ===\n";

} catch (\Exception $e) {
    echo "\n✗ Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}

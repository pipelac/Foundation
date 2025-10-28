#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Скрипт для быстрого тестирования MySQLConnectionFactory
 * 
 * Проверяет базовую функциональность фабрики соединений
 * без фактического подключения к БД
 */

require_once __DIR__ . '/../autoload.php';

use App\Component\MySQLConnectionFactory;
use App\Component\Logger;
use App\Component\Exception\MySQLException;

echo "=== Тест MySQLConnectionFactory ===\n\n";

try {
    echo "1. Создание тестовой конфигурации... ";
    $config = [
        'connections' => [
            'test1' => [
                'host' => 'localhost',
                'port' => 3306,
                'database' => 'test_db1',
                'username' => 'test_user',
                'password' => 'test_pass',
            ],
            'test2' => [
                'host' => 'localhost',
                'port' => 3306,
                'database' => 'test_db2',
                'username' => 'test_user',
                'password' => 'test_pass',
            ],
        ],
        'default_connection' => 'test1',
    ];
    echo "✓\n";

    echo "2. Инициализация фабрики без логгера... ";
    $factory = MySQLConnectionFactory::initialize($config);
    echo "✓\n";

    echo "3. Проверка доступных подключений... ";
    $available = $factory->getAvailableConnections();
    if (count($available) === 2 && in_array('test1', $available) && in_array('test2', $available)) {
        echo "✓\n";
        echo "   Найдено подключений: " . count($available) . "\n";
    } else {
        echo "✗\n";
        exit(1);
    }

    echo "4. Проверка имени подключения по умолчанию... ";
    $defaultName = $factory->getDefaultConnectionName();
    if ($defaultName === 'test1') {
        echo "✓\n";
    } else {
        echo "✗ (получено: {$defaultName})\n";
        exit(1);
    }

    echo "5. Проверка наличия подключения... ";
    if ($factory->hasConnection('test1') && $factory->hasConnection('test2') && !$factory->hasConnection('nonexistent')) {
        echo "✓\n";
    } else {
        echo "✗\n";
        exit(1);
    }

    echo "6. Проверка кеша (должен быть пуст)... ";
    if (count($factory->getCachedConnections()) === 0) {
        echo "✓\n";
    } else {
        echo "✗\n";
        exit(1);
    }

    echo "7. Проверка getInstance() после initialize()... ";
    $factoryInstance = MySQLConnectionFactory::getInstance();
    if ($factoryInstance === $factory) {
        echo "✓\n";
    } else {
        echo "✗\n";
        exit(1);
    }

    echo "8. Проверка обработки ошибок (несуществующее подключение)... ";
    try {
        $factory->getConnection('nonexistent');
        echo "✗ (исключение не выброшено)\n";
        exit(1);
    } catch (MySQLException $e) {
        if (strpos($e->getMessage(), 'не найдено') !== false) {
            echo "✓\n";
        } else {
            echo "✗ (неверное сообщение)\n";
            exit(1);
        }
    }

    echo "\n=== Все базовые тесты пройдены успешно! ===\n\n";

    echo "Примечание: Для полного тестирования с реальными подключениями к БД\n";
    echo "используйте: php examples/mysql_multi_connection_example.php\n";

} catch (\Throwable $e) {
    echo "\n✗ Критическая ошибка: {$e->getMessage()}\n";
    echo "Файл: {$e->getFile()}:{$e->getLine()}\n";
    echo "Трассировка:\n{$e->getTraceAsString()}\n";
    exit(1);
}

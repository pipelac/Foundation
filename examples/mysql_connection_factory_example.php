<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use App\Component\MySQLConnectionFactory;
use App\Component\Logger;
use App\Component\Exception\MySQLException;
use App\Component\Exception\MySQLConnectionException;

echo "=== Примеры использования класса MySQLConnectionFactory ===\n\n";

// Инициализация логгера
$logger = new Logger([
    'directory' => __DIR__ . '/../logs',
    'file_name' => 'mysql_factory_example.log',
    'max_files' => 3,
    'max_file_size' => 5,
]);

// Загрузка конфигурации
$configPath = __DIR__ . '/../config/mysql.json';
if (!file_exists($configPath)) {
    die("✗ Конфигурационный файл не найден: {$configPath}\n");
}

$config = json_decode(file_get_contents($configPath), true);
if ($config === null) {
    die("✗ Ошибка парсинга конфигурации: " . json_last_error_msg() . "\n");
}

// Создание фабрики соединений
try {
    $factory = new MySQLConnectionFactory($config, $logger);
    echo "✓ MySQLConnectionFactory успешно инициализирована\n\n";
} catch (MySQLException $e) {
    die("✗ Ошибка инициализации фабрики: {$e->getMessage()}\n");
}

// Информация о доступных БД
echo "--- Доступные базы данных ---\n";
$databases = $factory->getAvailableDatabases();
echo "Всего БД в конфигурации: " . count($databases) . "\n";
foreach ($databases as $dbName) {
    $isDefault = ($dbName === $factory->getDefaultDatabaseName()) ? ' (по умолчанию)' : '';
    echo "  - {$dbName}{$isDefault}\n";
}
echo "\n";

// Получение соединения по умолчанию
echo "--- Получение соединения по умолчанию ---\n";
try {
    $mainDb = $factory->getDefaultConnection();
    echo "✓ Соединение с БД по умолчанию установлено\n";
    
    if ($mainDb->ping()) {
        echo "✓ Проверка ping: соединение активно\n";
    }
    
    $info = $mainDb->getConnectionInfo();
    echo "  Версия сервера: {$info['server_version']}\n";
    echo "  Версия клиента: {$info['client_version']}\n\n";
} catch (MySQLConnectionException $e) {
    echo "✗ Ошибка подключения: {$e->getMessage()}\n\n";
} catch (MySQLException $e) {
    echo "✗ Ошибка MySQL: {$e->getMessage()}\n\n";
}

// Получение именованных соединений
echo "--- Получение именованных соединений ---\n";
$connectionNames = ['main', 'analytics', 'logs'];

foreach ($connectionNames as $dbName) {
    if (!$factory->hasDatabase($dbName)) {
        echo "⚠ База данных '{$dbName}' не найдена в конфигурации\n";
        continue;
    }
    
    try {
        $db = $factory->getConnection($dbName);
        echo "✓ Соединение с БД '{$dbName}' установлено\n";
    } catch (MySQLConnectionException $e) {
        echo "✗ Ошибка подключения к '{$dbName}': {$e->getMessage()}\n";
    } catch (MySQLException $e) {
        echo "✗ Ошибка получения '{$dbName}': {$e->getMessage()}\n";
    }
}
echo "\n";

// Демонстрация кеширования соединений
echo "--- Демонстрация кеширования соединений ---\n";
echo "Активных соединений в кеше: {$factory->getCachedConnectionsCount()}\n";
echo "Активные БД: " . implode(', ', $factory->getActiveDatabases()) . "\n\n";

// Повторное получение соединения (из кеша)
echo "--- Повторное получение соединения (из кеша) ---\n";
try {
    $startTime = microtime(true);
    $cachedConnection = $factory->getConnection('main');
    $elapsedTime = (microtime(true) - $startTime) * 1000;
    echo "✓ Соединение получено из кеша\n";
    echo "  Время получения: " . number_format($elapsedTime, 4) . " мс\n";
    echo "  Это то же самое соединение: " . ($cachedConnection === $mainDb ? 'Да' : 'Нет') . "\n\n";
} catch (MySQLException $e) {
    echo "✗ Ошибка: {$e->getMessage()}\n\n";
}

// Проверка активности соединений
echo "--- Проверка активности соединений ---\n";
foreach ($connectionNames as $dbName) {
    if ($factory->hasDatabase($dbName)) {
        $isAlive = $factory->isConnectionAlive($dbName);
        $status = $isAlive ? '✓ Активно' : '✗ Неактивно';
        echo "{$status} - {$dbName}\n";
    }
}
echo "\n";

// Демонстрация работы с несколькими БД одновременно
echo "--- Работа с несколькими БД одновременно ---\n";
try {
    // Создаем тестовые таблицы в разных БД
    echo "Подготовка тестовых таблиц...\n";
    
    // БД main
    $mainDb = $factory->getConnection('main');
    $mainDb->execute('DROP TABLE IF EXISTS test_users');
    $mainDb->execute('
        CREATE TABLE IF NOT EXISTS test_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ');
    
    // Вставка данных в main
    $userId = $mainDb->insert(
        'INSERT INTO test_users (name, email) VALUES (?, ?)',
        ['Иван Иванов', 'ivan@example.com']
    );
    echo "✓ Пользователь создан в БД 'main' с ID: {$userId}\n";
    
    // БД analytics (если доступна)
    if ($factory->hasDatabase('analytics')) {
        $analyticsDb = $factory->getConnection('analytics');
        $analyticsDb->execute('DROP TABLE IF EXISTS test_statistics');
        $analyticsDb->execute('
            CREATE TABLE IF NOT EXISTS test_statistics (
                id INT AUTO_INCREMENT PRIMARY KEY,
                metric_name VARCHAR(100) NOT NULL,
                metric_value INT NOT NULL,
                recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');
        
        // Вставка данных в analytics
        $statId = $analyticsDb->insert(
            'INSERT INTO test_statistics (metric_name, metric_value) VALUES (?, ?)',
            ['page_views', 1000]
        );
        echo "✓ Метрика создана в БД 'analytics' с ID: {$statId}\n";
    }
    
    // Чтение данных из обеих БД
    echo "\nЧтение данных:\n";
    
    $users = $mainDb->query('SELECT * FROM test_users');
    echo "  Пользователей в 'main': " . count($users) . "\n";
    
    if ($factory->hasDatabase('analytics')) {
        $analyticsDb = $factory->getConnection('analytics');
        $stats = $analyticsDb->query('SELECT * FROM test_statistics');
        echo "  Метрик в 'analytics': " . count($stats) . "\n";
    }
    
    echo "\n";
    
} catch (MySQLConnectionException $e) {
    echo "✗ Ошибка подключения: {$e->getMessage()}\n\n";
} catch (MySQLException $e) {
    echo "✗ Ошибка работы с БД: {$e->getMessage()}\n\n";
}

// Транзакции в разных БД
echo "--- Транзакции в разных БД ---\n";
try {
    $mainDb = $factory->getConnection('main');
    
    $result = $mainDb->transaction(function() use ($mainDb) {
        // Вставляем нескольких пользователей в рамках одной транзакции
        $id1 = $mainDb->insert(
            'INSERT INTO test_users (name, email) VALUES (?, ?)',
            ['Петр Петров', 'petr@example.com']
        );
        
        $id2 = $mainDb->insert(
            'INSERT INTO test_users (name, email) VALUES (?, ?)',
            ['Мария Сидорова', 'maria@example.com']
        );
        
        return ['id1' => $id1, 'id2' => $id2];
    });
    
    echo "✓ Транзакция в БД 'main' выполнена успешно\n";
    echo "  Созданы пользователи: ID {$result['id1']} и ID {$result['id2']}\n\n";
    
} catch (\Throwable $e) {
    echo "✗ Ошибка транзакции: {$e->getMessage()}\n\n";
}

// Проверка версий MySQL
echo "--- Проверка версий MySQL во всех соединениях ---\n";
try {
    $versions = $factory->getMySQLVersions();
    
    if (empty($versions)) {
        echo "⚠ Нет активных соединений для проверки версий\n";
    } else {
        foreach ($versions as $dbName => $versionInfo) {
            echo "БД '{$dbName}':\n";
            echo "  Версия: {$versionInfo['version']}\n";
            echo "  Компоненты: {$versionInfo['major']}.{$versionInfo['minor']}.{$versionInfo['patch']}\n";
            echo "  Поддерживается: " . ($versionInfo['is_supported'] ? '✓ Да' : '✗ Нет') . "\n";
            echo "  Рекомендуется: " . ($versionInfo['is_recommended'] ? '✓ Да (5.5.62+)' : '⚠ Обновление рекомендуется') . "\n";
        }
        
        echo "\nОбщая совместимость:\n";
        echo "  Все версии поддерживаются: " . ($factory->areAllVersionsSupported() ? '✓ Да' : '✗ Нет') . "\n";
        echo "  Все версии рекомендованы: " . ($factory->areAllVersionsRecommended() ? '✓ Да' : '⚠ Нет') . "\n";
        
        if (!$factory->areAllVersionsRecommended()) {
            echo "\n⚠ РЕКОМЕНДАЦИЯ: Обновите MySQL до версии 5.5.62 или выше\n";
            echo "  для обеспечения лучшей безопасности и производительности.\n";
        }
    }
} catch (MySQLException $e) {
    echo "✗ Ошибка проверки версий: {$e->getMessage()}\n";
}
echo "\n";

// Статистика по соединениям
echo "--- Финальная статистика ---\n";
echo "Всего доступных БД: " . count($factory->getAvailableDatabases()) . "\n";
echo "Активных соединений: {$factory->getCachedConnectionsCount()}\n";
echo "БД по умолчанию: {$factory->getDefaultDatabaseName()}\n\n";

// Очистка тестовых данных
echo "--- Очистка тестовых данных ---\n";
try {
    $mainDb = $factory->getConnection('main');
    $mainDb->execute('DROP TABLE IF EXISTS test_users');
    echo "✓ Таблица test_users удалена из 'main'\n";
    
    if ($factory->hasDatabase('analytics')) {
        $analyticsDb = $factory->getConnection('analytics');
        $analyticsDb->execute('DROP TABLE IF EXISTS test_statistics');
        echo "✓ Таблица test_statistics удалена из 'analytics'\n";
    }
} catch (MySQLException $e) {
    echo "✗ Ошибка очистки: {$e->getMessage()}\n";
}
echo "\n";

// Очистка кеша соединений
echo "--- Очистка кеша соединений ---\n";
$factory->clearConnectionCache();
echo "✓ Кеш соединений очищен\n";
echo "Активных соединений после очистки: {$factory->getCachedConnectionsCount()}\n\n";

echo "=== Завершение примеров ===\n";


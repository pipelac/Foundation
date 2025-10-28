<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use App\Component\MySQLConnectionFactory;
use App\Component\Logger;
use App\Component\Exception\MySQLException;
use App\Component\Exception\MySQLConnectionException;
use App\Config\ConfigLoader;

echo "=== Примеры использования MySQLConnectionFactory для множественных подключений ===\n\n";

$logger = new Logger([
    'directory' => __DIR__ . '/../logs',
    'file_name' => 'mysql_factory.log',
    'max_files' => 3,
    'max_file_size' => 5,
]);

try {
    echo "--- Загрузка конфигурации ---\n";
    $mysqlConfig = ConfigLoader::load(__DIR__ . '/../config/mysql.json');
    echo "✓ Конфигурация загружена\n\n";
    
    echo "--- Инициализация фабрики соединений ---\n";
    $factory = MySQLConnectionFactory::initialize($mysqlConfig, $logger);
    echo "✓ MySQLConnectionFactory инициализирована\n";
    
    $availableConnections = $factory->getAvailableConnections();
    echo "✓ Доступные подключения: " . implode(', ', $availableConnections) . "\n";
    
    $defaultName = $factory->getDefaultConnectionName();
    if ($defaultName !== null) {
        echo "✓ Подключение по умолчанию: {$defaultName}\n\n";
    }
    
} catch (MySQLException $e) {
    die("✗ Ошибка инициализации фабрики: {$e->getMessage()}\n");
}

echo "=== Пример 1: Получение и использование подключений ===\n\n";

try {
    echo "--- Получение подключения 'default' ---\n";
    $defaultDb = $factory->getConnection('default');
    echo "✓ Соединение 'default' получено\n";
    
    if ($defaultDb->ping()) {
        echo "✓ Соединение 'default' активно\n";
        $info = $defaultDb->getConnectionInfo();
        echo "  • База данных: {$info['server_info']}\n";
        echo "  • Версия сервера: {$info['server_version']}\n";
    }
    echo "\n";
    
} catch (MySQLConnectionException $e) {
    echo "⚠ Не удалось подключиться к 'default': {$e->getMessage()}\n\n";
} catch (MySQLException $e) {
    echo "✗ Ошибка работы с 'default': {$e->getMessage()}\n\n";
}

try {
    echo "--- Получение подключения 'analytics' ---\n";
    $analyticsDb = $factory->getConnection('analytics');
    echo "✓ Соединение 'analytics' получено\n";
    
    if ($analyticsDb->ping()) {
        echo "✓ Соединение 'analytics' активно\n";
    }
    echo "\n";
    
} catch (MySQLConnectionException $e) {
    echo "⚠ Не удалось подключиться к 'analytics': {$e->getMessage()}\n\n";
} catch (MySQLException $e) {
    echo "✗ Ошибка работы с 'analytics': {$e->getMessage()}\n\n";
}

try {
    echo "--- Получение подключения 'logs' ---\n";
    $logsDb = $factory->getConnection('logs');
    echo "✓ Соединение 'logs' получено\n";
    
    if ($logsDb->ping()) {
        echo "✓ Соединение 'logs' активно\n";
    }
    echo "\n";
    
} catch (MySQLConnectionException $e) {
    echo "⚠ Не удалось подключиться к 'logs': {$e->getMessage()}\n\n";
} catch (MySQLException $e) {
    echo "✗ Ошибка работы с 'logs': {$e->getMessage()}\n\n";
}

echo "=== Пример 2: Кеширование соединений ===\n\n";

echo "--- Проверка кеширования ---\n";
try {
    echo "Закешированные соединения: " . implode(', ', $factory->getCachedConnections()) . "\n";
    
    echo "\nПовторное получение соединения 'default' (из кеша):\n";
    $cachedDb = $factory->getConnection('default');
    echo "✓ Соединение получено из кеша (объект не пересоздавался)\n";
    echo "✓ Это тот же объект: " . ($cachedDb === $defaultDb ? 'Да' : 'Нет') . "\n\n";
    
} catch (MySQLException $e) {
    echo "✗ Ошибка: {$e->getMessage()}\n\n";
}

echo "=== Пример 3: Использование подключения по умолчанию ===\n\n";

try {
    echo "--- Получение подключения по умолчанию ---\n";
    $db = $factory->getDefaultConnection();
    echo "✓ Подключение по умолчанию получено\n\n";
    
} catch (MySQLException $e) {
    echo "✗ Ошибка: {$e->getMessage()}\n\n";
}

echo "=== Пример 4: Параллельная работа с несколькими БД ===\n\n";

try {
    echo "--- Создание тестовых таблиц в разных БД ---\n";
    
    // В основной БД создаем таблицу пользователей
    if (isset($defaultDb) && $defaultDb->ping()) {
        try {
            $defaultDb->execute('DROP TABLE IF EXISTS users');
            $defaultDb->execute('
                CREATE TABLE users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    email VARCHAR(100) NOT NULL UNIQUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ');
            echo "✓ Таблица 'users' создана в базе 'default'\n";
            
            $userId = $defaultDb->insert(
                'INSERT INTO users (name, email) VALUES (?, ?)',
                ['Иван Иванов', 'ivan@example.com']
            );
            echo "✓ Создан пользователь с ID: {$userId}\n\n";
        } catch (MySQLException $e) {
            echo "⚠ Ошибка работы с 'default': {$e->getMessage()}\n\n";
        }
    }
    
    // В БД аналитики создаем таблицу событий
    if (isset($analyticsDb) && $analyticsDb->ping()) {
        try {
            $analyticsDb->execute('DROP TABLE IF EXISTS events');
            $analyticsDb->execute('
                CREATE TABLE events (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    event_type VARCHAR(50) NOT NULL,
                    user_id INT NOT NULL,
                    event_data JSON,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ');
            echo "✓ Таблица 'events' создана в базе 'analytics'\n";
            
            $eventId = $analyticsDb->insert(
                'INSERT INTO events (event_type, user_id, event_data) VALUES (?, ?, ?)',
                ['user_registered', $userId ?? 1, json_encode(['source' => 'web'])]
            );
            echo "✓ Создано событие с ID: {$eventId}\n\n";
        } catch (MySQLException $e) {
            echo "⚠ Ошибка работы с 'analytics': {$e->getMessage()}\n\n";
        }
    }
    
    // В БД логов создаем таблицу логов
    if (isset($logsDb) && $logsDb->ping()) {
        try {
            $logsDb->execute('DROP TABLE IF EXISTS application_logs');
            $logsDb->execute('
                CREATE TABLE application_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    level VARCHAR(20) NOT NULL,
                    message TEXT NOT NULL,
                    context JSON,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ');
            echo "✓ Таблица 'application_logs' создана в базе 'logs'\n";
            
            $logId = $logsDb->insert(
                'INSERT INTO application_logs (level, message, context) VALUES (?, ?, ?)',
                ['info', 'User registered', json_encode(['user_id' => $userId ?? 1])]
            );
            echo "✓ Создан лог с ID: {$logId}\n\n";
        } catch (MySQLException $e) {
            echo "⚠ Ошибка работы с 'logs': {$e->getMessage()}\n\n";
        }
    }
    
} catch (MySQLException $e) {
    echo "✗ Общая ошибка: {$e->getMessage()}\n\n";
}

echo "=== Пример 5: Проверка состояния всех подключений ===\n\n";

try {
    echo "--- Проверка ping для всех закешированных соединений ---\n";
    $pingResults = $factory->pingAll();
    
    foreach ($pingResults as $name => $isActive) {
        $status = $isActive ? '✓ активно' : '✗ неактивно';
        echo "  • {$name}: {$status}\n";
    }
    echo "\n";
    
} catch (MySQLException $e) {
    echo "✗ Ошибка проверки: {$e->getMessage()}\n\n";
}

echo "=== Пример 6: Управление кешем ===\n\n";

try {
    echo "--- Информация о кеше ---\n";
    $cachedCount = count($factory->getCachedConnections());
    echo "Закешировано соединений: {$cachedCount}\n\n";
    
    echo "--- Удаление конкретного соединения из кеша ---\n";
    if ($factory->clearConnection('analytics')) {
        echo "✓ Соединение 'analytics' удалено из кеша\n";
    } else {
        echo "⚠ Соединение 'analytics' не было в кеше\n";
    }
    
    echo "Закешировано соединений: " . count($factory->getCachedConnections()) . "\n\n";
    
    echo "--- Полная очистка кеша ---\n";
    $factory->clearCache();
    echo "✓ Кеш соединений полностью очищен\n";
    echo "Закешировано соединений: " . count($factory->getCachedConnections()) . "\n\n";
    
} catch (MySQLException $e) {
    echo "✗ Ошибка управления кешем: {$e->getMessage()}\n\n";
}

echo "=== Пример 7: Получение экземпляра фабрики из любого места ===\n\n";

try {
    echo "--- Использование singleton getInstance() ---\n";
    $factoryInstance = MySQLConnectionFactory::getInstance();
    echo "✓ Получен экземпляр фабрики через getInstance()\n";
    echo "✓ Это тот же экземпляр: " . ($factoryInstance === $factory ? 'Да' : 'Нет') . "\n\n";
    
    echo "--- Получение подключения через глобальный экземпляр ---\n";
    $globalDb = $factoryInstance->getConnection('default');
    echo "✓ Подключение получено из глобального экземпляра\n\n";
    
} catch (MySQLException $e) {
    echo "✗ Ошибка: {$e->getMessage()}\n\n";
}

echo "=== Пример 8: Обработка ошибок ===\n\n";

try {
    echo "--- Попытка получить несуществующее подключение ---\n";
    $factory->getConnection('nonexistent');
    
} catch (MySQLException $e) {
    echo "✓ Ожидаемая ошибка перехвачена: {$e->getMessage()}\n\n";
}

echo "=== Пример 9: Очистка тестовых данных ===\n\n";

try {
    echo "--- Удаление тестовых таблиц ---\n";
    
    if (isset($defaultDb) && $defaultDb->ping()) {
        try {
            $defaultDb->execute('DROP TABLE IF EXISTS users');
            echo "✓ Таблица 'users' удалена из базы 'default'\n";
        } catch (MySQLException $e) {
            echo "⚠ Ошибка: {$e->getMessage()}\n";
        }
    }
    
    $analyticsDb = $factory->getConnection('analytics');
    if ($analyticsDb->ping()) {
        try {
            $analyticsDb->execute('DROP TABLE IF EXISTS events');
            echo "✓ Таблица 'events' удалена из базы 'analytics'\n";
        } catch (MySQLException $e) {
            echo "⚠ Ошибка: {$e->getMessage()}\n";
        }
    }
    
    $logsDb = $factory->getConnection('logs');
    if ($logsDb->ping()) {
        try {
            $logsDb->execute('DROP TABLE IF EXISTS application_logs');
            echo "✓ Таблица 'application_logs' удалена из базы 'logs'\n";
        } catch (MySQLException $e) {
            echo "⚠ Ошибка: {$e->getMessage()}\n";
        }
    }
    
    echo "\n";
    
} catch (MySQLConnectionException $e) {
    echo "⚠ Не удалось подключиться: {$e->getMessage()}\n\n";
} catch (MySQLException $e) {
    echo "✗ Ошибка очистки: {$e->getMessage()}\n\n";
}

echo "=== Завершение примеров ===\n\n";

echo "Основные преимущества MySQLConnectionFactory:\n";
echo "  ✓ Управление множественными подключениями к разным БД\n";
echo "  ✓ Автоматическое кеширование соединений для повышения производительности\n";
echo "  ✓ Ленивая инициализация - соединение создается только при необходимости\n";
echo "  ✓ Централизованное управление всеми подключениями приложения\n";
echo "  ✓ Singleton паттерн для глобального доступа из любого места кода\n";
echo "  ✓ Строгая типизация и обработка исключений\n";
echo "  ✓ Полная обратная совместимость с существующим MySQL классом\n";

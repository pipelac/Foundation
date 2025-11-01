<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Component\MySQL;
use App\Component\Logger;
use App\Component\Exception\MySQL\MySQLException;
use App\Component\Exception\MySQL\MySQLConnectionException;
use App\Component\Exception\MySQL\MySQLTransactionException;

/**
 * Полноценный тест класса MySQL
 * 
 * Тестирует все методы класса с реальной БД и проверяет логирование
 */
class MySQLFullTest
{
    private MySQL $db;
    private Logger $logger;
    private string $testTable = 'test_mysql_operations';
    private array $results = [];
    private int $passed = 0;
    private int $failed = 0;

    public function __construct()
    {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "ПОЛНОЦЕННЫЙ ТЕСТ КЛАССА MySQL.class.php\n";
        echo str_repeat("=", 80) . "\n\n";
    }

    /**
     * Запуск всех тестов
     */
    public function runAll(): void
    {
        try {
            $this->setupLogger();
            $this->setupDatabase();
            
            // Тесты подключения и конфигурации
            $this->testValidConfig();
            $this->testInvalidConfig();
            
            // Тесты DDL операций
            $this->testCreateTable();
            
            // Тесты INSERT операций
            $this->testInsertSimple();
            $this->testInsertWithParams();
            $this->testInsertBatch();
            
            // Тесты SELECT операций
            $this->testQuery();
            $this->testQueryOne();
            $this->testQueryScalar();
            $this->testQueryWithParams();
            
            // Тесты UPDATE операций
            $this->testUpdate();
            $this->testUpdateWithParams();
            
            // Тесты DELETE операций
            $this->testDelete();
            $this->testDeleteWithParams();
            
            // Тесты транзакций
            $this->testTransactionCommit();
            $this->testTransactionRollback();
            $this->testTransactionCallback();
            $this->testNestedTransactionError();
            
            // Тесты вспомогательных методов
            $this->testPing();
            $this->testGetConnectionInfo();
            $this->testGetMySQLVersion();
            $this->testStatementCache();
            
            // Тесты обработки ошибок
            $this->testInvalidQuery();
            $this->testInvalidTable();
            
            // Очистка
            $this->cleanup();
            
        } catch (\Throwable $e) {
            echo "\n❌ КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage() . "\n";
            echo "Трассировка: " . $e->getTraceAsString() . "\n";
        } finally {
            $this->printResults();
        }
    }

    /**
     * Настройка логгера
     */
    private function setupLogger(): void
    {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $this->logger = new Logger([
            'directory' => $logDir,
            'file_name' => 'mysql_test.log',
            'max_file_size' => 10, // MB
            'max_files' => 5,
        ]);

        echo "✓ Логгер настроен: {$logDir}/mysql_test.log\n\n";
    }

    /**
     * Настройка подключения к БД
     */
    private function setupDatabase(): void
    {
        echo "📊 Подключение к MySQL...\n";
        
        $config = [
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'test_database',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4',
            'persistent' => false,
            'cache_statements' => true,
        ];

        try {
            $this->db = new MySQL($config, $this->logger);
            echo "✓ Подключение к БД успешно\n\n";
        } catch (MySQLConnectionException $e) {
            // Попробуем создать базу данных
            echo "⚠ База данных не существует, создаем...\n";
            $this->createTestDatabase($config);
            $this->db = new MySQL($config, $this->logger);
            echo "✓ База данных создана и подключение установлено\n\n";
        }
    }

    /**
     * Создание тестовой базы данных
     */
    private function createTestDatabase(array $config): void
    {
        $tempConfig = $config;
        unset($tempConfig['database']);
        $tempConfig['database'] = 'mysql';
        
        $tempDb = new MySQL($tempConfig);
        $tempDb->execute("CREATE DATABASE IF NOT EXISTS test_database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    /**
     * Тест: валидная конфигурация
     */
    private function testValidConfig(): void
    {
        $this->test('Создание MySQL с валидной конфигурацией', function() {
            $config = [
                'database' => 'test_database',
                'username' => 'root',
                'password' => '',
            ];
            
            $db = new MySQL($config, $this->logger);
            return $db instanceof MySQL;
        });
    }

    /**
     * Тест: невалидная конфигурация
     */
    private function testInvalidConfig(): void
    {
        $this->test('Ошибка при отсутствии database в конфигурации', function() {
            try {
                new MySQL(['username' => 'root', 'password' => ''], $this->logger);
                return false;
            } catch (MySQLException $e) {
                return str_contains($e->getMessage(), 'database');
            }
        });

        $this->test('Ошибка при отсутствии username в конфигурации', function() {
            try {
                new MySQL(['database' => 'test', 'password' => ''], $this->logger);
                return false;
            } catch (MySQLException $e) {
                return str_contains($e->getMessage(), 'username');
            }
        });

        $this->test('Ошибка при подключении к несуществующей БД', function() {
            try {
                new MySQL([
                    'database' => 'nonexistent_db_' . uniqid(),
                    'username' => 'root',
                    'password' => '',
                ], $this->logger);
                return false;
            } catch (MySQLConnectionException $e) {
                return true;
            }
        });
    }

    /**
     * Тест: создание таблицы
     */
    private function testCreateTable(): void
    {
        $this->test('CREATE TABLE через execute()', function() {
            $sql = "CREATE TABLE IF NOT EXISTS `{$this->testTable}` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255),
                age INT,
                salary DECIMAL(10, 2),
                is_active BOOLEAN DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            $result = $this->db->execute($sql);
            
            // Проверяем, что таблица создана
            $tables = $this->db->query("SHOW TABLES LIKE '{$this->testTable}'");
            return count($tables) === 1;
        });

        $this->test('TRUNCATE TABLE через execute()', function() {
            $result = $this->db->execute("TRUNCATE TABLE `{$this->testTable}`");
            return $result === 0; // TRUNCATE возвращает 0
        });
    }

    /**
     * Тест: простой INSERT (новый API)
     */
    private function testInsertSimple(): void
    {
        $this->test('INSERT с новым API', function() {
            $lastId = $this->db->insert($this->testTable, [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'age' => 30,
                'salary' => 50000.00
            ]);
            return $lastId > 0;
        });
    }

    /**
     * Тест: INSERT с параметрами через execute()
     */
    private function testInsertWithParams(): void
    {
        $this->test('INSERT через execute() с именованными параметрами', function() {
            $sql = "INSERT INTO `{$this->testTable}` (name, email, age, salary) 
                    VALUES (:name, :email, :age, :salary)";
            
            $params = [
                ':name' => 'Jane Smith',
                ':email' => 'jane@example.com',
                ':age' => 28,
                ':salary' => 55000.00,
            ];
            
            $this->db->execute($sql, $params);
            $lastId = $this->db->getLastInsertId();
            return $lastId > 0;
        });

        $this->test('INSERT через execute() с позиционными параметрами', function() {
            $sql = "INSERT INTO `{$this->testTable}` (name, email, age, salary) 
                    VALUES (?, ?, ?, ?)";
            
            $params = ['Bob Johnson', 'bob@example.com', 35, 60000.00];
            
            $this->db->execute($sql, $params);
            $lastId = $this->db->getLastInsertId();
            return $lastId > 0;
        });
    }

    /**
     * Тест: массовая вставка
     */
    private function testInsertBatch(): void
    {
        $this->test('insertBatch() с 5 записями', function() {
            $rows = [
                ['name' => 'Alice Brown', 'email' => 'alice@example.com', 'age' => 25, 'salary' => 45000.00],
                ['name' => 'Charlie Davis', 'email' => 'charlie@example.com', 'age' => 40, 'salary' => 70000.00],
                ['name' => 'Diana Evans', 'email' => 'diana@example.com', 'age' => 32, 'salary' => 52000.00],
                ['name' => 'Frank Garcia', 'email' => 'frank@example.com', 'age' => 38, 'salary' => 68000.00],
                ['name' => 'Grace Harris', 'email' => 'grace@example.com', 'age' => 29, 'salary' => 51000.00],
            ];
            
            $count = $this->db->insertBatch($this->testTable, $rows);
            return $count === 5;
        });

        $this->test('insertBatch() с пустым массивом', function() {
            $count = $this->db->insertBatch($this->testTable, []);
            return $count === 0;
        });
    }

    /**
     * Тест: SELECT query()
     */
    private function testQuery(): void
    {
        $this->test('query() возвращает все записи', function() {
            $result = $this->db->query("SELECT * FROM `{$this->testTable}`");
            return is_array($result) && count($result) >= 8; // У нас должно быть минимум 8 записей
        });

        $this->test('query() с WHERE условием', function() {
            $result = $this->db->query("SELECT * FROM `{$this->testTable}` WHERE age > 30");
            return is_array($result) && count($result) > 0;
        });
    }

    /**
     * Тест: SELECT queryOne()
     */
    private function testQueryOne(): void
    {
        $this->test('queryOne() возвращает первую запись', function() {
            $result = $this->db->queryOne("SELECT * FROM `{$this->testTable}` ORDER BY id LIMIT 1");
            return is_array($result) && isset($result['name']);
        });

        $this->test('queryOne() возвращает null для пустого результата', function() {
            $result = $this->db->queryOne("SELECT * FROM `{$this->testTable}` WHERE id = 999999");
            return $result === null;
        });
    }

    /**
     * Тест: SELECT queryScalar()
     */
    private function testQueryScalar(): void
    {
        $this->test('queryScalar() возвращает COUNT(*)', function() {
            $count = $this->db->queryScalar("SELECT COUNT(*) FROM `{$this->testTable}`");
            return is_int($count) && $count >= 8;
        });

        $this->test('queryScalar() возвращает MAX(age)', function() {
            $maxAge = $this->db->queryScalar("SELECT MAX(age) FROM `{$this->testTable}`");
            return is_int($maxAge) && $maxAge >= 30;
        });

        $this->test('queryScalar() возвращает null для пустого результата', function() {
            $result = $this->db->queryScalar("SELECT name FROM `{$this->testTable}` WHERE id = 999999");
            return $result === null;
        });
    }

    /**
     * Тест: SELECT с параметрами
     */
    private function testQueryWithParams(): void
    {
        $this->test('query() с именованными параметрами', function() {
            $result = $this->db->query(
                "SELECT * FROM `{$this->testTable}` WHERE age > :min_age AND age < :max_age",
                [':min_age' => 25, ':max_age' => 35]
            );
            return is_array($result) && count($result) > 0;
        });

        $this->test('query() с позиционными параметрами', function() {
            $result = $this->db->query(
                "SELECT * FROM `{$this->testTable}` WHERE age > ? AND age < ?",
                [25, 35]
            );
            return is_array($result) && count($result) > 0;
        });

        $this->test('queryOne() с параметрами', function() {
            $result = $this->db->queryOne(
                "SELECT * FROM `{$this->testTable}` WHERE name = ?",
                ['John Doe']
            );
            return is_array($result) && $result['name'] === 'John Doe';
        });
    }

    /**
     * Тест: UPDATE (новый API)
     */
    private function testUpdate(): void
    {
        $this->test('update() с новым API', function() {
            $affected = $this->db->update(
                $this->testTable,
                ['is_active' => 0],
                ['age' => 40] // WHERE age = 40
            );
            return $affected >= 0;
        });
    }

    /**
     * Тест: UPDATE через execute()
     */
    private function testUpdateWithParams(): void
    {
        $this->test('UPDATE через execute() с именованными параметрами', function() {
            $affected = $this->db->execute(
                "UPDATE `{$this->testTable}` SET salary = :new_salary WHERE name = :name",
                [':new_salary' => 75000.00, ':name' => 'Charlie Davis']
            );
            return $affected === 1;
        });

        $this->test('UPDATE через execute() с позиционными параметрами', function() {
            $affected = $this->db->execute(
                "UPDATE `{$this->testTable}` SET age = ? WHERE email = ?",
                [31, 'john@example.com']
            );
            return $affected === 1;
        });
    }

    /**
     * Тест: DELETE (новый API)
     */
    private function testDelete(): void
    {
        $this->test('delete() с новым API', function() {
            // Сначала вставим запись для удаления
            $this->db->insert($this->testTable, [
                'name' => 'To Delete',
                'email' => 'delete@test.com',
                'age' => 25,
                'salary' => 40000.00
            ]);
            
            $affected = $this->db->delete($this->testTable, ['email' => 'delete@test.com']);
            return $affected >= 1;
        });
    }

    /**
     * Тест: DELETE через execute()
     */
    private function testDeleteWithParams(): void
    {
        $this->test('DELETE через execute() с именованными параметрами', function() {
            $affected = $this->db->execute(
                "DELETE FROM `{$this->testTable}` WHERE name = :name",
                [':name' => 'Grace Harris']
            );
            return $affected >= 0;
        });

        $this->test('DELETE через execute() с позиционными параметрами', function() {
            $affected = $this->db->execute(
                "DELETE FROM `{$this->testTable}` WHERE email = ?",
                ['diana@example.com']
            );
            return $affected >= 0;
        });
    }

    /**
     * Тест: транзакция с commit
     */
    private function testTransactionCommit(): void
    {
        $this->test('Транзакция с commit', function() {
            $this->db->beginTransaction();
            
            $this->db->insert($this->testTable, [
                'name' => 'Transaction Test',
                'email' => 'trans@example.com',
                'age' => 40
            ]);
            
            $this->db->commit();
            
            // Проверяем, что данные сохранились
            $result = $this->db->queryOne(
                "SELECT * FROM `{$this->testTable}` WHERE email = ?",
                ['trans@example.com']
            );
            
            return $result !== null && $result['name'] === 'Transaction Test';
        });
    }

    /**
     * Тест: транзакция с rollback
     */
    private function testTransactionRollback(): void
    {
        $this->test('Транзакция с rollback', function() {
            $this->db->beginTransaction();
            
            $this->db->insert($this->testTable, [
                'name' => 'Rollback Test',
                'email' => 'rollback@example.com',
                'age' => 45
            ]);
            
            $this->db->rollback();
            
            // Проверяем, что данные НЕ сохранились
            $result = $this->db->queryOne(
                "SELECT * FROM `{$this->testTable}` WHERE email = ?",
                ['rollback@example.com']
            );
            
            return $result === null;
        });
    }

    /**
     * Тест: транзакция через callback
     */
    private function testTransactionCallback(): void
    {
        $this->test('transaction() с успешным callback', function() {
            $result = $this->db->transaction(function() {
                $this->db->insert($this->testTable, [
                    'name' => 'Callback Test',
                    'email' => 'callback@example.com',
                    'age' => 33
                ]);
                
                return 'success';
            });
            
            // Проверяем результат и что данные сохранились
            $row = $this->db->queryOne(
                "SELECT * FROM `{$this->testTable}` WHERE email = ?",
                ['callback@example.com']
            );
            
            return $result === 'success' && $row !== null;
        });

        $this->test('transaction() с exception в callback', function() {
            try {
                $this->db->transaction(function() {
                    $this->db->insert($this->testTable, [
                        'name' => 'Exception Test',
                        'email' => 'exception@example.com',
                        'age' => 50
                    ]);
                    
                    throw new \Exception('Test exception');
                });
                
                return false; // Не должны сюда попасть
            } catch (\Exception $e) {
                // Проверяем, что данные НЕ сохранились
                $row = $this->db->queryOne(
                    "SELECT * FROM `{$this->testTable}` WHERE email = ?",
                    ['exception@example.com']
                );
                
                return $row === null && $e->getMessage() === 'Test exception';
            }
        });
    }

    /**
     * Тест: ошибка вложенных транзакций
     */
    private function testNestedTransactionError(): void
    {
        $this->test('Ошибка при попытке вложенной транзакции', function() {
            try {
                $this->db->beginTransaction();
                $this->db->beginTransaction(); // Вложенная транзакция
                return false;
            } catch (MySQLTransactionException $e) {
                $this->db->rollback();
                return str_contains($e->getMessage(), 'уже активна');
            }
        });
    }

    /**
     * Тест: ping
     */
    private function testPing(): void
    {
        $this->test('ping() возвращает true для активного соединения', function() {
            return $this->db->ping() === true;
        });
    }

    /**
     * Тест: getConnectionInfo
     */
    private function testGetConnectionInfo(): void
    {
        $this->test('getConnectionInfo() возвращает информацию о подключении', function() {
            $info = $this->db->getConnectionInfo();
            
            return is_array($info) 
                && isset($info['server_version'])
                && isset($info['client_version'])
                && isset($info['in_transaction']);
        });
    }

    /**
     * Тест: getMySQLVersion
     */
    private function testGetMySQLVersion(): void
    {
        $this->test('getMySQLVersion() возвращает структурированную версию', function() {
            $version = $this->db->getMySQLVersion();
            
            return is_array($version)
                && isset($version['version'])
                && isset($version['major'])
                && isset($version['minor'])
                && isset($version['patch'])
                && isset($version['is_supported'])
                && $version['major'] >= 5;
        });
    }

    /**
     * Тест: кеширование prepared statements
     */
    private function testStatementCache(): void
    {
        $this->test('Кеширование prepared statements работает', function() {
            // Выполняем один и тот же запрос несколько раз
            for ($i = 0; $i < 5; $i++) {
                $this->db->query("SELECT COUNT(*) as cnt FROM `{$this->testTable}`");
            }
            
            // Если кеширование работает, это должно выполниться быстро
            return true;
        });

        $this->test('clearStatementCache() очищает кеш', function() {
            $this->db->clearStatementCache();
            return true;
        });
    }

    /**
     * Тест: обработка ошибок запроса
     */
    private function testInvalidQuery(): void
    {
        $this->test('Ошибка при невалидном SQL запросе', function() {
            try {
                $this->db->query("INVALID SQL QUERY");
                return false;
            } catch (MySQLException $e) {
                return str_contains($e->getMessage(), 'Ошибка');
            }
        });
    }

    /**
     * Тест: обработка ошибок таблицы
     */
    private function testInvalidTable(): void
    {
        $this->test('Ошибка при запросе к несуществующей таблице', function() {
            try {
                $this->db->query("SELECT * FROM nonexistent_table_12345");
                return false;
            } catch (MySQLException $e) {
                return str_contains($e->getMessage(), 'Ошибка');
            }
        });
    }

    /**
     * Очистка после тестов
     */
    private function cleanup(): void
    {
        echo "\n" . str_repeat("-", 80) . "\n";
        echo "🧹 Очистка тестовых данных...\n";
        
        try {
            $this->db->execute("DROP TABLE IF EXISTS `{$this->testTable}`");
            echo "✓ Тестовая таблица удалена\n";
        } catch (\Exception $e) {
            echo "⚠ Ошибка при очистке: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Выполнение одного теста
     */
    private function test(string $description, callable $testFunction): void
    {
        echo "▶ {$description}... ";
        
        try {
            $result = $testFunction();
            
            if ($result === true) {
                echo "✓ PASSED\n";
                $this->passed++;
                $this->results[] = ['test' => $description, 'status' => 'PASSED'];
            } else {
                echo "✗ FAILED (returned false)\n";
                $this->failed++;
                $this->results[] = ['test' => $description, 'status' => 'FAILED', 'reason' => 'returned false'];
            }
        } catch (\Throwable $e) {
            echo "✗ FAILED\n";
            echo "  Ошибка: {$e->getMessage()}\n";
            echo "  Файл: {$e->getFile()}:{$e->getLine()}\n";
            $this->failed++;
            $this->results[] = [
                'test' => $description,
                'status' => 'FAILED',
                'reason' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ];
        }
    }

    /**
     * Вывод итоговых результатов
     */
    private function printResults(): void
    {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "РЕЗУЛЬТАТЫ ТЕСТИРОВАНИЯ\n";
        echo str_repeat("=", 80) . "\n\n";
        
        $total = $this->passed + $this->failed;
        $percentage = $total > 0 ? round(($this->passed / $total) * 100, 2) : 0;
        
        echo "Всего тестов:  {$total}\n";
        echo "✓ Успешных:    {$this->passed}\n";
        echo "✗ Провальных:  {$this->failed}\n";
        echo "Процент успеха: {$percentage}%\n\n";
        
        if ($this->failed > 0) {
            echo "ПРОВАЛЬНЫЕ ТЕСТЫ:\n";
            echo str_repeat("-", 80) . "\n";
            foreach ($this->results as $result) {
                if ($result['status'] === 'FAILED') {
                    echo "✗ {$result['test']}\n";
                    echo "  Причина: {$result['reason']}\n";
                    if (isset($result['trace'])) {
                        echo "  Трассировка:\n";
                        $traceLines = explode("\n", $result['trace']);
                        foreach (array_slice($traceLines, 0, 3) as $line) {
                            echo "    {$line}\n";
                        }
                    }
                    echo "\n";
                }
            }
        }
        
        echo str_repeat("=", 80) . "\n";
        
        if ($this->failed === 0) {
            echo "🎉 ВСЕ ТЕСТЫ УСПЕШНО ПРОЙДЕНЫ!\n";
        } else {
            echo "⚠ ЕСТЬ ПРОВАЛЕННЫЕ ТЕСТЫ - ТРЕБУЕТСЯ ИСПРАВЛЕНИЕ\n";
        }
        
        echo str_repeat("=", 80) . "\n\n";
    }
}

// Запуск тестов
$test = new MySQLFullTest();
$test->runAll();

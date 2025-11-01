<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Component\MySQL;
use App\Component\Logger;

/**
 * Тест новых хелперов класса MySQL
 */
class MySQLHelpersTest
{
    private MySQL $db;
    private Logger $logger;
    private string $testTable = 'test_mysql_helpers';
    private int $passed = 0;
    private int $failed = 0;

    public function __construct()
    {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "ТЕСТ НОВЫХ ХЕЛПЕРОВ MySQL.class.php\n";
        echo str_repeat("=", 80) . "\n\n";
    }

    public function runAll(): void
    {
        try {
            $this->setupLogger();
            $this->setupDatabase();
            $this->createTestTable();

            // Тесты новых хелперов
            $this->testQueryColumn();
            $this->testExists();
            $this->testCount();
            $this->testTruncate();
            $this->testTableExists();
            $this->testGetLastInsertId();
            $this->testInsertIgnore();
            $this->testReplace();
            $this->testUpsert();

            $this->cleanup();
        } catch (\Throwable $e) {
            echo "\n❌ КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage() . "\n";
            echo "Файл: {$e->getFile()}:{$e->getLine()}\n";
        } finally {
            $this->printResults();
        }
    }

    private function setupLogger(): void
    {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $this->logger = new Logger([
            'directory' => $logDir,
            'file_name' => 'mysql_helpers_test.log',
            'max_file_size' => 10,
            'max_files' => 5,
        ]);

        echo "✓ Логгер настроен\n\n";
    }

    private function setupDatabase(): void
    {
        echo "📊 Подключение к MySQL...\n";
        
        $config = [
            'host' => 'localhost',
            'database' => 'test_database',
            'username' => 'root',
            'password' => '',
        ];

        $this->db = new MySQL($config, $this->logger);
        echo "✓ Подключение установлено\n\n";
    }

    private function createTestTable(): void
    {
        $this->db->execute("DROP TABLE IF EXISTS `{$this->testTable}`");
        
        $sql = "CREATE TABLE `{$this->testTable}` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE,
            age INT,
            active BOOLEAN DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $this->db->execute($sql);

        // Вставляем тестовые данные
        $this->db->insert(
            "INSERT INTO `{$this->testTable}` (name, email, age, active) VALUES (?, ?, ?, ?)",
            ['Alice', 'alice@test.com', 25, 1]
        );
        $this->db->insert(
            "INSERT INTO `{$this->testTable}` (name, email, age, active) VALUES (?, ?, ?, ?)",
            ['Bob', 'bob@test.com', 30, 1]
        );
        $this->db->insert(
            "INSERT INTO `{$this->testTable}` (name, email, age, active) VALUES (?, ?, ?, ?)",
            ['Charlie', 'charlie@test.com', 35, 0]
        );

        echo "✓ Тестовая таблица создана и заполнена\n\n";
    }

    private function testQueryColumn(): void
    {
        $this->test('queryColumn() возвращает массив значений', function() {
            $names = $this->db->queryColumn("SELECT name FROM `{$this->testTable}` ORDER BY id");
            return is_array($names) && count($names) === 3 && $names[0] === 'Alice';
        });

        $this->test('queryColumn() с параметрами', function() {
            $ids = $this->db->queryColumn(
                "SELECT id FROM `{$this->testTable}` WHERE active = ?",
                [1]
            );
            return is_array($ids) && count($ids) === 2;
        });

        $this->test('queryColumn() возвращает пустой массив', function() {
            $result = $this->db->queryColumn("SELECT id FROM `{$this->testTable}` WHERE id > 1000");
            return is_array($result) && count($result) === 0;
        });
    }

    private function testExists(): void
    {
        $this->test('exists() возвращает true для существующей записи', function() {
            return $this->db->exists(
                "SELECT 1 FROM `{$this->testTable}` WHERE email = ?",
                ['alice@test.com']
            ) === true;
        });

        $this->test('exists() возвращает false для несуществующей записи', function() {
            return $this->db->exists(
                "SELECT 1 FROM `{$this->testTable}` WHERE email = ?",
                ['nonexistent@test.com']
            ) === false;
        });
    }

    private function testCount(): void
    {
        $this->test('count() без условий', function() {
            $count = $this->db->count($this->testTable);
            return $count === 3;
        });

        $this->test('count() с одним условием', function() {
            $count = $this->db->count($this->testTable, ['active' => 1]);
            return $count === 2;
        });

        $this->test('count() с несколькими условиями', function() {
            $count = $this->db->count($this->testTable, ['active' => 1, 'age' => 25]);
            return $count === 1;
        });
    }

    private function testTruncate(): void
    {
        $this->test('truncate() очищает таблицу', function() {
            // Создаем временную таблицу
            $tempTable = 'test_truncate_temp';
            $this->db->execute("DROP TABLE IF EXISTS `{$tempTable}`");
            $this->db->execute("CREATE TABLE `{$tempTable}` (id INT) ENGINE=InnoDB");
            $this->db->execute("INSERT INTO `{$tempTable}` VALUES (1), (2), (3)");
            
            // Очищаем
            $this->db->truncate($tempTable);
            
            // Проверяем
            $count = $this->db->count($tempTable);
            
            // Удаляем временную таблицу
            $this->db->execute("DROP TABLE `{$tempTable}`");
            
            return $count === 0;
        });
    }

    private function testTableExists(): void
    {
        $this->test('tableExists() возвращает true для существующей таблицы', function() {
            return $this->db->tableExists($this->testTable) === true;
        });

        $this->test('tableExists() возвращает false для несуществующей таблицы', function() {
            return $this->db->tableExists('nonexistent_table_12345') === false;
        });
    }

    private function testGetLastInsertId(): void
    {
        $this->test('getLastInsertId() возвращает ID после insert()', function() {
            $lastId = $this->db->insert(
                "INSERT INTO `{$this->testTable}` (name, email, age) VALUES (?, ?, ?)",
                ['Test User', 'test@example.com', 40]
            );
            
            $lastId2 = $this->db->getLastInsertId();
            
            return $lastId === $lastId2 && $lastId > 0;
        });
    }

    private function testInsertIgnore(): void
    {
        $this->test('insertIgnore() вставляет новую запись', function() {
            $lastId = $this->db->insertIgnore($this->testTable, [
                'name' => 'Diana',
                'email' => 'diana@test.com',
                'age' => 28,
            ]);
            
            return $lastId > 0;
        });

        $this->test('insertIgnore() игнорирует дубликат', function() {
            // Пытаемся вставить дубликат email
            $lastId = $this->db->insertIgnore($this->testTable, [
                'name' => 'Diana Duplicate',
                'email' => 'diana@test.com', // Дубликат
                'age' => 29,
            ]);
            
            // lastInsertId будет 0, так как запись не была вставлена
            return $lastId === 0;
        });
    }

    private function testReplace(): void
    {
        $this->test('replace() вставляет новую запись', function() {
            $lastId = $this->db->replace($this->testTable, [
                'name' => 'Eve',
                'email' => 'eve@test.com',
                'age' => 32,
            ]);
            
            return $lastId > 0;
        });

        $this->test('replace() заменяет существующую запись', function() {
            // Получаем текущий возраст Eve
            $before = $this->db->queryOne(
                "SELECT age FROM `{$this->testTable}` WHERE email = ?",
                ['eve@test.com']
            );
            
            // Заменяем запись
            $this->db->replace($this->testTable, [
                'name' => 'Eve Updated',
                'email' => 'eve@test.com',
                'age' => 33, // Новый возраст
            ]);
            
            // Проверяем обновление
            $after = $this->db->queryOne(
                "SELECT age FROM `{$this->testTable}` WHERE email = ?",
                ['eve@test.com']
            );
            
            return $before['age'] === 32 && $after['age'] === 33;
        });
    }

    private function testUpsert(): void
    {
        $this->test('upsert() вставляет новую запись', function() {
            $lastId = $this->db->upsert($this->testTable, [
                'name' => 'Frank',
                'email' => 'frank@test.com',
                'age' => 45,
            ]);
            
            $row = $this->db->queryOne(
                "SELECT * FROM `{$this->testTable}` WHERE email = ?",
                ['frank@test.com']
            );
            
            return $lastId > 0 && $row !== null && $row['name'] === 'Frank';
        });

        $this->test('upsert() обновляет существующую запись', function() {
            // Первая вставка
            $this->db->upsert($this->testTable, [
                'name' => 'Grace',
                'email' => 'grace@test.com',
                'age' => 27,
            ]);
            
            // Обновление через upsert
            $this->db->upsert($this->testTable, [
                'name' => 'Grace Updated',
                'email' => 'grace@test.com', // Тот же email (UNIQUE)
                'age' => 28,
            ]);
            
            $row = $this->db->queryOne(
                "SELECT * FROM `{$this->testTable}` WHERE email = ?",
                ['grace@test.com']
            );
            
            return $row !== null && $row['name'] === 'Grace Updated' && $row['age'] === 28;
        });

        $this->test('upsert() с отдельными данными для обновления', function() {
            $this->db->upsert(
                $this->testTable,
                [
                    'name' => 'Henry',
                    'email' => 'henry@test.com',
                    'age' => 50,
                ],
                [
                    'age' => 51, // Обновляем только возраст
                ]
            );
            
            // Пытаемся вставить дубликат с обновлением только возраста
            $this->db->upsert(
                $this->testTable,
                [
                    'name' => 'Henry New Name',
                    'email' => 'henry@test.com',
                    'age' => 52,
                ],
                [
                    'age' => 52, // Обновляем только возраст, имя остается прежним
                ]
            );
            
            $row = $this->db->queryOne(
                "SELECT * FROM `{$this->testTable}` WHERE email = ?",
                ['henry@test.com']
            );
            
            // Имя должно остаться 'Henry', а возраст обновиться до 52
            return $row !== null && $row['name'] === 'Henry' && $row['age'] === 52;
        });
    }

    private function cleanup(): void
    {
        echo "\n" . str_repeat("-", 80) . "\n";
        echo "🧹 Очистка...\n";
        
        try {
            $this->db->execute("DROP TABLE IF EXISTS `{$this->testTable}`");
            echo "✓ Очистка завершена\n";
        } catch (\Exception $e) {
            echo "⚠ Ошибка при очистке: " . $e->getMessage() . "\n";
        }
    }

    private function test(string $description, callable $testFunction): void
    {
        echo "▶ {$description}... ";
        
        try {
            $result = $testFunction();
            
            if ($result === true) {
                echo "✓ PASSED\n";
                $this->passed++;
            } else {
                echo "✗ FAILED (returned false)\n";
                $this->failed++;
            }
        } catch (\Throwable $e) {
            echo "✗ FAILED\n";
            echo "  Ошибка: {$e->getMessage()}\n";
            echo "  Файл: {$e->getFile()}:{$e->getLine()}\n";
            $this->failed++;
        }
    }

    private function printResults(): void
    {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "РЕЗУЛЬТАТЫ ТЕСТИРОВАНИЯ ХЕЛПЕРОВ\n";
        echo str_repeat("=", 80) . "\n\n";
        
        $total = $this->passed + $this->failed;
        $percentage = $total > 0 ? round(($this->passed / $total) * 100, 2) : 0;
        
        echo "Всего тестов:  {$total}\n";
        echo "✓ Успешных:    {$this->passed}\n";
        echo "✗ Провальных:  {$this->failed}\n";
        echo "Процент успеха: {$percentage}%\n\n";
        
        if ($this->failed === 0) {
            echo "🎉 ВСЕ ТЕСТЫ ХЕЛПЕРОВ УСПЕШНО ПРОЙДЕНЫ!\n";
        } else {
            echo "⚠ ЕСТЬ ПРОВАЛЕННЫЕ ТЕСТЫ\n";
        }
        
        echo str_repeat("=", 80) . "\n\n";
    }
}

$test = new MySQLHelpersTest();
$test->runAll();

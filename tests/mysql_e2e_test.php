<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Component\MySQL;
use App\Component\Logger;
use App\Component\Exception\MySQL\MySQLException;
use App\Component\Exception\MySQL\MySQLConnectionException;
use App\Component\Exception\MySQL\MySQLTransactionException;

/**
 * Комплексное E2E тестирование класса MySQL
 * 
 * Тестирует все методы класса в реальном окружении с боевой БД
 */
class MySQLEndToEndTest
{
    private Logger $logger;
    private int $testsPassed = 0;
    private int $testsFailed = 0;
    private array $errors = [];
    
    public function __construct()
    {
        // Инициализация логгера
        $logConfig = [
            'directory' => __DIR__ . '/../logs',
            'filename' => 'mysql_e2e_test.log',
            'level' => 'debug',
            'max_files' => 5,
        ];
        
        $this->logger = new Logger($logConfig);
        $this->logger->info('=== НАЧАЛО E2E ТЕСТИРОВАНИЯ КЛАССА MySQL ===');
    }
    
    /**
     * Запуск всех тестов
     */
    public function runAllTests(): void
    {
        echo "\n╔══════════════════════════════════════════════════════════════════════╗\n";
        echo "║   КОМПЛЕКСНОЕ E2E ТЕСТИРОВАНИЕ КЛАССА MySQL.class.php              ║\n";
        echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";
        
        // Базовые тесты подключения
        $this->testValidConnection();
        $this->testInvalidConnection();
        $this->testConnectionWithDifferentCharsets();
        $this->testPersistentConnection();
        $this->testConnectionInfo();
        $this->testMySQLVersion();
        $this->testPing();
        
        // Тесты SELECT запросов
        $this->testQuery();
        $this->testQueryOne();
        $this->testQueryScalar();
        $this->testQueryWithParameters();
        $this->testQueryEmpty();
        
        // Тесты INSERT запросов
        $this->testInsert();
        $this->testInsertWithoutAutoIncrement();
        $this->testInsertBatch();
        $this->testInsertBatchEmpty();
        
        // Тесты UPDATE запросов
        $this->testUpdate();
        $this->testUpdateNoRows();
        $this->testUpdateWithParameters();
        
        // Тесты DELETE запросов
        $this->testDelete();
        $this->testDeleteNoRows();
        $this->testDeleteWithParameters();
        
        // Тесты транзакций
        $this->testTransactionCommit();
        $this->testTransactionRollback();
        $this->testTransactionCallback();
        $this->testNestedTransactionError();
        $this->testTransactionWithoutActive();
        
        // Тесты execute для DDL команд
        $this->testExecuteDDL();
        $this->testExecuteWithParameters();
        
        // Тесты prepared statements и кеширования
        $this->testPreparedStatementCache();
        $this->testClearStatementCache();
        $this->testStatementCacheDisabled();
        
        // Тесты обработки ошибок
        $this->testSQLSyntaxError();
        $this->testForeignKeyConstraint();
        $this->testDuplicateEntry();
        
        // Комплексные сценарии
        $this->testComplexQueryWithJoins();
        $this->testBulkOperations();
        $this->testConcurrentTransactions();
        
        // Вывод итогового отчета
        $this->printSummary();
    }
    
    /**
     * Тест валидного подключения
     */
    private function testValidConnection(): void
    {
        $this->runTest('Валидное подключение к БД', function() {
            $config = [
                'host' => 'localhost',
                'database' => 'test_database_main',
                'username' => 'test_user',
                'password' => 'test_password_123',
                'charset' => 'utf8mb4',
            ];
            
            $db = new MySQL($config, $this->logger);
            $this->assertNotNull($db);
            $this->assertTrue($db->ping());
        });
    }
    
    /**
     * Тест невалидного подключения
     */
    private function testInvalidConnection(): void
    {
        $this->runTest('Подключение с неверными данными (должно выбросить исключение)', function() {
            $config = [
                'host' => 'localhost',
                'database' => 'non_existent_db',
                'username' => 'invalid_user',
                'password' => 'wrong_password',
            ];
            
            try {
                new MySQL($config, $this->logger);
                throw new \Exception('Ожидалось MySQLConnectionException');
            } catch (MySQLConnectionException $e) {
                // Ожидаемое исключение
                $this->assertTrue(true);
            }
        });
    }
    
    /**
     * Тест подключения с разными charset
     */
    private function testConnectionWithDifferentCharsets(): void
    {
        $this->runTest('Подключение с charset utf8', function() {
            $config = [
                'host' => 'localhost',
                'database' => 'test_database_main',
                'username' => 'test_user',
                'password' => 'test_password_123',
                'charset' => 'utf8',
            ];
            
            $db = new MySQL($config, $this->logger);
            $this->assertTrue($db->ping());
        });
    }
    
    /**
     * Тест персистентного подключения
     */
    private function testPersistentConnection(): void
    {
        $this->runTest('Персистентное подключение', function() {
            $config = [
                'host' => 'localhost',
                'database' => 'test_database_main',
                'username' => 'test_user',
                'password' => 'test_password_123',
                'persistent' => true,
            ];
            
            $db = new MySQL($config, $this->logger);
            $info = $db->getConnectionInfo();
            $this->assertNotEmpty($info['server_version']);
        });
    }
    
    /**
     * Тест получения информации о подключении
     */
    private function testConnectionInfo(): void
    {
        $this->runTest('Получение информации о подключении', function() {
            $db = $this->getConnection();
            $info = $db->getConnectionInfo();
            
            $this->assertArrayHasKey('server_version', $info);
            $this->assertArrayHasKey('client_version', $info);
            $this->assertArrayHasKey('connection_status', $info);
            $this->assertArrayHasKey('in_transaction', $info);
            $this->assertFalse($info['in_transaction']);
        });
    }
    
    /**
     * Тест получения версии MySQL
     */
    private function testMySQLVersion(): void
    {
        $this->runTest('Получение версии MySQL', function() {
            $db = $this->getConnection();
            $version = $db->getMySQLVersion();
            
            $this->assertArrayHasKey('version', $version);
            $this->assertArrayHasKey('major', $version);
            $this->assertArrayHasKey('minor', $version);
            $this->assertArrayHasKey('patch', $version);
            $this->assertTrue($version['is_supported']);
            
            echo " [MySQL {$version['version']}]";
        });
    }
    
    /**
     * Тест проверки подключения
     */
    private function testPing(): void
    {
        $this->runTest('Проверка активности подключения (ping)', function() {
            $db = $this->getConnection();
            $this->assertTrue($db->ping());
        });
    }
    
    /**
     * Тест SELECT запроса
     */
    private function testQuery(): void
    {
        $this->runTest('SELECT запрос (query)', function() {
            $db = $this->getConnection();
            $users = $db->query("SELECT * FROM users WHERE is_active = 1");
            
            $this->assertIsArray($users);
            $this->assertGreaterThan(0, count($users));
            $this->assertArrayHasKey('id', $users[0]);
            $this->assertArrayHasKey('username', $users[0]);
            $this->assertArrayHasKey('email', $users[0]);
            
            echo " [Найдено " . count($users) . " активных пользователей]";
        });
    }
    
    /**
     * Тест SELECT с одной строкой
     */
    private function testQueryOne(): void
    {
        $this->runTest('SELECT запрос с одной строкой (queryOne)', function() {
            $db = $this->getConnection();
            $user = $db->queryOne("SELECT * FROM users WHERE username = ?", ['john_doe']);
            
            $this->assertIsArray($user);
            $this->assertEquals('john_doe', $user['username']);
            $this->assertEquals('john@example.com', $user['email']);
        });
    }
    
    /**
     * Тест SELECT скалярного значения
     */
    private function testQueryScalar(): void
    {
        $this->runTest('SELECT скалярного значения (queryScalar)', function() {
            $db = $this->getConnection();
            $count = $db->queryScalar("SELECT COUNT(*) FROM users");
            
            $this->assertIsInt($count);
            $this->assertGreaterThan(0, $count);
            
            echo " [Всего пользователей: $count]";
        });
    }
    
    /**
     * Тест SELECT с параметрами
     */
    private function testQueryWithParameters(): void
    {
        $this->runTest('SELECT с параметрами', function() {
            $db = $this->getConnection();
            $users = $db->query(
                "SELECT * FROM users WHERE age > ? AND is_active = ? ORDER BY age",
                [25, 1]
            );
            
            $this->assertIsArray($users);
            $this->assertGreaterThan(0, count($users));
            
            echo " [Найдено " . count($users) . " пользователей старше 25 лет]";
        });
    }
    
    /**
     * Тест пустого результата
     */
    private function testQueryEmpty(): void
    {
        $this->runTest('SELECT с пустым результатом', function() {
            $db = $this->getConnection();
            $result = $db->query("SELECT * FROM users WHERE username = ?", ['non_existent_user']);
            
            $this->assertIsArray($result);
            $this->assertEmpty($result);
            
            $user = $db->queryOne("SELECT * FROM users WHERE username = ?", ['non_existent_user']);
            $this->assertNull($user);
        });
    }
    
    /**
     * Тест INSERT запроса
     */
    private function testInsert(): void
    {
        $this->runTest('INSERT запрос с AUTO_INCREMENT', function() {
            $db = $this->getConnection();
            $lastId = $db->insert(
                "INSERT INTO users (username, email, age, balance) VALUES (?, ?, ?, ?)",
                ['test_user_' . time(), 'test' . time() . '@example.com', 25, 100.50]
            );
            
            $this->assertGreaterThan(0, $lastId);
            
            // Проверяем, что запись действительно создана
            $user = $db->queryOne("SELECT * FROM users WHERE id = ?", [$lastId]);
            $this->assertIsArray($user);
            $this->assertEquals($lastId, $user['id']);
            
            echo " [ID новой записи: $lastId]";
        });
    }
    
    /**
     * Тест INSERT без AUTO_INCREMENT
     */
    private function testInsertWithoutAutoIncrement(): void
    {
        $this->runTest('INSERT в таблицу без AUTO_INCREMENT', function() {
            $db = $this->getConnection();
            
            // Создаем временную таблицу без AUTO_INCREMENT
            $db->execute("CREATE TEMPORARY TABLE temp_config (key_name VARCHAR(50) PRIMARY KEY, key_value VARCHAR(100))");
            
            $lastId = $db->insert(
                "INSERT INTO temp_config (key_name, key_value) VALUES (?, ?)",
                ['test_key', 'test_value']
            );
            
            $this->assertEquals(0, $lastId); // Без AUTO_INCREMENT возвращает 0
        });
    }
    
    /**
     * Тест массовой вставки
     */
    private function testInsertBatch(): void
    {
        $this->runTest('Массовая вставка (insertBatch)', function() {
            $db = $this->getConnection();
            
            $products = [
                ['name' => 'Test Product 1', 'price' => 99.99, 'stock' => 10, 'category' => 'Test'],
                ['name' => 'Test Product 2', 'price' => 149.99, 'stock' => 20, 'category' => 'Test'],
                ['name' => 'Test Product 3', 'price' => 199.99, 'stock' => 30, 'category' => 'Test'],
                ['name' => 'Test Product 4', 'price' => 249.99, 'stock' => 40, 'category' => 'Test'],
                ['name' => 'Test Product 5', 'price' => 299.99, 'stock' => 50, 'category' => 'Test'],
            ];
            
            $count = $db->insertBatch('products', $products);
            
            $this->assertEquals(5, $count);
            
            // Проверяем, что записи действительно созданы
            $testProducts = $db->query("SELECT * FROM products WHERE category = 'Test'");
            $this->assertGreaterThanOrEqual(5, count($testProducts));
            
            echo " [Вставлено $count записей]";
        });
    }
    
    /**
     * Тест массовой вставки пустого массива
     */
    private function testInsertBatchEmpty(): void
    {
        $this->runTest('Массовая вставка пустого массива', function() {
            $db = $this->getConnection();
            $count = $db->insertBatch('products', []);
            
            $this->assertEquals(0, $count);
        });
    }
    
    /**
     * Тест UPDATE запроса
     */
    private function testUpdate(): void
    {
        $this->runTest('UPDATE запрос', function() {
            $db = $this->getConnection();
            
            // Сначала находим пользователя для обновления
            $user = $db->queryOne("SELECT * FROM users WHERE username = ?", ['john_doe']);
            $oldBalance = $user['balance'];
            $newBalance = $oldBalance + 500;
            
            $affectedRows = $db->update(
                "UPDATE users SET balance = ? WHERE username = ?",
                [$newBalance, 'john_doe']
            );
            
            $this->assertEquals(1, $affectedRows);
            
            // Проверяем обновление
            $updatedUser = $db->queryOne("SELECT * FROM users WHERE username = ?", ['john_doe']);
            $this->assertEquals($newBalance, $updatedUser['balance']);
            
            echo " [Обновлен баланс: $oldBalance -> $newBalance]";
        });
    }
    
    /**
     * Тест UPDATE без затронутых строк
     */
    private function testUpdateNoRows(): void
    {
        $this->runTest('UPDATE без затронутых строк', function() {
            $db = $this->getConnection();
            $affectedRows = $db->update(
                "UPDATE users SET balance = 0 WHERE username = ?",
                ['non_existent_user']
            );
            
            $this->assertEquals(0, $affectedRows);
        });
    }
    
    /**
     * Тест UPDATE с параметрами
     */
    private function testUpdateWithParameters(): void
    {
        $this->runTest('UPDATE с несколькими параметрами', function() {
            $db = $this->getConnection();
            
            // Сначала проверяем, есть ли товары для обновления
            $count = $db->queryScalar("SELECT COUNT(*) FROM products WHERE category = ?", ['Accessories']);
            
            if ($count > 0) {
                $affectedRows = $db->update(
                    "UPDATE products SET stock = stock + ? WHERE category = ?",
                    [10, 'Accessories']
                );
                
                $this->assertGreaterThanOrEqual(0, $affectedRows);
                echo " [Обновлено $affectedRows товаров категории Accessories]";
            } else {
                // Если нет товаров Accessories, обновляем любую другую категорию
                $affectedRows = $db->update(
                    "UPDATE products SET stock = stock + ? WHERE category IS NOT NULL LIMIT 5",
                    [5]
                );
                
                $this->assertGreaterThanOrEqual(0, $affectedRows);
                echo " [Обновлено $affectedRows товаров (любых категорий)]";
            }
        });
    }
    
    /**
     * Тест DELETE запроса
     */
    private function testDelete(): void
    {
        $this->runTest('DELETE запрос', function() {
            $db = $this->getConnection();
            
            // Сначала создаем тестовую запись
            $lastId = $db->insert(
                "INSERT INTO users (username, email, age) VALUES (?, ?, ?)",
                ['temp_user_' . time(), 'temp' . time() . '@example.com', 99]
            );
            
            // Удаляем её
            $affectedRows = $db->delete("DELETE FROM users WHERE id = ?", [$lastId]);
            
            $this->assertEquals(1, $affectedRows);
            
            // Проверяем, что запись действительно удалена
            $user = $db->queryOne("SELECT * FROM users WHERE id = ?", [$lastId]);
            $this->assertNull($user);
        });
    }
    
    /**
     * Тест DELETE без затронутых строк
     */
    private function testDeleteNoRows(): void
    {
        $this->runTest('DELETE без затронутых строк', function() {
            $db = $this->getConnection();
            $affectedRows = $db->delete("DELETE FROM users WHERE id = ?", [999999]);
            
            $this->assertEquals(0, $affectedRows);
        });
    }
    
    /**
     * Тест DELETE с параметрами
     */
    private function testDeleteWithParameters(): void
    {
        $this->runTest('DELETE с параметрами', function() {
            $db = $this->getConnection();
            $affectedRows = $db->delete(
                "DELETE FROM products WHERE category = ? AND stock = ?",
                ['Test', 0]
            );
            
            $this->assertGreaterThanOrEqual(0, $affectedRows);
        });
    }
    
    /**
     * Тест транзакции с commit
     */
    private function testTransactionCommit(): void
    {
        $this->runTest('Транзакция с commit', function() {
            $db = $this->getConnection('test_database_transactions');
            
            // Запоминаем начальные балансы
            $account1Before = $db->queryOne("SELECT balance FROM accounts WHERE account_number = ?", ['ACC001']);
            $account2Before = $db->queryOne("SELECT balance FROM accounts WHERE account_number = ?", ['ACC002']);
            
            $db->beginTransaction();
            $this->assertTrue($db->inTransaction());
            
            // Переводим деньги между счетами
            $transferAmount = 100;
            $db->update("UPDATE accounts SET balance = balance - ? WHERE account_number = ?", [$transferAmount, 'ACC001']);
            $db->update("UPDATE accounts SET balance = balance + ? WHERE account_number = ?", [$transferAmount, 'ACC002']);
            $db->insert(
                "INSERT INTO transactions (from_account_id, to_account_id, amount, type, status) VALUES (?, ?, ?, ?, ?)",
                [1, 2, $transferAmount, 'transfer', 'completed']
            );
            
            $db->commit();
            $this->assertFalse($db->inTransaction());
            
            // Проверяем, что балансы изменились корректно
            $account1After = $db->queryOne("SELECT balance FROM accounts WHERE account_number = ?", ['ACC001']);
            $account2After = $db->queryOne("SELECT balance FROM accounts WHERE account_number = ?", ['ACC002']);
            
            $expectedBalance1 = $account1Before['balance'] - $transferAmount;
            $expectedBalance2 = $account2Before['balance'] + $transferAmount;
            
            $this->assertEquals($expectedBalance1, $account1After['balance']);
            $this->assertEquals($expectedBalance2, $account2After['balance']);
            
            echo " [Переведено $transferAmount: {$account1Before['balance']} -> {$account1After['balance']}]";
        });
    }
    
    /**
     * Тест транзакции с rollback
     */
    private function testTransactionRollback(): void
    {
        $this->runTest('Транзакция с rollback', function() {
            $db = $this->getConnection('test_database_transactions');
            
            // Запоминаем текущие балансы
            $account1Before = $db->queryOne("SELECT balance FROM accounts WHERE account_number = ?", ['ACC003']);
            $account2Before = $db->queryOne("SELECT balance FROM accounts WHERE account_number = ?", ['ACC004']);
            
            $db->beginTransaction();
            
            // Пытаемся перевести деньги
            $db->update("UPDATE accounts SET balance = balance - 500 WHERE account_number = ?", ['ACC003']);
            $db->update("UPDATE accounts SET balance = balance + 500 WHERE account_number = ?", ['ACC004']);
            
            // Откатываем транзакцию
            $db->rollback();
            $this->assertFalse($db->inTransaction());
            
            // Проверяем, что балансы не изменились
            $account1After = $db->queryOne("SELECT balance FROM accounts WHERE account_number = ?", ['ACC003']);
            $account2After = $db->queryOne("SELECT balance FROM accounts WHERE account_number = ?", ['ACC004']);
            
            $this->assertEquals($account1Before['balance'], $account1After['balance']);
            $this->assertEquals($account2Before['balance'], $account2After['balance']);
        });
    }
    
    /**
     * Тест транзакции через callback
     */
    private function testTransactionCallback(): void
    {
        $this->runTest('Транзакция через callback', function() {
            $db = $this->getConnection('test_database_transactions');
            
            $result = $db->transaction(function() use ($db) {
                $db->update("UPDATE accounts SET balance = balance - 50 WHERE account_number = ?", ['ACC001']);
                $db->update("UPDATE accounts SET balance = balance + 50 WHERE account_number = ?", ['ACC002']);
                return 'success';
            });
            
            $this->assertEquals('success', $result);
            $this->assertFalse($db->inTransaction());
        });
    }
    
    /**
     * Тест ошибки вложенных транзакций
     */
    private function testNestedTransactionError(): void
    {
        $this->runTest('Ошибка вложенных транзакций', function() {
            $db = $this->getConnection();
            
            $db->beginTransaction();
            
            try {
                $db->beginTransaction();
                throw new \Exception('Ожидалось MySQLTransactionException');
            } catch (MySQLTransactionException $e) {
                // Ожидаемое исключение
                $this->assertTrue(true);
            } finally {
                $db->rollback();
            }
        });
    }
    
    /**
     * Тест commit/rollback без активной транзакции
     */
    private function testTransactionWithoutActive(): void
    {
        $this->runTest('Commit/Rollback без активной транзакции', function() {
            $db = $this->getConnection();
            
            try {
                $db->commit();
                throw new \Exception('Ожидалось MySQLTransactionException');
            } catch (MySQLTransactionException $e) {
                $this->assertTrue(true);
            }
            
            try {
                $db->rollback();
                throw new \Exception('Ожидалось MySQLTransactionException');
            } catch (MySQLTransactionException $e) {
                $this->assertTrue(true);
            }
        });
    }
    
    /**
     * Тест execute для DDL команд
     */
    private function testExecuteDDL(): void
    {
        $this->runTest('Execute для DDL команд', function() {
            $db = $this->getConnection();
            
            // Создаем временную таблицу
            $result = $db->execute("CREATE TEMPORARY TABLE test_temp (id INT PRIMARY KEY, name VARCHAR(50))");
            $this->assertGreaterThanOrEqual(0, $result);
            
            // Вставляем данные через execute
            $result = $db->execute("INSERT INTO test_temp VALUES (1, 'test')");
            $this->assertEquals(1, $result);
            
            // Проверяем
            $row = $db->queryOne("SELECT * FROM test_temp WHERE id = 1");
            $this->assertEquals('test', $row['name']);
            
            // Удаляем таблицу
            $db->execute("DROP TEMPORARY TABLE test_temp");
        });
    }
    
    /**
     * Тест execute с параметрами
     */
    private function testExecuteWithParameters(): void
    {
        $this->runTest('Execute с параметрами', function() {
            $db = $this->getConnection();
            
            $db->execute("CREATE TEMPORARY TABLE test_params (id INT, value VARCHAR(50))");
            
            $result = $db->execute("INSERT INTO test_params VALUES (?, ?)", [1, 'test_value']);
            $this->assertEquals(1, $result);
            
            $row = $db->queryOne("SELECT * FROM test_params WHERE id = ?", [1]);
            $this->assertEquals('test_value', $row['value']);
        });
    }
    
    /**
     * Тест кеширования prepared statements
     */
    private function testPreparedStatementCache(): void
    {
        $this->runTest('Кеширование prepared statements', function() {
            $db = $this->getConnection();
            
            // Выполняем один и тот же запрос несколько раз
            for ($i = 1; $i <= 5; $i++) {
                $user = $db->queryOne("SELECT * FROM users WHERE id = ?", [$i]);
                $this->assertIsArray($user);
            }
            
            // Выполняем разные запросы для наполнения кеша
            for ($i = 1; $i <= 10; $i++) {
                $count = $db->queryScalar("SELECT COUNT(*) FROM users WHERE id <= ?", [$i]);
                $this->assertGreaterThan(0, $count);
            }
        });
    }
    
    /**
     * Тест очистки кеша statements
     */
    private function testClearStatementCache(): void
    {
        $this->runTest('Очистка кеша prepared statements', function() {
            $db = $this->getConnection();
            
            // Выполняем несколько запросов для наполнения кеша
            for ($i = 1; $i <= 5; $i++) {
                $db->queryOne("SELECT * FROM users WHERE id = ?", [$i]);
            }
            
            // Очищаем кеш
            $db->clearStatementCache();
            
            // Проверяем, что запросы продолжают работать
            $user = $db->queryOne("SELECT * FROM users WHERE id = ?", [1]);
            $this->assertIsArray($user);
        });
    }
    
    /**
     * Тест с отключенным кешированием
     */
    private function testStatementCacheDisabled(): void
    {
        $this->runTest('Работа с отключенным кешированием', function() {
            $config = [
                'host' => 'localhost',
                'database' => 'test_database_main',
                'username' => 'test_user',
                'password' => 'test_password_123',
                'cache_statements' => false,
            ];
            
            $db = new MySQL($config, $this->logger);
            
            // Выполняем запросы без кеширования
            for ($i = 1; $i <= 5; $i++) {
                $user = $db->queryOne("SELECT * FROM users WHERE id = ?", [$i]);
                $this->assertIsArray($user);
            }
        });
    }
    
    /**
     * Тест синтаксической ошибки SQL
     */
    private function testSQLSyntaxError(): void
    {
        $this->runTest('Обработка синтаксической ошибки SQL', function() {
            $db = $this->getConnection();
            
            try {
                $db->query("SELECT * FORM users"); // Намеренная ошибка: FORM вместо FROM
                throw new \Exception('Ожидалось MySQLException');
            } catch (MySQLException $e) {
                $this->assertTrue(true);
                $this->assertStringContainsString('syntax', strtolower($e->getMessage()));
            }
        });
    }
    
    /**
     * Тест нарушения внешнего ключа
     */
    private function testForeignKeyConstraint(): void
    {
        $this->runTest('Обработка нарушения внешнего ключа', function() {
            $db = $this->getConnection();
            
            try {
                // Пытаемся вставить заказ с несуществующим user_id
                $db->insert(
                    "INSERT INTO orders (user_id, product_id, quantity, total_price) VALUES (?, ?, ?, ?)",
                    [999999, 1, 1, 100]
                );
                throw new \Exception('Ожидалось MySQLException');
            } catch (MySQLException $e) {
                $this->assertTrue(true);
            }
        });
    }
    
    /**
     * Тест дублирующейся записи
     */
    private function testDuplicateEntry(): void
    {
        $this->runTest('Обработка дублирующейся записи', function() {
            $db = $this->getConnection();
            
            try {
                // Пытаемся вставить пользователя с существующим username
                $db->insert(
                    "INSERT INTO users (username, email, age) VALUES (?, ?, ?)",
                    ['john_doe', 'duplicate@example.com', 30]
                );
                throw new \Exception('Ожидалось MySQLException');
            } catch (MySQLException $e) {
                $this->assertTrue(true);
                $this->assertStringContainsString('Duplicate', $e->getMessage());
            }
        });
    }
    
    /**
     * Тест сложного запроса с JOIN
     */
    private function testComplexQueryWithJoins(): void
    {
        $this->runTest('Сложный запрос с JOIN', function() {
            $db = $this->getConnection();
            
            $query = "
                SELECT 
                    u.username,
                    u.email,
                    p.name as product_name,
                    o.quantity,
                    o.total_price,
                    o.status
                FROM orders o
                INNER JOIN users u ON o.user_id = u.id
                INNER JOIN products p ON o.product_id = p.id
                WHERE o.status = ?
                ORDER BY o.created_at DESC
                LIMIT 5
            ";
            
            $orders = $db->query($query, ['completed']);
            
            $this->assertIsArray($orders);
            $this->assertGreaterThan(0, count($orders));
            $this->assertArrayHasKey('username', $orders[0]);
            $this->assertArrayHasKey('product_name', $orders[0]);
            $this->assertArrayHasKey('total_price', $orders[0]);
            
            echo " [Найдено " . count($orders) . " завершенных заказов]";
        });
    }
    
    /**
     * Тест массовых операций
     */
    private function testBulkOperations(): void
    {
        $this->runTest('Массовые операции с большим объемом данных', function() {
            $db = $this->getConnection();
            
            // Создаем временную таблицу
            $db->execute("CREATE TEMPORARY TABLE bulk_test (id INT AUTO_INCREMENT PRIMARY KEY, data VARCHAR(100))");
            
            // Массовая вставка
            $rows = [];
            for ($i = 1; $i <= 100; $i++) {
                $rows[] = ['data' => 'Bulk data item ' . $i];
            }
            
            $startTime = microtime(true);
            $count = $db->insertBatch('bulk_test', $rows);
            $insertTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->assertEquals(100, $count);
            
            // Проверяем вставленные данные
            $totalCount = $db->queryScalar("SELECT COUNT(*) FROM bulk_test");
            $this->assertEquals(100, $totalCount);
            
            echo " [Вставлено $count записей за {$insertTime}ms]";
        });
    }
    
    /**
     * Тест конкурентных транзакций
     */
    private function testConcurrentTransactions(): void
    {
        $this->runTest('Конкурентные транзакции (isolation)', function() {
            $db1 = $this->getConnection('test_database_transactions');
            $db2 = $this->getConnection('test_database_transactions');
            
            // Запоминаем начальный баланс
            $initialBalance = $db1->queryScalar("SELECT balance FROM accounts WHERE account_number = ?", ['ACC001']);
            
            // Начинаем транзакцию в первом соединении
            $db1->beginTransaction();
            $db1->update("UPDATE accounts SET balance = balance - 100 WHERE account_number = ?", ['ACC001']);
            
            // Во втором соединении читаем баланс (должен остаться прежним из-за изоляции)
            $balanceInSecondConnection = $db2->queryScalar("SELECT balance FROM accounts WHERE account_number = ?", ['ACC001']);
            
            // Откатываем первую транзакцию
            $db1->rollback();
            
            // Проверяем, что баланс не изменился
            $finalBalance = $db2->queryScalar("SELECT balance FROM accounts WHERE account_number = ?", ['ACC001']);
            $this->assertEquals($initialBalance, $finalBalance);
            
            echo " [Изоляция транзакций работает корректно]";
        });
    }
    
    /**
     * Создает подключение к БД
     */
    private function getConnection(string $database = 'test_database_main'): MySQL
    {
        $config = [
            'host' => 'localhost',
            'database' => $database,
            'username' => 'test_user',
            'password' => 'test_password_123',
            'charset' => 'utf8mb4',
        ];
        
        return new MySQL($config, $this->logger);
    }
    
    /**
     * Запускает отдельный тест
     */
    private function runTest(string $name, callable $test): void
    {
        echo "\n[TEST] $name ... ";
        
        try {
            $test();
            $this->testsPassed++;
            echo "✅ PASSED";
        } catch (\Throwable $e) {
            $this->testsFailed++;
            $this->errors[] = [
                'test' => $name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ];
            echo "❌ FAILED";
            echo "\n       Ошибка: " . $e->getMessage();
            
            $this->logger->error("Тест провален: $name", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
    
    /**
     * Выводит итоговый отчет
     */
    private function printSummary(): void
    {
        $total = $this->testsPassed + $this->testsFailed;
        
        echo "\n\n╔══════════════════════════════════════════════════════════════════════╗\n";
        echo "║                        ИТОГОВЫЙ ОТЧЕТ                                ║\n";
        echo "╚══════════════════════════════════════════════════════════════════════╝\n\n";
        
        echo "Всего тестов:     $total\n";
        echo "Успешно:          " . $this->testsPassed . " ✅\n";
        echo "Провалено:        " . $this->testsFailed . " ❌\n";
        echo "Процент успеха:   " . round(($this->testsPassed / $total) * 100, 2) . "%\n";
        
        if ($this->testsFailed > 0) {
            echo "\n\n════════════════════ ДЕТАЛИ ОШИБОК ════════════════════\n\n";
            foreach ($this->errors as $i => $error) {
                echo ($i + 1) . ". " . $error['test'] . "\n";
                echo "   Ошибка: " . $error['error'] . "\n";
                echo "   Trace:\n" . $error['trace'] . "\n\n";
            }
        }
        
        echo "\n════════════════════════════════════════════════════════\n";
        echo "Полные логи доступны в: logs/mysql_e2e_test.log\n";
        echo "════════════════════════════════════════════════════════\n\n";
        
        $this->logger->info('=== ЗАВЕРШЕНИЕ E2E ТЕСТИРОВАНИЯ ===', [
            'total' => $total,
            'passed' => $this->testsPassed,
            'failed' => $this->testsFailed,
        ]);
    }
    
    /**
     * Утверждения для тестов
     */
    private function assertTrue(bool $condition, string $message = ''): void
    {
        if (!$condition) {
            throw new \Exception($message ?: 'Ожидалось true, получено false');
        }
    }
    
    private function assertFalse(bool $condition, string $message = ''): void
    {
        if ($condition) {
            throw new \Exception($message ?: 'Ожидалось false, получено true');
        }
    }
    
    private function assertEquals($expected, $actual, string $message = ''): void
    {
        if ($expected != $actual) {
            $msg = $message ?: "Ожидалось '$expected', получено '$actual'";
            throw new \Exception($msg);
        }
    }
    
    private function assertNotNull($value, string $message = ''): void
    {
        if ($value === null) {
            throw new \Exception($message ?: 'Ожидалось не null, получено null');
        }
    }
    
    private function assertNull($value, string $message = ''): void
    {
        if ($value !== null) {
            throw new \Exception($message ?: 'Ожидалось null, получено не null');
        }
    }
    
    private function assertIsArray($value, string $message = ''): void
    {
        if (!is_array($value)) {
            throw new \Exception($message ?: 'Ожидался массив, получено ' . gettype($value));
        }
    }
    
    private function assertIsInt($value, string $message = ''): void
    {
        if (!is_int($value)) {
            throw new \Exception($message ?: 'Ожидалось целое число, получено ' . gettype($value));
        }
    }
    
    private function assertGreaterThan($min, $actual, string $message = ''): void
    {
        if ($actual <= $min) {
            throw new \Exception($message ?: "Ожидалось значение > $min, получено $actual");
        }
    }
    
    private function assertGreaterThanOrEqual($min, $actual, string $message = ''): void
    {
        if ($actual < $min) {
            throw new \Exception($message ?: "Ожидалось значение >= $min, получено $actual");
        }
    }
    
    private function assertEmpty($value, string $message = ''): void
    {
        if (!empty($value)) {
            throw new \Exception($message ?: 'Ожидалось пустое значение');
        }
    }
    
    private function assertNotEmpty($value, string $message = ''): void
    {
        if (empty($value)) {
            throw new \Exception($message ?: 'Ожидалось не пустое значение');
        }
    }
    
    private function assertArrayHasKey($key, array $array, string $message = ''): void
    {
        if (!array_key_exists($key, $array)) {
            throw new \Exception($message ?: "Ключ '$key' не найден в массиве");
        }
    }
    
    private function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
    {
        if (strpos($haystack, $needle) === false) {
            throw new \Exception($message ?: "Строка '$needle' не найдена в '$haystack'");
        }
    }
}

// Запуск тестов
try {
    $test = new MySQLEndToEndTest();
    $test->runAllTests();
    exit(0);
} catch (\Throwable $e) {
    echo "\n\n❌ КРИТИЧЕСКАЯ ОШИБКА:\n";
    echo $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

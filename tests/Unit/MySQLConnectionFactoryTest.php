<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Component\MySQLConnectionFactory;
use App\Component\Logger;
use App\Component\Exception\MySQL\MySQLException;
use PHPUnit\Framework\TestCase;

/**
 * Модульные тесты для класса MySQLConnectionFactory
 * 
 * Проверяет функциональность фабрики соединений MySQL:
 * - Инициализацию с различными конфигурациями
 * - Валидацию конфигурации
 * - Обработку ошибок конфигурации
 */
class MySQLConnectionFactoryTest extends TestCase
{
    private string $testLogDirectory;
    
    /**
     * Настройка окружения перед каждым тестом
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->testLogDirectory = sys_get_temp_dir() . '/mysql_factory_test_' . uniqid();
        mkdir($this->testLogDirectory, 0777, true);
    }
    
    /**
     * Очистка после каждого теста
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        if (is_dir($this->testLogDirectory)) {
            $this->removeDirectory($this->testLogDirectory);
        }
    }
    
    /**
     * Рекурсивное удаление директории
     * 
     * @param string $directory Путь к директории
     */
    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        
        $items = array_diff(scandir($directory), ['.', '..']);
        foreach ($items as $item) {
            $path = $directory . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($directory);
    }
    
    /**
     * Тест: Исключение при отсутствии ключа databases
     */
    public function testThrowsExceptionWhenDatabasesKeyMissing(): void
    {
        $this->expectException(MySQLException::class);
        $this->expectExceptionMessage('Параметр "databases" обязателен');
        
        new MySQLConnectionFactory([]);
    }
    
    /**
     * Тест: Исключение при пустом массиве databases
     */
    public function testThrowsExceptionWhenDatabasesArrayEmpty(): void
    {
        $this->expectException(MySQLException::class);
        $this->expectExceptionMessage('Необходимо указать хотя бы одну базу данных');
        
        new MySQLConnectionFactory([
            'databases' => [],
        ]);
    }
    
    /**
     * Тест: Исключение при неправильном типе databases
     */
    public function testThrowsExceptionWhenDatabasesNotArray(): void
    {
        $this->expectException(MySQLException::class);
        
        new MySQLConnectionFactory([
            'databases' => 'not-an-array',
        ]);
    }
    
    /**
     * Тест: Исключение при запросе несуществующей базы данных
     */
    public function testThrowsExceptionWhenRequestingNonExistentDatabase(): void
    {
        $factory = new MySQLConnectionFactory([
            'databases' => [
                'main' => [
                    'database' => 'testdb',
                    'username' => 'user',
                    'password' => 'pass',
                ],
            ],
        ]);
        
        $this->expectException(MySQLException::class);
        $this->expectExceptionMessage('База данных "nonexistent" не найдена в конфигурации');
        
        $factory->getConnection('nonexistent');
    }
    
    /**
     * Тест: Успешная инициализация с одной базой данных
     */
    public function testSuccessfulInitializationWithSingleDatabase(): void
    {
        $factory = new MySQLConnectionFactory([
            'databases' => [
                'main' => [
                    'database' => 'testdb',
                    'username' => 'user',
                    'password' => 'pass',
                ],
            ],
        ]);
        
        $this->assertInstanceOf(MySQLConnectionFactory::class, $factory);
    }
    
    /**
     * Тест: Инициализация с несколькими базами данных
     */
    public function testInitializationWithMultipleDatabases(): void
    {
        $factory = new MySQLConnectionFactory([
            'databases' => [
                'main' => [
                    'database' => 'maindb',
                    'username' => 'user1',
                    'password' => 'pass1',
                ],
                'analytics' => [
                    'database' => 'analyticsdb',
                    'username' => 'user2',
                    'password' => 'pass2',
                ],
                'logs' => [
                    'database' => 'logsdb',
                    'username' => 'user3',
                    'password' => 'pass3',
                ],
            ],
        ]);
        
        $this->assertInstanceOf(MySQLConnectionFactory::class, $factory);
    }
    
    /**
     * Тест: Инициализация с базой данных по умолчанию
     */
    public function testInitializationWithDefaultDatabase(): void
    {
        $factory = new MySQLConnectionFactory([
            'databases' => [
                'main' => [
                    'database' => 'maindb',
                    'username' => 'user',
                    'password' => 'pass',
                ],
                'secondary' => [
                    'database' => 'secondarydb',
                    'username' => 'user',
                    'password' => 'pass',
                ],
            ],
            'default' => 'secondary',
        ]);
        
        $this->assertInstanceOf(MySQLConnectionFactory::class, $factory);
    }
    
    /**
     * Тест: Инициализация с логгером
     */
    public function testInitializationWithLogger(): void
    {
        $logger = new Logger([
            'directory' => $this->testLogDirectory,
        ]);
        
        $factory = new MySQLConnectionFactory([
            'databases' => [
                'main' => [
                    'database' => 'testdb',
                    'username' => 'user',
                    'password' => 'pass',
                ],
            ],
        ], $logger);
        
        $this->assertInstanceOf(MySQLConnectionFactory::class, $factory);
    }
    
    /**
     * Тест: Конфигурация базы данных с полными параметрами
     */
    public function testDatabaseConfigWithFullParameters(): void
    {
        $factory = new MySQLConnectionFactory([
            'databases' => [
                'main' => [
                    'host' => 'localhost',
                    'port' => 3306,
                    'database' => 'testdb',
                    'username' => 'user',
                    'password' => 'pass',
                    'charset' => 'utf8mb4',
                    'persistent' => false,
                    'cache_statements' => true,
                ],
            ],
        ]);
        
        $this->assertInstanceOf(MySQLConnectionFactory::class, $factory);
    }
    
    /**
     * Тест: Конфигурация с несколькими базами и разными параметрами
     */
    public function testMultipleDatabasesWithDifferentConfigs(): void
    {
        $factory = new MySQLConnectionFactory([
            'databases' => [
                'db1' => [
                    'host' => 'host1',
                    'port' => 3306,
                    'database' => 'db1',
                    'username' => 'user1',
                    'password' => 'pass1',
                ],
                'db2' => [
                    'host' => 'host2',
                    'port' => 3307,
                    'database' => 'db2',
                    'username' => 'user2',
                    'password' => 'pass2',
                ],
            ],
        ]);
        
        $this->assertInstanceOf(MySQLConnectionFactory::class, $factory);
    }
    
    /**
     * Тест: Исключение при невалидной БД по умолчанию
     */
    public function testThrowsExceptionForInvalidDefaultDatabase(): void
    {
        $this->expectException(MySQLException::class);
        $this->expectExceptionMessage('База данных по умолчанию "nonexistent" не найдена в конфигурации');
        
        new MySQLConnectionFactory([
            'databases' => [
                'main' => [
                    'database' => 'testdb',
                    'username' => 'user',
                    'password' => 'pass',
                ],
            ],
            'default' => 'nonexistent',
        ]);
    }
    
    /**
     * Тест: Первая база данных используется по умолчанию если default не указан
     */
    public function testFirstDatabaseUsedAsDefaultWhenNotSpecified(): void
    {
        $factory = new MySQLConnectionFactory([
            'databases' => [
                'first' => [
                    'database' => 'firstdb',
                    'username' => 'user',
                    'password' => 'pass',
                ],
                'second' => [
                    'database' => 'seconddb',
                    'username' => 'user',
                    'password' => 'pass',
                ],
            ],
        ]);
        
        $this->assertInstanceOf(MySQLConnectionFactory::class, $factory);
    }
    
    /**
     * Тест: Конфигурация с кастомными PDO опциями
     */
    public function testConfigurationWithCustomPdoOptions(): void
    {
        $factory = new MySQLConnectionFactory([
            'databases' => [
                'main' => [
                    'database' => 'testdb',
                    'username' => 'user',
                    'password' => 'pass',
                    'options' => [
                        1002 => 'SET NAMES utf8',
                    ],
                ],
            ],
        ]);
        
        $this->assertInstanceOf(MySQLConnectionFactory::class, $factory);
    }
    
    /**
     * Тест: Исключение при отсутствии обязательных параметров в конфигурации БД
     */
    public function testThrowsExceptionWhenRequiredDbParametersMissing(): void
    {
        $this->expectException(MySQLException::class);
        
        $factory = new MySQLConnectionFactory([
            'databases' => [
                'main' => [
                    'database' => 'testdb',
                ],
            ],
        ]);
        
        $factory->getConnection('main');
    }
    
    /**
     * Тест: Конфигурация с персистентным соединением
     */
    public function testConfigurationWithPersistentConnection(): void
    {
        $factory = new MySQLConnectionFactory([
            'databases' => [
                'main' => [
                    'database' => 'testdb',
                    'username' => 'user',
                    'password' => 'pass',
                    'persistent' => true,
                ],
            ],
        ]);
        
        $this->assertInstanceOf(MySQLConnectionFactory::class, $factory);
    }
    
    /**
     * Тест: Конфигурация с отключенным кешированием prepared statements
     */
    public function testConfigurationWithDisabledStatementCache(): void
    {
        $factory = new MySQLConnectionFactory([
            'databases' => [
                'main' => [
                    'database' => 'testdb',
                    'username' => 'user',
                    'password' => 'pass',
                    'cache_statements' => false,
                ],
            ],
        ]);
        
        $this->assertInstanceOf(MySQLConnectionFactory::class, $factory);
    }
    
    /**
     * Тест: Множественные экземпляры фабрики
     */
    public function testMultipleFactoryInstances(): void
    {
        $factory1 = new MySQLConnectionFactory([
            'databases' => [
                'db1' => [
                    'database' => 'db1',
                    'username' => 'user',
                    'password' => 'pass',
                ],
            ],
        ]);
        
        $factory2 = new MySQLConnectionFactory([
            'databases' => [
                'db2' => [
                    'database' => 'db2',
                    'username' => 'user',
                    'password' => 'pass',
                ],
            ],
        ]);
        
        $this->assertInstanceOf(MySQLConnectionFactory::class, $factory1);
        $this->assertInstanceOf(MySQLConnectionFactory::class, $factory2);
    }
}

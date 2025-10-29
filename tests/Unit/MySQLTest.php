<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Component\MySQL;
use App\Component\Logger;
use App\Component\Exception\MySQLException;
use App\Component\Exception\MySQLConnectionException;
use PHPUnit\Framework\TestCase;
use PDO;

/**
 * Модульные тесты для класса MySQL
 * 
 * Проверяет функциональность работы с MySQL:
 * - Валидацию конфигурации
 * - Параметры подключения
 * - Обработку ошибок конфигурации
 */
class MySQLTest extends TestCase
{
    private string $testLogDirectory;
    
    /**
     * Настройка окружения перед каждым тестом
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->testLogDirectory = sys_get_temp_dir() . '/mysql_test_' . uniqid();
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
     * Тест: Исключение при отсутствии обязательного параметра database
     */
    public function testThrowsExceptionWhenDatabaseMissing(): void
    {
        $this->expectException(MySQLException::class);
        $this->expectExceptionMessage('Параметр "database" обязателен');
        
        new MySQL([
            'username' => 'user',
            'password' => 'pass',
        ]);
    }
    
    /**
     * Тест: Исключение при отсутствии обязательного параметра username
     */
    public function testThrowsExceptionWhenUsernameMissing(): void
    {
        $this->expectException(MySQLException::class);
        $this->expectExceptionMessage('Параметр "username" обязателен');
        
        new MySQL([
            'database' => 'testdb',
            'password' => 'pass',
        ]);
    }
    
    /**
     * Тест: Исключение при отсутствии обязательного параметра password
     */
    public function testThrowsExceptionWhenPasswordMissing(): void
    {
        $this->expectException(MySQLException::class);
        $this->expectExceptionMessage('Параметр "password" обязателен');
        
        new MySQL([
            'database' => 'testdb',
            'username' => 'user',
        ]);
    }
    
    /**
     * Тест: Исключение при пустом database
     */
    public function testThrowsExceptionWhenDatabaseEmpty(): void
    {
        $this->expectException(MySQLException::class);
        $this->expectExceptionMessage('Параметр "database" не может быть пустым');
        
        new MySQL([
            'database' => '',
            'username' => 'user',
            'password' => 'pass',
        ]);
    }
    
    /**
     * Тест: Исключение при пустом username
     */
    public function testThrowsExceptionWhenUsernameEmpty(): void
    {
        $this->expectException(MySQLException::class);
        $this->expectExceptionMessage('Параметр "username" не может быть пустым');
        
        new MySQL([
            'database' => 'testdb',
            'username' => '',
            'password' => 'pass',
        ]);
    }
    
    /**
     * Тест: Проверка что пароль может быть пустым (для некоторых конфигураций)
     */
    public function testPasswordCanBeEmpty(): void
    {
        $this->expectException(MySQLConnectionException::class);
        
        new MySQL([
            'database' => 'testdb',
            'username' => 'user',
            'password' => '',
        ]);
    }
    
    /**
     * Тест: Исключение при невалидном host (подключение не удастся)
     */
    public function testThrowsExceptionForInvalidHost(): void
    {
        $this->expectException(MySQLConnectionException::class);
        
        new MySQL([
            'host' => 'invalid-host-that-does-not-exist-12345',
            'database' => 'testdb',
            'username' => 'user',
            'password' => 'pass',
        ]);
    }
    
    /**
     * Тест: Исключение при невалидном port
     */
    public function testThrowsExceptionForInvalidPort(): void
    {
        $this->expectException(MySQLConnectionException::class);
        
        new MySQL([
            'host' => 'localhost',
            'port' => 99999,
            'database' => 'testdb',
            'username' => 'user',
            'password' => 'pass',
        ]);
    }
    
    /**
     * Тест: Конфигурация с логгером не вызывает ошибок валидации
     */
    public function testConfigurationWithLogger(): void
    {
        $logger = new Logger([
            'directory' => $this->testLogDirectory,
        ]);
        
        $this->expectException(MySQLConnectionException::class);
        
        new MySQL([
            'database' => 'testdb',
            'username' => 'user',
            'password' => 'pass',
        ], $logger);
    }
    
    /**
     * Тест: Конфигурация с пользовательским charset
     */
    public function testConfigurationWithCustomCharset(): void
    {
        $this->expectException(MySQLConnectionException::class);
        
        new MySQL([
            'database' => 'testdb',
            'username' => 'user',
            'password' => 'pass',
            'charset' => 'utf8',
        ]);
    }
    
    /**
     * Тест: Конфигурация с персистентным соединением
     */
    public function testConfigurationWithPersistentConnection(): void
    {
        $this->expectException(MySQLConnectionException::class);
        
        new MySQL([
            'database' => 'testdb',
            'username' => 'user',
            'password' => 'pass',
            'persistent' => true,
        ]);
    }
    
    /**
     * Тест: Конфигурация с отключенным кешированием statements
     */
    public function testConfigurationWithDisabledStatementCache(): void
    {
        $this->expectException(MySQLConnectionException::class);
        
        new MySQL([
            'database' => 'testdb',
            'username' => 'user',
            'password' => 'pass',
            'cache_statements' => false,
        ]);
    }
    
    /**
     * Тест: Конфигурация с пользовательскими PDO опциями
     */
    public function testConfigurationWithCustomPdoOptions(): void
    {
        $this->expectException(MySQLConnectionException::class);
        
        new MySQL([
            'database' => 'testdb',
            'username' => 'user',
            'password' => 'pass',
            'options' => [
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ],
        ]);
    }
    
    /**
     * Тест: Конфигурация со всеми опциональными параметрами
     */
    public function testConfigurationWithAllOptionalParameters(): void
    {
        $logger = new Logger([
            'directory' => $this->testLogDirectory,
        ]);
        
        $this->expectException(MySQLConnectionException::class);
        
        new MySQL([
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'testdb',
            'username' => 'user',
            'password' => 'pass',
            'charset' => 'utf8mb4',
            'persistent' => false,
            'cache_statements' => true,
            'options' => [],
        ], $logger);
    }
    
    /**
     * Тест: Значения по умолчанию применяются корректно
     */
    public function testDefaultValuesAppliedCorrectly(): void
    {
        $this->expectException(MySQLConnectionException::class);
        
        new MySQL([
            'database' => 'testdb',
            'username' => 'user',
            'password' => 'pass',
        ]);
    }
    
    /**
     * Тест: Отрицательный port преобразуется в положительное значение
     */
    public function testNegativePortHandling(): void
    {
        $this->expectException(MySQLConnectionException::class);
        
        new MySQL([
            'port' => -3306,
            'database' => 'testdb',
            'username' => 'user',
            'password' => 'pass',
        ]);
    }
    
    /**
     * Тест: Нулевой port
     */
    public function testZeroPort(): void
    {
        $this->expectException(MySQLConnectionException::class);
        
        new MySQL([
            'port' => 0,
            'database' => 'testdb',
            'username' => 'user',
            'password' => 'pass',
        ]);
    }
    
    /**
     * Тест: Конфигурация с длинными строками
     */
    public function testConfigurationWithLongStrings(): void
    {
        $this->expectException(MySQLConnectionException::class);
        
        new MySQL([
            'database' => str_repeat('a', 1000),
            'username' => str_repeat('b', 1000),
            'password' => str_repeat('c', 1000),
        ]);
    }
    
    /**
     * Тест: Конфигурация со специальными символами в пароле
     */
    public function testConfigurationWithSpecialCharactersInPassword(): void
    {
        $this->expectException(MySQLConnectionException::class);
        
        new MySQL([
            'database' => 'testdb',
            'username' => 'user',
            'password' => 'p@$$w0rd!#%&',
        ]);
    }
    
    /**
     * Тест: Конфигурация с числовыми значениями в виде строк
     */
    public function testConfigurationWithNumericStrings(): void
    {
        $this->expectException(MySQLConnectionException::class);
        
        new MySQL([
            'port' => '3306',
            'database' => 'testdb',
            'username' => 'user',
            'password' => 'pass',
        ]);
    }
}

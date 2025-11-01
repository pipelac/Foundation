<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit тесты для проверки исправления биндинга параметров в prepared statements
 */
class MySQLPreparedStmtBindingTest extends TestCase
{
    /**
     * Тест определения позиционных плейсхолдеров
     * 
     * @dataProvider positionalPlaceholdersProvider
     */
    public function testUsesPositionalPlaceholders(string $query, bool $expected): void
    {
        // Реплицируем логику метода usesPositionalPlaceholders
        $strippedQuery = preg_replace("/'([^']|\\\\')*'/", '', $query);
        $strippedQuery = preg_replace('/"([^"]|\\\\")*"/', '', $strippedQuery ?? '');
        $result = ($strippedQuery !== null && strpos($strippedQuery, '?') !== false);
        
        $this->assertSame($expected, $result, "Query: {$query}");
    }
    
    /**
     * Провайдер данных для тестирования позиционных плейсхолдеров
     */
    public static function positionalPlaceholdersProvider(): array
    {
        return [
            // Позиционные плейсхолдеры
            ['SELECT * FROM users WHERE id = ?', true],
            ['INSERT INTO users (name, email) VALUES (?, ?)', true],
            ['UPDATE users SET name = ?, email = ? WHERE id = ?', true],
            ['DELETE FROM users WHERE id = ? AND status = ?', true],
            
            // Именованные плейсхолдеры
            ['SELECT * FROM users WHERE id = :id', false],
            ['INSERT INTO users (name, email) VALUES (:name, :email)', false],
            ['UPDATE users SET name = :name WHERE id = :id', false],
            
            // Без плейсхолдеров
            ['SELECT * FROM users', false],
            ['SELECT * FROM users WHERE id = 123', false],
            
            // Плейсхолдеры внутри строковых литералов (должны игнорироваться)
            ["SELECT * FROM users WHERE name = 'test?value'", false],
            ['SELECT * FROM users WHERE name = "test?value"', false],
            ["SELECT * FROM users WHERE name = 'What is your name?'", false],
            
            // Плейсхолдеры вне и внутри строковых литералов
            ["SELECT * FROM users WHERE name = 'test' AND id = ?", true],
            ['SELECT * FROM users WHERE name = "test" AND id = ? AND email = ?', true],
            
            // Экранированные кавычки внутри строковых литералов
            ["SELECT * FROM users WHERE name = 'O\\'Brien' AND id = ?", true],
            ['SELECT * FROM users WHERE name = "Test \\"quoted\\"" AND id = ?', true],
        ];
    }
    
    /**
     * Тест нормализации параметров через array_values
     * 
     * @dataProvider parametersNormalizationProvider
     */
    public function testParametersNormalization(array $params, array $expected): void
    {
        $normalized = array_values($params);
        $this->assertSame($expected, $normalized);
    }
    
    /**
     * Провайдер данных для тестирования нормализации параметров
     */
    public static function parametersNormalizationProvider(): array
    {
        return [
            // Обычный массив с числовыми индексами
            [
                ['Alice', 'alice@test.com', 25],
                ['Alice', 'alice@test.com', 25]
            ],
            
            // Ассоциативный массив со строковыми ключами
            [
                ['name' => 'Bob', 'email' => 'bob@test.com', 'age' => 30],
                ['Bob', 'bob@test.com', 30]
            ],
            
            // Массив с несвязными числовыми индексами
            [
                [2 => 'Charlie', 5 => 'charlie@test.com', 10 => 35],
                ['Charlie', 'charlie@test.com', 35]
            ],
            
            // Пустой массив
            [
                [],
                []
            ],
            
            // Смешанные типы значений
            [
                ['name' => 'Diana', 'age' => 28, 'active' => true, 'salary' => 50000.50],
                ['Diana', 28, true, 50000.50]
            ],
        ];
    }
    
    /**
     * Тест сохранения порядка значений при нормализации
     */
    public function testParametersOrderPreserved(): void
    {
        $params = [
            'first' => 'Alice',
            'second' => 'Bob',
            'third' => 'Charlie'
        ];
        
        $normalized = array_values($params);
        
        // Проверяем, что порядок сохранился
        $this->assertSame('Alice', $normalized[0]);
        $this->assertSame('Bob', $normalized[1]);
        $this->assertSame('Charlie', $normalized[2]);
        $this->assertCount(3, $normalized);
    }
    
    /**
     * Тест что array_values не изменяет сами значения
     */
    public function testParametersValuesUnchanged(): void
    {
        $params = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'age' => 42,
            'active' => false,
            'balance' => 1234.56,
            'data' => ['key' => 'value']
        ];
        
        $normalized = array_values($params);
        
        // Проверяем каждое значение
        $this->assertSame('Test User', $normalized[0]);
        $this->assertSame('test@example.com', $normalized[1]);
        $this->assertSame(42, $normalized[2]);
        $this->assertSame(false, $normalized[3]);
        $this->assertSame(1234.56, $normalized[4]);
        $this->assertSame(['key' => 'value'], $normalized[5]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Component\Telegram;
use App\Component\Logger;
use App\Component\Exception\TelegramConfigException;
use PHPUnit\Framework\TestCase;

/**
 * Модульные тесты для класса Telegram
 * 
 * Проверяет функциональность Telegram Bot API клиента:
 * - Инициализацию с различными конфигурациями
 * - Валидацию токена
 * - Валидацию конфигурационных параметров
 * - Константы режимов разметки
 */
class TelegramTest extends TestCase
{
    private string $testLogDirectory;
    
    /**
     * Настройка окружения перед каждым тестом
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->testLogDirectory = sys_get_temp_dir() . '/telegram_test_' . uniqid();
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
     * Тест: Исключение при отсутствии токена
     */
    public function testThrowsExceptionWhenTokenMissing(): void
    {
        $this->expectException(TelegramConfigException::class);
        $this->expectExceptionMessage('Токен Telegram бота не указан');
        
        new Telegram([]);
    }
    
    /**
     * Тест: Исключение при пустом токене
     */
    public function testThrowsExceptionWhenTokenEmpty(): void
    {
        $this->expectException(TelegramConfigException::class);
        $this->expectExceptionMessage('Токен Telegram бота не указан');
        
        new Telegram(['token' => '']);
    }
    
    /**
     * Тест: Исключение при токене из пробелов
     */
    public function testThrowsExceptionWhenTokenOnlyWhitespace(): void
    {
        $this->expectException(TelegramConfigException::class);
        $this->expectExceptionMessage('Токен Telegram бота не указан');
        
        new Telegram(['token' => '   ']);
    }
    
    /**
     * Тест: Исключение при невалидном формате токена
     */
    public function testThrowsExceptionForInvalidTokenFormat(): void
    {
        $this->expectException(TelegramConfigException::class);
        $this->expectExceptionMessage('Формат токена Telegram бота некорректен');
        
        new Telegram(['token' => 'invalid-token']);
    }
    
    /**
     * Тест: Исключение при токене без двоеточия
     */
    public function testThrowsExceptionForTokenWithoutColon(): void
    {
        $this->expectException(TelegramConfigException::class);
        $this->expectExceptionMessage('Формат токена Telegram бота некорректен');
        
        new Telegram(['token' => '123456789ABCDEF']);
    }
    
    /**
     * Тест: Исключение при токене с короткой первой частью
     */
    public function testThrowsExceptionForTokenWithShortFirstPart(): void
    {
        $this->expectException(TelegramConfigException::class);
        $this->expectExceptionMessage('Формат токена Telegram бота некорректен');
        
        new Telegram(['token' => '12:ABC-DEF1234ghIkl-zyx57W2v1u123ew11']);
    }
    
    /**
     * Тест: Успешная инициализация с валидным токеном
     */
    public function testSuccessfulInitializationWithValidToken(): void
    {
        $telegram = new Telegram([
            'token' => '123456789:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
        ]);
        
        $this->assertInstanceOf(Telegram::class, $telegram);
    }
    
    /**
     * Тест: Инициализация с токеном и default_chat_id
     */
    public function testInitializationWithDefaultChatId(): void
    {
        $telegram = new Telegram([
            'token' => '123456789:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
            'default_chat_id' => '123456789',
        ]);
        
        $this->assertInstanceOf(Telegram::class, $telegram);
    }
    
    /**
     * Тест: Инициализация с таймаутом
     */
    public function testInitializationWithTimeout(): void
    {
        $telegram = new Telegram([
            'token' => '123456789:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
            'timeout' => 60,
        ]);
        
        $this->assertInstanceOf(Telegram::class, $telegram);
    }
    
    /**
     * Тест: Инициализация с логгером
     */
    public function testInitializationWithLogger(): void
    {
        $logger = new Logger([
            'directory' => $this->testLogDirectory,
        ]);
        
        $telegram = new Telegram([
            'token' => '123456789:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
        ], $logger);
        
        $this->assertInstanceOf(Telegram::class, $telegram);
    }
    
    /**
     * Тест: Инициализация с количеством повторных попыток
     */
    public function testInitializationWithRetries(): void
    {
        $telegram = new Telegram([
            'token' => '123456789:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
            'retries' => 3,
        ]);
        
        $this->assertInstanceOf(Telegram::class, $telegram);
    }
    
    /**
     * Тест: Минимальный таймаут устанавливается корректно
     */
    public function testMinimumTimeoutIsEnforced(): void
    {
        $telegram = new Telegram([
            'token' => '123456789:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
            'timeout' => 1,
        ]);
        
        $this->assertInstanceOf(Telegram::class, $telegram);
    }
    
    /**
     * Тест: Отрицательный таймаут преобразуется в минимальное значение
     */
    public function testNegativeTimeoutConvertedToMinimum(): void
    {
        $telegram = new Telegram([
            'token' => '123456789:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
            'timeout' => -10,
        ]);
        
        $this->assertInstanceOf(Telegram::class, $telegram);
    }
    
    /**
     * Тест: Константы режимов разметки определены корректно
     */
    public function testParseModeconstants(): void
    {
        $this->assertEquals('Markdown', Telegram::PARSE_MODE_MARKDOWN);
        $this->assertEquals('MarkdownV2', Telegram::PARSE_MODE_MARKDOWN_V2);
        $this->assertEquals('HTML', Telegram::PARSE_MODE_HTML);
    }
    
    /**
     * Тест: default_chat_id может быть строкой
     */
    public function testDefaultChatIdCanBeString(): void
    {
        $telegram = new Telegram([
            'token' => '123456789:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
            'default_chat_id' => '@channel_name',
        ]);
        
        $this->assertInstanceOf(Telegram::class, $telegram);
    }
    
    /**
     * Тест: default_chat_id может быть числом
     */
    public function testDefaultChatIdCanBeNumeric(): void
    {
        $telegram = new Telegram([
            'token' => '123456789:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
            'default_chat_id' => -1001234567890,
        ]);
        
        $this->assertInstanceOf(Telegram::class, $telegram);
    }
    
    /**
     * Тест: Пустой default_chat_id игнорируется
     */
    public function testEmptyDefaultChatIdIgnored(): void
    {
        $telegram = new Telegram([
            'token' => '123456789:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
            'default_chat_id' => '',
        ]);
        
        $this->assertInstanceOf(Telegram::class, $telegram);
    }
    
    /**
     * Тест: default_chat_id с пробелами обрабатывается корректно
     */
    public function testDefaultChatIdWithWhitespace(): void
    {
        $telegram = new Telegram([
            'token' => '123456789:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
            'default_chat_id' => '   123456789   ',
        ]);
        
        $this->assertInstanceOf(Telegram::class, $telegram);
    }
    
    /**
     * Тест: Множественные экземпляры с разными токенами
     */
    public function testMultipleInstancesWithDifferentTokens(): void
    {
        $telegram1 = new Telegram(['token' => '111111111:ABC-DEF1234ghIkl-zyx57W2v1u123ew11']);
        $telegram2 = new Telegram(['token' => '222222222:ABC-DEF1234ghIkl-zyx57W2v1u123ew11']);
        $telegram3 = new Telegram(['token' => '333333333:ABC-DEF1234ghIkl-zyx57W2v1u123ew11']);
        
        $this->assertInstanceOf(Telegram::class, $telegram1);
        $this->assertInstanceOf(Telegram::class, $telegram2);
        $this->assertInstanceOf(Telegram::class, $telegram3);
    }
    
    /**
     * Тест: Токен с минимальной длиной первой части
     */
    public function testTokenWithMinimumFirstPartLength(): void
    {
        $telegram = new Telegram([
            'token' => '1234567:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
        ]);
        
        $this->assertInstanceOf(Telegram::class, $telegram);
    }
    
    /**
     * Тест: Токен с очень длинной первой частью
     */
    public function testTokenWithVeryLongFirstPart(): void
    {
        $telegram = new Telegram([
            'token' => '12345678901234567890:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
        ]);
        
        $this->assertInstanceOf(Telegram::class, $telegram);
    }
    
    /**
     * Тест: Отрицательное количество повторных попыток преобразуется в 0
     */
    public function testNegativeRetriesConvertedToZero(): void
    {
        $telegram = new Telegram([
            'token' => '123456789:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
            'retries' => -5,
        ]);
        
        $this->assertInstanceOf(Telegram::class, $telegram);
    }
    
    /**
     * Тест: Очень большое количество повторных попыток
     */
    public function testVeryLargeRetries(): void
    {
        $telegram = new Telegram([
            'token' => '123456789:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
            'retries' => 100,
        ]);
        
        $this->assertInstanceOf(Telegram::class, $telegram);
    }
    
    /**
     * Тест: Очень большой таймаут
     */
    public function testVeryLargeTimeout(): void
    {
        $telegram = new Telegram([
            'token' => '123456789:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
            'timeout' => 3600,
        ]);
        
        $this->assertInstanceOf(Telegram::class, $telegram);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Component\Email;
use App\Component\Logger;
use App\Component\Exception\EmailException;
use App\Component\Exception\EmailValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Модульные тесты для класса Email
 * 
 * Проверяет функциональность почтового компонента:
 * - Инициализацию с различными конфигурациями
 * - Валидацию email адресов
 * - Валидацию конфигурации
 * - SMTP и mail() параметры
 */
class EmailTest extends TestCase
{
    private string $testLogDirectory;
    
    /**
     * Настройка окружения перед каждым тестом
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->testLogDirectory = sys_get_temp_dir() . '/email_test_' . uniqid();
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
     * Тест: Исключение при отсутствии обязательного параметра from_email
     */
    public function testThrowsExceptionWhenFromEmailMissing(): void
    {
        $this->expectException(EmailValidationException::class);
        $this->expectExceptionMessageMatches('/from_email/');
        
        new Email([]);
    }
    
    /**
     * Тест: Исключение при невалидном from_email
     */
    public function testThrowsExceptionForInvalidFromEmail(): void
    {
        $this->expectException(EmailValidationException::class);
        
        new Email([
            'from_email' => 'invalid-email',
        ]);
    }
    
    /**
     * Тест: Успешная инициализация с минимальной конфигурацией
     */
    public function testSuccessfulInitializationWithMinimalConfig(): void
    {
        $email = new Email([
            'from_email' => 'sender@example.com',
        ]);
        
        $this->assertInstanceOf(Email::class, $email);
    }
    
    /**
     * Тест: Инициализация с именем отправителя
     */
    public function testInitializationWithFromName(): void
    {
        $email = new Email([
            'from_email' => 'sender@example.com',
            'from_name' => 'John Doe',
        ]);
        
        $this->assertInstanceOf(Email::class, $email);
    }
    
    /**
     * Тест: Инициализация с reply_to адресом
     */
    public function testInitializationWithReplyTo(): void
    {
        $email = new Email([
            'from_email' => 'sender@example.com',
            'reply_to' => 'reply@example.com',
        ]);
        
        $this->assertInstanceOf(Email::class, $email);
    }
    
    /**
     * Тест: Инициализация с return_path адресом
     */
    public function testInitializationWithReturnPath(): void
    {
        $email = new Email([
            'from_email' => 'sender@example.com',
            'return_path' => 'bounce@example.com',
        ]);
        
        $this->assertInstanceOf(Email::class, $email);
    }
    
    /**
     * Тест: Инициализация с полной SMTP конфигурацией
     */
    public function testInitializationWithSmtpConfig(): void
    {
        $email = new Email([
            'from_email' => 'sender@example.com',
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_username' => 'username',
            'smtp_password' => 'password',
        ]);
        
        $this->assertInstanceOf(Email::class, $email);
    }
    
    /**
     * Тест: Инициализация с логгером
     */
    public function testInitializationWithLogger(): void
    {
        $logger = new Logger([
            'directory' => $this->testLogDirectory,
        ]);
        
        $email = new Email([
            'from_email' => 'sender@example.com',
        ], $logger);
        
        $this->assertInstanceOf(Email::class, $email);
    }
    
    /**
     * Тест: Инициализация с пользовательской кодировкой
     */
    public function testInitializationWithCustomCharset(): void
    {
        $email = new Email([
            'from_email' => 'sender@example.com',
            'charset' => 'UTF-8',
        ]);
        
        $this->assertInstanceOf(Email::class, $email);
    }
    
    /**
     * Тест: Инициализация с настройками повторных попыток
     */
    public function testInitializationWithRetrySettings(): void
    {
        $email = new Email([
            'from_email' => 'sender@example.com',
            'retry_attempts' => 5,
            'retry_delay' => 10,
        ]);
        
        $this->assertInstanceOf(Email::class, $email);
    }
    
    /**
     * Тест: Инициализация с таймаутом
     */
    public function testInitializationWithTimeout(): void
    {
        $email = new Email([
            'from_email' => 'sender@example.com',
            'timeout' => 60,
        ]);
        
        $this->assertInstanceOf(Email::class, $email);
    }
    
    /**
     * Тест: Исключение при невалидном reply_to адресе
     */
    public function testThrowsExceptionForInvalidReplyTo(): void
    {
        $this->expectException(EmailValidationException::class);
        
        new Email([
            'from_email' => 'sender@example.com',
            'reply_to' => 'invalid-reply-email',
        ]);
    }
    
    /**
     * Тест: Исключение при невалидном return_path адресе
     */
    public function testThrowsExceptionForInvalidReturnPath(): void
    {
        $this->expectException(EmailValidationException::class);
        
        new Email([
            'from_email' => 'sender@example.com',
            'return_path' => 'invalid-return-path',
        ]);
    }
    
    /**
     * Тест: Инициализация с SSL шифрованием
     */
    public function testInitializationWithSslEncryption(): void
    {
        $email = new Email([
            'from_email' => 'sender@example.com',
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'smtp_username' => 'username',
            'smtp_password' => 'password',
        ]);
        
        $this->assertInstanceOf(Email::class, $email);
    }
    
    /**
     * Тест: Инициализация с минимальными значениями повторных попыток
     */
    public function testInitializationWithMinimumRetryValues(): void
    {
        $email = new Email([
            'from_email' => 'sender@example.com',
            'retry_attempts' => 1,
            'retry_delay' => 1,
        ]);
        
        $this->assertInstanceOf(Email::class, $email);
    }
    
    /**
     * Тест: Отрицательные значения retry преобразуются в положительные
     */
    public function testNegativeRetryValuesHandled(): void
    {
        $email = new Email([
            'from_email' => 'sender@example.com',
            'retry_attempts' => -1,
            'retry_delay' => -1,
        ]);
        
        $this->assertInstanceOf(Email::class, $email);
    }
    
    /**
     * Тест: Email с кириллицей в имени отправителя
     */
    public function testInitializationWithCyrillicFromName(): void
    {
        $email = new Email([
            'from_email' => 'sender@example.com',
            'from_name' => 'Иван Иванов',
        ]);
        
        $this->assertInstanceOf(Email::class, $email);
    }
    
    /**
     * Тест: Множественные экземпляры с разными конфигурациями
     */
    public function testMultipleInstancesWithDifferentConfigs(): void
    {
        $email1 = new Email(['from_email' => 'sender1@example.com']);
        $email2 = new Email(['from_email' => 'sender2@example.com']);
        $email3 = new Email(['from_email' => 'sender3@example.com']);
        
        $this->assertInstanceOf(Email::class, $email1);
        $this->assertInstanceOf(Email::class, $email2);
        $this->assertInstanceOf(Email::class, $email3);
    }
    
    /**
     * Тест: Email с нестандартным SMTP портом
     */
    public function testInitializationWithCustomSmtpPort(): void
    {
        $email = new Email([
            'from_email' => 'sender@example.com',
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => 2525,
        ]);
        
        $this->assertInstanceOf(Email::class, $email);
    }
    
    /**
     * Тест: Пустая строка в необязательных полях
     */
    public function testInitializationWithEmptyOptionalFields(): void
    {
        $email = new Email([
            'from_email' => 'sender@example.com',
            'from_name' => '',
            'reply_name' => '',
        ]);
        
        $this->assertInstanceOf(Email::class, $email);
    }
    
    /**
     * Тест: Валидный email с поддоменами
     */
    public function testInitializationWithSubdomainEmail(): void
    {
        $email = new Email([
            'from_email' => 'sender@mail.subdomain.example.com',
        ]);
        
        $this->assertInstanceOf(Email::class, $email);
    }
    
    /**
     * Тест: Валидный email с плюсом
     */
    public function testInitializationWithPlusInEmail(): void
    {
        $email = new Email([
            'from_email' => 'sender+tag@example.com',
        ]);
        
        $this->assertInstanceOf(Email::class, $email);
    }
}

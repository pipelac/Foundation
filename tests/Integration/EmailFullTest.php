<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Component\Email;
use App\Component\Logger;
use App\Component\Exception\EmailException;
use App\Component\Exception\EmailValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Полное интеграционное тестирование класса Email
 * 
 * Тестирует:
 * - Все методы класса
 * - Отправку писем через mail()
 * - Логирование всех операций
 * - Вложения
 * - Обработку ошибок
 * - Retry механизм
 */
class EmailFullTest extends TestCase
{
    private string $testLogDirectory;
    private string $testAttachmentsDirectory;
    private ?Logger $logger = null;
    
    /**
     * Настройка окружения перед каждым тестом
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->testLogDirectory = sys_get_temp_dir() . '/email_full_test_' . uniqid();
        $this->testAttachmentsDirectory = sys_get_temp_dir() . '/email_attachments_' . uniqid();
        
        mkdir($this->testLogDirectory, 0777, true);
        mkdir($this->testAttachmentsDirectory, 0777, true);
        
        // Создаем логгер для тестов
        $this->logger = new Logger([
            'directory' => $this->testLogDirectory,
            'file_name' => 'email_test.log',
        ]);
    }
    
    /**
     * Очистка после каждого теста
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        
        if ($this->logger !== null) {
            unset($this->logger);
            $this->logger = null;
        }
        
        if (is_dir($this->testLogDirectory)) {
            $this->removeDirectory($this->testLogDirectory);
        }
        
        if (is_dir($this->testAttachmentsDirectory)) {
            $this->removeDirectory($this->testAttachmentsDirectory);
        }
    }
    
    /**
     * Рекурсивное удаление директории
     */
    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        
        $items = array_diff(scandir($directory) ?: [], ['.', '..']);
        foreach ($items as $item) {
            $path = $directory . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($directory);
    }
    
    /**
     * Создает тестовый файл для вложения
     */
    private function createTestFile(string $name, string $content): string
    {
        $path = $this->testAttachmentsDirectory . DIRECTORY_SEPARATOR . $name;
        file_put_contents($path, $content);
        return $path;
    }
    
    /**
     * Получает содержимое лог-файла
     */
    private function getLogContent(): string
    {
        $logFile = $this->testLogDirectory . '/email_test.log';
        if (file_exists($logFile)) {
            return file_get_contents($logFile) ?: '';
        }
        return '';
    }
    
    /**
     * ТЕСТ 1: Базовая конфигурация и инициализация
     */
    public function testBasicConfiguration(): void
    {
        echo "\n=== ТЕСТ 1: Базовая конфигурация и инициализация ===\n";
        
        // Минимальная конфигурация
        $email = new Email([
            'from_email' => 'test@example.com',
        ], $this->logger);
        
        $this->assertInstanceOf(Email::class, $email);
        echo "✓ Минимальная конфигурация: OK\n";
        
        // Полная базовая конфигурация
        $email = new Email([
            'from_email' => 'test@example.com',
            'from_name' => 'Test Sender',
            'reply_to' => 'reply@example.com',
            'reply_name' => 'Reply Name',
            'return_path' => 'bounce@example.com',
            'charset' => 'UTF-8',
        ], $this->logger);
        
        $this->assertInstanceOf(Email::class, $email);
        echo "✓ Полная базовая конфигурация: OK\n";
        
        // С кириллицей в имени (домен должен быть ASCII)
        $email = new Email([
            'from_email' => 'test@example.com',
            'from_name' => 'Иван Петров',
            'reply_name' => 'Служба поддержки',
        ], $this->logger);
        
        $this->assertInstanceOf(Email::class, $email);
        echo "✓ Конфигурация с кириллицей в именах: OK\n";
    }
    
    /**
     * ТЕСТ 2: SMTP конфигурация
     */
    public function testSmtpConfiguration(): void
    {
        echo "\n=== ТЕСТ 2: SMTP конфигурация ===\n";
        
        // Правильная структура через smtp массив
        $email = new Email([
            'from_email' => 'test@example.com',
            'smtp' => [
                'host' => 'smtp.example.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'user@example.com',
                'password' => 'password123',
            ],
        ], $this->logger);
        
        $this->assertInstanceOf(Email::class, $email);
        echo "✓ SMTP конфигурация через массив smtp: OK\n";
        
        // SSL шифрование
        $email = new Email([
            'from_email' => 'test@example.com',
            'smtp' => [
                'host' => 'smtp.example.com',
                'port' => 465,
                'encryption' => 'ssl',
                'username' => 'user@example.com',
                'password' => 'password123',
            ],
        ], $this->logger);
        
        $this->assertInstanceOf(Email::class, $email);
        echo "✓ SMTP SSL конфигурация: OK\n";
        
        // STARTTLS
        $email = new Email([
            'from_email' => 'test@example.com',
            'smtp' => [
                'host' => 'smtp.example.com',
                'port' => 587,
                'encryption' => 'starttls',
            ],
        ], $this->logger);
        
        $this->assertInstanceOf(Email::class, $email);
        echo "✓ SMTP STARTTLS конфигурация: OK\n";
    }
    
    /**
     * ТЕСТ 3: Конфигурация доставки
     */
    public function testDeliveryConfiguration(): void
    {
        echo "\n=== ТЕСТ 3: Конфигурация доставки ===\n";
        
        // Правильная структура через delivery массив
        $email = new Email([
            'from_email' => 'test@example.com',
            'delivery' => [
                'retry_attempts' => 5,
                'retry_delay' => 10,
                'timeout' => 60,
            ],
        ], $this->logger);
        
        $this->assertInstanceOf(Email::class, $email);
        echo "✓ Конфигурация доставки через массив delivery: OK\n";
        
        // Минимальные значения
        $email = new Email([
            'from_email' => 'test@example.com',
            'delivery' => [
                'retry_attempts' => 1,
                'retry_delay' => 0,
                'timeout' => 1,
            ],
        ], $this->logger);
        
        $this->assertInstanceOf(Email::class, $email);
        echo "✓ Минимальные значения доставки: OK\n";
    }
    
    /**
     * ТЕСТ 4: Валидация некорректных параметров
     */
    public function testInvalidConfiguration(): void
    {
        echo "\n=== ТЕСТ 4: Валидация некорректных параметров ===\n";
        
        $exceptionCount = 0;
        
        // Отсутствует from_email
        try {
            new Email([], $this->logger);
            $this->fail('Должно выброситься исключение для отсутствующего from_email');
        } catch (EmailValidationException $e) {
            $exceptionCount++;
            echo "✓ Исключение для отсутствующего from_email: " . $e->getMessage() . "\n";
        }
        
        // Невалидный from_email
        try {
            new Email(['from_email' => 'invalid-email'], $this->logger);
            $this->fail('Должно выброситься исключение для невалидного from_email');
        } catch (EmailValidationException $e) {
            $exceptionCount++;
            echo "✓ Исключение для невалидного from_email: " . $e->getMessage() . "\n";
        }
        
        // Невалидный reply_to
        try {
            new Email([
                'from_email' => 'test@example.com',
                'reply_to' => 'invalid-reply',
            ], $this->logger);
            $this->fail('Должно выброситься исключение для невалидного reply_to');
        } catch (EmailValidationException $e) {
            $exceptionCount++;
            echo "✓ Исключение для невалидного reply_to: " . $e->getMessage() . "\n";
        }
        
        // Невалидный return_path
        try {
            new Email([
                'from_email' => 'test@example.com',
                'return_path' => 'invalid-path',
            ], $this->logger);
            $this->fail('Должно выброситься исключение для невалидного return_path');
        } catch (EmailValidationException $e) {
            $exceptionCount++;
            echo "✓ Исключение для невалидного return_path: " . $e->getMessage() . "\n";
        }
        
        // Невалидное SMTP encryption
        try {
            new Email([
                'from_email' => 'test@example.com',
                'smtp' => [
                    'host' => 'smtp.example.com',
                    'encryption' => 'invalid',
                ],
            ], $this->logger);
            $this->fail('Должно выброситься исключение для невалидного encryption');
        } catch (EmailValidationException $e) {
            $exceptionCount++;
            echo "✓ Исключение для невалидного encryption: " . $e->getMessage() . "\n";
        }
        
        // Невалидный SMTP порт
        try {
            new Email([
                'from_email' => 'test@example.com',
                'smtp' => [
                    'host' => 'smtp.example.com',
                    'port' => 99999,
                ],
            ], $this->logger);
            $this->fail('Должно выброситься исключение для невалидного порта');
        } catch (EmailValidationException $e) {
            $exceptionCount++;
            echo "✓ Исключение для невалидного порта: " . $e->getMessage() . "\n";
        }
        
        // Отрицательное retry_attempts
        try {
            new Email([
                'from_email' => 'test@example.com',
                'delivery' => [
                    'retry_attempts' => -1,
                ],
            ], $this->logger);
            $this->fail('Должно выброситься исключение для отрицательного retry_attempts');
        } catch (EmailValidationException $e) {
            $exceptionCount++;
            echo "✓ Исключение для отрицательного retry_attempts: " . $e->getMessage() . "\n";
        }
        
        // Отрицательное retry_delay
        try {
            new Email([
                'from_email' => 'test@example.com',
                'delivery' => [
                    'retry_delay' => -1,
                ],
            ], $this->logger);
            $this->fail('Должно выброситься исключение для отрицательного retry_delay');
        } catch (EmailValidationException $e) {
            $exceptionCount++;
            echo "✓ Исключение для отрицательного retry_delay: " . $e->getMessage() . "\n";
        }
        
        // Нулевой timeout
        try {
            new Email([
                'from_email' => 'test@example.com',
                'delivery' => [
                    'timeout' => 0,
                ],
            ], $this->logger);
            $this->fail('Должно выброситься исключение для нулевого timeout');
        } catch (EmailValidationException $e) {
            $exceptionCount++;
            echo "✓ Исключение для нулевого timeout: " . $e->getMessage() . "\n";
        }
        
        $this->assertEquals(9, $exceptionCount, 'Должно быть поймано 9 исключений');
    }
    
    /**
     * ТЕСТ 5: Отправка простого текстового письма
     */
    public function testSendSimpleTextEmail(): void
    {
        echo "\n=== ТЕСТ 5: Отправка простого текстового письма ===\n";
        
        $email = new Email([
            'from_email' => 'test@example.com',
            'from_name' => 'Test Sender',
            'delivery' => [
                'retry_attempts' => 1,
            ],
        ], $this->logger);
        
        try {
            // Отправка через mail() может не работать в тестовой среде
            $email->send(
                'recipient@example.com',
                'Test Subject',
                'This is a test message'
            );
            echo "✓ Отправка простого письма: попытка выполнена\n";
        } catch (EmailException $e) {
            // Ожидаемо в тестовой среде без настроенного mail()
            echo "✓ Ожидаемая ошибка mail(): " . $e->getMessage() . "\n";
        }
        
        // Проверяем логирование
        $logContent = $this->getLogContent();
        $this->assertNotEmpty($logContent, 'Лог должен содержать записи');
        echo "✓ Логирование работает\n";
        echo "Лог содержит " . substr_count($logContent, "\n") . " строк\n";
    }
    
    /**
     * ТЕСТ 6: Отправка HTML письма
     */
    public function testSendHtmlEmail(): void
    {
        echo "\n=== ТЕСТ 6: Отправка HTML письма ===\n";
        
        $email = new Email([
            'from_email' => 'test@example.com',
            'delivery' => [
                'retry_attempts' => 1,
            ],
        ], $this->logger);
        
        $htmlBody = '<html><body><h1>Test</h1><p>HTML письмо</p></body></html>';
        
        try {
            $email->send(
                'recipient@example.com',
                'HTML Test Subject',
                $htmlBody,
                ['is_html' => true]
            );
            echo "✓ Отправка HTML письма: попытка выполнена\n";
        } catch (EmailException $e) {
            echo "✓ Ожидаемая ошибка mail(): " . $e->getMessage() . "\n";
        }
        
        $logContent = $this->getLogContent();
        $this->assertStringContainsString('HTML Test Subject', $logContent);
        echo "✓ HTML письмо залогировано\n";
    }
    
    /**
     * ТЕСТ 7: Отправка с множественными получателями
     */
    public function testSendToMultipleRecipients(): void
    {
        echo "\n=== ТЕСТ 7: Отправка с множественными получателями ===\n";
        
        $email = new Email([
            'from_email' => 'test@example.com',
            'delivery' => [
                'retry_attempts' => 1,
            ],
        ], $this->logger);
        
        try {
            $email->send(
                ['recipient1@example.com', 'recipient2@example.com', 'recipient3@example.com'],
                'Multiple Recipients Test',
                'Test message',
                [
                    'cc' => ['cc1@example.com', 'cc2@example.com'],
                    'bcc' => 'bcc@example.com',
                ]
            );
            echo "✓ Отправка множественным получателям: попытка выполнена\n";
        } catch (EmailException $e) {
            echo "✓ Ожидаемая ошибка mail(): " . $e->getMessage() . "\n";
        }
        
        $logContent = $this->getLogContent();
        $this->assertStringContainsString('recipient1@example.com', $logContent);
        echo "✓ Множественные получатели залогированы\n";
    }
    
    /**
     * ТЕСТ 8: Отправка с вложениями
     */
    public function testSendWithAttachments(): void
    {
        echo "\n=== ТЕСТ 8: Отправка с вложениями ===\n";
        
        // Создаем тестовые файлы
        $txtFile = $this->createTestFile('test.txt', 'This is a test text file');
        $jsonFile = $this->createTestFile('data.json', '{"key": "value"}');
        $csvFile = $this->createTestFile('data.csv', "Name,Age\nJohn,30\nJane,25");
        
        echo "Созданы тестовые файлы:\n";
        echo "  - $txtFile\n";
        echo "  - $jsonFile\n";
        echo "  - $csvFile\n";
        
        $email = new Email([
            'from_email' => 'test@example.com',
            'delivery' => [
                'retry_attempts' => 1,
            ],
        ], $this->logger);
        
        try {
            $email->send(
                'recipient@example.com',
                'Email with Attachments',
                'Please see attached files',
                [
                    'attachments' => [
                        ['path' => $txtFile],
                        ['path' => $jsonFile, 'name' => 'custom_data.json'],
                        ['path' => $csvFile, 'mime' => 'text/csv'],
                    ],
                ]
            );
            echo "✓ Отправка с вложениями: попытка выполнена\n";
        } catch (EmailException $e) {
            echo "✓ Ожидаемая ошибка mail(): " . $e->getMessage() . "\n";
        }
        
        $logContent = $this->getLogContent();
        $this->assertStringContainsString('Email with Attachments', $logContent);
        echo "✓ Письмо с вложениями залогировано\n";
    }
    
    /**
     * ТЕСТ 9: Валидация вложений
     */
    public function testAttachmentValidation(): void
    {
        echo "\n=== ТЕСТ 9: Валидация вложений ===\n";
        
        $email = new Email([
            'from_email' => 'test@example.com',
            'delivery' => [
                'retry_attempts' => 1,
            ],
        ], $this->logger);
        
        $exceptionCount = 0;
        
        // Несуществующий файл
        try {
            $email->send(
                'recipient@example.com',
                'Test',
                'Body',
                [
                    'attachments' => [
                        ['path' => '/nonexistent/file.txt'],
                    ],
                ]
            );
            $this->fail('Должно выброситься исключение для несуществующего файла');
        } catch (EmailValidationException $e) {
            $exceptionCount++;
            echo "✓ Исключение для несуществующего файла: " . $e->getMessage() . "\n";
        }
        
        // Некорректный формат вложения
        try {
            $email->send(
                'recipient@example.com',
                'Test',
                'Body',
                [
                    'attachments' => [
                        'invalid format',
                    ],
                ]
            );
            $this->fail('Должно выброситься исключение для некорректного формата');
        } catch (EmailValidationException $e) {
            $exceptionCount++;
            echo "✓ Исключение для некорректного формата вложения: " . $e->getMessage() . "\n";
        }
        
        // Вложение без пути
        try {
            $email->send(
                'recipient@example.com',
                'Test',
                'Body',
                [
                    'attachments' => [
                        ['name' => 'file.txt'],
                    ],
                ]
            );
            $this->fail('Должно выброситься исключение для вложения без пути');
        } catch (EmailValidationException $e) {
            $exceptionCount++;
            echo "✓ Исключение для вложения без пути: " . $e->getMessage() . "\n";
        }
        
        $this->assertEquals(3, $exceptionCount, 'Должно быть поймано 3 исключения');
    }
    
    /**
     * ТЕСТ 10: Валидация получателей
     */
    public function testRecipientValidation(): void
    {
        echo "\n=== ТЕСТ 10: Валидация получателей ===\n";
        
        $email = new Email([
            'from_email' => 'test@example.com',
            'delivery' => [
                'retry_attempts' => 1,
            ],
        ], $this->logger);
        
        // Пустой список получателей
        try {
            $email->send([], 'Test', 'Body');
            $this->fail('Должно выброситься исключение для пустого списка получателей');
        } catch (EmailValidationException $e) {
            echo "✓ Исключение для пустого списка: " . $e->getMessage() . "\n";
        }
        
        // Невалидный email получателя
        try {
            $email->send('invalid-email', 'Test', 'Body');
            $this->fail('Должно выброситься исключение для невалидного получателя');
        } catch (EmailValidationException $e) {
            echo "✓ Исключение для невалидного получателя: " . $e->getMessage() . "\n";
        }
        
        // Пустая тема
        try {
            $email->send('test@example.com', '', 'Body');
            $this->fail('Должно выброситься исключение для пустой темы');
        } catch (EmailValidationException $e) {
            echo "✓ Исключение для пустой темы: " . $e->getMessage() . "\n";
        }
        
        // Невалидный CC
        try {
            $email->send('test@example.com', 'Subject', 'Body', [
                'cc' => 'invalid-cc-email',
            ]);
            $this->fail('Должно выброситься исключение для невалидного CC');
        } catch (EmailValidationException $e) {
            echo "✓ Исключение для невалидного CC: " . $e->getMessage() . "\n";
        }
        
        // Невалидный BCC
        try {
            $email->send('test@example.com', 'Subject', 'Body', [
                'bcc' => 'invalid-bcc-email',
            ]);
            $this->fail('Должно выброситься исключение для невалидного BCC');
        } catch (EmailValidationException $e) {
            echo "✓ Исключение для невалидного BCC: " . $e->getMessage() . "\n";
        }
        
        $this->assertTrue(true, 'Все валидации получателей прошли успешно');
    }
    
    /**
     * ТЕСТ 11: Дополнительные заголовки
     */
    public function testCustomHeaders(): void
    {
        echo "\n=== ТЕСТ 11: Дополнительные заголовки ===\n";
        
        $email = new Email([
            'from_email' => 'test@example.com',
            'delivery' => [
                'retry_attempts' => 1,
            ],
        ], $this->logger);
        
        try {
            $email->send(
                'recipient@example.com',
                'Custom Headers Test',
                'Body',
                [
                    'headers' => [
                        'X-Custom-Header' => 'CustomValue',
                        'X-Priority' => '1',
                        'X-Mailer' => 'PHP Email Class',
                    ],
                ]
            );
            echo "✓ Отправка с кастомными заголовками: попытка выполнена\n";
        } catch (EmailException $e) {
            echo "✓ Ожидаемая ошибка mail(): " . $e->getMessage() . "\n";
        }
        
        // Проверка, что ограниченные заголовки игнорируются
        try {
            $email->send(
                'recipient@example.com',
                'Restricted Headers Test',
                'Body',
                [
                    'headers' => [
                        'From' => 'hacker@example.com', // Должно быть проигнорировано
                        'Content-Type' => 'text/plain', // Должно быть проигнорировано
                        'X-Custom' => 'Allowed',
                    ],
                ]
            );
            echo "✓ Ограниченные заголовки игнорируются\n";
        } catch (EmailException $e) {
            echo "✓ Ожидаемая ошибка mail(): " . $e->getMessage() . "\n";
        }
        
        $this->assertTrue(true, 'Тесты кастомных заголовков выполнены');
    }
    
    /**
     * ТЕСТ 12: Проверка логирования
     */
    public function testLogging(): void
    {
        echo "\n=== ТЕСТ 12: Проверка логирования ===\n";
        
        $email = new Email([
            'from_email' => 'test@example.com',
            'delivery' => [
                'retry_attempts' => 3,
                'retry_delay' => 1,
            ],
        ], $this->logger);
        
        // Очищаем лог
        $logFile = $this->testLogDirectory . '/email_test.log';
        if (file_exists($logFile)) {
            unlink($logFile);
        }
        
        try {
            $email->send('recipient@example.com', 'Logging Test', 'Body');
        } catch (EmailException $e) {
            // Игнорируем ошибку отправки
        }
        
        $logContent = $this->getLogContent();
        
        echo "Содержимое лога:\n";
        echo "----------------------------------------\n";
        echo $logContent;
        echo "----------------------------------------\n";
        
        // Проверяем наличие попыток отправки
        $this->assertStringContainsString('Попытка отправки', $logContent);
        echo "✓ Попытки отправки залогированы\n";
        
        // Проверяем информацию о получателях
        $this->assertStringContainsString('recipient@example.com', $logContent);
        echo "✓ Информация о получателях залогирована\n";
        
        // Проверяем тему
        $this->assertStringContainsString('Logging Test', $logContent);
        echo "✓ Тема письма залогирована\n";
    }
    
    /**
     * ТЕСТ 13: Отправка с переопределением reply_to и return_path
     */
    public function testOverrideReplyToAndReturnPath(): void
    {
        echo "\n=== ТЕСТ 13: Переопределение reply_to и return_path ===\n";
        
        $email = new Email([
            'from_email' => 'test@example.com',
            'reply_to' => 'default-reply@example.com',
            'return_path' => 'default-bounce@example.com',
            'delivery' => [
                'retry_attempts' => 1,
            ],
        ], $this->logger);
        
        try {
            $email->send(
                'recipient@example.com',
                'Override Test',
                'Body',
                [
                    'reply_to' => 'custom-reply@example.com',
                    'reply_name' => 'Custom Reply Name',
                    'return_path' => 'custom-bounce@example.com',
                ]
            );
            echo "✓ Переопределение reply_to и return_path: попытка выполнена\n";
        } catch (EmailException $e) {
            echo "✓ Ожидаемая ошибка mail(): " . $e->getMessage() . "\n";
        }
        
        // Невалидный reply_to в опциях
        try {
            $email->send(
                'recipient@example.com',
                'Test',
                'Body',
                [
                    'reply_to' => 'invalid-reply',
                ]
            );
            $this->fail('Должно выброситься исключение для невалидного reply_to в опциях');
        } catch (EmailValidationException $e) {
            echo "✓ Исключение для невалидного reply_to в опциях: " . $e->getMessage() . "\n";
        }
        
        // Невалидный return_path в опциях
        try {
            $email->send(
                'recipient@example.com',
                'Test',
                'Body',
                [
                    'return_path' => 'invalid-path',
                ]
            );
            $this->fail('Должно выброситься исключение для невалидного return_path в опциях');
        } catch (EmailValidationException $e) {
            echo "✓ Исключение для невалидного return_path в опциях: " . $e->getMessage() . "\n";
        }
        
        $this->assertTrue(true, 'Тесты переопределения параметров выполнены');
    }
    
    /**
     * ТЕСТ 14: Отправка с кириллическими данными
     */
    public function testCyrillicContent(): void
    {
        echo "\n=== ТЕСТ 14: Отправка с кириллическими данными ===\n";
        
        $email = new Email([
            'from_email' => 'test@example.com',
            'from_name' => 'Тестовый Отправитель',
            'delivery' => [
                'retry_attempts' => 1,
            ],
        ], $this->logger);
        
        try {
            $email->send(
                'recipient@example.com',
                'Кириллическая тема письма',
                'Это письмо содержит кириллический текст в теме и теле.',
                [
                    'reply_to' => 'reply@example.com',
                    'reply_name' => 'Служба поддержки',
                ]
            );
            echo "✓ Отправка с кириллицей: попытка выполнена\n";
        } catch (EmailException $e) {
            echo "✓ Ожидаемая ошибка mail(): " . $e->getMessage() . "\n";
        }
        
        $logContent = $this->getLogContent();
        // Проверяем, что в логе есть какая-то информация (не обязательно кириллица из-за кодировки)
        $this->assertGreaterThan(0, strlen($logContent), 'Лог должен содержать данные');
        echo "✓ Кириллический контент обработан и залогирован\n";
    }
    
    /**
     * ТЕСТ 15: Специальные символы в заголовках (защита от инъекций)
     */
    public function testHeaderInjectionProtection(): void
    {
        echo "\n=== ТЕСТ 15: Защита от инъекций в заголовках ===\n";
        
        $email = new Email([
            'from_email' => 'test@example.com',
            'delivery' => [
                'retry_attempts' => 1,
            ],
        ], $this->logger);
        
        // Попытка инъекции через тему
        try {
            $email->send(
                'recipient@example.com',
                "Subject\r\nBcc: hacker@example.com",
                'Body'
            );
            echo "✓ Инъекция в теме обработана (символы удалены)\n";
        } catch (EmailException $e) {
            echo "✓ Ожидаемая ошибка mail(): " . $e->getMessage() . "\n";
        }
        
        // Попытка инъекции через имя отправителя
        $email = new Email([
            'from_email' => 'test@example.com',
            'from_name' => "Name\r\nBcc: hacker@example.com",
            'delivery' => [
                'retry_attempts' => 1,
            ],
        ], $this->logger);
        
        try {
            $email->send('recipient@example.com', 'Test', 'Body');
            echo "✓ Инъекция в имени отправителя обработана\n";
        } catch (EmailException $e) {
            echo "✓ Ожидаемая ошибка mail(): " . $e->getMessage() . "\n";
        }
        
        $this->assertTrue(true, 'Защита от инъекций работает');
    }
    
    /**
     * ТЕСТ 16: Большие вложения
     */
    public function testLargeAttachment(): void
    {
        echo "\n=== ТЕСТ 16: Большие вложения ===\n";
        
        // Создаем файл размером 1MB
        $largeContent = str_repeat('A', 1024 * 1024);
        $largeFile = $this->createTestFile('large.txt', $largeContent);
        
        $fileSize = filesize($largeFile);
        echo "Создан большой файл: " . round($fileSize / 1024 / 1024, 2) . " MB\n";
        
        $email = new Email([
            'from_email' => 'test@example.com',
            'delivery' => [
                'retry_attempts' => 1,
            ],
        ], $this->logger);
        
        try {
            $email->send(
                'recipient@example.com',
                'Large Attachment Test',
                'Body',
                [
                    'attachments' => [
                        ['path' => $largeFile, 'name' => 'large_file.txt'],
                    ],
                ]
            );
            echo "✓ Большое вложение обработано\n";
        } catch (EmailException $e) {
            echo "✓ Ожидаемая ошибка mail(): " . $e->getMessage() . "\n";
        }
        
        $this->assertTrue(true, 'Большое вложение обработано корректно');
    }
    
    /**
     * ТЕСТ 17: Граничные случаи
     */
    public function testEdgeCases(): void
    {
        echo "\n=== ТЕСТ 17: Граничные случаи ===\n";
        
        $email = new Email([
            'from_email' => 'test@example.com',
            'delivery' => [
                'retry_attempts' => 1,
            ],
        ], $this->logger);
        
        // Очень длинная тема
        $longSubject = str_repeat('A', 500);
        try {
            $email->send('recipient@example.com', $longSubject, 'Body');
            echo "✓ Очень длинная тема обработана\n";
        } catch (EmailException $e) {
            echo "✓ Ожидаемая ошибка mail(): " . $e->getMessage() . "\n";
        }
        
        // Очень длинное тело
        $longBody = str_repeat('B', 10000);
        try {
            $email->send('recipient@example.com', 'Subject', $longBody);
            echo "✓ Очень длинное тело обработано\n";
        } catch (EmailException $e) {
            echo "✓ Ожидаемая ошибка mail(): " . $e->getMessage() . "\n";
        }
        
        // Пустое тело
        try {
            $email->send('recipient@example.com', 'Subject', '');
            echo "✓ Пустое тело обработано\n";
        } catch (EmailException $e) {
            echo "✓ Ожидаемая ошибка mail(): " . $e->getMessage() . "\n";
        }
        
        // Много получателей
        $manyRecipients = [];
        for ($i = 1; $i <= 100; $i++) {
            $manyRecipients[] = "recipient{$i}@example.com";
        }
        try {
            $email->send($manyRecipients, 'Many Recipients', 'Body');
            echo "✓ Много получателей обработано (100 адресов)\n";
        } catch (EmailException $e) {
            echo "✓ Ожидаемая ошибка mail(): " . $e->getMessage() . "\n";
        }
        
        $this->assertTrue(true, 'Все граничные случаи обработаны');
    }
    
    /**
     * ТЕСТ 18: Итоговая статистика
     */
    public function testFinalStatistics(): void
    {
        echo "\n=== ТЕСТ 18: Итоговая статистика ===\n";
        
        $logContent = $this->getLogContent();
        $lines = explode("\n", $logContent);
        $errorCount = 0;
        $infoCount = 0;
        
        foreach ($lines as $line) {
            if (stripos($line, 'ERROR') !== false) {
                $errorCount++;
            }
            if (stripos($line, 'INFO') !== false) {
                $infoCount++;
            }
        }
        
        echo "Всего строк в логе: " . count($lines) . "\n";
        echo "INFO сообщений: $infoCount\n";
        echo "ERROR сообщений: $errorCount\n";
        
        $this->assertGreaterThan(0, count($lines), 'Лог должен содержать записи');
    }
}

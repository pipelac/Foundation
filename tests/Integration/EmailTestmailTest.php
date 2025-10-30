<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Component\Email;
use App\Component\Logger;
use App\Component\Exception\EmailException;
use App\Component\Exception\EmailValidationException;
use PHPUnit\Framework\TestCase;
use Exception;

/**
 * Интеграционные тесты для класса Email с использованием testmail.app
 * 
 * Тесты реальной отправки писем через testmail.app:
 * - Простые текстовые письма
 * - HTML письма
 * - Множественные получатели (to, cc, bcc)
 * - Письма с вложениями
 * - Пользовательские заголовки
 * - Reply-To функциональность
 * - Проверка доставки через API testmail.app
 * 
 * Для запуска тестов необходимо:
 * 1. Зарегистрироваться на https://testmail.app
 * 2. Получить API key и namespace
 * 3. Установить переменные окружения:
 *    - TESTMAIL_NAMESPACE - ваш namespace
 *    - TESTMAIL_API_KEY - ваш API ключ
 * 
 * Запуск: phpunit tests/Integration/EmailTestmailTest.php
 */
class EmailTestmailTest extends TestCase
{
    private ?string $testmailNamespace;
    private ?string $testmailApiKey;
    private string $testLogDirectory;
    private bool $skipTests = false;
    
    /**
     * Настройка окружения перед каждым тестом
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Получаем креды из переменных окружения
        $this->testmailNamespace = getenv('TESTMAIL_NAMESPACE') ?: null;
        $this->testmailApiKey = getenv('TESTMAIL_API_KEY') ?: null;
        
        // Если креды не установлены, пропускаем тесты
        if ($this->testmailNamespace === null || $this->testmailApiKey === null) {
            $this->skipTests = true;
            $this->markTestSkipped(
                'Тесты пропущены: установите TESTMAIL_NAMESPACE и TESTMAIL_API_KEY для запуска интеграционных тестов с testmail.app'
            );
        }
        
        $this->testLogDirectory = sys_get_temp_dir() . '/email_testmail_test_' . uniqid();
        mkdir($this->testLogDirectory, 0777, true);
        
        // Небольшая задержка между тестами для testmail.app
        sleep(1);
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
     * Генерирует уникальный email адрес для testmail.app
     */
    private function generateTestEmail(string $tag = 'test'): string
    {
        $unique = uniqid('', true);
        return "{$this->testmailNamespace}.{$tag}-{$unique}@inbox.testmail.app";
    }
    
    /**
     * Проверяет письма через API testmail.app
     * 
     * @param string $tag Тег для поиска писем
     * @param int $timeout Таймаут ожидания в секундах
     * @return array|null Массив писем или null
     */
    private function checkEmails(string $tag, int $timeout = 30): ?array
    {
        $startTime = time();
        
        while ((time() - $startTime) < $timeout) {
            $ch = curl_init();
            
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://api.testmail.app/api/json?apikey={$this->testmailApiKey}&namespace={$this->testmailNamespace}&tag={$tag}&limit=10",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $response !== false) {
                $data = json_decode($response, true);
                if (isset($data['emails']) && count($data['emails']) > 0) {
                    return $data['emails'];
                }
            }
            
            // Ждём 2 секунды перед следующей проверкой
            sleep(2);
        }
        
        return null;
    }
    
    /**
     * Создаёт Email инстанс с конфигурацией testmail.app
     */
    private function createEmailInstance(): Email
    {
        $logger = new Logger([
            'directory' => $this->testLogDirectory,
        ]);
        
        $config = [
            'from_email' => 'test@example.com',
            'from_name' => 'Test Sender',
            'smtp' => [
                'host' => 'smtp.testmail.app',
                'port' => 587,
                'encryption' => 'tls',
                'username' => $this->testmailNamespace,
                'password' => $this->testmailApiKey,
            ],
            'delivery' => [
                'retry_attempts' => 3,
                'retry_delay' => 2,
                'timeout' => 30,
            ],
        ];
        
        return new Email($config, $logger);
    }
    
    /**
     * Тест: Отправка простого текстового письма
     */
    public function testSendSimpleTextEmail(): void
    {
        $email = $this->createEmailInstance();
        $tag = 'simple-text';
        $recipient = $this->generateTestEmail($tag);
        $subject = 'Test Simple Text Email';
        $body = 'This is a simple text email for testing purposes.';
        
        // Отправляем письмо
        $email->send($recipient, $subject, $body, ['is_html' => false]);
        
        // Проверяем доставку
        $emails = $this->checkEmails($tag);
        
        $this->assertNotNull($emails, 'Письмо не было получено через testmail.app API');
        $this->assertCount(1, $emails, 'Должно быть получено ровно одно письмо');
        
        $receivedEmail = $emails[0];
        $this->assertEquals($subject, $receivedEmail['subject'], 'Тема письма не совпадает');
        $this->assertStringContainsString($body, $receivedEmail['text'], 'Текст письма не найден');
    }
    
    /**
     * Тест: Отправка HTML письма
     */
    public function testSendHtmlEmail(): void
    {
        $email = $this->createEmailInstance();
        $tag = 'html-email';
        $recipient = $this->generateTestEmail($tag);
        $subject = 'Test HTML Email';
        $body = '<html><body><h1>Hello</h1><p>This is an <strong>HTML</strong> email.</p></body></html>';
        
        // Отправляем HTML письмо
        $email->send($recipient, $subject, $body, ['is_html' => true]);
        
        // Проверяем доставку
        $emails = $this->checkEmails($tag);
        
        $this->assertNotNull($emails, 'HTML письмо не было получено');
        $this->assertCount(1, $emails);
        
        $receivedEmail = $emails[0];
        $this->assertEquals($subject, $receivedEmail['subject']);
        $this->assertStringContainsString('Hello', $receivedEmail['html'], 'HTML контент не найден');
        $this->assertStringContainsString('<strong>HTML</strong>', $receivedEmail['html']);
    }
    
    /**
     * Тест: Отправка письма с кириллицей
     */
    public function testSendEmailWithCyrillic(): void
    {
        $email = $this->createEmailInstance();
        $tag = 'cyrillic';
        $recipient = $this->generateTestEmail($tag);
        $subject = 'Тестовое письмо с кириллицей';
        $body = 'Привет! Это письмо на русском языке с кириллическими символами.';
        
        $email->send($recipient, $subject, $body, ['is_html' => false]);
        
        $emails = $this->checkEmails($tag);
        
        $this->assertNotNull($emails, 'Письмо с кириллицей не было получено');
        $this->assertCount(1, $emails);
        
        $receivedEmail = $emails[0];
        $this->assertStringContainsString('Тестовое письмо', $receivedEmail['subject']);
        $this->assertStringContainsString('Привет', $receivedEmail['text']);
    }
    
    /**
     * Тест: Отправка письма множественным получателям
     */
    public function testSendEmailToMultipleRecipients(): void
    {
        $email = $this->createEmailInstance();
        $tag = 'multiple-recipients';
        
        $recipients = [
            $this->generateTestEmail($tag),
            $this->generateTestEmail($tag),
        ];
        
        $subject = 'Test Multiple Recipients';
        $body = 'This email is sent to multiple recipients.';
        
        $email->send($recipients, $subject, $body, ['is_html' => false]);
        
        // Ждём чуть дольше для множественных получателей
        sleep(3);
        
        $emails = $this->checkEmails($tag);
        
        $this->assertNotNull($emails, 'Письма не были получены');
        $this->assertGreaterThanOrEqual(1, count($emails), 'Должно быть получено минимум одно письмо');
    }
    
    /**
     * Тест: Отправка письма с CC
     */
    public function testSendEmailWithCc(): void
    {
        $email = $this->createEmailInstance();
        $tag = 'with-cc';
        
        $recipient = $this->generateTestEmail($tag);
        $ccRecipient = $this->generateTestEmail($tag);
        
        $subject = 'Test Email with CC';
        $body = 'This email includes a CC recipient.';
        
        $email->send(
            $recipient,
            $subject,
            $body,
            [
                'is_html' => false,
                'cc' => $ccRecipient,
            ]
        );
        
        sleep(3);
        
        $emails = $this->checkEmails($tag);
        
        $this->assertNotNull($emails, 'Письма не были получены');
        $this->assertGreaterThanOrEqual(1, count($emails));
    }
    
    /**
     * Тест: Отправка письма с вложением
     */
    public function testSendEmailWithAttachment(): void
    {
        $email = $this->createEmailInstance();
        $tag = 'with-attachment';
        $recipient = $this->generateTestEmail($tag);
        
        // Создаём временный файл для вложения
        $attachmentPath = sys_get_temp_dir() . '/test_attachment_' . uniqid() . '.txt';
        file_put_contents($attachmentPath, 'This is test attachment content.');
        
        try {
            $subject = 'Test Email with Attachment';
            $body = 'This email contains an attachment.';
            
            $email->send(
                $recipient,
                $subject,
                $body,
                [
                    'is_html' => false,
                    'attachments' => [
                        [
                            'path' => $attachmentPath,
                            'name' => 'test_file.txt',
                            'mime' => 'text/plain',
                        ],
                    ],
                ]
            );
            
            $emails = $this->checkEmails($tag);
            
            $this->assertNotNull($emails, 'Письмо с вложением не было получено');
            $this->assertCount(1, $emails);
            
            $receivedEmail = $emails[0];
            $this->assertEquals($subject, $receivedEmail['subject']);
            $this->assertNotEmpty($receivedEmail['attachments'] ?? [], 'Вложения не найдены');
            
        } finally {
            if (file_exists($attachmentPath)) {
                unlink($attachmentPath);
            }
        }
    }
    
    /**
     * Тест: Отправка письма с Reply-To
     */
    public function testSendEmailWithReplyTo(): void
    {
        $email = $this->createEmailInstance();
        $tag = 'with-reply-to';
        $recipient = $this->generateTestEmail($tag);
        $replyTo = 'reply@example.com';
        
        $subject = 'Test Email with Reply-To';
        $body = 'This email has a custom Reply-To address.';
        
        $email->send(
            $recipient,
            $subject,
            $body,
            [
                'is_html' => false,
                'reply_to' => $replyTo,
                'reply_name' => 'Reply Test',
            ]
        );
        
        $emails = $this->checkEmails($tag);
        
        $this->assertNotNull($emails, 'Письмо с Reply-To не было получено');
        $this->assertCount(1, $emails);
        
        $receivedEmail = $emails[0];
        $this->assertEquals($subject, $receivedEmail['subject']);
        
        // Проверяем наличие Reply-To в заголовках
        $headers = $receivedEmail['headers'] ?? [];
        $replyToFound = false;
        foreach ($headers as $header) {
            if (isset($header['Reply-To']) && strpos($header['Reply-To'], $replyTo) !== false) {
                $replyToFound = true;
                break;
            }
        }
        
        $this->assertTrue($replyToFound, 'Reply-To заголовок не найден или некорректен');
    }
    
    /**
     * Тест: Отправка письма с пользовательскими заголовками
     */
    public function testSendEmailWithCustomHeaders(): void
    {
        $email = $this->createEmailInstance();
        $tag = 'custom-headers';
        $recipient = $this->generateTestEmail($tag);
        
        $subject = 'Test Email with Custom Headers';
        $body = 'This email includes custom headers.';
        
        $email->send(
            $recipient,
            $subject,
            $body,
            [
                'is_html' => false,
                'headers' => [
                    'X-Custom-Header' => 'CustomValue',
                    'X-Priority' => '1',
                ],
            ]
        );
        
        $emails = $this->checkEmails($tag);
        
        $this->assertNotNull($emails, 'Письмо с пользовательскими заголовками не было получено');
        $this->assertCount(1, $emails);
        
        $receivedEmail = $emails[0];
        $this->assertEquals($subject, $receivedEmail['subject']);
    }
    
    /**
     * Тест: Отправка большого HTML письма
     */
    public function testSendLargeHtmlEmail(): void
    {
        $email = $this->createEmailInstance();
        $tag = 'large-html';
        $recipient = $this->generateTestEmail($tag);
        
        $subject = 'Test Large HTML Email';
        
        // Создаём большой HTML контент
        $body = '<html><body>';
        $body .= '<h1>Large HTML Email Test</h1>';
        $body .= '<p>This is a test of sending a large HTML email.</p>';
        
        for ($i = 0; $i < 50; $i++) {
            $body .= "<p>Paragraph {$i}: Lorem ipsum dolor sit amet, consectetur adipiscing elit. 
                     Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>";
        }
        
        $body .= '</body></html>';
        
        $email->send($recipient, $subject, $body, ['is_html' => true]);
        
        $emails = $this->checkEmails($tag);
        
        $this->assertNotNull($emails, 'Большое HTML письмо не было получено');
        $this->assertCount(1, $emails);
        
        $receivedEmail = $emails[0];
        $this->assertEquals($subject, $receivedEmail['subject']);
        $this->assertStringContainsString('Large HTML Email Test', $receivedEmail['html']);
    }
    
    /**
     * Тест: Проверка валидации некорректного получателя
     */
    public function testValidationOfInvalidRecipient(): void
    {
        $email = $this->createEmailInstance();
        
        $this->expectException(EmailValidationException::class);
        
        $email->send(
            'invalid-email-address',
            'Test Subject',
            'Test Body',
            ['is_html' => false]
        );
    }
    
    /**
     * Тест: Проверка отправки с пустой темой
     */
    public function testSendEmailWithEmptySubject(): void
    {
        $email = $this->createEmailInstance();
        $recipient = $this->generateTestEmail('empty-subject');
        
        $this->expectException(EmailValidationException::class);
        $this->expectExceptionMessageMatches('/Тема письма/');
        
        $email->send($recipient, '', 'Test Body', ['is_html' => false]);
    }
    
    /**
     * Тест: Отправка письма с BCC
     */
    public function testSendEmailWithBcc(): void
    {
        $email = $this->createEmailInstance();
        $tag = 'with-bcc';
        
        $recipient = $this->generateTestEmail($tag);
        $bccRecipient = $this->generateTestEmail($tag);
        
        $subject = 'Test Email with BCC';
        $body = 'This email includes a BCC recipient.';
        
        $email->send(
            $recipient,
            $subject,
            $body,
            [
                'is_html' => false,
                'bcc' => $bccRecipient,
            ]
        );
        
        sleep(3);
        
        $emails = $this->checkEmails($tag);
        
        $this->assertNotNull($emails, 'Письма не были получены');
        $this->assertGreaterThanOrEqual(1, count($emails));
    }
    
    /**
     * Тест: Отправка письма со специальными символами в теме
     */
    public function testSendEmailWithSpecialCharactersInSubject(): void
    {
        $email = $this->createEmailInstance();
        $tag = 'special-chars';
        $recipient = $this->generateTestEmail($tag);
        
        $subject = 'Test: Special Chars & Symbols <> "Quotes" \'Apostrophes\'';
        $body = 'Email with special characters in subject.';
        
        $email->send($recipient, $subject, $body, ['is_html' => false]);
        
        $emails = $this->checkEmails($tag);
        
        $this->assertNotNull($emails, 'Письмо со спецсимволами не было получено');
        $this->assertCount(1, $emails);
    }
    
    /**
     * Тест: Отправка HTML письма с изображениями (base64)
     */
    public function testSendHtmlEmailWithImages(): void
    {
        $email = $this->createEmailInstance();
        $tag = 'html-images';
        $recipient = $this->generateTestEmail($tag);
        
        $subject = 'Test HTML Email with Images';
        
        // Создаём простой base64 image (1x1 прозрачный PNG)
        $base64Image = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        
        $body = '<html><body>';
        $body .= '<h1>HTML Email with Image</h1>';
        $body .= '<p>Below is an embedded image:</p>';
        $body .= '<img src="data:image/png;base64,' . $base64Image . '" alt="Test Image" />';
        $body .= '</body></html>';
        
        $email->send($recipient, $subject, $body, ['is_html' => true]);
        
        $emails = $this->checkEmails($tag);
        
        $this->assertNotNull($emails, 'HTML письмо с изображениями не было получено');
        $this->assertCount(1, $emails);
        
        $receivedEmail = $emails[0];
        $this->assertEquals($subject, $receivedEmail['subject']);
        $this->assertStringContainsString('img', $receivedEmail['html']);
    }
    
    /**
     * Тест: Проверка логирования при отправке
     */
    public function testEmailSendingLogsMessages(): void
    {
        $email = $this->createEmailInstance();
        $tag = 'logging-test';
        $recipient = $this->generateTestEmail($tag);
        
        $subject = 'Test Logging';
        $body = 'This email tests logging functionality.';
        
        $email->send($recipient, $subject, $body, ['is_html' => false]);
        
        // Проверяем, что лог-файл создан
        $logFiles = glob($this->testLogDirectory . '/*.log');
        
        $this->assertNotEmpty($logFiles, 'Лог-файл не был создан');
        
        $logContent = file_get_contents($logFiles[0]);
        $this->assertStringContainsString('Письмо успешно отправлено', $logContent);
    }
    
    /**
     * Тест: Производительность - отправка нескольких писем подряд
     */
    public function testSendMultipleEmailsPerformance(): void
    {
        $email = $this->createEmailInstance();
        $tag = 'performance';
        $emailCount = 3;
        
        $startTime = microtime(true);
        
        for ($i = 0; $i < $emailCount; $i++) {
            $recipient = $this->generateTestEmail($tag);
            $subject = "Performance Test Email #{$i}";
            $body = "This is performance test email number {$i}.";
            
            $email->send($recipient, $subject, $body, ['is_html' => false]);
        }
        
        $executionTime = microtime(true) - $startTime;
        
        // Проверяем, что все письма отправлены за разумное время (< 30 секунд для 3 писем)
        $this->assertLessThan(30, $executionTime, 'Отправка писем заняла слишком много времени');
        
        sleep(5);
        
        $emails = $this->checkEmails($tag);
        $this->assertNotNull($emails, 'Письма не были получены');
        $this->assertGreaterThanOrEqual(1, count($emails));
    }
}

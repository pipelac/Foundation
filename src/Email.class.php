<?php

declare(strict_types=1);

namespace App\Component;

use App\Component\Exception\EmailException;
use App\Component\Exception\EmailValidationException;
use DateTimeImmutable;
use Exception;
use Throwable;

/**
 * Класс для отправки электронных писем с поддержкой SMTP, вложений и HTML контента
 * 
 * Поддерживает:
 * - Отправку через SMTP или функцию mail()
 * - Механизм повторных попыток при сбоях
 * - Настройку таймаутов
 * - Вложения и HTML контент
 * - Множественных получателей (to, cc, bcc)
 */
class Email
{
    private string $fromEmail;
    private ?string $fromName;
    private ?string $replyTo;
    private ?string $replyName;
    private ?string $returnPath;
    private string $charset;
    private ?Logger $logger;
    
    private ?string $smtpHost;
    private ?int $smtpPort;
    private ?string $smtpEncryption;
    private ?string $smtpUsername;
    private ?string $smtpPassword;
    
    private int $retryAttempts;
    private int $retryDelay;
    private int $timeout;
    
    private const DEFAULT_RETRY_ATTEMPTS = 3;
    private const DEFAULT_RETRY_DELAY = 5;
    private const DEFAULT_TIMEOUT = 30;
    private const DEFAULT_SMTP_PORT = 587;
    private const DEFAULT_CHARSET = 'UTF-8';
    
    private const SMTP_RESPONSE_TIMEOUT = 5;
    private const CRLF = "\r\n";

    /**
     * Создает экземпляр почтового компонента
     *
     * @param array<string, mixed> $config Параметры почтового компонента
     * @param Logger|null $logger Инстанс логгера для записи ошибок
     * @throws EmailValidationException При некорректных параметрах конфигурации
     */
    public function __construct(array $config = [], ?Logger $logger = null)
    {
        $this->logger = $logger;
        
        $this->validateAndSetBasicConfig($config);
        $this->validateAndSetSmtpConfig($config);
        $this->validateAndSetDeliveryConfig($config);
    }

    /**
     * Отправляет электронное письмо указанным получателям
     *
     * @param string|array<int, string> $recipients Список e-mail адресов получателей
     * @param string $subject Тема письма
     * @param string $body Текст письма
     * @param array{
     *     is_html?: bool,
     *     cc?: string|array<int, string>,
     *     bcc?: string|array<int, string>,
     *     reply_to?: string,
     *     reply_name?: string,
     *     return_path?: string,
     *     attachments?: array<int, array{path: string, name?: string, mime?: string}>,
     *     headers?: array<string, string>
     * } $options Дополнительные параметры отправки
     * @throws EmailValidationException При некорректных параметрах
     * @throws EmailException При ошибке отправки
     */
    public function send(string|array $recipients, string $subject, string $body, array $options = []): void
    {
        $toRecipients = $this->normalizeRecipients($recipients);
        $ccRecipients = isset($options['cc']) ? $this->normalizeOptionalRecipients($options['cc']) : [];
        $bccRecipients = isset($options['bcc']) ? $this->normalizeOptionalRecipients($options['bcc']) : [];

        $replyTo = $this->resolveReplyTo($options['reply_to'] ?? null);
        $replyName = $this->resolveReplyName($options['reply_name'] ?? null);
        $returnPath = $this->resolveReturnPath($options['return_path'] ?? null);

        $isHtml = (bool)($options['is_html'] ?? false);
        $attachments = isset($options['attachments']) ? $this->normalizeAttachments($options['attachments']) : [];
        $extraHeaders = isset($options['headers']) && is_array($options['headers']) ? $options['headers'] : [];

        $subject = $this->sanitizeHeaderValue($subject);
        if ($subject === '') {
            throw new EmailValidationException('Тема письма не может быть пустой.');
        }

        $lastException = null;
        
        for ($attempt = 1; $attempt <= $this->retryAttempts; $attempt++) {
            try {
                if ($this->isSmtpConfigured()) {
                    $this->sendViaSmtp(
                        $toRecipients,
                        $ccRecipients,
                        $bccRecipients,
                        $subject,
                        $body,
                        $isHtml,
                        $attachments,
                        $replyTo,
                        $replyName,
                        $returnPath,
                        $extraHeaders
                    );
                } else {
                    $this->sendViaMailFunction(
                        $toRecipients,
                        $ccRecipients,
                        $bccRecipients,
                        $subject,
                        $body,
                        $isHtml,
                        $attachments,
                        $replyTo,
                        $replyName,
                        $returnPath,
                        $extraHeaders
                    );
                }
                
                $this->logInfo('Письмо успешно отправлено', [
                    'recipients' => implode(', ', $toRecipients),
                    'subject' => $subject,
                    'attempt' => $attempt,
                ]);
                
                return;
                
            } catch (Throwable $exception) {
                $lastException = $exception;
                
                $this->logError('Попытка отправки письма не удалась', [
                    'attempt' => $attempt,
                    'max_attempts' => $this->retryAttempts,
                    'recipients' => implode(', ', $toRecipients),
                    'subject' => $subject,
                    'exception' => $exception->getMessage(),
                ]);
                
                if ($attempt < $this->retryAttempts) {
                    $delay = $this->retryDelay * $attempt;
                    $this->logInfo("Повторная попытка через {$delay} секунд", [
                        'attempt' => $attempt + 1,
                        'delay' => $delay,
                    ]);
                    sleep($delay);
                }
            }
        }
        
        throw new EmailException(
            'Не удалось отправить письмо после ' . $this->retryAttempts . ' попыток. Последняя ошибка: ' . 
            ($lastException ? $lastException->getMessage() : 'Неизвестная ошибка'),
            0,
            $lastException
        );
    }

    /**
     * Валидирует и устанавливает базовую конфигурацию
     *
     * @param array<string, mixed> $config
     * @throws EmailValidationException
     */
    private function validateAndSetBasicConfig(array $config): void
    {
        $fromEmail = (string)($config['from_email'] ?? '');
        if ($fromEmail === '' || !$this->isValidEmail($fromEmail)) {
            throw new EmailValidationException('Не указан корректный адрес отправителя.');
        }
        $this->fromEmail = $this->sanitizeEmail($fromEmail);

        $fromName = isset($config['from_name']) ? trim((string)$config['from_name']) : null;
        $this->fromName = $fromName !== '' ? $fromName : null;

        $replyTo = isset($config['reply_to']) ? (string)$config['reply_to'] : null;
        if ($replyTo !== null && $replyTo !== '') {
            if (!$this->isValidEmail($replyTo)) {
                throw new EmailValidationException('Некорректный адрес reply-to.');
            }
            $this->replyTo = $this->sanitizeEmail($replyTo);
        } else {
            $this->replyTo = null;
        }
        
        $replyName = isset($config['reply_name']) ? trim((string)$config['reply_name']) : null;
        $this->replyName = $replyName !== '' ? $replyName : null;

        $returnPath = isset($config['return_path']) ? (string)$config['return_path'] : $this->fromEmail;
        if ($returnPath !== '') {
            if (!$this->isValidEmail($returnPath)) {
                throw new EmailValidationException('Некорректный адрес return-path.');
            }
            $this->returnPath = $this->sanitizeEmail($returnPath);
        } else {
            $this->returnPath = null;
        }

        $charset = (string)($config['charset'] ?? self::DEFAULT_CHARSET);
        $this->charset = $charset === '' ? self::DEFAULT_CHARSET : strtoupper($charset);
    }

    /**
     * Валидирует и устанавливает SMTP конфигурацию
     *
     * @param array<string, mixed> $config
     * @throws EmailValidationException
     */
    private function validateAndSetSmtpConfig(array $config): void
    {
        $smtpConfig = $config['smtp'] ?? [];
        
        if (!is_array($smtpConfig) || empty($smtpConfig)) {
            $this->smtpHost = null;
            $this->smtpPort = null;
            $this->smtpEncryption = null;
            $this->smtpUsername = null;
            $this->smtpPassword = null;
            return;
        }

        $host = isset($smtpConfig['host']) ? trim((string)$smtpConfig['host']) : null;
        if ($host === null || $host === '') {
            $this->smtpHost = null;
            $this->smtpPort = null;
            $this->smtpEncryption = null;
            $this->smtpUsername = null;
            $this->smtpPassword = null;
            return;
        }
        $this->smtpHost = $host;

        $port = $smtpConfig['port'] ?? self::DEFAULT_SMTP_PORT;
        if (!is_int($port) && !is_string($port)) {
            throw new EmailValidationException('SMTP порт должен быть числом.');
        }
        $portInt = (int)$port;
        if ($portInt < 1 || $portInt > 65535) {
            throw new EmailValidationException('SMTP порт должен быть в диапазоне 1-65535.');
        }
        $this->smtpPort = $portInt;

        $encryption = isset($smtpConfig['encryption']) ? strtolower(trim((string)$smtpConfig['encryption'])) : null;
        if ($encryption !== null && $encryption !== '' && !in_array($encryption, ['tls', 'ssl', 'starttls'], true)) {
            throw new EmailValidationException('SMTP encryption должен быть: tls, ssl или starttls.');
        }
        $this->smtpEncryption = $encryption !== '' ? $encryption : null;

        $username = isset($smtpConfig['username']) ? trim((string)$smtpConfig['username']) : null;
        $this->smtpUsername = $username !== '' ? $username : null;

        $password = isset($smtpConfig['password']) ? (string)$smtpConfig['password'] : null;
        $this->smtpPassword = $password;
    }

    /**
     * Валидирует и устанавливает конфигурацию доставки
     *
     * @param array<string, mixed> $config
     * @throws EmailValidationException
     */
    private function validateAndSetDeliveryConfig(array $config): void
    {
        $deliveryConfig = $config['delivery'] ?? [];
        
        if (!is_array($deliveryConfig)) {
            $deliveryConfig = [];
        }

        $retryAttempts = $deliveryConfig['retry_attempts'] ?? self::DEFAULT_RETRY_ATTEMPTS;
        if (!is_int($retryAttempts) && !is_string($retryAttempts)) {
            throw new EmailValidationException('Параметр retry_attempts должен быть числом.');
        }
        $retryAttempts = (int)$retryAttempts;
        if ($retryAttempts < 1) {
            throw new EmailValidationException('Параметр retry_attempts должен быть больше 0.');
        }
        $this->retryAttempts = $retryAttempts;

        $retryDelay = $deliveryConfig['retry_delay'] ?? self::DEFAULT_RETRY_DELAY;
        if (!is_int($retryDelay) && !is_string($retryDelay)) {
            throw new EmailValidationException('Параметр retry_delay должен быть числом.');
        }
        $retryDelay = (int)$retryDelay;
        if ($retryDelay < 0) {
            throw new EmailValidationException('Параметр retry_delay не может быть отрицательным.');
        }
        $this->retryDelay = $retryDelay;

        $timeout = $deliveryConfig['timeout'] ?? self::DEFAULT_TIMEOUT;
        if (!is_int($timeout) && !is_string($timeout)) {
            throw new EmailValidationException('Параметр timeout должен быть числом.');
        }
        $timeout = (int)$timeout;
        if ($timeout < 1) {
            throw new EmailValidationException('Параметр timeout должен быть больше 0.');
        }
        $this->timeout = $timeout;
    }

    /**
     * Проверяет, настроен ли SMTP
     */
    private function isSmtpConfigured(): bool
    {
        return $this->smtpHost !== null;
    }

    /**
     * Отправляет письмо через SMTP
     *
     * @param array<int, string> $toRecipients
     * @param array<int, string> $ccRecipients
     * @param array<int, string> $bccRecipients
     * @param array<int, array{name: string, mime: string, content: string}> $attachments
     * @param array<string, string> $extraHeaders
     * @throws EmailException
     */
    private function sendViaSmtp(
        array $toRecipients,
        array $ccRecipients,
        array $bccRecipients,
        string $subject,
        string $body,
        bool $isHtml,
        array $attachments,
        ?string $replyTo,
        ?string $replyName,
        ?string $returnPath,
        array $extraHeaders
    ): void {
        $socket = $this->connectToSmtp();
        
        try {
            $this->smtpCommand($socket, null, '220');
            
            $hostname = gethostname() ?: 'localhost';
            $this->smtpCommand($socket, "EHLO {$hostname}", '250');
            
            if ($this->smtpEncryption === 'tls' || $this->smtpEncryption === 'starttls') {
                $this->smtpCommand($socket, 'STARTTLS', '220');
                
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new EmailException('Не удалось включить TLS шифрование.');
                }
                
                $this->smtpCommand($socket, "EHLO {$hostname}", '250');
            }
            
            if ($this->smtpUsername !== null && $this->smtpPassword !== null) {
                $this->smtpAuthenticate($socket);
            }
            
            $fromEmail = $returnPath ?? $this->fromEmail;
            $this->smtpCommand($socket, "MAIL FROM:<{$fromEmail}>", '250');
            
            $allRecipients = array_merge($toRecipients, $ccRecipients, $bccRecipients);
            foreach ($allRecipients as $recipient) {
                $this->smtpCommand($socket, "RCPT TO:<{$recipient}>", '250');
            }
            
            $this->smtpCommand($socket, 'DATA', '354');
            
            $emailContent = $this->buildEmailContent(
                $toRecipients,
                $ccRecipients,
                $subject,
                $body,
                $isHtml,
                $attachments,
                $replyTo,
                $replyName,
                $extraHeaders
            );
            
            fwrite($socket, $emailContent . self::CRLF . '.' . self::CRLF);
            $this->readSmtpResponse($socket, '250');
            
            $this->smtpCommand($socket, 'QUIT', '221');
            
        } finally {
            fclose($socket);
        }
    }

    /**
     * Устанавливает соединение с SMTP сервером
     *
     * @return resource
     * @throws EmailException
     */
    private function connectToSmtp()
    {
        $host = $this->smtpHost;
        $port = $this->smtpPort;
        
        if ($this->smtpEncryption === 'ssl') {
            $host = 'ssl://' . $host;
        }
        
        $errno = 0;
        $errstr = '';
        
        $socket = @fsockopen($host, $port, $errno, $errstr, $this->timeout);
        
        if ($socket === false) {
            throw new EmailException(
                "Не удалось подключиться к SMTP серверу {$this->smtpHost}:{$this->smtpPort}. " .
                "Ошибка [{$errno}]: {$errstr}"
            );
        }
        
        stream_set_timeout($socket, self::SMTP_RESPONSE_TIMEOUT);
        
        return $socket;
    }

    /**
     * Выполняет аутентификацию на SMTP сервере
     *
     * @param resource $socket
     * @throws EmailException
     */
    private function smtpAuthenticate($socket): void
    {
        $this->smtpCommand($socket, 'AUTH LOGIN', '334');
        $this->smtpCommand($socket, base64_encode($this->smtpUsername), '334');
        $this->smtpCommand($socket, base64_encode($this->smtpPassword), '235');
    }

    /**
     * Отправляет команду SMTP и проверяет ответ
     *
     * @param resource $socket
     * @throws EmailException
     */
    private function smtpCommand($socket, ?string $command, string $expectedCode): void
    {
        if ($command !== null) {
            fwrite($socket, $command . self::CRLF);
        }
        
        $this->readSmtpResponse($socket, $expectedCode);
    }

    /**
     * Читает и проверяет ответ SMTP сервера
     *
     * @param resource $socket
     * @throws EmailException
     */
    private function readSmtpResponse($socket, string $expectedCode): string
    {
        $response = '';
        
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        
        $info = stream_get_meta_data($socket);
        if ($info['timed_out']) {
            throw new EmailException('Таймаут при ожидании ответа от SMTP сервера.');
        }
        
        if ($response === '') {
            throw new EmailException('Пустой ответ от SMTP сервера.');
        }
        
        $code = substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new EmailException(
                "SMTP ошибка. Ожидался код {$expectedCode}, получен: {$response}"
            );
        }
        
        return $response;
    }

    /**
     * Строит полное содержимое email для SMTP отправки
     *
     * @param array<int, string> $toRecipients
     * @param array<int, string> $ccRecipients
     * @param array<int, array{name: string, mime: string, content: string}> $attachments
     * @param array<string, string> $extraHeaders
     */
    private function buildEmailContent(
        array $toRecipients,
        array $ccRecipients,
        string $subject,
        string $body,
        bool $isHtml,
        array $attachments,
        ?string $replyTo,
        ?string $replyName,
        array $extraHeaders
    ): string {
        $headers = [];
        
        $headers[] = 'From: ' . $this->formatAddress($this->fromEmail, $this->fromName);
        $headers[] = 'To: ' . implode(', ', $toRecipients);
        
        if ($ccRecipients !== []) {
            $headers[] = 'Cc: ' . implode(', ', $ccRecipients);
        }
        
        if ($replyTo !== null) {
            $headers[] = 'Reply-To: ' . $this->formatAddress($replyTo, $replyName);
        }
        
        $headers[] = 'Subject: ' . $this->encodeHeaderValue($subject);
        $headers[] = 'Date: ' . (new DateTimeImmutable())->format(DateTimeImmutable::RFC2822);
        $headers[] = 'Message-ID: ' . $this->generateMessageId();
        $headers[] = 'MIME-Version: 1.0';
        
        foreach ($extraHeaders as $name => $value) {
            $normalizedName = $this->normalizeHeaderName($name);
            if ($normalizedName === '' || $this->isRestrictedHeader($normalizedName)) {
                continue;
            }
            $headers[] = $normalizedName . ': ' . $this->sanitizeHeaderValue((string)$value);
        }
        
        $normalizedBody = $this->normalizeLineEndings($body);
        
        if ($attachments === []) {
            $headers[] = 'Content-Type: ' . ($isHtml ? 'text/html' : 'text/plain') . '; charset=' . $this->charset;
            $headers[] = 'Content-Transfer-Encoding: quoted-printable';
            
            return implode(self::CRLF, $headers) . self::CRLF . self::CRLF . 
                   $this->encodeBody($normalizedBody);
        }
        
        $boundary = $this->generateBoundary();
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
        
        $content = implode(self::CRLF, $headers) . self::CRLF . self::CRLF;
        
        $content .= '--' . $boundary . self::CRLF;
        $content .= 'Content-Type: ' . ($isHtml ? 'text/html' : 'text/plain') . '; charset=' . $this->charset . self::CRLF;
        $content .= 'Content-Transfer-Encoding: quoted-printable' . self::CRLF . self::CRLF;
        $content .= $this->encodeBody($normalizedBody) . self::CRLF;
        
        foreach ($attachments as $attachment) {
            $fileName = $attachment['name'];
            $safeFileName = $this->sanitizeParameterValue($fileName);
            $encodedFileName = rawurlencode($fileName);
            
            $content .= '--' . $boundary . self::CRLF;
            $content .= 'Content-Type: ' . $attachment['mime'] . '; name="' . $safeFileName . '"' . self::CRLF;
            $content .= 'Content-Disposition: attachment; filename="' . $safeFileName . '"; filename*=UTF-8\'\'' . $encodedFileName . self::CRLF;
            $content .= 'Content-Transfer-Encoding: base64' . self::CRLF . self::CRLF;
            $content .= chunk_split(base64_encode($attachment['content'])) . self::CRLF;
        }
        
        $content .= '--' . $boundary . '--';
        
        return $content;
    }

    /**
     * Отправляет письмо через функцию mail()
     *
     * @param array<int, string> $toRecipients
     * @param array<int, string> $ccRecipients
     * @param array<int, string> $bccRecipients
     * @param array<int, array{name: string, mime: string, content: string}> $attachments
     * @param array<string, string> $extraHeaders
     * @throws EmailException
     */
    private function sendViaMailFunction(
        array $toRecipients,
        array $ccRecipients,
        array $bccRecipients,
        string $subject,
        string $body,
        bool $isHtml,
        array $attachments,
        ?string $replyTo,
        ?string $replyName,
        ?string $returnPath,
        array $extraHeaders
    ): void {
        $headers = $this->buildBaseHeaders($replyTo, $replyName, $ccRecipients, $bccRecipients, $extraHeaders);
        [$preparedBody, $contentHeaders] = $this->buildBody($body, $isHtml, $attachments);
        $headers = array_merge($headers, $contentHeaders);

        $encodedSubject = $this->encodeHeaderValue($subject);
        $headersString = implode(self::CRLF, $headers);
        $recipientsString = implode(', ', $toRecipients);

        $result = false;
        $lastError = null;

        $errorHandler = static function (int $severity, string $message) use (&$lastError): bool {
            $lastError = $message;
            return true;
        };

        set_error_handler($errorHandler);

        try {
            if ($returnPath !== null) {
                $result = mail($recipientsString, $encodedSubject, $preparedBody, $headersString, '-f' . $returnPath);
            } else {
                $result = mail($recipientsString, $encodedSubject, $preparedBody, $headersString);
            }
        } catch (Throwable $exception) {
            restore_error_handler();
            throw new EmailException('Исключение при отправке письма: ' . $exception->getMessage(), 0, $exception);
        }

        restore_error_handler();

        if ($result === false) {
            $message = 'Не удалось отправить письмо через функцию mail().';
            if ($lastError !== null) {
                $message .= ' ' . $lastError;
            }
            throw new EmailException($message);
        }
    }

    /**
     * Нормализует список получателей
     *
     * @param string|array<int, string> $recipients
     * @return array<int, string>
     * @throws EmailValidationException
     */
    private function normalizeRecipients(string|array $recipients): array
    {
        $list = is_array($recipients) ? $recipients : [$recipients];
        $normalized = [];

        foreach ($list as $recipient) {
            $value = trim((string)$recipient);
            if ($value === '') {
                continue;
            }

            if (!$this->isValidEmail($value)) {
                throw new EmailValidationException('Некорректный адрес получателя: ' . $recipient);
            }

            $normalized[] = $this->sanitizeEmail($value);
        }

        if ($normalized === []) {
            throw new EmailValidationException('Список получателей пуст.');
        }

        return $normalized;
    }

    /**
     * Нормализует опциональный список получателей
     *
     * @param string|array<int, string> $recipients
     * @return array<int, string>
     * @throws EmailValidationException
     */
    private function normalizeOptionalRecipients(string|array $recipients): array
    {
        if (is_string($recipients)) {
            $trimmed = trim($recipients);
            if ($trimmed === '') {
                return [];
            }
            return $this->normalizeRecipients($trimmed);
        }

        $prepared = [];
        foreach ($recipients as $value) {
            $trimmed = trim((string)$value);
            if ($trimmed !== '') {
                $prepared[] = $trimmed;
            }
        }

        if ($prepared === []) {
            return [];
        }

        return $this->normalizeRecipients($prepared);
    }

    /**
     * Определяет адрес Reply-To
     */
    private function resolveReplyTo(?string $replyTo): ?string
    {
        if ($replyTo === null || $replyTo === '') {
            return $this->replyTo;
        }

        if (!$this->isValidEmail($replyTo)) {
            throw new EmailValidationException('Некорректный адрес reply-to.');
        }

        return $this->sanitizeEmail($replyTo);
    }

    /**
     * Определяет имя Reply-To
     */
    private function resolveReplyName(?string $replyName): ?string
    {
        if ($replyName === null || $replyName === '') {
            return $this->replyName;
        }

        $trimmed = trim($replyName);
        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * Определяет адрес Return-Path
     */
    private function resolveReturnPath(?string $returnPath): ?string
    {
        if ($returnPath === null || $returnPath === '') {
            return $this->returnPath;
        }

        if (!$this->isValidEmail($returnPath)) {
            throw new EmailValidationException('Некорректный адрес return-path.');
        }

        return $this->sanitizeEmail($returnPath);
    }

    /**
     * Нормализует и валидирует вложения
     *
     * @param array<int, array{path: string, name?: string, mime?: string}> $attachments
     * @return array<int, array{name: string, mime: string, content: string}>
     * @throws EmailValidationException|RuntimeException
     */
    private function normalizeAttachments(array $attachments): array
    {
        $normalized = [];

        foreach ($attachments as $index => $attachment) {
            if (!is_array($attachment)) {
                throw new EmailValidationException('Вложение #' . $index . ' имеет некорректный формат.');
            }

            if (!isset($attachment['path'])) {
                throw new EmailValidationException('Для вложения #' . $index . ' не указан путь к файлу.');
            }

            $path = (string)$attachment['path'];
            if ($path === '' || !is_file($path) || !is_readable($path)) {
                throw new EmailValidationException('Файл вложения #' . $index . ' недоступен: ' . $path);
            }

            $content = file_get_contents($path);
            if ($content === false) {
                throw new EmailException('Не удалось прочитать файл вложения: ' . $path);
            }

            $name = isset($attachment['name']) && $attachment['name'] !== ''
                ? (string)$attachment['name']
                : basename($path);
            $mime = isset($attachment['mime']) && $attachment['mime'] !== ''
                ? (string)$attachment['mime']
                : $this->detectMimeType($path);

            $normalized[] = [
                'name' => $name,
                'mime' => $mime,
                'content' => $content,
            ];
        }

        return $normalized;
    }

    /**
     * Строит базовые заголовки письма для функции mail()
     *
     * @param array<int, string> $cc
     * @param array<int, string> $bcc
     * @param array<string, string> $extraHeaders
     * @return array<int, string>
     */
    private function buildBaseHeaders(
        ?string $replyTo,
        ?string $replyName,
        array $cc,
        array $bcc,
        array $extraHeaders
    ): array {
        $headers = [];
        $headers[] = 'From: ' . $this->formatAddress($this->fromEmail, $this->fromName);
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Date: ' . (new DateTimeImmutable())->format(DateTimeImmutable::RFC2822);
        $headers[] = 'Message-ID: ' . $this->generateMessageId();

        if ($replyTo !== null) {
            $headers[] = 'Reply-To: ' . $this->formatAddress($replyTo, $replyName);
        }

        if ($cc !== []) {
            $headers[] = 'Cc: ' . implode(', ', $cc);
        }

        if ($bcc !== []) {
            $headers[] = 'Bcc: ' . implode(', ', $bcc);
        }

        foreach ($extraHeaders as $name => $value) {
            $normalizedName = $this->normalizeHeaderName($name);
            if ($normalizedName === '' || $this->isRestrictedHeader($normalizedName)) {
                continue;
            }
            $headers[] = $normalizedName . ': ' . $this->sanitizeHeaderValue((string)$value);
        }

        return $headers;
    }

    /**
     * Строит тело письма с вложениями для функции mail()
     *
     * @param array<int, array{name: string, mime: string, content: string}> $attachments
     * @return array{0: string, 1: array<int, string>}
     */
    private function buildBody(string $body, bool $isHtml, array $attachments): array
    {
        $normalizedBody = $this->normalizeLineEndings($body);

        if ($attachments === []) {
            $headers = [
                'Content-Type: ' . ($isHtml ? 'text/html' : 'text/plain') . '; charset=' . $this->charset,
                'Content-Transfer-Encoding: quoted-printable',
            ];
            return [$this->encodeBody($normalizedBody), $headers];
        }

        $boundary = $this->generateBoundary();
        $headers = [
            'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
        ];

        $message = '--' . $boundary . self::CRLF;
        $message .= 'Content-Type: ' . ($isHtml ? 'text/html' : 'text/plain') . '; charset=' . $this->charset . self::CRLF;
        $message .= 'Content-Transfer-Encoding: quoted-printable' . self::CRLF . self::CRLF;
        $message .= $this->encodeBody($normalizedBody) . self::CRLF;

        foreach ($attachments as $attachment) {
            $fileName = $attachment['name'];
            $safeFileName = $this->sanitizeParameterValue($fileName);
            $encodedFileName = rawurlencode($fileName);

            $message .= '--' . $boundary . self::CRLF;
            $message .= 'Content-Type: ' . $attachment['mime'] . '; name="' . $safeFileName . '"' . self::CRLF;
            $message .= 'Content-Disposition: attachment; filename="' . $safeFileName . '"; filename*=UTF-8\'\'' . $encodedFileName . self::CRLF;
            $message .= 'Content-Transfer-Encoding: base64' . self::CRLF . self::CRLF;
            $message .= chunk_split(base64_encode($attachment['content'])) . self::CRLF;
        }

        $message .= '--' . $boundary . '--';

        return [$message, $headers];
    }

    /**
     * Кодирует тело письма в quoted-printable
     */
    private function encodeBody(string $body): string
    {
        return quoted_printable_encode($body);
    }

    /**
     * Очищает email адрес от опасных символов
     */
    private function sanitizeEmail(string $email): string
    {
        return str_replace(["\r", "\n", "\0"], '', trim($email));
    }

    /**
     * Очищает значение заголовка от опасных символов
     */
    private function sanitizeHeaderValue(string $value): string
    {
        $clean = str_replace(["\r", "\n", "\0"], ' ', trim($value));
        return preg_replace('/\s+/', ' ', $clean) ?? '';
    }

    /**
     * Нормализует имя заголовка
     */
    private function normalizeHeaderName(string $name): string
    {
        $clean = preg_replace('/[^A-Za-z0-9\-]/', '', $name) ?? '';
        return strtolower($clean) === '' ? '' : $this->capitalizeHeaderName($clean);
    }

    /**
     * Приводит имя заголовка к правильному регистру
     */
    private function capitalizeHeaderName(string $name): string
    {
        $parts = explode('-', strtolower($name));
        $parts = array_map(static fn (string $part): string => ucfirst($part), $parts);
        return implode('-', $parts);
    }

    /**
     * Проверяет, является ли заголовок ограниченным для переопределения
     */
    private function isRestrictedHeader(string $headerName): bool
    {
        $restricted = [
            'from',
            'to',
            'subject',
            'reply-to',
            'cc',
            'bcc',
            'content-type',
            'content-transfer-encoding',
            'mime-version',
            'date',
            'message-id',
        ];

        return in_array(strtolower($headerName), $restricted, true);
    }

    /**
     * Форматирует адрес с именем
     */
    private function formatAddress(string $email, ?string $name = null): string
    {
        if ($name === null || $name === '') {
            return $email;
        }

        return $this->encodeHeaderValue($name) . ' <' . $email . '>';
    }

    /**
     * Кодирует значение заголовка для поддержки Unicode
     */
    private function encodeHeaderValue(string $value): string
    {
        if (function_exists('mb_encode_mimeheader')) {
            return mb_encode_mimeheader($value, $this->charset, 'B', self::CRLF);
        }

        return '=?' . $this->charset . '?B?' . base64_encode($value) . '?=';
    }

    /**
     * Очищает значение параметра от опасных символов
     */
    private function sanitizeParameterValue(string $value): string
    {
        $clean = str_replace(['"', '\\', "\r", "\n", "\0"], '', $value);
        return $clean === '' ? 'attachment' : $clean;
    }

    /**
     * Генерирует уникальную границу для multipart сообщений
     */
    private function generateBoundary(): string
    {
        try {
            return 'mix_' . bin2hex(random_bytes(16));
        } catch (Exception) {
            return 'mix_' . uniqid('', true);
        }
    }

    /**
     * Генерирует уникальный Message-ID
     */
    private function generateMessageId(): string
    {
        try {
            $unique = bin2hex(random_bytes(16));
        } catch (Exception) {
            $unique = uniqid('', true);
        }

        $host = gethostname() ?: 'localhost';
        $host = $this->sanitizeParameterValue($host);
        if ($host === '' || $host === 'attachment') {
            $host = 'localhost';
        }

        return '<' . $unique . '@' . $host . '>';
    }

    /**
     * Нормализует окончания строк к CRLF
     */
    private function normalizeLineEndings(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        return str_replace("\n", self::CRLF, $value);
    }

    /**
     * Определяет MIME тип файла
     */
    private function detectMimeType(string $path): string
    {
        if (function_exists('mime_content_type')) {
            $type = mime_content_type($path);
            if ($type !== false) {
                return $type;
            }
        }

        return 'application/octet-stream';
    }

    /**
     * Логирует ошибку
     *
     * @param array<string, mixed> $context
     */
    private function logError(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->error($message, $context);
        }
    }

    /**
     * Логирует информационное сообщение
     *
     * @param array<string, mixed> $context
     */
    private function logInfo(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->info($message, $context);
        }
    }

    /**
     * Проверяет валидность email адреса с поддержкой IDN доменов
     *
     * @param string $email Email адрес для проверки
     * @return bool True если адрес валиден, иначе false
     */
    private function isValidEmail(string $email): bool
    {
        // Базовая проверка через filter_var
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return true;
        }

        // Попытка обработать IDN домен (кириллические домены)
        if (function_exists('idn_to_ascii')) {
            // Разбиваем email на локальную часть и домен
            $parts = explode('@', $email);
            if (count($parts) === 2) {
                [$local, $domain] = $parts;
                
                // Конвертируем домен в ASCII (Punycode)
                $asciiDomain = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
                
                if ($asciiDomain !== false) {
                    $asciiEmail = $local . '@' . $asciiDomain;
                    return filter_var($asciiEmail, FILTER_VALIDATE_EMAIL) !== false;
                }
            }
        }

        return false;
    }
}

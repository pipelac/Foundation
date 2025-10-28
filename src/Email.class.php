<?php

declare(strict_types=1);

namespace App\Component;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Класс для отправки электронных писем с поддержкой вложений и HTML контента
 */
class Email
{
    private string $fromEmail;
    private ?string $fromName;
    private ?string $replyTo;
    private ?string $returnPath;
    private string $charset;
    private ?Logger $logger;

    /**
     * @param array<string, mixed> $config Параметры почтового компонента
     * @param Logger|null $logger Инстанс логгера для записи ошибок
     */
    public function __construct(array $config = [], ?Logger $logger = null)
    {
        $fromEmail = (string)($config['from_email'] ?? '');
        if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Не указан корректный адрес отправителя.');
        }

        $this->fromEmail = $this->sanitizeEmail($fromEmail);

        $fromName = isset($config['from_name']) ? trim((string)$config['from_name']) : null;
        $this->fromName = $fromName !== '' ? $fromName : null;

        $replyTo = isset($config['reply_to']) ? (string)$config['reply_to'] : null;
        if ($replyTo !== null && $replyTo !== '') {
            if (!filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Некорректный адрес reply-to.');
            }

            $this->replyTo = $this->sanitizeEmail($replyTo);
        } else {
            $this->replyTo = null;
        }

        $returnPath = isset($config['return_path']) ? (string)$config['return_path'] : $this->fromEmail;
        if ($returnPath !== '') {
            if (!filter_var($returnPath, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Некорректный адрес return-path.');
            }

            $this->returnPath = $this->sanitizeEmail($returnPath);
        } else {
            $this->returnPath = null;
        }

        $charset = (string)($config['charset'] ?? 'UTF-8');
        $charset = $charset === '' ? 'UTF-8' : strtoupper($charset);
        $this->charset = $charset;

        $this->logger = $logger;
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
     *     return_path?: string,
     *     attachments?: array<int, array{path: string, name?: string, mime?: string}>,
     *     headers?: array<string, string>
     * } $options Дополнительные параметры отправки
     */
    public function send(string|array $recipients, string $subject, string $body, array $options = []): void
    {
        $toRecipients = $this->normalizeRecipients($recipients);
        $ccRecipients = isset($options['cc']) ? $this->normalizeOptionalRecipients($options['cc']) : [];
        $bccRecipients = isset($options['bcc']) ? $this->normalizeOptionalRecipients($options['bcc']) : [];

        $replyTo = $this->resolveReplyTo($options['reply_to'] ?? null);
        $returnPath = $this->resolveReturnPath($options['return_path'] ?? null);

        $isHtml = (bool)($options['is_html'] ?? false);
        $attachments = isset($options['attachments']) ? $this->normalizeAttachments($options['attachments']) : [];
        $extraHeaders = isset($options['headers']) && is_array($options['headers']) ? $options['headers'] : [];

        $headers = $this->buildBaseHeaders($replyTo, $ccRecipients, $bccRecipients, $extraHeaders);
        [$preparedBody, $contentHeaders] = $this->buildBody($body, $isHtml, $attachments);
        $headers = array_merge($headers, $contentHeaders);

        $subject = $this->sanitizeHeaderValue($subject);
        if ($subject === '') {
            throw new InvalidArgumentException('Тема письма не может быть пустой.');
        }

        $encodedSubject = $this->encodeHeaderValue($subject);
        $headersString = implode("\r\n", $headers);
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
            $this->logError('Исключение при отправке письма', [
                'recipients' => $recipientsString,
                'subject' => $subject,
                'exception' => $exception->getMessage(),
            ]);

            throw new RuntimeException('Исключение при отправке письма: ' . $exception->getMessage(), 0, $exception);
        }

        restore_error_handler();

        if ($result === false) {
            $context = [
                'recipients' => $recipientsString,
                'subject' => $subject,
            ];

            if ($lastError !== null) {
                $context['error'] = $lastError;
            }

            $this->logError('Не удалось отправить письмо', $context);

            $message = 'Не удалось отправить письмо.';
            if ($lastError !== null) {
                $message .= ' ' . $lastError;
            }

            throw new RuntimeException($message);
        }
    }

    /**
     * @param string|array<int, string> $recipients
     * @return array<int, string>
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

            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Некорректный адрес получателя: ' . $recipient);
            }

            $normalized[] = $this->sanitizeEmail($value);
        }

        if ($normalized === []) {
            throw new InvalidArgumentException('Список получателей пуст.');
        }

        return $normalized;
    }

    /**
     * @param string|array<int, string> $recipients
     * @return array<int, string>
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

    private function resolveReplyTo(?string $replyTo): ?string
    {
        if ($replyTo === null || $replyTo === '') {
            return $this->replyTo;
        }

        if (!filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Некорректный адрес reply-to.');
        }

        return $this->sanitizeEmail($replyTo);
    }

    private function resolveReturnPath(?string $returnPath): ?string
    {
        if ($returnPath === null || $returnPath === '') {
            return $this->returnPath;
        }

        if (!filter_var($returnPath, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Некорректный адрес return-path.');
        }

        return $this->sanitizeEmail($returnPath);
    }

    /**
     * @param array<int, array{path: string, name?: string, mime?: string}> $attachments
     * @return array<int, array{name: string, mime: string, content: string}>
     */
    private function normalizeAttachments(array $attachments): array
    {
        $normalized = [];

        foreach ($attachments as $index => $attachment) {
            if (!is_array($attachment)) {
                throw new InvalidArgumentException('Вложение #' . $index . ' имеет некорректный формат.');
            }

            if (!isset($attachment['path'])) {
                throw new InvalidArgumentException('Для вложения #' . $index . ' не указан путь к файлу.');
            }

            $path = (string)$attachment['path'];
            if ($path === '' || !is_file($path) || !is_readable($path)) {
                throw new InvalidArgumentException('Файл вложения #' . $index . ' недоступен: ' . $path);
            }

            $content = file_get_contents($path);
            if ($content === false) {
                throw new RuntimeException('Не удалось прочитать файл вложения: ' . $path);
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
     * @param array<int, string> $cc
     * @param array<int, string> $bcc
     * @param array<string, string> $extraHeaders
     * @return array<int, string>
     */
    private function buildBaseHeaders(?string $replyTo, array $cc, array $bcc, array $extraHeaders): array
    {
        $headers = [];
        $headers[] = 'From: ' . $this->formatAddress($this->fromEmail, $this->fromName);
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Date: ' . (new DateTimeImmutable())->format(DateTimeImmutable::RFC2822);
        $headers[] = 'Message-ID: ' . $this->generateMessageId();

        if ($replyTo !== null) {
            $headers[] = 'Reply-To: ' . $replyTo;
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

        $message = '--' . $boundary . "\r\n";
        $message .= 'Content-Type: ' . ($isHtml ? 'text/html' : 'text/plain') . '; charset=' . $this->charset . "\r\n";
        $message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $message .= $this->encodeBody($normalizedBody) . "\r\n";

        foreach ($attachments as $attachment) {
            $fileName = $attachment['name'];
            $safeFileName = $this->sanitizeParameterValue($fileName);
            $encodedFileName = rawurlencode($fileName);

            $message .= '--' . $boundary . "\r\n";
            $message .= 'Content-Type: ' . $attachment['mime'] . '; name="' . $safeFileName . '"' . "\r\n";
            $message .= 'Content-Disposition: attachment; filename="' . $safeFileName . '"; filename*=UTF-8\'\'' . $encodedFileName . "\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $message .= chunk_split(base64_encode($attachment['content'])) . "\r\n";
        }

        $message .= '--' . $boundary . "--";

        return [$message, $headers];
    }

    private function encodeBody(string $body): string
    {
        return quoted_printable_encode($body);
    }

    private function sanitizeEmail(string $email): string
    {
        return str_replace(["\r", "\n"], '', trim($email));
    }

    private function sanitizeHeaderValue(string $value): string
    {
        $clean = str_replace(["\r", "\n"], ' ', trim($value));

        return preg_replace('/\s+/', ' ', $clean) ?? '';
    }

    private function normalizeHeaderName(string $name): string
    {
        $clean = preg_replace('/[^A-Za-z0-9\-]/', '', $name) ?? '';

        return strtolower($clean) === '' ? '' : $this->capitalizeHeaderName($clean);
    }

    private function capitalizeHeaderName(string $name): string
    {
        $parts = explode('-', strtolower($name));
        $parts = array_map(static fn (string $part): string => ucfirst($part), $parts);

        return implode('-', $parts);
    }

    private function isRestrictedHeader(string $headerName): bool
    {
        $restricted = [
            'from',
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

    private function formatAddress(string $email, ?string $name = null): string
    {
        if ($name === null || $name === '') {
            return $email;
        }

        return $this->encodeHeaderValue($name) . ' <' . $email . '>';
    }

    private function encodeHeaderValue(string $value): string
    {
        if (function_exists('mb_encode_mimeheader')) {
            return mb_encode_mimeheader($value, $this->charset, 'B', "\r\n");
        }

        return '=?' . $this->charset . '?B?' . base64_encode($value) . '?=';
    }

    private function sanitizeParameterValue(string $value): string
    {
        $clean = str_replace(['"', '\\'], '', $value);

        return $clean === '' ? 'attachment' : $clean;
    }

    private function generateBoundary(): string
    {
        try {
            return 'mix_' . bin2hex(random_bytes(16));
        } catch (Exception) {
            return 'mix_' . uniqid('', true);
        }
    }

    private function generateMessageId(): string
    {
        try {
            $unique = bin2hex(random_bytes(16));
        } catch (Exception) {
            $unique = uniqid('', true);
        }

        $host = gethostname() ?: 'localhost';
        $host = $this->sanitizeParameterValue($host);
        if ($host === '') {
            $host = 'localhost';
        }

        return '<' . $unique . '@' . $host . '>';
    }

    private function normalizeLineEndings(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);

        return str_replace("\n", "\r\n", $value);
    }

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

    private function logError(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->error($message, $context);
        }
    }
}

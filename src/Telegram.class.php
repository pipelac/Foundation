<?php

declare(strict_types=1);

namespace App\Component;

use CURLFile;
use Exception;
use GuzzleHttp\Psr7\Utils;
use JsonException;
use RuntimeException;

/**
 * Класс для работы с Telegram Bot API
 */
class Telegram
{
    private const BASE_URL = 'https://api.telegram.org/bot';
    private const DEFAULT_TIMEOUT = 30;

    private string $token;
    private ?string $defaultChatId;
    private int $timeout;
    private ?Logger $logger;
    private Http $http;

    /**
     * @param array<string, mixed> $config Конфигурация Telegram API
     * @param Logger|null $logger Инстанс логгера
     * @throws Exception Если токен не указан
     */
    public function __construct(array $config, ?Logger $logger = null)
    {
        $this->token = (string)($config['token'] ?? '');
        $this->defaultChatId = isset($config['default_chat_id']) ? (string)$config['default_chat_id'] : null;
        $this->timeout = max(1, (int)($config['timeout'] ?? self::DEFAULT_TIMEOUT));
        $this->logger = $logger;

        if ($this->token === '') {
            throw new Exception('Токен Telegram бота не указан.');
        }

        $this->http = new Http([
            'base_uri' => self::BASE_URL . $this->token . '/',
            'timeout' => $this->timeout,
            'connect_timeout' => $this->timeout,
        ], $logger);
    }

    /**
     * Отправляет текстовое сообщение
     *
     * @param string|null $chatId Идентификатор чата
     * @param string $text Текст сообщения
     * @param array<string, mixed> $options Дополнительные параметры
     * @return array<string, mixed> Ответ Telegram API
     * @throws RuntimeException Если запрос завершился с ошибкой
     * @throws JsonException Если не удалось обработать JSON
     */
    public function sendText(?string $chatId, string $text, array $options = []): array
    {
        $payload = array_merge($options, [
            'chat_id' => $this->resolveChatId($chatId),
            'text' => $text,
        ]);

        return $this->sendJson('sendMessage', $payload);
    }

    /**
     * Отправляет изображение
     *
     * @param string|null $chatId Идентификатор чата
     * @param string $photo Путь к файлу или URL изображения
     * @param array<string, mixed> $options Дополнительные параметры
     * @return array<string, mixed> Ответ Telegram API
     * @throws RuntimeException Если запрос завершился с ошибкой
     * @throws JsonException Если не удалось обработать JSON
     */
    public function sendPhoto(?string $chatId, string $photo, array $options = []): array
    {
        $payload = array_merge($options, [
            'chat_id' => $this->resolveChatId($chatId),
            'photo' => $this->prepareFile($photo),
        ]);

        return $this->sendMultipart('sendPhoto', $payload);
    }

    /**
     * Отправляет видео
     *
     * @param string|null $chatId Идентификатор чата
     * @param string $video Путь к файлу или URL видео
     * @param array<string, mixed> $options Дополнительные параметры
     * @return array<string, mixed> Ответ Telegram API
     * @throws RuntimeException Если запрос завершился с ошибкой
     * @throws JsonException Если не удалось обработать JSON
     */
    public function sendVideo(?string $chatId, string $video, array $options = []): array
    {
        $payload = array_merge($options, [
            'chat_id' => $this->resolveChatId($chatId),
            'video' => $this->prepareFile($video),
        ]);

        return $this->sendMultipart('sendVideo', $payload);
    }

    /**
     * Отправляет аудиофайл
     *
     * @param string|null $chatId Идентификатор чата
     * @param string $audio Путь к файлу или URL аудио
     * @param array<string, mixed> $options Дополнительные параметры
     * @return array<string, mixed> Ответ Telegram API
     * @throws RuntimeException Если запрос завершился с ошибкой
     * @throws JsonException Если не удалось обработать JSON
     */
    public function sendAudio(?string $chatId, string $audio, array $options = []): array
    {
        $payload = array_merge($options, [
            'chat_id' => $this->resolveChatId($chatId),
            'audio' => $this->prepareFile($audio),
        ]);

        return $this->sendMultipart('sendAudio', $payload);
    }

    /**
     * Отправляет документ
     *
     * @param string|null $chatId Идентификатор чата
     * @param string $document Путь к файлу или URL документа
     * @param array<string, mixed> $options Дополнительные параметры
     * @return array<string, mixed> Ответ Telegram API
     * @throws RuntimeException Если запрос завершился с ошибкой
     * @throws JsonException Если не удалось обработать JSON
     */
    public function sendDocument(?string $chatId, string $document, array $options = []): array
    {
        $payload = array_merge($options, [
            'chat_id' => $this->resolveChatId($chatId),
            'document' => $this->prepareFile($document),
        ]);

        return $this->sendMultipart('sendDocument', $payload);
    }

    /**
     * Определяет идентификатор чата
     *
     * @param string|null $chatId Идентификатор чата
     * @return string Значение идентификатора чата
     * @throws RuntimeException Если идентификатор не задан
     */
    private function resolveChatId(?string $chatId): string
    {
        if ($chatId !== null) {
            return $chatId;
        }

        if ($this->defaultChatId === null) {
            throw new RuntimeException('Идентификатор чата не указан.');
        }

        return $this->defaultChatId;
    }

    /**
     * Подготавливает файл для отправки
     *
     * @param string $pathOrUrl Локальный путь или URL
     * @return CURLFile|string Подготовленный файл или URL
     */
    private function prepareFile(string $pathOrUrl): CURLFile|string
    {
        if (file_exists($pathOrUrl)) {
            return new CURLFile($pathOrUrl);
        }

        return $pathOrUrl;
    }

    /**
     * Выполняет JSON-запрос к Telegram API
     *
     * @param string $method Метод API
     * @param array<string, mixed> $payload Данные запроса
     * @return array<string, mixed> Ответ API
     * @throws RuntimeException Если запрос завершился с ошибкой
     * @throws JsonException Если не удалось обработать JSON
     */
    private function sendJson(string $method, array $payload): array
    {
        $response = $this->http->request('POST', $method, [
            'json' => $payload,
        ]);

        $statusCode = $response->getStatusCode();
        $body = (string)$response->getBody();

        if ($statusCode >= 400) {
            $this->logError('Telegram API вернул ошибку', ['status_code' => $statusCode, 'response' => $body]);
            throw new RuntimeException('Telegram API вернул ошибку: ' . $body);
        }

        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if (!($decoded['ok'] ?? false)) {
            $this->logError('Telegram API ответил ошибкой', ['response' => $decoded]);
            throw new RuntimeException('Telegram API ответил ошибкой.');
        }

        return $decoded;
    }

    /**
     * Выполняет multipart-запрос к Telegram API
     *
     * @param string $method Метод API
     * @param array<string, mixed> $payload Данные запроса
     * @return array<string, mixed> Ответ API
     * @throws RuntimeException Если запрос завершился с ошибкой
     * @throws JsonException Если не удалось обработать JSON ответа
     */
    private function sendMultipart(string $method, array $payload): array
    {
        $multipart = $this->prepareMultipart($payload);

        $response = $this->http->request('POST', $method, [
            'multipart' => $multipart,
        ]);

        $statusCode = $response->getStatusCode();
        $body = (string)$response->getBody();

        if ($statusCode >= 400) {
            $this->logError('Telegram API вернул ошибку', ['status_code' => $statusCode, 'response' => $body]);
            throw new RuntimeException('Telegram API вернул ошибку: ' . $body);
        }

        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if (!($decoded['ok'] ?? false)) {
            $this->logError('Telegram API ответил ошибкой', ['response' => $decoded]);
            throw new RuntimeException('Telegram API ответил ошибкой.');
        }

        return $decoded;
    }

    /**
     * Преобразует массив данных в формат multipart/form-data
     *
     * @param array<string, mixed> $payload Данные запроса
     * @return array<int, array<string, mixed>>
     * @throws JsonException Если не удалось сериализовать данные
     * @throws RuntimeException Если не удалось подготовить файловые данные
     */
    private function prepareMultipart(array $payload): array
    {
        $multipart = [];
        $flattened = $this->flattenMultipart($payload);

        foreach ($flattened as $name => $value) {
            if ($value instanceof CURLFile) {
                $multipart[] = $this->createFilePart($name, $value);
                continue;
            }

            $multipart[] = [
                'name' => $name,
                'contents' => $this->normalizeMultipartValue($value),
            ];
        }

        return $multipart;
    }

    /**
     * Рекурсивно разворачивает многоуровневый массив параметров
     *
     * @param array<string, mixed> $payload Исходные данные
     * @param string $prefix Текущий префикс ключа
     * @return array<string, mixed>
     */
    private function flattenMultipart(array $payload, string $prefix = ''): array
    {
        $result = [];

        foreach ($payload as $key => $value) {
            $name = $prefix === '' ? (string)$key : sprintf('%s[%s]', $prefix, (string)$key);

            if (is_array($value)) {
                $result += $this->flattenMultipart($value, $name);
                continue;
            }

            $result[$name] = $value;
        }

        return $result;
    }

    /**
     * Подготавливает файловую часть запроса
     *
     * @param string $name Имя поля
     * @param CURLFile $file Файл для загрузки
     * @return array<string, mixed>
     */
    private function createFilePart(string $name, CURLFile $file): array
    {
        $part = [
            'name' => $name,
            'contents' => Utils::tryFopen($file->getFilename(), 'rb'),
        ];

        $filename = $file->getPostFilename();
        if ($filename === '' || $filename === null) {
            $filename = basename($file->getFilename());
        }

        if ($filename !== '') {
            $part['filename'] = $filename;
        }

        $mimeType = $file->getMimeType();
        if ($mimeType !== '') {
            $part['headers'] = ['Content-Type' => $mimeType];
        }

        return $part;
    }

    /**
     * Преобразует значение в строку для multipart запроса
     *
     * @param mixed $value Значение параметра
     * @return string
     * @throws JsonException Если не удалось сериализовать значение
     */
    private function normalizeMultipartValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string)$value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string)$value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * Записывает ошибки в лог
     *
     * @param string $message Сообщение об ошибке
     * @param array<string, mixed> $context Дополнительный контекст
     */
    private function logError(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->error($message, $context);
        }
    }
}

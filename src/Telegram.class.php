<?php

declare(strict_types=1);

namespace App\Component;

use CURLFile;
use Exception;
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
     * Формирует полный URL метода API
     */
    private function buildUrl(string $method): string
    {
        return self::BASE_URL . $this->token . '/' . $method;
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
        $url = $this->buildUrl($method);
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Не удалось инициализировать cURL.');
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);

        $response = curl_exec($handle);
        $statusCode = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($response === false) {
            $this->logError('Ошибка cURL запроса', ['error' => $error]);
            throw new RuntimeException('Ошибка запроса к Telegram API: ' . $error);
        }

        if ($statusCode >= 400) {
            $this->logError('Telegram API вернул ошибку', ['status_code' => $statusCode, 'response' => $response]);
            throw new RuntimeException('Telegram API вернул ошибку: ' . $response);
        }

        $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
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
        $url = $this->buildUrl($method);

        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Не удалось инициализировать cURL.');
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => $this->timeout,
        ]);

        $response = curl_exec($handle);
        $statusCode = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($response === false) {
            $this->logError('Ошибка cURL multipart запроса', ['error' => $error]);
            throw new RuntimeException('Ошибка multipart запроса к Telegram API: ' . $error);
        }

        if ($statusCode >= 400) {
            $this->logError('Telegram API вернул ошибку', ['status_code' => $statusCode, 'response' => $response]);
            throw new RuntimeException('Telegram API вернул ошибку: ' . $response);
        }

        $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        if (!($decoded['ok'] ?? false)) {
            $this->logError('Telegram API ответил ошибкой', ['response' => $decoded]);
            throw new RuntimeException('Telegram API ответил ошибкой.');
        }

        return $decoded;
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

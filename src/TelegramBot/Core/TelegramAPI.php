<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Core;

use App\Component\Http;
use App\Component\Logger;
use App\Component\TelegramBot\Entities\Message;
use App\Component\TelegramBot\Entities\User;
use App\Component\TelegramBot\Exceptions\ApiException;
use App\Component\TelegramBot\Exceptions\ValidationException;
use App\Component\TelegramBot\Utils\Validator;
use CURLFile;

/**
 * Полный клиент Telegram Bot API с строгой типизацией
 * 
 * Предоставляет методы для всех основных операций API:
 * - Отправка сообщений всех типов
 * - Редактирование и удаление сообщений
 * - Работа с callback запросами
 * - Управление ботом и чатами
 * 
 * @link https://core.telegram.org/bots/api
 */
class TelegramAPI
{
    /**
     * Базовый URL Telegram Bot API
     */
    private const BASE_URL = 'https://api.telegram.org/bot';

    /**
     * Режим разметки: Markdown
     */
    public const PARSE_MODE_MARKDOWN = 'Markdown';

    /**
     * Режим разметки: MarkdownV2
     */
    public const PARSE_MODE_MARKDOWN_V2 = 'MarkdownV2';

    /**
     * Режим разметки: HTML
     */
    public const PARSE_MODE_HTML = 'HTML';

    /**
     * @param string $token Токен бота
     * @param Http $http HTTP клиент
     * @param Logger|null $logger Логгер
     * @param MessageStorage|null $messageStorage Хранилище сообщений
     */
    public function __construct(
        private readonly string $token,
        private readonly Http $http,
        private readonly ?Logger $logger = null,
        private readonly ?MessageStorage $messageStorage = null,
    ) {
        Validator::validateToken($token);
    }

    /**
     * Получает информацию о боте
     *
     * @return User Информация о боте
     * @throws ApiException При ошибке API
     */
    public function getMe(): User
    {
        $response = $this->sendRequest('getMe');
        return User::fromArray($response);
    }

    /**
     * Отправляет текстовое сообщение
     *
     * @param string|int $chatId Идентификатор чата
     * @param string $text Текст сообщения (1-4096 символов)
     * @param array<string, mixed> $options Дополнительные параметры
     * @return Message Отправленное сообщение
     * @throws ValidationException При некорректных параметрах
     * @throws ApiException При ошибке API
     */
    public function sendMessage(string|int $chatId, string $text, array $options = []): Message
    {
        Validator::validateChatId($chatId);
        Validator::validateText($text);

        $params = array_merge($options, [
            'chat_id' => $chatId,
            'text' => $text,
        ]);

        $response = $this->sendRequest('sendMessage', $params);
        return Message::fromArray($response);
    }

    /**
     * Отправляет фото
     *
     * @param string|int $chatId Идентификатор чата
     * @param string $photo Путь к файлу, URL или file_id
     * @param array<string, mixed> $options Дополнительные параметры
     * @return Message Отправленное сообщение
     * @throws ValidationException При некорректных параметрах
     * @throws ApiException При ошибке API
     */
    public function sendPhoto(string|int $chatId, string $photo, array $options = []): Message
    {
        Validator::validateChatId($chatId);

        if (isset($options['caption'])) {
            Validator::validateCaption($options['caption']);
        }

        $params = array_merge($options, [
            'chat_id' => $chatId,
            'photo' => $this->prepareFileOrUrl($photo),
        ]);

        $response = $this->sendRequest('sendPhoto', $params, $this->hasFile($params));
        return Message::fromArray($response);
    }

    /**
     * Отправляет видео
     *
     * @param string|int $chatId Идентификатор чата
     * @param string $video Путь к файлу, URL или file_id
     * @param array<string, mixed> $options Дополнительные параметры
     * @return Message Отправленное сообщение
     * @throws ValidationException При некорректных параметрах
     * @throws ApiException При ошибке API
     */
    public function sendVideo(string|int $chatId, string $video, array $options = []): Message
    {
        Validator::validateChatId($chatId);

        if (isset($options['caption'])) {
            Validator::validateCaption($options['caption']);
        }

        $params = array_merge($options, [
            'chat_id' => $chatId,
            'video' => $this->prepareFileOrUrl($video),
        ]);

        $response = $this->sendRequest('sendVideo', $params, $this->hasFile($params));
        return Message::fromArray($response);
    }

    /**
     * Отправляет аудио
     *
     * @param string|int $chatId Идентификатор чата
     * @param string $audio Путь к файлу, URL или file_id
     * @param array<string, mixed> $options Дополнительные параметры
     * @return Message Отправленное сообщение
     * @throws ValidationException При некорректных параметрах
     * @throws ApiException При ошибке API
     */
    public function sendAudio(string|int $chatId, string $audio, array $options = []): Message
    {
        Validator::validateChatId($chatId);

        if (isset($options['caption'])) {
            Validator::validateCaption($options['caption']);
        }

        $params = array_merge($options, [
            'chat_id' => $chatId,
            'audio' => $this->prepareFileOrUrl($audio),
        ]);

        $response = $this->sendRequest('sendAudio', $params, $this->hasFile($params));
        return Message::fromArray($response);
    }

    /**
     * Отправляет документ
     *
     * @param string|int $chatId Идентификатор чата
     * @param string $document Путь к файлу, URL или file_id
     * @param array<string, mixed> $options Дополнительные параметры
     * @return Message Отправленное сообщение
     * @throws ValidationException При некорректных параметрах
     * @throws ApiException При ошибке API
     */
    public function sendDocument(string|int $chatId, string $document, array $options = []): Message
    {
        Validator::validateChatId($chatId);

        if (isset($options['caption'])) {
            Validator::validateCaption($options['caption']);
        }

        $params = array_merge($options, [
            'chat_id' => $chatId,
            'document' => $this->prepareFileOrUrl($document),
        ]);

        $response = $this->sendRequest('sendDocument', $params, $this->hasFile($params));
        return Message::fromArray($response);
    }

    /**
     * Отправляет опрос
     *
     * @param string|int $chatId Идентификатор чата
     * @param string $question Вопрос опроса
     * @param array<string> $options Варианты ответов
     * @param array<string, mixed> $params Дополнительные параметры
     * @return Message Отправленное сообщение
     * @throws ValidationException При некорректных параметрах
     * @throws ApiException При ошибке API
     */
    public function sendPoll(string|int $chatId, string $question, array $options, array $params = []): Message
    {
        Validator::validateChatId($chatId);
        Validator::validatePollQuestion($question);
        Validator::validatePollOptions($options);

        $requestParams = array_merge($params, [
            'chat_id' => $chatId,
            'question' => $question,
            'options' => $options,
        ]);

        $response = $this->sendRequest('sendPoll', $requestParams);
        return Message::fromArray($response);
    }

    /**
     * Редактирует текст сообщения
     *
     * @param string|int $chatId Идентификатор чата
     * @param int $messageId Идентификатор сообщения
     * @param string $text Новый текст
     * @param array<string, mixed> $options Дополнительные параметры
     * @return Message Отредактированное сообщение
     * @throws ValidationException При некорректных параметрах
     * @throws ApiException При ошибке API
     */
    public function editMessageText(string|int $chatId, int $messageId, string $text, array $options = []): Message
    {
        Validator::validateChatId($chatId);
        Validator::validateText($text);

        $params = array_merge($options, [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
        ]);

        $response = $this->sendRequest('editMessageText', $params);
        return Message::fromArray($response);
    }

    /**
     * Редактирует подпись к медиа
     *
     * @param string|int $chatId Идентификатор чата
     * @param int $messageId Идентификатор сообщения
     * @param string $caption Новая подпись
     * @param array<string, mixed> $options Дополнительные параметры
     * @return Message Отредактированное сообщение
     * @throws ValidationException При некорректных параметрах
     * @throws ApiException При ошибке API
     */
    public function editMessageCaption(string|int $chatId, int $messageId, string $caption, array $options = []): Message
    {
        Validator::validateChatId($chatId);
        Validator::validateCaption($caption);

        $params = array_merge($options, [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'caption' => $caption,
        ]);

        $response = $this->sendRequest('editMessageCaption', $params);
        return Message::fromArray($response);
    }

    /**
     * Редактирует reply markup сообщения
     *
     * @param string|int $chatId Идентификатор чата
     * @param int $messageId Идентификатор сообщения
     * @param array<string, mixed> $replyMarkup Новая клавиатура
     * @return Message Отредактированное сообщение
     * @throws ValidationException При некорректных параметрах
     * @throws ApiException При ошибке API
     */
    public function editMessageReplyMarkup(string|int $chatId, int $messageId, array $replyMarkup): Message
    {
        Validator::validateChatId($chatId);

        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => $replyMarkup,
        ];

        $response = $this->sendRequest('editMessageReplyMarkup', $params);
        return Message::fromArray($response);
    }

    /**
     * Удаляет сообщение
     *
     * @param string|int $chatId Идентификатор чата
     * @param int $messageId Идентификатор сообщения
     * @return bool True при успешном удалении
     * @throws ValidationException При некорректных параметрах
     * @throws ApiException При ошибке API
     */
    public function deleteMessage(string|int $chatId, int $messageId): bool
    {
        Validator::validateChatId($chatId);

        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ];

        return $this->sendRequest('deleteMessage', $params);
    }

    /**
     * Отвечает на callback query
     *
     * @param string $callbackQueryId Идентификатор callback query
     * @param array<string, mixed> $options Дополнительные параметры
     * @return bool True при успешном ответе
     * @throws ApiException При ошибке API
     */
    public function answerCallbackQuery(string $callbackQueryId, array $options = []): bool
    {
        $params = array_merge($options, [
            'callback_query_id' => $callbackQueryId,
        ]);

        return $this->sendRequest('answerCallbackQuery', $params);
    }

    /**
     * Устанавливает webhook
     *
     * @param string $url URL для webhook
     * @param array<string, mixed> $options Дополнительные параметры
     * @return bool True при успешной установке
     * @throws ApiException При ошибке API
     */
    public function setWebhook(string $url, array $options = []): bool
    {
        $params = array_merge($options, ['url' => $url]);
        return $this->sendRequest('setWebhook', $params);
    }

    /**
     * Удаляет webhook
     *
     * @param bool $dropPendingUpdates Удалить ожидающие обновления
     * @return bool True при успешном удалении
     * @throws ApiException При ошибке API
     */
    public function deleteWebhook(bool $dropPendingUpdates = false): bool
    {
        $params = ['drop_pending_updates' => $dropPendingUpdates];
        return $this->sendRequest('deleteWebhook', $params);
    }

    /**
     * Получает информацию о webhook
     *
     * @return array<string, mixed> Информация о webhook
     * @throws ApiException При ошибке API
     */
    public function getWebhookInfo(): array
    {
        return $this->sendRequest('getWebhookInfo');
    }

    /**
     * Отправляет запрос к Telegram API
     *
     * @param string $method Метод API
     * @param array<string, mixed> $params Параметры запроса
     * @param bool $multipart Использовать multipart для файлов
     * @return mixed Результат запроса
     * @throws ApiException При ошибке API
     */
    private function sendRequest(string $method, array $params = [], bool $multipart = false): mixed
    {
        $success = false;
        $errorCode = null;
        $errorMessage = null;
        $result = null;

        try {
            $url = self::BASE_URL . $this->token . '/' . $method;

            $this->logger?->debug('Отправка запроса к Telegram API', [
                'method' => $method,
                'params_keys' => array_keys($params),
            ]);

            if ($multipart) {
                $multipartData = $this->prepareMultipart($params);
                $response = $this->http->request('POST', $url, [
                    'multipart' => $multipartData,
                ]);
            } else {
                $response = $this->http->request('POST', $url, [
                    'json' => $params,
                ]);
            }

            $body = (string)$response->getBody();
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            if (!isset($data['ok']) || !$data['ok']) {
                $errorMessage = $data['description'] ?? 'Неизвестная ошибка API';
                $errorCode = $data['error_code'] ?? 0;

                $this->logger?->error('Ошибка Telegram API', [
                    'method' => $method,
                    'error' => $errorMessage,
                    'code' => $errorCode,
                ]);

                // Сохранение ошибочного запроса в БД
                $this->messageStorage?->storeOutgoing(
                    $method,
                    $params,
                    null,
                    false,
                    $errorCode,
                    $errorMessage
                );

                throw new ApiException($errorMessage, $errorCode, [
                    'method' => $method,
                    'params' => $params,
                ]);
            }

            $this->logger?->debug('Запрос к Telegram API выполнен успешно', [
                'method' => $method,
            ]);

            $result = $data['result'];
            $success = true;

            // Сохранение успешного запроса в БД
            $this->messageStorage?->storeOutgoing(
                $method,
                $params,
                $result,
                true
            );

            return $result;
        } catch (\JsonException $e) {
            $errorMessage = 'Ошибка парсинга ответа API: ' . $e->getMessage();
            
            $this->logger?->error('Ошибка парсинга JSON ответа от Telegram API', [
                'method' => $method,
                'error' => $e->getMessage(),
            ]);

            // Сохранение ошибки парсинга в БД
            $this->messageStorage?->storeOutgoing(
                $method,
                $params,
                null,
                false,
                -1,
                $errorMessage
            );

            throw new ApiException($errorMessage);
        } catch (ApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            $errorMessage = 'Ошибка запроса к API: ' . $e->getMessage();
            
            $this->logger?->error('Ошибка при запросе к Telegram API', [
                'method' => $method,
                'error' => $e->getMessage(),
            ]);

            // Сохранение общей ошибки в БД
            $this->messageStorage?->storeOutgoing(
                $method,
                $params,
                null,
                false,
                -2,
                $errorMessage
            );

            throw new ApiException($errorMessage);
        }
    }

    /**
     * Подготавливает файл или URL для отправки
     *
     * @param string $fileOrUrl Путь к файлу, URL или file_id
     * @return string|CURLFile
     */
    private function prepareFileOrUrl(string $fileOrUrl): string|CURLFile
    {
        if (filter_var($fileOrUrl, FILTER_VALIDATE_URL)) {
            return $fileOrUrl;
        }

        if (str_starts_with($fileOrUrl, 'attach://') || !file_exists($fileOrUrl)) {
            return $fileOrUrl;
        }

        Validator::validateFile($fileOrUrl);

        return new CURLFile($fileOrUrl);
    }

    /**
     * Проверяет, содержит ли массив параметров файлы для загрузки
     *
     * @param array<string, mixed> $params Параметры запроса
     * @return bool True если есть файлы
     */
    private function hasFile(array $params): bool
    {
        foreach ($params as $value) {
            if ($value instanceof CURLFile) {
                return true;
            }
        }

        return false;
    }

    /**
     * Подготавливает данные для multipart запроса
     *
     * @param array<string, mixed> $params Параметры запроса
     * @return array<array<string, mixed>> Multipart данные
     */
    private function prepareMultipart(array $params): array
    {
        $multipart = [];

        foreach ($params as $name => $value) {
            if ($value instanceof CURLFile) {
                $multipart[] = [
                    'name' => $name,
                    'contents' => fopen($value->getFilename(), 'r'),
                    'filename' => basename($value->getFilename()),
                ];
            } elseif (is_array($value)) {
                $multipart[] = [
                    'name' => $name,
                    'contents' => json_encode($value),
                ];
            } else {
                $multipart[] = [
                    'name' => $name,
                    'contents' => (string)$value,
                ];
            }
        }

        return $multipart;
    }
}

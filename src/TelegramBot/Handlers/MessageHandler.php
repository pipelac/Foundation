<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Handlers;

use App\Component\Logger;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Entities\Message;
use App\Component\TelegramBot\Entities\Update;

/**
 * Обработчик текстовых и медиа сообщений
 * 
 * Предоставляет методы для обработки входящих сообщений
 * различных типов: текст, фото, видео, аудио, документы
 */
class MessageHandler
{
    /**
     * @param TelegramAPI $api API клиент
     * @param Logger|null $logger Логгер
     */
    public function __construct(
        private readonly TelegramAPI $api,
        private readonly ?Logger $logger = null,
    ) {
    }

    /**
     * Обрабатывает сообщение из обновления
     *
     * @param Update $update Обновление
     * @param callable $callback Callback для обработки: function(Message $message): void
     */
    public function handle(Update $update, callable $callback): void
    {
        $message = $update->getMessage();

        if ($message === null) {
            return;
        }

        try {
            $this->logger?->debug('Обработка сообщения', [
                'message_id' => $message->messageId,
                'chat_id' => $message->chat->id,
                'from' => $message->from?->username ?? $message->from?->id,
            ]);

            $callback($message);
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка обработки сообщения', [
                'message_id' => $message->messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Обрабатывает только текстовые сообщения
     *
     * @param Update $update Обновление
     * @param callable $callback Callback: function(Message $message, string $text): void
     */
    public function handleText(Update $update, callable $callback): void
    {
        $message = $update->getMessage();

        if ($message === null || !$message->isText()) {
            return;
        }

        try {
            $this->logger?->debug('Обработка текстового сообщения', [
                'message_id' => $message->messageId,
                'text_length' => mb_strlen($message->text ?? '', 'UTF-8'),
            ]);

            $callback($message, $message->text);
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка обработки текстового сообщения', [
                'message_id' => $message->messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Обрабатывает сообщения с фото
     *
     * @param Update $update Обновление
     * @param callable $callback Callback: function(Message $message): void
     */
    public function handlePhoto(Update $update, callable $callback): void
    {
        $message = $update->getMessage();

        if ($message === null || !$message->hasPhoto()) {
            return;
        }

        try {
            $this->logger?->debug('Обработка фото сообщения', [
                'message_id' => $message->messageId,
                'photo_count' => count($message->photo ?? []),
            ]);

            $callback($message);
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка обработки фото сообщения', [
                'message_id' => $message->messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Обрабатывает сообщения с видео
     *
     * @param Update $update Обновление
     * @param callable $callback Callback: function(Message $message): void
     */
    public function handleVideo(Update $update, callable $callback): void
    {
        $message = $update->getMessage();

        if ($message === null || !$message->hasVideo()) {
            return;
        }

        try {
            $this->logger?->debug('Обработка видео сообщения', [
                'message_id' => $message->messageId,
            ]);

            $callback($message);
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка обработки видео сообщения', [
                'message_id' => $message->messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Обрабатывает сообщения с аудио
     *
     * @param Update $update Обновление
     * @param callable $callback Callback: function(Message $message): void
     */
    public function handleAudio(Update $update, callable $callback): void
    {
        $message = $update->getMessage();

        if ($message === null || !$message->hasAudio()) {
            return;
        }

        try {
            $this->logger?->debug('Обработка аудио сообщения', [
                'message_id' => $message->messageId,
            ]);

            $callback($message);
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка обработки аудио сообщения', [
                'message_id' => $message->messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Обрабатывает сообщения с документами
     *
     * @param Update $update Обновление
     * @param callable $callback Callback: function(Message $message): void
     */
    public function handleDocument(Update $update, callable $callback): void
    {
        $message = $update->getMessage();

        if ($message === null || !$message->hasDocument()) {
            return;
        }

        try {
            $this->logger?->debug('Обработка документа', [
                'message_id' => $message->messageId,
            ]);

            $callback($message);
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка обработки документа', [
                'message_id' => $message->messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Отправляет ответ на сообщение
     *
     * @param Message $message Исходное сообщение
     * @param string $text Текст ответа
     * @param array<string, mixed> $options Дополнительные параметры
     * @return Message Отправленное сообщение
     */
    public function reply(Message $message, string $text, array $options = []): Message
    {
        $options['reply_to_message_id'] = $message->messageId;

        return $this->api->sendMessage($message->chat->id, $text, $options);
    }

    /**
     * Отправляет сообщение в чат
     *
     * @param Message $message Исходное сообщение (для получения chat_id)
     * @param string $text Текст сообщения
     * @param array<string, mixed> $options Дополнительные параметры
     * @return Message Отправленное сообщение
     */
    public function send(Message $message, string $text, array $options = []): Message
    {
        return $this->api->sendMessage($message->chat->id, $text, $options);
    }

    /**
     * Пересылает сообщение в другой чат
     *
     * @param Message $message Сообщение для пересылки
     * @param string|int $toChatId ID чата назначения
     * @return bool True при успешной пересылке
     */
    public function forward(Message $message, string|int $toChatId): bool
    {
        try {
            $this->api->sendMessage(
                $toChatId,
                $message->getContent() ?? 'Пересланное сообщение',
                []
            );

            return true;
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка пересылки сообщения', [
                'message_id' => $message->messageId,
                'to_chat_id' => $toChatId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}

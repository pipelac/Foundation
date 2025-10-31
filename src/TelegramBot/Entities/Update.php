<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Entities;

/**
 * Сущность обновления (входящее событие от Telegram)
 * 
 * Представляет входящее обновление от Telegram Bot API
 * 
 * @link https://core.telegram.org/bots/api#update
 */
class Update
{
    /**
     * @param int $updateId Уникальный идентификатор обновления
     * @param Message|null $message Новое входящее сообщение любого вида
     * @param Message|null $editedMessage Новая версия сообщения, которое было отредактировано
     * @param Message|null $channelPost Новое входящее сообщение канала любого вида
     * @param Message|null $editedChannelPost Новая версия сообщения канала, которое было отредактировано
     * @param CallbackQuery|null $callbackQuery Новый входящий callback запрос
     */
    public function __construct(
        public readonly int $updateId,
        public readonly ?Message $message = null,
        public readonly ?Message $editedMessage = null,
        public readonly ?Message $channelPost = null,
        public readonly ?Message $editedChannelPost = null,
        public readonly ?CallbackQuery $callbackQuery = null,
    ) {
    }

    /**
     * Создает объект Update из массива данных Telegram API
     *
     * @param array<string, mixed> $data Данные от Telegram API
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            updateId: (int)$data['update_id'],
            message: isset($data['message']) ? Message::fromArray($data['message']) : null,
            editedMessage: isset($data['edited_message']) ? Message::fromArray($data['edited_message']) : null,
            channelPost: isset($data['channel_post']) ? Message::fromArray($data['channel_post']) : null,
            editedChannelPost: isset($data['edited_channel_post']) ? Message::fromArray($data['edited_channel_post']) : null,
            callbackQuery: isset($data['callback_query']) ? CallbackQuery::fromArray($data['callback_query']) : null,
        );
    }

    /**
     * Проверяет, является ли обновление новым сообщением
     */
    public function isMessage(): bool
    {
        return $this->message !== null;
    }

    /**
     * Проверяет, является ли обновление отредактированным сообщением
     */
    public function isEditedMessage(): bool
    {
        return $this->editedMessage !== null;
    }

    /**
     * Проверяет, является ли обновление сообщением канала
     */
    public function isChannelPost(): bool
    {
        return $this->channelPost !== null;
    }

    /**
     * Проверяет, является ли обновление callback запросом
     */
    public function isCallbackQuery(): bool
    {
        return $this->callbackQuery !== null;
    }

    /**
     * Возвращает сообщение из обновления (любого типа)
     */
    public function getMessage(): ?Message
    {
        return $this->message 
            ?? $this->editedMessage 
            ?? $this->channelPost 
            ?? $this->editedChannelPost;
    }

    /**
     * Возвращает Chat из обновления
     */
    public function getChat(): ?Chat
    {
        $message = $this->getMessage();
        if ($message !== null) {
            return $message->chat;
        }

        if ($this->callbackQuery !== null && $this->callbackQuery->message !== null) {
            return $this->callbackQuery->message->chat;
        }

        return null;
    }

    /**
     * Возвращает User из обновления
     */
    public function getUser(): ?User
    {
        $message = $this->getMessage();
        if ($message !== null && $message->from !== null) {
            return $message->from;
        }

        if ($this->callbackQuery !== null) {
            return $this->callbackQuery->from;
        }

        return null;
    }

    /**
     * Преобразует объект в массив
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'update_id' => $this->updateId,
            'message' => $this->message?->toArray(),
            'edited_message' => $this->editedMessage?->toArray(),
            'channel_post' => $this->channelPost?->toArray(),
            'edited_channel_post' => $this->editedChannelPost?->toArray(),
            'callback_query' => $this->callbackQuery?->toArray(),
        ], fn($value) => $value !== null);
    }
}

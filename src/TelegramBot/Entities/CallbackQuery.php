<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Entities;

/**
 * Сущность callback query (нажатие на inline кнопку)
 * 
 * Представляет входящий callback запрос от inline кнопки
 * 
 * @link https://core.telegram.org/bots/api#callbackquery
 */
class CallbackQuery
{
    /**
     * @param string $id Уникальный идентификатор запроса
     * @param User $from Пользователь, нажавший кнопку
     * @param Message|null $message Сообщение с inline клавиатурой
     * @param string|null $inlineMessageId Идентификатор inline сообщения
     * @param string|null $chatInstance Глобальный идентификатор чата
     * @param string|null $data Данные, связанные с кнопкой (callback_data)
     * @param string|null $gameShortName Короткое имя игры
     */
    public function __construct(
        public readonly string $id,
        public readonly User $from,
        public readonly ?Message $message = null,
        public readonly ?string $inlineMessageId = null,
        public readonly ?string $chatInstance = null,
        public readonly ?string $data = null,
        public readonly ?string $gameShortName = null,
    ) {
    }

    /**
     * Создает объект CallbackQuery из массива данных Telegram API
     *
     * @param array<string, mixed> $data Данные от Telegram API
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string)$data['id'],
            from: User::fromArray($data['from']),
            message: isset($data['message']) ? Message::fromArray($data['message']) : null,
            inlineMessageId: isset($data['inline_message_id']) ? (string)$data['inline_message_id'] : null,
            chatInstance: isset($data['chat_instance']) ? (string)$data['chat_instance'] : null,
            data: isset($data['data']) ? (string)$data['data'] : null,
            gameShortName: isset($data['game_short_name']) ? (string)$data['game_short_name'] : null,
        );
    }

    /**
     * Проверяет, содержит ли callback данные
     */
    public function hasData(): bool
    {
        return $this->data !== null;
    }

    /**
     * Проверяет, связан ли callback с сообщением
     */
    public function hasMessage(): bool
    {
        return $this->message !== null;
    }

    /**
     * Возвращает chat ID из сообщения (если есть)
     */
    public function getChatId(): ?int
    {
        return $this->message?->chat->id;
    }

    /**
     * Возвращает message ID из сообщения (если есть)
     */
    public function getMessageId(): ?int
    {
        return $this->message?->messageId;
    }

    /**
     * Преобразует объект в массив
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'from' => $this->from->toArray(),
            'message' => $this->message?->toArray(),
            'inline_message_id' => $this->inlineMessageId,
            'chat_instance' => $this->chatInstance,
            'data' => $this->data,
            'game_short_name' => $this->gameShortName,
        ], fn($value) => $value !== null);
    }
}

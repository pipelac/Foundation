<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Entities;

/**
 * Сущность сообщения Telegram
 * 
 * Представляет сообщение любого типа с полной типизацией
 * 
 * @link https://core.telegram.org/bots/api#message
 */
class Message
{
    /**
     * @param int $messageId Уникальный идентификатор сообщения в чате
     * @param int $date Дата отправки сообщения (Unix timestamp)
     * @param Chat $chat Чат, в котором было отправлено сообщение
     * @param User|null $from Отправитель сообщения (пусто для сообщений каналов)
     * @param string|null $text Текст сообщения (для текстовых сообщений)
     * @param array<Media>|null $photo Массив фотографий разных размеров
     * @param Media|null $video Видео
     * @param Media|null $audio Аудио
     * @param Media|null $document Документ
     * @param Media|null $voice Голосовое сообщение
     * @param Media|null $videoNote Видео-сообщение
     * @param string|null $caption Подпись к медиа-контенту
     * @param Contact|null $contact Контакт (при отправке контакта)
     * @param Location|null $location Локация (при отправке локации)
     * @param Message|null $replyToMessage Сообщение, на которое ответили
     * @param int|null $editDate Дата последнего редактирования (Unix timestamp)
     * @param string|null $forwardFrom Оригинальный отправитель (для пересланных сообщений)
     * @param int|null $forwardDate Дата оригинального сообщения (для пересланных)
     */
    public function __construct(
        public readonly int $messageId,
        public readonly int $date,
        public readonly Chat $chat,
        public readonly ?User $from = null,
        public readonly ?string $text = null,
        public readonly ?array $photo = null,
        public readonly ?Media $video = null,
        public readonly ?Media $audio = null,
        public readonly ?Media $document = null,
        public readonly ?Media $voice = null,
        public readonly ?Media $videoNote = null,
        public readonly ?string $caption = null,
        public readonly ?Contact $contact = null,
        public readonly ?Location $location = null,
        public readonly ?Message $replyToMessage = null,
        public readonly ?int $editDate = null,
        public readonly ?string $forwardFrom = null,
        public readonly ?int $forwardDate = null,
    ) {
    }

    /**
     * Создает объект Message из массива данных Telegram API
     *
     * @param array<string, mixed> $data Данные от Telegram API
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $photo = null;
        if (isset($data['photo']) && is_array($data['photo'])) {
            $photo = array_map(fn($p) => Media::fromPhotoSize($p), $data['photo']);
        }

        return new self(
            messageId: (int)$data['message_id'],
            date: (int)$data['date'],
            chat: Chat::fromArray($data['chat']),
            from: isset($data['from']) ? User::fromArray($data['from']) : null,
            text: isset($data['text']) ? (string)$data['text'] : null,
            photo: $photo,
            video: isset($data['video']) ? Media::fromVideo($data['video']) : null,
            audio: isset($data['audio']) ? Media::fromAudio($data['audio']) : null,
            document: isset($data['document']) ? Media::fromDocument($data['document']) : null,
            voice: isset($data['voice']) ? Media::fromVoice($data['voice']) : null,
            videoNote: isset($data['video_note']) ? Media::fromVideo($data['video_note']) : null,
            caption: isset($data['caption']) ? (string)$data['caption'] : null,
            contact: isset($data['contact']) ? Contact::fromArray($data['contact']) : null,
            location: isset($data['location']) ? Location::fromArray($data['location']) : null,
            replyToMessage: isset($data['reply_to_message']) ? self::fromArray($data['reply_to_message']) : null,
            editDate: isset($data['edit_date']) ? (int)$data['edit_date'] : null,
            forwardFrom: isset($data['forward_from']['username']) ? (string)$data['forward_from']['username'] : null,
            forwardDate: isset($data['forward_date']) ? (int)$data['forward_date'] : null,
        );
    }

    /**
     * Проверяет, является ли сообщение текстовым
     */
    public function isText(): bool
    {
        return $this->text !== null;
    }

    /**
     * Проверяет, содержит ли сообщение фото
     */
    public function hasPhoto(): bool
    {
        return $this->photo !== null && count($this->photo) > 0;
    }

    /**
     * Проверяет, содержит ли сообщение видео
     */
    public function hasVideo(): bool
    {
        return $this->video !== null;
    }

    /**
     * Проверяет, содержит ли сообщение аудио
     */
    public function hasAudio(): bool
    {
        return $this->audio !== null;
    }

    /**
     * Проверяет, содержит ли сообщение документ
     */
    public function hasDocument(): bool
    {
        return $this->document !== null;
    }

    /**
     * Проверяет, содержит ли сообщение голосовое сообщение
     */
    public function hasVoice(): bool
    {
        return $this->voice !== null;
    }

    /**
     * Проверяет, является ли сообщение ответом на другое
     */
    public function isReply(): bool
    {
        return $this->replyToMessage !== null;
    }

    /**
     * Проверяет, было ли сообщение отредактировано
     */
    public function isEdited(): bool
    {
        return $this->editDate !== null;
    }

    /**
     * Проверяет, является ли сообщение пересланным
     */
    public function isForwarded(): bool
    {
        return $this->forwardDate !== null;
    }

    /**
     * Проверяет, содержит ли сообщение контакт
     */
    public function hasContact(): bool
    {
        return $this->contact !== null;
    }

    /**
     * Проверяет, содержит ли сообщение локацию
     */
    public function hasLocation(): bool
    {
        return $this->location !== null;
    }

    /**
     * Возвращает лучшее качество фото (если есть)
     */
    public function getBestPhoto(): ?Media
    {
        if (!$this->hasPhoto()) {
            return null;
        }

        return end($this->photo);
    }

    /**
     * Возвращает текст или подпись
     */
    public function getContent(): ?string
    {
        return $this->text ?? $this->caption;
    }

    /**
     * Преобразует объект в массив
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'message_id' => $this->messageId,
            'date' => $this->date,
            'chat' => $this->chat->toArray(),
            'from' => $this->from?->toArray(),
            'text' => $this->text,
            'photo' => $this->photo ? array_map(fn($p) => $p->toArray(), $this->photo) : null,
            'video' => $this->video?->toArray(),
            'audio' => $this->audio?->toArray(),
            'document' => $this->document?->toArray(),
            'voice' => $this->voice?->toArray(),
            'video_note' => $this->videoNote?->toArray(),
            'caption' => $this->caption,
            'contact' => $this->contact,
            'location' => $this->location,
            'reply_to_message' => $this->replyToMessage?->toArray(),
            'edit_date' => $this->editDate,
            'forward_from' => $this->forwardFrom,
            'forward_date' => $this->forwardDate,
        ], fn($value) => $value !== null);
    }
}

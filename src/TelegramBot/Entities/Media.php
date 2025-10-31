<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Entities;

/**
 * Сущность медиа-файла Telegram
 * 
 * Универсальная структура для фото, видео, аудио, документов и других типов медиа
 * 
 * @link https://core.telegram.org/bots/api#photosize
 * @link https://core.telegram.org/bots/api#video
 * @link https://core.telegram.org/bots/api#audio
 * @link https://core.telegram.org/bots/api#document
 */
class Media
{
    public const TYPE_PHOTO = 'photo';
    public const TYPE_VIDEO = 'video';
    public const TYPE_AUDIO = 'audio';
    public const TYPE_DOCUMENT = 'document';
    public const TYPE_VOICE = 'voice';
    public const TYPE_VIDEO_NOTE = 'video_note';
    public const TYPE_STICKER = 'sticker';
    public const TYPE_ANIMATION = 'animation';

    /**
     * @param string $fileId Идентификатор файла (можно использовать для скачивания или повторной отправки)
     * @param string $fileUniqueId Уникальный идентификатор файла
     * @param string $type Тип медиа-файла
     * @param int|null $fileSize Размер файла в байтах
     * @param int|null $width Ширина (для фото, видео, стикеров)
     * @param int|null $height Высота (для фото, видео, стикеров)
     * @param int|null $duration Длительность в секундах (для видео, аудио, голосовых)
     * @param string|null $mimeType MIME-тип файла
     * @param string|null $fileName Имя файла (для документов)
     * @param string|null $thumbnail URL превью (если доступно)
     * @param string|null $performer Исполнитель (для аудио)
     * @param string|null $title Название (для аудио)
     */
    public function __construct(
        public readonly string $fileId,
        public readonly string $fileUniqueId,
        public readonly string $type,
        public readonly ?int $fileSize = null,
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        public readonly ?int $duration = null,
        public readonly ?string $mimeType = null,
        public readonly ?string $fileName = null,
        public readonly ?string $thumbnail = null,
        public readonly ?string $performer = null,
        public readonly ?string $title = null,
    ) {
    }

    /**
     * Создает объект Media из массива данных Telegram API для PhotoSize
     *
     * @param array<string, mixed> $data Данные от Telegram API
     * @return self
     */
    public static function fromPhotoSize(array $data): self
    {
        return new self(
            fileId: (string)$data['file_id'],
            fileUniqueId: (string)$data['file_unique_id'],
            type: self::TYPE_PHOTO,
            fileSize: isset($data['file_size']) ? (int)$data['file_size'] : null,
            width: isset($data['width']) ? (int)$data['width'] : null,
            height: isset($data['height']) ? (int)$data['height'] : null,
        );
    }

    /**
     * Создает объект Media из массива данных Telegram API для Video
     *
     * @param array<string, mixed> $data Данные от Telegram API
     * @return self
     */
    public static function fromVideo(array $data): self
    {
        return new self(
            fileId: (string)$data['file_id'],
            fileUniqueId: (string)$data['file_unique_id'],
            type: self::TYPE_VIDEO,
            fileSize: isset($data['file_size']) ? (int)$data['file_size'] : null,
            width: isset($data['width']) ? (int)$data['width'] : null,
            height: isset($data['height']) ? (int)$data['height'] : null,
            duration: isset($data['duration']) ? (int)$data['duration'] : null,
            mimeType: isset($data['mime_type']) ? (string)$data['mime_type'] : null,
            fileName: isset($data['file_name']) ? (string)$data['file_name'] : null,
        );
    }

    /**
     * Создает объект Media из массива данных Telegram API для Audio
     *
     * @param array<string, mixed> $data Данные от Telegram API
     * @return self
     */
    public static function fromAudio(array $data): self
    {
        return new self(
            fileId: (string)$data['file_id'],
            fileUniqueId: (string)$data['file_unique_id'],
            type: self::TYPE_AUDIO,
            fileSize: isset($data['file_size']) ? (int)$data['file_size'] : null,
            duration: isset($data['duration']) ? (int)$data['duration'] : null,
            mimeType: isset($data['mime_type']) ? (string)$data['mime_type'] : null,
            fileName: isset($data['file_name']) ? (string)$data['file_name'] : null,
            performer: isset($data['performer']) ? (string)$data['performer'] : null,
            title: isset($data['title']) ? (string)$data['title'] : null,
        );
    }

    /**
     * Создает объект Media из массива данных Telegram API для Document
     *
     * @param array<string, mixed> $data Данные от Telegram API
     * @return self
     */
    public static function fromDocument(array $data): self
    {
        return new self(
            fileId: (string)$data['file_id'],
            fileUniqueId: (string)$data['file_unique_id'],
            type: self::TYPE_DOCUMENT,
            fileSize: isset($data['file_size']) ? (int)$data['file_size'] : null,
            mimeType: isset($data['mime_type']) ? (string)$data['mime_type'] : null,
            fileName: isset($data['file_name']) ? (string)$data['file_name'] : null,
        );
    }

    /**
     * Проверяет, является ли медиа изображением
     */
    public function isPhoto(): bool
    {
        return $this->type === self::TYPE_PHOTO;
    }

    /**
     * Проверяет, является ли медиа видео
     */
    public function isVideo(): bool
    {
        return $this->type === self::TYPE_VIDEO;
    }

    /**
     * Проверяет, является ли медиа аудио
     */
    public function isAudio(): bool
    {
        return $this->type === self::TYPE_AUDIO;
    }

    /**
     * Проверяет, является ли медиа документом
     */
    public function isDocument(): bool
    {
        return $this->type === self::TYPE_DOCUMENT;
    }

    /**
     * Возвращает размер файла в мегабайтах
     */
    public function getFileSizeInMB(): ?float
    {
        if ($this->fileSize === null) {
            return null;
        }

        return round($this->fileSize / 1024 / 1024, 2);
    }

    /**
     * Преобразует объект в массив
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'file_id' => $this->fileId,
            'file_unique_id' => $this->fileUniqueId,
            'type' => $this->type,
            'file_size' => $this->fileSize,
            'width' => $this->width,
            'height' => $this->height,
            'duration' => $this->duration,
            'mime_type' => $this->mimeType,
            'file_name' => $this->fileName,
            'thumbnail' => $this->thumbnail,
            'performer' => $this->performer,
            'title' => $this->title,
        ], fn($value) => $value !== null);
    }
}

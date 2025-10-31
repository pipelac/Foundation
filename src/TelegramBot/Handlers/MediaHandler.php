<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Handlers;

use App\Component\Logger;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Entities\Media;
use App\Component\TelegramBot\Entities\Message;
use App\Component\TelegramBot\Utils\FileDownloader;

/**
 * Обработчик медиа-файлов
 * 
 * Специализированный обработчик для загрузки и обработки
 * медиа-контента: фото, видео, аудио, документов
 */
class MediaHandler
{
    /**
     * @param TelegramAPI $api API клиент
     * @param FileDownloader $fileDownloader Загрузчик файлов
     * @param Logger|null $logger Логгер
     */
    public function __construct(
        private readonly TelegramAPI $api,
        private readonly FileDownloader $fileDownloader,
        private readonly ?Logger $logger = null,
    ) {
    }

    /**
     * Получает лучшее качество фото из сообщения
     *
     * @param Message $message Сообщение с фото
     * @return Media|null Фото в лучшем качестве или null
     */
    public function getBestPhoto(Message $message): ?Media
    {
        if (!$message->hasPhoto()) {
            return null;
        }

        return $message->getBestPhoto();
    }

    /**
     * Скачивает фото из сообщения
     *
     * @param Message $message Сообщение с фото
     * @param string $savePath Путь для сохранения
     * @param bool $bestQuality Скачать лучшее качество
     * @return string|null Путь к скачанному файлу или null
     */
    public function downloadPhoto(Message $message, string $savePath, bool $bestQuality = true): ?string
    {
        $photo = $bestQuality ? $this->getBestPhoto($message) : $message->photo[0] ?? null;

        if ($photo === null) {
            return null;
        }

        try {
            return $this->fileDownloader->downloadFile($photo->fileId, $savePath);
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка скачивания фото', [
                'message_id' => $message->messageId,
                'file_id' => $photo->fileId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Скачивает видео из сообщения
     *
     * @param Message $message Сообщение с видео
     * @param string $savePath Путь для сохранения
     * @return string|null Путь к скачанному файлу или null
     */
    public function downloadVideo(Message $message, string $savePath): ?string
    {
        if (!$message->hasVideo() || $message->video === null) {
            return null;
        }

        try {
            return $this->fileDownloader->downloadFile($message->video->fileId, $savePath);
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка скачивания видео', [
                'message_id' => $message->messageId,
                'file_id' => $message->video->fileId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Скачивает аудио из сообщения
     *
     * @param Message $message Сообщение с аудио
     * @param string $savePath Путь для сохранения
     * @return string|null Путь к скачанному файлу или null
     */
    public function downloadAudio(Message $message, string $savePath): ?string
    {
        if (!$message->hasAudio() || $message->audio === null) {
            return null;
        }

        try {
            return $this->fileDownloader->downloadFile($message->audio->fileId, $savePath);
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка скачивания аудио', [
                'message_id' => $message->messageId,
                'file_id' => $message->audio->fileId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Скачивает документ из сообщения
     *
     * @param Message $message Сообщение с документом
     * @param string $savePath Путь для сохранения
     * @return string|null Путь к скачанному файлу или null
     */
    public function downloadDocument(Message $message, string $savePath): ?string
    {
        if (!$message->hasDocument() || $message->document === null) {
            return null;
        }

        try {
            return $this->fileDownloader->downloadFile($message->document->fileId, $savePath);
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка скачивания документа', [
                'message_id' => $message->messageId,
                'file_id' => $message->document->fileId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Скачивает любой медиа-файл во временную директорию
     *
     * @param Message $message Сообщение с медиа
     * @return string|null Путь к скачанному файлу или null
     */
    public function downloadAnyMedia(Message $message): ?string
    {
        try {
            if ($message->hasPhoto()) {
                $photo = $this->getBestPhoto($message);
                if ($photo) {
                    return $this->fileDownloader->downloadToTemp($photo->fileId, 'photo_' . time() . '.jpg');
                }
            }

            if ($message->hasVideo() && $message->video) {
                return $this->fileDownloader->downloadToTemp(
                    $message->video->fileId,
                    'video_' . time() . '.' . $this->getExtension($message->video)
                );
            }

            if ($message->hasAudio() && $message->audio) {
                return $this->fileDownloader->downloadToTemp(
                    $message->audio->fileId,
                    'audio_' . time() . '.' . $this->getExtension($message->audio)
                );
            }

            if ($message->hasDocument() && $message->document) {
                return $this->fileDownloader->downloadToTemp(
                    $message->document->fileId,
                    $message->document->fileName ?? 'document_' . time()
                );
            }

            return null;
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка скачивания медиа', [
                'message_id' => $message->messageId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Получает URL для скачивания файла
     *
     * @param string $fileId Идентификатор файла
     * @return string|null URL файла или null
     */
    public function getFileUrl(string $fileId): ?string
    {
        try {
            return $this->fileDownloader->getFileUrl($fileId);
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка получения URL файла', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Получает размер файла
     *
     * @param string $fileId Идентификатор файла
     * @return int|null Размер файла в байтах или null
     */
    public function getFileSize(string $fileId): ?int
    {
        try {
            return $this->fileDownloader->getFileSize($fileId);
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка получения размера файла', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Проверяет, содержит ли сообщение какой-либо медиа-контент
     *
     * @param Message $message Сообщение
     * @return bool True если есть медиа
     */
    public function hasAnyMedia(Message $message): bool
    {
        return $message->hasPhoto() 
            || $message->hasVideo() 
            || $message->hasAudio() 
            || $message->hasDocument();
    }

    /**
     * Определяет тип медиа в сообщении
     *
     * @param Message $message Сообщение
     * @return string|null Тип медиа или null
     */
    public function getMediaType(Message $message): ?string
    {
        if ($message->hasPhoto()) {
            return Media::TYPE_PHOTO;
        }

        if ($message->hasVideo()) {
            return Media::TYPE_VIDEO;
        }

        if ($message->hasAudio()) {
            return Media::TYPE_AUDIO;
        }

        if ($message->hasDocument()) {
            return Media::TYPE_DOCUMENT;
        }

        return null;
    }

    /**
     * Получает расширение файла из MIME типа
     *
     * @param Media $media Медиа объект
     * @return string Расширение файла
     */
    private function getExtension(Media $media): string
    {
        if ($media->fileName !== null && str_contains($media->fileName, '.')) {
            $parts = explode('.', $media->fileName);
            return end($parts);
        }

        return match ($media->mimeType) {
            'video/mp4' => 'mp4',
            'video/mpeg' => 'mpeg',
            'audio/mpeg' => 'mp3',
            'audio/ogg' => 'ogg',
            'audio/wav' => 'wav',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            default => 'bin',
        };
    }
}

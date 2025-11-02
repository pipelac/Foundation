<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Core;

use App\Component\Logger;
use App\Component\TelegramBot\Entities\Message;
use App\Component\TelegramBot\Exceptions\ApiException;
use App\Component\TelegramBot\Exceptions\ValidationException;
use App\Component\TelegramBot\Utils\Validator;

/**
 * Класс для работы с постами телеграм каналов (имитация Stories)
 * 
 * ВАЖНО: Bot API не поддерживает работу со Stories напрямую.
 * Этот класс предоставляет функционал управления постами канала
 * для имитации поведения Stories через обычные посты.
 * 
 * Предоставляет методы для:
 * - Публикации фото и видео постов в канал
 * - Редактирования контента и подписей
 * - Удаления постов
 * - Закрепления/открепления постов
 * - Управления активными постами
 * - Ротации контента
 * 
 * @link https://core.telegram.org/bots/api
 */
class ChannelStories
{
    /**
     * Хранилище ID постов для отслеживания
     * @var array<int>
     */
    private array $trackedMessages = [];

    /**
     * @param TelegramAPI $api Telegram API клиент
     * @param Logger|null $logger Логгер для отладки
     */
    public function __construct(
        private readonly TelegramAPI $api,
        private readonly ?Logger $logger = null,
    ) {
    }

    /**
     * Отправляет фото-пост в канал (имитация Stories)
     *
     * @param string|int $chatId ID канала или username (@channel)
     * @param string $photo Путь к файлу, URL или file_id
     * @param array<string, mixed> $options Дополнительные параметры (caption, parse_mode и т.д.)
     * @return Message Отправленное сообщение
     * @throws ValidationException При некорректных параметрах
     * @throws ApiException При ошибке API
     */
    public function sendPhotoStory(string|int $chatId, string $photo, array $options = []): Message
    {
        Validator::validateChatId($chatId);

        if (isset($options['caption'])) {
            Validator::validateCaption($options['caption']);
        }

        $this->logger?->info('Отправка фото-поста в канал', [
            'chat_id' => $chatId,
            'has_caption' => isset($options['caption']),
        ]);

        try {
            $message = $this->api->sendPhoto($chatId, $photo, $options);
            
            $this->trackedMessages[] = $message->messageId;
            
            $this->logger?->info('Фото-пост успешно отправлен', [
                'chat_id' => $chatId,
                'message_id' => $message->messageId,
            ]);

            return $message;
        } catch (\Throwable $e) {
            $this->logger?->error('Ошибка отправки фото-поста', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Отправляет видео-пост в канал (имитация Stories)
     *
     * @param string|int $chatId ID канала или username (@channel)
     * @param string $video Путь к файлу, URL или file_id
     * @param array<string, mixed> $options Дополнительные параметры (caption, duration, width, height и т.д.)
     * @return Message Отправленное сообщение
     * @throws ValidationException При некорректных параметрах
     * @throws ApiException При ошибке API
     */
    public function sendVideoStory(string|int $chatId, string $video, array $options = []): Message
    {
        Validator::validateChatId($chatId);

        if (isset($options['caption'])) {
            Validator::validateCaption($options['caption']);
        }

        $this->logger?->info('Отправка видео-поста в канал', [
            'chat_id' => $chatId,
            'has_caption' => isset($options['caption']),
            'duration' => $options['duration'] ?? null,
        ]);

        try {
            $message = $this->api->sendVideo($chatId, $video, $options);
            
            $this->trackedMessages[] = $message->messageId;
            
            $this->logger?->info('Видео-пост успешно отправлен', [
                'chat_id' => $chatId,
                'message_id' => $message->messageId,
            ]);

            return $message;
        } catch (\Throwable $e) {
            $this->logger?->error('Ошибка отправки видео-поста', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Редактирует подпись к посту
     *
     * @param string|int $chatId ID канала или username (@channel)
     * @param int $messageId ID сообщения
     * @param string $caption Новая подпись
     * @param array<string, mixed> $options Дополнительные параметры (parse_mode и т.д.)
     * @return Message Отредактированное сообщение
     * @throws ValidationException При некорректных параметрах
     * @throws ApiException При ошибке API
     */
    public function editStoryCaption(
        string|int $chatId,
        int $messageId,
        string $caption,
        array $options = []
    ): Message {
        Validator::validateChatId($chatId);
        Validator::validateCaption($caption);

        $this->logger?->info('Редактирование подписи поста', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'caption_length' => mb_strlen($caption),
        ]);

        try {
            $message = $this->api->editMessageCaption($chatId, $messageId, $caption, $options);
            
            $this->logger?->info('Подпись поста успешно отредактирована', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
            ]);

            return $message;
        } catch (\Throwable $e) {
            $this->logger?->error('Ошибка редактирования подписи поста', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Удаляет пост из канала
     *
     * @param string|int $chatId ID канала или username (@channel)
     * @param int $messageId ID сообщения
     * @return bool True при успешном удалении
     * @throws ValidationException При некорректных параметрах
     * @throws ApiException При ошибке API
     */
    public function deleteStory(string|int $chatId, int $messageId): bool
    {
        Validator::validateChatId($chatId);

        $this->logger?->info('Удаление поста', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);

        try {
            $result = $this->api->deleteMessage($chatId, $messageId);
            
            // Удаляем из отслеживаемых
            $this->trackedMessages = array_filter(
                $this->trackedMessages,
                fn($id) => $id !== $messageId
            );
            
            $this->logger?->info('Пост успешно удален', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
            ]);

            return $result;
        } catch (\Throwable $e) {
            $this->logger?->error('Ошибка удаления поста', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Закрепляет пост в канале
     *
     * @param string|int $chatId ID канала или username (@channel)
     * @param int $messageId ID сообщения
     * @param bool $disableNotification Отключить уведомление о закреплении
     * @return bool True при успешном закреплении
     * @throws ValidationException При некорректных параметрах
     * @throws ApiException При ошибке API
     */
    public function pinStory(string|int $chatId, int $messageId, bool $disableNotification = false): bool
    {
        Validator::validateChatId($chatId);

        $this->logger?->info('Закрепление поста', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);

        try {
            $result = $this->api->pinChatMessage($chatId, $messageId, $disableNotification);
            
            $this->logger?->info('Пост успешно закреплен', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
            ]);

            return $result;
        } catch (\Throwable $e) {
            $this->logger?->error('Ошибка закрепления поста', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Открепляет пост в канале
     *
     * @param string|int $chatId ID канала или username (@channel)
     * @param int|null $messageId ID сообщения (null для открепления всех)
     * @return bool True при успешном открепении
     * @throws ValidationException При некорректных параметрах
     * @throws ApiException При ошибке API
     */
    public function unpinStory(string|int $chatId, ?int $messageId = null): bool
    {
        Validator::validateChatId($chatId);

        $this->logger?->info('Открепление поста', [
            'chat_id' => $chatId,
            'message_id' => $messageId ?? 'all',
        ]);

        try {
            $result = $messageId 
                ? $this->api->unpinChatMessage($chatId, $messageId)
                : $this->api->unpinAllChatMessages($chatId);
            
            $this->logger?->info('Пост успешно откреплен', [
                'chat_id' => $chatId,
                'message_id' => $messageId ?? 'all',
            ]);

            return $result;
        } catch (\Throwable $e) {
            $this->logger?->error('Ошибка открепления поста', [
                'chat_id' => $chatId,
                'message_id' => $messageId ?? 'all',
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Получает список отслеживаемых ID сообщений
     *
     * @return array<int> Массив ID сообщений
     */
    public function getTrackedMessages(): array
    {
        return $this->trackedMessages;
    }

    /**
     * Выполняет ротацию постов: удаляет старые и добавляет новые
     *
     * @param string|int $chatId ID канала или username (@channel)
     * @param int $maxStories Максимальное количество постов (старые удаляются)
     * @param array<array<string, mixed>> $newStories Массив новых постов для добавления
     * @return array{deleted: int, added: int, active: int} Статистика ротации
     * @throws ValidationException При некорректных параметрах
     * @throws ApiException При ошибке API
     */
    public function rotateStories(string|int $chatId, int $maxStories = 100, array $newStories = []): array
    {
        Validator::validateChatId($chatId);

        if ($maxStories < 1) {
            throw new ValidationException(
                'Максимальное количество постов должно быть больше 0',
                'maxStories',
                $maxStories
            );
        }

        $this->logger?->info('Начало ротации постов', [
            'chat_id' => $chatId,
            'max_stories' => $maxStories,
            'new_stories_count' => count($newStories),
        ]);

        $stats = [
            'deleted' => 0,
            'added' => 0,
            'active' => count($this->trackedMessages),
        ];

        try {
            // Определяем, сколько постов нужно удалить
            $totalAfterAdd = count($this->trackedMessages) + count($newStories);
            $toDelete = max(0, $totalAfterAdd - $maxStories);

            // Удаляем старые посты (FIFO - первые добавленные удаляются первыми)
            if ($toDelete > 0) {
                $this->logger?->info('Удаление старых постов', [
                    'chat_id' => $chatId,
                    'to_delete' => $toDelete,
                ]);

                for ($i = 0; $i < $toDelete && $i < count($this->trackedMessages); $i++) {
                    $messageId = $this->trackedMessages[$i];
                    try {
                        $this->deleteStory($chatId, $messageId);
                        $stats['deleted']++;
                    } catch (\Throwable $e) {
                        $this->logger?->warning('Не удалось удалить пост', [
                            'chat_id' => $chatId,
                            'message_id' => $messageId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Добавляем новые посты
            foreach ($newStories as $storyData) {
                try {
                    $type = $storyData['type'] ?? 'photo';
                    $media = $storyData['media'] ?? null;
                    $options = $storyData['options'] ?? [];

                    if (!$media) {
                        continue;
                    }

                    if ($type === 'video') {
                        $this->sendVideoStory($chatId, $media, $options);
                    } else {
                        $this->sendPhotoStory($chatId, $media, $options);
                    }

                    $stats['added']++;
                } catch (\Throwable $e) {
                    $this->logger?->warning('Не удалось добавить новый пост', [
                        'chat_id' => $chatId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Обновляем количество активных постов
            $stats['active'] = count($this->trackedMessages);

            $this->logger?->info('Ротация постов завершена', [
                'chat_id' => $chatId,
                'stats' => $stats,
            ]);

            return $stats;
        } catch (\Throwable $e) {
            $this->logger?->error('Ошибка ротации постов', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Удаляет все отслеживаемые посты
     *
     * @param string|int $chatId ID канала или username (@channel)
     * @return int Количество удаленных постов
     * @throws ValidationException При некорректных параметрах
     * @throws ApiException При ошибке API
     */
    public function deleteAllStories(string|int $chatId): int
    {
        Validator::validateChatId($chatId);

        $this->logger?->info('Удаление всех отслеживаемых постов', [
            'chat_id' => $chatId,
            'count' => count($this->trackedMessages),
        ]);

        $deleted = 0;

        try {
            $messagesToDelete = $this->trackedMessages;
            
            foreach ($messagesToDelete as $messageId) {
                try {
                    $this->deleteStory($chatId, $messageId);
                    $deleted++;
                } catch (\Throwable $e) {
                    $this->logger?->warning('Не удалось удалить пост', [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->logger?->info('Все отслеживаемые посты удалены', [
                'chat_id' => $chatId,
                'deleted' => $deleted,
            ]);

            return $deleted;
        } catch (\Throwable $e) {
            $this->logger?->error('Ошибка удаления всех постов', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Очищает список отслеживаемых сообщений
     *
     * @return void
     */
    public function clearTracking(): void
    {
        $this->trackedMessages = [];
        $this->logger?->info('Список отслеживаемых сообщений очищен');
    }

    /**
     * Добавляет ID сообщения в список отслеживаемых
     *
     * @param int $messageId ID сообщения
     * @return void
     */
    public function trackMessage(int $messageId): void
    {
        if (!in_array($messageId, $this->trackedMessages, true)) {
            $this->trackedMessages[] = $messageId;
        }
    }

    /**
     * Удаляет ID сообщения из списка отслеживаемых
     *
     * @param int $messageId ID сообщения
     * @return void
     */
    public function untrackMessage(int $messageId): void
    {
        $this->trackedMessages = array_filter(
            $this->trackedMessages,
            fn($id) => $id !== $messageId
        );
    }
}

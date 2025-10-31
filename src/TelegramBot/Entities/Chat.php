<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Entities;

/**
 * Сущность чата Telegram
 * 
 * Представляет чат (личный, группа, супергруппа или канал)
 * 
 * @link https://core.telegram.org/bots/api#chat
 */
class Chat
{
    public const TYPE_PRIVATE = 'private';
    public const TYPE_GROUP = 'group';
    public const TYPE_SUPERGROUP = 'supergroup';
    public const TYPE_CHANNEL = 'channel';

    /**
     * @param int $id Уникальный идентификатор чата
     * @param string $type Тип чата: private, group, supergroup или channel
     * @param string|null $title Название чата (для супергрупп, каналов и групп)
     * @param string|null $username Username чата (для публичных супергрупп и каналов)
     * @param string|null $firstName Имя собеседника (для личных чатов)
     * @param string|null $lastName Фамилия собеседника (для личных чатов)
     * @param bool|null $isForum True, если супергруппа является форумом
     */
    public function __construct(
        public readonly int $id,
        public readonly string $type,
        public readonly ?string $title = null,
        public readonly ?string $username = null,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly ?bool $isForum = null,
    ) {
    }

    /**
     * Создает объект Chat из массива данных Telegram API
     *
     * @param array<string, mixed> $data Данные от Telegram API
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int)$data['id'],
            type: (string)$data['type'],
            title: isset($data['title']) ? (string)$data['title'] : null,
            username: isset($data['username']) ? (string)$data['username'] : null,
            firstName: isset($data['first_name']) ? (string)$data['first_name'] : null,
            lastName: isset($data['last_name']) ? (string)$data['last_name'] : null,
            isForum: isset($data['is_forum']) ? (bool)$data['is_forum'] : null,
        );
    }

    /**
     * Проверяет, является ли чат личным
     */
    public function isPrivate(): bool
    {
        return $this->type === self::TYPE_PRIVATE;
    }

    /**
     * Проверяет, является ли чат группой
     */
    public function isGroup(): bool
    {
        return $this->type === self::TYPE_GROUP;
    }

    /**
     * Проверяет, является ли чат супергруппой
     */
    public function isSupergroup(): bool
    {
        return $this->type === self::TYPE_SUPERGROUP;
    }

    /**
     * Проверяет, является ли чат каналом
     */
    public function isChannel(): bool
    {
        return $this->type === self::TYPE_CHANNEL;
    }

    /**
     * Возвращает название чата или имя пользователя
     */
    public function getDisplayName(): string
    {
        if ($this->title !== null) {
            return $this->title;
        }

        return trim(($this->firstName ?? '') . ($this->lastName ? ' ' . $this->lastName : ''));
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
            'type' => $this->type,
            'title' => $this->title,
            'username' => $this->username,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'is_forum' => $this->isForum,
        ], fn($value) => $value !== null);
    }
}

<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Entities;

/**
 * Сущность пользователя Telegram
 * 
 * Представляет пользователя или бота Telegram
 * 
 * @link https://core.telegram.org/bots/api#user
 */
class User
{
    /**
     * @param int $id Уникальный идентификатор пользователя или бота
     * @param bool $isBot True, если это бот
     * @param string $firstName Имя пользователя
     * @param string|null $lastName Фамилия пользователя
     * @param string|null $username Username пользователя
     * @param string|null $languageCode Языковой код пользователя
     * @param bool|null $isPremium True, если у пользователя Telegram Premium
     * @param bool|null $addedToAttachmentMenu True, если бот добавлен в меню вложений
     * @param bool|null $canJoinGroups True, если бот может быть добавлен в группы
     * @param bool|null $canReadAllGroupMessages True, если режим приватности отключен
     * @param bool|null $supportsInlineQueries True, если бот поддерживает inline запросы
     */
    public function __construct(
        public readonly int $id,
        public readonly bool $isBot,
        public readonly string $firstName,
        public readonly ?string $lastName = null,
        public readonly ?string $username = null,
        public readonly ?string $languageCode = null,
        public readonly ?bool $isPremium = null,
        public readonly ?bool $addedToAttachmentMenu = null,
        public readonly ?bool $canJoinGroups = null,
        public readonly ?bool $canReadAllGroupMessages = null,
        public readonly ?bool $supportsInlineQueries = null,
    ) {
    }

    /**
     * Создает объект User из массива данных Telegram API
     *
     * @param array<string, mixed> $data Данные от Telegram API
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int)$data['id'],
            isBot: (bool)$data['is_bot'],
            firstName: (string)$data['first_name'],
            lastName: isset($data['last_name']) ? (string)$data['last_name'] : null,
            username: isset($data['username']) ? (string)$data['username'] : null,
            languageCode: isset($data['language_code']) ? (string)$data['language_code'] : null,
            isPremium: isset($data['is_premium']) ? (bool)$data['is_premium'] : null,
            addedToAttachmentMenu: isset($data['added_to_attachment_menu']) ? (bool)$data['added_to_attachment_menu'] : null,
            canJoinGroups: isset($data['can_join_groups']) ? (bool)$data['can_join_groups'] : null,
            canReadAllGroupMessages: isset($data['can_read_all_group_messages']) ? (bool)$data['can_read_all_group_messages'] : null,
            supportsInlineQueries: isset($data['supports_inline_queries']) ? (bool)$data['supports_inline_queries'] : null,
        );
    }

    /**
     * Возвращает полное имя пользователя
     */
    public function getFullName(): string
    {
        return trim($this->firstName . ($this->lastName ? ' ' . $this->lastName : ''));
    }

    /**
     * Возвращает упоминание пользователя (@username или имя)
     */
    public function getMention(): string
    {
        return $this->username ? '@' . $this->username : $this->getFullName();
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
            'is_bot' => $this->isBot,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'username' => $this->username,
            'language_code' => $this->languageCode,
            'is_premium' => $this->isPremium,
            'added_to_attachment_menu' => $this->addedToAttachmentMenu,
            'can_join_groups' => $this->canJoinGroups,
            'can_read_all_group_messages' => $this->canReadAllGroupMessages,
            'supports_inline_queries' => $this->supportsInlineQueries,
        ], fn($value) => $value !== null);
    }
}

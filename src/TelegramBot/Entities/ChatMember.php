<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Entities;

/**
 * Сущность информации о члене чата
 * 
 * Представляет информацию о статусе пользователя в чате/канале
 * 
 * @link https://core.telegram.org/bots/api#chatmember
 */
class ChatMember
{
    /**
     * Статус: создатель канала/группы
     */
    public const STATUS_CREATOR = 'creator';

    /**
     * Статус: администратор
     */
    public const STATUS_ADMINISTRATOR = 'administrator';

    /**
     * Статус: участник
     */
    public const STATUS_MEMBER = 'member';

    /**
     * Статус: ограничен (restricted)
     */
    public const STATUS_RESTRICTED = 'restricted';

    /**
     * Статус: покинул чат
     */
    public const STATUS_LEFT = 'left';

    /**
     * Статус: заблокирован (kicked)
     */
    public const STATUS_KICKED = 'kicked';

    /**
     * @param string $status Статус члена чата (creator, administrator, member, restricted, left, kicked)
     * @param User $user Информация о пользователе
     * @param bool|null $isAnonymous True, если пользователь анонимен
     * @param string|null $customTitle Кастомный титул администратора
     * @param int|null $untilDate Дата окончания ограничения (для restricted/kicked)
     */
    public function __construct(
        public readonly string $status,
        public readonly User $user,
        public readonly ?bool $isAnonymous = null,
        public readonly ?string $customTitle = null,
        public readonly ?int $untilDate = null,
    ) {
    }

    /**
     * Создает объект ChatMember из массива данных Telegram API
     *
     * @param array<string, mixed> $data Данные от Telegram API
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            status: (string)$data['status'],
            user: User::fromArray($data['user']),
            isAnonymous: isset($data['is_anonymous']) ? (bool)$data['is_anonymous'] : null,
            customTitle: isset($data['custom_title']) ? (string)$data['custom_title'] : null,
            untilDate: isset($data['until_date']) ? (int)$data['until_date'] : null,
        );
    }

    /**
     * Проверяет, является ли пользователь подписчиком канала/группы
     * (creator, administrator или member)
     *
     * @return bool True если подписан
     */
    public function isSubscribed(): bool
    {
        return in_array($this->status, [
            self::STATUS_CREATOR,
            self::STATUS_ADMINISTRATOR,
            self::STATUS_MEMBER,
        ], true);
    }

    /**
     * Проверяет, является ли пользователь администратором или создателем
     *
     * @return bool True если админ или создатель
     */
    public function isAdmin(): bool
    {
        return in_array($this->status, [
            self::STATUS_CREATOR,
            self::STATUS_ADMINISTRATOR,
        ], true);
    }

    /**
     * Проверяет, покинул ли пользователь чат
     *
     * @return bool True если покинул
     */
    public function hasLeft(): bool
    {
        return $this->status === self::STATUS_LEFT;
    }

    /**
     * Проверяет, заблокирован ли пользователь
     *
     * @return bool True если заблокирован
     */
    public function isKicked(): bool
    {
        return $this->status === self::STATUS_KICKED;
    }

    /**
     * Преобразует объект в массив
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'status' => $this->status,
            'user' => $this->user->toArray(),
            'is_anonymous' => $this->isAnonymous,
            'custom_title' => $this->customTitle,
            'until_date' => $this->untilDate,
        ], fn($value) => $value !== null);
    }
}

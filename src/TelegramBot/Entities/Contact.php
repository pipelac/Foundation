<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Entities;

/**
 * Сущность контакта Telegram
 * 
 * Представляет контакт, отправленный пользователем
 * 
 * @link https://core.telegram.org/bots/api#contact
 */
class Contact
{
    /**
     * @param string $phoneNumber Номер телефона контакта
     * @param string $firstName Имя контакта
     * @param string|null $lastName Фамилия контакта
     * @param int|null $userId ID пользователя Telegram контакта
     * @param string|null $vcard Дополнительные данные о контакте в формате vCard
     */
    public function __construct(
        public readonly string $phoneNumber,
        public readonly string $firstName,
        public readonly ?string $lastName = null,
        public readonly ?int $userId = null,
        public readonly ?string $vcard = null,
    ) {
    }

    /**
     * Создает объект Contact из массива данных Telegram API
     *
     * @param array<string, mixed> $data Данные от Telegram API
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            phoneNumber: (string)$data['phone_number'],
            firstName: (string)$data['first_name'],
            lastName: isset($data['last_name']) ? (string)$data['last_name'] : null,
            userId: isset($data['user_id']) ? (int)$data['user_id'] : null,
            vcard: isset($data['vcard']) ? (string)$data['vcard'] : null,
        );
    }
}

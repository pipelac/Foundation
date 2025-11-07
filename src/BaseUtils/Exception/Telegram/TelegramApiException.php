<?php

declare(strict_types=1);

namespace App\Component\Exception\Telegram;

/**
 * Исключение для ошибок Telegram Bot API
 */
class TelegramApiException extends TelegramException
{
    /**
     * @param string $message Сообщение об ошибке
     * @param int $statusCode HTTP статус код
     * @param string|null $description Описание ошибки от API
     * @param int|null $errorCode Код ошибки от Telegram API
     */
    public function __construct(
        string $message,
        private readonly int $statusCode = 0,
        private readonly ?string $description = null,
        private readonly ?int $errorCode = null
    ) {
        parent::__construct($message, $statusCode);
    }

    /**
     * Возвращает HTTP статус код
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Возвращает описание ошибки от API
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Возвращает код ошибки от Telegram API
     *
     * @return int|null
     */
    public function getErrorCode(): ?int
    {
        return $this->errorCode;
    }
}

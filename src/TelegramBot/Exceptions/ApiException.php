<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Exceptions;

/**
 * Исключение для ошибок Telegram Bot API
 * 
 * Выбрасывается при получении ошибки от Telegram API,
 * проблемах с подключением или некорректных параметрах запроса
 */
class ApiException extends TelegramBotException
{
    /**
     * Конструктор с дополнительным контекстом ошибки API
     *
     * @param string $message Сообщение об ошибке
     * @param int $code Код ошибки Telegram API
     * @param array<string, mixed> $context Дополнительный контекст
     */
    public function __construct(
        string $message,
        int $code = 0,
        private readonly array $context = []
    ) {
        parent::__construct($message, $code);
    }

    /**
     * Возвращает контекст ошибки
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}

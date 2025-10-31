<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Exceptions;

/**
 * Исключение для ошибок валидации данных
 * 
 * Выбрасывается при некорректных параметрах, невалидных данных
 * или нарушении ограничений Telegram API
 */
class ValidationException extends TelegramBotException
{
    /**
     * Конструктор с указанием поля и значения
     *
     * @param string $message Сообщение об ошибке
     * @param string|null $field Название поля с ошибкой
     * @param mixed $value Значение, не прошедшее валидацию
     */
    public function __construct(
        string $message,
        private readonly ?string $field = null,
        private readonly mixed $value = null
    ) {
        parent::__construct($message);
    }

    /**
     * Возвращает название поля с ошибкой
     */
    public function getField(): ?string
    {
        return $this->field;
    }

    /**
     * Возвращает значение, не прошедшее валидацию
     */
    public function getValue(): mixed
    {
        return $this->value;
    }
}

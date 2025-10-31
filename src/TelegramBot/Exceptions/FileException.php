<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Exceptions;

/**
 * Исключение для ошибок работы с файлами
 * 
 * Выбрасывается при проблемах с загрузкой, отправкой файлов,
 * превышении размера или недоступности файлового пути
 */
class FileException extends TelegramBotException
{
    /**
     * Конструктор с указанием пути к файлу
     *
     * @param string $message Сообщение об ошибке
     * @param string|null $filePath Путь к файлу
     */
    public function __construct(
        string $message,
        private readonly ?string $filePath = null
    ) {
        parent::__construct($message);
    }

    /**
     * Возвращает путь к файлу
     */
    public function getFilePath(): ?string
    {
        return $this->filePath;
    }
}

<?php

declare(strict_types=1);

namespace App\Component\Exception\Email;

/**
 * Исключение для ошибок валидации параметров электронной почты
 * 
 * Бросается при:
 * - пустой теме письма
 * - некорректных email адресах
 * - некорректных настройках SMTP
 * - некорректных параметрах retry/timeout
 */
class EmailValidationException extends EmailException
{
}

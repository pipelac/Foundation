<?php

declare(strict_types=1);

namespace App\Component\Exception;

use RuntimeException;

/**
 * Базовое исключение для ошибок отправки электронной почты
 * 
 * Используется для всех операций, связанных с отправкой писем через SMTP или mail()
 */
class EmailException extends RuntimeException
{
}

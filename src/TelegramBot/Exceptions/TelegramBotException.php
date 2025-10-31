<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Exceptions;

use RuntimeException;

/**
 * Базовое исключение для всех ошибок TelegramBot модуля
 */
class TelegramBotException extends RuntimeException
{
}

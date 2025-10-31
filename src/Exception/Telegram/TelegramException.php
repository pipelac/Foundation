<?php

declare(strict_types=1);

namespace App\Component\Exception\Telegram;

use RuntimeException;

/**
 * Базовое исключение для ошибок Telegram Bot API
 */
class TelegramException extends RuntimeException
{
}

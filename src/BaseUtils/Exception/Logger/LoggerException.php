<?php

declare(strict_types=1);

namespace App\Component\Exception\Logger;

use RuntimeException;

/**
 * Базовое исключение для ошибок логирования
 * 
 * Используется для всех операций, связанных с записью логов,
 * ротацией файлов и управлением лог-файлами
 */
class LoggerException extends RuntimeException
{
}

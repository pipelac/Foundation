<?php

declare(strict_types=1);

namespace App\Component\Exception\NetworkUtil;

use RuntimeException;

/**
 * Базовое исключение для ошибок NetworkUtil
 * 
 * Используется для всех операций, связанных с выполнением сетевых команд
 */
class NetworkUtilException extends RuntimeException
{
}

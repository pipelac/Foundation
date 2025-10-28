<?php

declare(strict_types=1);

namespace App\Component\Exception;

use RuntimeException;

/**
 * Базовое исключение для ошибок HTTP клиента
 * 
 * Используется для всех операций, связанных с выполнением HTTP запросов
 */
class HttpException extends RuntimeException
{
}

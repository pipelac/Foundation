<?php

declare(strict_types=1);

namespace App\Component\Exception;

use RuntimeException;

/**
 * Базовое исключение для ошибок ProxyPool менеджера
 * 
 * Используется для всех операций, связанных с управлением пулом прокси
 */
class ProxyPoolException extends RuntimeException
{
}

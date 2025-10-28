<?php

declare(strict_types=1);

namespace App\Component\Exception;

use Exception;

/**
 * Базовое исключение для ошибок работы с базой данных
 * 
 * Используется для всех операций, связанных с выполнением запросов,
 * подключением к БД и другими database-specific операциями
 */
class DatabaseException extends Exception
{
}

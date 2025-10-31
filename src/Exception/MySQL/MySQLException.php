<?php

declare(strict_types=1);

namespace App\Component\Exception\MySQL;

use Exception;

/**
 * Базовое исключение для ошибок работы с MySQL базой данных
 * 
 * Используется для всех операций, связанных с выполнением запросов,
 * подключением к БД и другими MySQL-specific операциями
 */
class MySQLException extends Exception
{
}

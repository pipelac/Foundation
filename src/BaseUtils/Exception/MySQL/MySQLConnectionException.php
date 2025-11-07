<?php

declare(strict_types=1);

namespace App\Component\Exception\MySQL;

/**
 * Исключение для ошибок подключения к MySQL базе данных
 * 
 * Бросается при:
 * - невозможности установить соединение с БД
 * - потере соединения во время работы
 * - недоступности БД
 */
class MySQLConnectionException extends MySQLException
{
}

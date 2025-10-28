<?php

declare(strict_types=1);

namespace App\Component\Exception;

/**
 * Исключение для ошибок подключения к базе данных
 * 
 * Бросается при:
 * - невозможности установить соединение с БД
 * - потере соединения во время работы
 * - недоступности БД
 */
class ConnectionException extends DatabaseException
{
}

<?php

declare(strict_types=1);

namespace App\Component\Exception;

/**
 * Исключение для ошибок валидации параметров NetworkUtil
 * 
 * Выбрасывается при некорректных входных данных:
 * - пустые или невалидные хосты, домены, URL
 * - некорректные значения параметров команд
 * - запрещённые символы в параметрах
 */
class NetworkUtilValidationException extends NetworkUtilException
{
}

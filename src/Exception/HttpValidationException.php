<?php

declare(strict_types=1);

namespace App\Component\Exception;

/**
 * Исключение для ошибок валидации параметров HTTP запроса
 * 
 * Бросается при:
 * - пустом HTTP методе
 * - пустом URI
 * - некорректных параметрах запроса
 */
class HttpValidationException extends HttpException
{
}

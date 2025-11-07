<?php

declare(strict_types=1);

namespace App\Component\Exception\Logger;

/**
 * Исключение для ошибок валидации параметров логгера
 * 
 * Бросается при:
 * - отсутствии директории для логов
 * - отсутствии имени файла лога
 * - некорректных параметрах ротации (количество файлов, размер)
 * - недопустимом уровне логирования
 */
class LoggerValidationException extends LoggerException
{
}

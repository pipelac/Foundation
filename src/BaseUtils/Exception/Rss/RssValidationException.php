<?php

declare(strict_types=1);

namespace App\Component\Exception\Rss;

/**
 * Исключение для ошибок валидации RSS/Atom параметров
 * 
 * Бросается при:
 * - пустом URL
 * - некорректном формате URL
 * - неподдерживаемом протоколе URL
 * - отсутствии имени хоста в URL
 */
class RssValidationException extends RssException
{
}

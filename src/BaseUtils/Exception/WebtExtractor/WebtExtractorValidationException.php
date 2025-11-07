<?php

declare(strict_types=1);

namespace App\Component\Exception\WebtExtractor;

/**
 * Исключение для ошибок валидации параметров WebtExtractor
 * 
 * Бросается при:
 * - пустом URL
 * - некорректном формате URL
 * - неподдерживаемом протоколе URL
 * - отсутствии имени хоста в URL
 * - пустом HTML контенте
 * - некорректных параметрах конфигурации
 */
class WebtExtractorValidationException extends WebtExtractorException
{
}

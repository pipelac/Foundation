<?php

declare(strict_types=1);

namespace App\Rss2Tlg\Exception\Feed;

/**
 * Исключение для ошибок валидации параметров фида
 * 
 * Генерируется когда параметры фида не соответствуют требуемым правилам:
 * - отсутствуют обязательные поля (id, url, name)
 * - невалидные значения (некорректный URL, недопустимый timeout и т.д.)
 */
class FeedValidationException extends FeedConfigException
{
}

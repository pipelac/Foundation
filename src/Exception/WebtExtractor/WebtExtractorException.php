<?php

declare(strict_types=1);

namespace App\Component\Exception\WebtExtractor;

use RuntimeException;

/**
 * Базовое исключение для ошибок работы с извлечением контента из веб-страниц
 * 
 * Используется для всех операций, связанных с загрузкой и парсингом веб-страниц через Readability
 */
class WebtExtractorException extends RuntimeException
{
}

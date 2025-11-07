<?php

declare(strict_types=1);

namespace App\Component\Exception\Rss;

use RuntimeException;

/**
 * Базовое исключение для ошибок работы с RSS/Atom лентами
 * 
 * Используется для всех операций, связанных с загрузкой и парсингом RSS/Atom фидов
 */
class RssException extends RuntimeException
{
}

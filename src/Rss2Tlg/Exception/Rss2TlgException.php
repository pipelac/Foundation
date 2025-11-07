<?php

declare(strict_types=1);

namespace App\Rss2Tlg\Exception;

use RuntimeException;

/**
 * Базовое исключение для модуля Rss2Tlg
 * 
 * Используется как родительский класс для всех специфичных исключений модуля,
 * позволяет отлавливать все ошибки Rss2Tlg единым блоком catch.
 */
class Rss2TlgException extends RuntimeException
{
}

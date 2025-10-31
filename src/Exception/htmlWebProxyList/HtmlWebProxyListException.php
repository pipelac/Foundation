<?php

declare(strict_types=1);

namespace App\Component\Exception\htmlWebProxyList;

use RuntimeException;

/**
 * Базовое исключение для ошибок HtmlWebProxyList
 * 
 * Используется для всех операций, связанных с получением списка прокси с htmlweb.ru
 */
class HtmlWebProxyListException extends RuntimeException
{
}

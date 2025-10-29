<?php

declare(strict_types=1);

namespace App\Component\Exception;

/**
 * Исключение для ошибок валидации HtmlWebProxyList
 * 
 * Выбрасывается при передаче некорректных параметров конфигурации
 * или других проблемах валидации
 */
class HtmlWebProxyListValidationException extends HtmlWebProxyListException
{
}

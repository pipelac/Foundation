<?php

declare(strict_types=1);

namespace App\Component\Exception;

/**
 * Исключение для ошибок валидации ProxyPool менеджера
 * 
 * Выбрасывается при передаче некорректных параметров конфигурации,
 * невалидных прокси URL или других проблемах валидации
 */
class ProxyPoolValidationException extends ProxyPoolException
{
}

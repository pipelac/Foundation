<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Exceptions;

/**
 * Исключение для ошибок контроля доступа
 * 
 * Выбрасывается при проблемах с загрузкой конфигурации
 * или проверкой прав доступа пользователей
 */
class AccessControlException extends TelegramBotException
{
}

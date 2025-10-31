<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Exceptions;

/**
 * Исключение для ошибок обработки webhook
 * 
 * Выбрасывается при проблемах с получением, парсингом
 * или валидацией входящих webhook запросов
 */
class WebhookException extends TelegramBotException
{
}

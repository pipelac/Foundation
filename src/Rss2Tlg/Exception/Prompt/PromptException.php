<?php

declare(strict_types=1);

namespace App\Rss2Tlg\Exception\Prompt;

use App\Rss2Tlg\Exception\Rss2TlgException;

/**
 * Базовое исключение для ошибок работы с промптами
 * 
 * Используется для всех операций, связанных с загрузкой, парсингом
 * и управлением файлами промптов для AI-анализа.
 */
class PromptException extends Rss2TlgException
{
}

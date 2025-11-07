<?php

declare(strict_types=1);

namespace App\Rss2Tlg\Exception\AI;

use App\Rss2Tlg\Exception\Rss2TlgException;

/**
 * Базовое исключение для ошибок AI-анализа новостей
 * 
 * Используется для всех операций, связанных с отправкой запросов к AI API,
 * обработкой ответов и анализом новостного контента.
 */
class AIAnalysisException extends Rss2TlgException
{
}

<?php

declare(strict_types=1);

namespace App\Rss2Tlg\Exception\AI;

/**
 * Исключение для ошибок парсинга ответов от AI
 * 
 * Генерируется когда:
 * - Ответ от AI не является валидным JSON
 * - JSON структура не соответствует ожидаемой схеме
 * - Отсутствуют обязательные поля в ответе
 */
class AIParsingException extends AIAnalysisException
{
}

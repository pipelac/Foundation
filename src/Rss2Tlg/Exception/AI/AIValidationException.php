<?php

declare(strict_types=1);

namespace App\Rss2Tlg\Exception\AI;

/**
 * Исключение для ошибок валидации результатов AI-анализа
 * 
 * Генерируется когда результат анализа прошел парсинг JSON,
 * но содержимое полей не соответствует ожидаемым критериям
 * (некорректные значения, пустые обязательные поля и т.д.)
 */
class AIValidationException extends AIAnalysisException
{
}

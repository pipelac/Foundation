<?php

declare(strict_types=1);

namespace App\Rss2Tlg\Exception\Prompt;

/**
 * Исключение для отсутствующих файлов промптов
 * 
 * Генерируется когда запрашиваемый промпт не найден в директории промптов,
 * или когда директория промптов не существует.
 */
class PromptNotFoundException extends PromptException
{
}

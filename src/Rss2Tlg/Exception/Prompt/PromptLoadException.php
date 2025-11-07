<?php

declare(strict_types=1);

namespace App\Rss2Tlg\Exception\Prompt;

/**
 * Исключение для ошибок загрузки промптов
 * 
 * Генерируется когда файл промпта существует, но произошла ошибка
 * при чтении его содержимого (нет прав доступа, ошибка чтения и т.д.)
 */
class PromptLoadException extends PromptException
{
}

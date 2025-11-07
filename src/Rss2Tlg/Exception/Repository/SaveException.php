<?php

declare(strict_types=1);

namespace App\Rss2Tlg\Exception\Repository;

/**
 * Исключение для ошибок сохранения данных в БД
 * 
 * Генерируется когда операция сохранения (INSERT/UPDATE) завершилась ошибкой:
 * - Нарушение constraints БД
 * - Проблемы с подключением
 * - Ошибки валидации данных перед сохранением
 */
class SaveException extends RepositoryException
{
}

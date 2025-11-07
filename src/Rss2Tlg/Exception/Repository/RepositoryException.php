<?php

declare(strict_types=1);

namespace App\Rss2Tlg\Exception\Repository;

use App\Rss2Tlg\Exception\Rss2TlgException;

/**
 * Базовое исключение для ошибок работы с репозиториями
 * 
 * Используется для всех операций, связанных с сохранением, загрузкой
 * и управлением данными в БД через репозитории (Items, Publications, AI Analysis).
 */
class RepositoryException extends Rss2TlgException
{
}

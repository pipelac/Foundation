<?php

declare(strict_types=1);

namespace App\Component\Exception;

/**
 * Исключение для ошибок управления транзакциями
 * 
 * Бросается при попытке некорректной работы с транзакциями:
 * - начало транзакции, когда она уже активна
 * - commit/rollback без активной транзакции
 * - ошибки при выполнении операций транзакций
 */
class TransactionException extends DatabaseException
{
}

<?php

declare(strict_types=1);

namespace App\Component\Exception\Snmp;

use Exception;

/**
 * Базовое исключение для ошибок работы с SNMP
 * 
 * Используется для всех операций, связанных с SNMP запросами,
 * обработкой данных и другими SNMP-specific операциями
 */
class SnmpException extends Exception
{
}

<?php

declare(strict_types=1);

namespace App\Component\Exception\Snmp;

/**
 * Исключение при ошибках валидации параметров SNMP
 * 
 * Выбрасывается при некорректных параметрах конфигурации,
 * неверных OID, типах данных и других проблемах валидации
 */
class SnmpValidationException extends SnmpException
{
}

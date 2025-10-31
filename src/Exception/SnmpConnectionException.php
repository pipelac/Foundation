<?php

declare(strict_types=1);

namespace App\Component\Exception;

/**
 * Исключение при ошибках подключения к SNMP агенту
 * 
 * Выбрасывается когда не удается установить соединение с SNMP агентом
 * или при проблемах с сетью
 */
class SnmpConnectionException extends SnmpException
{
}

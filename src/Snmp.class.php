<?php

declare(strict_types=1);

namespace App\Component;

use App\Component\Exception\SnmpException;
use App\Component\Exception\SnmpConnectionException;
use App\Component\Exception\SnmpValidationException;

/**
 * Профессиональный класс-обертка для работы с SNMP протоколом
 * 
 * Возможности:
 * - Строгая типизация на уровне PHP 8.1+
 * - Поддержка SNMPv1, SNMPv2c и SNMPv3
 * - Операции GET, GETNEXT, WALK, SET
 * - Множественные операции (GET/SET нескольких OID одновременно)
 * - Поддержка различных типов данных SNMP
 * - Автоматическая валидация параметров
 * - Структурированное логирование через Logger
 * - Специализированные исключения для разных типов ошибок
 * - Поддержка тайм-аутов и повторных попыток
 * - Безопасная обработка ошибок
 * 
 * Системные требования:
 * - PHP 8.1 или выше
 * - PHP SNMP расширение (ext-snmp)
 * - SNMP агент на целевом устройстве
 */
class Snmp
{
    /**
     * Внутренний объект SNMP
     */
    private ?\SNMP $snmp = null;

    /**
     * Опциональный логгер для записи операций и ошибок
     */
    private readonly ?Logger $logger;

    /**
     * Опциональный загрузчик OID конфигураций
     */
    private readonly ?SnmpOid $oidLoader;

    /**
     * Тип устройства для загрузки специфичных OID
     */
    private ?string $deviceType = null;

    /**
     * Хост SNMP агента
     */
    private readonly string $host;

    /**
     * Community string (для v1/v2c) или security name (для v3)
     */
    private readonly string $community;

    /**
     * Версия протокола SNMP
     */
    private readonly int $version;

    /**
     * Тайм-аут соединения в микросекундах
     */
    private readonly int $timeout;

    /**
     * Количество попыток повтора запроса
     */
    private readonly int $retries;

    /**
     * Параметры безопасности для SNMPv3
     * @var array<string, mixed>|null
     */
    private readonly ?array $v3SecurityParams;

    /**
     * Константы версий SNMP
     */
    public const VERSION_1 = \SNMP::VERSION_1;
    public const VERSION_2C = \SNMP::VERSION_2C;
    public const VERSION_2c = \SNMP::VERSION_2c;
    public const VERSION_3 = \SNMP::VERSION_3;

    /**
     * Константы типов данных для SET операций
     */
    public const TYPE_INTEGER = 'i';
    public const TYPE_UNSIGNED = 'u';
    public const TYPE_STRING = 's';
    public const TYPE_OBJID = 'o';
    public const TYPE_IPADDRESS = 'a';
    public const TYPE_COUNTER32 = 'c';
    public const TYPE_GAUGE32 = 'g';
    public const TYPE_TIMETICKS = 't';
    public const TYPE_OPAQUE = 'x';
    public const TYPE_COUNTER64 = 'C';
    public const TYPE_BITS = 'b';

    /**
     * Конструктор класса с валидацией параметров и установкой соединения
     * 
     * @param array{
     *     host: string,
     *     community?: string,
     *     version?: int,
     *     timeout?: int,
     *     retries?: int,
     *     port?: int,
     *     device_type?: string,
     *     v3_security_level?: string,
     *     v3_auth_protocol?: string,
     *     v3_auth_passphrase?: string,
     *     v3_privacy_protocol?: string,
     *     v3_privacy_passphrase?: string
     * } $config Конфигурация SNMP соединения
     * @param Logger|null $logger Логгер для записи операций и ошибок
     * @param SnmpOid|null $oidLoader Загрузчик OID конфигураций
     * 
     * @throws SnmpConnectionException Если не удалось подключиться к SNMP агенту
     * @throws SnmpValidationException Если конфигурация некорректна
     */
    public function __construct(array $config, ?Logger $logger = null, ?SnmpOid $oidLoader = null)
    {
        $this->logger = $logger;
        $this->oidLoader = $oidLoader;
        $this->deviceType = $config['device_type'] ?? null;
        
        try {
            $this->validateConfig($config);
            
            $this->host = (string)$config['host'];
            $this->community = (string)($config['community'] ?? 'public');
            $this->version = (int)($config['version'] ?? self::VERSION_2C);
            $this->timeout = (int)($config['timeout'] ?? 1000000); // 1 секунда по умолчанию
            $this->retries = (int)($config['retries'] ?? 3);
            
            // Извлечение параметров безопасности для SNMPv3
            if ($this->version === self::VERSION_3) {
                $this->v3SecurityParams = [
                    'sec_level' => (string)($config['v3_security_level'] ?? 'noAuthNoPriv'),
                    'auth_protocol' => (string)($config['v3_auth_protocol'] ?? ''),
                    'auth_passphrase' => (string)($config['v3_auth_passphrase'] ?? ''),
                    'priv_protocol' => (string)($config['v3_privacy_protocol'] ?? ''),
                    'priv_passphrase' => (string)($config['v3_privacy_passphrase'] ?? ''),
                ];
            } else {
                $this->v3SecurityParams = null;
            }
            
            $port = (int)($config['port'] ?? 161);
            $hostWithPort = $port !== 161 ? "{$this->host}:{$port}" : $this->host;
            
            $this->connect($hostWithPort);
            
            $this->log('info', 'SNMP соединение успешно установлено', [
                'host' => $this->host,
                'version' => $this->getVersionName($this->version),
                'timeout' => $this->timeout,
                'retries' => $this->retries,
            ]);
            
        } catch (SnmpException $e) {
            $this->log('error', 'Ошибка при создании SNMP соединения', [
                'host' => $config['host'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Деструктор - закрывает SNMP соединение
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Валидирует конфигурацию SNMP
     * 
     * @param array<string, mixed> $config Параметры конфигурации
     * @throws SnmpValidationException Если конфигурация некорректна
     */
    private function validateConfig(array $config): void
    {
        if (!isset($config['host']) || trim((string)$config['host']) === '') {
            throw new SnmpValidationException('Не указан хост SNMP агента');
        }

        if (isset($config['version'])) {
            $version = (int)$config['version'];
            if (!in_array($version, [self::VERSION_1, self::VERSION_2C, self::VERSION_3], true)) {
                throw new SnmpValidationException('Недопустимая версия SNMP протокола: ' . $version);
            }
        }

        if (isset($config['timeout'])) {
            $timeout = (int)$config['timeout'];
            if ($timeout <= 0) {
                throw new SnmpValidationException('Тайм-аут должен быть положительным числом');
            }
        }

        if (isset($config['retries'])) {
            $retries = (int)$config['retries'];
            if ($retries < 0) {
                throw new SnmpValidationException('Количество повторов не может быть отрицательным');
            }
        }

        // Валидация параметров SNMPv3
        if (isset($config['version']) && (int)$config['version'] === self::VERSION_3) {
            $this->validateV3Config($config);
        }
    }

    /**
     * Валидирует конфигурацию SNMPv3
     * 
     * @param array<string, mixed> $config Параметры конфигурации
     * @throws SnmpValidationException Если конфигурация некорректна
     */
    private function validateV3Config(array $config): void
    {
        $validSecLevels = ['noAuthNoPriv', 'authNoPriv', 'authPriv'];
        
        if (isset($config['v3_security_level'])) {
            $secLevel = (string)$config['v3_security_level'];
            if (!in_array($secLevel, $validSecLevels, true)) {
                throw new SnmpValidationException(
                    'Недопустимый уровень безопасности SNMPv3: ' . $secLevel
                );
            }

            if ($secLevel === 'authNoPriv' || $secLevel === 'authPriv') {
                if (!isset($config['v3_auth_protocol']) || trim((string)$config['v3_auth_protocol']) === '') {
                    throw new SnmpValidationException('Не указан протокол аутентификации для SNMPv3');
                }
                if (!isset($config['v3_auth_passphrase']) || trim((string)$config['v3_auth_passphrase']) === '') {
                    throw new SnmpValidationException('Не указана парольная фраза аутентификации для SNMPv3');
                }
            }

            if ($secLevel === 'authPriv') {
                if (!isset($config['v3_privacy_protocol']) || trim((string)$config['v3_privacy_protocol']) === '') {
                    throw new SnmpValidationException('Не указан протокол конфиденциальности для SNMPv3');
                }
                if (!isset($config['v3_privacy_passphrase']) || trim((string)$config['v3_privacy_passphrase']) === '') {
                    throw new SnmpValidationException('Не указана парольная фраза конфиденциальности для SNMPv3');
                }
            }
        }
    }

    /**
     * Устанавливает соединение с SNMP агентом
     * 
     * @param string $host Хост с опциональным портом
     * @throws SnmpConnectionException Если не удалось подключиться
     */
    private function connect(string $host): void
    {
        try {
            if ($this->version === self::VERSION_3 && $this->v3SecurityParams !== null) {
                $this->snmp = new \SNMP(
                    $this->version,
                    $host,
                    $this->community,
                    $this->timeout,
                    $this->retries
                );
                
                // Настройка параметров безопасности SNMPv3
                $this->snmp->setSecurity(
                    $this->v3SecurityParams['sec_level'],
                    $this->v3SecurityParams['auth_protocol'],
                    $this->v3SecurityParams['auth_passphrase'],
                    $this->v3SecurityParams['priv_protocol'],
                    $this->v3SecurityParams['priv_passphrase']
                );
            } else {
                $this->snmp = new \SNMP(
                    $this->version,
                    $host,
                    $this->community,
                    $this->timeout,
                    $this->retries
                );
            }

            // Устанавливаем быстрое отображение OID
            $this->snmp->quick_print = true;
            $this->snmp->oid_output_format = SNMP_OID_OUTPUT_NUMERIC;
            $this->snmp->valueretrieval = SNMP_VALUE_PLAIN;

        } catch (\SNMPException $e) {
            throw new SnmpConnectionException(
                'Не удалось установить SNMP соединение с ' . $host . ': ' . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Получает значение одного OID
     * 
     * @param string $oid OID для запроса
     * @return string|false Значение OID или false при ошибке
     * @throws SnmpException При критических ошибках
     */
    public function get(string $oid): string|false
    {
        $this->validateOid($oid);
        
        try {
            $this->log('debug', 'SNMP GET запрос', ['oid' => $oid]);
            
            $result = $this->snmp?->get($oid);
            
            $this->log('debug', 'SNMP GET ответ', [
                'oid' => $oid,
                'value' => $result,
            ]);
            
            return $result;
            
        } catch (\SNMPException $e) {
            $this->log('error', 'Ошибка SNMP GET', [
                'oid' => $oid,
                'error' => $e->getMessage(),
            ]);
            throw new SnmpException('SNMP GET ошибка для OID ' . $oid . ': ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * Получает значения нескольких OID одновременно
     * 
     * @param array<int, string> $oids Массив OID для запроса
     * @return array<string, string|false> Ассоциативный массив OID => значение
     * @throws SnmpException При критических ошибках
     */
    public function getMultiple(array $oids): array
    {
        if (empty($oids)) {
            throw new SnmpValidationException('Массив OID не может быть пустым');
        }

        foreach ($oids as $oid) {
            $this->validateOid($oid);
        }

        try {
            $this->log('debug', 'SNMP GET множественный запрос', ['oids' => $oids]);
            
            $result = $this->snmp?->get($oids) ?: [];
            
            $this->log('debug', 'SNMP GET множественный ответ', [
                'count' => count($result),
                'result' => $result,
            ]);
            
            return $result;
            
        } catch (\SNMPException $e) {
            $this->log('error', 'Ошибка SNMP GET множественный', [
                'oids' => $oids,
                'error' => $e->getMessage(),
            ]);
            throw new SnmpException('SNMP GET ошибка для множественных OID: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * Получает следующий OID в дереве MIB
     * 
     * @param string $oid OID для запроса
     * @return string|false Значение следующего OID или false при ошибке
     * @throws SnmpException При критических ошибках
     */
    public function getNext(string $oid): string|false
    {
        $this->validateOid($oid);
        
        try {
            $this->log('debug', 'SNMP GETNEXT запрос', ['oid' => $oid]);
            
            $result = $this->snmp?->getnext($oid);
            
            $this->log('debug', 'SNMP GETNEXT ответ', [
                'oid' => $oid,
                'value' => $result,
            ]);
            
            return $result;
            
        } catch (\SNMPException $e) {
            $this->log('error', 'Ошибка SNMP GETNEXT', [
                'oid' => $oid,
                'error' => $e->getMessage(),
            ]);
            throw new SnmpException('SNMP GETNEXT ошибка для OID ' . $oid . ': ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * Обходит дерево MIB начиная с указанного OID
     * 
     * @param string $oid Корневой OID для обхода
     * @param bool $suffixAsKey Использовать суффикс OID в качестве ключа массива
     * @param int $maxRepetitions Максимальное количество повторений (для SNMPv2c/v3)
     * @param int $nonRepeaters Количество неповторяющихся переменных (для SNMPv2c/v3)
     * @return array<string, mixed>|false Массив OID => значение или false при ошибке
     * @throws SnmpException При критических ошибках
     */
    public function walk(
        string $oid,
        bool $suffixAsKey = false,
        int $maxRepetitions = 20,
        int $nonRepeaters = 0
    ): array|false {
        $this->validateOid($oid);
        
        try {
            $this->log('debug', 'SNMP WALK запрос', [
                'oid' => $oid,
                'suffix_as_key' => $suffixAsKey,
                'max_repetitions' => $maxRepetitions,
            ]);
            
            $result = $this->snmp?->walk($oid, $suffixAsKey, $maxRepetitions, $nonRepeaters) ?: [];
            
            $this->log('debug', 'SNMP WALK ответ', [
                'oid' => $oid,
                'count' => count($result),
            ]);
            
            return $result;
            
        } catch (\SNMPException $e) {
            $this->log('error', 'Ошибка SNMP WALK', [
                'oid' => $oid,
                'error' => $e->getMessage(),
            ]);
            throw new SnmpException('SNMP WALK ошибка для OID ' . $oid . ': ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * Устанавливает значение OID
     * 
     * @param string $oid OID для установки
     * @param string $type Тип данных (используйте константы TYPE_*)
     * @param string|int $value Значение для установки
     * @return bool Успешность операции
     * @throws SnmpException При критических ошибках
     */
    public function set(string $oid, string $type, string|int $value): bool
    {
        $this->validateOid($oid);
        $this->validateType($type);
        
        try {
            $this->log('info', 'SNMP SET запрос', [
                'oid' => $oid,
                'type' => $type,
                'value' => $value,
            ]);
            
            $result = $this->snmp?->set($oid, $type, (string)$value) ?? false;
            
            $this->log('info', 'SNMP SET ответ', [
                'oid' => $oid,
                'success' => $result,
            ]);
            
            return $result;
            
        } catch (\SNMPException $e) {
            $this->log('error', 'Ошибка SNMP SET', [
                'oid' => $oid,
                'type' => $type,
                'value' => $value,
                'error' => $e->getMessage(),
            ]);
            throw new SnmpException('SNMP SET ошибка для OID ' . $oid . ': ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * Устанавливает значения нескольких OID одновременно
     * 
     * @param array<int, string> $oids Массив OID для установки
     * @param array<int, string> $types Массив типов данных
     * @param array<int, string|int> $values Массив значений
     * @return bool Успешность операции
     * @throws SnmpException При критических ошибках
     */
    public function setMultiple(array $oids, array $types, array $values): bool
    {
        if (empty($oids)) {
            throw new SnmpValidationException('Массив OID не может быть пустым');
        }

        if (count($oids) !== count($types) || count($oids) !== count($values)) {
            throw new SnmpValidationException('Массивы OID, типов и значений должны иметь одинаковую длину');
        }

        foreach ($oids as $oid) {
            $this->validateOid($oid);
        }

        foreach ($types as $type) {
            $this->validateType($type);
        }

        try {
            $this->log('info', 'SNMP SET множественный запрос', [
                'count' => count($oids),
                'oids' => $oids,
            ]);
            
            $result = $this->snmp?->set($oids, $types, $values) ?? false;
            
            $this->log('info', 'SNMP SET множественный ответ', [
                'success' => $result,
            ]);
            
            return $result;
            
        } catch (\SNMPException $e) {
            $this->log('error', 'Ошибка SNMP SET множественный', [
                'oids' => $oids,
                'error' => $e->getMessage(),
            ]);
            throw new SnmpException('SNMP SET ошибка для множественных OID: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * Получает информацию о системе (sysDescr, sysObjectID, sysUpTime и т.д.)
     * 
     * @return array<string, mixed> Массив с системной информацией
     * @throws SnmpException При критических ошибках
     */
    public function getSystemInfo(): array
    {
        $systemOids = [
            'sysDescr' => '.1.3.6.1.2.1.1.1.0',
            'sysObjectID' => '.1.3.6.1.2.1.1.2.0',
            'sysUpTime' => '.1.3.6.1.2.1.1.3.0',
            'sysContact' => '.1.3.6.1.2.1.1.4.0',
            'sysName' => '.1.3.6.1.2.1.1.5.0',
            'sysLocation' => '.1.3.6.1.2.1.1.6.0',
        ];

        try {
            $this->log('debug', 'Запрос системной информации');
            
            $result = $this->getMultiple(array_values($systemOids));
            
            $systemInfo = [];
            foreach ($systemOids as $name => $oid) {
                $systemInfo[$name] = $result[$oid] ?? null;
            }
            
            $this->log('debug', 'Получена системная информация', $systemInfo);
            
            return $systemInfo;
            
        } catch (SnmpException $e) {
            $this->log('error', 'Ошибка при получении системной информации', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Получает список сетевых интерфейсов
     * 
     * @return array<int, array<string, mixed>> Массив информации об интерфейсах
     * @throws SnmpException При критических ошибках
     */
    public function getNetworkInterfaces(): array
    {
        try {
            $this->log('debug', 'Запрос списка сетевых интерфейсов');
            
            // ifDescr - описание интерфейсов
            $ifDescr = $this->walk('.1.3.6.1.2.1.2.2.1.2', true);
            
            if ($ifDescr === false || empty($ifDescr)) {
                return [];
            }
            
            $interfaces = [];
            foreach ($ifDescr as $index => $description) {
                $interfaces[(int)$index] = [
                    'index' => (int)$index,
                    'description' => $description,
                    'status' => null,
                    'speed' => null,
                    'mtu' => null,
                ];
            }
            
            $this->log('debug', 'Получен список интерфейсов', [
                'count' => count($interfaces),
            ]);
            
            return $interfaces;
            
        } catch (SnmpException $e) {
            $this->log('error', 'Ошибка при получении списка интерфейсов', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Валидирует OID
     * 
     * @param string $oid OID для валидации
     * @throws SnmpValidationException Если OID некорректен
     */
    private function validateOid(string $oid): void
    {
        $oid = trim($oid);
        
        if ($oid === '') {
            throw new SnmpValidationException('OID не может быть пустым');
        }

        // Проверка формата OID (числовой или текстовый)
        if (!preg_match('/^\.?[\d\.]+$|^[a-zA-Z][\w\-\.]*$/', $oid)) {
            throw new SnmpValidationException('Некорректный формат OID: ' . $oid);
        }
    }

    /**
     * Валидирует тип данных для SET операции
     * 
     * @param string $type Тип данных
     * @throws SnmpValidationException Если тип некорректен
     */
    private function validateType(string $type): void
    {
        $validTypes = [
            self::TYPE_INTEGER,
            self::TYPE_UNSIGNED,
            self::TYPE_STRING,
            self::TYPE_OBJID,
            self::TYPE_IPADDRESS,
            self::TYPE_COUNTER32,
            self::TYPE_GAUGE32,
            self::TYPE_TIMETICKS,
            self::TYPE_OPAQUE,
            self::TYPE_COUNTER64,
            self::TYPE_BITS,
        ];

        if (!in_array($type, $validTypes, true)) {
            throw new SnmpValidationException('Недопустимый тип данных SNMP: ' . $type);
        }
    }

    /**
     * Получает имя версии протокола
     * 
     * @param int $version Версия протокола
     * @return string Имя версии
     */
    private function getVersionName(int $version): string
    {
        return match ($version) {
            self::VERSION_1 => 'SNMPv1',
            self::VERSION_2C, self::VERSION_2c => 'SNMPv2c',
            self::VERSION_3 => 'SNMPv3',
            default => 'Unknown',
        };
    }

    /**
     * Закрывает SNMP соединение
     */
    public function close(): void
    {
        if ($this->snmp !== null) {
            try {
                $this->snmp->close();
                $this->log('debug', 'SNMP соединение закрыто');
            } catch (\SNMPException $e) {
                $this->log('warning', 'Ошибка при закрытии SNMP соединения', [
                    'error' => $e->getMessage(),
                ]);
            }
            $this->snmp = null;
        }
    }

    /**
     * Получает информацию о текущем соединении
     * 
     * @return array<string, mixed> Информация о соединении
     */
    public function getConnectionInfo(): array
    {
        return [
            'host' => $this->host,
            'version' => $this->getVersionName($this->version),
            'timeout' => $this->timeout,
            'retries' => $this->retries,
            'connected' => $this->snmp !== null,
        ];
    }

    /**
     * Устанавливает тайм-аут соединения
     * 
     * @param int $timeout Тайм-аут в микросекундах
     * @throws SnmpValidationException Если тайм-аут некорректен
     */
    public function setTimeout(int $timeout): void
    {
        if ($timeout <= 0) {
            throw new SnmpValidationException('Тайм-аут должен быть положительным числом');
        }

        if ($this->snmp !== null) {
            $this->snmp->timeout = $timeout;
            $this->log('debug', 'Тайм-аут SNMP соединения изменен', ['timeout' => $timeout]);
        }
    }

    /**
     * Устанавливает количество повторов
     * 
     * @param int $retries Количество повторов
     * @throws SnmpValidationException Если количество повторов некорректно
     */
    public function setRetries(int $retries): void
    {
        if ($retries < 0) {
            throw new SnmpValidationException('Количество повторов не может быть отрицательным');
        }

        if ($this->snmp !== null) {
            $this->snmp->max_oids = $retries;
            $this->log('debug', 'Количество повторов SNMP изменено', ['retries' => $retries]);
        }
    }

    /**
     * Логирует сообщение через Logger если он доступен
     * 
     * @param string $level Уровень логирования
     * @param string $message Сообщение
     * @param array<string, mixed> $context Контекст
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger === null) {
            return;
        }

        try {
            $this->logger->log($level, $message, $context);
        } catch (\Exception $e) {
            // Подавляем ошибки логирования чтобы не прерывать основную работу
            error_log('Ошибка логирования SNMP: ' . $e->getMessage());
        }
    }

    /**
     * Получает последнюю ошибку SNMP
     * 
     * @return string Описание последней ошибки
     */
    public function getLastError(): string
    {
        if ($this->snmp === null) {
            return 'Соединение не установлено';
        }

        try {
            $errno = $this->snmp->getErrno();
            $error = $this->snmp->getError();
            
            if ($errno === 0) {
                return 'Нет ошибок';
            }
            
            return "Ошибка #{$errno}: {$error}";
            
        } catch (\SNMPException $e) {
            return 'Не удалось получить информацию об ошибке: ' . $e->getMessage();
        }
    }

    /**
     * Получает значение OID по его имени
     * 
     * @param string $oidName Имя OID из конфигурации
     * @param string $suffix Суффикс для добавления к OID (например, номер порта)
     * 
     * @return string|false Значение OID или false при ошибке
     * @throws SnmpException При критических ошибках или если OID загрузчик не настроен
     */
    public function getByName(string $oidName, string $suffix = ''): string|false
    {
        if ($this->oidLoader === null) {
            throw new SnmpException('OID загрузчик не настроен. Передайте SnmpOid в конструктор класса Snmp');
        }

        $oid = $this->oidLoader->getOid($oidName, $this->deviceType, $suffix);
        
        $this->log('debug', 'SNMP GET запрос по имени OID', [
            'oid_name' => $oidName,
            'device_type' => $this->deviceType,
            'suffix' => $suffix,
            'resolved_oid' => $oid,
        ]);
        
        return $this->get($oid);
    }

    /**
     * Получает значения нескольких OID по их именам
     * 
     * @param array<int, string> $oidNames Массив имен OID из конфигурации
     * @param string $suffix Суффикс для добавления ко всем OID
     * 
     * @return array<string, string|false> Ассоциативный массив: имя OID => значение
     * @throws SnmpException При критических ошибках
     */
    public function getMultipleByName(array $oidNames, string $suffix = ''): array
    {
        if ($this->oidLoader === null) {
            throw new SnmpException('OID загрузчик не настроен. Передайте SnmpOid в конструктор класса Snmp');
        }

        $resolvedOids = [];
        foreach ($oidNames as $oidName) {
            $resolvedOids[$oidName] = $this->oidLoader->getOid($oidName, $this->deviceType, $suffix);
        }

        $this->log('debug', 'SNMP GET множественный запрос по именам OID', [
            'oid_names' => $oidNames,
            'device_type' => $this->deviceType,
            'suffix' => $suffix,
            'resolved_oids' => $resolvedOids,
        ]);

        $oidValues = $this->getMultiple(array_values($resolvedOids));
        
        // Преобразуем результат обратно к именам OID
        $result = [];
        foreach ($resolvedOids as $name => $oid) {
            $result[$name] = $oidValues[$oid] ?? false;
        }
        
        return $result;
    }

    /**
     * Обходит дерево MIB по имени OID
     * 
     * @param string $oidName Имя корневого OID из конфигурации
     * @param string $suffix Суффикс для добавления к OID
     * @param bool $suffixAsKey Использовать суффикс OID в качестве ключа массива
     * @param int $maxRepetitions Максимальное количество повторений (для SNMPv2c/v3)
     * @param int $nonRepeaters Количество неповторяющихся переменных (для SNMPv2c/v3)
     * 
     * @return array<string, mixed>|false Массив OID => значение или false при ошибке
     * @throws SnmpException При критических ошибках
     */
    public function walkByName(
        string $oidName,
        string $suffix = '',
        bool $suffixAsKey = false,
        int $maxRepetitions = 20,
        int $nonRepeaters = 0
    ): array|false {
        if ($this->oidLoader === null) {
            throw new SnmpException('OID загрузчик не настроен. Передайте SnmpOid в конструктор класса Snmp');
        }

        $oid = $this->oidLoader->getOid($oidName, $this->deviceType, $suffix);
        
        $this->log('debug', 'SNMP WALK запрос по имени OID', [
            'oid_name' => $oidName,
            'device_type' => $this->deviceType,
            'suffix' => $suffix,
            'resolved_oid' => $oid,
        ]);
        
        return $this->walk($oid, $suffixAsKey, $maxRepetitions, $nonRepeaters);
    }

    /**
     * Устанавливает значение OID по его имени
     * 
     * @param string $oidName Имя OID из конфигурации
     * @param string|int $value Значение для установки
     * @param string $suffix Суффикс для добавления к OID (например, номер порта)
     * @param string|null $type Тип данных (если null - берется из конфигурации OID)
     * 
     * @return bool Успешность операции
     * @throws SnmpException При критических ошибках
     */
    public function setByName(
        string $oidName,
        string|int $value,
        string $suffix = '',
        ?string $type = null
    ): bool {
        if ($this->oidLoader === null) {
            throw new SnmpException('OID загрузчик не настроен. Передайте SnmpOid в конструктор класса Snmp');
        }

        $oid = $this->oidLoader->getOid($oidName, $this->deviceType, $suffix);
        
        // Если тип не указан явно, пытаемся получить из конфигурации
        if ($type === null) {
            $valueType = $this->oidLoader->getValueType($oidName, $this->deviceType);
            if ($valueType === null) {
                throw new SnmpValidationException(
                    "Тип значения не указан для OID '{$oidName}' и не найден в конфигурации"
                );
            }
            $type = $valueType;
        }
        
        $this->log('info', 'SNMP SET запрос по имени OID', [
            'oid_name' => $oidName,
            'device_type' => $this->deviceType,
            'suffix' => $suffix,
            'resolved_oid' => $oid,
            'type' => $type,
            'value' => $value,
        ]);
        
        return $this->set($oid, $type, $value);
    }

    /**
     * Устанавливает тип устройства для резолва OID
     * 
     * @param string|null $deviceType Тип устройства (например, "D-Link DES-3526")
     */
    public function setDeviceType(?string $deviceType): void
    {
        $this->deviceType = $deviceType;
        
        $this->log('debug', 'Тип устройства SNMP изменен', [
            'device_type' => $deviceType,
        ]);
    }

    /**
     * Получает текущий тип устройства
     * 
     * @return string|null Тип устройства
     */
    public function getDeviceType(): ?string
    {
        return $this->deviceType;
    }

    /**
     * Получает объект SnmpOid если он установлен
     * 
     * @return SnmpOid|null Объект SnmpOid или null
     */
    public function getOidLoader(): ?SnmpOid
    {
        return $this->oidLoader;
    }
}

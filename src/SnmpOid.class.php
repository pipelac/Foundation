<?php

declare(strict_types=1);

namespace App\Component;

use App\Component\Exception\Snmp\SnmpException;
use App\Component\Exception\SnmpValidationException;

/**
 * Класс для загрузки и управления SNMP OID конфигурациями
 * 
 * Возможности:
 * - Загрузка OID из JSON конфигурационных файлов
 * - Поддержка общих (common) и специфичных для устройств OID
 * - Автоматическое наследование: специфичные OID переопределяют общие
 * - Получение OID по имени с учетом типа устройства
 * - Кеширование загруженных конфигураций
 * - Строгая валидация структуры данных
 * - Поддержка метаданных OID (описание, тип операции, тип значения)
 * 
 * Системные требования:
 * - PHP 8.1 или выше
 * - JSON расширение
 */
class SnmpOid
{
    /**
     * Загруженная конфигурация OID
     * @var array<string, array<string, array<string, mixed>>>
     */
    private array $config = [];

    /**
     * Кеш объединенных OID для каждого типа устройства
     * @var array<string, array<string, array<string, mixed>>>
     */
    private array $mergedCache = [];

    /**
     * Путь к файлу конфигурации
     */
    private readonly string $configPath;

    /**
     * Опциональный логгер для записи операций и ошибок
     */
    private readonly ?Logger $logger;

    /**
     * Конструктор класса
     * 
     * @param string $configPath Путь к JSON файлу с конфигурацией OID
     * @param Logger|null $logger Логгер для записи операций и ошибок
     * 
     * @throws SnmpException Если не удалось загрузить конфигурацию
     * @throws SnmpValidationException Если конфигурация некорректна
     */
    public function __construct(string $configPath, ?Logger $logger = null)
    {
        $this->logger = $logger;
        $this->configPath = $configPath;
        
        try {
            $this->loadConfig();
            
            $this->log('info', 'SNMP OID конфигурация успешно загружена', [
                'config_path' => $configPath,
                'device_types' => array_keys($this->config),
            ]);
            
        } catch (\Exception $e) {
            $this->log('error', 'Ошибка при загрузке SNMP OID конфигурации', [
                'config_path' => $configPath,
                'error' => $e->getMessage(),
            ]);
            throw new SnmpException(
                'Не удалось загрузить SNMP OID конфигурацию: ' . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Загружает конфигурацию OID из JSON файла
     * 
     * @throws SnmpException Если не удалось загрузить или распарсить файл
     * @throws SnmpValidationException Если структура конфигурации некорректна
     */
    private function loadConfig(): void
    {
        if (!file_exists($this->configPath)) {
            throw new SnmpException('Файл конфигурации OID не найден: ' . $this->configPath);
        }

        if (!is_readable($this->configPath)) {
            throw new SnmpException('Файл конфигурации OID недоступен для чтения: ' . $this->configPath);
        }

        $jsonContent = file_get_contents($this->configPath);
        if ($jsonContent === false) {
            throw new SnmpException('Не удалось прочитать файл конфигурации OID: ' . $this->configPath);
        }

        try {
            $config = json_decode($jsonContent, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new SnmpException(
                'Ошибка парсинга JSON конфигурации OID: ' . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }

        if (!is_array($config)) {
            throw new SnmpValidationException('Конфигурация OID должна быть объектом JSON');
        }

        // Фильтруем служебные поля (начинающиеся с _)
        $this->config = array_filter(
            $config,
            fn($key) => !str_starts_with((string)$key, '_'),
            ARRAY_FILTER_USE_KEY
        );

        if (empty($this->config)) {
            throw new SnmpValidationException('Конфигурация OID пуста или содержит только служебные поля');
        }

        $this->validateConfig();
    }

    /**
     * Валидирует структуру загруженной конфигурации
     * 
     * @throws SnmpValidationException Если структура конфигурации некорректна
     */
    private function validateConfig(): void
    {
        foreach ($this->config as $deviceType => $oids) {
            if (!is_array($oids)) {
                throw new SnmpValidationException(
                    "Конфигурация для устройства '{$deviceType}' должна быть объектом"
                );
            }

            foreach ($oids as $oidName => $oidData) {
                if (!is_array($oidData)) {
                    throw new SnmpValidationException(
                        "Данные OID '{$oidName}' для устройства '{$deviceType}' должны быть объектом"
                    );
                }

                if (!isset($oidData['oid']) || trim((string)$oidData['oid']) === '') {
                    throw new SnmpValidationException(
                        "OID '{$oidName}' для устройства '{$deviceType}' не содержит обязательное поле 'oid'"
                    );
                }

                // Валидация типа операции если указан
                if (isset($oidData['type'])) {
                    $validTypes = ['get', 'set', 'walk', 'getnext'];
                    $type = strtolower((string)$oidData['type']);
                    if (!in_array($type, $validTypes, true)) {
                        throw new SnmpValidationException(
                            "Недопустимый тип операции '{$type}' для OID '{$oidName}' устройства '{$deviceType}'. " .
                            "Допустимые значения: " . implode(', ', $validTypes)
                        );
                    }
                }
            }
        }
    }

    /**
     * Получает OID по имени для указанного типа устройства
     * 
     * @param string $oidName Имя OID
     * @param string|null $deviceType Тип устройства (если null - используется только common)
     * @param string $suffix Суффикс для добавления к OID (например, номер порта)
     * 
     * @return string OID строка
     * @throws SnmpValidationException Если OID не найден
     */
    public function getOid(string $oidName, ?string $deviceType = null, string $suffix = ''): string
    {
        $oidData = $this->getOidData($oidName, $deviceType);
        $oid = (string)$oidData['oid'];
        
        // Добавляем суффикс если указан
        if ($suffix !== '') {
            $oid = rtrim($oid, '.') . '.' . ltrim($suffix, '.');
        }
        
        return $oid;
    }

    /**
     * Получает полные данные OID по имени для указанного типа устройства
     * 
     * @param string $oidName Имя OID
     * @param string|null $deviceType Тип устройства (если null - используется только common)
     * 
     * @return array<string, mixed> Данные OID (oid, description, type, value_type, value и т.д.)
     * @throws SnmpValidationException Если OID не найден
     */
    public function getOidData(string $oidName, ?string $deviceType = null): array
    {
        // Получаем объединенные OID для типа устройства
        $mergedOids = $this->getMergedOids($deviceType);
        
        if (!isset($mergedOids[$oidName])) {
            $deviceInfo = $deviceType !== null ? " для устройства '{$deviceType}'" : " в common секции";
            throw new SnmpValidationException("OID с именем '{$oidName}'{$deviceInfo} не найден");
        }
        
        return $mergedOids[$oidName];
    }

    /**
     * Получает объединенные OID (common + device-specific) с кешированием
     * 
     * @param string|null $deviceType Тип устройства
     * @return array<string, array<string, mixed>> Объединенный массив OID
     */
    private function getMergedOids(?string $deviceType): array
    {
        $cacheKey = $deviceType ?? '_common_only';
        
        // Проверяем кеш
        if (isset($this->mergedCache[$cacheKey])) {
            return $this->mergedCache[$cacheKey];
        }
        
        // Начинаем с общих OID
        $merged = $this->config['common'] ?? [];
        
        // Если указан тип устройства, объединяем со специфичными OID
        if ($deviceType !== null) {
            if (!isset($this->config[$deviceType])) {
                // Если тип устройства не найден, логируем предупреждение
                $this->log('warning', 'Тип устройства не найден в конфигурации', [
                    'device_type' => $deviceType,
                    'available_types' => array_keys($this->config),
                ]);
            } else {
                // Объединяем: специфичные OID переопределяют общие
                $merged = array_merge($merged, $this->config[$deviceType]);
            }
        }
        
        // Кешируем результат
        $this->mergedCache[$cacheKey] = $merged;
        
        return $merged;
    }

    /**
     * Проверяет существование OID по имени для указанного типа устройства
     * 
     * @param string $oidName Имя OID
     * @param string|null $deviceType Тип устройства
     * 
     * @return bool True если OID существует, false в противном случае
     */
    public function hasOid(string $oidName, ?string $deviceType = null): bool
    {
        try {
            $this->getOidData($oidName, $deviceType);
            return true;
        } catch (SnmpValidationException) {
            return false;
        }
    }

    /**
     * Получает список всех доступных имен OID для указанного типа устройства
     * 
     * @param string|null $deviceType Тип устройства (если null - только common)
     * 
     * @return array<int, string> Массив имен OID
     */
    public function getOidNames(?string $deviceType = null): array
    {
        $mergedOids = $this->getMergedOids($deviceType);
        return array_keys($mergedOids);
    }

    /**
     * Получает список всех доступных типов устройств
     * 
     * @return array<int, string> Массив типов устройств
     */
    public function getDeviceTypes(): array
    {
        return array_filter(
            array_keys($this->config),
            fn($type) => $type !== 'common'
        );
    }

    /**
     * Получает описание OID
     * 
     * @param string $oidName Имя OID
     * @param string|null $deviceType Тип устройства
     * 
     * @return string Описание OID
     * @throws SnmpValidationException Если OID не найден
     */
    public function getDescription(string $oidName, ?string $deviceType = null): string
    {
        $oidData = $this->getOidData($oidName, $deviceType);
        return (string)($oidData['description'] ?? '');
    }

    /**
     * Получает тип операции для OID (get, set, walk, getnext)
     * 
     * @param string $oidName Имя OID
     * @param string|null $deviceType Тип устройства
     * 
     * @return string Тип операции или 'get' по умолчанию
     * @throws SnmpValidationException Если OID не найден
     */
    public function getOperationType(string $oidName, ?string $deviceType = null): string
    {
        $oidData = $this->getOidData($oidName, $deviceType);
        return strtolower((string)($oidData['type'] ?? 'get'));
    }

    /**
     * Получает тип значения для SET операции (i, s, u, и т.д.)
     * 
     * @param string $oidName Имя OID
     * @param string|null $deviceType Тип устройства
     * 
     * @return string|null Тип значения или null если не указан
     * @throws SnmpValidationException Если OID не найден
     */
    public function getValueType(string $oidName, ?string $deviceType = null): ?string
    {
        $oidData = $this->getOidData($oidName, $deviceType);
        return isset($oidData['value_type']) ? (string)$oidData['value_type'] : null;
    }

    /**
     * Получает значение по умолчанию для SET операции
     * 
     * @param string $oidName Имя OID
     * @param string|null $deviceType Тип устройства
     * 
     * @return string|int|null Значение по умолчанию или null если не указано
     * @throws SnmpValidationException Если OID не найден
     */
    public function getDefaultValue(string $oidName, ?string $deviceType = null): string|int|null
    {
        $oidData = $this->getOidData($oidName, $deviceType);
        return $oidData['value'] ?? null;
    }

    /**
     * Перезагружает конфигурацию из файла
     * 
     * @throws SnmpException Если не удалось перезагрузить конфигурацию
     */
    public function reload(): void
    {
        $this->config = [];
        $this->mergedCache = [];
        
        $this->loadConfig();
        
        $this->log('info', 'SNMP OID конфигурация перезагружена', [
            'config_path' => $this->configPath,
        ]);
    }

    /**
     * Получает путь к файлу конфигурации
     * 
     * @return string Путь к файлу конфигурации
     */
    public function getConfigPath(): string
    {
        return $this->configPath;
    }

    /**
     * Получает статистику по загруженной конфигурации
     * 
     * @return array<string, mixed> Статистика
     */
    public function getStats(): array
    {
        $stats = [
            'config_path' => $this->configPath,
            'device_types_count' => count($this->config),
            'device_types' => [],
            'total_oids' => 0,
        ];
        
        foreach ($this->config as $deviceType => $oids) {
            $oidCount = count($oids);
            $stats['device_types'][$deviceType] = $oidCount;
            $stats['total_oids'] += $oidCount;
        }
        
        return $stats;
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
            error_log('Ошибка логирования SNMP OID: ' . $e->getMessage());
        }
    }
}

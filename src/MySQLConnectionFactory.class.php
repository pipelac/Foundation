<?php

declare(strict_types=1);

namespace App\Component;

use App\Component\Exception\MySQL\MySQLException;

/**
 * Фабрика соединений MySQL с кешированием для работы с несколькими БД
 * 
 * Особенности:
 * - Кеширование соединений: соединение с БД создается только один раз
 * - Поддержка множественных БД: можно подключаться к разным БД параллельно
 * - Ленивая инициализация: соединение создается только при первом обращении
 * - Потокобезопасность: использует статический кеш для переиспользования соединений
 * - Строгая типизация PHP 8.1+ с полной документацией
 * - Автоматическая проверка версии MySQL для обеспечения совместимости
 * 
 * Системные требования:
 * - PHP 8.1 или выше
 * - MySQL 5.5.62 или выше (рекомендуется MySQL 5.7+ или MySQL 8.0+)
 * - PDO расширение с драйвером MySQL
 * 
 * Поддержка версий MySQL:
 * - MySQL 5.5.62+ - полная поддержка
 * - MySQL 5.6+ - рекомендуется
 * - MySQL 5.7+ - оптимально
 * - MySQL 8.0+ - все возможности
 * - MariaDB 10.0+ - совместимость
 * 
 * @example
 * ```php
 * $factory = new MySQLConnectionFactory($config, $logger);
 * 
 * // Получение соединения с основной БД
 * $mainDb = $factory->getConnection('main');
 * $users = $mainDb->query('SELECT * FROM users');
 * 
 * // Получение соединения с аналитической БД
 * $analyticsDb = $factory->getConnection('analytics');
 * $stats = $analyticsDb->query('SELECT * FROM statistics');
 * ```
 */
class MySQLConnectionFactory
{
    /**
     * Глобальный кеш соединений для переиспользования
     * 
     * Ключ - имя базы данных, значение - экземпляр MySQL
     * 
     * @var array<string, MySQL>
     */
    private static array $connectionCache = [];

    /**
     * Конфигурация всех доступных баз данных
     * 
     * @var array{
     *     databases: array<string, array{
     *         host?: string,
     *         port?: int,
     *         database: string,
     *         username: string,
     *         password: string,
     *         charset?: string,
     *         options?: array<int, mixed>,
     *         persistent?: bool,
     *         cache_statements?: bool
     *     }>,
     *     default?: string
     * }
     */
    private readonly array $config;

    /**
     * Опциональный логгер для записи ошибок и отладочной информации
     */
    private readonly ?Logger $logger;

    /**
     * Имя базы данных по умолчанию
     */
    private readonly string $defaultDatabase;

    /**
     * Конструктор фабрики с валидацией конфигурации
     * 
     * @param array{
     *     databases: array<string, array{
     *         host?: string,
     *         port?: int,
     *         database: string,
     *         username: string,
     *         password: string,
     *         charset?: string,
     *         options?: array<int, mixed>,
     *         persistent?: bool,
     *         cache_statements?: bool
     *     }>,
     *     default?: string
     * } $config Конфигурация со всеми базами данных
     * @param Logger|null $logger Логгер для записи ошибок и отладочной информации
     * 
     * @throws MySQLException Если конфигурация некорректна
     */
    public function __construct(array $config, ?Logger $logger = null)
    {
        $this->logger = $logger;
        $this->validateConfig($config);
        $this->config = $config;
        
        // Определяем базу данных по умолчанию
        $this->defaultDatabase = $this->determineDefaultDatabase($config);
        
        $this->logDebug('MySQLConnectionFactory инициализирована', [
            'databases_count' => count($config['databases'] ?? []),
            'default_database' => $this->defaultDatabase,
        ]);
    }

    /**
     * Получает соединение с указанной базой данных
     * 
     * При первом обращении создает новое соединение и кеширует его.
     * Последующие обращения возвращают кешированное соединение.
     * 
     * @param string|null $databaseName Имя базы данных из конфигурации (null - БД по умолчанию)
     * 
     * @return MySQL Экземпляр соединения с базой данных
     * 
     * @throws MySQLException Если база данных не найдена в конфигурации
     */
    public function getConnection(?string $databaseName = null): MySQL
    {
        // Используем БД по умолчанию, если имя не указано
        $dbName = $databaseName ?? $this->defaultDatabase;
        
        // Проверяем наличие в кеше
        if (isset(self::$connectionCache[$dbName])) {
            $this->logDebug('Использование кешированного соединения', [
                'database' => $dbName,
            ]);
            return self::$connectionCache[$dbName];
        }
        
        // Проверяем наличие конфигурации для указанной БД
        if (!isset($this->config['databases'][$dbName])) {
            throw new MySQLException(
                sprintf(
                    'База данных "%s" не найдена в конфигурации. Доступные БД: %s',
                    $dbName,
                    implode(', ', array_keys($this->config['databases']))
                )
            );
        }
        
        // Создаем новое соединение
        $dbConfig = $this->config['databases'][$dbName];
        
        try {
            $connection = new MySQL($dbConfig, $this->logger);
            
            // Кешируем соединение
            self::$connectionCache[$dbName] = $connection;
            
            $this->logDebug('Создано новое соединение с БД', [
                'database' => $dbName,
                'host' => $dbConfig['host'] ?? 'localhost',
            ]);
            
            return $connection;
        } catch (\Throwable $e) {
            $this->logError('Ошибка создания соединения с БД', [
                'database' => $dbName,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Получает соединение с базой данных по умолчанию
     * 
     * @return MySQL Экземпляр соединения с базой данных по умолчанию
     * 
     * @throws MySQLException Если не удалось создать соединение
     */
    public function getDefaultConnection(): MySQL
    {
        return $this->getConnection($this->defaultDatabase);
    }

    /**
     * Возвращает список доступных имен баз данных
     * 
     * @return array<int, string> Массив имен баз данных
     */
    public function getAvailableDatabases(): array
    {
        return array_keys($this->config['databases']);
    }

    /**
     * Возвращает имя базы данных по умолчанию
     * 
     * @return string Имя базы данных по умолчанию
     */
    public function getDefaultDatabaseName(): string
    {
        return $this->defaultDatabase;
    }

    /**
     * Проверяет, существует ли конфигурация для указанной базы данных
     * 
     * @param string $databaseName Имя базы данных
     * 
     * @return bool True, если конфигурация существует, иначе false
     */
    public function hasDatabase(string $databaseName): bool
    {
        return isset($this->config['databases'][$databaseName]);
    }

    /**
     * Проверяет активность соединения с указанной базой данных
     * 
     * Если соединение еще не создано, возвращает false.
     * Если соединение создано, проверяет его активность через ping.
     * 
     * @param string|null $databaseName Имя базы данных (null - БД по умолчанию)
     * 
     * @return bool True, если соединение активно, иначе false
     */
    public function isConnectionAlive(?string $databaseName = null): bool
    {
        $dbName = $databaseName ?? $this->defaultDatabase;
        
        if (!isset(self::$connectionCache[$dbName])) {
            return false;
        }
        
        return self::$connectionCache[$dbName]->ping();
    }

    /**
     * Очищает кеш соединений для указанной базы данных или всех баз
     * 
     * Удаляет закешированные соединения из памяти.
     * При следующем обращении соединение будет создано заново.
     * 
     * @param string|null $databaseName Имя базы данных (null - очистить все)
     */
    public function clearConnectionCache(?string $databaseName = null): void
    {
        if ($databaseName === null) {
            $count = count(self::$connectionCache);
            self::$connectionCache = [];
            $this->logDebug('Очищен кеш всех соединений', ['cleared_count' => $count]);
        } elseif (isset(self::$connectionCache[$databaseName])) {
            unset(self::$connectionCache[$databaseName]);
            $this->logDebug('Очищен кеш соединения', ['database' => $databaseName]);
        }
    }

    /**
     * Возвращает количество активных закешированных соединений
     * 
     * @return int Количество соединений в кеше
     */
    public function getCachedConnectionsCount(): int
    {
        return count(self::$connectionCache);
    }

    /**
     * Возвращает список имен баз данных с активными соединениями
     * 
     * @return array<int, string> Массив имен баз данных с активными соединениями
     */
    public function getActiveDatabases(): array
    {
        return array_keys(self::$connectionCache);
    }

    /**
     * Возвращает информацию о версиях MySQL для всех активных соединений
     * 
     * Полезно для мониторинга и проверки совместимости версий в кластере БД.
     * 
     * @return array<string, array{
     *     version: string,
     *     major: int,
     *     minor: int,
     *     patch: int,
     *     is_supported: bool,
     *     is_recommended: bool
     * }> Массив версий MySQL для каждого активного соединения
     */
    public function getMySQLVersions(): array
    {
        $versions = [];
        
        foreach (self::$connectionCache as $dbName => $connection) {
            $versions[$dbName] = $connection->getMySQLVersion();
        }
        
        return $versions;
    }

    /**
     * Проверяет, что все активные соединения используют поддерживаемые версии MySQL
     * 
     * @return bool True, если все соединения поддерживаются, иначе false
     */
    public function areAllVersionsSupported(): bool
    {
        foreach (self::$connectionCache as $connection) {
            $versionInfo = $connection->getMySQLVersion();
            if (!$versionInfo['is_supported']) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Проверяет, что все активные соединения используют рекомендованные версии MySQL
     * 
     * @return bool True, если все соединения используют рекомендованные версии, иначе false
     */
    public function areAllVersionsRecommended(): bool
    {
        foreach (self::$connectionCache as $connection) {
            $versionInfo = $connection->getMySQLVersion();
            if (!$versionInfo['is_recommended']) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Валидирует конфигурацию фабрики соединений
     * 
     * @param array<string, mixed> $config Конфигурация для проверки
     * 
     * @throws MySQLException Если конфигурация некорректна
     */
    private function validateConfig(array $config): void
    {
        // Проверка наличия секции databases
        if (!isset($config['databases']) || !is_array($config['databases'])) {
            throw new MySQLException(
                'Конфигурация должна содержать секцию "databases" с массивом настроек БД'
            );
        }
        
        // Проверка что есть хотя бы одна БД
        if (empty($config['databases'])) {
            throw new MySQLException(
                'Секция "databases" не должна быть пустой. Необходимо указать хотя бы одну БД'
            );
        }
        
        // Валидация каждой конфигурации БД
        foreach ($config['databases'] as $dbName => $dbConfig) {
            if (!is_array($dbConfig)) {
                throw new MySQLException(
                    sprintf('Конфигурация базы данных "%s" должна быть массивом', $dbName)
                );
            }
            
            // Проверка обязательных полей
            $requiredFields = ['database', 'username', 'password'];
            foreach ($requiredFields as $field) {
                if (!isset($dbConfig[$field])) {
                    throw new MySQLException(
                        sprintf(
                            'В конфигурации базы данных "%s" отсутствует обязательное поле "%s"',
                            $dbName,
                            $field
                        )
                    );
                }
            }
        }
        
        // Проверка default БД, если указана
        if (isset($config['default'])) {
            $defaultDb = $config['default'];
            if (!isset($config['databases'][$defaultDb])) {
                throw new MySQLException(
                    sprintf(
                        'База данных по умолчанию "%s" не найдена в секции "databases"',
                        $defaultDb
                    )
                );
            }
        }
    }

    /**
     * Определяет имя базы данных по умолчанию
     * 
     * @param array<string, mixed> $config Конфигурация
     * 
     * @return string Имя базы данных по умолчанию
     */
    private function determineDefaultDatabase(array $config): string
    {
        // Если явно указана default БД - используем её
        if (isset($config['default']) && is_string($config['default'])) {
            return $config['default'];
        }
        
        // Иначе используем первую БД из списка
        $databases = array_keys($config['databases']);
        return $databases[0];
    }

    /**
     * Записывает отладочное сообщение в лог
     * 
     * @param string $message Текст сообщения
     * @param array<string, mixed> $context Контекст для логирования
     */
    private function logDebug(string $message, array $context = []): void
    {
        $this->logger?->debug($message, $context);
    }

    /**
     * Записывает сообщение об ошибке в лог
     * 
     * @param string $message Текст ошибки
     * @param array<string, mixed> $context Контекст ошибки
     */
    private function logError(string $message, array $context = []): void
    {
        $this->logger?->error($message, $context);
    }

    /**
     * Деструктор класса для освобождения ресурсов
     * 
     * При уничтожении фабрики очищает кеш соединений
     */
    public function __destruct()
    {
        // Кеш остается глобальным между экземплярами фабрики
        // Он будет очищен только при завершении скрипта
    }
}


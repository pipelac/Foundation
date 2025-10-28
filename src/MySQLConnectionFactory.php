<?php

declare(strict_types=1);

namespace App\Component;

use App\Component\Exception\MySQLConnectionException;
use App\Component\Exception\MySQLException;

/**
 * Фабрика для создания и кеширования MySQL соединений
 * 
 * Обеспечивает управление множественными подключениями к различным базам данных
 * с автоматическим кешированием соединений для повышения производительности.
 * 
 * Особенности:
 * - Паттерн Singleton для глобального доступа к фабрике
 * - Кеширование соединений по именам (connection pooling)
 * - Ленивая инициализация - соединение создается только при первом обращении
 * - Поддержка множественных БД одновременно
 * - Автоматическое управление жизненным циклом соединений
 * - Строгая типизация и обработка исключений
 * 
 * @example
 * ```php
 * // Инициализация фабрики с конфигурацией
 * $factory = MySQLConnectionFactory::initialize($config, $logger);
 * 
 * // Получение соединения по имени
 * $mainDb = $factory->getConnection('default');
 * $analyticsDb = $factory->getConnection('analytics');
 * 
 * // Получение соединения по умолчанию
 * $defaultDb = $factory->getDefaultConnection();
 * ```
 */
class MySQLConnectionFactory
{
    /**
     * Экземпляр фабрики (Singleton)
     */
    private static ?self $instance = null;

    /**
     * Кеш активных соединений MySQL
     * 
     * @var array<string, MySQL>
     */
    private array $connections = [];

    /**
     * Конфигурация всех подключений
     * 
     * @var array{
     *     connections: array<string, array{
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
     *     default_connection?: string
     * }
     */
    private readonly array $config;

    /**
     * Логгер для записи операций фабрики
     */
    private readonly ?Logger $logger;

    /**
     * Имя подключения по умолчанию
     */
    private readonly ?string $defaultConnectionName;

    /**
     * Приватный конструктор для реализации Singleton
     * 
     * @param array{
     *     connections: array<string, array{
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
     *     default_connection?: string
     * } $config Конфигурация всех подключений
     * @param Logger|null $logger Логгер для записи операций
     * 
     * @throws MySQLException Если конфигурация некорректна
     */
    private function __construct(array $config, ?Logger $logger = null)
    {
        $this->logger = $logger;
        $this->validateFactoryConfig($config);
        $this->config = $config;
        $this->defaultConnectionName = $config['default_connection'] ?? null;

        $this->logDebug('MySQLConnectionFactory инициализирована', [
            'available_connections' => array_keys($config['connections']),
            'default_connection' => $this->defaultConnectionName,
        ]);
    }

    /**
     * Инициализирует фабрику соединений с заданной конфигурацией
     * 
     * Создает новый экземпляр фабрики или перезаписывает существующий.
     * Используйте этот метод при загрузке приложения.
     * 
     * @param array{
     *     connections: array<string, array{
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
     *     default_connection?: string
     * } $config Конфигурация всех подключений
     * @param Logger|null $logger Логгер для записи операций
     * 
     * @return self Экземпляр фабрики
     * 
     * @throws MySQLException Если конфигурация некорректна
     */
    public static function initialize(array $config, ?Logger $logger = null): self
    {
        self::$instance = new self($config, $logger);
        return self::$instance;
    }

    /**
     * Возвращает экземпляр фабрики соединений
     * 
     * @return self Экземпляр фабрики
     * 
     * @throws MySQLException Если фабрика не была инициализирована
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new MySQLException(
                'MySQLConnectionFactory не инициализирована. Вызовите MySQLConnectionFactory::initialize() с конфигурацией.'
            );
        }

        return self::$instance;
    }

    /**
     * Возвращает MySQL соединение по имени с кешированием
     * 
     * При первом обращении создает новое соединение и кеширует его.
     * Последующие обращения возвращают закешированное соединение.
     * 
     * @param string $connectionName Имя подключения из конфигурации
     * 
     * @return MySQL Экземпляр MySQL соединения
     * 
     * @throws MySQLException Если подключение с указанным именем не найдено
     * @throws MySQLConnectionException Если не удалось создать соединение
     */
    public function getConnection(string $connectionName): MySQL
    {
        // Возвращаем закешированное соединение, если оно существует
        if (isset($this->connections[$connectionName])) {
            $this->logDebug('Использовано кешированное соединение', [
                'connection_name' => $connectionName,
            ]);
            return $this->connections[$connectionName];
        }

        // Проверяем наличие конфигурации для данного подключения
        if (!isset($this->config['connections'][$connectionName])) {
            $availableConnections = array_keys($this->config['connections']);
            throw new MySQLException(
                sprintf(
                    'Подключение "%s" не найдено в конфигурации. Доступные подключения: %s',
                    $connectionName,
                    implode(', ', $availableConnections)
                )
            );
        }

        // Создаем новое соединение
        $connectionConfig = $this->config['connections'][$connectionName];
        
        $this->logDebug('Создание нового MySQL соединения', [
            'connection_name' => $connectionName,
            'host' => $connectionConfig['host'] ?? 'localhost',
            'database' => $connectionConfig['database'],
        ]);

        try {
            $connection = new MySQL($connectionConfig, $this->logger);
            $this->connections[$connectionName] = $connection;

            $this->logDebug('MySQL соединение успешно создано и кешировано', [
                'connection_name' => $connectionName,
                'total_cached_connections' => count($this->connections),
            ]);

            return $connection;
        } catch (MySQLConnectionException $e) {
            $this->logError('Ошибка создания MySQL соединения', [
                'connection_name' => $connectionName,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Возвращает соединение по умолчанию
     * 
     * @return MySQL Экземпляр MySQL соединения
     * 
     * @throws MySQLException Если подключение по умолчанию не настроено
     * @throws MySQLConnectionException Если не удалось создать соединение
     */
    public function getDefaultConnection(): MySQL
    {
        if ($this->defaultConnectionName === null) {
            throw new MySQLException(
                'Подключение по умолчанию не настроено в конфигурации. Укажите "default_connection".'
            );
        }

        return $this->getConnection($this->defaultConnectionName);
    }

    /**
     * Проверяет наличие подключения с указанным именем в конфигурации
     * 
     * @param string $connectionName Имя подключения
     * 
     * @return bool True, если подключение доступно
     */
    public function hasConnection(string $connectionName): bool
    {
        return isset($this->config['connections'][$connectionName]);
    }

    /**
     * Проверяет, закешировано ли соединение
     * 
     * @param string $connectionName Имя подключения
     * 
     * @return bool True, если соединение закешировано
     */
    public function isConnectionCached(string $connectionName): bool
    {
        return isset($this->connections[$connectionName]);
    }

    /**
     * Возвращает список всех доступных имен подключений
     * 
     * @return array<int, string> Массив имен подключений
     */
    public function getAvailableConnections(): array
    {
        return array_keys($this->config['connections']);
    }

    /**
     * Возвращает список всех закешированных подключений
     * 
     * @return array<int, string> Массив имен закешированных подключений
     */
    public function getCachedConnections(): array
    {
        return array_keys($this->connections);
    }

    /**
     * Возвращает имя подключения по умолчанию
     * 
     * @return string|null Имя подключения по умолчанию или null
     */
    public function getDefaultConnectionName(): ?string
    {
        return $this->defaultConnectionName;
    }

    /**
     * Очищает кеш всех соединений
     * 
     * Закрывает все активные соединения и очищает кеш.
     * Используйте с осторожностью - все последующие запросы создадут новые соединения.
     */
    public function clearCache(): void
    {
        $count = count($this->connections);
        $this->connections = [];

        $this->logDebug('Кеш MySQL соединений очищен', [
            'cleared_connections' => $count,
        ]);
    }

    /**
     * Очищает конкретное соединение из кеша
     * 
     * @param string $connectionName Имя подключения для удаления
     * 
     * @return bool True, если соединение было удалено, false если не было закешировано
     */
    public function clearConnection(string $connectionName): bool
    {
        if (!isset($this->connections[$connectionName])) {
            return false;
        }

        unset($this->connections[$connectionName]);

        $this->logDebug('MySQL соединение удалено из кеша', [
            'connection_name' => $connectionName,
            'remaining_connections' => count($this->connections),
        ]);

        return true;
    }

    /**
     * Проверяет состояние всех закешированных соединений
     * 
     * @return array<string, bool> Массив с результатами проверки для каждого соединения
     */
    public function pingAll(): array
    {
        $results = [];

        foreach ($this->connections as $name => $connection) {
            $results[$name] = $connection->ping();
        }

        $this->logDebug('Проверка состояния всех соединений', [
            'results' => $results,
        ]);

        return $results;
    }

    /**
     * Валидирует конфигурацию фабрики
     * 
     * @param array<string, mixed> $config Конфигурация для проверки
     * 
     * @throws MySQLException Если конфигурация некорректна
     */
    private function validateFactoryConfig(array $config): void
    {
        if (!isset($config['connections'])) {
            throw new MySQLException(
                'Конфигурация должна содержать секцию "connections" с массивом подключений'
            );
        }

        if (!is_array($config['connections'])) {
            throw new MySQLException(
                'Секция "connections" должна быть массивом'
            );
        }

        if (empty($config['connections'])) {
            throw new MySQLException(
                'Необходимо указать хотя бы одно подключение в секции "connections"'
            );
        }

        // Проверяем, что default_connection существует в списке подключений
        if (isset($config['default_connection'])) {
            $defaultConnection = $config['default_connection'];
            if (!isset($config['connections'][$defaultConnection])) {
                throw new MySQLException(
                    sprintf(
                        'Подключение по умолчанию "%s" не найдено в списке connections',
                        $defaultConnection
                    )
                );
            }
        }
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
     * Деструктор для корректного завершения работы фабрики
     */
    public function __destruct()
    {
        $this->clearCache();
    }

    /**
     * Запрещаем клонирование экземпляра (Singleton)
     */
    private function __clone()
    {
    }

    /**
     * Запрещаем десериализацию экземпляра (Singleton)
     * 
     * @throws MySQLException
     */
    public function __wakeup(): void
    {
        throw new MySQLException('Десериализация MySQLConnectionFactory запрещена');
    }
}

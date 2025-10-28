<?php

declare(strict_types=1);

namespace App\Component;

use App\Component\Exception\MySQLConnectionException;
use App\Component\Exception\MySQLException;
use App\Component\Exception\MySQLTransactionException;
use PDO;
use PDOException;
use PDOStatement;

/**
 * Профессиональный класс для работы с MySQL через PDO
 * 
 * Возможности:
 * - Строгая типизация на уровне PHP 8.1+
 * - Кеширование подготовленных запросов для повышения производительности
 * - Поддержка персистентных соединений
 * - Управление транзакциями с контролем состояния
 * - Batch-операции для массовой вставки данных
 * - Структурированное логирование через Logger
 * - Специализированные исключения для разных типов ошибок
 */
class MySQL
{
    /**
     * Активное PDO соединение с базой данных
     */
    private readonly PDO $connection;

    /**
     * Опциональный логгер для записи ошибок и отладочной информации
     */
    private readonly ?Logger $logger;

    /**
     * Кеш подготовленных запросов для повышения производительности
     * 
     * @var array<string, PDOStatement>
     */
    private array $statementCache = [];

    /**
     * Максимальное количество кешируемых prepared statements
     */
    private const MAX_STATEMENT_CACHE_SIZE = 100;

    /**
     * Флаг включения кеширования prepared statements
     */
    private readonly bool $cacheStatements;

    /**
     * Конструктор класса с валидацией конфигурации и установкой соединения
     * 
     * @param array{
     *     host?: string,
     *     port?: int,
     *     database: string,
     *     username: string,
     *     password: string,
     *     charset?: string,
     *     options?: array<int, mixed>,
     *     persistent?: bool,
     *     cache_statements?: bool
     * } $config Конфигурация подключения к БД
     * @param Logger|null $logger Логгер для записи ошибок и отладочной информации
     * 
     * @throws MySQLConnectionException Если не удалось подключиться к БД
     * @throws MySQLException Если конфигурация некорректна
     */
    public function __construct(array $config, ?Logger $logger = null)
    {
        $this->logger = $logger;
        $this->validateConfig($config);

        $host = (string)($config['host'] ?? 'localhost');
        $port = (int)($config['port'] ?? 3306);
        $database = (string)$config['database'];
        $username = (string)$config['username'];
        $password = (string)$config['password'];
        $charset = (string)($config['charset'] ?? 'utf8mb4');
        $persistent = (bool)($config['persistent'] ?? false);
        $this->cacheStatements = (bool)($config['cache_statements'] ?? true);

        $userOptions = (array)($config['options'] ?? []);

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);

        $defaultOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_PERSISTENT => $persistent,
        ];

        $mergedOptions = array_replace($defaultOptions, $userOptions);

        try {
            $this->connection = new PDO($dsn, $username, $password, $mergedOptions);
            $this->logDebug('Успешное подключение к БД', [
                'host' => $host,
                'database' => $database,
                'persistent' => $persistent,
            ]);
        } catch (PDOException $e) {
            $this->logError('Ошибка подключения к БД', [
                'host' => $host,
                'database' => $database,
                'error' => $e->getMessage(),
            ]);
            throw new MySQLConnectionException(
                sprintf('Не удалось подключиться к базе данных "%s" на хосте "%s": %s', $database, $host, $e->getMessage()),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Валидирует конфигурацию подключения
     * 
     * @param array<string, mixed> $config Конфигурация для проверки
     * 
     * @throws MySQLException Если конфигурация некорректна
     */
    private function validateConfig(array $config): void
    {
        if (!isset($config['database']) || trim((string)$config['database']) === '') {
            throw new MySQLException('Не указано имя базы данных в конфигурации');
        }

        if (!isset($config['username'])) {
            throw new MySQLException('Не указано имя пользователя БД в конфигурации');
        }

        if (!isset($config['password'])) {
            throw new MySQLException('Не указан пароль для подключения к БД в конфигурации');
        }
    }

    /**
     * Возвращает PDO соединение для расширенной работы с БД
     * 
     * Используйте с осторожностью. Предпочтительно использовать
     * методы класса для типобезопасной работы с БД.
     * 
     * @return PDO Экземпляр PDO соединения
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    /**
     * Выполняет SELECT запрос и возвращает все строки результата
     * 
     * @param string $query SQL запрос с плейсхолдерами для параметров
     * @param array<string|int, mixed> $params Параметры для подготовленного запроса
     * 
     * @return array<int, array<string, mixed>> Массив строк результата
     * 
     * @throws MySQLException Если запрос завершился с ошибкой
     */
    public function query(string $query, array $params = []): array
    {
        try {
            $statement = $this->prepareAndExecute($query, $params);
            $result = $statement->fetchAll();

            $this->logDebug('Выполнен SELECT запрос', [
                'query' => $this->sanitizeQueryForLog($query),
                'rows' => count($result),
            ]);

            return $result;
        } catch (PDOException $e) {
            $this->logError('Ошибка выполнения SELECT запроса', [
                'query' => $this->sanitizeQueryForLog($query),
                'error' => $e->getMessage(),
            ]);
            throw new MySQLException(
                sprintf('Ошибка выполнения SELECT запроса: %s', $e->getMessage()),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Выполняет SELECT запрос и возвращает первую строку результата
     * 
     * @param string $query SQL запрос с плейсхолдерами для параметров
     * @param array<string|int, mixed> $params Параметры для подготовленного запроса
     * 
     * @return array<string, mixed>|null Первая строка результата или null, если результат пуст
     * 
     * @throws MySQLException Если запрос завершился с ошибкой
     */
    public function queryOne(string $query, array $params = []): ?array
    {
        try {
            $statement = $this->prepareAndExecute($query, $params);
            $result = $statement->fetch();

            $this->logDebug('Выполнен SELECT запрос (одна строка)', [
                'query' => $this->sanitizeQueryForLog($query),
                'found' => $result !== false,
            ]);

            return $result === false ? null : $result;
        } catch (PDOException $e) {
            $this->logError('Ошибка выполнения SELECT запроса (одна строка)', [
                'query' => $this->sanitizeQueryForLog($query),
                'error' => $e->getMessage(),
            ]);
            throw new MySQLException(
                sprintf('Ошибка выполнения SELECT запроса: %s', $e->getMessage()),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Выполняет SELECT запрос и возвращает одно скалярное значение
     * 
     * Удобно для запросов типа SELECT COUNT(*), SELECT MAX(id), и т.д.
     * 
     * @param string $query SQL запрос с плейсхолдерами для параметров
     * @param array<string|int, mixed> $params Параметры для подготовленного запроса
     * 
     * @return mixed Скалярное значение первого столбца первой строки или null
     * 
     * @throws MySQLException Если запрос завершился с ошибкой
     */
    public function queryScalar(string $query, array $params = []): mixed
    {
        try {
            $statement = $this->prepareAndExecute($query, $params);
            $result = $statement->fetchColumn();

            $this->logDebug('Выполнен SELECT запрос (скалярное значение)', [
                'query' => $this->sanitizeQueryForLog($query),
                'found' => $result !== false,
            ]);

            return $result === false ? null : $result;
        } catch (PDOException $e) {
            $this->logError('Ошибка выполнения SELECT запроса (скалярное значение)', [
                'query' => $this->sanitizeQueryForLog($query),
                'error' => $e->getMessage(),
            ]);
            throw new MySQLException(
                sprintf('Ошибка выполнения SELECT запроса: %s', $e->getMessage()),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Выполняет INSERT запрос и возвращает ID вставленной записи
     * 
     * @param string $query SQL запрос с плейсхолдерами для параметров
     * @param array<string|int, mixed> $params Параметры для подготовленного запроса
     * 
     * @return int ID вставленной записи (0 для таблиц без AUTO_INCREMENT)
     * 
     * @throws MySQLException Если запрос завершился с ошибкой
     */
    public function insert(string $query, array $params = []): int
    {
        try {
            $this->prepareAndExecute($query, $params);
            $lastId = (int)$this->connection->lastInsertId();

            $this->logDebug('Выполнен INSERT запрос', [
                'query' => $this->sanitizeQueryForLog($query),
                'last_insert_id' => $lastId,
            ]);

            return $lastId;
        } catch (PDOException $e) {
            $this->logError('Ошибка выполнения INSERT запроса', [
                'query' => $this->sanitizeQueryForLog($query),
                'error' => $e->getMessage(),
            ]);
            throw new MySQLException(
                sprintf('Ошибка выполнения INSERT запроса: %s', $e->getMessage()),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Выполняет массовую вставку данных (bulk insert) за одну транзакцию
     * 
     * Значительно эффективнее, чем множественные вызовы insert().
     * Все вставки выполняются в рамках одной транзакции.
     * 
     * @param string $table Имя таблицы для вставки
     * @param array<int, array<string, mixed>> $rows Массив строк для вставки
     * 
     * @return int Количество вставленных строк
     * 
     * @throws MySQLException Если операция завершилась с ошибкой
     * @throws MySQLTransactionException Если возникла ошибка транзакции
     */
    public function insertBatch(string $table, array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $columns = array_keys($rows[0]);
        $columnsStr = implode(', ', array_map(fn($col) => "`{$col}`", $columns));
        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';

        try {
            $this->beginTransaction();

            $query = sprintf(
                'INSERT INTO `%s` (%s) VALUES %s',
                $table,
                $columnsStr,
                $placeholders
            );

            $statement = $this->connection->prepare($query);
            $insertedCount = 0;

            foreach ($rows as $row) {
                $values = array_values($row);
                $statement->execute($values);
                $insertedCount++;
            }

            $this->commit();

            $this->logDebug('Выполнена массовая вставка', [
                'table' => $table,
                'rows' => $insertedCount,
            ]);

            return $insertedCount;
        } catch (PDOException $e) {
            $this->rollback();
            $this->logError('Ошибка массовой вставки', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
            throw new MySQLException(
                sprintf('Ошибка массовой вставки в таблицу "%s": %s', $table, $e->getMessage()),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Выполняет UPDATE запрос и возвращает количество затронутых строк
     * 
     * @param string $query SQL запрос с плейсхолдерами для параметров
     * @param array<string|int, mixed> $params Параметры для подготовленного запроса
     * 
     * @return int Количество обновленных строк
     * 
     * @throws MySQLException Если запрос завершился с ошибкой
     */
    public function update(string $query, array $params = []): int
    {
        try {
            $statement = $this->prepareAndExecute($query, $params);
            $rowCount = $statement->rowCount();

            $this->logDebug('Выполнен UPDATE запрос', [
                'query' => $this->sanitizeQueryForLog($query),
                'affected_rows' => $rowCount,
            ]);

            return $rowCount;
        } catch (PDOException $e) {
            $this->logError('Ошибка выполнения UPDATE запроса', [
                'query' => $this->sanitizeQueryForLog($query),
                'error' => $e->getMessage(),
            ]);
            throw new MySQLException(
                sprintf('Ошибка выполнения UPDATE запроса: %s', $e->getMessage()),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Выполняет DELETE запрос и возвращает количество удаленных строк
     * 
     * @param string $query SQL запрос с плейсхолдерами для параметров
     * @param array<string|int, mixed> $params Параметры для подготовленного запроса
     * 
     * @return int Количество удаленных строк
     * 
     * @throws MySQLException Если запрос завершился с ошибкой
     */
    public function delete(string $query, array $params = []): int
    {
        try {
            $statement = $this->prepareAndExecute($query, $params);
            $rowCount = $statement->rowCount();

            $this->logDebug('Выполнен DELETE запрос', [
                'query' => $this->sanitizeQueryForLog($query),
                'affected_rows' => $rowCount,
            ]);

            return $rowCount;
        } catch (PDOException $e) {
            $this->logError('Ошибка выполнения DELETE запроса', [
                'query' => $this->sanitizeQueryForLog($query),
                'error' => $e->getMessage(),
            ]);
            throw new MySQLException(
                sprintf('Ошибка выполнения DELETE запроса: %s', $e->getMessage()),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Выполняет произвольный SQL запрос (DDL, DML команды)
     * 
     * Используется для выполнения команд типа CREATE, ALTER, DROP, TRUNCATE и т.д.
     * 
     * @param string $query SQL команда для выполнения
     * 
     * @return int Количество затронутых строк (0 для большинства DDL команд)
     * 
     * @throws MySQLException Если команда завершилась с ошибкой
     */
    public function execute(string $query): int
    {
        try {
            $affectedRows = $this->connection->exec($query);

            $this->logDebug('Выполнена SQL команда', [
                'query' => $this->sanitizeQueryForLog($query),
                'affected_rows' => $affectedRows,
            ]);

            return $affectedRows === false ? 0 : $affectedRows;
        } catch (PDOException $e) {
            $this->logError('Ошибка выполнения SQL команды', [
                'query' => $this->sanitizeQueryForLog($query),
                'error' => $e->getMessage(),
            ]);
            throw new MySQLException(
                sprintf('Ошибка выполнения SQL команды: %s', $e->getMessage()),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Начинает новую транзакцию
     * 
     * @throws MySQLTransactionException Если транзакция уже активна или не удалось начать
     */
    public function beginTransaction(): void
    {
        try {
            if ($this->connection->inTransaction()) {
                throw new MySQLTransactionException('Транзакция уже активна. Вложенные транзакции не поддерживаются.');
            }

            $this->connection->beginTransaction();
            $this->logDebug('Транзакция начата');
        } catch (PDOException $e) {
            $this->logError('Ошибка начала транзакции', ['error' => $e->getMessage()]);
            throw new MySQLTransactionException(
                sprintf('Не удалось начать транзакцию: %s', $e->getMessage()),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Подтверждает активную транзакцию
     * 
     * @throws MySQLTransactionException Если транзакция не активна или не удалось подтвердить
     */
    public function commit(): void
    {
        try {
            if (!$this->connection->inTransaction()) {
                throw new MySQLTransactionException('Нет активной транзакции для подтверждения');
            }

            $this->connection->commit();
            $this->logDebug('Транзакция подтверждена');
        } catch (PDOException $e) {
            $this->logError('Ошибка подтверждения транзакции', ['error' => $e->getMessage()]);
            throw new MySQLTransactionException(
                sprintf('Не удалось подтвердить транзакцию: %s', $e->getMessage()),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Откатывает активную транзакцию
     * 
     * @throws MySQLTransactionException Если транзакция не активна или не удалось откатить
     */
    public function rollback(): void
    {
        try {
            if (!$this->connection->inTransaction()) {
                throw new MySQLTransactionException('Нет активной транзакции для отката');
            }

            $this->connection->rollBack();
            $this->logDebug('Транзакция откачена');
        } catch (PDOException $e) {
            $this->logError('Ошибка отката транзакции', ['error' => $e->getMessage()]);
            throw new MySQLTransactionException(
                sprintf('Не удалось откатить транзакцию: %s', $e->getMessage()),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Проверяет, активна ли в данный момент транзакция
     * 
     * @return bool True, если транзакция активна, иначе false
     */
    public function inTransaction(): bool
    {
        return $this->connection->inTransaction();
    }

    /**
     * Выполняет callback внутри транзакции с автоматическим commit/rollback
     * 
     * Если callback выполнен успешно, транзакция автоматически подтверждается.
     * При возникновении исключения транзакция откатывается, и исключение пробрасывается дальше.
     * 
     * @template T
     * @param callable(): T $callback Функция для выполнения внутри транзакции
     * 
     * @return T Результат выполнения callback
     * 
     * @throws MySQLTransactionException Если возникла ошибка управления транзакцией
     * @throws MySQLException Если возникла ошибка выполнения запросов
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback();
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            $this->logError('Ошибка в транзакции, выполнен rollback', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Проверяет состояние подключения к базе данных
     * 
     * Выполняет простой запрос для проверки доступности БД
     * 
     * @return bool True, если подключение активно, иначе false
     */
    public function ping(): bool
    {
        try {
            $this->connection->query('SELECT 1');
            return true;
        } catch (PDOException) {
            return false;
        }
    }

    /**
     * Возвращает информацию о текущем подключении
     * 
     * @return array{
     *     server_version: string,
     *     client_version: string,
     *     connection_status: string,
     *     server_info: string,
     *     in_transaction: bool
     * } Информация о подключении
     */
    public function getConnectionInfo(): array
    {
        return [
            'server_version' => $this->connection->getAttribute(PDO::ATTR_SERVER_VERSION),
            'client_version' => $this->connection->getAttribute(PDO::ATTR_CLIENT_VERSION),
            'connection_status' => $this->connection->getAttribute(PDO::ATTR_CONNECTION_STATUS),
            'server_info' => $this->connection->getAttribute(PDO::ATTR_SERVER_INFO),
            'in_transaction' => $this->connection->inTransaction(),
        ];
    }

    /**
     * Очищает кеш подготовленных запросов
     * 
     * Полезно для освобождения памяти после выполнения множества различных запросов
     */
    public function clearStatementCache(): void
    {
        $count = count($this->statementCache);
        $this->statementCache = [];
        $this->logDebug('Кеш prepared statements очищен', ['cleared_count' => $count]);
    }

    /**
     * Подготавливает и выполняет SQL запрос с кешированием prepared statements
     * 
     * @param string $query SQL запрос с плейсхолдерами
     * @param array<string|int, mixed> $params Параметры для привязки
     * 
     * @return PDOStatement Выполненный statement
     * 
     * @throws PDOException Если запрос не удалось подготовить или выполнить
     */
    private function prepareAndExecute(string $query, array $params = []): PDOStatement
    {
        $statement = $this->getOrPrepareStatement($query);
        $statement->execute($params);

        return $statement;
    }

    /**
     * Получает подготовленный statement из кеша или создает новый
     * 
     * @param string $query SQL запрос
     * 
     * @return PDOStatement Подготовленный statement
     * 
     * @throws PDOException Если не удалось подготовить statement
     */
    private function getOrPrepareStatement(string $query): PDOStatement
    {
        if (!$this->cacheStatements) {
            return $this->connection->prepare($query);
        }

        if (isset($this->statementCache[$query])) {
            return $this->statementCache[$query];
        }

        if (count($this->statementCache) >= self::MAX_STATEMENT_CACHE_SIZE) {
            $this->statementCache = array_slice($this->statementCache, 1, null, true);
        }

        $statement = $this->connection->prepare($query);
        $this->statementCache[$query] = $statement;

        return $statement;
    }

    /**
     * Санитизирует SQL запрос для безопасного логирования
     * 
     * Обрезает длинные запросы и заменяет множественные пробелы
     * 
     * @param string $query SQL запрос
     * 
     * @return string Санитизированный запрос
     */
    private function sanitizeQueryForLog(string $query): string
    {
        $sanitized = preg_replace('/\s+/', ' ', trim($query));
        
        if ($sanitized === null) {
            return $query;
        }

        return mb_strlen($sanitized) > 200 
            ? mb_substr($sanitized, 0, 200) . '...' 
            : $sanitized;
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
     * Очищает кеш prepared statements при уничтожении объекта
     */
    public function __destruct()
    {
        $this->statementCache = [];
    }
}

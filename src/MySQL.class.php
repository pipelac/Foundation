<?php

declare(strict_types=1);

namespace App\Component;

use App\Component\Exception\MySQL\MySQLConnectionException;
use App\Component\Exception\MySQL\MySQLException;
use App\Component\Exception\MySQL\MySQLTransactionException;
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
            
            // Проверка версии MySQL для обеспечения совместимости
            $this->validateMySQLVersion();
            
            $this->logDebug('Успешное подключение к БД', [
                'host' => $host,
                'database' => $database,
                'persistent' => $persistent,
                'charset' => $charset,
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
        if (!isset($config['database'])) {
            throw new MySQLException('Параметр "database" обязателен');
        }
        
        if (trim((string)$config['database']) === '') {
            throw new MySQLException('Параметр "database" не может быть пустым');
        }

        if (!isset($config['username'])) {
            throw new MySQLException('Параметр "username" обязателен');
        }
        
        if (trim((string)$config['username']) === '') {
            throw new MySQLException('Параметр "username" не может быть пустым');
        }

        if (!isset($config['password'])) {
            throw new MySQLException('Параметр "password" обязателен');
        }
    }

    /**
     * Валидирует версию MySQL для обеспечения совместимости
     * 
     * Проверяет, что версия MySQL соответствует минимальным требованиям (5.5.62+).
     * Выводит предупреждение в лог при использовании устаревших версий.
     * 
     * @throws MySQLException Если версия MySQL слишком старая (< 5.5)
     */
    private function validateMySQLVersion(): void
    {
        try {
            $version = (string)$this->connection->getAttribute(PDO::ATTR_SERVER_VERSION);
            
            // Извлекаем основную версию (например, "5.5.62" из "5.5.62-log")
            preg_match('/^(\d+)\.(\d+)\.(\d+)/', $version, $matches);
            
            if (empty($matches)) {
                $this->logDebug('Не удалось определить версию MySQL', ['version_string' => $version]);
                return;
            }
            
            $majorVersion = (int)$matches[1];
            $minorVersion = (int)$matches[2];
            $patchVersion = (int)$matches[3];
            
            // Минимальная поддерживаемая версия: MySQL 5.5.0
            $minMajor = 5;
            $minMinor = 5;
            $minPatch = 0;
            
            // Рекомендуемая версия: MySQL 5.5.62+
            $recommendedMajor = 5;
            $recommendedMinor = 5;
            $recommendedPatch = 62;
            
            // Проверка минимальной версии
            if ($majorVersion < $minMajor || 
                ($majorVersion === $minMajor && $minorVersion < $minMinor)) {
                throw new MySQLException(
                    sprintf(
                        'Версия MySQL %s не поддерживается. Минимальная версия: %d.%d.%d',
                        $version,
                        $minMajor,
                        $minMinor,
                        $minPatch
                    )
                );
            }
            
            // Предупреждение о старой версии (< 5.5.62)
            if ($majorVersion === $recommendedMajor && 
                $minorVersion === $recommendedMinor && 
                $patchVersion < $recommendedPatch) {
                $this->logDebug('Используется устаревшая версия MySQL', [
                    'current_version' => $version,
                    'recommended_version' => sprintf('%d.%d.%d', $recommendedMajor, $recommendedMinor, $recommendedPatch),
                    'warning' => 'Рекомендуется обновление до MySQL 5.5.62 или выше для лучшей безопасности и производительности',
                ]);
            }
            
            // Информация о версии для отладки
            $this->logDebug('Версия MySQL проверена', [
                'version' => $version,
                'major' => $majorVersion,
                'minor' => $minorVersion,
                'patch' => $patchVersion,
            ]);
            
        } catch (PDOException $e) {
            $this->logDebug('Не удалось проверить версию MySQL', ['error' => $e->getMessage()]);
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
     * Вставляет запись в таблицу и возвращает ID вставленной записи
     * 
     * Industry standard метод для вставки данных в таблицу.
     * Автоматически формирует INSERT запрос из переданных данных.
     * 
     * Примеры использования:
     * ```php
     * $id = $db->insert('users', ['name' => 'John', 'email' => 'john@example.com']);
     * $id = $db->insert('posts', ['title' => 'Hello', 'content' => 'World', 'user_id' => 1]);
     * ```
     * 
     * @param string $table Имя таблицы для вставки
     * @param array<string, mixed> $data Данные для вставки в формате ['column' => 'value']
     * 
     * @return int ID вставленной записи (0 для таблиц без AUTO_INCREMENT)
     * 
     * @throws MySQLException Если данные пусты или запрос завершился с ошибкой
     */
    public function insert(string $table, array $data): int
    {
        if (empty($data)) {
            throw new MySQLException('Нет данных для вставки');
        }

        $columns = array_keys($data);
        $columnsStr = implode(', ', array_map(fn($col) => "`{$col}`", $columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        
        $query = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            $columnsStr,
            $placeholders
        );

        try {
            $this->prepareAndExecute($query, array_values($data));
            $lastId = (int)$this->connection->lastInsertId();

            $this->logDebug('Выполнен INSERT запрос', [
                'table' => $table,
                'columns' => $columns,
                'last_insert_id' => $lastId,
            ]);

            return $lastId;
        } catch (PDOException $e) {
            $this->logError('Ошибка выполнения INSERT запроса', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
            throw new MySQLException(
                sprintf('Ошибка вставки в таблицу "%s": %s', $table, $e->getMessage()),
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
     * Обновляет записи в таблице и возвращает количество затронутых строк
     * 
     * Industry standard метод для обновления данных в таблице.
     * Автоматически формирует UPDATE запрос из переданных данных.
     * 
     * Примеры использования:
     * ```php
     * $count = $db->update('users', ['name' => 'Jane'], ['id' => 1]);
     * $count = $db->update('posts', ['status' => 'published'], ['user_id' => 5, 'draft' => 1]);
     * ```
     * 
     * @param string $table Имя таблицы для обновления
     * @param array<string, mixed> $data Данные для обновления в формате ['column' => 'value']
     * @param array<string, mixed> $conditions Условия WHERE в формате ['column' => 'value']
     * 
     * @return int Количество обновленных строк
     * 
     * @throws MySQLException Если данные пусты или запрос завершился с ошибкой
     */
    public function update(string $table, array $data, array $conditions = []): int
    {
        if (empty($data)) {
            throw new MySQLException('Нет данных для обновления');
        }

        // Формируем SET часть запроса
        $setParts = [];
        $params = [];
        foreach ($data as $column => $value) {
            $setParts[] = sprintf('`%s` = ?', $column);
            $params[] = $value;
        }
        $setStr = implode(', ', $setParts);

        // Формируем WHERE часть запроса
        $whereStr = '';
        if (!empty($conditions)) {
            $whereParts = [];
            foreach ($conditions as $column => $value) {
                $whereParts[] = sprintf('`%s` = ?', $column);
                $params[] = $value;
            }
            $whereStr = ' WHERE ' . implode(' AND ', $whereParts);
        }

        $query = sprintf('UPDATE `%s` SET %s%s', $table, $setStr, $whereStr);

        try {
            $statement = $this->prepareAndExecute($query, $params);
            $rowCount = $statement->rowCount();

            $this->logDebug('Выполнен UPDATE запрос', [
                'table' => $table,
                'affected_rows' => $rowCount,
            ]);

            return $rowCount;
        } catch (PDOException $e) {
            $this->logError('Ошибка выполнения UPDATE запроса', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
            throw new MySQLException(
                sprintf('Ошибка обновления таблицы "%s": %s', $table, $e->getMessage()),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Удаляет записи из таблицы и возвращает количество удаленных строк
     * 
     * Industry standard метод для удаления данных из таблицы.
     * Автоматически формирует DELETE запрос из переданных условий.
     * 
     * Примеры использования:
     * ```php
     * $count = $db->delete('users', ['id' => 1]);
     * $count = $db->delete('posts', ['user_id' => 5, 'draft' => 1]);
     * $count = $db->delete('logs', []); // Удалит ВСЕ записи! Используйте осторожно
     * ```
     * 
     * @param string $table Имя таблицы для удаления
     * @param array<string, mixed> $conditions Условия WHERE в формате ['column' => 'value']
     * 
     * @return int Количество удаленных строк
     * 
     * @throws MySQLException Если запрос завершился с ошибкой
     */
    public function delete(string $table, array $conditions = []): int
    {
        // Формируем WHERE часть запроса
        $whereStr = '';
        $params = [];
        if (!empty($conditions)) {
            $whereParts = [];
            foreach ($conditions as $column => $value) {
                $whereParts[] = sprintf('`%s` = ?', $column);
                $params[] = $value;
            }
            $whereStr = ' WHERE ' . implode(' AND ', $whereParts);
        }

        $query = sprintf('DELETE FROM `%s`%s', $table, $whereStr);

        try {
            $statement = $this->prepareAndExecute($query, $params);
            $rowCount = $statement->rowCount();

            $this->logDebug('Выполнен DELETE запрос', [
                'table' => $table,
                'affected_rows' => $rowCount,
            ]);

            return $rowCount;
        } catch (PDOException $e) {
            $this->logError('Ошибка выполнения DELETE запроса', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
            throw new MySQLException(
                sprintf('Ошибка удаления из таблицы "%s": %s', $table, $e->getMessage()),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Выполняет произвольный SQL запрос (DDL, DML команды)
     * 
     * Используется для выполнения команд типа CREATE, ALTER, DROP, TRUNCATE и т.д.
     * Также поддерживает параметризованные запросы через prepared statements.
     * 
     * @param string $query SQL команда для выполнения
     * @param array<string|int, mixed> $params Параметры для подготовленного запроса
     * 
     * @return int Количество затронутых строк (0 для большинства DDL команд)
     * 
     * @throws MySQLException Если команда завершилась с ошибкой
     */
    public function execute(string $query, array $params = []): int
    {
        try {
            // Если есть параметры, используем prepared statements
            if (!empty($params)) {
                $statement = $this->prepareAndExecute($query, $params);
                $affectedRows = $statement->rowCount();
            } else {
                // Для DDL команд без параметров используем exec
                $affectedRows = $this->connection->exec($query);
            }

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
     * Выполняет SELECT запрос и возвращает значения одного столбца в виде массива
     * 
     * Удобно для получения списка ID, имен и других значений одного поля.
     * 
     * @param string $query SQL запрос с плейсхолдерами для параметров
     * @param array<string|int, mixed> $params Параметры для подготовленного запроса
     * 
     * @return array<int, mixed> Массив значений первого столбца
     * 
     * @throws MySQLException Если запрос завершился с ошибкой
     */
    public function queryColumn(string $query, array $params = []): array
    {
        try {
            $statement = $this->prepareAndExecute($query, $params);
            $result = $statement->fetchAll(PDO::FETCH_COLUMN, 0);

            $this->logDebug('Выполнен SELECT запрос (столбец)', [
                'query' => $this->sanitizeQueryForLog($query),
                'rows' => count($result),
            ]);

            return $result;
        } catch (PDOException $e) {
            $this->logError('Ошибка выполнения SELECT запроса (столбец)', [
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
     * Проверяет существование записей по заданному условию
     * 
     * Эффективнее, чем COUNT(*) для проверки наличия данных.
     * 
     * @param string $query SQL запрос с плейсхолдерами (рекомендуется SELECT 1 FROM ...)
     * @param array<string|int, mixed> $params Параметры для подготовленного запроса
     * 
     * @return bool True, если запись существует, иначе false
     * 
     * @throws MySQLException Если запрос завершился с ошибкой
     */
    public function exists(string $query, array $params = []): bool
    {
        try {
            $statement = $this->prepareAndExecute($query, $params);
            $exists = $statement->fetch() !== false;

            $this->logDebug('Проверка существования записи', [
                'query' => $this->sanitizeQueryForLog($query),
                'exists' => $exists,
            ]);

            return $exists;
        } catch (PDOException $e) {
            $this->logError('Ошибка проверки существования записи', [
                'query' => $this->sanitizeQueryForLog($query),
                'error' => $e->getMessage(),
            ]);
            throw new MySQLException(
                sprintf('Ошибка проверки существования записи: %s', $e->getMessage()),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Подсчитывает количество записей в таблице по заданным условиям
     * 
     * @param string $table Имя таблицы
     * @param array<string, mixed> $conditions Условия WHERE в формате ['column' => 'value']
     * 
     * @return int Количество записей
     * 
     * @throws MySQLException Если запрос завершился с ошибкой
     */
    public function count(string $table, array $conditions = []): int
    {
        $query = sprintf('SELECT COUNT(*) FROM `%s`', $table);
        
        if (!empty($conditions)) {
            $where = [];
            $params = [];
            
            foreach ($conditions as $column => $value) {
                $where[] = sprintf('`%s` = ?', $column);
                $params[] = $value;
            }
            
            $query .= ' WHERE ' . implode(' AND ', $where);
        }

        try {
            $count = (int)$this->queryScalar($query, $params ?? []);

            $this->logDebug('Подсчет записей в таблице', [
                'table' => $table,
                'conditions' => $conditions,
                'count' => $count,
            ]);

            return $count;
        } catch (MySQLException $e) {
            $this->logError('Ошибка подсчета записей', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Быстро очищает таблицу (удаляет все записи)
     * 
     * Использует TRUNCATE TABLE, что быстрее, чем DELETE FROM.
     * Внимание: сбрасывает счетчик AUTO_INCREMENT.
     * 
     * @param string $table Имя таблицы для очистки
     * 
     * @return int Всегда возвращает 0 (TRUNCATE не возвращает количество удаленных строк)
     * 
     * @throws MySQLException Если операция завершилась с ошибкой
     */
    public function truncate(string $table): int
    {
        $query = sprintf('TRUNCATE TABLE `%s`', $table);

        try {
            $result = $this->execute($query);

            $this->logDebug('Таблица очищена', ['table' => $table]);

            return $result;
        } catch (MySQLException $e) {
            $this->logError('Ошибка очистки таблицы', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
            throw new MySQLException(
                sprintf('Ошибка очистки таблицы "%s": %s', $table, $e->getMessage()),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Проверяет существование таблицы в базе данных
     * 
     * @param string $table Имя таблицы для проверки
     * 
     * @return bool True, если таблица существует, иначе false
     * 
     * @throws MySQLException Если запрос завершился с ошибкой
     */
    public function tableExists(string $table): bool
    {
        try {
            $query = 'SELECT 1 FROM information_schema.tables 
                      WHERE table_schema = DATABASE() 
                      AND table_name = ? 
                      LIMIT 1';
            
            $exists = $this->exists($query, [$table]);

            $this->logDebug('Проверка существования таблицы', [
                'table' => $table,
                'exists' => $exists,
            ]);

            return $exists;
        } catch (MySQLException $e) {
            $this->logError('Ошибка проверки существования таблицы', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Возвращает ID последней вставленной записи
     * 
     * Полезно после выполнения INSERT запроса через execute() или другие методы.
     * 
     * @return int ID последней вставленной записи (0 для таблиц без AUTO_INCREMENT)
     */
    public function getLastInsertId(): int
    {
        return (int)$this->connection->lastInsertId();
    }

    /**
     * Выполняет INSERT IGNORE запрос для игнорирования дубликатов
     * 
     * Полезно, когда нужно вставить запись, но игнорировать ошибку дубликата.
     * 
     * @param string $table Имя таблицы
     * @param array<string, mixed> $data Данные для вставки в формате ['column' => 'value']
     * 
     * @return int ID вставленной записи (0 если запись была проигнорирована)
     * 
     * @throws MySQLException Если запрос завершился с ошибкой
     */
    public function insertIgnore(string $table, array $data): int
    {
        if (empty($data)) {
            throw new MySQLException('Нет данных для вставки');
        }

        $columns = array_keys($data);
        $columnsStr = implode(', ', array_map(fn($col) => "`{$col}`", $columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        
        $query = sprintf(
            'INSERT IGNORE INTO `%s` (%s) VALUES (%s)',
            $table,
            $columnsStr,
            $placeholders
        );

        try {
            $this->prepareAndExecute($query, array_values($data));
            $lastId = $this->getLastInsertId();

            $this->logDebug('Выполнен INSERT IGNORE запрос', [
                'table' => $table,
                'last_insert_id' => $lastId,
            ]);

            return $lastId;
        } catch (PDOException $e) {
            $this->logError('Ошибка выполнения INSERT IGNORE запроса', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
            throw new MySQLException(
                sprintf('Ошибка выполнения INSERT IGNORE запроса: %s', $e->getMessage()),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Выполняет REPLACE запрос (удаляет существующую запись и вставляет новую)
     * 
     * Работает аналогично INSERT, но если запись с таким ключом существует, 
     * она будет удалена и вставлена новая.
     * 
     * @param string $table Имя таблицы
     * @param array<string, mixed> $data Данные для замены в формате ['column' => 'value']
     * 
     * @return int ID вставленной/замененной записи
     * 
     * @throws MySQLException Если запрос завершился с ошибкой
     */
    public function replace(string $table, array $data): int
    {
        if (empty($data)) {
            throw new MySQLException('Нет данных для замены');
        }

        $columns = array_keys($data);
        $columnsStr = implode(', ', array_map(fn($col) => "`{$col}`", $columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        
        $query = sprintf(
            'REPLACE INTO `%s` (%s) VALUES (%s)',
            $table,
            $columnsStr,
            $placeholders
        );

        try {
            $this->prepareAndExecute($query, array_values($data));
            $lastId = $this->getLastInsertId();

            $this->logDebug('Выполнен REPLACE запрос', [
                'table' => $table,
                'last_insert_id' => $lastId,
            ]);

            return $lastId;
        } catch (PDOException $e) {
            $this->logError('Ошибка выполнения REPLACE запроса', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
            throw new MySQLException(
                sprintf('Ошибка выполнения REPLACE запроса: %s', $e->getMessage()),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Выполняет INSERT ... ON DUPLICATE KEY UPDATE запрос
     * 
     * Вставляет новую запись или обновляет существующую при конфликте ключей.
     * 
     * @param string $table Имя таблицы
     * @param array<string, mixed> $data Данные для вставки в формате ['column' => 'value']
     * @param array<string, mixed> $updateData Данные для обновления при конфликте (если пусто, используется $data)
     * 
     * @return int ID вставленной/обновленной записи
     * 
     * @throws MySQLException Если запрос завершился с ошибкой
     */
    public function upsert(string $table, array $data, array $updateData = []): int
    {
        if (empty($data)) {
            throw new MySQLException('Нет данных для вставки');
        }

        // Если не указаны данные для обновления, используем данные для вставки
        if (empty($updateData)) {
            $updateData = $data;
        }

        $columns = array_keys($data);
        $columnsStr = implode(', ', array_map(fn($col) => "`{$col}`", $columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        
        $updateParts = [];
        foreach (array_keys($updateData) as $column) {
            $updateParts[] = sprintf('`%s` = VALUES(`%s`)', $column, $column);
        }
        $updateStr = implode(', ', $updateParts);
        
        $query = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
            $table,
            $columnsStr,
            $placeholders,
            $updateStr
        );

        try {
            $this->prepareAndExecute($query, array_values($data));
            $lastId = $this->getLastInsertId();

            $this->logDebug('Выполнен UPSERT запрос', [
                'table' => $table,
                'last_insert_id' => $lastId,
            ]);

            return $lastId;
        } catch (PDOException $e) {
            $this->logError('Ошибка выполнения UPSERT запроса', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
            throw new MySQLException(
                sprintf('Ошибка выполнения UPSERT запроса: %s', $e->getMessage()),
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
     * Возвращает версию MySQL в структурированном виде
     * 
     * Полезно для проверки совместимости и отладки.
     * 
     * @return array{
     *     version: string,
     *     major: int,
     *     minor: int,
     *     patch: int,
     *     is_supported: bool,
     *     is_recommended: bool
     * } Информация о версии MySQL
     */
    public function getMySQLVersion(): array
    {
        $version = (string)$this->connection->getAttribute(PDO::ATTR_SERVER_VERSION);
        
        // Извлекаем компоненты версии
        preg_match('/^(\d+)\.(\d+)\.(\d+)/', $version, $matches);
        
        if (empty($matches)) {
            return [
                'version' => $version,
                'major' => 0,
                'minor' => 0,
                'patch' => 0,
                'is_supported' => false,
                'is_recommended' => false,
            ];
        }
        
        $major = (int)$matches[1];
        $minor = (int)$matches[2];
        $patch = (int)$matches[3];
        
        // Проверка поддержки (>= 5.5.0)
        $isSupported = ($major > 5) || ($major === 5 && $minor >= 5);
        
        // Проверка рекомендованной версии (>= 5.5.62)
        $isRecommended = ($major > 5) || 
                        ($major === 5 && $minor > 5) || 
                        ($major === 5 && $minor === 5 && $patch >= 62);
        
        return [
            'version' => $version,
            'major' => $major,
            'minor' => $minor,
            'patch' => $patch,
            'is_supported' => $isSupported,
            'is_recommended' => $isRecommended,
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
        
        // Если используются позиционные плейсхолдеры (?), нормализуем массив параметров
        // чтобы использовались числовые индексы, начиная с 0
        if (!empty($params) && $this->usesPositionalPlaceholders($query)) {
            $params = array_values($params);
        }
        
        $statement->execute($params);

        return $statement;
    }

    /**
     * Определяет, использует ли SQL запрос позиционные плейсхолдеры (?)
     * 
     * @param string $query SQL запрос
     * 
     * @return bool True, если используются позиционные плейсхолдеры, иначе false
     */
    private function usesPositionalPlaceholders(string $query): bool
    {
        // Проверяем наличие позиционных плейсхолдеров (?)
        // Учитываем, что ? может быть внутри строковых литералов, которые нужно игнорировать
        // Простая эвристика: если есть хотя бы один ? вне кавычек, считаем что используются позиционные
        $strippedQuery = preg_replace("/'([^']|\\\\')*'/", '', $query);
        $strippedQuery = preg_replace('/"([^"]|\\\\")*"/', '', $strippedQuery ?? '');
        
        return ($strippedQuery !== null && strpos($strippedQuery, '?') !== false);
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

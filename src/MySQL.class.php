<?php

declare(strict_types=1);

namespace App\Component;

use PDO;
use PDOException;
use PDOStatement;
use Exception;

/**
 * Класс для работы с MySQL через PDO
 */
class MySQL
{
    private PDO $connection;
    private ?Logger $logger;

    /**
     * @param array<string, mixed> $config Конфигурация подключения к БД
     * @param Logger|null $logger Логгер для записи ошибок
     * @throws Exception Если не удалось подключиться к БД
     */
    public function __construct(array $config, ?Logger $logger = null)
    {
        $this->logger = $logger;

        $host = (string)($config['host'] ?? 'localhost');
        $port = (int)($config['port'] ?? 3306);
        $database = (string)($config['database'] ?? '');
        $username = (string)($config['username'] ?? '');
        $password = (string)($config['password'] ?? '');
        $charset = (string)($config['charset'] ?? 'utf8mb4');
        $options = (array)($config['options'] ?? []);

        if ($database === '') {
            throw new Exception('Не указано имя базы данных.');
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        $defaultOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->connection = new PDO($dsn, $username, $password, array_merge($defaultOptions, $options));
        } catch (PDOException $e) {
            $this->logError('Ошибка подключения к БД', ['exception' => $e->getMessage()]);
            throw new Exception('Не удалось подключиться к базе данных: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Возвращает инстанс PDO для расширенной работы с БД
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    /**
     * Выполняет SELECT запрос и возвращает все строки
     *
     * @param string $query SQL запрос
     * @param array<string, mixed> $params Параметры для подготовленного запроса
     * @return array<int, array<string, mixed>> Массив результатов
     * @throws Exception Если запрос завершился с ошибкой
     */
    public function query(string $query, array $params = []): array
    {
        try {
            $statement = $this->execute($query, $params);

            return $statement->fetchAll();
        } catch (PDOException $e) {
            $this->logError('Ошибка выполнения SELECT', ['query' => $query, 'exception' => $e->getMessage()]);
            throw new Exception('Ошибка выполнения запроса: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Выполняет SELECT запрос и возвращает одну строку
     *
     * @param string $query SQL запрос
     * @param array<string, mixed> $params Параметры для подготовленного запроса
     * @return array<string, mixed>|null Строка результата или null
     * @throws Exception Если запрос завершился с ошибкой
     */
    public function queryOne(string $query, array $params = []): ?array
    {
        try {
            $statement = $this->execute($query, $params);
            $result = $statement->fetch();

            return $result === false ? null : $result;
        } catch (PDOException $e) {
            $this->logError('Ошибка выполнения SELECT одной строки', ['query' => $query, 'exception' => $e->getMessage()]);
            throw new Exception('Ошибка выполнения запроса: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Выполняет INSERT запрос и возвращает ID вставленной записи
     *
     * @param string $query SQL запрос
     * @param array<string, mixed> $params Параметры для подготовленного запроса
     * @return string ID вставленной записи
     * @throws Exception Если запрос завершился с ошибкой
     */
    public function insert(string $query, array $params = []): string
    {
        try {
            $this->execute($query, $params);

            return $this->connection->lastInsertId();
        } catch (PDOException $e) {
            $this->logError('Ошибка выполнения INSERT', ['query' => $query, 'exception' => $e->getMessage()]);
            throw new Exception('Ошибка выполнения INSERT: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Выполняет UPDATE запрос и возвращает количество затронутых строк
     *
     * @param string $query SQL запрос
     * @param array<string, mixed> $params Параметры для подготовленного запроса
     * @return int Количество обновленных строк
     * @throws Exception Если запрос завершился с ошибкой
     */
    public function update(string $query, array $params = []): int
    {
        try {
            $statement = $this->execute($query, $params);

            return $statement->rowCount();
        } catch (PDOException $e) {
            $this->logError('Ошибка выполнения UPDATE', ['query' => $query, 'exception' => $e->getMessage()]);
            throw new Exception('Ошибка выполнения UPDATE: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Выполняет DELETE запрос и возвращает количество удаленных строк
     *
     * @param string $query SQL запрос
     * @param array<string, mixed> $params Параметры для подготовленного запроса
     * @return int Количество удаленных строк
     * @throws Exception Если запрос завершился с ошибкой
     */
    public function delete(string $query, array $params = []): int
    {
        try {
            $statement = $this->execute($query, $params);

            return $statement->rowCount();
        } catch (PDOException $e) {
            $this->logError('Ошибка выполнения DELETE', ['query' => $query, 'exception' => $e->getMessage()]);
            throw new Exception('Ошибка выполнения DELETE: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Начинает транзакцию
     *
     * @throws Exception Если транзакция уже активна
     */
    public function beginTransaction(): void
    {
        try {
            if (!$this->connection->inTransaction()) {
                $this->connection->beginTransaction();
            }
        } catch (PDOException $e) {
            $this->logError('Ошибка начала транзакции', ['exception' => $e->getMessage()]);
            throw new Exception('Не удалось начать транзакцию: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Подтверждает транзакцию
     *
     * @throws Exception Если транзакция не активна
     */
    public function commit(): void
    {
        try {
            if ($this->connection->inTransaction()) {
                $this->connection->commit();
            }
        } catch (PDOException $e) {
            $this->logError('Ошибка commit транзакции', ['exception' => $e->getMessage()]);
            throw new Exception('Не удалось подтвердить транзакцию: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Откатывает транзакцию
     *
     * @throws Exception Если транзакция не активна
     */
    public function rollback(): void
    {
        try {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
        } catch (PDOException $e) {
            $this->logError('Ошибка rollback транзакции', ['exception' => $e->getMessage()]);
            throw new Exception('Не удалось откатить транзакцию: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Выполняет подготовленный запрос с параметрами
     *
     * @param string $query SQL запрос
     * @param array<string, mixed> $params Параметры для привязки
     * @throws PDOException Если запрос не удалось выполнить
     */
    private function execute(string $query, array $params = []): PDOStatement
    {
        $statement = $this->connection->prepare($query);
        $statement->execute($params);

        return $statement;
    }

    /**
     * Записывает ошибку в лог, если логгер установлен
     *
     * @param string $message Текст ошибки
     * @param array<string, mixed> $context Контекст ошибки
     */
    private function logError(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->error($message, $context);
        }
    }
}

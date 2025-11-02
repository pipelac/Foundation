<?php

declare(strict_types=1);

namespace App\Rss2Tlg;

use App\Component\Logger;
use App\Component\MySQL;
use App\Rss2Tlg\DTO\FeedState;

/**
 * Репозиторий для работы с состоянием RSS/Atom источников в БД
 * 
 * Управляет персистентным хранением состояния каждого источника:
 * ETag, Last-Modified, статусы, счётчики ошибок и backoff.
 */
class FeedStateRepository
{
    private const TABLE_NAME = 'rss2tlg_feed_state';

    /**
     * Конструктор репозитория
     * 
     * @param MySQL $db Подключение к БД
     * @param Logger|null $logger Логгер для отладки
     * @param bool $autoCreateTables Автоматическое создание таблиц (по умолчанию true)
     */
    public function __construct(
        private readonly MySQL $db,
        private readonly ?Logger $logger = null,
        bool $autoCreateTables = true
    ) {
        if ($autoCreateTables) {
            $this->createTableIfNotExist();
        }
    }

    /**
     * Получает состояние источника по ID
     * 
     * @param int $feedId Идентификатор источника
     * @return FeedState|null Состояние источника или null если не найдено
     */
    public function getByFeedId(int $feedId): ?FeedState
    {
        try {
            $sql = sprintf(
                "SELECT * FROM %s WHERE feed_id = %d LIMIT 1",
                self::TABLE_NAME,
                $feedId
            );

            $result = $this->db->query($sql);
            
            if (empty($result)) {
                return null;
            }

            return $this->mapRowToFeedState($result[0]);
        } catch (\Exception $e) {
            $this->logError('Ошибка получения состояния источника', [
                'feed_id' => $feedId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Получает состояние источника по URL
     * 
     * @param string $url URL источника
     * @return FeedState|null Состояние источника или null если не найдено
     */
    public function getByUrl(string $url): ?FeedState
    {
        try {
            $sql = sprintf("SELECT * FROM %s WHERE url = ? LIMIT 1", self::TABLE_NAME);
            $result = $this->db->query($sql, [$url]);

            if (empty($result)) {
                return null;
            }

            return $this->mapRowToFeedState($result[0]);
        } catch (\Exception $e) {
            $this->logError('Ошибка получения состояния по URL', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Сохраняет состояние источника (создаёт или обновляет)
     * 
     * @param int $feedId Идентификатор источника
     * @param string $url URL источника
     * @param FeedState $state Состояние для сохранения
     * @return bool true при успехе
     */
    public function save(int $feedId, string $url, FeedState $state): bool
    {
        try {
            // Экранируем строковые значения через готовый метод MySQL::escape()
            $urlEscaped = $this->db->escape($url);
            $etagEscaped = $state->etag !== null ? $this->db->escape($state->etag) : 'NULL';
            $lastModifiedEscaped = $state->lastModified !== null ? $this->db->escape($state->lastModified) : 'NULL';
            $backoffUntilEscaped = $state->backoffUntil !== null 
                ? $this->db->escape(date('Y-m-d H:i:s', $state->backoffUntil))
                : 'NULL';

            $sql = sprintf(
                "INSERT INTO %s (
                    feed_id, url, etag, last_modified, last_status, 
                    error_count, backoff_until, fetched_at, updated_at
                ) VALUES (
                    %d, %s, %s, %s, %d, %d, %s, FROM_UNIXTIME(%d), NOW()
                ) ON DUPLICATE KEY UPDATE
                    etag = VALUES(etag),
                    last_modified = VALUES(last_modified),
                    last_status = VALUES(last_status),
                    error_count = VALUES(error_count),
                    backoff_until = VALUES(backoff_until),
                    fetched_at = VALUES(fetched_at),
                    updated_at = NOW()",
                self::TABLE_NAME,
                $feedId,
                $urlEscaped,
                $etagEscaped,
                $lastModifiedEscaped,
                $state->lastStatus,
                $state->errorCount,
                $backoffUntilEscaped,
                $state->fetchedAt
            );

            $this->db->execute($sql);

            $this->logDebug('Состояние источника сохранено', [
                'feed_id' => $feedId,
                'url' => $url,
                'last_status' => $state->lastStatus,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logError('Ошибка сохранения состояния источника', [
                'feed_id' => $feedId,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Удаляет состояние источника
     * 
     * @param int $feedId Идентификатор источника
     * @return bool true при успехе
     */
    public function delete(int $feedId): bool
    {
        try {
            $sql = sprintf("DELETE FROM %s WHERE feed_id = %d", self::TABLE_NAME, $feedId);
            $this->db->execute($sql);

            $this->logDebug('Состояние источника удалено', ['feed_id' => $feedId]);
            return true;
        } catch (\Exception $e) {
            $this->logError('Ошибка удаления состояния источника', [
                'feed_id' => $feedId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Получает все источники в состоянии backoff
     * 
     * @return array<int, array{feed_id: int, backoff_until: int}> Массив источников в backoff
     */
    public function getInBackoff(): array
    {
        try {
            $sql = sprintf(
                "SELECT feed_id, UNIX_TIMESTAMP(backoff_until) as backoff_until 
                FROM %s 
                WHERE backoff_until IS NOT NULL AND backoff_until > NOW()",
                self::TABLE_NAME
            );

            return $this->db->query($sql);
        } catch (\Exception $e) {
            $this->logError('Ошибка получения источников в backoff', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Сбрасывает счётчик ошибок и backoff для источника
     * 
     * @param int $feedId Идентификатор источника
     * @return bool true при успехе
     */
    public function resetErrors(int $feedId): bool
    {
        try {
            $sql = sprintf(
                "UPDATE %s SET error_count = 0, backoff_until = NULL, updated_at = NOW() WHERE feed_id = %d",
                self::TABLE_NAME,
                $feedId
            );
            $this->db->execute($sql);

            $this->logDebug('Ошибки источника сброшены', ['feed_id' => $feedId]);
            return true;
        } catch (\Exception $e) {
            $this->logError('Ошибка сброса ошибок источника', [
                'feed_id' => $feedId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Преобразует строку БД в объект FeedState
     * 
     * @param array<string, mixed> $row Строка из БД
     * @return FeedState Объект состояния
     */
    private function mapRowToFeedState(array $row): FeedState
    {
        $backoffUntil = null;
        if (isset($row['backoff_until']) && $row['backoff_until'] !== null) {
            $backoffUntil = is_numeric($row['backoff_until']) 
                ? (int)$row['backoff_until']
                : strtotime($row['backoff_until']);
        }

        $fetchedAt = 0;
        if (isset($row['fetched_at']) && $row['fetched_at'] !== null) {
            $fetchedAt = is_numeric($row['fetched_at'])
                ? (int)$row['fetched_at']
                : strtotime($row['fetched_at']);
        }

        return new FeedState(
            etag: isset($row['etag']) && $row['etag'] !== '' ? (string)$row['etag'] : null,
            lastModified: isset($row['last_modified']) && $row['last_modified'] !== '' ? (string)$row['last_modified'] : null,
            lastStatus: (int)($row['last_status'] ?? 0),
            errorCount: (int)($row['error_count'] ?? 0),
            backoffUntil: $backoffUntil,
            fetchedAt: $fetchedAt
        );
    }

    /**
     * Логирует отладочную информацию
     * 
     * @param string $message Сообщение
     * @param array<string, mixed> $context Контекст
     */
    private function logDebug(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->debug($message, $context);
        }
    }

    /**
     * Логирует ошибку
     * 
     * @param string $message Сообщение об ошибке
     * @param array<string, mixed> $context Контекст
     */
    private function logError(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->error($message, $context);
        }
    }

    /**
     * Создаёт таблицу состояния источников если она не существует
     * 
     * @return bool true при успехе
     */
    private function createTableIfNotExist(): bool
    {
        try {
            // Проверяем существование таблицы
            $tableExists = $this->db->queryOne(
                "SELECT COUNT(*) as count FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
                [self::TABLE_NAME]
            );

            if (empty($tableExists) || $tableExists['count'] == 0) {
                $sql = "CREATE TABLE `" . self::TABLE_NAME . "` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Первичный ключ',
                    `feed_id` INT UNSIGNED NOT NULL COMMENT 'Идентификатор источника (из конфигурации)',
                    `url` VARCHAR(512) NOT NULL COMMENT 'URL RSS/Atom ленты',
                    
                    `etag` VARCHAR(255) NULL DEFAULT NULL COMMENT 'ETag из последнего успешного ответа',
                    `last_modified` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Last-Modified из последнего успешного ответа',
                    
                    `last_status` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'HTTP статус код последнего запроса (0 = сетевая ошибка)',
                    `error_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Счётчик последовательных ошибок',
                    `backoff_until` DATETIME NULL DEFAULT NULL COMMENT 'Время до которого запросы заблокированы (exponential backoff)',
                    
                    `fetched_at` DATETIME NOT NULL COMMENT 'Время последнего запроса',
                    `updated_at` DATETIME NOT NULL COMMENT 'Время последнего обновления записи',
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Время создания записи',
                    
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `idx_feed_id` (`feed_id`),
                    UNIQUE KEY `idx_url` (`url`),
                    KEY `idx_backoff_until` (`backoff_until`),
                    KEY `idx_error_count` (`error_count`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                COMMENT='Состояние RSS/Atom источников для модуля fetch'";

                $this->db->execute($sql);

                $this->logDebug('Таблица состояния источников создана', ['table' => self::TABLE_NAME]);
            }

            return true;
        } catch (\Exception $e) {
            $this->logError('Ошибка создания таблицы состояния источников', [
                'table' => self::TABLE_NAME,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

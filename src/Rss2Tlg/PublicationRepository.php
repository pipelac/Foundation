<?php

declare(strict_types=1);

namespace App\Rss2Tlg;

use App\Component\Logger;
use App\Component\MySQL;

/**
 * Репозиторий для работы с журналом публикаций в Telegram
 * 
 * Хранит информацию о том, какие новости, когда и куда были опубликованы.
 */
class PublicationRepository
{
    private const TABLE_NAME = 'rss2tlg_publications';

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
     * Записывает факт публикации в журнал
     * 
     * @param int $itemId ID новости
     * @param int $feedId ID источника
     * @param string $destinationType Тип назначения (bot/channel)
     * @param string $destinationId ID чата/канала
     * @param int $messageId ID сообщения в Telegram
     * @return int|null ID записи или null при ошибке
     */
    public function record(
        int $itemId,
        int $feedId,
        string $destinationType,
        string $destinationId,
        int $messageId
    ): ?int {
        try {
            $destType = $this->db->escape($destinationType);
            $destId = $this->db->escape($destinationId);

            $sql = sprintf(
                "INSERT INTO %s (
                    item_id, feed_id, destination_type, destination_id, 
                    message_id, published_at, created_at
                ) VALUES (
                    %d, %d, %s, %s, %d, NOW(), NOW()
                )",
                self::TABLE_NAME,
                $itemId,
                $feedId,
                $destType,
                $destId,
                $messageId
            );

            $this->db->execute($sql);

            $insertId = $this->db->getLastInsertId();

            $this->logDebug('Публикация записана', [
                'id' => $insertId,
                'item_id' => $itemId,
                'destination' => $destinationType . ':' . $destinationId,
                'message_id' => $messageId,
            ]);

            return $insertId;
        } catch (\Exception $e) {
            $this->logError('Ошибка записи публикации', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Получает все публикации для новости
     * 
     * @param int $itemId ID новости
     * @return array<int, array<string, mixed>> Массив публикаций
     */
    public function getByItemId(int $itemId): array
    {
        try {
            $sql = sprintf(
                "SELECT * FROM %s WHERE item_id = %d ORDER BY published_at DESC",
                self::TABLE_NAME,
                $itemId
            );
            
            return $this->db->query($sql);
        } catch (\Exception $e) {
            $this->logError('Ошибка получения публикаций', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Получает статистику по публикациям
     * 
     * @return array<string, mixed> Статистика
     */
    public function getStats(): array
    {
        try {
            $sql = sprintf(
                "SELECT 
                    COUNT(*) as total,
                    COUNT(DISTINCT item_id) as unique_items,
                    COUNT(DISTINCT feed_id) as unique_feeds,
                    SUM(CASE WHEN destination_type = 'bot' THEN 1 ELSE 0 END) as to_bot,
                    SUM(CASE WHEN destination_type = 'channel' THEN 1 ELSE 0 END) as to_channel,
                    MIN(published_at) as first_publication,
                    MAX(published_at) as last_publication
                FROM %s",
                self::TABLE_NAME
            );
            
            $result = $this->db->queryOne($sql);
            return $result ?? [];
        } catch (\Exception $e) {
            $this->logError('Ошибка получения статистики публикаций', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
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
     * Создаёт таблицу публикаций если она не существует
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
                    `item_id` INT UNSIGNED NOT NULL COMMENT 'ID новости (FK -> rss2tlg_items)',
                    `feed_id` INT UNSIGNED NOT NULL COMMENT 'ID источника',
                    
                    `destination_type` ENUM('bot', 'channel') NOT NULL COMMENT 'Тип назначения',
                    `destination_id` VARCHAR(255) NOT NULL COMMENT 'ID чата или канала',
                    `message_id` INT UNSIGNED NOT NULL COMMENT 'ID сообщения в Telegram',
                    
                    `published_at` DATETIME NOT NULL COMMENT 'Время публикации',
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Время создания записи',
                    
                    PRIMARY KEY (`id`),
                    KEY `idx_item_id` (`item_id`),
                    KEY `idx_feed_id` (`feed_id`),
                    KEY `idx_destination` (`destination_type`, `destination_id`),
                    KEY `idx_published_at` (`published_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                COMMENT='Журнал публикаций новостей в Telegram'";

                $this->db->execute($sql);

                $this->logDebug('Таблица публикаций создана', ['table' => self::TABLE_NAME]);
            }

            return true;
        } catch (\Exception $e) {
            $this->logError('Ошибка создания таблицы публикаций', [
                'table' => self::TABLE_NAME,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

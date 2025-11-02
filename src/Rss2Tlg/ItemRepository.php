<?php

declare(strict_types=1);

namespace App\Rss2Tlg;

use App\Component\Logger;
use App\Component\MySQL;
use App\Rss2Tlg\DTO\RawItem;

/**
 * Репозиторий для работы с новостями из RSS/Atom источников в БД
 * 
 * Управляет хранением и дедупликацией новостей по content_hash.
 */
class ItemRepository
{
    private const TABLE_NAME = 'rss2tlg_items';

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
     * Сохраняет новость в БД
     * 
     * @param int $feedId Идентификатор источника
     * @param RawItem $item Новость для сохранения
     * @return int|null ID сохраненной записи или null при ошибке
     */
    public function save(int $feedId, RawItem $item): ?int
    {
        try {
            // Проверяем, существует ли новость с таким content_hash
            $existing = $this->getByContentHash($item->contentHash);
            if ($existing !== null) {
                $this->logDebug('Новость уже существует', [
                    'content_hash' => $item->contentHash,
                    'existing_id' => $existing['id'],
                ]);
                return (int)$existing['id'];
            }

            // Экранируем данные
            $contentHash = $this->db->escape($item->contentHash);
            $guid = $item->guid !== null ? $this->db->escape($item->guid) : 'NULL';
            $title = $this->db->escape($item->title ?? '');
            $link = $this->db->escape($item->link ?? '');
            $description = $item->summary !== null ? $this->db->escape($item->summary) : 'NULL';
            $content = $item->content !== null ? $this->db->escape($item->content) : 'NULL';
            $pubDate = $item->pubDate !== null 
                ? $this->db->escape(date('Y-m-d H:i:s', $item->pubDate))
                : 'NULL';
            $author = !empty($item->authors) ? $this->db->escape($item->authors[0]) : 'NULL';
            $categories = !empty($item->categories) ? $this->db->escape(json_encode($item->categories)) : 'NULL';
            $enclosures = $item->enclosure !== null ? $this->db->escape(json_encode($item->enclosure)) : 'NULL';

            $sql = sprintf(
                "INSERT INTO %s (
                    feed_id, content_hash, guid, title, link, description, 
                    content, pub_date, author, categories, enclosures, 
                    is_published, created_at, updated_at
                ) VALUES (
                    %d, %s, %s, %s, %s, %s, 
                    %s, %s, %s, %s, %s,
                    0, NOW(), NOW()
                )",
                self::TABLE_NAME,
                $feedId,
                $contentHash,
                $guid,
                $title,
                $link,
                $description,
                $content,
                $pubDate,
                $author,
                $categories,
                $enclosures
            );

            $this->db->execute($sql);

            $insertId = $this->db->getLastInsertId();

            $this->logDebug('Новость сохранена', [
                'id' => $insertId,
                'feed_id' => $feedId,
                'content_hash' => $item->contentHash,
            ]);

            return $insertId;
        } catch (\Exception $e) {
            $this->logError('Ошибка сохранения новости', [
                'feed_id' => $feedId,
                'content_hash' => $item->contentHash,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Получает новость по content_hash
     * 
     * @param string $contentHash Хеш контента
     * @return array<string, mixed>|null Массив с данными новости или null
     */
    public function getByContentHash(string $contentHash): ?array
    {
        try {
            $sql = sprintf("SELECT * FROM %s WHERE content_hash = ? LIMIT 1", self::TABLE_NAME);
            $result = $this->db->query($sql, [$contentHash]);
            
            return !empty($result) ? $result[0] : null;
        } catch (\Exception $e) {
            $this->logError('Ошибка получения новости по content_hash', [
                'content_hash' => $contentHash,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Проверяет, существует ли новость с таким content_hash
     * 
     * @param string $contentHash Хеш контента
     * @return bool true если существует
     */
    public function exists(string $contentHash): bool
    {
        return $this->getByContentHash($contentHash) !== null;
    }

    /**
     * Помечает новость как опубликованную
     * 
     * @param int $itemId ID новости
     * @return bool true при успехе
     */
    public function markAsPublished(int $itemId): bool
    {
        try {
            $sql = sprintf(
                "UPDATE %s SET is_published = 1, updated_at = NOW() WHERE id = %d",
                self::TABLE_NAME,
                $itemId
            );
            $this->db->execute($sql);

            $this->logDebug('Новость помечена как опубликованная', ['id' => $itemId]);
            return true;
        } catch (\Exception $e) {
            $this->logError('Ошибка обновления статуса публикации', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Получает неопубликованные новости из источника
     * 
     * @param int $feedId Идентификатор источника
     * @param int $limit Максимальное количество новостей
     * @return array<int, array<string, mixed>> Массив новостей
     */
    public function getUnpublished(int $feedId, int $limit = 10): array
    {
        try {
            $sql = sprintf(
                "SELECT * FROM %s WHERE feed_id = %d AND is_published = 0 ORDER BY pub_date DESC LIMIT %d",
                self::TABLE_NAME,
                $feedId,
                $limit
            );
            
            return $this->db->query($sql);
        } catch (\Exception $e) {
            $this->logError('Ошибка получения неопубликованных новостей', [
                'feed_id' => $feedId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Получает статистику по новостям
     * 
     * @return array<string, mixed> Статистика
     */
    public function getStats(): array
    {
        try {
            $sql = sprintf(
                "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN is_published = 1 THEN 1 ELSE 0 END) as published,
                    SUM(CASE WHEN is_published = 0 THEN 1 ELSE 0 END) as unpublished,
                    COUNT(DISTINCT feed_id) as unique_feeds
                FROM %s",
                self::TABLE_NAME
            );
            
            $result = $this->db->queryOne($sql);
            return $result ?? [];
        } catch (\Exception $e) {
            $this->logError('Ошибка получения статистики', [
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
     * Создаёт таблицу новостей если она не существует
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
                    `feed_id` INT UNSIGNED NOT NULL COMMENT 'Идентификатор источника',
                    `content_hash` VARCHAR(32) NOT NULL COMMENT 'MD5 хеш контента для дедупликации',
                    
                    `guid` VARCHAR(512) NULL DEFAULT NULL COMMENT 'GUID элемента из RSS',
                    `title` VARCHAR(512) NOT NULL COMMENT 'Заголовок новости',
                    `link` VARCHAR(1024) NOT NULL COMMENT 'Ссылка на новость',
                    `description` TEXT NULL DEFAULT NULL COMMENT 'Краткое описание',
                    `content` MEDIUMTEXT NULL DEFAULT NULL COMMENT 'Полный контент',
                    
                    `pub_date` DATETIME NULL DEFAULT NULL COMMENT 'Дата публикации в источнике',
                    `author` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Автор',
                    `categories` JSON NULL DEFAULT NULL COMMENT 'Категории (массив)',
                    `enclosures` JSON NULL DEFAULT NULL COMMENT 'Вложения: изображения, аудио, видео',
                    
                    `is_published` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Флаг публикации в Telegram',
                    
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Время создания записи',
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Время последнего обновления',
                    
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `idx_content_hash` (`content_hash`),
                    KEY `idx_feed_id` (`feed_id`),
                    KEY `idx_is_published` (`is_published`),
                    KEY `idx_pub_date` (`pub_date`),
                    KEY `idx_feed_published` (`feed_id`, `is_published`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                COMMENT='Новости из RSS/Atom источников'";

                $this->db->execute($sql);

                $this->logDebug('Таблица новостей создана', ['table' => self::TABLE_NAME]);
            }

            return true;
        } catch (\Exception $e) {
            $this->logError('Ошибка создания таблицы новостей', [
                'table' => self::TABLE_NAME,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

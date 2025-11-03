<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Core;

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\TelegramBot\Entities\Message;

/**
 * Класс для хранения входящих и исходящих сообщений Telegram бота в БД
 * 
 * Возможности:
 * - Хранение всех входящих и исходящих сообщений
 * - Гибкая настройка уровня детализации хранимых данных
 * - Автоматическое создание таблицы при активации
 * - Индексы для быстрого поиска
 * - Контроль времени хранения записей
 * 
 * Уровни хранения:
 * - minimal: базовая информация (ID, тип, время, статус)
 * - standard: + текст, подпись, файлы, ответы
 * - extended: + метаданные, пересылки, форматирование
 * - full: полная информация включая сырые данные API
 */
class MessageStorage
{
    /**
     * Минимальный уровень - только базовая информация
     */
    public const LEVEL_MINIMAL = 'minimal';

    /**
     * Стандартный уровень - базовая + текст и файлы
     */
    public const LEVEL_STANDARD = 'standard';

    /**
     * Расширенный уровень - стандартный + метаданные
     */
    public const LEVEL_EXTENDED = 'extended';

    /**
     * Полный уровень - вся доступная информация
     */
    public const LEVEL_FULL = 'full';

    /**
     * Направление: входящее сообщение
     */
    public const DIRECTION_INCOMING = 'incoming';

    /**
     * Направление: исходящее сообщение
     */
    public const DIRECTION_OUTGOING = 'outgoing';

    /**
     * Имя таблицы для хранения сообщений
     */
    private const TABLE_NAME = 'telegram_bot_messages';

    /**
     * @param MySQL $db Подключение к БД
     * @param Logger|null $logger Логгер
     * @param array{
     *     enabled?: bool,
     *     storage_level?: string,
     *     store_incoming?: bool,
     *     store_outgoing?: bool,
     *     exclude_methods?: array<string>,
     *     retention_days?: int,
     *     auto_create_table?: bool
     * } $config Конфигурация хранилища
     */
    public function __construct(
        private readonly MySQL $db,
        private readonly ?Logger $logger = null,
        private readonly array $config = []
    ) {
        $enabled = $this->config['enabled'] ?? false;
        $autoCreateTable = $this->config['auto_create_table'] ?? true;

        if ($enabled && $autoCreateTable) {
            $this->createTableIfNotExists();
        }
    }

    /**
     * Проверяет, включено ли хранилище
     *
     * @return bool True если хранилище активно
     */
    public function isEnabled(): bool
    {
        return (bool)($this->config['enabled'] ?? false);
    }

    /**
     * Сохраняет исходящее сообщение
     *
     * @param string $method Название метода API
     * @param array<string, mixed> $params Параметры запроса
     * @param Message|array<string, mixed>|bool|null $response Ответ от API
     * @param bool $success Успешность отправки
     * @param int|null $errorCode Код ошибки
     * @param string|null $errorMessage Сообщение об ошибке
     * @return int|null ID записи или null при ошибке
     */
    public function storeOutgoing(
        string $method,
        array $params,
        Message|array|bool|null $response,
        bool $success = true,
        ?int $errorCode = null,
        ?string $errorMessage = null
    ): ?int {
        if (!$this->isEnabled()) {
            return null;
        }

        if (!($this->config['store_outgoing'] ?? true)) {
            return null;
        }

        // Проверка исключенных методов
        if (in_array($method, $this->config['exclude_methods'] ?? [], true)) {
            return null;
        }

        try {
            $chatId = $this->extractChatId($params);
            $userId = $this->extractUserId($params, $response);
            $messageType = $this->detectMessageType($method);
            $messageId = $this->extractMessageId($response);
            $telegramDate = $this->extractTelegramDate($response);

            $data = [
                'direction' => self::DIRECTION_OUTGOING,
                'message_id' => $messageId,
                'chat_id' => $chatId,
                'user_id' => $userId,
                'message_type' => $messageType,
                'method_name' => $method,
                'created_at' => date('Y-m-d H:i:s'),
                'telegram_date' => $telegramDate,
                'success' => $success ? 1 : 0,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
            ];

            // Добавление полей в зависимости от уровня хранения
            $storageLevel = $this->config['storage_level'] ?? self::LEVEL_STANDARD;
            $this->addFieldsByStorageLevel($data, $storageLevel, $params, $response);

            $id = $this->insertData(self::TABLE_NAME, $data);

            $this->logger?->debug('Исходящее сообщение сохранено', [
                'id' => $id,
                'method' => $method,
                'chat_id' => $chatId,
                'success' => $success,
            ]);

            return $id;
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка сохранения исходящего сообщения', [
                'method' => $method,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Сохраняет входящее сообщение
     *
     * @param Message $message Объект входящего сообщения
     * @return int|null ID записи или null при ошибке
     */
    public function storeIncoming(Message $message): ?int
    {
        if (!$this->isEnabled()) {
            return null;
        }

        if (!($this->config['store_incoming'] ?? true)) {
            return null;
        }

        try {
            $data = [
                'direction' => self::DIRECTION_INCOMING,
                'message_id' => $message->messageId,
                'chat_id' => $message->chat->id,
                'user_id' => $message->from?->id,
                'message_type' => $this->detectIncomingMessageType($message),
                'method_name' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'telegram_date' => date('Y-m-d H:i:s', $message->date),
                'success' => 1,
                'error_code' => null,
                'error_message' => null,
            ];

            // Добавление полей в зависимости от уровня хранения
            $storageLevel = $this->config['storage_level'] ?? self::LEVEL_STANDARD;
            $this->addIncomingFieldsByStorageLevel($data, $storageLevel, $message);

            $id = $this->insertData(self::TABLE_NAME, $data);

            $this->logger?->debug('Входящее сообщение сохранено', [
                'id' => $id,
                'message_id' => $message->messageId,
                'chat_id' => $message->chat->id,
            ]);

            return $id;
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка сохранения входящего сообщения', [
                'message_id' => $message->messageId ?? null,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Получает статистику по сообщениям
     *
     * @param string|int|null $chatId ID чата для фильтрации
     * @param int|null $days Количество дней для анализа
     * @return array{total: int, incoming: int, outgoing: int, success: int, failed: int, by_type: array<string, int>}
     */
    public function getStatistics(string|int|null $chatId = null, ?int $days = null): array
    {
        try {
            $where = [];
            $params = [];

            if ($chatId !== null) {
                $where[] = 'chat_id = ?';
                $params[] = $chatId;
            }

            if ($days !== null) {
                $where[] = 'created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)';
                $params[] = $days;
            }

            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            // Общая статистика
            $query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN direction = 'incoming' THEN 1 ELSE 0 END) as incoming,
                SUM(CASE WHEN direction = 'outgoing' THEN 1 ELSE 0 END) as outgoing,
                SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as success,
                SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed
                FROM " . self::TABLE_NAME . " {$whereClause}";

            $stats = $this->db->queryOne($query, $params);

            // Статистика по типам
            $typeQuery = "SELECT message_type, COUNT(*) as count 
                FROM " . self::TABLE_NAME . " {$whereClause}
                GROUP BY message_type";

            $typeStats = $this->db->query($typeQuery, $params);

            $byType = [];
            foreach ($typeStats as $row) {
                $byType[$row['message_type']] = (int)$row['count'];
            }

            return [
                'total' => (int)($stats['total'] ?? 0),
                'incoming' => (int)($stats['incoming'] ?? 0),
                'outgoing' => (int)($stats['outgoing'] ?? 0),
                'success' => (int)($stats['success'] ?? 0),
                'failed' => (int)($stats['failed'] ?? 0),
                'by_type' => $byType,
            ];
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка получения статистики', [
                'error' => $e->getMessage(),
            ]);

            return [
                'total' => 0,
                'incoming' => 0,
                'outgoing' => 0,
                'success' => 0,
                'failed' => 0,
                'by_type' => [],
            ];
        }
    }

    /**
     * Удаляет старые записи согласно настройке retention_days
     *
     * @return int Количество удаленных записей
     */
    public function cleanupOldMessages(): int
    {
        $retentionDays = $this->config['retention_days'] ?? 0;

        if ($retentionDays <= 0) {
            return 0;
        }

        try {
            $query = "DELETE FROM " . self::TABLE_NAME . " 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";

            $result = $this->db->execute($query, [$retentionDays]);

            $this->logger?->info('Очистка старых сообщений выполнена', [
                'retention_days' => $retentionDays,
                'deleted' => $result,
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка очистки старых сообщений', [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Создает таблицу для хранения сообщений, если она не существует
     *
     * @return bool True при успешном создании
     */
    private function createTableIfNotExists(): bool
    {
        try {
            // Проверка существования таблицы
            $exists = $this->db->queryOne(
                "SELECT COUNT(*) as count FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
                [self::TABLE_NAME]
            );

            if (!empty($exists) && $exists['count'] > 0) {
                $this->logger?->debug('Таблица уже существует', ['table' => self::TABLE_NAME]);
                return true;
            }

            $sql = "CREATE TABLE " . self::TABLE_NAME . " (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                direction ENUM('incoming', 'outgoing') NOT NULL,
                message_id BIGINT UNSIGNED DEFAULT NULL,
                chat_id BIGINT NOT NULL,
                user_id BIGINT DEFAULT NULL,
                message_type VARCHAR(50) NOT NULL,
                method_name VARCHAR(50) DEFAULT NULL,
                created_at DATETIME NOT NULL,
                telegram_date DATETIME DEFAULT NULL,
                
                -- Standard level fields
                text TEXT DEFAULT NULL,
                caption TEXT DEFAULT NULL,
                file_id VARCHAR(255) DEFAULT NULL,
                file_name VARCHAR(255) DEFAULT NULL,
                reply_to_message_id BIGINT UNSIGNED DEFAULT NULL,
                
                -- Extended level fields
                file_size INT UNSIGNED DEFAULT NULL,
                mime_type VARCHAR(100) DEFAULT NULL,
                media_metadata JSON DEFAULT NULL,
                forward_from_chat_id BIGINT DEFAULT NULL,
                entities JSON DEFAULT NULL,
                
                -- Full level fields
                reply_markup JSON DEFAULT NULL,
                options JSON DEFAULT NULL,
                raw_data JSON DEFAULT NULL,
                
                -- Status fields
                success TINYINT(1) NOT NULL DEFAULT 1,
                error_code INT DEFAULT NULL,
                error_message TEXT DEFAULT NULL,
                
                PRIMARY KEY (id),
                INDEX idx_chat_id (chat_id),
                INDEX idx_user_id (user_id),
                INDEX idx_created_at (created_at),
                INDEX idx_direction_type (direction, message_type),
                INDEX idx_message_id (message_id),
                INDEX idx_telegram_date (telegram_date),
                UNIQUE KEY idx_unique_message (direction, chat_id, message_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
            COMMENT='Хранилище входящих и исходящих сообщений Telegram бота'";

            $this->db->execute($sql);

            $this->logger?->info('Таблица создана успешно', ['table' => self::TABLE_NAME]);

            return true;
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка создания таблицы', [
                'table' => self::TABLE_NAME,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Вспомогательный метод для вставки данных в таблицу
     *
     * @param string $table Имя таблицы
     * @param array<string, mixed> $data Данные для вставки
     * @return int ID вставленной записи
     */
    private function insertData(string $table, array $data): int
    {
        $columns = [];
        $values = [];
        
        foreach ($data as $col => $val) {
            $columns[] = "`{$col}`";
            
            if ($val === null) {
                $values[] = 'NULL';
            } elseif (is_bool($val)) {
                $values[] = $val ? '1' : '0';
            } elseif (is_int($val) || is_float($val)) {
                $values[] = $val;
            } else {
                // Экранирование строк для безопасности
                $escaped = str_replace(
                    ["\\", "\0", "\n", "\r", "'", '"', "\x1a"],
                    ["\\\\", "\\0", "\\n", "\\r", "\\'", '\\"', "\\Z"],
                    (string)$val
                );
                $values[] = "'{$escaped}'";
            }
        }
        
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $values)
        );
        
        $this->db->execute($sql);
        return $this->db->getLastInsertId();
    }
    
    /**
     * Добавляет поля в зависимости от уровня хранения (для исходящих)
     *
     * @param array<string, mixed> &$data Массив данных для вставки
     * @param string $level Уровень хранения
     * @param array<string, mixed> $params Параметры запроса
     * @param Message|array<string, mixed>|bool|null $response Ответ от API
     */
    private function addFieldsByStorageLevel(
        array &$data,
        string $level,
        array $params,
        Message|array|bool|null $response
    ): void {
        // Standard level
        if (in_array($level, [self::LEVEL_STANDARD, self::LEVEL_EXTENDED, self::LEVEL_FULL], true)) {
            $data['text'] = $params['text'] ?? null;
            $data['caption'] = $params['caption'] ?? null;
            
            // Извлечение file_id из различных типов параметров
            $data['file_id'] = $params['photo'] ?? $params['video'] ?? 
                $params['document'] ?? $params['audio'] ?? $params['voice'] ?? null;
            
            if ($data['file_id'] && !is_string($data['file_id'])) {
                $data['file_id'] = null; // Если это не строка (file_id), а CURLFile
            }
            
            $data['file_name'] = $params['filename'] ?? null;
            $data['reply_to_message_id'] = $params['reply_to_message_id'] ?? null;
        }

        // Extended level
        if (in_array($level, [self::LEVEL_EXTENDED, self::LEVEL_FULL], true)) {
            $mediaMetadata = [];
            
            if (isset($params['width'])) {
                $mediaMetadata['width'] = $params['width'];
            }
            if (isset($params['height'])) {
                $mediaMetadata['height'] = $params['height'];
            }
            if (isset($params['duration'])) {
                $mediaMetadata['duration'] = $params['duration'];
            }
            
            $data['media_metadata'] = !empty($mediaMetadata) ? json_encode($mediaMetadata) : null;
            $data['entities'] = isset($params['entities']) ? json_encode($params['entities']) : null;
        }

        // Full level
        if ($level === self::LEVEL_FULL) {
            $data['reply_markup'] = isset($params['reply_markup']) ? json_encode($params['reply_markup']) : null;
            $data['options'] = json_encode($params);
            
            if ($response instanceof Message) {
                $data['raw_data'] = json_encode($response);
            } elseif (is_array($response)) {
                $data['raw_data'] = json_encode($response);
            }
        }
    }

    /**
     * Добавляет поля в зависимости от уровня хранения (для входящих)
     *
     * @param array<string, mixed> &$data Массив данных для вставки
     * @param string $level Уровень хранения
     * @param Message $message Объект сообщения
     */
    private function addIncomingFieldsByStorageLevel(
        array &$data,
        string $level,
        Message $message
    ): void {
        // Standard level
        if (in_array($level, [self::LEVEL_STANDARD, self::LEVEL_EXTENDED, self::LEVEL_FULL], true)) {
            $data['text'] = $message->text;
            $data['caption'] = $message->caption;
            
            // Извлечение file_id в зависимости от типа сообщения
            if ($message->photo && !empty($message->photo)) {
                $data['file_id'] = end($message->photo)->fileId;
            } elseif ($message->video) {
                $data['file_id'] = $message->video->fileId;
            } elseif ($message->document) {
                $data['file_id'] = $message->document->fileId;
                $data['file_name'] = $message->document->fileName;
                $data['file_size'] = $message->document->fileSize;
                $data['mime_type'] = $message->document->mimeType;
            } elseif ($message->audio) {
                $data['file_id'] = $message->audio->fileId;
            } elseif ($message->voice) {
                $data['file_id'] = $message->voice->fileId;
            }
            
            $data['reply_to_message_id'] = $message->replyToMessage?->id;
        }

        // Extended level
        if (in_array($level, [self::LEVEL_EXTENDED, self::LEVEL_FULL], true)) {
            $mediaMetadata = [];
            
            if ($message->photo && !empty($message->photo)) {
                $photo = end($message->photo);
                $mediaMetadata['width'] = $photo->width;
                $mediaMetadata['height'] = $photo->height;
                $data['file_size'] = $photo->fileSize;
            } elseif ($message->video) {
                $mediaMetadata['width'] = $message->video->width;
                $mediaMetadata['height'] = $message->video->height;
                $mediaMetadata['duration'] = $message->video->duration;
                $data['file_size'] = $message->video->fileSize;
                $data['mime_type'] = $message->video->mimeType;
            } elseif ($message->audio) {
                $mediaMetadata['duration'] = $message->audio->duration;
                $data['file_size'] = $message->audio->fileSize;
                $data['mime_type'] = $message->audio->mimeType;
            } elseif ($message->voice) {
                $mediaMetadata['duration'] = $message->voice->duration;
                $data['file_size'] = $message->voice->fileSize;
                $data['mime_type'] = $message->voice->mimeType;
            }
            
            $data['media_metadata'] = !empty($mediaMetadata) ? json_encode($mediaMetadata) : null;
            $data['forward_from_chat_id'] = $message->forwardFromChat?->id;
            $data['entities'] = $message->entities ? json_encode($message->entities) : null;
        }

        // Full level
        if ($level === self::LEVEL_FULL) {
            $data['raw_data'] = json_encode($message);
        }
    }

    /**
     * Извлекает chat_id из параметров
     *
     * @param array<string, mixed> $params Параметры запроса
     * @return int|null
     */
    private function extractChatId(array $params): ?int
    {
        return isset($params['chat_id']) ? (int)$params['chat_id'] : null;
    }

    /**
     * Извлекает user_id из параметров или ответа
     *
     * @param array<string, mixed> $params Параметры запроса
     * @param Message|array<string, mixed>|bool|null $response Ответ от API
     * @return int|null
     */
    private function extractUserId(array $params, Message|array|bool|null $response): ?int
    {
        if (isset($params['user_id'])) {
            return (int)$params['user_id'];
        }

        if ($response instanceof Message && $response->from) {
            return $response->from->id;
        }

        return null;
    }

    /**
     * Извлекает message_id из ответа
     *
     * @param Message|array<string, mixed>|bool|null $response Ответ от API
     * @return int|null
     */
    private function extractMessageId(Message|array|bool|null $response): ?int
    {
        if ($response instanceof Message) {
            return $response->messageId;
        }

        if (is_array($response) && isset($response['message_id'])) {
            return (int)$response['message_id'];
        }

        return null;
    }

    /**
     * Извлекает дату сообщения из ответа
     *
     * @param Message|array<string, mixed>|bool|null $response Ответ от API
     * @return string|null
     */
    private function extractTelegramDate(Message|array|bool|null $response): ?string
    {
        if ($response instanceof Message) {
            return date('Y-m-d H:i:s', $response->date);
        }

        if (is_array($response) && isset($response['date'])) {
            return date('Y-m-d H:i:s', (int)$response['date']);
        }

        return null;
    }

    /**
     * Определяет тип сообщения по названию метода API
     *
     * @param string $method Название метода
     * @return string
     */
    private function detectMessageType(string $method): string
    {
        return match ($method) {
            'sendMessage' => 'text',
            'sendPhoto' => 'photo',
            'sendVideo' => 'video',
            'sendAudio' => 'audio',
            'sendDocument' => 'document',
            'sendVoice' => 'voice',
            'sendVideoNote' => 'video_note',
            'sendLocation' => 'location',
            'sendVenue' => 'venue',
            'sendContact' => 'contact',
            'sendPoll' => 'poll',
            'sendDice' => 'dice',
            'sendSticker' => 'sticker',
            'sendAnimation' => 'animation',
            'editMessageText' => 'edit_text',
            'editMessageCaption' => 'edit_caption',
            'editMessageReplyMarkup' => 'edit_markup',
            'deleteMessage' => 'delete',
            'answerCallbackQuery' => 'callback_answer',
            default => 'unknown',
        };
    }

    /**
     * Определяет тип входящего сообщения
     *
     * @param Message $message Объект сообщения
     * @return string
     */
    private function detectIncomingMessageType(Message $message): string
    {
        if ($message->text) {
            return 'text';
        }
        if ($message->photo) {
            return 'photo';
        }
        if ($message->video) {
            return 'video';
        }
        if ($message->audio) {
            return 'audio';
        }
        if ($message->voice) {
            return 'voice';
        }
        if ($message->document) {
            return 'document';
        }
        if ($message->sticker) {
            return 'sticker';
        }
        if ($message->animation) {
            return 'animation';
        }
        if ($message->location) {
            return 'location';
        }
        if ($message->contact) {
            return 'contact';
        }
        if ($message->poll) {
            return 'poll';
        }

        return 'unknown';
    }

    /**
     * Получает топ пользователей по активности
     *
     * @param int $limit Количество пользователей (по умолчанию 10)
     * @param int|null $days Период в днях (null = за всё время)
     * @return array<array{user_id: int, message_count: int, last_activity: string}> Топ пользователей
     */
    public function getTopUsers(int $limit = 10, ?int $days = null): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        try {
            $where = [];
            $params = [];

            if ($days !== null) {
                $where[] = 'created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)';
                $params[] = $days;
            }

            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            $query = "SELECT 
                user_id,
                COUNT(*) as message_count,
                MAX(created_at) as last_activity
                FROM " . self::TABLE_NAME . " 
                {$whereClause}
                GROUP BY user_id
                ORDER BY message_count DESC
                LIMIT ?";

            $params[] = $limit;

            $result = $this->db->query($query, $params);

            $this->logger?->debug('Получен топ пользователей', [
                'count' => count($result),
                'limit' => $limit,
            ]);

            return array_map(function($row) {
                return [
                    'user_id' => (int)$row['user_id'],
                    'message_count' => (int)$row['message_count'],
                    'last_activity' => $row['last_activity'],
                ];
            }, $result);
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка получения топа пользователей', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Получает статистику по времени
     *
     * @param string $period Период: 'hour', 'day', 'week', 'month' (по умолчанию 'day')
     * @param int $limit Количество периодов (по умолчанию 30)
     * @return array<array{period: string, count: int, incoming: int, outgoing: int}> Статистика по периодам
     */
    public function getTimeStatistics(string $period = 'day', int $limit = 30): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        try {
            $formatMap = [
                'hour' => '%Y-%m-%d %H:00:00',
                'day' => '%Y-%m-%d',
                'week' => '%Y-%u',
                'month' => '%Y-%m',
            ];

            if (!isset($formatMap[$period])) {
                throw new \InvalidArgumentException("Неподдерживаемый период: {$period}");
            }

            $format = $formatMap[$period];

            $query = "SELECT 
                DATE_FORMAT(created_at, ?) as period,
                COUNT(*) as count,
                SUM(CASE WHEN direction = 'incoming' THEN 1 ELSE 0 END) as incoming,
                SUM(CASE WHEN direction = 'outgoing' THEN 1 ELSE 0 END) as outgoing
                FROM " . self::TABLE_NAME . " 
                GROUP BY period
                ORDER BY period DESC
                LIMIT ?";

            $result = $this->db->query($query, [$format, $limit]);

            $this->logger?->debug('Получена статистика по времени', [
                'period' => $period,
                'count' => count($result),
            ]);

            return array_map(function($row) {
                return [
                    'period' => $row['period'],
                    'count' => (int)$row['count'],
                    'incoming' => (int)$row['incoming'],
                    'outgoing' => (int)$row['outgoing'],
                ];
            }, $result);
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка получения статистики по времени', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Получает статистику ошибок
     *
     * @param int|null $days Период в днях (null = за всё время)
     * @return array<array{error_code: int, error_message: string, count: int, last_occurrence: string}> Статистика ошибок
     */
    public function getErrorStatistics(?int $days = null): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        try {
            $where = ['success = 0'];
            $params = [];

            if ($days !== null) {
                $where[] = 'created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)';
                $params[] = $days;
            }

            $whereClause = 'WHERE ' . implode(' AND ', $where);

            $query = "SELECT 
                error_code,
                error_message,
                COUNT(*) as count,
                MAX(created_at) as last_occurrence
                FROM " . self::TABLE_NAME . " 
                {$whereClause}
                GROUP BY error_code, error_message
                ORDER BY count DESC";

            $result = $this->db->query($query, $params);

            $this->logger?->debug('Получена статистика ошибок', [
                'count' => count($result),
            ]);

            return array_map(function($row) {
                return [
                    'error_code' => (int)$row['error_code'],
                    'error_message' => $row['error_message'],
                    'count' => (int)$row['count'],
                    'last_occurrence' => $row['last_occurrence'],
                ];
            }, $result);
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка получения статистики ошибок', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Получает подробную статистику по чату
     *
     * @param string|int $chatId ID чата
     * @return array{total: int, incoming: int, outgoing: int, by_type: array, by_day: array, errors: int} Подробная статистика
     */
    public function getChatStatistics(string|int $chatId): array
    {
        if (!$this->isEnabled()) {
            return [
                'total' => 0,
                'incoming' => 0,
                'outgoing' => 0,
                'by_type' => [],
                'by_day' => [],
                'errors' => 0,
            ];
        }

        try {
            // Общая статистика
            $general = $this->getStatistics($chatId);

            // Статистика по дням (последние 7 дней)
            $timeStats = $this->getTimeStatistics('day', 7);

            // Статистика ошибок для чата
            $errorQuery = "SELECT COUNT(*) as count 
                FROM " . self::TABLE_NAME . " 
                WHERE chat_id = ? AND success = 0";
            $errorResult = $this->db->queryOne($errorQuery, [$chatId]);
            $errors = (int)($errorResult['count'] ?? 0);

            return [
                'total' => $general['total'],
                'incoming' => $general['incoming'],
                'outgoing' => $general['outgoing'],
                'by_type' => $general['by_type'],
                'by_day' => $timeStats,
                'errors' => $errors,
            ];
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка получения статистики чата', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return [
                'total' => 0,
                'incoming' => 0,
                'outgoing' => 0,
                'by_type' => [],
                'by_day' => [],
                'errors' => 0,
            ];
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Core;

use App\Component\Logger;
use App\Component\MySQL;

/**
 * Менеджер для управления состояниями диалогов с пользователями
 * 
 * Обеспечивает:
 * - Хранение состояния диалога для каждого пользователя
 * - Многошаговые диалоги с сохранением данных между шагами
 * - Тайм-ауты для автоматического сброса устаревших диалогов
 * - Возможность удаления сообщений после обработки
 * - Хранение данных пользователей (id, first_name, username)
 */
class ConversationManager
{
    /**
     * Имя таблицы для хранения состояний диалогов
     */
    private const TABLE_CONVERSATIONS = 'telegram_bot_conversations';

    /**
     * Имя таблицы для хранения данных пользователей
     */
    private const TABLE_USERS = 'telegram_bot_users';

    /**
     * Тайм-аут для устаревших диалогов (в секундах)
     */
    private const DEFAULT_TIMEOUT = 3600; // 1 час

    /**
     * @param MySQL $db Подключение к БД
     * @param Logger|null $logger Логгер
     * @param array{
     *     enabled?: bool,
     *     timeout?: int,
     *     auto_create_tables?: bool
     * } $config Конфигурация менеджера
     */
    public function __construct(
        private readonly MySQL $db,
        private readonly ?Logger $logger = null,
        private readonly array $config = []
    ) {
        $enabled = $this->config['enabled'] ?? false;
        $autoCreateTables = $this->config['auto_create_tables'] ?? true;

        if ($enabled && $autoCreateTables) {
            $this->createTablesIfNotExist();
        }
    }

    /**
     * Проверяет, включен ли менеджер диалогов
     *
     * @return bool True если менеджер активен
     */
    public function isEnabled(): bool
    {
        return (bool)($this->config['enabled'] ?? false);
    }

    /**
     * Сохраняет или обновляет данные пользователя
     *
     * @param int $userId ID пользователя в Telegram
     * @param string|null $firstName Имя пользователя
     * @param string|null $username Username пользователя
     * @param string|null $lastName Фамилия пользователя
     * @return bool True при успешном сохранении
     */
    public function saveUser(
        int $userId,
        ?string $firstName = null,
        ?string $username = null,
        ?string $lastName = null
    ): bool {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            // Проверяем существование пользователя
            $existing = $this->db->queryOne(
                "SELECT id FROM " . self::TABLE_USERS . " WHERE user_id = ?",
                [$userId]
            );

            $data = [
                'user_id' => $userId,
                'first_name' => $firstName,
                'username' => $username,
                'last_name' => $lastName,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if ($existing) {
                // Обновляем существующего пользователя
                $this->db->update(
                    self::TABLE_USERS,
                    $data,
                    ['id' => $existing['id']]
                );
            } else {
                // Создаем нового пользователя
                $data['created_at'] = date('Y-m-d H:i:s');
                $this->db->insert(self::TABLE_USERS, $data);
            }

            $this->logger?->debug('Данные пользователя сохранены', [
                'user_id' => $userId,
                'username' => $username,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка сохранения пользователя', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Получает данные пользователя
     *
     * @param int $userId ID пользователя
     * @return array{user_id: int, first_name: ?string, username: ?string, last_name: ?string}|null
     */
    public function getUser(int $userId): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        try {
            $user = $this->db->queryOne(
                "SELECT user_id, first_name, username, last_name, created_at, updated_at 
                FROM " . self::TABLE_USERS . " 
                WHERE user_id = ?",
                [$userId]
            );

            return $user ?: null;
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка получения данных пользователя', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Начинает новый диалог для пользователя
     *
     * @param int $chatId ID чата
     * @param int $userId ID пользователя
     * @param string $state Состояние диалога (например, 'awaiting_name')
     * @param array<string, mixed> $data Дополнительные данные диалога
     * @param int|null $messageId ID сообщения с кнопками для последующего удаления
     * @return int|null ID созданного диалога
     */
    public function startConversation(
        int $chatId,
        int $userId,
        string $state,
        array $data = [],
        ?int $messageId = null
    ): ?int {
        if (!$this->isEnabled()) {
            return null;
        }

        try {
            // Завершаем все активные диалоги пользователя в этом чате
            $this->endConversation($chatId, $userId);

            $conversationData = [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'state' => $state,
                'data' => json_encode($data),
                'message_id' => $messageId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'expires_at' => date('Y-m-d H:i:s', time() + ($this->config['timeout'] ?? self::DEFAULT_TIMEOUT)),
            ];

            $id = $this->db->insert(self::TABLE_CONVERSATIONS, $conversationData);

            $this->logger?->info('Начат новый диалог', [
                'id' => $id,
                'chat_id' => $chatId,
                'user_id' => $userId,
                'state' => $state,
            ]);

            return $id;
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка создания диалога', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Получает текущее состояние диалога пользователя
     *
     * @param int $chatId ID чата
     * @param int $userId ID пользователя
     * @return array{id: int, state: string, data: array, message_id: ?int, created_at: string}|null
     */
    public function getConversation(int $chatId, int $userId): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        try {
            $conversation = $this->db->queryOne(
                "SELECT id, chat_id, user_id, state, data, message_id, created_at, updated_at, expires_at 
                FROM " . self::TABLE_CONVERSATIONS . " 
                WHERE chat_id = ? AND user_id = ? AND expires_at > NOW()
                ORDER BY created_at DESC
                LIMIT 1",
                [$chatId, $userId]
            );

            if (!$conversation) {
                return null;
            }

            // Декодируем JSON данные
            $conversation['data'] = json_decode($conversation['data'], true) ?? [];

            return $conversation;
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка получения диалога', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Обновляет состояние диалога
     *
     * @param int $chatId ID чата
     * @param int $userId ID пользователя
     * @param string $newState Новое состояние
     * @param array<string, mixed> $additionalData Дополнительные данные для слияния
     * @param int|null $newMessageId Новый ID сообщения с кнопками
     * @return bool True при успешном обновлении
     */
    public function updateConversation(
        int $chatId,
        int $userId,
        string $newState,
        array $additionalData = [],
        ?int $newMessageId = null
    ): bool {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $conversation = $this->getConversation($chatId, $userId);

            if (!$conversation) {
                $this->logger?->warning('Попытка обновить несуществующий диалог', [
                    'chat_id' => $chatId,
                    'user_id' => $userId,
                ]);
                return false;
            }

            // Объединяем существующие данные с новыми
            $mergedData = array_merge($conversation['data'], $additionalData);

            $updateData = [
                'state' => $newState,
                'data' => json_encode($mergedData),
                'updated_at' => date('Y-m-d H:i:s'),
                'expires_at' => date('Y-m-d H:i:s', time() + ($this->config['timeout'] ?? self::DEFAULT_TIMEOUT)),
            ];

            if ($newMessageId !== null) {
                $updateData['message_id'] = $newMessageId;
            }

            $this->db->update(
                self::TABLE_CONVERSATIONS,
                $updateData,
                ['id' => $conversation['id']]
            );

            $this->logger?->debug('Диалог обновлен', [
                'id' => $conversation['id'],
                'chat_id' => $chatId,
                'user_id' => $userId,
                'new_state' => $newState,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка обновления диалога', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Завершает диалог пользователя
     *
     * @param int $chatId ID чата
     * @param int $userId ID пользователя
     * @return bool True при успешном завершении
     */
    public function endConversation(int $chatId, int $userId): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $affected = $this->db->execute(
                "DELETE FROM " . self::TABLE_CONVERSATIONS . " 
                WHERE chat_id = ? AND user_id = ?",
                [$chatId, $userId]
            );

            if ($affected > 0) {
                $this->logger?->info('Диалог завершен', [
                    'chat_id' => $chatId,
                    'user_id' => $userId,
                    'deleted' => $affected,
                ]);
            }

            return true;
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка завершения диалога', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Получает ID сообщения с кнопками для удаления
     *
     * @param int $chatId ID чата
     * @param int $userId ID пользователя
     * @return int|null ID сообщения или null
     */
    public function getMessageIdForDeletion(int $chatId, int $userId): ?int
    {
        if (!$this->isEnabled()) {
            return null;
        }

        try {
            $conversation = $this->getConversation($chatId, $userId);
            return $conversation['message_id'] ?? null;
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка получения ID сообщения', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Очищает устаревшие диалоги
     *
     * @return int Количество удаленных диалогов
     */
    public function cleanupExpiredConversations(): int
    {
        if (!$this->isEnabled()) {
            return 0;
        }

        try {
            $deleted = $this->db->execute(
                "DELETE FROM " . self::TABLE_CONVERSATIONS . " WHERE expires_at <= NOW()"
            );

            if ($deleted > 0) {
                $this->logger?->info('Очищены устаревшие диалоги', [
                    'deleted' => $deleted,
                ]);
            }

            return $deleted;
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка очистки устаревших диалогов', [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Получает статистику по активным диалогам
     *
     * @return array{total: int, by_state: array<string, int>}
     */
    public function getStatistics(): array
    {
        if (!$this->isEnabled()) {
            return ['total' => 0, 'by_state' => []];
        }

        try {
            // Общее количество активных диалогов
            $total = $this->db->queryOne(
                "SELECT COUNT(*) as cnt FROM " . self::TABLE_CONVERSATIONS . " 
                WHERE expires_at > NOW()"
            )['cnt'] ?? 0;

            // По состояниям
            $byState = $this->db->query(
                "SELECT state, COUNT(*) as count 
                FROM " . self::TABLE_CONVERSATIONS . " 
                WHERE expires_at > NOW()
                GROUP BY state"
            );

            $byStateMap = [];
            foreach ($byState as $row) {
                $byStateMap[$row['state']] = (int)$row['count'];
            }

            return [
                'total' => (int)$total,
                'by_state' => $byStateMap,
            ];
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка получения статистики диалогов', [
                'error' => $e->getMessage(),
            ]);

            return ['total' => 0, 'by_state' => []];
        }
    }

    /**
     * Создает таблицы для хранения диалогов и пользователей
     *
     * @return bool True при успешном создании
     */
    private function createTablesIfNotExist(): bool
    {
        try {
            // Создание таблицы пользователей
            $usersTableExists = $this->db->queryOne(
                "SHOW TABLES LIKE ?",
                [self::TABLE_USERS]
            );

            if (empty($usersTableExists)) {
                $sqlUsers = "CREATE TABLE " . self::TABLE_USERS . " (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    user_id BIGINT NOT NULL,
                    first_name VARCHAR(255) DEFAULT NULL,
                    username VARCHAR(255) DEFAULT NULL,
                    last_name VARCHAR(255) DEFAULT NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    
                    PRIMARY KEY (id),
                    UNIQUE KEY idx_user_id (user_id),
                    INDEX idx_username (username)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
                COMMENT='Данные пользователей Telegram бота'";

                $this->db->execute($sqlUsers);

                $this->logger?->info('Таблица пользователей создана', ['table' => self::TABLE_USERS]);
            }

            // Создание таблицы диалогов
            $conversationsTableExists = $this->db->queryOne(
                "SHOW TABLES LIKE ?",
                [self::TABLE_CONVERSATIONS]
            );

            if (empty($conversationsTableExists)) {
                $sqlConversations = "CREATE TABLE " . self::TABLE_CONVERSATIONS . " (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    chat_id BIGINT NOT NULL,
                    user_id BIGINT NOT NULL,
                    state VARCHAR(100) NOT NULL,
                    data JSON DEFAULT NULL,
                    message_id BIGINT UNSIGNED DEFAULT NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    expires_at DATETIME NOT NULL,
                    
                    PRIMARY KEY (id),
                    INDEX idx_chat_user (chat_id, user_id),
                    INDEX idx_expires (expires_at),
                    INDEX idx_state (state)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
                COMMENT='Состояния диалогов с пользователями Telegram бота'";

                $this->db->execute($sqlConversations);

                $this->logger?->info('Таблица диалогов создана', ['table' => self::TABLE_CONVERSATIONS]);
            }

            return true;
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка создания таблиц', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}

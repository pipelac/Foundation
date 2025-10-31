<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Core;

use App\Component\Logger;
use App\Component\TelegramBot\Entities\Message;
use App\Component\TelegramBot\Entities\Update;

/**
 * Middleware для автоматической проверки доступа к командам
 * 
 * Оборачивает обработчики команд и проверяет права доступа
 * перед их выполнением. При отказе в доступе отправляет
 * соответствующее сообщение пользователю.
 */
class AccessControlMiddleware
{
    /**
     * @param AccessControl $accessControl Система контроля доступа
     * @param TelegramAPI $api API клиент для отправки сообщений
     * @param Logger|null $logger Логгер
     */
    public function __construct(
        private readonly AccessControl $accessControl,
        private readonly TelegramAPI $api,
        private readonly ?Logger $logger = null,
    ) {
    }

    /**
     * Оборачивает callback обработчика команды с проверкой доступа
     *
     * @param string $command Команда для проверки
     * @param callable $callback Оригинальный обработчик
     * @return callable Обернутый обработчик
     */
    public function wrapCommandHandler(string $command, callable $callback): callable
    {
        return function (Message $message, ...$args) use ($command, $callback) {
            // Если контроль доступа выключен - выполняем обработчик
            if (!$this->accessControl->isEnabled()) {
                return $callback($message, ...$args);
            }

            $chatId = $message->chat->id;
            $userId = $message->from?->id ?? $chatId;

            // Проверяем доступ
            if (!$this->accessControl->checkAccess($userId, $command)) {
                $this->handleAccessDenied($message, $command);
                return null;
            }

            // Выполняем оригинальный обработчик
            return $callback($message, ...$args);
        };
    }

    /**
     * Проверяет доступ пользователя к команде из Update
     *
     * @param Update $update Обновление
     * @param string $command Команда
     * @return bool True если доступ разрешен
     */
    public function checkAccessFromUpdate(Update $update, string $command): bool
    {
        if (!$this->accessControl->isEnabled()) {
            return true;
        }

        $message = $update->getMessage();
        if ($message === null || $message->from === null) {
            return false;
        }

        return $this->accessControl->checkAccess($message->from->id, $command);
    }

    /**
     * Обрабатывает отказ в доступе
     *
     * @param Message $message Сообщение пользователя
     * @param string $command Команда, к которой нет доступа
     */
    private function handleAccessDenied(Message $message, string $command): void
    {
        $userId = $message->from?->id ?? $message->chat->id;
        $username = $message->from?->username ?? 'unknown';

        $this->logger?->warning('Доступ к команде запрещен', [
            'user_id' => $userId,
            'username' => $username,
            'command' => $command,
            'role' => $this->accessControl->getUserRole($userId),
        ]);

        try {
            $deniedMessage = $this->accessControl->getAccessDeniedMessage();
            $this->api->sendMessage(
                $message->chat->id,
                $deniedMessage,
                ['reply_to_message_id' => $message->messageId]
            );
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка отправки сообщения об отказе в доступе', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Получает систему контроля доступа
     *
     * @return AccessControl
     */
    public function getAccessControl(): AccessControl
    {
        return $this->accessControl;
    }

    /**
     * Проверяет доступ и отправляет сообщение об отказе если нужно
     *
     * @param Message $message Сообщение пользователя
     * @param string $command Команда
     * @return bool True если доступ разрешен
     */
    public function checkAndNotify(Message $message, string $command): bool
    {
        if (!$this->accessControl->isEnabled()) {
            return true;
        }

        $userId = $message->from?->id ?? $message->chat->id;

        if (!$this->accessControl->checkAccess($userId, $command)) {
            $this->handleAccessDenied($message, $command);
            return false;
        }

        return true;
    }

    /**
     * Получает список доступных команд для пользователя из сообщения
     *
     * @param Message $message Сообщение пользователя
     * @return array<string> Массив доступных команд
     */
    public function getAllowedCommandsForMessage(Message $message): array
    {
        if (!$this->accessControl->isEnabled()) {
            return [];
        }

        $userId = $message->from?->id ?? $message->chat->id;
        return $this->accessControl->getAllowedCommands($userId);
    }
}

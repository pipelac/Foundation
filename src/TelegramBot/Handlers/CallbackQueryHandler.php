<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Handlers;

use App\Component\Logger;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Entities\CallbackQuery;
use App\Component\TelegramBot\Entities\Message;
use App\Component\TelegramBot\Entities\Update;
use App\Component\TelegramBot\Utils\Parser;

/**
 * Обработчик нажатий на inline кнопки (callback query)
 * 
 * Предоставляет методы для обработки callback запросов,
 * ответов на них и редактирования сообщений с кнопками
 */
class CallbackQueryHandler
{
    /**
     * @param TelegramAPI $api API клиент
     * @param Logger|null $logger Логгер
     */
    public function __construct(
        private readonly TelegramAPI $api,
        private readonly ?Logger $logger = null,
    ) {
    }

    /**
     * Обрабатывает callback query из обновления
     *
     * @param Update $update Обновление
     * @param callable $callback Callback: function(CallbackQuery $query): void
     */
    public function handle(Update $update, callable $callback): void
    {
        if (!$update->isCallbackQuery()) {
            return;
        }

        $query = $update->callbackQuery;

        try {
            $this->logger?->debug('Обработка callback query', [
                'callback_id' => $query->id,
                'data' => $query->data,
                'from' => $query->from->username ?? $query->from->id,
            ]);

            $callback($query);
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка обработки callback query', [
                'callback_id' => $query->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Обрабатывает callback query с определенным действием
     *
     * @param Update $update Обновление
     * @param string $action Действие для фильтрации
     * @param callable $callback Callback: function(CallbackQuery $query, array $params): void
     */
    public function handleAction(Update $update, string $action, callable $callback): void
    {
        if (!$update->isCallbackQuery()) {
            return;
        }

        $query = $update->callbackQuery;

        if (!$query->hasData()) {
            return;
        }

        $parsed = Parser::parseCallbackData($query->data);

        if (!isset($parsed['action']) || $parsed['action'] !== $action) {
            return;
        }

        try {
            $this->logger?->debug('Обработка callback action', [
                'callback_id' => $query->id,
                'action' => $action,
                'params' => $parsed,
            ]);

            $callback($query, $parsed);
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка обработки callback action', [
                'callback_id' => $query->id,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Отвечает на callback query без уведомления
     *
     * @param CallbackQuery $query Callback query
     * @return bool True при успешном ответе
     */
    public function answer(CallbackQuery $query): bool
    {
        return $this->api->answerCallbackQuery($query->id);
    }

    /**
     * Отвечает на callback query с текстом уведомления
     *
     * @param CallbackQuery $query Callback query
     * @param string $text Текст уведомления
     * @param bool $showAlert Показать alert вместо toast
     * @return bool True при успешном ответе
     */
    public function answerWithText(CallbackQuery $query, string $text, bool $showAlert = false): bool
    {
        return $this->api->answerCallbackQuery($query->id, [
            'text' => $text,
            'show_alert' => $showAlert,
        ]);
    }

    /**
     * Отвечает на callback query с alert
     *
     * @param CallbackQuery $query Callback query
     * @param string $text Текст alert
     * @return bool True при успешном ответе
     */
    public function answerWithAlert(CallbackQuery $query, string $text): bool
    {
        return $this->answerWithText($query, $text, true);
    }

    /**
     * Редактирует текст сообщения с callback query
     *
     * @param CallbackQuery $query Callback query
     * @param string $text Новый текст
     * @param array<string, mixed> $options Дополнительные параметры
     * @return Message|null Отредактированное сообщение или null
     */
    public function editText(CallbackQuery $query, string $text, array $options = []): ?Message
    {
        if (!$query->hasMessage()) {
            return null;
        }

        try {
            return $this->api->editMessageText(
                $query->getChatId(),
                $query->getMessageId(),
                $text,
                $options
            );
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка редактирования текста сообщения', [
                'callback_id' => $query->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Редактирует клавиатуру сообщения с callback query
     *
     * @param CallbackQuery $query Callback query
     * @param array<string, mixed> $replyMarkup Новая клавиатура
     * @return Message|null Отредактированное сообщение или null
     */
    public function editKeyboard(CallbackQuery $query, array $replyMarkup): ?Message
    {
        if (!$query->hasMessage()) {
            return null;
        }

        try {
            return $this->api->editMessageReplyMarkup(
                $query->getChatId(),
                $query->getMessageId(),
                $replyMarkup
            );
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка редактирования клавиатуры', [
                'callback_id' => $query->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Удаляет клавиатуру из сообщения
     *
     * @param CallbackQuery $query Callback query
     * @return Message|null Отредактированное сообщение или null
     */
    public function removeKeyboard(CallbackQuery $query): ?Message
    {
        return $this->editKeyboard($query, ['inline_keyboard' => []]);
    }

    /**
     * Отвечает на callback и редактирует текст сообщения
     *
     * @param CallbackQuery $query Callback query
     * @param string $text Новый текст
     * @param array<string, mixed> $options Дополнительные параметры
     * @return Message|null Отредактированное сообщение или null
     */
    public function answerAndEdit(CallbackQuery $query, string $text, array $options = []): ?Message
    {
        $this->answer($query);
        return $this->editText($query, $text, $options);
    }

    /**
     * Отвечает с уведомлением и редактирует текст сообщения
     *
     * @param CallbackQuery $query Callback query
     * @param string $notificationText Текст уведомления
     * @param string $messageText Новый текст сообщения
     * @param array<string, mixed> $options Дополнительные параметры
     * @return Message|null Отредактированное сообщение или null
     */
    public function answerWithTextAndEdit(
        CallbackQuery $query,
        string $notificationText,
        string $messageText,
        array $options = []
    ): ?Message {
        $this->answerWithText($query, $notificationText);
        return $this->editText($query, $messageText, $options);
    }

    /**
     * Отправляет новое сообщение в чат callback query
     *
     * @param CallbackQuery $query Callback query
     * @param string $text Текст сообщения
     * @param array<string, mixed> $options Дополнительные параметры
     * @return Message|null Отправленное сообщение или null
     */
    public function sendMessage(CallbackQuery $query, string $text, array $options = []): ?Message
    {
        $chatId = $query->getChatId();

        if ($chatId === null) {
            return null;
        }

        try {
            return $this->api->sendMessage($chatId, $text, $options);
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка отправки сообщения из callback', [
                'callback_id' => $query->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Извлекает данные из callback query
     *
     * @param CallbackQuery $query Callback query
     * @return array<string, string> Распарсенные данные
     */
    public function parseData(CallbackQuery $query): array
    {
        if (!$query->hasData()) {
            return [];
        }

        return Parser::parseCallbackData($query->data);
    }
}

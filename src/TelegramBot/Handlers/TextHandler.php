<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Handlers;

use App\Component\Logger;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Entities\Message;
use App\Component\TelegramBot\Entities\Update;
use App\Component\TelegramBot\Utils\Parser;

/**
 * Обработчик текста и команд
 * 
 * Специализированный обработчик для парсинга и обработки
 * текстовых сообщений, команд, аргументов и упоминаний
 */
class TextHandler
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
     * Обрабатывает команду из обновления
     *
     * @param Update $update Обновление
     * @param string $command Команда для обработки (без /)
     * @param callable $callback Callback: function(Message $message, array $args): void
     */
    public function handleCommand(Update $update, string $command, callable $callback): void
    {
        $message = $update->getMessage();

        if ($message === null || !$message->isText()) {
            return;
        }

        $parsed = Parser::parseCommand($message->text);

        if ($parsed['command'] !== $command) {
            return;
        }

        try {
            $this->logger?->debug('Обработка команды', [
                'command' => $command,
                'args' => $parsed['args'],
                'message_id' => $message->messageId,
            ]);

            $callback($message, $parsed['args']);
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка обработки команды', [
                'command' => $command,
                'message_id' => $message->messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Обрабатывает любую команду
     *
     * @param Update $update Обновление
     * @param callable $callback Callback: function(Message $message, string $command, array $args): void
     */
    public function handleAnyCommand(Update $update, callable $callback): void
    {
        $message = $update->getMessage();

        if ($message === null || !$message->isText()) {
            return;
        }

        if (!Parser::isCommand($message->text)) {
            return;
        }

        $parsed = Parser::parseCommand($message->text);

        if ($parsed['command'] === null) {
            return;
        }

        try {
            $this->logger?->debug('Обработка команды (любая)', [
                'command' => $parsed['command'],
                'args' => $parsed['args'],
                'message_id' => $message->messageId,
            ]);

            $callback($message, $parsed['command'], $parsed['args']);
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка обработки команды', [
                'command' => $parsed['command'],
                'message_id' => $message->messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Обрабатывает текст, содержащий определенную подстроку
     *
     * @param Update $update Обновление
     * @param string $substring Подстрока для поиска
     * @param callable $callback Callback: function(Message $message, string $text): void
     * @param bool $caseSensitive Учитывать регистр
     */
    public function handleContains(Update $update, string $substring, callable $callback, bool $caseSensitive = false): void
    {
        $message = $update->getMessage();

        if ($message === null || !$message->isText()) {
            return;
        }

        $text = $message->text;
        $needle = $substring;

        if (!$caseSensitive) {
            $text = mb_strtolower($text, 'UTF-8');
            $needle = mb_strtolower($needle, 'UTF-8');
        }

        if (!str_contains($text, $needle)) {
            return;
        }

        try {
            $this->logger?->debug('Обработка текста с подстрокой', [
                'substring' => $substring,
                'message_id' => $message->messageId,
            ]);

            $callback($message, $message->text);
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка обработки текста с подстрокой', [
                'substring' => $substring,
                'message_id' => $message->messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Обрабатывает текст, совпадающий с регулярным выражением
     *
     * @param Update $update Обновление
     * @param string $pattern Регулярное выражение
     * @param callable $callback Callback: function(Message $message, array $matches): void
     */
    public function handlePattern(Update $update, string $pattern, callable $callback): void
    {
        $message = $update->getMessage();

        if ($message === null || !$message->isText()) {
            return;
        }

        if (!preg_match($pattern, $message->text, $matches)) {
            return;
        }

        try {
            $this->logger?->debug('Обработка текста по паттерну', [
                'pattern' => $pattern,
                'message_id' => $message->messageId,
            ]);

            $callback($message, $matches);
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка обработки текста по паттерну', [
                'pattern' => $pattern,
                'message_id' => $message->messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Обрабатывает обычный текст (не команды)
     *
     * @param Update $update Обновление
     * @param callable $callback Callback: function(Message $message, string $text): void
     */
    public function handlePlainText(Update $update, callable $callback): void
    {
        $message = $update->getMessage();

        if ($message === null || !$message->isText()) {
            return;
        }

        if (Parser::isCommand($message->text)) {
            return;
        }

        try {
            $this->logger?->debug('Обработка обычного текста', [
                'message_id' => $message->messageId,
                'text_length' => mb_strlen($message->text, 'UTF-8'),
            ]);

            $callback($message, $message->text);
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка обработки обычного текста', [
                'message_id' => $message->messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Извлекает упоминания из сообщения
     *
     * @param Message $message Сообщение
     * @return array<string> Массив username без @
     */
    public function extractMentions(Message $message): array
    {
        if (!$message->isText()) {
            return [];
        }

        return Parser::extractMentions($message->text);
    }

    /**
     * Извлекает хештеги из сообщения
     *
     * @param Message $message Сообщение
     * @return array<string> Массив хештегов без #
     */
    public function extractHashtags(Message $message): array
    {
        if (!$message->isText()) {
            return [];
        }

        return Parser::extractHashtags($message->text);
    }

    /**
     * Извлекает URL из сообщения
     *
     * @param Message $message Сообщение
     * @return array<string> Массив найденных URL
     */
    public function extractUrls(Message $message): array
    {
        if (!$message->isText()) {
            return [];
        }

        return Parser::extractUrls($message->text);
    }

    /**
     * Проверяет, является ли сообщение командой
     *
     * @param Message $message Сообщение
     * @return bool True если это команда
     */
    public function isCommand(Message $message): bool
    {
        if (!$message->isText()) {
            return false;
        }

        return Parser::isCommand($message->text);
    }

    /**
     * Извлекает команду из сообщения
     *
     * @param Message $message Сообщение
     * @return string|null Команда без слэша или null
     */
    public function getCommand(Message $message): ?string
    {
        if (!$message->isText()) {
            return null;
        }

        return Parser::extractCommand($message->text);
    }

    /**
     * Извлекает аргументы команды
     *
     * @param Message $message Сообщение
     * @return array<string> Массив аргументов
     */
    public function getArguments(Message $message): array
    {
        if (!$message->isText()) {
            return [];
        }

        return Parser::extractArguments($message->text);
    }
}

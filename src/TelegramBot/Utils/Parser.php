<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Utils;

/**
 * Парсер текста и данных Telegram
 * 
 * Предоставляет методы для парсинга команд, аргументов,
 * callback data и других текстовых данных
 */
class Parser
{
    /**
     * Парсит команду из текста сообщения
     *
     * @param string $text Текст сообщения
     * @return array{command: string|null, args: array<string>} Команда и аргументы
     */
    public static function parseCommand(string $text): array
    {
        $text = trim($text);

        if (!str_starts_with($text, '/')) {
            return ['command' => null, 'args' => []];
        }

        $parts = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        if (empty($parts)) {
            return ['command' => null, 'args' => []];
        }

        $command = ltrim($parts[0], '/');
        
        if (str_contains($command, '@')) {
            $command = explode('@', $command)[0];
        }

        $args = array_slice($parts, 1);

        return [
            'command' => $command,
            'args' => $args,
        ];
    }

    /**
     * Извлекает команду из текста
     *
     * @param string $text Текст сообщения
     * @return string|null Команда без слэша и бот-имени
     */
    public static function extractCommand(string $text): ?string
    {
        $parsed = self::parseCommand($text);
        return $parsed['command'];
    }

    /**
     * Извлекает аргументы команды
     *
     * @param string $text Текст сообщения
     * @return array<string> Массив аргументов
     */
    public static function extractArguments(string $text): array
    {
        $parsed = self::parseCommand($text);
        return $parsed['args'];
    }

    /**
     * Проверяет, является ли текст командой
     *
     * @param string $text Текст для проверки
     * @return bool True если текст начинается с /
     */
    public static function isCommand(string $text): bool
    {
        return str_starts_with(trim($text), '/');
    }

    /**
     * Парсит callback data в структурированный формат
     * 
     * Поддерживает форматы:
     * - "action" -> ['action' => 'action']
     * - "action:value" -> ['action' => 'action', 'value' => 'value']
     * - "action:key1=val1,key2=val2" -> ['action' => 'action', 'key1' => 'val1', 'key2' => 'val2']
     *
     * @param string $callbackData Callback data из кнопки
     * @return array<string, string> Распарсенные данные
     */
    public static function parseCallbackData(string $callbackData): array
    {
        $result = [];

        if (!str_contains($callbackData, ':')) {
            $result['action'] = $callbackData;
            return $result;
        }

        $parts = explode(':', $callbackData, 2);
        $result['action'] = $parts[0];

        if (!isset($parts[1])) {
            return $result;
        }

        if (!str_contains($parts[1], '=')) {
            $result['value'] = $parts[1];
            return $result;
        }

        $pairs = explode(',', $parts[1]);
        foreach ($pairs as $pair) {
            if (str_contains($pair, '=')) {
                [$key, $value] = explode('=', $pair, 2);
                $result[trim($key)] = trim($value);
            }
        }

        return $result;
    }

    /**
     * Строит callback data из массива параметров
     *
     * @param string $action Действие
     * @param array<string, string|int> $params Дополнительные параметры
     * @return string Сформированный callback data
     */
    public static function buildCallbackData(string $action, array $params = []): string
    {
        if (empty($params)) {
            return $action;
        }

        $pairs = [];
        foreach ($params as $key => $value) {
            $pairs[] = $key . '=' . $value;
        }

        return $action . ':' . implode(',', $pairs);
    }

    /**
     * Извлекает упоминания пользователей (@username) из текста
     *
     * @param string $text Текст для парсинга
     * @return array<string> Массив username без @
     */
    public static function extractMentions(string $text): array
    {
        preg_match_all('/@([a-zA-Z0-9_]{5,})/', $text, $matches);
        return $matches[1] ?? [];
    }

    /**
     * Извлекает хештеги (#tag) из текста
     *
     * @param string $text Текст для парсинга
     * @return array<string> Массив хештегов без #
     */
    public static function extractHashtags(string $text): array
    {
        preg_match_all('/#([a-zA-Zа-яА-ЯёЁ0-9_]+)/u', $text, $matches);
        return $matches[1] ?? [];
    }

    /**
     * Извлекает URL из текста
     *
     * @param string $text Текст для парсинга
     * @return array<string> Массив найденных URL
     */
    public static function extractUrls(string $text): array
    {
        preg_match_all(
            '/(https?:\/\/[^\s]+)/i',
            $text,
            $matches
        );
        return $matches[1] ?? [];
    }

    /**
     * Экранирует специальные символы для MarkdownV2
     *
     * @param string $text Текст для экранирования
     * @return string Экранированный текст
     */
    public static function escapeMarkdownV2(string $text): string
    {
        $specialChars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        
        foreach ($specialChars as $char) {
            $text = str_replace($char, '\\' . $char, $text);
        }
        
        return $text;
    }

    /**
     * Экранирует специальные символы для HTML
     *
     * @param string $text Текст для экранирования
     * @return string Экранированный текст
     */
    public static function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Обрезает текст до указанной длины с добавлением многоточия
     *
     * @param string $text Текст для обрезки
     * @param int $maxLength Максимальная длина
     * @param string $suffix Суффикс (по умолчанию '...')
     * @return string Обрезанный текст
     */
    public static function truncate(string $text, int $maxLength, string $suffix = '...'): string
    {
        $length = mb_strlen($text, 'UTF-8');
        
        if ($length <= $maxLength) {
            return $text;
        }

        $suffixLength = mb_strlen($suffix, 'UTF-8');
        $truncateAt = $maxLength - $suffixLength;

        return mb_substr($text, 0, $truncateAt, 'UTF-8') . $suffix;
    }
}

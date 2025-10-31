<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Utils;

use App\Component\TelegramBot\Exceptions\ValidationException;

/**
 * Валидатор параметров Telegram Bot API
 * 
 * Проверяет корректность данных согласно ограничениям Telegram API
 * и выбрасывает исключения при обнаружении ошибок
 */
class Validator
{
    /**
     * Максимальная длина текста сообщения
     */
    private const MAX_TEXT_LENGTH = 4096;

    /**
     * Максимальная длина подписи
     */
    private const MAX_CAPTION_LENGTH = 1024;

    /**
     * Максимальная длина callback_data
     */
    private const MAX_CALLBACK_DATA_LENGTH = 64;

    /**
     * Максимальный размер файла в байтах (50 МБ)
     */
    private const MAX_FILE_SIZE = 52428800;

    /**
     * Минимальное количество вариантов в опросе
     */
    private const MIN_POLL_OPTIONS = 2;

    /**
     * Максимальное количество вариантов в опросе
     */
    private const MAX_POLL_OPTIONS = 10;

    /**
     * Валидирует токен бота
     *
     * @param string $token Токен для проверки
     * @throws ValidationException Если токен некорректен
     */
    public static function validateToken(string $token): void
    {
        $token = trim($token);

        if ($token === '') {
            throw new ValidationException(
                'Токен бота не может быть пустым',
                'token',
                $token
            );
        }

        if (!preg_match('/^\d{7,}:[A-Za-z0-9_-]{30,}$/', $token)) {
            throw new ValidationException(
                'Некорректный формат токена. Ожидается формат: 123456789:ABCdefGHIjklMNOpqrSTUvwxYZ',
                'token',
                $token
            );
        }
    }

    /**
     * Валидирует chat ID
     *
     * @param string|int $chatId Chat ID для проверки
     * @throws ValidationException Если chat ID некорректен
     */
    public static function validateChatId(string|int $chatId): void
    {
        $chatIdStr = (string)$chatId;

        if (trim($chatIdStr) === '') {
            throw new ValidationException(
                'Chat ID не может быть пустым',
                'chat_id',
                $chatId
            );
        }

        if (!preg_match('/^-?\d+$/', $chatIdStr) && !preg_match('/^@[a-zA-Z0-9_]{5,}$/', $chatIdStr)) {
            throw new ValidationException(
                'Chat ID должен быть числом или username в формате @username',
                'chat_id',
                $chatId
            );
        }
    }

    /**
     * Валидирует текст сообщения
     *
     * @param string $text Текст для проверки
     * @throws ValidationException Если текст некорректен
     */
    public static function validateText(string $text): void
    {
        if (trim($text) === '') {
            throw new ValidationException(
                'Текст сообщения не может быть пустым',
                'text',
                $text
            );
        }

        $length = mb_strlen($text, 'UTF-8');
        if ($length > self::MAX_TEXT_LENGTH) {
            throw new ValidationException(
                sprintf(
                    'Текст сообщения превышает максимальную длину %d символов (текущая: %d)',
                    self::MAX_TEXT_LENGTH,
                    $length
                ),
                'text',
                $text
            );
        }
    }

    /**
     * Валидирует подпись к медиа
     *
     * @param string $caption Подпись для проверки
     * @throws ValidationException Если подпись некорректна
     */
    public static function validateCaption(string $caption): void
    {
        $length = mb_strlen($caption, 'UTF-8');
        if ($length > self::MAX_CAPTION_LENGTH) {
            throw new ValidationException(
                sprintf(
                    'Подпись превышает максимальную длину %d символов (текущая: %d)',
                    self::MAX_CAPTION_LENGTH,
                    $length
                ),
                'caption',
                $caption
            );
        }
    }

    /**
     * Валидирует callback_data
     *
     * @param string $data Данные для проверки
     * @throws ValidationException Если данные некорректны
     */
    public static function validateCallbackData(string $data): void
    {
        if (trim($data) === '') {
            throw new ValidationException(
                'Callback data не может быть пустым',
                'callback_data',
                $data
            );
        }

        $length = strlen($data);
        if ($length > self::MAX_CALLBACK_DATA_LENGTH) {
            throw new ValidationException(
                sprintf(
                    'Callback data превышает максимальную длину %d байт (текущая: %d)',
                    self::MAX_CALLBACK_DATA_LENGTH,
                    $length
                ),
                'callback_data',
                $data
            );
        }
    }

    /**
     * Валидирует путь к файлу или URL
     *
     * @param string $file Путь к файлу или URL
     * @throws ValidationException Если файл некорректен
     */
    public static function validateFile(string $file): void
    {
        if (trim($file) === '') {
            throw new ValidationException(
                'Путь к файлу не может быть пустым',
                'file',
                $file
            );
        }

        if (filter_var($file, FILTER_VALIDATE_URL)) {
            return;
        }

        if (!file_exists($file)) {
            throw new ValidationException(
                sprintf('Файл не найден: %s', $file),
                'file',
                $file
            );
        }

        if (!is_readable($file)) {
            throw new ValidationException(
                sprintf('Файл недоступен для чтения: %s', $file),
                'file',
                $file
            );
        }

        $fileSize = filesize($file);
        if ($fileSize > self::MAX_FILE_SIZE) {
            throw new ValidationException(
                sprintf(
                    'Размер файла превышает максимальный %d МБ (текущий: %.2f МБ)',
                    self::MAX_FILE_SIZE / 1024 / 1024,
                    $fileSize / 1024 / 1024
                ),
                'file',
                $file
            );
        }
    }

    /**
     * Валидирует вопрос опроса
     *
     * @param string $question Вопрос для проверки
     * @throws ValidationException Если вопрос некорректен
     */
    public static function validatePollQuestion(string $question): void
    {
        if (trim($question) === '') {
            throw new ValidationException(
                'Вопрос опроса не может быть пустым',
                'question',
                $question
            );
        }

        $length = mb_strlen($question, 'UTF-8');
        if ($length < 1 || $length > 300) {
            throw new ValidationException(
                sprintf('Вопрос опроса должен содержать от 1 до 300 символов (текущая длина: %d)', $length),
                'question',
                $question
            );
        }
    }

    /**
     * Валидирует варианты ответов опроса
     *
     * @param array<string> $options Варианты для проверки
     * @throws ValidationException Если варианты некорректны
     */
    public static function validatePollOptions(array $options): void
    {
        $count = count($options);

        if ($count < self::MIN_POLL_OPTIONS || $count > self::MAX_POLL_OPTIONS) {
            throw new ValidationException(
                sprintf(
                    'Количество вариантов должно быть от %d до %d (текущее: %d)',
                    self::MIN_POLL_OPTIONS,
                    self::MAX_POLL_OPTIONS,
                    $count
                ),
                'options',
                $options
            );
        }

        foreach ($options as $index => $option) {
            $length = mb_strlen($option, 'UTF-8');
            if ($length < 1 || $length > 100) {
                throw new ValidationException(
                    sprintf(
                        'Вариант #%d должен содержать от 1 до 100 символов (текущая длина: %d)',
                        $index + 1,
                        $length
                    ),
                    'options',
                    $option
                );
            }
        }
    }

    /**
     * Валидирует inline клавиатуру
     *
     * @param array<array<array<string, mixed>>> $keyboard Клавиатура для проверки
     * @throws ValidationException Если клавиатура некорректна
     */
    public static function validateInlineKeyboard(array $keyboard): void
    {
        if (empty($keyboard)) {
            throw new ValidationException(
                'Клавиатура не может быть пустой',
                'inline_keyboard',
                $keyboard
            );
        }

        foreach ($keyboard as $rowIndex => $row) {
            if (!is_array($row) || empty($row)) {
                throw new ValidationException(
                    sprintf('Ряд #%d клавиатуры должен быть непустым массивом', $rowIndex + 1),
                    'inline_keyboard',
                    $row
                );
            }

            foreach ($row as $buttonIndex => $button) {
                if (!is_array($button)) {
                    throw new ValidationException(
                        sprintf('Кнопка [%d][%d] должна быть массивом', $rowIndex, $buttonIndex),
                        'inline_keyboard',
                        $button
                    );
                }

                if (!isset($button['text']) || trim($button['text']) === '') {
                    throw new ValidationException(
                        sprintf('Кнопка [%d][%d] должна иметь текст', $rowIndex, $buttonIndex),
                        'inline_keyboard',
                        $button
                    );
                }

                if (isset($button['callback_data'])) {
                    self::validateCallbackData($button['callback_data']);
                }
            }
        }
    }

    /**
     * Валидирует reply клавиатуру
     *
     * @param array<array<array<string, mixed>>> $keyboard Клавиатура для проверки
     * @throws ValidationException Если клавиатура некорректна
     */
    public static function validateReplyKeyboard(array $keyboard): void
    {
        if (empty($keyboard)) {
            throw new ValidationException(
                'Клавиатура не может быть пустой',
                'keyboard',
                $keyboard
            );
        }

        foreach ($keyboard as $rowIndex => $row) {
            if (!is_array($row) || empty($row)) {
                throw new ValidationException(
                    sprintf('Ряд #%d клавиатуры должен быть непустым массивом', $rowIndex + 1),
                    'keyboard',
                    $row
                );
            }

            foreach ($row as $buttonIndex => $button) {
                if (!is_array($button)) {
                    throw new ValidationException(
                        sprintf('Кнопка [%d][%d] должна быть массивом', $rowIndex, $buttonIndex),
                        'keyboard',
                        $button
                    );
                }

                if (!isset($button['text']) || trim($button['text']) === '') {
                    throw new ValidationException(
                        sprintf('Кнопка [%d][%d] должна иметь текст', $rowIndex, $buttonIndex),
                        'keyboard',
                        $button
                    );
                }
            }
        }
    }

    /**
     * Валидирует структуру клавиатуры (общая проверка)
     *
     * @param array<array<array<string, mixed>>> $keyboard Структура клавиатуры
     * @param string $type Тип клавиатуры ('inline' или 'reply')
     * @return void
     * @throws ValidationException Если клавиатура некорректна
     */
    public static function validateKeyboard(array $keyboard, string $type = 'inline'): void
    {
        if (empty($keyboard)) {
            throw new ValidationException(
                'Клавиатура не может быть пустой',
                'keyboard',
                []
            );
        }

        foreach ($keyboard as $rowIndex => $row) {
            if (!is_array($row)) {
                throw new ValidationException(
                    sprintf('Ряд #%d должен быть массивом', $rowIndex),
                    'keyboard',
                    $row
                );
            }

            if (empty($row)) {
                throw new ValidationException(
                    sprintf('Ряд #%d не может быть пустым', $rowIndex),
                    'keyboard',
                    []
                );
            }

            foreach ($row as $buttonIndex => $button) {
                if (!is_array($button)) {
                    throw new ValidationException(
                        sprintf('Кнопка [%d][%d] должна быть массивом', $rowIndex, $buttonIndex),
                        'keyboard',
                        $button
                    );
                }

                if (!isset($button['text']) || !is_string($button['text'])) {
                    throw new ValidationException(
                        sprintf('Кнопка [%d][%d] должна содержать текст', $rowIndex, $buttonIndex),
                        'keyboard',
                        $button
                    );
                }

                if ($type === 'inline') {
                    self::validateInlineButton($button, $rowIndex, $buttonIndex);
                }
            }
        }
    }

    /**
     * Валидирует inline кнопку
     *
     * @param array<string, mixed> $button Кнопка
     * @param int $rowIndex Индекс ряда
     * @param int $buttonIndex Индекс кнопки
     * @return void
     * @throws ValidationException
     */
    private static function validateInlineButton(array $button, int $rowIndex, int $buttonIndex): void
    {
        $hasAction = isset($button['callback_data']) 
            || isset($button['url']) 
            || isset($button['web_app'])
            || isset($button['login_url'])
            || isset($button['switch_inline_query'])
            || isset($button['switch_inline_query_current_chat']);

        if (!$hasAction) {
            throw new ValidationException(
                sprintf(
                    'Inline кнопка [%d][%d] должна содержать действие (callback_data, url, и т.д.)',
                    $rowIndex,
                    $buttonIndex
                ),
                'keyboard',
                $button
            );
        }
    }

    /**
     * Валидирует опции опроса (расширенная версия)
     *
     * @param array<string> $options Варианты ответов
     * @param bool $allowDuplicates Разрешить дубликаты
     * @return void
     * @throws ValidationException
     */
    public static function validatePollOptionsExtended(array $options, bool $allowDuplicates = true): void
    {
        // Базовая валидация
        self::validatePollOptions($options);

        // Проверка дубликатов если не разрешены
        if (!$allowDuplicates) {
            $unique = array_unique($options);
            if (count($unique) !== count($options)) {
                throw new ValidationException(
                    'Опции опроса содержат дубликаты',
                    'options',
                    $options
                );
            }
        }
    }

    /**
     * Валидирует результаты inline запроса
     *
     * @param array<array<string, mixed>> $results Массив результатов
     * @return void
     * @throws ValidationException
     */
    public static function validateInlineQuery(array $results): void
    {
        if (empty($results)) {
            throw new ValidationException(
                'Результаты inline запроса не могут быть пустыми',
                'inline_query_results',
                []
            );
        }

        if (count($results) > 50) {
            throw new ValidationException(
                sprintf(
                    'Максимум 50 результатов для inline запроса (передано: %d)',
                    count($results)
                ),
                'inline_query_results',
                $results
            );
        }

        foreach ($results as $index => $result) {
            if (!is_array($result)) {
                throw new ValidationException(
                    sprintf('Результат #%d должен быть массивом', $index),
                    'inline_query_results',
                    $result
                );
            }

            if (!isset($result['type']) || !is_string($result['type'])) {
                throw new ValidationException(
                    sprintf('Результат #%d должен содержать тип (type)', $index),
                    'inline_query_results',
                    $result
                );
            }

            if (!isset($result['id']) || !is_string($result['id'])) {
                throw new ValidationException(
                    sprintf('Результат #%d должен содержать уникальный ID', $index),
                    'inline_query_results',
                    $result
                );
            }
        }
    }

    /**
     * Валидирует параметры медиа-группы
     *
     * @param array<array<string, mixed>> $media Массив медиа
     * @return void
     * @throws ValidationException
     */
    public static function validateMediaGroup(array $media): void
    {
        if (count($media) < 2 || count($media) > 10) {
            throw new ValidationException(
                sprintf(
                    'Медиа-группа должна содержать от 2 до 10 элементов (передано: %d)',
                    count($media)
                ),
                'media',
                $media
            );
        }

        $allowedTypes = ['photo', 'video', 'document', 'audio'];

        foreach ($media as $index => $item) {
            if (!isset($item['type']) || !in_array($item['type'], $allowedTypes, true)) {
                throw new ValidationException(
                    sprintf(
                        'Элемент #%d имеет недопустимый тип медиа (разрешены: %s)',
                        $index,
                        implode(', ', $allowedTypes)
                    ),
                    'media',
                    $item
                );
            }

            if (!isset($item['media'])) {
                throw new ValidationException(
                    sprintf('Элемент #%d должен содержать медиа', $index),
                    'media',
                    $item
                );
            }

            if (isset($item['caption'])) {
                self::validateCaption($item['caption']);
            }
        }
    }

    /**
     * Валидирует webhook URL
     *
     * @param string $url URL webhook
     * @return void
     * @throws ValidationException
     */
    public static function validateWebhookUrl(string $url): void
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new ValidationException(
                'Некорректный URL webhook',
                'url',
                $url
            );
        }

        if (!str_starts_with($url, 'https://')) {
            throw new ValidationException(
                'Webhook URL должен использовать HTTPS',
                'url',
                $url
            );
        }

        $maxLength = 256;
        if (mb_strlen($url, 'UTF-8') > $maxLength) {
            throw new ValidationException(
                sprintf('URL webhook не должен превышать %d символов', $maxLength),
                'url',
                $url
            );
        }
    }

    /**
     * Валидирует email адрес
     *
     * @param string $email Email адрес
     * @return void
     * @throws ValidationException
     */
    public static function validateEmail(string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException(
                'Некорректный email адрес',
                'email',
                $email
            );
        }
    }

    /**
     * Валидирует номер телефона (базовая проверка)
     *
     * @param string $phone Номер телефона
     * @return void
     * @throws ValidationException
     */
    public static function validatePhone(string $phone): void
    {
        // Базовая проверка: только цифры, +, -, (, ), пробелы
        if (!preg_match('/^[\d\s\+\-\(\)]+$/', $phone)) {
            throw new ValidationException(
                'Некорректный номер телефона',
                'phone',
                $phone
            );
        }

        // Минимальная длина (без пробелов и символов)
        $digitsOnly = preg_replace('/[^\d]/', '', $phone);
        if (strlen($digitsOnly) < 7) {
            throw new ValidationException(
                'Номер телефона слишком короткий',
                'phone',
                $phone
            );
        }
    }
}

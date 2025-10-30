<?php

declare(strict_types=1);

namespace App\Component;

use App\Component\Exception\TelegramApiException;
use App\Component\Exception\TelegramConfigException;
use App\Component\Exception\TelegramFileException;
use CURLFile;
use GuzzleHttp\Psr7\Utils;
use JsonException;

/**
 * Класс для работы с Telegram Bot API
 * 
 * Обеспечивает типобезопасную и надежную интеграцию с Telegram Bot API.
 * Поддерживает отправку текстовых сообщений, изображений, видео, аудио и документов.
 * 
 * @link https://core.telegram.org/bots/api Документация Telegram Bot API
 */
class Telegram
{
    /**
     * Базовый URL Telegram Bot API
     */
    private const BASE_URL = 'https://api.telegram.org/bot';

    /**
     * Таймаут по умолчанию для HTTP запросов (в секундах)
     */
    private const DEFAULT_TIMEOUT = 30;

    /**
     * Минимальный допустимый таймаут (в секундах)
     */
    private const MIN_TIMEOUT = 5;

    /**
     * Максимальный размер файла для отправки (в байтах) - 50 МБ
     */
    private const MAX_FILE_SIZE = 52428800;

    /**
     * Режим разметки: Markdown
     */
    public const PARSE_MODE_MARKDOWN = 'Markdown';

    /**
     * Режим разметки: MarkdownV2
     */
    public const PARSE_MODE_MARKDOWN_V2 = 'MarkdownV2';

    /**
     * Режим разметки: HTML
     */
    public const PARSE_MODE_HTML = 'HTML';

    /**
     * Токен бота
     */
    private readonly string $token;

    /**
     * Идентификатор чата по умолчанию
     */
    private readonly ?string $defaultChatId;

    /**
     * Таймаут для HTTP запросов
     */
    private readonly int $timeout;

    /**
     * Экземпляр логгера
     */
    private readonly ?Logger $logger;

    /**
     * HTTP клиент
     */
    private readonly Http $http;

    /**
     * Конструктор класса Telegram
     *
     * @param array<string, mixed> $config Конфигурация Telegram API:
     *   - token (string, обязательно): Токен Telegram бота
     *   - default_chat_id (string, опционально): ID чата по умолчанию
     *   - timeout (int, опционально): Таймаут запросов в секундах (по умолчанию 30)
     *   - retries (int, опционально): Количество повторных попыток при ошибках сети
     * @param Logger|null $logger Экземпляр логгера для записи событий и ошибок
     * 
     * @throws TelegramConfigException Если конфигурация некорректна
     */
    public function __construct(array $config, ?Logger $logger = null)
    {
        $this->logger = $logger;
        $this->validateAndInitializeConfig($config);

        $httpConfig = [
            'base_uri' => self::BASE_URL . $this->token . '/',
            'timeout' => $this->timeout,
            'connect_timeout' => $this->timeout,
        ];

        if (array_key_exists('retries', $config)) {
            $httpConfig['retries'] = max(0, (int)$config['retries']);
        }

        $this->http = new Http($httpConfig, $logger);
    }

    /**
     * Валидирует и инициализирует конфигурацию
     *
     * @param array<string, mixed> $config Конфигурация
     * @throws TelegramConfigException Если конфигурация некорректна
     */
    private function validateAndInitializeConfig(array $config): void
    {
        $token = trim((string)($config['token'] ?? ''));

        if ($token === '') {
            throw new TelegramConfigException('Токен Telegram бота не указан.');
        }

        if (!$this->isValidToken($token)) {
            throw new TelegramConfigException('Формат токена Telegram бота некорректен. Ожидается формат: 123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11');
        }

        $this->token = $token;

        $defaultChatId = isset($config['default_chat_id']) ? trim((string)$config['default_chat_id']) : null;
        $this->defaultChatId = ($defaultChatId !== null && $defaultChatId !== '') ? $defaultChatId : null;

        $timeout = (int)($config['timeout'] ?? self::DEFAULT_TIMEOUT);
        $this->timeout = max(self::MIN_TIMEOUT, $timeout);

        if ($timeout < self::MIN_TIMEOUT) {
            $this->logWarning('Указанный таймаут меньше минимального, установлен минимальный таймаут', [
                'provided' => $timeout,
                'min' => self::MIN_TIMEOUT,
            ]);
        }
    }

    /**
     * Проверяет корректность формата токена Telegram бота
     *
     * @param string $token Токен для проверки
     * @return bool Возвращает true если токен корректен
     */
    private function isValidToken(string $token): bool
    {
        return (bool)preg_match('/^\d{7,}:[A-Za-z0-9_-]{30,}$/', $token);
    }

    /**
     * Проверяет валидность и доступность токена через API
     *
     * @return array<string, mixed> Информация о боте
     * @throws TelegramApiException Если токен невалиден или запрос завершился с ошибкой
     * @throws JsonException Если не удалось обработать JSON ответа
     */
    public function getMe(): array
    {
        return $this->sendJson('getMe', []);
    }

    /**
     * Отправляет текстовое сообщение
     *
     * @param string|null $chatId Идентификатор чата (если null, используется default_chat_id)
     * @param string $text Текст сообщения (до 4096 символов)
     * @param array<string, mixed> $options Дополнительные параметры:
     *   - parse_mode (string): Режим разметки (HTML, Markdown, MarkdownV2)
     *   - entities (array): Специальные сущности в тексте
     *   - disable_web_page_preview (bool): Отключить превью ссылок
     *   - disable_notification (bool): Отправить сообщение без звука
     *   - protect_content (bool): Защитить контент от пересылки
     *   - reply_to_message_id (int): ID сообщения для ответа
     *   - allow_sending_without_reply (bool): Отправить даже если сообщение для ответа не найдено
     *   - reply_markup (array): Дополнительные опции интерфейса
     * 
     * @return array<string, mixed> Ответ Telegram API
     * @throws TelegramApiException Если запрос завершился с ошибкой
     * @throws JsonException Если не удалось обработать JSON
     */
    public function sendText(?string $chatId, string $text, array $options = []): array
    {
        $this->validateText($text);

        $payload = array_merge($options, [
            'chat_id' => $this->resolveChatId($chatId),
            'text' => $text,
        ]);

        return $this->sendJson('sendMessage', $payload);
    }

    /**
     * Отправляет изображение
     *
     * @param string|null $chatId Идентификатор чата (если null, используется default_chat_id)
     * @param string $photo Путь к файлу или URL изображения
     * @param array<string, mixed> $options Дополнительные параметры:
     *   - caption (string): Подпись к изображению (до 1024 символов)
     *   - parse_mode (string): Режим разметки для подписи
     *   - caption_entities (array): Специальные сущности в подписи
     *   - disable_notification (bool): Отправить без звука
     *   - protect_content (bool): Защитить контент от пересылки
     *   - reply_to_message_id (int): ID сообщения для ответа
     *   - allow_sending_without_reply (bool): Отправить даже если сообщение для ответа не найдено
     *   - reply_markup (array): Дополнительные опции интерфейса
     * 
     * @return array<string, mixed> Ответ Telegram API
     * @throws TelegramApiException Если запрос завершился с ошибкой
     * @throws TelegramFileException Если файл не найден или недоступен
     * @throws JsonException Если не удалось обработать JSON
     */
    public function sendPhoto(?string $chatId, string $photo, array $options = []): array
    {
        $payload = array_merge($options, [
            'chat_id' => $this->resolveChatId($chatId),
            'photo' => $this->prepareFile($photo),
        ]);

        if (isset($options['caption'])) {
            $this->validateCaption($options['caption']);
        }

        return $this->sendMultipart('sendPhoto', $payload);
    }

    /**
     * Отправляет видео
     *
     * @param string|null $chatId Идентификатор чата (если null, используется default_chat_id)
     * @param string $video Путь к файлу или URL видео
     * @param array<string, mixed> $options Дополнительные параметры:
     *   - duration (int): Длительность видео в секундах
     *   - width (int): Ширина видео
     *   - height (int): Высота видео
     *   - thumb (string): Путь к превью (JPEG)
     *   - caption (string): Подпись к видео (до 1024 символов)
     *   - parse_mode (string): Режим разметки для подписи
     *   - caption_entities (array): Специальные сущности в подписи
     *   - supports_streaming (bool): Поддержка потокового воспроизведения
     *   - disable_notification (bool): Отправить без звука
     *   - protect_content (bool): Защитить контент от пересылки
     *   - reply_to_message_id (int): ID сообщения для ответа
     *   - allow_sending_without_reply (bool): Отправить даже если сообщение для ответа не найдено
     *   - reply_markup (array): Дополнительные опции интерфейса
     * 
     * @return array<string, mixed> Ответ Telegram API
     * @throws TelegramApiException Если запрос завершился с ошибкой
     * @throws TelegramFileException Если файл не найден или недоступен
     * @throws JsonException Если не удалось обработать JSON
     */
    public function sendVideo(?string $chatId, string $video, array $options = []): array
    {
        $payload = array_merge($options, [
            'chat_id' => $this->resolveChatId($chatId),
            'video' => $this->prepareFile($video),
        ]);

        if (isset($options['caption'])) {
            $this->validateCaption($options['caption']);
        }

        return $this->sendMultipart('sendVideo', $payload);
    }

    /**
     * Отправляет аудиофайл
     *
     * @param string|null $chatId Идентификатор чата (если null, используется default_chat_id)
     * @param string $audio Путь к файлу или URL аудио
     * @param array<string, mixed> $options Дополнительные параметры:
     *   - caption (string): Подпись к аудио (до 1024 символов)
     *   - parse_mode (string): Режим разметки для подписи
     *   - caption_entities (array): Специальные сущности в подписи
     *   - duration (int): Длительность аудио в секундах
     *   - performer (string): Исполнитель
     *   - title (string): Название трека
     *   - thumb (string): Путь к превью (JPEG)
     *   - disable_notification (bool): Отправить без звука
     *   - protect_content (bool): Защитить контент от пересылки
     *   - reply_to_message_id (int): ID сообщения для ответа
     *   - allow_sending_without_reply (bool): Отправить даже если сообщение для ответа не найдено
     *   - reply_markup (array): Дополнительные опции интерфейса
     * 
     * @return array<string, mixed> Ответ Telegram API
     * @throws TelegramApiException Если запрос завершился с ошибкой
     * @throws TelegramFileException Если файл не найден или недоступен
     * @throws JsonException Если не удалось обработать JSON
     */
    public function sendAudio(?string $chatId, string $audio, array $options = []): array
    {
        $payload = array_merge($options, [
            'chat_id' => $this->resolveChatId($chatId),
            'audio' => $this->prepareFile($audio),
        ]);

        if (isset($options['caption'])) {
            $this->validateCaption($options['caption']);
        }

        return $this->sendMultipart('sendAudio', $payload);
    }

    /**
     * Отправляет документ
     *
     * @param string|null $chatId Идентификатор чата (если null, используется default_chat_id)
     * @param string $document Путь к файлу или URL документа
     * @param array<string, mixed> $options Дополнительные параметры:
     *   - thumb (string): Путь к превью (JPEG)
     *   - caption (string): Подпись к документу (до 1024 символов)
     *   - parse_mode (string): Режим разметки для подписи
     *   - caption_entities (array): Специальные сущности в подписи
     *   - disable_content_type_detection (bool): Отключить автоопределение типа контента
     *   - disable_notification (bool): Отправить без звука
     *   - protect_content (bool): Защитить контент от пересылки
     *   - reply_to_message_id (int): ID сообщения для ответа
     *   - allow_sending_without_reply (bool): Отправить даже если сообщение для ответа не найдено
     *   - reply_markup (array): Дополнительные опции интерфейса
     * 
     * @return array<string, mixed> Ответ Telegram API
     * @throws TelegramApiException Если запрос завершился с ошибкой
     * @throws TelegramFileException Если файл не найден или недоступен
     * @throws JsonException Если не удалось обработать JSON
     */
    public function sendDocument(?string $chatId, string $document, array $options = []): array
    {
        $payload = array_merge($options, [
            'chat_id' => $this->resolveChatId($chatId),
            'document' => $this->prepareFile($document),
        ]);

        if (isset($options['caption'])) {
            $this->validateCaption($options['caption']);
        }

        return $this->sendMultipart('sendDocument', $payload);
    }

    /**
     * Валидирует текст сообщения
     *
     * @param string $text Текст для валидации
     * @throws TelegramApiException Если текст превышает допустимую длину
     */
    private function validateText(string $text): void
    {
        if (mb_strlen($text, 'UTF-8') > 4096) {
            throw new TelegramApiException('Текст сообщения не может превышать 4096 символов.');
        }

        if (trim($text) === '') {
            throw new TelegramApiException('Текст сообщения не может быть пустым.');
        }
    }

    /**
     * Валидирует подпись к медиафайлу
     *
     * @param string $caption Подпись для валидации
     * @throws TelegramApiException Если подпись превышает допустимую длину
     */
    private function validateCaption(string $caption): void
    {
        if (mb_strlen($caption, 'UTF-8') > 1024) {
            throw new TelegramApiException('Подпись не может превышать 1024 символа.');
        }
    }

    /**
     * Определяет идентификатор чата
     *
     * @param string|null $chatId Идентификатор чата
     * @return string Разрешенный идентификатор чата
     * @throws TelegramConfigException Если идентификатор не задан
     */
    private function resolveChatId(?string $chatId): string
    {
        if ($chatId !== null && $chatId !== '') {
            return trim($chatId);
        }

        if ($this->defaultChatId === null) {
            throw new TelegramConfigException('Идентификатор чата не указан. Укажите chat_id в параметрах метода или установите default_chat_id в конфигурации.');
        }

        return $this->defaultChatId;
    }

    /**
     * Подготавливает файл для отправки
     *
     * @param string $pathOrUrl Локальный путь или URL
     * @return CURLFile|string Подготовленный файл или URL
     * @throws TelegramFileException Если файл не найден или недоступен
     */
    private function prepareFile(string $pathOrUrl): CURLFile|string
    {
        if ($this->isUrl($pathOrUrl)) {
            return $pathOrUrl;
        }

        if (!file_exists($pathOrUrl)) {
            throw new TelegramFileException("Файл не найден: {$pathOrUrl}");
        }

        if (!is_readable($pathOrUrl)) {
            throw new TelegramFileException("Файл недоступен для чтения: {$pathOrUrl}");
        }

        $fileSize = @filesize($pathOrUrl);
        if ($fileSize === false) {
            throw new TelegramFileException("Не удалось определить размер файла: {$pathOrUrl}");
        }

        if ($fileSize > self::MAX_FILE_SIZE) {
            $maxSizeMb = self::MAX_FILE_SIZE / 1024 / 1024;
            $fileSizeMb = round($fileSize / 1024 / 1024, 2);
            throw new TelegramFileException("Размер файла ({$fileSizeMb} МБ) превышает максимально допустимый ({$maxSizeMb} МБ): {$pathOrUrl}");
        }

        if ($fileSize === 0) {
            throw new TelegramFileException("Файл пустой: {$pathOrUrl}");
        }

        return new CURLFile($pathOrUrl);
    }

    /**
     * Проверяет, является ли строка URL
     *
     * @param string $string Строка для проверки
     * @return bool Возвращает true если строка является валидным URL
     */
    private function isUrl(string $string): bool
    {
        return (bool)filter_var($string, FILTER_VALIDATE_URL);
    }

    /**
     * Выполняет JSON-запрос к Telegram API
     *
     * @param string $method Метод API
     * @param array<string, mixed> $payload Данные запроса
     * @return array<string, mixed> Ответ API
     * @throws TelegramApiException Если запрос завершился с ошибкой
     * @throws JsonException Если не удалось обработать JSON
     */
    private function sendJson(string $method, array $payload): array
    {
        $response = $this->http->request('POST', $method, [
            'json' => $payload,
        ]);

        return $this->processResponse($response, $method);
    }

    /**
     * Выполняет multipart-запрос к Telegram API
     *
     * @param string $method Метод API
     * @param array<string, mixed> $payload Данные запроса
     * @return array<string, mixed> Ответ API
     * @throws TelegramApiException Если запрос завершился с ошибкой
     * @throws JsonException Если не удалось обработать JSON ответа
     */
    private function sendMultipart(string $method, array $payload): array
    {
        $multipart = $this->prepareMultipart($payload);

        $response = $this->http->request('POST', $method, [
            'multipart' => $multipart,
        ]);

        return $this->processResponse($response, $method);
    }

    /**
     * Обрабатывает ответ от Telegram API
     *
     * @param \Psr\Http\Message\ResponseInterface $response HTTP ответ
     * @param string $method Имя вызванного метода API
     * @return array<string, mixed> Декодированный ответ
     * @throws TelegramApiException Если API вернул ошибку
     * @throws JsonException Если не удалось декодировать JSON
     */
    private function processResponse(\Psr\Http\Message\ResponseInterface $response, string $method): array
    {
        $statusCode = $response->getStatusCode();
        $body = (string)$response->getBody();

        if ($statusCode >= 400) {
            $this->handleApiError($statusCode, $body, $method);
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->logError('Не удалось декодировать JSON ответ от Telegram API', [
                'method' => $method,
                'status_code' => $statusCode,
                'body_preview' => mb_substr($body, 0, 200),
            ]);
            throw new TelegramApiException("Некорректный JSON в ответе от Telegram API (метод: {$method})", $statusCode);
        }

        if (!is_array($decoded)) {
            throw new TelegramApiException("Ожидался массив в ответе от Telegram API (метод: {$method})", $statusCode);
        }

        if (!($decoded['ok'] ?? false)) {
            $errorDescription = $decoded['description'] ?? 'Неизвестная ошибка';
            $errorCode = $decoded['error_code'] ?? null;

            $this->logError('Telegram API вернул ошибку', [
                'method' => $method,
                'error_code' => $errorCode,
                'description' => $errorDescription,
            ]);

            throw new TelegramApiException(
                "Telegram API вернул ошибку (метод: {$method}): {$errorDescription}",
                $statusCode,
                $errorDescription,
                $errorCode
            );
        }

        return $decoded;
    }

    /**
     * Обрабатывает ошибку от Telegram API
     *
     * @param int $statusCode HTTP статус код
     * @param string $body Тело ответа
     * @param string $method Имя вызванного метода API
     * @throws TelegramApiException
     */
    private function handleApiError(int $statusCode, string $body, string $method): void
    {
        $bodyPreview = mb_substr($body, 0, 500);

        $this->logError('Telegram API вернул HTTP ошибку', [
            'method' => $method,
            'status_code' => $statusCode,
            'body_preview' => $bodyPreview,
        ]);

        $errorMessage = match ($statusCode) {
            400 => 'Некорректный запрос',
            401 => 'Неверный токен бота',
            403 => 'Бот не имеет доступа к чату или пользователю',
            404 => 'Метод не найден',
            429 => 'Превышен лимит запросов (rate limit)',
            500, 502, 503, 504 => 'Внутренняя ошибка сервера Telegram',
            default => 'HTTP ошибка',
        };

        throw new TelegramApiException(
            "{$errorMessage} (метод: {$method}, код: {$statusCode})",
            $statusCode,
            $bodyPreview
        );
    }

    /**
     * Преобразует массив данных в формат multipart/form-data
     *
     * @param array<string, mixed> $payload Данные запроса
     * @return array<int, array<string, mixed>>
     * @throws JsonException Если не удалось сериализовать данные
     */
    private function prepareMultipart(array $payload): array
    {
        $multipart = [];
        $flattened = $this->flattenMultipart($payload);

        foreach ($flattened as $name => $value) {
            if ($value instanceof CURLFile) {
                $multipart[] = $this->createFilePart($name, $value);
                continue;
            }

            $multipart[] = [
                'name' => $name,
                'contents' => $this->normalizeMultipartValue($value),
            ];
        }

        return $multipart;
    }

    /**
     * Рекурсивно разворачивает многоуровневый массив параметров
     *
     * @param array<string, mixed> $payload Исходные данные
     * @param string $prefix Текущий префикс ключа
     * @return array<string, mixed>
     */
    private function flattenMultipart(array $payload, string $prefix = ''): array
    {
        $result = [];

        foreach ($payload as $key => $value) {
            $name = $prefix === '' ? (string)$key : sprintf('%s[%s]', $prefix, (string)$key);

            if (is_array($value) && !$this->isAssociativeArray($value)) {
                $result[$name] = $value;
                continue;
            }

            if (is_array($value)) {
                $result = array_merge($result, $this->flattenMultipart($value, $name));
                continue;
            }

            $result[$name] = $value;
        }

        return $result;
    }

    /**
     * Проверяет, является ли массив ассоциативным
     *
     * @param array<mixed> $array Массив для проверки
     * @return bool Возвращает true если массив ассоциативный
     */
    private function isAssociativeArray(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Подготавливает файловую часть запроса
     *
     * @param string $name Имя поля
     * @param CURLFile $file Файл для загрузки
     * @return array<string, mixed>
     * @throws TelegramFileException Если файл не может быть открыт
     */
    private function createFilePart(string $name, CURLFile $file): array
    {
        $filePath = $file->getFilename();

        if (!is_readable($filePath)) {
            throw new TelegramFileException("Файл недоступен для чтения: {$filePath}");
        }

        $resource = @fopen($filePath, 'rb');
        if ($resource === false) {
            throw new TelegramFileException("Не удалось открыть файл: {$filePath}");
        }

        $part = [
            'name' => $name,
            'contents' => $resource,
        ];

        $filename = $file->getPostFilename();
        if ($filename === '' || $filename === null) {
            $filename = basename($filePath);
        }

        if ($filename !== '') {
            $part['filename'] = $filename;
        }

        $mimeType = $file->getMimeType();
        if ($mimeType !== '') {
            $part['headers'] = ['Content-Type' => $mimeType];
        }

        return $part;
    }

    /**
     * Преобразует значение в строку для multipart запроса
     *
     * @param mixed $value Значение параметра
     * @return string
     * @throws JsonException Если не удалось сериализовать значение
     */
    private function normalizeMultipartValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string)$value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string)$value;
        }

        if (is_resource($value)) {
            throw new TelegramApiException('Тип resource не может быть передан в multipart запрос');
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * Записывает сообщение об ошибке в лог
     *
     * @param string $message Сообщение об ошибке
     * @param array<string, mixed> $context Дополнительный контекст
     */
    private function logError(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->error($message, $context);
        }
    }

    /**
     * Записывает предупреждение в лог
     *
     * @param string $message Сообщение-предупреждение
     * @param array<string, mixed> $context Дополнительный контекст
     */
    private function logWarning(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->warning($message, $context);
        }
    }
}

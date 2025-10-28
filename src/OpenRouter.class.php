<?php

declare(strict_types=1);

namespace App\Component;

use App\Component\Exception\OpenRouterApiException;
use App\Component\Exception\OpenRouterException;
use App\Component\Exception\OpenRouterNetworkException;
use App\Component\Exception\OpenRouterValidationException;
use JsonException;

/**
 * Класс для работы с OpenRouter API
 * 
 * Предоставляет методы для взаимодействия с различными AI моделями через OpenRouter API:
 * - Текстовая генерация (text2text)
 * - Генерация изображений (text2image)
 * - Распознавание изображений (image2text)
 * - Распознавание речи (audio2text)
 * - Синтез речи (text2audio)
 * - Извлечение текста из PDF (pdf2text)
 * - Потоковая передача текста (textStream)
 */
class OpenRouter
{
    private const BASE_URL = 'https://openrouter.ai/api/v1';
    private const DEFAULT_TIMEOUT = 60;
    private const MAX_FILE_DOWNLOAD_SIZE = 52428800; // 50 МБ
    private const STREAM_CHUNK_SIZE = 8192;
    private const DOWNLOAD_CHUNK_SIZE = 8192;

    private string $apiKey;
    private string $appName;
    private int $timeout;
    private ?Logger $logger;
    private Http $http;

    /**
     * Конструктор класса OpenRouter
     *
     * @param array<string, mixed> $config Конфигурация OpenRouter API:
     *                                     - api_key (string, обязательно): API ключ OpenRouter
     *                                     - app_name (string, необязательно): Название приложения
     *                                     - timeout (int, необязательно): Таймаут соединения в секундах
     *                                     - retries (int, необязательно): Количество повторных попыток
     * @param Logger|null $logger Экземпляр логгера для записи событий
     * @throws OpenRouterValidationException Если API ключ не указан или конфигурация некорректна
     */
    public function __construct(array $config, ?Logger $logger = null)
    {
        $this->validateConfiguration($config);
        
        $this->apiKey = $config['api_key'];
        $this->appName = (string)($config['app_name'] ?? 'BasicUtilitiesApp');
        $this->timeout = max(1, (int)($config['timeout'] ?? self::DEFAULT_TIMEOUT));
        $this->logger = $logger;

        $httpConfig = [
            'base_uri' => self::BASE_URL,
            'timeout' => $this->timeout,
            'connect_timeout' => $this->timeout,
        ];

        if (isset($config['retries'])) {
            $httpConfig['retries'] = max(1, (int)$config['retries']);
        }

        $this->http = new Http($httpConfig, $logger);
    }

    /**
     * Отправляет текстовый запрос и получает текстовый ответ (text2text)
     *
     * @param string $model Модель ИИ для использования (например, "openai/gpt-4")
     * @param string $prompt Текстовый запрос для модели
     * @param array<string, mixed> $options Дополнительные параметры запроса:
     *                                      - temperature (float): Температура генерации (0.0 - 2.0)
     *                                      - max_tokens (int): Максимальное количество токенов
     *                                      - top_p (float): Top-p sampling
     * @return string Ответ модели
     * @throws OpenRouterValidationException Если параметры невалидны
     * @throws OpenRouterApiException Если API вернул ошибку
     * @throws OpenRouterException Если модель не вернула текстовый ответ
     */
    public function text2text(string $model, string $prompt, array $options = []): string
    {
        $this->validateNotEmpty($model, 'model');
        $this->validateNotEmpty($prompt, 'prompt');

        $messages = [
            ['role' => 'user', 'content' => $prompt],
        ];

        $payload = array_merge([
            'model' => $model,
            'messages' => $messages,
        ], $options);

        $response = $this->sendRequest('/chat/completions', $payload);

        if (!isset($response['choices'][0]['message']['content'])) {
            throw new OpenRouterException('Модель не вернула текстовый ответ.');
        }

        return (string)$response['choices'][0]['message']['content'];
    }

    /**
     * Отправляет текстовый запрос и получает изображение (text2image)
     *
     * @param string $model Модель генерации изображений (например, "openai/dall-e-3")
     * @param string $prompt Описание изображения для генерации
     * @param array<string, mixed> $options Дополнительные параметры запроса:
     *                                      - size (string): Размер изображения
     *                                      - quality (string): Качество изображения
     *                                      - style (string): Стиль изображения
     * @return string URL сгенерированного изображения
     * @throws OpenRouterValidationException Если параметры невалидны
     * @throws OpenRouterApiException Если API вернул ошибку
     * @throws OpenRouterException Если модель не вернула URL изображения
     */
    public function text2image(string $model, string $prompt, array $options = []): string
    {
        $this->validateNotEmpty($model, 'model');
        $this->validateNotEmpty($prompt, 'prompt');

        $payload = array_merge([
            'model' => $model,
            'prompt' => $prompt,
        ], $options);

        $response = $this->sendRequest('/images/generations', $payload);

        if (!isset($response['data'][0]['url'])) {
            throw new OpenRouterException('Модель не вернула URL изображения.');
        }

        return (string)$response['data'][0]['url'];
    }

    /**
     * Отправляет изображение и получает текстовое описание (image2text)
     *
     * @param string $model Модель распознавания изображений (например, "openai/gpt-4-vision-preview")
     * @param string $imageUrl URL изображения для анализа
     * @param string $question Вопрос к изображению
     * @param array<string, mixed> $options Дополнительные параметры запроса
     * @return string Описание или ответ модели
     * @throws OpenRouterValidationException Если параметры невалидны
     * @throws OpenRouterApiException Если API вернул ошибку
     * @throws OpenRouterException Если модель не вернула текстовый ответ
     */
    public function image2text(
        string $model,
        string $imageUrl,
        string $question = 'Опиши это изображение',
        array $options = []
    ): string {
        $this->validateNotEmpty($model, 'model');
        $this->validateNotEmpty($imageUrl, 'imageUrl');
        $this->validateNotEmpty($question, 'question');

        $messages = [
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $question],
                    ['type' => 'image_url', 'image_url' => ['url' => $imageUrl]],
                ],
            ],
        ];

        $payload = array_merge([
            'model' => $model,
            'messages' => $messages,
        ], $options);

        $response = $this->sendRequest('/chat/completions', $payload);

        if (!isset($response['choices'][0]['message']['content'])) {
            throw new OpenRouterException('Модель не вернула текстовый ответ.');
        }

        return (string)$response['choices'][0]['message']['content'];
    }

    /**
     * Преобразует аудио в текст (audio2text)
     *
     * @param string $model Модель распознавания речи (например, "openai/whisper-1")
     * @param string $audioSource URL аудиофайла или путь к локальному файлу
     * @param array<string, mixed> $options Дополнительные параметры запроса:
     *                                      - language (string): Код языка (ISO-639-1)
     *                                      - temperature (float): Температура генерации
     *                                      - prompt (string): Подсказка для модели
     * @return string Распознанный текст
     * @throws OpenRouterValidationException Если параметры невалидны
     * @throws OpenRouterApiException Если API вернул ошибку
     * @throws OpenRouterNetworkException Если файл не удалось загрузить
     * @throws OpenRouterException Если модель не вернула текстовую транскрипцию
     * @throws JsonException Если не удалось декодировать ответ API
     */
    public function audio2text(string $model, string $audioSource, array $options = []): string
    {
        $this->validateNotEmpty($model, 'model');
        $this->validateNotEmpty($audioSource, 'audioSource');

        $multipart = [
            [
                'name' => 'model',
                'contents' => $model,
            ],
            $this->prepareMultipartFile('file', $audioSource, 'audio/mpeg'),
        ];

        foreach ($options as $key => $value) {
            $multipart[] = [
                'name' => (string)$key,
                'contents' => $this->normalizeMultipartValue($value),
            ];
        }

        $response = $this->http->request('POST', '/audio/transcriptions', [
            'multipart' => $multipart,
            'headers' => $this->buildHeaders(),
        ]);

        $statusCode = $response->getStatusCode();
        $body = (string)$response->getBody();

        if ($statusCode >= 400) {
            $this->logError('Сервер OpenRouter вернул ошибку при транскрибации аудио', [
                'status_code' => $statusCode,
                'response' => $body,
            ]);

            throw new OpenRouterApiException(
                'Сервер вернул код ошибки при транскрибации аудио',
                $statusCode,
                $body
            );
        }

        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        if (isset($decoded['text'])) {
            return (string)$decoded['text'];
        }

        if (isset($decoded['data'][0]['text'])) {
            return (string)$decoded['data'][0]['text'];
        }

        throw new OpenRouterException('Модель не вернула текстовую транскрипцию.');
    }

    /**
     * Преобразует текст в речь (text2audio)
     *
     * @param string $model Модель синтеза речи (например, "openai/tts-1" или "openai/tts-1-hd")
     * @param string $text Текст для преобразования в речь
     * @param string $voice Голос для синтеза (например, "alloy", "echo", "fable", "onyx", "nova", "shimmer")
     * @param array<string, mixed> $options Дополнительные параметры запроса:
     *                                      - speed (float): Скорость речи (0.25 - 4.0)
     *                                      - response_format (string): Формат аудио (mp3, opus, aac, flac)
     * @return string Бинарное содержимое аудиофайла
     * @throws OpenRouterValidationException Если параметры невалидны
     * @throws OpenRouterApiException Если API вернул ошибку
     * @throws OpenRouterException Если не удалось получить аудиоданные
     */
    public function text2audio(string $model, string $text, string $voice, array $options = []): string
    {
        $this->validateNotEmpty($model, 'model');
        $this->validateNotEmpty($text, 'text');
        $this->validateNotEmpty($voice, 'voice');

        $payload = array_merge([
            'model' => $model,
            'input' => $text,
            'voice' => $voice,
        ], $options);

        $headers = $this->buildHeaders();
        $headers['Content-Type'] = 'application/json';

        $response = $this->http->request('POST', '/audio/speech', [
            'json' => $payload,
            'headers' => $headers,
        ]);

        $statusCode = $response->getStatusCode();
        $body = (string)$response->getBody();

        if ($statusCode >= 400) {
            $this->logError('Сервер OpenRouter вернул ошибку при синтезе речи', [
                'status_code' => $statusCode,
                'response' => $body,
            ]);

            throw new OpenRouterApiException(
                'Сервер вернул код ошибки при синтезе речи',
                $statusCode,
                $body
            );
        }

        if ($body === '') {
            throw new OpenRouterException('Модель не вернула аудиоданные.');
        }

        return $body;
    }

    /**
     * Извлекает текст из PDF документа (pdf2text)
     *
     * @param string $model Модель анализа документов (например, "openai/gpt-4-vision" или "anthropic/claude-3")
     * @param string $pdfUrl URL PDF файла или путь к локальному файлу
     * @param string $instruction Инструкция для обработки документа
     * @param array<string, mixed> $options Дополнительные параметры запроса
     * @return string Извлеченный текст
     * @throws OpenRouterValidationException Если параметры невалидны
     * @throws OpenRouterApiException Если API вернул ошибку
     * @throws OpenRouterNetworkException Если файл не удалось загрузить
     * @throws OpenRouterException Если модель не вернула текстовый ответ
     */
    public function pdf2text(
        string $model,
        string $pdfUrl,
        string $instruction = 'Извлеки весь текст из этого PDF документа',
        array $options = []
    ): string {
        $this->validateNotEmpty($model, 'model');
        $this->validateNotEmpty($pdfUrl, 'pdfUrl');
        $this->validateNotEmpty($instruction, 'instruction');

        $pdfResource = $this->normalizeMediaReference($pdfUrl, 'application/pdf');

        $messages = [
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $instruction],
                    ['type' => 'document_url', 'document_url' => ['url' => $pdfResource]],
                ],
            ],
        ];

        $payload = array_merge([
            'model' => $model,
            'messages' => $messages,
        ], $options);

        $response = $this->sendRequest('/chat/completions', $payload);

        if (!isset($response['choices'][0]['message']['content'])) {
            throw new OpenRouterException('Модель не вернула текстовый ответ.');
        }

        return (string)$response['choices'][0]['message']['content'];
    }

    /**
     * Отправляет текстовый запрос с поддержкой streaming (потоковая передача)
     *
     * @param string $model Модель ИИ для использования
     * @param string $prompt Текстовый запрос
     * @param callable $callback Функция-обработчик частей ответа (принимает string)
     * @param array<string, mixed> $options Дополнительные параметры запроса
     * @throws OpenRouterValidationException Если параметры невалидны
     * @throws OpenRouterApiException Если API вернул ошибку
     * @throws OpenRouterException Если произошла ошибка при обработке потока
     */
    public function textStream(string $model, string $prompt, callable $callback, array $options = []): void
    {
        $this->validateNotEmpty($model, 'model');
        $this->validateNotEmpty($prompt, 'prompt');

        $messages = [
            ['role' => 'user', 'content' => $prompt],
        ];

        $payload = array_merge([
            'model' => $model,
            'messages' => $messages,
            'stream' => true,
        ], $options);

        $this->sendStreamRequest('/chat/completions', $payload, $callback);
    }

    /**
     * Валидирует конфигурацию при создании экземпляра класса
     *
     * @param array<string, mixed> $config Конфигурация для валидации
     * @throws OpenRouterValidationException Если конфигурация некорректна
     */
    private function validateConfiguration(array $config): void
    {
        if (!isset($config['api_key']) || !is_string($config['api_key']) || trim($config['api_key']) === '') {
            throw new OpenRouterValidationException('API ключ OpenRouter не указан или пустой.');
        }

        $config['api_key'] = trim($config['api_key']);
    }

    /**
     * Валидирует строковый параметр на пустоту
     *
     * @param string $value Значение для проверки
     * @param string $paramName Название параметра (для сообщения об ошибке)
     * @throws OpenRouterValidationException Если значение пустое
     */
    private function validateNotEmpty(string $value, string $paramName): void
    {
        if (trim($value) === '') {
            throw new OpenRouterValidationException(
                sprintf('Параметр "%s" не может быть пустым.', $paramName)
            );
        }
    }

    /**
     * Формирует стандартные заголовки для запросов к API
     *
     * @return array<string, string> Массив заголовков
     */
    private function buildHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'HTTP-Referer' => $this->appName,
        ];
    }

    /**
     * Выполняет стандартный HTTP-запрос к API
     *
     * @param string $endpoint Endpoint API (например, "/chat/completions")
     * @param array<string, mixed> $payload Данные для отправки в формате JSON
     * @return array<string, mixed> Декодированный ответ API
     * @throws OpenRouterApiException Если API вернул код ошибки >= 400
     * @throws OpenRouterException Если не удалось декодировать JSON ответ
     */
    private function sendRequest(string $endpoint, array $payload): array
    {
        $headers = $this->buildHeaders();
        $headers['Content-Type'] = 'application/json';

        $response = $this->http->request('POST', $endpoint, [
            'json' => $payload,
            'headers' => $headers,
        ]);

        $statusCode = $response->getStatusCode();
        $body = (string)$response->getBody();

        if ($statusCode >= 400) {
            $this->logError('Сервер OpenRouter вернул ошибку', [
                'status_code' => $statusCode,
                'endpoint' => $endpoint,
                'response' => $body
            ]);

            throw new OpenRouterApiException('Сервер вернул код ошибки', $statusCode, $body);
        }

        try {
            return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new OpenRouterException(
                'Не удалось декодировать ответ API: ' . $exception->getMessage(),
                0,
                $exception
            );
        }
    }

    /**
     * Выполняет streaming HTTP-запрос к API с обработкой данных в реальном времени
     *
     * @param string $endpoint Endpoint API
     * @param array<string, mixed> $payload Данные для отправки
     * @param callable $callback Функция для обработки потоков данных (принимает string)
     * @throws OpenRouterApiException Если API вернул ошибку
     * @throws OpenRouterException Если произошла ошибка при обработке потока
     */
    private function sendStreamRequest(string $endpoint, array $payload, callable $callback): void
    {
        $buffer = '';
        $headers = $this->buildHeaders();
        $headers['Content-Type'] = 'application/json';

        $this->http->requestStream('POST', $endpoint, function (string $chunk) use (&$buffer, $callback): void {
            $buffer .= $chunk;
            $lines = explode("\n", $buffer);
            $buffer = array_pop($lines) ?? '';

            foreach ($lines as $line) {
                $line = trim($line);

                if ($line === '' || $line === 'data: [DONE]') {
                    continue;
                }

                if (str_starts_with($line, 'data: ')) {
                    $json = substr($line, 6);

                    try {
                        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                    } catch (JsonException $exception) {
                        $this->logError('Ошибка декодирования JSON в потоке', [
                            'json' => $json,
                            'error' => $exception->getMessage()
                        ]);
                        continue;
                    }

                    if (isset($decoded['choices'][0]['delta']['content'])) {
                        $callback((string)$decoded['choices'][0]['delta']['content']);
                    }
                }
            }
        }, [
            'json' => $payload,
            'headers' => $headers,
        ]);
    }

    /**
     * Подготавливает часть multipart-запроса с файлом
     *
     * @param string $fieldName Имя поля в multipart запросе
     * @param string $reference URL или путь к локальному файлу
     * @param string $defaultMimeType MIME тип по умолчанию
     * @return array<string, mixed> Массив для multipart запроса
     * @throws OpenRouterValidationException Если файл не найден или не доступен
     * @throws OpenRouterNetworkException Если не удалось загрузить файл по URL
     */
    private function prepareMultipartFile(string $fieldName, string $reference, string $defaultMimeType): array
    {
        if (filter_var($reference, FILTER_VALIDATE_URL) !== false) {
            $contents = $this->downloadFile($reference);
            $filename = $this->deriveFileNameFromUrl($reference);
            $mimeType = $this->guessMimeTypeFromFileName($filename, $defaultMimeType);
        } else {
            if (!is_file($reference)) {
                throw new OpenRouterValidationException('Файл не найден: ' . $reference);
            }

            if (!is_readable($reference)) {
                throw new OpenRouterValidationException('Файл не доступен для чтения: ' . $reference);
            }

            $contents = file_get_contents($reference);

            if ($contents === false) {
                throw new OpenRouterNetworkException('Не удалось прочитать файл: ' . $reference);
            }

            $filename = basename($reference);

            if ($filename === '' || $filename === '.' || $filename === DIRECTORY_SEPARATOR) {
                $filename = 'file-' . md5($reference) . '.bin';
            }

            $mimeType = mime_content_type($reference);

            if ($mimeType === false) {
                $mimeType = $defaultMimeType;
            }
        }

        return [
            'name' => $fieldName,
            'contents' => $contents,
            'filename' => $filename,
            'headers' => [
                'Content-Type' => $mimeType,
            ],
        ];
    }

    /**
     * Преобразует значение опции в строку для multipart-запроса
     *
     * @param mixed $value Значение опции для преобразования
     * @return string Строковое представление значения
     * @throws OpenRouterException Если не удалось сериализовать значение
     */
    private function normalizeMultipartValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value) || $value === null) {
            return (string)$value;
        }

        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new OpenRouterException(
                'Не удалось сериализовать параметр multipart запроса: ' . $exception->getMessage(),
                0,
                $exception
            );
        }
    }

    /**
     * Загружает файл по URL с ограничением размера и проверкой безопасности
     *
     * @param string $url URL файла для загрузки
     * @return string Содержимое файла
     * @throws OpenRouterNetworkException Если не удалось загрузить файл или размер превышен
     */
    private function downloadFile(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => $this->timeout,
                'follow_location' => 1,
                'max_redirects' => 5,
                'user_agent' => $this->appName,
            ],
        ]);

        $stream = @fopen($url, 'rb', false, $context);

        if ($stream === false) {
            throw new OpenRouterNetworkException('Не удалось открыть поток для загрузки файла по URL: ' . $url);
        }

        $contents = '';
        $downloadedSize = 0;

        try {
            while (!feof($stream)) {
                $chunk = fread($stream, self::DOWNLOAD_CHUNK_SIZE);

                if ($chunk === false) {
                    throw new OpenRouterNetworkException('Ошибка при чтении данных из потока: ' . $url);
                }

                $contents .= $chunk;
                $downloadedSize += strlen($chunk);

                if ($downloadedSize > self::MAX_FILE_DOWNLOAD_SIZE) {
                    throw new OpenRouterNetworkException(
                        sprintf(
                            'Размер загружаемого файла превышает максимально допустимый (%d МБ): %s',
                            self::MAX_FILE_DOWNLOAD_SIZE / 1048576,
                            $url
                        )
                    );
                }
            }
        } finally {
            fclose($stream);
        }

        if ($contents === '') {
            throw new OpenRouterNetworkException('Загруженный файл пустой: ' . $url);
        }

        return $contents;
    }

    /**
     * Определяет имя файла на основе URL
     *
     * @param string $url URL файла
     * @return string Имя файла
     */
    private function deriveFileNameFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (is_string($path) && $path !== '') {
            $basename = basename($path);

            if ($basename !== '' && $basename !== '.' && $basename !== DIRECTORY_SEPARATOR) {
                return $basename;
            }
        }

        return 'file-' . md5($url) . '.bin';
    }

    /**
     * Определяет MIME-тип файла по его расширению
     *
     * @param string $filename Имя файла
     * @param string $defaultMimeType MIME тип по умолчанию
     * @return string MIME тип файла
     */
    private function guessMimeTypeFromFileName(string $filename, string $defaultMimeType): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($extension) {
            'mp3', 'mpeg3' => 'audio/mpeg',
            'm4a', 'mp4' => 'audio/mp4',
            'wav' => 'audio/wav',
            'webm' => 'audio/webm',
            'ogg', 'oga' => 'audio/ogg',
            'opus' => 'audio/opus',
            'flac' => 'audio/flac',
            'aac' => 'audio/aac',
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => $defaultMimeType,
        };
    }

    /**
     * Нормализует ссылку на медиафайл (URL или локальный путь преобразуется в data URI)
     *
     * @param string $reference URL или путь к файлу
     * @param string $defaultMimeType MIME тип по умолчанию
     * @return string URL или data URI
     * @throws OpenRouterValidationException Если файл не найден
     * @throws OpenRouterNetworkException Если не удалось прочитать содержимое файла
     */
    private function normalizeMediaReference(string $reference, string $defaultMimeType): string
    {
        if (filter_var($reference, FILTER_VALIDATE_URL) !== false) {
            return $reference;
        }

        if (!is_file($reference)) {
            throw new OpenRouterValidationException('Файл не найден: ' . $reference);
        }

        if (!is_readable($reference)) {
            throw new OpenRouterValidationException('Файл не доступен для чтения: ' . $reference);
        }

        $content = file_get_contents($reference);

        if ($content === false) {
            throw new OpenRouterNetworkException('Не удалось прочитать файл: ' . $reference);
        }

        $mimeType = mime_content_type($reference);

        if ($mimeType === false) {
            $mimeType = $defaultMimeType;
        }

        $encoded = base64_encode($content);

        return 'data:' . $mimeType . ';base64,' . $encoded;
    }

    /**
     * Записывает ошибку в лог при наличии логгера
     *
     * @param string $message Сообщение об ошибке
     * @param array<string, mixed> $context Контекст ошибки
     */
    private function logError(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->error($message, $context);
        }
    }
}

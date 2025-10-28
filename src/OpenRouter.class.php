<?php

declare(strict_types=1);

namespace App\Component;

use Exception;
use JsonException;
use RuntimeException;

/**
 * Класс для работы с OpenRouter API
 */
class OpenRouter
{
    private const BASE_URL = 'https://openrouter.ai/api/v1';
    private const DEFAULT_TIMEOUT = 60;

    private string $apiKey;
    private string $appName;
    private int $timeout;
    private ?Logger $logger;
    private Http $http;

    /**
     * @param array<string, mixed> $config Конфигурация OpenRouter API
     * @param Logger|null $logger Инстанс логгера
     * @throws Exception Если API ключ не установлен
     */
    public function __construct(array $config, ?Logger $logger = null)
    {
        $this->apiKey = (string)($config['api_key'] ?? '');
        $this->appName = (string)($config['app_name'] ?? 'BasicUtilitiesApp');
        $this->timeout = max(1, (int)($config['timeout'] ?? self::DEFAULT_TIMEOUT));
        $this->logger = $logger;

        if ($this->apiKey === '') {
            throw new Exception('API ключ OpenRouter не указан.');
        }

        $httpConfig = [
            'base_uri' => self::BASE_URL,
            'timeout' => $this->timeout,
            'connect_timeout' => $this->timeout,
        ];

        if (array_key_exists('retries', $config)) {
            $httpConfig['retries'] = (int)$config['retries'];
        }

        $this->http = new Http($httpConfig, $logger);
    }

    /**
     * Отправляет текстовый запрос и получает текстовый ответ (text2text)
     *
     * @param string $model Модель ИИ для использования
     * @param string $prompt Текстовый запрос
     * @param array<string, mixed> $options Дополнительные параметры
     * @return string Ответ модели
     * @throws Exception Если запрос завершился с ошибкой
     */
    public function text2text(string $model, string $prompt, array $options = []): string
    {
        $messages = [
            ['role' => 'user', 'content' => $prompt],
        ];

        $payload = array_merge([
            'model' => $model,
            'messages' => $messages,
        ], $options);

        $response = $this->sendRequest('/chat/completions', $payload);

        if (!isset($response['choices'][0]['message']['content'])) {
            throw new RuntimeException('Модель не вернула текстовый ответ.');
        }

        return (string)$response['choices'][0]['message']['content'];
    }

    /**
     * Отправляет текстовый запрос и получает изображение (text2image)
     *
     * @param string $model Модель генерации изображений
     * @param string $prompt Описание изображения
     * @param array<string, mixed> $options Дополнительные параметры
     * @return string URL сгенерированного изображения
     * @throws Exception Если запрос завершился с ошибкой
     */
    public function text2image(string $model, string $prompt, array $options = []): string
    {
        $payload = array_merge([
            'model' => $model,
            'prompt' => $prompt,
        ], $options);

        $response = $this->sendRequest('/images/generations', $payload);

        if (!isset($response['data'][0]['url'])) {
            throw new RuntimeException('Модель не вернула URL изображения.');
        }

        return (string)$response['data'][0]['url'];
    }

    /**
     * Отправляет изображение и получает текстовое описание (image2text)
     *
     * @param string $model Модель распознавания изображений
     * @param string $imageUrl URL изображения
     * @param string $question Вопрос к изображению
     * @param array<string, mixed> $options Дополнительные параметры
     * @return string Описание или ответ модели
     * @throws Exception Если запрос завершился с ошибкой
     */
    public function image2text(string $model, string $imageUrl, string $question = 'Опиши это изображение', array $options = []): string
    {
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
            throw new RuntimeException('Модель не вернула текстовый ответ.');
        }

        return (string)$response['choices'][0]['message']['content'];
    }

    /**
     * Преобразует аудио в текст (audio2text)
     *
     * @param string $model Модель распознавания речи (например, openai/whisper-1)
     * @param string $audioSource URL аудиофайла или путь к локальному файлу
     * @param array<string, mixed> $options Дополнительные параметры запроса
     * @return string Распознанный текст
     * @throws RuntimeException Если запрос завершился с ошибкой или ответ не содержит текста
     * @throws JsonException Если не удалось декодировать ответ API
     */
    public function audio2text(string $model, string $audioSource, array $options = []): string
    {
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
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'HTTP-Referer' => $this->appName,
            ],
        ]);

        $statusCode = $response->getStatusCode();
        $body = (string)$response->getBody();

        if ($statusCode >= 400) {
            $this->logError('Сервер OpenRouter вернул ошибку при транскрибации аудио', [
                'status_code' => $statusCode,
                'response' => $body,
            ]);

            throw new RuntimeException('Сервер вернул код ошибки: ' . $statusCode . ' | ' . $body);
        }

        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        if (isset($decoded['text'])) {
            return (string)$decoded['text'];
        }

        if (isset($decoded['data'][0]['text'])) {
            return (string)$decoded['data'][0]['text'];
        }

        throw new RuntimeException('Модель не вернула текстовую транскрипцию.');
    }

    /**
     * Извлекает текст из PDF документа (pdf2text)
     *
     * @param string $model Модель анализа документов (например, openai/gpt-4-vision или claude-3)
     * @param string $pdfUrl URL PDF файла или путь к локальному файлу
     * @param string $instruction Инструкция для обработки (по умолчанию - извлечение текста)
     * @param array<string, mixed> $options Дополнительные параметры
     * @return string Извлеченный текст
     * @throws Exception Если запрос завершился с ошибкой
     */
    public function pdf2text(string $model, string $pdfUrl, string $instruction = 'Извлеки весь текст из этого PDF документа', array $options = []): string
    {
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
            throw new RuntimeException('Модель не вернула текстовый ответ.');
        }

        return (string)$response['choices'][0]['message']['content'];
    }

    /**
     * Отправляет текстовый запрос с поддержкой streaming (потоковая передача)
     *
     * @param string $model Модель ИИ для использования
     * @param string $prompt Текстовый запрос
     * @param callable $callback Функция-обработчик частей ответа
     * @param array<string, mixed> $options Дополнительные параметры
     * @throws Exception Если запрос завершился с ошибкой
     */
    public function textStream(string $model, string $prompt, callable $callback, array $options = []): void
    {
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
     * Выполняет стандартный HTTP-запрос к API
     *
     * @param string $endpoint Endpoint API
     * @param array<string, mixed> $payload Данные для отправки
     * @return array<string, mixed> Декодированный ответ API
     * @throws RuntimeException Если запрос завершился с ошибкой
     * @throws JsonException Если не удалось закодировать JSON
     */
    private function sendRequest(string $endpoint, array $payload): array
    {
        $response = $this->http->request('POST', $endpoint, [
            'json' => $payload,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => $this->appName,
            ],
        ]);

        $statusCode = $response->getStatusCode();
        $body = (string)$response->getBody();

        if ($statusCode >= 400) {
            $this->logError('Сервер OpenRouter вернул ошибку', ['status_code' => $statusCode, 'response' => $body]);
            throw new RuntimeException('Сервер вернул код ошибки: ' . $statusCode . ' | ' . $body);
        }

        return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Выполняет streaming HTTP-запрос к API
     *
     * @param string $endpoint Endpoint API
     * @param array<string, mixed> $payload Данные для отправки
     * @param callable $callback Функция для обработки потоков данных
     * @throws RuntimeException Если запрос завершился с ошибкой
     * @throws JsonException Если не удалось закодировать JSON
     */
    private function sendStreamRequest(string $endpoint, array $payload, callable $callback): void
    {
        $buffer = '';

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
                    $decoded = json_decode($json, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        continue;
                    }

                    if (isset($decoded['choices'][0]['delta']['content'])) {
                        $callback($decoded['choices'][0]['delta']['content']);
                    }
                }
            }
        }, [
            'json' => $payload,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => $this->appName,
            ],
        ]);
    }

    /**
     * Подготавливает часть multipart-запроса с файлом.
     *
     * @param string $fieldName Имя поля в multipart запросе
     * @param string $reference URL или путь к файлу
     * @param string $defaultMimeType MIME тип по умолчанию
     * @return array<string, mixed>
     * @throws RuntimeException Если файл не найден, не доступен или не удалось его прочитать
     */
    private function prepareMultipartFile(string $fieldName, string $reference, string $defaultMimeType): array
    {
        if (filter_var($reference, FILTER_VALIDATE_URL)) {
            $contents = $this->downloadFile($reference);
            $filename = $this->deriveFileNameFromUrl($reference);
            $mimeType = $this->guessMimeTypeFromFileName($filename, $defaultMimeType);
        } else {
            if (!is_file($reference) || !is_readable($reference)) {
                throw new RuntimeException('Файл не найден или недоступен: ' . $reference);
            }

            $contents = file_get_contents($reference);

            if ($contents === false) {
                throw new RuntimeException('Не удалось прочитать файл: ' . $reference);
            }

            $filename = basename($reference);

            if ($filename === '' || $filename === '.' || $filename === DIRECTORY_SEPARATOR) {
                $filename = 'audio-file.bin';
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
     * Преобразует значение опции в строку для multipart-запроса.
     *
     * @param mixed $value Значение опции
     * @return string
     *
     * @throws RuntimeException Если не удалось сериализовать значение
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
            throw new RuntimeException('Не удалось сериализовать параметр multipart запроса.', 0, $exception);
        }
    }

    /**
     * Загружает файл по URL.
     *
     * @param string $url URL файла
     * @return string Содержимое файла
     * @throws RuntimeException Если не удалось загрузить файл
     */
    private function downloadFile(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => $this->timeout,
                'follow_location' => 1,
            ],
        ]);

        $contents = @file_get_contents($url, false, $context);

        if ($contents === false) {
            throw new RuntimeException('Не удалось загрузить файл по URL: ' . $url);
        }

        return $contents;
    }

    /**
     * Определяет имя файла на основе URL.
     *
     * @param string $url URL файла
     * @return string Имя файла
     */
    private function deriveFileNameFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (is_string($path)) {
            $basename = basename($path);

            if ($basename !== '' && $basename !== '.' && $basename !== DIRECTORY_SEPARATOR) {
                return $basename;
            }
        }

        return 'audio-' . md5($url) . '.bin';
    }

    /**
     * Определяет MIME-тип файла по его расширению.
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
            default => $defaultMimeType,
        };
    }

    /**
     * Нормализует ссылку на медиафайл (URL или локальный путь в data URI)
     *
     * @param string $reference URL или путь к файлу
     * @param string $defaultMimeType MIME тип по умолчанию, если не удалось определить автоматически
     * @return string URL или data URI
     * @throws RuntimeException Если файл не найден или не удалось прочитать содержимое
     */
    private function normalizeMediaReference(string $reference, string $defaultMimeType): string
    {
        if (filter_var($reference, FILTER_VALIDATE_URL)) {
            return $reference;
        }

        if (!is_file($reference) || !is_readable($reference)) {
            throw new RuntimeException('Файл не найден или недоступен: ' . $reference);
        }

        $content = file_get_contents($reference);

        if ($content === false) {
            throw new RuntimeException('Не удалось прочитать файл: ' . $reference);
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

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
 * - Работа с PDF документами (pdf2text)
 * - Работа с аудио (audio2text)
 * - Потоковая передача текста (textStream)
 * 
 * @link https://openrouter.ai/docs/quickstart Официальная документация OpenRouter API
 * @link https://openrouter.ai/docs/features/multimodal Multimodal функции
 */
class OpenRouter
{
    private const BASE_URL = 'https://openrouter.ai/api/v1/';
    private const DEFAULT_TIMEOUT = 60;

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
     * Генерирует изображение на основе текстового описания (text2image)
     *
     * @param string $model Модель генерации изображений (например, "openai/gpt-5-image", "google/gemini-2.5-flash-image")
     * @param string $prompt Текстовое описание изображения для генерации
     * @param array<string, mixed> $options Дополнительные параметры запроса:
     *                                      - size (string): Размер изображения (например, "1024x1024")
     *                                      - quality (string): Качество изображения ("standard", "hd")
     *                                      - n (int): Количество изображений для генерации
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
     * Извлекает текст из PDF документа (pdf2text)
     *
     * @param string $model Модель для анализа PDF (например, "openai/gpt-4-vision-preview", "anthropic/claude-3-opus")
     * @param string $pdfUrl URL PDF документа для анализа
     * @param string $instruction Инструкция для обработки документа
     * @param array<string, mixed> $options Дополнительные параметры запроса
     * @return string Извлеченный текст или результат анализа
     * @throws OpenRouterValidationException Если параметры невалидны
     * @throws OpenRouterApiException Если API вернул ошибку
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

        $messages = [
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $instruction],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => $pdfUrl,
                        ],
                    ],
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
     * @param string $model Модель распознавания речи (например, "openai/gpt-4o-audio-preview", "google/gemini-2.5-flash")
     * @param string $audioUrl URL аудиофайла для распознавания
     * @param array<string, mixed> $options Дополнительные параметры запроса:
     *                                      - language (string): Код языка (например, "ru", "en")
     *                                      - prompt (string): Подсказка для улучшения точности
     * @return string Распознанный текст
     * @throws OpenRouterValidationException Если параметры невалидны
     * @throws OpenRouterApiException Если API вернул ошибку
     * @throws OpenRouterException Если модель не вернула транскрипцию
     */
    public function audio2text(string $model, string $audioUrl, array $options = []): string
    {
        $this->validateNotEmpty($model, 'model');
        $this->validateNotEmpty($audioUrl, 'audioUrl');

        $messages = [
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'audio_url',
                        'audio_url' => [
                            'url' => $audioUrl,
                        ],
                    ],
                ],
            ],
        ];

        if (isset($options['prompt'])) {
            array_unshift($messages[0]['content'], [
                'type' => 'text',
                'text' => $options['prompt'],
            ]);
            unset($options['prompt']);
        }

        $payload = array_merge([
            'model' => $model,
            'messages' => $messages,
        ], $options);

        $response = $this->sendRequest('/chat/completions', $payload);

        if (!isset($response['choices'][0]['message']['content'])) {
            throw new OpenRouterException('Модель не вернула транскрипцию.');
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

        $response = $this->http->request('POST', ltrim($endpoint, '/'), [
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

        $this->http->requestStream('POST', ltrim($endpoint, '/'), function (string $chunk) use (&$buffer, $callback): void {
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

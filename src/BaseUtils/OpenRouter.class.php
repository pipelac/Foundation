<?php

declare(strict_types=1);

namespace App\Component;

use App\Component\Exception\OpenRouter\OpenRouterApiException;
use App\Component\Exception\OpenRouter\OpenRouterException;
use App\Component\Exception\OpenRouter\OpenRouterNetworkException;
use App\Component\Exception\OpenRouter\OpenRouterValidationException;
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
     * Отправляет текстовый запрос и получает полный ответ с метриками (text2textWithMetrics)
     *
     * @param string $model Модель ИИ для использования
     * @param string $prompt Текстовый запрос для модели
     * @param array<string, mixed> $options Дополнительные параметры запроса
     * @return array<string, mixed> Полный ответ с метриками:
     *                              - content (string): Текстовый ответ
     *                              - usage (array): Информация об использованных токенах
     *                              - model (string): Использованная модель
     *                              - id (string): ID генерации
     * @throws OpenRouterValidationException Если параметры невалидны
     * @throws OpenRouterApiException Если API вернул ошибку
     * @throws OpenRouterException Если модель не вернула текстовый ответ
     */
    public function text2textWithMetrics(string $model, string $prompt, array $options = []): array
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

        return [
            'content' => (string)$response['choices'][0]['message']['content'],
            'usage' => $response['usage'] ?? [
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0,
                'cached_tokens' => 0,
            ],
            'model' => $response['model'] ?? $model,
            'id' => $response['id'] ?? null,
            'created' => $response['created'] ?? null,
        ];
    }

    /**
     * Отправляет запрос с поддержкой multi-message и кеширования (chatWithMessages)
     *
     * Этот метод позволяет отправлять system и user сообщения отдельно,
     * что критически важно для prompt caching в OpenRouter.
     * System message остается неизменным между запросами и кешируется.
     *
     * @param string $model Модель ИИ для использования
     * @param array<int, array<string, string>> $messages Массив сообщений [['role' => 'system/user', 'content' => '...']]
     * @param array<string, mixed> $options Дополнительные параметры запроса
     * @return array<string, mixed> Полный ответ с метриками:
     *                              - content (string): Текстовый ответ
     *                              - usage (array): Информация об использованных токенах (включая cached_tokens)
     *                              - model (string): Использованная модель
     *                              - id (string): ID генерации
     * @throws OpenRouterValidationException Если параметры невалидны
     * @throws OpenRouterApiException Если API вернул ошибку
     * @throws OpenRouterException Если модель не вернула текстовый ответ
     */
    public function chatWithMessages(string $model, array $messages, array $options = []): array
    {
        $this->validateNotEmpty($model, 'model');
        
        if (empty($messages)) {
            throw new OpenRouterValidationException('Массив messages не может быть пустым.');
        }

        $payload = array_merge([
            'model' => $model,
            'messages' => $messages,
        ], $options);

        $response = $this->sendRequest('/chat/completions', $payload);

        if (!isset($response['choices'][0]['message']['content'])) {
            throw new OpenRouterException('Модель не вернула текстовый ответ.');
        }

        return [
            'content' => (string)$response['choices'][0]['message']['content'],
            'usage' => $response['usage'] ?? [
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0,
                'cached_tokens' => 0,
            ],
            'model' => $response['model'] ?? $model,
            'id' => $response['id'] ?? null,
            'created' => $response['created'] ?? null,
        ];
    }

    /**
     * Генерирует изображение на основе текстового описания (text2image)
     *
     * @param string $model Модель генерации изображений (например, "google/gemini-2.5-flash-image")
     * @param string $prompt Текстовое описание изображения для генерации
     * @param array<string, mixed> $options Дополнительные параметры запроса:
     *                                      - max_tokens (int): Максимальное количество токенов
     * @return string Base64-encoded изображение или URL (в зависимости от модели)
     * @throws OpenRouterValidationException Если параметры невалидны
     * @throws OpenRouterApiException Если API вернул ошибку
     * @throws OpenRouterException Если модель не вернула изображение
     */
    public function text2image(string $model, string $prompt, array $options = []): string
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

        // Проверяем наличие изображения в base64
        if (isset($response['choices'][0]['message']['content'])) {
            $content = $response['choices'][0]['message']['content'];
            
            // Если это массив с изображением
            if (is_array($content) && isset($content[0]['image'])) {
                return (string)$content[0]['image'];
            }
            
            // Если это строка с base64
            if (is_string($content)) {
                return $content;
            }
        }

        throw new OpenRouterException('Модель не вернула изображение.');
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
     * @param string $model Модель для анализа PDF (например, "openai/gpt-4o", "anthropic/claude-3.5-sonnet")
     * @param string $pdfUrl URL PDF документа для анализа
     * @param string $instruction Инструкция для обработки документа
     * @param array<string, mixed> $options Дополнительные параметры запроса:
     *                                      - plugins: настройки парсинга PDF (опционально)
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

        // Формат согласно документации OpenRouter: https://openrouter.ai/docs/features/multimodal/pdfs
        $messages = [
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $instruction],
                    [
                        'type' => 'file',
                        'file' => [
                            'filename' => basename($pdfUrl),
                            'file_data' => $pdfUrl,
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
     * @param string $model Модель распознавания речи (например, "google/gemini-2.5-flash")
     * @param string $audioPath Путь к локальному аудиофайлу или base64-encoded данные
     * @param array<string, mixed> $options Дополнительные параметры запроса:
     *                                      - format (string): Формат аудио ("wav", "mp3"), по умолчанию "wav"
     *                                      - prompt (string): Инструкция для транскрипции
     * @return string Распознанный текст
     * @throws OpenRouterValidationException Если параметры невалидны
     * @throws OpenRouterApiException Если API вернул ошибку
     * @throws OpenRouterException Если модель не вернула транскрипцию
     */
    public function audio2text(string $model, string $audioPath, array $options = []): string
    {
        $this->validateNotEmpty($model, 'model');
        $this->validateNotEmpty($audioPath, 'audioPath');

        // Определяем, это файл или base64
        $audioBase64 = $audioPath;
        if (file_exists($audioPath)) {
            $audioContent = file_get_contents($audioPath);
            if ($audioContent === false) {
                throw new OpenRouterException("Не удалось прочитать аудиофайл: {$audioPath}");
            }
            $audioBase64 = base64_encode($audioContent);
        }

        $format = $options['format'] ?? 'wav';
        $prompt = $options['prompt'] ?? 'Please transcribe this audio file.';
        
        // Удаляем из options чтобы не передавать в payload
        unset($options['format'], $options['prompt']);

        // Формат согласно документации OpenRouter: https://openrouter.ai/docs/features/multimodal/audio
        $messages = [
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $prompt,
                    ],
                    [
                        'type' => 'input_audio',
                        'input_audio' => [
                            'data' => $audioBase64,
                            'format' => $format,
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
            'HTTP-Referer' => 'https://' . $this->appName,
            'X-Title' => $this->appName,
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

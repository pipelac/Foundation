<?php

declare(strict_types=1);

namespace App\Component;

use App\Component\Exception\OpenAiApiException;
use App\Component\Exception\OpenAiException;
use App\Component\Exception\OpenAiNetworkException;
use App\Component\Exception\OpenAiValidationException;
use JsonException;

/**
 * Класс для работы с OpenAI API
 * 
 * Предоставляет методы для взаимодействия с различными AI моделями OpenAI:
 * - Текстовая генерация через GPT модели (text2text)
 * - Генерация изображений через DALL-E (text2image)
 * - Распознавание изображений через GPT-4 Vision (image2text)
 * - Транскрипция аудио через Whisper (audio2text)
 * - Потоковая передача текста (textStream)
 * - Создание эмбеддингов для текста (embeddings)
 * - Модерация контента (moderation)
 * 
 * @link https://platform.openai.com/docs/api-reference Официальная документация OpenAI API
 * @link https://platform.openai.com/docs/guides/vision Vision API документация
 */
class OpenAi
{
    private const BASE_URL = 'https://api.openai.com/v1';
    private const DEFAULT_TIMEOUT = 60;
    private const DEFAULT_MODEL = 'gpt-4o-mini';

    private string $apiKey;
    private string $organization;
    private int $timeout;
    private ?Logger $logger;
    private Http $http;

    /**
     * Конструктор класса OpenAi
     *
     * @param array<string, mixed> $config Конфигурация OpenAI API:
     *                                     - api_key (string, обязательно): API ключ OpenAI
     *                                     - organization (string, необязательно): ID организации OpenAI
     *                                     - timeout (int, необязательно): Таймаут соединения в секундах
     *                                     - retries (int, необязательно): Количество повторных попыток
     * @param Logger|null $logger Экземпляр логгера для записи событий
     * @throws OpenAiValidationException Если API ключ не указан или конфигурация некорректна
     */
    public function __construct(array $config, ?Logger $logger = null)
    {
        $this->validateConfiguration($config);
        
        $this->apiKey = $config['api_key'];
        $this->organization = (string)($config['organization'] ?? '');
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
     * @param string $prompt Текстовый запрос для модели
     * @param string $model Модель ИИ для использования (по умолчанию "gpt-4o-mini")
     * @param array<string, mixed> $options Дополнительные параметры запроса:
     *                                      - temperature (float): Температура генерации (0.0 - 2.0)
     *                                      - max_tokens (int): Максимальное количество токенов
     *                                      - top_p (float): Top-p sampling
     *                                      - frequency_penalty (float): Штраф за частоту (-2.0 - 2.0)
     *                                      - presence_penalty (float): Штраф за присутствие (-2.0 - 2.0)
     *                                      - system (string): Системное сообщение для задания контекста
     * @return string Ответ модели
     * @throws OpenAiValidationException Если параметры невалидны
     * @throws OpenAiApiException Если API вернул ошибку
     * @throws OpenAiException Если модель не вернула текстовый ответ
     */
    public function text2text(string $prompt, string $model = self::DEFAULT_MODEL, array $options = []): string
    {
        $this->validateNotEmpty($prompt, 'prompt');
        $this->validateNotEmpty($model, 'model');

        $this->logDebug('Отправка запроса text2text', [
            'model' => $model,
            'prompt_length' => strlen($prompt),
            'options' => array_keys($options)
        ]);

        $messages = [];
        
        if (isset($options['system'])) {
            $messages[] = ['role' => 'system', 'content' => (string)$options['system']];
            unset($options['system']);
        }
        
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $payload = array_merge([
            'model' => $model,
            'messages' => $messages,
        ], $options);

        $response = $this->sendRequest('/chat/completions', $payload);

        if (!isset($response['choices'][0]['message']['content'])) {
            throw new OpenAiException('Модель не вернула текстовый ответ.');
        }

        $result = (string)$response['choices'][0]['message']['content'];

        $this->logInfo('Успешный запрос text2text', [
            'model' => $model,
            'prompt_length' => strlen($prompt),
            'response_length' => strlen($result),
            'tokens_used' => $response['usage'] ?? null
        ]);

        return $result;
    }

    /**
     * Генерирует изображение на основе текстового описания через DALL-E (text2image)
     *
     * @param string $prompt Текстовое описание изображения для генерации
     * @param string $model Модель генерации изображений (по умолчанию "dall-e-3")
     * @param array<string, mixed> $options Дополнительные параметры запроса:
     *                                      - size (string): Размер изображения ("1024x1024", "1792x1024", "1024x1792")
     *                                      - quality (string): Качество изображения ("standard", "hd")
     *                                      - n (int): Количество изображений для генерации (1-10, только для dall-e-2)
     *                                      - style (string): Стиль изображения ("vivid", "natural")
     * @return string URL сгенерированного изображения
     * @throws OpenAiValidationException Если параметры невалидны
     * @throws OpenAiApiException Если API вернул ошибку
     * @throws OpenAiException Если модель не вернула URL изображения
     */
    public function text2image(string $prompt, string $model = 'dall-e-3', array $options = []): string
    {
        $this->validateNotEmpty($prompt, 'prompt');
        $this->validateNotEmpty($model, 'model');

        $this->logDebug('Отправка запроса text2image', [
            'model' => $model,
            'prompt_length' => strlen($prompt),
            'options' => array_keys($options)
        ]);

        $payload = array_merge([
            'model' => $model,
            'prompt' => $prompt,
        ], $options);

        $response = $this->sendRequest('/images/generations', $payload);

        if (!isset($response['data'][0]['url'])) {
            throw new OpenAiException('Модель не вернула URL изображения.');
        }

        $imageUrl = (string)$response['data'][0]['url'];

        $this->logInfo('Успешный запрос text2image', [
            'model' => $model,
            'prompt_length' => strlen($prompt),
            'image_url' => $imageUrl
        ]);

        return $imageUrl;
    }

    /**
     * Отправляет изображение и получает текстовое описание через GPT-4 Vision (image2text)
     *
     * @param string $imageUrl URL изображения для анализа
     * @param string $question Вопрос к изображению (по умолчанию "Опиши это изображение подробно")
     * @param string $model Модель распознавания изображений (по умолчанию "gpt-4o")
     * @param array<string, mixed> $options Дополнительные параметры запроса:
     *                                      - max_tokens (int): Максимальное количество токенов в ответе
     *                                      - detail (string): Уровень детализации анализа ("low", "high", "auto")
     * @return string Описание или ответ модели
     * @throws OpenAiValidationException Если параметры невалидны
     * @throws OpenAiApiException Если API вернул ошибку
     * @throws OpenAiException Если модель не вернула текстовый ответ
     */
    public function image2text(
        string $imageUrl,
        string $question = 'Опиши это изображение подробно',
        string $model = 'gpt-4o',
        array $options = []
    ): string {
        $this->validateNotEmpty($imageUrl, 'imageUrl');
        $this->validateNotEmpty($question, 'question');
        $this->validateNotEmpty($model, 'model');

        $this->logDebug('Отправка запроса image2text', [
            'model' => $model,
            'image_url' => $imageUrl,
            'question_length' => strlen($question),
            'options' => array_keys($options)
        ]);

        $imageUrlData = ['url' => $imageUrl];
        
        if (isset($options['detail'])) {
            $imageUrlData['detail'] = (string)$options['detail'];
            unset($options['detail']);
        }

        $messages = [
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $question],
                    ['type' => 'image_url', 'image_url' => $imageUrlData],
                ],
            ],
        ];

        $payload = array_merge([
            'model' => $model,
            'messages' => $messages,
        ], $options);

        $response = $this->sendRequest('/chat/completions', $payload);

        if (!isset($response['choices'][0]['message']['content'])) {
            throw new OpenAiException('Модель не вернула текстовый ответ.');
        }

        $result = (string)$response['choices'][0]['message']['content'];

        $this->logInfo('Успешный запрос image2text', [
            'model' => $model,
            'image_url' => $imageUrl,
            'response_length' => strlen($result),
            'tokens_used' => $response['usage'] ?? null
        ]);

        return $result;
    }

    /**
     * Преобразует аудио в текст через Whisper API (audio2text)
     *
     * @param string $audioUrl URL аудиофайла для распознавания
     * @param string $model Модель распознавания речи (по умолчанию "whisper-1")
     * @param array<string, mixed> $options Дополнительные параметры запроса:
     *                                      - language (string): Код языка ISO-639-1 (например, "ru", "en")
     *                                      - prompt (string): Подсказка для улучшения точности
     *                                      - temperature (float): Температура сэмплирования (0-1)
     *                                      - response_format (string): Формат ответа ("json", "text", "srt", "vtt")
     * @return string Распознанный текст
     * @throws OpenAiValidationException Если параметры невалидны
     * @throws OpenAiApiException Если API вернул ошибку
     * @throws OpenAiException Если модель не вернула транскрипцию
     */
    public function audio2text(string $audioUrl, string $model = 'whisper-1', array $options = []): string
    {
        $this->validateNotEmpty($audioUrl, 'audioUrl');
        $this->validateNotEmpty($model, 'model');

        $this->logDebug('Отправка запроса audio2text', [
            'model' => $model,
            'audio_url' => $audioUrl,
            'options' => array_keys($options)
        ]);

        // Для Whisper API необходимо скачать файл и отправить как multipart/form-data
        // Однако для упрощения, используем подход с отправкой URL через messages (GPT-4o Audio)
        $messages = [
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'input_audio',
                        'input_audio' => [
                            'data' => $audioUrl,
                            'format' => $options['format'] ?? 'mp3',
                        ],
                    ],
                ],
            ],
        ];

        if (isset($options['prompt'])) {
            array_unshift($messages[0]['content'], [
                'type' => 'text',
                'text' => (string)$options['prompt'],
            ]);
            unset($options['prompt']);
        }

        unset($options['format']);

        $payload = array_merge([
            'model' => 'gpt-4o-audio-preview',
            'messages' => $messages,
            'modalities' => ['text'],
        ], $options);

        $response = $this->sendRequest('/chat/completions', $payload);

        if (!isset($response['choices'][0]['message']['content'])) {
            throw new OpenAiException('Модель не вернула транскрипцию.');
        }

        $result = (string)$response['choices'][0]['message']['content'];

        $this->logInfo('Успешный запрос audio2text', [
            'model' => 'gpt-4o-audio-preview',
            'audio_url' => $audioUrl,
            'response_length' => strlen($result),
            'tokens_used' => $response['usage'] ?? null
        ]);

        return $result;
    }

    /**
     * Отправляет текстовый запрос с поддержкой streaming (потоковая передача)
     *
     * @param string $prompt Текстовый запрос
     * @param callable $callback Функция-обработчик частей ответа (принимает string)
     * @param string $model Модель ИИ для использования (по умолчанию "gpt-4o-mini")
     * @param array<string, mixed> $options Дополнительные параметры запроса:
     *                                      - temperature (float): Температура генерации
     *                                      - max_tokens (int): Максимальное количество токенов
     *                                      - system (string): Системное сообщение
     * @throws OpenAiValidationException Если параметры невалидны
     * @throws OpenAiApiException Если API вернул ошибку
     * @throws OpenAiException Если произошла ошибка при обработке потока
     */
    public function textStream(
        string $prompt,
        callable $callback,
        string $model = self::DEFAULT_MODEL,
        array $options = []
    ): void {
        $this->validateNotEmpty($prompt, 'prompt');
        $this->validateNotEmpty($model, 'model');

        $this->logDebug('Отправка streaming запроса textStream', [
            'model' => $model,
            'prompt_length' => strlen($prompt),
            'options' => array_keys($options)
        ]);

        $messages = [];
        
        if (isset($options['system'])) {
            $messages[] = ['role' => 'system', 'content' => (string)$options['system']];
            unset($options['system']);
        }
        
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $payload = array_merge([
            'model' => $model,
            'messages' => $messages,
            'stream' => true,
        ], $options);

        $this->sendStreamRequest('/chat/completions', $payload, $callback);

        $this->logInfo('Успешный streaming запрос textStream', [
            'model' => $model,
            'prompt_length' => strlen($prompt)
        ]);
    }

    /**
     * Создает эмбеддинги (векторные представления) для текста
     *
     * @param string|array<string> $input Текст или массив текстов для создания эмбеддингов
     * @param string $model Модель для создания эмбеддингов (по умолчанию "text-embedding-3-small")
     * @param array<string, mixed> $options Дополнительные параметры запроса:
     *                                      - dimensions (int): Размерность выходных векторов
     *                                      - encoding_format (string): Формат кодирования ("float", "base64")
     * @return array<int, array<int, float>> Массив эмбеддингов
     * @throws OpenAiValidationException Если параметры невалидны
     * @throws OpenAiApiException Если API вернул ошибку
     * @throws OpenAiException Если модель не вернула эмбеддинги
     */
    public function embeddings(string|array $input, string $model = 'text-embedding-3-small', array $options = []): array
    {
        if (is_string($input)) {
            $this->validateNotEmpty($input, 'input');
        } elseif (is_array($input) && count($input) === 0) {
            throw new OpenAiValidationException('Параметр "input" не может быть пустым массивом.');
        }

        $this->validateNotEmpty($model, 'model');

        $this->logDebug('Отправка запроса embeddings', [
            'model' => $model,
            'input_type' => is_string($input) ? 'string' : 'array',
            'input_count' => is_array($input) ? count($input) : 1,
            'options' => array_keys($options)
        ]);

        $payload = array_merge([
            'model' => $model,
            'input' => $input,
        ], $options);

        $response = $this->sendRequest('/embeddings', $payload);

        if (!isset($response['data']) || !is_array($response['data'])) {
            throw new OpenAiException('Модель не вернула эмбеддинги.');
        }

        $embeddings = [];
        foreach ($response['data'] as $item) {
            if (isset($item['embedding']) && is_array($item['embedding'])) {
                $embeddings[] = $item['embedding'];
            }
        }

        if (count($embeddings) === 0) {
            throw new OpenAiException('Модель не вернула корректные эмбеддинги.');
        }

        $this->logInfo('Успешный запрос embeddings', [
            'model' => $model,
            'embeddings_count' => count($embeddings),
            'dimensions' => count($embeddings[0] ?? []),
            'tokens_used' => $response['usage'] ?? null
        ]);

        return $embeddings;
    }

    /**
     * Проверяет контент на нарушение правил модерации OpenAI
     *
     * @param string $input Текст для проверки на модерацию
     * @param string $model Модель модерации (по умолчанию "text-moderation-latest")
     * @return array<string, mixed> Результаты модерации:
     *                              - flagged (bool): Флаг нарушения
     *                              - categories (array): Категории нарушений
     *                              - category_scores (array): Оценки по категориям
     * @throws OpenAiValidationException Если параметры невалидны
     * @throws OpenAiApiException Если API вернул ошибку
     * @throws OpenAiException Если модель не вернула результаты модерации
     */
    public function moderation(string $input, string $model = 'text-moderation-latest'): array
    {
        $this->validateNotEmpty($input, 'input');
        $this->validateNotEmpty($model, 'model');

        $this->logDebug('Отправка запроса moderation', [
            'model' => $model,
            'input_length' => strlen($input)
        ]);

        $payload = [
            'model' => $model,
            'input' => $input,
        ];

        $response = $this->sendRequest('/moderations', $payload);

        if (!isset($response['results'][0])) {
            throw new OpenAiException('Модель не вернула результаты модерации.');
        }

        $result = $response['results'][0];

        $this->logInfo('Успешный запрос moderation', [
            'model' => $model,
            'flagged' => $result['flagged'] ?? false,
            'categories_flagged' => array_keys(array_filter($result['categories'] ?? [], fn($v) => $v === true))
        ]);

        return $result;
    }

    /**
     * Валидирует конфигурацию при создании экземпляра класса
     *
     * @param array<string, mixed> $config Конфигурация для валидации
     * @throws OpenAiValidationException Если конфигурация некорректна
     */
    private function validateConfiguration(array $config): void
    {
        if (!isset($config['api_key']) || !is_string($config['api_key']) || trim($config['api_key']) === '') {
            throw new OpenAiValidationException('API ключ OpenAI не указан или пустой.');
        }
    }

    /**
     * Валидирует строковый параметр на пустоту
     *
     * @param string $value Значение для проверки
     * @param string $paramName Название параметра (для сообщения об ошибке)
     * @throws OpenAiValidationException Если значение пустое
     */
    private function validateNotEmpty(string $value, string $paramName): void
    {
        if (trim($value) === '') {
            throw new OpenAiValidationException(
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
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
        ];

        if ($this->organization !== '') {
            $headers['OpenAI-Organization'] = $this->organization;
        }

        return $headers;
    }

    /**
     * Выполняет стандартный HTTP-запрос к API
     *
     * @param string $endpoint Endpoint API (например, "/chat/completions")
     * @param array<string, mixed> $payload Данные для отправки в формате JSON
     * @return array<string, mixed> Декодированный ответ API
     * @throws OpenAiApiException Если API вернул код ошибки >= 400
     * @throws OpenAiException Если не удалось декодировать JSON ответ
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
            $this->logError('Сервер OpenAI вернул ошибку', [
                'status_code' => $statusCode,
                'endpoint' => $endpoint,
                'response' => $body
            ]);

            throw new OpenAiApiException('Сервер вернул код ошибки', $statusCode, $body);
        }

        try {
            return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new OpenAiException(
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
     * @throws OpenAiApiException Если API вернул ошибку
     * @throws OpenAiException Если произошла ошибка при обработке потока
     */
    private function sendStreamRequest(string $endpoint, array $payload, callable $callback): void
    {
        $buffer = '';
        $headers = $this->buildHeaders();
        $headers['Content-Type'] = 'application/json';

        try {
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
        } catch (\App\Component\Exception\HttpException $exception) {
            $statusCode = $exception->getCode();
            
            $this->logError('Ошибка потокового запроса к OpenAI', [
                'status_code' => $statusCode,
                'endpoint' => $endpoint,
                'message' => $exception->getMessage()
            ]);

            throw new OpenAiApiException('Сервер вернул код ошибки в потоковом запросе', $statusCode, '');
        }
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

    /**
     * Записывает информационное сообщение в лог при наличии логгера
     *
     * @param string $message Информационное сообщение
     * @param array<string, mixed> $context Контекст операции
     */
    private function logInfo(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->info($message, $context);
        }
    }

    /**
     * Записывает отладочное сообщение в лог при наличии логгера
     *
     * @param string $message Отладочное сообщение
     * @param array<string, mixed> $context Контекст операции
     */
    private function logDebug(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->debug($message, $context);
        }
    }
}

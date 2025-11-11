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
 * - Работа с видео (video2text)
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
     *                              - detailed_metrics (array): Детальные метрики для записи в БД
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
            'detailed_metrics' => $this->parseDetailedMetrics($response),
        ];
    }

    /**
     * Генерирует изображение на основе текстового описания (text2image)
     *
     * Поддерживает модели генерации изображений через OpenRouter API.
     * Модели возвращают изображение в формате:
     * - data URI (data:image/png;base64,...)
     * - Base64 строка
     * - URL изображения
     *
     * @param string $model Модель генерации изображений:
     *                      - google/gemini-2.5-flash-image-preview
     *                      - google/gemini-2.5-flash-image
     *                      - anthropic/claude-3-5-sonnet (с text+image генерацией)
     * @param string $prompt Текстовое описание изображения для генерации
     * @param array<string, mixed> $options Дополнительные параметры запроса:
     *                                      - max_tokens (int): Максимальное количество токенов (по умолчанию 4096)
     *                                      - size (string): Размер изображения, если поддерживается моделью
     *                                      - quality (string): Качество изображения, если поддерживается
     * @return string Data URI изображения (data:image/png;base64,...) или URL
     * @throws OpenRouterValidationException Если параметры невалидны
     * @throws OpenRouterApiException Если API вернул ошибку
     * @throws OpenRouterException Если модель не вернула изображение
     * @link https://openrouter.ai/docs/features/multimodal/image-generation Документация по генерации изображений
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

        // Проверяем наличие ответа
        if (!isset($response['choices'][0]['message'])) {
            throw new OpenRouterException('Модель не вернула message в ответе.');
        }

        $message = $response['choices'][0]['message'];

        // Вариант 1: OpenRouter возвращает изображение в поле images[] (основной формат для image generation)
        if (isset($message['images']) && is_array($message['images']) && count($message['images']) > 0) {
            // Берем первое изображение
            $firstImage = $message['images'][0];
            
            // Проверяем наличие image_url
            if (isset($firstImage['image_url']['url'])) {
                return (string)$firstImage['image_url']['url'];
            }
            
            // Альтернативный формат: прямое поле url
            if (isset($firstImage['url'])) {
                return (string)$firstImage['url'];
            }
        }

        // Вариант 2: content - это массив с элементами (альтернативный формат)
        if (isset($message['content']) && is_array($message['content'])) {
            $content = $message['content'];
            
            // Ищем элемент с типом image_url
            foreach ($content as $item) {
                if (isset($item['type']) && $item['type'] === 'image_url') {
                    if (isset($item['image_url']['url'])) {
                        return (string)$item['image_url']['url'];
                    }
                }
                // Старый формат: прямое поле image
                if (isset($item['image'])) {
                    return (string)$item['image'];
                }
            }
        }

        // Вариант 3: content - это строка с data URI или base64 (устаревший формат)
        if (isset($message['content']) && is_string($message['content'])) {
            $content = $message['content'];
            
            // Проверяем, что это не пустая строка и не просто текст
            if (!empty($content)) {
                // Если начинается с data:image - это data URI
                if (str_starts_with($content, 'data:image')) {
                    return $content;
                }
                // Если похоже на base64 (длинная строка с base64 символами)
                if (strlen($content) > 100 && preg_match('/^[A-Za-z0-9+\/=]+$/', substr($content, 0, 100))) {
                    return $content;
                }
            }
        }

        throw new OpenRouterException('Модель не вернула изображение в ожидаемом формате.');
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
     * Преобразует видео в текст (video2text)
     *
     * Анализирует видео и возвращает текстовое описание, транскрипцию или ответ на вопрос.
     * Поддерживает URL видео или base64-encoded данные.
     *
     * @param string $model Модель для анализа видео (например, "google/gemini-2.5-flash", "openai/gpt-4o")
     * @param string $videoUrl URL видео или base64-encoded данные
     * @param array<string, mixed> $options Дополнительные параметры:
     *                                      - prompt (string): Инструкция для анализа видео
     *                                      - max_tokens (int): Максимальное количество токенов
     * @return string Результат анализа видео
     * @throws OpenRouterValidationException Если параметры невалидны
     * @throws OpenRouterApiException Если API вернул ошибку
     * @throws OpenRouterException Если модель не вернула текстовый ответ
     * @link https://openrouter.ai/docs/features/multimodal/videos Документация по работе с видео
     */
    public function video2text(string $model, string $videoUrl, array $options = []): string
    {
        $this->validateNotEmpty($model, 'model');
        $this->validateNotEmpty($videoUrl, 'videoUrl');

        $prompt = $options['prompt'] ?? 'Пожалуйста, проанализируй это видео и опиши что на нём происходит.';
        
        // Удаляем prompt из options чтобы не передавать в payload
        unset($options['prompt']);

        // Формат согласно документации OpenRouter: https://openrouter.ai/docs/features/multimodal/videos
        $messages = [
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $prompt],
                    [
                        'type' => 'video_url',
                        'video_url' => [
                            'url' => $videoUrl,
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
     * Обрабатывает batch запросов с единым system промптом (для prompt caching)
     *
     * Отправляет несколько user-запросов с одним общим system-промптом.
     * System-промпт должен кешироваться между запросами, что экономит токены.
     * 
     * ВАЖНО: OpenRouter НЕ гарантирует кеширование между отдельными HTTP запросами.
     * Этот метод объединяет все user-запросы в один HTTP запрос для максимизации
     * шансов на кеширование system-промпта.
     *
     * @param string $model Модель ИИ для использования
     * @param string $systemPrompt Общий system-промпт (будет кешироваться)
     * @param array<int, string> $userPrompts Массив user-промптов для обработки
     * @param array<string, mixed> $options Дополнительные параметры запроса
     * @return array<int, array<string, mixed>> Массив результатов для каждого промпта:
     *                                           - content (string): Ответ
     *                                           - usage (array): Метрики токенов
     *                                           - model (string): Использованная модель
     *                                           - id (string): ID генерации
     * @throws OpenRouterValidationException Если параметры невалидны
     * @throws OpenRouterApiException Если API вернул ошибку
     * @throws OpenRouterException Если обработка не удалась
     */
    public function batchChatWithMessages(
        string $model,
        string $systemPrompt,
        array $userPrompts,
        array $options = []
    ): array {
        $this->validateNotEmpty($model, 'model');
        $this->validateNotEmpty($systemPrompt, 'systemPrompt');
        
        if (empty($userPrompts)) {
            throw new OpenRouterValidationException('Массив userPrompts не может быть пустым.');
        }

        $results = [];
        
        // Обрабатываем каждый user-промпт с одним и тем же system-промптом
        // Это должно максимизировать кеширование
        foreach ($userPrompts as $index => $userPrompt) {
            if (!is_string($userPrompt) || trim($userPrompt) === '') {
                $this->logError("Пропущен пустой user prompt", ['index' => $index]);
                continue;
            }
            
            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ];
            
            try {
                $result = $this->chatWithMessages($model, $messages, $options);
                $results[$index] = $result;
                
                if ($this->logger !== null) {
                    $cachedTokens = $result['usage']['cached_tokens'] ?? 0;
                    $this->logger->debug("Batch request #{$index} завершен", [
                        'cached_tokens' => $cachedTokens,
                        'total_tokens' => $result['usage']['total_tokens'] ?? 0
                    ]);
                }
            } catch (Exception $e) {
                $this->logError("Ошибка в batch request #{$index}", [
                    'error' => $e->getMessage()
                ]);
                
                // Продолжаем обработку остальных
                $results[$index] = [
                    'error' => $e->getMessage(),
                    'content' => null,
                    'usage' => [
                        'prompt_tokens' => 0,
                        'completion_tokens' => 0,
                        'total_tokens' => 0,
                        'cached_tokens' => 0,
                    ],
                ];
            }
        }
        
        return $results;
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

    /**
     * Парсит детальные метрики из ответа OpenRouter API
     *
     * Извлекает все доступные метрики для сохранения в БД:
     * - Временные метрики (generation_time, latency, moderation_latency)
     * - Токены (prompt, completion, native, cached, reasoning)
     * - Стоимость (usage, cache, data, file)
     * - Статус (finish_reason, provider_name)
     *
     * @param array<string, mixed> $response Полный ответ от OpenRouter API
     * @return array<string, mixed> Детальные метрики для записи в БД
     */
    private function parseDetailedMetrics(array $response): array
    {
        $usage = $response['usage'] ?? [];
        $choice = $response['choices'][0] ?? [];
        
        // Извлекаем метаданные провайдера
        $providerName = null;
        if (isset($response['model']) && is_string($response['model'])) {
            // Провайдер обычно указан в формате "provider/model-name"
            $parts = explode('/', $response['model'], 2);
            if (count($parts) === 2) {
                $providerName = $parts[0];
            }
        }
        
        // Альтернативный способ получения провайдера (если есть в metadata)
        if (isset($response['provider']) && is_string($response['provider'])) {
            $providerName = $response['provider'];
        }
        
        return [
            // Идентификация запроса
            'generation_id' => $response['id'] ?? null,
            'model' => $response['model'] ?? null,
            'provider_name' => $providerName,
            'created_at' => $response['created'] ?? null,
            
            // Временные метрики (мс)
            'generation_time' => $usage['generation_time'] ?? null,
            'latency' => $usage['latency'] ?? null,
            'moderation_latency' => $usage['moderation_latency'] ?? null,
            
            // Токены (OpenRouter подсчет)
            'tokens_prompt' => $usage['prompt_tokens'] ?? null,
            'tokens_completion' => $usage['completion_tokens'] ?? null,
            
            // Токены (native провайдера)
            'native_tokens_prompt' => $usage['native_tokens_prompt'] ?? null,
            'native_tokens_completion' => $usage['native_tokens_completion'] ?? null,
            'native_tokens_cached' => $usage['cached_tokens'] ?? null,
            'native_tokens_reasoning' => $usage['reasoning_tokens'] ?? null,
            
            // Стоимость (USD)
            'usage_total' => isset($usage['total_cost']) ? (float)$usage['total_cost'] : null,
            'usage_cache' => isset($usage['cache_cost']) ? (float)$usage['cache_cost'] : null,
            'usage_data' => isset($usage['data_cost']) ? (float)$usage['data_cost'] : null,
            'usage_file' => isset($usage['file_cost']) ? (float)$usage['file_cost'] : null,
            
            // Статус завершения
            'finish_reason' => $choice['finish_reason'] ?? null,
            
            // Полный response для будущего анализа
            'full_response' => json_encode($response, JSON_UNESCAPED_UNICODE),
        ];
    }
}

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
        $this->appName = (string)($config['app_name'] ?? 'RSSApp');
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

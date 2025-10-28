<?php

declare(strict_types=1);

namespace App\Component;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Класс-обёртка для выполнения HTTP-запросов на базе Guzzle
 */
class Http
{
    /**
     * @var array<string, mixed>
     */
    private array $defaultOptions;

    private Client $client;
    private ?Logger $logger;

    /**
     * @param array<string, mixed> $config Базовая конфигурация HTTP клиента
     * @param Logger|null $logger Инстанс логгера для записи ошибок
     */
    public function __construct(array $config = [], ?Logger $logger = null)
    {
        $clientConfig = [
            'http_errors' => false,
        ];

        if (isset($config['base_uri'])) {
            $clientConfig['base_uri'] = (string)$config['base_uri'];
        }

        if (isset($config['timeout'])) {
            $clientConfig['timeout'] = (float)$config['timeout'];
        }

        if (isset($config['connect_timeout'])) {
            $clientConfig['connect_timeout'] = (float)$config['connect_timeout'];
        }

        if (isset($config['verify'])) {
            $clientConfig['verify'] = (bool)$config['verify'];
        }

        if (isset($config['proxy'])) {
            $clientConfig['proxy'] = $config['proxy'];
        }

        if (isset($config['headers']) && is_array($config['headers'])) {
            $clientConfig['headers'] = $config['headers'];
        }

        if (array_key_exists('allow_redirects', $config)) {
            $clientConfig['allow_redirects'] = $config['allow_redirects'];
        }

        $this->defaultOptions = isset($config['options']) && is_array($config['options'])
            ? $config['options']
            : [];

        $this->client = new Client($clientConfig);
        $this->logger = $logger;
    }

    /**
     * Выполняет HTTP-запрос с указанными параметрами
     *
     * @param string $method HTTP метод
     * @param string $uri Адрес или endpoint
     * @param array<string, mixed> $options Дополнительные параметры запроса
     * @return ResponseInterface Ответ сервера
     */
    public function request(string $method, string $uri, array $options = []): ResponseInterface
    {
        $options = $this->mergeOptions($options);

        try {
            return $this->client->request($method, $uri, $options);
        } catch (GuzzleException $exception) {
            $this->logError('Ошибка HTTP запроса', [
                'method' => strtoupper($method),
                'uri' => $uri,
                'exception' => $exception->getMessage(),
            ]);

            throw new RuntimeException('Ошибка HTTP запроса: ' . $exception->getMessage(), (int)$exception->getCode(), $exception);
        }
    }

    /**
     * Объединяет параметры запроса с конфигурацией по умолчанию
     *
     * @param array<string, mixed> $options Параметры запроса
     * @return array<string, mixed>
     */
    private function mergeOptions(array $options): array
    {
        if ($this->defaultOptions === []) {
            return $options;
        }

        return array_replace_recursive($this->defaultOptions, $options);
    }

    /**
     * Выполняет потоковый HTTP-запрос с обработкой данных в реальном времени
     *
     * @param string $method HTTP метод
     * @param string $uri Адрес или endpoint
     * @param callable $callback Функция для обработки потоков данных
     * @param array<string, mixed> $options Дополнительные параметры запроса
     * @throws RuntimeException Если запрос завершился с ошибкой
     */
    public function requestStream(string $method, string $uri, callable $callback, array $options = []): void
    {
        $options = $this->mergeOptions($options);
        $options['stream'] = true;

        try {
            $response = $this->client->request($method, $uri, $options);
            $statusCode = $response->getStatusCode();

            if ($statusCode >= 400) {
                $body = (string)$response->getBody();
                $this->logError('HTTP потоковый запрос завершился ошибкой', [
                    'method' => strtoupper($method),
                    'uri' => $uri,
                    'status_code' => $statusCode,
                    'response' => $body,
                ]);

                throw new RuntimeException('HTTP потоковый запрос завершился ошибкой: ' . $statusCode . ' | ' . $body);
            }

            $body = $response->getBody();

            while (!$body->eof()) {
                $chunk = $body->read(8192);
                if ($chunk !== '') {
                    $callback($chunk);
                }
            }

            $body->close();
        } catch (GuzzleException $exception) {
            $this->logError('Ошибка потокового HTTP запроса', [
                'method' => strtoupper($method),
                'uri' => $uri,
                'exception' => $exception->getMessage(),
            ]);

            throw new RuntimeException('Ошибка потокового HTTP запроса: ' . $exception->getMessage(), (int)$exception->getCode(), $exception);
        }
    }

    /**
     * Записывает ошибку в лог (при наличии логгера)
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

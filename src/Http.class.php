<?php

declare(strict_types=1);

namespace App\Component;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
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
        $this->logger = $logger;
        
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

        if (array_key_exists('retries', $config)) {
            $clientConfig['handler'] = $this->createRetryHandler((int)$config['retries']);
        }

        $this->defaultOptions = isset($config['options']) && is_array($config['options'])
            ? $config['options']
            : [];

        $this->client = new Client($clientConfig);
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
     * Создаёт handler с поддержкой повторных попыток
     *
     * @param int $attempts Общее количество попыток выполнения запроса
     * @return HandlerStack
     */
    private function createRetryHandler(int $attempts): HandlerStack
    {
        $maxAttempts = max(1, $attempts);
        $handlerStack = HandlerStack::create();

        if ($maxAttempts === 1) {
            return $handlerStack;
        }

        $handlerStack->push(Middleware::retry(
            function (
                int $retriesAttempt,
                RequestInterface $request,
                ?ResponseInterface $response = null,
                ?RequestException $exception = null
            ) use ($maxAttempts): bool {
                if (!$exception instanceof ConnectException) {
                    return false;
                }

                if ($retriesAttempt >= $maxAttempts - 1) {
                    return false;
                }

                $this->logRetry($retriesAttempt + 1, $request, $exception);

                return true;
            },
            static function (int $retriesAttempt): int {
                return (int)(1000 * (2 ** $retriesAttempt));
            }
        ));

        return $handlerStack;
    }

    /**
     * Логирует попытку повторного запроса
     *
     * @param int $attemptNumber Номер попытки (начиная с 1 для повторов)
     * @param RequestInterface $request Запрос
     * @param RequestException $exception Исключение при ошибке соединения
     */
    private function logRetry(int $attemptNumber, RequestInterface $request, RequestException $exception): void
    {
        if ($this->logger === null) {
            return;
        }

        $this->logger->warning('Повторная попытка HTTP запроса', [
            'retry_attempt' => $attemptNumber,
            'method' => $request->getMethod(),
            'uri' => (string)$request->getUri(),
            'error' => $exception->getMessage(),
        ]);
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

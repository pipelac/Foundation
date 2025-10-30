<?php

declare(strict_types=1);

namespace App\Component;

use App\Component\Exception\HttpException;
use App\Component\Exception\HttpValidationException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Класс-обёртка для выполнения HTTP-запросов на базе Guzzle с поддержкой
 * ретраев, логирования, потоковой передачи данных и расширенной обработки ошибок
 */
class Http
{
    /**
     * HTTP методы
     */
    public const METHOD_GET = 'GET';
    public const METHOD_POST = 'POST';
    public const METHOD_PUT = 'PUT';
    public const METHOD_PATCH = 'PATCH';
    public const METHOD_DELETE = 'DELETE';
    public const METHOD_HEAD = 'HEAD';
    public const METHOD_OPTIONS = 'OPTIONS';

    /**
     * Размер чанка для потоковой передачи данных (8 КБ)
     */
    private const STREAM_CHUNK_SIZE = 8192;

    /**
     * Минимальная задержка между retry попытками (миллисекунды)
     */
    private const RETRY_MIN_DELAY_MS = 100;

    /**
     * Максимальная задержка между retry попытками (миллисекунды)
     */
    private const RETRY_MAX_DELAY_MS = 30000;

    /**
     * @var array<string, mixed>
     */
    private array $defaultOptions;

    private Client $client;
    private ?Logger $logger;
    private bool $logSuccessfulRequests;

    /**
     * Конструктор HTTP клиента с настройкой всех параметров
     * 
     * @param array<string, mixed> $config Базовая конфигурация HTTP клиента:
     *   - base_uri: Базовый URL для всех запросов
     *   - timeout: Общий таймаут в секундах (float)
     *   - connect_timeout: Таймаут подключения в секундах (float)
     *   - verify: Проверка SSL сертификата (bool)
     *   - proxy: Настройки прокси (string|array)
     *   - headers: Заголовки по умолчанию (array)
     *   - allow_redirects: Настройки редиректов (bool|array)
     *   - retries: Количество повторных попыток (int)
     *   - options: Дополнительные опции для запросов (array)
     *   - log_successful_requests: Логирование успешных запросов (bool, по умолчанию true)
     * @param Logger|null $logger Инстанс логгера для записи ошибок и отладочной информации
     */
    public function __construct(array $config = [], ?Logger $logger = null)
    {
        $this->logger = $logger;
        $this->logSuccessfulRequests = $config['log_successful_requests'] ?? true;
        
        $clientConfig = [
            'http_errors' => false,
        ];

        if (isset($config['base_uri'])) {
            $clientConfig['base_uri'] = (string)$config['base_uri'];
        }

        if (isset($config['timeout'])) {
            $clientConfig['timeout'] = max(0.0, (float)$config['timeout']);
        }

        if (isset($config['connect_timeout'])) {
            $clientConfig['connect_timeout'] = max(0.0, (float)$config['connect_timeout']);
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
            $retries = max(1, (int)$config['retries']);
            $clientConfig['handler'] = $this->createRetryHandler($retries);
        }

        $this->defaultOptions = isset($config['options']) && is_array($config['options'])
            ? $config['options']
            : [];

        $this->client = new Client($clientConfig);
    }

    /**
     * Выполняет HTTP-запрос с указанными параметрами и валидацией
     *
     * @param string $method HTTP метод (GET, POST, PUT, DELETE и т.д.)
     * @param string $uri Адрес или endpoint (не может быть пустым)
     * @param array<string, mixed> $options Дополнительные параметры запроса:
     *   - headers: Заголовки запроса
     *   - query: GET параметры
     *   - json: JSON тело запроса
     *   - form_params: Параметры формы
     *   - body: Тело запроса
     *   - timeout: Таймаут для конкретного запроса
     * @return ResponseInterface Ответ сервера
     * @throws HttpValidationException Если параметры запроса некорректны
     * @throws HttpException Если запрос завершился с ошибкой
     */
    public function request(string $method, string $uri, array $options = []): ResponseInterface
    {
        $this->validateRequest($method, $uri);
        $options = $this->mergeOptions($options);

        $startTime = microtime(true);

        try {
            $response = $this->client->request($method, $uri, $options);
            $duration = microtime(true) - $startTime;

            $this->logSuccessfulRequest($method, $uri, $response, $duration);

            return $response;
        } catch (GuzzleException $exception) {
            $duration = microtime(true) - $startTime;

            $this->logError('Ошибка HTTP запроса', [
                'method' => strtoupper($method),
                'uri' => $uri,
                'exception' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'duration' => round($duration, 3),
            ]);

            throw new HttpException(
                sprintf('Ошибка HTTP запроса [%s %s]: %s', strtoupper($method), $uri, $exception->getMessage()),
                (int)$exception->getCode(),
                $exception
            );
        }
    }

    /**
     * Выполняет GET запрос
     *
     * @param string $uri Адрес или endpoint
     * @param array<string, mixed> $options Дополнительные параметры запроса
     * @return ResponseInterface Ответ сервера
     * @throws HttpValidationException Если параметры запроса некорректны
     * @throws HttpException Если запрос завершился с ошибкой
     */
    public function get(string $uri, array $options = []): ResponseInterface
    {
        return $this->request(self::METHOD_GET, $uri, $options);
    }

    /**
     * Выполняет POST запрос
     *
     * @param string $uri Адрес или endpoint
     * @param array<string, mixed> $options Дополнительные параметры запроса
     * @return ResponseInterface Ответ сервера
     * @throws HttpValidationException Если параметры запроса некорректны
     * @throws HttpException Если запрос завершился с ошибкой
     */
    public function post(string $uri, array $options = []): ResponseInterface
    {
        return $this->request(self::METHOD_POST, $uri, $options);
    }

    /**
     * Выполняет PUT запрос
     *
     * @param string $uri Адрес или endpoint
     * @param array<string, mixed> $options Дополнительные параметры запроса
     * @return ResponseInterface Ответ сервера
     * @throws HttpValidationException Если параметры запроса некорректны
     * @throws HttpException Если запрос завершился с ошибкой
     */
    public function put(string $uri, array $options = []): ResponseInterface
    {
        return $this->request(self::METHOD_PUT, $uri, $options);
    }

    /**
     * Выполняет PATCH запрос
     *
     * @param string $uri Адрес или endpoint
     * @param array<string, mixed> $options Дополнительные параметры запроса
     * @return ResponseInterface Ответ сервера
     * @throws HttpValidationException Если параметры запроса некорректны
     * @throws HttpException Если запрос завершился с ошибкой
     */
    public function patch(string $uri, array $options = []): ResponseInterface
    {
        return $this->request(self::METHOD_PATCH, $uri, $options);
    }

    /**
     * Выполняет DELETE запрос
     *
     * @param string $uri Адрес или endpoint
     * @param array<string, mixed> $options Дополнительные параметры запроса
     * @return ResponseInterface Ответ сервера
     * @throws HttpValidationException Если параметры запроса некорректны
     * @throws HttpException Если запрос завершился с ошибкой
     */
    public function delete(string $uri, array $options = []): ResponseInterface
    {
        return $this->request(self::METHOD_DELETE, $uri, $options);
    }

    /**
     * Выполняет HEAD запрос
     *
     * @param string $uri Адрес или endpoint
     * @param array<string, mixed> $options Дополнительные параметры запроса
     * @return ResponseInterface Ответ сервера
     * @throws HttpValidationException Если параметры запроса некорректны
     * @throws HttpException Если запрос завершился с ошибкой
     */
    public function head(string $uri, array $options = []): ResponseInterface
    {
        return $this->request(self::METHOD_HEAD, $uri, $options);
    }

    /**
     * Возвращает экземпляр базового Guzzle клиента для расширенного использования
     *
     * @return Client Экземпляр Guzzle клиента
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Валидирует параметры HTTP-запроса перед выполнением
     *
     * @param string $method HTTP метод
     * @param string $uri Адрес или endpoint
     * @throws HttpValidationException Если параметры некорректны
     */
    private function validateRequest(string $method, string $uri): void
    {
        $method = trim($method);
        if ($method === '') {
            throw new HttpValidationException('HTTP метод не может быть пустым');
        }

        $uri = trim($uri);
        if ($uri === '') {
            throw new HttpValidationException('URI не может быть пустым');
        }
    }

    /**
     * Объединяет параметры запроса с конфигурацией по умолчанию
     * Использует умное слияние для корректной обработки вложенных массивов
     *
     * @param array<string, mixed> $options Параметры запроса
     * @return array<string, mixed> Объединённые параметры
     */
    private function mergeOptions(array $options): array
    {
        if ($this->defaultOptions === []) {
            return $options;
        }

        if ($options === []) {
            return $this->defaultOptions;
        }

        return array_merge_recursive($this->defaultOptions, $options);
    }

    /**
     * Выполняет потоковый HTTP-запрос с обработкой данных в реальном времени
     * Позволяет обрабатывать большие объёмы данных без загрузки всего ответа в память
     *
     * @param string $method HTTP метод (GET, POST и т.д.)
     * @param string $uri Адрес или endpoint
     * @param callable $callback Функция для обработки чанков данных: function(string $chunk): void
     * @param array<string, mixed> $options Дополнительные параметры запроса
     * @throws HttpValidationException Если параметры запроса некорректны
     * @throws HttpException Если запрос завершился с ошибкой
     */
    public function requestStream(string $method, string $uri, callable $callback, array $options = []): void
    {
        $this->validateRequest($method, $uri);
        
        $options = $this->mergeOptions($options);
        $options['stream'] = true;

        $startTime = microtime(true);
        $totalBytes = 0;

        try {
            $response = $this->client->request($method, $uri, $options);
            $statusCode = $response->getStatusCode();

            if ($statusCode >= 400) {
                // Для ошибочных ответов читаем только первые 1024 байта для логирования
                $body = $response->getBody();
                $errorPreview = $body->read(1024);
                $body->close();
                
                $duration = microtime(true) - $startTime;
                
                $this->logError('HTTP потоковый запрос завершился ошибкой', [
                    'method' => strtoupper($method),
                    'uri' => $uri,
                    'status_code' => $statusCode,
                    'response_preview' => $errorPreview,
                    'duration' => round($duration, 3),
                ]);

                throw new HttpException(
                    sprintf(
                        'HTTP потоковый запрос завершился ошибкой [%s %s]: код %d',
                        strtoupper($method),
                        $uri,
                        $statusCode
                    ),
                    $statusCode
                );
            }

            $body = $response->getBody();

            while (!$body->eof()) {
                $chunk = $body->read(self::STREAM_CHUNK_SIZE);
                if ($chunk !== '') {
                    $totalBytes += strlen($chunk);
                    $callback($chunk);
                }
            }

            $body->close();

            $duration = microtime(true) - $startTime;

            $this->logSuccessfulStreamRequest($method, $uri, $statusCode, $totalBytes, $duration);

        } catch (GuzzleException $exception) {
            $duration = microtime(true) - $startTime;

            $this->logError('Ошибка потокового HTTP запроса', [
                'method' => strtoupper($method),
                'uri' => $uri,
                'exception' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'duration' => round($duration, 3),
                'bytes_received' => $totalBytes,
            ]);

            throw new HttpException(
                sprintf('Ошибка потокового HTTP запроса [%s %s]: %s', strtoupper($method), $uri, $exception->getMessage()),
                (int)$exception->getCode(),
                $exception
            );
        }
    }

    /**
     * Создаёт handler с поддержкой повторных попыток при временных сбоях
     * Использует экспоненциальную задержку между попытками (exponential backoff)
     *
     * @param int $attempts Общее количество попыток выполнения запроса (минимум 1)
     * @return HandlerStack Handler stack с настроенными retry middleware
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
                ?\Throwable $exception = null
            ) use ($maxAttempts): bool {
                // Достигнут лимит попыток
                if ($retriesAttempt >= $maxAttempts - 1) {
                    return false;
                }

                // Повторяем при ошибках подключения
                if ($exception instanceof ConnectException) {
                    $this->logRetry($retriesAttempt + 1, $request, $exception);
                    return true;
                }

                // Повторяем при серверных ошибках (5xx) и таймаутах
                if ($response !== null) {
                    $statusCode = $response->getStatusCode();
                    if ($statusCode >= 500 && $statusCode < 600) {
                        $this->logRetry(
                            $retriesAttempt + 1,
                            $request,
                            new RequestException('Серверная ошибка: ' . $statusCode, $request, $response)
                        );
                        return true;
                    }

                    // Повторяем при 429 Too Many Requests
                    if ($statusCode === 429) {
                        $this->logRetry(
                            $retriesAttempt + 1,
                            $request,
                            new RequestException('Превышен лимит запросов (429)', $request, $response)
                        );
                        return true;
                    }
                }

                return false;
            },
            function (int $retriesAttempt): int {
                // Экспоненциальная задержка с ограничениями: 100ms, 200ms, 400ms, 800ms, ...
                $delayMs = (int)(self::RETRY_MIN_DELAY_MS * (2 ** $retriesAttempt));
                return min($delayMs, self::RETRY_MAX_DELAY_MS);
            }
        ));

        return $handlerStack;
    }

    /**
     * Логирует попытку повторного запроса с детальным контекстом
     *
     * @param int $attemptNumber Номер попытки (начиная с 1 для повторов)
     * @param RequestInterface $request HTTP запрос
     * @param \Throwable $exception Исключение, вызвавшее повтор
     */
    private function logRetry(int $attemptNumber, RequestInterface $request, \Throwable $exception): void
    {
        if ($this->logger === null) {
            return;
        }

        $context = [
            'retry_attempt' => $attemptNumber,
            'method' => $request->getMethod(),
            'uri' => (string)$request->getUri(),
            'error' => $exception->getMessage(),
        ];

        // Добавляем код ответа если есть (для RequestException)
        if ($exception instanceof RequestException) {
            $response = $exception->getResponse();
            if ($response !== null) {
                $context['status_code'] = $response->getStatusCode();
            }
        }

        $this->logger->warning('Повторная попытка HTTP запроса', $context);
    }

    /**
     * Записывает ошибку в лог с дополнительным контекстом (при наличии логгера)
     *
     * @param string $message Сообщение об ошибке
     * @param array<string, mixed> $context Контекст ошибки (метод, URI, код и т.д.)
     */
    private function logError(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->error($message, $context);
        }
    }

    /**
     * Логирует успешный HTTP запрос
     *
     * @param string $method HTTP метод
     * @param string $uri URI запроса
     * @param ResponseInterface $response Ответ сервера
     * @param float $duration Длительность выполнения запроса в секундах
     */
    private function logSuccessfulRequest(string $method, string $uri, ResponseInterface $response, float $duration): void
    {
        if ($this->logger === null || !$this->logSuccessfulRequests) {
            return;
        }

        $statusCode = $response->getStatusCode();
        $bodySize = $response->getBody()->getSize() ?? strlen((string)$response->getBody());

        $context = [
            'method' => strtoupper($method),
            'uri' => $uri,
            'status_code' => $statusCode,
            'duration' => round($duration, 3),
            'body_size' => $bodySize,
        ];

        // Добавляем Content-Type если есть
        $contentType = $response->getHeader('Content-Type');
        if (!empty($contentType)) {
            $context['content_type'] = implode(', ', $contentType);
        }

        $logLevel = $statusCode >= 400 ? 'warning' : 'info';
        $message = sprintf(
            'HTTP запрос выполнен [%s %s] код %d',
            strtoupper($method),
            $uri,
            $statusCode
        );

        $this->logger->log($logLevel, $message, $context);
    }

    /**
     * Логирует успешный потоковый HTTP запрос
     *
     * @param string $method HTTP метод
     * @param string $uri URI запроса
     * @param int $statusCode Код ответа
     * @param int $totalBytes Количество полученных байт
     * @param float $duration Длительность выполнения запроса в секундах
     */
    private function logSuccessfulStreamRequest(string $method, string $uri, int $statusCode, int $totalBytes, float $duration): void
    {
        if ($this->logger === null || !$this->logSuccessfulRequests) {
            return;
        }

        $context = [
            'method' => strtoupper($method),
            'uri' => $uri,
            'status_code' => $statusCode,
            'bytes_received' => $totalBytes,
            'duration' => round($duration, 3),
        ];

        $message = sprintf(
            'HTTP потоковый запрос выполнен [%s %s] код %d, получено %d байт',
            strtoupper($method),
            $uri,
            $statusCode,
            $totalBytes
        );

        $this->logger->info($message, $context);
    }
}

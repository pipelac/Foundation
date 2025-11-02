<?php

declare(strict_types=1);

namespace App\Rss2Tlg;

use App\Component\Http;
use App\Component\Logger;
use App\Component\MySQL;
use App\Component\Rss;
use App\Rss2Tlg\DTO\FeedConfig;
use App\Rss2Tlg\DTO\FeedState;
use App\Rss2Tlg\DTO\FetchResult;
use App\Rss2Tlg\DTO\RawItem;
use Psr\Http\Message\ResponseInterface;

/**
 * Основной класс для опроса RSS/Atom источников
 * 
 * Реализует конвейер обработки:
 * 1. Проверка расписания и backoff
 * 2. HTTP запрос с Conditional GET (ETag, Last-Modified)
 * 3. Парсинг через готовый класс Rss при 200 OK
 * 4. Нормализация элементов в RawItem
 * 5. Обновление состояния источника
 * 6. Сбор метрик и логирование
 */
class FetchRunner
{
    private FeedStateRepository $stateRepository;
    private array $metrics = [];

    /**
     * Конструктор FetchRunner
     * 
     * @param MySQL $db Подключение к БД
     * @param string $cacheDir Директория для кеша RSS
     * @param Logger|null $logger Логгер для отладки
     */
    public function __construct(
        private readonly MySQL $db,
        private readonly string $cacheDir,
        private readonly ?Logger $logger = null
    ) {
        $this->stateRepository = new FeedStateRepository($db, $logger);
        $this->initMetrics();
    }

    /**
     * Выполняет fetch для всех активных источников
     * 
     * Загружает конфигурацию всех источников и последовательно обрабатывает каждый.
     * Источники в backoff пропускаются.
     * 
     * @param array<int, FeedConfig> $feeds Массив конфигураций источников
     * @return array<int, FetchResult> Массив результатов индексированный по feed_id
     */
    public function runForAllFeeds(array $feeds): array
    {
        $this->logInfo('Начало опроса источников', ['feeds_count' => count($feeds)]);
        
        $results = [];
        
        foreach ($feeds as $feedConfig) {
            if (!$feedConfig->enabled) {
                $this->logDebug('Источник отключен, пропускаем', ['feed_id' => $feedConfig->id]);
                continue;
            }

            try {
                $result = $this->runForFeed($feedConfig);
                $results[$feedConfig->id] = $result;
            } catch (\Exception $e) {
                $this->logError('Необработанная ошибка при fetch источника', [
                    'feed_id' => $feedConfig->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logInfo('Опрос источников завершён', [
            'total' => count($feeds),
            'processed' => count($results),
            'metrics' => $this->getMetrics(),
        ]);

        return $results;
    }

    /**
     * Выполняет fetch для одного источника
     * 
     * Основной метод выполнения fetch:
     * - Проверяет backoff
     * - Загружает текущее состояние
     * - Выполняет HTTP запрос с Conditional GET
     * - Парсит ленту при необходимости
     * - Обновляет состояние
     * - Возвращает результат
     * 
     * @param FeedConfig $config Конфигурация источника
     * @return FetchResult Результат операции
     */
    public function runForFeed(FeedConfig $config): FetchResult
    {
        $startTime = microtime(true);
        
        $this->logInfo('Начало fetch источника', [
            'feed_id' => $config->id,
            'url' => $config->url,
        ]);

        // Загружаем текущее состояние источника
        $currentState = $this->stateRepository->getByFeedId($config->id);
        if ($currentState === null) {
            $currentState = FeedState::createInitial();
        }

        // Проверяем backoff
        if ($currentState->isInBackoff()) {
            $backoffRemaining = $currentState->getBackoffRemaining();
            $this->logInfo('Источник в backoff, пропускаем', [
                'feed_id' => $config->id,
                'backoff_remaining_sec' => $backoffRemaining,
            ]);

            return FetchResult::error($config->id, $currentState, [
                'reason' => 'backoff',
                'backoff_remaining' => $backoffRemaining,
            ]);
        }

        // Выполняем HTTP запрос
        try {
            $response = $this->performHttpRequest($config, $currentState);
            $statusCode = $response->getStatusCode();
            $duration = microtime(true) - $startTime;

            $this->incrementMetric('fetch_total');

            // Обрабатываем ответ в зависимости от статуса
            if ($statusCode === 304) {
                return $this->handle304NotModified($config, $currentState, $response, $duration);
            } elseif ($statusCode >= 200 && $statusCode < 300) {
                return $this->handle200Success($config, $currentState, $response, $duration);
            } else {
                return $this->handleErrorResponse($config, $currentState, $response, $duration);
            }
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;
            return $this->handleException($config, $currentState, $e, $duration);
        }
    }

    /**
     * Выполняет HTTP запрос с Conditional GET заголовками
     * 
     * @param FeedConfig $config Конфигурация источника
     * @param FeedState $state Текущее состояние источника
     * @return ResponseInterface HTTP ответ
     * @throws GuzzleException При ошибке сети
     */
    private function performHttpRequest(FeedConfig $config, FeedState $state): ResponseInterface
    {
        $headers = array_merge([
            'User-Agent' => 'Rss2Tlg/1.0 (RSS Aggregator)',
            'Accept' => 'application/rss+xml, application/atom+xml, application/xml, text/xml, */*',
            'Accept-Encoding' => 'gzip, deflate',
        ], $config->headers);

        // Добавляем Conditional GET заголовки
        if ($state->etag !== null) {
            $headers['If-None-Match'] = $state->etag;
        }

        if ($state->lastModified !== null) {
            $headers['If-Modified-Since'] = $state->lastModified;
        }

        $options = [
            'headers' => $headers,
            'timeout' => $config->timeout,
            'connect_timeout' => $config->timeout,
            'http_errors' => false, // Не выбрасывать исключение на 4xx/5xx
        ];

        if ($config->proxy !== null) {
            $options['proxy'] = $config->proxy;
        }

        if ($config->retries > 1) {
            $options['retries'] = $config->retries;
        }

        $http = new Http($options, $this->logger);
        return $http->get($config->url);
    }

    /**
     * Обрабатывает ответ 304 Not Modified
     * 
     * @param FeedConfig $config Конфигурация источника
     * @param FeedState $currentState Текущее состояние
     * @param ResponseInterface $response HTTP ответ
     * @param float $duration Длительность запроса
     * @return FetchResult Результат без новых элементов
     */
    private function handle304NotModified(
        FeedConfig $config,
        FeedState $currentState,
        ResponseInterface $response,
        float $duration
    ): FetchResult {
        $this->incrementMetric('fetch_304');

        $this->logInfo('Источник не изменился (304)', [
            'feed_id' => $config->id,
            'duration' => round($duration, 3),
        ]);

        // Обновляем состояние (сбрасываем ошибки, обновляем время)
        $newState = $currentState->withSuccessfulFetch(
            $currentState->etag,
            $currentState->lastModified,
            304
        );

        $this->stateRepository->save($config->id, $config->url, $newState);

        $metrics = [
            'status_code' => 304,
            'duration' => round($duration, 3),
            'items_count' => 0,
        ];

        return FetchResult::notModified($config->id, $newState, $metrics);
    }

    /**
     * Обрабатывает успешный ответ 200 OK
     * 
     * @param FeedConfig $config Конфигурация источника
     * @param FeedState $currentState Текущее состояние
     * @param ResponseInterface $response HTTP ответ
     * @param float $duration Длительность запроса
     * @return FetchResult Результат с распарсенными элементами
     */
    private function handle200Success(
        FeedConfig $config,
        FeedState $currentState,
        ResponseInterface $response,
        float $duration
    ): FetchResult {
        $this->incrementMetric('fetch_200');

        $body = (string)$response->getBody();
        $bodySize = strlen($body);

        $this->logInfo('Источник вернул новые данные (200)', [
            'feed_id' => $config->id,
            'body_size' => $bodySize,
            'duration' => round($duration, 3),
        ]);

        // Извлекаем ETag и Last-Modified из ответа
        $etag = $this->extractHeader($response, 'ETag');
        $lastModified = $this->extractHeader($response, 'Last-Modified');

        // Парсим ленту через SimplePie
        try {
            $items = $this->parseFeed($body, $config);
            
            $this->incrementMetric('items_parsed', count($items));

            $this->logInfo('Лента успешно распарсена', [
                'feed_id' => $config->id,
                'items_count' => count($items),
            ]);

            // Обновляем состояние на успех
            $newState = $currentState->withSuccessfulFetch($etag, $lastModified, 200);
            $this->stateRepository->save($config->id, $config->url, $newState);

            $metrics = [
                'status_code' => 200,
                'duration' => round($duration, 3),
                'body_size' => $bodySize,
                'items_count' => count($items),
                'valid_items_count' => count(array_filter($items, fn($item) => $item->isValid())),
            ];

            return FetchResult::success($config->id, $newState, $items, $metrics);
        } catch (\Exception $e) {
            $this->incrementMetric('parse_errors');
            
            $this->logError('Ошибка парсинга ленты', [
                'feed_id' => $config->id,
                'error' => $e->getMessage(),
            ]);

            // Помечаем как ошибку парсинга
            $newState = $currentState->withFailedFetch(200);
            $this->stateRepository->save($config->id, $config->url, $newState);

            $metrics = [
                'status_code' => 200,
                'parse_error' => $e->getMessage(),
                'duration' => round($duration, 3),
            ];

            return FetchResult::error($config->id, $newState, $metrics);
        }
    }

    /**
     * Обрабатывает ошибочный HTTP ответ (4xx, 5xx)
     * 
     * @param FeedConfig $config Конфигурация источника
     * @param FeedState $currentState Текущее состояние
     * @param ResponseInterface $response HTTP ответ
     * @param float $duration Длительность запроса
     * @return FetchResult Результат с ошибкой
     */
    private function handleErrorResponse(
        FeedConfig $config,
        FeedState $currentState,
        ResponseInterface $response,
        float $duration
    ): FetchResult {
        $statusCode = $response->getStatusCode();
        $this->incrementMetric('fetch_errors');

        $this->logError('Источник вернул ошибку', [
            'feed_id' => $config->id,
            'status_code' => $statusCode,
            'duration' => round($duration, 3),
        ]);

        // Обрабатываем Retry-After для 429 и 503
        $retryAfter = null;
        if (in_array($statusCode, [429, 503], true)) {
            $retryAfter = $this->parseRetryAfter($response);
        }

        $newState = $currentState->withFailedFetch($statusCode, $retryAfter);
        $this->stateRepository->save($config->id, $config->url, $newState);

        $metrics = [
            'status_code' => $statusCode,
            'duration' => round($duration, 3),
            'retry_after' => $retryAfter,
        ];

        return FetchResult::error($config->id, $newState, $metrics);
    }

    /**
     * Обрабатывает исключение при запросе
     * 
     * @param FeedConfig $config Конфигурация источника
     * @param FeedState $currentState Текущее состояние
     * @param \Exception $exception Исключение
     * @param float $duration Длительность запроса
     * @return FetchResult Результат с ошибкой
     */
    private function handleException(
        FeedConfig $config,
        FeedState $currentState,
        \Exception $exception,
        float $duration
    ): FetchResult {
        $this->incrementMetric('fetch_errors');

        $this->logError('Исключение при fetch источника', [
            'feed_id' => $config->id,
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'duration' => round($duration, 3),
        ]);

        // Сетевая ошибка - статус 0
        $newState = $currentState->withFailedFetch(0);
        $this->stateRepository->save($config->id, $config->url, $newState);

        $metrics = [
            'status_code' => 0,
            'exception' => get_class($exception),
            'error' => $exception->getMessage(),
            'duration' => round($duration, 3),
        ];

        return FetchResult::error($config->id, $newState, $metrics);
    }

    /**
     * Парсит RSS/Atom ленту через готовый класс Rss
     * 
     * @param string $xmlContent XML контент ленты
     * @param FeedConfig $config Конфигурация источника
     * @return array<int, RawItem> Массив распарсенных элементов
     * @throws \Exception При ошибке парсинга
     */
    private function parseFeed(string $xmlContent, FeedConfig $config): array
    {
        // Временный файл для XML контента (Rss класс работает с URL или файлом)
        $tempFile = tempnam(sys_get_temp_dir(), 'rss_');
        file_put_contents($tempFile, $xmlContent);
        
        try {
            // Конфигурация Rss класса
            $rssConfig = [
                'timeout' => $config->timeout,
                'max_content_size' => strlen($xmlContent) + 1024,
                'enable_cache' => $config->parserOptions['enable_cache'] ?? true,
                'cache_directory' => $this->cacheDir,
            ];

            $rss = new Rss($rssConfig, $this->logger);
            
            // Парсим через готовый класс
            $feedData = $rss->fetch('file://' . $tempFile);
            
            // Извлекаем элементы
            $feedItems = $feedData['items'] ?? [];
            
            // Применяем лимит max_items
            $maxItems = $config->parserOptions['max_items'] ?? null;
            if ($maxItems !== null && $maxItems > 0) {
                $feedItems = array_slice($feedItems, 0, $maxItems);
            }

            // Конвертируем в RawItem
            $items = [];
            foreach ($feedItems as $item) {
                try {
                    $rawItem = RawItem::fromRssArray($item);
                    if ($rawItem->isValid()) {
                        $items[] = $rawItem;
                    }
                } catch (\Exception $e) {
                    $this->logWarning('Ошибка обработки элемента ленты', [
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
            }

            return $items;
            
        } finally {
            // Удаляем временный файл
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Извлекает значение заголовка из HTTP ответа
     * 
     * @param ResponseInterface $response HTTP ответ
     * @param string $headerName Имя заголовка
     * @return string|null Значение заголовка или null
     */
    private function extractHeader(ResponseInterface $response, string $headerName): ?string
    {
        if (!$response->hasHeader($headerName)) {
            return null;
        }

        $values = $response->getHeader($headerName);
        if (empty($values)) {
            return null;
        }

        $value = trim($values[0]);
        return $value !== '' ? $value : null;
    }

    /**
     * Парсит заголовок Retry-After
     * 
     * @param ResponseInterface $response HTTP ответ
     * @return int|null Количество секунд до повтора или null
     */
    private function parseRetryAfter(ResponseInterface $response): ?int
    {
        $retryAfter = $this->extractHeader($response, 'Retry-After');
        if ($retryAfter === null) {
            return null;
        }

        // Может быть числом секунд или HTTP-датой
        if (is_numeric($retryAfter)) {
            return max(1, (int)$retryAfter);
        }

        // Пробуем распарсить как дату
        $timestamp = strtotime($retryAfter);
        if ($timestamp !== false) {
            $seconds = max(1, $timestamp - time());
            return $seconds;
        }

        return null;
    }

    /**
     * Инициализирует метрики
     */
    private function initMetrics(): void
    {
        $this->metrics = [
            'fetch_total' => 0,
            'fetch_200' => 0,
            'fetch_304' => 0,
            'fetch_errors' => 0,
            'parse_errors' => 0,
            'items_parsed' => 0,
        ];
    }

    /**
     * Увеличивает значение метрики
     * 
     * @param string $key Ключ метрики
     * @param int $increment Значение инкремента
     */
    private function incrementMetric(string $key, int $increment = 1): void
    {
        if (!isset($this->metrics[$key])) {
            $this->metrics[$key] = 0;
        }
        $this->metrics[$key] += $increment;
    }

    /**
     * Возвращает все метрики
     * 
     * @return array<string, int> Массив метрик
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * Логирует информационное сообщение
     * 
     * @param string $message Сообщение
     * @param array<string, mixed> $context Контекст
     */
    private function logInfo(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->info($message, $context);
        }
    }

    /**
     * Логирует отладочное сообщение
     * 
     * @param string $message Сообщение
     * @param array<string, mixed> $context Контекст
     */
    private function logDebug(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->debug($message, $context);
        }
    }

    /**
     * Логирует предупреждение
     * 
     * @param string $message Сообщение
     * @param array<string, mixed> $context Контекст
     */
    private function logWarning(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->warning($message, $context);
        }
    }

    /**
     * Логирует ошибку
     * 
     * @param string $message Сообщение об ошибке
     * @param array<string, mixed> $context Контекст
     */
    private function logError(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->error($message, $context);
        }
    }
}

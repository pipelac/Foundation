<?php

declare(strict_types=1);

namespace App\Component;

use App\Component\Exception\HttpException;
use App\Component\Exception\ProxyPool\ProxyPoolException;
use App\Component\Exception\ProxyPool\ProxyPoolValidationException;
use App\Component\Config\ConfigLoader;
use Psr\Http\Message\ResponseInterface;

/**
 * Легковесный менеджер пула прокси-серверов с автоматической ротацией,
 * health-check и retry механизмом
 * 
 * Основные возможности:
 * - Управление списком прокси-серверов
 * - Round-robin или random ротация прокси
 * - Автоматический health-check с отключением упавших прокси
 * - Автоматический retry при неудачных запросах с переключением на другой прокси
 * - Детальная статистика по каждому прокси и общая статистика
 * - Интеграция с Http.class.php для выполнения запросов
 * - Полное логирование всех операций
 */
class ProxyPool
{
    /**
     * Константы стратегий ротации
     */
    public const ROTATION_ROUND_ROBIN = 'round_robin';
    public const ROTATION_RANDOM = 'random';
    
    /**
     * Значения по умолчанию
     */
    private const DEFAULT_ROTATION_STRATEGY = self::ROTATION_ROUND_ROBIN;
    private const DEFAULT_HEALTH_CHECK_TIMEOUT = 5;
    private const DEFAULT_HEALTH_CHECK_URL = 'https://www.google.com';
    private const DEFAULT_HEALTH_CHECK_INTERVAL = 300; // 5 минут
    private const DEFAULT_MAX_RETRIES = 3;
    private const DEFAULT_AUTO_HEALTH_CHECK = true;
    
    /**
     * Стратегия ротации прокси
     */
    private string $rotationStrategy;
    
    /**
     * Текущий индекс для round-robin ротации
     */
    private int $currentIndex = 0;
    
    /**
     * URL для проверки health-check
     */
    private string $healthCheckUrl;
    
    /**
     * Таймаут для health-check в секундах
     */
    private int $healthCheckTimeout;
    
    /**
     * Интервал между автоматическими health-check в секундах
     */
    private int $healthCheckInterval;
    
    /**
     * Включить автоматический health-check
     */
    private bool $autoHealthCheck;
    
    /**
     * Максимальное количество retry попыток
     */
    private int $maxRetries;
    
    /**
     * Список прокси с метаданными
     * @var array<string, array{url: string, alive: bool, last_check: int, success_count: int, fail_count: int, last_error: string}>
     */
    private array $proxies = [];
    
    /**
     * Экземпляр логгера для записи событий
     */
    private ?Logger $logger;
    
    /**
     * HTTP клиент для выполнения запросов
     */
    private Http $http;
    
    /**
     * Общая статистика пула
     * @var array{total_requests: int, successful_requests: int, failed_requests: int, total_retries: int}
     */
    private array $poolStats = [
        'total_requests' => 0,
        'successful_requests' => 0,
        'failed_requests' => 0,
        'total_retries' => 0,
    ];

    /**
     * Создает экземпляр класса из конфигурационного файла
     * 
     * @param string $configPath Путь к JSON конфигурационному файлу
     * @param Logger|null $logger Инстанс логгера для записи событий
     * @return self Экземпляр класса ProxyPool
     * @throws ProxyPoolException Если не удалось загрузить конфигурацию
     * @throws ProxyPoolValidationException Если конфигурация некорректна
     */
    public static function fromConfig(string $configPath, ?Logger $logger = null): self
    {
        try {
            $config = ConfigLoader::load($configPath);
        } catch (\Exception $e) {
            throw new ProxyPoolException(
                'Не удалось загрузить конфигурацию: ' . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
        
        // Удаляем служебные поля из конфигурации
        unset($config['_comment'], $config['_fields']);
        
        return new self($config, $logger);
    }

    /**
     * Конструктор менеджера пула прокси
     * 
     * @param array<string, mixed> $config Конфигурация пула:
     *   - proxies: массив прокси URL (string[])
     *   - rotation_strategy: стратегия ротации ('round_robin' или 'random')
     *   - health_check_url: URL для проверки доступности прокси
     *   - health_check_timeout: таймаут health-check в секундах
     *   - health_check_interval: интервал между автоматическими проверками в секундах
     *   - auto_health_check: автоматически проверять прокси при инициализации
     *   - max_retries: максимальное количество retry попыток
     *   - http_config: конфигурация для Http клиента (array)
     * @param Logger|null $logger Инстанс логгера для записи событий
     * @throws ProxyPoolValidationException Если конфигурация некорректна
     */
    public function __construct(array $config = [], ?Logger $logger = null)
    {
        $this->logger = $logger;
        
        $this->rotationStrategy = (string)($config['rotation_strategy'] ?? self::DEFAULT_ROTATION_STRATEGY);
        $this->healthCheckUrl = (string)($config['health_check_url'] ?? self::DEFAULT_HEALTH_CHECK_URL);
        $this->healthCheckTimeout = max(1, (int)($config['health_check_timeout'] ?? self::DEFAULT_HEALTH_CHECK_TIMEOUT));
        $this->healthCheckInterval = max(0, (int)($config['health_check_interval'] ?? self::DEFAULT_HEALTH_CHECK_INTERVAL));
        $this->autoHealthCheck = (bool)($config['auto_health_check'] ?? self::DEFAULT_AUTO_HEALTH_CHECK);
        $this->maxRetries = max(1, (int)($config['max_retries'] ?? self::DEFAULT_MAX_RETRIES));
        
        $this->validateConfiguration();
        
        // Инициализация HTTP клиента
        $httpConfig = isset($config['http_config']) && is_array($config['http_config']) 
            ? $config['http_config'] 
            : [];
        
        $this->http = new Http($httpConfig, $logger);
        
        // Загрузка прокси из конфигурации
        if (isset($config['proxies']) && is_array($config['proxies'])) {
            foreach ($config['proxies'] as $proxy) {
                if (is_string($proxy) && $proxy !== '') {
                    $this->addProxy($proxy);
                }
            }
        }
        
        $this->logInfo('ProxyPool менеджер инициализирован', [
            'rotation_strategy' => $this->rotationStrategy,
            'proxies_count' => count($this->proxies),
            'auto_health_check' => $this->autoHealthCheck,
        ]);
        
        // Автоматическая проверка всех прокси при инициализации
        if ($this->autoHealthCheck && count($this->proxies) > 0) {
            $this->checkAllProxies();
        }
    }

    /**
     * Добавляет прокси в пул с валидацией
     * 
     * @param string $proxy URL прокси (формат: protocol://host:port или protocol://user:pass@host:port)
     * @throws ProxyPoolValidationException Если прокси URL невалиден
     */
    public function addProxy(string $proxy): void
    {
        $proxy = trim($proxy);
        
        if ($proxy === '') {
            throw new ProxyPoolValidationException('Прокси URL не может быть пустым');
        }
        
        $this->validateProxyUrl($proxy);
        
        // Проверяем, не добавлен ли уже этот прокси
        if (isset($this->proxies[$proxy])) {
            $this->logWarning('Прокси уже существует в пуле', ['proxy' => $proxy]);
            return;
        }
        
        $this->proxies[$proxy] = [
            'url' => $proxy,
            'alive' => true, // По умолчанию считаем живым
            'last_check' => 0,
            'success_count' => 0,
            'fail_count' => 0,
            'last_error' => '',
        ];
        
        $this->logInfo('Прокси добавлен в пул', ['proxy' => $proxy]);
    }

    /**
     * Удаляет прокси из пула
     * 
     * @param string $proxy URL прокси для удаления
     */
    public function removeProxy(string $proxy): void
    {
        $proxy = trim($proxy);
        
        if (!isset($this->proxies[$proxy])) {
            $this->logWarning('Попытка удалить несуществующий прокси', ['proxy' => $proxy]);
            return;
        }
        
        unset($this->proxies[$proxy]);
        
        // Сбрасываем индекс round-robin если он вышел за пределы
        if ($this->currentIndex >= count($this->proxies)) {
            $this->currentIndex = 0;
        }
        
        $this->logInfo('Прокси удален из пула', ['proxy' => $proxy]);
    }

    /**
     * Получает следующий доступный прокси согласно стратегии ротации
     * 
     * @return string|null URL прокси или null если нет доступных прокси
     */
    public function getNextProxy(): ?string
    {
        $aliveProxies = $this->getAliveProxies();
        
        if (count($aliveProxies) === 0) {
            $this->logWarning('Нет доступных живых прокси в пуле');
            return null;
        }
        
        if ($this->rotationStrategy === self::ROTATION_RANDOM) {
            return $this->getRandomProxy();
        }
        
        // Round-robin ротация
        $proxiesArray = array_values($aliveProxies);
        
        // Проверяем что индекс в пределах массива
        if ($this->currentIndex >= count($proxiesArray)) {
            $this->currentIndex = 0;
        }
        
        $proxy = $proxiesArray[$this->currentIndex]['url'];
        
        $this->currentIndex = ($this->currentIndex + 1) % count($proxiesArray);
        
        return $proxy;
    }

    /**
     * Получает случайный доступный прокси
     * 
     * @return string|null URL прокси или null если нет доступных прокси
     */
    public function getRandomProxy(): ?string
    {
        $aliveProxies = $this->getAliveProxies();
        
        if (count($aliveProxies) === 0) {
            $this->logWarning('Нет доступных живых прокси в пуле');
            return null;
        }
        
        $proxiesArray = array_values($aliveProxies);
        $randomIndex = array_rand($proxiesArray);
        
        return $proxiesArray[$randomIndex]['url'];
    }

    /**
     * Получает все живые прокси
     * 
     * @return array<string, array{url: string, alive: bool, last_check: int, success_count: int, fail_count: int, last_error: string}> Массив живых прокси
     */
    private function getAliveProxies(): array
    {
        return array_filter($this->proxies, function (array $proxy): bool {
            return $proxy['alive'] === true;
        });
    }

    /**
     * Проверяет здоровье конкретного прокси
     * 
     * @param string $proxy URL прокси для проверки
     * @return bool true если прокси доступен, false в противном случае
     */
    public function checkProxyHealth(string $proxy): bool
    {
        if (!isset($this->proxies[$proxy])) {
            $this->logWarning('Попытка проверить несуществующий прокси', ['proxy' => $proxy]);
            return false;
        }
        
        try {
            $http = new Http([
                'timeout' => $this->healthCheckTimeout,
                'connect_timeout' => $this->healthCheckTimeout,
                'proxy' => $proxy,
                'verify' => false,
            ], $this->logger);
            
            $response = $http->get($this->healthCheckUrl);
            $statusCode = $response->getStatusCode();
            
            $isAlive = $statusCode >= 200 && $statusCode < 400;
            
            $this->proxies[$proxy]['alive'] = $isAlive;
            $this->proxies[$proxy]['last_check'] = time();
            
            if ($isAlive) {
                $this->proxies[$proxy]['last_error'] = '';
                $this->logInfo('Health check пройден', [
                    'proxy' => $proxy,
                    'status_code' => $statusCode,
                ]);
            } else {
                $this->proxies[$proxy]['last_error'] = "HTTP {$statusCode}";
                $this->logWarning('Health check не пройден', [
                    'proxy' => $proxy,
                    'status_code' => $statusCode,
                ]);
            }
            
            return $isAlive;
        } catch (HttpException $e) {
            $this->proxies[$proxy]['alive'] = false;
            $this->proxies[$proxy]['last_check'] = time();
            $this->proxies[$proxy]['last_error'] = $e->getMessage();
            
            $this->logError('Ошибка health check прокси', [
                'proxy' => $proxy,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Проверяет здоровье всех прокси в пуле
     */
    public function checkAllProxies(): void
    {
        $this->logInfo('Начало проверки всех прокси в пуле', [
            'total_proxies' => count($this->proxies),
        ]);
        
        $aliveCount = 0;
        $deadCount = 0;
        
        foreach ($this->proxies as $proxy => $data) {
            // Проверяем только если прошло достаточно времени с последней проверки
            if ($data['last_check'] > 0 && (time() - $data['last_check']) < $this->healthCheckInterval) {
                if ($data['alive']) {
                    $aliveCount++;
                } else {
                    $deadCount++;
                }
                continue;
            }
            
            $isAlive = $this->checkProxyHealth($proxy);
            
            if ($isAlive) {
                $aliveCount++;
            } else {
                $deadCount++;
            }
        }
        
        $this->logInfo('Проверка всех прокси завершена', [
            'alive' => $aliveCount,
            'dead' => $deadCount,
        ]);
    }

    /**
     * Помечает прокси как мёртвый
     * 
     * @param string $proxy URL прокси
     */
    public function markProxyAsDead(string $proxy): void
    {
        if (!isset($this->proxies[$proxy])) {
            return;
        }
        
        $this->proxies[$proxy]['alive'] = false;
        $this->proxies[$proxy]['fail_count']++;
        
        $this->logWarning('Прокси помечен как мёртвый', [
            'proxy' => $proxy,
            'fail_count' => $this->proxies[$proxy]['fail_count'],
        ]);
    }

    /**
     * Помечает прокси как живой
     * 
     * @param string $proxy URL прокси
     */
    public function markProxyAsAlive(string $proxy): void
    {
        if (!isset($this->proxies[$proxy])) {
            return;
        }
        
        $this->proxies[$proxy]['alive'] = true;
        $this->proxies[$proxy]['success_count']++;
        $this->proxies[$proxy]['last_error'] = '';
        
        $this->logInfo('Прокси помечен как живой', [
            'proxy' => $proxy,
            'success_count' => $this->proxies[$proxy]['success_count'],
        ]);
    }

    /**
     * Получает детальную статистику по всем прокси и пулу
     * 
     * @return array<string, mixed> Статистика пула:
     *   - total_proxies: общее количество прокси
     *   - alive_proxies: количество живых прокси
     *   - dead_proxies: количество мёртвых прокси
     *   - total_requests: общее количество запросов
     *   - successful_requests: количество успешных запросов
     *   - failed_requests: количество неудачных запросов
     *   - total_retries: общее количество повторных попыток
     *   - proxies: детальная информация по каждому прокси
     */
    public function getStatistics(): array
    {
        $aliveCount = 0;
        $deadCount = 0;
        $proxiesStats = [];
        
        foreach ($this->proxies as $proxy => $data) {
            if ($data['alive']) {
                $aliveCount++;
            } else {
                $deadCount++;
            }
            
            $proxiesStats[] = [
                'url' => $proxy,
                'alive' => $data['alive'],
                'last_check' => $data['last_check'],
                'last_check_human' => $data['last_check'] > 0 
                    ? date('Y-m-d H:i:s', $data['last_check']) 
                    : 'никогда',
                'success_count' => $data['success_count'],
                'fail_count' => $data['fail_count'],
                'total_requests' => $data['success_count'] + $data['fail_count'],
                'success_rate' => ($data['success_count'] + $data['fail_count']) > 0
                    ? round($data['success_count'] / ($data['success_count'] + $data['fail_count']) * 100, 2)
                    : 0,
                'last_error' => $data['last_error'],
            ];
        }
        
        return [
            'total_proxies' => count($this->proxies),
            'alive_proxies' => $aliveCount,
            'dead_proxies' => $deadCount,
            'rotation_strategy' => $this->rotationStrategy,
            'total_requests' => $this->poolStats['total_requests'],
            'successful_requests' => $this->poolStats['successful_requests'],
            'failed_requests' => $this->poolStats['failed_requests'],
            'total_retries' => $this->poolStats['total_retries'],
            'success_rate' => $this->poolStats['total_requests'] > 0
                ? round($this->poolStats['successful_requests'] / $this->poolStats['total_requests'] * 100, 2)
                : 0,
            'proxies' => $proxiesStats,
        ];
    }

    /**
     * Выполняет HTTP запрос через прокси с автоматическим retry
     * 
     * При неудаче автоматически переключается на другой прокси и повторяет запрос.
     * Если все прокси не доступны, выбрасывает исключение.
     * 
     * @param string $method HTTP метод (GET, POST, PUT, DELETE и т.д.)
     * @param string $uri URL для запроса
     * @param array<string, mixed> $options Дополнительные опции для запроса
     * @param int $maxRetries Максимальное количество попыток (по умолчанию из конфигурации)
     * @return ResponseInterface HTTP ответ
     * @throws ProxyPoolException Если не удалось выполнить запрос ни через один прокси
     */
    public function request(string $method, string $uri, array $options = [], int $maxRetries = 0): ResponseInterface
    {
        if ($maxRetries === 0) {
            $maxRetries = $this->maxRetries;
        }
        
        $this->poolStats['total_requests']++;
        
        $attempts = 0;
        $lastException = null;
        $triedProxies = [];
        
        while ($attempts < $maxRetries) {
            $proxy = $this->getNextProxy();
            
            if ($proxy === null) {
                $this->logError('Нет доступных прокси для выполнения запроса');
                break;
            }
            
            // Пропускаем уже попробованные прокси
            if (in_array($proxy, $triedProxies, true)) {
                // Если попробовали все доступные прокси
                if (count($triedProxies) >= count($this->getAliveProxies())) {
                    break;
                }
                continue;
            }
            
            $triedProxies[] = $proxy;
            $attempts++;
            
            try {
                $http = new Http(array_merge([
                    'proxy' => $proxy,
                    'verify' => false,
                ], $options), $this->logger);
                
                $response = $http->request($method, $uri, $options);
                $statusCode = $response->getStatusCode();
                
                // Проверяем успешность запроса
                if ($statusCode >= 200 && $statusCode < 400) {
                    $this->markProxyAsAlive($proxy);
                    $this->poolStats['successful_requests']++;
                    
                    if ($attempts > 1) {
                        $this->poolStats['total_retries'] += ($attempts - 1);
                    }
                    
                    $this->logInfo('HTTP запрос через прокси выполнен успешно', [
                        'method' => $method,
                        'uri' => $uri,
                        'proxy' => $proxy,
                        'status_code' => $statusCode,
                        'attempts' => $attempts,
                    ]);
                    
                    return $response;
                }
                
                // Если статус код указывает на ошибку прокси
                $this->markProxyAsDead($proxy);
                $this->proxies[$proxy]['last_error'] = "HTTP {$statusCode}";
                
                $this->logWarning('HTTP запрос через прокси вернул ошибку', [
                    'method' => $method,
                    'uri' => $uri,
                    'proxy' => $proxy,
                    'status_code' => $statusCode,
                    'attempt' => $attempts,
                ]);
                
            } catch (HttpException $e) {
                $this->markProxyAsDead($proxy);
                $this->proxies[$proxy]['last_error'] = $e->getMessage();
                $lastException = $e;
                
                $this->logError('Ошибка HTTP запроса через прокси', [
                    'method' => $method,
                    'uri' => $uri,
                    'proxy' => $proxy,
                    'error' => $e->getMessage(),
                    'attempt' => $attempts,
                ]);
            }
        }
        
        $this->poolStats['failed_requests']++;
        $this->poolStats['total_retries'] += $attempts;
        
        $errorMessage = $lastException !== null 
            ? $lastException->getMessage() 
            : 'Нет доступных прокси или все попытки исчерпаны';
        
        throw new ProxyPoolException(
            sprintf(
                'Не удалось выполнить HTTP запрос [%s %s] через прокси после %d попыток: %s',
                strtoupper($method),
                $uri,
                $attempts,
                $errorMessage
            ),
            0,
            $lastException
        );
    }

    /**
     * Выполняет GET запрос через прокси с автоматическим retry
     * 
     * @param string $uri URL для запроса
     * @param array<string, mixed> $options Дополнительные опции для запроса
     * @param int $maxRetries Максимальное количество попыток
     * @return ResponseInterface HTTP ответ
     * @throws ProxyPoolException Если не удалось выполнить запрос
     */
    public function get(string $uri, array $options = [], int $maxRetries = 0): ResponseInterface
    {
        return $this->request(Http::METHOD_GET, $uri, $options, $maxRetries);
    }

    /**
     * Выполняет POST запрос через прокси с автоматическим retry
     * 
     * @param string $uri URL для запроса
     * @param array<string, mixed> $options Дополнительные опции для запроса
     * @param int $maxRetries Максимальное количество попыток
     * @return ResponseInterface HTTP ответ
     * @throws ProxyPoolException Если не удалось выполнить запрос
     */
    public function post(string $uri, array $options = [], int $maxRetries = 0): ResponseInterface
    {
        return $this->request(Http::METHOD_POST, $uri, $options, $maxRetries);
    }

    /**
     * Выполняет PUT запрос через прокси с автоматическим retry
     * 
     * @param string $uri URL для запроса
     * @param array<string, mixed> $options Дополнительные опции для запроса
     * @param int $maxRetries Максимальное количество попыток
     * @return ResponseInterface HTTP ответ
     * @throws ProxyPoolException Если не удалось выполнить запрос
     */
    public function put(string $uri, array $options = [], int $maxRetries = 0): ResponseInterface
    {
        return $this->request(Http::METHOD_PUT, $uri, $options, $maxRetries);
    }

    /**
     * Выполняет DELETE запрос через прокси с автоматическим retry
     * 
     * @param string $uri URL для запроса
     * @param array<string, mixed> $options Дополнительные опции для запроса
     * @param int $maxRetries Максимальное количество попыток
     * @return ResponseInterface HTTP ответ
     * @throws ProxyPoolException Если не удалось выполнить запрос
     */
    public function delete(string $uri, array $options = [], int $maxRetries = 0): ResponseInterface
    {
        return $this->request(Http::METHOD_DELETE, $uri, $options, $maxRetries);
    }

    /**
     * Получает прямой доступ к HTTP клиенту с установленным прокси
     * 
     * @param string|null $proxy URL прокси или null для получения следующего по ротации
     * @return Http HTTP клиент с настроенным прокси
     * @throws ProxyPoolException Если нет доступных прокси
     */
    public function getHttpClient(?string $proxy = null): Http
    {
        if ($proxy === null) {
            $proxy = $this->getNextProxy();
        }
        
        if ($proxy === null) {
            throw new ProxyPoolException('Нет доступных прокси для создания HTTP клиента');
        }
        
        return new Http([
            'proxy' => $proxy,
            'verify' => false,
        ], $this->logger);
    }

    /**
     * Получает список всех прокси с их статусом
     * 
     * @return array<string, array{url: string, alive: bool}> Массив прокси
     */
    public function getAllProxies(): array
    {
        $result = [];
        
        foreach ($this->proxies as $proxy => $data) {
            $result[$proxy] = [
                'url' => $data['url'],
                'alive' => $data['alive'],
            ];
        }
        
        return $result;
    }

    /**
     * Очищает пул прокси
     */
    public function clearProxies(): void
    {
        $count = count($this->proxies);
        $this->proxies = [];
        $this->currentIndex = 0;
        
        $this->logInfo('Пул прокси очищен', ['removed_count' => $count]);
    }

    /**
     * Сбрасывает статистику пула
     */
    public function resetStatistics(): void
    {
        $this->poolStats = [
            'total_requests' => 0,
            'successful_requests' => 0,
            'failed_requests' => 0,
            'total_retries' => 0,
        ];
        
        foreach ($this->proxies as $proxy => $data) {
            $this->proxies[$proxy]['success_count'] = 0;
            $this->proxies[$proxy]['fail_count'] = 0;
        }
        
        $this->logInfo('Статистика пула сброшена');
    }

    /**
     * Валидирует конфигурацию менеджера
     * 
     * @throws ProxyPoolValidationException Если конфигурация некорректна
     */
    private function validateConfiguration(): void
    {
        $allowedStrategies = [self::ROTATION_ROUND_ROBIN, self::ROTATION_RANDOM];
        
        if (!in_array($this->rotationStrategy, $allowedStrategies, true)) {
            throw new ProxyPoolValidationException(
                sprintf(
                    'Недопустимая стратегия ротации: %s. Допустимые значения: %s',
                    $this->rotationStrategy,
                    implode(', ', $allowedStrategies)
                )
            );
        }
        
        if ($this->healthCheckTimeout < 1) {
            throw new ProxyPoolValidationException('Таймаут health-check должен быть не менее 1 секунды');
        }
        
        if ($this->healthCheckUrl === '') {
            throw new ProxyPoolValidationException('URL для health-check не может быть пустым');
        }
    }

    /**
     * Валидирует URL прокси
     * 
     * @param string $proxy URL прокси для валидации
     * @throws ProxyPoolValidationException Если URL прокси невалиден
     */
    private function validateProxyUrl(string $proxy): void
    {
        // Базовая валидация формата прокси
        if (!preg_match('#^(https?|socks4|socks5)://.*#i', $proxy)) {
            throw new ProxyPoolValidationException(
                sprintf('Невалидный формат прокси URL: %s. Ожидается формат: protocol://host:port', $proxy)
            );
        }
    }

    /**
     * Записывает информационное сообщение в лог
     * 
     * @param string $message Текст сообщения
     * @param array<string, mixed> $context Контекст
     */
    private function logInfo(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->info($message, $context);
        }
    }

    /**
     * Записывает предупреждение в лог
     * 
     * @param string $message Текст сообщения
     * @param array<string, mixed> $context Контекст
     */
    private function logWarning(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->warning($message, $context);
        }
    }

    /**
     * Записывает ошибку в лог
     * 
     * @param string $message Текст сообщения
     * @param array<string, mixed> $context Контекст
     */
    private function logError(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->error($message, $context);
        }
    }
}

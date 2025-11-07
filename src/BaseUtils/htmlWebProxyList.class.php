<?php

declare(strict_types=1);

namespace App\Component;

use App\Component\Exception\htmlWebProxyList\HtmlWebProxyListException;
use App\Component\Exception\htmlWebProxyList\HtmlWebProxyListValidationException;
use App\Component\Exception\HttpException;
use App\Component\Config\ConfigLoader;

/**
 * Класс для получения списка прокси-серверов с htmlweb.ru API
 * 
 * Возвращает список прокси серверов в формате, совместимом с ProxyPool.
 * 
 * API документация: https://htmlweb.ru/analiz/proxy_list.php#api
 * 
 * Поддерживаемые параметры:
 * - api_key: API ключ из профиля (обязательный)
 * - country: Код страны (RU, US и т.д.) или список через запятую
 * - country_not: Исключить страны, через запятую
 * - perpage: Количество прокси на странице (по умолчанию 20, за каждые 20 списывается 1 запрос)
 * - work: Работоспособность из России (1 - работает, 0 - не работает)
 * - type: Тип прокси (HTTP, HTTPS, SOCKS4, SOCKS5)
 * - p: Номер страницы (с какого прокси начинать выдачу)
 * - short: Краткий формат вывода (пустое, 2, 4)
 */
class htmlWebProxyList
{
    /**
     * URL API для получения списка прокси
     */
    private const API_URL = 'http://htmlweb.ru/json/proxy/get';
    
    /**
     * Значения по умолчанию
     */
    private const DEFAULT_TIMEOUT = 10;
    private const DEFAULT_PERPAGE = 20;
    
    /**
     * Максимальные значения для валидации
     */
    private const MAX_SPEED = 100000;
    private const MIN_SPEED = 0;
    
    /**
     * Допустимые значения параметров
     */
    private const ALLOWED_WORK_VALUES = [0, 1];
    private const ALLOWED_TYPE_VALUES = ['HTTP', 'HTTPS', 'SOCKS4', 'SOCKS5'];
    private const ALLOWED_SHORT_VALUES = ['', '2', '4', 2, 4];
    
    /**
     * HTTP клиент для выполнения запросов
     */
    private Http $http;
    
    /**
     * Экземпляр логгера для записи событий
     */
    private ?Logger $logger;
    
    /**
     * Таймаут для HTTP запросов в секундах
     */
    private int $timeout;
    
    /**
     * Параметры конфигурации для получения прокси
     * @var array<string, mixed>
     */
    private array $params;
    
    /**
     * API ключ для доступа к htmlweb.ru
     */
    private string $apiKey;
    
    /**
     * Остаток доступных запросов из последнего ответа API
     */
    private ?int $remainingLimit = null;

    /**
     * Создает экземпляр класса из конфигурационного файла
     * 
     * @param string $configPath Путь к JSON конфигурационному файлу
     * @param Logger|null $logger Инстанс логгера для записи событий
     * @return self Экземпляр класса htmlWebProxyList
     * @throws HtmlWebProxyListException Если не удалось загрузить конфигурацию
     * @throws HtmlWebProxyListValidationException Если конфигурация некорректна
     */
    public static function fromConfig(string $configPath, ?Logger $logger = null): self
    {
        try {
            $config = ConfigLoader::load($configPath);
        } catch (\Exception $e) {
            throw new HtmlWebProxyListException(
                'Не удалось загрузить конфигурацию: ' . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
        
        // Извлекаем API ключ из конфигурации
        if (!isset($config['api_key']) || empty($config['api_key'])) {
            throw new HtmlWebProxyListValidationException(
                'API ключ (api_key) не указан в конфигурационном файле'
            );
        }
        
        $apiKey = (string)$config['api_key'];
        
        // Удаляем служебные поля из конфигурации
        unset($config['api_key'], $config['_comment'], $config['_fields']);
        
        return new self($apiKey, $config, $logger);
    }

    /**
     * Конструктор класса HtmlWebProxyList
     * 
     * @param string $apiKey API ключ из профиля htmlweb.ru (обязательный)
     * @param array<string, mixed> $config Конфигурация для получения прокси:
     *   - country: Код страны (например: RU, US, GB) или список через запятую (string|array)
     *   - country_not: Исключить страны, через запятую (string|array)
     *   - perpage: Количество прокси на странице (int, по умолчанию 20)
     *   - work: Фильтр по работоспособности из России: 1 (работает), 0 (не работает) (int)
     *   - type: Тип прокси: HTTP, HTTPS, SOCKS4, SOCKS5 (string)
     *   - p: Номер страницы (int)
     *   - short: Краткий формат вывода: пустое, 2 (с протоколами), 4 (текстовый список) (int|string)
     *   - timeout: Таймаут HTTP запроса в секундах (int)
     * @param Logger|null $logger Инстанс логгера для записи событий
     * @throws HtmlWebProxyListValidationException Если конфигурация некорректна
     */
    public function __construct(string $apiKey, array $config = [], ?Logger $logger = null)
    {
        if (empty($apiKey)) {
            throw new HtmlWebProxyListValidationException(
                'API ключ (api_key) является обязательным параметром'
            );
        }
        
        $this->apiKey = $apiKey;
        $this->logger = $logger;
        $this->timeout = max(1, (int)($config['timeout'] ?? self::DEFAULT_TIMEOUT));
        
        $this->params = $this->extractAndValidateParams($config);
        
        $this->http = new Http([
            'timeout' => $this->timeout,
            'connect_timeout' => $this->timeout,
            'verify' => true,
            'allow_redirects' => true,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'application/json,text/html,*/*',
            ],
        ], $logger);
        
        $this->logInfo('htmlWebProxyList инициализирован', [
            'timeout' => $this->timeout,
            'params' => $this->params,
        ]);
    }

    /**
     * Извлекает и валидирует параметры конфигурации
     * 
     * @param array<string, mixed> $config Конфигурация
     * @return array<string, mixed> Валидированные параметры
     * @throws HtmlWebProxyListValidationException Если параметры некорректны
     */
    private function extractAndValidateParams(array $config): array
    {
        $params = [];
        
        // Параметр country - код страны или список через запятую
        if (isset($config['country'])) {
            if (is_array($config['country'])) {
                $country = implode(',', array_map('strtoupper', $config['country']));
            } else {
                $country = trim((string)$config['country']);
            }
            if ($country !== '') {
                $params['country'] = strtoupper($country);
            }
        }
        
        // Параметр country_not - исключить страны
        if (isset($config['country_not'])) {
            if (is_array($config['country_not'])) {
                $countryNot = implode(',', array_map('strtoupper', $config['country_not']));
            } else {
                $countryNot = trim((string)$config['country_not']);
            }
            if ($countryNot !== '') {
                $params['country_not'] = strtoupper($countryNot);
            }
        }
        
        // Параметр perpage - количество прокси на странице
        if (isset($config['perpage'])) {
            $perpage = (int)$config['perpage'];
            if ($perpage < 1) {
                throw new HtmlWebProxyListValidationException(
                    "Параметр perpage должен быть больше 0, указано: {$perpage}"
                );
            }
            $params['perpage'] = $perpage;
        }
        
        // Параметр work - работоспособность из России (1 - работает, 0 - не работает)
        if (isset($config['work'])) {
            $work = (int)$config['work'];
            if (!in_array($work, self::ALLOWED_WORK_VALUES, true)) {
                throw new HtmlWebProxyListValidationException(
                    "Параметр work должен быть 1 (работает из России) или 0 (не работает), указано: {$work}"
                );
            }
            $params['work'] = $work;
        }
        
        // Параметр type - тип прокси (HTTP, HTTPS, SOCKS4, SOCKS5)
        if (isset($config['type'])) {
            $type = strtoupper(trim((string)$config['type']));
            if (!in_array($type, self::ALLOWED_TYPE_VALUES, true)) {
                throw new HtmlWebProxyListValidationException(
                    "Параметр type должен быть одним из: " . implode(', ', self::ALLOWED_TYPE_VALUES) . ", указано: {$type}"
                );
            }
            $params['type'] = $type;
        }
        
        // Параметр p - номер страницы
        if (isset($config['p'])) {
            $page = (int)$config['p'];
            if ($page < 1) {
                throw new HtmlWebProxyListValidationException(
                    "Параметр p (номер страницы) должен быть больше 0, указано: {$page}"
                );
            }
            $params['p'] = $page;
        }
        
        // Параметр short - краткий формат вывода (пустое, 2, 4)
        if (isset($config['short'])) {
            $short = $config['short'];
            // Приводим к строке для проверки
            $shortStr = (string)$short;
            if ($shortStr !== '' && !in_array($short, self::ALLOWED_SHORT_VALUES, true)) {
                throw new HtmlWebProxyListValidationException(
                    "Параметр short должен быть пустым, 2 или 4, указано: {$short}"
                );
            }
            if ($shortStr !== '') {
                $params['short'] = $short;
            }
        }
        
        return $params;
    }

    /**
     * Получает список прокси серверов с htmlweb.ru API
     * 
     * @return array<int, string> Массив URL прокси серверов в формате для ProxyPool
     * @throws HtmlWebProxyListException Если не удалось получить список прокси
     */
    public function getProxies(): array
    {
        try {
            // Добавляем api_key к параметрам запроса
            $queryParams = array_merge($this->params, ['api_key' => $this->apiKey]);
            
            $this->logInfo('Запрос списка прокси с htmlweb.ru', [
                'params' => $this->params,
            ]);
            
            $response = $this->http->get(self::API_URL, [
                'query' => $queryParams,
            ]);
            
            $statusCode = $response->getStatusCode();
            
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new HtmlWebProxyListException(
                    "API вернул ошибочный статус код: {$statusCode}"
                );
            }
            
            $body = $response->getBody()->getContents();
            
            if (empty($body)) {
                $this->logWarning('API вернул пустой ответ');
                return [];
            }
            
            $proxies = $this->parseProxyList($body);
            
            $this->logInfo('Получен список прокси', [
                'count' => count($proxies),
                'remaining_limit' => $this->remainingLimit,
            ]);
            
            return $proxies;
            
        } catch (HttpException $e) {
            $this->logError('Ошибка HTTP запроса к API htmlweb.ru', [
                'error' => $e->getMessage(),
            ]);
            
            throw new HtmlWebProxyListException(
                'Не удалось выполнить запрос к API htmlweb.ru: ' . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Парсит ответ API и извлекает список прокси
     * 
     * API может возвращать данные в разных форматах:
     * - JSON с полной информацией о прокси (по умолчанию)
     * - JSON со списком прокси (short)
     * - JSON со списком прокси с протоколами (short=2)
     * - Текстовый список IP:PORT (short=4)
     * 
     * @param string $body Тело ответа от API
     * @return array<int, string> Массив URL прокси серверов
     * @throws HtmlWebProxyListException Если не удалось распарсить ответ
     */
    private function parseProxyList(string $body): array
    {
        $proxies = [];
        
        // Проверяем формат short=4 (текстовый список)
        if (isset($this->params['short']) && (int)$this->params['short'] === 4) {
            $proxies = $this->parseTextFormat($body);
        } else {
            // JSON формат (по умолчанию, short или short=2)
            $proxies = $this->parseJsonFormat($body);
        }
        
        if (empty($proxies)) {
            $this->logWarning('Не удалось извлечь прокси из ответа API');
        }
        
        return $proxies;
    }

    /**
     * Парсит JSON формат ответа API
     * 
     * Форматы ответа:
     * 1. По умолчанию: {"0":{"name":"IP:PORT","work":1,"type":"HTTP","speed":94,...},"limit":9323}
     * 2. short: {"0":"IP:PORT","1":"IP:PORT",...,"limit":18}
     * 3. short=2: {"0":"protocol://IP:PORT","1":"protocol://IP:PORT",...,"limit":18}
     * 
     * @param string $body Тело ответа в JSON формате
     * @return array<int, string> Массив URL прокси
     * @throws HtmlWebProxyListException Если не удалось декодировать JSON
     */
    private function parseJsonFormat(string $body): array
    {
        $proxies = [];
        
        // Декодируем JSON
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new HtmlWebProxyListException(
                'Ошибка парсинга JSON ответа от API: ' . json_last_error_msg()
            );
        }
        
        if (!is_array($data)) {
            throw new HtmlWebProxyListException(
                'API вернул некорректный формат данных'
            );
        }
        
        // Извлекаем поле limit (остаток запросов)
        if (isset($data['limit'])) {
            $this->remainingLimit = (int)$data['limit'];
            unset($data['limit']);
        }
        
        // Проверяем на ошибки в ответе
        if (isset($data['error'])) {
            throw new HtmlWebProxyListException(
                'API вернул ошибку: ' . $data['error']
            );
        }
        
        // Парсим прокси в зависимости от формата
        $shortFormat = $this->params['short'] ?? null;
        
        foreach ($data as $key => $value) {
            // Пропускаем не числовые ключи
            if (!is_numeric($key)) {
                continue;
            }
            
            if ($shortFormat === 2 || $shortFormat === '2') {
                // short=2: прокси уже с протоколом (protocol://IP:PORT)
                $proxies[] = $this->normalizeProxyUrl((string)$value);
            } elseif ($shortFormat !== null && $shortFormat !== '') {
                // short (без значения): только IP:PORT
                $proxies[] = $this->buildProxyUrl((string)$value);
            } elseif (is_array($value) && isset($value['name'])) {
                // Полный формат: извлекаем name и type
                $proxyAddress = (string)$value['name'];
                $proxyType = isset($value['type']) ? strtolower((string)$value['type']) : 'http';
                $proxies[] = "{$proxyType}://{$proxyAddress}";
            }
        }
        
        return $proxies;
    }

    /**
     * Парсит текстовый формат вывода (short=4)
     * 
     * Формат: IP:PORT на каждой строке
     * Пример:
     * 192.168.1.1:8080
     * 10.0.0.1:3128
     * 
     * @param string $body Тело ответа
     * @return array<int, string> Массив URL прокси
     */
    private function parseTextFormat(string $body): array
    {
        $proxies = [];
        $lines = explode("\n", $body);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if ($line === '' || $line === '#' || str_starts_with($line, '#')) {
                continue;
            }
            
            if (preg_match('/^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d+)$/', $line, $matches)) {
                $ip = $matches[1];
                $port = $matches[2];
                
                if ($this->validateIpAddress($ip) && $this->validatePort((int)$port)) {
                    $proxies[] = $this->buildProxyUrl("{$ip}:{$port}");
                }
            }
        }
        
        return $proxies;
    }
    
    /**
     * Строит URL прокси с протоколом
     * 
     * @param string $address Адрес прокси в формате IP:PORT
     * @return string URL прокси с протоколом
     */
    private function buildProxyUrl(string $address): string
    {
        $type = strtolower($this->params['type'] ?? 'http');
        return "{$type}://{$address}";
    }
    
    /**
     * Нормализует URL прокси (добавляет протокол если отсутствует)
     * 
     * @param string $url URL прокси
     * @return string Нормализованный URL
     */
    private function normalizeProxyUrl(string $url): string
    {
        // Если URL уже содержит протокол, возвращаем как есть
        if (preg_match('/^[a-z0-9]+:\/\//i', $url)) {
            return $url;
        }
        
        // Иначе добавляем протокол по умолчанию
        return $this->buildProxyUrl($url);
    }

    /**
     * Валидирует IP адрес
     * 
     * @param string $ip IP адрес для валидации
     * @return bool true если IP адрес валиден
     */
    private function validateIpAddress(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
            return true;
        }
        
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return true;
        }
        
        return false;
    }

    /**
     * Валидирует порт
     * 
     * @param int $port Номер порта
     * @return bool true если порт валиден
     */
    private function validatePort(int $port): bool
    {
        return $port > 0 && $port <= 65535;
    }

    /**
     * Обновляет параметры конфигурации
     * 
     * Позволяет изменить параметры без пересоздания объекта
     * 
     * @param array<string, mixed> $config Новые параметры конфигурации
     * @throws HtmlWebProxyListValidationException Если параметры некорректны
     */
    public function updateParams(array $config): void
    {
        $newParams = $this->extractAndValidateParams($config);
        $this->params = array_merge($this->params, $newParams);
        
        $this->logInfo('Параметры обновлены', [
            'params' => $this->params,
        ]);
    }

    /**
     * Возвращает текущие параметры конфигурации
     * 
     * @return array<string, mixed> Текущие параметры
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Сбрасывает параметры конфигурации к значениям по умолчанию
     */
    public function resetParams(): void
    {
        $this->params = [];
        
        $this->logInfo('Параметры сброшены к значениям по умолчанию');
    }
    
    /**
     * Возвращает остаток доступных запросов из последнего ответа API
     * 
     * @return int|null Остаток запросов или null если запросы еще не выполнялись
     */
    public function getRemainingLimit(): ?int
    {
        return $this->remainingLimit;
    }

    /**
     * Получает список прокси и добавляет их в указанный ProxyPool
     * 
     * Удобный метод для прямой интеграции с ProxyPool
     * 
     * @param ProxyPool $proxyPool Экземпляр ProxyPool для добавления прокси
     * @return int Количество добавленных прокси
     * @throws HtmlWebProxyListException Если не удалось получить список прокси
     */
    public function loadIntoProxyPool(ProxyPool $proxyPool): int
    {
        $proxies = $this->getProxies();
        
        $addedCount = 0;
        foreach ($proxies as $proxy) {
            try {
                $proxyPool->addProxy($proxy);
                $addedCount++;
            } catch (\Exception $e) {
                $this->logWarning('Не удалось добавить прокси в ProxyPool', [
                    'proxy' => $proxy,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        $this->logInfo('Прокси добавлены в ProxyPool', [
            'total' => count($proxies),
            'added' => $addedCount,
        ]);
        
        return $addedCount;
    }

    /**
     * Записывает информационное сообщение в лог
     * 
     * @param string $message Текст сообщения
     * @param array<string, mixed> $context Дополнительные данные
     */
    private function logInfo(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->info('[htmlWebProxyList] ' . $message, $context);
        }
    }

    /**
     * Записывает предупреждение в лог
     * 
     * @param string $message Текст сообщения
     * @param array<string, mixed> $context Дополнительные данные
     */
    private function logWarning(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->warning('[htmlWebProxyList] ' . $message, $context);
        }
    }

    /**
     * Записывает сообщение об ошибке в лог
     * 
     * @param string $message Текст сообщения
     * @param array<string, mixed> $context Дополнительные данные
     */
    private function logError(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->error('[htmlWebProxyList] ' . $message, $context);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Component;

use App\Component\Exception\HtmlWebProxyListException;
use App\Component\Exception\HtmlWebProxyListValidationException;
use App\Component\Exception\HttpException;

/**
 * Класс для получения списка прокси-серверов с htmlweb.ru API
 * 
 * Возвращает список прокси серверов в формате, совместимом с ProxyPool.
 * 
 * API документация: https://htmlweb.ru/analiz/proxy_list.php#api
 * 
 * Поддерживаемые параметры:
 * - country: Код страны (RU, US и т.д.)
 * - country_not: Исключить страны
 * - perpage: Количество прокси на странице (макс 50)
 * - work: Тип работы (yes, maybe, no)
 * - type: Тип прокси (http, https, socks4, socks5)
 * - speed_max: Максимальная скорость в мс
 * - page: Номер страницы
 * - short: Краткий формат вывода (only_ip)
 */
class htmlWebProxyList
{
    /**
     * URL API для получения списка прокси
     */
    private const API_URL = 'https://htmlweb.ru/analiz/proxy_list.php';
    
    /**
     * Значения по умолчанию
     */
    private const DEFAULT_TIMEOUT = 10;
    private const DEFAULT_PERPAGE = 50;
    private const DEFAULT_PAGE = 1;
    private const DEFAULT_TYPE = 'http';
    
    /**
     * Максимальные значения для валидации
     */
    private const MAX_PERPAGE = 50;
    private const MAX_SPEED = 10000;
    private const MIN_SPEED = 0;
    
    /**
     * Допустимые значения параметров
     */
    private const ALLOWED_WORK_VALUES = ['yes', 'maybe', 'no'];
    private const ALLOWED_TYPE_VALUES = ['http', 'https', 'socks4', 'socks5'];
    private const ALLOWED_SHORT_VALUES = ['only_ip'];
    
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
     * Конструктор класса HtmlWebProxyList
     * 
     * @param array<string, mixed> $config Конфигурация для получения прокси:
     *   - country: Код страны (например: RU, US, GB) (string)
     *   - country_not: Исключить страны, через запятую (string)
     *   - perpage: Количество прокси на странице, максимум 50 (int)
     *   - work: Фильтр по работоспособности: yes, maybe, no (string)
     *   - type: Тип прокси: http, https, socks4, socks5 (string)
     *   - speed_max: Максимальная скорость прокси в миллисекундах (int)
     *   - page: Номер страницы (int)
     *   - short: Краткий формат вывода: only_ip (string)
     *   - timeout: Таймаут HTTP запроса в секундах (int)
     * @param Logger|null $logger Инстанс логгера для записи событий
     * @throws HtmlWebProxyListValidationException Если конфигурация некорректна
     */
    public function __construct(array $config = [], ?Logger $logger = null)
    {
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
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
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
        
        if (isset($config['country'])) {
            $country = trim((string)$config['country']);
            if ($country !== '') {
                $params['country'] = strtoupper($country);
            }
        }
        
        if (isset($config['country_not'])) {
            $countryNot = trim((string)$config['country_not']);
            if ($countryNot !== '') {
                $params['country_not'] = strtoupper($countryNot);
            }
        }
        
        if (isset($config['perpage'])) {
            $perpage = (int)$config['perpage'];
            if ($perpage < 1 || $perpage > self::MAX_PERPAGE) {
                throw new HtmlWebProxyListValidationException(
                    "Параметр perpage должен быть от 1 до " . self::MAX_PERPAGE . ", указано: {$perpage}"
                );
            }
            $params['perpage'] = $perpage;
        }
        
        if (isset($config['work'])) {
            $work = trim(strtolower((string)$config['work']));
            if (!in_array($work, self::ALLOWED_WORK_VALUES, true)) {
                throw new HtmlWebProxyListValidationException(
                    "Параметр work должен быть одним из: " . implode(', ', self::ALLOWED_WORK_VALUES) . ", указано: {$work}"
                );
            }
            $params['work'] = $work;
        }
        
        if (isset($config['type'])) {
            $type = trim(strtolower((string)$config['type']));
            if (!in_array($type, self::ALLOWED_TYPE_VALUES, true)) {
                throw new HtmlWebProxyListValidationException(
                    "Параметр type должен быть одним из: " . implode(', ', self::ALLOWED_TYPE_VALUES) . ", указано: {$type}"
                );
            }
            $params['type'] = $type;
        }
        
        if (isset($config['speed_max'])) {
            $speedMax = (int)$config['speed_max'];
            if ($speedMax < self::MIN_SPEED || $speedMax > self::MAX_SPEED) {
                throw new HtmlWebProxyListValidationException(
                    "Параметр speed_max должен быть от " . self::MIN_SPEED . " до " . self::MAX_SPEED . ", указано: {$speedMax}"
                );
            }
            $params['speed_max'] = $speedMax;
        }
        
        if (isset($config['page'])) {
            $page = (int)$config['page'];
            if ($page < 1) {
                throw new HtmlWebProxyListValidationException(
                    "Параметр page должен быть больше 0, указано: {$page}"
                );
            }
            $params['page'] = $page;
        }
        
        if (isset($config['short'])) {
            $short = trim(strtolower((string)$config['short']));
            if ($short !== '' && !in_array($short, self::ALLOWED_SHORT_VALUES, true)) {
                throw new HtmlWebProxyListValidationException(
                    "Параметр short должен быть одним из: " . implode(', ', self::ALLOWED_SHORT_VALUES) . ", указано: {$short}"
                );
            }
            if ($short !== '') {
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
            $this->logInfo('Запрос списка прокси с htmlweb.ru', [
                'params' => $this->params,
            ]);
            
            $response = $this->http->get(self::API_URL, [
                'query' => $this->params,
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
     * - HTML таблица с прокси
     * - Текстовый список (при использовании short=only_ip)
     * 
     * @param string $body Тело ответа от API
     * @return array<int, string> Массив URL прокси серверов
     * @throws HtmlWebProxyListException Если не удалось распарсить ответ
     */
    private function parseProxyList(string $body): array
    {
        $proxies = [];
        
        if (isset($this->params['short']) && $this->params['short'] === 'only_ip') {
            $proxies = $this->parseShortFormat($body);
        } else {
            $proxies = $this->parseHtmlFormat($body);
        }
        
        if (empty($proxies)) {
            $this->logWarning('Не удалось извлечь прокси из ответа API');
        }
        
        return $proxies;
    }

    /**
     * Парсит краткий формат вывода (short=only_ip)
     * 
     * Формат: IP:PORT на каждой строке
     * Пример:
     * 192.168.1.1:8080
     * 10.0.0.1:3128
     * 
     * @param string $body Тело ответа
     * @return array<int, string> Массив URL прокси
     */
    private function parseShortFormat(string $body): array
    {
        $proxies = [];
        $lines = explode("\n", $body);
        
        $type = $this->params['type'] ?? self::DEFAULT_TYPE;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if ($line === '' || $line === '#' || str_starts_with($line, '#')) {
                continue;
            }
            
            if (preg_match('/^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d+)$/', $line, $matches)) {
                $ip = $matches[1];
                $port = $matches[2];
                
                if ($this->validateIpAddress($ip) && $this->validatePort((int)$port)) {
                    $proxies[] = "{$type}://{$ip}:{$port}";
                }
            }
        }
        
        return $proxies;
    }

    /**
     * Парсит HTML формат вывода
     * 
     * Извлекает прокси из HTML таблицы
     * 
     * @param string $body Тело ответа в HTML формате
     * @return array<int, string> Массив URL прокси
     */
    private function parseHtmlFormat(string $body): array
    {
        $proxies = [];
        
        $type = $this->params['type'] ?? self::DEFAULT_TYPE;
        
        if (preg_match_all('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d+)/', $body, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $ip = $match[1];
                $port = $match[2];
                
                if ($this->validateIpAddress($ip) && $this->validatePort((int)$port)) {
                    $proxyUrl = "{$type}://{$ip}:{$port}";
                    
                    if (!in_array($proxyUrl, $proxies, true)) {
                        $proxies[] = $proxyUrl;
                    }
                }
            }
        }
        
        return $proxies;
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

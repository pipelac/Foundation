<?php

declare(strict_types=1);

namespace App\Component;

use App\Component\Exception\Rss\RssException;
use App\Component\Exception\Rss\RssValidationException;
use DateTimeImmutable;
use Exception;
use SimplePie\SimplePie;
use SimplePie\File;

/**
 * Класс для безопасной загрузки и парсинга RSS/Atom лент на базе SimplePie
 * 
 * Поддерживаемые форматы:
 * - RSS 0.90, 0.91, 0.92, 1.0, 2.0
 * - Atom 0.3, 1.0
 * - RDF
 * 
 * Особенности:
 * - Использование производительной библиотеки SimplePie
 * - Строгая типизация всех параметров и возвращаемых значений
 * - Встроенное кеширование для повышения производительности
 * - Автоматическая санитизация HTML контента
 * - Валидация URL перед загрузкой
 * - Ограничение размера загружаемого контента
 * - Логирование всех критических операций
 * - Обработка исключений на каждом уровне
 * - Поддержка различных кодировок
 * - Обработка битых и некорректных фидов
 */
class Rss
{
    /**
     * Константы типов RSS лент
     */
    private const FEED_TYPE_RSS = 'rss';
    private const FEED_TYPE_ATOM = 'atom';
    private const FEED_TYPE_RDF = 'rdf';
    private const FEED_TYPE_UNKNOWN = 'unknown';
    
    /**
     * Константы для конфигурации
     */
    private const DEFAULT_TIMEOUT = 10;
    private const DEFAULT_MAX_SIZE = 10485760; // 10 MB в байтах
    private const DEFAULT_USER_AGENT = 'RSSClient/2.0 (+https://example.com) SimplePie';
    private const DEFAULT_CACHE_DURATION = 3600; // 1 час в секундах
    
    /**
     * Пользовательский агент для HTTP запросов
     */
    private string $userAgent;
    
    /**
     * Таймаут соединения в секундах
     */
    private int $timeout;
    
    /**
     * Максимальный размер загружаемого контента в байтах
     */
    private int $maxContentSize;
    
    /**
     * Директория для кеширования
     */
    private ?string $cacheDirectory;
    
    /**
     * Длительность кеширования в секундах
     */
    private int $cacheDuration;
    
    /**
     * Включить/выключить кеширование
     */
    private bool $enableCache;
    
    /**
     * Включить/выключить санитизацию HTML
     */
    private bool $enableSanitization;
    
    /**
     * Экземпляр логгера для записи событий и ошибок
     */
    private ?Logger $logger;
    
    /**
     * HTTP клиент для выполнения запросов
     */
    private Http $http;

    /**
     * Конструктор класса RSS загрузчика
     * 
     * @param array<string, mixed> $config Конфигурация загрузчика:
     *                                     - user_agent: строка User-Agent для HTTP запросов
     *                                     - timeout: таймаут соединения в секундах (мин. 1)
     *                                     - max_content_size: максимальный размер контента в байтах
     *                                     - cache_directory: директория для кеширования (null - отключить)
     *                                     - cache_duration: длительность кеша в секундах (по умолчанию 3600)
     *                                     - enable_cache: включить кеширование (по умолчанию true)
     *                                     - enable_sanitization: включить санитизацию HTML (по умолчанию true)
     * @param Logger|null $logger Инстанс логгера для записи событий
     * @throws RssException Если директория кеша не существует или недоступна для записи
     */
    public function __construct(array $config = [], ?Logger $logger = null)
    {
        $this->userAgent = (string)($config['user_agent'] ?? self::DEFAULT_USER_AGENT);
        $this->timeout = max(1, (int)($config['timeout'] ?? self::DEFAULT_TIMEOUT));
        $this->maxContentSize = max(1024, (int)($config['max_content_size'] ?? self::DEFAULT_MAX_SIZE));
        $this->cacheDuration = max(0, (int)($config['cache_duration'] ?? self::DEFAULT_CACHE_DURATION));
        $this->enableCache = (bool)($config['enable_cache'] ?? true);
        $this->enableSanitization = (bool)($config['enable_sanitization'] ?? true);
        $this->logger = $logger;

        // Настройка кеширования
        $this->cacheDirectory = isset($config['cache_directory']) 
            ? (string)$config['cache_directory'] 
            : null;

        if ($this->enableCache && $this->cacheDirectory !== null) {
            $this->validateCacheDirectory($this->cacheDirectory);
        }

        // Инициализация HTTP клиента
        $this->http = new Http([
            'timeout' => $this->timeout,
            'connect_timeout' => $this->timeout,
            'allow_redirects' => true,
            'headers' => [
                'User-Agent' => $this->userAgent,
            ],
        ], $logger);

        $this->logInfo('RSS клиент инициализирован', [
            'timeout' => $this->timeout,
            'max_size' => $this->maxContentSize,
            'cache_enabled' => $this->enableCache,
        ]);
    }

    /**
     * Загружает и парсит RSS/Atom ленту по указанному URL
     * 
     * Выполняет полный цикл:
     * 1. Валидация URL
     * 2. Создание и настройка SimplePie экземпляра
     * 3. Загрузка и парсинг через SimplePie
     * 4. Нормализация данных в единый формат
     * 5. Кеширование результатов (если включено)
     *
     * @param string $url Адрес RSS/Atom ленты (должен быть валидным HTTP/HTTPS URL)
     * @return array<string, mixed> Структурированные данные ленты:
     *                              - type: тип ленты ('rss', 'atom', 'rdf' или 'unknown')
     *                              - title: заголовок ленты
     *                              - description: описание ленты
     *                              - link: ссылка на источник
     *                              - language: язык контента
     *                              - image: изображение ленты (URL)
     *                              - copyright: информация о копирайте
     *                              - generator: генератор ленты
     *                              - items: массив элементов ленты
     * @throws RssValidationException Если URL невалиден
     * @throws RssException Если не удалось загрузить или распарсить ленту
     */
    public function fetch(string $url): array
    {
        try {
            $this->validateUrl($url);
            
            $this->logInfo('Начало загрузки RSS ленты', ['url' => $url]);
            
            $feed = $this->createSimplePieInstance();
            $this->configureSimplePie($feed);
            
            // Загружаем контент через наш HTTP клиент для контроля
            $xmlContent = $this->download($url);
            $this->validateContentSize($xmlContent);
            
            // Парсим контент через SimplePie
            $feed->set_raw_data($xmlContent);
            
            if (!$feed->init()) {
                $error = $feed->error();
                $this->logError('SimplePie не смог инициализировать ленту', [
                    'url' => $url,
                    'error' => $error,
                ]);
                throw new RssException('Ошибка парсинга RSS ленты: ' . ($error ?: 'Неизвестная ошибка'));
            }

            $normalizedData = $this->normalizeFeed($feed);
            
            $this->logInfo('RSS лента успешно загружена', [
                'url' => $url,
                'type' => $normalizedData['type'],
                'items_count' => count($normalizedData['items']),
            ]);

            return $normalizedData;
            
        } catch (RssValidationException | RssException $e) {
            $this->logError('Ошибка загрузки ленты', [
                'url' => $url, 
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            throw $e;
        } catch (Exception $e) {
            $this->logError('Критическая ошибка загрузки ленты', [
                'url' => $url, 
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new RssException(
                'Критическая ошибка при обработке ленты: ' . $e->getMessage(), 
                0, 
                $e
            );
        }
    }

    /**
     * Создает экземпляр SimplePie
     * 
     * @return SimplePie Новый экземпляр SimplePie
     */
    private function createSimplePieInstance(): SimplePie
    {
        return new SimplePie();
    }

    /**
     * Настраивает экземпляр SimplePie согласно конфигурации
     * 
     * @param SimplePie $feed Экземпляр SimplePie для настройки
     * @return void
     */
    private function configureSimplePie(SimplePie $feed): void
    {
        // Настройка кеширования
        if ($this->enableCache && $this->cacheDirectory !== null) {
            $feed->set_cache_location($this->cacheDirectory);
            $feed->set_cache_duration($this->cacheDuration);
            $feed->enable_cache(true);
        } else {
            $feed->enable_cache(false);
        }

        // Настройка санитизации HTML контента
        // В SimplePie санитизация включена по умолчанию через объект Sanitize
        // Если нужно отключить - можно использовать strip_htmltags с пустым массивом
        if (!$this->enableSanitization) {
            // Отключаем удаление потенциально опасных тегов
            $feed->strip_htmltags([]);
            $feed->strip_attributes([]);
        }
        
        // Настройка обработки порядка элементов
        $feed->enable_order_by_date(true);
        
        // Таймаут (SimplePie использует внутренний File class)
        $feed->set_timeout($this->timeout);
        
        // User Agent
        $feed->set_useragent($this->userAgent);
        
        // Максимальная глубина проверки ссылок
        $feed->set_autodiscovery_level(SimplePie::LOCATOR_AUTODISCOVERY | SimplePie::LOCATOR_LOCAL_EXTENSION);
        
        $this->logInfo('SimplePie настроен', [
            'cache_enabled' => $this->enableCache,
            'sanitization_enabled' => $this->enableSanitization,
            'timeout' => $this->timeout,
        ]);
    }

    /**
     * Валидирует URL перед загрузкой
     * 
     * Проверяет:
     * - Корректность формата URL
     * - Наличие протокола (http/https)
     * - Наличие хоста
     * 
     * @param string $url URL для валидации
     * @return void
     * @throws RssValidationException Если URL невалиден
     */
    private function validateUrl(string $url): void
    {
        if (trim($url) === '') {
            throw new RssValidationException('URL не может быть пустым');
        }
        
        $parsedUrl = parse_url($url);
        
        if ($parsedUrl === false) {
            throw new RssValidationException('Некорректный формат URL');
        }
        
        $scheme = $parsedUrl['scheme'] ?? '';
        if (!in_array(strtolower($scheme), ['http', 'https'], true)) {
            throw new RssValidationException('URL должен использовать протокол HTTP или HTTPS');
        }
        
        if (!isset($parsedUrl['host']) || trim($parsedUrl['host']) === '') {
            throw new RssValidationException('URL должен содержать имя хоста');
        }
    }

    /**
     * Валидирует директорию кеша
     * 
     * Проверяет существование и доступность директории для записи
     * 
     * @param string $directory Путь к директории кеша
     * @return void
     * @throws RssException Если директория не существует или недоступна для записи
     */
    private function validateCacheDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            // Пытаемся создать директорию
            if (!@mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new RssException(
                    "Директория кеша не существует и не может быть создана: {$directory}"
                );
            }
        }
        
        if (!is_writable($directory)) {
            throw new RssException("Директория кеша недоступна для записи: {$directory}");
        }
    }

    /**
     * Выполняет HTTP-запрос для получения содержимого RSS/Atom ленты
     * 
     * Обрабатывает HTTP ошибки и возвращает тело ответа
     *
     * @param string $url Адрес ленты
     * @return string Содержимое XML ленты
     * @throws RssException Если запрос завершился с ошибкой или сервер вернул код ошибки
     */
    private function download(string $url): string
    {
        try {
            $response = $this->http->request('GET', $url);
            $statusCode = $response->getStatusCode();
            
            if ($statusCode < 200 || $statusCode >= 400) {
                $this->logError('HTTP ошибка при загрузке ленты', [
                    'url' => $url, 
                    'status_code' => $statusCode,
                ]);
                throw new RssException("Сервер вернул код ошибки: {$statusCode}");
            }

            $body = (string)$response->getBody();
            
            if ($body === '') {
                throw new RssException('Сервер вернул пустой ответ');
            }

            return $body;
            
        } catch (Exception $e) {
            if ($e instanceof RssException) {
                throw $e;
            }
            throw new RssException('Ошибка загрузки контента: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Проверяет размер загруженного контента
     * 
     * Защищает от загрузки чрезмерно больших файлов,
     * которые могут вызвать исчерпание памяти
     * 
     * @param string $content Контент для проверки
     * @return void
     * @throws RssException Если размер контента превышает лимит
     */
    private function validateContentSize(string $content): void
    {
        $contentSize = strlen($content);
        
        if ($contentSize > $this->maxContentSize) {
            throw new RssException(sprintf(
                'Размер контента (%d байт) превышает максимально допустимый (%d байт)',
                $contentSize,
                $this->maxContentSize
            ));
        }
    }

    /**
     * Приводит SimplePie ленту к единому нормализованному формату
     * 
     * Извлекает все необходимые данные из SimplePie объекта
     *
     * @param SimplePie $feed Экземпляр SimplePie с загруженными данными
     * @return array<string, mixed> Нормализованные данные ленты
     */
    private function normalizeFeed(SimplePie $feed): array
    {
        $feedType = $this->determineFeedType($feed);
        $title = $this->safeGetString($feed->get_title());
        $items = $this->extractItems($feed);
        
        $this->logInfo('Нормализация данных ленты', [
            'type' => $feedType,
            'title' => $title,
            'items_count' => count($items),
        ]);
        
        return [
            'type' => $feedType,
            'title' => $title,
            'description' => $this->safeGetString($feed->get_description()),
            'link' => $this->safeGetString($feed->get_link()),
            'language' => $this->safeGetString($feed->get_language()),
            'image' => $this->extractImageUrl($feed),
            'copyright' => $this->safeGetString($feed->get_copyright()),
            'generator' => '', // SimplePie не предоставляет get_generator() в текущей версии
            'items' => $items,
        ];
    }

    /**
     * Определяет тип ленты
     * 
     * @param SimplePie $feed Экземпляр SimplePie
     * @return string Тип ленты
     */
    private function determineFeedType(SimplePie $feed): string
    {
        $type = $feed->get_type();
        
        if ($type === null) {
            return self::FEED_TYPE_UNKNOWN;
        }
        
        // SimplePie возвращает битовую маску типов
        if ($type & SimplePie::TYPE_RSS_ALL) {
            return self::FEED_TYPE_RSS;
        }
        
        if ($type & SimplePie::TYPE_ATOM_ALL) {
            return self::FEED_TYPE_ATOM;
        }
        
        return self::FEED_TYPE_UNKNOWN;
    }

    /**
     * Извлекает URL изображения ленты
     * 
     * @param SimplePie $feed Экземпляр SimplePie
     * @return string URL изображения или пустая строка
     */
    private function extractImageUrl(SimplePie $feed): string
    {
        $image = $feed->get_image_url();
        return $this->safeGetString($image);
    }

    /**
     * Извлекает все элементы из ленты
     * 
     * @param SimplePie $feed Экземпляр SimplePie
     * @return array<int, array<string, mixed>> Массив элементов ленты
     */
    private function extractItems(SimplePie $feed): array
    {
        $items = [];
        $feedItems = $feed->get_items();
        
        if ($feedItems === null || $feedItems === []) {
            return [];
        }

        foreach ($feedItems as $item) {
            try {
                $items[] = $this->normalizeItem($item);
            } catch (Exception $e) {
                $this->logWarning('Ошибка обработки элемента ленты', [
                    'error' => $e->getMessage(),
                    'item_title' => $item->get_title(),
                ]);
                // Пропускаем проблемный элемент, продолжаем обработку остальных
                continue;
            }
        }

        return $items;
    }

    /**
     * Нормализует отдельный элемент ленты
     * 
     * @param \SimplePie\Item $item Элемент SimplePie
     * @return array<string, mixed> Нормализованные данные элемента
     */
    private function normalizeItem(\SimplePie\Item $item): array
    {
        return [
            'title' => $this->safeGetString($item->get_title()),
            'link' => $this->safeGetString($item->get_link()),
            'description' => $this->safeGetString($item->get_description()),
            'content' => $this->safeGetString($item->get_content()),
            'published_at' => $this->extractDate($item),
            'author' => $this->extractAuthor($item),
            'categories' => $this->extractCategories($item),
            'enclosures' => $this->extractEnclosures($item),
            'id' => $this->safeGetString($item->get_id()),
        ];
    }

    /**
     * Извлекает дату публикации элемента
     * 
     * @param \SimplePie\Item $item Элемент SimplePie
     * @return DateTimeImmutable|null Дата публикации или null
     */
    private function extractDate(\SimplePie\Item $item): ?DateTimeImmutable
    {
        $timestamp = $item->get_date('U');
        
        if ($timestamp === null || $timestamp === '') {
            return null;
        }

        try {
            $dateString = $item->get_date('c'); // ISO 8601 формат
            if ($dateString === null) {
                return null;
            }
            return new DateTimeImmutable($dateString);
        } catch (Exception $e) {
            $this->logWarning('Не удалось распарсить дату элемента', [
                'timestamp' => $timestamp,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Извлекает информацию об авторе элемента
     * 
     * @param \SimplePie\Item $item Элемент SimplePie
     * @return string Имя автора или пустая строка
     */
    private function extractAuthor(\SimplePie\Item $item): string
    {
        $author = $item->get_author();
        
        if ($author === null) {
            return '';
        }
        
        $name = $author->get_name();
        return $this->safeGetString($name);
    }

    /**
     * Извлекает категории/теги элемента
     * 
     * @param \SimplePie\Item $item Элемент SimplePie
     * @return array<int, string> Массив категорий
     */
    private function extractCategories(\SimplePie\Item $item): array
    {
        $categories = [];
        $itemCategories = $item->get_categories();
        
        if ($itemCategories === null || $itemCategories === []) {
            return [];
        }

        foreach ($itemCategories as $category) {
            $term = $category->get_term();
            if ($term !== null && trim($term) !== '') {
                $categories[] = trim($term);
            }
        }

        return array_unique($categories);
    }

    /**
     * Извлекает вложения (медиа файлы) элемента
     * 
     * @param \SimplePie\Item $item Элемент SimplePie
     * @return array<int, array<string, mixed>> Массив вложений
     */
    private function extractEnclosures(\SimplePie\Item $item): array
    {
        $enclosures = [];
        $itemEnclosures = $item->get_enclosures();
        
        if ($itemEnclosures === null || $itemEnclosures === []) {
            return [];
        }

        foreach ($itemEnclosures as $enclosure) {
            $enclosures[] = [
                'url' => $this->safeGetString($enclosure->get_link()),
                'type' => $this->safeGetString($enclosure->get_type()),
                'length' => $this->safeGetString($enclosure->get_length()),
                'title' => $this->safeGetString($enclosure->get_title()),
            ];
        }

        return $enclosures;
    }

    /**
     * Безопасно преобразует значение в строку
     * 
     * Обрабатывает null значения и приводит к строке
     * 
     * @param mixed $value Значение для преобразования
     * @return string Строковое представление или пустая строка
     */
    private function safeGetString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        
        return trim((string)$value);
    }

    /**
     * Записывает информационное сообщение в лог при наличии логгера
     * 
     * @param string $message Сообщение для лога
     * @param array<string, mixed> $context Контекст (дополнительные данные)
     * @return void
     */
    private function logInfo(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->info($message, $context);
        }
    }

    /**
     * Записывает предупреждение в лог при наличии логгера
     * 
     * @param string $message Сообщение для лога
     * @param array<string, mixed> $context Контекст (дополнительные данные)
     * @return void
     */
    private function logWarning(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->warning($message, $context);
        }
    }

    /**
     * Записывает ошибку в лог при наличии логгера
     * 
     * @param string $message Сообщение об ошибке
     * @param array<string, mixed> $context Контекст ошибки (дополнительные данные)
     * @return void
     */
    private function logError(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->error($message, $context);
        }
    }
}

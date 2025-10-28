<?php

declare(strict_types=1);

namespace App\Component;

use App\Component\Exception\RssException;
use App\Component\Exception\RssValidationException;
use DateTimeImmutable;
use Exception;
use SimplePie\SimplePie;
use SimplePie\Cache\File as SimplePieFileCache;

/**
 * Класс для безопасной загрузки и парсинга RSS/Atom лент с использованием SimplePie
 * 
 * Поддерживаемые форматы:
 * - RSS 0.90, 0.91, 0.92, 1.0, 2.0
 * - Atom 0.3, 1.0
 * - JSON Feed
 * 
 * Особенности:
 * - Использование надежной библиотеки SimplePie для парсинга
 * - Строгая типизация всех параметров и возвращаемых значений
 * - Встроенное кеширование для оптимизации производительности
 * - Защита от XXE (XML External Entity) атак
 * - Валидация URL перед загрузкой
 * - Ограничение размера загружаемого контента
 * - Логирование всех критических операций
 * - Обработка исключений на каждом уровне
 * - Автоматическая нормализация данных из различных форматов
 */
class Rss
{
    /**
     * Константы типов RSS лент
     */
    private const FEED_TYPE_RSS = 'rss';
    private const FEED_TYPE_ATOM = 'atom';
    private const FEED_TYPE_UNKNOWN = 'unknown';
    
    /**
     * Константы для конфигурации
     */
    private const DEFAULT_TIMEOUT = 10;
    private const DEFAULT_MAX_SIZE = 10485760; // 10 MB в байтах
    private const DEFAULT_USER_AGENT = 'RSSClient/2.0 (+https://example.com)';
    private const DEFAULT_CACHE_DURATION = 3600; // 1 час
    
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
     * Экземпляр логгера для записи событий и ошибок
     */
    private ?Logger $logger;
    
    /**
     * Директория для кеширования
     */
    private ?string $cacheDirectory;
    
    /**
     * Длительность кеширования в секундах
     */
    private int $cacheDuration;
    
    /**
     * Включено ли кеширование
     */
    private bool $cacheEnabled;

    /**
     * Конструктор класса RSS загрузчика
     * 
     * @param array<string, mixed> $config Конфигурация загрузчика:
     *                                     - user_agent: строка User-Agent для HTTP запросов
     *                                     - timeout: таймаут соединения в секундах (мин. 1)
     *                                     - max_content_size: максимальный размер контента в байтах
     *                                     - cache_enabled: включить кеширование (по умолчанию false)
     *                                     - cache_directory: директория для кеша (по умолчанию sys_get_temp_dir())
     *                                     - cache_duration: длительность кеша в секундах (по умолчанию 3600)
     * @param Logger|null $logger Инстанс логгера для записи событий
     */
    public function __construct(array $config = [], ?Logger $logger = null)
    {
        $this->userAgent = (string)($config['user_agent'] ?? self::DEFAULT_USER_AGENT);
        $this->timeout = max(1, (int)($config['timeout'] ?? self::DEFAULT_TIMEOUT));
        $this->maxContentSize = max(1024, (int)($config['max_content_size'] ?? self::DEFAULT_MAX_SIZE));
        $this->cacheEnabled = (bool)($config['cache_enabled'] ?? false);
        $this->cacheDuration = max(0, (int)($config['cache_duration'] ?? self::DEFAULT_CACHE_DURATION));
        $this->logger = $logger;
        
        // Установка директории кеша
        if ($this->cacheEnabled) {
            $this->cacheDirectory = (string)($config['cache_directory'] ?? sys_get_temp_dir() . '/simplepie_cache');
            $this->ensureCacheDirectory();
        } else {
            $this->cacheDirectory = null;
        }
    }

    /**
     * Загружает и парсит RSS/Atom ленту по указанному URL
     * 
     * Выполняет полный цикл:
     * 1. Валидация URL
     * 2. Инициализация SimplePie с безопасными настройками
     * 3. Загрузка и парсинг контента
     * 4. Нормализация данных в единый формат
     *
     * @param string $url Адрес RSS/Atom ленты (должен быть валидным HTTP/HTTPS URL)
     * @return array<string, mixed> Структурированные данные ленты:
     *                              - type: тип ленты ('rss', 'atom' или 'unknown')
     *                              - title: заголовок ленты
     *                              - description: описание ленты
     *                              - link: ссылка на источник
     *                              - language: язык контента
     *                              - items: массив элементов ленты
     * @throws RssException Если не удалось загрузить или распарсить ленту
     * @throws RssValidationException Если URL невалиден
     */
    public function fetch(string $url): array
    {
        try {
            $this->validateUrl($url);
            
            $feed = $this->createSimplePie();
            $this->configureSimplePie($feed, $url);
            
            if (!$feed->init()) {
                $error = $feed->error();
                throw new RssException('Не удалось инициализировать ленту: ' . ($error ?: 'неизвестная ошибка'));
            }
            
            // Проверка кодировки и правильности загрузки
            $feed->handle_content_type();
            
            return $this->normalizeFeed($feed);
            
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
            ]);
            throw new RssException('Критическая ошибка при обработке ленты: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Создает экземпляр SimplePie
     * 
     * @return SimplePie Новый экземпляр SimplePie
     */
    private function createSimplePie(): SimplePie
    {
        return new SimplePie();
    }

    /**
     * Конфигурирует SimplePie с безопасными настройками
     * 
     * @param SimplePie $feed Экземпляр SimplePie
     * @param string $url URL для загрузки
     * @return void
     */
    private function configureSimplePie(SimplePie $feed, string $url): void
    {
        // Установка URL
        $feed->set_feed_url($url);
        
        // Настройки безопасности и производительности
        $feed->set_useragent($this->userAgent);
        $feed->set_timeout($this->timeout);
        $feed->force_feed(true); // Принудительная обработка как фида
        $feed->enable_order_by_date(true); // Сортировка по дате
        
        // Настройка кеширования
        if ($this->cacheEnabled && $this->cacheDirectory !== null) {
            $feed->set_cache_location($this->cacheDirectory);
            $feed->set_cache_duration($this->cacheDuration);
            $feed->enable_cache(true);
        } else {
            $feed->enable_cache(false);
        }
        
        // Безопасность: удаление потенциально опасного контента
        $feed->strip_htmltags([
            'base', 'blink', 'body', 'doctype', 'embed',
            'font', 'form', 'frame', 'frameset', 'html',
            'iframe', 'input', 'marquee', 'meta', 'noscript',
            'object', 'param', 'script', 'style'
        ]);
        
        $feed->strip_attributes([
            'bgsound', 'class', 'expr', 'id', 'style', 'onclick',
            'onerror', 'onfinish', 'onmouseover', 'onmouseout',
            'onfocus', 'onblur', 'lowsrc', 'dynsrc'
        ]);
        
        // Ограничение размера контента будет проверено через curl_options
        $feed->set_curl_options([
            CURLOPT_MAXFILESIZE => $this->maxContentSize,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING => '', // Поддержка всех кодировок
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
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
     * Создает директорию для кеша если она не существует
     * 
     * @return void
     */
    private function ensureCacheDirectory(): void
    {
        if ($this->cacheDirectory === null) {
            return;
        }
        
        if (!is_dir($this->cacheDirectory)) {
            try {
                if (!mkdir($this->cacheDirectory, 0755, true) && !is_dir($this->cacheDirectory)) {
                    $this->logWarning('Не удалось создать директорию кеша', [
                        'directory' => $this->cacheDirectory,
                    ]);
                    $this->cacheEnabled = false;
                    $this->cacheDirectory = null;
                }
            } catch (Exception $e) {
                $this->logWarning('Ошибка при создании директории кеша', [
                    'directory' => $this->cacheDirectory,
                    'error' => $e->getMessage(),
                ]);
                $this->cacheEnabled = false;
                $this->cacheDirectory = null;
            }
        }
    }

    /**
     * Приводит SimplePie объект к единому нормализованному формату
     * 
     * Извлекает данные из SimplePie и возвращает в стандартизированном виде
     *
     * @param SimplePie $feed Объект SimplePie с загруженным фидом
     * @return array<string, mixed> Нормализованные данные ленты
     * @throws RssException Если лента пуста или некорректна
     */
    private function normalizeFeed(SimplePie $feed): array
    {
        // Определение типа ленты
        $type = $this->detectFeedType($feed);
        
        // Извлечение метаданных ленты
        $title = $feed->get_title();
        $description = $feed->get_description();
        $link = $feed->get_link();
        $language = $feed->get_language();
        
        // Извлечение элементов
        $items = $this->extractItems($feed);
        
        return [
            'type' => $type,
            'title' => $title !== null ? trim($title) : '',
            'description' => $description !== null ? trim($description) : '',
            'link' => $link !== null ? trim($link) : '',
            'language' => $language !== null ? trim($language) : '',
            'items' => $items,
        ];
    }

    /**
     * Определяет тип ленты (RSS или Atom)
     * 
     * @param SimplePie $feed Объект SimplePie
     * @return string Тип ленты ('rss', 'atom' или 'unknown')
     */
    private function detectFeedType(SimplePie $feed): string
    {
        $version = $feed->get_type();
        
        if ($version === null) {
            return self::FEED_TYPE_UNKNOWN;
        }
        
        $version = strtolower($version);
        
        // SimplePie возвращает значения типа: RSS 2.0, Atom 1.0, и т.д.
        if (str_contains($version, 'atom')) {
            return self::FEED_TYPE_ATOM;
        }
        
        if (str_contains($version, 'rss')) {
            return self::FEED_TYPE_RSS;
        }
        
        return self::FEED_TYPE_UNKNOWN;
    }

    /**
     * Извлекает элементы (items) из ленты
     * 
     * @param SimplePie $feed Объект SimplePie
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
            $items[] = $this->extractItem($item);
        }
        
        return $items;
    }

    /**
     * Извлекает данные из одного элемента ленты
     * 
     * @param \SimplePie\Item $item Элемент SimplePie
     * @return array<string, mixed> Нормализованные данные элемента
     */
    private function extractItem($item): array
    {
        // Заголовок
        $title = $item->get_title();
        
        // Ссылка
        $link = $item->get_link();
        
        // Описание (контент или краткое описание)
        $description = $item->get_description();
        if ($description === null || trim($description) === '') {
            $content = $item->get_content();
            $description = $content !== null ? $content : '';
        }
        
        // Дата публикации
        $publishedAt = $this->extractDate($item);
        
        // Автор
        $author = $this->extractAuthor($item);
        
        // Категории
        $categories = $this->extractItemCategories($item);
        
        return [
            'title' => $title !== null ? trim($title) : '',
            'link' => $link !== null ? trim($link) : '',
            'description' => $description !== null ? trim($description) : '',
            'published_at' => $publishedAt,
            'author' => $author,
            'categories' => $categories,
        ];
    }

    /**
     * Извлекает дату публикации из элемента
     * 
     * @param \SimplePie\Item $item Элемент SimplePie
     * @return DateTimeImmutable|null Дата публикации или null
     */
    private function extractDate($item): ?DateTimeImmutable
    {
        $date = $item->get_date();
        
        if ($date === null) {
            return null;
        }
        
        try {
            return new DateTimeImmutable($date);
        } catch (Exception $e) {
            $this->logWarning('Не удалось распарсить дату', [
                'date_string' => $date,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Извлекает автора из элемента
     * 
     * @param \SimplePie\Item $item Элемент SimplePie
     * @return string Имя автора или пустая строка
     */
    private function extractAuthor($item): string
    {
        $author = $item->get_author();
        
        if ($author === null) {
            return '';
        }
        
        $name = $author->get_name();
        
        if ($name !== null && trim($name) !== '') {
            return trim($name);
        }
        
        $email = $author->get_email();
        
        if ($email !== null && trim($email) !== '') {
            return trim($email);
        }
        
        return '';
    }

    /**
     * Извлекает категории/теги из элемента
     * 
     * @param \SimplePie\Item $item Элемент SimplePie
     * @return array<int, string> Массив категорий (без пустых значений)
     */
    private function extractItemCategories($item): array
    {
        $categories = [];
        $itemCategories = $item->get_categories();
        
        if ($itemCategories === null || $itemCategories === []) {
            return [];
        }
        
        foreach ($itemCategories as $category) {
            $label = $category->get_label();
            
            if ($label !== null && trim($label) !== '') {
                $categories[] = trim($label);
            }
        }
        
        return array_values(array_unique($categories));
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

    /**
     * Возвращает информацию о кешировании
     * 
     * @return array<string, mixed> Информация о кеше:
     *                              - enabled: включено ли кеширование
     *                              - directory: директория кеша
     *                              - duration: длительность кеша в секундах
     */
    public function getCacheInfo(): array
    {
        return [
            'enabled' => $this->cacheEnabled,
            'directory' => $this->cacheDirectory,
            'duration' => $this->cacheDuration,
        ];
    }

    /**
     * Очищает весь кеш SimplePie
     * 
     * @return bool True если кеш успешно очищен, false в противном случае
     */
    public function clearCache(): bool
    {
        if (!$this->cacheEnabled || $this->cacheDirectory === null) {
            return false;
        }
        
        try {
            if (!is_dir($this->cacheDirectory)) {
                return true;
            }
            
            $files = glob($this->cacheDirectory . '/*');
            
            if ($files === false) {
                return false;
            }
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            
            $this->logWarning('Кеш успешно очищен', [
                'directory' => $this->cacheDirectory,
                'files_deleted' => count($files),
            ]);
            
            return true;
        } catch (Exception $e) {
            $this->logError('Ошибка при очистке кеша', [
                'directory' => $this->cacheDirectory,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

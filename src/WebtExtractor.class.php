<?php

declare(strict_types=1);

namespace App\Component;

use App\Component\Exception\WebtExtractorException;
use App\Component\Exception\WebtExtractorValidationException;
use DOMDocument;
use Exception;
use fivefilters\Readability\Readability;
use fivefilters\Readability\Configuration;
use fivefilters\Readability\ParseException;

/**
 * Класс для извлечения основного контента из веб-страниц на базе Readability
 * 
 * Возможности:
 * - Извлечение основного текстового контента из HTML
 * - Автоматическое определение заголовка, автора, даты публикации
 * - Извлечение изображений и ссылок из статьи
 * - Извлечение мета-данных (Open Graph, Twitter Cards, JSON-LD)
 * - Поддержка различных кодировок
 * - Интеграция с HTTP клиентом для загрузки страниц с retry и прокси
 * - Логирование всех операций
 * - Валидация входных данных
 * - Обработка исключений на каждом уровне
 * 
 * Особенности:
 * - Использование производительной библиотеки fivefilters/readability.php
 * - Строгая типизация всех параметров и возвращаемых значений
 * - Автоматическая очистка и санитизация контента
 * - Ограничение размера загружаемого контента
 * - Поддержка работы как с URL, так и с готовым HTML
 */
class WebtExtractor
{
    /**
     * Константы для конфигурации
     */
    private const DEFAULT_TIMEOUT = 30;
    private const DEFAULT_MAX_SIZE = 10485760; // 10 MB в байтах
    private const DEFAULT_USER_AGENT = 'Mozilla/5.0 (compatible; WebtExtractor/2.0; +https://example.com)';
    private const DEFAULT_CONNECT_TIMEOUT = 10;
    private const DEFAULT_RETRIES = 3;
    
    /**
     * Минимальная длина контента для считывания успешным извлечением
     */
    private const MIN_CONTENT_LENGTH = 100;
    
    /**
     * Пользовательский агент для HTTP запросов
     */
    private string $userAgent;
    
    /**
     * Таймаут соединения в секундах
     */
    private int $timeout;
    
    /**
     * Таймаут подключения в секундах
     */
    private int $connectTimeout;
    
    /**
     * Максимальный размер загружаемого контента в байтах
     */
    private int $maxContentSize;
    
    /**
     * Количество повторных попыток при ошибках
     */
    private int $retries;
    
    /**
     * Включить/выключить извлечение изображений
     */
    private bool $extractImages;
    
    /**
     * Включить/выключить извлечение ссылок
     */
    private bool $extractLinks;
    
    /**
     * Включить/выключить извлечение мета-данных
     */
    private bool $extractMetadata;
    
    /**
     * Экземпляр логгера для записи событий и ошибок
     */
    private ?Logger $logger;
    
    /**
     * HTTP клиент для выполнения запросов
     */
    private Http $http;
    
    /**
     * Настройки прокси
     * @var string|array<string, string>|null
     */
    private string|array|null $proxy;
    
    /**
     * Проверка SSL сертификата
     */
    private bool $verifySsl;

    /**
     * Конструктор класса WebtExtractor
     * 
     * @param array<string, mixed> $config Конфигурация экстрактора:
     *                                     - user_agent: строка User-Agent для HTTP запросов
     *                                     - timeout: таймаут соединения в секундах (мин. 1)
     *                                     - connect_timeout: таймаут подключения в секундах (мин. 1)
     *                                     - max_content_size: максимальный размер контента в байтах
     *                                     - retries: количество повторных попыток при ошибках (мин. 1)
     *                                     - extract_images: извлекать изображения (по умолчанию true)
     *                                     - extract_links: извлекать ссылки (по умолчанию true)
     *                                     - extract_metadata: извлекать мета-данные (по умолчанию true)
     *                                     - proxy: настройки прокси (строка или массив)
     *                                     - verify_ssl: проверка SSL сертификата (по умолчанию true)
     * @param Logger|null $logger Инстанс логгера для записи событий
     */
    public function __construct(array $config = [], ?Logger $logger = null)
    {
        $this->userAgent = (string)($config['user_agent'] ?? self::DEFAULT_USER_AGENT);
        $this->timeout = max(1, (int)($config['timeout'] ?? self::DEFAULT_TIMEOUT));
        $this->connectTimeout = max(1, (int)($config['connect_timeout'] ?? self::DEFAULT_CONNECT_TIMEOUT));
        $this->maxContentSize = max(1024, (int)($config['max_content_size'] ?? self::DEFAULT_MAX_SIZE));
        $this->retries = max(1, (int)($config['retries'] ?? self::DEFAULT_RETRIES));
        $this->extractImages = (bool)($config['extract_images'] ?? true);
        $this->extractLinks = (bool)($config['extract_links'] ?? true);
        $this->extractMetadata = (bool)($config['extract_metadata'] ?? true);
        $this->proxy = $config['proxy'] ?? null;
        $this->verifySsl = (bool)($config['verify_ssl'] ?? true);
        $this->logger = $logger;

        // Инициализация HTTP клиента с retry механизмом
        $httpConfig = [
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
            'retries' => $this->retries,
            'verify' => $this->verifySsl,
            'allow_redirects' => [
                'max' => 10,
                'strict' => true,
                'referer' => true,
                'track_redirects' => true,
            ],
            'headers' => [
                'User-Agent' => $this->userAgent,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
                'Accept-Encoding' => 'gzip, deflate',
                'DNT' => '1',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
            ],
        ];

        if ($this->proxy !== null) {
            $httpConfig['proxy'] = $this->proxy;
        }

        $this->http = new Http($httpConfig, $logger);

        $this->logInfo('WebtExtractor инициализирован', [
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
            'max_size' => $this->maxContentSize,
            'retries' => $this->retries,
            'proxy_enabled' => $this->proxy !== null,
        ]);
    }

    /**
     * Извлекает основной контент из веб-страницы по URL
     * 
     * Выполняет полный цикл:
     * 1. Валидация URL
     * 2. Загрузка HTML контента через HTTP клиент
     * 3. Парсинг через Readability
     * 4. Извлечение дополнительных данных (изображения, ссылки, мета-данные)
     * 5. Нормализация результатов
     *
     * @param string $url Адрес веб-страницы (должен быть валидным HTTP/HTTPS URL)
     * @return array<string, mixed> Структурированные данные страницы:
     *                              - url: оригинальный URL
     *                              - title: заголовок статьи
     *                              - author: автор (если найден)
     *                              - content: основной HTML контент
     *                              - text_content: текстовая версия контента
     *                              - excerpt: краткое описание
     *                              - lead_image_url: главное изображение
     *                              - date_published: дата публикации (если найдена)
     *                              - language: язык контента
     *                              - images: массив изображений (если extract_images = true)
     *                              - links: массив ссылок (если extract_links = true)
     *                              - metadata: мета-данные страницы (если extract_metadata = true)
     *                              - word_count: количество слов
     *                              - read_time: примерное время чтения в минутах
     * @throws WebtExtractorValidationException Если URL невалиден
     * @throws WebtExtractorException Если не удалось загрузить или распарсить страницу
     */
    public function extract(string $url): array
    {
        try {
            $this->validateUrl($url);
            
            $this->logInfo('Начало извлечения контента', ['url' => $url]);
            
            // Загружаем HTML контент через HTTP клиент
            $html = $this->download($url);
            $this->validateContentSize($html);
            
            // Извлекаем контент через Readability
            $result = $this->extractFromHtml($html, $url);
            
            $this->logInfo('Контент успешно извлечен', [
                'url' => $url,
                'title' => $result['title'],
                'word_count' => $result['word_count'],
                'images_count' => count($result['images']),
            ]);

            return $result;
            
        } catch (WebtExtractorValidationException $e) {
            $this->logError('Ошибка валидации URL', [
                'url' => $url, 
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            throw $e;
        } catch (WebtExtractorException $e) {
            $this->logError('Ошибка извлечения контента', [
                'url' => $url, 
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            throw $e;
        } catch (Exception $e) {
            $this->logError('Критическая ошибка извлечения контента', [
                'url' => $url, 
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new WebtExtractorException(
                'Критическая ошибка при извлечении контента: ' . $e->getMessage(), 
                0, 
                $e
            );
        }
    }

    /**
     * Извлекает основной контент из готового HTML
     * 
     * Полезно когда HTML уже загружен или получен из другого источника
     *
     * @param string $html HTML контент для парсинга
     * @param string $url URL страницы (используется для резолва относительных ссылок)
     * @return array<string, mixed> Структурированные данные страницы (см. extract())
     * @throws WebtExtractorValidationException Если HTML пуст или URL невалиден
     * @throws WebtExtractorException Если не удалось распарсить HTML
     */
    public function extractFromHtml(string $html, string $url): array
    {
        try {
            $this->validateHtml($html);
            $this->validateUrl($url);
            
            $this->logDebug('Начало парсинга HTML', [
                'url' => $url,
                'html_size' => strlen($html),
            ]);
            
            // Создаем конфигурацию Readability
            $configuration = $this->createReadabilityConfiguration();
            
            // Парсим HTML через Readability
            $readability = new Readability($configuration);
            $readability->parse($html, $url);
            
            // Проверяем успешность парсинга
            if (!$readability->getContent()) {
                throw new WebtExtractorException('Не удалось извлечь контент: страница не содержит читаемого текста');
            }
            
            // Формируем базовый результат из Readability
            $result = $this->normalizeReadabilityResult($readability, $url);
            
            // Извлекаем дополнительные данные если требуется
            $dom = $this->createDomDocument($html);
            
            if ($this->extractImages) {
                $result['images'] = $this->extractImagesFromDom($dom, $url);
            }
            
            if ($this->extractLinks) {
                $result['links'] = $this->extractLinksFromDom($dom, $url);
            }
            
            if ($this->extractMetadata) {
                $result['metadata'] = $this->extractMetadataFromDom($dom, $url);
            }
            
            return $result;
            
        } catch (ParseException $e) {
            $this->logError('Ошибка парсинга через Readability', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            throw new WebtExtractorException('Ошибка парсинга контента: ' . $e->getMessage(), 0, $e);
        } catch (WebtExtractorValidationException $e) {
            $this->logError('Ошибка валидации параметров', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } catch (WebtExtractorException $e) {
            $this->logError('Ошибка извлечения контента', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } catch (Exception $e) {
            $this->logError('Критическая ошибка парсинга HTML', [
                'url' => $url,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
            throw new WebtExtractorException(
                'Критическая ошибка при парсинге HTML: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Пакетное извлечение контента из нескольких URL
     * 
     * Удобно для обработки списка ссылок из RSS ленты
     *
     * @param array<int, string> $urls Массив URL для обработки
     * @param bool $continueOnError Продолжать обработку при ошибках (по умолчанию true)
     * @return array<string, array<string, mixed>|array<string, string>> Ассоциативный массив URL => результат:
     *                              - при успехе: массив с извлеченными данными
     *                              - при ошибке: ['error' => 'сообщение об ошибке']
     */
    public function extractBatch(array $urls, bool $continueOnError = true): array
    {
        $results = [];
        $totalUrls = count($urls);
        
        $this->logInfo('Начало пакетного извлечения контента', [
            'urls_count' => $totalUrls,
            'continue_on_error' => $continueOnError,
        ]);
        
        foreach ($urls as $index => $url) {
            try {
                $this->logDebug('Обработка URL', [
                    'index' => $index + 1,
                    'total' => $totalUrls,
                    'url' => $url,
                ]);
                
                $results[$url] = $this->extract($url);
                
            } catch (Exception $e) {
                $errorMessage = sprintf('[%s] %s', get_class($e), $e->getMessage());
                $results[$url] = ['error' => $errorMessage];
                
                $this->logWarning('Ошибка при пакетной обработке URL', [
                    'url' => $url,
                    'error' => $errorMessage,
                ]);
                
                if (!$continueOnError) {
                    $this->logError('Прерывание пакетной обработки из-за ошибки', [
                        'processed' => $index + 1,
                        'total' => $totalUrls,
                    ]);
                    break;
                }
            }
        }
        
        $successCount = count(array_filter($results, fn($result) => !isset($result['error'])));
        $errorCount = $totalUrls - $successCount;
        
        $this->logInfo('Пакетное извлечение завершено', [
            'total' => $totalUrls,
            'success' => $successCount,
            'errors' => $errorCount,
        ]);
        
        return $results;
    }

    /**
     * Создает конфигурацию для Readability
     * 
     * @return Configuration Конфигурация Readability
     */
    private function createReadabilityConfiguration(): Configuration
    {
        $configuration = new Configuration();
        
        // Настройки парсинга
        $configuration->setFixRelativeURLs(true);
        $configuration->setSubstituteEntities(true);
        $configuration->setSummonCthulhu(false); // Отключаем агрессивную очистку
        
        return $configuration;
    }

    /**
     * Нормализует результат Readability в единый формат
     * 
     * @param Readability $readability Экземпляр Readability с распарсенными данными
     * @param string $url Оригинальный URL
     * @return array<string, mixed> Нормализованные данные
     */
    private function normalizeReadabilityResult(Readability $readability, string $url): array
    {
        $content = $readability->getContent() ?? '';
        $textContent = $this->stripTags($content);
        $wordCount = $this->countWords($textContent);
        
        return [
            'url' => $url,
            'title' => $this->safeGetString($readability->getTitle()),
            'author' => $this->safeGetString($readability->getAuthor()),
            'content' => $content,
            'text_content' => $textContent,
            'excerpt' => $this->safeGetString($readability->getExcerpt()),
            'lead_image_url' => $this->safeGetString($readability->getImage()),
            'date_published' => null, // Readability не предоставляет дату
            'language' => $this->detectLanguage($textContent),
            'images' => [], // Заполняется позже если требуется
            'links' => [], // Заполняется позже если требуется
            'metadata' => [
                'open_graph' => [],
                'twitter_card' => [],
                'meta' => [],
                'json_ld' => [],
            ], // Заполняется позже если требуется
            'word_count' => $wordCount,
            'read_time' => $this->calculateReadTime($wordCount),
            'extracted_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Создает DOMDocument из HTML
     * 
     * @param string $html HTML контент
     * @return DOMDocument Экземпляр DOMDocument
     * @throws WebtExtractorException Если не удалось создать DOMDocument
     */
    private function createDomDocument(string $html): DOMDocument
    {
        try {
            $dom = new DOMDocument('1.0', 'UTF-8');
            
            // Подавляем ошибки парсинга HTML
            $previousValue = libxml_use_internal_errors(true);
            
            // Пытаемся загрузить HTML
            $loaded = @$dom->loadHTML(
                mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'),
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOWARNING | LIBXML_NOERROR
            );
            
            libxml_clear_errors();
            libxml_use_internal_errors($previousValue);
            
            if (!$loaded) {
                throw new WebtExtractorException('Не удалось создать DOMDocument из HTML');
            }
            
            return $dom;
            
        } catch (Exception $e) {
            throw new WebtExtractorException('Ошибка создания DOMDocument: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Извлекает все изображения из HTML
     * 
     * @param DOMDocument $dom DOMDocument с HTML
     * @param string $baseUrl Базовый URL для резолва относительных путей
     * @return array<int, array<string, string>> Массив изображений с атрибутами
     */
    private function extractImagesFromDom(DOMDocument $dom, string $baseUrl): array
    {
        $images = [];
        
        try {
            $imgTags = $dom->getElementsByTagName('img');
            
            foreach ($imgTags as $img) {
                $src = $img->getAttribute('src');
                
                if (trim($src) === '') {
                    continue;
                }
                
                // Резолвим относительные URL
                $absoluteSrc = $this->resolveUrl($src, $baseUrl);
                
                $images[] = [
                    'src' => $absoluteSrc,
                    'alt' => $img->getAttribute('alt') ?: '',
                    'title' => $img->getAttribute('title') ?: '',
                    'width' => $img->getAttribute('width') ?: '',
                    'height' => $img->getAttribute('height') ?: '',
                ];
            }
            
        } catch (Exception $e) {
            $this->logWarning('Ошибка извлечения изображений', [
                'error' => $e->getMessage(),
            ]);
        }
        
        return $images;
    }

    /**
     * Извлекает все ссылки из HTML
     * 
     * @param DOMDocument $dom DOMDocument с HTML
     * @param string $baseUrl Базовый URL для резолва относительных путей
     * @return array<int, array<string, string>> Массив ссылок с атрибутами
     */
    private function extractLinksFromDom(DOMDocument $dom, string $baseUrl): array
    {
        $links = [];
        
        try {
            $aTags = $dom->getElementsByTagName('a');
            
            foreach ($aTags as $a) {
                $href = $a->getAttribute('href');
                
                if (trim($href) === '' || str_starts_with($href, '#') || str_starts_with($href, 'javascript:')) {
                    continue;
                }
                
                // Резолвим относительные URL
                $absoluteHref = $this->resolveUrl($href, $baseUrl);
                
                $links[] = [
                    'href' => $absoluteHref,
                    'text' => trim($a->textContent) ?: '',
                    'title' => $a->getAttribute('title') ?: '',
                    'rel' => $a->getAttribute('rel') ?: '',
                ];
            }
            
        } catch (Exception $e) {
            $this->logWarning('Ошибка извлечения ссылок', [
                'error' => $e->getMessage(),
            ]);
        }
        
        return $links;
    }

    /**
     * Извлекает мета-данные из HTML (Open Graph, Twitter Cards, JSON-LD)
     * 
     * @param DOMDocument $dom DOMDocument с HTML
     * @param string $url URL страницы
     * @return array<string, mixed> Массив мета-данных
     */
    private function extractMetadataFromDom(DOMDocument $dom, string $url): array
    {
        $metadata = [
            'open_graph' => [],
            'twitter_card' => [],
            'meta' => [],
            'json_ld' => [],
        ];
        
        try {
            // Извлекаем Open Graph и Twitter Cards из meta тегов
            $metaTags = $dom->getElementsByTagName('meta');
            
            foreach ($metaTags as $meta) {
                $property = $meta->getAttribute('property');
                $name = $meta->getAttribute('name');
                $content = $meta->getAttribute('content');
                
                if (str_starts_with($property, 'og:')) {
                    $key = substr($property, 3);
                    $metadata['open_graph'][$key] = $content;
                } elseif (str_starts_with($name, 'twitter:')) {
                    $key = substr($name, 8);
                    $metadata['twitter_card'][$key] = $content;
                } elseif ($name !== '' && $content !== '') {
                    $metadata['meta'][$name] = $content;
                }
            }
            
            // Извлекаем JSON-LD из script тегов
            $scriptTags = $dom->getElementsByTagName('script');
            
            foreach ($scriptTags as $script) {
                $type = $script->getAttribute('type');
                
                if ($type === 'application/ld+json') {
                    try {
                        $jsonContent = $script->textContent;
                        if (trim($jsonContent) !== '') {
                            $decoded = json_decode($jsonContent, true, 512, JSON_THROW_ON_ERROR);
                            if (is_array($decoded)) {
                                $metadata['json_ld'][] = $decoded;
                            }
                        }
                    } catch (Exception $e) {
                        $this->logDebug('Ошибка парсинга JSON-LD', [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
            
        } catch (Exception $e) {
            $this->logWarning('Ошибка извлечения мета-данных', [
                'error' => $e->getMessage(),
            ]);
        }
        
        return $metadata;
    }

    /**
     * Резолвит относительный URL в абсолютный
     * 
     * @param string $url URL для резолва
     * @param string $baseUrl Базовый URL
     * @return string Абсолютный URL
     */
    private function resolveUrl(string $url, string $baseUrl): string
    {
        // Если URL уже абсолютный, возвращаем его
        if (parse_url($url, PHP_URL_SCHEME) !== null) {
            return $url;
        }
        
        // Парсим базовый URL
        $base = parse_url($baseUrl);
        
        if ($base === false || !isset($base['scheme']) || !isset($base['host'])) {
            return $url;
        }
        
        // URL начинается с //
        if (str_starts_with($url, '//')) {
            return $base['scheme'] . ':' . $url;
        }
        
        // URL начинается с /
        if (str_starts_with($url, '/')) {
            return $base['scheme'] . '://' . $base['host'] . $url;
        }
        
        // Относительный URL
        $path = $base['path'] ?? '/';
        $lastSlash = strrpos($path, '/');
        
        if ($lastSlash !== false) {
            $path = substr($path, 0, $lastSlash + 1);
        }
        
        return $base['scheme'] . '://' . $base['host'] . $path . $url;
    }

    /**
     * Определяет язык текста (упрощенная версия)
     * 
     * @param string $text Текст для анализа
     * @return string Код языка (ISO 639-1) или 'unknown'
     */
    private function detectLanguage(string $text): string
    {
        // Упрощенное определение языка по наличию кириллицы
        if (preg_match('/[а-яА-ЯЁё]/u', $text)) {
            return 'ru';
        }
        
        // Латиница - предполагаем английский
        if (preg_match('/[a-zA-Z]/', $text)) {
            return 'en';
        }
        
        return 'unknown';
    }

    /**
     * Подсчитывает количество слов в тексте
     * 
     * @param string $text Текст для подсчета
     * @return int Количество слов
     */
    private function countWords(string $text): int
    {
        $text = trim($text);
        
        if ($text === '') {
            return 0;
        }
        
        // Используем регулярное выражение для поддержки кириллицы
        return count(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY));
    }

    /**
     * Рассчитывает примерное время чтения в минутах
     * 
     * Средняя скорость чтения: 200-250 слов в минуту
     * Используем 225 слов/минуту как среднее значение
     * 
     * @param int $wordCount Количество слов
     * @return int Время чтения в минутах (минимум 1)
     */
    private function calculateReadTime(int $wordCount): int
    {
        if ($wordCount === 0) {
            return 1;
        }
        
        return max(1, (int)ceil($wordCount / 225));
    }

    /**
     * Удаляет HTML теги из текста
     * 
     * @param string $html HTML контент
     * @return string Текст без тегов
     */
    private function stripTags(string $html): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text ?? '');
    }

    /**
     * Безопасно получает строковое значение
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
     * Валидирует URL перед загрузкой
     * 
     * Проверяет:
     * - Корректность формата URL
     * - Наличие протокола (http/https)
     * - Наличие хоста
     * 
     * @param string $url URL для валидации
     * @throws WebtExtractorValidationException Если URL невалиден
     */
    private function validateUrl(string $url): void
    {
        if (trim($url) === '') {
            throw new WebtExtractorValidationException('URL не может быть пустым');
        }
        
        $parsedUrl = parse_url($url);
        
        if ($parsedUrl === false) {
            throw new WebtExtractorValidationException('Некорректный формат URL');
        }
        
        $scheme = $parsedUrl['scheme'] ?? '';
        if (!in_array(strtolower($scheme), ['http', 'https'], true)) {
            throw new WebtExtractorValidationException('URL должен использовать протокол HTTP или HTTPS');
        }
        
        if (!isset($parsedUrl['host']) || trim($parsedUrl['host']) === '') {
            throw new WebtExtractorValidationException('URL должен содержать имя хоста');
        }
    }

    /**
     * Валидирует HTML контент
     * 
     * @param string $html HTML для валидации
     * @throws WebtExtractorValidationException Если HTML невалиден
     */
    private function validateHtml(string $html): void
    {
        if (trim($html) === '') {
            throw new WebtExtractorValidationException('HTML контент не может быть пустым');
        }
        
        if (strlen($html) < self::MIN_CONTENT_LENGTH) {
            throw new WebtExtractorValidationException(
                sprintf(
                    'HTML контент слишком короткий (минимум %d байт)',
                    self::MIN_CONTENT_LENGTH
                )
            );
        }
    }

    /**
     * Проверяет размер загруженного контента
     * 
     * @param string $content Контент для проверки
     * @throws WebtExtractorException Если размер контента превышает лимит
     */
    private function validateContentSize(string $content): void
    {
        $contentSize = strlen($content);
        
        if ($contentSize > $this->maxContentSize) {
            throw new WebtExtractorException(sprintf(
                'Размер контента (%d байт) превышает максимально допустимый (%d байт)',
                $contentSize,
                $this->maxContentSize
            ));
        }
    }

    /**
     * Выполняет HTTP-запрос для получения HTML страницы
     * 
     * @param string $url Адрес страницы
     * @return string HTML контент
     * @throws WebtExtractorException Если запрос завершился с ошибкой
     */
    private function download(string $url): string
    {
        try {
            $response = $this->http->request('GET', $url);
            $statusCode = $response->getStatusCode();
            
            if ($statusCode < 200 || $statusCode >= 400) {
                $this->logError('HTTP ошибка при загрузке страницы', [
                    'url' => $url, 
                    'status_code' => $statusCode,
                ]);
                throw new WebtExtractorException("Сервер вернул код ошибки: {$statusCode}");
            }

            $body = (string)$response->getBody();
            
            if ($body === '') {
                throw new WebtExtractorException('Сервер вернул пустой ответ');
            }

            return $body;
            
        } catch (Exception $e) {
            if ($e instanceof WebtExtractorException) {
                throw $e;
            }
            throw new WebtExtractorException('Ошибка загрузки контента: ' . $e->getMessage(), 0, $e);
        }
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
            $this->logger->info($message, $context);
        }
    }

    /**
     * Записывает отладочное сообщение в лог
     *
     * @param string $message Текст сообщения
     * @param array<string, mixed> $context Дополнительные данные
     */
    private function logDebug(string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->debug($message, $context);
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
            $this->logger->warning($message, $context);
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
            $this->logger->error($message, $context);
        }
    }
}

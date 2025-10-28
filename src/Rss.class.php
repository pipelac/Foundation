<?php

declare(strict_types=1);

namespace App\Component;

use App\Component\Exception\RssException;
use App\Component\Exception\RssValidationException;
use DateTimeImmutable;
use Exception;
use SimpleXMLElement;

/**
 * Класс для безопасной загрузки и парсинга RSS/Atom лент с защитой от XML-атак
 * 
 * Поддерживаемые форматы:
 * - RSS 2.0
 * - Atom 1.0
 * 
 * Особенности:
 * - Строгая типизация всех параметров и возвращаемых значений
 * - Защита от XXE (XML External Entity) атак
 * - Валидация URL перед загрузкой
 * - Ограничение размера загружаемого контента
 * - Логирование всех критических операций
 * - Обработка исключений на каждом уровне
 */
class Rss
{
    /**
     * Константы типов RSS лент
     */
    private const FEED_TYPE_RSS = 'rss';
    private const FEED_TYPE_ATOM = 'atom';
    
    /**
     * Константы для конфигурации
     */
    private const DEFAULT_TIMEOUT = 10;
    private const DEFAULT_MAX_SIZE = 10485760; // 10 MB в байтах
    private const DEFAULT_USER_AGENT = 'RSSClient/1.0 (+https://example.com)';
    
    /**
     * Константы для XML парсинга (защита от XXE атак)
     */
    private const LIBXML_OPTIONS = LIBXML_NOCDATA | LIBXML_NOENT | LIBXML_NONET;
    
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
     * @param Logger|null $logger Инстанс логгера для записи событий
     */
    public function __construct(array $config = [], ?Logger $logger = null)
    {
        $this->userAgent = (string)($config['user_agent'] ?? self::DEFAULT_USER_AGENT);
        $this->timeout = max(1, (int)($config['timeout'] ?? self::DEFAULT_TIMEOUT));
        $this->maxContentSize = max(1024, (int)($config['max_content_size'] ?? self::DEFAULT_MAX_SIZE));
        $this->logger = $logger;

        $this->http = new Http([
            'timeout' => $this->timeout,
            'connect_timeout' => $this->timeout,
            'allow_redirects' => true,
            'headers' => [
                'User-Agent' => $this->userAgent,
            ],
        ], $logger);
    }

    /**
     * Загружает и парсит RSS/Atom ленту по указанному URL
     * 
     * Выполняет полный цикл:
     * 1. Валидация URL
     * 2. Загрузка контента через HTTP
     * 3. Проверка размера контента
     * 4. Парсинг XML с защитой от атак
     * 5. Нормализация данных в единый формат
     *
     * @param string $url Адрес RSS/Atom ленты (должен быть валидным HTTP/HTTPS URL)
     * @return array<string, mixed> Структурированные данные ленты:
     *                              - type: тип ленты ('rss' или 'atom')
     *                              - title: заголовок ленты
     *                              - description: описание ленты
     *                              - link: ссылка на источник
     *                              - language: язык контента
     *                              - items: массив элементов ленты
     * @throws RssException Если не удалось загрузить или распарсить ленту
     * @throws Exception Если произошла критическая ошибка
     */
    public function fetch(string $url): array
    {
        try {
            $this->validateUrl($url);
            
            $xmlContent = $this->download($url);
            $this->validateContentSize($xmlContent);
            
            $document = $this->loadXml($xmlContent);

            return $this->normalizeFeed($document);
        } catch (RuntimeException $e) {
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
        $response = $this->http->request('GET', $url);

        $statusCode = $response->getStatusCode();
        
        if ($statusCode < 200 || $statusCode >= 400) {
            $this->logError('HTTP ошибка при загрузке ленты', [
                'url' => $url, 
                'status_code' => $statusCode,
            ]);
            throw new RssException('Сервер вернул код ошибки: ' . $statusCode);
        }

        $body = (string)$response->getBody();
        
        if ($body === '') {
            throw new RssException('Сервер вернул пустой ответ');
        }

        return $body;
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
     * Загружает и парсит XML-документ из строки с защитой от XXE атак
     * 
     * Использует безопасные настройки libxml:
     * - LIBXML_NOCDATA: конвертирует CDATA в текстовые узлы
     * - LIBXML_NOENT: запрещает подстановку сущностей
     * - LIBXML_NONET: запрещает сетевой доступ при загрузке документа
     *
     * @param string $xml XML строка для парсинга
     * @return SimpleXMLElement Объект распарсенного XML документа
     * @throws RssException Если XML невалиден или содержит ошибки
     */
    private function loadXml(string $xml): SimpleXMLElement
    {
        // Включаем внутреннюю обработку ошибок libxml
        $useInternalErrors = libxml_use_internal_errors(true);
        
        // Очищаем предыдущие ошибки
        libxml_clear_errors();
        
        try {
            $document = simplexml_load_string($xml, SimpleXMLElement::class, self::LIBXML_OPTIONS);

            if ($document === false) {
                $errors = $this->formatLibxmlErrors();
                throw new RssException('Ошибка парсинга XML: ' . $errors);
            }

            return $document;
        } finally {
            // Очищаем ошибки и восстанавливаем предыдущее состояние
            libxml_clear_errors();
            libxml_use_internal_errors($useInternalErrors);
        }
    }

    /**
     * Форматирует ошибки libxml в читаемую строку
     * 
     * @return string Отформатированные ошибки через точку с запятой
     */
    private function formatLibxmlErrors(): string
    {
        $errors = libxml_get_errors();
        
        if ($errors === []) {
            return 'Неизвестная ошибка парсинга XML';
        }
        
        $errorMessages = array_map(
            static fn($error): string => trim($error->message),
            $errors
        );
        
        return implode('; ', $errorMessages);
    }

    /**
     * Приводит RSS или Atom документ к единому нормализованному формату
     * 
     * Автоматически определяет тип ленты и вызывает соответствующий парсер
     *
     * @param SimpleXMLElement $document Распарсенный XML документ
     * @return array<string, mixed> Нормализованные данные ленты
     * @throws RssException Если формат ленты не распознан
     */
    private function normalizeFeed(SimpleXMLElement $document): array
    {
        // Проверяем формат RSS 2.0
        if (isset($document->channel)) {
            return $this->parseRss($document);
        }

        // Проверяем формат Atom
        if ($document->getName() === 'feed') {
            return $this->parseAtom($document);
        }

        throw new RssException('Неизвестный формат ленты. Поддерживаются только RSS 2.0 и Atom 1.0');
    }

    /**
     * Парсит RSS 2.0 документ в нормализованный формат
     * 
     * Извлекает метаданные канала и все элементы (items)
     *
     * @param SimpleXMLElement $document XML документ RSS 2.0
     * @return array<string, mixed> Данные ленты в нормализованном формате
     */
    private function parseRss(SimpleXMLElement $document): array
    {
        $channel = $document->channel;
        
        if ($channel === null) {
            throw new RssException('RSS документ не содержит элемент channel');
        }

        return [
            'type' => self::FEED_TYPE_RSS,
            'title' => $this->extractText($channel->title),
            'description' => $this->extractText($channel->description),
            'link' => $this->extractText($channel->link),
            'language' => $this->extractText($channel->language),
            'items' => $this->parseRssItems($channel),
        ];
    }

    /**
     * Парсит элементы (items) RSS ленты
     * 
     * @param SimpleXMLElement $channel Элемент channel RSS документа
     * @return array<int, array<string, mixed>> Массив элементов ленты
     */
    private function parseRssItems(SimpleXMLElement $channel): array
    {
        $items = [];

        foreach ($channel->item as $item) {
            $items[] = [
                'title' => $this->extractText($item->title),
                'link' => $this->extractText($item->link),
                'description' => $this->extractText($item->description),
                'published_at' => $this->parseDate($this->extractText($item->pubDate)),
                'author' => $this->extractText($item->author),
                'categories' => $this->extractCategories($item),
            ];
        }

        return $items;
    }

    /**
     * Парсит Atom 1.0 документ в нормализованный формат
     * 
     * Извлекает метаданные feed и все записи (entries)
     *
     * @param SimpleXMLElement $document XML документ Atom 1.0
     * @return array<string, mixed> Данные ленты в нормализованном формате
     */
    private function parseAtom(SimpleXMLElement $document): array
    {
        return [
            'type' => self::FEED_TYPE_ATOM,
            'title' => $this->extractText($document->title),
            'description' => $this->extractText($document->subtitle),
            'link' => $this->extractAtomLink($document),
            'language' => $this->extractText($document->{'xml:lang'}),
            'items' => $this->parseAtomEntries($document),
        ];
    }

    /**
     * Парсит записи (entries) Atom ленты
     * 
     * @param SimpleXMLElement $document Atom документ
     * @return array<int, array<string, mixed>> Массив записей ленты
     */
    private function parseAtomEntries(SimpleXMLElement $document): array
    {
        $items = [];

        foreach ($document->entry as $entry) {
            $items[] = [
                'title' => $this->extractText($entry->title),
                'link' => $this->extractAtomLink($entry),
                'description' => $this->extractAtomContent($entry),
                'published_at' => $this->parseAtomDate($entry),
                'author' => $this->extractText($entry->author->name),
                'categories' => $this->extractCategories($entry),
            ];
        }

        return $items;
    }

    /**
     * Извлекает ссылку из Atom элемента
     * 
     * Atom использует элемент <link> с атрибутом href.
     * Если есть несколько ссылок, возвращает первую с rel="alternate" или просто первую
     *
     * @param SimpleXMLElement $element Atom элемент (feed или entry)
     * @return string URL ссылки или пустая строка, если ссылка не найдена
     */
    private function extractAtomLink(SimpleXMLElement $element): string
    {
        if (!isset($element->link)) {
            return '';
        }
        
        // Если только один элемент link
        if (count($element->link) === 1) {
            $href = (string)($element->link['href'] ?? '');
            return $href !== '' ? $href : (string)$element->link;
        }
        
        // Если несколько элементов link, ищем rel="alternate"
        foreach ($element->link as $link) {
            $rel = (string)($link['rel'] ?? '');
            if ($rel === '' || $rel === 'alternate') {
                return (string)($link['href'] ?? '');
            }
        }
        
        // Возвращаем первый найденный href
        return (string)($element->link[0]['href'] ?? '');
    }

    /**
     * Извлекает контент из Atom элемента
     * 
     * Atom может содержать как summary (краткое описание), так и content (полный контент)
     * 
     * @param SimpleXMLElement $entry Atom entry элемент
     * @return string Текст контента или пустая строка
     */
    private function extractAtomContent(SimpleXMLElement $entry): string
    {
        if (isset($entry->content)) {
            return $this->extractText($entry->content);
        }
        
        if (isset($entry->summary)) {
            return $this->extractText($entry->summary);
        }
        
        return '';
    }

    /**
     * Извлекает дату публикации из Atom элемента
     * 
     * Atom использует элементы updated или published для дат
     * 
     * @param SimpleXMLElement $entry Atom entry элемент
     * @return DateTimeImmutable|null Дата публикации или null
     */
    private function parseAtomDate(SimpleXMLElement $entry): ?DateTimeImmutable
    {
        if (isset($entry->published)) {
            return $this->parseDate($this->extractText($entry->published));
        }
        
        if (isset($entry->updated)) {
            return $this->parseDate($this->extractText($entry->updated));
        }
        
        return null;
    }

    /**
     * Извлекает категории/теги из элемента RSS/Atom
     * 
     * Поддерживает как RSS категории, так и Atom теги
     *
     * @param SimpleXMLElement $element Элемент XML (item или entry)
     * @return array<int, string> Массив категорий (без пустых значений)
     */
    private function extractCategories(SimpleXMLElement $element): array
    {
        if (!isset($element->category)) {
            return [];
        }
        
        $categories = [];

        foreach ($element->category as $category) {
            $categoryText = $this->extractText($category);
            
            if ($categoryText !== '') {
                $categories[] = $categoryText;
            }
        }

        return $categories;
    }

    /**
     * Извлекает текстовое содержимое из XML элемента
     * 
     * Безопасно обрабатывает null значения и приводит к строке
     * 
     * @param SimpleXMLElement|null $element XML элемент
     * @return string Текстовое содержимое или пустая строка
     */
    private function extractText(?SimpleXMLElement $element): string
    {
        if ($element === null) {
            return '';
        }
        
        return trim((string)$element);
    }

    /**
     * Парсит строку даты и создает объект DateTimeImmutable
     * 
     * Поддерживает различные форматы дат (RFC 2822, ISO 8601 и др.)
     * При ошибке парсинга логирует предупреждение и возвращает null
     *
     * @param string $dateString Строка с датой
     * @return DateTimeImmutable|null Объект даты или null при ошибке
     */
    private function parseDate(string $dateString): ?DateTimeImmutable
    {
        if ($dateString === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($dateString);
        } catch (Exception $e) {
            $this->logWarning('Не удалось распарсить дату', [
                'date_string' => $dateString,
                'error' => $e->getMessage(),
            ]);
            return null;
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

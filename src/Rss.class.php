<?php

declare(strict_types=1);

namespace App\Component;

use DateTimeImmutable;
use Exception;
use SimpleXMLElement;
use RuntimeException;

/**
 * Класс для загрузки и парсинга RSS/Atom лент
 */
class Rss
{
    private const DEFAULT_TIMEOUT = 10;

    private string $userAgent;
    private int $timeout;
    private ?Logger $logger;

    /**
     * @param array<string, mixed> $config Конфигурация загрузчика
     * @param Logger|null $logger Инстанс логгера
     */
    public function __construct(array $config = [], ?Logger $logger = null)
    {
        $this->userAgent = (string)($config['user_agent'] ?? 'RSSClient/1.0 (+https://example.com)');
        $this->timeout = max(1, (int)($config['timeout'] ?? self::DEFAULT_TIMEOUT));
        $this->logger = $logger;
    }

    /**
     * Загружает и парсит ленту по указанному URL
     *
     * @param string $url Адрес RSS/Atom ленты
     * @return array<string, mixed> Структурированные данные ленты
     * @throws Exception Если не удалось загрузить или распарсить ленту
     */
    public function fetch(string $url): array
    {
        try {
            $xmlContent = $this->download($url);
            $document = $this->loadXml($xmlContent);

            return $this->normalizeFeed($document);
        } catch (Exception $e) {
            $this->logError('Ошибка загрузки ленты', ['url' => $url, 'exception' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Выполняет HTTP-запрос для получения содержимого ленты
     *
     * @param string $url Адрес ленты
     * @return string Содержимое XML
     * @throws RuntimeException Если запрос завершился с ошибкой
     */
    private function download(string $url): string
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Не удалось инициализировать запрос cURL.');
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => $this->userAgent,
        ]);

        $response = curl_exec($handle);
        $error = curl_error($handle);
        $statusCode = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($response === false) {
            throw new RuntimeException('Ошибка запроса: ' . $error);
        }

        if ($statusCode >= 400) {
            throw new RuntimeException('Сервер вернул код ошибки: ' . $statusCode);
        }

        return $response;
    }

    /**
     * Загружает XML-документ из строки
     *
     * @param string $xml XML строка
     * @return SimpleXMLElement Объект XML
     * @throws RuntimeException Если XML невалиден
     */
    private function loadXml(string $xml): SimpleXMLElement
    {
        libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA);

        if ($document === false) {
            $errors = array_map(static fn($error) => $error->message, libxml_get_errors());
            libxml_clear_errors();
            throw new RuntimeException('Ошибка парсинга XML: ' . implode('; ', $errors));
        }

        return $document;
    }

    /**
     * Приводит RSS или Atom документ к единому виду
     *
     * @param SimpleXMLElement $document XML документ
     * @return array<string, mixed> Данные ленты
     */
    private function normalizeFeed(SimpleXMLElement $document): array
    {
        if (isset($document->channel)) {
            return $this->parseRss($document);
        }

        if ($document->getName() === 'feed') {
            return $this->parseAtom($document);
        }

        throw new RuntimeException('Неизвестный формат RSS/Atom ленты.');
    }

    /**
     * Парсит RSS документ
     *
     * @param SimpleXMLElement $document XML документ RSS
     * @return array<string, mixed> Данные ленты
     */
    private function parseRss(SimpleXMLElement $document): array
    {
        $channel = $document->channel;
        $items = [];

        foreach ($channel->item as $item) {
            $items[] = [
                'title' => (string)($item->title ?? ''),
                'link' => (string)($item->link ?? ''),
                'description' => (string)($item->description ?? ''),
                'published_at' => $this->createDate((string)($item->pubDate ?? '')),
                'author' => (string)($item->author ?? ''),
                'categories' => $this->extractCategories($item),
            ];
        }

        return [
            'type' => 'rss',
            'title' => (string)($channel->title ?? ''),
            'description' => (string)($channel->description ?? ''),
            'link' => (string)($channel->link ?? ''),
            'language' => (string)($channel->language ?? ''),
            'items' => $items,
        ];
    }

    /**
     * Парсит Atom документ
     *
     * @param SimpleXMLElement $document XML документ Atom
     * @return array<string, mixed> Данные ленты
     */
    private function parseAtom(SimpleXMLElement $document): array
    {
        $items = [];

        foreach ($document->entry as $entry) {
            $items[] = [
                'title' => (string)($entry->title ?? ''),
                'link' => $this->extractAtomLink($entry),
                'description' => (string)($entry->summary ?? $entry->content ?? ''),
                'published_at' => $this->createDate((string)($entry->updated ?? $entry->published ?? '')),
                'author' => (string)($entry->author->name ?? ''),
                'categories' => $this->extractCategories($entry),
            ];
        }

        return [
            'type' => 'atom',
            'title' => (string)($document->title ?? ''),
            'description' => (string)($document->subtitle ?? ''),
            'link' => $this->extractAtomLink($document),
            'language' => (string)($document->{'xml:lang'} ?? ''),
            'items' => $items,
        ];
    }

    /**
     * Извлекает ссылку из Atom записи
     *
     * @param SimpleXMLElement $element Элемент Atom
     */
    private function extractAtomLink(SimpleXMLElement $element): string
    {
        $link = $element->link;

        if ($link === null) {
            return '';
        }

        if (isset($link['href'])) {
            return (string)$link['href'];
        }

        return (string)$link;
    }

    /**
     * Извлекает категории из элемента RSS/Atom
     *
     * @param SimpleXMLElement $element Элемент XML
     * @return array<int, string> Список категорий
     */
    private function extractCategories(SimpleXMLElement $element): array
    {
        $categories = [];

        foreach ($element->category ?? [] as $category) {
            $categories[] = (string)$category;
        }

        return $categories;
    }

    /**
     * Создает объект даты из строки
     *
     * @param string $value Исходная строка
     * @return DateTimeImmutable|null Объект даты или null
     */
    private function createDate(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Записывает ошибку в лог при наличии логгера
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

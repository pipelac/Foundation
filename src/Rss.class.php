<?php

declare(strict_types=1);

namespace App\Component;

use DateTimeImmutable;
use Exception;
use SimplePie\SimplePie;
use RuntimeException;

/**
 * Класс для загрузки и парсинга RSS/Atom лент с использованием SimplePie
 */
class Rss
{
    private const DEFAULT_TIMEOUT = 10;

    private string $userAgent;
    private int $timeout;
    private ?Logger $logger;
    private ?string $cacheDir;
    private int $cacheDuration;

    /**
     * @param array<string, mixed> $config Конфигурация загрузчика
     * @param Logger|null $logger Инстанс логгера
     */
    public function __construct(array $config = [], ?Logger $logger = null)
    {
        $this->userAgent = (string)($config['user_agent'] ?? 'RSSClient/1.0 (+https://example.com)');
        $this->timeout = max(1, (int)($config['timeout'] ?? self::DEFAULT_TIMEOUT));
        $this->cacheDir = isset($config['cache_dir']) ? (string)$config['cache_dir'] : null;
        $this->cacheDuration = max(0, (int)($config['cache_duration'] ?? 3600));
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
            $feed = $this->loadFeed($url);
            
            return $this->normalizeFeed($feed);
        } catch (Exception $e) {
            $this->logError('Ошибка загрузки ленты', ['url' => $url, 'exception' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Создает и настраивает экземпляр SimplePie для загрузки ленты
     *
     * @param string $url Адрес ленты
     * @return SimplePie Настроенный объект SimplePie
     * @throws RuntimeException Если не удалось загрузить ленту
     */
    private function loadFeed(string $url): SimplePie
    {
        $feed = new SimplePie();
        $feed->set_feed_url($url);
        $feed->set_useragent($this->userAgent);
        $feed->set_timeout($this->timeout);
        $feed->enable_order_by_date(true);

        $this->configureCache($feed);

        $success = $feed->init();
        
        if (!$success) {
            $error = $feed->error();
            $this->logError('Ошибка инициализации SimplePie', ['url' => $url, 'error' => $error]);
            throw new RuntimeException('Не удалось загрузить RSS ленту: ' . $error);
        }

        return $feed;
    }

    /**
     * Настраивает кеширование для SimplePie
     *
     * @param SimplePie $feed Объект SimplePie
     */
    private function configureCache(SimplePie $feed): void
    {
        if ($this->cacheDir === null) {
            $feed->enable_cache(false);

            return;
        }

        $cacheDir = $this->cacheDir;

        if (!is_dir($cacheDir)) {
            $created = @mkdir($cacheDir, 0775, true);
            clearstatcache(false, $cacheDir);

            if (!$created && !is_dir($cacheDir)) {
                $this->logError('Не удалось создать директорию кеша RSS', ['cache_dir' => $cacheDir]);
                $feed->enable_cache(false);

                return;
            }
        }

        if (!is_writable($cacheDir)) {
            $this->logError('Директория кеша RSS недоступна для записи', ['cache_dir' => $cacheDir]);
            $feed->enable_cache(false);

            return;
        }

        $feed->set_cache_location($cacheDir);
        $feed->set_cache_duration($this->cacheDuration);
        $feed->enable_cache(true);
    }

    /**
     * Приводит SimplePie объект к единому виду
     *
     * @param SimplePie $feed Объект SimplePie
     * @return array<string, mixed> Данные ленты
     */
    private function normalizeFeed(SimplePie $feed): array
    {
        $items = [];
        
        foreach ($feed->get_items() as $item) {
            $items[] = [
                'title' => $item->get_title() ?? '',
                'link' => $item->get_permalink() ?? '',
                'description' => $item->get_description() ?? '',
                'published_at' => $this->createDate($item->get_date('c')),
                'author' => $this->extractAuthor($item),
                'categories' => $this->extractCategories($item),
            ];
        }

        return [
            'type' => $this->detectFeedType($feed),
            'title' => $feed->get_title() ?? '',
            'description' => $feed->get_description() ?? '',
            'link' => $feed->get_permalink() ?? '',
            'language' => $feed->get_language() ?? '',
            'items' => $items,
        ];
    }

    /**
     * Определяет тип ленты (RSS или Atom)
     *
     * @param SimplePie $feed Объект SimplePie
     * @return string Тип ленты
     */
    private function detectFeedType(SimplePie $feed): string
    {
        $type = $feed->get_type();
        
        if ($type & SIMPLEPIE_TYPE_ATOM_03 || $type & SIMPLEPIE_TYPE_ATOM_10) {
            return 'atom';
        }
        
        return 'rss';
    }

    /**
     * Извлекает имя автора из элемента ленты
     *
     * @param \SimplePie\Item $item Элемент SimplePie
     * @return string Имя автора
     */
    private function extractAuthor($item): string
    {
        $author = $item->get_author();
        
        if ($author === null) {
            return '';
        }
        
        return $author->get_name() ?? '';
    }

    /**
     * Извлекает категории из элемента ленты
     *
     * @param \SimplePie\Item $item Элемент SimplePie
     * @return array<int, string> Список категорий
     */
    private function extractCategories($item): array
    {
        $categories = [];
        
        foreach ($item->get_categories() ?? [] as $category) {
            $label = $category->get_label();
            if ($label !== null && $label !== '') {
                $categories[] = $label;
            }
        }
        
        return $categories;
    }

    /**
     * Создает объект даты из строки
     *
     * @param string|null $value Исходная строка
     * @return DateTimeImmutable|null Объект даты или null
     */
    private function createDate(?string $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
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

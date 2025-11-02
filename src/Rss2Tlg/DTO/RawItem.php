<?php

declare(strict_types=1);

namespace App\Rss2Tlg\DTO;

/**
 * Сырой элемент RSS/Atom ленты (до обработки и дедупликации)
 * 
 * Содержит нормализованные данные элемента ленты в универсальном формате.
 * Поддерживает как RSS, так и Atom элементы.
 */
class RawItem
{
    /**
     * Конструктор сырого элемента ленты
     * 
     * @param string|null $guid Глобальный уникальный идентификатор элемента
     * @param string|null $link Ссылка на полную версию элемента
     * @param string|null $title Заголовок элемента
     * @param string|null $summary Краткое описание элемента (description/summary)
     * @param string|null $content Полный контент элемента (если доступен)
     * @param array<int, string> $authors Список авторов элемента
     * @param array<int, string> $categories Список категорий/тегов элемента
     * @param array<string, mixed>|null $enclosure Вложение (медиа файл): url, type, length
     * @param int|null $pubDate Дата публикации (Unix timestamp)
     * @param string $contentHash Хэш нормализованного контента для дедупликации
     */
    public function __construct(
        public readonly ?string $guid,
        public readonly ?string $link,
        public readonly ?string $title,
        public readonly ?string $summary,
        public readonly ?string $content,
        public readonly array $authors,
        public readonly array $categories,
        public readonly ?array $enclosure,
        public readonly ?int $pubDate,
        public readonly string $contentHash
    ) {
    }

    /**
     * Создаёт экземпляр из массива данных класса Rss
     * 
     * @param array<string, mixed> $item Массив с данными элемента
     * @return self Экземпляр RawItem
     */
    public static function fromRssArray(array $item): self
    {
        // Извлекаем GUID
        $guid = $item['id'] ?? null;
        if ($guid !== null && trim($guid) === '') {
            $guid = null;
        }

        // Извлекаем ссылку
        $link = $item['link'] ?? null;
        if ($link !== null && trim($link) === '') {
            $link = null;
        }

        // Извлекаем заголовок
        $title = $item['title'] ?? null;
        if ($title !== null) {
            $title = html_entity_decode(strip_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $title = trim($title);
            if ($title === '') {
                $title = null;
            }
        }

        // Извлекаем краткое описание
        $summary = $item['description'] ?? null;
        if ($summary !== null) {
            $summary = html_entity_decode(strip_tags($summary), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $summary = trim($summary);
            if ($summary === '') {
                $summary = null;
            }
        }

        // Извлекаем полный контент
        $content = $item['content'] ?? null;
        if ($content !== null) {
            $content = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $content = trim($content);
            if ($content === '') {
                $content = null;
            }
        }

        // Извлекаем авторов
        $authors = [];
        $authorString = $item['author'] ?? '';
        if ($authorString !== '' && trim($authorString) !== '') {
            $authors[] = trim($authorString);
        }

        // Извлекаем категории
        $categories = $item['categories'] ?? [];

        // Извлекаем enclosure
        $enclosure = null;
        $enclosures = $item['enclosures'] ?? [];
        if (!empty($enclosures) && is_array($enclosures[0] ?? null)) {
            $firstEnclosure = $enclosures[0];
            $enclosure = [
                'url' => $firstEnclosure['url'] ?? '',
                'type' => $firstEnclosure['type'] ?? 'application/octet-stream',
                'length' => $firstEnclosure['length'] ?? 0,
            ];
        }

        // Извлекаем дату публикации
        $pubDate = null;
        if (isset($item['published_at']) && $item['published_at'] instanceof \DateTimeImmutable) {
            $pubDate = $item['published_at']->getTimestamp();
        }

        // Вычисляем хэш контента
        $contentHash = self::calculateContentHash($guid, $link, $title, $summary, $content);

        return new self(
            guid: $guid,
            link: $link,
            title: $title,
            summary: $summary,
            content: $content,
            authors: $authors,
            categories: $categories,
            enclosure: $enclosure,
            pubDate: $pubDate,
            contentHash: $contentHash
        );
    }

    /**
     * Создаёт экземпляр из данных SimplePie Item
     * 
     * @param \SimplePie\Item $item Элемент SimplePie
     * @return self Экземпляр RawItem
     */
    public static function fromSimplePieItem(\SimplePie\Item $item): self
    {
        // Извлекаем GUID
        $guid = $item->get_id();
        if ($guid === null || trim($guid) === '') {
            $guid = null;
        }

        // Извлекаем ссылку
        $link = $item->get_permalink();
        if ($link === null || trim($link) === '') {
            $link = null;
        }

        // Извлекаем заголовок
        $title = $item->get_title();
        if ($title !== null) {
            $title = html_entity_decode(strip_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $title = trim($title);
            if ($title === '') {
                $title = null;
            }
        }

        // Извлекаем краткое описание
        $summary = $item->get_description();
        if ($summary !== null) {
            $summary = html_entity_decode(strip_tags($summary), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $summary = trim($summary);
            if ($summary === '') {
                $summary = null;
            }
        }

        // Извлекаем полный контент
        $content = $item->get_content();
        if ($content !== null) {
            $content = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $content = trim($content);
            if ($content === '') {
                $content = null;
            }
        }

        // Извлекаем авторов
        $authors = [];
        $simplePieAuthors = $item->get_authors();
        if ($simplePieAuthors !== null) {
            foreach ($simplePieAuthors as $author) {
                $name = $author->get_name();
                if ($name !== null && trim($name) !== '') {
                    $authors[] = trim($name);
                }
            }
        }

        // Извлекаем категории
        $categories = [];
        $simplePieCategories = $item->get_categories();
        if ($simplePieCategories !== null) {
            foreach ($simplePieCategories as $category) {
                $term = $category->get_term();
                if ($term !== null && trim($term) !== '') {
                    $categories[] = trim($term);
                }
            }
        }

        // Извлекаем enclosure (медиа)
        $enclosure = null;
        $simplePieEnclosure = $item->get_enclosure();
        if ($simplePieEnclosure !== null) {
            $enclosureUrl = $simplePieEnclosure->get_link();
            if ($enclosureUrl !== null && trim($enclosureUrl) !== '') {
                $enclosure = [
                    'url' => $enclosureUrl,
                    'type' => $simplePieEnclosure->get_type() ?? 'application/octet-stream',
                    'length' => $simplePieEnclosure->get_length() ?? 0,
                ];
            }
        }

        // Извлекаем дату публикации
        $pubDate = null;
        $dateObj = $item->get_date('U');
        if ($dateObj !== null && is_numeric($dateObj)) {
            $pubDate = (int)$dateObj;
        }

        // Вычисляем хэш контента для дедупликации
        $contentHash = self::calculateContentHash($guid, $link, $title, $summary, $content);

        return new self(
            guid: $guid,
            link: $link,
            title: $title,
            summary: $summary,
            content: $content,
            authors: $authors,
            categories: $categories,
            enclosure: $enclosure,
            pubDate: $pubDate,
            contentHash: $contentHash
        );
    }

    /**
     * Вычисляет стабильный хэш контента для дедупликации
     * 
     * Использует несколько полей для создания уникального отпечатка элемента.
     * Хэш устойчив к изменениям форматирования и пробелов.
     * 
     * @param string|null $guid GUID элемента
     * @param string|null $link Ссылка элемента
     * @param string|null $title Заголовок элемента
     * @param string|null $summary Краткое описание
     * @param string|null $content Полный контент
     * @return string MD5 хэш нормализованного контента
     */
    private static function calculateContentHash(
        ?string $guid,
        ?string $link,
        ?string $title,
        ?string $summary,
        ?string $content
    ): string {
        // Используем GUID как основной идентификатор если доступен
        if ($guid !== null && trim($guid) !== '') {
            return md5('guid:' . trim($guid));
        }

        // Иначе комбинируем link + title + content
        $parts = [];
        
        if ($link !== null && trim($link) !== '') {
            $parts[] = 'link:' . trim($link);
        }
        
        if ($title !== null && trim($title) !== '') {
            // Нормализуем пробелы в заголовке
            $normalizedTitle = preg_replace('/\s+/u', ' ', trim($title));
            $parts[] = 'title:' . $normalizedTitle;
        }
        
        // Используем content если доступен, иначе summary
        $textContent = $content ?? $summary;
        if ($textContent !== null && trim($textContent) !== '') {
            // Нормализуем пробелы и берём первые 500 символов
            $normalizedContent = preg_replace('/\s+/u', ' ', trim($textContent));
            $truncatedContent = mb_substr($normalizedContent, 0, 500, 'UTF-8');
            $parts[] = 'content:' . $truncatedContent;
        }

        // Если нет ни одного поля - возвращаем случайный хэш (такие элементы вряд ли полезны)
        if (empty($parts)) {
            return md5('empty:' . uniqid('', true));
        }

        return md5(implode('|', $parts));
    }

    /**
     * Проверяет валидность элемента
     * 
     * Элемент считается валидным если у него есть хотя бы:
     * - GUID или ссылка
     * - Заголовок или контент
     * 
     * @return bool true если элемент валиден
     */
    public function isValid(): bool
    {
        $hasIdentifier = ($this->guid !== null && trim($this->guid) !== '') 
            || ($this->link !== null && trim($this->link) !== '');

        $hasContent = ($this->title !== null && trim($this->title) !== '')
            || ($this->summary !== null && trim($this->summary) !== '')
            || ($this->content !== null && trim($this->content) !== '');

        return $hasIdentifier && $hasContent;
    }

    /**
     * Преобразует элемент в массив
     * 
     * @return array<string, mixed> Массив с данными элемента
     */
    public function toArray(): array
    {
        return [
            'guid' => $this->guid,
            'link' => $this->link,
            'title' => $this->title,
            'summary' => $this->summary,
            'content' => $this->content,
            'authors' => $this->authors,
            'categories' => $this->categories,
            'enclosure' => $this->enclosure,
            'pub_date' => $this->pubDate,
            'content_hash' => $this->contentHash,
        ];
    }
}

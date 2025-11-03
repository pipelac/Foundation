<?php

declare(strict_types=1);

namespace App\Rss2Tlg\DTO;

/**
 * Конфигурация источника RSS/Atom ленты
 * 
 * Содержит все настройки для опроса конкретного источника:
 * параметры HTTP запросов, расписание, опции парсера
 */
class FeedConfig
{
    /**
     * Конструктор конфигурации источника
     * 
     * @param int $id Уникальный идентификатор источника
     * @param string $url URL RSS/Atom ленты
     * @param string|null $name Название источника (опционально)
     * @param bool $enabled Флаг активности источника (false - пропускать опрос)
     * @param int $timeout Таймаут HTTP запроса в секундах
     * @param int $retries Количество повторных попыток при ошибках
     * @param int $pollingInterval Интервал опроса в секундах
     * @param array<string, string> $headers Дополнительные HTTP заголовки (User-Agent, Accept и т.д.)
     * @param array<string, mixed> $parserOptions Опции парсера (max_items, enable_cache и т.д.)
     * @param string|null $proxy Настройки прокси (опционально, формат: "http://host:port")
     */
    public function __construct(
        public readonly int $id,
        public readonly string $url,
        public readonly ?string $name = null,
        public readonly bool $enabled = true,
        public readonly int $timeout = 30,
        public readonly int $retries = 3,
        public readonly int $pollingInterval = 300,
        public readonly array $headers = [],
        public readonly array $parserOptions = [],
        public readonly ?string $proxy = null
    ) {
    }

    /**
     * Создаёт экземпляр из массива конфигурации
     * 
     * @param array<string, mixed> $data Массив с данными конфигурации
     * @return self Экземпляр FeedConfig
     * @throws \InvalidArgumentException Если данные невалидны
     */
    public static function fromArray(array $data): self
    {
        self::validate($data);

        return new self(
            id: (int)$data['id'],
            url: (string)$data['url'],
            name: isset($data['name']) ? (string)$data['name'] : null,
            enabled: (bool)($data['enabled'] ?? true),
            timeout: (int)($data['timeout'] ?? 30),
            retries: (int)($data['retries'] ?? 3),
            pollingInterval: (int)($data['polling_interval'] ?? 300),
            headers: (array)($data['headers'] ?? []),
            parserOptions: (array)($data['parser_options'] ?? []),
            proxy: isset($data['proxy']) ? (string)$data['proxy'] : null
        );
    }

    /**
     * Валидирует данные конфигурации
     * 
     * @param array<string, mixed> $data Данные для валидации
     * @return void
     * @throws \InvalidArgumentException Если данные невалидны
     */
    private static function validate(array $data): void
    {
        if (!isset($data['id'])) {
            throw new \InvalidArgumentException('Параметр "id" обязателен');
        }

        if (!isset($data['url']) || trim((string)$data['url']) === '') {
            throw new \InvalidArgumentException('Параметр "url" обязателен и не может быть пустым');
        }

        $url = (string)$data['url'];
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException("Некорректный формат URL: {$url}");
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException("URL должен использовать протокол HTTP или HTTPS: {$url}");
        }
    }

    /**
     * Преобразует конфигурацию в массив
     * 
     * @return array<string, mixed> Массив с данными конфигурации
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'name' => $this->name,
            'enabled' => $this->enabled,
            'timeout' => $this->timeout,
            'retries' => $this->retries,
            'polling_interval' => $this->pollingInterval,
            'headers' => $this->headers,
            'parser_options' => $this->parserOptions,
            'proxy' => $this->proxy,
        ];
    }
}

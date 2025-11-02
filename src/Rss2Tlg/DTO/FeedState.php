<?php

declare(strict_types=1);

namespace App\Rss2Tlg\DTO;

/**
 * Состояние источника RSS/Atom ленты
 * 
 * Хранит текущее состояние источника для оптимизации запросов:
 * - ETag и Last-Modified для Conditional GET
 * - Статус последнего запроса
 * - Счётчики ошибок и backoff для rate limiting
 */
class FeedState
{
    /**
     * Конструктор состояния источника
     * 
     * @param string|null $etag Значение ETag из последнего успешного ответа
     * @param string|null $lastModified Значение Last-Modified из последнего успешного ответа
     * @param int $lastStatus HTTP статус код последнего запроса
     * @param int $errorCount Счётчик последовательных ошибок
     * @param int|null $backoffUntil Unix timestamp до которого запросы заблокированы (backoff)
     * @param int $fetchedAt Unix timestamp последнего запроса
     */
    public function __construct(
        public readonly ?string $etag,
        public readonly ?string $lastModified,
        public readonly int $lastStatus,
        public readonly int $errorCount,
        public readonly ?int $backoffUntil,
        public readonly int $fetchedAt
    ) {
    }

    /**
     * Создаёт начальное состояние для нового источника
     * 
     * @return self Новый экземпляр с пустым состоянием
     */
    public static function createInitial(): self
    {
        return new self(
            etag: null,
            lastModified: null,
            lastStatus: 0,
            errorCount: 0,
            backoffUntil: null,
            fetchedAt: 0
        );
    }

    /**
     * Создаёт экземпляр из массива данных
     * 
     * @param array<string, mixed> $data Массив с данными состояния
     * @return self Экземпляр FeedState
     */
    public static function fromArray(array $data): self
    {
        return new self(
            etag: isset($data['etag']) ? (string)$data['etag'] : null,
            lastModified: isset($data['last_modified']) ? (string)$data['last_modified'] : null,
            lastStatus: (int)($data['last_status'] ?? 0),
            errorCount: (int)($data['error_count'] ?? 0),
            backoffUntil: isset($data['backoff_until']) ? (int)$data['backoff_until'] : null,
            fetchedAt: (int)($data['fetched_at'] ?? 0)
        );
    }

    /**
     * Создаёт новое состояние с успешным запросом
     * 
     * @param string|null $etag Новый ETag из ответа
     * @param string|null $lastModified Новый Last-Modified из ответа
     * @param int $statusCode HTTP статус код
     * @return self Новый экземпляр с обновлёнными данными
     */
    public function withSuccessfulFetch(?string $etag, ?string $lastModified, int $statusCode): self
    {
        return new self(
            etag: $etag,
            lastModified: $lastModified,
            lastStatus: $statusCode,
            errorCount: 0, // Сбрасываем счётчик ошибок
            backoffUntil: null, // Снимаем блокировку
            fetchedAt: time()
        );
    }

    /**
     * Создаёт новое состояние с ошибкой запроса
     * 
     * @param int $statusCode HTTP статус код
     * @param int|null $backoffSeconds Количество секунд для backoff (если null - вычисляется автоматически)
     * @return self Новый экземпляр с увеличенным счётчиком ошибок
     */
    public function withFailedFetch(int $statusCode, ?int $backoffSeconds = null): self
    {
        $newErrorCount = $this->errorCount + 1;
        
        // Экспоненциальный backoff: 60, 120, 240, 480, 960 секунд (макс. 15 минут)
        $calculatedBackoff = min(60 * (2 ** $newErrorCount), 900);
        $backoff = $backoffSeconds ?? $calculatedBackoff;

        return new self(
            etag: $this->etag,
            lastModified: $this->lastModified,
            lastStatus: $statusCode,
            errorCount: $newErrorCount,
            backoffUntil: time() + $backoff,
            fetchedAt: time()
        );
    }

    /**
     * Проверяет, находится ли источник в состоянии backoff
     * 
     * @return bool true если запросы заблокированы
     */
    public function isInBackoff(): bool
    {
        if ($this->backoffUntil === null) {
            return false;
        }

        return time() < $this->backoffUntil;
    }

    /**
     * Возвращает количество секунд до окончания backoff
     * 
     * @return int Секунд до разблокировки (0 если не заблокировано)
     */
    public function getBackoffRemaining(): int
    {
        if (!$this->isInBackoff()) {
            return 0;
        }

        return max(0, $this->backoffUntil - time());
    }

    /**
     * Преобразует состояние в массив
     * 
     * @return array<string, mixed> Массив с данными состояния
     */
    public function toArray(): array
    {
        return [
            'etag' => $this->etag,
            'last_modified' => $this->lastModified,
            'last_status' => $this->lastStatus,
            'error_count' => $this->errorCount,
            'backoff_until' => $this->backoffUntil,
            'fetched_at' => $this->fetchedAt,
        ];
    }
}

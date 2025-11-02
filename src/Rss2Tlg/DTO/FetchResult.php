<?php

declare(strict_types=1);

namespace App\Rss2Tlg\DTO;

/**
 * Результат операции fetch для RSS/Atom источника
 * 
 * Объединяет обновлённое состояние источника и список извлечённых элементов.
 * Используется для передачи данных между этапами конвейера.
 */
class FetchResult
{
    /**
     * Конструктор результата fetch операции
     * 
     * @param int $feedId Идентификатор источника
     * @param FeedState $state Обновлённое состояние источника
     * @param array<int, RawItem> $items Список извлечённых элементов (пустой при 304)
     * @param array<string, mixed> $metrics Метрики выполнения операции
     */
    public function __construct(
        public readonly int $feedId,
        public readonly FeedState $state,
        public readonly array $items,
        public readonly array $metrics = []
    ) {
    }

    /**
     * Создаёт результат для успешного fetch с новыми элементами (200 OK)
     * 
     * @param int $feedId Идентификатор источника
     * @param FeedState $state Обновлённое состояние
     * @param array<int, RawItem> $items Список элементов
     * @param array<string, mixed> $metrics Метрики операции
     * @return self Экземпляр FetchResult
     */
    public static function success(int $feedId, FeedState $state, array $items, array $metrics = []): self
    {
        return new self($feedId, $state, $items, $metrics);
    }

    /**
     * Создаёт результат для 304 Not Modified (без новых элементов)
     * 
     * @param int $feedId Идентификатор источника
     * @param FeedState $state Обновлённое состояние
     * @param array<string, mixed> $metrics Метрики операции
     * @return self Экземпляр FetchResult с пустым списком элементов
     */
    public static function notModified(int $feedId, FeedState $state, array $metrics = []): self
    {
        return new self($feedId, $state, [], $metrics);
    }

    /**
     * Создаёт результат для ошибки fetch
     * 
     * @param int $feedId Идентификатор источника
     * @param FeedState $state Обновлённое состояние с ошибкой
     * @param array<string, mixed> $metrics Метрики операции
     * @return self Экземпляр FetchResult с пустым списком элементов
     */
    public static function error(int $feedId, FeedState $state, array $metrics = []): self
    {
        return new self($feedId, $state, [], $metrics);
    }

    /**
     * Проверяет, был ли fetch успешным
     * 
     * @return bool true если статус 200-299
     */
    public function isSuccessful(): bool
    {
        return $this->state->lastStatus >= 200 && $this->state->lastStatus < 300;
    }

    /**
     * Проверяет, вернул ли сервер 304 Not Modified
     * 
     * @return bool true если статус 304
     */
    public function isNotModified(): bool
    {
        return $this->state->lastStatus === 304;
    }

    /**
     * Проверяет, произошла ли ошибка при fetch
     * 
     * @return bool true если статус >= 400 или 0 (сетевая ошибка)
     */
    public function isError(): bool
    {
        return $this->state->lastStatus === 0 || $this->state->lastStatus >= 400;
    }

    /**
     * Возвращает количество извлечённых элементов
     * 
     * @return int Количество элементов
     */
    public function getItemCount(): int
    {
        return count($this->items);
    }

    /**
     * Возвращает только валидные элементы
     * 
     * @return array<int, RawItem> Массив валидных элементов
     */
    public function getValidItems(): array
    {
        return array_filter($this->items, fn(RawItem $item) => $item->isValid());
    }

    /**
     * Возвращает метрику по ключу
     * 
     * @param string $key Ключ метрики
     * @param mixed $default Значение по умолчанию
     * @return mixed Значение метрики или default
     */
    public function getMetric(string $key, mixed $default = null): mixed
    {
        return $this->metrics[$key] ?? $default;
    }

    /**
     * Преобразует результат в массив
     * 
     * @return array<string, mixed> Массив с данными результата
     */
    public function toArray(): array
    {
        return [
            'feed_id' => $this->feedId,
            'state' => $this->state->toArray(),
            'items' => array_map(fn(RawItem $item) => $item->toArray(), $this->items),
            'metrics' => $this->metrics,
        ];
    }
}

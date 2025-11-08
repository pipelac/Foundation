<?php

declare(strict_types=1);

namespace App\Rss2Tlg\Pipeline;

/**
 * Интерфейс для модулей AI Pipeline
 * 
 * Определяет общий контракт для всех модулей обработки новостей:
 * - Суммаризация
 * - Дедупликация
 * - Перевод
 * - Иллюстрация
 */
interface PipelineModuleInterface
{
    /**
     * Обрабатывает одну новость
     *
     * @param int $itemId ID новости из таблицы rss2tlg_items
     * @return bool True если обработка успешна, False при ошибке
     */
    public function processItem(int $itemId): bool;

    /**
     * Обрабатывает пакет новостей
     *
     * @param array<int> $itemIds Массив ID новостей
     * @return array{success: int, failed: int, skipped: int} Статистика обработки
     */
    public function processBatch(array $itemIds): array;

    /**
     * Получает статус обработки новости
     *
     * @param int $itemId ID новости
     * @return string|null Статус обработки (pending, processing, success, failed, skipped) или null
     */
    public function getStatus(int $itemId): ?string;

    /**
     * Получает метрики работы модуля
     *
     * @return array<string, mixed> Метрики производительности
     */
    public function getMetrics(): array;

    /**
     * Сбрасывает метрики
     */
    public function resetMetrics(): void;
}

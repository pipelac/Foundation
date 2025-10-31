<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Core;

use App\Component\Logger;

/**
 * Ограничитель частоты запросов для Telegram API
 * 
 * Telegram Bot API имеет лимиты:
 * - 30 сообщений в секунду для разных чатов
 * - 1 сообщение в секунду для одного чата
 * - 20 сообщений в минуту для одной группы
 * 
 * Этот класс помогает автоматически соблюдать эти лимиты.
 */
class RateLimiter
{
    /**
     * Максимальное количество запросов
     */
    private int $maxRequests;

    /**
     * Период в секундах
     */
    private int $perSeconds;

    /**
     * История запросов [timestamp => count]
     * 
     * @var array<int, int>
     */
    private array $requestHistory = [];

    /**
     * История запросов по чатам [chatId => [timestamp => count]]
     * 
     * @var array<string|int, array<int, int>>
     */
    private array $chatHistory = [];

    /**
     * Максимальное количество запросов на один чат в секунду
     */
    private int $maxRequestsPerChat = 1;

    /**
     * @param int $maxRequests Максимальное количество запросов (по умолчанию 30)
     * @param int $perSeconds Период в секундах (по умолчанию 1)
     * @param Logger|null $logger Логгер
     */
    public function __construct(
        int $maxRequests = 30,
        int $perSeconds = 1,
        private readonly ?Logger $logger = null
    ) {
        $this->maxRequests = max(1, $maxRequests);
        $this->perSeconds = max(1, $perSeconds);
    }

    /**
     * Проверяет, можно ли выполнить запрос для общего лимита
     *
     * @return bool True если запрос можно выполнить
     */
    public function check(): bool
    {
        $this->cleanup();
        
        $now = time();
        $currentSecond = $now - ($now % $this->perSeconds);
        
        $count = $this->requestHistory[$currentSecond] ?? 0;
        
        if ($count >= $this->maxRequests) {
            $this->logger?->debug('Rate limit достигнут (общий)', [
                'current' => $count,
                'max' => $this->maxRequests,
            ]);
            return false;
        }
        
        return true;
    }

    /**
     * Проверяет, можно ли выполнить запрос для конкретного чата
     *
     * @param string|int $chatId ID чата
     * @return bool True если запрос можно выполнить
     */
    public function checkForChat(string|int $chatId): bool
    {
        $this->cleanup();
        
        $now = time();
        $currentSecond = $now - ($now % $this->perSeconds);
        
        if (!isset($this->chatHistory[$chatId])) {
            return true;
        }
        
        $count = $this->chatHistory[$chatId][$currentSecond] ?? 0;
        
        if ($count >= $this->maxRequestsPerChat) {
            $this->logger?->debug('Rate limit достигнут для чата', [
                'chat_id' => $chatId,
                'current' => $count,
                'max' => $this->maxRequestsPerChat,
            ]);
            return false;
        }
        
        return true;
    }

    /**
     * Записывает выполненный запрос (общий)
     *
     * @return void
     */
    public function record(): void
    {
        $now = time();
        $currentSecond = $now - ($now % $this->perSeconds);
        
        if (!isset($this->requestHistory[$currentSecond])) {
            $this->requestHistory[$currentSecond] = 0;
        }
        
        $this->requestHistory[$currentSecond]++;
    }

    /**
     * Записывает выполненный запрос для конкретного чата
     *
     * @param string|int $chatId ID чата
     * @return void
     */
    public function recordForChat(string|int $chatId): void
    {
        $now = time();
        $currentSecond = $now - ($now % $this->perSeconds);
        
        if (!isset($this->chatHistory[$chatId])) {
            $this->chatHistory[$chatId] = [];
        }
        
        if (!isset($this->chatHistory[$chatId][$currentSecond])) {
            $this->chatHistory[$chatId][$currentSecond] = 0;
        }
        
        $this->chatHistory[$chatId][$currentSecond]++;
    }

    /**
     * Ожидает, пока не будет доступен слот для запроса
     * 
     * ВНИМАНИЕ: Блокирует выполнение!
     *
     * @param int $maxWaitSeconds Максимальное время ожидания (по умолчанию 5 секунд)
     * @return bool True если удалось дождаться, false если превышено время ожидания
     */
    public function wait(int $maxWaitSeconds = 5): bool
    {
        $startTime = time();
        
        while (!$this->check()) {
            if (time() - $startTime >= $maxWaitSeconds) {
                $this->logger?->warning('Превышено время ожидания rate limiter', [
                    'max_wait' => $maxWaitSeconds,
                ]);
                return false;
            }
            
            usleep(100000); // 100ms
        }
        
        return true;
    }

    /**
     * Ожидает доступности слота для конкретного чата
     *
     * @param string|int $chatId ID чата
     * @param int $maxWaitSeconds Максимальное время ожидания
     * @return bool True если удалось дождаться
     */
    public function waitForChat(string|int $chatId, int $maxWaitSeconds = 5): bool
    {
        $startTime = time();
        
        while (!$this->checkForChat($chatId)) {
            if (time() - $startTime >= $maxWaitSeconds) {
                $this->logger?->warning('Превышено время ожидания rate limiter для чата', [
                    'chat_id' => $chatId,
                    'max_wait' => $maxWaitSeconds,
                ]);
                return false;
            }
            
            usleep(100000); // 100ms
        }
        
        return true;
    }

    /**
     * Выполняет действие с автоматическим соблюдением rate limit
     *
     * @param callable $action Действие для выполнения
     * @param string|int|null $chatId ID чата (опционально)
     * @return mixed Результат выполнения действия
     * @throws \Exception Если превышено время ожидания
     */
    public function execute(callable $action, string|int|null $chatId = null): mixed
    {
        // Проверяем общий лимит
        if (!$this->wait()) {
            throw new \Exception('Rate limit: превышено время ожидания (общий лимит)');
        }
        
        // Проверяем лимит для чата, если указан
        if ($chatId !== null && !$this->waitForChat($chatId)) {
            throw new \Exception("Rate limit: превышено время ожидания для чата {$chatId}");
        }
        
        // Выполняем действие
        $result = $action();
        
        // Записываем в историю
        $this->record();
        if ($chatId !== null) {
            $this->recordForChat($chatId);
        }
        
        return $result;
    }

    /**
     * Очищает устаревшую историю запросов
     *
     * @return void
     */
    private function cleanup(): void
    {
        $now = time();
        $cutoff = $now - ($this->perSeconds * 2);
        
        // Очистка общей истории
        foreach ($this->requestHistory as $timestamp => $count) {
            if ($timestamp < $cutoff) {
                unset($this->requestHistory[$timestamp]);
            }
        }
        
        // Очистка истории по чатам
        foreach ($this->chatHistory as $chatId => $history) {
            foreach ($history as $timestamp => $count) {
                if ($timestamp < $cutoff) {
                    unset($this->chatHistory[$chatId][$timestamp]);
                }
            }
            
            // Удаляем пустые записи чатов
            if (empty($this->chatHistory[$chatId])) {
                unset($this->chatHistory[$chatId]);
            }
        }
    }

    /**
     * Сбрасывает все счётчики
     *
     * @return void
     */
    public function reset(): void
    {
        $this->requestHistory = [];
        $this->chatHistory = [];
        $this->logger?->debug('Rate limiter сброшен');
    }

    /**
     * Получает статистику использования
     *
     * @return array{total_requests: int, active_chats: int, current_load: float}
     */
    public function getStats(): array
    {
        $this->cleanup();
        
        $totalRequests = array_sum($this->requestHistory);
        $activeChats = count($this->chatHistory);
        $currentLoad = $this->maxRequests > 0 
            ? ($totalRequests / $this->maxRequests) * 100 
            : 0;
        
        return [
            'total_requests' => $totalRequests,
            'active_chats' => $activeChats,
            'current_load' => round($currentLoad, 2),
        ];
    }

    /**
     * Устанавливает максимальное количество запросов на чат
     *
     * @param int $maxRequests Максимальное количество запросов
     * @return self
     */
    public function setMaxRequestsPerChat(int $maxRequests): self
    {
        $this->maxRequestsPerChat = max(1, $maxRequests);
        return $this;
    }
}

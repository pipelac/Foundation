<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Core;

use App\Component\Logger;
use App\Component\TelegramBot\Entities\Update;
use App\Component\TelegramBot\Exceptions\ApiException;

/**
 * Обработчик работы бота в режиме Long Polling
 * 
 * Получает обновления через метод getUpdates вместо webhook,
 * поддерживает длительное соединение и автоматическое подтверждение
 * обработанных обновлений
 */
class PollingHandler
{
    /**
     * Текущий offset для получения обновлений
     */
    private int $offset = 0;

    /**
     * Флаг остановки polling цикла
     */
    private bool $stopPolling = false;

    /**
     * Таймаут long polling в секундах (максимум 50 для long polling)
     */
    private int $timeout = 30;

    /**
     * Максимальное количество обновлений за один запрос (1-100)
     */
    private int $limit = 100;

    /**
     * Типы обновлений для получения (пустой массив = все типы)
     * 
     * @var array<string>
     */
    private array $allowedUpdates = [];

    /**
     * @param TelegramAPI $api API клиент
     * @param Logger|null $logger Логгер
     */
    public function __construct(
        private readonly TelegramAPI $api,
        private readonly ?Logger $logger = null,
    ) {
        $this->logger?->info('Инициализация PollingHandler');
    }

    /**
     * Устанавливает timeout для long polling
     *
     * @param int $timeout Таймаут в секундах (0-50)
     * @return self
     */
    public function setTimeout(int $timeout): self
    {
        if ($timeout < 0 || $timeout > 50) {
            $this->logger?->warning('Некорректный timeout для polling', [
                'timeout' => $timeout,
                'default' => 30,
            ]);
            $timeout = 30;
        }

        $this->timeout = $timeout;
        $this->logger?->debug('Установлен timeout для polling', ['timeout' => $timeout]);
        return $this;
    }

    /**
     * Устанавливает лимит количества обновлений за запрос
     *
     * @param int $limit Лимит (1-100)
     * @return self
     */
    public function setLimit(int $limit): self
    {
        if ($limit < 1 || $limit > 100) {
            $this->logger?->warning('Некорректный limit для polling', [
                'limit' => $limit,
                'default' => 100,
            ]);
            $limit = 100;
        }

        $this->limit = $limit;
        $this->logger?->debug('Установлен limit для polling', ['limit' => $limit]);
        return $this;
    }

    /**
     * Устанавливает типы разрешенных обновлений
     *
     * @param array<string> $allowedUpdates Типы обновлений
     * @return self
     */
    public function setAllowedUpdates(array $allowedUpdates): self
    {
        $this->allowedUpdates = $allowedUpdates;
        $this->logger?->debug('Установлены разрешенные типы обновлений', [
            'types' => $allowedUpdates,
        ]);
        return $this;
    }

    /**
     * Устанавливает начальный offset
     *
     * @param int $offset Offset для начала получения обновлений
     * @return self
     */
    public function setOffset(int $offset): self
    {
        $this->offset = $offset;
        $this->logger?->debug('Установлен offset', ['offset' => $offset]);
        return $this;
    }

    /**
     * Получает текущий offset
     *
     * @return int Текущий offset
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * Получает обновления через метод getUpdates
     *
     * @return array<Update> Массив обновлений
     * @throws ApiException При ошибке API
     */
    public function getUpdates(): array
    {
        try {
            $params = [
                'offset' => $this->offset,
                'limit' => $this->limit,
                'timeout' => $this->timeout,
            ];

            if (!empty($this->allowedUpdates)) {
                $params['allowed_updates'] = $this->allowedUpdates;
            }

            $this->logger?->debug('Запрос обновлений через getUpdates', [
                'offset' => $this->offset,
                'limit' => $this->limit,
                'timeout' => $this->timeout,
            ]);

            $response = $this->api->getUpdates($params);

            if (!is_array($response)) {
                $this->logger?->error('Получен некорректный ответ от getUpdates');
                return [];
            }

            $updates = [];
            foreach ($response as $updateData) {
                if (!is_array($updateData)) {
                    continue;
                }

                try {
                    $update = Update::fromArray($updateData);
                    $updates[] = $update;

                    // Обновляем offset для следующего запроса
                    if ($update->updateId >= $this->offset) {
                        $this->offset = $update->updateId + 1;
                    }
                } catch (\Exception $e) {
                    $this->logger?->error('Ошибка парсинга обновления', [
                        'error' => $e->getMessage(),
                        'update_data' => $updateData,
                    ]);
                }
            }

            $count = count($updates);
            if ($count > 0) {
                $this->logger?->info('Получено обновлений через polling', [
                    'count' => $count,
                    'new_offset' => $this->offset,
                ]);
            } else {
                $this->logger?->debug('Обновлений не получено');
            }

            return $updates;
        } catch (ApiException $e) {
            $this->logger?->error('Ошибка при получении обновлений через polling', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger?->error('Неожиданная ошибка при polling', [
                'error' => $e->getMessage(),
            ]);
            throw new ApiException('Ошибка при получении обновлений: ' . $e->getMessage());
        }
    }

    /**
     * Запускает цикл polling с обработкой обновлений
     *
     * @param callable $handler Обработчик обновлений function(Update $update): void
     * @param int|null $maxIterations Максимальное количество итераций (null = бесконечно)
     * @return void
     */
    public function startPolling(callable $handler, ?int $maxIterations = null): void
    {
        $this->stopPolling = false;
        $iterations = 0;

        $this->logger?->info('Запуск polling режима', [
            'timeout' => $this->timeout,
            'limit' => $this->limit,
            'max_iterations' => $maxIterations ?? 'бесконечно',
        ]);

        while (!$this->stopPolling) {
            try {
                $updates = $this->getUpdates();

                foreach ($updates as $update) {
                    try {
                        $this->logger?->debug('Обработка обновления', [
                            'update_id' => $update->updateId,
                        ]);

                        $handler($update);

                        $this->logger?->debug('Обновление обработано успешно', [
                            'update_id' => $update->updateId,
                        ]);
                    } catch (\Exception $e) {
                        $this->logger?->error('Ошибка при обработке обновления', [
                            'update_id' => $update->updateId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Проверка лимита итераций
                $iterations++;
                if ($maxIterations !== null && $iterations >= $maxIterations) {
                    $this->logger?->info('Достигнут лимит итераций polling', [
                        'iterations' => $iterations,
                    ]);
                    break;
                }
            } catch (ApiException $e) {
                $this->logger?->error('Критическая ошибка API при polling', [
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                ]);

                // Пауза перед повторной попыткой при ошибке
                sleep(5);
            } catch (\Exception $e) {
                $this->logger?->error('Неожиданная критическая ошибка при polling', [
                    'error' => $e->getMessage(),
                ]);

                // Пауза перед повторной попыткой при ошибке
                sleep(5);
            }
        }

        $this->logger?->info('Polling режим остановлен', [
            'total_iterations' => $iterations,
            'final_offset' => $this->offset,
        ]);
    }

    /**
     * Останавливает цикл polling
     *
     * @return void
     */
    public function stopPolling(): void
    {
        $this->stopPolling = true;
        $this->logger?->info('Получен сигнал остановки polling');
    }

    /**
     * Проверяет, запущен ли polling
     *
     * @return bool True если polling активен
     */
    public function isPolling(): bool
    {
        return !$this->stopPolling;
    }

    /**
     * Обрабатывает одну итерацию polling с возвратом обновлений
     * 
     * Полезно для интеграции в собственный цикл обработки
     *
     * @return array<Update> Массив обновлений
     */
    public function pollOnce(): array
    {
        try {
            $this->logger?->debug('Выполнение одной итерации polling');
            return $this->getUpdates();
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка при выполнении pollOnce', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Сбрасывает состояние обработчика
     *
     * @return void
     */
    public function reset(): void
    {
        $this->stopPolling = false;
        $this->offset = 0;
        $this->logger?->info('Состояние PollingHandler сброшено');
    }

    /**
     * Пропускает все ожидающие обновления
     * 
     * Полезно при первом запуске, чтобы не обрабатывать старые сообщения
     *
     * @return int Количество пропущенных обновлений
     */
    public function skipPendingUpdates(): int
    {
        try {
            $this->logger?->info('Пропуск всех ожидающих обновлений');

            $updates = $this->getUpdates();
            $skipped = count($updates);

            // Если были обновления, offset уже обновлен в getUpdates()
            // Если обновлений не было, offset не меняется

            $this->logger?->info('Пропущено обновлений', [
                'count' => $skipped,
                'new_offset' => $this->offset,
            ]);

            return $skipped;
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка при пропуске обновлений', [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }
}

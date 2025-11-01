<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Core;

use App\Component\Logger;
use App\Component\TelegramBot\Exceptions\AccessControlException;
use App\Component\TelegramBot\Exceptions\ValidationException;

/**
 * Система проверки подписки пользователей на каналы
 * 
 * Управляет доступом к командам бота на основе подписки на Telegram каналы.
 * Поддерживает три режима проверки:
 * - ALL: пользователь должен быть подписан на ВСЕ каналы
 * - ANY: пользователь должен быть подписан хотя бы на ОДИН канал
 * - EXACT: проверка подписки на конкретный канал
 */
class ChannelSubscriptionChecker
{
    /**
     * Режим: пользователь должен быть подписан на ВСЕ каналы
     */
    public const MODE_ALL = 'all';

    /**
     * Режим: пользователь должен быть подписан хотя бы на ОДИН канал
     */
    public const MODE_ANY = 'any';

    /**
     * Режим: проверка подписки на конкретный канал
     */
    public const MODE_EXACT = 'exact';

    /**
     * Включена ли проверка подписки
     */
    private bool $enabled;

    /**
     * Режим проверки (all, any, exact)
     */
    private string $mode;

    /**
     * Список каналов для проверки
     * @var array<string>
     */
    private array $channels;

    /**
     * Сообщение об отказе в доступе
     */
    private string $accessDeniedMessage;

    /**
     * Кеш результатов проверки [user_id => [channel => is_subscribed]]
     * @var array<int, array<string, bool>>
     */
    private array $cache = [];

    /**
     * Время жизни кеша в секундах (по умолчанию 5 минут)
     */
    private int $cacheTtl;

    /**
     * Время последней проверки [user_id => [channel => timestamp]]
     * @var array<int, array<string, int>>
     */
    private array $cacheTimestamps = [];

    /**
     * @param TelegramAPI $api API клиент для проверки подписки
     * @param array<string, mixed> $config Конфигурация проверки подписки
     * @param Logger|null $logger Логгер
     * @throws AccessControlException При ошибке загрузки конфигурации
     */
    public function __construct(
        private readonly TelegramAPI $api,
        array $config,
        private readonly ?Logger $logger = null,
    ) {
        $this->loadConfig($config);
    }

    /**
     * Загружает конфигурацию проверки подписки
     *
     * @param array<string, mixed> $config Конфигурация
     * @throws AccessControlException При ошибке загрузки
     */
    private function loadConfig(array $config): void
    {
        try {
            $this->enabled = $config['enabled'] ?? false;
            $this->mode = $config['mode'] ?? self::MODE_ALL;
            $this->channels = $config['channels'] ?? [];
            $this->accessDeniedMessage = $config['access_denied_message'] ?? 'Для использования бота необходимо подписаться на канал.';
            $this->cacheTtl = $config['cache_ttl'] ?? 300;

            // Валидация режима
            if (!in_array($this->mode, [self::MODE_ALL, self::MODE_ANY, self::MODE_EXACT], true)) {
                throw new AccessControlException("Некорректный режим проверки: {$this->mode}. Допустимые: all, any, exact");
            }

            // Валидация каналов
            if ($this->enabled && empty($this->channels)) {
                throw new AccessControlException('Список каналов не может быть пустым при включенной проверке подписки');
            }

            // Нормализация имен каналов (добавляем @ если отсутствует)
            $this->channels = array_map(function ($channel) {
                $channel = trim($channel);
                if (!str_starts_with($channel, '@')) {
                    $channel = '@' . $channel;
                }
                return $channel;
            }, $this->channels);

            $this->logger?->info('Проверка подписки на каналы ' . ($this->enabled ? 'активирована' : 'деактивирована'), [
                'mode' => $this->mode,
                'channels_count' => count($this->channels),
                'channels' => $this->channels,
                'cache_ttl' => $this->cacheTtl,
            ]);
        } catch (AccessControlException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new AccessControlException('Ошибка загрузки конфигурации проверки подписки: ' . $e->getMessage());
        }
    }

    /**
     * Проверяет, включена ли проверка подписки
     *
     * @return bool True если включена
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Проверяет подписку пользователя на каналы
     *
     * @param int $userId ID пользователя
     * @return bool True если пользователь соответствует требованиям подписки
     */
    public function checkSubscription(int $userId): bool
    {
        // Если проверка выключена - разрешаем доступ
        if (!$this->enabled) {
            return true;
        }

        // Если нет каналов для проверки - разрешаем доступ
        if (empty($this->channels)) {
            return true;
        }

        $this->logger?->debug('Проверка подписки пользователя', [
            'user_id' => $userId,
            'mode' => $this->mode,
            'channels' => $this->channels,
        ]);

        try {
            // Проверяем подписку в зависимости от режима
            $result = match ($this->mode) {
                self::MODE_ALL => $this->checkAllChannels($userId),
                self::MODE_ANY => $this->checkAnyChannel($userId),
                self::MODE_EXACT => $this->checkExactChannel($userId, $this->channels[0]),
                default => false,
            };

            $this->logger?->info('Результат проверки подписки', [
                'user_id' => $userId,
                'result' => $result,
                'mode' => $this->mode,
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка проверки подписки', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            // В случае ошибки разрешаем доступ (fail-open), чтобы не блокировать пользователей
            return true;
        }
    }

    /**
     * Проверяет, подписан ли пользователь на ВСЕ каналы
     *
     * @param int $userId ID пользователя
     * @return bool True если подписан на все каналы
     */
    private function checkAllChannels(int $userId): bool
    {
        foreach ($this->channels as $channel) {
            if (!$this->isUserSubscribedToChannel($userId, $channel)) {
                $this->logger?->debug('Пользователь не подписан на канал', [
                    'user_id' => $userId,
                    'channel' => $channel,
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * Проверяет, подписан ли пользователь хотя бы на ОДИН канал
     *
     * @param int $userId ID пользователя
     * @return bool True если подписан хотя бы на один канал
     */
    private function checkAnyChannel(int $userId): bool
    {
        foreach ($this->channels as $channel) {
            if ($this->isUserSubscribedToChannel($userId, $channel)) {
                $this->logger?->debug('Пользователь подписан на канал', [
                    'user_id' => $userId,
                    'channel' => $channel,
                ]);
                return true;
            }
        }

        return false;
    }

    /**
     * Проверяет подписку на конкретный канал
     *
     * @param int $userId ID пользователя
     * @param string $channel Username канала
     * @return bool True если подписан
     */
    private function checkExactChannel(int $userId, string $channel): bool
    {
        return $this->isUserSubscribedToChannel($userId, $channel);
    }

    /**
     * Проверяет, подписан ли пользователь на канал (с кешированием)
     *
     * @param int $userId ID пользователя
     * @param string $channel Username канала
     * @return bool True если подписан
     */
    private function isUserSubscribedToChannel(int $userId, string $channel): bool
    {
        // Проверяем кеш
        if ($this->isCacheValid($userId, $channel)) {
            $this->logger?->debug('Использование кешированного результата', [
                'user_id' => $userId,
                'channel' => $channel,
            ]);
            return $this->cache[$userId][$channel];
        }

        // Выполняем проверку через API
        try {
            $chatMember = $this->api->getChatMember($channel, $userId);
            $isSubscribed = $chatMember->isSubscribed();

            // Сохраняем в кеш
            $this->setCacheValue($userId, $channel, $isSubscribed);

            $this->logger?->debug('Статус подписки получен', [
                'user_id' => $userId,
                'channel' => $channel,
                'status' => $chatMember->status,
                'is_subscribed' => $isSubscribed,
            ]);

            return $isSubscribed;
        } catch (ValidationException $e) {
            $this->logger?->error('Ошибка валидации при проверке подписки', [
                'user_id' => $userId,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка при проверке подписки', [
                'user_id' => $userId,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Проверяет валидность кешированного значения
     *
     * @param int $userId ID пользователя
     * @param string $channel Username канала
     * @return bool True если кеш валиден
     */
    private function isCacheValid(int $userId, string $channel): bool
    {
        if (!isset($this->cache[$userId][$channel])) {
            return false;
        }

        if (!isset($this->cacheTimestamps[$userId][$channel])) {
            return false;
        }

        $timestamp = $this->cacheTimestamps[$userId][$channel];
        $currentTime = time();

        return ($currentTime - $timestamp) < $this->cacheTtl;
    }

    /**
     * Сохраняет значение в кеш
     *
     * @param int $userId ID пользователя
     * @param string $channel Username канала
     * @param bool $value Результат проверки
     */
    private function setCacheValue(int $userId, string $channel, bool $value): void
    {
        if (!isset($this->cache[$userId])) {
            $this->cache[$userId] = [];
            $this->cacheTimestamps[$userId] = [];
        }

        $this->cache[$userId][$channel] = $value;
        $this->cacheTimestamps[$userId][$channel] = time();
    }

    /**
     * Очищает кеш для пользователя
     *
     * @param int|null $userId ID пользователя (null - очистить весь кеш)
     */
    public function clearCache(?int $userId = null): void
    {
        if ($userId === null) {
            $this->cache = [];
            $this->cacheTimestamps = [];
            $this->logger?->info('Кеш проверки подписки полностью очищен');
        } else {
            unset($this->cache[$userId], $this->cacheTimestamps[$userId]);
            $this->logger?->debug('Кеш проверки подписки очищен для пользователя', [
                'user_id' => $userId,
            ]);
        }
    }

    /**
     * Возвращает сообщение об отказе в доступе
     *
     * @return string Сообщение
     */
    public function getAccessDeniedMessage(): string
    {
        return $this->accessDeniedMessage;
    }

    /**
     * Возвращает список каналов для проверки
     *
     * @return array<string> Массив username каналов
     */
    public function getChannels(): array
    {
        return $this->channels;
    }

    /**
     * Возвращает текущий режим проверки
     *
     * @return string Режим (all, any, exact)
     */
    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * Получает детальную информацию о подписках пользователя
     *
     * @param int $userId ID пользователя
     * @return array<string, bool> Массив [channel => is_subscribed]
     */
    public function getSubscriptionDetails(int $userId): array
    {
        $details = [];

        foreach ($this->channels as $channel) {
            try {
                $details[$channel] = $this->isUserSubscribedToChannel($userId, $channel);
            } catch (\Exception $e) {
                $this->logger?->error('Ошибка получения информации о подписке', [
                    'user_id' => $userId,
                    'channel' => $channel,
                    'error' => $e->getMessage(),
                ]);
                $details[$channel] = false;
            }
        }

        return $details;
    }

    /**
     * Форматирует список каналов для отображения пользователю
     *
     * @return string Отформатированный список каналов
     */
    public function formatChannelsList(): string
    {
        $list = [];
        foreach ($this->channels as $channel) {
            $list[] = "• {$channel}";
        }

        return implode("\n", $list);
    }
}

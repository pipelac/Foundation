<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Core;

use App\Component\Logger;
use App\Component\TelegramBot\Exceptions\WebhookException;

/**
 * Помощник для автоматической настройки webhook
 * 
 * Упрощает настройку, проверку и удаление webhook для Telegram Bot API.
 * Поддерживает все параметры webhook включая сертификаты и IP whitelist.
 */
class WebhookSetup
{
    /**
     * @param TelegramAPI $api API клиент
     * @param Logger|null $logger Логгер
     */
    public function __construct(
        private readonly TelegramAPI $api,
        private readonly ?Logger $logger = null
    ) {
    }

    /**
     * Настраивает webhook
     *
     * @param string $url URL для webhook (должен быть HTTPS)
     * @param array{
     *     certificate?: string,
     *     ip_address?: string,
     *     max_connections?: int,
     *     allowed_updates?: array<string>,
     *     drop_pending_updates?: bool,
     *     secret_token?: string
     * } $options Дополнительные параметры
     * @return bool True при успешной настройке
     * @throws WebhookException При ошибке настройки
     */
    public function configure(string $url, array $options = []): bool
    {
        try {
            // Валидация URL
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw new WebhookException("Некорректный URL: {$url}");
            }

            // Проверка HTTPS (обязательно для Telegram)
            if (!str_starts_with($url, 'https://')) {
                throw new WebhookException("URL должен использовать HTTPS: {$url}");
            }

            $this->logger?->info('Настройка webhook', [
                'url' => $url,
                'options' => array_keys($options),
            ]);

            $result = $this->api->setWebhook($url, $options);

            if ($result) {
                $this->logger?->info('Webhook успешно настроен', ['url' => $url]);
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка настройки webhook', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            throw new WebhookException(
                "Не удалось настроить webhook: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Проверяет текущее состояние webhook
     *
     * @return array{url: string, has_custom_certificate: bool, pending_update_count: int, last_error_date?: int, last_error_message?: string, max_connections?: int, allowed_updates?: array<string>}
     * @throws WebhookException При ошибке проверки
     */
    public function verify(): array
    {
        try {
            $this->logger?->debug('Проверка состояния webhook');

            $info = $this->api->getWebhookInfo();

            $this->logger?->info('Получена информация о webhook', [
                'url' => $info['url'] ?? 'не установлен',
                'pending' => $info['pending_update_count'] ?? 0,
            ]);

            return $info;
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка проверки webhook', [
                'error' => $e->getMessage(),
            ]);
            throw new WebhookException(
                "Не удалось проверить webhook: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Удаляет webhook
     *
     * @param bool $dropPendingUpdates Удалить ожидающие обновления
     * @return bool True при успешном удалении
     * @throws WebhookException При ошибке удаления
     */
    public function delete(bool $dropPendingUpdates = false): bool
    {
        try {
            $this->logger?->info('Удаление webhook', [
                'drop_pending' => $dropPendingUpdates,
            ]);

            $result = $this->api->deleteWebhook($dropPendingUpdates);

            if ($result) {
                $this->logger?->info('Webhook успешно удалён');
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка удаления webhook', [
                'error' => $e->getMessage(),
            ]);
            throw new WebhookException(
                "Не удалось удалить webhook: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Проверяет, настроен ли webhook
     *
     * @return bool True если webhook настроен
     */
    public function isConfigured(): bool
    {
        try {
            $info = $this->verify();
            return !empty($info['url']);
        } catch (\Exception $e) {
            $this->logger?->warning('Не удалось проверить наличие webhook', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Получает количество ожидающих обновлений
     *
     * @return int Количество ожидающих обновлений
     */
    public function getPendingUpdatesCount(): int
    {
        try {
            $info = $this->verify();
            return (int)($info['pending_update_count'] ?? 0);
        } catch (\Exception $e) {
            $this->logger?->warning('Не удалось получить количество ожидающих обновлений', [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Проверяет наличие ошибок webhook
     *
     * @return array{has_error: bool, last_error_date?: int, last_error_message?: string}|null Информация об ошибках или null
     */
    public function getLastError(): ?array
    {
        try {
            $info = $this->verify();

            if (isset($info['last_error_date']) && isset($info['last_error_message'])) {
                return [
                    'has_error' => true,
                    'last_error_date' => $info['last_error_date'],
                    'last_error_message' => $info['last_error_message'],
                ];
            }

            return ['has_error' => false];
        } catch (\Exception $e) {
            $this->logger?->warning('Не удалось проверить ошибки webhook', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Переключается с polling на webhook
     *
     * @param string $url URL для webhook
     * @param array<string, mixed> $options Опции webhook
     * @return bool True при успешном переключении
     */
    public function switchFromPolling(string $url, array $options = []): bool
    {
        try {
            $this->logger?->info('Переключение с polling на webhook');

            // Настраиваем webhook с удалением старых обновлений
            $options['drop_pending_updates'] = $options['drop_pending_updates'] ?? true;

            $result = $this->configure($url, $options);

            if ($result) {
                $this->logger?->info('Успешно переключено на webhook');
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка переключения на webhook', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Переключается с webhook на polling
     *
     * @param bool $dropPendingUpdates Удалить ожидающие обновления
     * @return bool True при успешном переключении
     */
    public function switchToPolling(bool $dropPendingUpdates = false): bool
    {
        try {
            $this->logger?->info('Переключение с webhook на polling');

            $result = $this->delete($dropPendingUpdates);

            if ($result) {
                $this->logger?->info('Успешно переключено на polling');
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка переключения на polling', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Генерирует secret token для webhook
     *
     * @param int $length Длина токена (по умолчанию 32)
     * @return string Сгенерированный токен
     */
    public static function generateSecretToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Проверяет secret token из запроса
     *
     * @param string $expectedToken Ожидаемый токен
     * @param string $receivedToken Полученный токен из заголовка
     * @return bool True если токены совпадают
     */
    public static function verifySecretToken(string $expectedToken, string $receivedToken): bool
    {
        return hash_equals($expectedToken, $receivedToken);
    }
}

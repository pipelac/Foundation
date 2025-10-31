<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Core;

use App\Component\Logger;
use App\Component\TelegramBot\Entities\Update;
use App\Component\TelegramBot\Exceptions\WebhookException;

/**
 * Обработчик входящих webhook запросов от Telegram
 * 
 * Получает, парсит и валидирует входящие обновления,
 * предоставляя типобезопасный доступ к данным
 */
class WebhookHandler
{
    /**
     * Токен для проверки секретного заголовка (опционально)
     */
    private ?string $secretToken = null;

    /**
     * @param Logger|null $logger Логгер
     */
    public function __construct(
        private readonly ?Logger $logger = null,
    ) {
    }

    /**
     * Устанавливает секретный токен для проверки webhook запросов
     *
     * @param string $token Секретный токен
     * @return self
     */
    public function setSecretToken(string $token): self
    {
        $this->secretToken = $token;
        return $this;
    }

    /**
     * Получает входящее обновление из webhook запроса
     *
     * @param bool $validate Валидировать секретный токен
     * @return Update Объект обновления
     * @throws WebhookException При ошибке получения или парсинга
     */
    public function getUpdate(bool $validate = true): Update
    {
        try {
            if ($validate && $this->secretToken !== null) {
                $this->validateSecretToken();
            }

            $input = $this->getRequestBody();

            if (empty($input)) {
                throw new WebhookException('Получен пустой запрос');
            }

            $data = json_decode($input, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($data)) {
                throw new WebhookException('Некорректный формат данных webhook');
            }

            if (!isset($data['update_id'])) {
                throw new WebhookException('Отсутствует update_id в данных webhook');
            }

            $this->logger?->debug('Получено обновление из webhook', [
                'update_id' => $data['update_id'],
                'keys' => array_keys($data),
            ]);

            return Update::fromArray($data);
        } catch (\JsonException $e) {
            $this->logger?->error('Ошибка парсинга JSON из webhook', [
                'error' => $e->getMessage(),
            ]);

            throw new WebhookException('Ошибка парсинга JSON: ' . $e->getMessage());
        } catch (WebhookException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger?->error('Ошибка обработки webhook', [
                'error' => $e->getMessage(),
            ]);

            throw new WebhookException('Ошибка обработки webhook: ' . $e->getMessage());
        }
    }

    /**
     * Проверяет секретный токен из заголовка
     *
     * @throws WebhookException Если токен не совпадает
     */
    private function validateSecretToken(): void
    {
        $header = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null;

        if ($header === null) {
            throw new WebhookException('Отсутствует заголовок X-Telegram-Bot-Api-Secret-Token');
        }

        if (!hash_equals($this->secretToken, $header)) {
            $this->logger?->warning('Получен webhook запрос с неверным секретным токеном');
            throw new WebhookException('Неверный секретный токен');
        }
    }

    /**
     * Получает тело запроса
     *
     * @return string Тело запроса
     * @throws WebhookException Если не удалось прочитать
     */
    private function getRequestBody(): string
    {
        $input = file_get_contents('php://input');

        if ($input === false) {
            throw new WebhookException('Не удалось прочитать тело запроса');
        }

        return $input;
    }

    /**
     * Отправляет HTTP 200 OK ответ Telegram
     * 
     * Необходимо вызвать для подтверждения получения обновления
     *
     * @param array<string, mixed>|null $response Опциональный JSON ответ
     */
    public function sendResponse(?array $response = null): void
    {
        if ($response !== null) {
            header('Content-Type: application/json');
            echo json_encode($response);
        } else {
            http_response_code(200);
        }

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    /**
     * Обрабатывает webhook и возвращает обновление или null при ошибке
     * 
     * Безопасная обертка над getUpdate() для production
     *
     * @param bool $validate Валидировать секретный токен
     * @return Update|null Обновление или null при ошибке
     */
    public function handleSafely(bool $validate = true): ?Update
    {
        try {
            return $this->getUpdate($validate);
        } catch (WebhookException $e) {
            $this->logger?->error('Ошибка обработки webhook (безопасный режим)', [
                'error' => $e->getMessage(),
            ]);

            $this->sendResponse();
            return null;
        }
    }

    /**
     * Проверяет, является ли текущий запрос POST запросом
     *
     * @return bool True если POST
     */
    public static function isPostRequest(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /**
     * Проверяет, содержит ли запрос JSON
     *
     * @return bool True если Content-Type: application/json
     */
    public static function isJsonRequest(): bool
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        return str_contains($contentType, 'application/json');
    }

    /**
     * Выполняет базовую валидацию webhook запроса
     *
     * @return bool True если запрос выглядит как webhook от Telegram
     */
    public static function isValidWebhookRequest(): bool
    {
        return self::isPostRequest() && self::isJsonRequest();
    }
}

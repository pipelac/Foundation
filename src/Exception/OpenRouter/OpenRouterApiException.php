<?php

declare(strict_types=1);

namespace App\Component\Exception\OpenRouter;

/**
 * Исключение для ошибок API OpenRouter (код ответа >= 400)
 */
class OpenRouterApiException extends OpenRouterException
{
    private int $statusCode;
    private string $responseBody;

    /**
     * @param string $message Сообщение об ошибке
     * @param int $statusCode HTTP код ответа
     * @param string $responseBody Тело ответа от API
     */
    public function __construct(string $message, int $statusCode, string $responseBody)
    {
        parent::__construct($message, $statusCode);
        $this->statusCode = $statusCode;
        $this->responseBody = $responseBody;
    }

    /**
     * Получить HTTP код ответа
     *
     * @return int HTTP код статуса
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Получить тело ответа от API
     *
     * @return string Тело ответа
     */
    public function getResponseBody(): string
    {
        return $this->responseBody;
    }
}

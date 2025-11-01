<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Entities;

/**
 * Сущность локации Telegram
 * 
 * Представляет географическую точку
 * 
 * @link https://core.telegram.org/bots/api#location
 */
class Location
{
    /**
     * @param float $longitude Долгота
     * @param float $latitude Широта
     * @param float|null $horizontalAccuracy Радиус неопределенности локации в метрах (0-1500)
     * @param int|null $livePeriod Время относительно отправки сообщения, в течение которого обновляется локация (секунды)
     * @param int|null $heading Направление движения пользователя (1-360 градусов)
     * @param int|null $proximityAlertRadius Максимальное расстояние для оповещения о приближении к другому участнику чата (метры)
     */
    public function __construct(
        public readonly float $longitude,
        public readonly float $latitude,
        public readonly ?float $horizontalAccuracy = null,
        public readonly ?int $livePeriod = null,
        public readonly ?int $heading = null,
        public readonly ?int $proximityAlertRadius = null,
    ) {
    }

    /**
     * Создает объект Location из массива данных Telegram API
     *
     * @param array<string, mixed> $data Данные от Telegram API
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            longitude: (float)$data['longitude'],
            latitude: (float)$data['latitude'],
            horizontalAccuracy: isset($data['horizontal_accuracy']) ? (float)$data['horizontal_accuracy'] : null,
            livePeriod: isset($data['live_period']) ? (int)$data['live_period'] : null,
            heading: isset($data['heading']) ? (int)$data['heading'] : null,
            proximityAlertRadius: isset($data['proximity_alert_radius']) ? (int)$data['proximity_alert_radius'] : null,
        );
    }
}

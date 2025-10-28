<?php

declare(strict_types=1);

namespace App\Config;

use Exception;

/**
 * Загрузчик конфигурационных файлов
 */
class ConfigLoader
{
    /**
     * Загружает и парсит JSON конфигурационный файл
     *
     * @param string $configPath Путь к конфигурационному файлу
     * @return array<string, mixed> Массив конфигурации
     * @throws Exception Если файл не найден или содержит некорректный JSON
     */
    public static function load(string $configPath): array
    {
        if (!file_exists($configPath)) {
            throw new Exception("Конфигурационный файл не найден: {$configPath}");
        }

        $content = file_get_contents($configPath);
        if ($content === false) {
            throw new Exception("Не удалось прочитать конфигурационный файл: {$configPath}");
        }

        $config = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Ошибка парсинга JSON: " . json_last_error_msg());
        }

        return $config;
    }
}

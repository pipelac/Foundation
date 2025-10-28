<?php

declare(strict_types=1);

namespace App\Component;

/**
 * Фабрика MySQL соединений с кешированием инстансов
 */
class MySQLConnectionFactory
{
    /**
     * @var array<string, MySQL> Кеш созданных подключений
     */
    private static array $connections = [];

    /**
     * Возвращает MySQL соединение, используя кеш по конфигурации
     *
     * @param array<string, mixed> $config Конфигурация подключения
     * @param Logger|null $logger Логгер для записи ошибок
     * @return MySQL Готовый экземпляр MySQL
     */
    public static function get(array $config, ?Logger $logger = null): MySQL
    {
        $key = self::buildCacheKey($config);

        if (!isset(self::$connections[$key])) {
            self::$connections[$key] = new MySQL($config, $logger);
        }

        return self::$connections[$key];
    }

    /**
     * Сбрасывает кеш подключений
     *
     * @param array<string, mixed>|null $config Конфигурация для удаления конкретного подключения
     */
    public static function clear(?array $config = null): void
    {
        if ($config === null) {
            self::$connections = [];

            return;
        }

        $key = self::buildCacheKey($config);
        unset(self::$connections[$key]);
    }

    /**
     * Возвращает количество закешированных подключений
     */
    public static function count(): int
    {
        return count(self::$connections);
    }

    /**
     * Создает ключ кеша на основе конфигурации
     *
     * @param array<string, mixed> $config Конфигурация подключения
     * @return string Уникальный ключ кеша
     */
    private static function buildCacheKey(array $config): string
    {
        $normalized = [
            'host' => (string)($config['host'] ?? 'localhost'),
            'port' => (int)($config['port'] ?? 3306),
            'database' => (string)($config['database'] ?? ''),
            'username' => (string)($config['username'] ?? ''),
            'password' => hash('sha256', (string)($config['password'] ?? '')),
            'charset' => (string)($config['charset'] ?? 'utf8mb4'),
            'options' => self::normalizeOptions((array)($config['options'] ?? [])),
        ];

        return hash('sha256', serialize($normalized));
    }

    /**
     * Нормализует набор опций подключения
     *
     * @param array<mixed> $options Массив опций
     * @return array<mixed> Отсортированный массив опций
     */
    private static function normalizeOptions(array $options): array
    {
        ksort($options);

        foreach ($options as $key => $value) {
            if (is_array($value)) {
                $options[$key] = self::normalizeOptions($value);
            }
        }

        return $options;
    }
}

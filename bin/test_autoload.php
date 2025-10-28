#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Тест автозагрузки классов
 */

require_once __DIR__ . '/../autoload.php';

$classes = [
    'App\Config\ConfigLoader',
    'App\Component\Logger',
    'App\Component\MySQL',
    'App\Component\MySQLConnectionFactory',
    'App\Component\Rss',
    'App\Component\OpenRouter',
    'App\Component\Telegram',
];

echo "Проверка автозагрузки классов...\n\n";

foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "✓ {$class} загружен успешно\n";
    } else {
        echo "✗ {$class} не найден\n";
        exit(1);
    }
}

echo "\nВсе классы загружены корректно!\n";

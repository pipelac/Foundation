#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Пример использования компонентов
 */

$autoloaderPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloaderPath)) {
    require_once $autoloaderPath;
} else {
    require_once __DIR__ . '/../autoload.php';
}

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\Rss;
use App\Component\OpenRouter;
use App\Component\Telegram;
use App\Config\ConfigLoader;

try {
    // Загрузка конфигураций
    $loggerConfig = ConfigLoader::load(__DIR__ . '/../config/logger.json');
    $mysqlConfig = ConfigLoader::load(__DIR__ . '/../config/mysql.json');
    $rssConfig = ConfigLoader::load(__DIR__ . '/../config/rss.json');
    $openRouterConfig = ConfigLoader::load(__DIR__ . '/../config/openrouter.json');
    $telegramConfig = ConfigLoader::load(__DIR__ . '/../config/telegram.json');

    // Инициализация логгера
    $logger = new Logger($loggerConfig);
    $logger->info('Приложение запущено');

    // Инициализация компонентов
    $mysql = new MySQL($mysqlConfig, $logger);
    $rss = new Rss($rssConfig, $logger);
    $openRouter = new OpenRouter($openRouterConfig, $logger);
    $telegram = new Telegram($telegramConfig, $logger);

    // Пример работы с RSS
    echo "=== Пример работы с RSS ===\n";
    $feed = $rss->fetch('https://news.ycombinator.com/rss');
    echo "Лента: {$feed['title']}\n";
    echo "Количество элементов: " . count($feed['items']) . "\n\n";

    // Пример работы с БД
    echo "=== Пример работы с MySQL ===\n";
    // $mysql->query('CREATE TABLE IF NOT EXISTS feeds (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255))');
    // $lastId = $mysql->insert('INSERT INTO feeds (title) VALUES (?)', [$feed['title']]);
    // echo "Вставлена запись с ID: {$lastId}\n\n";

    // Пример работы с OpenRouter (text2text)
    echo "=== Пример работы с OpenRouter ===\n";
    // $response = $openRouter->text2text('openai/gpt-3.5-turbo', 'Привет, как дела?');
    // echo "Ответ модели: {$response}\n\n";

    // Пример работы с Telegram
    echo "=== Пример работы с Telegram ===\n";
    // $telegram->sendText(null, 'Тестовое сообщение из набора утилит');
    // echo "Сообщение отправлено\n\n";

    $logger->info('Приложение завершено');

} catch (Exception $e) {
    if (isset($logger)) {
        $logger->error('Критическая ошибка', ['exception' => $e->getMessage()]);
    }

    echo "Ошибка: {$e->getMessage()}\n";
    exit(1);
}

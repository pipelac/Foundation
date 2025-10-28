<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use App\Component\Rss;
use App\Component\Logger;

echo "=== RSS Feed Parser Examples ===\n\n";

// Пример 1: Создание RSS загрузчика без логгера
echo "1. Создание RSS загрузчика без логгера:\n";
$rss = new Rss([
    'user_agent' => 'MyRSSReader/1.0',
    'timeout' => 15,
    'max_content_size' => 5242880, // 5 MB
]);
echo "RSS загрузчик создан\n\n";

// Пример 2: Создание RSS загрузчика с логгером
echo "2. Создание RSS загрузчика с логгером:\n";
$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'file_name' => 'rss.log',
    'max_files' => 3,
    'max_file_size' => 1, // 1 MB
    'pattern' => '[{timestamp}] {level}: {message} {context}',
    'date_format' => 'Y-m-d H:i:s',
]);

$rssWithLogger = new Rss([
    'user_agent' => 'MyRSSReader/2.0',
    'timeout' => 10,
], $logger);
echo "RSS загрузчик с логгером создан\n\n";

// Пример 3: Загрузка RSS ленты (пример с публичным RSS)
echo "3. Загрузка и парсинг RSS ленты:\n";
try {
    // Пример с RSS лентой Habr
    $feed = $rssWithLogger->fetch('https://habr.com/ru/rss/all/all/');
    
    echo "Тип ленты: {$feed['type']}\n";
    echo "Заголовок: {$feed['title']}\n";
    echo "Описание: {$feed['description']}\n";
    echo "Ссылка: {$feed['link']}\n";
    echo "Язык: {$feed['language']}\n";
    echo "Количество элементов: " . count($feed['items']) . "\n\n";
    
    // Вывод первых 3 элементов
    echo "Первые 3 элемента:\n";
    $itemsToShow = array_slice($feed['items'], 0, 3);
    
    foreach ($itemsToShow as $index => $item) {
        $itemNumber = $index + 1;
        echo "\n--- Элемент #{$itemNumber} ---\n";
        echo "Заголовок: {$item['title']}\n";
        echo "Ссылка: {$item['link']}\n";
        echo "Описание: " . mb_substr($item['description'], 0, 100) . "...\n";
        
        if ($item['published_at'] !== null) {
            echo "Дата публикации: {$item['published_at']->format('Y-m-d H:i:s')}\n";
        }
        
        if ($item['author'] !== '') {
            echo "Автор: {$item['author']}\n";
        }
        
        if (!empty($item['categories'])) {
            echo "Категории: " . implode(', ', $item['categories']) . "\n";
        }
    }
    
} catch (RuntimeException $e) {
    echo "Ошибка загрузки RSS ленты: {$e->getMessage()}\n";
} catch (Exception $e) {
    echo "Критическая ошибка: {$e->getMessage()}\n";
}

echo "\n";

// Пример 4: Обработка ошибок валидации URL
echo "4. Обработка ошибок валидации URL:\n";
$invalidUrls = [
    '',
    'not-a-url',
    'ftp://example.com/feed.xml',
    'http://',
];

foreach ($invalidUrls as $url) {
    try {
        echo "Попытка загрузить: '$url'\n";
        $rss->fetch($url);
    } catch (RuntimeException $e) {
        echo "  ✗ Ошибка: {$e->getMessage()}\n";
    }
}

echo "\n";

// Пример 5: Atom лента
echo "5. Загрузка Atom ленты:\n";
try {
    // Пример с Atom лентой (GitHub releases)
    $atomFeed = $rssWithLogger->fetch('https://github.com/php/php-src/releases.atom');
    
    echo "Тип ленты: {$atomFeed['type']}\n";
    echo "Заголовок: {$atomFeed['title']}\n";
    echo "Количество элементов: " . count($atomFeed['items']) . "\n";
    
    if (!empty($atomFeed['items'])) {
        $firstItem = $atomFeed['items'][0];
        echo "\nПервый элемент:\n";
        echo "  Заголовок: {$firstItem['title']}\n";
        echo "  Ссылка: {$firstItem['link']}\n";
    }
    
} catch (RuntimeException $e) {
    echo "Ошибка загрузки Atom ленты: {$e->getMessage()}\n";
} catch (Exception $e) {
    echo "Критическая ошибка: {$e->getMessage()}\n";
}

echo "\n";

// Пример 6: Обработка несуществующей ленты
echo "6. Обработка несуществующей ленты (404 ошибка):\n";
try {
    $rss->fetch('https://example.com/nonexistent-feed.xml');
} catch (RuntimeException $e) {
    echo "  ✗ Ожидаемая ошибка: {$e->getMessage()}\n";
}

echo "\n";

// Пример 7: Работа с кастомными настройками
echo "7. RSS загрузчик с максимальной производительностью:\n";
$fastRss = new Rss([
    'timeout' => 5,
    'max_content_size' => 1048576, // 1 MB - маленький лимит для быстрой загрузки
]);
echo "Быстрый RSS загрузчик создан с таймаутом 5 сек и лимитом 1 MB\n\n";

echo "=== Examples completed ===\n";

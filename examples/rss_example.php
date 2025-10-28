<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use App\Component\Rss;
use App\Component\Logger;
use App\Component\Exception\RssException;
use App\Component\Exception\RssValidationException;

echo "=== RSS Feed Parser с SimplePie - Примеры ===\n\n";

// Пример 1: Создание RSS загрузчика без логгера и кеша
echo "1. Создание базового RSS загрузчика:\n";
$rss = new Rss([
    'user_agent' => 'MyRSSReader/2.0',
    'timeout' => 15,
    'max_content_size' => 5242880, // 5 MB
    'enable_cache' => false, // Отключаем кеш для примера
]);
echo "RSS загрузчик создан (без кеша)\n\n";

// Пример 2: Создание RSS загрузчика с логгером и кешем
echo "2. Создание RSS загрузчика с логгером и кешем:\n";
$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'file_name' => 'rss.log',
    'max_files' => 3,
    'max_file_size' => 1, // 1 MB
    'pattern' => '[{timestamp}] {level}: {message} {context}',
    'date_format' => 'Y-m-d H:i:s',
]);

$rssWithLogger = new Rss([
    'user_agent' => 'MyRSSReader/2.0 SimplePie',
    'timeout' => 10,
    'cache_directory' => __DIR__ . '/cache',
    'cache_duration' => 3600, // 1 час
    'enable_cache' => true,
    'enable_sanitization' => true,
], $logger);
echo "RSS загрузчик с логгером и кешем создан\n\n";

// Пример 3: Загрузка RSS ленты
echo "3. Загрузка и парсинг RSS ленты:\n";
try {
    // Пример с RSS лентой Habr
    $feed = $rssWithLogger->fetch('https://habr.com/ru/rss/all/all/');
    
    echo "Тип ленты: {$feed['type']}\n";
    echo "Заголовок: {$feed['title']}\n";
    echo "Описание: {$feed['description']}\n";
    echo "Ссылка: {$feed['link']}\n";
    echo "Язык: {$feed['language']}\n";
    
    // Новые поля SimplePie
    if (!empty($feed['image'])) {
        echo "Изображение: {$feed['image']}\n";
    }
    if (!empty($feed['copyright'])) {
        echo "Copyright: {$feed['copyright']}\n";
    }
    if (!empty($feed['generator'])) {
        echo "Генератор: {$feed['generator']}\n";
    }
    
    echo "Количество элементов: " . count($feed['items']) . "\n\n";
    
    // Вывод первых 3 элементов
    echo "Первые 3 элемента:\n";
    $itemsToShow = array_slice($feed['items'], 0, 3);
    
    foreach ($itemsToShow as $index => $item) {
        $itemNumber = $index + 1;
        echo "\n--- Элемент #{$itemNumber} ---\n";
        echo "ID: {$item['id']}\n";
        echo "Заголовок: {$item['title']}\n";
        echo "Ссылка: {$item['link']}\n";
        
        // Используем полный контент, если доступен
        $description = !empty($item['content']) 
            ? mb_substr(strip_tags($item['content']), 0, 100)
            : mb_substr(strip_tags($item['description']), 0, 100);
        echo "Описание: {$description}...\n";
        
        if ($item['published_at'] !== null) {
            echo "Дата публикации: {$item['published_at']->format('Y-m-d H:i:s')}\n";
        }
        
        if ($item['author'] !== '') {
            echo "Автор: {$item['author']}\n";
        }
        
        if (!empty($item['categories'])) {
            echo "Категории: " . implode(', ', $item['categories']) . "\n";
        }
        
        // Показываем медиа вложения
        if (!empty($item['enclosures'])) {
            echo "Вложения:\n";
            foreach ($item['enclosures'] as $enclosure) {
                echo "  - {$enclosure['type']}: {$enclosure['url']}\n";
            }
        }
    }
    
} catch (RssValidationException $e) {
    echo "Ошибка валидации: {$e->getMessage()}\n";
} catch (RssException $e) {
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
    } catch (RssValidationException $e) {
        echo "  ✗ Ошибка валидации: {$e->getMessage()}\n";
    } catch (RssException $e) {
        echo "  ✗ Ошибка RSS: {$e->getMessage()}\n";
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
        echo "  ID: {$firstItem['id']}\n";
        echo "  Заголовок: {$firstItem['title']}\n";
        echo "  Ссылка: {$firstItem['link']}\n";
        
        if ($firstItem['published_at'] !== null) {
            echo "  Дата: {$firstItem['published_at']->format('Y-m-d H:i:s')}\n";
        }
    }
    
} catch (RssException $e) {
    echo "Ошибка загрузки Atom ленты: {$e->getMessage()}\n";
} catch (Exception $e) {
    echo "Критическая ошибка: {$e->getMessage()}\n";
}

echo "\n";

// Пример 6: Обработка несуществующей ленты
echo "6. Обработка несуществующей ленты (404 ошибка):\n";
try {
    $rss->fetch('https://example.com/nonexistent-feed.xml');
} catch (RssException $e) {
    echo "  ✗ Ожидаемая ошибка: {$e->getMessage()}\n";
}

echo "\n";

// Пример 7: Работа с кастомными настройками (быстрая загрузка)
echo "7. RSS загрузчик с максимальной производительностью:\n";
$fastRss = new Rss([
    'timeout' => 5,
    'max_content_size' => 1048576, // 1 MB - маленький лимит для быстрой загрузки
    'enable_cache' => false,
    'enable_sanitization' => false, // Отключаем для скорости (не рекомендуется в production)
]);
echo "Быстрый RSS загрузчик создан (таймаут 5 сек, лимит 1 MB, без кеша)\n\n";

// Пример 8: Демонстрация кеширования
echo "8. Демонстрация работы кеширования:\n";
$cachedRss = new Rss([
    'cache_directory' => __DIR__ . '/cache',
    'cache_duration' => 60, // 1 минута для демонстрации
    'enable_cache' => true,
], $logger);

try {
    echo "Первая загрузка (будет закеширована)...\n";
    $startTime = microtime(true);
    $feed1 = $cachedRss->fetch('https://habr.com/ru/rss/all/all/');
    $time1 = microtime(true) - $startTime;
    echo "Время загрузки: " . round($time1, 3) . " сек\n";
    
    echo "\nВторая загрузка (из кеша)...\n";
    $startTime = microtime(true);
    $feed2 = $cachedRss->fetch('https://habr.com/ru/rss/all/all/');
    $time2 = microtime(true) - $startTime;
    echo "Время загрузки: " . round($time2, 3) . " сек\n";
    
    $speedup = $time1 / max($time2, 0.001);
    echo "\nУскорение: " . round($speedup, 1) . "x\n";
    
} catch (Exception $e) {
    echo "Ошибка: {$e->getMessage()}\n";
}

echo "\n";

// Пример 9: Работа с медиа контентом
echo "9. Извлечение медиа вложений из подкаста:\n";
echo "(Для демонстрации используем пример, если лента содержит вложения)\n";
try {
    // Здесь можно использовать URL подкаста
    echo "Пропущено (требуется URL подкаста с enclosures)\n";
} catch (Exception $e) {
    echo "Ошибка: {$e->getMessage()}\n";
}

echo "\n";

// Пример 10: Обработка всех типов исключений
echo "10. Полная обработка всех типов исключений:\n";
try {
    $feed = $rss->fetch('https://example.com/might-fail.xml');
    echo "Лента успешно загружена\n";
} catch (RssValidationException $e) {
    // Ошибка валидации URL
    echo "  ✗ Валидация: {$e->getMessage()}\n";
    echo "    Код: {$e->getCode()}\n";
} catch (RssException $e) {
    // Ошибка RSS (HTTP, парсинг, и т.д.)
    echo "  ✗ RSS: {$e->getMessage()}\n";
    echo "    Код: {$e->getCode()}\n";
    if ($e->getPrevious()) {
        echo "    Причина: {$e->getPrevious()->getMessage()}\n";
    }
} catch (Exception $e) {
    // Критическая непредвиденная ошибка
    echo "  ✗ Критическая: {$e->getMessage()}\n";
    echo "    Класс: " . get_class($e) . "\n";
}

echo "\n=== Примеры завершены ===\n";
echo "\nПроверьте директорию logs/ для просмотра логов\n";
echo "Проверьте директорию cache/ для просмотра кешированных данных\n";

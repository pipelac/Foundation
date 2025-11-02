<?php

/**
 * Демонстрация парсинга RSS/Atom без БД
 * 
 * Показывает как работает RawItem::fromSimplePieItem()
 * для извлечения и нормализации данных из RSS/Atom лент
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Rss2Tlg\DTO\RawItem;
use SimplePie\SimplePie;

$feedUrl = $argv[1] ?? 'https://news.ycombinator.com/rss';

echo "=== RSS/Atom Parsing Demo ===\n\n";
echo "URL: {$feedUrl}\n\n";

try {
    // Создание временной директории для кеша
    $cacheDir = sys_get_temp_dir() . '/rss2tlg_demo_' . uniqid();
    mkdir($cacheDir, 0755, true);
    
    echo "Загрузка и парсинг ленты...\n";
    
    // Инициализация SimplePie
    $feed = new SimplePie();
    $feed->set_feed_url($feedUrl);
    $feed->set_cache_location($cacheDir);
    $feed->enable_cache(false); // Отключаем кеш для демо
    $feed->set_timeout(30);
    $feed->enable_order_by_date(true);
    
    // Инициализация
    $success = $feed->init();
    
    if (!$success) {
        throw new Exception('Ошибка инициализации SimplePie: ' . ($feed->error() ?? 'Unknown'));
    }
    
    echo "✓ Лента загружена\n\n";
    
    // Получаем информацию о ленте
    echo str_repeat('=', 70) . "\n";
    echo "ИНФОРМАЦИЯ О ЛЕНТЕ\n";
    echo str_repeat('=', 70) . "\n\n";
    
    echo "Заголовок: " . ($feed->get_title() ?? 'N/A') . "\n";
    echo "Описание: " . ($feed->get_description() ?? 'N/A') . "\n";
    echo "Ссылка: " . ($feed->get_link() ?? 'N/A') . "\n";
    echo "Язык: " . ($feed->get_language() ?? 'N/A') . "\n";
    
    $type = $feed->get_type();
    $feedType = 'unknown';
    if ($type !== null) {
        if ($type & SimplePie::TYPE_RSS_ALL) {
            $feedType = 'RSS';
        } elseif ($type & SimplePie::TYPE_ATOM_ALL) {
            $feedType = 'Atom';
        }
    }
    echo "Тип: " . $feedType . "\n\n";
    
    // Получаем элементы
    $items = $feed->get_items();
    
    if ($items === null || empty($items)) {
        echo "Элементов не найдено\n";
        exit(0);
    }
    
    $itemCount = count($items);
    echo "Всего элементов: {$itemCount}\n\n";
    
    // Ограничим вывод до 5 элементов
    $displayCount = min(5, $itemCount);
    
    echo str_repeat('=', 70) . "\n";
    echo "ЭЛЕМЕНТЫ (первые {$displayCount} из {$itemCount})\n";
    echo str_repeat('=', 70) . "\n\n";
    
    $validCount = 0;
    $invalidCount = 0;
    
    foreach (array_slice($items, 0, $displayCount) as $index => $simplePieItem) {
        echo sprintf("[%d] %s\n", $index + 1, str_repeat('-', 66)) . "\n\n";
        
        // Конвертируем SimplePie\Item в RawItem
        $rawItem = RawItem::fromSimplePieItem($simplePieItem);
        
        // Выводим поля
        echo "Заголовок:\n";
        echo "  " . ($rawItem->title ?? 'N/A') . "\n\n";
        
        echo "Ссылка:\n";
        echo "  " . ($rawItem->link ?? 'N/A') . "\n\n";
        
        if ($rawItem->guid !== null) {
            echo "GUID:\n";
            echo "  " . $rawItem->guid . "\n\n";
        }
        
        if ($rawItem->summary !== null) {
            $summary = mb_substr($rawItem->summary, 0, 150);
            echo "Описание:\n";
            echo "  " . $summary;
            if (mb_strlen($rawItem->summary) > 150) {
                echo "...";
            }
            echo "\n\n";
        }
        
        if (!empty($rawItem->authors)) {
            echo "Авторы:\n";
            foreach ($rawItem->authors as $author) {
                echo "  - " . $author . "\n";
            }
            echo "\n";
        }
        
        if (!empty($rawItem->categories)) {
            echo "Категории:\n";
            foreach (array_slice($rawItem->categories, 0, 5) as $category) {
                echo "  - " . $category . "\n";
            }
            if (count($rawItem->categories) > 5) {
                echo "  ... и ещё " . (count($rawItem->categories) - 5) . "\n";
            }
            echo "\n";
        }
        
        if ($rawItem->enclosure !== null) {
            echo "Вложение:\n";
            echo "  URL: " . $rawItem->enclosure['url'] . "\n";
            echo "  Type: " . $rawItem->enclosure['type'] . "\n";
            echo "  Size: " . number_format($rawItem->enclosure['length']) . " bytes\n\n";
        }
        
        if ($rawItem->pubDate !== null) {
            echo "Дата публикации:\n";
            echo "  " . date('Y-m-d H:i:s', $rawItem->pubDate) . " UTC\n\n";
        }
        
        echo "Content Hash (для дедупликации):\n";
        echo "  " . $rawItem->contentHash . "\n\n";
        
        echo "Валидность:\n";
        if ($rawItem->isValid()) {
            echo "  ✓ Valid\n";
            $validCount++;
        } else {
            echo "  ✗ Invalid (отсутствуют обязательные поля)\n";
            $invalidCount++;
        }
        
        echo "\n";
    }
    
    // Статистика
    echo str_repeat('=', 70) . "\n";
    echo "СТАТИСТИКА\n";
    echo str_repeat('=', 70) . "\n\n";
    
    // Обработаем все элементы для статистики
    $allRawItems = array_map(
        fn($item) => RawItem::fromSimplePieItem($item),
        $items
    );
    
    $totalValid = count(array_filter($allRawItems, fn($item) => $item->isValid()));
    $totalInvalid = count($allRawItems) - $totalValid;
    
    $totalWithAuthors = count(array_filter($allRawItems, fn($item) => !empty($item->authors)));
    $totalWithCategories = count(array_filter($allRawItems, fn($item) => !empty($item->categories)));
    $totalWithEnclosures = count(array_filter($allRawItems, fn($item) => $item->enclosure !== null));
    
    echo "Всего элементов:        {$itemCount}\n";
    echo "Валидных элементов:     {$totalValid}\n";
    echo "Невалидных элементов:   {$totalInvalid}\n";
    echo "С авторами:             {$totalWithAuthors}\n";
    echo "С категориями:          {$totalWithCategories}\n";
    echo "С вложениями:           {$totalWithEnclosures}\n";
    
    // Проверка уникальности хэшей
    $hashes = array_map(fn($item) => $item->contentHash, $allRawItems);
    $uniqueHashes = array_unique($hashes);
    $duplicates = count($hashes) - count($uniqueHashes);
    
    echo "Уникальных хэшей:       " . count($uniqueHashes) . "\n";
    echo "Возможных дубликатов:   {$duplicates}\n";
    
    echo "\n✓ Парсинг завершён успешно\n";
    
    // Очистка
    rmdir($cacheDir);
    
} catch (Exception $e) {
    echo "\n✗ ОШИБКА: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

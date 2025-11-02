<?php

require_once __DIR__ . '/../autoload.php';

use App\Component\MySQL;
use App\Component\Logger;

$logger = new Logger([
    'directory' => 'logs',
    'file_name' => 'check_media.log',
    'enabled' => false,
]);

$db = new MySQL([
    'host' => 'localhost',
    'database' => 'rss2tlg_test',
    'username' => 'root',
    'password' => '',
], $logger);

echo "=== Проверка медиа-ресурсов в БД ===\n\n";

// Проверка enclosures из RSS
$items = $db->query("
    SELECT id, feed_id, title, enclosures, extracted_images 
    FROM rss2tlg_items 
    WHERE enclosures IS NOT NULL OR extracted_images IS NOT NULL 
    LIMIT 5
");

echo "Новостей с медиа: " . count($items) . "\n\n";

foreach ($items as $item) {
    echo "ID: {$item['id']} | Feed: {$item['feed_id']}\n";
    echo "Title: " . mb_substr($item['title'], 0, 60) . "...\n";
    
    if ($item['enclosures']) {
        $enc = json_decode($item['enclosures'], true);
        echo "Enclosures: " . count($enc ?? []) . " шт.\n";
        if (!empty($enc)) {
            foreach ($enc as $e) {
                echo "  - Type: " . ($e['type'] ?? 'unknown') . "\n";
                echo "    URL: " . mb_substr($e['url'] ?? '', 0, 80) . "\n";
            }
        }
    }
    
    if ($item['extracted_images']) {
        $imgs = json_decode($item['extracted_images'], true);
        echo "Extracted Images: " . count($imgs ?? []) . " шт.\n";
        if (!empty($imgs) && is_array($imgs)) {
            foreach (array_slice($imgs, 0, 2) as $img) {
                if (is_array($img)) {
                    echo "  - " . mb_substr($img['url'] ?? $img['src'] ?? '', 0, 80) . "\n";
                }
            }
        }
    }
    
    echo "\n";
}

// Статистика по типам медиа
$stats = $db->query("
    SELECT 
        COUNT(*) as total_items,
        SUM(CASE WHEN enclosures IS NOT NULL THEN 1 ELSE 0 END) as with_enclosures,
        SUM(CASE WHEN extracted_images IS NOT NULL THEN 1 ELSE 0 END) as with_extracted_images
    FROM rss2tlg_items
");

echo "=== Статистика медиа ===\n";
echo "Всего новостей: " . $stats[0]['total_items'] . "\n";
echo "С enclosures (RSS): " . $stats[0]['with_enclosures'] . "\n";
echo "С extracted images: " . $stats[0]['with_extracted_images'] . "\n";

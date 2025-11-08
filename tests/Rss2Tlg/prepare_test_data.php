<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Component\Logger;
use App\Component\MySQL;

// ============================================================================
// PREPARE TEST DATA: Загружаем тестовые новости из RSS
// ============================================================================

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════╗\n";
echo "║           ПОДГОТОВКА ТЕСТОВЫХ ДАННЫХ ИЗ RSS                      ║\n";
echo "╚═══════════════════════════════════════════════════════════════════╝\n";
echo "\n";

try {
    // Настраиваем логирование
    $loggerConfig = [
        'directory' => __DIR__ . '/../../logs',
        'file_name' => 'prepare_data_' . date('Y-m-d') . '.log',
        'min_level' => 'debug',
    ];
    $logger = new Logger($loggerConfig);
    
    // Подключаемся к БД
    echo "🗄️  Подключение к MariaDB...\n";
    $dbConfig = [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'rss2tlg',
        'username' => 'rss2tlg_user',
        'password' => 'rss2tlg_password_2024',
        'charset' => 'utf8mb4',
    ];
    $db = new MySQL($dbConfig, $logger);
    echo "✅ Подключение установлено\n\n";
    
    // RSS ленты для тестирования
    $feeds = [
        [
            'id' => 1,
            'url' => 'https://www.engadget.com/rss.xml',
            'name' => 'Engadget',
        ],
        [
            'id' => 2,
            'url' => 'https://feeds.bbci.co.uk/news/technology/rss.xml',
            'name' => 'BBC Technology',
        ],
    ];
    
    $totalItems = 0;
    
    foreach ($feeds as $feed) {
        echo "📡 Загружаем RSS: {$feed['name']}\n";
        echo "   URL: {$feed['url']}\n";
        
        // Загружаем RSS
        $rssContent = @file_get_contents($feed['url']);
        if (!$rssContent) {
            echo "⚠️  Ошибка загрузки RSS\n\n";
            continue;
        }
        
        // Парсим XML
        $xml = @simplexml_load_string($rssContent);
        if (!$xml) {
            echo "⚠️  Ошибка парсинга XML\n\n";
            continue;
        }
        
        $items = $xml->channel->item ?? $xml->entry ?? [];
        $count = 0;
        $limit = 5; // Ограничиваем 5 новостями на источник
        
        foreach ($items as $item) {
            if ($count >= $limit) {
                break;
            }
            
            // Извлекаем данные
            $title = (string)($item->title ?? '');
            $link = (string)($item->link ?? '');
            $description = (string)($item->description ?? '');
            $content = (string)($item->children('content', true)->encoded ?? '');
            $pubDate = (string)($item->pubDate ?? $item->published ?? date('Y-m-d H:i:s'));
            
            if (empty($title) || empty($link)) {
                continue;
            }
            
            // Преобразуем дату
            $timestamp = strtotime($pubDate);
            if ($timestamp === false) {
                $timestamp = time();
            }
            $pubDateFormatted = date('Y-m-d H:i:s', $timestamp);
            
            // Вставляем в БД
            $contentHash = md5($title . $description . $content);
            
            $query = "
                INSERT INTO rss2tlg_items (
                    feed_id, title, link, description, content, pub_date, 
                    guid, content_hash, created_at
                )
                VALUES (
                    :feed_id, :title, :link, :description, :content, :pub_date,
                    :guid, :content_hash, NOW()
                )
                ON DUPLICATE KEY UPDATE
                    title = VALUES(title),
                    description = VALUES(description),
                    content = VALUES(content),
                    content_hash = VALUES(content_hash),
                    updated_at = NOW()
            ";
            
            $params = [
                'feed_id' => $feed['id'],
                'title' => $title,
                'link' => $link,
                'description' => $description,
                'content' => $content,
                'pub_date' => $pubDateFormatted,
                'guid' => md5($link),
                'content_hash' => $contentHash,
            ];
            
            try {
                $db->execute($query, $params);
                $count++;
                $totalItems++;
            } catch (Exception $e) {
                // Игнорируем дубликаты
                if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                    throw $e;
                }
            }
        }
        
        echo "✅ Загружено: {$count} новостей\n\n";
    }
    
    echo "╔═══════════════════════════════════════════════════════════════════╗\n";
    echo "║                          ИТОГО                                    ║\n";
    echo "╚═══════════════════════════════════════════════════════════════════╝\n\n";
    echo "✅ Всего загружено: {$totalItems} новостей\n";
    echo "🗄️  Данные готовы для тестирования\n\n";
    
    // Показываем статистику
    $stats = $db->queryOne("SELECT COUNT(*) as total FROM rss2tlg_items");
    echo "📊 Всего новостей в БД: {$stats['total']}\n\n";
    
} catch (Exception $e) {
    echo "\n❌ ОШИБКА: {$e->getMessage()}\n";
    echo "Trace: {$e->getTraceAsString()}\n\n";
    exit(1);
}

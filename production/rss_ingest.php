#!/usr/bin/env php
<?php
/**
 * RSS Ingest Production Script
 * 
 * Собирает новости из RSS источников и сохраняет в БД.
 * Запускается по cron каждую минуту.
 * 
 * @package Rss2Tlg
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use App\Component\Logger;
use App\Component\MySQL;
use App\Config\ConfigLoader;

// ============================================================================
// КОНСТАНТЫ
// ============================================================================

const SCRIPT_NAME = 'RSS Ingest';
const SCRIPT_VERSION = '1.0.0';
const LOG_PREFIX = '[RSS_INGEST]';

// ============================================================================
// ГЛАВНАЯ ФУНКЦИЯ
// ============================================================================

function main(): void
{
    $startTime = microtime(true);
    $scriptStart = date('Y-m-d H:i:s');
    
    echo "\n";
    echo "╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║           RSS INGEST PRODUCTION SCRIPT v1.0.0                 ║\n";
    echo "╚═══════════════════════════════════════════════════════════════╝\n";
    echo "🕐 Start: {$scriptStart}\n\n";
    
    try {
        // Инициализация
        $config = loadConfiguration();
        $logger = initLogger($config);
        $db = initDatabase($config, $logger);
        
        $logger->info(LOG_PREFIX . ' Script started', [
            'version' => SCRIPT_VERSION,
            'pid' => getmypid()
        ]);
        
        // Автоинициализация БД из feeds.json если активных лент нет
        syncFeedsFromConfig($db, $logger);
        
        // Получение списка активных источников
        $feeds = getActiveFeeds($db, $logger);
        
        if (empty($feeds)) {
            $logger->warning(LOG_PREFIX . ' No active feeds found');
            echo "⚠️  Нет активных источников\n";
            return;
        }
        
        echo "📊 Найдено источников: " . count($feeds) . "\n\n";
        $logger->info(LOG_PREFIX . ' Active feeds loaded', ['count' => count($feeds)]);
        
        // Статистика
        $stats = [
            'feeds_processed' => 0,
            'feeds_success' => 0,
            'feeds_failed' => 0,
            'items_total' => 0,
            'items_new' => 0,
            'items_duplicates' => 0,
            'errors' => []
        ];
        
        // Обработка каждого источника
        foreach ($feeds as $feed) {
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            echo "📡 Источник: {$feed['name']}\n";
            echo "🔗 URL: {$feed['feed_url']}\n";
            
            $stats['feeds_processed']++;
            
            try {
                $result = processFeed($feed, $db, $logger);
                
                if ($result['success']) {
                    $stats['feeds_success']++;
                    $stats['items_total'] += $result['items_total'];
                    $stats['items_new'] += $result['items_new'];
                    $stats['items_duplicates'] += $result['items_duplicates'];
                    
                    echo "✅ Успешно обработан\n";
                    echo "   📥 Получено: {$result['items_total']}\n";
                    echo "   ✨ Новых: {$result['items_new']}\n";
                    echo "   🔁 Дубликатов: {$result['items_duplicates']}\n";
                } else {
                    $stats['feeds_failed']++;
                    $stats['errors'][] = [
                        'feed' => $feed['name'],
                        'error' => $result['error']
                    ];
                    
                    echo "❌ Ошибка: {$result['error']}\n";
                }
                
            } catch (\Exception $e) {
                $stats['feeds_failed']++;
                $stats['errors'][] = [
                    'feed' => $feed['name'],
                    'error' => $e->getMessage()
                ];
                
                $logger->error(LOG_PREFIX . ' Feed processing exception', [
                    'feed_id' => $feed['id'],
                    'feed_name' => $feed['name'],
                    'exception' => $e->getMessage()
                ]);
                
                echo "❌ Исключение: {$e->getMessage()}\n";
            }
            
            echo "\n";
        }
        
        // Итоговая статистика
        $executionTime = round(microtime(true) - $startTime, 2);
        
        echo "╔═══════════════════════════════════════════════════════════════╗\n";
        echo "║                    ИТОГОВАЯ СТАТИСТИКА                        ║\n";
        echo "╚═══════════════════════════════════════════════════════════════╝\n";
        echo "📊 Источников обработано: {$stats['feeds_processed']}\n";
        echo "   ✅ Успешно: {$stats['feeds_success']}\n";
        echo "   ❌ Ошибок: {$stats['feeds_failed']}\n";
        echo "\n";
        echo "📰 Всего элементов получено: {$stats['items_total']}\n";
        echo "   ✨ Новых: {$stats['items_new']}\n";
        echo "   🔁 Дубликатов: {$stats['items_duplicates']}\n";
        echo "\n";
        echo "⏱️  Время выполнения: {$executionTime} сек\n";
        echo "🕐 Завершено: " . date('Y-m-d H:i:s') . "\n\n";
        
        $logger->info(LOG_PREFIX . ' Script completed', [
            'stats' => $stats,
            'execution_time' => $executionTime
        ]);
        
    } catch (\Exception $e) {
        $error = "Fatal error: {$e->getMessage()}";
        echo "\n❌ КРИТИЧЕСКАЯ ОШИБКА: {$error}\n\n";
        
        if (isset($logger)) {
            $logger->error(LOG_PREFIX . ' Fatal error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        exit(1);
    }
}

// ============================================================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// ============================================================================

/**
 * Загрузка конфигурации
 */
function loadConfiguration(): array
{
    $configDir = __DIR__ . '/configs';
    
    // Загрузка основного конфига
    $mainConfigPath = $configDir . '/main.json';
    if (!file_exists($mainConfigPath)) {
        throw new \RuntimeException("Main config not found: {$mainConfigPath}");
    }
    
    $mainConfig = json_decode(file_get_contents($mainConfigPath), true);
    if (!$mainConfig) {
        throw new \RuntimeException("Failed to parse main config");
    }
    
    return $mainConfig;
}

/**
 * Инициализация логгера
 */
function initLogger(array $config): Logger
{
    $logConfig = [
        'directory' => $config['log_directory'] ?? __DIR__ . '/../logs',
        'file_name' => 'rss_ingest.log',
        'min_level' => $config['log_level'] ?? 'info'
    ];
    
    return new Logger($logConfig);
}

/**
 * Инициализация подключения к БД
 */
function initDatabase(array $config, Logger $logger): MySQL
{
    $configPath = __DIR__ . '/configs/database.json';
    if (!file_exists($configPath)) {
        throw new \RuntimeException("Database config not found: {$configPath}");
    }
    
    $dbConfig = json_decode(file_get_contents($configPath), true);
    if (!$dbConfig) {
        throw new \RuntimeException("Failed to parse database config");
    }
    
    return new MySQL($dbConfig, $logger);
}

/**
 * Синхронизация feeds из конфига в БД
 */
function syncFeedsFromConfig(MySQL $db, Logger $logger): void
{
    // Проверка наличия активных лент в БД
    $activeFeedsCount = $db->queryOne("SELECT COUNT(*) as cnt FROM rss2tlg_feeds WHERE enabled = 1");
    
    if ($activeFeedsCount && $activeFeedsCount['cnt'] > 0) {
        $logger->info(LOG_PREFIX . ' Active feeds already exist in DB', ['count' => $activeFeedsCount['cnt']]);
        echo "✅ Активных лент в БД: {$activeFeedsCount['cnt']}\n\n";
        return;
    }
    
    // Загрузка feeds.json
    $feedsConfigPath = __DIR__ . '/configs/feeds.json';
    if (!file_exists($feedsConfigPath)) {
        $logger->warning(LOG_PREFIX . ' feeds.json not found', ['path' => $feedsConfigPath]);
        echo "⚠️  Файл feeds.json не найден: {$feedsConfigPath}\n\n";
        return;
    }
    
    $feedsConfig = json_decode(file_get_contents($feedsConfigPath), true);
    if (!$feedsConfig || !isset($feedsConfig['feeds']) || !is_array($feedsConfig['feeds'])) {
        $logger->warning(LOG_PREFIX . ' Invalid feeds.json format');
        echo "⚠️  Некорректный формат feeds.json\n\n";
        return;
    }
    
    echo "🔄 Синхронизация лент из feeds.json...\n";
    $logger->info(LOG_PREFIX . ' Starting feeds synchronization from config', [
        'feeds_count' => count($feedsConfig['feeds'])
    ]);
    
    $syncedCount = 0;
    $skippedCount = 0;
    
    foreach ($feedsConfig['feeds'] as $feed) {
        if (!isset($feed['name']) || !isset($feed['feed_url'])) {
            $logger->warning(LOG_PREFIX . ' Invalid feed config', ['feed' => $feed]);
            $skippedCount++;
            continue;
        }
        
        // Проверка, существует ли уже такой feed_url
        $existing = $db->queryOne(
            "SELECT id, enabled FROM rss2tlg_feeds WHERE feed_url = ? LIMIT 1",
            [$feed['feed_url']]
        );
        
        // Преобразование boolean в TINYINT
        $enabled = isset($feed['enabled']) ? (int)(bool)$feed['enabled'] : 1;
        
        if ($existing) {
            // Обновление существующей записи
            $db->execute(
                "UPDATE rss2tlg_feeds SET name = ?, website_url = ?, enabled = ?, updated_at = NOW() WHERE id = ?",
                [
                    $feed['name'],
                    $feed['website_url'] ?? null,
                    $enabled,
                    $existing['id']
                ]
            );
            
            $logger->info(LOG_PREFIX . ' Feed updated', [
                'id' => $existing['id'],
                'name' => $feed['name'],
                'enabled' => $enabled
            ]);
            
            echo "   ✏️  Обновлен: {$feed['name']} (enabled: {$enabled})\n";
        } else {
            // Вставка новой записи
            $db->execute(
                "INSERT INTO rss2tlg_feeds (name, feed_url, website_url, enabled) VALUES (?, ?, ?, ?)",
                [
                    $feed['name'],
                    $feed['feed_url'],
                    $feed['website_url'] ?? null,
                    $enabled
                ]
            );
            
            $insertId = $db->getLastInsertId();
            
            $logger->info(LOG_PREFIX . ' Feed inserted', [
                'id' => $insertId,
                'name' => $feed['name'],
                'enabled' => $enabled
            ]);
            
            echo "   ✅ Добавлен: {$feed['name']} (enabled: {$enabled})\n";
        }
        
        $syncedCount++;
    }
    
    echo "✅ Синхронизация завершена: обработано {$syncedCount}, пропущено {$skippedCount}\n\n";
    
    $logger->info(LOG_PREFIX . ' Feeds synchronization completed', [
        'synced' => $syncedCount,
        'skipped' => $skippedCount
    ]);
}

/**
 * Получение списка активных источников
 */
function getActiveFeeds(MySQL $db, Logger $logger): array
{
    $sql = "SELECT id, name, feed_url, website_url 
            FROM rss2tlg_feeds 
            WHERE enabled = 1 
            ORDER BY id";
    
    return $db->query($sql);
}

/**
 * Обработка одного RSS источника
 */
function processFeed(array $feed, MySQL $db, Logger $logger): array
{
    $result = [
        'success' => false,
        'items_total' => 0,
        'items_new' => 0,
        'items_duplicates' => 0,
        'error' => null
    ];
    
    try {
        // Скачивание RSS
        $rssContent = fetchRSS($feed['feed_url'], $logger);
        
        if (!$rssContent) {
            $result['error'] = 'Failed to fetch RSS content';
            return $result;
        }
        
        // Парсинг RSS
        $items = parseRSS($rssContent, $feed, $logger);
        
        if (empty($items)) {
            $result['error'] = 'No items found in RSS';
            return $result;
        }
        
        $result['items_total'] = count($items);
        
        // Сохранение элементов
        foreach ($items as $item) {
            $saved = saveItem($item, $feed['id'], $db, $logger);
            
            if ($saved) {
                $result['items_new']++;
            } else {
                $result['items_duplicates']++;
            }
        }
        
        // Обновление состояния фида
        updateFeedState($feed['id'], $feed['feed_url'], true, null, $db, $logger);
        
        $result['success'] = true;
        
        $logger->info(LOG_PREFIX . ' Feed processed successfully', [
            'feed_id' => $feed['id'],
            'feed_name' => $feed['name'],
            'items_total' => $result['items_total'],
            'items_new' => $result['items_new'],
            'items_duplicates' => $result['items_duplicates']
        ]);
        
    } catch (\Exception $e) {
        $result['error'] = $e->getMessage();
        
        $logger->error(LOG_PREFIX . ' Feed processing error', [
            'feed_id' => $feed['id'],
            'feed_name' => $feed['name'],
            'error' => $e->getMessage()
        ]);
        
        // Обновление состояния фида с ошибкой
        updateFeedState($feed['id'], $feed['feed_url'], false, $e->getMessage(), $db, $logger);
    }
    
    return $result;
}

/**
 * Скачивание RSS контента
 */
function fetchRSS(string $url, Logger $logger): ?string
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (RSS2TLG Bot/1.0)',
        CURLOPT_ENCODING => 'gzip, deflate',
    ]);
    
    $content = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($content === false || $httpCode !== 200) {
        $logger->warning(LOG_PREFIX . ' RSS fetch failed', [
            'url' => $url,
            'http_code' => $httpCode,
            'error' => $error
        ]);
        return null;
    }
    
    return $content;
}

/**
 * Парсинг RSS контента
 */
function parseRSS(string $content, array $feed, Logger $logger): array
{
    // Подавление XML ошибок
    libxml_use_internal_errors(true);
    
    $xml = simplexml_load_string($content);
    
    if ($xml === false) {
        $errors = libxml_get_errors();
        libxml_clear_errors();
        
        $logger->error(LOG_PREFIX . ' XML parsing error', [
            'feed_id' => $feed['id'],
            'errors' => array_map(fn($e) => $e->message, $errors)
        ]);
        
        return [];
    }
    
    $items = [];
    
    // RSS 2.0
    if (isset($xml->channel->item)) {
        foreach ($xml->channel->item as $xmlItem) {
            $items[] = parseRSSItem($xmlItem);
        }
    }
    // Atom
    elseif (isset($xml->entry)) {
        foreach ($xml->entry as $xmlItem) {
            $items[] = parseAtomItem($xmlItem);
        }
    }
    
    return $items;
}

/**
 * Парсинг RSS item
 */
function parseRSSItem(\SimpleXMLElement $item): array
{
    $namespaces = $item->getNamespaces(true);
    
    // Извлечение content:encoded если есть
    $content = null;
    if (isset($namespaces['content'])) {
        $contentNs = $item->children($namespaces['content']);
        if (isset($contentNs->encoded)) {
            $content = (string)$contentNs->encoded;
        }
    }
    
    // Извлечение медиа из media:content
    $enclosures = [];
    if (isset($namespaces['media'])) {
        $mediaNs = $item->children($namespaces['media']);
        if (isset($mediaNs->content)) {
            foreach ($mediaNs->content as $mediaContent) {
                $attrs = $mediaContent->attributes();
                $enclosures[] = [
                    'url' => (string)$attrs['url'],
                    'type' => (string)$attrs['type'],
                    'medium' => (string)$attrs['medium']
                ];
            }
        }
    }
    
    // Стандартные enclosure
    if (isset($item->enclosure)) {
        foreach ($item->enclosure as $enclosure) {
            $attrs = $enclosure->attributes();
            $enclosures[] = [
                'url' => (string)$attrs['url'],
                'type' => (string)$attrs['type'],
                'length' => (int)$attrs['length']
            ];
        }
    }
    
    // Категории
    $categories = [];
    if (isset($item->category)) {
        foreach ($item->category as $category) {
            $categories[] = (string)$category;
        }
    }
    
    return [
        'guid' => (string)($item->guid ?? $item->link),
        'title' => (string)$item->title,
        'link' => (string)$item->link,
        'description' => (string)$item->description,
        'content' => $content,
        'pub_date' => (string)$item->pubDate,
        'author' => (string)($item->author ?? $item->creator ?? null),
        'categories' => $categories,
        'enclosures' => $enclosures,
    ];
}

/**
 * Парсинг Atom entry
 */
function parseAtomItem(\SimpleXMLElement $entry): array
{
    $namespaces = $entry->getNamespaces(true);
    
    // Link
    $link = '';
    if (isset($entry->link)) {
        foreach ($entry->link as $linkEl) {
            $attrs = $linkEl->attributes();
            if (!isset($attrs['rel']) || $attrs['rel'] == 'alternate') {
                $link = (string)$attrs['href'];
                break;
            }
        }
    }
    
    // Content
    $content = null;
    if (isset($entry->content)) {
        $content = (string)$entry->content;
    }
    
    // Categories
    $categories = [];
    if (isset($entry->category)) {
        foreach ($entry->category as $category) {
            $attrs = $category->attributes();
            $categories[] = (string)($attrs['term'] ?? $attrs['label'] ?? '');
        }
    }
    
    return [
        'guid' => (string)$entry->id,
        'title' => (string)$entry->title,
        'link' => $link,
        'description' => (string)($entry->summary ?? ''),
        'content' => $content,
        'pub_date' => (string)($entry->published ?? $entry->updated),
        'author' => (string)($entry->author->name ?? null),
        'categories' => $categories,
        'enclosures' => [],
    ];
}

/**
 * Сохранение элемента в БД
 * 
 * @return bool true если элемент новый, false если дубликат
 */
function saveItem(array $item, int $feedId, MySQL $db, Logger $logger): bool
{
    // Генерация content_hash для дедупликации
    $contentForHash = $item['title'] . '|' . $item['link'];
    $contentHash = md5($contentForHash);
    
    // Проверка на дубликат
    $existsSql = "SELECT id FROM rss2tlg_items 
                  WHERE feed_id = ? AND content_hash = ? 
                  LIMIT 1";
    
    $existing = $db->queryOne($existsSql, [$feedId, $contentHash]);
    
    if ($existing) {
        return false; // Дубликат
    }
    
    // Парсинг даты публикации
    $pubDate = null;
    if (!empty($item['pub_date'])) {
        try {
            $date = new \DateTime($item['pub_date']);
            $pubDate = $date->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            $logger->warning(LOG_PREFIX . ' Invalid pub_date', [
                'pub_date' => $item['pub_date'],
                'error' => $e->getMessage()
            ]);
        }
    }
    
    // Вставка
    $sql = "INSERT INTO rss2tlg_items 
            (feed_id, content_hash, guid, title, link, description, content, 
             pub_date, author, categories, enclosures, extraction_status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
    
    $params = [
        $feedId,
        $contentHash,
        $item['guid'],
        $item['title'],
        $item['link'],
        $item['description'],
        $item['content'],
        $pubDate,
        $item['author'],
        !empty($item['categories']) ? json_encode($item['categories'], JSON_UNESCAPED_UNICODE) : null,
        !empty($item['enclosures']) ? json_encode($item['enclosures'], JSON_UNESCAPED_UNICODE) : null,
    ];
    
    try {
        $db->execute($sql, $params);
        return true; // Новый элемент
    } catch (\Exception $e) {
        $logger->error(LOG_PREFIX . ' Failed to save item', [
            'feed_id' => $feedId,
            'title' => $item['title'],
            'error' => $e->getMessage()
        ]);
        return false;
    }
}

/**
 * Обновление состояния источника
 */
function updateFeedState(int $feedId, string $feedUrl, bool $success, ?string $error, MySQL $db, Logger $logger): void
{
    try {
        // Проверка существования записи
        $existsSql = "SELECT id FROM rss2tlg_feed_state WHERE feed_id = ? LIMIT 1";
        $existing = $db->queryOne($existsSql, [$feedId]);
        
        if ($existing) {
            // Обновление
            if ($success) {
                $sql = "UPDATE rss2tlg_feed_state 
                        SET last_status = 200, 
                            last_error = NULL, 
                            error_count = 0, 
                            backoff_until = NULL,
                            fetched_at = NOW(),
                            updated_at = NOW()
                        WHERE feed_id = ?";
                $db->execute($sql, [$feedId]);
            } else {
                $sql = "UPDATE rss2tlg_feed_state 
                        SET last_status = 0, 
                            last_error = ?, 
                            error_count = error_count + 1,
                            fetched_at = NOW(),
                            updated_at = NOW()
                        WHERE feed_id = ?";
                $db->execute($sql, [$error, $feedId]);
            }
        } else {
            // Вставка
            $sql = "INSERT INTO rss2tlg_feed_state 
                    (feed_id, url, last_status, last_error, error_count, fetched_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
            
            $params = [
                $feedId,
                $feedUrl,
                $success ? 200 : 0,
                $error,
                $success ? 0 : 1
            ];
            
            $db->execute($sql, $params);
        }
    } catch (\Exception $e) {
        $logger->error(LOG_PREFIX . ' Failed to update feed state', [
            'feed_id' => $feedId,
            'error' => $e->getMessage()
        ]);
    }
}

// ============================================================================
// ЗАПУСК
// ============================================================================

main();

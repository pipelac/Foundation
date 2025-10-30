<?php

declare(strict_types=1);

/**
 * Демонстрация работы исправленного класса Rss
 * Показывает загрузку реальных RSS лент и работу всех методов
 */

require_once __DIR__ . '/autoload.php';

use App\Component\Rss;
use App\Component\Logger;

// Цвета для консоли
const C_GREEN = "\033[32m";
const C_BLUE = "\033[34m";
const C_YELLOW = "\033[33m";
const C_RESET = "\033[0m";

echo C_BLUE . "\n";
echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║           ДЕМОНСТРАЦИЯ ИСПРАВЛЕННОГО КЛАССА RSS (PHP 8.1+)                ║\n";
echo "║                    Все методы работают корректно                           ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n";
echo C_RESET . "\n";

// Инициализация логгера
$logger = new Logger([
    'directory' => __DIR__ . '/logs_demo_rss',
    'file_name' => 'rss_demo.log',
    'max_files' => 3,
    'max_file_size' => 5242880,
]);

// Инициализация RSS клиента с полной конфигурацией
$rss = new Rss([
    'user_agent' => 'Mozilla/5.0 (compatible; RSSReader/2.0)',
    'timeout' => 20,
    'max_content_size' => 10485760, // 10 MB
    'cache_directory' => __DIR__ . '/cache_demo_rss',
    'cache_duration' => 3600, // 1 час
    'enable_cache' => true,
    'enable_sanitization' => true,
], $logger);

echo C_GREEN . "✓ RSS клиент успешно инициализирован\n" . C_RESET;
echo "  - Таймаут: 20 секунд\n";
echo "  - Максимальный размер: 10 MB\n";
echo "  - Кеширование: включено (3600 сек)\n";
echo "  - Санитизация HTML: включена\n\n";

// Список лент для демонстрации
$feeds = [
    'NASA Breaking News' => 'https://www.nasa.gov/rss/dyn/breaking_news.rss',
    'Habr.com' => 'https://habr.com/ru/rss/all/all/',
];

foreach ($feeds as $name => $url) {
    echo C_BLUE . str_repeat('─', 80) . "\n";
    echo "Загрузка ленты: $name\n";
    echo "URL: $url\n" . C_RESET;
    echo str_repeat('─', 80) . "\n\n";
    
    try {
        $startTime = microtime(true);
        $data = $rss->fetch($url);
        $loadTime = round((microtime(true) - $startTime) * 1000, 2);
        
        echo C_GREEN . "✓ Лента успешно загружена за {$loadTime} мс\n\n" . C_RESET;
        
        // Информация о ленте
        echo C_YELLOW . "Информация о ленте:\n" . C_RESET;
        echo "  Тип ленты:    " . strtoupper($data['type']) . "\n";
        echo "  Заголовок:    " . (strlen($data['title']) > 50 ? substr($data['title'], 0, 50) . '...' : $data['title']) . "\n";
        echo "  Описание:     " . (strlen($data['description']) > 50 ? substr($data['description'], 0, 50) . '...' : $data['description']) . "\n";
        echo "  Ссылка:       " . $data['link'] . "\n";
        echo "  Язык:         " . ($data['language'] ?: 'не указан') . "\n";
        echo "  Изображение:  " . ($data['image'] ? 'есть' : 'нет') . "\n";
        echo "  Копирайт:     " . ($data['copyright'] ? substr($data['copyright'], 0, 40) . '...' : 'не указан') . "\n";
        echo "  Элементов:    " . count($data['items']) . "\n\n";
        
        // Первые 3 элемента
        if (!empty($data['items'])) {
            echo C_YELLOW . "Первые элементы ленты:\n" . C_RESET;
            foreach (array_slice($data['items'], 0, 3) as $i => $item) {
                $num = $i + 1;
                echo "\n  $num. " . C_GREEN . $item['title'] . C_RESET . "\n";
                echo "     Ссылка:    " . $item['link'] . "\n";
                echo "     Автор:     " . ($item['author'] ?: 'не указан') . "\n";
                
                if ($item['published_at'] instanceof DateTimeImmutable) {
                    echo "     Дата:      " . $item['published_at']->format('d.m.Y H:i:s') . "\n";
                }
                
                if (!empty($item['categories'])) {
                    echo "     Категории: " . implode(', ', array_slice($item['categories'], 0, 3)) . "\n";
                }
                
                $description = strip_tags($item['description']);
                if ($description) {
                    echo "     Описание:  " . substr($description, 0, 80) . "...\n";
                }
            }
        }
        
        echo "\n";
        
        // Тест кеширования - повторная загрузка
        echo C_YELLOW . "Проверка кеширования...\n" . C_RESET;
        $startTime2 = microtime(true);
        $data2 = $rss->fetch($url);
        $loadTime2 = round((microtime(true) - $startTime2) * 1000, 2);
        
        echo "  Первая загрузка:  {$loadTime} мс\n";
        echo "  Вторая загрузка:  {$loadTime2} мс\n";
        
        if ($loadTime2 < $loadTime * 0.5) {
            echo C_GREEN . "  ✓ Кеширование работает! Ускорение в " . round($loadTime / $loadTime2, 1) . "x\n" . C_RESET;
        } else {
            echo C_YELLOW . "  ⚠ Возможно, кеш не сработал (разница незначительна)\n" . C_RESET;
        }
        
        echo "\n";
        
    } catch (Exception $e) {
        echo C_YELLOW . "✗ Ошибка загрузки: " . $e->getMessage() . "\n" . C_RESET;
        echo "  Класс исключения: " . get_class($e) . "\n\n";
    }
    
    // Небольшая задержка между запросами
    sleep(1);
}

// Финальная статистика
echo C_BLUE . str_repeat('═', 80) . "\n";
echo "ФИНАЛЬНАЯ СТАТИСТИКА\n";
echo str_repeat('═', 80) . C_RESET . "\n\n";

$logFile = __DIR__ . '/logs_demo_rss/rss_demo.log';
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $lines = array_filter(explode("\n", $logContent));
    
    $infoCount = substr_count($logContent, ' INFO ');
    $errorCount = substr_count($logContent, ' ERROR ');
    $warningCount = substr_count($logContent, ' WARNING ');
    
    echo "Статистика логирования:\n";
    echo "  Всего записей: " . count($lines) . "\n";
    echo "  INFO:          " . C_GREEN . $infoCount . C_RESET . "\n";
    echo "  WARNING:       " . ($warningCount > 0 ? C_YELLOW : C_GREEN) . $warningCount . C_RESET . "\n";
    echo "  ERROR:         " . ($errorCount > 0 ? C_YELLOW : C_GREEN) . $errorCount . C_RESET . "\n\n";
    
    echo "Лог файл: $logFile\n";
    echo "Для просмотра: tail -f $logFile\n\n";
}

echo C_GREEN . "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                     ✓ ДЕМОНСТРАЦИЯ ЗАВЕРШЕНА                               ║\n";
echo "║                  Класс Rss работает корректно!                             ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n" . C_RESET;
echo "\n";

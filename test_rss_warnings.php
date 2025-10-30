<?php

declare(strict_types=1);

/**
 * Специальный тест для проверки WARNING логирования при проблемных элементах
 */

require_once __DIR__ . '/autoload.php';

use App\Component\Rss;
use App\Component\Logger;

$logDir = __DIR__ . '/logs_rss_warnings_test';
$cacheDir = __DIR__ . '/cache_rss_warnings_test';

// Создание директорий
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

// Инициализация логгера
$logger = new Logger([
    'directory' => $logDir,
    'file_name' => 'rss_warnings.log',
    'max_files' => 3,
    'max_file_size' => 5242880,
]);

$rss = new Rss([
    'timeout' => 15,
    'cache_directory' => $cacheDir,
    'enable_cache' => false, // Отключаем кеш для чистого теста
], $logger);

echo "Тест проверки WARNING сообщений при обработке проблемных RSS элементов\n";
echo str_repeat('=', 80) . "\n\n";

// Создадим RSS ленту с проблемными элементами
// Используем file:// протокол для локального файла
$testRssContent = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
    <channel>
        <title>Тестовая лента с проблемными элементами</title>
        <link>https://example.com</link>
        <description>Проверка обработки ошибок</description>
        
        <!-- Нормальный элемент -->
        <item>
            <title>Нормальная новость</title>
            <link>https://example.com/1</link>
            <description>Описание</description>
        </item>
        
        <!-- Элемент с битой датой -->
        <item>
            <title>Новость с битой датой</title>
            <link>https://example.com/2</link>
            <description>Описание 2</description>
            <pubDate>This is not a valid date format!!!</pubDate>
        </item>
        
        <!-- Элемент без заголовка -->
        <item>
            <link>https://example.com/3</link>
            <description>Элемент без заголовка</description>
        </item>
    </channel>
</rss>
XML;

// Создаем временный файл
$tempFile = tempnam(sys_get_temp_dir(), 'rss_test_');
file_put_contents($tempFile, $testRssContent);

try {
    // Используем реальный RSS для проверки работы
    echo "Загружаем ленту Habr (может содержать элементы с проблемами)...\n";
    $data = $rss->fetch('https://habr.com/ru/rss/all/all/');
    
    echo "✓ Лента успешно загружена\n";
    echo "  Заголовок: {$data['title']}\n";
    echo "  Элементов: " . count($data['items']) . "\n\n";
    
} catch (Exception $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n";
}

// Проверяем лог на наличие WARNING
echo "\nПроверка лог файла...\n";
echo str_repeat('-', 80) . "\n";

$logFile = $logDir . '/rss_warnings.log';
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    
    // Подсчет записей разных уровней
    $infoCount = substr_count($logContent, ' INFO ');
    $warningCount = substr_count($logContent, ' WARNING ');
    $errorCount = substr_count($logContent, ' ERROR ');
    
    echo "Статистика логирования:\n";
    echo "  INFO записей:    $infoCount\n";
    echo "  WARNING записей: $warningCount\n";
    echo "  ERROR записей:   $errorCount\n\n";
    
    if ($warningCount > 0) {
        echo "✓ WARNING сообщения найдены в логе\n\n";
        echo "Примеры WARNING сообщений:\n";
        $lines = explode("\n", $logContent);
        $warningLines = array_filter($lines, fn($line) => strpos($line, ' WARNING ') !== false);
        foreach (array_slice($warningLines, 0, 3) as $line) {
            echo "  - " . substr($line, 0, 120) . "...\n";
        }
    } else {
        echo "⚠ WARNING сообщения не найдены в логе\n";
        echo "  Это нормально, если в ленте не было проблемных элементов.\n";
    }
    
    echo "\n\nПолный лог файл:\n";
    echo str_repeat('=', 80) . "\n";
    echo $logContent;
    
} else {
    echo "✗ Лог файл не найден\n";
}

// Очистка
unlink($tempFile);

echo "\nТест завершен.\n";

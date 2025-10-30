<?php

declare(strict_types=1);

/**
 * Тест логирования ошибок в WebtExtractor
 * 
 * Проверяет, что все ошибки правильно логируются
 */

require_once __DIR__ . '/autoload.php';

use App\Component\WebtExtractor;
use App\Component\Logger;
use App\Component\Exception\WebtExtractorException;
use App\Component\Exception\WebtExtractorValidationException;

// Создаем логгер
$logDir = __DIR__ . '/logs_webextractor_errors';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$logger = new Logger([
    'directory' => $logDir,
    'file_name' => 'webextractor_errors.log',
    'max_files' => 5,
    'max_file_size' => 10,
]);

echo "=== Тест логирования ошибок WebtExtractor ===\n\n";

$extractor = new WebtExtractor([], $logger);

// Тест 1: Пустой URL
echo "1. Тест с пустым URL:\n";
try {
    $extractor->extractFromHtml('<html><body>Test content here</body></html>', '');
} catch (WebtExtractorValidationException $e) {
    echo "   ✓ Исключение поймано: " . $e->getMessage() . "\n\n";
}

// Тест 2: Некорректный URL
echo "2. Тест с некорректным URL:\n";
try {
    $extractor->extractFromHtml('<html><body>Test content here</body></html>', 'not-a-valid-url');
} catch (WebtExtractorValidationException $e) {
    echo "   ✓ Исключение поймано: " . $e->getMessage() . "\n\n";
}

// Тест 3: Пустой HTML
echo "3. Тест с пустым HTML:\n";
try {
    $extractor->extractFromHtml('', 'https://example.com');
} catch (WebtExtractorValidationException $e) {
    echo "   ✓ Исключение поймано: " . $e->getMessage() . "\n\n";
}

// Тест 4: HTML без читаемого контента
echo "4. Тест с HTML без читаемого контента:\n";
try {
    $html = '<html><head><title>Test</title></head><body><script>console.log("test");</script></body></html>';
    $extractor->extractFromHtml($html, 'https://example.com');
} catch (WebtExtractorException $e) {
    echo "   ✓ Исключение поймано: " . $e->getMessage() . "\n\n";
}

// Тест 5: Очень большой контент (превышение лимита)
echo "5. Тест с превышением размера контента:\n";
try {
    $smallExtractor = new WebtExtractor([
        'max_content_size' => 100, // очень маленький лимит
    ], $logger);
    
    $largeHtml = str_repeat('<p>Test content paragraph</p>', 100);
    $fullHtml = "<html><body><article>$largeHtml</article></body></html>";
    
    $smallExtractor->extractFromHtml($fullHtml, 'https://example.com');
} catch (WebtExtractorException $e) {
    echo "   ✓ Исключение поймано: " . $e->getMessage() . "\n\n";
}

// Проверяем лог-файл
echo "=== Проверка лог-файла ===\n\n";
$logFile = $logDir . '/webextractor_errors.log';

if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $lines = explode("\n", $logContent);
    
    echo "Количество строк в логе: " . count(array_filter($lines)) . "\n\n";
    echo "Содержимое лога:\n";
    echo str_repeat("=", 80) . "\n";
    echo $logContent;
    echo str_repeat("=", 80) . "\n\n";
    
    // Подсчитываем количество ошибок
    $errorCount = substr_count($logContent, 'ERROR');
    $warningCount = substr_count($logContent, 'WARNING');
    $debugCount = substr_count($logContent, 'DEBUG');
    $infoCount = substr_count($logContent, 'INFO');
    
    echo "Статистика логирования:\n";
    echo "  INFO:    $infoCount\n";
    echo "  DEBUG:   $debugCount\n";
    echo "  WARNING: $warningCount\n";
    echo "  ERROR:   $errorCount\n\n";
    
    if ($errorCount > 0 || $warningCount > 0) {
        echo "✓ Ошибки и предупреждения успешно залогированы!\n";
    } else {
        echo "⚠ Ошибки не были залогированы (возможна проблема с логированием)\n";
    }
} else {
    echo "✗ Лог-файл не создан!\n";
}

echo "\n=== Тест завершен ===\n";

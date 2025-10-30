<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Component\Http;
use App\Component\Logger;

/**
 * Тест механизма повторных попыток (retry) и детального логирования
 */

echo "=== ТЕСТИРОВАНИЕ RETRY МЕХАНИЗМА И ЛОГИРОВАНИЯ ===\n\n";

// Создание директории для логов
$logDir = __DIR__ . '/logs_retry_test';
if (!is_dir($logDir)) {
    mkdir($logDir, 0775, true);
}

// Инициализация логгера
$logger = new Logger([
    'directory' => $logDir,
    'file_name' => 'retry_test.log',
    'max_files' => 3,
    'max_file_size' => 1,
    'enabled' => true,
    'log_buffer_size' => 0,
]);

echo "✓ Логгер инициализирован\n\n";

// =============================================================================
// ТЕСТ 1: Успешный запрос с логированием
// =============================================================================
echo "ТЕСТ 1: Успешный запрос с полным логированием\n";
echo str_repeat('-', 80) . "\n";

$http = new Http([
    'timeout' => 10.0,
    'log_successful_requests' => true, // Включено по умолчанию
], $logger);

try {
    $response = $http->get('https://www.google.com/');
    echo "✓ Запрос выполнен успешно\n";
    echo "  Статус: " . $response->getStatusCode() . "\n";
    echo "  Размер: " . strlen((string)$response->getBody()) . " байт\n";
} catch (Exception $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n";
}

echo "\n";

// =============================================================================
// ТЕСТ 2: Отключение логирования успешных запросов
// =============================================================================
echo "ТЕСТ 2: Отключение логирования успешных запросов\n";
echo str_repeat('-', 80) . "\n";

$httpNoLog = new Http([
    'timeout' => 10.0,
    'log_successful_requests' => false,
], $logger);

try {
    $response = $httpNoLog->get('https://www.google.com/');
    echo "✓ Запрос выполнен успешно (без логирования)\n";
    echo "  Статус: " . $response->getStatusCode() . "\n";
} catch (Exception $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n";
}

echo "\n";

// =============================================================================
// ТЕСТ 3: Retry при серверной ошибке 500 (с использованием реального API)
// =============================================================================
echo "ТЕСТ 3: Retry при серверной ошибке (симуляция)\n";
echo str_repeat('-', 80) . "\n";

$httpRetry = new Http([
    'timeout' => 10.0,
    'retries' => 3,
], $logger);

try {
    // httpbin может быть недоступен, попробуем другой сервис
    $response = $httpRetry->get('https://httpstat.us/500');
    echo "  Статус: " . $response->getStatusCode() . "\n";
    echo "  (Retry может не сработать для 500, т.к. это успешный ответ с кодом 500)\n";
} catch (Exception $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n";
}

echo "\n";

// =============================================================================
// ТЕСТ 4: Множественные запросы для проверки логирования
// =============================================================================
echo "ТЕСТ 4: Множественные запросы разных типов\n";
echo str_repeat('-', 80) . "\n";

$httpMulti = new Http([
    'timeout' => 10.0,
], $logger);

$endpoints = [
    ['GET', 'https://www.example.com/', 'Example.com'],
    ['HEAD', 'https://www.google.com/', 'Google HEAD'],
];

foreach ($endpoints as [$method, $url, $name]) {
    try {
        $startTime = microtime(true);
        $response = $httpMulti->request($method, $url);
        $duration = microtime(true) - $startTime;
        
        echo "  {$name}: {$response->getStatusCode()} (" . round($duration, 3) . "s)\n";
    } catch (Exception $e) {
        echo "  {$name}: Ошибка - " . $e->getMessage() . "\n";
    }
}

echo "\n";

// =============================================================================
// ТЕСТ 5: Потоковый запрос с логированием
// =============================================================================
echo "ТЕСТ 5: Потоковый запрос с логированием\n";
echo str_repeat('-', 80) . "\n";

$httpStream = new Http([
    'timeout' => 15.0,
], $logger);

$receivedBytes = 0;

try {
    $httpStream->requestStream(
        'GET',
        'https://www.example.com/',
        function(string $chunk) use (&$receivedBytes) {
            $receivedBytes += strlen($chunk);
        }
    );
    echo "✓ Потоковый запрос выполнен\n";
    echo "  Получено байт: {$receivedBytes}\n";
} catch (Exception $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n";
}

echo "\n";

// =============================================================================
// АНАЛИЗ ЛОГОВ
// =============================================================================
echo str_repeat('=', 80) . "\n";
echo "АНАЛИЗ ЛОГОВ\n";
echo str_repeat('=', 80) . "\n\n";

$logFile = $logDir . '/retry_test.log';
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $logLines = explode("\n", trim($logContent));
    
    echo "Файл лога: {$logFile}\n";
    echo "Размер: " . strlen($logContent) . " байт\n";
    echo "Количество записей: " . count($logLines) . "\n\n";
    
    // Подсчет по уровням
    $levels = [
        'DEBUG' => 0,
        'INFO' => 0,
        'WARNING' => 0,
        'ERROR' => 0,
        'CRITICAL' => 0,
    ];
    
    foreach ($logLines as $line) {
        foreach ($levels as $level => $count) {
            if (str_contains($line, " {$level} ")) {
                $levels[$level]++;
                break;
            }
        }
    }
    
    echo "Распределение по уровням:\n";
    foreach ($levels as $level => $count) {
        if ($count > 0) {
            echo "  {$level}: {$count}\n";
        }
    }
    echo "\n";
    
    echo "Последние 10 записей в логе:\n";
    echo str_repeat('-', 80) . "\n";
    $lastLines = array_slice($logLines, -10);
    foreach ($lastLines as $line) {
        // Обрезаем длинные строки для читаемости
        if (strlen($line) > 120) {
            $line = substr($line, 0, 117) . '...';
        }
        echo $line . "\n";
    }
    echo "\n";
    
    // Проверка наличия ключевых полей
    echo "Проверка полей логирования:\n";
    echo str_repeat('-', 80) . "\n";
    
    $hasMethod = false;
    $hasUri = false;
    $hasStatusCode = false;
    $hasDuration = false;
    $hasBodySize = false;
    
    foreach ($logLines as $line) {
        if (str_contains($line, '"method"')) $hasMethod = true;
        if (str_contains($line, '"uri"')) $hasUri = true;
        if (str_contains($line, '"status_code"')) $hasStatusCode = true;
        if (str_contains($line, '"duration"')) $hasDuration = true;
        if (str_contains($line, '"body_size"')) $hasBodySize = true;
    }
    
    echo "  ✓ method: " . ($hasMethod ? 'Да' : 'Нет') . "\n";
    echo "  ✓ uri: " . ($hasUri ? 'Да' : 'Нет') . "\n";
    echo "  ✓ status_code: " . ($hasStatusCode ? 'Да' : 'Нет') . "\n";
    echo "  ✓ duration: " . ($hasDuration ? 'Да' : 'Нет') . "\n";
    echo "  ✓ body_size: " . ($hasBodySize ? 'Да' : 'Нет') . "\n";
    
} else {
    echo "⚠ Файл лога не найден: {$logFile}\n";
}

echo "\n=== ТЕСТИРОВАНИЕ ЗАВЕРШЕНО ===\n";

<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Component\Http;
use App\Component\Logger;
use App\Component\Exception\HttpException;
use App\Component\Exception\HttpValidationException;

/**
 * Комплексный тест класса Http с проверкой всех методов и логирования
 */

echo "=== ТЕСТИРОВАНИЕ КЛАССА Http ===\n\n";

// Создание директории для логов
$logDir = __DIR__ . '/logs_test';
if (!is_dir($logDir)) {
    mkdir($logDir, 0775, true);
}

// Инициализация логгера
$logger = new Logger([
    'directory' => $logDir,
    'file_name' => 'http_test.log',
    'max_files' => 3,
    'max_file_size' => 1, // 1 MB
    'enabled' => true,
    'log_buffer_size' => 0, // Без буферизации для немедленной записи
]);

echo "✓ Логгер инициализирован: {$logDir}/http_test.log\n\n";

// Счетчики для статистики
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

/**
 * Вспомогательная функция для тестирования
 */
function runTest(string $testName, callable $testFunc, Logger $logger): void
{
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    
    echo "Тест #{$totalTests}: {$testName}\n";
    echo str_repeat('-', 80) . "\n";
    
    try {
        $result = $testFunc($logger);
        if ($result) {
            $passedTests++;
            echo "✓ УСПЕШНО\n\n";
        } else {
            $failedTests++;
            echo "✗ ПРОВАЛЕН\n\n";
        }
    } catch (Throwable $e) {
        $failedTests++;
        echo "✗ ИСКЛЮЧЕНИЕ: " . get_class($e) . "\n";
        echo "  Сообщение: {$e->getMessage()}\n";
        echo "  Файл: {$e->getFile()}:{$e->getLine()}\n\n";
    }
}

// =============================================================================
// ТЕСТ 1: Базовая инициализация без параметров
// =============================================================================
runTest('Базовая инициализация без параметров', function(Logger $logger) {
    $http = new Http([], $logger);
    $client = $http->getClient();
    
    echo "  Тип клиента: " . get_class($client) . "\n";
    echo "  Клиент создан успешно\n";
    
    return $client instanceof GuzzleHttp\Client;
}, $logger);

// =============================================================================
// ТЕСТ 2: Инициализация с полной конфигурацией
// =============================================================================
runTest('Инициализация с полной конфигурацией', function(Logger $logger) {
    $config = [
        'base_uri' => 'https://httpbin.org',
        'timeout' => 10.0,
        'connect_timeout' => 5.0,
        'verify' => true,
        'headers' => [
            'User-Agent' => 'PHP-Test-Client/1.0',
            'Accept' => 'application/json',
        ],
        'allow_redirects' => true,
        'retries' => 3,
        'options' => [
            'http_errors' => false,
        ],
    ];
    
    $http = new Http($config, $logger);
    $client = $http->getClient();
    
    echo "  Base URI: https://httpbin.org\n";
    echo "  Timeout: 10.0 сек\n";
    echo "  Connect timeout: 5.0 сек\n";
    echo "  Retries: 3\n";
    echo "  Конфигурация применена успешно\n";
    
    return true;
}, $logger);

// =============================================================================
// ТЕСТ 3: GET запрос (httpbin.org/get)
// =============================================================================
runTest('GET запрос к httpbin.org/get', function(Logger $logger) {
    $http = new Http([
        'timeout' => 15.0,
        'verify' => true,
    ], $logger);
    
    $response = $http->get('https://httpbin.org/get', [
        'query' => [
            'param1' => 'value1',
            'param2' => 'value2',
        ],
    ]);
    
    $statusCode = $response->getStatusCode();
    $body = (string)$response->getBody();
    $data = json_decode($body, true);
    
    echo "  Статус: {$statusCode}\n";
    echo "  Content-Type: " . implode(', ', $response->getHeader('Content-Type')) . "\n";
    echo "  Размер ответа: " . strlen($body) . " байт\n";
    echo "  URL запроса: " . ($data['url'] ?? 'N/A') . "\n";
    echo "  Параметры: " . json_encode($data['args'] ?? []) . "\n";
    
    return $statusCode === 200 && isset($data['args']['param1']);
}, $logger);

// =============================================================================
// ТЕСТ 4: POST запрос с JSON данными
// =============================================================================
runTest('POST запрос с JSON данными', function(Logger $logger) {
    $http = new Http([
        'timeout' => 15.0,
    ], $logger);
    
    $postData = [
        'name' => 'Тестовый пользователь',
        'email' => 'test@example.com',
        'age' => 30,
        'active' => true,
    ];
    
    $response = $http->post('https://httpbin.org/post', [
        'json' => $postData,
        'headers' => [
            'X-Custom-Header' => 'CustomValue',
        ],
    ]);
    
    $statusCode = $response->getStatusCode();
    $body = (string)$response->getBody();
    $data = json_decode($body, true);
    
    echo "  Статус: {$statusCode}\n";
    echo "  Отправленные данные: " . json_encode($postData, JSON_UNESCAPED_UNICODE) . "\n";
    echo "  Полученные данные: " . json_encode($data['json'] ?? [], JSON_UNESCAPED_UNICODE) . "\n";
    echo "  Заголовок X-Custom-Header: " . ($data['headers']['X-Custom-Header'] ?? 'N/A') . "\n";
    
    return $statusCode === 200 && $data['json']['name'] === 'Тестовый пользователь';
}, $logger);

// =============================================================================
// ТЕСТ 5: PUT запрос
// =============================================================================
runTest('PUT запрос', function(Logger $logger) {
    $http = new Http([], $logger);
    
    $updateData = [
        'id' => 123,
        'status' => 'updated',
        'timestamp' => time(),
    ];
    
    $response = $http->put('https://httpbin.org/put', [
        'json' => $updateData,
    ]);
    
    $statusCode = $response->getStatusCode();
    $body = (string)$response->getBody();
    $data = json_decode($body, true);
    
    echo "  Статус: {$statusCode}\n";
    echo "  Обновленные данные: " . json_encode($data['json'] ?? [], JSON_UNESCAPED_UNICODE) . "\n";
    
    return $statusCode === 200;
}, $logger);

// =============================================================================
// ТЕСТ 6: PATCH запрос
// =============================================================================
runTest('PATCH запрос', function(Logger $logger) {
    $http = new Http([], $logger);
    
    $patchData = [
        'field' => 'value',
    ];
    
    $response = $http->patch('https://httpbin.org/patch', [
        'json' => $patchData,
    ]);
    
    $statusCode = $response->getStatusCode();
    $body = (string)$response->getBody();
    $data = json_decode($body, true);
    
    echo "  Статус: {$statusCode}\n";
    echo "  PATCH данные: " . json_encode($data['json'] ?? [], JSON_UNESCAPED_UNICODE) . "\n";
    
    return $statusCode === 200;
}, $logger);

// =============================================================================
// ТЕСТ 7: DELETE запрос
// =============================================================================
runTest('DELETE запрос', function(Logger $logger) {
    $http = new Http([], $logger);
    
    $response = $http->delete('https://httpbin.org/delete', [
        'query' => [
            'id' => 456,
        ],
    ]);
    
    $statusCode = $response->getStatusCode();
    $body = (string)$response->getBody();
    $data = json_decode($body, true);
    
    echo "  Статус: {$statusCode}\n";
    echo "  Параметры: " . json_encode($data['args'] ?? []) . "\n";
    
    return $statusCode === 200;
}, $logger);

// =============================================================================
// ТЕСТ 8: HEAD запрос
// =============================================================================
runTest('HEAD запрос', function(Logger $logger) {
    $http = new Http([], $logger);
    
    $response = $http->head('https://httpbin.org/get');
    
    $statusCode = $response->getStatusCode();
    $headers = $response->getHeaders();
    
    echo "  Статус: {$statusCode}\n";
    echo "  Количество заголовков: " . count($headers) . "\n";
    echo "  Content-Type: " . implode(', ', $response->getHeader('Content-Type')) . "\n";
    echo "  Размер тела: " . strlen((string)$response->getBody()) . " байт\n";
    
    return $statusCode === 200 && strlen((string)$response->getBody()) === 0;
}, $logger);

// =============================================================================
// ТЕСТ 9: Обработка 404 ошибки
// =============================================================================
runTest('Обработка 404 ошибки', function(Logger $logger) {
    $http = new Http([], $logger);
    
    $response = $http->get('https://httpbin.org/status/404');
    
    $statusCode = $response->getStatusCode();
    
    echo "  Статус: {$statusCode}\n";
    echo "  Ответ получен без исключения (http_errors = false)\n";
    
    return $statusCode === 404;
}, $logger);

// =============================================================================
// ТЕСТ 10: Обработка 500 ошибки
// =============================================================================
runTest('Обработка 500 ошибки', function(Logger $logger) {
    $http = new Http([], $logger);
    
    $response = $http->get('https://httpbin.org/status/500');
    
    $statusCode = $response->getStatusCode();
    
    echo "  Статус: {$statusCode}\n";
    echo "  Серверная ошибка обработана корректно\n";
    
    return $statusCode === 500;
}, $logger);

// =============================================================================
// ТЕСТ 11: Валидация - пустой метод
// =============================================================================
runTest('Валидация: пустой HTTP метод', function(Logger $logger) {
    $http = new Http([], $logger);
    
    try {
        $http->request('', 'https://httpbin.org/get');
        echo "  ✗ Исключение не было выброшено\n";
        return false;
    } catch (HttpValidationException $e) {
        echo "  ✓ Выброшено HttpValidationException\n";
        echo "  Сообщение: {$e->getMessage()}\n";
        return str_contains($e->getMessage(), 'метод');
    }
}, $logger);

// =============================================================================
// ТЕСТ 12: Валидация - пустой URI
// =============================================================================
runTest('Валидация: пустой URI', function(Logger $logger) {
    $http = new Http([], $logger);
    
    try {
        $http->get('');
        echo "  ✗ Исключение не было выброшено\n";
        return false;
    } catch (HttpValidationException $e) {
        echo "  ✓ Выброшено HttpValidationException\n";
        echo "  Сообщение: {$e->getMessage()}\n";
        return str_contains($e->getMessage(), 'URI');
    }
}, $logger);

// =============================================================================
// ТЕСТ 13: Несуществующий домен (обработка сетевых ошибок)
// =============================================================================
runTest('Несуществующий домен (сетевая ошибка)', function(Logger $logger) {
    $http = new Http([
        'timeout' => 5.0,
        'connect_timeout' => 2.0,
    ], $logger);
    
    try {
        $http->get('https://this-domain-definitely-does-not-exist-12345678.com');
        echo "  ✗ Исключение не было выброшено\n";
        return false;
    } catch (HttpException $e) {
        echo "  ✓ Выброшено HttpException\n";
        echo "  Сообщение: {$e->getMessage()}\n";
        return true;
    }
}, $logger);

// =============================================================================
// ТЕСТ 14: Потоковый запрос (requestStream)
// =============================================================================
runTest('Потоковый запрос с обработкой чанков', function(Logger $logger) {
    $http = new Http([
        'timeout' => 15.0,
    ], $logger);
    
    $receivedBytes = 0;
    $chunkCount = 0;
    $receivedData = '';
    
    $http->requestStream('GET', 'https://httpbin.org/stream-bytes/4096', function(string $chunk) use (&$receivedBytes, &$chunkCount, &$receivedData) {
        $receivedBytes += strlen($chunk);
        $chunkCount++;
        $receivedData .= $chunk;
    });
    
    echo "  Получено байт: {$receivedBytes}\n";
    echo "  Количество чанков: {$chunkCount}\n";
    echo "  Первые 50 байт (hex): " . bin2hex(substr($receivedData, 0, 50)) . "\n";
    
    return $receivedBytes > 0 && $chunkCount > 0;
}, $logger);

// =============================================================================
// ТЕСТ 15: Потоковый запрос с ошибкой (404)
// =============================================================================
runTest('Потоковый запрос с ошибкой 404', function(Logger $logger) {
    $http = new Http([], $logger);
    
    try {
        $http->requestStream('GET', 'https://httpbin.org/status/404', function(string $chunk) {
            // Этот callback не должен быть вызван
        });
        echo "  ✗ Исключение не было выброшено\n";
        return false;
    } catch (HttpException $e) {
        echo "  ✓ Выброшено HttpException\n";
        echo "  Сообщение: {$e->getMessage()}\n";
        return str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), 'ошибкой');
    }
}, $logger);

// =============================================================================
// ТЕСТ 16: Заголовки и User-Agent
// =============================================================================
runTest('Пользовательские заголовки и User-Agent', function(Logger $logger) {
    $http = new Http([
        'headers' => [
            'User-Agent' => 'CustomBot/2.0',
            'X-API-Key' => 'secret-key-12345',
        ],
    ], $logger);
    
    $response = $http->get('https://httpbin.org/headers');
    
    $statusCode = $response->getStatusCode();
    $body = (string)$response->getBody();
    $data = json_decode($body, true);
    
    $headers = $data['headers'] ?? [];
    
    echo "  Статус: {$statusCode}\n";
    echo "  User-Agent: " . ($headers['User-Agent'] ?? 'N/A') . "\n";
    echo "  X-Api-Key: " . ($headers['X-Api-Key'] ?? 'N/A') . "\n";
    
    return $statusCode === 200 && 
           isset($headers['User-Agent']) && 
           str_contains($headers['User-Agent'], 'CustomBot');
}, $logger);

// =============================================================================
// ТЕСТ 17: Проверка редиректов
// =============================================================================
runTest('Обработка редиректов', function(Logger $logger) {
    $http = new Http([
        'allow_redirects' => true,
    ], $logger);
    
    $response = $http->get('https://httpbin.org/redirect/3');
    
    $statusCode = $response->getStatusCode();
    $body = (string)$response->getBody();
    $data = json_decode($body, true);
    
    echo "  Финальный статус: {$statusCode}\n";
    echo "  Финальный URL: " . ($data['url'] ?? 'N/A') . "\n";
    
    return $statusCode === 200;
}, $logger);

// =============================================================================
// ТЕСТ 18: Отключение редиректов
// =============================================================================
runTest('Отключение редиректов', function(Logger $logger) {
    $http = new Http([
        'allow_redirects' => false,
    ], $logger);
    
    $response = $http->get('https://httpbin.org/redirect/1');
    
    $statusCode = $response->getStatusCode();
    $location = implode(', ', $response->getHeader('Location'));
    
    echo "  Статус: {$statusCode}\n";
    echo "  Location: {$location}\n";
    
    return $statusCode === 302 && !empty($location);
}, $logger);

// =============================================================================
// ТЕСТ 19: Задержка запроса (delay)
// =============================================================================
runTest('Запрос с задержкой на сервере', function(Logger $logger) {
    $http = new Http([
        'timeout' => 10.0,
    ], $logger);
    
    $startTime = microtime(true);
    $response = $http->get('https://httpbin.org/delay/2');
    $duration = microtime(true) - $startTime;
    
    $statusCode = $response->getStatusCode();
    
    echo "  Статус: {$statusCode}\n";
    echo "  Время выполнения: " . round($duration, 2) . " сек\n";
    
    return $statusCode === 200 && $duration >= 2.0;
}, $logger);

// =============================================================================
// ТЕСТ 20: Таймаут запроса
// =============================================================================
runTest('Таймаут запроса (ожидается HttpException)', function(Logger $logger) {
    $http = new Http([
        'timeout' => 1.0,
        'connect_timeout' => 1.0,
    ], $logger);
    
    try {
        $http->get('https://httpbin.org/delay/5');
        echo "  ✗ Исключение не было выброшено\n";
        return false;
    } catch (HttpException $e) {
        echo "  ✓ Выброшено HttpException из-за таймаута\n";
        echo "  Сообщение: {$e->getMessage()}\n";
        return true;
    }
}, $logger);

// =============================================================================
// СТАТИСТИКА
// =============================================================================
echo str_repeat('=', 80) . "\n";
echo "ИТОГОВАЯ СТАТИСТИКА:\n";
echo str_repeat('=', 80) . "\n";
echo "Всего тестов: {$totalTests}\n";
echo "Успешно: {$passedTests} ✓\n";
echo "Провалено: {$failedTests} ✗\n";
$successRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 2) : 0;
echo "Процент успеха: {$successRate}%\n";
echo str_repeat('=', 80) . "\n\n";

// Проверка логов
echo "=== ПРОВЕРКА ЛОГИРОВАНИЯ ===\n\n";

$logFile = $logDir . '/http_test.log';
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $logLines = explode("\n", trim($logContent));
    
    echo "Файл лога: {$logFile}\n";
    echo "Размер лога: " . strlen($logContent) . " байт\n";
    echo "Количество строк: " . count($logLines) . "\n\n";
    
    // Подсчет записей по уровням
    $errorCount = 0;
    $warningCount = 0;
    $infoCount = 0;
    
    foreach ($logLines as $line) {
        if (str_contains($line, 'ERROR')) {
            $errorCount++;
        } elseif (str_contains($line, 'WARNING')) {
            $warningCount++;
        } elseif (str_contains($line, 'INFO')) {
            $infoCount++;
        }
    }
    
    echo "Записей ERROR: {$errorCount}\n";
    echo "Записей WARNING: {$warningCount}\n";
    echo "Записей INFO: {$infoCount}\n\n";
    
    if ($errorCount > 0) {
        echo "Последние ERROR записи:\n";
        echo str_repeat('-', 80) . "\n";
        $errorLines = array_filter($logLines, fn($line) => str_contains($line, 'ERROR'));
        $lastErrors = array_slice($errorLines, -5);
        foreach ($lastErrors as $errorLine) {
            echo $errorLine . "\n";
        }
        echo "\n";
    }
} else {
    echo "⚠ Файл лога не найден: {$logFile}\n\n";
}

echo "=== ТЕСТИРОВАНИЕ ЗАВЕРШЕНО ===\n";

// Возвращаем код выхода
exit($failedTests > 0 ? 1 : 0);

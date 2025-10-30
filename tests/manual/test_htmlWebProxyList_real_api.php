<?php

declare(strict_types=1);

/**
 * Детальный реальный тест класса htmlWebProxyList с реальным API
 * 
 * Тест использует до 10 кредитов API для полноценного тестирования всех методов
 * API key: 11d4e524d447983db6ab0e35752dee8a
 */

require_once __DIR__ . '/../../autoload.php';

use App\Component\htmlWebProxyList;
use App\Component\ProxyPool;
use App\Component\Logger;

// Инициализация логгера
$logDir = __DIR__ . '/../../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$logger = new Logger([
    'directory' => $logDir,
    'file_name' => 'htmlWebProxyList_test.log',
    'max_files' => 3,
    'max_file_size' => 10,
]);

echo "\n" . str_repeat("=", 80) . "\n";
echo "ДЕТАЛЬНЫЙ ТЕСТ КЛАССА htmlWebProxyList\n";
echo "Использование реального API: до 10 кредитов\n";
echo str_repeat("=", 80) . "\n\n";

$apiKey = '11d4e524d447983db6ab0e35752dee8a';
$totalCreditsUsed = 0;
$testResults = [];

/**
 * Функция для вывода результата теста
 */
function printTestResult(string $testName, bool $success, string $message = '', array $data = []): void
{
    global $testResults;
    
    $status = $success ? '✓ УСПЕХ' : '✗ ОШИБКА';
    $color = $success ? "\033[32m" : "\033[31m";
    $reset = "\033[0m";
    
    echo "{$color}{$status}{$reset} - {$testName}\n";
    
    if ($message !== '') {
        echo "  → {$message}\n";
    }
    
    if (!empty($data)) {
        echo "  → Данные: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    }
    
    echo "\n";
    
    $testResults[] = [
        'test' => $testName,
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ];
}

// ============================================================================
// ТЕСТ 1: Инициализация класса с валидным API ключом
// ============================================================================
echo "ТЕСТ 1: Инициализация класса с валидным API ключом\n";
echo str_repeat("-", 80) . "\n";

try {
    $htmlWeb = new htmlWebProxyList($apiKey, [
        'timeout' => 15,
    ], $logger);
    
    printTestResult(
        'Инициализация класса',
        true,
        'Класс успешно инициализирован',
        ['api_key' => substr($apiKey, 0, 10) . '...']
    );
} catch (Exception $e) {
    printTestResult(
        'Инициализация класса',
        false,
        'Ошибка: ' . $e->getMessage()
    );
    exit(1);
}

// ============================================================================
// ТЕСТ 2: Получение простого списка прокси (5 прокси = 1 кредит)
// ============================================================================
echo "ТЕСТ 2: Получение простого списка прокси (perpage=5)\n";
echo str_repeat("-", 80) . "\n";

try {
    $htmlWeb->updateParams(['perpage' => 5]);
    $proxies = $htmlWeb->getProxies();
    $remainingLimit = $htmlWeb->getRemainingLimit();
    
    $totalCreditsUsed++;
    
    $success = is_array($proxies) && count($proxies) > 0;
    printTestResult(
        'Получение списка прокси (perpage=5)',
        $success,
        $success ? "Получено прокси: " . count($proxies) : 'Не удалось получить прокси',
        [
            'count' => count($proxies),
            'remaining_limit' => $remainingLimit,
            'credits_used' => $totalCreditsUsed,
            'sample_proxies' => array_slice($proxies, 0, 3),
        ]
    );
} catch (Exception $e) {
    printTestResult(
        'Получение списка прокси',
        false,
        'Ошибка: ' . $e->getMessage()
    );
}

// ============================================================================
// ТЕСТ 3: Фильтр по стране (country=US, 3 прокси = 1 кредит)
// ============================================================================
echo "ТЕСТ 3: Получение прокси с фильтром по стране (US)\n";
echo str_repeat("-", 80) . "\n";

try {
    $htmlWeb->resetParams();
    $htmlWeb->updateParams([
        'country' => 'US',
        'perpage' => 3,
    ]);
    
    $proxies = $htmlWeb->getProxies();
    $remainingLimit = $htmlWeb->getRemainingLimit();
    
    $totalCreditsUsed++;
    
    $success = is_array($proxies) && count($proxies) > 0;
    printTestResult(
        'Фильтр по стране US',
        $success,
        $success ? "Получено прокси из US: " . count($proxies) : 'Не удалось получить прокси',
        [
            'country' => 'US',
            'count' => count($proxies),
            'remaining_limit' => $remainingLimit,
            'credits_used' => $totalCreditsUsed,
            'proxies' => $proxies,
        ]
    );
} catch (Exception $e) {
    printTestResult(
        'Фильтр по стране',
        false,
        'Ошибка: ' . $e->getMessage()
    );
}

// ============================================================================
// ТЕСТ 4: Фильтр по типу прокси (HTTP, 3 прокси = 1 кредит)
// ============================================================================
echo "ТЕСТ 4: Получение прокси с фильтром по типу (HTTP)\n";
echo str_repeat("-", 80) . "\n";

try {
    $htmlWeb->resetParams();
    $htmlWeb->updateParams([
        'type' => 'HTTP',
        'perpage' => 3,
    ]);
    
    $proxies = $htmlWeb->getProxies();
    $remainingLimit = $htmlWeb->getRemainingLimit();
    
    $totalCreditsUsed++;
    
    $success = is_array($proxies) && count($proxies) > 0;
    
    // Проверяем, что все прокси имеют протокол http://
    $allHttp = true;
    foreach ($proxies as $proxy) {
        if (!str_starts_with(strtolower($proxy), 'http://')) {
            $allHttp = false;
            break;
        }
    }
    
    printTestResult(
        'Фильтр по типу HTTP',
        $success && $allHttp,
        $success ? "Получено HTTP прокси: " . count($proxies) : 'Не удалось получить прокси',
        [
            'type' => 'HTTP',
            'count' => count($proxies),
            'all_http_protocol' => $allHttp,
            'remaining_limit' => $remainingLimit,
            'credits_used' => $totalCreditsUsed,
            'proxies' => $proxies,
        ]
    );
} catch (Exception $e) {
    printTestResult(
        'Фильтр по типу HTTP',
        false,
        'Ошибка: ' . $e->getMessage()
    );
}

// ============================================================================
// ТЕСТ 5: Формат short=2 (с протоколами, 3 прокси = 1 кредит)
// ============================================================================
echo "ТЕСТ 5: Получение прокси в формате short=2 (с протоколами)\n";
echo str_repeat("-", 80) . "\n";

try {
    $htmlWeb->resetParams();
    $htmlWeb->updateParams([
        'short' => 2,
        'perpage' => 3,
    ]);
    
    $proxies = $htmlWeb->getProxies();
    $remainingLimit = $htmlWeb->getRemainingLimit();
    
    $totalCreditsUsed++;
    
    $success = is_array($proxies) && count($proxies) > 0;
    printTestResult(
        'Формат short=2',
        $success,
        $success ? "Получено прокси: " . count($proxies) : 'Не удалось получить прокси',
        [
            'format' => 'short=2',
            'count' => count($proxies),
            'remaining_limit' => $remainingLimit,
            'credits_used' => $totalCreditsUsed,
            'proxies' => $proxies,
        ]
    );
} catch (Exception $e) {
    printTestResult(
        'Формат short=2',
        false,
        'Ошибка: ' . $e->getMessage()
    );
}

// ============================================================================
// ТЕСТ 6: Формат short=4 (текстовый список, 3 прокси = 1 кредит)
// ============================================================================
echo "ТЕСТ 6: Получение прокси в формате short=4 (текстовый список)\n";
echo str_repeat("-", 80) . "\n";

try {
    $htmlWeb->resetParams();
    $htmlWeb->updateParams([
        'short' => 4,
        'perpage' => 3,
    ]);
    
    $proxies = $htmlWeb->getProxies();
    $remainingLimit = $htmlWeb->getRemainingLimit();
    
    $totalCreditsUsed++;
    
    $success = is_array($proxies) && count($proxies) > 0;
    printTestResult(
        'Формат short=4',
        $success,
        $success ? "Получено прокси: " . count($proxies) : 'Не удалось получить прокси',
        [
            'format' => 'short=4',
            'count' => count($proxies),
            'remaining_limit' => $remainingLimit,
            'credits_used' => $totalCreditsUsed,
            'proxies' => $proxies,
        ]
    );
} catch (Exception $e) {
    printTestResult(
        'Формат short=4',
        false,
        'Ошибка: ' . $e->getMessage()
    );
}

// ============================================================================
// ТЕСТ 7: Проверка метода getRemainingLimit()
// ============================================================================
echo "ТЕСТ 7: Проверка метода getRemainingLimit()\n";
echo str_repeat("-", 80) . "\n";

try {
    $remainingLimit = $htmlWeb->getRemainingLimit();
    
    $success = $remainingLimit !== null && $remainingLimit >= 0;
    printTestResult(
        'Метод getRemainingLimit()',
        $success,
        "Остаток лимита: {$remainingLimit}",
        [
            'remaining_limit' => $remainingLimit,
            'credits_used_so_far' => $totalCreditsUsed,
        ]
    );
} catch (Exception $e) {
    printTestResult(
        'Метод getRemainingLimit()',
        false,
        'Ошибка: ' . $e->getMessage()
    );
}

// ============================================================================
// ТЕСТ 8: Комбинированные фильтры (2 прокси = 1 кредит)
// ============================================================================
echo "ТЕСТ 8: Комбинированные фильтры (country=RU, type=HTTP, work=1)\n";
echo str_repeat("-", 80) . "\n";

try {
    $htmlWeb->resetParams();
    $htmlWeb->updateParams([
        'country' => 'RU',
        'type' => 'HTTP',
        'work' => 1,
        'perpage' => 2,
    ]);
    
    $proxies = $htmlWeb->getProxies();
    $remainingLimit = $htmlWeb->getRemainingLimit();
    
    $totalCreditsUsed++;
    
    $success = is_array($proxies);
    printTestResult(
        'Комбинированные фильтры',
        $success,
        $success ? "Получено прокси: " . count($proxies) : 'Не удалось получить прокси',
        [
            'filters' => ['country' => 'RU', 'type' => 'HTTP', 'work' => 1],
            'count' => count($proxies),
            'remaining_limit' => $remainingLimit,
            'credits_used' => $totalCreditsUsed,
            'proxies' => $proxies,
        ]
    );
} catch (Exception $e) {
    printTestResult(
        'Комбинированные фильтры',
        false,
        'Ошибка: ' . $e->getMessage()
    );
}

// ============================================================================
// ТЕСТ 9: Интеграция с ProxyPool через loadIntoProxyPool() (3 прокси = 1 кредит)
// ============================================================================
echo "ТЕСТ 9: Интеграция с ProxyPool через loadIntoProxyPool()\n";
echo str_repeat("-", 80) . "\n";

try {
    $proxyPool = new ProxyPool([
        'auto_health_check' => false, // Отключаем автоматическую проверку для экономии времени
    ], $logger);
    
    $htmlWeb->resetParams();
    $htmlWeb->updateParams(['perpage' => 3]);
    
    $addedCount = $htmlWeb->loadIntoProxyPool($proxyPool);
    $remainingLimit = $htmlWeb->getRemainingLimit();
    
    $totalCreditsUsed++;
    
    $stats = $proxyPool->getStatistics();
    
    $success = $addedCount > 0 && $stats['total_proxies'] === $addedCount;
    printTestResult(
        'Интеграция с ProxyPool',
        $success,
        "Добавлено прокси в пул: {$addedCount}",
        [
            'added_count' => $addedCount,
            'pool_total' => $stats['total_proxies'],
            'remaining_limit' => $remainingLimit,
            'credits_used' => $totalCreditsUsed,
        ]
    );
} catch (Exception $e) {
    printTestResult(
        'Интеграция с ProxyPool',
        false,
        'Ошибка: ' . $e->getMessage()
    );
}

// ============================================================================
// ТЕСТ 10: Проверка валидации параметров
// ============================================================================
echo "ТЕСТ 10: Проверка валидации параметров\n";
echo str_repeat("-", 80) . "\n";

$validationTests = [
    [
        'name' => 'Негативный perpage',
        'params' => ['perpage' => -1],
        'should_fail' => true,
    ],
    [
        'name' => 'Некорректный work',
        'params' => ['work' => 5],
        'should_fail' => true,
    ],
    [
        'name' => 'Некорректный type',
        'params' => ['type' => 'INVALID'],
        'should_fail' => true,
    ],
    [
        'name' => 'Некорректный short',
        'params' => ['short' => 99],
        'should_fail' => true,
    ],
];

foreach ($validationTests as $test) {
    try {
        $testHtmlWeb = new htmlWebProxyList($apiKey, $test['params'], $logger);
        
        if ($test['should_fail']) {
            printTestResult(
                "Валидация: {$test['name']}",
                false,
                'Ожидалась ошибка валидации, но её не произошло'
            );
        } else {
            printTestResult(
                "Валидация: {$test['name']}",
                true,
                'Параметры приняты корректно'
            );
        }
    } catch (Exception $e) {
        if ($test['should_fail']) {
            printTestResult(
                "Валидация: {$test['name']}",
                true,
                'Валидация корректно отклонила некорректные параметры: ' . $e->getMessage()
            );
        } else {
            printTestResult(
                "Валидация: {$test['name']}",
                false,
                'Неожиданная ошибка: ' . $e->getMessage()
            );
        }
    }
}

// ============================================================================
// ТЕСТ 11: Методы getParams() и resetParams()
// ============================================================================
echo "ТЕСТ 11: Методы getParams() и resetParams()\n";
echo str_repeat("-", 80) . "\n";

try {
    $htmlWeb->resetParams();
    $htmlWeb->updateParams([
        'country' => 'US',
        'type' => 'HTTP',
        'perpage' => 5,
    ]);
    
    $params = $htmlWeb->getParams();
    $hasCorrectParams = isset($params['country']) && $params['country'] === 'US';
    
    printTestResult(
        'Метод getParams()',
        $hasCorrectParams,
        'Параметры получены корректно',
        ['params' => $params]
    );
    
    $htmlWeb->resetParams();
    $paramsAfterReset = $htmlWeb->getParams();
    $isReset = empty($paramsAfterReset);
    
    printTestResult(
        'Метод resetParams()',
        $isReset,
        'Параметры сброшены корректно',
        ['params_after_reset' => $paramsAfterReset]
    );
} catch (Exception $e) {
    printTestResult(
        'Методы getParams/resetParams',
        false,
        'Ошибка: ' . $e->getMessage()
    );
}

// ============================================================================
// ИТОГОВАЯ СТАТИСТИКА
// ============================================================================
echo "\n" . str_repeat("=", 80) . "\n";
echo "ИТОГОВАЯ СТАТИСТИКА ТЕСТИРОВАНИЯ\n";
echo str_repeat("=", 80) . "\n\n";

$successCount = count(array_filter($testResults, fn($r) => $r['success']));
$totalTests = count($testResults);
$successRate = ($totalTests > 0) ? round(($successCount / $totalTests) * 100, 2) : 0;

echo "Всего тестов: {$totalTests}\n";
echo "Успешно: {$successCount}\n";
echo "Провалено: " . ($totalTests - $successCount) . "\n";
echo "Процент успеха: {$successRate}%\n";
echo "Использовано API кредитов: {$totalCreditsUsed}\n";
echo "Остаток лимита: " . ($htmlWeb->getRemainingLimit() ?? 'неизвестно') . "\n";

echo "\n" . str_repeat("=", 80) . "\n";
echo "ТЕСТИРОВАНИЕ ЗАВЕРШЕНО\n";
echo str_repeat("=", 80) . "\n";

// Сохраняем результаты в файл
$resultsFile = $logDir . '/htmlWebProxyList_test_results.json';
file_put_contents(
    $resultsFile,
    json_encode([
        'timestamp' => date('Y-m-d H:i:s'),
        'total_tests' => $totalTests,
        'successful_tests' => $successCount,
        'failed_tests' => $totalTests - $successCount,
        'success_rate' => $successRate,
        'credits_used' => $totalCreditsUsed,
        'remaining_limit' => $htmlWeb->getRemainingLimit(),
        'tests' => $testResults,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
);

echo "\nРезультаты сохранены в: {$resultsFile}\n";
echo "Логи сохранены в: {$logDir}/htmlWebProxyList_test.log\n\n";

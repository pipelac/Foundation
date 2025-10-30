<?php

declare(strict_types=1);

/**
 * Интеграционный тест классов htmlWebProxyList, ProxyPool и RSS
 * 
 * Сценарий:
 * 1. Получаем список прокси через htmlWebProxyList
 * 2. Загружаем прокси в ProxyPool
 * 3. Проверяем работоспособность прокси через health check
 * 4. Парсим RSS ленты через прокси
 * 5. Собираем статистику и результаты
 * 
 * Использует до 10 кредитов API htmlweb.ru
 * API key: 11d4e524d447983db6ab0e35752dee8a
 */

require_once __DIR__ . '/../../autoload.php';

use App\Component\htmlWebProxyList;
use App\Component\ProxyPool;
use App\Component\Rss;
use App\Component\Logger;

// Инициализация логгера
$logDir = __DIR__ . '/../../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$logger = new Logger([
    'directory' => $logDir,
    'file_name' => 'integration_test.log',
    'max_files' => 3,
    'max_file_size' => 10,
]);

echo "\n" . str_repeat("=", 100) . "\n";
echo "ИНТЕГРАЦИОННЫЙ ТЕСТ: htmlWebProxyList + ProxyPool + RSS\n";
echo "Использование реального API: до 10 кредитов\n";
echo str_repeat("=", 100) . "\n\n";

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
        foreach ($data as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            echo "  → {$key}: {$value}\n";
        }
    }
    
    echo "\n";
    
    $testResults[] = [
        'test' => $testName,
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ];
}

// RSS ленты для тестирования
$rssFeeds = [
    'https://lenta.ru/rss',
    'https://ria.ru/export/rss2/archive/index.xml',
    'https://www.vedomosti.ru/rss/news',
];

// ============================================================================
// ШАГ 1: Получение списка прокси через htmlWebProxyList
// ============================================================================
echo "ШАГ 1: Получение списка прокси через htmlWebProxyList\n";
echo str_repeat("-", 100) . "\n";

try {
    $htmlWeb = new htmlWebProxyList($apiKey, [
        'perpage' => 10, // Получаем 10 прокси (1 кредит)
        'type' => 'HTTP',
        'timeout' => 15,
    ], $logger);
    
    $proxies = $htmlWeb->getProxies();
    $remainingLimit = $htmlWeb->getRemainingLimit();
    
    $totalCreditsUsed++;
    
    $success = is_array($proxies) && count($proxies) > 0;
    printTestResult(
        'Получение прокси через htmlWebProxyList',
        $success,
        $success ? "Получено прокси: " . count($proxies) : 'Не удалось получить прокси',
        [
            'count' => count($proxies),
            'remaining_limit' => $remainingLimit,
            'credits_used' => $totalCreditsUsed,
            'sample_proxies' => array_slice($proxies, 0, 3),
        ]
    );
    
    if (!$success) {
        echo "КРИТИЧЕСКАЯ ОШИБКА: Не удалось получить прокси. Тест прерван.\n";
        exit(1);
    }
} catch (Exception $e) {
    printTestResult(
        'Получение прокси через htmlWebProxyList',
        false,
        'Ошибка: ' . $e->getMessage()
    );
    echo "КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage() . "\n";
    exit(1);
}

// ============================================================================
// ШАГ 2: Загрузка прокси в ProxyPool
// ============================================================================
echo "ШАГ 2: Загрузка прокси в ProxyPool\n";
echo str_repeat("-", 100) . "\n";

try {
    $proxyPool = new ProxyPool([
        'auto_health_check' => false, // Отключаем автоматическую проверку
        'rotation_strategy' => ProxyPool::ROTATION_ROUND_ROBIN,
        'max_retries' => 3,
        'health_check_timeout' => 10,
    ], $logger);
    
    // Добавляем прокси в пул
    foreach ($proxies as $proxy) {
        try {
            $proxyPool->addProxy($proxy);
        } catch (Exception $e) {
            echo "  → Предупреждение: Не удалось добавить прокси {$proxy}: {$e->getMessage()}\n";
        }
    }
    
    $stats = $proxyPool->getStatistics();
    
    $success = $stats['total_proxies'] > 0;
    printTestResult(
        'Загрузка прокси в ProxyPool',
        $success,
        "Загружено прокси в пул: {$stats['total_proxies']}",
        [
            'total_proxies' => $stats['total_proxies'],
            'alive_proxies' => $stats['alive_proxies'],
            'dead_proxies' => $stats['dead_proxies'],
        ]
    );
} catch (Exception $e) {
    printTestResult(
        'Загрузка прокси в ProxyPool',
        false,
        'Ошибка: ' . $e->getMessage()
    );
    echo "КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage() . "\n";
    exit(1);
}

// ============================================================================
// ШАГ 3: Health check прокси (проверка первых 5 прокси)
// ============================================================================
echo "ШАГ 3: Health check прокси (проверка первых 5)\n";
echo str_repeat("-", 100) . "\n";

try {
    $checkedProxies = 0;
    $aliveProxies = 0;
    
    echo "Проверка работоспособности прокси...\n";
    
    foreach (array_slice($proxies, 0, 5) as $proxy) {
        echo "  → Проверка {$proxy}... ";
        $isAlive = $proxyPool->checkProxyHealth($proxy);
        
        if ($isAlive) {
            echo "✓ РАБОТАЕТ\n";
            $aliveProxies++;
        } else {
            echo "✗ НЕ РАБОТАЕТ\n";
        }
        
        $checkedProxies++;
    }
    
    $stats = $proxyPool->getStatistics();
    
    printTestResult(
        'Health check прокси',
        true,
        "Проверено: {$checkedProxies}, Работает: {$aliveProxies}",
        [
            'checked' => $checkedProxies,
            'alive' => $aliveProxies,
            'dead' => $checkedProxies - $aliveProxies,
            'total_in_pool' => $stats['total_proxies'],
            'alive_in_pool' => $stats['alive_proxies'],
        ]
    );
} catch (Exception $e) {
    printTestResult(
        'Health check прокси',
        false,
        'Ошибка: ' . $e->getMessage()
    );
}

// ============================================================================
// ШАГ 4: Тест RSS парсинга БЕЗ прокси (контрольный тест)
// ============================================================================
echo "ШАГ 4: Тест RSS парсинга БЕЗ прокси (контрольный тест)\n";
echo str_repeat("-", 100) . "\n";

try {
    $cacheDir = __DIR__ . '/../../cache/rss_test';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $rss = new Rss([
        'timeout' => 15,
        'cache_directory' => $cacheDir,
        'cache_duration' => 300,
        'enable_cache' => true,
    ], $logger);
    
    $directResults = [];
    
    foreach ($rssFeeds as $feedUrl) {
        echo "  → Парсинг {$feedUrl} без прокси... ";
        
        try {
            $startTime = microtime(true);
            $feedData = $rss->fetch($feedUrl);
            $endTime = microtime(true);
            
            $success = isset($feedData['items']) && count($feedData['items']) > 0;
            
            if ($success) {
                echo "✓ OK (" . round($endTime - $startTime, 2) . "s, " . count($feedData['items']) . " элементов)\n";
                $directResults[$feedUrl] = [
                    'success' => true,
                    'items_count' => count($feedData['items']),
                    'time' => round($endTime - $startTime, 2),
                    'title' => $feedData['title'] ?? 'N/A',
                ];
            } else {
                echo "✗ Нет элементов\n";
                $directResults[$feedUrl] = ['success' => false, 'error' => 'Нет элементов'];
            }
        } catch (Exception $e) {
            echo "✗ Ошибка: " . $e->getMessage() . "\n";
            $directResults[$feedUrl] = ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    $successfulFeeds = count(array_filter($directResults, fn($r) => $r['success']));
    
    printTestResult(
        'RSS парсинг без прокси',
        $successfulFeeds > 0,
        "Успешно загружено лент: {$successfulFeeds} из " . count($rssFeeds),
        ['results' => $directResults]
    );
} catch (Exception $e) {
    printTestResult(
        'RSS парсинг без прокси',
        false,
        'Ошибка: ' . $e->getMessage()
    );
}

// ============================================================================
// ШАГ 5: Тест RSS парсинга ЧЕРЕЗ прокси (основной тест интеграции)
// ============================================================================
echo "ШАГ 5: Тест RSS парсинга ЧЕРЕЗ прокси (интеграционный тест)\n";
echo str_repeat("-", 100) . "\n";

try {
    $proxyResults = [];
    
    // Выбираем только живые прокси для тестирования
    $stats = $proxyPool->getStatistics();
    $aliveCount = $stats['alive_proxies'];
    
    if ($aliveCount === 0) {
        echo "  → Предупреждение: Нет живых прокси для тестирования. Пропускаем этот шаг.\n";
        printTestResult(
            'RSS парсинг через прокси',
            false,
            'Нет живых прокси для тестирования'
        );
    } else {
        echo "Доступно живых прокси: {$aliveCount}\n\n";
        
        // Тестируем каждую RSS ленту через прокси
        foreach ($rssFeeds as $feedUrl) {
            echo "  → Парсинг {$feedUrl} через прокси:\n";
            
            $attempts = 0;
            $maxAttempts = min(3, $aliveCount); // Максимум 3 попытки или количество живых прокси
            $feedSuccess = false;
            
            while ($attempts < $maxAttempts && !$feedSuccess) {
                $attempts++;
                $proxy = $proxyPool->getNextProxy();
                
                if ($proxy === null) {
                    echo "    Попытка {$attempts}: ✗ Нет доступных прокси\n";
                    break;
                }
                
                echo "    Попытка {$attempts} через {$proxy}... ";
                
                try {
                    // Создаем новый RSS клиент с прокси
                    $rssWithProxy = new Rss([
                        'timeout' => 20,
                        'cache_directory' => $cacheDir,
                        'cache_duration' => 0, // Отключаем кеш для реального теста
                        'enable_cache' => false,
                    ], $logger);
                    
                    // Временно изменяем HTTP клиент для использования прокси
                    // Для этого нужно создать Http клиент с прокси через reflection
                    $httpReflection = new ReflectionClass($rssWithProxy);
                    $httpProperty = $httpReflection->getProperty('http');
                    $httpProperty->setAccessible(true);
                    
                    $httpWithProxy = new \App\Component\Http([
                        'timeout' => 20,
                        'connect_timeout' => 20,
                        'proxy' => $proxy,
                        'verify' => false,
                        'allow_redirects' => true,
                        'headers' => [
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                        ],
                    ], $logger);
                    
                    $httpProperty->setValue($rssWithProxy, $httpWithProxy);
                    
                    $startTime = microtime(true);
                    $feedData = $rssWithProxy->fetch($feedUrl);
                    $endTime = microtime(true);
                    
                    $success = isset($feedData['items']) && count($feedData['items']) > 0;
                    
                    if ($success) {
                        echo "✓ OK (" . round($endTime - $startTime, 2) . "s, " . count($feedData['items']) . " элементов)\n";
                        $proxyResults[$feedUrl] = [
                            'success' => true,
                            'proxy' => $proxy,
                            'attempts' => $attempts,
                            'items_count' => count($feedData['items']),
                            'time' => round($endTime - $startTime, 2),
                            'title' => $feedData['title'] ?? 'N/A',
                        ];
                        $feedSuccess = true;
                        $proxyPool->markProxyAsAlive($proxy);
                    } else {
                        echo "✗ Нет элементов\n";
                        $proxyPool->markProxyAsDead($proxy);
                    }
                } catch (Exception $e) {
                    echo "✗ Ошибка: " . substr($e->getMessage(), 0, 100) . "\n";
                    $proxyPool->markProxyAsDead($proxy);
                    
                    if ($attempts >= $maxAttempts) {
                        $proxyResults[$feedUrl] = [
                            'success' => false,
                            'attempts' => $attempts,
                            'error' => $e->getMessage(),
                        ];
                    }
                }
            }
            
            if (!$feedSuccess) {
                echo "    → Не удалось загрузить ленту после {$attempts} попыток\n";
            }
            
            echo "\n";
        }
        
        $successfulProxyFeeds = count(array_filter($proxyResults, fn($r) => $r['success']));
        
        printTestResult(
            'RSS парсинг через прокси',
            $successfulProxyFeeds > 0,
            "Успешно загружено лент через прокси: {$successfulProxyFeeds} из " . count($rssFeeds),
            ['results' => $proxyResults]
        );
    }
} catch (Exception $e) {
    printTestResult(
        'RSS парсинг через прокси',
        false,
        'Ошибка: ' . $e->getMessage()
    );
}

// ============================================================================
// ШАГ 6: Дополнительные тесты интеграции (если остались кредиты)
// ============================================================================
echo "ШАГ 6: Дополнительные тесты интеграции\n";
echo str_repeat("-", 100) . "\n";

$remainingLimit = $htmlWeb->getRemainingLimit();
echo "Остаток API кредитов: {$remainingLimit}\n";

if ($remainingLimit > 5) {
    echo "Достаточно кредитов для дополнительных тестов\n\n";
    
    // Тест: Получение дополнительных прокси и добавление в существующий пул
    try {
        echo "  → Получение дополнительных прокси (5 шт)... ";
        
        $htmlWeb->resetParams();
        $htmlWeb->updateParams(['perpage' => 5]);
        
        $newProxies = $htmlWeb->getProxies();
        $totalCreditsUsed++;
        
        echo "✓ Получено: " . count($newProxies) . "\n";
        
        echo "  → Добавление новых прокси в существующий пул... ";
        $addedCount = 0;
        foreach ($newProxies as $proxy) {
            try {
                $proxyPool->addProxy($proxy);
                $addedCount++;
            } catch (Exception $e) {
                // Прокси уже существует или невалиден
            }
        }
        echo "✓ Добавлено: {$addedCount}\n";
        
        $stats = $proxyPool->getStatistics();
        
        printTestResult(
            'Добавление дополнительных прокси',
            $addedCount > 0,
            "Добавлено новых прокси: {$addedCount}",
            [
                'new_proxies' => count($newProxies),
                'added' => $addedCount,
                'total_in_pool' => $stats['total_proxies'],
                'credits_used' => $totalCreditsUsed,
            ]
        );
    } catch (Exception $e) {
        printTestResult(
            'Добавление дополнительных прокси',
            false,
            'Ошибка: ' . $e->getMessage()
        );
    }
    
    // Тест: Ротация прокси в режиме random
    try {
        echo "\n  → Тест ротации прокси в режиме RANDOM... ";
        
        // Создаем новый пул с random ротацией
        $randomPool = new ProxyPool([
            'rotation_strategy' => ProxyPool::ROTATION_RANDOM,
            'auto_health_check' => false,
        ], $logger);
        
        // Добавляем несколько прокси
        foreach (array_slice($proxies, 0, 5) as $proxy) {
            try {
                $randomPool->addProxy($proxy);
            } catch (Exception $e) {
                // Игнорируем ошибки
            }
        }
        
        // Получаем несколько прокси и проверяем что они разные
        $selectedProxies = [];
        for ($i = 0; $i < 10; $i++) {
            $proxy = $randomPool->getNextProxy();
            if ($proxy !== null) {
                $selectedProxies[] = $proxy;
            }
        }
        
        $uniqueProxies = array_unique($selectedProxies);
        $isRandom = count($uniqueProxies) > 1;
        
        echo $isRandom ? "✓ OK\n" : "✗ Все прокси одинаковые\n";
        
        printTestResult(
            'Ротация прокси (random)',
            $isRandom,
            "Получено уникальных прокси: " . count($uniqueProxies) . " из 10 запросов",
            [
                'total_requests' => count($selectedProxies),
                'unique_proxies' => count($uniqueProxies),
            ]
        );
    } catch (Exception $e) {
        printTestResult(
            'Ротация прокси (random)',
            false,
            'Ошибка: ' . $e->getMessage()
        );
    }
} else {
    echo "Недостаточно кредитов для дополнительных тестов (осталось: {$remainingLimit})\n";
    printTestResult(
        'Дополнительные тесты',
        false,
        'Пропущено из-за недостатка кредитов',
        ['remaining_limit' => $remainingLimit]
    );
}

// ============================================================================
// ШАГ 7: Финальная статистика ProxyPool
// ============================================================================
echo "\nШАГ 7: Финальная статистика ProxyPool\n";
echo str_repeat("-", 100) . "\n";

try {
    $finalStats = $proxyPool->getStatistics();
    
    echo "Общая статистика пула прокси:\n";
    echo "  → Всего прокси: {$finalStats['total_proxies']}\n";
    echo "  → Живых: {$finalStats['alive_proxies']}\n";
    echo "  → Мертвых: {$finalStats['dead_proxies']}\n";
    echo "  → Стратегия ротации: {$finalStats['rotation_strategy']}\n";
    echo "  → Всего запросов: {$finalStats['total_requests']}\n";
    echo "  → Успешных: {$finalStats['successful_requests']}\n";
    echo "  → Неудачных: {$finalStats['failed_requests']}\n";
    echo "  → Повторных попыток: {$finalStats['total_retries']}\n\n";
    
    echo "Детальная статистика по прокси:\n";
    foreach ($finalStats['proxies'] as $proxyStats) {
        $status = $proxyStats['alive'] ? '✓' : '✗';
        $successRate = $proxyStats['success_rate'];
        echo "  {$status} {$proxyStats['url']}\n";
        echo "     Успехов: {$proxyStats['success_count']}, Провалов: {$proxyStats['fail_count']}, ";
        echo "Успешность: {$successRate}%\n";
        if ($proxyStats['last_error'] !== '') {
            echo "     Последняя ошибка: {$proxyStats['last_error']}\n";
        }
    }
    
    printTestResult(
        'Статистика ProxyPool',
        true,
        'Статистика собрана',
        [
            'total_proxies' => $finalStats['total_proxies'],
            'alive_proxies' => $finalStats['alive_proxies'],
            'total_requests' => $finalStats['total_requests'],
            'success_rate' => $finalStats['successful_requests'] > 0 
                ? round(($finalStats['successful_requests'] / $finalStats['total_requests']) * 100, 2) 
                : 0,
        ]
    );
} catch (Exception $e) {
    printTestResult(
        'Статистика ProxyPool',
        false,
        'Ошибка: ' . $e->getMessage()
    );
}

// ============================================================================
// ИТОГОВАЯ СТАТИСТИКА
// ============================================================================
echo "\n" . str_repeat("=", 100) . "\n";
echo "ИТОГОВАЯ СТАТИСТИКА ИНТЕГРАЦИОННОГО ТЕСТИРОВАНИЯ\n";
echo str_repeat("=", 100) . "\n\n";

$successCount = count(array_filter($testResults, fn($r) => $r['success']));
$totalTests = count($testResults);
$successRate = ($totalTests > 0) ? round(($successCount / $totalTests) * 100, 2) : 0;

echo "Всего тестов: {$totalTests}\n";
echo "Успешно: {$successCount}\n";
echo "Провалено: " . ($totalTests - $successCount) . "\n";
echo "Процент успеха: {$successRate}%\n";
echo "Использовано API кредитов: {$totalCreditsUsed}\n";
echo "Остаток лимита: " . ($htmlWeb->getRemainingLimit() ?? 'неизвестно') . "\n";

echo "\n" . str_repeat("=", 100) . "\n";
echo "ИНТЕГРАЦИОННОЕ ТЕСТИРОВАНИЕ ЗАВЕРШЕНО\n";
echo str_repeat("=", 100) . "\n";

// Сохраняем результаты в файл
$resultsFile = $logDir . '/integration_test_results.json';
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
echo "Логи сохранены в: {$logDir}/integration_test.log\n\n";

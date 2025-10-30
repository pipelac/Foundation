<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Component\ProxyPool;
use App\Component\Logger;
use App\Component\Exception\ProxyPoolException;
use App\Component\Exception\ProxyPoolValidationException;

/**
 * Комплексный тест всех методов класса ProxyPool
 * 
 * Тестирует:
 * - Инициализацию класса
 * - Добавление и удаление прокси
 * - Валидацию прокси
 * - Ротацию прокси (round-robin и random)
 * - Health check функционал
 * - Статистику
 * - HTTP запросы через прокси
 * - Логирование
 */

echo "=== КОМПЛЕКСНОЕ ТЕСТИРОВАНИЕ ProxyPool ===\n\n";

// Создаём логгер для отслеживания всех операций
$logFile = __DIR__ . '/logs/test_proxypool_' . date('Y-m-d_His') . '.log';
$logger = new Logger([
    'directory' => dirname($logFile),
    'filename' => basename($logFile),
    'max_files' => 1,
]);

$logger->info('Начало тестирования ProxyPool');

// Счётчики для статистики тестов
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

/**
 * Вспомогательная функция для проверки результата теста
 */
function assertTest(string $testName, bool $condition, string $message = ''): void
{
    global $totalTests, $passedTests, $failedTests, $logger;
    
    $totalTests++;
    
    if ($condition) {
        $passedTests++;
        echo "✓ {$testName}: PASS\n";
        $logger->info("Тест пройден: {$testName}");
    } else {
        $failedTests++;
        echo "✗ {$testName}: FAIL" . ($message ? " - {$message}" : "") . "\n";
        $logger->error("Тест провален: {$testName}", ['message' => $message]);
    }
}

/**
 * Вспомогательная функция для проверки исключения
 */
function assertException(string $testName, callable $callback, string $expectedExceptionClass): void
{
    global $logger;
    
    try {
        $callback();
        assertTest($testName, false, "Ожидалось исключение {$expectedExceptionClass}");
    } catch (Exception $e) {
        $actualClass = get_class($e);
        assertTest(
            $testName, 
            $actualClass === $expectedExceptionClass,
            "Ожидалось {$expectedExceptionClass}, получено {$actualClass}: {$e->getMessage()}"
        );
    }
}

echo "\n--- ТЕСТ 1: Инициализация класса ---\n";

try {
    // Базовая инициализация без параметров
    $pool = new ProxyPool([], $logger);
    assertTest('Инициализация без параметров', true);
} catch (Exception $e) {
    assertTest('Инициализация без параметров', false, $e->getMessage());
}

try {
    // Инициализация с параметрами
    $config = [
        'rotation_strategy' => ProxyPool::ROTATION_ROUND_ROBIN,
        'health_check_url' => 'https://httpbin.org/status/200',
        'health_check_timeout' => 5,
        'health_check_interval' => 300,
        'auto_health_check' => false,
        'max_retries' => 3,
    ];
    $pool = new ProxyPool($config, $logger);
    assertTest('Инициализация с параметрами', true);
} catch (Exception $e) {
    assertTest('Инициализация с параметрами', false, $e->getMessage());
}

echo "\n--- ТЕСТ 2: Валидация конфигурации ---\n";

// Тест невалидной стратегии ротации
assertException(
    'Невалидная стратегия ротации',
    function () use ($logger) {
        new ProxyPool(['rotation_strategy' => 'invalid_strategy'], $logger);
    },
    ProxyPoolValidationException::class
);

// Тест пустого URL для health check
assertException(
    'Пустой URL health check',
    function () use ($logger) {
        new ProxyPool(['health_check_url' => ''], $logger);
    },
    ProxyPoolValidationException::class
);

echo "\n--- ТЕСТ 3: Добавление и удаление прокси ---\n";

$pool = new ProxyPool(['auto_health_check' => false], $logger);

try {
    // Добавление валидных прокси
    $pool->addProxy('http://proxy1.example.com:8080');
    $pool->addProxy('http://user:pass@proxy2.example.com:8080');
    $pool->addProxy('socks5://proxy3.example.com:1080');
    
    $proxies = $pool->getAllProxies();
    assertTest('Добавление 3 валидных прокси', count($proxies) === 3);
} catch (Exception $e) {
    assertTest('Добавление валидных прокси', false, $e->getMessage());
}

// Тест добавления дубликата
try {
    $pool->addProxy('http://proxy1.example.com:8080');
    $proxies = $pool->getAllProxies();
    assertTest('Добавление дубликата (должен проигнорироваться)', count($proxies) === 3);
} catch (Exception $e) {
    assertTest('Добавление дубликата', false, $e->getMessage());
}

// Тест невалидного формата прокси
assertException(
    'Невалидный формат прокси',
    function () use ($pool) {
        $pool->addProxy('invalid-proxy');
    },
    ProxyPoolValidationException::class
);

// Тест пустого прокси
assertException(
    'Пустой прокси URL',
    function () use ($pool) {
        $pool->addProxy('');
    },
    ProxyPoolValidationException::class
);

// Тест удаления прокси
try {
    $pool->removeProxy('http://proxy1.example.com:8080');
    $proxies = $pool->getAllProxies();
    assertTest('Удаление прокси', count($proxies) === 2);
} catch (Exception $e) {
    assertTest('Удаление прокси', false, $e->getMessage());
}

// Тест удаления несуществующего прокси
try {
    $pool->removeProxy('http://nonexistent.proxy.com:8080');
    $proxies = $pool->getAllProxies();
    assertTest('Удаление несуществующего прокси', count($proxies) === 2);
} catch (Exception $e) {
    assertTest('Удаление несуществующего прокси', false, $e->getMessage());
}

echo "\n--- ТЕСТ 4: Round-robin ротация ---\n";

$pool = new ProxyPool([
    'rotation_strategy' => ProxyPool::ROTATION_ROUND_ROBIN,
    'auto_health_check' => false,
], $logger);

$pool->addProxy('http://proxy1.example.com:8080');
$pool->addProxy('http://proxy2.example.com:8080');
$pool->addProxy('http://proxy3.example.com:8080');

try {
    $proxy1 = $pool->getNextProxy();
    $proxy2 = $pool->getNextProxy();
    $proxy3 = $pool->getNextProxy();
    $proxy4 = $pool->getNextProxy(); // Должен вернуться к первому
    
    assertTest('Round-robin: первый прокси', $proxy1 !== null);
    assertTest('Round-robin: второй прокси отличается', $proxy1 !== $proxy2);
    assertTest('Round-robin: третий прокси отличается', $proxy2 !== $proxy3);
    assertTest('Round-robin: цикличность', $proxy1 === $proxy4);
} catch (Exception $e) {
    assertTest('Round-robin ротация', false, $e->getMessage());
}

echo "\n--- ТЕСТ 5: Random ротация ---\n";

$pool = new ProxyPool([
    'rotation_strategy' => ProxyPool::ROTATION_RANDOM,
    'auto_health_check' => false,
], $logger);

$pool->addProxy('http://proxy1.example.com:8080');
$pool->addProxy('http://proxy2.example.com:8080');
$pool->addProxy('http://proxy3.example.com:8080');

try {
    $proxiesSelected = [];
    for ($i = 0; $i < 20; $i++) {
        $proxy = $pool->getRandomProxy();
        if ($proxy !== null) {
            $proxiesSelected[$proxy] = true;
        }
    }
    
    // При 20 попытках с 3 прокси вероятность использовать все 3 очень высока
    assertTest('Random: выбор случайных прокси', count($proxiesSelected) >= 2);
} catch (Exception $e) {
    assertTest('Random ротация', false, $e->getMessage());
}

echo "\n--- ТЕСТ 6: Пометка прокси как живой/мёртвый ---\n";

$pool = new ProxyPool(['auto_health_check' => false], $logger);
$pool->addProxy('http://proxy1.example.com:8080');

try {
    // Помечаем как мёртвый
    $pool->markProxyAsDead('http://proxy1.example.com:8080');
    $nextProxy = $pool->getNextProxy();
    assertTest('Пометка как мёртвый: прокси недоступен', $nextProxy === null);
    
    // Помечаем как живой
    $pool->markProxyAsAlive('http://proxy1.example.com:8080');
    $nextProxy = $pool->getNextProxy();
    assertTest('Пометка как живой: прокси доступен', $nextProxy !== null);
} catch (Exception $e) {
    assertTest('Пометка прокси', false, $e->getMessage());
}

echo "\n--- ТЕСТ 7: Получение всех прокси ---\n";

$pool = new ProxyPool(['auto_health_check' => false], $logger);
$pool->addProxy('http://proxy1.example.com:8080');
$pool->addProxy('http://proxy2.example.com:8080');

try {
    $allProxies = $pool->getAllProxies();
    
    assertTest('getAllProxies: количество', count($allProxies) === 2);
    assertTest('getAllProxies: структура', 
        isset($allProxies['http://proxy1.example.com:8080']) &&
        isset($allProxies['http://proxy1.example.com:8080']['url']) &&
        isset($allProxies['http://proxy1.example.com:8080']['alive'])
    );
} catch (Exception $e) {
    assertTest('getAllProxies', false, $e->getMessage());
}

echo "\n--- ТЕСТ 8: Статистика ---\n";

$pool = new ProxyPool(['auto_health_check' => false], $logger);
$pool->addProxy('http://proxy1.example.com:8080');
$pool->addProxy('http://proxy2.example.com:8080');

// Имитируем успешные и неудачные запросы
$pool->markProxyAsAlive('http://proxy1.example.com:8080');
$pool->markProxyAsAlive('http://proxy1.example.com:8080');
$pool->markProxyAsDead('http://proxy1.example.com:8080');
$pool->markProxyAsAlive('http://proxy2.example.com:8080');

try {
    $stats = $pool->getStatistics();
    
    assertTest('Статистика: наличие ключей', 
        isset($stats['total_proxies']) &&
        isset($stats['alive_proxies']) &&
        isset($stats['dead_proxies']) &&
        isset($stats['rotation_strategy']) &&
        isset($stats['proxies'])
    );
    
    assertTest('Статистика: total_proxies', $stats['total_proxies'] === 2);
    assertTest('Статистика: alive_proxies', $stats['alive_proxies'] === 1);
    assertTest('Статистика: dead_proxies', $stats['dead_proxies'] === 1);
    assertTest('Статистика: детали прокси', count($stats['proxies']) === 2);
    
    // Проверяем детальную информацию о первом прокси
    $proxy1Stats = $stats['proxies'][0];
    assertTest('Статистика прокси: success_count', $proxy1Stats['success_count'] === 2);
    assertTest('Статистика прокси: fail_count', $proxy1Stats['fail_count'] === 1);
    assertTest('Статистика прокси: total_requests', $proxy1Stats['total_requests'] === 3);
    assertTest('Статистика прокси: success_rate', abs($proxy1Stats['success_rate'] - 66.67) < 0.1);
} catch (Exception $e) {
    assertTest('Статистика', false, $e->getMessage());
}

echo "\n--- ТЕСТ 9: Сброс статистики ---\n";

try {
    $pool->resetStatistics();
    $stats = $pool->getStatistics();
    
    assertTest('Сброс статистики: total_requests', $stats['total_requests'] === 0);
    assertTest('Сброс статистики: successful_requests', $stats['successful_requests'] === 0);
    assertTest('Сброс статистики: failed_requests', $stats['failed_requests'] === 0);
    
    $proxy1Stats = $stats['proxies'][0];
    assertTest('Сброс статистики прокси: success_count', $proxy1Stats['success_count'] === 0);
    assertTest('Сброс статистики прокси: fail_count', $proxy1Stats['fail_count'] === 0);
} catch (Exception $e) {
    assertTest('Сброс статистики', false, $e->getMessage());
}

echo "\n--- ТЕСТ 10: Очистка пула ---\n";

try {
    $pool->clearProxies();
    $proxies = $pool->getAllProxies();
    assertTest('Очистка пула', count($proxies) === 0);
} catch (Exception $e) {
    assertTest('Очистка пула', false, $e->getMessage());
}

echo "\n--- ТЕСТ 11: Health Check (реальный запрос) ---\n";

// Используем публичный тестовый прокси или пропускаем тест
$testProxy = 'http://proxy.example.com:8080'; // Замените на реальный если есть

$pool = new ProxyPool([
    'health_check_url' => 'https://httpbin.org/status/200',
    'health_check_timeout' => 5,
    'auto_health_check' => false,
], $logger);

$pool->addProxy($testProxy);

try {
    $isAlive = $pool->checkProxyHealth($testProxy);
    echo "   Health check для {$testProxy}: " . ($isAlive ? "ALIVE" : "DEAD") . "\n";
    assertTest('Health check: выполнение', true); // Просто проверяем что не падает
} catch (Exception $e) {
    assertTest('Health check', false, $e->getMessage());
}

echo "\n--- ТЕСТ 12: checkAllProxies ---\n";

$pool = new ProxyPool([
    'health_check_url' => 'https://httpbin.org/status/200',
    'health_check_timeout' => 5,
    'auto_health_check' => false,
], $logger);

$pool->addProxy('http://proxy1.example.com:8080');
$pool->addProxy('http://proxy2.example.com:8080');

try {
    $pool->checkAllProxies();
    $stats = $pool->getStatistics();
    
    // Проверяем что все прокси были проверены
    $allChecked = true;
    foreach ($stats['proxies'] as $proxyData) {
        if ($proxyData['last_check'] === 0) {
            $allChecked = false;
            break;
        }
    }
    
    assertTest('checkAllProxies: все прокси проверены', $allChecked);
} catch (Exception $e) {
    assertTest('checkAllProxies', false, $e->getMessage());
}

echo "\n--- ТЕСТ 13: getHttpClient ---\n";

$pool = new ProxyPool(['auto_health_check' => false], $logger);
$pool->addProxy('http://proxy1.example.com:8080');

try {
    $httpClient = $pool->getHttpClient();
    assertTest('getHttpClient: создание клиента', $httpClient instanceof \App\Component\Http);
} catch (Exception $e) {
    assertTest('getHttpClient', false, $e->getMessage());
}

// Тест без доступных прокси
$emptyPool = new ProxyPool(['auto_health_check' => false], $logger);
assertException(
    'getHttpClient: нет доступных прокси',
    function () use ($emptyPool) {
        $emptyPool->getHttpClient();
    },
    ProxyPoolException::class
);

echo "\n--- ТЕСТ 14: HTTP запросы через прокси (симуляция) ---\n";

// Примечание: реальные HTTP запросы требуют рабочих прокси
// Тестируем только что метод существует и вызывается без критических ошибок

$pool = new ProxyPool([
    'max_retries' => 2,
    'auto_health_check' => false,
], $logger);

$pool->addProxy('http://invalid.proxy1.example.com:8080');
$pool->addProxy('http://invalid.proxy2.example.com:8080');

try {
    // Ожидаем исключение, так как прокси невалидные
    $response = $pool->get('https://httpbin.org/ip');
    assertTest('HTTP GET через невалидные прокси', false, 'Ожидалось исключение');
} catch (ProxyPoolException $e) {
    assertTest('HTTP GET через невалидные прокси: исключение', true);
    
    // Проверяем что статистика обновилась
    $stats = $pool->getStatistics();
    assertTest('HTTP GET: статистика failed_requests', $stats['failed_requests'] > 0);
} catch (Exception $e) {
    assertTest('HTTP GET', false, $e->getMessage());
}

echo "\n--- ТЕСТ 15: Загрузка из конфигурации ---\n";

// Создаём временный конфигурационный файл
$configPath = __DIR__ . '/config/test_proxypool_config.json';
$configDir = dirname($configPath);

if (!is_dir($configDir)) {
    mkdir($configDir, 0755, true);
}

$configData = [
    'proxies' => [
        'http://proxy1.example.com:8080',
        'http://proxy2.example.com:8080',
    ],
    'rotation_strategy' => 'random',
    'health_check_url' => 'https://httpbin.org/status/200',
    'health_check_timeout' => 10,
    'auto_health_check' => false,
    'max_retries' => 5,
];

file_put_contents($configPath, json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

try {
    $pool = ProxyPool::fromConfig($configPath, $logger);
    $proxies = $pool->getAllProxies();
    
    assertTest('fromConfig: загрузка конфигурации', count($proxies) === 2);
    
    $stats = $pool->getStatistics();
    assertTest('fromConfig: стратегия ротации', $stats['rotation_strategy'] === 'random');
} catch (Exception $e) {
    assertTest('fromConfig', false, $e->getMessage());
}

// Тест загрузки несуществующего файла
assertException(
    'fromConfig: несуществующий файл',
    function () use ($logger) {
        ProxyPool::fromConfig('/nonexistent/config.json', $logger);
    },
    ProxyPoolException::class
);

echo "\n--- ТЕСТ 16: Проверка логирования ---\n";

// Создаём отдельный лог-файл для проверки
$testLogFile = __DIR__ . '/logs/test_logging_' . date('Y-m-d_His') . '.log';
$testLogger = new Logger([
    'directory' => dirname($testLogFile),
    'filename' => basename($testLogFile),
    'max_files' => 1,
]);

$pool = new ProxyPool(['auto_health_check' => false], $testLogger);
$pool->addProxy('http://proxy1.example.com:8080');
$pool->markProxyAsDead('http://proxy1.example.com:8080');
$pool->markProxyAsAlive('http://proxy1.example.com:8080');
$pool->removeProxy('http://proxy1.example.com:8080');

// Даём время на запись в файл и сброс буфера
unset($testLogger);
sleep(1);

// Проверяем существование файла
if (file_exists($testLogFile)) {
    $logContent = file_get_contents($testLogFile);
    
    assertTest('Логирование: инициализация', strpos($logContent, 'ProxyPool менеджер инициализирован') !== false);
    assertTest('Логирование: добавление прокси', strpos($logContent, 'Прокси добавлен в пул') !== false);
    assertTest('Логирование: пометка как мёртвый', strpos($logContent, 'Прокси помечен как мёртвый') !== false);
    assertTest('Логирование: пометка как живой', strpos($logContent, 'Прокси помечен как живой') !== false);
    assertTest('Логирование: удаление прокси', strpos($logContent, 'Прокси удален из пула') !== false);
} else {
    // Если файл не существует, проверим что Logger использует буферизацию
    assertTest('Логирование: файл создан', false, 'Лог файл не создан - возможно Logger использует буферизацию');
}

echo "\n--- ТЕСТ 17: Тестирование всех HTTP методов ---\n";

$pool = new ProxyPool([
    'max_retries' => 1,
    'auto_health_check' => false,
], $logger);

$pool->addProxy('http://invalid.proxy.example.com:8080');

// Тестируем что все методы существуют и корректно обрабатывают ошибки
$methods = ['get', 'post', 'put', 'delete'];

foreach ($methods as $method) {
    try {
        $pool->$method('https://httpbin.org/' . $method);
        assertTest("HTTP {$method}: ожидалось исключение", false);
    } catch (ProxyPoolException $e) {
        assertTest("HTTP {$method}: корректная обработка ошибки", true);
    } catch (Exception $e) {
        assertTest("HTTP {$method}", false, $e->getMessage());
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "ИТОГОВАЯ СТАТИСТИКА ТЕСТОВ\n";
echo str_repeat("=", 60) . "\n";
echo "Всего тестов:    {$totalTests}\n";
echo "Пройдено:        {$passedTests} (" . round($passedTests / $totalTests * 100, 2) . "%)\n";
echo "Провалено:       {$failedTests} (" . round($failedTests / $totalTests * 100, 2) . "%)\n";
echo str_repeat("=", 60) . "\n";

if ($failedTests === 0) {
    echo "\n✓ ВСЕ ТЕСТЫ ПРОЙДЕНЫ УСПЕШНО!\n\n";
    $logger->info('Все тесты ProxyPool пройдены успешно', [
        'total' => $totalTests,
        'passed' => $passedTests,
    ]);
} else {
    echo "\n✗ НЕКОТОРЫЕ ТЕСТЫ ПРОВАЛЕНЫ\n\n";
    $logger->warning('Некоторые тесты ProxyPool провалены', [
        'total' => $totalTests,
        'passed' => $passedTests,
        'failed' => $failedTests,
    ]);
}

echo "Лог-файл тестов: {$logFile}\n";
echo "Лог-файл логирования: {$testLogFile}\n\n";

// Очистка временных файлов
if (file_exists($configPath)) {
    unlink($configPath);
}

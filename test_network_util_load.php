<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Component\NetworkUtil;
use App\Component\Logger;

/**
 * Полноценный нагрузочный тест класса NetworkUtil
 * с реальными сетевыми командами и детальным логированием
 */

echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║  ПОЛНОЦЕННЫЙ НАГРУЗОЧНЫЙ ТЕСТ NetworkUtil                        ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

// Инициализация логгера
$logDir = __DIR__ . '/logs/network_util_test';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$logger = new Logger([
    'directory' => $logDir,
    'file_name' => 'network_util',
    'max_files' => 5,
    'max_file_size' => 10 * 1024 * 1024, // 10 MB
    'pattern' => '{timestamp} [{level}] {message} {context}',
    'date_format' => 'Y-m-d H:i:s',
    'log_buffer_size_bytes' => 8192,
]);

// Инициализация NetworkUtil
$networkUtil = new NetworkUtil([
    'default_timeout' => 30,
    'throw_on_error' => false, // Для нагрузочного теста не прерываем выполнение
], $logger);

$results = [];
$totalTests = 0;
$successfulTests = 0;
$failedTests = 0;
$totalDuration = 0.0;

/**
 * Вспомогательная функция для выполнения и логирования теста
 */
function runTest(string $testName, callable $testFunction, array &$results, int &$totalTests, int &$successfulTests, int &$failedTests, float &$totalDuration): void
{
    echo "\n" . str_repeat('─', 70) . "\n";
    echo "🧪 ТЕСТ: {$testName}\n";
    echo str_repeat('─', 70) . "\n";

    $startTime = microtime(true);
    
    try {
        $result = $testFunction();
        $duration = microtime(true) - $startTime;
        
        $totalTests++;
        $totalDuration += $duration;
        
        if ($result['success']) {
            $successfulTests++;
            echo "✅ УСПЕХ (код: {$result['exit_code']}, время: {$result['duration']}с)\n";
        } else {
            $failedTests++;
            echo "❌ ОШИБКА (код: {$result['exit_code']}, время: {$result['duration']}с)\n";
            if ($result['error']) {
                echo "   Ошибка: " . substr($result['error'], 0, 200) . "\n";
            }
        }
        
        if (!empty($result['output'])) {
            $outputLines = explode("\n", trim($result['output']));
            $displayLines = array_slice($outputLines, 0, 10);
            echo "\n📄 Вывод команды (первые 10 строк):\n";
            foreach ($displayLines as $line) {
                echo "   " . substr($line, 0, 120) . "\n";
            }
            if (count($outputLines) > 10) {
                echo "   ... и ещё " . (count($outputLines) - 10) . " строк\n";
            }
        }
        
        $results[$testName] = $result;
        
    } catch (Exception $e) {
        $duration = microtime(true) - $startTime;
        $totalTests++;
        $failedTests++;
        $totalDuration += $duration;
        
        echo "❌ ИСКЛЮЧЕНИЕ: " . $e->getMessage() . "\n";
        
        $results[$testName] = [
            'success' => false,
            'error' => $e->getMessage(),
            'exit_code' => -1,
            'duration' => round($duration, 3),
        ];
    }
}

// ═══════════════════════════════════════════════════════════════════
// БЛОК 1: ТЕСТЫ PING
// ═══════════════════════════════════════════════════════════════════
echo "\n\n╔══════════════════════════════════════════════════════════════════╗\n";
echo "║  БЛОК 1: ТЕСТЫ PING                                              ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n";

runTest('Ping localhost', function() use ($networkUtil) {
    return $networkUtil->ping('localhost', 3);
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

runTest('Ping 8.8.8.8 (Google DNS)', function() use ($networkUtil) {
    return $networkUtil->ping('8.8.8.8', 4);
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

runTest('Ping google.com', function() use ($networkUtil) {
    return $networkUtil->ping('google.com', 3);
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

runTest('Ping недостижимый хост', function() use ($networkUtil) {
    return $networkUtil->ping('192.168.255.254', 2, 5);
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

// ═══════════════════════════════════════════════════════════════════
// БЛОК 2: ТЕСТЫ DNS (DIG, NSLOOKUP, HOST)
// ═══════════════════════════════════════════════════════════════════
echo "\n\n╔══════════════════════════════════════════════════════════════════╗\n";
echo "║  БЛОК 2: ТЕСТЫ DNS                                               ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n";

runTest('Dig A запись google.com', function() use ($networkUtil) {
    return $networkUtil->dig('google.com', 'A');
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

runTest('Dig MX запись gmail.com', function() use ($networkUtil) {
    return $networkUtil->dig('gmail.com', 'MX');
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

runTest('Dig NS запись cloudflare.com', function() use ($networkUtil) {
    return $networkUtil->dig('cloudflare.com', 'NS');
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

runTest('Dig с указанием DNS сервера', function() use ($networkUtil) {
    return $networkUtil->dig('github.com', 'A', '1.1.1.1');
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

runTest('Nslookup yahoo.com', function() use ($networkUtil) {
    return $networkUtil->nslookup('yahoo.com');
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

runTest('Nslookup с DNS сервером 8.8.8.8', function() use ($networkUtil) {
    return $networkUtil->nslookup('amazon.com', '8.8.8.8');
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

runTest('Host wikipedia.org', function() use ($networkUtil) {
    return $networkUtil->host('wikipedia.org');
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

runTest('Host с DNS сервером', function() use ($networkUtil) {
    return $networkUtil->host('reddit.com', '1.1.1.1');
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

// ═══════════════════════════════════════════════════════════════════
// БЛОК 3: ТЕСТЫ WHOIS
// ═══════════════════════════════════════════════════════════════════
echo "\n\n╔══════════════════════════════════════════════════════════════════╗\n";
echo "║  БЛОК 3: ТЕСТЫ WHOIS                                             ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n";

runTest('Whois google.com', function() use ($networkUtil) {
    return $networkUtil->whois('google.com');
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

runTest('Whois 8.8.8.8', function() use ($networkUtil) {
    return $networkUtil->whois('8.8.8.8');
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

// ═══════════════════════════════════════════════════════════════════
// БЛОК 4: ТЕСТЫ TRACEROUTE И MTR
// ═══════════════════════════════════════════════════════════════════
echo "\n\n╔══════════════════════════════════════════════════════════════════╗\n";
echo "║  БЛОК 4: ТЕСТЫ TRACEROUTE И MTR                                  ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n";

runTest('Traceroute google.com', function() use ($networkUtil) {
    return $networkUtil->traceroute('google.com', 15, 20);
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

runTest('Traceroute 8.8.8.8', function() use ($networkUtil) {
    return $networkUtil->traceroute('8.8.8.8', 10, 15);
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

runTest('MTR google.com', function() use ($networkUtil) {
    return $networkUtil->mtr('google.com', 5, true, 30);
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

runTest('MTR cloudflare.com', function() use ($networkUtil) {
    return $networkUtil->mtr('1.1.1.1', 3, true, 25);
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

// ═══════════════════════════════════════════════════════════════════
// БЛОК 5: ТЕСТЫ HTTP (CURL, WGET)
// ═══════════════════════════════════════════════════════════════════
echo "\n\n╔══════════════════════════════════════════════════════════════════╗\n";
echo "║  БЛОК 5: ТЕСТЫ HTTP                                              ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n";

runTest('Curl GET google.com', function() use ($networkUtil) {
    return $networkUtil->curl('https://www.google.com', ['-I']);
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

runTest('Curl GET github.com', function() use ($networkUtil) {
    return $networkUtil->curl('https://github.com', ['-I', '-L']);
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

runTest('Curl с таймаутом', function() use ($networkUtil) {
    return $networkUtil->curl('https://httpbin.org/delay/2', ['--max-time', '5']);
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

runTest('Wget spider cloudflare.com', function() use ($networkUtil) {
    return $networkUtil->wget('https://www.cloudflare.com', ['--spider', '-q']);
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

runTest('Wget spider wikipedia.org', function() use ($networkUtil) {
    return $networkUtil->wget('https://en.wikipedia.org', ['--spider', '-q', '--timeout=10']);
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

// ═══════════════════════════════════════════════════════════════════
// БЛОК 6: ТЕСТЫ СЕТЕВОЙ ИНФОРМАЦИИ (NETSTAT, SS, IP, IFCONFIG, ARP)
// ═══════════════════════════════════════════════════════════════════
echo "\n\n╔══════════════════════════════════════════════════════════════════╗\n";
echo "║  БЛОК 6: ТЕСТЫ СЕТЕВОЙ ИНФОРМАЦИИ                                ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n";

runTest('Netstat listening ports', function() use ($networkUtil) {
    return $networkUtil->netstat(['-tuln']);
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

runTest('Netstat все соединения', function() use ($networkUtil) {
    return $networkUtil->netstat(['-a']);
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

runTest('SS listening sockets', function() use ($networkUtil) {
    return $networkUtil->ss(['-tuln']);
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

runTest('SS статистика', function() use ($networkUtil) {
    return $networkUtil->ss(['-s']);
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

runTest('IP адреса интерфейсов', function() use ($networkUtil) {
    return $networkUtil->ip(['addr', 'show']);
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

runTest('IP таблица маршрутизации', function() use ($networkUtil) {
    return $networkUtil->ip(['route', 'show']);
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

runTest('Ifconfig все интерфейсы', function() use ($networkUtil) {
    return $networkUtil->ifconfig();
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

runTest('Ifconfig конкретный интерфейс', function() use ($networkUtil) {
    return $networkUtil->ifconfig('lo');
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

runTest('ARP таблица', function() use ($networkUtil) {
    return $networkUtil->arp(['-a']);
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

// ═══════════════════════════════════════════════════════════════════
// БЛОК 7: ТЕСТЫ NMAP (если установлен)
// ═══════════════════════════════════════════════════════════════════
echo "\n\n╔══════════════════════════════════════════════════════════════════╗\n";
echo "║  БЛОК 7: ТЕСТЫ NMAP (опционально)                                ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n";

runTest('Nmap localhost', function() use ($networkUtil) {
    return $networkUtil->nmap('localhost', '22,80,443', [], 30);
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

runTest('Nmap сканирование портов', function() use ($networkUtil) {
    return $networkUtil->nmap('127.0.0.1', '1-100', [], 45);
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

// ═══════════════════════════════════════════════════════════════════
// БЛОК 8: ТЕСТЫ TCPDUMP (опционально, требует прав root)
// ═══════════════════════════════════════════════════════════════════
echo "\n\n╔══════════════════════════════════════════════════════════════════╗\n";
echo "║  БЛОК 8: ТЕСТЫ TCPDUMP (опционально, требует прав)               ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n";

runTest('Tcpdump на lo интерфейсе', function() use ($networkUtil) {
    return $networkUtil->tcpdump('lo', 5, 'icmp', 10);
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

// ═══════════════════════════════════════════════════════════════════
// БЛОК 9: СТРЕСС-ТЕСТЫ (множественные запросы)
// ═══════════════════════════════════════════════════════════════════
echo "\n\n╔══════════════════════════════════════════════════════════════════╗\n";
echo "║  БЛОК 9: СТРЕСС-ТЕСТЫ                                            ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n";

$hosts = ['google.com', 'github.com', 'cloudflare.com', 'amazon.com', 'microsoft.com'];

echo "\n🔄 Выполнение серии ping запросов к {count} хостам...\n";
$stressStartTime = microtime(true);
$stressResults = [];

foreach ($hosts as $host) {
    $result = $networkUtil->ping($host, 2, 5);
    $stressResults[] = $result;
    echo ($result['success'] ? '✓' : '✗') . " {$host} ";
}

$stressDuration = microtime(true) - $stressStartTime;
echo "\n\n⏱️  Общее время стресс-теста: " . round($stressDuration, 2) . "с\n";

$stressSuccessful = count(array_filter($stressResults, fn($r) => $r['success']));
echo "📊 Успешных запросов: {$stressSuccessful}/" . count($hosts) . "\n";

// ═══════════════════════════════════════════════════════════════════
// БЛОК 10: ТЕСТЫ ВАЛИДАЦИИ
// ═══════════════════════════════════════════════════════════════════
echo "\n\n╔══════════════════════════════════════════════════════════════════╗\n";
echo "║  БЛОК 10: ТЕСТЫ ВАЛИДАЦИИ                                        ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n";

runTest('Валидация: пустой хост', function() use ($networkUtil) {
    try {
        return $networkUtil->ping('', 1);
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'exit_code' => -1,
            'duration' => 0.0,
        ];
    }
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

runTest('Валидация: некорректный URL', function() use ($networkUtil) {
    try {
        return $networkUtil->curl('invalid-url', ['-I']);
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'exit_code' => -1,
            'duration' => 0.0,
        ];
    }
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

runTest('Валидация: опасные символы в хосте', function() use ($networkUtil) {
    try {
        return $networkUtil->ping('test;rm -rf', 1);
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'exit_code' => -1,
            'duration' => 0.0,
        ];
    }
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

runTest('Валидация: некорректный тип DNS записи', function() use ($networkUtil) {
    try {
        return $networkUtil->dig('google.com', 'INVALID');
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'exit_code' => -1,
            'duration' => 0.0,
        ];
    }
}, $results, $totalTests, $successfulTests, $failedTests, $totalDuration);

// ═══════════════════════════════════════════════════════════════════
// ИТОГОВЫЙ ОТЧЁТ
// ═══════════════════════════════════════════════════════════════════
echo "\n\n";
echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║  ИТОГОВЫЙ ОТЧЁТ                                                  ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

echo "📊 СТАТИСТИКА ВЫПОЛНЕНИЯ:\n";
echo str_repeat('─', 70) . "\n";
echo sprintf("Всего тестов: %d\n", $totalTests);
echo sprintf("✅ Успешных: %d (%.1f%%)\n", $successfulTests, $totalTests > 0 ? ($successfulTests / $totalTests * 100) : 0);
echo sprintf("❌ Неудачных: %d (%.1f%%)\n", $failedTests, $totalTests > 0 ? ($failedTests / $totalTests * 100) : 0);
echo sprintf("⏱️  Общее время: %.2fс\n", $totalDuration);
echo sprintf("⚡ Среднее время на тест: %.3fс\n", $totalTests > 0 ? ($totalDuration / $totalTests) : 0);

echo "\n📋 ДЕТАЛЬНАЯ СТАТИСТИКА ПО КОМАНДАМ:\n";
echo str_repeat('─', 70) . "\n";

$commandStats = [];
foreach ($results as $testName => $result) {
    $command = $result['command'] ?? 'unknown';
    if (!isset($commandStats[$command])) {
        $commandStats[$command] = [
            'total' => 0,
            'success' => 0,
            'failed' => 0,
            'total_duration' => 0.0,
        ];
    }
    $commandStats[$command]['total']++;
    if ($result['success']) {
        $commandStats[$command]['success']++;
    } else {
        $commandStats[$command]['failed']++;
    }
    $commandStats[$command]['total_duration'] += $result['duration'] ?? 0.0;
}

foreach ($commandStats as $command => $stats) {
    $avgDuration = $stats['total'] > 0 ? ($stats['total_duration'] / $stats['total']) : 0;
    echo sprintf(
        "%-15s | Всего: %2d | Успешно: %2d | Неудачно: %2d | Ср. время: %.3fс\n",
        $command,
        $stats['total'],
        $stats['success'],
        $stats['failed'],
        $avgDuration
    );
}

echo "\n💾 ЛОГИ СОХРАНЕНЫ В: {$logDir}\n";

echo "\n\n╔══════════════════════════════════════════════════════════════════╗\n";
echo "║  ТЕСТИРОВАНИЕ ЗАВЕРШЕНО                                          ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n";

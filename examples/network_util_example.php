<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Component\NetworkUtil;
use App\Component\Logger;

/**
 * Примеры использования класса NetworkUtil
 */

echo "═══════════════════════════════════════════════════════════════\n";
echo "  ПРИМЕРЫ ИСПОЛЬЗОВАНИЯ NetworkUtil\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Инициализация логгера
$logDir = __DIR__ . '/../logs/network_util_examples';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$logger = new Logger([
    'directory' => $logDir,
    'file_name' => 'network_util_examples',
    'max_files' => 3,
    'max_file_size' => 5 * 1024 * 1024,
]);

// Инициализация NetworkUtil
$networkUtil = new NetworkUtil([
    'default_timeout' => 30,
    'throw_on_error' => false, // Для примеров не прерываем выполнение
], $logger);

// ═══════════════════════════════════════════════════════════════
// ПРИМЕР 1: Проверка доступности хоста с помощью ping
// ═══════════════════════════════════════════════════════════════
echo "ПРИМЕР 1: Проверка доступности хоста\n";
echo "─────────────────────────────────────\n";

$result = $networkUtil->ping('google.com', 4);

if ($result['success']) {
    echo "✅ Хост google.com доступен\n";
    echo "Время выполнения: {$result['duration']}с\n";
    echo "Первые 3 строки вывода:\n";
    $lines = explode("\n", trim($result['output']));
    foreach (array_slice($lines, 0, 3) as $line) {
        echo "  $line\n";
    }
} else {
    echo "❌ Хост недоступен: {$result['error']}\n";
}

// ═══════════════════════════════════════════════════════════════
// ПРИМЕР 2: DNS запросы
// ═══════════════════════════════════════════════════════════════
echo "\n\nПРИМЕР 2: DNS запросы\n";
echo "─────────────────────────────────────\n";

// A запись
$result = $networkUtil->dig('github.com', 'A');
echo "📧 A запись для github.com:\n";
if ($result['success']) {
    $lines = explode("\n", trim($result['output']));
    foreach ($lines as $line) {
        if (strpos($line, 'github.com') !== false && strpos($line, 'IN') !== false) {
            echo "  $line\n";
        }
    }
}

// MX запись
$result = $networkUtil->dig('gmail.com', 'MX');
echo "\n📧 MX записи для gmail.com:\n";
if ($result['success']) {
    $lines = explode("\n", trim($result['output']));
    foreach ($lines as $line) {
        if (strpos($line, 'MX') !== false && strpos($line, 'gmail.com') !== false) {
            echo "  $line\n";
        }
    }
}

// ═══════════════════════════════════════════════════════════════
// ПРИМЕР 3: WHOIS информация
// ═══════════════════════════════════════════════════════════════
echo "\n\nПРИМЕР 3: WHOIS информация о домене\n";
echo "─────────────────────────────────────\n";

$result = $networkUtil->whois('google.com');
if ($result['success']) {
    echo "✅ WHOIS данные получены\n";
    echo "Размер ответа: " . strlen($result['output']) . " байт\n";
    $lines = explode("\n", trim($result['output']));
    echo "Первые 5 строк:\n";
    foreach (array_slice($lines, 0, 5) as $line) {
        echo "  $line\n";
    }
}

// ═══════════════════════════════════════════════════════════════
// ПРИМЕР 4: Трассировка маршрута
// ═══════════════════════════════════════════════════════════════
echo "\n\nПРИМЕР 4: Трассировка маршрута до хоста\n";
echo "─────────────────────────────────────\n";

$result = $networkUtil->traceroute('8.8.8.8', 10, 15);
if ($result['success']) {
    echo "✅ Трассировка выполнена за {$result['duration']}с\n";
    $lines = explode("\n", trim($result['output']));
    echo "Результат:\n";
    foreach (array_slice($lines, 0, 8) as $line) {
        echo "  $line\n";
    }
}

// ═══════════════════════════════════════════════════════════════
// ПРИМЕР 5: HTTP проверка с curl
// ═══════════════════════════════════════════════════════════════
echo "\n\nПРИМЕР 5: HTTP проверка доступности сайта\n";
echo "─────────────────────────────────────\n";

$result = $networkUtil->curl('https://github.com', ['-I', '-L']);
if ($result['success']) {
    echo "✅ Сайт доступен\n";
    $lines = explode("\n", trim($result['output']));
    echo "HTTP заголовки:\n";
    foreach (array_slice($lines, 0, 10) as $line) {
        if (!empty(trim($line))) {
            echo "  $line\n";
        }
    }
}

// ═══════════════════════════════════════════════════════════════
// ПРИМЕР 6: Информация о сетевых интерфейсах
// ═══════════════════════════════════════════════════════════════
echo "\n\nПРИМЕР 6: Информация о сетевых интерфейсах\n";
echo "─────────────────────────────────────\n";

$result = $networkUtil->ip(['addr', 'show']);
if ($result['success']) {
    echo "✅ Список интерфейсов:\n";
    $lines = explode("\n", trim($result['output']));
    foreach ($lines as $line) {
        if (preg_match('/^\d+:\s+(\w+):/', $line, $matches)) {
            echo "  • Интерфейс: {$matches[1]}\n";
        }
    }
}

// ═══════════════════════════════════════════════════════════════
// ПРИМЕР 7: Статистика сетевых соединений
// ═══════════════════════════════════════════════════════════════
echo "\n\nПРИМЕР 7: Статистика сетевых соединений (ss)\n";
echo "─────────────────────────────────────\n";

$result = $networkUtil->ss(['-s']);
if ($result['success']) {
    echo "✅ Статистика получена:\n";
    $lines = explode("\n", trim($result['output']));
    foreach (array_slice($lines, 0, 10) as $line) {
        if (!empty(trim($line))) {
            echo "  $line\n";
        }
    }
}

// ═══════════════════════════════════════════════════════════════
// ПРИМЕР 8: Сканирование портов с nmap
// ═══════════════════════════════════════════════════════════════
echo "\n\nПРИМЕР 8: Сканирование портов localhost\n";
echo "─────────────────────────────────────\n";

$result = $networkUtil->nmap('localhost', '22,80,443', [], 30);
if ($result['success']) {
    echo "✅ Сканирование завершено за {$result['duration']}с\n";
    echo "Результат:\n";
    $lines = explode("\n", trim($result['output']));
    foreach ($lines as $line) {
        if (strpos($line, '/tcp') !== false || strpos($line, 'STATE') !== false) {
            echo "  $line\n";
        }
    }
}

// ═══════════════════════════════════════════════════════════════
// ПРИМЕР 9: Обработка ошибок
// ═══════════════════════════════════════════════════════════════
echo "\n\nПРИМЕР 9: Обработка ошибок валидации\n";
echo "─────────────────────────────────────\n";

try {
    $result = $networkUtil->ping('', 1);
} catch (Exception $e) {
    echo "❌ Перехвачено исключение: {$e->getMessage()}\n";
}

try {
    $result = $networkUtil->curl('not-a-valid-url', ['-I']);
} catch (Exception $e) {
    echo "❌ Перехвачено исключение: {$e->getMessage()}\n";
}

// ═══════════════════════════════════════════════════════════════
// ПРИМЕР 10: Использование с throw_on_error = true
// ═══════════════════════════════════════════════════════════════
echo "\n\nПРИМЕР 10: Режим с выбросом исключений\n";
echo "─────────────────────────────────────\n";

$strictNetworkUtil = new NetworkUtil([
    'default_timeout' => 10,
    'throw_on_error' => true,
], $logger);

try {
    $result = $strictNetworkUtil->ping('192.168.255.254', 1, 2);
    echo "✅ Хост доступен\n";
} catch (Exception $e) {
    echo "❌ Хост недоступен (выброшено исключение)\n";
}

echo "\n═══════════════════════════════════════════════════════════════\n";
echo "  ПРИМЕРЫ ЗАВЕРШЕНЫ\n";
echo "═══════════════════════════════════════════════════════════════\n";

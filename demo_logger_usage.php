<?php

declare(strict_types=1);

/**
 * Демонстрация реального использования класса Logger
 * 
 * Этот скрипт показывает различные сценарии использования Logger
 * в реальных приложениях
 */

require_once __DIR__ . '/autoload.php';

use App\Component\Logger;

// Цвета для консольного вывода
define('GREEN', "\033[32m");
define('YELLOW', "\033[33m");
define('BLUE', "\033[34m");
define('CYAN', "\033[36m");
define('RESET', "\033[0m");
define('BOLD', "\033[1m");

echo BOLD . CYAN . "\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo "           ДЕМОНСТРАЦИЯ РЕАЛЬНОГО ИСПОЛЬЗОВАНИЯ КЛАССА Logger               \n";
echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo RESET . "\n";

// Создаем директорию для демонстрации
$demoDir = sys_get_temp_dir() . '/logger_demo_' . date('Y-m-d_H-i-s');
mkdir($demoDir, 0777, true);

echo YELLOW . "📁 Рабочая директория: " . RESET . $demoDir . "\n\n";

// ===============================================================================
// ПРИМЕР 1: Базовое использование
// ===============================================================================
echo BOLD . "━━━ ПРИМЕР 1: Базовое логирование приложения ━━━\n" . RESET;

$appLogger = new Logger([
    'directory' => $demoDir . '/app',
    'file_name' => 'application.log',
    'max_files' => 5,
    'max_file_size' => 2, // MB
]);

$appLogger->info('Приложение запущено');
$appLogger->debug('Загрузка конфигурации', ['config_file' => '/etc/app/config.json']);
$appLogger->info('Подключение к базе данных', [
    'host' => 'localhost',
    'database' => 'myapp',
    'user' => 'appuser'
]);
$appLogger->warning('Медленный запрос к БД', [
    'query' => 'SELECT * FROM users WHERE active = 1',
    'duration_ms' => 1523
]);
$appLogger->flush();

echo GREEN . "✓ Записано 4 сообщения в application.log\n" . RESET;
displayLogContent($demoDir . '/app/application.log');

// ===============================================================================
// ПРИМЕР 2: Логирование HTTP запросов
// ===============================================================================
echo "\n" . BOLD . "━━━ ПРИМЕР 2: Логирование HTTP запросов ━━━\n" . RESET;

$httpLogger = new Logger([
    'directory' => $demoDir . '/http',
    'file_name' => 'access.log',
    'pattern' => '{timestamp} [{level}] {message}',
    'date_format' => 'd/M/Y:H:i:s O',
    'log_buffer_size' => 10, // KB
]);

// Имитация HTTP запросов
$requests = [
    ['method' => 'GET', 'path' => '/', 'status' => 200, 'time' => 45],
    ['method' => 'POST', 'path' => '/api/users', 'status' => 201, 'time' => 123],
    ['method' => 'GET', 'path' => '/api/products', 'status' => 200, 'time' => 89],
    ['method' => 'DELETE', 'path' => '/api/users/123', 'status' => 404, 'time' => 34],
    ['method' => 'POST', 'path' => '/login', 'status' => 401, 'time' => 67],
];

foreach ($requests as $req) {
    $level = $req['status'] >= 400 ? 'error' : 'info';
    $httpLogger->log(
        $level,
        "{$req['method']} {$req['path']} - {$req['status']} ({$req['time']}ms)",
        ['ip' => '192.168.1.100', 'user_agent' => 'Mozilla/5.0']
    );
}
$httpLogger->flush();

echo GREEN . "✓ Записано " . count($requests) . " HTTP запросов в access.log\n" . RESET;
displayLogContent($demoDir . '/http/access.log');

// ===============================================================================
// ПРИМЕР 3: Обработка ошибок и исключений
// ===============================================================================
echo "\n" . BOLD . "━━━ ПРИМЕР 3: Логирование ошибок и исключений ━━━\n" . RESET;

$errorLogger = new Logger([
    'directory' => $demoDir . '/errors',
    'file_name' => 'error.log',
    'pattern' => '{timestamp} {level} {message} {context}',
]);

// Имитация ошибок
try {
    throw new RuntimeException('Не удалось подключиться к внешнему API');
} catch (RuntimeException $e) {
    $errorLogger->error('Ошибка подключения к API', [
        'exception' => get_class($e),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => array_slice($e->getTrace(), 0, 3)
    ]);
}

try {
    throw new InvalidArgumentException('Некорректный email адрес: "invalid-email"');
} catch (InvalidArgumentException $e) {
    $errorLogger->warning('Ошибка валидации', [
        'type' => 'validation_error',
        'field' => 'email',
        'value' => 'invalid-email',
        'error' => $e->getMessage()
    ]);
}

$errorLogger->critical('Критическая ошибка системы', [
    'error' => 'Недостаточно места на диске',
    'free_space' => '100MB',
    'required' => '500MB',
    'action' => 'Немедленно требуется вмешательство администратора'
]);

$errorLogger->flush();

echo GREEN . "✓ Записано 3 ошибки разных уровней в error.log\n" . RESET;
displayLogContent($demoDir . '/errors/error.log');

// ===============================================================================
// ПРИМЕР 4: Многоуровневое логирование по модулям
// ===============================================================================
echo "\n" . BOLD . "━━━ ПРИМЕР 4: Логирование по модулям системы ━━━\n" . RESET;

// Модуль аутентификации
$authLogger = new Logger([
    'directory' => $demoDir . '/modules',
    'file_name' => 'auth.log',
]);

$authLogger->info('Попытка входа', ['username' => 'admin', 'ip' => '192.168.1.50']);
$authLogger->info('Успешная аутентификация', ['user_id' => 123, 'session_id' => 'abc123']);
$authLogger->warning('Неудачная попытка входа', [
    'username' => 'hacker',
    'ip' => '45.67.89.12',
    'reason' => 'Неверный пароль',
    'attempts' => 5
]);
$authLogger->flush();

// Модуль платежей
$paymentLogger = new Logger([
    'directory' => $demoDir . '/modules',
    'file_name' => 'payments.log',
]);

$paymentLogger->info('Новый платеж инициирован', [
    'order_id' => 'ORD-12345',
    'amount' => 1999.99,
    'currency' => 'RUB',
    'user_id' => 456
]);
$paymentLogger->info('Платеж обработан', [
    'transaction_id' => 'TXN-67890',
    'status' => 'success',
    'payment_method' => 'card'
]);
$paymentLogger->error('Ошибка обработки платежа', [
    'order_id' => 'ORD-12346',
    'error_code' => 'INSUFFICIENT_FUNDS',
    'amount' => 5000.00
]);
$paymentLogger->flush();

echo GREEN . "✓ Создано 2 модульных лога (auth.log, payments.log)\n" . RESET;
echo CYAN . "  auth.log:" . RESET . "\n";
displayLogContent($demoDir . '/modules/auth.log', 3);
echo CYAN . "  payments.log:" . RESET . "\n";
displayLogContent($demoDir . '/modules/payments.log', 3);

// ===============================================================================
// ПРИМЕР 5: Производительное логирование с буферизацией
// ===============================================================================
echo "\n" . BOLD . "━━━ ПРИМЕР 5: Высокопроизводительное логирование ━━━\n" . RESET;

$perfLogger = new Logger([
    'directory' => $demoDir . '/performance',
    'file_name' => 'high_volume.log',
    'log_buffer_size' => 64, // 64 KB буфер
]);

$startTime = microtime(true);

// Имитация высоконагруженного приложения
for ($i = 1; $i <= 500; $i++) {
    $perfLogger->debug("Обработка задачи #{$i}", [
        'queue' => 'default',
        'worker' => 'worker-' . ($i % 5 + 1),
        'duration_ms' => rand(10, 100)
    ]);
}

$perfLogger->flush();
$duration = microtime(true) - $startTime;

echo GREEN . "✓ Записано 500 сообщений за " . number_format($duration * 1000, 2) . " мс\n" . RESET;
echo CYAN . "  Производительность: " . number_format(500 / $duration, 0) . " записей/сек\n" . RESET;
displayLogContent($demoDir . '/performance/high_volume.log', 5);

// ===============================================================================
// ПРИМЕР 6: Ротация файлов при большом объеме данных
// ===============================================================================
echo "\n" . BOLD . "━━━ ПРИМЕР 6: Демонстрация ротации файлов ━━━\n" . RESET;

$rotatingLogger = new Logger([
    'directory' => $demoDir . '/rotation',
    'file_name' => 'rotating.log',
    'max_files' => 3,
    'max_file_size' => 1, // 1 MB для демонстрации
    'log_buffer_size' => 0, // Без буфера для немедленной ротации
]);

// Записываем много данных для триггера ротации
$largeMessage = str_repeat('А', 5000); // 5KB сообщение

for ($i = 1; $i <= 300; $i++) {
    $rotatingLogger->info("Большое сообщение #{$i}: {$largeMessage}");
}

// Проверяем созданные файлы
$files = glob($demoDir . '/rotation/*.log*');
sort($files);

echo GREEN . "✓ Создано файлов после ротации: " . count($files) . "\n" . RESET;
foreach ($files as $file) {
    $size = filesize($file);
    echo CYAN . "  " . basename($file) . " - " . number_format($size / 1024, 2) . " KB\n" . RESET;
}

// ===============================================================================
// ПРИМЕР 7: Условное логирование
// ===============================================================================
echo "\n" . BOLD . "━━━ ПРИМЕР 7: Условное логирование (enable/disable) ━━━\n" . RESET;

$conditionalLogger = new Logger([
    'directory' => $demoDir . '/conditional',
    'file_name' => 'conditional.log',
]);

$debugMode = false; // Отключаем debug логи в production

if (!$debugMode) {
    $conditionalLogger->disable();
}

$conditionalLogger->debug('Это debug сообщение (не будет записано)');
$conditionalLogger->info('Это info сообщение (тоже не будет записано)');

$conditionalLogger->enable();

$conditionalLogger->info('Важное сообщение (будет записано)');
$conditionalLogger->error('Ошибка (будет записана)');
$conditionalLogger->flush();

echo GREEN . "✓ Условное логирование работает корректно\n" . RESET;
displayLogContent($demoDir . '/conditional/conditional.log');

// ===============================================================================
// ПРИМЕР 8: Кастомное форматирование
// ===============================================================================
echo "\n" . BOLD . "━━━ ПРИМЕР 8: Пользовательское форматирование ━━━\n" . RESET;

// JSON формат
$jsonLogger = new Logger([
    'directory' => $demoDir . '/formats',
    'file_name' => 'json_format.log',
    'pattern' => '{"time":"{timestamp}","level":"{level}","msg":"{message}","data":{context}}',
    'date_format' => 'c', // ISO 8601
]);

$jsonLogger->info('API request received', [
    'endpoint' => '/api/v1/users',
    'method' => 'GET',
    'response_time' => 145
]);
$jsonLogger->flush();

// Простой формат
$simpleLogger = new Logger([
    'directory' => $demoDir . '/formats',
    'file_name' => 'simple_format.log',
    'pattern' => '[{level}] {message}',
]);

$simpleLogger->warning('Простое предупреждение');
$simpleLogger->error('Простая ошибка');
$simpleLogger->flush();

echo GREEN . "✓ Создано 2 лога с разными форматами\n" . RESET;
echo CYAN . "  JSON формат:" . RESET . "\n";
displayLogContent($demoDir . '/formats/json_format.log');
echo CYAN . "  Простой формат:" . RESET . "\n";
displayLogContent($demoDir . '/formats/simple_format.log');

// ===============================================================================
// ИТОГОВАЯ СТАТИСТИКА
// ===============================================================================
echo "\n" . BOLD . CYAN;
echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo "                              ИТОГОВАЯ СТАТИСТИКА                              \n";
echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo RESET;

$totalFiles = countFiles($demoDir);
$totalSize = getDirSize($demoDir);

echo "\n";
echo YELLOW . "📊 Всего создано файлов логов: " . RESET . $totalFiles . "\n";
echo YELLOW . "💾 Общий размер логов: " . RESET . number_format($totalSize / 1024, 2) . " KB\n";
echo YELLOW . "📁 Директория с демо-данными: " . RESET . $demoDir . "\n";
echo "\n";

echo GREEN . "✓ Демонстрация завершена успешно!\n" . RESET;
echo CYAN . "💡 Проверьте созданные файлы для изучения формата логов.\n" . RESET;
echo "\n";

// ===============================================================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// ===============================================================================

/**
 * Отображает содержимое лог-файла
 */
function displayLogContent(string $filePath, int $maxLines = 10): void
{
    if (!file_exists($filePath)) {
        echo "  " . YELLOW . "(файл не создан)\n" . RESET;
        return;
    }
    
    $content = file_get_contents($filePath);
    $lines = explode("\n", trim($content));
    $displayLines = array_slice($lines, 0, $maxLines);
    
    foreach ($displayLines as $line) {
        if (trim($line) !== '') {
            // Обрезаем длинные строки для читаемости
            $displayLine = strlen($line) > 100 ? substr($line, 0, 97) . '...' : $line;
            echo "  " . RESET . $displayLine . "\n";
        }
    }
    
    $remaining = count($lines) - count($displayLines);
    if ($remaining > 0) {
        echo "  " . CYAN . "... и еще {$remaining} строк(и)\n" . RESET;
    }
}

/**
 * Подсчитывает количество файлов в директории рекурсивно
 */
function countFiles(string $dir): int
{
    $count = 0;
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $count++;
        }
    }
    
    return $count;
}

/**
 * Вычисляет размер директории рекурсивно
 */
function getDirSize(string $dir): int
{
    $size = 0;
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $size += $file->getSize();
        }
    }
    
    return $size;
}

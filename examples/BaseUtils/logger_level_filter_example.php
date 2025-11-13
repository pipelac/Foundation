<?php

declare(strict_types=1);

require_once __DIR__ . '/../../autoload.php';

use App\Component\Logger;

echo "=== Примеры фильтрации уровней логирования ===\n\n";

$logsDir = __DIR__ . '/../logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

// Пример 1: DEBUG уровень - логируются все сообщения
echo "=== Пример 1: log_level = DEBUG (все сообщения) ===\n";
$debugLogger = new Logger([
    'directory' => $logsDir,
    'file_name' => 'debug_level.log',
    'log_level' => 'DEBUG',
    'max_files' => 1,
    'max_file_size' => 1,
]);

$debugLogger->debug('DEBUG: Детальная отладочная информация');
$debugLogger->info('INFO: Информационное сообщение');
$debugLogger->warning('WARNING: Предупреждение');
$debugLogger->error('ERROR: Ошибка');
$debugLogger->critical('CRITICAL: Критическая ошибка');

echo "✓ Все 5 сообщений записаны в debug_level.log\n\n";

// Пример 2: INFO уровень - DEBUG не логируется
echo "=== Пример 2: log_level = INFO (без DEBUG) ===\n";
$infoLogger = new Logger([
    'directory' => $logsDir,
    'file_name' => 'info_level.log',
    'log_level' => 'INFO',
    'max_files' => 1,
    'max_file_size' => 1,
]);

$infoLogger->debug('DEBUG: Это сообщение НЕ попадет в лог');
$infoLogger->info('INFO: Информационное сообщение');
$infoLogger->warning('WARNING: Предупреждение');
$infoLogger->error('ERROR: Ошибка');
$infoLogger->critical('CRITICAL: Критическая ошибка');

echo "✓ 4 сообщения записаны в info_level.log (DEBUG отфильтрован)\n\n";

// Пример 3: WARNING уровень - только предупреждения и ошибки
echo "=== Пример 3: log_level = WARNING (только предупреждения и ошибки) ===\n";
$warningLogger = new Logger([
    'directory' => $logsDir,
    'file_name' => 'warning_level.log',
    'log_level' => 'WARNING',
    'max_files' => 1,
    'max_file_size' => 1,
]);

$warningLogger->debug('DEBUG: Это НЕ попадет в лог');
$warningLogger->info('INFO: Это НЕ попадет в лог');
$warningLogger->warning('WARNING: Предупреждение');
$warningLogger->error('ERROR: Ошибка');
$warningLogger->critical('CRITICAL: Критическая ошибка');

echo "✓ 3 сообщения записаны в warning_level.log (DEBUG и INFO отфильтрованы)\n\n";

// Пример 4: ERROR уровень - только ошибки
echo "=== Пример 4: log_level = ERROR (только ошибки) ===\n";
$errorLogger = new Logger([
    'directory' => $logsDir,
    'file_name' => 'error_level.log',
    'log_level' => 'ERROR',
    'max_files' => 1,
    'max_file_size' => 1,
]);

$errorLogger->debug('DEBUG: Отфильтровано');
$errorLogger->info('INFO: Отфильтровано');
$errorLogger->warning('WARNING: Отфильтровано');
$errorLogger->error('ERROR: Ошибка');
$errorLogger->critical('CRITICAL: Критическая ошибка');

echo "✓ 2 сообщения записаны в error_level.log (только ERROR и CRITICAL)\n\n";

// Пример 5: CRITICAL уровень - только критические ошибки
echo "=== Пример 5: log_level = CRITICAL (только критические) ===\n";
$criticalLogger = new Logger([
    'directory' => $logsDir,
    'file_name' => 'critical_level.log',
    'log_level' => 'CRITICAL',
    'max_files' => 1,
    'max_file_size' => 1,
]);

$criticalLogger->debug('DEBUG: Отфильтровано');
$criticalLogger->info('INFO: Отфильтровано');
$criticalLogger->warning('WARNING: Отфильтровано');
$criticalLogger->error('ERROR: Отфильтровано');
$criticalLogger->critical('CRITICAL: Критическая ошибка');

echo "✓ 1 сообщение записано в critical_level.log (только CRITICAL)\n\n";

// Пример 6: Регистронезависимость
echo "=== Пример 6: Регистронезависимость параметра log_level ===\n";
$lowercaseLogger = new Logger([
    'directory' => $logsDir,
    'file_name' => 'lowercase_level.log',
    'log_level' => 'info', // lowercase
    'max_files' => 1,
    'max_file_size' => 1,
]);

$lowercaseLogger->debug('DEBUG: Отфильтровано');
$lowercaseLogger->info('INFO: Записано');

echo "✓ Параметр 'info' (lowercase) работает корректно\n\n";

// Пример 7: Использование min_level (альтернативное название)
echo "=== Пример 7: Использование min_level вместо log_level ===\n";
$minLevelLogger = new Logger([
    'directory' => $logsDir,
    'file_name' => 'min_level.log',
    'min_level' => 'WARNING', // Альтернативное название параметра
    'max_files' => 1,
    'max_file_size' => 1,
]);

$minLevelLogger->info('INFO: Отфильтровано');
$minLevelLogger->warning('WARNING: Записано');

echo "✓ Параметр 'min_level' работает корректно\n\n";

// Пример 8: Реальный production кейс
echo "=== Пример 8: Production конфигурация ===\n";
$productionLogger = new Logger([
    'directory' => $logsDir,
    'file_name' => 'production.log',
    'log_level' => 'INFO',       // Отключить DEBUG в production
    'max_files' => 30,           // 30 дней истории
    'max_file_size' => 100,      // 100 МБ на файл
    'log_buffer_size' => 256,    // Буфер 256 КБ для производительности
    'pattern' => '{timestamp} [{level}] {message} {context}',
]);

// Имитация production логирования
$productionLogger->debug('Детальная отладка', ['step' => 1]); // НЕ попадет в лог
$productionLogger->info('Запрос обработан', ['duration' => 0.15]); // Попадет
$productionLogger->warning('Медленный запрос', ['duration' => 2.5]); // Попадет
$productionLogger->error('Ошибка подключения', ['service' => 'payment']); // Попадет

echo "✓ Production логирование настроено (DEBUG отключен для производительности)\n\n";

// Пример 9: Development конфигурация
echo "=== Пример 9: Development конфигурация ===\n";
$devLogger = new Logger([
    'directory' => $logsDir,
    'file_name' => 'development.log',
    'log_level' => 'DEBUG',      // Все логи включая DEBUG
    'max_files' => 3,
    'max_file_size' => 10,
    'log_buffer_size' => 0,      // Без буферизации для немедленной записи
    'pattern' => '[{timestamp}] {level}: {message} | {context}',
]);

// Имитация development логирования
$devLogger->debug('Начало обработки', ['request_id' => 'abc123']); // Попадет
$devLogger->debug('SQL запрос', ['query' => 'SELECT * FROM users']); // Попадет
$devLogger->info('Запрос выполнен', ['rows' => 150]); // Попадет

echo "✓ Development логирование настроено (все уровни включены)\n\n";

echo "==============================================\n";
echo "✅ Все примеры выполнены успешно!\n";
echo "==============================================\n";
echo "\nИерархия уровней логирования (от низшего к высшему):\n";
echo "  DEBUG < INFO < WARNING < ERROR < CRITICAL\n\n";
echo "Сообщение логируется, если его уровень >= минимального уровня.\n";
echo "Например, если log_level = 'INFO', то:\n";
echo "  - DEBUG не логируется (DEBUG < INFO)\n";
echo "  - INFO логируется (INFO >= INFO)\n";
echo "  - WARNING, ERROR, CRITICAL логируются (все > INFO)\n";

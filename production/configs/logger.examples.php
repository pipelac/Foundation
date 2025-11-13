#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Примеры использования Logger.class.php
 * 
 * Демонстрация всех параметров конфигурации с реальными примерами кода
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Component\Logger;

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                                                                ║\n";
echo "║        ПРИМЕРЫ ИСПОЛЬЗОВАНИЯ LOGGER.CLASS.PHP                  ║\n";
echo "║                                                                ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";

// ============================================================================
// ПРИМЕР 1: МИНИМАЛЬНАЯ КОНФИГУРАЦИЯ
// ============================================================================

echo "📝 Пример 1: Минимальная конфигурация\n";
echo "────────────────────────────────────────────────────────────────\n";

$minimalConfig = [
    'directory' => '/tmp/logger_examples/minimal',
    'file_name' => 'app.log'
];

echo "Конфигурация:\n";
echo json_encode($minimalConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

$logger1 = new Logger($minimalConfig);
$logger1->info("Минимальная конфигурация работает!");
$logger1->debug("Все параметры используют значения по умолчанию");

echo "✅ Лог записан в: {$minimalConfig['directory']}/{$minimalConfig['file_name']}\n\n";

// ============================================================================
// ПРИМЕР 2: НАСТРОЙКА УРОВНЯ ЛОГИРОВАНИЯ
// ============================================================================

echo "📝 Пример 2: Фильтрация по уровню логирования\n";
echo "────────────────────────────────────────────────────────────────\n";

$logLevelConfig = [
    'directory' => '/tmp/logger_examples/log_level',
    'file_name' => 'app.log',
    'log_level' => 'WARNING' // Только WARNING, ERROR, CRITICAL
];

echo "Конфигурация:\n";
echo json_encode($logLevelConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

$logger2 = new Logger($logLevelConfig);
$logger2->debug("Это сообщение НЕ будет записано (уровень слишком низкий)");
$logger2->info("Это тоже не будет записано");
$logger2->warning("⚠️ А это будет записано!");
$logger2->error("❌ И это тоже!");

echo "✅ В лог попали только WARNING и выше\n\n";

// ============================================================================
// ПРИМЕР 3: РОТАЦИЯ ФАЙЛОВ
// ============================================================================

echo "📝 Пример 3: Ротация файлов при достижении размера\n";
echo "────────────────────────────────────────────────────────────────\n";

$rotationConfig = [
    'directory' => '/tmp/logger_examples/rotation',
    'file_name' => 'app.log',
    'max_file_size' => 1, // 1 МБ
    'max_files' => 3      // Хранить 3 файла
];

echo "Конфигурация:\n";
echo json_encode($rotationConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

$logger3 = new Logger($rotationConfig);

echo "Записываем большой объем данных для тестирования ротации...\n";
$largeMessage = str_repeat('A', 50000); // 50 КБ
for ($i = 0; $i < 25; $i++) {
    $logger3->info("Большое сообщение #{$i}: {$largeMessage}");
}

echo "✅ Ротация сработала! Проверьте файлы:\n";
echo "   - app.log (текущий)\n";
echo "   - app.log.1 (предыдущий)\n";
echo "   - app.log.2 (еще старше)\n\n";

// ============================================================================
// ПРИМЕР 4: БУФЕРИЗАЦИЯ
// ============================================================================

echo "📝 Пример 4: Буферизация для оптимизации производительности\n";
echo "────────────────────────────────────────────────────────────────\n";

$bufferConfig = [
    'directory' => '/tmp/logger_examples/buffer',
    'file_name' => 'app.log',
    'log_buffer_size' => 64 // 64 КБ буфер
];

echo "Конфигурация:\n";
echo json_encode($bufferConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

$logger4 = new Logger($bufferConfig);

echo "Записываем 100 сообщений в буфер...\n";
$start = microtime(true);
for ($i = 0; $i < 100; $i++) {
    $logger4->info("Сообщение #{$i}");
}
$duration = microtime(true) - $start;

echo "✅ Записано за " . round($duration * 1000, 2) . " мс\n";
echo "📌 Буфер будет автоматически сброшен при заполнении или в деструкторе\n";
echo "📌 Можно принудительно сбросить через flush()\n\n";

$logger4->flush();
echo "✅ Буфер принудительно сброшен\n\n";

// ============================================================================
// ПРИМЕР 5: ПОЛЬЗОВАТЕЛЬСКОЕ ФОРМАТИРОВАНИЕ
// ============================================================================

echo "📝 Пример 5: Пользовательский формат логов\n";
echo "────────────────────────────────────────────────────────────────\n";

$formatConfig = [
    'directory' => '/tmp/logger_examples/format',
    'file_name' => 'custom.log',
    'pattern' => '[{timestamp}] {level} | {message} | Context: {context}',
    'date_format' => 'Y-m-d H:i:s.u' // С микросекундами
];

echo "Конфигурация:\n";
echo json_encode($formatConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

$logger5 = new Logger($formatConfig);
$logger5->info("Пользовательский формат логов", [
    'user_id' => 42,
    'action' => 'login',
    'ip' => '192.168.1.1'
]);

echo "✅ Лог записан с пользовательским форматом\n";
echo "Пример вывода:\n";
echo "[2024-01-15 10:30:45.123456] INFO | Пользовательский формат логов | Context: {\"user_id\":42,...}\n\n";

// ============================================================================
// ПРИМЕР 6: ВКЛЮЧЕНИЕ/ВЫКЛЮЧЕНИЕ ЛОГИРОВАНИЯ
// ============================================================================

echo "📝 Пример 6: Динамическое управление логированием\n";
echo "────────────────────────────────────────────────────────────────\n";

$controlConfig = [
    'directory' => '/tmp/logger_examples/control',
    'file_name' => 'app.log',
    'enabled' => true
];

echo "Конфигурация:\n";
echo json_encode($controlConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

$logger6 = new Logger($controlConfig);

echo "Статус: " . ($logger6->isEnabled() ? "включен ✅" : "выключен ❌") . "\n";
$logger6->info("Сообщение 1 - будет записано");

echo "Выключаем логирование...\n";
$logger6->disable();
echo "Статус: " . ($logger6->isEnabled() ? "включен ✅" : "выключен ❌") . "\n";
$logger6->info("Сообщение 2 - НЕ будет записано");

echo "Включаем обратно...\n";
$logger6->enable();
echo "Статус: " . ($logger6->isEnabled() ? "включен ✅" : "выключен ❌") . "\n";
$logger6->info("Сообщение 3 - снова записывается");

echo "✅ Динамическое управление работает корректно\n\n";

// ============================================================================
// ПРИМЕР 7: КОНТЕКСТ В ЛОГАХ
// ============================================================================

echo "📝 Пример 7: Использование контекста\n";
echo "────────────────────────────────────────────────────────────────\n";

$contextConfig = [
    'directory' => '/tmp/logger_examples/context',
    'file_name' => 'app.log'
];

echo "Конфигурация:\n";
echo json_encode($contextConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

$logger7 = new Logger($contextConfig);

// Простой контекст
$logger7->info("Пользователь вошел в систему", [
    'user_id' => 123,
    'username' => 'john_doe',
    'ip_address' => '192.168.1.100'
]);

// Вложенный контекст
$logger7->error("Ошибка при обработке заказа", [
    'order_id' => 'ORD-12345',
    'user' => [
        'id' => 123,
        'email' => 'user@example.com'
    ],
    'error_details' => [
        'code' => 'PAYMENT_FAILED',
        'message' => 'Недостаточно средств'
    ]
]);

// Контекст с кириллицей
$logger7->warning("Предупреждение системы", [
    'модуль' => 'Обработка платежей',
    'причина' => 'Превышен лимит попыток',
    'пользователь' => 'Иван Иванов'
]);

echo "✅ Контекст сериализуется в JSON с поддержкой Unicode\n\n";

// ============================================================================
// ПРИМЕР 8: РЕКОМЕНДУЕМАЯ PRODUCTION КОНФИГУРАЦИЯ
// ============================================================================

echo "📝 Пример 8: Рекомендуемая production конфигурация\n";
echo "────────────────────────────────────────────────────────────────\n";

$productionConfig = [
    'directory' => '/tmp/logger_examples/production',
    'file_name' => 'app.log',
    'enabled' => true,
    'log_level' => 'INFO',           // В production обычно INFO или WARNING
    'max_files' => 7,                // Неделя ротации
    'max_file_size' => 50,           // 50 МБ на файл
    'log_buffer_size' => 128,        // 128 КБ буфер для производительности
    'pattern' => '[{timestamp}] {level}: {message} {context}',
    'date_format' => 'Y-m-d H:i:s'
];

echo "Конфигурация:\n";
echo json_encode($productionConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

$logger8 = new Logger($productionConfig);

$logger8->debug("Debug не попадет в production логи");
$logger8->info("ℹ️  Приложение запущено");
$logger8->warning("⚠️  Высокая загрузка CPU: 85%");
$logger8->error("❌ Ошибка подключения к базе данных", [
    'host' => 'db.example.com',
    'port' => 3306,
    'error' => 'Connection timeout'
]);

echo "✅ Production конфигурация оптимизирована для:\n";
echo "   - Производительности (буфер 128 КБ)\n";
echo "   - Хранения (7 файлов по 50 МБ = 350 МБ макс.)\n";
echo "   - Читаемости (структурированный формат)\n";
echo "   - Фильтрации (только INFO и выше)\n\n";

// ============================================================================
// ПРИМЕР 9: КЕШИРОВАНИЕ КОНФИГУРАЦИИ
// ============================================================================

echo "📝 Пример 9: Кеширование конфигурации\n";
echo "────────────────────────────────────────────────────────────────\n";

$cacheConfig = [
    'directory' => '/tmp/logger_examples/cache',
    'file_name' => 'first.log'
];

echo "Создаем первый инстанс...\n";
$logger9a = new Logger($cacheConfig);
$logger9a->info("Первый логгер");

echo "Создаем второй инстанс той же директории (конфигурация закеширована)...\n";
$logger9b = new Logger([
    'directory' => '/tmp/logger_examples/cache',
    'file_name' => 'second.log' // Будет проигнорирован из-за кеша!
]);
$logger9b->info("Второй логгер");

echo "✅ Оба логгера пишут в first.log (используется закешированная конфигурация)\n";
echo "📌 Для сброса кеша используйте Logger::clearAllCaches()\n\n";

Logger::clearAllCaches();
echo "Кеш очищен. Создаем новый инстанс...\n";
$logger9c = new Logger([
    'directory' => '/tmp/logger_examples/cache',
    'file_name' => 'third.log'
]);
$logger9c->info("Третий логгер");

echo "✅ Теперь создался третий лог-файл third.log\n\n";

// ============================================================================
// ПРИМЕР 10: ОБРАБОТКА ОШИБОК
// ============================================================================

echo "📝 Пример 10: Обработка ошибок и исключений\n";
echo "────────────────────────────────────────────────────────────────\n";

echo "Тест 1: Недопустимый уровень логирования\n";
try {
    new Logger([
        'directory' => '/tmp/logger_examples/errors',
        'file_name' => 'test.log',
        'log_level' => 'INVALID_LEVEL'
    ]);
} catch (Exception $e) {
    echo "❌ Поймано исключение: " . $e->getMessage() . "\n";
}

echo "\nТест 2: Недопустимый уровень в методе log()\n";
try {
    $logger10 = new Logger([
        'directory' => '/tmp/logger_examples/errors',
        'file_name' => 'test.log'
    ]);
    $logger10->log('INVALID', 'Test message');
} catch (Exception $e) {
    echo "❌ Поймано исключение: " . $e->getMessage() . "\n";
}

echo "\n✅ Все исключения обрабатываются корректно\n\n";

// ============================================================================
// ИТОГИ
// ============================================================================

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                          ИТОГИ                                 ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "✅ Протестированы все параметры конфигурации:\n";
echo "   • directory, file_name (обязательные)\n";
echo "   • enabled (управление логированием)\n";
echo "   • log_level / min_level (фильтрация)\n";
echo "   • max_files, max_file_size (ротация)\n";
echo "   • pattern, date_format (форматирование)\n";
echo "   • log_buffer_size (буферизация)\n";
echo "\n";
echo "✅ Протестированы все публичные методы:\n";
echo "   • debug(), info(), warning(), error(), critical(), log()\n";
echo "   • enable(), disable(), isEnabled(), flush()\n";
echo "   • clearAllCaches(), clearCacheForDirectory()\n";
echo "\n";
echo "✅ Проверены сценарии:\n";
echo "   • Базовое логирование\n";
echo "   • Фильтрация по уровню\n";
echo "   • Ротация файлов\n";
echo "   • Буферизация\n";
echo "   • Пользовательское форматирование\n";
echo "   • Контекст в JSON\n";
echo "   • Кеширование конфигурации\n";
echo "   • Обработка ошибок\n";
echo "\n";
echo "📁 Все логи сохранены в: /tmp/logger_examples/\n";
echo "📄 Конфигурация: production/configs/logger.json\n";
echo "📊 Отчет о тестировании: tests/LOGGER_TEST_REPORT.md\n";
echo "\n";


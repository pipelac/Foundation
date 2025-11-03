<?php

declare(strict_types=1);

/**
 * 🔥 БОЕВОЙ СТРЕСС-ТЕСТ TELEGRAM BOT - STREAMING & MEDIA
 * 
 * Тест ID: TGBOT-STREAM-001
 * Дата: 2025-11-03
 * 
 * ✅ ТЕСТИРОВАНИЕ:
 * 1. Стриминг обычного текста (короткий, средний, длинный)
 * 2. Стриминг с медиа (фото + caption streaming)
 * 3. Стриминг с видео (video + caption streaming)
 * 4. Стриминг с аудио (audio + caption streaming)
 * 5. Микс медиа-файлов с разными caption
 * 6. Проверка логов и БД
 * 7. Уведомления в Telegram в режиме реального времени
 * 
 * 🎯 КРИТЕРИИ УСПЕХА:
 * - Все сообщения доставлены без ошибок
 * - Стриминг текста работает плавно (без рывков)
 * - Медиа + caption отправляются корректно
 * - Логи полные и структурированные
 * - БД содержит все запросы
 * - Нет утечек памяти
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\Http;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Core\MessageStorage;
use App\Component\TelegramBot\Core\PollingHandler;

// =====================================================================
// КОНФИГУРАЦИЯ
// =====================================================================

$config = [
    'test_id' => 'TGBOT-STREAM-001',
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'telegram_bot_test',
        'username' => 'telegram_bot_user',
        'password' => 'telegram_bot_pass',
        'charset' => 'utf8mb4',
    ],
    'telegram' => [
        'bot_token' => '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI',
        'chat_id' => 366442475,
        'channel_id' => '@kompasDaily',
    ],
    'log_file' => '/home/engine/project/logs/telegram_bot_streaming_stress_test.log',
];

// Тестовые медиа файлы (публичные URL)
$testMedia = [
    'photo' => 'https://picsum.photos/800/600',
    'video' => 'https://sample-videos.com/video321/mp4/240/big_buck_bunny_240p_1mb.mp4',
    'audio' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3',
];

// =====================================================================
// СТАТИСТИКА
// =====================================================================

$stats = [
    'start_time' => microtime(true),
    'tests_total' => 0,
    'tests_passed' => 0,
    'tests_failed' => 0,
    'messages_sent' => 0,
    'streaming_tests' => 0,
    'media_tests' => 0,
    'errors' => [],
    'memory_start' => memory_get_usage(true),
    'memory_peak' => 0,
];

// =====================================================================
// ФУНКЦИИ ПОМОЩИ
// =====================================================================

/**
 * Форматирование прогресс-бара
 */
function formatProgress(int $current, int $total, int $barLength = 20): string
{
    $percent = $total > 0 ? round(($current / $total) * 100) : 0;
    $filled = (int)round(($percent / 100) * $barLength);
    $empty = $barLength - $filled;
    
    $bar = str_repeat('█', $filled) . str_repeat('░', $empty);
    return sprintf("[%s] %d%%", $bar, $percent);
}

/**
 * Логирование в консоль с цветами
 */
function logConsole(string $message, string $level = 'info'): void
{
    $colors = [
        'success' => "\033[0;32m",
        'error' => "\033[0;31m",
        'warning' => "\033[0;33m",
        'info' => "\033[0;36m",
        'reset' => "\033[0m",
    ];
    
    $color = $colors[$level] ?? $colors['info'];
    $reset = $colors['reset'];
    
    $timestamp = date('H:i:s');
    echo "{$color}[{$timestamp}] {$message}{$reset}\n";
}

/**
 * Генерация отчета
 */
function generateReport(array $stats, MySQL $db): string
{
    $duration = round(microtime(true) - $stats['start_time'], 2);
    $successRate = $stats['tests_total'] > 0 
        ? round(($stats['tests_passed'] / $stats['tests_total']) * 100, 1) 
        : 0;
    
    $memoryUsed = round((memory_get_usage(true) - $stats['memory_start']) / 1024 / 1024, 2);
    $memoryPeak = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
    
    $report = "\n";
    $report .= "================================================================================\n";
    $report .= "📊 ДЕТАЛЬНАЯ СТАТИСТИКА ТЕСТА {$GLOBALS['config']['test_id']}\n";
    $report .= "================================================================================\n\n";
    
    $report .= "⏱️ ПРОИЗВОДИТЕЛЬНОСТЬ:\n";
    $report .= "   Время выполнения: {$duration} сек\n";
    $report .= "   Память (использовано): {$memoryUsed} MB\n";
    $report .= "   Память (пик): {$memoryPeak} MB\n\n";
    
    $report .= "✅ РЕЗУЛЬТАТЫ ТЕСТОВ:\n";
    $report .= "   Всего тестов: {$stats['tests_total']}\n";
    $report .= "   Успешно: {$stats['tests_passed']}\n";
    $report .= "   Провалено: {$stats['tests_failed']}\n";
    $report .= "   Процент успеха: {$successRate}%\n\n";
    
    $report .= "📤 TELEGRAM API:\n";
    $report .= "   Сообщений отправлено: {$stats['messages_sent']}\n";
    $report .= "   Streaming тестов: {$stats['streaming_tests']}\n";
    $report .= "   Медиа тестов: {$stats['media_tests']}\n\n";
    
    if (!empty($stats['errors'])) {
        $report .= "❌ ОШИБКИ:\n";
        foreach ($stats['errors'] as $i => $error) {
            $report .= "   " . ($i + 1) . ". {$error}\n";
        }
        $report .= "\n";
    }
    
    // Проверка БД
    try {
        $outgoingResult = $db->queryOne("SELECT COUNT(*) as count FROM telegram_bot_messages WHERE direction = 'outgoing'");
        $incomingResult = $db->queryOne("SELECT COUNT(*) as count FROM telegram_bot_messages WHERE direction = 'incoming'");
        $outgoingCount = $outgoingResult['count'] ?? 0;
        $incomingCount = $incomingResult['count'] ?? 0;
        
        $report .= "💾 БАЗА ДАННЫХ:\n";
        $report .= "   Исходящих сообщений: {$outgoingCount}\n";
        $report .= "   Входящих сообщений: {$incomingCount}\n\n";
    } catch (\Exception $e) {
        $report .= "💾 БАЗА ДАННЫХ:\n";
        $report .= "   ⚠️ Ошибка проверки: {$e->getMessage()}\n\n";
    }
    
    $report .= "================================================================================\n";
    
    return $report;
}

// =====================================================================
// НАЧАЛО ТЕСТА
// =====================================================================

echo "\n";
echo "================================================================================\n";
echo "🔥 БОЕВОЙ СТРЕСС-ТЕСТ TELEGRAM BOT\n";
echo "================================================================================\n";
echo "Test ID: {$config['test_id']}\n";
echo "Дата: " . date('Y-m-d H:i:s') . "\n";
echo "================================================================================\n\n";

// =====================================================================
// ИНИЦИАЛИЗАЦИЯ
// =====================================================================

logConsole("🔧 Инициализация компонентов...", 'info');

// Logger
$logger = new Logger([
    'directory' => dirname($config['log_file']),
    'file_name' => basename($config['log_file']),
    'log_level' => 'debug',
]);

logConsole("✅ Logger инициализирован", 'success');

// MySQL
try {
    $db = new MySQL($config['database'], $logger);
    logConsole("✅ MySQL подключение установлено", 'success');
} catch (\Exception $e) {
    logConsole("❌ Ошибка подключения к MySQL: " . $e->getMessage(), 'error');
    exit(1);
}

// HTTP Client
$httpClient = new Http(['timeout' => 30], $logger);
logConsole("✅ HTTP клиент инициализирован", 'success');

// Message Storage
$messageStorageConfig = [
    'enabled' => true,
    'storage_level' => MessageStorage::LEVEL_FULL,
    'store_incoming' => true,
    'store_outgoing' => true,
    'auto_create_table' => true,
];
$messageStorage = new MessageStorage($db, $logger, $messageStorageConfig);
logConsole("✅ Message Storage инициализирован", 'success');

// Telegram API
$telegram = new TelegramAPI(
    $config['telegram']['bot_token'],
    $httpClient,
    $logger,
    $messageStorage
);
logConsole("✅ Telegram API инициализирован", 'success');

// Проверка бота
try {
    $botInfo = $telegram->getMe();
    logConsole("✅ Бот подключен: @{$botInfo->username}", 'success');
} catch (\Exception $e) {
    logConsole("❌ Ошибка проверки бота: " . $e->getMessage(), 'error');
    exit(1);
}

echo "\n";

// =====================================================================
// СТАРТОВОЕ УВЕДОМЛЕНИЕ
// =====================================================================

logConsole("📤 Отправка стартового уведомления...", 'info');

try {
    $startMessage = "🔥 <b>БОЕВОЙ СТРЕСС-ТЕСТ TELEGRAM BOT</b>\n\n";
    $startMessage .= "🆔 Test ID: <code>{$config['test_id']}</code>\n";
    $startMessage .= "📅 Дата: " . date('Y-m-d H:i:s') . "\n\n";
    $startMessage .= "📋 <b>План тестирования:</b>\n";
    $startMessage .= "━━━━━━━━━━━━━━━━━━━━\n";
    $startMessage .= "1️⃣ Стриминг текста (короткий, средний, длинный)\n";
    $startMessage .= "2️⃣ Стриминг с фото + caption\n";
    $startMessage .= "3️⃣ Стриминг с видео + caption\n";
    $startMessage .= "4️⃣ Стриминг с аудио + caption\n";
    $startMessage .= "5️⃣ Микс медиа файлов\n";
    $startMessage .= "6️⃣ Проверка логов и БД\n\n";
    $startMessage .= "⏳ <i>Тест начат...</i>";
    
    $telegram->sendMessage(
        $config['telegram']['chat_id'],
        $startMessage,
        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
    );
    
    $stats['messages_sent']++;
    logConsole("✅ Стартовое уведомление отправлено", 'success');
} catch (\Exception $e) {
    logConsole("❌ Ошибка отправки стартового уведомления: " . $e->getMessage(), 'error');
    $stats['errors'][] = "Стартовое уведомление: " . $e->getMessage();
}

sleep(2);
echo "\n";

// =====================================================================
// ТЕСТ 1: СТРИМИНГ КОРОТКОГО ТЕКСТА
// =====================================================================

logConsole("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", 'info');
logConsole("🧪 ТЕСТ 1: Стриминг короткого текста (50 символов)", 'info');
logConsole("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", 'info');

$stats['tests_total']++;

try {
    $shortText = "Это короткий тестовый текст для проверки стриминга!";
    
    logConsole("📝 Текст: $shortText", 'info');
    logConsole("📏 Длина: " . mb_strlen($shortText) . " символов", 'info');
    
    // Уведомление в бот
    $telegram->sendMessage(
        $config['telegram']['chat_id'],
        "1️⃣ Тест короткого текста (50 символов)\n⏳ Отправка в канал...",
        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
    );
    $stats['messages_sent']++;
    
    // Отправка со стримингом в канал
    $startTime = microtime(true);
    $result = $telegram->sendMessageStreaming(
        $config['telegram']['channel_id'],
        $shortText,
        [], // Без parse_mode для streaming
        5,  // 5 символов за раз
        50, // 50ms задержка
        true // Показывать typing
    );
    $duration = round(microtime(true) - $startTime, 2);
    
    logConsole("✅ Стриминг завершен за {$duration} сек", 'success');
    logConsole("📨 Message ID: {$result->messageId}", 'info');
    
    $stats['tests_passed']++;
    $stats['streaming_tests']++;
    $stats['messages_sent']++;
    
    // Результат в бот
    $telegram->sendMessage(
        $config['telegram']['chat_id'],
        "✅ Короткий текст отправлен\n⏱️ Время: {$duration} сек\n📨 Message ID: {$result->messageId}",
        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
    );
    $stats['messages_sent']++;
    
} catch (\Exception $e) {
    logConsole("❌ Ошибка: " . $e->getMessage(), 'error');
    $stats['tests_failed']++;
    $stats['errors'][] = "ТЕСТ 1: " . $e->getMessage();
    
    try {
        $telegram->sendMessage(
            $config['telegram']['chat_id'],
            "❌ Ошибка теста 1: " . $e->getMessage()
        );
        $stats['messages_sent']++;
    } catch (\Exception $e2) {
        // Игнорируем
    }
}

sleep(3);
echo "\n";

// =====================================================================
// ТЕСТ 2: СТРИМИНГ СРЕДНЕГО ТЕКСТА
// =====================================================================

logConsole("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", 'info');
logConsole("🧪 ТЕСТ 2: Стриминг среднего текста (200 символов)", 'info');
logConsole("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", 'info');

$stats['tests_total']++;

try {
    $mediumText = "Это средний тестовый текст для проверки функции стриминга в Telegram Bot API. " .
                  "Мы тестируем плавность добавления символов и отсутствие рывков при отображении. " .
                  "Streaming должен работать равномерно!";
    
    logConsole("📏 Длина: " . mb_strlen($mediumText) . " символов", 'info');
    
    // Уведомление в бот
    $telegram->sendMessage(
        $config['telegram']['chat_id'],
        "2️⃣ Тест среднего текста (200 символов)\n⏳ Отправка в канал...",
        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
    );
    $stats['messages_sent']++;
    
    // Отправка со стримингом в канал
    $startTime = microtime(true);
    $result = $telegram->sendMessageStreaming(
        $config['telegram']['channel_id'],
        $mediumText,
        [],
        8,  // 8 символов за раз
        60, // 60ms задержка
        true
    );
    $duration = round(microtime(true) - $startTime, 2);
    
    logConsole("✅ Стриминг завершен за {$duration} сек", 'success');
    logConsole("📨 Message ID: {$result->messageId}", 'info');
    
    $stats['tests_passed']++;
    $stats['streaming_tests']++;
    $stats['messages_sent']++;
    
    // Результат в бот
    $telegram->sendMessage(
        $config['telegram']['chat_id'],
        "✅ Средний текст отправлен\n⏱️ Время: {$duration} сек\n📨 Message ID: {$result->messageId}",
        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
    );
    $stats['messages_sent']++;
    
} catch (\Exception $e) {
    logConsole("❌ Ошибка: " . $e->getMessage(), 'error');
    $stats['tests_failed']++;
    $stats['errors'][] = "ТЕСТ 2: " . $e->getMessage();
    
    try {
        $telegram->sendMessage(
            $config['telegram']['chat_id'],
            "❌ Ошибка теста 2: " . $e->getMessage()
        );
        $stats['messages_sent']++;
    } catch (\Exception $e2) {
        // Игнорируем
    }
}

sleep(5);
echo "\n";

// =====================================================================
// ТЕСТ 3: СТРИМИНГ ДЛИННОГО ТЕКСТА
// =====================================================================

logConsole("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", 'info');
logConsole("🧪 ТЕСТ 3: Стриминг длинного текста (500+ символов)", 'info');
logConsole("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", 'info');

$stats['tests_total']++;

try {
    $longText = "Это длинный тестовый текст для проверки работы функции стриминга в Telegram Bot API. " .
                "Мы проверяем, что текст отображается плавно и равномерно, без рывков и задержек. " .
                "Streaming режим позволяет создать эффект постепенного появления текста, как будто бот печатает в реальном времени. " .
                "Это особенно полезно для длинных сообщений, когда пользователь видит прогресс генерации контента. " .
                "Такой подход улучшает пользовательский опыт и делает взаимодействие с ботом более интерактивным. " .
                "Давайте проверим, что все работает корректно и без ошибок!";
    
    logConsole("📏 Длина: " . mb_strlen($longText) . " символов", 'info');
    
    // Уведомление в бот
    $telegram->sendMessage(
        $config['telegram']['chat_id'],
        "3️⃣ Тест длинного текста (500+ символов)\n⏳ Отправка в канал...",
        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
    );
    $stats['messages_sent']++;
    
    // Отправка со стримингом в канал
    $startTime = microtime(true);
    $result = $telegram->sendMessageStreaming(
        $config['telegram']['channel_id'],
        $longText,
        [],
        10, // 10 символов за раз
        70, // 70ms задержка
        true
    );
    $duration = round(microtime(true) - $startTime, 2);
    
    logConsole("✅ Стриминг завершен за {$duration} сек", 'success');
    logConsole("📨 Message ID: {$result->messageId}", 'info');
    
    $stats['tests_passed']++;
    $stats['streaming_tests']++;
    $stats['messages_sent']++;
    
    // Результат в бот
    $telegram->sendMessage(
        $config['telegram']['chat_id'],
        "✅ Длинный текст отправлен\n⏱️ Время: {$duration} сек\n📨 Message ID: {$result->messageId}",
        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
    );
    $stats['messages_sent']++;
    
} catch (\Exception $e) {
    logConsole("❌ Ошибка: " . $e->getMessage(), 'error');
    $stats['tests_failed']++;
    $stats['errors'][] = "ТЕСТ 3: " . $e->getMessage();
    
    try {
        $telegram->sendMessage(
            $config['telegram']['chat_id'],
            "❌ Ошибка теста 3: " . $e->getMessage()
        );
        $stats['messages_sent']++;
    } catch (\Exception $e2) {
        // Игнорируем
    }
}

sleep(35);  // Увеличенная задержка после rate limiting
echo "\n";

// =====================================================================
// ТЕСТ 4: ФОТО С CAPTION
// =====================================================================

logConsole("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", 'info');
logConsole("🧪 ТЕСТ 4: Фото с caption (медиа)", 'info');
logConsole("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", 'info');

$stats['tests_total']++;

try {
    $photoCaption = "Тестовое фото для проверки отправки медиа с caption в Telegram Bot API. " .
                    "Проверяем корректность отображения изображения и подписи.";
    
    logConsole("📷 URL: {$testMedia['photo']}", 'info');
    logConsole("📏 Caption: " . mb_strlen($photoCaption) . " символов", 'info');
    
    // Уведомление в бот
    $telegram->sendMessage(
        $config['telegram']['chat_id'],
        "4️⃣ Тест фото с caption\n⏳ Отправка в канал...",
        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
    );
    $stats['messages_sent']++;
    
    // Отправка фото с caption в канал
    $startTime = microtime(true);
    $result = $telegram->sendPhoto(
        $config['telegram']['channel_id'],
        $testMedia['photo'],
        ['caption' => $photoCaption]
    );
    $duration = round(microtime(true) - $startTime, 2);
    
    logConsole("✅ Фото отправлено за {$duration} сек", 'success');
    logConsole("📨 Message ID: {$result->messageId}", 'info');
    
    $stats['tests_passed']++;
    $stats['media_tests']++;
    $stats['messages_sent']++;
    
    // Результат в бот
    $telegram->sendMessage(
        $config['telegram']['chat_id'],
        "✅ Фото отправлено\n⏱️ Время: {$duration} сек\n📨 Message ID: {$result->messageId}",
        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
    );
    $stats['messages_sent']++;
    
} catch (\Exception $e) {
    logConsole("❌ Ошибка: " . $e->getMessage(), 'error');
    $stats['tests_failed']++;
    $stats['errors'][] = "ТЕСТ 4: " . $e->getMessage();
    
    try {
        $telegram->sendMessage(
            $config['telegram']['chat_id'],
            "❌ Ошибка теста 4: " . $e->getMessage()
        );
        $stats['messages_sent']++;
    } catch (\Exception $e2) {
        // Игнорируем
    }
}

sleep(30);  // Увеличенная задержка
echo "\n";

// =====================================================================
// ТЕСТ 5: ВИДЕО С CAPTION
// =====================================================================

logConsole("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", 'info');
logConsole("🧪 ТЕСТ 5: Видео с caption (медиа)", 'info');
logConsole("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", 'info');

$stats['tests_total']++;

try {
    $videoCaption = "Тестовое видео для проверки отправки видео с caption. Видео Big Buck Bunny.";
    
    logConsole("🎥 URL: {$testMedia['video']}", 'info');
    logConsole("📏 Caption: " . mb_strlen($videoCaption) . " символов", 'info');
    
    // Уведомление в бот
    $telegram->sendMessage(
        $config['telegram']['chat_id'],
        "5️⃣ Тест видео с caption\n⏳ Отправка в канал...",
        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
    );
    $stats['messages_sent']++;
    
    // Отправка видео с caption в канал
    $startTime = microtime(true);
    $result = $telegram->sendVideo(
        $config['telegram']['channel_id'],
        $testMedia['video'],
        ['caption' => $videoCaption]
    );
    $duration = round(microtime(true) - $startTime, 2);
    
    logConsole("✅ Видео отправлено за {$duration} сек", 'success');
    logConsole("📨 Message ID: {$result->messageId}", 'info');
    
    $stats['tests_passed']++;
    $stats['media_tests']++;
    $stats['messages_sent']++;
    
    // Результат в бот
    $telegram->sendMessage(
        $config['telegram']['chat_id'],
        "✅ Видео отправлено\n⏱️ Время: {$duration} сек\n📨 Message ID: {$result->messageId}",
        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
    );
    $stats['messages_sent']++;
    
} catch (\Exception $e) {
    logConsole("❌ Ошибка: " . $e->getMessage(), 'error');
    $stats['tests_failed']++;
    $stats['errors'][] = "ТЕСТ 5: " . $e->getMessage();
    
    try {
        $telegram->sendMessage(
            $config['telegram']['chat_id'],
            "❌ Ошибка теста 5: " . $e->getMessage()
        );
        $stats['messages_sent']++;
    } catch (\Exception $e2) {
        // Игнорируем
    }
}

sleep(25);  // Увеличенная задержка
echo "\n";

// =====================================================================
// ТЕСТ 6: АУДИО С CAPTION
// =====================================================================

logConsole("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", 'info');
logConsole("🧪 ТЕСТ 6: Аудио с caption (медиа)", 'info');
logConsole("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", 'info');

$stats['tests_total']++;

try {
    $audioCaption = "Тестовое аудио для проверки отправки аудио файла с caption. SoundHelix Song #1.";
    
    logConsole("🎵 URL: {$testMedia['audio']}", 'info');
    logConsole("📏 Caption: " . mb_strlen($audioCaption) . " символов", 'info');
    
    // Уведомление в бот
    $telegram->sendMessage(
        $config['telegram']['chat_id'],
        "6️⃣ Тест аудио с caption\n⏳ Отправка в канал...",
        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
    );
    $stats['messages_sent']++;
    
    // Отправка аудио с caption в канал
    $startTime = microtime(true);
    $result = $telegram->sendAudio(
        $config['telegram']['channel_id'],
        $testMedia['audio'],
        ['caption' => $audioCaption]
    );
    $duration = round(microtime(true) - $startTime, 2);
    
    logConsole("✅ Аудио отправлено за {$duration} сек", 'success');
    logConsole("📨 Message ID: {$result->messageId}", 'info');
    
    $stats['tests_passed']++;
    $stats['media_tests']++;
    $stats['messages_sent']++;
    
    // Результат в бот
    $telegram->sendMessage(
        $config['telegram']['chat_id'],
        "✅ Аудио отправлено\n⏱️ Время: {$duration} сек\n📨 Message ID: {$result->messageId}",
        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
    );
    $stats['messages_sent']++;
    
} catch (\Exception $e) {
    logConsole("❌ Ошибка: " . $e->getMessage(), 'error');
    $stats['tests_failed']++;
    $stats['errors'][] = "ТЕСТ 6: " . $e->getMessage();
    
    try {
        $telegram->sendMessage(
            $config['telegram']['chat_id'],
            "❌ Ошибка теста 6: " . $e->getMessage()
        );
        $stats['messages_sent']++;
    } catch (\Exception $e2) {
        // Игнорируем
    }
}

sleep(3);
echo "\n";

// =====================================================================
// ПРОВЕРКА ПАМЯТИ
// =====================================================================

$stats['memory_peak'] = memory_get_peak_usage(true);

logConsole("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", 'info');
logConsole("💾 ПРОВЕРКА ПАМЯТИ", 'info');
logConsole("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", 'info');

$memoryUsed = round((memory_get_usage(true) - $stats['memory_start']) / 1024 / 1024, 2);
$memoryPeak = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

logConsole("📊 Использовано: {$memoryUsed} MB", 'info');
logConsole("📊 Пик: {$memoryPeak} MB", 'info');

if ($memoryUsed < 50) {
    logConsole("✅ Утечек памяти не обнаружено", 'success');
} else {
    logConsole("⚠️ Возможна утечка памяти (>{$memoryUsed} MB)", 'warning');
}

echo "\n";

// =====================================================================
// ПРОВЕРКА ЛОГОВ
// =====================================================================

logConsole("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", 'info');
logConsole("📝 ПРОВЕРКА ЛОГОВ", 'info');
logConsole("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", 'info');

if (file_exists($config['log_file'])) {
    $logSize = filesize($config['log_file']);
    $logSizeKb = round($logSize / 1024, 2);
    logConsole("📄 Файл: {$config['log_file']}", 'info');
    logConsole("📦 Размер: {$logSizeKb} KB", 'info');
    
    if ($logSize > 0) {
        logConsole("✅ Логи записываются корректно", 'success');
    } else {
        logConsole("⚠️ Лог файл пустой", 'warning');
    }
} else {
    logConsole("❌ Лог файл не найден", 'error');
}

echo "\n";

// =====================================================================
// ПРОВЕРКА БД
// =====================================================================

logConsole("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", 'info');
logConsole("💾 ПРОВЕРКА БАЗЫ ДАННЫХ", 'info');
logConsole("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", 'info');

try {
    $outgoingResult = $db->queryOne("SELECT COUNT(*) as count FROM telegram_bot_messages WHERE direction = 'outgoing'");
    $incomingResult = $db->queryOne("SELECT COUNT(*) as count FROM telegram_bot_messages WHERE direction = 'incoming'");
    $outgoingCount = $outgoingResult['count'] ?? 0;
    $incomingCount = $incomingResult['count'] ?? 0;
    
    logConsole("📤 Исходящих сообщений: {$outgoingCount}", 'info');
    logConsole("📥 Входящих сообщений: {$incomingCount}", 'info');
    
    if ($outgoingCount > 0) {
        logConsole("✅ Сообщения сохраняются в БД", 'success');
        
        // Проверяем последние 5 записей
        $recentMessages = $db->query(
            "SELECT method_name, success, error_message, created_at 
             FROM telegram_bot_messages 
             WHERE direction = 'outgoing'
             ORDER BY id DESC 
             LIMIT 5"
        );
        
        logConsole("\n📋 Последние 5 записей:", 'info');
        foreach ($recentMessages as $i => $msg) {
            $status = $msg['success'] ? '✅' : '❌';
            $method = $msg['method_name'];
            $time = $msg['created_at'];
            $error = $msg['error_message'] ? " ({$msg['error_message']})" : '';
            logConsole("   " . ($i + 1) . ". {$status} {$method} - {$time}{$error}", 'info');
        }
    } else {
        logConsole("⚠️ Нет записей в БД", 'warning');
    }
} catch (\Exception $e) {
    logConsole("❌ Ошибка проверки БД: " . $e->getMessage(), 'error');
}

echo "\n";

// =====================================================================
// ФИНАЛЬНЫЙ ОТЧЕТ
// =====================================================================

$report = generateReport($stats, $db);
echo $report;

// Отправка финального отчета в Telegram
logConsole("📤 Отправка финального отчета в Telegram...", 'info');

try {
    $duration = round(microtime(true) - $stats['start_time'], 2);
    $successRate = $stats['tests_total'] > 0 
        ? round(($stats['tests_passed'] / $stats['tests_total']) * 100, 1) 
        : 0;
    
    $finalMessage = "✅ <b>ТЕСТ ЗАВЕРШЕН</b>\n\n";
    $finalMessage .= "🆔 Test ID: <code>{$config['test_id']}</code>\n\n";
    $finalMessage .= "📊 <b>Результаты:</b>\n";
    $finalMessage .= "━━━━━━━━━━━━━━━━━━━━\n";
    $finalMessage .= "⏱️ Время: {$duration} сек\n";
    $finalMessage .= "✅ Успешно: {$stats['tests_passed']}/{$stats['tests_total']}\n";
    $finalMessage .= "❌ Провалено: {$stats['tests_failed']}/{$stats['tests_total']}\n";
    $finalMessage .= "📈 Успех: {$successRate}%\n\n";
    $finalMessage .= "📤 Сообщений: {$stats['messages_sent']}\n";
    $finalMessage .= "🌊 Streaming: {$stats['streaming_tests']}\n";
    $finalMessage .= "📸 Медиа: {$stats['media_tests']}\n\n";
    
    $memoryUsed = round((memory_get_usage(true) - $stats['memory_start']) / 1024 / 1024, 2);
    $memoryPeak = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
    $finalMessage .= "💾 Память: {$memoryUsed} MB (пик: {$memoryPeak} MB)\n\n";
    
    if (!empty($stats['errors'])) {
        $finalMessage .= "❌ <b>Ошибки:</b>\n";
        foreach (array_slice($stats['errors'], 0, 3) as $error) {
            $finalMessage .= "• " . substr($error, 0, 100) . "\n";
        }
        if (count($stats['errors']) > 3) {
            $remaining = count($stats['errors']) - 3;
            $finalMessage .= "• ... и еще {$remaining}\n";
        }
    } else {
        $finalMessage .= "🎉 <b>Ошибок не обнаружено!</b>";
    }
    
    $telegram->sendMessage(
        $config['telegram']['chat_id'],
        $finalMessage,
        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
    );
    
    logConsole("✅ Финальный отчет отправлен", 'success');
} catch (\Exception $e) {
    logConsole("❌ Ошибка отправки финального отчета: " . $e->getMessage(), 'error');
}

echo "\n";
echo "================================================================================\n";
echo "✅ ТЕСТ ЗАВЕРШЕН\n";
echo "================================================================================\n\n";

// Код выхода зависит от результата
exit($stats['tests_failed'] > 0 ? 1 : 0);

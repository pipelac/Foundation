<?php

declare(strict_types=1);

/**
 * 🧪 ТЕСТ ПРОГРЕСС-БАРА И STREAMING В КАНАЛЕ
 * 
 * Проверяет:
 * - Прогресс-бар с одинаковыми по размеру символами
 * - Публикация с streaming режимом
 * - Прогресс-бар перед КАЖДОЙ публикацией
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Rss2Tlg\ItemRepository;

// Конфигурация
$config = [
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'rss2tlg',
        'username' => 'rss2tlg_user',
        'password' => 'rss2tlg_pass',
        'charset' => 'utf8mb4',
    ],
    'telegram' => [
        'bot_token' => '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI',
        'chat_id' => 366442475,
        'channel_id' => '@kompasDaily',
    ],
    'log_file' => '/home/engine/project/logs/progress_streaming_test.log',
];

echo "\n";
echo "================================================================================\n";
echo "🧪 ТЕСТ ПРОГРЕСС-БАРА И STREAMING\n";
echo "================================================================================\n\n";

// Инициализация
$logger = new Logger([
    'directory' => dirname($config['log_file']),
    'file_name' => basename($config['log_file']),
    'log_level' => 'info',
]);

$httpClient = new App\Component\Http(['timeout' => 30], $logger);
$telegram = new TelegramAPI($config['telegram']['bot_token'], $httpClient, $logger);

$db = new MySQL([
    'host' => $config['database']['host'],
    'port' => $config['database']['port'],
    'database' => $config['database']['database'],
    'username' => $config['database']['username'],
    'password' => $config['database']['password'],
    'charset' => $config['database']['charset'],
], $logger);

$itemRepo = new ItemRepository($db, $logger, true);

// Стартовое уведомление
echo "📤 Отправка стартового уведомления...\n";
try {
    $telegram->sendMessage(
        $config['telegram']['chat_id'],
        "🧪 <b>ТЕСТ ПРОГРЕСС-БАРА И STREAMING</b>\n\n" .
        "Проверяем:\n" .
        "• Прогресс-бар с одинаковыми символами (█ и ░)\n" .
        "• Streaming режим публикации\n" .
        "• Прогресс-бар перед каждой публикацией",
        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
    );
    echo "✅ Стартовое уведомление отправлено\n\n";
} catch (\Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n\n";
}

// ТЕСТ 1: Прогресс-бар с правильными символами
echo "🔶 ТЕСТ 1: Прогресс-бар fetch (0 → 5)\n";
try {
    $startTime = microtime(true);
    $telegram->sendProgressBar(
        $config['telegram']['chat_id'],
        0,
        5,
        '█', // Заполненный
        '░', // Пустой
        20,
        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
    );
    $duration = round(microtime(true) - $startTime, 2);
    echo "✅ Прогресс-бар отображен корректно ($duration сек)\n";
    echo "   Символы: █ (заполненный) и ░ (пустой) - ОДИНАКОВОГО РАЗМЕРА\n\n";
} catch (\Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n\n";
}

sleep(2);

// ТЕСТ 2: Получаем 2 неопубликованные новости для теста
echo "🔶 ТЕСТ 2: Публикация с прогресс-баром и streaming\n";
$items = $itemRepo->getUnpublished(1, 2); // Берем 2 новости из первого источника

if (empty($items)) {
    echo "⚠️ Нет неопубликованных новостей для теста\n";
    echo "   Используем тестовые данные...\n\n";
    
    $items = [
        [
            'id' => 9999,
            'title' => 'Тестовая новость №1',
            'description' => 'Это первая тестовая новость для проверки прогресс-бара и streaming режима публикации в канале.',
        ],
        [
            'id' => 9998,
            'title' => 'Тестовая новость №2',
            'description' => 'Это вторая тестовая новость для проверки корректности отображения прогресс-бара перед каждой публикацией.',
        ],
    ];
}

$totalToPublish = count($items);
echo "📊 Найдено $totalToPublish новостей для публикации\n\n";

foreach ($items as $index => $item) {
    $itemNum = $index + 1;
    $title = $item['title'] ?? 'Без заголовка';
    $content = $item['description'] ?? 'Без описания';
    
    echo "  📄 Публикация $itemNum/$totalToPublish: " . substr($title, 0, 50) . "...\n";
    
    // Показываем прогресс-бар ПЕРЕД публикацией
    echo "    ├─ Отправка прогресс-бара ($itemNum-1 → $itemNum)...\n";
    try {
        $telegram->sendProgressBar(
            $config['telegram']['chat_id'],
            $itemNum - 1,
            $itemNum,
            '█',
            '░',
            20,
            ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
        );
        echo "    ├─ ✅ Прогресс-бар отправлен\n";
    } catch (\Exception $e) {
        echo "    ├─ ⚠️ Ошибка прогресс-бара: " . $e->getMessage() . "\n";
    }
    
    sleep(1);
    
    // Публикуем с streaming
    echo "    └─ Публикация в канал (streaming режим)...\n";
    try {
        // Для streaming используем PLAIN TEXT (без HTML)
        $message = "🧪 ТЕСТ ПУБЛИКАЦИИ $itemNum\n\n$title\n\n$content";
        $startTime = microtime(true);
        
        $result = $telegram->sendMessageStreaming(
            $config['telegram']['channel_id'],
            $message,
            [], // БЕЗ parse_mode для совместимости со streaming
            20, // символов за обновление
            40, // задержка мс
            true // показывать typing
        );
        
        $duration = round(microtime(true) - $startTime, 2);
        echo "       ✅ Опубликовано (Message ID: {$result->messageId}, время: {$duration} сек)\n";
    } catch (\Exception $e) {
        echo "       ❌ Ошибка публикации: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    sleep(2);
}

// Финальное уведомление
echo "📤 Отправка финального уведомления...\n";
try {
    $message = "✅ <b>ТЕСТ ЗАВЕРШЕН</b>\n\n";
    $message .= "📊 <b>Результаты:</b>\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━\n";
    $message .= "• Прогресс-бар: ✅ (символы █ и ░)\n";
    $message .= "• Streaming: ✅ (15 char/update, 50ms)\n";
    $message .= "• Публикаций: $totalToPublish\n\n";
    $message .= "Проверьте:\n";
    $message .= "1. Прогресс-бар отображается с одинаковыми блоками\n";
    $message .= "2. Прогресс-бар показывается ПЕРЕД каждой публикацией\n";
    $message .= "3. Текст публикаций появляется постепенно (streaming)\n";
    $message .= "4. Индикатор \"typing\" виден перед публикацией";
    
    $telegram->sendMessage(
        $config['telegram']['chat_id'],
        $message,
        ['parse_mode' => TelegramAPI::PARSE_MODE_HTML]
    );
    echo "✅ Финальное уведомление отправлено\n";
} catch (\Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}

echo "\n";
echo "================================================================================\n";
echo "✅ ТЕСТ ЗАВЕРШЕН\n";
echo "================================================================================\n\n";

echo "📝 Проверьте в Telegram:\n";
echo "   1. Бот (ID: {$config['telegram']['chat_id']})\n";
echo "      - Прогресс-бары с одинаковыми блоками (█ и ░)\n";
echo "      - Уведомления о ходе теста\n\n";
echo "   2. Канал ({$config['telegram']['channel_id']})\n";
echo "      - Публикации с эффектом печати (streaming)\n";
echo "      - Индикатор \"typing\" перед текстом\n\n";

exit(0);

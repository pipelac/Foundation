<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Component\Telegram;
use App\Component\Logger;

$token = '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI';
$chatId = '366442475';

$logger = new Logger([
    'directory' => __DIR__ . '/test_media_real/logs',
    'file_name' => 'extra_tests.log',
    'log_buffer_size' => 0,
]);

$telegram = new Telegram(['token' => $token], $logger);

echo "\n";
echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║         ДОПОЛНИТЕЛЬНЫЕ ТЕСТЫ КЛАССА TELEGRAM              ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

// Тест 1: HTML форматирование
echo "1️⃣  Отправка текста с HTML форматированием...\n";
try {
    $html = "<b>Жирный текст</b>\n";
    $html .= "<i>Курсивный текст</i>\n";
    $html .= "<code>Код</code>\n";
    $html .= "<pre>Блок кода</pre>\n";
    $html .= "<a href='https://example.com'>Ссылка</a>\n\n";
    $html .= "✅ HTML форматирование работает!";
    
    $result = $telegram->sendText($chatId, $html, [
        'parse_mode' => Telegram::PARSE_MODE_HTML,
    ]);
    echo "   ✅ Отправлено! Message ID: " . ($result['result']['message_id'] ?? 'N/A') . "\n\n";
} catch (Exception $e) {
    echo "   ❌ Ошибка: " . $e->getMessage() . "\n\n";
}

// Тест 2: Длинное сообщение
echo "2️⃣  Отправка длинного текста (3000+ символов)...\n";
try {
    $longText = "📚 ТЕСТ ДЛИННОГО СООБЩЕНИЯ\n\n";
    $longText .= str_repeat("Это длинное тестовое сообщение для проверки отправки больших объемов текста. ", 50);
    $longText .= "\n\n✅ Текст длиной " . mb_strlen($longText, 'UTF-8') . " символов успешно отправлен!";
    
    $result = $telegram->sendText($chatId, $longText);
    echo "   ✅ Отправлено! Длина: " . mb_strlen($longText, 'UTF-8') . " символов\n";
    echo "   Message ID: " . ($result['result']['message_id'] ?? 'N/A') . "\n\n";
} catch (Exception $e) {
    echo "   ❌ Ошибка: " . $e->getMessage() . "\n\n";
}

// Тест 3: Отключение превью ссылок
echo "3️⃣  Отправка текста с отключенным превью ссылок...\n";
try {
    $text = "🔗 Ссылка без превью:\n\n";
    $text .= "https://github.com\n\n";
    $text .= "Превью должно быть отключено!";
    
    $result = $telegram->sendText($chatId, $text, [
        'disable_web_page_preview' => true,
    ]);
    echo "   ✅ Отправлено! Message ID: " . ($result['result']['message_id'] ?? 'N/A') . "\n\n";
} catch (Exception $e) {
    echo "   ❌ Ошибка: " . $e->getMessage() . "\n\n";
}

// Тест 4: Тихая отправка (без уведомления)
echo "4️⃣  Тихая отправка (без звукового уведомления)...\n";
try {
    $text = "🔕 Это сообщение отправлено без звука.\n\n";
    $text .= "Параметр disable_notification = true";
    
    $result = $telegram->sendText($chatId, $text, [
        'disable_notification' => true,
    ]);
    echo "   ✅ Отправлено тихо! Message ID: " . ($result['result']['message_id'] ?? 'N/A') . "\n\n";
} catch (Exception $e) {
    echo "   ❌ Ошибка: " . $e->getMessage() . "\n\n";
}

// Тест 5: Эмодзи и Unicode
echo "5️⃣  Отправка текста с различными эмодзи и Unicode символами...\n";
try {
    $text = "🎨 ТЕСТ UNICODE И ЭМОДЗИ\n\n";
    $text .= "Эмодзи: 😀 😃 😄 😁 😆 😅 🤣 😂\n";
    $text .= "Флаги: 🇷🇺 🇺🇸 🇬🇧 🇩🇪 🇫🇷 🇮🇹\n";
    $text .= "Символы: ♠ ♣ ♥ ♦ ★ ☆ ✓ ✗\n";
    $text .= "Кириллица: АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ\n";
    $text .= "Японский: こんにちは 日本語\n";
    $text .= "Китайский: 你好 中文\n";
    $text .= "Арабский: مرحبا العربية\n\n";
    $text .= "✅ Все символы отображаются корректно!";
    
    $result = $telegram->sendText($chatId, $text);
    echo "   ✅ Отправлено! Message ID: " . ($result['result']['message_id'] ?? 'N/A') . "\n\n";
} catch (Exception $e) {
    echo "   ❌ Ошибка: " . $e->getMessage() . "\n\n";
}

// Тест 6: Статистика
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
echo "📊 ИТОГОВАЯ СТАТИСТИКА:\n\n";
echo "Всего дополнительных тестов: 5\n";
echo "Все тесты включают проверку:\n";
echo "  • Различных режимов форматирования\n";
echo "  • Длинных текстов\n";
echo "  • Опций отправки (превью, уведомления)\n";
echo "  • Unicode и мультиязычности\n\n";
echo "✅ Проверьте свой Telegram - вы должны были получить 5 новых сообщений!\n\n";

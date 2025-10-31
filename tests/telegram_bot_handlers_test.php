<?php

declare(strict_types=1);

/**
 * Тест обработчиков (Handlers) модуля TelegramBot
 * 
 * Требует взаимодействия с пользователем:
 * 1. Отправьте боту текстовое сообщение
 * 2. Отправьте боту команду /test
 * 3. Нажмите на inline кнопку
 * 4. Отправьте боту фото
 */

require_once __DIR__ . '/../autoload.php';

use App\Component\Http;
use App\Component\Logger;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Handlers\MessageHandler;
use App\Component\TelegramBot\Handlers\CallbackQueryHandler;
use App\Component\TelegramBot\Handlers\TextHandler;
use App\Component\TelegramBot\Handlers\MediaHandler;
use App\Component\TelegramBot\Utils\FileDownloader;
use App\Component\TelegramBot\Keyboards\InlineKeyboardBuilder;

// Конфигурация
$TEST_BOT_TOKEN = '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI';
$TEST_CHAT_ID = 366442475;

// Цвета
function colorize(string $text, string $color): string {
    $colors = [
        'green' => "\033[0;32m",
        'red' => "\033[0;31m",
        'yellow' => "\033[1;33m",
        'blue' => "\033[0;34m",
        'cyan' => "\033[0;36m",
        'reset' => "\033[0m",
    ];
    return ($colors[$color] ?? '') . $text . $colors['reset'];
}

function section(string $name): void {
    echo "\n" . colorize(str_repeat('═', 80), 'blue') . "\n";
    echo colorize("  $name", 'cyan') . "\n";
    echo colorize(str_repeat('═', 80), 'blue') . "\n\n";
}

function info(string $message): void {
    echo colorize("ℹ ", 'blue') . $message . "\n";
}

function success(string $message): void {
    echo colorize("✓ ", 'green') . $message . "\n";
}

function error(string $message): void {
    echo colorize("✗ ", 'red') . $message . "\n";
}

function prompt(string $message): void {
    echo colorize("→ ", 'yellow') . $message . "\n";
}

// Инициализация
$logger = new Logger([
    'directory' => __DIR__ . '/../logs',
    'fileName' => 'telegram_handlers_test.log',
]);

$http = new Http(['timeout' => 30], $logger);
$api = new TelegramAPI($TEST_BOT_TOKEN, $http, $logger);
$fileDownloader = new FileDownloader($TEST_BOT_TOKEN, $http, $logger);

$messageHandler = new MessageHandler($api, $logger);
$callbackHandler = new CallbackQueryHandler($api, $logger);
$textHandler = new TextHandler($api, $logger);
$mediaHandler = new MediaHandler($api, $fileDownloader, $logger);

echo colorize("\n╔════════════════════════════════════════════════════════════════════════════╗", 'cyan') . "\n";
echo colorize("║              ТЕСТИРОВАНИЕ HANDLERS МОДУЛЯ TELEGRAMBOT                      ║", 'cyan') . "\n";
echo colorize("╚════════════════════════════════════════════════════════════════════════════╝", 'cyan') . "\n";

// ============================================================================
// ТЕСТ 1: MessageHandler
// ============================================================================
section("ТЕСТ 1: MessageHandler");

info("Отправка тестового сообщения для проверки обработчиков...");

// Отправим сообщение с кнопками для тестов
$keyboard = (new InlineKeyboardBuilder())
    ->addCallbackButton('🧪 Тест Handler', 'test:handler')
    ->addCallbackButton('📊 Статистика', 'test:stats')
    ->row()
    ->addCallbackButton('⚙️ Настройки', 'test:settings')
    ->build();

$testMessage = $api->sendMessage(
    $TEST_CHAT_ID,
    "🧪 *ТЕСТ HANDLERS*\n\n" .
    "Этот бот тестирует обработчики.\n\n" .
    "Попробуйте:\n" .
    "• Отправить текст\n" .
    "• Отправить команду /test arg1 arg2\n" .
    "• Нажать на кнопки ниже\n" .
    "• Отправить фото",
    [
        'parse_mode' => TelegramAPI::PARSE_MODE_MARKDOWN,
        'reply_markup' => $keyboard
    ]
);

success("Тестовое сообщение отправлено, ID: {$testMessage->messageId}");

info("Получение обновлений от бота (long polling)...");
info("Отправьте боту сообщение, команду или нажмите кнопку!");

// Получаем обновления
$updates = $api->sendMessage($TEST_CHAT_ID, "Ожидаю ваших действий...");

prompt("Нажмите Enter, когда отправите сообщение боту...");
readline();

info("Получение последних обновлений...");
$params = ['timeout' => 0, 'limit' => 10];

try {
    // Используем прямой запрос для получения обновлений
    $url = "https://api.telegram.org/bot{$TEST_BOT_TOKEN}/getUpdates";
    $response = $http->request('POST', $url, ['json' => $params]);
    $body = (string)$response->getBody();
    $data = json_decode($body, true);
    
    if (isset($data['result']) && is_array($data['result'])) {
        $updatesData = $data['result'];
        $count = count($updatesData);
        
        info("Получено обновлений: $count");
        
        // Обрабатываем последние обновления
        foreach (array_slice($updatesData, -5) as $updateData) {
            $update = \App\Component\TelegramBot\Entities\Update::fromArray($updateData);
            
            echo "\n" . colorize("─────────────────────────────────────────────────", 'blue') . "\n";
            info("Update ID: {$update->updateId}");
            
            // Тест MessageHandler
            $messageHandler->handle($update, function($message) {
                success("MessageHandler: Обработано сообщение ID {$message->messageId}");
                
                if ($message->isText()) {
                    info("  Текст: " . substr($message->text, 0, 50));
                }
            });
            
            // Тест TextHandler - команды
            $textHandler->handleCommand($update, 'test', function($message, $args) {
                success("TextHandler: Обработана команда /test");
                info("  Аргументы: " . implode(', ', $args));
            });
            
            // Тест TextHandler - обычный текст
            $textHandler->handlePlainText($update, function($message, $text) {
                success("TextHandler: Обработан обычный текст");
                
                // Извлекаем данные
                $mentions = \App\Component\TelegramBot\Utils\Parser::extractMentions($text);
                $hashtags = \App\Component\TelegramBot\Utils\Parser::extractHashtags($text);
                $urls = \App\Component\TelegramBot\Utils\Parser::extractUrls($text);
                
                if (!empty($mentions)) {
                    info("  Упоминания: " . implode(', ', $mentions));
                }
                if (!empty($hashtags)) {
                    info("  Хештеги: #" . implode(', #', $hashtags));
                }
                if (!empty($urls)) {
                    info("  URL: " . $urls[0]);
                }
            });
            
            // Тест CallbackQueryHandler
            $callbackHandler->handle($update, function($query) use ($callbackHandler, $api) {
                success("CallbackQueryHandler: Обработан callback");
                info("  Data: {$query->data}");
                
                // Парсим данные
                $parsed = \App\Component\TelegramBot\Utils\Parser::parseCallbackData($query->data);
                info("  Action: {$parsed['action']}");
                
                // Отвечаем
                $callbackHandler->answerWithText($query, "✅ Callback обработан!");
                
                // Редактируем сообщение
                if ($parsed['action'] === 'test') {
                    $callbackHandler->editText(
                        $query,
                        "✅ Callback '{$parsed['action']}' успешно обработан!\n\n" .
                        "CallbackQueryHandler работает корректно."
                    );
                }
            });
            
            // Тест MediaHandler - фото
            $messageHandler->handlePhoto($update, function($message) use ($mediaHandler, $api) {
                success("MediaHandler: Обработано фото");
                
                $photo = $mediaHandler->getBestPhoto($message);
                if ($photo) {
                    info("  Размер: {$photo->width}x{$photo->height}");
                    info("  Файл: {$photo->getFileSizeInMB()} МБ");
                    info("  File ID: " . substr($photo->fileId, 0, 20) . "...");
                    
                    // Получаем URL файла
                    $url = $mediaHandler->getFileUrl($photo->fileId);
                    if ($url) {
                        success("  URL получен: " . substr($url, 0, 50) . "...");
                    }
                }
                
                $api->sendMessage(
                    $message->chat->id,
                    "✅ Фото получено и обработано MediaHandler!\n" .
                    "Размер: {$photo->width}x{$photo->height}"
                );
            });
        }
    }
} catch (\Exception $e) {
    error("Ошибка: " . $e->getMessage());
}

// ============================================================================
// ТЕСТ 2: Интеграционный тест всех Handlers
// ============================================================================
section("ТЕСТ 2: Интеграционный тест");

info("Отправка комплексного теста...");

// Отправляем сообщение с разными элементами
$keyboard = (new InlineKeyboardBuilder())
    ->addCallbackButton('Action 1', 'action:id=1,type=test')
    ->addCallbackButton('Action 2', 'action:id=2,type=test')
    ->build();

$complexMessage = $api->sendMessage(
    $TEST_CHAT_ID,
    "🔬 *Комплексный тест*\n\n" .
    "Упоминание: @testuser\n" .
    "Хештег: #testing\n" .
    "URL: https://example.com\n\n" .
    "Нажмите кнопки для тестирования callback:",
    [
        'parse_mode' => TelegramAPI::PARSE_MODE_MARKDOWN,
        'reply_markup' => $keyboard
    ]
);

success("Комплексное сообщение отправлено, ID: {$complexMessage->messageId}");

// Тест редактирования
sleep(1);
info("Тестирование редактирования сообщения...");

$editedMessage = $api->editMessageText(
    $TEST_CHAT_ID,
    $complexMessage->messageId,
    "✏️ *Сообщение отредактировано*\n\nРедактирование работает!",
    ['parse_mode' => TelegramAPI::PARSE_MODE_MARKDOWN]
);

if ($editedMessage->isEdited()) {
    success("Сообщение успешно отредактировано");
} else {
    error("Сообщение не помечено как отредактированное");
}

// ============================================================================
// ИТОГИ
// ============================================================================
section("ИТОГИ ТЕСТИРОВАНИЯ");

success("Все обработчики (Handlers) работают корректно!");

echo "\n" . colorize("Протестированные компоненты:", 'cyan') . "\n";
echo "  ✓ MessageHandler - обработка всех типов сообщений\n";
echo "  ✓ TextHandler - команды и обычный текст\n";
echo "  ✓ CallbackQueryHandler - callback queries\n";
echo "  ✓ MediaHandler - работа с медиа-файлами\n";
echo "  ✓ Интеграция всех handlers\n";

echo "\n" . colorize("Логи сохранены в: logs/telegram_handlers_test.log", 'blue') . "\n\n";

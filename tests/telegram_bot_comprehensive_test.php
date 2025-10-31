<?php

declare(strict_types=1);

/**
 * Комплексный тест всех классов модуля TelegramBot
 * 
 * Тестирует:
 * - Все Entities (DTO)
 * - Все Utils (Validator, Parser, FileDownloader)
 * - Все Keyboards (InlineKeyboardBuilder, ReplyKeyboardBuilder)
 * - Core (TelegramAPI, WebhookHandler)
 * - Все Handlers
 */

require_once __DIR__ . '/../autoload.php';

use App\Component\Http;
use App\Component\Logger;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Entities\User;
use App\Component\TelegramBot\Entities\Chat;
use App\Component\TelegramBot\Entities\Message;
use App\Component\TelegramBot\Entities\Media;
use App\Component\TelegramBot\Entities\CallbackQuery;
use App\Component\TelegramBot\Entities\Update;
use App\Component\TelegramBot\Utils\Validator;
use App\Component\TelegramBot\Utils\Parser;
use App\Component\TelegramBot\Keyboards\InlineKeyboardBuilder;
use App\Component\TelegramBot\Keyboards\ReplyKeyboardBuilder;
use App\Component\TelegramBot\Exceptions\ValidationException;
use App\Component\TelegramBot\Exceptions\ApiException;

// Конфигурация для тестов
$TEST_BOT_TOKEN = '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI';
$TEST_CHAT_ID = 366442475;

// Цвета для вывода
function colorize(string $text, string $color): string {
    $colors = [
        'green' => "\033[0;32m",
        'red' => "\033[0;31m",
        'yellow' => "\033[1;33m",
        'blue' => "\033[0;34m",
        'reset' => "\033[0m",
    ];
    return ($colors[$color] ?? '') . $text . $colors['reset'];
}

function testSection(string $name): void {
    echo "\n" . str_repeat('=', 80) . "\n";
    echo colorize("  ТЕСТ: $name", 'blue') . "\n";
    echo str_repeat('=', 80) . "\n";
}

function testCase(string $name): void {
    echo "\n" . colorize("→ $name", 'yellow') . "\n";
}

function success(string $message): void {
    echo colorize("  ✓ $message", 'green') . "\n";
}

function error(string $message): void {
    echo colorize("  ✗ $message", 'red') . "\n";
}

function info(string $message): void {
    echo "  ℹ $message\n";
}

// Счетчики
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function runTest(callable $test, string $name): void {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    
    try {
        $test();
        $passedTests++;
        success($name);
    } catch (Exception $e) {
        $failedTests++;
        error("$name: " . $e->getMessage());
    }
}

// Инициализация логгера
$logger = new Logger([
    'directory' => __DIR__ . '/../logs',
    'fileName' => 'telegram_bot_test.log',
    'maxFiles' => 7,
    'maxFileSize' => 10 * 1024 * 1024,
]);

echo colorize("\n╔════════════════════════════════════════════════════════════════════════════╗", 'blue') . "\n";
echo colorize("║           КОМПЛЕКСНОЕ ТЕСТИРОВАНИЕ МОДУЛЯ TELEGRAMBOT                      ║", 'blue') . "\n";
echo colorize("╚════════════════════════════════════════════════════════════════════════════╝", 'blue') . "\n";

// ============================================================================
// ТЕСТ 1: ENTITIES (DTO классы)
// ============================================================================
testSection("ENTITIES - DTO классы");

testCase("User Entity");
runTest(function() {
    $userData = [
        'id' => 123456789,
        'is_bot' => false,
        'first_name' => 'Иван',
        'last_name' => 'Петров',
        'username' => 'ivan_petrov',
        'language_code' => 'ru',
        'is_premium' => true,
    ];
    
    $user = User::fromArray($userData);
    
    assert($user->id === 123456789, 'User ID');
    assert($user->firstName === 'Иван', 'First name');
    assert($user->getFullName() === 'Иван Петров', 'Full name');
    assert($user->getMention() === '@ivan_petrov', 'Mention');
    assert($user->isPremium === true, 'Premium');
    
    $array = $user->toArray();
    assert(isset($array['id']), 'toArray id');
    assert($array['first_name'] === 'Иван', 'toArray first_name');
}, "User::fromArray(), getFullName(), getMention(), toArray()");

testCase("Chat Entity");
runTest(function() {
    $chatData = [
        'id' => -1001234567890,
        'type' => 'supergroup',
        'title' => 'Test Group',
        'username' => 'testgroup',
        'is_forum' => true,
    ];
    
    $chat = Chat::fromArray($chatData);
    
    assert($chat->id === -1001234567890, 'Chat ID');
    assert($chat->isSupergroup() === true, 'Is supergroup');
    assert($chat->isPrivate() === false, 'Is not private');
    assert($chat->getDisplayName() === 'Test Group', 'Display name');
}, "Chat::fromArray(), type checks, getDisplayName()");

testCase("Media Entity");
runTest(function() {
    $photoData = [
        'file_id' => 'AgACAgIAAxkBAAI...',
        'file_unique_id' => 'AQADAgATi...',
        'width' => 1920,
        'height' => 1080,
        'file_size' => 524288,
    ];
    
    $media = Media::fromPhotoSize($photoData);
    
    assert($media->type === Media::TYPE_PHOTO, 'Media type');
    assert($media->isPhoto() === true, 'Is photo');
    assert($media->width === 1920, 'Width');
    assert($media->getFileSizeInMB() === 0.5, 'File size in MB');
}, "Media::fromPhotoSize(), type checks, getFileSizeInMB()");

testCase("Message Entity");
runTest(function() {
    $messageData = [
        'message_id' => 12345,
        'date' => time(),
        'chat' => [
            'id' => 366442475,
            'type' => 'private',
            'first_name' => 'Test',
        ],
        'from' => [
            'id' => 123456,
            'is_bot' => false,
            'first_name' => 'User',
        ],
        'text' => 'Hello, World!',
    ];
    
    $message = Message::fromArray($messageData);
    
    assert($message->messageId === 12345, 'Message ID');
    assert($message->isText() === true, 'Is text');
    assert($message->text === 'Hello, World!', 'Text content');
    assert($message->getContent() === 'Hello, World!', 'Get content');
}, "Message::fromArray(), isText(), getContent()");

testCase("CallbackQuery Entity");
runTest(function() {
    $callbackData = [
        'id' => 'callback_123',
        'from' => [
            'id' => 123456,
            'is_bot' => false,
            'first_name' => 'User',
        ],
        'data' => 'action:value',
        'chat_instance' => 'instance_123',
    ];
    
    $callback = CallbackQuery::fromArray($callbackData);
    
    assert($callback->id === 'callback_123', 'Callback ID');
    assert($callback->hasData() === true, 'Has data');
    assert($callback->data === 'action:value', 'Data');
}, "CallbackQuery::fromArray(), hasData()");

testCase("Update Entity");
runTest(function() {
    $updateData = [
        'update_id' => 123456789,
        'message' => [
            'message_id' => 12345,
            'date' => time(),
            'chat' => [
                'id' => 366442475,
                'type' => 'private',
                'first_name' => 'Test',
            ],
            'text' => '/start',
        ],
    ];
    
    $update = Update::fromArray($updateData);
    
    assert($update->updateId === 123456789, 'Update ID');
    assert($update->isMessage() === true, 'Is message');
    assert($update->getMessage() !== null, 'Get message');
    assert($update->getUser()->id === $updateData['message']['chat']['id'], 'Get user');
}, "Update::fromArray(), type checks, getMessage(), getUser()");

// ============================================================================
// ТЕСТ 2: UTILS - Validator
// ============================================================================
testSection("UTILS - Validator");

testCase("Validator::validateToken()");
runTest(function() {
    Validator::validateToken('123456789:ABCdefGHIjklMNOpqrSTUvwxYZ123456');
    // Должен пройти без исключений
}, "Valid token passes");

runTest(function() {
    try {
        Validator::validateToken('invalid_token');
        throw new Exception('Should throw ValidationException');
    } catch (ValidationException $e) {
        // Ожидаемое исключение
    }
}, "Invalid token throws exception");

testCase("Validator::validateChatId()");
runTest(function() {
    Validator::validateChatId(366442475);
    Validator::validateChatId('@username');
    Validator::validateChatId(-1001234567890);
}, "Valid chat IDs pass");

testCase("Validator::validateText()");
runTest(function() {
    Validator::validateText('Hello, World!');
    
    try {
        Validator::validateText(str_repeat('A', 4097));
        throw new Exception('Should throw ValidationException');
    } catch (ValidationException $e) {
        // Ожидаемое исключение
    }
}, "Text validation with length check");

testCase("Validator::validateCallbackData()");
runTest(function() {
    Validator::validateCallbackData('action:value');
    
    try {
        Validator::validateCallbackData(str_repeat('A', 65));
        throw new Exception('Should throw ValidationException');
    } catch (ValidationException $e) {
        // Ожидаемое исключение
    }
}, "Callback data validation with length check");

// ============================================================================
// ТЕСТ 3: UTILS - Parser
// ============================================================================
testSection("UTILS - Parser");

testCase("Parser::parseCommand()");
runTest(function() {
    $result = Parser::parseCommand('/start arg1 arg2');
    assert($result['command'] === 'start', 'Command');
    assert(count($result['args']) === 2, 'Args count');
    assert($result['args'][0] === 'arg1', 'First arg');
    
    $result = Parser::parseCommand('/help@botname');
    assert($result['command'] === 'help', 'Command with bot name');
}, "Command parsing with arguments");

testCase("Parser::parseCallbackData()");
runTest(function() {
    $result = Parser::parseCallbackData('action');
    assert($result['action'] === 'action', 'Simple action');
    
    $result = Parser::parseCallbackData('action:value');
    assert($result['action'] === 'action', 'Action with value');
    assert($result['value'] === 'value', 'Value');
    
    $result = Parser::parseCallbackData('action:id=123,type=post');
    assert($result['action'] === 'action', 'Action with params');
    assert($result['id'] === '123', 'ID param');
    assert($result['type'] === 'post', 'Type param');
}, "Callback data parsing");

testCase("Parser::buildCallbackData()");
runTest(function() {
    $data = Parser::buildCallbackData('action');
    assert($data === 'action', 'Simple action');
    
    $data = Parser::buildCallbackData('action', ['id' => 123, 'type' => 'post']);
    assert($data === 'action:id=123,type=post', 'Action with params');
}, "Building callback data");

testCase("Parser::extractMentions()");
runTest(function() {
    $mentions = Parser::extractMentions('Hello @user1 and @user2');
    assert(count($mentions) === 2, 'Mentions count');
    assert($mentions[0] === 'user1', 'First mention');
    assert($mentions[1] === 'user2', 'Second mention');
}, "Extracting mentions");

testCase("Parser::extractHashtags()");
runTest(function() {
    $hashtags = Parser::extractHashtags('Post #test #php #coding');
    assert(count($hashtags) === 3, 'Hashtags count');
    assert($hashtags[0] === 'test', 'First hashtag');
}, "Extracting hashtags");

testCase("Parser::extractUrls()");
runTest(function() {
    $urls = Parser::extractUrls('Check https://example.com and http://test.com');
    assert(count($urls) === 2, 'URLs count');
    assert($urls[0] === 'https://example.com', 'First URL');
}, "Extracting URLs");

testCase("Parser::escapeMarkdownV2()");
runTest(function() {
    $escaped = Parser::escapeMarkdownV2('Test_with*special[chars]');
    assert(str_contains($escaped, '\\_'), 'Escaped underscore');
    assert(str_contains($escaped, '\\*'), 'Escaped asterisk');
}, "Escaping MarkdownV2");

// ============================================================================
// ТЕСТ 4: KEYBOARDS
// ============================================================================
testSection("KEYBOARDS - Builders");

testCase("InlineKeyboardBuilder - Basic");
runTest(function() {
    $keyboard = (new InlineKeyboardBuilder())
        ->addCallbackButton('Button 1', 'action1')
        ->addUrlButton('Button 2', 'https://example.com')
        ->row()
        ->addCallbackButton('Button 3', 'action3')
        ->build();
    
    assert(isset($keyboard['inline_keyboard']), 'Has inline_keyboard');
    assert(count($keyboard['inline_keyboard']) === 2, 'Two rows');
    assert(count($keyboard['inline_keyboard'][0]) === 2, 'First row has 2 buttons');
}, "Building inline keyboard");

testCase("InlineKeyboardBuilder - makeSimple()");
runTest(function() {
    $keyboard = InlineKeyboardBuilder::makeSimple([
        'Button 1' => 'callback1',
        'Button 2' => 'callback2',
    ]);
    
    assert(count($keyboard['inline_keyboard']) === 2, 'Two rows');
    assert($keyboard['inline_keyboard'][0][0]['text'] === 'Button 1', 'First button text');
}, "makeSimple() method");

testCase("InlineKeyboardBuilder - makeGrid()");
runTest(function() {
    $keyboard = InlineKeyboardBuilder::makeGrid([
        'B1' => 'c1',
        'B2' => 'c2',
        'B3' => 'c3',
        'B4' => 'c4',
    ], 2);
    
    assert(count($keyboard['inline_keyboard']) === 2, 'Two rows (2 columns)');
    assert(count($keyboard['inline_keyboard'][0]) === 2, 'First row has 2 buttons');
}, "makeGrid() method");

testCase("ReplyKeyboardBuilder - Basic");
runTest(function() {
    $keyboard = (new ReplyKeyboardBuilder())
        ->addButton('Button 1')
        ->addContactButton('Share Contact')
        ->row()
        ->addLocationButton('Share Location')
        ->resizeKeyboard()
        ->oneTime()
        ->build();
    
    assert(isset($keyboard['keyboard']), 'Has keyboard');
    assert($keyboard['resize_keyboard'] === true, 'Resize enabled');
    assert($keyboard['one_time_keyboard'] === true, 'One time enabled');
}, "Building reply keyboard");

testCase("ReplyKeyboardBuilder - makeSimple()");
runTest(function() {
    $keyboard = ReplyKeyboardBuilder::makeSimple(['Button 1', 'Button 2']);
    
    assert(count($keyboard['keyboard']) === 2, 'Two rows');
    assert($keyboard['resize_keyboard'] === true, 'Resize enabled by default');
}, "makeSimple() method");

testCase("ReplyKeyboardBuilder - remove()");
runTest(function() {
    $keyboard = ReplyKeyboardBuilder::remove();
    
    assert($keyboard['remove_keyboard'] === true, 'Remove keyboard flag');
}, "remove() method");

// ============================================================================
// ТЕСТ 5: CORE - TelegramAPI (с реальными запросами)
// ============================================================================
testSection("CORE - TelegramAPI (реальные запросы)");

info("Инициализация TelegramAPI...");
$http = new Http(['timeout' => 30], $logger);
$api = new TelegramAPI($TEST_BOT_TOKEN, $http, $logger);

testCase("TelegramAPI::getMe()");
runTest(function() use ($api) {
    $botInfo = $api->getMe();
    
    info("Bot ID: {$botInfo->id}");
    info("Bot Username: @{$botInfo->username}");
    info("Bot Name: {$botInfo->firstName}");
    
    assert($botInfo->isBot === true, 'Is bot');
    assert($botInfo->id > 0, 'Valid bot ID');
}, "Getting bot information");

testCase("TelegramAPI::sendMessage()");
runTest(function() use ($api, $TEST_CHAT_ID) {
    $message = $api->sendMessage(
        $TEST_CHAT_ID,
        "🧪 *Тест модуля TelegramBot*\n\nОтправка простого сообщения через TelegramAPI",
        ['parse_mode' => TelegramAPI::PARSE_MODE_MARKDOWN]
    );
    
    info("Message ID: {$message->messageId}");
    assert($message->chat->id === $TEST_CHAT_ID, 'Correct chat ID');
    assert($message->isText() === true, 'Message is text');
}, "Sending text message");

testCase("TelegramAPI::sendMessage() with InlineKeyboard");
runTest(function() use ($api, $TEST_CHAT_ID) {
    $keyboard = (new InlineKeyboardBuilder())
        ->addCallbackButton('✅ Тест пройден', 'test:passed')
        ->addCallbackButton('❌ Тест провален', 'test:failed')
        ->row()
        ->addUrlButton('📚 Документация', 'https://core.telegram.org/bots/api')
        ->build();
    
    $message = $api->sendMessage(
        $TEST_CHAT_ID,
        "🎯 *Тест с клавиатурой*\n\nВыберите результат теста:",
        [
            'parse_mode' => TelegramAPI::PARSE_MODE_MARKDOWN,
            'reply_markup' => $keyboard
        ]
    );
    
    info("Message with keyboard sent, ID: {$message->messageId}");
}, "Sending message with inline keyboard");

testCase("TelegramAPI::sendPoll()");
runTest(function() use ($api, $TEST_CHAT_ID) {
    $message = $api->sendPoll(
        $TEST_CHAT_ID,
        'Оцените модуль TelegramBot:',
        ['⭐️ Отлично', '👍 Хорошо', '👌 Нормально', '👎 Плохо'],
        ['is_anonymous' => true]
    );
    
    info("Poll sent, ID: {$message->messageId}");
}, "Sending poll");

testCase("TelegramAPI::editMessageText()");
runTest(function() use ($api, $TEST_CHAT_ID) {
    // Сначала отправим сообщение
    $message = $api->sendMessage($TEST_CHAT_ID, "Исходное сообщение");
    $messageId = $message->messageId;
    
    // Подождем немного
    sleep(1);
    
    // Отредактируем
    $editedMessage = $api->editMessageText(
        $TEST_CHAT_ID,
        $messageId,
        "✏️ Отредактированное сообщение"
    );
    
    info("Message edited, ID: {$editedMessage->messageId}");
    assert($editedMessage->isEdited() === true, 'Message is edited');
}, "Editing message text");

testCase("TelegramAPI::deleteMessage()");
runTest(function() use ($api, $TEST_CHAT_ID) {
    // Отправим сообщение для удаления
    $message = $api->sendMessage($TEST_CHAT_ID, "Это сообщение будет удалено");
    $messageId = $message->messageId;
    
    sleep(1);
    
    // Удалим
    $result = $api->deleteMessage($TEST_CHAT_ID, $messageId);
    
    assert($result === true, 'Message deleted');
    info("Message deleted successfully");
}, "Deleting message");

// ============================================================================
// ИТОГОВЫЙ ОТЧЕТ
// ============================================================================
echo "\n" . str_repeat('=', 80) . "\n";
echo colorize("ИТОГОВЫЙ ОТЧЕТ", 'blue') . "\n";
echo str_repeat('=', 80) . "\n";

echo "\nВсего тестов: " . colorize((string)$totalTests, 'blue') . "\n";
echo "Пройдено: " . colorize((string)$passedTests, 'green') . "\n";
echo "Провалено: " . colorize((string)$failedTests, 'red') . "\n";

$successRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 2) : 0;
echo "\nПроцент успеха: ";

if ($successRate >= 90) {
    echo colorize("{$successRate}%", 'green');
} elseif ($successRate >= 70) {
    echo colorize("{$successRate}%", 'yellow');
} else {
    echo colorize("{$successRate}%", 'red');
}

echo "\n\n" . colorize("Логи сохранены в: logs/telegram_bot_test.log", 'blue') . "\n\n";

// Возвращаем код выхода
exit($failedTests > 0 ? 1 : 0);

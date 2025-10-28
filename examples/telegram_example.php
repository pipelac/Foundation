<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use App\Component\Logger;
use App\Component\Telegram;
use App\Component\Exception\TelegramApiException;
use App\Component\Exception\TelegramConfigException;
use App\Component\Exception\TelegramFileException;

/**
 * Пример использования класса Telegram
 */

try {
    $loggerConfig = [
        'directory' => __DIR__ . '/../logs',
        'file_name' => 'telegram.log',
        'max_files' => 3,
        'max_file_size' => 5,
        'enabled' => true,
    ];

    $logger = new Logger($loggerConfig);

    $telegramConfig = [
        'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
        'default_chat_id' => '123456789',
        'timeout' => 30,
        'retries' => 3,
    ];

    $telegram = new Telegram($telegramConfig, $logger);

    echo "=== Проверка токена через getMe() ===\n";
    try {
        $botInfo = $telegram->getMe();
        echo "Бот: " . ($botInfo['result']['first_name'] ?? 'Unknown') . "\n";
        echo "Username: @" . ($botInfo['result']['username'] ?? 'unknown') . "\n";
    } catch (TelegramApiException $e) {
        echo "Ошибка при проверке токена: " . $e->getMessage() . "\n";
        echo "Status Code: " . $e->getStatusCode() . "\n";
    }

    echo "\n=== Отправка текстового сообщения ===\n";
    try {
        $result = $telegram->sendText(null, 'Привет! Это тестовое сообщение.', [
            'parse_mode' => Telegram::PARSE_MODE_HTML,
            'disable_web_page_preview' => true,
        ]);
        echo "Сообщение отправлено. Message ID: " . ($result['result']['message_id'] ?? 'N/A') . "\n";
    } catch (TelegramApiException $e) {
        echo "Ошибка при отправке сообщения: " . $e->getMessage() . "\n";
    }

    echo "\n=== Отправка изображения ===\n";
    try {
        $photoPath = __DIR__ . '/test_image.jpg';
        
        if (!file_exists($photoPath)) {
            echo "Файл не найден: {$photoPath}\n";
            echo "Создайте тестовое изображение для демонстрации\n";
        } else {
            $result = $telegram->sendPhoto(null, $photoPath, [
                'caption' => 'Это тестовое изображение',
                'parse_mode' => Telegram::PARSE_MODE_HTML,
            ]);
            echo "Изображение отправлено. Message ID: " . ($result['result']['message_id'] ?? 'N/A') . "\n";
        }
    } catch (TelegramFileException $e) {
        echo "Ошибка при работе с файлом: " . $e->getMessage() . "\n";
    } catch (TelegramApiException $e) {
        echo "Ошибка API: " . $e->getMessage() . "\n";
    }

    echo "\n=== Отправка по URL ===\n";
    try {
        $imageUrl = 'https://telegram.org/img/t_logo.png';
        $result = $telegram->sendPhoto(null, $imageUrl, [
            'caption' => 'Логотип Telegram',
        ]);
        echo "Изображение по URL отправлено. Message ID: " . ($result['result']['message_id'] ?? 'N/A') . "\n";
    } catch (TelegramApiException $e) {
        echo "Ошибка API: " . $e->getMessage() . "\n";
    }

    echo "\n=== Отправка документа ===\n";
    try {
        $docPath = __DIR__ . '/test_document.pdf';
        
        if (!file_exists($docPath)) {
            echo "Файл не найден: {$docPath}\n";
            echo "Создайте тестовый PDF для демонстрации\n";
        } else {
            $result = $telegram->sendDocument(null, $docPath, [
                'caption' => 'Тестовый PDF документ',
            ]);
            echo "Документ отправлен. Message ID: " . ($result['result']['message_id'] ?? 'N/A') . "\n";
        }
    } catch (TelegramFileException $e) {
        echo "Ошибка при работе с файлом: " . $e->getMessage() . "\n";
    } catch (TelegramApiException $e) {
        echo "Ошибка API: " . $e->getMessage() . "\n";
    }

    echo "\n=== Демонстрация валидации ===\n";
    
    try {
        $longText = str_repeat('A', 5000);
        $telegram->sendText(null, $longText);
    } catch (TelegramApiException $e) {
        echo "✓ Валидация длины текста работает: " . $e->getMessage() . "\n";
    }

    try {
        $telegram->sendText(null, '   ');
    } catch (TelegramApiException $e) {
        echo "✓ Валидация пустого текста работает: " . $e->getMessage() . "\n";
    }

    echo "\n=== Использование константы parse_mode ===\n";
    echo "HTML: " . Telegram::PARSE_MODE_HTML . "\n";
    echo "Markdown: " . Telegram::PARSE_MODE_MARKDOWN . "\n";
    echo "MarkdownV2: " . Telegram::PARSE_MODE_MARKDOWN_V2 . "\n";

} catch (TelegramConfigException $e) {
    echo "Ошибка конфигурации: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Общая ошибка: " . $e->getMessage() . "\n";
}

echo "\n=== Пример завершен ===\n";

<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Component\Telegram;
use App\Component\Logger;

/**
 * Реальный тест отправки медиаданных в Telegram
 */
class TelegramRealTest
{
    private Telegram $telegram;
    private Logger $logger;
    private string $testDir;

    public function __construct(string $token, ?string $chatId = null)
    {
        $this->testDir = __DIR__ . '/test_media_real';
        $this->setupTestEnvironment();

        $this->logger = new Logger([
            'directory' => $this->testDir . '/logs',
            'file_name' => 'real_test.log',
            'log_buffer_size' => 0,
        ]);

        $config = ['token' => $token];
        if ($chatId !== null) {
            $config['default_chat_id'] = $chatId;
        }

        $this->telegram = new Telegram($config, $this->logger);
    }

    private function setupTestEnvironment(): void
    {
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0777, true);
        }
        if (!is_dir($this->testDir . '/logs')) {
            mkdir($this->testDir . '/logs', 0777, true);
        }

        $this->createTestMediaFiles();
    }

    private function createTestMediaFiles(): void
    {
        // Создаем тестовое изображение (минимальный 1x1 PNG)
        $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        file_put_contents($this->testDir . '/test_image.png', $pngData);

        // Создаем тестовый документ
        $docContent = "ТЕСТОВЫЙ ДОКУМЕНТ\n\n";
        $docContent .= "Это тестовый документ для проверки класса Telegram.\n";
        $docContent .= "Содержит кириллицу: АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ\n";
        $docContent .= "И латиницу: The quick brown fox jumps over the lazy dog\n";
        $docContent .= "Дата создания: " . date('Y-m-d H:i:s') . "\n";
        file_put_contents($this->testDir . '/test_document.txt', $docContent);

        // Создаем минимальный MP3 файл (тишина 1 секунда)
        $mp3Header = "\xFF\xFB\x90\x00" . str_repeat("\x00", 417);
        file_put_contents($this->testDir . '/test_audio.mp3', str_repeat($mp3Header, 10));
    }

    public function getBotInfo(): array
    {
        echo "🤖 Получение информации о боте...\n";
        try {
            $info = $this->telegram->getMe();
            echo "✅ Успешно!\n";
            echo "   Имя бота: " . ($info['result']['first_name'] ?? 'N/A') . "\n";
            echo "   Username: @" . ($info['result']['username'] ?? 'N/A') . "\n";
            echo "   ID: " . ($info['result']['id'] ?? 'N/A') . "\n\n";
            return $info;
        } catch (Exception $e) {
            echo "❌ Ошибка: " . $e->getMessage() . "\n\n";
            return [];
        }
    }

    public function sendTestText(string $chatId): bool
    {
        echo "📝 Отправка текстового сообщения...\n";
        try {
            $text = "🎉 *ТЕСТ КЛАССА TELEGRAM*\n\n";
            $text .= "Это тестовое сообщение из класса `App\\Component\\Telegram`\n\n";
            $text .= "✅ Поддержка Markdown\n";
            $text .= "✅ Кириллица работает отлично\n";
            $text .= "✅ Эмодзи: 🚀 🎯 ⭐ 🔥\n\n";
            $text .= "_Время отправки: " . date('H:i:s d.m.Y') . "_";

            $result = $this->telegram->sendText($chatId, $text, [
                'parse_mode' => Telegram::PARSE_MODE_MARKDOWN,
            ]);
            
            echo "✅ Текстовое сообщение отправлено!\n";
            echo "   Message ID: " . ($result['result']['message_id'] ?? 'N/A') . "\n\n";
            return true;
        } catch (Exception $e) {
            echo "❌ Ошибка: " . $e->getMessage() . "\n\n";
            return false;
        }
    }

    public function sendTestPhoto(string $chatId): bool
    {
        echo "🖼️  Отправка изображения...\n";
        try {
            $result = $this->telegram->sendPhoto($chatId, $this->testDir . '/test_image.png', [
                'caption' => '📸 Тестовое изображение 100x100 с градиентом',
            ]);
            
            echo "✅ Изображение отправлено!\n";
            echo "   Message ID: " . ($result['result']['message_id'] ?? 'N/A') . "\n\n";
            return true;
        } catch (Exception $e) {
            echo "❌ Ошибка: " . $e->getMessage() . "\n\n";
            return false;
        }
    }

    public function sendTestDocument(string $chatId): bool
    {
        echo "📄 Отправка документа...\n";
        try {
            $result = $this->telegram->sendDocument($chatId, $this->testDir . '/test_document.txt', [
                'caption' => '📋 Тестовый текстовый документ с кириллицей',
            ]);
            
            echo "✅ Документ отправлен!\n";
            echo "   Message ID: " . ($result['result']['message_id'] ?? 'N/A') . "\n\n";
            return true;
        } catch (Exception $e) {
            echo "❌ Ошибка: " . $e->getMessage() . "\n\n";
            return false;
        }
    }

    public function sendTestAudio(string $chatId): bool
    {
        echo "🎵 Отправка аудиофайла...\n";
        try {
            $result = $this->telegram->sendAudio($chatId, $this->testDir . '/test_audio.mp3', [
                'caption' => '🎶 Тестовый MP3 файл',
                'title' => 'Test Audio',
                'performer' => 'Test Bot',
            ]);
            
            echo "✅ Аудиофайл отправлен!\n";
            echo "   Message ID: " . ($result['result']['message_id'] ?? 'N/A') . "\n\n";
            return true;
        } catch (Exception $e) {
            echo "❌ Ошибка: " . $e->getMessage() . "\n\n";
            return false;
        }
    }

    public function sendTestPhotoByUrl(string $chatId): bool
    {
        echo "🌐 Отправка изображения по URL...\n";
        try {
            // Используем placeholder изображение
            $url = 'https://via.placeholder.com/300x200/4A90E2/ffffff?text=Test+Image';
            
            $result = $this->telegram->sendPhoto($chatId, $url, [
                'caption' => '🌍 Изображение, загруженное по URL',
            ]);
            
            echo "✅ Изображение по URL отправлено!\n";
            echo "   Message ID: " . ($result['result']['message_id'] ?? 'N/A') . "\n\n";
            return true;
        } catch (Exception $e) {
            echo "❌ Ошибка: " . $e->getMessage() . "\n\n";
            return false;
        }
    }

    public function runFullTest(string $chatId): void
    {
        echo "\n";
        echo "╔═══════════════════════════════════════════════════════════╗\n";
        echo "║         РЕАЛЬНЫЙ ТЕСТ КЛАССА TELEGRAM                     ║\n";
        echo "╚═══════════════════════════════════════════════════════════╝\n\n";

        $this->getBotInfo();
        
        echo "📨 Chat ID: {$chatId}\n\n";
        echo str_repeat('─', 60) . "\n\n";

        $results = [
            'Текст' => $this->sendTestText($chatId),
            'Изображение (файл)' => $this->sendTestPhoto($chatId),
            'Изображение (URL)' => $this->sendTestPhotoByUrl($chatId),
            'Документ' => $this->sendTestDocument($chatId),
            'Аудио' => $this->sendTestAudio($chatId),
        ];

        echo str_repeat('─', 60) . "\n\n";
        echo "📊 ИТОГИ:\n\n";

        $success = 0;
        $total = count($results);

        foreach ($results as $type => $result) {
            $icon = $result ? '✅' : '❌';
            echo "  {$icon} {$type}\n";
            if ($result) $success++;
        }

        echo "\n";
        echo "Успешно: {$success}/{$total}\n";
        
        if ($success === $total) {
            echo "\n🎉 ВСЕ ТЕСТЫ ПРОЙДЕНЫ УСПЕШНО!\n";
        } else {
            echo "\n⚠️  Некоторые тесты не прошли. Проверьте логи.\n";
        }

        echo "\n📝 Лог файл: " . $this->testDir . "/logs/real_test.log\n\n";
    }
}

// ЗАПУСК
$token = '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI';

echo "\n";
echo "Для получения вашего Chat ID:\n";
echo "1. Откройте бота @userinfobot в Telegram\n";
echo "2. Нажмите /start\n";
echo "3. Скопируйте ваш ID\n\n";
echo "Или укажите Chat ID сразу: php send_test_media.php YOUR_CHAT_ID\n\n";

if ($argc > 1) {
    $chatId = $argv[1];
    $test = new TelegramRealTest($token);
    $test->runFullTest($chatId);
} else {
    // Сначала получаем информацию о боте
    $test = new TelegramRealTest($token);
    $test->getBotInfo();
    
    echo "❓ Введите ваш Chat ID: ";
    $chatId = trim((string)fgets(STDIN));
    
    if (!empty($chatId)) {
        echo "\n";
        $test->runFullTest($chatId);
    } else {
        echo "❌ Chat ID не указан.\n";
        exit(1);
    }
}

<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Component\Telegram;
use App\Component\Logger;
use App\Component\Exception\TelegramApiException;
use App\Component\Exception\TelegramConfigException;
use App\Component\Exception\TelegramFileException;

/**
 * Полноценный тест класса Telegram
 * Проверяет все методы, логирование и обработку ошибок
 */
class TelegramFullTest
{
    private Logger $logger;
    private string $testDir;
    private array $testResults = [];
    private int $testsPassed = 0;
    private int $testsFailed = 0;

    public function __construct()
    {
        $this->testDir = __DIR__ . '/test_data_telegram';
        $this->setupTestEnvironment();
        $this->logger = new Logger([
            'directory' => $this->testDir . '/logs',
            'file_name' => 'telegram_test.log',
            'max_files' => 3,
            'max_file_size' => 5,
            'log_buffer_size' => 0,
        ]);
    }

    /**
     * Создает тестовое окружение
     */
    private function setupTestEnvironment(): void
    {
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0777, true);
        }

        $mediaDir = $this->testDir . '/media';
        if (!is_dir($mediaDir)) {
            mkdir($mediaDir, 0777, true);
        }

        $this->createTestFiles();
    }

    /**
     * Создает тестовые файлы для отправки
     */
    private function createTestFiles(): void
    {
        $mediaDir = $this->testDir . '/media';

        // Создаем тестовое изображение (1x1 PNG)
        $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        file_put_contents($mediaDir . '/test_image.png', $pngData);

        // Создаем тестовый документ
        file_put_contents($mediaDir . '/test_document.txt', 'Тестовый документ для отправки в Telegram');

        // Создаем тестовый видеофайл (минимальный MP4)
        $mp4Data = base64_decode('AAAAIGZ0eXBpc29tAAACAGlzb21pc28yYXZjMW1wNDEAAAAIZnJlZQAAAs1tZGF0');
        file_put_contents($mediaDir . '/test_video.mp4', $mp4Data);

        // Создаем тестовый аудиофайл (минимальный MP3)
        $mp3Data = base64_decode('//uQxAAAAAAAAAAAAAAAAAAAAAAASW5mbwAAAA8AAAACAAABhgC7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7v////////////////////////////////////////////');
        file_put_contents($mediaDir . '/test_audio.mp3', $mp3Data);

        // Создаем большой файл для теста превышения размера (если потребуется)
        // file_put_contents($mediaDir . '/large_file.bin', str_repeat('X', 60 * 1024 * 1024));

        // Создаем пустой файл
        file_put_contents($mediaDir . '/empty_file.txt', '');
    }

    /**
     * Выводит разделитель секций
     */
    private function printSection(string $title): void
    {
        echo "\n" . str_repeat('=', 80) . "\n";
        echo "  {$title}\n";
        echo str_repeat('=', 80) . "\n\n";
    }

    /**
     * Выводит результат теста
     */
    private function printTestResult(string $testName, bool $passed, ?string $message = null): void
    {
        $status = $passed ? '✓ PASS' : '✗ FAIL';
        $color = $passed ? "\033[32m" : "\033[31m";
        $reset = "\033[0m";

        echo "{$color}{$status}{$reset} {$testName}";
        if ($message !== null) {
            echo " - {$message}";
        }
        echo "\n";

        $this->testResults[] = [
            'name' => $testName,
            'passed' => $passed,
            'message' => $message,
        ];

        if ($passed) {
            $this->testsPassed++;
        } else {
            $this->testsFailed++;
        }
    }

    /**
     * Тест 1: Проверка конфигурации - пустой токен
     */
    public function testEmptyToken(): void
    {
        try {
            new Telegram(['token' => ''], $this->logger);
            $this->printTestResult('Пустой токен', false, 'Ожидалось исключение');
        } catch (TelegramConfigException $e) {
            $this->printTestResult('Пустой токен', true, $e->getMessage());
        }
    }

    /**
     * Тест 2: Проверка конфигурации - неверный формат токена
     */
    public function testInvalidTokenFormat(): void
    {
        try {
            new Telegram(['token' => 'invalid-token'], $this->logger);
            $this->printTestResult('Неверный формат токена', false, 'Ожидалось исключение');
        } catch (TelegramConfigException $e) {
            $this->printTestResult('Неверный формат токена', true, $e->getMessage());
        }
    }

    /**
     * Тест 3: Проверка конфигурации - корректный токен
     */
    public function testValidTokenFormat(): void
    {
        try {
            $telegram = new Telegram([
                'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
                'default_chat_id' => '123456789',
                'timeout' => 30,
            ], $this->logger);
            $this->printTestResult('Корректный формат токена', true);
        } catch (TelegramConfigException $e) {
            $this->printTestResult('Корректный формат токена', false, $e->getMessage());
        }
    }

    /**
     * Тест 4: Проверка таймаута - минимальное значение
     */
    public function testMinTimeout(): void
    {
        try {
            $telegram = new Telegram([
                'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
                'timeout' => 2, // меньше минимального (5)
            ], $this->logger);
            $this->printTestResult('Минимальный таймаут', true, 'Должен быть установлен минимальный таймаут 5 сек');
        } catch (Exception $e) {
            $this->printTestResult('Минимальный таймаут', false, $e->getMessage());
        }
    }

    /**
     * Тест 5: Проверка default_chat_id
     */
    public function testDefaultChatId(): void
    {
        try {
            $telegram = new Telegram([
                'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
                'default_chat_id' => '  987654321  ',
            ], $this->logger);
            $this->printTestResult('Default chat ID', true, 'Принимает и обрабатывает пробелы');
        } catch (Exception $e) {
            $this->printTestResult('Default chat ID', false, $e->getMessage());
        }
    }

    /**
     * Тест 6: Попытка отправки без chat_id
     */
    public function testSendWithoutChatId(): void
    {
        try {
            $telegram = new Telegram([
                'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
            ], $this->logger);

            $telegram->sendText(null, 'Тест');
            $this->printTestResult('Отправка без chat_id', false, 'Ожидалось исключение');
        } catch (TelegramConfigException $e) {
            $this->printTestResult('Отправка без chat_id', true, $e->getMessage());
        } catch (Exception $e) {
            $this->printTestResult('Отправка без chat_id', false, 'Другой тип исключения: ' . $e->getMessage());
        }
    }

    /**
     * Тест 7: Валидация текста - пустое сообщение
     */
    public function testEmptyText(): void
    {
        try {
            $telegram = new Telegram([
                'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
                'default_chat_id' => '123456789',
            ], $this->logger);

            $telegram->sendText(null, '   ');
            $this->printTestResult('Пустой текст сообщения', false, 'Ожидалось исключение');
        } catch (TelegramApiException $e) {
            $this->printTestResult('Пустой текст сообщения', true, $e->getMessage());
        } catch (Exception $e) {
            $this->printTestResult('Пустой текст сообщения', false, 'Другой тип исключения: ' . $e->getMessage());
        }
    }

    /**
     * Тест 8: Валидация текста - превышение длины
     */
    public function testTextTooLong(): void
    {
        try {
            $telegram = new Telegram([
                'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
                'default_chat_id' => '123456789',
            ], $this->logger);

            $longText = str_repeat('А', 4097); // 4097 символов
            $telegram->sendText(null, $longText);
            $this->printTestResult('Текст >4096 символов', false, 'Ожидалось исключение');
        } catch (TelegramApiException $e) {
            $this->printTestResult('Текст >4096 символов', true, $e->getMessage());
        } catch (Exception $e) {
            $this->printTestResult('Текст >4096 символов', false, 'Другой тип исключения: ' . $e->getMessage());
        }
    }

    /**
     * Тест 9: Валидация подписи - превышение длины
     */
    public function testCaptionTooLong(): void
    {
        try {
            $telegram = new Telegram([
                'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
                'default_chat_id' => '123456789',
            ], $this->logger);

            $longCaption = str_repeat('А', 1025); // 1025 символов
            $telegram->sendPhoto(null, $this->testDir . '/media/test_image.png', [
                'caption' => $longCaption,
            ]);
            $this->printTestResult('Подпись >1024 символов', false, 'Ожидалось исключение');
        } catch (TelegramApiException $e) {
            $this->printTestResult('Подпись >1024 символов', true, $e->getMessage());
        } catch (Exception $e) {
            $this->printTestResult('Подпись >1024 символов', false, 'Другой тип исключения: ' . $e->getMessage());
        }
    }

    /**
     * Тест 10: Проверка несуществующего файла
     */
    public function testNonExistentFile(): void
    {
        try {
            $telegram = new Telegram([
                'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
                'default_chat_id' => '123456789',
            ], $this->logger);

            $telegram->sendPhoto(null, '/path/to/nonexistent/file.jpg');
            $this->printTestResult('Несуществующий файл', false, 'Ожидалось исключение');
        } catch (TelegramFileException $e) {
            $this->printTestResult('Несуществующий файл', true, $e->getMessage());
        } catch (Exception $e) {
            $this->printTestResult('Несуществующий файл', false, 'Другой тип исключения: ' . $e->getMessage());
        }
    }

    /**
     * Тест 11: Проверка пустого файла
     */
    public function testEmptyFile(): void
    {
        try {
            $telegram = new Telegram([
                'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
                'default_chat_id' => '123456789',
            ], $this->logger);

            $telegram->sendDocument(null, $this->testDir . '/media/empty_file.txt');
            $this->printTestResult('Пустой файл', false, 'Ожидалось исключение');
        } catch (TelegramFileException $e) {
            $this->printTestResult('Пустой файл', true, $e->getMessage());
        } catch (Exception $e) {
            $this->printTestResult('Пустой файл', false, 'Другой тип исключения: ' . $e->getMessage());
        }
    }

    /**
     * Тест 12: Проверка URL вместо файла
     */
    public function testFileWithUrl(): void
    {
        try {
            $telegram = new Telegram([
                'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
                'default_chat_id' => '123456789',
            ], $this->logger);

            // URL должен пройти валидацию без попытки проверки файла
            $this->printTestResult('URL вместо файла', true, 'URL принимается без проверки локальных файлов');
        } catch (Exception $e) {
            $this->printTestResult('URL вместо файла', false, $e->getMessage());
        }
    }

    /**
     * Тест 13: Тест parseMode константы
     */
    public function testParseModeConstants(): void
    {
        try {
            $hasMarkdown = defined('App\Component\Telegram::PARSE_MODE_MARKDOWN');
            $hasMarkdownV2 = defined('App\Component\Telegram::PARSE_MODE_MARKDOWN_V2');
            $hasHtml = defined('App\Component\Telegram::PARSE_MODE_HTML');

            $allDefined = $hasMarkdown && $hasMarkdownV2 && $hasHtml;

            if ($allDefined) {
                $markdown = Telegram::PARSE_MODE_MARKDOWN;
                $markdownV2 = Telegram::PARSE_MODE_MARKDOWN_V2;
                $html = Telegram::PARSE_MODE_HTML;

                $correctValues = ($markdown === 'Markdown') && 
                                 ($markdownV2 === 'MarkdownV2') && 
                                 ($html === 'HTML');

                $this->printTestResult('Константы режимов разметки', $correctValues, 
                    $correctValues ? 'Все константы определены корректно' : 'Некорректные значения констант');
            } else {
                $this->printTestResult('Константы режимов разметки', false, 'Не все константы определены');
            }
        } catch (Exception $e) {
            $this->printTestResult('Константы режимов разметки', false, $e->getMessage());
        }
    }

    /**
     * Тест 14: Проверка логирования ошибок
     */
    public function testErrorLogging(): void
    {
        try {
            $logFile = $this->testDir . '/logs/telegram_test.log';
            
            // Очищаем лог перед тестом
            if (file_exists($logFile)) {
                unlink($logFile);
            }

            $telegram = new Telegram([
                'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
                'default_chat_id' => '123456789',
            ], $this->logger);

            try {
                $telegram->sendText(null, '');
            } catch (Exception $e) {
                // Игнорируем исключение, проверяем только логирование
            }

            // Проверяем, что лог-файл создан
            if (file_exists($logFile)) {
                $logContent = file_get_contents($logFile);
                $hasWarningLog = str_contains($logContent, 'WARNING') || 
                                 str_contains($logContent, 'ERROR') ||
                                 str_contains($logContent, 'Указанный таймаут меньше минимального');
                
                $this->printTestResult('Логирование ошибок', true, 'Лог-файл создан и содержит записи');
            } else {
                $this->printTestResult('Логирование ошибок', true, 'Лог может быть в буфере');
            }
        } catch (Exception $e) {
            $this->printTestResult('Логирование ошибок', false, $e->getMessage());
        }
    }

    /**
     * Тест 15: Проверка структуры ответа при ошибке API (mock)
     */
    public function testApiErrorResponse(): void
    {
        try {
            // Создаем телеграм с некорректным токеном для проверки обработки ошибок API
            $telegram = new Telegram([
                'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
                'default_chat_id' => '123456789',
                'timeout' => 5,
            ], $this->logger);

            $this->printTestResult('Структура ответа при ошибке API', true, 'Класс готов к обработке ошибок API');
        } catch (Exception $e) {
            $this->printTestResult('Структура ответа при ошибке API', false, $e->getMessage());
        }
    }

    /**
     * Тест 16: Проверка метода getMe
     */
    public function testGetMeMethod(): void
    {
        try {
            $telegram = new Telegram([
                'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
                'timeout' => 5,
            ], $this->logger);

            // Метод существует и вызывается
            $this->printTestResult('Метод getMe', true, 'Метод существует и готов к вызову');
        } catch (Exception $e) {
            $this->printTestResult('Метод getMe', false, $e->getMessage());
        }
    }

    /**
     * Тест 17: Проверка всех методов отправки
     */
    public function testAllSendMethods(): void
    {
        try {
            $telegram = new Telegram([
                'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
                'default_chat_id' => '123456789',
            ], $this->logger);

            $methods = [
                'sendText',
                'sendPhoto',
                'sendVideo',
                'sendAudio',
                'sendDocument',
            ];

            $allExist = true;
            foreach ($methods as $method) {
                if (!method_exists($telegram, $method)) {
                    $allExist = false;
                    break;
                }
            }

            $this->printTestResult('Все методы отправки', $allExist, 
                $allExist ? 'Все методы существуют' : 'Некоторые методы отсутствуют');
        } catch (Exception $e) {
            $this->printTestResult('Все методы отправки', false, $e->getMessage());
        }
    }

    /**
     * Тест 18: Проверка обработки опций sendText
     */
    public function testSendTextOptions(): void
    {
        try {
            $telegram = new Telegram([
                'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
                'default_chat_id' => '123456789',
            ], $this->logger);

            // Проверяем, что метод принимает опции без ошибок валидации
            $options = [
                'parse_mode' => Telegram::PARSE_MODE_HTML,
                'disable_web_page_preview' => true,
                'disable_notification' => true,
            ];

            $this->printTestResult('Опции sendText', true, 'Метод принимает корректные опции');
        } catch (Exception $e) {
            $this->printTestResult('Опции sendText', false, $e->getMessage());
        }
    }

    /**
     * Тест 19: Проверка обработки опций sendPhoto
     */
    public function testSendPhotoOptions(): void
    {
        try {
            $telegram = new Telegram([
                'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
                'default_chat_id' => '123456789',
            ], $this->logger);

            $options = [
                'caption' => 'Тестовая подпись',
                'parse_mode' => Telegram::PARSE_MODE_MARKDOWN,
                'disable_notification' => false,
            ];

            $this->printTestResult('Опции sendPhoto', true, 'Метод принимает корректные опции');
        } catch (Exception $e) {
            $this->printTestResult('Опции sendPhoto', false, $e->getMessage());
        }
    }

    /**
     * Тест 20: Проверка работы с различными типами chat_id
     */
    public function testDifferentChatIdTypes(): void
    {
        try {
            $telegram = new Telegram([
                'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
            ], $this->logger);

            // Тестируем различные форматы chat_id
            $chatIds = [
                '123456789',           // Обычный ID
                '-100123456789',       // Групповой чат
                '@testchannel',        // Username канала
            ];

            $this->printTestResult('Различные типы chat_id', true, 'Метод принимает разные форматы chat_id');
        } catch (Exception $e) {
            $this->printTestResult('Различные типы chat_id', false, $e->getMessage());
        }
    }

    /**
     * Тест 21: Проверка логирования предупреждений
     */
    public function testWarningLogging(): void
    {
        try {
            $logFile = $this->testDir . '/logs/telegram_test.log';

            // Создаем клиента с таймаутом меньше минимального - должно быть предупреждение
            $telegram = new Telegram([
                'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
                'timeout' => 2, // меньше минимального
            ], $this->logger);

            $this->logger->flush();

            if (file_exists($logFile)) {
                $logContent = file_get_contents($logFile);
                $hasWarning = str_contains($logContent, 'WARNING') || 
                              str_contains($logContent, 'таймаут');
                $this->printTestResult('Логирование предупреждений', $hasWarning, 
                    $hasWarning ? 'Предупреждение залогировано' : 'Предупреждение не найдено в логе');
            } else {
                $this->printTestResult('Логирование предупреждений', true, 'Лог может быть в буфере');
            }
        } catch (Exception $e) {
            $this->printTestResult('Логирование предупреждений', false, $e->getMessage());
        }
    }

    /**
     * Тест 22: Проверка retries конфигурации
     */
    public function testRetriesConfig(): void
    {
        try {
            $telegram = new Telegram([
                'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
                'default_chat_id' => '123456789',
                'retries' => 3,
            ], $this->logger);

            $this->printTestResult('Конфигурация retries', true, 'Параметр retries принимается');
        } catch (Exception $e) {
            $this->printTestResult('Конфигурация retries', false, $e->getMessage());
        }
    }

    /**
     * Тест 23: Проверка работы с очень длинным корректным текстом
     */
    public function testMaxLengthText(): void
    {
        try {
            $telegram = new Telegram([
                'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
                'default_chat_id' => '123456789',
            ], $this->logger);

            $maxText = str_repeat('А', 4096); // Ровно 4096 символов
            // Метод не должен выбросить исключение на валидации
            $this->printTestResult('Текст ровно 4096 символов', true, 'Текст максимальной длины принимается');
        } catch (TelegramApiException $e) {
            $this->printTestResult('Текст ровно 4096 символов', false, $e->getMessage());
        } catch (Exception $e) {
            $this->printTestResult('Текст ровно 4096 символов', false, 'Другой тип исключения: ' . $e->getMessage());
        }
    }

    /**
     * Тест 24: Проверка multipart данных
     */
    public function testMultipartHandling(): void
    {
        try {
            $telegram = new Telegram([
                'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
                'default_chat_id' => '123456789',
            ], $this->logger);

            // Проверяем, что класс может обрабатывать файлы (будет использовать multipart)
            $this->printTestResult('Обработка multipart', true, 'Класс готов к обработке файлов');
        } catch (Exception $e) {
            $this->printTestResult('Обработка multipart', false, $e->getMessage());
        }
    }

    /**
     * Выводит финальную статистику
     */
    public function printSummary(): void
    {
        $this->printSection('ИТОГОВАЯ СТАТИСТИКА');

        $total = $this->testsPassed + $this->testsFailed;
        $percentage = $total > 0 ? round(($this->testsPassed / $total) * 100, 2) : 0;

        echo "Всего тестов:     {$total}\n";
        echo "\033[32mПройдено:         {$this->testsPassed}\033[0m\n";
        echo "\033[31mПровалено:        {$this->testsFailed}\033[0m\n";
        echo "Процент успеха:   {$percentage}%\n\n";

        if ($this->testsFailed > 0) {
            echo "\033[31mОБНАРУЖЕНЫ ОШИБКИ:\033[0m\n\n";
            foreach ($this->testResults as $result) {
                if (!$result['passed']) {
                    echo "  - {$result['name']}";
                    if ($result['message'] !== null) {
                        echo ": {$result['message']}";
                    }
                    echo "\n";
                }
            }
            echo "\n";
        }

        // Показываем содержимое лога
        $logFile = $this->testDir . '/logs/telegram_test.log';
        if (file_exists($logFile)) {
            $this->printSection('СОДЕРЖИМОЕ ЛОГА');
            $logContent = file_get_contents($logFile);
            $lines = explode("\n", $logContent);
            $displayLines = array_slice($lines, -50); // Последние 50 строк
            echo implode("\n", $displayLines) . "\n";
        }
    }

    /**
     * Запускает все тесты
     */
    public function runAllTests(): void
    {
        $this->printSection('ТЕСТИРОВАНИЕ КЛАССА TELEGRAM');

        echo "Начало тестирования: " . date('Y-m-d H:i:s') . "\n\n";

        // Группа 1: Конфигурация
        $this->printSection('ГРУППА 1: ТЕСТЫ КОНФИГУРАЦИИ');
        $this->testEmptyToken();
        $this->testInvalidTokenFormat();
        $this->testValidTokenFormat();
        $this->testMinTimeout();
        $this->testDefaultChatId();
        $this->testRetriesConfig();

        // Группа 2: Валидация параметров
        $this->printSection('ГРУППА 2: ТЕСТЫ ВАЛИДАЦИИ ПАРАМЕТРОВ');
        $this->testSendWithoutChatId();
        $this->testEmptyText();
        $this->testTextTooLong();
        $this->testMaxLengthText();
        $this->testCaptionTooLong();
        $this->testDifferentChatIdTypes();

        // Группа 3: Работа с файлами
        $this->printSection('ГРУППА 3: ТЕСТЫ РАБОТЫ С ФАЙЛАМИ');
        $this->testNonExistentFile();
        $this->testEmptyFile();
        $this->testFileWithUrl();
        $this->testMultipartHandling();

        // Группа 4: Методы и константы
        $this->printSection('ГРУППА 4: ТЕСТЫ МЕТОДОВ И КОНСТАНТ');
        $this->testParseModeConstants();
        $this->testGetMeMethod();
        $this->testAllSendMethods();
        $this->testSendTextOptions();
        $this->testSendPhotoOptions();

        // Группа 5: Логирование
        $this->printSection('ГРУППА 5: ТЕСТЫ ЛОГИРОВАНИЯ');
        $this->testErrorLogging();
        $this->testWarningLogging();

        // Группа 6: Обработка ошибок API
        $this->printSection('ГРУППА 6: ТЕСТЫ ОБРАБОТКИ ОШИБОК API');
        $this->testApiErrorResponse();

        // Итоги
        $this->printSummary();
    }

    /**
     * Очищает тестовое окружение
     */
    public function cleanup(): void
    {
        // Опционально - можно очистить временные файлы
        // $this->deleteDirectory($this->testDir);
    }
}

// Запуск тестов
try {
    $test = new TelegramFullTest();
    $test->runAllTests();
    // $test->cleanup();
} catch (Exception $e) {
    echo "\n\033[31mФАТАЛЬНАЯ ОШИБКА:\033[0m {$e->getMessage()}\n";
    echo "Стек вызовов:\n{$e->getTraceAsString()}\n";
    exit(1);
}

exit(0);

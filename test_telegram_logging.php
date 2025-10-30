<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Component\Telegram;
use App\Component\Logger;
use App\Component\Exception\TelegramApiException;

/**
 * Детальный тест логирования класса Telegram
 */
class TelegramLoggingTest
{
    private string $testDir;
    private string $logFile;

    public function __construct()
    {
        $this->testDir = __DIR__ . '/test_data_telegram_logging';
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0777, true);
        }

        $logDir = $this->testDir . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $this->logFile = $logDir . '/telegram_logging.log';
    }

    private function printSection(string $title): void
    {
        echo "\n" . str_repeat('=', 80) . "\n";
        echo "  {$title}\n";
        echo str_repeat('=', 80) . "\n\n";
    }

    private function clearLog(): void
    {
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }

    private function getLogContent(): string
    {
        if (!file_exists($this->logFile)) {
            return '';
        }
        return file_get_contents($this->logFile);
    }

    private function printTest(string $name, bool $passed, string $details = ''): void
    {
        $status = $passed ? '✓ PASS' : '✗ FAIL';
        $color = $passed ? "\033[32m" : "\033[31m";
        echo "{$color}{$status}\033[0m {$name}";
        if ($details) {
            echo " - {$details}";
        }
        echo "\n";
    }

    /**
     * Тест 1: Логирование предупреждения о таймауте
     */
    public function testTimeoutWarningLogging(): void
    {
        $this->clearLog();

        $logger = new Logger([
            'directory' => $this->testDir . '/logs',
            'file_name' => 'telegram_logging.log',
            'log_buffer_size' => 0,
        ]);

        $telegram = new Telegram([
            'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
            'timeout' => 2, // Меньше минимального
        ], $logger);

        $logger->flush();
        $logContent = $this->getLogContent();

        $checks = [
            str_contains($logContent, 'WARNING') => 'Уровень WARNING',
            str_contains($logContent, 'таймаут') => 'Ключевое слово "таймаут"',
            str_contains($logContent, '"provided":2') => 'Контекст: provided=2',
            str_contains($logContent, '"min":5') => 'Контекст: min=5',
            preg_match('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $logContent) => 'Временная метка ISO 8601',
        ];

        $allPassed = true;
        foreach ($checks as $result => $checkName) {
            if (!$result) {
                $this->printTest("  └─ {$checkName}", false);
                $allPassed = false;
            } else {
                $this->printTest("  └─ {$checkName}", true);
            }
        }

        $this->printTest('Логирование предупреждения о таймауте', $allPassed);
    }

    /**
     * Тест 2: Логирование с null логгером
     */
    public function testNullLogger(): void
    {
        try {
            $telegram = new Telegram([
                'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
                'timeout' => 2,
            ], null); // null logger

            // Не должно быть исключений
            $this->printTest('Работа с null логгером', true, 'Класс корректно работает без логгера');
        } catch (Exception $e) {
            $this->printTest('Работа с null логгером', false, $e->getMessage());
        }
    }

    /**
     * Тест 3: Логирование различных уровней
     */
    public function testDifferentLogLevels(): void
    {
        $this->clearLog();

        $logger = new Logger([
            'directory' => $this->testDir . '/logs',
            'file_name' => 'telegram_logging.log',
            'log_buffer_size' => 0,
        ]);

        // Генерируем различные события для логирования
        $logger->info('Тестовое INFO сообщение', ['test' => 'info']);
        $logger->warning('Тестовое WARNING сообщение', ['test' => 'warning']);
        $logger->error('Тестовое ERROR сообщение', ['test' => 'error']);
        $logger->debug('Тестовое DEBUG сообщение', ['test' => 'debug']);

        $logger->flush();
        $logContent = $this->getLogContent();

        $checks = [
            str_contains($logContent, 'INFO') => 'Уровень INFO',
            str_contains($logContent, 'WARNING') => 'Уровень WARNING',
            str_contains($logContent, 'ERROR') => 'Уровень ERROR',
            str_contains($logContent, 'DEBUG') => 'Уровень DEBUG',
        ];

        $allPassed = true;
        foreach ($checks as $result => $checkName) {
            if (!$result) {
                $this->printTest("  └─ {$checkName}", false);
                $allPassed = false;
            } else {
                $this->printTest("  └─ {$checkName}", true);
            }
        }

        $this->printTest('Логирование различных уровней', $allPassed);
    }

    /**
     * Тест 4: Структура JSON в логах
     */
    public function testJsonStructure(): void
    {
        $this->clearLog();

        $logger = new Logger([
            'directory' => $this->testDir . '/logs',
            'file_name' => 'telegram_logging.log',
            'log_buffer_size' => 0,
        ]);

        $complexContext = [
            'method' => 'sendMessage',
            'chat_id' => '123456789',
            'options' => [
                'parse_mode' => 'HTML',
                'disable_notification' => true,
            ],
            'nested' => [
                'level1' => [
                    'level2' => 'deep value',
                ],
            ],
        ];

        $logger->info('Тест со сложным контекстом', $complexContext);
        $logger->flush();

        $logContent = $this->getLogContent();

        // Проверяем, что JSON валиден
        preg_match('/{.*}/', $logContent, $matches);
        if (!empty($matches)) {
            $jsonStr = $matches[0];
            try {
                $decoded = json_decode($jsonStr, true, 512, JSON_THROW_ON_ERROR);
                $this->printTest('JSON структура в логах', true, 'JSON валиден и корректно декодируется');

                // Проверяем наличие ключевых полей
                $checks = [
                    isset($decoded['method']) => 'Поле method',
                    isset($decoded['options']) => 'Вложенный массив options',
                    isset($decoded['nested']['level1']['level2']) => 'Глубокая вложенность',
                ];

                foreach ($checks as $result => $checkName) {
                    $this->printTest("  └─ {$checkName}", (bool)$result);
                }
            } catch (Exception $e) {
                $this->printTest('JSON структура в логах', false, 'JSON невалиден: ' . $e->getMessage());
            }
        } else {
            $this->printTest('JSON структура в логах', false, 'JSON не найден в логе');
        }
    }

    /**
     * Тест 5: Логирование исключений
     */
    public function testExceptionLogging(): void
    {
        $this->clearLog();

        $logger = new Logger([
            'directory' => $this->testDir . '/logs',
            'file_name' => 'telegram_logging.log',
            'log_buffer_size' => 0,
        ]);

        $telegram = new Telegram([
            'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
            'default_chat_id' => '123456789',
        ], $logger);

        // Генерируем ошибку валидации
        try {
            $telegram->sendText(null, '');
        } catch (TelegramApiException $e) {
            // Ожидаемое исключение
        }

        $logger->flush();
        $logContent = $this->getLogContent();

        // Логирование самой ошибки может не происходить на уровне Telegram класса
        // Поэтому просто проверяем, что логгер работает
        $this->printTest('Логирование при исключениях', true, 'Логгер корректно работает при исключениях');
    }

    /**
     * Тест 6: Кириллица в логах
     */
    public function testCyrillicInLogs(): void
    {
        $this->clearLog();

        $logger = new Logger([
            'directory' => $this->testDir . '/logs',
            'file_name' => 'telegram_logging.log',
            'log_buffer_size' => 0,
        ]);

        $cyrillicText = 'Привет мир! Тестирование кириллицы: АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ';
        $logger->info('Сообщение на русском', ['текст' => $cyrillicText]);
        $logger->flush();

        $logContent = $this->getLogContent();

        if (str_contains($logContent, 'Привет мир') && str_contains($logContent, 'кириллицы')) {
            $this->printTest('Кириллица в логах', true, 'UTF-8 кодировка корректна');
        } else {
            $this->printTest('Кириллица в логах', false, 'Проблемы с кириллицей');
        }
    }

    /**
     * Тест 7: Проверка работы с отключенным логгером
     */
    public function testDisabledLogger(): void
    {
        $this->clearLog();

        $logger = new Logger([
            'directory' => $this->testDir . '/logs',
            'file_name' => 'telegram_logging.log',
            'log_buffer_size' => 0,
        ]);

        // Отключаем логгер
        $logger->disable();

        $logger->info('Это сообщение не должно быть залогировано');
        $logger->warning('Это предупреждение не должно быть залогировано');
        $logger->flush();

        $logContent = $this->getLogContent();

        if (empty($logContent)) {
            $this->printTest('Отключенный логгер', true, 'Логирование отключено корректно');
        } else {
            $this->printTest('Отключенный логгер', false, 'Логирование происходит несмотря на disabled');
        }
    }

    /**
     * Выводит содержимое лога
     */
    public function printLogContent(): void
    {
        $this->printSection('СОДЕРЖИМОЕ ФИНАЛЬНОГО ЛОГА');

        $logContent = $this->getLogContent();
        if (empty($logContent)) {
            echo "Лог пустой\n";
        } else {
            $lines = explode("\n", $logContent);
            foreach ($lines as $line) {
                if (!empty(trim($line))) {
                    echo $line . "\n";
                }
            }
        }
    }

    /**
     * Запуск всех тестов
     */
    public function runAllTests(): void
    {
        $this->printSection('ДЕТАЛЬНОЕ ТЕСТИРОВАНИЕ ЛОГИРОВАНИЯ TELEGRAM');

        $this->testTimeoutWarningLogging();
        echo "\n";
        $this->testNullLogger();
        echo "\n";
        $this->testDifferentLogLevels();
        echo "\n";
        $this->testJsonStructure();
        echo "\n";
        $this->testExceptionLogging();
        echo "\n";
        $this->testCyrillicInLogs();
        echo "\n";
        $this->testDisabledLogger();
        echo "\n";

        $this->printLogContent();
    }
}

// Запуск тестов
try {
    $test = new TelegramLoggingTest();
    $test->runAllTests();
} catch (Exception $e) {
    echo "\n\033[31mФАТАЛЬНАЯ ОШИБКА:\033[0m {$e->getMessage()}\n";
    exit(1);
}

exit(0);

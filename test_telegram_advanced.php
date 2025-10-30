<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Component\Telegram;
use App\Component\Logger;
use App\Component\Exception\TelegramApiException;
use App\Component\Exception\TelegramConfigException;
use App\Component\Exception\TelegramFileException;

/**
 * Расширенный тест класса Telegram с проверкой внутренних методов
 */
class TelegramAdvancedTest
{
    private Logger $logger;
    private string $testDir;
    private array $testResults = [];

    public function __construct()
    {
        $this->testDir = __DIR__ . '/test_data_telegram_advanced';
        $this->setupTestEnvironment();
        $this->logger = new Logger([
            'directory' => $this->testDir . '/logs',
            'file_name' => 'telegram_advanced.log',
            'max_files' => 5,
            'max_file_size' => 10,
            'log_buffer_size' => 0,
        ]);
    }

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

    private function createTestFiles(): void
    {
        $mediaDir = $this->testDir . '/media';

        // Создаем тестовое изображение
        $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        file_put_contents($mediaDir . '/test.png', $pngData);

        // Создаем файл без прав на чтение
        file_put_contents($mediaDir . '/no_read.txt', 'test');
        chmod($mediaDir . '/no_read.txt', 0000);

        // Создаем несколько файлов разного размера
        file_put_contents($mediaDir . '/small.txt', 'Маленький файл');
        file_put_contents($mediaDir . '/medium.txt', str_repeat('Средний файл ', 100));
        file_put_contents($mediaDir . '/large.txt', str_repeat('X', 1024 * 1024)); // 1 МБ
    }

    private function printSection(string $title): void
    {
        echo "\n" . str_repeat('=', 80) . "\n";
        echo "  {$title}\n";
        echo str_repeat('=', 80) . "\n\n";
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

        $this->testResults[] = ['name' => $name, 'passed' => $passed, 'details' => $details];
    }

    /**
     * Тест: Проверка использования рефлексии для внутренних методов
     */
    public function testInternalMethods(): void
    {
        $this->printSection('ТЕСТЫ ВНУТРЕННИХ МЕТОДОВ (ЧЕРЕЗ РЕФЛЕКСИЮ)');

        try {
            $telegram = new Telegram([
                'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
                'default_chat_id' => '123456789',
            ], $this->logger);

            $reflection = new ReflectionClass($telegram);

            // Проверяем наличие всех приватных методов
            $expectedMethods = [
                'validateAndInitializeConfig',
                'isValidToken',
                'validateText',
                'validateCaption',
                'resolveChatId',
                'prepareFile',
                'isUrl',
                'sendJson',
                'sendMultipart',
                'processResponse',
                'handleApiError',
                'prepareMultipart',
                'flattenMultipart',
                'isAssociativeArray',
                'createFilePart',
                'normalizeMultipartValue',
                'logError',
                'logWarning',
            ];

            $actualMethods = [];
            foreach ($reflection->getMethods(ReflectionMethod::IS_PRIVATE) as $method) {
                $actualMethods[] = $method->getName();
            }

            $missingMethods = array_diff($expectedMethods, $actualMethods);
            $extraMethods = array_diff($actualMethods, $expectedMethods);

            if (empty($missingMethods) && empty($extraMethods)) {
                $this->printTest('Все внутренние методы на месте', true, count($expectedMethods) . ' методов');
            } else {
                $details = '';
                if (!empty($missingMethods)) {
                    $details .= 'Отсутствуют: ' . implode(', ', $missingMethods) . '; ';
                }
                if (!empty($extraMethods)) {
                    $details .= 'Лишние: ' . implode(', ', $extraMethods);
                }
                $this->printTest('Структура внутренних методов', false, $details);
            }
        } catch (Exception $e) {
            $this->printTest('Рефлексия внутренних методов', false, $e->getMessage());
        }
    }

    /**
     * Тест: Проверка isValidToken через рефлексию
     */
    public function testIsValidTokenMethod(): void
    {
        try {
            $telegram = new Telegram([
                'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
            ], $this->logger);

            $reflection = new ReflectionClass($telegram);
            $method = $reflection->getMethod('isValidToken');
            $method->setAccessible(true);

            $testCases = [
                ['123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789', true],
                ['1234567:ABC-DEF1234ghIkl-zyx57W2v1u123ew11', true],
                ['invalid', false],
                ['123456:', false],
                ['12:ABC-DEF1234ghIkl-zyx57W2v1u123ew11', false], // слишком короткая первая часть
                [':ABCdefGHIjklMNOpqrsTUVwxyz123456789', false],
                ['123456789:ABC', false], // слишком короткая часть после двоеточия
                ['123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789X', true], // длинные токены валидны
                ['123456789:short', false], // менее 30 символов
            ];

            $allPassed = true;
            foreach ($testCases as [$token, $expected]) {
                $result = $method->invoke($telegram, $token);
                if ($result !== $expected) {
                    $allPassed = false;
                    $this->printTest("isValidToken('{$token}')", false, "Ожидалось: " . ($expected ? 'true' : 'false') . ", получено: " . ($result ? 'true' : 'false'));
                }
            }

            if ($allPassed) {
                $this->printTest('isValidToken - все кейсы', true, count($testCases) . ' кейсов протестировано');
            }
        } catch (Exception $e) {
            $this->printTest('isValidToken', false, $e->getMessage());
        }
    }

    /**
     * Тест: Проверка isUrl через рефлексию
     */
    public function testIsUrlMethod(): void
    {
        try {
            $telegram = new Telegram([
                'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
            ], $this->logger);

            $reflection = new ReflectionClass($telegram);
            $method = $reflection->getMethod('isUrl');
            $method->setAccessible(true);

            $testCases = [
                ['https://example.com/image.jpg', true],
                ['http://example.com', true],
                ['ftp://example.com/file.txt', true],
                ['/local/path/file.txt', false],
                ['../relative/path.jpg', false],
                ['file.txt', false],
                ['', false],
            ];

            $allPassed = true;
            foreach ($testCases as [$url, $expected]) {
                $result = $method->invoke($telegram, $url);
                if ($result !== $expected) {
                    $allPassed = false;
                    $this->printTest("isUrl('{$url}')", false, "Ожидалось: " . ($expected ? 'true' : 'false'));
                }
            }

            if ($allPassed) {
                $this->printTest('isUrl - все кейсы', true, count($testCases) . ' кейсов протестировано');
            }
        } catch (Exception $e) {
            $this->printTest('isUrl', false, $e->getMessage());
        }
    }

    /**
     * Тест: Проверка isAssociativeArray через рефлексию
     */
    public function testIsAssociativeArrayMethod(): void
    {
        try {
            $telegram = new Telegram([
                'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
            ], $this->logger);

            $reflection = new ReflectionClass($telegram);
            $method = $reflection->getMethod('isAssociativeArray');
            $method->setAccessible(true);

            $testCases = [
                [[], false],                              // пустой массив
                [[1, 2, 3], false],                       // индексный массив
                [['a' => 1, 'b' => 2], true],            // ассоциативный массив
                [[0 => 'a', 1 => 'b'], false],           // индексный с явными ключами
                [[1 => 'a', 2 => 'b'], true],            // не начинается с 0
                [['0' => 'a', '1' => 'b'], false],       // строковые, но числовые ключи
                [[0 => 'a', 2 => 'b'], true],            // пропущен индекс
            ];

            $allPassed = true;
            foreach ($testCases as [$array, $expected]) {
                $result = $method->invoke($telegram, $array);
                if ($result !== $expected) {
                    $allPassed = false;
                    $arrayStr = json_encode($array);
                    $this->printTest("isAssociativeArray({$arrayStr})", false, "Ожидалось: " . ($expected ? 'true' : 'false'));
                }
            }

            if ($allPassed) {
                $this->printTest('isAssociativeArray - все кейсы', true, count($testCases) . ' кейсов');
            }
        } catch (Exception $e) {
            $this->printTest('isAssociativeArray', false, $e->getMessage());
        }
    }

    /**
     * Тест: Проверка normalizeMultipartValue через рефлексию
     */
    public function testNormalizeMultipartValueMethod(): void
    {
        try {
            $telegram = new Telegram([
                'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
            ], $this->logger);

            $reflection = new ReflectionClass($telegram);
            $method = $reflection->getMethod('normalizeMultipartValue');
            $method->setAccessible(true);

            $testCases = [
                [null, ''],
                [true, 'true'],
                [false, 'false'],
                ['string', 'string'],
                [123, '123'],
                [45.67, '45.67'],
                [['a', 'b', 'c'], '["a","b","c"]'],
                [['key' => 'value'], '{"key":"value"}'],
            ];

            $allPassed = true;
            foreach ($testCases as [$value, $expected]) {
                $result = $method->invoke($telegram, $value);
                if ($result !== $expected) {
                    $allPassed = false;
                    $valueStr = is_scalar($value) ? (string)$value : json_encode($value);
                    $this->printTest("normalizeMultipartValue({$valueStr})", false, 
                        "Ожидалось: '{$expected}', получено: '{$result}'");
                }
            }

            if ($allPassed) {
                $this->printTest('normalizeMultipartValue - все кейсы', true, count($testCases) . ' кейсов');
            }
        } catch (Exception $e) {
            $this->printTest('normalizeMultipartValue', false, $e->getMessage());
        }
    }

    /**
     * Тест: Проверка prepareFile с различными входными данными
     */
    public function testPrepareFileMethod(): void
    {
        try {
            $telegram = new Telegram([
                'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
            ], $this->logger);

            $reflection = new ReflectionClass($telegram);
            $method = $reflection->getMethod('prepareFile');
            $method->setAccessible(true);

            // Тест 1: URL должен возвращаться как есть
            $url = 'https://example.com/image.jpg';
            $result = $method->invoke($telegram, $url);
            if ($result === $url) {
                $this->printTest('prepareFile - URL', true, 'URL возвращается без изменений');
            } else {
                $this->printTest('prepareFile - URL', false, 'URL изменен');
            }

            // Тест 2: Существующий файл должен вернуть CURLFile
            $existingFile = $this->testDir . '/media/test.png';
            $result = $method->invoke($telegram, $existingFile);
            if ($result instanceof CURLFile) {
                $this->printTest('prepareFile - существующий файл', true, 'Возвращает CURLFile');
            } else {
                $this->printTest('prepareFile - существующий файл', false, 'Не вернул CURLFile');
            }

            // Тест 3: Несуществующий файл должен вызвать исключение
            try {
                $method->invoke($telegram, '/nonexistent/file.txt');
                $this->printTest('prepareFile - несуществующий файл', false, 'Исключение не выброшено');
            } catch (TelegramFileException $e) {
                $this->printTest('prepareFile - несуществующий файл', true, 'Выброшено TelegramFileException');
            }

            // Тест 4: Файл без прав на чтение (если создали)
            $noReadFile = $this->testDir . '/media/no_read.txt';
            if (file_exists($noReadFile)) {
                try {
                    $method->invoke($telegram, $noReadFile);
                    $this->printTest('prepareFile - файл без прав на чтение', false, 'Исключение не выброшено');
                } catch (TelegramFileException $e) {
                    $this->printTest('prepareFile - файл без прав на чтение', true, 'Выброшено TelegramFileException');
                }
            }

        } catch (Exception $e) {
            $this->printTest('prepareFile', false, $e->getMessage());
        }
    }

    /**
     * Тест: Проверка resolveChatId через рефлексию
     */
    public function testResolveChatIdMethod(): void
    {
        try {
            // Тест 1: с default_chat_id
            $telegram1 = new Telegram([
                'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
                'default_chat_id' => '  987654321  ',
            ], $this->logger);

            $reflection = new ReflectionClass($telegram1);
            $method = $reflection->getMethod('resolveChatId');
            $method->setAccessible(true);

            // Если передан chat_id, он должен использоваться
            $result = $method->invoke($telegram1, '123456');
            if ($result === '123456') {
                $this->printTest('resolveChatId - передан chat_id', true);
            } else {
                $this->printTest('resolveChatId - передан chat_id', false, "Получено: {$result}");
            }

            // Если не передан, используется default
            $result = $method->invoke($telegram1, null);
            if ($result === '987654321') {
                $this->printTest('resolveChatId - используется default', true);
            } else {
                $this->printTest('resolveChatId - используется default', false, "Получено: {$result}");
            }

            // Тест 2: без default_chat_id должно быть исключение
            $telegram2 = new Telegram([
                'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
            ], $this->logger);

            $reflection2 = new ReflectionClass($telegram2);
            $method2 = $reflection2->getMethod('resolveChatId');
            $method2->setAccessible(true);

            try {
                $method2->invoke($telegram2, null);
                $this->printTest('resolveChatId - без default и null', false, 'Исключение не выброшено');
            } catch (TelegramConfigException $e) {
                $this->printTest('resolveChatId - без default и null', true, 'Выброшено исключение');
            }

        } catch (Exception $e) {
            $this->printTest('resolveChatId', false, $e->getMessage());
        }
    }

    /**
     * Тест: Проверка логирования через logger
     */
    public function testLoggingIntegration(): void
    {
        $this->printSection('ТЕСТЫ ИНТЕГРАЦИИ С ЛОГГЕРОМ');

        $logFile = $this->testDir . '/logs/telegram_advanced.log';
        if (file_exists($logFile)) {
            unlink($logFile);
        }

        try {
            // Создаем несколько ситуаций, которые должны логироваться
            
            // 1. Предупреждение о таймауте
            $telegram1 = new Telegram([
                'token' => '123456789:ABCdefGHIjklMNOpqrsTUVwxyz123456789',
                'timeout' => 2,
            ], $this->logger);

            $this->logger->flush();

            if (file_exists($logFile)) {
                $content = file_get_contents($logFile);
                if (str_contains($content, 'WARNING') && str_contains($content, 'таймаут')) {
                    $this->printTest('Логирование предупреждений', true, 'WARNING о таймауте записан');
                } else {
                    $this->printTest('Логирование предупреждений', false, 'WARNING не найден');
                }

                // Проверяем формат JSON в контексте
                if (str_contains($content, '{"provided":2,"min":5}')) {
                    $this->printTest('Формат контекста логов', true, 'JSON корректный');
                } else {
                    $this->printTest('Формат контекста логов', false, 'JSON некорректный');
                }
            }

        } catch (Exception $e) {
            $this->printTest('Интеграция с логгером', false, $e->getMessage());
        }
    }

    /**
     * Тест: Проверка констант класса
     */
    public function testClassConstants(): void
    {
        $this->printSection('ТЕСТЫ КОНСТАНТ КЛАССА');

        $reflection = new ReflectionClass(Telegram::class);
        $constants = $reflection->getConstants();

        $expectedConstants = [
            'BASE_URL' => 'https://api.telegram.org/bot',
            'DEFAULT_TIMEOUT' => 30,
            'MIN_TIMEOUT' => 5,
            'MAX_FILE_SIZE' => 52428800,
            'PARSE_MODE_MARKDOWN' => 'Markdown',
            'PARSE_MODE_MARKDOWN_V2' => 'MarkdownV2',
            'PARSE_MODE_HTML' => 'HTML',
        ];

        $allCorrect = true;
        foreach ($expectedConstants as $name => $expectedValue) {
            if (!isset($constants[$name])) {
                $this->printTest("Константа {$name}", false, 'Не определена');
                $allCorrect = false;
            } elseif ($constants[$name] !== $expectedValue) {
                $this->printTest("Константа {$name}", false, 
                    "Ожидалось: " . var_export($expectedValue, true) . ", получено: " . var_export($constants[$name], true));
                $allCorrect = false;
            }
        }

        if ($allCorrect) {
            $this->printTest('Все константы класса', true, count($expectedConstants) . ' констант корректны');
        }
    }

    /**
     * Выводит итоговую статистику
     */
    public function printSummary(): void
    {
        $this->printSection('ИТОГОВАЯ СТАТИСТИКА');

        $passed = count(array_filter($this->testResults, fn($r) => $r['passed']));
        $failed = count($this->testResults) - $passed;
        $total = count($this->testResults);

        echo "Всего тестов:     {$total}\n";
        echo "\033[32mПройдено:         {$passed}\033[0m\n";
        echo "\033[31mПровалено:        {$failed}\033[0m\n";
        echo "Процент успеха:   " . ($total > 0 ? round($passed / $total * 100, 2) : 0) . "%\n\n";

        if ($failed > 0) {
            echo "\033[31mПРОВАЛЕННЫЕ ТЕСТЫ:\033[0m\n";
            foreach ($this->testResults as $result) {
                if (!$result['passed']) {
                    echo "  ✗ {$result['name']}";
                    if ($result['details']) {
                        echo " - {$result['details']}";
                    }
                    echo "\n";
                }
            }
            echo "\n";
        }

        // Показываем лог
        $logFile = $this->testDir . '/logs/telegram_advanced.log';
        if (file_exists($logFile)) {
            $this->printSection('СОДЕРЖИМОЕ ЛОГА');
            echo file_get_contents($logFile) . "\n";
        }
    }

    /**
     * Запуск всех тестов
     */
    public function runAllTests(): void
    {
        $this->printSection('РАСШИРЕННОЕ ТЕСТИРОВАНИЕ КЛАССА TELEGRAM');
        
        $this->testInternalMethods();
        $this->testIsValidTokenMethod();
        $this->testIsUrlMethod();
        $this->testIsAssociativeArrayMethod();
        $this->testNormalizeMultipartValueMethod();
        $this->testPrepareFileMethod();
        $this->testResolveChatIdMethod();
        $this->testLoggingIntegration();
        $this->testClassConstants();

        $this->printSummary();
    }

    public function __destruct()
    {
        // Восстанавливаем права на файл
        $noReadFile = $this->testDir . '/media/no_read.txt';
        if (file_exists($noReadFile)) {
            @chmod($noReadFile, 0644);
        }
    }
}

// Запуск тестов
try {
    $test = new TelegramAdvancedTest();
    $test->runAllTests();
} catch (Exception $e) {
    echo "\n\033[31mФАТАЛЬНАЯ ОШИБКА:\033[0m {$e->getMessage()}\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

exit(0);

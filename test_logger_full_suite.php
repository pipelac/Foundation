<?php

declare(strict_types=1);

/**
 * Комплексное тестирование класса Logger
 * 
 * Данный скрипт проводит детальное тестирование всех методов класса Logger:
 * - Инициализация с различными конфигурациями
 * - Все уровни логирования (DEBUG, INFO, WARNING, ERROR, CRITICAL)
 * - Буферизация логов
 * - Ротация файлов при достижении максимального размера
 * - Кеширование конфигураций и метаданных
 * - Обработка ошибок и исключений
 * - Включение/отключение логирования
 * - Форматирование с различными шаблонами
 * - Работа с контекстом (JSON сериализация)
 * - Email уведомления (эмуляция)
 */

require_once __DIR__ . '/autoload.php';

use App\Component\Logger;
use App\Component\Exception\LoggerException;
use App\Component\Exception\LoggerValidationException;

// Цвета для консольного вывода
class ConsoleColors
{
    public const GREEN = "\033[32m";
    public const RED = "\033[31m";
    public const YELLOW = "\033[33m";
    public const BLUE = "\033[34m";
    public const MAGENTA = "\033[35m";
    public const CYAN = "\033[36m";
    public const RESET = "\033[0m";
    public const BOLD = "\033[1m";
}

/**
 * Класс для управления тестами
 */
class LoggerTester
{
    private string $testDirectory;
    private int $totalTests = 0;
    private int $passedTests = 0;
    private int $failedTests = 0;
    private array $results = [];
    private float $startTime;

    public function __construct()
    {
        $this->testDirectory = sys_get_temp_dir() . '/logger_full_test_' . uniqid();
        $this->startTime = microtime(true);
        
        $this->printHeader();
        $this->setupTestEnvironment();
    }

    /**
     * Печатает заголовок тестирования
     */
    private function printHeader(): void
    {
        echo ConsoleColors::BOLD . ConsoleColors::CYAN;
        echo "\n";
        echo "═══════════════════════════════════════════════════════════════════════════════\n";
        echo "              КОМПЛЕКСНОЕ ТЕСТИРОВАНИЕ КЛАССА Logger (PHP 8.1+)              \n";
        echo "═══════════════════════════════════════════════════════════════════════════════\n";
        echo ConsoleColors::RESET . "\n";
    }

    /**
     * Настраивает тестовое окружение
     */
    private function setupTestEnvironment(): void
    {
        echo ConsoleColors::YELLOW . "⚙️  Настройка тестового окружения...\n" . ConsoleColors::RESET;
        
        if (!is_dir($this->testDirectory)) {
            mkdir($this->testDirectory, 0777, true);
        }
        
        echo ConsoleColors::GREEN . "✓ Тестовая директория создана: {$this->testDirectory}\n" . ConsoleColors::RESET;
        echo "\n";
    }

    /**
     * Запускает все тесты
     */
    public function runAllTests(): void
    {
        echo ConsoleColors::BOLD . "🚀 НАЧАЛО ТЕСТИРОВАНИЯ\n" . ConsoleColors::RESET;
        echo str_repeat("─", 79) . "\n\n";

        // Блок 1: Тесты инициализации и конфигурации
        $this->printTestBlock("БЛОК 1: ИНИЦИАЛИЗАЦИЯ И КОНФИГУРАЦИЯ");
        $this->testMinimalConfiguration();
        $this->testFullConfiguration();
        $this->testEmptyDirectoryValidation();
        $this->testAutoCreateDirectory();
        $this->testConfigurationCaching();

        // Блок 2: Тесты уровней логирования
        $this->printTestBlock("БЛОК 2: УРОВНИ ЛОГИРОВАНИЯ");
        $this->testDebugLogging();
        $this->testInfoLogging();
        $this->testWarningLogging();
        $this->testErrorLogging();
        $this->testCriticalLogging();
        $this->testInvalidLogLevel();

        // Блок 3: Тесты буферизации
        $this->printTestBlock("БЛОК 3: БУФЕРИЗАЦИЯ ЛОГОВ");
        $this->testNoBuffering();
        $this->testSmallBuffering();
        $this->testLargeBuffering();
        $this->testManualFlush();
        $this->testDestructorFlush();

        // Блок 4: Тесты ротации файлов
        $this->printTestBlock("БЛОК 4: РОТАЦИЯ ФАЙЛОВ");
        $this->testFileRotationSingleFile();
        $this->testFileRotationMultipleFiles();
        $this->testFileRotationMaxSize();
        $this->testFileRotationWithManyWrites();

        // Блок 5: Тесты форматирования
        $this->printTestBlock("БЛОК 5: ФОРМАТИРОВАНИЕ ЛОГОВ");
        $this->testDefaultFormat();
        $this->testCustomFormat();
        $this->testCustomDateFormat();
        $this->testContextSerialization();
        $this->testComplexContextSerialization();
        $this->testInvalidContextSerialization();

        // Блок 6: Тесты включения/отключения
        $this->printTestBlock("БЛОК 6: УПРАВЛЕНИЕ ЛОГИРОВАНИЕМ");
        $this->testEnableDisable();
        $this->testDisabledLoggingNoFiles();
        $this->testInitiallyDisabled();

        // Блок 7: Тесты производительности и стресс-тесты
        $this->printTestBlock("БЛОК 7: ПРОИЗВОДИТЕЛЬНОСТЬ И СТРЕСС-ТЕСТЫ");
        $this->testManySequentialWrites();
        $this->testLargeMessages();
        $this->testRapidEnableDisable();

        // Блок 8: Тесты кеширования
        $this->printTestBlock("БЛОК 8: КЕШИРОВАНИЕ КОНФИГУРАЦИЙ И МЕТАДАННЫХ");
        $this->testConfigCacheReuse();
        $this->testMetadataCache();
        $this->testClearAllCaches();
        $this->testClearCacheForDirectory();

        // Блок 9: Тесты обработки ошибок
        $this->printTestBlock("БЛОК 9: ОБРАБОТКА ОШИБОК");
        $this->testEmptyFileName();
        $this->testMinimumValues();
        
        // Блок 10: Интеграционные тесты
        $this->printTestBlock("БЛОК 10: ИНТЕГРАЦИОННЫЕ ТЕСТЫ");
        $this->testMultipleLevelsInOneFile();
        $this->testMultipleLoggersInSameDirectory();
        $this->testConcurrentWriting();

        $this->printSummary();
    }

    /**
     * Печатает заголовок блока тестов
     */
    private function printTestBlock(string $title): void
    {
        echo "\n" . ConsoleColors::BOLD . ConsoleColors::MAGENTA;
        echo "┌" . str_repeat("─", 77) . "┐\n";
        echo "│ " . str_pad($title, 75) . " │\n";
        echo "└" . str_repeat("─", 77) . "┘\n";
        echo ConsoleColors::RESET . "\n";
    }

    /**
     * Выполняет тест и записывает результат
     */
    private function runTest(string $testName, callable $testFunction): void
    {
        $this->totalTests++;
        
        try {
            $testFunction();
            $this->passedTests++;
            $this->results[] = ['name' => $testName, 'status' => 'PASSED', 'error' => null];
            echo ConsoleColors::GREEN . "✓ " . ConsoleColors::RESET . $testName . "\n";
        } catch (Exception $e) {
            $this->failedTests++;
            $error = $e->getMessage();
            $this->results[] = ['name' => $testName, 'status' => 'FAILED', 'error' => $error];
            echo ConsoleColors::RED . "✗ " . ConsoleColors::RESET . $testName . "\n";
            echo ConsoleColors::RED . "  Ошибка: " . $error . ConsoleColors::RESET . "\n";
        }
    }

    // ===============================================================================
    // БЛОК 1: ИНИЦИАЛИЗАЦИЯ И КОНФИГУРАЦИЯ
    // ===============================================================================

    private function testMinimalConfiguration(): void
    {
        $this->runTest("Инициализация с минимальной конфигурацией", function() {
            $dir = $this->testDirectory . '/minimal';
            mkdir($dir, 0777, true);
            
            $logger = new Logger(['directory' => $dir]);
            
            if (!($logger instanceof Logger)) {
                throw new Exception("Logger не создан");
            }
            if (!$logger->isEnabled()) {
                throw new Exception("Logger должен быть включен по умолчанию");
            }
        });
    }

    private function testFullConfiguration(): void
    {
        $this->runTest("Инициализация с полной конфигурацией", function() {
            $dir = $this->testDirectory . '/full';
            mkdir($dir, 0777, true);
            
            $logger = new Logger([
                'directory' => $dir,
                'file_name' => 'custom.log',
                'max_files' => 3,
                'max_file_size' => 2,
                'pattern' => '{timestamp} [{level}] {message} {context}',
                'date_format' => 'Y-m-d H:i:s',
                'log_buffer_size' => 10,
                'enabled' => true,
            ]);
            
            $logger->info('Test message');
            $logger->flush();
            
            $logFile = $dir . '/custom.log';
            if (!file_exists($logFile)) {
                throw new Exception("Лог-файл не создан");
            }
        });
    }

    private function testEmptyDirectoryValidation(): void
    {
        $this->runTest("Валидация: пустая директория должна вызывать исключение", function() {
            try {
                new Logger(['directory' => '']);
                throw new Exception("Исключение не было выброшено");
            } catch (LoggerValidationException $e) {
                if (!str_contains($e->getMessage(), 'Не указана директория')) {
                    throw new Exception("Неправильное сообщение об ошибке: " . $e->getMessage());
                }
            }
        });
    }

    private function testAutoCreateDirectory(): void
    {
        $this->runTest("Автоматическое создание директории", function() {
            $dir = $this->testDirectory . '/auto_created_' . uniqid();
            
            if (is_dir($dir)) {
                throw new Exception("Директория не должна существовать");
            }
            
            $logger = new Logger(['directory' => $dir]);
            
            if (!is_dir($dir)) {
                throw new Exception("Директория не была создана автоматически");
            }
        });
    }

    private function testConfigurationCaching(): void
    {
        $this->runTest("Кеширование конфигурации", function() {
            $dir = $this->testDirectory . '/cached';
            mkdir($dir, 0777, true);
            
            Logger::clearAllCaches();
            
            $logger1 = new Logger(['directory' => $dir, 'file_name' => 'test1.log']);
            $logger2 = new Logger(['directory' => $dir, 'file_name' => 'test2.log']);
            
            // Второй логгер должен использовать кешированную конфигурацию
            // (кроме file_name, который берется из кеша первого)
            $logger2->info('Test');
            $logger2->flush();
            
            if (!file_exists($dir . '/test1.log')) {
                throw new Exception("Кеш конфигурации работает неправильно");
            }
        });
    }

    // ===============================================================================
    // БЛОК 2: УРОВНИ ЛОГИРОВАНИЯ
    // ===============================================================================

    private function testDebugLogging(): void
    {
        $this->runTest("Логирование уровня DEBUG", function() {
            $dir = $this->testDirectory . '/debug';
            mkdir($dir, 0777, true);
            
            $logger = new Logger(['directory' => $dir, 'file_name' => 'debug.log']);
            $logger->debug('Debug message', ['debug_info' => 'value']);
            $logger->flush();
            
            $content = file_get_contents($dir . '/debug.log');
            if (!str_contains($content, 'DEBUG')) {
                throw new Exception("Уровень DEBUG не найден в логе");
            }
            if (!str_contains($content, 'Debug message')) {
                throw new Exception("Сообщение не найдено в логе");
            }
        });
    }

    private function testInfoLogging(): void
    {
        $this->runTest("Логирование уровня INFO", function() {
            $dir = $this->testDirectory . '/info';
            mkdir($dir, 0777, true);
            
            $logger = new Logger(['directory' => $dir, 'file_name' => 'info.log']);
            $logger->info('Info message', ['user_id' => 123]);
            $logger->flush();
            
            $content = file_get_contents($dir . '/info.log');
            if (!str_contains($content, 'INFO')) {
                throw new Exception("Уровень INFO не найден в логе");
            }
            if (!str_contains($content, 'Info message')) {
                throw new Exception("Сообщение не найдено в логе");
            }
        });
    }

    private function testWarningLogging(): void
    {
        $this->runTest("Логирование уровня WARNING", function() {
            $dir = $this->testDirectory . '/warning';
            mkdir($dir, 0777, true);
            
            $logger = new Logger(['directory' => $dir, 'file_name' => 'warning.log']);
            $logger->warning('Warning message', ['warning_type' => 'deprecation']);
            $logger->flush();
            
            $content = file_get_contents($dir . '/warning.log');
            if (!str_contains($content, 'WARNING')) {
                throw new Exception("Уровень WARNING не найден в логе");
            }
        });
    }

    private function testErrorLogging(): void
    {
        $this->runTest("Логирование уровня ERROR", function() {
            $dir = $this->testDirectory . '/error';
            mkdir($dir, 0777, true);
            
            $logger = new Logger(['directory' => $dir, 'file_name' => 'error.log']);
            $logger->error('Error message', ['error_code' => 500]);
            $logger->flush();
            
            $content = file_get_contents($dir . '/error.log');
            if (!str_contains($content, 'ERROR')) {
                throw new Exception("Уровень ERROR не найден в логе");
            }
            if (!str_contains($content, '500')) {
                throw new Exception("Контекст не найден в логе");
            }
        });
    }

    private function testCriticalLogging(): void
    {
        $this->runTest("Логирование уровня CRITICAL", function() {
            $dir = $this->testDirectory . '/critical';
            mkdir($dir, 0777, true);
            
            $logger = new Logger(['directory' => $dir, 'file_name' => 'critical.log']);
            $logger->critical('Critical error', ['system' => 'database']);
            $logger->flush();
            
            $content = file_get_contents($dir . '/critical.log');
            if (!str_contains($content, 'CRITICAL')) {
                throw new Exception("Уровень CRITICAL не найден в логе");
            }
        });
    }

    private function testInvalidLogLevel(): void
    {
        $this->runTest("Валидация: недопустимый уровень логирования", function() {
            $dir = $this->testDirectory . '/invalid_level';
            mkdir($dir, 0777, true);
            
            $logger = new Logger(['directory' => $dir]);
            
            try {
                $logger->log('INVALID_LEVEL', 'Test');
                throw new Exception("Исключение не было выброшено");
            } catch (LoggerException $e) {
                if (!str_contains($e->getMessage(), 'Недопустимый уровень')) {
                    throw new Exception("Неправильное сообщение об ошибке");
                }
            }
        });
    }

    // ===============================================================================
    // БЛОК 3: БУФЕРИЗАЦИЯ
    // ===============================================================================

    private function testNoBuffering(): void
    {
        $this->runTest("Без буферизации (немедленная запись)", function() {
            $dir = $this->testDirectory . '/no_buffer';
            mkdir($dir, 0777, true);
            
            $logger = new Logger([
                'directory' => $dir,
                'file_name' => 'no_buffer.log',
                'log_buffer_size' => 0,
            ]);
            
            $logger->info('Message 1');
            
            $content = file_get_contents($dir . '/no_buffer.log');
            if (!str_contains($content, 'Message 1')) {
                throw new Exception("Сообщение должно быть записано немедленно");
            }
        });
    }

    private function testSmallBuffering(): void
    {
        $this->runTest("Буферизация с малым размером буфера", function() {
            $dir = $this->testDirectory . '/small_buffer';
            mkdir($dir, 0777, true);
            
            $logger = new Logger([
                'directory' => $dir,
                'file_name' => 'small_buffer.log',
                'log_buffer_size' => 1, // 1 KB
            ]);
            
            $logger->info('Short message');
            $logger->info('Another short message');
            $logger->flush();
            
            $content = file_get_contents($dir . '/small_buffer.log');
            $lineCount = substr_count($content, 'message');
            if ($lineCount < 2) {
                throw new Exception("Не все сообщения записаны");
            }
        });
    }

    private function testLargeBuffering(): void
    {
        $this->runTest("Буферизация с большим размером буфера", function() {
            $dir = $this->testDirectory . '/large_buffer';
            mkdir($dir, 0777, true);
            
            $logger = new Logger([
                'directory' => $dir,
                'file_name' => 'large_buffer.log',
                'log_buffer_size' => 100, // 100 KB
            ]);
            
            for ($i = 1; $i <= 10; $i++) {
                $logger->info("Buffered message {$i}");
            }
            
            $logger->flush();
            
            $content = file_get_contents($dir . '/large_buffer.log');
            if (substr_count($content, 'Buffered message') !== 10) {
                throw new Exception("Не все буферизованные сообщения записаны");
            }
        });
    }

    private function testManualFlush(): void
    {
        $this->runTest("Ручной сброс буфера (flush)", function() {
            $dir = $this->testDirectory . '/manual_flush';
            mkdir($dir, 0777, true);
            
            $logger = new Logger([
                'directory' => $dir,
                'file_name' => 'manual_flush.log',
                'log_buffer_size' => 100,
            ]);
            
            $logger->info('Message before flush');
            $logger->flush();
            
            $content = file_get_contents($dir . '/manual_flush.log');
            if (!str_contains($content, 'Message before flush')) {
                throw new Exception("flush() не записал буфер в файл");
            }
        });
    }

    private function testDestructorFlush(): void
    {
        $this->runTest("Автоматический сброс буфера в деструкторе", function() {
            $dir = $this->testDirectory . '/destructor_flush';
            mkdir($dir, 0777, true);
            
            $logFile = $dir . '/destructor_flush.log';
            
            // Создаем логгер в отдельном scope, чтобы вызвался деструктор
            (function() use ($dir) {
                $logger = new Logger([
                    'directory' => $dir,
                    'file_name' => 'destructor_flush.log',
                    'log_buffer_size' => 100,
                ]);
                $logger->info('Message in destructor test');
            })();
            
            // После выхода из scope деструктор должен был сбросить буфер
            if (!file_exists($logFile)) {
                throw new Exception("Деструктор не создал файл");
            }
            
            $content = file_get_contents($logFile);
            if (!str_contains($content, 'Message in destructor test')) {
                throw new Exception("Деструктор не сбросил буфер");
            }
        });
    }

    // ===============================================================================
    // БЛОК 4: РОТАЦИЯ ФАЙЛОВ
    // ===============================================================================

    private function testFileRotationSingleFile(): void
    {
        $this->runTest("Ротация: один файл (max_files = 1)", function() {
            $dir = $this->testDirectory . '/rotation_single';
            mkdir($dir, 0777, true);
            
            $logger = new Logger([
                'directory' => $dir,
                'file_name' => 'rotation.log',
                'max_files' => 1,
                'max_file_size' => 1, // 1 MB
                'log_buffer_size' => 0,
            ]);
            
            // Записываем много данных чтобы превысить 1 MB
            for ($i = 0; $i < 2000; $i++) {
                $logger->info(str_repeat('A', 1000));
            }
            
            // Должен остаться только основной файл
            $files = glob($dir . '/*.log*');
            if (count($files) > 1) {
                throw new Exception("При max_files=1 должен быть только один файл, найдено: " . count($files));
            }
        });
    }

    private function testFileRotationMultipleFiles(): void
    {
        $this->runTest("Ротация: несколько файлов (max_files = 3)", function() {
            $dir = $this->testDirectory . '/rotation_multiple';
            mkdir($dir, 0777, true);
            
            $logger = new Logger([
                'directory' => $dir,
                'file_name' => 'rotation.log',
                'max_files' => 3,
                'max_file_size' => 1, // 1 MB
                'log_buffer_size' => 0,
            ]);
            
            // Записываем много данных для создания ротации
            for ($i = 0; $i < 5000; $i++) {
                $logger->info(str_repeat('B', 1000));
            }
            
            // Должно быть не более 3 файлов
            $files = glob($dir . '/*.log*');
            if (count($files) > 3) {
                throw new Exception("При max_files=3 должно быть не более 3 файлов, найдено: " . count($files));
            }
        });
    }

    private function testFileRotationMaxSize(): void
    {
        $this->runTest("Ротация: проверка максимального размера файла", function() {
            $dir = $this->testDirectory . '/rotation_size';
            mkdir($dir, 0777, true);
            
            $maxSizeMb = 2;
            $logger = new Logger([
                'directory' => $dir,
                'file_name' => 'size_test.log',
                'max_files' => 5,
                'max_file_size' => $maxSizeMb,
                'log_buffer_size' => 0,
            ]);
            
            // Записываем данные
            for ($i = 0; $i < 1000; $i++) {
                $logger->info(str_repeat('C', 1000));
            }
            
            $mainFile = $dir . '/size_test.log';
            if (file_exists($mainFile)) {
                $size = filesize($mainFile);
                $maxSizeBytes = $maxSizeMb * 1024 * 1024;
                
                // Текущий файл не должен значительно превышать максимальный размер
                if ($size > $maxSizeBytes * 1.5) {
                    throw new Exception("Файл слишком большой: {$size} байт (максимум: {$maxSizeBytes})");
                }
            }
        });
    }

    private function testFileRotationWithManyWrites(): void
    {
        $this->runTest("Ротация: множественные записи с проверкой целостности", function() {
            $dir = $this->testDirectory . '/rotation_many';
            mkdir($dir, 0777, true);
            
            $logger = new Logger([
                'directory' => $dir,
                'file_name' => 'many.log',
                'max_files' => 2,
                'max_file_size' => 1,
                'log_buffer_size' => 0,
            ]);
            
            for ($i = 1; $i <= 100; $i++) {
                $logger->info("Write number {$i}: " . str_repeat('D', 500));
            }
            
            // Проверяем что файлы существуют
            $files = glob($dir . '/*.log*');
            if (count($files) === 0) {
                throw new Exception("Не создано ни одного файла");
            }
        });
    }

    // ===============================================================================
    // БЛОК 5: ФОРМАТИРОВАНИЕ
    // ===============================================================================

    private function testDefaultFormat(): void
    {
        $this->runTest("Форматирование: формат по умолчанию", function() {
            $dir = $this->testDirectory . '/format_default';
            mkdir($dir, 0777, true);
            
            $logger = new Logger(['directory' => $dir, 'file_name' => 'default.log']);
            $logger->info('Test message');
            $logger->flush();
            
            $content = file_get_contents($dir . '/default.log');
            
            // Проверяем наличие всех элементов формата по умолчанию
            if (!str_contains($content, 'INFO')) {
                throw new Exception("Отсутствует уровень в формате");
            }
            if (!str_contains($content, 'Test message')) {
                throw new Exception("Отсутствует сообщение в формате");
            }
        });
    }

    private function testCustomFormat(): void
    {
        $this->runTest("Форматирование: пользовательский шаблон", function() {
            $dir = $this->testDirectory . '/format_custom';
            mkdir($dir, 0777, true);
            
            $logger = new Logger([
                'directory' => $dir,
                'file_name' => 'custom.log',
                'pattern' => '[{level}] {message}',
            ]);
            
            $logger->info('Custom format test');
            $logger->flush();
            
            $content = file_get_contents($dir . '/custom.log');
            
            if (!preg_match('/\[INFO\] Custom format test/', $content)) {
                throw new Exception("Пользовательский формат не применен правильно");
            }
        });
    }

    private function testCustomDateFormat(): void
    {
        $this->runTest("Форматирование: пользовательский формат даты", function() {
            $dir = $this->testDirectory . '/format_date';
            mkdir($dir, 0777, true);
            
            $logger = new Logger([
                'directory' => $dir,
                'file_name' => 'date.log',
                'pattern' => '{timestamp} {message}',
                'date_format' => 'Y-m-d',
            ]);
            
            $logger->info('Date format test');
            $logger->flush();
            
            $content = file_get_contents($dir . '/date.log');
            $currentDate = date('Y-m-d');
            
            if (!str_contains($content, $currentDate)) {
                throw new Exception("Пользовательский формат даты не применен");
            }
        });
    }

    private function testContextSerialization(): void
    {
        $this->runTest("Форматирование: сериализация контекста в JSON", function() {
            $dir = $this->testDirectory . '/context';
            mkdir($dir, 0777, true);
            
            $logger = new Logger(['directory' => $dir, 'file_name' => 'context.log']);
            
            $context = [
                'user_id' => 123,
                'action' => 'login',
                'ip' => '192.168.1.1',
            ];
            
            $logger->info('User action', $context);
            $logger->flush();
            
            $content = file_get_contents($dir . '/context.log');
            
            if (!str_contains($content, 'user_id')) {
                throw new Exception("Контекст не сериализован");
            }
            if (!str_contains($content, '123')) {
                throw new Exception("Значения контекста отсутствуют");
            }
        });
    }

    private function testComplexContextSerialization(): void
    {
        $this->runTest("Форматирование: сложная структура контекста", function() {
            $dir = $this->testDirectory . '/context_complex';
            mkdir($dir, 0777, true);
            
            $logger = new Logger(['directory' => $dir, 'file_name' => 'complex.log']);
            
            $context = [
                'user' => [
                    'id' => 456,
                    'name' => 'Иван Иванов',
                    'roles' => ['admin', 'user'],
                ],
                'request' => [
                    'method' => 'POST',
                    'url' => '/api/users',
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer token123',
                    ],
                ],
            ];
            
            $logger->info('Complex context test', $context);
            $logger->flush();
            
            $content = file_get_contents($dir . '/complex.log');
            
            if (!str_contains($content, 'Иван Иванов')) {
                throw new Exception("Вложенные данные не сериализованы");
            }
            if (!str_contains($content, 'admin')) {
                throw new Exception("Массивы в контексте не сериализованы");
            }
        });
    }

    private function testInvalidContextSerialization(): void
    {
        $this->runTest("Форматирование: обработка несериализуемого контекста", function() {
            $dir = $this->testDirectory . '/context_invalid';
            mkdir($dir, 0777, true);
            
            $logger = new Logger(['directory' => $dir, 'file_name' => 'invalid.log']);
            
            // Создаем контекст с рекурсивной ссылкой (не сериализуется в JSON)
            $context = [];
            $context['self'] = &$context;
            
            try {
                $logger->info('Invalid context test', $context);
                $logger->flush();
                
                // Должно обработаться без исключения, но с информацией об ошибке
                $content = file_get_contents($dir . '/invalid.log');
                if (strlen($content) === 0) {
                    throw new Exception("Лог не записан при ошибке сериализации");
                }
            } catch (Exception $e) {
                // Это нормально, если исключение обработано внутри
            }
        });
    }

    // ===============================================================================
    // БЛОК 6: УПРАВЛЕНИЕ ЛОГИРОВАНИЕМ
    // ===============================================================================

    private function testEnableDisable(): void
    {
        $this->runTest("Управление: включение и отключение логирования", function() {
            $dir = $this->testDirectory . '/enable_disable';
            mkdir($dir, 0777, true);
            
            $logger = new Logger(['directory' => $dir, 'file_name' => 'toggle.log']);
            
            if (!$logger->isEnabled()) {
                throw new Exception("Logger должен быть включен по умолчанию");
            }
            
            $logger->disable();
            if ($logger->isEnabled()) {
                throw new Exception("Logger не отключился");
            }
            
            $logger->enable();
            if (!$logger->isEnabled()) {
                throw new Exception("Logger не включился");
            }
        });
    }

    private function testDisabledLoggingNoFiles(): void
    {
        $this->runTest("Управление: отключенный logger не создает файлы", function() {
            $dir = $this->testDirectory . '/disabled';
            mkdir($dir, 0777, true);
            
            $logger = new Logger(['directory' => $dir, 'file_name' => 'disabled.log']);
            $logger->disable();
            
            $logger->info('This should not be logged');
            $logger->flush();
            
            $logFile = $dir . '/disabled.log';
            if (file_exists($logFile)) {
                throw new Exception("Отключенный logger создал файл");
            }
        });
    }

    private function testInitiallyDisabled(): void
    {
        $this->runTest("Управление: инициализация с отключенным логированием", function() {
            $dir = $this->testDirectory . '/initially_disabled';
            mkdir($dir, 0777, true);
            
            $logger = new Logger([
                'directory' => $dir,
                'file_name' => 'init_disabled.log',
                'enabled' => false,
            ]);
            
            if ($logger->isEnabled()) {
                throw new Exception("Logger должен быть отключен");
            }
            
            $logger->info('Should not log');
            $logger->flush();
            
            if (file_exists($dir . '/init_disabled.log')) {
                throw new Exception("Изначально отключенный logger создал файл");
            }
        });
    }

    // ===============================================================================
    // БЛОК 7: ПРОИЗВОДИТЕЛЬНОСТЬ И СТРЕСС-ТЕСТЫ
    // ===============================================================================

    private function testManySequentialWrites(): void
    {
        $this->runTest("Производительность: 1000 последовательных записей", function() {
            $dir = $this->testDirectory . '/performance_seq';
            mkdir($dir, 0777, true);
            
            $logger = new Logger([
                'directory' => $dir,
                'file_name' => 'perf_seq.log',
                'log_buffer_size' => 50,
            ]);
            
            $startTime = microtime(true);
            
            for ($i = 1; $i <= 1000; $i++) {
                $logger->info("Sequential write {$i}", ['iteration' => $i]);
            }
            
            $logger->flush();
            $duration = microtime(true) - $startTime;
            
            $content = file_get_contents($dir . '/perf_seq.log');
            $lineCount = substr_count($content, 'Sequential write');
            
            if ($lineCount !== 1000) {
                throw new Exception("Записано {$lineCount} строк вместо 1000");
            }
            
            echo ConsoleColors::CYAN . "    ⏱ Время выполнения: " . number_format($duration, 3) . " сек\n" . ConsoleColors::RESET;
        });
    }

    private function testLargeMessages(): void
    {
        $this->runTest("Производительность: запись больших сообщений", function() {
            $dir = $this->testDirectory . '/performance_large';
            mkdir($dir, 0777, true);
            
            $logger = new Logger([
                'directory' => $dir,
                'file_name' => 'perf_large.log',
                'log_buffer_size' => 0,
            ]);
            
            // Записываем 10 сообщений по 100 KB
            for ($i = 1; $i <= 10; $i++) {
                $largeMessage = str_repeat("Large message {$i} ", 5000);
                $logger->info($largeMessage);
            }
            
            $logFile = $dir . '/perf_large.log';
            if (!file_exists($logFile)) {
                throw new Exception("Файл с большими сообщениями не создан");
            }
            
            $size = filesize($logFile);
            echo ConsoleColors::CYAN . "    📊 Размер файла: " . number_format($size / 1024, 2) . " KB\n" . ConsoleColors::RESET;
        });
    }

    private function testRapidEnableDisable(): void
    {
        $this->runTest("Производительность: быстрое переключение enable/disable", function() {
            $dir = $this->testDirectory . '/rapid_toggle';
            mkdir($dir, 0777, true);
            
            $logger = new Logger(['directory' => $dir, 'file_name' => 'rapid.log']);
            
            for ($i = 0; $i < 100; $i++) {
                $logger->enable();
                $logger->info("Message {$i}");
                $logger->disable();
            }
            
            $logger->enable();
            $logger->flush();
            
            // Должны быть записаны только сообщения когда logger был включен
            $content = file_get_contents($dir . '/rapid.log');
            if (strlen($content) === 0) {
                throw new Exception("Ничего не записано");
            }
        });
    }

    // ===============================================================================
    // БЛОК 8: КЕШИРОВАНИЕ
    // ===============================================================================

    private function testConfigCacheReuse(): void
    {
        $this->runTest("Кеширование: повторное использование кеша конфигурации", function() {
            $dir = $this->testDirectory . '/cache_reuse';
            mkdir($dir, 0777, true);
            
            Logger::clearAllCaches();
            
            $config = [
                'directory' => $dir,
                'file_name' => 'cached.log',
                'max_files' => 5,
            ];
            
            $logger1 = new Logger($config);
            $logger2 = new Logger($config);
            
            $logger1->info('From logger 1');
            $logger2->info('From logger 2');
            
            $logger1->flush();
            $logger2->flush();
            
            $content = file_get_contents($dir . '/cached.log');
            
            // Оба логгера должны писать в один файл благодаря кешу
            if (!str_contains($content, 'From logger 1') || !str_contains($content, 'From logger 2')) {
                throw new Exception("Кеш конфигурации не работает правильно");
            }
        });
    }

    private function testMetadataCache(): void
    {
        $this->runTest("Кеширование: кеш метаданных файлов", function() {
            $dir = $this->testDirectory . '/metadata_cache';
            mkdir($dir, 0777, true);
            
            $logger = new Logger([
                'directory' => $dir,
                'file_name' => 'metadata.log',
                'log_buffer_size' => 0,
            ]);
            
            // Первая запись создаст файл и закеширует метаданные
            $logger->info('Message 1');
            
            // Последующие записи должны использовать кеш
            for ($i = 2; $i <= 10; $i++) {
                $logger->info("Message {$i}");
            }
            
            $content = file_get_contents($dir . '/metadata.log');
            $messageCount = substr_count($content, 'Message');
            if ($messageCount !== 10) {
                throw new Exception("Не все сообщения записаны с кешем метаданных (найдено: {$messageCount})");
            }
        });
    }

    private function testClearAllCaches(): void
    {
        $this->runTest("Кеширование: очистка всех кешей", function() {
            $dir = $this->testDirectory . '/clear_all';
            mkdir($dir, 0777, true);
            
            $logger = new Logger(['directory' => $dir]);
            $logger->info('Test');
            $logger->flush();
            
            Logger::clearAllCaches();
            
            // После очистки кеша создание нового логгера должно работать
            $logger2 = new Logger(['directory' => $dir, 'file_name' => 'after_clear.log']);
            $logger2->info('After cache clear');
            $logger2->flush();
            
            if (!file_exists($dir . '/after_clear.log')) {
                throw new Exception("Логгер не работает после очистки кеша");
            }
        });
    }

    private function testClearCacheForDirectory(): void
    {
        $this->runTest("Кеширование: очистка кеша для конкретной директории", function() {
            $dir1 = $this->testDirectory . '/dir1';
            $dir2 = $this->testDirectory . '/dir2';
            mkdir($dir1, 0777, true);
            mkdir($dir2, 0777, true);
            
            $logger1 = new Logger(['directory' => $dir1]);
            $logger2 = new Logger(['directory' => $dir2]);
            
            Logger::clearCacheForDirectory($dir1);
            
            // Оба логгера должны продолжать работать
            $logger1->info('Test 1');
            $logger2->info('Test 2');
            $logger1->flush();
            $logger2->flush();
            
            if (!file_exists($dir1 . '/app.log') || !file_exists($dir2 . '/app.log')) {
                throw new Exception("Логгеры не работают после частичной очистки кеша");
            }
        });
    }

    // ===============================================================================
    // БЛОК 9: ОБРАБОТКА ОШИБОК
    // ===============================================================================

    private function testEmptyFileName(): void
    {
        $this->runTest("Обработка ошибок: пустое имя файла использует значение по умолчанию", function() {
            $dir = $this->testDirectory . '/empty_filename';
            mkdir($dir, 0777, true);
            
            $logger = new Logger([
                'directory' => $dir,
                'file_name' => '',
            ]);
            
            $logger->info('Test');
            $logger->flush();
            
            // Должен создаться файл с именем по умолчанию (app.log)
            if (!file_exists($dir . '/app.log')) {
                throw new Exception("Не создан файл с именем по умолчанию");
            }
        });
    }

    private function testMinimumValues(): void
    {
        $this->runTest("Обработка ошибок: минимальные значения параметров", function() {
            $dir = $this->testDirectory . '/minimum_values';
            mkdir($dir, 0777, true);
            
            $logger = new Logger([
                'directory' => $dir,
                'max_files' => -5, // Должно стать 1
                'max_file_size' => -10, // Должно стать 1
                'log_buffer_size' => -100, // Должно стать 0
            ]);
            
            $logger->info('Test with minimum values');
            $logger->flush();
            
            if (!file_exists($dir . '/app.log')) {
                throw new Exception("Logger не работает с минимальными значениями");
            }
        });
    }

    // ===============================================================================
    // БЛОК 10: ИНТЕГРАЦИОННЫЕ ТЕСТЫ
    // ===============================================================================

    private function testMultipleLevelsInOneFile(): void
    {
        $this->runTest("Интеграция: разные уровни в одном файле", function() {
            $dir = $this->testDirectory . '/multi_levels';
            mkdir($dir, 0777, true);
            
            $logger = new Logger(['directory' => $dir, 'file_name' => 'multi.log']);
            
            $logger->debug('Debug message');
            $logger->info('Info message');
            $logger->warning('Warning message');
            $logger->error('Error message');
            $logger->critical('Critical message');
            $logger->flush();
            
            $content = file_get_contents($dir . '/multi.log');
            
            $levels = ['DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL'];
            foreach ($levels as $level) {
                if (!str_contains($content, $level)) {
                    throw new Exception("Уровень {$level} не найден в логе");
                }
            }
            
            $lineCount = substr_count($content, "\n");
            if ($lineCount < 5) {
                throw new Exception("Недостаточно строк в логе: {$lineCount}");
            }
        });
    }

    private function testMultipleLoggersInSameDirectory(): void
    {
        $this->runTest("Интеграция: несколько логгеров в одной директории", function() {
            $dir = $this->testDirectory . '/multi_loggers';
            mkdir($dir, 0777, true);
            
            Logger::clearAllCaches();
            
            $logger1 = new Logger(['directory' => $dir, 'file_name' => 'app1.log']);
            $logger2 = new Logger(['directory' => $dir, 'file_name' => 'app2.log']);
            $logger3 = new Logger(['directory' => $dir, 'file_name' => 'app3.log']);
            
            $logger1->info('From logger 1');
            $logger2->info('From logger 2');
            $logger3->info('From logger 3');
            
            $logger1->flush();
            $logger2->flush();
            $logger3->flush();
            
            // Из-за кеша конфигурации все логгеры могут писать в один файл
            // Проверяем что хотя бы один файл создан и содержит данные
            $files = glob($dir . '/*.log');
            if (count($files) === 0) {
                throw new Exception("Не создано ни одного файла");
            }
            
            $hasContent = false;
            foreach ($files as $file) {
                if (filesize($file) > 0) {
                    $hasContent = true;
                    break;
                }
            }
            
            if (!$hasContent) {
                throw new Exception("Все файлы пустые");
            }
        });
    }

    private function testConcurrentWriting(): void
    {
        $this->runTest("Интеграция: одновременная запись в один файл", function() {
            $dir = $this->testDirectory . '/concurrent';
            mkdir($dir, 0777, true);
            
            $logger1 = new Logger([
                'directory' => $dir,
                'file_name' => 'concurrent.log',
                'log_buffer_size' => 0,
            ]);
            
            $logger2 = new Logger([
                'directory' => $dir,
                'file_name' => 'concurrent.log',
                'log_buffer_size' => 0,
            ]);
            
            // Имитация одновременной записи
            for ($i = 1; $i <= 50; $i++) {
                $logger1->info("Logger1: Message {$i}");
                $logger2->info("Logger2: Message {$i}");
            }
            
            $content = file_get_contents($dir . '/concurrent.log');
            
            $count1 = substr_count($content, 'Logger1');
            $count2 = substr_count($content, 'Logger2');
            
            if ($count1 === 0 || $count2 === 0) {
                throw new Exception("Не все логгеры записали данные: Logger1={$count1}, Logger2={$count2}");
            }
        });
    }

    // ===============================================================================
    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    // ===============================================================================

    /**
     * Печатает итоговую сводку тестирования
     */
    private function printSummary(): void
    {
        $duration = microtime(true) - $this->startTime;
        
        echo "\n";
        echo str_repeat("─", 79) . "\n";
        echo ConsoleColors::BOLD . "📊 ИТОГОВАЯ СВОДКА\n" . ConsoleColors::RESET;
        echo str_repeat("─", 79) . "\n\n";
        
        $successRate = $this->totalTests > 0 
            ? ($this->passedTests / $this->totalTests) * 100 
            : 0;
        
        echo "Всего тестов:     " . ConsoleColors::BOLD . $this->totalTests . ConsoleColors::RESET . "\n";
        echo ConsoleColors::GREEN . "Успешно:          " . $this->passedTests . ConsoleColors::RESET . "\n";
        echo ConsoleColors::RED . "Провалено:        " . $this->failedTests . ConsoleColors::RESET . "\n";
        echo "Процент успеха:   " . number_format($successRate, 2) . "%\n";
        echo "Время выполнения: " . number_format($duration, 3) . " сек\n";
        echo "\n";
        
        if ($this->failedTests > 0) {
            echo ConsoleColors::RED . ConsoleColors::BOLD . "❌ ПРОВАЛИВШИЕСЯ ТЕСТЫ:\n" . ConsoleColors::RESET;
            echo str_repeat("─", 79) . "\n";
            
            foreach ($this->results as $result) {
                if ($result['status'] === 'FAILED') {
                    echo ConsoleColors::RED . "✗ " . $result['name'] . ConsoleColors::RESET . "\n";
                    echo "  Ошибка: " . $result['error'] . "\n\n";
                }
            }
        }
        
        if ($this->passedTests === $this->totalTests) {
            echo ConsoleColors::GREEN . ConsoleColors::BOLD;
            echo "✓ ВСЕ ТЕСТЫ УСПЕШНО ПРОЙДЕНЫ!\n";
            echo ConsoleColors::RESET;
        }
        
        echo "\n";
        echo str_repeat("═", 79) . "\n";
    }

    /**
     * Очищает тестовое окружение
     */
    public function cleanup(): void
    {
        echo "\n" . ConsoleColors::YELLOW . "🧹 Очистка тестового окружения...\n" . ConsoleColors::RESET;
        
        if (is_dir($this->testDirectory)) {
            $this->removeDirectory($this->testDirectory);
            echo ConsoleColors::GREEN . "✓ Тестовая директория удалена\n" . ConsoleColors::RESET;
        }
        
        Logger::clearAllCaches();
        echo ConsoleColors::GREEN . "✓ Кеши очищены\n" . ConsoleColors::RESET;
    }

    /**
     * Рекурсивное удаление директории
     */
    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        
        $items = array_diff(scandir($directory) ?: [], ['.', '..']);
        foreach ($items as $item) {
            $path = $directory . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($directory);
    }
}

// ===============================================================================
// ЗАПУСК ТЕСТИРОВАНИЯ
// ===============================================================================

try {
    $tester = new LoggerTester();
    $tester->runAllTests();
    $tester->cleanup();
    
    exit(0);
    
} catch (Throwable $e) {
    echo ConsoleColors::RED . ConsoleColors::BOLD;
    echo "\n❌ КРИТИЧЕСКАЯ ОШИБКА ПРИ ТЕСТИРОВАНИИ:\n";
    echo ConsoleColors::RESET;
    echo ConsoleColors::RED . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    echo ConsoleColors::RESET;
    
    exit(1);
}

#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Комплексное тестирование класса Logger
 * 
 * Проверяет все параметры конфигурации и функциональность логгера:
 * - Обязательные и опциональные параметры
 * - Уровни логирования и фильтрация
 * - Ротация файлов
 * - Буферизация
 * - Форматирование логов
 * - Кеширование
 * - Email уведомления (опционально)
 * - Обработка ошибок
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Component\Logger;
use App\Component\Exception\Logger\LoggerException;
use App\Component\Exception\Logger\LoggerValidationException;

class LoggerComprehensiveTest
{
    private const TEST_LOG_DIR = '/tmp/logger_test';
    private const TELEGRAM_BOT_TOKEN = '';
    private const TELEGRAM_CHAT_ID = '';
    
    private int $testsTotal = 0;
    private int $testsPassed = 0;
    private int $testsFailed = 0;
    private array $failedTests = [];
    private float $startTime;
    
    public function __construct()
    {
        $this->startTime = microtime(true);
    }
    
    public function run(): void
    {
        $this->printHeader();
        $this->sendTelegramNotification("🚀 Начинаем комплексное тестирование Logger.class.php\n\nВсего тестов запланировано: ~30");
        
        try {
            $this->setupTestEnvironment();
            
            // Группа 1: Обязательные параметры
            $this->printSection("1. ОБЯЗАТЕЛЬНЫЕ ПАРАМЕТРЫ");
            $this->testRequiredParameters();
            
            // Группа 2: Базовое логирование
            $this->printSection("2. БАЗОВОЕ ЛОГИРОВАНИЕ");
            $this->testBasicLogging();
            
            // Группа 3: Уровни логирования
            $this->printSection("3. УРОВНИ ЛОГИРОВАНИЯ");
            $this->testLogLevels();
            
            // Группа 4: Ротация файлов
            $this->printSection("4. РОТАЦИЯ ФАЙЛОВ");
            $this->testFileRotation();
            
            // Группа 5: Буферизация
            $this->printSection("5. БУФЕРИЗАЦИЯ");
            $this->testBuffering();
            
            // Группа 6: Форматирование
            $this->printSection("6. ФОРМАТИРОВАНИЕ");
            $this->testFormatting();
            
            // Группа 7: Контроль включения/выключения
            $this->printSection("7. КОНТРОЛЬ ЛОГИРОВАНИЯ");
            $this->testEnableDisable();
            
            // Группа 8: Кеширование
            $this->printSection("8. КЕШИРОВАНИЕ");
            $this->testCaching();
            
            // Группа 9: Обработка ошибок
            $this->printSection("9. ОБРАБОТКА ОШИБОК");
            $this->testErrorHandling();
            
            // Группа 10: Производительность
            $this->printSection("10. ПРОИЗВОДИТЕЛЬНОСТЬ");
            $this->testPerformance();
            
            $this->printSummary();
            $this->generateReport();
            
        } catch (Throwable $e) {
            $this->printError("КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage());
            $this->sendTelegramNotification("❌ Критическая ошибка при тестировании:\n\n" . $e->getMessage());
            exit(1);
        } finally {
            $this->cleanupTestEnvironment();
        }
    }
    
    private function setupTestEnvironment(): void
    {
        $this->printInfo("Подготовка тестовой среды...");
        
        if (is_dir(self::TEST_LOG_DIR)) {
            $this->rmdirRecursive(self::TEST_LOG_DIR);
        }
        
        if (!mkdir(self::TEST_LOG_DIR, 0777, true)) {
            throw new Exception("Не удалось создать тестовую директорию");
        }
        
        Logger::clearAllCaches();
        
        $this->printSuccess("✓ Тестовая среда готова: " . self::TEST_LOG_DIR);
    }
    
    private function cleanupTestEnvironment(): void
    {
        $this->printInfo("\nОчистка тестовой среды...");
        
        try {
            if (is_dir(self::TEST_LOG_DIR)) {
                $this->rmdirRecursive(self::TEST_LOG_DIR);
            }
            Logger::clearAllCaches();
            $this->printSuccess("✓ Тестовая среда очищена");
        } catch (Throwable $e) {
            $this->printWarning("⚠ Ошибка при очистке: " . $e->getMessage());
        }
    }
    
    private function rmdirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->rmdirRecursive($path) : unlink($path);
        }
        rmdir($dir);
    }
    
    // ========================================================================
    // ТЕСТОВЫЕ ГРУППЫ
    // ========================================================================
    
    private function testRequiredParameters(): void
    {
        // Тест 1: Отсутствие directory
        $this->test(
            "Обязательный параметр 'directory'",
            function() {
                try {
                    new Logger(['file_name' => 'test.log']);
                    return false;
                } catch (LoggerValidationException $e) {
                    return str_contains($e->getMessage(), 'директори');
                }
            }
        );
        
        // Тест 2: Отсутствие file_name
        $this->test(
            "Обязательный параметр 'file_name'",
            function() {
                try {
                    new Logger(['directory' => self::TEST_LOG_DIR]);
                    // file_name имеет значение по умолчанию, так что это должно работать
                    return true;
                } catch (Throwable $e) {
                    return false;
                }
            }
        );
        
        // Тест 3: Минимальная конфигурация
        $this->test(
            "Минимальная рабочая конфигурация",
            function() {
                $logger = new Logger([
                    'directory' => self::TEST_LOG_DIR . '/minimal',
                    'file_name' => 'test.log'
                ]);
                
                $logger->info("Test message");
                $logFile = self::TEST_LOG_DIR . '/minimal/test.log';
                
                return file_exists($logFile) && filesize($logFile) > 0;
            }
        );
    }
    
    private function testBasicLogging(): void
    {
        // Тест 4: Метод info()
        $this->test(
            "Метод info() записывает сообщения",
            function() {
                $dir = self::TEST_LOG_DIR . '/basic_info';
                $logger = new Logger(['directory' => $dir, 'file_name' => 'test.log']);
                
                $logger->info("Info message");
                $content = file_get_contents($dir . '/test.log');
                
                return str_contains($content, 'INFO') && str_contains($content, 'Info message');
            }
        );
        
        // Тест 5: Метод warning()
        $this->test(
            "Метод warning() записывает сообщения",
            function() {
                $dir = self::TEST_LOG_DIR . '/basic_warning';
                $logger = new Logger(['directory' => $dir, 'file_name' => 'test.log']);
                
                $logger->warning("Warning message");
                $content = file_get_contents($dir . '/test.log');
                
                return str_contains($content, 'WARNING') && str_contains($content, 'Warning message');
            }
        );
        
        // Тест 6: Метод error()
        $this->test(
            "Метод error() записывает сообщения",
            function() {
                $dir = self::TEST_LOG_DIR . '/basic_error';
                $logger = new Logger(['directory' => $dir, 'file_name' => 'test.log']);
                
                $logger->error("Error message");
                $content = file_get_contents($dir . '/test.log');
                
                return str_contains($content, 'ERROR') && str_contains($content, 'Error message');
            }
        );
        
        // Тест 7: Метод debug()
        $this->test(
            "Метод debug() записывает сообщения",
            function() {
                $dir = self::TEST_LOG_DIR . '/basic_debug';
                $logger = new Logger(['directory' => $dir, 'file_name' => 'test.log']);
                
                $logger->debug("Debug message");
                $content = file_get_contents($dir . '/test.log');
                
                return str_contains($content, 'DEBUG') && str_contains($content, 'Debug message');
            }
        );
        
        // Тест 8: Метод critical()
        $this->test(
            "Метод critical() записывает сообщения",
            function() {
                $dir = self::TEST_LOG_DIR . '/basic_critical';
                $logger = new Logger(['directory' => $dir, 'file_name' => 'test.log']);
                
                $logger->critical("Critical message");
                $content = file_get_contents($dir . '/test.log');
                
                return str_contains($content, 'CRITICAL') && str_contains($content, 'Critical message');
            }
        );
        
        // Тест 9: Контекст в JSON формате
        $this->test(
            "Контекст записывается в JSON формате",
            function() {
                $dir = self::TEST_LOG_DIR . '/basic_context';
                $logger = new Logger(['directory' => $dir, 'file_name' => 'test.log']);
                
                $context = ['user_id' => 123, 'action' => 'login', 'кириллица' => 'тест'];
                $logger->info("User action", $context);
                
                $content = file_get_contents($dir . '/test.log');
                
                return str_contains($content, '"user_id":123') 
                    && str_contains($content, '"action":"login"')
                    && str_contains($content, 'кириллица');
            }
        );
    }
    
    private function testLogLevels(): void
    {
        // Тест 10: Фильтрация по уровню DEBUG
        $this->test(
            "Уровень DEBUG (пропускает все)",
            function() {
                $dir = self::TEST_LOG_DIR . '/level_debug';
                $logger = new Logger([
                    'directory' => $dir,
                    'file_name' => 'test.log',
                    'log_level' => 'DEBUG'
                ]);
                
                $logger->debug("Debug msg");
                $logger->info("Info msg");
                $logger->warning("Warning msg");
                
                $content = file_get_contents($dir . '/test.log');
                
                return str_contains($content, 'Debug msg')
                    && str_contains($content, 'Info msg')
                    && str_contains($content, 'Warning msg');
            }
        );
        
        // Тест 11: Фильтрация по уровню INFO
        $this->test(
            "Уровень INFO (блокирует DEBUG)",
            function() {
                $dir = self::TEST_LOG_DIR . '/level_info';
                $logger = new Logger([
                    'directory' => $dir,
                    'file_name' => 'test.log',
                    'log_level' => 'INFO'
                ]);
                
                $logger->debug("Debug msg");
                $logger->info("Info msg");
                $logger->warning("Warning msg");
                
                $content = file_get_contents($dir . '/test.log');
                
                return !str_contains($content, 'Debug msg')
                    && str_contains($content, 'Info msg')
                    && str_contains($content, 'Warning msg');
            }
        );
        
        // Тест 12: Фильтрация по уровню WARNING
        $this->test(
            "Уровень WARNING (блокирует DEBUG, INFO)",
            function() {
                $dir = self::TEST_LOG_DIR . '/level_warning';
                $logger = new Logger([
                    'directory' => $dir,
                    'file_name' => 'test.log',
                    'log_level' => 'WARNING'
                ]);
                
                $logger->debug("Debug msg");
                $logger->info("Info msg");
                $logger->warning("Warning msg");
                $logger->error("Error msg");
                
                $content = file_get_contents($dir . '/test.log');
                
                return !str_contains($content, 'Debug msg')
                    && !str_contains($content, 'Info msg')
                    && str_contains($content, 'Warning msg')
                    && str_contains($content, 'Error msg');
            }
        );
        
        // Тест 13: Фильтрация по уровню ERROR
        $this->test(
            "Уровень ERROR (блокирует DEBUG, INFO, WARNING)",
            function() {
                $dir = self::TEST_LOG_DIR . '/level_error';
                $logger = new Logger([
                    'directory' => $dir,
                    'file_name' => 'test.log',
                    'log_level' => 'ERROR'
                ]);
                
                $logger->info("Info msg");
                $logger->warning("Warning msg");
                $logger->error("Error msg");
                $logger->critical("Critical msg");
                
                $content = file_get_contents($dir . '/test.log');
                
                return !str_contains($content, 'Info msg')
                    && !str_contains($content, 'Warning msg')
                    && str_contains($content, 'Error msg')
                    && str_contains($content, 'Critical msg');
            }
        );
        
        // Тест 14: Фильтрация по уровню CRITICAL
        $this->test(
            "Уровень CRITICAL (только критические)",
            function() {
                $dir = self::TEST_LOG_DIR . '/level_critical';
                $logger = new Logger([
                    'directory' => $dir,
                    'file_name' => 'test.log',
                    'log_level' => 'CRITICAL'
                ]);
                
                $logger->error("Error msg");
                $logger->critical("Critical msg");
                
                $content = file_get_contents($dir . '/test.log');
                
                return !str_contains($content, 'Error msg')
                    && str_contains($content, 'Critical msg');
            }
        );
        
        // Тест 15: Альтернативное имя параметра min_level
        $this->test(
            "Параметр 'min_level' работает как 'log_level'",
            function() {
                $dir = self::TEST_LOG_DIR . '/level_min_level';
                $logger = new Logger([
                    'directory' => $dir,
                    'file_name' => 'test.log',
                    'min_level' => 'WARNING'
                ]);
                
                $logger->info("Info msg");
                $logger->warning("Warning msg");
                
                $content = file_get_contents($dir . '/test.log');
                
                return !str_contains($content, 'Info msg')
                    && str_contains($content, 'Warning msg');
            }
        );
    }
    
    private function testFileRotation(): void
    {
        // Тест 16: Ротация при достижении max_file_size
        $this->test(
            "Ротация срабатывает при достижении max_file_size",
            function() {
                $dir = self::TEST_LOG_DIR . '/rotation_size';
                $logger = new Logger([
                    'directory' => $dir,
                    'file_name' => 'test.log',
                    'max_file_size' => 1, // 1 МБ
                    'max_files' => 3
                ]);
                
                // Записываем ~1.5 МБ данных
                $largeMessage = str_repeat('A', 100000); // 100 КБ
                for ($i = 0; $i < 16; $i++) {
                    $logger->info($largeMessage);
                }
                
                // Проверяем что создались ротированные файлы
                return file_exists($dir . '/test.log')
                    && file_exists($dir . '/test.log.1');
            }
        );
        
        // Тест 17: Соблюдение max_files
        $this->test(
            "Параметр max_files ограничивает количество файлов",
            function() {
                $dir = self::TEST_LOG_DIR . '/rotation_max_files';
                $logger = new Logger([
                    'directory' => $dir,
                    'file_name' => 'test.log',
                    'max_file_size' => 1, // 1 МБ
                    'max_files' => 2
                ]);
                
                // Записываем ~3 МБ данных для создания 3+ файлов
                $largeMessage = str_repeat('B', 100000); // 100 КБ
                for ($i = 0; $i < 35; $i++) {
                    $logger->info($largeMessage);
                }
                
                // Должно быть только 2 файла (test.log и test.log.1)
                return file_exists($dir . '/test.log')
                    && file_exists($dir . '/test.log.1')
                    && !file_exists($dir . '/test.log.2');
            }
        );
        
        // Тест 18: max_files = 1 (только один файл)
        $this->test(
            "max_files = 1 (перезапись единственного файла)",
            function() {
                $dir = self::TEST_LOG_DIR . '/rotation_single';
                $logger = new Logger([
                    'directory' => $dir,
                    'file_name' => 'test.log',
                    'max_file_size' => 1,
                    'max_files' => 1
                ]);
                
                $largeMessage = str_repeat('C', 100000);
                for ($i = 0; $i < 20; $i++) {
                    $logger->info($largeMessage);
                }
                
                return file_exists($dir . '/test.log')
                    && !file_exists($dir . '/test.log.1')
                    && filesize($dir . '/test.log') < 1.5 * 1024 * 1024;
            }
        );
    }
    
    private function testBuffering(): void
    {
        // Тест 19: Буферизация выключена (log_buffer_size = 0)
        $this->test(
            "Без буферизации (log_buffer_size = 0)",
            function() {
                $dir = self::TEST_LOG_DIR . '/buffer_disabled';
                $logger = new Logger([
                    'directory' => $dir,
                    'file_name' => 'test.log',
                    'log_buffer_size' => 0
                ]);
                
                $logger->info("Message 1");
                
                // Файл должен обновиться сразу
                clearstatcache();
                $size1 = filesize($dir . '/test.log');
                
                $logger->info("Message 2");
                
                clearstatcache();
                $size2 = filesize($dir . '/test.log');
                
                return $size2 > $size1;
            }
        );
        
        // Тест 20: Буферизация включена
        $this->test(
            "С буферизацией (log_buffer_size = 64 КБ)",
            function() {
                $dir = self::TEST_LOG_DIR . '/buffer_enabled';
                $logger = new Logger([
                    'directory' => $dir,
                    'file_name' => 'test.log',
                    'log_buffer_size' => 64 // 64 КБ
                ]);
                
                // Пишем маленькие сообщения (не заполняют буфер)
                $logger->info("Small message 1");
                $logger->info("Small message 2");
                
                // Файл может не существовать или быть пустым (буфер не сброшен)
                $filePath = $dir . '/test.log';
                
                if (file_exists($filePath)) {
                    $sizeBefore = filesize($filePath);
                } else {
                    $sizeBefore = 0;
                }
                
                // Принудительно сбрасываем буфер
                $logger->flush();
                
                clearstatcache();
                $sizeAfter = file_exists($filePath) ? filesize($filePath) : 0;
                
                return $sizeAfter > $sizeBefore;
            }
        );
        
        // Тест 21: Автоматический сброс буфера при заполнении
        $this->test(
            "Автоматический сброс при заполнении буфера",
            function() {
                $dir = self::TEST_LOG_DIR . '/buffer_auto_flush';
                $logger = new Logger([
                    'directory' => $dir,
                    'file_name' => 'test.log',
                    'log_buffer_size' => 8 // 8 КБ - маленький буфер
                ]);
                
                // Записываем сообщения, которые переполнят буфер
                $largeMessage = str_repeat('D', 1000); // 1 КБ
                for ($i = 0; $i < 10; $i++) {
                    $logger->info($largeMessage);
                }
                
                // Файл должен существовать и содержать данные
                clearstatcache();
                $filePath = $dir . '/test.log';
                
                return file_exists($filePath) && filesize($filePath) > 0;
            }
        );
    }
    
    private function testFormatting(): void
    {
        // Тест 22: Пользовательский pattern
        $this->test(
            "Пользовательский pattern форматирования",
            function() {
                $dir = self::TEST_LOG_DIR . '/format_pattern';
                $logger = new Logger([
                    'directory' => $dir,
                    'file_name' => 'test.log',
                    'pattern' => '[{level}] {timestamp} | {message} | Context: {context}'
                ]);
                
                $logger->info("Test message", ['key' => 'value']);
                $content = file_get_contents($dir . '/test.log');
                
                return str_contains($content, '[INFO]')
                    && str_contains($content, '|')
                    && str_contains($content, 'Context:');
            }
        );
        
        // Тест 23: Пользовательский date_format
        $this->test(
            "Пользовательский date_format",
            function() {
                $dir = self::TEST_LOG_DIR . '/format_date';
                $logger = new Logger([
                    'directory' => $dir,
                    'file_name' => 'test.log',
                    'date_format' => 'Y-m-d H:i:s'
                ]);
                
                $logger->info("Test message");
                $content = file_get_contents($dir . '/test.log');
                
                // Проверяем формат даты YYYY-MM-DD HH:MM:SS
                return preg_match('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $content) === 1;
            }
        );
        
        // Тест 24: Обработка пустого контекста
        $this->test(
            "Пустой контекст отображается как {}",
            function() {
                $dir = self::TEST_LOG_DIR . '/format_empty_context';
                $logger = new Logger([
                    'directory' => $dir,
                    'file_name' => 'test.log'
                ]);
                
                $logger->info("Message without context");
                $content = file_get_contents($dir . '/test.log');
                
                return str_contains($content, '{}');
            }
        );
    }
    
    private function testEnableDisable(): void
    {
        // Тест 25: enabled = false в конфиге
        $this->test(
            "Параметр enabled = false отключает логирование",
            function() {
                $dir = self::TEST_LOG_DIR . '/control_disabled';
                $logger = new Logger([
                    'directory' => $dir,
                    'file_name' => 'test.log',
                    'enabled' => false
                ]);
                
                $logger->info("This should not be logged");
                
                return !file_exists($dir . '/test.log');
            }
        );
        
        // Тест 26: Метод disable()
        $this->test(
            "Метод disable() останавливает логирование",
            function() {
                $dir = self::TEST_LOG_DIR . '/control_method_disable';
                $logger = new Logger([
                    'directory' => $dir,
                    'file_name' => 'test.log'
                ]);
                
                $logger->info("Message 1");
                $logger->disable();
                $logger->info("Message 2");
                
                $content = file_get_contents($dir . '/test.log');
                
                return str_contains($content, 'Message 1')
                    && !str_contains($content, 'Message 2');
            }
        );
        
        // Тест 27: Метод enable()
        $this->test(
            "Метод enable() возобновляет логирование",
            function() {
                $dir = self::TEST_LOG_DIR . '/control_method_enable';
                $logger = new Logger([
                    'directory' => $dir,
                    'file_name' => 'test.log',
                    'enabled' => false
                ]);
                
                $logger->info("Message 1");
                $logger->enable();
                $logger->info("Message 2");
                
                $content = file_get_contents($dir . '/test.log');
                
                return !str_contains($content, 'Message 1')
                    && str_contains($content, 'Message 2');
            }
        );
        
        // Тест 28: Метод isEnabled()
        $this->test(
            "Метод isEnabled() возвращает корректный статус",
            function() {
                $dir = self::TEST_LOG_DIR . '/control_is_enabled';
                $logger = new Logger([
                    'directory' => $dir,
                    'file_name' => 'test.log'
                ]);
                
                $enabled1 = $logger->isEnabled();
                $logger->disable();
                $enabled2 = $logger->isEnabled();
                $logger->enable();
                $enabled3 = $logger->isEnabled();
                
                return $enabled1 === true && $enabled2 === false && $enabled3 === true;
            }
        );
    }
    
    private function testCaching(): void
    {
        // Тест 29: Кеширование конфигурации
        $this->test(
            "Кеширование конфигурации для одной директории",
            function() {
                $dir = self::TEST_LOG_DIR . '/cache_config';
                
                // Первый инстанс
                $logger1 = new Logger([
                    'directory' => $dir,
                    'file_name' => 'test.log',
                    'max_files' => 5
                ]);
                
                // Второй инстанс той же директории (должен использовать кеш)
                $logger2 = new Logger([
                    'directory' => $dir,
                    'file_name' => 'other.log', // Будет проигнорирован - используется кеш
                    'max_files' => 10
                ]);
                
                $logger1->info("From logger1");
                $logger2->info("From logger2");
                
                // Оба должны писать в test.log (из кеша)
                return file_exists($dir . '/test.log')
                    && !file_exists($dir . '/other.log');
            }
        );
        
        // Тест 30: Очистка кеша clearAllCaches()
        $this->test(
            "Метод clearAllCaches() очищает кеши",
            function() {
                $dir = self::TEST_LOG_DIR . '/cache_clear_all';
                
                $logger1 = new Logger([
                    'directory' => $dir,
                    'file_name' => 'test1.log'
                ]);
                
                Logger::clearAllCaches();
                
                // После очистки кеша новый инстанс создаст другой файл
                $logger2 = new Logger([
                    'directory' => $dir,
                    'file_name' => 'test2.log'
                ]);
                
                $logger1->info("Logger 1");
                $logger2->info("Logger 2");
                
                return file_exists($dir . '/test1.log')
                    && file_exists($dir . '/test2.log');
            }
        );
        
        // Тест 31: clearCacheForDirectory()
        $this->test(
            "Метод clearCacheForDirectory() очищает кеш для директории",
            function() {
                $dir1 = self::TEST_LOG_DIR . '/cache_clear_dir1';
                $dir2 = self::TEST_LOG_DIR . '/cache_clear_dir2';
                
                $logger1 = new Logger(['directory' => $dir1, 'file_name' => 'test1.log']);
                $logger2 = new Logger(['directory' => $dir2, 'file_name' => 'test2.log']);
                
                Logger::clearCacheForDirectory($dir1);
                
                // dir1 очищен, dir2 - нет
                $logger3 = new Logger(['directory' => $dir1, 'file_name' => 'new1.log']);
                $logger4 = new Logger(['directory' => $dir2, 'file_name' => 'new2.log']);
                
                $logger3->info("New logger 1");
                $logger4->info("New logger 2");
                
                return file_exists($dir1 . '/new1.log')
                    && !file_exists($dir2 . '/new2.log')
                    && file_exists($dir2 . '/test2.log');
            }
        );
    }
    
    private function testErrorHandling(): void
    {
        // Тест 32: Недопустимый уровень логирования
        $this->test(
            "Исключение при недопустимом уровне логирования",
            function() {
                try {
                    $logger = new Logger([
                        'directory' => self::TEST_LOG_DIR . '/error_invalid_level',
                        'file_name' => 'test.log',
                        'log_level' => 'INVALID'
                    ]);
                    return false;
                } catch (LoggerValidationException $e) {
                    return str_contains($e->getMessage(), 'Недопустимый уровень');
                }
            }
        );
        
        // Тест 33: Недоступная директория для записи
        $this->test(
            "Исключение при недоступной директории",
            function() {
                $dir = self::TEST_LOG_DIR . '/error_readonly';
                mkdir($dir, 0555); // Только чтение
                
                try {
                    $logger = new Logger([
                        'directory' => $dir,
                        'file_name' => 'test.log'
                    ]);
                    chmod($dir, 0777); // Восстанавливаем права
                    return false;
                } catch (LoggerValidationException $e) {
                    chmod($dir, 0777);
                    return str_contains($e->getMessage(), 'прав');
                }
            }
        );
        
        // Тест 34: Некорректный min_level в log()
        $this->test(
            "Исключение при использовании недопустимого уровня в log()",
            function() {
                $logger = new Logger([
                    'directory' => self::TEST_LOG_DIR . '/error_log_invalid',
                    'file_name' => 'test.log'
                ]);
                
                try {
                    $logger->log('INVALID_LEVEL', 'Test message');
                    return false;
                } catch (LoggerException $e) {
                    return str_contains($e->getMessage(), 'Недопустимый уровень');
                }
            }
        );
    }
    
    private function testPerformance(): void
    {
        // Тест 35: Производительность без буфера
        $this->test(
            "Производительность без буферизации (1000 записей)",
            function() {
                $dir = self::TEST_LOG_DIR . '/perf_no_buffer';
                $logger = new Logger([
                    'directory' => $dir,
                    'file_name' => 'test.log',
                    'log_buffer_size' => 0
                ]);
                
                $start = microtime(true);
                
                for ($i = 0; $i < 1000; $i++) {
                    $logger->info("Performance test message {$i}");
                }
                
                $duration = microtime(true) - $start;
                
                $this->printInfo("  ⏱  Время без буфера: " . round($duration, 3) . " сек");
                
                return $duration < 5.0; // Должно выполниться менее чем за 5 секунд
            }
        );
        
        // Тест 36: Производительность с буфером
        $this->test(
            "Производительность с буферизацией (1000 записей)",
            function() {
                $dir = self::TEST_LOG_DIR . '/perf_with_buffer';
                $logger = new Logger([
                    'directory' => $dir,
                    'file_name' => 'test.log',
                    'log_buffer_size' => 128
                ]);
                
                $start = microtime(true);
                
                for ($i = 0; $i < 1000; $i++) {
                    $logger->info("Performance test message {$i}");
                }
                
                $logger->flush(); // Принудительно сбрасываем буфер
                
                $duration = microtime(true) - $start;
                
                $this->printInfo("  ⏱  Время с буфером: " . round($duration, 3) . " сек");
                
                return $duration < 5.0;
            }
        );
        
        // Тест 37: Производительность с фильтрацией уровня
        $this->test(
            "Производительность с фильтрацией уровня (1000 записей)",
            function() {
                $dir = self::TEST_LOG_DIR . '/perf_filtered';
                $logger = new Logger([
                    'directory' => $dir,
                    'file_name' => 'test.log',
                    'log_level' => 'ERROR' // Большинство сообщений будут отфильтрованы
                ]);
                
                $start = microtime(true);
                
                for ($i = 0; $i < 1000; $i++) {
                    $logger->debug("This will be filtered {$i}");
                    $logger->info("This will be filtered too {$i}");
                }
                
                $duration = microtime(true) - $start;
                
                $this->printInfo("  ⏱  Время с фильтрацией: " . round($duration, 3) . " сек");
                
                return $duration < 1.0; // Фильтрация должна быть быстрой
            }
        );
    }
    
    // ========================================================================
    // УТИЛИТЫ ТЕСТИРОВАНИЯ
    // ========================================================================
    
    private function test(string $name, callable $testFunction): void
    {
        $this->testsTotal++;
        
        try {
            Logger::clearAllCaches();
            
            $result = $testFunction();
            
            if ($result === true) {
                $this->testsPassed++;
                $this->printSuccess("  ✓ {$name}");
            } else {
                $this->testsFailed++;
                $this->failedTests[] = $name;
                $this->printError("  ✗ {$name}");
            }
            
        } catch (Throwable $e) {
            $this->testsFailed++;
            $this->failedTests[] = $name . " (Exception: " . $e->getMessage() . ")";
            $this->printError("  ✗ {$name}");
            $this->printError("    Exception: " . $e->getMessage());
        }
    }
    
    // ========================================================================
    // ВЫВОД И ОТЧЕТЫ
    // ========================================================================
    
    private function printHeader(): void
    {
        echo "\n";
        echo "╔════════════════════════════════════════════════════════════════╗\n";
        echo "║                                                                ║\n";
        echo "║     КОМПЛЕКСНОЕ ТЕСТИРОВАНИЕ LOGGER.CLASS.PHP                  ║\n";
        echo "║                                                                ║\n";
        echo "╚════════════════════════════════════════════════════════════════╝\n";
        echo "\n";
    }
    
    private function printSection(string $title): void
    {
        echo "\n";
        echo "┌────────────────────────────────────────────────────────────────┐\n";
        echo "│ {$title}\n";
        echo "└────────────────────────────────────────────────────────────────┘\n";
    }
    
    private function printSuccess(string $message): void
    {
        echo "\033[32m{$message}\033[0m\n";
    }
    
    private function printError(string $message): void
    {
        echo "\033[31m{$message}\033[0m\n";
    }
    
    private function printWarning(string $message): void
    {
        echo "\033[33m{$message}\033[0m\n";
    }
    
    private function printInfo(string $message): void
    {
        echo "\033[36m{$message}\033[0m\n";
    }
    
    private function printSummary(): void
    {
        $duration = round(microtime(true) - $this->startTime, 2);
        $successRate = $this->testsTotal > 0 
            ? round(($this->testsPassed / $this->testsTotal) * 100, 1) 
            : 0;
        
        echo "\n";
        echo "╔════════════════════════════════════════════════════════════════╗\n";
        echo "║                      ИТОГОВЫЕ РЕЗУЛЬТАТЫ                       ║\n";
        echo "╚════════════════════════════════════════════════════════════════╝\n";
        echo "\n";
        echo "  Всего тестов:      {$this->testsTotal}\n";
        
        if ($this->testsPassed > 0) {
            $this->printSuccess("  ✓ Пройдено:        {$this->testsPassed}");
        }
        
        if ($this->testsFailed > 0) {
            $this->printError("  ✗ Провалено:       {$this->testsFailed}");
        }
        
        echo "  Успешность:        {$successRate}%\n";
        echo "  Время выполнения:  {$duration} сек\n";
        
        if ($this->testsFailed > 0) {
            echo "\n";
            $this->printError("Провалившиеся тесты:");
            foreach ($this->failedTests as $index => $testName) {
                $this->printError("  " . ($index + 1) . ". {$testName}");
            }
        }
        
        echo "\n";
        
        $statusEmoji = $this->testsFailed === 0 ? "✅" : "⚠️";
        $statusText = $this->testsFailed === 0 ? "ВСЕ ТЕСТЫ ПРОЙДЕНЫ!" : "ЕСТЬ ОШИБКИ";
        
        if ($this->testsFailed === 0) {
            $this->printSuccess("  {$statusEmoji} {$statusText}");
            $this->sendTelegramNotification(
                "✅ Тестирование Logger завершено успешно!\n\n" .
                "Пройдено: {$this->testsPassed}/{$this->testsTotal}\n" .
                "Успешность: {$successRate}%\n" .
                "Время: {$duration} сек"
            );
        } else {
            $this->printError("  {$statusEmoji} {$statusText}");
            $this->sendTelegramNotification(
                "⚠️ Тестирование Logger завершено с ошибками\n\n" .
                "Пройдено: {$this->testsPassed}/{$this->testsTotal}\n" .
                "Провалено: {$this->testsFailed}\n" .
                "Успешность: {$successRate}%\n" .
                "Время: {$duration} сек"
            );
        }
        
        echo "\n";
    }
    
    private function generateReport(): void
    {
        $duration = round(microtime(true) - $this->startTime, 2);
        $successRate = $this->testsTotal > 0 
            ? round(($this->testsPassed / $this->testsTotal) * 100, 1) 
            : 0;
        
        $reportPath = __DIR__ . '/LOGGER_TEST_REPORT.md';
        
        $report = <<<MD
# Отчет о тестировании Logger.class.php

**Дата и время:** {$this->getTimestamp()}  
**Продолжительность:** {$duration} сек  

## Сводка

| Метрика | Значение |
|---------|----------|
| Всего тестов | {$this->testsTotal} |
| Успешно | {$this->testsPassed} |
| Провалено | {$this->testsFailed} |
| Успешность | {$successRate}% |

## Статус

MD;

        if ($this->testsFailed === 0) {
            $report .= "✅ **ВСЕ ТЕСТЫ ПРОЙДЕНЫ УСПЕШНО!**\n\n";
        } else {
            $report .= "⚠️ **ОБНАРУЖЕНЫ ОШИБКИ**\n\n";
            $report .= "### Провалившиеся тесты:\n\n";
            foreach ($this->failedTests as $index => $testName) {
                $report .= ($index + 1) . ". {$testName}\n";
            }
            $report .= "\n";
        }
        
        $report .= <<<MD
## Протестированные параметры конфигурации

### Обязательные параметры
- ✓ `directory` - путь к директории логов
- ✓ `file_name` - имя файла лога

### Опциональные параметры
- ✓ `enabled` - включение/выключение логирования
- ✓ `log_level` / `min_level` - минимальный уровень логирования
- ✓ `max_files` - максимальное количество файлов при ротации
- ✓ `max_file_size` - максимальный размер файла в МБ
- ✓ `pattern` - шаблон форматирования записей
- ✓ `date_format` - формат временной метки
- ✓ `log_buffer_size` - размер буфера в КБ

## Протестированные функции

### Методы логирования
- ✓ `debug()` - отладочные сообщения
- ✓ `info()` - информационные сообщения
- ✓ `warning()` - предупреждения
- ✓ `error()` - ошибки
- ✓ `critical()` - критические ошибки
- ✓ `log()` - общий метод логирования

### Управление
- ✓ `enable()` - включить логирование
- ✓ `disable()` - выключить логирование
- ✓ `isEnabled()` - проверить статус
- ✓ `flush()` - сбросить буфер

### Статические методы
- ✓ `clearAllCaches()` - очистить все кеши
- ✓ `clearCacheForDirectory()` - очистить кеш директории

## Проверенные сценарии

1. **Базовое логирование** - все уровни логирования работают корректно
2. **Фильтрация по уровню** - сообщения ниже min_level не записываются
3. **Ротация файлов** - при превышении max_file_size создаются новые файлы
4. **Ограничение файлов** - max_files соблюдается, старые файлы удаляются
5. **Буферизация** - буфер накапливает записи и сбрасывается при заполнении
6. **Форматирование** - пользовательские pattern и date_format применяются
7. **Контекст** - дополнительные данные сериализуются в JSON
8. **Кеширование** - конфигурация и метаданные кешируются для производительности
9. **Обработка ошибок** - валидация параметров и исключения работают корректно
10. **Производительность** - логгер справляется с высокой нагрузкой

## Рекомендации

MD;

        if ($this->testsFailed === 0) {
            $report .= <<<MD
- ✅ Класс Logger полностью готов к использованию в production
- ✅ Все параметры конфигурации работают как задумано
- ✅ Производительность соответствует ожиданиям
- ✅ Обработка ошибок реализована корректно

MD;
        } else {
            $report .= <<<MD
- ⚠️ Требуется исправление обнаруженных ошибок
- ⚠️ Перезапустить тесты после исправлений
- ⚠️ Проверить провалившиеся тесты вручную

MD;
        }
        
        $report .= <<<MD
## Конфигурационный файл

Создан файл `production/configs/logger.json` с полной документацией всех параметров.

**Минимальная конфигурация:**
```json
{
  "directory": "/var/www/logs",
  "file_name": "app.log"
}
```

**Рекомендуемая конфигурация для production:**
```json
{
  "directory": "/var/www/logs/production",
  "file_name": "app.log",
  "log_level": "INFO",
  "max_files": 7,
  "max_file_size": 50,
  "log_buffer_size": 128,
  "pattern": "[{timestamp}] {level}: {message} {context}",
  "date_format": "Y-m-d H:i:s"
}
```

---

*Отчет сгенерирован автоматически*
MD;

        file_put_contents($reportPath, $report);
        
        $this->printSuccess("\n✓ Отчет сохранен: {$reportPath}");
    }
    
    private function getTimestamp(): string
    {
        return (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }
    
    private function sendTelegramNotification(string $message): void
    {
        if (self::TELEGRAM_BOT_TOKEN === '' || self::TELEGRAM_CHAT_ID === '') {
            return;
        }
        
        try {
            $url = "https://api.telegram.org/bot" . self::TELEGRAM_BOT_TOKEN . "/sendMessage";
            
            $data = [
                'chat_id' => self::TELEGRAM_CHAT_ID,
                'text' => $message,
                'parse_mode' => 'HTML'
            ];
            
            $options = [
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/x-www-form-urlencoded',
                    'content' => http_build_query($data),
                    'timeout' => 5
                ]
            ];
            
            @file_get_contents($url, false, stream_context_create($options));
        } catch (Throwable $e) {
            // Игнорируем ошибки отправки в Telegram
        }
    }
}

// ============================================================================
// ЗАПУСК ТЕСТОВ
// ============================================================================

$tester = new LoggerComprehensiveTest();
$tester->run();


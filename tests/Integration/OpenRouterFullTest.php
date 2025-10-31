<?php

declare(strict_types=1);

require_once __DIR__ . '/../../autoload.php';

use App\Component\OpenRouter;
use App\Component\OpenRouterMetrics;
use App\Component\Logger;
use App\Component\Exception\OpenRouter\OpenRouterException;
use App\Component\Exception\OpenRouter\OpenRouterApiException;
use App\Component\Exception\OpenRouter\OpenRouterValidationException;

/**
 * Полный интеграционный тест всех методов OpenRouter и OpenRouterMetrics
 * 
 * Тестирует все методы с реальными API вызовами, используя оптимизацию
 * для минимизации расходов (лимит $1).
 */
class OpenRouterFullTest
{
    private OpenRouter $openRouter;
    private OpenRouterMetrics $metrics;
    private Logger $logger;
    private array $results = [];
    private float $estimatedCost = 0.0;
    private string $logDirectory;

    /**
     * Конструктор теста
     *
     * @param string $apiKey API ключ OpenRouter
     */
    public function __construct(string $apiKey)
    {
        $this->logDirectory = __DIR__ . '/../../logs_openrouter_test';
        
        if (!is_dir($this->logDirectory)) {
            mkdir($this->logDirectory, 0755, true);
        }

        $this->logger = new Logger([
            'directory' => $this->logDirectory,
            'file_name' => 'openrouter_full_test.log',
            'max_files' => 3,
        ]);

        $config = [
            'api_key' => $apiKey,
            'app_name' => 'OpenRouterFullTestSuite',
            'timeout' => 60,
            'retries' => 2,
        ];

        $this->openRouter = new OpenRouter($config, $this->logger);
        $this->metrics = new OpenRouterMetrics($config, $this->logger);

        $this->logInfo('=== Инициализация тестового окружения ===');
    }

    /**
     * Запускает все тесты
     */
    public function runAllTests(): void
    {
        echo "╔══════════════════════════════════════════════════════════════════╗\n";
        echo "║  ПОЛНЫЙ ИНТЕГРАЦИОННЫЙ ТЕСТ OpenRouter И OpenRouterMetrics      ║\n";
        echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

        // Сначала проверяем метрики и баланс
        $this->testMetricsGetKeyInfo();
        $this->testMetricsGetBalance();
        $this->testMetricsGetUsageStats();
        $this->testMetricsGetRateLimits();
        $this->testMetricsGetAccountStatus();
        
        // Получаем список моделей
        $this->testMetricsGetModels();
        
        // Используем надежную, дешевую модель для тестов
        // GPT-3.5-Turbo стоит $0.0005 за 1M prompt tokens и $0.0015 за 1M completion tokens
        // Для теста с 10 tokens это будет ~$0.00002 что укладывается в лимит $1
        $cheapModel = 'openai/gpt-3.5-turbo';
        echo "   ℹ️  Используем для тестов: {$cheapModel}\n\n";

        // Тестируем методы оценки стоимости
        $this->testMetricsEstimateCost($cheapModel);
        $this->testMetricsHasEnoughBalance();
        
        // Тестируем основные методы OpenRouter с дешевой моделью
        $this->testText2Text($cheapModel);
        
        // Тестируем streaming
        $this->testTextStream($cheapModel);
        
        // Проверяем валидацию
        $this->testValidation();
        
        // Итоговый отчет
        $this->printReport();
    }

    /**
     * Находит самую дешевую текстовую модель
     *
     * @return string|null ID модели или null если не найдено
     */
    private function findCheapestModel(): ?string
    {
        try {
            $models = $this->metrics->getModels();
            $cheapest = null;
            $lowestCost = PHP_FLOAT_MAX;

            // Список моделей с проблемами (требуют особых настроек или возвращают нестандартный формат)
            $problematicModels = [
                'nvidia/nemotron-nano-12b-v2-vl:free',  // Требует настройки data policy
                'minimax/minimax-m2:free',  // Возвращает reasoning вместо content
            ];

            foreach ($models as $model) {
                // Пропускаем проблемные модели
                if (in_array($model['id'], $problematicModels, true)) {
                    continue;
                }

                // Ищем бесплатные модели или с минимальной стоимостью
                $promptCost = (float)$model['pricing']['prompt'];
                $completionCost = (float)$model['pricing']['completion'];
                $totalCost = $promptCost + $completionCost;

                // Пропускаем модели без текстовой модальности или с image
                if (isset($model['architecture']['modality'])) {
                    $modality = $model['architecture']['modality'];
                    if (strpos($modality, 'text') === false || strpos($modality, 'image') !== false) {
                        continue;
                    }
                }

                // Приоритет бесплатным моделям
                if ($totalCost == 0.0) {
                    $this->logInfo("Найдена бесплатная модель: {$model['id']}");
                    return $model['id'];
                }

                if ($totalCost < $lowestCost && $totalCost > 0) {
                    $lowestCost = $totalCost;
                    $cheapest = $model['id'];
                }
            }

            if ($cheapest !== null) {
                $this->logInfo("Найдена дешевая модель: {$cheapest} (стоимость: {$lowestCost})");
            }

            return $cheapest;
        } catch (Exception $e) {
            $this->logError('Ошибка поиска дешевой модели: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Тест: Получение информации о ключе
     */
    private function testMetricsGetKeyInfo(): void
    {
        $testName = 'OpenRouterMetrics::getKeyInfo()';
        echo "📊 Тест: {$testName}\n";
        
        try {
            $keyInfo = $this->metrics->getKeyInfo();
            
            $this->assertArrayHasKeys($keyInfo, ['label', 'usage', 'limit', 'is_free_tier', 'rate_limit']);
            $this->assertIsFloat($keyInfo['usage']);
            $this->assertIsArray($keyInfo['rate_limit']);
            
            echo "   ✓ Название ключа: {$keyInfo['label']}\n";
            echo "   ✓ Использовано: \${$keyInfo['usage']}\n";
            echo "   ✓ Лимит: " . ($keyInfo['limit'] !== null ? "\${$keyInfo['limit']}" : "Без лимита") . "\n";
            echo "   ✓ Бесплатный: " . ($keyInfo['is_free_tier'] ? "Да" : "Нет") . "\n";
            
            $this->recordSuccess($testName, $keyInfo);
        } catch (Exception $e) {
            $this->recordFailure($testName, $e);
        }
        
        echo "\n";
    }

    /**
     * Тест: Получение баланса
     */
    private function testMetricsGetBalance(): void
    {
        $testName = 'OpenRouterMetrics::getBalance()';
        echo "💰 Тест: {$testName}\n";
        
        try {
            $balance = $this->metrics->getBalance();
            
            $this->assertIsFloat($balance);
            
            if ($balance >= 0) {
                echo "   ✓ Доступный баланс: \${$balance}\n";
            } else {
                echo "   ✓ Использовано (без лимита): \$" . abs($balance) . "\n";
            }
            
            $this->recordSuccess($testName, ['balance' => $balance]);
        } catch (Exception $e) {
            $this->recordFailure($testName, $e);
        }
        
        echo "\n";
    }

    /**
     * Тест: Получение статистики использования
     */
    private function testMetricsGetUsageStats(): void
    {
        $testName = 'OpenRouterMetrics::getUsageStats()';
        echo "📈 Тест: {$testName}\n";
        
        try {
            $stats = $this->metrics->getUsageStats();
            
            $this->assertArrayHasKeys($stats, ['total_usage', 'limit', 'remaining', 'usage_percent', 'is_free_tier']);
            $this->assertIsFloat($stats['total_usage']);
            $this->assertIsFloat($stats['remaining']);
            $this->assertIsFloat($stats['usage_percent']);
            
            echo "   ✓ Всего использовано: \${$stats['total_usage']}\n";
            echo "   ✓ Остаток: \${$stats['remaining']}\n";
            echo "   ✓ Использовано: {$stats['usage_percent']}%\n";
            
            $this->recordSuccess($testName, $stats);
        } catch (Exception $e) {
            $this->recordFailure($testName, $e);
        }
        
        echo "\n";
    }

    /**
     * Тест: Получение лимитов запросов
     */
    private function testMetricsGetRateLimits(): void
    {
        $testName = 'OpenRouterMetrics::getRateLimits()';
        echo "⏱️  Тест: {$testName}\n";
        
        try {
            $rateLimits = $this->metrics->getRateLimits();
            
            $this->assertArrayHasKeys($rateLimits, ['requests', 'interval', 'description']);
            $this->assertIsInt($rateLimits['requests']);
            $this->assertIsString($rateLimits['interval']);
            
            echo "   ✓ {$rateLimits['description']}\n";
            
            $this->recordSuccess($testName, $rateLimits);
        } catch (Exception $e) {
            $this->recordFailure($testName, $e);
        }
        
        echo "\n";
    }

    /**
     * Тест: Получение статуса аккаунта
     */
    private function testMetricsGetAccountStatus(): void
    {
        $testName = 'OpenRouterMetrics::getAccountStatus()';
        echo "🔍 Тест: {$testName}\n";
        
        try {
            $status = $this->metrics->getAccountStatus();
            
            $this->assertArrayHasKeys($status, ['key_info', 'balance', 'usage_stats', 'rate_limits']);
            $this->assertIsArray($status['key_info']);
            $this->assertIsFloat($status['balance']);
            $this->assertIsArray($status['usage_stats']);
            
            echo "   ✓ Баланс: \${$status['balance']}\n";
            echo "   ✓ Использовано: {$status['usage_stats']['usage_percent']}%\n";
            
            $this->recordSuccess($testName, $status);
        } catch (Exception $e) {
            $this->recordFailure($testName, $e);
        }
        
        echo "\n";
    }

    /**
     * Тест: Получение списка моделей
     */
    private function testMetricsGetModels(): void
    {
        $testName = 'OpenRouterMetrics::getModels()';
        echo "🤖 Тест: {$testName}\n";
        
        try {
            $models = $this->metrics->getModels();
            
            $this->assertIsArray($models);
            $this->assertGreaterThan(count($models), 0);
            
            if (count($models) > 0) {
                $this->assertArrayHasKeys($models[0], ['id', 'name', 'pricing', 'context_length']);
            }
            
            echo "   ✓ Получено моделей: " . count($models) . "\n";
            
            // Показываем первые 3 модели
            $displayCount = min(3, count($models));
            for ($i = 0; $i < $displayCount; $i++) {
                $model = $models[$i];
                echo "   • {$model['name']} (prompt: \${$model['pricing']['prompt']}/1M)\n";
            }
            
            $this->recordSuccess($testName, ['models_count' => count($models)]);
        } catch (Exception $e) {
            $this->recordFailure($testName, $e);
        }
        
        echo "\n";
    }

    /**
     * Тест: Оценка стоимости
     *
     * @param string $modelId ID модели
     */
    private function testMetricsEstimateCost(string $modelId): void
    {
        $testName = 'OpenRouterMetrics::estimateCost()';
        echo "💵 Тест: {$testName}\n";
        
        try {
            $estimate = $this->metrics->estimateCost($modelId, 100, 50);
            
            $this->assertArrayHasKeys($estimate, ['prompt_cost', 'completion_cost', 'total_cost', 'model', 'tokens']);
            $this->assertIsFloat($estimate['total_cost']);
            
            echo "   ✓ Модель: {$estimate['model']}\n";
            echo "   ✓ Стоимость prompt: \${$estimate['prompt_cost']}\n";
            echo "   ✓ Стоимость completion: \${$estimate['completion_cost']}\n";
            echo "   ✓ Общая стоимость: \${$estimate['total_cost']}\n";
            
            $this->estimatedCost += $estimate['total_cost'];
            
            $this->recordSuccess($testName, $estimate);
        } catch (Exception $e) {
            $this->recordFailure($testName, $e);
        }
        
        echo "\n";
    }

    /**
     * Тест: Проверка достаточности баланса
     */
    private function testMetricsHasEnoughBalance(): void
    {
        $testName = 'OpenRouterMetrics::hasEnoughBalance()';
        echo "✔️  Тест: {$testName}\n";
        
        try {
            $hasBalance = $this->metrics->hasEnoughBalance(0.01);
            
            $this->assertIsBool($hasBalance);
            
            echo "   ✓ Баланс достаточен для $0.01: " . ($hasBalance ? "Да" : "Нет") . "\n";
            
            $this->recordSuccess($testName, ['has_balance' => $hasBalance]);
        } catch (Exception $e) {
            $this->recordFailure($testName, $e);
        }
        
        echo "\n";
    }

    /**
     * Тест: Текстовая генерация
     *
     * @param string $model ID модели
     */
    private function testText2Text(string $model): void
    {
        $testName = 'OpenRouter::text2text()';
        echo "✍️  Тест: {$testName}\n";
        
        try {
            $prompt = "Say 'OK'";
            $response = $this->openRouter->text2text(
                $model,
                $prompt,
                [
                    'max_tokens' => 10,
                    'temperature' => 0.1,
                ]
            );
            
            $this->assertIsString($response);
            $this->assertNotEmpty($response);
            
            echo "   ✓ Модель: {$model}\n";
            echo "   ✓ Промпт: {$prompt}\n";
            echo "   ✓ Ответ: {$response}\n";
            
            $this->recordSuccess($testName, [
                'model' => $model,
                'prompt' => $prompt,
                'response' => $response,
                'response_length' => strlen($response),
            ]);
        } catch (Exception $e) {
            $this->recordFailure($testName, $e);
        }
        
        echo "\n";
    }

    /**
     * Тест: Потоковая передача текста
     *
     * @param string $model ID модели
     */
    private function testTextStream(string $model): void
    {
        $testName = 'OpenRouter::textStream()';
        echo "🌊 Тест: {$testName}\n";
        
        try {
            $prompt = "Count: 1, 2, 3";
            $chunks = [];
            
            echo "   • Получение потока: ";
            
            $this->openRouter->textStream(
                $model,
                $prompt,
                function (string $chunk) use (&$chunks): void {
                    $chunks[] = $chunk;
                    echo ".";
                },
                [
                    'max_tokens' => 20,
                    'temperature' => 0.1,
                ]
            );
            
            echo "\n";
            
            $fullResponse = implode('', $chunks);
            
            $this->assertIsArray($chunks);
            $this->assertGreaterThan(count($chunks), 0);
            $this->assertNotEmpty($fullResponse);
            
            echo "   ✓ Получено чанков: " . count($chunks) . "\n";
            echo "   ✓ Полный ответ: {$fullResponse}\n";
            
            $this->recordSuccess($testName, [
                'model' => $model,
                'prompt' => $prompt,
                'chunks_count' => count($chunks),
                'response' => $fullResponse,
            ]);
        } catch (Exception $e) {
            $this->recordFailure($testName, $e);
        }
        
        echo "\n";
    }

    /**
     * Тест: Валидация параметров
     */
    private function testValidation(): void
    {
        echo "🔒 Тесты валидации:\n";
        
        // Тест 1: Пустая модель
        $testName = 'Validation: Empty model';
        try {
            $this->openRouter->text2text('', 'test');
            $this->recordFailure($testName, new Exception('Валидация не сработала'));
        } catch (OpenRouterValidationException $e) {
            echo "   ✓ Корректно отловлена пустая модель\n";
            $this->recordSuccess($testName, ['exception' => $e->getMessage()]);
        } catch (Exception $e) {
            $this->recordFailure($testName, $e);
        }
        
        // Тест 2: Пустой промпт
        $testName = 'Validation: Empty prompt';
        try {
            $this->openRouter->text2text('some-model', '');
            $this->recordFailure($testName, new Exception('Валидация не сработала'));
        } catch (OpenRouterValidationException $e) {
            echo "   ✓ Корректно отловлен пустой промпт\n";
            $this->recordSuccess($testName, ['exception' => $e->getMessage()]);
        } catch (Exception $e) {
            $this->recordFailure($testName, $e);
        }
        
        // Тест 3: Отрицательные токены в estimateCost
        $testName = 'Validation: Negative tokens';
        try {
            $this->metrics->estimateCost('some-model', -100, 50);
            $this->recordFailure($testName, new Exception('Валидация не сработала'));
        } catch (OpenRouterValidationException $e) {
            echo "   ✓ Корректно отловлены отрицательные токены\n";
            $this->recordSuccess($testName, ['exception' => $e->getMessage()]);
        } catch (Exception $e) {
            $this->recordFailure($testName, $e);
        }
        
        // Тест 4: Отрицательная стоимость в hasEnoughBalance
        $testName = 'Validation: Negative cost';
        try {
            $this->metrics->hasEnoughBalance(-0.01);
            $this->recordFailure($testName, new Exception('Валидация не сработала'));
        } catch (OpenRouterValidationException $e) {
            echo "   ✓ Корректно отловлена отрицательная стоимость\n";
            $this->recordSuccess($testName, ['exception' => $e->getMessage()]);
        } catch (Exception $e) {
            $this->recordFailure($testName, $e);
        }
        
        echo "\n";
    }

    /**
     * Выводит итоговый отчет
     */
    private function printReport(): void
    {
        $successCount = 0;
        $failureCount = 0;
        
        foreach ($this->results as $result) {
            if ($result['success']) {
                $successCount++;
            } else {
                $failureCount++;
            }
        }
        
        $totalTests = $successCount + $failureCount;
        $successRate = $totalTests > 0 ? round(($successCount / $totalTests) * 100, 2) : 0;
        
        echo "╔══════════════════════════════════════════════════════════════════╗\n";
        echo "║                        ИТОГОВЫЙ ОТЧЕТ                            ║\n";
        echo "╚══════════════════════════════════════════════════════════════════╝\n\n";
        
        echo "Всего тестов: {$totalTests}\n";
        echo "✓ Успешно: {$successCount}\n";
        echo "✗ Ошибок: {$failureCount}\n";
        echo "Успешность: {$successRate}%\n";
        echo "Примерная стоимость тестов: \${$this->estimatedCost}\n\n";
        
        if ($failureCount > 0) {
            echo "Ошибки:\n";
            echo str_repeat('-', 70) . "\n";
            foreach ($this->results as $result) {
                if (!$result['success']) {
                    echo "• {$result['test']}\n";
                    echo "  Ошибка: {$result['error']}\n\n";
                }
            }
        }
        
        echo "Логи сохранены в: {$this->logDirectory}\n\n";
        
        $this->logInfo('=== Тестирование завершено ===');
        $this->logInfo("Успешно: {$successCount}/{$totalTests} ({$successRate}%)");
    }

    /**
     * Записывает успешный результат теста
     *
     * @param string $testName Название теста
     * @param mixed $data Данные теста
     */
    private function recordSuccess(string $testName, $data): void
    {
        $this->results[] = [
            'test' => $testName,
            'success' => true,
            'data' => $data,
        ];
        
        $this->logInfo("✓ {$testName} - успешно", is_array($data) ? $data : []);
    }

    /**
     * Записывает неудачный результат теста
     *
     * @param string $testName Название теста
     * @param Exception $exception Исключение
     */
    private function recordFailure(string $testName, Exception $exception): void
    {
        $error = $exception->getMessage();
        
        $this->results[] = [
            'test' => $testName,
            'success' => false,
            'error' => $error,
            'exception_class' => get_class($exception),
        ];
        
        echo "   ✗ Ошибка: {$error}\n";
        
        $this->logError("✗ {$testName} - ошибка: {$error}", [
            'exception_class' => get_class($exception),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Проверяет наличие ключей в массиве
     *
     * @param array<string, mixed> $array Массив
     * @param array<int, string> $keys Ключи
     * @throws Exception Если ключ отсутствует
     */
    private function assertArrayHasKeys(array $array, array $keys): void
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $array)) {
                throw new Exception("Ключ '{$key}' отсутствует в массиве");
            }
        }
    }

    /**
     * Проверяет, что значение является строкой
     *
     * @param mixed $value Значение
     * @throws Exception Если не строка
     */
    private function assertIsString($value): void
    {
        if (!is_string($value)) {
            throw new Exception('Значение должно быть строкой');
        }
    }

    /**
     * Проверяет, что значение является числом
     *
     * @param mixed $value Значение
     * @throws Exception Если не число
     */
    private function assertIsFloat($value): void
    {
        if (!is_float($value) && !is_int($value)) {
            throw new Exception('Значение должно быть числом');
        }
    }

    /**
     * Проверяет, что значение является целым числом
     *
     * @param mixed $value Значение
     * @throws Exception Если не целое число
     */
    private function assertIsInt($value): void
    {
        if (!is_int($value)) {
            throw new Exception('Значение должно быть целым числом');
        }
    }

    /**
     * Проверяет, что значение является массивом
     *
     * @param mixed $value Значение
     * @throws Exception Если не массив
     */
    private function assertIsArray($value): void
    {
        if (!is_array($value)) {
            throw new Exception('Значение должно быть массивом');
        }
    }

    /**
     * Проверяет, что значение является булевым
     *
     * @param mixed $value Значение
     * @throws Exception Если не булево
     */
    private function assertIsBool($value): void
    {
        if (!is_bool($value)) {
            throw new Exception('Значение должно быть булевым');
        }
    }

    /**
     * Проверяет, что строка не пустая
     *
     * @param string $value Значение
     * @throws Exception Если пустая
     */
    private function assertNotEmpty(string $value): void
    {
        if (trim($value) === '') {
            throw new Exception('Строка не должна быть пустой');
        }
    }

    /**
     * Проверяет, что число больше заданного
     *
     * @param int|float $value Значение
     * @param int|float $min Минимум
     * @throws Exception Если меньше
     */
    private function assertGreaterThan($value, $min): void
    {
        if ($value <= $min) {
            throw new Exception("Значение {$value} должно быть больше {$min}");
        }
    }

    /**
     * Логирует информационное сообщение
     *
     * @param string $message Сообщение
     * @param array<string, mixed> $context Контекст
     */
    private function logInfo(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    /**
     * Логирует ошибку
     *
     * @param string $message Сообщение
     * @param array<string, mixed> $context Контекст
     */
    private function logError(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }
}

// Запуск тестов
if (php_sapi_name() === 'cli') {
    $apiKey = $argv[1] ?? '';
    
    if (empty($apiKey)) {
        echo "❌ Ошибка: API ключ не указан\n";
        echo "Использование: php OpenRouterFullTest.php <api-key>\n";
        exit(1);
    }
    
    try {
        $test = new OpenRouterFullTest($apiKey);
        $test->runAllTests();
    } catch (Exception $e) {
        echo "❌ Критическая ошибка: {$e->getMessage()}\n";
        echo "Класс: " . get_class($e) . "\n";
        exit(1);
    }
}

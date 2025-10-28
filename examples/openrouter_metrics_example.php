<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use App\Component\Logger;
use App\Component\OpenRouterMetrics;
use App\Config\ConfigLoader;

/**
 * Пример использования класса OpenRouterMetrics для работы с метриками OpenRouter API
 * 
 * Демонстрирует:
 * - Получение информации о API ключе
 * - Проверку баланса и лимитов
 * - Получение списка доступных моделей
 * - Оценку стоимости запросов
 * - Получение информации о генерациях
 */

try {
    echo "=== Пример работы с метриками OpenRouter API ===\n\n";

    // Инициализация логгера
    $loggerConfig = ConfigLoader::load(__DIR__ . '/../config/logger.json');
    $logger = new Logger($loggerConfig);

    // Инициализация OpenRouterMetrics
    $config = ConfigLoader::load(__DIR__ . '/../config/openrouter.json');
    $metrics = new OpenRouterMetrics($config, $logger);

    // 1. Получение информации о ключе API
    echo "1. Информация о API ключе:\n";
    echo str_repeat('-', 50) . "\n";
    
    $keyInfo = $metrics->getKeyInfo();
    echo "Название ключа: {$keyInfo['label']}\n";
    echo "Использовано: \${$keyInfo['usage']}\n";
    echo "Лимит: " . ($keyInfo['limit'] !== null ? "\${$keyInfo['limit']}" : "Без лимита") . "\n";
    echo "Бесплатный уровень: " . ($keyInfo['is_free_tier'] ? "Да" : "Нет") . "\n";
    echo "Rate limit: {$keyInfo['rate_limit']['requests']} запросов за {$keyInfo['rate_limit']['interval']}\n";
    echo "\n";

    // 2. Проверка баланса
    echo "2. Баланс аккаунта:\n";
    echo str_repeat('-', 50) . "\n";
    
    $balance = $metrics->getBalance();
    if ($balance >= 0) {
        echo "Доступный баланс: \${$balance}\n";
    } else {
        echo "Использовано (без лимита): \$" . abs($balance) . "\n";
    }
    echo "\n";

    // 3. Статистика использования
    echo "3. Статистика использования:\n";
    echo str_repeat('-', 50) . "\n";
    
    $usageStats = $metrics->getUsageStats();
    echo "Всего использовано: \${$usageStats['total_usage']}\n";
    echo "Лимит: " . ($usageStats['limit'] !== null ? "\${$usageStats['limit']}" : "Без лимита") . "\n";
    echo "Остаток: \${$usageStats['remaining']}\n";
    echo "Использовано: {$usageStats['usage_percent']}%\n";
    echo "\n";

    // 4. Rate limits
    echo "4. Лимиты запросов (Rate Limits):\n";
    echo str_repeat('-', 50) . "\n";
    
    $rateLimits = $metrics->getRateLimits();
    echo "Лимит: {$rateLimits['description']}\n";
    echo "\n";

    // 5. Список доступных моделей (первые 5)
    echo "5. Доступные модели (первые 5):\n";
    echo str_repeat('-', 50) . "\n";
    
    $models = $metrics->getModels();
    $displayCount = min(5, count($models));
    
    for ($i = 0; $i < $displayCount; $i++) {
        $model = $models[$i];
        echo "\nМодель #{$i + 1}:\n";
        echo "  ID: {$model['id']}\n";
        echo "  Название: {$model['name']}\n";
        echo "  Описание: " . substr($model['description'], 0, 80) . "...\n";
        echo "  Контекст: {$model['context_length']} токенов\n";
        echo "  Стоимость prompt: \${$model['pricing']['prompt']} за 1M токенов\n";
        echo "  Стоимость completion: \${$model['pricing']['completion']} за 1M токенов\n";
    }
    
    echo "\nВсего доступно моделей: " . count($models) . "\n\n";

    // 6. Информация о конкретной модели
    echo "6. Информация о конкретной модели:\n";
    echo str_repeat('-', 50) . "\n";
    
    $modelId = 'openai/gpt-3.5-turbo';
    
    try {
        $modelInfo = $metrics->getModelInfo($modelId);
        echo "Модель: {$modelInfo['name']}\n";
        echo "ID: {$modelInfo['id']}\n";
        echo "Максимальный контекст: {$modelInfo['context_length']} токенов\n";
        echo "Модальность: {$modelInfo['architecture']['modality']}\n";
        echo "Стоимость prompt: \${$modelInfo['pricing']['prompt']} за 1M токенов\n";
        echo "Стоимость completion: \${$modelInfo['pricing']['completion']} за 1M токенов\n";
    } catch (Exception $e) {
        echo "Ошибка получения информации о модели: {$e->getMessage()}\n";
    }
    echo "\n";

    // 7. Оценка стоимости запроса
    echo "7. Оценка стоимости запроса:\n";
    echo str_repeat('-', 50) . "\n";
    
    $promptTokens = 1000;
    $completionTokens = 500;
    
    try {
        $costEstimate = $metrics->estimateCost($modelId, $promptTokens, $completionTokens);
        echo "Модель: {$costEstimate['model']}\n";
        echo "Токенов в запросе: {$costEstimate['tokens']['prompt']}\n";
        echo "Токенов в ответе: {$costEstimate['tokens']['completion']}\n";
        echo "Всего токенов: {$costEstimate['tokens']['total']}\n";
        echo "Стоимость запроса: \${$costEstimate['prompt_cost']}\n";
        echo "Стоимость ответа: \${$costEstimate['completion_cost']}\n";
        echo "Общая стоимость: \${$costEstimate['total_cost']}\n";
        
        // Проверка достаточности баланса
        $hasBalance = $metrics->hasEnoughBalance($costEstimate['total_cost']);
        echo "Достаточно баланса: " . ($hasBalance ? "Да" : "Нет") . "\n";
    } catch (Exception $e) {
        echo "Ошибка оценки стоимости: {$e->getMessage()}\n";
    }
    echo "\n";

    // 8. Полная информация об аккаунте
    echo "8. Полная информация об аккаунте:\n";
    echo str_repeat('-', 50) . "\n";
    
    $accountStatus = $metrics->getAccountStatus();
    echo "Ключ: {$accountStatus['key_info']['label']}\n";
    echo "Баланс: \${$accountStatus['balance']}\n";
    echo "Использовано: {$accountStatus['usage_stats']['usage_percent']}%\n";
    echo "Rate limit: {$accountStatus['rate_limits']['description']}\n";
    echo "\n";

    // 9. Пример получения информации о генерации (требует реальный ID)
    echo "9. Информация о генерации:\n";
    echo str_repeat('-', 50) . "\n";
    echo "Для получения информации о конкретной генерации используйте:\n";
    echo "\$generationInfo = \$metrics->getGenerationInfo(\$generationId);\n";
    echo "где \$generationId - это значение из заголовка X-Request-Id ответа API\n";
    echo "\n";

    // Пример использования с реальным ID (закомментирован)
    /*
    $generationId = 'gen_your_generation_id_here';
    try {
        $generationInfo = $metrics->getGenerationInfo($generationId);
        echo "ID генерации: {$generationInfo['id']}\n";
        echo "Модель: {$generationInfo['model']}\n";
        echo "Создано: {$generationInfo['created_at']}\n";
        echo "Токенов в запросе: {$generationInfo['usage']['prompt_tokens']}\n";
        echo "Токенов в ответе: {$generationInfo['usage']['completion_tokens']}\n";
        echo "Всего токенов: {$generationInfo['usage']['total_tokens']}\n";
        echo "Стоимость: \${$generationInfo['cost']}\n";
    } catch (Exception $e) {
        echo "Ошибка получения информации о генерации: {$e->getMessage()}\n";
    }
    */

    echo "=== Пример завершён успешно ===\n";

} catch (Exception $e) {
    echo "Ошибка: {$e->getMessage()}\n";
    echo "Класс исключения: " . get_class($e) . "\n";
    
    if ($e->getPrevious() !== null) {
        echo "Предыдущее исключение: {$e->getPrevious()->getMessage()}\n";
    }
    
    exit(1);
}

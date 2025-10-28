#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use App\Component\Logger;
use App\Component\OpenRouterClient;
use App\Config\ConfigLoader;
use App\Component\Exception\OpenRouterException;

/**
 * Пример использования OpenRouterClient для получения информации о балансе,
 * использованных токенах и статистике аккаунта OpenRouter
 */

echo "=== Пример работы с OpenRouterClient ===\n\n";

try {
    $loggerConfig = ConfigLoader::load(__DIR__ . '/../config/logger.json');
    $logger = new Logger($loggerConfig);

    $config = ConfigLoader::load(__DIR__ . '/../config/openrouter.json');
    $client = new OpenRouterClient($config, $logger);

    echo "1. Проверка валидности API ключа...\n";
    $isValid = $client->validateApiKey();
    echo "   API ключ " . ($isValid ? "валидный ✓" : "невалидный ✗") . "\n\n";

    if (!$isValid) {
        echo "Проверьте настройки API ключа в config/openrouter.json\n";
        exit(1);
    }

    echo "2. Получение информации об аккаунте...\n";
    $keyInfo = $client->getKeyInfo();
    echo "   Название ключа: " . ($keyInfo['label'] ?? 'N/A') . "\n";
    echo "   Использовано: $" . number_format($keyInfo['usage'] ?? 0, 4) . "\n";
    
    if (isset($keyInfo['limit'])) {
        echo "   Лимит: $" . number_format($keyInfo['limit'], 2) . "\n";
    } else {
        echo "   Лимит: Безлимитный\n";
    }
    
    echo "   Бесплатный аккаунт: " . (($keyInfo['is_free_tier'] ?? false) ? "Да" : "Нет") . "\n\n";

    echo "3. Получение текущего баланса...\n";
    $balance = $client->getBalance();
    echo "   Баланс: $" . number_format($balance, 2) . "\n\n";

    echo "4. Получение статистики использования...\n";
    $stats = $client->getUsageStats();
    echo "   Всего использовано: $" . number_format($stats['total_usage_usd'], 4) . "\n";
    
    if ($stats['limit_usd'] !== null) {
        echo "   Лимит: $" . number_format($stats['limit_usd'], 2) . "\n";
        echo "   Осталось: $" . number_format($stats['remaining_usd'], 2) . "\n";
        echo "   Использовано: " . number_format($stats['usage_percentage'], 2) . "%\n";
    } else {
        echo "   Безлимитный аккаунт\n";
    }
    
    echo "   Бесплатный тариф: " . ($stats['is_free_tier'] ? "Да" : "Нет") . "\n\n";

    echo "5. Получение информации о лимитах запросов...\n";
    $rateLimits = $client->getRateLimits();
    
    if ($rateLimits['requests_per_minute'] !== null) {
        echo "   Запросов в минуту: " . $rateLimits['requests_per_minute'] . "\n";
    }
    
    if ($rateLimits['requests_per_day'] !== null) {
        echo "   Запросов в день: " . $rateLimits['requests_per_day'] . "\n";
    }
    
    if ($rateLimits['tokens_per_minute'] !== null) {
        echo "   Токенов в минуту: " . $rateLimits['tokens_per_minute'] . "\n";
    }
    
    if (empty(array_filter($rateLimits))) {
        echo "   Лимиты не установлены\n";
    }
    
    echo "\n";

    echo "6. Получение списка доступных моделей...\n";
    $models = $client->getModels();
    echo "   Всего доступно моделей: " . count($models) . "\n";
    
    if (count($models) > 0) {
        echo "   Примеры моделей:\n";
        $displayedCount = 0;
        foreach ($models as $model) {
            if ($displayedCount >= 5) {
                break;
            }
            
            $modelId = $model['id'] ?? 'unknown';
            $modelName = $model['name'] ?? 'Unknown';
            $contextLength = $model['context_length'] ?? 0;
            
            echo "   - $modelId ($modelName) - контекст: " . number_format($contextLength) . " токенов\n";
            $displayedCount++;
        }
        
        if (count($models) > 5) {
            echo "   ... и ещё " . (count($models) - 5) . " моделей\n";
        }
    }
    
    echo "\n";

    echo "7. Получение детальной информации о модели GPT-3.5-turbo...\n";
    try {
        $modelInfo = $client->getModelInfo('openai/gpt-3.5-turbo');
        echo "   ID: " . ($modelInfo['id'] ?? 'N/A') . "\n";
        echo "   Название: " . ($modelInfo['name'] ?? 'N/A') . "\n";
        echo "   Контекст: " . number_format($modelInfo['context_length'] ?? 0) . " токенов\n";
        
        if (isset($modelInfo['pricing'])) {
            $pricing = $modelInfo['pricing'];
            echo "   Цена промпта: $" . number_format($pricing['prompt'] ?? 0, 6) . " за 1M токенов\n";
            echo "   Цена ответа: $" . number_format($pricing['completion'] ?? 0, 6) . " за 1M токенов\n";
        }
    } catch (OpenRouterException $e) {
        echo "   Модель не найдена: " . $e->getMessage() . "\n";
    }
    
    echo "\n";

    echo "8. Расчёт стоимости запроса...\n";
    echo "   Пример: 1000 токенов промпта + 500 токенов ответа для gpt-3.5-turbo\n";
    try {
        $cost = $client->calculateCost('openai/gpt-3.5-turbo', 1000, 500);
        echo "   Модель: " . $cost['model_id'] . "\n";
        echo "   Токенов промпта: " . $cost['prompt_tokens'] . "\n";
        echo "   Токенов ответа: " . $cost['completion_tokens'] . "\n";
        echo "   Всего токенов: " . $cost['total_tokens'] . "\n";
        echo "   Стоимость промпта: $" . number_format($cost['prompt_cost_usd'], 6) . "\n";
        echo "   Стоимость ответа: $" . number_format($cost['completion_cost_usd'], 6) . "\n";
        echo "   Общая стоимость: $" . number_format($cost['total_cost_usd'], 6) . "\n";
    } catch (OpenRouterException $e) {
        echo "   Ошибка расчёта: " . $e->getMessage() . "\n";
    }
    
    echo "\n";

    echo "9. Пример получения информации о конкретной генерации (по ID)...\n";
    echo "   Для получения информации о генерации нужен её ID\n";
    echo "   ID можно получить из заголовка X-OpenRouter-Generation-Id при запросе\n";
    echo "   Пример использования:\n";
    echo "   \$generationId = 'gen_abc123xyz';\n";
    echo "   \$info = \$client->getGenerationInfo(\$generationId);\n";
    echo "   print_r(\$info);\n\n";

    echo "=== Все операции выполнены успешно! ===\n";

} catch (OpenRouterException $e) {
    echo "\n❌ Ошибка OpenRouter: " . $e->getMessage() . "\n";
    
    if ($e instanceof \App\Component\Exception\OpenRouterApiException) {
        echo "   Код статуса: " . $e->getStatusCode() . "\n";
        echo "   Ответ сервера: " . $e->getResponseBody() . "\n";
    }
    
    exit(1);
} catch (Exception $e) {
    echo "\n❌ Общая ошибка: " . $e->getMessage() . "\n";
    exit(1);
}

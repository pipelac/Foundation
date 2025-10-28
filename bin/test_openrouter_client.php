#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use App\Component\OpenRouterClient;

/**
 * Простой тест для проверки загрузки класса OpenRouterClient
 */

echo "=== Тест загрузки класса OpenRouterClient ===\n\n";

try {
    // Проверка, что класс существует и может быть загружен
    if (!class_exists(OpenRouterClient::class)) {
        echo "❌ Класс OpenRouterClient не найден!\n";
        exit(1);
    }
    
    echo "✓ Класс OpenRouterClient успешно загружен\n";
    
    // Проверка обязательных методов
    $requiredMethods = [
        'validateApiKey',
        'getKeyInfo',
        'getBalance',
        'getUsageStats',
        'getRateLimits',
        'getModels',
        'getModelInfo',
        'calculateCost',
        'getGenerationInfo',
    ];
    
    $reflection = new ReflectionClass(OpenRouterClient::class);
    $missingMethods = [];
    
    foreach ($requiredMethods as $method) {
        if (!$reflection->hasMethod($method)) {
            $missingMethods[] = $method;
        }
    }
    
    if (!empty($missingMethods)) {
        echo "❌ Отсутствуют методы: " . implode(', ', $missingMethods) . "\n";
        exit(1);
    }
    
    echo "✓ Все необходимые методы присутствуют\n";
    
    // Проверка, что конструктор требует конфигурацию
    try {
        new OpenRouterClient([]);
        echo "❌ Конструктор должен требовать api_key!\n";
        exit(1);
    } catch (Exception $e) {
        echo "✓ Валидация конфигурации работает корректно\n";
    }
    
    echo "\n=== Все тесты пройдены успешно! ===\n";
    
} catch (Exception $e) {
    echo "\n❌ Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}

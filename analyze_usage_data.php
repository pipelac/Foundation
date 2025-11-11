<?php

declare(strict_types=1);

/**
 * Анализ usage полей из реального ответа OpenRouter API
 */

echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║            АНАЛИЗ ПОЛЕЙ usage_* ИЗ OPENROUTER API                        ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n\n";

// Реальный ответ из вашего примера
$generationData = [
    "id" => 29578990472,
    "generation_id" => "gen-1762889236-JMrQSCLRK12sLq3L6xGe",
    "provider_name" => "DeepInfra",
    "model" => "google/gemma-3-27b-it",
    "tokens_prompt" => 3483,
    "tokens_completion" => 288,
    "native_tokens_prompt" => 3637,
    "native_tokens_completion" => 341,
    "usage" => 0.0003780711,           // ← ОБЩАЯ СТОИМОСТЬ
    "usage_cache" => null,
    "usage_data" => -0.0000038189,     // ← ОТРИЦАТЕЛЬНОЕ! (компенсация)
    "usage_web" => null,
    "usage_file" => 0,
];

echo "📊 ДАННЫЕ ИЗ РЕАЛЬНОГО ОТВЕТА API:\n\n";

printf("usage:        %s USD\n", number_format($generationData['usage'], 10));
printf("usage_cache:  %s\n", $generationData['usage_cache'] ?? 'null');
printf("usage_data:   %s USD  ← ОТРИЦАТЕЛЬНОЕ!\n", number_format($generationData['usage_data'], 10));
printf("usage_web:    %s\n", $generationData['usage_web'] ?? 'null');
printf("usage_file:   %s USD\n", number_format($generationData['usage_file'], 10));

echo "\n" . str_repeat("─", 75) . "\n\n";

echo "🔍 АНАЛИЗ:\n\n";

echo "1. usage_data = " . $generationData['usage_data'] . " (ОТРИЦАТЕЛЬНОЕ)\n";
echo "   Это означает, что это УЖЕ компенсация/скидка от OpenRouter\n\n";

echo "2. Если usage_data отрицательное, то это уже вычет из стоимости\n";
echo "   OpenRouter возвращает скидки как отрицательные значения!\n\n";

echo str_repeat("─", 75) . "\n\n";

echo "💡 ПРАВИЛЬНАЯ ФОРМУЛА:\n\n";

$usageTotal = $generationData['usage'];
$usageData = $generationData['usage_data'];

echo "Вариант 1 (если usage_data отрицательное - просто сложить):\n";
$finalCost1 = $usageTotal + $usageData;  // Сложение, так как usage_data уже отрицательное
printf("   final_cost = usage + usage_data\n");
printf("   final_cost = %.10f + (%.10f)\n", $usageTotal, $usageData);
printf("   final_cost = %.10f USD\n", $finalCost1);

echo "\n";

echo "Вариант 2 (если вычитать абсолютное значение):\n";
$finalCost2 = $usageTotal - abs($usageData);
printf("   final_cost = usage - abs(usage_data)\n");
printf("   final_cost = %.10f - %.10f\n", $usageTotal, abs($usageData));
printf("   final_cost = %.10f USD\n", $finalCost2);

echo "\n";
echo "✅ Оба варианта дают одинаковый результат!\n";
echo "   Рекомендуется: final_cost = usage + usage_data (проще)\n\n";

echo str_repeat("─", 75) . "\n\n";

echo "📝 ИТОГОВАЯ СТОИМОСТЬ:\n\n";
printf("   Gross cost (usage):          $ %.10f\n", $usageTotal);
printf("   Compensation (usage_data):   $ %.10f\n", $usageData);
printf("   ─────────────────────────────────────\n");
printf("   Net cost (final_cost):       $ %.10f\n\n", $finalCost1);

echo str_repeat("─", 75) . "\n\n";

echo "🎯 ВЫВОДЫ:\n\n";
echo "1. usage_data приходит со знаком МИНУС если есть компенсация\n";
echo "2. usage_cache, usage_web, usage_file - тоже могут быть отрицательными\n";
echo "3. Правильная формула: final_cost = usage + usage_data + usage_cache + usage_web + usage_file\n";
echo "4. Все поля со скидками уже отрицательные, поэтому просто суммируем\n\n";

echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║                    РЕКОМЕНДУЕМАЯ ФОРМУЛА                                 ║\n";
echo "║                                                                           ║\n";
echo "║  final_cost = usage + (usage_data ?? 0) + (usage_cache ?? 0)            ║\n";
echo "║                    + (usage_web ?? 0) + (usage_file ?? 0)               ║\n";
echo "║                                                                           ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n";

<?php

declare(strict_types=1);

/**
 * Тест схожести текстов через Simhash
 * 
 * Проверяет степень схожести оригинального текста с его переписанными вариантами.
 * Использует алгоритм Simhash и расстояние Хэмминга для измерения различий.
 * 
 * Использует библиотеку swatchion/simhash v1.0.0 напрямую для простоты.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Swatchion\SimHash\SimHash;

// Тексты для тестирования
$original = "Стало известно о тайной поставке ракет Storm Shadow Украине
Bloomberg: Великобритания передала Украине дополнительные ракеты Storm ShadowФото: Benoit Tessier / ReutersПравительство Великобритании передало Украине дополнительные крылатые ракеты Storm Shadow. Об этом сообщает Bloomberg со ссылкой на источники.«Поставка неуказанного количества ракет была осуществлена ​​для того, чтобы обеспечить Украину запасами в преддверии зимних месяцев», — сказано в сообщении.Ранее президент США Дональд Трамп заявил, что не рассматривает вопрос передачи Украине ракет Tomahawk.";

$variants = [
    1 => "Bloomberg: Британия тайно пополнила запасы Украины ракетами Storm Shadow

Правительство Великобритании передало Украине дополнительную партию крылатых ракет Storm Shadow, сообщает Bloomberg со ссылкой на источники. Количество не раскрывается; поставка призвана укрепить арсенал Киева перед зимним периодом. Ранее президент США Дональд Трамп заявил, что не рассматривает передачу Украине ракет Tomahawk.",

    2 => "Лондон поставил Киеву новые Storm Shadow перед зимними месяцами — Bloomberg

По данным агентства, Великобритания без огласки направила Украине дополнительные крылатые ракеты Storm Shadow. Объем не уточняется; цель — обеспечить запасы на зиму. Президент США Дональд Трамп ранее отметил, что вопрос о поставках Tomahawk Украине не стоит.",

    3 => "Источники: Великобритания передала Украине дополнительную партию Storm Shadow

Bloomberg сообщает, что Лондон отправил Украине крылатые ракеты Storm Shadow, не раскрывая их количество. Поставка призвана усилить ракетный потенциал Киева перед зимним сезоном. Ранее президент Дональд Трамп исключил передачу Украине Tomahawk.",

    4 => "Великобритания усилила арсенал Украины Storm Shadow, детали поставки не раскрыты

Как пишет Bloomberg со ссылкой на источники, дополнительная партия ракет Storm Shadow уже передана Украине. Поставка нацелена на поддержание запасов в преддверии зимы. Президент США Дональд Трамп ранее говорил, что не рассматривает передачу Tomahawk Киеву.",

    5 => "Bloomberg: «тихая» поставка Storm Shadow из Великобритании Украине

По данным агентства, британское правительство без огласки отправило Украине дополнительные крылатые ракеты Storm Shadow. Количество не называется; задача — обеспечить резервы на зиму. Президент США Дональд Трамп заявлял, что поставок Tomahawk Украине не планируется.",
];

/**
 * Вычисляет Simhash для текста
 */
function calculateSimhash(string $text): string
{
    if (empty(trim($text))) {
        return str_repeat('0', 64);
    }
    
    $simhash = new SimHash();
    return $simhash->hash($text);
}

/**
 * Вычисляет расстояние Хэмминга между двумя Simhash значениями
 */
function getHammingDistance(string $hash1, string $hash2): int
{
    if (strlen($hash1) !== strlen($hash2)) {
        return PHP_INT_MAX;
    }
    
    $distance = 0;
    $length = strlen($hash1);
    
    for ($i = 0; $i < $length; $i++) {
        if ($hash1[$i] !== $hash2[$i]) {
            $distance++;
        }
    }
    
    return $distance;
}

try {
    echo "\n";
    echo "==========================================================\n";
    echo "  ТЕСТ СХОЖЕСТИ ТЕКСТОВ ЧЕРЕЗ SIMHASH\n";
    echo "==========================================================\n\n";
    
    // Вычисляем Simhash для оригинала
    echo "1. ОРИГИНАЛЬНЫЙ ТЕКСТ\n";
    echo "   Длина: " . strlen($original) . " символов\n";
    $originalHash = calculateSimhash($original);
    echo "   Simhash: " . $originalHash . "\n\n";

    // Таблица результатов
    echo "2. АНАЛИЗ СХОЖЕСТИ ВАРИАНТОВ\n\n";
    echo str_pad("Вариант", 10) . " | ";
    echo str_pad("Длина", 10) . " | ";
    echo str_pad("Hamming Dist", 15) . " | ";
    echo str_pad("Схожесть %", 12) . " | ";
    echo "Оценка\n";
    echo str_repeat("-", 85) . "\n";

    $results = [];

    foreach ($variants as $num => $text) {
        // Вычисляем Simhash для варианта
        $variantHash = calculateSimhash($text);
        
        // Вычисляем расстояние Хэмминга
        $hammingDistance = getHammingDistance($originalHash, $variantHash);
        
        // Вычисляем процент схожести (64 бита - максимум)
        $similarity = (1 - $hammingDistance / 64) * 100;
        
        // Оценка схожести
        $assessment = match(true) {
            $hammingDistance <= 1 => "Идентичны",
            $hammingDistance <= 3 => "Очень похожи",
            $hammingDistance <= 6 => "Похожи",
            $hammingDistance <= 10 => "Умеренно похожи",
            default => "Разные"
        };

        $results[$num] = [
            'length' => strlen($text),
            'hash' => $variantHash,
            'distance' => $hammingDistance,
            'similarity' => $similarity,
            'assessment' => $assessment,
        ];

        // Вывод строки таблицы
        echo str_pad("#$num", 10) . " | ";
        echo str_pad((string)$results[$num]['length'], 10) . " | ";
        echo str_pad((string)$results[$num]['distance'], 15) . " | ";
        echo str_pad(number_format($similarity, 2) . "%", 12) . " | ";
        echo $assessment . "\n";
    }

    echo str_repeat("-", 85) . "\n\n";

    // Детальная информация по каждому варианту
    echo "3. ДЕТАЛЬНЫЙ АНАЛИЗ\n\n";

    foreach ($variants as $num => $text) {
        echo "ВАРИАНТ #$num\n";
        echo str_repeat("-", 60) . "\n";
        echo "Текст: " . mb_substr($text, 0, 100) . "...\n";
        echo "Длина: {$results[$num]['length']} символов\n";
        echo "Simhash: {$results[$num]['hash']}\n";
        echo "Hamming Distance: {$results[$num]['distance']} бит\n";
        echo "Схожесть: " . number_format($results[$num]['similarity'], 2) . "%\n";
        echo "Оценка: {$results[$num]['assessment']}\n";
        
        // Битовое сравнение (показываем первые 32 и последние 32 бита)
        echo "Битовое сравнение:\n";
        echo "  Оригинал: " . substr($originalHash, 0, 32) . "..." . substr($originalHash, -32) . "\n";
        echo "  Вариант:  " . substr($results[$num]['hash'], 0, 32) . "..." . substr($results[$num]['hash'], -32) . "\n";
        
        // Показываем позиции различающихся битов
        $diffPositions = [];
        for ($i = 0; $i < 64; $i++) {
            if ($originalHash[$i] !== $results[$num]['hash'][$i]) {
                $diffPositions[] = $i;
            }
        }
        echo "  Различия в битах: ";
        if (empty($diffPositions)) {
            echo "нет";
        } else {
            echo implode(", ", array_slice($diffPositions, 0, 10));
            if (count($diffPositions) > 10) {
                echo "... (всего " . count($diffPositions) . ")";
            }
        }
        echo "\n\n";
    }

    // Итоги
    echo "4. ИТОГИ ТЕСТИРОВАНИЯ\n";
    echo str_repeat("=", 60) . "\n";

    $avgDistance = array_sum(array_column($results, 'distance')) / count($results);
    $avgSimilarity = array_sum(array_column($results, 'similarity')) / count($results);

    echo "Всего вариантов протестировано: " . count($variants) . "\n";
    echo "Средняя Hamming Distance: " . number_format($avgDistance, 2) . " бит\n";
    echo "Средняя схожесть: " . number_format($avgSimilarity, 2) . "%\n\n";

    // Распределение по категориям
    $categories = array_count_values(array_column($results, 'assessment'));
    echo "Распределение по категориям:\n";
    foreach ($categories as $category => $count) {
        echo "  - $category: $count\n";
    }

    echo "\n";
    echo "ПОРОГИ СХОЖЕСТИ (рекомендации для RSS2TLG):\n";
    echo "  0-1 бит:  Идентичны (дубликат 100%)\n";
    echo "  2-3 бит:  Очень похожи (вероятный дубликат) ✓ Рекомендованный порог\n";
    echo "  4-6 бит:  Похожи (может быть дубликатом)\n";
    echo "  7-10 бит: Умеренно похожи (разные тексты на одну тему)\n";
    echo "  11+ бит:  Разные (разные новости)\n";

    echo "\n";
    echo "==========================================================\n";
    echo "  ТЕСТ ЗАВЕРШЕН УСПЕШНО ✓\n";
    echo "==========================================================\n\n";

} catch (\Exception $e) {
    echo "\n❌ ОШИБКА: " . $e->getMessage() . "\n";
    echo "Трассировка:\n" . $e->getTraceAsString() . "\n\n";
    exit(1);
}

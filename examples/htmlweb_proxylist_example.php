<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use App\Component\Logger;
use App\Component\ProxyPool;
use App\Component\htmlWebProxyList;
use App\Component\Exception\HtmlWebProxyListException;
use App\Component\Exception\HtmlWebProxyListValidationException;

echo "=== Примеры использования htmlWebProxyList ===\n\n";

// ВАЖНО: Замените 'YOUR_API_KEY_HERE' на ваш реальный API ключ из профиля htmlweb.ru
$apiKey = 'YOUR_API_KEY_HERE';

// Пример 1: Базовое использование - получение списка прокси
echo "=== Пример 1: Базовое получение списка прокси ===\n\n";

try {
    $logger = new Logger([
        'directory' => __DIR__ . '/../logs',
        'file_name' => 'htmlweb_proxylist.log',
        'enabled' => true,
    ]);
    
    $htmlWebProxy = new htmlWebProxyList($apiKey, [
        'perpage' => 10,
        'type' => 'HTTP',
    ], $logger);
    
    echo "✓ htmlWebProxyList инициализирован\n";
    echo "Параметры: " . json_encode($htmlWebProxy->getParams()) . "\n";
    
    echo "Получение списка прокси...\n";
    $proxies = $htmlWebProxy->getProxies();
    
    echo "✓ Получено прокси: " . count($proxies) . "\n";
    echo "✓ Остаток запросов: " . ($htmlWebProxy->getRemainingLimit() ?? 'неизвестно') . "\n";
    
    if (count($proxies) > 0) {
        echo "Примеры прокси:\n";
        foreach (array_slice($proxies, 0, 5) as $proxy) {
            echo "  - {$proxy}\n";
        }
    }
    
    echo "\n";
    
} catch (HtmlWebProxyListException $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n\n";
}


// Пример 2: Фильтрация по стране
echo "=== Пример 2: Фильтрация прокси по стране ===\n\n";

try {
    $htmlWebProxy = new htmlWebProxyList($apiKey, [
        'country' => 'RU',
        'perpage' => 20,
        'type' => 'HTTP',
        'work' => 1, // 1 - работает из России
    ]);
    
    echo "✓ Конфигурация: прокси из России, только рабочие\n";
    
    $proxies = $htmlWebProxy->getProxies();
    echo "✓ Получено российских прокси: " . count($proxies) . "\n";
    echo "✓ Остаток запросов: " . ($htmlWebProxy->getRemainingLimit() ?? 'неизвестно') . "\n\n";
    
} catch (HtmlWebProxyListException $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n\n";
}


// Пример 3: Использование краткого формата short=4
echo "=== Пример 3: Краткий формат вывода (short=4) ===\n\n";

try {
    $htmlWebProxy = new htmlWebProxyList($apiKey, [
        'perpage' => 15,
        'type' => 'SOCKS5',
        'short' => 4, // Текстовый список IP:PORT
    ]);
    
    echo "✓ Конфигурация: SOCKS5 прокси, краткий формат (short=4)\n";
    
    $proxies = $htmlWebProxy->getProxies();
    echo "✓ Получено SOCKS5 прокси: " . count($proxies) . "\n\n";
    
} catch (HtmlWebProxyListException $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n\n";
}


// Пример 4: Использование формата short=2 (с протоколами)
echo "=== Пример 4: Краткий формат с протоколами (short=2) ===\n\n";

try {
    $htmlWebProxy = new htmlWebProxyList($apiKey, [
        'perpage' => 10,
        'type' => 'HTTPS',
        'short' => 2, // Список с протоколами: protocol://IP:PORT
    ]);
    
    echo "✓ Конфигурация: HTTPS прокси, формат с протоколами\n";
    
    $proxies = $htmlWebProxy->getProxies();
    echo "✓ Получено прокси: " . count($proxies) . "\n\n";
    
} catch (HtmlWebProxyListException $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n\n";
}


// Пример 5: Интеграция с ProxyPool
echo "=== Пример 5: Загрузка прокси в ProxyPool ===\n\n";

try {
    $logger = new Logger([
        'directory' => __DIR__ . '/../logs',
        'file_name' => 'proxypool_integration.log',
        'enabled' => true,
    ]);
    
    $proxyPool = new ProxyPool([
        'rotation_strategy' => ProxyPool::ROTATION_ROUND_ROBIN,
        'auto_health_check' => false,
    ], $logger);
    
    $htmlWebProxy = new htmlWebProxyList($apiKey, [
        'perpage' => 30,
        'type' => 'HTTP',
        'work' => 1, // Только работающие из России
    ], $logger);
    
    echo "Загрузка прокси из htmlweb.ru в ProxyPool...\n";
    $addedCount = $htmlWebProxy->loadIntoProxyPool($proxyPool);
    
    echo "✓ Добавлено прокси в пул: {$addedCount}\n";
    echo "✓ Остаток запросов: " . ($htmlWebProxy->getRemainingLimit() ?? 'неизвестно') . "\n";
    
    $stats = $proxyPool->getStatistics();
    echo "Всего прокси в пуле: {$stats['total_proxies']}\n";
    
    if ($addedCount > 0) {
        echo "\nПопытка получить следующий прокси:\n";
        $nextProxy = $proxyPool->getNextProxy();
        echo "Следующий прокси: {$nextProxy}\n";
    }
    
    echo "\n";
    
} catch (Exception $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n\n";
}


// Пример 6: Обновление параметров
echo "=== Пример 6: Динамическое обновление параметров ===\n\n";

try {
    $htmlWebProxy = new htmlWebProxyList($apiKey, [
        'perpage' => 10,
        'type' => 'HTTP',
    ]);
    
    echo "Начальные параметры: " . json_encode($htmlWebProxy->getParams()) . "\n";
    
    $htmlWebProxy->updateParams([
        'country' => 'US',
        'work' => 1,
    ]);
    
    echo "Обновленные параметры: " . json_encode($htmlWebProxy->getParams()) . "\n";
    
    $proxies = $htmlWebProxy->getProxies();
    echo "✓ Получено прокси с новыми параметрами: " . count($proxies) . "\n\n";
    
} catch (HtmlWebProxyListException $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n\n";
}


// Пример 7: Исключение стран
echo "=== Пример 7: Исключение определенных стран ===\n\n";

try {
    $htmlWebProxy = new htmlWebProxyList($apiKey, [
        'perpage' => 20,
        'country_not' => 'CN,RU', // Исключить Китай и Россию
        'type' => 'HTTP',
    ]);
    
    echo "✓ Конфигурация: исключить Китай и Россию\n";
    
    $proxies = $htmlWebProxy->getProxies();
    echo "✓ Получено прокси: " . count($proxies) . "\n\n";
    
} catch (HtmlWebProxyListException $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n\n";
}


// Пример 8: Обработка ошибок валидации
echo "=== Пример 8: Обработка ошибок валидации ===\n\n";

try {
    // Попытка создать с некорректным типом
    $htmlWebProxy = new htmlWebProxyList($apiKey, [
        'perpage' => 10,
        'type' => 'invalid_type',
    ]);
    
} catch (HtmlWebProxyListValidationException $e) {
    echo "✓ Корректно поймана ошибка валидации:\n";
    echo "  {$e->getMessage()}\n\n";
}


// Пример 9: Работа с пагинацией
echo "=== Пример 9: Пагинация результатов ===\n\n";

try {
    echo "Загрузка первой страницы...\n";
    $htmlWebProxyPage1 = new htmlWebProxyList($apiKey, [
        'perpage' => 10,
        'p' => 1, // Номер страницы (параметр 'p', не 'page')
        'type' => 'HTTP',
    ]);
    $proxiesPage1 = $htmlWebProxyPage1->getProxies();
    echo "✓ Страница 1: получено " . count($proxiesPage1) . " прокси\n";
    
    echo "Загрузка второй страницы...\n";
    $htmlWebProxyPage2 = new htmlWebProxyList($apiKey, [
        'perpage' => 10,
        'p' => 2,
        'type' => 'HTTP',
    ]);
    $proxiesPage2 = $htmlWebProxyPage2->getProxies();
    echo "✓ Страница 2: получено " . count($proxiesPage2) . " прокси\n";
    
    echo "Всего загружено: " . (count($proxiesPage1) + count($proxiesPage2)) . " прокси\n\n";
    
} catch (HtmlWebProxyListException $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n\n";
}


// Пример 10: Полная конфигурация
echo "=== Пример 10: Полная конфигурация со всеми параметрами ===\n\n";

try {
    $logger = new Logger([
        'directory' => __DIR__ . '/../logs',
        'file_name' => 'htmlweb_full.log',
        'enabled' => true,
    ]);
    
    $htmlWebProxy = new htmlWebProxyList($apiKey, [
        'country' => 'US',
        'perpage' => 50,
        'work' => 1, // 1 - работает из России
        'type' => 'HTTPS',
        'p' => 1, // Номер страницы
        'timeout' => 15,
    ], $logger);
    
    echo "✓ Полная конфигурация:\n";
    echo "  Страна: US\n";
    echo "  На странице: 50\n";
    echo "  Работоспособность: работает из России (1)\n";
    echo "  Тип: HTTPS\n";
    echo "  Страница: 1\n";
    echo "  Таймаут: 15s\n\n";
    
    $proxies = $htmlWebProxy->getProxies();
    echo "✓ Получено прокси: " . count($proxies) . "\n";
    echo "✓ Остаток запросов: " . ($htmlWebProxy->getRemainingLimit() ?? 'неизвестно') . "\n\n";
    
} catch (HtmlWebProxyListException $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n\n";
}


// Пример 11: Использование массива стран
echo "=== Пример 11: Фильтрация по нескольким странам ===\n\n";

try {
    $htmlWebProxy = new htmlWebProxyList($apiKey, [
        'country' => ['US', 'GB', 'DE'], // Массив стран
        'perpage' => 20,
        'type' => 'HTTP',
    ]);
    
    echo "✓ Конфигурация: прокси из США, Великобритании, Германии\n";
    
    $proxies = $htmlWebProxy->getProxies();
    echo "✓ Получено прокси: " . count($proxies) . "\n";
    echo "Параметр country: " . $htmlWebProxy->getParams()['country'] . "\n\n";
    
} catch (HtmlWebProxyListException $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n\n";
}


// Пример 12: Валидация пустого API ключа
echo "=== Пример 12: Валидация API ключа ===\n\n";

try {
    $htmlWebProxy = new htmlWebProxyList('', [
        'perpage' => 10,
    ]);
    
} catch (HtmlWebProxyListValidationException $e) {
    echo "✓ Корректно поймана ошибка валидации API ключа:\n";
    echo "  {$e->getMessage()}\n\n";
}


echo "=== Примеры завершены ===\n";

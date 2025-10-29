<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use App\Component\Logger;
use App\Component\ProxyPool;
use App\Component\htmlWebProxyList;
use App\Component\Exception\HtmlWebProxyListException;
use App\Component\Exception\HtmlWebProxyListValidationException;

echo "=== Примеры использования htmlWebProxyList ===\n\n";

// Пример 1: Базовое использование - получение списка прокси
echo "=== Пример 1: Базовое получение списка прокси ===\n\n";

try {
    $logger = new Logger([
        'directory' => __DIR__ . '/../logs',
        'file_name' => 'htmlweb_proxylist.log',
        'enabled' => true,
    ]);
    
    $htmlWebProxy = new htmlWebProxyList([
        'perpage' => 10,
        'type' => 'http',
    ], $logger);
    
    echo "✓ htmlWebProxyList инициализирован\n";
    echo "Параметры: " . json_encode($htmlWebProxy->getParams()) . "\n";
    
    echo "Получение списка прокси...\n";
    $proxies = $htmlWebProxy->getProxies();
    
    echo "✓ Получено прокси: " . count($proxies) . "\n";
    
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
    $htmlWebProxy = new htmlWebProxyList([
        'country' => 'RU',
        'perpage' => 20,
        'type' => 'http',
        'work' => 'yes',
    ]);
    
    echo "✓ Конфигурация: прокси из России, только рабочие\n";
    
    $proxies = $htmlWebProxy->getProxies();
    echo "✓ Получено российских прокси: " . count($proxies) . "\n\n";
    
} catch (HtmlWebProxyListException $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n\n";
}


// Пример 3: Использование краткого формата
echo "=== Пример 3: Краткий формат вывода ===\n\n";

try {
    $htmlWebProxy = new htmlWebProxyList([
        'perpage' => 15,
        'type' => 'socks5',
        'short' => 'only_ip',
    ]);
    
    echo "✓ Конфигурация: SOCKS5 прокси, краткий формат\n";
    
    $proxies = $htmlWebProxy->getProxies();
    echo "✓ Получено SOCKS5 прокси: " . count($proxies) . "\n\n";
    
} catch (HtmlWebProxyListException $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n\n";
}


// Пример 4: Фильтрация по скорости
echo "=== Пример 4: Быстрые прокси ===\n\n";

try {
    $htmlWebProxy = new htmlWebProxyList([
        'perpage' => 25,
        'type' => 'http',
        'speed_max' => 1000,
        'work' => 'yes',
    ]);
    
    echo "✓ Конфигурация: прокси со скоростью до 1000ms\n";
    
    $proxies = $htmlWebProxy->getProxies();
    echo "✓ Получено быстрых прокси: " . count($proxies) . "\n\n";
    
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
    
    $htmlWebProxy = new htmlWebProxyList([
        'perpage' => 30,
        'type' => 'http',
        'work' => 'yes',
    ], $logger);
    
    echo "Загрузка прокси из htmlweb.ru в ProxyPool...\n";
    $addedCount = $htmlWebProxy->loadIntoProxyPool($proxyPool);
    
    echo "✓ Добавлено прокси в пул: {$addedCount}\n";
    
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
    $htmlWebProxy = new htmlWebProxyList([
        'perpage' => 10,
        'type' => 'http',
    ]);
    
    echo "Начальные параметры: " . json_encode($htmlWebProxy->getParams()) . "\n";
    
    $htmlWebProxy->updateParams([
        'country' => 'US',
        'speed_max' => 500,
        'work' => 'yes',
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
    $htmlWebProxy = new htmlWebProxyList([
        'perpage' => 20,
        'country_not' => 'CN,RU',
        'type' => 'http',
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
    $htmlWebProxy = new htmlWebProxyList([
        'perpage' => 100,
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
    $htmlWebProxyPage1 = new htmlWebProxyList([
        'perpage' => 10,
        'page' => 1,
        'type' => 'http',
    ]);
    $proxiesPage1 = $htmlWebProxyPage1->getProxies();
    echo "✓ Страница 1: получено " . count($proxiesPage1) . " прокси\n";
    
    echo "Загрузка второй страницы...\n";
    $htmlWebProxyPage2 = new htmlWebProxyList([
        'perpage' => 10,
        'page' => 2,
        'type' => 'http',
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
    
    $htmlWebProxy = new htmlWebProxyList([
        'country' => 'US',
        'perpage' => 50,
        'work' => 'yes',
        'type' => 'https',
        'speed_max' => 2000,
        'page' => 1,
        'timeout' => 15,
    ], $logger);
    
    echo "✓ Полная конфигурация:\n";
    echo "  Страна: US\n";
    echo "  На странице: 50\n";
    echo "  Работоспособность: yes\n";
    echo "  Тип: https\n";
    echo "  Макс. скорость: 2000ms\n";
    echo "  Страница: 1\n";
    echo "  Таймаут: 15s\n\n";
    
    $proxies = $htmlWebProxy->getProxies();
    echo "✓ Получено прокси: " . count($proxies) . "\n\n";
    
} catch (HtmlWebProxyListException $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n\n";
}


echo "=== Примеры завершены ===\n";

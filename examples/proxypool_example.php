<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use App\Component\Logger;
use App\Component\ProxyPool;
use App\Component\Exception\ProxyPoolException;
use App\Config\ConfigLoader;

// Пример 1: Базовое использование ProxyPool с конфигурацией
echo "=== Пример 1: Базовое использование ProxyPool ===\n\n";

try {
    // Инициализация логгера
    $logger = new Logger([
        'directory' => __DIR__ . '/../logs',
        'file_name' => 'proxypool.log',
        'enabled' => true,
    ]);
    
    // Конфигурация пула прокси
    $config = [
        'proxies' => [
            'http://proxy1.example.com:8080',
            'http://user:pass@proxy2.example.com:3128',
            'socks5://proxy3.example.com:1080',
        ],
        'rotation_strategy' => ProxyPool::ROTATION_ROUND_ROBIN,
        'health_check_url' => 'https://httpbin.org/ip',
        'health_check_timeout' => 5,
        'auto_health_check' => false, // Отключаем автоматическую проверку для примера
        'max_retries' => 3,
    ];
    
    $proxyPool = new ProxyPool($config, $logger);
    
    echo "✓ ProxyPool инициализирован\n";
    echo "Загружено прокси: " . count($proxyPool->getAllProxies()) . "\n\n";
    
} catch (Exception $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n\n";
}


// Пример 2: Добавление и удаление прокси
echo "=== Пример 2: Добавление и удаление прокси ===\n\n";

try {
    $proxyPool = new ProxyPool([
        'rotation_strategy' => ProxyPool::ROTATION_RANDOM,
    ]);
    
    // Добавление прокси
    $proxyPool->addProxy('http://new-proxy1.example.com:8080');
    $proxyPool->addProxy('http://new-proxy2.example.com:8080');
    $proxyPool->addProxy('socks5://new-proxy3.example.com:1080');
    
    echo "✓ Добавлено 3 прокси\n";
    
    // Получение следующего прокси
    $proxy = $proxyPool->getNextProxy();
    echo "Следующий прокси: {$proxy}\n";
    
    // Удаление прокси
    $proxyPool->removeProxy('http://new-proxy1.example.com:8080');
    echo "✓ Удален 1 прокси\n\n";
    
} catch (Exception $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n\n";
}


// Пример 3: Ротация прокси (Round-robin)
echo "=== Пример 3: Ротация прокси (Round-robin) ===\n\n";

try {
    $proxyPool = new ProxyPool([
        'proxies' => [
            'http://proxy-rr-1.example.com:8080',
            'http://proxy-rr-2.example.com:8080',
            'http://proxy-rr-3.example.com:8080',
        ],
        'rotation_strategy' => ProxyPool::ROTATION_ROUND_ROBIN,
        'auto_health_check' => false,
    ]);
    
    echo "Стратегия: Round-robin\n";
    echo "Последовательность прокси:\n";
    
    for ($i = 1; $i <= 5; $i++) {
        $proxy = $proxyPool->getNextProxy();
        echo "{$i}. {$proxy}\n";
    }
    
    echo "\n";
    
} catch (Exception $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n\n";
}


// Пример 4: Health-check прокси
echo "=== Пример 4: Health-check прокси ===\n\n";

try {
    $proxyPool = new ProxyPool([
        'proxies' => [
            'http://working-proxy.example.com:8080',
            'http://dead-proxy.example.com:8080',
        ],
        'health_check_url' => 'https://httpbin.org/status/200',
        'health_check_timeout' => 3,
        'auto_health_check' => false,
    ]);
    
    echo "Проверка здоровья прокси...\n";
    
    // Проверка всех прокси
    $proxyPool->checkAllProxies();
    
    // Получение статистики
    $stats = $proxyPool->getStatistics();
    echo "Живых прокси: {$stats['alive_proxies']}\n";
    echo "Мёртвых прокси: {$stats['dead_proxies']}\n\n";
    
} catch (Exception $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n\n";
}


// Пример 5: Выполнение HTTP запроса через прокси с retry
echo "=== Пример 5: HTTP запрос через прокси с retry ===\n\n";

try {
    $proxyPool = new ProxyPool([
        'proxies' => [
            'http://proxy1.example.com:8080',
            'http://proxy2.example.com:8080',
        ],
        'max_retries' => 3,
        'auto_health_check' => false,
    ]);
    
    echo "Выполнение GET запроса через прокси...\n";
    
    // Этот запрос может не сработать в примере, так как прокси несуществующие
    // В реальном использовании с настоящими прокси это будет работать
    try {
        $response = $proxyPool->get('https://httpbin.org/ip');
        $statusCode = $response->getStatusCode();
        $body = $response->getBody()->getContents();
        
        echo "✓ Запрос выполнен успешно\n";
        echo "Код ответа: {$statusCode}\n";
        echo "Тело ответа: {$body}\n\n";
    } catch (ProxyPoolException $e) {
        echo "✗ Все прокси недоступны: " . $e->getMessage() . "\n\n";
    }
    
} catch (Exception $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n\n";
}


// Пример 6: Получение детальной статистики
echo "=== Пример 6: Детальная статистика ===\n\n";

try {
    $proxyPool = new ProxyPool([
        'proxies' => [
            'http://stats-proxy1.example.com:8080',
            'http://stats-proxy2.example.com:8080',
            'http://stats-proxy3.example.com:8080',
        ],
        'rotation_strategy' => ProxyPool::ROTATION_ROUND_ROBIN,
        'auto_health_check' => false,
    ]);
    
    // Помечаем некоторые прокси для демонстрации статистики
    $proxyPool->markProxyAsAlive('http://stats-proxy1.example.com:8080');
    $proxyPool->markProxyAsAlive('http://stats-proxy1.example.com:8080');
    $proxyPool->markProxyAsDead('http://stats-proxy2.example.com:8080');
    
    $stats = $proxyPool->getStatistics();
    
    echo "=== Общая статистика пула ===\n";
    echo "Всего прокси: {$stats['total_proxies']}\n";
    echo "Живых прокси: {$stats['alive_proxies']}\n";
    echo "Мёртвых прокси: {$stats['dead_proxies']}\n";
    echo "Стратегия ротации: {$stats['rotation_strategy']}\n";
    echo "Всего запросов: {$stats['total_requests']}\n";
    echo "Успешных запросов: {$stats['successful_requests']}\n";
    echo "Неудачных запросов: {$stats['failed_requests']}\n";
    echo "Всего повторов: {$stats['total_retries']}\n";
    echo "Успешность: {$stats['success_rate']}%\n\n";
    
    echo "=== Детальная статистика по прокси ===\n";
    foreach ($stats['proxies'] as $proxyInfo) {
        echo "Прокси: {$proxyInfo['url']}\n";
        echo "  Статус: " . ($proxyInfo['alive'] ? '✓ Живой' : '✗ Мёртвый') . "\n";
        echo "  Успешных запросов: {$proxyInfo['success_count']}\n";
        echo "  Неудачных запросов: {$proxyInfo['fail_count']}\n";
        echo "  Успешность: {$proxyInfo['success_rate']}%\n";
        echo "  Последняя проверка: {$proxyInfo['last_check_human']}\n";
        if ($proxyInfo['last_error'] !== '') {
            echo "  Последняя ошибка: {$proxyInfo['last_error']}\n";
        }
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n\n";
}


// Пример 7: Использование с конфигурационным файлом
echo "=== Пример 7: Загрузка из конфигурационного файла ===\n\n";

try {
    $configPath = __DIR__ . '/../config/proxypool.json';
    
    if (file_exists($configPath)) {
        $logger = new Logger([
            'directory' => __DIR__ . '/../logs',
            'file_name' => 'proxypool_from_config.log',
            'enabled' => true,
        ]);
        
        // Способ 1: Использование статического метода fromConfig
        $proxyPool = ProxyPool::fromConfig($configPath, $logger);
        
        echo "✓ ProxyPool загружен из конфигурационного файла через fromConfig()\n";
        
        $stats = $proxyPool->getStatistics();
        echo "Загружено прокси: {$stats['total_proxies']}\n";
        echo "Стратегия ротации: {$stats['rotation_strategy']}\n";
        
        // Способ 2: Использование ConfigLoader напрямую
        $config = ConfigLoader::load($configPath);
        unset($config['_comment'], $config['_fields']);
        
        $proxyPool2 = new ProxyPool($config);
        echo "✓ ProxyPool также может быть создан через ConfigLoader::load()\n\n";
    } else {
        echo "⚠ Конфигурационный файл не найден: {$configPath}\n\n";
    }
    
} catch (Exception $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n\n";
}


// Пример 8: Сброс и очистка
echo "=== Пример 8: Сброс статистики и очистка пула ===\n\n";

try {
    $proxyPool = new ProxyPool([
        'proxies' => [
            'http://clear-proxy1.example.com:8080',
            'http://clear-proxy2.example.com:8080',
        ],
        'auto_health_check' => false,
    ]);
    
    // Добавляем немного статистики
    $proxyPool->markProxyAsAlive('http://clear-proxy1.example.com:8080');
    $proxyPool->markProxyAsDead('http://clear-proxy2.example.com:8080');
    
    $statsBefore = $proxyPool->getStatistics();
    echo "До сброса - прокси с успешными запросами: " . 
         count(array_filter($statsBefore['proxies'], fn($p) => $p['success_count'] > 0)) . "\n";
    
    // Сбрасываем статистику
    $proxyPool->resetStatistics();
    
    $statsAfter = $proxyPool->getStatistics();
    echo "После сброса - прокси с успешными запросами: " . 
         count(array_filter($statsAfter['proxies'], fn($p) => $p['success_count'] > 0)) . "\n";
    
    echo "✓ Статистика сброшена\n\n";
    
    // Очищаем весь пул
    $proxyPool->clearProxies();
    
    $statsClear = $proxyPool->getStatistics();
    echo "После очистки - всего прокси: {$statsClear['total_proxies']}\n";
    echo "✓ Пул очищен\n\n";
    
} catch (Exception $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n\n";
}


echo "=== Примеры завершены ===\n";

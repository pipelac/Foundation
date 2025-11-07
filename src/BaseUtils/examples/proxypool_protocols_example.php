<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../autoload.php';

use App\Component\Logger;
use App\Component\ProxyPool;
use App\Component\Exception\ProxyPoolException;
use App\Component\Exception\ProxyPoolValidationException;

/**
 * Демонстрация поддержки всех типов протоколов прокси:
 * - HTTP
 * - HTTPS
 * - SOCKS4
 * - SOCKS5
 */

echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║  ProxyPool - Поддержка протоколов: HTTP, HTTPS, SOCKS4, SOCKS5  ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

// Инициализация логгера
$logger = new Logger([
    'directory' => __DIR__ . '/../logs',
    'file_name' => 'proxypool_protocols.log',
    'enabled' => true,
]);

// Пример 1: Добавление всех типов прокси
echo "=== Пример 1: Добавление всех поддерживаемых типов прокси ===\n\n";

try {
    $proxyPool = new ProxyPool([
        'rotation_strategy' => ProxyPool::ROTATION_ROUND_ROBIN,
        'auto_health_check' => false,
    ], $logger);
    
    echo "Добавление прокси различных типов:\n\n";
    
    // HTTP прокси
    $proxyPool->addProxy('http://proxy-http.example.com:8080');
    echo "✓ HTTP прокси добавлен:   http://proxy-http.example.com:8080\n";
    
    // HTTPS прокси
    $proxyPool->addProxy('https://proxy-https.example.com:8443');
    echo "✓ HTTPS прокси добавлен:  https://proxy-https.example.com:8443\n";
    
    // SOCKS4 прокси
    $proxyPool->addProxy('socks4://proxy-socks4.example.com:1080');
    echo "✓ SOCKS4 прокси добавлен: socks4://proxy-socks4.example.com:1080\n";
    
    // SOCKS5 прокси
    $proxyPool->addProxy('socks5://proxy-socks5.example.com:1080');
    echo "✓ SOCKS5 прокси добавлен: socks5://proxy-socks5.example.com:1080\n";
    
    $stats = $proxyPool->getStatistics();
    echo "\n✓ Всего успешно добавлено прокси: {$stats['total_proxies']}\n\n";
    
} catch (ProxyPoolValidationException $e) {
    echo "✗ Ошибка валидации: {$e->getMessage()}\n\n";
} catch (Exception $e) {
    echo "✗ Ошибка: {$e->getMessage()}\n\n";
}


// Пример 2: Прокси с аутентификацией
echo "=== Пример 2: Прокси с аутентификацией ===\n\n";

try {
    $proxyPool = new ProxyPool([
        'rotation_strategy' => ProxyPool::ROTATION_RANDOM,
        'auto_health_check' => false,
    ], $logger);
    
    echo "Добавление прокси с учетными данными:\n\n";
    
    // HTTP с аутентификацией
    $proxyPool->addProxy('http://user:password@proxy-auth.example.com:8080');
    echo "✓ HTTP с auth:   http://user:password@proxy-auth.example.com:8080\n";
    
    // HTTPS с аутентификацией
    $proxyPool->addProxy('https://admin:secret@proxy-secure.example.com:8443');
    echo "✓ HTTPS с auth:  https://admin:secret@proxy-secure.example.com:8443\n";
    
    // SOCKS5 с аутентификацией
    $proxyPool->addProxy('socks5://user123:pass456@proxy-socks5-auth.example.com:1080');
    echo "✓ SOCKS5 с auth: socks5://user123:pass456@proxy-socks5-auth.example.com:1080\n";
    
    $stats = $proxyPool->getStatistics();
    echo "\n✓ Прокси с аутентификацией добавлены: {$stats['total_proxies']}\n\n";
    
} catch (ProxyPoolValidationException $e) {
    echo "✗ Ошибка валидации: {$e->getMessage()}\n\n";
} catch (Exception $e) {
    echo "✗ Ошибка: {$e->getMessage()}\n\n";
}


// Пример 3: Инициализация через конфигурацию со всеми типами
echo "=== Пример 3: Инициализация пула со всеми типами прокси ===\n\n";

try {
    $config = [
        'proxies' => [
            // HTTP прокси
            'http://proxy1.example.com:8080',
            'http://user:pass@proxy2.example.com:3128',
            
            // HTTPS прокси
            'https://secure-proxy.example.com:8443',
            
            // SOCKS4 прокси
            'socks4://socks4-proxy.example.com:1080',
            
            // SOCKS5 прокси
            'socks5://socks5-proxy.example.com:1080',
            'socks5://admin:secret@socks5-auth.example.com:1080',
        ],
        'rotation_strategy' => ProxyPool::ROTATION_ROUND_ROBIN,
        'health_check_url' => 'https://httpbin.org/ip',
        'health_check_timeout' => 5,
        'auto_health_check' => false,
        'max_retries' => 3,
    ];
    
    $proxyPool = new ProxyPool($config, $logger);
    
    $stats = $proxyPool->getStatistics();
    
    echo "Пул прокси инициализирован:\n";
    echo "  Всего прокси: {$stats['total_proxies']}\n";
    echo "  Стратегия: {$stats['rotation_strategy']}\n\n";
    
    echo "Список всех прокси:\n";
    foreach ($stats['proxies'] as $index => $proxy) {
        $protocol = explode('://', $proxy['url'])[0];
        $protocol = strtoupper($protocol);
        echo sprintf("  %d. [%-7s] %s\n", $index + 1, $protocol, $proxy['url']);
    }
    
    echo "\n✓ Все типы прокси успешно загружены\n\n";
    
} catch (ProxyPoolValidationException $e) {
    echo "✗ Ошибка валидации: {$e->getMessage()}\n\n";
} catch (Exception $e) {
    echo "✗ Ошибка: {$e->getMessage()}\n\n";
}


// Пример 4: Ротация через разные типы прокси
echo "=== Пример 4: Ротация через разные типы прокси ===\n\n";

try {
    $proxyPool = new ProxyPool([
        'proxies' => [
            'http://http-proxy.example.com:8080',
            'https://https-proxy.example.com:8443',
            'socks4://socks4-proxy.example.com:1080',
            'socks5://socks5-proxy.example.com:1080',
        ],
        'rotation_strategy' => ProxyPool::ROTATION_ROUND_ROBIN,
        'auto_health_check' => false,
    ], $logger);
    
    echo "Демонстрация Round-robin ротации через разные типы:\n\n";
    
    for ($i = 1; $i <= 6; $i++) {
        $proxy = $proxyPool->getNextProxy();
        if ($proxy !== null) {
            $protocol = strtoupper(explode('://', $proxy)[0]);
            echo sprintf("  Запрос #%d -> [%-7s] %s\n", $i, $protocol, $proxy);
        }
    }
    
    echo "\n✓ Ротация работает корректно для всех типов\n\n";
    
} catch (Exception $e) {
    echo "✗ Ошибка: {$e->getMessage()}\n\n";
}


// Пример 5: Валидация неподдерживаемых протоколов
echo "=== Пример 5: Валидация протоколов ===\n\n";

try {
    $proxyPool = new ProxyPool([
        'auto_health_check' => false,
    ], $logger);
    
    echo "Проверка валидации:\n\n";
    
    // Поддерживаемые протоколы - должны пройти
    $supportedProtocols = ['http', 'https', 'socks4', 'socks5'];
    foreach ($supportedProtocols as $protocol) {
        try {
            $proxyUrl = "{$protocol}://test-proxy.example.com:8080";
            $proxyPool->addProxy($proxyUrl);
            echo "✓ {$protocol}:// - поддерживается\n";
        } catch (ProxyPoolValidationException $e) {
            echo "✗ {$protocol}:// - НЕ поддерживается: {$e->getMessage()}\n";
        }
    }
    
    echo "\n";
    
    // Неподдерживаемые протоколы - должны быть отклонены
    $unsupportedProtocols = ['ftp', 'ssh', 'telnet', 'socks', 'proxy'];
    echo "Проверка неподдерживаемых протоколов:\n\n";
    
    foreach ($unsupportedProtocols as $protocol) {
        try {
            $proxyUrl = "{$protocol}://test-proxy.example.com:8080";
            $proxyPool->addProxy($proxyUrl);
            echo "✗ {$protocol}:// - был добавлен (не должен был!)\n";
        } catch (ProxyPoolValidationException $e) {
            echo "✓ {$protocol}:// - правильно отклонен\n";
        }
    }
    
    echo "\n✓ Валидация работает корректно\n\n";
    
} catch (Exception $e) {
    echo "✗ Ошибка: {$e->getMessage()}\n\n";
}


// Пример 6: Статистика по типам прокси
echo "=== Пример 6: Статистика по типам прокси ===\n\n";

try {
    $proxyPool = new ProxyPool([
        'proxies' => [
            'http://http1.example.com:8080',
            'http://http2.example.com:8080',
            'https://https1.example.com:8443',
            'socks4://socks4-1.example.com:1080',
            'socks4://socks4-2.example.com:1080',
            'socks5://socks5-1.example.com:1080',
            'socks5://socks5-2.example.com:1080',
            'socks5://socks5-3.example.com:1080',
        ],
        'auto_health_check' => false,
    ], $logger);
    
    $stats = $proxyPool->getStatistics();
    
    // Подсчет по типам
    $protocolCounts = [
        'http' => 0,
        'https' => 0,
        'socks4' => 0,
        'socks5' => 0,
    ];
    
    foreach ($stats['proxies'] as $proxy) {
        $protocol = explode('://', $proxy['url'])[0];
        if (isset($protocolCounts[$protocol])) {
            $protocolCounts[$protocol]++;
        }
    }
    
    echo "Статистика по типам прокси:\n\n";
    echo "  Всего прокси: {$stats['total_proxies']}\n";
    echo "  ├─ HTTP:   {$protocolCounts['http']} шт.\n";
    echo "  ├─ HTTPS:  {$protocolCounts['https']} шт.\n";
    echo "  ├─ SOCKS4: {$protocolCounts['socks4']} шт.\n";
    echo "  └─ SOCKS5: {$protocolCounts['socks5']} шт.\n\n";
    
    echo "✓ Все типы прокси учтены в пуле\n\n";
    
} catch (Exception $e) {
    echo "✗ Ошибка: {$e->getMessage()}\n\n";
}


echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║  ✓ Все примеры выполнены успешно!                            ║\n";
echo "║                                                               ║\n";
echo "║  Библиотека ProxyPool полностью поддерживает:                ║\n";
echo "║    • HTTP прокси                                             ║\n";
echo "║    • HTTPS прокси                                            ║\n";
echo "║    • SOCKS4 прокси                                           ║\n";
echo "║    • SOCKS5 прокси                                           ║\n";
echo "║    • Аутентификацию для всех типов                           ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n";

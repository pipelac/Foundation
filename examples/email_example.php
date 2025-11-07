<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use App\Component\Email;
use App\Component\Logger;
use App\Component\Config\ConfigLoader;

try {
    // Загрузка конфигурации
    $emailConfig = ConfigLoader::load(__DIR__ . '/../config/email.json');
    $loggerConfig = ConfigLoader::load(__DIR__ . '/../config/logger.json');
    
    // Создание логгера
    $logger = new Logger($loggerConfig);
    
    // Пример 1: Отправка через функцию mail() (без SMTP конфигурации)
    echo "=== Пример 1: Отправка через функцию mail() ===\n";
    
    $configWithoutSmtp = [
        'from_email' => 'noreply@example.com',
        'from_name' => 'Basic Utilities',
        'reply_to' => 'support@example.com',
        'reply_name' => 'Support Team',
        'charset' => 'UTF-8',
        'delivery' => [
            'retry_attempts' => 3,
            'retry_delay' => 2,
            'timeout' => 30,
        ],
    ];
    
    $emailViaMailFunction = new Email($configWithoutSmtp, $logger);
    
    // Отправка простого письма
    try {
        $emailViaMailFunction->send(
            'user@example.com',
            'Тестовое письмо',
            'Привет! Это тестовое письмо через функцию mail().',
            ['is_html' => false]
        );
        echo "✓ Письмо отправлено успешно через mail()\n\n";
    } catch (Exception $e) {
        echo "✗ Ошибка: {$e->getMessage()}\n\n";
    }
    
    // Пример 2: Отправка через SMTP с полной конфигурацией
    echo "=== Пример 2: Отправка через SMTP ===\n";
    
    $configWithSmtp = [
        'from_email' => 'noreply@example.com',
        'from_name' => 'Basic Utilities',
        'reply_to' => 'support@example.com',
        'reply_name' => 'Support Team',
        'charset' => 'UTF-8',
        'smtp' => [
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'your-email@gmail.com',
            'password' => 'your-app-password',
        ],
        'delivery' => [
            'retry_attempts' => 3,
            'retry_delay' => 5,
            'timeout' => 30,
        ],
    ];
    
    $emailViaSmtp = new Email($configWithSmtp, $logger);
    
    // Отправка HTML письма с вложением
    try {
        $emailViaSmtp->send(
            ['user1@example.com', 'user2@example.com'],
            'HTML письмо с вложением',
            '<html><body><h1>Привет!</h1><p>Это <strong>HTML письмо</strong> через SMTP.</p></body></html>',
            [
                'is_html' => true,
                'cc' => 'manager@example.com',
                'reply_to' => 'custom-reply@example.com',
                'reply_name' => 'Custom Reply Name',
                'attachments' => [
                    [
                        'path' => __DIR__ . '/../README.md',
                        'name' => 'README.md',
                        'mime' => 'text/plain',
                    ],
                ],
            ]
        );
        echo "✓ HTML письмо с вложением отправлено успешно через SMTP\n\n";
    } catch (Exception $e) {
        echo "✗ Ошибка: {$e->getMessage()}\n\n";
    }
    
    // Пример 3: Отправка с использованием конфигурационного файла
    echo "=== Пример 3: Отправка с использованием config файла ===\n";
    
    // Перед использованием заполните username и password в config/email.json
    $email = new Email($emailConfig, $logger);
    
    try {
        $email->send(
            'recipient@example.com',
            'Письмо из конфигурации',
            'Это письмо отправлено с использованием параметров из config/email.json',
            ['is_html' => false]
        );
        echo "✓ Письмо отправлено с использованием конфигурационного файла\n\n";
    } catch (Exception $e) {
        echo "✗ Ошибка: {$e->getMessage()}\n\n";
    }
    
    // Пример 4: Демонстрация механизма повторных попыток
    echo "=== Пример 4: Механизм повторных попыток ===\n";
    
    $configWithRetry = [
        'from_email' => 'noreply@example.com',
        'from_name' => 'Retry Test',
        'smtp' => [
            'host' => 'invalid-smtp-server.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'test@example.com',
            'password' => 'password',
        ],
        'delivery' => [
            'retry_attempts' => 3,
            'retry_delay' => 1,
            'timeout' => 5,
        ],
    ];
    
    $emailWithRetry = new Email($configWithRetry, $logger);
    
    try {
        $emailWithRetry->send(
            'test@example.com',
            'Тест повторных попыток',
            'Это письмо будет пытаться отправиться 3 раза с задержкой.',
            ['is_html' => false]
        );
        echo "✓ Письмо отправлено\n\n";
    } catch (Exception $e) {
        echo "✗ Ожидаемая ошибка (недоступный SMTP): {$e->getMessage()}\n";
        echo "  Механизм повторных попыток сработал корректно!\n\n";
    }
    
    // Пример 5: Прямая передача параметров в конструктор (без конфига)
    echo "=== Пример 5: Инициализация без файла конфигурации ===\n";
    
    $directConfig = [
        'from_email' => 'app@example.com',
        'from_name' => 'My Application',
        'reply_to' => 'noreply@example.com',
        'reply_name' => 'No Reply',
        'return_path' => 'bounces@example.com',
        'charset' => 'UTF-8',
        'smtp' => [
            'host' => 'smtp.example.com',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => 'user@example.com',
            'password' => 'secure-password',
        ],
        'delivery' => [
            'retry_attempts' => 5,
            'retry_delay' => 3,
            'timeout' => 60,
        ],
    ];
    
    $directEmail = new Email($directConfig, $logger);
    echo "✓ Email объект создан с прямыми параметрами\n";
    echo "  - SMTP Host: smtp.example.com:465 (SSL)\n";
    echo "  - Retry: 5 попыток с задержкой 3 сек\n";
    echo "  - Timeout: 60 сек\n\n";
    
    echo "=== Все примеры выполнены ===\n";
    
} catch (Exception $e) {
    echo "Критическая ошибка: {$e->getMessage()}\n";
    echo "Трассировка:\n{$e->getTraceAsString()}\n";
}

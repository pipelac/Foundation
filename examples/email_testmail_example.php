<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use App\Component\Email;
use App\Component\Logger;

/**
 * Пример использования Email класса с testmail.app
 * 
 * Перед запуском установите переменные окружения:
 * export TESTMAIL_NAMESPACE="your-namespace"
 * export TESTMAIL_API_KEY="your-api-key"
 * 
 * Запуск: php examples/email_testmail_example.php
 */

// Проверяем наличие креденшиалов
$namespace = getenv('TESTMAIL_NAMESPACE');
$apiKey = getenv('TESTMAIL_API_KEY');

if (!$namespace || !$apiKey) {
    echo "❌ Ошибка: Не установлены переменные окружения\n\n";
    echo "Установите переменные окружения перед запуском:\n";
    echo "  export TESTMAIL_NAMESPACE=\"your-namespace\"\n";
    echo "  export TESTMAIL_API_KEY=\"your-api-key\"\n\n";
    echo "Получить креденшиалы можно на: https://testmail.app\n";
    exit(1);
}

echo "=== Email тестирование с testmail.app ===\n\n";
echo "Namespace: {$namespace}\n";
echo "API Key: " . substr($apiKey, 0, 10) . "...\n\n";

// Создаём логгер
$logger = new Logger([
    'directory' => sys_get_temp_dir() . '/testmail_logs',
]);

// Конфигурация Email с testmail.app
$config = [
    'from_email' => 'sender@example.com',
    'from_name' => 'Test Sender',
    'reply_to' => 'reply@example.com',
    'smtp' => [
        'host' => 'smtp.testmail.app',
        'port' => 587,
        'encryption' => 'tls',
        'username' => $namespace,
        'password' => $apiKey,
    ],
    'delivery' => [
        'retry_attempts' => 3,
        'retry_delay' => 2,
        'timeout' => 30,
    ],
];

$email = new Email($config, $logger);

/**
 * Генерирует тестовый email адрес
 */
function generateTestEmail(string $namespace, string $tag): string
{
    return "{$namespace}.{$tag}@inbox.testmail.app";
}

/**
 * Проверяет письма через API testmail.app
 */
function checkEmails(string $namespace, string $apiKey, string $tag, int $timeout = 15): ?array
{
    echo "  Проверка писем через API (tag: {$tag})...\n";
    
    $startTime = time();
    
    while ((time() - $startTime) < $timeout) {
        $url = "https://api.testmail.app/api/json";
        $params = [
            'apikey' => $apiKey,
            'namespace' => $namespace,
            'tag' => $tag,
            'limit' => 5,
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response !== false) {
            $data = json_decode($response, true);
            if (isset($data['emails']) && count($data['emails']) > 0) {
                return $data['emails'];
            }
        }
        
        sleep(2);
    }
    
    return null;
}

try {
    // Пример 1: Простое текстовое письмо
    echo "=== Пример 1: Простое текстовое письмо ===\n";
    
    $tag1 = 'simple-text-' . time();
    $recipient1 = generateTestEmail($namespace, $tag1);
    
    echo "Отправка письма на: {$recipient1}\n";
    
    $email->send(
        $recipient1,
        'Простое тестовое письмо',
        'Привет! Это простое текстовое письмо для тестирования.',
        ['is_html' => false]
    );
    
    echo "✓ Письмо отправлено успешно!\n";
    
    // Проверяем доставку
    $emails = checkEmails($namespace, $apiKey, $tag1);
    
    if ($emails !== null && count($emails) > 0) {
        echo "✓ Письмо получено через API!\n";
        echo "  Тема: {$emails[0]['subject']}\n";
        echo "  От: {$emails[0]['from']}\n";
        echo "  Текст: " . substr($emails[0]['text'], 0, 50) . "...\n";
    } else {
        echo "⚠ Письмо не найдено через API (возможна задержка)\n";
    }
    
    echo "\n";
    
    // Пример 2: HTML письмо
    echo "=== Пример 2: HTML письмо ===\n";
    
    $tag2 = 'html-email-' . time();
    $recipient2 = generateTestEmail($namespace, $tag2);
    
    echo "Отправка HTML письма на: {$recipient2}\n";
    
    $htmlBody = '
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            h1 { color: #333; }
            .highlight { background-color: #ffff00; }
        </style>
    </head>
    <body>
        <h1>HTML Email Test</h1>
        <p>Это <strong>HTML письмо</strong> с форматированием.</p>
        <p class="highlight">Выделенный текст</p>
        <ul>
            <li>Пункт 1</li>
            <li>Пункт 2</li>
            <li>Пункт 3</li>
        </ul>
    </body>
    </html>
    ';
    
    $email->send(
        $recipient2,
        'HTML письмо с форматированием',
        $htmlBody,
        ['is_html' => true]
    );
    
    echo "✓ HTML письмо отправлено успешно!\n\n";
    
    // Пример 3: Письмо с вложением
    echo "=== Пример 3: Письмо с вложением ===\n";
    
    $tag3 = 'attachment-' . time();
    $recipient3 = generateTestEmail($namespace, $tag3);
    
    echo "Отправка письма с вложением на: {$recipient3}\n";
    
    // Создаём временный файл для вложения
    $attachmentPath = sys_get_temp_dir() . '/test_attachment_' . uniqid() . '.txt';
    $attachmentContent = "Это содержимое тестового файла.\n";
    $attachmentContent .= "Дата создания: " . date('Y-m-d H:i:s') . "\n";
    $attachmentContent .= "Это вложение было создано для демонстрации возможностей Email класса.\n";
    
    file_put_contents($attachmentPath, $attachmentContent);
    
    try {
        $email->send(
            $recipient3,
            'Письмо с вложением',
            'В этом письме есть прикреплённый файл. Проверьте вложение!',
            [
                'is_html' => false,
                'attachments' => [
                    [
                        'path' => $attachmentPath,
                        'name' => 'test_document.txt',
                        'mime' => 'text/plain',
                    ],
                ],
            ]
        );
        
        echo "✓ Письмо с вложением отправлено успешно!\n";
        
    } finally {
        // Удаляем временный файл
        if (file_exists($attachmentPath)) {
            unlink($attachmentPath);
        }
    }
    
    echo "\n";
    
    // Пример 4: Множественные получатели
    echo "=== Пример 4: Множественные получатели ===\n";
    
    $tag4 = 'multiple-' . time();
    
    $recipients = [
        generateTestEmail($namespace, $tag4),
        generateTestEmail($namespace, $tag4),
    ];
    
    echo "Отправка письма нескольким получателям:\n";
    foreach ($recipients as $i => $recipient) {
        echo "  Получатель " . ($i + 1) . ": {$recipient}\n";
    }
    
    $email->send(
        $recipients,
        'Письмо нескольким получателям',
        'Это письмо отправлено сразу нескольким получателям.',
        [
            'is_html' => false,
            'cc' => generateTestEmail($namespace, $tag4),
        ]
    );
    
    echo "✓ Письмо отправлено всем получателям!\n\n";
    
    // Пример 5: Письмо с пользовательскими заголовками
    echo "=== Пример 5: Пользовательские заголовки ===\n";
    
    $tag5 = 'headers-' . time();
    $recipient5 = generateTestEmail($namespace, $tag5);
    
    echo "Отправка письма с пользовательскими заголовками на: {$recipient5}\n";
    
    $email->send(
        $recipient5,
        'Письмо с пользовательскими заголовками',
        'Это письмо содержит дополнительные заголовки.',
        [
            'is_html' => false,
            'reply_to' => 'custom-reply@example.com',
            'reply_name' => 'Custom Reply Name',
            'headers' => [
                'X-Custom-Header' => 'CustomValue',
                'X-Priority' => '1',
                'X-Mailer' => 'Email Class v1.0',
            ],
        ]
    );
    
    echo "✓ Письмо с пользовательскими заголовками отправлено!\n\n";
    
    // Итоговая информация
    echo "=== Итоги ===\n";
    echo "✓ Все примеры выполнены успешно!\n\n";
    echo "Для просмотра всех отправленных писем:\n";
    echo "1. Откройте: https://testmail.app\n";
    echo "2. Войдите в свой аккаунт\n";
    echo "3. Выберите namespace: {$namespace}\n";
    echo "4. Просмотрите полученные письма\n\n";
    
    echo "Или используйте API для программной проверки:\n";
    echo "  curl 'https://api.testmail.app/api/json?apikey={$apiKey}&namespace={$namespace}&limit=10'\n\n";
    
    // Дополнительная демонстрация API
    echo "=== Проверка последних писем через API ===\n";
    
    $allEmails = checkEmails($namespace, $apiKey, '', 5);
    
    if ($allEmails !== null) {
        echo "Найдено писем: " . count($allEmails) . "\n\n";
        
        foreach ($allEmails as $i => $receivedEmail) {
            echo "Письмо #" . ($i + 1) . ":\n";
            echo "  От: {$receivedEmail['from']}\n";
            echo "  Кому: {$receivedEmail['to']}\n";
            echo "  Тема: {$receivedEmail['subject']}\n";
            echo "  Время: " . date('Y-m-d H:i:s', $receivedEmail['timestamp']) . "\n";
            
            if (isset($receivedEmail['attachments']) && count($receivedEmail['attachments']) > 0) {
                echo "  Вложений: " . count($receivedEmail['attachments']) . "\n";
            }
            
            echo "\n";
        }
    } else {
        echo "Письма не найдены или ещё не доставлены.\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ Ошибка: {$e->getMessage()}\n";
    echo "\nТрассировка:\n{$e->getTraceAsString()}\n";
    exit(1);
}

echo "\n=== Тестирование завершено ===\n";

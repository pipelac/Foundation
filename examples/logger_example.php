<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use App\Component\Logger;
use App\Config\ConfigLoader;

try {
    echo "=== Примеры использования Logger ===\n\n";
    
    // Пример 1: Базовое использование без email уведомлений
    echo "=== Пример 1: Базовое логирование ===\n";
    
    $basicConfig = [
        'directory' => __DIR__ . '/../logs',
        'file_name' => 'basic.log',
        'max_files' => 3,
        'max_file_size' => 1,
        'pattern' => '{timestamp} [{level}] {message} {context}',
        'date_format' => 'Y-m-d H:i:s',
        'enabled' => true,
    ];
    
    $basicLogger = new Logger($basicConfig);
    
    $basicLogger->debug('Отладочное сообщение', ['user_id' => 42]);
    $basicLogger->info('Информационное сообщение', ['action' => 'login']);
    $basicLogger->warning('Предупреждение', ['memory_usage' => '85%']);
    $basicLogger->error('Ошибка', ['error_code' => 'E001']);
    $basicLogger->critical('Критическая ошибка', ['system' => 'database', 'message' => 'Connection lost']);
    
    echo "✓ Базовые логи записаны в файл: basic.log\n\n";
    
    // Пример 2: Logger с email уведомлениями для критических ошибок
    echo "=== Пример 2: Logger с email уведомлениями (CRITICAL) ===\n";
    
    $loggerWithEmail = new Logger([
        'directory' => __DIR__ . '/../logs',
        'file_name' => 'app_with_email.log',
        'max_files' => 5,
        'max_file_size' => 10,
        'pattern' => '{timestamp} [{level}] {message} {context}',
        'date_format' => 'Y-m-d H:i:s',
        'enabled' => true,
        'admin_email' => 'admin@example.com',
        'email_config' => [
            'from_email' => 'noreply@example.com',
            'from_name' => 'Logger System',
            'reply_to' => 'support@example.com',
            'reply_name' => 'Support Team',
            'charset' => 'UTF-8',
            // SMTP настройки (опционально)
            // 'smtp' => [
            //     'host' => 'smtp.gmail.com',
            //     'port' => 587,
            //     'encryption' => 'tls',
            //     'username' => 'your-email@gmail.com',
            //     'password' => 'your-app-password',
            // ],
            'delivery' => [
                'retry_attempts' => 3,
                'retry_delay' => 5,
                'timeout' => 30,
            ],
        ],
        'email_on_levels' => ['CRITICAL'],
    ]);
    
    $loggerWithEmail->info('Приложение запущено', ['version' => '1.0.0']);
    $loggerWithEmail->warning('Низкая производительность', ['response_time' => 5.2]);
    
    // Эта запись отправит email администратору
    $loggerWithEmail->critical('База данных недоступна', [
        'host' => 'localhost',
        'port' => 3306,
        'error' => 'Connection refused',
        'timestamp' => time(),
    ]);
    
    echo "✓ Логи записаны. Email уведомление отправлено для CRITICAL уровня\n";
    echo "  (если настроен SMTP или доступна функция mail())\n\n";
    
    // Пример 3: Множественные получатели и несколько уровней для email
    echo "=== Пример 3: Множественные администраторы и уровни ===\n";
    
    $multiAdminLogger = new Logger([
        'directory' => __DIR__ . '/../logs',
        'file_name' => 'multi_admin.log',
        'max_files' => 5,
        'max_file_size' => 10,
        'enabled' => true,
        'admin_email' => [
            'admin1@example.com',
            'admin2@example.com',
            'admin3@example.com',
        ],
        'email_config' => [
            'from_email' => 'alerts@example.com',
            'from_name' => 'Alert System',
            'charset' => 'UTF-8',
        ],
        'email_on_levels' => ['ERROR', 'CRITICAL'],
    ]);
    
    $multiAdminLogger->info('Операция выполнена успешно');
    $multiAdminLogger->warning('Кеш очищен', ['cache_type' => 'redis']);
    
    // Эти записи отправят email всем администраторам
    $multiAdminLogger->error('Ошибка подключения к внешнему API', [
        'api' => 'payment_gateway',
        'status_code' => 500,
    ]);
    
    $multiAdminLogger->critical('Критическая ошибка безопасности', [
        'type' => 'unauthorized_access_attempt',
        'ip' => '192.168.1.100',
        'attempts' => 5,
    ]);
    
    echo "✓ Email уведомления отправлены для ERROR и CRITICAL уровней\n";
    echo "  на адреса: admin1@example.com, admin2@example.com, admin3@example.com\n\n";
    
    // Пример 4: Использование конфигурационного файла
    echo "=== Пример 4: Загрузка из конфигурационного файла ===\n";
    
    $loggerConfig = ConfigLoader::load(__DIR__ . '/../config/logger.json');
    
    // Добавляем email конфигурацию (обычно это в config/logger.json)
    $loggerConfig['admin_email'] = 'sysadmin@example.com';
    $loggerConfig['email_config'] = [
        'from_email' => 'system@example.com',
        'from_name' => 'System Monitor',
        'charset' => 'UTF-8',
    ];
    
    $configLogger = new Logger($loggerConfig);
    
    $configLogger->info('Logger инициализирован из конфига');
    $configLogger->critical('Тестовое критическое событие', [
        'test' => true,
        'module' => 'config_example',
    ]);
    
    echo "✓ Logger успешно инициализирован из конфигурационного файла\n\n";
    
    // Пример 5: Динамическое управление логированием
    echo "=== Пример 5: Управление состоянием логирования ===\n";
    
    $dynamicLogger = new Logger([
        'directory' => __DIR__ . '/../logs',
        'file_name' => 'dynamic.log',
        'enabled' => true,
    ]);
    
    $dynamicLogger->info('Лог включен');
    
    $dynamicLogger->disable();
    $dynamicLogger->info('Это сообщение не будет записано');
    
    $dynamicLogger->enable();
    $dynamicLogger->info('Лог снова включен');
    
    echo "✓ Состояние: " . ($dynamicLogger->isEnabled() ? 'включено' : 'выключено') . "\n\n";
    
    // Пример 6: Буферизация и принудительный сброс
    echo "=== Пример 6: Буферизация логов ===\n";
    
    $bufferedLogger = new Logger([
        'directory' => __DIR__ . '/../logs',
        'file_name' => 'buffered.log',
        'log_buffer_size' => 64, // 64 KB буфер
        'enabled' => true,
    ]);
    
    for ($i = 1; $i <= 10; $i++) {
        $bufferedLogger->info("Сообщение #{$i}", ['iteration' => $i]);
    }
    
    echo "✓ 10 сообщений добавлено в буфер\n";
    
    $bufferedLogger->flush();
    echo "✓ Буфер принудительно сброшен на диск\n\n";
    
    // Пример 7: Пример HTML письма
    echo "=== Пример 7: Демонстрация HTML письма ===\n";
    echo "При отправке email уведомления, администратор получит красиво оформленное письмо:\n";
    echo "  - Цветовая индикация уровня (CRITICAL = темно-красный)\n";
    echo "  - Таблица с деталями события\n";
    echo "  - Форматированный JSON контекст\n";
    echo "  - Информация о сервере и директории логов\n\n";
    
    // Пример 8: Обработка ошибок email
    echo "=== Пример 8: Обработка ошибок отправки email ===\n";
    echo "Если отправка email не удается, Logger:\n";
    echo "  - Продолжит работать и записывать логи в файл\n";
    echo "  - Запишет ошибку через error_log()\n";
    echo "  - Не прервет выполнение приложения\n";
    echo "  - Гарантирует надежность основного функционала\n\n";
    
    echo "=== Все примеры выполнены успешно ===\n";
    
} catch (Exception $e) {
    echo "Критическая ошибка: {$e->getMessage()}\n";
    echo "Трассировка:\n{$e->getTraceAsString()}\n";
}

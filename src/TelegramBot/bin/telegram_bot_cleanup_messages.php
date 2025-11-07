#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Скрипт для очистки старых сообщений из БД
 * 
 * Использование:
 *   php src/TelegramBot/bin/telegram_bot_cleanup_messages.php
 * 
 * Cron (ежедневно в 2:00):
 *   0 2 * * * php /path/to/project/src/TelegramBot/bin/telegram_bot_cleanup_messages.php
 */

require_once __DIR__ . '/../../../autoload.php';

use App\Config\ConfigLoader;
use App\Component\Logger;
use App\Component\MySQLConnectionFactory;
use App\Component\TelegramBot\Core\MessageStorage;

// Инициализация логгера
$logger = new Logger([
    'directory' => __DIR__ . '/../../../logs',
    'fileName' => 'telegram_bot_cleanup.log',
]);

$logger->info('=== Запуск очистки старых сообщений ===');

try {
    // Загрузка конфигураций
    $mysqlConfig = ConfigLoader::load(__DIR__ . '/../../../config/mysql.json');
    $storageConfig = ConfigLoader::load(__DIR__ . '/../config/telegram_bot_message_storage.json');
    
    // Проверка, что хранилище включено
    if (!($storageConfig['message_storage']['enabled'] ?? false)) {
        $logger->info('MessageStorage отключен в конфигурации, очистка не требуется');
        exit(0);
    }
    
    // Проверка retention_days
    $retentionDays = $storageConfig['message_storage']['retention_days'] ?? 0;
    
    if ($retentionDays <= 0) {
        $logger->info('retention_days не установлен (бесконечное хранение), очистка не требуется');
        exit(0);
    }
    
    // Подключение к БД
    $factory = new MySQLConnectionFactory($mysqlConfig, $logger);
    $db = $factory->getConnection('main');
    
    $logger->info('Подключение к БД установлено');
    
    // Создание хранилища
    $messageStorage = new MessageStorage(
        $db,
        $logger,
        $storageConfig['message_storage']
    );
    
    // Получение статистики до очистки
    $statsBefore = $messageStorage->getStatistics();
    
    $logger->info('Статистика до очистки', [
        'total' => $statsBefore['total'],
        'retention_days' => $retentionDays,
    ]);
    
    // Выполнение очистки
    $startTime = microtime(true);
    $deleted = $messageStorage->cleanupOldMessages();
    $duration = round(microtime(true) - $startTime, 2);
    
    // Получение статистики после очистки
    $statsAfter = $messageStorage->getStatistics();
    
    $logger->info('Очистка завершена', [
        'deleted' => $deleted,
        'duration_sec' => $duration,
        'total_before' => $statsBefore['total'],
        'total_after' => $statsAfter['total'],
    ]);
    
    // Вывод результатов в консоль
    echo "Очистка старых сообщений завершена\n";
    echo "Удалено записей: $deleted\n";
    echo "Было записей: {$statsBefore['total']}\n";
    echo "Осталось записей: {$statsAfter['total']}\n";
    echo "Время выполнения: {$duration} сек\n";
    
    exit(0);
    
} catch (\Exception $e) {
    $logger->error('Ошибка при очистке сообщений', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    
    echo "ОШИБКА: " . $e->getMessage() . "\n";
    exit(1);
}

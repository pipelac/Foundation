#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Скрипт для очистки устаревших диалогов
 * 
 * Использование:
 *   php src/TelegramBot/bin/telegram_bot_cleanup_conversations.php
 * 
 * Cron (каждый час):
 *   0 * * * * php /path/to/project/src/TelegramBot/bin/telegram_bot_cleanup_conversations.php
 */

require_once __DIR__ . '/../../../autoload.php';

use App\Component\Config\ConfigLoader;
use App\Component\Logger;
use App\Component\MySQLConnectionFactory;
use App\Component\TelegramBot\Core\ConversationManager;

// Инициализация логгера
$logger = new Logger([
    'directory' => __DIR__ . '/../../../logs',
    'fileName' => 'telegram_bot_cleanup_conversations.log',
]);

$logger->info('=== Запуск очистки устаревших диалогов ===');

try {
    // Загрузка конфигураций
    $mysqlConfig = ConfigLoader::load(__DIR__ . '/../../../config/mysql.json');
    $conversationsConfig = ConfigLoader::load(__DIR__ . '/../config/telegram_bot_conversations.json');
    
    // Проверка, что менеджер включен
    if (!($conversationsConfig['conversations']['enabled'] ?? false)) {
        $logger->info('ConversationManager отключен в конфигурации');
        exit(0);
    }
    
    // Подключение к БД
    $factory = new MySQLConnectionFactory($mysqlConfig, $logger);
    $db = $factory->getConnection('main');
    
    $logger->info('Подключение к БД установлено');
    
    // Создание менеджера диалогов
    $conversationManager = new ConversationManager(
        $db,
        $logger,
        $conversationsConfig['conversations']
    );
    
    // Получение статистики до очистки
    $statsBefore = $conversationManager->getStatistics();
    
    $logger->info('Статистика до очистки', [
        'total' => $statsBefore['total'],
        'by_state' => $statsBefore['by_state'],
    ]);
    
    // Выполнение очистки
    $startTime = microtime(true);
    $deleted = $conversationManager->cleanupExpiredConversations();
    $duration = round(microtime(true) - $startTime, 2);
    
    // Получение статистики после очистки
    $statsAfter = $conversationManager->getStatistics();
    
    $logger->info('Очистка завершена', [
        'deleted' => $deleted,
        'duration_sec' => $duration,
        'total_before' => $statsBefore['total'],
        'total_after' => $statsAfter['total'],
    ]);
    
    // Вывод результатов в консоль
    echo "Очистка устаревших диалогов завершена\n";
    echo "Удалено диалогов: $deleted\n";
    echo "Было активных: {$statsBefore['total']}\n";
    echo "Осталось активных: {$statsAfter['total']}\n";
    echo "Время выполнения: {$duration} сек\n";
    
    exit(0);
    
} catch (\Exception $e) {
    $logger->error('Ошибка при очистке диалогов', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    
    echo "ОШИБКА: " . $e->getMessage() . "\n";
    exit(1);
}

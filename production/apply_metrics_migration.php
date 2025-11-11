<?php

declare(strict_types=1);

/**
 * Скрипт применения миграции openrouter_metrics
 * 
 * Использование:
 * php production/apply_metrics_migration.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Component\MySQL;
use App\Component\Logger;
use App\Config\ConfigLoader;

echo "=== Применение миграции openrouter_metrics ===\n\n";

try {
    // Загрузка конфигурации
    $configLoader = new ConfigLoader();
    $mainConfig = $configLoader->load(__DIR__ . '/configs/main.json');
    $dbConfig = $configLoader->load(__DIR__ . '/configs/database.json');
    
    // Настройка логгера
    $loggerConfig = [
        'directory' => $mainConfig['log_directory'] ?? __DIR__ . '/../logs',
        'file_name' => 'migration_metrics',
        'min_level' => 'debug',
    ];
    $logger = new Logger($loggerConfig);
    
    // Подключение к БД
    $db = new MySQL($dbConfig, $logger);
    
    echo "✅ Подключение к БД установлено\n";
    
    // Читаем миграцию
    $migrationFile = __DIR__ . '/sql/migration_openrouter_metrics.sql';
    
    if (!file_exists($migrationFile)) {
        throw new Exception("Файл миграции не найден: {$migrationFile}");
    }
    
    $migrationSQL = file_get_contents($migrationFile);
    
    if ($migrationSQL === false) {
        throw new Exception("Не удалось прочитать файл миграции");
    }
    
    echo "✅ Файл миграции загружен\n";
    
    // Применяем миграцию
    echo "🔄 Применяем миграцию...\n";
    
    $db->execute($migrationSQL);
    
    echo "✅ Миграция успешно применена!\n\n";
    
    // Проверяем таблицу
    $checkSQL = "SHOW TABLES LIKE 'openrouter_metrics'";
    $result = $db->query($checkSQL);
    
    if (count($result) > 0) {
        echo "✅ Таблица openrouter_metrics создана\n";
        
        // Показываем структуру
        $descSQL = "DESCRIBE openrouter_metrics";
        $structure = $db->query($descSQL);
        
        echo "\n📊 Структура таблицы:\n";
        foreach ($structure as $field) {
            echo sprintf(
                "  - %s: %s %s\n",
                $field['Field'],
                $field['Type'],
                $field['Null'] === 'NO' ? '(NOT NULL)' : ''
            );
        }
    } else {
        echo "❌ Таблица не была создана\n";
    }
    
    echo "\n✅ Миграция завершена успешно!\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}

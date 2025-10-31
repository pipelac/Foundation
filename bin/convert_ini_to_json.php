#!/usr/bin/env php
<?php

/**
 * Скрипт конвертации INI файлов в JSON для системы контроля доступа TelegramBot
 * 
 * Использование:
 *   php bin/convert_ini_to_json.php <users.ini> <roles.ini> [output_dir]
 * 
 * Пример:
 *   php bin/convert_ini_to_json.php users.ini roles.ini config/
 */

declare(strict_types=1);

// Проверка аргументов
if ($argc < 3) {
    echo "Использование: {$argv[0]} <users.ini> <roles.ini> [output_dir]\n";
    echo "\n";
    echo "Пример:\n";
    echo "  {$argv[0]} users.ini roles.ini config/\n";
    echo "\n";
    exit(1);
}

$usersIniFile = $argv[1];
$rolesIniFile = $argv[2];
$outputDir = $argv[3] ?? './';

// Убедимся что output директория заканчивается на /
if (!str_ends_with($outputDir, '/')) {
    $outputDir .= '/';
}

echo "=== Конвертация INI файлов в JSON ===\n\n";

// Проверка существования файлов
if (!file_exists($usersIniFile)) {
    echo "Ошибка: Файл {$usersIniFile} не найден\n";
    exit(1);
}

if (!file_exists($rolesIniFile)) {
    echo "Ошибка: Файл {$rolesIniFile} не найден\n";
    exit(1);
}

// Создание output директории если не существует
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
    echo "Создана директория: {$outputDir}\n";
}

/**
 * Конвертирует INI файл с пользователями в JSON
 */
function convertUsersIni(string $iniFile): array
{
    echo "Чтение {$iniFile}...\n";
    
    $data = parse_ini_file($iniFile, true);
    
    if ($data === false) {
        throw new Exception("Не удалось прочитать INI файл: {$iniFile}");
    }
    
    $users = [];
    
    foreach ($data as $chatId => $userData) {
        // Преобразуем все значения в строки и обработаем пустые значения
        $user = [
            'first_name' => $userData['first_name'] ?? '',
            'last_name' => $userData['last_name'] ?? '',
            'email' => $userData['email'] ?? '',
            'role' => $userData['role'] ?? 'default',
            'mac' => $userData['mac'] ?? '',
        ];
        
        // Добавляем пользователя
        $users[$chatId] = $user;
    }
    
    echo "Обработано пользователей: " . count($users) . "\n";
    
    return $users;
}

/**
 * Конвертирует INI файл с ролями в JSON
 */
function convertRolesIni(string $iniFile): array
{
    echo "Чтение {$iniFile}...\n";
    
    $data = parse_ini_file($iniFile, true);
    
    if ($data === false) {
        throw new Exception("Не удалось прочитать INI файл: {$iniFile}");
    }
    
    $roles = [];
    
    foreach ($data as $roleName => $roleData) {
        // Обрабатываем команды
        $commands = [];
        
        if (isset($roleData['commands'])) {
            // Команды в INI могут быть строкой, разделенной запятыми
            if (is_string($roleData['commands'])) {
                $commands = array_map(
                    'trim',
                    explode(',', $roleData['commands'])
                );
            } elseif (is_array($roleData['commands'])) {
                $commands = $roleData['commands'];
            }
        }
        
        $roles[$roleName] = [
            'commands' => $commands,
        ];
    }
    
    echo "Обработано ролей: " . count($roles) . "\n";
    
    return $roles;
}

/**
 * Сохраняет данные в JSON файл с красивым форматированием
 */
function saveJson(string $file, array $data): void
{
    $json = json_encode(
        $data,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    
    if ($json === false) {
        throw new Exception("Ошибка создания JSON: " . json_last_error_msg());
    }
    
    if (file_put_contents($file, $json) === false) {
        throw new Exception("Не удалось записать файл: {$file}");
    }
    
    echo "Сохранено: {$file}\n";
}

try {
    // Конвертация пользователей
    echo "\n--- Конвертация пользователей ---\n";
    $users = convertUsersIni($usersIniFile);
    
    // Добавляем комментарии
    $users['_comment'] = [
        "Список пользователей бота с их правами доступа.",
        "Ключ - chat_id пользователя в Telegram (узнать можно у @userinfobot).",
        "Секция 'default' используется как шаблон для незарегистрированных пользователей."
    ];
    
    $usersJsonFile = $outputDir . 'telegram_bot_users.json';
    saveJson($usersJsonFile, $users);
    
    // Конвертация ролей
    echo "\n--- Конвертация ролей ---\n";
    $roles = convertRolesIni($rolesIniFile);
    
    // Добавляем комментарии
    $roles['_comment'] = [
        "Определение ролей и разрешенных для них команд.",
        "Каждая роль содержит массив команд, которые могут выполнять пользователи с этой ролью."
    ];
    
    $rolesJsonFile = $outputDir . 'telegram_bot_roles.json';
    saveJson($rolesJsonFile, $roles);
    
    // Создаем главный конфиг если не существует
    $configFile = $outputDir . 'telegram_bot_access_control.json';
    
    if (!file_exists($configFile)) {
        echo "\n--- Создание конфигурации ---\n";
        
        $config = [
            'enabled' => false,
            'users_file' => $usersJsonFile,
            'roles_file' => $rolesJsonFile,
            'default_role' => 'default',
            'access_denied_message' => 'У вас нет доступа к этой команде.',
            '_comment' => [
                "Конфигурация системы контроля доступа для Telegram бота.",
                "Управляет правами пользователей на основе ролей.",
                "Установите 'enabled: true' для активации."
            ]
        ];
        
        saveJson($configFile, $config);
    }
    
    echo "\n=== Конвертация завершена успешно! ===\n\n";
    echo "Созданные файлы:\n";
    echo "  - {$usersJsonFile}\n";
    echo "  - {$rolesJsonFile}\n";
    echo "  - {$configFile}\n";
    echo "\n";
    echo "Для активации контроля доступа:\n";
    echo "  1. Откройте {$configFile}\n";
    echo "  2. Установите 'enabled': true\n";
    echo "  3. Проверьте пути к файлам users_file и roles_file\n";
    echo "\n";
    
} catch (Exception $e) {
    echo "\nОшибка: " . $e->getMessage() . "\n";
    exit(1);
}

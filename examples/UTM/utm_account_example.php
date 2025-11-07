<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Component\Config\ConfigLoader;
use App\Component\Logger;
use App\Component\MySQL;
use App\Component\UTM\Account;
use App\Component\UTM\Utils;
use App\Component\Exception\UTM\AccountException;

/**
 * Пример использования модуля UTM Account
 * 
 * Демонстрирует:
 * 1. Загрузку конфигурации
 * 2. Инициализацию компонентов
 * 3. Работу с лицевыми счетами
 * 4. Использование утилит
 */

try {
    // 1. Загрузка конфигурации
    $configPath = __DIR__ . '/../config/utm_example.json';
    
    if (!file_exists($configPath)) {
        echo "ВНИМАНИЕ: Конфигурационный файл не найден: {$configPath}\n";
        echo "Пожалуйста, настройте src/UTM/config/utm_example.json под свои нужды.\n";
        exit(1);
    }
    
    $config = ConfigLoader::load($configPath);

    // 2. Инициализация Logger
    $loggerConfig = [
        'directory' => __DIR__ . '/../../../' . ($config['logger']['directory'] ?? 'logs'),
        'file' => $config['logger']['file'] ?? 'utm.log',
        'max_files' => $config['logger']['max_files'] ?? 15,
        'max_file_size_mb' => $config['logger']['max_file_size_mb'] ?? 5,
        'buffer_size_kb' => $config['logger']['buffer_size_kb'] ?? 512,
        'enabled' => $config['logger']['enabled'] ?? true,
    ];
    
    $logger = new Logger($loggerConfig);
    $logger->log('INFO', 'Запуск примера работы с UTM Account');

    // 3. Инициализация подключения к БД
    $dbConfig = $config['database'];
    $db = new MySQL($dbConfig, $logger);
    
    $logger->log('INFO', 'Подключение к БД установлено');

    // 4. Создание экземпляра Account
    $account = new Account($db, $logger);

    // Примеры работы с API (используйте реальный ID счета)
    $accountId = 1; // Замените на реальный ID
    
    echo "\n=== Примеры работы с UTM Account API ===\n\n";

    // Пример 1: Получение информации о счете
    echo "1. Полная информация о счете {$accountId}:\n";
    try {
        $accountInfo = $account->getAccountInfo($accountId);
        echo "   Баланс: {$accountInfo['balance']}\n";
        echo "   Кредит: {$accountInfo['credit']}\n";
        echo "   Заблокирован: " . ($accountInfo['is_blocked'] ? 'Да' : 'Нет') . "\n";
    } catch (AccountException $e) {
        echo "   Ошибка: " . $e->getMessage() . "\n";
    }

    // Пример 2: Получение баланса в разных форматах
    echo "\n2. Баланс в разных форматах:\n";
    try {
        $balance1 = $account->getBalance($accountId, 'balance and credit');
        echo "   С кредитом: {$balance1}\n";
        
        $balance2 = $account->getBalance($accountId, 'balance + credit');
        echo "   Сумма: {$balance2}\n";
        
        $balance3 = $account->getBalance($accountId, 'array');
        echo "   Массив: balance={$balance3['balance']}, credit={$balance3['credit']}\n";
    } catch (AccountException $e) {
        echo "   Ошибка: " . $e->getMessage() . "\n";
    }

    // Пример 3: Получение текущих тарифов
    echo "\n3. Текущие тарифы:\n";
    try {
        $tariffs = $account->getCurrentTariff($accountId, 'array');
        if ($tariffs) {
            foreach ($tariffs as $id => $name) {
                echo "   ID {$id}: {$name}\n";
            }
        } else {
            echo "   Тарифы не подключены\n";
        }
    } catch (AccountException $e) {
        echo "   Ошибка: " . $e->getMessage() . "\n";
    }

    // Пример 4: Получение следующих тарифов
    echo "\n4. Следующие тарифы:\n";
    try {
        $nextTariffs = $account->getNextTariff($accountId, null, 'tariff+id');
        if ($nextTariffs) {
            echo "   {$nextTariffs}\n";
        } else {
            echo "   Следующие тарифы не настроены\n";
        }
    } catch (AccountException $e) {
        echo "   Ошибка: " . $e->getMessage() . "\n";
    }

    // Пример 5: Получение услуг
    echo "\n5. Подключенные услуги:\n";
    try {
        $services = $account->getServices($accountId, 'array');
        if ($services) {
            foreach ($services as $id => $info) {
                echo "   ID {$id}: {$info['name']} - {$info['cost']} руб. (кол-во: {$info['count']})\n";
            }
        } else {
            echo "   Услуги не подключены\n";
        }
    } catch (AccountException $e) {
        echo "   Ошибка: " . $e->getMessage() . "\n";
    }

    // Пример 6: Получение групп
    echo "\n6. Группы пользователя:\n";
    try {
        $groups = $account->getGroups($accountId);
        if ($groups) {
            echo "   ID групп: {$groups}\n";
        } else {
            echo "   Группы не назначены\n";
        }
    } catch (AccountException $e) {
        echo "   Ошибка: " . $e->getMessage() . "\n";
    }

    // ========== Примеры использования утилит Utils ==========
    echo "\n\n=== Примеры работы с Utils ===\n\n";

    // Пример 7: Валидация email
    echo "7. Валидация email:\n";
    $testEmail = "admin@example.com";
    $isValid = Utils::isValidEmail($testEmail);
    echo "   {$testEmail}: " . ($isValid ? 'валидный' : 'невалидный') . "\n";

    // Пример 8: Валидация и форматирование телефона
    echo "\n8. Форматирование телефона:\n";
    try {
        $phone = Utils::validateMobileNumber("+7 (909) 123-45-67");
        echo "   Отформатированный: {$phone}\n";
    } catch (\App\Component\Exception\UTM\UtilsValidationException $e) {
        echo "   Ошибка: " . $e->getMessage() . "\n";
    }

    // Пример 9: Округление чисел
    echo "\n9. Округление чисел:\n";
    $rounded = Utils::doRound(1234.5678, 2);
    echo "   1234.5678 -> {$rounded}\n";

    // Пример 10: Формирование окончаний
    echo "\n10. Правильные окончания слов:\n";
    echo "   " . Utils::numWord(1, ['день', 'дня', 'дней']) . "\n";
    echo "   " . Utils::numWord(2, ['день', 'дня', 'дней']) . "\n";
    echo "   " . Utils::numWord(5, ['день', 'дня', 'дней']) . "\n";

    // Пример 11: Конвертация времени
    echo "\n11. Конвертация минут в читаемый формат:\n";
    echo "   1500 минут = " . Utils::min2hour(1500, true) . "\n";
    echo "   1500 минут = " . Utils::min2hour(1500, false) . "\n";

    // Пример 12: Транслитерация
    echo "\n12. Транслитерация:\n";
    $rus = "Привет";
    $lat = Utils::rus2lat($rus);
    $back = Utils::lat2rus($lat);
    echo "   {$rus} -> {$lat} -> {$back}\n";

    // Пример 13: Генерация случайных строк
    echo "\n13. Генерация случайных данных:\n";
    echo "   Строка: " . Utils::generateString(10) . "\n";
    echo "   Пароль: " . Utils::generatePassword(8) . "\n";

    // Пример 14: Парсинг диапазонов чисел
    echo "\n14. Парсинг диапазонов:\n";
    $numbers = Utils::parseNumbers("1,3-5,7,10-12");
    echo "   '1,3-5,7,10-12' -> " . implode(', ', $numbers) . "\n";

    $logger->log('INFO', 'Пример завершен успешно');
    echo "\n=== Пример завершен успешно ===\n";

} catch (\Exception $e) {
    echo "\n!!! КРИТИЧЕСКАЯ ОШИБКА !!!\n";
    echo "Сообщение: " . $e->getMessage() . "\n";
    echo "Файл: " . $e->getFile() . " (строка " . $e->getLine() . ")\n";
    
    if (isset($logger)) {
        $logger->log('CRITICAL', 'Критическая ошибка в примере', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
    
    exit(1);
}

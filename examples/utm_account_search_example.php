<?php

/**
 * Пример использования методов поиска Account API
 * 
 * Демонстрирует новые методы поиска лицевых счетов
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config\ConfigLoader;
use App\Component\Logger;
use App\Component\MySQL;
use App\Component\UTM\Account;
use App\Component\Exception\UTM\AccountException;

try {
    // Загрузка конфигурации UTM
    $config = ConfigLoader::load(__DIR__ . '/../Config/utm.json');
    
    // Инициализация компонентов
    $logger = new Logger($config['logger']);
    $db = new MySQL($config['database'], $logger);
    $account = new Account($db, $logger);

    echo "=== Демонстрация методов поиска Account API ===\n\n";

    // 1. Поиск по IP-адресу
    echo "1. Поиск счета по IP-адресу (192.168.1.100)\n";
    try {
        $accountId = $account->getAccountByIP('192.168.1.100');
        if ($accountId !== null) {
            echo "   Найден счет: {$accountId}\n";
        } else {
            echo "   Счет не найден\n";
        }
    } catch (AccountException $e) {
        echo "   Ошибка: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // 2. Получение IP адресов счета
    echo "2. Получение IP-адресов счета (ID: 12345)\n";
    try {
        // Формат: ip (через запятую)
        $ips = $account->getIpByAccount(12345, 'ip', ', ');
        if ($ips !== null) {
            echo "   IP-адреса: {$ips}\n";
        } else {
            echo "   У счета нет IP-адресов\n";
        }
        
        // Формат: массив [IP => MAC]
        $ipArray = $account->getIpByAccount(12345, 'array');
        if ($ipArray !== null) {
            echo "   Массив IP => MAC:\n";
            foreach ($ipArray as $ip => $mac) {
                echo "   - {$ip} => {$mac}\n";
            }
        }
    } catch (AccountException $e) {
        echo "   Ошибка: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // 3. Поиск по телефону
    echo "3. Поиск счетов по номеру телефона (79091234567)\n";
    try {
        $accounts = $account->getAccountByPhone('79091234567');
        if ($accounts !== null) {
            echo "   Найденные счета: {$accounts}\n";
        } else {
            echo "   Счета не найдены\n";
        }
    } catch (AccountException $e) {
        echo "   Ошибка: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // 4. Поиск по адресу
    echo "4. Поиск счетов по адресу\n";
    try {
        // Поиск только по улице
        $accounts = $account->getAccountByAddress('ул. Пушкина');
        if ($accounts !== null) {
            echo "   Найдено по адресу 'ул. Пушкина': {$accounts}\n";
        }
        
        // Поиск с уточнением квартиры
        $accounts = $account->getAccountByAddress('ул. Пушкина', '1', '5', '23');
        if ($accounts !== null) {
            echo "   Найдено по адресу с квартирой: {$accounts}\n";
        }
    } catch (AccountException $e) {
        echo "   Ошибка: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // 5. Поиск по ФИО
    echo "5. Поиск счетов по ФИО (Иванов)\n";
    try {
        $accounts = $account->getAccountByFio('Иванов');
        if ($accounts !== null) {
            echo "   Найденные счета: {$accounts}\n";
        } else {
            echo "   Счета не найдены\n";
        }
    } catch (AccountException $e) {
        echo "   Ошибка: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // 6. Поиск по порту коммутатора
    echo "6. Поиск счетов по порту коммутатора (b1-s5, порт 27)\n";
    try {
        $accounts = $account->getAccountBySwitchPort('b1-s5', '27');
        if ($accounts !== null) {
            echo "   Найденные счета: {$accounts}\n";
        } else {
            echo "   Счета не найдены\n";
        }
    } catch (AccountException $e) {
        echo "   Ошибка: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // 7. Поиск по VLAN
    echo "7. Поиск счетов по VLAN (530)\n";
    try {
        $accounts = $account->getAccountByVlan(530, ', ', 10); // максимум 10 результатов
        if ($accounts !== null) {
            echo "   Найденные счета: {$accounts}\n";
        } else {
            echo "   Счета не найдены\n";
        }
    } catch (AccountException $e) {
        echo "   Ошибка: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // 8. Поиск по серийному номеру WiFi роутера
    echo "8. Поиск счетов по серийному номеру WiFi роутера (ABC123)\n";
    try {
        $accounts = $account->getAccountBySnWiFi('ABC123');
        if ($accounts !== null) {
            echo "   Найденные счета: {$accounts}\n";
        } else {
            echo "   Счета не найдены\n";
        }
    } catch (AccountException $e) {
        echo "   Ошибка: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // 9. Поиск по серийному номеру STB медиаплеера
    echo "9. Поиск счетов по серийному номеру STB (XYZ789)\n";
    try {
        $accounts = $account->getAccountBySnStb('XYZ789');
        if ($accounts !== null) {
            echo "   Найденные счета: {$accounts}\n";
        } else {
            echo "   Счета не найдены\n";
        }
    } catch (AccountException $e) {
        echo "   Ошибка: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // 10. Поиск по SSID
    echo "10. Поиск счетов по SSID (MyWiFi)\n";
    try {
        $accounts = $account->getAccountBySSID('MyWiFi');
        if ($accounts !== null) {
            echo "    Найденные счета: {$accounts}\n";
        } else {
            echo "    Счета не найдены\n";
        }
    } catch (AccountException $e) {
        echo "    Ошибка: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // 11. Дополнительные параметры пользователя
    echo "11. Получение дополнительных параметров пользователя (ID: 12345)\n";
    try {
        // Получить все параметры
        $params = $account->getUadParamsByAccount(12345);
        if ($params !== null) {
            echo "    Все параметры: {$params}\n";
        }
        
        // Получить конкретный параметр (2001 - коммутатор и порт)
        $switchParam = $account->getUadParamsByAccount(12345, 2001);
        if ($switchParam !== null) {
            echo "    Параметр 2001: {$switchParam}\n";
        }
    } catch (AccountException $e) {
        echo "    Ошибка: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // 12. Определение дилера
    echo "12. Определение дилера для счета (ID: 12345)\n";
    try {
        $dealer = $account->getDealerNameByAccount(12345);
        echo "    Дилер: {$dealer}\n";
    } catch (AccountException $e) {
        echo "    Ошибка: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // 13. Получение логина и пароля
    echo "13. Получение логина и пароля (ID: 12345)\n";
    try {
        $credentials = $account->getLoginAndPaswordByAccountId(12345);
        echo "    Логин: {$credentials['login']}\n";
        echo "    Пароль: {$credentials['password']}\n";
    } catch (AccountException $e) {
        echo "    Ошибка: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // 14. Получение последнего account_id
    echo "14. Получение последнего account_id\n";
    try {
        $lastAccountId = $account->getLastAccountId();
        echo "    Последний account_id: {$lastAccountId}\n";
    } catch (AccountException $e) {
        echo "    Ошибка: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // 15. Конвертация между ID
    echo "15. Конвертация между различными ID\n";
    try {
        $accountId = 12345;
        
        // Проверка существования
        $exists = $account->getAccountId($accountId);
        echo "    Счет {$accountId} существует: " . ($exists ? 'Да' : 'Нет') . "\n";
        
        // Получение порядкового номера
        $numberId = $account->getNumberIdByAccount($accountId);
        echo "    Порядковый номер (id users_accounts): {$numberId}\n";
        
        // Обратная конвертация
        $backToAccountId = $account->getAccountByUserId(100); // предположим userId = 100
        echo "    account_id для userId 100: {$backToAccountId}\n";
        
    } catch (AccountException $e) {
        echo "    Ошибка: " . $e->getMessage() . "\n";
    }
    echo "\n";

    echo "=== Демонстрация завершена ===\n";

} catch (\Exception $e) {
    echo "Критическая ошибка: " . $e->getMessage() . "\n";
    exit(1);
}

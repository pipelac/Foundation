# Модуль UTM для работы с биллинговой системой

## Описание

Модуль UTM предоставляет современный объектно-ориентированный API для работы с биллинговой системой UTM5. Полностью переписан с использованием PHP 8.1+ и базовых классов проекта (Logger, MySQL, Email, NetworkUtil).

## Структура модуля

```
src/UTM/
├── Account.php              # Класс для работы с лицевыми счетами
└── Utils.php                # Класс утилит и вспомогательных функций

src/Exception/UTM/
├── AccountException.php              # Исключение для Account
└── UtilsValidationException.php      # Исключение для Utils
```

## Установка и настройка

### 1. Конфигурационный файл

Создайте конфигурационный файл `Config/utm.json` на основе `Config/utm_example.json`:

```json
{
  "database": {
    "host": "dbs.example.com",
    "port": 3301,
    "database": "UTM5",
    "username": "utm_user",
    "password": "your_password",
    "charset": "utf8mb4",
    "persistent": false,
    "cache_statements": true
  },
  "logger": {
    "directory": "logs",
    "file": "utm.log",
    "max_files": 15,
    "max_file_size_mb": 5,
    "buffer_size_kb": 512,
    "enabled": true
  },
  "email": {
    "from_email": "noc@example.com",
    "from_name": "UTM Bot",
    "smtp_host": "mail.example.com",
    "smtp_port": 465,
    "smtp_encryption": "ssl"
  }
}
```

### 2. Инициализация

```php
<?php

use App\Config\ConfigLoader;
use App\Component\Logger;
use App\Component\MySQL;
use App\Component\UTM\Account;

// Загрузка конфигурации
$config = ConfigLoader::load(__DIR__ . '/Config/utm.json');

// Инициализация Logger
$loggerConfig = [
    'directory' => __DIR__ . '/' . $config['logger']['directory'],
    'file' => $config['logger']['file'],
    'max_files' => $config['logger']['max_files'],
    'max_file_size_mb' => $config['logger']['max_file_size_mb'],
    'enabled' => $config['logger']['enabled']
];
$logger = new Logger($loggerConfig);

// Подключение к БД
$db = new MySQL($config['database'], $logger);

// Создание экземпляра Account
$account = new Account($db, $logger);
```

## API класса Account

### getAccountInfo()

Получает полную информацию о лицевом счете.

```php
/**
 * @param int $accountId ID лицевого счета
 * @return array Информация о счете
 * @throws AccountException При ошибках
 */
public function getAccountInfo(int $accountId): array
```

**Пример:**
```php
$info = $account->getAccountInfo(123);
echo "Баланс: {$info['balance']}\n";
echo "Кредит: {$info['credit']}\n";
echo "Заблокирован: " . ($info['is_blocked'] ? 'Да' : 'Нет') . "\n";
```

### getBalance()

Получает баланс лицевого счета в различных форматах.

```php
/**
 * @param int $accountId ID лицевого счета
 * @param string $format Формат вывода:
 *   - 'balance and credit': "1000(500)р."
 *   - 'balance + credit': сумма баланса и кредита
 *   - 'balance': только баланс
 *   - 'credit': только кредит
 *   - 'array': массив с balance и credit
 * @param int $precision Количество знаков после запятой
 * @param string $unit Единица измерения
 * @return string|array Баланс в указанном формате
 */
public function getBalance(
    int $accountId, 
    string $format = 'balance and credit', 
    int $precision = 2, 
    string $unit = "р."
): string|array
```

**Примеры:**
```php
// Формат "баланс(кредит)"
$balance = $account->getBalance(123);
echo $balance; // "1234.56(500)р."

// Только баланс
$balance = $account->getBalance(123, 'balance');
echo $balance; // "1234.56"

// Массив
$balance = $account->getBalance(123, 'array');
echo "Баланс: {$balance['balance']}, Кредит: {$balance['credit']}\n";

// Сумма баланса и кредита
$total = $account->getBalance(123, 'balance + credit');
echo "Доступно: {$total}\n";
```

### getCurrentTariff()

Получает текущие тарифы лицевого счета.

```php
/**
 * @param int $accountId ID лицевого счета
 * @param string $format Формат вывода:
 *   - 'tariff+id': "Базовый (id 5)"
 *   - 'tariff': "Базовый"
 *   - 'id': "5"
 *   - 'array': [5 => 'Базовый']
 * @param string $separator Разделитель для нескольких тарифов
 * @return string|array|null Тарифы или null если нет
 */
public function getCurrentTariff(
    int $accountId, 
    string $format = 'tariff+id', 
    string $separator = "\n"
): string|array|null
```

**Примеры:**
```php
// Формат с ID
$tariff = $account->getCurrentTariff(123);
echo $tariff; // "Базовый (id 5)"

// Массив тарифов
$tariffs = $account->getCurrentTariff(123, 'array');
foreach ($tariffs as $id => $name) {
    echo "ID {$id}: {$name}\n";
}

// Только названия
$tariff = $account->getCurrentTariff(123, 'tariff');
echo $tariff; // "Базовый"
```

### getNextTariff()

Получает следующие тарифы (на которые будет переход).

```php
/**
 * @param int $accountId ID лицевого счета
 * @param int|null $fromTariffId ID тарифа, с которого переход (null = все)
 * @param string $format Формат (аналогично getCurrentTariff)
 * @param string $separator Разделитель
 * @return string|array|null Тарифы или null если нет
 */
public function getNextTariff(
    int $accountId, 
    ?int $fromTariffId = null,
    string $format = 'tariff+id', 
    string $separator = "\n"
): string|array|null
```

**Примеры:**
```php
// Все следующие тарифы
$nextTariffs = $account->getNextTariff(123);

// Следующий тариф от конкретного тарифа
$nextTariff = $account->getNextTariff(123, 5, 'tariff');
```

### getServices()

Получает услуги, подключенные к лицевому счету.

```php
/**
 * @param int $accountId ID лицевого счета
 * @param string $format Формат вывода:
 *   - 'service+id': "VPN (id 10)"
 *   - 'service+cost': "VPN (100 руб.)"
 *   - 'service': "VPN"
 *   - 'id': "10"
 *   - 'array': [10 => ['name' => 'VPN', 'cost' => 100, 'count' => 1]]
 * @param string $separator Разделитель
 * @return string|array|null Услуги или null если нет
 */
public function getServices(
    int $accountId, 
    string $format = 'service+id', 
    string $separator = "\n"
): string|array|null
```

**Примеры:**
```php
// Формат с ценой
$services = $account->getServices(123, 'service+cost');
echo $services; // "VPN (100 руб.)\nАнтивирус (50 руб.)"

// Массив услуг с детальной информацией
$services = $account->getServices(123, 'array');
foreach ($services as $id => $info) {
    echo "ID {$id}: {$info['name']} - {$info['cost']} руб. (кол-во: {$info['count']})\n";
}
```

### getGroups()

Получает группы, к которым принадлежит лицевой счет.

```php
/**
 * @param int $accountId ID лицевого счета
 * @param string $separator Разделитель для нескольких групп
 * @return string|null ID групп через разделитель или null если нет
 */
public function getGroups(int $accountId, string $separator = ','): ?string
```

**Пример:**
```php
$groups = $account->getGroups(123);
echo "Группы: {$groups}\n"; // "1,5,10"
```

## API класса Utils

Класс содержит статические методы для работы с данными.

### Валидация

```php
// Проверка email
Utils::isValidEmail('test@example.com'); // true

// Валидация и форматирование телефона
$phone = Utils::validateMobileNumber('+7 (909) 123-45-67'); // "79091234567"

// Валидация IP
$ip = Utils::validateIp('192.168.1.1'); // "192.168.1.1"

// Проверка IP в подсети
Utils::isIpInRange('192.168.1.100', '192.168.1.0/24'); // true
```

### Форматирование чисел

```php
// Округление без незначащих нулей
Utils::doRound(123.45000, 2); // "123.45"
Utils::doRound(100.00, 2);    // "100"

// Правильные окончания
Utils::numWord(1, ['день', 'дня', 'дней']); // "1 день"
Utils::numWord(2, ['день', 'дня', 'дней']); // "2 дня"
Utils::numWord(5, ['день', 'дня', 'дней']); // "5 дней"

// Конвертация времени
Utils::min2hour(1500, true);  // "1 день 1 час"
Utils::min2hour(1500, false); // "1д:1ч"
```

### Работа со строками

```php
// HEX конвертация
$hex = Utils::strToHex('Hello');
$str = Utils::hexToStr($hex);

// Транслитерация
Utils::rus2lat('Привет');     // "Privet"
Utils::lat2rus('Privet');     // "Привет"

// Первая буква в верхний регистр (UTF-8)
Utils::mbUcfirst('привет');   // "Привет"

// Мультибайтовая замена
Utils::mbStrReplace('а', 'о', 'мама', true); // "момо"
```

### Генерация данных

```php
// Случайная строка (буквы + цифры)
Utils::generateString(10);    // "aB3x9Km2pQ"

// Числовой пароль
Utils::generatePassword(8);   // "12847593"
```

### Работа с массивами и диапазонами

```php
// Парсинг диапазонов
$numbers = Utils::parseNumbers("1,3-5,7,10-12");
// [1, 3, 4, 5, 7, 10, 11, 12]

// Конвертация 2D в 1D массив
$array2d = [['a', 'b'], ['c', 'd']];
$array1d = Utils::array2ToArray1($array2d); // ['a', 'b', 'c', 'd']

// Форматирование массива в список
$array = ['key1' => 'value1', 'key2' => 'value2'];
echo Utils::array1ToList($array, '• ', ':');
// • key1: value1
// • key2: value2
```

### Специфичные функции для сетевого оборудования

```php
// Преобразование портов коммутаторов
$bin = Utils::memberPortsHex2Bin('4000000000000000');
$hex = Utils::memberPortsBin2Hex($bin);

// Конвертация в HEX для MAC-адресов
$hex = Utils::dec2hex('123456789'); // "75bcd15"
```

## Обработка ошибок

Все методы бросают специализированные исключения:

```php
use App\Component\Exception\UTM\AccountException;
use App\Component\Exception\UTM\UtilsValidationException;

try {
    $info = $account->getAccountInfo(999999);
} catch (AccountException $e) {
    echo "Ошибка работы с аккаунтом: " . $e->getMessage();
    // Логируется автоматически через Logger
}

try {
    $phone = Utils::validateMobileNumber('invalid');
} catch (UtilsValidationException $e) {
    echo "Ошибка валидации: " . $e->getMessage();
}
```

## Логирование

Все операции автоматически логируются:

```php
// INFO: Запросы к БД
// ERROR: Ошибки и исключения
// CRITICAL: Критические ошибки

// Логи сохраняются в файл указанный в конфигурации
// С автоматической ротацией по размеру
```

## Интеграция с Telegram Bot

Пример использования в Telegram боте:

```php
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\UTM\Account;

// Обработчик команды /balance
$messageHandler->on('text', function($update) use ($account, $api) {
    if ($update->message->text === '/balance') {
        $chatId = $update->message->chat->id;
        
        // Получаем ID счета пользователя из БД или кеша
        $accountId = getUserAccountId($chatId);
        
        try {
            $balance = $account->getBalance($accountId);
            $api->sendMessage($chatId, "💰 Ваш баланс: {$balance}");
        } catch (AccountException $e) {
            $api->sendMessage($chatId, "❌ Ошибка: " . $e->getMessage());
        }
    }
});
```

## Миграция со старого API

### Было (старый coreApi.php):

```php
$core = new core();
$dbc = DBFactory::getConnection('utm');

// Получение баланса
$result = $this->getBalanceByAccount($account_id);
if ($result['status'] == 'OK') {
    $balance = $result['result'];
}

// Получение тарифа
$result = $this->getCurrentTariffByAccount($account_id);
if ($result['status'] == 'OK') {
    $tariff = $result['result'];
}
```

### Стало (новый API):

```php
$db = new MySQL($config['database'], $logger);
$account = new Account($db, $logger);

// Получение баланса
try {
    $balance = $account->getBalance($accountId);
    // Работаем с $balance
} catch (AccountException $e) {
    // Обработка ошибки
}

// Получение тарифа
try {
    $tariff = $account->getCurrentTariff($accountId);
    // Работаем с $tariff
} catch (AccountException $e) {
    // Обработка ошибки
}
```

### Основные отличия:

1. **Типизация**: Все параметры и возвращаемые значения строго типизированы
2. **Исключения**: Вместо массивов `['status' => 'ERROR']` используются исключения
3. **Логирование**: Автоматическое логирование всех операций
4. **Конфигурация**: JSON вместо INI файлов
5. **Dependency Injection**: Компоненты передаются через конструктор
6. **PSR-4 Autoloading**: Автозагрузка классов через Composer

## Требования

- PHP 8.1 или выше
- MySQL 5.5+ (рекомендуется 5.7+)
- Расширения: PDO, mbstring, bcmath
- Composer для автозагрузки

## Методы поиска и работы с данными

### getUadParamsByAccount()

Получает дополнительные параметры пользователя по лицевому счету.

```php
// Получить все параметры
$params = $account->getUadParamsByAccount(12345);
// Результат: "2001=b1-s5_530_1,2,3,2009=ABC123456"

// Получить конкретный параметр (2001 - коммутатор и порт)
$switchParam = $account->getUadParamsByAccount(12345, 2001);
// Результат: "b1-s5_530_1,2,3"
```

### getAccountByIP()

Получает лицевой счет по IP-адресу.

```php
$accountId = $account->getAccountByIP('192.168.1.100');
// Результат: 12345 или null если не найден
```

### getIpByAccount()

Получает IP-адреса лицевого счета в различных форматах.

```php
// Формат: только IP через разделитель
$ips = $account->getIpByAccount(12345, 'ip', ', ');
// Результат: "192.168.1.100, 192.168.1.101"

// Формат: IP с MAC
$ips = $account->getIpByAccount(12345, 'ip+mac');
// Результат: "192.168.1.100 [AA:BB:CC:DD:EE:FF]\n192.168.1.101"

// Формат: массив [IP => MAC]
$ips = $account->getIpByAccount(12345, 'array');
// Результат: ['192.168.1.100' => 'AA:BB:CC:DD:EE:FF', '192.168.1.101' => '']
```

### getAccountByPhone()

Получает лицевые счета по номеру телефона.

```php
// Точное совпадение
$accounts = $account->getAccountByPhone('79091234567');
// Результат: "12345,12346"

// Частичное совпадение (если номер невалидный)
$accounts = $account->getAccountByPhone('909 123');
// Ищет по LIKE
```

### getAccountByAddress()

Получает лицевые счета по адресу.

```php
// Поиск только по улице
$accounts = $account->getAccountByAddress('ул. Пушкина');

// Поиск с уточнением подъезда, этажа и квартиры
$accounts = $account->getAccountByAddress('ул. Пушкина', '1', '5', '23');
// Результат: "12345,12346"
```

### getAccountByFio()

Получает лицевые счета по ФИО (или части).

```php
$accounts = $account->getAccountByFio('Иванов');
// Результат: "12345,12346,12347"

// Поиск по нескольким словам
$accounts = $account->getAccountByFio('Иванов Петр');
// Заменяет пробелы на % для LIKE
```

### getAccountBySwitchPort()

Получает лицевые счета по порту коммутатора.

```php
$accounts = $account->getAccountBySwitchPort('b1-s5', '27');
// Результат: "12345,12346"
```

### getAccountByVlan()

Получает лицевые счета по VLAN.

```php
$accounts = $account->getAccountByVlan(530, ', ', 10);
// Результат: "12345, 12346, 12347" (максимум 10 результатов)
```

### getAccountBySnWiFi()

Получает лицевые счета по серийному номеру Wi-Fi роутера.

```php
$accounts = $account->getAccountBySnWiFi('ABC123');
// Результат: "12345"
// Минимальная длина: 3 символа
```

### getAccountBySnStb()

Получает лицевые счета по серийному номеру STB медиаплеера.

```php
$accounts = $account->getAccountBySnStb('XYZ789');
// Результат: "12345,12346"
// Ищет в параметрах 2007 и 2008
```

### getAccountBySSID()

Получает лицевые счета по SSID WiFi сети.

```php
$accounts = $account->getAccountBySSID('MyWiFi');
// Результат: "12345"
```

### getDealerNameByAccount()

Получает название дилера по лицевому счету.

```php
$dealer = $account->getDealerNameByAccount(12345);
// Результат: "Марат", "Стариков" или "БТ"
// Определяется по группам 88888, 99999
```

### getLoginAndPaswordByAccountId()

Получает логин и пароль по ID лицевого счета.

```php
$credentials = $account->getLoginAndPaswordByAccountId(12345);
// Результат: ['login' => 'user123', 'password' => 'pass123']
```

### getAccountId()

Проверяет существование лицевого счета.

```php
try {
    $accountId = $account->getAccountId(12345);
    // Счет существует
} catch (AccountException $e) {
    // Счет не существует
}
```

### getNumberIdByAccount()

Получает порядковый номер учетной записи (id из users_accounts).

```php
$numberId = $account->getNumberIdByAccount(12345);
// Результат: 1234 (id из таблицы users_accounts)
```

### getAccountByUserId()

Получает ID лицевого счета по user_id.

```php
$accountId = $account->getAccountByUserId(100);
// Результат: 12345
```

### getLastAccountId()

Получает ID последнего лицевого счета.

```php
$lastAccountId = $account->getLastAccountId();
// Результат: 99999
```

## Конфигурационные файлы

### src/UTM/config/account.json

Конфигурация для работы с лицевыми счетами:
- Параметры создания пользователей
- Маппинг дилеров на группы
- Тарифы для физических и юридических лиц
- Комбо-тарифы (контракты)
- VLAN конфигурация

Подробнее см. `src/UTM/config/README.md`

## Дополнительные примеры

Полные рабочие примеры доступны в файлах:
- `examples/utm_account_example.php` - Базовая работа с Account API
- `examples/utm_account_search_example.php` - Методы поиска

## Поддержка

При возникновении ошибок проверьте:
1. Конфигурацию БД в `Config/utm.json`
2. Логи в директории, указанной в конфигурации
3. Права доступа к директории логов
4. Подключение к серверу БД UTM

Все критические ошибки автоматически логируются с уровнем CRITICAL.

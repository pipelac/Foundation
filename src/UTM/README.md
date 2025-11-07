# UTM Module - Современный API для UTM5 Биллинга

## Быстрый старт

### 1. Установка и настройка

```bash
# Скопируйте конфигурационный файл
cp Config/utm_example.json Config/utm.json

# Отредактируйте Config/utm.json под свои настройки
```

### 2. Базовый пример

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Config\ConfigLoader;
use App\Component\Logger;
use App\Component\MySQL;
use App\Component\UTM\Account;

// Загрузка конфигурации
$config = ConfigLoader::load(__DIR__ . '/Config/utm.json');

// Инициализация компонентов
$logger = new Logger([
    'directory' => __DIR__ . '/' . $config['logger']['directory'],
    'file' => $config['logger']['file'],
    'enabled' => true
]);

$db = new MySQL($config['database'], $logger);
$account = new Account($db, $logger);

// Работа с аккаунтом
try {
    $balance = $account->getBalance(123);
    echo "Баланс: {$balance}\n";
    
    $tariff = $account->getCurrentTariff(123, 'tariff+id');
    echo "Тариф: {$tariff}\n";
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}
```

### 3. Использование утилит

```php
use App\Component\UTM\Utils;

// Валидация
$phone = Utils::validateMobileNumber('+7 909 123 45 67'); // "79091234567"
$ip = Utils::validateIp('192.168.1.1');
$valid = Utils::isValidEmail('test@example.com'); // true

// Форматирование
$rounded = Utils::doRound(123.456, 2); // "123.46"
$time = Utils::min2hour(90, true); // "1 час 30 минут"
$word = Utils::numWord(5, ['день', 'дня', 'дней']); // "5 дней"

// Транслитерация
$lat = Utils::rus2lat('Привет'); // "Privet"
$rus = Utils::lat2rus('Privet'); // "Привет"

// Генерация
$string = Utils::generateString(10); // "aB3x9Km2pQ"
$password = Utils::generatePassword(8); // "12847593"
```

## Структура модуля

```
src/UTM/
├── Account.php              # API для работы с лицевыми счетами
└── Utils.php                # Утилиты (валидация, форматирование)

src/Exception/UTM/
├── AccountException.php     # Исключения Account
└── UtilsValidationException.php  # Исключения Utils
```

## Основные возможности

### Account API

- ✅ `getAccountInfo()` - полная информация о счете
- ✅ `getBalance()` - баланс в различных форматах
- ✅ `getCurrentTariff()` - текущие тарифы
- ✅ `getNextTariff()` - следующие тарифы
- ✅ `getServices()` - подключенные услуги
- ✅ `getGroups()` - группы пользователя

### Utils API

**Валидация:**
- `isValidEmail()` - проверка email
- `validateMobileNumber()` - валидация и форматирование телефона
- `validateIp()` - валидация IP-адреса
- `isIpInRange()` - проверка IP в подсети

**Форматирование:**
- `doRound()` - округление чисел
- `numWord()` - правильные окончания слов
- `min2hour()` - конвертация времени

**Строки:**
- `hexToStr()` / `strToHex()` - HEX конвертация
- `rus2lat()` / `lat2rus()` - транслитерация
- `mbUcfirst()` - первая буква в верхний регистр
- `mbStrReplace()` - мультибайтовая замена

**Генерация:**
- `generateString()` - случайная строка
- `generatePassword()` - числовой пароль

**Массивы:**
- `parseNumbers()` - парсинг диапазонов (1,3-5,7)
- `array2ToArray1()` - 2D в 1D массив
- `array1ToList()` - массив в список

## Документация

Полная документация доступна в `docs/UTM_MODULE.md`

## Тестирование

```bash
# Запуск тестов Utils
php tests/test_utm_utils.php

# Запуск примера (требуется настроенная БД)
php examples/utm_account_example.php
```

## Требования

- PHP 8.1+
- MySQL 5.5+ (рекомендуется 5.7+)
- Расширения: PDO, mbstring, bcmath

## Миграция со старого API

**Было:**
```php
$core = new core();
$result = $this->getBalanceByAccount($account_id);
if ($result['status'] == 'OK') {
    $balance = $result['result'];
}
```

**Стало:**
```php
$account = new Account($db, $logger);
try {
    $balance = $account->getBalance($accountId);
} catch (AccountException $e) {
    // Обработка ошибки
}
```

## Преимущества нового API

✅ **Строгая типизация** - все параметры и возвращаемые значения типизированы  
✅ **Исключения** - вместо массивов status/error  
✅ **Автологирование** - все операции логируются автоматически  
✅ **PSR-4** - автозагрузка через Composer  
✅ **Dependency Injection** - компоненты передаются через конструктор  
✅ **JSON конфигурация** - вместо INI файлов  
✅ **Современный PHP 8.1+** - использование новейших возможностей языка  

## Поддержка

При возникновении проблем проверьте:
1. Конфигурацию в `Config/utm.json`
2. Логи в директории, указанной в конфигурации
3. Подключение к серверу БД UTM5

Все ошибки автоматически логируются с соответствующим уровнем (INFO, WARNING, ERROR, CRITICAL).

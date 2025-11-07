# UTM Module - История изменений

## [1.0.0] - 2025-01-XX

### Добавлено
- ✅ Новый модуль `src/UTM/` для работы с биллинговой системой UTM5
- ✅ Класс `Account` для работы с лицевыми счетами:
  - `getAccountInfo()` - полная информация о счете
  - `getBalance()` - баланс в различных форматах
  - `getCurrentTariff()` - текущие тарифы
  - `getNextTariff()` - следующие тарифы
  - `getServices()` - подключенные услуги
  - `getGroups()` - группы пользователя
- ✅ Класс `Utils` с 30+ статическими методами:
  - Валидация (email, телефон, IP, подсеть)
  - Форматирование (округление, время, окончания слов)
  - Транслитерация (ГОСТ rus↔lat)
  - Генерация (случайные строки, пароли)
  - Работа с массивами и диапазонами
  - Специфичные функции для сетевого оборудования
- ✅ Специализированные исключения:
  - `AccountException` - ошибки работы с аккаунтами
  - `UtilsValidationException` - ошибки валидации
- ✅ Полная документация:
  - `docs/UTM_MODULE.md` - подробная документация API (16KB)
  - `src/UTM/README.md` - быстрый старт
  - Примеры в основном README.md
- ✅ Примеры использования:
  - `examples/utm_account_example.php` - полный рабочий пример
  - `Config/utm_example.json` - шаблон конфигурации
- ✅ Тестирование:
  - `tests/test_utm_utils.php` - 12 unit тестов для Utils (все проходят ✅)

### Технические детали

#### Архитектура
- PHP 8.1+ с строгой типизацией
- PSR-4 автозагрузка через Composer
- Dependency Injection (Logger, MySQL)
- JSON конфигурация вместо INI
- Обработка через исключения вместо массивов status/error

#### Интеграция с базовыми классами
- `Logger` - автоматическое логирование всех операций
- `MySQL` - типизированная работа с БД через PDO
- `Email` - поддержка email уведомлений (через Logger)
- `ConfigLoader` - загрузка JSON конфигурации

#### Преимущества перед старым API
- ✅ Строгая типизация всех параметров и возвращаемых значений
- ✅ Исключения вместо массивов `['status' => 'ERROR']`
- ✅ Автоматическое логирование всех операций (INFO, WARNING, ERROR, CRITICAL)
- ✅ PSR-4 autoloading через Composer
- ✅ Dependency Injection вместо глобальных объектов
- ✅ JSON конфигурация вместо INI файлов
- ✅ Современный PHP 8.1+ синтаксис (readonly, match, union types)
- ✅ Полная PHPDoc документация на русском языке

### Миграция со старого API

**Старый код (PHP 5.6):**
```php
include_once "coreApi.php";
include_once "AccountApi.php";

$core = new core();
$account = new account();

$result = $account->getBalanceByAccount($account_id);
if ($result['status'] == 'OK') {
    $balance = $result['result'];
} else {
    echo "Ошибка: " . $result['descr'];
}
```

**Новый код (PHP 8.1+):**
```php
use App\Component\Config\ConfigLoader;
use App\Component\MySQL;
use App\Component\Logger;
use App\Component\UTM\Account;

$config = ConfigLoader::load('Config/utm.json');
$logger = new Logger($config['logger']);
$db = new MySQL($config['database'], $logger);
$account = new Account($db, $logger);

try {
    $balance = $account->getBalance($accountId);
    echo "Баланс: {$balance}";
} catch (AccountException $e) {
    echo "Ошибка: " . $e->getMessage();
}
```

### Удалено
- ❌ Старые классы PHP 5.6 (coreApi.php, AccountApi.php, dbPdoApi.php, DBFactory.Class.php)
- ❌ Массивы status/error для возврата результатов
- ❌ INI конфигурационные файлы
- ❌ Методы `sendOk()`, `sendError()`, `sendNull()`, `sendWarning()`
- ❌ Метод `CallFunctionName()` для детального логирования вызовов

### Обратная совместимость
⚠️ **BREAKING CHANGES**: Модуль полностью переписан и НЕ совместим со старым API.
Требуется миграция кода согласно документации в `docs/UTM_MODULE.md`.

### Требования
- PHP 8.1 или выше
- MySQL 5.5+ (рекомендуется 5.7+)
- Расширения: PDO, pdo_mysql, mbstring, bcmath
- Composer для автозагрузки

### Тестирование
```bash
# Запуск unit тестов Utils
php tests/test_utm_utils.php

# Запуск примера (требуется настроенная БД UTM5)
php examples/utm_account_example.php
```

### Файлы и размеры
- `src/UTM/Account.php` - 29.5 KB (625 строк)
- `src/UTM/Utils.php` - 18.8 KB (608 строк)
- `src/Exception/UTM/AccountException.php` - 0.2 KB
- `src/Exception/UTM/UtilsValidationException.php` - 0.2 KB
- `docs/UTM_MODULE.md` - 16.0 KB
- `src/UTM/README.md` - 5.8 KB
- `examples/utm_account_example.php` - 6.5 KB
- `tests/test_utm_utils.php` - 5.4 KB
- `Config/utm_example.json` - 0.9 KB

**Всего:** ~83 KB кода и документации

### Контрибьюторы
- Initial implementation and architecture

### Связанные компоненты
Модуль использует следующие базовые компоненты проекта:
- `Logger` (v1.0.0) - логирование
- `MySQL` (v1.0.0) - работа с БД
- `Email` (v1.0.0) - email уведомления
- `ConfigLoader` - загрузка конфигурации

### Планы на будущее
- [ ] Интеграция с TelegramBot для создания бота поддержки
- [ ] Методы для работы с платежами
- [ ] Методы для работы с IPv4/IPv6 адресами
- [ ] Методы для работы с оборудованием (SNMP интеграция)
- [ ] Dashboard для мониторинга лицевых счетов
- [ ] REST API обертка
- [ ] GraphQL API
- [ ] WebSocket поддержка для real-time обновлений

---

**Статус:** ✅ PRODUCTION READY  
**Версия:** 1.0.0  
**Дата релиза:** 2025-01-XX  
**Совместимость:** PHP 8.1+, MySQL 5.5+

# UTM Tests

Эта папка предназначена для unit-тестов модуля UTM.

## Планируемые тесты

### Account API Tests
- `AccountTest.php` - тесты для класса Account
  - getBalance()
  - getCurrentTariff()
  - getServices()
  - getAccountByIP()
  - getAccountByPhone()
  - и другие методы...

### Utils API Tests
- `UtilsTest.php` - тесты для класса Utils
  - isValidEmail()
  - validateMobileNumber()
  - validateIp()
  - rus2lat() / lat2rus()
  - doRound()
  - parseNumbers()
  - и другие методы...

## Требования для тестирования

- PHPUnit 9.5+
- Тестовая БД UTM5 (для интеграционных тестов)
- Конфигурация: `config/utm_test.json`

## Запуск тестов

```bash
# Запуск всех тестов UTM
vendor/bin/phpunit src/UTM/tests/

# Запуск конкретного теста
vendor/bin/phpunit src/UTM/tests/UtilsTest.php
vendor/bin/phpunit src/UTM/tests/AccountTest.php
```

## Статус

🚧 **В разработке** - тесты будут добавлены в следующей версии

---

**Примечание:** Базовое тестирование Utils выполнялось в `tests/test_utm_utils.php` (12 успешных тестов).

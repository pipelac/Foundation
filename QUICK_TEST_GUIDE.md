# 🚀 Быстрый запуск интеграционных тестов TelegramBot

## Подготовка (5 минут)

### 1. Запуск MySQL
```bash
sudo systemctl start mysql
sudo mysql -e "CREATE DATABASE IF NOT EXISTS telegram_bot_test;"
sudo mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '';"
```

### 2. Установка зависимостей
```bash
composer install
```

## Запуск тестов (2 минуты)

### Базовый тест (19 тестов, ~120 сек)
```bash
php tests/Integration/TelegramBotIntegrationTest.php
```

### Расширенный тест (7 тестов, ~20 сек)
```bash
php tests/Integration/TelegramBotAdvancedTest.php
```

## Ожидаемые результаты

✅ **Базовый тест:** 18/19 пройдено (94.74%)  
✅ **Расширенный тест:** 7/7 пройдено (100%)  
✅ **ИТОГО:** 25/26 пройдено (96.15%)

## Проверка результатов

### MySQL
```bash
mysql -u root telegram_bot_test -e "SELECT COUNT(*) FROM dialog_states;"
mysql -u root telegram_bot_test -e "SELECT COUNT(*) FROM users;"
mysql -u root telegram_bot_test -e "SELECT COUNT(*) FROM statistics;"
```

### Логи
```bash
tail -f logs/app.log
```

## Отчёты

- 📄 `TELEGRAM_BOT_INTEGRATION_TEST_REPORT.md` - детальный отчёт
- 📄 `TELEGRAM_BOT_FINAL_TEST_SUMMARY.md` - итоговая сводка
- 📄 `tests/Integration/README.md` - полная документация

---

**Время выполнения:** ~3 минуты  
**Требования:** PHP 8.1+, MySQL 8.0+, Internet  
**Статус модуля:** ✅ ГОТОВ К PRODUCTION

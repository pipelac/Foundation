# 🧪 TelegramBot Testing Guide

Руководство по тестированию TelegramBot в режиме Polling

---

## 📋 Предварительные требования

### Обязательно
- ✅ PHP 8.1+
- ✅ MySQL/MariaDB (запущен и доступен)
- ✅ Composer dependencies установлены
- ✅ Telegram Bot Token

### Проверка
```bash
# Проверка PHP версии
php -v

# Проверка MySQL
mysql -u root -e "SELECT VERSION();"

# Проверка зависимостей
composer install
```

---

## 🚀 Быстрый старт

### 1. Запуск MySQL
```bash
# Если MySQL не запущен
sudo mysqld --user=mysql --datadir=/var/lib/mysql &

# Или
sudo service mysql start
```

### 2. Создание базы данных
```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS utilities_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 3. Запуск автоматизированного теста
```bash
cd /home/engine/project
php telegram_bot_automated_test.php
```

**Результат:** 36 автоматических тестов (~3 минуты)

---

## 📁 Доступные тесты

### 1. Автоматизированный тест (рекомендуется)
**Файл:** `telegram_bot_automated_test.php`

**Что тестирует:**
- MySQL подключение
- TelegramAPI инициализация
- Отправка сообщений (текст, HTML, Markdown, эмодзи)
- Клавиатуры (Inline, Reply)
- ConversationManager (диалоги с контекстом)
- PollingHandler (Long Polling)
- Редактирование и удаление сообщений
- Логирование
- Обработка ошибок
- Создание MySQL дампов

**Преимущества:**
- ✅ Не требует пользовательского ввода
- ✅ Быстрое выполнение (~3 минуты)
- ✅ 100% автоматизация
- ✅ Детальные отчеты

**Запуск:**
```bash
php telegram_bot_automated_test.php
```

---

### 2. Интерактивный тест (опциональный)
**Файл:** `telegram_bot_comprehensive_test.php`

**Что тестирует:**
- Все 6 уровней функциональности
- Интерактивное взаимодействие с пользователем
- Медиа файлы (фото, документы, аудио, видео)
- Комплексные диалоговые сценарии

**Преимущества:**
- ✅ Полная проверка всех возможностей
- ✅ Реальное взаимодействие с пользователем
- ✅ Тестирование медиа

**Недостатки:**
- ⚠️ Требует активного участия пользователя
- ⚠️ Длительное выполнение (до 30 минут)
- ⚠️ Таймауты ожидания (30 сек на действие)

**Запуск:**
```bash
php telegram_bot_comprehensive_test.php
```

---

## 📊 Ожидаемые результаты

### Успешное выполнение

```
╔══════════════════════════════════════════════════════════════════════╗
║  АВТОМАТИЗИРОВАННОЕ ТЕСТИРОВАНИЕ TELEGRAMBOT                         ║
╚══════════════════════════════════════════════════════════════════════╝

...36 тестов...

╔══════════════════════════════════════════════════════════════════════╗
║  ИТОГОВАЯ СТАТИСТИКА                                                 ║
╚══════════════════════════════════════════════════════════════════════╝

Всего тестов: 36
✅ Пройдено: 36
❌ Провалено: 0
⚠️  Предупреждений: 0
📈 Процент успеха: 100%

✅ АВТОМАТИЗИРОВАННОЕ ТЕСТИРОВАНИЕ ЗАВЕРШЕНО!
```

---

## 📁 Создаваемые файлы

### После тестирования создаются:

#### 1. MySQL дампы (`/mysql/`)
```
mysql/
├── telegram_bot_users_dump.sql (2.6 KB)
└── telegram_bot_conversations_dump.sql (2.6 KB)
```

#### 2. Логи (`/logs/`)
```
logs/
└── app.log (размер зависит от количества операций)
```

#### 3. Отчеты (корневая директория)
```
/
├── TELEGRAM_BOT_TEST_REPORT.md (детальный отчет)
└── TELEGRAM_BOT_TESTING_SUMMARY.md (краткая сводка)
```

---

## 🔍 Проверка результатов

### 1. Просмотр MySQL данных
```bash
# Пользователи
mysql -u root utilities_db -e "SELECT * FROM telegram_bot_users;"

# Диалоги
mysql -u root utilities_db -e "SELECT * FROM telegram_bot_conversations;"

# Структура таблиц
mysql -u root utilities_db -e "SHOW CREATE TABLE telegram_bot_users\G"
```

### 2. Просмотр логов
```bash
# Последние 50 строк
tail -50 logs/app.log

# Только ошибки
grep ERROR logs/app.log

# В реальном времени
tail -f logs/app.log
```

### 3. Просмотр дампов
```bash
# Содержимое дампа
cat mysql/telegram_bot_users_dump.sql

# Количество INSERT строк
grep "INSERT INTO" mysql/telegram_bot_users_dump.sql | wc -l
```

---

## 🔧 Устранение проблем

### Проблема: MySQL не запущен
```bash
# Ошибка: "Can't connect to MySQL server"
# Решение:
sudo mysqld --user=mysql --datadir=/var/lib/mysql &
```

### Проблема: База данных не существует
```bash
# Ошибка: "Unknown database 'utilities_db'"
# Решение:
mysql -u root -e "CREATE DATABASE utilities_db;"
```

### Проблема: Нет прав на запись логов
```bash
# Ошибка: "Permission denied: logs/app.log"
# Решение:
chmod 755 logs/
```

### Проблема: Telegram API ошибки
```bash
# Ошибка: "Unauthorized" или "Bad Gateway"
# Решение:
# 1. Проверьте токен бота
# 2. Проверьте интернет соединение
# 3. Проверьте доступность api.telegram.org
```

---

## 📝 Конфигурация

### Тестовые данные (в коде тестов)

```php
// Bot Token
$BOT_TOKEN = '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI';

// Test Chat IDs
$CHAT_IDS = [366442475, 311619417];

// Timeout для интерактивных тестов
$TEST_TIMEOUT = 30; // секунд
```

### MySQL конфигурация (`config/mysql.json`)

```json
{
    "databases": {
        "main": {
            "host": "localhost",
            "port": 3306,
            "database": "utilities_db",
            "username": "root",
            "password": "",
            "charset": "utf8mb4"
        }
    }
}
```

### ConversationManager (`config/telegram_bot_conversations.json`)

```json
{
    "conversations": {
        "enabled": true,
        "timeout": 3600,
        "auto_create_tables": true
    }
}
```

---

## 🎓 Дополнительная информация

### Уровни тестирования

| Уровень | Описание | Автотест | Интерактивный |
|---------|----------|----------|---------------|
| 1 | Начальные операции (текст, эмодзи) | ✅ | ✅ |
| 2 | Базовые операции (медиа) | ⚠️ | ✅ |
| 3 | Клавиатуры | ✅ | ✅ |
| 4 | Диалоги с контекстом | ✅ | ✅ |
| 5 | Обработка ошибок | ✅ | ✅ |
| 6 | Комплексные сценарии | ⚠️ | ✅ |

**Легенда:**
- ✅ Полностью поддерживается
- ⚠️ Частично (без пользовательского ввода)

---

## 📞 Тестовый бот

### Информация
- **Username:** @PipelacTest_bot
- **Bot ID:** 8327641497
- **Режим:** Polling (Long Polling)

### Команды
- `/start` - Начать работу с ботом
- `/info` - Информация о боте
- `/stat` - Статистика использования
- `/edit` - Редактирование настроек

---

## ✅ Чек-лист перед тестированием

- [ ] MySQL запущен и доступен
- [ ] База данных `utilities_db` создана
- [ ] Composer зависимости установлены
- [ ] Токен бота корректный
- [ ] Папки `logs/` и `mysql/` существуют и доступны для записи
- [ ] PHP версия 8.1 или выше

---

## 🎯 Следующие шаги после тестирования

1. ✅ Просмотрите отчет: `TELEGRAM_BOT_TEST_REPORT.md`
2. ✅ Проверьте логи на наличие ошибок
3. ✅ Изучите созданные MySQL дампы
4. ✅ При необходимости запустите интерактивные тесты
5. ✅ Адаптируйте конфигурацию под ваши нужды

---

## 📚 Дополнительная документация

- `README.md` - основная документация проекта
- `TELEGRAM_BOT_TEST_REPORT.md` - детальный отчет о тестировании
- `TELEGRAM_BOT_TESTING_SUMMARY.md` - краткая сводка результатов
- `POLLING_QUICK_START.md` - быстрый старт с Polling
- `TELEGRAM_BOT_CONVERSATIONS.md` - руководство по диалогам

---

**Удачного тестирования! 🚀**

*Документация обновлена: 2025-11-01*

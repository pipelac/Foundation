# TelegramBot - Информация о миграции файлов

## 📋 Что было сделано (2025-11-07)

### ✅ Перенос в src/TelegramBot/

Все материалы, тесты, примеры и конфигурации модуля TelegramBot перенесены в единую директорию `src/TelegramBot/`.

## 🔄 Список перенесенных файлов

### 1. CLI Скрипты (bin/)

**Откуда:** `/bin/`  
**Куда:** `/src/TelegramBot/bin/`

- `telegram_bot_cleanup_messages.php` ✅
- `telegram_bot_cleanup_conversations.php` ✅
- `convert_ini_to_json.php` ✅

**Изменения в путях:**
- `__DIR__ . '/../autoload.php'` → `__DIR__ . '/../../../autoload.php'`
- `__DIR__ . '/../logs'` → `__DIR__ . '/../../../logs'`
- `__DIR__ . '/../config/mysql.json'` → `__DIR__ . '/../../../config/mysql.json'`
- `__DIR__ . '/../config/telegram_bot_*.json'` → `__DIR__ . '/../config/telegram_bot_*.json'`

### 2. Примеры использования (examples/)

**Откуда:** `/examples/`  
**Куда:** `/src/TelegramBot/examples/`

- `telegram_bot_polling_example.php` ✅
- `telegram_bot_advanced.php` ✅
- `telegram_bot_with_conversations.php` ✅
- `telegram_bot_with_message_storage.php` ✅
- `telegram_bot_access_control.php` ✅
- `telegram_bot_counter_example.php` ✅
- `telegram_example.php` ✅

### 3. Конфигурации (config/)

**Откуда:** `/config/`  
**Куда:** `/src/TelegramBot/config/`

- `telegram_bot_access_control.json` ✅
- `telegram_bot_conversations.json` ✅
- `telegram_bot_message_storage.json` ✅
- `telegram_bot_roles.json` ✅
- `telegram_bot_users.json` ✅

**Примечание:** Файл `/config/telegram.json` НЕ перенесен, так как относится к легаси классу `Telegram.class.php`.

## 🗑️ Удаленные файлы

1. **FIXES_AND_IMPROVEMENTS.md** - Удален из корня проекта (относился к Rss2Tlg модулю)
2. **bin/** - Директория удалена после переноса всех скриптов

## 📁 Итоговая структура

```
src/TelegramBot/
├── bin/                       # CLI скрипты (3 файла)
├── config/                    # Конфигурации (5 файлов)
├── examples/                  # Примеры (7 файлов)
├── Core/                      # Ядро модуля
├── Entities/                  # DTO классы
├── Handlers/                  # Обработчики
├── Keyboards/                 # Билдеры клавиатур
├── Utils/                     # Утилиты
├── Exceptions/                # Исключения
├── README.md                  # Основная документация
├── STRUCTURE.md               # Архитектура
├── INDEX.md                   # Полный индекс
├── QUICKSTART.md              # Быстрый старт
└── MIGRATION_INFO.md          # Этот файл
```

## 🔧 Команды для запуска

### Примеры

```bash
# Было
php examples/telegram_bot_polling_example.php

# Стало
php src/TelegramBot/examples/telegram_bot_polling_example.php
```

### CLI скрипты

```bash
# Было
php bin/telegram_bot_cleanup_messages.php

# Стало
php src/TelegramBot/bin/telegram_bot_cleanup_messages.php
```

### Cron задачи (обновите!)

```bash
# Очистка сообщений (ежедневно в 2:00)
0 2 * * * php /path/to/project/src/TelegramBot/bin/telegram_bot_cleanup_messages.php

# Очистка диалогов (каждый час)
0 * * * * php /path/to/project/src/TelegramBot/bin/telegram_bot_cleanup_conversations.php
```

## ✅ Проверка миграции

### 1. Убедитесь, что старые директории пусты

```bash
# Должно быть пусто
ls /path/to/project/bin/

# Telegram файлов не должно быть
ls /path/to/project/examples/ | grep telegram

# Только telegram.json (для старого Telegram.class.php)
ls /path/to/project/config/ | grep telegram
```

### 2. Проверьте новую структуру

```bash
ls src/TelegramBot/bin/
ls src/TelegramBot/examples/
ls src/TelegramBot/config/
```

### 3. Запустите тестовый пример

```bash
php src/TelegramBot/examples/telegram_bot_polling_example.php
```

## 📚 Навигация по модулю

- [QUICKSTART.md](QUICKSTART.md) - Быстрый старт и примеры запуска
- [INDEX.md](INDEX.md) - Полный индекс файлов и классов
- [README.md](README.md) - Основная документация API
- [STRUCTURE.md](STRUCTURE.md) - Архитектура и структура

## 🎯 Преимущества новой структуры

✅ **Все в одном месте** - весь функционал модуля в одной директории  
✅ **Легче поддерживать** - не нужно искать файлы по всему проекту  
✅ **Удобная навигация** - понятная структура папок  
✅ **Независимость** - модуль можно легко переносить между проектами  
✅ **Чистота кодовой базы** - корневые директории не замусорены  

---

**Дата миграции:** 2025-11-07  
**Версия:** 2.0  
**Статус:** ✅ ЗАВЕРШЕНО

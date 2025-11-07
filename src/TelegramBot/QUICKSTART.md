# TelegramBot - Быстрый старт

## 📦 Что входит в модуль?

```
src/TelegramBot/
├── bin/        → CLI скрипты для обслуживания
├── config/     → Примеры конфигураций
├── examples/   → Примеры использования
├── Core/       → Ядро (API, Polling, Webhook, Storage)
├── Entities/   → DTO классы (Message, User, Chat)
├── Handlers/   → Обработчики событий
├── Keyboards/  → Билдеры клавиатур
├── Utils/      → Утилиты
└── Exceptions/ → Исключения
```

## 🚀 Запуск примеров

### 1. Базовый polling бот

```bash
# Отредактируйте токен в файле
nano src/TelegramBot/examples/telegram_bot_polling_example.php

# Запустите
php src/TelegramBot/examples/telegram_bot_polling_example.php
```

### 2. Продвинутый бот

```bash
php src/TelegramBot/examples/telegram_bot_advanced.php
```

### 3. Бот с диалогами (conversations)

```bash
php src/TelegramBot/examples/telegram_bot_with_conversations.php
```

### 4. Бот с хранением сообщений

```bash
php src/TelegramBot/examples/telegram_bot_with_message_storage.php
```

### 5. Бот с контролем доступа

```bash
php src/TelegramBot/examples/telegram_bot_access_control.php
```

## 🔧 CLI Скрипты для обслуживания

### Очистка старых сообщений

```bash
# Вручную
php src/TelegramBot/bin/telegram_bot_cleanup_messages.php

# Cron (ежедневно в 2:00)
0 2 * * * php /path/to/project/src/TelegramBot/bin/telegram_bot_cleanup_messages.php
```

### Очистка устаревших диалогов

```bash
# Вручную
php src/TelegramBot/bin/telegram_bot_cleanup_conversations.php

# Cron (каждый час)
0 * * * * php /path/to/project/src/TelegramBot/bin/telegram_bot_cleanup_conversations.php
```

### Конвертация INI → JSON конфигов

```bash
php src/TelegramBot/bin/convert_ini_to_json.php users.ini roles.ini config/
```

## ⚙️ Конфигурация

Все конфиги в `src/TelegramBot/config/`:

| Файл | Описание |
|------|----------|
| `telegram_bot_access_control.json` | Контроль доступа (вкл/выкл, пути к users/roles) |
| `telegram_bot_users.json` | Список пользователей с их chat_id и ролями |
| `telegram_bot_roles.json` | Роли и разрешенные команды |
| `telegram_bot_conversations.json` | Настройки менеджера диалогов |
| `telegram_bot_message_storage.json` | Настройки хранения сообщений в БД |

## 📖 Полная документация

- [INDEX.md](INDEX.md) - Индекс и навигация
- [README.md](README.md) - Основная документация
- [STRUCTURE.md](STRUCTURE.md) - Архитектура

## 💡 Минимальный код для отправки сообщения

```php
<?php
require_once __DIR__ . '/../../../autoload.php';

use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\Http;
use App\Component\Logger;

$http = new Http(['timeout' => 30]);
$logger = new Logger(['directory' => __DIR__ . '/logs']);
$bot = new TelegramAPI('YOUR_BOT_TOKEN', $http, $logger);

// Отправить сообщение
$bot->sendMessage(123456789, 'Привет из TelegramBot!');
```

## 📋 Требования

- **PHP 8.1+**
- **MySQL/MariaDB** (опционально, для MessageStorage и ConversationManager)
- **Guzzle HTTP** (через Composer)
- **Классы проекта**: Http, Logger, MySQL

## ✅ Статус

- ✅ **PRODUCTION READY**
- ✅ Полностью протестировано
- ✅ Строгая типизация PHP 8.1+
- ✅ Автоматическое создание таблиц БД
- ✅ Детальное логирование

---

**Версия:** 2.0  
**Обновлено:** 2025-11-07

# TelegramBot - Индекс и навигация

## 📁 Структура проекта

```
src/TelegramBot/
├── Core/                      # Ядро системы
│   ├── TelegramAPI.php       # Основной API клиент
│   ├── PollingHandler.php    # Обработчик polling режима
│   ├── WebhookHandler.php    # Обработчик webhook режима
│   ├── MessageStorage.php    # Хранение сообщений в БД
│   ├── ConversationManager.php # Управление диалогами
│   ├── AccessControl.php     # Контроль доступа
│   ├── RateLimiter.php       # Ограничение частоты запросов
│   └── ...
│
├── Entities/                  # Сущности Telegram
│   ├── Message.php
│   ├── User.php
│   ├── Chat.php
│   ├── CallbackQuery.php
│   ├── Update.php
│   └── ...
│
├── Handlers/                  # Обработчики событий
│   ├── MessageHandler.php
│   ├── CallbackQueryHandler.php
│   ├── MediaHandler.php
│   └── TextHandler.php
│
├── Keyboards/                 # Конструкторы клавиатур
│   ├── InlineKeyboardBuilder.php
│   └── ReplyKeyboardBuilder.php
│
├── Utils/                     # Утилиты
│   └── FileDownloader.php
│
├── Exceptions/                # Исключения
│   ├── TelegramBotException.php
│   ├── ApiException.php
│   ├── AccessControlException.php
│   └── ...
│
├── bin/                       # CLI скрипты
│   ├── telegram_bot_cleanup_messages.php      # Очистка старых сообщений
│   ├── telegram_bot_cleanup_conversations.php # Очистка устаревших диалогов
│   └── convert_ini_to_json.php                # Конвертация INI → JSON
│
├── config/                    # Примеры конфигураций
│   ├── telegram_bot_access_control.json
│   ├── telegram_bot_conversations.json
│   ├── telegram_bot_message_storage.json
│   ├── telegram_bot_roles.json
│   └── telegram_bot_users.json
│
├── examples/                  # Примеры использования
│   ├── telegram_bot_polling_example.php       # Базовый polling бот
│   ├── telegram_bot_advanced.php              # Продвинутый бот
│   ├── telegram_bot_with_conversations.php    # Бот с диалогами
│   ├── telegram_bot_with_message_storage.php  # Бот с хранением сообщений
│   ├── telegram_bot_access_control.php        # Контроль доступа
│   ├── telegram_bot_counter_example.php       # Простой счетчик
│   └── telegram_example.php                   # Базовый пример
│
├── README.md                  # Основная документация
├── STRUCTURE.md               # Структура и архитектура
└── INDEX.md                   # Этот файл
```

## 🚀 Быстрый старт

### 1. Базовый пример

```php
<?php
require_once __DIR__ . '/../../../autoload.php';

use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\Http;
use App\Component\Logger;

$config = [
    'logger' => ['directory' => __DIR__ . '/logs'],
];

$http = new Http($config);
$logger = new Logger($config['logger']);
$bot = new TelegramAPI('YOUR_BOT_TOKEN', $http, $logger);

// Отправка сообщения
$bot->sendMessage(123456789, 'Привет!');
```

### 2. Polling бот

```bash
php src/TelegramBot/examples/telegram_bot_polling_example.php
```

### 3. Бот с диалогами

```bash
php src/TelegramBot/examples/telegram_bot_with_conversations.php
```

## 🛠️ CLI Скрипты

### Очистка старых сообщений

```bash
# Запуск вручную
php src/TelegramBot/bin/telegram_bot_cleanup_messages.php

# Cron (ежедневно в 2:00)
0 2 * * * php /path/to/project/src/TelegramBot/bin/telegram_bot_cleanup_messages.php
```

### Очистка устаревших диалогов

```bash
# Запуск вручную
php src/TelegramBot/bin/telegram_bot_cleanup_conversations.php

# Cron (каждый час)
0 * * * * php /path/to/project/src/TelegramBot/bin/telegram_bot_cleanup_conversations.php
```

### Конвертация INI → JSON

```bash
php src/TelegramBot/bin/convert_ini_to_json.php users.ini roles.ini config/
```

## 📋 Конфигурация

Все конфигурационные файлы находятся в `src/TelegramBot/config/`:

- **telegram_bot_access_control.json** - Настройки контроля доступа
- **telegram_bot_conversations.json** - Настройки менеджера диалогов
- **telegram_bot_message_storage.json** - Настройки хранения сообщений
- **telegram_bot_roles.json** - Определение ролей
- **telegram_bot_users.json** - Список пользователей

## 📚 Документация

- [README.md](README.md) - Основная документация и API
- [STRUCTURE.md](STRUCTURE.md) - Архитектура и структура классов

## ✅ Основные возможности

- ✅ Полная реализация Telegram Bot API
- ✅ Polling и Webhook режимы
- ✅ Хранение сообщений в БД (MessageStorage)
- ✅ Управление диалогами (ConversationManager)
- ✅ Контроль доступа на основе ролей (AccessControl)
- ✅ Rate Limiting
- ✅ Inline и Reply клавиатуры
- ✅ Обработка медиа файлов
- ✅ Автоматическое создание таблиц БД
- ✅ Логирование всех операций

## 🔧 Зависимости

- **PHP 8.1+**
- **MySQL/MariaDB** (для MessageStorage, ConversationManager)
- **App\Component\Http** - HTTP клиент
- **App\Component\Logger** - Система логирования
- **App\Component\MySQL** - Работа с БД

## 📝 Примеры использования

См. папку `examples/` для детальных примеров:

1. **telegram_bot_polling_example.php** - Базовый polling бот
2. **telegram_bot_advanced.php** - Расширенный функционал
3. **telegram_bot_with_conversations.php** - Работа с диалогами
4. **telegram_bot_with_message_storage.php** - Хранение истории
5. **telegram_bot_access_control.php** - Контроль доступа
6. **telegram_bot_counter_example.php** - Простой счетчик

## 🔐 Безопасность

- ✅ Prepared statements для всех SQL запросов
- ✅ Валидация всех входящих данных
- ✅ Rate limiting для защиты от спама
- ✅ Контроль доступа на основе ролей
- ✅ Логирование всех действий пользователей

## 🎯 Production Ready

Модуль полностью готов к использованию в production:
- ✅ Тестирован на реальных проектах
- ✅ Автоматическое создание таблиц
- ✅ Graceful degradation
- ✅ Детальное логирование
- ✅ CLI скрипты для обслуживания

---

**Версия:** 2.0  
**Обновлено:** 2025-11-07  
**Статус:** ✅ PRODUCTION READY

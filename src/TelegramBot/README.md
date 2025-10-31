# TelegramBot - Модульная система для работы с Telegram Bot API

Полнофункциональная модульная система с строгой типизацией для создания Telegram ботов на PHP 8.1+.

## Быстрый старт

### 1. Базовая настройка

```php
use App\Component\Http;
use App\Component\Logger;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Core\WebhookHandler;

// Инициализация зависимостей
$logger = new Logger(['directory' => __DIR__ . '/logs']);
$http = new Http(['timeout' => 30], $logger);

// Создание API клиента
$api = new TelegramAPI('YOUR_BOT_TOKEN', $http, $logger);

// Webhook обработчик
$webhookHandler = new WebhookHandler($logger);
```

### 2. Простой эхо-бот

```php
use App\Component\TelegramBot\Handlers\MessageHandler;

$messageHandler = new MessageHandler($api, $logger);
$update = $webhookHandler->getUpdate();

$messageHandler->handleText($update, function($message, $text) use ($messageHandler) {
    $messageHandler->reply($message, "Вы написали: $text");
});

$webhookHandler->sendResponse();
```

### 3. Бот с командами

```php
use App\Component\TelegramBot\Handlers\TextHandler;
use App\Component\TelegramBot\Keyboards\InlineKeyboardBuilder;

$textHandler = new TextHandler($api, $logger);
$update = $webhookHandler->getUpdate();

// Команда /start
$textHandler->handleCommand($update, 'start', function($message) use ($api) {
    $keyboard = InlineKeyboardBuilder::makeSimple([
        '📚 Помощь' => 'help',
        '⚙️ Настройки' => 'settings',
    ]);
    
    $api->sendMessage(
        $message->chat->id,
        "Привет! Выбери действие:",
        ['reply_markup' => $keyboard]
    );
});
```

### 4. Обработка callback кнопок

```php
use App\Component\TelegramBot\Handlers\CallbackQueryHandler;

$callbackHandler = new CallbackQueryHandler($api, $logger);

$callbackHandler->handleAction($update, 'help', function($query) use ($callbackHandler) {
    $callbackHandler->answerAndEdit($query, 'Это справка по боту!');
});

$callbackHandler->handleAction($update, 'settings', function($query) use ($callbackHandler) {
    $callbackHandler->answerWithText($query, 'Настройки пока недоступны');
});
```

## Структура модуля

```
src/TelegramBot/
├── Exceptions/         # Исключения
│   ├── TelegramBotException.php
│   ├── ApiException.php
│   ├── ValidationException.php
│   ├── FileException.php
│   └── WebhookException.php
├── Entities/          # DTO классы
│   ├── User.php
│   ├── Chat.php
│   ├── Message.php
│   ├── Media.php
│   ├── CallbackQuery.php
│   └── Update.php
├── Utils/             # Утилиты
│   ├── Validator.php
│   ├── Parser.php
│   └── FileDownloader.php
├── Keyboards/         # Построители клавиатур
│   ├── InlineKeyboardBuilder.php
│   └── ReplyKeyboardBuilder.php
├── Core/              # Ядро системы
│   ├── TelegramAPI.php
│   └── WebhookHandler.php
└── Handlers/          # Обработчики событий
    ├── MessageHandler.php
    ├── CallbackQueryHandler.php
    ├── TextHandler.php
    └── MediaHandler.php
```

## Основные возможности

### ✅ Строгая типизация
Все параметры и возвращаемые значения строго типизированы (PHP 8.1+)

### ✅ Валидация на каждом уровне
Автоматическая проверка всех параметров с выбросом исключений

### ✅ DTO сущности
Удобные объекты для всех типов данных Telegram API

### ✅ Fluent API для клавиатур
Интуитивное создание inline и reply клавиатур

### ✅ Обработчики событий
Специализированные классы для разных типов обновлений

### ✅ Загрузка файлов
Встроенный загрузчик файлов с серверов Telegram

### ✅ Парсинг текста
Команды, callback data, упоминания, хештеги, URL

### ✅ Логирование
Интеграция с системой логирования проекта

## Примеры использования

См. полную документацию: `/docs/TELEGRAM_BOT_MODULE.md`

Рабочий пример: `/examples/telegram_bot_advanced.php`

## Требования

- PHP 8.1+
- Composer зависимости: guzzlehttp/guzzle
- Классы проекта: Http, Logger

## Лицензия

Часть проекта, см. LICENSE.

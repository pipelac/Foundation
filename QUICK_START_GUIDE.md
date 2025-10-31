# Быстрый старт с модулем TelegramBot

## Минимальный пример (5 строк кода)

```php
<?php
require 'autoload.php';

use App\Component\{Http, Logger};
use App\Component\TelegramBot\Core\TelegramAPI;

$logger = new Logger(['directory' => __DIR__ . '/logs']);
$http = new Http(['timeout' => 30], $logger);
$api = new TelegramAPI('YOUR_BOT_TOKEN', $http, $logger);

// Отправка сообщения
$api->sendMessage(YOUR_CHAT_ID, 'Привет от TelegramBot модуля!');
```

## Пример бота с командами

```php
<?php
require 'autoload.php';

use App\Component\{Http, Logger};
use App\Component\TelegramBot\{
    Core\TelegramAPI,
    Core\WebhookHandler,
    Handlers\TextHandler,
    Keyboards\InlineKeyboardBuilder
};

// Инициализация
$logger = new Logger(['directory' => 'logs']);
$http = new Http(['timeout' => 30], $logger);
$api = new TelegramAPI('YOUR_BOT_TOKEN', $http, $logger);
$textHandler = new TextHandler($api, $logger);
$webhookHandler = new WebhookHandler($logger);

// Получение обновления
$update = $webhookHandler->getUpdate();

// Обработка команды /start
$textHandler->handleCommand($update, 'start', function($message) use ($api) {
    $keyboard = InlineKeyboardBuilder::makeSimple([
        'Кнопка 1' => 'action1',
        'Кнопка 2' => 'action2',
    ]);
    
    $api->sendMessage(
        $message->chat->id,
        "Привет! Выбери действие:",
        ['reply_markup' => $keyboard]
    );
});

// Отправка ответа webhook
$webhookHandler->sendResponse();
```

## Пример обработки callback

```php
use App\Component\TelegramBot\Handlers\CallbackQueryHandler;

$callbackHandler = new CallbackQueryHandler($api, $logger);

// Обработка нажатия на кнопку
$callbackHandler->handleAction($update, 'action1', function($query) use ($callbackHandler) {
    $callbackHandler->answerAndEdit(
        $query,
        "Вы выбрали действие 1!"
    );
});
```

## Пример работы с медиа

```php
use App\Component\TelegramBot\{
    Handlers\MediaHandler,
    Utils\FileDownloader
};

$fileDownloader = new FileDownloader('YOUR_BOT_TOKEN', $http, $logger);
$mediaHandler = new MediaHandler($api, $fileDownloader, $logger);

// Обработка фото
$messageHandler->handlePhoto($update, function($message) use ($mediaHandler, $api) {
    $photo = $mediaHandler->getBestPhoto($message);
    
    // Скачать фото
    $path = $mediaHandler->downloadPhoto($message, "/tmp/photo.jpg");
    
    $api->sendMessage(
        $message->chat->id,
        "Фото получено! Размер: {$photo->width}x{$photo->height}"
    );
});
```

## Структура минимального проекта

```
my-telegram-bot/
├── config/
│   └── telegram.json        # Конфигурация
├── logs/                    # Логи
├── webhook.php              # Webhook endpoint
└── composer.json
```

### webhook.php
```php
<?php
require 'vendor/autoload.php';

use App\Component\{Http, Logger};
use App\Component\TelegramBot\{
    Core\TelegramAPI,
    Core\WebhookHandler,
    Handlers\TextHandler,
    Handlers\CallbackQueryHandler
};

// Загрузка конфига
$config = json_decode(file_get_contents('config/telegram.json'), true);

// Инициализация
$logger = new Logger(['directory' => 'logs']);
$http = new Http(['timeout' => 30], $logger);
$api = new TelegramAPI($config['token'], $http, $logger);
$textHandler = new TextHandler($api, $logger);
$callbackHandler = new CallbackQueryHandler($api, $logger);
$webhookHandler = new WebhookHandler($logger);

// Получение обновления
$update = $webhookHandler->getUpdate();

// Команда /start
$textHandler->handleCommand($update, 'start', function($message) use ($api) {
    $api->sendMessage($message->chat->id, "Бот запущен!");
});

// Обычный текст
$textHandler->handlePlainText($update, function($message, $text) use ($api) {
    $api->sendMessage($message->chat->id, "Вы написали: $text");
});

// Callback queries
$callbackHandler->handle($update, function($query) use ($callbackHandler) {
    $callbackHandler->answerWithText($query, "Кнопка нажата!");
});

// Ответ webhook
$webhookHandler->sendResponse();
```

## Полезные ссылки

- **Полная документация**: `/docs/TELEGRAM_BOT_MODULE.md`
- **Примеры**: `/examples/telegram_bot_advanced.php`
- **Тесты**: `/tests/telegram_bot_comprehensive_test.php`
- **API Reference**: https://core.telegram.org/bots/api

## Настройка webhook

```bash
# Установка webhook
curl -X POST "https://api.telegram.org/botYOUR_BOT_TOKEN/setWebhook" \
     -d "url=https://yourdomain.com/webhook.php"

# Проверка webhook
curl "https://api.telegram.org/botYOUR_BOT_TOKEN/getWebhookInfo"
```

## Отладка

```php
// Включение детального логирования
$logger = new Logger([
    'directory' => 'logs',
    'fileName' => 'telegram_debug.log'
]);

// Проверка бота
$botInfo = $api->getMe();
echo "Bot: @{$botInfo->username}\n";

// Получение последних обновлений
$updates = $api->getUpdates(['limit' => 10]);
print_r($updates);
```

## Советы

1. **Всегда используйте логгер** для отладки
2. **Валидируйте входные данные** через Validator
3. **Обрабатывайте исключения** на каждом уровне
4. **Используйте типизацию** для безопасности
5. **Читайте логи** при возникновении проблем

## Готовые шаблоны

### Эхо-бот
```php
$textHandler->handlePlainText($update, function($message, $text) use ($api) {
    $api->sendMessage($message->chat->id, "Эхо: $text");
});
```

### Бот с меню
```php
$keyboard = (new InlineKeyboardBuilder())
    ->addCallbackButton('📝 Помощь', 'help')
    ->addCallbackButton('⚙️ Настройки', 'settings')
    ->row()
    ->addCallbackButton('📊 Статистика', 'stats')
    ->build();

$api->sendMessage($chatId, "Главное меню:", ['reply_markup' => $keyboard]);
```

### Обработчик команд
```php
$commands = ['start', 'help', 'settings'];

foreach ($commands as $cmd) {
    $textHandler->handleCommand($update, $cmd, function($message, $args) use ($api, $cmd) {
        $api->sendMessage($message->chat->id, "Команда /$cmd выполнена!");
    });
}
```

---

**Готово! Начните с минимального примера и расширяйте по мере необходимости.**

🎯 **Успешной разработки!**

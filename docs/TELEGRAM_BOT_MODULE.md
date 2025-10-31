# Модульная система TelegramBot

Полнофункциональная модульная система для работы с Telegram Bot API с строгой типизацией и слоистой архитектурой.

## Архитектура

Система состоит из 7 слоев:

### 1. Exceptions (Исключения)
Пользовательские исключения для всех типов ошибок:

- **TelegramBotException** - базовое исключение модуля
- **ApiException** - ошибки Telegram Bot API с контекстом
- **ValidationException** - ошибки валидации с указанием поля и значения
- **FileException** - ошибки работы с файлами
- **WebhookException** - ошибки обработки webhook

### 2. Entities (Сущности)
DTO классы с полной типизацией для всех типов данных:

- **User** - информация о пользователе/боте
- **Chat** - информация о чате (private, group, supergroup, channel)
- **Message** - структура сообщения со всеми полями
- **Media** - универсальная структура для медиа-файлов (фото, видео, аудио, документы)
- **CallbackQuery** - нажатия на inline кнопки
- **Update** - входящие события от Telegram

Все сущности имеют:
- Строгую типизацию всех свойств
- Метод `fromArray()` для создания из данных API
- Метод `toArray()` для сериализации
- Удобные методы проверки состояния (isText(), hasPhoto(), etc.)

### 3. Utils (Утилиты)
Вспомогательные классы:

#### Validator
Валидация с выбросом исключений:
- Токен бота
- Chat ID
- Текст и подписи
- Callback data
- Файлы и их размеры
- Опросы и клавиатуры

#### Parser
Парсинг текста и данных:
- Команды и аргументы
- Callback data (поддержка форматов: `action`, `action:value`, `action:key=val,key2=val2`)
- Упоминания (@username)
- Хештеги (#tag)
- URL
- Экранирование для MarkdownV2 и HTML
- Обрезка текста

#### FileDownloader
Загрузка файлов с серверов Telegram:
- Получение информации о файле
- Скачивание файлов по file_id
- Скачивание во временную директорию
- Получение прямых ссылок на файлы
- Получение размера файлов

### 4. Keyboards (Клавиатуры)

#### InlineKeyboardBuilder
Построитель inline клавиатур (fluent API):
```php
$keyboard = (new InlineKeyboardBuilder())
    ->addCallbackButton('Button 1', 'callback_data_1')
    ->addUrlButton('Button 2', 'https://example.com')
    ->row()
    ->addWebAppButton('Open App', 'https://app.example.com')
    ->build();
```

Поддерживаемые типы кнопок:
- Callback кнопки
- URL кнопки
- Web App кнопки
- Switch inline кнопки
- Login кнопки

Вспомогательные методы:
- `makeSimple()` - простая клавиатура (одна кнопка в ряд)
- `makeGrid()` - клавиатура-сетка

#### ReplyKeyboardBuilder
Построитель reply клавиатур:
```php
$keyboard = (new ReplyKeyboardBuilder())
    ->addButton('Text Button')
    ->addContactButton('Share Contact')
    ->row()
    ->addLocationButton('Share Location')
    ->resizeKeyboard()
    ->oneTime()
    ->build();
```

Поддерживаемые типы кнопок:
- Текстовые кнопки
- Запрос контакта
- Запрос местоположения
- Запрос опроса
- Web App кнопки

Параметры:
- Автоматический размер
- Одноразовая клавиатура
- Placeholder
- Селективный режим
- Персистентность

### 5. Core (Ядро)

#### TelegramAPI
Полный API клиент со строгой типизацией:

**Основные методы:**
- `getMe()` - информация о боте
- `sendMessage()` - отправка текста
- `sendPhoto()`, `sendVideo()`, `sendAudio()`, `sendDocument()` - отправка медиа
- `sendPoll()` - отправка опросов
- `editMessageText()`, `editMessageCaption()`, `editMessageReplyMarkup()` - редактирование
- `deleteMessage()` - удаление сообщений
- `answerCallbackQuery()` - ответ на callback
- `setWebhook()`, `deleteWebhook()`, `getWebhookInfo()` - управление webhook

**Особенности:**
- Автоматическая валидация параметров
- Поддержка file_id, URL и локальных файлов
- Автоматический выбор multipart для файлов
- Обработка ошибок с подробным логированием
- Возврат типизированных сущностей

#### WebhookHandler
Обработчик входящих webhook запросов:

```php
$handler = new WebhookHandler($logger);
$handler->setSecretToken('your_secret');

// Получение обновления
$update = $handler->getUpdate();

// Или безопасная обработка
$update = $handler->handleSafely();

// Отправка ответа
$handler->sendResponse();
```

**Возможности:**
- Валидация секретного токена
- Парсинг JSON из php://input
- Проверка валидности запроса
- Безопасный режим обработки
- Поддержка fastcgi_finish_request

### 6. Handlers (Обработчики)

#### MessageHandler
Обработка текстовых и медиа сообщений:

```php
$handler = new MessageHandler($api, $logger);

// Обработка любых сообщений
$handler->handle($update, function($message) {
    // ...
});

// Только текст
$handler->handleText($update, function($message, $text) {
    // ...
});

// Только фото
$handler->handlePhoto($update, function($message) {
    // ...
});

// Ответ на сообщение
$handler->reply($message, 'Ответ');

// Отправка сообщения
$handler->send($message, 'Сообщение');
```

#### CallbackQueryHandler
Обработка нажатий на inline кнопки:

```php
$handler = new CallbackQueryHandler($api, $logger);

// Обработка любого callback
$handler->handle($update, function($query) {
    // ...
});

// Обработка определенного действия
$handler->handleAction($update, 'action_name', function($query, $params) {
    // $params содержит распарсенные данные
});

// Ответ на callback
$handler->answer($query);
$handler->answerWithText($query, 'Текст');
$handler->answerWithAlert($query, 'Alert!');

// Редактирование сообщения
$handler->editText($query, 'Новый текст');
$handler->editKeyboard($query, $newKeyboard);
$handler->removeKeyboard($query);

// Комбинированные методы
$handler->answerAndEdit($query, 'Новый текст');
```

#### TextHandler
Обработка текста и команд:

```php
$handler = new TextHandler($api, $logger);

// Конкретная команда
$handler->handleCommand($update, 'start', function($message, $args) {
    // ...
});

// Любая команда
$handler->handleAnyCommand($update, function($message, $command, $args) {
    // ...
});

// Текст с подстрокой
$handler->handleContains($update, 'hello', function($message, $text) {
    // ...
});

// Регулярное выражение
$handler->handlePattern($update, '/\d+/', function($message, $matches) {
    // ...
});

// Обычный текст (не команды)
$handler->handlePlainText($update, function($message, $text) {
    // ...
});

// Извлечение данных
$mentions = $handler->extractMentions($message);
$hashtags = $handler->extractHashtags($message);
$urls = $handler->extractUrls($message);
```

#### MediaHandler
Обработка и загрузка медиа-файлов:

```php
$handler = new MediaHandler($api, $fileDownloader, $logger);

// Получение лучшего качества фото
$photo = $handler->getBestPhoto($message);

// Скачивание файлов
$path = $handler->downloadPhoto($message, '/path/to/save.jpg');
$path = $handler->downloadVideo($message, '/path/to/save.mp4');
$path = $handler->downloadAudio($message, '/path/to/save.mp3');
$path = $handler->downloadDocument($message, '/path/to/save.pdf');

// Скачивание любого медиа во временную директорию
$path = $handler->downloadAnyMedia($message);

// Получение информации
$url = $handler->getFileUrl($fileId);
$size = $handler->getFileSize($fileId);
$hasMedia = $handler->hasAnyMedia($message);
$type = $handler->getMediaType($message);
```

## Примеры использования

### Простой бот с командами

```php
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Core\WebhookHandler;
use App\Component\TelegramBot\Handlers\TextHandler;
use App\Component\TelegramBot\Keyboards\InlineKeyboardBuilder;

$api = new TelegramAPI($token, $http, $logger);
$webhookHandler = new WebhookHandler($logger);
$textHandler = new TextHandler($api, $logger);

$update = $webhookHandler->getUpdate();

// Команда /start
$textHandler->handleCommand($update, 'start', function($message, $args) use ($api) {
    $keyboard = InlineKeyboardBuilder::makeSimple([
        'Помощь' => 'help',
        'Настройки' => 'settings',
    ]);
    
    $api->sendMessage(
        $message->chat->id,
        'Привет! Я бот.',
        ['reply_markup' => $keyboard]
    );
});

$webhookHandler->sendResponse();
```

### Обработка callback с параметрами

```php
use App\Component\TelegramBot\Handlers\CallbackQueryHandler;

$callbackHandler = new CallbackQueryHandler($api, $logger);

// Создание кнопки с параметрами
$data = Parser::buildCallbackData('edit', ['id' => 123, 'type' => 'post']);
// Результат: "edit:id=123,type=post"

// Обработка
$callbackHandler->handleAction($update, 'edit', function($query, $params) {
    // $params = ['action' => 'edit', 'id' => '123', 'type' => 'post']
    $id = $params['id'];
    $type = $params['type'];
});
```

### Загрузка и обработка медиа

```php
use App\Component\TelegramBot\Handlers\MessageHandler;
use App\Component\TelegramBot\Handlers\MediaHandler;

$messageHandler = new MessageHandler($api, $logger);
$mediaHandler = new MediaHandler($api, $fileDownloader, $logger);

$messageHandler->handlePhoto($update, function($message) use ($api, $mediaHandler) {
    $photo = $mediaHandler->getBestPhoto($message);
    
    // Скачать фото
    $path = $mediaHandler->downloadPhoto($message, "/tmp/photo_{$photo->fileId}.jpg");
    
    if ($path) {
        $api->sendMessage(
            $message->chat->id,
            "Фото сохранено: {$photo->width}x{$photo->height}, " .
            "{$photo->getFileSizeInMB()} МБ"
        );
    }
});
```

### Создание сложных клавиатур

```php
// Inline клавиатура
$inline = (new InlineKeyboardBuilder())
    ->addCallbackButton('📝 Редактировать', 'edit:123')
    ->addCallbackButton('🗑️ Удалить', 'delete:123')
    ->row()
    ->addUrlButton('🔗 Подробнее', 'https://example.com')
    ->addWebAppButton('🚀 Открыть', 'https://app.example.com')
    ->build();

// Reply клавиатура
$reply = (new ReplyKeyboardBuilder())
    ->addButton('📱 Главное меню')
    ->addButton('⚙️ Настройки')
    ->row()
    ->addContactButton('📞 Поделиться контактом')
    ->addLocationButton('📍 Отправить местоположение')
    ->row()
    ->addButton('❌ Закрыть')
    ->resizeKeyboard()
    ->oneTime()
    ->placeholder('Выберите действие...')
    ->build();

$api->sendMessage($chatId, 'Выберите:', ['reply_markup' => $reply]);
```

## Обработка ошибок

Все компоненты используют иерархию исключений:

```php
try {
    $api->sendMessage($chatId, $text);
} catch (ValidationException $e) {
    // Ошибка валидации параметров
    $logger->error('Validation error', [
        'field' => $e->getField(),
        'value' => $e->getValue(),
    ]);
} catch (ApiException $e) {
    // Ошибка Telegram API
    $logger->error('API error', [
        'code' => $e->getCode(),
        'context' => $e->getContext(),
    ]);
} catch (TelegramBotException $e) {
    // Любая другая ошибка модуля
    $logger->error('Bot error', ['message' => $e->getMessage()]);
}
```

## Интеграция с существующим Telegram.class.php

Модульная система полностью независима от `Telegram.class.php` и может использоваться параллельно:

- `Telegram.class.php` - для простых случаев и быстрой отправки
- `TelegramBot/*` - для сложных ботов с обработкой событий

Можно постепенно мигрировать, используя оба подхода одновременно.

## Требования

- PHP 8.1+
- Composer зависимости: guzzlehttp/guzzle
- Классы проекта: Http, Logger

## Лицензия

См. LICENSE файл проекта.

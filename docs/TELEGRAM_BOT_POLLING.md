# PollingHandler - Работа бота в режиме Long Polling

Класс `PollingHandler` предоставляет удобный интерфейс для работы Telegram бота в режиме long polling через метод `getUpdates` API.

## Содержание

- [Введение](#введение)
- [Режимы работы: Webhook vs Polling](#режимы-работы-webhook-vs-polling)
- [Инициализация](#инициализация)
- [Конфигурация](#конфигурация)
- [Основные методы](#основные-методы)
- [Примеры использования](#примеры-использования)
- [Обработка ошибок](#обработка-ошибок)
- [Логирование](#логирование)
- [Лучшие практики](#лучшие-практики)

## Введение

`PollingHandler` автоматизирует получение и обработку обновлений от Telegram Bot API в режиме polling:

- ✅ Long polling с настраиваемым timeout
- ✅ Автоматическое управление offset
- ✅ Фильтрация типов обновлений
- ✅ Обработка ошибок и восстановление соединения
- ✅ Полное логирование операций
- ✅ Типобезопасность (PHP 8.1+)

## Режимы работы: Webhook vs Polling

### Webhook
- ✅ Instant delivery (мгновенная доставка)
- ✅ Меньше нагрузка на сервер
- ❌ Требует HTTPS и публичный URL
- ❌ Сложнее настройка и отладка

### Polling (Long Polling)
- ✅ Простая настройка и отладка
- ✅ Работает локально без HTTPS
- ✅ Не требует публичный URL
- ❌ Постоянное соединение с API
- ❌ Небольшая задержка (timeout)

**Рекомендации:**
- **Development/Testing**: Используйте polling
- **Production**: Используйте webhook (если есть HTTPS)
- **Небольшие боты**: Polling подходит отлично
- **Высоконагруженные боты**: Предпочтительнее webhook

## Инициализация

```php
use App\Component\Http;
use App\Component\Logger;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Core\PollingHandler;

// Зависимости
$logger = new Logger(['directory' => __DIR__ . '/logs']);
$http = new Http(['timeout' => 60], $logger);
$api = new TelegramAPI('YOUR_BOT_TOKEN', $http, $logger);

// Создание PollingHandler
$polling = new PollingHandler($api, $logger);
```

## Конфигурация

### setTimeout(int $timeout): self

Устанавливает timeout для long polling (0-50 секунд).

```php
$polling->setTimeout(30); // 30 секунд (рекомендуется)
```

**Особенности:**
- При timeout=0: short polling (возвращается сразу)
- При timeout>0: long polling (ждет до указанного времени)
- Максимум: 50 секунд (ограничение Telegram API)
- Рекомендуется: 20-30 секунд

### setLimit(int $limit): self

Устанавливает максимальное количество обновлений за один запрос (1-100).

```php
$polling->setLimit(100); // По умолчанию
```

### setAllowedUpdates(array $allowedUpdates): self

Фильтрует типы получаемых обновлений.

```php
// Только сообщения и callback запросы
$polling->setAllowedUpdates(['message', 'callback_query']);

// Все типы (по умолчанию)
$polling->setAllowedUpdates([]);
```

**Доступные типы:**
- `message` - Новые сообщения
- `edited_message` - Отредактированные сообщения
- `channel_post` - Посты в каналах
- `edited_channel_post` - Отредактированные посты
- `callback_query` - Callback запросы от inline кнопок
- `inline_query` - Inline запросы
- `chosen_inline_result` - Выбранные inline результаты
- `shipping_query` - Запросы доставки
- `pre_checkout_query` - Предварительные запросы оплаты
- `poll` - Обновления опросов
- `poll_answer` - Ответы на опросы
- `my_chat_member` - Изменения статуса бота
- `chat_member` - Изменения участников чата

### setOffset(int $offset): self

Устанавливает начальный offset для получения обновлений.

```php
$polling->setOffset(12345);
```

## Основные методы

### getUpdates(): array<Update>

Получает массив обновлений через один запрос к API.

```php
$updates = $polling->getUpdates();

foreach ($updates as $update) {
    echo "Update ID: {$update->updateId}\n";
}
```

**Особенности:**
- Автоматически обновляет offset
- Возвращает массив объектов `Update`
- При ошибке выбрасывает `ApiException`

### startPolling(callable $handler, ?int $maxIterations = null): void

Запускает цикл получения и обработки обновлений.

```php
$polling->startPolling(function(Update $update) use ($api) {
    if ($update->isMessage()) {
        $message = $update->message;
        $api->sendMessage(
            $message->chat->id,
            "Получено: " . $message->text
        );
    }
}, null); // null = бесконечный цикл
```

**Параметры:**
- `$handler`: Функция обработки `function(Update $update): void`
- `$maxIterations`: Максимум итераций (null = бесконечно)

**Особенности:**
- Продолжает работу при ошибках
- Автоматическая пауза при критических ошибках (5 сек)
- Можно остановить через `stopPolling()`

### pollOnce(): array<Update>

Выполняет одну итерацию polling и возвращает обновления.

```php
$updates = $polling->pollOnce();
```

Полезно для интеграции в собственный цикл обработки.

### stopPolling(): void

Останавливает цикл polling.

```php
$polling->stopPolling();
```

Обычно вызывается из обработчика при получении команды `/stop`.

### isPolling(): bool

Проверяет, активен ли polling.

```php
if ($polling->isPolling()) {
    echo "Polling активен\n";
}
```

### skipPendingUpdates(): int

Пропускает все ожидающие обновления.

```php
$skipped = $polling->skipPendingUpdates();
echo "Пропущено: $skipped обновлений\n";
```

**Применение:**
- При первом запуске бота
- Чтобы не обрабатывать старые сообщения
- После длительного простоя

### reset(): void

Сбрасывает состояние обработчика (offset и флаги).

```php
$polling->reset();
```

### getOffset(): int

Возвращает текущий offset.

```php
$currentOffset = $polling->getOffset();
```

## Примеры использования

### Простой эхо-бот

```php
$polling->setTimeout(30);
$polling->skipPendingUpdates();

$polling->startPolling(function(Update $update) use ($api) {
    if ($update->isMessage() && $update->message->text) {
        $api->sendMessage(
            $update->message->chat->id,
            "Эхо: " . $update->message->text
        );
    }
});
```

### Бот с командами

```php
$polling->startPolling(function(Update $update) use ($api, $polling) {
    if (!$update->isMessage()) {
        return;
    }
    
    $message = $update->message;
    $text = $message->text ?? '';
    $chatId = $message->chat->id;
    
    if (str_starts_with($text, '/')) {
        $command = strtolower(trim($text, '/'));
        
        match($command) {
            'start' => $api->sendMessage($chatId, "Привет! 👋"),
            'help' => $api->sendMessage($chatId, "Команды: /start, /help, /stop"),
            'stop' => function() use ($api, $chatId, $polling) {
                $api->sendMessage($chatId, "До свидания! 👋");
                $polling->stopPolling();
            },
            default => $api->sendMessage($chatId, "Неизвестная команда: $command"),
        };
    } else {
        $api->sendMessage($chatId, "Отправьте команду");
    }
});
```

### Обработка callback кнопок

```php
use App\Component\TelegramBot\Keyboards\InlineKeyboardBuilder;

$polling->startPolling(function(Update $update) use ($api) {
    // Показываем меню
    if ($update->isMessage() && $update->message->text === '/menu') {
        $keyboard = InlineKeyboardBuilder::makeSimple([
            '✅ Вариант 1' => 'choice_1',
            '❌ Вариант 2' => 'choice_2',
        ]);
        
        $api->sendMessage(
            $update->message->chat->id,
            "Выберите:",
            ['reply_markup' => $keyboard]
        );
    }
    
    // Обрабатываем выбор
    if ($update->isCallbackQuery()) {
        $query = $update->callbackQuery;
        
        $api->answerCallbackQuery($query->id, [
            'text' => 'Выбор принят!',
        ]);
        
        $api->editMessageText(
            $query->message->chat->id,
            $query->message->messageId,
            "Вы выбрали: " . $query->data
        );
    }
});
```

### Собственный цикл обработки

```php
while (true) {
    $updates = $polling->getUpdates();
    
    foreach ($updates as $update) {
        // Ваша логика
        processUpdate($update);
    }
    
    // Можно добавить дополнительную логику
    checkScheduledTasks();
    cleanupOldData();
}
```

## Обработка ошибок

Все ошибки логируются автоматически. Метод `startPolling()` продолжает работу при ошибках.

### Обработка ошибок в handler

```php
$polling->startPolling(function(Update $update) use ($api, $logger) {
    try {
        // Ваш код
        if ($update->isMessage()) {
            $api->sendMessage(
                $update->message->chat->id,
                "OK"
            );
        }
    } catch (Exception $e) {
        // Логируем ошибку
        $logger->error('Ошибка обработки', [
            'error' => $e->getMessage(),
            'update_id' => $update->updateId,
        ]);
        
        // Уведомляем пользователя
        if ($update->isMessage()) {
            $api->sendMessage(
                $update->message->chat->id,
                "❌ Произошла ошибка"
            );
        }
    }
});
```

### Обработка критических ошибок API

```php
try {
    $polling->startPolling($handler);
} catch (\App\Component\TelegramBot\Exceptions\ApiException $e) {
    // Критическая ошибка API (неверный токен, бан и т.д.)
    $logger->error('Критическая ошибка API', [
        'error' => $e->getMessage(),
        'code' => $e->getCode(),
    ]);
    
    // Отправляем уведомление администратору
    sendAdminNotification($e);
}
```

## Логирование

Все операции автоматически логируются:

### Уровень INFO
- Инициализация handler
- Запуск/остановка polling
- Количество полученных обновлений
- Сброс состояния

### Уровень DEBUG
- Каждый запрос getUpdates
- Параметры запросов
- Обработка отдельных обновлений

### Уровень ERROR
- Ошибки API
- Ошибки парсинга
- Критические ошибки

**Пример лога:**
```
2024-01-15T10:00:00+00:00 INFO Инициализация PollingHandler {}
2024-01-15T10:00:00+00:00 DEBUG Установлен timeout для polling {"timeout":30}
2024-01-15T10:00:00+00:00 INFO Запуск polling режима {"timeout":30,"limit":100}
2024-01-15T10:00:05+00:00 DEBUG Запрос обновлений через getUpdates {"offset":0}
2024-01-15T10:00:06+00:00 INFO Получено обновлений через polling {"count":3}
```

## Лучшие практики

### 1. Используйте skipPendingUpdates() при запуске

```php
// Пропускаем старые сообщения
$polling->skipPendingUpdates();

// Начинаем обработку новых
$polling->startPolling($handler);
```

### 2. Всегда удаляйте webhook перед polling

```php
$api->deleteWebhook(true); // true = удалить ожидающие обновления
sleep(1); // Даем серверу время
```

### 3. Используйте разумный timeout

```php
// ✅ Хорошо: баланс между скоростью и нагрузкой
$polling->setTimeout(30);

// ❌ Плохо: слишком короткий (лишние запросы)
$polling->setTimeout(1);

// ❌ Плохо: превышает лимит API
$polling->setTimeout(60);
```

### 4. Фильтруйте ненужные типы обновлений

```php
// Только то, что нужно
$polling->setAllowedUpdates(['message', 'callback_query']);
```

### 5. Обрабатывайте ошибки в handler

```php
$polling->startPolling(function(Update $update) use ($api) {
    try {
        // Ваш код
    } catch (Exception $e) {
        // Обрабатываем, но не прерываем polling
        handleError($e);
    }
});
```

### 6. Используйте graceful shutdown

```php
// Обработка сигналов для корректной остановки
pcntl_signal(SIGTERM, function() use ($polling) {
    $polling->stopPolling();
});

pcntl_signal(SIGINT, function() use ($polling) {
    $polling->stopPolling();
});

$polling->startPolling($handler);
```

### 7. Сохраняйте offset между перезапусками

```php
// При старте
$savedOffset = (int)file_get_contents('offset.txt');
$polling->setOffset($savedOffset);

// В обработчике периодически
file_put_contents('offset.txt', $polling->getOffset());
```

## Troubleshooting

### Не получаю обновления

1. Проверьте, что webhook удален: `$api->deleteWebhook(true)`
2. Проверьте, что бот не заблокирован пользователем
3. Проверьте права бота в чате/группе
4. Проверьте фильтр `allowedUpdates`

### Бот обрабатывает старые сообщения

Используйте `skipPendingUpdates()` при запуске:

```php
$polling->skipPendingUpdates();
```

### Слишком много запросов к API

Увеличьте timeout:

```php
$polling->setTimeout(30); // Вместо 5
```

### Не обрабатываются некоторые типы обновлений

Проверьте фильтр `allowedUpdates`:

```php
// Получать все типы
$polling->setAllowedUpdates([]);
```

## См. также

- [Основная документация TelegramBot](/src/TelegramBot/README.md)
- [Примеры использования](/examples/telegram_bot_polling_example.php)
- [Интерактивный тест](/tests/telegram_bot_polling_interactive_test.php)
- [API Reference](https://core.telegram.org/bots/api#getupdates)

# PollingHandler - Quick Start Guide

## Быстрый старт за 5 минут

### 1. Базовая инициализация

```php
<?php
require_once __DIR__ . '/autoload.php';

use App\Component\Http;
use App\Component\Logger;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Core\PollingHandler;
use App\Component\TelegramBot\Entities\Update;

// Ваш токен бота
$botToken = 'YOUR_BOT_TOKEN';

// Инициализация
$logger = new Logger(['directory' => __DIR__ . '/logs']);
$http = new Http(['timeout' => 60], $logger);
$api = new TelegramAPI($botToken, $http, $logger);

// Удаляем webhook (обязательно для polling!)
$api->deleteWebhook(true);

// Создаем polling handler
$polling = new PollingHandler($api, $logger);
$polling->setTimeout(30);              // Long polling 30 сек
$polling->skipPendingUpdates();        // Пропускаем старые сообщения
```

### 2. Простой эхо-бот

```php
// Запускаем обработку
$polling->startPolling(function(Update $update) use ($api) {
    if ($update->isMessage() && $update->message->text) {
        $api->sendMessage(
            $update->message->chat->id,
            "Эхо: " . $update->message->text
        );
    }
});
```

### 3. Бот с командами

```php
$polling->startPolling(function(Update $update) use ($api, $polling) {
    if (!$update->isMessage() || !$update->message->text) {
        return;
    }
    
    $message = $update->message;
    $text = $message->text;
    $chatId = $message->chat->id;
    
    // Обработка команд
    if (str_starts_with($text, '/')) {
        match(trim($text, '/')) {
            'start' => $api->sendMessage($chatId, "👋 Привет!"),
            'help' => $api->sendMessage($chatId, "📚 Список команд..."),
            'stop' => function() use ($api, $chatId, $polling) {
                $api->sendMessage($chatId, "👋 До свидания!");
                $polling->stopPolling();
            }(),
            default => $api->sendMessage($chatId, "❓ Неизвестная команда"),
        };
    } else {
        // Обычное сообщение
        $api->sendMessage($chatId, "Вы написали: $text");
    }
});
```

## Готовые тесты

### Запуск автоматического теста

```bash
php tests/telegram_bot_polling_test.php
```

Проверит все 23 метода класса.

### Запуск smoke test

```bash
php tests/telegram_bot_polling_smoke_test.php
```

Быстрая проверка работоспособности (8 базовых тестов).

### Запуск интерактивного теста

```bash
php tests/telegram_bot_polling_interactive_test.php
```

Запустит бота, с которым можно взаимодействовать в реальном времени.

## Основные методы

| Метод | Описание |
|-------|----------|
| `setTimeout(int)` | Установить timeout (0-50 сек) |
| `setLimit(int)` | Лимит обновлений (1-100) |
| `setAllowedUpdates(array)` | Фильтр типов обновлений |
| `getUpdates()` | Получить обновления (1 запрос) |
| `startPolling(callable)` | Запустить цикл обработки |
| `stopPolling()` | Остановить цикл |
| `pollOnce()` | Одна итерация polling |
| `skipPendingUpdates()` | Пропустить старые |
| `reset()` | Сброс состояния |

## Примеры

Полные примеры использования:
```bash
examples/telegram_bot_polling_example.php
```

## Документация

Полная документация:
```bash
docs/TELEGRAM_BOT_POLLING.md
```

## Важно!

1. **Всегда удаляйте webhook перед polling:**
   ```php
   $api->deleteWebhook(true);
   ```

2. **Используйте skipPendingUpdates() при первом запуске:**
   ```php
   $polling->skipPendingUpdates();
   ```

3. **Рекомендуемый timeout: 20-30 секунд:**
   ```php
   $polling->setTimeout(30);
   ```

## Webhook vs Polling

| | Webhook | Polling |
|---|---|---|
| **Настройка** | Сложная | Простая ✅ |
| **HTTPS** | Требуется | Не нужен ✅ |
| **Локально** | Сложно | Легко ✅ |
| **Production** | Лучше | Подходит для малых |

## Устранение проблем

**Не получаю обновления?**
1. Проверьте удаление webhook: `$api->deleteWebhook(true)`
2. Проверьте бот не заблокирован
3. Попробуйте: `$polling->setAllowedUpdates([])`

**Обрабатываются старые сообщения?**
```php
$polling->skipPendingUpdates();
```

**Слишком много запросов?**
```php
$polling->setTimeout(30); // Увеличьте timeout
```

## Тестовые данные (из задания)

```php
$BOT_TOKEN = '8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI';
$CHAT_ID = 366442475;
```

Bot: @PipelacTest_bot

## Результаты тестирования

✅ **23/23 теста пройдено (100%)**

Подробные результаты: `POLLING_TEST_RESULTS.md`

---

**Готово к использованию!** 🎉

Для подробной информации смотрите:
- 📖 `/docs/TELEGRAM_BOT_POLLING.md` - полная документация
- 📝 `/examples/telegram_bot_polling_example.php` - примеры
- 🧪 `/POLLING_TEST_RESULTS.md` - результаты тестов

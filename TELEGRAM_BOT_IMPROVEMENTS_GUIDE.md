# 🚀 РУКОВОДСТВО ПО НОВЫМ УЛУЧШЕНИЯМ TELEGRAMBOT

## 📋 Содержание

1. [Методы-фабрики для билдеров](#1-методы-фабрики-для-билдеров)
2. [Упрощённые методы ConversationManager](#2-упрощённые-методы-conversationmanager)
3. [Rate Limiting](#3-rate-limiting)
4. [Middleware для команд](#4-middleware-для-команд)
5. [Пакетная отправка сообщений](#5-пакетная-отправка-сообщений)
6. [Обработчик ошибок](#6-обработчик-ошибок)
7. [Расширенная аналитика MessageStorage](#7-расширенная-аналитика-messagestorage)
8. [Webhook Setup](#8-webhook-setup)
9. [Расширенный Validator](#9-расширенный-validator)
10. [Метрики производительности](#10-метрики-производительности)

---

## 1. Методы-фабрики для билдеров

### ✨ Что нового?

Добавлены статические методы `make()` для InlineKeyboardBuilder и ReplyKeyboardBuilder.

### 📝 Примеры использования

**До:**
```php
$keyboard = (new InlineKeyboardBuilder())
    ->addCallbackButton('Кнопка', 'data')
    ->build();
```

**После:**
```php
$keyboard = InlineKeyboardBuilder::make()
    ->addCallbackButton('Кнопка', 'data')
    ->build();
```

**Reply клавиатура:**
```php
$keyboard = ReplyKeyboardBuilder::make()
    ->addButton('Главная')
    ->addButton('Инфо')
    ->row()
    ->addButton('Статистика')
    ->resizeKeyboard(true)
    ->build();
```

---

## 2. Упрощённые методы ConversationManager

### ✨ Что нового?

Добавлены удобные методы для работы с состояниями без необходимости указывать `chatId`.

### 📝 Новые методы

- `setState(int $userId, string $state, array $data = []): bool`
- `getState(int $userId): ?array`
- `clearState(int $userId): bool`
- `updateStateData(int $userId, array $data): bool`
- `isInState(int $userId, string $state): bool`

### 💡 Примеры использования

```php
// Установка состояния
$conversationManager->setState($userId, 'registration_step_1', [
    'started_at' => time(),
]);

// Проверка состояния
if ($conversationManager->isInState($userId, 'registration_step_1')) {
    // Пользователь в процессе регистрации
}

// Получение состояния
$state = $conversationManager->getState($userId);
if ($state) {
    echo "Текущее состояние: " . $state['state'];
    echo "Данные: " . json_encode($state['data']);
}

// Обновление данных без изменения состояния
$conversationManager->updateStateData($userId, [
    'name' => 'Иван',
    'age' => 25,
]);

// Очистка состояния
$conversationManager->clearState($userId);
```

### 🎯 Пример многошагового диалога

```php
$polling->startPolling(function(Update $update) use ($api, $conversationManager) {
    if (!$update->isMessage()) return;
    
    $message = $update->message;
    $userId = $message->from->id;
    $text = $message->text;
    
    // Команда /register
    if ($text === '/register') {
        $conversationManager->setState($userId, 'reg_name');
        $api->sendMessage($message->chat->id, 'Введите ваше имя:');
        return;
    }
    
    // Обработка состояний
    $state = $conversationManager->getState($userId);
    if (!$state) return;
    
    switch ($state['state']) {
        case 'reg_name':
            $conversationManager->setState($userId, 'reg_age', ['name' => $text]);
            $api->sendMessage($message->chat->id, "Привет, {$text}! Сколько вам лет?");
            break;
            
        case 'reg_age':
            if (!is_numeric($text)) {
                $api->sendMessage($message->chat->id, 'Введите число!');
                return;
            }
            $data = $state['data'];
            $data['age'] = (int)$text;
            $conversationManager->clearState($userId);
            $api->sendMessage(
                $message->chat->id,
                "Регистрация завершена!\nИмя: {$data['name']}\nВозраст: {$data['age']}"
            );
            break;
    }
});
```

---

## 3. Rate Limiting

### ✨ Что нового?

Новый класс `RateLimiter` для автоматического соблюдения лимитов Telegram API (30 сообщений/сек).

### 📝 Примеры использования

**Базовое использование:**
```php
use App\Component\TelegramBot\Core\RateLimiter;

$rateLimiter = new RateLimiter(
    maxRequests: 30,  // 30 запросов
    perSeconds: 1,    // в секунду
    logger: $logger
);

// Проверка перед отправкой
if ($rateLimiter->check()) {
    $api->sendMessage($chatId, $text);
    $rateLimiter->record();
}
```

**Проверка для конкретного чата:**
```php
if ($rateLimiter->checkForChat($chatId)) {
    $api->sendMessage($chatId, $text);
    $rateLimiter->recordForChat($chatId);
}
```

**Автоматическое ожидание:**
```php
// Ожидает доступности слота (максимум 5 секунд)
if ($rateLimiter->wait()) {
    $api->sendMessage($chatId, $text);
    $rateLimiter->record();
}
```

**Автоматическое выполнение с rate limiting:**
```php
$result = $rateLimiter->execute(
    action: function() use ($api, $chatId, $text) {
        return $api->sendMessage($chatId, $text);
    },
    chatId: $chatId
);
```

**Получение статистики:**
```php
$stats = $rateLimiter->getStats();
// ['total_requests' => 150, 'active_chats' => 25, 'current_load' => 75.5]
```

---

## 4. Middleware для команд

### ✨ Что нового?

Класс `CommandMiddleware` для удобного роутинга команд.

### 📝 Примеры использования

**Базовая настройка:**
```php
use App\Component\TelegramBot\Core\CommandMiddleware;

$middleware = new CommandMiddleware($logger);

// Регистрация команд
$middleware->register('start', function(Update $update, array $args) use ($api) {
    $api->sendMessage(
        $update->message->chat->id,
        'Добро пожаловать! Используйте /help для справки.'
    );
});

$middleware->register('help', function(Update $update, array $args) use ($api) {
    $api->sendMessage(
        $update->message->chat->id,
        "Доступные команды:\n/start - начало\n/help - справка\n/stat - статистика"
    );
});

// Обработка неизвестных команд
$middleware->onUnknownCommand(function(Update $update, string $command) use ($api) {
    $api->sendMessage(
        $update->message->chat->id,
        "Неизвестная команда: /{$command}"
    );
});

// Обработка обычных сообщений
$middleware->onMessage(function(Update $update) use ($api) {
    $api->sendMessage(
        $update->message->chat->id,
        "Эхо: " . $update->message->text
    );
});
```

**Использование в Polling:**
```php
$polling->startPolling(function(Update $update) use ($middleware) {
    $middleware->process($update);
});
```

**Регистрация нескольких команд:**
```php
$middleware->registerMultiple(['info', 'about', 'version'], function(Update $update) use ($api) {
    $api->sendMessage($update->message->chat->id, 'Информация о боте v1.0');
});
```

**Группировка команд:**
```php
$middleware->group('admin_', [
    'users' => function($update) { /* ... */ },
    'stats' => function($update) { /* ... */ },
    'settings' => function($update) { /* ... */ },
]);
// Создаст команды: /admin_users, /admin_stats, /admin_settings
```

---

## 5. Пакетная отправка сообщений

### ✨ Что нового?

Методы `sendBatch()` и `broadcast()` в TelegramAPI.

### 📝 Примеры использования

**Отправка нескольких сообщений одному пользователю:**
```php
$messages = [
    'Первое сообщение',
    'Второе сообщение',
    ['text' => 'Третье с опциями', 'options' => ['parse_mode' => 'HTML']],
];

$sent = $api->sendBatch($chatId, $messages, delayMs: 100);
echo "Отправлено: " . count($sent) . " сообщений";
```

**Рассылка одного сообщения многим пользователям:**
```php
$chatIds = [123456, 789012, 345678];
$text = 'Важное уведомление для всех!';

$result = $api->broadcast($chatIds, $text, ['parse_mode' => 'HTML'], delayMs: 150);

echo "Успешно: " . count($result['sent']);
echo "Ошибок: " . count($result['failed']);
```

**С использованием RateLimiter:**
```php
$rateLimiter = new RateLimiter(30, 1, $logger);

foreach ($chatIds as $chatId) {
    $rateLimiter->execute(
        action: fn() => $api->sendMessage($chatId, $text),
        chatId: $chatId
    );
}
```

---

## 6. Обработчик ошибок

### ✨ Что нового?

Метод `setErrorHandler()` в PollingHandler.

### 📝 Примеры использования

**Базовая обработка ошибок:**
```php
$polling->setErrorHandler(function(\Exception $error, Update $update) use ($api, $logger) {
    // Логируем ошибку
    $logger->error('Ошибка обработки обновления', [
        'error' => $error->getMessage(),
        'update_id' => $update->updateId,
    ]);
    
    // Уведомляем пользователя
    if ($update->isMessage()) {
        $api->sendMessage(
            $update->message->chat->id,
            '❌ Произошла ошибка. Пожалуйста, попробуйте позже.'
        );
    }
});
```

**Отправка ошибок администратору:**
```php
$adminChatId = 123456789;

$polling->setErrorHandler(function(\Exception $error, Update $update) use ($api, $adminChatId) {
    // Уведомляем администратора
    $api->sendMessage(
        $adminChatId,
        "🚨 Ошибка в боте:\n\n" .
        "Update ID: {$update->updateId}\n" .
        "Error: {$error->getMessage()}\n" .
        "File: {$error->getFile()}:{$error->getLine()}"
    );
});
```

---

## 7. Расширенная аналитика MessageStorage

### ✨ Что нового?

Новые методы аналитики для MessageStorage.

### 📝 Новые методы

- `getTopUsers(int $limit = 10, ?int $days = null): array`
- `getTimeStatistics(string $period = 'day', int $limit = 30): array`
- `getErrorStatistics(?int $days = null): array`
- `getChatStatistics(string|int $chatId): array`

### 💡 Примеры использования

**Топ активных пользователей:**
```php
$topUsers = $messageStorage->getTopUsers(limit: 10, days: 7);
// [
//     ['user_id' => 123, 'message_count' => 150, 'last_activity' => '2025-10-31 12:00:00'],
//     ...
// ]

$text = "🏆 Топ пользователей за неделю:\n\n";
foreach ($topUsers as $index => $user) {
    $text .= ($index + 1) . ". User {$user['user_id']}: {$user['message_count']} сообщений\n";
}
$api->sendMessage($chatId, $text);
```

**Статистика по времени:**
```php
// По дням
$dailyStats = $messageStorage->getTimeStatistics('day', 7);

// По часам
$hourlyStats = $messageStorage->getTimeStatistics('hour', 24);

// По месяцам
$monthlyStats = $messageStorage->getTimeStatistics('month', 12);

// Вывод
foreach ($dailyStats as $stat) {
    echo "{$stat['period']}: {$stat['count']} сообщений " .
         "({$stat['incoming']} вх, {$stat['outgoing']} исх)\n";
}
```

**Статистика ошибок:**
```php
$errors = $messageStorage->getErrorStatistics(days: 7);

if (!empty($errors)) {
    $text = "⚠️ Ошибки за последнюю неделю:\n\n";
    foreach ($errors as $error) {
        $text .= "Код {$error['error_code']}: {$error['error_message']}\n";
        $text .= "Количество: {$error['count']}\n";
        $text .= "Последняя: {$error['last_occurrence']}\n\n";
    }
    $api->sendMessage($adminChatId, $text);
}
```

**Детальная статистика по чату:**
```php
$chatStats = $messageStorage->getChatStatistics($chatId);

$report = "📊 Статистика чата:\n\n";
$report .= "Всего: {$chatStats['total']}\n";
$report .= "Входящих: {$chatStats['incoming']}\n";
$report .= "Исходящих: {$chatStats['outgoing']}\n";
$report .= "Ошибок: {$chatStats['errors']}\n\n";
$report .= "Активность по дням:\n";
foreach ($chatStats['by_day'] as $day) {
    $report .= "  {$day['period']}: {$day['count']}\n";
}

$api->sendMessage($chatId, $report);
```

---

## 8. Webhook Setup

### ✨ Что нового?

Класс `WebhookSetup` для упрощённой настройки webhook.

### 📝 Примеры использования

**Настройка webhook:**
```php
use App\Component\TelegramBot\Core\WebhookSetup;

$webhookSetup = new WebhookSetup($api, $logger);

// Простая настройка
$webhookSetup->configure('https://example.com/webhook');

// С дополнительными параметрами
$webhookSetup->configure('https://example.com/webhook', [
    'max_connections' => 100,
    'allowed_updates' => ['message', 'callback_query'],
    'secret_token' => WebhookSetup::generateSecretToken(),
    'drop_pending_updates' => true,
]);
```

**Проверка webhook:**
```php
$info = $webhookSetup->verify();
echo "URL: {$info['url']}\n";
echo "Ожидающие обновления: {$info['pending_update_count']}\n";

if ($webhookSetup->isConfigured()) {
    echo "Webhook настроен\n";
}

// Проверка ошибок
$error = $webhookSetup->getLastError();
if ($error && $error['has_error']) {
    echo "Ошибка: {$error['last_error_message']}\n";
}
```

**Переключение режимов:**
```php
// С Polling на Webhook
$webhookSetup->switchFromPolling('https://example.com/webhook', [
    'drop_pending_updates' => true,
]);

// С Webhook на Polling
$webhookSetup->switchToPolling(dropPendingUpdates: true);
```

**Удаление webhook:**
```php
$webhookSetup->delete(dropPendingUpdates: true);
```

**Проверка secret token:**
```php
$expectedToken = 'your_secret_token';
$receivedToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';

if (WebhookSetup::verifySecretToken($expectedToken, $receivedToken)) {
    // Токен валиден
}
```

---

## 9. Расширенный Validator

### ✨ Что нового?

Дополнительные методы валидации в классе Validator.

### 📝 Новые методы

- `validateKeyboard(array $keyboard, string $type = 'inline'): void`
- `validatePollOptionsExtended(array $options, bool $allowDuplicates = true): void`
- `validateInlineQuery(array $results): void`
- `validateMediaGroup(array $media): void`
- `validateWebhookUrl(string $url): void`
- `validateEmail(string $email): void`
- `validatePhone(string $phone): void`

### 💡 Примеры использования

**Валидация клавиатуры:**
```php
use App\Component\TelegramBot\Utils\Validator;

$keyboard = [
    [
        ['text' => 'Кнопка 1', 'callback_data' => 'btn1'],
        ['text' => 'Кнопка 2', 'callback_data' => 'btn2'],
    ],
];

try {
    Validator::validateKeyboard($keyboard, 'inline');
    echo "Клавиатура валидна\n";
} catch (ValidationException $e) {
    echo "Ошибка: {$e->getMessage()}\n";
}
```

**Валидация опций опроса без дубликатов:**
```php
$options = ['Вариант 1', 'Вариант 2', 'Вариант 3'];

Validator::validatePollOptionsExtended($options, allowDuplicates: false);
```

**Валидация медиа-группы:**
```php
$media = [
    ['type' => 'photo', 'media' => 'file_id_1', 'caption' => 'Фото 1'],
    ['type' => 'photo', 'media' => 'file_id_2', 'caption' => 'Фото 2'],
];

Validator::validateMediaGroup($media);
```

**Валидация webhook URL:**
```php
$url = 'https://example.com/webhook';
Validator::validateWebhookUrl($url);
```

**Валидация email и телефона:**
```php
try {
    Validator::validateEmail('user@example.com');
    Validator::validatePhone('+7 (999) 123-45-67');
    echo "Данные валидны\n";
} catch (ValidationException $e) {
    echo "Ошибка: {$e->getMessage()}\n";
}
```

---

## 10. Метрики производительности

### ✨ Что нового?

Класс `MetricsCollector` для сбора метрик производительности.

### 📝 Примеры использования

**Базовое использование:**
```php
use App\Component\TelegramBot\Utils\MetricsCollector;

$metrics = new MetricsCollector($logger);

// Измерение производительности
$start = microtime(true);
$api->sendMessage($chatId, $text);
$duration = microtime(true) - $start;

$metrics->track('sendMessage', $duration, success: true);
```

**Автоматическое измерение:**
```php
// Обёртка для автоматического измерения
function trackMethod(string $method, callable $action, MetricsCollector $metrics): mixed
{
    $start = microtime(true);
    try {
        $result = $action();
        $metrics->track($method, microtime(true) - $start, true);
        return $result;
    } catch (\Exception $e) {
        $metrics->track($method, microtime(true) - $start, false);
        throw $e;
    }
}

// Использование
$message = trackMethod('sendMessage', fn() => $api->sendMessage($chatId, $text), $metrics);
```

**Получение статистики:**
```php
// Среднее время отклика
$avgTime = $metrics->getAverageResponseTime('sendMessage');
echo "Среднее время: " . ($avgTime * 1000) . "ms\n";

// Процент ошибок
$failureRate = $metrics->getFailureRate('sendMessage');
echo "Процент ошибок: {$failureRate}%\n";

// Полная статистика
$stats = $metrics->getStatistics();
print_r($stats);
```

**Топы методов:**
```php
// Самые медленные методы
$slowest = $metrics->getSlowestMethods(5);
foreach ($slowest as $method) {
    echo "{$method['method']}: " . ($method['avg_time'] * 1000) . "ms " .
         "({$method['calls']} вызовов)\n";
}

// Методы с наибольшим процентом ошибок
$mostFailed = $metrics->getMostFailedMethods(5);
foreach ($mostFailed as $method) {
    echo "{$method['method']}: {$method['failure_rate']}% " .
         "({$method['failures']}/{$method['total']})\n";
}
```

**Экспорт метрик:**
```php
// В JSON
file_put_contents('metrics.json', $metrics->exportToJson());

// В формат Prometheus
$prometheusMetrics = $metrics->exportToPrometheus('telegram_bot');
foreach ($prometheusMetrics as $metric) {
    echo "{$metric['name']} {$metric['value']}\n";
}
```

**Uptime:**
```php
echo "Uptime: " . $metrics->getFormattedUptime(); // "2h 15m 30s"
```

---

## 🎯 Комплексный пример использования всех улучшений

```php
<?php

use App\Component\Http;
use App\Component\Logger;
use App\Component\MySQL;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Core\PollingHandler;
use App\Component\TelegramBot\Core\MessageStorage;
use App\Component\TelegramBot\Core\ConversationManager;
use App\Component\TelegramBot\Core\RateLimiter;
use App\Component\TelegramBot\Core\CommandMiddleware;
use App\Component\TelegramBot\Utils\MetricsCollector;
use App\Component\TelegramBot\Keyboards\InlineKeyboardBuilder;

// Инициализация
$logger = new Logger(['directory' => __DIR__ . '/logs']);
$http = new Http(['timeout' => 60], $logger);
$db = new MySQL([/*config*/], $logger);

$messageStorage = new MessageStorage($db, $logger, ['enabled' => true]);
$conversationManager = new ConversationManager($db, $logger, ['enabled' => true]);
$metrics = new MetricsCollector($logger);
$rateLimiter = new RateLimiter(30, 1, $logger);

$api = new TelegramAPI('YOUR_TOKEN', $http, $logger, $messageStorage);

// Настройка middleware
$middleware = new CommandMiddleware($logger);

$middleware->register('start', function($update) use ($api, $metrics) {
    $start = microtime(true);
    $api->sendMessage($update->message->chat->id, 'Привет!');
    $metrics->track('sendMessage', microtime(true) - $start);
});

$middleware->register('register', function($update) use ($api, $conversationManager) {
    $userId = $update->message->from->id;
    $conversationManager->setState($userId, 'reg_name');
    $api->sendMessage($update->message->chat->id, 'Введите имя:');
});

$middleware->register('stats', function($update) use ($api, $messageStorage, $metrics) {
    $chatId = $update->message->chat->id;
    
    // Статистика сообщений
    $msgStats = $messageStorage->getChatStatistics($chatId);
    
    // Метрики производительности
    $avgTime = $metrics->getAverageResponseTime() * 1000;
    $failRate = $metrics->getFailureRate();
    
    $text = "📊 Статистика:\n\n";
    $text .= "Сообщений: {$msgStats['total']}\n";
    $text .= "Средний отклик: " . round($avgTime, 2) . "ms\n";
    $text .= "Процент ошибок: " . round($failRate, 2) . "%\n";
    $text .= "Uptime: " . $metrics->getFormattedUptime();
    
    $api->sendMessage($chatId, $text);
});

// Обработчик ошибок
$polling = new PollingHandler($api, $logger);
$polling->setErrorHandler(function($error, $update) use ($api) {
    if ($update->isMessage()) {
        $api->sendMessage(
            $update->message->chat->id,
            '❌ Произошла ошибка. Попробуйте позже.'
        );
    }
});

// Запуск
$polling->skipPendingUpdates();
$polling->startPolling(function($update) use ($middleware, $conversationManager, $api, $rateLimiter) {
    // Обработка команд через middleware
    $middleware->process($update);
    
    // Обработка состояний
    if ($update->isMessage()) {
        $userId = $update->message->from->id;
        $state = $conversationManager->getState($userId);
        
        if ($state) {
            // С rate limiting
            $rateLimiter->execute(function() use ($api, $update, $state, $conversationManager, $userId) {
                // Обработка состояний
                // ...
            }, $update->message->chat->id);
        }
    }
});
```

---

## 🚀 Заключение

Все 10 улучшений реализованы и готовы к использованию! Они значительно упрощают разработку, улучшают производительность и добавляют мощные инструменты для мониторинга и аналитики.

### Приоритет использования:

**Высокий приоритет:**
1. ✅ Методы-фабрики (make())
2. ✅ Упрощённые методы ConversationManager
3. ✅ Rate Limiting

**Средний приоритет:**
4. ✅ Command Middleware
5. ✅ Пакетная отправка
6. ✅ Обработчик ошибок
7. ✅ Расширенная аналитика

**По необходимости:**
8. ✅ Webhook Setup
9. ✅ Расширенный Validator
10. ✅ Метрики производительности

### 📝 Дополнительные ресурсы

- [Основная документация TelegramBot](/src/TelegramBot/README.md)
- [Отчёт о тестировании](/TELEGRAM_BOT_E2E_TEST_REPORT.md)
- [Примеры использования](/examples/)

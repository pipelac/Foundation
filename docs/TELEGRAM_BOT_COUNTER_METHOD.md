# TelegramBot: Метод sendCounter()

## Описание

Метод `sendCounter()` отправляет анимированный счетчик, который изменяется от начального значения до конечного с интервалом ровно 1 секунда между обновлениями. Основан на технологии редактирования сообщений через `editMessageText()`.

## Возможности

- ✅ Счет в обоих направлениях (вверх и вниз)
- ✅ Поддержка обычных цифр (0-9)
- ✅ Поддержка эмодзи цифр (0️⃣-9️⃣)
- ✅ Поддержка отрицательных чисел с эмодзи (➖)
- ✅ Точный интервал: ровно 1 секунда
- ✅ Полное логирование операций
- ✅ Обработка ошибок редактирования

## Сигнатура

```php
public function sendCounter(
    string|int $chatId,
    int $start,
    int $end,
    bool $useEmoji = false,
    array $options = []
): Message
```

### Параметры

| Параметр | Тип | Описание |
|----------|-----|----------|
| `$chatId` | `string\|int` | Идентификатор чата или username канала |
| `$start` | `int` | Начальное значение счетчика |
| `$end` | `int` | Конечное значение счетчика |
| `$useEmoji` | `bool` | Использовать эмодзи цифры (по умолчанию `false`) |
| `$options` | `array` | Дополнительные опции Telegram API (parse_mode, reply_markup и т.д.) |

### Возвращаемое значение

Возвращает объект `Message` с финальным состоянием счетчика.

### Исключения

- `ValidationException` - если `$chatId` некорректен
- `ValidationException` - если `$start === $end`
- `ApiException` - при ошибках Telegram API

## Примеры использования

### Пример 1: Простой счетчик вверх

```php
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\Http;
use App\Component\Logger;

$logger = new Logger(['directory' => __DIR__ . '/logs']);
$http = new Http(['timeout' => 60], $logger);
$telegram = new TelegramAPI('YOUR_BOT_TOKEN', $http, $logger);

// Счетчик от 1 до 5
$message = $telegram->sendCounter(123456789, 1, 5);

echo "Счетчик завершен! Message ID: {$message->messageId}";
```

**Результат**: Пользователь увидит последовательность: 1 → 2 → 3 → 4 → 5
**Время выполнения**: ~4 секунды (4 обновления × 1 секунда)

### Пример 2: Обратный отсчет

```php
// Обратный отсчет от 10 до 1
$telegram->sendCounter($chatId, 10, 1);
```

**Результат**: 10 → 9 → 8 → 7 → 6 → 5 → 4 → 3 → 2 → 1
**Время выполнения**: ~9 секунд

### Пример 3: Эмодзи счетчик

```php
// Счетчик с эмодзи от 0 до 5
$telegram->sendCounter($chatId, 0, 5, true);
```

**Результат**: 0️⃣ → 1️⃣ → 2️⃣ → 3️⃣ → 4️⃣ → 5️⃣

### Пример 4: Двузначные числа с эмодзи

```php
// Обратный отсчет с эмодзи
$telegram->sendCounter($chatId, 25, 20, true);
```

**Результат**: 2️⃣5️⃣ → 2️⃣4️⃣ → 2️⃣3️⃣ → 2️⃣2️⃣ → 2️⃣1️⃣ → 2️⃣0️⃣

### Пример 5: С дополнительными опциями

```php
use App\Component\TelegramBot\Keyboards\InlineKeyboardBuilder;

// Создаем клавиатуру
$keyboard = InlineKeyboardBuilder::make()
    ->addCallbackButton('Еще раз', 'restart_counter')
    ->build();

// Счетчик с клавиатурой
$telegram->sendCounter(
    $chatId,
    1,
    10,
    false,
    ['reply_markup' => $keyboard]
);
```

### Пример 6: Отрицательные числа с эмодзи

```php
// Счетчик с отрицательными числами
$telegram->sendCounter($chatId, -3, 3, true);
```

**Результат**: ➖3️⃣ → ➖2️⃣ → ➖1️⃣ → 0️⃣ → 1️⃣ → 2️⃣ → 3️⃣

### Пример 7: Использование в боте с callback

```php
$polling->startPolling(function (Update $update) use ($telegram) {
    if ($update->callbackQuery) {
        $callback = $update->callbackQuery;
        $chatId = $callback->message->chat->id;
        
        if ($callback->data === 'countdown') {
            // Обратный отсчет перед запуском
            $telegram->sendMessage($chatId, 'Запуск через...');
            $telegram->sendCounter($chatId, 5, 1);
            $telegram->sendMessage($chatId, '🚀 Старт!');
        }
        
        $telegram->answerCallbackQuery($callback->id);
    }
});
```

## Технические детали

### Алгоритм работы

1. Валидация параметров
2. Определение направления счета (вверх/вниз)
3. Отправка первого сообщения со стартовым значением
4. Цикл обновлений с интервалом 1 секунда:
   - Инкремент/декремент текущего значения
   - Форматирование значения (число/эмодзи)
   - Редактирование сообщения через `editMessageText()`
5. Логирование завершения
6. Возврат финального Message объекта

### Форматирование эмодзи

При `$useEmoji = true` каждая цифра заменяется на соответствующий эмодзи:

```php
private function formatCounterValue(int $value, bool $useEmoji): string
{
    if (!$useEmoji) {
        return (string)$value;
    }
    
    $emojiDigits = [
        '0' => '0️⃣', '1' => '1️⃣', '2' => '2️⃣', '3' => '3️⃣', '4' => '4️⃣',
        '5' => '5️⃣', '6' => '6️⃣', '7' => '7️⃣', '8' => '8️⃣', '9' => '9️⃣',
        '-' => '➖',
    ];
    
    // Посимвольная замена
    // ...
}
```

### Производительность

| Шагов | Время (секунд) | Примечание |
|-------|----------------|------------|
| 1-5 | 1-5 | По 1 секунде на шаг |
| 10 | ~10 | + накладные расходы API |
| 50 | ~50 | Может быть медленным для больших диапазонов |
| 100+ | 100+ | Не рекомендуется |

**Рекомендации**:
- Для диапазонов > 20 шагов: показывать только ключевые значения
- Для длительных операций: использовать прогресс-бар вместо счетчика
- Учитывать rate limits Telegram API

### Логирование

Метод логирует две записи для каждого вызова:

```
INFO Начало отправки счетчика {
    "chat_id": 123456789,
    "start": 1,
    "end": 10,
    "use_emoji": false
}

INFO Счетчик завершен {
    "chat_id": 123456789,
    "message_id": 42,
    "start": 1,
    "end": 10
}
```

При ошибках редактирования:

```
WARNING Ошибка при редактировании счетчика {
    "chat_id": 123456789,
    "message_id": 42,
    "error": "Message is not modified",
    "current_value": 5
}
```

## Практические сценарии

### 1. Обратный отсчет перед действием

```php
function launchSequence(TelegramAPI $telegram, int $chatId): void
{
    $telegram->sendMessage($chatId, '🚀 Подготовка к запуску...');
    $telegram->sendCounter($chatId, 10, 0);
    $telegram->sendMessage($chatId, '💥 ЗАПУСК!');
    
    // Выполнение действия
    performAction();
}
```

### 2. Визуализация прогресса

```php
function processItems(TelegramAPI $telegram, int $chatId, array $items): void
{
    $total = count($items);
    $telegram->sendMessage($chatId, "Обработка {$total} элементов...");
    
    // Показываем счетчик прогресса
    $message = $telegram->sendMessage($chatId, '0');
    
    foreach ($items as $i => $item) {
        processItem($item);
        $telegram->editMessageText($chatId, $message->messageId, (string)($i + 1));
        usleep(100000); // 0.1 секунды между обновлениями
    }
    
    $telegram->sendMessage($chatId, '✅ Обработка завершена!');
}
```

### 3. Игровой таймер

```php
function startGameTimer(TelegramAPI $telegram, int $chatId, int $seconds): void
{
    $telegram->sendMessage($chatId, "⏰ Время на раунд: {$seconds} секунд");
    $telegram->sendCounter($chatId, $seconds, 0);
    $telegram->sendMessage($chatId, '⏱️ Время вышло!');
    
    endGameRound($chatId);
}
```

### 4. Демонстрация эмодзи счетчика

```php
function showEmojiDemo(TelegramAPI $telegram, int $chatId): void
{
    $telegram->sendMessage(
        $chatId,
        '🎨 Демонстрация эмодзи счетчика\nСчет от 0 до 9:'
    );
    
    $telegram->sendCounter($chatId, 0, 9, true);
    
    sleep(2);
    
    $telegram->sendMessage(
        $chatId,
        'А теперь в обратном порядке:'
    );
    
    $telegram->sendCounter($chatId, 9, 0, true);
}
```

## Ограничения

1. **Telegram Rate Limits**
   - Не более 30 сообщений в секунду на одного бота
   - Для счетчиков это не проблема (1 обновление в секунду)

2. **Большие диапазоны**
   - Счетчик от 1 до 100 займет ~100 секунд
   - Telegram может закрыть соединение при очень долгих операциях
   - Рекомендуется: диапазон ≤ 30 шагов

3. **Параллельные счетчики**
   - Можно запускать несколько счетчиков в разных чатах
   - В одном чате нельзя (будет конфликт редактирования)

4. **Эмодзи ограничения**
   - Поддерживаются только цифры 0-9 и минус
   - Нет эмодзи для других символов (точка, запятая и т.д.)

## Сравнение с sendMessageStreaming()

| Характеристика | sendCounter() | sendMessageStreaming() |
|----------------|---------------|------------------------|
| Интервал обновления | 1 секунда (фиксированный) | Настраиваемый (по умолчанию 60мс) |
| Тип контента | Числа (обычные/эмодзи) | Любой текст |
| Направление | Двунаправленный | Только вперед |
| Формат | Автоматический | Ручной |
| Применение | Счетчики, таймеры | Эффект печати текста |
| Скорость | Медленная (1 сек/шаг) | Быстрая (настраиваемая) |

## См. также

- [sendMessageStreaming()](./TELEGRAM_BOT_SENDCHATACTION_STREAMING.md) - базовый метод для стриминга
- [TelegramAPI](../src/TelegramBot/Core/TelegramAPI.php) - полный API клиент
- [Тестовый отчет](../COUNTER_TEST_REPORT.md) - результаты тестирования

## История изменений

### v1.0.0 (01.11.2025)
- ✨ Первый релиз метода sendCounter()
- ✅ Поддержка обычных и эмодзи цифр
- ✅ Точный интервал 1 секунда
- ✅ Полное логирование
- ✅ Все тесты пройдены

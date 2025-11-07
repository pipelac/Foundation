# Telegram - Документация

## Описание

`Telegram` - класс для работы с Telegram Bot API. Обеспечивает типобезопасную и надежную интеграцию с Telegram ботами для отправки текстовых сообщений, изображений, видео, аудио и документов.

## Возможности

- ✅ Отправка текстовых сообщений с форматированием (HTML, Markdown)
- ✅ Отправка изображений (фото)
- ✅ Отправка видео с поддержкой streaming
- ✅ Отправка аудио файлов
- ✅ Отправка документов (любые файлы до 50 МБ)
- ✅ Поддержка подписей к медиафайлам
- ✅ Отправка файлов по URL или локальному пути
- ✅ Валидация токена и параметров
- ✅ Настраиваемые таймауты и retry
- ✅ Поддержка default chat ID
- ✅ Интеграция с Logger
- ✅ Обработка ошибок API

## Требования

- PHP 8.1+
- Расширения: `json`, `curl`
- Токен Telegram бота (получить у [@BotFather](https://t.me/botfather))
- Composer (для Guzzle HTTP клиента)

## Установка

```bash
composer install
```

## Создание бота

1. Найдите [@BotFather](https://t.me/botfather) в Telegram
2. Отправьте команду `/newbot`
3. Следуйте инструкциям для создания бота
4. Получите токен в формате `123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11`
5. Узнайте свой Chat ID через [@userinfobot](https://t.me/userinfobot)

## Конфигурация

Создайте файл `config/telegram.json`:

```json
{
    "token": "123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11",
    "default_chat_id": "123456789",
    "timeout": 30,
    "retries": 3
}
```

### Параметры конфигурации

| Параметр | Тип | Обязательный | По умолчанию | Описание |
|----------|-----|--------------|--------------|----------|
| `token` | string | Да | - | Токен Telegram бота |
| `default_chat_id` | string | Нет | - | ID чата по умолчанию |
| `timeout` | int | Нет | 30 | Таймаут запросов в секундах |
| `retries` | int | Нет | - | Количество повторных попыток |

## Использование

### Инициализация

```php
use App\Component\Telegram;
use App\Component\Logger;
use App\Component\Config\ConfigLoader;

// С логгером
$config = ConfigLoader::load(__DIR__ . '/config/telegram.json');
$loggerConfig = ConfigLoader::load(__DIR__ . '/config/logger.json');
$logger = new Logger($loggerConfig);
$telegram = new Telegram($config, $logger);

// Без логгера
$telegram = new Telegram($config);
```

### Отправка текстовых сообщений

```php
// Простое сообщение
$telegram->sendText('123456789', 'Привет из PHP!');

// С использованием default_chat_id
$telegram->sendText(null, 'Сообщение в default чат');

// С форматированием HTML
$telegram->sendText('123456789', '<b>Жирный</b> и <i>курсив</i>', [
    'parse_mode' => Telegram::PARSE_MODE_HTML,
]);

// С форматированием Markdown
$telegram->sendText('123456789', '*Жирный* и _курсив_', [
    'parse_mode' => Telegram::PARSE_MODE_MARKDOWN,
]);

// С дополнительными опциями
$telegram->sendText('123456789', 'Важное уведомление', [
    'disable_notification' => false,        // Со звуком
    'disable_web_page_preview' => true,     // Без превью ссылок
    'protect_content' => true,              // Запретить пересылку
]);
```

### Отправка изображений

```php
// Отправка по URL
$telegram->sendPhoto('123456789', 'https://example.com/image.jpg');

// Отправка локального файла
$telegram->sendPhoto('123456789', '/path/to/image.jpg');

// С подписью
$telegram->sendPhoto('123456789', '/path/to/photo.jpg', [
    'caption' => 'Красивое фото!',
]);

// С HTML форматированием в подписи
$telegram->sendPhoto('123456789', 'https://example.com/photo.jpg', [
    'caption' => '<b>Фото дня</b> 📸',
    'parse_mode' => Telegram::PARSE_MODE_HTML,
]);
```

### Отправка видео

```php
// Отправка видео
$telegram->sendVideo('123456789', 'https://example.com/video.mp4');

// С дополнительными параметрами
$telegram->sendVideo('123456789', '/path/to/video.mp4', [
    'caption' => 'Видео обучение',
    'duration' => 120,                      // Длительность в секундах
    'width' => 1920,
    'height' => 1080,
    'supports_streaming' => true,           // Поддержка потокового воспроизведения
]);
```

### Отправка аудио

```php
// Отправка аудио файла
$telegram->sendAudio('123456789', '/path/to/audio.mp3');

// С метаданными
$telegram->sendAudio('123456789', 'https://example.com/song.mp3', [
    'title' => 'Название трека',
    'performer' => 'Исполнитель',
    'duration' => 180,
    'caption' => 'Новая музыка!',
]);
```

### Отправка документов

```php
// Отправка любого файла
$telegram->sendDocument('123456789', '/path/to/document.pdf');

// С подписью
$telegram->sendDocument('123456789', '/path/to/report.pdf', [
    'caption' => 'Отчет за январь 2024',
    'disable_content_type_detection' => false,
]);

// Отправка ZIP архива
$telegram->sendDocument('123456789', '/path/to/archive.zip', [
    'caption' => 'Архив с файлами проекта',
]);
```

## Примеры использования

### Уведомления системы

```php
class TelegramNotifier
{
    private Telegram $telegram;
    private string $chatId;
    
    public function __construct(Telegram $telegram, string $chatId)
    {
        $this->telegram = $telegram;
        $this->chatId = $chatId;
    }
    
    public function notifyError(string $message, array $context = []): void
    {
        $text = "🔴 <b>Ошибка</b>\n\n";
        $text .= $message . "\n\n";
        
        if (!empty($context)) {
            $text .= "<b>Контекст:</b>\n";
            foreach ($context as $key => $value) {
                $text .= "• {$key}: {$value}\n";
            }
        }
        
        $text .= "\n<i>" . date('Y-m-d H:i:s') . "</i>";
        
        try {
            $this->telegram->sendText($this->chatId, $text, [
                'parse_mode' => Telegram::PARSE_MODE_HTML,
            ]);
        } catch (Exception $e) {
            error_log("Failed to send Telegram notification: " . $e->getMessage());
        }
    }
    
    public function notifySuccess(string $message): void
    {
        $text = "✅ <b>Успех</b>\n\n{$message}";
        
        $this->telegram->sendText($this->chatId, $text, [
            'parse_mode' => Telegram::PARSE_MODE_HTML,
            'disable_notification' => true, // Без звука
        ]);
    }
    
    public function notifyWarning(string $message): void
    {
        $text = "⚠️ <b>Предупреждение</b>\n\n{$message}";
        
        $this->telegram->sendText($this->chatId, $text, [
            'parse_mode' => Telegram::PARSE_MODE_HTML,
        ]);
    }
}

// Использование
$notifier = new TelegramNotifier($telegram, '123456789');

$notifier->notifyError('Не удалось подключиться к БД', [
    'host' => 'localhost',
    'error' => 'Connection refused',
]);

$notifier->notifySuccess('Резервное копирование завершено успешно');
$notifier->notifyWarning('Осталось мало места на диске (10%)');
```

### Отправка отчетов

```php
class ReportSender
{
    private Telegram $telegram;
    private MySQL $mysql;
    
    public function __construct(Telegram $telegram, MySQL $mysql)
    {
        $this->telegram = $telegram;
        $this->mysql = $mysql;
    }
    
    public function sendDailyReport(string $chatId): void
    {
        // Получить статистику
        $stats = $this->mysql->queryOne('
            SELECT 
                COUNT(*) as total_users,
                COUNT(CASE WHEN last_login >= CURDATE() THEN 1 END) as active_today,
                COUNT(CASE WHEN created_at >= CURDATE() THEN 1 END) as new_today
            FROM users
        ');
        
        // Сформировать отчет
        $text = "📊 <b>Ежедневный отчет</b>\n\n";
        $text .= "👥 Всего пользователей: {$stats['total_users']}\n";
        $text .= "✅ Активных сегодня: {$stats['active_today']}\n";
        $text .= "🆕 Новых сегодня: {$stats['new_today']}\n\n";
        $text .= "📅 " . date('d.m.Y H:i');
        
        // Отправить
        $this->telegram->sendText($chatId, $text, [
            'parse_mode' => Telegram::PARSE_MODE_HTML,
        ]);
        
        // Если есть график, отправить его
        if (file_exists('/path/to/chart.png')) {
            $this->telegram->sendPhoto($chatId, '/path/to/chart.png', [
                'caption' => 'График активности за неделю',
            ]);
        }
    }
}

// Использование в cron
$sender = new ReportSender($telegram, $mysql);
$sender->sendDailyReport('123456789');
```

### Мониторинг сервера

```php
class ServerMonitor
{
    private Telegram $telegram;
    private string $chatId;
    
    public function __construct(Telegram $telegram, string $chatId)
    {
        $this->telegram = $telegram;
        $this->chatId = $chatId;
    }
    
    public function checkAndNotify(): void
    {
        $alerts = [];
        
        // Проверка CPU
        $cpuUsage = $this->getCpuUsage();
        if ($cpuUsage > 80) {
            $alerts[] = "🔥 CPU: {$cpuUsage}%";
        }
        
        // Проверка памяти
        $memoryUsage = $this->getMemoryUsage();
        if ($memoryUsage > 80) {
            $alerts[] = "💾 Память: {$memoryUsage}%";
        }
        
        // Проверка диска
        $diskUsage = $this->getDiskUsage();
        if ($diskUsage > 90) {
            $alerts[] = "💿 Диск: {$diskUsage}%";
        }
        
        if (!empty($alerts)) {
            $text = "⚠️ <b>Предупреждение сервера</b>\n\n";
            $text .= implode("\n", $alerts);
            
            $this->telegram->sendText($this->chatId, $text, [
                'parse_mode' => Telegram::PARSE_MODE_HTML,
            ]);
        }
    }
    
    private function getCpuUsage(): float
    {
        $load = sys_getloadavg();
        return round($load[0] * 100 / 4); // Для 4 ядер
    }
    
    private function getMemoryUsage(): float
    {
        $free = shell_exec('free');
        $free = (string)$free;
        $free = trim($free);
        preg_match_all('/\s+(\d+)/', $free, $matches);
        return round($matches[1][2] / $matches[1][1] * 100);
    }
    
    private function getDiskUsage(): float
    {
        return round(disk_total_space('/') / disk_free_space('/') * 100);
    }
}

// Запуск каждые 5 минут через cron
$monitor = new ServerMonitor($telegram, '123456789');
$monitor->checkAndNotify();
```

### Обработка файлов

```php
// Отправка сгенерированного PDF отчета
$pdf->generate();
$telegram->sendDocument('123456789', '/tmp/report.pdf', [
    'caption' => 'Отчет сгенерирован: ' . date('Y-m-d H:i'),
]);

// Отправка backup архива
$backup->create();
$telegram->sendDocument('123456789', '/backups/db_backup.zip', [
    'caption' => 'Резервная копия БД за ' . date('d.m.Y'),
]);

// Отправка лог файла
if (file_exists('/var/log/app.log')) {
    $telegram->sendDocument('123456789', '/var/log/app.log', [
        'caption' => 'Логи приложения',
    ]);
}
```

### Интеграция с RSS

```php
use App\Component\Rss;

$rss = new Rss();
$telegram = new Telegram($config);

$feed = $rss->fetch('https://example.com/feed.xml');

foreach (array_slice($feed['items'], 0, 5) as $item) {
    $text = "📰 <b>{$item['title']}</b>\n\n";
    $text .= strip_tags($item['description']) . "\n\n";
    $text .= "🔗 <a href=\"{$item['link']}\">Читать полностью</a>";
    
    $telegram->sendText('123456789', $text, [
        'parse_mode' => Telegram::PARSE_MODE_HTML,
        'disable_web_page_preview' => false,
    ]);
    
    sleep(1); // Пауза между сообщениями
}
```

## API Reference

### Конструктор

```php
public function __construct(array $config, ?Logger $logger = null)
```

**Параметры:**
- `$config` (array) - Конфигурация бота
- `$logger` (Logger|null) - Опциональный логгер

**Исключения:**
- `TelegramConfigException` - Некорректная конфигурация

### getMe()

```php
public function getMe(): array
```

Получает информацию о боте. Используется для проверки токена.

**Возвращает:** Информация о боте (id, name, username и т.д.)

### sendText()

```php
public function sendText(?string $chatId, string $text, array $options = []): array
```

Отправляет текстовое сообщение (до 4096 символов).

**Опции:**
- `parse_mode` - Режим форматирования (HTML, Markdown, MarkdownV2)
- `disable_web_page_preview` - Отключить превью ссылок
- `disable_notification` - Отправить без звука
- `protect_content` - Защитить от пересылки
- `reply_to_message_id` - ID сообщения для ответа

### sendPhoto()

```php
public function sendPhoto(?string $chatId, string $photo, array $options = []): array
```

Отправляет изображение (URL или локальный файл).

**Опции:**
- `caption` - Подпись к изображению (до 1024 символов)
- `parse_mode` - Форматирование подписи

### sendVideo()

```php
public function sendVideo(?string $chatId, string $video, array $options = []): array
```

Отправляет видео (до 50 МБ).

**Опции:**
- `caption` - Подпись
- `duration` - Длительность в секундах
- `width`, `height` - Размеры видео
- `supports_streaming` - Поддержка streaming

### sendAudio()

```php
public function sendAudio(?string $chatId, string $audio, array $options = []): array
```

Отправляет аудио файл.

**Опции:**
- `caption` - Подпись
- `duration` - Длительность
- `performer` - Исполнитель
- `title` - Название

### sendDocument()

```php
public function sendDocument(?string $chatId, string $document, array $options = []): array
```

Отправляет документ (любой файл до 50 МБ).

**Опции:**
- `caption` - Подпись
- `disable_content_type_detection` - Отключить автоопределение типа

## Обработка ошибок

### Исключения

- `TelegramException` - Базовое исключение
- `TelegramConfigException` - Ошибка конфигурации
- `TelegramApiException` - Ошибка API
- `TelegramFileException` - Ошибка файла

```php
use App\Component\Exception\TelegramApiException;
use App\Component\Exception\TelegramConfigException;
use App\Component\Exception\TelegramFileException;

try {
    $telegram->sendText('123456789', 'Привет!');
} catch (TelegramApiException $e) {
    echo "Ошибка API: {$e->getMessage()}\n";
} catch (TelegramFileException $e) {
    echo "Ошибка файла: {$e->getMessage()}\n";
} catch (TelegramConfigException $e) {
    echo "Ошибка конфигурации: {$e->getMessage()}\n";
}
```

## Лучшие практики

1. **Используйте default_chat_id** для упрощения:
   ```php
   $telegram->sendText(null, 'Сообщение'); // Использует default_chat_id
   ```

2. **Добавляйте обработку ошибок**:
   ```php
   try {
       $telegram->sendText($chatId, $message);
   } catch (Exception $e) {
       error_log("Telegram error: " . $e->getMessage());
   }
   ```

3. **Соблюдайте лимиты** Telegram API:
   - 30 сообщений в секунду
   - 20 сообщений в минуту в группу
   - Добавляйте паузы между сообщениями

4. **Используйте правильное форматирование**:
   ```php
   // HTML - проще и надежнее
   'parse_mode' => Telegram::PARSE_MODE_HTML
   
   // Markdown - требует экранирования спецсимволов
   'parse_mode' => Telegram::PARSE_MODE_MARKDOWN
   ```

5. **Проверяйте размер файлов**:
   ```php
   if (filesize($file) > 50 * 1024 * 1024) {
       throw new Exception('Файл слишком большой');
   }
   ```

6. **Храните токен в безопасности**:
   - Не коммитьте в Git
   - Используйте переменные окружения
   - Ограничивайте права доступа к config файлам

7. **Логируйте важные события**:
   ```php
   $telegram = new Telegram($config, $logger);
   ```

## Форматирование текста

### HTML

```php
$text = "
<b>Жирный текст</b>
<i>Курсив</i>
<u>Подчеркнутый</u>
<s>Зачеркнутый</s>
<code>Моноширинный</code>
<pre>Блок кода</pre>
<a href='https://example.com'>Ссылка</a>
";

$telegram->sendText($chatId, $text, [
    'parse_mode' => Telegram::PARSE_MODE_HTML,
]);
```

### Markdown

```php
$text = "
*Жирный текст*
_Курсив_
[Ссылка](https://example.com)
`Моноширинный`
```
Блок кода
```
";

$telegram->sendText($chatId, $text, [
    'parse_mode' => Telegram::PARSE_MODE_MARKDOWN,
]);
```

## Ограничения Telegram API

- Текст сообщения: до 4096 символов
- Подпись: до 1024 символов
- Размер файла: до 50 МБ
- Скорость отправки: до 30 сообщений в секунду
- Фото: до 10 МБ (рекомендуется сжимать)

## Безопасность

1. **Защита токена**:
   ```bash
   chmod 600 config/telegram.json
   ```

2. **Валидация chat_id**:
   ```php
   if (!is_numeric($chatId)) {
       throw new Exception('Invalid chat ID');
   }
   ```

3. **Санитизация HTML**:
   ```php
   $text = htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8');
   ```

4. **Проверка файлов**:
   ```php
   if (!file_exists($file) || !is_readable($file)) {
       throw new Exception('File not accessible');
   }
   ```

## Производительность

- Используйте `disable_notification` для массовых рассылок
- Добавляйте паузы между сообщениями для соблюдения rate limits
- Кешируйте результаты `getMe()` если используется часто
- Используйте асинхронную отправку для множества сообщений

## См. также

- [Official Telegram Bot API](https://core.telegram.org/bots/api)
- [Logger документация](LOGGER.md) - для логирования
- [RSS документация](RSS.md) - для интеграции с новостными лентами
- [MySQL документация](MYSQL.md) - для хранения истории сообщений

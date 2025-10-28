# RSS/Atom Feed Parser

Безопасный и надежный парсер RSS/Atom лент для PHP 8.1+

## ✨ Возможности

- 🔒 **Безопасность**: Защита от XXE атак
- ✅ **Валидация**: Проверка URL и размера контента
- 📝 **Типизация**: Строгая типизация всех параметров и возвращаемых значений
- 🌐 **Поддержка форматов**: RSS 2.0 и Atom 1.0
- 📊 **Логирование**: Интеграция с компонентом Logger
- 🎯 **Production-ready**: Готов к использованию в production среде

## 📦 Установка

Класс входит в состав репозитория и находится в `src/Rss.class.php`

## 🚀 Быстрый старт

### Базовое использование

```php
<?php

require_once 'autoload.php';

use App\Component\Rss;

$rss = new Rss();
$feed = $rss->fetch('https://example.com/feed.xml');

echo "Заголовок: {$feed['title']}\n";
echo "Описание: {$feed['description']}\n";
echo "Элементов: " . count($feed['items']) . "\n";

foreach ($feed['items'] as $item) {
    echo "\n{$item['title']}\n";
    echo "{$item['link']}\n";
}
```

### С логгером

```php
<?php

use App\Component\Rss;
use App\Component\Logger;

$logger = new Logger([
    'directory' => '/var/log/myapp',
    'file_name' => 'rss.log',
]);

$rss = new Rss([
    'user_agent' => 'MyApp/1.0',
    'timeout' => 15,
    'max_content_size' => 5242880, // 5 MB
], $logger);

try {
    $feed = $rss->fetch('https://example.com/feed.xml');
    // Обработка ленты
} catch (RuntimeException $e) {
    echo "Ошибка: {$e->getMessage()}\n";
}
```

## ⚙️ Конфигурация

### Параметры конструктора

```php
$rss = new Rss([
    'user_agent' => 'RSSClient/1.0',        // User-Agent для HTTP запросов
    'timeout' => 10,                         // Таймаут соединения в секундах
    'max_content_size' => 10485760,         // Максимальный размер контента (10 MB)
], $logger);
```

| Параметр | Тип | По умолчанию | Описание |
|----------|-----|--------------|----------|
| `user_agent` | string | `'RSSClient/1.0 (+https://example.com)'` | User-Agent для HTTP запросов |
| `timeout` | int | `10` | Таймаут соединения в секундах (минимум 1) |
| `max_content_size` | int | `10485760` | Максимальный размер контента в байтах (минимум 1024) |

## 📋 Формат возвращаемых данных

### Структура ленты

```php
[
    'type' => 'rss',              // Тип: 'rss' или 'atom'
    'title' => 'Заголовок ленты',
    'description' => 'Описание',
    'link' => 'https://example.com',
    'language' => 'ru',
    'items' => [                  // Массив элементов
        [
            'title' => 'Заголовок статьи',
            'link' => 'https://example.com/article',
            'description' => 'Описание статьи',
            'published_at' => DateTimeImmutable|null,
            'author' => 'Автор',
            'categories' => ['cat1', 'cat2'],
        ],
        // ... другие элементы
    ],
]
```

## 🔒 Безопасность

### Защита от XXE атак

Класс использует безопасные настройки libxml:

```php
LIBXML_NOCDATA  // Конвертирует CDATA в текстовые узлы
LIBXML_NOENT    // Запрещает подстановку сущностей
LIBXML_NONET    // Блокирует сетевой доступ
```

### Валидация URL

- Проверка формата URL
- Только HTTP/HTTPS протоколы
- Обязательное наличие хоста

### Ограничение размера

Защита от исчерпания памяти:

```php
$rss = new Rss([
    'max_content_size' => 1048576, // 1 MB
]);
```

## ⚠️ Обработка ошибок

### RuntimeException

Генерируется при:
- Невалидном URL
- HTTP ошибках (коды 400+)
- Ошибках парсинга XML
- Превышении лимита размера контента
- Неизвестном формате ленты

```php
try {
    $feed = $rss->fetch($url);
} catch (RuntimeException $e) {
    // Обработка ошибки
    error_log("RSS Error: {$e->getMessage()}");
}
```

### Exception

Генерируется при критических ошибках:

```php
try {
    $feed = $rss->fetch($url);
} catch (Exception $e) {
    // Критическая ошибка
    error_log("Critical Error: {$e->getMessage()}");
}
```

## 📊 Примеры использования

### Обработка обоих форматов

```php
$feed = $rss->fetch($url);

switch ($feed['type']) {
    case 'rss':
        echo "RSS 2.0 лента\n";
        break;
    case 'atom':
        echo "Atom 1.0 лента\n";
        break;
}
```

### Работа с датами

```php
foreach ($feed['items'] as $item) {
    if ($item['published_at'] !== null) {
        echo $item['published_at']->format('Y-m-d H:i:s');
    } else {
        echo "Дата неизвестна";
    }
}
```

### Обработка категорий

```php
foreach ($feed['items'] as $item) {
    if (!empty($item['categories'])) {
        echo "Категории: " . implode(', ', $item['categories']) . "\n";
    }
}
```

## 🧪 Примеры кода

Полные примеры использования доступны в файле:
- `examples/rss_example.php`

## 📚 Документация

### PHPDoc

Все методы полностью документированы на русском языке с описанием:
- Назначения метода
- Параметров и их типов
- Возвращаемых значений
- Возможных исключений

## 🎯 Требования

- PHP 8.1 или выше
- Расширение libxml
- Расширение SimpleXML
- Guzzle HTTP Client (для Http класса)

## 🔄 Обратная совместимость

Класс полностью совместим с предыдущей версией:
- ✅ Сигнатура методов не изменилась
- ✅ Формат данных идентичен
- ✅ Все существующие тесты должны работать

## 📈 Производительность

### Оптимизации
- Проверка существования элементов перед итерацией
- Использование констант вместо строк
- Early return для упрощения логики
- Минимизация дублирования кода

### Рекомендации
- Используйте кеширование результатов для часто запрашиваемых лент
- Настройте разумные таймауты (10-15 секунд)
- Ограничьте размер контента в соответствии с вашими требованиями

## 🐛 Отладка

### С использованием Logger

```php
$logger = new Logger([
    'directory' => '/var/log/myapp',
    'file_name' => 'rss_debug.log',
]);

$rss = new Rss([], $logger);

// Все ошибки и предупреждения будут записаны в лог
$feed = $rss->fetch($url);
```

### Логируемые события
- HTTP ошибки
- Ошибки парсинга XML
- Ошибки валидации URL
- Ошибки парсинга дат
- Критические ошибки

## 💡 Лучшие практики

1. **Всегда используйте try-catch**
   ```php
   try {
       $feed = $rss->fetch($url);
   } catch (RuntimeException $e) {
       // Обработка ошибки
   }
   ```

2. **Проверяйте наличие данных**
   ```php
   if ($item['published_at'] !== null) {
       // Работа с датой
   }
   ```

3. **Используйте логгер в production**
   ```php
   $rss = new Rss($config, $logger);
   ```

4. **Настройте таймауты**
   ```php
   $rss = new Rss(['timeout' => 15]);
   ```

5. **Кешируйте результаты**
   ```php
   $cache_key = 'rss_' . md5($url);
   $feed = $cache->remember($cache_key, function() use ($rss, $url) {
       return $rss->fetch($url);
   }, 3600);
   ```

## 📝 Лицензия

Часть проекта, лицензия указана в корневом README.md

## 🤝 Вклад

Класс разработан с соблюдением стандартов PHP 8.1+ и готов к использованию в production.

## 📞 Поддержка

При возникновении проблем:
1. Проверьте логи (если используется Logger)
2. Проверьте валидность RSS/Atom ленты
3. Убедитесь в доступности URL

---

**Версия: 2.0.0 (Production Ready)**

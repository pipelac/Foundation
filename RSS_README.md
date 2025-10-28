# RSS/Atom Feed Parser на базе SimplePie

Современный, безопасный и производительный парсер RSS/Atom лент для PHP 8.1+ на базе библиотеки SimplePie

## ✨ Возможности

- 🚀 **Производительность**: Использование мощной библиотеки SimplePie
- 🔒 **Безопасность**: Защита от XXE атак и валидация данных
- 💾 **Кеширование**: Встроенная поддержка кеширования для оптимизации
- 🧹 **Санитизация**: Автоматическая очистка HTML контента
- ✅ **Валидация**: Проверка URL и размера контента
- 📝 **Типизация**: Строгая типизация всех параметров и возвращаемых значений
- 🌐 **Поддержка форматов**: RSS 0.90-2.0, Atom 0.3-1.0, RDF
- 📊 **Логирование**: Интеграция с компонентом Logger
- 🎯 **Production-ready**: Готов к использованию в production среде
- 🛡️ **Надежность**: Обработка битых и некорректных фидов

## 📦 Установка

### Требования

- PHP 8.1 или выше
- SimplePie 1.8+
- Guzzle HTTP Client 7.8+
- Расширения: libxml, SimpleXML, curl

### Установка через Composer

```bash
composer require simplepie/simplepie
```

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

### С логгером и кешированием

```php
<?php

use App\Component\Rss;
use App\Component\Logger;

$logger = new Logger([
    'directory' => '/var/log/myapp',
    'file_name' => 'rss.log',
]);

$rss = new Rss([
    'user_agent' => 'MyApp/2.0',
    'timeout' => 15,
    'max_content_size' => 5242880, // 5 MB
    'cache_directory' => '/tmp/rss_cache',
    'cache_duration' => 3600, // 1 час
    'enable_cache' => true,
    'enable_sanitization' => true,
], $logger);

try {
    $feed = $rss->fetch('https://example.com/feed.xml');
    // Обработка ленты
} catch (RssException $e) {
    echo "Ошибка: {$e->getMessage()}\n";
}
```

## ⚙️ Конфигурация

### Параметры конструктора

```php
$rss = new Rss([
    'user_agent' => 'MyRSSReader/2.0',        // User-Agent для HTTP запросов
    'timeout' => 10,                           // Таймаут соединения в секундах
    'max_content_size' => 10485760,           // Максимальный размер контента (10 MB)
    'cache_directory' => '/tmp/rss_cache',    // Директория для кеша
    'cache_duration' => 3600,                 // Длительность кеша в секундах
    'enable_cache' => true,                   // Включить кеширование
    'enable_sanitization' => true,            // Включить санитизацию HTML
], $logger);
```

| Параметр | Тип | По умолчанию | Описание |
|----------|-----|--------------|----------|
| `user_agent` | string | `'RSSClient/2.0 (+https://example.com) SimplePie'` | User-Agent для HTTP запросов |
| `timeout` | int | `10` | Таймаут соединения в секундах (минимум 1) |
| `max_content_size` | int | `10485760` | Максимальный размер контента в байтах (минимум 1024) |
| `cache_directory` | string\|null | `null` | Директория для кеширования (null - отключить) |
| `cache_duration` | int | `3600` | Длительность кеша в секундах (1 час) |
| `enable_cache` | bool | `true` | Включить/выключить кеширование |
| `enable_sanitization` | bool | `true` | Включить/выключить санитизацию HTML |

## 📋 Формат возвращаемых данных

### Структура ленты

```php
[
    'type' => 'rss',                     // Тип: 'rss', 'atom', 'rdf' или 'unknown'
    'title' => 'Заголовок ленты',
    'description' => 'Описание',
    'link' => 'https://example.com',
    'language' => 'ru',
    'image' => 'https://example.com/logo.png',
    'copyright' => '© 2024 Example',
    'generator' => 'WordPress 6.0',
    'items' => [                         // Массив элементов
        [
            'title' => 'Заголовок статьи',
            'link' => 'https://example.com/article',
            'description' => 'Краткое описание',
            'content' => 'Полный контент статьи',
            'published_at' => DateTimeImmutable|null,
            'author' => 'Автор',
            'categories' => ['cat1', 'cat2'],
            'enclosures' => [            // Медиа вложения
                [
                    'url' => 'https://example.com/media.mp3',
                    'type' => 'audio/mpeg',
                    'length' => '12345',
                    'title' => 'Название медиа',
                ],
            ],
            'id' => 'unique-item-id',
        ],
        // ... другие элементы
    ],
]
```

## 🔒 Безопасность

### Защита от XXE атак

SimplePie автоматически использует безопасные настройки libxml:

- Запрещает загрузку внешних сущностей
- Блокирует сетевой доступ при парсинге
- Отключает подстановку сущностей

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

### Санитизация HTML

Автоматическая очистка HTML контента от потенциально опасных элементов:

```php
$rss = new Rss([
    'enable_sanitization' => true, // Включено по умолчанию
]);
```

## 💾 Кеширование

### Настройка кеширования

```php
$rss = new Rss([
    'cache_directory' => '/tmp/rss_cache',
    'cache_duration' => 3600, // 1 час
    'enable_cache' => true,
]);
```

Кеширование значительно повышает производительность:
- Уменьшает нагрузку на внешние сервера
- Снижает время ответа при повторных запросах
- Экономит трафик

### Очистка кеша

Вручную удалите файлы из директории кеша или настройте автоматическую очистку через cron:

```bash
# Очистка кеша старше 24 часов
find /tmp/rss_cache -type f -mtime +1 -delete
```

## ⚠️ Обработка ошибок

### RssValidationException

Генерируется при валидации входных данных:
- Пустой URL
- Некорректный формат URL
- Недопустимый протокол

```php
use App\Component\Exception\RssValidationException;

try {
    $feed = $rss->fetch($url);
} catch (RssValidationException $e) {
    // Обработка ошибки валидации
    error_log("Validation Error: {$e->getMessage()}");
}
```

### RssException

Генерируется при:
- HTTP ошибках (коды 400+)
- Ошибках парсинга XML
- Превышении лимита размера контента
- Проблемах с кешем

```php
use App\Component\Exception\RssException;

try {
    $feed = $rss->fetch($url);
} catch (RssException $e) {
    // Обработка RSS ошибки
    error_log("RSS Error: {$e->getMessage()}");
}
```

### Exception

Критические ошибки:

```php
try {
    $feed = $rss->fetch($url);
} catch (Exception $e) {
    // Критическая ошибка
    error_log("Critical Error: {$e->getMessage()}");
}
```

## 📊 Примеры использования

### Работа с разными форматами

```php
$feed = $rss->fetch($url);

switch ($feed['type']) {
    case 'rss':
        echo "RSS 2.0 лента\n";
        break;
    case 'atom':
        echo "Atom 1.0 лента\n";
        break;
    case 'rdf':
        echo "RDF лента\n";
        break;
    default:
        echo "Неизвестный формат\n";
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

### Работа с медиа вложениями

```php
foreach ($feed['items'] as $item) {
    foreach ($item['enclosures'] as $enclosure) {
        echo "Медиа: {$enclosure['url']}\n";
        echo "Тип: {$enclosure['type']}\n";
        echo "Размер: {$enclosure['length']} байт\n";
    }
}
```

### Извлечение полного контента

```php
foreach ($feed['items'] as $item) {
    // Используем полный контент, если доступен
    $text = !empty($item['content']) ? $item['content'] : $item['description'];
    echo strip_tags($text); // Удаляем HTML теги
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
- SimplePie 1.8+
- Расширение libxml
- Расширение SimpleXML
- Расширение curl
- Guzzle HTTP Client (для Http класса)

## 🔄 Обратная совместимость

Класс максимально совместим с предыдущей версией:
- ✅ Основной метод `fetch()` сохранен
- ✅ Формат данных расширен (добавлены новые поля)
- ✅ Все базовые тесты должны работать
- ⚠️ Новые поля: `image`, `copyright`, `generator`, `content`, `enclosures`, `id`

### Миграция с предыдущей версии

Изменения минимальны - просто обновите код:

```php
// Старый код продолжает работать
$feed = $rss->fetch($url);
echo $feed['title'];
echo $feed['items'][0]['title'];

// Новые возможности
echo $feed['image']; // Логотип ленты
echo $feed['items'][0]['content']; // Полный контент
```

## 📈 Производительность

### Оптимизации

- Использование SimplePie - одной из самых быстрых RSS библиотек
- Встроенное кеширование уменьшает повторные запросы
- Контроль размера контента защищает от перегрузки
- Ленивая загрузка данных через SimplePie
- Минимизация дублирования кода

### Рекомендации

1. **Включите кеширование** для часто запрашиваемых лент:
   ```php
   $rss = new Rss([
       'cache_directory' => '/tmp/rss_cache',
       'cache_duration' => 1800, // 30 минут
   ]);
   ```

2. **Настройте разумные таймауты**:
   ```php
   $rss = new Rss(['timeout' => 10]);
   ```

3. **Ограничьте размер контента**:
   ```php
   $rss = new Rss(['max_content_size' => 5242880]); // 5 MB
   ```

4. **Используйте очередь для обработки** множества лент
5. **Мониторьте производительность** через логирование

## 🐛 Отладка

### С использованием Logger

```php
$logger = new Logger([
    'directory' => '/var/log/myapp',
    'file_name' => 'rss_debug.log',
]);

$rss = new Rss([], $logger);

// Все операции будут залогированы
$feed = $rss->fetch($url);
```

### Логируемые события

- Инициализация RSS клиента
- Начало загрузки ленты
- Успешная загрузка (с типом и количеством элементов)
- HTTP ошибки
- Ошибки парсинга SimplePie
- Ошибки валидации URL
- Ошибки обработки отдельных элементов
- Ошибки парсинга дат
- Критические ошибки с трассировкой

## 💡 Лучшие практики

1. **Всегда используйте try-catch**
   ```php
   try {
       $feed = $rss->fetch($url);
   } catch (RssValidationException $e) {
       // Обработка ошибки валидации
   } catch (RssException $e) {
       // Обработка RSS ошибки
   } catch (Exception $e) {
       // Критическая ошибка
   }
   ```

2. **Проверяйте наличие данных**
   ```php
   if ($item['published_at'] !== null) {
       // Работа с датой
   }
   
   if (!empty($item['content'])) {
       // Используем полный контент
   }
   ```

3. **Используйте логгер в production**
   ```php
   $rss = new Rss($config, $logger);
   ```

4. **Настройте таймауты адекватно**
   ```php
   $rss = new Rss(['timeout' => 15]);
   ```

5. **Включите кеширование**
   ```php
   $rss = new Rss([
       'cache_directory' => '/tmp/rss_cache',
       'cache_duration' => 3600,
   ]);
   ```

6. **Обрабатывайте ошибки элементов gracefully**
   ```php
   // Класс автоматически пропускает проблемные элементы
   // и логирует предупреждения
   ```

7. **Регулярно чистите кеш**
   ```bash
   # Cron задача для очистки старого кеша
   0 0 * * * find /tmp/rss_cache -type f -mtime +7 -delete
   ```

## 🆕 Новые возможности (версия 3.0)

### SimplePie интеграция

- Поддержка большего количества форматов RSS/Atom
- Лучшая обработка некорректных фидов
- Встроенная санитизация HTML
- Автоматическое определение кодировки

### Расширенные данные

- **Изображения лент**: `$feed['image']`
- **Информация о копирайте**: `$feed['copyright']`
- **Генератор ленты**: `$feed['generator']`
- **Полный контент**: `$item['content']`
- **Медиа вложения**: `$item['enclosures']`
- **Уникальный ID**: `$item['id']`

### Кеширование

- Встроенная поддержка кеширования
- Настраиваемая длительность кеша
- Автоматическое управление кешем

### Санитизация

- Автоматическая очистка HTML от опасных элементов
- Защита от XSS атак в контенте
- Настраиваемая санитизация

## 📝 Лицензия

Часть проекта, лицензия указана в корневом README.md

## 🤝 Вклад

Класс разработан с соблюдением стандартов PHP 8.1+ и готов к использованию в production.

## 📞 Поддержка

При возникновении проблем:
1. Проверьте логи (если используется Logger)
2. Проверьте валидность RSS/Atom ленты
3. Убедитесь в доступности URL
4. Проверьте права на директорию кеша
5. Убедитесь, что SimplePie установлен

---

**Версия: 3.0.0 (Production Ready with SimplePie)**

Дата обновления: 2024

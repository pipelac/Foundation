# WebtExtractor — Извлечение контента из веб-страниц

Класс для извлечения основного контента из веб-страниц на базе библиотеки fivefilters/readability.php.

## Возможности

- ✅ Извлечение основного текстового контента из любой веб-страницы
- ✅ Автоматическое определение заголовка, автора, даты публикации
- ✅ Извлечение всех изображений и ссылок из статьи
- ✅ Извлечение мета-данных (Open Graph, Twitter Cards, JSON-LD)
- ✅ Поддержка различных кодировок
- ✅ Интеграция с HTTP клиентом (retry-механизмы, прокси, таймауты)
- ✅ Интеграция с Logger для полного логирования операций
- ✅ Пакетная обработка множества URL
- ✅ Валидация входных данных
- ✅ Обработка исключений на каждом уровне
- ✅ Расчет времени чтения
- ✅ Подсчет количества слов

## Требования

- PHP 8.1+
- ext-dom
- ext-mbstring
- ext-json
- fivefilters/readability.php ^3.1

## Установка

```bash
composer require fivefilters/readability.php
```

## Использование

### Базовый пример

```php
<?php

require_once __DIR__ . '/autoload.php';

use App\Component\WebtExtractor;
use App\Component\Logger;

// Инициализация логгера (опционально)
$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'file_name' => 'webt-extractor.log',
    'max_files' => 5,
    'max_file_size' => 10,
]);

// Инициализация экстрактора
$extractor = new WebtExtractor([
    'timeout' => 30,
    'retries' => 3,
    'user_agent' => 'Mozilla/5.0 (compatible; MyBot/1.0)',
], $logger);

// Извлечение контента
try {
    $result = $extractor->extract('https://example.com/article');
    
    echo "Заголовок: {$result['title']}\n";
    echo "Автор: {$result['author']}\n";
    echo "Количество слов: {$result['word_count']}\n";
    echo "Время чтения: {$result['read_time']} мин.\n";
    echo "\nКонтент:\n{$result['text_content']}\n";
    
} catch (Exception $e) {
    echo "Ошибка: {$e->getMessage()}\n";
}
```

### Извлечение из готового HTML

```php
<?php

$html = file_get_contents('article.html');

try {
    $result = $extractor->extractFromHtml($html, 'https://example.com/article');
    
    print_r($result);
    
} catch (Exception $e) {
    echo "Ошибка: {$e->getMessage()}\n";
}
```

### Пакетная обработка ссылок из RSS

```php
<?php

use App\Component\WebtExtractor;
use App\Component\Rss;
use App\Component\Logger;

// Инициализация
$logger = new Logger(['directory' => __DIR__ . '/logs']);
$rss = new Rss([], $logger);
$extractor = new WebtExtractor(['retries' => 3], $logger);

// Загружаем RSS ленту
$feed = $rss->fetch('https://example.com/rss');

// Извлекаем URL всех статей
$urls = array_map(fn($item) => $item['link'], $feed['items']);

// Пакетное извлечение контента
$results = $extractor->extractBatch($urls, continueOnError: true);

foreach ($results as $url => $result) {
    if (isset($result['error'])) {
        echo "Ошибка [{$url}]: {$result['error']}\n";
        continue;
    }
    
    echo "✓ {$result['title']} ({$result['word_count']} слов)\n";
}
```

### Использование с прокси

```php
<?php

$extractor = new WebtExtractor([
    'proxy' => 'http://proxy.example.com:8080',
    // или с аутентификацией:
    // 'proxy' => 'http://user:pass@proxy.example.com:8080',
    // или настройка для разных протоколов:
    // 'proxy' => [
    //     'http' => 'tcp://proxy.example.com:8080',
    //     'https' => 'tcp://proxy.example.com:8081',
    // ],
    'timeout' => 60,
    'retries' => 5,
], $logger);

$result = $extractor->extract('https://example.com/article');
```

### Расширенная конфигурация

```php
<?php

$extractor = new WebtExtractor([
    // HTTP настройки
    'user_agent' => 'Mozilla/5.0 (compatible; MyBot/1.0)',
    'timeout' => 30,                    // Таймаут соединения (сек)
    'connect_timeout' => 10,             // Таймаут подключения (сек)
    'retries' => 3,                      // Количество повторных попыток
    'max_content_size' => 10485760,      // Максимальный размер контента (10 МБ)
    
    // Прокси настройки
    'proxy' => 'http://proxy.example.com:8080',
    'verify_ssl' => true,                // Проверка SSL сертификата
    
    // Извлечение дополнительных данных
    'extract_images' => true,            // Извлекать изображения
    'extract_links' => true,             // Извлекать ссылки
    'extract_metadata' => true,          // Извлекать мета-данные
], $logger);
```

## Структура результата

Метод `extract()` возвращает ассоциативный массив со следующей структурой:

```php
[
    // Основная информация
    'url' => 'https://example.com/article',
    'title' => 'Заголовок статьи',
    'author' => 'Имя автора',
    
    // Контент
    'content' => '<div>HTML контент статьи</div>',
    'text_content' => 'Текстовая версия контента',
    'excerpt' => 'Краткое описание статьи',
    
    // Изображения
    'lead_image_url' => 'https://example.com/image.jpg',
    'images' => [
        [
            'src' => 'https://example.com/image1.jpg',
            'alt' => 'Альтернативный текст',
            'title' => 'Заголовок изображения',
            'width' => '800',
            'height' => '600',
        ],
        // ...
    ],
    
    // Ссылки
    'links' => [
        [
            'href' => 'https://example.com/related',
            'text' => 'Текст ссылки',
            'title' => 'Заголовок ссылки',
            'rel' => 'nofollow',
        ],
        // ...
    ],
    
    // Мета-данные
    'metadata' => [
        'open_graph' => [
            'title' => 'OG заголовок',
            'description' => 'OG описание',
            'image' => 'https://example.com/og-image.jpg',
            // ...
        ],
        'twitter_card' => [
            'card' => 'summary_large_image',
            'title' => 'Twitter заголовок',
            // ...
        ],
        'meta' => [
            'description' => 'META описание',
            'keywords' => 'ключевые, слова',
            // ...
        ],
        'json_ld' => [
            // JSON-LD структурированные данные
        ],
    ],
    
    // Дополнительная информация
    'date_published' => null,            // Дата публикации (если найдена)
    'language' => 'ru',                  // Язык контента
    'word_count' => 1250,                // Количество слов
    'read_time' => 6,                    // Время чтения в минутах
    'extracted_at' => '2024-01-15 14:30:00', // Время извлечения
]
```

## Обработка ошибок

Класс использует специализированные исключения:

```php
use App\Component\Exception\WebtExtractorException;
use App\Component\Exception\WebtExtractorValidationException;

try {
    $result = $extractor->extract($url);
    
} catch (WebtExtractorValidationException $e) {
    // Ошибки валидации (неправильный URL, пустой HTML и т.д.)
    echo "Ошибка валидации: {$e->getMessage()}\n";
    
} catch (WebtExtractorException $e) {
    // Общие ошибки извлечения контента
    echo "Ошибка извлечения: {$e->getMessage()}\n";
    
} catch (Exception $e) {
    // Критические ошибки
    echo "Критическая ошибка: {$e->getMessage()}\n";
}
```

## Интеграция с Logger

WebtExtractor автоматически логирует все операции при наличии Logger:

```php
$logger = new Logger([
    'directory' => __DIR__ . '/logs',
    'file_name' => 'webt-extractor.log',
]);

$extractor = new WebtExtractor([], $logger);

// Все операции будут залогированы:
// - INFO: инициализация, начало/конец извлечения
// - DEBUG: парсинг HTML, обработка элементов
// - WARNING: пропуск проблемных элементов
// - ERROR: ошибки загрузки, парсинга
```

## Примеры использования

### Извлечение для создания превью

```php
function createArticlePreview(string $url): array
{
    $extractor = new WebtExtractor([
        'extract_images' => true,
        'extract_links' => false,
        'extract_metadata' => true,
    ]);
    
    $result = $extractor->extract($url);
    
    return [
        'title' => $result['title'],
        'description' => substr($result['text_content'], 0, 200) . '...',
        'image' => $result['lead_image_url'] ?: ($result['images'][0]['src'] ?? ''),
        'read_time' => $result['read_time'],
    ];
}
```

### Поиск внешних ссылок

```php
function findExternalLinks(string $url): array
{
    $extractor = new WebtExtractor([
        'extract_links' => true,
    ]);
    
    $result = $extractor->extract($url);
    $domain = parse_url($url, PHP_URL_HOST);
    
    return array_filter(
        $result['links'],
        fn($link) => parse_url($link['href'], PHP_URL_HOST) !== $domain
    );
}
```

### Извлечение структурированных данных

```php
function extractStructuredData(string $url): array
{
    $extractor = new WebtExtractor([
        'extract_metadata' => true,
    ]);
    
    $result = $extractor->extract($url);
    
    $structuredData = [
        'title' => $result['metadata']['open_graph']['title'] 
                    ?? $result['metadata']['meta']['title'] 
                    ?? $result['title'],
        'description' => $result['metadata']['open_graph']['description']
                    ?? $result['metadata']['meta']['description']
                    ?? $result['excerpt'],
        'image' => $result['metadata']['open_graph']['image']
                    ?? $result['lead_image_url'],
        'type' => $result['metadata']['open_graph']['type'] ?? 'article',
    ];
    
    return $structuredData;
}
```

## Производительность

- Используйте пакетную обработку `extractBatch()` для множества URL
- Настройте таймауты и retries в зависимости от стабильности источников
- Отключайте `extract_images`, `extract_links`, `extract_metadata` если не нужны
- Используйте прокси для обхода rate-limiting
- Логируйте только важные события для уменьшения I/O

## Лучшие практики

1. **Всегда используйте try-catch** для обработки исключений
2. **Настройте Logger** для отслеживания проблем в продакшене
3. **Используйте retry механизм** для нестабильных источников
4. **Ограничивайте max_content_size** для защиты от больших файлов
5. **Проверяйте валидность URL** перед массовой обработкой
6. **Используйте прокси** при большом количестве запросов к одному домену

## Решение проблем

### Не удается извлечь контент

- Проверьте доступность URL (может быть 403, 404, 500)
- Убедитесь что страница содержит читаемый текст (не JS-приложение)
- Попробуйте изменить User-Agent

### Таймауты при загрузке

- Увеличьте `timeout` и `connect_timeout`
- Используйте прокси ближе к целевому серверу
- Проверьте скорость интернет-соединения

### Пустой результат

- Страница может быть защищена от скрейпинга
- Контент может загружаться через JavaScript
- Попробуйте загрузить HTML вручную и использовать `extractFromHtml()`

## Связь с другими компонентами

- **Http**: используется для загрузки страниц с retry и прокси
- **Logger**: используется для логирования всех операций
- **Rss**: можно комбинировать для извлечения полного контента из RSS

## Лицензия

Этот компонент использует библиотеку fivefilters/readability.php, 
которая распространяется под лицензией Apache License 2.0.

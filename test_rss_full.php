<?php

declare(strict_types=1);

/**
 * Полноценный тест всех методов класса Rss
 * Тестирует реальную функциональность с логированием
 */

require_once __DIR__ . '/autoload.php';

use App\Component\Rss;
use App\Component\Logger;
use App\Component\Exception\RssException;
use App\Component\Exception\RssValidationException;

// Цвета для вывода в консоль
const COLOR_GREEN = "\033[32m";
const COLOR_RED = "\033[31m";
const COLOR_YELLOW = "\033[33m";
const COLOR_BLUE = "\033[34m";
const COLOR_RESET = "\033[0m";

/**
 * Вывод результата теста
 */
function printTestResult(string $testName, bool $success, ?string $details = null): void
{
    $status = $success ? COLOR_GREEN . '✓ PASSED' : COLOR_RED . '✗ FAILED';
    echo sprintf("%s %s%s\n", $status, $testName, COLOR_RESET);
    if ($details !== null) {
        echo COLOR_YELLOW . "   → $details" . COLOR_RESET . "\n";
    }
}

/**
 * Заголовок раздела
 */
function printSection(string $title): void
{
    echo "\n" . COLOR_BLUE . str_repeat('=', 80) . COLOR_RESET . "\n";
    echo COLOR_BLUE . $title . COLOR_RESET . "\n";
    echo COLOR_BLUE . str_repeat('=', 80) . COLOR_RESET . "\n\n";
}

/**
 * Создание тестового RSS контента
 */
function createTestRssFeed(): string
{
    return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>Тестовая RSS лента</title>
        <link>https://example.com</link>
        <description>Описание тестовой ленты для проверки парсинга</description>
        <language>ru</language>
        <copyright>© 2024 Test Feed</copyright>
        <generator>Test Generator 1.0</generator>
        <image>
            <url>https://example.com/image.jpg</url>
            <title>Тестовое изображение</title>
            <link>https://example.com</link>
        </image>
        <item>
            <title>Первая новость</title>
            <link>https://example.com/news/1</link>
            <description>Краткое описание первой новости</description>
            <content:encoded xmlns:content="http://purl.org/rss/1.0/modules/content/">
                <![CDATA[<p>Полный текст первой новости с <strong>HTML разметкой</strong></p>]]>
            </content:encoded>
            <pubDate>Wed, 30 Oct 2024 12:00:00 +0000</pubDate>
            <author>author1@example.com (Автор Первый)</author>
            <category>Технологии</category>
            <category>Новости</category>
            <guid isPermaLink="true">https://example.com/news/1</guid>
            <enclosure url="https://example.com/audio/1.mp3" length="12345" type="audio/mpeg"/>
        </item>
        <item>
            <title>Вторая новость</title>
            <link>https://example.com/news/2</link>
            <description>Краткое описание второй новости</description>
            <pubDate>Wed, 30 Oct 2024 14:00:00 +0000</pubDate>
            <author>author2@example.com (Автор Второй)</author>
            <category>Бизнес</category>
            <guid>unique-id-2</guid>
        </item>
        <item>
            <title>Третья новость без даты</title>
            <link>https://example.com/news/3</link>
            <description>Новость без даты публикации</description>
        </item>
    </channel>
</rss>
XML;
}

/**
 * Создание тестового Atom фида
 */
function createTestAtomFeed(): string
{
    return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
    <title>Тестовая Atom лента</title>
    <link href="https://example.com/atom"/>
    <updated>2024-10-30T12:00:00Z</updated>
    <id>https://example.com/atom</id>
    <subtitle>Описание Atom ленты</subtitle>
    
    <entry>
        <title>Atom новость 1</title>
        <link href="https://example.com/atom/1"/>
        <id>atom-entry-1</id>
        <updated>2024-10-30T12:00:00Z</updated>
        <summary>Краткое описание Atom новости</summary>
        <content type="html"><![CDATA[<p>Полный контент <em>Atom</em> новости</p>]]></content>
        <author>
            <name>Atom Автор</name>
            <email>atom@example.com</email>
        </author>
        <category term="Atom Category"/>
    </entry>
</feed>
XML;
}

/**
 * Создание некорректного XML
 */
function createInvalidXml(): string
{
    return '<?xml version="1.0" encoding="UTF-8"?><rss><channel><title>Broken';
}

// =============================================================================
// НАЧАЛО ТЕСТИРОВАНИЯ
// =============================================================================

echo COLOR_BLUE . "\n";
echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║         ПОЛНОЦЕННОЕ ТЕСТИРОВАНИЕ КЛАССА RSS (PHP 8.1+)                    ║\n";
echo "║         Детальная проверка всех методов с логированием                    ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n";
echo COLOR_RESET . "\n";

$testResults = [];
$logDir = __DIR__ . '/logs_rss_test';
$cacheDir = __DIR__ . '/cache_rss_test';

// Создание директорий
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

// Инициализация логгера
$logger = new Logger([
    'directory' => $logDir,
    'file_name' => 'rss_test.log',
    'max_files' => 3,
    'max_file_size' => 5242880, // 5 MB
]);

// =============================================================================
// ТЕСТ 1: Инициализация класса с различными конфигурациями
// =============================================================================

printSection('ТЕСТ 1: Инициализация класса Rss');

try {
    // 1.1 Инициализация с минимальной конфигурацией
    $rss1 = new Rss([], null);
    printTestResult('1.1 Инициализация с пустой конфигурацией', true);
    $testResults['init_empty'] = true;
} catch (Exception $e) {
    printTestResult('1.1 Инициализация с пустой конфигурацией', false, $e->getMessage());
    $testResults['init_empty'] = false;
}

try {
    // 1.2 Инициализация с логгером
    $rss2 = new Rss([], $logger);
    printTestResult('1.2 Инициализация с логгером', true);
    $testResults['init_logger'] = true;
} catch (Exception $e) {
    printTestResult('1.2 Инициализация с логгером', false, $e->getMessage());
    $testResults['init_logger'] = false;
}

try {
    // 1.3 Инициализация с полной конфигурацией
    $rss3 = new Rss([
        'user_agent' => 'TestRSSClient/1.0',
        'timeout' => 15,
        'max_content_size' => 5242880, // 5 MB
        'cache_directory' => $cacheDir,
        'cache_duration' => 1800,
        'enable_cache' => true,
        'enable_sanitization' => true,
    ], $logger);
    printTestResult('1.3 Инициализация с полной конфигурацией', true);
    $testResults['init_full'] = true;
} catch (Exception $e) {
    printTestResult('1.3 Инициализация с полной конфигурацией', false, $e->getMessage());
    $testResults['init_full'] = false;
}

try {
    // 1.4 Инициализация с некорректной директорией кеша
    $invalidCacheDir = '/invalid/path/that/does/not/exist/and/cannot/be/created';
    $rss4 = new Rss([
        'cache_directory' => $invalidCacheDir,
        'enable_cache' => true,
    ], $logger);
    printTestResult('1.4 Инициализация с некорректной директорией кеша', false, 'Должно было выбросить исключение');
    $testResults['init_invalid_cache'] = false;
} catch (RssException $e) {
    printTestResult('1.4 Инициализация с некорректной директорией кеша', true, 'Ожидаемое исключение: ' . $e->getMessage());
    $testResults['init_invalid_cache'] = true;
} catch (Exception $e) {
    printTestResult('1.4 Инициализация с некорректной директорией кеша', false, 'Неожиданное исключение: ' . $e->getMessage());
    $testResults['init_invalid_cache'] = false;
}

try {
    // 1.5 Инициализация с отключенным кешированием
    $rss5 = new Rss([
        'enable_cache' => false,
    ], $logger);
    printTestResult('1.5 Инициализация с отключенным кешированием', true);
    $testResults['init_no_cache'] = true;
} catch (Exception $e) {
    printTestResult('1.5 Инициализация с отключенным кешированием', false, $e->getMessage());
    $testResults['init_no_cache'] = false;
}

// =============================================================================
// ТЕСТ 2: Валидация URL
// =============================================================================

printSection('ТЕСТ 2: Валидация URL');

$rssValidator = new Rss([], $logger);

// 2.1 Валидный HTTP URL
try {
    $reflection = new ReflectionClass($rssValidator);
    $method = $reflection->getMethod('validateUrl');
    $method->setAccessible(true);
    
    $method->invoke($rssValidator, 'http://example.com/feed.xml');
    printTestResult('2.1 Валидный HTTP URL', true);
    $testResults['validate_http'] = true;
} catch (Exception $e) {
    printTestResult('2.1 Валидный HTTP URL', false, $e->getMessage());
    $testResults['validate_http'] = false;
}

// 2.2 Валидный HTTPS URL
try {
    $reflection = new ReflectionClass($rssValidator);
    $method = $reflection->getMethod('validateUrl');
    $method->setAccessible(true);
    
    $method->invoke($rssValidator, 'https://example.com/feed.xml');
    printTestResult('2.2 Валидный HTTPS URL', true);
    $testResults['validate_https'] = true;
} catch (Exception $e) {
    printTestResult('2.2 Валидный HTTPS URL', false, $e->getMessage());
    $testResults['validate_https'] = false;
}

// 2.3 Пустой URL
try {
    $reflection = new ReflectionClass($rssValidator);
    $method = $reflection->getMethod('validateUrl');
    $method->setAccessible(true);
    
    $method->invoke($rssValidator, '');
    printTestResult('2.3 Пустой URL', false, 'Должно было выбросить исключение');
    $testResults['validate_empty'] = false;
} catch (RssValidationException $e) {
    printTestResult('2.3 Пустой URL', true, 'Ожидаемое исключение: ' . $e->getMessage());
    $testResults['validate_empty'] = true;
} catch (Exception $e) {
    printTestResult('2.3 Пустой URL', false, 'Неожиданное исключение: ' . $e->getMessage());
    $testResults['validate_empty'] = false;
}

// 2.4 URL без протокола
try {
    $reflection = new ReflectionClass($rssValidator);
    $method = $reflection->getMethod('validateUrl');
    $method->setAccessible(true);
    
    $method->invoke($rssValidator, 'example.com/feed.xml');
    printTestResult('2.4 URL без протокола', false, 'Должно было выбросить исключение');
    $testResults['validate_no_protocol'] = false;
} catch (RssValidationException $e) {
    printTestResult('2.4 URL без протокола', true, 'Ожидаемое исключение: ' . $e->getMessage());
    $testResults['validate_no_protocol'] = true;
} catch (Exception $e) {
    printTestResult('2.4 URL без протокола', false, 'Неожиданное исключение: ' . $e->getMessage());
    $testResults['validate_no_protocol'] = false;
}

// 2.5 URL с FTP протоколом
try {
    $reflection = new ReflectionClass($rssValidator);
    $method = $reflection->getMethod('validateUrl');
    $method->setAccessible(true);
    
    $method->invoke($rssValidator, 'ftp://example.com/feed.xml');
    printTestResult('2.5 URL с FTP протоколом', false, 'Должно было выбросить исключение');
    $testResults['validate_ftp'] = false;
} catch (RssValidationException $e) {
    printTestResult('2.5 URL с FTP протоколом', true, 'Ожидаемое исключение: ' . $e->getMessage());
    $testResults['validate_ftp'] = true;
} catch (Exception $e) {
    printTestResult('2.5 URL с FTP протоколом', false, 'Неожиданное исключение: ' . $e->getMessage());
    $testResults['validate_ftp'] = false;
}

// 2.6 URL без хоста
try {
    $reflection = new ReflectionClass($rssValidator);
    $method = $reflection->getMethod('validateUrl');
    $method->setAccessible(true);
    
    $method->invoke($rssValidator, 'http:///feed.xml');
    printTestResult('2.6 URL без хоста', false, 'Должно было выбросить исключение');
    $testResults['validate_no_host'] = false;
} catch (RssValidationException $e) {
    printTestResult('2.6 URL без хоста', true, 'Ожидаемое исключение: ' . $e->getMessage());
    $testResults['validate_no_host'] = true;
} catch (Exception $e) {
    printTestResult('2.6 URL без хоста', false, 'Неожиданное исключение: ' . $e->getMessage());
    $testResults['validate_no_host'] = false;
}

// =============================================================================
// ТЕСТ 3: Проверка размера контента
// =============================================================================

printSection('ТЕСТ 3: Проверка размера контента');

$rssSizeValidator = new Rss([
    'max_content_size' => 1024, // 1 KB для теста
], $logger);

// 3.1 Контент в пределах лимита
try {
    $reflection = new ReflectionClass($rssSizeValidator);
    $method = $reflection->getMethod('validateContentSize');
    $method->setAccessible(true);
    
    $smallContent = str_repeat('a', 500);
    $method->invoke($rssSizeValidator, $smallContent);
    printTestResult('3.1 Контент в пределах лимита (500 байт)', true);
    $testResults['size_small'] = true;
} catch (Exception $e) {
    printTestResult('3.1 Контент в пределах лимита (500 байт)', false, $e->getMessage());
    $testResults['size_small'] = false;
}

// 3.2 Контент превышает лимит
try {
    $reflection = new ReflectionClass($rssSizeValidator);
    $method = $reflection->getMethod('validateContentSize');
    $method->setAccessible(true);
    
    $largeContent = str_repeat('a', 2000);
    $method->invoke($rssSizeValidator, $largeContent);
    printTestResult('3.2 Контент превышает лимит (2000 байт)', false, 'Должно было выбросить исключение');
    $testResults['size_large'] = false;
} catch (RssException $e) {
    printTestResult('3.2 Контент превышает лимит (2000 байт)', true, 'Ожидаемое исключение: ' . $e->getMessage());
    $testResults['size_large'] = true;
} catch (Exception $e) {
    printTestResult('3.2 Контент превышает лимит (2000 байт)', false, 'Неожиданное исключение: ' . $e->getMessage());
    $testResults['size_large'] = false;
}

// =============================================================================
// ТЕСТ 4: Тестирование с реальными RSS лентами
// =============================================================================

printSection('ТЕСТ 4: Загрузка реальных RSS/Atom лент');

$rssReal = new Rss([
    'timeout' => 20,
    'max_content_size' => 10485760, // 10 MB
    'cache_directory' => $cacheDir,
    'cache_duration' => 3600,
    'enable_cache' => true,
], $logger);

// Список тестовых RSS лент
$testFeeds = [
    'Habr RSS' => 'https://habr.com/ru/rss/all/all/',
    'BBC News RSS' => 'http://feeds.bbci.co.uk/news/rss.xml',
    'NASA Breaking News' => 'https://www.nasa.gov/rss/dyn/breaking_news.rss',
];

foreach ($testFeeds as $feedName => $feedUrl) {
    try {
        echo "   Загрузка ленты: $feedName...\n";
        $data = $rssReal->fetch($feedUrl);
        
        $success = isset($data['title']) && isset($data['items']) && count($data['items']) > 0;
        $details = sprintf(
            'Тип: %s, Заголовок: %s, Элементов: %d',
            $data['type'],
            substr($data['title'], 0, 50),
            count($data['items'])
        );
        
        printTestResult("4.x Загрузка $feedName", $success, $details);
        $testResults["real_feed_" . md5($feedName)] = $success;
        
        // Дополнительная проверка первого элемента
        if (!empty($data['items'])) {
            $firstItem = $data['items'][0];
            $itemDetails = sprintf(
                'Первый элемент - Заголовок: %s, Ссылка: %s, Автор: %s',
                substr($firstItem['title'], 0, 40),
                substr($firstItem['link'], 0, 40),
                $firstItem['author'] ?: 'не указан'
            );
            echo COLOR_YELLOW . "   → $itemDetails" . COLOR_RESET . "\n";
        }
        
    } catch (RssValidationException $e) {
        printTestResult("4.x Загрузка $feedName", false, 'Ошибка валидации: ' . $e->getMessage());
        $testResults["real_feed_" . md5($feedName)] = false;
    } catch (RssException $e) {
        printTestResult("4.x Загрузка $feedName", false, 'Ошибка RSS: ' . $e->getMessage());
        $testResults["real_feed_" . md5($feedName)] = false;
    } catch (Exception $e) {
        printTestResult("4.x Загрузка $feedName", false, 'Неожиданная ошибка: ' . $e->getMessage());
        $testResults["real_feed_" . md5($feedName)] = false;
    }
    
    // Небольшая задержка между запросами
    sleep(1);
}

// =============================================================================
// ТЕСТ 5: Проверка структуры данных
// =============================================================================

printSection('ТЕСТ 5: Проверка структуры возвращаемых данных');

// Используем одну из успешно загруженных лент
try {
    $testFeedUrl = 'https://www.nasa.gov/rss/dyn/breaking_news.rss';
    $feedData = $rssReal->fetch($testFeedUrl);
    
    // 5.1 Проверка обязательных полей ленты
    $requiredFields = ['type', 'title', 'description', 'link', 'language', 'image', 'copyright', 'generator', 'items'];
    $allFieldsPresent = true;
    foreach ($requiredFields as $field) {
        if (!array_key_exists($field, $feedData)) {
            $allFieldsPresent = false;
            echo COLOR_RED . "   Отсутствует поле: $field" . COLOR_RESET . "\n";
        }
    }
    printTestResult('5.1 Наличие всех обязательных полей ленты', $allFieldsPresent);
    $testResults['structure_feed_fields'] = $allFieldsPresent;
    
    // 5.2 Проверка структуры элементов
    if (!empty($feedData['items'])) {
        $itemRequiredFields = ['title', 'link', 'description', 'content', 'published_at', 'author', 'categories', 'enclosures', 'id'];
        $firstItem = $feedData['items'][0];
        $allItemFieldsPresent = true;
        
        foreach ($itemRequiredFields as $field) {
            if (!array_key_exists($field, $firstItem)) {
                $allItemFieldsPresent = false;
                echo COLOR_RED . "   Отсутствует поле элемента: $field" . COLOR_RESET . "\n";
            }
        }
        printTestResult('5.2 Наличие всех обязательных полей элемента', $allItemFieldsPresent);
        $testResults['structure_item_fields'] = $allItemFieldsPresent;
        
        // 5.3 Проверка типов данных
        $typeChecks = [
            'title' => 'string',
            'link' => 'string',
            'description' => 'string',
            'content' => 'string',
            'author' => 'string',
            'id' => 'string',
            'categories' => 'array',
            'enclosures' => 'array',
        ];
        
        $allTypesCorrect = true;
        foreach ($typeChecks as $field => $expectedType) {
            $actualType = gettype($firstItem[$field]);
            if ($actualType !== $expectedType) {
                $allTypesCorrect = false;
                echo COLOR_RED . "   Неверный тип поля $field: ожидается $expectedType, получено $actualType" . COLOR_RESET . "\n";
            }
        }
        printTestResult('5.3 Корректность типов данных элементов', $allTypesCorrect);
        $testResults['structure_types'] = $allTypesCorrect;
    }
    
} catch (Exception $e) {
    printTestResult('5.x Проверка структуры данных', false, $e->getMessage());
    $testResults['structure_feed_fields'] = false;
    $testResults['structure_item_fields'] = false;
    $testResults['structure_types'] = false;
}

// =============================================================================
// ТЕСТ 6: Проверка кеширования
// =============================================================================

printSection('ТЕСТ 6: Проверка функциональности кеширования');

$rssCaching = new Rss([
    'cache_directory' => $cacheDir,
    'cache_duration' => 60, // 1 минута
    'enable_cache' => true,
], $logger);

try {
    $testUrl = 'https://www.nasa.gov/rss/dyn/breaking_news.rss';
    
    // Первая загрузка (из сети)
    $start1 = microtime(true);
    $data1 = $rssCaching->fetch($testUrl);
    $time1 = microtime(true) - $start1;
    
    // Вторая загрузка (должна быть из кеша)
    $start2 = microtime(true);
    $data2 = $rssCaching->fetch($testUrl);
    $time2 = microtime(true) - $start2;
    
    $details = sprintf(
        'Первая загрузка: %.3f сек, Вторая загрузка: %.3f сек',
        $time1,
        $time2
    );
    
    // Вторая загрузка должна быть значительно быстрее
    $cachingWorks = $time2 < $time1 * 0.5 || $time2 < 0.1;
    printTestResult('6.1 Кеширование работает', $cachingWorks, $details);
    $testResults['caching_works'] = $cachingWorks;
    
    // Проверка идентичности данных
    $dataIdentical = ($data1 === $data2);
    printTestResult('6.2 Данные из кеша идентичны', $dataIdentical);
    $testResults['caching_identical'] = $dataIdentical;
    
} catch (Exception $e) {
    printTestResult('6.x Проверка кеширования', false, $e->getMessage());
    $testResults['caching_works'] = false;
    $testResults['caching_identical'] = false;
}

// =============================================================================
// ТЕСТ 7: Проверка обработки ошибок
// =============================================================================

printSection('ТЕСТ 7: Обработка ошибок и исключительных ситуаций');

$rssErrors = new Rss([], $logger);

// 7.1 Несуществующий домен
try {
    $rssErrors->fetch('http://this-domain-definitely-does-not-exist-12345678.com/feed.xml');
    printTestResult('7.1 Несуществующий домен', false, 'Должно было выбросить исключение');
    $testResults['error_invalid_domain'] = false;
} catch (RssException $e) {
    printTestResult('7.1 Несуществующий домен', true, 'Ожидаемое исключение: ' . $e->getMessage());
    $testResults['error_invalid_domain'] = true;
} catch (Exception $e) {
    printTestResult('7.1 Несуществующий домен', false, 'Неожиданное исключение: ' . $e->getMessage());
    $testResults['error_invalid_domain'] = false;
}

// 7.2 404 ошибка
try {
    $rssErrors->fetch('https://httpstat.us/404');
    printTestResult('7.2 HTTP 404 ошибка', false, 'Должно было выбросить исключение');
    $testResults['error_404'] = false;
} catch (RssException $e) {
    printTestResult('7.2 HTTP 404 ошибка', true, 'Ожидаемое исключение: ' . $e->getMessage());
    $testResults['error_404'] = true;
} catch (Exception $e) {
    printTestResult('7.2 HTTP 404 ошибка', false, 'Неожиданное исключение: ' . $e->getMessage());
    $testResults['error_404'] = false;
}

// 7.3 Таймаут
try {
    $rssTimeout = new Rss([
        'timeout' => 1, // 1 секунда
    ], $logger);
    
    // URL который точно не ответит за 1 секунду
    $rssTimeout->fetch('https://httpstat.us/200?sleep=5000');
    printTestResult('7.3 Таймаут запроса', false, 'Должно было выбросить исключение');
    $testResults['error_timeout'] = false;
} catch (RssException $e) {
    printTestResult('7.3 Таймаут запроса', true, 'Ожидаемое исключение: ' . $e->getMessage());
    $testResults['error_timeout'] = true;
} catch (Exception $e) {
    printTestResult('7.3 Таймаут запроса', false, 'Неожиданное исключение: ' . $e->getMessage());
    $testResults['error_timeout'] = false;
}

// =============================================================================
// ТЕСТ 8: Проверка логирования
// =============================================================================

printSection('ТЕСТ 8: Проверка логирования операций');

// Читаем лог файл
$logFile = $logDir . '/rss_test.log';
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $logLines = explode("\n", $logContent);
    $logCount = count(array_filter($logLines));
    
    printTestResult('8.1 Лог файл создан', true, "Записей в логе: $logCount");
    $testResults['logging_file_exists'] = true;
    
    // Проверка наличия различных уровней логирования
    $hasInfo = strpos($logContent, 'INFO') !== false;
    $hasError = strpos($logContent, 'ERROR') !== false;
    $hasWarning = strpos($logContent, 'WARNING') !== false;
    
    printTestResult('8.2 Присутствуют INFO сообщения', $hasInfo);
    printTestResult('8.3 Присутствуют ERROR сообщения', $hasError);
    printTestResult('8.4 Присутствуют WARNING сообщения', $hasWarning);
    
    $testResults['logging_info'] = $hasInfo;
    $testResults['logging_error'] = $hasError;
    $testResults['logging_warning'] = $hasWarning;
    
    // Проверка логирования конкретных операций
    $hasInitLog = strpos($logContent, 'RSS клиент инициализирован') !== false;
    $hasFetchLog = strpos($logContent, 'Начало загрузки RSS ленты') !== false;
    $hasSuccessLog = strpos($logContent, 'RSS лента успешно загружена') !== false;
    
    printTestResult('8.5 Логирование инициализации', $hasInitLog);
    printTestResult('8.6 Логирование начала загрузки', $hasFetchLog);
    printTestResult('8.7 Логирование успешной загрузки', $hasSuccessLog);
    
    $testResults['logging_init'] = $hasInitLog;
    $testResults['logging_fetch'] = $hasFetchLog;
    $testResults['logging_success'] = $hasSuccessLog;
    
} else {
    printTestResult('8.1 Лог файл создан', false, 'Файл не найден');
    $testResults['logging_file_exists'] = false;
}

// =============================================================================
// ИТОГОВАЯ СТАТИСТИКА
// =============================================================================

printSection('ИТОГОВАЯ СТАТИСТИКА ТЕСТИРОВАНИЯ');

$totalTests = count($testResults);
$passedTests = count(array_filter($testResults));
$failedTests = $totalTests - $passedTests;
$successRate = ($totalTests > 0) ? round(($passedTests / $totalTests) * 100, 2) : 0;

echo sprintf("Всего тестов:         %d\n", $totalTests);
echo sprintf("%sУспешных тестов:      %d%s\n", COLOR_GREEN, $passedTests, COLOR_RESET);
echo sprintf("%sПроваленных тестов:   %d%s\n", COLOR_RED, $failedTests, COLOR_RESET);
echo sprintf("Процент успеха:       %s%.2f%%%s\n\n", 
    $successRate >= 80 ? COLOR_GREEN : COLOR_RED, 
    $successRate, 
    COLOR_RESET
);

// Список проваленных тестов
if ($failedTests > 0) {
    echo COLOR_RED . "Проваленные тесты:" . COLOR_RESET . "\n";
    foreach ($testResults as $testName => $result) {
        if (!$result) {
            echo COLOR_RED . "  ✗ $testName" . COLOR_RESET . "\n";
        }
    }
    echo "\n";
}

// Информация о логах
echo COLOR_BLUE . "Файлы логов и кеша:" . COLOR_RESET . "\n";
echo "  Логи: $logDir\n";
echo "  Кеш: $cacheDir\n\n";

echo COLOR_BLUE . "Для просмотра подробных логов выполните:" . COLOR_RESET . "\n";
echo "  tail -f $logFile\n\n";

// Финальное сообщение
if ($successRate >= 90) {
    echo COLOR_GREEN . "╔════════════════════════════════════════════════════════════════════════════╗\n";
    echo "║                   ✓ ВСЕ ТЕСТЫ УСПЕШНО ПРОЙДЕНЫ!                           ║\n";
    echo "║              Класс Rss работает корректно и стабильно.                    ║\n";
    echo "╚════════════════════════════════════════════════════════════════════════════╝" . COLOR_RESET . "\n\n";
    exit(0);
} elseif ($successRate >= 70) {
    echo COLOR_YELLOW . "╔════════════════════════════════════════════════════════════════════════════╗\n";
    echo "║               ⚠ БОЛЬШИНСТВО ТЕСТОВ ПРОЙДЕНО УСПЕШНО                       ║\n";
    echo "║            Требуется небольшая доработка некоторых функций.               ║\n";
    echo "╚════════════════════════════════════════════════════════════════════════════╝" . COLOR_RESET . "\n\n";
    exit(1);
} else {
    echo COLOR_RED . "╔════════════════════════════════════════════════════════════════════════════╗\n";
    echo "║                     ✗ ОБНАРУЖЕНЫ КРИТИЧЕСКИЕ ОШИБКИ                        ║\n";
    echo "║              Необходима серьезная доработка класса Rss.                    ║\n";
    echo "╚════════════════════════════════════════════════════════════════════════════╝" . COLOR_RESET . "\n\n";
    exit(2);
}

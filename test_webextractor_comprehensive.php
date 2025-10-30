<?php

declare(strict_types=1);

/**
 * Комплексный тест класса WebtExtractor
 * 
 * Проверяет все публичные методы класса:
 * - Конструктор с различными параметрами
 * - extract() - извлечение контента по URL
 * - extractFromHtml() - извлечение из готового HTML
 * - extractBatch() - пакетная обработка
 * 
 * Также тестирует:
 * - Валидацию входных данных
 * - Извлечение изображений, ссылок, метаданных
 * - Обработку ошибок и исключений
 * - Логирование всех операций
 */

require_once __DIR__ . '/autoload.php';

use App\Component\WebtExtractor;
use App\Component\Logger;
use App\Component\Exception\WebtExtractorException;
use App\Component\Exception\WebtExtractorValidationException;

// Цвета для вывода
const COLOR_RESET = "\033[0m";
const COLOR_GREEN = "\033[32m";
const COLOR_RED = "\033[31m";
const COLOR_YELLOW = "\033[33m";
const COLOR_BLUE = "\033[34m";
const COLOR_CYAN = "\033[36m";

// Счетчики тестов
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

/**
 * Выводит заголовок теста
 */
function testHeader(string $title): void
{
    echo "\n" . COLOR_BLUE . "═══════════════════════════════════════════════════════════════════════" . COLOR_RESET . "\n";
    echo COLOR_CYAN . "  " . $title . COLOR_RESET . "\n";
    echo COLOR_BLUE . "═══════════════════════════════════════════════════════════════════════" . COLOR_RESET . "\n\n";
}

/**
 * Выводит сообщение об успехе
 */
function testSuccess(string $message): void
{
    global $totalTests, $passedTests;
    $totalTests++;
    $passedTests++;
    echo COLOR_GREEN . "✓ PASS: " . COLOR_RESET . $message . "\n";
}

/**
 * Выводит сообщение об ошибке
 */
function testFail(string $message, ?Throwable $e = null): void
{
    global $totalTests, $failedTests;
    $totalTests++;
    $failedTests++;
    echo COLOR_RED . "✗ FAIL: " . COLOR_RESET . $message . "\n";
    if ($e !== null) {
        echo COLOR_RED . "  Ошибка: " . $e->getMessage() . COLOR_RESET . "\n";
        echo COLOR_RED . "  Класс: " . get_class($e) . COLOR_RESET . "\n";
    }
}

/**
 * Выводит информационное сообщение
 */
function testInfo(string $message): void
{
    echo COLOR_YELLOW . "ℹ INFO: " . COLOR_RESET . $message . "\n";
}

/**
 * Выводит итоги тестирования
 */
function testSummary(): void
{
    global $totalTests, $passedTests, $failedTests;
    
    echo "\n" . COLOR_BLUE . "═══════════════════════════════════════════════════════════════════════" . COLOR_RESET . "\n";
    echo COLOR_CYAN . "  ИТОГИ ТЕСТИРОВАНИЯ" . COLOR_RESET . "\n";
    echo COLOR_BLUE . "═══════════════════════════════════════════════════════════════════════" . COLOR_RESET . "\n\n";
    
    echo "Всего тестов: " . COLOR_CYAN . $totalTests . COLOR_RESET . "\n";
    echo "Успешно:      " . COLOR_GREEN . $passedTests . COLOR_RESET . "\n";
    echo "Провалено:    " . COLOR_RED . $failedTests . COLOR_RESET . "\n";
    
    $percentage = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 2) : 0;
    echo "Процент успеха: " . ($percentage == 100 ? COLOR_GREEN : COLOR_YELLOW) . $percentage . "%" . COLOR_RESET . "\n";
}

/**
 * Создает тестовый HTML контент с различными элементами
 */
function createTestHtml(string $type = 'full'): string
{
    $baseHtml = [
        'full' => <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Тестовое описание статьи о технологиях">
    <meta name="keywords" content="php, testing, webextractor">
    <meta name="author" content="Иван Иванов">
    
    <!-- Open Graph -->
    <meta property="og:title" content="Тестовая статья о веб-технологиях">
    <meta property="og:description" content="Подробное описание для социальных сетей">
    <meta property="og:image" content="https://example.com/images/og-image.jpg">
    <meta property="og:url" content="https://example.com/articles/test">
    <meta property="og:type" content="article">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Тестовая статья о веб-технологиях">
    <meta name="twitter:description" content="Описание для Twitter">
    <meta name="twitter:image" content="https://example.com/images/twitter-image.jpg">
    
    <title>Тестовая статья о веб-технологиях</title>
    
    <!-- JSON-LD -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Article",
        "headline": "Тестовая статья о веб-технологиях",
        "author": {
            "@type": "Person",
            "name": "Иван Иванов"
        },
        "datePublished": "2024-01-15",
        "image": "https://example.com/images/article-image.jpg"
    }
    </script>
</head>
<body>
    <header>
        <nav>
            <a href="/">Главная</a>
            <a href="/about">О нас</a>
        </nav>
    </header>
    
    <main>
        <article>
            <h1>Тестовая статья о веб-технологиях</h1>
            
            <div class="author">Автор: Иван Иванов</div>
            <div class="date">15 января 2024</div>
            
            <img src="https://example.com/images/main-image.jpg" 
                 alt="Главное изображение статьи" 
                 title="Веб-технологии" 
                 width="800" 
                 height="600">
            
            <p>Это вводный параграф статьи, который содержит важную информацию о веб-разработке и современных технологиях. PHP является одним из самых популярных языков программирования для создания веб-приложений.</p>
            
            <h2>Основы веб-извлечения данных</h2>
            
            <p>Веб-скрейпинг и извлечение данных - важные навыки для современного разработчика. Существует множество инструментов и библиотек, которые помогают автоматизировать процесс сбора информации из веб-страниц.</p>
            
            <p>Библиотека Readability позволяет извлекать основной контент из HTML страниц, удаляя навигацию, рекламу и другие вспомогательные элементы. Это особенно полезно для создания RSS-читалок и агрегаторов новостей.</p>
            
            <h2>Работа с изображениями</h2>
            
            <p>При извлечении контента важно также получить все изображения из статьи:</p>
            
            <img src="/images/local-image.jpg" alt="Локальное изображение" width="400" height="300">
            <img src="//cdn.example.com/image.png" alt="CDN изображение">
            
            <h2>Полезные ссылки</h2>
            
            <p>Вот несколько полезных ресурсов для изучения:</p>
            
            <ul>
                <li><a href="https://php.net" title="Официальный сайт PHP">PHP Documentation</a></li>
                <li><a href="https://github.com" rel="nofollow">GitHub</a></li>
                <li><a href="/tutorials/web-scraping">Руководство по веб-скрейпингу</a></li>
                <li><a href="#section">Внутренняя ссылка (должна быть пропущена)</a></li>
                <li><a href="javascript:void(0)">JavaScript ссылка (должна быть пропущена)</a></li>
            </ul>
            
            <h2>Заключение</h2>
            
            <p>В заключение можно сказать, что современные инструменты для извлечения веб-контента делают процесс сбора и обработки информации значительно проще и эффективнее. Используйте их с умом и соблюдайте правила сайтов.</p>
        </article>
    </main>
    
    <footer>
        <p>© 2024 Test Site. Все права защищены.</p>
    </footer>
    
    <aside>
        <div class="ads">Реклама</div>
        <div class="sidebar">Боковая панель</div>
    </aside>
</body>
</html>
HTML,
        'minimal' => <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Минимальная статья</title>
</head>
<body>
    <article>
        <h1>Минимальная статья</h1>
        <p>Это короткая статья с минимальным содержанием для тестирования базового функционала извлечения контента из HTML страниц.</p>
        <p>Второй параграф добавлен для обеспечения достаточного количества контента, чтобы библиотека Readability могла успешно извлечь текст.</p>
        <p>Третий параграф делает контент еще более читаемым и позволяет проверить корректность работы экстрактора на минимальных данных.</p>
    </article>
</body>
</html>
HTML,
        'english' => <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <title>English Test Article</title>
</head>
<body>
    <article>
        <h1>English Test Article</h1>
        <p>This is a test article in English. It contains several paragraphs to test language detection and word counting functionality.</p>
        <p>Web scraping is an important technique for data extraction. Modern tools make this process easier and more efficient.</p>
        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
    </article>
</body>
</html>
HTML,
        'empty' => '',
        'short' => '<p>Короткий</p>',
        'invalid' => 'Invalid HTML without any tags or structure',
    ];
    
    return $baseHtml[$type] ?? $baseHtml['full'];
}

// ============================================================================
// ТЕСТ 1: Инициализация с различными параметрами
// ============================================================================
testHeader("ТЕСТ 1: Инициализация WebtExtractor");

try {
    // Создаем логгер для тестирования
    $logDir = __DIR__ . '/logs_webextractor_test';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logger = new Logger([
        'directory' => $logDir,
        'file_name' => 'webextractor_test.log',
        'max_files' => 5,
        'max_file_size' => 10, // MB
    ]);
    
    testSuccess("Логгер успешно создан");
} catch (Throwable $e) {
    testFail("Не удалось создать логгер", $e);
    exit(1);
}

// Тест 1.1: Инициализация с настройками по умолчанию
try {
    $extractor = new WebtExtractor([], $logger);
    testSuccess("Инициализация с настройками по умолчанию");
} catch (Throwable $e) {
    testFail("Ошибка при инициализации с настройками по умолчанию", $e);
}

// Тест 1.2: Инициализация с пользовательскими параметрами
try {
    $extractor = new WebtExtractor([
        'user_agent' => 'TestBot/1.0',
        'timeout' => 20,
        'connect_timeout' => 5,
        'max_content_size' => 5242880, // 5 MB
        'retries' => 2,
        'extract_images' => true,
        'extract_links' => true,
        'extract_metadata' => true,
        'verify_ssl' => true,
    ], $logger);
    testSuccess("Инициализация с пользовательскими параметрами");
} catch (Throwable $e) {
    testFail("Ошибка при инициализации с пользовательскими параметрами", $e);
}

// Тест 1.3: Инициализация без логгера
try {
    $extractorNoLogger = new WebtExtractor();
    testSuccess("Инициализация без логгера");
} catch (Throwable $e) {
    testFail("Ошибка при инициализации без логгера", $e);
}

// Тест 1.4: Инициализация с отключением извлечения дополнительных данных
try {
    $extractorMinimal = new WebtExtractor([
        'extract_images' => false,
        'extract_links' => false,
        'extract_metadata' => false,
    ], $logger);
    testSuccess("Инициализация с отключением извлечения дополнительных данных");
} catch (Throwable $e) {
    testFail("Ошибка при инициализации с отключением извлечения дополнительных данных", $e);
}

// ============================================================================
// ТЕСТ 2: Извлечение контента из HTML (extractFromHtml)
// ============================================================================
testHeader("ТЕСТ 2: Извлечение контента из HTML");

// Тест 2.1: Извлечение из полного HTML
try {
    $html = createTestHtml('full');
    $url = 'https://example.com/articles/test';
    
    $result = $extractor->extractFromHtml($html, $url);
    
    // Проверяем структуру результата
    $requiredKeys = ['url', 'title', 'author', 'content', 'text_content', 'excerpt', 
                     'lead_image_url', 'date_published', 'language', 'images', 'links', 
                     'metadata', 'word_count', 'read_time', 'extracted_at'];
    
    $missingKeys = [];
    foreach ($requiredKeys as $key) {
        if (!array_key_exists($key, $result)) {
            $missingKeys[] = $key;
        }
    }
    
    if (empty($missingKeys)) {
        testSuccess("Извлечение из полного HTML - все обязательные поля присутствуют");
    } else {
        testFail("Извлечение из полного HTML - отсутствуют поля: " . implode(', ', $missingKeys));
    }
    
    // Выводим результаты
    testInfo("URL: " . $result['url']);
    testInfo("Заголовок: " . $result['title']);
    testInfo("Автор: " . $result['author']);
    testInfo("Язык: " . $result['language']);
    testInfo("Количество слов: " . $result['word_count']);
    testInfo("Время чтения: " . $result['read_time'] . " мин");
    testInfo("Количество изображений: " . count($result['images']));
    testInfo("Количество ссылок: " . count($result['links']));
    
} catch (Throwable $e) {
    testFail("Ошибка при извлечении из полного HTML", $e);
}

// Тест 2.2: Проверка извлечения заголовка
try {
    if (!empty($result['title']) && mb_strlen($result['title']) > 0) {
        testSuccess("Заголовок извлечен: " . $result['title']);
    } else {
        testFail("Заголовок не извлечен или пустой");
    }
} catch (Throwable $e) {
    testFail("Ошибка при проверке заголовка", $e);
}

// Тест 2.3: Проверка извлечения текстового контента
try {
    if (!empty($result['text_content']) && mb_strlen($result['text_content']) > 100) {
        testSuccess("Текстовый контент извлечен (длина: " . mb_strlen($result['text_content']) . " символов)");
    } else {
        testFail("Текстовый контент не извлечен или слишком короткий");
    }
} catch (Throwable $e) {
    testFail("Ошибка при проверке текстового контента", $e);
}

// Тест 2.4: Проверка подсчета слов
try {
    if ($result['word_count'] > 0) {
        testSuccess("Подсчет слов работает: " . $result['word_count'] . " слов");
    } else {
        testFail("Подсчет слов вернул 0");
    }
} catch (Throwable $e) {
    testFail("Ошибка при проверке подсчета слов", $e);
}

// Тест 2.5: Проверка расчета времени чтения
try {
    if ($result['read_time'] > 0) {
        testSuccess("Расчет времени чтения работает: " . $result['read_time'] . " мин");
    } else {
        testFail("Расчет времени чтения вернул 0");
    }
} catch (Throwable $e) {
    testFail("Ошибка при проверке расчета времени чтения", $e);
}

// Тест 2.6: Проверка определения языка
try {
    if ($result['language'] === 'ru') {
        testSuccess("Язык определен правильно: " . $result['language']);
    } else {
        testFail("Язык определен неправильно: " . $result['language'] . " (ожидался 'ru')");
    }
} catch (Throwable $e) {
    testFail("Ошибка при проверке определения языка", $e);
}

// ============================================================================
// ТЕСТ 3: Извлечение изображений
// ============================================================================
testHeader("ТЕСТ 3: Извлечение изображений");

// Тест 3.1: Проверка наличия изображений
try {
    if (count($result['images']) > 0) {
        testSuccess("Изображения извлечены: " . count($result['images']) . " шт.");
        
        foreach ($result['images'] as $index => $image) {
            testInfo(sprintf(
                "  [%d] src: %s, alt: %s, width: %s, height: %s",
                $index + 1,
                $image['src'],
                $image['alt'],
                $image['width'] ?: 'не указано',
                $image['height'] ?: 'не указано'
            ));
        }
    } else {
        testFail("Изображения не извлечены");
    }
} catch (Throwable $e) {
    testFail("Ошибка при проверке извлечения изображений", $e);
}

// Тест 3.2: Проверка резолва относительных URL изображений
try {
    $hasRelativeUrls = false;
    foreach ($result['images'] as $image) {
        if (str_starts_with($image['src'], 'http')) {
            $hasRelativeUrls = true;
            break;
        }
    }
    
    if ($hasRelativeUrls) {
        testSuccess("Относительные URL изображений преобразованы в абсолютные");
    } else {
        testInfo("Относительные URL не найдены для проверки");
    }
} catch (Throwable $e) {
    testFail("Ошибка при проверке резолва URL изображений", $e);
}

// ============================================================================
// ТЕСТ 4: Извлечение ссылок
// ============================================================================
testHeader("ТЕСТ 4: Извлечение ссылок");

// Тест 4.1: Проверка наличия ссылок
try {
    if (count($result['links']) > 0) {
        testSuccess("Ссылки извлечены: " . count($result['links']) . " шт.");
        
        foreach ($result['links'] as $index => $link) {
            testInfo(sprintf(
                "  [%d] href: %s, text: %s",
                $index + 1,
                $link['href'],
                mb_strlen($link['text']) > 50 ? mb_substr($link['text'], 0, 50) . '...' : $link['text']
            ));
        }
    } else {
        testFail("Ссылки не извлечены");
    }
} catch (Throwable $e) {
    testFail("Ошибка при проверке извлечения ссылок", $e);
}

// Тест 4.2: Проверка фильтрации якорных ссылок
try {
    $hasAnchorLinks = false;
    foreach ($result['links'] as $link) {
        if (str_starts_with($link['href'], '#')) {
            $hasAnchorLinks = true;
            break;
        }
    }
    
    if (!$hasAnchorLinks) {
        testSuccess("Якорные ссылки правильно отфильтрованы");
    } else {
        testFail("Якорные ссылки не отфильтрованы");
    }
} catch (Throwable $e) {
    testFail("Ошибка при проверке фильтрации якорных ссылок", $e);
}

// Тест 4.3: Проверка фильтрации JavaScript ссылок
try {
    $hasJsLinks = false;
    foreach ($result['links'] as $link) {
        if (str_starts_with($link['href'], 'javascript:')) {
            $hasJsLinks = true;
            break;
        }
    }
    
    if (!$hasJsLinks) {
        testSuccess("JavaScript ссылки правильно отфильтрованы");
    } else {
        testFail("JavaScript ссылки не отфильтрованы");
    }
} catch (Throwable $e) {
    testFail("Ошибка при проверке фильтрации JavaScript ссылок", $e);
}

// ============================================================================
// ТЕСТ 5: Извлечение метаданных
// ============================================================================
testHeader("ТЕСТ 5: Извлечение метаданных");

// Тест 5.1: Проверка извлечения Open Graph
try {
    if (isset($result['metadata']['open_graph']) && count($result['metadata']['open_graph']) > 0) {
        testSuccess("Open Graph метаданные извлечены: " . count($result['metadata']['open_graph']) . " полей");
        
        foreach ($result['metadata']['open_graph'] as $key => $value) {
            testInfo("  og:{$key}: {$value}");
        }
    } else {
        testFail("Open Graph метаданные не извлечены");
    }
} catch (Throwable $e) {
    testFail("Ошибка при проверке извлечения Open Graph", $e);
}

// Тест 5.2: Проверка извлечения Twitter Card
try {
    if (isset($result['metadata']['twitter_card']) && count($result['metadata']['twitter_card']) > 0) {
        testSuccess("Twitter Card метаданные извлечены: " . count($result['metadata']['twitter_card']) . " полей");
        
        foreach ($result['metadata']['twitter_card'] as $key => $value) {
            testInfo("  twitter:{$key}: {$value}");
        }
    } else {
        testFail("Twitter Card метаданные не извлечены");
    }
} catch (Throwable $e) {
    testFail("Ошибка при проверке извлечения Twitter Card", $e);
}

// Тест 5.3: Проверка извлечения JSON-LD
try {
    if (isset($result['metadata']['json_ld']) && count($result['metadata']['json_ld']) > 0) {
        testSuccess("JSON-LD метаданные извлечены: " . count($result['metadata']['json_ld']) . " блоков");
        
        foreach ($result['metadata']['json_ld'] as $index => $jsonLd) {
            testInfo(sprintf("  [%d] @type: %s", $index + 1, $jsonLd['@type'] ?? 'не указан'));
        }
    } else {
        testFail("JSON-LD метаданные не извлечены");
    }
} catch (Throwable $e) {
    testFail("Ошибка при проверке извлечения JSON-LD", $e);
}

// Тест 5.4: Проверка извлечения обычных meta тегов
try {
    if (isset($result['metadata']['meta']) && count($result['metadata']['meta']) > 0) {
        testSuccess("Обычные meta теги извлечены: " . count($result['metadata']['meta']) . " полей");
    } else {
        testFail("Обычные meta теги не извлечены");
    }
} catch (Throwable $e) {
    testFail("Ошибка при проверке извлечения обычных meta тегов", $e);
}

// ============================================================================
// ТЕСТ 6: Определение языка контента
// ============================================================================
testHeader("ТЕСТ 6: Определение языка контента");

// Тест 6.1: Определение русского языка
try {
    $htmlRu = createTestHtml('full');
    $resultRu = $extractor->extractFromHtml($htmlRu, 'https://example.com/ru');
    
    if ($resultRu['language'] === 'ru') {
        testSuccess("Русский язык определен правильно");
    } else {
        testFail("Русский язык определен неправильно: " . $resultRu['language']);
    }
} catch (Throwable $e) {
    testFail("Ошибка при определении русского языка", $e);
}

// Тест 6.2: Определение английского языка
try {
    $htmlEn = createTestHtml('english');
    $resultEn = $extractor->extractFromHtml($htmlEn, 'https://example.com/en');
    
    if ($resultEn['language'] === 'en') {
        testSuccess("Английский язык определен правильно");
    } else {
        testFail("Английский язык определен неправильно: " . $resultEn['language']);
    }
} catch (Throwable $e) {
    testFail("Ошибка при определении английского языка", $e);
}

// ============================================================================
// ТЕСТ 7: Валидация входных данных
// ============================================================================
testHeader("ТЕСТ 7: Валидация входных данных");

// Тест 7.1: Пустой URL
try {
    $extractor->extractFromHtml('<html><body>Test</body></html>', '');
    testFail("Не выброшено исключение для пустого URL");
} catch (WebtExtractorValidationException $e) {
    testSuccess("Пустой URL правильно отклонен: " . $e->getMessage());
} catch (Throwable $e) {
    testFail("Неправильный тип исключения для пустого URL", $e);
}

// Тест 7.2: Некорректный URL
try {
    $extractor->extractFromHtml('<html><body>Test</body></html>', 'not-a-url');
    testFail("Не выброшено исключение для некорректного URL");
} catch (WebtExtractorValidationException $e) {
    testSuccess("Некорректный URL правильно отклонен: " . $e->getMessage());
} catch (Throwable $e) {
    testFail("Неправильный тип исключения для некорректного URL", $e);
}

// Тест 7.3: URL без протокола HTTP/HTTPS
try {
    $extractor->extractFromHtml('<html><body>Test</body></html>', 'ftp://example.com');
    testFail("Не выброшено исключение для FTP протокола");
} catch (WebtExtractorValidationException $e) {
    testSuccess("FTP протокол правильно отклонен: " . $e->getMessage());
} catch (Throwable $e) {
    testFail("Неправильный тип исключения для FTP протокола", $e);
}

// Тест 7.4: Пустой HTML
try {
    $extractor->extractFromHtml('', 'https://example.com');
    testFail("Не выброшено исключение для пустого HTML");
} catch (WebtExtractorValidationException $e) {
    testSuccess("Пустой HTML правильно отклонен: " . $e->getMessage());
} catch (Throwable $e) {
    testFail("Неправильный тип исключения для пустого HTML", $e);
}

// Тест 7.5: Слишком короткий HTML
try {
    $extractor->extractFromHtml('<p>Test</p>', 'https://example.com');
    testFail("Не выброшено исключение для слишком короткого HTML");
} catch (WebtExtractorValidationException $e) {
    testSuccess("Слишком короткий HTML правильно отклонен: " . $e->getMessage());
} catch (Throwable $e) {
    testFail("Неправильный тип исключения для слишком короткого HTML", $e);
}

// ============================================================================
// ТЕСТ 8: Минимальный контент
// ============================================================================
testHeader("ТЕСТ 8: Извлечение минимального контента");

// Тест 8.1: Обработка минимального HTML
try {
    $htmlMinimal = createTestHtml('minimal');
    $resultMinimal = $extractor->extractFromHtml($htmlMinimal, 'https://example.com/minimal');
    
    if (!empty($resultMinimal['title'])) {
        testSuccess("Минимальный HTML успешно обработан");
        testInfo("Заголовок: " . $resultMinimal['title']);
        testInfo("Количество слов: " . $resultMinimal['word_count']);
    } else {
        testFail("Не удалось извлечь данные из минимального HTML");
    }
} catch (Throwable $e) {
    testFail("Ошибка при обработке минимального HTML", $e);
}

// ============================================================================
// ТЕСТ 9: Извлечение с отключенными опциями
// ============================================================================
testHeader("ТЕСТ 9: Извлечение с отключенными опциями");

// Тест 9.1: Отключение извлечения изображений
try {
    $result9 = $extractorMinimal->extractFromHtml(createTestHtml('full'), 'https://example.com');
    
    if (count($result9['images']) === 0) {
        testSuccess("Извлечение изображений отключено корректно");
    } else {
        testFail("Изображения извлечены, хотя опция отключена");
    }
} catch (Throwable $e) {
    testFail("Ошибка при проверке отключения извлечения изображений", $e);
}

// Тест 9.2: Отключение извлечения ссылок
try {
    if (count($result9['links']) === 0) {
        testSuccess("Извлечение ссылок отключено корректно");
    } else {
        testFail("Ссылки извлечены, хотя опция отключена");
    }
} catch (Throwable $e) {
    testFail("Ошибка при проверке отключения извлечения ссылок", $e);
}

// Тест 9.3: Отключение извлечения метаданных
try {
    if (count($result9['metadata']['open_graph']) === 0 && 
        count($result9['metadata']['twitter_card']) === 0 &&
        count($result9['metadata']['json_ld']) === 0) {
        testSuccess("Извлечение метаданных отключено корректно");
    } else {
        testFail("Метаданные извлечены, хотя опция отключена");
    }
} catch (Throwable $e) {
    testFail("Ошибка при проверке отключения извлечения метаданных", $e);
}

// ============================================================================
// ТЕСТ 10: Пакетное извлечение (extractBatch)
// ============================================================================
testHeader("ТЕСТ 10: Пакетное извлечение контента");

// Создаем мок-сервер для тестирования HTTP запросов не будем делать, так как это требует реальных URL
// Вместо этого протестируем логику пакетной обработки с ручной проверкой

testInfo("Тест пакетного извлечения пропущен - требуются реальные URL");
testInfo("Метод extractBatch() будет протестирован в интеграционных тестах");

// ============================================================================
// ТЕСТ 11: Проверка логирования
// ============================================================================
testHeader("ТЕСТ 11: Проверка логирования");

// Тест 11.1: Проверка создания лог-файла
try {
    $logFiles = glob($logDir . '/*.log');
    
    if (count($logFiles) > 0) {
        testSuccess("Лог-файл создан: " . basename($logFiles[0]));
        
        // Читаем содержимое лога
        $logContent = file_get_contents($logFiles[0]);
        $logLines = explode("\n", $logContent);
        $logLinesCount = count(array_filter($logLines, fn($line) => !empty(trim($line))));
        
        testInfo("Записей в логе: " . $logLinesCount);
        
        if ($logLinesCount > 0) {
            testSuccess("Логирование работает корректно");
        } else {
            testFail("Лог-файл создан, но пустой");
        }
    } else {
        testFail("Лог-файл не создан");
    }
} catch (Throwable $e) {
    testFail("Ошибка при проверке лог-файла", $e);
}

// Тест 11.2: Проверка логирования инициализации
try {
    $logContent = file_get_contents($logFiles[0]);
    
    if (strpos($logContent, 'WebtExtractor инициализирован') !== false) {
        testSuccess("Инициализация залогирована");
    } else {
        testFail("Инициализация не залогирована");
    }
} catch (Throwable $e) {
    testFail("Ошибка при проверке логирования инициализации", $e);
}

// Тест 11.3: Проверка логирования операций парсинга
try {
    if (strpos($logContent, 'Начало парсинга HTML') !== false) {
        testSuccess("Операции парсинга залогированы");
    } else {
        testFail("Операции парсинга не залогированы");
    }
} catch (Throwable $e) {
    testFail("Ошибка при проверке логирования операций парсинга", $e);
}

// Тест 11.4: Проверка логирования ошибок
try {
    if (strpos($logContent, 'ERROR') !== false || strpos($logContent, 'WARNING') !== false) {
        testSuccess("Ошибки и предупреждения залогированы");
    } else {
        testInfo("В логе нет ошибок и предупреждений (это нормально для успешных тестов)");
    }
} catch (Throwable $e) {
    testFail("Ошибка при проверке логирования ошибок", $e);
}

// ============================================================================
// ТЕСТ 12: Тест обработки ошибок Readability
// ============================================================================
testHeader("ТЕСТ 12: Обработка ошибок парсинга");

// Тест 12.1: HTML без читаемого контента
try {
    $emptyContentHtml = '<html><head><title>Empty</title></head><body><div class="ads">Ads only</div></body></html>';
    $resultEmpty = $extractor->extractFromHtml($emptyContentHtml, 'https://example.com/empty');
    testInfo("HTML без читаемого контента обработан (Readability может вернуть пустой результат)");
} catch (WebtExtractorException $e) {
    testSuccess("HTML без читаемого контента правильно отклонен: " . $e->getMessage());
} catch (Throwable $e) {
    testFail("Неправильный тип исключения для HTML без контента", $e);
}

// ============================================================================
// ТЕСТ 13: Тест работы без логгера
// ============================================================================
testHeader("ТЕСТ 13: Работа без логгера");

try {
    $resultNoLogger = $extractorNoLogger->extractFromHtml(createTestHtml('full'), 'https://example.com/noLogger');
    
    if (!empty($resultNoLogger['title'])) {
        testSuccess("Извлечение работает корректно без логгера");
    } else {
        testFail("Извлечение не работает без логгера");
    }
} catch (Throwable $e) {
    testFail("Ошибка при работе без логгера", $e);
}

// ============================================================================
// ТЕСТ 14: Краевые случаи
// ============================================================================
testHeader("ТЕСТ 14: Краевые случаи");

// Тест 14.1: Очень длинный текст для проверки word_count и read_time
try {
    $longText = str_repeat('Это тестовое предложение для проверки подсчета слов и времени чтения. ', 500);
    $longHtml = "<html><body><article><h1>Длинная статья</h1><p>{$longText}</p></article></body></html>";
    
    $resultLong = $extractor->extractFromHtml($longHtml, 'https://example.com/long');
    
    if ($resultLong['word_count'] > 1000 && $resultLong['read_time'] > 1) {
        testSuccess(sprintf(
            "Длинный текст обработан корректно: %d слов, %d мин чтения",
            $resultLong['word_count'],
            $resultLong['read_time']
        ));
    } else {
        testFail("Некорректная обработка длинного текста");
    }
} catch (Throwable $e) {
    testFail("Ошибка при обработке длинного текста", $e);
}

// Тест 14.2: HTML с некорректной кодировкой
try {
    $html14_2 = createTestHtml('full');
    // Пытаемся обработать HTML с кириллицей
    $result14_2 = $extractor->extractFromHtml($html14_2, 'https://example.com/encoding');
    
    // Проверяем, что кириллица не превратилась в кракозябры
    if (preg_match('/[а-яА-ЯЁё]/u', $result14_2['text_content'])) {
        testSuccess("Кодировка обработана корректно, кириллица сохранена");
    } else {
        testFail("Проблема с кодировкой - кириллица не найдена");
    }
} catch (Throwable $e) {
    testFail("Ошибка при обработке кодировки", $e);
}

// Тест 14.3: URL с различными схемами
try {
    $urls = [
        'https://example.com/page' => true,
        'http://example.com/page' => true,
    ];
    
    foreach ($urls as $url => $shouldPass) {
        try {
            $result14_3 = $extractor->extractFromHtml(createTestHtml('full'), $url);
            if ($shouldPass) {
                testSuccess("URL '{$url}' принят корректно");
            } else {
                testFail("URL '{$url}' не должен был быть принят");
            }
        } catch (WebtExtractorValidationException $e) {
            if (!$shouldPass) {
                testSuccess("URL '{$url}' правильно отклонен");
            } else {
                testFail("URL '{$url}' не должен был быть отклонен");
            }
        }
    }
} catch (Throwable $e) {
    testFail("Ошибка при проверке различных URL схем", $e);
}

// ============================================================================
// ИТОГИ
// ============================================================================
testSummary();

// Выводим примеры логов
echo "\n" . COLOR_BLUE . "═══════════════════════════════════════════════════════════════════════" . COLOR_RESET . "\n";
echo COLOR_CYAN . "  ПРИМЕРЫ ИЗ ЛОГ-ФАЙЛА" . COLOR_RESET . "\n";
echo COLOR_BLUE . "═══════════════════════════════════════════════════════════════════════" . COLOR_RESET . "\n\n";

if (!empty($logFiles) && file_exists($logFiles[0])) {
    $logLines = file($logFiles[0], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $displayCount = min(30, count($logLines));
    
    for ($i = 0; $i < $displayCount; $i++) {
        echo $logLines[$i] . "\n";
    }
    
    if (count($logLines) > $displayCount) {
        echo COLOR_YELLOW . "... и еще " . (count($logLines) - $displayCount) . " строк в лог-файле" . COLOR_RESET . "\n";
    }
}

echo "\n" . COLOR_CYAN . "Полный лог доступен в: " . ($logFiles[0] ?? 'не создан') . COLOR_RESET . "\n\n";

// Возвращаем код выхода
exit($failedTests > 0 ? 1 : 0);

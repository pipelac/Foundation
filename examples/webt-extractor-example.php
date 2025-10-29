<?php

declare(strict_types=1);

/**
 * Пример использования WebtExtractor для извлечения контента из веб-страниц
 */

require_once __DIR__ . '/../autoload.php';

use App\Component\WebtExtractor;
use App\Component\Logger;
use App\Component\Rss;
use App\Component\Exception\WebtExtractorException;
use App\Component\Exception\WebtExtractorValidationException;

// ============================================================================
// 1. Базовый пример извлечения контента
// ============================================================================

echo "=== 1. Базовый пример извлечения контента ===\n\n";

try {
    // Инициализация логгера (опционально)
    $logger = new Logger([
        'directory' => __DIR__ . '/../logs',
        'file_name' => 'webt-extractor.log',
        'max_files' => 5,
        'max_file_size' => 10,
    ]);

    // Инициализация экстрактора
    $extractor = new WebtExtractor([
        'timeout' => 30,
        'retries' => 3,
        'user_agent' => 'Mozilla/5.0 (compatible; WebtExtractor/2.0)',
    ], $logger);

    // Извлечение контента
    $url = 'https://en.wikipedia.org/wiki/Web_scraping';
    echo "Извлечение контента из: {$url}\n\n";
    
    $result = $extractor->extract($url);
    
    echo "✓ Заголовок: {$result['title']}\n";
    echo "✓ Автор: " . ($result['author'] ?: 'не указан') . "\n";
    echo "✓ Язык: {$result['language']}\n";
    echo "✓ Количество слов: {$result['word_count']}\n";
    echo "✓ Время чтения: {$result['read_time']} мин.\n";
    echo "✓ Изображений: " . count($result['images']) . "\n";
    echo "✓ Ссылок: " . count($result['links']) . "\n";
    echo "\n✓ Контент (первые 200 символов):\n";
    echo substr($result['text_content'], 0, 200) . "...\n\n";
    
} catch (WebtExtractorValidationException $e) {
    echo "✗ Ошибка валидации: {$e->getMessage()}\n\n";
} catch (WebtExtractorException $e) {
    echo "✗ Ошибка извлечения: {$e->getMessage()}\n\n";
} catch (Exception $e) {
    echo "✗ Критическая ошибка: {$e->getMessage()}\n\n";
}

// ============================================================================
// 2. Извлечение из готового HTML
// ============================================================================

echo "=== 2. Извлечение из готового HTML ===\n\n";

try {
    $html = <<<'HTML'
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="description" content="Описание статьи">
    <meta property="og:title" content="OG заголовок">
    <meta property="og:description" content="OG описание">
    <title>Тестовая статья</title>
</head>
<body>
    <article>
        <h1>Заголовок статьи</h1>
        <p>Это первый абзац статьи с полезным контентом.</p>
        <img src="/image1.jpg" alt="Изображение 1">
        <p>Это второй абзац с ещё большим количеством полезной информации.</p>
        <a href="https://example.com">Внешняя ссылка</a>
        <p>Третий абзац завершает статью.</p>
    </article>
</body>
</html>
HTML;

    $result = $extractor->extractFromHtml($html, 'https://example.com/article');
    
    echo "✓ Заголовок: {$result['title']}\n";
    echo "✓ Количество слов: {$result['word_count']}\n";
    echo "✓ Изображений: " . count($result['images']) . "\n";
    echo "✓ Ссылок: " . count($result['links']) . "\n";
    
    if (!empty($result['metadata']['open_graph'])) {
        echo "\n✓ Open Graph данные:\n";
        foreach ($result['metadata']['open_graph'] as $key => $value) {
            echo "  - {$key}: {$value}\n";
        }
    }
    
    echo "\n";
    
} catch (Exception $e) {
    echo "✗ Ошибка: {$e->getMessage()}\n\n";
}

// ============================================================================
// 3. Пакетная обработка ссылок из RSS ленты
// ============================================================================

echo "=== 3. Пакетная обработка ссылок из RSS ленты ===\n\n";

try {
    // Инициализация RSS клиента
    $rss = new Rss([
        'timeout' => 10,
    ], $logger);
    
    // Загружаем тестовую RSS ленту (замените на реальную)
    $feedUrl = 'https://feeds.bbci.co.uk/news/rss.xml';
    echo "Загрузка RSS ленты: {$feedUrl}\n";
    
    $feed = $rss->fetch($feedUrl);
    echo "✓ Загружено {$feed['title']}\n";
    echo "✓ Всего записей: " . count($feed['items']) . "\n\n";
    
    // Извлекаем URL первых 3 статей
    $urls = array_slice(
        array_map(fn($item) => $item['link'], $feed['items']),
        0,
        3
    );
    
    echo "Пакетное извлечение контента из " . count($urls) . " статей...\n\n";
    
    // Пакетное извлечение
    $results = $extractor->extractBatch($urls, continueOnError: true);
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($results as $url => $result) {
        if (isset($result['error'])) {
            echo "✗ Ошибка [{$url}]:\n  {$result['error']}\n\n";
            $errorCount++;
            continue;
        }
        
        echo "✓ {$result['title']}\n";
        echo "  Слов: {$result['word_count']}, Время чтения: {$result['read_time']} мин.\n";
        echo "  URL: {$url}\n\n";
        $successCount++;
    }
    
    echo "Итого: {$successCount} успешно, {$errorCount} с ошибками\n\n";
    
} catch (Exception $e) {
    echo "✗ Ошибка: {$e->getMessage()}\n\n";
}

// ============================================================================
// 4. Использование с прокси
// ============================================================================

echo "=== 4. Использование с прокси ===\n\n";

try {
    // Настройка с прокси (закомментировано для примера)
    $extractorWithProxy = new WebtExtractor([
        // 'proxy' => 'http://proxy.example.com:8080',
        // или с аутентификацией:
        // 'proxy' => 'http://user:pass@proxy.example.com:8080',
        'timeout' => 60,
        'retries' => 5,
    ], $logger);
    
    echo "✓ Экстрактор с прокси инициализирован\n";
    echo "(раскомментируйте настройки прокси для использования)\n\n";
    
} catch (Exception $e) {
    echo "✗ Ошибка: {$e->getMessage()}\n\n";
}

// ============================================================================
// 5. Извлечение изображений и ссылок
// ============================================================================

echo "=== 5. Извлечение изображений и ссылок ===\n\n";

try {
    $extractorFull = new WebtExtractor([
        'extract_images' => true,
        'extract_links' => true,
        'extract_metadata' => true,
    ], $logger);
    
    $url = 'https://en.wikipedia.org/wiki/PHP';
    echo "Извлечение всех данных из: {$url}\n\n";
    
    $result = $extractorFull->extract($url);
    
    // Показываем первые 3 изображения
    if (!empty($result['images'])) {
        echo "✓ Изображения (первые 3):\n";
        foreach (array_slice($result['images'], 0, 3) as $img) {
            echo "  - {$img['src']}\n";
            if ($img['alt']) {
                echo "    Alt: {$img['alt']}\n";
            }
        }
        echo "\n";
    }
    
    // Показываем первые 3 ссылки
    if (!empty($result['links'])) {
        echo "✓ Ссылки (первые 3):\n";
        foreach (array_slice($result['links'], 0, 3) as $link) {
            echo "  - {$link['text']}\n";
            echo "    {$link['href']}\n";
        }
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "✗ Ошибка: {$e->getMessage()}\n\n";
}

// ============================================================================
// 6. Создание превью статьи
// ============================================================================

echo "=== 6. Создание превью статьи ===\n\n";

/**
 * Функция для создания превью статьи
 */
function createArticlePreview(WebtExtractor $extractor, string $url): array
{
    $result = $extractor->extract($url);
    
    return [
        'title' => $result['title'],
        'description' => mb_substr($result['text_content'], 0, 200) . '...',
        'image' => $result['lead_image_url'] ?: ($result['images'][0]['src'] ?? ''),
        'read_time' => $result['read_time'],
        'word_count' => $result['word_count'],
        'url' => $url,
    ];
}

try {
    $url = 'https://en.wikipedia.org/wiki/Web_content';
    $preview = createArticlePreview($extractor, $url);
    
    echo "✓ Превью создано:\n";
    echo "  Заголовок: {$preview['title']}\n";
    echo "  Описание: {$preview['description']}\n";
    echo "  Изображение: " . ($preview['image'] ?: 'нет') . "\n";
    echo "  Время чтения: {$preview['read_time']} мин.\n";
    echo "  Количество слов: {$preview['word_count']}\n\n";
    
} catch (Exception $e) {
    echo "✗ Ошибка: {$e->getMessage()}\n\n";
}

// ============================================================================
// 7. Поиск внешних ссылок
// ============================================================================

echo "=== 7. Поиск внешних ссылок ===\n\n";

/**
 * Функция для поиска внешних ссылок
 */
function findExternalLinks(WebtExtractor $extractor, string $url): array
{
    $result = $extractor->extract($url);
    $domain = parse_url($url, PHP_URL_HOST);
    
    return array_filter(
        $result['links'],
        fn($link) => parse_url($link['href'], PHP_URL_HOST) !== $domain
    );
}

try {
    $url = 'https://en.wikipedia.org/wiki/HTTP';
    $externalLinks = findExternalLinks($extractor, $url);
    
    echo "✓ Найдено " . count($externalLinks) . " внешних ссылок\n";
    echo "  Первые 3:\n";
    
    foreach (array_slice($externalLinks, 0, 3) as $link) {
        echo "  - {$link['href']}\n";
    }
    
    echo "\n";
    
} catch (Exception $e) {
    echo "✗ Ошибка: {$e->getMessage()}\n\n";
}

echo "=== Все примеры выполнены ===\n";

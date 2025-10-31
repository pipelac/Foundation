<?php

declare(strict_types=1);

namespace Tests;

use App\Component\WebtExtractor;
use App\Component\Logger;
use App\Component\Exception\WebtExtractor\WebtExtractorException;
use App\Component\Exception\WebtExtractor\WebtExtractorValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Тесты для класса WebtExtractor
 */
class WebtExtractorTest extends TestCase
{
    private WebtExtractor $extractor;
    private ?Logger $logger = null;

    protected function setUp(): void
    {
        // Инициализация без логгера для тестов
        $this->extractor = new WebtExtractor([
            'timeout' => 10,
            'retries' => 1,
        ]);
    }

    /**
     * Тест инициализации с базовой конфигурацией
     */
    public function testConstructorWithDefaultConfig(): void
    {
        $extractor = new WebtExtractor();
        $this->assertInstanceOf(WebtExtractor::class, $extractor);
    }

    /**
     * Тест инициализации с полной конфигурацией
     */
    public function testConstructorWithFullConfig(): void
    {
        $extractor = new WebtExtractor([
            'user_agent' => 'TestBot/1.0',
            'timeout' => 30,
            'connect_timeout' => 10,
            'max_content_size' => 5242880,
            'retries' => 5,
            'extract_images' => false,
            'extract_links' => false,
            'extract_metadata' => false,
            'verify_ssl' => false,
        ]);
        
        $this->assertInstanceOf(WebtExtractor::class, $extractor);
    }

    /**
     * Тест инициализации с логгером
     */
    public function testConstructorWithLogger(): void
    {
        $logger = new Logger([
            'directory' => sys_get_temp_dir(),
            'file_name' => 'test.log',
        ]);
        
        $extractor = new WebtExtractor([], $logger);
        $this->assertInstanceOf(WebtExtractor::class, $extractor);
    }

    /**
     * Тест валидации пустого URL
     */
    public function testExtractWithEmptyUrlThrowsException(): void
    {
        $this->expectException(WebtExtractorValidationException::class);
        $this->expectExceptionMessage('URL не может быть пустым');
        
        $this->extractor->extract('');
    }

    /**
     * Тест валидации URL без протокола
     */
    public function testExtractWithInvalidProtocolThrowsException(): void
    {
        $this->expectException(WebtExtractorValidationException::class);
        $this->expectExceptionMessage('URL должен использовать протокол HTTP или HTTPS');
        
        $this->extractor->extract('ftp://example.com');
    }

    /**
     * Тест валидации URL без хоста
     */
    public function testExtractWithInvalidUrlThrowsException(): void
    {
        $this->expectException(WebtExtractorValidationException::class);
        $this->expectExceptionMessage('Некорректный формат URL');
        
        $this->extractor->extract('http://');
    }

    /**
     * Тест извлечения из готового HTML
     */
    public function testExtractFromHtmlWithValidHtml(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <title>Test Article</title>
    <meta name="description" content="Test description">
</head>
<body>
    <article>
        <h1>Article Title</h1>
        <p>This is a paragraph with enough content to make it readable.</p>
        <p>This is another paragraph with more content to ensure proper extraction.</p>
        <p>And a third paragraph to make sure we have enough text content.</p>
    </article>
</body>
</html>
HTML;
        
        $result = $this->extractor->extractFromHtml($html, 'https://example.com/article');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('text_content', $result);
        $this->assertArrayHasKey('word_count', $result);
        $this->assertArrayHasKey('read_time', $result);
        $this->assertArrayHasKey('url', $result);
        
        $this->assertEquals('https://example.com/article', $result['url']);
        $this->assertGreaterThan(0, $result['word_count']);
        $this->assertGreaterThan(0, $result['read_time']);
    }

    /**
     * Тест извлечения из HTML с изображениями
     */
    public function testExtractFromHtmlWithImages(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
    <article>
        <h1>Article with Images</h1>
        <p>Content with images below.</p>
        <img src="/image1.jpg" alt="Image 1" title="First Image">
        <p>More content here to make it readable and extractable by the system.</p>
        <img src="https://example.com/image2.jpg" alt="Image 2">
    </article>
</body>
</html>
HTML;
        
        $result = $this->extractor->extractFromHtml($html, 'https://example.com/article');
        
        $this->assertArrayHasKey('images', $result);
        $this->assertIsArray($result['images']);
        $this->assertCount(2, $result['images']);
        
        $this->assertEquals('https://example.com/image1.jpg', $result['images'][0]['src']);
        $this->assertEquals('Image 1', $result['images'][0]['alt']);
        $this->assertEquals('First Image', $result['images'][0]['title']);
    }

    /**
     * Тест извлечения из HTML со ссылками
     */
    public function testExtractFromHtmlWithLinks(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
    <article>
        <h1>Article with Links</h1>
        <p>This is content with <a href="https://example.com/link1" title="Link Title">a link</a> inside.</p>
        <p>More content with <a href="/relative-link">relative link</a> to make it extractable.</p>
        <p>And <a href="#anchor">anchor link</a> that should be filtered out properly.</p>
    </article>
</body>
</html>
HTML;
        
        $result = $this->extractor->extractFromHtml($html, 'https://example.com/article');
        
        $this->assertArrayHasKey('links', $result);
        $this->assertIsArray($result['links']);
        
        // Должны быть только не-anchor ссылки
        $this->assertGreaterThanOrEqual(2, count($result['links']));
    }

    /**
     * Тест извлечения мета-данных
     */
    public function testExtractFromHtmlWithMetadata(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <meta name="description" content="Page description">
    <meta property="og:title" content="OG Title">
    <meta property="og:description" content="OG Description">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="Twitter Title">
</head>
<body>
    <article>
        <h1>Article with Metadata</h1>
        <p>This is content that should be extracted properly with all metadata.</p>
        <p>Additional paragraph to ensure proper content extraction by the library.</p>
    </article>
</body>
</html>
HTML;
        
        $result = $this->extractor->extractFromHtml($html, 'https://example.com/article');
        
        $this->assertArrayHasKey('metadata', $result);
        $this->assertIsArray($result['metadata']);
        
        $this->assertArrayHasKey('open_graph', $result['metadata']);
        $this->assertArrayHasKey('twitter_card', $result['metadata']);
        $this->assertArrayHasKey('meta', $result['metadata']);
        
        $this->assertEquals('OG Title', $result['metadata']['open_graph']['title']);
        $this->assertEquals('summary', $result['metadata']['twitter_card']['card']);
    }

    /**
     * Тест валидации пустого HTML
     */
    public function testExtractFromHtmlWithEmptyHtmlThrowsException(): void
    {
        $this->expectException(WebtExtractorValidationException::class);
        $this->expectExceptionMessage('HTML контент не может быть пустым');
        
        $this->extractor->extractFromHtml('', 'https://example.com');
    }

    /**
     * Тест валидации слишком короткого HTML
     */
    public function testExtractFromHtmlWithShortHtmlThrowsException(): void
    {
        $this->expectException(WebtExtractorValidationException::class);
        $this->expectExceptionMessage('HTML контент слишком короткий');
        
        $this->extractor->extractFromHtml('<html></html>', 'https://example.com');
    }

    /**
     * Тест извлечения из HTML без читаемого контента
     */
    public function testExtractFromHtmlWithNoReadableContentThrowsException(): void
    {
        $html = str_repeat('<div>test</div>', 20); // Минимальная длина, но нет статьи
        
        $this->expectException(WebtExtractorException::class);
        
        $this->extractor->extractFromHtml($html, 'https://example.com');
    }

    /**
     * Тест пакетной обработки с пустым массивом
     */
    public function testExtractBatchWithEmptyArray(): void
    {
        $results = $this->extractor->extractBatch([]);
        
        $this->assertIsArray($results);
        $this->assertCount(0, $results);
    }

    /**
     * Тест пакетной обработки с невалидными URL
     */
    public function testExtractBatchWithInvalidUrls(): void
    {
        $urls = [
            'invalid-url',
            'ftp://example.com',
            'http://',
        ];
        
        $results = $this->extractor->extractBatch($urls, continueOnError: true);
        
        $this->assertIsArray($results);
        $this->assertCount(3, $results);
        
        foreach ($results as $url => $result) {
            $this->assertArrayHasKey('error', $result);
            $this->assertIsString($result['error']);
        }
    }

    /**
     * Тест корректного подсчета слов
     */
    public function testWordCountCalculation(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
    <article>
        <h1>Test Article</h1>
        <p>One two three four five six seven eight nine ten.</p>
        <p>Additional paragraph with more words to test the counter properly.</p>
    </article>
</body>
</html>
HTML;
        
        $result = $this->extractor->extractFromHtml($html, 'https://example.com');
        
        $this->assertGreaterThan(10, $result['word_count']);
    }

    /**
     * Тест расчета времени чтения
     */
    public function testReadTimeCalculation(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
    <article>
        <h1>Test Article</h1>
        <p>This is a test paragraph with some content for testing read time calculation.</p>
        <p>Another paragraph to add more words and ensure proper calculation of reading time.</p>
    </article>
</body>
</html>
HTML;
        
        $result = $this->extractor->extractFromHtml($html, 'https://example.com');
        
        $this->assertArrayHasKey('read_time', $result);
        $this->assertIsInt($result['read_time']);
        $this->assertGreaterThanOrEqual(1, $result['read_time']);
    }

    /**
     * Тест извлечения с кириллицей
     */
    public function testExtractFromHtmlWithCyrillic(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body>
    <article>
        <h1>Тестовая статья</h1>
        <p>Это тестовый параграф с кириллическим текстом для проверки работы экстрактора.</p>
        <p>Дополнительный параграф с большим количеством текста на русском языке.</p>
    </article>
</body>
</html>
HTML;
        
        $result = $this->extractor->extractFromHtml($html, 'https://example.com/article');
        
        $this->assertArrayHasKey('language', $result);
        $this->assertEquals('ru', $result['language']);
        $this->assertGreaterThan(0, $result['word_count']);
    }

    /**
     * Тест конфигурации с отключенными опциями
     */
    public function testExtractWithDisabledOptions(): void
    {
        $extractor = new WebtExtractor([
            'extract_images' => false,
            'extract_links' => false,
            'extract_metadata' => false,
        ]);
        
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <meta property="og:title" content="OG Title">
</head>
<body>
    <article>
        <h1>Article Title</h1>
        <p>Content with <a href="/link">link</a> and <img src="/image.jpg"> image.</p>
        <p>More content to ensure proper extraction without additional features enabled.</p>
    </article>
</body>
</html>
HTML;
        
        $result = $extractor->extractFromHtml($html, 'https://example.com');
        
        $this->assertArrayHasKey('images', $result);
        $this->assertArrayHasKey('links', $result);
        $this->assertArrayHasKey('metadata', $result);
        
        // Должны быть пустыми из-за отключенных опций
        $this->assertCount(0, $result['images']);
        $this->assertCount(0, $result['links']);
        $this->assertCount(0, $result['metadata']['open_graph']);
    }
}

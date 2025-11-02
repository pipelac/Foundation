<?php

declare(strict_types=1);

namespace Tests\Rss2Tlg\DTO;

use App\Rss2Tlg\DTO\FeedConfig;
use PHPUnit\Framework\TestCase;

/**
 * Тесты для класса FeedConfig
 */
class FeedConfigTest extends TestCase
{
    /**
     * Тест успешного создания конфигурации из массива
     */
    public function testFromArraySuccess(): void
    {
        $data = [
            'id' => 1,
            'url' => 'https://example.com/feed.xml',
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'polling_interval' => 300,
            'headers' => ['User-Agent' => 'Test/1.0'],
            'parser_options' => ['max_items' => 50],
            'proxy' => 'http://proxy.example.com:8080',
        ];

        $config = FeedConfig::fromArray($data);

        $this->assertSame(1, $config->id);
        $this->assertSame('https://example.com/feed.xml', $config->url);
        $this->assertTrue($config->enabled);
        $this->assertSame(30, $config->timeout);
        $this->assertSame(3, $config->retries);
        $this->assertSame(300, $config->pollingInterval);
        $this->assertSame(['User-Agent' => 'Test/1.0'], $config->headers);
        $this->assertSame(['max_items' => 50], $config->parserOptions);
        $this->assertSame('http://proxy.example.com:8080', $config->proxy);
    }

    /**
     * Тест создания с дефолтными значениями
     */
    public function testFromArrayWithDefaults(): void
    {
        $data = [
            'id' => 2,
            'url' => 'https://example.com/rss',
        ];

        $config = FeedConfig::fromArray($data);

        $this->assertSame(2, $config->id);
        $this->assertTrue($config->enabled); // Default true
        $this->assertSame(30, $config->timeout); // Default 30
        $this->assertSame(3, $config->retries); // Default 3
        $this->assertSame(300, $config->pollingInterval); // Default 300
        $this->assertSame([], $config->headers); // Default empty
        $this->assertSame([], $config->parserOptions); // Default empty
        $this->assertNull($config->proxy); // Default null
    }

    /**
     * Тест валидации: отсутствие ID
     */
    public function testValidationMissingId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Параметр "id" обязателен');

        FeedConfig::fromArray([
            'url' => 'https://example.com/feed.xml',
        ]);
    }

    /**
     * Тест валидации: отсутствие URL
     */
    public function testValidationMissingUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Параметр "url" обязателен');

        FeedConfig::fromArray([
            'id' => 1,
        ]);
    }

    /**
     * Тест валидации: пустой URL
     */
    public function testValidationEmptyUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Параметр "url" обязателен');

        FeedConfig::fromArray([
            'id' => 1,
            'url' => '   ',
        ]);
    }

    /**
     * Тест валидации: некорректный формат URL
     */
    public function testValidationInvalidUrlFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Некорректный формат URL');

        FeedConfig::fromArray([
            'id' => 1,
            'url' => 'not-a-valid-url',
        ]);
    }

    /**
     * Тест валидации: неподдерживаемый протокол
     */
    public function testValidationInvalidProtocol(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('URL должен использовать протокол HTTP или HTTPS');

        FeedConfig::fromArray([
            'id' => 1,
            'url' => 'ftp://example.com/feed.xml',
        ]);
    }

    /**
     * Тест конвертации в массив
     */
    public function testToArray(): void
    {
        $data = [
            'id' => 1,
            'url' => 'https://example.com/feed.xml',
            'enabled' => true,
            'timeout' => 20,
            'retries' => 2,
            'polling_interval' => 600,
            'headers' => ['Accept' => 'application/rss+xml'],
            'parser_options' => ['max_items' => 100],
            'proxy' => null,
        ];

        $config = FeedConfig::fromArray($data);
        $result = $config->toArray();

        $this->assertSame($data, $result);
    }
}

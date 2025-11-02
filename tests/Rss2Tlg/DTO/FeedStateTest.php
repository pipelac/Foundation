<?php

declare(strict_types=1);

namespace Tests\Rss2Tlg\DTO;

use App\Rss2Tlg\DTO\FeedState;
use PHPUnit\Framework\TestCase;

/**
 * Тесты для класса FeedState
 */
class FeedStateTest extends TestCase
{
    /**
     * Тест создания начального состояния
     */
    public function testCreateInitial(): void
    {
        $state = FeedState::createInitial();

        $this->assertNull($state->etag);
        $this->assertNull($state->lastModified);
        $this->assertSame(0, $state->lastStatus);
        $this->assertSame(0, $state->errorCount);
        $this->assertNull($state->backoffUntil);
        $this->assertSame(0, $state->fetchedAt);
    }

    /**
     * Тест создания из массива
     */
    public function testFromArray(): void
    {
        $data = [
            'etag' => '"abc123"',
            'last_modified' => 'Mon, 15 Jan 2024 10:00:00 GMT',
            'last_status' => 200,
            'error_count' => 2,
            'backoff_until' => 1705320000,
            'fetched_at' => 1705319000,
        ];

        $state = FeedState::fromArray($data);

        $this->assertSame('"abc123"', $state->etag);
        $this->assertSame('Mon, 15 Jan 2024 10:00:00 GMT', $state->lastModified);
        $this->assertSame(200, $state->lastStatus);
        $this->assertSame(2, $state->errorCount);
        $this->assertSame(1705320000, $state->backoffUntil);
        $this->assertSame(1705319000, $state->fetchedAt);
    }

    /**
     * Тест успешного fetch с обновлением состояния
     */
    public function testWithSuccessfulFetch(): void
    {
        $initialState = new FeedState(
            etag: '"old-etag"',
            lastModified: 'Old Date',
            lastStatus: 500,
            errorCount: 3,
            backoffUntil: time() + 1000,
            fetchedAt: 1000000
        );

        $newState = $initialState->withSuccessfulFetch(
            '"new-etag"',
            'New Date',
            200
        );

        // Проверяем обновлённые поля
        $this->assertSame('"new-etag"', $newState->etag);
        $this->assertSame('New Date', $newState->lastModified);
        $this->assertSame(200, $newState->lastStatus);
        
        // Проверяем сброс ошибок
        $this->assertSame(0, $newState->errorCount);
        $this->assertNull($newState->backoffUntil);
        
        // Проверяем что время обновлено
        $this->assertGreaterThan($initialState->fetchedAt, $newState->fetchedAt);
    }

    /**
     * Тест неудачного fetch с увеличением счётчика ошибок
     */
    public function testWithFailedFetch(): void
    {
        $initialState = FeedState::createInitial();
        
        $newState = $initialState->withFailedFetch(503);

        $this->assertSame(503, $newState->lastStatus);
        $this->assertSame(1, $newState->errorCount);
        $this->assertNotNull($newState->backoffUntil);
        $this->assertGreaterThan(time(), $newState->backoffUntil);
    }

    /**
     * Тест экспоненциального backoff при последовательных ошибках
     */
    public function testExponentialBackoff(): void
    {
        $state = FeedState::createInitial();

        // Первая ошибка: backoff ~120 сек
        $state1 = $state->withFailedFetch(500);
        $this->assertSame(1, $state1->errorCount);
        
        // Вторая ошибка: backoff ~240 сек
        $state2 = $state1->withFailedFetch(500);
        $this->assertSame(2, $state2->errorCount);
        
        // Третья ошибка: backoff ~480 сек
        $state3 = $state2->withFailedFetch(500);
        $this->assertSame(3, $state3->errorCount);
        
        // Проверяем что backoff увеличивается
        $this->assertGreaterThan($state1->backoffUntil, $state2->backoffUntil);
        $this->assertGreaterThan($state2->backoffUntil, $state3->backoffUntil);
    }

    /**
     * Тест кастомного backoff времени
     */
    public function testCustomBackoff(): void
    {
        $state = FeedState::createInitial();
        $customBackoff = 7200; // 2 часа

        $newState = $state->withFailedFetch(429, $customBackoff);

        $expectedBackoffUntil = time() + $customBackoff;
        
        $this->assertSame(429, $newState->lastStatus);
        $this->assertSame(1, $newState->errorCount);
        
        // Проверяем с небольшой погрешностью (±2 секунды)
        $this->assertEqualsWithDelta($expectedBackoffUntil, $newState->backoffUntil, 2);
    }

    /**
     * Тест проверки наличия backoff
     */
    public function testIsInBackoff(): void
    {
        // Состояние без backoff
        $state1 = new FeedState(
            etag: null,
            lastModified: null,
            lastStatus: 200,
            errorCount: 0,
            backoffUntil: null,
            fetchedAt: time()
        );
        $this->assertFalse($state1->isInBackoff());

        // Состояние с активным backoff
        $state2 = new FeedState(
            etag: null,
            lastModified: null,
            lastStatus: 500,
            errorCount: 1,
            backoffUntil: time() + 300,
            fetchedAt: time()
        );
        $this->assertTrue($state2->isInBackoff());

        // Состояние с истёкшим backoff
        $state3 = new FeedState(
            etag: null,
            lastModified: null,
            lastStatus: 500,
            errorCount: 1,
            backoffUntil: time() - 100,
            fetchedAt: time()
        );
        $this->assertFalse($state3->isInBackoff());
    }

    /**
     * Тест получения оставшегося времени backoff
     */
    public function testGetBackoffRemaining(): void
    {
        // Без backoff
        $state1 = FeedState::createInitial();
        $this->assertSame(0, $state1->getBackoffRemaining());

        // С активным backoff
        $backoffTime = 120;
        $state2 = new FeedState(
            etag: null,
            lastModified: null,
            lastStatus: 500,
            errorCount: 1,
            backoffUntil: time() + $backoffTime,
            fetchedAt: time()
        );
        
        $remaining = $state2->getBackoffRemaining();
        $this->assertGreaterThan(0, $remaining);
        $this->assertLessThanOrEqual($backoffTime, $remaining);

        // С истёкшим backoff
        $state3 = new FeedState(
            etag: null,
            lastModified: null,
            lastStatus: 500,
            errorCount: 1,
            backoffUntil: time() - 100,
            fetchedAt: time()
        );
        $this->assertSame(0, $state3->getBackoffRemaining());
    }

    /**
     * Тест конвертации в массив
     */
    public function testToArray(): void
    {
        $state = new FeedState(
            etag: '"test-etag"',
            lastModified: 'Test Date',
            lastStatus: 200,
            errorCount: 0,
            backoffUntil: 1705320000,
            fetchedAt: 1705319000
        );

        $result = $state->toArray();

        $this->assertSame([
            'etag' => '"test-etag"',
            'last_modified' => 'Test Date',
            'last_status' => 200,
            'error_count' => 0,
            'backoff_until' => 1705320000,
            'fetched_at' => 1705319000,
        ], $result);
    }
}

<?php declare(strict_types=1);

namespace Cache;

use Clock\Clock;
use Clock\ClockExceptionInterface;
use Clock\ClockInterface;
use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

class InMemoryCacheTest extends TestCase
{

    public function testInstanceOfPsr(): void
    {
        $cache = new InMemoryCache;
        self::assertInstanceOf(CacheInterface::class, $cache);
    }

    /**
     * @throws \Psr\SimpleCache\CacheException
     * @throws ClockExceptionInterface
     */
    public function testHasAndGet(): void
    {
        $key = 'my-key';
        $cache = new InMemoryCache;
        self::assertFalse($cache->has($key));

        $cache->set($key, 'value');
        self::assertTrue($cache->has($key));

        $cache->set($key, 'value', -1);
        self::assertFalse($cache->has($key));

        $cache->set($key, 'value');
        self::assertTrue($cache->has($key));

        $cache->clear();
        self::assertFalse($cache->has($key));
    }

    /**
     * @dataProvider getWithExpiryProvider
     * @throws \Psr\SimpleCache\CacheException
     * @throws ClockExceptionInterface|Exception
     */
    public function testGetWithExpiry(DateInterval|int|null $ttl, int $interval, bool $has): void
    {
        $key = 'my-key';
        $value = 'my-value';
        $default = null;

        $cache = $this->prepExpiryCache($interval);

        self::assertFalse($cache->has($key));

        $cache->set($key, $value, $ttl);

        self::assertEquals($has ? $value : $default, $cache->get($key, $default));
    }

        /**
         * @dataProvider getWithExpiryProvider
         * @throws \Psr\SimpleCache\CacheException
         * @throws ClockExceptionInterface|Exception
         */
        public function testHasWithExpiry(DateInterval|int|null $ttl, int $interval, bool $has): void
    {
        $key = 'my-key';
        $value = 'my-value';
        $default = null;
        $cache = $this->prepExpiryCache($interval);

        self::assertFalse($cache->has($key));

        $cache->set($key, $value, $ttl);

        self::assertEquals($has, $cache->has($key));
    }

    public static function getWithExpiryProvider(): array
    {
        return [
            [null, 0, true],
            [null, 10, true],
            [null, 3600, true],
            [-1, 0, false],
            [3600, 0, true],
            [3600, 5, true],
            [3600, 3600, true],
            [3600, 3601, false],
            [new DateInterval('PT1S'), 0, true],
            [new DateInterval('PT1S'), 1, true],
            [new DateInterval('PT1S'), 2, false],
            [new DateInterval('PT5S'), 0, true],
            [new DateInterval('PT5S'), 5, true],
            [new DateInterval('PT5S'), 6, false],
            [new DateInterval('PT1H'), 0, true],
            [new DateInterval('PT1H'), 3600, true],
            [new DateInterval('PT1H'), 3601, false],
        ];
    }

    /**
     * @throws Exception
     */
    private function prepExpiryCache(int $interval): InMemoryCache
    {
        $clock = $this->createMock(ClockInterface::class);

        $counter = 0;
        $clock->expects($this->any())->method('now')
            ->willReturnCallback(function () use (&$counter, $interval) {
                $counter++;
                if ($counter <= 2) {
                    return (new DateTimeImmutable)->setTimestamp(0);
                }
                return (new DateTimeImmutable)->setTimestamp($interval);
            });

        $clock->expects($this->any())->method('with')->willReturnCallback(function () {
            return new Clock(0);
        });

        return new InMemoryCache($clock);
    }
}
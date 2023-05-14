<?php declare(strict_types=1);

namespace Cache;

use DateInterval;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

class SessionHandlerTest extends TestCase
{
    private CacheInterface|MockObject $cache;
    private string $prefix = 'test-prefix:';

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
    }

    /**
     * @dataProvider writeDataProvider
     */
    public function testWrite(string $id, string $data, DateInterval|int $ttl, bool $expected): void
    {
        $handler = new SessionHandler($this->cache, $ttl, $this->prefix);

        $this->cache->expects($this->once())->method('set')
            ->with($this->prefix . $id, $data, $ttl)
            ->willReturn($expected);

        self::assertEquals($expected, $handler->write($id, $data));
    }

    public static function writeDataProvider(): array
    {
        $interval = new DateInterval('P1D');
        return [
            ['session-id', 'e0gq014dl62369llptd600ns5a', 3600, true],
            ['session-id', 'e0gq014dl62369llptd600ns5a', 3600, false],
            ['session-id', 'e0gq014dl62369llptd600ns5a', $interval, true],
            ['session-id', 'e0gq014dl62369llptd600ns5a', $interval, false]
        ];
    }

    public function testGc()
    {
        $handler = new SessionHandler($this->cache, 0);

        self::assertTrue($handler->gc(0));
    }

    /**
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function testDestroy(): void
    {
        $handler = new SessionHandler($this->cache, 0, $this->prefix);

        $this->cache->expects($this->once())->method('delete')
            ->with($this->prefix . 'e0gq014dl62369llptd600ns5a')
            ->willReturn(true);

        self::assertTrue($handler->destroy('e0gq014dl62369llptd600ns5a'));
    }

    public function testClose()
    {
        $handler = new SessionHandler($this->cache, 0);
        self::assertTrue($handler->close());
    }

    public function testOpen()
    {
        $handler = new SessionHandler($this->cache, 0);
        self::assertTrue($handler->open('path', 'PHPSESSID-name'));
    }

    /**
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function testRead()
    {
        $handler = new SessionHandler($this->cache, 0, $this->prefix);

        $this->cache->expects($this->once())->method('get')
            ->with($this->prefix . 'e0gq014dl62369llptd600ns5a')
            ->willReturn('my-value');

        $result = $handler->read('e0gq014dl62369llptd600ns5a');

        self::assertSame('my-value', $result);
    }
}

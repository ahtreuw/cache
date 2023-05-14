<?php declare(strict_types=1);

namespace Cache;

use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

class NullCacheTest extends TestCase
{
    /**
     * @throws InvalidArgumentException
     */
    public function testNullCache(): void
    {
        $simpleCache = new NullCache;

        self::assertInstanceOf(CacheInterface::class, $simpleCache);

        self::assertNull($simpleCache->get('key'));
        self::assertEquals(['key' => null], $simpleCache->getMultiple(['key']));

        self::assertFalse($simpleCache->has('key'));

        self::assertFalse($simpleCache->delete('key'));
        self::assertFalse($simpleCache->deleteMultiple(['key']));

        self::assertFalse($simpleCache->set('key', 'value'));
        self::assertFalse($simpleCache->setMultiple(['key', 'value']));

        self::assertFalse($simpleCache->clear());
    }
}

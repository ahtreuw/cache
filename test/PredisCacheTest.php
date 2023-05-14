<?php declare(strict_types=1);

namespace Cache;

use Clock\ClockException;
use Clock\ClockInterface;
use DateInterval;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Predis\ClientInterface;
use Predis\Command\CommandInterface;
use Predis\Command\Redis\DEL;
use Predis\Command\Redis\EXISTS;
use Predis\Command\Redis\GET;
use Predis\Command\Redis\KEYS;
use Predis\Command\Redis\SET;
use Predis\Response\ServerException;
use Predis\Response\Status;
use Psr\SimpleCache\CacheException;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

class PredisCacheTest extends TestCase
{
    private MockObject|ClientInterface $client;
    private static string $prefix = 'prefix:';
    private static int $defaultTtl = 13;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->client = $this->createMock(ClientInterface::class);
    }

    public function testInstanceOfPsr(): void
    {
        $cache = new PredisCache(parameters: $this->client);
        self::assertInstanceOf(CacheInterface::class, $cache);
    }

    /**
     * @dataProvider hasProvider
     * @throws CacheException
     */
    public function testHas(string $key, mixed $exists, mixed $expected): void
    {
        $this->client->expects($this->once())->method('executeCommand')
            ->willReturnCallback(function (CommandInterface $command) use ($key, $exists) {

                self::assertInstanceOf(EXISTS::class, $command);
                self::assertEquals(self::$prefix . $key, $command->getArgument(0));

                return $exists;
            });

        $cache = new PredisCache(parameters: $this->client, prefix: self::$prefix);

        $result = $cache->has($key);

        self::assertEquals($expected, $result);
    }

    public static function hasProvider(): array
    {
        return [
            ['key-01', true, true],
            ['key:02', false, false]
        ];
    }

    /**
     * @dataProvider getProvider
     * @throws CacheException
     */
    public function testGet(string $key, mixed $value, mixed $default, mixed $expected): void
    {
        $this->client->expects($this->once())->method('executeCommand')
            ->willReturnCallback(function (CommandInterface $command) use ($key, $value) {

                self::assertInstanceOf(GET::class, $command);
                self::assertEquals(self::$prefix . $key, $command->getArgument(0));

                return is_null($value) ? $value : serialize($value);
            });

        $cache = new PredisCache(parameters: $this->client, prefix: self::$prefix);

        $result = $cache->get($key, $default);

        self::assertEquals($expected, $result);
    }

    public static function getProvider(): array
    {
        $interval = new DateInterval('PT35S');
        return [
            ['key-01', 'value-01', 'default-value', 'value-01'],
            ['key-01', $interval, 'default-value', $interval],
            ['key-01', null, 'default-value', 'default-value'],
            ['key-01', null, $interval, $interval],
            ['key-01', null, null, null],
        ];
    }


    /**
     * @dataProvider setProvider
     * @throws CacheException
     */
    public function testSet(string $key, mixed $value, null|int|DateInterval $ttl, array $expected): void
    {
        $this->client->expects($this->once())->method('executeCommand')
            ->willReturnCallback(function (CommandInterface $command) use ($key, $expected) {

                self::assertInstanceOf(SET::class, $command);
                self::assertEquals($expected, $command->getArguments());

                return new Status('OK');
            });

        $cache = new PredisCache(parameters: $this->client, prefix: self::$prefix);

        self::assertEquals(true, $cache->set($key, $value, $ttl));
    }

    /**
     * @dataProvider setProvider
     * @throws CacheException
     */
    public function testSetWithDefaultTtl(): void
    {
        $this->client->expects($this->once())->method('executeCommand')
            ->willReturnCallback(function (CommandInterface $command) {

                self::assertInstanceOf(SET::class, $command);
                self::assertEquals(['key', serialize('value'), 'EX', self::$defaultTtl], $command->getArguments());

                return new Status('OK');
            });

        $cache = new PredisCache(parameters: $this->client, defaultTtl: self::$defaultTtl);

        self::assertEquals(true, $cache->set('key', 'value'));
    }

    public static function setProvider(): array
    {
        $interval = new DateInterval('PT35S');
        return [
            ['key', 'value', $interval, [self::$prefix . 'key', serialize('value'), 'EX', 35]],
            ['key', $interval, 3600, [self::$prefix . 'key', serialize($interval), 'EX', 3600]],
            ['key', $interval, null, [self::$prefix . 'key', serialize($interval)]]
        ];
    }

    /**
     * @throws CacheException
     * @throws InvalidArgumentException
     */
    public function testDelete(): void
    {
        $this->client->expects($this->once())->method('executeCommand')
            ->willReturnCallback(function (CommandInterface $command) {

                self::assertInstanceOf(DEL::class, $command);
                self::assertEquals(['my-key'], $command->getArguments());

                return new Status('OK');
            });

        $cache = new PredisCache(parameters: $this->client);

        self::assertEquals(true, $cache->delete('my-key'));
    }

    public function testSetError(): void
    {
        $this->client->expects($this->once())->method('executeCommand')
            ->willReturnCallback(function () {
                return new Status('My error message');
            });

        $cache = new PredisCache(parameters: $this->client, defaultTtl: self::$defaultTtl, prefix: self::$prefix);

        self::expectException(CacheException::class);
        self::expectExceptionMessage('My error message');

        $cache->set('key', 'value');
    }

    public function testInvalidArgumentException(): void
    {
        $this->client->expects($this->never())->method('executeCommand');

        $cache = new PredisCache(parameters: $this->client);

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The key string is not a legal value: "key ".');

        $cache->set('key ', 'value');
    }

    /**
     * @throws CacheException
     * @throws Exception
     */
    public function testClockExceptionArgumentException(): void
    {
        $this->client->expects($this->never())->method('executeCommand');
        $clock = $this->createMock(ClockInterface::class);

        $cache = new PredisCache(parameters: $this->client, clock: $clock);

        self::expectException(\Cache\CacheException::class);
        self::expectExceptionMessage('My clock exception message');

        $clock->expects($this->once())->method('with')->willReturnCallback(function () {
            throw new ClockException('My clock exception message');
        });

        $cache->set('key', 'value', new DateInterval('PT35S'));
    }

    public function testSetServerErrorOnExecuteCommand(): void
    {
        $this->client->expects($this->once())->method('executeCommand')
            ->willReturnCallback(function () {
                throw new ServerException('My error message');
            });

        $cache = new PredisCache(parameters: $this->client, defaultTtl: self::$defaultTtl, prefix: self::$prefix);

        self::expectException(CacheException::class);
        self::expectExceptionMessage('My error message');

        $cache->set('key', 'value');
    }

    /**
     * @throws CacheException
     * @throws InvalidArgumentException
     */
    public function testClearEmpty(): void
    {
        $this->client->expects($this->once())->method('executeCommand')
            ->willReturnCallback(function (CommandInterface $command) {
                self::assertInstanceOf(KEYS::class, $command);
                self::assertEquals(self::$prefix . '*', $command->getArgument(0));
                return [];
            });

        $cache = new PredisCache(parameters: $this->client, prefix: self::$prefix);
        $cache->clear();
    }

    /**
     * @throws CacheException
     * @throws InvalidArgumentException
     */
    public function testClear(): void
    {
        $this->client->expects($this->exactly(2))->method('executeCommand')
            ->willReturnCallback(function (CommandInterface $command) {
                if ($command instanceof KEYS) {
                    self::assertInstanceOf(KEYS::class, $command);
                    self::assertEquals('*', $command->getArgument(0));
                    return ['key1', 'key2'];
                }
                self::assertInstanceOf(DEL::class, $command);
                self::assertEquals(['key1', 'key2'], $command->getArguments());
                return true;
            });

        $cache = new PredisCache(parameters: $this->client);
        $cache->clear();
    }


    /**
     * @throws CacheException
     */
    public function testGetMultiple(): void
    {
        $this->client->expects($this->exactly(2))->method('executeCommand')
            ->willReturnCallback(function (CommandInterface $command) {
                self::assertInstanceOf(GET::class, $command);
                return serialize('test-val');
            });

        $cache = new PredisCache(parameters: $this->client);
        $result = $cache->getMultiple(['key1', 'key2']);

        self::assertSame(['key1' => 'test-val', 'key2' => 'test-val'], $result);
    }

    /**
     * @throws CacheException
     */
    public function testGetMultipleDefaultAssoc(): void
    {
        $this->client->expects($this->exactly(2))->method('executeCommand')
            ->willReturnCallback(function (CommandInterface $command) {
                self::assertInstanceOf(GET::class, $command);
                return null;
            });

        $cache = new PredisCache(parameters: $this->client);
        $result = $cache->getMultiple(['key1', 'key2'], ['key1' => 'hello1', 'key2' => 'hello2']);

        self::assertSame(['key1' => 'hello1', 'key2' => 'hello2'], $result);
    }

    /**
     * @throws CacheException
     */
    public function testGetMultipleDefault(): void
    {
        $this->client->expects($this->exactly(2))->method('executeCommand')
            ->willReturnCallback(function (CommandInterface $command) {
                self::assertInstanceOf(GET::class, $command);
                return null;
            });

        $cache = new PredisCache(parameters: $this->client);
        $result = $cache->getMultiple(['key1', 'key2'], 'hello');

        self::assertSame(['key1' => 'hello', 'key2' => 'hello'], $result);
    }

    /**
     * @throws CacheException
     */
    public function testSetMultiple(): void
    {
        $i = 0;
        $this->client->expects($this->exactly(2))->method('executeCommand')
            ->willReturnCallback(function (CommandInterface $command) use (&$i) {
                self::assertInstanceOf(SET::class, $command);
                $i++;
                if ($i === 1) {
                    self::assertEquals(['key1', serialize('hello1')], $command->getArguments());
                    return new Status('OK');
                }
                self::assertEquals(['key2', serialize('hello2')], $command->getArguments());
                return new Status('OK');
            });

        $cache = new PredisCache(parameters: $this->client);

        $result = $cache->setMultiple(['key1' => 'hello1', 'key2' => 'hello2']);

        self::assertSame(true, $result);
    }

}

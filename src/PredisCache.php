<?php declare(strict_types=1);

namespace Cache;

use Clock\Clock;
use Clock\ClockExceptionInterface;
use Clock\ClockInterface;
use DateInterval;
use Predis\Client;
use Predis\ClientInterface;
use Predis\Command\CommandInterface;
use Predis\Command\Redis\DEL;
use Predis\Command\Redis\EXISTS;
use Predis\Command\Redis\GET;
use Predis\Command\Redis\KEYS;
use Predis\Command\Redis\SET;
use Predis\Response\Status;
use Psr\SimpleCache\CacheInterface;
use Throwable;

class PredisCache implements CacheInterface
{
    protected ClientInterface $client;

    public function __construct(
        mixed                           $parameters = null,
        mixed                           $options = null,
        protected DateInterval|int|null $defaultTtl = null,
        protected string                $prefix = '',
        protected ClockInterface        $clock = new Clock
    )
    {
        $this->client = $parameters instanceof ClientInterface ? $parameters : new Client($parameters, $options);
    }

    /**
     * @throws \Psr\SimpleCache\CacheException
     */
    public function has(string $key): bool
    {
        $command = new EXISTS;
        $command->setArguments([$this->prepareKey($key)]);

        $result = $this->executeCommand($command);

        return boolval($result);
    }

    /**
     * @throws \Psr\SimpleCache\CacheException
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $command = new GET;
        $command->setArguments([$this->prepareKey($key)]);

        $result = $this->executeCommand($command);

        if (false === is_null($result)) {
            $result = unserialize($result);
        }

        return $result ?? $default;
    }

    /**
     * @throws \Psr\SimpleCache\CacheException
     */
    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $arguments = [$this->prepareKey($key), is_null($value) ? $value : serialize($value)];

        $ttl = $this->prepareTTL($ttl);

        if (false === is_null($ttl)) {
            $arguments[] = 'EX';
            $arguments[] = $ttl;
        }

        $command = new SET;
        $command->setArguments($arguments);

        $result = $this->executeCommand($command);

        if ($result instanceof Status && strval($result) === 'OK') {
            return true;
        }

        throw new CacheException(strval($result));
    }

    /**
     * @throws \Psr\SimpleCache\CacheException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function delete(string $key): bool
    {
        return $this->deleteMultiple([$key]);
    }

    /**
     * @throws \Psr\SimpleCache\CacheException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function clear(): bool
    {
        $command = new KEYS;
        $command->setArguments([$this->prefix . '*']);

        $keys = $this->executeCommand($command);

        return $this->deleteMultiple($keys);
    }

    /**
     * @throws \Psr\SimpleCache\CacheException
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $return = [];
        foreach ($keys as $key) {
            $defaultValue = (is_array($default) && array_key_exists($key, $default)) ? $default[$key] : $default;
            $return[$key] = $this->get($key, $defaultValue);
        }
        return $return;
    }

    /**
     * @throws \Psr\SimpleCache\CacheException
     */
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        $return = true;
        foreach ($values as $key => $value) {
            $result = $this->set($key, $value, $ttl);
            $return = $return && $result;
        }
        return $return;
    }

    /**
     * @throws \Psr\SimpleCache\CacheException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $arguments = [];
        foreach ($keys as $key) {
            $arguments[] = $this->prepareKey($key);
        }

        if (count($arguments) === 0) {
            return true;
        }

        $command = new DEL;
        $command->setArguments($arguments);

        return boolval($this->executeCommand($command));
    }

    /**
     * @throws \Psr\SimpleCache\CacheException
     */
    protected function executeCommand(CommandInterface $command)
    {
        try {
            return $this->client->executeCommand($command);
        } catch (Throwable $exception) {
            throw new CacheException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @throws \Psr\SimpleCache\CacheException
     */
    protected function prepareTTL(DateInterval|int|null $ttl): ?int
    {
        try {
            if ($ttl instanceof DateInterval) {
                return $this->clock->with(0)->now()->add($ttl)->getTimestamp();
            }

            if (is_null($ttl) && $this->defaultTtl instanceof DateInterval) {
                return $this->clock->with(0)->now()->add($this->defaultTtl)->getTimestamp();
            }
        } catch (ClockExceptionInterface $exception) {
            throw new CacheException($exception->getMessage(), 0, $exception);
        }

        return is_int($ttl) ? $ttl : $this->defaultTtl;
    }

    /**
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function prepareKey(string $key): string
    {
        if ('' === trim($key) || trim($key) !== $key) {
            throw new InvalidArgumentException(sprintf('The key string is not a legal value: "%s".', $key));
        }

        return $this->prefix . $key;
    }
}

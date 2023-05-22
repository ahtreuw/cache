<?php declare(strict_types=1);

namespace Cache;

use Clock\ClockExceptionInterface;
use DateInterval;

trait SimpleCacheTrait
{
    protected DateInterval|int|null $defaultTtl = null;
    protected string $prefix = '';

    /**
     * @throws \Psr\SimpleCache\CacheException|ClockExceptionInterface
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
     * @throws ClockExceptionInterface
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
        $return = true;
        foreach ($keys as $key) {
            $result = $this->delete($key);
            $return = $return && $result;
        }
        return $return;
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
}
<?php declare(strict_types=1);

namespace Cache;

use Clock\Clock;
use Clock\ClockExceptionInterface;
use DateInterval;
use JetBrains\PhpStorm\ArrayShape;
use Psr\Clock\ClockInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

class InMemoryCache implements CacheInterface
{
    use SimpleCacheTrait;

    #[ArrayShape(['exp' => "int", 'val' => "mixed|null"])]
    private array $values = [];

    public function __construct(
        private ClockInterface $clock = new Clock
    )
    {
    }

    /**
     * @throws InvalidArgumentException
     * @throws ClockExceptionInterface
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key = $this->prepareKey($key), $this->values) === false) {
            return $default;
        }
        if ($this->isExpired($this->values[$key]['exp'])) {
            $this->delete($key);
            return null;
        }
        return $this->values[$key]['val'];
    }

    /**
     * @throws \Psr\SimpleCache\CacheException
     * @throws ClockExceptionInterface
     */
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $key = $this->prepareKey($key);
        $ttl = $this->prepareTTL($ttl);

        if ($this->isExpired($ttl)) {
            $this->delete($key);
            return true;
        }

        $this->values[$key] = ['val' => $value, 'exp' => $ttl];
        return true;
    }

    public function delete(string $key): bool
    {
        if (array_key_exists($key = $this->prepareKey($key), $this->values)) {
            unset($this->values[$key]);
        }
        return true;
    }

    public function clear(): bool
    {
        $this->values = [];
    }

    public function has(string $key): bool
    {
        return array_key_exists($this->prepareKey($key), $this->values);
    }

    /**
     * @throws ClockExceptionInterface
     */
    private function isExpired(?int $expire): bool
    {
        if (is_null($expire)) {
            return false;
        }
        return $this->clock->now()->getTimestamp() <= $expire;
    }
}
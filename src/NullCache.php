<?php declare(strict_types=1);

namespace Cache;

use DateInterval;
use JetBrains\PhpStorm\Pure;
use Psr\SimpleCache\CacheInterface;

class NullCache implements CacheInterface
{
    use SimpleCacheTrait;

    public function __construct(
        private bool $returnOnSet = false,
        private bool $returnOnDelete = false,
        private bool $returnOnClear = false,
        private bool $returnOnHas = false,
    )
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        return $this->returnOnSet;
    }

    public function delete(string $key): bool
    {
        return $this->returnOnDelete;
    }

    public function clear(): bool
    {
        return $this->returnOnClear;
    }

    public function has(string $key): bool
    {
        return $this->returnOnHas;
    }
}

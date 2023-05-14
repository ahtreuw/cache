<?php declare(strict_types=1);

namespace Cache;

use DateInterval;
use JetBrains\PhpStorm\Pure;
use Psr\SimpleCache\CacheInterface;

class NullCache implements CacheInterface
{
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

    #[Pure] public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $return = [];
        foreach ($keys as $key) {
            $defaultValue = is_array($default) && array_key_exists($key, $default) ? $default[$key] : $default;
            $return[$key] = $this->get($key, $defaultValue);
        }
        return $return;
    }

    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        return $this->returnOnSet;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        return $this->returnOnDelete;
    }
}

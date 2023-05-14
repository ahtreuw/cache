<?php declare(strict_types=1);

namespace Cache;

use DateInterval;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use ReturnTypeWillChange;
use SessionHandlerInterface;

class SessionHandler implements SessionHandlerInterface
{
    public function __construct(
        private CacheInterface   $cache,
        private DateInterval|int $ttl,
        private string           $prefix = 'session:'
    ) {}

    public function register(): bool
    {
        return session_set_save_handler($this, true);
    }

    public function close(): bool
    {
        return true;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function destroy(string $id): bool
    {
        $this->cache->delete($this->prefix . $id);
        return true;
    }

    #[ReturnTypeWillChange]
    public function gc(int $max_lifetime): int|bool
    {
        return true;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function read(string $id): string|false
    {
        return $this->cache->get($this->prefix . $id) ?: '';
    }

    /**
     * @throws InvalidArgumentException
     */
    public function write(string $id, string $data): bool
    {
        return $this->cache->set($this->prefix . $id, $data, $this->ttl);
    }
}

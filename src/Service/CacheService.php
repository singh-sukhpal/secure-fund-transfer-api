<?php

namespace App\Service;

use App\Cache\CacheKey;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class CacheService
{
    public function __construct(private CacheInterface $cache)
    {
    }

    /**
     * Generic get with a factory callback and TTL (seconds).
     * Callback receives the ItemInterface and should return the cached value.
     *
     * @return mixed
     */
    public function get(string $cacheKey, callable $factory, int $ttl = 60)
    {
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($factory, $ttl) {
            $item->expiresAfter($ttl);
            return $factory($item);
        });
    }

    public function set(string $cacheKey, $value, int $ttl = 60): void
    {
        $this->cache->get($cacheKey, function (ItemInterface $item) use ($value, $ttl) {
            $item->expiresAfter($ttl);
            return $value;
        });
    }

    public function delete(string $cacheKey): void
    {
        $this->cache->delete($cacheKey);
    }

    // Transfer-specific helpers

    public function getTransferStatus(string $referenceId, callable $factory, int $ttl = 60)
    {
        return $this->get(CacheKey::transferStatus($referenceId), $factory, $ttl);
    }

    public function setTransferStatus(string $referenceId, $value, int $ttl = 60): void
    {
        $this->set(CacheKey::transferStatus($referenceId), $value, $ttl);
    }

    public function deleteTransferStatus(string $referenceId): void
    {
        $this->delete(CacheKey::transferStatus($referenceId));
    }


}
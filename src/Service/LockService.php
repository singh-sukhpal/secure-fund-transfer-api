<?php

namespace App\Service;

use App\Cache\CacheKey;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

final class LockService
{
    public function __construct(
        private LockFactory $lockFactory,
        private LoggerInterface $logger
    ) {}

    /**
     * Create a transfer lock for given referenceId (and optional account ids).
     * Returns a LockInterface; caller must acquire()/release() (or use tryAcquire wrapper below).
     */
    public function createTransferLock(string $referenceId, ?int $fromId = null, ?int $toId = null, int $ttl = 60): LockInterface
    {
        $key = CacheKey::transferLock($referenceId, $fromId, $toId);
        return $this->lockFactory->createLock($key, $ttl);
    }

    /**
     * Try acquire lock; returns acquired LockInterface or null if acquire failed.
     * Useful to centralize logging / metrics for lock contention.
     */
    public function acquireTransferLock(string $referenceId, ?int $fromId = null, ?int $toId = null, int $ttl = 60): ?LockInterface
    {
        $lock = $this->createTransferLock($referenceId, $fromId, $toId, $ttl);
        if (!$lock->acquire()) {
            $this->logger->warning('Transfer lock busy', ['referenceId' => $referenceId]);
            return null;
        }
        return $lock;
    }
}
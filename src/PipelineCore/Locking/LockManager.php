<?php

declare(strict_types=1);

namespace Polymorph\Platform\PipelineCore\Locking;

interface LockManager
{
    /**
     * Acquire lock for the key within current transaction
     *
     * @throws LockException if cannot acquire
     */
    public function acquireLock(LockKey $key): void;

    /**
     * Try acquiring lock within timeout budget.
     */
    public function tryAcquire(LockKey $key, int $timeoutMs): bool;
}

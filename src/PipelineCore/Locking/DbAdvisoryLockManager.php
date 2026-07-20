<?php

declare(strict_types=1);

namespace Polymorph\Platform\PipelineCore\Locking;

use Illuminate\Support\Facades\DB;
use Polymorph\Platform\PipelineCore\Observability\PipelineLogger;

class DbAdvisoryLockManager implements LockManager
{
    public function __construct(
        private readonly PipelineLogger $logger,
    ) {}

    public function acquireLock(LockKey $key): void
    {
        $lockId = $this->hashLockKey($key);

        // pg_advisory_xact_lock блокирует до конца транзакции
        DB::select('SELECT pg_advisory_xact_lock(?)', [$lockId]);

        $this->logger->debug('PostgreSQL advisory lock acquired', [
            'lock_key' => $key->toString(),
            'lock_id' => $lockId,
        ]);
    }

    public function tryAcquire(LockKey $key, int $timeoutMs): bool
    {
        if ($timeoutMs < 0) {
            throw new LockException('Lock timeout must be greater than or equal to zero');
        }

        $lockId = $this->hashLockKey($key);
        $deadline = microtime(true) + ($timeoutMs / 1000);

        do {
            $row = DB::selectOne('SELECT pg_try_advisory_xact_lock(?) AS acquired', [$lockId]);
            $acquired = (bool) (($row->acquired ?? false));

            if ($acquired) {
                $this->logger->debug('PostgreSQL advisory lock acquired (tryAcquire)', [
                    'lock_key' => $key->toString(),
                    'lock_id' => $lockId,
                    'timeout_ms' => $timeoutMs,
                ]);

                return true;
            }

            if ($timeoutMs === 0) {
                break;
            }

            usleep(10_000);
        } while (microtime(true) < $deadline);

        $this->logger->warning('Failed to acquire PostgreSQL advisory lock within timeout', [
            'lock_key' => $key->toString(),
            'lock_id' => $lockId,
            'timeout_ms' => $timeoutMs,
        ]);

        return false;
    }

    /**
     * Hash lock key to integer (для PostgreSQL advisory locks)
     */
    private function hashLockKey(LockKey $key): int
    {
        $hashBytes = hash('sha256', $key->toString(), true);
        $firstEightBytes = substr($hashBytes, 0, 8);
        $unpacked = unpack('qlock', $firstEightBytes);

        if (!is_array($unpacked) || !isset($unpacked['lock'])) {
            throw new LockException('Failed to hash advisory lock key');
        }

        return $unpacked['lock'];
    }

}

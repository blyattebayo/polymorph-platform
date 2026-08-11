<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Infrastructure\Services\Shared;

use Closure;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;
use Throwable;

final readonly class BestEffortAudit
{
    public function __construct(private AppLogger $logger) {}

    /** @param array<string, mixed> $context */
    public function record(string $operation, Closure $audit, array $context = []): void
    {
        try {
            $audit();
        } catch (Throwable $exception) {
            $this->logger->error('Authentication audit failed.', [
                'operation' => $operation,
                'exception' => $exception,
                ...$context,
            ]);
        }
    }
}

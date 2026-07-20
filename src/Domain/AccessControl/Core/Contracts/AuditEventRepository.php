<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Core\Contracts;

interface AuditEventRepository
{
    /**
     * @param array<string, mixed> $payload
     */
    public function append(
        string $eventType,
        string $actor,
        array $payload,
        ?string $targetSubject = null,
        ?int $policyId = null,
    ): void;
}
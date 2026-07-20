<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Infrastructure\Repositories;

use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AuditEventRepository;
use Polymorph\Platform\Domain\AccessControl\Core\Models\AuditEvent;

final class EloquentAuditEventRepository implements AuditEventRepository
{
    public function append(
        string $eventType,
        string $actor,
        array $payload,
        ?string $targetSubject = null,
        ?int $policyId = null,
    ): void {
        AuditEvent::query()->create([
            'event_type' => $eventType,
            'actor' => $actor,
            'target_subject' => $targetSubject,
            'policy_id' => $policyId,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }
}
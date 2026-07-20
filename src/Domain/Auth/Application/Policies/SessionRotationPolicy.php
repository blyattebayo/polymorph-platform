<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\Policies;

use Carbon\CarbonImmutable;

final class SessionRotationPolicy
{
    public function decide(?object $session): SessionRotationDecision
    {
        if ($session === null) {
            return SessionRotationDecision::Missing;
        }

        if ($session->revoked_at !== null || $session->rotated_at !== null) {
            return SessionRotationDecision::Reused;
        }

        if (CarbonImmutable::parse((string) $session->expires_at)->isPast()) {
            return SessionRotationDecision::Expired;
        }

        $absoluteExpiresAt = $session->absolute_expires_at ?? null;
        if ($absoluteExpiresAt !== null && CarbonImmutable::parse((string) $absoluteExpiresAt)->isPast()) {
            return SessionRotationDecision::Expired;
        }

        return SessionRotationDecision::Allowed;
    }
}

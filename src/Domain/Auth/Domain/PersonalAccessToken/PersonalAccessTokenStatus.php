<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken;

use DateTimeImmutable;

enum PersonalAccessTokenStatus: string
{
    case NotYetValid = 'not_yet_valid';
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public static function at(
        DateTimeImmutable $issuedAt,
        DateTimeImmutable $expiresAt,
        ?DateTimeImmutable $revokedAt,
        DateTimeImmutable $now,
    ): self {
        return match (true) {
            $revokedAt !== null => self::Revoked,
            $now < $issuedAt => self::NotYetValid,
            $now >= $expiresAt => self::Expired,
            default => self::Active,
        };
    }
}

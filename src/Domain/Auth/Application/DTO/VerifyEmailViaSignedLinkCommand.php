<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\DTO;

final readonly class VerifyEmailViaSignedLinkCommand
{
    public function __construct(
        public int $userId,
        public string $emailHash,
        public bool $signatureValid,
    ) {}
}

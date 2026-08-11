<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Auth\Application\PersonalAccessToken;

use Polymorph\Platform\Domain\Auth\Domain\PersonalAccessToken\PersonalAccessTokenInvariantViolation;
use Polymorph\Platform\SharedKernel\Contracts\DomainErrorDescriptor;
use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ConvertsToErrorPayload;
use Polymorph\Platform\Support\Errors\ErrorCode;
use RuntimeException;

final class InvalidPersonalAccessTokenDefinition extends RuntimeException implements DomainErrorDescriptor, ErrorConvertible
{
    use ConvertsToErrorPayload;

    private function __construct(
        string $message,
        private readonly string $reason,
    ) {
        parent::__construct($message);
    }

    public static function fromInvariant(PersonalAccessTokenInvariantViolation $violation): self
    {
        return new self($violation->getMessage(), $violation->invariant->value);
    }

    /** @param non-empty-list<array{resource: string, action: string}> $unknown */
    public static function unknownScopes(array $unknown): self
    {
        $labels = array_map(
            static fn (array $scope): string => $scope['resource'].'/'.$scope['action'],
            $unknown,
        );

        return new self(
            'Unknown personal access token scopes: '.implode(', ', $labels).'.',
            'unknown_scopes',
        );
    }

    public static function expirationExceedsMaximum(): self
    {
        return new self(
            'Personal access token expiration exceeds the 365-day maximum lifetime.',
            'expiration_exceeds_maximum',
        );
    }

    public function errorCode(): ErrorCode
    {
        return ErrorCode::VALIDATION_ERROR;
    }

    /** @return array{reason: string} */
    public function errorMeta(): array
    {
        return ['reason' => $this->reason];
    }
}

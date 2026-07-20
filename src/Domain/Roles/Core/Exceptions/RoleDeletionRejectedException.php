<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Roles\Core\Exceptions;

use Polymorph\Platform\SharedKernel\Contracts\ErrorConvertible;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Polymorph\Platform\Support\Errors\ErrorPayload;
use RuntimeException;

final class RoleDeletionRejectedException extends RuntimeException implements ErrorConvertible
{
    /**
     * @param  array<string, mixed>  $meta
     */
    private function __construct(
        string $message,
        private readonly array $meta = [],
    ) {
        parent::__construct($message);
    }

    /**
     * @param  array<string, mixed>  $violation
     */
    public static function fromViolation(array $violation): self
    {
        return new self(
            (string) ($violation['detail'] ?? 'Role cannot be deleted.'),
            array_merge(['resource' => 'role'], $violation),
        );
    }

    public function toError(ErrorFactory $factory): ErrorPayload
    {
        return $factory->for(ErrorCode::CONFLICT)
            ->detail($this->getMessage())
            ->meta($this->meta)
            ->build();
    }
}

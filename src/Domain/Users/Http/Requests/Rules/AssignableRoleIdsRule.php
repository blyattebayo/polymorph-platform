<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Users\Http\Requests\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Polymorph\Platform\Domain\Roles\Core\Contracts\UserRoleAssignmentGuard;
use Polymorph\Platform\Domain\Roles\Core\Exceptions\RoleNotAssignableException;
use Polymorph\Platform\Domain\Users\Support\RoleIdsNormalizer;

final class AssignableRoleIdsRule implements ValidationRule
{
    public function __construct(
        private readonly UserRoleAssignmentGuard $assignmentGuard,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || $value === []) {
            return;
        }

        $normalizedRoleIds = RoleIdsNormalizer::normalize($value);

        try {
            $this->assignmentGuard->assertAssignable($normalizedRoleIds);
        } catch (RoleNotAssignableException $exception) {
            $fail($exception->getMessage());
        }
    }
}

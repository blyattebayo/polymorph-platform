<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Services;

use Illuminate\Support\Collection;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Effect;
use Polymorph\Platform\Domain\AccessControl\Infrastructure\Repositories\EloquentAssignmentRepository;
use Polymorph\Platform\Domain\Auth\Application\Authentication\AuthenticationContext;
use Polymorph\Platform\Domain\Roles\Core\Contracts\RoleAssignmentRepository;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\SharedKernel\Access\AccessCheck;
use Polymorph\Platform\SharedKernel\Access\AccessGate;
use Polymorph\Platform\SharedKernel\Access\ResourceRef;

/**
 * Sole owner of authorization decisions, evaluated directly from canonical policies.
 */
final class PolicyAccessGate implements AccessGate
{
    public function __construct(
        private readonly AuthenticationContext $auth,
        private readonly RoleAssignmentRepository $roleAssignments,
        private readonly EloquentAssignmentRepository $assignments,
    ) {}

    public function allows(?User $user, ResourceRef $resource, string $action): bool
    {
        if (! $user instanceof User || (int) $user->id <= 0) {
            return false;
        }

        $action = strtolower(trim($action));
        if ($action === '') {
            return false;
        }

        return $this->evaluate($this->rulesFor($user), $resource->value, $action);
    }

    public function currentUserAllows(ResourceRef $resource, string $action): bool
    {
        return $this->allows($this->auth->user(), $resource, $action);
    }

    public function allowsEach(?User $user, array $checks): array
    {
        if ($checks === []) {
            return [];
        }

        if (! $user instanceof User || (int) $user->id <= 0) {
            return array_map(static fn (): bool => false, $checks);
        }

        $rules = $this->rulesFor($user);

        return array_map(
            fn (AccessCheck $check): bool => $this->evaluate(
                $rules,
                $check->resource->value,
                strtolower(trim($check->action)),
            ),
            $checks,
        );
    }

    /**
     * @return Collection<int, object{id:int,resource_pattern:string,action:string,effect:string}>
     */
    private function rulesFor(User $user): Collection
    {
        $userId = (int) $user->id;

        return $this->assignments->policyRulesForSubjects([
            "user:{$userId}",
            ...array_map(
                static fn (string $role): string => "role:{$role}",
                $this->roleAssignments->roleCodesForUser($userId),
            ),
        ]);
    }

    /**
     * @param  Collection<int, object{id:int,resource_pattern:string,action:string,effect:string}>  $rules
     */
    private function evaluate(Collection $rules, string $resource, string $action): bool
    {
        if ($resource === '' || $action === '') {
            return false;
        }

        $allowed = false;

        foreach ($rules as $rule) {
            $ruleAction = strtolower(trim((string) $rule->action));
            if ($ruleAction !== '*' && $ruleAction !== $action) {
                continue;
            }

            if (! $this->resourceMatches((string) $rule->resource_pattern, $resource)) {
                continue;
            }

            $effect = strtolower(trim((string) $rule->effect));
            if ($effect === Effect::DENY->value) {
                return false;
            }

            if ($effect === Effect::ALLOW->value) {
                $allowed = true;
            }
        }

        return $allowed;
    }

    private function resourceMatches(string $pattern, string $resource): bool
    {
        $pattern = trim($pattern);
        $resource = trim($resource);

        return $pattern !== ''
            && $resource !== ''
            && ($pattern === '*' || $pattern === $resource || str_starts_with($resource, $pattern.'.'));
    }
}

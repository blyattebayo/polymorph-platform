<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Services;

use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AccessSubjectProvider;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\PolicyRuntime;
use Polymorph\Platform\Domain\Auth\Application\Authentication\AuthenticationContext;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\SharedKernel\Access\AccessCheck;
use Polymorph\Platform\SharedKernel\Access\AccessGate;
use Polymorph\Platform\SharedKernel\Access\ResourceRef;

/**
 * Единственное место, где «тройка» (резолвер актора, субъекты, рантайм политик)
 * склеивается в решение. Биндинг scoped: субъекты кэшируются на запрос.
 *
 * Решение определяется политиками субъекта. Transport credential только устанавливает
 * текущего актора и не содержит второго параллельного языка прав.
 */
final class PolicyRuntimeAccessGate implements AccessGate
{
    public function __construct(
        private readonly PolicyRuntime $runtime,
        private readonly AccessSubjectProvider $subjectProvider,
        private readonly AuthenticationContext $auth,
    ) {}

    public function allows(?User $user, ResourceRef $resource, string $action): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        $action = trim($action);

        return $this->runtime->allows($this->subjectProvider->for($user), $resource->value, $action);
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

        if (! $user instanceof User) {
            return array_map(static fn (): bool => false, $checks);
        }

        $decisions = $this->runtime->batchEvaluate(
            $this->subjectProvider->for($user),
            array_map(
                static fn (AccessCheck $check): array => [
                    'resource' => $check->resource->value,
                    'action' => trim($check->action),
                ],
                $checks,
            ),
        );

        return array_map(
            static fn ($decision): bool => $decision->allowed(),
            $decisions,
        );
    }
}

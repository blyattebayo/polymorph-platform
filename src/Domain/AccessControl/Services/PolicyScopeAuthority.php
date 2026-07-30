<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Services;

use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AssignmentRepository;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\PolicyRepository;
use Polymorph\Platform\Domain\AccessControl\Core\Models\Policy;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Subject;
use Polymorph\Platform\SharedKernel\Access\AccessGate;
use Polymorph\Platform\SharedKernel\Access\ResourceRef;
use Polymorph\Platform\SharedKernel\Identity\CurrentActorResolver;
use Polymorph\Platform\SharedKernel\Identity\UserIdentity;

/**
 * Правило «нельзя распоряжаться правами шире собственных» для HTTP-админки ACL.
 *
 * Капабилити policy.manage/policy.assign дают доступ к ЭНДПОИНТАМ, но не должны
 * давать власть над произвольным содержимым политик: до этого guard'а носитель
 * policy.assign мог назначить себе wildcard-политику ('*','*') и стать полным
 * суперадмином, а носитель policy.manage — переписать уже назначенную политику
 * в wildcard или удалить wildcard у role:system.admin. Правило одно на все
 * мутации: актор вправе создавать/менять/удалять/назначать/снимать только
 * политику, чей (resource_pattern, action) он сам покрывает по обычной
 * ACL-цепочке. Wildcard-держатель (system.admin) покрывает всё.
 *
 * Guard живёт на HTTP-границе (контроллеры ACL), а не внутри
 * AccessControlAdminService: сидеры, консоль и установка расширений работают
 * без HTTP-актора и остаются системными путями.
 */
final class PolicyScopeAuthority
{
    public function __construct(
        private readonly AccessGate $gate,
        private readonly CurrentActorResolver $currentActor,
        private readonly PolicyRepository $policies,
        private readonly AssignmentRepository $assignments,
    ) {}

    public function assertCanManageScope(string $resourcePattern, string $action): void
    {
        $actor = $this->currentActor->actor();

        // Fail-closed: без актора этот путь недостижим штатно (маршруты за
        // RequireCapability), значит отсутствие актора — отказ, а не пропуск.
        if (! $actor instanceof UserIdentity) {
            throw AccessControlApplicationException::forbidden(
                'Managing policies requires an authenticated actor.',
            );
        }

        // resource_pattern проверяется как ресурс: покрытие паттерна актором
        // означает покрытие всего поддерева, которое этот паттерн раздаёт.
        // Wildcard-паттерн ('*') матчится только собственным wildcard'ом актора,
        // wildcard-action — только action '*'.
        $allowed = $this->gate->allows($actor, ResourceRef::fromString($resourcePattern), $action);

        if (! $allowed) {
            throw AccessControlApplicationException::forbidden(sprintf(
                'You cannot manage a policy on "%s/%s": your own access does not cover this scope.',
                $resourcePattern,
                $action,
            ));
        }
    }

    /**
     * @param  list<int>  $policyIds
     */
    public function assertCanManagePolicies(array $policyIds): void
    {
        foreach (array_values(array_unique($policyIds)) as $policyId) {
            $policy = $this->policies->find((int) $policyId);

            // Несуществующая политика — не наша забота: сервис ответит 404/422
            // своим ходом, guard не должен маскировать это в 403.
            if (! $policy instanceof Policy) {
                continue;
            }

            $this->assertCanManageScope((string) $policy->resource_pattern, (string) $policy->action);
        }
    }

    /**
     * Для полной замены набора политик субъекта проверяется ДИФФ, а не весь
     * список: неизменяемая часть набора может содержать политики шире прав
     * актора (их назначил кто-то более привилегированный), и это не повод
     * запрещать актору управлять своей частью.
     *
     * @param  list<int>  $requestedPolicyIds
     */
    public function assertCanReplaceSubjectPolicies(Subject $subject, array $requestedPolicyIds): void
    {
        $requested = array_values(array_unique(array_map(static fn (mixed $id): int => (int) $id, $requestedPolicyIds)));

        $current = $this->assignments->policyIdsForSubject($subject)
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $changed = [
            ...array_diff($requested, $current),
            ...array_diff($current, $requested),
        ];

        $this->assertCanManagePolicies(array_values($changed));
    }
}

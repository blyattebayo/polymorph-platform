<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Services;

use Polymorph\Platform\Domain\AccessControl\Core\Models\Policy;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Effect;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Subject;
use Polymorph\Platform\Domain\AccessControl\Infrastructure\Repositories\EloquentAssignmentRepository;
use Polymorph\Platform\Domain\AccessControl\Infrastructure\Repositories\EloquentPolicyRepository;
use Polymorph\Platform\Domain\Auth\Application\Authentication\AuthenticationContext;
use Polymorph\Platform\Domain\Users\Core\Models\User;
use Polymorph\Platform\SharedKernel\Access\AccessGate;
use Polymorph\Platform\SharedKernel\Access\ResourceRef;

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
        private readonly AuthenticationContext $auth,
        private readonly EloquentPolicyRepository $policies,
        private readonly EloquentAssignmentRepository $assignments,
    ) {}

    public function assertCanManageScope(string $resourcePattern, string $action): void
    {
        $actor = $this->requireUser();

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
     * Нельзя УРЕЗАТЬ права субъекта, который привилегированнее актора.
     *
     * Проверка скоупа отвечает только на вопрос «не шире ли политика моих прав»
     * и ничего не знает о том, КОМУ её вешают. С deny-overrides этого мало:
     * политику {acl.policy, manage, deny} актор покрывал собственным
     * acl.policy/manage, вешал на role:system.admin и отбирал у админов
     * управление ACL, сохраняя своё. Симметрично работало снятие allow.
     *
     * Ограничение намеренно узкое — только на операции, снижающие доступ
     * (назначить deny, снять allow). Расширять права субъекта, который в чём-то
     * привилегированнее актора, по-прежнему можно: иначе управление «своей»
     * частью набора блокировала бы чужая политика, назначенная кем-то выше
     * (см. assertCanReplaceSubjectPolicies).
     */
    public function assertCanReduceSubjectAccess(Subject $subject): void
    {
        $actor = $this->requireUser();

        foreach ($this->subjectPolicies($subject) as $policy) {
            $resourcePattern = (string) $policy->resource_pattern;
            $action = (string) $policy->action;

            if ($this->gate->allows($actor, ResourceRef::fromString($resourcePattern), $action)) {
                continue;
            }

            throw AccessControlApplicationException::forbidden(sprintf(
                'You cannot reduce access of "%s": it holds "%s/%s", which your own access does not cover.',
                (string) $subject,
                $resourcePattern,
                $action,
            ));
        }
    }

    /**
     * Правка и удаление политики бьют по всем, кому она уже назначена: сменив
     * effect или сузив паттерн, назначенную allow-политику превращают в
     * инструмент против привилегированного субъекта.
     */
    public function assertCanRewritePolicyForSubjects(int $policyId): void
    {
        foreach ($this->assignments->subjectsByPolicy($policyId) as $subject) {
            $this->assertCanReduceSubjectAccess(Subject::fromString((string) $subject));
        }
    }

    private function isDenyPolicy(int $policyId): bool
    {
        $policy = $this->policies->find($policyId);

        return $policy instanceof Policy
            && strtolower(trim((string) $policy->effect)) === Effect::DENY->value;
    }

    /**
     * Роль — контейнер политик, поэтому выдать или снять её равносильно тому,
     * чтобы назначить или снять субъекту весь её набор. Дверь через
     * ac_assignments закрыта {@see self::assertCanReplaceSubjectPolicies()},
     * эта закрывает вторую дверь — user_role_assignments.
     */
    public function assertCanManageRolePolicies(string $roleCode): void
    {
        $policyIds = $this->assignments->policyIdsForSubject(Subject::role($roleCode))
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $this->assertCanManagePolicies($policyIds);
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

        $added = array_values(array_diff($requested, $current));
        $removed = array_values(array_diff($current, $requested));

        $this->assertCanManagePolicies([...$added, ...$removed]);

        // Полная замена урезает доступ, если добавляет deny или снимает allow.
        $reduces = array_filter($added, fn (int $id): bool => $this->isDenyPolicy($id)) !== []
            || array_filter($removed, fn (int $id): bool => ! $this->isDenyPolicy($id)) !== [];

        if ($reduces) {
            $this->assertCanReduceSubjectAccess($subject);
        }
    }

    private function requireUser(): User
    {
        $actor = $this->auth->user();

        // Fail-closed: без актора этот путь недостижим штатно (маршруты за
        // RequireCapability), значит отсутствие актора — отказ, а не пропуск.
        if (! $actor instanceof User) {
            throw AccessControlApplicationException::forbidden(
                'Managing policies requires an authenticated actor.',
            );
        }

        return $actor;
    }

    /**
     * @return list<Policy>
     */
    private function subjectPolicies(Subject $subject): array
    {
        $policies = [];

        foreach ($this->assignments->policyIdsForSubject($subject)->unique() as $policyId) {
            $policy = $this->policies->find((int) $policyId);

            if ($policy instanceof Policy) {
                $policies[] = $policy;
            }
        }

        return $policies;
    }
}

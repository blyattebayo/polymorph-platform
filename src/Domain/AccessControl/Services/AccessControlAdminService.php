<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Services;

use Closure;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AccessControlAdministration;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\ActionRegistry;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AssignmentRepository;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AuditActorResolver;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AuditEventRepository;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\PolicyCompilationService;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\PolicyRepository;
use Polymorph\Platform\Domain\AccessControl\Core\Models\Assignment;
use Polymorph\Platform\Domain\AccessControl\Core\Models\Policy;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\PolicyData;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Subject;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

final class AccessControlAdminService implements AccessControlAdministration
{
    public function __construct(
        private readonly PolicyRepository $policies,
        private readonly AssignmentRepository $assignments,
        private readonly PolicyCompilationService $compiler,
        private readonly AuditEventRepository $auditEvents,
        private readonly AuditActorResolver $auditActorResolver,
        private readonly ActionRegistry $actionRegistry,
    ) {}

    public function createPolicy(array $data): Policy
    {
        return DB::transaction(function () use ($data): Policy {
            $policyData = $this->policyDataFromInput($data);
            $this->ensureNoDuplicatePolicy($this->policies->findDuplicate($policyData));

            return $this->createNormalizedPolicy($policyData);
        });
    }

    public function ensurePolicy(array $data): Policy
    {
        return DB::transaction(function () use ($data): Policy {
            $policyData = $this->policyDataFromInput($data);

            $existing = $this->policies->findDuplicate($policyData);
            if ($existing !== null) {
                return $existing;
            }

            return $this->createNormalizedPolicy($policyData);
        });
    }

    private function createNormalizedPolicy(PolicyData $policyData): Policy
    {
        $policy = $this->policies->create($policyData);
        $actor = $this->auditActorResolver->resolve();

        $this->auditEvents->append(
            'policy.created',
            $actor,
            $policyData->toAuditPayload(),
            policyId: (int) $policy->id,
        );

        return $policy;
    }

    public function updatePolicy(int $id, array $data): Policy
    {
        return DB::transaction(function () use ($id, $data): Policy {
            $policy = $this->getPolicyOrFail($id);
            $policyData = $this->policyDataFromInput($data);
            $this->ensureNoDuplicatePolicy($this->policies->findDuplicate($policyData), $id);
            $actor = $this->auditActorResolver->resolve();

            $updated = $this->policies->update($policy, $policyData);
            $this->scheduleAffectedSubjectsRecompile($id);

            $this->auditEvents->append(
                'policy.updated',
                $actor,
                $policyData->toAuditPayload(),
                policyId: $id,
            );

            return $updated;
        });
    }

    public function deletePolicy(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $subjects = $this->deletePolicyRecordWithAudit($id);
            $this->scheduleSubjectsRecompile($subjects);
        });
    }

    /**
     * Удалить политику с её назначениями и аудит-событием БЕЗ планирования
     * пересчёта — возвращает затронутых субъектов, чтобы вызывающий собрал их в
     * один пересчёт. Должен вызываться внутри транзакции.
     *
     * @return list<Subject>
     */
    private function deletePolicyRecordWithAudit(int $id): array
    {
        $policy = $this->getPolicyOrFail($id);
        $subjects = $this->assignedSubjects($id);
        $actor = $this->auditActorResolver->resolve();

        $this->auditEvents->append(
            'policy.deleted',
            $actor,
            ['subjects' => $this->subjectKeys($subjects)],
            policyId: $id,
        );

        $this->policies->delete($policy);

        return $subjects;
    }

    public function revokeResource(
        array $resourcePatterns,
        string $action = CapabilityCatalog::ACTION_ACCESS,
        string $effect = CapabilityCatalog::EFFECT_ALLOW,
    ): void {
        $policyIds = $this->policies->idsForResources($resourcePatterns, $action, $effect);
        if ($policyIds === []) {
            return;
        }

        // Одна транзакция на весь отзыв; пересчёт субъектов планируется ОДИН раз
        // на весь набор (а не на каждую политику), иначе субъект с грантами на N
        // удаляемых ресурсов рекомпилировался бы N раз.
        DB::transaction(function () use ($policyIds): void {
            $affected = [];
            foreach ($policyIds as $policyId) {
                foreach ($this->deletePolicyRecordWithAudit($policyId) as $subject) {
                    $affected[(string) $subject] = $subject;
                }
            }

            $this->scheduleSubjectsRecompile(array_values($affected));
        });
    }

    public function assign(int $policyId, Subject $subject): Assignment
    {
        return DB::transaction(function () use ($policyId, $subject): Assignment {
            $this->getPolicyOrFail($policyId);
            $actor = $this->auditActorResolver->resolve();

            $assignment = $this->assignments->upsert($policyId, $subject);
            $subjectKey = (string) $subject;

            $this->scheduleSubjectRecompile($subject);
            $this->auditEvents->append('assignment.created', $actor, ['assignment_id' => (int) $assignment->id], $subjectKey, $policyId);

            return $assignment;
        });
    }

    public function unassign(int $policyId, int $assignmentId): void
    {
        $assignment = $this->assignments->find($assignmentId);
        if ($assignment === null || (int) $assignment->policy_id !== $policyId) {
            throw AccessControlApplicationException::notFound('Assignment not found.');
        }

        $subject = $this->subjectFromStoredValue((string) $assignment->subject);

        DB::transaction(function () use ($policyId, $assignmentId, $assignment, $subject): void {
            $actor = $this->auditActorResolver->resolve();

            $this->assignments->delete($assignment);
            $this->scheduleSubjectRecompile($subject);
            $this->auditEvents->append('assignment.deleted', $actor, ['assignment_id' => $assignmentId], (string) $subject, $policyId);
        });
    }

    public function setSubjectPolicies(Subject $subject, array $policyIds): void
    {
        $normalizedPolicyIds = $this->normalizePolicyIds($policyIds, allowEmpty: true);

        DB::transaction(function () use ($subject, $normalizedPolicyIds): void {
            $actor = $this->auditActorResolver->resolve();

            if ($normalizedPolicyIds !== []) {
                $this->assertAllPoliciesExist($normalizedPolicyIds);
            }

            $currentPolicyIds = $this->assignments->policyIdsForSubject($subject)
                ->map(static fn (mixed $id): int => (int) $id)
                ->unique()
                ->values()
                ->all();

            $policyIdsToDelete = array_values(array_diff($currentPolicyIds, $normalizedPolicyIds));
            $policyIdsToCreate = array_values(array_diff($normalizedPolicyIds, $currentPolicyIds));

            $this->assignments->deleteManyForSubject($subject, $policyIdsToDelete);
            $this->assignments->upsertManyForSubject($subject, $policyIdsToCreate);

            if ($policyIdsToDelete === [] && $policyIdsToCreate === []) {
                return;
            }

            $this->scheduleSubjectRecompile($subject);
            $this->auditEvents->append('assignment.subject_set', $actor, [
                'policy_ids' => $normalizedPolicyIds,
                'added_policy_ids' => $policyIdsToCreate,
                'removed_policy_ids' => $policyIdsToDelete,
            ], (string) $subject);
        });
    }

    private function getPolicyOrFail(int $id): Policy
    {
        $policy = $this->policies->find($id);
        if ($policy === null) {
            throw AccessControlApplicationException::notFound('Policy not found.');
        }

        return $policy;
    }

    private function ensureNoDuplicatePolicy(?Policy $duplicate, ?int $currentPolicyId = null): void
    {
        if ($duplicate === null) {
            return;
        }

        if ($currentPolicyId !== null && (int) $duplicate->id === $currentPolicyId) {
            return;
        }

        throw AccessControlApplicationException::validation('Equivalent policy already exists.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function policyDataFromInput(array $data): PolicyData
    {
        try {
            return PolicyData::fromInput($data, $this->actionRegistry);
        } catch (InvalidArgumentException $exception) {
            throw AccessControlApplicationException::validation($exception->getMessage());
        }
    }

    private function subjectFromStoredValue(string $subject): Subject
    {
        try {
            return Subject::fromString($subject);
        } catch (InvalidArgumentException $exception) {
            throw AccessControlApplicationException::validation($exception->getMessage());
        }
    }

    /**
     * @param  list<Subject>  $subjects
     * @return list<string>
     */
    private function subjectKeys(array $subjects): array
    {
        return array_map(static fn (Subject $subject): string => (string) $subject, $subjects);
    }

    /**
     * @param  list<int>  $policyIds
     * @return list<int>
     */
    private function normalizePolicyIds(array $policyIds, bool $allowEmpty = false): array
    {
        $normalized = array_values(array_unique(array_filter(array_map(static fn (mixed $id): int => (int) $id, $policyIds), static fn (int $id): bool => $id > 0)));
        if (! $allowEmpty && $normalized === []) {
            throw AccessControlApplicationException::validation('policy_ids must contain at least one positive id.');
        }

        return $normalized;
    }

    /**
     * @param  list<int>  $policyIds
     */
    private function assertAllPoliciesExist(array $policyIds): void
    {
        if (array_diff($policyIds, $this->policies->existingIds($policyIds)) !== []) {
            throw AccessControlApplicationException::validation('One or more policy_ids do not exist.');
        }
    }

    /**
     * @return list<Subject>
     */
    private function assignedSubjects(int $policyId): array
    {
        return $this->assignments->subjectsByPolicy($policyId)
            ->map(fn (mixed $subject): Subject => $this->subjectFromStoredValue((string) $subject))
            ->unique()
            ->values()
            ->all();
    }

    private function scheduleSubjectRecompile(Subject $subject): void
    {
        $this->afterCommit(function () use ($subject): void {
            $this->compiler->recompileSubject($subject);
        });
    }

    /**
     * @param  list<Subject>  $subjects
     */
    private function scheduleSubjectsRecompile(array $subjects): void
    {
        if ($subjects === []) {
            return;
        }

        $uniqueSubjects = [];
        foreach ($subjects as $subject) {
            $uniqueSubjects[(string) $subject] = $subject;
        }

        $this->afterCommit(function () use ($uniqueSubjects): void {
            foreach (array_values($uniqueSubjects) as $subject) {
                $this->compiler->recompileSubject($subject);
            }
        });
    }

    private function scheduleAffectedSubjectsRecompile(int $policyId): void
    {
        $this->afterCommit(function () use ($policyId): void {
            $this->compiler->recompileAffectedSubjects($policyId);
        });
    }

    private function afterCommit(Closure $callback): void
    {
        DB::afterCommit($callback);
    }
}

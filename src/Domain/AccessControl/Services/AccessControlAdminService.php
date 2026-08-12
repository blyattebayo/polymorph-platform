<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AccessControlAdministration;
use Polymorph\Platform\Domain\AccessControl\Core\Models\Assignment;
use Polymorph\Platform\Domain\AccessControl\Core\Models\Policy;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Effect;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\PolicyData;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Subject;
use Polymorph\Platform\Domain\AccessControl\Infrastructure\Repositories\EloquentAssignmentRepository;
use Polymorph\Platform\Domain\AccessControl\Infrastructure\Repositories\EloquentPolicyRepository;
use Polymorph\Platform\SharedKernel\Access\CapabilityCatalog;

/** Owns every policy and assignment mutation and its database transaction. */
final class AccessControlAdminService implements AccessControlAdministration
{
    public function __construct(
        private readonly EloquentPolicyRepository $policies,
        private readonly EloquentAssignmentRepository $assignments,
    ) {}

    public function createPolicy(array $data): Policy
    {
        return DB::transaction(function () use ($data): Policy {
            $policyData = $this->policyDataFromInput($data);
            $this->ensureNoDuplicatePolicy($this->policies->findDuplicate($policyData));

            return $this->policies->create($policyData);
        });
    }

    public function ensurePolicy(array $data): Policy
    {
        return DB::transaction(function () use ($data): Policy {
            $policyData = $this->policyDataFromInput($data);
            $existing = $this->policies->findDuplicate($policyData);

            return $existing ?? $this->policies->create($policyData);
        });
    }

    public function updatePolicy(int $id, array $data): Policy
    {
        return DB::transaction(function () use ($id, $data): Policy {
            $policy = $this->getPolicyOrFail($id);
            $policyData = $this->policyDataFromInput($data);
            $this->ensureNoDuplicatePolicy($this->policies->findDuplicate($policyData), $id);

            return $this->policies->update($policy, $policyData);
        });
    }

    public function deletePolicy(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $this->policies->delete($this->getPolicyOrFail($id));
        });
    }

    public function revokeResource(
        array $resourcePatterns,
        string $action = CapabilityCatalog::ACTION_ACCESS,
    ): void {
        DB::transaction(function () use ($resourcePatterns, $action): void {
            $policyIds = $this->policies->idsForResources($resourcePatterns, $action, Effect::ALLOW);
            foreach ($policyIds as $policyId) {
                $policy = $this->policies->find($policyId);
                if ($policy instanceof Policy) {
                    $this->policies->delete($policy);
                }
            }
        });
    }

    public function assign(int $policyId, Subject $subject): Assignment
    {
        return DB::transaction(function () use ($policyId, $subject): Assignment {
            $this->getPolicyOrFail($policyId);

            return $this->assignments->upsert($policyId, $subject);
        });
    }

    public function unassign(int $assignmentId): void
    {
        DB::transaction(function () use ($assignmentId): void {
            $assignment = $this->assignments->find($assignmentId);
            if ($assignment === null) {
                throw AccessControlApplicationException::notFound('Assignment not found.');
            }

            $this->assignments->delete($assignment);
        });
    }

    public function setSubjectPolicies(Subject $subject, array $policyIds): void
    {
        $normalizedPolicyIds = $this->normalizePolicyIds($policyIds);

        DB::transaction(function () use ($subject, $normalizedPolicyIds): void {
            if ($normalizedPolicyIds !== []) {
                $this->assertAllPoliciesExist($normalizedPolicyIds);
            }

            $currentPolicyIds = $this->assignments->policyIdsForSubject($subject)
                ->map(static fn (mixed $id): int => (int) $id)
                ->unique()
                ->values()
                ->all();

            $this->assignments->deleteManyForSubject(
                $subject,
                array_values(array_diff($currentPolicyIds, $normalizedPolicyIds)),
            );
            $this->assignments->upsertManyForSubject(
                $subject,
                array_values(array_diff($normalizedPolicyIds, $currentPolicyIds)),
            );
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
        if ($duplicate !== null && (int) $duplicate->id !== $currentPolicyId) {
            throw AccessControlApplicationException::validation('Equivalent policy already exists.');
        }
    }

    /** @param array<string, mixed> $data */
    private function policyDataFromInput(array $data): PolicyData
    {
        try {
            return PolicyData::fromInput($data);
        } catch (InvalidArgumentException $exception) {
            throw AccessControlApplicationException::validation($exception->getMessage());
        }
    }

    /** @param list<int> $policyIds */
    private function normalizePolicyIds(array $policyIds): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $policyIds),
            static fn (int $id): bool => $id > 0,
        )));
    }

    /** @param list<int> $policyIds */
    private function assertAllPoliciesExist(array $policyIds): void
    {
        if (array_diff($policyIds, $this->policies->existingIds($policyIds)) !== []) {
            throw AccessControlApplicationException::validation('One or more policy_ids do not exist.');
        }
    }
}

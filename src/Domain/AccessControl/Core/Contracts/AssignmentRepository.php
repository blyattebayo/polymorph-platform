<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Core\Contracts;

use Illuminate\Support\Collection;
use Polymorph\Platform\Domain\AccessControl\Core\Models\Assignment;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\PolicySnapshotData;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Subject;

interface AssignmentRepository
{
    public function upsert(int $policyId, Subject $subject): Assignment;

    /**
     * @param  list<int>  $policyIds
     */
    public function upsertManyForSubject(Subject $subject, array $policyIds): void;

    public function deleteForPolicyAndSubject(int $policyId, Subject $subject): void;

    /**
     * @param  list<int>  $policyIds
     */
    public function deleteManyForSubject(Subject $subject, array $policyIds): void;

    public function find(int $assignmentId): ?Assignment;

    public function delete(Assignment $assignment): void;

    /**
     * @return Collection<int, int>
     */
    public function policyIdsForSubject(Subject $subject): Collection;

    /**
     * @return Collection<int, string>
     */
    public function subjectsByPolicy(int $policyId): Collection;

    /**
     * @return Collection<int, string>
     */
    public function allSubjects(): Collection;

    /**
     * @return Collection<int, PolicySnapshotData>
     */
    public function policySnapshotsForSubject(Subject $subject): Collection;

    /**
     * @return list<array<string, mixed>>
     */
    public function listByPolicy(int $policyId): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listBySubject(Subject $subject): array;
}

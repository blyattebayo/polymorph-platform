<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Infrastructure\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AssignmentRepository;
use Polymorph\Platform\Domain\AccessControl\Core\Models\Assignment;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\PolicySnapshotData;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Subject;

final class EloquentAssignmentRepository implements AssignmentRepository
{
    public function upsert(int $policyId, Subject $subject): Assignment
    {
        $subjectKey = (string) $subject;

        return Assignment::query()->firstOrCreate([
            'policy_id' => $policyId,
            'subject' => $subjectKey,
        ]);
    }

    public function upsertManyForSubject(Subject $subject, array $policyIds): void
    {
        if ($policyIds === []) {
            return;
        }

        $subjectKey = (string) $subject;
        $timestamp = now();

        $rows = array_map(
            static fn (int $policyId): array => [
                'policy_id' => $policyId,
                'subject' => $subjectKey,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            $policyIds,
        );

        Assignment::query()->insertOrIgnore($rows);
    }

    public function deleteForPolicyAndSubject(int $policyId, Subject $subject): void
    {
        $subjectKey = (string) $subject;

        Assignment::query()
            ->where('policy_id', $policyId)
            ->where('subject', $subjectKey)
            ->delete();
    }

    public function deleteManyForSubject(Subject $subject, array $policyIds): void
    {
        if ($policyIds === []) {
            return;
        }

        $subjectKey = (string) $subject;

        Assignment::query()
            ->where('subject', $subjectKey)
            ->whereIn('policy_id', $policyIds)
            ->delete();
    }

    public function find(int $assignmentId): ?Assignment
    {
        return Assignment::query()->find($assignmentId);
    }

    public function delete(Assignment $assignment): void
    {
        $assignment->delete();
    }

    public function policyIdsForSubject(Subject $subject): Collection
    {
        $subjectKey = (string) $subject;

        return Assignment::query()
            ->where('subject', $subjectKey)
            ->pluck('policy_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values();
    }

    public function subjectsByPolicy(int $policyId): Collection
    {
        return Assignment::query()
            ->where('policy_id', $policyId)
            ->pluck('subject')
            ->map(static fn (mixed $subject): string => (string) $subject)
            ->values();
    }

    public function allSubjects(): Collection
    {
        return Assignment::query()
            ->distinct()
            ->orderBy('subject')
            ->pluck('subject')
            ->map(static fn (mixed $subject): string => (string) $subject)
            ->filter(static fn (string $subject): bool => $subject !== '')
            ->values();
    }

    public function policySnapshotsForSubject(Subject $subject): Collection
    {
        $subjectKey = (string) $subject;

        return DB::table('ac_assignments as a')
            ->join('ac_policies as p', 'p.id', '=', 'a.policy_id')
            ->where('a.subject', $subjectKey)
            ->where('p.is_active', true)
            ->orderBy('p.priority')
            ->orderBy('p.id')
            ->get([
                'p.id as policy_id',
                'p.resource_pattern',
                'p.action',
                'p.effect',
                'p.priority',
            ])
            ->map(static fn (object $snapshot): PolicySnapshotData => new PolicySnapshotData(
                policyId: (int) $snapshot->policy_id,
                resourcePattern: (string) $snapshot->resource_pattern,
                action: (string) $snapshot->action,
                effect: (string) $snapshot->effect,
                priority: (int) $snapshot->priority,
            ));
    }

    public function listByPolicy(int $policyId): array
    {
        return Assignment::query()
            ->where('policy_id', $policyId)
            ->orderBy('id')
            ->get(['id', 'policy_id', 'subject', 'created_at', 'updated_at'])
            ->map(static fn (Assignment $assignment): array => [
                'id' => (int) $assignment->id,
                'policy_id' => (int) $assignment->policy_id,
                'subject' => (string) $assignment->subject,
                'created_at' => $assignment->created_at?->toDateTimeString(),
                'updated_at' => $assignment->updated_at?->toDateTimeString(),
            ])
            ->values()
            ->all();
    }

    public function listBySubject(Subject $subject): array
    {
        $subjectKey = (string) $subject;

        return Assignment::query()
            ->where('subject', $subjectKey)
            ->orderBy('id')
            ->get(['id', 'policy_id', 'subject', 'created_at', 'updated_at'])
            ->map(static fn (Assignment $assignment): array => [
                'id' => (int) $assignment->id,
                'policy_id' => (int) $assignment->policy_id,
                'subject' => (string) $assignment->subject,
                'created_at' => $assignment->created_at?->toDateTimeString(),
                'updated_at' => $assignment->updated_at?->toDateTimeString(),
            ])
            ->values()
            ->all();
    }
}

<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Infrastructure\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\AccessControl\Core\Models\Assignment;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Subject;

final class EloquentAssignmentRepository
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

    public function policyRulesForSubjects(array $subjects): Collection
    {
        if ($subjects === []) {
            return collect();
        }

        return DB::table('ac_assignments as assignment')
            ->join('ac_policies as policy', 'policy.id', '=', 'assignment.policy_id')
            ->whereIn('assignment.subject', $subjects)
            ->orderBy('policy.id')
            ->get([
                'policy.id',
                'policy.resource_pattern',
                'policy.action',
                'policy.effect',
            ]);
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

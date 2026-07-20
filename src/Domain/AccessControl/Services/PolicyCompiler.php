<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Services;

use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AssignmentRepository;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CompiledPolicyRepository;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\PolicyCompilationService;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\CompiledPolicyData;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\PolicySnapshotData;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Subject;

final class PolicyCompiler implements PolicyCompilationService
{
    public function __construct(
        private readonly AssignmentRepository $assignments,
        private readonly CompiledPolicyRepository $compiledPolicies,
    ) {}

    public function recompileSubject(Subject $subject): void
    {
        $subjectKey = (string) $subject;

        $snapshots = $this->assignments->policySnapshotsForSubject($subject);
        $rows = [];

        foreach ($snapshots as $snapshot) {
            if (! $snapshot instanceof PolicySnapshotData || $snapshot->resourcePattern === '') {
                continue;
            }

            $rows[] = new CompiledPolicyData(
                resourcePattern: $snapshot->resourcePattern,
                action: $snapshot->action,
                effect: $snapshot->effect,
                priority: $snapshot->priority,
                policyId: $snapshot->policyId > 0 ? $snapshot->policyId : null,
                compiledAt: now(),
            );
        }

        $this->compiledPolicies->replaceForSubject($subjectKey, $rows);
    }

    public function recompileAffectedSubjects(int $policyId): void
    {
        $subjects = $this->assignments->subjectsByPolicy($policyId)
            ->map(static fn (mixed $subject): string => (string) $subject)
            ->filter(static fn (string $subject): bool => $subject !== '')
            ->unique()
            ->values();

        foreach ($subjects as $subject) {
            $this->recompileSubject(Subject::fromString($subject));
        }
    }

    public function recompileAll(): int
    {
        $subjects = $this->assignments->allSubjects()
            ->map(static fn (mixed $subject): string => (string) $subject)
            ->filter(static fn (string $subject): bool => $subject !== '')
            ->merge($this->compiledPolicies->subjects())
            ->unique()
            ->values();

        foreach ($subjects as $subject) {
            $this->recompileSubject(Subject::fromString($subject));
        }

        return $subjects->count();
    }
}

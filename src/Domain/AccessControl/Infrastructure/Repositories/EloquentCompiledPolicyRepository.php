<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Infrastructure\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\CompiledPolicyRepository;
use Polymorph\Platform\Domain\AccessControl\Core\Models\CompiledPolicy;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\CompiledPolicyData;

final class EloquentCompiledPolicyRepository implements CompiledPolicyRepository
{
    public function replaceForSubject(string $subject, array $rows): void
    {
        DB::transaction(function () use ($subject, $rows): void {
            $this->deleteRowsForSubject($subject);

            if ($rows === []) {
                return;
            }

            $payload = array_map(
                static fn (CompiledPolicyData $row): array => $row->toPersistence($subject),
                $rows,
            );

            CompiledPolicy::query()->insert($payload);
        });
    }

    public function findForSubjects(array $subjects, array $actions = []): Collection
    {
        $query = CompiledPolicy::query()
            ->whereIn('subject', $subjects)
            ->orderBy('priority')
            ->orderBy('id');

        if ($actions !== []) {
            $query->whereIn('action', $actions);
        }

        return $query->get();
    }

    public function listBySubject(string $subject): Collection
    {
        return CompiledPolicy::query()
            ->where('subject', $subject)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
    }

    public function subjects(): Collection
    {
        return CompiledPolicy::query()
            ->select('subject')
            ->distinct()
            ->pluck('subject')
            ->map(static fn (mixed $subject): string => trim((string) $subject))
            ->filter(static fn (string $subject): bool => $subject !== '')
            ->values();
    }

    public function clear(): void
    {
        CompiledPolicy::query()->delete();
    }

    private function deleteRowsForSubject(string $subject): void
    {
        CompiledPolicy::query()
            ->where('subject', $subject)
            ->delete();
    }
}

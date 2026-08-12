<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Infrastructure\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Polymorph\Platform\Domain\AccessControl\Core\Models\Policy;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Effect;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\PolicyData;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageRequest;

final class EloquentPolicyRepository
{
    public function paginate(array $filters, PageRequest $pagination): LengthAwarePaginator
    {
        $query = Policy::query()->orderBy('id');

        $resourcePattern = $filters['resource_pattern'] ?? null;
        if (is_string($resourcePattern) && $resourcePattern !== '') {
            $query->where('resource_pattern', $resourcePattern);
        }

        foreach (['action', 'effect'] as $field) {
            $value = $filters[$field] ?? null;
            if (is_string($value) && $value !== '') {
                $query->where($field, $value);
            }
        }

        return $query->paginate(perPage: $pagination->perPage, page: $pagination->page);
    }

    public function find(int $id): ?Policy
    {
        return Policy::query()->find($id);
    }

    public function findDuplicate(PolicyData $policyData): ?Policy
    {
        $query = Policy::query();

        $query
            ->where('resource_pattern', $policyData->resourcePattern)
            ->where('action', $policyData->action)
            ->where('effect', $policyData->effect->value);

        return $query->first();
    }

    public function create(PolicyData $policyData): Policy
    {
        return Policy::query()->create($policyData->toPersistence());
    }

    public function update(Policy $policy, PolicyData $policyData): Policy
    {
        $policy->update($policyData->toPersistence());

        /** @var Policy $fresh */
        $fresh = $policy->fresh();

        return $fresh;
    }

    public function delete(Policy $policy): void
    {
        $policy->delete();
    }

    public function existingIds(array $ids): array
    {
        return Policy::query()
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    public function idsForResources(array $resourcePatterns, string $action, Effect $effect): array
    {
        if ($resourcePatterns === []) {
            return [];
        }

        return Policy::query()
            ->whereIn('resource_pattern', $resourcePatterns)
            ->where('action', $action)
            ->where('effect', $effect->value)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }
}

<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Core\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Polymorph\Platform\Domain\AccessControl\Core\Models\Policy;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\PolicyData;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageRequest;

interface PolicyRepository
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, PageRequest $pagination): LengthAwarePaginator;

    public function find(int $id): ?Policy;

    public function exists(int $id): bool;

    public function findDuplicate(PolicyData $policyData): ?Policy;

    public function create(PolicyData $policyData): Policy;

    public function update(Policy $policy, PolicyData $policyData): Policy;

    public function delete(Policy $policy): void;

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    public function existingIds(array $ids): array;

    /**
     * Идентификаторы политик с точным resource_pattern из набора (фильтр по action/effect).
     *
     * @param  list<string>  $resourcePatterns
     * @return list<int>
     */
    public function idsForResources(array $resourcePatterns, string $action, string $effect): array;
}

<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Services;

use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AssignmentRepository;
use Polymorph\Platform\Domain\AccessControl\Core\Contracts\PolicyRepository;
use Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Subject;
use Polymorph\Platform\Infrastructure\Pagination\V2\LaravelPaginatorAdapter;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageRequest;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageResult;

final class PolicyQueryService
{
    public function __construct(
        private readonly PolicyRepository $policies,
        private readonly AssignmentRepository $assignments,
        private readonly LaravelPaginatorAdapter $paginatorAdapter,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listPolicies(array $filters, PageRequest $pagination): PageResult
    {
        return $this->paginatorAdapter->toPageResult($this->policies->paginate($filters, $pagination));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAssignments(int $policyId): array
    {
        if (! $this->policies->exists($policyId)) {
            throw AccessControlApplicationException::notFound('Policy not found.');
        }

        return $this->assignments->listByPolicy($policyId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listSubjectAssignments(Subject $subject): array
    {
        return $this->assignments->listBySubject($subject);
    }
}

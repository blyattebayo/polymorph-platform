<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\AccessControl\Http\Controllers;

use Polymorph\Platform\Domain\AccessControl\Core\Contracts\AccessControlAdministration;
use Polymorph\Platform\Domain\AccessControl\Http\Requests\IndexAssignmentsRequest;
use Polymorph\Platform\Domain\AccessControl\Http\Requests\SetAssignmentsRequest;
use Polymorph\Platform\Domain\AccessControl\Services\PolicyQueryService;
use Polymorph\Platform\Http\Controllers\Controller;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;
use Illuminate\Http\JsonResponse;

final class AssignmentController extends Controller
{
    public function __construct(
        private readonly AccessControlAdministration $adminService,
        private readonly PolicyQueryService $queryService,
    ) {
    }

    public function replace(SetAssignmentsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $subject = \Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Subject::fromString((string) $validated['subject']);

        $this->adminService->setSubjectPolicies(
            $subject,
            array_map(static fn (mixed $id): int => (int) $id, $validated['policy_ids']),
        );

        return AdminResponse::json([
            'data' => $this->queryService->listSubjectAssignments($subject),
        ]);
    }

    public function indexBySubject(IndexAssignmentsRequest $request): JsonResponse
    {
        $data = $this->queryService->listSubjectAssignments(
            \Polymorph\Platform\Domain\AccessControl\Core\ValueObjects\Subject::fromString((string) $request->validated()['subject']),
        );

        return AdminResponse::json(['data' => $data]);
    }
}
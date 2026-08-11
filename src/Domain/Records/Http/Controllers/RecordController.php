<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Polymorph\Platform\Domain\Auth\Application\Authentication\AuthenticationContext;
use Polymorph\Platform\Domain\Records\Core\Contracts\RecordRepository;
use Polymorph\Platform\Domain\Records\Http\Requests\HydrateRecordsRequest;
use Polymorph\Platform\Domain\Records\Http\Requests\IndexRecordsRequest;
use Polymorph\Platform\Domain\Records\Http\Requests\StoreRecordRequest;
use Polymorph\Platform\Domain\Records\Http\Requests\UpdateRecordRequest;
use Polymorph\Platform\Domain\Records\Services\RecordReader;
use Polymorph\Platform\Domain\Records\Services\RecordWriteOrchestrationService;
use Polymorph\Platform\Http\Controllers\Controller;
use Polymorph\Platform\Http\Pagination\V2\PaginatedJsonResponse;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ThrowsErrors;

final class RecordController extends Controller
{
    use ThrowsErrors;

    public function __construct(
        private readonly RecordRepository $recordRepository,
        private readonly RecordReader $recordReader,
        private readonly RecordWriteOrchestrationService $recordWriteOrchestrationService,
        private readonly AuthenticationContext $auth,
    ) {}

    public function index(IndexRecordsRequest $request): JsonResponse
    {
        $recordDefinitionId = (int) $request->validated('record_definition_id');
        $result = $this->recordReader->listForDefinition(
            $this->auth->requireUser(),
            $recordDefinitionId,
            $request->pageRequest(),
        );

        return PaginatedJsonResponse::from($result);
    }

    public function show(int $id): JsonResponse
    {
        $row = $this->recordReader->show($this->auth->requireUser(), $id);

        if ($row === null) {
            $this->throwError(
                ErrorCode::NOT_FOUND,
                sprintf('Record with ID %d does not exist.', $id),
                ['record_id' => $id],
            );
        }

        return AdminResponse::json(['data' => $row]);
    }

    public function hydrate(HydrateRecordsRequest $request): JsonResponse
    {
        $recordIds = array_values(array_unique(array_map(
            static fn (mixed $id): int => (int) $id,
            $request->validated('record_ids', []),
        )));

        return AdminResponse::json($this->recordReader->hydrate($this->auth->requireUser(), $recordIds));
    }

    public function store(StoreRecordRequest $request): JsonResponse
    {
        $user = $this->auth->requireUser();
        $snapshot = $this->recordWriteOrchestrationService->create(
            recordDefinitionId: (int) $request->validated('record_definition_id'),
            dataJson: $request->dataJson(),
            actor: $user,
        );

        $record = $this->recordRepository->findForResponse($snapshot->id->value);

        return AdminResponse::json([
            'data' => $this->recordReader->presentLoadedRecord($user, $record),
        ], 201);
    }

    public function update(UpdateRecordRequest $request, int $id): JsonResponse
    {
        $user = $this->auth->requireUser();
        $snapshot = $this->recordWriteOrchestrationService->update(
            $id,
            $request->dataJson(),
            $user,
        );

        $record = $this->recordRepository->findForResponse($snapshot->id->value);

        return AdminResponse::json([
            'data' => $this->recordReader->presentLoadedRecord($user, $record),
        ]);
    }

    public function destroy(int $id): Response
    {
        $this->recordWriteOrchestrationService->delete($id, $this->auth->requireUser());

        return AdminResponse::noContent();
    }

    public function restore(int $id): JsonResponse
    {
        $user = $this->auth->requireUser();
        $snapshot = $this->recordWriteOrchestrationService->restore($id, $user);

        $record = $this->recordRepository->findForResponse($snapshot->id->value);

        return AdminResponse::json([
            'data' => $this->recordReader->presentLoadedRecord($user, $record),
        ]);
    }
}

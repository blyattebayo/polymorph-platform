<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Http\Controllers;

use Polymorph\Platform\Domain\Records\Core\Contracts\RecordRepository;
use Polymorph\Platform\Domain\Records\Http\Requests\HydrateRecordsRequest;
use Polymorph\Platform\Domain\Records\Http\Requests\ListRecordsRequest;
use Polymorph\Platform\Domain\Records\Http\Requests\StoreRecordRequest;
use Polymorph\Platform\Domain\Records\Http\Requests\UpdateRecordRequest;
use Polymorph\Platform\Domain\Records\Services\RecordReader;
use Polymorph\Platform\Domain\Records\Services\RecordWriteOrchestrationService;
use Polymorph\Platform\Http\Controllers\Controller;
use Polymorph\Platform\Http\Pagination\V2\PaginatedJsonResponse;
use Polymorph\Platform\Http\Resources\Admin\Support\AdminResponse;
use Polymorph\Platform\SharedKernel\Identity\UserIdentity;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ThrowsErrors;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class RecordController extends Controller
{
    use ThrowsErrors;

    public function __construct(
        private readonly RecordRepository $recordRepository,
        private readonly RecordReader $recordReader,
        private readonly RecordWriteOrchestrationService $recordWriteOrchestrationService,
    ) {}

    public function index(ListRecordsRequest $request, UserIdentity $actor): JsonResponse
    {
        $recordDefinitionId = (int) $request->validated('record_definition_id');
        $result = $this->recordReader->listForDefinition(
            $actor,
            $recordDefinitionId,
            $request->pageRequest(),
        );
        return PaginatedJsonResponse::from($result);
    }

    public function show(int $id, UserIdentity $actor): JsonResponse
    {
        $row = $this->recordReader->show($actor, $id);

        if ($row === null) {
            $this->throwError(
                ErrorCode::NOT_FOUND,
                sprintf('Record with ID %d does not exist.', $id),
                ['record_id' => $id],
            );
        }

        return AdminResponse::json(['data' => $row]);
    }

    public function hydrate(HydrateRecordsRequest $request, UserIdentity $actor): JsonResponse
    {
        $recordIds = array_values(array_unique(array_map(
            static fn (mixed $id): int => (int) $id,
            $request->validated('record_ids', []),
        )));

        return AdminResponse::json($this->recordReader->hydrate($actor, $recordIds));
    }

    public function store(StoreRecordRequest $request, UserIdentity $actor): JsonResponse
    {
        $snapshot = $this->recordWriteOrchestrationService->create(
            recordDefinitionId: (int) $request->validated('record_definition_id'),
            dataJson: $request->dataJson(),
            actor: $actor,
        );

        $record = $this->recordRepository->findForResponse($snapshot->id->value);

        return AdminResponse::json([
            'data' => $this->recordReader->presentLoadedRecord($actor, $record),
        ], 201);
    }

    public function update(UpdateRecordRequest $request, int $id, UserIdentity $actor): JsonResponse
    {
        $snapshot = $this->recordWriteOrchestrationService->update(
            $id,
            $request->dataJson(),
            $actor,
        );

        $record = $this->recordRepository->findForResponse($snapshot->id->value);

        return AdminResponse::json([
            'data' => $this->recordReader->presentLoadedRecord($actor, $record),
        ]);
    }

    public function destroy(int $id, UserIdentity $actor): Response
    {
        $this->recordWriteOrchestrationService->delete($id, $actor);

        return AdminResponse::noContent();
    }

    public function restore(int $id, UserIdentity $actor): JsonResponse
    {
        $snapshot = $this->recordWriteOrchestrationService->restore($id, $actor);

        $record = $this->recordRepository->findForResponse($snapshot->id->value);

        return AdminResponse::json([
            'data' => $this->recordReader->presentLoadedRecord($actor, $record),
        ]);
    }
}


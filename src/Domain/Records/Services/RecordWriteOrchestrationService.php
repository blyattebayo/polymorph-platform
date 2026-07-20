<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Services;

use Illuminate\Support\Str;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Contracts\RecordDefinitionRepository;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Polymorph\Platform\Domain\Records\Core\Contracts\RecordRepository;
use Polymorph\Platform\Domain\Records\Pipeline\Commands\CreateRecordCommand;
use Polymorph\Platform\Domain\Records\Pipeline\Commands\DeleteRecordCommand;
use Polymorph\Platform\Domain\Records\Pipeline\Commands\RestoreRecordCommand;
use Polymorph\Platform\Domain\Records\Pipeline\Commands\UpdateRecordCommand;
use Polymorph\Platform\Domain\Records\Pipeline\Core\RecordSnapshot;
use Polymorph\Platform\Domain\Records\Pipeline\Handlers\CreateRecordHandler;
use Polymorph\Platform\Domain\Records\Pipeline\Handlers\DeleteRecordHandler;
use Polymorph\Platform\Domain\Records\Pipeline\Handlers\RestoreRecordHandler;
use Polymorph\Platform\Domain\Records\Pipeline\Handlers\UpdateRecordHandler;
use Polymorph\Platform\SharedKernel\Identity\UserIdentity;
use Polymorph\Platform\Support\Errors\ErrorCode;
use Polymorph\Platform\Support\Errors\ThrowsErrors;

final class RecordWriteOrchestrationService
{
    use ThrowsErrors;

    public function __construct(
        private readonly CreateRecordHandler $createRecordHandler,
        private readonly UpdateRecordHandler $updateRecordHandler,
        private readonly DeleteRecordHandler $deleteRecordHandler,
        private readonly RestoreRecordHandler $restoreRecordHandler,
        private readonly RecordWriteAccessService $recordWriteAccessService,
        private readonly RecordRepository $recordRepository,
        private readonly RecordDefinitionRepository $recordDefinitionRepository,
    ) {}

    /**
     * @param  array<string,mixed>  $dataJson
     */
    public function create(int $recordDefinitionId, array $dataJson, UserIdentity $actor): RecordSnapshot
    {
        $operationId = (string) Str::uuid();
        $recordDefinition = $this->recordDefinitionRepository->find($recordDefinitionId);
        if ($recordDefinition === null) {
            $this->throwError(
                ErrorCode::NOT_FOUND,
                'Record definition not found.',
                ['record_definition_id' => $recordDefinitionId],
            );
        }

        $this->recordWriteAccessService->assertCanWriteRecordDefinitionPayload($actor, $recordDefinition, $dataJson);

        return $this->createRecordHandler->handle(new CreateRecordCommand(
            $recordDefinition,
            $dataJson,
            $actor->userId(),
            $operationId,
        ));
    }

    /**
     * @param  array<string,mixed>  $dataJson
     */
    public function update(int $recordId, array $dataJson, UserIdentity $actor): RecordSnapshot
    {
        $operationId = (string) Str::uuid();
        $record = $this->recordRepository->findWithDefinition($recordId);

        if ($record === null) {
            $this->throwError(
                ErrorCode::NOT_FOUND,
                'Record not found.',
                ['record_id' => $recordId, 'trashed' => false],
            );
        }

        $recordDefinition = $record->getRelation('recordDefinition');
        if (! $recordDefinition instanceof RecordDefinition) {
            throw new \LogicException(sprintf(
                'Record %d is missing an associated recordDefinition during update orchestration.',
                $recordId,
            ));
        }

        $this->recordWriteAccessService->assertCanWriteRecordDefinitionPayload($actor, $recordDefinition, $dataJson);

        return $this->updateRecordHandler->handle(new UpdateRecordCommand(
            $recordId,
            $recordDefinition,
            $dataJson,
            $actor->userId(),
            $operationId,
        ));
    }

    public function delete(int $recordId, UserIdentity $actor): void
    {
        $operationId = (string) Str::uuid();
        $this->deleteRecordHandler->handle(new DeleteRecordCommand(
            $recordId,
            $actor->userId(),
            $operationId,
        ));
    }

    public function restore(int $recordId, UserIdentity $actor): RecordSnapshot
    {
        $operationId = (string) Str::uuid();

        return $this->restoreRecordHandler->handle(new RestoreRecordCommand(
            $recordId,
            $actor->userId(),
            $operationId,
        ));
    }
}

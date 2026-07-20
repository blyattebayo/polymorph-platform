<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Http\Controllers;

use Polymorph\Platform\Domain\RecordDefinitions\Core\Contracts\RecordDefinitionRepository;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Exceptions\RecordDefinitionNotFoundErrorFactory;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Polymorph\Platform\Domain\RecordDefinitions\Http\Requests\CreateRecordDefinitionRequest;
use Polymorph\Platform\Domain\RecordDefinitions\Http\Requests\IndexRecordDefinitionsRequest;
use Polymorph\Platform\Domain\RecordDefinitions\Http\Requests\UpdateRecordDefinitionRequest;
use Polymorph\Platform\Domain\RecordDefinitions\Http\Resources\RecordDefinitionResource;
use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Commands\CreateRecordDefinitionCommand;
use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Commands\DeleteRecordDefinitionCommand;
use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Commands\UpdateRecordDefinitionCommand;
use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Handlers\CreateRecordDefinitionHandler;
use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Handlers\DeleteRecordDefinitionHandler;
use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Handlers\UpdateRecordDefinitionHandler;
use Polymorph\Platform\Http\Controllers\Controller;
use Polymorph\Platform\Http\Pagination\V2\PaginatedJsonResponse;
use Polymorph\Platform\Infrastructure\Pagination\V2\LaravelPaginatorAdapter;
use Polymorph\Platform\SharedKernel\Ownership\ResourceOwnershipService;
use Polymorph\Platform\SharedKernel\Ownership\ResourceType;
use Polymorph\Platform\Support\Errors\ErrorFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Контроллер для управления типами записей (RecordDefinition) в админ-панели.
 *
 * Предоставляет CRUD операции для типов записей: создание, чтение, обновление, удаление.
 *
 * @package Polymorph\Platform\Domain\RecordDefinitions\Http\Controllers
 */
class RecordDefinitionController extends Controller
{
    public function __construct(
        private readonly CreateRecordDefinitionHandler $createRecordDefinitionHandler,
        private readonly UpdateRecordDefinitionHandler $updateRecordDefinitionHandler,
        private readonly DeleteRecordDefinitionHandler $deleteRecordDefinitionHandler,
        private readonly RecordDefinitionRepository $repository,
        private readonly ErrorFactory $errorFactory,
        private readonly LaravelPaginatorAdapter $paginatorAdapter,
        private readonly ResourceOwnershipService $ownershipService,
    ) {
    }


    public function store(CreateRecordDefinitionRequest $request): JsonResponse
    {
        $recordDefinition = $this->createRecordDefinitionHandler->handle(new CreateRecordDefinitionCommand(
            payload: $request->toData(),
        ));
        $this->requireOwnership($recordDefinition);

        return (new RecordDefinitionResource($recordDefinition))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function index(IndexRecordDefinitionsRequest $request): JsonResponse
    {
        $result = $this->paginatorAdapter
            ->toPageResult($this->repository->paginate($request->pageRequest()))
            ->mapItems(
                fn (RecordDefinition $recordDefinition): array => $this->serializeRecordDefinition($recordDefinition, $request)
            );

        return PaginatedJsonResponse::from(
            $result
        );
    }

    public function show(int $id): RecordDefinitionResource
    {
        $recordDefinition = $this->resolveRecordDefinition($id);
        $this->requireOwnership($recordDefinition);

        return new RecordDefinitionResource($recordDefinition);
    }

    public function update(UpdateRecordDefinitionRequest $request, int $id): RecordDefinitionResource
    {
        $updatedType = $this->updateRecordDefinitionHandler->handle(new UpdateRecordDefinitionCommand(
            recordDefinition: $this->resolveRecordDefinition($id),
            payload: $request->toData(),
        ));
        $this->requireOwnership($updatedType);

        return new RecordDefinitionResource($updatedType);
    }

    public function destroy(Request $request, int $id): Response
    {
        $force = $request->boolean('force');

        $this->deleteRecordDefinitionHandler->handle(new DeleteRecordDefinitionCommand(
            recordDefinition: $this->resolveRecordDefinition($id),
            force: $force,
        ));

        return response()->noContent();
    }

    private function resolveRecordDefinition(int $id): RecordDefinition
    {
        return $this->repository->find($id)
            ?? throw RecordDefinitionNotFoundErrorFactory::byId($this->errorFactory, $id);
    }

    private function serializeRecordDefinition(RecordDefinition $recordDefinition, IndexRecordDefinitionsRequest $request): array
    {
        $this->requireOwnership($recordDefinition);

        return RecordDefinitionResource::make($recordDefinition)->toArray($request);
    }

    private function requireOwnership(RecordDefinition $recordDefinition): void
    {
        $this->ownershipService->require(ResourceType::RECORD_DEFINITION, (int) $recordDefinition->id);
    }
}

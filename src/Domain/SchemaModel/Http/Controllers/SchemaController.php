<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Polymorph\Platform\Domain\SchemaModel\Core\Contracts\SchemaRepository;
use Polymorph\Platform\Domain\SchemaModel\Core\Exceptions\SchemaNotFoundException;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;
use Polymorph\Platform\Domain\SchemaModel\Http\Requests\BulkDeleteSchemasRequest;
use Polymorph\Platform\Domain\SchemaModel\Http\Requests\IndexSchemasRequest;
use Polymorph\Platform\Domain\SchemaModel\Http\Requests\StoreSchemaRequest;
use Polymorph\Platform\Domain\SchemaModel\Http\Requests\UpdateSchemaRequest;
use Polymorph\Platform\Domain\SchemaModel\Http\Resources\BulkDeleteSchemasResource;
use Polymorph\Platform\Domain\SchemaModel\Http\Resources\FieldTreeResource;
use Polymorph\Platform\Domain\SchemaModel\Http\Resources\SchemaResource;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Commands\DeleteSchemaCommand;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Commands\SaveSchemaWithFieldsCommand;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Handlers\BulkDeleteSchemasHandler;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Handlers\DeleteSchemaHandler;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Handlers\SaveSchemaWithFieldsHandler;
use Polymorph\Platform\Http\Pagination\V2\PaginatedJsonResponse;
use Polymorph\Platform\Infrastructure\Pagination\V2\LaravelPaginatorAdapter;
use Polymorph\Platform\SharedKernel\Ownership\ResourceOwnershipService;
use Polymorph\Platform\SharedKernel\Ownership\ResourceType;
use Polymorph\Platform\Support\Errors\ThrowsErrors;

/**
 * Контроллер для управления схемами.
 */
final class SchemaController extends Controller
{
    use ThrowsErrors;

    public function __construct(
        private readonly SaveSchemaWithFieldsHandler $saveSchemaWithFieldsHandler,
        private readonly DeleteSchemaHandler $deleteSchemaHandler,
        private readonly BulkDeleteSchemasHandler $bulkDeleteSchemasHandler,
        private readonly SchemaRepository $repository,
        private readonly LaravelPaginatorAdapter $paginatorAdapter,
        private readonly ResourceOwnershipService $ownershipService,
    ) {}

    /**
     * Получить список всех схем.
     */
    public function index(IndexSchemasRequest $request): JsonResponse
    {
        $result = $this->paginatorAdapter
            ->toPageResult($this->repository->search($request->filters(), $request->pageRequest()))
            ->mapItems(
                fn (SchemaModel $schema): array => $this->serializeSchema($schema, $request)
            );

        return PaginatedJsonResponse::from(
            $result
        );
    }

    /**
     * Создать новую схему.
     */
    public function store(StoreSchemaRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $fieldsUpsert = $validated['fields']['upsert'] ?? [];
        $schemaPayload = Arr::except($validated, ['fields']);

        $schema = $this->saveSchemaWithFieldsHandler->handle(new SaveSchemaWithFieldsCommand(
            schemaPayload: $schemaPayload,
            fieldsUpsert: $fieldsUpsert,
            fieldsDelete: [],
        ));
        $this->requireOwnership($schema);

        return (new SchemaResource($schema->load('fields')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Получить схему по ID.
     */
    public function show(int $id): SchemaResource
    {
        $schema = $this->repository->find($id);

        if (! $schema) {
            throw SchemaNotFoundException::byId($id);
        }

        $schema->loadCount(['fields', 'recordDefinitions']);
        $this->requireOwnership($schema);

        return new SchemaResource($schema);
    }

    /**
     * Получить дерево полей схемы.
     */
    public function tree(Request $request, int $id): JsonResponse
    {
        $schema = $this->repository->find($id);

        if (! $schema) {
            throw SchemaNotFoundException::byId($id);
        }

        $rootFields = $schema->rootFields()
            ->with(['children' => function ($query) {
                $query->with('children')->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();
        $this->requireOwnership($schema);

        return response()->json([
            'schema' => new SchemaResource($schema),
            'fields' => FieldTreeResource::collection($rootFields),
        ]);
    }

    /**
     * Обновить схему.
     */
    public function update(UpdateSchemaRequest $request, int $id): SchemaResource
    {
        $schema = $this->repository->find($id);

        if (! $schema) {
            throw SchemaNotFoundException::byId($id);
        }

        $validated = $request->validated();
        $fieldsPayload = is_array($validated['fields'] ?? null) ? $validated['fields'] : [];
        $fieldsUpsert = is_array($fieldsPayload['upsert'] ?? null) ? $fieldsPayload['upsert'] : [];
        $fieldsDelete = is_array($fieldsPayload['delete'] ?? null) ? $fieldsPayload['delete'] : [];
        $schemaPayload = Arr::except($validated, ['fields']);

        $schema = $this->saveSchemaWithFieldsHandler->handle(new SaveSchemaWithFieldsCommand(
            schemaPayload: $schemaPayload,
            fieldsUpsert: $fieldsUpsert,
            fieldsDelete: $fieldsDelete,
            schema: $schema,
        ));
        $this->requireOwnership($schema);

        return new SchemaResource($schema->load('fields'));
    }

    /**
     * Удалить схему.
     */
    public function destroy(int $id): JsonResponse
    {
        $schema = $this->repository->find($id);

        if (! $schema) {
            throw SchemaNotFoundException::byId($id);
        }

        $this->deleteSchemaHandler->handle(new DeleteSchemaCommand(
            schema: $schema,
        ));

        return response()->json(null, 204);
    }

    /**
     * Массовое удаление схем.
     */
    public function bulkDestroy(BulkDeleteSchemasRequest $request): JsonResponse
    {
        $ids = array_map('intval', $request->validated('ids'));
        $result = $this->bulkDeleteSchemasHandler->handle($ids);

        return response()->json(BulkDeleteSchemasResource::fromResult($result));
    }

    /**
     * Получить статистику использования схемы.
     */
    public function usage(int $id): JsonResponse
    {
        $schema = $this->repository->find($id);

        if (! $schema) {
            throw SchemaNotFoundException::byId($id);
        }

        return response()->json($this->repository->getUsageInfo($schema)->toUsageResponse());
    }

    private function serializeSchema(SchemaModel $schema, IndexSchemasRequest $request): array
    {
        $this->requireOwnership($schema);

        return SchemaResource::make($schema)->toArray($request);
    }

    private function requireOwnership(SchemaModel $schema): void
    {
        $this->ownershipService->require(ResourceType::SCHEMA, (int) $schema->id);
    }
}

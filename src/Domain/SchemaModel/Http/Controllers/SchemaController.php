<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Polymorph\Platform\Domain\SchemaModel\Core\Exceptions\SchemaNotFoundException;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;
use Polymorph\Platform\Domain\SchemaModel\Http\Requests\BulkDeleteSchemasRequest;
use Polymorph\Platform\Domain\SchemaModel\Http\Requests\IndexSchemasRequest;
use Polymorph\Platform\Domain\SchemaModel\Http\Resources\SchemaResource;
use Polymorph\Platform\Domain\SchemaModel\Infrastructure\Repositories\SchemaRepository;
use Polymorph\Platform\Domain\SchemaModel\Services\SchemaFieldVisibility;
use Polymorph\Platform\Domain\SchemaModel\Services\SchemaMutationService;
use Polymorph\Platform\Http\Pagination\V2\PaginatedJsonResponse;
use Polymorph\Platform\Infrastructure\Pagination\V2\LaravelPaginatorAdapter;
use Polymorph\Platform\SharedKernel\Ownership\ResourceOwnershipService;
use Polymorph\Platform\SharedKernel\Ownership\ResourceType;

/** HTTP transport for the single SchemaModel lifecycle contract. */
final class SchemaController extends Controller
{
    public function __construct(
        private readonly SchemaMutationService $mutations,
        private readonly SchemaRepository $schemas,
        private readonly LaravelPaginatorAdapter $paginator,
        private readonly ResourceOwnershipService $ownership,
        private readonly SchemaFieldVisibility $visibility,
    ) {}

    public function index(IndexSchemasRequest $request): JsonResponse
    {
        $result = $this->paginator
            ->toPageResult($this->schemas->search($request->filters(), $request->pageRequest()))
            ->mapItems(function (SchemaModel $schema) use ($request): array {
                $this->requireOwnership($schema);

                return SchemaResource::make($schema)->toArray($request);
            });

        return PaginatedJsonResponse::from($result);
    }

    public function store(Request $request): JsonResponse
    {
        $schema = $this->mutations->create($request->all());

        return (new SchemaResource($this->detailed($schema)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(int $id): SchemaResource
    {
        return new SchemaResource($this->detailed($this->find($id)));
    }

    public function update(Request $request, int $id): SchemaResource
    {
        return new SchemaResource($this->detailed($this->mutations->update($id, $request->all())));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->mutations->delete($id);

        return response()->json(null, 204);
    }

    public function bulkDestroy(BulkDeleteSchemasRequest $request): JsonResponse
    {
        $ids = array_values(array_map('intval', $request->validated('ids')));

        return response()->json($this->mutations->deleteMany($ids));
    }

    private function find(int $id): SchemaModel
    {
        return $this->schemas->find($id) ?? throw SchemaNotFoundException::byId($id);
    }

    private function detailed(SchemaModel $schema): SchemaModel
    {
        $schema->loadMissing('ownership');
        $schema->loadCount(['fields', 'recordDefinitions']);
        $this->requireOwnership($schema);

        $fields = $schema->fields()
            ->with(['refConstraint', 'mediaConstraints'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $schema->setRelation('fields', $this->visibility->visibleTree((int) $schema->id, $fields));

        return $schema;
    }

    private function requireOwnership(SchemaModel $schema): void
    {
        $this->ownership->require(ResourceType::SCHEMA, (int) $schema->id);
    }
}

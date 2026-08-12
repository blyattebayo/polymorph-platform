<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Infrastructure\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Contracts\RecordDefinitionRepository;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\SchemaUsageInfo;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageRequest;

/** Concrete schema persistence and the one usage query needed by deletion. */
final class SchemaRepository
{
    public function __construct(
        private readonly RecordDefinitionRepository $recordDefinitions,
    ) {}

    public function find(int $id): ?SchemaModel
    {
        return SchemaModel::query()->with('ownership')->find($id);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): SchemaModel
    {
        return SchemaModel::create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(SchemaModel $schema, array $data): SchemaModel
    {
        $schema->update($data);

        return $schema->fresh('ownership') ?? $schema;
    }

    public function delete(SchemaModel $schema): void
    {
        $schema->delete();
    }

    public function getUsageInfo(SchemaModel $schema): SchemaUsageInfo
    {
        return new SchemaUsageInfo(
            schemaId: (int) $schema->id,
            schemaCode: (string) $schema->code,
            schemaName: (string) $schema->name,
            recordDefinitions: $this->recordDefinitions->summariesForSchema((int) $schema->id),
        );
    }

    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        $query = SchemaModel::query()->where('code', $code);
        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    /** @param array{search?:string|null} $criteria */
    public function search(array $criteria, PageRequest $pagination): LengthAwarePaginator
    {
        $query = SchemaModel::query()
            ->with('ownership')
            ->withCount(['fields', 'recordDefinitions']);

        $search = trim((string) ($criteria['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query
            ->orderByDesc('created_at')
            ->paginate(perPage: $pagination->perPage, page: $pagination->page);
    }
}

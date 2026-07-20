<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Infrastructure\Repositories;

use Polymorph\Platform\Domain\RecordDefinitions\Core\Contracts\RecordDefinitionRepository;
use Polymorph\Platform\Domain\SchemaModel\Core\Contracts\SchemaRepository;
use Polymorph\Platform\Domain\SchemaModel\Core\Models\SchemaModel;
use Polymorph\Platform\Domain\SchemaModel\Core\ValueObjects\SchemaUsageInfo;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Eloquent реализация SchemaRepository.
 */
class EloquentSchemaRepository implements SchemaRepository
{
    public function __construct(
        private readonly RecordDefinitionRepository $recordDefinitions,
    ) {
    }

    public function find(int $id): ?SchemaModel
    {
        return SchemaModel::query()
            ->with('ownership')
            ->find($id);
    }

    public function create(array $data): SchemaModel
    {
        return SchemaModel::create($data);
    }

    public function update(SchemaModel $schema, array $data): SchemaModel
    {
        $schema->update($data);
        return $schema->fresh('ownership');
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
        $query = SchemaModel::where('code', $code);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    public function search(array $criteria, PageRequest $pagination): LengthAwarePaginator
    {
        $query = SchemaModel::query()
            ->with('ownership')
            ->withCount(['fields', 'recordDefinitions']);

        // Поиск
        if (!empty($criteria['search'])) {
            $search = $criteria['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Фильтр по использованию
        if (isset($criteria['in_use'])) {
            if ($criteria['in_use']) {
                $query->has('recordDefinitions');
            } else {
                $query->doesntHave('recordDefinitions');
            }
        }

        // Сортировка
        $sortBy = $criteria['sort_by'] ?? 'created_at';
        if ($sortBy === 'recordDefinitions_count') {
            $sortBy = 'record_definitions_count';
        }
        $sortDir = $criteria['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate(perPage: $pagination->perPage, page: $pagination->page);
    }
}

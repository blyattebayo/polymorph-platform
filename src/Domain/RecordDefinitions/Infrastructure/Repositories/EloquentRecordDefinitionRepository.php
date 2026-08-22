<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Infrastructure\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Contracts\RecordDefinitionRepository;
use Polymorph\Platform\Domain\RecordDefinitions\Core\Models\RecordDefinition;
use Polymorph\Platform\SharedKernel\Pagination\V2\PageRequest;

/**
 * Eloquent реализация репозитория RecordDefinition.
 */
class EloquentRecordDefinitionRepository implements RecordDefinitionRepository
{
    public function find(int $id): ?RecordDefinition
    {
        return RecordDefinition::query()
            ->with(['schema', 'ownership'])
            ->find($id);
    }

    public function exists(int $id): bool
    {
        return RecordDefinition::query()->whereKey($id)->exists();
    }

    public function paginate(PageRequest $pagination): LengthAwarePaginator
    {
        return RecordDefinition::query()
            ->with(['schema:id,code,metadata', 'ownership'])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(perPage: $pagination->perPage, page: $pagination->page);
    }

    public function create(array $data): RecordDefinition
    {
        return RecordDefinition::create($data);
    }

    public function update(RecordDefinition $recordDefinition, array $data): RecordDefinition
    {
        $recordDefinition->update($data);

        return $recordDefinition->fresh(['schema', 'ownership']);
    }

    public function delete(RecordDefinition $recordDefinition): void
    {
        $recordDefinition->delete();
    }

    public function summariesForSchema(int $schemaId): array
    {
        return RecordDefinition::query()
            ->select(['id', 'name'])
            ->where('schema_id', $schemaId)
            ->orderBy('id')
            ->get()
            ->map(static fn (RecordDefinition $definition): array => [
                'id' => (int) $definition->id,
                'name' => (string) $definition->name,
            ])
            ->all();
    }

    public function cleanupFieldRefConstraintsForForceDelete(RecordDefinition $recordDefinition): void
    {
        DB::table('field_ref_constraints')
            ->where('allowed_record_definition_id', $recordDefinition->id)
            ->delete();
    }
}

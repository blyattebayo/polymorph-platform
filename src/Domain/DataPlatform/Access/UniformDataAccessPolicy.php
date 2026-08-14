<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Access;

use Illuminate\Database\Query\Builder;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;

/** Implements policies that grant or deny every Data Platform operation uniformly. */
trait UniformDataAccessPolicy
{
    abstract protected function grantsAllDataAccess(): bool;

    public function canReadDefinition(?int $actorId, int $definitionId): bool
    {
        return $this->grantsAllDataAccess();
    }

    public function canWriteDefinition(?int $actorId, int $definitionId): bool
    {
        return $this->grantsAllDataAccess();
    }

    public function canDeleteRecord(?int $actorId, int $definitionId, int $recordId): bool
    {
        return $this->grantsAllDataAccess();
    }

    public function canReadField(?int $actorId, int $definitionId, FieldDefinition $field): bool
    {
        return $this->grantsAllDataAccess();
    }

    public function canWriteField(?int $actorId, int $definitionId, FieldDefinition $field): bool
    {
        return $this->grantsAllDataAccess();
    }

    public function canReadTargetRecord(?int $actorId, int $recordId): bool
    {
        return $this->grantsAllDataAccess();
    }

    public function applyReadableRecordScope(Builder $query, ?int $actorId, int $definitionId): bool
    {
        if (! $this->grantsAllDataAccess()) {
            $query->whereRaw('false');
        }

        return true;
    }

    public function readableTargetRecordIds(?int $actorId, array $recordIds): array
    {
        return $this->grantsAllDataAccess() ? $this->uniqueInts($recordIds) : [];
    }

    public function readableMediaIds(?int $actorId, array $mediaIds): array
    {
        return $this->grantsAllDataAccess() ? $this->uniqueStrings($mediaIds) : [];
    }

    public function attachableRecordIds(?int $actorId, int $sourceDefinitionId, FieldDefinition $field, array $recordIds): array
    {
        return $this->grantsAllDataAccess() ? $this->uniqueInts($recordIds) : [];
    }

    public function attachableMediaIds(?int $actorId, int $sourceDefinitionId, FieldDefinition $field, array $mediaIds): array
    {
        return $this->grantsAllDataAccess() ? $this->uniqueStrings($mediaIds) : [];
    }

    /** @return list<int> */
    private function uniqueInts(array $values): array
    {
        return array_values(array_unique(array_map('intval', $values)));
    }

    /** @return list<string> */
    private function uniqueStrings(array $values): array
    {
        return array_values(array_unique(array_map('strval', $values)));
    }
}

<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Access;

use Illuminate\Database\Query\Builder;
use Polymorph\Platform\Domain\DataPlatform\Fields\FieldDefinition;

interface DataAccessPolicy
{
    public function canReadDefinition(?int $actorId, int $definitionId): bool;

    public function canWriteDefinition(?int $actorId, int $definitionId): bool;

    public function canDeleteRecord(?int $actorId, int $definitionId, int $recordId): bool;

    public function canReadField(?int $actorId, int $definitionId, FieldDefinition $field): bool;

    public function canWriteField(?int $actorId, int $definitionId, FieldDefinition $field): bool;

    public function canReadTargetRecord(?int $actorId, int $recordId): bool;

    /** Returns true when a complete SQL scope was applied (including allow-all). */
    public function applyReadableRecordScope(Builder $query, ?int $actorId, int $definitionId): bool;

    /**
     * @param  list<int>  $recordIds
     * @return list<int>
     */
    public function readableTargetRecordIds(?int $actorId, array $recordIds): array;

    /** @param list<string> $mediaIds @return list<string> */
    public function readableMediaIds(?int $actorId, array $mediaIds): array;

    /** @param list<int> $recordIds @return list<int> */
    public function attachableRecordIds(?int $actorId, int $sourceDefinitionId, FieldDefinition $field, array $recordIds): array;

    /** @param list<string> $mediaIds @return list<string> */
    public function attachableMediaIds(?int $actorId, int $sourceDefinitionId, FieldDefinition $field, array $mediaIds): array;
}

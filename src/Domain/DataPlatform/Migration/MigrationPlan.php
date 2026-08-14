<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\DataPlatform\Migration;

use Polymorph\Platform\Domain\DataPlatform\Serialization\DatabaseJson;

final readonly class MigrationPlan
{
    /** @param list<MigrationOperation> $operations */
    public function __construct(
        public string $id,
        public int $definitionId,
        public string $fromVersionId,
        public string $toVersionId,
        public string $classification,
        public string $state,
        public array $operations,
        public int $failedCount,
    ) {}

    public static function fromRow(object $row, DatabaseJson $json): self
    {
        return new self(
            id: (string) $row->id,
            definitionId: (int) $row->record_definition_id,
            fromVersionId: (string) $row->from_schema_version_id,
            toVersionId: (string) $row->to_schema_version_id,
            classification: (string) $row->classification,
            state: (string) $row->state,
            operations: array_map(
                static fn (mixed $operation): MigrationOperation => MigrationOperation::fromArray((array) $operation),
                $json->decodeList($row->operations, 'dp_schema_migration_plans.operations'),
            ),
            failedCount: (int) $row->failed_count,
        );
    }
}

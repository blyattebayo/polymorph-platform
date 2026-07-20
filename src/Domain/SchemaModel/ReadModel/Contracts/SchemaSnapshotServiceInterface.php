<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\ReadModel\Contracts;

use Polymorph\Platform\Domain\SchemaModel\ReadModel\SchemaSnapshot;

interface SchemaSnapshotServiceInterface
{
    public function snapshotForRootRecordDefinition(int $rootRecordDefinitionId): SchemaSnapshot;

    public function clearCache(?int $recordDefinitionId = null): void;

    public function clearCacheForSchema(int $schemaId): void;
}

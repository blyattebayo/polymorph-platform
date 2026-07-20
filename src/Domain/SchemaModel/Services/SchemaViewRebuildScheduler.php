<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Services;

use Polymorph\Platform\Domain\Materialization\Services\RecordDefinitionViewManager;
use Illuminate\Support\Facades\DB;

final class SchemaViewRebuildScheduler
{
    /**
     * @var array<int, true>
     */
    private array $scheduledSchemaIds = [];

    public function __construct(
        private readonly RecordDefinitionViewManager $viewManager,
    ) {
    }

    public function schedule(int $schemaId): void
    {
        if ($schemaId <= 0 || isset($this->scheduledSchemaIds[$schemaId])) {
            return;
        }

        $this->scheduledSchemaIds[$schemaId] = true;

        DB::afterCommit(function () use ($schemaId): void {
            unset($this->scheduledSchemaIds[$schemaId]);
            $this->viewManager->rebuildForSchema($schemaId);
        });
    }
}

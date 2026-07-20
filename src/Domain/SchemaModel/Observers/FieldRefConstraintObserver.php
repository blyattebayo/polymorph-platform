<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Observers;

use Polymorph\Platform\Domain\SchemaModel\Core\Models\FieldRefConstraint;
use Polymorph\Platform\Domain\SchemaModel\ReadModel\Contracts\SchemaSnapshotServiceInterface;
use Polymorph\Platform\Domain\SchemaModel\Services\ConstraintCache;
use Polymorph\Platform\Domain\SchemaModel\Services\SchemaViewRebuildScheduler;
use Polymorph\Platform\Support\Logging\Contracts\AppLogger;

/**
 * Observer для модели FieldRefConstraint.
 *
 * Обрабатывает изменения constraint и:
 * - Инвалидирует кеш constraints
 */
class FieldRefConstraintObserver
{
    public function __construct(
        private ConstraintCache $constraintCache,
        private SchemaSnapshotServiceInterface $schemaService,
        private SchemaViewRebuildScheduler $rebuildScheduler,
        private readonly AppLogger $logger,
    ) {}

    /**
     * Handle the FieldRefConstraint "created" event.
     */
    public function created(FieldRefConstraint $constraint): void
    {
        $this->refreshSchemaAndViews($constraint);
    }

    /**
     * Handle the FieldRefConstraint "updated" event.
     */
    public function updated(FieldRefConstraint $constraint): void
    {
        $changes = $constraint->getChanges();

        $this->logger->debug('schema.field_ref_constraint.updated', [
            'constraint_id' => $constraint->id,
            'changed_attributes' => array_keys($changes),
        ]);

        // Инвалидируем кеш constraints для этого record_definition
        $this->constraintCache->invalidateForRecordDefinition($constraint->allowed_record_definition_id);

        $this->logger->debug('schema.field_ref_constraint.cache_invalidated', [
            'record_definition_id' => $constraint->allowed_record_definition_id,
        ]);

        $this->refreshSchemaAndViews($constraint);
    }

    public function deleted(FieldRefConstraint $constraint): void
    {
        $this->refreshSchemaAndViews($constraint);
    }

    private function refreshSchemaAndViews(FieldRefConstraint $constraint): void
    {
        $constraint->loadMissing('field');
        $schemaId = (int) ($constraint->field?->schema_id ?? 0);

        if ($schemaId <= 0) {
            return;
        }

        $this->schemaService->clearCacheForSchema($schemaId);
        $this->rebuildScheduler->schedule($schemaId);
    }
}

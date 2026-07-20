<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Pipeline;

use Polymorph\Platform\Domain\SchemaModel\Pipeline\Steps\Combined\EnsureSchemaOwnershipStep;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Steps\Combined\PersistFieldsDeleteStep;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Steps\Combined\PersistFieldsUpsertStep;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Steps\Combined\PersistSchemaStep;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Steps\Combined\SyncFieldsConstraintsStep;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Steps\Combined\ValidateSaveSchemaWithFieldsStep;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Steps\Schema\DeleteSchemaOwnershipStep;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Steps\Schema\PersistDeletedSchemaStep;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Steps\Schema\ValidateDeleteSchemaStep;
use Polymorph\Platform\PipelineCore\Runtime\PipelineDefinition;
use Polymorph\Platform\PipelineCore\Runtime\Stage;

final class SchemaPipelineDefinitions
{
    public function __construct(
        private readonly ValidateSaveSchemaWithFieldsStep $validateSaveSchemaWithFieldsStep,
        private readonly PersistSchemaStep $persistSchemaStep,
        private readonly PersistFieldsUpsertStep $persistFieldsUpsertStep,
        private readonly PersistFieldsDeleteStep $persistFieldsDeleteStep,
        private readonly SyncFieldsConstraintsStep $syncFieldsConstraintsStep,
        private readonly EnsureSchemaOwnershipStep $ensureSchemaOwnershipStep,
        private readonly ValidateDeleteSchemaStep $validateDeleteSchemaStep,
        private readonly PersistDeletedSchemaStep $persistDeletedSchemaStep,
        private readonly DeleteSchemaOwnershipStep $deleteSchemaOwnershipStep,
    ) {
    }

    public function saveWithFields(): PipelineDefinition
    {
        return $this->make(
            name: 'schema.save_with_fields',
            requiresLock: true,
            validationSteps: [$this->validateSaveSchemaWithFieldsStep],
            writeSteps: [
                $this->persistSchemaStep,
                $this->persistFieldsUpsertStep,
                $this->persistFieldsDeleteStep,
            ],
            derivedWriteSteps: [$this->syncFieldsConstraintsStep, $this->ensureSchemaOwnershipStep],
        );
    }

    public function delete(): PipelineDefinition
    {
        return $this->make(
            name: 'schema.delete',
            requiresLock: true,
            validationSteps: [$this->validateDeleteSchemaStep],
            writeSteps: [$this->persistDeletedSchemaStep],
            derivedWriteSteps: [$this->deleteSchemaOwnershipStep],
        );
    }

    private function make(
        string $name,
        bool $requiresLock,
        array $validationSteps = [],
        array $loadAndLockSteps = [],
        array $writeSteps = [],
        array $derivedWriteSteps = [],
    ): PipelineDefinition {
        return new PipelineDefinition(
            name: $name,
            steps: [
                Stage::VALIDATION->value => $validationSteps,
                Stage::LOAD_AND_LOCK->value => $loadAndLockSteps,
                Stage::WRITE->value => $writeSteps,
                Stage::DERIVED_WRITE->value => $derivedWriteSteps,
            ],
            requiresLock: $requiresLock,
        );
    }
}
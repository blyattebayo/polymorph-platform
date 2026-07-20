<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Pipeline;

use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Steps\RecordDefinition\DeleteRecordDefinitionOwnershipStep;
use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Steps\RecordDefinition\EnsureCreatedRecordDefinitionOwnershipStep;
use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Steps\RecordDefinition\PersistCreatedRecordDefinitionStep;
use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Steps\RecordDefinition\PersistDeletedRecordDefinitionStep;
use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Steps\RecordDefinition\PersistUpdatedRecordDefinitionStep;
use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Steps\RecordDefinition\ValidateDeleteRecordDefinitionStep;
use Polymorph\Platform\PipelineCore\Runtime\PipelineDefinition;
use Polymorph\Platform\PipelineCore\Runtime\Stage;

final class RecordDefinitionPipelineDefinitions
{
    public function __construct(
        private readonly PersistCreatedRecordDefinitionStep $persistCreatedRecordDefinitionStep,
        private readonly EnsureCreatedRecordDefinitionOwnershipStep $ensureCreatedRecordDefinitionOwnershipStep,
        private readonly PersistUpdatedRecordDefinitionStep $persistUpdatedRecordDefinitionStep,
        private readonly PersistDeletedRecordDefinitionStep $persistDeletedRecordDefinitionStep,
        private readonly DeleteRecordDefinitionOwnershipStep $deleteRecordDefinitionOwnershipStep,
        private readonly ValidateDeleteRecordDefinitionStep $validateDeleteRecordDefinitionStep,
    ) {}

    public function create(): PipelineDefinition
    {
        return $this->make(
            name: 'record_definition.create',
            requiresLock: false,
            writeSteps: [$this->persistCreatedRecordDefinitionStep],
            derivedWriteSteps: [$this->ensureCreatedRecordDefinitionOwnershipStep],
        );
    }

    public function update(): PipelineDefinition
    {
        return $this->make(
            name: 'record_definition.update',
            requiresLock: true,
            writeSteps: [$this->persistUpdatedRecordDefinitionStep],
        );
    }

    public function delete(): PipelineDefinition
    {
        return $this->make(
            name: 'record_definition.delete',
            requiresLock: true,
            validationSteps: [$this->validateDeleteRecordDefinitionStep],
            writeSteps: [$this->persistDeletedRecordDefinitionStep],
            derivedWriteSteps: [$this->deleteRecordDefinitionOwnershipStep],
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

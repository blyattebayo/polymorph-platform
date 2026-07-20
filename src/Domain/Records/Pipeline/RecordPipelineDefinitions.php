<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\Records\Pipeline;

use Polymorph\Platform\Domain\Records\Pipeline\Steps\Write\ApplyAndPersistStep;
use Polymorph\Platform\Domain\Records\Pipeline\Steps\Write\BuildRecordChangeSetStep;
use Polymorph\Platform\Domain\Records\Pipeline\Steps\Write\CreateRecordModelStep;
use Polymorph\Platform\Domain\Records\Pipeline\Steps\Write\ForbidSystemFieldsStep;
use Polymorph\Platform\Domain\Records\Pipeline\Steps\Write\LoadRecordStep;
use Polymorph\Platform\Domain\Records\Pipeline\Steps\Write\LoadTrashedRecordStep;
use Polymorph\Platform\Domain\Records\Pipeline\Steps\Write\NormalizeSchemaDataStep;
use Polymorph\Platform\Domain\Records\Pipeline\Steps\Write\RestoreRecordStep;
use Polymorph\Platform\Domain\Records\Pipeline\Steps\Write\SoftDeleteRecordStep;
use Polymorph\Platform\Domain\Records\Pipeline\Steps\Write\ValidateSchemaDataStep;
use Polymorph\Platform\Domain\Records\Pipeline\Steps\Write\ValidateUpdateStep;
use Polymorph\Platform\PipelineCore\Runtime\PipelineDefinition;
use Polymorph\Platform\PipelineCore\Runtime\Stage;

final class RecordPipelineDefinitions
{
    public function __construct(
        private readonly ForbidSystemFieldsStep $forbidSystemFieldsStep,
        private readonly ValidateUpdateStep $validateUpdateStep,
        private readonly ValidateSchemaDataStep $validateSchemaDataStep,
        private readonly NormalizeSchemaDataStep $normalizeSchemaDataStep,
        private readonly LoadRecordStep $loadRecordStep,
        private readonly LoadTrashedRecordStep $loadTrashedRecordStep,
        private readonly BuildRecordChangeSetStep $buildRecordChangeSetStep,
        private readonly ApplyAndPersistStep $applyAndPersistStep,
        private readonly CreateRecordModelStep $createRecordModelStep,
        private readonly RestoreRecordStep $restoreRecordStep,
        private readonly SoftDeleteRecordStep $softDeleteRecordStep,
    ) {}

    public function createWrite(): PipelineDefinition
    {
        return $this->make(
            name: 'record.create.write',
            requiresLock: false,
            validationSteps: [
                $this->forbidSystemFieldsStep,
                $this->validateSchemaDataStep,
                $this->normalizeSchemaDataStep,
            ],
            writeSteps: [$this->createRecordModelStep],
        );
    }

    public function updateWrite(): PipelineDefinition
    {
        return $this->make(
            name: 'record.update.write',
            requiresLock: true,
            validationSteps: [
                $this->validateUpdateStep,
                $this->forbidSystemFieldsStep,
                $this->validateSchemaDataStep,
                $this->normalizeSchemaDataStep,
            ],
            loadAndLockSteps: [$this->loadRecordStep],
            writeSteps: [
                $this->buildRecordChangeSetStep,
                $this->applyAndPersistStep,
            ],
        );
    }

    public function delete(): PipelineDefinition
    {
        return $this->make(
            name: 'record.delete',
            requiresLock: true,
            loadAndLockSteps: [$this->loadTrashedRecordStep],
            writeSteps: [$this->softDeleteRecordStep],
        );
    }

    public function restoreWrite(): PipelineDefinition
    {
        return $this->make(
            name: 'record.restore.write',
            requiresLock: true,
            loadAndLockSteps: [$this->loadTrashedRecordStep],
            writeSteps: [$this->restoreRecordStep],
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

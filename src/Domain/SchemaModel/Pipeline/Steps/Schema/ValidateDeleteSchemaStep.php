<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\SchemaModel\Pipeline\Steps\Schema;

use Polymorph\Platform\Domain\SchemaModel\Core\Contracts\SchemaRepository;
use Polymorph\Platform\Domain\SchemaModel\Core\Exceptions\SchemaInUseException;
use Polymorph\Platform\Domain\SchemaModel\Pipeline\Contexts\DeleteSchemaContext;
use Polymorph\Platform\PipelineCore\Runtime\PipelineContext;
use Polymorph\Platform\PipelineCore\Runtime\AbstractStep;
use Polymorph\Platform\PipelineCore\Runtime\StepResult;
use Polymorph\Platform\Support\Errors\ErrorCode;

final class ValidateDeleteSchemaStep extends AbstractStep
{
    public function __construct(
        private readonly SchemaRepository $schemaRepository,
    ) {
        parent::__construct(DeleteSchemaContext::class);}

    public function name(): string
    {
        return 'schema-model.delete.validate';
    }

    public function requires(): array
    {
        return [
            'input.schema',
        ];
    }

    public function run(PipelineContext $context): StepResult
    {
        /** @var DeleteSchemaContext $context */

        $usage = $this->schemaRepository->getUsageInfo($context->schema);

        if ($usage->isInUse()) {
            return StepResult::failure(
                error: SchemaInUseException::create($context->schema->code, $usage->usageCount())->getMessage(),
                metadata: $usage->toConflictMeta(),
                errorCode: ErrorCode::CONFLICT,
                errorTitle: 'Schema is in use',
            );
        }

        return StepResult::success();
    }
}

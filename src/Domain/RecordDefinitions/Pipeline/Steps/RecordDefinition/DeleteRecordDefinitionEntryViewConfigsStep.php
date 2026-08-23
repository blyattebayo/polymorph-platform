<?php

declare(strict_types=1);

namespace Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Steps\RecordDefinition;

use Polymorph\Platform\Domain\UiConfig\Services\ConfigCleaner;
use Polymorph\Platform\Domain\RecordDefinitions\Pipeline\Contexts\DeleteRecordDefinitionContext;
use Polymorph\Platform\PipelineCore\Runtime\AbstractStep;
use Polymorph\Platform\PipelineCore\Runtime\PipelineContext;
use Polymorph\Platform\PipelineCore\Runtime\StepResult;

/**
 * Макеты карточек адресуются определением записи, но внешнего ключа на него не
 * имеют, поэтому осиротевшие строки снимает этот шаг, а не каскад БД.
 */
final class DeleteRecordDefinitionEntryViewConfigsStep extends AbstractStep
{
    public function __construct(
        private readonly ConfigCleaner $uiConfigs,
    ) {
        parent::__construct(DeleteRecordDefinitionContext::class);
    }

    public function name(): string
    {
        return 'record_definition.delete.delete_entry_view_configs';
    }

    public function requires(): array
    {
        return [
            'input.record_definition',
        ];
    }

    public function run(PipelineContext $context): StepResult
    {
        /** @var DeleteRecordDefinitionContext $context */
        $this->uiConfigs->removeForRecordDefinition((int) $context->recordDefinition->id);

        return StepResult::success();
    }
}

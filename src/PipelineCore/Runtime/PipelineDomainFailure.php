<?php

declare(strict_types=1);

namespace Polymorph\Platform\PipelineCore\Runtime;

final class PipelineDomainFailure extends PipelineResult
{
    /**
     * @param  array<string, StageResult>  $stageResults
     * @param  array<string, StageResult>  $warnings
     */
    public function __construct(
        array $stageResults,
        array $warnings,
        public readonly Stage $failedStage,
        public readonly StageResult $failedStageResult,
        public readonly ?string $failureMessage,
    ) {
        parent::__construct(
            stageResults: $stageResults,
            warnings: $warnings,
        );
    }

}

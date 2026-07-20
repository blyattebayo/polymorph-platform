<?php

declare(strict_types=1);

namespace Polymorph\Platform\PipelineCore\Runtime;

abstract class PipelineResult
{
    /**
     * @param array<string, StageResult> $stageResults
     * @param array<string, StageResult> $warnings
     */
    public function __construct(
        public readonly array $stageResults,
        public readonly array $warnings = [],
    ) {}

    /**
     * @param array<string, StageResult> $stageResults
     */
    public static function success(array $stageResults, array $warnings = []): self
    {
        return new PipelineSuccess(
            stageResults: $stageResults,
            warnings: $warnings,
        );
    }

    /**
     * @param array<string, StageResult> $priorStageResults
     */
    public static function domainFailure(
        Stage $failedStage,
        StageResult $failedStageResult,
        array $priorStageResults = [],
        ?string $message = null,
    ): self {
        $stageResults = $priorStageResults;
        $stageResults[$failedStage->value] = $failedStageResult;

        return new PipelineDomainFailure(
            stageResults: $stageResults,
            warnings: [],
            failedStage: $failedStage,
            failedStageResult: $failedStageResult,
            failureMessage: $message ?? $failedStageResult->errorMessage(),
        );
    }

}

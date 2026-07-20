<?php

declare(strict_types=1);

namespace Polymorph\Platform\PipelineCore\Runtime;

final class PipelineSuccess extends PipelineResult
{
    /**
     * @param array<string, StageResult> $stageResults
     * @param array<string, StageResult> $warnings
     */
    public function __construct(
        array $stageResults,
        array $warnings = [],
    ) {
        parent::__construct(
            stageResults: $stageResults,
            warnings: $warnings,
        );
    }

}

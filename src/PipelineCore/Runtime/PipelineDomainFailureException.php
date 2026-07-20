<?php

declare(strict_types=1);

namespace Polymorph\Platform\PipelineCore\Runtime;

final class PipelineDomainFailureException extends \RuntimeException
{
    public function __construct(
        public readonly PipelineDomainFailure $result,
        public readonly string $fallbackMessage,
    ) {
        parent::__construct($fallbackMessage);
    }
}

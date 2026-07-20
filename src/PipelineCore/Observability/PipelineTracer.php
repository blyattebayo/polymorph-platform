<?php

declare(strict_types=1);

namespace Polymorph\Platform\PipelineCore\Observability;

class PipelineTracer
{
    public function __construct(
        private readonly PipelineLogger $logger,
    ) {}

    public function startTrace(string $pipelineName, OperationId $operationId): TraceContext
    {
        return new TraceContext(
            pipelineName: $pipelineName,
            operationId: $operationId,
            startTime: microtime(true),
            logger: $this->logger,
        );
    }
}

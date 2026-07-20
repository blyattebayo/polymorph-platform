<?php

declare(strict_types=1);

namespace Polymorph\Platform\PipelineCore\Runtime;

use Polymorph\Platform\PipelineCore\Locking\LockKey;
use Polymorph\Platform\PipelineCore\Observability\OperationId;
use Polymorph\Platform\PipelineCore\Tx\TransactionManager;

class TransactionalPipelineRunner
{
    public function __construct(
        private readonly PipelineExecutor $executor,
        private readonly TransactionManager $txManager,
    ) {}

    /**
     * Исполнить пайплайн В ТРАНЗАКЦИИ
     */
    public function runInTransaction(
        PipelineDefinition $definition,
        PipelineContext $context,
        OperationId $operationId,
        ?LockKey $lockKey = null,
    ): PipelineSuccess {
        $successfulResult = null;

        $this->txManager->runInTransaction(function () use (
            $definition,
            $context,
            $operationId,
            $lockKey,
            &$successfulResult,
        ): void {
            $result = $this->executor->execute($definition, $context, $operationId, $lockKey);

            if ($result instanceof PipelineDomainFailure) {
                throw new PipelineDomainFailureException($result, $result->failureMessage ?? 'Pipeline returned domain failure result.');
            }

            $successfulResult = $result;
        });

        return $successfulResult
            ?? throw new \LogicException('Pipeline execution did not produce result.');
    }
}

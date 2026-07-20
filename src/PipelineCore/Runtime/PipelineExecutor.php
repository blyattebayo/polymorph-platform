<?php

declare(strict_types=1);

namespace Polymorph\Platform\PipelineCore\Runtime;

use Polymorph\Platform\PipelineCore\Locking\LockManager;
use Polymorph\Platform\PipelineCore\Locking\LockKey;
use Polymorph\Platform\PipelineCore\Observability\OperationId;
use Polymorph\Platform\PipelineCore\Observability\PipelineLogger;
use Polymorph\Platform\PipelineCore\Observability\PipelineTracer;
use Polymorph\Platform\PipelineCore\Observability\TraceContext;

class PipelineExecutor
{
    public function __construct(
        private readonly LockManager $lockManager,
        private readonly PipelineLogger $logger,
        private readonly PipelineTracer $tracer,
    ) {}

    /**
     * Исполнить пайплайн БЕЗ транзакции
     * 
     * ⚠️ Handler/use-case сам решает, оборачивать ли в транзакцию
     * 
     * @param PipelineDefinition $definition
     * @param object $context Типизированный контекст (EntryWriteContext, etc.)
     * @param OperationId $operationId Для трассировки
     * @param LockKey|null $lockKey Ключ блокировки (если null, берётся из context)
     * @return PipelineResult
     */
    public function execute(
        PipelineDefinition $definition,
        PipelineContext $context,
        OperationId $operationId,
        ?LockKey $lockKey = null,
    ): PipelineResult {
        $this->logger->info('Pipeline started', [
            'pipeline' => $definition->name,
            'operation_id' => $operationId->value,
        ]);

        $trace = $this->tracer->startTrace($definition->name, $operationId);

        try {
            // 1. Acquire lock if required
            if ($definition->requiresLock) {
                $lock = $lockKey ?? $this->resolveLockKey($context);
                $this->acquireLock($lock, $trace);
            }

            // 2. Execute stages in order
            $stageResults = [];
            $warnings = [];
            foreach ($definition->orderedStages() as $stage) {

                $stageResult = $this->executeStage(
                    stage: $stage,
                    definition: $definition,
                    context: $context,
                    trace: $trace,
                );

                if (!$stageResult->success) {
                    if (!$stage->isBlocking()) {
                        $warnings[$stage->value] = $stageResult;
                        $stageResults[$stage->value] = $stageResult;

                        $trace->recordEvent('pipeline_stage_non_blocking_failed', [
                            'stage' => $stage->value,
                            'error' => $stageResult->errorMessage(),
                        ]);

                        $this->logger->warning('Non-blocking stage failed', [
                            'pipeline' => $definition->name,
                            'stage' => $stage->value,
                            'error' => $stageResult->errorMessage(),
                        ]);

                        continue;
                    }

                    $trace->recordEvent('pipeline_failed', [
                        'stage' => $stage->value,
                        'error' => $stageResult->errorMessage(),
                    ]);

                    return PipelineResult::domainFailure(
                        failedStage: $stage,
                        failedStageResult: $stageResult,
                        priorStageResults: $stageResults,
                        message: $stageResult->errorMessage(),
                    );
                }

                $stageResults[$stage->value] = $stageResult;
            }

            return PipelineResult::success(
                stageResults: $stageResults,
                warnings: $warnings,
            );
        } catch (\Throwable $e) {
            $this->logger->error('Pipeline failed with unhandled exception', [
                'pipeline' => $definition->name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $trace->recordException($e);
            throw $e;
        } finally {
            $trace->finish();

            $this->logger->info('Pipeline finished', [
                'pipeline' => $definition->name,
                'duration_ms' => $trace->getDurationMs(),
            ]);
        }
    }

    /**
     * Исполнить стадию: запустить все шаги последовательно
     */
    private function executeStage(
        Stage $stage,
        PipelineDefinition $definition,
        PipelineContext $context,
        TraceContext $trace,
    ): StageResult {
        $steps = $definition->getStepsForStage($stage);

        if (empty($steps)) {
            return StageResult::empty($stage);
        }

        $trace->startStage($stage);

        $stepResults = [];

        foreach ($steps as $step) {
            $expectedContextClass = $step->contextClass();
            if (!$context instanceof $expectedContextClass) {
                throw new \LogicException(sprintf(
                    "Step '%s' expects context '%s', got '%s'",
                    $step->name(),
                    $expectedContextClass,
                    $context::class,
                ));
            }

            if (!$step->shouldRun($context)) {
                $this->logger->debug('Step skipped', [
                    'step' => $step->name(),
                    'stage' => $stage->value,
                ]);
                continue;
            }

            $trace->startStep($step->name());
            try {
                $result = $step->run($context);
            } finally {
                $trace->endStep($step->name());
            }

            $stepResults[$step->name()] = $result;

            if (!$result->success) {
                $this->logger->warning('Step failed', [
                    'step' => $step->name(),
                    'stage' => $stage->value,
                    'error' => $result->errorMessage,
                    'cause' => $result->cause !== null
                        ? $result->cause::class . ': ' . $result->cause->getMessage()
                        : null,
                ]);

                $trace->endStage($stage, success: false);

                return StageResult::failed(
                    stage: $stage,
                    stepResults: $stepResults,
                    failedStep: $result,
                );
            }
        }

        $trace->endStage($stage, success: true);

        return StageResult::succeeded(
            stage: $stage,
            stepResults: $stepResults,
        );
    }

    /**
     * Acquire advisory lock
     */
    private function acquireLock(LockKey $lockKey, TraceContext $trace): void
    {
        $trace->recordEvent('lock_acquire_attempt', [
            'resource_type' => $lockKey->resourceType,
            'resource_id' => $lockKey->resourceId,
        ]);

        $this->lockManager->acquireLock($lockKey);

        $trace->recordEvent('lock_acquired', [
            'resource_type' => $lockKey->resourceType,
            'resource_id' => $lockKey->resourceId,
        ]);
    }

    /**
     * Resolve lock key from context contract
     */
    private function resolveLockKey(PipelineContext $context): LockKey
    {
        if ($context instanceof LockableContext) {
            return $context->getLockKey();
        }

        throw new \InvalidArgumentException(
            'Context must implement LockableContext or provide explicit lock key'
        );
    }
}

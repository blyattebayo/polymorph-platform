<?php

declare(strict_types=1);

namespace Polymorph\Platform\PipelineCore\Observability;

use Polymorph\Platform\PipelineCore\Runtime\Stage;

class TraceContext
{
    private ?float $endTime = null;

    /** @var array<string, array{start: float, end: float|null, success?: bool}> */
    private array $stages = [];

    /** @var array<string, array{start: float, end: float|null}> */
    private array $steps = [];

    /** @var array<int, array{name: string, timestamp: float, data: array}> */
    private array $events = [];

    public function __construct(
        private readonly string $pipelineName,
        private readonly OperationId $operationId,
        private readonly float $startTime,
        private readonly PipelineLogger $logger,
    ) {
        $this->logger->debug('Trace started', [
            'pipeline' => $this->pipelineName,
            'operation_id' => $this->operationId->value,
        ]);
    }

    public function startStage(Stage $stage): void
    {
        $this->stages[$stage->value] = [
            'start' => microtime(true),
            'end' => null,
        ];
    }

    public function endStage(Stage $stage, bool $success): void
    {
        if (!isset($this->stages[$stage->value])) {
            return;
        }

        $this->stages[$stage->value]['end'] = microtime(true);
        $this->stages[$stage->value]['success'] = $success;
    }

    public function startStep(string $stepName): void
    {
        $this->steps[$stepName] = [
            'start' => microtime(true),
            'end' => null,
        ];
    }

    public function endStep(string $stepName): void
    {
        if (!isset($this->steps[$stepName])) {
            return;
        }

        $this->steps[$stepName]['end'] = microtime(true);
    }

    public function recordEvent(string $eventName, array $data = []): void
    {
        $this->events[] = [
            'name' => $eventName,
            'timestamp' => microtime(true),
            'data' => $data,
        ];
    }

    public function recordException(\Throwable $e): void
    {
        $this->recordEvent('exception', [
            'class' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }

    public function finish(): void
    {
        $this->endTime = microtime(true);

        $this->logger->info('Trace finished', [
            'pipeline' => $this->pipelineName,
            'operation_id' => $this->operationId->value,
            'duration_ms' => $this->getDurationMs(),
            'stages' => $this->getStageDurations(),
            'steps' => $this->getStepDurations(),
        ]);
    }

    public function getDurationMs(): float
    {
        $end = $this->endTime ?? microtime(true);
        return ($end - $this->startTime) * 1000;
    }

    /**
     * @return array<string, float>
     */
    public function getStageDurations(): array
    {
        $durations = [];
        foreach ($this->stages as $name => $timing) {
            if ($timing['end'] !== null) {
                $durations[$name] = ($timing['end'] - $timing['start']) * 1000;
            }
        }

        return $durations;
    }

    /**
     * @return array<string, float>
     */
    public function getStepDurations(): array
    {
        $durations = [];
        foreach ($this->steps as $name => $timing) {
            if ($timing['end'] !== null) {
                $durations[$name] = ($timing['end'] - $timing['start']) * 1000;
            }
        }

        return $durations;
    }
}

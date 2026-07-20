<?php

declare(strict_types=1);

namespace Polymorph\Platform\PipelineCore\Runtime;

interface Step
{
    /**
     * Unique step name for tracing/debugging
     */
    public function name(): string;

    /**
     * Expected context class for this step.
     *
     * @return class-string<PipelineContext>
     */
    public function contextClass(): string;
    
    /**
     * Should this step run given current context state?
     */
    public function shouldRun(PipelineContext $context): bool;

    /**
     * Context slots that must be available before step execution.
     *
     * @return string[]
     */
    public function requires(): array;

    /**
     * Context slots that this step guarantees to populate/update.
     *
     * @return string[]
     */
    public function produces(): array;
    
    /**
     * Execute the step logic
     */
    public function run(PipelineContext $context): StepResult;
}
